// ==========================================================================
// SchoolManagement — Quản lý Năm học / Học kỳ (Academic Year)
// Alpine.js component — phân quyền đọc/ghi/sửa/xóa theo module permission.
// ==========================================================================

function academicYearManager() {
    return {
        // ===== State =====
        years: [],
        branches: [],
        filteredYears: [],
        loading: true,
        saving: false,
        initialLoading: true,

        // Filter
        searchQuery: '',
        filterBranchUuid: '',

        // Modal
        showModal: false,
        editingUuid: null,
        form: { name: '', branch_uuid: '', start_date: '', end_date: '' },
        dateError: '',

        // Dropdown
        openDropdown: null,

        // Permission (set bằng my-modules API khi init)
        canRead: false,
        canWrite: false,
        canEdit: false,
        canDelete: false,

        // ============================================================
        // Lifecycle
        // ============================================================

        async load() {
            // Đọc permission từ userModules đã load ở adminApp.init()
            this.loadPermissions();
            if (! this.canRead) {
                this.loading = false;
                this.initialLoading = false;
                return;
            }
            await Promise.all([this.loadYears(), this.loadBranches()]);
            this.initialLoading = false;
            this.applyFilter();
        },

        loadPermissions() {
            const m = (typeof userModules !== 'undefined' && userModules) ? userModules : null;
            if (m === null) {
                // super_admin: full quyền
                this.canRead = this.canWrite = this.canEdit = this.canDelete = true;
                return;
            }
            const p = m['school-management'];
            if (! p) {
                this.canRead = this.canWrite = this.canEdit = this.canDelete = false;
                return;
            }
            this.canRead   = !!(p.can_read   || p.can_write || p.can_edit || p.can_delete);
            this.canWrite  = !!(p.can_write  || p.can_edit  || p.can_delete);
            this.canEdit   = !!(p.can_edit   || p.can_delete);
            this.canDelete = !!p.can_delete;
        },

        async loadYears() {
            this.loading = true;
            try {
                const data = await apiGet('/api/school-management/academic-years');
                if (data?.status === 'success') {
                    this.years = data.data || [];
                }
            } catch (e) {
                showToast('error', 'Không thể tải danh sách năm học.');
            } finally {
                this.loading = false;
            }
        },

        async loadBranches() {
            try {
                const data = await apiGet('/api/school-management/branches');
                if (data?.status === 'success') {
                    this.branches = data.data || [];
                }
            } catch (e) {
                showToast('error', 'Không thể tải danh sách chi nhánh.');
            }
        },

        // ============================================================
        // Filter
        // ============================================================

        applyFilter() {
            const q    = this.searchQuery.toLowerCase().trim();
            const b    = this.filterBranchUuid;
            this.filteredYears = this.years.filter(y => {
                if (q && ! (y.name || '').toLowerCase().includes(q)) return false;
                if (b && y.branch_uuid !== b) return false;
                return true;
            });
        },

        // ============================================================
        // Helpers
        // ============================================================

        formatDate(s) {
            if (! s) return '—';
            // YYYY-MM-DD → DD/MM/YYYY
            const [y, m, d] = s.split('-');
            return `${d}/${m}/${y}`;
        },

        durationText(y) {
            if (! y.start_date || ! y.end_date) return '';
            const start = new Date(y.start_date);
            const end   = new Date(y.end_date);
            const days  = Math.round((end - start) / (1000 * 60 * 60 * 24));
            if (days < 30) return `${days} ngày`;
            const months = Math.round(days / 30);
            if (months < 12) return `${months} tháng`;
            return `${(days / 365).toFixed(1)} năm`;
        },

        // ============================================================
        // Dropdown
        // ============================================================

        toggleDropdown(uuid) {
            this.openDropdown = this.openDropdown === uuid ? null : uuid;
        },

        closeAllDropdowns() {
            this.openDropdown = null;
        },

        closeAllModals() {
            this.showModal = false;
        },

        // ============================================================
        // Tạo / Sửa
        // ============================================================

        openCreate() {
            if (! this.canWrite) {
                showToast('error', 'Bạn không có quyền tạo năm học.');
                return;
            }
            this.editingUuid = null;
            const today = new Date().toISOString().split('T')[0];
            this.form = { name: '', branch_uuid: '', start_date: today, end_date: '' };
            this.dateError = '';
            this.showModal = true;
        },

        openEdit(y) {
            if (! this.canEdit) {
                showToast('error', 'Bạn không có quyền sửa năm học.');
                return;
            }
            this.editingUuid = y.uuid;
            this.form = {
                name: y.name,
                branch_uuid: y.branch_uuid,
                start_date: y.start_date,
                end_date: y.end_date,
            };
            this.dateError = '';
            this.showModal = true;
        },

        validateDates() {
            this.dateError = '';
            if (! this.form.start_date || ! this.form.end_date) {
                this.dateError = 'Vui lòng chọn đầy đủ ngày bắt đầu và kết thúc.';
                return false;
            }
            if (this.form.end_date <= this.form.start_date) {
                this.dateError = 'Ngày kết thúc phải sau ngày bắt đầu.';
                return false;
            }
            return true;
        },

        async save() {
            if (! this.form.name.trim()) {
                showToast('error', 'Vui lòng nhập tên năm học.');
                return;
            }
            if (! this.form.branch_uuid) {
                showToast('error', 'Vui lòng chọn chi nhánh.');
                return;
            }
            if (! this.validateDates()) {
                return;
            }

            this.saving = true;
            try {
                let data;
                if (this.editingUuid) {
                    data = await apiRequest('/api/school-management/academic-years/' + this.editingUuid, {
                        method:  'PUT',
                        body:    JSON.stringify(this.form),
                        headers: { 'Content-Type': 'application/json' },
                    });
                } else {
                    data = await apiRequest('/api/school-management/academic-years', {
                        method:  'POST',
                        body:    new URLSearchParams(this.form),
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    });
                }

                if (data?.status === 'success') {
                    showToast('success', this.editingUuid ? 'Cập nhật năm học thành công!' : 'Tạo năm học thành công!');
                    this.showModal = false;
                    await this.loadYears();
                    this.applyFilter();
                } else {
                    showToast('error', data?.message || 'Có lỗi xảy ra.');
                }
            } catch (e) {
                showToast('error', 'Không thể kết nối API.');
            } finally {
                this.saving = false;
            }
        },

        // ============================================================
        // Xóa
        // ============================================================

        async confirmDelete(y) {
            if (! this.canDelete) {
                showToast('error', 'Bạn không có quyền xóa năm học.');
                return;
            }

            const ok = await showConfirm({
                title: 'Xóa năm học',
                message: `Xóa năm học "${y.name}" (${this.formatDate(y.start_date)} → ${this.formatDate(y.end_date)})?\n\nHành động này có thể ảnh hưởng đến lịch học, thời khóa biểu và kết quả học tập.`,
                type: 'danger',
                confirmText: 'Xóa',
            });
            if (! ok) return;

            try {
                const data = await apiRequest('/api/school-management/academic-years/' + y.uuid, { method: 'DELETE' });
                if (data?.status === 'success') {
                    showToast('success', 'Đã xóa năm học.');
                    this.years = this.years.filter(x => x.uuid !== y.uuid);
                    this.applyFilter();
                } else {
                    showToast('error', data?.message || 'Có lỗi xảy ra.');
                }
            } catch (e) {
                showToast('error', 'Không thể kết nối API.');
            }
        },
    };
}
