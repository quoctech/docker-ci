<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Dashboard<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>Tổng quan<?= $this->endSection() ?>

<?= $this->section('content') ?>

<script>
(function() {
    try {
        var u = JSON.parse(localStorage.getItem('user') || 'null');
        if (u && u.role === 'user') window.location.replace('/admin/my-classrooms');
    } catch (e) {}
})();
</script>

<div x-data="dashboardStats()" x-init="load()">

    <!-- Page header with version info -->
    <div class="page-header" style="margin-bottom:20px">
        <div>
            <h1 class="content__title">Tổng quan hệ thống</h1>
            <p class="content__subtitle" x-text="serverStatus ? 'BladeEngine v' + serverStatus.app.version + ' · ' + serverStatus.app.environment : 'BladeEngine'">BladeEngine</p>
        </div>
        <button class="btn btn--ghost btn--sm" @click="loadStatus()" :disabled="loadingStatus">
            <span x-text="loadingStatus ? 'Đang kiểm tra...' : '↻ Làm mới'"></span>
        </button>
    </div>

    <!-- Summary numbers — compact, no color overload -->
    <div class="card" style="margin-bottom:16px">
        <div class="card__body" style="padding:14px 24px">
            <div class="dashboard-stats">
                <div class="dashboard-stat">
                    <span class="dashboard-stat__num" x-text="stats.total_users">—</span>
                    <span class="dashboard-stat__label">Người dùng</span>
                </div>
                <div class="dashboard-stat__divider"></div>
                <div class="dashboard-stat">
                    <span class="dashboard-stat__num" x-text="stats.active_modules">—</span>
                    <span class="dashboard-stat__label">Module hoạt động</span>
                </div>
                <div class="dashboard-stat__divider"></div>
                <div class="dashboard-stat">
                    <span class="dashboard-stat__num"
                          :style="stats.unseen_logs > 0 ? 'color:var(--color-danger)' : ''"
                          x-text="stats.unseen_logs">—</span>
                    <span class="dashboard-stat__label">Log chưa đọc</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Report -->
    <div class="card">
        <div class="card__header">
            <h3>Báo cáo trạng thái</h3>
        </div>

        <div x-show="loadingStatus" style="padding:24px;text-align:center;color:var(--color-text-muted);font-size:13px">
            Đang kiểm tra...
        </div>

        <template x-if="!loadingStatus && serverStatus">
            <table class="table sr-table">
                <tbody>
                    <!-- Environment -->
                    <tr>
                        <td class="sr-status" :class="serverStatus.app.environment === 'production' ? 'sr-ok' : 'sr-neutral'">
                            <span x-text="serverStatus.app.environment === 'production' ? '✓' : '—'"></span>
                        </td>
                        <td class="sr-label">Môi trường</td>
                        <td class="sr-value">
                            <span x-text="serverStatus.app.environment"></span>
                        </td>
                    </tr>

                    <!-- PHP -->
                    <tr>
                        <td class="sr-status sr-ok">✓</td>
                        <td class="sr-label">PHP</td>
                        <td class="sr-value" x-text="serverStatus.php.version"></td>
                    </tr>

                    <!-- Web Server -->
                    <tr>
                        <td class="sr-status sr-ok">✓</td>
                        <td class="sr-label">Web server</td>
                        <td class="sr-value" x-text="serverStatus.app.server || 'Nginx'"></td>
                    </tr>

                    <!-- Timezone -->
                    <tr>
                        <td class="sr-status sr-ok">✓</td>
                        <td class="sr-label">Múi giờ</td>
                        <td class="sr-value" x-text="serverStatus.app.timezone"></td>
                    </tr>

                    <!-- Load avg -->
                    <tr x-show="serverStatus.app.load?.['1min'] !== undefined">
                        <td class="sr-status sr-neutral">—</td>
                        <td class="sr-label">Load avg</td>
                        <td class="sr-value" x-text="(serverStatus.app.load?.['1min'] ?? '-') + ' / ' + (serverStatus.app.load?.['5min'] ?? '-') + ' / ' + (serverStatus.app.load?.['15min'] ?? '-') + '  (1 / 5 / 15 phút)'"></td>
                    </tr>

                    <!-- Database -->
                    <tr>
                        <td class="sr-status" :class="serverStatus.database.status === 'online' ? 'sr-ok' : 'sr-err'">
                            <span x-text="serverStatus.database.status === 'online' ? '✓' : '✗'"></span>
                        </td>
                        <td class="sr-label">Database</td>
                        <td class="sr-value">
                            <span>MariaDB</span>
                            <span class="sr-meta" x-text="serverStatus.database.version || ''"></span>
                            <span class="sr-note" :style="serverStatus.database.status !== 'online' ? 'color:var(--color-danger)' : ''"
                                  x-text="serverStatus.database.status !== 'online' ? 'Không kết nối được' : ''"></span>
                        </td>
                    </tr>

                    <!-- Redis -->
                    <tr>
                        <td class="sr-status" :class="serverStatus.redis.status === 'online' ? 'sr-ok' : 'sr-err'">
                            <span x-text="serverStatus.redis.status === 'online' ? '✓' : '✗'"></span>
                        </td>
                        <td class="sr-label">Redis</td>
                        <td class="sr-value">
                            <span x-text="serverStatus.redis.status === 'online' ? 'Online' : 'Offline'"></span>
                            <span class="sr-note" x-show="serverStatus.redis.status !== 'online'" style="color:var(--color-danger)">Kiểm tra kết nối Redis</span>
                        </td>
                    </tr>

                    <!-- Memory -->
                    <tr>
                        <td class="sr-status" :class="serverStatus.memory.percent > 80 ? 'sr-err' : serverStatus.memory.percent > 60 ? 'sr-warn' : 'sr-ok'">
                            <span x-text="serverStatus.memory.percent > 80 ? '✗' : '✓'"></span>
                        </td>
                        <td class="sr-label">Bộ nhớ PHP</td>
                        <td class="sr-value">
                            <div class="sr-bar-row">
                                <div class="sr-bar">
                                    <div class="sr-bar__fill"
                                         :style="'width:' + (serverStatus.memory.percent || 0) + '%;background:' + (serverStatus.memory.percent > 80 ? 'var(--color-danger)' : serverStatus.memory.percent > 60 ? 'var(--color-warning)' : 'var(--color-text-muted)')"></div>
                                </div>
                                <span x-text="(serverStatus.memory.used || '—') + ' / ' + (serverStatus.memory.limit || '—') + ' (' + (serverStatus.memory.percent || 0) + '%)'"></span>
                            </div>
                        </td>
                    </tr>

                    <!-- Disk -->
                    <tr>
                        <td class="sr-status" :class="serverStatus.disk.percent > 90 ? 'sr-err' : serverStatus.disk.percent > 70 ? 'sr-warn' : 'sr-ok'">
                            <span x-text="serverStatus.disk.percent > 90 ? '✗' : '✓'"></span>
                        </td>
                        <td class="sr-label">Ổ đĩa</td>
                        <td class="sr-value">
                            <div class="sr-bar-row">
                                <div class="sr-bar">
                                    <div class="sr-bar__fill"
                                         :style="'width:' + (serverStatus.disk.percent || 0) + '%;background:' + (serverStatus.disk.percent > 90 ? 'var(--color-danger)' : serverStatus.disk.percent > 70 ? 'var(--color-warning)' : 'var(--color-text-muted)')"></div>
                                </div>
                                <span x-text="(serverStatus.disk.used || '—') + ' / ' + (serverStatus.disk.total || '—') + ' (' + (serverStatus.disk.percent || 0) + '%) · ' + (serverStatus.disk.free || '—') + ' còn trống'"></span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </template>

        <div x-show="!loadingStatus && !serverStatus" style="padding:16px 24px;font-size:13px;color:var(--color-danger)">
            Không thể lấy thông tin máy chủ.
        </div>
    </div>

</div>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="/assets/modules/SystemAdmin/system-admin.css">
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="/assets/modules/SystemAdmin/system-admin.js"></script>
<?= $this->endSection() ?>

<?= $this->endSection() ?>
