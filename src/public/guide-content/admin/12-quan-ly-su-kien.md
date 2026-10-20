# 12. Quản lý sự kiện (sinh nhật, ngày lễ, âm thanh & lời chúc)

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
- [11. Nhật ký hoạt động (Audit Log)](11-nhat-ky-hoat-dong.md)
- **12. Quản lý sự kiện (sinh nhật, ngày lễ, âm thanh & lời chúc)** ← *đang xem*
- [13. Vòng quay may mắn (Quay sponsor)](13-vong-quay-may-man.md)
- [14. Huy hiệu nhà tài trợ & thành tích trong năm](14-huy-hieu-thanh-tich.md)

</details>

Trang **Sự kiện** (menu bên trái, biểu tượng 🎉 — `/admin/{room}/events`) giúp Admin theo dõi các sự kiện sắp tới của phòng, kiểm tra thông báo đã được gửi hay chưa, gửi lại thông báo và tuỳ chỉnh **nhạc nền, hiệu ứng âm thanh, lời chúc** trên trang chúc mừng của từng sự kiện.

## 12.1. Các sự kiện được hỗ trợ

- **Sinh nhật thành viên**: hiển thị trên trang phòng từ 3 ngày trước đến 3 ngày sau ngày sinh (dựa trên ngày sinh thành viên nhập trong hồ sơ hoặc Admin nhập khi thêm thành viên).
- **Ngày lễ**: Tết Dương lịch (01/01), Tết Nguyên Đán (tự tính theo âm lịch, hiển thị trước/sau 7 ngày), Quốc tế Phụ nữ (08/03), Phụ nữ Việt Nam (20/10), Quốc tế Đàn ông (19/11), Giáng sinh (25/12) — các ngày lễ khác Tết chỉ hiển thị **đúng ngày**.

Mỗi sự kiện có một trang chúc mừng riêng cho thành viên (xem bài hướng dẫn User *"Sự kiện của phòng"*) và một slide trên **banner sự kiện** ở đầu các trang phòng.

## 12.2. Danh sách sự kiện sắp tới

- Tiêu đề **"Sự kiện sắp tới"** kèm **badge** cho biết số sự kiện **đang hiển thị** trên trang phòng; các dòng đang hiển thị có **nền xanh**.
- Bảng liệt kê lần diễn ra tiếp theo của từng ngày lễ và các ngày có sinh nhật trong 30 ngày tới (kèm tên thành viên), sắp xếp theo ngày.
- Cột **Thông báo** cho biết trạng thái:

| Trạng thái | Ý nghĩa |
| ---------- | ------- |
| ✅ **Đã gửi lúc …** | Thông báo đã gửi, kèm số kênh thông báo đã nhận tin |
| 🟡 **Chưa gửi** | Sự kiện đang diễn ra nhưng chưa gửi — có thể bấm **Gửi ngay** |
| 📅 **Tự động gửi lúc 09:00 …** | Sự kiện chưa đến, hệ thống sẽ tự gửi |
| 🔴 **Chưa gửi – đã qua ngày gửi** | Ngày gửi đã qua mà thông báo chưa được gửi |

## 12.3. Gửi thông báo sự kiện

- Hệ thống **tự động gửi một lần** lúc **9:00** (theo múi giờ của phòng) vào ngày sự kiện: thông báo trong ứng dụng cho mọi thành viên và tin nhắn trên các kênh thông báo của phòng, kèm link tới trang chúc mừng. Với Tết Nguyên Đán, thông báo được gửi vào mùng 1.
- Trong ngày sự kiện diễn ra, Admin có thể bấm **Gửi ngay** (nếu chưa gửi) hoặc **Gửi lại** (nếu đã gửi) bao nhiêu lần tuỳ ý; mỗi lần đều có hộp thoại xác nhận. Thông báo sinh nhật chỉ gửi được trong **đúng ngày sinh nhật**.
- Nếu lần gửi tự động bị lỗi giữa chừng, dòng sự kiện vẫn hiện **Chưa gửi** để Admin gửi bổ sung.

## 12.4. Cấu hình âm thanh

Bấm nút **🎵 Âm thanh** trên dòng sự kiện để mở hộp thoại cấu hình (rê chuột lên nút để xem mô tả; chấm xanh trên nút nghĩa là sự kiện đã được tuỳ chỉnh):

1. **Nhạc nền**:
   - **Giai điệu có sẵn** (mặc định): giai điệu cổ điển không còn bản quyền, phát trực tiếp trong trình duyệt (Auld Lang Syne, Jingle Bells, Happy Birthday, Für Elise, Canon, Ode to Joy; Tết dùng giai điệu ngũ cung riêng).
   - **Link nhạc riêng**: dán link **https** trỏ thẳng tới file nhạc **.mp3/.ogg** mà phòng có quyền sử dụng. Link trang nghe nhạc như **Zing MP3, YouTube, Spotify, NhacCuaTui** không phát được và sẽ bị từ chối.
   - **Tắt nhạc**.
2. **Hiệu ứng âm thanh**: bật/tắt các tiếng động khi thành viên mở trang (bật nắp sâm panh, mở bao lì xì, chuông…).
3. Bấm **Nghe thử** để nghe nhạc đang chọn (tự dừng sau 20 giây), rồi bấm **Lưu** — cấu hình được lưu ngay, không tải lại trang.

> Các bài hát có bản quyền (ví dụ "Happy New Year" của ABBA) không được nhúng sẵn. Nếu muốn dùng, phòng cần có file nhạc hợp lệ và đặt ở nơi cho phép tải trực tiếp.

## 12.5. Quản lý lời chúc

Bấm nút **❝ Lời chúc** trên dòng sự kiện để mở hộp thoại danh sách lời chúc (mỗi lời chúc là một slide trên trang chúc mừng, tự chuyển sau 2 giây):

- Mặc định trang dùng **lời chúc mẫu** của hệ thống, hiển thị theo ngôn ngữ của từng thành viên. Ô soạn thảo được điền sẵn các lời chúc mẫu để bạn chỉnh sửa.
- **Thêm lời chúc**, **sửa** trực tiếp trong ô, **đưa lên/đưa xuống** để đổi thứ tự, **xoá** lời chúc không dùng. Tối đa **20** lời chúc, mỗi lời tối đa **300** ký tự; dòng trống được bỏ qua và không được trùng nhau.
- Với **sinh nhật**, viết `:name` để chèn tên người sinh nhật vào lời chúc.
- Bấm **Lưu lời chúc**: từ lúc này mọi thành viên thấy đúng danh sách bạn nhập (không đổi theo ngôn ngữ).
- Bấm **Khôi phục lời chúc mẫu** để xoá danh sách riêng và quay về lời chúc mẫu.

---

[🏠 Mục lục](README.md)
