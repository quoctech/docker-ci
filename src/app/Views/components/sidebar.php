<?php
$sidebarModules = [];
try {
    $moduleModel    = new \Modules\SystemAdmin\Models\ModuleModel();
    $sidebarModules = $moduleModel->getEnabledWithAdminUrl();
} catch (\Throwable $e) {
    // Không làm gián đoạn render layout nếu DB lỗi
}

$currentUri = uri_string();
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

        <!-- Lớp học — giáo viên và super_admin -->
        <li class="sidebar__nav-group" x-show="user && (user.role === 'workspace_admin' || user.role === 'super_admin')" x-cloak>
            <div class="sidebar__nav-label">Lớp học</div>
            <ul>
                <li class="sidebar__nav-item <?= $currentUri === 'admin/classrooms' ? 'sidebar__nav-item--active' : '' ?>">
                    <a href="/admin/classrooms">🏫 Danh sách lớp học</a>
                </li>
                <li class="sidebar__nav-item <?= str_starts_with($currentUri, 'admin/classrooms/students') ? 'sidebar__nav-item--active' : '' ?>">
                    <a href="/admin/classrooms/students">👥 Danh sách học sinh</a>
                </li>
            </ul>
        </li>

        <!-- Tính năng (modules) — chỉ super_admin, trừ classroom (đã có trong nhóm Lớp học) -->
        <?php
        $nonClassroomModules = array_filter($sidebarModules, fn($m) => ltrim($m->admin_url, '/') !== 'admin/classrooms');
        if (! empty($nonClassroomModules)): ?>
        <li class="sidebar__nav-group" x-show="user && user.role === 'super_admin'" x-cloak>
            <div class="sidebar__nav-label">Tính năng</div>
            <ul>
                <?php foreach ($nonClassroomModules as $mod): ?>
                <li class="sidebar__nav-item <?= str_starts_with($currentUri, ltrim($mod->admin_url, '/')) ? 'sidebar__nav-item--active' : '' ?>">
                    <a href="<?= esc($mod->admin_url) ?>">
                        <?= esc($mod->icon ?? '🧩') ?> <?= esc($mod->name) ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </li>
        <?php endif; ?>

        <!-- Học tập — chỉ user (học sinh) -->
        <li class="sidebar__nav-group" x-show="user && user.role === 'user'" x-cloak>
            <div class="sidebar__nav-label">Học tập</div>
            <ul>
                <li class="sidebar__nav-item <?= str_starts_with($currentUri, 'admin/my-classrooms') ? 'sidebar__nav-item--active' : '' ?>">
                    <a href="/admin/my-classrooms">📚 Lớp học của tôi</a>
                </li>
            </ul>
        </li>

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
