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
   Controllers/         — API controllers + PageController (nếu có UI)
   Models/              — CI4 Models
   Repositories/        — Data access layer
   Views/               — View files của module (BẮT BUỘC nằm ở đây, không để ở app/Views)
   Filters/             — CI4 Filters (nếu cần)
   docs/                — Tài liệu module
   ```

2. Viết migration trong `src/app/Database/Migrations/` với tên:
   `YYYY-MM-DD-00000N_<MôTả>.php`

3. Đăng ký module vào bảng `modules`:
   ```sql
   INSERT INTO modules (slug, name, description, is_enabled, is_core, version, sort_order, admin_url, icon)
   VALUES ('ten-module', 'Tên module', 'Mô tả', 1, 0, '1.0.0', 20, '/admin/ten-module', '🔧');
   ```

4. Đăng ký routes trong `src/app/Config/Routes.php`.

5. Tạo `<ModuleName>PageController` trong `Controllers/` của module, đặt view
   bằng namespace path (xem quy tắc Views bên dưới). **Không thêm method vào
   `AdminPageController`** — controller đó chỉ phục vụ module `SystemAdmin`.

6. **Đăng ký Awesome Bar items** (xem quy tắc ở trên).

7. Ghi log với `SystemLogger` khi có lỗi trong controller/service.

---

## Quy tắc Views — View phải nằm trong module

> **Mọi view file đều phải đặt trong thư mục `Views/` của module sở hữu nó.**
> Không được đặt view của module trong `src/app/Views/`.

### Phân loại

| Nơi đặt | Chứa gì |
|---|---|
| `src/modules/<Module>/Views/` | View riêng của module đó |
| `src/app/Views/layouts/` | Shared layout dùng chung cho toàn bộ admin shell |
| `src/app/Views/components/` | Shared partials: header, sidebar, toast, confirm |
| `src/app/Views/errors/` | Error pages của framework |

### Cách gọi view từ PageController

Dùng **namespace path** — không dùng path tương đối kiểu `'admin/ten-module/index'`:

```php
// ✅ ĐÚNG — namespace path, CI4 tự resolve theo Composer PSR-4
return view('Modules\TenModule\Views\feature/index');
return view('Modules\TenModule\Views\feature/detail', ['data' => $data]);

// ❌ SAI — path tương đối, view nằm ở app/Views (không đúng module)
return view('admin/ten-module/index');
```

Namespace `Modules\TenModule` được đăng ký qua Composer PSR-4 (`"Modules\\": "modules/"`)
và CI4 tự tìm file tại `src/modules/TenModule/Views/feature/index.php`.

### Include partial của module khác

Trong layout hoặc view, dùng namespace path tương tự:

```php
// Layout dùng AwesomeBar widget của module AwesomeBar
<?= $this->include('Modules\AwesomeBar\Views\awesome_bar') ?>

// View extend shared layout (layout nằm ở app/Views/layouts — không có namespace)
<?= $this->extend('layouts/admin') ?>
```

### Cấu trúc Views trong module

Không có quy định cứng về cây thư mục bên trong `Views/`, nhưng quy ước chung:

```
modules/Classroom/Views/
├── classrooms/          — views cho teacher
│   ├── index.php
│   ├── detail.php
│   └── assignment.php
└── my_classrooms/       — views cho student
    ├── index.php
    └── detail.php
```

### PageController pattern

Mỗi module có UI riêng tạo một `<Module>PageController` — controller này chỉ
render view, không chứa business logic. Auth check được thực hiện ở client-side
(Alpine.js kiểm tra JWT). Ví dụ:

```php
namespace Modules\Classroom\Controllers;

use App\Controllers\BaseController;

class ClassroomPageController extends BaseController
{
    public function index(): string
    {
        return view('Modules\Classroom\Views\classrooms/index');
    }
}
```

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
| `classrooms`          | Lớp học (Classroom module)       |
| `classroom_members`   | Thành viên lớp học               |
| `assignments`         | Bài tập trong lớp học            |
| `assignment_submissions` | Bài nộp của học sinh          |
| `migrations`          | Lịch sử migration                |
