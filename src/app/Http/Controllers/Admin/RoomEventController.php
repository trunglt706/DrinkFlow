<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Room\SendRoomEventNotificationAction;
use App\Enums\EventMusicMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\SendRoomEventNotificationRequest;
use App\Http\Requests\UpdateRoomEventSettingsRequest;
use App\Models\Room;
use App\Services\Occasion\RoomEventService;
use App\Services\Occasion\RoomEventSettingsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

/**
 * Room events of the admin (/admin/{room}/events): upcoming birthdays and occasions with whether their notification
 * was sent, and a button to send one that is due and unsent. The admin.room middleware has checked access to the room.
 */
class RoomEventController extends Controller
{
    /**
     * Upcoming and current events, by date.
     *
     * @param Room $room Room whose events are listed.
     * @param RoomEventService $events Room events.
     * @return View Events page.
     */
    public function index(Room $room, RoomEventService $events, RoomEventSettingsService $settings): View
    {
        return view('admin.events.index', [
            'room' => $room,
            'events' => $events->upcoming($room),
            'today' => $events->today($room),
            'settings' => $settings->all($room),
        ]);
    }

    /**
     * Save the background music and sound effects of one event page.
     *
     * @param UpdateRoomEventSettingsRequest $request Validated settings.
     * @param Room $room Room whose page is set.
     * @param RoomEventSettingsService $settings Event page settings.
     * @return JsonResponse|RedirectResponse JSON for the page script (saved in place), otherwise back to the events page.
     */
    public function settings(UpdateRoomEventSettingsRequest $request, Room $room, RoomEventSettingsService $settings): JsonResponse|RedirectResponse
    {
        $type = (string) $request->validated('type');
        $settings->update(
            $room,
            $type,
            EventMusicMode::from((string) $request->validated('music_mode')),
            $request->validated('music_url'),
            $request->boolean('sound_effects'),
        );

        if ($request->expectsJson()) {
            $saved = $settings->forEvent($room, $type);

            return response()->json([
                'message' => __('occasions.admin.settings_saved'),
                'settings' => ['music_mode' => $saved['music_mode']->value, 'music_url' => $saved['music_url'], 'sound_effects' => $saved['sound_effects']],
            ]);
        }

        return redirect()->route('admin.events.index', $room)->withFragment('event-settings')->with('status', __('occasions.admin.settings_saved'));
    }

    /**
     * Send the notification of an event that is due today and not sent yet.
     *
     * @param SendRoomEventNotificationRequest $request Validated event type.
     * @param Room $room Room to notify.
     * @param SendRoomEventNotificationAction $send Sending action.
     * @return RedirectResponse Back to the events page with the outcome.
     */
    public function send(SendRoomEventNotificationRequest $request, Room $room, SendRoomEventNotificationAction $send): RedirectResponse
    {
        $type = (string) $request->validated('type');
        $redirect = redirect()->route('admin.events.index', $room);

        return $send->execute($room, $type)
            ? $redirect->with('status', __('occasions.admin.sent'))
            : $redirect->withErrors(['type' => __('occasions.admin.not_sendable')]);
    }
}
