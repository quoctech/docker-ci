<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $this->renderSection('title') ?> - BladeEngine Admin</title>
    <link rel="stylesheet" href="/assets/css/admin.css">
    <link rel="stylesheet" href="/assets/modules/AwesomeBar/awesome-bar.css">
    <?= $this->renderSection('styles') ?>
    <script src="/assets/js/admin.js"></script>
    <script src="/assets/modules/AwesomeBar/awesome-bar.js"></script>
    <script defer src="/assets/js/alpine.min.js"></script>
</head>
<body x-data="adminApp()" x-init="init()">

    <!-- Awesome Bar -->
    <?= $this->include('Modules\AwesomeBar\Views\awesome_bar') ?>

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

    <?= $this->renderSection('scripts') ?>
</body>
</html>
