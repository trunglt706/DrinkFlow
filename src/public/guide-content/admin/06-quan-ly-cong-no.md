# 6. Sổ kế toán & Công nợ kép

<details open>
<summary><strong>📑 Menu điều hướng</strong> — bấm để chuyển nhanh sang trang khác</summary>

- [🏠 Trang chủ / Mục lục](README.md)
- [1. Đăng nhập & khôi phục mật khẩu](01-dang-nhap-quan-tri.md)
- [2. Chọn phòng & Tổng quan vận hành](02-chon-phong-tong-quan.md)
- [3. Hồ sơ quản trị viên & bảo mật](03-ho-so-bao-mat-admin.md)
- [4. Quản lý Chiến dịch & Menu](04-quan-ly-chien-dich.md)
- [5. Quản lý Đơn gom & điều chỉnh giá](05-quan-ly-don-hang.md)
- **6. Sổ kế toán & Công nợ kép** ← *đang xem*
- [7. Danh bạ thành viên & thiết bị tin cậy](07-thanh-vien-room.md)
- [8. Cấu hình hệ thống & Tài khoản thanh toán](08-cau-hinh-he-thong.md)
- [9. Tích hợp Bot thông báo & Gửi thông báo](09-kenh-thong-bao-broadcast.md)
- [10. Báo cáo & Phân tích Logistics](10-bao-cao-thong-ke.md)
- [11. Nhật ký hoạt động (Audit Log)](11-nhat-ky-hoat-dong.md)
- [12. Quản lý sự kiện (sinh nhật, ngày lễ, âm thanh & lời chúc)](12-quan-ly-su-kien.md)
- [13. Vòng quay may mắn (Quay sponsor)](13-vong-quay-may-man.md)
- [14. Huy hiệu nhà tài trợ & thành tích trong năm](14-huy-hieu-thanh-tich.md)

</details>

Trang **"Sổ Kế Toán & Quản Lý Công Nợ Kép"** (sidebar mục **"Quản lý Công nợ kép"**) là nơi đối soát toàn bộ khoản nợ của thành viên (và sponsor, nếu có) trong Room.

![Sổ kế toán & công nợ](images/11-cong-no.png)

## 6.1. Bốn chỉ số tài chính

Đầu trang hiển thị: **Tổng chi tại quán**, **Đã thu từ thành viên**, **Nợ quán còn tồn** và **Nợ thành viên còn lại** — giúp bạn nắm nhanh tình hình thu chi của Room.

## 6.2. Bảng công nợ

Tìm kiếm theo tên/mã thành viên, lọc theo **thành viên cụ thể**, **khoảng ngày** hoặc **trạng thái nợ** (Chờ duyệt / Đã thanh toán / Thanh toán một phần / Chưa thanh toán). Mỗi dòng hiển thị: thành viên nợ, chiến dịch phát sinh nợ, trạng thái, **nợ gốc** và **số còn lại**.

Bấm biểu tượng 👁 để xem **Chi tiết công nợ** đầy đủ của dòng đó.

## 6.3. Xử lý từng khoản nợ

Tuỳ trạng thái, mỗi dòng có các nút thao tác khác nhau:

- **"Duyệt thanh toán"** (khi trạng thái *Chờ duyệt*) — hiện khi thành viên đã tự báo "Đã thanh toán" trên app của họ; mở hộp thoại **"Yêu cầu duyệt thanh toán"** để bạn đối chiếu rồi xác nhận, hệ thống tự gạch nợ.
- **"Thu tiền"** — dùng khi bạn nhận tiền trực tiếp (tiền mặt hoặc đã kiểm tra sao kê thủ công) mà thành viên chưa tự báo qua app; mở hộp thoại **"Thu tiền"** để ghi nhận số tiền đã thu.
- **Điều chỉnh nợ** (biểu tượng ⚙) — dùng khi cần sửa số tiền nợ (ví dụ có sai lệch cần bù trừ), mở hộp thoại **"Điều chỉnh nợ: [tên thành viên]"**.
- Nhãn **"Đã tất toán"** hiện khi khoản nợ đã về 0.

## 6.4. Xử lý hàng loạt

- **"Xuất CSV"** ở đầu trang — tải toàn bộ sổ nợ hiện tại ra file để lưu trữ/đối chiếu ngoài hệ thống.
- **"Yêu cầu duyệt thanh toán toàn bộ nợ"** — với thành viên có nhiều khoản nợ cùng lúc báo thanh toán một lần (nội dung chuyển khoản trùng mã thành viên), hệ thống gộp lại để bạn duyệt một lần cho tất cả các khoản.
- Chức năng **nhắc nợ** gửi thông báo nhắc thanh toán tới các thành viên còn nợ (qua kênh đã kết nối ở [bài 9](09-kenh-thong-bao-broadcast.md)), và **tất toán hàng loạt** cho phép đóng nhiều khoản nợ nhỏ cùng lúc khi cần chốt sổ cuối kỳ.

> Khoản nợ có thể thuộc về **thành viên thường** (chưa trả tiền đơn hàng) hoặc **Sponsor** (chưa hoàn tất nghĩa vụ tài trợ đã cam kết) — cả hai đều xuất hiện chung trong sổ kế toán này, đúng như tên gọi "Công nợ kép".
