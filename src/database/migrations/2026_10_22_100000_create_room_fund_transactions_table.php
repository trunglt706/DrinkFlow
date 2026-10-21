<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Room fund ledger: append-only entries (never updated or deleted). Each entry stores the balance after it, written
 * while the room row is locked, so the latest entry is the current balance. A debt payment made with the fund has
 * exactly one entry (unique debt_payment_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_fund_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->bigInteger('amount');
            $table->bigInteger('balance_after');
            $table->foreignId('room_user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('debt_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('debt_payment_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
            $table->index(['room_id', 'type', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_fund_transactions');
    }
};
