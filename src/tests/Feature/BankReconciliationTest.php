<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BankProvider;
use App\Enums\BankTransactionStatus;
use App\Enums\CampaignStatus;
use App\Enums\DebtStatus;
use App\Models\Admin;
use App\Models\BankTransaction;
use App\Models\Campaign;
use App\Models\Debt;
use App\Models\GlobalUser;
use App\Models\PaymentAccount;
use App\Models\Room;
use App\Models\RoomBankIntegration;
use App\Models\RoomUser;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Automatic reconciliation of SePay transfers with room debts.
 */
class BankReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private const API_KEY = 'sepay-secret-key-123';

    private Admin $admin;

    private Room $room;

    private RoomUser $member;

    private RoomBankIntegration $integration;

    private int $sepayId = 1000;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->admin = Admin::create(['name' => 'Bank Admin', 'email' => 'bank-admin@example.test', 'password' => 'secret123', 'status' => 'active']);
        $this->room = Room::create(['name' => 'Bank Room', 'slug' => 'bank-room', 'status' => 'active']);
        $this->admin->rooms()->attach($this->room);
        $this->member = $this->member('Nguyen Van B', 'nvb@example.test');
        PaymentAccount::create(['room_id' => $this->room->id, 'bank_code' => 'VCB', 'bank_name' => 'Vietcombank', 'account_number' => '0011223344', 'account_name' => 'DRINKFLOW', 'is_default' => true, 'status' => 'active']);
        $this->integration = RoomBankIntegration::create([
            'room_id' => $this->room->id,
            'provider' => BankProvider::SePay,
            'webhook_token' => str_repeat('a', 48),
            'api_key' => self::API_KEY,
            'is_active' => true,
        ]);
    }

    private function member(string $name, string $email): RoomUser
    {
        $user = GlobalUser::create(['name' => $name, 'normalized_name' => mb_strtoupper($name), 'email' => $email, 'status' => 'active']);

        return RoomUser::create([
            'room_id' => $this->room->id,
            'global_user_id' => $user->id,
            'user_code' => (string) \Illuminate\Support\Str::uuid(),
            'display_name' => $name,
            'normalized_name' => mb_strtoupper($name),
            'status' => 'active',
        ]);
    }

    private function debt(int $amount, ?RoomUser $member = null): Debt
    {
        $member ??= $this->member;
        $account = PaymentAccount::query()->where('room_id', $this->room->id)->firstOrFail();
        $campaign = Campaign::create(['room_id' => $this->room->id, 'name' => 'Cafe '.$amount, 'restaurant' => 'Cafe', 'status' => CampaignStatus::Closed, 'payment_account_id' => $account->id]);

        return Debt::create([
            'room_id' => $this->room->id,
            'campaign_id' => $campaign->id,
            'room_user_id' => $member->id,
            'original_amount' => $amount,
            'sponsor_amount' => 0,
            'paid_amount' => 0,
            'remaining_amount' => $amount,
            'status' => DebtStatus::Unpaid,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function webhook(string $content, int $amount, array $overrides = [], string $key = self::API_KEY): \Illuminate\Testing\TestResponse
    {
        $body = $overrides + [
            'id' => $this->sepayId++,
            'gateway' => 'Vietcombank',
            'transactionDate' => '2026-10-20 14:02:37',
            'accountNumber' => '0011223344',
            'subAccount' => null,
            'code' => null,
            'content' => $content,
            'transferType' => 'in',
            'transferAmount' => $amount,
            'accumulated' => 1000000,
            'referenceCode' => 'FT'.$this->sepayId,
            'description' => '',
        ];

        return $this->postJson(route('webhooks.bank', ['provider' => 'sepay', 'token' => $this->integration->webhook_token]), $body, ['Authorization' => 'Apikey '.$key]);
    }

    public function test_webhook_must_target_an_active_connection_with_the_right_api_key(): void
    {
        $this->webhook('X', 1000, [], 'wrong-key')->assertStatus(401);
        $this->postJson(route('webhooks.bank', ['provider' => 'sepay', 'token' => str_repeat('b', 48)]), ['id' => 1], ['Authorization' => 'Apikey '.self::API_KEY])->assertNotFound();
        $this->integration->update(['is_active' => false]);
        $this->webhook('X', 1000)->assertNotFound();

        $this->assertSame(0, BankTransaction::query()->count());
    }

    public function test_transfer_naming_a_debt_with_the_exact_amount_settles_it_once(): void
    {
        $debt = $this->debt(45000);
        $content = 'MBVCB.123456.'.str_replace('-', '', $debt->code).' NGUYENVANB chuyen tien';

        $this->webhook($content, 45000, ['id' => 777])->assertOk()->assertJson(['success' => true, 'status' => 'matched']);
        $this->webhook($content, 45000, ['id' => 777])->assertOk()->assertJson(['success' => true, 'status' => 'matched']);

        $this->assertSame(DebtStatus::Paid, $debt->fresh()->status);
        $this->assertSame(1, $debt->payments()->count());
        $transaction = BankTransaction::query()->sole();
        $this->assertSame($debt->id, $transaction->debt_id);
        $this->assertSame('debt', $transaction->reason);
        $this->assertNotNull($this->integration->fresh()->last_received_at);
    }

    public function test_wrong_amount_waits_for_the_admin_who_assigns_it_as_a_partial_payment(): void
    {
        $debt = $this->debt(45000);
        $this->webhook($debt->code, 20000)->assertOk()->assertJson(['status' => 'unmatched']);

        $transaction = BankTransaction::query()->sole();
        $this->assertSame('amount_mismatch', $transaction->reason);
        $this->assertSame($debt->id, $transaction->debt_id);
        $this->assertSame(DebtStatus::Unpaid, $debt->fresh()->status);

        $this->actingAs($this->admin, 'admin')->get(route('admin.bank-sync.index', $this->room->slug))
            ->assertOk()->assertSee('data-bank-unmatched="1"', false)->assertSee('data-bank-assign', false);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.bank-sync.assign', [$this->room->slug, $transaction->id]), ['debt_code' => $debt->code])
            ->assertSessionHasNoErrors();

        $this->assertSame(BankTransactionStatus::Manual, $transaction->fresh()->status);
        $this->assertSame(25000, (int) $debt->fresh()->remaining_amount);
        $this->assertSame(DebtStatus::Partial, $debt->fresh()->status);

        // Already resolved: a second decision is refused.
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.bank-sync.ignore', [$this->room->slug, $transaction->id]))
            ->assertSessionHasErrors('transaction');
    }

    public function test_pay_all_reference_approves_the_members_pending_payment_request(): void
    {
        $this->debt(30000);
        $this->debt(20000);
        $this->actingAs($this->member->globalUser, 'web')
            ->postJson(route('user.debts.confirm-payment', $this->room), ['transfer_content' => 'NGUYEN VAN B TV'.$this->member->id])
            ->assertOk();
        $request = Debt::paymentRequests()->where('room_id', $this->room->id)->sole();

        $this->webhook('NGUYENVANBTV'.$this->member->id, 50000)->assertOk()->assertJson(['status' => 'matched']);

        $this->assertSame(DebtStatus::Approved, $request->fresh()->status);
        $this->assertSame(0, (int) Debt::query()->where('room_user_id', $this->member->id)->sum('remaining_amount'));
        $this->assertSame($request->id, BankTransaction::query()->sole()->payment_request_id);
    }

    public function test_pay_all_reference_without_request_settles_every_payable_debt_when_the_total_matches(): void
    {
        $first = $this->debt(30000);
        $second = $this->debt(20000);
        $other = $this->debt(10000, $this->member('Tran Van C', 'tvc@example.test'));

        $this->webhook('NGUYEN VAN B TV'.$this->member->id, 49000)->assertJson(['status' => 'unmatched']);
        $this->webhook('NGUYEN VAN B TV'.$this->member->id, 50000)->assertJson(['status' => 'matched']);

        $this->assertSame(DebtStatus::Paid, $first->fresh()->status);
        $this->assertSame(DebtStatus::Paid, $second->fresh()->status);
        $this->assertSame(DebtStatus::Unpaid, $other->fresh()->status);
    }

    public function test_outgoing_transfers_and_other_accounts_are_ignored(): void
    {
        $debt = $this->debt(45000);

        $this->webhook($debt->code, 45000, ['transferType' => 'out'])->assertJson(['status' => 'ignored']);
        $this->webhook($debt->code, 45000, ['accountNumber' => '9999999999'])->assertJson(['status' => 'ignored']);
        $this->webhook('chuyen tien an trua', 45000)->assertJson(['status' => 'unmatched']);

        $this->assertSame(DebtStatus::Unpaid, $debt->fresh()->status);
        $this->assertSame(['outgoing', 'other_account', 'no_reference'], BankTransaction::query()->orderBy('id')->pluck('reason')->all());
    }

    public function test_admin_configures_the_connection_and_the_api_key_is_encrypted(): void
    {
        $room = Room::create(['name' => 'New Room', 'slug' => 'new-bank-room', 'status' => 'active']);
        $this->admin->rooms()->attach($room);

        $this->actingAs($this->admin, 'admin')->get(route('admin.bank-sync.index', $room->slug))
            ->assertOk()->assertSee('/webhooks/bank/sepay/', false);
        $this->actingAs($this->admin, 'admin')->put(route('admin.bank-sync.settings', $room->slug), ['is_active' => 1])
            ->assertSessionHasErrors('api_key');
        $this->actingAs($this->admin, 'admin')->put(route('admin.bank-sync.settings', $room->slug), ['is_active' => 1, 'api_key' => 'my-new-api-key'])
            ->assertSessionHasNoErrors();

        $integration = RoomBankIntegration::query()->where('room_id', $room->id)->sole();
        $this->assertTrue($integration->is_active);
        $this->assertSame('my-new-api-key', $integration->api_key);
        $this->assertNotSame('my-new-api-key', DB::table('room_bank_integrations')->where('id', $integration->id)->value('api_key'));

        $token = $integration->webhook_token;
        $this->actingAs($this->admin, 'admin')->put(route('admin.bank-sync.settings', $room->slug), ['is_active' => 1, 'regenerate_token' => 1])
            ->assertSessionHasNoErrors();
        $this->assertNotSame($token, $integration->fresh()->webhook_token);
        $this->assertSame('my-new-api-key', $integration->fresh()->api_key, 'A blank key keeps the stored one.');

        $other = Admin::create(['name' => 'Other', 'email' => 'other-bank@example.test', 'password' => 'secret123', 'status' => 'active']);
        $this->actingAs($other, 'admin')->get(route('admin.bank-sync.index', $room->slug))->assertRedirect();
    }
}
