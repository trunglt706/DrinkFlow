<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\RoomUserDevice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PruneAuxiliaryDataCommand extends Command
{
    /** @var string */
    protected $signature = 'drinkflow:prune';

    /** @var string */
    protected $description = 'Prune auxiliary data: expired sessions, revoked/expired trusted devices, and old notifications.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $now = now();

        // 1. Prune revoked devices older than retention (default 30 days)
        $revokedDays = (int) config('auth.revoked_device_retention_days', 30);
        $prunedRevoked = RoomUserDevice::query()
            ->whereNotNull('revoked_at')
            ->where('revoked_at', '<', $now->copy()->subDays($revokedDays))
            ->delete();

        // 2. Prune expired trusted devices older than TTL
        $ttlDays = (int) config('auth.trusted_device_ttl_days', 30);
        $prunedExpiredDevices = RoomUserDevice::query()
            ->whereNull('revoked_at')
            ->where('verified_at', '<', $now->copy()->subDays($ttlDays))
            ->delete();

        // 3. Prune expired sessions if sessions table exists
        $prunedSessions = 0;
        if (Schema::hasTable('sessions')) {
            $lifetimeMinutes = (int) config('session.lifetime', 120);
            $cutoffTimestamp = $now->copy()->subMinutes($lifetimeMinutes)->getTimestamp();
            $prunedSessions = DB::table('sessions')
                ->where('last_activity', '<', $cutoffTimestamp)
                ->delete();
        }

        // 4. Trigger notification pruning
        $this->call('drinkflow:prune-notifications');

        $this->info("Pruned {$prunedRevoked} revoked devices, {$prunedExpiredDevices} expired devices, and {$prunedSessions} expired sessions.");

        return self::SUCCESS;
    }
}
