<?php

declare(strict_types=1);

namespace App\Enums;

enum RoomUserStatus: string
{
    case Active = 'active';
    case Blocked = 'blocked';
    case Removed = 'removed';
}
