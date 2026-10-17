<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Enums\DebtStatus;
use App\Models\Admin;
use App\Models\Campaign;
use App\Models\Debt;
use App\Models\GlobalUser;
use App\Models\PaymentAccount;
use App\Models\Room;
use App\Models\RoomUser;
use App\Services\Debt\DebtCreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Consolidated debt payment requests: a parent `debts` row bundling a member's campaign debts.
 */
class DebtPaymentRequestTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    private Room $room;

    private RoomUser $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

        $this->admin = Admin::create([
            'name' => 'Request Admin',
            'email' => 'request-admin@example.test',
            'password' => 'secret',
            'status' => 'active',
        ]);
        $this->room = Room::create(['name' => 'Request Room', 'slug' => 'request-room', 'status' => 'active']);
        $this->admin->rooms()->attach($this->room);
        $this->member = $this->member($this->room, 'Le Van C', 'lvc@example.test');
    }

    /**
     * Create an active room member.
     *
     * @param Room $room Target room.
     * @param string $name Display name.
     * @param string $email Email address.
     * @return RoomUser Member.
     */
    private function member(Room $room, string $name, string $email): RoomUser
    {
        $user = GlobalUser::create([
            'name' => $name,
            'normalized_name' => mb_strtoupper($name),
            'email' => $email,
            'status' => 'active',
        ]);

        return RoomUser::create([
            'room_id' => $room->id,
            'global_user_id' => $user->id,
            'user_code' => 'REQ-'.$user->id,
            'display_name' => $name,
            'normalized_name' => mb_strtoupper($name),
            'status' => 'active',
        ]);
    }

    /**
     * Get (or create) the active receiving account of a room; paying without one is refused.
     *
     * @param int $roomId Owning room.
     * @return PaymentAccount Active account.
     */
    private function receivingAccount(int $roomId): PaymentAccount
    {
        return PaymentAccount::firstOrCreate(
            ['room_id' => $roomId, 'account_number' => '0011223344'],
            ['bank_code' => 'VCB', 'bank_name' => 'Vietcombank', 'account_name' => 'DRINKFLOW', 'is_default' => true, 'status' => 'active']
        );
    }

    /**
     * Create an unpaid campaign debt in its own closed campaign.
     *
     * @param int $amount Remaining amount.
     * @param RoomUser|null $member Debtor, defaults to the test member.
     * @return Debt Campaign debt.
     */
    private function debt(int $amount, ?RoomUser $member = null): Debt
    {
        $member ??= $this->member;
        $campaign = Campaign::create([
            'room_id' => $member->room_id,
            'name' => 'Campaign '.$amount,
            'restaurant' => 'Cafe',
            'status' => CampaignStatus::Closed,
            'payment_account_id' => $this->receivingAccount($member->room_id)->id,
        ]);

        return Debt::create([
            'room_id' => $member->room_id,
            'campaign_id' => $campaign->id,
            'room_user_id' => $member->id,
            'original_amount' => $amount,
            'sponsor_amount' => 0,
            'paid_amount' => 0,
            'remaining_amount' => $amount,
            'status' => DebtStatus::Unpaid,
        ]);
    }

    /**
     * Submit "pay all" as the test member.
     *
     * @return \Illuminate\Testing\TestResponse Response.
     */
    private function submitPayAll(): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->member->globalUser, 'web')
            ->postJson(route('user.debts.confirm-payment', $this->room), ['transfer_content' => 'REQ ALL']);
    }

    /**
     * Fetch the only payment request of the room.
     *
     * @return Debt Parent row.
     */
    private function onlyRequest(): Debt
    {
        return Debt::paymentRequests()->where('room_id', $this->room->id)->sole();
    }

    public function test_pay_all_bundles_eligible_debts_into_one_pending_request(): void
    {
        $d1 = $this->debt(500000);
        $d2 = $this->debt(300000);
        $d3 = $this->debt(700000);

        $this->submitPayAll()->assertOk()->assertJsonPath('success', true)->assertJsonPath('updated_count', 3);

        $request = $this->onlyRequest();
        $this->assertSame(DebtStatus::Pending, $request->status);
        $this->assertNull($request->campaign_id);
        $this->assertNull($request->parent_id);
        $this->assertSame(1500000, (int) $request->original_amount);
        $this->assertSame('REQ ALL', $request->payment_content);
        foreach ([$d1, $d2, $d3] as $debt) {
            $debt->refresh();
            $this->assertSame($request->id, $debt->parent_id);
            $this->assertSame(DebtStatus::Unpaid, $debt->status);
        }

        // The parent is a request, not extra debt: ledger queries and credit ignore it.
        $this->assertSame(3, Debt::query()->where('room_id', $this->room->id)->count());
        $this->assertSame(1500000, app(DebtCreditService::class)->used($this->member));
    }

    public function test_pay_all_needs_at_least_two_eligible_debts(): void
    {
        $this->submitPayAll()->assertStatus(422);
        $this->debt(200000);
        $this->submitPayAll()->assertStatus(422)->assertJsonValidationErrors('debt');

        $this->assertSame(0, Debt::paymentRequests()->count());
    }

    public function test_debts_created_after_submission_are_not_part_of_the_request(): void
    {
        $this->debt(500000);
        $this->debt(300000);
        $this->submitPayAll()->assertOk();
        $first = $this->onlyRequest();

        $d4 = $this->debt(400000);
        $this->assertNull($d4->fresh()->parent_id);
        // A second pay-all only sees D4, which alone is not enough.
        $this->submitPayAll()->assertStatus(422);
        $this->assertSame(1, Debt::paymentRequests()->count());
        $this->assertSame(1200000, app(DebtCreditService::class)->used($this->member));

        $d5 = $this->debt(100000);
        $this->submitPayAll()->assertOk()->assertJsonPath('updated_count', 2);
        $second = Debt::paymentRequests()->whereKeyNot($first->id)->sole();
        $this->assertSame(500000, (int) $second->original_amount);
        $this->assertSame($second->id, $d4->fresh()->parent_id);
        $this->assertSame($second->id, $d5->fresh()->parent_id);
        $this->assertSame(800000, (int) $first->fresh()->original_amount);
    }

    public function test_bundled_debts_are_locked_while_the_request_is_pending(): void
    {
        $d1 = $this->debt(500000);
        $this->debt(300000);
        $this->submitPayAll()->assertOk();

        // Single-debt confirmation by the member.
        $this->actingAs($this->member->globalUser, 'web')
            ->postJson(route('user.debts.confirm-payment', $this->room), ['debt_id' => $d1->id])
            ->assertStatus(422);
        // Admin records a payment on a bundled debt.
        $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.debts.pay', [$this->room->slug, $d1->id]), ['amount' => 100000, 'payment_method' => 'cash'])
            ->assertStatus(422);
        // Bulk settlement skips bundled debts instead of settling them.
        $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.debts.settle', $this->room->slug), ['room_user_id' => $this->member->id, 'payment_method' => 'cash'])
            ->assertStatus(422);

        $this->assertSame(500000, (int) $d1->fresh()->remaining_amount);
        $this->assertSame(0, DB::table('debt_payments')->count());
    }

    public function test_admin_approval_settles_exactly_the_bundled_debts(): void
    {
        $d1 = $this->debt(500000);
        $d2 = $this->debt(300000);
        $this->submitPayAll()->assertOk();
        $d4 = $this->debt(400000);
        $request = $this->onlyRequest();

        $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.debts.payment-requests.approve', [$this->room->slug, $request->id]))
            ->assertOk()
            ->assertJsonPath('data.status', DebtStatus::Approved->value);

        $request->refresh();
        $this->assertSame(DebtStatus::Approved, $request->status);
        $this->assertSame($this->admin->id, $request->reviewed_by_admin_id);
        foreach ([$d1, $d2] as $debt) {
            $debt->refresh();
            $this->assertSame(DebtStatus::Paid, $debt->status);
            $this->assertSame(0, (int) $debt->remaining_amount);
            $this->assertSame($request->id, $debt->parent_id);
            $this->assertDatabaseHas('debt_payments', ['debt_id' => $debt->id, 'amount' => $debt->paid_amount, 'reference' => $request->code]);
        }
        $this->assertSame(DebtStatus::Unpaid, $d4->fresh()->status);
        $this->assertSame(400000, app(DebtCreditService::class)->used($this->member));

        // A request is decided once.
        $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.debts.payment-requests.approve', [$this->room->slug, $request->id]))
            ->assertStatus(422);
        $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.debts.payment-requests.reject', [$this->room->slug, $request->id]), ['reason' => 'late'])
            ->assertStatus(422);
        $this->assertSame(2, DB::table('debt_payments')->count());
    }

    public function test_approval_is_refused_when_a_bundled_balance_changed(): void
    {
        $d1 = $this->debt(500000);
        $this->debt(300000);
        $this->submitPayAll()->assertOk();
        $request = $this->onlyRequest();

        // A legacy write path that bypasses the model lock.
        DB::table('debts')->where('id', $d1->id)->update(['remaining_amount' => 450000]);

        $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.debts.payment-requests.approve', [$this->room->slug, $request->id]))
            ->assertStatus(409);

        $this->assertSame(DebtStatus::Pending, $request->fresh()->status);
        $this->assertSame(0, DB::table('debt_payments')->count());
    }

    public function test_rejected_request_keeps_links_and_its_debts_can_be_paid_one_by_one(): void
    {
        $d1 = $this->debt(500000);
        $d2 = $this->debt(300000);
        $this->submitPayAll()->assertOk();
        $request = $this->onlyRequest();

        $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.debts.payment-requests.reject', [$this->room->slug, $request->id]), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');
        $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.debts.payment-requests.reject', [$this->room->slug, $request->id]), ['reason' => 'Transfer not found'])
            ->assertOk();

        $request->refresh();
        $this->assertSame(DebtStatus::Rejected, $request->status);
        $this->assertSame('Transfer not found', $request->review_reason);
        $this->assertSame($request->id, $d1->fresh()->parent_id);
        $this->assertSame(800000, app(DebtCreditService::class)->used($this->member));

        // Each rejected child can go through the single-debt flow.
        $this->actingAs($this->member->globalUser, 'web')
            ->postJson(route('user.debts.confirm-payment', $this->room), ['debt_id' => $d2->id, 'transfer_content' => 'D2'])
            ->assertOk();
        $this->assertSame(DebtStatus::Pending, $d2->fresh()->status);
        $this->assertSame($request->id, $d2->fresh()->parent_id);
    }

    public function test_debts_of_a_rejected_request_can_be_bundled_into_a_new_request(): void
    {
        $d1 = $this->debt(500000);
        $d2 = $this->debt(300000);
        $this->submitPayAll()->assertOk();
        $rejected = $this->onlyRequest();
        $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.debts.payment-requests.reject', [$this->room->slug, $rejected->id]), ['reason' => 'Transfer not found'])
            ->assertOk();

        $this->submitPayAll()->assertOk();

        $new = Debt::paymentRequests()->where('room_id', $this->room->id)->whereKeyNot($rejected->id)->sole();
        $this->assertSame(DebtStatus::Pending, $new->status);
        $this->assertSame(800000, (int) $new->original_amount);
        $this->assertSame($new->id, $d1->fresh()->parent_id);
        $this->assertSame($new->id, $d2->fresh()->parent_id);
        $this->assertSame(DebtStatus::Rejected, $rejected->fresh()->status);
    }

    public function test_request_from_another_room_cannot_be_reviewed(): void
    {
        $this->debt(500000);
        $this->debt(300000);
        $this->submitPayAll()->assertOk();
        $request = $this->onlyRequest();

        $otherRoom = Room::create(['name' => 'Other Room', 'slug' => 'other-request-room', 'status' => 'active']);
        $this->admin->rooms()->attach($otherRoom);

        $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.debts.payment-requests.approve', [$otherRoom->slug, $request->id]))
            ->assertNotFound();
        $this->assertSame(DebtStatus::Pending, $request->fresh()->status);
    }

    public function test_admin_menu_badge_counts_pending_payment_requests(): void
    {
        $this->debt(500000);
        $this->debt(300000);
        $this->submitPayAll()->assertOk();
        $single = $this->debt(200000);
        // Created after the bundle, so it can still go through the single-debt confirmation.
        $this->actingAs($this->member->globalUser, 'web')
            ->postJson(route('user.debts.confirm-payment', $this->room), ['debt_id' => $single->id, 'transfer_content' => 'ONE'])
            ->assertOk();

        // One consolidated request + one single confirmation; the bundled children are not counted again.
        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.debts.page', $this->room->slug))
            ->assertOk()
            ->assertSee('data-nav-pending-payments', false)
            ->assertSee('title="'.e(__('admin.pending_payment_requests_badge', ['count' => 2])).'"', false);

        $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.debts.payment-requests.reject', [$this->room->slug, $this->onlyRequest()->id]), ['reason' => 'Transfer not found'])
            ->assertOk();
        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.debts.page', $this->room->slug))
            ->assertSee('title="'.e(__('admin.pending_payment_requests_badge', ['count' => 1])).'"', false);
    }

    public function test_pages_show_the_request_to_member_and_admin(): void
    {
        $this->debt(500000);
        $this->debt(300000);
        $this->submitPayAll()->assertOk();
        $request = $this->onlyRequest();

        $this->actingAs($this->member->globalUser, 'web')
            ->get(route('user.debts.index', $this->room))
            ->assertOk()
            ->assertSeeText(__('room.debts.requests_title'))
            ->assertSeeText(__('room.debts.in_request_label', ['code' => $request->code]))
            ->assertSee('data-debt-summary', false);

        // The ledger lists the request itself in place of the two debts bundled into it.
        $html = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.debts.page', $this->room->slug))
            ->assertOk()
            ->assertSee('data-payment-request-row="'.$request->id.'"', false)
            ->assertSeeText(__('admin.payment_request_ledger_label', ['count' => 2]))
            ->assertSee('data-open-payment-request', false)
            ->assertSee('payment-request-modal', false)
            ->assertSee('data-pending-requests-notice', false)
            ->getContent();
        foreach ($request->children as $child) {
            $this->assertStringNotContainsString('data-debt-id="'.$child->id.'"', $html);
        }
    }

    public function test_ledger_summary_counts_a_pending_request_instead_of_its_debts(): void
    {
        $this->debt(500000);
        $this->debt(300000);
        $this->submitPayAll()->assertOk();
        $request = $this->onlyRequest();
        // Created after the request, so it is not bundled into it.
        $this->debt(100000);

        $summary = app(\App\Services\Admin\AdminDebtService::class)->getLedgerSummary($this->room);

        // Owed = the pending request amount + the debt outside any request, each counted once.
        $this->assertSame(800000, (int) $request->original_amount);
        $this->assertSame(900000, $summary['storeDebtPending']);
    }
}
