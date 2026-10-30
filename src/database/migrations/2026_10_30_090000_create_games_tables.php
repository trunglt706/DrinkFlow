<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mini games of the /me portal ("Trò chơi").
 *
 * - `games`: catalogue of games (seeded by GameSeeder).
 * - `game_rule_sets` / `game_rule_versions` / `game_rule_items`: an owner's scoring rules. Every edit writes a new
 *   version with its own items, so a version never changes once created.
 * - `game_rooms`: a play session owned by a global user; it points at the rule version it applies.
 * - `game_players`: stable player identities of a room (display name may change, the id never does).
 * - `game_rounds` / `game_results` / `game_result_losers`: results grouped by round. A result keeps a snapshot of the
 *   rule item (name, points, version) and is never deleted: it is voided with a reason.
 * - `game_score_transactions`: points ledger; a void adds a reversal transaction, the original is kept.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 100)->unique();
            $table->string('name', 150);
            $table->string('description', 500)->nullable();
            $table->string('icon', 64)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('game_rule_sets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('global_users')->cascadeOnDelete();
            $table->string('name', 100);
            $table->boolean('allow_negative_points')->default(false);
            // Latest version; nullable only while the set and its first version are created in one transaction.
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->timestamps();
            $table->index(['owner_id', 'game_id']);
        });

        Schema::create('game_rule_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('game_rule_set_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->timestamp('created_at')->nullable();
            $table->unique(['game_rule_set_id', 'version']);
        });

        Schema::table('game_rule_sets', function (Blueprint $table): void {
            $table->foreign('current_version_id')->references('id')->on('game_rule_versions')->nullOnDelete();
        });

        Schema::create('game_rule_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('game_rule_version_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->integer('points');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->index(['game_rule_version_id', 'sort_order']);
        });

        Schema::create('game_rooms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('global_users')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('status', 20)->default('waiting');
            $table->foreignId('game_rule_set_id')->constrained()->restrictOnDelete();
            $table->foreignId('game_rule_version_id')->constrained()->restrictOnDelete();
            // Capability token of the public link; null while the link is revoked.
            $table->string('public_token', 64)->nullable()->unique();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
            $table->index(['owner_id', 'game_id', 'status']);
        });

        Schema::create('game_players', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('game_room_id')->constrained()->cascadeOnDelete();
            $table->string('display_name', 50);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['game_room_id', 'sort_order']);
        });

        Schema::create('game_rounds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('game_room_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->timestamp('created_at')->nullable();
            $table->unique(['game_room_id', 'number']);
        });

        Schema::create('game_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('game_room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('game_round_id')->constrained()->cascadeOnDelete();
            $table->foreignId('winner_player_id')->constrained('game_players')->restrictOnDelete();
            $table->foreignId('game_rule_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('game_rule_version_id')->nullable()->constrained()->nullOnDelete();
            // Snapshot of the rule item when the result was recorded: a later rule change never alters it.
            $table->string('rule_item_name', 100);
            $table->integer('points');
            $table->unsignedInteger('rule_version_number');
            $table->string('idempotency_key', 64);
            $table->string('status', 20)->default('valid');
            $table->foreignId('recorded_by')->nullable()->constrained('global_users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('global_users')->nullOnDelete();
            $table->string('void_reason', 255)->nullable();
            $table->timestamps();
            $table->unique(['game_room_id', 'idempotency_key']);
            $table->index(['game_room_id', 'status']);
        });

        Schema::create('game_result_losers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('game_result_id')->constrained()->cascadeOnDelete();
            $table->foreignId('game_player_id')->constrained()->restrictOnDelete();
            $table->unique(['game_result_id', 'game_player_id']);
            $table->index('game_player_id');
        });

        Schema::create('game_score_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('game_room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('game_result_id')->constrained()->cascadeOnDelete();
            $table->foreignId('game_player_id')->constrained()->restrictOnDelete();
            $table->string('type', 20);
            $table->integer('points');
            $table->foreignId('reverses_transaction_id')->nullable()->constrained('game_score_transactions')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->index(['game_room_id', 'game_player_id']);
            // At most one reversal per original transaction.
            $table->unique('reverses_transaction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_score_transactions');
        Schema::dropIfExists('game_result_losers');
        Schema::dropIfExists('game_results');
        Schema::dropIfExists('game_rounds');
        Schema::dropIfExists('game_players');
        Schema::dropIfExists('game_rooms');
        Schema::dropIfExists('game_rule_items');
        Schema::table('game_rule_sets', function (Blueprint $table): void {
            $table->dropForeign(['current_version_id']);
        });
        Schema::dropIfExists('game_rule_versions');
        Schema::dropIfExists('game_rule_sets');
        Schema::dropIfExists('games');
    }
};
