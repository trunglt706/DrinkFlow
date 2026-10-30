<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Enums\DebtStatus;
use App\Enums\OrderStatus;
use App\Events\CampaignCreated;
use App\Models\Admin;
use App\Models\Campaign;
use App\Models\CampaignParticipant;
use App\Models\Debt;
use App\Models\GlobalUser;
use App\Models\Order;
use App\Models\Room;
use App\Models\RoomUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminCampaignSubViewsTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;
    private Room $room;
    private Campaign $campaign;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::create([
            'name' => 'Room Admin',
            'email' => 'roomadmin@example.test',
            'password' => Hash::make('secret'),
            'status' => 'active',
        ]);

        $this->room = Room::create([
            'name' => 'Admin Room',
            'slug' => 'test-room',
            'status' => 'active',
        ]);

        $this->admin->rooms()->attach($this->room);

        $this->campaign = Campaign::create([
            'room_id' => $this->room->id,
            'status' => CampaignStatus::Closed,
            'name' => 'Trà sữa buổi chiều',
            'restaurant' => 'Gong Cha',
        ]);
    }

    /**
     * Test admin can access campaign info view via /info route.
     */
    public function test_admin_can_access_campaign_info_route(): void
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->get("/admin/{$this->room->slug}/campaigns/{$this->campaign->id}/info");

        $response->assertOk()
            ->assertViewIs('admin.campaign-info')
            ->assertSee('Trà sữa buổi chiều')
            ->assertSee('Gong Cha')
            ->assertSee(__('admin.campaign_nav_info'))
            ->assertSee(__('admin.campaign_nav_orders'))
            ->assertSee(__('admin.financial_settlement_summary'));
    }

    public function test_full_sponsorship_uses_gross_total_for_total_and_each_sponsor(): void
    {
        $firstSponsor = RoomUser::create([
            'room_id' => $this->room->id,
            'global_user_id' => GlobalUser::create(['name' => 'First Sponsor', 'email' => 'first-sponsor@example.test'])->id,
            'display_name' => 'First Sponsor',
            'status' => 'active',
        ]);
        $secondSponsor = RoomUser::create([
            'room_id' => $this->room->id,
            'global_user_id' => GlobalUser::create(['name' => 'Second Sponsor', 'email' => 'second-sponsor@example.test'])->id,
            'display_name' => 'Second Sponsor',
            'status' => 'active',
        ]);
        $this->campaign->update([
            'sponsor_type' => Campaign::SPONSOR_TYPE_FULL,
            'sponsor_allocations' => [
                ['room_user_id' => $firstSponsor->id, 'percentage' => 50],
                ['room_user_id' => $secondSponsor->id, 'percentage' => 50],
            ],
            'delivery_fee' => 10,
            'discount' => 9,
        ]);
        Order::create([
            'room_id' => $this->room->id,
            'campaign_id' => $this->campaign->id,
            'room_user_id' => $firstSponsor->id,
            'subtotal' => 100,
            'sponsor_amount' => 100,
            'final_amount' => 0,
            'status' => OrderStatus::Submitted,
        ]);

        $this->actingAs($this->admin, 'admin')
            ->get("/admin/{$this->room->slug}/campaigns/{$this->campaign->id}/info")
            ->assertOk()
            ->assertViewHas('grossTotal', 101)
            ->assertViewHas('sponsorSubsidy', 101)
            ->assertViewHas('sponsorsList', static function ($sponsors): bool {
                return $sponsors->pluck('amount')->all() === [50, 51]
                    && $sponsors->pluck('name')->all() === ['First Sponsor', 'Second Sponsor'];
            });
    }

    /**
     * Test the cancel-campaign confirm button carries a loading state for its submit.
     */
    public function test_info_page_offers_the_join_link_only_while_members_can_join(): void
    {
        $joinLink = route('user.campaigns.show', [$this->room, $this->campaign]);

        // Closed campaign (setUp): nothing to join any more.
        $this->actingAs($this->admin, 'admin')->get(route('admin.campaigns.info', [$this->room->slug, $this->campaign]))
            ->assertOk()
            ->assertDontSee('data-copy="'.$joinLink.'"', false);

        $this->campaign->update(['status' => CampaignStatus::Active]);
        $this->actingAs($this->admin, 'admin')->get(route('admin.campaigns.info', [$this->room->slug, $this->campaign]))
            ->assertOk()
            ->assertSee('data-copy="'.$joinLink.'"', false)
            ->assertSee(__('admin.copy_join_link'));
    }

    public function test_cancel_campaign_button_has_submit_loading_state(): void
    {
        $this->campaign->update(['status' => CampaignStatus::Active]);

        $html = $this->actingAs($this->admin, 'admin')
            ->get("/admin/{$this->room->slug}/campaigns/{$this->campaign->id}/info")
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="cancel-campaign-btn-normal"', $html);
        $this->assertStringContainsString('id="cancel-campaign-btn-loading"', $html);
        $this->assertStringContainsString(__('admin.cancelling_status'), $html);
        $this->assertStringContainsString("loadingEl.style.display = 'flex'", $html);
    }

    /**
     * Test admin can access campaign orders and items list view via /orders route.
     */
    public function test_admin_can_access_campaign_orders_route(): void
    {
        $globalUser = GlobalUser::create([
            'name' => 'Nguyễn Văn A',
            'email' => 'nguyenvana@example.test',
        ]);

        $roomUser = RoomUser::create([
            'room_id' => $this->room->id,
            'global_user_id' => $globalUser->id,
            'display_name' => 'Nguyễn Văn A',
            'status' => 'active',
        ]);

        Order::create([
            'room_id' => $this->room->id,
            'campaign_id' => $this->campaign->id,
            'room_user_id' => $roomUser->id,
            'subtotal' => 50000,
            'sponsor_amount' => 0,
            'final_amount' => 50000,
            'status' => OrderStatus::Submitted,
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->get("/admin/{$this->room->slug}/campaigns/{$this->campaign->id}/orders");

        $response->assertOk()
            ->assertViewIs('admin.campaign-orders')
            ->assertSee('Trà sữa buổi chiều')
            ->assertSee(__('admin.campaign_nav_info'))
            ->assertSee(__('admin.campaign_nav_orders'))
            ->assertSee(__('admin.aggregated_items_list'))
            ->assertSee(__('admin.orders_list_tab'))
            ->assertSee('Nguyễn Văn A')
            // The bulk "confirm campaign debts paid" modal preselects bank transfer.
            ->assertSee('bulkPaymentMethod: '.\Illuminate\Support\Js::from(\App\Enums\PaymentMethod::Transfer->value)->toHtml(), false);
    }

    public function test_campaign_orders_tab_excludes_cancelled_orders_from_list_and_totals(): void
    {
        $globalUser = GlobalUser::create(['name' => 'Order Member', 'email' => 'order-member@example.test']);
        $roomUser = RoomUser::create([
            'room_id' => $this->room->id,
            'global_user_id' => $globalUser->id,
            'display_name' => 'Order Member',
            'status' => 'active',
        ]);
        $activeOrder = Order::create([
            'room_id' => $this->room->id,
            'campaign_id' => $this->campaign->id,
            'room_user_id' => $roomUser->id,
            'subtotal' => 50000,
            'sponsor_amount' => 0,
            'final_amount' => 50000,
            'status' => OrderStatus::Submitted,
        ]);
        $cancelledOrder = Order::create([
            'room_id' => $this->room->id,
            'campaign_id' => $this->campaign->id,
            'room_user_id' => $roomUser->id,
            'subtotal' => 30000,
            'sponsor_amount' => 0,
            'final_amount' => 30000,
            'status' => OrderStatus::Cancelled,
            'cancelled_at' => now(),
        ]);

        $this->actingAs($this->admin, 'admin')
            ->get("/admin/{$this->room->slug}/campaigns/{$this->campaign->id}/orders")
            ->assertOk()
            ->assertViewHas('orders', fn ($orders): bool => $orders->count() === 1 && $orders->first()->id === $activeOrder->id)
            ->assertViewHas('grossSubtotal', 50000)
            ->assertSee($activeOrder->code)
            ->assertDontSee($cancelledOrder->code);
    }

    public function test_adjustment_form_displays_formatted_currency_values(): void
    {
        $this->campaign->update(['delivery_fee' => 12345, 'discount' => 6789]);

        $this->actingAs($this->admin, 'admin')
            ->get("/admin/{$this->room->slug}/campaigns/{$this->campaign->id}/info")
            ->assertOk()
            ->assertSee('id="adjust-fee-input" inputmode="numeric" value="12.345"', false)
            ->assertSee('id="adjust-discount-input" inputmode="numeric" value="6.789"', false)
            ->assertSee('oninput="formatMoneyInput(event)"', false)
            ->assertSee('type="checkbox" id="adjust-notify-members" class=', false)
            ->assertSee('notify_members: Boolean(notifyCheckbox && notifyCheckbox.checked)', false);
    }

    /**
     * Test a closed campaign cannot have its info, fees, discount or price edited.
     */
    public function test_closed_campaign_rejects_updates(): void
    {
        $base = "/admin/{$this->room->slug}/campaigns/{$this->campaign->id}";
        $item = $this->campaign->items()->create([
            'name' => 'Trà đào',
            'normalized_name' => 'tra dao',
            'base_price' => 30000,
        ]);

        $this->actingAs($this->admin, 'admin')
            ->patchJson($base, ['delivery_fee' => 5000, 'discount' => 1000])
            ->assertStatus(422)
            ->assertJsonPath('message', __('admin.campaign_locked_cannot_modify'));

        $this->actingAs($this->admin, 'admin')
            ->patchJson("{$base}/items/{$item->id}", ['name' => 'Trà đào', 'base_price' => 99000])
            ->assertStatus(422);

        $this->actingAs($this->admin, 'admin')
            ->deleteJson($base)
            ->assertStatus(422);

        $this->actingAs($this->admin, 'admin')
            ->get("{$base}/edit")
            ->assertRedirect("{$base}/info");

        $this->assertSame(0, (int) $this->campaign->fresh()->delivery_fee);
        $this->assertSame(30000, (int) $item->fresh()->base_price);
    }

    /**
     * Test the info page of a closed campaign hides edit, fee adjustment and cancel actions.
     */
    public function test_closed_campaign_info_hides_edit_actions(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->get("/admin/{$this->room->slug}/campaigns/{$this->campaign->id}/info")
            ->assertOk()
            ->assertDontSee(__('admin.edit_campaign'))
            // The confirm modal reuses the same label, so check the button that opens it.
            ->assertDontSee("openModal('confirm-cancel-modal');", false)
            ->assertDontSee("openModal('adjust-fee-modal');", false);
    }

    /**
     * Test item, size and topping creation works on an editable (active) campaign and is rejected once closed.
     */
    public function test_item_options_can_be_created_only_while_campaign_is_editable(): void
    {
        $base = "/admin/{$this->room->slug}/campaigns/{$this->campaign->id}";
        $item = $this->campaign->items()->create(['name' => 'Trà đào', 'normalized_name' => 'tra dao', 'base_price' => 30000]);

        $this->actingAs($this->admin, 'admin')
            ->postJson("{$base}/items", ['name' => 'Trà vải', 'base_price' => 32000])
            ->assertStatus(422);
        $this->actingAs($this->admin, 'admin')
            ->postJson("{$base}/items/{$item->id}/toppings", ['name' => 'Trân châu', 'price' => 5000])
            ->assertStatus(422);
        $this->actingAs($this->admin, 'admin')
            ->postJson("{$base}/items/{$item->id}/sizes", ['name' => 'L', 'price_delta' => 5000])
            ->assertStatus(422);

        $this->campaign->update(['status' => CampaignStatus::Active]);

        $this->actingAs($this->admin, 'admin')
            ->postJson("{$base}/items", ['name' => 'Trà vải', 'base_price' => 32000])
            ->assertCreated();
        $this->actingAs($this->admin, 'admin')
            ->postJson("{$base}/items/{$item->id}/toppings", ['name' => 'Trân châu', 'price' => 5000])
            ->assertCreated();
        $this->actingAs($this->admin, 'admin')
            ->postJson("{$base}/items/{$item->id}/sizes", ['name' => 'L', 'price_delta' => 5000])
            ->assertCreated();
    }

    /**
     * Test the info page links to the menu availability page, which lists items and saves toggles.
     */
    public function test_info_page_links_to_menu_page_and_menu_toggles_items(): void
    {
        $this->campaign->update(['status' => CampaignStatus::Active]);
        $item = $this->campaign->items()->create(['name' => 'Trà đào', 'normalized_name' => 'tra dao', 'base_price' => 30000]);
        $base = "/admin/{$this->room->slug}/campaigns/{$this->campaign->id}";
        $menuUrl = route('admin.campaigns.menu', [$this->room->slug, $this->campaign]);

        $this->actingAs($this->admin, 'admin')
            ->get("{$base}/info")
            ->assertOk()
            ->assertSee($menuUrl, false);

        $this->actingAs($this->admin, 'admin')
            ->get($menuUrl)
            ->assertOk()
            ->assertViewIs('admin.campaign-menu')
            ->assertSee('Trà đào')
            ->assertSee("toggleItemStatus({$item->id})", false);

        $this->actingAs($this->admin, 'admin')
            ->patchJson("{$base}/items-batch-status", ['items' => [['id' => $item->id, 'status' => 'inactive']]])
            ->assertOk()
            ->assertJsonPath('data.updated_count', 1);

        $this->assertSame('inactive', $item->fresh()->status->value);
    }

    /**
     * Test the legacy campaign URL redirects HTML requests to the info or orders page and no live view remains.
     */
    public function test_legacy_show_url_redirects_and_live_view_is_gone(): void
    {
        $base = "/admin/{$this->room->slug}/campaigns/{$this->campaign->id}";

        $this->actingAs($this->admin, 'admin')->get("{$base}?view=live")
            ->assertRedirect(route('admin.campaigns.info', [$this->room->slug, $this->campaign]));
        $this->actingAs($this->admin, 'admin')->get("{$base}?view=detail")
            ->assertRedirect(route('admin.campaigns.orders', [$this->room->slug, $this->campaign]));
        $this->actingAs($this->admin, 'admin')->getJson($base)->assertOk()->assertJsonPath('data.id', $this->campaign->id);

        $this->assertFalse(view()->exists('admin.campaign-live'));
    }

    /**
     * Test the menu page is read-only for a closed campaign.
     */
    public function test_menu_page_is_read_only_for_closed_campaign(): void
    {
        $item = $this->campaign->items()->create(['name' => 'Trà đào', 'normalized_name' => 'tra dao', 'base_price' => 30000]);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.campaigns.menu', [$this->room->slug, $this->campaign]))
            ->assertOk()
            ->assertSee(__('admin.campaign_locked_cannot_modify'));

        $this->actingAs($this->admin, 'admin')
            ->patchJson("/admin/{$this->room->slug}/campaigns/{$this->campaign->id}/items-batch-status", ['items' => [['id' => $item->id, 'status' => 'inactive']]])
            ->assertStatus(422);
    }

    /**
     * Test the info page lists actions as a vertical stack without the removed buttons or dropdown.
     */
    public function test_info_page_actions_are_a_flat_list_without_removed_buttons(): void
    {
        $this->campaign->update(['status' => CampaignStatus::Active]);

        $this->actingAs($this->admin, 'admin')
            ->get("/admin/{$this->room->slug}/campaigns/{$this->campaign->id}/info")
            ->assertOk()
            ->assertDontSee('action-menu-dropdown', false)
            ->assertDontSee("openModal('adjust-fee-modal');", false)
            ->assertSee(__('admin.close_campaign_action'))
            ->assertSee(__('admin.edit_campaign'))
            ->assertSee(__('admin.confirm_duplicate'))
            ->assertSee(__('admin.cancel_campaign_action'))
            ->assertSee("openModal('confirm-duplicate-modal');", false)
            ->assertSee('id="confirm-duplicate-modal"', false);
    }

    /**
     * Test the info page no longer renders the campaign header block inside the sub-navigation bar.
     */
    public function test_info_page_sub_navigation_has_no_campaign_header(): void
    {
        $html = $this->actingAs($this->admin, 'admin')
            ->get("/admin/{$this->room->slug}/campaigns/{$this->campaign->id}/info")
            ->assertOk()
            ->getContent();

        $nav = (string) str($html)->after('TOP SUB-NAVIGATION BAR')->before('SECTION 1');
        $this->assertStringNotContainsString('<h1', $nav);
        $this->assertStringNotContainsString('#' . $this->campaign->code, $nav);
        $this->assertStringContainsString(route('admin.campaigns.orders', [$this->room->slug, $this->campaign]), $nav);
    }

    /**
     * Test the banner shows the description but neither room nor sponsor details.
     */
    public function test_info_banner_shows_description_without_room_and_sponsors(): void
    {
        $globalUser = GlobalUser::create(['name' => 'Nhà Tài Trợ', 'email' => 'sponsor@example.test']);
        $roomUser = RoomUser::create([
            'room_id' => $this->room->id,
            'global_user_id' => $globalUser->id,
            'display_name' => 'Anh Tài Trợ',
            'status' => 'active',
        ]);
        $this->campaign->update([
            'description' => 'Đặt trước 11h nhé',
            'sponsor_type' => Campaign::SPONSOR_TYPE_FULL,
            'sponsor_description' => 'Sếp bao cả phòng',
            'sponsor_allocations' => [['room_user_id' => $roomUser->id, 'percentage' => 100]],
        ]);

        $html = $this->actingAs($this->admin, 'admin')
            ->get("/admin/{$this->room->slug}/campaigns/{$this->campaign->id}/info")
            ->assertOk()
            ->assertSee('Đặt trước 11h nhé')
            ->getContent();

        $banner = (string) str($html)->after('SECTION 1')->before('SECTION 2');
        $this->assertStringNotContainsString(__('admin.room_label'), $banner);
        $this->assertStringNotContainsString($this->room->name, $banner);
        $this->assertStringNotContainsString(__('admin.sponsor_type_label'), $banner);
        $this->assertStringNotContainsString('Sếp bao cả phòng', $banner);
        $this->assertStringNotContainsString('Anh Tài Trợ', $banner);
    }

    /**
     * Test duplicating a campaign creates a draft copy with its own code.
     */
    public function test_duplicate_creates_draft_copy_with_new_code(): void
    {
        $this->campaign->items()->create(['name' => 'Trà đào', 'normalized_name' => 'tra dao', 'base_price' => 30000]);

        $response = $this->actingAs($this->admin, 'admin')
            ->postJson("/admin/{$this->room->slug}/campaigns/{$this->campaign->id}/duplicate")
            ->assertCreated();

        $copy = Campaign::query()->findOrFail($response->json('data.id'));
        $this->assertSame(CampaignStatus::Draft, $copy->status);
        $this->assertNotSame($this->campaign->code, $copy->code);
        $this->assertSame(1, $copy->items()->count());
    }

    /**
     * Test the description is a muted italic line under the campaign name, without a card or title.
     */
    public function test_info_description_is_plain_italic_text_without_card_or_title(): void
    {
        $this->campaign->update(['description' => 'Đặt trước 11h nhé']);

        $html = $this->actingAs($this->admin, 'admin')
            ->get("/admin/{$this->room->slug}/campaigns/{$this->campaign->id}/info")
            ->assertOk()
            ->assertSee('Đặt trước 11h nhé')
            ->assertDontSee(__('admin.campaign_desc_label'))
            ->getContent();

        $this->assertMatchesRegularExpression('/<p class="[^"]*\bitalic\b[^"]*">\s*Đặt trước 11h nhé\s*<\/p>/u', $html);
    }

    /**
     * Test the sponsor policy (type and description) is shown even before any subsidy amount exists.
     */
    public function test_info_shows_sponsor_policy(): void
    {
        $this->campaign->update([
            'sponsor_type' => Campaign::SPONSOR_TYPE_FULL,
            'sponsor_description' => 'Sếp bao cả phòng',
        ]);

        $this->actingAs($this->admin, 'admin')
            ->get("/admin/{$this->room->slug}/campaigns/{$this->campaign->id}/info")
            ->assertOk()
            ->assertSee(__('admin.sponsor_type_label'))
            ->assertSee(__('admin.sponsor_type_full'))
            ->assertSee('Sếp bao cả phòng');
    }

    /**
     * Test extend buttons render only for live campaigns with a deadline.
     */
    public function test_info_shows_extend_buttons_only_for_live_campaigns(): void
    {
        $url = "/admin/{$this->room->slug}/campaigns/{$this->campaign->id}/info";

        $this->actingAs($this->admin, 'admin')->get($url)->assertOk()->assertDontSee('onclick="extendDeadline(', false);

        $this->campaign->update(['status' => CampaignStatus::Active, 'deadline' => now()->addHour()]);
        $html = $this->actingAs($this->admin, 'admin')->get($url)->assertOk()->getContent();
        foreach (Campaign::EXTEND_DEADLINE_MINUTES as $minutes) {
            $this->assertStringContainsString("extendDeadline({$minutes})", $html);
        }
    }

    /**
     * Test extending adds minutes to a future deadline, and to "now" once the deadline has passed.
     */
    public function test_extend_deadline_adds_minutes(): void
    {
        $url = "/admin/{$this->room->slug}/campaigns/{$this->campaign->id}/extend-deadline";
        $deadline = now()->addMinutes(30)->startOfSecond();
        $this->campaign->update(['status' => CampaignStatus::Active, 'deadline' => $deadline]);

        $this->actingAs($this->admin, 'admin')->postJson($url, ['minutes' => 20])->assertOk();
        $this->assertSame(
            $deadline->copy()->addMinutes(20)->timestamp,
            $this->campaign->fresh()->deadline->timestamp
        );
        $this->assertDatabaseHas('audit_logs', ['event' => 'campaign.deadline_extended', 'target_id' => $this->campaign->id]);

        $this->campaign->update(['deadline' => now()->subMinutes(15)]);
        $this->actingAs($this->admin, 'admin')->postJson($url, ['minutes' => 10])->assertOk();
        $this->assertEqualsWithDelta(
            now()->addMinutes(10)->timestamp,
            $this->campaign->fresh()->deadline->timestamp,
            5
        );
    }

    /**
     * Test extension rejects unsupported minutes, non-live campaigns and campaigns without a deadline.
     */
    public function test_extend_deadline_rejects_invalid_requests(): void
    {
        $url = "/admin/{$this->room->slug}/campaigns/{$this->campaign->id}/extend-deadline";

        // Closed campaign is locked.
        $this->actingAs($this->admin, 'admin')->postJson($url, ['minutes' => 10])->assertStatus(422);

        $this->campaign->update(['status' => CampaignStatus::Active, 'deadline' => now()->addHour()]);
        $this->actingAs($this->admin, 'admin')->postJson($url, ['minutes' => 15])->assertStatus(422)->assertJsonValidationErrors('minutes');
        $this->actingAs($this->admin, 'admin')->postJson($url, [])->assertStatus(422)->assertJsonValidationErrors('minutes');

        $this->campaign->update(['deadline' => null]);
        $this->actingAs($this->admin, 'admin')->postJson($url, ['minutes' => 10])
            ->assertStatus(422)->assertJsonValidationErrors('deadline');
    }

    /**
     * Test the orders page tooltips, email in the debt ledger, icon-only detail button and read-only debt modal.
     */
    public function test_orders_page_tooltips_ledger_email_and_readonly_debt_modal(): void
    {
        $globalUser = GlobalUser::create(['name' => 'Nguyễn Văn A', 'email' => 'nguyenvana@example.test']);
        $roomUser = RoomUser::create([
            'room_id' => $this->room->id,
            'global_user_id' => $globalUser->id,
            'user_code' => 'USR-777',
            'display_name' => 'Nguyễn Văn A',
            'status' => 'active',
        ]);
        Order::create([
            'room_id' => $this->room->id,
            'campaign_id' => $this->campaign->id,
            'room_user_id' => $roomUser->id,
            'code' => 'ORD-COPY-1',
            'subtotal' => 50000,
            'final_amount' => 50000,
            'status' => OrderStatus::Submitted,
        ]);
        \App\Models\Debt::create([
            'room_id' => $this->room->id,
            'campaign_id' => $this->campaign->id,
            'room_user_id' => $roomUser->id,
            'original_amount' => 50000,
            'remaining_amount' => 50000,
            'status' => \App\Enums\DebtStatus::Unpaid,
        ]);

        $html = $this->actingAs($this->admin, 'admin')
            ->get("/admin/{$this->room->slug}/campaigns/{$this->campaign->id}/orders")
            ->assertOk()
            ->getContent();

        // Shared fixed-position tooltip + tooltip hooks on copy / eye buttons.
        $this->assertStringContainsString('x-ref="tip"', $html);
        $this->assertStringContainsString('data-tip="'.__('admin.copy_order_code').'"', $html);
        $this->assertStringContainsString('data-tip="'.__('admin.view_order_detail').'"', $html);

        // Ledger: email replaces the user code, and the detail button is icon-only.
        $ledger = (string) str($html)->after('x-show="activeTab === \'ledger\'"')->before('TAB 5: DECLINED');
        $this->assertStringContainsString('nguyenvana@example.test', $ledger);
        $this->assertStringNotContainsString('USR-777', $ledger);
        $this->assertStringNotContainsString('<span>'.__('admin.view_order_detail').'</span>', $ledger);

        // Debt detail modal: read-only status and no footer.
        $modal = (string) str($html)->after('MODAL: DEBT DETAILS')->before('function campaignOrdersComponent');
        $this->assertStringContainsString('Status (read-only)', $modal);
        $this->assertStringNotContainsString('updateDebtStatus', $modal);
        $this->assertStringNotContainsString('<select', $modal);
        $this->assertStringNotContainsString('Modal Footer', $modal);

        $this->assertSame(substr_count($html, '<div'), substr_count($html, '</div>'));
    }

    /**
     * Test the department tab groups orders strictly by desk_location, merging spacing/case variants.
     */
    public function test_department_groups_use_normalized_desk_location(): void
    {
        $members = [
            ['A', 'a@example.test', 'Tầng 2', 30000],
            ['B', 'b@example.test', '  tầng   2 ', 20000],
            ['C', 'c@example.test', 'Kế toán', 10000],
            ['D', 'd@example.test', null, 40000],
        ];

        foreach ($members as [$name, $email, $desk, $amount]) {
            $globalUser = GlobalUser::create(['name' => $name, 'email' => $email, 'desk_location' => $desk]);
            $roomUser = RoomUser::create([
                'room_id' => $this->room->id,
                'global_user_id' => $globalUser->id,
                'display_name' => $name,
                'status' => 'active',
            ]);
            $order = Order::create([
                'room_id' => $this->room->id,
                'campaign_id' => $this->campaign->id,
                'room_user_id' => $roomUser->id,
                'subtotal' => $amount,
                'final_amount' => $amount,
                'status' => OrderStatus::Submitted,
            ]);
            $order->items()->create([
                'item_name' => 'Trà đào',
                'unit_price' => $amount,
                'quantity' => 1,
                'line_subtotal' => $amount,
            ]);
        }

        $data = app(\App\Services\Admin\AdminCampaignDetailService::class)
            ->getCampaignViewData($this->room, $this->campaign->fresh());
        $groups = $data['departmentGroups'];

        $this->assertCount(3, $groups);
        $this->assertSame(['Kế toán', 'Tầng 2', __('admin.unassigned_department')], $groups->pluck('department')->all());
        $this->assertSame(2, $groups[1]['members']->count());
        $this->assertSame(50000, $groups[1]['total_amount']);
        $this->assertTrue($groups[2]['is_unassigned']);

        $this->actingAs($this->admin, 'admin')
            ->get("/admin/{$this->room->slug}/campaigns/{$this->campaign->id}/orders")
            ->assertOk()
            ->assertSee('id="department-filter"', false)
            ->assertSee('<option value="1">Tầng 2</option>', false)
            ->assertDontSee('Modal Footer', false);
    }

    /**
     * Test the campaign editor marks the chosen previous menu with a check icon and formats the budget input.
     */
    public function test_edit_page_marks_selected_previous_menu_and_formats_budget(): void
    {
        $draft = Campaign::create([
            'room_id' => $this->room->id,
            'status' => CampaignStatus::Draft,
            'name' => 'Bản nháp',
            'restaurant' => 'Cafe',
        ]);
        $this->campaign->items()->create(['name' => 'Trà đào', 'normalized_name' => 'tra dao', 'base_price' => 30000]);

        $html = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.campaigns.edit', [$this->room->slug, $draft]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('selectedPreviousCampaignId === '.$this->campaign->id, $html);
        $this->assertStringContainsString('>check_circle</span>', $html);
        $this->assertStringContainsString('x-effect="$el.value = formatCurrencyDisplay(form.max_budget)"', $html);

        // Each category chip carries a red delete button that removes the category with its items.
        $this->assertStringContainsString('@click.stop="removeMenuCategory(category)"', $html);
        $this->assertStringContainsString('absolute -top-2 -right-2', $html);
        $this->assertStringContainsString('bg-red-600', $html);
        $this->assertStringNotContainsString('data-category-delete-confirm', $html);

        // Name and shop are single-line (truncated); the item count sits on the same row as the created time.
        $this->assertStringContainsString('class="truncate" title="Trà sữa buổi chiều"', $html);
        $this->assertStringContainsString('class="text-[11px] text-outline mt-1 truncate" title="Gong Cha"', $html);
        $this->assertMatchesRegularExpression('/history<\/span>\s*<span class="truncate">[^<]+<\/span>\s*<\/div>\s*<span class="[^"]*whitespace-nowrap[^"]*">1 /u', $html);
    }

    /**
     * Test the resend button renders only for live campaigns.
     */
    public function test_info_shows_resend_notification_button_only_for_live_campaigns(): void
    {
        $url = "/admin/{$this->room->slug}/campaigns/{$this->campaign->id}/info";

        $this->actingAs($this->admin, 'admin')->get($url)->assertOk()->assertDontSee('id="resend-notification-btn"', false);

        $this->campaign->update(['status' => CampaignStatus::Active, 'deadline' => now()->addHour()]);
        $this->actingAs($this->admin, 'admin')->get($url)->assertOk()->assertSee('id="resend-notification-btn"', false);
    }

    /**
     * Test resending re-dispatches the created event (web, channel and socket fan-out) for live campaigns only.
     */
    public function test_resend_notification_redispatches_created_event_for_live_campaigns(): void
    {
        Event::fake([CampaignCreated::class]);
        $url = "/admin/{$this->room->slug}/campaigns/{$this->campaign->id}/resend-notification";

        // Closed campaign cannot be announced again.
        $this->actingAs($this->admin, 'admin')->postJson($url)->assertStatus(422);
        Event::assertNotDispatched(CampaignCreated::class);

        $this->travel(61)->seconds(); // the rejected attempt above also counts toward the limit
        $this->campaign->update(['status' => CampaignStatus::Active]);
        $this->actingAs($this->admin, 'admin')->postJson($url)->assertOk()->assertJsonPath('message', __('admin.resend_notification_success'));

        Event::assertDispatched(CampaignCreated::class, fn (CampaignCreated $event): bool => $event->campaign->id === $this->campaign->id);
        $this->assertDatabaseHas('audit_logs', ['event' => 'campaign.notification_resent', 'target_id' => $this->campaign->id]);
    }

    /**
     * Test resending is rate limited per campaign with a localized JSON error.
     */
    public function test_resend_notification_is_rate_limited_per_campaign(): void
    {
        Event::fake([CampaignCreated::class]);
        $url = "/admin/{$this->room->slug}/campaigns/{$this->campaign->id}/resend-notification";
        $this->campaign->update(['status' => CampaignStatus::Active]);

        $this->actingAs($this->admin, 'admin')->postJson($url)->assertOk();
        $this->actingAs($this->admin, 'admin')->postJson($url)
            ->assertStatus(429)
            ->assertJsonStructure(['message']);

        Event::assertDispatchedTimes(CampaignCreated::class, 1);
    }

    /**
     * Test the aggregated and orders tabs show toppings and ice/sugar, and split drinks by customization.
     */
    public function test_orders_page_shows_toppings_and_customizations_in_item_columns(): void
    {
        $roomUser = RoomUser::create([
            'room_id' => $this->room->id,
            'global_user_id' => GlobalUser::create(['name' => 'Topping Fan', 'email' => 'fan@example.test'])->id,
            'display_name' => 'Topping Fan',
            'status' => 'active',
        ]);
        $order = Order::create([
            'room_id' => $this->room->id,
            'campaign_id' => $this->campaign->id,
            'room_user_id' => $roomUser->id,
            'subtotal' => 75000,
            'final_amount' => 75000,
            'status' => OrderStatus::Submitted,
        ]);
        $plain = $order->items()->create(['item_name' => 'Trà đào', 'size_name' => 'L', 'unit_price' => 30000, 'quantity' => 1, 'line_subtotal' => 30000]);
        $withPearl = $order->items()->create([
            'item_name' => 'Trà đào', 'size_name' => 'L', 'unit_price' => 30000, 'quantity' => 1,
            'ice_percent' => 50, 'sugar_percent' => 70, 'line_subtotal' => 35000,
        ]);
        $withPearl->toppings()->create(['topping_name' => 'Trân châu', 'unit_price' => 5000, 'quantity' => 1, 'subtotal' => 5000]);

        $response = $this->actingAs($this->admin, 'admin')
            ->get("/admin/{$this->room->slug}/campaigns/{$this->campaign->id}/orders")
            ->assertOk()
            ->assertSee('+ Trân châu', false)
            ->assertSee('50% '.__('room.orders.ice'), false)
            ->assertSee('70% '.__('room.orders.sugar'), false);

        // Same drink with and without toppings is two lines in the aggregated list.
        $aggregated = $response->viewData('aggregatedItems');
        $this->assertCount(2, $aggregated);
        $this->assertSame([1, 1], $aggregated->pluck('quantity')->values()->all());
        $this->assertSame(['Trân châu'], $aggregated->firstWhere('toppings', collect(['Trân châu']))['toppings']->all());

        // The department tab lists the same customizations per department.
        $departments = $response->viewData('departmentGroups');
        $this->assertCount(1, $departments);
        $this->assertCount(2, $departments->first()['items']);
        $this->assertContains(['Trân châu'], $departments->first()['items']->pluck('toppings')->map->all()->all());
    }

    public function test_aggregated_quantity_tooltip_lists_each_member_and_combines_their_item_quantities(): void
    {
        $members = collect(['Alice', 'Bob'])->mapWithKeys(function (string $name): array {
            $globalUser = GlobalUser::create(['name' => $name, 'email' => strtolower($name).'@example.test']);
            $roomUser = RoomUser::create([
                'room_id' => $this->room->id,
                'global_user_id' => $globalUser->id,
                'display_name' => $name,
                'status' => 'active',
            ]);

            return [$name => $roomUser];
        });

        foreach (['Alice' => [1, 2], 'Bob' => [2]] as $name => $quantities) {
            $orderQuantity = array_sum($quantities);
            $order = Order::create([
                'room_id' => $this->room->id,
                'campaign_id' => $this->campaign->id,
                'room_user_id' => $members[$name]->id,
                'subtotal' => $orderQuantity * 25000,
                'final_amount' => $orderQuantity * 25000,
                'status' => OrderStatus::Submitted,
            ]);
            foreach ($quantities as $quantity) {
                $order->items()->create([
                    'item_name' => 'Coffee',
                    'unit_price' => 25000,
                    'quantity' => $quantity,
                    'line_subtotal' => $quantity * 25000,
                ]);
            }
        }

        $response = $this->actingAs($this->admin, 'admin')
            ->get("/admin/{$this->room->slug}/campaigns/{$this->campaign->id}/orders")
            ->assertOk();

        $item = $response->viewData('aggregatedItems')->first();
        $this->assertSame(5, $item['quantity']);
        $this->assertSame([
            ['name' => 'Alice', 'quantity' => 3],
            ['name' => 'Bob', 'quantity' => 2],
        ], $item['member_quantities']->values()->all());
        $response->assertSee("data-tip=\"Alice x 3\nBob x 2\"", false);
    }

    /**
     * Debt records are always created on close, so the modal no longer offers an opt-out checkbox.
     */
    public function test_close_modal_has_no_auto_debt_checkbox(): void
    {
        $this->campaign->update(['status' => CampaignStatus::Active, 'deadline' => now()->addHour()]);

        $this->actingAs($this->admin, 'admin')
            ->get("/admin/{$this->room->slug}/campaigns/{$this->campaign->id}/info")
            ->assertOk()
            ->assertSee('data-close-campaign-open', false)
            ->assertDontSee('close-campaign-allow-debt', false);
    }

    /**
     * Test the update endpoint respects the notify_members flag sent by the confirm modal.
     */
    public function test_update_endpoint_skips_notification_when_notify_members_is_false(): void
    {
        $this->campaign->update(['status' => CampaignStatus::Active]);
        $base = "/admin/{$this->room->slug}/campaigns/{$this->campaign->id}";

        Event::fake([\App\Events\CampaignUpdated::class]);
        $this->actingAs($this->admin, 'admin')
            ->patchJson($base, ['delivery_fee' => 1000, 'discount' => 0, 'notify_members' => false])
            ->assertOk();
        Event::assertNotDispatched(\App\Events\CampaignUpdated::class);

        Event::fake([\App\Events\CampaignUpdated::class]);
        $this->actingAs($this->admin, 'admin')
            ->patchJson($base, ['delivery_fee' => 2000, 'discount' => 0, 'notify_members' => true])
            ->assertOk();
        Event::assertDispatched(\App\Events\CampaignUpdated::class);
    }

    /**
     * Test the batch item status update only notifies room members when explicitly requested.
     */
    public function test_batch_item_status_update_only_notifies_when_notify_members_is_true(): void
    {
        $this->campaign->update(['status' => CampaignStatus::Active]);
        $item = $this->campaign->items()->create(['name' => 'Trà đào', 'normalized_name' => 'tra dao', 'base_price' => 30000, 'status' => 'active']);
        $base = "/admin/{$this->room->slug}/campaigns/{$this->campaign->id}/items-batch-status";

        Event::fake([\App\Events\CampaignUpdated::class]);
        $this->actingAs($this->admin, 'admin')
            ->patchJson($base, ['items' => [['id' => $item->id, 'status' => 'inactive']]])
            ->assertOk();
        Event::assertNotDispatched(\App\Events\CampaignUpdated::class);
        $this->assertSame('inactive', $item->fresh()->status->value);

        Event::fake([\App\Events\CampaignUpdated::class]);
        $this->actingAs($this->admin, 'admin')
            ->patchJson($base, ['items' => [['id' => $item->id, 'status' => 'active']], 'notify_members' => true])
            ->assertOk();
        Event::assertDispatched(\App\Events\CampaignUpdated::class);
        $this->assertSame('active', $item->fresh()->status->value);
    }

    /**
     * Test the orders tab action dropdown only offers delete/confirm-payment while the campaign is live.
     */
    public function test_orders_tab_action_dropdown_hides_live_only_actions_when_not_live(): void
    {
        $globalUser = GlobalUser::create(['name' => 'Dropdown Member', 'email' => 'dropdown-member@example.test']);
        $roomUser = RoomUser::create([
            'room_id' => $this->room->id, 'global_user_id' => $globalUser->id,
            'display_name' => 'Dropdown Member', 'status' => 'active',
        ]);
        $order = Order::create([
            'room_id' => $this->room->id, 'campaign_id' => $this->campaign->id, 'room_user_id' => $roomUser->id,
            'subtotal' => 50000, 'final_amount' => 50000, 'status' => OrderStatus::Submitted,
        ]);
        $rowDeleteCall = "openDeleteOrderModal({$order->id});";
        $rowConfirmPaymentCall = "openConfirmPaymentModal({$order->id});";

        // Closed campaign (from setUp): only "view detail" should show, no delete/confirm-payment.
        $this->actingAs($this->admin, 'admin')
            ->get("/admin/{$this->room->slug}/campaigns/{$this->campaign->id}/orders")
            ->assertOk()
            ->assertSee(__('admin.view_order_detail'))
            ->assertDontSee($rowDeleteCall, false)
            ->assertDontSee($rowConfirmPaymentCall, false);

        $this->campaign->update(['status' => CampaignStatus::Active]);

        $this->actingAs($this->admin, 'admin')
            ->get("/admin/{$this->room->slug}/campaigns/{$this->campaign->id}/orders")
            ->assertOk()
            ->assertSee($rowDeleteCall, false)
            ->assertSee($rowConfirmPaymentCall, false);
    }

    /**
     * Test an admin can delete an order only while its campaign is live.
     */
    public function test_admin_can_delete_order_only_while_campaign_is_live(): void
    {
        $globalUser = GlobalUser::create(['name' => 'Delete Member', 'email' => 'delete-member@example.test']);
        $roomUser = RoomUser::create([
            'room_id' => $this->room->id, 'global_user_id' => $globalUser->id,
            'display_name' => 'Delete Member', 'status' => 'active',
        ]);
        $order = Order::create([
            'room_id' => $this->room->id, 'campaign_id' => $this->campaign->id, 'room_user_id' => $roomUser->id,
            'subtotal' => 50000, 'final_amount' => 50000, 'status' => OrderStatus::Submitted,
        ]);

        $this->actingAs($this->admin, 'admin')
            ->deleteJson("/admin/{$this->room->slug}/orders/{$order->id}")
            ->assertStatus(422);
        $this->assertDatabaseHas('orders', ['id' => $order->id]);

        $this->campaign->update(['status' => CampaignStatus::Active]);

        $this->actingAs($this->admin, 'admin')
            ->deleteJson("/admin/{$this->room->slug}/orders/{$order->id}")
            ->assertOk();
        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
    }

    /**
     * Test confirming payment for an order settles the member's debt for that campaign, scoped only to
     * that member (not other members' orders in the same campaign), and only while the campaign is live.
     */
    public function test_admin_can_confirm_order_payment_only_while_campaign_is_live(): void
    {
        $globalUser = GlobalUser::create(['name' => 'Payer Member', 'email' => 'payer-member@example.test']);
        $roomUser = RoomUser::create([
            'room_id' => $this->room->id, 'global_user_id' => $globalUser->id,
            'display_name' => 'Payer Member', 'status' => 'active',
        ]);
        $order = Order::create([
            'room_id' => $this->room->id, 'campaign_id' => $this->campaign->id, 'room_user_id' => $roomUser->id,
            'subtotal' => 50000, 'final_amount' => 50000, 'status' => OrderStatus::Submitted,
        ]);
        $otherGlobalUser = GlobalUser::create(['name' => 'Other Member', 'email' => 'other-member@example.test']);
        $otherRoomUser = RoomUser::create([
            'room_id' => $this->room->id, 'global_user_id' => $otherGlobalUser->id,
            'display_name' => 'Other Member', 'status' => 'active',
        ]);
        $otherOrder = Order::create([
            'room_id' => $this->room->id, 'campaign_id' => $this->campaign->id, 'room_user_id' => $otherRoomUser->id,
            'subtotal' => 20000, 'final_amount' => 20000, 'status' => OrderStatus::Submitted,
        ]);

        $this->actingAs($this->admin, 'admin')
            ->postJson("/admin/{$this->room->slug}/orders/{$order->id}/confirm-payment")
            ->assertStatus(422);

        $this->campaign->update(['status' => CampaignStatus::Active]);

        $this->actingAs($this->admin, 'admin')
            ->postJson("/admin/{$this->room->slug}/orders/{$order->id}/confirm-payment")
            ->assertOk();

        $this->assertSame('paid', $order->fresh()->payment_status->value);
        $this->assertNotSame('paid', $otherOrder->fresh()->payment_status->value);
        $this->assertDatabaseHas('debts', [
            'campaign_id' => $this->campaign->id,
            'room_user_id' => $roomUser->id,
            'status' => 'paid',
            'remaining_amount' => 0,
        ]);
    }

    /**
     * Test the orders tab action dropdown hides "confirm payment" once an order is already paid,
     * but keeps offering delete.
     */
    public function test_orders_tab_hides_confirm_payment_action_for_already_paid_orders(): void
    {
        $this->campaign->update(['status' => CampaignStatus::Active]);
        $globalUser = GlobalUser::create(['name' => 'Paid Member', 'email' => 'paid-member@example.test']);
        $roomUser = RoomUser::create([
            'room_id' => $this->room->id, 'global_user_id' => $globalUser->id,
            'display_name' => 'Paid Member', 'status' => 'active',
        ]);
        $order = Order::create([
            'room_id' => $this->room->id, 'campaign_id' => $this->campaign->id, 'room_user_id' => $roomUser->id,
            'subtotal' => 50000, 'final_amount' => 50000, 'status' => OrderStatus::Submitted,
            'payment_status' => 'paid',
        ]);

        $this->actingAs($this->admin, 'admin')
            ->get("/admin/{$this->room->slug}/campaigns/{$this->campaign->id}/orders")
            ->assertOk()
            ->assertDontSee("openConfirmPaymentModal({$order->id});", false)
            ->assertSee("openDeleteOrderModal({$order->id});", false);
    }

    /**
     * Test deleting an order also removes the member's debt and debt payment records for that campaign.
     */
    public function test_deleting_order_also_deletes_its_debt_and_payments(): void
    {
        $this->campaign->update(['status' => CampaignStatus::Active]);
        $globalUser = GlobalUser::create(['name' => 'Debt Delete Member', 'email' => 'debt-delete-member@example.test']);
        $roomUser = RoomUser::create([
            'room_id' => $this->room->id, 'global_user_id' => $globalUser->id,
            'display_name' => 'Debt Delete Member', 'status' => 'active',
        ]);
        $order = Order::create([
            'room_id' => $this->room->id, 'campaign_id' => $this->campaign->id, 'room_user_id' => $roomUser->id,
            'subtotal' => 50000, 'final_amount' => 50000, 'status' => OrderStatus::Submitted,
        ]);
        $debt = Debt::create([
            'room_id' => $this->room->id, 'campaign_id' => $this->campaign->id, 'room_user_id' => $roomUser->id,
            'original_amount' => 50000, 'remaining_amount' => 0, 'paid_amount' => 50000, 'status' => DebtStatus::Paid,
        ]);
        \App\Models\DebtPayment::create([
            'debt_id' => $debt->id, 'amount' => 50000, 'payment_method' => 'vietqr',
        ]);

        $this->actingAs($this->admin, 'admin')
            ->deleteJson("/admin/{$this->room->slug}/orders/{$order->id}")
            ->assertOk();

        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
        $this->assertDatabaseMissing('debts', ['id' => $debt->id]);
        $this->assertDatabaseMissing('debt_payments', ['debt_id' => $debt->id]);
    }

    public function test_bulk_payment_confirmation_updates_only_outstanding_campaign_debts(): void
    {
        $user = GlobalUser::create(['name' => 'Debt Member', 'email' => 'debt-member@example.test']);
        $roomUser = RoomUser::create([
            'room_id' => $this->room->id, 'global_user_id' => $user->id,
            'display_name' => 'Debt Member', 'status' => 'active',
        ]);
        $order = Order::create([
            'room_id' => $this->room->id, 'campaign_id' => $this->campaign->id,
            'room_user_id' => $roomUser->id, 'subtotal' => 50000,
            'final_amount' => 50000, 'status' => OrderStatus::Submitted,
        ]);
        $cancelledOrder = Order::create([
            'room_id' => $this->room->id, 'campaign_id' => $this->campaign->id,
            'room_user_id' => $roomUser->id, 'subtotal' => 10000,
            'final_amount' => 10000, 'status' => OrderStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
        $debt = Debt::create([
            'room_id' => $this->room->id, 'campaign_id' => $this->campaign->id,
            'room_user_id' => $roomUser->id, 'original_amount' => 50000,
            'paid_amount' => 10000, 'remaining_amount' => 40000,
            'status' => DebtStatus::Partial,
        ]);
        $otherCampaign = Campaign::create([
            'room_id' => $this->room->id, 'name' => 'Other Campaign',
            'restaurant' => 'Other Shop', 'status' => CampaignStatus::Closed,
        ]);
        $otherDebt = Debt::create([
            'room_id' => $this->room->id, 'campaign_id' => $otherCampaign->id,
            'room_user_id' => $roomUser->id, 'original_amount' => 20000,
            'paid_amount' => 0, 'remaining_amount' => 20000,
            'status' => DebtStatus::Unpaid,
        ]);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.campaigns.orders', [$this->room, $this->campaign]))
            ->assertOk()
            ->assertSee(__('admin.confirm_paid_orders'));

        $url = route('admin.campaigns.confirm-debts-paid', [$this->room, $this->campaign]);
        $this->actingAs($this->admin, 'admin')->postJson($url)->assertUnprocessable()->assertJsonValidationErrors('payment_method');
        $this->actingAs($this->admin, 'admin')->postJson($url, ['payment_method' => 'transfer'])->assertOk()->assertJsonPath('data.count', 1);

        $this->assertSame(DebtStatus::Paid, $debt->fresh()->status);
        $this->assertSame(50000, $debt->fresh()->paid_amount);
        $this->assertSame(0, $debt->fresh()->remaining_amount);
        $this->assertSame(DebtStatus::Unpaid, $otherDebt->fresh()->status);
        $this->assertSame(OrderStatus::Submitted, $order->fresh()->status);
        $this->assertSame('paid', $order->fresh()->payment_status->value);
        $this->assertNotNull($order->fresh()->paid_at);
        $this->assertSame('unpaid', $cancelledOrder->fresh()->payment_status->value);
        $this->assertDatabaseHas('debt_payments', [
            'debt_id' => $debt->id,
            'amount' => 40000,
            'payment_method' => 'transfer',
            'created_by_admin_id' => $this->admin->id,
        ]);
        $this->assertSame(1, $debt->payments()->count());

        $this->actingAs($this->admin, 'admin')->postJson($url, ['payment_method' => 'transfer'])->assertOk()->assertJsonPath('data.count', 0);
        $this->assertSame(50000, $debt->fresh()->paid_amount);
        $this->assertSame(1, $debt->payments()->count());
    }

    /**
     * Test the close-summary endpoint returns fresh item, money and member totals that ignore cancelled orders.
     */
    public function test_close_summary_returns_latest_counts_for_close_modal(): void
    {
        $this->campaign->update([
            'status' => CampaignStatus::Active,
            'code' => 'CMP-CLOSE',
            'delivery_fee' => 15000,
            'discount' => 5000,
            'sponsor_type' => Campaign::SPONSOR_TYPE_PER_ITEM,
        ]);
        $url = "/admin/{$this->room->slug}/campaigns/{$this->campaign->id}/close-summary";

        $makeMember = fn (string $name, string $status = 'active'): RoomUser => RoomUser::create([
            'room_id' => $this->room->id,
            'global_user_id' => GlobalUser::create(['name' => $name, 'email' => str($name)->slug().'@example.test'])->id,
            'display_name' => $name,
            'status' => $status,
        ]);
        $alice = $makeMember('Alice');
        $bob = $makeMember('Bob');
        $carol = $makeMember('Carol');
        $dave = $makeMember('Dave');
        $makeMember('Erin');
        $makeMember('Removed Member', 'removed');

        $this->actingAs($this->admin, 'admin')->getJson($url)
            ->assertOk()
            ->assertJsonPath('data.code', 'CMP-CLOSE')
            ->assertJsonPath('data.is_closable', true)
            ->assertJsonPath('data.total_items', 0)
            ->assertJsonPath('data.orders_count', 0)
            ->assertJsonPath('data.ordered_users_count', 0)
            ->assertJsonPath('data.pending_users_count', 5)
            ->assertJsonPath('data.declined_users_count', 0)
            ->assertJsonPath('data.total_users_count', 5);

        // Orders placed after the page was rendered must show up the next time the modal opens.
        $aliceOrder = Order::create([
            'room_id' => $this->room->id, 'campaign_id' => $this->campaign->id, 'room_user_id' => $alice->id,
            'subtotal' => 100000, 'sponsor_amount' => 20000, 'final_amount' => 80000, 'status' => OrderStatus::Submitted,
        ]);
        $aliceOrder->items()->create(['item_name' => 'Trà đào', 'unit_price' => 30000, 'quantity' => 2, 'line_subtotal' => 60000]);
        $aliceOrder->items()->create(['item_name' => 'Bánh', 'unit_price' => 40000, 'quantity' => 1, 'line_subtotal' => 40000, 'is_self_paid' => true]);
        // Proxy order Alice placed for Bob.
        $proxyOrder = Order::create([
            'parent_id' => $aliceOrder->id, 'room_id' => $this->room->id, 'campaign_id' => $this->campaign->id, 'room_user_id' => $bob->id,
            'subtotal' => 90000, 'sponsor_amount' => 10000, 'final_amount' => 80000, 'status' => OrderStatus::Submitted,
        ]);
        $proxyOrder->items()->create(['item_name' => 'Trà đào', 'unit_price' => 30000, 'quantity' => 3, 'line_subtotal' => 90000]);
        // Cancelled orders are ignored.
        $cancelled = Order::create([
            'room_id' => $this->room->id, 'campaign_id' => $this->campaign->id, 'room_user_id' => $carol->id,
            'subtotal' => 30000, 'sponsor_amount' => 5000, 'final_amount' => 25000, 'status' => OrderStatus::Cancelled,
        ]);
        $cancelled->items()->create(['item_name' => 'Trà đào', 'unit_price' => 30000, 'quantity' => 1, 'line_subtotal' => 30000]);
        CampaignParticipant::create([
            'campaign_id' => $this->campaign->id, 'room_user_id' => $dave->id,
            'status' => CampaignParticipant::STATUS_DECLINED, 'declined_at' => now(),
        ]);

        $this->actingAs($this->admin, 'admin')->getJson($url)
            ->assertOk()
            ->assertJsonPath('data.own_items', 2)
            ->assertJsonPath('data.proxy_items', 3)
            ->assertJsonPath('data.self_paid_items', 1)
            ->assertJsonPath('data.total_items', 6)
            ->assertJsonPath('data.gross_subtotal', 190000)
            ->assertJsonPath('data.discount_total', 5000)
            ->assertJsonPath('data.extra_fee_total', 15000)
            ->assertJsonPath('data.sponsor_total', 30000)
            ->assertJsonPath('data.self_paid_total', 40000)
            ->assertJsonPath('data.final_total', 170000)
            ->assertJsonPath('data.orders_count', 2)
            ->assertJsonPath('data.ordered_users_count', 2)
            ->assertJsonPath('data.pending_users_count', 2)
            ->assertJsonPath('data.declined_users_count', 1)
            ->assertJsonPath('data.total_users_count', 5);

        $this->campaign->update(['status' => CampaignStatus::Closed]);

        $this->actingAs($this->admin, 'admin')->getJson($url)
            ->assertOk()
            ->assertJsonPath('data.is_closable', false);
    }

    /**
     * Test the close-summary endpoint cannot read campaigns from another room.
     */
    public function test_close_summary_is_scoped_to_the_current_room(): void
    {
        $otherRoom = Room::create(['name' => 'Other Room', 'slug' => 'other-room', 'status' => 'active']);
        $otherCampaign = Campaign::create([
            'room_id' => $otherRoom->id,
            'status' => CampaignStatus::Active,
            'name' => 'Other campaign',
            'restaurant' => 'Phuc Long',
        ]);

        $this->actingAs($this->admin, 'admin')
            ->getJson("/admin/{$this->room->slug}/campaigns/{$otherCampaign->id}/close-summary")
            ->assertNotFound();
    }

    /**
     * Test the info page and the dashboard share the same close-campaign modal.
     */
    public function test_info_page_and_dashboard_render_shared_close_campaign_modal(): void
    {
        $this->campaign->update(['status' => CampaignStatus::Active, 'deadline' => now()->addHour()]);
        $summaryUrl = route('admin.campaigns.close-summary', [$this->room, '__CAMPAIGN__']);

        $this->actingAs($this->admin, 'admin')
            ->get("/admin/{$this->room->slug}/campaigns/{$this->campaign->id}/info")
            ->assertOk()
            ->assertSee('data-close-campaign-modal', false)
            ->assertSee('data-summary-url-template="'.$summaryUrl.'"', false)
            ->assertSee('data-close-campaign-open data-campaign-id="'.$this->campaign->id.'"', false)
            ->assertDontSee('id="close-confirm-modal"', false);

        $this->actingAs($this->admin, 'admin')
            ->get("/admin/{$this->room->slug}/dashboard")
            ->assertOk()
            ->assertSee('data-close-campaign-modal', false)
            ->assertSee('data-summary-url-template="'.$summaryUrl.'"', false)
            ->assertSee(__('admin.close_campaign_confirm_modal_title'))
            ->assertDontSee('id="close-campaign-modal"', false);
    }

    /**
     * The financial settlement lists the self-paid ("trả riêng") total and each member's self-paid
     * amount, and the orders tab shows the original menu-price total under the payable amount.
     *
     * @return void
     */
    public function test_info_page_lists_self_paid_members_and_orders_tab_shows_original_total(): void
    {
        $makeMember = fn (string $name): RoomUser => RoomUser::create([
            'room_id' => $this->room->id,
            'global_user_id' => GlobalUser::create(['name' => $name, 'email' => str($name)->slug().'@example.test'])->id,
            'display_name' => $name,
            'status' => 'active',
        ]);
        $alice = $makeMember('Alice Selfpaid');
        $bob = $makeMember('Bob Selfpaid');
        $carol = $makeMember('Carol Sponsored');

        foreach ([[$alice, [40000, 15000]], [$bob, [25000]]] as [$member, $selfPaidLines]) {
            $selfPaid = array_sum($selfPaidLines);
            $order = Order::create([
                'room_id' => $this->room->id, 'campaign_id' => $this->campaign->id, 'room_user_id' => $member->id,
                'subtotal' => 30000 + $selfPaid, 'final_amount' => 30000 + $selfPaid, 'status' => OrderStatus::Submitted,
            ]);
            $order->items()->create(['item_name' => 'Trà', 'unit_price' => 30000, 'quantity' => 1, 'line_subtotal' => 30000]);
            foreach ($selfPaidLines as $linePrice) {
                $order->items()->create(['item_name' => 'Bánh', 'unit_price' => $linePrice, 'quantity' => 1, 'line_subtotal' => $linePrice, 'is_self_paid' => true]);
            }
        }
        $carolOrder = Order::create([
            'room_id' => $this->room->id, 'campaign_id' => $this->campaign->id, 'room_user_id' => $carol->id,
            'subtotal' => 123000, 'final_amount' => 100000, 'status' => OrderStatus::Submitted,
        ]);
        $carolOrder->items()->create(['item_name' => 'Trà', 'unit_price' => 123000, 'quantity' => 1, 'line_subtotal' => 123000]);

        $response = $this->actingAs($this->admin, 'admin')->get(route('admin.campaigns.info', [$this->room->slug, $this->campaign]))
            ->assertOk()
            ->assertSee(__('admin.self_paid_total_label'))
            ->assertSee(__('admin.self_paid_members_hint', ['count' => 2]));
        $this->assertSame(80000, $response->viewData('selfPaidTotal'));
        $this->assertSame(
            [['Alice Selfpaid', 55000], ['Bob Selfpaid', 25000]],
            $response->viewData('selfPaidMembers')->map(fn (array $row): array => [$row['name'], $row['amount']])->all(),
        );

        $this->actingAs($this->admin, 'admin')->get(route('admin.campaigns.orders', [$this->room->slug, $this->campaign]))
            ->assertOk()
            ->assertSee(__('admin.payable_original_total', ['amount' => \App\Support\Helpers\FormatHelper::formatCurrency(123000)]));
    }

    /**
     * The aggregated and department tabs show each line's payable share (delivery fee added, discount deducted)
     * next to its original total, and the shares add up exactly to the campaign total.
     *
     * @return void
     */
    public function test_aggregated_and_department_lines_show_payable_share_after_fee_and_discount(): void
    {
        $this->campaign->update(['delivery_fee' => 10000, 'discount' => 20000]);
        foreach ([['Dana Payable', 'Tầng 1', 'Trà', 30000], ['Evan Payable', 'Tầng 2', 'Cà phê', 60000]] as [$name, $desk, $itemName, $price]) {
            $member = RoomUser::create([
                'room_id' => $this->room->id,
                'global_user_id' => GlobalUser::create(['name' => $name, 'email' => str($name)->slug().'@example.test', 'desk_location' => $desk])->id,
                'display_name' => $name,
                'status' => 'active',
            ]);
            $order = Order::create([
                'room_id' => $this->room->id, 'campaign_id' => $this->campaign->id, 'room_user_id' => $member->id,
                'subtotal' => $price, 'final_amount' => $price, 'status' => OrderStatus::Submitted,
            ]);
            $order->items()->create(['item_name' => $itemName, 'unit_price' => $price, 'quantity' => 1, 'line_subtotal' => $price]);
        }

        $response = $this->actingAs($this->admin, 'admin')->get(route('admin.campaigns.orders', [$this->room->slug, $this->campaign]))
            ->assertOk()
            ->assertSee(__('admin.payable_original_total', ['amount' => \App\Support\Helpers\FormatHelper::formatCurrency(60000)]));

        // 90.000 + 10.000 - 20.000 = 80.000, split 1:2 over the lines with the rounding remainder kept in the total.
        $this->assertSame(
            [['Trà', 30000, 26667], ['Cà phê', 60000, 53333]],
            $response->viewData('aggregatedItems')->map(fn (array $item): array => [$item['name'], $item['total_amount'], $item['payable_amount']])->all(),
        );
        $departments = $response->viewData('departmentGroups');
        $this->assertSame([26667, 53333], $departments->pluck('payable_amount')->all());
        $this->assertSame([26667, 53333], $departments->map(fn (array $dept): int => (int) $dept['items']->sum('payable_amount'))->all());
    }

    /**
     * The campaign's order list and the admin order management page show the newest order first.
     *
     * @return void
     */
    public function test_order_lists_are_sorted_newest_first(): void
    {
        foreach (['Oldest Orderer' => 3, 'Newest Orderer' => 1, 'Middle Orderer' => 2] as $name => $hoursAgo) {
            $member = RoomUser::create([
                'room_id' => $this->room->id,
                'global_user_id' => GlobalUser::create(['name' => $name, 'email' => str($name)->slug().'@example.test'])->id,
                'display_name' => $name,
                'status' => 'active',
            ]);
            $order = Order::create([
                'room_id' => $this->room->id, 'campaign_id' => $this->campaign->id, 'room_user_id' => $member->id,
                'subtotal' => 30000, 'final_amount' => 30000, 'status' => OrderStatus::Submitted,
                // The note is only rendered in the order rows, so it pins down the row order.
                'note' => 'note-'.$hoursAgo.'h',
            ]);
            $order->items()->create(['item_name' => 'Trà', 'unit_price' => 30000, 'quantity' => 1, 'line_subtotal' => 30000]);
            $order->forceFill(['created_at' => now()->subHours($hoursAgo)])->saveQuietly();
        }

        // Notes are repeated later in the page's JSON data: compare where each one first appears (the table rows).
        $firstPositions = static fn (string $html): array => array_map(
            static fn (string $note): int|false => strpos($html, $note),
            ['note-1h', 'note-2h', 'note-3h'],
        );
        foreach ([
            route('admin.campaigns.orders', [$this->room->slug, $this->campaign]),
            route('admin.orders.page', $this->room->slug),
        ] as $url) {
            $positions = $firstPositions($this->actingAs($this->admin, 'admin')->get($url)->assertOk()->getContent());
            $this->assertNotContains(false, $positions, $url);
            $sorted = $positions;
            sort($sorted);
            $this->assertSame($sorted, $positions, $url);
        }
    }
}
