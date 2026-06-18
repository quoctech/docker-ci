<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Quản lý người dùng<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>Hệ thống / Quản lý người dùng<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div x-data="userManager()" x-init="loadUsers()"
     x-on:keydown.escape.window="showModal = false; showResetModal = false; showModulesModal = false">
    <!-- Header -->
    <div class="page-header">
        <div>
            <h1 class="content__title">Quản lý người dùng</h1>
            <p class="content__subtitle">Quản lý tài khoản, phân quyền và trạng thái người dùng.</p>
        </div>
        <button class="btn btn--primary btn--sm" @click="openCreateModal()">+ Thêm người dùng</button>
    </div>

    <!-- Filters -->
    <div class="card" style="margin-bottom:20px">
        <div class="filter-bar">
            <input class="form-input filter-bar__search" style="height:36px"
                   placeholder="Tên, email, username, SĐT..."
                   x-model="filters.search"
                   @input.debounce.400ms="loadUsers()">
            <select class="form-input" style="height:36px" x-model="filters.role" @change="loadUsers()">
                <option value="">Tất cả quyền</option>
                <option value="super_admin">Super Admin</option>
                <option value="workspace_admin">Giáo viên</option>
                <option value="user">Người dùng</option>
            </select>
            <select class="form-input" style="height:36px" x-model="filters.status" @change="loadUsers()">
                <option value="">Tất cả trạng thái</option>
                <option value="active">Hoạt động</option>
                <option value="locked">Bị khóa</option>
                <option value="pending">Chờ duyệt</option>
            </select>
            <select class="form-input" style="height:36px" x-model="filters.grade"
                    @change="loadUsers()" x-show="filters.role === 'user' || filters.role === ''">
                <option value="">Tất cả lớp</option>
                <template x-for="g in [1,2,3,4,5,6,7,8,9]" :key="g">
                    <option :value="g" x-text="'Lớp ' + g"></option>
                </template>
            </select>
            <span class="filter-bar__count" x-text="'Tổng người dùng: ' + pagination.total"></span>
        </div>
    </div>

    <!-- Users table -->
    <div class="card">
        <div class="card__body" style="padding:0">
            <div class="table-wrapper">
                <table class="table table-card">
                    <thead>
                        <tr>
                            <th>Người dùng</th>
                            <th class="hide-mobile">Liên hệ</th>
                            <th>Quyền</th>
                            <th class="hide-mobile">Lớp / Tổ chức</th>
                            <th>Trạng thái</th>
                            <th class="hide-tablet">Đăng nhập gần nhất</th>
                            <th style="width:110px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="u in users" :key="u.uuid">
                            <tr>
                                <td data-label="Người dùng">
                                    <div style="display:flex;align-items:center;gap:10px">
                                        <div style="width:34px;height:34px;border-radius:50%;background:var(--color-primary-light);display:flex;align-items:center;justify-content:center;overflow:hidden;flex-shrink:0">
                                            <img :src="u.avatar_url || ''" x-show="u.avatar_url" style="width:100%;height:100%;object-fit:cover">
                                            <span x-show="!u.avatar_url" style="font-size:13px;font-weight:600;color:var(--color-primary)" x-text="u.full_name.charAt(0).toUpperCase()"></span>
                                        </div>
                                        <div>
                                            <div style="font-weight:500;font-size:13px" x-text="u.full_name"></div>
                                            <div style="font-size:11px;color:var(--color-text-muted)" x-text="u.username ? '@' + u.username : u.email"></div>
                                        </div>
                                    </div>
                                </td>
                                <td data-label="Liên hệ" class="hide-mobile">
                                    <div style="font-size:13px" x-text="u.email"></div>
                                    <div style="font-size:11px;color:var(--color-text-muted)" x-text="u.phone || ''"></div>
                                </td>
                                <td data-label="Quyền">
                                    <span class="badge"
                                          :class="u.role === 'super_admin' ? 'badge--danger' : u.role === 'workspace_admin' ? 'badge--warning' : 'badge--info'"
                                          x-text="roleLabel(u.role)"></span>
                                </td>
                                <td data-label="Lớp / Tổ chức" class="hide-mobile" style="font-size:12px;color:var(--color-text-muted)">
                                    <span x-show="u.role === 'user' && u.grade" x-text="'Lớp ' + u.grade"></span>
                                    <span x-show="u.role === 'workspace_admin' && u.organization" x-text="u.organization"></span>
                                    <span x-show="!u.grade && !u.organization">—</span>
                                </td>
                                <td data-label="Trạng thái">
                                    <span class="badge"
                                          :class="u.status === 'active' ? 'badge--success' : u.status === 'locked' ? 'badge--danger' : 'badge--warning'"
                                          x-text="statusLabel(u.status)"></span>
                                </td>
                                <td data-label="Đăng nhập" class="hide-tablet" style="font-size:12px;color:var(--color-text-muted)" x-text="u.last_login || '—'"></td>
                                <td data-label="">
                                    <div style="display:flex;gap:4px;flex-wrap:nowrap">
                                        <button class="btn btn--ghost btn--sm" @click="openEditModal(u)" title="Chỉnh sửa">✏</button>
                                        <button class="btn btn--ghost btn--sm" @click="openResetPasswordModal(u)" title="Đặt lại mật khẩu">🔑</button>
                                        <button class="btn btn--ghost btn--sm" @click="openModulesModal(u)" title="Phân quyền module">🔐</button>
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
            <div x-show="pagination.total_pages > 1" class="pagination-bar">
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
                    <div class="form-row">
                        <div class="form-group">
                            <label>Username</label>
                            <input class="form-input" x-model="form.username" placeholder="Tùy chọn">
                        </div>
                        <div class="form-group">
                            <label>Số điện thoại</label>
                            <input class="form-input" x-model="form.phone" placeholder="Tùy chọn">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Quyền *</label>
                            <select class="form-input" x-model="form.role">
                                <option value="user">Học sinh</option>
                                <option value="workspace_admin">Giáo viên</option>
                                <option value="super_admin">Super Admin</option>
                            </select>
                        </div>
                        <div class="form-group" x-show="!editingUser">
                            <label>Mật khẩu *</label>
                            <div style="position:relative" x-data="{ show: false }">
                                <input class="form-input" :type="show ? 'text' : 'password'" x-model="form.password" :required="!editingUser" style="padding-right:40px">
                                <button type="button" @click="show = !show"
                                        style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:16px;color:var(--color-text-muted);line-height:1"
                                        :title="show ? 'Ẩn mật khẩu' : 'Hiện mật khẩu'"
                                        x-text="show ? '🙈' : '👁'"></button>
                            </div>
                        </div>
                    </div>

                    <!-- Học sinh: chọn lớp -->
                    <div class="form-group" x-show="form.role === 'user'">
                        <label>Lớp học</label>
                        <select class="form-input" x-model="form.grade">
                            <option value="">-- Chưa xác định --</option>
                            <template x-for="g in [1,2,3,4,5,6,7,8,9]" :key="g">
                                <option :value="g" x-text="'Lớp ' + g"></option>
                            </template>
                        </select>
                    </div>

                    <!-- Giáo viên: tổ chức -->
                    <div class="form-group" x-show="form.role === 'workspace_admin'">
                        <label>Tổ chức / Trường <span style="font-size:11px;color:var(--color-text-muted)">(tùy chọn)</span></label>
                        <input class="form-input" x-model="form.organization" placeholder="VD: Trường THCS Nguyễn Trãi">
                    </div>

                    <button type="submit" class="btn btn--primary btn--full" :disabled="saving">
                        <span x-text="saving ? 'Đang lưu...' : (editingUser ? 'Cập nhật' : 'Tạo người dùng')"></span>
                    </button>
                </form>
            </div>
        </div>
    </div>
    <!-- Module Permissions Modal -->
    <div x-show="showModulesModal" x-cloak
         class="confirm-overlay"
         @click.self="showModulesModal = false">
        <div class="card" style="width:100%;max-width:480px;max-height:90vh;overflow-y:auto">
            <div class="card__header">
                <h3>Phân quyền module</h3>
                <button class="btn btn--ghost btn--sm" @click="showModulesModal = false">✕</button>
            </div>
            <div class="card__body">
                <p style="font-size:13px;color:var(--color-text-muted);margin-bottom:16px">
                    Người dùng: <strong x-text="modulesTarget?.full_name"></strong><br>
                    Tích chọn module để cấp quyền truy cập riêng (ngoài quyền của role).
                </p>

                <div x-show="loadingModules" style="text-align:center;padding:24px;color:var(--color-text-muted);font-size:13px">
                    Đang tải danh sách module...
                </div>

                <div x-show="!loadingModules && modulesList.length === 0"
                     style="text-align:center;padding:24px;color:var(--color-text-muted);font-size:13px">
                    Không có module nào.
                </div>

                <div x-show="!loadingModules && modulesList.length > 0"
                     style="display:flex;flex-direction:column;gap:8px">
                    <template x-for="m in modulesList" :key="m.slug">
                        <label style="display:flex;align-items:center;gap:10px;padding:10px 12px;border:1px solid var(--color-border);border-radius:6px;cursor:pointer;transition:border-color 0.15s"
                               :style="m.granted ? 'border-color:var(--color-primary);background:var(--color-primary-light)' : ''">
                            <input type="checkbox" x-model="m.granted" style="width:16px;height:16px;flex-shrink:0">
                            <div style="flex:1">
                                <div style="font-size:13px;font-weight:500" x-text="m.name"></div>
                                <div style="font-size:11px;color:var(--color-text-muted)" x-text="m.slug"></div>
                            </div>
                            <span x-show="!m.enabled" class="badge badge--secondary" style="font-size:10px">Tắt</span>
                        </label>
                    </template>
                </div>

                <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:20px">
                    <button class="btn btn--secondary btn--sm" @click="showModulesModal = false">Hủy</button>
                    <button class="btn btn--primary btn--sm" :disabled="savingModules" @click="saveModules()">
                        <span x-text="savingModules ? 'Đang lưu...' : 'Lưu phân quyền'"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Reset Password Modal -->
    <div x-show="showResetModal" x-cloak
         class="confirm-overlay"
         @click.self="showResetModal = false">
        <div class="card" style="width:100%;max-width:420px" x-data="{ showPwd: false }">
            <div class="card__header">
                <h3>Đặt lại mật khẩu</h3>
                <button class="btn btn--ghost btn--sm" @click="showResetModal = false">✕</button>
            </div>
            <div class="card__body">
                <p style="font-size:13px;color:var(--color-text-muted);margin-bottom:16px">
                    Đặt lại mật khẩu cho: <strong x-text="resetTarget?.full_name"></strong><br>
                    Sau khi đặt lại, người dùng sẽ bị đăng xuất khỏi tất cả thiết bị.
                </p>
                <form @submit.prevent="doResetPassword()">
                    <div class="form-group">
                        <label>Mật khẩu mới *</label>
                        <div style="position:relative">
                            <input class="form-input" :type="showPwd ? 'text' : 'password'"
                                   x-model="resetForm.new_password"
                                   placeholder="Tối thiểu 4 ký tự"
                                   style="padding-right:40px"
                                   required minlength="4">
                            <button type="button" @click="showPwd = !showPwd"
                                    style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:16px;color:var(--color-text-muted);line-height:1"
                                    :title="showPwd ? 'Ẩn mật khẩu' : 'Hiện mật khẩu'"
                                    x-text="showPwd ? '🙈' : '👁'"></button>
                        </div>
                    </div>
                    <div style="display:flex;gap:8px;justify-content:flex-end">
                        <button type="button" class="btn btn--secondary btn--sm" @click="showResetModal = false">Hủy</button>
                        <button type="submit" class="btn btn--primary btn--sm" :disabled="saving">
                            <span x-text="saving ? 'Đang lưu...' : 'Đặt lại mật khẩu'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?= $this->section('scripts') ?>
<script src="/assets/modules/SystemAdmin/system-admin.js"></script>
<?= $this->endSection() ?>

<?= $this->endSection() ?>
