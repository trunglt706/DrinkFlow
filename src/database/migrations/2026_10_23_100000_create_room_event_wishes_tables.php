<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wishes wall of the room event pages: wishes written by the members (to a celebrant on the birthday page), the
 * hearts given to them (one per member and wish, guarded by a unique index) and the room admin's moderation.
 *
 * `occurrence` is the date of the celebrated occurrence (the occasion's day, or the celebrant's birthday that
 * year), so every year starts with an empty wall.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_event_wishes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 32);
            $table->date('occurrence');
            $table->foreignId('room_user_id')->constrained('room_users')->cascadeOnDelete();
            $table->foreignId('recipient_room_user_id')->nullable()->constrained('room_users')->cascadeOnDelete();
            $table->string('message', 500);
            $table->unsignedInteger('hearts_count')->default(0);
            $table->timestamp('hidden_at')->nullable();
            $table->foreignId('hidden_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
            $table->index(['room_id', 'event_type', 'occurrence']);
            $table->index(['room_user_id', 'event_type', 'occurrence']);
        });

        Schema::create('room_event_wish_hearts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('room_event_wish_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_user_id')->constrained('room_users')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->unique(['room_event_wish_id', 'room_user_id'], 'room_event_wish_hearts_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_event_wish_hearts');
        Schema::dropIfExists('room_event_wishes');
    }
};
