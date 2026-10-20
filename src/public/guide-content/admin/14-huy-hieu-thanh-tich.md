# 14. Huy hiệu nhà tài trợ & thành tích trong năm

<details open>
<summary><strong>📑 Menu điều hướng</strong> — bấm để chuyển nhanh sang trang khác</summary>

- [🏠 Trang chủ / Mục lục](README.md)
- [1. Đăng nhập & khôi phục mật khẩu](01-dang-nhap-quan-tri.md)
- [2. Chọn phòng & Tổng quan vận hành](02-chon-phong-tong-quan.md)
- [3. Hồ sơ quản trị viên & bảo mật](03-ho-so-bao-mat-admin.md)
- [4. Quản lý Chiến dịch & Menu](04-quan-ly-chien-dich.md)
- [5. Quản lý Đơn gom & điều chỉnh giá](05-quan-ly-don-hang.md)
- [6. Sổ kế toán & Công nợ kép](06-quan-ly-cong-no.md)
- [7. Danh bạ thành viên & thiết bị tin cậy](07-thanh-vien-room.md)
- [8. Cấu hình hệ thống & Tài khoản thanh toán](08-cau-hinh-he-thong.md)
- [9. Tích hợp Bot thông báo & Gửi thông báo](09-kenh-thong-bao-broadcast.md)
- [10. Báo cáo & Phân tích Logistics](10-bao-cao-thong-ke.md)
- [11. Nhật ký hoạt động (Audit Log)](11-nhat-ky-hoat-dong.md)
- [12. Quản lý sự kiện (sinh nhật, ngày lễ, âm thanh & lời chúc)](12-quan-ly-su-kien.md)
- [13. Vòng quay may mắn (Quay sponsor)](13-vong-quay-may-man.md)
- **14. Huy hiệu nhà tài trợ & thành tích trong năm** ← *đang xem*

</details>

DrinkFlow vinh danh những thành viên **tài trợ nhiều nhất mỗi tháng** bằng huy hiệu (huy chương) và cho mỗi thành viên xem lại **thành tích trong năm** của mình.

## 14.1. Cách tính huy hiệu

- Vào **00:15 ngày đầu mỗi tháng**, hệ thống thống kê tổng số tiền tài trợ của từng thành viên trong tháng trước, dựa trên các **đơn đã hoàn thành** của các chiến dịch tạo trong tháng.
- **3 thành viên tài trợ nhiều nhất** nhận huy hiệu **Vàng (TOP 1)**, **Bạc (TOP 2)**, **Đồng (TOP 3)**; nếu bằng tiền, thành viên có mã thành viên nhỏ hơn (tham gia sớm hơn) được xếp trên.
- Khối **"TOP 3 nhà tài trợ"** trên trang Tổng quan của Admin và của thành viên hiển thị kết quả tháng trước; huy hiệu cũng hiện cạnh tên người đạt trong phòng.

## 14.2. Thống kê lại

Khi dữ liệu đơn hàng của tháng trước thay đổi (ví dụ hoàn tất thêm đơn, điều chỉnh tài trợ), bấm **Thống kê lại** ở khối TOP 3 nhà tài trợ trên trang Tổng quan để tính lại bảng xếp hạng tháng trước. Thao tác này bị giới hạn số lần trong một khoảng thời gian để tránh quá tải. Trang **Thành tích** của thành viên được làm mới ngay sau khi thống kê lại.

## 14.3. Báo cáo huy hiệu theo năm

Trong trang **Báo cáo**, chọn tab **Huy hiệu**:

- Chọn **Năm** rồi bấm **Xem**.
- **Huy hiệu theo tháng**: TOP 3 nhà tài trợ của từng tháng đã kết thúc trong năm, kèm số tiền.
- **Tổng huy hiệu theo thành viên**: số huy hiệu Vàng/Bạc/Đồng và tổng mỗi thành viên đạt được trong năm.

## 14.4. Trang Thành tích của thành viên

Mỗi thành viên có tab **Thành tích** trong Room (`/rooms/{room}/achievements`) để xem:

- tổng số huy chương trong năm hiện tại và số huy chương Vàng/Bạc/Đồng;
- thành tích từng tháng (huy chương, hạng và số tiền đã tài trợ; tháng hiện tại hiển thị "Đang diễn ra");
- lời nhắn động viên tuỳ theo số huy chương đã có.

Dữ liệu trang được lưu đệm tối đa một ngày và tự làm mới khi số liệu được thống kê lại. Admin không cần cấu hình gì thêm cho trang này.

---

[🏠 Mục lục](README.md)
