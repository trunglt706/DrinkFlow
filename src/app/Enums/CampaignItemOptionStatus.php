<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Status of a campaign item size or topping. Deleting an option only hides it, so orders that already
 * reference it keep their history; members never see or order a hidden option.
 */
enum CampaignItemOptionStatus: string
{
    case Active = 'active';
    case Hidden = 'hidden';
}
