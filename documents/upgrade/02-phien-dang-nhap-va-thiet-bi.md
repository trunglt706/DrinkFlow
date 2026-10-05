# UPG-02 – Phiên đăng nhập & thiết bị tin cậy

**Mục tiêu:** Các thao tác "đăng xuất thiết bị", "đăng xuất thiết bị khác" và cơ chế thiết bị tin cậy (`drinkflow_device_uuid` / `drinkflow_trusted_token`) phải có hiệu lực thật, token có thời hạn phía server.

> Mỗi task con dưới đây phải được quản trị xác nhận bằng lời (nêu rõ mã task) trước khi thực hiện. Xem [README](README.md#1-quy-trình-bắt-buộc-xác-nhận-trước-khi-thực-hiện).

---

## UPG-02.1 – Đăng xuất thiết bị khác phải vô hiệu hoá cookie "remember me"

- **Mức độ:** 🔴 Cao
- **Hiện trạng:**
  - Người dùng đăng nhập bằng `Auth::guard('web')->login($user, true)` (Google OAuth, thiết bị tin cậy), nên luôn có cookie remember.
  - `UserSessionService::logoutOtherDevices` và `logoutDevice` (`src/app/Services/User/UserSessionService.php:94`, `:118`) chỉ xoá bản ghi bảng `sessions`, **không đổi `remember_token`** của `GlobalUser`.
  - Thiết bị vừa bị đăng xuất sẽ tự đăng nhập lại ở request kế tiếp nhờ cookie remember.
  - Phía Admin đã làm đúng (`AdminAuthService.php:244` đổi `remember_token`), phía User chưa có.
- **Phương án đề xuất:**
  1. Trong `logoutOtherDevices`: đổi `remember_token` (`Str::random(60)`), sau đó gọi lại `Auth::guard('web')->login($user, true)` cho phiên hiện tại để phiên hiện tại nhận cookie remember mới.
  2. Áp dụng tương tự cho xoá tài khoản và khi superadmin/admin khoá user (nếu chưa có).
- **Tiêu chí hoàn thành:**
  - Test: phiên B có cookie remember; phiên A gọi "đăng xuất thiết bị khác"; request của B với cookie remember cũ → không còn đăng nhập.
  - Phiên A vẫn đăng nhập bình thường.
  - `php artisan test` PASS.
- **Xác nhận của quản trị:** ✅ Đã xác nhận (05/10/2026: "Tiếp tục File 02")
- **Trạng thái thực hiện:** ✅ Hoàn thành

---

## UPG-02.2 – "Đăng xuất một thiết bị" chỉ thu hồi đúng thiết bị được chọn

- **Mức độ:** 🔴 Cao (lỗi chức năng + bảo mật)
- **Hiện trạng:** `UserSessionService::logoutDevice($user, $sessionId, ...)` xoá đúng 1 session nhưng lại thu hồi **tất cả** thiết bị tin cậy khác của user (`UserSessionService.php:101-107`). Ngược lại, cookie remember của thiết bị bị chọn vẫn còn hiệu lực (xem UPG-02.1).
- **Phương án đề xuất:**
  1. Xác định thiết bị gắn với session bị chọn (lưu `device_uuid` vào payload/cột của session khi đăng nhập, hoặc cho phép chọn thiết bị theo `room_user_devices.id` trên trang `/me/devices`).
  2. Chỉ thu hồi thiết bị đó.
  3. Vì cookie remember không gắn với từng session, cần quản trị chọn một trong hai:
     - (a) đổi `remember_token`, chấp nhận các thiết bị khác cũng phải đăng nhập lại qua Google;
     - (b) chuyển sang cơ chế remember theo thiết bị (bảng riêng), phức tạp hơn.
- **Tiêu chí hoàn thành:** Test: user có 3 thiết bị, đăng xuất thiết bị 2 → thiết bị 1 và 3 vẫn hoạt động, thiết bị 2 không còn đăng nhập được. `php artisan test` PASS.
- **Cần quản trị quyết định:** phương án (a) hay (b) ở bước 3.
- **Xác nhận của quản trị:** ✅ Đã xác nhận (05/10/2026: "Tiếp tục File 02")
- **Trạng thái thực hiện:** ✅ Hoàn thành

---

## UPG-02.3 – Token thiết bị tin cậy có thời hạn phía server

- **Mức độ:** 🟠 Trung bình
- **Hiện trạng:** Cookie `drinkflow_trusted_token` hết hạn sau 30 ngày ở trình duyệt, nhưng `DeviceTrustService::resolve` (`src/app/Services/Auth/DeviceTrustService.php:35`) không kiểm tra tuổi token (`verified_at`/`last_seen_at`). Token bị lộ dùng được vô thời hạn cho tới khi bị thu hồi thủ công.
- **Phương án đề xuất:**
  1. Thêm `config('auth.trusted_device_ttl_days')` (mặc định 30).
  2. `resolve()` từ chối thiết bị có `verified_at` cũ hơn TTL (hoặc không hoạt động quá N ngày, tuỳ quản trị chọn).
  3. Tuỳ chọn: xoay vòng (rotate) token khi dùng để khôi phục phiên.
- **Tiêu chí hoàn thành:** Test token quá hạn → không khôi phục phiên, cookie bị xoá. `php artisan test` PASS.
- **Cần quản trị quyết định:** TTL tính theo ngày cấp hay theo lần hoạt động cuối; có rotate token hay không.
- **Xác nhận của quản trị:** ✅ Đã xác nhận (05/10/2026: "Tiếp tục File 02")
- **Trạng thái thực hiện:** ✅ Hoàn thành

---

## UPG-02.4 – `/logout` chỉ nhận POST có CSRF

- **Mức độ:** 🟡 Thấp
- **Hiện trạng:** `src/routes/user.php` khai báo `Route::match(['get', 'post'], '/logout', ...)` và `bootstrap/app.php` miễn CSRF cho `logout`. Trang ngoài có thể nhúng `<img src="/logout">` để ép người dùng đăng xuất.
- **Phương án đề xuất:**
  1. Rà các nơi gọi `/logout` (Blade, JS `logout-modal.js`) để đảm bảo đều gửi POST kèm token.
  2. Bỏ GET và bỏ miễn CSRF.
  3. Riêng trường hợp session hết hạn gây lỗi 419 khi logout: xử lý trong exception handler bằng cách redirect về `/`.
- **Tiêu chí hoàn thành:** GET `/logout` → 405; POST không token → không đăng xuất; POST có token → đăng xuất. `npm run build`, `php artisan test` PASS; kiểm tra lại nút đăng xuất trên trình duyệt.
- **Xác nhận của quản trị:** ✅ Đã xác nhận (05/10/2026: "Tiếp tục File 02")
- **Trạng thái thực hiện:** ✅ Hoàn thành
