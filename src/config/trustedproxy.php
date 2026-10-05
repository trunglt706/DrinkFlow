<?php

declare(strict_types=1);

$configuredProxies = array_filter(
    array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', ''))),
    static fn (string $proxy): bool => $proxy !== '',
);

return [
    // Keep empty when Laravel is reached directly. Never use "*" in production.
    'proxies' => array_values($configuredProxies),
];
