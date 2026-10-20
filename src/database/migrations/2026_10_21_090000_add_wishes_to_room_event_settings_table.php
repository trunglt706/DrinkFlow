<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wishes of an event page chosen by the room admin (list of strings). Null means the default sample wishes of the
 * event (lang/{locale}/occasions.php, lang/{locale}/birthday.php), shown in the viewer's language.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_event_settings', function (Blueprint $table): void {
            $table->json('wishes')->nullable()->after('sound_effects');
        });
    }

    public function down(): void
    {
        Schema::table('room_event_settings', function (Blueprint $table): void {
            $table->dropColumn('wishes');
        });
    }
};
