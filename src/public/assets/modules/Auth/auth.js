// ==========================================================================
// Auth — Login page
// ==========================================================================

function checkAlreadyLoggedIn() {
    const token = localStorage.getItem('access_token');
    if (!token) return;
    const redirect = new URLSearchParams(window.location.search).get('redirect');
    window.location.replace(safeRedirect(redirect));
}

function safeRedirect(url) {
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

// ==========================================================================
// Auth — Profile page
// ==========================================================================

function profileManager() {
    return {
        profile: {},
        form: { full_name: '', username: '', phone: '' },
        pwForm: { current_password: '', new_password: '' },
        saving: false,
        changingPw: false,

        async loadProfile() {
            const data = await apiGet('/api/auth/me');
            if (data && data.status === 'success') {
                this.profile = data.data;
                this.form.full_name = data.data.full_name;
                this.form.username = data.data.username || '';
                this.form.phone = data.data.phone || '';
            }
        },

        async saveProfile() {
            this.saving = true;
            const myId = this.profile.uuid;
            if (!myId) { this.saving = false; return; }

            const body = new URLSearchParams({
                full_name: this.form.full_name,
                username: this.form.username,
                phone: this.form.phone,
            });
            const data = await apiPut('/api/admin/users/' + myId, body);
            if (data && data.status === 'success') {
                showToast('success', 'Đã cập nhật hồ sơ.');
                const user = JSON.parse(localStorage.getItem('user') || '{}');
                user.full_name = this.form.full_name;
                user.username = this.form.username;
                localStorage.setItem('user', JSON.stringify(user));
                this.profile.full_name = this.form.full_name;
                this.profile.username = this.form.username;
                this.profile.phone = this.form.phone;
            } else {
                showToast('error', data ? data.message : 'Lỗi cập nhật');
            }
            this.saving = false;
        },

        async changePassword() {
            this.changingPw = true;
            const body = new URLSearchParams(this.pwForm);
            const data = await apiPut('/api/auth/change-password', body);
            if (data && data.status === 'success') {
                showToast('success', 'Đã đổi mật khẩu. Vui lòng đăng nhập lại.');
                setTimeout(() => {
                    localStorage.removeItem('access_token');
                    localStorage.removeItem('user');
                    window.location.href = '/admin/login';
                }, 1500);
            } else {
                showToast('error', data ? data.message : 'Đổi mật khẩu thất bại');
            }
            this.changingPw = false;
        },

        async uploadMyAvatar(event) {
            const file = event.target.files[0];
            if (!file) return;

            const myId = this.profile.uuid;
            if (!myId) return;

            const formData = new FormData();
            formData.append('avatar', file);

            const token = getToken();
            try {
                const res = await fetch('/api/admin/users/' + myId + '/avatar', {
                    method: 'POST',
                    headers: { 'Authorization': 'Bearer ' + token },
                    body: formData
                });
                const data = await res.json();
                if (data.status === 'success') {
                    this.profile.avatar_url = data.data.avatar_url;
                    const user = JSON.parse(localStorage.getItem('user') || '{}');
                    user.avatar_url = data.data.avatar_url;
                    localStorage.setItem('user', JSON.stringify(user));
                    showToast('success', 'Đã cập nhật avatar.');
                    location.reload();
                } else {
                    showToast('error', data.message || 'Upload thất bại');
                }
            } catch (e) {
                showToast('error', 'Lỗi kết nối');
            }
            event.target.value = '';
        },

        async removeMyAvatar() {
            const confirmed = await showConfirm({
                title: 'Xóa avatar',
                message: 'Bạn chắc chắn muốn xóa avatar?',
                type: 'warning',
                confirmText: 'Xóa'
            });
            if (!confirmed) return;

            const myId = this.profile.uuid;
            if (!myId) return;

            const data = await apiDelete('/api/admin/users/' + myId + '/avatar');
            if (data && data.status === 'success') {
                this.profile.avatar_url = null;
                const user = JSON.parse(localStorage.getItem('user') || '{}');
                user.avatar_url = null;
                localStorage.setItem('user', JSON.stringify(user));
                showToast('success', 'Đã xóa avatar.');
                location.reload();
            }
        }
    };
}
