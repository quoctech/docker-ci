# AwesomeBar — Tìm kiếm nhanh (CTRL+K)

Module cung cấp giao diện tìm kiếm toàn cục trong admin panel.

## Mở

| Shortcut | Hành động |
|----------|-----------|
| `Ctrl+K` | Mở/đóng Awesome Bar |
| `↑ ↓`    | Di chuyển giữa kết quả |
| `Enter`  | Chọn kết quả |
| `Esc`    | Đóng |

## Nguồn tìm kiếm

| Nhóm    | Nguồn dữ liệu              | Giới hạn |
|---------|---------------------------|----------|
| Trang   | `awesome_bar_items`        | 10       |
| Người dùng | `users` (active)       | 6        |
| Module  | `modules`                 | 5        |
| Cài đặt | `site_configs`            | 4        |

## Schema: `awesome_bar_items`

```sql
id          SMALLINT UNSIGNED AUTO_INCREMENT
type        ENUM('page','action','module','external')
title       VARCHAR(255)          -- Tên hiển thị
subtitle    VARCHAR(150) NULL     -- Mô tả ngắn
url         VARCHAR(500) NULL     -- URL đích (null = custom action)
icon        VARCHAR(60)  NULL     -- Emoji hoặc icon name
keywords    VARCHAR(500) NULL     -- Từ khoá tìm kiếm, cách nhau bởi dấu cách
module_slug VARCHAR(60)  NULL     -- NULL = luôn hiển thị; có giá trị = chỉ khi module đó enabled
is_active   TINYINT(1)            -- 1 = hiển thị
sort_order  SMALLINT              -- Thứ tự ưu tiên trong kết quả
```

## Convention: Đăng ký khi tạo module mới

Khi tạo module mới hoặc thêm tính năng có trang admin, **BẮT BUỘC** đăng ký vào Awesome Bar
trong migration hoặc install script:

```php
// Trong migration Up() của module mới:
use Modules\AwesomeBar\Repositories\AwesomeBarItemRepository;

$repo = new AwesomeBarItemRepository();
$repo->register([
    'type'        => 'page',
    'title'       => 'Tên tính năng',
    'subtitle'    => 'Mô tả ngắn',
    'url'         => '/admin/ten-tinh-nang',
    'icon'        => '🔧',
    'keywords'    => 'keyword1 keyword2 viet-khong-dau',
    'module_slug' => 'ten-module-slug',  // hoặc null nếu luôn hiển thị
    'sort_order'  => 20,
]);

// Trong Down():
$repo->removeByModuleSlug('ten-module-slug');
```

## Quyền truy cập

Hiện tại, toàn bộ admin panel yêu cầu `super_admin`. Tất cả items trong Awesome Bar
đều hiển thị với super_admin. Khi thêm role mới (ví dụ `workspace_admin`) vào admin,
bổ sung cột `required_role ENUM('super_admin','workspace_admin')` vào bảng `awesome_bar_items`
và filter trong `AdminAwesomeBarController::search()` theo role của user hiện tại.
