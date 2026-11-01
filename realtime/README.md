# DrinkFlow Realtime Gateway

`realtime/` chứa gateway Socket.IO tối giản cho DrinkFlow. Gateway chỉ xác thực socket, quản lý channel và chuyển tiếp event; Laravel vẫn là source of truth cho dữ liệu, nghiệp vụ và authorization.

## Chạy nhanh

### Docker Compose (khuyến nghị)

DrinkFlow hiện triển khai một gateway duy nhất trên một VPS; code chưa dùng Redis adapter hay chia sẻ state giữa nhiều instance.

Từ thư mục root:

```powershell
docker compose up -d --build realtime
docker compose logs -f realtime
```

Compose chạy Node.js 22, mount thư mục này vào `/app`, cài dependency rồi chạy `npm start`. Gateway lắng nghe cổng `3001`.

### Chạy độc lập trên host

```powershell
Set-Location realtime
npm install
$env:APP_KEY = "base64:replace-with-the-laravel-app-key"
$env:PORT = "3001"
$env:CORS_ORIGIN = "http://localhost:8080"
npm start
```

Gateway tự đọc `realtime/.env`, sau đó `src/.env` nếu biến chưa tồn tại. Có thể kiểm tra bằng `http://localhost:3001/health`.

## Cấu hình

| Biến | Bắt buộc | Mô tả |
| --- | --- | --- |
| `PORT` | Không | Cổng HTTP/Socket.IO, mặc định `3001`. |
| `HOST` | Không | Địa chỉ lắng nghe, mặc định `0.0.0.0` (cần cho Docker). Khi chạy native sau Nginx hãy đặt `127.0.0.1`. |
| `APP_KEY` | Có* | Khóa HMAC mà Laravel hiện dùng để ký socket token. |
| `SOCKET_TOKEN_SECRET` | Có* | Nếu có giá trị, gateway dùng khóa này thay cho `APP_KEY`. Phải giống khóa Laravel dùng để ký token; với code hiện tại nên để trống hoặc đặt bằng `APP_KEY`. |
| `REALTIME_INTERNAL_SECRET` | Có cho internal emit | Shared secret đối chiếu header `X-Realtime-Secret`. |
| `CORS_ORIGIN` | Không | Origin được phép, mặc định `*`; nên đặt URL web cụ thể ở production. |

`*` Gateway cần ít nhất một trong `SOCKET_TOKEN_SECRET` hoặc `APP_KEY`. Không commit các giá trị này. Compose truyền biến từ `src/.env` và có fallback development trong `docker-compose.yml`; phải thay secret mẫu trước khi triển khai.

## Xác thực socket

Laravel cấp token ngắn hạn qua:

- User theo Room: `/rooms/{room}/socket-token`
- User global: `/me/socket-token`
- Admin theo Room: `/admin/{room}/socket-token`
- Superadmin: `/superadmin/socket-token`

Token có dạng `base64url(JSON claims).hex-hmac-sha256`. Client gửi token qua Socket.IO auth hoặc header Bearer:

```javascript
const socket = io("http://localhost:3001", { auth: { token } });
```

Gateway kiểm tra chữ ký, `actor_type` (`user`, `admin`, `superadmin`) và `exp`. Token mặc định do Laravel phát có thời hạn 300 giây; client nên xin token mới khi hết hạn.

## Channel và authorization

Gateway tự join channel dựa trên signed claims. Browser không được tự quyết định ID để lấy quyền.

| Channel | Actor | Ý nghĩa |
| --- | --- | --- |
| `user:{roomUserId}` | User | Event cá nhân trong một Room |
| `global_user:{globalUserId}` | User | Event global của người dùng |
| `room:{roomId}` | User/Admin/Superadmin | Broadcast trong Room được claims cho phép |
| `game:{gameRoomId}` | Game viewer (`game_viewer`) | Khách xem trang public `/play/{token}` của một game room; nhận `game_room.status_changed` khi chủ room Start/End để reload trang |
| `admin:{adminId}` | Admin | Event riêng của Admin |
| `superadmin` | Superadmin | Event toàn hệ thống cho Superadmin |
| `system` | Superadmin | Event vận hành hệ thống |

Event `subscribe` chỉ cho phép tham gia lại channel đã có trong allow-list; channel khác nhận lỗi `Channel is not authorized`. Order/debt gắn với membership trong Room nên dùng `user:{room_user_id}`, không dùng global user channel nếu event chỉ thuộc một Room.

## Internal emit API

Laravel gửi event đến gateway:

```http
POST /internal/emit
X-Realtime-Secret: <REALTIME_INTERNAL_SECRET>
Content-Type: application/json
```

Ví dụ:

```json
{
  "event": "order.updated",
  "room_id": 4,
  "user_channel": "user:217",
  "payload": { "order_id": 91, "status": "completed" }
}
```

Allow-list hiện tại trong `server.js`:

```text
order.created, order.updated, order.deleted
order.payment_submitted, order.payment_approved, debt.payment_approved
campaign.created, campaign.updated, campaign.deleted, campaign.closed
campaign.cancelled, campaign.delivering
campaign.menu.updated, campaign.menu.deleted
campaign.participant.declined, campaign.participant.rejoined
notification.created, room.membership.updated
sponsor_draw.event, game_room.status_changed
```

Kết quả HTTP: `202` khi hợp lệ; `401` nếu sai internal secret; `400` nếu JSON lỗi; `422` nếu event, `room_id` hoặc input không hợp lệ. Event được gửi đến `room:{room_id}` và, nếu hợp lệ, thêm đến `user:<số>` hoặc `global_user:<số>`.

## Health endpoint

Endpoint này được bảo vệ bằng header `X-Realtime-Secret` (không chỉ bằng socket token).

```powershell
Invoke-WebRequest http://localhost:3001/health -Headers @{ "X-Realtime-Secret" = $env:REALTIME_INTERNAL_SECRET }
```

`GET /health` không yêu cầu socket token nhưng bắt buộc header `X-Realtime-Secret`. Response gồm số kết nối theo actor, kết nối theo Room, 20 disconnect gần nhất và số lần xác thực thất bại; không trả payload socket.

Superadmin dùng endpoint này trong socket monitoring. Không public endpoint ra Internet nếu không cần.

## Vận hành và xử lý lỗi

```powershell
docker compose logs -f realtime
docker compose restart realtime
Invoke-WebRequest http://localhost:3001/health
```

Nếu socket bị từ chối, kiểm tra khóa HMAC, token còn hạn, `actor_type` và `CORS_ORIGIN`. Nếu internal emit trả `401`, kiểm tra `REALTIME_INTERNAL_SECRET` ở Laravel, Compose và gateway. Nếu gateway không khởi động, kiểm tra lỗi thiếu secret hoặc cổng `3001`.

## CORS, reverse proxy và production

Development:

```dotenv
CORS_ORIGIN=http://localhost:8080
```

Production nên dùng origin HTTPS cụ thể, proxy HTTP upgrade cho Socket.IO và giữ `REALTIME_URL` nội bộ giữa Laravel và gateway. Không dùng `CORS_ORIGIN=*` trên gateway công khai. Rotate secret theo quy trình và restart cả Laravel worker/gateway sau khi thay đổi.

## Tương thích với Laravel

Laravel tạo token qua `src/app/Services/Realtime/SocketTokenService.php` và phát event qua listener `src/app/Listeners/PublishRealtimeEvent.php`. Khi thêm/sửa event hoặc channel, cập nhật đồng thời:

1. allow-list và validation trong `server.js`;
2. listener/payload ở Laravel;
3. client listener trong `src/resources/`;
4. feature test realtime.

Client nên dùng event để cập nhật UI nhưng tải lại dữ liệu từ Laravel khi cần trạng thái chuẩn cuối cùng. Realtime delivery không được làm rollback transaction nghiệp vụ.

Xem thêm [README hệ thống](../README.md) và [tài liệu triển khai](../documents/deploy/README.md).
