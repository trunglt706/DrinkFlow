<?php

declare(strict_types=1);

namespace App\Actions\Room;

use App\Enums\PaymentAccountStatus;
use App\Models\PaymentAccount;
use App\Models\Room;
use App\Models\RoomSetting;
use App\Services\Audit\AuditService;
use App\Services\Debt\DebtAutoReminderService;
use App\Services\Room\RoomAccessPolicy;
use Illuminate\Support\Facades\DB;

class UpdateRoomSettingsAction
{
    public function __construct(protected AuditService $audit)
    {
    }

    /**
     * Update room core settings and room_settings key-values.
     */
    public function execute(Room $room, array $data, array $before): array
    {
        if (array_key_exists('default_payment_account_id', $data) && $data['default_payment_account_id'] !== null) {
            abort_unless(
                PaymentAccount::query()->whereKey($data['default_payment_account_id'])->where('room_id', $room->id)->where('status', PaymentAccountStatus::Active)->exists(),
                422,
                __('admin.invalid_payment_account')
            );
        }

        $dynamicKeys = [
            'default_sponsor' => RoomSetting::TYPE_STRING,
            'default_payment_account_id' => RoomSetting::TYPE_INTEGER,
            'campaign_title_template' => RoomSetting::TYPE_STRING,
            'max_campaign_budget' => RoomSetting::TYPE_INTEGER,
            'personal_debt_ceiling' => RoomSetting::TYPE_INTEGER,
            'auto_lock_on_debt_limit' => RoomSetting::TYPE_BOOLEAN,
            DebtAutoReminderService::SETTING_KEY => RoomSetting::TYPE_BOOLEAN,
            'is_public' => RoomSetting::TYPE_BOOLEAN,
        ];

        $updated = DB::transaction(function () use ($room, $data, $dynamicKeys): Room {
            $room->update(collect($data)->only(['name', 'description', 'avatar_url', 'timezone', 'language'])->all());

            // Access-rule lists (email domains / IPs) are stored as normalized JSON arrays; empty = no restriction.
            foreach (RoomAccessPolicy::LIST_KEYS as $key) {
                if (array_key_exists($key, $data)) {
                    $values = (array) ($data[$key] ?? []);
                    $list = $key === RoomAccessPolicy::ALLOWED_EMAIL_DOMAINS
                        ? RoomAccessPolicy::normalizeDomains($values)
                        : RoomAccessPolicy::normalizeIps($values);
                    RoomSetting::updateOrCreate(
                        ['room_id' => $room->id, 'key' => $key],
                        ['value' => json_encode($list), 'type' => RoomSetting::TYPE_JSON, 'is_secret' => false]
                    );
                }
            }
            app(RoomAccessPolicy::class)->forget($room);

            foreach ($dynamicKeys as $key => $type) {
                if (array_key_exists($key, $data)) {
                    $rawVal = $data[$key];
                    $val = $rawVal === null ? null : (string) $rawVal;
                    if ($type === RoomSetting::TYPE_BOOLEAN && $rawVal !== null) {
                        $val = $rawVal ? '1' : '0';
                    }
                    RoomSetting::updateOrCreate(
                        ['room_id' => $room->id, 'key' => $key],
                        ['value' => $val, 'type' => $type, 'is_secret' => false]
                    );
                }
            }

            return $room->fresh();
        });

        $after = $this->payload($updated);
        $this->audit->record('room.settings_updated', 'room', $room->id, $room->id, $before, $after);

        return $after;
    }

    /**
     * Format payload.
     */
    public function payload(Room $room): array
    {
        $settings = $room->roomSettings()->get()->keyBy('key');

        $extra = [];
        foreach ($settings as $key => $setting) {
            $val = $setting->value;
            if ($setting->type === RoomSetting::TYPE_INTEGER) {
                $val = $val !== null ? (int) $val : null;
            } elseif ($setting->type === RoomSetting::TYPE_BOOLEAN) {
                $val = filter_var($val, FILTER_VALIDATE_BOOLEAN);
            } elseif ($setting->type === RoomSetting::TYPE_JSON) {
                $decoded = json_decode((string) $val, true);
                $val = is_array($decoded) ? array_values($decoded) : [];
            }
            $extra[$key] = $val;
        }

        return array_merge($room->only(['id', 'name', 'slug', 'description', 'avatar_url', 'timezone', 'language', 'status']), $extra, [
            'default_sponsor' => $settings->get('default_sponsor')?->value,
            'default_payment_account_id' => ($settings->get('default_payment_account_id')?->value !== null ? (int) $settings->get('default_payment_account_id')->value : null),
            'campaign_title_template' => $settings->get('campaign_title_template')?->value ?? ('['.$room->name.'] Trà chiều & Cafe {date}'),
            'max_campaign_budget' => $settings->get('max_campaign_budget')?->value !== null ? (int) $settings->get('max_campaign_budget')->value : 70000,
            'personal_debt_ceiling' => $settings->get('personal_debt_ceiling')?->value !== null ? (int) $settings->get('personal_debt_ceiling')->value : 150000,
            'auto_lock_on_debt_limit' => $settings->get('auto_lock_on_debt_limit')?->value !== null ? filter_var($settings->get('auto_lock_on_debt_limit')->value, FILTER_VALIDATE_BOOLEAN) : true,
            DebtAutoReminderService::SETTING_KEY => $settings->get(DebtAutoReminderService::SETTING_KEY)?->value !== null ? filter_var($settings->get(DebtAutoReminderService::SETTING_KEY)->value, FILTER_VALIDATE_BOOLEAN) : true,
            'is_public' => $settings->get('is_public')?->value !== null ? filter_var($settings->get('is_public')->value, FILTER_VALIDATE_BOOLEAN) : true,
        ], array_fill_keys(array_diff(RoomAccessPolicy::LIST_KEYS, array_keys($extra)), []));
    }
}
