# 4. Quản lý Chiến dịch & Menu

<details open>
<summary><strong>📑 Menu điều hướng</strong> — bấm để chuyển nhanh sang trang khác</summary>

- [🏠 Trang chủ / Mục lục](README.md)
- [1. Đăng nhập & khôi phục mật khẩu](01-dang-nhap-quan-tri.md)
- [2. Chọn phòng & Tổng quan vận hành](02-chon-phong-tong-quan.md)
- [3. Hồ sơ quản trị viên & bảo mật](03-ho-so-bao-mat-admin.md)
- **4. Quản lý Chiến dịch & Menu** ← *đang xem*
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

**Chiến dịch (Campaign)** là một đợt gom đơn cụ thể — quán, menu, hạn chốt đơn, chính sách tài trợ. Đây là công cụ vận hành trung tâm của Admin, truy cập qua sidebar mục **"Chiến dịch & Menu"**.

## 4.1. Danh sách chiến dịch

Trang liệt kê toàn bộ chiến dịch của Room, có thể **tìm kiếm**, lọc theo **trạng thái** (Nháp/Đang mở/Sắp đóng/Đã đóng/Đã hủy...). Mỗi dòng hiển thị quán/thương hiệu, khung thời gian nhận đơn, trạng thái, số đơn và tổng tiền tạm tính.

Bấm nút **⋮ (thao tác)** cuối mỗi dòng để mở menu nhanh tuỳ theo trạng thái chiến dịch:

- **Xem chi tiết chiến dịch** — luôn có.
- **Chỉnh sửa chiến dịch** — khi còn ở trạng thái Nháp/Đang mở/Sắp đóng.
- **Đóng sớm chiến dịch** — khi đang mở/sắp đóng.
- **Hủy Chiến Dịch** — khi đang mở/sắp đóng.
- **Lưu trữ** — khi đã đóng.

Nếu Room **chưa có chiến dịch nào đang mở**, nút **"Tạo Chiến Dịch Nhanh"** sẽ hiện ở góc phải để bắt đầu ngay.

## 4.2. Tạo Chiến Dịch Nhanh

Mục tiêu của màn hình này là tạo chiến dịch **nhanh nhất có thể** — không bắt bạn nhập lại những gì đã có sẵn trong [Cài đặt hệ thống](08-cau-hinh-he-thong.md) (tên mẫu, ngân sách tối đa, tài khoản nhận tiền mặc định...).

![Trang Tạo Chiến Dịch Nhanh](images/07-tao-chien-dich.png)

### Thông tin chiến dịch

Điền **Tên chiến dịch**, **Thương hiệu/Quán**, **Hạn chốt đơn**, chọn **Tài khoản VietQR nhận tiền** (mặc định lấy theo Room nếu bỏ trống) và **Mô tả** (tuỳ chọn).

### Nguồn dữ liệu Menu — 3 cách nạp món nhanh

1. **Chiến dịch trước** — chọn một chiến dịch cũ để sao chép lại toàn bộ món, giá, hình ảnh (không sao chép đơn hàng cũ).
2. **Chuyển đổi AI (Data Gateway)** — dán nội dung/ảnh menu, hệ thống sinh prompt và chuyển đổi thành danh sách món chuẩn hoá để bạn xem trước trước khi nạp vào.
3. **Crawl từ URL** — dán link menu từ app giao đồ ăn hoặc website quán, bấm **"Crawl menu"**, hệ thống tự trích xuất món để bạn xem trước.

Dù chọn nguồn nào, bạn vẫn có thể **thêm món thủ công** bằng nút **"+ Thêm món"** ở khối **Xem trước Menu đã chọn**: nhập danh mục, tên món, giá, ảnh (dán URL hoặc tải file), mô tả, và ở tab **"Tuỳ chọn khác"** có thể thêm **Topping** và **Size** riêng cho món đó. Mỗi món có công tắc **đang bán/ngừng bán** và có thể **sửa** hoặc **xoá** trực tiếp trong danh sách xem trước, dùng ô tìm kiếm hoặc chế độ xem theo danh mục để duyệt nhanh khi menu dài.

### Chính sách tài trợ (Sponsor)

Chọn **Hình thức tài trợ**: *Không tài trợ* hoặc *Tài trợ toàn bộ*. Với tài trợ toàn bộ, bấm **"+ Thêm Sponsor"** để tìm và chọn thành viên tài trợ, nhập **% đóng góp** cho từng người. Có thể đặt thêm **Trần ngân sách tối đa mỗi sản phẩm** và **Mô tả chính sách tài trợ** hiển thị cho thành viên khi đặt món.

### Lưu & Phát động

- **"Lưu bản nháp"** — lưu lại chưa công bố, thành viên chưa nhìn thấy.
- **"Phát động chiến dịch"** — mở hộp thoại xác nhận tóm tắt (tên, quán, ngân sách tối đa, số món, hạn chốt đơn) rồi bấm **"Xác nhận & Phát động"** để chính thức mở cho thành viên đặt món ngay.

## 4.3. Trung tâm điều khiển chiến dịch (Control Center)

Bấm vào một chiến dịch để vào trang chi tiết, có 3 tab con:

![Chi tiết chiến dịch — Control Center](images/08-chi-tiet-chien-dich.png)

| Tab | Nội dung |
| --- | -------- |
| **Thông tin Chiến dịch** | Tổng quan: trạng thái, đồng hồ đếm ngược, chính sách tài trợ, điều chỉnh phụ phí/giảm giá, trạng thái giao hàng, mã QR thanh toán |
| **Danh sách Món & Đơn hàng** | Xem đơn thời gian thực, tổng hợp món theo phòng ban, sổ nợ cá nhân, danh sách user không tham gia/chưa phản hồi |
| **Điều chỉnh Menu** | Thêm/sửa/ẩn món, quản lý topping và size (chỉ hiện khi chiến dịch chưa bị khoá) |

### Tab "Danh sách Món & Đơn hàng"

Đây là nơi theo dõi một chiến dịch đang Live theo thời gian thực:

![Theo dõi đơn hàng trực tiếp trong chiến dịch](images/09-don-hang-truc-tiep.png)

- **Danh sách đơn hàng** — từng đơn kèm người đặt, món, trạng thái; có thể xem chi tiết, xác nhận, huỷ đơn.
- **Món theo phòng ban** — tổng hợp số lượng từng món cần chuẩn bị, tiện gửi cho quán.
- **Sổ nợ cá nhân** — theo dõi ai đã trả/chưa trả cho chiến dịch này.
- **User không tham gia** — danh sách thành viên đã chủ động bấm "Không tham gia".
- **User chưa phản hồi** — thành viên đã nhận thông báo nhưng chưa đặt món cũng chưa từ chối, giúp bạn biết nên nhắc ai.

### Điều chỉnh phụ phí, giảm giá & giao hàng

Trong tab Thông tin, khối **"Điều chỉnh phụ phí & giảm giá"** cho phép cập nhật phí giao hàng/khuyến mãi phát sinh sau khi quán chốt đơn thực tế. Khối **"Trạng thái giao hàng & Nhận món"** có nút **"Món đã được giao đến"** để báo cho thành viên biết đồ đã tới Room.

### Các thao tác vòng đời khác

- **"Gửi lại thông báo"** — bắn lại thông báo mời đặt món cho thành viên (Slack/Telegram/push tuỳ kênh đã kết nối, xem [bài 9](09-kenh-thong-bao-broadcast.md)).
- **"Nhân bản chiến dịch"** — tạo nhanh một bản nháp mới sao chép cấu hình từ chiến dịch hiện tại.
- **"Gia hạn thêm thời gian"** — dời hạn chốt đơn khi cần.
- **Đóng chiến dịch** — mở hộp thoại **"Xác nhận kết thúc đơn & Chốt chiến dịch"**; sau khi đóng, hệ thống tự tính tổng kết cuối cùng và chuyển sang [Sổ kế toán & Công nợ](06-quan-ly-cong-no.md) nếu còn khoản chưa thu.
- **Huỷ chiến dịch** — dùng khi cần dừng hẳn chiến dịch (ví dụ quán báo nghỉ), khác với đóng bình thường.
