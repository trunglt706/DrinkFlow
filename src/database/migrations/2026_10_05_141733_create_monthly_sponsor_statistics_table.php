<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('monthly_sponsor_statistics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_user_id')->constrained()->cascadeOnDelete();
            $table->date('month');
            $table->unsignedBigInteger('total_sponsored')->default(0);
            $table->unsignedInteger('sponsored_campaigns')->default(0);
            $table->timestamps();
            $table->unique(['room_id', 'month', 'room_user_id'], 'monthly_sponsors_identity_unique');
            $table->index(['room_id', 'month', 'total_sponsored'], 'monthly_sponsors_ranking_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monthly_sponsor_statistics');
    }
};
