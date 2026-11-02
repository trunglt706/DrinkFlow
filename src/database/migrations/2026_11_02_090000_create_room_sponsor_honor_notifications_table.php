<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per room and month whose top 3 sponsors were announced (in-app + socket + room channels): the unique index
 * makes overlapping scheduler runs (or a manual re-run of the command) send the announcement at most once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_sponsor_honor_notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->date('month');
            $table->unsignedInteger('member_count')->default(0);
            $table->unsignedInteger('channel_count')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['room_id', 'month'], 'room_sponsor_honor_notifications_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_sponsor_honor_notifications');
    }
};
