<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Campaign\CreateCampaignAction;
use App\Actions\Campaign\UpdateCampaignAction;
use App\Actions\Campaign\UpdateClosedCampaignPaymentAccountAction;
use App\Actions\Debt\ConfirmCampaignDebtsPaidAction;
use App\Enums\CampaignStatus;
use App\Enums\PaymentMethod;
use App\Exports\CampaignAggregateExport;
use App\Exports\CampaignDetailExport;
use App\Http\Controllers\Concerns\ManagesRoomCampaign;
use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmCampaignDebtsPaidRequest;
use App\Http\Requests\StoreCampaignImageRequest;
use App\Http\Requests\StoreCampaignRequest;
use App\Http\Requests\UpdateCampaignPaymentAccountRequest;
use App\Http\Requests\UpdateCampaignRequest;
use App\Models\Campaign;
use App\Models\Room;
use App\Services\Media\ImageUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class CampaignController extends Controller
{
    use ManagesRoomCampaign;

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

    /** Toggle a campaign item's availability status. */

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
}
