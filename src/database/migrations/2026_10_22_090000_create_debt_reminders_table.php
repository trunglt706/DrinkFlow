<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tiered automatic debt reminders (1, 3 and 7 days after a debt is created).
 *
 * One row per debt and stage that was sent: the unique index makes overlapping scheduler runs (or several servers)
 * send each stage at most once. The debtor may snooze the reminders of a debt a limited number of times.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('debt_reminders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('debt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('stage');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['debt_id', 'stage'], 'debt_reminders_debt_stage_unique');
            $table->index(['room_id', 'sent_at']);
        });

        Schema::table('debts', function (Blueprint $table): void {
            $table->timestamp('reminder_snoozed_until')->nullable()->after('note');
            $table->unsignedTinyInteger('reminder_snooze_count')->default(0)->after('reminder_snoozed_until');
        });
    }

    public function down(): void
    {
        Schema::table('debts', function (Blueprint $table): void {
            $table->dropColumn(['reminder_snoozed_until', 'reminder_snooze_count']);
        });

        Schema::dropIfExists('debt_reminders');
    }
};
