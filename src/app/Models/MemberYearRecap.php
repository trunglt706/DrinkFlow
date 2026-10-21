<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Precomputed year recap of a room member (see App\Services\Reporting\YearRecapService).
 */
class MemberYearRecap extends Model
{
    protected $fillable = ['room_id', 'room_user_id', 'year', 'data'];

    protected function casts(): array
    {
        return ['year' => 'integer', 'data' => 'array'];
    }
}
