<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\GlobalUser;
use App\Models\QrLoginToken;
use App\Services\Auth\QrLoginService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class QrLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function user(string $status = 'active'): GlobalUser
    {
        return GlobalUser::create(['name' => 'QR Lan', 'email' => 'qr-lan@example.test', 'status' => $status]);
    }

    /**
     * Issue a code as the signed-in member and return its plain token from the link.
     */
    private function issueToken(GlobalUser $user): string
    {
        $url = (string) $this->actingAs($user, 'web')->postJson(route('user.me.devices.qr-login'))
            ->assertOk()
            ->assertJsonPath('rotate_in', QrLoginService::ROTATE_SECONDS)
            ->assertHeader('Cache-Control', 'no-store, private')
            ->json('url');
        $this->assertStringStartsWith(url('/auth/qr/'), $url);
        $this->app['auth']->forgetGuards();
        $this->flushSession();

        return basename(parse_url($url, PHP_URL_PATH));
    }

    public function test_devices_page_shows_the_qr_panel(): void
    {
        $this->actingAs($this->user(), 'web')->get(route('user.me.devices'))
            ->assertOk()
            ->assertSee('data-qr-login', false)
            ->assertSee(route('user.me.devices.qr-login'), false)
            ->assertSee(__('global.qr_login.title'));
    }

    public function test_issuing_requires_a_signed_in_member(): void
    {
        $this->postJson(route('user.me.devices.qr-login'))->assertStatus(401);
        $this->assertDatabaseCount('qr_login_tokens', 0);
    }

    public function test_scanning_a_code_signs_the_phone_in_once(): void
    {
        $user = $this->user();
        $token = $this->issueToken($user);
        // Only the hash is stored.
        $this->assertDatabaseMissing('qr_login_tokens', ['token_hash' => $token]);
        $this->assertDatabaseHas('qr_login_tokens', ['global_user_id' => $user->id, 'token_hash' => hash('sha256', $token)]);

        $this->assertGuest('web');
        $this->get(route('auth.qr-login', $token))->assertRedirect(route('user.me.dashboard'));
        $this->assertAuthenticatedAs($user, 'web');
        $this->assertNotNull(QrLoginToken::query()->value('used_at'));
        $this->assertTrue(AuditLog::query()->where('event', 'user.logged_in')->where('target_id', $user->id)
            ->where('metadata->authentication_method', 'qr_code')->exists());

        // Used codes cannot sign in another device.
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->get(route('auth.qr-login', $token))->assertRedirect(url('/'))->assertSessionHas('login_error', __('global.qr_login.invalid'));
        $this->assertGuest('web');
    }

    public function test_expired_replaced_unknown_and_blocked_codes_are_refused(): void
    {
        $user = $this->user();

        // Expired after the TTL.
        Carbon::setTestNow(now());
        $expired = $this->issueToken($user);
        Carbon::setTestNow(now()->addSeconds(QrLoginService::TTL_SECONDS + 1));
        $this->get(route('auth.qr-login', $expired))->assertSessionHas('login_error');
        $this->assertGuest('web');

        // A new code revokes the previous unused one.
        $old = $this->issueToken($user);
        $new = $this->issueToken($user);
        $this->get(route('auth.qr-login', $old))->assertSessionHas('login_error');
        $this->assertGuest('web');

        // Unknown or malformed codes.
        $this->get(route('auth.qr-login', str_repeat('a', 48)))->assertSessionHas('login_error');
        $this->get(route('auth.qr-login', 'short'))->assertSessionHas('login_error');
        $this->assertGuest('web');

        // The account was blocked after the code was shown.
        $user->forceFill(['status' => 'blocked'])->save();
        $this->get(route('auth.qr-login', $new))->assertSessionHas('login_error');
        $this->assertGuest('web');
    }
}
