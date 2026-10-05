<?php

namespace Tests\Feature;

use App\Actions\User\JoinRoomAction;
use App\Models\GlobalUser;
use App\Models\Room;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GlobalDevicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_accessing_me_devices_redirects_to_home_or_referer(): void
    {
        $response = $this->get('/me/devices');
        $response->assertRedirect('/');

        $refererResponse = $this->from(url('/contact'))->get('/me/devices');
        $refererResponse->assertRedirect(url('/contact'));
    }

    public function test_authenticated_user_can_view_me_devices_page(): void
    {
        $user = GlobalUser::create([
            'name' => 'Trung Lê',
            'normalized_name' => 'TRUNG LE',
            'email' => 'trung.lt@company.com',
            'status' => 'active',
        ]);

        $room = Room::create(['name' => 'Ban Công nghệ & Kỹ thuật số', 'slug' => 'tech-team']);
        app(JoinRoomAction::class)->execute($user, $room, 'device-1', 'hash-1');

        DB::table('sessions')->insert([
            'id' => 'session-remote-456',
            'user_id' => $user->id,
            'ip_address' => '118.70.144.12',
            'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)',
            'payload' => 'dummy',
            'last_activity' => time(),
        ]);

        $response = $this->actingAs($user, 'web')->get('/me/devices');

        $response->assertOk();
        $response->assertSee('Bảo mật tài khoản &amp; Thiết bị đăng nhập', false);
        $response->assertSee('trung.lt@company.com');
        $response->assertSee('Thiết bị hiện tại của bạn');
        $response->assertSee('Đăng xuất phiên này');
    }

    public function test_user_can_logout_a_specific_session(): void
    {
        $user = GlobalUser::create([
            'name' => 'Trung Lê',
            'normalized_name' => 'TRUNG LE',
            'email' => 'trung.lt@company.com',
            'status' => 'active',
        ]);

        DB::table('sessions')->insert([
            'id' => 'session-remote-123',
            'user_id' => $user->id,
            'ip_address' => '118.70.144.12',
            'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)',
            'payload' => 'dummy',
            'last_activity' => time(),
        ]);

        $response = $this->withoutMiddleware(ValidateCsrfToken::class)
            ->actingAs($user, 'web')
            ->post('/me/devices/logout/session-remote-123');

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $this->assertDatabaseMissing('sessions', [
            'id' => 'session-remote-123',
        ]);
    }

    public function test_user_can_logout_all_other_sessions(): void
    {
        $user = GlobalUser::create([
            'name' => 'Trung Lê',
            'normalized_name' => 'TRUNG LE',
            'email' => 'trung.lt@company.com',
            'status' => 'active',
        ]);

        DB::table('sessions')->insert([
            [
                'id' => 'remote-session-a',
                'user_id' => $user->id,
                'ip_address' => '118.70.144.12',
                'user_agent' => 'Safari on iPhone',
                'payload' => 'dummy',
                'last_activity' => time(),
            ],
            [
                'id' => 'remote-session-b',
                'user_id' => $user->id,
                'ip_address' => '14.161.28.88',
                'user_agent' => 'Chrome on Mac',
                'payload' => 'dummy',
                'last_activity' => time(),
            ],
        ]);

        $response = $this->withoutMiddleware(ValidateCsrfToken::class)
            ->actingAs($user, 'web')
            ->post('/me/devices/logout-all');

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $this->assertDatabaseMissing('sessions', ['id' => 'remote-session-a']);
        $this->assertDatabaseMissing('sessions', ['id' => 'remote-session-b']);
    }

    public function test_user_cannot_delete_account_with_invalid_confirmation(): void
    {
        $user = GlobalUser::create([
            'name' => 'Trung Lê',
            'normalized_name' => 'TRUNG LE',
            'email' => 'trung.lt@company.com',
            'status' => 'active',
        ]);

        $response = $this->withoutMiddleware(ValidateCsrfToken::class)
            ->actingAs($user, 'web')
            ->post('/me/account/delete', [
                'confirm_delete' => 'sai_email@company.com',
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['confirm_delete']);

        $user->refresh();
        $this->assertEquals('active', $user->status->value);
    }

    public function test_user_can_delete_account_with_valid_confirmation(): void
    {
        $user = GlobalUser::create([
            'name' => 'Trung Lê',
            'normalized_name' => 'TRUNG LE',
            'email' => 'trung.lt@company.com',
            'status' => 'active',
        ]);

        $response = $this->withoutMiddleware(ValidateCsrfToken::class)
            ->actingAs($user, 'web')
            ->post('/me/account/delete', [
                'confirm_delete' => 'XÓA TÀI KHOẢN',
            ]);

        $response->assertRedirect('/');

        $user->refresh();
        $this->assertEquals('disabled', $user->status->value);
    }

    public function test_logout_other_devices_rotates_remember_token_and_revokes_other_devices(): void
    {
        $user = GlobalUser::create([
            'name' => 'Trung Lê',
            'normalized_name' => 'TRUNG LE',
            'email' => 'trung.lt2@company.com',
            'status' => 'active',
            'remember_token' => 'initial-token-123456789012345678901234567890123456789012345678901234567890',
        ]);

        $room = Room::create(['name' => 'IT Team', 'slug' => 'it-team']);
        $roomUser = app(JoinRoomAction::class)->execute($user, $room, 'current-device-uuid', 'hash-current');
        \App\Models\RoomUserDevice::create([
            'room_user_id' => $roomUser->id,
            'device_uuid' => 'other-device-uuid',
            'token_hash' => hash('sha256', 'other-token'),
            'verified_at' => now(),
            'last_seen_at' => now(),
        ]);

        $service = app(\App\Services\User\UserSessionService::class);
        $service->logoutOtherDevices($user, 'current-session-id', 'current-device-uuid');

        $user->refresh();
        $this->assertNotEquals('initial-token-123456789012345678901234567890123456789012345678901234567890', $user->remember_token);
        $this->assertNotNull($user->remember_token);

        $this->assertDatabaseHas('room_user_devices', [
            'device_uuid' => 'current-device-uuid',
            'revoked_at' => null,
        ]);
        $this->assertDatabaseMissing('room_user_devices', [
            'device_uuid' => 'other-device-uuid',
            'revoked_at' => null,
        ]);
    }

    public function test_logout_single_device_does_not_revoke_other_trusted_devices(): void
    {
        $user = GlobalUser::create([
            'name' => 'Trung Lê',
            'normalized_name' => 'TRUNG LE',
            'email' => 'trung.single@company.com',
            'status' => 'active',
        ]);

        $room = Room::create(['name' => 'IT Single', 'slug' => 'it-single']);
        $roomUser = app(JoinRoomAction::class)->execute($user, $room, 'device-stay', 'hash-stay');
        \App\Models\RoomUserDevice::create([
            'room_user_id' => $roomUser->id,
            'device_uuid' => 'device-also-stay',
            'token_hash' => hash('sha256', 'token-also-stay'),
            'verified_at' => now(),
            'last_seen_at' => now(),
        ]);

        DB::table('sessions')->insert([
            'id' => 'session-to-kill',
            'user_id' => $user->id,
            'ip_address' => '1.2.3.4',
            'user_agent' => 'Other Browser',
            'payload' => 'payload',
            'last_activity' => time(),
        ]);

        $service = app(\App\Services\User\UserSessionService::class);
        $service->logoutDevice($user, 'session-to-kill', 'device-stay');

        $this->assertDatabaseMissing('sessions', ['id' => 'session-to-kill']);
        $this->assertDatabaseHas('room_user_devices', [
            'device_uuid' => 'device-also-stay',
            'revoked_at' => null,
        ]);
    }

    public function test_device_trust_service_enforces_server_side_ttl(): void
    {
        $user = GlobalUser::create([
            'name' => 'TTL User',
            'normalized_name' => 'TTL USER',
            'email' => 'ttl@company.com',
            'status' => 'active',
        ]);
        $room = Room::create(['name' => 'TTL Room', 'slug' => 'ttl-room']);
        $roomUser = app(JoinRoomAction::class)->execute($user, $room, 'ttl-device', 'hash-placeholder');

        $trustService = app(\App\Services\Auth\DeviceTrustService::class);
        $token = $trustService->issue($roomUser, 'ttl-device');

        // Fresh token resolves successfully
        $resolved = $trustService->resolve('ttl-device', $token, $room->id);
        $this->assertNotNull($resolved);

        // Expire the token by setting verified_at to 31 days ago
        \App\Models\RoomUserDevice::where('device_uuid', 'ttl-device')->update([
            'verified_at' => now()->subDays(31),
        ]);

        $expiredResolved = $trustService->resolve('ttl-device', $token, $room->id);
        $this->assertNull($expiredResolved);
    }

    public function test_logout_endpoint_requires_post(): void
    {
        $user = GlobalUser::create([
            'name' => 'Logout User',
            'normalized_name' => 'LOGOUT USER',
            'email' => 'logout@company.com',
            'status' => 'active',
        ]);

        // GET /logout should return 405 Method Not Allowed
        $getResponse = $this->actingAs($user, 'web')->get('/logout');
        $getResponse->assertStatus(405);

        // POST /logout should succeed
        $postResponse = $this->withoutMiddleware(ValidateCsrfToken::class)
            ->actingAs($user, 'web')
            ->post('/logout');
        $postResponse->assertRedirect('/');
        $this->assertGuest('web');
    }
}
