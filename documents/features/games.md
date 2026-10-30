# Trò chơi (Game vui)

Người dùng vào **"Trò chơi"** từ hai chỗ: banner trên trang `/me`, hoặc mục **Trò chơi** trên menu User Global. Cả hai dẫn tới `/me/games`, nơi liệt kê các trò chơi trong bảng `games`.

- Trò chơi do Superadmin quản lý. Mặc định seed sẵn **"Game vui"** (`slug = game-vui`).
- Chọn một game sẽ mở lịch sử các room mà người dùng đã tạo cho game đó.
- Chủ room (Owner) tạo room, ghi nhận thắng/thua, xem bảng xếp hạng và chia sẻ link công khai.
- Người chơi mở link công khai, không cần đăng nhập. Họ chọn tên mình rồi tự ghi nhận các trận thắng của mình.

Giao diện ưu tiên mobile và hỗ trợ vi/en/ja (`src/lang/{vi,en,ja}/games.php`).

## Luồng màn hình

| URL | Mô tả |
| --- | --- |
| `/me` | Banner "Trò chơi" nằm dưới phần chào. Menu User Global có mục **Trò chơi** (`data-nav-games`). |
| `/me/games` | Danh sách game đang bật (`is_active`), sắp xếp theo `sort_order`. |
| `/me/games/{slug}` | Lịch sử room của Owner: tên, trạng thái, số người chơi, thời gian hoạt động gần nhất. Có bộ lọc *Tất cả / Đang hoạt động / Đã kết thúc* và nút sao chép link công khai (room đã kết thúc thì không có nút này). |
| `/me/games/{slug}/rooms/create` | Tạo room trên một màn hình: tên room → số người chơi (mặc định 4, từ 2 đến 20) → tên người chơi → chọn bộ luật → xem lại → tạo. Tên người chơi thêm/xóa dòng được; để trống thì tự đặt "Người chơi N". |
| `/me/games/{slug}/rooms/{id}` | Dashboard room của Owner. Header gồm tên, trạng thái, link công khai, QR, tạo link mới/thu hồi, và các nút Bắt đầu / Vòng mới / Kết thúc. Bên dưới là tóm tắt, bảng xếp hạng, đối đầu, lịch sử kết quả, quản lý người chơi và nút **Ghi kết quả**. |
| `/me/games/{slug}/rule-sets` | Bộ luật tính điểm của Owner; tạo/sửa bộ luật tại `/create` và `/{id}/edit`. |
| `/play/{token}` | Trang công khai của room, xem chi tiết ở mục dưới. |
| `/superadmin/games` | Superadmin quản lý danh sách trò chơi: thêm, sửa, xóa. |

### Trang công khai `/play/{token}`

- Dùng layout riêng `<x-game.play-layout>`, không dùng layout chung của site hay của room. Đầu trang chỉ có thanh nhỏ gồm:
  - Nút **Quản trị room**: quay lại dashboard `/me/games/{slug}/rooms/{id}`. Chỉ hiện khi người xem đang đăng nhập và là chủ room.
  - Logo.
  - **Dropdown ngôn ngữ** (Tiếng Việt / English / 日本語).
- Giao diện mobile-first, ít thao tác:
  - **Header**: tên room, trạng thái, vòng hiện tại, số kết quả, nút **Chia sẻ**.
  - **Chọn tên ("Bạn là ai?")**: lưới nút lớn. Sau khi chọn, hiện thẻ "Bạn: …" với Hạng / Điểm / Thắng / Thua và nút **Đổi**.
  - Bảng xếp hạng (dòng của người chơi đang chọn được làm nổi bật), đối đầu, lịch sử kết quả.
  - **Thanh dưới cố định**:
    - Nút **Ghi nhận kết quả**.
    - Nút **Vòng mới**, chỉ hiện khi người xem đang đăng nhập và là chủ room.
- **Chia sẻ**: modal có mã QR dẫn tới link trò chơi, nút sao chép link và nút "Gửi qua ứng dụng" (dùng Web Share API nếu trình duyệt hỗ trợ).
- **Ghi nhận kết quả** mở bottom sheet trên một màn hình duy nhất:
  - Người thắng mặc định là người chơi đã chọn, không có bước chọn người thắng.
  - Chỉ cần chọn **Danh mục** và **một Người thua** (chọn đơn).
  - Khi mở sheet, giá trị đầu tiên không bị disable của mỗi nhóm được chọn sẵn, nên với kết quả thông thường chỉ cần bấm **Xác nhận** một lần.
- **Loading**: nút Xác nhận (ghi kết quả, và hộp xác nhận "Vòng mới") hiện spinner "Đang tải..." và bị khóa cho tới khi trang tải lại, nên không bấm hai lần được.

Modal **Ghi kết quả** của Owner trên dashboard vẫn có 4 bước: danh mục → người thắng → người thua → xem lại. Mỗi bước cũng chọn sẵn giá trị đầu tiên không bị disable; người đã chọn làm người thắng bị khóa ở bước chọn người thua. Các thao tác thay đổi dữ liệu đều có hộp xác nhận. Hủy kết quả bắt buộc nhập lý do.

## Quy tắc nghiệp vụ

### Room (BR-01, BR-09, BR-10)

- **Chủ sở hữu**: room thuộc về một Owner (`game_rooms.owner_id` → `global_users`). Chỉ Owner xem dashboard trong `/me` và quản trị room (`GameRoomPolicy::manage`); người khác nhận 403.
- **Trạng thái** (`App\Enums\GameRoomStatus`): `waiting` → `playing` → `finished`.
  - Room được tạo ở trạng thái `waiting`.
  - **Bắt đầu** cần ít nhất 2 người chơi đang hoạt động; room chuyển sang `playing` và tạo **vòng 1**.
  - Owner mở vòng mới từ dashboard hoặc từ trang công khai. Kết quả mới luôn thuộc vòng mới nhất.
  - Room chỉ kết thúc khi Owner bấm **Kết thúc**.
- **Room đã kết thúc**: không nhận kết quả, vòng, người chơi hay thao tác hủy nào nữa. Link công khai bị đóng (xem BR-02).
- **Người chơi**:
  - Mỗi người chơi có ID cố định (`game_players.id`). Đổi tên hoặc ngừng chơi không làm thay đổi lịch sử.
  - Người đã ngừng chơi vẫn hiện trên bảng xếp hạng nhưng không được chọn khi ghi kết quả mới.
  - Tên người chơi không được trùng trong một room (không phân biệt hoa thường).

### Truy cập công khai (BR-02)

- **Token**: `game_rooms.public_token` (40 ký tự, unique) là quyền truy cập trang công khai.
  - Owner có thể **tạo link mới** (link cũ ngừng hoạt động ngay) hoặc **thu hồi** link.
  - Token sai hoặc đã thu hồi: trả **404** kèm thông báo.
- **Link bị đóng**: khi room `finished` **hoặc** game bị Superadmin tắt (`is_active = false`), cả trang lẫn API ghi kết quả trả **410** với thông báo "Trò chơi đã đóng". Owner vẫn xem lịch sử trong `/me`, và dashboard ghi rõ link công khai không còn truy cập được.
- **Chọn danh tính**: người xem chọn danh tính qua `?player={id}`. Lựa chọn lưu trong session theo từng room; bỏ chọn bằng `?player=none`.
- **Ghi kết quả từ link công khai**: endpoint `POST /play/{token}/results` (JSON, `throttle:30,1`).
  - **Người thắng** luôn là người chơi đã chọn trong session; server không nhận `winner_id` từ client.
  - Người chơi đó phải đang hoạt động; người xem chưa chọn tên thì không ghi được.
  - Kết quả lưu `recorded_by_player_id`, và lịch sử hiển thị "Ghi bởi …".
- **Mỗi lần gửi là một kết quả với đúng một người thua** (`loser_id`). Trong cùng một vòng, một người chơi có thể ghi nhiều kết quả, không bị chặn; mỗi kết quả phải được ghi bằng một thao tác riêng.
  - Nút **Ghi nhận kết quả** luôn hiển thị khi room đang chơi.
  - Mỗi lần mở sheet sinh một idempotency key mới, nên gửi lại cùng key (khi mất mạng) không tạo kết quả trùng.
  - Kết quả luôn được lưu vào vòng hiện tại (vòng mới nhất) do server xác định lúc ghi.
- **Vòng mới từ trang công khai**: nút **Vòng mới** chỉ hiện khi người xem đang đăng nhập và là chủ room. Form gửi tới route của Owner, nơi `GameRoomPolicy` kiểm tra lại, rồi redirect về trang công khai.
- **Dữ liệu ẩn**: trang công khai không hiển thị email Owner hay dữ liệu nội bộ.
- **Hạn chế đã biết**: không có đăng nhập ở trang công khai, nên người cầm link có thể chọn tên người khác. Đây là mô hình tin cậy chấp nhận cho MVP; Owner hủy kết quả sai bằng void.

### Kết quả và điểm (BR-03, BR-04)

- Mỗi kết quả có đúng một người thắng và ít nhất một người thua. Tất cả phải là người chơi đang hoạt động của cùng room, và người thắng không được nằm trong danh sách thua.
- Điểm lấy từ danh mục thuộc phiên bản luật room đang áp dụng; client không gửi điểm.
- Chỉ người thắng được cộng điểm. Điểm âm chỉ được phép khi bộ luật bật "Cho phép điểm âm".
- Tổng điểm của người chơi bằng tổng các `game_score_transactions` của người đó.

### Phiên bản luật (BR-05)

- Mỗi lần lưu bộ luật tạo một **phiên bản mới**; phiên bản cũ không bao giờ bị sửa.
- Room chỉ đổi phiên bản khi Owner bấm **Áp dụng phiên bản mới**. Nút này có cảnh báo trước khi áp dụng.
- Mỗi kết quả lưu snapshot `rule_item_name`, `points`, `rule_version_number`. Lưu trữ một danh mục không làm mất lịch sử.

### Lịch sử bất biến (BR-06)

- Không xóa cứng kết quả. Sửa sai bằng **hủy** (`voided`) kèm lý do.
- Giao dịch điểm gốc được giữ nguyên; hệ thống thêm giao dịch `reversal` đối dấu (unique theo `reverses_transaction_id`). Model `GameScoreTransaction` chặn update/delete.
- Kết quả đã hủy vẫn hiện trong lịch sử (gạch ngang, nhãn "Đã hủy" và lý do) nhưng không được tính vào thống kê.

### Thống kê (BR-07)

`App\Services\Game\GameStatisticsService` tính các chỉ số sau:

- **Điểm**: tổng giao dịch điểm.
- **Thắng**: số kết quả hợp lệ mà người chơi thắng.
- **Thua**: số lần người chơi nằm trong danh sách thua của kết quả hợp lệ.
- **Đối đầu**: đếm riêng A thắng B và B thắng A. Với mỗi đối thủ, hiển thị thêm **điểm kiếm được** từ đối thủ đó, cùng số thắng/thua.
  - Ví dụ: Client 1 có tổng 10 điểm, gồm 5 điểm với Client 2 (1 thắng · 2 thua) và 5 điểm với Client 3.
  - Điểm với đối thủ là tổng điểm snapshot của các kết quả hợp lệ mà người chơi thắng đối thủ đó.
  - Một kết quả có nhiều người thua (chỉ chủ room ghi được) chia đều điểm cho từng người thua, tối đa 2 chữ số thập phân. Nhờ vậy tổng các dòng đối đầu luôn bằng tổng điểm.
- **Xếp hạng**: Điểm ↓ → Thắng ↓ → Tên ↑.
- Lịch sử hiển thị 100 kết quả gần nhất.

### Idempotency và đồng thời (BR-08)

- Mỗi lần ghi kết quả gửi kèm `idempotency_key` (UUID); có unique index `(game_room_id, idempotency_key)`.
- Gửi lại cùng key (ví dụ khi mất mạng) nhận `200` với kết quả cũ thay vì `201`, nên không bị ghi trùng.
- Kết quả, danh sách người thua, giao dịch điểm và audit log được ghi trong một transaction. Transaction khóa dòng `game_rooms` và kiểm tra lại room vẫn `playing` trước khi ghi.

## Quản lý trò chơi (Superadmin)

- **Vị trí**: menu **Nội dung → Trò chơi** (`/superadmin/games`).
- **Quyền**: `game.view` để xem, `game.manage` để thêm/sửa/xóa. Hai quyền này có trong catalog `App\Enums\Permission`.
- **Trường dữ liệu**: tên, slug (dùng trong URL `/me/games/{slug}`, unique), icon (tên Material Symbols, có link mở trang tra cứu icon trên Google Fonts), thứ tự, mô tả, trạng thái **Đang bật**.
- **Tắt trò chơi**: trò chơi bị ẩn khỏi danh sách và các trang của người dùng (404), và mọi link công khai của game bị đóng (410). Dữ liệu được giữ nguyên.
- **Xóa trò chơi**: chỉ xóa được trò chơi **chưa có room hoặc bộ luật nào**. Trò chơi đã dùng thì phải tắt thay vì xóa.
- **Audit**: các thao tác ghi sự kiện `game.created`, `game.updated`, `game.deleted`.

## Kiến trúc

| Thành phần | Vị trí |
| --- | --- |
| Migration | `2026_10_30_090000_create_games_tables.php`, `2026_10_30_100000_add_recorded_by_player_to_game_results_table.php` |
| Seeder | `GameSeeder` (gọi từ `DatabaseSeeder`, chạy lại nhiều lần vẫn an toàn) |
| Model | `Game`, `GameRuleSet`, `GameRuleVersion`, `GameRuleItem`, `GameRoom`, `GamePlayer`, `GameRound`, `GameResult`, `GameScoreTransaction` |
| Enum | `GameRoomStatus`, `GameResultStatus`, `GameScoreTransactionType`; quyền `Permission::GameView`, `Permission::GameManage` |
| Service | `App\Services\Game\GameRoomService`, `GameResultService`, `GameRuleSetService`, `GameStatisticsService`, `GamePublicAccessService`, `GameCatalogService` |
| Controller | Owner: `User\Global\Games*Controller`. Công khai: `Public\GameRoomController`. Superadmin: `Superadmin\GameController` |
| FormRequest | `App\Http\Requests\Game\*` |
| Policy | `GameRoomPolicy`, `GameRuleSetPolicy` |
| View | `user/global/games/*`, `components/game/{status-badge,scoreboard,play-layout}`, `public/game-room*.blade.php`, `superadmin/games/*` |
| JS | `resources/js/global/games.js` (trang `/me`), `resources/js/game-play.js` (entry Vite riêng của trang công khai), `resources/js/shared/game-common.js` (hộp xác nhận, loading, uuid, toast) |

## Triển khai

Từ thư mục `src/`:

```bash
php artisan migrate
php artisan db:seed --class=GameSeeder
php artisan permissions:sync
npm run build
```

Sau `permissions:sync`, cấp quyền `game.view` / `game.manage` cho vai trò hoặc tài khoản Superadmin cần dùng.

## Kiểm thử

```bash
php artisan test --filter="GamesTest|SuperadminGameManagementTest"
php artisan test
```

## Giới hạn MVP

- Không có realtime: muốn thấy kết quả mới thì phải tải lại trang.
- Trang công khai không yêu cầu đăng nhập; danh tính người chơi dựa trên lựa chọn trong session.
