<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional birthday of a member: drives the birthday banner of the rooms and the celebration page.
 * Only the day and month are ever shown to other members.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('global_users', function (Blueprint $table): void {
            $table->date('birthday')->nullable()->after('delivery_location');
        });
    }

    public function down(): void
    {
        Schema::table('global_users', function (Blueprint $table): void {
            $table->dropColumn('birthday');
        });
    }
};
