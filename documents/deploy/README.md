# Hướng dẫn Triển khai DrinkFlow lên Máy chủ VPS (Deployment Guides)

Thư mục này chứa hướng dẫn chính thức để đưa DrinkFlow lên VPS production. DrinkFlow chạy **trực tiếp trên hệ điều hành** (không dùng Docker) với **MySQL** là database duy nhất, theo quy định trong [AGENTS.md](../../AGENTS.md).

---

## 📑 Danh mục Tài liệu

| Tài liệu | Nội dung |
| :--- | :--- |
| 🛠️ [**Standalone VPS Deployment**](./standalone-vps-deployment.md) | Cài đặt từ đầu trên Ubuntu 22.04/24.04: Nginx, PHP 8.3-FPM, MySQL 8, Redis, Node.js 22, Supervisor, Certbot; cấu hình `.env`, worker, scheduler, script deploy, sao lưu, rollback. |
| 🔁 [**Sao lưu & Di chuyển VPS / Domain**](./vps-migration-backup.md) | Chuyển hệ thống sang VPS khác, domain hoặc subdomain mới (Cloudflare DNS) không mất dữ liệu; sao lưu định kỳ sang VPS dự phòng; rollback. |
| 🌐 [**Trusted Proxies**](./trusted-proxies.md) | Cấu hình `TRUSTED_PROXIES` khi chạy trực tiếp hoặc sau Cloudflare để IP người dùng được nhận đúng. |

> `docker-compose.yml` ở thư mục gốc repo là cấu hình cũ dùng PostgreSQL, **không** dùng cho production và không được hỗ trợ.

---

## 💻 Yêu cầu Phần cứng Tối thiểu

| Quy mô sử dụng | CPU | RAM | Ổ cứng SSD/NVMe | Băng thông mạng |
| :--- | :--- | :--- | :--- | :--- |
| **Demo / Văn phòng nhỏ** (< 50 users, 2-5 rooms) | 1 vCPU | 2 GB RAM (kèm 2GB Swap) | 25 GB SSD | 100 Mbps |
| **Doanh nghiệp tiêu chuẩn** (50 - 300 users, 10-20 rooms) | 2 vCPU | 4 GB RAM (kèm 4GB Swap) | 50 GB SSD/NVMe | 1 Gbps |
| **Doanh nghiệp lớn** (> 300 users, realtime cao) | 4 vCPU | 8 GB RAM | 100 GB NVMe | 1 Gbps |

Bật Food Crawler (Google Chrome headless) cần thêm khoảng 0,5–1 GB RAM khi đang nhập menu.

---

## 🌐 Sơ đồ Cổng Mạng & Tường lửa (UFW)

```text
INTERNET / USERS  (tùy chọn: qua Cloudflare proxy)
       │
       ├──► Port 80 (HTTP)  ───► Nginx: chuyển hướng sang HTTPS
       ├──► Port 443 (HTTPS) ──► Nginx (SSL/TLS)
       │                              │
       │                              ├──► PHP-FPM qua unix socket /var/run/php/php8.3-fpm.sock (Laravel)
       │                              └──► 127.0.0.1:3001 Socket.IO Realtime Gateway (/socket.io/)
       │
       └──► Port 22 (SSH)   ───► Quản trị viên (chỉ SSH Key)

Nội bộ (chỉ 127.0.0.1): MySQL 3306 · Redis 6379 · Realtime 3001
Supervisor: drinkflow-worker (queue redis) · drinkflow-worker-db (queue database: sự kiện realtime) · drinkflow-realtime
Cron: schedule:run mỗi phút (giờ lịch chạy theo UTC)
```

| Cổng | Giao thức | Phạm vi | Mục đích |
| :--- | :--- | :--- | :--- |
| `22` (hoặc custom) | TCP | Public (giới hạn IP nếu có) | SSH quản trị |
| `80` | TCP | Public | HTTP (tự động redirect sang 443) |
| `443` | TCP | Public | HTTPS (web + WebSocket WSS) |
| `3306` | TCP | **Chỉ localhost** | MySQL |
| `3001` | TCP | **Chỉ localhost** (Nginx proxy vào) | Socket.IO Realtime Gateway |
| `6379` | TCP | **Chỉ localhost** | Redis (session, cache, queue) |

---

## 🔒 Checklist Trước khi Go-Live

- [ ] `APP_ENV=production` và `APP_DEBUG=false` trong `src/.env`; quyền file `640`, chủ `$USER:www-data`.
- [ ] Khóa đăng nhập SSH bằng mật khẩu và root; chỉ dùng SSH Key.
- [ ] UFW chỉ mở cổng 22, 80, 443; MySQL/Redis/Realtime chỉ nghe `127.0.0.1`.
- [ ] Chứng chỉ SSL hoạt động (Let's Encrypt hoặc Cloudflare Origin Certificate với chế độ *Full (strict)*).
- [ ] Đứng sau Cloudflare proxy: đã cấu hình `TRUSTED_PROXIES` theo [trusted-proxies.md](./trusted-proxies.md) và bật WebSockets.
- [ ] Google OAuth có đúng Redirect URI: `https://your-domain.com/auth/google/callback`.
- [ ] Đã chạy `php artisan db:seed --force` (danh mục quyền, role `full`) và tạo Superadmin thật (không có tài khoản `@drinkflow.local`).
- [ ] Supervisor chạy đủ 3 chương trình: `drinkflow-worker`, `drinkflow-worker-db`, `drinkflow-realtime`.
- [ ] Cron `schedule:run` cho `www-data`; `php artisan schedule:list` hiện đủ tác vụ.
- [ ] SMTP gửi được email (OTP quên mật khẩu).
- [ ] Đã chạy `php artisan config:cache`, `route:cache`, `view:cache`, `event:cache`.
- [ ] Sao lưu hằng ngày DB + `storage/app` + `storage/fonts` + `.env`, có bản sao **ngoài VPS** và đã diễn tập khôi phục.
- [ ] Toàn bộ test PASS trên máy phát triển trước khi push:

  ```bash
  cd src
  php artisan test
  ```
