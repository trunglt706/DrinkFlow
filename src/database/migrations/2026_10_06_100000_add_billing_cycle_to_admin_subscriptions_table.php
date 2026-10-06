<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Billing cycle of a subscription and its renewal reminder marker.
     *
     * - `period_months`: length of one billing period (an Agent registers for N months or N years; renewals
     *   reuse it). Existing subscriptions keep the former fixed 1-month period.
     * - `renewal_reminder_sent_for`: period end the renewal reminder was sent for, so the daily job sends
     *   it once per period.
     *
     * A self-registered Agent's subscription now starts `pending` (awaiting payment of its first invoice):
     * `status` is a plain string column, so the new value needs no schema change.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('admin_subscriptions', function (Blueprint $table): void {
            $table->unsignedSmallInteger('period_months')->default(1)->after('price_snapshot');
            $table->timestamp('renewal_reminder_sent_for')->nullable()->after('expires_at');
        });
    }

    /**
     * @return void
     */
    public function down(): void
    {
        Schema::table('admin_subscriptions', function (Blueprint $table): void {
            $table->dropColumn(['period_months', 'renewal_reminder_sent_for']);
        });
    }
};
