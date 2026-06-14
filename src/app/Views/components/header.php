<header class="header">
    <div class="header__left">
        <button class="header__hamburger" @click="sidebarOpen = !sidebarOpen">☰</button>
        <span class="header__breadcrumb"><?= $this->renderSection('breadcrumb') ?></span>
    </div>
    <div class="header__right">
        <!-- Profile dropdown -->
        <div class="header__profile" x-data="{ open: false }">
            <button class="header__profile-btn" @click="open = !open" @click.outside="open = false">
                <div class="header__avatar">
                    <img :src="user && user.avatar_url ? user.avatar_url : ''" x-show="user && user.avatar_url" style="width:100%;height:100%;object-fit:cover;border-radius:50%">
                    <span x-show="!user || !user.avatar_url" x-text="user ? user.full_name.charAt(0).toUpperCase() : '?'"></span>
                </div>
                <span class="header__user-name" x-text="user ? user.full_name : ''"></span>
            </button>
            <!-- Dropdown menu -->
            <div class="header__dropdown" x-show="open" x-transition>
                <a href="/admin/profile" class="header__dropdown-item">✏ Chỉnh sửa hồ sơ</a>
                <a href="#" class="header__dropdown-item" @click.prevent="logout()">↪ Đăng xuất</a>
            </div>
        </div>
    </div>
</header>
