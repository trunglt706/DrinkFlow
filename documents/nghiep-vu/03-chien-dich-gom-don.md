# 3. Chiến dịch gom đơn (Campaign)

[← Về Tổng quan nghiệp vụ](overview.md)

**Campaign** là trung tâm nghiệp vụ của DrinkFlow: một đợt gom đơn cụ thể gắn với một quán, một menu, một hạn chốt đơn và một chính sách tài trợ.

## 3.1. Vòng đời chiến dịch (`CampaignStatus`)

```mermaid
stateDiagram-v2
    [*] --> Draft: Admin lưu nháp
    Draft --> Active: activate() — cần ≥1 món Active + tài khoản thanh toán hợp lệ + hạn chốt còn tương lai
    Scheduled --> Active: activate()
    Draft --> Cancelled: cancel() (chưa live)
    Draft --> Archived: archive() (đường gián tiếp qua Cancelled)
    Active --> Closing: bắt đầu close() — khoá nhận đơn, đang tính toán
    Closing --> Closed: close() hoàn tất — đã tạo Debt, Order → Completed
    Active --> Archived: cancel() (đang live → huỷ orders, chuyển thẳng Archived)
    Closing --> Archived: cancel()
    Closed --> Archived: archive()
    Cancelled --> Archived: archive()
    Archived --> [*]
```

> **Lưu ý quan trọng**: khi `cancel()` một chiến dịch **đang live** (`Active`/`Scheduled`/`Closing`), toàn bộ Order chưa huỷ sẽ bị chuyển sang `Cancelled` và campaign đi thẳng sang `Archived` — **không** qua `Cancelled` như khi huỷ một bản `Draft` (`TransitionCampaignAction::cancel`).

> **Một Room chỉ có một chiến dịch đang chạy**: trước khi tạo/kích hoạt, `EnsureNoRunningCampaignAction` khoá dòng Room và từ chối nếu Room còn chiến dịch khác ở trạng thái `Active` hoặc `Closing`.

## 3.2. Ba nguồn nạp menu khi tạo nhanh

```mermaid
flowchart LR
    Start["Tạo Chiến Dịch Nhanh"] --> Src{Chọn nguồn menu}
    Src -->|"Chiến dịch trước"| Reuse["Copy Product/Category/Price/Image\ntừ Campaign cũ — KHÔNG copy Order"]
    Src -->|"Data Gateway (AI)"| AI["Dán ảnh/text menu →\nsinh prompt → AI chuẩn hoá JSON → Preview"]
    Src -->|"Crawl URL"| Crawl["Dán link ShopeeFood (shopeefood.vn) →\nFoodCrawlerGateway (Chrome headless) trích xuất → Preview"]
    Reuse --> Preview["Xem trước danh sách món\n(có thể thêm/sửa/xoá thủ công)"]
    AI --> Preview
    Crawl --> Preview
    Preview --> Publish["Lưu nháp hoặc Phát động ngay"]
```

Food Crawler hiện chỉ có provider ShopeeFood (`ShopeeFoodProvider`), chỉ crawl qua HTTPS và chặn địa chỉ mạng nội bộ — chi tiết ở [bài 19](19-nhap-menu-crawler-data-gateway.md). Dù chọn nguồn nào, Admin luôn có thể **thêm món thủ công** (tên, giá, ảnh, mô tả, topping, size) trước khi publish — không có màn hình quản trị "Sản phẩm" độc lập, dữ liệu món chỉ tồn tại trong phạm vi từng Campaign (`CreateCampaignAction`, `ImportCampaignItemsAction`, `CrawlerController`, `DataGatewayController`).

## 3.3. Trạng thái từng món trong menu (`CampaignItemStatus`)

`Active` (đang bán) → `SoldOut` (hết hàng tạm thời, vẫn hiện nhưng không đặt được) → `Inactive` (ẩn khỏi menu). Admin đổi trạng thái từng món hoặc hàng loạt (`toggleItemStatus`, `batchUpdateItemStatus`).

## 3.4. Theo dõi mức độ tham gia (`CampaignParticipant`)

Song song với Order, hệ thống theo dõi trạng thái tham gia của **từng thành viên** với từng chiến dịch để tính "Final Summary" chính xác — xem chi tiết công thức ở [bài 4](04-dat-mon-gio-hang.md).

## 3.5. Các hành động vòng đời khác (không đổi `status`)

| Hành động | Action class | Ghi chú |
| --- | --- | --- |
| Gia hạn deadline | `ExtendCampaignDeadlineAction` | Chỉ cộng thêm phút vào `deadline`, các mốc cố định `Campaign::EXTEND_DEADLINE_MINUTES` = `[10, 20, 30, 60]` phút |
| Khoá/mở nhận đơn | `SetCampaignOrderingLockAction` | Campaign vẫn `Active` nhưng thành viên không đặt món, sửa giỏ hay đổi trạng thái tham gia được; Admin vẫn đặt hộ được. Chỉ áp dụng khi còn trước `deadline` |
| Đổi tài khoản nhận tiền sau khi đóng | `UpdateClosedCampaignPaymentAccountAction` | Chỉ đổi `payment_account_id` của campaign đã đóng; Order/Debt giữ nguyên |
| Đánh dấu đang giao | `MarkCampaignDeliveringAction` | Bắn `CampaignDelivering` event, không đổi `status` |
| Gửi lại thông báo | `ResendCampaignNotificationAction` | Bắn lại `CampaignCreated`-style notification |
| Nhân bản | `DuplicateCampaignAction` | Tạo bản `Draft` mới sao chép cấu hình |
| Chia lại hoá đơn | `SplitCampaignBillAction` | Áp dụng `BillSplitMethod` khi cần chia lại phí ship/giảm giá sau khi đã có Order |

## Tham chiếu mã nguồn

`App\Models\Campaign`, `CampaignItem`, `CampaignParticipant` · `App\Enums\CampaignStatus`, `CampaignItemStatus` · `App\Actions\Campaign\*` (18 action) · `App\Services\FoodCrawler\FoodCrawlerGateway`, `App\Services\DataGateway\*` · `App\Events\CampaignCreated/Updated/Closed/Cancelled/Delivering` · Xem thao tác UI ở [Hướng dẫn Admin – bài 4](../../src/public/guide-content/admin/04-quan-ly-chien-dich.md).
