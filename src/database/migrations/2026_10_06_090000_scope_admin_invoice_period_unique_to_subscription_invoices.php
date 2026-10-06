<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Limit the "one invoice per subscription period" guarantee to periodic (subscription) invoices.
     *
     * Upgrade invoices are attached to the current subscription with period_start = request time, so the old
     * unique (admin_subscription_id, period_start) rejected a re-submitted upgrade request (the voided request
     * keeps its row) and an upgrade requested in the same second as the period invoice. The generated column
     * holds admin_subscription_id only for subscription invoices (NULL otherwise, and NULLs never collide),
     * so invoice generation stays idempotent at database level while upgrade invoices are no longer blocked.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('admin_invoices', function (Blueprint $table): void {
            // MySQL backs the admin_subscription_id foreign key with the unique index: keep an index for it first.
            $table->index('admin_subscription_id');
        });

        Schema::table('admin_invoices', function (Blueprint $table): void {
            $table->dropUnique(['admin_subscription_id', 'period_start']);
            $table->unsignedBigInteger('period_subscription_id')->nullable()
                ->storedAs("CASE WHEN type = 'subscription' THEN admin_subscription_id ELSE NULL END");
            $table->unique(['period_subscription_id', 'period_start']);
        });
    }

    /**
     * Restore the original unique index (fails if upgrade invoices now share a subscription period).
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('admin_invoices', function (Blueprint $table): void {
            $table->dropUnique(['period_subscription_id', 'period_start']);
            $table->dropColumn('period_subscription_id');
            $table->unique(['admin_subscription_id', 'period_start']);
        });

        Schema::table('admin_invoices', function (Blueprint $table): void {
            $table->dropIndex(['admin_subscription_id']);
        });
    }
};
