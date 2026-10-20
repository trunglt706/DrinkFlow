<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-room settings of each event page (`birthday` or an App\Enums\RoomOccasion value): background music and sound
 * effects. A missing row means the defaults (built-in melody, sound effects on).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_event_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 40);
            $table->string('music_mode', 20)->default('builtin');
            $table->string('music_url', 2048)->nullable();
            $table->boolean('sound_effects')->default(true);
            $table->timestamps();
            $table->unique(['room_id', 'event_type'], 'room_event_settings_room_event_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_event_settings');
    }
};
