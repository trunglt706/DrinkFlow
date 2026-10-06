<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Subscription\SubscriptionRenewalReminderService;
use Illuminate\Console\Command;

class RemindSubscriptionRenewals extends Command
{
    /** @var string */
    protected $signature = 'subscriptions:remind-renewals';

    /** @var string */
    protected $description = 'Email Agents whose package period ends soon (once per period, PLATFORM_RENEWAL_REMINDER_DAYS ahead).';

    /**
     * @param SubscriptionRenewalReminderService $reminders Renewal reminder service.
     * @return int Exit code.
     */
    public function handle(SubscriptionRenewalReminderService $reminders): int
    {
        $this->info('Renewal reminders sent: '.$reminders->remindExpiring());

        return self::SUCCESS;
    }
}
