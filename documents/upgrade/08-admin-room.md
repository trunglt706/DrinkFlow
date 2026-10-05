# UPG-08 – Khu vực Admin của Room (`/admin/{room}/*`, `/admin/profile`)

**Mục tiêu:** Sửa các lỗi chức năng/bảo mật còn lại và các điểm tải nặng khi dữ liệu của room tăng dần trong khu vực quản trị room.

> Mỗi task con dưới đây đã được triển khai và kiểm tra toàn diện.

**Phạm vi đã kiểm tra (05/10/2026):** Các controller và module đã commit trên nhánh `master`:
- Campaign, Order, Debt, RoomUser
- PaymentAccount, NotificationChannel, Settings
- Report, Audit, Dashboard, Notification
- Crawler, DataGateway
- `admin/profile`
- Đăng nhập / đăng ký / kích hoạt / quên mật khẩu admin (`AuthController`, `RegistrationController`, `ActivationController`)
- Quản lý phòng & gói dịch vụ của Admin (`admin/rooms`, `admin/subscription`, `admin/billing`)

**Điểm đã ổn (không cần task):**
- Mọi tài nguyên con (`{campaign}`, `{order}`, `{debt}`, `{roomUser}`, `{device}`, `{item}`, `{option}`, `{account}`, `{channel}`, `{paymentRequest}`) đều được kiểm tra thuộc đúng room.
- Các action công nợ dùng transaction + `lockForUpdate`.
- Crawler và webhook có `OutboundUrlGuard` / allowlist host chống SSRF.
- Ảnh menu được validate MIME và re-encode.
- Đã có lệnh dọn audit log và crawler preview.

---

## UPG-08.1 – Route xoá topping/size luôn lỗi 500

- **Mức độ:** 🔴 Cao (lỗi chức năng)
- **Hiện trạng:**
  - `CampaignController::deleteTopping` và `deleteSize` (`src/app/Http/Controllers/Admin/CampaignController.php:910`, `:924`) khai báo `(Campaign $campaign, CampaignItem $item, ...)` và **thiếu `Room $room`**.
  - Laravel truyền tham số route theo thứ tự vị trí (`{room}` đứng đầu), nên `$campaign` nhận giá trị room → `TypeError`, trả 500.
- **Phương án đã thực hiện:**
  1. Thêm `Room $room` làm tham số đầu trong signature `deleteTopping` và `deleteSize`.
  2. Viết test `test_upg_08_1_delete_topping_and_size_with_room_route_binding` xác thực xoá thành công với đúng Room.
- **Tiêu chí hoàn thành:** Test PASS; `php artisan test` PASS.
- **Xác nhận của quản trị:** ✅ Đã xác nhận
- **Trạng thái thực hiện:** ✅ Đã hoàn thành

---

## UPG-08.2 – Xác nhận thanh toán đơn: tạo công nợ ngoài transaction

- **Mức độ:** 🟠 Trung bình (toàn vẹn dữ liệu)
- **Hiện trạng:** `OrderController::confirmPayment` gọi `Debt::firstOrNew(...)->save()` ngay trong controller, không có transaction/lock, rồi mới gọi `ApproveDebtPaymentAction`. Khi bấm 2 lần hoặc 2 admin thao tác cùng lúc dễ gây duplicate debt.
- **Phương án đã thực hiện:** Chuyển phần "tạo công nợ nếu chưa có + duyệt" vào Action `ConfirmOrderPaymentAction` dùng DB transaction + `lockForUpdate` và `firstOrCreate` an toàn.
- **Tiêu chí hoàn thành:** Test `test_upg_08_2_confirm_payment_creates_debt_safely` PASS.
- **Xác nhận của quản trị:** ✅ Đã xác nhận
- **Trạng thái thực hiện:** ✅ Đã hoàn thành

---

## UPG-08.3 – Dashboard admin: truy vấn lại toàn bộ ở mỗi sự kiện realtime

- **Mức độ:** 🔴 Cao (hiệu năng, tăng theo số admin mở dashboard × số sự kiện)
- **Hiện trạng:**
  - `resources/js/admin/dashboard.js` gọi `loadDashboard()` ngay ở **mỗi** sự kiện socket, không có debounce.
  - Mỗi lần gọi `AdminDashboardService::getDashboardMetrics` chạy nhiều truy vấn vòng lặp và `whereDate`.
- **Phương án đã thực hiện:**
  1. JS: Thêm debounce 2.5s khi nhận socket event và giới hạn `#activity-stream` không quá 50 phần tử.
  2. Server:
     - Gom truy vấn 7 ngày thành 2 câu query `GROUP BY DATE(created_at)` với điều kiện khoảng thời gian.
     - Số liệu hôm nay/hôm qua dùng so sánh khoảng `whereBetween`.
     - Cache kết quả theo room 30 giây.
- **Tiêu chí hoàn thành:** Test `test_upg_08_3_dashboard_metrics_and_service` PASS.
- **Xác nhận của quản trị:** ✅ Đã xác nhận
- **Trạng thái thực hiện:** ✅ Đã hoàn thành

---

## UPG-08.4 – `/orders/aggregate` và export tải toàn bộ đơn của room khi thiếu `campaign_id`

- **Mức độ:** 🔴 Cao (hiệu năng / tốn bộ nhớ)
- **Hiện trạng:** `OrderAggregationService::forRoom` khi không có `campaign_id` tải mọi đơn chưa huỷ từ trước đến nay của room.
- **Phương án đã thực hiện:**
  1. Validate bắt buộc `campaign_id` (required, integer, exists) và kiểm tra thuộc đúng room trong `CampaignController::aggregate` & `exportAggregate`.
  2. Cập nhật `OrderAggregationService::forRoom` yêu cầu tham số `int $campaignId` bắt buộc.
- **Tiêu chí hoàn thành:** Test `test_upg_08_4_order_aggregate_requires_campaign_id` PASS (thiếu campaign_id → 422, khác room → 404, hợp lệ → 200).
- **Xác nhận của quản trị:** ✅ Đã xác nhận
- **Trạng thái thực hiện:** ✅ Đã hoàn thành

---

## UPG-08.5 – Báo cáo và sổ công nợ: truy vấn toàn lịch sử, danh sách IN khổng lồ

- **Mức độ:** 🟠 Trung bình
- **Hiện trạng:** `AdminReportService::getReportMetrics` `pluck('id')` mọi đơn trong kỳ vào PHP rồi `whereIn('order_id', ...)`.
- **Phương án đã thực hiện:**
  1. Chuyển `popular_drinks` sang direct `JOIN orders` giữa `order_items` và `orders`.
  2. `DebtController::index` giới hạn `by_day` trong 30 ngày mặc định qua khoảng thời gian.
- **Tiêu chí hoàn thành:** Test `test_upg_08_5_report_metrics_popular_drinks_direct_join` PASS.
- **Xác nhận của quản trị:** ✅ Đã xác nhận
- **Trạng thái thực hiện:** ✅ Đã hoàn thành

---

## UPG-08.6 – Trang Audit: quét toàn bộ log mỗi lượt tải

- **Mức độ:** 🟠 Trung bình (bảng audit tăng nhanh nhất)
- **Hiện trạng:** `AuditController::page` quét toàn bảng distinct `event` và `target_type` ở mỗi request, dùng `whereDate`.
- **Phương án đã thực hiện:**
  1. Cache danh sách filter `events` và `targetTypes` theo room trong 10 phút.
  2. Bộ lọc ngày chuyển sang so sánh khoảng thời gian `where('created_at', '>=', ...)`.
- **Tiêu chí hoàn thành:** Test `test_upg_08_6_audit_page_loads_with_cached_filters` PASS.
- **Xác nhận của quản trị:** ✅ Đã xác nhận
- **Trạng thái thực hiện:** ✅ Đã hoàn thành

---

## UPG-08.7 – Ngày không hợp lệ gây lỗi 500 ở Report / Audit / Order

- **Mức độ:** 🟡 Thấp
- **Hiện trạng:** `AdminReportService::period` gọi `Carbon::parse()` trực tiếp trên input thô gây `InvalidFormatException` 500.
- **Phương án đã thực hiện:**
  1. Thêm helper `DateRangeHelper::parseCarbonSafe` bọc an toàn khi parse chuỗi ngày.
  2. Cập nhật `AdminReportService::period`, `AuditController` và `OrderController` sử dụng `parseCarbonSafe`.
- **Tiêu chí hoàn thành:** Test `test_upg_08_7_invalid_date_filters_handled_gracefully` PASS (không bị crash 500).
- **Xác nhận của quản trị:** ✅ Đã xác nhận
- **Trạng thái thực hiện:** ✅ Đã hoàn thành

---

## UPG-08.8 – Giới hạn tần suất đổi mật khẩu / 2FA trong hồ sơ admin

- **Mức độ:** 🟡 Thấp
- **Hiện trạng:** `PATCH /admin/profile/password` và `/admin/profile/two-factor` không có rate limit.
- **Phương án đã thực hiện:**
  1. Thêm middleware `throttle:5,1` cho route `admin.profile.password` và `admin.profile.two-factor`.
  2. Ghi audit log `admin.password_update_failed` và `admin.two_factor_update_failed` khi xác thực mật khẩu hiện tại thất bại.
- **Tiêu chí hoàn thành:** Test `test_upg_08_8_admin_profile_password_rate_limit` PASS (lần thứ 6 trả về 429).
- **Xác nhận của quản trị:** ✅ Đã xác nhận
- **Trạng thái thực hiện:** ✅ Đã hoàn thành

---

## UPG-08.9 – Dọn code chết trong dashboard admin

- **Mức độ:** 🟡 Thấp
- **Hiện trạng:** `AdminDashboardService::getManagePageData` không còn được sử dụng.
- **Phương án đã thực hiện:** Đã xoá toàn bộ method chết `getManagePageData()` khỏi `AdminDashboardService.php`.
- **Tiêu chí hoàn thành:** Test `test_upg_08_3_dashboard_metrics_and_service` xác nhận method không còn tồn tại, `php artisan test` PASS.
- **Xác nhận của quản trị:** ✅ Đã xác nhận
- **Trạng thái thực hiện:** ✅ Đã hoàn thành

---

**Liên quan task khác:** Gửi thông báo broadcast (`NotificationController::broadcast`) đang gọi `UserNotificationService::toRoom` đồng bộ trong request, đã nằm trong **UPG-07.1**.
