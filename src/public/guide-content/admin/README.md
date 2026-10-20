# Hướng dẫn sử dụng DrinkFlow dành cho Admin

Bộ tài liệu này dành cho **Admin** — quản trị viên phụ trách vận hành một hoặc nhiều **Room** (phòng ban) được phân quyền trên DrinkFlow: tạo và điều phối các chiến dịch gom đơn, theo dõi đơn hàng theo thời gian thực, đối soát công nợ, quản lý thành viên phòng và cấu hình phòng.

> Admin **khác** với User (thành viên đặt món thông thường tại `/rooms/{room}`) và **khác** với Superadmin (quản trị toàn hệ thống). Admin chỉ thao tác được trong phạm vi các Room mình được phân quyền, không quản lý được Global User, tài khoản Admin khác, hay cấu hình toàn hệ thống.

## Trước khi bắt đầu

- Bạn cần một **tài khoản Admin** (email công vụ + mật khẩu) do Superadmin hoặc IT cấp, đã được gán quyền quản lý ít nhất 1 Room.
- Cổng quản trị nằm ở đường dẫn riêng `/admin` (khác với `/me` và `/rooms/{room}` của User) — không đăng nhập bằng Google Workspace như User mà đăng nhập bằng **email + mật khẩu**, có thể kèm xác thực hai lớp (2FA OTP hoặc Google Workspace).

## Mục lục

| # | Bài viết | Nội dung chính |
| - | -------- | -------------- |
| 1 | [Đăng nhập & khôi phục mật khẩu](01-dang-nhap-quan-tri.md) | Đăng nhập, quên mật khẩu, xác thực OTP, đặt lại mật khẩu |
| 2 | [Chọn phòng & Tổng quan vận hành](02-chon-phong-tong-quan.md) | Danh sách Room được phân quyền, Dashboard vận hành theo thời gian thực |
| 3 | [Hồ sơ quản trị viên & bảo mật](03-ho-so-bao-mat-admin.md) | Cập nhật hồ sơ, đổi mật khẩu, bật/tắt xác thực hai lớp (2FA) |
| 4 | [Quản lý Chiến dịch & Menu](04-quan-ly-chien-dich.md) | Tạo nhanh chiến dịch, nguồn menu (tái sử dụng/AI/crawler), tài trợ, vòng đời chiến dịch |
| 5 | [Quản lý Đơn gom & điều chỉnh giá](05-quan-ly-don-hang.md) | Danh sách đơn toàn Room, đổi trạng thái, huỷ/khoá, điều chỉnh giá thực tế |
| 6 | [Sổ kế toán & Công nợ kép](06-quan-ly-cong-no.md) | Công nợ thành viên & sponsor, thu tiền, duyệt thanh toán, nhắc nợ, xuất báo cáo |
| 7 | [Danh bạ thành viên & thiết bị tin cậy](07-thanh-vien-room.md) | Thêm/khoá/gỡ thành viên, thao tác hàng loạt, thu hồi thiết bị đăng nhập |
| 8 | [Cấu hình hệ thống & Tài khoản thanh toán](08-cau-hinh-he-thong.md) | Truy cập phòng, mặc định chiến dịch, chính sách công nợ, tài khoản VietQR |
| 9 | [Tích hợp Bot thông báo & Gửi thông báo](09-kenh-thong-bao-broadcast.md) | Kết nối Slack/Telegram/Webhook, gửi thông báo hàng loạt tới thành viên |
| 10 | [Báo cáo & Phân tích Logistics](10-bao-cao-thong-ke.md) | Tỉ lệ tham gia, thống kê công nợ, xuất báo cáo |
| 11 | [Nhật ký hoạt động (Audit Log)](11-nhat-ky-hoat-dong.md) | Lịch sử toàn bộ thao tác quan trọng của Admin trong Room |
| 12 | [Quản lý sự kiện](12-quan-ly-su-kien.md) | Sự kiện sắp tới, trạng thái & gửi lại thông báo, cấu hình âm thanh và lời chúc |
| 13 | [Vòng quay may mắn (Quay sponsor)](13-vong-quay-may-man.md) | Tạo phiên, phát hành link, quay, xác nhận & thông báo, biên bản kiểm chứng |
| 14 | [Huy hiệu nhà tài trợ & thành tích trong năm](14-huy-hieu-thanh-tich.md) | Cách tính huy hiệu, thống kê lại, báo cáo theo năm, trang Thành tích của thành viên |

## Ghi chú về hình ảnh minh họa

Ảnh minh họa trong bộ tài liệu này được dựng từ các **bản thiết kế mẫu (mockup)** có sẵn của hệ thống (`documents/pages/admin/`), vì việc chụp ảnh thật đòi hỏi tài khoản Admin thật với mật khẩu — tài liệu này không có quyền truy cập đó và không tạo/đoán thông tin đăng nhập thay bạn. Bố cục, nhãn nút và nội dung trong ảnh mockup đã được đối chiếu khớp với mã nguồn Blade và file ngôn ngữ (`lang/vi/admin.php`) hiện tại của dự án.
