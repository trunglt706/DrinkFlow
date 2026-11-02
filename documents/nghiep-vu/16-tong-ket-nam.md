# 16. Tổng kết năm (Year recap)

[← Về Tổng quan nghiệp vụ](overview.md)

Mỗi thành viên có một trang **tổng kết năm** kiểu "Wrapped" cho từng Room: số ly, món và quán yêu thích, tháng đặt nhiều nhất, chi tiêu, tài trợ đã cho/đã nhận, huy hiệu ([bài 15](15-huy-hieu-nha-tai-tro-thanh-tich.md)) và thứ hạng trong Room.

## 16.1. Mùa tổng kết

- Mùa chạy từ **15/12 tới 31/1** theo timezone của Room: tháng 12 tổng kết năm hiện tại, tháng 1 tổng kết năm vừa qua.
- Năm cũ hơn **luôn mở được**; năm hiện tại chỉ mở khi mùa đã bắt đầu.
- Trong mùa, dashboard Room giới thiệu tổng kết cho thành viên **có đặt món** trong năm đó.
- Trang: `/rooms/{room}/recap/{year}` (`user.rooms.recap`).

## 16.2. Cách tính

- Dữ liệu: Order **không bị huỷ** của Room, tạo trong năm (theo timezone của Room), thuộc chiến dịch **không bị huỷ**.
- "Món yêu thích" tính theo số ly; tên món được gộp không phân biệt dấu và hoa thường.
- Kết quả lưu sẵn trong `member_year_recaps`:
  - lệnh `drinkflow:build-year-recaps` (02:30 UTC mỗi ngày, chỉ làm việc trong mùa) tính trước cho mọi Room `Active`;
  - nếu thiếu hoặc năm còn trong mùa (số liệu đang thay đổi) thì tính lại ngay ở lần đọc đầu tiên rồi lưu.

## 16.3. Thông báo tổng kết

Lệnh `drinkflow:send-year-recaps` (mỗi giờ, phút 25) thông báo khi năm mới bắt đầu (`YearRecapNotificationService`):

- Từ **9:00 ngày 1/1 theo giờ Room**; lần chạy bị lỡ được bù trong cả tháng 1.
- Tính lại tổng kết của Room, rồi:
  - mỗi thành viên `Active` **có đặt món** trong năm nhận một thông báo `recap.year` (lưu + realtime), link tới trang tổng kết của họ;
  - kênh ngoài của Room nhận số liệu chung của cả phòng (tổng số ly, số thành viên).
- **Một lần cho mỗi Room/năm** nhờ unique `(room_id, year)` của bảng `room_year_recap_notifications`. Room không có ai đặt món trong năm thì không gửi.
- Tham số: `--year`, `--room=slug`, `--force` (bỏ qua kiểm tra ngày/giờ; vẫn chỉ gửi một lần).

## Tham chiếu mã nguồn

`App\Models\MemberYearRecap` · `App\Services\Reporting\YearRecapService`, `YearRecapNotificationService` · `App\Console\Commands\SendYearRecaps` và lệnh `drinkflow:build-year-recaps` · `NotificationType::YearRecap`.
