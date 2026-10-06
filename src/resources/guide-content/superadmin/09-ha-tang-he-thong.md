# 9. Hạ tầng & cài đặt hệ thống

## 9.1. Socket.IO & hàng đợi

Menu **Hạ tầng → Socket.IO & Hàng đợi**: trạng thái máy chủ thời gian thực, số kết nối, các job đang chờ / thất bại. Có thể thử lại hoặc xóa job thất bại khi được cấp quyền quản lý hàng đợi.

## 9.2. Cài đặt hệ thống

Menu **Cài đặt hệ thống** gồm:

- **Sức khỏe hệ thống**: các ô trạng thái (cơ sở dữ liệu, cache, hàng đợi, lưu trữ, máy chủ thời gian thực, lịch chạy tác vụ...) được xếp thành lưới gọn, mỗi ô chiếm khoảng nửa chiều ngang để nhìn nhanh chỗ nào đang lỗi.
- **Cài đặt chung**: các thông số vận hành của nền tảng.
- **Bảo trì**: bật chế độ bảo trì kèm thông báo; người dùng và đại lý thấy trang bảo trì cho tới khi tắt.
- **Mail**: cấu hình máy chủ gửi thư và **gửi thư thử** để kiểm tra.
- **Lưu trữ**: cấu hình nơi lưu tệp tải lên.

## 9.3. Lịch chạy tác vụ (cron)

Các tác vụ tự động (gia hạn thuê bao, sinh hóa đơn, nhắc gia hạn, đánh dấu quá hạn, tự động tạm ngưng, tính vinh danh nhà tài trợ hằng tháng...) chỉ chạy khi máy chủ gọi `php artisan schedule:run` mỗi phút qua cron. Nếu các con số trong [Tài chính nền tảng](04-tai-chinh-nen-tang.md) không tự cập nhật, hãy kiểm tra cron trước tiên.
