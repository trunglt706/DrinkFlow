<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\DebtStatus;
use App\Enums\OrderStatus;
use App\Enums\RoomUserStatus;
use App\Models\Room;
use App\Models\RoomUser;
use App\Models\RoomUserDevice;

class AdminRoomUserService
{
    /**
     * Get member counts and summary metrics for Room Users directory view.
     *
     * @param Room $room Room entity.
     * @return array<string, int>
     */
    public function getDirectoryMetrics(Room $room): array
    {
        return [
            'totalMembers' => $room->roomUsers()->count(),
            'activeMembers' => $room->roomUsers()->where('status', RoomUserStatus::Active->value)->count(),
            'blockedMembers' => $room->roomUsers()->where('status', RoomUserStatus::Blocked->value)->count(),
            'debtMembers' => $room->roomUsers()->whereHas('debts', fn ($q) => $q->whereIn('status', DebtStatus::outstandingValues()))->count(),
        ];
    }

    /**
     * Format room user detail with masked device trust indicators, financial & activity metrics.
     *
     * @param RoomUser $roomUser Room user entity.
     * @return array<string, mixed> Structured detail payload.
     */
    public function formatUserDetail(RoomUser $roomUser): array
    {
        $roomUser->load(['globalUser', 'orders.items.toppings', 'debts.campaign', 'devices']);

        $totalDebt = (int) $roomUser->debts
            ->whereIn('status', DebtStatus::outstandingValues())
            ->sum('remaining_amount');

        $totalSpent = (int) $roomUser->orders
            ->whereIn('status', [...OrderStatus::activeValues(), OrderStatus::Completed->value])
            ->sum('final_amount');

        $totalOrders = $roomUser->orders->count();

        $mappedDevices = $roomUser->devices->map(fn (RoomUserDevice $device): array => [
            'id' => $device->id,
            'device_uuid' => substr((string) $device->device_uuid, 0, 8) . '…',
            'device_name' => $device->device_name,
            'verified_at' => $device->verified_at?->format('d/m/Y H:i'),
            'last_seen_at' => $device->last_seen_at ? $device->last_seen_at->diffForHumans() : '—',
            'revoked_at' => $device->revoked_at?->format('d/m/Y H:i'),
            'status' => $device->revoked_at ? 'revoked' : 'active',
        ])->values()->all();

        return [
            'id' => $roomUser->id,
            'room_id' => $roomUser->room_id,
            'user_code' => $roomUser->user_code,
            'display_name' => $roomUser->display_name ?: ($roomUser->globalUser?->name ?? 'Member #' . $roomUser->id),
            'role' => $roomUser->role instanceof \BackedEnum ? $roomUser->role->value : (string) ($roomUser->role ?? 'member'),
            'status' => $roomUser->status instanceof \BackedEnum ? $roomUser->status->value : (string) ($roomUser->status ?? 'active'),
            'created_at' => $roomUser->created_at?->format('d/m/Y H:i'),
            'last_active_at' => $roomUser->last_active_at ? $roomUser->last_active_at->diffForHumans() : __('admin.never_active'),
            'total_orders' => $totalOrders,
            'total_spent' => $totalSpent,
            'total_debt' => $totalDebt,
            'global_user' => $roomUser->globalUser ? [
                'id' => $roomUser->globalUser->id,
                'name' => $roomUser->globalUser->name,
                'email' => $roomUser->globalUser->email,
                'phone' => $roomUser->globalUser->phone,
                'desk_location' => $roomUser->globalUser->desk_location,
                'delivery_location' => $roomUser->globalUser->delivery_location,
                'birthday' => $roomUser->globalUser->birthday?->format('d/m/Y'),
                'avatar_url' => $roomUser->globalUser->avatar_url,
                'preferences' => $roomUser->globalUser->preferences,
                'last_login_at' => $roomUser->globalUser->last_login_at?->format('d/m/Y H:i'),
            ] : null,
            'devices' => $mappedDevices,
        ];
    }
}
