<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class SystemNotificationChannel extends Model
{
    /** Supported channel types => product name shown in forms and filters (brand names, not translated). */
    public const TYPES = ['slack' => 'Slack', 'telegram' => 'Telegram', 'chatwork' => 'Chatwork', 'webhook' => 'Webhook'];

    protected $fillable = ['type', 'name', 'config_encrypted', 'status'];
    protected $hidden = ['config_encrypted'];
    protected $appends = ['configured'];

    protected function configEncrypted(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value,
            set: fn ($value) => encrypt($value),
        );
    }

    protected function configured(): Attribute
    {
        return Attribute::get(fn () => filled($this->config_encrypted));
    }
}
