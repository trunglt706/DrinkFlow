<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\Permission;
use App\Enums\PermissionScope;
use App\Enums\SuperadminStatus;
use App\Mail\AgentBillingSuspendedMail;
use App\Models\Admin;
use App\Models\AdminInvoice;
use App\Models\Superadmin;
use App\Models\SuperadminAdmin;
use App\Services\Notification\SuperadminNotificationService;
use App\Services\Notification\SystemNotificationChannelDispatcher;
use App\Support\Helpers\FormatHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Announce an automatic billing suspension.
 *
 * The Agent gets an email. The platform team is told on three channels: the Superadmin inbox (bell +
 * notifications page) with the realtime push (through {@see SuperadminNotificationService}), and the
 * configured outbound channels (Slack/Telegram/ChatWork/webhook). Inbox copies go to the Superadmins
 * managing the Agent and to those with full (`all` scope) access to platform debts, and only to
 * accounts allowed to open the Agent's billing page the alert links to. Outbound delivery is
 * best-effort and runs after the suspension committed.
 */
class BillingSuspensionNotifier
{
    /** Notification type shown in the Superadmin inbox. */
    public const TYPE = 'agent.billing_suspended';

    public function __construct(
        private readonly SuperadminNotificationService $notifications,
        private readonly SystemNotificationChannelDispatcher $channels,
    ) {}

    /**
     * Notify the Agent and the responsible Superadmins about the suspension.
     *
     * @param Admin $admin Suspended Agent.
     * @param AdminInvoice|null $invoice Oldest overdue invoice that caused it.
     * @return int Number of Superadmin inbox rows created.
     */
    public function notifySuspended(Admin $admin, ?AdminInvoice $invoice): int
    {
        Mail::to($admin->email)->queue((new AgentBillingSuspendedMail($admin, $invoice))->locale(app()->getLocale())->afterCommit());

        $recipients = $this->recipientIds($admin);
        $title = __('platform.billing.suspended_notify_title', ['name' => $admin->name]);
        $body = __('platform.billing.suspended_notify_body', [
            'number' => $invoice?->number ?? '—',
            'amount' => FormatHelper::formatCurrency((int) ($invoice?->remaining() ?? 0)),
        ]);
        $created = $recipients === [] ? 0 : $this->notifications->notifySuperadmins(self::TYPE, $title, $body, [
            'admin_id' => $admin->id,
            'name' => $admin->name,
            'email' => $admin->email,
            'invoice_id' => $invoice?->id,
            'invoice_number' => $invoice?->number,
            // Relative, so the inbox link works whatever host the console is opened on.
            'url' => route('superadmin.billing.agent', $admin->id, false),
        ], $recipients, Permission::DebtView)->count();

        $outbound = function () use ($admin, $invoice, $title): void {
            $this->dispatchOutbound($admin, $invoice, $title);
        };
        DB::transactionLevel() > 0 ? DB::afterCommit($outbound) : $outbound();

        return $created;
    }

    /**
     * Superadmins managing the Agent plus those whose debt access covers every Agent.
     *
     * @param Admin $admin Agent.
     * @return array<int, int> Superadmin IDs (may be empty).
     */
    private function recipientIds(Admin $admin): array
    {
        $managers = SuperadminAdmin::query()->where('admin_id', $admin->id)->pluck('superadmin_id')->map(static fn ($id): int => (int) $id)->all();

        return Superadmin::query()->where('status', SuperadminStatus::Active->value)->get()
            ->filter(static fn (Superadmin $superadmin): bool => in_array((int) $superadmin->id, $managers, true)
                || $superadmin->scopeFor(Permission::DebtView) === PermissionScope::All)
            ->map(static fn (Superadmin $superadmin): int => (int) $superadmin->id)
            ->values()->all();
    }

    /**
     * Push the alert to the configured outbound channels.
     *
     * @param Admin $admin Suspended Agent.
     * @param AdminInvoice|null $invoice Overdue invoice.
     * @param string $title Translated title.
     * @return void
     */
    private function dispatchOutbound(Admin $admin, ?AdminInvoice $invoice, string $title): void
    {
        try {
            $this->channels->dispatch([
                'event' => self::TYPE,
                'title' => $title,
                'message' => implode("\n", array_filter([
                    __('platform.registrations.notify_field_agent').': '.$admin->name,
                    __('platform.registrations.notify_field_email').': '.$admin->email,
                    $invoice ? __('platform.billing.invoice').': '.$invoice->number.' · '.FormatHelper::formatCurrency($invoice->remaining()) : null,
                    __('platform.registrations.notify_field_action').': '.route('superadmin.billing.agent', $admin->id),
                ])),
            ]);
        } catch (\Throwable $exception) {
            // Outbound delivery is best-effort: the inbox copies above are already stored.
            Log::warning('Billing suspension notification could not reach an outbound channel.', [
                'admin_id' => $admin->id,
                'channel_exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
