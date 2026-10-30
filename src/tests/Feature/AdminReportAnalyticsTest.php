<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Enums\DebtStatus;
use App\Enums\OrderStatus;
use App\Models\Admin;
use App\Models\Campaign;
use App\Models\Debt;
use App\Models\GlobalUser;
use App\Models\PaymentAccount;
use App\Models\Room;
use App\Models\RoomUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminReportAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The "Top 5" drinks/stores panels must never load more than 5 rows, even when more
     * distinct items exist in the period.
     *
     * @return void
     */
    public function test_top_drinks_and_stores_are_limited_to_five(): void
    {
        $admin = $this->admin();
        $room = $this->roomFor($admin);
        $campaign = Campaign::create(['room_id' => $room->id, 'name' => 'Lunch', 'restaurant' => 'Cafe', 'status' => CampaignStatus::Active]);

        // A room only allows one order per member per campaign, so use a distinct member per item.
        foreach (range(1, 7) as $i) {
            $member = $this->member($room, "top5-member-{$i}@example.test", "TOP5{$i}");
            $order = $member->orders()->create([
                'room_id' => $room->id, 'campaign_id' => $campaign->id,
                'subtotal' => 10000, 'final_amount' => 10000, 'status' => OrderStatus::Submitted,
            ]);
            $order->items()->create([
                'item_name' => "Drink {$i}", 'unit_price' => 10000, 'quantity' => 8 - $i, 'line_subtotal' => 10000,
            ]);
        }

        $response = $this->actingAs($admin, 'admin')->getJson("/admin/{$room->id}/reports?period=all&tab=products");

        $response->assertOk();
        $this->assertCount(5, $response->json('data.popular_drinks'));
    }

    /**
     * The debts tab must only list members who still owe money, not everyone who ever had a debt.
     *
     * @return void
     */
    public function test_debts_tab_only_lists_users_with_outstanding_debt(): void
    {
        $admin = $this->admin();
        $room = $this->roomFor($admin);
        $campaign = Campaign::create(['room_id' => $room->id, 'name' => 'Lunch', 'restaurant' => 'Cafe', 'status' => CampaignStatus::Closed]);

        $settledMember = $this->member($room, 'settled@example.test', 'SETTLED');
        Debt::create([
            'room_id' => $room->id, 'campaign_id' => $campaign->id, 'room_user_id' => $settledMember->id,
            'original_amount' => 50000, 'paid_amount' => 50000, 'remaining_amount' => 0, 'status' => DebtStatus::Paid,
        ]);

        $owingMember = $this->member($room, 'owing@example.test', 'OWING');
        Debt::create([
            'room_id' => $room->id, 'campaign_id' => $campaign->id, 'room_user_id' => $owingMember->id,
            'original_amount' => 80000, 'paid_amount' => 0, 'remaining_amount' => 80000, 'status' => DebtStatus::Unpaid,
        ]);

        $response = $this->actingAs($admin, 'admin')->getJson("/admin/{$room->id}/reports?period=all&tab=debts");

        $response->assertOk()
            ->assertJsonCount(1, 'data.debts_by_user')
            ->assertJsonPath('data.debts_by_user.0.user_email', 'owing@example.test');
    }

    /**
     * The receiving-accounts tab groups order count, received and outstanding money by the
     * campaign's payment account; campaigns without an account fall into one "unassigned" row.
     *
     * @return void
     */
    public function test_accounts_tab_summarises_orders_and_money_per_payment_account(): void
    {
        $admin = $this->admin();
        $room = $this->roomFor($admin);
        $vcb = PaymentAccount::create(['room_id' => $room->id, 'bank_code' => 'VCB', 'bank_name' => 'Vietcombank', 'account_number' => '0011002233', 'account_name' => 'NGUYEN VAN A', 'is_default' => true, 'status' => 'active']);
        PaymentAccount::create(['room_id' => $room->id, 'bank_code' => 'TCB', 'bank_name' => 'Techcombank', 'account_number' => '1900123456', 'account_name' => 'TRAN THI B', 'status' => 'active']);

        $vcbCampaign = Campaign::create(['room_id' => $room->id, 'name' => 'Lunch', 'restaurant' => 'Cafe', 'status' => CampaignStatus::Closed, 'payment_account_id' => $vcb->id]);
        $noAccountCampaign = Campaign::create(['room_id' => $room->id, 'name' => 'Tea', 'restaurant' => 'Tea shop', 'status' => CampaignStatus::Closed]);

        $paid = $this->member($room, 'acc-paid@example.test', 'ACCPAID');
        $owing = $this->member($room, 'acc-owing@example.test', 'ACCOWE');
        $cancelled = $this->member($room, 'acc-cancel@example.test', 'ACCCAN');
        foreach ([[$paid, OrderStatus::Completed], [$owing, OrderStatus::Completed], [$cancelled, OrderStatus::Cancelled]] as [$member, $status]) {
            $member->orders()->create(['room_id' => $room->id, 'campaign_id' => $vcbCampaign->id, 'subtotal' => 30000, 'final_amount' => 30000, 'status' => $status]);
        }
        Debt::create(['room_id' => $room->id, 'campaign_id' => $vcbCampaign->id, 'room_user_id' => $paid->id, 'original_amount' => 30000, 'paid_amount' => 30000, 'remaining_amount' => 0, 'status' => DebtStatus::Paid]);
        Debt::create(['room_id' => $room->id, 'campaign_id' => $vcbCampaign->id, 'room_user_id' => $owing->id, 'original_amount' => 30000, 'paid_amount' => 10000, 'remaining_amount' => 20000, 'status' => DebtStatus::Partial]);

        $paid->orders()->create(['room_id' => $room->id, 'campaign_id' => $noAccountCampaign->id, 'subtotal' => 25000, 'final_amount' => 25000, 'status' => OrderStatus::Completed]);
        Debt::create(['room_id' => $room->id, 'campaign_id' => $noAccountCampaign->id, 'room_user_id' => $paid->id, 'original_amount' => 25000, 'paid_amount' => 0, 'remaining_amount' => 25000, 'status' => DebtStatus::Unpaid]);

        $response = $this->actingAs($admin, 'admin')->getJson("/admin/{$room->id}/reports?period=all&tab=accounts");

        // The unused TCB account has no activity and is not listed.
        $response->assertOk()->assertJsonCount(2, 'data.payment_accounts');
        $rows = collect($response->json('data.payment_accounts'))->keyBy(fn (array $row): string => (string) $row['payment_account_id']);

        $this->assertSame('VCB', $rows[(string) $vcb->id]['bank_code']);
        $this->assertStringNotContainsString('0011002233', (string) $rows[(string) $vcb->id]['account_number']);
        // The full number is only revealed on demand by the show/hide toggle.
        $this->assertSame('0011002233', $rows[(string) $vcb->id]['account_number_full']);
        $this->assertSame(2, $rows[(string) $vcb->id]['order_count']);
        $this->assertSame(40000, $rows[(string) $vcb->id]['total_received']);
        $this->assertSame(20000, $rows[(string) $vcb->id]['total_outstanding']);

        $this->assertSame(1, $rows['']['order_count']);
        $this->assertSame(0, $rows['']['total_received']);
        $this->assertSame(25000, $rows['']['total_outstanding']);

        $this->actingAs($admin, 'admin')->get("/admin/{$room->slug}/reports/analytics")
            ->assertOk()
            ->assertSee(__('admin.tab_payment_accounts_analytics'))
            ->assertSee('id="payment-accounts-list"', false);
    }

    /**
     * The sponsors tab must only list the campaigns' actual sponsors, never a member who simply
     * received a subsidy on their own order.
     *
     * @return void
     */
    public function test_sponsors_tab_only_lists_actual_campaign_sponsors(): void
    {
        $admin = $this->admin();
        $room = $this->roomFor($admin);
        $member = $this->member($room, 'beneficiary@example.test', 'BENEF');

        $unsponsoredCampaign = Campaign::create([
            'room_id' => $room->id, 'name' => 'No sponsor', 'restaurant' => 'Cafe',
            'status' => CampaignStatus::Active, 'sponsor_type' => 'none',
        ]);
        $member->orders()->create([
            'room_id' => $room->id, 'campaign_id' => $unsponsoredCampaign->id,
            'subtotal' => 90000, 'sponsor_amount' => 90000, 'final_amount' => 0, 'status' => OrderStatus::Submitted,
        ]);

        $sponsoredCampaign = Campaign::create([
            'room_id' => $room->id, 'name' => 'Sponsored', 'restaurant' => 'Cafe',
            'status' => CampaignStatus::Active, 'sponsor_type' => 'per_item', 'sponsor_name' => 'Công ty ABC',
        ]);
        $member->orders()->create([
            'room_id' => $room->id, 'campaign_id' => $sponsoredCampaign->id,
            'subtotal' => 50000, 'sponsor_amount' => 20000, 'final_amount' => 30000, 'status' => OrderStatus::Submitted,
        ]);

        $response = $this->actingAs($admin, 'admin')->getJson("/admin/{$room->id}/reports?period=all&tab=sponsors");

        $response->assertOk()
            ->assertJsonCount(1, 'data.sponsors_leaderboard')
            ->assertJsonPath('data.sponsors_leaderboard.0.user_name', 'Công ty ABC')
            ->assertJsonPath('data.sponsors_leaderboard.0.total_sponsored', 20000);
    }

    private function admin(string $email = 'report-admin@example.test'): Admin
    {
        return Admin::create(['name' => 'Report Admin', 'email' => $email, 'password' => Hash::make('secret'), 'status' => 'active']);
    }

    private function roomFor(Admin $admin, string $slug = 'report-analytics-room'): Room
    {
        $room = Room::create(['name' => 'Report Room', 'slug' => $slug, 'status' => 'active']);
        $admin->rooms()->attach($room);

        return $room;
    }

    private function member(Room $room, string $email, string $code): RoomUser
    {
        $user = GlobalUser::create(['name' => 'Report Member', 'normalized_name' => 'REPORT MEMBER', 'email' => $email, 'status' => 'active']);

        return RoomUser::create([
            'room_id' => $room->id, 'global_user_id' => $user->id,
            'user_code' => $code, 'display_name' => $user->name,
            'normalized_name' => strtoupper($user->name), 'status' => 'active',
            'joined_at' => now(),
        ]);
    }
}
