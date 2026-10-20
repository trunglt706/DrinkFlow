# Đề xuất phát triển DrinkFlow (sau v2.4.0)

- **Ngày lập:** 20/10/2026
- **Trạng thái:** Chờ duyệt
- **Phạm vi:** Rà soát route, service, enum, kênh thông báo và tài liệu hướng dẫn hiện có; đề xuất chức năng hoàn thiện / phát triển thêm.

> Cách duyệt: đánh dấu cột **Duyệt** ở từng bảng (`[x]` đồng ý, `[-]` từ chối, `[?]` cần bàn thêm) và ghi chú nếu có. Mục nào được duyệt sẽ được phân tích chi tiết (database, luồng, màn hình) trước khi triển khai.

---

## 1. Đánh giá hiện trạng

### Điểm mạnh

- Vòng đời một đợt đặt món đầy đủ: chiến dịch → đơn hàng → đặt hộ / món trả riêng → công nợ.
- Thanh toán qua VietQR; 5 cách chia hóa đơn (`by_order`, `sponsor_first`, `equal`, `flat_price`, `custom`); nhà tài trợ.
- Phân quyền 3 cấp (User / Admin / Superadmin) kèm nhật ký hoạt động; gói dịch vụ và hóa đơn nền tảng.
- Tạo gắn kết: vòng quay may mắn, sự kiện (sinh nhật + 6 ngày lễ), thành tích TOP nhà tài trợ.

### Khoảng trống

| # | Khoảng trống | Hiện trạng trong code |
|---|---|---|
| G1 | Thông báo tới người dùng chưa chủ động | Chỉ có thông báo trong web và kênh Slack / Telegram / webhook cho cả phòng; chưa có PWA / Web Push tới từng thiết bị. |
| G2 | Xác nhận chuyển khoản vẫn làm tay | Chưa tích hợp webhook ngân hàng (SePay, Casso, PayOS); admin đối soát từng khoản. |
| G3 | Quỹ phòng chưa có sổ quỹ | `PaymentMethod::RoomFund` mới là phương thức thanh toán, chưa có sổ thu chi / số dư. |
| G4 | Gắn kết mới dừng ở mức "xem" | Trang sự kiện, thành tích chưa có tương tác giữa các thành viên. |

---

## 2. P1 – Giữ chân người dùng và giảm việc vận hành (ưu tiên cao)

| # | Chức năng | Lợi ích | Ghi chú kỹ thuật | Công sức | Duyệt |
|---|---|---|---|---|---|
| 1 | **PWA + Web Push** (cài lên màn hình chính, thông báo trên từng thiết bị) | Người dùng đặt món chủ yếu trên điện thoại; nhận "chiến dịch sắp đóng", "đến lượt trả nợ" kể cả khi đã đóng tab → tăng tỉ lệ tham gia. | Tái sử dụng `NotificationType`, `UserNotificationService`; thêm bảng subscription theo thiết bị, cho phép bật/tắt theo loại thông báo. | M | [-] |
| 2 | **Tự đối soát chuyển khoản** qua webhook SePay / Casso / PayOS | Giảm mạnh nhất công việc thủ công của admin; người dùng được xác nhận gần như tức thì. | Đối chiếu nội dung chuyển khoản với mã đơn `ORD{room_id}-YYYYMMDD-seq`; chữ ký webhook, idempotency (unique theo mã giao dịch), log đối soát. | M | [x] |
| 3 | **Đặt lại món cũ / "Món quen của tôi"** 1 chạm | Rút thời gian đặt món còn vài giây. | Dữ liệu yêu thích đã có trong `UserRoomCampaignService`; chỉ cần map sang món của chiến dịch hiện tại. | S | [?] |
| 4 | **Nhắc nợ theo bậc** (sau 1, 3, 7 ngày) kèm VietQR trong thông báo, cho phép "tạm hoãn" | Thu nợ nhanh hơn, ít ngại hơn cho admin. | Mở rộng `DebtReminderService` + schedule; chống gửi trùng bằng unique index. | S | [x] |
| 5 | **Màn hình "Hôm nay"**: chiến dịch đang mở, khoản nợ của tôi, sự kiện / sinh nhật | Giảm số lần chuyển tab, tăng tỉ lệ quay lại mỗi ngày. | Gom dữ liệu từ dashboard service hiện có; cache ngắn. | S | [?] |

---

## 3. P2 – Tăng gắn kết cộng đồng trong phòng

| # | Chức năng | Lợi ích | Ghi chú kỹ thuật | Công sức | Duyệt |
|---|---|---|---|---|---|
| 6 | **Bình chọn quán / món** trước khi mở chiến dịch | Thành viên được tham gia quyết định; chiến dịch tạo sẵn từ lựa chọn thắng. | Kết hợp vòng quay "chọn quán" khi hòa phiếu; unique vote theo (poll, user). | M | [-] |
| 7 | **Đánh giá món sau khi nhận hàng** (sao + bình luận ngắn) | Tạo dữ liệu "món ngon nhất phòng", giúp người mới chọn món. | Unique theo (order_item, user); hiển thị điểm trung bình ở trang đặt món. | S | [x] |
| 8 | **Tường lời chúc trên trang sự kiện** (gửi lời chúc, thả tim) | Biến trang sự kiện từ "xem" thành "tham gia". | Giới hạn độ dài, rate limit, admin ẩn được nội dung không phù hợp. | M | [x] |
| 9 | **Mở rộng thành tích**: "trả nợ đúng hạn 3 tháng liền", "chuỗi 10 ngày đặt món", "khai trương quán mới"; bảng xếp hạng tuần | Thêm động lực hành vi tốt (trả nợ đúng hạn). | Dùng lại cơ chế cache + version key của `MonthlySponsorStatisticsService`. | M | [?] |
| 10 | **Tổng kết năm** (kiểu Spotify Wrapped): số ly, món yêu thích, tổng chi, số lần tài trợ, thẻ chia sẻ | Hiệu ứng lan truyền mạnh, hợp phát hành dịp Tết. | Dữ liệu thống kê đã có; tính sẵn bằng command cuối năm và cache. | M | [x] |

---

## 4. P3 – Hoàn thiện nghiệp vụ và mở rộng kinh doanh

| # | Chức năng | Lợi ích | Ghi chú kỹ thuật | Công sức | Duyệt |
|---|---|---|---|---|---|
| 11 | **Sổ quỹ phòng**: nạp quỹ, trừ quỹ khi đặt món, số dư, lịch sử, xuất Excel | Hoàn thiện `RoomFund`; minh bạch tiền chung. | Bảng ledger (append-only), số dư tính từ ledger, khóa giao dịch khi trừ quỹ. | M | [x] |
| 12 | **Ngân sách và hạn mức** (công ty tài trợ X đ/người/tháng, cảnh báo vượt) | Tính năng then chốt để bán cho doanh nghiệp (B2B). | Cấu hình theo phòng; kiểm tra khi đặt món; báo cáo sử dụng ngân sách. | L | [-] |
| 13 | **Chiến dịch định kỳ** (vd: "trà sữa thứ Sáu" 15:00 mỗi tuần) | Giảm thao tác lặp lại của admin. | Template chiến dịch + scheduler; bỏ qua ngày nghỉ tùy chọn. | M | [ ] |
| 14 | **Tích hợp Google Chat / Microsoft Teams / Zalo OA** | Nhiều công ty Việt Nam không dùng Slack. | Mở rộng từ `SendsNotificationChannelPayloads` (webhook đã có). | S–M | [-] |
| 15 | **Báo cáo cho admin**: xu hướng chi tiêu, tỉ lệ tham gia, nợ quá hạn, quán được gọi nhiều; email tổng hợp hàng tuần | Admin thấy giá trị sản phẩm → gia hạn gói. | Dựa trên `ReportController` + thống kê tháng hiện có; gửi qua queue. | M | [-] |

---

## 5. Nền tảng kỹ thuật (làm song song)

| # | Hạng mục | Lý do | Duyệt |
|---|---|---|---|
| T1 | **Múi giờ theo phòng** (đã có task riêng) | Bắt buộc trước khi phục vụ khách ở nước ngoài; lịch gửi 9:00 hiện phụ thuộc múi giờ hệ thống. | [?] |
| T2 | **Sửa `PaymentStatementExportTest`** đang fail và **đưa test lên CI** cho mỗi PR | Hiện chưa có CI chạy test tự động trong repo. | [x] |
| T3 | **Admin tải nhạc lên** cho trang sự kiện (lưu storage, giới hạn dung lượng / định dạng) | Giải quyết dứt điểm việc link Zing MP3 không phát được. | [x] |
| T4 | **Số liệu sản phẩm**: DAU/MAU, tỉ lệ tham gia chiến dịch, thời gian trung bình trả nợ | Cần để đo hiệu quả các đề xuất ở trên. | [x] |

> Công sức: **S** ≈ 1–3 ngày, **M** ≈ 3–7 ngày, **L** > 1 tuần (ước lượng sơ bộ, chốt lại sau khi phân tích chi tiết).

---

## 6. Lộ trình gợi ý

| Phiên bản | Nội dung | Mục tiêu |
|---|---|---|
| v2.5 | 1, 3, 4, 5 | Giữ chân người dùng nhanh, thấy ngay hiệu quả |
| v2.6 | 2, 11 | Tự động hóa tiền bạc |
| v2.7 | 6, 7, 8, 9 | Cộng đồng |
| Dịp Tết | 10 | Lan truyền |
| v3.0 | 12, 13, 14, 15 | Hướng tới khách doanh nghiệp (B2B) |

---

## 7. Ghi chú duyệt

- Người duyệt:
- Ngày duyệt:
- Ý kiến chung:
