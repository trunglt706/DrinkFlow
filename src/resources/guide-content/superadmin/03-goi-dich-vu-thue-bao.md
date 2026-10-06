# 3. Gói dịch vụ & thuê bao

## 3.1. Gói dịch vụ

Menu **Gói dịch vụ**: mỗi gói có mã, tên, **giá theo tháng**, **hạn mức phòng** và trạng thái. Chỉ gói đang mở mới hiện ở trang đăng ký đại lý.

Khi một đại lý đăng ký hoặc đổi gói, giá và hạn mức được **chốt (snapshot)** vào thuê bao của họ. Sửa giá gói sau đó không ảnh hưởng tới thuê bao đang chạy.

## 3.2. Chu kỳ thanh toán

Mỗi thuê bao có **chu kỳ riêng** (ví dụ 1 tháng, 6 tháng, 1 năm = 12 tháng) do đại lý chọn khi đăng ký:

- Hóa đơn mỗi kỳ = giá tháng × số tháng của chu kỳ.
- Khi hết kỳ, thuê bao **tự gia hạn đúng chu kỳ đó** và sinh hóa đơn kỳ mới ở trạng thái *Chờ thanh toán*.

## 3.3. Thuê bao

Menu **Đại lý → Thuê bao**: danh sách thuê bao, gói, ngày hết hạn và trạng thái (*Chờ thanh toán*, *Đang hoạt động*, *Đã thay thế*, *Hết hạn*, *Đã hủy*).

Trên trang thuê bao của một đại lý, Superadmin có thể:

- **Đổi gói ngay**: nâng cấp áp dụng ngay, kèm phần khấu trừ giá trị chưa dùng của kỳ hiện tại; hạ cấp bị từ chối nếu đại lý đang sở hữu nhiều phòng hơn hạn mức gói mới.
- **Hủy thuê bao** khi cần chấm dứt dịch vụ.

Khi chính đại lý nâng cấp, hệ thống phát hành **hóa đơn nâng cấp**; gói mới chỉ áp dụng khi hóa đơn đó được thanh toán. Hạ cấp do đại lý yêu cầu được lên lịch vào cuối kỳ.
