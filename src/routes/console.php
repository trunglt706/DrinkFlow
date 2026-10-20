<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 1. Prune expired audit logs daily at 02:00
Schedule::command('drinkflow:prune-audit-logs')
    ->dailyAt('02:00')
    ->withoutOverlapping();

// 2. Prune read admin & user notifications daily at 02:30
Schedule::command('drinkflow:prune-notifications')
    ->dailyAt('02:30')
    ->withoutOverlapping();

// 3. Prune crawler previews and storage logs daily at 03:00
Schedule::command('drinkflow:prune-crawler-and-logs')
    ->dailyAt('03:00')
    ->withoutOverlapping();

// 3b. Prune auxiliary data: sessions, expired/revoked trusted devices, notifications daily at 03:30
Schedule::command('drinkflow:prune')
    ->dailyAt('03:30')
    ->withoutOverlapping();


// 4. Remind members who have not ordered yet (and room admins) shortly before a campaign's deadline
Schedule::command('drinkflow:remind-campaign-deadlines')
    ->everyMinute()
    ->withoutOverlapping();

// 5. Snapshot queue/storage/socket health for the superadmin dashboard history (older snapshots are pruned in the same run)
Schedule::command('drinkflow:capture-system-metrics')
    ->everyFifteenMinutes()
    ->withoutOverlapping();

// 6. Close ended subscription periods: renew, apply scheduled downgrades, end cancelled subscriptions
Schedule::command('subscriptions:process')
    ->hourly()
    ->withoutOverlapping();

// Announce today's member birthdays in each room at 9:00 room time (hourly so every room timezone is covered; once a day per room).
Schedule::command('drinkflow:notify-birthdays')
    ->hourlyAt(0)
    ->withoutOverlapping();

// Occasion greetings (New Year, Tết, 8/3, 20/10, 19/11, Christmas) at 9:00 room time on the day (hourly so every room
// timezone is covered; the command returns at once on the other days of the year; once a year per room and occasion).
Schedule::command('drinkflow:notify-occasions')
    ->hourlyAt(5)
    ->withoutOverlapping();

// Remind Agents a few days before their package period ends (once per period).
Schedule::command('subscriptions:remind-renewals')
    ->dailyAt('08:00')
    ->withoutOverlapping();

// 7. Platform billing: invoice new subscription periods, then flag unpaid invoices past their due date
Schedule::command('billing:generate-invoices')
    ->hourlyAt(10)
    ->withoutOverlapping();
Schedule::command('billing:process-overdue')
    ->dailyAt('01:30')
    ->withoutOverlapping();
Schedule::command('billing:enforce-overdue')
    ->dailyAt('01:45')
    ->withoutOverlapping();

// Calculate last month's honors for active rooms on the first day of each month.
Schedule::command('drinkflow:calculate-monthly-sponsors')
    ->monthlyOn(1, '00:15')
    ->withoutOverlapping();
