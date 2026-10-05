<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Enums\NotificationType;
use App\Enums\GlobalUserStatus;
use App\Enums\RoomUserStatus;
use App\Events\UserNotificationCreated;
use App\Models\GlobalUser;
use App\Models\Room;
use App\Models\RoomUser;
use App\Models\UserNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class UserNotificationService
{
    /**
     * Gửi thông báo đến toàn bộ thành viên đang hoạt động trong phòng qua Queue Job + bulk insert.
     *
     * @param  \App\Models\Room  $room  Đối tượng phòng nhận thông báo
     * @param  string  $type  Loại thông báo (ví dụ: campaign.created, room.notice)
     * @param  string  $title  Tiêu đề thông báo
     * @param  string|null  $body  Nội dung chi tiết thông báo
     * @param  array<string, mixed>  $data  Dữ liệu bổ sung (metadata/payload)
     * @param  string|null  $link  Đường dẫn liên kết đính kèm thông báo
     * @return void
     */
    public function toRoom(Room $room, string $type, string $title, ?string $body = null, array $data = [], ?string $link = null): void
    {
        \App\Jobs\SendRoomNotificationJob::dispatch($room, $type, $title, $body, $data, $link);
    }

    /**
     * Gửi thông báo đến một thành viên phòng cụ thể.
     *
     * @param  \App\Models\RoomUser  $roomUser  Thành viên phòng nhận thông báo
     * @param  string  $type  Loại thông báo
     * @param  string  $title  Tiêu đề thông báo
     * @param  string|null  $body  Nội dung chi tiết
     * @param  array<string, mixed>  $data  Dữ liệu bổ sung
     * @param  string|null  $link  Đường dẫn liên kết đính kèm thông báo
     * @return \App\Models\UserNotification  Bản ghi thông báo vừa tạo
     */
    public function toRoomUser(RoomUser $roomUser, string $type, string $title, ?string $body = null, array $data = [], ?string $link = null): UserNotification
    {
        $notification = UserNotification::create([
            'global_user_id' => $roomUser->global_user_id,
            'room_user_id' => $roomUser->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'link' => $link,
            'data' => $data,
        ]);
        UserNotificationCreated::dispatch($notification);

        return $notification;
    }

    /**
     * Gửi thông báo toàn hệ thống đến người dùng cụ thể.
     *
     * @param  \App\Models\GlobalUser  $user  Tài khoản người dùng toàn hệ thống
     * @param  string  $type  Loại thông báo
     * @param  string  $title  Tiêu đề thông báo
     * @param  string|null  $body  Nội dung chi tiết
     * @param  array<string, mixed>  $data  Dữ liệu bổ sung
     * @param  string|null  $link  Đường dẫn liên kết đính kèm thông báo
     * @return \App\Models\UserNotification  Bản ghi thông báo vừa tạo
     */
    public function toGlobalUser(GlobalUser $user, string $type, string $title, ?string $body = null, array $data = [], ?string $link = null): UserNotification
    {
        $notification = UserNotification::create([
            'global_user_id' => $user->id,
            'room_user_id' => null,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'link' => $link,
            'data' => $data,
        ]);
        UserNotificationCreated::dispatch($notification);

        return $notification;
    }
    /**
     * Lấy danh sách thông báo phân trang theo từng tab và tính số lượng thông báo theo danh mục.
     *
     * @param  \App\Models\GlobalUser  $user  Tài khoản người dùng toàn hệ thống
     * @param  \Illuminate\Http\Request  $request  Đối tượng HTTP Request chứa query tab
     * @return array<string, mixed>  Mảng dữ liệu cho View thông báo
     */
    public function getNotificationsPageData(GlobalUser $user, Request $request): array
    {
        $tab = $request->query('tab', 'all');
        $baseQuery = $user->notifications();

        // Calculate counts for filter tabs
        $allCount = (clone $baseQuery)->count();
        $unreadCount = (clone $baseQuery)->whereNull('read_at')->count();
        $roomOrderTypes = [NotificationType::CampaignCreated->value, NotificationType::CampaignDeadlineReminder->value, NotificationType::OrderStatus->value, NotificationType::OrderProxyReceived->value, NotificationType::RoomInvite->value];
        $paymentTypes = [NotificationType::PaymentDue->value, NotificationType::PaymentConfirmed->value, NotificationType::DebtReminder->value];
        $profileTypes = [NotificationType::SecurityAlert->value, NotificationType::DeviceNew->value];
        $otherTypes = [NotificationType::AdminBroadcast->value, NotificationType::NotificationTest->value];
        $roomOrderCount = (clone $baseQuery)->whereIn('type', $roomOrderTypes)->count();
        $paymentCount = (clone $baseQuery)->whereIn('type', $paymentTypes)->count();
        $profileCount = (clone $baseQuery)->whereIn('type', $profileTypes)->count();
        $otherCount = (clone $baseQuery)->whereIn('type', $otherTypes)->count();

        // Query by active tab
        $query = (clone $baseQuery)->latest();
        if ($tab === 'unread') {
            $query->whereNull('read_at');
        } elseif ($tab === 'room_order') {
            $query->whereIn('type', $roomOrderTypes);
        } elseif ($tab === 'payment') {
            $query->whereIn('type', $paymentTypes);
        } elseif ($tab === 'profile') {
            $query->whereIn('type', $profileTypes);
        } elseif ($tab === 'other') {
            $query->whereIn('type', $otherTypes);
        }

        $notifications = $query->paginate(15)->appends(['tab' => $tab]);

        $breadcrumbs = [
            ['label' => __('global.rooms.breadcrumb_personal'), 'url' => route('user.me.dashboard')],
            ['label' => __('global.notifications.breadcrumb_notifications'), 'url' => route('user.me.notifications')],
        ];

        return compact(
            'user',
            'tab',
            'allCount',
            'unreadCount',
            'roomOrderCount',
            'paymentCount',
            'profileCount',
            'otherCount',
            'notifications',
            'breadcrumbs'
        );
    }

    /**
     * Lấy danh sách thông báo phân trang dạng JSON API.
     *
     * @param  \App\Models\GlobalUser  $user  Tài khoản người dùng toàn hệ thống
     * @param  \Illuminate\Http\Request  $request  Đối tượng HTTP Request chứa tùy chọn lọc unread
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator  Phân trang thông báo
     */
    public function getNotificationsApiData(GlobalUser $user, Request $request): LengthAwarePaginator
    {
        $query = $user->notifications()->latest();
        if ($request->boolean('unread')) {
            $query->whereNull('read_at');
        }

        return $query->paginate(30);
    }

    /**
     * Đánh dấu toàn bộ thông báo chưa đọc của người dùng thành đã đọc.
     *
     * @param  \App\Models\GlobalUser  $user  Tài khoản người dùng toàn hệ thống
     * @return int Number of notifications marked as read.
     */
    public function markAllAsRead(GlobalUser $user): int
    {
        return $user->notifications()->whereNull('read_at')->update(['read_at' => now()]);
    }

    /**
     * Đánh dấu một thông báo cụ thể thành đã đọc.
     *
     * @param  \App\Models\GlobalUser  $user  Tài khoản người dùng sở hữu thông báo
     * @param  \App\Models\UserNotification  $notification  Bản ghi thông báo cần đánh dấu
     * @return \App\Models\UserNotification  Bản ghi thông báo sau khi cập nhật
     */
    public function markAsRead(GlobalUser $user, UserNotification $notification): UserNotification
    {
        abort_unless($notification->global_user_id === $user->id, 404);
        $notification->update(['read_at' => now()]);

        return $notification->fresh();
    }
}
