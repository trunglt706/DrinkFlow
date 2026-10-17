<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CampaignItemStatus;
use App\Enums\CampaignStatus;
use App\Enums\PaymentAccountStatus;
use App\Models\Campaign;
use App\Models\CampaignItem;
use App\Models\GlobalUser;
use App\Models\PaymentAccount;
use App\Models\Room;
use App\Models\RoomUser;
use App\Services\Campaign\UserRoomCampaignService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignMenuCategoriesTest extends TestCase
{
    use RefreshDatabase;

    private Room $room;

    private Campaign $campaign;

    protected function setUp(): void
    {
        parent::setUp();

        $this->room = Room::create(['name' => 'Menu Room', 'slug' => 'menu-room', 'status' => 'active']);
        $this->campaign = Campaign::create([
            'room_id' => $this->room->id,
            'name' => 'Trà chiều',
            'restaurant' => 'Cafe',
            'status' => CampaignStatus::Active,
            'deadline' => now()->addHour(),
        ]);
    }

    private function item(string $name, ?string $category, CampaignItemStatus $status = CampaignItemStatus::Active): CampaignItem
    {
        return $this->campaign->items()->create([
            'name' => $name,
            'normalized_name' => strtoupper($name),
            'category' => $category,
            'base_price' => 30000,
            'status' => $status,
        ]);
    }

    public function test_category_is_trimmed_and_blank_category_is_stored_as_null(): void
    {
        $this->assertSame('Trà sữa', $this->item('A', "  Trà   sữa \n")->fresh()->category);
        $this->assertNull($this->item('B', '   ')->fresh()->category);
    }

    public function test_user_menu_only_lists_categories_that_have_an_orderable_item(): void
    {
        $this->item('Trà sữa trân châu', 'Trà sữa');
        $this->item('Trà sữa matcha', ' Trà sữa ');
        $this->item('Cà phê muối', 'Cà phê', CampaignItemStatus::Inactive);
        $this->item('Sinh tố bơ', 'Sinh tố', CampaignItemStatus::SoldOut);
        $this->item('Bánh flan', '   ');
        $this->item('Nước ép cam', 'Nước ép');

        $user = GlobalUser::create(['name' => 'Member', 'email' => 'menu-member@example.test', 'status' => 'active']);
        $roomUser = RoomUser::create(['room_id' => $this->room->id, 'global_user_id' => $user->id, 'display_name' => 'Member', 'status' => 'active']);

        $data = app(UserRoomCampaignService::class)->getCampaignViewData($this->room, $roomUser, $user);

        $this->assertSame(['Trà sữa', 'Nước ép'], $data['categories']->all());
    }

    public function test_payment_account_name_prefers_the_campaign_account_and_falls_back_to_the_room(): void
    {
        $account = fn (string $name): PaymentAccount => PaymentAccount::create([
            'room_id' => $this->room->id,
            'bank_code' => 'VCB',
            'bank_name' => 'Vietcombank',
            'account_number' => '0123456789',
            'account_name' => $name,
            'status' => PaymentAccountStatus::Active,
        ]);
        $service = app(UserRoomCampaignService::class);

        $this->assertNull($service->getCampaignViewData($this->room, null, null)['paymentAccountName']);

        $account('ROOM OWNER');
        $this->assertSame('ROOM OWNER', $service->getCampaignViewData($this->room, null, null)['paymentAccountName']);

        $this->campaign->update(['payment_account_id' => $account('CAMPAIGN OWNER')->id]);
        $this->assertSame('CAMPAIGN OWNER', $service->getCampaignViewData($this->room, null, null)['paymentAccountName']);
    }
}
