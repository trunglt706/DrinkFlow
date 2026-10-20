# 1. Đăng nhập & khôi phục mật khẩu

<details open>
<summary><strong>📑 Menu điều hướng</strong> — bấm để chuyển nhanh sang trang khác</summary>

- [🏠 Trang chủ / Mục lục](README.md)
- **1. Đăng nhập & khôi phục mật khẩu** ← *đang xem*
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
- [14. Huy hiệu nhà tài trợ & thành tích trong năm](14-huy-hieu-thanh-tich.md)

</details>

## 1.1. Đăng nhập Cổng Quản Trị

Khác với User (đăng nhập bằng Google Workspace), Admin đăng nhập bằng **email công vụ + mật khẩu** tại đường dẫn riêng `/admin/login`.

1. Mở `/admin/login`.
2. Nhập **Email công vụ** (ví dụ `admin@company.com`) và **Mật khẩu**.
3. Có thể tick **"Ghi nhớ đăng nhập"** để không phải nhập lại trong phiên làm việc sau.
4. Bấm **"Đăng nhập hệ thống"**.

![Trang đăng nhập Cổng Quản Trị](images/00-dang-nhap.png)

Khu vực này được đánh dấu **"KHU VỰC TRUY CẬP GIỚI HẠN"** và có cảnh báo giám sát 24/7 ở cuối trang — chỉ tài khoản đã được cấp quyền Admin mới đăng nhập được.

### Xác thực hai lớp (2FA)

Nếu tài khoản của bạn đã bật 2FA (xem [bài 3](03-ho-so-bao-mat-admin.md)), sau khi nhập đúng email/mật khẩu, hệ thống sẽ yêu cầu bạn **tiếp tục xác thực bằng Google Workspace** của chính tài khoản đó thay vì vào thẳng hệ thống — bấm **"Đăng nhập bằng Google Workspace"** để hoàn tất, hoặc **"Hủy"** để quay lại màn hình đăng nhập.

## 1.2. Quên mật khẩu

1. Tại trang đăng nhập, bấm **"Quên mật khẩu?"**.
2. Nhập **Email công vụ** đã đăng ký rồi bấm **"Gửi mã xác thực OTP"**.

![Trang khôi phục mật khẩu](images/01-quen-mat-khau.png)

Hệ thống sẽ tạo mã OTP xác thực đa nhân tố (MFA) và gửi tới hộp thư công vụ đã được gán quyền quản lý Room, có hiệu lực trong **15 phút**. Nếu không nhận được mã sau 2 phút, kiểm tra thư mục Spam/Junk hoặc liên hệ IT Administrator.

## 1.3. Nhập mã OTP xác thực

Kiểm tra email và nhập **mã OTP 6 chữ số** vừa nhận được, sau đó bấm **"Xác thực & Tiếp tục"**. Nếu chưa nhận được mã, bấm **"Chưa nhận được mã? Gửi lại OTP"**.

![Trang nhập mã OTP](images/02-xac-thuc-otp.png)

## 1.4. Đặt mật khẩu mới

Sau khi xác thực OTP thành công, đặt mật khẩu mới đáp ứng yêu cầu bảo mật: **tối thiểu 8 ký tự**, **bao gồm chữ và số**, và **khớp với trường xác nhận**. Nhập xong bấm **"Lưu mật khẩu mới & Hoàn tất"**.

![Trang đặt mật khẩu mới](images/03-dat-lai-mat-khau.png)

Sau khi đặt lại thành công, bạn được đưa về trang đăng nhập để vào hệ thống bằng mật khẩu mới.

## 1.5. Đăng xuất

Bấm vào khu vực avatar/tên tài khoản ở góc phải thanh trên cùng (trong Cổng quản trị) rồi chọn **"Đăng xuất"**, xác nhận trong hộp thoại hiện ra.
