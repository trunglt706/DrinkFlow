# Hướng dẫn sử dụng DrinkFlow dành cho User

Chào mừng bạn đến với **DrinkFlow** — nền tảng gom đơn đồ uống/đồ ăn nội bộ dành cho công ty. Bộ tài liệu này viết cho **User** (thành viên thông thường trong một Room/phòng ban), giúp bạn tự thao tác được ngay từ lần đầu tiên: đăng nhập, tham gia phòng, đặt món cùng đồng nghiệp, theo dõi đơn hàng, thanh toán và quản lý hồ sơ cá nhân.

> Tài liệu này **không** dành cho Host/Admin quản trị Room hay Superadmin hệ thống — nếu bạn cần tạo Room, mở chiến dịch gom đơn, hoặc quản lý người dùng, hãy tham khảo tài liệu dành cho Admin.

## Trước khi bắt đầu

- Bạn cần một **tài khoản email công ty** (Google Workspace) đã được quản trị viên cấp phép sử dụng DrinkFlow.
- Bạn cần một **đường link Room** do Host/Trưởng phòng gửi (qua Slack, Google Chat, email...) để tham gia phòng đặt món của nhóm mình.
- DrinkFlow hoạt động tốt nhất trên trình duyệt máy tính hoặc điện thoại đã đăng nhập Google.

## Mục lục

| # | Bài viết | Nội dung chính |
| - | -------- | -------------- |
| 1 | [Đăng nhập & Cổng thông tin cá nhân](01-dang-nhap-va-cong-thong-tin.md) | Đăng nhập bằng Google Workspace, làm quen giao diện `/me`, đăng xuất |
| 2 | [Tham gia & quản lý Room](02-tham-gia-va-quan-ly-room.md) | Tham gia phòng qua link, xem danh sách Room, chuyển đổi giữa các Room |
| 3 | [Đặt món trong chiến dịch gom đơn](03-dat-mon-chien-dich.md) | Xem menu, tùy chọn size/topping, giỏ hàng, đặt giúp đồng nghiệp, gửi đơn |
| 4 | [Theo dõi đơn hàng](04-theo-doi-don-hang.md) | Trạng thái đơn theo thời gian thực, chi tiết đơn, lịch sử đơn toàn hệ thống |
| 5 | [Thanh toán & Công nợ](05-thanh-toan-cong-no.md) | Thanh toán VietQR trong Room, Ví & Thanh toán tổng hợp, xuất sao kê |
| 6 | [Thống kê chi tiêu](06-thong-ke-chi-tieu.md) | Thống kê theo từng Room và thống kê tổng hợp toàn hệ thống |
| 7 | [Hồ sơ cá nhân](07-ho-so-ca-nhan.md) | Cập nhật thông tin liên hệ, tài khoản nhận hoàn tiền, hồ sơ trong từng Room |
| 8 | [Bảo mật & thiết bị đăng nhập](08-bao-mat-thiet-bi.md) | Quản lý phiên đăng nhập, đăng xuất thiết bị lạ, xoá tài khoản |
| 9 | [Thông báo](09-thong-bao.md) | Chuông thông báo, trung tâm thông báo, cấu hình kênh nhận tin |
| 10 | [Đánh giá & góp ý](10-danh-gia-gop-y.md) | Gửi đánh giá trải nghiệm, xem thống kê hài lòng chung |
| 11 | [Khi tài khoản bị khoá / hạn chế](11-tai-khoan-bi-khoa.md) | Phân biệt khoá toàn hệ thống và hạn chế theo Room, cách gửi khiếu nại |
| 12 | [Đặt món giúp thành viên khác (Order dùm)](12-dat-ho-thanh-vien-khac.md) | Điều kiện đặt hộ, cách gán món cho người nhận, giới hạn hạn mức nợ |
| 13 | [Món trả riêng (không dùng tài trợ)](13-mon-tra-rieng.md) | Đánh dấu món không dùng tài trợ, ảnh hưởng đến công nợ cá nhân |
| 14 | [Sự kiện của phòng (sinh nhật & ngày lễ)](14-su-kien-cua-phong.md) | Banner sự kiện, trang chúc mừng, âm thanh, thông báo 9:00, cập nhật ngày sinh |
| 15 | [Vòng quay may mắn](15-vong-quay-may-man.md) | Theo dõi trực tiếp phiên quay nhà tài trợ / chọn quán, tính minh bạch của kết quả |
| 16 | [Thành tích trong năm](16-thanh-tich.md) | Huy chương TOP nhà tài trợ theo từng tháng, tổng huy chương, lời nhắn động viên |

## Ghi chú về hình ảnh minh họa

Ảnh trong các bài `02`–`17`, `06`(room)... là **ảnh chụp thật** từ giao diện DrinkFlow đang chạy. Riêng ảnh trang đăng nhập, trang mời tham gia Room và 2 trang tài khoản bị khoá là **ảnh dựng từ bản thiết kế mẫu (mockup)** của hệ thống vì các trạng thái này khó tái hiện an toàn trên dữ liệu thật (cần một Room mới hoàn toàn hoặc một tài khoản đang bị khoá thật sự). Bố cục, nhãn nút và nội dung trong các ảnh mockup khớp với giao diện thật.
