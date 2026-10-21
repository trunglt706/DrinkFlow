<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Debt\DebtAutoReminderService;
use Illuminate\Console\Command;

class RemindDebts extends Command
{
    /** @var string */
    protected $signature = 'drinkflow:remind-debts';

    /** @var string */
    protected $description = 'Send the tiered automatic debt reminders (1, 3 and 7 days after a debt is created), from 9:00 room time.';

    /**
     * Send the due reminders of every room.
     *
     * @param DebtAutoReminderService $reminders Automatic debt reminder service.
     * @return int Process exit code.
     */
    public function handle(DebtAutoReminderService $reminders): int
    {
        $count = $reminders->remindDueRooms();
        $this->info("Sent {$count} debt reminder(s).");

        return self::SUCCESS;
    }
}
