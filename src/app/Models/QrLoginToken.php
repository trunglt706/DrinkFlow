<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One-time QR sign-in code of a member (only its hash is stored).
 *
 * @property int $id
 * @property int $global_user_id
 * @property string $token_hash
 * @property \Illuminate\Support\Carbon $expires_at
 * @property \Illuminate\Support\Carbon|null $used_at
 * @property string|null $created_ip
 * @property string|null $used_ip
 */
class QrLoginToken extends Model
{
    protected $fillable = ['global_user_id', 'token_hash', 'expires_at', 'used_at', 'created_ip', 'used_ip'];

    protected $hidden = ['token_hash'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    /**
     * Member the code signs in.
     *
     * @return BelongsTo<GlobalUser, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(GlobalUser::class, 'global_user_id');
    }
}
