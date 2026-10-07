<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\CampaignStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentAccountStatus;
use App\Enums\RoomUserStatus;
use App\Http\Controllers\Concerns\ManagesRoomCampaign;
use App\Http\Controllers\Controller;
use App\Http\Requests\CampaignPageRequest;
use App\Models\Campaign;
use App\Models\Room;
use App\Services\Admin\PreviousCampaignMenuService;
use App\Services\Order\PublicOrderCheckService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Blade pages of the admin campaign area: list, create/edit forms and the campaign info, menu and orders tabs.
 */
class CampaignPageController extends Controller
{
    use ManagesRoomCampaign;

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
}
