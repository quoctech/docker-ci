<header class="header">
    <div class="header__left">
        <button class="header__hamburger" @click="sidebarOpen = !sidebarOpen">☰</button>
        <span class="header__breadcrumb"><?= $this->renderSection('breadcrumb') ?></span>
    </div>
    <div class="header__right">
        <span class="header__user" x-text="user ? user.full_name : ''"></span>
        <span class="badge badge--info" x-text="user ? user.role : ''"></span>
    </div>
</header>
