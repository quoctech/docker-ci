# BladeEngine — Quy tắc phát triển

## Tạo module mới

Mỗi module sống trong `src/modules/<ModuleName>/` và được kích hoạt qua **một migration duy nhất**. Không sửa file nào ngoài các bước dưới đây.

### 1. Cấu trúc thư mục

```
src/modules/MyModule/
├── Controllers/
│   ├── MyModulePageController.php   # render views (extends BaseController)
│   └── AdminMyModuleController.php  # API (extends ApiController)
├── Models/
├── Repositories/
└── Views/
```

### 2. Migration

Đặt tên file: `src/app/Database/Migrations/YYYY-MM-DD-NNNNNN_CreateMyModuleTables.php`

Migration phải làm đủ **5 việc** theo thứ tự:

```php
public function up(): void
{
    // 2a. Tạo các bảng của module
    $this->forge->addField([...]);
    $this->forge->createTable('my_module_table');

    // 2b. Đăng ký module vào bảng modules
    \Config\Database::connect()->table('modules')->insert([
        'slug'        => 'my-module',        // kebab-case, unique
        'name'        => 'Tên module',
        'description' => 'Mô tả ngắn.',
        'is_enabled'  => 1,
        'is_core'     => 0,
        'version'     => '1.0.0',
        'sort_order'  => 50,                 // thứ tự trong danh sách module
        'admin_url'   => '/admin/my-module', // URL chính của module
        'icon'        => '🎯',
    ]);

    // 2c. Đăng ký sidebar nav items — sidebar.php tự động render, KHÔNG cần sửa sidebar
    \Config\Database::connect()->table('module_sidebar_items')->insertBatch([
        // Item cho teacher / admin
        [
            'module_slug'   => 'my-module',
            'group_label'   => 'Tên nhóm',          // tiêu đề section trên sidebar
            'label'         => 'Trang quản lý',
            'url'           => '/admin/my-module',
            'icon'          => '🎯',
            'allowed_roles' => '["workspace_admin","super_admin"]', // cả hai thấy
            'match_exact'   => 1,                    // 1 = chỉ active khi URL khớp chính xác
            'sort_order'    => 10,
        ],
        // Item cho học sinh (nếu có)
        [
            'module_slug'   => 'my-module',
            'group_label'   => 'Học tập',
            'label'         => 'Trang học sinh',
            'url'           => '/admin/my-module/student',
            'icon'          => '📖',
            'allowed_roles' => '["user"]',
            'match_exact'   => 0,
            'sort_order'    => 20,
        ],
    ]);

    // 2d. Đăng ký Awesome Bar items (Ctrl+K)
    (new \Modules\AwesomeBar\Repositories\AwesomeBarItemRepository())->register([
        'type'        => 'page',
        'title'       => 'Tên trang',
        'subtitle'    => 'Mô tả hiển thị trong kết quả tìm kiếm',
        'url'         => '/admin/my-module',
        'icon'        => '🎯',
        'keywords'    => 'tu khoa tim kiem khong dau',
        'module_slug' => 'my-module',
        'sort_order'  => 50,
    ]);
}

public function down(): void
{
    // Xóa theo thứ tự ngược
    $this->forge->dropTable('my_module_table', true);

    $db = \Config\Database::connect();
    $db->table('modules')->where('slug', 'my-module')->delete();
    $db->table('module_sidebar_items')->where('module_slug', 'my-module')->delete();

    (new \Modules\AwesomeBar\Repositories\AwesomeBarItemRepository())->removeByModuleSlug('my-module');
}
```

### 3. Routes

Thêm vào `src/app/Config/Routes.php`:

```php
// Page routes — dùng module_redirect để chặn khi module tắt
$routes->group('admin', ['namespace' => '', 'filter' => 'module_redirect:my-module'], function ($routes) {
    $routes->get('my-module', '\Modules\MyModule\Controllers\MyModulePageController::index');
    $routes->get('my-module/(:segment)', '\Modules\MyModule\Controllers\MyModulePageController::detail/$1');
});

// API routes — dùng module_check (trả JSON 503 thay vì redirect)
$routes->group('', ['filter' => 'auth'], function ($routes) {
    $routes->group('my-module', ['filter' => 'module_check:my-module'], function ($routes) {
        $routes->get('',             '\Modules\MyModule\Controllers\AdminMyModuleController::index');
        $routes->post('',            '\Modules\MyModule\Controllers\AdminMyModuleController::create');
        $routes->get('(:segment)',   '\Modules\MyModule\Controllers\AdminMyModuleController::show/$1');
        $routes->put('(:segment)',   '\Modules\MyModule\Controllers\AdminMyModuleController::update/$1');
        $routes->delete('(:segment)','\Modules\MyModule\Controllers\AdminMyModuleController::delete/$1');
    });
});
```

---

## allowed_roles trong module_sidebar_items

| Giá trị | Hiệu ứng Alpine x-show | Dùng khi |
|---|---|---|
| `["workspace_admin","super_admin"]` | `hasModule(slug)` | Super_admin và giáo viên đều thấy |
| `["workspace_admin"]` | `hasModule(slug)` + loại super_admin | Super_admin đã có item này ở section "Hệ thống" |
| `["user"]` | `user.role === 'user'` | Chỉ học sinh |

---

## Quy tắc chung

- **Query builder, không dùng SQL thuần.** Dùng `$db->table('...')->select()->where()->get()`.
- **Controller API** extend `ApiController`, **controller page** extend `BaseController`.
- **Không sửa `sidebar.php`** khi thêm module — chỉ insert vào `module_sidebar_items`.
- **Không sửa `AdminAwesomeBarController`** — chỉ dùng `AwesomeBarItemRepository::register()`.
- Module tắt (`is_enabled = 0`): page routes bị redirect về `/admin/modules`, API routes trả 503.
- Học sinh (`role = 'user'`) không có `user_module_permissions` — AwesomeBar tự tìm lớp học của họ qua `classroom_members`.
