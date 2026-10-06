<?php

namespace Tests;

use App\Models\Superadmin;
use App\Services\Authorization\PermissionCatalogService;
use App\Support\Security\OutboundUrlGuard;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The settings cache is static and outlives the database refresh: without this a maintenance window
        // saved by an earlier test would still apply (e.g. once a later test travels in time).
        \App\Services\System\SystemSettingsService::clearCache();

        // Keep tests offline: every host name resolves to a fixed public address, so only IP literals and
        // reserved names such as "localhost" can exercise the private-address checks of the SSRF guard.
        $this->app->bind(OutboundUrlGuard::class, fn (): OutboundUrlGuard => new class extends OutboundUrlGuard {
            protected function resolveHost(string $host): array
            {
                return ['93.184.216.34'];
            }
        });
    }

    /**
     * Create a superadmin holding every permission with the `all` scope (platform owner).
     *
     * @param array<string, mixed> $attributes Superadmin attributes.
     * @return Superadmin Created superadmin.
     */
    protected function createSuperadmin(array $attributes): Superadmin
    {
        $superadmin = Superadmin::create($attributes);
        app(PermissionCatalogService::class)->grantAll($superadmin);

        return $superadmin;
    }

    /**
     * Masked form of an email as the Superadmin console shows it (the raw value is never rendered there),
     * so list / isolation assertions keep telling one person's row from another's.
     *
     * @param string $email Real email.
     * @return string Masked email, e.g. "al***@d***.test".
     */
    protected function maskedEmail(string $email): string
    {
        return app(\App\Services\Security\SensitiveDataService::class)->mask($email, \App\Services\Security\SensitiveDataService::TYPE_EMAIL);
    }
}
