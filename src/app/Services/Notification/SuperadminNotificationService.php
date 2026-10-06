<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Enums\Permission;
use App\Enums\SuperadminStatus;
use App\Events\SuperadminNotificationCreated;
use App\Models\Superadmin;
use App\Models\SuperadminNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Platform notifications of Superadmins: delivery, inbox reading and read receipts.
 *
 * An alert raised by the platform (not by a room) is written for every active Superadmin, one row
 * each, then published to the realtime gateway on that account's private channel. Every read path
 * here is restricted to the signed-in account, so a Superadmin can never read or clear another
 * Superadmin's copy — exactly like {@see AdminNotificationService} does for Agents.
 */
class SuperadminNotificationService
{
    /** Inbox filter value: only notifications not read yet. */
    public const FILTER_UNREAD = 'unread';

    /** Inbox filter value: only notifications already read. */
    public const FILTER_READ = 'read';

    /**
     * Deliver one platform notification to every active Superadmin.
     *
     * @param string $type Notification type, e.g. `agent.registered`.
     * @param string $title Translated title.
     * @param string $body Translated body.
     * @param array<string, mixed> $data Structured payload for the inbox and the realtime client.
     * @param array<int, int> $onlyRecipients Superadmin IDs to restrict delivery to; empty means every active account.
     * @param Permission|null $requiredPermission Only accounts holding this permission (any scope) receive it, so an
     *                                            alert never links to a page its recipient cannot open; null = no filter.
     * @return Collection<int, SuperadminNotification> Created notifications, one per recipient.
     */
    public function notifySuperadmins(string $type, string $title, string $body, array $data = [], array $onlyRecipients = [], ?Permission $requiredPermission = null): Collection
    {
        $recipients = $this->recipients($onlyRecipients, $requiredPermission);
        $created = new Collection();

        foreach ($recipients as $superadmin) {
            $notification = SuperadminNotification::create([
                'superadmin_id' => $superadmin->id,
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'data' => $data,
            ]);
            $created->push($notification);
            // Published after the row exists and after the surrounding transaction commits, so a
            // rolled-back registration never pushes a notification for a row that does not exist.
            $publish = static fn () => SuperadminNotificationCreated::dispatch($notification);
            DB::transactionLevel() > 0 ? DB::afterCommit($publish) : $publish();
        }

        return $created;
    }

    /**
     * Newest unread notifications of one account, for the header bell.
     *
     * @param Superadmin $superadmin Recipient account.
     * @param int $limit Maximum notifications to return.
     * @return Collection<int, SuperadminNotification> Unread notifications, newest first.
     */
    public function unreadFor(Superadmin $superadmin, int $limit = 5): Collection
    {
        return $this->forSuperadmin($superadmin)
            ->whereNull('read_at')
            ->latest('created_at')
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Total unread notifications of one account, for the bell badge.
     *
     * @param Superadmin $superadmin Recipient account.
     * @return int Unread count.
     */
    public function unreadCountFor(Superadmin $superadmin): int
    {
        return $this->forSuperadmin($superadmin)->whereNull('read_at')->count();
    }

    /**
     * Paginate the notifications of one account.
     *
     * @param Superadmin $superadmin Recipient account.
     * @param array{status?: ?string, search?: ?string, from?: ?\DateTimeInterface, to?: ?\DateTimeInterface} $filters `status` is unread|read (anything else = all); `search` matches title/body; `from` / `to` bound the received date.
     * @param int $perPage Page size.
     * @param string $pageName Query-string key for the page number, so it can share a page with another paginator.
     * @return LengthAwarePaginator Notifications, newest first.
     */
    public function paginateFor(Superadmin $superadmin, array $filters, int $perPage, string $pageName = 'page'): LengthAwarePaginator
    {
        $status = $filters['status'] ?? null;
        $search = trim((string) ($filters['search'] ?? ''));
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;

        if (! $this->tableExists()) {
            return new Paginator([], 0, $perPage, 1, ['pageName' => $pageName]);
        }

        return $this->forSuperadmin($superadmin)
            ->when($status === self::FILTER_UNREAD, static fn (Builder $query) => $query->whereNull('read_at'))
            ->when($status === self::FILTER_READ, static fn (Builder $query) => $query->whereNotNull('read_at'))
            ->when($search !== '', static fn (Builder $query) => $query->where(
                static fn (Builder $inner) => $inner->where('title', 'like', "%{$search}%")->orWhere('body', 'like', "%{$search}%")
            ))
            ->when($from instanceof \DateTimeInterface, static fn (Builder $query) => $query->where('created_at', '>=', $from))
            ->when($to instanceof \DateTimeInterface, static fn (Builder $query) => $query->where('created_at', '<=', $to))
            ->latest('created_at')
            ->latest('id')
            ->paginate($perPage, ['*'], $pageName);
    }

    /**
     * Mark one notification as read, only when it belongs to the given account.
     *
     * @param Superadmin $superadmin Recipient account.
     * @param SuperadminNotification $notification Notification to mark.
     * @return bool False when the notification belongs to another account.
     */
    public function markRead(Superadmin $superadmin, SuperadminNotification $notification): bool
    {
        if ((int) $notification->superadmin_id !== (int) $superadmin->id) {
            return false;
        }

        if ($notification->read_at === null) {
            $notification->forceFill(['read_at' => now()])->save();
        }

        return true;
    }

    /**
     * Mark every unread notification of one account as read.
     *
     * @param Superadmin $superadmin Recipient account.
     * @return int Number of notifications marked as read.
     */
    public function markAllRead(Superadmin $superadmin): int
    {
        return $this->forSuperadmin($superadmin)->whereNull('read_at')->update(['read_at' => now()]);
    }

    /**
     * Base query restricted to one account's notifications.
     *
     * @param Superadmin $superadmin Recipient account.
     * @return Builder<SuperadminNotification> Scoped query.
     */
    private function forSuperadmin(Superadmin $superadmin): Builder
    {
        return SuperadminNotification::query()->where('superadmin_id', $superadmin->id);
    }

    /**
     * Active Superadmins that should receive a platform alert.
     *
     * @param array<int, int> $onlyRecipients Explicit recipient IDs (used when a caller wants a subset).
     * @param Permission|null $requiredPermission Permission every recipient must hold; null = no filter.
     * @return Collection<int, Superadmin> Recipients, oldest account first for a stable order.
     */
    private function recipients(array $onlyRecipients, ?Permission $requiredPermission = null): Collection
    {
        $query = Superadmin::query()
            // A suspended account cannot sign in, so it would never read the alert.
            ->where('status', SuperadminStatus::Active->value)
            ->orderBy('id');

        if ($onlyRecipients !== []) {
            $query->whereIn('id', $onlyRecipients);
        }

        if ($requiredPermission !== null) {
            // Same grant table the `permission:` route middleware reads (Superadmin::hasPermission).
            $query->whereHas('permissions', static fn (Builder $grant) => $grant->where('key', $requiredPermission->value));
        }

        return $query->get();
    }

    /**
     * Whether the notification table exists yet (keeps an un-migrated install usable).
     *
     * @return bool True when the table is available.
     */
    private function tableExists(): bool
    {
        return Schema::hasTable('superadmin_notifications');
    }
}
