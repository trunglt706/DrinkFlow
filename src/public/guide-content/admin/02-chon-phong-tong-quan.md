# 2. Chọn phòng & Tổng quan vận hành

<details open>
<summary><strong>📑 Menu điều hướng</strong> — bấm để chuyển nhanh sang trang khác</summary>

- [🏠 Trang chủ / Mục lục](README.md)
- [1. Đăng nhập & khôi phục mật khẩu](01-dang-nhap-quan-tri.md)
- **2. Chọn phòng & Tổng quan vận hành** ← *đang xem*
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

## 2.1. Danh sách Room được phân quyền

Sau khi đăng nhập, nếu bạn quản lý nhiều hơn 1 Room, hệ thống đưa bạn tới trang **chọn Room** (`/admin`) liệt kê tất cả các phòng bạn được phân quyền quản lý — không hiển thị các Room khác trong hệ thống.

![Danh sách Room được phân quyền](images/04-danh-sach-phong.png)

Mỗi thẻ Room hiển thị:

- Nhãn trạng thái: **🔥 Đang có chiến dịch Live**, **⚡ Sắp mở chiến dịch**, hoặc **⚠️ Cần đối soát nợ** (khi có công nợ tồn đọng).
- Số **thành viên đang hoạt động** và **số đơn hôm nay**; nếu có nợ tồn đọng, hiển thị thêm tổng số tiền chưa thu.
- Nút **hình cái kẹp giấy** để **sao chép nhanh link mời tham gia Room** (gửi cho nhân sự mới).
- Nút **"Mở Dashboard"** để vào vận hành Room đó.

Dùng ô **tìm kiếm** hoặc các nút lọc **Tất cả / 🔥 Live / ⚠️ Nợ / Nhàn rỗi** để tìm nhanh Room cần xử lý.

> Nếu bạn chỉ quản lý đúng 1 Room, hệ thống bỏ qua bước chọn và đưa bạn thẳng vào Dashboard của Room đó.

## 2.2. Dashboard vận hành Room

Đây là màn hình vận hành chính sau khi chọn một Room, cập nhật theo thời gian thực qua Socket.IO.

![Dashboard vận hành Room](images/05-dashboard-room.png)

**5 chỉ số tổng quan** ở đầu trang:

| Chỉ số | Ý nghĩa |
| --- | --- |
| Room đang hoạt động | Số Room bạn quản lý đang có thành viên hoạt động |
| Chiến dịch Live | Số chiến dịch đang mở nhận đơn, kèm đồng hồ đếm ngược đến hạn chốt |
| Đơn hôm nay | Tổng số đơn đã đặt trong ngày, so với hôm qua |
| Tổng giá trị hôm nay | Tổng tiền đơn hàng hôm nay, kèm phần đã được tài trợ |
| Công nợ chưa thu | Tổng tiền còn nợ và số thành viên **"Cần đối soát"** |

Bên dưới là **biểu đồ xu hướng theo tuần** (số chiến dịch & chi tiêu), **thẻ Chiến dịch đang Live** với đồng hồ đếm ngược, tiến độ số người đã đặt/tổng thành viên, số tiền cần thu ròng và các nút **"Xem đơn & điều chỉnh"**, **"Điều chỉnh chiến dịch"**, **"Đóng sớm chiến dịch"**. Cuối trang là bảng **đơn hàng gần đây** cập nhật trực tiếp.

### Đóng sớm chiến dịch ngay từ Dashboard

Bấm **"Đóng sớm chiến dịch"** trên thẻ chiến dịch Live để mở hộp thoại xác nhận, hiển thị số thành viên đã đặt và tạm tính tổng tiền, có tuỳ chọn **thông báo qua Slack**. Bấm **"Xác nhận đóng ngay"** để chốt chiến dịch ngay lập tức (xem thêm quy trình đóng chiến dịch ở [bài 4](04-quan-ly-chien-dich.md)).
