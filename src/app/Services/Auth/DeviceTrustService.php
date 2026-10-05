<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Events\ForceReloadRequested;
use App\Models\RoomUser;
use App\Models\RoomUserDevice;
use Illuminate\Support\Str;

class DeviceTrustService
{
    /** @var array<string, ?RoomUserDevice> */
    private array $resolvedCache = [];

    /**
     * Handle the issue operation.
     * @param RoomUser $roomUser Parameter value.
     * @param string $deviceUuid Parameter value.
     * @return string Result of the operation.
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
     * Handle the resolve operation.
     * @param string $deviceUuid Parameter value.
     * @param string $token Parameter value.
     * @param ?int $roomId Parameter value.
     * @return ?RoomUserDevice Result of the operation.
     */
    public function resolve(string $deviceUuid, string $token, ?int $roomId = null): ?RoomUserDevice
    {
        if ($deviceUuid === '' || $token === '') {
            return null;
        }

        $cacheKey = $deviceUuid.'|'.$token.'|'.($roomId ?? 'null');
        if (array_key_exists($cacheKey, $this->resolvedCache)) {
            $cachedDevice = $this->resolvedCache[$cacheKey];
            if ($cachedDevice) {
                $lastSeen = $cachedDevice->last_seen_at;
                if ($lastSeen === null || $lastSeen->lte(now()->subMinutes(5))) {
                    $cachedDevice->update(['last_seen_at' => now()]);
                    $cachedDevice->last_seen_at = now();
                }
            }

            return $cachedDevice;
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

        $this->resolvedCache[$cacheKey] = $device;

        return $device;
    }

    /**
     * Revoke a trusted device and force its open pages to reload, so the device is signed out immediately.
     * @param RoomUserDevice $device Trusted device to revoke.
     * @return void
     */
    public function revoke(RoomUserDevice $device): void
    {
        $device->update(['revoked_at' => now()]);
        $this->resolvedCache = [];
        event(ForceReloadRequested::forDevice($device->device_uuid, ForceReloadRequested::REASON_DEVICE_REVOKED));
    }

    /**
     * Handle the hash operation.
     * @param string $token Parameter value.
     * @return string Result of the operation.
     */
    private function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}

