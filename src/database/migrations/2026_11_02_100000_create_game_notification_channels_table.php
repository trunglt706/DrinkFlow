<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Outbound notification channels (Slack, Telegram, Chatwork, webhook) owned by a global user for the Utilities area.
 * A game room picks one of its owner's channels, like it picks a rule set; the final leaderboard is sent there when
 * the room finishes and can be sent again later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_notification_channels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_id')->constrained('global_users')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('type', 20);
            $table->text('config_encrypted');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['owner_id', 'is_active']);
        });

        Schema::table('game_rooms', function (Blueprint $table): void {
            $table->foreignId('game_notification_channel_id')->nullable()->after('game_rule_version_id')
                ->constrained('game_notification_channels')->nullOnDelete();
            $table->timestamp('results_notified_at')->nullable()->after('finished_at');
        });
    }

    public function down(): void
    {
        Schema::table('game_rooms', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('game_notification_channel_id');
            $table->dropColumn('results_notified_at');
        });
        Schema::dropIfExists('game_notification_channels');
    }
};
