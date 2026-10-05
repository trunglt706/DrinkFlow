# Cấu hình trusted proxy

DrinkFlow hiện được triển khai theo mô hình Laravel nhận kết nối trực tiếp, không có reverse proxy/CDN đứng trước ứng dụng. Vì vậy:

```env
TRUSTED_PROXIES=
```

Khi để trống, địa chỉ IP dùng cho allowlist/blocklist Room luôn lấy từ kết nối trực tiếp; các header `X-Forwarded-*` do client gửi không được tin cậy.

Nếu hạ tầng sau này bổ sung nginx, load balancer hoặc CDN, chỉ cấu hình chính xác IP/CIDR của proxy, phân tách bằng dấu phẩy:

```env
TRUSTED_PROXIES=10.0.0.10,10.0.1.0/24
```

Không đặt `TRUSTED_PROXIES=*` trên production vì client có thể giả mạo `X-Forwarded-For` để vượt quy tắc IP của Room. Sau khi thay đổi, chạy:

```bash
php artisan config:clear
php artisan config:cache
```
