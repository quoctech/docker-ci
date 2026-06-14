<aside class="sidebar" :class="{ 'sidebar--open': sidebarOpen }">
    <div class="sidebar__brand">
        <h2>⚡ EduGame</h2>
    </div>

    <ul class="sidebar__nav">
        <li class="sidebar__nav-group">
            <div class="sidebar__nav-label">Tổng quan</div>
            <ul>
                <li class="sidebar__nav-item <?= uri_string() === 'admin' ? 'sidebar__nav-item--active' : '' ?>">
                    <a href="/admin">📊 Bảng điều khiển</a>
                </li>
            </ul>
        </li>

        <li class="sidebar__nav-group">
            <div class="sidebar__nav-label">Hệ thống</div>
            <ul>
                <li class="sidebar__nav-item <?= str_starts_with(uri_string(), 'admin/users') ? 'sidebar__nav-item--active' : '' ?>">
                    <a href="/admin/users">👤 Quản lý người dùng</a>
                </li>
                <li class="sidebar__nav-item <?= str_starts_with(uri_string(), 'admin/modules') ? 'sidebar__nav-item--active' : '' ?>">
                    <a href="/admin/modules">🧩 Quản lý Module</a>
                </li>
                <li class="sidebar__nav-item <?= str_starts_with(uri_string(), 'admin/configs') ? 'sidebar__nav-item--active' : '' ?>">
                    <a href="/admin/configs">⚙ Cài đặt Website</a>
                </li>
            </ul>
        </li>

        <li class="sidebar__nav-group">
            <div class="sidebar__nav-label">Tài khoản</div>
            <ul>
                <li class="sidebar__nav-item">
                    <a href="#" @click.prevent="logout()">↪ Đăng xuất</a>
                </li>
            </ul>
        </li>
    </ul>

    <!-- Version -->
    <div class="sidebar__version">
        v<?= env('APP_VERSION', '1.0.0') ?>
    </div>
</aside>
