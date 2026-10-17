<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Self-description of a member (Markdown, at most 300 words), edited on /me/profile.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('global_users', function (Blueprint $table): void {
            $table->text('bio')->nullable()->after('delivery_location');
        });
    }

    public function down(): void
    {
        Schema::table('global_users', function (Blueprint $table): void {
            $table->dropColumn('bio');
        });
    }
};
