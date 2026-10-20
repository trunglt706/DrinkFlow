# 8. Cấu hình hệ thống & Tài khoản thanh toán

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
- **8. Cấu hình hệ thống & Tài khoản thanh toán** ← *đang xem*
- [9. Tích hợp Bot thông báo & Gửi thông báo](09-kenh-thong-bao-broadcast.md)
- [10. Báo cáo & Phân tích Logistics](10-bao-cao-thong-ke.md)
- [11. Nhật ký hoạt động (Audit Log)](11-nhat-ky-hoat-dong.md)
- [12. Quản lý sự kiện (sinh nhật, ngày lễ, âm thanh & lời chúc)](12-quan-ly-su-kien.md)
- [13. Vòng quay may mắn (Quay sponsor)](13-vong-quay-may-man.md)
- [14. Huy hiệu nhà tài trợ & thành tích trong năm](14-huy-hieu-thanh-tich.md)

</details>

Trang **"Cài Đặt cấu hình hệ thống"** (sidebar mục **"Cấu hình hệ thống"**) là nơi thiết lập các giá trị mặc định giúp [Tạo Chiến Dịch Nhanh](04-quan-ly-chien-dich.md) thật sự nhanh, cùng với quản lý tài khoản nhận tiền của Room.

![Trang cấu hình hệ thống của Room](images/13-cai-dat.png)

## 8.1. Truy cập phòng

- Công tắc **"Chế độ công khai"**: bật để bất kỳ ai có link đều tự tham gia được; tắt để giới hạn tham gia riêng tư hơn.
- **Liên kết tham gia phòng**: link mời cố định của Room, bấm biểu tượng 📋 để sao chép nhanh và gửi cho thành viên mới.

## 8.2. Mặc định chiến dịch

- **Mẫu tên chiến dịch**: đặt cú pháp tự động sinh tên, hỗ trợ các biến `{date}`, `{time}`, `{day_of_week}`, `{creator_name}` — bấm vào từng thẻ biến để chèn nhanh vào ô nhập.
- **Trần ngân sách tối đa mỗi sản phẩm**: giá trị mặc định được điền sẵn mỗi khi tạo chiến dịch mới (có thể sửa riêng cho từng chiến dịch nếu cần).

## 8.3. Chính sách Chi tiêu & Hạn mức Nợ

- **Trần công nợ cá nhân**: số tiền nợ tối đa một thành viên được phép tích luỹ.
- **Tự động khoá khi vượt hạn mức nợ**: bật để hệ thống tự động chặn thành viên đặt món thêm khi đã nợ vượt trần, cho tới khi thanh toán.

Lưu các thay đổi ở nhóm cấu hình bên trái bằng nút **"Lưu thay đổi"** cuối form.

## 8.4. Tài khoản thanh toán (VietQR)

Cột bên phải quản lý các **tài khoản ngân hàng nhận tiền** của Room — dùng để sinh mã VietQR cho thành viên chuyển khoản khi đặt món hoặc trả nợ.

- Bấm **"+ Thêm mới"** để mở hộp thoại **"Thêm tài khoản thanh toán"**: chọn **Ngân hàng** (tìm theo mã/tên), nhập **Số tài khoản** và **Chủ tài khoản**.
- Mỗi tài khoản hiển thị nhãn **"Mặc định"** (nếu là tài khoản nhận tiền chính của Room) và trạng thái hoạt động.
- Trên từng tài khoản: bấm **"Xem QR"** để phóng to mã QR dùng khi cần, biểu tượng ✏ để **sửa**, biểu tượng 🗑 để **xoá**.

> Tài khoản thanh toán mặc định của Room sẽ tự động được gợi ý khi tạo chiến dịch mới, nhưng bạn vẫn có thể chọn một tài khoản khác riêng cho từng chiến dịch nếu cần tách quỹ.
