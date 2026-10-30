<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exports\CampaignAggregateExport;
use App\Http\Middleware\SanitizeInputStrings;
use App\Mail\AdminResetPasswordOtpMail;
use App\Models\Admin;
use App\Models\Room;
use App\Services\FoodCrawler\Exceptions\FoodCrawlerException;
use App\Services\FoodCrawler\FoodCrawlerGateway;
use App\Services\Notification\RoomNotificationChannelService;
use App\Support\Security\OutboundUrlGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Tests\TestCase;

/**
 * Regression coverage for the admin-area security hardening (SSRF, OTP brute force, session revocation,
 * formula injection, security headers).
 */
class AdminSecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::create([
            'name' => 'Hardening Admin',
            'email' => 'hardening@example.test',
            'password' => Hash::make('a-very-long-password-1'),
            'status' => 'active',
        ]);
        $this->room = Room::create(['name' => 'Hardening', 'slug' => 'hardening', 'status' => 'active']);
        $this->admin->rooms()->attach($this->room);
    }

    public function test_responses_carry_security_headers(): void
    {
        $response = $this->get('/admin/login');

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy');
        $this->assertStringContainsString("frame-ancestors 'self'", (string) $response->headers->get('Content-Security-Policy'));
    }

    public function test_csp_allows_the_active_local_vite_port_only(): void
    {
        $originalHotFile = Vite::hotFile();
        $hotFile = tempnam(sys_get_temp_dir(), 'drinkflow-vite-');
        $this->assertNotFalse($hotFile);

        try {
            Vite::useHotFile($hotFile);
            file_put_contents($hotFile, 'http://127.0.0.1:5174');
            $policy = (string) $this->get('/admin/login')->headers->get('Content-Security-Policy');
            $this->assertStringContainsString('http://127.0.0.1:5174', $policy);
            $this->assertStringContainsString('ws://127.0.0.1:5174', $policy);

            file_put_contents($hotFile, 'https://untrusted.example:5174');
            $policy = (string) $this->get('/admin/login')->headers->get('Content-Security-Policy');
            $this->assertStringNotContainsString('untrusted.example', $policy);
        } finally {
            Vite::useHotFile($originalHotFile);
            unlink($hotFile);
        }
    }

    public function test_csp_allows_cdn_source_maps_only_in_local_environment(): void
    {
        $connectSrc = function (): string {
            $policy = (string) $this->get('/admin/login')->headers->get('Content-Security-Policy');
            preg_match('/connect-src ([^;]*)/', $policy, $matches);

            return $matches[1] ?? '';
        };

        $this->assertStringNotContainsString('https://cdn.jsdelivr.net', $connectSrc());

        $this->app['env'] = 'local';
        $this->assertStringContainsString('https://cdn.jsdelivr.net', $connectSrc());
    }

    public function test_outbound_url_guard_rejects_internal_and_non_https_targets(): void
    {
        $guard = app(OutboundUrlGuard::class);

        foreach ([
            'http://hooks.example.test/x',
            'https://localhost/x',
            'https://127.0.0.1/x',
            'https://169.254.169.254/latest/meta-data',
            'https://10.0.0.5/x',
            'https://192.168.1.10/x',
            'https://100.64.0.1/x',
            'https://[::1]/x',
            'https://[::ffff:127.0.0.1]/x',
            'https://user:pass@hooks.example.test/x',
            'ftp://hooks.example.test/x',
        ] as $url) {
            $this->assertFalse($guard->isSafe($url), "{$url} must be rejected");
        }

        $this->assertTrue($guard->isSafe('https://hooks.example.test/x'));
        $this->assertFalse($guard->isSafe('https://evil.example.test/x', ['hooks.slack.com']));
        $this->assertTrue($guard->isSafe('https://hooks.slack.com/services/T/B/X', ['hooks.slack.com']));
    }

    public function test_notification_channel_rejects_internal_webhook_urls(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->postJson("/admin/{$this->room->id}/notification-channels", [
                'type' => 'webhook',
                'name' => 'Internal',
                'config' => ['webhook_url' => 'https://169.254.169.254/latest/meta-data'],
            ])->assertStatus(422)->assertJsonValidationErrors('config.webhook_url');

        $this->actingAs($this->admin, 'admin')
            ->postJson("/admin/{$this->room->id}/notification-channels", [
                'type' => 'slack',
                'name' => 'Not slack',
                'config' => ['webhook_url' => 'https://hooks.example.test/services/T/B/X'],
            ])->assertStatus(422)->assertJsonValidationErrors('config.webhook_url');

        $this->actingAs($this->admin, 'admin')
            ->postJson("/admin/{$this->room->id}/notification-channels", [
                'type' => 'telegram',
                'name' => 'Path injection',
                'config' => ['bot_token' => '../../admin', 'chat_id' => '1'],
            ])->assertStatus(422)->assertJsonValidationErrors('config.bot_token');
    }

    public function test_dispatcher_refuses_stored_internal_webhook_and_sends_nothing(): void
    {
        Http::fake();
        $channel = app(RoomNotificationChannelService::class)->save($this->room->id, [
            'type' => 'webhook',
            'name' => 'Legacy internal',
            'status' => 'enabled',
            'config' => ['webhook_url' => 'https://127.0.0.1/hook'],
        ]);

        $this->actingAs($this->admin, 'admin')
            ->postJson("/admin/{$this->room->id}/notification-channels/{$channel->id}/test")
            ->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_channel_editor_receives_stored_secrets_uncached_and_blank_update_keeps_them(): void
    {
        $service = app(RoomNotificationChannelService::class);
        $channel = $service->save($this->room->id, [
            'type' => 'webhook',
            'name' => 'Hook',
            'status' => 'enabled',
            'config' => ['webhook_url' => 'https://hooks.example.test/a', 'secret_token' => 'super-secret'],
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->getJson("/admin/{$this->room->id}/notification-channels/{$channel->id}")
            ->assertOk();
        // The room admin sees the stored credentials to check them (masked behind a show/hide toggle in the form).
        $response->assertJsonPath('data.config.secret_token', 'super-secret');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertContains('secret_token', $response->json('data.secrets_configured'));

        $this->actingAs($this->admin, 'admin')
            ->patchJson("/admin/{$this->room->id}/notification-channels/{$channel->id}", [
                'type' => 'webhook',
                'name' => 'Hook renamed',
                'config' => ['webhook_url' => '', 'secret_token' => ''],
            ])->assertOk();

        $editable = $service->editable($channel->fresh());
        $this->assertSame(['webhook_url', 'secret_token'], array_values(array_intersect(['webhook_url', 'secret_token'], $editable['secrets_configured'])));
    }

    public function test_crawler_rejects_hosts_outside_the_provider_allowlist(): void
    {
        $gateway = app(FoodCrawlerGateway::class);

        foreach (['http://127.0.0.1:3306/', 'https://169.254.169.254/', 'https://evil.example.test/menu'] as $url) {
            try {
                $gateway->crawl($url);
                $this->fail("{$url} must be rejected");
            } catch (FoodCrawlerException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_reset_otp_response_does_not_reveal_whether_an_account_exists(): void
    {
        Mail::fake();

        $this->post('/admin/forgot-password', ['email' => 'nobody@example.test'])
            ->assertRedirect(route('admin.verify-otp.page'))
            ->assertSessionMissing('errors');

        Mail::assertNothingSent();
        $this->assertFalse(session()->has('admin_reset_otp'));
    }

    public function test_reset_otp_is_invalidated_after_repeated_wrong_guesses(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        Mail::fake();
        $this->post('/admin/forgot-password', ['email' => $this->admin->email]);
        $otp = (string) session('admin_reset_otp');

        for ($i = 0; $i < 5; $i++) {
            $this->post('/admin/verify-otp', ['otp' => '000000'])->assertSessionHasErrors('otp');
        }

        // Even the correct code no longer works once the guess budget is spent.
        $this->post('/admin/verify-otp', ['otp' => $otp])->assertSessionHasErrors('otp');
        $this->assertFalse((bool) session('admin_reset_verified'));
    }

    public function test_reset_otp_guesses_are_limited_per_account_across_restarted_flows(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        Mail::fake();

        for ($round = 0; $round < 3; $round++) {
            $this->post('/admin/forgot-password', ['email' => $this->admin->email]);
            for ($i = 0; $i < 4; $i++) {
                $this->post('/admin/verify-otp', ['otp' => '000000'])->assertSessionHasErrors('otp');
            }
        }

        // 12 wrong guesses were attempted; the account-wide budget (10) is exhausted, so even a fresh, correct OTP fails.
        $this->post('/admin/forgot-password', ['email' => $this->admin->email]);
        $otp = (string) session('admin_reset_otp');
        $this->post('/admin/verify-otp', ['otp' => $otp])->assertSessionHasErrors('otp');

        RateLimiter::clear('admin-reset-otp-fail:'.$this->admin->email);
    }

    public function test_reset_password_requires_twelve_characters(): void
    {
        Mail::fake();
        $this->post('/admin/forgot-password', ['email' => $this->admin->email]);
        $otp = (string) session('admin_reset_otp');
        $response = $this->post('/admin/verify-otp', ['otp' => $otp]);
        $query = (string) parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY);

        $this->post('/admin/reset-password?'.$query, ['password' => 'Short1!aa', 'password_confirmation' => 'Short1!aa'])
            ->assertSessionHasErrors('password');

        Mail::assertSent(AdminResetPasswordOtpMail::class);
    }

    public function test_password_change_revokes_other_sessions_but_keeps_the_current_one(): void
    {
        config(['captcha.disable' => true]);
        $this->actingAs($this->admin, 'admin')->get('/admin/profile')->assertOk();
        $staleFingerprint = session('admin_password_fingerprint');
        $this->assertIsString($staleFingerprint);

        $this->actingAs($this->admin, 'admin')
            ->patch('/admin/profile/password', [
                'current_password' => 'a-very-long-password-1',
                'password' => 'another-very-long-password-2',
                'password_confirmation' => 'another-very-long-password-2',
            ])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('another-very-long-password-2', $this->admin->fresh()->password));

        // The session that changed the password stays valid...
        $this->get('/admin/profile')->assertOk();

        // ...while a session still carrying the old fingerprint is signed out.
        $this->flushSession();
        $this->withSession(['admin_password_fingerprint' => $staleFingerprint])
            ->actingAs($this->admin->fresh(), 'admin')
            ->get('/admin/profile')
            ->assertRedirect(route('admin.login.page'));
    }

    public function test_csv_exports_neutralize_spreadsheet_formulas(): void
    {
        $export = new CampaignAggregateExport([
            ['name' => '=HYPERLINK("http://evil.test")', 'size' => '+cmd', 'toppings' => '@SUM(A1)', 'quantity' => 2],
            ['name' => 'Trà sữa', 'size' => '-5000', 'toppings' => '', 'quantity' => 1],
        ]);

        $rows = $export->array();

        $this->assertSame("'=HYPERLINK(\"http://evil.test\")", $rows[0][0]);
        $this->assertSame("'+cmd", $rows[0][1]);
        $this->assertSame("'@SUM(A1)", $rows[0][2]);
        $this->assertSame('Trà sữa', $rows[1][0]);
        $this->assertSame('-5000', $rows[1][1]);
    }

    public function test_sanitizer_resists_nested_tag_payloads_without_mangling_plain_text(): void
    {
        $sanitizer = new SanitizeInputStrings();

        $this->assertStringNotContainsString('<script', $sanitizer->cleanString('<scr<script></script>ipt>alert(1)</scr<script></script>ipt>'));
        $this->assertStringNotContainsString('onerror', $sanitizer->cleanString('<img src=x onerror=alert(1)>'));
        $this->assertStringNotContainsString('onload', $sanitizer->cleanString('<svg/onload=alert(1)>'));
        $this->assertSame('Ice tea, data: 50k, one=1, only = x', $sanitizer->cleanString('Ice tea, data: 50k, one=1, only = x'));
    }
}
