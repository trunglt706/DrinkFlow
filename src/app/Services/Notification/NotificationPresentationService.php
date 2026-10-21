<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Enums\NotificationType;
use App\Models\AdminNotification;
use App\Models\Campaign;
use App\Models\Debt;
use App\Models\Order;
use App\Models\Room;
use App\Models\SuperadminNotification;
use App\Models\UserNotification;
use App\Services\Campaign\CampaignDeadlineReminderService;
use Illuminate\Support\Carbon;

class NotificationPresentationService
{
    /**
     * Build locale-aware display data for a notification header item.
     *
     * @param UserNotification|AdminNotification $notification Notification model.
     * @return array{title: string, body: string, icon: string, link: ?string} Presentation data.
     */
    public function present(UserNotification|AdminNotification|SuperadminNotification $notification): array
    {
        $type = (string) $notification->type;
        $data = is_array($notification->data) ? $notification->data : [];

        // Platform notifications of Superadmins carry their own translated title/body.
        if ($notification instanceof SuperadminNotification) {
            return [
                'title' => (string) ($notification->title ?: __('superadmin.inbox.platform_default_title')),
                'body' => (string) ($notification->body ?? ''),
                'icon' => $this->icon($type),
                // Written server-side by the sender (e.g. the registration review page), never by a client.
                'link' => is_string($data['url'] ?? null) && $data['url'] !== '' ? $data['url'] : null,
            ];
        }

        // Messages written by a room admin are shown exactly as they were sent.
        if ($notification instanceof UserNotification && $this->isAdminBroadcast($type, $data)) {
            return [
                'title' => (string) ($notification->title ?: __('global.notifications.default_title')),
                'body' => (string) ($notification->body ?? ''),
                'icon' => $this->icon($type),
                'link' => $notification->link,
            ];
        }

        $titleKey = $this->titleKey($type, $notification instanceof AdminNotification);

        $title = $titleKey !== null ? __($titleKey) : (string) ($notification->title ?? '');
        if ($title === $titleKey || $title === '') {
            $title = (string) ($notification->title ?? __('global.notifications.default_title'));
        }
        if ($notification instanceof UserNotification && $this->isPaymentReminder($type)) {
            $code = $this->debtCode($data);
            if ($code !== null) {
                $title = __('messages.payment_reminder_with_code', ['code' => $code]);
            }
        }
        if ($notification instanceof UserNotification) {
            $title = $this->campaignTitle($type, $data) ?? $title;
        }

        $body = $this->body($notification, $type, $data);

        return [
            'title' => $title,
            'body' => $body,
            'icon' => $this->icon($type),
            'link' => $notification instanceof UserNotification ? $this->link($notification, $type, $data) : null,
        ];
    }

    /**
     * Determine whether a user notification was sent manually by a room admin.
     *
     * @param string $type Notification type.
     * @param array<string, mixed> $data Structured notification data.
     * @return bool True for admin broadcasts, whatever template type the admin picked.
     */
    private function isAdminBroadcast(string $type, array $data): bool
    {
        return $type === NotificationType::AdminBroadcast->value || ! empty($data['broadcast']);
    }

    /**
     * Determine whether a notification asks the member to pay.
     *
     * @param string $type Notification type.
     * @return bool True for payment/debt reminder types.
     */
    private function isPaymentReminder(string $type): bool
    {
        return in_array($type, [
            NotificationType::PaymentReminder->value,
            NotificationType::PaymentDue->value,
            NotificationType::DebtReminder->value,
        ], true);
    }

    /**
     * Resolve the debt code a payment reminder refers to.
     *
     * @param array<string, mixed> $data Structured notification data.
     * @return string|null Debt code, or null when the notification carries no debt reference.
     */
    private function debtCode(array $data): ?string
    {
        if (! empty($data['debt_code'])) {
            return (string) $data['debt_code'];
        }

        if (! empty($data['debt_id'])) {
            $code = Debt::query()->whereKey($data['debt_id'])->value('code');

            return $code !== null ? (string) $code : null;
        }

        return null;
    }

    /**
     * Build the coded title ("Campaign #CODE ...") of campaign created/closed/ordering-locked notifications.
     *
     * @param string $type Notification type.
     * @param array<string, mixed> $data Structured notification data.
     * @return string|null Localized title, or null when the type has no coded title or the campaign code is unknown.
     */
    private function campaignTitle(string $type, array $data): ?string
    {
        $key = match (true) {
            $type === NotificationType::CampaignCreated->value => 'messages.campaign_created_title',
            $type === NotificationType::CampaignClosed->value => 'messages.campaign_closed_title',
            $type === NotificationType::CampaignUpdated->value && ($data['ordering_locked'] ?? null) === true => 'messages.campaign_ordering_locked_title',
            default => null,
        };
        if ($key === null) {
            return null;
        }

        $code = ! empty($data['campaign_code'])
            ? (string) $data['campaign_code']
            : (! empty($data['campaign_id']) ? Campaign::query()->whereKey($data['campaign_id'])->value('code') : null);

        return $code !== null && $code !== '' ? __($key, ['code' => $code]) : null;
    }

    /**
     * Resolve the link of a user notification, deriving it from the payload when none was stored.
     *
     * Order notifications open the order page; new-campaign notifications open the campaign
     * order page only while that campaign still accepts orders.
     *
     * @param UserNotification $notification Notification model.
     * @param string $type Notification type.
     * @param array<string, mixed> $data Structured notification data.
     * @return string|null URL, or null when there is nothing to open.
     */
    private function link(UserNotification $notification, string $type, array $data): ?string
    {
        if ($type === NotificationType::CampaignDeadlineReminder->value) {
            return $this->liveCampaignLink($data);
        }

        // New campaign / ordering locked: the campaign info page.
        if ($type === NotificationType::CampaignCreated->value
            || ($type === NotificationType::CampaignUpdated->value && ($data['ordering_locked'] ?? null) === true)) {
            $room = $this->campaignRoom($data);

            return $room !== null ? route('user.campaigns.index', $room) : $notification->link;
        }

        // Items delivered: the member's "My orders" page.
        if ($type === NotificationType::CampaignDelivering->value) {
            $room = $this->campaignRoom($data);

            return $room !== null ? route('user.orders.index', $room) : $notification->link;
        }

        // Payment reminders: the debts page, opening that debt's payment modal.
        if ($this->isPaymentReminder($type)) {
            $code = $this->debtCode($data);
            $room = $this->campaignRoom($data);
            if ($code !== null && $room !== null) {
                return route('user.debts.index', ['room' => $room, 'debt' => $code]);
            }
        }

        if (! empty($notification->link)) {
            return $notification->link;
        }

        if ($type === NotificationType::OrderCreated->value && ! empty($data['order_id'])) {
            return $this->orderLink((int) $data['order_id']);
        }

        return null;
    }

    /**
     * Build the order page URL of an order.
     *
     * @param int $orderId Order ID.
     * @return string|null Order page URL, or null when the order or its room no longer exists.
     */
    private function orderLink(int $orderId): ?string
    {
        // `code` is selected because the order page is bound by code, not by id.
        $order = Order::query()->with('room:id,slug')->find($orderId, ['id', 'code', 'room_id']);
        if ($order === null || ! $order->room instanceof Room) {
            return null;
        }

        return route('user.orders.page', [$order->room, $order]);
    }

    /**
     * Resolve the room of a notification from its room or campaign reference.
     *
     * @param array<string, mixed> $data Structured notification data.
     * @return Room|null Room, or null when neither reference resolves.
     */
    private function campaignRoom(array $data): ?Room
    {
        if (! empty($data['room_id'])) {
            $room = Room::query()->find($data['room_id'], ['id', 'slug']);
            if ($room !== null) {
                return $room;
            }
        }

        if (! empty($data['campaign_id'])) {
            $roomId = Campaign::query()->whereKey($data['campaign_id'])->value('room_id');

            return $roomId !== null ? Room::query()->find($roomId, ['id', 'slug']) : null;
        }

        return null;
    }

    /**
     * Build the campaign order page URL while the campaign is live.
     *
     * @param array<string, mixed> $data Structured notification data.
     * @return string|null Campaign order page URL, or null when the campaign is gone or no longer orderable.
     */
    private function liveCampaignLink(array $data): ?string
    {
        if (empty($data['campaign_id'])) {
            return null;
        }

        $campaign = Campaign::query()->with('room:id,slug')->find($data['campaign_id']);
        if ($campaign === null || ! $campaign->room instanceof Room || ! $campaign->isOrderable()) {
            return null;
        }

        return route('user.campaigns.order-page', [$campaign->room, $campaign]);
    }

    /**
     * Resolve the translation key for a notification title.
     *
     * @param string $type Notification type.
     * @param bool $admin Whether the notification belongs to an admin.
     * @return string|null Translation key or null for a stored/custom title.
     */
    private function titleKey(string $type, bool $admin): ?string
    {
        if ($admin) {
            $key = 'admin.audit_event_' . str_replace('.', '_', $type);
            return __($key) === $key ? null : $key;
        }

        return match ($type) {
            NotificationType::CampaignCancelled->value => 'messages.campaign_cancelled_title',
            NotificationType::CampaignDeadlineReminder->value => 'messages.campaign_deadline_reminder_title',
            NotificationType::OrderCreated->value => 'messages.order_created',
            NotificationType::OrderProxyReceived->value => 'messages.order_proxy_received_title',
            NotificationType::OrderUpdated->value, NotificationType::OrderStatus->value => 'messages.order_status_updated',
            NotificationType::OrderDeleted->value => 'messages.order_deleted_title',
            NotificationType::PaymentReminder->value, NotificationType::PaymentDue->value, NotificationType::DebtReminder->value => 'messages.payment_reminder',
            default => null,
        };
    }

    /**
     * Resolve a locale-aware body while preserving rich campaign payloads.
     *
     * @param UserNotification|AdminNotification $notification Notification model.
     * @param string $type Notification type.
     * @param array<string, mixed> $data Structured notification data.
     * @return string Body text.
     */
    private function body(UserNotification|AdminNotification $notification, string $type, array $data): string
    {
        if ($type === NotificationType::CampaignDeadlineReminder->value && ! empty($data['deadline'])) {
            return $this->deadlineReminderBody($notification, $data);
        }

        if ($notification instanceof AdminNotification) {
            if (!empty($notification->body)) {
                return (string) $notification->body;
            }

            $auditKey = 'admin.audit_event_' . str_replace('.', '_', $type);
            $translated = __($auditKey);
            if ($translated !== $auditKey) {
                return $translated;
            }

            return (string) ($notification->title ?? __('admin.system_updated'));
        }

        $orderId = $data['order_id'] ?? null;
        $orderCode = (string) ($data['order_code'] ?? '');
        if ($orderCode === '' && $orderId !== null) {
            $orderCode = (string) (Order::query()->whereKey($orderId)->value('code') ?? '#' . $orderId);
        }

        if ($type === NotificationType::OrderCreated->value && $orderCode !== '') {
            return __('messages.order_created_body', ['order_code' => $orderCode]);
        }
        if (in_array($type, [NotificationType::OrderUpdated->value, NotificationType::OrderStatus->value], true) && $orderCode !== '') {
            return __('messages.order_status_updated_body', [
                'order_code' => $orderCode,
                'status' => (string) ($data['status'] ?? ''),
            ]);
        }
        if ($type === NotificationType::OrderDeleted->value) {
            return __('messages.order_deleted_body');
        }

        return (string) ($notification->body ?? '');
    }

    /**
     * Build the "ordering closes soon" body in the current locale from the stored campaign data.
     *
     * @param UserNotification|AdminNotification $notification Notification model.
     * @param array<string, mixed> $data Structured notification data (campaign name, deadline, member counts).
     * @return string Body text.
     */
    private function deadlineReminderBody(UserNotification|AdminNotification $notification, array $data): string
    {
        $replace = [
            'campaign' => (string) ($data['campaign_name'] ?? $data['campaign_code'] ?? ''),
            'time' => app(CampaignDeadlineReminderService::class)->formatTime(Carbon::parse((string) $data['deadline'])),
        ];

        if ($notification instanceof AdminNotification) {
            return __('admin.campaign_deadline_reminder_body', $replace + [
                'pending' => (int) ($data['pending_count'] ?? 0),
                'total' => (int) ($data['total_count'] ?? 0),
            ]);
        }

        return __('messages.campaign_deadline_reminder_body', $replace);
    }

    /**
     * Resolve the header icon for a notification type.
     *
     * @param string $type Notification type.
     * @return string Material Symbols icon name.
     */
    private function icon(string $type): string
    {
        return match (true) {
            $type === NotificationType::CampaignDeadlineReminder->value => 'alarm',
            // Platform alerts about Agents: same icon as the Agents section of the console.
            str_starts_with($type, 'agent.') => 'storefront',
            str_starts_with($type, 'campaign.') => 'local_fire_department',
            str_starts_with($type, 'order.') => 'check_circle',
            str_starts_with($type, 'payment.'), str_starts_with($type, 'debt.') => 'payments',
            str_starts_with($type, 'security.'), $type === NotificationType::DeviceNew->value => 'security',
            $type === NotificationType::YearRecap->value => 'auto_awesome',
            default => 'notifications',
        };
    }
}
