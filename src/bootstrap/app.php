<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // Listeners are registered explicitly in AppServiceProvider. Auto-discovery would register every
    // App\Listeners\*::handle() a second time and fire each notification twice.
    ->withEvents(discover: false)
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'global.user' => \App\Http\Middleware\ResolveGlobalUser::class,
            'room.user' => \App\Http\Middleware\ResolveRoomUser::class,
            'admin.room' => \App\Http\Middleware\EnsureAdminRoomAccess::class,
            'room.subscription' => \App\Http\Middleware\EnsureRoomSubscriptionActive::class,
            'superadmin' => \App\Http\Middleware\EnsureSuperadmin::class,
            'permission' => \App\Http\Middleware\EnsureSuperadminPermission::class,
            'maintenance' => \App\Http\Middleware\CheckMaintenanceMode::class,
            'json.only' => \App\Http\Middleware\EnsureJsonRequest::class,
            'user.has_rooms' => \App\Http\Middleware\EnsureUserHasRooms::class,
            'user.active_room' => \App\Http\Middleware\EnsureUserHasActiveRoom::class,
            // Per-room access rules configured on the room settings page (App\Services\Room\RoomAccessPolicy).
            'room.ip' => \App\Http\Middleware\EnsureRoomIpAllowed::class,
            'room.email_domain' => \App\Http\Middleware\EnsureRoomEmailDomainAllowed::class,
        ]);
        $middleware->redirectGuestsTo(function (Request $request): string {
            if ($request->is('superadmin', 'superadmin/*')) {
                return route('superadmin.login.page');
            }

            return $request->is('admin', 'admin/*') ? route('admin.login.page') : route('auth.google');
        });
        // The payment provider cannot send a CSRF token; its notifications are HMAC-signed instead.
        $middleware->validateCsrfTokens(except: ['payments/webhook']);
        $middleware->web(append: [
            \App\Http\Middleware\SetLocale::class,
            \App\Http\Middleware\EnsureActiveAdmin::class,
            \App\Http\Middleware\SecurityHeaders::class,
            // Needs the session, the admin guard and the matched route (superadmin bypass, login
            // routes), so it lives in the web group rather than the global stack.
            \App\Http\Middleware\CheckMaintenanceMode::class,
        ]);
        // Apply the session locale right after the session starts and before route model binding,
        // so a 404 thrown by a missing {room}/{campaign} model is rendered in the user's language.
        $middleware->prependToPriorityList(
            before: \Illuminate\Routing\Middleware\SubstituteBindings::class,
            prepend: \App\Http\Middleware\SetLocale::class,
        );
        // Same for the security headers: a 404 from route model binding must still carry them (noindex, CSP...).
        $middleware->prependToPriorityList(
            before: \Illuminate\Routing\Middleware\SubstituteBindings::class,
            prepend: \App\Http\Middleware\SecurityHeaders::class,
        );
        $middleware->append(\App\Http\Middleware\SanitizeInputStrings::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
