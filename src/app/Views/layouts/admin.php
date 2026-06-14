<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $this->renderSection('title') ?> - BladeEngine Admin</title>
    <link rel="stylesheet" href="/assets/css/admin.css">
    <script src="/assets/js/admin.js"></script>
    <script defer src="/assets/js/alpine.min.js"></script>
</head>
<body x-data="adminApp()" x-init="init()">

    <!-- Awesome Bar -->
    <div x-data="awesomeBar()" x-cloak
         @keydown.ctrl.k.window.prevent="toggle()"
         @keydown.meta.k.window.prevent="toggle()"
         @keydown.escape.window="open && close()"
         @keydown.arrow-up.window.prevent="open && moveActive(-1)"
         @keydown.arrow-down.window.prevent="open && moveActive(1)"
         @keydown.enter.window.prevent="open && confirmActive()">
        <div x-show="open" class="awesome-bar-overlay" @click.self="close()">
            <div class="awesome-bar-box">
                <!-- Input -->
                <div class="awesome-bar-input-wrap">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                    <input class="awesome-bar-input"
                           x-ref="awesomeInput"
                           type="text"
                           placeholder="Tìm kiếm trang, người dùng, module..."
                           x-model="query"
                           @input="onInput()"
                           autocomplete="off">
                    <span x-show="loading" style="font-size:12px;color:var(--color-text-muted)">...</span>
                </div>

                <!-- Results -->
                <div class="awesome-bar-results">
                    <template x-if="hasResults">
                        <template x-for="group in groupedItems" :key="group.cat">
                            <div>
                                <div class="awesome-bar-group" x-text="group.label"></div>
                                <template x-for="item in group.items" :key="item._flatIdx">
                                    <div class="awesome-bar-item"
                                         :class="activeIndex === item._flatIdx ? 'is-active' : ''"
                                         @click="navigate(item)"
                                         @mouseenter="activeIndex = item._flatIdx">
                                        <span class="awesome-bar-item__icon" x-text="item.icon || '📄'"></span>
                                        <div class="awesome-bar-item__text">
                                            <div class="awesome-bar-item__title" x-text="item.title"></div>
                                            <div class="awesome-bar-item__sub" x-show="item.subtitle" x-text="item.subtitle"></div>
                                        </div>
                                        <span class="awesome-bar-item__kbd" x-show="item.url" x-text="item.url"></span>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </template>

                    <div x-show="!hasResults && !loading && query.length > 0" class="awesome-bar-empty">
                        Không tìm thấy kết quả cho "<span x-text="query"></span>"
                    </div>
                </div>

                <!-- Footer hint -->
                <div class="awesome-bar-footer">
                    <span>↑↓ Di chuyển</span>
                    <span>↵ Chọn</span>
                    <span>Esc Đóng</span>
                    <span style="margin-left:auto">Ctrl+K</span>
                </div>
            </div>
        </div>
    </div>

    <div class="admin-wrapper">
        <!-- Sidebar Overlay (mobile) -->
        <div class="sidebar-overlay"
             :class="{ 'sidebar-overlay--visible': sidebarOpen }"
             @click="sidebarOpen = false"></div>

        <!-- Sidebar -->
        <?= $this->include('components/sidebar') ?>

        <!-- Main Content -->
        <div class="main-content">
            <?= $this->include('components/header') ?>

            <div class="content">
                <?= $this->renderSection('content') ?>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <?= $this->include('components/toast') ?>

    <!-- Confirm Dialog -->
    <?= $this->include('components/confirm') ?>

</body>
</html>
