<?php
/**
 * Sidebar — hoàn toàn data-driven cho phần module.
 *
 * Khi thêm module mới, chỉ cần insert vào bảng module_sidebar_items
 * trong migration của module đó. Không cần sửa file này.
 *
 * Logic x-show cho item:
 *   allowed_roles chứa "workspace_admin" + "super_admin"  → hasModule(slug)
 *   allowed_roles chứa "workspace_admin" (không có super_admin) → hasModule + exclude super_admin
 *   allowed_roles chứa "user"                              → user.role === 'user'
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

/**
 * Tính x-show Alpine.js cho một sidebar item dựa trên allowed_roles.
 */
function sidebarItemXshow(object $item): string
{
    $roles = $item->roles_arr;

    if (in_array('workspace_admin', $roles) && in_array('super_admin', $roles)) {
        // hasModule trả true cho super_admin (userModules=null) và workspace_admin có quyền
        return "user && hasModule('" . esc($item->module_slug, 'attr') . "')";
    }

    if (in_array('workspace_admin', $roles)) {
        // super_admin đã có item này ở section khác (vd: "Hệ thống")
        return "user && user.role !== 'super_admin' && hasModule('" . esc($item->module_slug, 'attr') . "')";
    }

    if (in_array('user', $roles)) {
        return "user && user.role === 'user'";
    }

    return "user && " . json_encode($roles) . ".includes(user.role)";
}

/**
 * Tính x-show Alpine.js cho nhóm nav dựa trên union roles của tất cả items trong nhóm.
 */
function sidebarGroupXshow(array $items): string
{
    $allRoles = array_unique(array_merge(...array_map(fn($i) => $i->roles_arr, $items)));

    $conds = [];
    if (array_intersect(['workspace_admin', 'super_admin'], $allRoles)) {
        $conds[] = "user.role === 'workspace_admin' || user.role === 'super_admin'";
    }
    if (in_array('user', $allRoles)) {
        $conds[] = "user.role === 'user'";
    }

    return "user && (" . implode(' || ', $conds ?: ["false"]) . ")";
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
                    $isActive = $item->match_exact
                        ? ($currentUri === ltrim($item->url, '/'))
                        : str_starts_with($currentUri, ltrim($item->url, '/'));
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
