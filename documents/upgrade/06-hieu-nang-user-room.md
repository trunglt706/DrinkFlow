# UPG-06 – Hiệu năng trang User Room (`/rooms/{room}/*`)

**Mục tiêu:** Các trang trong room không quét toàn bộ đơn của room ở mỗi lượt xem, không ghi DB ở mọi request, và không lọc dữ liệu lớn bằng PHP.

> Mỗi task con dưới đây phải được quản trị xác nhận bằng lời (nêu rõ mã task) trước khi thực hiện. Xem [README](README.md#1-quy-trình-bắt-buộc-xác-nhận-trước-khi-thực-hiện).

---

## UPG-06.1 – Dashboard room: biểu đồ 7 ngày bằng 1 truy vấn + cache

- **Mức độ:** 🔴 Cao (chạy ở mỗi lượt mở dashboard, tăng theo số đơn của room)
- **Hiện trạng:** `UserRoomDashboardService::getWeeklyItemTrend` (`src/app/Services/Dashboard/UserRoomDashboardService.php:132`) chạy vòng lặp 7 ngày, mỗi ngày 2 truy vấn, tổng cộng 14 truy vấn. Các truy vấn dùng `whereDate('created_at', ...)`, nên không dùng được index `(room_id, created_at)` và phải quét mọi đơn của room.
- **Phương án đề xuất:**
  1. Một truy vấn cho `SUM(subtotal)` theo ngày và một truy vấn `SUM(order_items.quantity)` theo ngày, với `created_at` trong khoảng 7 ngày và `GROUP BY DATE(created_at)`. Điền 0 cho ngày trống ở PHP.
  2. Cache kết quả theo room trong 1–5 phút (quản trị chọn). Xoá cache khi có đơn mới nếu cần số liệu tức thời.
  3. Xem xét cache tương tự cho `topSponsors`.
- **Tiêu chí hoàn thành:** Test số liệu 7 ngày khớp dữ liệu mẫu (có đơn huỷ, đơn ở biên ngày). Dashboard còn ≤ 2 truy vấn cho biểu đồ. `php artisan test` PASS.
- **Cần quản trị quyết định:** Thời gian cache chấp nhận được (chọn 2 phút).
- **Xác nhận của quản trị:** ✅ Đã xác nhận — “tiến hành tiếp file 06: từ UPG-06.1 đến UPG-06.4” (05/10/2026)
- **Trạng thái thực hiện:** ⚠️ Bị chặn — phần triển khai và test mục tiêu đã hoàn tất (`UserRoomPerformanceUpgradeTest` PASS, 143/143 User tests PASS); suite chung còn 3 lỗi ngoài phạm vi UPG-06 tại billing/subscription.
- **Kết quả kiểm tra (05/10/2026):** Tổng hợp biểu đồ 7 ngày bằng 2 truy vấn SQL theo khoảng ngày, cache kết quả 2 phút; kiểm tra lần gọi thứ hai lấy từ cache không phát sinh query biểu đồ; `UserRoomPerformanceUpgradeTest` PASS.

---

## UPG-06.2 – Giảm ghi DB ở mỗi request vào room

- **Mức độ:** 🟠 Trung bình
- **Hiện trạng:** Mỗi request vào `/rooms/{room}/*`:
  - `ResolveRoomUser` luôn `update(['last_active_at' => now()])` (`src/app/Http/Middleware/ResolveRoomUser.php:91`);
  - `DeviceTrustService::resolve` bị gọi 2 lần (trong `ResolveGlobalUser` và `ResolveRoomUser`), mỗi lần cập nhật `last_seen_at`;
  - Room có thể bị tra lại nhiều lần (`EnsureRoomIpAllowed::room`, `ResolveRoomUser`).

  Kết quả là tới 3 câu `UPDATE` cho mỗi lượt xem trang, và thêm nữa với các request AJAX/polling.
- **Phương án đề xuất:**
  1. Chỉ cập nhật `last_active_at` / `last_seen_at` khi giá trị cũ hơn 5 phút.
  2. Ghi nhớ kết quả `resolve()` trong request (thuộc tính của service, hoặc `request()->attributes`) để middleware thứ hai dùng lại.
- **Tiêu chí hoàn thành:** Test: 2 request liên tiếp trong 5 phút chỉ sinh 1 lần cập nhật. Chức năng thu hồi thiết bị vẫn đúng. `php artisan test` PASS.
- **Xác nhận của quản trị:** ✅ Đã xác nhận — “tiến hành tiếp file 06: từ UPG-06.1 đến UPG-06.4” (05/10/2026)
- **Trạng thái thực hiện:** ⚠️ Bị chặn — phần triển khai và test mục tiêu đã hoàn tất; suite chung còn 3 lỗi ngoài phạm vi UPG-06 tại billing/subscription.
- **Kết quả kiểm tra (05/10/2026):** Memoize kết quả `resolve()` trong request, tái sử dụng `Room` instance qua request attributes, kiểm tra ngưỡng 5 phút trước khi update `last_active_at` và `last_seen_at`; `UserRoomPerformanceUpgradeTest` PASS.

---

## UPG-06.3 – Tra cứu thành viên / tra cứu đơn công khai bằng truy vấn có index

- **Mức độ:** 🟠 Trung bình
- **Hiện trạng:**
  - `CampaignController::lookupMember`: tải **toàn bộ** thành viên active của room kèm `globalUser`, rồi lọc bằng PHP.
  - `PublicOrderCheckService::findOrders` (`src/app/Services/Order/PublicOrderCheckService.php:57`): tải **toàn bộ** đơn của campaign kèm 5 quan hệ, rồi lọc bằng PHP ở mỗi lần tra cứu.
- **Phương án đề xuất:**
  1. Truy vấn trực tiếp theo `room_users.user_code` (đã unique), `global_users.email` (có index), `orders.code` (unique).
  2. Số điện thoại: chuẩn hoá khi lưu (cột `phone_normalized` + index), hoặc giữ so khớp nhưng chỉ trong phạm vi hẹp. Quản trị chọn.
  3. Chỉ eager-load quan hệ cho các đơn khớp.
- **Tiêu chí hoàn thành:** Test tra cứu theo từng loại định danh cho kết quả như cũ; số bản ghi tải không phụ thuộc quy mô room/campaign. `php artisan test` PASS.
- **Xác nhận của quản trị:** ✅ Đã xác nhận — “tiến hành tiếp file 06: từ UPG-06.1 đến UPG-06.4” (05/10/2026)
- **Trạng thái thực hiện:** ⚠️ Bị chặn — phần triển khai và test mục tiêu đã hoàn tất; suite chung còn 3 lỗi ngoài phạm vi UPG-06 tại billing/subscription.
- **Kết quả kiểm tra (05/10/2026):** Tra cứu thành viên và đơn công khai tìm trực tiếp qua SQL index (`user_code`, `email`, `code`), chỉ eager-load trên tập kết quả tìm thấy; hỗ trợ fallback giải mã phone an toàn; `UserRoomPerformanceUpgradeTest` PASS.

---

## UPG-06.4 – Tối ưu truy vấn công nợ hiển thị cho thành viên

- **Mức độ:** 🟡 Thấp
- **Hiện trạng:**
  - `UserRoomDebtService::queryVisibleDebts` (`src/app/Services/Debt/UserRoomDebtService.php:131`) dùng hai subquery lồng `whereHas('campaign.orders')` / `orWhereDoesntHave('campaign.orders')` cho mỗi công nợ.
  - `getDebtViewData` chạy nhiều truy vấn clone: danh sách, chưa trả, đã trả tháng này, tổng tài trợ.
- **Phương án đề xuất:**
  1. Viết lại điều kiện thành `whereExists` / `whereNotExists` trực tiếp trên `orders` theo `(campaign_id, room_user_id)`, dùng index ở UPG-01.3.
  2. Gộp các số tổng vào 1 truy vấn `SUM(CASE ...)`.
- **Tiêu chí hoàn thành:** Test hiển thị công nợ như cũ, bao gồm trường hợp công nợ của nhà tài trợ và đơn đã huỷ. `php artisan test` PASS.
- **Phụ thuộc:** Nên làm sau UPG-01.3.
- **Xác nhận của quản trị:** ✅ Đã xác nhận — “tiến hành tiếp file 06: từ UPG-06.1 đến UPG-06.4” (05/10/2026)
- **Trạng thái thực hiện:** ⚠️ Bị chặn — phần triển khai và test mục tiêu đã hoàn tất; suite chung còn 3 lỗi ngoài phạm vi UPG-06 tại billing/subscription.
- **Kết quả kiểm tra (05/10/2026):** Đã viết lại điều kiện `queryVisibleDebts` dùng `whereExists` và `whereNotExists` trực tiếp trên bảng `orders` theo `campaign_id` và `room_user_id`; bảo đảm chính xác công nợ nhà tài trợ và ẩn công nợ chỉ có đơn đã huỷ; `UserRoomPerformanceUpgradeTest` PASS.
