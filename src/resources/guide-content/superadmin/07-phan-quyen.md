# 7. Superadmin, vai trò & phân quyền

## 7.1. Tài khoản Superadmin

Menu **Quản trị → Superadmin & phân quyền**: tạo tài khoản, đổi trạng thái, đặt lại mật khẩu, xóa tài khoản (không xóa được chính mình).

Trang chi tiết một Superadmin gồm, từ trên xuống:

1. **Thông tin tài khoản**: tên, email, trạng thái, mật khẩu mới (để trống nếu không đổi).
2. **Phân quyền** (toàn chiều ngang trang): ma trận quyền theo từng nhóm.

## 7.2. Ma trận quyền

- Mỗi nhóm (Đại lý, Gói dịch vụ, Tài chính, Hệ thống...) có **ô chọn ở tiêu đề nhóm** để chọn/bỏ chọn tất cả quyền trong nhóm. Ô này hiển thị trạng thái *chọn một phần* khi nhóm chỉ được cấp một số quyền, kèm số đếm `đã chọn/tổng`.
- Nút **Chọn tất cả quyền** / **Bỏ chọn tất cả** áp dụng cho toàn bộ ma trận.
- Quyền có phạm vi (ví dụ xem đại lý, xem công nợ) cho phép chọn **Tất cả đại lý** hoặc **Đại lý được phân công**.
- Bấm **Lưu phân quyền** để áp dụng. Thay đổi chưa lưu sẽ mất khi rời trang.

## 7.3. Đồng bộ quyền

Nút **Đồng bộ quyền** (góc phải phần Phân quyền) nạp các **quyền mới phát sinh trong quá trình phát triển** vào hệ thống — tương đương lệnh `php artisan permissions:sync`. Dùng sau khi triển khai phiên bản có thêm quyền mới mà ma trận chưa hiển thị.

- Không thay đổi quyền đang cấp cho bất kỳ tài khoản nào.
- Quyền không còn trong mã nguồn chỉ được báo lại, **không bị xóa**.
- Kết quả đồng bộ được ghi vào nhật ký kiểm toán.

## 7.4. Vai trò

Menu **Vai trò superadmin**: lưu sẵn các bộ quyền (ví dụ Kế toán, Hỗ trợ đại lý) để áp dụng nhanh. Trên trang chi tiết Superadmin, chọn vai trò rồi bấm **Áp dụng** để thay toàn bộ quyền của tài khoản bằng bộ quyền của vai trò. Biểu mẫu vai trò dùng cùng ma trận quyền (có chọn tất cả theo nhóm).
