<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Dashboard<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>Tổng quan<?= $this->endSection() ?>

<?= $this->section('content') ?>

<h1 class="content__title">Tổng quan</h1>
<p class="content__subtitle">Bảng điều khiển hệ thống BladeEngine</p>

<!-- Stats Cards -->
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
            <div style="font-size:32px;font-weight:700;color:var(--color-warning)" x-text="stats.unseen_logs">-</div>
            <div style="font-size:13px;color:var(--color-text-muted);margin-top:4px">Log chưa xem</div>
        </div>
    </div>

    <!-- Server Status -->
    <div class="card" style="grid-column: 1 / -1; margin-top:4px">
        <div class="card__header" style="display:flex;justify-content:space-between;align-items:center">
            <h3>Trạng thái máy chủ</h3>
            <button class="btn btn--ghost btn--sm" @click="loadStatus()" :disabled="loadingStatus">
                <span x-text="loadingStatus ? 'Đang tải...' : '↻ Làm mới'"></span>
            </button>
        </div>
        <div class="card__body">
            <!-- Loading -->
            <div x-show="loadingStatus" style="text-align:center;padding:20px;color:var(--color-text-muted)">Đang kiểm tra...</div>

            <div x-show="!loadingStatus && serverStatus" class="grid grid--2" style="gap:20px">
                <!-- Left col -->
                <div>
                    <table class="table" style="font-size:13px">
                        <tr>
                            <td style="font-weight:500;width:130px;color:var(--color-text-muted)">Môi trường</td>
                            <td>
                                <span class="badge"
                                      :class="serverStatus?.app?.environment === 'production' ? 'badge--success' : 'badge--warning'"
                                      x-text="serverStatus?.app?.environment || '-'"></span>
                            </td>
                        </tr>
                        <tr>
                            <td style="font-weight:500;color:var(--color-text-muted)">PHP</td>
                            <td x-text="serverStatus?.php?.version || '-'"></td>
                        </tr>
                        <tr>
                            <td style="font-weight:500;color:var(--color-text-muted)">Web Server</td>
                            <td x-text="serverStatus?.app?.server || 'Nginx'"></td>
                        </tr>
                        <tr>
                            <td style="font-weight:500;color:var(--color-text-muted)">Múi giờ</td>
                            <td x-text="serverStatus?.app?.timezone || '-'"></td>
                        </tr>
                        <tr>
                            <td style="font-weight:500;color:var(--color-text-muted)">Phiên bản</td>
                            <td x-text="'v' + (serverStatus?.app?.version || '1.0.0')"></td>
                        </tr>
                        <tr x-show="serverStatus?.app?.load?.['1min'] !== undefined">
                            <td style="font-weight:500;color:var(--color-text-muted)">Load avg</td>
                            <td x-text="(serverStatus?.app?.load?.['1min'] ?? '-') + ' / ' + (serverStatus?.app?.load?.['5min'] ?? '-') + ' / ' + (serverStatus?.app?.load?.['15min'] ?? '-')"></td>
                        </tr>
                    </table>
                </div>

                <!-- Right col -->
                <div>
                    <!-- Memory bar -->
                    <div style="margin-bottom:16px">
                        <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px">
                            <span style="font-weight:500">Bộ nhớ PHP</span>
                            <span style="color:var(--color-text-muted)" x-text="(serverStatus?.memory?.used || '-') + ' / ' + (serverStatus?.memory?.limit || '-')"></span>
                        </div>
                        <div style="background:var(--color-border);border-radius:4px;height:8px;overflow:hidden">
                            <div style="height:100%;border-radius:4px;transition:width .3s"
                                 :style="'width:' + (serverStatus?.memory?.percent || 0) + '%;background:' + (serverStatus?.memory?.percent > 80 ? 'var(--color-danger)' : serverStatus?.memory?.percent > 60 ? 'var(--color-warning)' : 'var(--color-success)')"></div>
                        </div>
                        <div style="font-size:11px;color:var(--color-text-muted);margin-top:2px" x-text="(serverStatus?.memory?.percent || 0) + '% đã dùng'"></div>
                    </div>

                    <!-- Disk bar -->
                    <div style="margin-bottom:16px">
                        <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px">
                            <span style="font-weight:500">Ổ đĩa</span>
                            <span style="color:var(--color-text-muted)" x-text="(serverStatus?.disk?.used || '-') + ' / ' + (serverStatus?.disk?.total || '-')"></span>
                        </div>
                        <div style="background:var(--color-border);border-radius:4px;height:8px;overflow:hidden">
                            <div style="height:100%;border-radius:4px;transition:width .3s"
                                 :style="'width:' + (serverStatus?.disk?.percent || 0) + '%;background:' + (serverStatus?.disk?.percent > 90 ? 'var(--color-danger)' : serverStatus?.disk?.percent > 70 ? 'var(--color-warning)' : 'var(--color-success)')"></div>
                        </div>
                        <div style="font-size:11px;color:var(--color-text-muted);margin-top:2px" x-text="(serverStatus?.disk?.percent || 0) + '% đã dùng · ' + (serverStatus?.disk?.free || '-') + ' còn lại'"></div>
                    </div>

                    <!-- Services -->
                    <div style="display:flex;gap:12px;flex-wrap:wrap">
                        <div style="display:flex;align-items:center;gap:6px;font-size:13px">
                            <span style="width:8px;height:8px;border-radius:50%;display:inline-block"
                                  :style="'background:' + (serverStatus?.database?.status === 'online' ? 'var(--color-success)' : 'var(--color-danger)')"></span>
                            <span>MariaDB</span>
                            <span style="font-size:11px;color:var(--color-text-muted)" x-text="serverStatus?.database?.version || ''"></span>
                        </div>
                        <div style="display:flex;align-items:center;gap:6px;font-size:13px">
                            <span style="width:8px;height:8px;border-radius:50%;display:inline-block"
                                  :style="'background:' + (serverStatus?.redis?.status === 'online' ? 'var(--color-success)' : 'var(--color-danger)')"></span>
                            <span>Redis</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Error state -->
            <div x-show="!loadingStatus && !serverStatus" style="color:var(--color-danger);font-size:13px">
                Không thể lấy thông tin máy chủ.
            </div>
        </div>
    </div>
</div>

<script>
function dashboardStats() {
    return {
        stats: { total_users: '-', active_modules: '-', unseen_logs: '-' },
        serverStatus: null,
        loadingStatus: false,

        async load() {
            await Promise.all([this.loadStats(), this.loadStatus()]);
        },

        async loadStats() {
            const token = localStorage.getItem('access_token');
            try {
                const [modData, usrData, logData] = await Promise.all([
                    fetch('/api/admin/modules',           { headers: { 'Authorization': 'Bearer ' + token } }).then(r => r.json()),
                    fetch('/api/admin/users?per_page=1',  { headers: { 'Authorization': 'Bearer ' + token } }).then(r => r.json()),
                    fetch('/api/admin/system-logs/stats', { headers: { 'Authorization': 'Bearer ' + token } }).then(r => r.json()),
                ]);
                if (modData.status === 'success')
                    this.stats.active_modules = modData.data.filter(m => m.is_enabled).length + '/' + modData.data.length;
                if (usrData.status === 'success')
                    this.stats.total_users = usrData.data.pagination.total;
                if (logData.status === 'success')
                    this.stats.unseen_logs = logData.data.unseen;
            } catch (e) {
                console.error('Failed to load stats');
            }
        },

        async loadStatus() {
            this.loadingStatus = true;
            try {
                const data = await apiGet('/api/admin/server-status');
                this.serverStatus = data?.status === 'success' ? data.data : null;
            } catch (e) {
                this.serverStatus = null;
            }
            this.loadingStatus = false;
        },
    };
}
</script>

<?= $this->endSection() ?>
