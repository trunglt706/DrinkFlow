<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Enums\DebtStatus;
use App\Enums\RoomFundTransactionType;
use App\Exports\RoomFundLedgerExport;
use App\Models\Admin;
use App\Models\Campaign;
use App\Models\Debt;
use App\Models\GlobalUser;
use App\Models\Room;
use App\Models\RoomFundTransaction;
use App\Models\RoomUser;
use App\Services\RoomFund\RoomFundService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

/**
 * Room fund ledger: append-only entries, balance never below zero, debts paid with the fund.
 */
class RoomFundLedgerTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    private Room $room;

    private RoomUser $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->admin = Admin::create(['name' => 'Fund Admin', 'email' => 'fund-admin@example.test', 'password' => 'secret123', 'status' => 'active']);
        $this->room = Room::create(['name' => 'Fund Room', 'slug' => 'fund-room', 'status' => 'active']);
        $this->admin->rooms()->attach($this->room);
        $user = GlobalUser::create(['name' => 'Pham Van E', 'normalized_name' => 'PHAM VAN E', 'email' => 'pve@example.test', 'status' => 'active']);
        $this->member = RoomUser::create([
            'room_id' => $this->room->id,
            'global_user_id' => $user->id,
            'user_code' => 'FUND-1',
            'display_name' => 'Pham Van E',
            'normalized_name' => 'PHAM VAN E',
            'status' => 'active',
        ]);
    }

    private function store(array $data): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin, 'admin')->post(route('admin.fund.store', $this->room->slug), $data);
    }

    private function debt(int $amount): Debt
    {
        $campaign = Campaign::create(['room_id' => $this->room->id, 'name' => 'Cafe '.$amount, 'restaurant' => 'Cafe', 'status' => CampaignStatus::Closed]);

        return Debt::create([
            'room_id' => $this->room->id,
            'campaign_id' => $campaign->id,
            'room_user_id' => $this->member->id,
            'original_amount' => $amount,
            'sponsor_amount' => 0,
            'paid_amount' => 0,
            'remaining_amount' => $amount,
            'status' => DebtStatus::Unpaid,
        ]);
    }

    private function balance(): int
    {
        return app(RoomFundService::class)->balance($this->room);
    }

    public function test_admin_records_deposits_expenses_and_adjustments(): void
    {
        $this->store(['type' => 'deposit', 'amount' => 200000, 'room_user_id' => $this->member->id])
            ->assertRedirect(route('admin.fund.index', $this->room->slug))->assertSessionHasNoErrors();
        $this->store(['type' => 'expense', 'amount' => 50000, 'note' => 'Banh sinh nhat'])->assertSessionHasNoErrors();
        $this->store(['type' => 'adjustment', 'amount' => -10000, 'note' => 'Sua nhap sai'])->assertSessionHasNoErrors();

        $this->assertSame(140000, $this->balance());
        $this->assertSame([200000, -50000, -10000], RoomFundTransaction::query()->orderBy('id')->pluck('amount')->all());
        $this->assertSame([200000, 150000, 140000], RoomFundTransaction::query()->orderBy('id')->pluck('balance_after')->all());
        $this->assertSame($this->member->id, RoomFundTransaction::query()->where('type', 'deposit')->value('room_user_id'));
        $this->assertDatabaseHas('audit_logs', ['event' => 'room_fund.expense', 'room_id' => $this->room->id]);

        $this->actingAs($this->admin, 'admin')->get(route('admin.fund.index', $this->room->slug))
            ->assertOk()
            ->assertSee('data-fund-balance="140000"', false)
            ->assertSee('Banh sinh nhat');
    }

    public function test_balance_never_goes_below_zero_and_input_is_validated(): void
    {
        $this->store(['type' => 'deposit', 'amount' => 30000]);

        $this->store(['type' => 'expense', 'amount' => 30001, 'note' => 'Qua tay'])->assertSessionHasErrors('amount');
        $this->store(['type' => 'expense', 'amount' => 1000])->assertSessionHasErrors('note');
        $this->store(['type' => 'deposit', 'amount' => -5000])->assertSessionHasErrors('amount');
        $this->store(['type' => 'debt_payment', 'amount' => 5000, 'note' => 'x'])->assertSessionHasErrors('type');
        $this->store(['type' => 'adjustment', 'amount' => 0, 'note' => 'x'])->assertSessionHasErrors('amount');

        $otherRoom = Room::create(['name' => 'Other', 'slug' => 'other-fund-room', 'status' => 'active']);
        $outsider = RoomUser::create(['room_id' => $otherRoom->id, 'global_user_id' => $this->member->global_user_id, 'user_code' => 'OUT-1', 'display_name' => 'Out', 'normalized_name' => 'OUT', 'status' => 'active']);
        $this->store(['type' => 'deposit', 'amount' => 5000, 'room_user_id' => $outsider->id])->assertSessionHasErrors('room_user_id');

        $this->assertSame(30000, $this->balance());
        $this->assertSame(1, RoomFundTransaction::query()->count());
    }

    public function test_entries_are_append_only(): void
    {
        $this->store(['type' => 'deposit', 'amount' => 30000]);
        $entry = RoomFundTransaction::query()->sole();

        $this->expectException(LogicException::class);
        $entry->update(['amount' => 1]);
    }

    public function test_paying_a_debt_with_the_fund_takes_it_out_of_the_ledger(): void
    {
        $this->store(['type' => 'deposit', 'amount' => 50000]);
        $debt = $this->debt(40000);

        $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.debts.pay', [$this->room->slug, $debt->id]), ['amount' => 40000, 'payment_method' => 'room_fund'])
            ->assertOk();

        $this->assertSame(10000, $this->balance());
        $entry = RoomFundTransaction::query()->where('type', RoomFundTransactionType::DebtPayment->value)->sole();
        $this->assertSame(-40000, $entry->amount);
        $this->assertSame($debt->id, $entry->debt_id);
        $this->assertSame($this->member->id, $entry->room_user_id);
        $this->assertNotNull($entry->debt_payment_id);

        // Other payment methods do not touch the fund.
        $cash = $this->debt(20000);
        $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.debts.pay', [$this->room->slug, $cash->id]), ['amount' => 20000, 'payment_method' => 'cash'])
            ->assertOk();
        $this->assertSame(10000, $this->balance());
    }

    public function test_a_debt_payment_the_fund_cannot_cover_is_rolled_back(): void
    {
        $this->store(['type' => 'deposit', 'amount' => 10000]);
        $debt = $this->debt(40000);

        $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.debts.pay', [$this->room->slug, $debt->id]), ['amount' => 40000, 'payment_method' => 'room_fund'])
            ->assertStatus(422)->assertJsonValidationErrors('amount');

        $this->assertSame(40000, (int) $debt->fresh()->remaining_amount);
        $this->assertSame(0, $debt->payments()->count());
        $this->assertSame(10000, $this->balance());
    }

    public function test_confirming_a_campaign_paid_with_the_fund_charges_every_debt(): void
    {
        $this->store(['type' => 'deposit', 'amount' => 100000]);
        $debt = $this->debt(30000);

        $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.campaigns.confirm-debts-paid', [$this->room->slug, $debt->campaign_id]), ['payment_method' => 'room_fund'])
            ->assertOk();

        $this->assertSame(70000, $this->balance());
        $this->assertSame(DebtStatus::Paid, $debt->fresh()->status);
    }

    public function test_export_and_access_control(): void
    {
        $this->store(['type' => 'deposit', 'amount' => 30000, 'note' => '=HYPERLINK("x")']);
        Excel::fake();

        $this->actingAs($this->admin, 'admin')->get(route('admin.fund.export', $this->room->slug))->assertOk();
        Excel::matchByRegex();
        Excel::assertDownloaded('/^drinkflow-fund-room-fund-.*\.xlsx$/', function (RoomFundLedgerExport $export): bool {
            $row = $export->map($export->query()->firstOrFail());

            return $row[2] === 30000 && $row[3] === 30000 && $row[6] === '\'=HYPERLINK("x")';
        });

        $other = Admin::create(['name' => 'Other Admin', 'email' => 'other-fund@example.test', 'password' => 'secret123', 'status' => 'active']);
        $this->actingAs($other, 'admin')->get(route('admin.fund.index', $this->room->slug))->assertRedirect();
        $this->actingAs($other, 'admin')->post(route('admin.fund.store', $this->room->slug), ['type' => 'deposit', 'amount' => 1])->assertRedirect();
        $this->assertSame(1, RoomFundTransaction::query()->count());
    }
}
