# 20. Góp ý, Liên hệ, Phiên bản & Hướng dẫn

[← Về Tổng quan nghiệp vụ](overview.md)

Các nghiệp vụ nội dung và kênh liên lạc giữa người dùng với đội vận hành nền tảng.

## 20.1. Góp ý của thành viên (Feedback)

- Global User gửi đánh giá (số sao `rating`) + nội dung tại `/me/feedback` (`user.me.feedback.store`, rate limit `feedback-submission`).
- `FeedbackStatus`: mặc định **`Inactive`** khi gửi; Superadmin duyệt sang `Active`, sửa hoặc xoá (`/superadmin/feedbacks`).
- Chỉ góp ý **`Active` và từ 3 sao trở lên** (`Feedback::MIN_VISIBLE_RATING`, scope `visibleOnFeedbackPage`) được hiển thị và tính vào điểm trung bình/phân bố sao (`UserFeedbackService`).

## 20.2. Liên hệ (Contact)

- Form công khai `/contact`: chủ đề `ContactTopic` (`vietqr`, `deploy`, `feedback`, `merchant`, `other`).
- Bảo vệ chống spam theo [AGENTS.md mục 5](../../AGENTS.md): **captcha** (`mews/captcha`, chỉ bắt buộc khi captcha bật và máy chủ có `ext-gd`) và rate limit `contact-submission`; validation ở backend (`ContactRequest`).
- Mỗi yêu cầu nhận mã ticket dạng `#DF-XXXXX` duy nhất (`ContactService`).

## 20.3. Phiên bản phát hành (Versions)

- Trang công khai `/versions/{version?}` liệt kê lịch sử phát hành, nội dung theo ngôn ngữ đang chọn (`VersionService`).
- Dữ liệu từ bảng `versions` (Superadmin quản lý tại `/superadmin/versions`; `VersionSeeder` nạp sẵn khi `db:seed`), đọc qua cache và **xoá cache** mỗi khi Superadmin tạo/sửa/xoá phiên bản. Khi DB chưa có dữ liệu thì dùng danh sách mặc định trong mã nguồn.

## 20.4. Hướng dẫn sử dụng (Guides)

`UserGuideService` đọc các bài Markdown đặt tên `NN-slug.md`, sắp xếp theo số thứ tự, lấy tiêu đề từ H1 đầu tiên, trích đoạn mô tả, viết lại link nội bộ/ảnh rồi render bằng CommonMark (GFM).

| Bộ | Thư mục | Đường dẫn |
| --- | --- | --- |
| Thành viên (client) | `src/public/guide-content/user` | `/guides`, `/me/guides`, `/rooms/{room}/guides` |
| Đại lý (agent) | `src/public/guide-content/admin` | `/guides/agent` |
| Superadmin (nội bộ) | `src/resources/guide-content/superadmin` — **không** nằm trong `public/` | `/superadmin/guides` (cần đăng nhập Superadmin) |

Khi thêm tính năng có giao diện, cập nhật bài hướng dẫn tương ứng trong các thư mục trên; tài liệu nghiệp vụ trong `documents/nghiep-vu/` liên kết tới chúng.

## Tham chiếu mã nguồn

`App\Models\Feedback`, `Version` · `App\Enums\FeedbackStatus`, `ContactTopic` · `App\Services\User\UserFeedbackService`, `App\Services\Contact\ContactService`, `App\Services\Public\VersionService`, `App\Services\Guide\UserGuideService` · `App\Http\Controllers\Public\ContactController`, `VersionController`, `GuideController` · `App\Http\Controllers\Superadmin\FeedbackController` · `App\Http\Requests\ContactRequest`.
