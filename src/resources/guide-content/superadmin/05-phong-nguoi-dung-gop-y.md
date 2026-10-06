# 5. Phòng, người dùng, chiến dịch & góp ý

## 5.1. Quản lý phòng

Menu **Hệ thống → Quản lý phòng**: tất cả phòng của mọi đại lý. Mở một phòng để xem số thành viên, chiến dịch, quản trị viên được phân công.

- **Bật / Tắt phòng**: luôn mở **hộp thoại xác nhận** trước khi thực hiện. Phòng bị tắt thì thành viên không truy cập được cho tới khi bật lại; dữ liệu phòng được giữ nguyên.
- **Sửa phòng**: đổi tên, slug (đổi slug làm liên kết cũ hết hiệu lực), trạng thái và quản trị viên.
- **Xóa phòng**: không thể hoàn tác.

## 5.2. Quản lý người dùng

Menu **Quản lý người dùng**: người dùng toàn cục (đăng nhập bằng Google Workspace). Có thể khóa/mở khóa, gỡ khỏi phòng, xóa tài khoản; dữ liệu đơn hàng và công nợ được giữ để đối soát.

## 5.3. Quản lý chiến dịch

Menu **Quản lý chiến dịch**: chiến dịch gom đơn của mọi phòng, dùng để tra soát hoặc can thiệp khi cần.

## 5.4. Người dùng góp ý

Menu **Người dùng góp ý**: phản hồi mới luôn ở trạng thái *Chờ duyệt*. Chỉ phản hồi đã duyệt và từ 3 sao trở lên mới hiển thị công khai ở `/me/feedback`.

| Thao tác | Mô tả |
| -------- | ----- |
| Duyệt / Gỡ duyệt | Hiển thị hoặc ẩn phản hồi khỏi trang công khai. |
| **Sửa** (biểu tượng bút) | Chỉnh nội dung, số sao, phân hệ hoặc tên hiển thị (ví dụ sửa lỗi chính tả, bỏ từ ngữ không phù hợp). Trạng thái duyệt được giữ nguyên. |
| **Xóa** (biểu tượng thùng rác) | Xóa vĩnh viễn phản hồi rác hoặc trùng lặp, có hộp thoại xác nhận. |

Mọi lần sửa/xóa đều được ghi vào [nhật ký kiểm toán](08-nhat-ky-bao-mat.md) kèm dữ liệu trước/sau.
