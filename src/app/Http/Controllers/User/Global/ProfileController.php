<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Global;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFeedbackRequest;
use App\Http\Requests\UpdateGlobalProfileRequest;
use App\Models\Feedback;
use App\Models\GlobalUser;
use App\Services\User\UserFeedbackService;
use App\Services\User\UserProfileService;
use App\Services\User\UserSessionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    /**
     * Chuyển tiếp request đến phương thức hiển thị hồ sơ cá nhân.
     *
     * @param  Request  $request  Đối tượng HTTP Request hiện tại
     * @param  UserProfileService  $service  Service xử lý dữ liệu hồ sơ
     * @return View Giao diện thông tin cá nhân
     */
    public function __invoke(Request $request, UserProfileService $service): View
    {
        return $this->index($request, $service);
    }

    /**
     * Hiển thị trang hồ sơ cá nhân toàn hệ thống (/me/profile).
     *
     * @param  Request  $request  Đối tượng HTTP Request hiện tại
     * @param  UserProfileService  $service  Service xử lý dữ liệu hồ sơ
     * @return View Giao diện hồ sơ cá nhân
     */
    public function index(Request $request, UserProfileService $service): View
    {
        /** @var GlobalUser $user */
        $user = $request->attributes->get('global_user') ?? $request->user('web');

        $data = $service->getProfileData($user);

        return view('user.global.profile', $data);
    }

    /**
     * Cập nhật thông tin liên hệ và sở thích gọi đồ của người dùng.
     *
     * @param  UpdateGlobalProfileRequest  $request  Đối tượng Form Request đã xác thực
     * @param  UserProfileService  $service  Service xử lý cập nhật
     * @return RedirectResponse Phản hồi chuyển hướng kèm thông báo thành công
     */
    public function update(UpdateGlobalProfileRequest $request, UserProfileService $service): RedirectResponse
    {
        /** @var GlobalUser $user */
        $user = $request->attributes->get('global_user') ?? $request->user('web');

        $validated = $request->validated();

        $service->updateProfile($user, $validated, $request);

        return back()->with('status', __('global.profile.update_success_status'));
    }

    /**
     * Trả về dữ liệu JSON hồ sơ người dùng cho các client API hoặc realtime frontend.
     *
     * @param  Request  $request  Đối tượng HTTP Request hiện tại
     * @return JsonResponse Dữ liệu JSON chứa thông tin người dùng và các liên kết
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->attributes->get('global_user') ?? $request->user('web');

        return response()->json(['data' => $user->load(['oauthIdentities', 'roomUsers.room'])]);
    }

    /**
     * Trang đối soát và lịch sử thanh toán cá nhân (/me/payments).
     *
     * @param  Request  $request  Đối tượng HTTP Request
     * @return View|JsonResponse Giao diện hoặc dữ liệu JSON thanh toán
     */
    public function payments(Request $request): View|JsonResponse
    {
        return app(PaymentsController::class)->index($request);
    }

    /**
     * Hiển thị trang quản lý thiết bị và phiên đăng nhập (/me/devices).
     *
     * @param  Request  $request  Đối tượng HTTP Request
     * @param  UserSessionService  $service  Service xử lý phiên làm việc
     * @return View Giao diện quản lý thiết bị
     */
    public function devices(Request $request, UserSessionService $service): View
    {
        /** @var GlobalUser $user */
        $user = $request->attributes->get('global_user') ?? $request->user('web');

        $data = $service->getDevicesData($user, $request);

        return view('user.global.devices', $data);
    }

    /**
     * Đăng xuất một thiết bị / phiên làm việc từ xa cụ thể.
     *
     * @param  Request  $request  Đối tượng HTTP Request
     * @param  UserSessionService  $service  Service xử lý phiên
     * @param  string  $sessionId  Mã định danh phiên làm việc cần thu hồi
     * @return RedirectResponse Phản hồi chuyển hướng quay lại
     */
    public function logoutDevice(Request $request, UserSessionService $service, string $sessionId): RedirectResponse
    {
        /** @var GlobalUser $user */
        $user = $request->attributes->get('global_user') ?? $request->user('web');

        $service->logoutDevice($user, $sessionId, (string) $request->cookie('drinkflow_device_uuid', ''));

        return back()->with('status', __('global.devices.logout_device_success'));
    }

    /**
     * Đăng xuất tất cả các thiết bị và phiên làm việc khác ngoại trừ phiên hiện tại.
     *
     * @param  Request  $request  Đối tượng HTTP Request
     * @param  UserSessionService  $service  Service xử lý phiên
     * @return RedirectResponse Phản hồi chuyển hướng quay lại
     */
    public function logoutOtherDevices(Request $request, UserSessionService $service): RedirectResponse
    {
        /** @var GlobalUser $user */
        $user = $request->attributes->get('global_user') ?? $request->user('web');

        $service->logoutOtherDevices(
            $user,
            $request->session()->getId(),
            (string) $request->cookie('drinkflow_device_uuid', ''),
        );

        return back()->with('status', __('global.devices.logout_other_devices_success'));
    }

    /**
     * Xử lý yêu cầu vô hiệu hóa hoặc xóa tài khoản cá nhân.
     *
     * @param  Request  $request  Đối tượng HTTP Request chứa chuỗi xác nhận
     * @param  UserProfileService  $service  Service xử lý hồ sơ
     * @return RedirectResponse Chuyển hướng về trang chủ sau khi xóa
     */
    public function deleteAccount(Request $request, UserProfileService $service): RedirectResponse
    {
        /** @var GlobalUser $user */
        $user = $request->attributes->get('global_user') ?? $request->user('web');

        $deleted = $service->deleteAccount($user, $request);
        if (! $deleted) {
            return back()->withErrors(['confirm_delete' => __('global.devices.delete_confirm_invalid')]);
        }

        return redirect('/')->with('status', __('global.devices.delete_account_success'));
    }

    /**
     * Hiển thị danh sách hoặc dữ liệu JSON góp ý phản hồi của người dùng (/me/feedback).
     *
     * @param  Request  $request  Đối tượng HTTP Request
     * @param  UserFeedbackService  $service  Service quản lý góp ý
     * @return View|JsonResponse Giao diện hoặc phản hồi JSON
     */
    public function feedback(Request $request, UserFeedbackService $service): View|JsonResponse
    {
        /** @var GlobalUser $user */
        $user = $request->attributes->get('global_user') ?? $request->user('web');

        $result = $service->getFeedbackData($user, $request);

        if ($result['is_json']) {
            return response()->json($result['data']);
        }

        return view('user.global.feedback', $result['view_data']);
    }

    /**
     * Tiếp nhận và lưu trữ góp ý mới từ người dùng toàn hệ thống.
     *
     * @param  StoreFeedbackRequest  $request  Đối tượng Form Request chứa nội dung góp ý
     * @param  UserFeedbackService  $service  Service xử lý lưu góp ý
     * @return RedirectResponse Phản hồi chuyển hướng kèm thông báo thành công
     */
    public function storeFeedback(StoreFeedbackRequest $request, UserFeedbackService $service): RedirectResponse
    {
        /** @var GlobalUser $user */
        $user = $request->attributes->get('global_user') ?? $request->user('web');

        $todayCount = Feedback::query()
            ->where('global_user_id', $user->id)
            ->where('created_at', '>=', now()->startOfDay())
            ->where('created_at', '<', now()->addDay()->startOfDay())
            ->count();

        if ($todayCount >= 1) {
            return back()->withErrors(['quota' => __('global.feedback.quota_exceeded_error')]);
        }

        $validated = $request->validated();

        $service->storeFeedback($user, $validated);

        return back()->with('status', __('global.feedback.submit_success_status'));
    }
}
