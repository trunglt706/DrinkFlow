<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Campaign\CloseCampaignAction;
use App\Actions\Campaign\DuplicateCampaignAction;
use App\Actions\Campaign\ExtendCampaignDeadlineAction;
use App\Actions\Campaign\SplitCampaignBillAction;
use App\Actions\Campaign\TransitionCampaignAction;
use App\Http\Controllers\Concerns\ManagesRoomCampaign;
use App\Http\Controllers\Controller;
use App\Http\Requests\CloseCampaignRequest;
use App\Http\Requests\ExtendCampaignDeadlineRequest;
use App\Http\Requests\SplitBillRequest;
use App\Models\Campaign;
use App\Models\Room;
use Illuminate\Http\JsonResponse;

/**
 * Campaign state transitions for room admins: activate, lock ordering, deliver, close, cancel, archive, duplicate.
 */
class CampaignLifecycleController extends Controller
{
    use ManagesRoomCampaign;

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
            // Pre-formatted like the page, so the campaign page updates in place without a reload.
            'deadline_display' => $updated->deadline?->format('H:i d/m/Y'),
            'deadline_time' => $updated->deadline?->format('H:i'),
        ]);
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
     * @param CloseCampaignRequest $request Validated close request.
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
}
