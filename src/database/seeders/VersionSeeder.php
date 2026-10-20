<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Superadmin;
use App\Models\Version;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class VersionSeeder extends Seeder
{
    /**
     * Nạp dữ liệu phiên bản ban đầu của hệ thống vào cơ sở dữ liệu.
     */
    public function run(): void
    {
        $superadmin = Superadmin::query()->orderBy('id')->first();

        $versions = [
            [
                'version' => 'v2.3.0',
                'title' => 'Multi-room Ordering, Proxy Orders & VietQR Settlement',
                'changelog' => 'Bản phát hành chính thức v2.3.0 hoàn thiện nền tảng đặt đồ uống đa phòng cho môi trường production: xác thực Google OAuth và thiết bị tin cậy; mã đơn hàng chuẩn ORD{room_id}-YYYYMMDD-sequence; đặt món giúp thành viên khác với đơn cha/con, công nợ và thông báo realtime đúng người nhận; VietQR và quy trình xác nhận thanh toán công nợ; cùng hệ thống migration schema hợp nhất, an toàn khi triển khai mới.',
                'release_date' => Carbon::createFromFormat('d/m/Y', '18/09/2026')->toDateString(),
                'force_refresh' => false,
                'important' => true,
                'created_by_superadmin_id' => $superadmin?->id,
            ],
            [
                'version' => 'v2.4.0',
                'title' => 'Room Events, Lucky Draw & Yearly Achievements',
                'changelog' => 'Bản phát hành v2.4.0: sự kiện sinh nhật và 6 ngày lễ (Tết Dương lịch, Tết Nguyên Đán, 8/3, 20/10, 19/11, Giáng sinh) với banner, trang chúc mừng có hiệu ứng, âm thanh, lời chúc tuỳ chỉnh và thông báo tự động lúc 9:00; trang quản lý sự kiện cho Admin (gửi/gửi lại thông báo, cấu hình âm thanh và lời chúc); vòng quay may mắn quay nhà tài trợ/chọn quán minh bạch; trang Thành tích với huy chương TOP nhà tài trợ trong năm; banner nhắc cập nhật ngày sinh và tài liệu hướng dẫn mới cho User và Admin.',
                'release_date' => Carbon::createFromFormat('d/m/Y', '20/10/2026')->toDateString(),
                'force_refresh' => false,
                'important' => true,
                'created_by_superadmin_id' => $superadmin?->id,
            ],
        ];

        foreach ($versions as $item) {
            Version::updateOrCreate(
                ['version' => $item['version']],
                $item
            );
        }

        Version::clearLatestVersionCache();
    }
}
