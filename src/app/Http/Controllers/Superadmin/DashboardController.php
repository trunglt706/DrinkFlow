<?php

declare(strict_types=1);

namespace App\Http\Controllers\Superadmin;

use App\Enums\CampaignStatus;
use App\Enums\GlobalUserStatus;
use App\Enums\OrderStatus;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Campaign;
use App\Models\GlobalUser;
use App\Models\Order;
use App\Models\Room;
use App\Services\Authorization\AgentScope;
use App\Services\Dashboard\PlatformDashboardService;
use App\Services\Dashboard\SuperadminDashboardService;
use App\Services\Dashboard\SuperadminInsightsService;
use App\Services\Dashboard\SuperadminTrendsService;
use App\Services\System\SystemHealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    /**
     * Handle the index operation.
     * @param Request $request Parameter value.
     * @param SystemHealthService $health Infrastructure health snapshot service.
     * @param PlatformDashboardService $platform Permission-aware platform widgets.
     * @return JsonResponse|View Result of the operation.
     */
    public function index(Request $request, SystemHealthService $health, PlatformDashboardService $platform): JsonResponse|View
    {
        /** @var \App\Models\Superadmin $superadmin */
        $superadmin = $request->user('superadmin');
        if (!$request->expectsJson()) {
            return view('superadmin.dashboard', [
                'widgets' => $platform->widgetsFor($superadmin),
                'platformAnalytics' => $platform->canSeePlatformAnalytics($superadmin),
                // Restricted Superadmins with room access get the operations analytics of their own rooms.
                'scopedAnalytics' => $superadmin->hasPermission(Permission::RoomView),
            ]);
        }

        $today = now()->toDateString();
        // Every figure follows its permission and the Agent scope; hidden figures are null.
        $scope = app(AgentScope::class);
        $rooms = $superadmin->hasPermission(Permission::RoomView) ? $scope->applyToRooms(Room::query(), $superadmin, Permission::RoomView) : null;
        $users = $superadmin->hasPermission(Permission::GlobalUserView);
        return response()->json([
            'data' => [
                'total_rooms' => $rooms ? (clone $rooms)->count() : null,
                'active_rooms' => $rooms ? (clone $rooms)->active()->count() : null,
                'total_global_users' => $users ? GlobalUser::where('status', '!=', GlobalUserStatus::Deleted->value)->count() : null,
                'active_global_users' => $users ? GlobalUser::active()->count() : null,
                // Only Agents in the viewer's scope; hidden (null) without `agent.view`.
                'total_admins' => $superadmin->hasPermission(Permission::AgentView) ? Admin::query()->visibleTo($superadmin)->count() : null,
                'active_campaigns' => $rooms ? $scope->applyToRoomOwned(Campaign::query(), $superadmin, Permission::RoomView)->where('status', CampaignStatus::Active)->count() : null,
                'orders_today' => $rooms ? $scope->applyToRoomOwned(Order::query(), $superadmin, Permission::RoomView)->whereDate('created_at', $today)->whereNot('status', OrderStatus::Cancelled)->count() : null,
                'system_health' => $superadmin->hasPermission(Permission::SettingsView) || $superadmin->hasPermission(Permission::QueueView) ? $health->snapshot() : null,
            ]
        ]);
    }

    /**
     * System-wide analytics (KPIs with period deltas, daily series, debt aging, room health, stuck campaigns).
     *
     * @param Request $request Current request; `?fresh=1` bypasses the analytics cache.
     * @param SuperadminDashboardService $dashboard Analytics aggregator.
     * @param PlatformDashboardService $platform Decides who sees the platform-wide figures.
     * @param AgentScope $scope Room scope of a restricted Superadmin.
     * @return JsonResponse Analytics payload under `data` (scoped to the viewer's rooms when restricted).
     */
    public function analytics(Request $request, SuperadminDashboardService $dashboard, PlatformDashboardService $platform, AgentScope $scope): JsonResponse
    {
        /** @var \App\Models\Superadmin $superadmin */
        $superadmin = $request->user('superadmin');
        if ($platform->canSeePlatformAnalytics($superadmin)) {
            return response()->json(['data' => $dashboard->analytics($request->boolean('fresh'))]);
        }

        // Restricted Superadmin: the same operational figures, counted only over the rooms in its scope.
        abort_unless($superadmin->hasPermission(Permission::RoomView), 403);
        $roomIds = $scope->adminIds($superadmin, Permission::RoomView) === null
            ? null
            : $scope->applyToRooms(Room::query(), $superadmin, Permission::RoomView)->pluck('rooms.id')->map(static fn ($id): int => (int) $id)->all();

        return response()->json(['data' => $dashboard->analyticsFor($roomIds, $superadmin->hasPermission(Permission::SecurityView), $request->boolean('fresh'))]);
    }

    /**
     * Secondary analytics (security trends and suspicious IPs, admin activity, order peak-hours heatmap).
     *
     * @param Request $request Current request; `?fresh=1` bypasses the insights cache.
     * @param SuperadminInsightsService $insights Insights aggregator.
     * @return JsonResponse Insights payload under `data`.
     */
    public function insights(Request $request, SuperadminInsightsService $insights): JsonResponse
    {
        return response()->json(['data' => $insights->insightsFor($request->user('superadmin'), $request->boolean('fresh'))]);
    }

    /**
     * Long-range trends (signup cohort retention, feedback ratings, contact topics, infrastructure history).
     *
     * @param Request $request Current request; `?fresh=1` bypasses the trends cache.
     * @param SuperadminTrendsService $trends Trends aggregator.
     * @return JsonResponse Trends payload under `data`.
     */
    public function trends(Request $request, SuperadminTrendsService $trends): JsonResponse
    {
        $this->ensurePlatformAnalytics($request);
        return response()->json(['data' => $trends->trends($request->boolean('fresh'))]);
    }

    /**
     * Platform-wide analytics are not scoped per Agent, so only unrestricted Superadmins may read them.
     *
     * @param Request $request Incoming request.
     * @return void
     */
    private function ensurePlatformAnalytics(Request $request): void
    {
        abort_unless(app(PlatformDashboardService::class)->canSeePlatformAnalytics($request->user('superadmin')), 403);
    }
}
