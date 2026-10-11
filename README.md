# Laravel-Lab — CoffeeShop Laravel Framework Sections 1–6

Integrated Laravel course project covering the database, REST API, error handling and logging, user interface and checkout, testing, and security sections.

## Run with Docker

```sh
cp .env.example .env
npm ci && npm run build
docker compose -p laravel-lab --profile tools run --rm composer install --no-interaction
docker compose -p laravel-lab build app
docker compose -p laravel-lab run --rm --no-deps app php artisan key:generate
APP_PORT=8006 DB_FORWARD_PORT=3316 docker compose -p laravel-lab up -d
docker compose -p laravel-lab exec app php artisan migrate --seed
```

Open http://127.0.0.1:8006. Docker Compose starts a separate MySQL database. Email uses the local log driver by default.

For this Mac's existing local PHP setup, `./start.sh` runs at http://127.0.0.1:8006.

Fortify: đăng ký, đăng nhập/đăng xuất, giới hạn login sai, reset mật khẩu, xác minh email, cập nhật hồ sơ/mật khẩu, xóa tài khoản, 2FA với QR và recovery codes. Sanctum: tạo/cập nhật quyền/thu hồi API token bằng Livewire. Menu account nối dashboard, profile và API tokens.

Mở http://127.0.0.1:8006/register để tạo tài khoản. Bản Mac hiện có thể dùng database local `database/section6.sqlite`; `.env` là cấu hình riêng của máy. Không bật APP_DEBUG ở production.

Các trang: `/login`, `/register`, `/forgot-password`, `/dashboard`, `/profile`, `/api-tokens`.

Email local dùng `MAIL_MAILER=log`: mở `storage/logs/laravel.log` để lấy link verification/reset; SMTP thực chưa được nghiệm thu. Nội dung mail/token/2FA không ghi vào Telescope. Đổi `.env` sang SMTP của bạn nếu cần gửi thật.

## API / Postman

Import `postman/Section6.postman_collection.json`. Tạo token ở `/api-tokens`, copy đầy đủ token một lần và điền biến `apiToken`. Base URL mặc định `http://127.0.0.1:8006`. Các request dùng Bearer auth từ Collection và `Accept: application/json`.

API `/api/v1/user`, `/api/v1/categories`, `/api/v1/products`: thiếu token → 401; thiếu quyền → 403. GET cần `read`, POST cần `create`, PUT/PATCH cần `update`, DELETE cần `delete`. Token chỉ lưu SHA-256; không thể xem lại bản plaintext sau khi đóng modal. Chỉ chủ tài khoản quản lý token; xóa tài khoản thu hồi token.

## Kiểm thử

```sh
./php.sh vendor/bin/phpunit
./php.sh artisan view:cache
npm run build
```


