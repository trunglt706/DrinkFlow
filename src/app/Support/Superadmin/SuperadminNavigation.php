<?php

declare(strict_types=1);

namespace App\Support\Superadmin;

use App\Models\Superadmin;
use Illuminate\Support\Facades\Gate;

/**
 * Sidebar / command-palette menu of the superadmin console.
 *
 * Grouped as in the Navigation Target of documents/features/development_tasks_saas_extension.md. Links the signed-in
 * account may not open are left out (the routes still enforce the same permissions).
 */
final class SuperadminNavigation
{
    /**
     * Menu definition: section label key (null = no heading) => list of [key, route name, icon, label key, permission].
     *
     * @var array<int, array{0: string|null, 1: list<array{0: string, 1: string, 2: string, 3: string, 4: string|null}>}>
     */
    private const SECTIONS = [
        [null, [
            ['dashboard', 'superadmin.dashboard', 'space_dashboard', 'superadmin.layout.dashboard_health', null],
        ]],
        ['platform.nav.agents', [
            ['agents', 'superadmin.agents.index', 'storefront', 'platform.nav.agent_list', 'agent.view'],
            ['registrations', 'superadmin.registrations.index', 'how_to_reg', 'platform.nav.registrations', 'agent.approve'],
            ['subscriptions', 'superadmin.subscriptions.index', 'workspace_premium', 'platform.nav.subscriptions', 'subscription.view'],
        ]],
        ['platform.nav.plans', [
            ['packages', 'superadmin.packages.index', 'inventory_2', 'platform.nav.packages', 'package.view'],
        ]],
        ['platform.nav.platform_finance', [
            ['revenue', 'superadmin.billing.revenue', 'payments', 'platform.nav.revenue', 'revenue.view'],
            ['outstanding', 'superadmin.billing.outstanding', 'request_quote', 'platform.nav.outstanding', 'debt.view'],
        ]],
        ['platform.nav.system', [
            ['rooms', 'superadmin.rooms.page', 'meeting_room', 'superadmin.layout.room_management', 'room.view'],
            ['users', 'superadmin.global-users.page', 'group', 'superadmin.layout.global_users', 'global_user.view'],
            ['campaigns', 'superadmin.campaigns.page', 'campaign', 'superadmin.layout.global_campaigns', 'room.view'],
            ['feedbacks', 'superadmin.feedbacks.page', 'rate_review', 'superadmin.layout.feedbacks', 'feedback.view'],
        ]],
        ['platform.nav.content', [
            ['versions', 'superadmin.versions.page', 'new_releases', 'superadmin.layout.versions', 'version.view'],
            ['notifications', 'superadmin.notifications.page', 'notifications', 'superadmin.layout.global_notifications', 'settings.view'],
        ]],
        ['platform.nav.governance', [
            ['superadmins', 'superadmin.superadmins.index', 'manage_accounts', 'superadmin.layout.superadmins', 'superadmin.view'],
            ['roles', 'superadmin.roles.index', 'badge', 'platform.nav.roles', 'superadmin.view'],
            ['audit', 'superadmin.audit.page', 'history_toggle_off', 'superadmin.layout.audit_logs', 'audit.view'],
            ['security', 'superadmin.security.page', 'shield_locked', 'superadmin.layout.security_center', 'security.view'],
        ]],
        ['platform.nav.infrastructure', [
            ['socket', 'superadmin.socket.page', 'hub', 'superadmin.layout.socket_queue', 'queue.view'],
            ['system', 'superadmin.system.page', 'build_circle', 'superadmin.layout.system_settings', 'settings.view'],
        ]],
    ];

    /**
     * Menu sections visible to the given account, with translated labels and resolved URLs.
     *
     * @param Superadmin|null $viewer Signed-in superadmin; null yields only the permission-free links.
     * @return list<array{label: string|null, links: list<array{key: string, url: string, icon: string, label: string}>}>
     */
    public function sectionsFor(?Superadmin $viewer): array
    {
        $gate = Gate::forUser($viewer);
        $sections = [];

        foreach (self::SECTIONS as [$labelKey, $links]) {
            $visible = [];
            foreach ($links as [$key, $routeName, $icon, $linkLabelKey, $permission]) {
                if ($permission !== null && ($viewer === null || !$gate->allows($permission))) {
                    continue;
                }
                $visible[] = ['key' => $key, 'url' => route($routeName), 'icon' => $icon, 'label' => __($linkLabelKey)];
            }

            if ($visible !== []) {
                $sections[] = ['label' => $labelKey === null ? null : __($labelKey), 'links' => $visible];
            }
        }

        return $sections;
    }

    /**
     * Section label and link label of the active menu entry, for the topbar breadcrumb.
     *
     * @param list<array{label: string|null, links: list<array{key: string, url: string, icon: string, label: string}>}> $sections Output of sectionsFor().
     * @param string|null $active Key of the active menu entry (the layout's `active` parameter).
     * @return array{section: string|null, link: array{key: string, url: string, icon: string, label: string}}|null
     */
    public function locate(array $sections, ?string $active): ?array
    {
        if ($active === null || $active === '') {
            return null;
        }

        foreach ($sections as $section) {
            foreach ($section['links'] as $link) {
                if ($link['key'] === $active) {
                    return ['section' => $section['label'], 'link' => $link];
                }
            }
        }

        return null;
    }
}
