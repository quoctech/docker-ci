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
        <li class="sidebar__nav-group">
            <div class="sidebar__nav-label">Tổng quan</div>
            <ul>
                <li class="sidebar__nav-item <?= $currentUri === 'admin' ? 'sidebar__nav-item--active' : '' ?>">
                    <a href="/admin">📊 Bảng điều khiển</a>
                </li>
            </ul>
        </li>

        <li class="sidebar__nav-group">
            <div class="sidebar__nav-label">Hệ thống</div>
            <ul>
                <li class="sidebar__nav-item <?= str_starts_with($currentUri, 'admin/users') ? 'sidebar__nav-item--active' : '' ?>">
                    <a href="/admin/users">👤 Quản lý người dùng</a>
                </li>
                <li class="sidebar__nav-item <?= str_starts_with($currentUri, 'admin/modules') ? 'sidebar__nav-item--active' : '' ?>">
                    <a href="/admin/modules">🧩 Quản lý Module</a>
                </li>
            </ul>
        </li>

        <?php if (! empty($sidebarModules)): ?>
        <li class="sidebar__nav-group">
            <div class="sidebar__nav-label">Tính năng</div>
            <ul>
                <?php foreach ($sidebarModules as $mod): ?>
                <li class="sidebar__nav-item <?= str_starts_with($currentUri, ltrim($mod->admin_url, '/')) ? 'sidebar__nav-item--active' : '' ?>">
                    <a href="<?= esc($mod->admin_url) ?>">
                        <?= esc($mod->icon ?? '🧩') ?> <?= esc($mod->name) ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </li>
        <?php endif; ?>
    </ul>

    <!-- Cài đặt Website — luôn ghim ở cuối sidebar -->
    <div class="sidebar__footer">
        <a href="/admin/configs"
           class="sidebar__nav-item-link <?= str_starts_with($currentUri, 'admin/configs') ? 'sidebar__nav-item-link--active' : '' ?>">
            ⚙ Cài đặt Website
        </a>
    </div>

    <div class="sidebar__version">
        v<?= env('APP_VERSION', '1.0.0') ?>
    </div>
</aside>
