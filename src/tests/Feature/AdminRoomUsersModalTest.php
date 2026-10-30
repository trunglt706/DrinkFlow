<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RoomUserStatus;
use App\Models\Admin;
use App\Models\GlobalUser;
use App\Models\Room;
use App\Models\RoomUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminRoomUsersModalTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The shared user action modal ships an icon in the header and an icon inside the confirm button.
     */
    public function test_user_action_modal_has_header_and_confirm_icons(): void
    {
        $admin = Admin::create([
            'name' => 'Room Admin',
            'email' => 'users-admin@example.test',
            'password' => Hash::make('secret'),
            'status' => 'active',
        ]);
        $room = Room::create(['name' => 'Users Room', 'slug' => 'users-room', 'status' => 'active']);
        $admin->rooms()->attach($room);

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.room-users.page', $room->slug))
            ->assertOk()
            ->assertSee('id="user-action-icon-wrap"', false)
            ->assertSee('id="user-action-icon"', false)
            ->assertSee('id="user-action-confirm-icon"', false)
            ->assertSee('id="user-action-confirm-label"', false)
            ->assertSee('data-confirm-label="'.__('admin.confirm_action').'"', false);

        $html = $response->getContent();
        $this->assertSame(substr_count($html, '<div'), substr_count($html, '</div>'));
    }

    /**
     * Removed members get a restore button, and restoring puts them back to active.
     */
    public function test_admin_can_restore_removed_member(): void
    {
        $admin = Admin::create([
            'name' => 'Room Admin',
            'email' => 'restore-admin@example.test',
            'password' => Hash::make('secret'),
            'status' => 'active',
        ]);
        $room = Room::create(['name' => 'Users Room', 'slug' => 'users-room', 'status' => 'active']);
        $admin->rooms()->attach($room);
        $member = RoomUser::create([
            'room_id' => $room->id,
            'global_user_id' => GlobalUser::create(['name' => 'Gone Member', 'email' => 'gone@example.test', 'status' => 'active'])->id,
            'display_name' => 'Gone Member',
            'status' => RoomUserStatus::Removed,
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.room-users.page', $room->slug))
            ->assertOk()
            ->assertSee('data-restore-room-user', false);

        $url = route('admin.room-users.restore', [$room->slug, $member->id]);
        $this->actingAs($admin, 'admin')->postJson($url)->assertOk();

        $this->assertSame(RoomUserStatus::Active, $member->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['event' => 'room_user.status_updated', 'target_id' => $member->id]);

        // Restoring a member that is not removed is rejected.
        $this->actingAs($admin, 'admin')->postJson($url)->assertStatus(422)->assertJsonValidationErrors('room_user');

        // A member of another room cannot be restored through this room.
        $otherRoom = Room::create(['name' => 'Other', 'slug' => 'other-room', 'status' => 'active']);
        $foreign = RoomUser::create([
            'room_id' => $otherRoom->id,
            'global_user_id' => GlobalUser::create(['name' => 'Foreign', 'email' => 'foreign@example.test', 'status' => 'active'])->id,
            'display_name' => 'Foreign',
            'status' => RoomUserStatus::Removed,
        ]);
        $this->actingAs($admin, 'admin')
            ->postJson(route('admin.room-users.restore', [$room->slug, $foreign->id]))
            ->assertNotFound();
    }

    /**
     * The members table shows a "Tổng đơn hàng" column and when each member joined the room.
     */
    public function test_members_table_shows_total_orders_and_joined_at(): void
    {
        $admin = Admin::create([
            'name' => 'Room Admin',
            'email' => 'joined-admin@example.test',
            'password' => Hash::make('secret'),
            'status' => 'active',
        ]);
        $room = Room::create(['name' => 'Joined Room', 'slug' => 'joined-room', 'status' => 'active']);
        $admin->rooms()->attach($room);
        $joinedAt = now()->setDate(2026, 9, 1)->setTime(8, 45);
        RoomUser::create([
            'room_id' => $room->id,
            'global_user_id' => GlobalUser::create(['name' => 'Joined Member', 'email' => 'joined@example.test', 'status' => 'active'])->id,
            'display_name' => 'Joined Member',
            'status' => RoomUserStatus::Active,
            'joined_at' => $joinedAt,
        ]);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.room-users.page', $room->slug))
            ->assertOk()
            ->assertSee(__('admin.th_total_orders'))
            ->assertSee(__('admin.th_joined_at'))
            ->getContent();

        $this->assertSame('Tổng đơn hàng', trans('admin.th_total_orders', [], 'vi'));
        $this->assertSame('Ngày tham gia', trans('admin.th_joined_at', [], 'vi'));
        // Only the join date is shown, without the time.
        $this->assertMatchesRegularExpression('/data-member-joined-at>\s*<span class="text-on-surface">01\/09\/2026<\/span>\s*<\/td>/', $html);
        $this->assertStringNotContainsString('08:45', $html);
        // The status badge starts with an icon.
        $this->assertMatchesRegularExpression('/data-member-status-badge[^>]*>\s*<span class="material-symbols-outlined[^"]*"\s+aria-hidden="true">check_circle<\/span>/', $html);
    }

    /**
     * The members directory lists the most recently active member first; members who never visited come last.
     */
    public function test_members_directory_is_sorted_by_last_access_descending(): void
    {
        $admin = Admin::create([
            'name' => 'Room Admin',
            'email' => 'sort-admin@example.test',
            'password' => Hash::make('secret'),
            'status' => 'active',
        ]);
        $room = Room::create(['name' => 'Sort Room', 'slug' => 'sort-room', 'status' => 'active']);
        $admin->rooms()->attach($room);
        foreach (['Never Visited' => null, 'Visited Yesterday' => now()->subDay(), 'Visited Just Now' => now()->subMinute(), 'Visited Last Week' => now()->subWeek()] as $name => $lastActiveAt) {
            RoomUser::create([
                'room_id' => $room->id,
                'global_user_id' => GlobalUser::create(['name' => $name, 'email' => str($name)->slug().'@example.test', 'status' => 'active'])->id,
                'display_name' => $name,
                'status' => RoomUserStatus::Active,
                'last_active_at' => $lastActiveAt,
            ]);
        }

        $roomUsers = $this->actingAs($admin, 'admin')
            ->get(route('admin.room-users.page', $room->slug))
            ->assertOk()
            ->viewData('roomUsers');

        $this->assertSame(
            ['Visited Just Now', 'Visited Yesterday', 'Visited Last Week', 'Never Visited'],
            $roomUsers->getCollection()->pluck('display_name')->all(),
        );
    }
}
