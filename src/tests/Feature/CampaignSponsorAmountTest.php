<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Campaign\CloseCampaignAction;
use App\Actions\Order\CreateOrderAction;
use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\CampaignItem;
use App\Models\Debt;
use App\Models\GlobalUser;
use App\Models\Room;
use App\Models\RoomUser;
use App\Services\Admin\AdminCampaignDetailService;
use App\Services\Reporting\SponsorLeaderboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The sponsor amount shown for a fully sponsored campaign leaves the "trả riêng" lines out and is
 * exactly what each sponsor owes once the campaign is closed.
 */
class CampaignSponsorAmountTest extends TestCase
{
    use RefreshDatabase;

    private Room $room;

    private RoomUser $member;

    private RoomUser $sponsorA;

    private RoomUser $sponsorB;

    private Campaign $campaign;

    protected function setUp(): void
    {
        parent::setUp();
        $this->room = Room::create(['name' => 'Sponsor Room', 'slug' => 'sponsor-room']);
        $this->member = $this->roomUser('Member');
        $this->sponsorA = $this->roomUser('Sponsor A');
        $this->sponsorB = $this->roomUser('Sponsor B');
        $this->campaign = Campaign::create([
            'room_id' => $this->room->id,
            'name' => 'Full sponsor',
            'restaurant' => 'Cafe',
            'status' => CampaignStatus::Active,
            'sponsor_type' => Campaign::SPONSOR_TYPE_FULL,
            'sponsor_allocations' => [
                ['room_user_id' => $this->sponsorA->id, 'percentage' => 50],
                ['room_user_id' => $this->sponsorB->id, 'percentage' => 50],
            ],
            // Odd fee: the halves do not split evenly, so each half rounds up, so the first sponsor absorbs the -1 remainder.
            'delivery_fee' => 15001,
            'self_paid_price_basis' => Campaign::SELF_PAID_PRICE_BASIS_ORIGINAL,
        ]);

        $coffee = CampaignItem::create(['campaign_id' => $this->campaign->id, 'name' => 'Coffee', 'normalized_name' => 'COFFEE', 'base_price' => 30000, 'status' => 'active']);
        $cake = CampaignItem::create(['campaign_id' => $this->campaign->id, 'name' => 'Cake', 'normalized_name' => 'CAKE', 'base_price' => 20000, 'status' => 'active']);
        app(CreateOrderAction::class)->execute($this->campaign, $this->member, [
            'items' => [
                ['item_id' => $coffee->id, 'quantity' => 1, 'is_self_paid' => false],
                ['item_id' => $cake->id, 'quantity' => 1, 'is_self_paid' => true],
            ],
        ]);
    }

    private function roomUser(string $name): RoomUser
    {
        $user = GlobalUser::create(['name' => $name, 'normalized_name' => mb_strtoupper($name), 'email' => uniqid('sp-', true).'@example.test']);

        return RoomUser::create(['room_id' => $this->room->id, 'global_user_id' => $user->id, 'user_code' => uniqid('U', false), 'display_name' => $name, 'normalized_name' => mb_strtoupper($name), 'status' => 'active']);
    }

    public function test_detail_and_close_summary_exclude_self_paid_lines(): void
    {
        $service = app(AdminCampaignDetailService::class);

        // Coffee 30,000 + the whole fee 15,001 (original basis); the 20,000 cake is the member's own.
        $view = $service->getCampaignViewData($this->room, $this->campaign->fresh());
        $this->assertSame(45001, $view['sponsorSubsidy']);
        $this->assertSame([22500, 22501], $view['sponsorsList']->pluck('amount')->all());
        $this->assertSame([50, 50], $view['sponsorsList']->pluck('percentage')->all());

        $summary = $service->getCloseSummary($this->room, $this->campaign);
        $this->assertSame(45001, $summary['sponsor_total']);
        $this->assertSame(20000, $summary['final_total']);
    }

    public function test_sponsor_debts_match_the_displayed_amounts_and_add_up(): void
    {
        $displayed = app(AdminCampaignDetailService::class)
            ->getCampaignViewData($this->room, $this->campaign->fresh())['sponsorsList']->pluck('amount')->all();

        app(CloseCampaignAction::class)->execute($this->campaign);

        $debtA = Debt::query()->where('campaign_id', $this->campaign->id)->where('room_user_id', $this->sponsorA->id)->firstOrFail();
        $debtB = Debt::query()->where('campaign_id', $this->campaign->id)->where('room_user_id', $this->sponsorB->id)->firstOrFail();
        $this->assertSame($displayed, [(int) $debtA->remaining_amount, (int) $debtB->remaining_amount]);

        foreach ([$debtA, $debtB] as $debt) {
            // SetDebtStatusAction derives the paid amount from this identity.
            $this->assertSame((int) $debt->remaining_amount, (int) $debt->original_amount + (int) $debt->adjustment_amount - (int) $debt->sponsor_amount);
        }

        $this->assertDatabaseHas('debts', ['campaign_id' => $this->campaign->id, 'room_user_id' => $this->member->id, 'remaining_amount' => 20000]);

        // The closed campaign still shows the same sponsor figures.
        $after = app(AdminCampaignDetailService::class)->getCampaignViewData($this->room, $this->campaign->fresh());
        $this->assertSame($displayed, $after['sponsorsList']->pluck('amount')->all());
    }

    /**
     * Production case: 2 sponsors at 50% of a 250,000 gross total showed 124,999 / 125,000 while the campaign
     * was running (125,000 each once closed). The delivery fee split between the orders rounded each part, so
     * the parts summed to 9,999 instead of 10,000 and the pool lost 1đ.
     */
    public function test_two_half_sponsors_of_an_even_total_get_equal_shares_before_and_after_close(): void
    {
        $campaign = Campaign::create([
            'room_id' => $this->room->id,
            'name' => 'Half and half',
            'restaurant' => 'Cafe',
            'status' => CampaignStatus::Active,
            'sponsor_type' => Campaign::SPONSOR_TYPE_FULL,
            'sponsor_allocations' => [
                ['room_user_id' => $this->sponsorA->id, 'percentage' => 50],
                ['room_user_id' => $this->sponsorB->id, 'percentage' => 50],
            ],
            // 3 equal orders: 10,000 / 3 = 3,333.33 per order.
            'delivery_fee' => 10000,
            'self_paid_price_basis' => Campaign::SELF_PAID_PRICE_BASIS_ORIGINAL,
        ]);
        $tea = CampaignItem::create(['campaign_id' => $campaign->id, 'name' => 'Tea', 'normalized_name' => 'TEA', 'base_price' => 80000, 'status' => 'active']);
        foreach ([$this->member, $this->roomUser('Member 2'), $this->roomUser('Member 3')] as $member) {
            app(CreateOrderAction::class)->execute($campaign, $member, ['items' => [['item_id' => $tea->id, 'quantity' => 1, 'is_self_paid' => false]]]);
        }
        $service = app(AdminCampaignDetailService::class);

        // Running campaign: the admin campaign info page.
        $running = $service->getCampaignViewData($this->room, $campaign->fresh());
        $this->assertSame(250000, $running['grossTotal']);
        $this->assertSame(250000, $running['sponsorSubsidy']);
        $this->assertSame([125000, 125000], $running['sponsorsList']->pluck('amount')->all());
        $admin = \App\Models\Admin::create(['name' => 'Room Admin', 'email' => 'sponsor-admin@example.test', 'password' => 'secret123', 'status' => 'active']);
        $admin->rooms()->attach($this->room);
        $html = $this->actingAs($admin, 'admin')->get(route('admin.campaigns.info', [$this->room->slug, $campaign->id]))->assertOk()->getContent();
        $this->assertStringNotContainsString('124.999', $html);
        $this->assertSame(2, substr_count($html, '125.000'));

        // Closed campaign: the sponsor debts and the page show the same 125,000 each.
        app(CloseCampaignAction::class)->execute($campaign);
        $debts = Debt::query()->where('campaign_id', $campaign->id)->whereIn('room_user_id', [$this->sponsorA->id, $this->sponsorB->id])->orderBy('room_user_id')->pluck('remaining_amount')->map(static fn ($amount): int => (int) $amount)->all();
        $this->assertSame([125000, 125000], $debts);
        $closed = $service->getCampaignViewData($this->room, $campaign->fresh());
        $this->assertSame(250000, $closed['sponsorSubsidy']);
        $this->assertSame([125000, 125000], $closed['sponsorsList']->pluck('amount')->all());
    }

    public function test_leaderboard_excludes_self_paid_lines(): void
    {
        $rows = app(SponsorLeaderboardService::class)->build($this->room)->keyBy('room_user_id');

        $this->assertSame(22500, $rows[$this->sponsorA->id]['total_sponsored']);
        $this->assertSame(22501, $rows[$this->sponsorB->id]['total_sponsored']);
    }

    public function test_sponsor_percentages_must_be_whole_numbers(): void
    {
        $validator = validator(
            ['sponsor_allocations' => [['room_user_id' => 1, 'percentage' => 33.5]]],
            ['sponsor_allocations.*.percentage' => (new \App\Http\Requests\StoreCampaignRequest())->rules()['sponsor_allocations.*.percentage']],
        );

        $this->assertTrue($validator->fails());
    }
}
