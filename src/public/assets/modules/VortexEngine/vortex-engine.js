// ==========================================================================
// VortexEngine — Subscription Manager
// ==========================================================================

function subscriptionManager() {
    return {
        tab: 'activate',

        activePackages: [],
        allPackages: [],
        loadingPkg: false,
        loadingAllPkg: false,

        activating: false,
        result: null,
        form: { student_id: '', package_key: '' },

        studentSearch: '',
        studentOpen: false,
        studentFocus: 0,
        loadingStudents: false,
        filteredStudents: [],
        selectedStudentLabel: '',
        _searchTimer: null,

        showCreateForm: false,
        creating: false,
        newPkg: { package_key: '', name: '', days_to_add: '', price: '', description: '', sub_type: 'VIP', max_students: 1, allowed_grades: [] },

        editingId: null,
        editBuf: {},
        saving: false,

        subscriptions: [],
        loadingSubs: false,
        subFilters: { search: '', status: '', grade: '' },
        subPagination: { page: 1, per_page: 20, total: 0, total_pages: 0 },
        editingSub: null,
        editSubBuf: {},
        savingSub: false,

        async init() {
            const user = (() => { try { return JSON.parse(localStorage.getItem('user') || 'null'); } catch { return null; } })();
            if (!user || (user.role !== 'super_admin' && user.role !== 'workspace_admin')) {
                window.location.href = '/admin';
                return;
            }
            if (user.role === 'workspace_admin') {
                const mods = await apiGet('/api/auth/my-modules');
                if (!mods?.data?.slugs?.includes('vortex-engine')) {
                    window.location.href = '/admin/classrooms';
                    return;
                }
            }
            this.loadingPkg = true;
            const data = await apiGet('/api/admin/subscriptions/packages');
            if (data?.status === 'success') this.activePackages = data.data;
            this.loadingPkg = false;
            this.fetchStudents('', true);
        },

        async loadAllPackages() {
            if (this.allPackages.length) return;
            this.loadingAllPkg = true;
            const data = await apiGet('/api/admin/subscriptions/packages/all');
            if (data?.status === 'success') this.allPackages = data.data;
            this.loadingAllPkg = false;
        },

        _studentPage: 1,
        _studentHasMore: false,
        _studentQuery: '',

        async fetchStudents(q, reset = false) {
            if (reset) {
                this._studentPage = 1;
                this.filteredStudents = [];
            }
            this._studentQuery = q;
            this.loadingStudents = true;
            const params = new URLSearchParams({
                per_page: 10, page: this._studentPage,
                exclude_subscribed: 1,
            });
            if (q) params.set('search', q);
            const data = await apiGet('/api/admin/subscriptions/students?' + params);
            if (data?.status === 'success') {
                const users = data.data.users || [];
                this.filteredStudents = reset ? users : [...this.filteredStudents, ...users];
                const pg = data.data.pagination;
                this._studentHasMore = pg.page < pg.total_pages;
            }
            this.studentFocus = 0;
            this.loadingStudents = false;
        },

        async loadMoreStudents() {
            this._studentPage++;
            await this.fetchStudents(this._studentQuery, false);
        },

        onStudentSearch() {
            this.studentOpen = true;
            this.form.student_id = '';
            this.selectedStudentLabel = '';
            clearTimeout(this._searchTimer);
            this._searchTimer = setTimeout(() => this.fetchStudents(this.studentSearch, true), 300);
        },

        selectStudent(s) {
            this.form.student_id      = s.uuid;
            this.selectedStudentLabel = s.full_name + ' — ' + s.email;
            this.studentSearch        = '';
            this.studentOpen          = false;
        },

        selectStudentByIndex(idx) {
            if (this.filteredStudents[idx]) this.selectStudent(this.filteredStudents[idx]);
        },

        clearStudent() {
            this.form.student_id      = '';
            this.selectedStudentLabel = '';
            this.studentSearch        = '';
            this.studentOpen          = true;
            this.$nextTick(() => this.$refs.studentPicker.querySelector('input').focus());
        },

        async activate() {
            if (!this.form.student_id || !this.form.package_key) return;
            this.activating = true;
            this.result = null;
            const body = new URLSearchParams({ student_id: this.form.student_id, package_key: this.form.package_key });
            const data = await apiPost('/api/admin/subscriptions/activate', body);
            if (data?.status === 'success') {
                this.result = { ...data.data, student_name: this.selectedStudentLabel };
                showToast('success', data.message);
                this.form = { student_id: '', package_key: '' };
                this.selectedStudentLabel = '';
                this.studentSearch = '';
                this.fetchStudents('', true);
            } else {
                showToast('error', data?.message || 'Kích hoạt thất bại.');
            }
            this.activating = false;
        },

        autoKey() {
            this.newPkg.package_key = this.newPkg.name
                .toUpperCase()
                .normalize('NFD').replace(/[̀-ͯ]/g, '')
                .replace(/Đ/g, 'D')
                .replace(/[^A-Z0-9\s]/g, '')
                .trim().replace(/\s+/g, '_');
        },

        resetNewPkg() {
            this.newPkg = { package_key: '', name: '', days_to_add: '', price: '', description: '', sub_type: 'VIP', max_students: 1, allowed_grades: [] };
        },

        async createPackage() {
            this.creating = true;
            const body = JSON.stringify({
                package_key:    this.newPkg.package_key,
                name:           this.newPkg.name,
                days_to_add:    Number(this.newPkg.days_to_add),
                price:          Number(this.newPkg.price),
                description:    this.newPkg.description,
                sub_type:       this.newPkg.sub_type || 'VIP',
                max_students:   Number(this.newPkg.max_students) || 1,
                allowed_grades: this.newPkg.allowed_grades.length ? this.newPkg.allowed_grades.map(Number) : null,
            });
            const data = await apiRequest('/api/admin/subscriptions/packages', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body,
            });
            if (data?.status === 'success') {
                showToast('success', data.message);
                this.allPackages.push(data.data);
                if (Number(data.data.is_active)) this.activePackages.push(data.data);
                this.resetNewPkg();
                this.showCreateForm = false;
            } else {
                showToast('error', data?.message || 'Tạo gói thất bại.');
            }
            this.creating = false;
        },

        async togglePkg(pkg, enable) {
            const data = await apiPut(`/api/admin/subscriptions/packages/${pkg.package_key}/toggle`);
            if (data?.status === 'success') {
                pkg.is_active = enable ? 1 : 0;
                this.activePackages = this.allPackages.filter(p => Number(p.is_active));
                showToast('success', data.message);
            } else {
                pkg.is_active = enable ? 0 : 1;
                showToast('error', data?.message || 'Có lỗi xảy ra.');
            }
        },

        startEdit(pkg) {
            this.editingId = pkg.id;
            let grades = [];
            try { grades = pkg.allowed_grades ? JSON.parse(pkg.allowed_grades).map(Number) : []; } catch(e) {}
            this.editBuf = {
                name:           pkg.name,
                days_to_add:    pkg.days_to_add,
                price:          pkg.price,
                description:    pkg.description || '',
                sub_type:       pkg.sub_type || 'VIP',
                max_students:   pkg.max_students || 1,
                allowed_grades: grades,
            };
        },

        cancelEdit() {
            this.editingId = null;
            this.editBuf = {};
        },

        async loadSubList(reset = false) {
            if (reset) this.subPagination.page = 1;
            this.loadingSubs = true;
            const params = new URLSearchParams({
                page:     this.subPagination.page,
                per_page: this.subPagination.per_page,
            });
            if (this.subFilters.search) params.set('search', this.subFilters.search);
            if (this.subFilters.status) params.set('status', this.subFilters.status);
            if (this.subFilters.grade)  params.set('grade', this.subFilters.grade);
            const data = await apiGet('/api/admin/subscriptions/list?' + params);
            if (data?.status === 'success') {
                this.subscriptions   = data.data.subscriptions;
                this.subPagination   = data.data.pagination;
            }
            this.loadingSubs = false;
        },

        subGoPage(page) {
            if (page < 1 || page > this.subPagination.total_pages) return;
            this.subPagination.page = page;
            this.loadSubList();
        },

        isExpired(dateStr) {
            if (!dateStr) return false;
            return new Date(dateStr) < new Date();
        },

        openEditSub(s) {
            this.editingSub = s;
            this.editSubBuf = {
                package_key:  s.package_key,
                status:       s.status,
                expired_date: s.expired_date ? s.expired_date.substring(0, 10) : '',
            };
            this.loadAllPackages();
        },

        async saveSubEdit() {
            if (!this.editingSub) return;
            this.savingSub = true;
            const body = JSON.stringify({
                package_key:  this.editSubBuf.package_key || undefined,
                status:       this.editSubBuf.status       || undefined,
                expired_date: this.editSubBuf.expired_date || null,
            });
            const data = await apiRequest(`/api/admin/subscriptions/${this.editingSub.id}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body,
            });
            if (data?.status === 'success') {
                showToast('success', 'Đã cập nhật.');
                const idx = this.subscriptions.findIndex(s => s.id === this.editingSub.id);
                if (idx !== -1) {
                    this.subscriptions[idx] = {
                        ...this.subscriptions[idx],
                        package_key:  this.editSubBuf.package_key,
                        status:       this.editSubBuf.status,
                        expired_date: this.editSubBuf.expired_date ? this.editSubBuf.expired_date + ' 00:00:00' : null,
                    };
                    const pkg = this.allPackages.find(p => p.package_key === this.editSubBuf.package_key);
                    if (pkg) {
                        this.subscriptions[idx].package_name = pkg.name;
                        this.subscriptions[idx].days_to_add  = pkg.days_to_add;
                        this.subscriptions[idx].price        = pkg.price;
                    }
                }
                this.editingSub = null;
            } else {
                showToast('error', data?.message || 'Cập nhật thất bại.');
            }
            this.savingSub = false;
        },

        async saveEdit(pkg) {
            this.saving = true;
            const body = JSON.stringify({
                name:           this.editBuf.name,
                days_to_add:    Number(this.editBuf.days_to_add),
                price:          Number(this.editBuf.price),
                description:    this.editBuf.description,
                sub_type:       this.editBuf.sub_type || 'VIP',
                max_students:   Number(this.editBuf.max_students) || 1,
                allowed_grades: this.editBuf.allowed_grades?.length ? this.editBuf.allowed_grades.map(Number) : null,
            });
            const data = await apiRequest(`/api/admin/subscriptions/packages/${pkg.package_key}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body,
            });
            if (data?.status === 'success') {
                Object.assign(pkg, data.data);
                this.activePackages = this.allPackages.filter(p => Number(p.is_active));
                this.editingId = null;
                this.editBuf = {};
                showToast('success', data.message);
            } else {
                showToast('error', data?.message || 'Lưu thất bại.');
            }
            this.saving = false;
        },
    };
}
