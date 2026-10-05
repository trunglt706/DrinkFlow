<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Global;

use App\Enums\RoomStatus;
use App\Enums\RoomUserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\JoinRoomByCodeRequest;
use App\Http\Resources\RoomUserResource;
use App\Models\GlobalUser;
use App\Services\Room\RoomAccessPolicy;
use App\Services\Room\UserRoomsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RoomsController extends Controller
{
    /**
     * Chuyển tiếp request đến phương thức hiển thị danh sách phòng.
     *
     * @param  \Illuminate\Http\Request  $request  Đối tượng HTTP Request hiện tại
     * @param  \App\Services\Room\UserRoomsService  $service  Service xử lý dữ liệu phòng
     * @return \Illuminate\Contracts\View\View|\Illuminate\Http\JsonResponse  Giao diện View hoặc phản hồi JSON
     */
    public function __invoke(Request $request, UserRoomsService $service): View|JsonResponse
    {
        return $this->index($request, $service);
    }

    /**
     * Hiển thị trang danh sách phòng tham gia hoặc trả về danh sách JSON (/me/rooms).
     *
     * @param  \Illuminate\Http\Request  $request  Đối tượng HTTP Request hiện tại
     * @param  \App\Services\Room\UserRoomsService  $service  Service xử lý dữ liệu phòng
     * @return \Illuminate\Contracts\View\View|\Illuminate\Http\JsonResponse  Giao diện View hoặc phản hồi JSON
     */
    public function index(Request $request, UserRoomsService $service): View|JsonResponse
    {
        /** @var GlobalUser $user */
        $user = $request->attributes->get('global_user') ?? $request->user('web');

        // Backward compatibility for JSON clients
        if ($request->wantsJson()) {
            $memberships = $user->roomUsers()->with('room')->where('status', RoomUserStatus::Active->value)->paginate(20);
            $memberships->through(fn ($membership): array => (new RoomUserResource($membership))->resolve($request));

            return response()->json([
                'data' => $memberships,
            ]);
        }

        $data = $service->getRoomsData($user, $request);

        return view('user.global.rooms', $data);
    }

    /**
     * Xử lý tham gia nhanh vào phòng thông qua URL liên kết hoặc mã phòng.
     *
     * @param  \App\Http\Requests\JoinRoomByCodeRequest  $request  Đối tượng Form Request chứa URL phòng đã xác thực
     * @param  \App\Services\Room\UserRoomsService  $service  Service tìm kiếm phòng
     * @return \Illuminate\Http\RedirectResponse  Chuyển hướng đến trang tham gia phòng hoặc Dashboard
     */
    public function joinByCode(JoinRoomByCodeRequest $request, UserRoomsService $service): RedirectResponse
    {
        $inputUrl = (string) $request->input('room_url');
        $room = $service->resolveRoomFromUrl($inputUrl);

        if (! $room) {
            return back()->withInput()->withErrors(['room_url' => __('global.rooms.room_not_found')]);
        }

        $roomStatus = $room->status instanceof \BackedEnum ? $room->status->value : (string) $room->status;
        if ($roomStatus !== RoomStatus::Active->value) {
            return back()->withInput()->withErrors(['room_url' => __('global.rooms.room_inactive')]);
        }

        /** @var GlobalUser|null $user */
        $user = $request->attributes->get('global_user') ?? $request->user('web');
        // Room access rules: report them on the join form instead of sending the member to a 403 page.
        $policy = app(RoomAccessPolicy::class);
        if (! $policy->ipAllowed($room, $request->ip())) {
            return back()->withInput()->withErrors(['room_url' => __('room.access.ip_denied', ['ip' => (string) $request->ip()])]);
        }
        if ($user && ! $policy->emailAllowed($room, $user->email)) {
            return back()->withInput()->withErrors(['room_url' => $policy->emailDeniedMessage($room)]);
        }
        if ($user) {
            $membership = $user->roomUsers()->where('room_id', $room->id)->first();
            if ($membership) {
                $statusVal = $membership->status instanceof RoomUserStatus ? $membership->status->value : (string) $membership->status;
                if ($statusVal === RoomUserStatus::Active->value) {
                    return redirect()->route('user.dashboard', $room)->with('status', __('global.rooms.already_member', ['name' => $room->name]));
                } elseif ($statusVal === RoomUserStatus::Blocked->value) {
                    return back()->withInput()->withErrors(['room_url' => __('global.rooms.room_access_restricted')]);
                }
            }
        }

        return redirect()->route('user.rooms.join.show', $room);
    }
}
