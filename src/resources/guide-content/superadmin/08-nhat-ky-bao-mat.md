# 8. Nhật ký & bảo mật

## 8.1. Nhật ký hệ thống (Audit log)

Menu **Quản trị → Nhật ký hệ thống**: lịch sử các thao tác quan trọng do Superadmin thực hiện trên toàn nền tảng.

Bộ lọc:

| Bộ lọc | Mô tả |
| ------ | ----- |
| **Tác nhân** | Chỉ xem thao tác của một Superadmin cụ thể (danh sách gồm các Superadmin đã có hoạt động trong nhật ký). |
| Sự kiện | Loại hành động (ví dụ tạm ngưng đại lý, ghi nhận thanh toán, sửa phản hồi). |
| Đối tượng | Loại dữ liệu bị tác động (đại lý, hóa đơn, phòng, phản hồi...). |
| Khoảng thời gian | Ngày bắt đầu / kết thúc. |

Mỗi dòng hiển thị **tên sự kiện dễ đọc** kèm mã sự kiện gốc, người thực hiện, đối tượng và phòng liên quan. Nhật ký chỉ đọc, không thể sửa hay xóa.

> Lịch sử riêng của từng đại lý (kể cả thao tác do hệ thống hoặc chính đại lý thực hiện) xem ở tab **Hoạt động** trong hồ sơ đại lý.

## 8.2. Trung tâm bảo mật

Menu **Trung tâm bảo mật**: theo dõi đăng nhập thất bại, tài khoản bị khóa tạm và các cảnh báo bảo mật; dùng để phát hiện dò mật khẩu hoặc truy cập bất thường.
