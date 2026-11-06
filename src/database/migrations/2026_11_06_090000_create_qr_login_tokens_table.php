<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One-time QR codes a signed-in member shows on /me/devices to sign in on a phone without Google. Only the SHA-256
 * hash of each code is stored; a code is valid for a few seconds and is consumed by the first successful scan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qr_login_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('global_user_id')->constrained('global_users')->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at')->index();
            $table->timestamp('used_at')->nullable();
            $table->string('created_ip', 45)->nullable();
            $table->string('used_ip', 45)->nullable();
            $table->timestamps();
            $table->index(['global_user_id', 'used_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_login_tokens');
    }
};
