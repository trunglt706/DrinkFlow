<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Order\ConfirmOrderPaymentAction;
use App\Enums\CampaignStatus;
use App\Enums\DebtStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\CampaignItem;
use App\Models\CampaignItemSize;
use App\Models\CampaignItemTopping;
use App\Models\Debt;
use App\Models\GlobalUser;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Room;
use App\Models\RoomUser;
use App\Services\Admin\AdminReportService;
use App\Services\Dashboard\AdminDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class AdminRoomUpgradeTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;
    private Room $room;
    private RoomUser $roomUser;
    private Campaign $campaign;
    private CampaignItem $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::create([
            'name' => 'Upgrade Admin',
            'email' => 'upgrade-admin@example.test',
            'password' => 'secret-password',
            'status' => 'active',
        ]);

        $this->room = Room::create([
            'name' => 'Room Upgrade Test',
            'slug' => 'room-upgrade-test',
            'status' => 'active',
            'created_by_admin_id' => $this->admin->id,
        ]);
        $this->admin->rooms()->attach($this->room->id);

        $globalUser = GlobalUser::create([
            'email' => 'user-upgrade@example.test',
            'name' => 'Global User Test',
            'password' => 'secret-password',
            'status' => 'active',
        ]);

        $this->roomUser = RoomUser::create([
            'room_id' => $this->room->id,
            'global_user_id' => $globalUser->id,
            'user_code' => 'USR-001',
            'display_name' => 'Member Test',
            'status' => 'active',
        ]);

        $this->campaign = Campaign::create([
            'room_id' => $this->room->id,
            'name' => 'Tra Sua Hom Nay',
            'restaurant' => 'Phuc Long',
            'status' => CampaignStatus::Active,
            'created_by_admin_id' => $this->admin->id,
            'started_at' => now(),
            'closed_at' => now()->addHours(2),
        ]);

        $this->item = CampaignItem::create([
            'campaign_id' => $this->campaign->id,
            'name' => 'Tra Sua Oolong',
            'base_price' => 35000,
            'status' => 'active',
        ]);
    }

    /**
     * UPG-08.1: Test deleting topping and size route parameters with Room bound.
     */
    public function test_upg_08_1_delete_topping_and_size_with_room_route_binding(): void
    {
        $topping = CampaignItemTopping::create([
            'campaign_item_id' => $this->item->id,
            'name' => 'Tran Chau Den',
            'price' => 5000,
            'status' => 'active',
        ]);

        $size = CampaignItemSize::create([
            'campaign_item_id' => $this->item->id,
            'name' => 'Size L',
            'price' => 10000,
            'status' => 'active',
        ]);

        // Delete topping
        $resTopping = $this->actingAs($this->admin, 'admin')
            ->deleteJson("/admin/{$this->room->slug}/campaigns/{$this->campaign->id}/items/{$this->item->id}/toppings/{$topping->id}");

        $resTopping->assertOk()->assertJsonPath('data.hidden', true);
        $this->assertEquals('hidden', $topping->fresh()->status);

        // Delete size
        $resSize = $this->actingAs($this->admin, 'admin')
            ->deleteJson("/admin/{$this->room->slug}/campaigns/{$this->campaign->id}/items/{$this->item->id}/sizes/{$size->id}");

        $resSize->assertOk()->assertJsonPath('data.hidden', true);
        $this->assertEquals('hidden', $size->fresh()->status);
    }

    /**
     * UPG-08.2: Test confirmPayment creates debt safely inside transaction and updates order status.
     */
    public function test_upg_08_2_confirm_payment_creates_debt_safely(): void
    {
        $order = Order::create([
            'code' => 'ORD-TEST-001',
            'room_id' => $this->room->id,
            'campaign_id' => $this->campaign->id,
            'room_user_id' => $this->roomUser->id,
            'subtotal' => 50000,
            'final_amount' => 50000,
            'sponsor_amount' => 0,
            'status' => OrderStatus::Submitted->value,
            'payment_status' => PaymentStatus::Pending->value,
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->postJson("/admin/{$this->room->slug}/orders/{$order->id}/confirm-payment");

        $response->assertOk();
        $this->assertEquals(PaymentStatus::Paid, $order->fresh()->payment_status);

        // Check debt was created and approved
        $debt = Debt::where('campaign_id', $this->campaign->id)
            ->where('room_user_id', $this->roomUser->id)
            ->first();

        $this->assertNotNull($debt);
        $this->assertEquals(DebtStatus::Paid, $debt->status);
        $this->assertEquals(0, $debt->remaining_amount);
        $this->assertEquals(50000, $debt->paid_amount);

        // Confirming again does not create duplicate debts
        $action = app(ConfirmOrderPaymentAction::class);
        $action->execute($order->fresh());

        $this->assertEquals(1, Debt::where('campaign_id', $this->campaign->id)
            ->where('room_user_id', $this->roomUser->id)
            ->count());
    }

    /**
     * UPG-08.3 & UPG-08.9: Test dashboard metrics computation and caching, and absence of dead code.
     */
    public function test_upg_08_3_dashboard_metrics_and_service(): void
    {
        $service = app(AdminDashboardService::class);
        $metrics = $service->getDashboardMetrics($this->room, true);

        $this->assertArrayHasKey('active_rooms', $metrics);
        $this->assertArrayHasKey('orders_today', $metrics);
        $this->assertArrayHasKey('weekly_trend', $metrics);
        $this->assertCount(7, $metrics['weekly_trend']);

        // Verify dead method getManagePageData does not exist
        $this->assertFalse(method_exists($service, 'getManagePageData'));
    }

    /**
     * UPG-08.4: Test /orders/aggregate requires campaign_id and validates room ownership.
     */
    public function test_upg_08_4_order_aggregate_requires_campaign_id(): void
    {
        // Missing campaign_id -> 422
        $resMissing = $this->actingAs($this->admin, 'admin')
            ->getJson("/admin/{$this->room->slug}/orders/aggregate");
        $resMissing->assertStatus(422)->assertJsonValidationErrors(['campaign_id']);

        // Other room's campaign -> 404
        $otherRoom = Room::create(['name' => 'Other Room', 'slug' => 'other-room', 'status' => 'active']);
        $otherCampaign = Campaign::create([
            'room_id' => $otherRoom->id,
            'name' => 'Other Campaign',
            'restaurant' => 'Other',
            'status' => CampaignStatus::Active,
        ]);

        $resOther = $this->actingAs($this->admin, 'admin')
            ->getJson("/admin/{$this->room->slug}/orders/aggregate?campaign_id={$otherCampaign->id}");
        $resOther->assertStatus(404);

        // Valid campaign -> 200
        $resValid = $this->actingAs($this->admin, 'admin')
            ->getJson("/admin/{$this->room->slug}/orders/aggregate?campaign_id={$this->campaign->id}");
        $resValid->assertOk()->assertJsonStructure(['data']);
    }

    /**
     * UPG-08.5: Test AdminReportService popular_drinks direct JOIN query.
     */
    public function test_upg_08_5_report_metrics_popular_drinks_direct_join(): void
    {
        $order = Order::create([
            'code' => 'ORD-TEST-002',
            'room_id' => $this->room->id,
            'campaign_id' => $this->campaign->id,
            'room_user_id' => $this->roomUser->id,
            'subtotal' => 45000,
            'final_amount' => 45000,
            'sponsor_amount' => 0,
            'status' => OrderStatus::Completed->value,
            'payment_status' => PaymentStatus::Paid->value,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'campaign_item_id' => $this->item->id,
            'item_name' => 'Tra Dao Cam Sa',
            'unit_price' => 45000,
            'quantity' => 2,
            'line_subtotal' => 90000,
        ]);

        $reportService = app(AdminReportService::class);
        $request = Request::create("/admin/{$this->room->slug}/reports", 'GET', ['tab' => 'products', 'period' => 'today']);

        $metrics = $reportService->getReportMetrics($request, $this->room);

        $this->assertArrayHasKey('popular_drinks', $metrics);
        $this->assertNotEmpty($metrics['popular_drinks']);
        $this->assertEquals('Tra Dao Cam Sa', $metrics['popular_drinks'][0]->item_name);
        $this->assertEquals(2, $metrics['popular_drinks'][0]->quantity);
    }

    /**
     * UPG-08.6: Test Audit logs page loads with cached filters and range query.
     */
    public function test_upg_08_6_audit_page_loads_with_cached_filters(): void
    {
        AuditLog::create([
            'room_id' => $this->room->id,
            'event' => 'order.created',
            'target_type' => 'order',
            'target_id' => 1,
            'actor_type' => 'user',
            'actor_id' => $this->roomUser->id,
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->get("/admin/{$this->room->slug}/audit/logs");

        $response->assertOk();
        $response->assertViewHas('events');
        $response->assertViewHas('targetTypes');
    }

    /**
     * UPG-08.7: Test date filter endpoints do not crash with invalid date strings.
     */
    public function test_upg_08_7_invalid_date_filters_handled_gracefully(): void
    {
        // Report page
        $resReport = $this->actingAs($this->admin, 'admin')
            ->get("/admin/{$this->room->slug}/reports/analytics?date_from=invalid-date&date_to=garbage");
        $resReport->assertOk();

        // Audit JSON
        $resAudit = $this->actingAs($this->admin, 'admin')
            ->getJson("/admin/{$this->room->slug}/audit/logs?date_from=invalid-date&date_to=garbage");
        $resAudit->assertOk();

        // Order JSON
        $resOrder = $this->actingAs($this->admin, 'admin')
            ->getJson("/admin/{$this->room->slug}/orders?from=invalid-date&to=garbage");
        $resOrder->assertOk();
    }

    /**
     * UPG-08.8: Test rate limiting on admin profile password update.
     */
    public function test_upg_08_8_admin_profile_password_rate_limit(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($this->admin, 'admin')
                ->patch('/admin/profile/password', [
                    'current_password' => 'wrong-password',
                    'password' => 'NewPassword123!',
                    'password_confirmation' => 'NewPassword123!',
                ]);
        }

        // 6th attempt should be throttled (429)
        $response = $this->actingAs($this->admin, 'admin')
            ->patch('/admin/profile/password', [
                'current_password' => 'wrong-password',
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ]);

        $response->assertStatus(429);
    }
}
