<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\GlobalUser;
use App\Models\Room;
use App\Models\RoomUser;
use App\Services\Birthday\RoomBirthdayService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Birthday field on /me/profile, the room banner shown 3 days before and after a member's birthday, and the
 * stand-alone celebration page reachable only by members of the room during that window.
 */
class RoomBirthdayTest extends TestCase
{
    use RefreshDatabase;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-16 09:00:00');
        $this->room = Room::create(['name' => 'Birthday Room', 'slug' => 'birthday-room', 'status' => 'active', 'timezone' => 'UTC']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function member(string $name, ?string $birthday, ?Room $room = null): RoomUser
    {
        $user = GlobalUser::create(['name' => $name, 'email' => strtolower(str_replace(' ', '', $name)).uniqid().'@example.test', 'status' => 'active', 'birthday' => $birthday]);

        return RoomUser::create(['room_id' => ($room ?? $this->room)->id, 'global_user_id' => $user->id, 'display_name' => $name, 'status' => 'active']);
    }

    public function test_offset_handles_the_window_year_boundaries_and_29_february(): void
    {
        $service = app(RoomBirthdayService::class);
        $offset = fn (string $birthday, string $today): int => $service->offsetFromToday(CarbonImmutable::parse($birthday), CarbonImmutable::parse($today));

        $this->assertSame(0, $offset('1990-10-16', '2026-10-16'));
        $this->assertSame(3, $offset('1990-10-19', '2026-10-16'));
        $this->assertSame(-3, $offset('1990-10-13', '2026-10-16'));
        $this->assertSame(2, $offset('1990-01-01', '2026-12-30'));
        $this->assertSame(-2, $offset('1990-12-30', '2027-01-01'));
        // 29 February is celebrated on 28 February in a non-leap year.
        $this->assertSame(0, $offset('2000-02-29', '2027-02-28'));
    }

    public function test_banner_lists_members_within_three_days_on_room_pages(): void
    {
        $viewer = $this->member('Viewer', null);
        $this->member('Today Tran', '1995-10-16');
        $this->member('Soon Le', '1992-10-19');
        $this->member('Past Pham', '1990-10-13');
        $this->member('Too Far', '1990-10-20');
        $this->member('Other Room', '1990-10-16', Room::create(['name' => 'Other', 'slug' => 'other-bd-room', 'status' => 'active']));

        $html = $this->actingAs($viewer->globalUser, 'web')->get(route('user.dashboard', $this->room->slug))
            ->assertOk()
            ->assertSee('data-birthday-banner', false)
            ->assertSee(__('birthday.banner_today', ['name' => 'Today Tran']))
            ->assertSee(trans_choice('birthday.banner_upcoming', 3, ['name' => 'Soon Le', 'days' => 3, 'date' => '19/10']))
            ->assertSee(trans_choice('birthday.banner_past', 3, ['name' => 'Past Pham', 'days' => 3, 'date' => '13/10']))
            ->assertDontSee('Too Far')
            ->assertDontSee('Other Room')
            ->getContent();

        // Never the birth year.
        $this->assertStringNotContainsString('1995', $html);
        $this->assertSame(3, substr_count($html, 'data-birthday-link='));
        // One scrolling line of text for all of them, with a fixed call-to-action and no "1/3" counters.
        $this->assertSame(1, substr_count($html, 'df-bd-ticker-track"'));
        $this->assertStringContainsString('df-bd-cta', $html);
        $this->assertStringNotContainsString('1/3', $html);
    }

    public function test_no_banner_without_a_birthday_in_the_window(): void
    {
        $viewer = $this->member('Viewer', '1990-05-05');

        $this->actingAs($viewer->globalUser, 'web')->get(route('user.dashboard', $this->room->slug))
            ->assertOk()->assertDontSee('data-birthday-banner', false);
    }

    public function test_celebration_page_shows_every_member_of_the_window_to_room_members_only(): void
    {
        $viewer = $this->member('Viewer', null);
        $hoa = $this->member('Happy Hoa', '1993-10-16');
        $an = $this->member('Happy An', '1991-10-16');
        $soon = $this->member('Soon Son', '1990-10-18');
        $this->member('Later Linh', '1993-11-30');
        $url = route('user.rooms.birthday', $this->room);
        $this->assertStringEndsWith('/rooms/birthday-room/birthday', $url);
        $hoa->globalUser->update(['bio' => 'Thích **cà phê** muối']);
        $hoa->update(['joined_at' => '2025-03-10 08:00:00']);

        $html = $this->actingAs($viewer->globalUser, 'web')->get($url)
            ->assertOk()
            ->assertSee('data-birthday-page', false)
            ->assertSee('Happy An, Happy Hoa & Soon Son')
            ->assertDontSee('Later Linh')
            ->assertDontSee('1993')
            ->assertDontSee('data-birthday-banner', false)
            // The hover card carries the join date and the bio (Markdown rendered).
            ->assertSee('<strong>cà phê</strong>', false)
            ->assertSee('10/03/2025')
            ->getContent();
        foreach ([$hoa, $an, $soon] as $celebrant) {
            $this->assertStringContainsString('data-birthday-person="'.$celebrant->user_code.'"', $html);
        }

        // The whole banner opens the room's celebration page.
        $banner = $this->actingAs($viewer->globalUser, 'web')->get(route('user.dashboard', $this->room->slug))->getContent();
        $this->assertSame(1, substr_count($banner, 'href="'.$url.'"'));

        // Nobody in the window: nothing to celebrate.
        Carbon::setTestNow('2026-06-01 09:00:00');
        $this->actingAs($viewer->globalUser, 'web')->get($url)->assertNotFound();
        Carbon::setTestNow('2026-10-16 09:00:00');

        // Someone outside the room never reaches the page.
        $stranger = $this->member('Stranger', null, Room::create(['name' => 'Elsewhere', 'slug' => 'elsewhere-bd', 'status' => 'active']));
        $this->assertNotSame(200, $this->actingAs($stranger->globalUser, 'web')->get($url)->getStatusCode());
    }

    public function test_profile_saves_the_birthday_and_refreshes_the_banner(): void
    {
        $member = $this->member('Self Member', null);
        $user = $member->globalUser;
        $this->actingAs($user, 'web')->get(route('user.dashboard', $this->room->slug))->assertDontSee('data-birthday-banner', false);

        $this->withoutMiddleware(ValidateCsrfToken::class)->actingAs($user, 'web')
            ->post('/me/profile', ['birthday' => '1994-10-16'])->assertSessionHasNoErrors();
        $this->assertSame('1994-10-16', $user->fresh()->birthday->toDateString());
        $this->actingAs($user, 'web')->get('/me/profile')->assertSee('value="1994-10-16"', false);

        // The cached banner is refreshed at once, with a personal greeting for the member.
        $this->actingAs($user, 'web')->get(route('user.dashboard', $this->room->slug))
            ->assertSee(__('birthday.banner_today_self', ['name' => 'Self Member']));

        $this->withoutMiddleware(ValidateCsrfToken::class)->actingAs($user, 'web')
            ->post('/me/profile', ['birthday' => '2030-01-01'])->assertSessionHasErrors('birthday');
        $this->withoutMiddleware(ValidateCsrfToken::class)->actingAs($user, 'web')
            ->post('/me/profile', ['birthday' => ''])->assertSessionHasNoErrors();
        $this->assertNull($user->fresh()->birthday);
    }

    public function test_morning_notification_goes_to_each_room_with_a_birthday_today_once(): void
    {
        $sent = [];
        $this->mock(\App\Services\Notification\RoomNotificationChannelDispatcher::class, function ($mock) use (&$sent): void {
            $mock->shouldReceive('dispatch')->andReturnUsing(function (Room $room, array $payload) use (&$sent): int {
                $sent[] = [$room->id, $payload];

                return 1;
            });
        });
        $viewer = $this->member('Viewer', null);
        $this->member('Happy Hoa', '1993-10-16');
        $this->member('Happy An', '1991-10-16');
        $this->member('Tomorrow Tu', '1990-10-17');
        $quiet = Room::create(['name' => 'Quiet', 'slug' => 'quiet-bd-room', 'status' => 'active', 'timezone' => 'UTC']);
        $this->member('Quiet Member', '1990-10-17', $quiet);

        // Before 9:00 room time nothing is sent.
        Carbon::setTestNow('2026-10-16 08:59:00');
        $this->artisan('drinkflow:notify-birthdays')->assertSuccessful();
        $this->assertSame([], $sent);

        Carbon::setTestNow('2026-10-16 09:00:00');
        $this->artisan('drinkflow:notify-birthdays')->expectsOutputToContain('1 room(s)')->assertSuccessful();
        $this->artisan('drinkflow:notify-birthdays')->expectsOutputToContain('0 room(s)')->assertSuccessful();

        $this->assertCount(1, $sent);
        [$roomId, $payload] = $sent[0];
        $this->assertSame($this->room->id, $roomId);
        $this->assertSame('birthday.today', $payload['event']);
        $this->assertStringContainsString('Happy An & Happy Hoa', $payload['message']);
        $this->assertStringContainsString(route('user.rooms.birthday', $this->room), $payload['message']);
        $this->assertStringNotContainsString('Tomorrow Tu', $payload['message']);

        // Every active member of the room gets the in-app notification, linking to the celebration page.
        $notification = \App\Models\UserNotification::query()->where('global_user_id', $viewer->global_user_id)->sole();
        $this->assertSame('birthday.today', $notification->type);
        $this->assertSame(route('user.rooms.birthday', $this->room), $notification->link);
        $this->assertSame(4, \App\Models\UserNotification::query()->where('type', 'birthday.today')->count());
        $this->assertDatabaseHas('room_birthday_notifications', ['room_id' => $this->room->id, 'celebrated_on' => '2026-10-16', 'celebrant_count' => 2, 'channel_count' => 1]);
    }

    public function test_admin_member_list_marks_birthdays_of_the_month_and_menu_counts_them(): void
    {
        $admin = \App\Models\Admin::create(['name' => 'Room Admin', 'email' => 'bd-admin@example.test', 'password' => 'secret123', 'status' => 'active']);
        $admin->rooms()->attach($this->room);
        $this->member('October One', '1990-10-02');
        $this->member('October Two', '1988-10-30');
        $this->member('November Nam', '1990-11-01');
        $this->member('No Birthday', null);
        $blocked = $this->member('Blocked October', '1990-10-05');
        $blocked->update(['status' => 'blocked']);

        $html = $this->actingAs($admin, 'admin')->get(route('admin.room-users.page', $this->room->slug))
            ->assertOk()
            ->assertSee('data-nav-birthdays', false)
            ->assertSee('title="'.e(__('admin.birthday_month_badge', ['count' => 2])).'"', false)
            ->assertSee('data-tooltip="'.e(__('admin.member_birthday_tooltip', ['date' => '02/10'])).'"', false)
            ->assertSee('data-tooltip="'.e(__('admin.member_birthday_tooltip', ['date' => '30/10'])).'"', false)
            ->getContent();

        // The blocked member keeps the icon in the list (same month) but is not counted in the menu badge.
        $this->assertSame(3, substr_count($html, 'data-member-birthday'));
        $this->assertStringNotContainsString('1988', $html);
    }

    public function test_notification_follows_each_room_timezone(): void
    {
        $tokyo = Room::create(['name' => 'Tokyo', 'slug' => 'tokyo-bd-room', 'status' => 'active', 'timezone' => 'Asia/Tokyo']);
        $this->member('Tokyo Birthday', '1990-10-17', $tokyo);

        // 00:30 UTC = 09:30 in Tokyo, already 17 October there.
        Carbon::setTestNow('2026-10-17 00:30:00');
        $this->artisan('drinkflow:notify-birthdays')->expectsOutputToContain('1 room(s)')->assertSuccessful();
        $this->assertDatabaseHas('room_birthday_notifications', ['room_id' => $tokyo->id, 'celebrated_on' => '2026-10-17']);
    }
}
