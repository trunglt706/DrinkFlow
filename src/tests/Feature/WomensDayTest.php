<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\GlobalUser;
use App\Models\Room;
use App\Models\RoomUser;
use App\Models\UserNotification;
use App\Services\Notification\RoomNotificationChannelDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Vietnamese Women's Day: the /rooms/{room}/20-10 page reachable by members of the room, and the yearly greeting
 * sent on 20 October from 10:00 room time with a link to that page.
 */
class WomensDayTest extends TestCase
{
    use RefreshDatabase;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-20 10:00:00');
        $this->room = Room::create(['name' => 'Flower Room', 'slug' => 'flower-room', 'status' => 'active', 'timezone' => 'UTC']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function member(string $name, ?Room $room = null): RoomUser
    {
        $user = GlobalUser::create(['name' => $name, 'email' => strtolower(str_replace(' ', '', $name)).uniqid().'@example.test', 'status' => 'active']);

        return RoomUser::create(['room_id' => ($room ?? $this->room)->id, 'global_user_id' => $user->id, 'display_name' => $name, 'status' => 'active']);
    }

    /**
     * Capture the payloads sent to the room notification channels.
     *
     * @param array<int, array{0: int, 1: array<string, mixed>}> $sent Captured (room id, payload) pairs.
     */
    private function captureChannels(array &$sent): void
    {
        $this->mock(RoomNotificationChannelDispatcher::class, function ($mock) use (&$sent): void {
            $mock->shouldReceive('dispatch')->andReturnUsing(function (Room $room, array $payload) use (&$sent): int {
                $sent[] = [$room->id, $payload];

                return 1;
            });
        });
    }

    public function test_members_see_the_bud_and_every_wish_on_one_slide_each(): void
    {
        $viewer = $this->member('Viewer Lan');
        $wishes = (array) __('womens_day.wishes');

        $html = $this->actingAs($viewer->globalUser, 'web')->get(route('user.rooms.womens-day', $this->room->slug))
            ->assertOk()
            ->assertSee('data-rose', false)
            ->assertSee(__('womens_day.tap_hint'))
            ->assertSee(__('womens_day.subheading', ['room' => 'Flower Room']))
            ->assertSee('const SLIDE_MS = 2000;', false)
            ->getContent();

        $this->assertSame(count($wishes), substr_count($html, 'data-slide>'));
        foreach ($wishes as $wish) {
            $this->assertStringContainsString(e($wish), $html);
        }
        $this->assertStringEndsWith('/rooms/flower-room/20-10', route('user.rooms.womens-day', $this->room));
    }

    public function test_page_is_hidden_from_robots(): void
    {
        $viewer = $this->member('Viewer Lan');

        $this->actingAs($viewer->globalUser, 'web')->get(route('user.rooms.womens-day', $this->room->slug))
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->assertSee('<meta name="robots" content="noindex, nofollow, noarchive, nosnippet">', false);
    }

    public function test_page_is_only_for_members_of_the_room(): void
    {
        $outsider = $this->member('Outsider', Room::create(['name' => 'Other', 'slug' => 'other-flower-room', 'status' => 'active']));

        $this->get(route('user.rooms.womens-day', $this->room->slug))->assertRedirect();
        $this->actingAs($outsider->globalUser, 'web')->get(route('user.rooms.womens-day', $this->room->slug))
            ->assertRedirect()->assertDontSee(__('womens_day.tap_hint'));
    }

    public function test_greeting_goes_to_each_active_room_once_a_year_from_ten_oclock(): void
    {
        $sent = [];
        $this->captureChannels($sent);
        $viewer = $this->member('Viewer Lan');
        $this->member('Member Mai');
        $tokyo = Room::create(['name' => 'Tokyo Room', 'slug' => 'tokyo-flower-room', 'status' => 'active', 'timezone' => 'Asia/Tokyo']);
        Room::create(['name' => 'Closed Room', 'slug' => 'closed-flower-room', 'status' => 'archived', 'timezone' => 'UTC']);

        // 09:59 UTC: too early for the UTC room, but already 18:59 on 20/10 in Tokyo.
        Carbon::setTestNow('2026-10-20 09:59:00');
        $this->artisan('drinkflow:notify-womens-day')->expectsOutputToContain('1 room(s)')->assertSuccessful();
        $this->assertSame([$tokyo->id], array_column($sent, 0));

        Carbon::setTestNow('2026-10-20 10:00:00');
        $this->artisan('drinkflow:notify-womens-day')->expectsOutputToContain('1 room(s)')->assertSuccessful();
        $this->artisan('drinkflow:notify-womens-day')->expectsOutputToContain('0 room(s)')->assertSuccessful();

        $this->assertCount(2, $sent);
        [$roomId, $payload] = $sent[1];
        $link = route('user.rooms.womens-day', $this->room);
        $this->assertSame($this->room->id, $roomId);
        $this->assertSame('occasion.womens_day', $payload['event']);
        $this->assertSame($link, $payload['url']);
        $this->assertStringContainsString($link, $payload['message']);

        // Every active member of the room gets the in-app notification, linking to the 20/10 page.
        $notification = UserNotification::query()->where('global_user_id', $viewer->global_user_id)->sole();
        $this->assertSame('occasion.womens_day', $notification->type);
        $this->assertSame($link, $notification->link);
        $this->assertSame(__('womens_day.notify_title'), $notification->title);
        $this->assertSame(2, UserNotification::query()->where('type', 'occasion.womens_day')->count());
        $this->assertDatabaseHas('room_occasion_notifications', ['room_id' => $this->room->id, 'occasion' => 'womens_day', 'year' => 2026, 'channel_count' => 1]);
    }

    public function test_nothing_is_sent_on_another_day_unless_forced_for_one_room(): void
    {
        $sent = [];
        $this->captureChannels($sent);
        $this->member('Viewer Lan');
        Room::create(['name' => 'Second Room', 'slug' => 'second-flower-room', 'status' => 'active', 'timezone' => 'UTC']);

        Carbon::setTestNow('2026-10-21 09:00:00');
        $this->artisan('drinkflow:notify-womens-day')->expectsOutputToContain('0 room(s)')->assertSuccessful();
        $this->assertSame([], $sent);

        $this->artisan('drinkflow:notify-womens-day', ['--force' => true, '--room' => 'flower-room'])->expectsOutputToContain('1 room(s)')->assertSuccessful();
        $this->artisan('drinkflow:notify-womens-day', ['--force' => true, '--room' => 'flower-room'])->expectsOutputToContain('0 room(s)')->assertSuccessful();
        $this->assertSame([$this->room->id], array_column($sent, 0));
    }
}
