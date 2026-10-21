<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per room and year whose recap was announced (in-app + channels) at the start of the new year: the unique
 * index makes overlapping scheduler runs (or a manual run of the command) send it at most once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_year_recap_notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('member_count')->default(0);
            $table->unsignedInteger('channel_count')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['room_id', 'year'], 'room_year_recap_notifications_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_year_recap_notifications');
    }
};
