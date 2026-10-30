<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Results may also be recorded from the public link (/play/{token}) by the player the visitor picked: keep which
 * player recorded it (recorded_by stays null, no account is signed in there).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_results', function (Blueprint $table): void {
            $table->foreignId('recorded_by_player_id')->nullable()->after('recorded_by')->constrained('game_players')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('game_results', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('recorded_by_player_id');
        });
    }
};
