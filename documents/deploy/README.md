# Hướng dẫn Triển khai DrinkFlow lên Máy chủ VPS (Deployment Guides)

Chào mừng bạn đến với tài liệu hướng dẫn triển khai chính thức của hệ thống **DrinkFlow Enterprise**. Thư mục này cung cấp các hướng dẫn chi tiết từng bước (step-by-step) để đưa ứng dụng từ môi trường phát triển lên máy chủ ảo (VPS/Cloud Server) trên môi trường Production thực tế.

---

## 📑 Danh mục Tài liệu Triển khai

| Tài liệu | Phương thức triển khai | Đối tượng & Trường hợp sử dụng |
| :--- | :--- | :--- |
| 🐳 [**Docker Compose Deployment**](./docker-vps-deployment.md) | **Container hóa (Docker Compose)** | ⭐ **Khuyến nghị**. Triển khai nhanh, đóng gói đồng nhất, dễ scale, quản lý trọn gói MySQL, PHP-FPM, Socket.IO. |
| 🛠️ [**Standalone VPS Deployment**](./standalone-vps-deployment.md) | **Cài đặt từng dịch vụ (Native Services)** | Dành cho người muốn kiểm soát toàn bộ tài nguyên bare-metal, tối ưu hóa hiệu năng tối đa của PHP-FPM, Nginx, MySQL, Redis, Supervisor trên hệ điều hành Ubuntu. |

---

## 📊 So sánh 2 Phương thức Triển khai

| Tiêu chí | 🐳 Docker Compose | 🛠️ Standalone (Native Services) |
| :--- | :--- | :--- |
| **Độ phức tạp cài đặt** | Thấp (chỉ cần Docker & Compose) | Trung bình (cài đặt từng package) |
| **Mức tiêu hao tài nguyên** | +5-10% overhead | Tối ưu tối đa phần cứng |
| **Mức độ cô lập (Isolation)** | Hoàn toàn cô lập qua container | Chia sẻ môi trường OS chung |
| **Bảo trì & Nâng cấp** | Rất dễ (`docker compose pull/up`) | Cần quản lý apt, ppa, service updates |
| **Khả năng sao lưu/khôi phục** | Dễ dàng (Volume snapshot, docker volume) | Cần backup riêng db, storage, config |
| **Môi trường khuyến nghị** | Ubuntu 22.04 / 24.04, Debian 12 | Ubuntu 22.04 / 24.04 LTS |

---

## 💻 Yêu cầu Phần cứng Tối thiểu (System Requirements)

| Quy mô sử dụng | CPU | RAM | Ổ cứng SSD/NVMe | Băng thông mạng |
| :--- | :--- | :--- | :--- | :--- |
| **Demo / Văn phòng nhỏ** (< 50 users, 2-5 rooms) | 1 vCPU | 2 GB RAM (kèm 2GB Swap) | 25 GB SSD | 100 Mbps |
| **Doanh nghiệp tiêu chuẩn** (50 - 300 users, 10-20 rooms) | 2 vCPU | 4 GB RAM (kèm 4GB Swap) | 50 GB SSD/NVMe | 1 Gbps |
| **Doanh nghiệp lớn / Tập đoàn** (> 300 users, realtime cao) | 4 vCPU | 8 GB RAM | 100 GB NVMe | 1 Gbps |

---

## 🌐 Sơ đồ Cổng Mạng (Network Ports) & Tường lửa (UFW)

Để ứng dụng hoạt động thông suốt và an toàn, cấu hình tường lửa chỉ mở các cổng công khai cần thiết:

```text
INTERNET / USERS
       │
       ├──► Port 80 (HTTP)  ───► Chuyển hướng sang HTTPS
       ├──► Port 443 (HTTPS) ──► Nginx Reverse Proxy (SSL / TLS)
       │                              │
       │                              ├──► Port 8080 / PHP-FPM (Laravel Application)
       │                              └──► Port 3001 / WebSocket (/socket.io/)
       │
       └──► Port 22 (SSH)   ───► Quản trị viên (Khuyến nghị đổi port & dùng SSH Key)
```

### Bảng Port Chi tiết:

| Cổng (Port) | Giao thức | Phạm vi | Mục đích |
| :--- | :--- | :--- | :--- |
| `22` (hoặc custom) | TCP | Public (Giới hạn IP nếu có) | Truy cập quản trị SSH |
| `80` | TCP | Public | HTTP (Tự động redirect sang 443) |
| `443` | TCP | Public | HTTPS an toàn (Web + Realtime WSS) |
| `3306` | TCP | **Internal / Localhost Only** | MySQL Database (Không mở ra ngoài) |
| `3001` | TCP | **Internal / Reverse Proxy** | Socket.IO Realtime Gateway |
| `6379` | TCP | **Internal / Localhost Only** | Redis Cache & Queue (nếu sử dụng) |

---

## 🔒 Checklist An ninh & Trước khi Go-Live

Xem [cấu hình trusted proxy](./trusted-proxies.md). Với mô hình hiện tại không có proxy, giữ `TRUSTED_PROXIES` trống.

Trước khi công bố đường dẫn cho người dùng nội bộ, hãy chắc chắn hoàn thành các mục kiểm tra an toàn sau:

- [ ] `APP_ENV=production` và `APP_DEBUG=false` trong file `.env`.
- [ ] Khóa tài khoản root SSH và chỉ đăng nhập bằng SSH Key.
- [ ] Cấu hình UFW Firewall chỉ mở cổng 22, 80, 443.
- [ ] Cơ sở dữ liệu MySQL đặt mật khẩu mạnh, không listen ra public IP.
- [ ] Chứng chỉ SSL (Let's Encrypt) hoạt động với điểm đánh giá SSL Labs hạng A.
- [ ] Thiết lập Google OAuth Credentials với đúng Redirect URL: `https://your-domain.com/auth/google/callback`.
- [ ] Chạy `php artisan config:cache`, `route:cache`, `view:cache`.
- [ ] Thiết lập Cronjob tự động backup Database hàng ngày sang thư mục riêng hoặc Cloud Storage.
- [ ] Đã kiểm tra toàn bộ tính năng và chạy test pass 100%:
  ```bash
  php artisan test
  ```
