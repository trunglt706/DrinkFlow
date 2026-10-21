<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the member sent the comment of an item rating: from then on the rating (stars and comment) is final.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_item_ratings', function (Blueprint $table): void {
            $table->timestamp('commented_at')->nullable()->after('comment');
        });
    }

    public function down(): void
    {
        Schema::table('order_item_ratings', function (Blueprint $table): void {
            $table->dropColumn('commented_at');
        });
    }
};
