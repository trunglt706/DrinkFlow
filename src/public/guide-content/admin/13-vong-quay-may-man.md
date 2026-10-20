# 13. Vòng quay may mắn (Quay sponsor)

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
- [12. Quản lý sự kiện (sinh nhật, ngày lễ, âm thanh & lời chúc)](12-quan-ly-su-kien.md)
- **13. Vòng quay may mắn (Quay sponsor)** ← *đang xem*
- [14. Huy hiệu nhà tài trợ & thành tích trong năm](14-huy-hieu-thanh-tich.md)

</details>

Trang **Quay sponsor** (menu bên trái, biểu tượng 🎲 — `/admin/{room}/sponsor-draws`) dùng để quay ngẫu nhiên **nhà tài trợ** trong các thành viên của phòng, hoặc **quay chọn quán**. Kết quả do **máy chủ chọn**, mỗi ứng viên trúng tối đa một lần; thành viên theo dõi trực tiếp qua một link có thời hạn.

## 13.1. Tạo và cài đặt phiên quay

1. Bấm **Tạo phiên mới** — phiên được tạo ở trạng thái **Nháp** và mở hộp thoại **Cài đặt phiên quay**.
2. Chọn **Loại phiên quay**:
   - **Quay nhà tài trợ**: toàn bộ thành viên đang hoạt động của phòng được đưa vào vòng quay. Nhập **Số nhà tài trợ cần chọn** và (không bắt buộc) **Chiến dịch nhận tài trợ**.
   - **Quay chọn quán**: chọn từ 2 quán trở lên trong danh sách quán của các chiến dịch trước, hoặc nhập thêm quán mới (mỗi dòng một quán); hệ thống chọn ngẫu nhiên 1 quán.
3. Bấm **Lưu cài đặt**: danh sách hợp lệ được **chốt** và **link truy cập** cho thành viên được phát hành.

> Danh sách chỉ đổi được **trước lượt quay đầu tiên**. Lưu lại cài đặt sẽ chốt lại danh sách và tạo seed mới; mọi thay đổi đều ghi vào biên bản kiểm chứng.

## 13.2. Chia sẻ link cho thành viên

- Bấm **Sao chép** ở khung **Link truy cập cho thành viên** và gửi link vào kênh chat của phòng.
- Link có hạn dùng (**Còn hạn đến …**). Khi link hết hạn, không ai (kể cả Admin) quay tiếp được; kết quả đã quay vẫn được lưu. Bấm **Phát hành link mới** để tiếp tục — link cũ sẽ không dùng được nữa.

## 13.3. Quay

1. Bấm **Quay lượt tiếp theo**: máy chủ chọn ngẫu nhiên một người/quán trong danh sách còn lại và mọi người đang mở link thấy kết quả cùng lúc.
2. Lặp lại đến khi đủ số lượng cần chọn.
3. Trước lượt quay đầu tiên, Admin có thể xoá bớt ứng viên khỏi danh sách hoặc **Đồng bộ danh sách thành viên hợp lệ** (phiên quay nhà tài trợ).
4. Nếu cần quay lại từ đầu khi kết quả chưa xác nhận, bấm **Reset** (xoá toàn bộ lượt đã quay).

## 13.4. Xác nhận và thông báo

- Khi quay đủ, kiểm tra danh sách người trúng rồi bấm **Xác nhận và gửi thông báo**. Kết quả được gửi lên các kênh thông báo của phòng; sau khi xác nhận **không thể huỷ phiên**.
- Nếu đã chọn **Chiến dịch nhận tài trợ**, hệ thống đặt chiến dịch đó thành **tài trợ toàn phần** và chia đều tỷ lệ cho những người trúng.
- Bảng trạng thái gửi cho từng kênh (**Đang gửi / Thành công / Thất bại**); bấm **Gửi lại các kênh lỗi** khi có kênh gửi thất bại.

## 13.5. Huỷ phiên và biên bản kiểm chứng

- **Huỷ phiên** (nhập lý do) khi cần làm lại, ví dụ chọn nhầm chiến dịch hay sai số lượng. Kết quả đã quay vẫn được lưu.
- **Tải biên bản kiểm chứng** để lưu trữ/đối chiếu: biên bản ghi lại danh sách đã chốt, seed (commit-reveal) và từng lượt quay, giúp chứng minh kết quả không bị can thiệp.

---

[🏠 Mục lục](README.md)
