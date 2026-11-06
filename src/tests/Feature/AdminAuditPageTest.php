<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuditPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test admin audit page renders correctly with hidden target column and detail button/modal.
     *
     * @return void
     */
    public function test_admin_audit_page_hides_target_column_and_shows_details_modal(): void
    {
        $admin = Admin::create([
            'name' => 'Audit Admin',
            'email' => 'audit-admin@example.test',
            'password' => 'secret-password',
            'status' => 'active',
        ]);
        $room = Room::create([
            'name' => 'Công Nghệ',
            'slug' => 'cong-nghe',
            'status' => 'active',
        ]);
        $admin->rooms()->attach($room);

        $log = AuditLog::create([
            'room_id' => $room->id,
            'actor_type' => 'Admin',
            'actor_id' => $admin->id,
            'event' => 'campaign.create',
            'target_type' => 'Campaign',
            'target_id' => 101,
            'ip_address' => '192.168.1.100',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            'before_data' => null,
            'after_data' => ['name' => 'Trà Chiều', 'restaurant' => 'KOI Thé'],
            'metadata' => ['source' => 'admin_web'],
            'created_at' => now(),
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.audit.page', $room->slug));

        $response->assertOk();
        // Table header has Details button and timestamp, but not target object column in the ledger header
        $response->assertSee(__('admin.details'));
        $response->assertSee(__('admin.audit_overview'));
        $response->assertSee(__('admin.audit_changes'));
        $response->assertSee(__('admin.before_data'));
        $response->assertSee(__('admin.after_data'));
        $response->assertSee(__('admin.metadata'));
        $response->assertDontSee(__('admin.raw_json_payload'));
        $response->assertSee('class="sr-only">' . __('admin.details'), false);

        // Target column header should not be present in table
        $response->assertDontSee('<th class="py-3 px-4">' . __('admin.target_object') . '</th>', false);
    }

    /**
     * Logs written in the same second must still split into stable, non-overlapping pages
     * (newest id first), otherwise rows repeat or disappear between pages.
     *
     * @return void
     */
    public function test_audit_pagination_is_stable_when_logs_share_the_same_timestamp(): void
    {
        $admin = Admin::create([
            'name' => 'Audit Pager',
            'email' => 'audit-pager@example.test',
            'password' => 'secret-password',
            'status' => 'active',
        ]);
        $room = Room::create(['name' => 'Pager', 'slug' => 'pager', 'status' => 'active']);
        $admin->rooms()->attach($room);

        $sameSecond = now()->startOfSecond();
        $perPage = \App\Constants\Pagination::ADMIN_PER_PAGE;
        $ids = [];
        for ($i = 0; $i < $perPage + 5; $i++) {
            $ids[] = AuditLog::create([
                'room_id' => $room->id,
                'actor_type' => 'Admin',
                'actor_id' => $admin->id,
                'event' => 'campaign.create',
                'target_type' => 'Campaign',
                'target_id' => $i + 1,
                'created_at' => $sameSecond,
            ])->id;
        }
        rsort($ids);

        $this->actingAs($admin, 'admin');
        $pageOne = $this->get(route('admin.audit.page', $room->slug))->assertOk()->viewData('logs');
        $pageTwo = $this->get(route('admin.audit.page', ['room' => $room->slug, 'page' => 2]))->assertOk()->viewData('logs');

        $this->assertSame(array_slice($ids, 0, $perPage), $pageOne->pluck('id')->all());
        $this->assertSame(array_slice($ids, $perPage), $pageTwo->pluck('id')->all());
        $this->assertStringContainsString('page=2', (string) $pageOne->nextPageUrl());
    }

    /**
     * Test audit event keys such as debt.payment_approved are properly translated.
     *
     * @return void
     */
    public function test_debt_payment_approved_and_other_audit_events_are_translated(): void
    {
        $admin = Admin::create([
            'name' => 'Audit Admin 2',
            'email' => 'audit-admin2@example.test',
            'password' => 'secret-password',
            'status' => 'active',
        ]);
        $room = Room::create([
            'name' => 'Marketing',
            'slug' => 'marketing',
            'status' => 'active',
        ]);
        $admin->rooms()->attach($room);

        AuditLog::create([
            'room_id' => $room->id,
            'actor_type' => 'Admin',
            'actor_id' => $admin->id,
            'event' => 'debt.payment_approved',
            'target_type' => 'debt',
            'target_id' => 88,
            'ip_address' => '127.0.0.1',
            'created_at' => now(),
        ]);

        AuditLog::create([
            'room_id' => $room->id,
            'actor_type' => 'Admin',
            'actor_id' => $admin->id,
            'event' => 'admin.logged_in',
            'target_type' => 'admin',
            'target_id' => $admin->id,
            'ip_address' => '127.0.0.1',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.audit.page', $room->slug));

        $response->assertOk();
        $response->assertSee(__('admin.audit_event_debt_payment_approved'));
        $response->assertSee(__('admin.audit_event_admin_logged_in'));
        $response->assertSee(__('admin.audit_target_debt'));
        $response->assertSee(__('admin.audit_target_admin'));
    }
}
