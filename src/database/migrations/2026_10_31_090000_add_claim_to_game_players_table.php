<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A player picked on the public link (/play/{token}) is held by that visitor: `claimed_by` is the random claim
 * token kept in the visitor's session, `claimed_at` the last activity (an idle claim expires with the session).
 * Other visitors cannot pick a held player, and the unique index keeps one visitor to one player per room (MySQL
 * allows several NULLs, so unclaimed players are not affected).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_players', function (Blueprint $table): void {
            $table->string('claimed_by', 64)->nullable()->after('sort_order');
            $table->timestamp('claimed_at')->nullable()->after('claimed_by');
            $table->unique(['game_room_id', 'claimed_by']);
        });
    }

    public function down(): void
    {
        Schema::table('game_players', function (Blueprint $table): void {
            $table->dropUnique(['game_room_id', 'claimed_by']);
            $table->dropColumn(['claimed_by', 'claimed_at']);
        });
    }
};
