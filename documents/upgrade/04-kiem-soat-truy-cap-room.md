# UPG-04 – Kiểm soát truy cập Room

**Mục tiêu:** Trạng thái room (`active` / `inactive` / `archived`) và quy tắc IP của room được áp dụng nhất quán trên mọi route thành viên.

> Mỗi task con dưới đây phải được quản trị xác nhận bằng lời (nêu rõ mã task) trước khi thực hiện. Xem [README](README.md#1-quy-trình-bắt-buộc-xác-nhận-trước-khi-thực-hiện).

---

## UPG-04.1 – Middleware `room.user` kiểm tra trạng thái room

- **Mức độ:** 🟠 Trung bình
- **Hiện trạng:**
  - `ResolveRoomUser` (`src/app/Http/Middleware/ResolveRoomUser.php`) không kiểm tra `rooms.status`. Room `inactive`/`archived` vẫn mở được dashboard, đơn hàng, công nợ, thống kê.
  - Các action nhận `Request` thường (không qua FormRequest có `authorizeActiveRoomUser`) vẫn thực hiện được trên room đã tắt:
    - `POST orders/{order}/confirm-payment`
    - `POST campaigns/{campaign}/decline`, `rejoin`
    - `DELETE .../cart`, `PATCH .../cart/{index}`
- **Phương án đề xuất:**
  1. Trong `ResolveRoomUser`, nếu room không `active`: request JSON trả 404/403, request thường hiển thị trang thông báo room đã tạm dừng (có key dịch vi/en/ja).
  2. Quản trị chọn: room `inactive` có cho **xem** lịch sử/công nợ (chỉ đọc) hay chặn hoàn toàn.
- **Tiêu chí hoàn thành:** Test mỗi route thành viên với room `inactive` và `archived`. `php artisan test` PASS.
- **Quyết định áp dụng:** Room `inactive` và `archived` bị chặn hoàn toàn.
- **Xác nhận của quản trị:** ✅ Đã xác nhận — “Đồng ý UPG-04.1, UPG-04.2 và UPG-04.3”; “UPG-04.1: room inactive chặn hoàn toàn” (05/10/2026)
- **Trạng thái thực hiện:** ⚠️ Bị chặn — phần triển khai và test mục tiêu đã hoàn tất (`26 passed`, `167 assertions`; test mới UPG-04: `4 passed`, `35 assertions`). Toàn suite ngày 05/10/2026: `944 passed`, `2 skipped`, `3 failed`; ba lỗi đều thuộc billing/subscription đã tồn tại trước UPG-04 (`PlatformBillingTest`, `PlatformSecurityIsolationTest`, `SubscriptionManagementTest`).

---

## UPG-04.2 – Làm rõ việc áp dụng gói dịch vụ (subscription) phía thành viên

- **Mức độ:** 🟡 Thấp (cần quyết định nghiệp vụ)
- **Hiện trạng:** Route admin của room có middleware `room.subscription` (`src/routes/admin.php:80`). Route thành viên (`src/routes/user.php`) không có. Khi gói của chủ room hết hạn, thành viên vẫn đặt đơn bình thường.
- **Phương án đề xuất:** Chờ quản trị quyết định nghiệp vụ:
  - (a) giữ nguyên;
  - (b) chặn tạo đơn/campaign mới nhưng vẫn cho xem và thanh toán công nợ;
  - (c) chặn hoàn toàn.
- **Tiêu chí hoàn thành:** Theo phương án được chọn, có test tương ứng; `php artisan test` PASS.
- **Quyết định áp dụng:** Chọn (c), chặn hoàn toàn route thành viên khi subscription của chủ room không còn hiệu lực.
- **Xác nhận của quản trị:** ✅ Đã xác nhận — “Đồng ý UPG-04.1, UPG-04.2 và UPG-04.3”; “UPG-04.2: chọn (c) chặn hoàn toàn” (05/10/2026)
- **Trạng thái thực hiện:** ⚠️ Bị chặn — phần triển khai và test mục tiêu đã hoàn tất (`26 passed`, `167 assertions`; test mới UPG-04: `4 passed`, `35 assertions`). Toàn suite ngày 05/10/2026: `944 passed`, `2 skipped`, `3 failed`; ba lỗi đều thuộc billing/subscription đã tồn tại trước UPG-04 (`PlatformBillingTest`, `PlatformSecurityIsolationTest`, `SubscriptionManagementTest`).

---

## UPG-04.3 – Cấu hình TrustProxies cho quy tắc IP của room

- **Mức độ:** 🟠 Trung bình (phụ thuộc hạ tầng deploy)
- **Hiện trạng:** `room.ip` (`EnsureRoomIpAllowed`) dùng `$request->ip()`, nhưng `bootstrap/app.php` chưa cấu hình `trustProxies`.
  - Nếu chạy sau nginx reverse proxy / Cloudflare: mọi người dùng mang IP của proxy, danh sách IP cho phép/chặn không hoạt động đúng.
  - Nếu sau này cấu hình `at: '*'`: header `X-Forwarded-For` có thể bị giả mạo để vượt allowlist.
- **Phương án đề xuất:**
  1. Thêm `$middleware->trustProxies(at: config('trustedproxy.proxies'))`, đọc từ config (env `TRUSTED_PROXIES`, danh sách IP/CIDR cụ thể, **không** dùng `*` trên production).
  2. Ghi chú cấu hình vào `documents/deploy`.
- **Tiêu chí hoàn thành:** Test với header `X-Forwarded-For` từ proxy tin cậy / không tin cậy. `php artisan test` PASS.
- **Mô hình deploy:** Không sử dụng reverse proxy/CDN; ứng dụng không tin cậy `X-Forwarded-*` từ client.
- **Xác nhận của quản trị:** ✅ Đã xác nhận — “Đồng ý UPG-04.1, UPG-04.2 và UPG-04.3”; “UPG-04.3: không proxy” (05/10/2026)
- **Trạng thái thực hiện:** ⚠️ Bị chặn — phần triển khai và test proxy tin cậy/không tin cậy đã hoàn tất (`4 passed`, `35 assertions` trong test UPG-04). Toàn suite ngày 05/10/2026: `944 passed`, `2 skipped`, `3 failed`; ba lỗi đều thuộc billing/subscription đã tồn tại trước UPG-04 (`PlatformBillingTest`, `PlatformSecurityIsolationTest`, `SubscriptionManagementTest`).
