<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sponsor draw sessions of a campaign: frozen eligibility snapshot, results, per-channel notification
     * delivery and an append-only, hash-chained event log (also the realtime replay source).
     *
     * Snapshot / result rows keep the room_user_id without a foreign key on purpose: a member removed from
     * the room later must not erase or block the historical record of a draw.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('sponsor_draw_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->foreignId('target_campaign_id')->nullable()->constrained('campaigns')->nullOnDelete();
            $table->unsignedSmallInteger('target_winner_count');
            $table->string('status', 30);
            // Link: SHA-256 of the random token for lookups, plus the encrypted token so admins can copy it again.
            $table->char('access_token_hash', 64)->nullable()->unique();
            $table->text('access_token_encrypted')->nullable();
            $table->timestamp('link_expires_at')->nullable();
            // Fairness: frozen snapshot hash, seed commitment (seed revealed only after the draw) and method.
            $table->timestamp('eligibility_snapshot_at');
            $table->unsignedInteger('eligible_count');
            $table->char('snapshot_hash', 64);
            $table->text('seed_encrypted');
            $table->char('seed_commitment', 64);
            $table->string('random_method', 50);
            $table->timestamp('seed_revealed_at')->nullable();
            $table->unsignedInteger('last_sequence')->default(0);
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('confirmed_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('cancelled_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->json('sponsor_assignment')->nullable();
            $table->timestamps();

            $table->index(['room_id', 'status']);
            $table->index(['campaign_id', 'created_at']);
        });

        Schema::create('sponsor_draw_eligible_users', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sponsor_draw_session_id')->constrained('sponsor_draw_sessions')->cascadeOnDelete();
            $table->unsignedBigInteger('room_user_id');
            // Stable position in the snapshot (sorted by room_user_id); the draw algorithm shuffles positions.
            $table->unsignedInteger('position');
            $table->string('display_name_snapshot');
            $table->boolean('is_winner')->default(false);
            $table->timestamps();

            $table->unique(['sponsor_draw_session_id', 'room_user_id'], 'sponsor_draw_eligible_session_user_unique');
            $table->unique(['sponsor_draw_session_id', 'position'], 'sponsor_draw_eligible_session_position_unique');
        });

        Schema::create('sponsor_draw_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sponsor_draw_session_id')->constrained('sponsor_draw_sessions')->cascadeOnDelete();
            $table->unsignedBigInteger('room_user_id');
            $table->unsignedInteger('snapshot_position');
            $table->string('display_name_snapshot');
            $table->unsignedSmallInteger('draw_order');
            $table->foreignId('drawn_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('drawn_at');
            $table->timestamps();

            // One win per member and one result per turn: the database itself refuses duplicates.
            $table->unique(['sponsor_draw_session_id', 'room_user_id'], 'sponsor_draw_results_session_user_unique');
            $table->unique(['sponsor_draw_session_id', 'draw_order'], 'sponsor_draw_results_session_order_unique');
        });

        Schema::create('sponsor_draw_notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sponsor_draw_session_id')->constrained('sponsor_draw_sessions')->cascadeOnDelete();
            $table->unsignedBigInteger('notification_channel_id');
            $table->string('channel_type', 30);
            $table->string('channel_name')->nullable();
            $table->string('status', 20);
            $table->json('payload_snapshot');
            $table->string('provider_reference')->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            // Idempotency: one delivery record per channel and session; a resend reuses it.
            $table->unique(['sponsor_draw_session_id', 'notification_channel_id'], 'sponsor_draw_notifications_session_channel_unique');
        });

        Schema::create('sponsor_draw_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sponsor_draw_session_id')->constrained('sponsor_draw_sessions')->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->uuid('event_uuid')->unique();
            $table->string('type', 40);
            $table->json('payload');
            $table->foreignId('actor_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->char('previous_hash', 64)->nullable();
            $table->char('event_hash', 64);
            $table->timestamp('created_at');

            $table->unique(['sponsor_draw_session_id', 'sequence'], 'sponsor_draw_events_session_sequence_unique');
        });
    }

    /**
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('sponsor_draw_events');
        Schema::dropIfExists('sponsor_draw_notifications');
        Schema::dropIfExists('sponsor_draw_results');
        Schema::dropIfExists('sponsor_draw_eligible_users');
        Schema::dropIfExists('sponsor_draw_sessions');
    }
};
