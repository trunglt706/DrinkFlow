# Cấu hình trusted proxy

Laravel lấy IP người dùng (dùng cho rate limit, allowlist/blocklist IP của Room) từ `REMOTE_ADDR`. Chỉ khi `REMOTE_ADDR` thuộc danh sách `TRUSTED_PROXIES` (đọc qua `config/trustedproxy.php`), Laravel mới tin header `X-Forwarded-For` / `X-Forwarded-Proto` do proxy đó gửi.

## Trường hợp 1: Nginx + PHP-FPM trên cùng VPS, không có CDN (DNS only)

Nginx chuyển request sang PHP-FPM qua FastCGI, `REMOTE_ADDR` đã là IP thật của người dùng. Giữ trống:

```env
TRUSTED_PROXIES=
```

Header `X-Forwarded-*` do client tự gửi sẽ bị bỏ qua.

## Trường hợp 2: Bật proxy Cloudflare (đám mây cam)

Mọi request tới VPS đều đi từ IP của Cloudflare. Nếu để trống, mọi người dùng bị xem là cùng vài IP Cloudflare: rate limit dùng chung và allowlist/blocklist IP của Room sai. Khai báo đúng các dải IP Cloudflare (danh sách chính thức: <https://www.cloudflare.com/ips-v4> và <https://www.cloudflare.com/ips-v6>):

```bash
# Sinh giá trị TRUSTED_PROXIES từ danh sách hiện hành của Cloudflare
echo "TRUSTED_PROXIES=$( (curl -fsS https://www.cloudflare.com/ips-v4; echo; curl -fsS https://www.cloudflare.com/ips-v6) | grep -v '^$' | paste -sd, -)"
```

Dán dòng in ra vào `src/.env`. Cloudflare thỉnh thoảng bổ sung dải IP: kiểm tra lại danh sách vài tháng một lần.

Request đi thẳng vào IP VPS (không qua Cloudflare) có `REMOTE_ADDR` không thuộc danh sách nên header giả mạo vẫn bị bỏ qua. Có thể chặn hẳn truy cập trực tiếp bằng UFW (chỉ cho dải IP Cloudflare vào cổng 80/443) để ẩn IP gốc của VPS.

## Quy tắc chung

- Không đặt `TRUSTED_PROXIES=*` trên production: client có thể giả mạo `X-Forwarded-For` để vượt quy tắc IP của Room.
- Nếu sau này thêm load balancer/proxy khác, chỉ thêm đúng IP/CIDR của proxy đó, phân tách bằng dấu phẩy.
- Sau khi thay đổi:

```bash
cd /var/www/drinkflow/src
sudo -u www-data php artisan config:clear
sudo -u www-data php artisan config:cache
sudo systemctl reload php8.3-fpm
```
