<?php
/**
 * Sidebar — hoàn toàn data-driven cho phần module.
 *
 * Khi thêm module mới, chỉ cần insert vào bảng module_sidebar_items
 * trong migration của module đó. Không cần sửa file này.
 *
 * Logic x-show (đã đơn giản hóa — permission-based):
 *   Mọi item: user && hasModule(slug)
 *   Mọi group: user && (hasModule(slug1) || hasModule(slug2) || ...)
 *
 * → Áp dụng cho MỌI role (super_admin, workspace_admin, user/học sinh).
 * → Item chỉ hiện khi user có can_read cho module đó (qua role permission).
 * → Super_admin (userModules=null) luôn thấy mọi items.
 */

$navGroups = [];
try {
    $moduleModel = new \Modules\SystemAdmin\Models\ModuleModel();
    $rawItems    = $moduleModel->getSidebarItems();

    foreach ($rawItems as $item) {
        $item->roles_arr = json_decode($item->allowed_roles, true) ?: [];
        $navGroups[$item->group_label][] = $item;
    }
} catch (\Throwable $e) {
    // Không làm gián đoạn render layout nếu DB lỗi
}

$currentUri = uri_string();

// Tìm sidebar item active: segment-aware longest prefix match wins.
// Ví dụ: /admin/classrooms/students → "Danh sách học sinh" thắng "Danh sách lớp học"
//         /admin/classrooms/UUID    → "Danh sách lớp học" (không item nào dài hơn khớp)
//         /admin/school-management/branches/UUID → "Quản lý chi nhánh"
$activeUrl = null;
$bestLen   = -1;
foreach ($rawItems as $candidate) {
    $path = ltrim($candidate->url, '/');
    if ($path === '') continue;
    if ($currentUri === $path || str_starts_with($currentUri . '/', $path . '/')) {
        if (strlen($path) > $bestLen) {
            $bestLen   = strlen($path);
            $activeUrl = $candidate->url;
        }
    }
}

/**
 * Tính x-show Alpine.js cho một sidebar item dựa trên allowed_roles + min_perm.
 */
function sidebarItemXshow(object $item): string
{
    $roles    = $item->roles_arr;
    $module   = esc($item->module_slug, 'attr');
    $minPerm  = $item->min_perm ?? null; // null|'read'|'write'|'edit'|'delete'

    // Item chỉ dành cho role='user' (học sinh): chỉ hiện cho student
    if (in_array('user', $roles) && ! in_array('super_admin', $roles) && ! in_array('workspace_admin', $roles)) {
        // Ngoại lệ: item "Danh sách lớp học" (URL /admin/my-classrooms)
        // → ẨN khi student ĐÃ CÓ quyền trên module classroom (sẽ thấy qua module trực tiếp,
        //   không cần shortcut "Danh sách lớp học của tôi" nữa).
        // → HIỆN khi student CHƯA có quyền gì trên module (cần shortcut này để xem lớp).
        if ($item->url === '/admin/my-classrooms') {
            return "user && user.role === 'user' && !hasModule('{$module}')";
        }
        return "user && user.role === 'user' && hasModule('{$module}')";
    }

    // Item cho admin/workspace_admin/super_admin.
    // Nếu có min_perm = write|edit|delete → phải có quyền tương ứng mới hiện
    // (dùng để ẩn các màn "chỉ-đọc" với user có quyền read-only).
    if ($minPerm && $minPerm !== 'read') {
        return "user && hasModulePerm('{$module}', 'can_{$minPerm}')";
    }

    // Default: chỉ cần hasModule (read)
    return "user && hasModule('{$module}')";
}

/**
 * Tính x-show Alpine.js cho nhóm nav dựa trên union roles và module permissions của tất cả items.
 *
 * Với workspace_admin/super_admin: group chỉ hiện khi hasModule() trả true cho ít nhất 1 slug trong nhóm.
 * hasModule() trả true cho super_admin (userModules=null) nên super_admin luôn thấy.
 * workspace_admin chỉ thấy khi được cấp can_read cho ít nhất 1 module trong nhóm.
 *
 * Ngoại lệ cho student-only item: nếu trong nhóm có 1 item chỉ dành cho student (my-classrooms)
 * thì nhóm cũng hiện cho student — vì item đó dùng logic ngược (!hasModule) nên có thể hiện
 * ngay cả khi student chưa có quyền module.
 */
function sidebarGroupXshow(array $items): string
{
    $moduleSlugs = array_unique(array_map(fn($i) => $i->module_slug, $items));

    $hasAnyModule = implode(' || ', array_map(
        fn($s) => "hasModule('" . esc($s, 'attr') . "')",
        $moduleSlugs
    ));

    // Detect có student-only item không
    $hasStudentItem = false;
    foreach ($items as $i) {
        $r = $i->roles_arr;
        if (in_array('user', $r) && ! in_array('super_admin', $r) && ! in_array('workspace_admin', $r)) {
            $hasStudentItem = true;
            break;
        }
    }

    $conditions = [];
    if ($hasAnyModule) {
        $conditions[] = $hasAnyModule;
    }
    if ($hasStudentItem) {
        $conditions[] = "user.role === 'user'";
    }

    if (empty($conditions)) {
        return "user && false";
    }

    return "user && (" . implode(' || ', $conditions) . ")";
}
?>
<aside class="sidebar" :class="{ 'sidebar--open': sidebarOpen }">
    <div class="sidebar__brand">
        <h2>⚡ BladeEngine</h2>
    </div>

    <!-- Nav chính (cuộn được) -->
    <ul class="sidebar__nav">

        <!-- Bảng điều khiển — chỉ super_admin -->
        <li class="sidebar__nav-group" x-show="user && user.role === 'super_admin'" x-cloak>
            <div class="sidebar__nav-label">Tổng quan</div>
            <ul>
                <li class="sidebar__nav-item <?= $currentUri === 'admin' ? 'sidebar__nav-item--active' : '' ?>">
                    <a href="/admin">📊 Bảng điều khiển</a>
                </li>
            </ul>
        </li>

        <!-- Hệ thống — chỉ super_admin -->
        <li class="sidebar__nav-group" x-show="user && user.role === 'super_admin'" x-cloak>
            <div class="sidebar__nav-label">Hệ thống</div>
            <ul>
                <li class="sidebar__nav-item <?= str_starts_with($currentUri, 'admin/users') ? 'sidebar__nav-item--active' : '' ?>">
                    <a href="/admin/users">👤 Quản lý người dùng</a>
                </li>
                <li class="sidebar__nav-item <?= str_starts_with($currentUri, 'admin/modules') ? 'sidebar__nav-item--active' : '' ?>">
                    <a href="/admin/modules">🧩 Quản lý Module</a>
                </li>
                <li class="sidebar__nav-item <?= str_starts_with($currentUri, 'admin/system-logs') ? 'sidebar__nav-item--active' : '' ?>">
                    <a href="/admin/system-logs">📋 System Log</a>
                </li>
            </ul>
        </li>

        <!-- Module nav items — data-driven từ bảng module_sidebar_items -->
        <?php foreach ($navGroups as $groupLabel => $items): ?>
        <li class="sidebar__nav-group" x-show="<?= sidebarGroupXshow($items) ?>" x-cloak>
            <div class="sidebar__nav-label"><?= esc($groupLabel) ?></div>
            <ul>
                <?php foreach ($items as $item):
                    $isActive = ($item->url === $activeUrl);
                ?>
                <li class="sidebar__nav-item <?= $isActive ? 'sidebar__nav-item--active' : '' ?>"
                    x-show="<?= sidebarItemXshow($item) ?>" x-cloak>
                    <a href="<?= esc($item->url) ?>"><?= esc($item->icon) ?> <?= esc($item->label) ?></a>
                </li>
                <?php endforeach; ?>
            </ul>
        </li>
        <?php endforeach; ?>

    </ul>

    <!-- Cài đặt Website — chỉ super_admin -->
    <div class="sidebar__footer" x-show="user && user.role === 'super_admin'" x-cloak>
        <a href="/admin/configs"
           class="sidebar__nav-item-link <?= str_starts_with($currentUri, 'admin/configs') ? 'sidebar__nav-item-link--active' : '' ?>">
            ⚙ Cài đặt Website
        </a>
    </div>

    <div class="sidebar__version">
        v<?= env('APP_VERSION', '1.0.0') ?>
    </div>
</aside>
