// ==========================================================================
// AWESOME BAR (CTRL+K)
// ==========================================================================

function awesomeBar() {
    return {
        open: false,
        query: '',
        results: { pages: [], users: [], modules: [], configs: [] },
        activeIndex: -1,
        loading: false,
        _debounce: null,

        get flatItems() {
            const items = [];
            const push = (cat, list) => list.forEach(i => items.push({ ...i, _cat: cat }));
            push('pages',   this.results.pages   || []);
            push('users',   this.results.users   || []);
            push('modules', this.results.modules || []);
            push('configs', this.results.configs || []);
            return items;
        },

        get hasResults() {
            return this.flatItems.length > 0;
        },

        toggle() {
            this.open ? this.close() : this.show();
        },

        show() {
            this.open  = true;
            this.query = '';
            this.activeIndex = -1;
            this.fetchResults('');
            this.$nextTick(() => this.$refs.awesomeInput?.focus());
        },

        close() {
            this.open  = false;
            this.query = '';
            this.activeIndex = -1;
        },

        onInput() {
            clearTimeout(this._debounce);
            this._debounce = setTimeout(() => this.fetchResults(this.query), 200);
        },

        async fetchResults(q) {
            this.loading = true;
            this.activeIndex = -1;
            const data = await apiGet('/api/admin/search?q=' + encodeURIComponent(q));
            if (data?.status === 'success') {
                this.results = data.data;
            }
            this.loading = false;
        },

        moveActive(dir) {
            const len = this.flatItems.length;
            if (len === 0) return;
            this.activeIndex = (this.activeIndex + dir + len) % len;
        },

        confirmActive() {
            const item = this.flatItems[this.activeIndex];
            if (!item) return;
            this.navigate(item);
        },

        navigate(item) {
            if (item.type === 'action' && item.title === 'Đăng xuất') {
                this.close();
                // Trigger adminApp logout
                const appEl = document.querySelector('[x-data="adminApp()"]');
                if (appEl && appEl._x_dataStack) {
                    appEl._x_dataStack[0].logout?.();
                }
                return;
            }
            if (item.url) {
                this.close();
                window.location.href = item.url;
            }
        },

        groupLabel(cat) {
            return { pages: 'Trang', users: 'Người dùng', modules: 'Module', configs: 'Cài đặt' }[cat] || cat;
        },

        // Group results preserving order
        get groupedItems() {
            const groups = [];
            const order  = ['pages', 'users', 'modules', 'configs'];
            let   idx    = 0;
            for (const cat of order) {
                const list = this.results[cat] || [];
                if (!list.length) continue;
                groups.push({ cat, label: this.groupLabel(cat), items: list.map(i => ({ ...i, _flatIdx: idx++ })) });
            }
            return groups;
        },
    };
}
