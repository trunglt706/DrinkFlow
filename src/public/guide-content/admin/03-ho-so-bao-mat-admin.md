# 3. Hồ sơ quản trị viên & bảo mật

<details open>
<summary><strong>📑 Menu điều hướng</strong> — bấm để chuyển nhanh sang trang khác</summary>

- [🏠 Trang chủ / Mục lục](README.md)
- [1. Đăng nhập & khôi phục mật khẩu](01-dang-nhap-quan-tri.md)
- [2. Chọn phòng & Tổng quan vận hành](02-chon-phong-tong-quan.md)
- **3. Hồ sơ quản trị viên & bảo mật** ← *đang xem*
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
- [14. Huy hiệu nhà tài trợ & thành tích trong năm](14-huy-hieu-thanh-tich.md)

</details>

Vào trang này bằng cách bấm vào khu vực avatar/tên tài khoản ở góc phải thanh trên cùng, hoặc mục **"Hồ sơ & bảo mật"** ở cuối sidebar (đường dẫn `/admin/profile`).

![Trang hồ sơ quản trị viên](images/06-ho-so-admin.png)

## 3.1. Thông tin cơ bản

Khối bên trái cho phép cập nhật **Họ tên**, **Số điện thoại** và **Phòng ban**. Riêng **Email công vụ** đã xác thực thì **khoá, không sửa được** ở đây. Sửa xong bấm **"Lưu hồ sơ"**.

Đầu trang cũng hiển thị: vai trò (Admin/Siêu quản trị viên), số **Room được phân quyền**, ngày tạo tài khoản và lần đăng nhập gần nhất. Bấm biểu tượng máy ảnh nhỏ trên avatar để đổi ảnh đại diện.

## 3.2. Đổi mật khẩu đăng nhập

Trong khối **"Bảo mật đăng nhập"**, nhập **Mật khẩu hiện tại**, **Mật khẩu mới** và **Xác nhận mật khẩu mới**, sau đó bấm **"Cập nhật mật khẩu"**.

## 3.3. Bật/tắt xác thực hai lớp (2FA)

Ở cùng khối bảo mật, bấm nút gạt cạnh **"Xác thực hai lớp"** để bật hoặc tắt. Hệ thống sẽ mở hộp thoại yêu cầu **nhập lại mật khẩu hiện tại** để xác nhận thay đổi, sau đó bấm **"Xác nhận"**.

Khi 2FA đang bật, mỗi lần đăng nhập bằng email/mật khẩu, bạn sẽ cần xác thực thêm một bước qua Google Workspace của chính tài khoản Admin đó trước khi vào được hệ thống (xem [bài 1](01-dang-nhap-quan-tri.md)) — nên bật tính năng này để tăng an toàn cho tài khoản có quyền thao tác tài chính.
