<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Campaign\ExtendCampaignDeadlineAction;
use App\Actions\Campaign\UpdateClosedCampaignPaymentAccountAction;
use App\Actions\Campaign\CloseCampaignAction;
use App\Actions\Campaign\CreateCampaignAction;
use App\Actions\Campaign\CreateCampaignItemAction;
use App\Actions\Campaign\CreateItemOptionAction;
use App\Actions\Campaign\DuplicateCampaignAction;
use App\Actions\Campaign\SplitCampaignBillAction;
use App\Actions\Campaign\TransitionCampaignAction;
use App\Actions\Campaign\UpdateCampaignAction;
use App\Actions\Campaign\UpdateCampaignItemAction;
use App\Actions\Debt\ConfirmCampaignDebtsPaidAction;
use App\Enums\PaymentAccountStatus;
use App\Enums\PaymentMethod;
use App\Enums\CampaignStatus;
use App\Enums\OrderStatus;
use App\Enums\RoomUserStatus;
use App\Events\CampaignUpdated;
use App\Events\RoomRealtimeEvent;
use App\Exports\CampaignAggregateExport;
use App\Exports\CampaignDetailExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ExtendCampaignDeadlineRequest;
use App\Http\Requests\UpdateCampaignPaymentAccountRequest;
use App\Http\Requests\BatchUpdateCampaignItemStatusRequest;
use App\Http\Requests\CampaignPageRequest;
use App\Http\Requests\CloseCampaignRequest;
use App\Http\Requests\ConfirmCampaignDebtsPaidRequest;
use App\Http\Requests\SplitBillRequest;
use App\Http\Requests\StoreCampaignItemRequest;
use App\Http\Requests\StoreCampaignImageRequest;
use App\Http\Requests\StoreCampaignRequest;
use App\Http\Requests\StoreItemOptionRequest;
use App\Http\Requests\UpdateCampaignItemStatusRequest;
use App\Http\Requests\UpdateCampaignRequest;
use App\Http\Requests\UpdateItemOptionRequest;
use App\Models\Campaign;
use App\Models\CampaignItem;
use App\Models\CampaignItemSize;
use App\Models\CampaignItemTopping;
use App\Models\Room;
use App\Services\Admin\PreviousCampaignMenuService;
use App\Services\Audit\AuditService;
use App\Services\Order\PublicOrderCheckService;
use App\Services\Media\ImageUploadService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\URL;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class CampaignController extends Controller
{
    /**
     * Display a listing of campaigns for the current room.
     *
     * @param Request $request Incoming HTTP request.
     * @return JsonResponse Paginated campaign list.
     */
    public function index(Request $request): JsonResponse
    {
        $room = $request->attributes->get('room');
        $query = Campaign::query()
            ->where('room_id', $room->id)
            ->withCount('orders')
            ->when($request->filled('status'), fn($query) => $query->where('status', $request->string('status')->toString()))
            ->latest();

        return response()->json(['data' => $query->paginate(\App\Constants\Pagination::ADMIN_PER_PAGE)]);
    }

    /**
     * Display the standalone Campaigns management view.
     *
     * @param CampaignPageRequest $request Incoming HTTP request.
     * @param Room $room Room entity.
     * @return View Blade view.
     */
    public function page(CampaignPageRequest $request, Room $room): View
    {
        $validated = $request->validated();
        // Same basis as the close summary (AdminCampaignDetailService): only non-cancelled orders count.
        $validOrders = static fn ($orderQuery) => $orderQuery
            ->where('status', '!=', OrderStatus::Cancelled->value)
            ->whereNull('cancelled_at');
        $query = Campaign::query()
            ->where('room_id', $room->id)
            ->withCount('orders')
            ->withSum(['orders as gross_subtotal' => $validOrders], 'subtotal')
            ->withSum(['orderItems as total_items' => static fn ($itemQuery) => $itemQuery
                ->where('orders.status', '!=', OrderStatus::Cancelled->value)
                ->whereNull('orders.cancelled_at')], 'order_items.quantity')
            ->latest();

        $search = trim((string) ($validated['search'] ?? ''));
        if ($search !== '') {
            $normalizedSearch = mb_strtolower($search);
            $query->where(function ($campaignQuery) use ($normalizedSearch): void {
                // Only the campaign code, campaign name and restaurant name are searchable.
                $campaignQuery->whereRaw('LOWER(code) LIKE ?', ['%' . $normalizedSearch . '%'])
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%' . $normalizedSearch . '%'])
                    ->orWhereRaw('LOWER(restaurant) LIKE ?', ['%' . $normalizedSearch . '%']);
            });
        }

        $status = (string) ($validated['status'] ?? 'all');
        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        $sponsorType = (string) ($validated['sponsor_type'] ?? 'all');
        if ($sponsorType !== '' && $sponsorType !== 'all') {
            $query->where('sponsor_type', $sponsorType);
        }

        $campaigns = $query->paginate(\App\Constants\Pagination::ADMIN_PER_PAGE)->withQueryString();

        return view('admin.campaigns', [
            'room' => $room,
            'campaigns' => $campaigns,
            'hasLiveCampaign' => $room->hasActiveCampaign(),
            'statusFilters' => collect(CampaignStatus::cases())
                ->filter(static fn(CampaignStatus $status): bool => in_array($status, [
                    CampaignStatus::Active,
                    CampaignStatus::Scheduled,
                    CampaignStatus::Closed,
                    CampaignStatus::Archived,
                ], true))
                ->map(static fn(CampaignStatus $status): array => [
                    'value' => $status->value,
                    'label' => __('admin.filter_' . $status->value),
                ])->values()->all(),
            'sponsorTypeFilters' => array_map(static fn(string $type): array => [
                'value' => $type,
                'label' => __('admin.sponsor_type_' . $type),
            ], Campaign::SPONSOR_TYPES),
            'filters' => [
                'search' => $search,
                'status' => $status !== '' ? $status : 'all',
                'sponsor_type' => $sponsorType !== '' ? $sponsorType : 'all',
            ],
        ]);
    }

    /**
     * Show the campaign creation interface.
     *
     * @param Request $request Incoming HTTP request.
     * @param Room $room Room entity.
     * @param PreviousCampaignMenuService $previousMenus Supplies the first page of closed campaigns for menu reuse.
     * @return View|JsonResponse|RedirectResponse Blade view, JSON response or redirect when a campaign is still running.
     */
    public function create(Request $request, Room $room, PreviousCampaignMenuService $previousMenus): View|JsonResponse|RedirectResponse
    {
        $room = $request->attributes->get('room') ?? $room;
        if ($room->hasActiveCampaign()) {
            abort_if($request->wantsJson(), 422, __('admin.campaign_running_exists'));

            return redirect()->route('admin.campaigns.page', $room)
                ->with('error', __('admin.campaign_running_exists'));
        }
        $paymentAccounts = $room->paymentAccounts()->where('status', PaymentAccountStatus::Active)->get();
        $settings = $room->roomSettings()
            ->whereIn('key', ['campaign_title_template', 'max_campaign_budget', 'default_payment_account_id', 'default_sponsor'])
            ->get()
            ->keyBy('key');
        $titleTemplate = (string) ($settings->get('campaign_title_template')?->value ?? ('[' . $room->name . '] Trà chiều & Cafe {date}'));
        $creatorName = (string) ($request->user('admin')?->name ?? '');
        $campaignDefaults = [
            'name' => strtr($titleTemplate, [
                '{date}' => now()->format('d/m/Y'),
                '{time}' => now()->format('H:i'),
                '{day_of_week}' => now()->translatedFormat('l'),
                '{creator_name}' => $creatorName,
            ]),
            'max_budget' => (int) ($settings->get('max_campaign_budget')?->value ?? 70_000),
            'payment_account_id' => (int) ($settings->get('default_payment_account_id')?->value ?? 0),
            'sponsor_name' => (string) ($settings->get('default_sponsor')?->value ?? ''),
        ];
        if (!$paymentAccounts->contains('id', $campaignDefaults['payment_account_id'])) {
            $campaignDefaults['payment_account_id'] = (int) ($paymentAccounts->first()?->id ?? 0);
        }
        $roomUsers = $room->roomUsers()->with('globalUser')->where('status', RoomUserStatus::Active)->get();
        $previousPage = $previousMenus->page($room);

        if ($request->wantsJson()) {
            return response()->json([
                'room' => $room,
                'payment_accounts' => $paymentAccounts,
                'room_users' => $roomUsers,
                'previous_campaigns' => $previousPage['campaigns'],
                'campaign_defaults' => $campaignDefaults,
            ]);
        }

        return view('admin.campaign-create', [
            'room' => $room,
            'paymentAccounts' => $paymentAccounts,
            'roomUsers' => $roomUsers,
            'previousCampaigns' => $previousPage['campaigns'],
            'previousCampaignsHasMore' => $previousPage['has_more'],
            'campaignDefaults' => $campaignDefaults,
        ]);
    }

    /**
     * Retrieve a page of previous (closed) campaigns with menu items for fast reuse ("Xem thêm").
     *
     * @param Request $request Query: offset (default 0), limit (default 4, max 20), exclude (campaign ID being edited).
     * @param Room $room Room entity.
     * @param PreviousCampaignMenuService $previousMenus Paginates the room's closed campaigns.
     * @return JsonResponse Campaigns under `data`, plus `meta.has_more` and `meta.next_offset`.
     */
    public function previousMenus(Request $request, Room $room, PreviousCampaignMenuService $previousMenus): JsonResponse
    {
        $validated = $request->validate([
            'offset' => ['nullable', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.PreviousCampaignMenuService::MAX_PAGE_SIZE],
            'exclude' => ['nullable', 'integer'],
        ]);

        $page = $previousMenus->page(
            $room,
            (int) ($validated['offset'] ?? 0),
            (int) ($validated['limit'] ?? PreviousCampaignMenuService::PAGE_SIZE),
            isset($validated['exclude']) ? (int) $validated['exclude'] : null,
        );

        return response()->json([
            'data' => $page['campaigns'],
            'meta' => ['has_more' => $page['has_more'], 'next_offset' => $page['next_offset']],
        ]);
    }

    /**
     * Display the campaign overview & information view.
     *
     * @param Request $request Incoming HTTP request.
     * @param Room $room Room entity.
     * @param Campaign $campaign Campaign entity.
     * @param \App\Services\Admin\AdminCampaignDetailService $detailService Campaign detail service.
     * @param PublicOrderCheckService $orderCheckService Public order check service.
     * @return JsonResponse|View Response payload or Blade view.
     */
    public function showInfo(Request $request, Room $room, Campaign $campaign, \App\Services\Admin\AdminCampaignDetailService $detailService, PublicOrderCheckService $orderCheckService): JsonResponse|View
    {
        $this->assertCampaign($campaign);

        if ($request->expectsJson()) {
            $campaign->load(['items.sizes', 'items.toppings', 'paymentAccount', 'orders.roomUser.globalUser', 'orders.items', 'debts.roomUser.globalUser']);
            return response()->json(['data' => $campaign]);
        }

        $data = $detailService->getCampaignViewData($room, $campaign);
        $data['orderCheckUrl'] = $orderCheckService->isCheckable($campaign)
            ? URL::temporarySignedRoute(
                'public.order-check',
                now()->addDays(30),
                ['campaign' => $campaign->id, 'hash' => $orderCheckService->hash($campaign)]
            )
            : null;

        $data['adjustablePaymentAccounts'] = $campaign->isLocked()
            ? $room->paymentAccounts()->where('status', PaymentAccountStatus::Active)->orderByDesc('is_default')->get()
            : collect();

        return view('admin.campaign-info', $data);
    }

    /**
     * Display the menu availability page where items can be switched on or off.
     *
     * @param Room $room Room entity.
     * @param Campaign $campaign Campaign entity.
     * @return View Blade view listing the campaign menu items.
     */
    public function showMenu(Room $room, Campaign $campaign): View
    {
        $this->assertCampaign($campaign);

        return view('admin.campaign-menu', ['room' => $room, 'campaign' => $campaign]);
    }

    /**
     * Display the campaign orders and items list view.
     *
     * @param Request $request Incoming HTTP request.
     * @param Room $room Room entity.
     * @param Campaign $campaign Campaign entity.
     * @param \App\Services\Admin\AdminCampaignDetailService $detailService Campaign detail service.
     * @param PublicOrderCheckService $orderCheckService Public order check service.
     * @param \App\Services\Admin\AdminOrderOnBehalfService $onBehalf Data of the "order on behalf" / "edit order" modal.
     * @return JsonResponse|View Response payload or Blade view.
     */
    public function showOrders(Request $request, Room $room, Campaign $campaign, \App\Services\Admin\AdminCampaignDetailService $detailService, PublicOrderCheckService $orderCheckService, \App\Services\Admin\AdminOrderOnBehalfService $onBehalf): JsonResponse|View
    {
        $this->assertCampaign($campaign);

        if ($request->expectsJson()) {
            $campaign->load(['items.sizes', 'items.toppings', 'paymentAccount', 'orders.roomUser.globalUser', 'orders.items', 'debts.roomUser.globalUser']);
            return response()->json(['data' => $campaign]);
        }

        $data = $detailService->getCampaignViewData($room, $campaign);
        $data['orderCheckUrl'] = $orderCheckService->isCheckable($campaign)
            ? URL::temporarySignedRoute(
                'public.order-check',
                now()->addDays(30),
                ['campaign' => $campaign->id, 'hash' => $orderCheckService->hash($campaign)]
            )
            : null;
        // Same "order on behalf" / "edit order" modal as the orders page; null once the campaign is not live.
        $data['onBehalfData'] = $onBehalf->formData($room, $campaign);

        return view('admin.campaign-orders', $data);
    }

    /** Confirm all outstanding debts for this campaign as paid. */
    public function confirmDebtsPaid(ConfirmCampaignDebtsPaidRequest $request, Room $room, Campaign $campaign, ConfirmCampaignDebtsPaidAction $action): JsonResponse
    {
        $this->assertCampaign($campaign);

        $count = $action->execute($campaign, PaymentMethod::from($request->validated('payment_method')), $request->user('admin')?->id);

        return response()->json([
            'data' => ['count' => $count],
            'message' => __('admin.bulk_debts_paid_success', ['count' => $count]),
        ]);
    }

    /**
     * Return the campaign as JSON, or redirect HTML requests to the matching campaign page.
     *
     * @param Request $request Incoming HTTP request.
     * @param Room $room Room entity.
     * @param Campaign $campaign Campaign entity.
     * @return JsonResponse|RedirectResponse JSON payload or redirect to the info/orders page.
     */
    public function show(Request $request, Room $room, Campaign $campaign): JsonResponse|RedirectResponse
    {
        $this->assertCampaign($campaign);

        if ($request->expectsJson()) {
            $campaign->load(['items.sizes', 'items.toppings', 'paymentAccount', 'orders.roomUser.globalUser', 'orders.items', 'debts.roomUser.globalUser']);
            return response()->json(['data' => $campaign]);
        }

        $routeName = in_array($request->query('view'), ['orders', 'items', 'detail'], true)
            ? 'admin.campaigns.orders'
            : 'admin.campaigns.info';

        return redirect()->route($routeName, [$room, $campaign]);
    }

    /**
     * Download one dataset from the campaign detail tabs as an Excel workbook.
     *
     * @param Room $room Current room.
     * @param Campaign $campaign Campaign being exported.
     * @param string $dataset Export dataset key.
     * @param \App\Services\Admin\AdminCampaignDetailService $detailService Campaign detail data service.
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse Excel download response.
     */
    public function exportDetail(Room $room, Campaign $campaign, string $dataset, \App\Services\Admin\AdminCampaignDetailService $detailService): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $this->assertCampaign($campaign);
        abort_unless(in_array($dataset, ['aggregated', 'orders', 'departments', 'debts', 'declined', 'unresponsive'], true), 404);
        $data = $detailService->getCampaignViewData($room, $campaign);

        return Excel::download(new CampaignDetailExport($dataset, $data), 'campaign-'.$campaign->code.'-'.$dataset.'.xlsx');
    }

    /**
     * Show the campaign editing interface.
     *
     * @param Request $request Incoming HTTP request.
     * @param Room $room Room entity.
     * @param Campaign $campaign Campaign entity.
     * @param PreviousCampaignMenuService $previousMenus Supplies the first page of closed campaigns for menu reuse.
     * @return View|JsonResponse|RedirectResponse Blade view, JSON response or redirect when the campaign is locked.
     */
    public function edit(Request $request, Room $room, Campaign $campaign, PreviousCampaignMenuService $previousMenus): View|JsonResponse|RedirectResponse
    {
        $this->assertCampaign($campaign);
        if ($campaign->isLocked()) {
            abort_if($request->wantsJson(), 422, __('admin.campaign_locked_cannot_modify'));

            return redirect()->route('admin.campaigns.info', [$room, $campaign])
                ->with('error', __('admin.campaign_locked_cannot_modify'));
        }
        $room = $request->attributes->get('room') ?? $room;
        $campaign->load(['items.sizes', 'items.toppings', 'paymentAccount']);
        $paymentAccounts = $room->paymentAccounts()->where('status', PaymentAccountStatus::Active)->get();
        $settings = $room->roomSettings()
            ->whereIn('key', ['max_campaign_budget', 'default_payment_account_id'])
            ->get()
            ->keyBy('key');
        $maxBudget = (int) ($settings->get('max_campaign_budget')?->value ?? 70_000);
        $roomUsers = $room->roomUsers()->with('globalUser')->where('status', RoomUserStatus::Active)->get();
        $previousPage = $previousMenus->page($room, 0, PreviousCampaignMenuService::PAGE_SIZE, $campaign->id);
        $previousCampaigns = $previousPage['campaigns'];

        if ($request->wantsJson()) {
            return response()->json([
                'room' => $room,
                'campaign' => $campaign,
                'payment_accounts' => $paymentAccounts,
                'room_users' => $roomUsers,
                'previous_campaigns' => $previousCampaigns,
                'max_budget' => $maxBudget,
            ]);
        }

        return view('admin.campaign-edit', [
            'room' => $room,
            'campaign' => $campaign,
            'paymentAccounts' => $paymentAccounts,
            'roomUsers' => $roomUsers,
            'previousCampaigns' => $previousCampaigns,
            'previousCampaignsHasMore' => $previousPage['has_more'],
            'maxBudget' => $maxBudget,
        ]);
    }

    /**
     * Handle the update operation for a campaign.
     *
     * @param UpdateCampaignRequest $request Incoming validated update request.
     * @param Room $room Room entity.
     * @param Campaign $campaign Campaign entity.
     * @param UpdateCampaignAction $updateAction Campaign update action.
     * @return JsonResponse Result of the operation.
     */
    public function update(UpdateCampaignRequest $request, Room $room, Campaign $campaign, UpdateCampaignAction $updateAction): JsonResponse
    {
        $this->assertCampaignEditable($campaign);
        $data = $request->validated();
        $notifyMembers = (bool) ($data['notify_members'] ?? true);
        unset($data['notify_members']);
        $adminId = $request->user('admin')?->id;
        $updatedCampaign = $updateAction->execute($campaign, $data, $adminId, $notifyMembers);
        $this->publishCampaignEvent('campaign.updated', $updatedCampaign);

        return response()->json([
            'message' => __('admin.campaign_updated_successfully'),
            'data' => $updatedCampaign,
        ]);
    }

    /**
     * Change the receiving bank account of a closed campaign.
     *
     * @param UpdateCampaignPaymentAccountRequest $request Validated request carrying the new account ID.
     * @param Room $room Current room.
     * @param Campaign $campaign Closed campaign to adjust.
     * @param UpdateClosedCampaignPaymentAccountAction $action Payment account update action.
     * @return JsonResponse Updated payment account and a human readable message.
     */
    public function updatePaymentAccount(UpdateCampaignPaymentAccountRequest $request, Room $room, Campaign $campaign, UpdateClosedCampaignPaymentAccountAction $action): JsonResponse
    {
        $this->assertCampaign($campaign);
        $updated = $action->execute($campaign, (int) $request->validated('payment_account_id'), $request->user('admin')?->id);

        return response()->json([
            'message' => __('admin.campaign_payment_account_updated'),
            'data' => ['payment_account' => $updated->paymentAccount],
        ]);
    }

    /**
     * Extend the ordering deadline of a live campaign by a fixed number of minutes.
     *
     * @param ExtendCampaignDeadlineRequest $request Validated request carrying the minutes to add.
     * @param Room $room Current room.
     * @param Campaign $campaign Campaign whose deadline is extended.
     * @param ExtendCampaignDeadlineAction $action Deadline extension action.
     * @return JsonResponse Updated campaign and a human readable message.
     */
    public function extendDeadline(ExtendCampaignDeadlineRequest $request, Room $room, Campaign $campaign, ExtendCampaignDeadlineAction $action): JsonResponse
    {
        $this->assertCampaignEditable($campaign);
        $minutes = (int) $request->validated('minutes');
        $updated = $action->execute($campaign, $minutes, $request->user('admin')?->id);
        $this->publishCampaignEvent('campaign.updated', $updated);

        return response()->json([
            'message' => __('admin.extend_deadline_success', [
                'minutes' => $minutes,
                'deadline' => $updated->deadline?->format('H:i d/m/Y'),
            ]),
            'data' => $updated,
        ]);
    }

    /**
     * Permanently delete an archived campaign that has no financial history.
     *
     * @param Room $room Current room.
     * @param Campaign $campaign Campaign to delete.
     * @param ImageUploadService $imageUploadService Image storage cleanup service.
     * @return JsonResponse Deletion result.
     */
    public function destroy(Room $room, Campaign $campaign, ImageUploadService $imageUploadService): JsonResponse
    {
        $this->assertCampaign($campaign);
        abort_unless(in_array($campaign->status, [CampaignStatus::Draft, CampaignStatus::Active, CampaignStatus::Closing, CampaignStatus::Archived], true), 422, __('admin.campaign_delete_archived_only'));
        abort_if($campaign->orders()->exists() || $campaign->debts()->exists(), 422, __('admin.campaign_delete_has_history'));

        foreach ($campaign->items as $item) {
            if (!empty($item->image_url)) {
                $imageUploadService->deleteFile($item->image_url);
            }
        }

        $campaign->delete();
        $this->publishCampaignEvent('campaign.deleted', $campaign);

        return response()->json(['data' => ['deleted' => true]]);
    }

    /**
     * Handle the store operation.
     * @param StoreCampaignRequest $request Parameter value.
     * @param CreateCampaignAction $action Parameter value.
     * @return JsonResponse Result of the operation.
     */
    public function store(StoreCampaignRequest $request, CreateCampaignAction $action): JsonResponse
    {
        $room = request()->attributes->get('room');
        return response()->json(['data' => $action->execute($room, $request->validated(), request()->user('admin')->id)], 201);
    }

    /**
     * Upload and optimize a temporary menu image for the campaign creator.
     *
     * @param StoreCampaignImageRequest $request Validated image upload request.
     * @param ImageUploadService $imageUploadService Shared image optimization service.
     * @return JsonResponse Public URL of the optimized image.
     */
    public function uploadImage(StoreCampaignImageRequest $request, ImageUploadService $imageUploadService): JsonResponse
    {
        $imageUrl = $imageUploadService->uploadCampaignImage($request->file('image'));

        return response()->json(['data' => ['url' => $imageUrl]], 201);
    }

    /**
     * Handle the activate operation.
     * @param Room $room Parameter value.
     * @param Campaign $campaign Parameter value.
     * @param TransitionCampaignAction $action Parameter value.
     * @return JsonResponse Result of the operation.
     */
    public function activate(Room $room, Campaign $campaign, TransitionCampaignAction $action): JsonResponse
    {
        $this->assertCampaign($campaign);
        return response()->json(['data' => $action->activate($campaign)]);
    }

    /**
     * Lock member ordering on a running campaign ("khóa chiến dịch").
     *
     * @param \App\Http\Requests\CampaignOrderingLockRequest $request Validated request (notify flag).
     * @param Room $room Current room.
     * @param Campaign $campaign Target campaign.
     * @param \App\Actions\Campaign\SetCampaignOrderingLockAction $action Lock action.
     * @return JsonResponse Operation response.
     */
    public function lockOrdering(\App\Http\Requests\CampaignOrderingLockRequest $request, Room $room, Campaign $campaign, \App\Actions\Campaign\SetCampaignOrderingLockAction $action): JsonResponse
    {
        $this->assertCampaign($campaign);
        $updated = $action->execute($campaign, true, $request->user('admin')?->id, $request->boolean('notify'));

        return response()->json(['message' => __('admin.campaign_lock_success'), 'data' => $updated]);
    }

    /**
     * Reopen member ordering on a locked running campaign.
     *
     * @param \App\Http\Requests\CampaignOrderingLockRequest $request Validated request (notify flag).
     * @param Room $room Current room.
     * @param Campaign $campaign Target campaign.
     * @param \App\Actions\Campaign\SetCampaignOrderingLockAction $action Lock action.
     * @return JsonResponse Operation response.
     */
    public function unlockOrdering(\App\Http\Requests\CampaignOrderingLockRequest $request, Room $room, Campaign $campaign, \App\Actions\Campaign\SetCampaignOrderingLockAction $action): JsonResponse
    {
        $this->assertCampaign($campaign);
        $updated = $action->execute($campaign, false, $request->user('admin')?->id, $request->boolean('notify'));

        return response()->json(['message' => __('admin.campaign_unlock_success'), 'data' => $updated]);
    }

    /**
     * Mark all orders in campaign as delivering and broadcast pick-up notifications.
     *
     * @param Room $room Current room.
     * @param Campaign $campaign Target campaign.
     * @param \App\Actions\Campaign\MarkCampaignDeliveringAction $action Transition action.
     * @return JsonResponse Operation response.
     */
    public function markDelivering(Room $room, Campaign $campaign, \App\Actions\Campaign\MarkCampaignDeliveringAction $action): JsonResponse
    {
        $this->assertCampaign($campaign);
        $updated = $action->execute($campaign, request()->user('admin')?->id);

        return response()->json([
            'message' => __('admin.campaign_delivering_success'),
            'data' => $updated,
        ]);
    }

    /**
     * Send the live campaign announcement again via web, channel gateways and realtime socket.
     *
     * @param Room $room Current room.
     * @param Campaign $campaign Live campaign to announce again.
     * @param \App\Actions\Campaign\ResendCampaignNotificationAction $action Resend action.
     * @return JsonResponse Operation response.
     */
    public function resendNotification(Room $room, Campaign $campaign, \App\Actions\Campaign\ResendCampaignNotificationAction $action): JsonResponse
    {
        $this->assertCampaign($campaign);
        $action->execute($campaign, request()->user('admin')?->id);

        return response()->json(['message' => __('admin.resend_notification_success')]);
    }

    /**
     * Handle the cancel operation.
     * @param Room $room Parameter value.
     * @param Campaign $campaign Parameter value.
     * @param TransitionCampaignAction $action Parameter value.
     * @return JsonResponse Result of the operation.
     */
    public function cancel(Room $room, Campaign $campaign, TransitionCampaignAction $action): JsonResponse
    {
        $this->assertCampaign($campaign);
        $updated = $action->cancel($campaign);
        $this->publishCampaignEvent('campaign.deleted', $updated);

        return response()->json(['data' => $updated]);
    }

    /**
     * Handle the archive operation.
     * @param Room $room Parameter value.
     * @param Campaign $campaign Parameter value.
     * @param TransitionCampaignAction $action Parameter value.
     * @return JsonResponse Result of the operation.
     */
    public function archive(Room $room, Campaign $campaign, TransitionCampaignAction $action): JsonResponse
    {
        $this->assertCampaign($campaign);
        $updated = $action->archive($campaign);
        $this->publishCampaignEvent('campaign.deleted', $updated);

        return response()->json(['data' => $updated]);
    }

    /**
     * Handle the duplicate operation.
     * @param Room $room Parameter value.
     * @param Campaign $campaign Parameter value.
     * @param DuplicateCampaignAction $action Parameter value.
     * @return JsonResponse Result of the operation.
     */
    public function duplicate(Room $room, Campaign $campaign, DuplicateCampaignAction $action): JsonResponse
    {
        $this->assertCampaign($campaign);
        return response()->json(['data' => $action->execute($campaign, request()->user('admin')->id)], 201);
    }

    /**
     * Close the campaign; debt records are always generated for members who still owe money.
     *
     * @param Request $request Incoming HTTP request.
     * @param Room $room Room entity.
     * @param Campaign $campaign Campaign entity.
     * @param CloseCampaignAction $action Close campaign action.
     * @return JsonResponse Response containing updated campaign payload.
     */
    public function close(CloseCampaignRequest $request, Room $room, Campaign $campaign, CloseCampaignAction $action): JsonResponse
    {
        $this->assertCampaign($campaign);
        $data = $request->validated();
        $reason = $data['reason'] ?? null;
        $reasonString = is_string($reason) && trim($reason) !== '' ? trim($reason) : null;
        return response()->json(['data' => $action->execute($campaign, true, $reasonString)]);
    }

    /**
     * Return the latest participation summary shown in the close-campaign confirmation modal.
     *
     * @param Room $room Current room.
     * @param Campaign $campaign Campaign about to be closed.
     * @param \App\Services\Admin\AdminCampaignDetailService $detailService Campaign detail service.
     * @return JsonResponse Fresh member and item counts for the campaign.
     */
    public function closeSummary(Room $room, Campaign $campaign, \App\Services\Admin\AdminCampaignDetailService $detailService): JsonResponse
    {
        $this->assertCampaign($campaign);

        return response()->json(['data' => $detailService->getCloseSummary($room, $campaign)]);
    }

    /**
     * Handle the split bill operation.
     * @param SplitBillRequest $request Parameter value.
     * @param Room $room Parameter value.
     * @param Campaign $campaign Parameter value.
     * @param SplitCampaignBillAction $action Parameter value.
     * @return JsonResponse Result of the operation.
     */
    public function splitBill(SplitBillRequest $request, Room $room, Campaign $campaign, SplitCampaignBillAction $action): JsonResponse
    {
        $this->assertCampaign($campaign);
        return response()->json(['data' => $action->execute($campaign, $request->validated('method'), $request->validated('allocations', []))]);
    }

    /**
     * Handle the store item operation.
     * @param StoreCampaignItemRequest $request Parameter value.
     * @param Room $room Current room.
     * @param Campaign $campaign Parameter value.
     * @param CreateCampaignItemAction $action Parameter value.
     * @return JsonResponse Result of the operation.
     */
    public function storeItem(StoreCampaignItemRequest $request, Room $room, Campaign $campaign, CreateCampaignItemAction $action): JsonResponse
    {
        $this->assertCampaignEditable($campaign);
        $item = $action->execute($campaign, $request->validated());
        $this->publishMenuEvent('campaign.menu.updated', $campaign, $item);

        return response()->json(['data' => $item], 201);
    }

    /**
     * Handle the update item operation.
     * @param StoreCampaignItemRequest $request Parameter value.
     * @param Room $room Parameter value.
     * @param Campaign $campaign Parameter value.
     * @param CampaignItem $item Parameter value.
     * @param UpdateCampaignItemAction $action Parameter value.
     * @return JsonResponse Result of the operation.
     */
    public function updateItem(StoreCampaignItemRequest $request, Room $room, Campaign $campaign, CampaignItem $item, UpdateCampaignItemAction $action): JsonResponse
    {
        $this->assertItemEditable($campaign, $item);
        $updated = $action->execute($item, $request->validated());
        $this->publishMenuEvent('campaign.menu.updated', $campaign, $updated);

        return response()->json(['data' => $updated]);
    }

    /**
     * Handle the archive item operation.
     * @param Room $room Parameter value.
     * @param Campaign $campaign Parameter value.
     * @param CampaignItem $item Parameter value.
     * @param AuditService $audit Parameter value.
     * @return JsonResponse Result of the operation.
     */
    public function archiveItem(Room $room, Campaign $campaign, CampaignItem $item, AuditService $audit): JsonResponse
    {
        $this->assertItemEditable($campaign, $item);
        $before = $item->status?->value ?? (string) $item->status;
        $item->update(['status' => \App\Enums\CampaignItemStatus::Inactive]);
        $audit->record('campaign_item.archived', 'campaign_item', $item->id, $campaign->room_id, ['status' => $before, 'name' => $item->name], ['status' => \App\Enums\CampaignItemStatus::Inactive->value, 'name' => $item->name]);
        $this->publishMenuEvent('campaign.menu.deleted', $campaign, $item);
        return response()->json(['data' => $item->fresh()]);
    }

    /**
     * Batch update campaign items availability statuses.
     *
     * @param BatchUpdateCampaignItemStatusRequest $request Incoming validated batch update request.
     * @param Room $room Room entity.
     * @param Campaign $campaign Campaign entity.
     * @param AuditService $audit Audit service.
     * @return JsonResponse Result of the batch update operation.
     */
    public function batchUpdateItemStatus(BatchUpdateCampaignItemStatusRequest $request, Room $room, Campaign $campaign, AuditService $audit): JsonResponse
    {
        $this->assertCampaignEditable($campaign);
        $validated = $request->validated();
        $notifyMembers = (bool) ($validated['notify_members'] ?? false);

        $itemIds = collect($validated['items'])->pluck('id')->all();
        $existingItems = $campaign->items()->whereIn('id', $itemIds)->get()->keyBy('id');

        $updatedCount = 0;
        foreach ($validated['items'] as $itemData) {
            $item = $existingItems->get($itemData['id']);
            if (! $item) {
                continue;
            }
            $newStatus = $itemData['status'];
            $before = $item->status?->value ?? (string) $item->status;
            if ($before !== $newStatus) {
                $item->update(['status' => \App\Enums\CampaignItemStatus::from($newStatus)]);
                $audit->record('campaign_item.status_updated', 'campaign_item', $item->id, $campaign->room_id, ['status' => $before, 'name' => $item->name], ['status' => $newStatus, 'name' => $item->name]);
                $this->publishMenuEvent('campaign.menu.updated', $campaign, $item);
                $updatedCount++;
            }
        }

        if ($updatedCount > 0 && $notifyMembers) {
            CampaignUpdated::dispatch($campaign->fresh()->loadMissing('room'));
        }

        return response()->json([
            'data' => [
                'updated_count' => $updatedCount,
            ],
            'message' => __('admin.campaign_items_batch_updated_success', ['count' => $updatedCount]),
        ]);
    }

    /** Toggle a campaign item's availability status. */
    public function toggleItemStatus(UpdateCampaignItemStatusRequest $request, Room $room, Campaign $campaign, CampaignItem $item, AuditService $audit): JsonResponse
    {
        $this->assertItemEditable($campaign, $item);
        $status = $request->validated()['status'];
        $before = $item->status?->value ?? (string) $item->status;
        $item->update(['status' => \App\Enums\CampaignItemStatus::from($status)]);
        $audit->record('campaign_item.status_updated', 'campaign_item', $item->id, $campaign->room_id, ['status' => $before, 'name' => $item->name], ['status' => $status, 'name' => $item->name]);
        $this->publishMenuEvent('campaign.menu.updated', $campaign, $item);
        return response()->json(['data' => $item->fresh()]);
    }

    /**
     * Handle the store topping operation.
     * @param StoreItemOptionRequest $request Parameter value.
     * @param Room $room Current room.
     * @param Campaign $campaign Parameter value.
     * @param CampaignItem $item Parameter value.
     * @param CreateItemOptionAction $action Parameter value.
     * @return JsonResponse Result of the operation.
     */
    public function storeTopping(StoreItemOptionRequest $request, Room $room, Campaign $campaign, CampaignItem $item, CreateItemOptionAction $action): JsonResponse
    {
        $this->assertItemEditable($campaign, $item);
        return response()->json(['data' => $action->topping($item, $request->validated())], 201);
    }

    /**
     * Handle the store size operation.
     * @param StoreItemOptionRequest $request Parameter value.
     * @param Room $room Current room.
     * @param Campaign $campaign Parameter value.
     * @param CampaignItem $item Parameter value.
     * @param CreateItemOptionAction $action Parameter value.
     * @return JsonResponse Result of the operation.
     */
    public function storeSize(StoreItemOptionRequest $request, Room $room, Campaign $campaign, CampaignItem $item, CreateItemOptionAction $action): JsonResponse
    {
        $this->assertItemEditable($campaign, $item);
        return response()->json(['data' => $action->size($item, $request->validated())], 201);
    }

    /**
     * Handle the update topping operation.
     * @param UpdateItemOptionRequest $request Parameter value.
     * @param Room $room Parameter value.
     * @param Campaign $campaign Parameter value.
     * @param CampaignItem $item Parameter value.
     * @param CampaignItemTopping $option Parameter value.
     * @return JsonResponse Result of the operation.
     */
    public function updateTopping(UpdateItemOptionRequest $request, Room $room, Campaign $campaign, CampaignItem $item, CampaignItemTopping $option): JsonResponse
    {
        $this->assertItemEditable($campaign, $item);
        abort_unless($option->campaign_item_id === $item->id, 404);
        $option->update($request->validated());
        return response()->json(['data' => $option->fresh()]);
    }

    /**
     * Handle the update size operation.
     * @param UpdateItemOptionRequest $request Parameter value.
     * @param Room $room Parameter value.
     * @param Campaign $campaign Parameter value.
     * @param CampaignItem $item Parameter value.
     * @param CampaignItemSize $option Parameter value.
     * @return JsonResponse Result of the operation.
     */
    public function updateSize(UpdateItemOptionRequest $request, Room $room, Campaign $campaign, CampaignItem $item, CampaignItemSize $option): JsonResponse
    {
        $this->assertItemEditable($campaign, $item);
        abort_unless($option->campaign_item_id === $item->id, 404);
        $option->update($request->validated());
        return response()->json(['data' => $option->fresh()]);
    }

    /**
     * Handle the delete topping operation.
     * @param Room $room Parameter value.
     * @param Campaign $campaign Parameter value.
     * @param CampaignItem $item Parameter value.
     * @param CampaignItemTopping $option Parameter value.
     * @return JsonResponse Result of the operation.
     */
    public function deleteTopping(Room $room, Campaign $campaign, CampaignItem $item, CampaignItemTopping $option): JsonResponse
    {
        $this->assertItemEditable($campaign, $item);
        abort_unless($option->campaign_item_id === $item->id, 404);
        $option->update(['status' => 'hidden']);
        return response()->json(['data' => ['hidden' => true]]);
    }

    /**
     * Handle the delete size operation.
     * @param Room $room Parameter value.
     * @param Campaign $campaign Parameter value.
     * @param CampaignItem $item Parameter value.
     * @param CampaignItemSize $option Parameter value.
     * @return JsonResponse Result of the operation.
     */
    public function deleteSize(Room $room, Campaign $campaign, CampaignItem $item, CampaignItemSize $option): JsonResponse
    {
        $this->assertItemEditable($campaign, $item);
        abort_unless($option->campaign_item_id === $item->id, 404);
        $option->update(['status' => 'hidden']);
        return response()->json(['data' => ['hidden' => true]]);
    }

    /**
     * Handle the aggregate operation.
     * @param Request $request Parameter value.
     * @param \App\Services\Admin\OrderAggregationService $aggregator Parameter value.
     * @return JsonResponse Result of the operation.
     */
    public function aggregate(Request $request, \App\Services\Admin\OrderAggregationService $aggregator): JsonResponse
    {
        $validated = $request->validate([
            'campaign_id' => 'required|integer|exists:campaigns,id',
        ]);
        $room = request()->attributes->get('room');
        $campaign = Campaign::where('id', $validated['campaign_id'])->where('room_id', $room->id)->firstOrFail();

        return response()->json(['data' => $aggregator->forRoom($room->id, $campaign->id)]);
    }

    /**
     * Handle the export aggregate operation.
     *
     * @param Request $request HTTP request containing campaign_id parameter.
     * @param \App\Services\Admin\OrderAggregationService $aggregator Order aggregation service.
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse Excel download response.
     */
    public function exportAggregate(Request $request, \App\Services\Admin\OrderAggregationService $aggregator): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $validated = $request->validate([
            'campaign_id' => 'required|integer|exists:campaigns,id',
        ]);
        $room = request()->attributes->get('room');
        $campaign = Campaign::where('id', $validated['campaign_id'])->where('room_id', $room->id)->firstOrFail();

        $rows = $aggregator->forRoom($room->id, $campaign->id);
        return Excel::download(
            new CampaignAggregateExport($rows->toArray()),
            'drinkflow-aggregator.csv'
        );
    }

    /**
     * Handle the assert campaign operation.
     * @param Campaign $campaign Parameter value.
     * @return void Result of the operation.
     */
    private function assertCampaign(Campaign $campaign): void
    {
        abort_unless($campaign->room_id === request()->attributes->get('room')->id, 404);
    }

    /**
     * Ensure the campaign belongs to the current room and can still be modified.
     *
     * @param Campaign $campaign Campaign being modified.
     * @return void
     */
    private function assertCampaignEditable(Campaign $campaign): void
    {
        $this->assertCampaign($campaign);
        abort_if($campaign->isLocked(), 422, __('admin.campaign_locked_cannot_modify'));
    }

    /**
     * Ensure the item belongs to the campaign and the campaign can still be modified.
     *
     * @param Campaign $campaign Campaign being modified.
     * @param CampaignItem $item Item being modified.
     * @return void
     */
    private function assertItemEditable(Campaign $campaign, CampaignItem $item): void
    {
        $this->assertCampaignEditable($campaign);
        abort_unless($item->campaign_id === $campaign->id, 404);
    }

    /**
     * Handle the assert item operation.
     * @param Campaign $campaign Parameter value.
     * @param CampaignItem $item Parameter value.
     * @return void Result of the operation.
     */
    private function assertItem(Campaign $campaign, CampaignItem $item): void
    {
        $this->assertCampaign($campaign);
        abort_unless($item->campaign_id === $campaign->id, 404);
    }

    /**
     * Publish a campaign lifecycle change to the room's authorized socket clients.
     *
     * @param string $event Realtime event name.
     * @param Campaign $campaign Changed campaign.
     * @return void
     */
    private function publishCampaignEvent(string $event, Campaign $campaign): void
    {
        RoomRealtimeEvent::dispatch($event, $campaign->room_id, [
            'campaign_id' => $campaign->id,
            'status' => $campaign->status?->value,
        ]);
    }

    /**
     * Publish a menu change to the room's authorized socket clients.
     *
     * @param string $event Realtime event name.
     * @param Campaign $campaign Parent campaign.
     * @param CampaignItem $item Changed menu item.
     * @return void
     */
    private function publishMenuEvent(string $event, Campaign $campaign, CampaignItem $item): void
    {
        RoomRealtimeEvent::dispatch($event, $campaign->room_id, [
            'campaign_id' => $campaign->id,
            'item_id' => $item->id,
            'status' => $item->status instanceof \BackedEnum ? $item->status->value : (string) $item->status,
        ]);
    }
}
