# 2. Quản lý Room & Thành viên

[← Về Tổng quan nghiệp vụ](overview.md)

**Room** là đơn vị tổ chức trung tâm của DrinkFlow — mỗi phòng ban/nhóm là một Room độc lập với thành viên, chiến dịch, công nợ và cấu hình riêng. Mỗi Room thuộc về **một Agent** (`rooms.owner_admin_id`).

## 2.1. Vòng đời Room

```mermaid
stateDiagram-v2
    [*] --> Active: Agent tạo Room (/admin/rooms, trong hạn mức gói)\nhoặc Superadmin tạo
    Active --> Inactive: tạm ngưng (Superadmin)
    Inactive --> Active: kích hoạt lại
    Active --> Archived: Agent hoặc Superadmin lưu trữ
    Inactive --> Archived
    Archived --> Active: Agent khôi phục (nếu còn hạn mức)
```

- **Agent** tự tạo, sửa, lưu trữ và khôi phục Room của mình (`Admin\RoomController` → `ManageAgentRoomAction`). Số Room `Active` + `Inactive` bị giới hạn bởi gói dịch vụ (`RoomQuotaService`, `room_limit_snapshot` của thuê bao đang hiệu lực); Room `Archived` không tính vào hạn mức, nên khôi phục Room cũng phải còn chỗ trống.
- **Superadmin** tạo/sửa/đổi trạng thái/xoá mọi Room (`Superadmin\RoomController`, `ManageRoomAction`) theo permission `room.manage`.
- Chỉ Room `Active` mới vào được khu vực quản trị (`admin.room`) và được các lệnh nền (thống kê, nhắc nợ, thông báo) xử lý.
- Agent có thể thêm Admin khác làm **cộng tác viên** của Room (`admin_rooms`); Superadmin cũng gán được qua `PUT /superadmin/admins/{admin}/rooms`.

## 2.2. Vòng đời thành viên (RoomUser)

```mermaid
stateDiagram-v2
    [*] --> Active: Global User tham gia qua link mời\nhoặc Admin thêm trực tiếp
    Active --> Blocked: Admin chặn (vi phạm / nợ quá hạn)
    Blocked --> Active: Admin duyệt lại
    Active --> Removed: Admin xoá khỏi Room
    Removed --> Active: Admin khôi phục
```

Điểm quan trọng: **`RoomUserStatus` độc lập với `GlobalUserStatus`** — bị `Blocked` tại 1 Room không ảnh hưởng quyền truy cập các Room khác hay Cổng thông tin cá nhân `/me` của Global User đó (`JoinRoomAction`, `SetRoomUserStatusAction`). Ngược lại, Global User bị khoá/xoá thì không dùng được Room nào.

## 2.3. Luồng tham gia Room

```mermaid
sequenceDiagram
    participant U as Global User
    participant J as JoinPageController
    participant A as JoinRoomAction
    U->>J: GET /rooms/{room}/join
    alt chưa là thành viên
        J-->>U: hiển thị trang xác nhận tham gia
        U->>J: POST /rooms/{room}/join
        J->>A: execute(room, globalUser)
        A-->>J: tạo RoomUser (status=active, user_code sinh tự động)
        J-->>U: redirect vào Dashboard Room
    else đã là thành viên active
        J-->>U: redirect thẳng vào Dashboard Room
    end
```

Room có thể giới hạn truy cập theo **allowlist/blocklist IP** (cấu hình Room, kiểm tra ở `RoomAccessPolicy`) — IP người dùng phải được nhận đúng, xem [`documents/deploy/trusted-proxies.md`](../deploy/trusted-proxies.md).

Mỗi `RoomUser` được cấp một **`user_code`** ngẫu nhiên — dùng làm định danh khi đặt hộ ([bài 4](04-dat-mon-gio-hang.md)), làm nội dung chuyển khoản "thanh toán tất cả" ([bài 7](07-thanh-toan-cong-no.md)) và hiển thị trong bảng đối soát thay vì lộ email.

## 2.4. Thiết bị tin cậy (Device Trust)

Mỗi lần đăng nhập trên thiết bị mới có thể tạo một bản ghi `RoomUserDevice` gắn với `RoomUser` cụ thể (không phải Global User) — cho phép bỏ qua xác thực lại trong một khoảng thời gian. Admin có thể **thu hồi** từng thiết bị (`admin.room-user-devices.revoke`) khi nghi ngờ mất máy — xem chi tiết ở [bài 10](10-bao-mat-thiet-bi-audit.md).

## Tham chiếu mã nguồn

`App\Models\Room`, `App\Models\RoomUser`, `App\Models\RoomUserDevice` · `App\Enums\RoomStatus`, `RoomUserStatus` · `App\Policies\RoomPolicy` · `App\Services\Room\RoomQuotaService`, `RoomAccessPolicy` · `App\Actions\Room\ManageAgentRoomAction` · `App\Actions\User\JoinRoomAction`, `SetRoomUserStatusAction`, `RestoreRoomUserAction`, `AdminAddRoomUserAction` · `App\Actions\Superadmin\ManageRoomAction` · `App\Events\RoomMembershipUpdated`.
