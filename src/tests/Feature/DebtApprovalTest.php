<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Debt\ApproveDebtPaymentAction;
use App\Enums\CampaignStatus;
use App\Enums\DebtStatus;
use App\Enums\PaymentStatus;
use App\Models\Admin;
use App\Models\Campaign;
use App\Models\Debt;
use App\Models\DebtPayment;
use App\Models\GlobalUser;
use App\Models\Order;
use App\Models\PaymentAccount;
use App\Models\Room;
use App\Models\RoomUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for the Admin Debt Payment Approval flow.
 *
 * Covers:
 *  - The debts ledger page renders the approve button for pending debts.
 *  - The approve API endpoint settles the debt and updates orders.
 *  - Double-approval is rejected with a validation error.
 */
class DebtApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Create an active Admin and a Room that it manages.
     *
     * @param string $email Admin email.
     * @param string $slug  Room slug.
     * @return array{0: Admin, 1: Room}
     */
    private function adminWithRoom(string $email, string $slug): array
    {
        $admin = Admin::create([
            'name'     => 'Test Admin',
            'email'    => $email,
            'password' => 'secret',
            'status'   => 'active',
        ]);
        $room = Room::create([
            'name'   => 'Approve Test Room',
            'slug'   => $slug,
            'status' => 'active',
        ]);
        $admin->rooms()->attach($room);

        return [$admin, $room];
    }

    /**
     * Create a RoomUser (and its backing GlobalUser) for a given room.
     *
     * @param Room   $room  Target room.
     * @param string $name  Display name.
     * @param string $email Email address.
     * @return RoomUser
     */
    private function member(Room $room, string $name, string $email): RoomUser
    {
        $user = GlobalUser::create([
            'name'            => $name,
            'normalized_name' => mb_strtoupper($name),
            'email'           => $email,
            'status'          => 'active',
        ]);

        return RoomUser::create([
            'room_id'         => $room->id,
            'global_user_id'  => $user->id,
            'user_code'       => 'APP-' . $user->id,
            'display_name'    => $name,
            'normalized_name' => mb_strtoupper($name),
            'status'          => 'active',
        ]);
    }

    /**
     * Create a Campaign for the given room.
     *
     * @param Room $room Target room.
     * @return Campaign
     */
    private function campaign(Room $room): Campaign
    {
        return Campaign::create([
            'room_id'    => $room->id,
            'name'       => 'Approval Campaign',
            'restaurant' => 'Test Cafe',
            'status'     => CampaignStatus::Closed,
        ]);
    }

    /**
     * Create a Debt record with the given status and remaining amount.
     *
     * @param Room      $room       Owner room.
     * @param Campaign  $campaign   Source campaign.
     * @param RoomUser  $roomUser   Debtor.
     * @param DebtStatus $status    Debt status.
     * @param int        $amount    Original / remaining amount.
     * @return Debt
     */
    private function debt(Room $room, Campaign $campaign, RoomUser $roomUser, DebtStatus $status, int $amount = 50000): Debt
    {
        return Debt::create([
            'room_id'          => $room->id,
            'campaign_id'      => $campaign->id,
            'room_user_id'     => $roomUser->id,
            'original_amount'  => $amount,
            'sponsor_amount'   => 0,
            'paid_amount'      => $status === DebtStatus::Paid ? $amount : 0,
            'remaining_amount' => $status === DebtStatus::Paid ? 0 : $amount,
            'status'           => $status,
        ]);
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    /**
     * The debts ledger page shows the approve button only for pending debts
     * and renders the new approval modal markup.
     */
    public function test_ledger_page_shows_approve_button_and_modal_for_pending_debt(): void
    {
        [$admin, $room] = $this->adminWithRoom('approve-page@example.test', 'approve-page-room');
        $campaign   = $this->campaign($room);
        $member     = $this->member($room, 'Nguyen Van A', 'nva@example.test');
        $pendingDebt = $this->debt($room, $campaign, $member, DebtStatus::Pending);

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.debts.page', $room->slug));

        $response->assertOk();

        // Approve button rendered with new JS call
        $response->assertSee('openApproveDebtModal', false);
        // Approve modal container present
        $response->assertSee('approve-debt-modal', false);
        // Confirm button
        $response->assertSee('approve-modal-confirm', false);
        // Status pending badge visible
        $response->assertSee('Nguyen Van A');
    }

    /**
     * Admin calling POST /debts/{debt}/approve on a pending debt:
     *  - Marks debt status as "paid" with remaining_amount = 0.
     *  - Creates a DebtPayment record.
     *  - Returns HTTP 200 with success message.
     */
    public function test_admin_can_approve_pending_debt_and_it_is_settled(): void
    {
        [$admin, $room] = $this->adminWithRoom('approve-api@example.test', 'approve-api-room');
        $campaign = $this->campaign($room);
        $member   = $this->member($room, 'Tran Thi B', 'ttb@example.test');
        $debt     = $this->debt($room, $campaign, $member, DebtStatus::Pending, 75000);

        $response = $this->actingAs($admin, 'admin')
            ->postJson(route('admin.debts.approve', [$room->slug, $debt->id]));

        $response->assertOk()
            ->assertJsonPath('data.status', DebtStatus::Paid->value)
            ->assertJsonPath('data.remaining_amount', 0);

        $this->assertDatabaseHas('debts', [
            'id'               => $debt->id,
            'status'           => DebtStatus::Paid->value,
            'remaining_amount' => 0,
        ]);

        $this->assertDatabaseHas('debt_payments', [
            'debt_id' => $debt->id,
            'amount'  => 75000,
        ]);

        $this->actingAs($member->globalUser, 'web')
            ->get(route('user.debts.index', $room))
            ->assertOk()
            ->assertSeeText(__('room.debts.status_paid'))
            ->assertDontSeeText(__('room.debts.status_unpaid'))
            ->assertSee('<meta property="og:image" content="'.asset('images/og-debt-payment.jpg').'">', false);
    }

    /**
     * The debt payment button carries its campaign ID so a "?campaign={id}" link (campaign-closed
     * notification) can open that campaign's payment modal.
     */
    public function test_debts_page_marks_pay_button_with_campaign_for_auto_open(): void
    {
        [, $room] = $this->adminWithRoom('auto-open@example.test', 'auto-open-room');
        $campaign = $this->campaign($room);
        $member   = $this->member($room, 'Tran Thi D', 'ttd@example.test');
        $debt = $this->debt($room, $campaign, $member, DebtStatus::Unpaid, 40000);

        $this->actingAs($member->globalUser, 'web')
            ->get(route('user.debts.index', ['room' => $room, 'campaign' => $campaign->code]))
            ->assertOk()
            ->assertSee('data-pay-debt-campaign="'.$campaign->code.'"', false)
            ->assertSee("get('campaign')", false)
            // The QR modal shows the debt code of the debt being paid.
            ->assertSee('data-qr-debt-code', false)
            ->assertSee("'".$debt->code."')", false);
    }

    /**
     * Paying all debts uses the member's name (ASCII, uppercase) plus the member reference TV{id}, which bank
     * reconciliation matches to the member; long names are shortened so the content stays within 25 characters.
     */
    public function test_pay_all_transfer_content_is_member_name_and_reference(): void
    {
        [, $room] = $this->adminWithRoom('pay-all-name@example.test', 'pay-all-name-room');
        $member = $this->member($room, 'Trần Thị Đào', 'ttdao@example.test');

        $data = app(\App\Services\Debt\UserRoomDebtService::class)->getDebtViewData($room, $member->fresh(), $member->globalUser);

        $this->assertSame('TRAN THI DAO TV'.$member->id, $data['payAllContent']);

        $vietQr = app(\App\Services\Payment\VietQrService::class);
        $long = $vietQr->payAllContent('Nguyễn Hoàng Phương Thảo Nguyên', 123456);
        $this->assertSame('NGUYEN HOANG PHU TV123456', $long);
        $this->assertSame(123456, $vietQr->memberReferenceIn('MBVCB.123.'.str_replace(' ', '', $long).'.CT tu 0123'));
    }

    /**
     * Approving a debt also synchronises the payment_status on all orders
     * belonging to the same campaign + member combination.
     */
    public function test_approve_syncs_order_payment_status_to_paid(): void
    {
        [$admin, $room] = $this->adminWithRoom('approve-order@example.test', 'approve-order-room');
        $campaign = $this->campaign($room);
        // "My Orders" only lists orders of the live campaign; reporting a payment needs a receiving account.
        $account = PaymentAccount::create([
            'room_id' => $room->id, 'bank_code' => 'VCB', 'bank_name' => 'Vietcombank',
            'account_number' => '0011223344', 'account_name' => 'DRINKFLOW', 'is_default' => true, 'status' => 'active',
        ]);
        $campaign->update(['status' => CampaignStatus::Active, 'payment_account_id' => $account->id]);
        $member   = $this->member($room, 'Le Van C', 'lvc@example.test');
        $debt     = $this->debt($room, $campaign, $member, DebtStatus::Pending, 30000);

        // Create a matching order for the same campaign + member
        $order = Order::create([
            'room_id'        => $room->id,
            'campaign_id'    => $campaign->id,
            'room_user_id'   => $member->id,
            'payment_status' => PaymentStatus::Unpaid->value,
            'subtotal'       => 30000,
            'final_amount'   => 30000,
            'status'         => 'completed',
        ]);

        $this->actingAs($member->globalUser, 'web')
            ->postJson(route('user.orders.confirm-payment', [$room, $order]))
            ->assertOk()
            ->assertJsonPath('payment_status', PaymentStatus::Pending->value)
            ->assertJsonPath('payment_confirmation.content', $order->fresh()->code ?: ('DF'.$order->id.' '.$member->user_code));

        $this->assertNotNull($debt->fresh()->payment_requested_at);
        $this->assertSame($order->fresh()->code ?: ('DF'.$order->id.' '.$member->user_code), $debt->fresh()->payment_content);

        $this->actingAs($admin, 'admin')
            ->postJson(route('admin.debts.approve', [$room->slug, $debt->id]))
            ->assertOk();

        $this->assertDatabaseHas('orders', [
            'id'             => $order->id,
            'payment_status' => PaymentStatus::Paid->value,
        ]);
        $this->assertDatabaseHas('debt_payments', [
            'debt_id' => $debt->id,
            'created_by_admin_id' => $admin->id,
        ]);

        $this->actingAs($member->globalUser, 'web')
            ->get(route('user.orders.index', $room))
            ->assertOk()
            ->assertSeeText(__('room.orders.payment_request_details_title'))
            ->assertSeeText(__('room.orders.payment_approval_info'))
            ->assertSee($order->fresh()->code ?: ('DF'.$order->id.' '.$member->user_code))
            ->assertSee($admin->name);
    }

    /**
     * When a debt has payment_content matching member's user_code (Pay All request),
     * approving it settles ALL pending debts for that member in the room.
     */
    public function test_admin_can_approve_pay_all_request_and_settle_all_pending_debts(): void
    {
        [$admin, $room] = $this->adminWithRoom('approve-all@example.test', 'approve-all-room');
        $campaign1 = $this->campaign($room);
        $campaign2 = Campaign::create([
            'room_id'    => $room->id,
            'name'       => 'Approval Campaign 2',
            'restaurant' => 'Test Restaurant 2',
            'status'     => CampaignStatus::Closed,
        ]);
        $member = $this->member($room, 'Hoang Van F', 'hvf@example.test');

        $debt1 = $this->debt($room, $campaign1, $member, DebtStatus::Pending, 50000);
        $debt1->update(['payment_content' => $member->user_code]);

        $debt2 = $this->debt($room, $campaign2, $member, DebtStatus::Pending, 30000);
        $debt2->update(['payment_content' => $member->user_code]);

        $response = $this->actingAs($admin, 'admin')
            ->postJson(route('admin.debts.approve', [$room->slug, $debt1->id]));

        $response->assertOk();

        $this->assertSame(0, (int) $debt1->fresh()->remaining_amount);
        $this->assertSame(DebtStatus::Paid, $debt1->fresh()->status);

        $this->assertSame(0, (int) $debt2->fresh()->remaining_amount);
        $this->assertSame(DebtStatus::Paid, $debt2->fresh()->status);

        $this->assertDatabaseHas('debt_payments', ['debt_id' => $debt1->id, 'amount' => 50000]);
        $this->assertDatabaseHas('debt_payments', ['debt_id' => $debt2->id, 'amount' => 30000]);
    }

    /**
     * Attempting to approve an already-settled debt returns a 422 validation error.
     */
    public function test_approving_already_settled_debt_returns_validation_error(): void
    {
        [$admin, $room] = $this->adminWithRoom('approve-dup@example.test', 'approve-dup-room');
        $campaign = $this->campaign($room);
        $member   = $this->member($room, 'Pham Thi D', 'ptd@example.test');
        $debt     = $this->debt($room, $campaign, $member, DebtStatus::Paid, 40000);

        $this->actingAs($admin, 'admin')
            ->postJson(route('admin.debts.approve', [$room->slug, $debt->id]))
            ->assertStatus(422);
    }

    /**
     * An admin from a different room cannot approve another room's debt.
     */
    public function test_admin_cannot_approve_debt_from_different_room(): void
    {
        [$admin, $room]             = $this->adminWithRoom('approve-sec@example.test', 'approve-sec-room');
        [$otherAdmin, $otherRoom]   = $this->adminWithRoom('other-admin@example.test', 'other-sec-room');
        $campaign  = $this->campaign($otherRoom);
        $member    = $this->member($otherRoom, 'Vo Van E', 'vve@example.test');
        $debt      = $this->debt($otherRoom, $campaign, $member, DebtStatus::Pending);

        // $admin is authenticated against $room, but the debt belongs to $otherRoom
        $this->actingAs($admin, 'admin')
            ->postJson(route('admin.debts.approve', [$room->slug, $debt->id]))
            ->assertNotFound();
    }
}
