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
        user: (() => {
            try { return JSON.parse(localStorage.getItem('user') || 'null'); } catch (e) { return null; }
        })(),
        toasts: [],
        confirmDialog: { show: false, title: '', message: '', type: 'danger', confirmText: 'Xác nhận', _resolve: null },
        userModules: null, // null = tất cả (super_admin), array = slugs được phép

        async init() {
            const token = getToken();
            if (!token) {
                redirectToLogin();
                return;
            }
            _toastContainer = this.toasts;
            _confirmApp = this;

            const data = await apiGet('/api/auth/my-modules');
            if (data?.status === 'success') {
                this.userModules = data.data.all ? null : (data.data.slugs || []);
            }
        },

        hasModule(slug) {
            return this.userModules === null || this.userModules.includes(slug);
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

// awesomeBar() moved to /assets/modules/AwesomeBar/awesome-bar.js
