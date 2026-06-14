<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>System Log<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>Hệ thống / System Log<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div x-data="systemLogApp()" x-init="load()">

    <div class="page-header">
        <div>
            <h1 class="content__title">System Log</h1>
            <p class="content__subtitle">Nhật ký lỗi và sự kiện hệ thống.</p>
        </div>
        <div style="display:flex;gap:8px">
            <button class="btn btn--secondary btn--sm" @click="markAllSeen()" :disabled="unseenCount === 0">
                Đánh dấu đã xem <span x-show="unseenCount > 0" x-text="'(' + unseenCount + ')'" style="margin-left:4px"></span>
            </button>
            <button class="btn btn--danger btn--sm" @click="confirmClearAll()">Xóa tất cả</button>
        </div>
    </div>

    <!-- Filters -->
    <div class="card" style="margin-bottom:20px">
        <div class="filter-bar">
            <input class="form-input filter-bar__search" style="height:36px"
                   placeholder="Tìm kiếm tiêu đề, nội dung..."
                   x-model="filters.q" @input.debounce.400ms="load()">
            <select class="form-input" style="height:36px" x-model="filters.level" @change="load()">
                <option value="">Tất cả cấp độ</option>
                <option value="debug">Debug</option>
                <option value="info">Info</option>
                <option value="warning">Warning</option>
                <option value="error">Error</option>
                <option value="critical">Critical</option>
            </select>
            <select class="form-input" style="height:36px" x-model="filters.channel" @change="load()">
                <option value="">Tất cả channel</option>
                <option value="app">app</option>
                <option value="api">api</option>
                <option value="auth">auth</option>
                <option value="exception">exception</option>
                <option value="vortex">vortex</option>
            </select>
            <select class="form-input" style="height:36px" x-model="filters.seen" @change="load()">
                <option value="">Tất cả</option>
                <option value="0">Chưa xem</option>
                <option value="1">Đã xem</option>
            </select>
            <span class="filter-bar__count" x-text="'Tổng: ' + pagination.total + ' log'"></span>
        </div>
    </div>

    <!-- Table -->
    <div class="card">
        <div class="card__body" style="padding:0">
            <div x-show="loading" style="padding:40px;text-align:center;color:var(--color-text-muted)">Đang tải...</div>
            <div class="table-wrapper" x-show="!loading">
                <table class="table table-card">
                    <thead>
                        <tr>
                            <th style="width:90px">Cấp độ</th>
                            <th style="width:90px">Channel</th>
                            <th>Tiêu đề</th>
                            <th style="width:140px" class="hide-mobile">Thời gian</th>
                            <th style="width:80px" class="hide-mobile">IP</th>
                            <th style="width:60px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="log in records" :key="log.id">
                            <tr :class="log.seen == 0 && log.level !== 'debug' ? 'row--unseen' : ''">
                                <td data-label="Cấp độ">
                                    <span class="badge" :class="levelBadge(log.level)" x-text="log.level"></span>
                                </td>
                                <td data-label="Channel">
                                    <code style="font-size:11px;background:var(--color-bg);padding:2px 6px;border-radius:4px" x-text="log.channel"></code>
                                </td>
                                <td data-label="Tiêu đề">
                                    <span x-text="log.title" style="word-break:break-word"></span>
                                    <div x-show="log.url" style="font-size:11px;color:var(--color-text-muted);margin-top:2px">
                                        <span x-text="log.method + ' ' + log.url"></span>
                                    </div>
                                </td>
                                <td data-label="Thời gian" class="hide-mobile" style="font-size:12px;color:var(--color-text-muted)" x-text="log.created_at"></td>
                                <td data-label="IP" class="hide-mobile" style="font-size:12px;color:var(--color-text-muted)" x-text="log.ip_address || '-'"></td>
                                <td data-label="">
                                    <div style="display:flex;gap:4px;justify-content:flex-end">
                                        <button class="btn btn--ghost btn--sm" @click="viewDetail(log)" title="Xem chi tiết">👁</button>
                                        <button class="btn btn--ghost btn--sm" @click="deleteLog(log.id)" title="Xóa">✕</button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <div x-show="!loading && records.length === 0" style="padding:60px;text-align:center;color:var(--color-text-muted)">
                Không có log nào.
            </div>

            <!-- Pagination -->
            <div x-show="pagination.total_pages > 1" class="pagination-bar">
                <button class="btn btn--ghost btn--sm" @click="goPage(pagination.page - 1)" :disabled="pagination.page <= 1">← Trước</button>
                <span style="padding:6px 12px;font-size:13px" x-text="pagination.page + ' / ' + pagination.total_pages"></span>
                <button class="btn btn--ghost btn--sm" @click="goPage(pagination.page + 1)" :disabled="pagination.page >= pagination.total_pages">Sau →</button>
            </div>
        </div>
    </div>

    <!-- Detail Modal -->
    <div x-show="detail" x-cloak class="modal-overlay" @click.self="detail = null">
        <div class="modal-box" style="max-width:720px">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
                <h3 style="margin:0">Chi tiết log</h3>
                <button class="btn btn--ghost btn--sm" @click="detail = null">✕</button>
            </div>
            <template x-if="detail">
                <div>
                    <!-- Meta badges -->
                    <div style="display:flex;gap:8px;margin-bottom:10px;flex-wrap:wrap;align-items:center">
                        <span class="badge" :class="levelBadge(detail.level)" x-text="detail.level"></span>
                        <code style="font-size:12px;background:var(--color-bg);padding:2px 8px;border-radius:4px" x-text="detail.channel"></code>
                        <span style="font-size:12px;color:var(--color-text-muted)" x-text="detail.created_at"></span>
                        <span x-show="detail.ip_address" style="font-size:12px;color:var(--color-text-muted)">IP: <span x-text="detail.ip_address"></span></span>
                    </div>

                    <!-- Title -->
                    <div style="font-weight:600;font-size:14px;margin-bottom:12px;word-break:break-word" x-text="detail.title"></div>

                    <!-- Request line -->
                    <template x-if="detail.url">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;padding:8px 12px;background:var(--color-bg);border-radius:8px;font-size:13px">
                            <span style="font-weight:600;font-family:monospace;font-size:11px;padding:2px 6px;border-radius:4px;background:var(--color-border)" x-text="detail.method?.toUpperCase()"></span>
                            <code style="flex:1;word-break:break-all" x-text="detail.url"></code>
                        </div>
                    </template>

                    <!-- GET Params -->
                    <template x-if="detail.context?._request?.params && Object.keys(detail.context._request.params).length > 0">
                        <div style="margin-bottom:12px">
                            <div class="log-section-label">Query Params</div>
                            <div class="log-kv-grid">
                                <template x-for="[k, v] in Object.entries(detail.context._request.params)" :key="k">
                                    <div class="log-kv-row">
                                        <span class="log-kv-key" x-text="k"></span>
                                        <span class="log-kv-val" x-text="v"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>

                    <!-- POST Payload -->
                    <template x-if="detail.context?._request?.payload && Object.keys(detail.context._request.payload).length > 0">
                        <div style="margin-bottom:12px">
                            <div class="log-section-label">Request Payload</div>
                            <div class="log-kv-grid">
                                <template x-for="[k, v] in Object.entries(detail.context._request.payload)" :key="k">
                                    <div class="log-kv-row">
                                        <span class="log-kv-key" x-text="k"></span>
                                        <span class="log-kv-val"
                                              :style="v === '[MASKED]' ? 'color:var(--color-text-muted);font-style:italic' : ''"
                                              x-text="typeof v === 'object' ? JSON.stringify(v) : v"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>

                    <!-- Custom context (everything except _request) -->
                    <template x-if="detail.context && Object.keys(detail.context).filter(k => k !== '_request').length > 0">
                        <div style="margin-bottom:12px">
                            <div class="log-section-label">Context</div>
                            <pre class="log-pre" x-text="JSON.stringify(
                                Object.fromEntries(Object.entries(detail.context).filter(([k]) => k !== '_request')),
                                null, 2
                            )"></pre>
                        </div>
                    </template>

                    <!-- Message / Stack trace -->
                    <template x-if="detail.message">
                        <div>
                            <div class="log-section-label">Stack Trace / Message</div>
                            <pre class="log-pre" x-text="detail.message"></pre>
                        </div>
                    </template>
                </div>
            </template>
        </div>
    </div>
</div>

<style>
.row--unseen td { background: #fffbf0; }
.row--unseen:hover td { background: #fff8e6 !important; }

.log-section-label {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--color-text-muted);
    margin-bottom: 6px;
}

.log-kv-grid {
    background: var(--color-bg);
    border-radius: 8px;
    overflow: hidden;
    border: 1px solid var(--color-border);
}

.log-kv-row {
    display: grid;
    grid-template-columns: 160px 1fr;
    gap: 0;
    border-bottom: 1px solid var(--color-border);
    font-size: 13px;
}
.log-kv-row:last-child { border-bottom: none; }

.log-kv-key {
    padding: 7px 12px;
    font-family: monospace;
    font-size: 12px;
    color: var(--color-text-muted);
    background: rgba(0,0,0,.02);
    border-right: 1px solid var(--color-border);
    word-break: break-word;
}

.log-kv-val {
    padding: 7px 12px;
    word-break: break-all;
    font-family: monospace;
    font-size: 12px;
}

.log-pre {
    background: var(--color-bg);
    padding: 12px;
    border-radius: 8px;
    font-size: 12px;
    overflow-x: auto;
    white-space: pre-wrap;
    word-break: break-all;
    margin: 0;
    border: 1px solid var(--color-border);
    max-height: 300px;
    overflow-y: auto;
}

@media (max-width: 639px) {
    .log-kv-row { grid-template-columns: 1fr; }
    .log-kv-key { border-right: none; border-bottom: 1px solid var(--color-border); }
}
</style>

<script>
function systemLogApp() {
    return {
        records: [],
        pagination: { total: 0, page: 1, per_page: 30, total_pages: 0 },
        filters: { q: '', level: '', channel: '', seen: '' },
        loading: false,
        detail: null,
        unseenCount: 0,

        async load() {
            this.loading = true;
            const params = new URLSearchParams({
                page: this.pagination.page,
                per_page: this.pagination.per_page,
                ...Object.fromEntries(Object.entries(this.filters).filter(([, v]) => v !== ''))
            });
            const data = await apiGet('/api/admin/system-logs?' + params);
            if (data?.status === 'success') {
                this.records    = data.data.records;
                this.pagination = data.data.pagination;
            }
            await this.loadUnseenCount();
            this.loading = false;
        },

        async loadUnseenCount() {
            const data = await apiGet('/api/admin/system-logs/stats');
            if (data?.status === 'success') this.unseenCount = data.data.unseen;
        },

        async viewDetail(log) {
            const data = await apiGet('/api/admin/system-logs/' + log.id);
            if (data?.status === 'success') {
                this.detail = data.data;
                if (log.seen == 0) { log.seen = 1; this.unseenCount = Math.max(0, this.unseenCount - 1); }
            }
        },

        async deleteLog(id) {
            const ok = await showConfirm({ title: 'Xóa log', message: 'Xóa log này?', type: 'danger', confirmText: 'Xóa' });
            if (!ok) return;
            await apiRequest('/api/admin/system-logs/' + id, { method: 'DELETE' });
            this.records = this.records.filter(r => r.id !== id);
            this.pagination.total--;
        },

        async markAllSeen() {
            await apiPost('/api/admin/system-logs/mark-seen');
            this.records.forEach(r => r.seen = 1);
            this.unseenCount = 0;
            showToast('success', 'Đã đánh dấu tất cả đã xem');
        },

        async confirmClearAll() {
            const ok = await showConfirm({
                title: 'Xóa tất cả log',
                message: 'Hành động này sẽ xóa toàn bộ nhật ký. Không thể hoàn tác!',
                type: 'danger', confirmText: 'Xóa tất cả'
            });
            if (!ok) return;
            await apiRequest('/api/admin/system-logs', { method: 'DELETE' });
            this.records = []; this.pagination.total = 0; this.unseenCount = 0;
            showToast('success', 'Đã xóa toàn bộ log');
        },

        goPage(p) {
            if (p < 1 || p > this.pagination.total_pages) return;
            this.pagination.page = p;
            this.load();
        },

        levelBadge(level) {
            return {
                debug:    'badge--secondary',
                info:     'badge--info',
                warning:  'badge--warning',
                error:    'badge--danger',
                critical: 'badge--danger',
            }[level] || 'badge--secondary';
        },
    };
}
</script>

<?= $this->endSection() ?>
