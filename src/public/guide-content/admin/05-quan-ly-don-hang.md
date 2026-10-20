# 5. Quản lý Đơn gom & điều chỉnh giá

<details open>
<summary><strong>📑 Menu điều hướng</strong> — bấm để chuyển nhanh sang trang khác</summary>

- [🏠 Trang chủ / Mục lục](README.md)
- [1. Đăng nhập & khôi phục mật khẩu](01-dang-nhap-quan-tri.md)
- [2. Chọn phòng & Tổng quan vận hành](02-chon-phong-tong-quan.md)
- [3. Hồ sơ quản trị viên & bảo mật](03-ho-so-bao-mat-admin.md)
- [4. Quản lý Chiến dịch & Menu](04-quan-ly-chien-dich.md)
- **5. Quản lý Đơn gom & điều chỉnh giá** ← *đang xem*
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

Trang **"Quản lý Đơn Gom & Điều Chỉnh Giá"** (sidebar mục **"Đơn gom Realtime"**) tổng hợp **toàn bộ đơn hàng của Room**, không giới hạn theo một chiến dịch — dùng khi bạn cần tra cứu, chỉnh sửa hoặc xử lý hàng loạt đơn.

Nếu Room đang có chiến dịch Live, đầu trang hiển thị **thẻ chiến dịch đang mở** (hạn chốt đơn, ngân sách tối đa, chính sách tài trợ, tài khoản nhận thanh toán) để bạn nắm nhanh bối cảnh trước khi thao tác trên đơn.

## 5.1. Tìm kiếm, lọc và bảng đơn hàng

Dùng ô tìm kiếm và bộ lọc trạng thái để thu hẹp danh sách, bấm **"Lọc"** để áp dụng hoặc **"Xóa bộ lọc"** để về mặc định.

Bảng đơn hàng gồm các cột: **Mã đơn**, **Khách hàng**, **Chi tiết món**, **Trạng thái**, **Tạm tính thực tế**, và **Thao tác**. Mỗi dòng có ô tick để chọn phục vụ xử lý hàng loạt; dùng ô tick ở đầu bảng để **chọn tất cả** / **bỏ chọn tất cả**.

## 5.2. Đổi trạng thái đơn

Trạng thái đơn đi theo vòng đời: **Đã gửi → Đã xác nhận → Đang giao → Hoàn thành** (hoặc **Đã hủy**). Bấm vào ô trạng thái trên từng dòng để chọn trạng thái mới trực tiếp, hoặc mở menu **⋮** ở cuối dòng để xem **Chi tiết đơn hàng**, **Huỷ đơn**, hoặc **Mở khoá đơn** (khi đơn đang bị khoá do chiến dịch đã đóng nhưng cần chỉnh sửa lại).

### Xử lý hàng loạt

Chọn nhiều đơn bằng ô tick rồi dùng thanh công cụ hàng loạt hiện ra phía trên bảng để:

- Đổi trạng thái đồng loạt (ví dụ đánh dấu **"Hoàn thành"** cho cả loạt đơn cùng lúc).
- **Huỷ nhiều đơn** cùng lúc — hệ thống yêu cầu xác nhận trong hộp thoại **"Xác nhận hủy nhiều đơn hàng"** trước khi thực hiện, tránh thao tác nhầm.

## 5.3. Điều chỉnh giá thực tế của đơn

Vì giá trên app giao đồ ăn hoặc voucher có thể thay đổi so với lúc tạo chiến dịch, Admin được quyền chỉnh **giá thực tế** của từng món trong đơn khi chiến dịch còn đang Live.

![Điều chỉnh giá thực tế đơn hàng](images/10-dieu-chinh-gia.png)

1. Mở **Chi tiết đơn hàng** rồi bấm **"Điều chỉnh giá"** (hoặc từ menu **⋮**).
2. Với từng món, sửa **Giá thực tế** (khác với Giá ban đầu được giữ lại để đối chiếu).
3. Bấm **"Tính lại"** để xem tạm tính mới, rồi **"Lưu thay đổi"**.

Hệ thống sẽ **tự tính lại** toàn bộ: tạm tính, phần tài trợ (sponsor), số tiền cần thanh toán và công nợ liên quan — bạn không tự nhập các số này. Thành viên đặt đơn đó sẽ nhận **thông báo tức thì** báo giá đơn đã thay đổi, kèm số tiền cũ/mới.

> Mọi thay đổi giá đều được lưu vết trong [Nhật ký hoạt động](11-nhat-ky-hoat-dong.md) để đối chiếu về sau.
