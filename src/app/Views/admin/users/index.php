<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Quản lý người dùng<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>Hệ thống / Quản lý người dùng<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div x-data="userManager()" x-init="loadUsers()">
    <!-- Header -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px">
        <div>
            <h1 class="content__title">Quản lý người dùng</h1>
            <p class="content__subtitle">Quản lý tài khoản, phân quyền và trạng thái người dùng.</p>
        </div>
        <button class="btn btn--primary btn--sm" @click="openCreateModal()">+ Thêm người dùng</button>
    </div>

    <!-- Filters -->
    <div class="card" style="margin-bottom:20px">
        <div class="card__body" style="padding:12px 20px;display:flex;gap:12px;flex-wrap:wrap;align-items:center">
            <input class="form-input" style="max-width:250px;height:36px"
                   placeholder="Tìm kiếm..."
                   x-model="filters.search"
                   @input.debounce.400ms="loadUsers()">
            <select class="form-input" style="max-width:150px;height:36px" x-model="filters.role" @change="loadUsers()">
                <option value="">Tất cả quyền</option>
                <option value="super_admin">Super Admin</option>
                <option value="workspace_admin">Giáo viên</option>
                <option value="user">Người dùng</option>
            </select>
            <select class="form-input" style="max-width:150px;height:36px" x-model="filters.status" @change="loadUsers()">
                <option value="">Tất cả trạng thái</option>
                <option value="active">Hoạt động</option>
                <option value="locked">Bị khóa</option>
                <option value="pending">Chờ duyệt</option>
            </select>
            <span style="font-size:12px;color:var(--color-text-muted)" x-text="'Tổng: ' + pagination.total + ' người dùng'"></span>
        </div>
    </div>

    <!-- Users table -->
    <div class="card">
        <div class="card__body" style="padding:0">
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Người dùng</th>
                            <th>Liên hệ</th>
                            <th>Quyền</th>
                            <th>Trạng thái</th>
                            <th>Đăng nhập gần nhất</th>
                            <th style="width:120px">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="u in users" :key="u.id">
                            <tr>
                                <td>
                                    <div style="display:flex;align-items:center;gap:10px">
                                        <!-- Avatar -->
                                        <div style="width:36px;height:36px;border-radius:50%;background:var(--color-primary-light);display:flex;align-items:center;justify-content:center;overflow:hidden;flex-shrink:0">
                                            <img :src="u.avatar_url || ''" x-show="u.avatar_url" style="width:100%;height:100%;object-fit:cover">
                                            <span x-show="!u.avatar_url" style="font-size:14px;font-weight:600;color:var(--color-primary)" x-text="u.full_name.charAt(0).toUpperCase()"></span>
                                        </div>
                                        <div>
                                            <div style="font-weight:500" x-text="u.full_name"></div>
                                            <div style="font-size:11px;color:var(--color-text-muted)" x-text="u.username ? '@' + u.username : ''"></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-size:13px" x-text="u.email"></div>
                                    <div style="font-size:11px;color:var(--color-text-muted)" x-text="u.phone || ''"></div>
                                </td>
                                <td>
                                    <span class="badge"
                                          :class="u.role === 'super_admin' ? 'badge--danger' : u.role === 'workspace_admin' ? 'badge--warning' : 'badge--info'"
                                          x-text="roleLabel(u.role)"></span>
                                </td>
                                <td>
                                    <span class="badge"
                                          :class="u.status === 'active' ? 'badge--success' : u.status === 'locked' ? 'badge--danger' : 'badge--warning'"
                                          x-text="statusLabel(u.status)"></span>
                                </td>
                                <td style="font-size:12px;color:var(--color-text-muted)" x-text="u.last_login || '—'"></td>
                                <td>
                                    <div style="display:flex;gap:4px">
                                        <button class="btn btn--ghost btn--sm" @click="openEditModal(u)" title="Chỉnh sửa">✏</button>
                                        <button class="btn btn--ghost btn--sm" @click="toggleStatus(u)" :title="u.status === 'active' ? 'Khóa' : 'Mở khóa'">
                                            <span x-text="u.status === 'active' ? '🔒' : '🔓'"></span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <!-- Empty state -->
            <div x-show="users.length === 0 && !loading" style="padding:40px;text-align:center;color:var(--color-text-muted)">
                Không tìm thấy người dùng nào.
            </div>

            <!-- Pagination -->
            <div x-show="pagination.total_pages > 1" style="padding:12px 20px;display:flex;justify-content:center;gap:4px;border-top:1px solid var(--color-border)">
                <button class="btn btn--ghost btn--sm" @click="goPage(pagination.page - 1)" :disabled="pagination.page <= 1">← Trước</button>
                <span style="padding:6px 12px;font-size:13px" x-text="pagination.page + ' / ' + pagination.total_pages"></span>
                <button class="btn btn--ghost btn--sm" @click="goPage(pagination.page + 1)" :disabled="pagination.page >= pagination.total_pages">Sau →</button>
            </div>
        </div>
    </div>

    <!-- Edit/Create Modal -->
    <div x-show="showModal" x-cloak
         class="confirm-overlay"
         @click.self="showModal = false">
        <div class="card" style="width:100%;max-width:600px;max-height:90vh;overflow-y:auto">
            <div class="card__header">
                <h3 x-text="editingUser ? 'Chỉnh sửa người dùng' : 'Thêm người dùng mới'"></h3>
                <button class="btn btn--ghost btn--sm" @click="showModal = false">✕</button>
            </div>
            <div class="card__body">
                <!-- Avatar upload (chỉ khi edit) -->
                <div x-show="editingUser" style="text-align:center;margin-bottom:20px">
                    <div style="width:80px;height:80px;border-radius:50%;background:var(--color-primary-light);display:inline-flex;align-items:center;justify-content:center;overflow:hidden;margin-bottom:8px">
                        <img :src="editingUser?.avatar_url || ''" x-show="editingUser?.avatar_url" style="width:100%;height:100%;object-fit:cover">
                        <span x-show="!editingUser?.avatar_url" style="font-size:28px;font-weight:600;color:var(--color-primary)" x-text="form.full_name ? form.full_name.charAt(0).toUpperCase() : '?'"></span>
                    </div>
                    <div style="display:flex;gap:8px;justify-content:center">
                        <label class="btn btn--secondary btn--sm" style="cursor:pointer">
                            📷 Đổi avatar
                            <input type="file" accept="image/png,image/jpeg,image/webp" style="display:none" @change="uploadAvatar($event)">
                        </label>
                        <button x-show="editingUser?.avatar_url" class="btn btn--danger btn--sm" @click="removeAvatar()">Xóa</button>
                    </div>
                </div>

                <form @submit.prevent="saveUser()">
                    <div class="form-group">
                        <label>Họ tên *</label>
                        <input class="form-input" x-model="form.full_name" required>
                    </div>
                    <div class="form-group">
                        <label>Email *</label>
                        <input class="form-input" type="email" x-model="form.email" :disabled="!!editingUser" required>
                    </div>
                    <div style="display:flex;gap:12px">
                        <div class="form-group" style="flex:1">
                            <label>Username</label>
                            <input class="form-input" x-model="form.username" placeholder="Tùy chọn">
                        </div>
                        <div class="form-group" style="flex:1">
                            <label>Số điện thoại</label>
                            <input class="form-input" x-model="form.phone" placeholder="Tùy chọn">
                        </div>
                    </div>
                    <div style="display:flex;gap:12px">
                        <div class="form-group" style="flex:1">
                            <label>Quyền *</label>
                            <select class="form-input" x-model="form.role">
                                <option value="user">Người dùng</option>
                                <option value="workspace_admin">Giáo viên</option>
                                <option value="super_admin">Super Admin</option>
                            </select>
                        </div>
                        <div class="form-group" style="flex:1" x-show="!editingUser">
                            <label>Mật khẩu *</label>
                            <input class="form-input" type="password" x-model="form.password" :required="!editingUser">
                        </div>
                    </div>

                    <button type="submit" class="btn btn--primary btn--full" :disabled="saving">
                        <span x-text="saving ? 'Đang lưu...' : (editingUser ? 'Cập nhật' : 'Tạo người dùng')"></span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function userManager() {
    return {
        users: [],
        pagination: { page: 1, per_page: 20, total: 0, total_pages: 0 },
        filters: { search: '', role: '', status: '' },
        loading: false,
        showModal: false,
        editingUser: null,
        saving: false,
        form: { full_name: '', email: '', username: '', phone: '', role: 'user', password: '' },

        roleLabel(role) {
            const map = { super_admin: 'Super Admin', workspace_admin: 'Giáo viên', user: 'Người dùng' };
            return map[role] || role;
        },

        statusLabel(status) {
            const map = { active: 'Hoạt động', locked: 'Bị khóa', pending: 'Chờ duyệt' };
            return map[status] || status;
        },

        async loadUsers() {
            this.loading = true;
            const params = new URLSearchParams({
                page: this.pagination.page,
                per_page: this.pagination.per_page,
            });
            if (this.filters.search) params.set('search', this.filters.search);
            if (this.filters.role) params.set('role', this.filters.role);
            if (this.filters.status) params.set('status', this.filters.status);

            const data = await apiGet('/api/admin/users?' + params.toString());
            if (data && data.status === 'success') {
                this.users = data.data.users;
                this.pagination = data.data.pagination;
            }
            this.loading = false;
        },

        goPage(page) {
            if (page < 1 || page > this.pagination.total_pages) return;
            this.pagination.page = page;
            this.loadUsers();
        },

        openCreateModal() {
            this.editingUser = null;
            this.form = { full_name: '', email: '', username: '', phone: '', role: 'user', password: '' };
            this.showModal = true;
        },

        openEditModal(user) {
            this.editingUser = user;
            this.form = {
                full_name: user.full_name,
                email: user.email,
                username: user.username || '',
                phone: user.phone || '',
                role: user.role,
                password: '',
            };
            this.showModal = true;
        },

        async saveUser() {
            this.saving = true;

            if (this.editingUser) {
                // Update existing user
                const body = new URLSearchParams({
                    full_name: this.form.full_name,
                    username: this.form.username,
                    phone: this.form.phone,
                });
                const data = await apiPut('/api/admin/users/' + this.editingUser.id, body);

                if (data && data.status === 'success') {
                    // Also update role if changed
                    if (this.form.role !== this.editingUser.role) {
                        await apiPut('/api/admin/users/' + this.editingUser.id + '/role',
                            new URLSearchParams({ role: this.form.role }));
                    }
                    showToast('success', 'Đã cập nhật người dùng.');
                    this.showModal = false;
                    this.loadUsers();
                } else {
                    showToast('error', data ? data.message : 'Lỗi cập nhật');
                }
            } else {
                // Create new user
                const body = new URLSearchParams(this.form);
                const data = await apiPost('/api/admin/users', body);

                if (data && data.status === 'success') {
                    showToast('success', 'Đã tạo người dùng.');
                    this.showModal = false;
                    this.loadUsers();
                } else {
                    showToast('error', data ? data.message : 'Lỗi tạo người dùng');
                }
            }

            this.saving = false;
        },

        async toggleStatus(user) {
            const newStatus = user.status === 'active' ? 'locked' : 'active';
            const isLocking = newStatus === 'locked';

            const confirmed = await showConfirm({
                title: isLocking ? 'Khóa tài khoản' : 'Mở khóa tài khoản',
                message: isLocking
                    ? 'Người dùng sẽ bị đăng xuất ngay lập tức và không thể đăng nhập. Bạn chắc chắn?'
                    : 'Người dùng sẽ có thể đăng nhập lại. Bạn chắc chắn?',
                type: isLocking ? 'danger' : 'info',
                confirmText: isLocking ? 'Khóa' : 'Mở khóa'
            });

            if (!confirmed) return;

            const data = await apiPut('/api/admin/users/' + user.id + '/status',
                new URLSearchParams({ status: newStatus }));

            if (data && data.status === 'success') {
                user.status = newStatus;
                showToast('success', isLocking ? 'Đã khóa tài khoản.' : 'Đã mở khóa tài khoản.');
            } else {
                showToast('error', data ? data.message : 'Thao tác thất bại');
            }
        },

        async uploadAvatar(event) {
            const file = event.target.files[0];
            if (!file) return;

            const formData = new FormData();
            formData.append('avatar', file);

            const token = getToken();
            try {
                const res = await fetch('/api/admin/users/' + this.editingUser.id + '/avatar', {
                    method: 'POST',
                    headers: { 'Authorization': 'Bearer ' + token },
                    body: formData
                });
                const data = await res.json();
                if (data.status === 'success') {
                    this.editingUser.avatar_url = data.data.avatar_url;
                    showToast('success', 'Đã cập nhật avatar.');
                    this.loadUsers();
                } else {
                    showToast('error', data.message || 'Upload thất bại');
                }
            } catch (e) {
                showToast('error', 'Lỗi kết nối');
            }
            // Reset input
            event.target.value = '';
        },

        async removeAvatar() {
            const confirmed = await showConfirm({
                title: 'Xóa avatar',
                message: 'Avatar sẽ bị xóa vĩnh viễn. Bạn chắc chắn?',
                type: 'warning',
                confirmText: 'Xóa'
            });
            if (!confirmed) return;

            const data = await apiDelete('/api/admin/users/' + this.editingUser.id + '/avatar');
            if (data && data.status === 'success') {
                this.editingUser.avatar_url = null;
                this.editingUser.avatar = null;
                showToast('success', 'Đã xóa avatar.');
                this.loadUsers();
            }
        }
    };
}
</script>

<?= $this->endSection() ?>
