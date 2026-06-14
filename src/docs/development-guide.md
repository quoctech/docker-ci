# BladeEngine — Development Guide

## Quy tắc ghi log vào System Log

Mọi lỗi API, lỗi hệ thống, sự kiện quan trọng **BẮT BUỘC** ghi vào `system_logs`
thông qua `App\Libraries\SystemLogger`.

### Cách dùng

```php
use App\Libraries\SystemLogger;

// Lỗi nghiêm trọng
SystemLogger::error('Thanh toán thất bại', $e->getMessage(), ['order_id' => $id]);

// Cảnh báo
SystemLogger::warning('Giới hạn API gần đạt', null, ['current' => $count]);

// Thông tin
SystemLogger::info('Người dùng đăng ký mới', ['user_id' => $uuid]);

// Ghi exception toàn bộ stack trace
SystemLogger::exception($e, 'checkout');

// Với channel tùy chỉnh
SystemLogger::channel('vortex', 'error', 'Kích hoạt gói học lỗi', $e->getMessage(), $ctx);
```

### Levels

| Level      | Khi nào dùng                              |
|------------|-------------------------------------------|
| `debug`    | Dev only, không cần xem trong production  |
| `info`     | Sự kiện bình thường (user đăng ký, login) |
| `warning`  | Bất thường nhưng không crash (rate limit) |
| `error`    | Lỗi ảnh hưởng user (thanh toán, API fail) |
| `critical` | Hệ thống không hoạt động được             |

### Channels

| Channel     | Dùng cho              |
|-------------|----------------------|
| `app`       | Logic ứng dụng chung  |
| `api`       | API errors (auto)     |
| `auth`      | Đăng nhập/JWT errors  |
| `exception` | Unhandled exceptions  |
| `vortex`    | VortexEngine module   |

> **API errors tự động được ghi** bởi `LogApiErrorsFilter` (4xx trừ 401/404, và 5xx).
> Không cần ghi thêm thủ công trong controller cho HTTP error responses.

---

## Quy tắc đăng ký vào Awesome Bar

Khi tạo **module mới** hoặc **tính năng có trang admin**, **BẮT BUỘC** đăng ký vào Awesome Bar
trong migration `Up()`:

```php
use Modules\AwesomeBar\Repositories\AwesomeBarItemRepository;

// Trong migration Up():
(new AwesomeBarItemRepository())->register([
    'type'        => 'page',
    'title'       => 'Tên hiển thị trong CTRL+K',
    'subtitle'    => 'Mô tả ngắn',
    'url'         => '/admin/duong-dan',
    'icon'        => '🔧',
    'keywords'    => 'keyword1 keyword2 tieng-viet-ko-dau',
    'module_slug' => 'slug-cua-module',  // null nếu luôn hiển thị
    'sort_order'  => 20,
]);

// Trong Down():
(new AwesomeBarItemRepository())->removeByModuleSlug('slug-cua-module');
```

> Xem thêm: `modules/AwesomeBar/docs/AwesomeBar.md`

---

## Quy tắc tạo Module mới

1. Tạo thư mục `src/modules/<ModuleName>/` với cấu trúc:
   ```
   Controllers/   — API controllers
   Models/        — CI4 Models
   Repositories/  — Data access layer
   Filters/       — CI4 Filters (nếu cần)
   docs/          — Tài liệu module
   ```

2. Viết migration trong `src/app/Database/Migrations/` với tên:
   `YYYY-MM-DD-00000N_<MôTả>.php`

3. Đăng ký module vào bảng `modules`:
   ```sql
   INSERT INTO modules (slug, name, description, is_enabled, is_core, version, sort_order, admin_url, icon)
   VALUES ('ten-module', 'Tên module', 'Mô tả', 1, 0, '1.0.0', 20, '/admin/ten-module', '🔧');
   ```

4. Đăng ký routes trong `src/app/Config/Routes.php`.

5. Thêm trang admin vào `AdminPageController::tenModule()` và `Routes.php`.

6. **Đăng ký Awesome Bar items** (xem quy tắc ở trên).

7. Ghi log với `SystemLogger` khi có lỗi trong controller/service.

---

## Quy tắc viết CSS

### Tránh dùng `!important`

**Không dùng `!important` để resolve conflict CSS.** Thay vào đó, tăng độ đặc hiệu (specificity) của selector.

**Cách tính specificity:** `(inline, id, class/attr/pseudo-class, element)`

```css
/* ❌ SAI — dùng !important để ép override */
.filter-bar select {
    width: auto !important;
}

/* ✅ ĐÚNG — selector .filter-bar select (0,1,1) đã cao hơn .form-input (0,1,0) */
.filter-bar select {
    width: auto;
}
```

**Quy tắc chung:**
- Thêm parent class để tăng specificity: `.filter-bar select` > `.form-input`
- Thêm element type: `input.form-input` (0,1,1) > `.form-input` (0,1,0)
- Trong media query cùng specificity, cascade order quyết định — rule sau thắng

**Chỉ được dùng `!important` khi:**
| Trường hợp | Ví dụ | Lý do |
|------------|-------|-------|
| Utility class ẩn/hiện | `[x-cloak] { display: none !important }` | Phải override bất kỳ `display` nào |
| Utility responsive | `.hide-mobile { display: none !important }` | Cần ẩn bất kể component style |

---

## Cấu trúc Database

| Bảng                  | Mô tả                            |
|-----------------------|----------------------------------|
| `users`               | Tài khoản người dùng             |
| `modules`             | Registry các module              |
| `site_configs`        | Cấu hình website (key-value)     |
| `refresh_tokens`      | JWT refresh tokens               |
| `packages`            | Gói học (VortexEngine)           |
| `student_subscriptions` | Đăng ký gói học                |
| `system_logs`         | Nhật ký lỗi & sự kiện            |
| `awesome_bar_items`   | Registry tính năng CTRL+K        |
| `migrations`          | Lịch sử migration                |
