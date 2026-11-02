# 5. Vận hành đơn hàng (Order)

[← Về Tổng quan nghiệp vụ](overview.md)

## 5.1. Vòng đời trạng thái (`OrderStatus`)

```mermaid
stateDiagram-v2
    [*] --> Submitted: User gửi đơn
    Submitted --> Confirmed: Admin xác nhận
    Confirmed --> Ordering: quán đang pha chế/chuẩn bị
    Ordering --> Ordered: đã xong tại quán
    Ordered --> Delivering: đang giao tới Room
    Delivering --> Completed: người nhận xác nhận đã nhận món
    state "Bất kỳ trạng thái đang hoạt động" as ANY
    ANY --> Cancelled: Admin/User huỷ (còn trong hạn)
```

`isActive()` coi 5 trạng thái đầu (`Submitted…Delivering`) là **"đang hoạt động"**; `Completed` và `Cancelled` là trạng thái cuối (terminal). Khi [đóng chiến dịch](03-chien-dich-gom-don.md#31-vòng-đời-chiến-dịch-campaignstatus), toàn bộ Order đang hoạt động bị chuyển thẳng sang `Completed`.

## 5.2. Sửa/khoá đơn

`UpdateOrderAction` chỉ cho sửa khi Order **đang hoạt động và chưa Cancelled** (`order.status->isActive()`); Order đã `Completed`/`Cancelled` bị khoá, cần Admin bấm **"Mở khoá đơn"** (`unlock`) mới sửa lại được — dùng khi cần chỉnh sau khi chiến dịch đã đóng.

## 5.3. Điều chỉnh giá thực tế — công thức tính lại

```mermaid
flowchart TD
    A["Admin sửa unit_price của 1 OrderItem"] --> B{"unit_price + phần topping/qty\n> Campaign.max_budget ?"}
    B -->|Có| Reject["Từ chối — báo vượt trần ngân sách"]
    B -->|Không| C["line_subtotal = unit_price × qty + tổng topping"]
    C --> D["Order.subtotal = Σ line_subtotal của mọi item"]
    D --> E["sponsor_amount = tính lại theo chính sách tài trợ\ncủa Campaign (xem bài 6)"]
    E --> F["final_amount = max(0, subtotal + delivery_amount − discount_amount − sponsor_amount)"]
    F --> G["Ghi Audit Log: order.price_adjusted\n(giá cũ/mới, lý do)"]
    G --> H["Thông báo order.price_adjusted cho chủ đơn\n+ Event OrderUpdated → Socket.IO cập nhật màn hình"]
```

Trần `Campaign.max_budget` áp dụng cho **cả dòng** (đơn giá × số lượng + topping). Server **luôn** tính lại `subtotal`/`sponsor_amount`/`final_amount` từ dữ liệu gốc — Admin chỉ được sửa `unit_price` từng dòng, không được ghi đè trực tiếp các trường tổng hợp. Dòng **trả riêng** (`is_self_paid`) không tính vào phần được tài trợ.

## 5.4. Thanh toán ở cấp Order (`PaymentStatus`)

Ngoài công nợ sau khi đóng chiến dịch ([bài 7](07-thanh-toan-cong-no.md)), thành viên có thể báo đã chuyển khoản cho **từng đơn** (`ConfirmOrderPaymentAction`): `payment_status` chuyển `Unpaid → Pending`, Admin nhận thông báo `order.payment_submitted` (lưu + realtime) và duyệt bằng `AdminApproveOrderPaymentAction`: hành động này tìm (hoặc tạo) khoản `Debt` của đơn trong chiến dịch rồi duyệt qua `ApproveDebtPaymentAction` — nghĩa là việc "đã trả" luôn được ghi nhận ở cấp công nợ.

## 5.5. Xử lý hàng loạt

`bulkStatus` và `bulkCancel` (Admin) áp dụng cùng `UpdateOrderStatusAction` cho nhiều Order được chọn trong 1 transaction, kèm ghi `AuditService` cho từng thay đổi.

## Tham chiếu mã nguồn

`App\Models\Order`, `OrderItem`, `OrderItemTopping` · `App\Enums\OrderStatus`, `PaymentStatus` · `App\Actions\Order\UpdateOrderAction`, `UpdateOrderItemsAction`, `UpdateOrderStatusAction`, `DeleteOrderAction`, `ConfirmOrderPaymentAction`, `AdminApproveOrderPaymentAction` · `App\Events\OrderCreated/Updated/Deleted` · Xem thao tác UI ở [Hướng dẫn Admin – bài 5](../../src/public/guide-content/admin/05-quan-ly-don-hang.md) và [Hướng dẫn User – bài 4](../../src/public/guide-content/user/04-theo-doi-don-hang.md).
