<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audio file uploaded by the room admin as the background music of an event page (music_mode `upload`): its path on
 * the public disk (random file name) and the original file name shown on the admin events page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_event_settings', function (Blueprint $table): void {
            $table->string('music_path')->nullable()->after('music_url');
            $table->string('music_name', 191)->nullable()->after('music_path');
        });
    }

    public function down(): void
    {
        Schema::table('room_event_settings', function (Blueprint $table): void {
            $table->dropColumn(['music_path', 'music_name']);
        });
    }
};
