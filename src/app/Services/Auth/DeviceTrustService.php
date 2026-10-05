<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Events\ForceReloadRequested;
use App\Models\RoomUser;
use App\Models\RoomUserDevice;
use Illuminate\Support\Str;

class DeviceTrustService
{
    /**
     * Issue a trusted token for a room user device.
     *
     * @param RoomUser $roomUser Target room user.
     * @param string $deviceUuid Device UUID.
     * @return string Plain-text token.
     */
    public function issue(RoomUser $roomUser, string $deviceUuid): string
    {
        $token = Str::random(64);
        RoomUserDevice::updateOrCreate(
            ['room_user_id' => $roomUser->id, 'device_uuid' => $deviceUuid],
            ['token_hash' => $this->hash($token), 'verified_at' => now(), 'last_seen_at' => now(), 'revoked_at' => null],
        );

        return $token;
    }

    /**
     * Resolve and validate a trusted device token against server-side TTL.
     *
     * @param string $deviceUuid Device UUID.
     * @param string $token Plain-text token.
     * @param ?int $roomId Optional room ID to restrict resolution.
     * @return ?RoomUserDevice
     */
    public function resolve(string $deviceUuid, string $token, ?int $roomId = null): ?RoomUserDevice
    {
        if ($deviceUuid === '' || $token === '') {
            return null;
        }

        $ttlDays = (int) config('auth.trusted_device_ttl_days', 30);
        $cutoff = now()->subDays($ttlDays);

        $query = RoomUserDevice::query()
            ->with('roomUser.globalUser')
            ->where('device_uuid', $deviceUuid)
            ->whereNull('revoked_at')
            ->where('verified_at', '>=', $cutoff);

        if ($roomId !== null) {
            $query->whereHas('roomUser', fn ($q) => $q->where('room_id', $roomId));
        }

        $device = $query->latest('id')->get()->first(fn (RoomUserDevice $device) => hash_equals($device->token_hash, $this->hash($token)));

        if ($device) {
            $lastSeen = $device->last_seen_at;
            if ($lastSeen === null || $lastSeen->lte(now()->subMinutes(5))) {
                $device->update(['last_seen_at' => now()]);
                $device->last_seen_at = now();
            }
        }

        return $device;
    }

    /**
     * Revoke a trusted device and force its open pages to reload, so the device is signed out immediately.
     *
     * @param RoomUserDevice $device Trusted device to revoke.
     * @return void
     */
    public function revoke(RoomUserDevice $device): void
    {
        $device->update(['revoked_at' => now()]);
        event(ForceReloadRequested::forDevice($device->device_uuid, ForceReloadRequested::REASON_DEVICE_REVOKED));
    }

    /**
     * Hash the token with SHA-256 for secure storage.
     *
     * @param string $token Plain-text token.
     * @return string SHA-256 hash.
     */
    private function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
