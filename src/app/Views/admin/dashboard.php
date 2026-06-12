<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Dashboard<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>Tổng quan<?= $this->endSection() ?>

<?= $this->section('content') ?>

<h1 class="content__title">Tổng quan</h1>
<p class="content__subtitle">Bảng điều khiển hệ thống EduGame Platform</p>

<div class="grid grid--3" x-data="dashboardStats()" x-init="load()">
    <div class="card">
        <div class="card__body" style="text-align:center">
            <div style="font-size:32px;font-weight:700;color:var(--color-primary)" x-text="stats.total_users">-</div>
            <div style="font-size:13px;color:var(--color-text-muted);margin-top:4px">Người dùng</div>
        </div>
    </div>
    <div class="card">
        <div class="card__body" style="text-align:center">
            <div style="font-size:32px;font-weight:700;color:var(--color-success)" x-text="stats.active_modules">-</div>
            <div style="font-size:13px;color:var(--color-text-muted);margin-top:4px">Module đang bật</div>
        </div>
    </div>
    <div class="card">
        <div class="card__body" style="text-align:center">
            <div style="font-size:32px;font-weight:700;color:var(--color-warning)" x-text="stats.total_configs">-</div>
            <div style="font-size:13px;color:var(--color-text-muted);margin-top:4px">Cấu hình hệ thống</div>
        </div>
    </div>
</div>

<div class="card" style="margin-top:24px">
    <div class="card__header">
        <h3>Thông tin hệ thống</h3>
    </div>
    <div class="card__body">
        <table class="table">
            <tr><td style="font-weight:500;width:200px">Framework</td><td>CodeIgniter 4.7.3</td></tr>
            <tr><td style="font-weight:500">PHP</td><td>8.5 FPM</td></tr>
            <tr><td style="font-weight:500">Cơ sở dữ liệu</td><td>MariaDB 11</td></tr>
            <tr><td style="font-weight:500">Cache & Queue</td><td>Redis</td></tr>
            <tr><td style="font-weight:500">Async Engine</td><td>Node.js</td></tr>
            <tr><td style="font-weight:500">Web Server</td><td>Nginx</td></tr>
        </table>
    </div>
</div>

<script>
function dashboardStats() {
    return {
        stats: { total_users: '-', active_modules: '-', total_configs: '-' },
        async load() {
            const token = localStorage.getItem('access_token');
            try {
                // Load modules
                const modRes = await fetch('/api/admin/modules', {
                    headers: { 'Authorization': 'Bearer ' + token }
                });
                const modData = await modRes.json();
                if (modData.status === 'success') {
                    this.stats.active_modules = modData.data.filter(m => m.is_enabled).length + '/' + modData.data.length;
                }

                // Load configs
                const cfgRes = await fetch('/api/admin/configs', {
                    headers: { 'Authorization': 'Bearer ' + token }
                });
                const cfgData = await cfgRes.json();
                if (cfgData.status === 'success') {
                    let count = 0;
                    Object.values(cfgData.data).forEach(g => count += g.length);
                    this.stats.total_configs = count;
                }

                // Load users count
                const usrRes = await fetch('/api/admin/users?per_page=1', {
                    headers: { 'Authorization': 'Bearer ' + token }
                });
                const usrData = await usrRes.json();
                if (usrData.status === 'success') {
                    this.stats.total_users = usrData.data.pagination.total;
                }
            } catch (e) {
                console.error('Failed to load stats');
            }
        }
    };
}
</script>

<?= $this->endSection() ?>
