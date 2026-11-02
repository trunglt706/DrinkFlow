# 6. Chính sách Tài trợ (Sponsor)

[← Về Tổng quan nghiệp vụ](overview.md)

Model `Campaign` khai báo 4 hằng số kiểu tài trợ (`Campaign::SPONSOR_TYPES`), nhưng **chỉ 2 kiểu dùng được**:

| Hằng số | Giá trị | Trạng thái |
| --- | --- | --- |
| `SPONSOR_TYPE_NONE` | `none` | ✅ Không tài trợ — từng thành viên tự trả |
| `SPONSOR_TYPE_FULL` | `full` | ✅ Tài trợ toàn bộ — một hoặc nhiều Sponsor chia theo % (`sponsor_allocations`) |
| `SPONSOR_TYPE_PER_ITEM` | `per_item` | ⛔ Không tạo được: `StoreCampaignRequest`/`UpdateCampaignRequest` chỉ chấp nhận `none,full` |
| `SPONSOR_TYPE_BUDGET` | `budget` | ⛔ Như trên |

`per_item` và `budget` chỉ còn là hằng số + nhánh tính toán cũ trong `OrderPricingService::sponsorAmount()` (trợ giá cố định/đơn và quỹ dùng chung theo thứ tự đặt, giới hạn bởi `max_budget`) để đọc đúng dữ liệu cũ, bộ lọc danh sách chiến dịch và nội dung thông báo. Không xây tính năng mới dựa trên hai kiểu này khi chưa mở lại validation và UI.

## 6.1. Sponsor lúc đặt món

Khi đặt món, `OrderPricingService::sponsorAmount()` tính `sponsor_amount` dự kiến của đơn để hiển thị cho thành viên. Dòng **trả riêng** (`order_items.is_self_paid`) không bao giờ được tài trợ. Số chốt cuối cùng do lúc đóng chiến dịch quyết định (mục 6.2).

## 6.2. Quyết toán lúc đóng chiến dịch (`CloseCampaignAction`)

### Tài trợ toàn bộ (`full` + có ít nhất một allocation)

Dùng chung `CampaignSponsorshipCalculator` với mọi màn hình hiển thị số tài trợ, nên con số trên màn hình đúng bằng công nợ được tạo:

```
1. Phí ship và giảm giá của chiến dịch chia cho từng đơn theo tỉ lệ subtotal (phân bổ chính xác tới từng đồng).
2. Mỗi đơn tách phần "trả riêng" và phần được tài trợ; phần ship/giảm giá của phần trả riêng
   tính theo Campaign.self_paid_price_basis:
     - original          : phần trả riêng tính theo giá gốc, không chịu ship/giảm giá
     - campaign_prorated : ship/giảm giá chia theo tỉ lệ giữa phần trả riêng và phần được tài trợ
3. pool = Σ phần được tài trợ (đã gồm ship − giảm giá tương ứng)
4. share[i] = round(pool × percentage[i] / 100); sai số làm tròn dồn vào Sponsor đầu tiên
```

Kết quả:
- Mỗi Order: `sponsor_amount` = phần được tài trợ, `final_amount` = **phần trả riêng** (bằng 0 nếu không có món trả riêng), `status = Completed`.
- Mỗi Sponsor: một `Debt` với `original_amount` (tiền món) + `adjustment_amount` (ship − giảm giá) = `remaining_amount`.
- Người có món trả riêng: một `Debt` cho phần đó. Nếu Sponsor cũng tự đặt món trả riêng, hai khoản được gộp vào **một** Debt.

### Không tài trợ (`none`)

Nợ ghi cho **từng thành viên** theo tỉ lệ đóng góp vào tổng đơn:

```
ratio[user]        = subtotal[user] / grossSubtotal
deliveryFee[user]  = round(delivery_fee × ratio[user])
discount[user]     = round(discount × ratio[user])
finalAmount[user]  = max(0, subtotal[user] + deliveryFee[user] − discount[user] − sponsorAmount[user])
```

Sai số làm tròn được bù vào người đầu tiên để tổng khớp chính xác hoá đơn.

## 6.3. Bảng quyết định: ai bị ghi nợ?

| Chính sách | Ai nợ? | Khi nào phát sinh |
| --- | --- | --- |
| `none` | Từng thành viên (100% giá trị đơn + phần ship/giảm giá) | Lúc đóng chiến dịch |
| `full` | Sponsor (theo % cam kết) + người đặt cho phần **trả riêng** | Lúc đóng chiến dịch |

Chọn nhà tài trợ ngẫu nhiên: [bài 14 – Vòng quay may mắn](14-vong-quay-may-man.md). Thống kê và huy hiệu Top 3 nhà tài trợ mỗi tháng: [bài 15](15-huy-hieu-nha-tai-tro-thanh-tich.md).

## Tham chiếu mã nguồn

`App\Models\Campaign` (hằng số `SPONSOR_TYPE_*`, `SELF_PAID_PRICE_BASIS_*`, cột `sponsor_allocations`) · `App\Services\Campaign\CampaignSponsorshipCalculator` · `App\Services\Order\OrderPricingService::sponsorAmount()` · `App\Actions\Campaign\CloseCampaignAction` · `App\Http\Requests\StoreCampaignRequest`, `UpdateCampaignRequest` · Xem thêm hệ quả công nợ ở [bài 7](07-thanh-toan-cong-no.md).
