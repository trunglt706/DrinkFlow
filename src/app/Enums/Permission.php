<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Catalog of Superadmin permissions, stored in the `permissions` table.
 *
 * Each key is "<group>.<ability>". The enum is the single source of truth: `permissions:sync`
 * writes new cases to the database, and code checks abilities through these cases instead of
 * string literals. Labels live in lang/{vi,en,ja}/superadmin.php under `permissions`.
 */
enum Permission: string
{
    case AgentView = 'agent.view';
    case AgentManage = 'agent.manage';
    case AgentApprove = 'agent.approve';
    case PackageView = 'package.view';
    case PackageManage = 'package.manage';
    case SubscriptionView = 'subscription.view';
    case SubscriptionManage = 'subscription.manage';
    case RevenueView = 'revenue.view';
    case DebtView = 'debt.view';
    case DebtManage = 'debt.manage';
    case RoomView = 'room.view';
    case RoomManage = 'room.manage';
    case GlobalUserView = 'global_user.view';
    case GlobalUserManage = 'global_user.manage';
    case FeedbackView = 'feedback.view';
    case FeedbackManage = 'feedback.manage';
    case VersionView = 'version.view';
    case VersionManage = 'version.manage';
    case GameView = 'game.view';
    case GameManage = 'game.manage';
    case SettingsView = 'settings.view';
    case SettingsManage = 'settings.manage';
    case SecurityView = 'security.view';
    case AuditView = 'audit.view';
    case QueueView = 'queue.view';
    case QueueManage = 'queue.manage';
    case SuperadminView = 'superadmin.view';
    case SuperadminManage = 'superadmin.manage';

    /**
     * Permission group, the part before the dot (agent, package, room…).
     *
     * @return string Group key.
     */
    public function group(): string
    {
        return strstr($this->value, '.', true) ?: $this->value;
    }

    /**
     * Translated label of the permission.
     *
     * @return string Label.
     */
    public function label(): string
    {
        return __('superadmin.permissions.items.'.str_replace('.', '_', $this->value));
    }

    /**
     * Translated label of the permission group.
     *
     * @return string Group label.
     */
    public function groupLabel(): string
    {
        return __('superadmin.permissions.groups.'.$this->group());
    }

    /**
     * Whether the permission can be limited to the Agents assigned to a Superadmin (`managed` scope).
     *
     * Agent-owned data (agents, their subscriptions, revenue, debt and rooms) can be scoped; platform
     * resources such as packages, settings or queues are always global.
     *
     * @return bool True when `managed` is meaningful for this permission.
     */
    public function supportsScope(): bool
    {
        return in_array($this->group(), ['agent', 'subscription', 'revenue', 'debt', 'room'], true);
    }

    /**
     * Every permission key.
     *
     * @return array<int, string> Permission values.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
