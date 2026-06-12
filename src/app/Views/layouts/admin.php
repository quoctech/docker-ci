<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $this->renderSection('title') ?> - EduGame Admin</title>
    <link rel="stylesheet" href="/assets/css/admin.css">
    <script src="/assets/js/admin.js"></script>
    <script defer src="/assets/js/alpine.min.js"></script>
</head>
<body x-data="adminApp()" x-init="init()">

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

</body>
</html>
