<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Events\RoomRealtimeEvent;
use App\Models\Campaign;
use App\Models\CampaignItem;

/**
 * Shared guards and realtime notices for the admin campaign controllers: every campaign (and menu item)
 * must belong to the room resolved by the admin.room middleware.
 */
trait ManagesRoomCampaign
{
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
