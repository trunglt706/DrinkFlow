# UPG-01 – Toàn vẹn dữ liệu đơn hàng trên MySQL

**Mục tiêu:** Đảm bảo các ràng buộc nghiệp vụ về đơn hàng và tài khoản thanh toán được bảo vệ ở tầng database trên **MySQL** (môi trường chính thức), không chỉ trên sqlite dùng khi test.

> Mỗi task con dưới đây phải được quản trị xác nhận bằng lời (nêu rõ mã task) trước khi thực hiện. Xem [README](README.md#1-quy-trình-bắt-buộc-xác-nhận-trước-khi-thực-hiện).

---

## UPG-01.1 – Chặn tạo nhiều đơn active cho cùng thành viên trong một campaign

- **Mức độ:** 🔴 Cao (toàn vẹn dữ liệu, ảnh hưởng tiền/công nợ)
- **Hiện trạng:**
  - Unique index `orders_one_active_per_user_campaign` chỉ được tạo khi driver là `pgsql`/`sqlite` (`src/database/migrations/2026_09_10_000000_create_drinkflow_core_tables.php:201`, `2026_09_27_000000_add_placed_by_admin_id_to_orders_table.php:41`).
  - Đã kiểm tra `information_schema.STATISTICS` trên MySQL local: **không có** index này.
  - `CreateOrderAction` không tự kiểm tra đơn active đã tồn tại, chỉ dựa vào index. Nhánh bắt lỗi `orders_one_active_per_user_campaign` ở `src/app/Http/Controllers/User/OrderController.php:78-115` không bao giờ chạy trên MySQL.
  - Thành viên gửi lại form là tạo thêm đơn active. Bảng `debts` có unique `(campaign_id, room_user_id)` nên công nợ và đơn có thể lệch nhau.
  - Test vẫn PASS vì `phpunit.xml` dùng sqlite.
- **Phương án đề xuất:**
  1. Trong transaction của `CreateOrderAction` (campaign đã `lockForUpdate`), kiểm tra `exists()` đơn có status thuộc nhóm active (`OrderStatus::activeValues()`) của `(campaign_id, room_user_id)`. Nếu có, ném `ValidationException` với code `active_order_exists` (dùng lại key dịch `global.orders.active_order_exists`).
  2. Enum `App\Enums\OrderStatus` đã có hàm `OrderStatus::activeValues()`. Cần thay thế toàn bộ các mảng enum/string lặp ở `src/app/Http/Controllers/User/OrderController.php`, `src/app/Services/Campaign/UserRoomCampaignService.php` sang gọi `OrderStatus::activeValues()`.
  3. Migration mới cho MySQL: thêm generated column `active_order_key` (= `room_user_id` khi status active, ngược lại `NULL`) và unique index `(campaign_id, active_order_key)`. Giữ nguyên partial index cho sqlite.
  4. Controller xử lý cả `ValidationException` mới lẫn `QueryException` của index MySQL (tên index mới).
  5. Trước khi thêm unique index: viết truy vấn kiểm tra dữ liệu trùng hiện có; nếu có, dừng lại báo quản trị để quyết định cách gộp/huỷ.
- **Tiêu chí hoàn thành:**
  - Test mới: gửi đơn lần 2 khi còn đơn active → 422 `active_order_exists`; đơn cũ bị huỷ thì đặt lại được.
  - Test cho luồng đặt hộ (`CreateProxyOrdersAction`) và admin đặt hộ (`PlaceOrderOnBehalfAction`).
  - `php artisan migrate` chạy thành công trên MySQL; `SHOW INDEX FROM orders` thấy index mới.
  - `php artisan test` PASS.
- **Rủi ro / lưu ý:** Migration thất bại nếu dữ liệu đã có đơn trùng → bắt buộc bước kiểm tra ở phương án (5).
- **Xác nhận của quản trị:** ✅ Đã xác nhận (05/10/2026: "Đồng ý thực hiện toàn bộ File 01")
- **Trạng thái thực hiện:** ✅ Hoàn thành

---

## UPG-01.2 – Chỉ một tài khoản thanh toán mặc định cho mỗi room

- **Mức độ:** 🟠 Trung bình
- **Hiện trạng:** Unique index `payment_accounts_one_default_per_room` cũng chỉ tạo cho pgsql/sqlite (`2026_09_10_000000_create_drinkflow_core_tables.php:254`). Trên MySQL một room có thể có nhiều `is_default = true`, dẫn đến VietQR chọn tài khoản không xác định (`OrderController::payment`, `UserPaymentsService`).
- **Phương án đề xuất:**
  1. Generated column `default_room_key` (= `room_id` khi `is_default = 1`, ngược lại `NULL`) + unique index trên MySQL.
  2. Action lưu tài khoản mặc định bỏ cờ mặc định của tài khoản cũ trong cùng transaction.
  3. Kiểm tra dữ liệu trùng trước khi thêm index, báo quản trị nếu có.
- **Tiêu chí hoàn thành:** Test đặt tài khoản mặc định mới thì tài khoản cũ mất cờ; migration chạy trên MySQL; `php artisan test` PASS.
- **Xác nhận của quản trị:** ✅ Đã xác nhận (05/10/2026: "Đồng ý thực hiện toàn bộ File 01")
- **Trạng thái thực hiện:** ✅ Hoàn thành

---

## UPG-01.3 – Index tổng hợp cho truy vấn đơn theo campaign + thành viên

- **Mức độ:** 🟡 Thấp (hiệu năng, hỗ trợ UPG-01.1 và UPG-06.4)
- **Hiện trạng:** Bảng `orders` chỉ có `(campaign_id, status)`, `(room_user_id, created_at)`. Các truy vấn "đơn của thành viên X trong campaign Y" (kiểm tra đơn active, `queryVisibleDebts`, `has_ordered` trên dashboard) phải lọc thêm trên tập đơn của campaign.
- **Phương án đề xuất:** Migration thêm index `(campaign_id, room_user_id, status)`. Nếu UPG-01.1 đã tạo unique index bắt đầu bằng `(campaign_id, ...)` thì đánh giá lại xem còn cần hay không.
- **Tiêu chí hoàn thành:** `EXPLAIN` truy vấn kiểm tra đơn active dùng index mới; migration chạy trên MySQL; `php artisan test` PASS.
- **Xác nhận của quản trị:** ✅ Đã xác nhận (05/10/2026: "Đồng ý thực hiện toàn bộ File 01")
- **Trạng thái thực hiện:** ✅ Hoàn thành
