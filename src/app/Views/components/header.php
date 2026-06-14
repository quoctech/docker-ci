<header class="header">
    <div class="header__left">
        <button class="header__hamburger" @click="sidebarOpen = !sidebarOpen">☰</button>
        <span class="header__breadcrumb"><?= $this->renderSection('breadcrumb') ?></span>
    </div>
    <div class="header__right">
        <!-- Awesome Bar trigger -->
        <button class="btn btn--ghost btn--sm header__search-btn"
                onclick="document.dispatchEvent(new KeyboardEvent('keydown',{key:'k',ctrlKey:true,bubbles:true}))"
                title="Tìm kiếm nhanh (Ctrl+K)"
                style="display:flex;align-items:center;gap:6px;font-size:13px;color:var(--color-text-muted)">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            <span class="hide-mobile">Tìm kiếm</span>
            <kbd style="font-size:10px;background:var(--color-bg);border:1px solid var(--color-border);border-radius:3px;padding:1px 5px;color:var(--color-text-muted)" class="hide-tablet hide-mobile">Ctrl+K</kbd>
        </button>

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
