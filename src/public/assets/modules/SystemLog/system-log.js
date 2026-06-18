// ==========================================================================
// System Log
// ==========================================================================

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
