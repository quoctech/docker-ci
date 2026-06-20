# AwesomeBar — Tìm kiếm nhanh (CTRL+K)

Module cung cấp giao diện tìm kiếm toàn cục trong admin panel.

## Mở

| Shortcut | Hành động |
|----------|-----------|
| `Ctrl+K` | Mở/đóng Awesome Bar |
| `↑ ↓`    | Di chuyển giữa kết quả |
| `Enter`  | Chọn kết quả |
| `Esc`    | Đóng |

## Quy tắc truy cập (NO RESTRICTION)

**BẤT KỲ user đã đăng nhập đều có thể dùng Awesome Bar.** Không restrict theo role.

Kết quả search được filter theo permission của user hiện tại:

| Role | Items hiển thị |
|---|---|
| **super_admin** | Tất cả items (kể cả `module_slug=NULL`) |
| **workspace_admin** | Items có `module_slug` nằm trong danh sách module user có `can_read` (qua role) |
| **user (học sinh)** | Items có `module_slug` nằm trong danh sách module user có `can_read` (nếu được phân quyền qua role) + **luôn thấy lớp học của mình** |

> Items với `module_slug = NULL` (hoặc rỗng) → chỉ hiện cho **super_admin** (coi như system-level).

## Nguồn tìm kiếm

| Nhóm    | Nguồn dữ liệu              | Giới hạn | Ai thấy |
|---------|---------------------------|----------|---------|
| Trang   | `awesome_bar_items`        | 10       | super_admin: tất cả • workspace_admin/user: filter theo permission |
| Lớp của tôi | `classroom_members`     | 8        | Chỉ user (học sinh) |
| Người dùng | `users` (active)       | 6        | Chỉ super_admin |
| Module  | `modules`                 | 5        | Chỉ super_admin |
| Cài đặt | `site_configs`            | 4        | Chỉ super_admin |

## Schema: `awesome_bar_items`

```sql
id          SMALLINT UNSIGNED AUTO_INCREMENT
type        ENUM('page','action','module','external')
title       VARCHAR(255)          -- Tên hiển thị
subtitle    VARCHAR(150) NULL     -- Mô tả ngắn
url         VARCHAR(500) NULL     -- URL đích (null = custom action)
icon        VARCHAR(60)  NULL     -- Emoji hoặc icon name
keywords    VARCHAR(500) NULL     -- Từ khoá tìm kiếm, cách nhau bởi dấu cách
module_slug VARCHAR(60)  NULL     -- module để check permission (NULL = chỉ super_admin thấy)
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
    'module_slug' => 'ten-module-slug',  // BẮT BUỘC có slug để check permission
    'sort_order'  => 20,
]);

// Trong Down():
$repo->removeByModuleSlug('ten-module-slug');
```

> ⚠️ **Quan trọng**: LUÔN set `module_slug` khi đăng ký. Items không có `module_slug` sẽ chỉ hiện cho super_admin.

## Quyền truy cập (Permission System)

Awesome Bar sử dụng `UserPermissionRepository` (Single Source of Truth — JOIN qua role) để filter items theo permission của user hiện tại.

### Flow check quyền:
```
user_applied_roles → roles (is_active=1) → role_module_permissions
        ↓
UNION permissions từ tất cả role user được gán
        ↓
Lấy map slug → {can_read, can_write, can_edit, can_delete}
        ↓
Cache qua Redis (perm:user:{uuid}, TTL 1 giờ)
```

### Áp dụng:
- **workspace_admin + user**: items có `module_slug` phải nằm trong `getReadableSlugs(userUuid)` mới hiển thị.
- Học sinh được phân quyền cho module nào thì thấy items của module đó + luôn thấy lớp của mình.
- Nếu user không có quyền cho module nào → chỉ thấy items global (`module_slug=NULL` cho super_admin) hoặc lớp của mình (cho student).

## Cache invalidation

Cache permission tự động bị xóa khi:
1. Admin thay đổi permission của role → DEL cache của tất cả user có role
2. Admin apply role cho user → DEL cache của user đó
3. Admin xóa role → DEL cache của tất cả user có role

User sẽ thấy items mới ở request kế tiếp (sau khi cache miss → query DB → refresh).