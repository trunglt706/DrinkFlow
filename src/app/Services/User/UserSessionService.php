<?php

declare(strict_types=1);

namespace App\Services\User;

use App\Enums\RoomUserStatus;
use App\Events\ForceReloadRequested;
use App\Models\GlobalUser;
use App\Models\RoomUserDevice;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserSessionService
{
    /**
     * Tổng hợp thông tin thiết bị, phiên làm việc hiện tại và danh sách các phiên đăng nhập từ xa của người dùng.
     *
     * @param  GlobalUser  $user  Tài khoản người dùng toàn hệ thống
     * @param  Request  $request  Đối tượng HTTP Request hiện tại
     * @return array<string, mixed> Mảng dữ liệu hiển thị trên trang quản lý thiết bị
     */
    public function getDevicesData(GlobalUser $user, Request $request): array
    {
        $domain = Str::after((string) $user->email, '@') ?: 'company.com';
        $primaryRoomUser = $user->roomUsers()->with('room')->where('status', RoomUserStatus::Active->value)->orderByDesc('last_active_at')->first();
        $department = $primaryRoomUser?->room?->name ?? __('global.profile.default_dept');
        $userCode = $primaryRoomUser?->user_code ?? $user->code;

        // Current session & device details
        $currentSessionId = $request->session()->getId();
        $currentUa = $request->userAgent();
        $currentDeviceInfo = $this->parseUserAgent($currentUa);
        $currentIp = $request->ip() ?: __('global.devices.internal_network');
        $currentSessionCode = '#SES-'.strtoupper(substr(md5((string) $currentSessionId), 0, 4)).'-VN';

        // Query other sessions from database sessions table
        $dbSessions = DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', $currentSessionId)
            ->orderByDesc('last_activity')
            ->limit(20)
            ->get();

        $otherSessions = [];
        foreach ($dbSessions as $sess) {
            $parsed = $this->parseUserAgent($sess->user_agent);
            $lastActivity = Carbon::createFromTimestamp($sess->last_activity);
            $otherSessions[] = [
                'id' => $sess->id,
                'device_name' => $parsed['device_name'],
                'icon' => $parsed['icon'],
                'ip' => $sess->ip_address ?: __('global.devices.internal_network'),
                'location' => __('global.devices.default_country'),
                'last_active' => $lastActivity->diffForHumans(),
                'is_real_session' => true,
            ];
        }

        $breadcrumbs = [
            ['label' => __('global.rooms.breadcrumb_personal'), 'url' => route('user.me.dashboard')],
            ['label' => __('global.devices.breadcrumb_devices'), 'url' => route('user.me.devices')],
        ];

        return compact(
            'user',
            'domain',
            'department',
            'userCode',
            'currentDeviceInfo',
            'currentIp',
            'currentSessionCode',
            'otherSessions',
            'breadcrumbs'
        );
    }

    /**
     * Hủy bỏ và đăng xuất một phiên làm việc từ xa theo Session ID.
     *
     * @param  GlobalUser  $user  Tài khoản người dùng sở hữu phiên
     * @param  string  $sessionId  Mã định danh phiên làm việc cần xóa
     * @param  string  $currentDeviceUuid  UUID thiết bị hiện tại
     */
    public function logoutDevice(GlobalUser $user, string $sessionId, string $currentDeviceUuid): void
    {
        DB::table('sessions')
            ->where('id', $sessionId)
            ->where('user_id', $user->id)
            ->delete();
    }

    /**
     * Thu hồi toàn bộ các phiên làm việc và thiết bị tin cậy khác ngoại trừ phiên hiện tại.
     *
     * @param  GlobalUser  $user  Tài khoản người dùng
     * @param  string  $currentSessionId  Mã định danh phiên làm việc hiện tại cần giữ lại
     * @param  string  $currentDeviceUuid  UUID thiết bị hiện tại cần giữ lại
     */
    public function logoutOtherDevices(GlobalUser $user, string $currentSessionId, string $currentDeviceUuid): void
    {
        DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', $currentSessionId)
            ->delete();

        // Revoke other trusted device tokens
        $roomUserIds = $user->roomUsers()->pluck('id');
        $query = RoomUserDevice::query()->whereIn('room_user_id', $roomUserIds)->whereNull('revoked_at');
        if ($currentDeviceUuid !== '') {
            $query->where('device_uuid', '!=', $currentDeviceUuid);
        }
        $deviceUuids = $query->pluck('device_uuid')->all();
        $query->update(['revoked_at' => now()]);
        $this->publishRevocations($deviceUuids);

        // Rotate remember_token to invalidate existing remember me cookies on other devices
        $user->forceFill(['remember_token' => Str::random(60)])->save();

        if (auth('web')->check() && auth('web')->id() === $user->id) {
            auth('web')->login($user, true);
        }
    }

    /**
     * Force pages still open on the revoked trusted devices to reload, so they are signed out immediately.
     *
     * @param  list<string>  $deviceUuids  Revoked device UUIDs.
     */
    private function publishRevocations(array $deviceUuids): void
    {
        foreach (array_unique($deviceUuids) as $deviceUuid) {
            event(ForceReloadRequested::forDevice($deviceUuid, ForceReloadRequested::REASON_DEVICE_REVOKED));
        }
    }

    /**
     * Phân tích chuỗi User-Agent thành tên thiết bị (trình duyệt + hệ điều hành) và icon hiển thị.
     *
     * Chỉ suy ra những gì User-Agent thật sự cho biết; không đoán model máy. Thứ tự kiểm tra quan trọng:
     * UA của Edge/Opera/Samsung chứa "Chrome", UA của Chrome chứa "Safari", UA của iPad (iPadOS 13+) giống macOS.
     *
     * @param  string|null  $userAgent  Chuỗi User-Agent từ HTTP header
     * @return array{browser: string, os: string, device_name: string, icon: string} Thông tin hiển thị của thiết bị
     */
    public function parseUserAgent(?string $userAgent): array
    {
        $ua = (string) $userAgent;

        $os = match (true) {
            (bool) preg_match('/iPhone|iPod/i', $ua) => 'iOS',
            (bool) preg_match('/iPad/i', $ua) => 'iPadOS',
            (bool) preg_match('/Android/i', $ua) => 'Android',
            (bool) preg_match('/CrOS/i', $ua) => 'ChromeOS',
            (bool) preg_match('/Windows/i', $ua) => 'Windows',
            (bool) preg_match('/Macintosh|Mac OS X/i', $ua) => 'macOS',
            (bool) preg_match('/Linux|X11/i', $ua) => 'Linux',
            default => __('global.devices.unknown_os'),
        };

        $browser = match (true) {
            (bool) preg_match('/Edg(e|A|iOS)?\//i', $ua) => 'Microsoft Edge',
            (bool) preg_match('/OPR\/|Opera/i', $ua) => 'Opera',
            (bool) preg_match('/SamsungBrowser/i', $ua) => 'Samsung Internet',
            (bool) preg_match('/Firefox\/|FxiOS/i', $ua) => 'Firefox',
            (bool) preg_match('/Chrome\/|CriOS/i', $ua) => 'Chrome',
            (bool) preg_match('/Safari\//i', $ua) => 'Safari',
            default => __('global.devices.unknown_browser'),
        };

        $icon = match ($os) {
            'iOS', 'Android' => 'smartphone',
            'iPadOS' => 'tablet_mac',
            'macOS' => 'laptop_mac',
            'Windows' => 'laptop_windows',
            'ChromeOS' => 'laptop_chromebook',
            default => 'computer',
        };

        return [
            'browser' => $browser,
            'os' => $os,
            'device_name' => __('global.devices.device_on_os', ['browser' => $browser, 'os' => $os]),
            'icon' => $icon,
        ];
    }
}
