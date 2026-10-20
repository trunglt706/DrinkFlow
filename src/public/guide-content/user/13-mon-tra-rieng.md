# 13. Món trả riêng (không dùng tài trợ)

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
- [12. Đặt món giúp thành viên khác (Order dùm)](12-dat-ho-thanh-vien-khac.md)
- **13. Món trả riêng (không dùng tài trợ)** ← *đang xem*
- [14. Sự kiện của phòng (sinh nhật & ngày lễ)](14-su-kien-cua-phong.md)
- [15. Vòng quay may mắn (quay nhà tài trợ / quay chọn quán)](15-vong-quay-may-man.md)
- [16. Thành tích trong năm (huy chương TOP nhà tài trợ)](16-thanh-tich.md)

</details>

**"Trả riêng"** là tuỳ chọn cho phép bạn đặt một món **không dùng đến chính sách tài trợ** của chiến dịch (dù Room đang tài trợ toàn bộ hay tài trợ theo sản phẩm/ngân sách). Toàn bộ chi phí món đó sẽ ghi thẳng vào công nợ cá nhân của bạn, tách biệt với phần được công ty/Room hỗ trợ. Tính năng này phù hợp khi bạn muốn gọi thêm một món ngoài định mức được tài trợ (ví dụ order thêm 1 ly cho người thân, món giá cao hơn mức trợ giá cho phép...) mà không ảnh hưởng đến quỹ chung của Room.

## 13.1. Đánh dấu một món là "trả riêng"

1. Bấm chọn món muốn đặt để mở hộp thoại **"Tùy chỉnh món"** (xem [bài 3, mục 3.2](03-dat-mon-chien-dich.md#32-chọn-món)).
2. Sau khi chọn size/topping/ghi chú, tick vào ô **"Trả riêng (không dùng tài trợ)"** ở cuối hộp thoại.
3. Hệ thống hiển thị chú thích nhắc lại: **"Món này sẽ không được sponsor hỗ trợ, số tiền sẽ được ghi thẳng vào công nợ của bạn."**
4. Bấm **"Thêm vào đơn"** như bình thường.

Lưu chọn này áp dụng **riêng cho từng món** — bạn có thể vừa có món dùng tài trợ bình thường, vừa có món đánh dấu trả riêng trong cùng một giỏ hàng.

## 13.2. Ảnh hưởng đến công nợ

- Món trả riêng **không được cộng vào phần tài trợ** của Room/công ty, dù chiến dịch đang áp dụng chính sách tài trợ toàn bộ hay theo sản phẩm.
- Số tiền món trả riêng được **cộng thẳng vào công nợ cá nhân** của bạn, tách biệt với phần đơn hàng dùng chung.
- Cách tính chính xác số tiền ghi nợ (có cộng thêm phần chia sẻ phí giao hàng và giảm giá chung của cả chiến dịch hay không) do **Host/Admin cấu hình riêng cho từng chiến dịch**. Nếu không chắc món trả riêng của mình được tính theo cách nào, hãy hỏi trực tiếp Host/Admin quản lý Room.

## 13.3. Nhận diện món trả riêng

Món đã đánh dấu trả riêng luôn đi kèm nhãn màu vàng **"Trả riêng"** (biểu tượng 💳) ở các vị trí sau để bạn dễ theo dõi:

- Trong khung **giỏ hàng** khi đang chọn món.
- Trong hộp thoại **"Xác nhận đặt món"** trước khi gửi đơn — mục thống kê riêng **"Trả riêng"** cho biết số món đã đánh dấu.
- Trong **"Đơn hàng của tôi"** (xem [bài 4](04-theo-doi-don-hang.md)) và **lịch sử đơn hàng toàn hệ thống** (`/me/orders`).

## 13.4. Lưu ý khi kết hợp với đặt hộ

Một món **không thể vừa đánh dấu "Trả riêng" vừa đặt hộ cho người khác** — nút **"Chọn người nhận"** sẽ tự ẩn khi món đã tick trả riêng, vì món trả riêng luôn thuộc về đúng người đã thêm nó vào giỏ hàng. Nếu bạn cần vừa đặt hộ đồng nghiệp vừa muốn họ tự chịu chi phí không qua tài trợ, hãy nhờ chính đồng nghiệp đó đăng nhập và tự đánh dấu trả riêng cho món của họ, hoặc trao đổi trực tiếp để họ hoàn lại chi phí sau (xem thêm [bài 12: Đặt món giúp thành viên khác](12-dat-ho-thanh-vien-khac.md)).

---

⬅️ [Trang trước: 12. Đặt món giúp thành viên khác (Order dùm)](12-dat-ho-thanh-vien-khac.md) &nbsp;|&nbsp; [🏠 Mục lục](README.md) &nbsp;|&nbsp; [Trang sau: 14. Sự kiện của phòng (sinh nhật & ngày lễ) ➡️](14-su-kien-cua-phong.md)
