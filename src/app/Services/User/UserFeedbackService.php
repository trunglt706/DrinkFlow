<?php

declare(strict_types=1);

namespace App\Services\User;

use App\Enums\FeedbackStatus;
use App\Enums\RoomUserStatus;
use App\Models\Feedback;
use App\Models\GlobalUser;
use App\Support\Helpers\FormatHelper;
use Illuminate\Http\Request;

class UserFeedbackService
{
    /**
     * Lấy danh sách góp ý phân trang, tính toán điểm sao trung bình và tỷ lệ phân bổ đánh giá.
     *
     * @param  GlobalUser  $user  Tài khoản người dùng toàn hệ thống
     * @param  Request  $request  Đối tượng HTTP Request hiện tại
     * @return array<string, mixed> Mảng chứa cờ `is_json` và dữ liệu tương ứng (JSON hoặc View data)
     */
    public function getFeedbackData(GlobalUser $user, Request $request): array
    {
        // Only approved feedback rated 3 stars or more is shown; the statistics follow the same rule.
        $feedbacks = Feedback::visibleOnFeedbackPage()->with('globalUser')->orderByDesc('created_at')->orderByDesc('id')->paginate(5);

        if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
            return [
                'is_json' => true,
                'data' => [
                    'success' => true,
                    'data' => $feedbacks->map(function ($fb) {
                        return [
                            'id' => $fb->id,
                            'user_display_name' => $fb->user_display_name ?: __('global.feedback.anonymous_user'),
                            'user_initial' => strtoupper(mb_substr($fb->user_display_name ?: 'U', 0, 1)),
                            'department_name' => $fb->department_name,
                            'rating' => (int) $fb->rating,
                            'subsystem' => $fb->subsystem,
                            'subsystem_label' => $fb->subsystem_label,
                            'content' => $fb->content,
                            'created_at_formatted' => FormatHelper::formatDate($fb->created_at),
                        ];
                    }),
                    'current_page' => $feedbacks->currentPage(),
                    'has_more' => $feedbacks->hasMorePages(),
                    'next_page' => $feedbacks->hasMorePages() ? $feedbacks->currentPage() + 1 : null,
                    'total' => $feedbacks->total(),
                    'count' => $feedbacks->count(),
                ],
            ];
        }

        $primaryRoomUser = $user->roomUsers()->with('room')->where('status', RoomUserStatus::Active->value)->orderByDesc('last_active_at')->first();
        $department = $primaryRoomUser?->room?->name ?? 'Ban Kỹ thuật';

        // Daily quota: 1 submission per day
        $todayCount = Feedback::query()
            ->where('global_user_id', $user->id)
            ->where('created_at', '>=', now()->startOfDay())
            ->where('created_at', '<', now()->addDay()->startOfDay())
            ->count();
        $canSubmit = $todayCount < 1;

        // Aggregate statistics
        $totalCount = Feedback::visibleOnFeedbackPage()->count();
        if ($totalCount > 0) {
            $avgScore = round((float) Feedback::visibleOnFeedbackPage()->avg('rating'), 1);
            $countsByStar = [];
            for ($s = 5; $s >= 1; $s--) {
                $countS = Feedback::visibleOnFeedbackPage()->where('rating', $s)->count();
                $countsByStar[$s] = [
                    'count' => $countS,
                    'percent' => (int) round(($countS / $totalCount) * 100),
                ];
            }
        } else {
            $avgScore = 0;
            $countsByStar = [
                5 => ['count' => 0, 'percent' => 0],
                4 => ['count' => 0, 'percent' => 0],
                3 => ['count' => 0, 'percent' => 0],
                2 => ['count' => 0, 'percent' => 0],
                1 => ['count' => 0, 'percent' => 0],
            ];
        }

        $breadcrumbs = [
            ['label' => __('global.rooms.breadcrumb_personal'), 'url' => route('user.me.dashboard')],
            ['label' => __('global.feedback.breadcrumb_feedback'), 'url' => route('user.me.feedback')],
        ];

        return [
            'is_json' => false,
            'view_data' => compact(
                'user',
                'department',
                'todayCount',
                'canSubmit',
                'totalCount',
                'avgScore',
                'countsByStar',
                'feedbacks',
                'breadcrumbs'
            ),
        ];
    }

    /**
     * Lưu trữ phản hồi góp ý từ người dùng toàn hệ thống.
     *
     * @param  GlobalUser  $user  Tài khoản người dùng gửi góp ý
     * @param  array<string, mixed>  $validated  Dữ liệu đánh giá (rating, subsystem, content) đã validate
     */
    public function storeFeedback(GlobalUser $user, array $validated): void
    {
        $primaryRoomUser = $user->roomUsers()->with('room')->where('status', RoomUserStatus::Active->value)->orderByDesc('last_active_at')->first();
        $department = $primaryRoomUser?->room?->name ?? 'Ban Công nghệ & Kỹ thuật số';

        Feedback::create([
            'global_user_id' => $user->id,
            'rating' => $validated['rating'],
            'subsystem' => $validated['subsystem'],
            'content' => $validated['content'],
            // Needs superadmin approval before it can appear on the feedback page.
            'status' => FeedbackStatus::Inactive,
            'user_display_name' => $user->name ?: __('global.feedback.anonymous_user'),
            'department_name' => $department,
        ]);
    }
}
