# Vinh danh nhà tài trợ theo tháng

Dashboard `/rooms/{room}/dashboard` và `/admin/{room}/dashboard` hiển thị tối đa ba thành viên tài trợ nhiều nhất trong tháng dương lịch trước, riêng cho từng phòng. Huy chương GOLD HERO, SILVER HERO, BRONZE HERO có thể lật, dùng icon `local_cafe` của hệ thống và tên nhà tài trợ viết hoa. Không hiển thị số tiền, số chiến dịch hoặc nút phóng lớn; giao diện hỗ trợ vi/en/ja. Dashboard phòng đặt bảng vinh danh trước biểu đồ và thông tin chiến dịch cuối trang, không có khối thông báo mặc định.

## Cách tính

- Dùng tháng tạo chiến dịch và múi giờ ứng dụng, nhất quán với bảng xếp hạng tài trợ hiện tại.
- Chỉ tính đơn `completed` trong chiến dịch active/closing/closed/archived. Không tính chiến dịch nháp, lên lịch hoặc đã hủy.
- Người tài trợ được xác định từ `sponsor_allocations.room_user_id`, không phải người nhận hỗ trợ trên đơn. Tên tài trợ tự do không gắn với tài khoản không được đưa vào bảng thành viên này.
- Tái sử dụng `SponsorLeaderboardService` và `CampaignSponsorshipCalculator`, bao gồm tỷ lệ chia, phí giao hàng, giảm giá và loại trừ món trả riêng.
- Lưu **tất cả** người tài trợ có số tiền dương, không chỉ TOP 3, trong `monthly_sponsor_statistics`. `month` là ngày đầu tháng; tiền là số nguyên VND.
- Unique `(room_id, month, room_user_id)` chống bản ghi trùng. Command cập nhật bản ghi hiện có và xóa số liệu tháng/phòng không còn hợp lệ khi tính lại. Mỗi phòng được cập nhật trong transaction và khóa hàng room để tuần tự hóa các lần chạy đồng thời.
- Khi bằng tổng tiền, ưu tiên `room_user_id` nhỏ hơn để thứ hạng ổn định. Nếu chưa có thống kê hoặc không có tài trợ, hiển thị trạng thái trống.

## Triển khai và chạy

Từ thư mục `src/`:

```bash
php artisan migrate --force
php artisan drinkflow:calculate-monthly-sponsors
```

Mặc định tính tháng trước cho tất cả phòng đang hoạt động (`active`). Bỏ qua phòng `inactive` và `archived`, giữ nguyên thống kê đã lưu của các phòng này. Tùy chọn `--room` cũng chỉ chấp nhận phòng đang hoạt động. Có thể tính lại một tháng hoặc phòng cụ thể:

```bash
php artisan drinkflow:calculate-monthly-sponsors --month=2026-09
php artisan drinkflow:calculate-monthly-sponsors --month=2026-09 --room=mens-est
```

Laravel Scheduler chạy command lúc **00:15 ngày 1 hằng tháng**, theo múi giờ ứng dụng, để thống kê tháng vừa kết thúc. Server cần cron chạy `php artisan schedule:run` mỗi phút. Khi cần cập nhật số liệu điều chỉnh muộn hoặc các tháng cũ hơn, chạy lại thủ công bằng `--month`.

Dashboard chỉ đọc bảng thống kê; không chạy tổng hợp trong HTTP request và không cần xóa cache sau khi tính lại.

## Kiểm thử

```bash
php artisan test --filter=MonthlySponsorStatisticsTest
php artisan test
```
