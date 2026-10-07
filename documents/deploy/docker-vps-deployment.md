# Hướng dẫn Triển khai DrinkFlow lên VPS bằng Docker & Docker Compose

Tài liệu này hướng dẫn chi tiết từ A-Z cách cài đặt, cấu hình, bảo mật và vận hành hệ thống **DrinkFlow** trên máy chủ ảo VPS (Ubuntu 22.04 / 24.04 LTS) bằng Docker Compose.

---

## 1. Kiến trúc Container Triển khai

Hệ thống DrinkFlow được đóng gói thành các dịch vụ độc lập kết nối qua mạng nội bộ Docker:

```text
[ Browser / Client ]
         │ (HTTPS / WSS)
         ▼
[ Nginx Reverse Proxy trên Host ] (Cổng 80/443 + SSL Let's Encrypt)
         │
         ├──► [ drinkflow-app ] (Port 8080) ──► Laravel 12 + PHP 8.3 FPM / CLI
         │                                            │
         │                                            └──► [ drinkflow-postgres ] (Port 5432)
         │
         └──► [ drinkflow-realtime ] (Port 3001) ──► Node.js Socket.IO Gateway
```

---

## 2. Chuẩn bị VPS & Cài đặt Môi trường ban đầu

### Bước 2.1: Cập nhật hệ thống & Tạo Swap File
Đăng nhập vào VPS với quyền `root`:

```bash
# Cập nhật các gói phần mềm
sudo apt update && sudo apt upgrade -y
sudo apt install -y curl wget git ufw unzip htop ca-certificates gnupg lsb-release

# Tạo 2GB Swap file (khuyến nghị cho VPS RAM 2GB-4GB)
sudo fallocate -l 2G /swapfile
sudo chmod 600 /swapfile
sudo mkswap /swapfile
sudo swapon /swapfile
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
```

### Bước 2.2: Cài đặt Docker Engine & Docker Compose Plugin (Official Repository)

```bash
# Thêm GPG key của Docker
sudo install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg
sudo chmod a+r /etc/apt/keyrings/docker.gpg

# Thêm kho Docker vào APT sources
echo \
  "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu \
  $(. /etc/os-release && echo "$VERSION_CODENAME") stable" | \
  sudo tee /etc/apt/sources.list.d/docker.list > /dev/null

# Cài đặt Docker Engine, CLI, Containerd và Docker Compose
sudo apt update
sudo apt install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

# Bật Docker khởi động cùng hệ thống
sudo systemctl enable docker
sudo systemctl start docker

# Kiểm tra phiên bản
docker --version
docker compose version
```

### Bước 2.3: Cấu hình Tường lửa (UFW)

```bash
# Cho phép SSH, HTTP, HTTPS
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# Kích hoạt UFW
sudo ufw enable
sudo ufw status
```

---

## 3. Clone Mã Nguồn & Cấu hình Ứng dụng

### Bước 3.1: Đưa mã nguồn lên VPS
Tạo thư mục làm việc tại `/var/www/drinkflow`:

```bash
sudo mkdir -p /var/www/drinkflow
sudo chown -R $USER:$USER /var/www/drinkflow
cd /var/www/drinkflow

# Clone repository Drinkflow
git clone https://github.com/your-username/Drinkflow-New.git .
```

### Bước 3.2: Thiết lập File Cấu hình Môi trường `.env`

Sao chép file `.env.example` sang `.env` trong thư mục `src/`:

```bash
cp src/.env.example src/.env
nano src/.env
```

Điền các thông số quan trọng cho Production:

```ini
APP_NAME=DrinkFlow
APP_ENV=production
APP_KEY=base64:YOUR_APP_KEY_HERE
APP_DEBUG=false
APP_URL=https://drinkflow.yourcompany.com
APP_TIMEZONE=Asia/Ho_Chi_Minh
APP_LOCALE=vi
APP_FALLBACK_LOCALE=en

LOG_CHANNEL=daily
LOG_LEVEL=error

# Cấu hình Cơ sở dữ liệu PostgreSQL Container
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=drinkflow_prod
DB_USERNAME=drinkflow_user
DB_PASSWORD=SuperStrongPassword_123456!

# Session & Cache qua Database
SESSION_DRIVER=database
SESSION_LIFETIME=10080
CACHE_STORE=database
QUEUE_CONNECTION=database

# Realtime Socket.IO Gateway
REALTIME_URL=http://realtime:3001
REALTIME_PUBLIC_URL=https://drinkflow.yourcompany.com
REALTIME_INTERNAL_SECRET=DrinkFlowSecret_Prod_Key_99999
SOCKET_TOKEN_SECRET=SocketTokenSecret_Prod_Key_88888
CORS_ORIGIN=https://drinkflow.yourcompany.com

# Google OAuth (Tạo trên Google Cloud Console)
GOOGLE_CLIENT_ID=your-google-client-id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your-google-client-secret
GOOGLE_REDIRECT_URI=https://drinkflow.yourcompany.com/auth/google/callback

# SaaS platform: gói dịch vụ, hoá đơn nền tảng và thanh toán online
PLATFORM_DEFAULT_PACKAGE=starter
PLATFORM_INVOICE_DUE_DAYS=7
PLATFORM_INVOICE_GRACE_DAYS=7
# Chỉ bật khi cron `platform:enforce-overdue` đã chạy và muốn tạm khoá tự động
PLATFORM_AUTO_SUSPEND=false
PLATFORM_PAYMENTS_ENABLED=false
PLATFORM_PAYMENTS_CHECKOUT_URL=
PLATFORM_PAYMENTS_WEBHOOK_SECRET=
# Tài khoản chủ nền tảng do seeder tạo, CHỈ ở môi trường không phải production.
# PLATFORM_SEED_OWNER_VALUE để trống: seeder sinh ngẫu nhiên và in một lần ra console.
# PLATFORM_SEED_OWNER_EMAIL=superadmin@drinkflow.local
# PLATFORM_SEED_OWNER_NAME="DrinkFlow Superadmin"
# PLATFORM_SEED_OWNER_VALUE=
```

### Bước 3.3: Tinh chỉnh `docker-compose.yml` cho Production

Mở file `docker-compose.yml` ở thư mục gốc:

```yaml
services:
  postgres:
    image: postgres:16-alpine
    environment:
      POSTGRES_DB: ${POSTGRES_DB:-drinkflow_prod}
      POSTGRES_USER: ${POSTGRES_USER:-drinkflow_user}
      POSTGRES_PASSWORD: ${POSTGRES_PASSWORD:-SuperStrongPassword_123456!}
    volumes:
      - postgres_data:/var/lib/postgresql/data
    healthcheck:
      test: [ "CMD-SHELL", "pg_isready -U ${POSTGRES_USER:-drinkflow_user} -d ${POSTGRES_DB:-drinkflow_prod}" ]
      interval: 5s
      timeout: 5s
      retries: 12
    restart: always

  app:
    build:
      context: .
      dockerfile: Dockerfile
    ports:
      - "127.0.0.1:8080:8000"
    volumes:
      - ./src/storage:/var/www/html/storage
    env_file:
      - ./src/.env
    depends_on:
      postgres:
        condition: service_healthy
    restart: always

  realtime:
    image: node:22-alpine
    env_file:
      - ./src/.env
    working_dir: /app
    volumes:
      - ./realtime:/app
    command: sh -c "npm install --production --no-audit --no-fund && npm start"
    ports:
      - "127.0.0.1:3001:3001"
    restart: always

volumes:
  postgres_data:
```

> **Lưu ý bảo mật**: `ports` của app và realtime được bind vào `127.0.0.1` (`127.0.0.1:8080:8000`, `127.0.0.1:3001:3001`) để chỉ Nginx Reverse Proxy trên Host mới có thể truy cập, không mở trực tiếp ra internet.

---

## 4. Cài đặt Nginx Reverse Proxy & SSL (Let's Encrypt) trên Host

### Bước 4.1: Cài đặt Nginx & Certbot

```bash
sudo apt install -y nginx certbot python3-certbot-nginx
```

### Bước 4.2: Cấu hình Nginx Virtual Host

Tạo file cấu hình `/etc/nginx/sites-available/drinkflow`:

```bash
sudo nano /etc/nginx/sites-available/drinkflow
```

Dán nội dung cấu hình sau (thay `drinkflow.yourcompany.com` bằng tên miền thật):

```nginx
server {
    listen 80;
    server_name drinkflow.yourcompany.com;

    # File upload limits
    client_max_body_size 50M;

    # Gzip Compression (HTML luôn được nén; không nén ảnh jpg/png/webp và woff2 vì đã nén sẵn).
    # `gzip_proxied any` bắt buộc ở đây vì mọi response đều đi qua proxy_pass tới container.
    gzip on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 5;
    gzip_min_length 1024;
    gzip_buffers 16 8k;
    gzip_http_version 1.1;
    gzip_types text/plain text/css text/xml text/javascript application/javascript application/x-javascript application/json application/ld+json application/manifest+json application/xml application/rss+xml application/atom+xml application/vnd.ms-fontobject font/ttf font/otf image/svg+xml image/x-icon;

    # 1. Socket.IO Realtime Gateway Proxy
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

    # 2. Main Laravel Application Proxy
    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Forwarded-Host $host;
        proxy_set_header X-Forwarded-Port $server_port;
        proxy_read_timeout 300s;
        proxy_connect_timeout 60s;
    }

    # Cache static build assets
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|woff2|woff|ttf|svg|webp)$ {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host $host;
        expires 30d;
        add_header Cache-Control "public, no-transform";
    }
}
```

Kích hoạt site và kiểm tra cú pháp Nginx:

```bash
sudo ln -sf /etc/nginx/sites-available/drinkflow /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### Bước 4.3: Cài đặt Chứng chỉ SSL Miễn phí từ Let's Encrypt

```bash
sudo certbot --nginx -d drinkflow.yourcompany.com
```

Chọn tự động chuyển hướng toàn bộ lưu lượng HTTP sang HTTPS khi được hỏi.

---

## 5. Khởi động Ứng dụng & Chạy Migration Ban đầu

### Bước 5.1: Build và Khởi chạy Docker Containers

Tại thư mục `/var/www/drinkflow`:

```bash
# Build và chạy ngầm toàn bộ container
docker compose up -d --build

# Kiểm tra trạng thái các container
docker compose ps
```

### Bước 5.2: Khởi tạo Dữ liệu & Tối ưu hóa Cache

```bash
# Tạo App Key nếu chưa có
docker compose exec app php artisan key:generate --force

# Chạy Migration tạo bảng CSDL PostgreSQL
docker compose exec app php artisan migrate --force

# Seed dữ liệu demo / cấu hình mặc định
docker compose exec app php artisan db:seed --force

# Tối ưu hóa cache Production cho Laravel
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
docker compose exec app php artisan view:cache
```

---

## 6. Script Triển khai Tự động & Cập nhật 1-Click (`deploy.sh`)

Tạo file `deploy.sh` tại `/var/www/drinkflow/deploy.sh` để dễ dàng cập nhật code mới:

```bash
nano deploy.sh
```

Nội dung script:

```bash
#!/usr/bin/env bash
set -e

echo "🚀 [1/6] Kéo mã nguồn mới nhất từ Git..."
git pull origin main

echo "📦 [2/6] Rebuild Docker containers..."
docker compose build app

echo "🔄 [3/6] Khởi động lại dịch vụ..."
docker compose up -d

echo "🗄️ [4/6] Chạy Database Migrations..."
docker compose exec -T app php artisan migrate --force

echo "⚡ [5/6] Xóa và nạp lại Cache..."
docker compose exec -T app php artisan optimize:clear
docker compose exec -T app php artisan config:cache
docker compose exec -T app php artisan route:cache
docker compose exec -T app php artisan view:cache

echo "✅ [6/6] Triển khai hoàn tất thành công!"
```

Cấp quyền thực thi:

```bash
chmod +x deploy.sh
```

Mỗi lần có bản cập nhật mới, chỉ cần chạy:

```bash
./deploy.sh
```

---

## 7. Sao lưu Cơ sở dữ liệu Tự động (Backup PostgreSQL)

Tạo thư mục sao lưu và script backup:

```bash
sudo mkdir -p /var/backups/drinkflow
sudo nano /var/www/drinkflow/backup-db.sh
```

Nội dung script:

```bash
#!/usr/bin/env bash
BACKUP_DIR="/var/backups/drinkflow"
DATE=$(date +%Y%m%d_%H%M%S)
CONTAINER="drinkflow-new-postgres-1"
DB_NAME="drinkflow_prod"
DB_USER="drinkflow_user"

# Xuất bản sao lưu PostgreSQL dạng nén (.gz)
docker exec $CONTAINER pg_dump -U $DB_USER -d $DB_NAME | gzip > "$BACKUP_DIR/db_$DATE.sql.gz"

# Xóa các bản sao lưu cũ quá 14 ngày
find "$BACKUP_DIR" -type f -name "*.sql.gz" -mtime +14 -delete

echo "Backup created: $BACKUP_DIR/db_$DATE.sql.gz"
```

Cấp quyền thực thi và tạo cronjob chạy lúc 02:00 sáng mỗi ngày:

```bash
chmod +x /var/www/drinkflow/backup-db.sh

# Mở crontab
crontab -e

# Thêm dòng sau vào cuối:
0 2 * * * /var/www/drinkflow/backup-db.sh >> /var/log/drinkflow-backup.log 2>&1
```

---

## 8. Lệnh Quản trị Thường Dùng (Cheat Sheet)

| Thao tác | Câu lệnh |
| :--- | :--- |
| **Xem log realtime** | `docker compose logs -f app` |
| **Xem log socket realtime** | `docker compose logs -f realtime` |
| **Kiểm tra trạng thái containers** | `docker compose ps` |
| **Khởi động lại toàn bộ** | `docker compose restart` |
| **Vào terminal container PHP** | `docker compose exec app sh` |
| **Truy cập CSDL PostgreSQL** | `docker compose exec postgres psql -U drinkflow_user -d drinkflow_prod` |
| **Chạy Test tự động** | `docker compose exec app php artisan test` |
| **Xóa toàn bộ cache** | `docker compose exec app php artisan optimize:clear` |
