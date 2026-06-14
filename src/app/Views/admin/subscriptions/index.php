<?= $this->extend('layouts/admin') ?>
<?= $this->section('title') ?>Quản lý gói học<?= $this->endSection() ?>
<?= $this->section('breadcrumb') ?>Tính năng / Quản lý gói học<?= $this->endSection() ?>
<?= $this->section('content') ?>

<div x-data="subscriptionManager()" x-init="init()">

    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px">
        <div>
            <h1 class="content__title">💎 Quản lý gói học</h1>
            <p class="content__subtitle">Kích hoạt, gia hạn và quản lý gói đăng ký của học viên.</p>
        </div>
        <div style="display:flex;gap:8px">
            <button class="btn btn--sm" :class="tab === 'activate' ? 'btn--primary' : 'btn--secondary'" @click="tab = 'activate'">⚡ Kích hoạt</button>
            <button class="btn btn--sm" :class="tab === 'packages' ? 'btn--primary' : 'btn--secondary'" @click="tab = 'packages'; loadAllPackages()">📦 Quản lý gói</button>
        </div>
    </div>

    <!-- ===== TAB: KÍCH HOẠT ===== -->
    <div x-show="tab === 'activate'">

        <!-- Package cards (chọn nhanh) -->
        <div style="margin-bottom:28px">
            <p style="font-size:13px;color:var(--color-text-muted);margin-bottom:12px">Chọn gói để điền tự động:</p>
            <div x-show="loadingPkg" style="color:var(--color-text-muted);font-size:13px">Đang tải...</div>
            <div class="grid grid--4" x-show="!loadingPkg">
                <template x-for="pkg in activePackages" :key="pkg.package_key">
                    <div class="card" style="cursor:pointer;transition:.15s"
                         :style="form.package_key === pkg.package_key ? 'border:2px solid var(--color-primary)' : 'border:2px solid transparent'"
                         @click="form.package_key = pkg.package_key">
                        <div class="card__body" style="text-align:center;padding:16px">
                            <h4 x-text="pkg.name" style="margin:0 0 6px;font-size:13px"></h4>
                            <div style="font-size:18px;font-weight:700;color:var(--color-primary)"
                                 x-text="Number(pkg.price).toLocaleString('vi-VN') + '₫'"></div>
                            <div style="font-size:11px;color:var(--color-text-muted);margin-top:2px"
                                 x-text="pkg.days_to_add + ' ngày'"></div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- Form kích hoạt -->
        <div class="card" style="max-width:500px">
            <div class="card__header"><h3>Kích hoạt / Gia hạn</h3></div>
            <div class="card__body">
                <form @submit.prevent="activate()">

                    <!-- Học viên — searchable dropdown -->
                    <div class="form-group">
                        <label>Học viên <span style="color:red">*</span></label>
                        <!-- BUG FIX: @click.outside đặt ở wrapper, không phải dropdown -->
                        <div class="ss-wrap" x-ref="studentPicker" @click.outside="studentOpen = false">
                            <input class="form-input"
                                   type="text"
                                   x-model="studentSearch"
                                   @input="onStudentSearch()"
                                   @focus="studentOpen = true"
                                   @keydown.escape="studentOpen = false"
                                   @keydown.arrow-down.prevent="studentFocus = Math.min(studentFocus + 1, filteredStudents.length - 1)"
                                   @keydown.arrow-up.prevent="studentFocus = Math.max(studentFocus - 1, 0)"
                                   @keydown.enter.prevent="selectStudentByIndex(studentFocus)"
                                   placeholder="Tìm theo tên, email, username..."
                                   autocomplete="off">

                            <!-- Overlay hiển thị khi đã chọn -->
                            <div x-show="form.student_id && !studentOpen"
                                 @click="clearStudent()"
                                 class="ss-selected">
                                <span x-text="selectedStudentLabel" style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"></span>
                                <span class="ss-clear" title="Xóa">×</span>
                            </div>

                            <!-- Dropdown -->
                            <div class="ss-dropdown" x-show="studentOpen">
                                <div x-show="loadingStudents" class="ss-hint">Đang tìm...</div>
                                <div x-show="!loadingStudents && filteredStudents.length === 0" class="ss-hint">
                                    <span x-text="studentSearch ? 'Không tìm thấy học viên.' : 'Gõ để tìm kiếm...'"></span>
                                </div>
                                <template x-for="(s, idx) in filteredStudents" :key="s.uuid">
                                    <div class="ss-option"
                                         :class="idx === studentFocus ? 'ss-option--active' : ''"
                                         @mousedown.prevent="selectStudent(s)">
                                        <div style="font-weight:500;font-size:13px" x-text="s.full_name"></div>
                                        <div style="font-size:11px;color:var(--color-text-muted)"
                                             x-text="s.email + (s.username ? ' · @' + s.username : '')"></div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>

                    <!-- Gói học -->
                    <div class="form-group">
                        <label>Gói học <span style="color:red">*</span></label>
                        <select class="form-input" x-model="form.package_key" required>
                            <option value="">-- Chọn gói --</option>
                            <template x-for="pkg in activePackages" :key="pkg.package_key">
                                <option :value="pkg.package_key"
                                        x-text="pkg.name + ' — ' + pkg.days_to_add + ' ngày — ' + Number(pkg.price).toLocaleString('vi-VN') + '₫'">
                                </option>
                            </template>
                        </select>
                    </div>

                    <button type="submit" class="btn btn--primary btn--full"
                            :disabled="activating || !form.student_id || !form.package_key">
                        <span x-text="activating ? 'Đang xử lý...' : '⚡ Kích hoạt gói học'"></span>
                    </button>
                </form>

                <template x-if="result">
                    <div style="margin-top:16px;padding:14px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;font-size:13px">
                        <div style="font-weight:600;color:#16a34a;margin-bottom:8px">✅ Kích hoạt thành công</div>
                        <div style="display:grid;gap:4px">
                            <div>Học viên: <strong x-text="result.student_name"></strong></div>
                            <div>Gói: <strong x-text="result.package_key"></strong></div>
                            <div>Hết hạn: <strong x-text="result.expired_date"></strong></div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- ===== TAB: QUẢN LÝ GÓI ===== -->
    <div x-show="tab === 'packages'">

        <!-- Form tạo gói mới -->
        <div class="card" style="margin-bottom:24px">
            <div class="card__header" style="cursor:pointer;display:flex;align-items:center;justify-content:space-between"
                 @click="showCreateForm = !showCreateForm">
                <h3>➕ Thêm gói tùy chọn</h3>
                <span x-text="showCreateForm ? '▲' : '▼'" style="font-size:12px;color:var(--color-text-muted)"></span>
            </div>
            <div x-show="showCreateForm" x-transition>
                <div class="card__body">
                    <form @submit.prevent="createPackage()" style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                        <div class="form-group" style="margin:0">
                            <label>Tên gói <span style="color:red">*</span></label>
                            <input class="form-input" x-model="newPkg.name"
                                   @input="autoKey()"
                                   placeholder="VD: Gói 2 tháng" required>
                        </div>
                        <div class="form-group" style="margin:0">
                            <label>Mã gói <span style="color:red">*</span>
                                <span style="font-size:10px;color:var(--color-text-muted)">(chữ hoa, gạch dưới)</span>
                            </label>
                            <input class="form-input" x-model="newPkg.package_key"
                                   placeholder="VD: 2_MONTHS" required>
                        </div>
                        <div class="form-group" style="margin:0">
                            <label>Số ngày <span style="color:red">*</span></label>
                            <input class="form-input" type="number" x-model="newPkg.days_to_add"
                                   min="1" placeholder="60" required>
                        </div>
                        <div class="form-group" style="margin:0">
                            <label>Giá (VNĐ) <span style="color:red">*</span></label>
                            <input class="form-input" type="number" x-model="newPkg.price"
                                   min="0" placeholder="800000" required>
                        </div>
                        <div class="form-group" style="margin:0;grid-column:span 2">
                            <label>Mô tả</label>
                            <input class="form-input" x-model="newPkg.description" placeholder="Mô tả ngắn về gói...">
                        </div>
                        <div style="grid-column:span 2;display:flex;gap:8px">
                            <button type="submit" class="btn btn--primary btn--sm" :disabled="creating">
                                <span x-text="creating ? 'Đang tạo...' : 'Tạo gói'"></span>
                            </button>
                            <button type="button" class="btn btn--secondary btn--sm" @click="resetNewPkg()">Đặt lại</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Danh sách tất cả gói -->
        <div x-show="loadingAllPkg" style="color:var(--color-text-muted);font-size:13px">Đang tải...</div>
        <div class="grid grid--3" x-show="!loadingAllPkg">
            <template x-for="pkg in allPackages" :key="pkg.id">
                <div class="card" :style="!pkg.is_active ? 'opacity:.65' : ''">
                    <div class="card__body" style="padding:16px">

                        <!-- Header: tên + toggle -->
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px">
                            <div style="flex:1;min-width:0">
                                <!-- View mode -->
                                <div x-show="editingId !== pkg.id">
                                    <h4 x-text="pkg.name" style="margin:0 0 2px;font-size:14px"></h4>
                                    <code style="font-size:11px;color:var(--color-text-muted)" x-text="pkg.package_key"></code>
                                </div>
                                <!-- Edit mode: tên -->
                                <div x-show="editingId === pkg.id">
                                    <input class="form-input form-input--sm" x-model="editBuf.name"
                                           style="margin-bottom:4px" placeholder="Tên gói">
                                    <code style="font-size:11px;color:var(--color-text-muted)" x-text="pkg.package_key"></code>
                                </div>
                            </div>
                            <label class="toggle-switch" style="flex-shrink:0;margin-left:8px">
                                <input type="checkbox" :checked="pkg.is_active"
                                       @change="togglePkg(pkg, $event.target.checked)">
                                <span class="toggle-switch__slider"></span>
                            </label>
                        </div>

                        <!-- View mode: giá + ngày + mô tả -->
                        <div x-show="editingId !== pkg.id">
                            <div style="font-size:20px;font-weight:700;color:var(--color-primary)"
                                 x-text="Number(pkg.price).toLocaleString('vi-VN') + '₫'"></div>
                            <div style="font-size:12px;color:var(--color-text-muted);margin-top:2px"
                                 x-text="pkg.days_to_add + ' ngày'"></div>
                            <div x-show="pkg.description"
                                 style="font-size:12px;color:var(--color-text-muted);margin-top:6px"
                                 x-text="pkg.description"></div>
                        </div>

                        <!-- Edit mode: giá + ngày + mô tả -->
                        <div x-show="editingId === pkg.id" style="display:grid;gap:8px;margin-top:4px">
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                                <div>
                                    <label style="font-size:11px;color:var(--color-text-muted)">Giá (₫)</label>
                                    <input class="form-input form-input--sm" type="number"
                                           x-model="editBuf.price" min="0">
                                </div>
                                <div>
                                    <label style="font-size:11px;color:var(--color-text-muted)">Số ngày</label>
                                    <input class="form-input form-input--sm" type="number"
                                           x-model="editBuf.days_to_add" min="1">
                                </div>
                            </div>
                            <div>
                                <label style="font-size:11px;color:var(--color-text-muted)">Mô tả</label>
                                <input class="form-input form-input--sm" x-model="editBuf.description"
                                       placeholder="Mô tả ngắn...">
                            </div>
                        </div>

                        <!-- Actions -->
                        <div style="display:flex;gap:6px;margin-top:12px">
                            <!-- View mode -->
                            <template x-if="editingId !== pkg.id">
                                <button class="btn btn--secondary btn--sm"
                                        @click="startEdit(pkg)">✏️ Sửa</button>
                            </template>
                            <!-- Edit mode -->
                            <template x-if="editingId === pkg.id">
                                <div style="display:flex;gap:6px;width:100%">
                                    <button class="btn btn--primary btn--sm" style="flex:1"
                                            :disabled="saving"
                                            @click="saveEdit(pkg)"
                                            x-text="saving ? 'Đang lưu...' : '💾 Lưu'">
                                    </button>
                                    <button class="btn btn--secondary btn--sm"
                                            @click="cancelEdit()">Hủy</button>
                                </div>
                            </template>
                        </div>

                    </div>
                </div>
            </template>
        </div>
    </div>

</div>

<style>
/* Searchable select */
.ss-wrap { position: relative; }
.ss-selected {
    position: absolute; inset: 0;
    display: flex; align-items: center; gap: 8px;
    padding: 0 10px;
    background: var(--color-surface, #fff);
    border: 1px solid var(--color-border, #e2e8f0);
    border-radius: var(--radius-sm, 6px);
    font-size: 14px; cursor: pointer; z-index: 2;
}
.ss-clear { font-size: 18px; color: var(--color-text-muted); flex-shrink: 0; }
.ss-clear:hover { color: var(--color-danger, #ef4444); }
.ss-dropdown {
    position: absolute; top: calc(100% + 4px); left: 0; right: 0;
    background: var(--color-surface, #fff);
    border: 1px solid var(--color-border, #e2e8f0);
    border-radius: var(--radius-sm, 6px);
    box-shadow: 0 4px 16px rgba(0,0,0,.1);
    max-height: 230px; overflow-y: auto; z-index: 50;
}
.ss-hint { padding: 12px 14px; font-size: 13px; color: var(--color-text-muted); }
.ss-option { padding: 10px 14px; cursor: pointer; border-bottom: 1px solid #f1f5f9; }
.ss-option:last-child { border-bottom: none; }
.ss-option:hover, .ss-option--active { background: var(--color-primary-light, #eff6ff); }

/* Small form inputs for inline edit */
.form-input--sm { padding: 5px 8px; font-size: 13px; }

/* Toggle switch (copy từ modules/index.php) */
.toggle-switch { position:relative;display:inline-block;width:40px;height:22px;cursor:pointer; }
.toggle-switch input { opacity:0;width:0;height:0; }
.toggle-switch__slider { position:absolute;inset:0;background:#ccc;border-radius:22px;transition:.2s; }
.toggle-switch__slider::before { content:'';position:absolute;width:16px;height:16px;left:3px;top:3px;background:#fff;border-radius:50%;transition:.2s; }
.toggle-switch input:checked + .toggle-switch__slider { background:var(--color-primary,#4f46e5); }
.toggle-switch input:checked + .toggle-switch__slider::before { transform:translateX(18px); }
</style>

<script>
function subscriptionManager() {
    return {
        tab: 'activate',

        // Packages
        activePackages: [],
        allPackages: [],
        loadingPkg: false,
        loadingAllPkg: false,

        // Activate form
        activating: false,
        result: null,
        form: { student_id: '', package_key: '' },

        // Student picker
        studentSearch: '',
        studentOpen: false,
        studentFocus: 0,
        loadingStudents: false,
        filteredStudents: [],
        selectedStudentLabel: '',
        _searchTimer: null,

        // Create package form
        showCreateForm: false,
        creating: false,
        newPkg: { package_key: '', name: '', days_to_add: '', price: '', description: '' },

        // Inline edit
        editingId: null,
        editBuf: {},
        saving: false,

        async init() {
            this.loadingPkg = true;
            const data = await apiGet('/api/admin/subscriptions/packages');
            if (data?.status === 'success') this.activePackages = data.data;
            this.loadingPkg = false;
            await this.fetchStudents('');
        },

        async loadAllPackages() {
            if (this.allPackages.length) return;
            this.loadingAllPkg = true;
            const data = await apiGet('/api/admin/subscriptions/packages/all');
            if (data?.status === 'success') this.allPackages = data.data;
            this.loadingAllPkg = false;
        },

        // ---- Student picker ----
        async fetchStudents(q) {
            this.loadingStudents = true;
            const qs = q ? `&search=${encodeURIComponent(q)}` : '';
            const data = await apiGet('/api/admin/users?role=user&per_page=50' + qs);
            this.filteredStudents = data?.status === 'success' ? data.data.users : [];
            this.studentFocus = 0;
            this.loadingStudents = false;
        },

        onStudentSearch() {
            this.studentOpen = true;
            this.form.student_id = '';
            this.selectedStudentLabel = '';
            clearTimeout(this._searchTimer);
            this._searchTimer = setTimeout(() => this.fetchStudents(this.studentSearch), 300);
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

        // ---- Activate ----
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
            } else {
                showToast('error', data?.message || 'Kích hoạt thất bại.');
            }
            this.activating = false;
        },

        // ---- Create package ----
        autoKey() {
            // Tự sinh package_key từ tên nếu người dùng chưa nhập tay
            const auto = this.newPkg.name
                .toUpperCase()
                .normalize('NFD').replace(/[̀-ͯ]/g, '')
                .replace(/Đ/g, 'D')
                .replace(/[^A-Z0-9\s]/g, '')
                .trim()
                .replace(/\s+/g, '_');
            this.newPkg.package_key = auto;
        },

        resetNewPkg() {
            this.newPkg = { package_key: '', name: '', days_to_add: '', price: '', description: '' };
        },

        async createPackage() {
            this.creating = true;
            const body = new URLSearchParams({
                package_key: this.newPkg.package_key,
                name:        this.newPkg.name,
                days_to_add: this.newPkg.days_to_add,
                price:       this.newPkg.price,
                description: this.newPkg.description,
            });
            const data = await apiPost('/api/admin/subscriptions/packages', body);
            if (data?.status === 'success') {
                showToast('success', data.message);
                this.allPackages.push(data.data);
                this.activePackages = this.allPackages.filter(p => p.is_active);
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
                this.activePackages = this.allPackages.filter(p => p.is_active);
                showToast('success', data.message);
            } else {
                pkg.is_active = enable ? 0 : 1; // revert
                showToast('error', data?.message || 'Có lỗi xảy ra.');
            }
        },

        startEdit(pkg) {
            this.editingId = pkg.id;
            this.editBuf = {
                name:        pkg.name,
                days_to_add: pkg.days_to_add,
                price:       pkg.price,
                description: pkg.description || '',
            };
        },

        cancelEdit() {
            this.editingId = null;
            this.editBuf = {};
        },

        async saveEdit(pkg) {
            this.saving = true;
            const body = JSON.stringify({
                name:        this.editBuf.name,
                days_to_add: Number(this.editBuf.days_to_add),
                price:       Number(this.editBuf.price),
                description: this.editBuf.description,
            });
            const data = await apiRequest(`/api/admin/subscriptions/packages/${pkg.package_key}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body,
            });
            if (data?.status === 'success') {
                Object.assign(pkg, data.data);
                this.activePackages = this.allPackages.filter(p => p.is_active);
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
</script>

<?= $this->endSection() ?>
