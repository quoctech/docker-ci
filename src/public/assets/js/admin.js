/**
 * Admin JS - Shared utilities cho admin panel.
 *
 * Cung cấp:
 * - API helper functions (apiGet, apiPost, apiPut, apiDelete)
 * - Toast notification system
 * - Admin app Alpine.js component (sidebar, auth check)
 * - Token auto-refresh và redirect on 401
 */

// ==========================================================================
// API HELPERS
// ==========================================================================

/**
 * Lấy access token từ localStorage.
 * @returns {string|null}
 */
function getToken() {
    return localStorage.getItem('access_token');
}

/**
 * Gọi API với method GET.
 * Tự động gắn Authorization header và xử lý 401.
 *
 * @param {string} url API endpoint path
 * @returns {Promise<object|null>} JSON response hoặc null nếu lỗi
 */
async function apiGet(url) {
    return apiRequest(url, { method: 'GET' });
}

/**
 * Gọi API với method POST.
 *
 * @param {string} url  API endpoint path
 * @param {URLSearchParams|null} body Request body
 * @returns {Promise<object|null>}
 */
async function apiPost(url, body = null) {
    const opts = { method: 'POST' };
    if (body) {
        opts.body = body;
        opts.headers = { 'Content-Type': 'application/x-www-form-urlencoded' };
    }
    return apiRequest(url, opts);
}

/**
 * Gọi API với method PUT.
 *
 * @param {string} url  API endpoint path
 * @param {URLSearchParams|null} body Request body
 * @returns {Promise<object|null>}
 */
async function apiPut(url, body = null) {
    const opts = { method: 'PUT' };
    if (body) {
        opts.body = body;
        opts.headers = { 'Content-Type': 'application/x-www-form-urlencoded' };
    }
    return apiRequest(url, opts);
}

/**
 * Gọi API với method DELETE.
 *
 * @param {string} url API endpoint path
 * @returns {Promise<object|null>}
 */
async function apiDelete(url) {
    return apiRequest(url, { method: 'DELETE' });
}

/**
 * Base API request handler.
 * Tự động gắn token, xử lý 401 (redirect login).
 *
 * @param {string} url     API endpoint
 * @param {object} options Fetch options (method, body, headers)
 * @returns {Promise<object|null>}
 */
async function apiRequest(url, options = {}) {
    const token = getToken();

    if (!token) {
        window.location.href = '/admin/login';
        return null;
    }

    // Merge headers: giữ content-type nếu có, thêm Authorization
    options.headers = {
        ...options.headers,
        'Authorization': 'Bearer ' + token
    };

    try {
        const res = await fetch(url, options);

        // Token hết hạn hoặc bị revoke → redirect login
        if (res.status === 401) {
            localStorage.removeItem('access_token');
            localStorage.removeItem('user');
            window.location.href = '/admin/login';
            return null;
        }

        return await res.json();
    } catch (e) {
        console.error('API request failed:', e);
        return null;
    }
}

// ==========================================================================
// TOAST NOTIFICATION
// ==========================================================================

/** @type {Array} Global toast array (Alpine reactive) */
let _toastContainer = null;

/**
 * Hiển thị toast notification.
 *
 * @param {string} type    'success' | 'error' | 'warning'
 * @param {string} message Nội dung thông báo
 * @param {number} duration Thời gian hiển thị (ms), default 3000
 */
function showToast(type, message, duration = 3000) {
    if (_toastContainer) {
        const toast = { type, message, visible: true };
        _toastContainer.push(toast);

        // Auto dismiss sau duration
        setTimeout(() => {
            toast.visible = false;
            // Cleanup sau animation
            setTimeout(() => {
                const idx = _toastContainer.indexOf(toast);
                if (idx > -1) _toastContainer.splice(idx, 1);
            }, 300);
        }, duration);
    }
}

// ==========================================================================
// ADMIN APP COMPONENT
// ==========================================================================

/**
 * Alpine.js component chính cho admin layout.
 * Quản lý sidebar state, user info, logout.
 */
function adminApp() {
    return {
        sidebarOpen: false,
        user: null,
        toasts: [],

        init() {
            // Check authentication
            const token = getToken();
            if (!token) {
                window.location.href = '/admin/login';
                return;
            }

            // Load user info từ localStorage
            const userStr = localStorage.getItem('user');
            if (userStr) {
                this.user = JSON.parse(userStr);
            }

            // Register toast container cho global access
            _toastContainer = this.toasts;
        },

        /**
         * Đăng xuất: gọi API logout, xóa token, redirect.
         */
        async logout() {
            await apiPost('/api/auth/logout');
            localStorage.removeItem('access_token');
            localStorage.removeItem('user');
            window.location.href = '/admin/login';
        }
    };
}
