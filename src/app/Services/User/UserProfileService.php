<?php

declare(strict_types=1);

namespace App\Services\User;

use App\Enums\GlobalUserStatus;
use App\Enums\OrderStatus;
use App\Enums\RoomUserStatus;
use App\Models\GlobalUser;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Media\ImageUploadService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UserProfileService
{
    public function __construct(
        protected ImageUploadService $imageUploadService
    ) {}

    /**
     * Thu thập toàn bộ dữ liệu hồ sơ cá nhân toàn hệ thống, cấp bậc thành viên (tier gamification), tùy chọn ăn uống và tài khoản ngân hàng.
     *
     * @param  GlobalUser  $user  Tài khoản người dùng toàn hệ thống
     * @return array<string, mixed> Mảng dữ liệu cho View Profile
     */
    public function getProfileData(GlobalUser $user): array
    {
        // Extract Workspace domain from user email
        $domain = Str::after((string) $user->email, '@');
        $workspaceText = __('global.profile.workspace_auth_domain', ['domain' => $domain ?: 'company.com']);

        // Query active room users of current user
        $roomUsers = $user->roomUsers()
            ->with([
                'room' => function ($q) {
                    $q->with([
                        'paymentAccounts' => function ($pa) {
                            $pa->where('status', RoomUserStatus::Active->value);
                        },
                    ]);
                },
            ])
            ->where('status', RoomUserStatus::Active->value)
            ->orderByDesc('last_active_at')
            ->orderByDesc('updated_at')
            ->get();

        $primaryRoomUser = $roomUsers->first();
        $primaryRoom = $primaryRoomUser?->room;
        $department = $primaryRoom?->name ?? __('global.profile.default_dept');
        $userCode = $primaryRoomUser?->user_code ?? $user->code;
        $role = $primaryRoomUser ? __('global.profile.role_member', ['name' => $primaryRoom->name]) : __('global.profile.default_role');

        // Order metrics across all user memberships
        $roomUserIds = $roomUsers->pluck('id');
        $ordersQuery = Order::query()->whereIn('room_user_id', $roomUserIds);
        $totalOrdersCount = (clone $ordersQuery)->count();

        $completedStatuses = [
            OrderStatus::Submitted->value,
            OrderStatus::Confirmed->value,
            OrderStatus::Completed->value,
            'submitted',
            'confirmed',
            'paid',
            'completed',
        ];

        $totalSpent = (int) (clone $ordersQuery)->whereIn('status', $completedStatuses)->sum('final_amount');
        $paidOrdersCount = (clone $ordersQuery)->whereIn('status', ['paid', 'completed', OrderStatus::Completed->value])->count();
        $totalCups = (int) OrderItem::query()
            ->whereIn('order_id', function ($q) use ($roomUserIds) {
                $q->select('id')->from('orders')
                    ->whereIn('room_user_id', $roomUserIds)
                    ->where('status', OrderStatus::Completed->value);
            })
            ->sum('quantity');

        $paymentRate = $totalOrdersCount > 0 ? (int) round(($paidOrdersCount / $totalOrdersCount) * 100) : 100;

        // Gamification / Member Level tier
        if ($totalOrdersCount >= 30) {
            $memberLevel = 'Diamond';
            $memberTitle = __('global.profile.tier_diamond_title');
            $memberSubtitle = __('global.profile.tier_diamond_sub');
            $badgeIcon = 'military_tech';
        } elseif ($totalOrdersCount >= 10) {
            $memberLevel = 'Gold';
            $memberTitle = __('global.profile.tier_gold_title');
            $memberSubtitle = __('global.profile.tier_gold_sub');
            $badgeIcon = 'workspace_premium';
        } else {
            $memberLevel = 'Standard';
            $memberTitle = __('global.profile.tier_standard_title');
            $memberSubtitle = __('global.profile.tier_standard_sub');
            $badgeIcon = 'verified';
        }

        $joinedDate = $user->created_at ? $user->created_at->translatedFormat('m/Y') : now()->translatedFormat('m/Y');
        $joinedDuration = $user->created_at ? $user->created_at->diffForHumans(['parts' => 1]) : __('global.profile.just_joined');

        // Bank / Payment info from real database
        $defaultPayment = $primaryRoom?->paymentAccounts->firstWhere('is_default', true)
            ?? $primaryRoom?->paymentAccounts->first();
        $bankData = $defaultPayment ? [
            'bank_name' => $defaultPayment->bank_name,
            'bank_code' => $defaultPayment->bank_code,
            'account_number' => $defaultPayment->getRawOriginal('account_number'),
            'account_name' => $defaultPayment->account_name,
            'branch' => __('global.profile.default_branch'),
        ] : null;

        // Preferences & contact details (Real database fields)
        $phone = $user->phone ?? '';
        $deskLocation = $user->desk_location ?? '';
        $deliveryLocation = $user->delivery_location ?? '';
        $bio = (string) ($user->bio ?? '');
        // Stored as Markdown; raw HTML is escaped when rendered.
        $bioHtml = \App\Support\Helpers\MarkdownHelper::toSafeHtml($bio);
        $bioMaxWords = GlobalUser::BIO_MAX_WORDS;
        $birthday = $user->birthday?->toDateString() ?? '';
        $preferences = is_array($user->preferences) ? $user->preferences : [];
        $sugar = $preferences['sugar'] ?? '';
        $ice = $preferences['ice'] ?? '';
        $toppings = $preferences['toppings'] ?? [];
        $orderNote = $preferences['note'] ?? '';
        $notifyCampaign = $preferences['notify_campaign'] ?? true;
        $notifySound = $preferences['notify_sound'] ?? true;

        $breadcrumbs = [
            ['label' => __('global.rooms.breadcrumb_personal'), 'url' => route('user.me.dashboard')],
            ['label' => __('global.profile.breadcrumb_profile'), 'url' => route('user.me.profile')],
        ];

        $hasRooms = $user->roomUsers()->where('status', RoomUserStatus::Active->value)->exists();

        return compact(
            'user',
            'hasRooms',
            'workspaceText',
            'department',
            'userCode',
            'role',
            'totalOrdersCount',
            'totalSpent',
            'totalCups',
            'paymentRate',
            'memberLevel',
            'memberTitle',
            'memberSubtitle',
            'badgeIcon',
            'joinedDate',
            'joinedDuration',
            'phone',
            'deskLocation',
            'deliveryLocation',
            'bio',
            'bioHtml',
            'bioMaxWords',
            'birthday',
            'sugar',
            'ice',
            'toppings',
            'orderNote',
            'notifyCampaign',
            'notifySound',
            'breadcrumbs'
        );
    }

    /**
     * Cập nhật thông tin liên hệ và sở thích đồ uống của người dùng.
     *
     * @param  GlobalUser  $user  Tài khoản người dùng cần cập nhật
     * @param  array<string, mixed>  $data  Dữ liệu đã qua kiểm duyệt hợp lệ
     * @param  Request|null  $request  Đối tượng Request (tùy chọn để kiểm tra cờ boolean)
     */
    public function updateProfile(GlobalUser $user, array $data, ?Request $request = null): void
    {
        $updates = [];
        if ($request && $request->hasFile('avatar')) {
            $avatarFile = $request->file('avatar');
            if ($avatarFile instanceof UploadedFile) {
                $updates['avatar_url'] = $this->imageUploadService->uploadAvatar($avatarFile);
            }
        } elseif (isset($data['avatar']) && $data['avatar'] instanceof UploadedFile) {
            $updates['avatar_url'] = $this->imageUploadService->uploadAvatar($data['avatar']);
        } elseif (array_key_exists('avatar_url', $data) && ! empty($data['avatar_url'])) {
            $updates['avatar_url'] = $data['avatar_url'];
        }

        if (array_key_exists('phone', $data)) {
            $updates['phone'] = $data['phone'] ?? null;
        }
        if (array_key_exists('desk_location', $data)) {
            $updates['desk_location'] = $data['desk_location'] ?? null;
        }
        if (array_key_exists('delivery_location', $data)) {
            $updates['delivery_location'] = $data['delivery_location'] ?? null;
        }
        if (array_key_exists('birthday', $data)) {
            $updates['birthday'] = $data['birthday'] ?: null;
        }
        if (array_key_exists('bio', $data)) {
            $bio = trim((string) ($data['bio'] ?? ''));
            $updates['bio'] = $bio !== '' ? $bio : null;
        }

        $preferences = is_array($user->preferences) ? $user->preferences : [];
        if (array_key_exists('sugar', $data)) {
            $preferences['sugar'] = $data['sugar'] ?? null;
        }
        if (array_key_exists('ice', $data)) {
            $preferences['ice'] = $data['ice'] ?? null;
        }
        if (array_key_exists('toppings', $data)) {
            $preferences['toppings'] = array_values(array_filter($data['toppings'] ?? []));
        }
        if (array_key_exists('note', $data)) {
            $preferences['note'] = $data['note'] ?? null;
        }
        if (array_key_exists('notify_campaign', $data) || ($request && $request->has('notify_campaign'))) {
            $preferences['notify_campaign'] = $request ? $request->boolean('notify_campaign') : (bool) ($data['notify_campaign'] ?? false);
        }
        if (array_key_exists('notify_sound', $data) || ($request && $request->has('notify_sound'))) {
            $preferences['notify_sound'] = $request ? $request->boolean('notify_sound') : (bool) ($data['notify_sound'] ?? false);
        }

        $updates['preferences'] = $preferences;
        $birthdayChanged = (array_key_exists('birthday', $updates) && $updates['birthday'] !== $user->birthday?->toDateString())
            || (array_key_exists('bio', $updates) && $updates['bio'] !== $user->bio);
        $user->update($updates);
        if ($birthdayChanged) {
            // The room banners / celebration pages are cached per day; refresh them so the change shows at once.
            app(\App\Services\Birthday\RoomBirthdayService::class)->forgetForUser($user);
        }
    }

    /**
     * Vô hiệu hóa tài khoản cá nhân và đăng xuất khỏi toàn bộ phiên làm việc.
     *
     * @param  GlobalUser  $user  Tài khoản người dùng yêu cầu xóa/vô hiệu hóa
     * @param  Request  $request  Đối tượng Request chứa chuỗi xác nhận xác minh
     * @return bool True nếu xóa tài khoản thành công, False nếu chuỗi xác nhận không khớp
     */
    public function deleteAccount(GlobalUser $user, Request $request): bool
    {
        $confirmText = trim((string) $request->input('confirm_delete'));
        if ($confirmText !== $user->email && $confirmText !== 'XÓA TÀI KHOẢN' && $confirmText !== 'DELETE ACCOUNT') {
            return false;
        }

        if ($user->hasOutstandingDebts()) {
            throw ValidationException::withMessages([
                'confirm_delete' => __('admin.cannot_delete_user_with_outstanding_debt'),
            ]);
        }

        $user->update(['status' => GlobalUserStatus::Disabled]);

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return true;
    }
}
