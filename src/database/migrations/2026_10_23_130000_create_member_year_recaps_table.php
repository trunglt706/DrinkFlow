<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Year recap of each member of a room (cups, favourite dish and shop, spending, sponsorship, medals...), computed in
 * advance by drinkflow:build-year-recaps so the recap page reads one row. One row per room, member and year.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_year_recaps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_user_id')->constrained('room_users')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->json('data');
            $table->timestamps();
            $table->unique(['room_id', 'room_user_id', 'year'], 'member_year_recaps_unique');
            $table->index(['room_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_year_recaps');
    }
};
