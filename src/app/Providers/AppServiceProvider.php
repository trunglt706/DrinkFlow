<?php

namespace App\Providers;

use App\Services\FoodCrawler\Contracts\FoodCrawlerProviderInterface;
use App\Services\FoodCrawler\Contracts\BrowserTransportInterface;
use App\Services\FoodCrawler\Browser\PuppeteerBrowserTransport;
use App\Services\FoodCrawler\ProviderResolver;
use App\Events\AdminNotificationCreated;
use App\Events\SuperadminNotificationCreated;
use App\Listeners\PublishSuperadminNotification;
use App\Events\CampaignClosed;
use App\Events\CampaignCancelled;
use App\Events\CampaignCreated;
use App\Events\CampaignDelivering;
use App\Events\CampaignUpdated;
use App\Events\OrderCreated;
use App\Events\ProxyOrdersCreated;
use App\Events\OrderUpdated;
use App\Events\OrderDeleted;
use App\Events\RoomRealtimeEvent;
use App\Events\RoomMembershipUpdated;
use App\Events\UserNotificationCreated;
use App\Events\ForceReloadRequested;
use App\Events\MaintenanceStateChanged;
use App\Listeners\CreateOrderNotification;
use App\Listeners\NotifyProxyOrderRecipients;
use App\Listeners\CreateOrderStatusNotification;
use App\Listeners\NotifyCampaignClosed;
use App\Listeners\NotifyCampaignCancelled;
use App\Listeners\NotifyCampaignCreated;
use App\Listeners\NotifyCampaignDelivering;
use App\Listeners\NotifyCampaignUpdated;
use App\Listeners\NotifyOrderDeleted;
use App\Listeners\PublishRealtimeEvent;
use App\View\Composers\AdminLayoutComposer;
use App\View\Composers\SuperadminLayoutComposer;
use App\View\Composers\UserGlobalLayoutComposer;
use App\View\Composers\UserRoomLayoutComposer;
use App\View\Composers\PublicLayoutComposer;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(BrowserTransportInterface::class, PuppeteerBrowserTransport::class);
        // Online payment of platform invoices (hosted checkout + signed notifications).
        $this->app->bind(\App\Services\Billing\Gateway\PaymentGateway::class, \App\Services\Billing\Gateway\SignedLinkGateway::class);
        // Singleton so the .env-based mail/storage config is captured once, before overrides.
        $this->app->singleton(\App\Services\System\SystemConfigService::class);
        // One instance per request: middleware and actions share the room lists it has already read.
        $this->app->scoped(\App\Services\Room\RoomAccessPolicy::class);
        $this->app->singleton(ProviderResolver::class, function (): ProviderResolver {
            $providers = array_map(
                fn(string $provider): FoodCrawlerProviderInterface => $this->app->make($provider),
                config('food-crawler.providers', []),
            );

            return new ProviderResolver($providers);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Superadmin mail/storage settings override the .env values (empty fields keep .env).
        $this->app->make(\App\Services\System\SystemConfigService::class)->apply();

        // Superadmin features: one Gate per permission; resources: policies with the Agent scope.
        foreach (\App\Enums\Permission::cases() as $permission) {
            \Illuminate\Support\Facades\Gate::define(
                $permission->value,
                static fn (mixed $user): bool => $user instanceof \App\Models\Superadmin && $user->hasPermission($permission),
            );
        }
        \Illuminate\Support\Facades\Gate::policy(\App\Models\Admin::class, \App\Policies\AdminPolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\App\Models\Room::class, \App\Policies\RoomPolicy::class);

        // A self-service Agent registration announces itself to every active Superadmin (see AdminObserver).
        \App\Models\Admin::observe(\App\Observers\AdminObserver::class);

        Event::listen(OrderCreated::class, CreateOrderNotification::class);
        Event::listen(ProxyOrdersCreated::class, NotifyProxyOrderRecipients::class);
        Event::listen(OrderUpdated::class, CreateOrderStatusNotification::class);
        Event::listen(OrderDeleted::class, NotifyOrderDeleted::class);
        Event::listen(OrderCreated::class, PublishRealtimeEvent::class);
        Event::listen(OrderUpdated::class, PublishRealtimeEvent::class);
        Event::listen(OrderDeleted::class, PublishRealtimeEvent::class);
        Event::listen(CampaignCreated::class, NotifyCampaignCreated::class);
        Event::listen(CampaignCreated::class, PublishRealtimeEvent::class);
        Event::listen(CampaignUpdated::class, NotifyCampaignUpdated::class);
        Event::listen(CampaignUpdated::class, PublishRealtimeEvent::class);
        Event::listen(CampaignDelivering::class, NotifyCampaignDelivering::class);
        Event::listen(CampaignDelivering::class, PublishRealtimeEvent::class);
        Event::listen(CampaignClosed::class, NotifyCampaignClosed::class);
        Event::listen(CampaignClosed::class, PublishRealtimeEvent::class);
        Event::listen(CampaignCancelled::class, NotifyCampaignCancelled::class);
        Event::listen(CampaignCancelled::class, PublishRealtimeEvent::class);
        Event::listen(RoomRealtimeEvent::class, PublishRealtimeEvent::class);
        Event::listen(RoomMembershipUpdated::class, PublishRealtimeEvent::class);
        Event::listen(UserNotificationCreated::class, PublishRealtimeEvent::class);
        Event::listen(AdminNotificationCreated::class, PublishRealtimeEvent::class);
        Event::listen(SuperadminNotificationCreated::class, PublishSuperadminNotification::class);
        Event::listen(ForceReloadRequested::class, PublishRealtimeEvent::class);
        Event::listen(MaintenanceStateChanged::class, PublishRealtimeEvent::class);

        \Illuminate\Support\Facades\RateLimiter::for('campaign-resend-notification', function (\Illuminate\Http\Request $request) {
            $campaign = $request->route('campaign');
            $campaignId = $campaign instanceof \Illuminate\Database\Eloquent\Model ? $campaign->getKey() : (string) $campaign;
            $adminKey = $request->user('admin')?->id ? 'admin:' . $request->user('admin')->id : ($request->ip() ?: '127.0.0.1');
            $tooManyRequests = static fn (\Illuminate\Http\Request $request, array $headers): \Illuminate\Http\JsonResponse => response()->json([
                'message' => __('admin.resend_notification_rate_limited', ['seconds' => (int) ($headers['Retry-After'] ?? 60)]),
            ], 429, $headers);

            return [
                // One announcement per campaign per minute, regardless of which admin triggers it.
                \Illuminate\Cache\RateLimiting\Limit::perMinute(1)->by('campaign:' . $campaignId)->response($tooManyRequests),
                // Cap the total number of resends a single admin can fire per hour.
                \Illuminate\Cache\RateLimiting\Limit::perHour(10)->by($adminKey)->response($tooManyRequests),
            ];
        });

        \Illuminate\Support\Facades\RateLimiter::for('contact-submission', function (\Illuminate\Http\Request $request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(5)->by($request->ip() ?: '127.0.0.1');
        });

        \Illuminate\Support\Facades\RateLimiter::for('feedback-submission', function (\Illuminate\Http\Request $request) {
            $userKey = $request->user('web')?->id ? 'user:' . $request->user('web')->id : ($request->ip() ?: '127.0.0.1');
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(5)->by($userKey);
        });

        \Illuminate\Support\Facades\RateLimiter::for('room-member-lookup', function (\Illuminate\Http\Request $request) {
            $userKey = $request->user('web')?->id
                ? 'user:' . $request->user('web')->id
                : 'ip:' . ($request->ip() ?: '127.0.0.1');

            return \Illuminate\Cache\RateLimiting\Limit::perMinute(20)->by($userKey);
        });

        \Illuminate\Support\Facades\RateLimiter::for('crawler-preview', function (\Illuminate\Http\Request $request) {
            $adminKey = $request->user('admin')?->id ? 'admin:' . $request->user('admin')->id : ($request->ip() ?: '127.0.0.1');
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(10)->by($adminKey);
        });

        // Per-admin limits for expensive or fan-out admin operations (file exports, bulk changes, reminders).
        \Illuminate\Support\Facades\RateLimiter::for('admin-export', function (\Illuminate\Http\Request $request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(10)->by('admin-export:' . ($request->user('admin')?->id ?? $request->ip() ?: '127.0.0.1'));
        });

        \Illuminate\Support\Facades\RateLimiter::for('admin-bulk', function (\Illuminate\Http\Request $request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(20)->by('admin-bulk:' . ($request->user('admin')?->id ?? $request->ip() ?: '127.0.0.1'));
        });

        \Illuminate\Support\Facades\RateLimiter::for('room-join', function (\Illuminate\Http\Request $request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(15)->by($request->ip() ?: '127.0.0.1');
        });

        \Illuminate\Support\Facades\RateLimiter::for('public-order-check', function (\Illuminate\Http\Request $request) {
            $ip = $request->ip() ?: '127.0.0.1';
            $identifier = mb_strtolower(trim((string) $request->input('identifier', '')));
            return [
                \Illuminate\Cache\RateLimiting\Limit::perMinute(10)->by('ip:'.$ip),
                \Illuminate\Cache\RateLimiting\Limit::perMinute(3)->by('lookup:'.$ip.'|'.hash('sha256', $identifier)),
            ];
        });

        \Illuminate\Support\Facades\RateLimiter::for('admin-login', function (\Illuminate\Http\Request $request) {
            $email = \Illuminate\Support\Str::lower((string) $request->input('email', ''));
            $ip = $request->ip() ?: '127.0.0.1';
            return [
                \Illuminate\Cache\RateLimiting\Limit::perMinute(5)->by($email . '|' . $ip),
                \Illuminate\Cache\RateLimiting\Limit::perMinute(20)->by($ip),
            ];
        });

        \Illuminate\Support\Facades\RateLimiter::for('auth-login', function (\Illuminate\Http\Request $request) {
            $email = \Illuminate\Support\Str::lower((string) $request->input('email', ''));
            $ip = $request->ip() ?: '127.0.0.1';
            return [
                \Illuminate\Cache\RateLimiting\Limit::perMinute(5)->by($email . '|' . $ip),
                \Illuminate\Cache\RateLimiting\Limit::perMinute(20)->by($ip),
            ];
        });

        \Illuminate\Support\Facades\RateLimiter::for('admin-forgot-password', function (\Illuminate\Http\Request $request) {
            $email = \Illuminate\Support\Str::lower((string) $request->input('email', ''));
            $ip = $request->ip() ?: '127.0.0.1';
            return [
                \Illuminate\Cache\RateLimiting\Limit::perMinute(5)->by($email . '|' . $ip),
                \Illuminate\Cache\RateLimiting\Limit::perMinute(15)->by($ip),
            ];
        });

        \Illuminate\Support\Facades\RateLimiter::for('admin-verify-otp', function (\Illuminate\Http\Request $request) {
            $email = \Illuminate\Support\Str::lower((string) ($request->session()->get('admin_reset_email') ?: $request->input('email', '')));
            $ip = $request->ip() ?: '127.0.0.1';
            return [
                \Illuminate\Cache\RateLimiting\Limit::perMinute(5)->by($email . '|' . $ip),
                \Illuminate\Cache\RateLimiting\Limit::perMinute(15)->by($ip),
            ];
        });

        \Illuminate\Support\Facades\RateLimiter::for('admin-reset-password', function (\Illuminate\Http\Request $request) {
            $email = \Illuminate\Support\Str::lower((string) ($request->input('email') ?: $request->query('email') ?: $request->session()->get('admin_reset_email', '')));
            $ip = $request->ip() ?: '127.0.0.1';
            return [
                \Illuminate\Cache\RateLimiting\Limit::perMinute(5)->by($email . '|' . $ip),
                \Illuminate\Cache\RateLimiting\Limit::perMinute(15)->by($ip),
            ];
        });

        \Illuminate\Support\Facades\RateLimiter::for('user-auth-google', function (\Illuminate\Http\Request $request) {
            $ip = $request->ip() ?: '127.0.0.1';
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(15)->by($ip);
        });

        \Illuminate\Support\Facades\RateLimiter::for('user-auth-logout', function (\Illuminate\Http\Request $request) {
            $ip = $request->ip() ?: '127.0.0.1';
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(20)->by($ip);
        });

        View::composer(['components.admin.*', 'admin.*'], AdminLayoutComposer::class);
        View::composer(['components.global.*', 'user.global.*'], UserGlobalLayoutComposer::class);
        View::composer(['components.room.*', 'user.room.*', 'user.orders'], UserRoomLayoutComposer::class);
        View::composer(['components.public.*', 'public.*'], PublicLayoutComposer::class);
        View::composer('superadmin.layout', SuperadminLayoutComposer::class);
        View::share('locales', \App\Constants\AppLocale::SUPPORTED);

        \Illuminate\Support\Facades\Blade::directive('formatDate', function ($expression) {
            return "<?php echo \App\Support\Helpers\FormatHelper::formatDate($expression); ?>";
        });

        \Illuminate\Support\Facades\Blade::directive('formatDateTime', function ($expression) {
            return "<?php echo \App\Support\Helpers\FormatHelper::formatDateTime($expression); ?>";
        });

        \Carbon\Carbon::macro('toAppDate', function () {
            /** @var \Carbon\Carbon $this */
            return \App\Support\Helpers\FormatHelper::formatDate($this);
        });

        \Carbon\Carbon::macro('toAppDateTime', function () {
            /** @var \Carbon\Carbon $this */
            return \App\Support\Helpers\FormatHelper::formatDateTime($this);
        });
    }
}
