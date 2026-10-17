<?php

declare(strict_types=1);

namespace App\Enums;

enum NotificationType: string
{
    case CampaignCreated = 'campaign.created';
    case CampaignClosed = 'campaign.closed';
    case CampaignCancelled = 'campaign.cancelled';
    case CampaignUpdated = 'campaign.updated';
    case CampaignDelivering = 'campaign.delivering';
    case CampaignDeadlineReminder = 'campaign.deadline_reminder';
    case NotificationTest = 'notification.test';
    case OrderCreated = 'order.created';
    case OrderProxyReceived = 'order.proxy_received';
    case OrderUpdated = 'order.updated';
    case OrderStatus = 'order.status';
    case OrderDeleted = 'order.deleted';
    case PaymentReminder = 'payment.reminder';
    case PaymentDue = 'payment.due';
    case PaymentConfirmed = 'payment.confirmed';
    case DebtReminder = 'debt.reminder';
    case DebtPaymentApproved = 'debt.payment_approved';
    case DebtUpdated = 'debt.updated';
    case DebtAdjusted = 'debt.adjusted';
    case DeviceNew = 'device.new';
    case SecurityAlert = 'security.alert';
    case RoomInvite = 'room.invite';
    case AdminBroadcast = 'admin.broadcast';
    case BirthdayToday = 'birthday.today';
}
