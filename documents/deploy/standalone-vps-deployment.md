# Hướng dẫn Triển khai DrinkFlow lên VPS (Cài đặt từng dịch vụ - Native Bare-Metal)

Tài liệu này hướng dẫn chi tiết cách cài đặt và cấu hình từng dịch vụ riêng biệt trực tiếp trên hệ điều hành **Ubuntu 22.04 / 24.04 LTS** mà không sử dụng Docker, nhằm tối ưu hóa 100% hiệu năng phần cứng cho hệ thống **DrinkFlow Enterprise**.

> **Cập nhật:** tài liệu đã được đối chiếu với code hiện tại (nhánh `master`). Các điểm khác so với bản cũ: không chạy `db:seed` đầy đủ trên production, có cấu hình SMTP cho email OTP, cấu hình Chrome cho Food Crawler, bảo mật `.env`/Redis/session, và script deploy có sao lưu DB + chế độ bảo trì.

---

## 1. Danh sách Dịch vụ Cần Cài đặt

1. **Nginx Web Server** (Xử lý HTTPS, Static assets & Reverse Proxy WebSocket).
2. **PHP 8.3 & PHP-FPM** (Chạy ứng dụng Laravel 12 với đầy đủ extensions).
3. **MySQL 8.0/8.4** (Hệ quản trị cơ sở dữ liệu quan hệ tự host).
4. **Redis Server** (Bộ nhớ đệm Session, Cache và Queue Worker).
5. **Node.js 22 LTS & npm** (Xây dựng assets Vite & chạy Socket.IO Gateway).
6. **Supervisor** (Giám sát và duy trì tiến trình chạy ngầm Queue Worker và Socket.IO).
7. **Composer 2** (Trình quản lý thư viện PHP).
8. **Certbot** (Cấp và tự động gia hạn SSL Let's Encrypt).
9. **Google Chrome (stable)** (Chỉ cần nếu dùng tính năng Food Crawler - nhập menu từ ShopeeFood).
10. **SMTP** (Dịch vụ gửi email - bắt buộc để admin nhận mã OTP khi quên mật khẩu).

---

## 2. Chuẩn bị VPS & Cài đặt Các Gói Hệ Thống Cơ Bản

Đăng nhập vào máy chủ bằng SSH với quyền `root` hoặc tài khoản có quyền `sudo`:

```bash
# Cập nhật kho gói APT (không tự động nâng cấp toàn bộ VPS đang hoạt động)
sudo apt update
sudo apt install -y software-properties-common curl wget git unzip htop ufw ca-certificates gnupg lsb-release

# Thiết lập Timezone sang Việt Nam
sudo timedatectl set-timezone Asia/Ho_Chi_Minh

# Tạo 2GB-4GB Swap file chống tràn RAM
sudo fallocate -l 2G /swapfile
sudo chmod 600 /swapfile
sudo mkswap /swapfile
sudo swapon /swapfile
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
```

### 2.1. Nguyên tắc khi VPS đang chạy nhiều website

Không xem VPS là máy chủ mới nếu đã có website đang hoạt động. Trước khi cài đặt,
ghi lại các service, port và virtual host hiện có; không dừng, xóa hoặc thay thế cấu
hình của ứng dụng khác:

```bash
# Kiểm tra các service và port đang sử dụng
sudo systemctl --type=service --state=running
sudo ss -ltnp

# Sao lưu cấu hình Nginx nếu Nginx đã tồn tại
if [ -d /etc/nginx ]; then
    sudo cp -a /etc/nginx /etc/nginx.backup.$(date +%Y%m%d-%H%M%S)
fi
```

Nếu máy đang có lịch bảo trì riêng, không chạy `apt upgrade` trong giờ cao điểm vì
việc nâng cấp có thể làm restart các service hiện hữu. Có thể tách bước nâng cấp ra
khỏi lần triển khai DrinkFlow và thực hiện sau khi đã kiểm tra kế hoạch downtime. Khi
cần nâng cấp, chạy riêng trong maintenance window:

```bash
sudo apt update
sudo apt upgrade -y
```

Không chạy `ufw reset` hoặc mở các port tùy tiện. Chỉ bổ sung rule còn thiếu và giữ
nguyên các rule của website khác:

```bash
sudo ufw status numbered
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
```

MySQL, Redis và Socket.IO phải chỉ lắng nghe trên `127.0.0.1`; không mở các port
3306, 6379 hoặc 3001 ra Internet. Sau khi cài xong, xác nhận bằng `sudo ss -ltnp`
(xem mục 10).

---

## 3. Cài đặt & Cấu hình Từng Dịch vụ

### 3.1. Cài đặt Nginx Web Server

Nếu Nginx đã được cài đặt để phục vụ các website khác, chỉ kiểm tra trạng thái và
không cần cài lại hoặc khởi động lại service:

```bash
if command -v nginx >/dev/null 2>&1; then
    nginx -v
    sudo systemctl status nginx --no-pager
else
    sudo apt install -y nginx
    sudo systemctl enable nginx
    sudo systemctl start nginx
fi
```

---

### 3.2. Cài đặt PHP 8.3 & Các PHP Extensions

Thêm kho lưu trữ PPA Ondřej Surý để cài đặt PHP 8.3 chuẩn:

```bash
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

# Cài đặt PHP 8.3 FPM, CLI và các extension thiết yếu cho Laravel
# gd: captcha + xử lý ảnh | zip/xml: xuất Excel | redis: phpredis | intl, bcmath, mbstring: Laravel
sudo apt install -y php8.3-fpm php8.3-cli php8.3-common \
    php8.3-mysql \
    php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip \
    php8.3-gd php8.3-bcmath php8.3-intl php8.3-redis \
    php8.3-opcache php8.3-readline

# Kiểm tra phiên bản PHP (yêu cầu tối thiểu của ứng dụng: PHP ^8.2)
php -v
```

#### Tối ưu hóa `php.ini` cho Production:
Mở file `/etc/php/8.3/fpm/php.ini`:

```bash
sudo nano /etc/php/8.3/fpm/php.ini
```

Điều chỉnh các thông số sau:

```ini
memory_limit = 256M
upload_max_filesize = 50M
post_max_size = 50M
max_execution_time = 120
date.timezone = Asia/Ho_Chi_Minh
expose_php = Off
cgi.fix_pathinfo = 0

; Bật OPcache
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.revalidate_freq=0
opcache.validate_timestamps=0
```

> **Lưu ý 1:** `opcache.validate_timestamps=0` nghĩa là PHP-FPM **không tự nhận code mới**.
> Sau mỗi lần deploy phải `reload php8.3-fpm` (script ở mục 8 đã làm việc này).
>
> **Lưu ý 2:** Food Crawler gọi Node/Chrome bằng `shell_exec`. Không đưa `shell_exec`
> vào `disable_functions`, nếu không tính năng nhập menu sẽ lỗi.

Khởi động lại PHP-FPM:

```bash
sudo systemctl restart php8.3-fpm
sudo systemctl enable php8.3-fpm
```

---

### 3.3. Cài đặt & Khởi tạo MySQL tự host

Phần này cài MySQL trực tiếp trên VPS, không sử dụng dịch vụ cơ sở dữ liệu managed hoặc
kết nối từ trình duyệt. Gói `mysql-server` của Ubuntu cung cấp MySQL 8.x phù hợp với
Ubuntu 22.04/24.04:

```bash
sudo apt update
sudo apt install -y mysql-server

sudo systemctl enable mysql
sudo systemctl start mysql
sudo systemctl status mysql --no-pager
```

Chạy trình cấu hình bảo mật và chọn mật khẩu root theo yêu cầu của hệ thống:

```bash
sudo mysql_secure_installation
```

Để tránh mở MySQL ra Internet, giữ `bind-address` ở loopback. Kiểm tra file
`/etc/mysql/mysql.conf.d/mysqld.cnf` và bảo đảm có các thiết lập sau:

```ini
[mysqld]
bind-address = 127.0.0.1
character-set-server = utf8mb4
collation-server = utf8mb4_unicode_ci
```

Khởi động lại MySQL sau khi thay đổi cấu hình:

```bash
sudo systemctl restart mysql
```

#### Tạo Database & User riêng cho DrinkFlow

Sinh mật khẩu ngẫu nhiên và lưu vào trình quản lý secrets (không dùng lại mật khẩu mẫu):

```bash
openssl rand -base64 24
```

Đăng nhập MySQL bằng tài khoản quản trị:

```bash
sudo mysql
```

Trong dấu nhắc lệnh `mysql`, thay `MAT_KHAU_DATABASE_THAT_MANH` bằng mật khẩu vừa tạo:

```sql
CREATE DATABASE drinkflow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER 'drinkflow_user'@'127.0.0.1'
    IDENTIFIED BY 'MAT_KHAU_DATABASE_THAT_MANH';

GRANT ALL PRIVILEGES ON drinkflow_prod.* TO 'drinkflow_user'@'127.0.0.1';
FLUSH PRIVILEGES;
EXIT;
```

Tài khoản được giới hạn đúng vào `127.0.0.1`, khớp với `DB_HOST` bên dưới. Không cấp user
với host `%` và không dùng tài khoản `root` cho ứng dụng. Kiểm tra kết nối bằng:

```bash
mysql --host=127.0.0.1 --port=3306 \
    --user=drinkflow_user --password drinkflow_prod
```

Nếu cần truy cập MySQL từ máy quản trị, dùng SSH tunnel thay vì mở cổng 3306 trên Internet.

---

### 3.4. Cài đặt Redis Server (Cache, Session & Queue)

```bash
sudo apt install -y redis-server

# Sinh mật khẩu Redis
openssl rand -hex 24

# Mở file cấu hình redis
sudo nano /etc/redis/redis.conf
```

Chỉnh các dòng sau (thay `MAT_KHAU_REDIS` bằng mật khẩu vừa sinh):

```ini
bind 127.0.0.1 -::1
supervised systemd
requirepass MAT_KHAU_REDIS
maxmemory 256mb
maxmemory-policy volatile-lru
```

> **Vì sao `volatile-lru` thay vì `allkeys-lru`:** Redis này chứa cả **hàng đợi (queue)**,
> **session** và **cache**. `allkeys-lru` có thể xóa nhầm job trong hàng đợi khi hết bộ nhớ
> (mất thông báo, mất tác vụ). `volatile-lru` chỉ loại bỏ các key có TTL (session, cache).

Khởi động lại Redis:

```bash
sudo systemctl restart redis-server
sudo systemctl enable redis-server

# Kiểm tra kết nối
redis-cli -a 'MAT_KHAU_REDIS' ping
# Kết quả trả về: PONG
```

---

### 3.5. Cài đặt Composer 2 & Node.js 22 LTS

```bash
# Cài đặt Composer toàn hệ thống
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer

# Cài đặt Node.js 22 LTS qua NodeSource
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs

# Kiểm tra phiên bản
composer --version
node -v
npm -v
```

---

### 3.6. Cài đặt Google Chrome cho Food Crawler (tùy chọn)

Chỉ cần khi dùng tính năng nhập menu từ ShopeeFood. Trên Ubuntu, gói `chromium-browser`
chỉ là bản **snap** và không chạy ổn định dưới `www-data`, vì vậy dùng Google Chrome:

```bash
wget -q https://dl.google.com/linux/direct/google-chrome-stable_current_amd64.deb -O /tmp/chrome.deb
sudo apt install -y /tmp/chrome.deb
rm /tmp/chrome.deb

google-chrome-stable --version
```

Thư mục profile riêng cho Chrome (`storage/app/chrome-profile`) được tạo ở mục 4.4, sau khi
đã clone mã nguồn (không tạo trước vì `git clone` cần thư mục đích còn trống).
Các biến `FOOD_CRAWLER_*` tương ứng nằm trong `.env` (mục 4.3).

---

## 4. Triển khai Mã Nguồn DrinkFlow

### 4.0. Checklist trước khi deploy (làm trên máy phát triển)

Server chỉ nhận được những gì đã được **commit và push** lên GitHub. Nhánh chính của
repo là **`master`** (không phải `main`).

1. Kiểm tra file chưa được theo dõi bởi Git:

   ```bash
   git status --short
   ```

   Mọi file có dấu `??` mà code đang dùng **phải được `git add` + commit**. Đặc biệt
   `src/app/Http/Middleware/SecurityHeaders.php` được `bootstrap/app.php` tham chiếu:
   nếu thiếu file này trên server, **toàn bộ website sẽ lỗi 500**. Các file mới hiện có
   gồm (không đầy đủ): `src/app/Support/Security/`, `src/app/Exports/Concerns/`,
   `src/app/Enums/PaymentMethod.php`, `src/app/Services/FoodCrawler/Browser/network-guard.cjs`,
   `src/resources/js/shared/escape-html.js`, `src/resources/views/admin/partials/`.

2. Chạy toàn bộ test:

   ```bash
   cd src
   php artisan test
   ```

   Hiện `NormalizeDongSignMigrationTest` thất bại vì thiếu file migration
   `database/migrations/2026_09_20_000000_normalize_dong_sign_in_stored_texts.php`.
   Nếu đây là migration cần chạy trên production, hãy thêm nó vào repo trước khi push;
   nếu không, xử lý test đó trước khi deploy.

3. Push:

   ```bash
   git push origin master
   ```

### 4.1. Clone Source Code & Cấp Quyền

```bash
sudo mkdir -p /var/www/drinkflow
sudo chown -R $USER:www-data /var/www/drinkflow
cd /var/www/drinkflow

# Repo private: dùng deploy key hoặc Personal Access Token khi git hỏi thông tin
git clone https://github.com/trungthk/Drinkflow-New.git .
git checkout master
```

### 4.2. Cài đặt PHP Dependencies & Build Frontend Assets

```bash
cd /var/www/drinkflow/src

# 1. PHP packages cho Production (--no-scripts: bước package:discover chạy bằng www-data ở mục 4.4,
#    vì bootstrap/cache thuộc www-data)
composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# 2. Node packages & build Vite CSS/JS.
#    Không tải Chrome đi kèm puppeteer (dùng Google Chrome đã cài ở mục 3.6)
PUPPETEER_SKIP_DOWNLOAD=1 npm ci
npm run build

# 3. Dependencies cho Realtime Gateway
cd /var/www/drinkflow/realtime
npm ci --omit=dev
```

> `src/public/build/` không được commit, vì vậy **bắt buộc** chạy `npm run build` trên
> server sau mỗi lần thay đổi JS/CSS (script ở mục 8 đã làm).

### 4.3. Cấu hình file `.env` của Laravel

Quay lại thư mục `src/`:

```bash
cd /var/www/drinkflow/src
cp .env.example .env

# Sinh sẵn 2 secret ngẫu nhiên để dán vào .env bên dưới
echo "REALTIME_INTERNAL_SECRET=$(openssl rand -hex 32)"
echo "SOCKET_TOKEN_SECRET=$(openssl rand -hex 32)"

nano .env
```

Cấu hình các thông số (mọi giá trị chứa ký tự đặc biệt như `#`, `$`, khoảng trắng phải đặt trong dấu nháy kép):

```ini
APP_NAME=DrinkFlow
APP_ENV=production          # BẮT BUỘC là production: nếu để "local", captcha đăng nhập admin bị tắt
APP_KEY=
APP_DEBUG=false             # BẮT BUỘC false
APP_URL=https://drinkflow.yourcompany.com
APP_TIMEZONE=Asia/Ho_Chi_Minh
APP_LOCALE=vi

LOG_CHANNEL=daily
LOG_LEVEL=warning

# Kết nối MySQL tự host, chỉ lắng nghe trên máy VPS
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=drinkflow_prod
DB_USERNAME=drinkflow_user
DB_PASSWORD="MAT_KHAU_DATABASE_THAT_MANH"

# Session, Cache & Queue qua Redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD="MAT_KHAU_REDIS"

# Email (BẮT BUỘC: dùng để gửi OTP quên mật khẩu admin. Nếu để MAIL_MAILER=log thì OTP chỉ ghi vào log)
MAIL_MAILER=smtp
MAIL_HOST=smtp.yourprovider.com
MAIL_PORT=587
MAIL_USERNAME=your-smtp-user
MAIL_PASSWORD="your-smtp-password"
MAIL_FROM_ADDRESS=no-reply@yourcompany.com
MAIL_FROM_NAME="${APP_NAME}"

# Captcha (để false; captcha chỉ tự tắt khi APP_ENV=local)
CAPTCHA_DISABLE=false

# Realtime Socket.IO (dùng 2 secret sinh ở trên, KHÔNG dùng lại giá trị mẫu)
REALTIME_URL=http://127.0.0.1:3001
REALTIME_PUBLIC_URL=https://drinkflow.yourcompany.com
REALTIME_INTERNAL_SECRET=DAN_GIA_TRI_openssl_rand_hex_32
SOCKET_TOKEN_SECRET=DAN_GIA_TRI_openssl_rand_hex_32
CORS_ORIGIN=https://drinkflow.yourcompany.com

# Google OAuth
GOOGLE_CLIENT_ID=your-google-client-id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your-google-client-secret
GOOGLE_REDIRECT_URI=https://drinkflow.yourcompany.com/auth/google/callback
# Giới hạn tên miền email được phép thêm vào phòng (phân tách bằng dấu phẩy, để trống = cho phép mọi domain)
GOOGLE_ALLOWED_DOMAINS=yourcompany.com

# Food Crawler (chỉ cần khi dùng nhập menu từ ShopeeFood)
FOOD_CRAWLER_CHROME_PATH=/usr/bin/google-chrome-stable
FOOD_CRAWLER_NODE_BINARY=/usr/bin/node
FOOD_CRAWLER_NPM_BINARY=/usr/bin/npm
FOOD_CRAWLER_HEADLESS=true
FOOD_CRAWLER_USER_DATA_DIR=/var/www/drinkflow/src/storage/app/chrome-profile

# SaaS platform: gói dịch vụ, hoá đơn nền tảng và thanh toán online
PLATFORM_DEFAULT_PACKAGE=starter
PLATFORM_INVOICE_DUE_DAYS=7
PLATFORM_INVOICE_GRACE_DAYS=7
# Chỉ bật khi đã cấu hình cron `platform:enforce-overdue` và muốn tạm khoá tự động
PLATFORM_AUTO_SUSPEND=false
PLATFORM_PAYMENTS_ENABLED=false
PLATFORM_PAYMENTS_CHECKOUT_URL=
PLATFORM_PAYMENTS_WEBHOOK_SECRET=

# Tài khoản chủ nền tảng do `php artisan db:seed` tạo (chỉ chạy ở môi trường không phải production)
# Giá trị đăng nhập không nằm trong mã nguồn: để trống sẽ sinh ngẫu nhiên và in một lần ra console.
PLATFORM_SEED_OWNER_EMAIL=superadmin@drinkflow.local
PLATFORM_SEED_OWNER_NAME="DrinkFlow Superadmin"
PLATFORM_SEED_OWNER_VALUE=
```

Khóa quyền đọc `.env` (chỉ chủ sở hữu và `www-data` được đọc; Realtime Gateway cũng đọc file này):

```bash
chown $USER:www-data /var/www/drinkflow/src/.env
chmod 640 /var/www/drinkflow/src/.env
```

Cập nhật `GOOGLE_REDIRECT_URI` trong Google Cloud Console cho khớp với giá trị trên
(đúng scheme `https`).

### 4.4. Sinh App Key, Chạy Migration & Phân Quyền Thư mục

```bash
cd /var/www/drinkflow/src

# Phân quyền chuẩn cho Web Server
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

# Thư mục profile cho Chrome của Food Crawler (mục 3.6)
sudo -u www-data mkdir -p storage/app/chrome-profile

# Sinh Application Encryption Key (ghi vào .env của user hiện tại)
php artisan key:generate --force

# Từ đây chạy artisan bằng www-data để file cache/log không bị sai chủ sở hữu
sudo -u www-data php artisan package:discover --ansi
sudo -u www-data php artisan migrate --force

# Chỉ nạp dữ liệu tham chiếu (phiên bản hệ thống). KHÔNG chạy `db:seed` đầy đủ trên production
# vì seeder demo tạo tài khoản admin/superadmin với mật khẩu công khai (xem 4.5).
sudo -u www-data php artisan db:seed --class=VersionSeeder --force

# Tạo symlink public/storage (chạy bằng user hiện tại vì thư mục public/ thuộc user này)
php artisan storage:link

# Tối ưu hóa cache Production
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
sudo -u www-data php artisan event:cache
```

> Từ phiên bản này, `DatabaseSeeder` tự bỏ qua dữ liệu demo khi `APP_ENV=production` và
> chỉ gọi `VersionSeeder`. Chạy `db:seed --class=VersionSeeder` vẫn là cách rõ ràng nhất.

### 4.5. Tạo tài khoản Superadmin đầu tiên

Không dùng tài khoản demo trong seeder (`superadmin@drinkflow.local` / `drinkflow2026`).
Tạo tài khoản thật với mật khẩu mạnh (tối thiểu 12 ký tự); mật khẩu được nhập ẩn, không lưu vào lịch sử shell:

```bash
cd /var/www/drinkflow/src

read -rp "Email superadmin: " SA_EMAIL
read -rsp "Mật khẩu (>= 12 ký tự): " SA_PASS; echo

SA_EMAIL="$SA_EMAIL" SA_PASS="$SA_PASS" sudo -E -u www-data php artisan tinker --execute='
App\Models\AdminAccount::create([
    "name" => "Superadmin",
    "email" => strtolower(getenv("SA_EMAIL")),
    "password" => getenv("SA_PASS"),
    "role" => "superadmin",
    "status" => "active",
]);
echo "OK\n";
'

unset SA_EMAIL SA_PASS
```

Sau khi đăng nhập lần đầu tại `/admin/login`, tạo các admin phòng từ trang Superadmin,
và bật xác thực 2 bước (Google Workspace) trong trang hồ sơ admin nếu tổ chức dùng Google Workspace.

---

## 5. Cấu hình Nginx Virtual Host & SSL

### 5.1. Tạo Server Block Nginx

Tạo file `/etc/nginx/sites-available/drinkflow`:

```bash
sudo nano /etc/nginx/sites-available/drinkflow
```

Dán cấu hình chi tiết dưới đây:

```nginx
server {
    listen 80;
    server_name drinkflow.yourcompany.com;
    root /var/www/drinkflow/src/public;

    index index.php index.html;
    charset utf-8;
    client_max_body_size 50M;
    server_tokens off;

    # Gzip Compression (HTML, CSS, JS, JSON, SVG, font). Xem giải thích ở mục 5.1.1.
    gzip on;
    gzip_vary on;                 # Gửi "Vary: Accept-Encoding" để CDN/proxy cache đúng bản nén
    gzip_proxied any;             # Nén cả response khi đứng sau Cloudflare/reverse proxy
    gzip_comp_level 5;            # 5-6 là điểm cân bằng CPU/kích thước
    gzip_min_length 1024;         # File < 1 KB nén không đáng
    gzip_buffers 16 8k;
    gzip_http_version 1.1;
    gzip_disable "msie6";
    # text/html luôn được nén, không cần khai báo. Không nén ảnh jpg/png/webp và woff2 (đã nén sẵn).
    gzip_types
        text/plain
        text/css
        text/xml
        text/javascript
        application/javascript
        application/x-javascript
        application/json
        application/ld+json
        application/manifest+json
        application/xml
        application/rss+xml
        application/atom+xml
        application/vnd.ms-fontobject
        font/ttf
        font/otf
        image/svg+xml
        image/x-icon;

    # 1. Socket.IO Realtime Proxy
    # BẮT BUỘC dùng `^~`: nếu không, khối regex `\.(...|js|...)$` ở mục 4 sẽ được ưu tiên và
    # /socket.io/socket.io.js bị tìm như file tĩnh trong public/ (404). Các request polling/WebSocket
    # không có đuôi .js nên vẫn chạy, chỉ riêng file client bị lỗi.
    location ^~ /socket.io/ {
        proxy_pass http://127.0.0.1:3001;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 86400s;
        proxy_send_timeout 86400s;
    }

    # 2. Main Laravel Routing
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    # 3. PHP-FPM FastCGI Handler
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
        fastcgi_buffer_size 16k;
        fastcgi_buffers 4 16k;
        # Food Crawler mở Chrome trong request, có thể mất > 60s (mặc định của Nginx)
        fastcgi_read_timeout 120s;
    }

    # 4a. Vite build assets: tên file có hash (public-B8mxmMiX.css...), đổi nội dung là đổi tên,
    #     nên cache 1 năm + immutable. `^~` để khối này thắng khối regex 4b bên dưới.
    location ^~ /build/ {
        expires 1y;
        add_header Cache-Control "public, max-age=31536000, immutable";
        add_header X-Content-Type-Options "nosniff" always;
        access_log off;
        try_files $uri =404;
    }

    # 4b. Static Assets Cache (ảnh, favicon... không có hash trong tên)
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|woff2|woff|ttf|svg|webp)$ {
        expires 30d;
        add_header Cache-Control "public, no-transform";
        add_header X-Content-Type-Options "nosniff" always;
        access_log off;
    }

    # Block hidden files (.env, .git)
    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

#### 5.1.1. Bật nén Gzip & cache tài nguyên tĩnh

Khối `gzip` ở trên làm HTML/CSS/JS nhỏ đi 70-85% khi truyền (ví dụ `public.css` 66 KB còn ~12 KB),
giúp giảm FCP/LCP và xử lý cảnh báo Lighthouse *"Document request latency – No compression applied"*.
Khối `location ^~ /build/` xử lý cảnh báo *"Use efficient cache lifetimes"*.

- **Đặt gzip ở cấp `server`** (như trên) để áp dụng cho mọi location, kể cả response PHP-FPM.
  Nếu muốn bật cho toàn bộ website trên VPS thì đặt ở khối `http {}` của `/etc/nginx/nginx.conf`.
  Ubuntu đã có sẵn `gzip on;` trong `nginx.conf`; Nginx báo lỗi `"gzip" directive is duplicate`
  chỉ khi khai báo hai lần **trong cùng một khối**. Khai báo ở `server` sẽ ghi đè cấp `http`, không gây lỗi.
- **Không thêm `gzip_static on;`** trừ khi bước build có sinh file `.gz` cạnh asset (Vite mặc định không sinh).
- Sau khi Certbot thêm khối `listen 443 ssl` (mục 5.2), khối gzip vẫn nằm trong cùng `server` nên HTTPS cũng được nén.
- Nếu website đứng sau **Cloudflare**, Cloudflare tự nén Brotli/Gzip tới trình duyệt; vẫn nên giữ gzip ở Nginx
  để giảm băng thông giữa VPS và Cloudflare.

Kiểm tra sau khi reload Nginx (thay tên file CSS bằng file thật trong `src/public/build/assets/`):

```bash
# HTML trang chủ: kỳ vọng có "content-encoding: gzip" và "vary: Accept-Encoding"
curl -s -o /dev/null -D - -H "Accept-Encoding: gzip" https://drinkflow.yourcompany.com/ | grep -iE "content-encoding|vary"

# Asset Vite: kỳ vọng "content-encoding: gzip" và "cache-control: public, max-age=31536000, immutable"
CSS=$(ls /var/www/drinkflow/src/public/build/assets/public-*.css | head -n1 | xargs -n1 basename)
curl -s -o /dev/null -D - -H "Accept-Encoding: gzip" "https://drinkflow.yourcompany.com/build/assets/$CSS" \
  | grep -iE "content-encoding|cache-control"
```

> **Đang chạy bản cấu hình cũ (dùng `location /socket.io/` không có `^~`)?** Trình duyệt sẽ báo
> `GET https://<domain>/socket.io/socket.io.js 404`. Sửa trực tiếp trên VPS (chạy cho cả file đã được
> Certbot thêm khối HTTPS, sed thay mọi vị trí):
>
> ```bash
> sudo sed -i 's|location /socket.io/ {|location ^~ /socket.io/ {|' /etc/nginx/sites-available/drinkflow
> sudo nginx -t && sudo systemctl reload nginx
> curl -sI https://drinkflow.yourcompany.com/socket.io/socket.io.js | head -n3   # kỳ vọng: HTTP 200, content-type javascript
> ```

> Các header bảo mật của trang (`X-Frame-Options`, `Content-Security-Policy: frame-ancestors`,
> `Referrer-Policy`, `Permissions-Policy`, HSTS khi chạy HTTPS) do **ứng dụng Laravel** gắn vào
> (middleware `SecurityHeaders`), không cần thêm trong Nginx.
> Nếu đặt website sau Cloudflare hoặc reverse proxy khác, cần cấu hình Trusted Proxies của
> Laravel để IP client (dùng cho rate limit) và scheme HTTPS được nhận đúng.

Kích hoạt site:

```bash
sudo ln -sf /etc/nginx/sites-available/drinkflow /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

Không xóa `/etc/nginx/sites-enabled/default` hoặc các symlink của website khác. Nếu
Nginx báo lỗi, không reload và không sửa trực tiếp các site đang hoạt động; khôi phục
file DrinkFlow vừa thay đổi rồi chạy lại `sudo nginx -t`. `reload` được dùng thay cho
`restart` để Nginx nạp cấu hình mới mà không ngắt các kết nối hiện tại.

### 5.2. Cài đặt SSL Let's Encrypt bằng Certbot

Trỏ bản ghi DNS `A` của `drinkflow.yourcompany.com` về IP VPS trước khi chạy:

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d drinkflow.yourcompany.com

# Kiểm tra tự động gia hạn
sudo certbot renew --dry-run
```

> `SESSION_SECURE_COOKIE=true` yêu cầu website chạy HTTPS; nếu chưa cấp SSL thì không thể đăng nhập.

---

## 6. Cấu hình Supervisor Quản lý Tiến trình Chạy ngầm

Supervisor sẽ tự động duy trì và khởi động lại **Queue Worker** và **Socket.IO Realtime Gateway** khi máy chủ khởi động lại hoặc khi tiến trình gặp sự cố.

### 6.1. Cài đặt Supervisor

```bash
sudo apt install -y supervisor
sudo systemctl enable supervisor
sudo systemctl start supervisor
```

### 6.2. Cấu hình Laravel Queue Worker

Tạo file cấu hình `/etc/supervisor/conf.d/drinkflow-worker.conf`:

```bash
sudo nano /etc/supervisor/conf.d/drinkflow-worker.conf
```

Nội dung:

```ini
[program:drinkflow-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/drinkflow/src/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/drinkflow/src/storage/logs/worker.log
stopwaitsecs=3600
```

### 6.3. Cấu hình Node.js Socket.IO Realtime Server

Tạo file cấu hình `/etc/supervisor/conf.d/drinkflow-realtime.conf`:

```bash
sudo nano /etc/supervisor/conf.d/drinkflow-realtime.conf
```

Nội dung:

```ini
[program:drinkflow-realtime]
directory=/var/www/drinkflow/realtime
command=node server.js
autostart=true
autorestart=true
user=www-data
environment=NODE_ENV="production",PORT="3001",HOST="127.0.0.1"
redirect_stderr=true
stdout_logfile=/var/www/drinkflow/src/storage/logs/realtime.log
```

`HOST="127.0.0.1"` bắt buộc để gateway chỉ lắng nghe nội bộ (mặc định là mọi interface).
Gateway tự đọc `realtime/.env`, sau đó `src/.env` (các biến `SOCKET_TOKEN_SECRET`,
`REALTIME_INTERNAL_SECRET`, `CORS_ORIGIN`), nên `www-data` phải đọc được `src/.env` (đã cấp ở mục 4.3).

### 6.4. Nạp và Khởi chạy Supervisor:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
```

---

## 7. Cấu hình Cronjob cho Laravel Task Scheduler

Mở crontab của user `www-data`:

```bash
sudo crontab -u www-data -e
```

Thêm dòng sau vào cuối:

```cron
* * * * * php /var/www/drinkflow/src/artisan schedule:run >> /dev/null 2>&1
```

Kiểm tra danh sách tác vụ đã lên lịch:

```bash
sudo -u www-data php /var/www/drinkflow/src/artisan schedule:list
```

---

## 8. Script Cập nhật Mã Nguồn Định kỳ (`deploy-native.sh`)

Tạo file `/var/www/drinkflow/deploy-native.sh`:

```bash
nano /var/www/drinkflow/deploy-native.sh
```

Nội dung script (sao lưu DB trước khi migrate, bật chế độ bảo trì trong lúc cập nhật,
chỉ khởi động lại các tiến trình của DrinkFlow để không ảnh hưởng website khác trên VPS):

```bash
#!/usr/bin/env bash
set -Eeuo pipefail

APP_DIR=/var/www/drinkflow
BRANCH=master
DB_NAME=drinkflow_prod
BACKUP_DIR=/var/backups/drinkflow

artisan() { sudo -u www-data php "$APP_DIR/src/artisan" "$@"; }

on_error() {
    echo "❌ Deploy thất bại ở dòng $1. Website ĐANG Ở CHẾ ĐỘ BẢO TRÌ."
    echo "   Sửa lỗi rồi chạy lại script, hoặc rollback (xem mục 11) rồi chạy: sudo -u www-data php $APP_DIR/src/artisan up"
}
trap 'on_error $LINENO' ERR

cd "$APP_DIR"

echo "💾 [1/9] Sao lưu cơ sở dữ liệu..."
sudo mkdir -p "$BACKUP_DIR"
sudo mysqldump --single-transaction --routines "$DB_NAME" | gzip \
    | sudo tee "$BACKUP_DIR/pre-deploy-$(date +%Y%m%d-%H%M%S).sql.gz" > /dev/null

echo "🚧 [2/9] Bật chế độ bảo trì..."
artisan down --retry=60 || true

echo "🚀 [3/9] Kéo mã nguồn mới nhất (nhánh $BRANCH)..."
git fetch origin "$BRANCH"
git pull --ff-only origin "$BRANCH"

echo "📦 [4/9] Cài đặt Composer Dependencies..."
cd "$APP_DIR/src"
composer install --no-dev --optimize-autoloader --no-interaction --no-scripts
artisan package:discover --ansi

echo "🎨 [5/9] Build Assets Vite & Realtime dependencies..."
PUPPETEER_SKIP_DOWNLOAD=1 npm ci
npm run build
(cd "$APP_DIR/realtime" && npm ci --omit=dev)

echo "🗄️ [6/9] Chạy Database Migrations..."
artisan migrate --force

echo "⚡ [7/9] Làm mới Cache..."
artisan optimize:clear
artisan config:cache
artisan route:cache
artisan view:cache
artisan event:cache

echo "🔄 [8/9] Khởi động lại Worker, Realtime & PHP-FPM..."
artisan queue:restart
sudo supervisorctl restart drinkflow-realtime
sudo supervisorctl restart 'drinkflow-worker:*'
# BẮT BUỘC: opcache.validate_timestamps=0 nên PHP-FPM chỉ nhận code, route và config mới sau khi reload.
# Thiếu bước này, web vẫn chạy bản cũ (ví dụ /captcha/api/contact trả 404) dù `php artisan route:list` đã đúng.
sudo systemctl reload php8.3-fpm
sudo systemctl is-active --quiet php8.3-fpm
sudo systemctl reload nginx

echo "✅ [9/9] Tắt bảo trì & kiểm tra..."
artisan up
# Từ đây website ĐÃ online: lỗi ở các bước kiểm tra bên dưới chỉ là cảnh báo, không được báo "đang bảo trì".
trap - ERR
APP_URL=$(grep -E '^APP_URL=' "$APP_DIR/src/.env" | head -n1 | cut -d= -f2- | tr -d '"')
# --http1.1: tránh lỗi "curl: (16) Error in the HTTP2 framing layer" khi VPS tự gọi domain của chính nó (xem mục 8.2).
# --retry: chờ PHP-FPM/Nginx nạp xong sau khi reload thay vì báo lỗi ngay.
CURL_CHECK=(curl --http1.1 -fsS --retry 5 --retry-delay 3 --retry-connrefused --max-time 20 -o /dev/null)
"${CURL_CHECK[@]}" -w "Health check /up: HTTP %{http_code}\n" "$APP_URL/up" \
    || echo "⚠️ Health check /up không đạt (website đã bật, chỉ kiểm tra thất bại). Xem mục 8.2."
# Cảnh báo (không dừng deploy) nếu web chưa phục vụ captcha; thường do chưa reload FPM hoặc CAPTCHA_DISABLE=true
"${CURL_CHECK[@]}" -w "Captcha /captcha/api/contact: HTTP %{http_code}\n" "$APP_URL/captcha/api/contact" \
    || echo "⚠️ Captcha không phản hồi. Kiểm tra: reload php8.3-fpm, CAPTCHA_DISABLE trong .env / biến môi trường FPM."

echo "🎉 Cập nhật thành công!"
```

Cấp quyền:

```bash
chmod +x /var/www/drinkflow/deploy-native.sh
```

Mỗi khi muốn deploy code mới (sau khi đã push lên `master`), chỉ cần chạy:

```bash
/var/www/drinkflow/deploy-native.sh
```

> Script không dùng `supervisorctl restart all` vì lệnh đó sẽ khởi động lại **cả các
> chương trình của website khác** trên cùng VPS.
> `git pull --ff-only` sẽ dừng nếu server có thay đổi cục bộ; không sửa trực tiếp mã nguồn trên server.

### 8.1. Nâng cấp script `deploy-native.sh` đã tạo từ trước

Script đã có sẵn trên VPS **không tự cập nhật** theo tài liệu này. Kiểm tra xem nó đã có bước reload PHP-FPM chưa:

```bash
grep -n "reload php8.3-fpm" /var/www/drinkflow/deploy-native.sh
```

Nếu không in ra dòng nào, thêm bước reload ngay sau dòng khởi động lại worker:

```bash
sudo sed -i "/supervisorctl restart 'drinkflow-worker:\*'/a sudo systemctl reload php8.3-fpm" /var/www/drinkflow/deploy-native.sh
grep -n "reload php8.3-fpm" /var/www/drinkflow/deploy-native.sh   # phải thấy đúng 1 dòng
```

Nếu tài khoản deploy phải nhập mật khẩu khi chạy `sudo`, script sẽ treo hoặc dừng tại bước này. Cách đơn giản nhất là
chạy cả script bằng `sudo /var/www/drinkflow/deploy-native.sh`. Nếu muốn chạy bằng user thường, cần cho phép **mọi** lệnh `sudo`
trong script (`systemctl`, `supervisorctl`, `mysqldump`, `mkdir`, `tee`, `-u www-data`) chạy không hỏi mật khẩu. Ví dụ dưới đây
chỉ mới phần reload PHP-FPM/Nginx (thay `deploy` bằng user thật; kiểm tra đường dẫn bằng `which systemctl`):

```bash
echo 'deploy ALL=(root) NOPASSWD: /usr/bin/systemctl reload php8.3-fpm, /usr/bin/systemctl reload nginx, /usr/bin/systemctl is-active --quiet php8.3-fpm' \
    | sudo tee /etc/sudoers.d/drinkflow-deploy
sudo chmod 440 /etc/sudoers.d/drinkflow-deploy
sudo visudo -cf /etc/sudoers.d/drinkflow-deploy
```

### 8.2. Xử lý lỗi `curl: (16) Error in the HTTP2 framing layer` ở bước kiểm tra cuối

Triệu chứng khi chạy `deploy-native.sh`:

```text
curl: (16) Error in the HTTP2 framing layer
Health check /up: HTTP 000
❌ Deploy thất bại ở dòng NN. Website ĐANG Ở CHẾ ĐỘ BẢO TRÌ.
```

Đây là lỗi của lệnh `curl` health check (VPS gọi HTTPS tới chính domain của nó qua HTTP/2), **không phải lỗi ứng dụng**.
Vì `artisan up` đã chạy ngay trước đó nên website thường **đã online**; thông báo "đang bảo trì" chỉ đúng nếu lỗi xảy ra ở các bước trước `artisan up`.

**Bước 1: xác nhận website đang online (không cần chạy lại deploy):**

```bash
curl --http1.1 -sS -o /dev/null -w "HTTP %{http_code}\n" https://drinkflow.yourcompany.com/up
# HTTP 200: website đã online. Nếu HTTP 503: đang bảo trì, bật lại bằng lệnh dưới
sudo -u www-data php /var/www/drinkflow/src/artisan up
```

**Bước 2: cập nhật script trên VPS** bằng khối cuối `[9/9]` ở mục 8 (`trap - ERR`, `--http1.1`, `--retry`, kiểm tra chỉ cảnh báo).
Tìm vị trí cần sửa:

```bash
grep -n "Health check\|artisan up" /var/www/drinkflow/deploy-native.sh
nano /var/www/drinkflow/deploy-native.sh
```

**Bước 3 (tùy chọn): tìm nguyên nhân gốc của lỗi HTTP/2** để kiểm tra bằng HTTP/2 trong tương lai:

```bash
curl --version | head -n1
curl -sv --http2   -o /dev/null https://drinkflow.yourcompany.com/up 2>&1 | tail -n 25
curl -sv --http1.1 -o /dev/null https://drinkflow.yourcompany.com/up 2>&1 | tail -n 25   # nếu chỉ HTTP/1.1 chạy được: lỗi nằm ở HTTP/2
sudo nginx -T 2>/dev/null | grep -nE "listen .*(ssl|http2)|http2"
sudo tail -n 30 /var/log/nginx/error.log
# Gọi thẳng Nginx trên máy, bỏ qua DNS/Cloudflare, để loại trừ lỗi từ tầng ngoài:
curl --http1.1 -sk --resolve drinkflow.yourcompany.com:443:127.0.0.1 -o /dev/null -w "local HTTP %{http_code}\n" https://drinkflow.yourcompany.com/up
```

Cách đọc kết quả:

| Kết quả | Nguyên nhân thường gặp |
|---|---|
| `--http1.1` OK, `--http2` lỗi | `curl` cũ hoặc Nginx/proxy xử lý HTTP/2 lỗi. Giữ `--http1.1` trong script; cập nhật `curl`/Nginx nếu cần dùng HTTP/2 |
| Gọi `--resolve ... 127.0.0.1` OK nhưng gọi qua domain lỗi | Lỗi ở tầng ngoài (Cloudflare/proxy/hairpin NAT của VPS), không phải Nginx của bạn |
| Cả hai đều lỗi, log Nginx có `upstream ... too big header` hoặc `connect() failed` | PHP-FPM chưa sẵn sàng hoặc header phản hồi quá lớn (tăng `fastcgi_buffer_size`) |

---

## 9. Sao lưu Định kỳ

Sao lưu hằng ngày cơ sở dữ liệu và ảnh người dùng tải lên (`storage/app/public`):

```bash
sudo mkdir -p /var/backups/drinkflow
sudo crontab -e
```

```cron
30 2 * * * mysqldump --single-transaction --routines drinkflow_prod | gzip > /var/backups/drinkflow/db-$(date +\%Y\%m\%d).sql.gz
40 2 * * * tar -czf /var/backups/drinkflow/uploads-$(date +\%Y\%m\%d).tar.gz -C /var/www/drinkflow/src/storage/app public
50 2 * * * find /var/backups/drinkflow -type f -mtime +14 -delete
```

Nên đồng bộ thư mục `/var/backups/drinkflow` sang nơi lưu trữ ngoài VPS, và thử khôi phục định kỳ.

---

## 10. Kiểm tra sau khi Deploy

Chạy lần lượt và đối chiếu kết quả mong đợi:

```bash
# 1. Các cổng nội bộ chỉ nghe trên 127.0.0.1 (3306, 6379, 3001), không xuất hiện 0.0.0.0
sudo ss -ltnp | grep -E ':(3306|6379|3001)\b'

# 2. Tiến trình nền đang RUNNING
sudo supervisorctl status

# 3. Realtime Gateway phản hồi nội bộ
curl -s http://127.0.0.1:3001/health

# 3b. File client Socket.IO đi qua Nginx tới Realtime (kỳ vọng HTTP 200). Nếu 404: thiếu `^~` ở location /socket.io/
curl -sI https://drinkflow.yourcompany.com/socket.io/socket.io.js | head -n1

# 4. Header bảo mật do ứng dụng gắn (kỳ vọng: X-Frame-Options, Content-Security-Policy,
#    X-Content-Type-Options, Referrer-Policy và Strict-Transport-Security)
curl -sI https://drinkflow.yourcompany.com/admin/login | grep -iE 'x-frame|content-security|nosniff|referrer|strict-transport'

# 5. Không còn tài khoản demo (kỳ vọng in ra 0)
sudo -u www-data php /var/www/drinkflow/src/artisan tinker --execute='echo App\Models\AdminAccount::where("email", "like", "%@drinkflow.local")->count();'

# 6. Gửi thử email (thay địa chỉ nhận) - cần thiết để OTP quên mật khẩu hoạt động
sudo -u www-data php /var/www/drinkflow/src/artisan tinker --execute='Mail::raw("DrinkFlow mail test", fn ($m) => $m->to("you@yourcompany.com")->subject("DrinkFlow mail test")); echo "sent";'

# 6b. Route captcha do web (PHP-FPM) phục vụ (kỳ vọng HTTP 200). Nếu 404 dù `route:list` đúng: chưa reload php8.3-fpm
curl -sI https://drinkflow.yourcompany.com/captcha/api/contact | head -n1

# 7. Chrome chạy được dưới www-data (chỉ khi dùng Food Crawler; kỳ vọng in ra <html>...)
sudo -u www-data google-chrome-stable --headless=new --no-sandbox \
    --user-data-dir=/var/www/drinkflow/src/storage/app/chrome-profile --dump-dom about:blank
```

Kiểm tra thủ công trên trình duyệt:

1. Mở `/admin/login`: có hiển thị captcha (nếu không, `APP_ENV` đang là `local`).
2. Đăng nhập superadmin, mở một phòng, thực hiện một thao tác ghi (ví dụ đổi trạng thái order).
   Nếu gặp lỗi `419 Page Expired`, tải lại trang; CSRF hiện đã được bắt buộc cho toàn bộ
   khu vực admin.
3. Mở DevTools > Network > WS: kết nối `/socket.io/` thành công (mã 101).
4. Thử "Quên mật khẩu" với email admin thật: email OTP về hộp thư.
5. Thử nhập menu từ link ShopeeFood (Food Crawler). Link phải thuộc `shopeefood.vn`;
   máy chủ chỉ crawl qua HTTPS và chặn mọi địa chỉ mạng nội bộ.
6. Kênh thông báo Slack/Webhook chỉ chấp nhận URL **HTTPS công khai** (Slack: `hooks.slack.com`).
   Kênh đã lưu trước đó trỏ tới địa chỉ nội bộ hoặc `http://` sẽ không gửi được và cần cập nhật lại.

> **Lưu ý khi nâng cấp từ bản cũ:** cấu hình mặc định mới bật mã hóa session
> (`SESSION_ENCRYPT`), nên mọi người dùng sẽ bị đăng xuất một lần sau khi deploy.
> Đổi hoặc reset mật khẩu admin cũng đăng xuất các phiên khác của admin đó.

---

## 11. Rollback khi Deploy Lỗi

```bash
cd /var/www/drinkflow

# 1. Quay về commit trước đó (xem git log để chọn)
git log --oneline -5
git checkout <commit-truoc-do>

# 2. Cài lại dependencies & build tương ứng với commit đó (các bước 4-5 trong script deploy)

# 3. Nếu migration mới đã chạy và cần hoàn tác dữ liệu: khôi phục DB từ bản sao lưu pre-deploy
sudo -u www-data php src/artisan down
gunzip -c /var/backups/drinkflow/pre-deploy-YYYYMMDD-HHMMSS.sql.gz | sudo mysql drinkflow_prod

# 4. Làm mới cache, khởi động lại tiến trình, tắt bảo trì
sudo -u www-data php src/artisan optimize:clear
sudo supervisorctl restart drinkflow-realtime 'drinkflow-worker:*'
sudo systemctl reload php8.3-fpm
sudo -u www-data php src/artisan up
```

Sau khi xử lý xong, quay lại nhánh làm việc bằng `git checkout master`.
