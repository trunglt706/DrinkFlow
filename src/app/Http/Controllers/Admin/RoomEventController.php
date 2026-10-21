<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Room\SendRoomEventNotificationAction;
use App\Enums\EventMusicMode;
use App\Enums\RoomOccasion;
use App\Http\Controllers\Controller;
use App\Http\Requests\ModerateRoomEventWishRequest;
use App\Http\Requests\SendRoomEventNotificationRequest;
use App\Http\Requests\UpdateRoomEventSettingsRequest;
use App\Http\Requests\UpdateRoomEventWishesRequest;
use App\Models\Room;
use App\Services\Birthday\RoomBirthdayService;
use App\Services\Occasion\RoomEventService;
use App\Services\Occasion\RoomEventSettingsService;
use App\Services\Occasion\RoomEventWishWallService;
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
    public function index(Room $room, RoomEventService $events, RoomEventSettingsService $settings, RoomEventWishWallService $wall): View
    {
        return view('admin.events.index', [
            'room' => $room,
            'events' => $events->upcoming($room),
            'today' => $events->today($room),
            'settings' => $settings->all($room),
            'walls' => $wall->forAdmin($room),
        ]);
    }

    /**
     * Preview an event page as the members see it, whatever the date: the birthday page celebrates the members of
     * the window, or a "Demo" celebrant when there is none. The wishes wall (members only) is left out.
     *
     * @param Room $room Room whose page is previewed.
     * @param string $type `birthday` or a RoomOccasion value (constrained by the route).
     * @param RoomEventService $events Room events.
     * @param RoomEventSettingsService $settings Music, sound and wishes of the page.
     * @param RoomBirthdayService $birthdays Birthday lookup.
     * @return View Event page in preview mode.
     */
    public function preview(Room $room, string $type, RoomEventService $events, RoomEventSettingsService $settings, RoomBirthdayService $birthdays): View
    {
        $common = [
            'room' => $room,
            'audio' => $settings->forEvent($room, $type),
            'wishes' => $settings->wishes($room, $type),
            'wall' => null,
            'backUrl' => route('admin.events.index', $room),
            'preview' => true,
        ];

        if ($type === SendRoomEventNotificationAction::TYPE_BIRTHDAY) {
            $celebrants = $birthdays->celebrants($room);

            return view('user.birthday.show', $common + [
                'celebrants' => $celebrants !== [] ? $celebrants : [$birthdays->demoCelebrant($room)],
                'viewerCode' => '',
            ]);
        }

        $occasion = RoomOccasion::from($type);

        return view('user.occasions.'.str_replace('_', '-', $occasion->value), $common + [
            'occasion' => $occasion,
            'year' => $events->occurrence($occasion, $events->today($room))['date']->year,
        ]);
    }

    /**
     * Hide a member's wish of an event wishes wall, or show it again.
     *
     * @param ModerateRoomEventWishRequest $request Validated state.
     * @param Room $room Room of the wish.
     * @param int $wish Wish id.
     * @param RoomEventWishWallService $wall Wishes wall.
     * @return JsonResponse|RedirectResponse JSON for the page script, otherwise back to the events page.
     */
    public function moderateWish(ModerateRoomEventWishRequest $request, Room $room, int $wish, RoomEventWishWallService $wall): JsonResponse|RedirectResponse
    {
        $hidden = $request->boolean('hidden');
        /** @var \App\Models\Admin $admin */
        $admin = $request->user('admin');
        $wall->setHidden($room, $admin, $wish, $hidden);
        $message = __($hidden ? 'occasions.wall.admin_hidden' : 'occasions.wall.admin_shown');

        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'hidden' => $hidden]);
        }

        return redirect()->route('admin.events.index', $room)->with('status', $message);
    }

    /**
     * Save the background music (built-in, link, uploaded file or none) and sound effects of one event page.
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
            $request->file('music_file'),
        );

        if ($request->expectsJson()) {
            $saved = $settings->forEvent($room, $type);

            return response()->json([
                'message' => __('occasions.admin.settings_saved'),
                'settings' => [
                    'music_mode' => $saved['music_mode']->value,
                    'music_url' => $saved['music_url'],
                    'music_file_url' => $saved['music_file_url'],
                    'music_name' => $saved['music_name'],
                    'sound_effects' => $saved['sound_effects'],
                ],
            ]);
        }

        return redirect()->route('admin.events.index', $room)->with('status', __('occasions.admin.settings_saved'));
    }

    /**
     * Save the wishes of one event page: the room's own list, or back to the default sample wishes.
     *
     * @param UpdateRoomEventWishesRequest $request Validated wishes.
     * @param Room $room Room whose page is set.
     * @param RoomEventSettingsService $settings Event page settings.
     * @return JsonResponse|RedirectResponse JSON for the page script (saved in place), otherwise back to the events page.
     */
    public function wishes(UpdateRoomEventWishesRequest $request, Room $room, RoomEventSettingsService $settings): JsonResponse|RedirectResponse
    {
        $type = (string) $request->validated('type');
        $useDefault = $request->boolean('use_default');
        $settings->updateWishes($room, $type, $useDefault ? null : array_values((array) $request->validated('wishes')));
        $message = __($useDefault ? 'occasions.admin.wishes_reset' : 'occasions.admin.wishes_saved');

        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'custom' => ! $useDefault, 'wishes' => $settings->wishes($room, $type)]);
        }

        return redirect()->route('admin.events.index', $room)->with('status', $message);
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
