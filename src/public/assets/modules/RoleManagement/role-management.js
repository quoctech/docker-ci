// ==========================================================================
// RoleManagement — Role Manager
// ==========================================================================

function roleManager() {
    return {
        roles: [],
        loading: false,

        showModal: false,
        submitting: false,
        editingId: null,
        form: { name: '', description: '' },

        showModulesModal: false,
        currentRole: null,
        modulesList: [],
        loadingModules: false,
        savingModules: false,

        showApplyModal: false,
        applyRole: null,
        userSearch: '',
        userResults: [],
        searchingUsers: false,

        showUsersModal: false,
        usersRole: null,
        usersList: [],
        loadingUsers: false,
        removingUserUuid: null,

        async load() {
            this.loading = true;
            const data = await apiGet('/api/role-management/roles');
            if (data?.status === 'success') this.roles = data.data;
            this.loading = false;
        },

        openCreate() {
            this.editingId = null;
            this.form = { name: '', description: '' };
            this.showModal = true;
        },

        openEdit(r) {
            this.editingId = r.uuid;
            this.form = { name: r.name, description: r.description || '' };
            this.showModal = true;
        },

        async submitForm() {
            if (!this.form.name.trim()) { showToast('error', 'Vui lòng nhập tên vai trò.'); return; }
            this.submitting = true;

            const body   = new URLSearchParams(this.form);
            const url    = this.editingId ? '/api/role-management/roles/' + this.editingId : '/api/role-management/roles';
            const method = this.editingId ? 'PUT' : 'POST';

            const data = await apiRequest(url, { method, body, headers: { 'Content-Type': 'application/x-www-form-urlencoded' } });
            this.submitting = false;

            if (data?.status === 'success') {
                showToast('success', this.editingId ? 'Cập nhật thành công!' : 'Tạo vai trò thành công!');
                this.showModal = false;
                await this.load();
            } else {
                showToast('error', data?.message || 'Có lỗi xảy ra.');
            }
        },

        async confirmDelete(r) {
            // Bước 0: Bảo vệ — không cho xóa role hệ thống
            if (r.is_protected) {
                showToast('error', `Không thể xóa vai trò hệ thống "${r.name}".`);
                return;
            }

            // Bước 1: Lấy số user đang được gán role này để cảnh báo
            //         (bỏ qua nếu r.uuid không hợp lệ — tránh gọi API với UUID rỗng gây 404 khó hiểu)
            let assignedCount = 0;
            let assignedUsers = [];
            let apiError = null;
            if (r.uuid) {
                try {
                    const info = await apiGet('/api/role-management/roles/' + r.uuid + '/users');
                    if (info?.status === 'success') {
                        assignedCount = info.data.count;
                        assignedUsers = info.data.users || [];
                    } else {
                        apiError = info?.message || 'Không thể lấy thông tin người dùng.';
                    }
                } catch (e) {
                    apiError = 'Không thể kết nối API. Vui lòng thử lại.';
                }
            } else {
                apiError = 'UUID vai trò không hợp lệ. Vui lòng tải lại trang.';
            }

            // Nếu API lỗi — chặn xóa để tránh xóa nhầm
            if (apiError) {
                showToast('error', apiError);
                await this.load(); // refresh danh sách để lấy UUID mới
                return;
            }

            // Bước 2: Build message cảnh báo
            let message = `Xóa vai trò "${r.name}"? Hành động này không thể hoàn tác.`;
            if (assignedCount > 0) {
                message += `\n\n⚠️ Hiện có ${assignedCount} người dùng đang được gán vai trò này. Tất cả sẽ bị gỡ bỏ vai trò kèm theo.`;
                const preview = assignedUsers.slice(0, 3).map(u => u.full_name || u.email).join(', ');
                if (preview) {
                    message += `\n   (${preview}${assignedCount > 3 ? `, ...` : ''})`;
                }
            }

            const ok = await showConfirm({
                title: assignedCount > 0 ? '⚠️ Xóa vai trò (sẽ gỡ khỏi người dùng)' : 'Xóa vai trò',
                message: message,
                type: 'danger',
                confirmText: assignedCount > 0 ? `Xóa & gỡ ${assignedCount} người dùng` : 'Xóa',
            });
            if (!ok) return;

            // Bước 3: Gọi DELETE — server sẽ tự động gỡ role khỏi user trước khi xóa role
            const data = await apiRequest('/api/role-management/roles/' + r.uuid, { method: 'DELETE' });
            if (data?.status === 'success') {
                showToast('success', data.message || 'Đã xóa vai trò.');
                this.roles = this.roles.filter(x => x.uuid !== r.uuid);
            } else {
                showToast('error', data?.message || 'Có lỗi xảy ra.');
                // Refresh danh sách nếu lỗi 403/404 — có thể role đã bị xóa/thay đổi từ tab khác
                if (data?.code === 403 || data?.code === 404) {
                    await this.load();
                }
            }
        },

        async openModulesModal(r) {
            this.currentRole  = r;
            this.modulesList  = [];
            this.showModulesModal = true;
            this.loadingModules  = true;
            const data = await apiGet('/api/role-management/roles/' + r.uuid + '/modules');
            if (data?.status === 'success') this.modulesList = data.data;
            this.loadingModules = false;
        },

        onReadChange(m) {
            if (!m.can_read) {
                m.can_write  = false;
                m.can_edit   = false;
                m.can_delete = false;
            }
        },

        async saveModules() {
            this.savingModules = true;
            const modules = this.modulesList
                .filter(m => m.can_read)
                .map(m => ({
                    slug:       m.slug,
                    can_read:   m.can_read   ? 1 : 0,
                    can_write:  m.can_write  ? 1 : 0,
                    can_edit:   m.can_edit   ? 1 : 0,
                    can_delete: m.can_delete ? 1 : 0,
                }));

            const res  = await fetch('/api/role-management/roles/' + this.currentRole.uuid + '/modules', {
                method:  'PUT',
                headers: { 'Authorization': 'Bearer ' + getToken(), 'Content-Type': 'application/json' },
                body:    JSON.stringify({ modules }),
            });
            const data = await res.json();
            this.savingModules = false;

            if (data?.status === 'success') {
                showToast('success', 'Đã cập nhật phân quyền vai trò.');
                this.showModulesModal = false;
                await this.load();
            } else {
                showToast('error', data?.message || 'Có lỗi xảy ra.');
            }
        },

        openApplyModal(r) {
            this.applyRole   = r;
            this.userSearch  = '';
            this.userResults = [];
            this.showApplyModal = true;
        },

        async searchUsers() {
            if (this.userSearch.length < 2) { this.userResults = []; return; }
            this.searchingUsers = true;
            const data = await apiGet('/api/admin/users?search=' + encodeURIComponent(this.userSearch) + '&role=workspace_admin&per_page=10');
            if (data?.status === 'success') this.userResults = data.data.users || [];
            this.searchingUsers = false;
        },

        async applyRoleToUser(user) {
            const ok = await showConfirm({
                title:       'Áp dụng vai trò',
                message:     `Áp dụng vai trò "${this.applyRole.name}" cho "${user.full_name}"?\n\nPhân quyền module hiện tại của người dùng sẽ bị ghi đè.`,
                type:        'warning',
                confirmText: 'Áp dụng',
            });
            if (!ok) return;

            const res  = await fetch('/api/role-management/roles/' + this.applyRole.uuid + '/apply-to-user', {
                method:  'POST',
                headers: { 'Authorization': 'Bearer ' + getToken(), 'Content-Type': 'application/json' },
                body:    JSON.stringify({ user_uuid: user.uuid }),
            });
            const data = await res.json();

            if (data?.status === 'success') {
                showToast('success', data.message);
                this.showApplyModal = false;
            } else {
                showToast('error', data?.message || 'Có lỗi xảy ra.');
            }
        },

        // ============================================================
        // Danh sách người dùng có vai trò
        // ============================================================

        async openUsersModal(r) {
            this.usersRole      = r;
            this.usersList      = [];
            this.showUsersModal = true;
            this.loadingUsers   = true;

            try {
                const data = await apiGet('/api/role-management/roles/' + r.uuid + '/users');
                if (data?.status === 'success') {
                    this.usersList = data.data.users || [];
                } else {
                    showToast('error', data?.message || 'Không thể tải danh sách người dùng.');
                }
            } catch (e) {
                showToast('error', 'Không thể kết nối API.');
            } finally {
                this.loadingUsers = false;
            }
        },

        async confirmUnapplyRole(user) {
            // Bảo vệ: không cho gỡ role 'user' khỏi super admin (vì super admin cần role này)
            if (user.is_super_admin && this.usersRole?.slug === 'user') {
                showToast('error', 'Không thể gỡ vai trò "user" khỏi tài khoản Super Admin.');
                return;
            }

            const ok = await showConfirm({
                title:       'Gỡ bỏ vai trò',
                message:     `Gỡ bỏ vai trò "${this.usersRole?.name}" khỏi người dùng "${user.full_name || user.email}"?\n\nNgười dùng sẽ mất các quyền tương ứng ở request kế tiếp.`,
                type:        'warning',
                confirmText: 'Gỡ bỏ',
            });
            if (!ok) return;

            this.removingUserUuid = user.uuid;
            try {
                const data = await apiRequest('/api/role-management/user-applied-roles', {
                    method:  'DELETE',
                    body:    new URLSearchParams({ user_uuid: user.uuid, role_uuid: this.usersRole.uuid }),
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                });

                if (data?.status === 'success') {
                    showToast('success', data.message || 'Đã gỡ bỏ vai trò.');
                    // Xóa user khỏi list local
                    this.usersList = this.usersList.filter(x => x.uuid !== user.uuid);
                } else {
                    showToast('error', data?.message || 'Có lỗi xảy ra.');
                }
            } catch (e) {
                showToast('error', 'Không thể kết nối API.');
            } finally {
                this.removingUserUuid = null;
            }
        },
    };
}
