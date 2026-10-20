<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Automatic bank reconciliation of room payments.
 *
 * room_bank_integrations: one bank-feed provider (SePay) per room, reached through a secret webhook URL token and
 * authenticated with the room's own API key (stored encrypted).
 * bank_transactions: every incoming notification, unique per provider transaction ID so a retried webhook is
 * recorded (and settles a debt) only once; matched to a debt, a payment request, or left for the admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_bank_integrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('room_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('provider', 20);
            $table->string('webhook_token', 64)->unique();
            $table->text('api_key')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamp('last_received_at')->nullable();
            $table->timestamps();
        });

        Schema::create('bank_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 20);
            $table->string('provider_transaction_id', 100);
            $table->string('account_number', 50)->nullable();
            $table->string('direction', 10);
            $table->unsignedBigInteger('amount');
            $table->string('content', 500)->nullable();
            $table->string('reference_code', 100)->nullable();
            $table->timestamp('transacted_at')->nullable();
            $table->string('status', 20);
            $table->string('reason', 50)->nullable();
            $table->foreignId('debt_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_request_id')->nullable()->constrained('debts')->nullOnDelete();
            $table->foreignId('room_user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('resolved_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'provider_transaction_id'], 'bank_transactions_provider_txn_unique');
            $table->index(['room_id', 'status', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transactions');
        Schema::dropIfExists('room_bank_integrations');
    }
};
