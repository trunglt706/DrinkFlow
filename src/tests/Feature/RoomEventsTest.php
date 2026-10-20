<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RoomOccasion;
use App\Models\Admin;
use App\Models\GlobalUser;
use App\Models\Room;
use App\Models\RoomUser;
use App\Models\UserNotification;
use App\Services\Notification\RoomNotificationChannelDispatcher;
use App\Support\Helpers\LunarCalendar;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Room events: the yearly occasions (shown on the day, Tết 7 days before and after) with their own pages, the events
 * banner of the room pages, the greeting notifications sent at 9:00 room time, and the admin events page.
 */
class RoomEventsTest extends TestCase
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

    private function member(string $name, ?string $birthday = null, ?Room $room = null): RoomUser
    {
        $user = GlobalUser::create(['name' => $name, 'email' => strtolower(str_replace(' ', '', $name)).uniqid().'@example.test', 'status' => 'active', 'birthday' => $birthday]);

        return RoomUser::create(['room_id' => ($room ?? $this->room)->id, 'global_user_id' => $user->id, 'display_name' => $name, 'status' => 'active']);
    }

    private function admin(): Admin
    {
        $admin = Admin::create(['name' => 'Room Admin', 'email' => 'events-admin'.uniqid().'@example.test', 'password' => 'secret123', 'status' => 'active']);
        $admin->rooms()->attach($this->room);

        return $admin;
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

    private function occasionUrl(RoomOccasion $occasion): string
    {
        return route('user.rooms.occasion', [$this->room->slug, $occasion->slug()]);
    }

    public function test_lunar_new_year_follows_the_vietnamese_calendar(): void
    {
        $this->assertSame('2025-01-29', LunarCalendar::lunarNewYear(2025)->toDateString());
        $this->assertSame('2026-02-17', LunarCalendar::lunarNewYear(2026)->toDateString());
        $this->assertSame('2027-02-06', LunarCalendar::lunarNewYear(2027)->toDateString());
        // UTC+7 puts the 2030 new moon one day before the Chinese calendar.
        $this->assertSame('2030-02-02', LunarCalendar::lunarNewYear(2030)->toDateString());
    }

    public function test_every_occasion_page_opens_on_its_day_with_one_slide_per_wish(): void
    {
        $viewer = $this->member('Viewer Lan');
        $days = [
            [RoomOccasion::NewYear, '2027-01-01', '/1-1'],
            [RoomOccasion::LunarNewYear, '2027-02-06', '/tet'],
            [RoomOccasion::InternationalWomensDay, '2027-03-08', '/8-3'],
            [RoomOccasion::WomensDay, '2026-10-20', '/20-10'],
            [RoomOccasion::MensDay, '2026-11-19', '/19-11'],
            [RoomOccasion::Christmas, '2026-12-25', '/25-12'],
        ];
        foreach ($days as [$occasion, $date, $path]) {
            Carbon::setTestNow($date.' 12:00:00');
            $wishes = (array) __('occasions.'.$occasion->value.'.wishes');
            $html = $this->actingAs($viewer->globalUser, 'web')->get($this->occasionUrl($occasion))
                ->assertOk()
                ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
                ->assertSee('<meta name="robots" content="noindex, nofollow, noarchive, nosnippet">', false)
                ->assertSee('data-stage-button', false)
                ->assertSee(__('occasions.'.$occasion->value.'.tap_hint'))
                ->assertSee('const SLIDE_MS = 2000;', false)
                ->getContent();

            $this->assertStringEndsWith('/rooms/flower-room'.$path, $this->occasionUrl($occasion));
            $this->assertSame(count($wishes), substr_count($html, 'data-slide>'), $occasion->value);
            foreach ($wishes as $wish) {
                $this->assertStringContainsString(e($wish), $html);
            }
        }
    }

    public function test_occasions_are_shown_only_on_the_day_and_tet_seven_days_around(): void
    {
        $viewer = $this->member('Viewer Lan')->globalUser;

        Carbon::setTestNow('2026-10-21 08:00:00');
        $this->actingAs($viewer, 'web')->get($this->occasionUrl(RoomOccasion::WomensDay))->assertNotFound();
        Carbon::setTestNow('2026-10-19 23:59:00');
        $this->actingAs($viewer, 'web')->get($this->occasionUrl(RoomOccasion::WomensDay))->assertNotFound();

        // Tết 2027 is on 6 February: shown from 30 January to 13 February.
        foreach (['2027-01-30' => 200, '2027-02-13' => 200, '2027-01-29' => 404, '2027-02-14' => 404] as $date => $status) {
            Carbon::setTestNow($date.' 09:00:00');
            $this->actingAs($viewer, 'web')->get($this->occasionUrl(RoomOccasion::LunarNewYear))->assertStatus($status);
        }
    }

    public function test_every_occasion_page_opens_at_any_date_locally(): void
    {
        $viewer = $this->member('Viewer Lan')->globalUser;
        Carbon::setTestNow('2026-11-05 09:00:00');
        $this->app->detectEnvironment(static fn (): string => 'local');

        foreach (RoomOccasion::cases() as $occasion) {
            $this->actingAs($viewer, 'web')->get($this->occasionUrl($occasion))->assertOk()->assertSee('data-stage-button', false);
        }
        // The banner still follows the real dates.
        $this->actingAs($viewer, 'web')->get(route('user.dashboard', $this->room->slug))->assertDontSee('data-events-banner', false);
    }

    public function test_occasion_page_is_only_for_members_of_the_room(): void
    {
        $outsider = $this->member('Outsider', null, Room::create(['name' => 'Other', 'slug' => 'other-room', 'status' => 'active']))->globalUser;
        $url = $this->occasionUrl(RoomOccasion::WomensDay);

        $this->get($url)->assertRedirect();
        $this->actingAs($outsider, 'web')->get($url)->assertRedirect()->assertDontSee('data-stage-button', false);
    }

    public function test_banner_slides_through_the_events_of_the_day(): void
    {
        $viewer = $this->member('Viewer Lan');

        $html = $this->actingAs($viewer->globalUser, 'web')->get(route('user.dashboard', $this->room->slug))
            ->assertOk()
            ->assertSee('data-events-banner', false)
            ->assertSee('data-events-slide="womens_day"', false)
            ->assertSee(__('occasions.womens_day.banner'))
            ->assertSee('href="'.$this->occasionUrl(RoomOccasion::WomensDay).'"', false)
            ->assertDontSee('data-events-prev', false)
            ->getContent();
        $this->assertSame(1, substr_count($html, 'data-events-slide="'));

        // A birthday the same day adds its own slide, and the slider gets its controls.
        $this->member('Happy Hoa', '1995-10-21');
        Cache::flush(); // Celebrants are cached for a few minutes.
        $html = $this->actingAs($viewer->globalUser, 'web')->get(route('user.dashboard', $this->room->slug))
            ->assertOk()
            ->assertSee('data-events-slide="birthday"', false)
            ->assertSee('data-birthday-banner', false)
            ->assertSee('data-events-prev', false)
            ->getContent();
        $this->assertSame(2, substr_count($html, 'data-events-slide="'));

        // Lunar New Year days count from the first day of Tết.
        Carbon::setTestNow('2027-02-08 09:00:00');
        $this->actingAs($viewer->globalUser, 'web')->get(route('user.dashboard', $this->room->slug))
            ->assertSee(__('occasions.lunar_new_year.banner_after', ['day' => 3]));
        Carbon::setTestNow('2027-02-03 09:00:00');
        $this->actingAs($viewer->globalUser, 'web')->get(route('user.dashboard', $this->room->slug))
            ->assertSee(trans_choice('occasions.lunar_new_year.banner_before', 3, ['days' => 3]));

        // No occasion and no birthday: no banner.
        Carbon::setTestNow('2026-11-05 09:00:00');
        $this->actingAs($viewer->globalUser, 'web')->get(route('user.dashboard', $this->room->slug))
            ->assertOk()->assertDontSee('data-events-banner', false);
    }

    public function test_greeting_goes_to_each_active_room_once_a_year_from_nine_oclock(): void
    {
        $sent = [];
        $this->captureChannels($sent);
        $viewer = $this->member('Viewer Lan');
        $this->member('Member Mai');
        Room::create(['name' => 'Closed Room', 'slug' => 'closed-flower-room', 'status' => 'archived', 'timezone' => 'UTC']);

        // Nothing before 9:00.
        Carbon::setTestNow('2026-10-20 08:59:00');
        $this->artisan('drinkflow:notify-occasions')->expectsOutputToContain('Sent 0 occasion')->assertSuccessful();
        $this->assertSame([], $sent);

        Carbon::setTestNow('2026-10-20 09:00:00');
        $this->artisan('drinkflow:notify-occasions')->expectsOutputToContain('Sent 1 occasion')->assertSuccessful();
        $this->artisan('drinkflow:notify-occasions')->expectsOutputToContain('Sent 0 occasion')->assertSuccessful();

        $this->assertCount(1, $sent);
        [$roomId, $payload] = $sent[0];
        $link = $this->occasionUrl(RoomOccasion::WomensDay);
        $this->assertSame($this->room->id, $roomId);
        $this->assertSame('occasion.womens_day', $payload['event']);
        $this->assertSame($link, $payload['url']);
        $this->assertStringContainsString($link, $payload['message']);

        $notification = UserNotification::query()->where('global_user_id', $viewer->global_user_id)->sole();
        $this->assertSame('occasion.womens_day', $notification->type);
        $this->assertSame($link, $notification->link);
        $this->assertSame(__('occasions.womens_day.notify_title'), $notification->title);
        $this->assertSame(2, UserNotification::query()->where('type', 'occasion.womens_day')->count());
        $this->assertDatabaseHas('room_occasion_notifications', ['room_id' => $this->room->id, 'occasion' => 'womens_day', 'year' => 2026, 'channel_count' => 1]);
    }

    public function test_rooms_greeted_by_the_previous_20_10_release_are_not_greeted_again(): void
    {
        $sent = [];
        $this->captureChannels($sent);
        $viewer = $this->member('Viewer Lan');
        $greeted = Room::create(['name' => 'Greeted Room', 'slug' => 'greeted-room', 'status' => 'active', 'timezone' => 'UTC']);
        // Row and notification written by the deployed drinkflow:notify-womens-day (same table, key and type).
        DB::table('room_occasion_notifications')->insert(['room_id' => $greeted->id, 'occasion' => 'womens_day', 'year' => 2026, 'channel_count' => 1, 'sent_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $oldLink = url('/rooms/flower-room/20-10');

        $this->artisan('drinkflow:notify-occasions')->expectsOutputToContain('Sent 1 occasion')->assertSuccessful();
        $this->assertSame([$this->room->id], array_column($sent, 0));
        // Links of notifications sent by the previous release still open the 20/10 page on the day.
        $this->assertSame($oldLink, $this->occasionUrl(RoomOccasion::WomensDay));
        $this->actingAs($viewer->globalUser, 'web')->get($oldLink)->assertOk();
    }

    public function test_tet_greeting_is_sent_on_the_first_day_only_and_options_are_validated(): void
    {
        $sent = [];
        $this->captureChannels($sent);
        $this->member('Viewer Lan');

        Carbon::setTestNow('2027-02-05 10:00:00');
        $this->artisan('drinkflow:notify-occasions')->expectsOutputToContain('Sent 0 occasion')->assertSuccessful();
        Carbon::setTestNow('2027-02-06 10:30:00');
        $this->artisan('drinkflow:notify-occasions')->expectsOutputToContain('Sent 1 occasion')->assertSuccessful();
        $this->assertSame('occasion.lunar_new_year', $sent[0][1]['event']);
        $this->assertDatabaseHas('room_occasion_notifications', ['room_id' => $this->room->id, 'occasion' => 'lunar_new_year', 'year' => 2027]);

        $this->artisan('drinkflow:notify-occasions', ['--occasion' => 'halloween'])->assertExitCode(2);
        $this->artisan('drinkflow:notify-occasions', ['--occasion' => 'christmas', '--force' => true, '--room' => 'flower-room'])->expectsOutputToContain('Sent 1 occasion')->assertSuccessful();
        $this->artisan('drinkflow:notify-occasions', ['--occasion' => 'christmas', '--force' => true, '--room' => 'flower-room'])->expectsOutputToContain('Sent 0 occasion')->assertSuccessful();
    }

    public function test_admin_lists_upcoming_events_with_their_notification_status(): void
    {
        $this->member('Happy Hoa', '1995-10-20');
        $this->member('Soon Son', '1992-10-30');
        $this->member('Far Phuong', '1990-12-30');
        DB::table('room_occasion_notifications')->insert(['room_id' => $this->room->id, 'occasion' => 'womens_day', 'year' => 2026, 'channel_count' => 2, 'sent_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        $html = $this->actingAs($this->admin(), 'admin')->get(route('admin.events.index', $this->room->slug))
            ->assertOk()
            ->assertSee('data-nav-events', false)
            ->assertSee(__('occasions.womens_day.name'))
            ->assertSee(__('occasions.christmas.name'))
            ->assertSee(__('occasions.lunar_new_year.name'))
            ->assertSee('Happy Hoa')
            ->assertSee('Soon Son')
            ->assertDontSee('Far Phuong')
            ->getContent();

        // Every occasion once, plus one row per birthday day; the title counts the events shown today (tinted rows).
        $this->assertSame(count(RoomOccasion::cases()) + 2, substr_count($html, 'data-room-event="'));
        $this->assertStringContainsString('data-room-events-showing="2"', $html);
        $this->assertSame(2, substr_count($html, 'data-room-event-active'));
        // 20/10 already sent (the admin may send it again); today's birthday can be sent; Christmas is not due yet.
        $this->assertMatchesRegularExpression('/data-room-event="womens_day".*?data-room-event-status="sent".*?data-room-event-send="womens_day"/s', $html);
        $this->assertStringContainsString(e(__('occasions.admin.resend_confirm')), $html);
        $this->assertStringContainsString('data-room-event-send="birthday"', $html);
        $this->assertStringNotContainsString('data-room-event-send="christmas"', $html);
        $this->assertStringContainsString(e(__('occasions.admin.status_scheduled', ['time' => '09:00 25/12'])), $html);
    }

    public function test_admin_sends_and_resends_an_event_of_the_day(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $sent = [];
        $this->captureChannels($sent);
        $this->member('Happy Hoa', '1995-10-20');
        $admin = $this->admin();
        $send = route('admin.events.send', $this->room->slug);

        // Before 9:00 the scheduler has not sent 20/10 yet: the admin sends it, then sends it again.
        Carbon::setTestNow('2026-10-20 07:00:00');
        $this->actingAs($admin, 'admin')->post($send, ['type' => 'womens_day'])
            ->assertRedirect(route('admin.events.index', $this->room->slug))->assertSessionHas('status', __('occasions.admin.sent'));
        Carbon::setTestNow('2026-10-20 07:30:00');
        $this->actingAs($admin, 'admin')->post($send, ['type' => 'womens_day'])->assertSessionHas('status', __('occasions.admin.sent'));
        $this->assertStringStartsWith('2026-10-20 07:30:00', (string) DB::table('room_occasion_notifications')->where('room_id', $this->room->id)->value('sent_at'));

        // Today's birthdays, twice too.
        $this->actingAs($admin, 'admin')->post($send, ['type' => 'birthday'])->assertSessionHas('status');
        $this->actingAs($admin, 'admin')->post($send, ['type' => 'birthday'])->assertSessionHas('status');

        // The automatic sends find both days already handled.
        Carbon::setTestNow('2026-10-20 10:00:00');
        $this->artisan('drinkflow:notify-birthdays')->expectsOutputToContain('0 room(s)');
        $this->artisan('drinkflow:notify-occasions')->expectsOutputToContain('Sent 0 occasion');
        $this->assertSame(['occasion.womens_day', 'occasion.womens_day', 'birthday.today', 'birthday.today'], array_column(array_column($sent, 1), 'event'));

        // Not happening today, or unknown type.
        $this->actingAs($admin, 'admin')->post($send, ['type' => 'christmas'])->assertSessionHasErrors('type');
        $this->actingAs($admin, 'admin')->post($send, ['type' => 'halloween'])->assertSessionHasErrors('type');
        Carbon::setTestNow('2026-10-21 10:00:00');
        $this->actingAs($admin, 'admin')->post($send, ['type' => 'birthday'])->assertSessionHasErrors('type');
        $this->assertCount(4, $sent);
    }

    public function test_admin_completes_a_send_that_crashed_halfway(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $sent = [];
        $this->captureChannels($sent);
        // The scheduler claimed 20/10 at 9:00 but crashed before sending: it never retries by itself.
        DB::table('room_occasion_notifications')->insert(['room_id' => $this->room->id, 'occasion' => 'womens_day', 'year' => 2026, 'created_at' => now(), 'updated_at' => now()]);
        $this->artisan('drinkflow:notify-occasions')->expectsOutputToContain('Sent 0 occasion');

        $admin = $this->admin();
        $this->actingAs($admin, 'admin')->get(route('admin.events.index', $this->room->slug))
            ->assertSee('data-room-event-status="not-sent"', false)
            ->assertSee('data-room-event-send="womens_day"', false);
        $this->actingAs($admin, 'admin')->post(route('admin.events.send', $this->room->slug), ['type' => 'womens_day'])->assertSessionHas('status');
        $this->assertCount(1, $sent);
        $this->assertNotNull(DB::table('room_occasion_notifications')->where('room_id', $this->room->id)->value('sent_at'));
    }

    public function test_event_pages_play_the_built_in_melody_and_sounds_by_default(): void
    {
        $viewer = $this->member('Viewer Lan', '1995-10-20')->globalUser;

        $page = $this->actingAs($viewer, 'web')->get($this->occasionUrl(RoomOccasion::WomensDay))
            ->assertOk()
            ->assertHeader('Content-Security-Policy')
            ->assertSee('data-event-sound', false)
            ->getContent();
        $this->assertStringContainsString('"music":"builtin","url":null,"effects":true,"melody":"womens_day"', html_entity_decode($this->audioConfig($page)));
        $this->assertStringContainsString("media-src 'self' https:", (string) $this->actingAs($viewer, 'web')->get($this->occasionUrl(RoomOccasion::WomensDay))->headers->get('Content-Security-Policy'));

        $birthday = $this->actingAs($viewer, 'web')->get(route('user.rooms.birthday', $this->room->slug))->assertOk()->getContent();
        $this->assertStringContainsString('"melody":"birthday"', html_entity_decode($this->audioConfig($birthday)));
    }

    public function test_admin_sets_the_music_and_sounds_of_each_event_page(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = $this->admin();
        $viewer = $this->member('Viewer Lan')->globalUser;
        $save = route('admin.events.settings', $this->room->slug);

        $this->actingAs($admin, 'admin')->get(route('admin.events.index', $this->room->slug))
            ->assertOk()->assertSee('data-event-settings="birthday"', false)->assertSee('data-event-settings="christmas"', false)
            ->assertSee(__('occasions.admin.music_builtin', ['melody' => __('occasions.melodies.new_year')]));

        // A custom song needs an https link.
        foreach ([[], ['music_url' => 'http://cdn.example.test/song.mp3'], ['music_url' => 'javascript:alert(1)']] as $data) {
            $this->actingAs($admin, 'admin')->put($save, ['type' => 'womens_day', 'music_mode' => 'custom', 'sound_effects' => '1'] + $data)->assertSessionHasErrors('music_url');
        }
        $this->actingAs($admin, 'admin')->put($save, ['type' => 'halloween', 'music_mode' => 'off'])->assertSessionHasErrors('type');
        // A streaming site page is a web player, never an audio file the browser can play.
        foreach (['https://zingmp3.vn/bai-hat/Happy-Birthday-to-You-HAPPY-BIRTHDAY/XejbxBiPVzQK.html' => 'zingmp3.vn', 'https://www.youtube.com/watch?v=abc' => 'youtube.com', 'https://m.nhaccuatui.com/bai-hat/x.html' => 'nhaccuatui.com'] as $url => $site) {
            $this->actingAs($admin, 'admin')->putJson($save, ['type' => 'birthday', 'music_mode' => 'custom', 'music_url' => $url])
                ->assertStatus(422)->assertJsonPath('errors.music_url.0', __('occasions.admin.music_url_page', ['site' => $site]));
        }

        $this->actingAs($admin, 'admin')->put($save, ['type' => 'womens_day', 'music_mode' => 'custom', 'music_url' => 'https://cdn.example.test/song.mp3', 'sound_effects' => '0'])
            ->assertRedirect()->assertSessionHas('status', __('occasions.admin.settings_saved'));
        $this->assertDatabaseHas('room_event_settings', ['room_id' => $this->room->id, 'event_type' => 'womens_day', 'music_mode' => 'custom', 'sound_effects' => false]);
        $page = html_entity_decode($this->audioConfig($this->actingAs($viewer, 'web')->get($this->occasionUrl(RoomOccasion::WomensDay))->getContent()));
        $this->assertStringContainsString('"music":"custom","url":"https://cdn.example.test/song.mp3","effects":false', $page);

        // The events page script saves in place (JSON), and only "own music link" rows show the link field.
        $this->actingAs($admin, 'admin')->putJson($save, ['type' => 'christmas', 'music_mode' => 'custom'])->assertStatus(422)->assertJsonValidationErrors('music_url');
        $this->actingAs($admin, 'admin')->putJson($save, ['type' => 'christmas', 'music_mode' => 'builtin', 'sound_effects' => true])
            ->assertOk()->assertJsonPath('message', __('occasions.admin.settings_saved'))->assertJsonPath('settings.music_mode', 'builtin');
        $html = $this->actingAs($admin, 'admin')->get(route('admin.events.index', $this->room->slug))->getContent();
        $this->assertMatchesRegularExpression('/data-event-settings="womens_day".*?data-music-url-field\s*>/s', $html);
        $this->assertMatchesRegularExpression('/data-event-settings="christmas".*?data-music-url-field\s+hidden/s', $html);

        // No music and no sound effects: no sound button at all; other events keep the defaults.
        $this->actingAs($admin, 'admin')->put($save, ['type' => 'womens_day', 'music_mode' => 'off', 'music_url' => 'https://cdn.example.test/song.mp3']);
        $this->assertNull(DB::table('room_event_settings')->where('event_type', 'womens_day')->value('music_url'));
        $this->actingAs($viewer, 'web')->get($this->occasionUrl(RoomOccasion::WomensDay))->assertOk()->assertDontSee('data-event-sound', false);
        Carbon::setTestNow('2026-12-25 12:00:00');
        $this->actingAs($viewer, 'web')->get($this->occasionUrl(RoomOccasion::Christmas))->assertOk()->assertSee('data-event-sound', false);
    }

    /**
     * The (HTML-escaped) data-config attribute of the sound button of a page.
     */
    private function audioConfig(string $html): string
    {
        return preg_match('/data-event-sound data-config="([^"]*)"/', $html, $match) === 1 ? $match[1] : '';
    }

    public function test_events_page_is_only_for_admins_of_the_room(): void
    {
        $other = Admin::create(['name' => 'Other Admin', 'email' => 'other-events-admin@example.test', 'password' => 'secret123', 'status' => 'active']);

        $this->get(route('admin.events.index', $this->room->slug))->assertRedirect();
        $this->actingAs($other, 'admin')->get(route('admin.events.index', $this->room->slug))->assertRedirect(route('admin.login.page'));
        $this->actingAs($other, 'admin')->postJson(route('admin.events.send', $this->room->slug), ['type' => 'womens_day'])->assertForbidden();
    }
}
