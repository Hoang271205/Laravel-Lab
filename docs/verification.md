# Kết quả kiểm chứng — 09/10/2026

Đã chạy trực tiếp trên Docker của máy này với Laravel 12.69.3, PHP 8.5 và MySQL 8.4.11.

- Build image `coffeeshop-app`: thành công.
- `docker compose run --rm composer install --no-interaction`: thành công từ lock file.
- `app` và `db`: running, healthy; web cổng 8000, MySQL host cổng 3307.
- `php artisan migrate --seed`: thành công trên database `coffee`; toàn bộ 5 migration có trạng thái Ran.
- Dữ liệu thực từ Tinker: 2 danh mục Cafe/Chicken, mỗi danh mục 6 sản phẩm; tổng 12 sản phẩm.
- `app:query-builder-playground`: exit 0, in đủ 2 danh mục và 12 sản phẩm.
- Tinker `--execute`: exit 0, truy vấn được MySQL và quan hệ/count.
- `sh scripts/verify-mysql.sh`: migrate → rollback → migrate thành công trên `coffee_test`; test MySQL 8 passed, 39 assertions.
- `docker compose exec app php artisan test`: test SQLite 8 passed, 39 assertions.
- `vendor/bin/pint --test`: PASS, 36 PHP files.
- `docker compose run --rm composer validate --strict`: valid, exit 0.
- HTTP `/` và `/up`: đều 200.
- Trình duyệt Codex: hiển thị Cafe (6), Chicken (6), sản phẩm và giá; screenshot ở viewport hẹp không bị tràn ngang.

Test kiểm tra seed lặp không nhân đôi ID, hai chiều quan hệ, factory tạo category, khóa ngoại từ chối ID không tồn tại, xóa category đặt foreign key thành null, cập nhật ID category cascade, product không có category, định dạng giá/tổng tiền, output command và trang menu.

Điều chỉnh build sau lỗi mạng: sử dụng compiler và extension nền có sẵn trong image PHP chính thức, chỉ build pdo_mysql/bcmath/pcntl; Composer dùng service công cụ riêng. Không cần cài PHP/Composer trên host.

Project có Git repository riêng và nhánh `feature/working_with_database`. Chưa tạo commit hoặc push GitHub. Credentials local `.env`, vendor và SQLite sinh tự động được Git ignore. DB test riêng vẫn được giữ để chạy lại.

Phạm vi đã hoàn thành: toàn bộ nội dung Section 1 trong văn bản đề được cung cấp, kèm menu đọc dữ liệu. Giỏ hàng, checkout và lưu đơn hàng chưa nằm trong Section 1 này.
