<?php

declare(strict_types=1);

namespace App\Services\Realtime;

use App\Enums\GlobalUserStatus;
use App\Enums\RoomStatus;
use App\Enums\SuperadminStatus;
use App\Enums\RoomUserStatus;
use App\Models\Admin;
use App\Models\GlobalUser;
use App\Models\Room;
use App\Models\Superadmin;
use App\Models\RoomUser;
use App\Models\SponsorDrawSession;
use Illuminate\Support\Str;

class SocketTokenService
{
    /** Actor types understood by the realtime gateway. */
    public const ACTOR_ADMIN = 'admin';
    public const ACTOR_SUPERADMIN = 'superadmin';

    /** Trusted device UUIDs accepted in tokens (also enforced by the realtime server's `device:` channel). */
    private const DEVICE_UUID_PATTERN = '/^[A-Za-z0-9-]{1,128}$/';

    /**
     * Issue a realtime socket authentication token for a room user.
     *
     * @param  RoomUser  $roomUser  The room user entity requesting the token.
     * @param  int  $ttlSeconds  Time-to-live for the token in seconds.
     * @param  string|null  $deviceUuid  Trusted device UUID; when valid, the socket also joins `device:{uuid}`
     *                                   so revoking that device can force its open room pages to reload.
     * @return string Signed HMAC token string.
     */
    public function issue(RoomUser $roomUser, int $ttlSeconds = 300, ?string $deviceUuid = null): string
    {
        abort_unless(
            $roomUser->globalUser?->status === GlobalUserStatus::Active
            && $roomUser->status === RoomUserStatus::Active,
            403
        );

        $payload = [
            'actor_type' => 'user',
            'global_user_id' => $roomUser->global_user_id,
            'room_user_id' => $roomUser->id,
            'room_id' => $roomUser->room_id,
            'exp' => now()->addSeconds($ttlSeconds)->timestamp,
            'jti' => (string) Str::uuid(),
        ];
        if ($deviceUuid !== null && preg_match(self::DEVICE_UUID_PATTERN, $deviceUuid) === 1) {
            $payload['device_uuid'] = $deviceUuid;
        }
        $encoded = $this->encode($payload);

        return $encoded . '.' . hash_hmac('sha256', $encoded, $this->signingSecret());
    }

    /** Issue a realtime token scoped to one trusted user device. */
    public function issueForDevice(GlobalUser $user, string $deviceUuid, int $ttlSeconds = 300): string
    {
        abort_unless($user->status === GlobalUserStatus::Active && preg_match(self::DEVICE_UUID_PATTERN, $deviceUuid) === 1, 403);

        $payload = [
            'actor_type' => 'user',
            'global_user_id' => $user->id,
            'device_uuid' => $deviceUuid,
            'exp' => now()->addSeconds($ttlSeconds)->timestamp,
            'jti' => (string) Str::uuid(),
        ];
        $encoded = $this->encode($payload);

        return $encoded . '.' . hash_hmac('sha256', $encoded, $this->signingSecret());
    }

    /**
     * Issue a realtime socket authentication token for an admin account.
     *
     * @param  Admin  $admin  The admin account instance.
     * @param  Room|null  $room  Optional specific room context.
     * @param  int  $ttlSeconds  Time-to-live for the token in seconds.
     * @return string Signed HMAC token string.
     */
    public function issueForAdmin(Admin $admin, ?Room $room = null, int $ttlSeconds = 300): string
    {
        abort_unless($admin->isActive(), 403);

        // Same rule as the admin room area (RoomPolicy::operate): owner or collaborator only.
        if (!$room || !\Illuminate\Support\Facades\Gate::forUser($admin)->allows('operate', $room)) {
            abort(403);
        }
        $roomIds = [$room->id];

        $payload = [
            'actor_type' => self::ACTOR_ADMIN,
            'admin_id' => $admin->id,
            'room_ids' => $roomIds,
            'exp' => now()->addSeconds($ttlSeconds)->timestamp,
            'jti' => (string) Str::uuid(),
        ];
        $encoded = $this->encode($payload);

        return $encoded . '.' . hash_hmac('sha256', $encoded, $this->signingSecret());
    }

    /**
     * Issue a realtime socket token for a Superadmin: the superadmin and system channels plus every active room.
     *
     * @param Superadmin $superadmin Signed-in superadmin.
     * @param int $ttlSeconds Time-to-live for the token in seconds.
     * @return string Signed HMAC token string.
     */
    public function issueForSuperadmin(Superadmin $superadmin, int $ttlSeconds = 300): string
    {
        abort_unless($superadmin->status === SuperadminStatus::Active, 403);

        $encoded = $this->encode([
            'actor_type' => self::ACTOR_SUPERADMIN,
            'superadmin_id' => $superadmin->id,
            'room_ids' => Room::query()->where('status', RoomStatus::Active->value)->pluck('id')->all(),
            'exp' => now()->addSeconds($ttlSeconds)->timestamp,
            'jti' => (string) Str::uuid(),
        ]);

        return $encoded . '.' . hash_hmac('sha256', $encoded, $this->signingSecret());
    }

    /**
     * Issue a token that only joins one sponsor draw session's channel (draw:{id}).
     *
     * The caller has already checked the viewer's right to watch the session. The token never outlives the
     * session's access link, so a viewer cannot reconnect once the link has expired.
     *
     * @param SponsorDrawSession $session Session being watched (link must be valid).
     * @param int $ttlSeconds Maximum time-to-live in seconds.
     * @return string Signed HMAC token string.
     */
    public function issueForDrawViewer(SponsorDrawSession $session, int $ttlSeconds = 300): string
    {
        abort_unless($session->linkIsValid(), 410);

        $encoded = $this->encode([
            'actor_type' => 'draw_viewer',
            'draw_session_id' => $session->id,
            'exp' => min(now()->addSeconds($ttlSeconds)->timestamp, $session->link_expires_at->timestamp),
            'jti' => (string) Str::uuid(),
        ]);

        return $encoded . '.' . hash_hmac('sha256', $encoded, $this->signingSecret());
    }

    /**
     * Verify and decode a given realtime socket token.
     *
     * @param  string  $token  The raw token string.
     * @return array<string, mixed>|null Decoded payload array or null if invalid/expired.
     */
    public function verify(string $token): ?array
    {
        [$encoded, $signature] = array_pad(explode('.', $token, 2), 2, '');

        if ($encoded === '' || $signature === '' || !hash_equals(hash_hmac('sha256', $encoded, $this->signingSecret()), $signature)) {
            return null;
        }

        $payload = json_decode((string) base64_decode(strtr($encoded, '-_', '+/')), true);

        return is_array($payload) && ($payload['actor_type'] ?? null) === 'user' && ($payload['exp'] ?? 0) >= now()->timestamp ? $payload : null;
    }

    /**
     * Encode payload data into base64url format.
     *
     * @param  array<string, mixed>  $payload  The data payload to encode.
     * @return string Base64url encoded string.
     */
    private function encode(array $payload): string
    {
        return rtrim(strtr(base64_encode((string) json_encode($payload, JSON_UNESCAPED_SLASHES)), '+/', '-_'), '=');
    }

    /**
     * Resolve the shared HMAC secret used by Laravel and the realtime gateway.
     *
     * @return string Secret used to sign and verify socket tokens.
     */
    private function signingSecret(): string
    {
        $configured = trim((string) config('services.realtime.socket_token_secret', ''));

        return $configured !== '' ? $configured : (string) config('app.key');
    }
}
