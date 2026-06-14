/**
 * Admin JS - Shared utilities cho admin panel.
 */

// ==========================================================================
// API HELPERS
// ==========================================================================

function getToken() {
    return localStorage.getItem('access_token');
}

async function apiGet(url) {
    return apiRequest(url, { method: 'GET' });
}

async function apiPost(url, body = null) {
    const opts = { method: 'POST' };
    if (body) {
        opts.body = body;
        opts.headers = { 'Content-Type': 'application/x-www-form-urlencoded' };
    }
    return apiRequest(url, opts);
}

async function apiPut(url, body = null) {
    const opts = { method: 'PUT' };
    if (body) {
        opts.body = body;
        opts.headers = { 'Content-Type': 'application/x-www-form-urlencoded' };
    }
    return apiRequest(url, opts);
}

async function apiDelete(url) {
    return apiRequest(url, { method: 'DELETE' });
}

function redirectToLogin() {
    const current = window.location.pathname + window.location.search;
    window.location.href = '/admin/login?redirect=' + encodeURIComponent(current);
}

let _refreshing = null;

async function tryRefreshToken() {
    if (_refreshing) return _refreshing;
    _refreshing = fetch('/api/auth/refresh', { method: 'POST', credentials: 'include' })
        .then(async r => {
            const data = await r.json().catch(() => null);
            if (r.ok && data?.data?.access_token) {
                localStorage.setItem('access_token', data.data.access_token);
                if (data.data.user) localStorage.setItem('user', JSON.stringify(data.data.user));
                return true;
            }
            return false;
        })
        .catch(() => false)
        .finally(() => { _refreshing = null; });
    return _refreshing;
}

async function apiRequest(url, options = {}, _retry = false) {
    const token = getToken();
    if (!token) { redirectToLogin(); return null; }
    options.headers = { ...options.headers, 'Authorization': 'Bearer ' + token };
    try {
        const res = await fetch(url, options);
        if (res.status === 401) {
            if (!_retry) {
                const ok = await tryRefreshToken();
                if (ok) {
                    // Clone options tanpa header cũ để attach token mới
                    const { headers: _, ...rest } = options;
                    return apiRequest(url, rest, true);
                }
            }
            localStorage.removeItem('access_token');
            localStorage.removeItem('user');
            redirectToLogin();
            return null;
        }
        return await res.json();
    } catch (e) {
        console.error('API error:', e);
        return null;
    }
}

// ==========================================================================
// TOAST
// ==========================================================================

let _toastContainer = null;

function showToast(type, message, duration = 3000) {
    if (!_toastContainer) return;
    const toast = { type, message, visible: true };
    _toastContainer.push(toast);
    setTimeout(() => {
        toast.visible = false;
        setTimeout(() => {
            const idx = _toastContainer.indexOf(toast);
            if (idx > -1) _toastContainer.splice(idx, 1);
        }, 300);
    }, duration);
}

// ==========================================================================
// CONFIRM DIALOG
// ==========================================================================

let _confirmApp = null;

/**
 * Hiển thị confirm dialog.
 * @param {object} opts { title, message, type: 'danger'|'warning'|'info', confirmText }
 * @returns {Promise<boolean>}
 */
function showConfirm(opts = {}) {
    return new Promise(resolve => {
        if (!_confirmApp) { resolve(false); return; }
        _confirmApp.confirmDialog = {
            show: true,
            title: opts.title || 'Xác nhận',
            message: opts.message || 'Bạn chắc chắn muốn thực hiện?',
            type: opts.type || 'danger',
            confirmText: opts.confirmText || 'Xác nhận',
            _resolve: resolve
        };
    });
}

// ==========================================================================
// ADMIN APP
// ==========================================================================

function adminApp() {
    return {
        sidebarOpen: false,
        user: null,
        toasts: [],
        confirmDialog: { show: false, title: '', message: '', type: 'danger', confirmText: 'Xác nhận', _resolve: null },

        init() {
            const token = getToken();
            if (!token) {
                redirectToLogin();
                return;
            }
            const userStr = localStorage.getItem('user');
            if (userStr) {
                this.user = JSON.parse(userStr);
            }
            _toastContainer = this.toasts;
            _confirmApp = this;
        },

        confirmAction() {
            this.confirmDialog.show = false;
            if (this.confirmDialog._resolve) {
                this.confirmDialog._resolve(true);
                this.confirmDialog._resolve = null;
            }
        },

        cancelAction() {
            this.confirmDialog.show = false;
            if (this.confirmDialog._resolve) {
                this.confirmDialog._resolve(false);
                this.confirmDialog._resolve = null;
            }
        },

        async logout() {
            const confirmed = await showConfirm({
                title: 'Đăng xuất',
                message: 'Bạn muốn đăng xuất khỏi hệ thống?',
                type: 'warning',
                confirmText: 'Đăng xuất'
            });
            if (!confirmed) return;
            await apiPost('/api/auth/logout');
            localStorage.removeItem('access_token');
            localStorage.removeItem('user');
            window.location.href = '/admin/login';
        }
    };
}

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
