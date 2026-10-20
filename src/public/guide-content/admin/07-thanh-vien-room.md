# 7. Danh bạ thành viên & thiết bị tin cậy

<details open>
<summary><strong>📑 Menu điều hướng</strong> — bấm để chuyển nhanh sang trang khác</summary>

- [🏠 Trang chủ / Mục lục](README.md)
- [1. Đăng nhập & khôi phục mật khẩu](01-dang-nhap-quan-tri.md)
- [2. Chọn phòng & Tổng quan vận hành](02-chon-phong-tong-quan.md)
- [3. Hồ sơ quản trị viên & bảo mật](03-ho-so-bao-mat-admin.md)
- [4. Quản lý Chiến dịch & Menu](04-quan-ly-chien-dich.md)
- [5. Quản lý Đơn gom & điều chỉnh giá](05-quan-ly-don-hang.md)
- [6. Sổ kế toán & Công nợ kép](06-quan-ly-cong-no.md)
- **7. Danh bạ thành viên & thiết bị tin cậy** ← *đang xem*
- [8. Cấu hình hệ thống & Tài khoản thanh toán](08-cau-hinh-he-thong.md)
- [9. Tích hợp Bot thông báo & Gửi thông báo](09-kenh-thong-bao-broadcast.md)
- [10. Báo cáo & Phân tích Logistics](10-bao-cao-thong-ke.md)
- [11. Nhật ký hoạt động (Audit Log)](11-nhat-ky-hoat-dong.md)
- [12. Quản lý sự kiện (sinh nhật, ngày lễ, âm thanh & lời chúc)](12-quan-ly-su-kien.md)
- [13. Vòng quay may mắn (Quay sponsor)](13-vong-quay-may-man.md)
- [14. Huy hiệu nhà tài trợ & thành tích trong năm](14-huy-hieu-thanh-tich.md)

</details>

Trang **"Danh Bạ Thành Viên & Quản Trị Thiết Bị Tin Cậy"** (sidebar mục **"Thành viên Room"**) quản lý toàn bộ người tham gia Room.

![Danh bạ thành viên Room](images/12-thanh-vien.png)

## 7.1. Tổng quan & tìm kiếm

4 thẻ KPI đầu trang cho biết **Tổng thành viên**, **Đang hoạt động**, **Bị chặn** và **Đang nợ**. Dùng ô tìm kiếm và bộ lọc trạng thái để tra nhanh một thành viên.

Bảng danh bạ hiển thị: **Thành viên** (tên + email, bấm vào để xem hồ sơ chi tiết), **Vai trò** (Thành viên/Quản trị/Chủ phòng), **Thiết bị tin cậy** (số thiết bị đã đăng nhập), **Số đơn đã đặt**, **Trạng thái** và **Thao tác**.

## 7.2. Thêm thành viên mới

Bấm **"+ Thêm thành viên"** ở góc phải, điền **Email**, **Họ tên**, **Số điện thoại** và **Vị trí/bàn làm việc**, rồi lưu lại — hữu ích khi cần thêm nhân sự mới vào Room theo cách thủ công thay vì đợi họ tự tham gia qua link mời.

## 7.3. Khoá / Mở khoá / Xoá thành viên

Trên mỗi dòng:

- Biểu tượng 🔒/🔓 để **chặn** hoặc **duyệt lại** một thành viên đang hoạt động.
- Biểu tượng 🗑 **"Xoá khỏi Room"** — thành viên chuyển sang trạng thái đã rời phòng (không xoá dữ liệu lịch sử).
- Với thành viên đã bị xoá, biểu tượng ♻ **"Khôi phục vào Room"** để đưa họ trở lại.

> Khoá/xoá thành viên **chỉ ảnh hưởng trong phạm vi Room này** — không ảnh hưởng tới tài khoản DrinkFlow toàn hệ thống của họ (xem phân biệt ở tài liệu User, [bài 11 – Khi tài khoản bị khoá/hạn chế](../user/11-tai-khoan-bi-khoa.md)).

## 7.4. Xử lý hàng loạt

Tick chọn nhiều thành viên để hiện thanh công cụ hàng loạt với 3 lựa chọn: **"Duyệt hàng loạt"**, **"Chặn hàng loạt"**, **"Xoá hàng loạt"** — hệ thống luôn yêu cầu xác nhận trước khi thực hiện.

## 7.5. Quản lý thiết bị tin cậy

Bấm vào nhãn **"🖥 X thiết bị"** trên một thành viên để mở hộp thoại quản lý thiết bị của họ — liệt kê các thiết bị đã đăng nhập tin cậy (không cần xác thực lại trong một khoảng thời gian). Bấm **"Thu hồi"** trên một thiết bị để buộc thiết bị đó đăng xuất và yêu cầu xác thực lại lần đăng nhập tiếp theo — dùng khi thành viên báo mất máy/đổi máy hoặc nghi ngờ có truy cập lạ.
