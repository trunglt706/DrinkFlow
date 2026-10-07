<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

class AdminRoomsUpdated implements ShouldDispatchAfterCommit
{
    public function __construct(public readonly int $adminId, public readonly array $roomIds)
    {
    }
}
