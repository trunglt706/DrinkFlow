# 12. Đặt món giúp thành viên khác (Order dùm)

<details open>
<summary><strong>📑 Menu điều hướng</strong> — bấm để chuyển nhanh sang trang khác</summary>

- [🏠 Trang chủ / Mục lục](README.md)
- [1. Đăng nhập & Cổng thông tin cá nhân](01-dang-nhap-va-cong-thong-tin.md)
- [2. Tham gia & quản lý Room](02-tham-gia-va-quan-ly-room.md)
- [3. Đặt món trong chiến dịch gom đơn](03-dat-mon-chien-dich.md)
- [4. Theo dõi đơn hàng](04-theo-doi-don-hang.md)
- [5. Thanh toán & Công nợ](05-thanh-toan-cong-no.md)
- [6. Thống kê chi tiêu](06-thong-ke-chi-tieu.md)
- [7. Hồ sơ cá nhân](07-ho-so-ca-nhan.md)
- [8. Bảo mật & thiết bị đăng nhập](08-bao-mat-thiet-bi.md)
- [9. Thông báo](09-thong-bao.md)
- [10. Đánh giá & góp ý](10-danh-gia-gop-y.md)
- [11. Khi tài khoản bị khoá / hạn chế](11-tai-khoan-bi-khoa.md)
- **12. Đặt món giúp thành viên khác (Order dùm)** ← *đang xem*
- [13. Món trả riêng (không dùng tài trợ)](13-mon-tra-rieng.md)
- [14. Sự kiện của phòng (sinh nhật & ngày lễ)](14-su-kien-cua-phong.md)
- [15. Vòng quay may mắn (quay nhà tài trợ / quay chọn quán)](15-vong-quay-may-man.md)
- [16. Thành tích trong năm (huy chương TOP nhà tài trợ)](16-thanh-tich.md)

</details>

**Order dùm (đặt hộ)** là tính năng cho phép bạn thêm một món vào giỏ hàng chung của chiến dịch nhưng gán món đó cho **một thành viên khác trong cùng Room** — ví dụ đồng nghiệp đang họp, đang bận tay, hoặc nhờ bạn đặt giúp qua chat. Số tiền của món đặt hộ sẽ được tính vào công nợ của **người nhận**, không phải của bạn.

## 12.1. Điều kiện để đặt hộ

Trước khi gán món cho người khác, hãy lưu ý:

- Bạn cần có **ít nhất một món cho chính mình** trong giỏ hàng trước khi được phép đặt hộ người khác. Nếu giỏ hàng chưa có món nào của riêng bạn, hệ thống báo **"Bạn cần đặt ít nhất một món cho chính mình trước khi đặt giúp người khác."**
- Không thể dùng chức năng này để **"đặt hộ chính mình"** — hệ thống sẽ từ chối với thông báo **"Bạn không thể dùng chức năng này để đặt món cho chính mình."**
- Món đã đánh dấu **"Trả riêng"** (xem [bài 13](13-mon-tra-rieng.md)) **không thể** đặt hộ cho người khác — nút gán người nhận sẽ tự động ẩn đi với món này, vì món trả riêng luôn thuộc về đúng người đã thêm nó vào giỏ.
- Người nhận phải là **thành viên đang hoạt động** trong cùng Room. Nếu không tìm thấy, hệ thống báo **"Không tìm thấy thành viên đang hoạt động với thông tin :code."**
- Nếu Room có giới hạn hạn mức công nợ tối đa cho mỗi thành viên và người nhận đã gần chạm mức đó, hệ thống sẽ từ chối gán món với thông báo **"Đã vượt quá hạn mức nợ (:limit)."** — trong trường hợp này bạn không thể đặt hộ thêm cho người đó cho tới khi họ thanh toán bớt công nợ.

## 12.2. Cách đặt món giúp người khác

1. Thêm món vào giỏ hàng như bình thường (xem [bài 3, mục 3.2](03-dat-mon-chien-dich.md#32-chọn-món)).
2. Mở **"Giỏ hàng"**, tìm đến món muốn đặt hộ, bấm biểu tượng **bút chì (Chọn người nhận)** cạnh món đó.
3. Hộp thoại **"Đặt món giúp thành viên khác"** hiện ra. Nhập **mã thành viên, email hoặc số điện thoại** của người nhận vào ô tìm kiếm rồi bấm **"Tra cứu"**.
4. Nếu tìm thấy, hệ thống hiển thị **tên và email** của người đó trong khung xác nhận màu xanh. Kiểm tra đúng người rồi bấm **"Lưu người nhận"**.
5. Món trong giỏ hàng giờ hiển thị nhãn **"Đặt giúp: [tên người nhận]"** thay cho tên bạn.

> 💡 Mẹo: Trong khung giỏ hàng luôn có gợi ý **"Bạn có thể order dùm người khác bằng cách nhấn nút chỉnh sửa và nhập mã user hợp lệ."** để nhắc lại thao tác này.

Muốn đổi người nhận hoặc gán lại về cho chính mình, bấm lại biểu tượng bút chì và lặp lại các bước trên, hoặc bỏ trống ô tra cứu rồi lưu để trả món về đơn của bạn.

## 12.3. Kiểm tra lại trước khi gửi đơn

Khi bấm **"Xác nhận đơn"**, hộp thoại xác nhận sẽ hiển thị 3 số liệu tổng hợp để bạn rà soát trước khi gửi:

- **Tự đặt** — số món tính vào đơn của chính bạn.
- **Đặt dùm** — số món đã gán cho người khác.
- **Trả riêng** — số món đánh dấu trả riêng (xem [bài 13](13-mon-tra-rieng.md)).

Kiểm tra đúng số lượng ở từng mục rồi mới bấm **"Gửi đơn"**.

## 12.4. Xem lại các món đã đặt hộ

Sau khi gửi đơn thành công, vào tab **"Đơn hàng của tôi"** trong Room (xem [bài 4](04-theo-doi-don-hang.md)) — đơn của bạn sẽ liệt kê riêng một khối **"Món đã order dùm"**, kèm tên từng người nhận để bạn dễ đối chiếu khi thu tiền hoặc nhắc thanh toán.

---

⬅️ [Trang trước: 11. Khi tài khoản bị khoá / hạn chế](11-tai-khoan-bi-khoa.md) &nbsp;|&nbsp; [🏠 Mục lục](README.md) &nbsp;|&nbsp; [Trang sau: 13. Món trả riêng (không dùng tài trợ) ➡️](13-mon-tra-rieng.md)
