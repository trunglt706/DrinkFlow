# Sao lưu & Di chuyển DrinkFlow sang VPS khác / Domain / Subdomain (Cloudflare DNS)

Tài liệu này hướng dẫn chuyển toàn bộ hệ thống DrinkFlow đang chạy trên VPS hiện tại (gọi là **VPS cũ**) sang một VPS khác (**VPS mới**), giữ nguyên hoặc đổi sang domain/subdomain mới quản lý DNS trên **Cloudflare**, sao cho:

- **Không mất dữ liệu** (RPO = 0): mọi ghi dữ liệu trên VPS cũ được đóng băng trước lần sao lưu cuối cùng.
- **Gián đoạn ngắn** (RTO ~ 5–15 phút): chỉ trong khoảng thời gian dump/restore lần cuối và đổi DNS.
- **Có đường lùi**: VPS cũ được giữ nguyên để rollback.

Tài liệu áp dụng cho mô hình triển khai [Standalone VPS](./standalone-vps-deployment.md) (Nginx + PHP-FPM + MySQL + Redis + Supervisor + Node.js realtime, thư mục `/var/www/drinkflow`). Thay `drinkflow_prod`, `drinkflow_user`, domain mẫu bằng giá trị thật.

> Không ghi mật khẩu, `APP_KEY`, secret vào tài liệu, ticket hay chat. Chỉ truyền chúng giữa 2 VPS qua SSH.

---

## 1. Chọn kịch bản

| Kịch bản | Ví dụ | Điểm khác biệt |
| :--- | :--- | :--- |
| **A. Đổi VPS, giữ domain** | `drinkflow.yourcompany.com` trỏ từ IP cũ sang IP mới | Chỉ đổi bản ghi A trên Cloudflare. Người dùng không cần làm gì, phiên đăng nhập (Redis) mất nên phải đăng nhập lại. |
| **B. Đổi VPS + domain mới** | `drinkflow.yourcompany.com` → `drinkflow.newcompany.vn` | Như A, thêm cập nhật URL trong `.env`, Google OAuth, webhook ngân hàng/thanh toán, link đã lưu trong DB, chuyển hướng 301 domain cũ. |
| **C. Đổi VPS + subdomain mới** | `drinkflow.yourcompany.com` → `order.yourcompany.com` | Như B nhưng cùng zone Cloudflare; chuyển hướng 301 bằng Redirect Rule trong cùng zone. |
| **D. VPS dự phòng (warm standby)** | `standby.yourcompany.com` luôn có bản sao mới nhất | Đồng bộ định kỳ (mục 9). Khi VPS chính sự cố, thực hiện kịch bản A với dữ liệu từ lần đồng bộ gần nhất. |

### 1.1. Runbook nhanh – Kịch bản C (đổi VPS + subdomain mới cùng zone Cloudflare)

Khai báo một lần trong phiên SSH (trên cả 2 VPS) rồi dùng lại ở các lệnh bên dưới:

```bash
export OLD_HOST=drinkflow.yourcompany.com     # Subdomain đang chạy
export NEW_HOST=order.yourcompany.com         # Subdomain mới
export NEW_IP=IP_VPS_MOI
```

| # | Thời điểm | Việc cần làm | Mục |
| :-: | :--- | :--- | :--- |
| 1 | T-3 ngày | Ghi commit đang chạy, tạo SSH key chuyển dữ liệu | 3 |
| 2 | T-3 ngày | Dựng VPS mới cùng commit, copy `.env` (giữ `APP_KEY`), Nginx `server_name $NEW_HOST`, Supervisor/cron **chưa bật** | 4.1, 4.2 |
| 3 | T-3 ngày | Cloudflare: thêm bản ghi `A` **`order` → `$NEW_IP`, Proxied**; SSL Full (strict); bật WebSockets; cấp chứng chỉ cho `$NEW_HOST` (Origin Certificate `*.yourcompany.com` hoặc certbot DNS-01) | 4.3 |
| 4 | T-3 ngày | Sửa `.env` VPS mới: `APP_URL`, `REALTIME_PUBLIC_URL`, `CORS_ORIGIN`, `GOOGLE_REDIRECT_URI` sang `https://$NEW_HOST`; `TRUSTED_PROXIES` = dải IP Cloudflare | 7.1, 4.3 |
| 5 | T-3 ngày | Google OAuth: **thêm** redirect URI + JS origin của `$NEW_HOST` (giữ URI cũ) | 7.2 |
| 6 | T-2 ngày | Diễn tập: dump + rsync sang VPS mới, mở `https://$NEW_HOST` kiểm tra; đo thời gian | 5 |
| 7 | Giờ G | VPS cũ: `artisan down`, tắt cron, chạy hết queue, dừng worker | 6.2 |
| 8 | Giờ G | Dump cuối + rsync `storage/app` → VPS mới; restore, `migrate --force`, `optimize` | 6.3, 6.4 |
| 9 | Giờ G | Đổi link đã lưu: `REPLACE(link, 'https://$OLD_HOST', 'https://$NEW_HOST')` | 7.3 |
| 10 | Giờ G | VPS mới: bật Supervisor + cron, chạy checklist mục 8, rồi `artisan up` | 6.5, 8 |
| 11 | Giờ G | Cloudflare: Redirect Rule 301 `$OLD_HOST` → `https://$NEW_HOST` (bản ghi `drinkflow` giữ **Proxied**, đổi IP sang `$NEW_IP` để không phụ thuộc VPS cũ) | 7.4 |
| 12 | Giờ G | Đổi URL webhook ngân hàng/thanh toán sang `$NEW_HOST` tại nhà cung cấp (webhook POST không theo 301) | 7.2 |
| 13 | G+7 ngày | Dọn dẹp, huỷ VPS cũ; giữ Redirect Rule lâu dài cho bookmark cũ | 11 |

Lệnh của bước 9 với biến ở trên:

```bash
sudo mysql drinkflow_prod -e "UPDATE user_notifications
  SET link = REPLACE(link, 'https://${OLD_HOST}', 'https://${NEW_HOST}')
  WHERE link LIKE 'https://${OLD_HOST}%';"
```

> Kịch bản C không cần chờ DNS lan truyền: `$NEW_HOST` đã trỏ sẵn về VPS mới từ bước 3, việc "chuyển" thực chất là tắt VPS cũ + bật redirect. Người dùng phải đăng nhập lại vì cookie phiên gắn với subdomain cũ.

---

## 2. Những gì phải sao lưu

| Thành phần | Vị trí trên VPS cũ | Bắt buộc | Ghi chú |
| :--- | :--- | :---: | :--- |
| Database MySQL | `drinkflow_prod` | ✅ | Toàn bộ nghiệp vụ, kể cả bảng `jobs` (queue `database` chứa sự kiện realtime), thống kê, thông báo. |
| Font PDF | `/var/www/drinkflow/src/storage/fonts` | Nếu có | Không nằm trong Git (font Nhật cho hoá đơn PDF, cache font DOMPDF). |
| `src/.env` | `/var/www/drinkflow/src/.env` | ✅ | **Giữ nguyên `APP_KEY`**: cấu hình kênh thông báo, tích hợp ngân hàng, 2FA... được mã hoá bằng khoá này. Đổi `APP_KEY` = mất dữ liệu đã mã hoá. |
| `realtime/.env` | `/var/www/drinkflow/realtime/.env` | Nếu có | Gateway Socket.IO đọc file này trước `src/.env`. |
| File người dùng tải lên | `/var/www/drinkflow/src/storage/app` | ✅ | Gồm `public/` (ảnh menu, avatar, QR...) và `private/`. Bỏ qua `chrome-profile/` nếu muốn (tự tạo lại). |
| Cấu hình Nginx | `/etc/nginx/sites-available/drinkflow*` | ✅ | Đổi `server_name` nếu đổi domain. |
| Supervisor | `/etc/supervisor/conf.d/drinkflow-*.conf` | ✅ | Worker queue + realtime. |
| Crontab | `crontab -l -u www-data` (hoặc user đang chạy scheduler) | ✅ | Dòng `schedule:run`, các dòng backup. |
| `php.ini` đã tối ưu | `/etc/php/8.3/fpm/conf.d/` | Nên | Theo mục 3.2 của tài liệu Standalone. |
| Chứng chỉ SSL | `/etc/letsencrypt` | ❌ | Cấp mới trên VPS mới (mục 4.3) thay vì copy. |
| Redis | session, cache, queue `redis` | ❌ | Không chuyển. Queue phải được **chạy hết** trước khi chuyển (mục 6.2). Người dùng đăng nhập lại sau khi chuyển. |
| Mã nguồn | Git | ❌ | Clone lại đúng commit đang chạy trên VPS cũ (`git rev-parse HEAD`). |

---

## 3. Chuẩn bị trên VPS cũ (không gián đoạn)

```bash
cd /var/www/drinkflow
git rev-parse HEAD                      # Ghi lại commit đang chạy
git status --short                      # Phải sạch; nếu có sửa tay trên server, commit/ghi chú lại
php -v && mysql --version && node -v    # VPS mới nên cài cùng phiên bản chính

# Dung lượng cần chuyển
sudo du -sh /var/www/drinkflow/src/storage/app
sudo mysql -e "SELECT table_schema, ROUND(SUM(data_length+index_length)/1024/1024,1) AS MB
               FROM information_schema.tables WHERE table_schema='drinkflow_prod' GROUP BY table_schema;"
```

Cho phép VPS cũ SSH sang VPS mới bằng key (chỉ dùng trong quá trình chuyển, gỡ sau khi xong):

```bash
# Trên VPS cũ
ssh-keygen -t ed25519 -f ~/.ssh/drinkflow_migrate -N ""
ssh-copy-id -i ~/.ssh/drinkflow_migrate.pub deploy@IP_VPS_MOI
```

---

## 4. Dựng VPS mới

### 4.1. Cài dịch vụ & mã nguồn

Làm theo [standalone-vps-deployment.md](./standalone-vps-deployment.md) mục **2 → 4.2** và **6** (Nginx, PHP 8.3, MySQL, Redis, Composer, Node.js, Supervisor), với các khác biệt:

```bash
cd /var/www/drinkflow
git checkout <COMMIT_DANG_CHAY_TREN_VPS_CU>   # Cùng phiên bản code với VPS cũ
```

- Tạo database `drinkflow_prod` và user MySQL như mục 3.3 (mật khẩu có thể mới).
- **KHÔNG** chạy `php artisan key:generate`, **KHÔNG** chạy `db:seed`, **KHÔNG** tạo superadmin mới: dữ liệu sẽ được khôi phục từ VPS cũ.
- Chưa bật Supervisor và cron cho tới bước cutover (mục 6.5), tránh 2 server cùng gửi thông báo/chạy scheduler.

### 4.2. Copy `.env` và cấu hình hệ thống

```bash
# Trên VPS cũ
scp -i ~/.ssh/drinkflow_migrate /var/www/drinkflow/src/.env deploy@IP_VPS_MOI:/tmp/drinkflow-src.env
scp -i ~/.ssh/drinkflow_migrate /var/www/drinkflow/realtime/.env deploy@IP_VPS_MOI:/tmp/drinkflow-realtime.env 2>/dev/null || true
sudo tar -czf /tmp/drinkflow-sysconf.tgz /etc/nginx/sites-available/drinkflow* /etc/supervisor/conf.d/drinkflow-*.conf
scp -i ~/.ssh/drinkflow_migrate /tmp/drinkflow-sysconf.tgz deploy@IP_VPS_MOI:/tmp/
sudo crontab -l -u www-data > /tmp/drinkflow-cron.txt; scp -i ~/.ssh/drinkflow_migrate /tmp/drinkflow-cron.txt deploy@IP_VPS_MOI:/tmp/
```

```bash
# Trên VPS mới
sudo install -o $USER -g www-data -m 640 /tmp/drinkflow-src.env /var/www/drinkflow/src/.env
[ -f /tmp/drinkflow-realtime.env ] && sudo install -o $USER -g www-data -m 640 /tmp/drinkflow-realtime.env /var/www/drinkflow/realtime/.env
sudo tar -xzf /tmp/drinkflow-sysconf.tgz -C /
sudo ln -sf /etc/nginx/sites-available/drinkflow /etc/nginx/sites-enabled/drinkflow
rm -f /tmp/drinkflow-*.env                       # Không để secret nằm lại trong /tmp
```

Sửa `src/.env` trên VPS mới:

- `DB_PASSWORD`, `REDIS_PASSWORD`: theo mật khẩu đã đặt trên VPS mới.
- **Giữ nguyên** `APP_KEY`, `SOCKET_TOKEN_SECRET`, `REALTIME_INTERNAL_SECRET`.
- Kịch bản B/C: cập nhật thêm các URL ở mục 7.1.

### 4.3. Cloudflare & SSL cho VPS mới

1. **Bản ghi kiểm thử**: trong Cloudflare → *DNS* → *Records*, thêm bản ghi `A` tạm, ví dụ `new-drinkflow` → `IP_VPS_MOI`, **Proxied** (đám mây cam). Thêm `new-drinkflow.yourcompany.com` vào `server_name` của Nginx trên VPS mới.
2. **SSL/TLS** → *Overview*: chế độ **Full (strict)** (không dùng *Flexible*: gây vòng lặp redirect HTTPS và cookie `Secure` lỗi).
3. **Network**: bật **WebSockets** (bắt buộc cho `/socket.io/`).
4. **Chứng chỉ trên VPS mới** – chọn một:
   - **Cloudflare Origin Certificate** (khuyến nghị khi luôn bật proxy): *SSL/TLS* → *Origin Server* → *Create certificate* cho `yourcompany.com, *.yourcompany.com`, lưu vào `/etc/ssl/cloudflare/drinkflow.pem` và `.key` (quyền `600`), trỏ `ssl_certificate`/`ssl_certificate_key` của Nginx tới đó.
   - **Let's Encrypt qua DNS-01** (hoạt động cả khi domain chính vẫn đang trỏ về VPS cũ):

     ```bash
     sudo apt install -y python3-certbot-dns-cloudflare
     sudo install -m 600 /dev/null /root/.cloudflare.ini
     # Token Cloudflare chỉ có quyền Zone:DNS:Edit cho zone này
     echo "dns_cloudflare_api_token = <TOKEN>" | sudo tee /root/.cloudflare.ini > /dev/null
     sudo certbot certonly --dns-cloudflare --dns-cloudflare-credentials /root/.cloudflare.ini \
          -d drinkflow.yourcompany.com -d new-drinkflow.yourcompany.com
     ```

5. **IP thật của người dùng**: khi bật proxy Cloudflare, Laravel chỉ thấy IP của Cloudflare → rate limit và allowlist/blocklist IP của Room sai. Cấu hình `TRUSTED_PROXIES` bằng đúng dải IP Cloudflare theo [trusted-proxies.md](./trusted-proxies.md) (có lệnh sinh sẵn giá trị), **không** dùng `*`. Tùy chọn: UFW chỉ cho dải IP Cloudflare vào cổng 80/443 để ẩn IP gốc của VPS.

```bash
sudo nginx -t && sudo systemctl reload nginx
```

---

## 5. Diễn tập (không gián đoạn VPS cũ)

Mục đích: đo thời gian dump/restore và phát hiện lỗi trước ngày chuyển thật.

```bash
# Trên VPS cũ: dump nhất quán, không khoá bảng InnoDB
sudo mysqldump --single-transaction --quick --routines --triggers --events \
     --default-character-set=utf8mb4 --set-gtid-purged=OFF drinkflow_prod \
  | gzip | ssh -i ~/.ssh/drinkflow_migrate deploy@IP_VPS_MOI "cat > /tmp/drinkflow-rehearsal.sql.gz"

# Đồng bộ file tải lên (chạy lại bao nhiêu lần cũng được, chỉ copy phần thay đổi)
sudo rsync -aHAX --delete --numeric-ids -e "ssh -i $HOME/.ssh/drinkflow_migrate" \
     --exclude 'chrome-profile/' \
     /var/www/drinkflow/src/storage/app/ deploy@IP_VPS_MOI:/var/www/drinkflow/src/storage/app/
```

> Bỏ `--set-gtid-purged=OFF` nếu MySQL báo không nhận tuỳ chọn (MariaDB).

```bash
# Trên VPS mới
gunzip -c /tmp/drinkflow-rehearsal.sql.gz | sudo mysql drinkflow_prod
cd /var/www/drinkflow/src
sudo chown -R www-data:www-data storage bootstrap/cache
sudo -u www-data php artisan migrate --force        # Không có migration mới nếu cùng commit
sudo -u www-data php artisan optimize:clear && sudo -u www-data php artisan optimize
php artisan storage:link
```

Mở `https://new-drinkflow.yourcompany.com` (thêm tạm domain này vào `APP_URL`/`CORS_ORIGIN` nếu cần đăng nhập thử), kiểm tra đăng nhập, ảnh, dashboard, trang admin. **Không** tạo đơn/chiến dịch thật trên bản diễn tập; dữ liệu này sẽ bị ghi đè ở bước cutover.

Ghi lại thời gian dump + restore: đó là thời gian bảo trì tối thiểu.

---

## 6. Cutover – chuyển thật (gián đoạn ngắn)

Chọn khung giờ ít người dùng, **không** trùng các mốc scheduler quan trọng. Giờ scheduler là **UTC** (xem `php artisan schedule:list`), ví dụ 00:15 UTC ngày 1 hằng tháng (= 07:15 giờ Việt Nam): thống kê & thông báo top 3 nhà tài trợ; 01:30–01:45 UTC: xử lý hoá đơn quá hạn; tháng 1: thông báo tổng kết năm. Các thông báo này được ghi nhận trong DB nên không gửi lặp, nhưng nếu chúng rơi vào lúc bảo trì sẽ chạy trễ tới khi VPS mới bật cron.

### 6.1. T-24h: hạ TTL

Với bản ghi **Proxied**, Cloudflare đổi IP gần như tức thì (TTL Auto), không cần hạ TTL. Nếu bản ghi đang **DNS only** (đám mây xám), đặt TTL = 1 phút trước 24 giờ.

### 6.2. Đóng băng ghi dữ liệu trên VPS cũ

```bash
cd /var/www/drinkflow/src

# 1. Bật bảo trì: người dùng thấy trang 503, quản trị viên vẫn vào được qua URL bí mật
sudo -u www-data php artisan down --retry=60 --secret="<CHUOI_BI_MAT_TAM_THOI>"

# 2. Tắt scheduler: comment dòng `schedule:run` (tránh chạy song song với VPS mới)
sudo crontab -e -u www-data

# 3. Chạy hết queue còn tồn rồi dừng worker
sudo -u www-data php artisan queue:work redis --stop-when-empty
sudo -u www-data php artisan queue:work database --stop-when-empty
sudo supervisorctl stop 'drinkflow-worker:*' 'drinkflow-worker-db:*'
sudo -u www-data php artisan queue:failed           # Ghi nhận job lỗi (nếu có) để xử lý lại trên VPS mới
```

Webhook ngân hàng/thanh toán gửi tới VPS cũ trong lúc bảo trì sẽ nhận 503; các cổng thanh toán thường gửi lại sau. Kiểm tra lại giao dịch trong khoảng thời gian bảo trì ở mục 8.

### 6.3. Sao lưu lần cuối & chuyển sang VPS mới

```bash
# Trên VPS cũ
STAMP=$(date +%Y%m%d-%H%M%S)
sudo mkdir -p /var/backups/drinkflow
sudo mysqldump --single-transaction --quick --routines --triggers --events \
     --default-character-set=utf8mb4 --set-gtid-purged=OFF drinkflow_prod \
  | gzip | sudo tee /var/backups/drinkflow/cutover-$STAMP.sql.gz > /dev/null
sha256sum /var/backups/drinkflow/cutover-$STAMP.sql.gz

scp -i ~/.ssh/drinkflow_migrate /var/backups/drinkflow/cutover-$STAMP.sql.gz deploy@IP_VPS_MOI:/tmp/
sudo rsync -aHAX --delete --numeric-ids -e "ssh -i $HOME/.ssh/drinkflow_migrate" \
     --exclude 'chrome-profile/' \
     /var/www/drinkflow/src/storage/app/ deploy@IP_VPS_MOI:/var/www/drinkflow/src/storage/app/
sudo rsync -a --numeric-ids -e "ssh -i $HOME/.ssh/drinkflow_migrate" \
     /var/www/drinkflow/src/storage/fonts/ deploy@IP_VPS_MOI:/var/www/drinkflow/src/storage/fonts/
```

### 6.4. Khôi phục trên VPS mới

```bash
sha256sum /tmp/cutover-*.sql.gz                    # Phải trùng với VPS cũ

# Xoá dữ liệu diễn tập rồi nạp bản cuối
sudo mysql -e "DROP DATABASE drinkflow_prod; CREATE DATABASE drinkflow_prod CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
               GRANT ALL PRIVILEGES ON drinkflow_prod.* TO 'drinkflow_user'@'127.0.0.1'; FLUSH PRIVILEGES;"
gunzip -c /tmp/cutover-*.sql.gz | sudo mysql drinkflow_prod

cd /var/www/drinkflow/src
sudo chown -R www-data:www-data storage bootstrap/cache
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan optimize:clear
sudo -u www-data php artisan optimize

# So khớp số bản ghi một vài bảng chính giữa 2 VPS (chạy cùng lệnh trên VPS cũ)
sudo mysql drinkflow_prod -e "SELECT (SELECT COUNT(*) FROM orders) orders, (SELECT COUNT(*) FROM campaigns) campaigns,
                                     (SELECT COUNT(*) FROM debts) debts, (SELECT COUNT(*) FROM user_notifications) notifications;"
```

Kịch bản B/C: thực hiện thêm mục 7 trước khi tiếp tục.

### 6.5. Bật tiến trình nền & scheduler trên VPS mới

```bash
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl start all && sudo supervisorctl status
sudo crontab -u www-data /tmp/drinkflow-cron.txt && sudo crontab -l -u www-data
```

### 6.6. Đổi DNS trên Cloudflare

Cloudflare → *DNS* → *Records* → bản ghi `drinkflow` (hoặc domain/subdomain mới) → đổi **IPv4 address** thành `IP_VPS_MOI` → *Save*. Nếu có bản ghi `AAAA` (IPv6) trỏ VPS cũ, sửa hoặc xoá theo.

```bash
# Xác nhận traffic đã vào VPS mới (theo dõi access log khi mở website)
sudo tail -f /var/log/nginx/access.log
```

Khi đã kiểm tra xong mục 8:

```bash
cd /var/www/drinkflow/src && sudo -u www-data php artisan up
```

**VPS cũ giữ nguyên trạng thái bảo trì** (không `php artisan up`) ít nhất 7 ngày để có thể rollback, sau đó mới huỷ.

---

## 7. Bổ sung cho kịch bản đổi domain / subdomain (B, C)

### 7.1. `.env` trên VPS mới

```ini
APP_URL=https://order.yourcompany.com
REALTIME_PUBLIC_URL=https://order.yourcompany.com
CORS_ORIGIN=https://order.yourcompany.com
GOOGLE_REDIRECT_URI=https://order.yourcompany.com/auth/google/callback
# Nếu đang đặt SESSION_DOMAIN, đổi theo domain mới hoặc để trống
```

```bash
cd /var/www/drinkflow/src
sudo -u www-data php artisan optimize:clear && sudo -u www-data php artisan optimize
sudo supervisorctl restart drinkflow-realtime     # Gateway đọc CORS_ORIGIN lúc khởi động
```

Nginx: thay `server_name` thành domain mới, cấp chứng chỉ cho domain mới (mục 4.3), `sudo nginx -t && sudo systemctl reload nginx`.

### 7.2. Dịch vụ bên ngoài

- **Google Cloud Console** → OAuth Client: thêm `https://order.yourcompany.com/auth/google/callback` vào *Authorized redirect URIs* và domain mới vào *Authorized JavaScript origins* (giữ URI cũ tới khi chuyển xong).
- **Webhook ngân hàng/thanh toán**: cập nhật URL tại nhà cung cấp sang domain mới. Đường dẫn (gồm token của từng tích hợp) xem tại trang cấu hình tích hợp ngân hàng của Room hoặc bằng `php artisan route:list --name=webhook`.
- **Kênh thông báo** (Telegram/Discord/Slack/webhook) không cần đổi: cấu hình nằm trong DB, đã được giải mã bằng `APP_KEY` cũ.

### 7.3. Link tuyệt đối đã lưu trong DB

Thông báo của người dùng lưu link đầy đủ (`user_notifications.link`). Sau khi khôi phục DB, đổi domain trong các link cũ:

```bash
sudo mysql drinkflow_prod -e "UPDATE user_notifications
  SET link = REPLACE(link, 'https://drinkflow.yourcompany.com', 'https://order.yourcompany.com')
  WHERE link LIKE 'https://drinkflow.yourcompany.com%';"
```

### 7.4. Chuyển hướng domain cũ sang domain mới

Giữ bản ghi DNS của domain cũ ở trạng thái **Proxied** (có thể trỏ tạm về `IP_VPS_MOI`) và tạo trong zone của domain cũ: *Rules* → *Redirect Rules* → *Create rule*:

- **If**: Hostname equals `drinkflow.yourcompany.com`
- **Then**: Dynamic redirect, expression `concat("https://order.yourcompany.com", http.request.uri.path)`, giữ query string, status **301**.

Bookmark, link trong email/Chatwork cũ sẽ tự chuyển sang domain mới. **Ngoại lệ**: webhook POST không theo 301 → bắt buộc đổi URL tại nhà cung cấp (mục 7.2).

---

## 8. Kiểm tra sau khi chuyển

```bash
cd /var/www/drinkflow/src
sudo supervisorctl status                                    # drinkflow-worker, drinkflow-worker-db, drinkflow-realtime RUNNING
curl -sI https://order.yourcompany.com | head -n 5           # 200 (hoặc 503 khi vẫn đang bảo trì)
curl -s -o /dev/null -w "%{http_code}\n" "https://order.yourcompany.com/socket.io/?EIO=4&transport=polling"  # 200
sudo -u www-data php artisan schedule:list                   # Scheduler nhận đủ lệnh
sudo -u www-data php artisan migrate:status | tail -n 5      # Không còn migration Pending
sudo -u www-data php artisan queue:failed
tail -n 50 storage/logs/laravel-$(date +%F).log
```

Kiểm tra thủ công:

- [ ] Đăng nhập Google (user) và đăng nhập admin/superadmin (có captcha, OTP email).
- [ ] Ảnh menu, avatar, QR thanh toán hiển thị (storage đã chuyển, `storage:link` đã tạo).
- [ ] Mở 2 trình duyệt cùng Room: tạo đơn ở một bên, bên kia cập nhật realtime (socket qua Cloudflare).
- [ ] Gửi thử một kênh thông báo từ trang cấu hình kênh của Room (xác nhận `APP_KEY` đúng: nếu sai sẽ lỗi giải mã).
- [ ] Đối soát giao dịch ngân hàng/thanh toán phát sinh trong khung giờ bảo trì.
- [ ] Allowlist/blocklist IP của Room nhận đúng IP người dùng (mục 4.3 bước 5).
- [ ] Số bản ghi các bảng chính khớp giữa 2 VPS (mục 6.4).

---

## 9. Sao lưu định kỳ sang VPS dự phòng (kịch bản D)

Mục 9 của tài liệu Standalone chỉ sao lưu trên cùng VPS; nếu VPS hỏng, bản sao lưu mất theo. Đẩy bản sao lưu sang VPS dự phòng hằng đêm:

```bash
sudo nano /usr/local/bin/drinkflow-offsite-backup.sh
```

```bash
#!/usr/bin/env bash
set -euo pipefail
STAMP=$(date +%Y%m%d-%H%M%S)
DIR=/var/backups/drinkflow
REMOTE=deploy@IP_VPS_DU_PHONG
KEY=/root/.ssh/drinkflow_migrate
mkdir -p "$DIR"

mysqldump --single-transaction --quick --routines --triggers --events \
  --default-character-set=utf8mb4 drinkflow_prod | gzip > "$DIR/db-$STAMP.sql.gz"
tar -czf "$DIR/env-$STAMP.tgz" -C /var/www/drinkflow src/.env $( [ -f /var/www/drinkflow/realtime/.env ] && echo realtime/.env )
chmod 600 "$DIR"/*

rsync -a -e "ssh -i $KEY" "$DIR/" "$REMOTE:/var/backups/drinkflow/"
rsync -aHAX --delete -e "ssh -i $KEY" --exclude 'chrome-profile/' \
  /var/www/drinkflow/src/storage/app/ "$REMOTE:/var/backups/drinkflow/storage-app/"
rsync -a --delete -e "ssh -i $KEY" \
  /var/www/drinkflow/src/storage/fonts/ "$REMOTE:/var/backups/drinkflow/storage-fonts/"

find "$DIR" -type f -mtime +14 -delete
```

```bash
sudo chmod 700 /usr/local/bin/drinkflow-offsite-backup.sh
sudo crontab -e     # root
# 15 3 * * * /usr/local/bin/drinkflow-offsite-backup.sh >> /var/log/drinkflow-offsite-backup.log 2>&1
```

- Trên VPS dự phòng: thư mục `/var/backups/drinkflow` thuộc user `deploy`, quyền `700`; file `env-*.tgz` chứa `APP_KEY`, tuyệt đối không công khai.
- **Diễn tập khôi phục mỗi tháng** trên VPS dự phòng theo mục 5 (bản ghi `standby.yourcompany.com` trên Cloudflare). Bản sao lưu chưa từng khôi phục thử thì chưa được coi là bản sao lưu.
- Khi VPS chính sự cố: trên VPS dự phòng nạp bản `db-*.sql.gz` mới nhất + `storage-app/`, rồi làm mục 6.4 → 6.6. Dữ liệu phát sinh sau lần sao lưu cuối (tối đa ~24h) sẽ mất; muốn giảm, tăng tần suất cron (ví dụ mỗi giờ) hoặc dùng MySQL replication.

---

## 10. Rollback

Chỉ rollback khi VPS mới lỗi **và** chưa có dữ liệu mới quan trọng ghi trên VPS mới:

1. Cloudflare: đổi bản ghi A về `IP_VPS_CU` (kịch bản B/C: tắt Redirect Rule).
2. Trên VPS mới: `php artisan down`, dừng Supervisor, comment cron.
3. Trên VPS cũ: bỏ comment cron, `sudo supervisorctl start all`, `php artisan up`.

Nếu VPS mới đã nhận dữ liệu mới: dump DB + rsync `storage/app` từ VPS mới về VPS cũ theo đúng mục 6.2 → 6.4 (đảo chiều), không chỉ đổi DNS.

---

## 11. Dọn dẹp sau khi ổn định (sau 7 ngày)

- [ ] Xoá khoá `~/.ssh/drinkflow_migrate` khỏi VPS cũ và dòng tương ứng trong `~/.ssh/authorized_keys` của VPS mới (trừ khi dùng cho mục 9).
- [ ] Xoá `/tmp/cutover-*.sql.gz`, `/tmp/drinkflow-*` trên cả 2 VPS.
- [ ] Xoá bản ghi DNS tạm `new-drinkflow` trên Cloudflare và khỏi `server_name` Nginx.
- [ ] Thu hồi API token Cloudflare tạm (nếu chỉ dùng để cấp chứng chỉ một lần).
- [ ] Xoá redirect URI cũ trong Google OAuth (kịch bản B/C), khi chắc chắn không còn dùng.
- [ ] Huỷ VPS cũ sau khi lưu lại bản `cutover-*.sql.gz` ở nơi lưu trữ an toàn.
