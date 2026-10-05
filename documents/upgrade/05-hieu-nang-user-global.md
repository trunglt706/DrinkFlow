# UPG-05 – Hiệu năng trang User Global (`/me/*`)

**Mục tiêu:** Các trang cá nhân không tải toàn bộ lịch sử đơn hàng vào bộ nhớ. Số liệu được tổng hợp bằng SQL, có phân trang, và truy vấn dùng được index. Thời gian phản hồi không tăng tuyến tính theo số đơn của người dùng.

> Mỗi task con dưới đây phải được quản trị xác nhận bằng lời (nêu rõ mã task) trước khi thực hiện. Xem [README](README.md#1-quy-trình-bắt-buộc-xác-nhận-trước-khi-thực-hiện).

---

## UPG-05.1 – `/me/statistics`, `/analytics`, `/me/statistics/export`: tổng hợp bằng SQL

- **Mức độ:** 🔴 Cao (tăng tuyến tính theo toàn bộ lịch sử)
- **Hiện trạng:**
  - `UserGlobalAnalyticsService::getAnalyticsViewData` (`src/app/Services/Analytics/UserGlobalAnalyticsService.php:21`):
    - tải **mọi** đơn (mọi trạng thái, mọi thời điểm) kèm `campaign`, `items`, rồi mới lọc `Completed` bằng PHP;
    - `$ru->orders` trong vòng lặp room gây N+1 và tải lại toàn bộ đơn lần nữa.
  - `getAnalyticsApiData` (`:171`) tải `roomUsers.orders.items` toàn bộ.
  - **Lỗi logic kèm theo:** `weeklyStats` gộp đơn của mọi tháng theo ngày trong tháng (ngày 1–7 của mọi tháng vào "tuần 1"...).
- **Phương án đề xuất:**
  1. Tổng số đơn, tổng chi, tổng tài trợ: một truy vấn `SUM/COUNT` với `status = completed`.
  2. Top món, top nhà hàng, chi theo room: `GROUP BY` bằng SQL, `LIMIT`.
  3. Biểu đồ tuần: giới hạn khoảng thời gian (ví dụ tháng hiện tại, hoặc thêm bộ lọc kỳ như trang thống kê room), `GROUP BY` theo tuần.
  4. Export dùng cùng dữ liệu tổng hợp.
- **Tiêu chí hoàn thành:**
  - Số query của trang không phụ thuộc số room/số đơn.
  - Test so sánh số liệu với dữ liệu mẫu (bao gồm đơn ở nhiều tháng).
  - `php artisan test` PASS; kiểm tra trang bằng trình duyệt.
- **Cần quản trị quyết định:** Khoảng thời gian mặc định của trang thống kê cá nhân (tháng này / 30 ngày / toàn bộ nhưng tính bằng SQL).
- **Xác nhận của quản trị:** ⏳ Chưa xác nhận
- **Trạng thái thực hiện:** ⬜ Chưa thực hiện

---

## UPG-05.2 – `/me/payments` và export: phân trang, bỏ tạo QR hàng loạt

- **Mức độ:** 🔴 Cao
- **Hiện trạng:** `UserPaymentsService::getPaymentsData` (`src/app/Services/Payment/UserPaymentsService.php:22`):
  - tải mọi đơn kèm `room.paymentAccounts`, `campaign.paymentAccount`, `items`, không phân trang;
  - lọc/sắp xếp bằng PHP;
  - tạo payload VietQR cho **từng** đơn, kể cả đơn đã thanh toán;
  - dùng chuỗi `'pending'` cứng thay vì enum.
- **Phương án đề xuất:**
  1. Metric (chưa thanh toán, đã thanh toán tháng này, tài trợ) bằng truy vấn tổng hợp.
  2. Danh sách: query có `where`/`orderBy` theo filter, `paginate()`.
  3. Chỉ tạo QR cho đơn chưa thanh toán trên trang hiện tại (hoặc tạo khi người dùng mở modal).
  4. Export: dùng `FromQuery` + `chunk` của Laravel Excel.
  5. Thay chuỗi cứng bằng enum.
- **Tiêu chí hoàn thành:** Số đơn tải mỗi request ≤ kích thước trang; test metric và export. `php artisan test` PASS; kiểm tra giao diện.
- **Xác nhận của quản trị:** ⏳ Chưa xác nhận
- **Trạng thái thực hiện:** ⬜ Chưa thực hiện

---

## UPG-05.3 – `/me/rooms`: bỏ eager-load toàn bộ đơn

- **Mức độ:** 🟠 Trung bình
- **Hiện trạng:** `UserRoomsService::getRoomsData` eager-load `orders` (`src/app/Services/Room/UserRoomsService.php:50`) cho mỗi membership, chỉ để đếm số đơn, tính tổng chi và lấy đơn mới nhất trong `formatRoomCard`.
- **Phương án đề xuất:** Thay bằng `withCount('orders')`, `withSum('orders', 'final_amount')`, `withMax('orders', 'created_at')`. `formatRoomCard` đọc các thuộc tính này; giữ tương thích cho `UserGlobalDashboardService` đang gọi lại hàm này.
- **Tiêu chí hoàn thành:** Thẻ room hiển thị số liệu như cũ; không còn truy vấn tải toàn bộ `orders`. `php artisan test` PASS.
- **Xác nhận của quản trị:** ⏳ Chưa xác nhận
- **Trạng thái thực hiện:** ⬜ Chưa thực hiện

---

## UPG-05.4 – Bỏ `whereDate` / `whereMonth` trên cột có index

- **Mức độ:** 🟡 Thấp
- **Hiện trạng:** `whereDate()` / `whereMonth()` / `whereYear()` sinh ra `DATE(created_at) ...`, nên MySQL không dùng được index `(room_user_id, created_at)`. Các vị trí:
  - `UserOrdersService.php:70-75`, `:105-106`, `:161-164`
  - `UserRoomDebtService.php:63-64`
  - `ProfileController.php:205` (quota feedback)
- **Phương án đề xuất:** Đổi sang so sánh khoảng: `where('created_at', '>=', $start)->where('created_at', '<', $end)`, tính `$start`/`$end` theo timezone của app.
- **Tiêu chí hoàn thành:** Test biên (đơn lúc 00:00 và 23:59) cho các bộ lọc. `php artisan test` PASS.
- **Xác nhận của quản trị:** ⏳ Chưa xác nhận
- **Trạng thái thực hiện:** ⬜ Chưa thực hiện

---

## UPG-05.5 – Không truy vấn thông báo lặp lại trong layout global

- **Mức độ:** 🟡 Thấp
- **Hiện trạng:**
  - `UserGlobalLayoutComposer` gắn cho mọi view `components.global.*` và `user.global.*`. Mỗi component (layout, header...) không nhận sẵn dữ liệu sẽ tự đếm và lấy 5 thông báo chưa đọc, nên một trang có thể lặp truy vấn nhiều lần.
  - Nhiều service (`UserPaymentsService`, `UserOrdersService`, `UserRoomsService`, `UserGlobalAnalyticsService`) cũng tự truy vấn lại cùng dữ liệu này.
- **Phương án đề xuất:**
  1. Tạo một service nhỏ (ví dụ `UserNotificationSummary`) nhớ kết quả trong phạm vi request, dùng chung cho composer và các service.
  2. Bỏ phần truy vấn trùng trong các service.
- **Tiêu chí hoàn thành:** Mỗi trang `/me/*` chỉ còn 1 truy vấn đếm + 1 truy vấn danh sách thông báo (kiểm tra bằng `DB::listen` trong test). `php artisan test` PASS.
- **Xác nhận của quản trị:** ⏳ Chưa xác nhận
- **Trạng thái thực hiện:** ⬜ Chưa thực hiện
