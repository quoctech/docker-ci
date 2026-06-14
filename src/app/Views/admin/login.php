<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - BladeEngine Admin</title>
    <link rel="stylesheet" href="/assets/css/admin.css">
    <script defer src="/assets/js/alpine.min.js"></script>
</head>
<body>

<div class="login-page" x-data="loginForm()" x-init="checkAlreadyLoggedIn()">
    <div class="login-card">
        <div class="login-card__logo">
            <h1>⚡ BladeEngine</h1>
            <p>Đăng nhập hệ thống quản trị</p>
        </div>

        <form @submit.prevent="submit()">
            <div class="form-group">
                <label for="identifier">Tài khoản</label>
                <input
                    type="text"
                    id="identifier"
                    class="form-input"
                    :class="{ 'form-input--error': errors.identifier }"
                    x-model="form.identifier"
                    placeholder="Email, tên đăng nhập hoặc số điện thoại"
                    autocomplete="username"
                    required>
                <div class="form-error" x-show="errors.identifier" x-text="errors.identifier"></div>
            </div>

            <div class="form-group">
                <label for="password">Mật khẩu</label>
                <input
                    type="password"
                    id="password"
                    class="form-input"
                    :class="{ 'form-input--error': errors.password }"
                    x-model="form.password"
                    placeholder="Nhập mật khẩu"
                    autocomplete="current-password"
                    required>
                <div class="form-error" x-show="errors.password" x-text="errors.password"></div>
            </div>

            <!-- Error message -->
            <div class="form-group" x-show="errorMessage"
                 style="background:#FEE2E2;color:#DC2626;padding:10px 12px;border-radius:6px;font-size:13px">
                <span x-text="errorMessage"></span>
            </div>

            <button type="submit" class="btn btn--primary btn--full" :disabled="loading">
                <span x-show="!loading">Đăng nhập</span>
                <span x-show="loading">Đang xử lý...</span>
            </button>
        </form>
    </div>
</div>

<script>
function checkAlreadyLoggedIn() {
    const token = localStorage.getItem('access_token');
    if (!token) return;
    const redirect = new URLSearchParams(window.location.search).get('redirect');
    window.location.replace(safeRedirect(redirect));
}

function safeRedirect(url) {
    // Chỉ cho phép redirect nội bộ /admin/*
    if (url && url.startsWith('/admin') && !url.startsWith('/admin/login')) {
        return url;
    }
    return '/admin';
}

function loginForm() {
    return {
        form: { identifier: '', password: '' },
        errors: {},
        errorMessage: '',
        loading: false,

        async submit() {
            this.errors = {};
            this.errorMessage = '';
            this.loading = true;

            try {
                const body = new URLSearchParams(this.form);
                const res = await fetch('/api/auth/login', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: body
                });

                const data = await res.json();

                if (data.status === 'success') {
                    localStorage.setItem('access_token', data.data.access_token);
                    localStorage.setItem('user', JSON.stringify(data.data.user));

                    const redirect = new URLSearchParams(window.location.search).get('redirect');
                    window.location.href = safeRedirect(redirect);
                } else {
                    if (data.errors) {
                        this.errors = data.errors;
                    } else {
                        this.errorMessage = data.message;
                    }
                }
            } catch (e) {
                this.errorMessage = 'Lỗi kết nối. Vui lòng thử lại.';
            } finally {
                this.loading = false;
            }
        }
    };
}
</script>

</body>
</html>
