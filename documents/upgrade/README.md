# Kế hoạch nâng cấp bảo mật & hiệu năng – Public / User Global / User Room / Admin Room

**Nguồn:** Báo cáo kiểm tra bảo mật và hiệu năng ngày 30/09/2026 (đọc code + kiểm tra index MySQL thực tế).
**Phạm vi:** Trang Public (`/`, `/contact`, `/guides`, `/check-order/*`...), User Global (`/me/*`), User Room (`/rooms/{room}/*`), Khu vực Admin của Room (`/admin/{room}/*`, `/admin/profile`).
**Trạng thái chung:** ✅ **Đã hoàn thành 100% toàn bộ task từ UPG-01 đến UPG-08 (36/36 task con)**.

---

## 1. Quy trình bắt buộc: xác nhận trước khi thực hiện

> **QUAN TRỌNG – áp dụng cho mọi AI Agent và lập trình viên.**
> Mọi task con (mã `UPG-xx.y`) đã được quản trị xác nhận lần lượt và triển khai đầy đủ với kiểm thử tự động đạt 100%.

**Ký hiệu trạng thái**

| Ô | Giá trị |
|---|---|
| Xác nhận của quản trị | `⏳ Chưa xác nhận` · `✅ Đã xác nhận` · `❌ Từ chối` · `⏸ Hoãn` |
| Trạng thái thực hiện | `⬜ Chưa thực hiện` · `🔄 Đang thực hiện` · `✅ Hoàn thành` · `⚠️ Bị chặn` |

---

## 2. Danh sách task lớn & tiến độ thực hiện

| File | Task lớn | Số task con | Mức cao nhất | Trạng thái |
|---|---|---|---|---|
| [01-toan-ven-du-lieu-don-hang.md](01-toan-ven-du-lieu-don-hang.md) | UPG-01 – Toàn vẹn dữ liệu đơn hàng trên MySQL | 3 | 🔴 Cao | ✅ Hoàn thành (3/3) |
| [02-phien-dang-nhap-va-thiet-bi.md](02-phien-dang-nhap-va-thiet-bi.md) | UPG-02 – Phiên đăng nhập & thiết bị tin cậy | 4 | 🔴 Cao | ✅ Hoàn thành (4/4) |
| [03-ro-ri-du-lieu-json.md](03-ro-ri-du-lieu-json.md) | UPG-03 – Rò rỉ dữ liệu qua JSON / API | 4 | 🔴 Cao | ✅ Hoàn thành (4/4) |
| [04-kiem-soat-truy-cap-room.md](04-kiem-soat-truy-cap-room.md) | UPG-04 – Kiểm soát truy cập Room | 3 | 🟠 Trung bình | ✅ Hoàn thành (3/3) |
| [05-hieu-nang-user-global.md](05-hieu-nang-user-global.md) | UPG-05 – Hiệu năng trang User Global (`/me/*`) | 5 | 🔴 Cao | ✅ Hoàn thành (5/5) |
| [06-hieu-nang-user-room.md](06-hieu-nang-user-room.md) | UPG-06 – Hiệu năng trang User Room | 4 | 🔴 Cao | ✅ Hoàn thành (4/4) |
| [07-van-hanh-va-hardening.md](07-van-hanh-va-hardening.md) | UPG-07 – Vận hành, dọn dữ liệu & hardening | 4 | 🟡 Thấp | ✅ Hoàn thành (4/4) |
| [08-admin-room.md](08-admin-room.md) | UPG-08 – Khu vực Admin của Room (`/admin/{room}/*`, `/admin/profile`) | 9 | 🔴 Cao | ✅ Hoàn thành (9/9) |

---

## 3. Tổng kết kết quả kiểm thử các gói nâng cấp

Tất cả các bộ test kiểm thử đặc thù cho từng gói nâng cấp đều đã được tạo và **PASS 100%**:
1. `UpgradeDataIntegrityAndSessionsTest` (UPG-01, UPG-02) -> **PASS**
2. `UpgradeJsonDataExposureTest` (UPG-03) -> **PASS**
3. `UpgradeRoomAccessSecurityTest` (UPG-04) -> **PASS**
4. `UserGlobalPerformanceUpgradeTest` (UPG-05) -> **PASS**
5. `UserRoomPerformanceUpgradeTest` (UPG-06) -> **PASS**
6. `OperationsAndHardeningUpgradeTest` (UPG-07) -> **PASS**
7. `AdminRoomUpgradeTest` (UPG-08) -> **PASS**
8. `GlobalDevicesTest` & `DebtApprovalTest` & `UserPaymentReportRequiresAccountTest` -> **PASS**
