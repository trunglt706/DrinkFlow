# 11. Nhật ký hoạt động (Audit Log)

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
- **11. Nhật ký hoạt động (Audit Log)** ← *đang xem*
- [12. Quản lý sự kiện (sinh nhật, ngày lễ, âm thanh & lời chúc)](12-quan-ly-su-kien.md)
- [13. Vòng quay may mắn (Quay sponsor)](13-vong-quay-may-man.md)
- [14. Huy hiệu nhà tài trợ & thành tích trong năm](14-huy-hieu-thanh-tich.md)

</details>

> Trang này chưa có ảnh mockup sẵn trong kho thiết kế của dự án nên bài viết chỉ mô tả bằng chữ; bố cục và nhãn nút đã được đối chiếu trực tiếp với mã nguồn Blade và file ngôn ngữ.

Trang **"Nhật ký hoạt động"** (sidebar, cùng nhóm với Báo cáo) ghi lại toàn bộ các thao tác quan trọng mà Admin thực hiện trong Room — dùng để tra soát khi có tranh chấp hoặc cần biết ai đã thay đổi gì, vào lúc nào.

## 11.1. Bộ lọc

- **Sự kiện**: lọc theo loại hành động cụ thể (ví dụ tạo/đóng chiến dịch, điều chỉnh giá đơn, duyệt thanh toán, khoá thành viên...).
- **Đối tượng tác động**: lọc theo loại dữ liệu bị thay đổi (đơn hàng, chiến dịch, công nợ, thành viên...).
- **Người thực hiện**: tìm theo tên/email Admin.
- **Khoảng thời gian**: chọn ngày bắt đầu/kết thúc.

Bấm **"Áp dụng"** để lọc hoặc **"Đặt lại"** để xoá bộ lọc.

## 11.2. Bảng nhật ký

Mỗi dòng ghi lại: **Thời điểm**, **Tên sự kiện**, **Người thực hiện**, **Địa chỉ IP** thực hiện thao tác, và nút **Chi tiết** để xem đầy đủ dữ liệu trước/sau thay đổi (payload) của sự kiện đó — hữu ích nhất khi cần tra lại **lý do và giá trị cũ/mới** của một lần [điều chỉnh giá đơn hàng](05-quan-ly-don-hang.md#53-điều-chỉnh-giá-thực-tế-của-đơn) hay một lần [điều chỉnh công nợ](06-quan-ly-cong-no.md).

Vì đây là nhật ký chỉ-đọc phục vụ minh bạch và đối soát, Admin không thể sửa hay xoá các bản ghi trong trang này.
