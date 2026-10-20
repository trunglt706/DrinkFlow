# 9. Tích hợp Bot thông báo & Gửi thông báo

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
- **9. Tích hợp Bot thông báo & Gửi thông báo** ← *đang xem*
- [10. Báo cáo & Phân tích Logistics](10-bao-cao-thong-ke.md)
- [11. Nhật ký hoạt động (Audit Log)](11-nhat-ky-hoat-dong.md)
- [12. Quản lý sự kiện (sinh nhật, ngày lễ, âm thanh & lời chúc)](12-quan-ly-su-kien.md)
- [13. Vòng quay may mắn (Quay sponsor)](13-vong-quay-may-man.md)
- [14. Huy hiệu nhà tài trợ & thành tích trong năm](14-huy-hieu-thanh-tich.md)

</details>

> Trang này chưa có ảnh mockup sẵn trong kho thiết kế của dự án nên bài viết chỉ mô tả bằng chữ; bố cục và nhãn nút đã được đối chiếu trực tiếp với mã nguồn Blade và file ngôn ngữ.

## 9.1. Kết nối kênh thông báo (Webhook/Bot)

Vào sidebar mục **"Cấu hình hệ thống"** → bấm nút **"Kênh Webhook"** ở đầu trang Cài đặt (hoặc truy cập trực tiếp trang **"Tích Hợp Bot Thông Báo Đa Kênh"**). Đây là nơi kết nối Room với các kênh chat nội bộ để tự động bắn thông báo khi có chiến dịch mới, nhắc nợ...

Cột trái liệt kê các **kênh đã kết nối** (loại kênh, trạng thái Đã cấu hình/Chưa cấu hình, đang bật/tắt). Trên mỗi kênh có thể **Sửa**, **Test ping** (gửi thử một tin nhắn để kiểm tra kết nối hoạt động đúng) hoặc **Xoá**.

Cột phải là form **"Kết Nối Bot Mới"**:

1. Đặt **Tên kênh** dễ nhận biết (ví dụ "Slack #team-it").
2. Chọn **Nền tảng**: Telegram / Slack / Chatwork / Webhook tuỳ chỉnh.
3. Điền thông tin riêng theo từng nền tảng:
   - **Telegram**: Bot Token + Chat ID.
   - **Slack**: Slack Webhook URL.
   - **Chatwork**: API Token + Room ID.
   - **Webhook tuỳ chỉnh**: Endpoint URL, và Secret Token (tuỳ chọn) để xác thực chữ ký.
4. Bấm **"Lưu Webhook"**.

Bên dưới form luôn có khối **"Cách lấy thông tin tích hợp"** hướng dẫn từng bước lấy Token/Webhook URL tương ứng với nền tảng bạn vừa chọn.

## 9.2. Gửi thông báo hàng loạt (Broadcast)

Ngay trên thanh điều hướng trong một Room, bấm nút **"Gửi thông báo"** để chủ động gửi tin tới toàn bộ thành viên, không cần gắn với sự kiện tự động nào:

1. Chọn **Loại thông báo**: *Thông báo chung*, *Mở chiến dịch mới* hoặc *Nhắc thanh toán* — mỗi loại có sẵn mẫu tiêu đề/nội dung gợi ý để bạn chỉnh sửa nhanh.
2. Nhập **Tiêu đề** và **Nội dung**.
3. Gửi đi — thông báo sẽ tới đồng thời qua ứng dụng DrinkFlow của thành viên và các kênh webhook/bot đã kết nối ở mục 9.1.

## 9.3. Chuông thông báo của Admin

Biểu tượng 🔔 trên thanh điều hướng hiển thị các cập nhật gần đây (đơn mới, kết quả điều chỉnh giá, yêu cầu duyệt thanh toán, thành viên mới tham gia...). Bấm **"Đánh dấu tất cả đã đọc"** để dọn sạch danh sách sau khi đã xem.
