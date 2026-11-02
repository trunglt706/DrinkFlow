# 17. Sinh nhật & Dịp lễ (Sự kiện của phòng)

[← Về Tổng quan nghiệp vụ](overview.md)

Room tự động chúc mừng **sinh nhật thành viên** và các **dịp lễ** trong năm: banner trên mọi trang Room, trang chúc mừng có nhạc và tường lời chúc, thông báo trong app và tin nhắn ra kênh ngoài.

## 17.1. Sự kiện

| Sự kiện | Ngày | Hiển thị banner/trang |
| --- | --- | --- |
| Sinh nhật thành viên | Ngày sinh (người dùng tự nhập ở hồ sơ, `global_users.birthday`); người sinh 29/2 được chúc vào 28/2 ở năm không nhuận | 3 ngày trước và sau (`RoomBirthdayService::WINDOW_DAYS`) |
| Năm mới (`new_year`) | 1/1 | Đúng ngày |
| Tết Nguyên Đán (`lunar_new_year`) | Mùng 1 Tết âm lịch (`LunarCalendar`) | 7 ngày trước và sau |
| Quốc tế Phụ nữ (`intl_womens_day`) | 8/3 | Đúng ngày |
| Phụ nữ Việt Nam (`womens_day`) | 20/10 | Đúng ngày |
| Nam giới (`mens_day`) | 19/11 | Đúng ngày |
| Giáng sinh (`christmas`) | 25/12 | Đúng ngày |

Mọi ngày tính theo **timezone của Room**. Chỉ tên, ngày và tháng sinh được hiển thị — **không bao giờ lộ năm sinh**.

Trang sự kiện: `/rooms/{room}/birthday`, `/rooms/{room}/{occasion}` (chỉ mở khi sự kiện đang được hiển thị).

## 17.2. Thông báo tự động

| | Sinh nhật | Dịp lễ |
| --- | --- | --- |
| Lệnh | `drinkflow:notify-birthdays` (mỗi giờ, phút 0) | `drinkflow:notify-occasions` (mỗi giờ, phút 5) |
| Thời điểm | Từ **9:00 giờ Room** của ngày sinh nhật | Từ **9:00 giờ Room** của ngày lễ |
| Người nhận | Mọi thành viên `Active` của Room (thông báo `birthday.today`) + kênh ngoài | Mọi thành viên `Active` (thông báo `occasion.*`) + kênh ngoài, link tới trang dịp lễ |
| Chống gửi lặp | Một lần mỗi Room/ngày (`room_birthday_notifications`) | Một lần mỗi Room/dịp/năm (`room_occasion_notifications`) |

Lệnh chạy mỗi giờ để phủ mọi timezone của Room; ngoài các ngày có dịp lễ, lệnh dịp lễ thoát ngay. Admin có thể **gửi lại thủ công** từ trang Sự kiện (`/admin/{room}/events/send`) bao nhiêu lần tuỳ ý, bất kể giờ.

## 17.3. Trang sự kiện do Admin cấu hình

Trang `/admin/{room}/events` liệt kê sự kiện sắp tới kèm trạng thái gửi, và cho mỗi loại sự kiện (`RoomEventSettingsService`):

- **Nhạc nền & hiệu ứng âm thanh**: link nhạc (các trang nghe nhạc trực tuyến mở bằng trình phát web) hoặc file tải lên (giới hạn theo `upload_max_filesize` của PHP).
- **Danh sách lời chúc mẫu** riêng của Room (lời chúc sinh nhật có thể dùng `:name`); để trống thì dùng lời chúc mặc định theo ngôn ngữ người xem.
- Xem trước trang sự kiện (`/events/{type}/preview`).

## 17.4. Tường lời chúc

Khi sự kiện đang hiển thị, thành viên viết lời chúc ngắn (trang sinh nhật: gửi tới một người được chúc mừng), thả tim lời chúc của người khác và xoá lời chúc của chính mình. Admin **ẩn/hiện** lời chúc không phù hợp (`/admin/{room}/events/wall/{wish}`). Mỗi lần diễn ra sự kiện có một tường riêng (ngày lễ đó / sinh nhật năm đó) — `RoomEventWishWallService`.

## Tham chiếu mã nguồn

`App\Enums\RoomOccasion` · `App\Support\Helpers\LunarCalendar` · `App\Services\Birthday\RoomBirthdayService`, `BirthdayNotificationService` · `App\Services\Occasion\OccasionNotificationService`, `RoomEventService`, `RoomEventSettingsService`, `RoomEventWishWallService` · `App\Http\Controllers\Admin\RoomEventController` · `NotificationType::BirthdayToday`, `NewYear`, `LunarNewYear`, `InternationalWomensDay`, `WomensDay`, `MensDay`, `Christmas` · Hướng dẫn: [Admin – Quản lý sự kiện](../../src/public/guide-content/admin/12-quan-ly-su-kien.md), [User – Sự kiện của phòng](../../src/public/guide-content/user/14-su-kien-cua-phong.md).
