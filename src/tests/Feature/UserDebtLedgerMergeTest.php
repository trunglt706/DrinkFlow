<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Enums\DebtStatus;
use App\Models\Campaign;
use App\Models\Debt;
use App\Models\GlobalUser;
use App\Models\PaymentAccount;
use App\Models\Room;
use App\Models\RoomUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The member debt page shows one merged ledger: top-level debts plus consolidated payment requests.
 * Debts bundled into a request (parent_id set) must never appear as their own row.
 */
class UserDebtLedgerMergeTest extends TestCase
{
    use RefreshDatabase;

    private Room $room;

    private RoomUser $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->room = Room::create(['name' => 'Ledger Room', 'slug' => 'ledger-room', 'status' => 'active']);
        $this->member = $this->member($this->room, 'Ledger Member', 'ledger@example.test');
    }

    /**
     * Render the merged ledger the way the debt page does: same services, same component.
     *
     * @param Room $room Room being viewed.
     * @param RoomUser $member Member viewing the page.
     * @return string Rendered component HTML.
     */
    private function renderLedger(Room $room, RoomUser $member): string
    {
        $roomDebts = app(\App\Services\Debt\UserRoomDebtService::class);
        $requests = app(\App\Services\Debt\DebtPaymentRequestService::class);
        $data = $roomDebts->getDebtViewData($room, $member, $member->globalUser);
        $requestData = $requests->memberViewData($room, $member, $data['ledgerDebts']->getCollection());

        // Explicit props, mirroring the markup the debt page will use.
        return view('components.room.debt-ledger', [
            'debts' => $data['ledgerDebts'],
            'paymentRequests' => $requestData['paymentRequests'],
            'paymentRequestDetails' => $requestData['paymentRequestDetails'],
            'transferContents' => $data['transferContents'],
            'qrPayloads' => $data['qrPayloads'],
            'qrAccounts' => $data['qrAccounts'],
        ])->render();
    }

    /**
     * The ledger lists top-level debts, and the payment request that bundles them.
     *
     * @return void
     */
    public function test_ledger_shows_top_level_debts_and_the_request(): void
    {
        $first = $this->debt(500000);
        $second = $this->debt(300000);
        $request = $this->bundle([$first, $second]);

        $html = $this->renderLedger($this->room, $this->member);

        $this->assertStringContainsString($first->code, $html);
        $this->assertStringContainsString($second->code, $html);
        $this->assertStringContainsString($request->code, $html);
        $this->assertStringContainsString(__('room.debts.requests_title'), $html);
        // Blade escapes `&` when echoing, so compare against the escaped label.
        $this->assertStringContainsString(e(__('room.debts.merged_ledger_badge')), $html);
    }

    /**
     * A bundled debt is not rendered as an independent ledger row: it has no payment button of its own.
     *
     * @return void
     */
    public function test_bundled_debt_has_no_own_payment_action(): void
    {
        $first = $this->debt(500000);
        $second = $this->debt(300000);
        $request = $this->bundle([$first, $second]);

        $html = $this->renderLedger($this->room, $this->member);

        // A debt carrying its own QR action exposes data-pay-debt-code; a bundled one must not.
        $this->assertStringNotContainsString('data-pay-debt-code="'.$first->code.'"', $html);
        $this->assertStringNotContainsString('data-pay-debt-code="'.$second->code.'"', $html);

        // Instead the row reports that the debt sits inside the request.
        $this->assertStringContainsString(
            __('room.debts.in_request_label', ['code' => $request->code]),
            $html,
        );
    }

    /**
     * The ledger carries the detail payload of every request, and the hooks the modal uses.
     *
     * @return void
     */
    public function test_ledger_carries_request_detail_payload_for_the_modal(): void
    {
        $first = $this->debt(500000);
        $second = $this->debt(300000);
        $request = $this->bundle([$first, $second]);

        $html = $this->renderLedger($this->room, $this->member);

        $this->assertStringContainsString('openDetail(', $html);
        $this->assertStringContainsString($request->code, $html);
        $this->assertStringContainsString(__('room.debts.request_detail_title'), $html);
        $this->assertStringContainsString(__('room.debts.bundled_debts_heading'), $html);
        $this->assertStringContainsString(__('room.debts.view_request_detail'), $html);
    }

    /**
     * The detail payload is built server-side with status, amount and children of the request.
     *
     * @return void
     */
    public function test_request_detail_payload_is_complete(): void
    {
        $first = $this->debt(120000);
        $second = $this->debt(80000);
        $request = $this->bundle([$first, $second]);

        $payloads = app(\App\Services\Debt\DebtPaymentRequestService::class)
            ->memberViewData($this->room, $this->member, collect())['paymentRequestDetails'];

        $this->assertArrayHasKey($request->code, $payloads);
        $detail = $payloads[$request->code];

        $this->assertSame($request->code, $detail['code']);
        $this->assertSame('pending', $detail['status']);
        $this->assertSame(__('room.debts.request_status_pending'), $detail['status_label']);
        $this->assertSame(200000, $detail['amount']);
        $this->assertSame(2, $detail['debts_count']);
        $this->assertCount(2, $detail['debts']);
        $this->assertSame(
            [$first->code, $second->code],
            array_column($detail['debts'], 'code'),
        );
    }

    /**
     * After a rejection the request stays listed, and its debts are ordinary payable rows again.
     *
     * @return void
     */
    public function test_rejected_request_debts_are_listed_as_payable_rows_again(): void
    {
        $first = $this->debt(70000);
        $second = $this->debt(60000);
        $request = $this->bundle([$first, $second]);
        Debt::paymentRequests()->whereKey($request->id)->update([
            'status' => DebtStatus::Rejected->value,
            'review_reason' => 'Transfer not found',
        ]);

        $html = $this->renderLedger($this->room, $this->member);

        // The rejected request is still shown, with its reason and the children it kept.
        $this->assertStringContainsString($request->code, $html);
        $this->assertStringContainsString('Transfer not found', $html);
        $this->assertStringContainsString($first->code, $html);
        $this->assertStringContainsString($second->code, $html);

        // Its debts come back as top-level rows, each with its own QR action (once, not inside the request).
        $this->assertSame(1, substr_count($html, 'data-pay-debt-code="'.$first->code.'"'));
        $this->assertSame(1, substr_count($html, 'data-pay-debt-code="'.$second->code.'"'));

        // The pay-all total counts them again so they can be bundled into a new request.
        $data = app(\App\Services\Debt\UserRoomDebtService::class)->getDebtViewData($this->room, $this->member, $this->member->globalUser);
        $this->assertSame(130000, $data['totalPayableAmount']);
    }

    /**
     * The ledger of one member never leaks another member's debts.
     *
     * @return void
     */
    public function test_ledger_does_not_leak_other_members_debts(): void
    {
        $other = $this->member($this->room, 'Other Member', 'other@example.test');
        $foreign = $this->debt(900000, $other);
        $mine = $this->debt(50000);

        $html = $this->actingAs($this->member->globalUser, 'web')
            ->get(route('user.debts.index', $this->room))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString($mine->code, $html);
        $this->assertStringNotContainsString($foreign->code, $html);
    }

    /**
     * Create an active room member.
     *
     * @param Room $room Target room.
     * @param string $name Display name.
     * @param string $email Account email.
     * @return RoomUser Created membership.
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
            'user_code' => 'LED-'.$user->id,
            'display_name' => $name,
            'normalized_name' => mb_strtoupper($name),
            'status' => 'active',
        ]);
    }

    /**
     * Get (or create) the room's active receiving account.
     *
     * @param int $roomId Owning room.
     * @return PaymentAccount Active account.
     */
    private function receivingAccount(int $roomId): PaymentAccount
    {
        return PaymentAccount::firstOrCreate(
            ['room_id' => $roomId, 'is_default' => true],
            [
                'bank_code' => 'VCB',
                'bank_name' => 'Vietcombank',
                'account_number' => '0011002233',
                'account_name' => 'LEDGER ROOM',
                'status' => 'active',
            ],
        );
    }

    /**
     * Create a campaign debt for a member.
     *
     * @param int $amount Outstanding amount.
     * @param RoomUser|null $member Debt owner.
     * @return Debt Created debt.
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
     * Bundle debts into one pending payment request, as the pay-all flow does.
     *
     * @param array<int, Debt> $debts Debts to bundle.
     * @return Debt The created request.
     */
    private function bundle(array $debts): Debt
    {
        $total = array_sum(array_map(static fn (Debt $debt): int => (int) $debt->remaining_amount, $debts));

        $request = Debt::create([
            'room_id' => $this->room->id,
            'campaign_id' => null,
            'room_user_id' => $this->member->id,
            'original_amount' => $total,
            'sponsor_amount' => 0,
            'paid_amount' => 0,
            'remaining_amount' => $total,
            'status' => DebtStatus::Pending,
            'payment_requested_at' => now(),
            'payment_content' => 'LEDGER BUNDLE',
        ]);

        foreach ($debts as $debt) {
            // `parent_id` is deliberately not fillable, so children are linked through the query
            // builder exactly as SubmitDebtPaymentRequestAction does.
            Debt::query()->whereKey($debt->id)->update(['parent_id' => $request->id]);
        }

        return $request->refresh();
    }
}
