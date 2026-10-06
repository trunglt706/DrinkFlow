# 1. Đăng nhập & giao diện quản trị

Trang Superadmin nằm ở `/superadmin` và dùng **tài khoản riêng** (bảng `superadmins`), tách biệt hoàn toàn với tài khoản Đại lý (`/admin`) và Thành viên (`/me`). Phiên đăng nhập Đại lý không bao giờ mở được trang Superadmin.

## 1.1. Đăng nhập

- Mở `/superadmin/login`, nhập **email** và **mật khẩu** do chủ nền tảng cấp.
- Số lần đăng nhập sai bị giới hạn để chống dò mật khẩu; khi bị khóa tạm, chờ một phút rồi thử lại.
- Tài khoản bị **tạm ngưng** không đăng nhập được. Liên hệ người có quyền *Quản lý superadmin* để mở lại.

## 1.2. Thanh công cụ phía trên

| Thành phần | Công dụng |
| ---------- | --------- |
| Nút thu gọn menu | Thu gọn / mở rộng thanh menu bên trái (lưu theo trình duyệt). |
| Đường dẫn (breadcrumb) | Cho biết bạn đang ở nhóm menu và trang nào; bấm để quay lại trang cha. |
| **Tìm nhanh** (`Ctrl K` / `⌘ K`) | Gõ tên trang để nhảy tới ngay; chỉ liệt kê các trang tài khoản bạn được phép mở. |
| Giao diện | Chọn Sáng, Tối hoặc Theo hệ thống. |
| Ngôn ngữ | Tiếng Việt, English, 日本語. |
| **Hướng dẫn sử dụng** (biểu tượng sách) | Mở bộ tài liệu này. |
| Chuông thông báo | Thông báo dành riêng cho tài khoản của bạn (đăng ký mới, đại lý bị tạm ngưng do quá hạn...). |
| Tài khoản | Xem email đang đăng nhập và **Đăng xuất**. |

Ở góc dưới bên phải, nút **Lên đầu trang** xuất hiện khi bạn cuộn xuống một trang dài.

## 1.3. Menu theo quyền

Menu bên trái chỉ hiển thị những mục mà tài khoản của bạn có quyền xem. Mỗi quyền có **phạm vi**: *Tất cả đại lý* hoặc *Đại lý được phân công* — xem [Superadmin, vai trò & phân quyền](07-phan-quyen.md).

## 1.4. Hộp thoại xác nhận

Mọi thao tác thay đổi dữ liệu quan trọng (xóa, tạm ngưng, bật/tắt phòng, ghi nhận thanh toán...) đều mở **hộp thoại xác nhận** mô tả hậu quả trước khi thực hiện. Thao tác **không thể hoàn tác** được ghi chú rõ trong hộp thoại.
