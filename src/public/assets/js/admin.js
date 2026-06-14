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

async function apiRequest(url, options = {}) {
    const token = getToken();
    if (!token) {
        window.location.href = '/admin/login';
        return null;
    }
    options.headers = { ...options.headers, 'Authorization': 'Bearer ' + token };
    try {
        const res = await fetch(url, options);
        if (res.status === 401) {
            localStorage.removeItem('access_token');
            localStorage.removeItem('user');
            window.location.href = '/admin/login';
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
                window.location.href = '/admin/login';
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
