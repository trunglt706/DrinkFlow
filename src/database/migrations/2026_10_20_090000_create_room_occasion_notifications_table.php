<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per room, occasion (App\Enums\RoomOccasion) and year whose greeting was sent: the unique index makes
 * overlapping scheduler runs (or a manual run of the command) send it at most once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_occasion_notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->string('occasion', 40);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('channel_count')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['room_id', 'occasion', 'year'], 'room_occasion_notifications_room_year_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_occasion_notifications');
    }
};
