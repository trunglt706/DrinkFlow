<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Days on which each member opened a room page (one row per day, room and account; the unique index makes the
 * recording idempotent). Source of the product metrics: DAU / WAU / MAU and stickiness.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_active_members', function (Blueprint $table): void {
            $table->id();
            $table->date('activity_date');
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('global_user_id')->constrained('global_users')->cascadeOnDelete();
            $table->unique(['activity_date', 'room_id', 'global_user_id'], 'daily_active_members_unique');
            $table->index(['activity_date', 'global_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_active_members');
    }
};
