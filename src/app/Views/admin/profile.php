<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Hồ sơ cá nhân<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>Tài khoản / Hồ sơ cá nhân<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div x-data="profileManager()" x-init="loadProfile()">
    <div style="margin-bottom:24px">
        <h1 class="content__title">Hồ sơ cá nhân</h1>
        <p class="content__subtitle">Quản lý thông tin tài khoản của bạn.</p>
    </div>

    <div style="display:grid;grid-template-columns:1fr 2fr;gap:24px">
        <!-- Avatar Card -->
        <div class="card">
            <div class="card__body" style="text-align:center;padding:32px">
                <div style="width:100px;height:100px;border-radius:50%;background:var(--color-primary-light);display:inline-flex;align-items:center;justify-content:center;overflow:hidden;margin-bottom:16px">
                    <img :src="profile.avatar_url || ''" x-show="profile.avatar_url" style="width:100%;height:100%;object-fit:cover">
                    <span x-show="!profile.avatar_url" style="font-size:36px;font-weight:600;color:var(--color-primary)" x-text="profile.full_name ? profile.full_name.charAt(0).toUpperCase() : '?'"></span>
                </div>
                <h3 x-text="profile.full_name" style="margin-bottom:4px"></h3>
                <p style="font-size:12px;color:var(--color-text-muted)" x-text="'@' + (profile.username || profile.email)"></p>
                <span class="badge badge--info" x-text="profile.role" style="margin-top:8px"></span>

                <div style="margin-top:20px;display:flex;flex-direction:column;gap:8px">
                    <label class="btn btn--secondary btn--sm btn--full" style="cursor:pointer">
                        📷 Đổi avatar
                        <input type="file" accept="image/png,image/jpeg,image/webp" style="display:none" @change="uploadMyAvatar($event)">
                    </label>
                    <button x-show="profile.avatar_url" class="btn btn--danger btn--sm btn--full" @click="removeMyAvatar()">Xóa avatar</button>
                </div>
            </div>
        </div>

        <!-- Profile Form -->
        <div class="card">
            <div class="card__header">
                <h3>Thông tin cá nhân</h3>
            </div>
            <div class="card__body">
                <form @submit.prevent="saveProfile()">
                    <div class="form-group">
                        <label>Họ tên</label>
                        <input class="form-input" x-model="form.full_name" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input class="form-input" type="email" :value="profile.email" disabled style="opacity:0.6">
                        <div style="font-size:11px;color:var(--color-text-muted);margin-top:4px">Email không thể thay đổi.</div>
                    </div>
                    <div style="display:flex;gap:12px">
                        <div class="form-group" style="flex:1">
                            <label>Username</label>
                            <input class="form-input" x-model="form.username" placeholder="Tên đăng nhập">
                        </div>
                        <div class="form-group" style="flex:1">
                            <label>Số điện thoại</label>
                            <input class="form-input" x-model="form.phone" placeholder="Số điện thoại">
                        </div>
                    </div>
                    <button type="submit" class="btn btn--primary" :disabled="saving">
                        <span x-text="saving ? 'Đang lưu...' : 'Lưu thay đổi'"></span>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Change Password -->
    <div class="card" style="margin-top:24px">
        <div class="card__header">
            <h3>Đổi mật khẩu</h3>
        </div>
        <div class="card__body">
            <form @submit.prevent="changePassword()" style="max-width:400px">
                <div class="form-group">
                    <label>Mật khẩu hiện tại</label>
                    <input class="form-input" type="password" x-model="pwForm.current_password" required>
                </div>
                <div class="form-group">
                    <label>Mật khẩu mới</label>
                    <input class="form-input" type="password" x-model="pwForm.new_password" required>
                </div>
                <button type="submit" class="btn btn--primary" :disabled="changingPw">
                    <span x-text="changingPw ? 'Đang đổi...' : 'Đổi mật khẩu'"></span>
                </button>
            </form>
        </div>
    </div>
</div>

<script>
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
            // Dùng API admin update user (tự update chính mình qua ID trong token)
            const meData = await apiGet('/api/auth/me');
            if (!meData || meData.status !== 'success') { this.saving = false; return; }

            const userId = meData.data.uuid; // need actual ID
            // Gọi API users list để lấy ID
            const usersData = await apiGet('/api/admin/users?search=' + encodeURIComponent(this.profile.email));
            if (!usersData || usersData.status !== 'success' || !usersData.data.users.length) {
                showToast('error', 'Không tìm thấy tài khoản.');
                this.saving = false;
                return;
            }

            const myId = usersData.data.users[0].id;
            const body = new URLSearchParams({
                full_name: this.form.full_name,
                username: this.form.username,
                phone: this.form.phone,
            });
            const data = await apiPut('/api/admin/users/' + myId, body);
            if (data && data.status === 'success') {
                showToast('success', 'Đã cập nhật hồ sơ.');
                // Update localStorage user
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

            // Lấy user ID
            const usersData = await apiGet('/api/admin/users?search=' + encodeURIComponent(this.profile.email));
            if (!usersData || !usersData.data.users.length) return;
            const myId = usersData.data.users[0].id;

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
                    // Update localStorage
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

            const usersData = await apiGet('/api/admin/users?search=' + encodeURIComponent(this.profile.email));
            if (!usersData || !usersData.data.users.length) return;
            const myId = usersData.data.users[0].id;

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
</script>

<?= $this->endSection() ?>
