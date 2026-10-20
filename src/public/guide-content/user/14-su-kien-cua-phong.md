# 14. Sự kiện của phòng (sinh nhật & ngày lễ)

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
- [13. Món trả riêng (không dùng tài trợ)](13-mon-tra-rieng.md)
- **14. Sự kiện của phòng (sinh nhật & ngày lễ)** ← *đang xem*
- [15. Vòng quay may mắn (quay nhà tài trợ / quay chọn quán)](15-vong-quay-may-man.md)
- [16. Thành tích trong năm (huy chương TOP nhà tài trợ)](16-thanh-tich.md)

</details>

DrinkFlow tự nhắc cả phòng về các dịp đặc biệt: **sinh nhật thành viên** và **các ngày lễ trong năm**. Mỗi sự kiện có một **trang chúc mừng riêng** với hiệu ứng, âm thanh và các lời chúc, kèm thông báo gửi tới cả phòng.

## 14.1. Các sự kiện và thời gian hiển thị

| Sự kiện | Ngày | Thời gian hiển thị | Trang chúc mừng |
| ------- | ---- | ------------------ | --------------- |
| 🎂 Sinh nhật thành viên | Ngày sinh của thành viên | Trước và sau ngày sinh **3 ngày** | `/rooms/{room}/birthday` |
| 🎆 Tết Dương lịch | 01/01 | Đúng ngày | `/rooms/{room}/1-1` |
| 🧧 Tết Nguyên Đán | Mùng 1 Tết (âm lịch) | Trước và sau **7 ngày** | `/rooms/{room}/tet` |
| 🌷 Quốc tế Phụ nữ | 08/03 | Đúng ngày | `/rooms/{room}/8-3` |
| 🌹 Phụ nữ Việt Nam | 20/10 | Đúng ngày | `/rooms/{room}/20-10` |
| 🏆 Quốc tế Đàn ông | 19/11 | Đúng ngày | `/rooms/{room}/19-11` |
| 🎄 Giáng sinh | 25/12 | Đúng ngày | `/rooms/{room}/25-12` |

Ngày Tết Nguyên Đán được hệ thống tự tính theo lịch âm Việt Nam, bạn không cần làm gì. Ngoài khoảng thời gian hiển thị, trang chúc mừng của sự kiện sẽ không mở được.

## 14.2. Banner sự kiện trên đầu trang phòng

Khi có sự kiện đang diễn ra, phía trên mọi trang trong Room xuất hiện một **banner chạy ngang**:

- Mỗi sự kiện là một slide với màu sắc riêng; có nhiều sự kiện thì banner **tự chuyển slide**, kèm nút **‹ ›** và các chấm chọn. Trên điện thoại bạn có thể **vuốt** để chuyển.
- Slide sinh nhật hiển thị dòng chữ chạy tên những người có sinh nhật trong khoảng ±3 ngày (ví dụ "Hôm nay là sinh nhật của …", "Còn 2 ngày nữa là sinh nhật …").
- Trong dịp Tết, banner đếm ngược "Còn N ngày nữa là Tết" và sau đó hiển thị "Mùng N Tết".
- Bấm vào banner hoặc nút **Xem ngay** để mở trang chúc mừng.

## 14.3. Trang chúc mừng

Mỗi ngày lễ có một hình ảnh trung tâm riêng: nụ hồng (20/10), hộp quà hoa tulip (8/3), chai sâm panh (1/1), bao lì xì (Tết), cây thông Noel (25/12), hộp quà chiếc cúp (19/11).

1. Lúc mới mở trang, bạn chỉ thấy hình trung tâm và dòng chữ gợi ý (ví dụ **"Click vào đây để nhìn thấy điều mới lạ"**).
2. **Bấm vào hình** (hoặc dòng chữ) để mở: hoa nở, hộp quà bật nắp, sâm panh bật nút, bao lì xì mở ra… kèm hiệu ứng cánh hoa, pháo hoa, tuyết rơi tuỳ dịp.
3. Tiêu đề và các **lời chúc** xuất hiện, mỗi lời chúc là một slide, **tự chuyển sau 2 giây**. Bạn có thể:
   - bấm **‹ ›** hoặc các chấm để chuyển lời chúc;
   - bấm **❚❚** để tạm dừng, **▶** để tiếp tục (rê chuột lên khung lời chúc cũng tạm dừng);
4. Bấm nút ở cuối trang (ví dụ **"Ngắm lại nụ hồng"**) để xem lại hiệu ứng từ đầu, hoặc **← Quay lại phòng**.

Lời chúc có thể là lời chúc mẫu của hệ thống (theo ngôn ngữ bạn đang dùng) hoặc danh sách riêng do Admin của phòng soạn.

### Trang sinh nhật

Trang sinh nhật hiển thị ảnh đại diện và tên những người đang được chúc mừng. Rê chuột (hoặc chạm) vào ảnh để xem thẻ thông tin; **chạm vào chiếc bánh để thổi nến** và nhận lời chúc. Trang chỉ hiển thị **ngày và tháng** sinh, không bao giờ hiển thị năm sinh.

## 14.4. Âm thanh và nhạc nền

- Khi bạn bấm mở trang, hệ thống phát **hiệu ứng âm thanh** phù hợp (tiếng bật nắp sâm panh, mở bao lì xì, chuông Noel, thổi nến…) và **nhạc nền** của sự kiện. Trình duyệt không cho phép phát âm thanh trước khi bạn bấm, nên nhạc chỉ bắt đầu từ lúc này.
- Nút **🔊 / 🔇** ở góc trên bên phải dùng để tắt/bật tiếng; lựa chọn được ghi nhớ trên trình duyệt của bạn.
- Nhạc nền mặc định là các giai điệu cổ điển không còn bản quyền (ví dụ *Auld Lang Syne* cho năm mới, *Jingle Bells* cho Noel, *Happy Birthday* cho sinh nhật). Admin có thể đổi sang bài hát riêng hoặc tắt nhạc.

## 14.5. Thông báo sự kiện

Vào **9:00 sáng** của ngày sự kiện (sinh nhật hoặc ngày lễ; Tết Nguyên Đán là mùng 1), mọi thành viên trong phòng nhận một **thông báo** kèm link tới trang chúc mừng, đồng thời tin nhắn được gửi lên các kênh thông báo của phòng (Slack, Telegram…). Admin cũng có thể gửi lại thông báo trong ngày diễn ra sự kiện.

## 14.6. Cập nhật ngày sinh của bạn

Để cả phòng chúc mừng đúng ngày sinh nhật của bạn, hãy nhập **ngày sinh** trong hồ sơ cá nhân:

1. Nếu bạn chưa có ngày sinh, cuối màn hình sẽ hiện banner **"Nhập ngày sinh để cả phòng cùng chúc mừng bạn…"**. Bấm **Cập nhật ngay** để tới thẳng ô ngày sinh trong trang **Hồ sơ cá nhân** (`/me/profile`), hoặc mở trang này như hướng dẫn ở [bài 7](07-ho-so-ca-nhan.md).
2. Chọn ngày sinh và bấm **Lưu thông tin liên hệ**.
3. Bấm **✕** trên banner nếu muốn ẩn lời nhắc trong **1 ngày**.

Thành viên khác chỉ thấy **ngày và tháng** sinh của bạn để gửi lời chúc, không thấy năm sinh.

---

⬅️ [Trang trước: 13. Món trả riêng (không dùng tài trợ)](13-mon-tra-rieng.md) &nbsp;|&nbsp; [🏠 Mục lục](README.md) &nbsp;|&nbsp; [Trang sau: 15. Vòng quay may mắn ➡️](15-vong-quay-may-man.md)
