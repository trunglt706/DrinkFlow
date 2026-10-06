<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sponsor draws become typed and start as drafts:
 * - `type`: sponsors (room members) or shops (names chosen by the admin);
 * - a draft has no source campaign, eligible snapshot, seed or link yet, so those columns become nullable;
 * - shop candidates / results have no room member, so `room_user_id` becomes nullable there.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sponsor_draw_sessions', function (Blueprint $table): void {
            $table->string('type', 20)->default('sponsors')->after('room_id');
            $table->unsignedBigInteger('campaign_id')->nullable()->change();
            $table->unsignedSmallInteger('target_winner_count')->default(1)->change();
            $table->timestamp('eligibility_snapshot_at')->nullable()->change();
            $table->unsignedInteger('eligible_count')->default(0)->change();
            $table->char('snapshot_hash', 64)->nullable()->change();
            $table->text('seed_encrypted')->nullable()->change();
            $table->char('seed_commitment', 64)->nullable()->change();
            $table->string('random_method', 50)->nullable()->change();
            $table->index(['room_id', 'type']);
        });
        Schema::table('sponsor_draw_eligible_users', function (Blueprint $table): void {
            $table->unsignedBigInteger('room_user_id')->nullable()->change();
        });
        Schema::table('sponsor_draw_results', function (Blueprint $table): void {
            $table->unsignedBigInteger('room_user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Drafts and shop draws cannot exist in the previous schema.
        DB::table('sponsor_draw_sessions')->where('type', 'shops')->orWhere('status', 'draft')->orWhereNull('campaign_id')->delete();

        Schema::table('sponsor_draw_results', function (Blueprint $table): void {
            $table->unsignedBigInteger('room_user_id')->nullable(false)->change();
        });
        Schema::table('sponsor_draw_eligible_users', function (Blueprint $table): void {
            $table->unsignedBigInteger('room_user_id')->nullable(false)->change();
        });
        Schema::table('sponsor_draw_sessions', function (Blueprint $table): void {
            $table->dropIndex(['room_id', 'type']);
            $table->dropColumn('type');
            $table->unsignedBigInteger('campaign_id')->nullable(false)->change();
            $table->unsignedSmallInteger('target_winner_count')->default(null)->change();
            $table->timestamp('eligibility_snapshot_at')->nullable(false)->change();
            $table->unsignedInteger('eligible_count')->default(null)->change();
            $table->char('snapshot_hash', 64)->nullable(false)->change();
            $table->text('seed_encrypted')->nullable(false)->change();
            $table->char('seed_commitment', 64)->nullable(false)->change();
            $table->string('random_method', 50)->nullable(false)->change();
        });
    }
};
