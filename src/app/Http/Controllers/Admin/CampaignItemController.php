<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Campaign\CreateCampaignItemAction;
use App\Actions\Campaign\CreateItemOptionAction;
use App\Actions\Campaign\UpdateCampaignItemAction;
use App\Enums\CampaignItemOptionStatus;
use App\Events\CampaignUpdated;
use App\Http\Controllers\Concerns\ManagesRoomCampaign;
use App\Http\Controllers\Controller;
use App\Http\Requests\BatchUpdateCampaignItemStatusRequest;
use App\Http\Requests\StoreCampaignItemRequest;
use App\Http\Requests\StoreItemOptionRequest;
use App\Http\Requests\UpdateCampaignItemStatusRequest;
use App\Http\Requests\UpdateItemOptionRequest;
use App\Models\Campaign;
use App\Models\CampaignItem;
use App\Models\CampaignItemSize;
use App\Models\CampaignItemTopping;
use App\Models\Room;
use App\Services\Audit\AuditService;
use Illuminate\Http\JsonResponse;

/**
 * Campaign menu management for room admins: items, their sizes and toppings.
 */
class CampaignItemController extends Controller
{
    use ManagesRoomCampaign;

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
        $option->update(['status' => CampaignItemOptionStatus::Hidden->value]);
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
        $option->update(['status' => CampaignItemOptionStatus::Hidden->value]);
        return response()->json(['data' => ['hidden' => true]]);
    }
}
