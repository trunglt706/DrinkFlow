<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Enums\OrderStatus;
use App\Models\Admin;
use App\Models\Campaign;
use App\Models\GlobalUser;
use App\Models\Order;
use App\Models\Room;
use App\Models\RoomUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminReportsVisualsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Report tabs are Vietnamese only, and the member cells get the texts for their email copy button.
     *
     * @return void
     */
    public function test_tabs_are_vietnamese_only_and_email_copy_texts_are_provided(): void
    {
        $admin = Admin::create(['name' => 'Report Admin', 'email' => 'report-tabs@example.test', 'password' => Hash::make('secret'), 'status' => 'active']);
        $room = Room::create(['name' => 'Report Tabs Room', 'slug' => 'report-tabs-room', 'status' => 'active']);
        $admin->rooms()->attach($room);

        $html = $this->actingAs($admin, 'admin')->withSession(['locale' => 'vi'])
            ->get(route('admin.reports.page', $room->slug))
            ->assertOk()
            ->getContent();

        $tabs = (string) str($html)->after('id="report-tabs"')->before('<!-- Tab 1');
        foreach (['Chiến dịch', 'Đồ uống &amp; Món', 'Công nợ', 'Tài trợ &amp; Quỹ', 'Thành viên'] as $label) {
            $this->assertStringContainsString('<span>' . $label . '</span>', $tabs);
        }
        $this->assertDoesNotMatchRegularExpression('/<span>[^<]*\([A-Za-z &]+\)<\/span>/', $tabs);

        // memberCellHtml() in reports.js reads these for the copy button after each email.
        $i18n = json_decode(html_entity_decode((string) str($html)->after('id="report-tabs" data-i18n="')->before('"')), true);
        $this->assertSame(__('admin.copy_email'), $i18n['copyEmail']);
        // Success is shown by the icon (data-copy-feedback="icon"), so only the failure text is needed.
        $this->assertSame(__('admin.copy_failed'), $i18n['copyFailed']);
    }

    /**
     * Participation bars are colored by band: below 50%, below 80%, and 80% or more.
     *
     * @return void
     */
    public function test_participation_ratio_uses_color_bands(): void
    {
        $admin = Admin::create(['name' => 'Report Admin', 'email' => 'report-visuals@example.test', 'password' => Hash::make('secret'), 'status' => 'active']);
        $room = Room::create(['name' => 'Report Room', 'slug' => 'report-visuals-room', 'status' => 'active']);
        $admin->rooms()->attach($room);

        $members = collect(range(1, 10))->map(fn (int $i): RoomUser => RoomUser::create([
            'room_id' => $room->id,
            'global_user_id' => GlobalUser::create(['name' => "Member {$i}", 'email' => "report-member-{$i}@example.test", 'status' => 'active'])->id,
            'display_name' => "Member {$i}",
            'status' => 'active',
        ]));
        // 3/10 = 30% (low), 6/10 = 60% (medium), 10/10 = 100% (high).
        foreach (['Low Campaign' => 3, 'Medium Campaign' => 6, 'High Campaign' => 10] as $name => $orders) {
            $campaign = Campaign::create(['room_id' => $room->id, 'name' => $name, 'restaurant' => 'Cafe', 'status' => CampaignStatus::Closed]);
            $members->take($orders)->each(fn (RoomUser $member) => Order::create([
                'room_id' => $room->id, 'campaign_id' => $campaign->id, 'room_user_id' => $member->id,
                'subtotal' => 20000, 'final_amount' => 20000, 'status' => OrderStatus::Completed,
            ]));
        }

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.reports.page', $room->slug))
            ->assertOk()
            ->assertSee(__('admin.participation_low'))
            ->assertSee(__('admin.participation_medium'))
            ->assertSee(__('admin.participation_high'))
            ->getContent();

        foreach (['Low Campaign' => ['low', 'bg-rose-500'], 'Medium Campaign' => ['medium', 'bg-amber-500'], 'High Campaign' => ['high', 'bg-emerald-500']] as $name => [$band, $barClass]) {
            $block = (string) str($html)->after('data-participation-band="' . $band . '"')->before('data-participation-band=');
            $this->assertStringContainsString($name, $block, $band);
            $this->assertStringContainsString($barClass, $block, $band);
        }
    }
}
