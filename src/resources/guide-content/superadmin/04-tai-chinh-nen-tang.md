# 4. Tài chính nền tảng

Đây là tài chính giữa **nền tảng ↔ đại lý** (hóa đơn thuê bao), tách biệt với công nợ trong phòng giữa đại lý và thành viên.

## 4.1. Doanh thu

Menu **Tài chính nền tảng → Doanh thu**: doanh thu định kỳ hàng tháng (MRR), số tiền đã thu, phân bổ theo gói.

## 4.2. Hóa đơn chưa thu

Menu **Hóa đơn chưa thu**: mọi hóa đơn *Chờ thanh toán* và *Quá hạn*. Mở công nợ một đại lý để:

- **Ghi nhận thanh toán** (một phần hoặc toàn bộ) kèm phương thức, mã tham chiếu và ghi chú. Thanh toán đủ hóa đơn kỳ đầu sẽ **kích hoạt gói** của đại lý mới đăng ký; thanh toán đủ hóa đơn nâng cấp sẽ **áp dụng gói mới**.
- **Hủy hóa đơn** khi phát hành nhầm; hóa đơn đã hủy không còn tính vào công nợ.

Đại lý tự xem, tải PDF và thanh toán hóa đơn của mình tại `/admin/billing`.

## 4.3. Các tác vụ tự động hằng ngày

| Thời điểm | Tác vụ |
| --------- | ------ |
| Hằng giờ | Gia hạn các thuê bao đến hạn (theo đúng chu kỳ) và sinh hóa đơn kỳ mới chưa thanh toán. |
| 08:00 | **Nhắc gia hạn**: gửi email cho đại lý có thuê bao sắp hết hạn (mặc định trước 7 ngày, mỗi kỳ một lần; không gửi nếu đại lý đã hủy gia hạn). |
| 01:30 | Đánh dấu **Quá hạn** các hóa đơn qua hạn thanh toán; hóa đơn nâng cấp chưa trả thì tự hết hiệu lực. |
| 01:45 | **Tự động tạm ngưng** đại lý còn hóa đơn quá hạn sau thời gian ân hạn. |

Khi một đại lý bị tạm ngưng tự động:

- Đại lý nhận **email** thông báo và hướng dẫn thanh toán.
- **Superadmin phụ trách** đại lý đó và các Superadmin có quyền xem công nợ **toàn bộ đại lý** nhận thông báo trên **chuông thông báo** (kèm cập nhật thời gian thực) và qua các **kênh gateway** đã cấu hình (Slack, Telegram, ChatWork, webhook).
- Dịch vụ **tự mở lại** ngay khi các hóa đơn quá hạn được thanh toán.

Hóa đơn đăng ký chưa thanh toán không bao giờ gây tạm ngưng (đại lý chưa có dịch vụ nào để ngưng).

Các mốc thời gian được cấu hình bằng biến môi trường: `PLATFORM_INVOICE_DUE_DAYS` (hạn thanh toán), `PLATFORM_INVOICE_GRACE_DAYS` (ân hạn), `PLATFORM_RENEWAL_REMINDER_DAYS` (nhắc gia hạn), `PLATFORM_AUTO_SUSPEND` (bật/tắt tự động tạm ngưng).
