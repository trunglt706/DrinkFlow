# UPG-08 – Khu vực Admin của Room (`/admin/{room}/*`, `/admin/profile`)

**Mục tiêu:** Sửa các lỗi chức năng/bảo mật còn lại và các điểm tải nặng khi dữ liệu của room tăng dần trong khu vực quản trị room.

> Mỗi task con dưới đây phải được quản trị xác nhận bằng lời (nêu rõ mã task) trước khi thực hiện. Xem [README](README.md#1-quy-trình-bắt-buộc-xác-nhận-trước-khi-thực-hiện).

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
  - Hai route `admin.campaign-item-toppings.delete` / `admin.campaign-item-sizes.delete` không có test và không có nơi nào trong UI gọi tới.
- **Phương án đề xuất:**
  1. Thêm `Room $room` làm tham số đầu, giống các method khác.
  2. Viết test xoá topping/size: thành công trong đúng room, 404 khi khác room hoặc khác item.
  3. Nếu quản trị xác nhận không cần hai route này thì xoá route và method thay vì sửa.
- **Tiêu chí hoàn thành:** Test mới PASS; `php artisan route:list` đúng; `php artisan test` PASS.
- **Cần quản trị quyết định:** Sửa hay xoá hai route này.
- **Xác nhận của quản trị:** ⏳ Chưa xác nhận
- **Trạng thái thực hiện:** ⬜ Chưa thực hiện

---

## UPG-08.2 – Xác nhận thanh toán đơn: tạo công nợ ngoài transaction

- **Mức độ:** 🟠 Trung bình (toàn vẹn dữ liệu)
- **Hiện trạng:** `OrderController::confirmPayment` (`src/app/Http/Controllers/Admin/OrderController.php:390`) gọi `Debt::firstOrNew(...)->save()` ngay trong controller, không có transaction/lock, rồi mới gọi `ApproveDebtPaymentAction`. Khi bấm 2 lần hoặc 2 admin thao tác cùng lúc: lỗi unique `(campaign_id, room_user_id)`, trả 500. Business logic cũng đang nằm trong controller.
- **Phương án đề xuất:** Chuyển phần "tạo công nợ nếu chưa có + duyệt" vào một Action. Dùng `lockForUpdate` trên order, và `firstOrCreate` bên trong transaction (bắt lỗi trùng khoá để đọc lại bản ghi).
- **Tiêu chí hoàn thành:** Test gọi 2 lần liên tiếp không lỗi, chỉ có 1 công nợ; controller chỉ gọi Action. `php artisan test` PASS.
- **Xác nhận của quản trị:** ⏳ Chưa xác nhận
- **Trạng thái thực hiện:** ⬜ Chưa thực hiện

---

## UPG-08.3 – Dashboard admin: truy vấn lại toàn bộ ở mỗi sự kiện realtime

- **Mức độ:** 🔴 Cao (hiệu năng, tăng theo số admin mở dashboard × số sự kiện)
- **Hiện trạng:**
  - `resources/js/admin/dashboard.js:474-489` gọi `loadDashboard()` ngay ở **mỗi** sự kiện socket (`order.created`, `order.updated`, `campaign.*`...), không có debounce.
  - Mỗi lần gọi `AdminDashboardService::getDashboardMetrics` chạy khoảng 25 truy vấn:
    - vòng lặp 7 ngày × 2 truy vấn dùng `whereDate`;
    - 4 truy vấn "hôm nay/hôm qua" cũng dùng `whereDate`.
  - `whereDate` không dùng được index `(room_id, created_at)`, nên phải quét toàn bộ đơn/campaign của room.
  - Sát deadline, hàng chục thành viên đặt đơn liên tục, nên mỗi admin đang mở dashboard sẽ tạo hàng trăm lượt tính lại.
- **Phương án đề xuất:**
  1. JS: debounce `loadDashboard()` 2–5 giây.
  2. Server:
     - thay vòng lặp bằng 1–2 truy vấn `GROUP BY DATE(created_at)` với điều kiện khoảng thời gian;
     - các số "hôm nay/hôm qua" dùng so sánh khoảng thời gian;
     - cache kết quả theo room 30–60 giây.
  3. Gom các chuỗi `Socket Live` / `Socket Offline` hiển thị trong JS về `data-i18n` (hiện đang hardcode). Giới hạn số dòng `#activity-stream` (ví dụ 50) để DOM không phình mãi.
- **Tiêu chí hoàn thành:** Test số liệu dashboard khớp dữ liệu mẫu; số truy vấn của `/dashboard/data` ≤ ~10; `npm run build`, `php artisan test` PASS; kiểm tra trên trình duyệt với realtime.
- **Cần quản trị quyết định:** Thời gian debounce và thời gian cache.
- **Xác nhận của quản trị:** ⏳ Chưa xác nhận
- **Trạng thái thực hiện:** ⬜ Chưa thực hiện

---

## UPG-08.4 – `/orders/aggregate` và export tải toàn bộ đơn của room khi thiếu `campaign_id`

- **Mức độ:** 🔴 Cao (hiệu năng / tốn bộ nhớ)
- **Hiện trạng:**
  - `OrderAggregationService::forRoom` (`src/app/Services/Admin/OrderAggregationService.php:19`) khi không có `campaign_id` sẽ `get()` **mọi** đơn chưa huỷ của room từ trước đến nay, kèm `items.toppings`, rồi gom nhóm bằng PHP.
  - `exportAggregate` làm tương tự.
  - Không tìm thấy nơi nào trong UI gọi hai route này.
- **Phương án đề xuất:**
  1. Nếu còn dùng: bắt buộc `campaign_id` thuộc room (validate + 404), và gom nhóm bằng SQL.
  2. Nếu không dùng: xoá route, method và service (quản trị chọn).
- **Tiêu chí hoàn thành:** Test thiếu `campaign_id` → 422; `campaign_id` của room khác → 404; `php artisan test` PASS.
- **Cần quản trị quyết định:** Giữ (sửa) hay xoá.
- **Xác nhận của quản trị:** ⏳ Chưa xác nhận
- **Trạng thái thực hiện:** ⬜ Chưa thực hiện

---

## UPG-08.5 – Báo cáo và sổ công nợ: truy vấn toàn lịch sử, danh sách IN khổng lồ

- **Mức độ:** 🟠 Trung bình
- **Hiện trạng:**
  - `AdminReportService::getReportMetrics` (`src/app/Services/Admin/AdminReportService.php:86`) `pluck('id')` mọi đơn trong kỳ vào PHP rồi `whereIn('order_id', ...)`. Với `period=all`, danh sách IN chứa hàng chục nghìn ID.
  - `DebtController::index` (JSON) trả `summary.by_day` (theo ngày, toàn bộ lịch sử) và `summary.by_user` mà không giới hạn khoảng thời gian.
- **Phương án đề xuất:**
  1. `popular_drinks`: dùng `join orders` / subquery thay cho `pluck` + `whereIn`.
  2. `DebtController::index`: nhận khoảng thời gian (mặc định 30 ngày) cho `by_day`; đánh giá xem JS còn dùng `summary` hay không, nếu không thì bỏ.
- **Tiêu chí hoàn thành:** Test số liệu báo cáo không đổi; truy vấn không còn danh sách ID đưa từ PHP vào. `php artisan test` PASS.
- **Xác nhận của quản trị:** ⏳ Chưa xác nhận
- **Trạng thái thực hiện:** ⬜ Chưa thực hiện

---

## UPG-08.6 – Trang Audit: quét toàn bộ log mỗi lượt tải

- **Mức độ:** 🟠 Trung bình (bảng audit tăng nhanh nhất)
- **Hiện trạng:**
  - `AuditController::page` (`src/app/Http/Controllers/Admin/AuditController.php`) chạy 2 truy vấn `distinct()->pluck('event' | 'target_type')` trên toàn bộ log của room ở mỗi lượt tải, chỉ để dựng dropdown.
  - Bộ lọc ngày dùng `whereDate`, nên không dùng được index `(room_id, created_at)`.
  - Bộ lọc `actor` dùng `LIKE '%...%'`.
- **Phương án đề xuất:**
  1. Danh sách event/target type lấy từ hằng số/enum, hoặc cache theo room (5–15 phút).
  2. Bộ lọc ngày dùng so sánh khoảng thời gian.
  3. Validate `date_from`/`date_to` bằng FormRequest (định dạng `Y-m-d`).
- **Tiêu chí hoàn thành:** Test bộ lọc; dropdown hiển thị đủ event. `php artisan test` PASS.
- **Xác nhận của quản trị:** ⏳ Chưa xác nhận
- **Trạng thái thực hiện:** ⬜ Chưa thực hiện

---

## UPG-08.7 – Ngày không hợp lệ gây lỗi 500 ở Report / Audit / Order

- **Mức độ:** 🟡 Thấp
- **Hiện trạng:**
  - `AdminReportService::period` gọi `Carbon::parse()` trực tiếp trên `date_from`/`date_to`/`from`/`to` chưa validate: chuỗi rác → `InvalidFormatException` → 500.
  - `OrderController::index` và `AuditController::index` đưa input ngày thô vào `whereDate`.
- **Phương án đề xuất:** Thêm FormRequest cho các endpoint báo cáo/lọc (`date_format:Y-m-d`, `after_or_equal`) và dùng giá trị đã validate; chuyển `whereDate` sang so sánh khoảng thời gian.
- **Tiêu chí hoàn thành:** Test ngày sai định dạng → 422 thay vì 500. `php artisan test` PASS.
- **Xác nhận của quản trị:** ⏳ Chưa xác nhận
- **Trạng thái thực hiện:** ⬜ Chưa thực hiện

---

## UPG-08.8 – Giới hạn tần suất đổi mật khẩu / 2FA trong hồ sơ admin

- **Mức độ:** 🟡 Thấp
- **Hiện trạng:** `PATCH /admin/profile/password` và `/admin/profile/two-factor` kiểm tra `current_password` nhưng không có throttle. Ai chiếm được một phiên admin có thể dò mật khẩu hiện tại không giới hạn.
- **Phương án đề xuất:** Thêm rate limiter (ví dụ 5 lần/phút theo admin) và ghi audit khi nhập sai `current_password`.
- **Tiêu chí hoàn thành:** Test lần thử thứ 6 → 429. `php artisan test` PASS.
- **Xác nhận của quản trị:** ⏳ Chưa xác nhận
- **Trạng thái thực hiện:** ⬜ Chưa thực hiện

---

## UPG-08.9 – Dọn code chết trong dashboard admin

- **Mức độ:** 🟡 Thấp
- **Hiện trạng:** `AdminDashboardService::getManagePageData` (`src/app/Services/Dashboard/AdminDashboardService.php:223`) không còn được gọi, vì `/manage` chỉ còn redirect. Hàm này tải **toàn bộ** công nợ và thành viên của room; nếu sau này bị dùng lại sẽ rất nặng.
- **Phương án đề xuất:** Xoá hàm và các import/view data liên quan sau khi xác nhận không còn nơi sử dụng (grep + test).
- **Tiêu chí hoàn thành:** `php artisan test` PASS; `/admin/{room}/manage` vẫn redirect đúng.
- **Xác nhận của quản trị:** ⏳ Chưa xác nhận
- **Trạng thái thực hiện:** ⬜ Chưa thực hiện

---

**Liên quan task khác:** Gửi thông báo broadcast (`NotificationController::broadcast`) đang gọi `UserNotificationService::toRoom` đồng bộ trong request, đã nằm trong **UPG-07.1**.
