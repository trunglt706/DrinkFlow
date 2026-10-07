<?php

declare(strict_types=1);

namespace App\Enums;

enum GlobalUserStatus: string
{
    case Active = 'active';
    case Blocked = 'blocked';
    case Disabled = 'disabled';
    /** Soft-deleted by a superadmin: the row (and its order/debt history) is kept, sign-in is refused. */
    case Deleted = 'deleted';
}
