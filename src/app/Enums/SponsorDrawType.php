<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a draw session picks: sponsors among the room's members, or one shop among a list chosen by the admin.
 */
enum SponsorDrawType: string
{
    case Sponsors = 'sponsors';
    case Shops = 'shops';
}
