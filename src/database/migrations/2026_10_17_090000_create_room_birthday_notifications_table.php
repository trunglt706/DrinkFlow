<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per room and day whose birthday notification was sent: the unique index makes overlapping scheduler
 * runs (or several servers) send it at most once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_birthday_notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->date('celebrated_on');
            $table->unsignedInteger('celebrant_count')->default(0);
            $table->unsignedInteger('channel_count')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['room_id', 'celebrated_on'], 'room_birthday_notifications_room_day_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_birthday_notifications');
    }
};
