# 2. Đại lý & đăng ký

**Đại lý (Agent)** là khách hàng của nền tảng: họ mua gói dịch vụ và tự vận hành các phòng (Room) của mình tại `/admin`.

## 2.1. Danh sách đại lý

Menu **Đại lý → Danh sách đại lý**: tìm theo tên/email/công ty, lọc theo trạng thái. Superadmin có phạm vi *Đại lý được phân công* chỉ thấy các đại lý mình phụ trách.

## 2.2. Hồ sơ đại lý

Mở một đại lý để xem các tab:

| Tab | Nội dung |
| --- | -------- |
| Tổng quan | Trạng thái, gói hiện tại, hạn mức phòng, số tiền còn nợ, Superadmin phụ trách. |
| Thuê bao | Gói, chu kỳ, ngày hết hạn, lịch sử thay đổi gói. |
| Phòng | Các phòng đại lý sở hữu. |
| Chiến dịch | Chiến dịch gần đây trong các phòng của đại lý. |
| Công nợ | Hóa đơn nền tảng và các khoản đã thanh toán. |
| Hoạt động | Nhật ký các sự kiện liên quan tới đại lý. Mỗi dòng hiển thị **tên sự kiện dễ đọc** (ví dụ "Kích hoạt tài khoản đại lý"), kèm mã sự kiện gốc và người thực hiện. |

Các thao tác trên hồ sơ: **phân công / gỡ Superadmin phụ trách**, **tạm ngưng / mở lại** đại lý, **gửi lại email kích hoạt**.

## 2.3. Luồng đăng ký: trả tiền để kích hoạt

Đại lý tự đăng ký tại `/admin/register`:

1. Nhập họ tên, công ty, email, số điện thoại, chọn **gói** và **chu kỳ thanh toán** (số lượng + Tháng/Năm, mặc định 6 tháng). Không nhập mật khẩu ở bước này.
2. Hệ thống tạo ngay tài khoản ở trạng thái chờ, một **thuê bao chờ thanh toán** và **hóa đơn kỳ đầu** (giá tháng × số tháng), rồi gửi email gồm **liên kết kích hoạt** (xác minh email + đặt mật khẩu) và thông tin hóa đơn.
3. Sau khi đặt mật khẩu, đại lý đăng nhập được nhưng **chưa có hạn mức phòng**; hệ thống đưa họ tới trang *Lịch sử thanh toán* để thanh toán.
4. Khi hóa đơn kỳ đầu được thanh toán đủ (bạn ghi nhận, hoặc qua cổng thanh toán), gói được **kích hoạt tự động**, chu kỳ tính từ lúc thanh toán, và đại lý nhận email xác nhận.

**Không còn bước duyệt.** Trang *Đăng ký chờ duyệt* vẫn liệt kê các đăng ký để bạn theo dõi: hồ sơ đăng ký hiển thị hóa đơn kỳ đầu và nút mở công nợ của đại lý thay cho biểu mẫu duyệt. Nút **Từ chối** vẫn dùng được để loại đăng ký rác; khi đó hóa đơn kỳ đầu bị hủy và thuê bao chờ bị đóng.

> Gói miễn phí được kích hoạt ngay khi đăng ký (hóa đơn 0đ tự chuyển sang đã thanh toán).

## 2.4. Mời đại lý

Superadmin có thể tạo sẵn tài khoản đại lý và gửi **lời mời kích hoạt**; người được mời đặt mật khẩu qua liên kết và gói được bắt đầu ngay khi kích hoạt.
