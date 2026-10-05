<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            // 1. Orders: Active order uniqueness per campaign and room user
            if (! Schema::hasColumn('orders', 'active_order_key')) {
                DB::statement("
                    ALTER TABLE orders
                    ADD COLUMN active_order_key BIGINT UNSIGNED
                    GENERATED ALWAYS AS (
                        CASE WHEN status IN ('submitted', 'confirmed', 'ordering', 'ordered', 'delivering') THEN room_user_id ELSE NULL END
                    ) VIRTUAL NULL AFTER room_user_id
                ");
                DB::statement('CREATE UNIQUE INDEX orders_campaign_active_user_unique ON orders (campaign_id, active_order_key)');
            }

            // 2. Payment accounts: Only one default payment account per room
            if (! Schema::hasColumn('payment_accounts', 'default_room_key')) {
                DB::statement("
                    ALTER TABLE payment_accounts
                    ADD COLUMN default_room_key BIGINT UNSIGNED
                    GENERATED ALWAYS AS (
                        CASE WHEN is_default = 1 THEN room_id ELSE NULL END
                    ) VIRTUAL NULL AFTER room_id
                ");
                DB::statement('CREATE UNIQUE INDEX payment_accounts_room_default_unique ON payment_accounts (default_room_key)');
            }

            // 3. Orders: Composite index for (campaign_id, room_user_id, status)
            DB::statement('CREATE INDEX orders_campaign_user_status_index ON orders (campaign_id, room_user_id, status)');
        } elseif (in_array($driver, ['sqlite', 'pgsql'], true)) {
            Schema::table('orders', function (Blueprint $table) {
                $table->index(['campaign_id', 'room_user_id', 'status'], 'orders_campaign_user_status_index');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('DROP INDEX IF EXISTS orders_campaign_user_status_index ON orders');
            DB::statement('DROP INDEX IF EXISTS orders_campaign_active_user_unique ON orders');
            if (Schema::hasColumn('orders', 'active_order_key')) {
                DB::statement('ALTER TABLE orders DROP COLUMN active_order_key');
            }

            DB::statement('DROP INDEX IF EXISTS payment_accounts_room_default_unique ON payment_accounts');
            if (Schema::hasColumn('payment_accounts', 'default_room_key')) {
                DB::statement('ALTER TABLE payment_accounts DROP COLUMN default_room_key');
            }
        } elseif (in_array($driver, ['sqlite', 'pgsql'], true)) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropIndex('orders_campaign_user_status_index');
            });
        }
    }
};
