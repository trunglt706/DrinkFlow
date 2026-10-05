# UPG-09 – Nâng cấp UX/UI khu vực Superadmin (`/superadmin/*`)

**Trạng thái:** ✅ Hoàn thành 12/12 task (06/10/2026).
**Phạm vi:** Toàn bộ trang dưới `/superadmin/*` (layout, ~30 view, component `components/superadmin/*`, `resources/css/superadmin.css`, `resources/js/superadmin/*`) và trang đăng nhập superadmin.
**Nguyên tắc:** Giữ nguyên tính năng – route, controller, Gate/permission, `id`/`data-*` mà JS dùng và tên class `sa-*` vẫn là "API" của giao diện. Chỉ thay đổi trình bày và tương tác.

## Quyết định đã chốt (05/10/2026)

| # | Câu hỏi | Quyết định |
|---|---|---|
| 1 | Hướng thẩm mỹ | **Phong cách riêng cho Superadmin**, tách khỏi console admin room (emerald). Sidebar graphite tối, nội dung nền trung tính, accent indigo/violet – kiểu "control plane" (Linear/Vercel). |
| 2 | Dark mode | **Làm trong đợt này** – light/dark/theo hệ thống, lưu lựa chọn theo trình duyệt. |
| 3 | Thao tác nguy hiểm trên dòng bảng | **Đồng ý** chuyển Khoá/Xoá… vào menu ⋯ (row actions); cập nhật assertion liên quan và ghi rõ lý do. |

## Hiện trạng (tóm tắt kiểm tra)

1. `superadmin.css` có 2 lớp: style gốc (navy `#0b1c30`, font `Plus Jakarta Sans` không được tải) bị lớp "Unified theme" (emerald) ghi đè gần hết; mã màu hardcode khắp nơi dù đã có `@theme`. Inter bị tải 2 lần.
2. Chữ quá nhỏ: 46 rule ở 10–11px (bảng, nút, input 11px; nhãn 10px).
3. Component không đồng nhất: KPI dashboard vs revenue khác markup; bộ lọc `.sa-filters` vs `form.superadmin-actions`; biểu đồ revenue dùng `style=width`; 14 view có `<script>` inline + shim global trong layout.
4. Shell: 22 mục menu/7 nhóm, không tìm kiếm; icon `badge` trùng (Users/Roles); topbar không có breadcrumb, đổi ngôn ngữ, menu người dùng; mobile drawer không backdrop/Esc/focus trap.
5. Bảng: 2–3 nút chữ mỗi dòng, header không sticky, phân trang mặc định; phản hồi bằng `sa-notice` đầu trang + reload 500ms; KPI hiển thị "—" thay vì skeleton.

### Markup đang bị test khoá

- `<a class="sa-health-row" href="` · `id="sa-analytics"` · `data-sa-notifications`
- `class="sa-button warning" type="button" data-action="toggle-user-status" data-user-id="` (sẽ đổi ở UPG-09.4)
- `superadmin-logo` · `id="superadmin-logout-modal"` · `data-modal-open="superadmin-logout-modal"`
- `id="maintenance-form" class="sa-health-list" data-no-loading`
- `<span class="material-symbols-outlined text-[16px]">delete</span>`

## Danh sách task

| Mã | Nội dung | Trạng thái |
|---|---|---|
| UPG-09.0 | Baseline: chụp ảnh 30 trang (vi desktop/mobile + ja desktop, 90 ảnh tại `.playwright-mcp/baseline/`) | ✅ Hoàn thành |
| UPG-09.1 | Design tokens (light/dark), thang chữ, spacing, radius, shadow; gộp CSS, bỏ lớp style chết, tải font 1 lần | ✅ Hoàn thành |
| UPG-09.2 | Shell: sidebar tối (lọc menu, icon), topbar (breadcrumb, Ctrl/⌘ K, đổi ngôn ngữ, theme sáng/tối/hệ thống, user menu), mobile drawer (backdrop, Esc, focus trap); menu chuyển sang `App\Support\Superadmin\SuperadminNavigation` + `SuperadminLayoutComposer`. Nhóm menu thu gọn được thay bằng ô lọc + Ctrl K | ✅ Hoàn thành |
| UPG-09.3 | Component: `page-header`, `kpi`, `row-actions` (menu ⋯), `pagination`, `page-script`, toast (`showSuperadminToast`), tabs dùng chung (`[data-sa-tablist]`), `sa-label`/`sa-field`/`sa-form-actions`; làm lại `button`, `status-pill`, modal, `empty-state`, loading | ✅ Hoàn thành |
| UPG-09.4 | Mẫu **Danh sách**: header chung cho 20 trang, phân trang mới cho 16 trang, menu ⋯ cho users/admins/rooms/campaigns/queue/versions/notifications (assertion `toggle-user-status` đổi sang `sa-menu-item is-warning`) | ✅ Hoàn thành |
| UPG-09.5 | Mẫu **Chi tiết** (11 trang): header + back link icon, meta slot, tab gạch chân cho trang Đại lý; bỏ chuỗi cứng tiếng Anh/Việt ở room-detail | ✅ Hoàn thành |
| UPG-09.6 | **Form/Cài đặt**: thanh lưu `sa-form-actions` (sticky ở form vai trò), field/label 13px thống nhất | ✅ Hoàn thành |
| UPG-09.7 | **Dashboard**: KPI có icon, phần phân tích chia 3 tab (Vận hành / Bảo mật & hoạt động / Xu hướng & hạ tầng); **Doanh thu**: KPI chung + biểu đồ xu hướng theo token | ✅ Hoàn thành |
| UPG-09.8 | Đăng nhập superadmin: biến thể `:superadmin` của `x-admin-auth.layout` (cột trái graphite, accent indigo qua `.sa-auth` trong admin.css); trang đăng nhập admin giữ nguyên | ✅ Hoàn thành |
| UPG-09.9 | 13 trang chuyển script inline sang `resources/js/superadmin/pages/*.js` (bản dịch qua `data-i18n`), bỏ shim `window.dfApi…` trong layout và `exposeSuperadminGlobals()` | ✅ Hoàn thành |
| UPG-09.10 | QA: `php artisan test` 973 pass (4 lỗi có sẵn ở billing/subscription, không liên quan); 30 trang × sáng/tối không lỗi JS; mobile 390px không tràn ngang; kiểm tra key dịch vi/en/ja đủ | ✅ Hoàn thành |
| UPG-09.11 | **Datatable mới** (06/10/2026): bảng tràn sát mép card (bỏ hộp lồng hộp), header sticky, hàng 52px, bóng cuộn ngang, empty-state không viền, phân trang thành footer của card; canh lề theo loại cột cho 32 bảng (Blade + JS dashboard/room-detail/user-detail) | ✅ Hoàn thành |

## Hướng thiết kế (UPG-09.1)

- **Màu:** neutral = zinc; accent = indigo (`#4f46e5` light / `#818cf8` dark); trạng thái success/warning/danger/info mỗi màu có nền nhạt. Sidebar luôn tối (graphite `#0c0c0e`).
- **Token:** biến `--sa-*` trong `resources/css/superadmin.css`; `@theme inline` ánh xạ các màu Tailwind ngữ nghĩa (`text-on-surface`, `bg-surface-container`…) sang token nên markup dùng utility cũng tự đổi theo theme.
- **Chữ:** Inter, thang 12/13/14/16/20/24/30; bảng 13px; tối thiểu 12px; `tabular-nums` cho số/tiền.
- **Hình khối:** spacing bội số 4px; radius 6/10/14px; viền 1px mảnh, bóng rất nhẹ (ưu tiên viền thay bóng).
- Toàn bộ màu đi qua biến CSS `--sa-*`; dark mode qua `[data-theme="dark"]` + `prefers-color-scheme`.

## Quy ước cột của datatable (UPG-09.11)

Đặt cùng một class trên `<th>` và `<td>` của cột:

| Class | Dùng cho | Canh lề |
|---|---|---|
| *(không)* | Văn bản thường | Trái |
| `col-primary` | Định danh chính (tên + dòng phụ, avatar) | Trái, chiếm phần rộng còn lại (min 180px) |
| `num` | Số, tiền, số lượng, tỉ lệ | Phải, chữ số đều (`tabular-nums`), không xuống dòng |
| `col-status` | Badge / pill trạng thái | Trái, co theo nội dung |
| `col-date` | Ngày / giờ / kỳ | Trái, co theo nội dung, chữ số đều |
| `col-mono` | Mã, IP, UUID, số hóa đơn | Trái, monospace, không xuống dòng |
| `col-wrap` | Văn bản dài (góp ý, exception, metadata) | Trái, xuống dòng, 240–520px |
| `col-actions` | Nút thao tác / menu ⋯ | Phải, co theo nội dung |
