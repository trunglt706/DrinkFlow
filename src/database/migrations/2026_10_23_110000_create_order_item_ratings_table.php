<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ratings (1–5 stars + short comment) a member gives to the items of their own orders once received.
 *
 * One rating per order item and member (unique index; rating again updates it). `restaurant_key` and `item_key` are
 * the normalized restaurant and item names, so the average of a dish follows it from one campaign to the next.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_item_ratings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_user_id')->constrained('room_users')->cascadeOnDelete();
            $table->string('restaurant_key', 191);
            $table->string('item_key', 191);
            $table->unsignedTinyInteger('stars');
            $table->string('comment', 200)->nullable();
            $table->timestamps();
            $table->unique(['order_item_id', 'room_user_id'], 'order_item_ratings_item_user_unique');
            $table->index(['room_id', 'restaurant_key', 'item_key'], 'order_item_ratings_dish_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_ratings');
    }
};
