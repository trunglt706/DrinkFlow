<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Enums\GlobalUserStatus;
use App\Enums\OrderStatus;
use App\Enums\RoomStatus;
use App\Enums\RoomUserStatus;
use App\Jobs\SendRoomNotificationJob;
use App\Models\AdminNotification;
use App\Models\Campaign;
use App\Models\GlobalUser;
use App\Models\Order;
use App\Models\Room;
use App\Models\RoomUser;
use App\Models\RoomUserDevice;
use App\Models\UserNotification;
use App\Services\Notification\UserNotificationService;
use App\Support\Helpers\QueryHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OperationsAndHardeningUpgradeTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_room_notification_job_bulk_inserts_for_active_members(): void
    {
        $room = Room::create(['name' => 'Notify Room', 'slug' => 'notify-room', 'status' => RoomStatus::Active]);
        $activeUser1 = GlobalUser::create(['name' => 'U1', 'email' => 'u1@test.com', 'status' => GlobalUserStatus::Active]);
        $activeUser2 = GlobalUser::create(['name' => 'U2', 'email' => 'u2@test.com', 'status' => GlobalUserStatus::Active]);
        $blockedUser = GlobalUser::create(['name' => 'U3', 'email' => 'u3@test.com', 'status' => GlobalUserStatus::Blocked]);

        $m1 = RoomUser::create(['room_id' => $room->id, 'global_user_id' => $activeUser1->id, 'user_code' => 'N01', 'display_name' => 'U1', 'status' => RoomUserStatus::Active]);
        $m2 = RoomUser::create(['room_id' => $room->id, 'global_user_id' => $activeUser2->id, 'user_code' => 'N02', 'display_name' => 'U2', 'status' => RoomUserStatus::Active]);
        $m3 = RoomUser::create(['room_id' => $room->id, 'global_user_id' => $blockedUser->id, 'user_code' => 'N03', 'display_name' => 'U3', 'status' => RoomUserStatus::Active]);

        // Test dispatch via service
        Queue::fake([SendRoomNotificationJob::class]);
        app(UserNotificationService::class)->toRoom($room, 'room.notice', 'Announcement', 'Hello everyone');
        Queue::assertPushed(SendRoomNotificationJob::class);

        // Test job execution
        $job = new SendRoomNotificationJob($room, 'room.notice', 'Announcement', 'Hello everyone', ['extra' => 123], '/link');
        $job->handle();

        $notifications = UserNotification::where('type', 'room.notice')->get();
        $this->assertCount(2, $notifications);
        $this->assertEqualsCanonicalizing([$activeUser1->id, $activeUser2->id], $notifications->pluck('global_user_id')->all());
    }

    public function test_prune_auxiliary_data_command_cleans_expired_records(): void
    {
        Carbon::setTestNow('2026-10-15 12:00:00');
        $user = GlobalUser::create(['name' => 'Prune User', 'email' => 'prune@test.com', 'status' => GlobalUserStatus::Active]);
        $room = Room::create(['name' => 'Prune Room', 'slug' => 'prune-room', 'status' => RoomStatus::Active]);
        $member = RoomUser::create(['room_id' => $room->id, 'global_user_id' => $user->id, 'user_code' => 'PRN01', 'display_name' => 'Prune User', 'status' => RoomUserStatus::Active]);

        // 1. Old revoked device (> 30 days) vs Recent revoked device (5 days)
        $oldRevoked = RoomUserDevice::create([
            'room_user_id' => $member->id,
            'device_uuid' => 'dev-old-revoked',
            'token_hash' => 'hash1',
            'revoked_at' => Carbon::parse('2026-09-01 10:00:00'),
        ]);
        $recentRevoked = RoomUserDevice::create([
            'room_user_id' => $member->id,
            'device_uuid' => 'dev-recent-revoked',
            'token_hash' => 'hash2',
            'revoked_at' => Carbon::parse('2026-10-12 10:00:00'),
        ]);

        // 2. Old read notification (> 30 days) vs Unread notification
        $oldReadNotice = UserNotification::create([
            'global_user_id' => $user->id,
            'type' => 'test',
            'title' => 'Old Read',
            'read_at' => Carbon::parse('2026-09-01 10:00:00'),
        ]);
        $unreadNotice = UserNotification::create([
            'global_user_id' => $user->id,
            'type' => 'test',
            'title' => 'Unread',
            'read_at' => null,
        ]);

        $exitCode = Artisan::call('drinkflow:prune');
        $this->assertSame(0, $exitCode);

        $this->assertDatabaseMissing('room_user_devices', ['id' => $oldRevoked->id]);
        $this->assertDatabaseHas('room_user_devices', ['id' => $recentRevoked->id]);

        $this->assertDatabaseMissing('user_notifications', ['id' => $oldReadNotice->id]);
        $this->assertDatabaseHas('user_notifications', ['id' => $unreadNotice->id]);
    }

    public function test_query_helper_escapes_like_wildcards_and_sanitizes_terms(): void
    {
        $this->assertSame('100\\% Juice', QueryHelper::escapeLike('100% Juice'));
        $this->assertSame('test\\_item', QueryHelper::escapeLike('test_item'));
        $this->assertSame('back\\\\slash', QueryHelper::escapeLike('back\\slash'));

        $longString = str_repeat('a', 150);
        $this->assertSame(100, mb_strlen(QueryHelper::sanitizeSearchTerm($longString)));
    }
}
