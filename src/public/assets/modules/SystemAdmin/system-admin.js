// ==========================================================================
// System Admin — Dashboard
// ==========================================================================

function dashboardStats() {
    return {
        stats: { total_users: '—', active_modules: '—', unseen_logs: '—' },
        serverStatus: null,
        loadingStatus: false,

        async load() {
            await Promise.all([this.loadStats(), this.loadStatus()]);
        },

        async loadStats() {
            const token = localStorage.getItem('access_token');
            try {
                const [modData, usrData, logData] = await Promise.all([
                    fetch('/api/admin/modules',           { headers: { 'Authorization': 'Bearer ' + token } }).then(r => r.json()),
                    fetch('/api/admin/users?per_page=1',  { headers: { 'Authorization': 'Bearer ' + token } }).then(r => r.json()),
                    fetch('/api/admin/system-logs/stats', { headers: { 'Authorization': 'Bearer ' + token } }).then(r => r.json()),
                ]);
                if (modData.status === 'success')
                    this.stats.active_modules = modData.data.filter(m => m.is_enabled).length + '/' + modData.data.length;
                if (usrData.status === 'success')
                    this.stats.total_users = usrData.data.pagination.total;
                if (logData.status === 'success')
                    this.stats.unseen_logs = logData.data.unseen;
            } catch (e) {}
        },

        async loadStatus() {
            this.loadingStatus = true;
            try {
                const data = await apiGet('/api/admin/server-status');
                this.serverStatus = data?.status === 'success' ? data.data : null;
            } catch (e) {
                this.serverStatus = null;
            }
            this.loadingStatus = false;
        },
    };
}

// ==========================================================================
// System Admin — User Management
// ==========================================================================

function userManager() {
    return {
        users: [],
        pagination: { page: 1, per_page: 20, total: 0, total_pages: 0 },
        filters: { search: '', role: '', status: '', grade: '' },
        loading: false,
        showModal: false,
        editingUser: null,
        saving: false,
        form: { full_name: '', email: '', username: '', phone: '', role: 'user', password: '', grade: '', organization: '' },
        showResetModal: false,
        resetTarget: null,
        resetForm: { new_password: '' },
        showModulesModal: false,
        modulesTarget: null,
        modulesList: [],
        loadingModules: false,
        savingModules: false,

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
            if (this.filters.role)   params.set('role', this.filters.role);
            if (this.filters.status) params.set('status', this.filters.status);
            if (this.filters.grade)  params.set('grade', this.filters.grade);

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
            this.form = { full_name: '', email: '', username: '', phone: '', role: 'user', password: '', grade: '', organization: '' };
            this.showModal = true;
        },

        openEditModal(user) {
            this.editingUser = user;
            this.form = {
                full_name:    user.full_name,
                email:        user.email,
                username:     user.username || '',
                phone:        user.phone || '',
                role:         user.role,
                password:     '',
                grade:        user.grade || '',
                organization: user.organization || '',
            };
            this.showModal = true;
        },

        async saveUser() {
            this.saving = true;

            if (this.editingUser) {
                const updateFields = {
                    full_name: this.form.full_name,
                    username:  this.form.username,
                    phone:     this.form.phone,
                    grade:        this.form.role === 'user' ? (this.form.grade || '') : '',
                    organization: this.form.role === 'workspace_admin' ? (this.form.organization || '') : '',
                };
                const body = new URLSearchParams(updateFields);
                const data = await apiPut('/api/admin/users/' + this.editingUser.uuid, body);

                if (data && data.status === 'success') {
                    if (this.form.role !== this.editingUser.role) {
                        await apiPut('/api/admin/users/' + this.editingUser.uuid + '/role',
                            new URLSearchParams({ role: this.form.role }));
                    }
                    showToast('success', 'Đã cập nhật người dùng.');
                    this.showModal = false;
                    this.loadUsers();
                } else {
                    showToast('error', data ? data.message : 'Lỗi cập nhật');
                }
            } else {
                const createFields = { ...this.form };
                if (this.form.role !== 'user') delete createFields.grade;
                if (this.form.role !== 'workspace_admin') delete createFields.organization;
                const body = new URLSearchParams(createFields);
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

        async openModulesModal(user) {
            this.modulesTarget  = user;
            this.modulesList    = [];
            this.showModulesModal = true;
            this.loadingModules   = true;
            const data = await apiGet('/api/admin/users/' + user.uuid + '/modules');
            if (data?.status === 'success') this.modulesList = data.data;
            this.loadingModules = false;
        },

        // Khi bỏ tích "Đọc" → xóa hết các quyền con
        onReadChange(m) {
            if (!m.can_read) {
                m.can_write  = false;
                m.can_edit   = false;
                m.can_delete = false;
            }
        },

        async saveModules() {
            this.savingModules = true;
            const token   = getToken();
            const modules = this.modulesList
                .filter(m => m.can_read)
                .map(m => ({
                    slug:       m.slug,
                    can_read:   m.can_read   ? 1 : 0,
                    can_write:  m.can_write  ? 1 : 0,
                    can_edit:   m.can_edit   ? 1 : 0,
                    can_delete: m.can_delete ? 1 : 0,
                }));
            try {
                const res  = await fetch('/api/admin/users/' + this.modulesTarget.uuid + '/modules', {
                    method:  'PUT',
                    headers: { 'Authorization': 'Bearer ' + token, 'Content-Type': 'application/json' },
                    body:    JSON.stringify({ modules }),
                });
                const data = await res.json();
                if (data?.status === 'success') {
                    showToast('success', 'Đã cập nhật phân quyền module.');
                    this.showModulesModal = false;
                } else {
                    showToast('error', data?.message || 'Có lỗi xảy ra.');
                }
            } catch (e) {
                showToast('error', 'Lỗi kết nối.');
            }
            this.savingModules = false;
        },

        openResetPasswordModal(user) {
            this.resetTarget = user;
            this.resetForm = { new_password: '' };
            this.showResetModal = true;
        },

        async doResetPassword() {
            this.saving = true;
            const data = await apiPut(
                '/api/admin/users/' + this.resetTarget.uuid + '/reset-password',
                new URLSearchParams(this.resetForm)
            );
            if (data && data.status === 'success') {
                showToast('success', 'Đã đặt lại mật khẩu.');
                this.showResetModal = false;
            } else {
                showToast('error', data ? data.message : 'Thao tác thất bại');
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

            const data = await apiPut('/api/admin/users/' + user.uuid + '/status',
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
                const res = await fetch('/api/admin/users/' + this.editingUser.uuid + '/avatar', {
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

            const data = await apiDelete('/api/admin/users/' + this.editingUser.uuid + '/avatar');
            if (data && data.status === 'success') {
                this.editingUser.avatar_url = null;
                this.editingUser.avatar = null;
                showToast('success', 'Đã xóa avatar.');
                this.loadUsers();
            }
        }
    };
}

// ==========================================================================
// System Admin — Module Management
// ==========================================================================

function moduleManager() {
    return {
        modules: [],
        uninstalled: [],
        loading: false,
        scanning: false,
        syncing: false,
        scanned: false,

        async init() {
            this.loading = true;
            const data = await apiGet('/api/admin/modules');
            if (data && data.status === 'success') {
                this.modules = data.data;
            }
            this.loading = false;
        },

        async toggleModule(module, enable) {
            const label = enable ? 'bật' : 'tắt';
            const confirmed = await showConfirm({
                title: (enable ? 'Bật' : 'Tắt') + ' module',
                message: `Bạn chắc chắn muốn ${label} module "${module.name}"?`,
                type: enable ? 'info' : 'warning',
                confirmText: enable ? 'Bật' : 'Tắt',
            });
            if (!confirmed) {
                module.is_enabled = !enable;
                return;
            }

            const data = await apiPut(`/api/admin/modules/${module.id}/toggle`);
            if (data && data.status === 'success') {
                module.is_enabled = enable;
                showToast('success', `Đã ${label} module "${module.name}".`);
            } else {
                module.is_enabled = !enable;
                showToast('error', data ? data.message : 'Có lỗi xảy ra.');
            }
        },

        async scanModules() {
            this.scanning = true;
            const data = await apiPost('/api/admin/modules/scan');
            this.scanning = false;
            this.scanned = true;
            if (data && data.status === 'success') {
                this.uninstalled = data.data;
                if (data.data.length > 0) {
                    showToast('info', `Tìm thấy ${data.data.length} module chưa cài.`);
                }
            } else {
                showToast('error', data ? data.message : 'Quét thất bại.');
            }
        },

        async installModule(mod) {
            const data = await apiPost(`/api/admin/modules/${mod.dir}/install`);
            if (data && data.status === 'success') {
                showToast('success', data.message);
                this.uninstalled = this.uninstalled.filter(m => m.slug !== mod.slug);
                await this.init();
            } else {
                showToast('error', data ? data.message : 'Cài đặt thất bại.');
            }
        },

        async syncCache() {
            this.syncing = true;
            const data = await apiPost('/api/admin/modules/sync-cache');
            this.syncing = false;
            if (data && data.status === 'success') {
                showToast('success', 'Đã đồng bộ Redis cache.');
            }
        },
    };
}

// ==========================================================================
// System Admin — Site Config
// ==========================================================================

function configManager() {
    return {
        configs: [],
        groups: [],
        activeGroup: 'general',
        loading: false,
        groupLabels: {
            general: '⚙ Cài đặt chung',
            contact: '📞 Liên hệ'
        },

        get filteredConfigs() {
            return this.configs.filter(c => c.group === this.activeGroup);
        },

        async loadConfigs() {
            this.loading = true;
            const data = await apiGet('/api/admin/configs');
            if (data && data.status === 'success') {
                const allowedGroups = ['general', 'contact'];
                this.groups = Object.keys(data.data).filter(g => allowedGroups.includes(g));
                this.configs = [];
                for (const [group, items] of Object.entries(data.data)) {
                    if (!allowedGroups.includes(group)) continue;
                    items.forEach(item => {
                        item._editValue = item.value || '';
                        item._saving = false;
                        item._saved = false;
                        this.configs.push(item);
                    });
                }
            }
            this.loading = false;
        },

        async saveConfig(config) {
            if (config._editValue === config.value) return;

            config._saving = true;
            config._saved = false;

            const token = getToken();
            try {
                const res = await fetch('/api/admin/configs/' + config.key, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'Authorization': 'Bearer ' + token
                    },
                    body: 'value=' + encodeURIComponent(config._editValue)
                });

                if (res.status === 401) {
                    localStorage.removeItem('access_token');
                    window.location.href = '/admin/login';
                    return;
                }

                const data = await res.json();

                if (data.status === 'success') {
                    config.value = config._editValue;
                    config._saved = true;
                    showToast('success', 'Đã lưu: ' + (config.description || config.key));
                    setTimeout(() => { config._saved = false; }, 3000);
                } else {
                    showToast('error', data.message || 'Lưu thất bại');
                }
            } catch (e) {
                showToast('error', 'Lỗi kết nối');
            }

            config._saving = false;
        }
    };
}
