<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Enums\GlobalUserStatus;
use App\Models\GlobalUser;
use App\Models\QrLoginToken;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * QR sign-in on a phone: a member signed in on a computer shows a QR code (a new one every
 * {@see self::ROTATE_SECONDS} seconds); scanning it on the phone opens a one-time link that signs the phone in to
 * the same account. Only the SHA-256 hash of a code is stored, a code lives {@see self::TTL_SECONDS} seconds and the
 * first successful scan consumes it atomically, so a code can never sign in two devices.
 */
class QrLoginService
{
    /** How often the computer shows a new code. */
    public const ROTATE_SECONDS = 10;

    /** How long a code stays valid: one rotation plus a margin to scan it and open the link. */
    public const TTL_SECONDS = 20;

    /** Length of the random code put in the link. */
    private const TOKEN_LENGTH = 48;

    /**
     * Issue a new code for the member (older unused codes of the member are revoked).
     *
     * @param GlobalUser $user Member signed in on the computer.
     * @param string|null $ip IP address of the computer.
     * @return array{token: string, expires_at: Carbon} Plain code (shown once, never stored) and its expiry.
     */
    public function issue(GlobalUser $user, ?string $ip): array
    {
        $token = Str::random(self::TOKEN_LENGTH);
        $expiresAt = now()->addSeconds(self::TTL_SECONDS);

        // Keep only the newest code of the member and drop long-expired codes of everyone.
        QrLoginToken::query()->where('global_user_id', $user->id)->whereNull('used_at')->delete();
        QrLoginToken::query()->where('expires_at', '<', now()->subDay())->delete();

        QrLoginToken::query()->create([
            'global_user_id' => $user->id,
            'token_hash' => $this->hash($token),
            'expires_at' => $expiresAt,
            'created_ip' => $ip,
        ]);

        return ['token' => $token, 'expires_at' => $expiresAt];
    }

    /**
     * Consume a scanned code: the member it belongs to, or null when the code is unknown, expired, already used or
     * the account can no longer sign in.
     *
     * @param string $token Plain code from the link.
     * @param string|null $ip IP address of the phone.
     * @return GlobalUser|null Member to sign in.
     */
    public function consume(string $token, ?string $ip): ?GlobalUser
    {
        if (strlen($token) !== self::TOKEN_LENGTH || ! ctype_alnum($token)) {
            return null;
        }

        $record = QrLoginToken::query()->where('token_hash', $this->hash($token))->first();
        if ($record === null) {
            return null;
        }

        // Atomic: only one request can flip used_at, even when the link is opened twice at the same time.
        $claimed = QrLoginToken::query()
            ->whereKey($record->id)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->update(['used_at' => now(), 'used_ip' => $ip]);
        if ($claimed !== 1) {
            return null;
        }

        $user = GlobalUser::query()->find($record->global_user_id);
        $status = $user?->status instanceof GlobalUserStatus ? $user->status : GlobalUserStatus::tryFrom((string) $user?->status);

        return $status === GlobalUserStatus::Active ? $user : null;
    }

    /**
     * @param string $token Plain code.
     * @return string SHA-256 hash stored in the database.
     */
    private function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
