<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\User\JoinRoomAction;
use App\Enums\CampaignStatus;
use App\Enums\PaymentAccountStatus;
use App\Models\Campaign;
use App\Models\CampaignItem;
use App\Models\GlobalUser;
use App\Models\PaymentAccount;
use App\Models\Room;
use App\Models\RoomUser;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignOrderingAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The menu offers a grid / list display toggle (grid by default, remembered in the browser storage).
     *
     * @return void
     */
    public function test_menu_offers_grid_and_list_display_modes(): void
    {
        [$user, $room] = $this->createRoomMember('open');
        $this->createCampaignWithItem($room, CampaignStatus::Active, now()->addHour());

        $this->actingAs($user, 'web')
            ->get(route('user.campaigns.index', $room))
            ->assertOk()
            ->assertSee('data-menu-view-toggle', false)
            ->assertSee('data-menu-view="grid"', false)
            ->assertSee('data-menu-view="list"', false)
            ->assertSee('data-menu-items', false)
            ->assertSee('data-list-price', false)
            // The item has no description: the card and the image viewer show the default one.
            ->assertSee('data-lightbox-description="'.e(__('room.campaign.default_drink_desc')).'"', false)
            ->assertSee("drinkflow.campaign.menu_view", false)
            ->assertSee(__('room.campaign.menu_view_label'))
            ->assertSee(__('room.campaign.menu_view_grid'))
            ->assertSee(__('room.campaign.menu_view_list'));
    }

    /**
     * Verify that an active campaign before its deadline displays ordering controls.
     *
     * @return void
     */
    public function test_live_campaign_before_deadline_allows_ordering(): void
    {
        [$user, $room, $roomUser] = $this->createRoomMember('open');
        [$campaign, $item] = $this->createCampaignWithItem($room, CampaignStatus::Active, now()->addHour());

        $this->actingAs($user, 'web')
            ->get(route('user.campaigns.index', $room))
            ->assertOk()
            ->assertSee('data-menu-item-image', false)
            ->assertSee('green-tea.webp', false)
            ->assertSee('data-add-to-cart-button', false)
            ->assertSee('data-campaign-cart-button', false);

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->actingAs($user, 'web')
            ->postJson(route('user.campaigns.cart.store', [$room, $campaign]), [
                'item_id' => $item->id,
                'quantity' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.0.image_url', '/storage/uploads/campaigns/green-tea.webp');

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->actingAs($user, 'web')
            ->postJson(route('user.orders.store', [$room, $campaign]), [
                'items' => [['item_id' => $item->id, 'quantity' => 1]],
            ])
            ->assertCreated();

        $this->assertDatabaseHas('orders', [
            'campaign_id' => $campaign->id,
            'room_user_id' => $roomUser->id,
        ]);
    }

    /**
     * The campaign page must let a member open a modal listing every room member's orders for
     * the live campaign, reusing the same JSON endpoint and modal as the debts page.
     *
     * @return void
     */
    public function test_campaign_page_shows_room_orders_button_and_modal(): void
    {
        [$user, $room] = $this->createRoomMember('room-orders');
        [$campaign] = $this->createCampaignWithItem($room, CampaignStatus::Active, now()->addHour());

        $this->actingAs($user, 'web')
            ->get(route('user.campaigns.index', $room))
            ->assertOk()
            ->assertSee('openCampaignDetail('.$campaign->id.')', false)
            ->assertSee(__('room.campaign.view_room_orders_button'))
            ->assertSee('campaignModalOpen', false);
    }

    /**
     * Verify that the campaign banner renders persisted policy and payment details.
     *
     * @return void
     */
    public function test_campaign_banner_uses_campaign_policy_and_payment_data(): void
    {
        [$user, $room, $roomUser] = $this->createRoomMember('policy-banner');
        [$campaign] = $this->createCampaignWithItem($room, CampaignStatus::Active, now()->addHour());
        $paymentAccount = PaymentAccount::create([
            'room_id' => $room->id,
            'bank_code' => 'VCB',
            'bank_name' => 'Vietcombank',
            'account_number' => '0123456789',
            'account_name' => 'DRINKFLOW TEAM',
            'is_default' => true,
            'status' => PaymentAccountStatus::Active,
        ]);
        $campaign->update([
            'code' => 'CPN-TECH-001',
            'sponsor_name' => 'Technology Fund',
            'sponsor_type' => 'full',
            'sponsor_description' => 'Quarterly team benefit',
            'sponsor_allocations' => [[
                'room_user_id' => $roomUser->id,
                'percentage' => 100,
            ]],
            'max_budget' => 75_000,
            'payment_account_id' => $paymentAccount->id,
        ]);

        $this->actingAs($user, 'web')
            ->get(route('user.campaigns.index', $room))
            ->assertOk()
            ->assertSee('CPN-TECH-001')
            ->assertDontSee(__('room.campaign.active_run_badge'))
            ->assertDontSee('4.9')
            ->assertSee(__('room.campaign.sponsor_type_full'))
            ->assertSee($user->name)
            ->assertSee('100%')
            ->assertSee('75.000đ')
            // Transfer payments name the receiving account holder, but never expose bank or account number.
            ->assertSee(__('room.campaign.policy_payment_val').' - DRINKFLOW TEAM')
            ->assertDontSee('Technology Fund')
            ->assertDontSee('Vietcombank')
            ->assertDontSee('0123456789');
    }

    /**
     * Verify that an expired live campaign hides ordering controls and rejects cart changes.
     *
     * @return void
     */
    public function test_expired_live_campaign_hides_cart_and_rejects_cart_submission(): void
    {
        [$user, $room] = $this->createRoomMember('expired-cart');
        [$campaign, $item] = $this->createCampaignWithItem($room, CampaignStatus::Active, now()->subMinute());

        $this->actingAs($user, 'web')
            ->get(route('user.campaigns.index', $room))
            ->assertOk()
            ->assertDontSee('data-add-to-cart-button', false)
            ->assertDontSee('data-campaign-cart-button', false)
            ->assertDontSee('data-participation-form', false)
            ->assertSee(__('room.header.countdown_closed'))
            ->assertSee(__('room.campaign.ordering_closed'));

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->actingAs($user, 'web')
            ->postJson(route('user.campaigns.cart.store', [$room, $campaign]), [
                'item_id' => $item->id,
                'quantity' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('campaign');

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->actingAs($user, 'web')
            ->postJson(route('user.campaigns.decline', [$room, $campaign]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('campaign');

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->actingAs($user, 'web')
            ->postJson(route('user.campaigns.rejoin', [$room, $campaign]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('campaign');

        $this->assertDatabaseCount('campaign_participants', 0);
    }

    /**
     * Verify that a direct order request is rejected after the campaign deadline.
     *
     * @return void
     */
    public function test_expired_live_campaign_rejects_direct_order_submission(): void
    {
        [$user, $room] = $this->createRoomMember('expired-order');
        [$campaign, $item] = $this->createCampaignWithItem($room, CampaignStatus::Active, now()->subSecond());

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->actingAs($user, 'web')
            ->postJson(route('user.orders.store', [$room, $campaign]), [
                'items' => [['item_id' => $item->id, 'quantity' => 1]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('campaign');

        $this->assertDatabaseCount('orders', 0);
    }

    /**
     * Verify that a scheduled campaign cannot accept a forged direct order request.
     *
     * @return void
     */
    public function test_non_live_campaign_rejects_direct_order_submission(): void
    {
        [$user, $room] = $this->createRoomMember('scheduled');
        [$campaign, $item] = $this->createCampaignWithItem($room, CampaignStatus::Scheduled, now()->addHour());

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->actingAs($user, 'web')
            ->postJson(route('user.orders.store', [$room, $campaign]), [
                'items' => [['item_id' => $item->id, 'quantity' => 1]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('campaign');

        $this->assertDatabaseCount('orders', 0);
    }

    /**
     * Create an active global user and room membership for an ordering scenario.
     *
     * @param string $suffix Unique value used for test data.
     * @return array{0: GlobalUser, 1: Room, 2: RoomUser} Created user, room, and membership.
     */
    private function createRoomMember(string $suffix): array
    {
        $user = GlobalUser::create([
            'name' => 'Campaign User',
            'normalized_name' => 'CAMPAIGN USER',
            'email' => "campaign-{$suffix}@company.com",
        ]);
        $room = Room::create([
            'name' => "Campaign Room {$suffix}",
            'slug' => "campaign-room-{$suffix}",
        ]);
        $roomUser = app(JoinRoomAction::class)->execute(
            $user,
            $room,
            "device-{$suffix}",
            "hash-{$suffix}",
        );

        return [$user, $room, $roomUser];
    }

    /**
     * Verify the item-customization modal keeps its topping checkboxes reactively bound to the
     * selection state, and resets that state whenever the modal is dismissed without confirming —
     * otherwise a previously checked topping stays visually checked (via stale DOM state) on reopen
     * without being priced in or sent to the backend.
     *
     * @return void
     */
    public function test_item_customize_modal_resets_toppings_when_closed(): void
    {
        [$user, $room] = $this->createRoomMember('toppings');
        [, $item] = $this->createCampaignWithItem($room, CampaignStatus::Active, now()->addHour());
        $item->toppings()->create(['name' => 'Trân châu', 'price' => 5000]);

        $html = $this->actingAs($user, 'web')
            ->get(route('user.campaigns.index', $room))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(':checked="selectedToppings.some(t => t.id === top.id)"', $html);
        $this->assertStringContainsString('closeCustomModal()', $html);
        $this->assertStringContainsString("closeCustomModal() {\n      this.showCustomModal = false;\n      this.selectedToppings = [];", $html);
    }

    /**
     * Create a campaign and one active item for an ordering scenario.
     *
     * @param Room $room Room owning the campaign.
     * @param CampaignStatus $status Campaign lifecycle status.
     * @param \DateTimeInterface $deadline Campaign ordering deadline.
     * @return array{0: Campaign, 1: CampaignItem} Created campaign and item.
     */
    private function createCampaignWithItem(Room $room, CampaignStatus $status, \DateTimeInterface $deadline): array
    {
        $campaign = Campaign::create([
            'room_id' => $room->id,
            'name' => 'Campaign availability',
            'restaurant' => 'Cafe',
            'status' => $status,
            'deadline' => $deadline,
        ]);
        $item = CampaignItem::create([
            'campaign_id' => $campaign->id,
            'name' => 'Green tea',
            'normalized_name' => 'GREEN TEA',
            'base_price' => 20000,
            'image_url' => '/storage/uploads/campaigns/green-tea.webp',
            'status' => 'active',
        ]);

        return [$campaign, $item];
    }
}
