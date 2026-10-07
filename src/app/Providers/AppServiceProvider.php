<?php

declare(strict_types=1);

namespace App\Providers;

use App\Constants\AppLocale;
use App\Enums\Permission;
use App\Events\AdminNotificationCreated;
use App\Events\CampaignCancelled;
use App\Events\CampaignClosed;
use App\Events\CampaignCreated;
use App\Events\CampaignDelivering;
use App\Events\CampaignUpdated;
use App\Events\ForceReloadRequested;
use App\Events\MaintenanceStateChanged;
use App\Events\OrderCreated;
use App\Events\OrderDeleted;
use App\Events\OrderUpdated;
use App\Events\ProxyOrdersCreated;
use App\Events\RoomMembershipUpdated;
use App\Events\RoomRealtimeEvent;
use App\Events\SuperadminNotificationCreated;
use App\Events\UserNotificationCreated;
use App\Listeners\CreateOrderNotification;
use App\Listeners\CreateOrderStatusNotification;
use App\Listeners\NotifyCampaignCancelled;
use App\Listeners\NotifyCampaignClosed;
use App\Listeners\NotifyCampaignCreated;
use App\Listeners\NotifyCampaignDelivering;
use App\Listeners\NotifyCampaignUpdated;
use App\Listeners\NotifyOrderDeleted;
use App\Listeners\NotifyProxyOrderRecipients;
use App\Listeners\PublishRealtimeEvent;
use App\Listeners\PublishSuperadminNotification;
use App\Models\Admin;
use App\Models\Room;
use App\Models\Superadmin;
use App\Observers\AdminObserver;
use App\Policies\AdminPolicy;
use App\Policies\RoomPolicy;
use App\Services\Billing\Gateway\PaymentGateway;
use App\Services\Billing\Gateway\SignedLinkGateway;
use App\Services\FoodCrawler\Browser\PuppeteerBrowserTransport;
use App\Services\FoodCrawler\Contracts\BrowserTransportInterface;
use App\Services\FoodCrawler\Contracts\FoodCrawlerProviderInterface;
use App\Services\FoodCrawler\ProviderResolver;
use App\Services\Notification\UserNotificationSummary;
use App\Services\Room\RoomAccessPolicy;
use App\Services\System\SystemConfigService;
use App\Support\Helpers\FormatHelper;
use App\View\Composers\AdminLayoutComposer;
use App\View\Composers\PublicLayoutComposer;
use App\View\Composers\SuperadminLayoutComposer;
use App\View\Composers\UserGlobalLayoutComposer;
use App\View\Composers\UserRoomLayoutComposer;
use Carbon\Carbon;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(BrowserTransportInterface::class, PuppeteerBrowserTransport::class);
        // Online payment of platform invoices (hosted checkout + signed notifications).
        $this->app->bind(PaymentGateway::class, SignedLinkGateway::class);
        // Singleton so the .env-based mail/storage config is captured once, before overrides.
        $this->app->singleton(SystemConfigService::class);
        // One instance per request: middleware and actions share the room lists it has already read.
        $this->app->scoped(RoomAccessPolicy::class);
        // Global layout and page services share one notification summary per HTTP request.
        $this->app->scoped(UserNotificationSummary::class);
        $this->app->singleton(ProviderResolver::class, function (): ProviderResolver {
            $providers = array_map(
                fn (string $provider): FoodCrawlerProviderInterface => $this->app->make($provider),
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
        $this->app->make(SystemConfigService::class)->apply();

        // Superadmin features: one Gate per permission; resources: policies with the Agent scope.
        foreach (Permission::cases() as $permission) {
            Gate::define(
                $permission->value,
                static fn (mixed $user): bool => $user instanceof Superadmin && $user->hasPermission($permission),
            );
        }
        Gate::policy(Admin::class, AdminPolicy::class);
        Gate::policy(Room::class, RoomPolicy::class);

        // A self-service Agent registration announces itself to every active Superadmin (see AdminObserver).
        Admin::observe(AdminObserver::class);

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

        RateLimiter::for('campaign-resend-notification', function (Request $request) {
            $campaign = $request->route('campaign');
            $campaignId = $campaign instanceof Model ? $campaign->getKey() : (string) $campaign;
            $adminKey = $request->user('admin')?->id ? 'admin:'.$request->user('admin')->id : ($request->ip() ?: '127.0.0.1');
            $tooManyRequests = static fn (Request $request, array $headers): JsonResponse => response()->json([
                'message' => __('admin.resend_notification_rate_limited', ['seconds' => (int) ($headers['Retry-After'] ?? 60)]),
            ], 429, $headers);

            return [
                // One announcement per campaign per minute, regardless of which admin triggers it.
                Limit::perMinute(1)->by('campaign:'.$campaignId)->response($tooManyRequests),
                // Cap the total number of resends a single admin can fire per hour.
                Limit::perHour(10)->by($adminKey)->response($tooManyRequests),
            ];
        });

        RateLimiter::for('contact-submission', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip() ?: '127.0.0.1');
        });

        RateLimiter::for('feedback-submission', function (Request $request) {
            $userKey = $request->user('web')?->id ? 'user:'.$request->user('web')->id : ($request->ip() ?: '127.0.0.1');

            return Limit::perMinute(5)->by($userKey);
        });

        RateLimiter::for('room-member-lookup', function (Request $request) {
            $userKey = $request->user('web')?->id
                ? 'user:'.$request->user('web')->id
                : 'ip:'.($request->ip() ?: '127.0.0.1');

            return Limit::perMinute(20)->by($userKey);
        });

        RateLimiter::for('crawler-preview', function (Request $request) {
            $adminKey = $request->user('admin')?->id ? 'admin:'.$request->user('admin')->id : ($request->ip() ?: '127.0.0.1');

            return Limit::perMinute(10)->by($adminKey);
        });

        // Per-admin limits for expensive or fan-out admin operations (file exports, bulk changes, reminders).
        RateLimiter::for('admin-export', function (Request $request) {
            return Limit::perMinute(10)->by('admin-export:'.($request->user('admin')?->id ?? $request->ip() ?: '127.0.0.1'));
        });

        RateLimiter::for('admin-bulk', function (Request $request) {
            return Limit::perMinute(20)->by('admin-bulk:'.($request->user('admin')?->id ?? $request->ip() ?: '127.0.0.1'));
        });

        // Image uploads are resized/re-encoded server side: generous enough to build a whole menu, bounded per admin.
        RateLimiter::for('admin-image-upload', function (Request $request) {
            return Limit::perMinute(60)->by('admin-image-upload:'.($request->user('admin')?->id ?? $request->ip() ?: '127.0.0.1'));
        });

        // Rebuilding the sponsor podium re-aggregates a month of orders: 3 runs per 10 minutes per admin account.
        RateLimiter::for('sponsor-honors-recalculate', function (Request $request) {
            return Limit::perMinutes(10, 3)
                ->by('sponsor-honors:'.($request->user('admin')?->id ?? $request->ip() ?: '127.0.0.1'))
                ->response(static fn (Request $request, array $headers) => back()->with('sponsor_honors_error', __('sponsor_honors.recalculate_rate_limited', [
                    'minutes' => max(1, (int) ceil(((int) ($headers['Retry-After'] ?? 60)) / 60)),
                ])));
        });

        RateLimiter::for('room-join', function (Request $request) {
            return Limit::perMinute(15)->by($request->ip() ?: '127.0.0.1');
        });

        RateLimiter::for('public-order-check', function (Request $request) {
            $ip = $request->ip() ?: '127.0.0.1';
            $identifier = mb_strtolower(trim((string) $request->input('identifier', '')));

            return [
                Limit::perMinute(10)->by('ip:'.$ip),
                Limit::perMinute(3)->by('lookup:'.$ip.'|'.hash('sha256', $identifier)),
            ];
        });

        RateLimiter::for('admin-login', function (Request $request) {
            $email = Str::lower((string) $request->input('email', ''));
            $ip = $request->ip() ?: '127.0.0.1';

            return [
                Limit::perMinute(5)->by($email.'|'.$ip),
                Limit::perMinute(20)->by($ip),
            ];
        });

        RateLimiter::for('auth-login', function (Request $request) {
            $email = Str::lower((string) $request->input('email', ''));
            $ip = $request->ip() ?: '127.0.0.1';

            return [
                Limit::perMinute(5)->by($email.'|'.$ip),
                Limit::perMinute(20)->by($ip),
            ];
        });

        RateLimiter::for('admin-forgot-password', function (Request $request) {
            $email = Str::lower((string) $request->input('email', ''));
            $ip = $request->ip() ?: '127.0.0.1';

            return [
                Limit::perMinute(5)->by($email.'|'.$ip),
                Limit::perMinute(15)->by($ip),
            ];
        });

        RateLimiter::for('admin-verify-otp', function (Request $request) {
            $email = Str::lower((string) ($request->session()->get('admin_reset_email') ?: $request->input('email', '')));
            $ip = $request->ip() ?: '127.0.0.1';

            return [
                Limit::perMinute(5)->by($email.'|'.$ip),
                Limit::perMinute(15)->by($ip),
            ];
        });

        RateLimiter::for('admin-reset-password', function (Request $request) {
            $email = Str::lower((string) ($request->input('email') ?: $request->query('email') ?: $request->session()->get('admin_reset_email', '')));
            $ip = $request->ip() ?: '127.0.0.1';

            return [
                Limit::perMinute(5)->by($email.'|'.$ip),
                Limit::perMinute(15)->by($ip),
            ];
        });

        RateLimiter::for('user-auth-google', function (Request $request) {
            $ip = $request->ip() ?: '127.0.0.1';

            return Limit::perMinute(15)->by($ip);
        });

        RateLimiter::for('user-auth-logout', function (Request $request) {
            $ip = $request->ip() ?: '127.0.0.1';

            return Limit::perMinute(20)->by($ip);
        });

        View::composer(['components.admin.*', 'admin.*'], AdminLayoutComposer::class);
        View::composer(['components.global.*', 'user.global.*'], UserGlobalLayoutComposer::class);
        View::composer(['components.room.*', 'user.room.*', 'user.orders'], UserRoomLayoutComposer::class);
        View::composer(['components.public.*', 'public.*'], PublicLayoutComposer::class);
        View::composer('superadmin.layout', SuperadminLayoutComposer::class);
        View::share('locales', AppLocale::SUPPORTED);

        Blade::directive('formatDate', function ($expression) {
            return "<?php echo \App\Support\Helpers\FormatHelper::formatDate($expression); ?>";
        });

        Blade::directive('formatDateTime', function ($expression) {
            return "<?php echo \App\Support\Helpers\FormatHelper::formatDateTime($expression); ?>";
        });

        Carbon::macro('toAppDate', function () {
            /** @var Carbon $this */
            return FormatHelper::formatDate($this);
        });

        Carbon::macro('toAppDateTime', function () {
            /** @var Carbon $this */
            return FormatHelper::formatDateTime($this);
        });
    }
}
