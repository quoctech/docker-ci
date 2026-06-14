<?= $this->extend('layouts/admin') ?>
<?= $this->section('title') ?>Quản lý gói học<?= $this->endSection() ?>
<?= $this->section('breadcrumb') ?>Tính năng / Quản lý gói học<?= $this->endSection() ?>
<?= $this->section('content') ?>

<div x-data="subscriptionManager()" x-init="init()">

    <!-- ===== PAGE HEADER ===== -->
    <div class="sub-header">
        <div>
            <h1 class="content__title" style="margin-bottom:2px">💎 Quản lý gói học</h1>
            <p class="content__subtitle">Kích hoạt và quản lý gói đăng ký học viên.</p>
        </div>
        <div class="sub-tabs">
            <button class="sub-tab" :class="{'sub-tab--active': tab === 'activate'}" @click="tab = 'activate'">
                <span>⚡</span> Kích hoạt
            </button>
            <button class="sub-tab" :class="{'sub-tab--active': tab === 'packages'}"
                    @click="tab = 'packages'; loadAllPackages()">
                <span>📦</span> Quản lý gói
            </button>
            <button class="sub-tab" :class="{'sub-tab--active': tab === 'list'}"
                    @click="tab = 'list'; loadSubList()">
                <span>📋</span> Danh sách
            </button>
        </div>
    </div>

    <!-- ===== TAB: KÍCH HOẠT ===== -->
    <div x-show="tab === 'activate'">
        <div class="activate-layout">

            <!-- LEFT: Student + Confirm -->
            <div class="activate-col activate-col--left">

                <!-- Step 1: Student -->
                <div class="step-card">
                    <div class="step-label"><span class="step-num">1</span> Chọn học viên</div>

                    <div class="ss-wrap" x-ref="studentPicker" @click.outside="studentOpen = false">
                        <div class="ss-field" :class="{'ss-field--focus': studentOpen}">
                            <svg class="ss-field__icon" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"/>
                            </svg>
                            <input type="text"
                                   class="ss-field__input"
                                   x-model="studentSearch"
                                   @input="onStudentSearch()"
                                   @focus="studentOpen = true"
                                   @keydown.escape="studentOpen = false"
                                   @keydown.arrow-down.prevent="studentFocus = Math.min(studentFocus+1, filteredStudents.length-1)"
                                   @keydown.arrow-up.prevent="studentFocus = Math.max(studentFocus-1, 0)"
                                   @keydown.enter.prevent="selectStudentByIndex(studentFocus)"
                                   :placeholder="form.student_id && !studentOpen ? '' : 'Tìm theo tên, email, username...'"
                                   autocomplete="off">
                            <div x-show="form.student_id && !studentOpen"
                                 class="ss-field__selected">
                                <span x-text="selectedStudentLabel.split(' — ')[0]" class="ss-field__selected-name"></span>
                                <button class="ss-field__clear" @click.stop="clearStudent()" title="Xóa">×</button>
                            </div>
                        </div>
                        <div class="ss-dropdown" x-show="studentOpen">
                            <div x-show="loadingStudents" class="ss-hint">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation:spin 1s linear infinite;flex-shrink:0"><path d="M21 12a9 9 0 11-6.219-8.56"/></svg>
                                Đang tìm kiếm...
                            </div>
                            <div x-show="!loadingStudents && filteredStudents.length === 0" class="ss-hint">
                                <span x-text="studentSearch ? 'Không tìm thấy học viên.' : 'Gõ để tìm kiếm...'"></span>
                            </div>
                            <template x-for="(s, idx) in filteredStudents" :key="s.uuid">
                                <div class="ss-option" :class="{'ss-option--active': idx === studentFocus}"
                                     @mousedown.prevent="selectStudent(s)">
                                    <div class="ss-avatar" x-text="s.full_name.charAt(0).toUpperCase()"></div>
                                    <div>
                                        <div class="ss-option__name" x-text="s.full_name"></div>
                                        <div class="ss-option__sub" x-text="'@' + (s.username || '—') + ' · ' + s.email + (s.grade ? ' · Lớp ' + s.grade : '')"></div>
                                    </div>
                                </div>
                            </template>
                            <div x-show="!loadingStudents && _studentHasMore" class="ss-loadmore">
                                <button class="ss-loadmore-btn" @mousedown.prevent="loadMoreStudents()">
                                    <span x-show="!loadingStudents">Tải thêm</span>
                                </button>
                            </div>
                            <div x-show="loadingStudents && filteredStudents.length > 0" class="ss-hint">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation:spin 1s linear infinite;flex-shrink:0"><path d="M21 12a9 9 0 11-6.219-8.56"/></svg>
                                Đang tải thêm...
                            </div>
                        </div>
                    </div>

                    <!-- Selected student badge -->
                    <div x-show="form.student_id" x-transition class="selected-student">
                        <div class="selected-student__avatar" x-text="selectedStudentLabel.charAt(0).toUpperCase()"></div>
                        <div class="selected-student__info">
                            <div class="selected-student__name" x-text="selectedStudentLabel.split(' — ')[0]"></div>
                            <div class="selected-student__email" x-text="selectedStudentLabel.split(' — ')[1] || ''"></div>
                        </div>
                        <div class="selected-student__check">✓</div>
                    </div>
                </div>

                <!-- Step 3: Confirm (chỉ hiện khi đã chọn đủ) -->
                <div class="step-card" x-show="form.student_id && form.package_key" x-transition>
                    <div class="step-label"><span class="step-num">3</span> Xác nhận</div>
                    <template x-if="form.package_key">
                        <div class="confirm-box">
                            <template x-for="pkg in activePackages.filter(p => p.package_key === form.package_key)">
                                <div>
                                    <div class="confirm-row">
                                        <span class="confirm-row__label">Học viên</span>
                                        <span class="confirm-row__value" x-text="selectedStudentLabel.split(' — ')[0]"></span>
                                    </div>
                                    <div class="confirm-row">
                                        <span class="confirm-row__label">Gói học</span>
                                        <span class="confirm-row__value" x-text="pkg.name"></span>
                                    </div>
                                    <div class="confirm-row">
                                        <span class="confirm-row__label">Thời hạn</span>
                                        <span class="confirm-row__value" x-text="pkg.days_to_add + ' ngày'"></span>
                                    </div>
                                    <div class="confirm-row confirm-row--price">
                                        <span class="confirm-row__label">Thanh toán</span>
                                        <span class="confirm-row__price" x-text="Number(pkg.price) === 0 ? 'Miễn phí' : Number(pkg.price).toLocaleString('vi-VN') + '₫'"></span>
                                    </div>
                                </div>
                            </template>
                            <button class="btn-activate" @click="activate()" :disabled="activating">
                                <template x-if="!activating">
                                    <span>⚡ Kích hoạt ngay</span>
                                </template>
                                <template x-if="activating">
                                    <span style="display:flex;align-items:center;gap:8px">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation:spin 1s linear infinite"><path d="M21 12a9 9 0 11-6.219-8.56"/></svg>
                                        Đang xử lý...
                                    </span>
                                </template>
                            </button>
                        </div>
                    </template>
                </div>

                <!-- Success result -->
                <template x-if="result">
                    <div class="result-banner" x-transition>
                        <div class="result-banner__icon">🎉</div>
                        <div class="result-banner__body">
                            <div class="result-banner__title">Kích hoạt thành công!</div>
                            <div class="result-banner__rows">
                                <span x-text="result.student_name?.split(' — ')[0] || result.student_name"></span>
                                <span class="result-sep">·</span>
                                <span x-text="result.package_key"></span>
                                <span class="result-sep">·</span>
                                <span>Hết hạn: <strong x-text="result.expired_date"></strong></span>
                            </div>
                        </div>
                        <button class="result-banner__close" @click="result = null">×</button>
                    </div>
                </template>
            </div>

            <!-- RIGHT: Package selection -->
            <div class="activate-col activate-col--right">
                <div class="step-card" style="height:100%">
                    <div class="step-label"><span class="step-num">2</span> Chọn gói học</div>
                    <div x-show="loadingPkg" style="padding:24px;color:var(--color-text-muted);font-size:13px">Đang tải...</div>
                    <div class="pkg-list" x-show="!loadingPkg">
                        <template x-for="pkg in activePackages" :key="pkg.package_key">
                            <div class="pkg-option"
                                 :class="{'pkg-option--selected': form.package_key === pkg.package_key}"
                                 @click="form.package_key = pkg.package_key">
                                <div class="pkg-option__radio">
                                    <div class="pkg-option__dot" x-show="form.package_key === pkg.package_key"></div>
                                </div>
                                <div class="pkg-option__info">
                                    <div class="pkg-option__name" x-text="pkg.name"></div>
                                    <div class="pkg-option__days" x-text="pkg.days_to_add + ' ngày truy cập đầy đủ'"></div>
                                </div>
                                <div class="pkg-option__price">
                                    <span x-text="Number(pkg.price) === 0 ? 'Miễn phí' : Number(pkg.price).toLocaleString('vi-VN') + '₫'"></span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- ===== TAB: QUẢN LÝ GÓI ===== -->
    <div x-show="tab === 'packages'">

        <!-- Toolbar -->
        <div class="pkg-toolbar">
            <span style="font-size:13px;color:var(--color-text-muted)" x-show="!loadingAllPkg">
                <strong x-text="allPackages.length"></strong> gói học
            </span>
            <button class="btn btn--primary btn--sm" @click="showCreateForm = !showCreateForm">
                <span x-text="showCreateForm ? '✕ Đóng' : '＋ Thêm gói mới'"></span>
            </button>
        </div>

        <!-- Create form panel -->
        <div x-show="showCreateForm" x-transition class="create-panel">
            <div class="create-panel__title">Tạo gói học mới</div>
            <div class="create-panel__grid">
                <div class="form-group" style="margin:0">
                    <label class="form-label">Tên gói <span class="required">*</span></label>
                    <input class="form-input" x-model="newPkg.name" @input="autoKey()" placeholder="VD: Gói 2 tháng" required>
                </div>
                <div class="form-group" style="margin:0">
                    <label class="form-label">
                        Mã gói <span class="required">*</span>
                        <span class="hint">UPPER_CASE</span>
                    </label>
                    <input class="form-input" x-model="newPkg.package_key" placeholder="VD: 2_MONTHS" required>
                </div>
                <div class="form-group" style="margin:0">
                    <label class="form-label">Số ngày <span class="required">*</span></label>
                    <input class="form-input" type="number" x-model="newPkg.days_to_add" min="1" placeholder="60" required>
                </div>
                <div class="form-group" style="margin:0">
                    <label class="form-label">Giá (VNĐ) <span class="required">*</span></label>
                    <input class="form-input" type="number" x-model="newPkg.price" min="0" placeholder="800000" required>
                </div>
                <div class="form-group" style="margin:0">
                    <label class="form-label">Loại đăng ký <span class="required">*</span></label>
                    <select class="form-input" x-model="newPkg.sub_type">
                        <option value="VIP">Premium</option>
                        <option value="TRIAL">Dùng thử</option>
                    </select>
                </div>
                <div class="form-group" style="margin:0">
                    <label class="form-label">Số học sinh tối đa <span class="hint">1 = đơn, >1 = gói kép</span></label>
                    <input class="form-input" type="number" x-model="newPkg.max_students" min="1" max="10" placeholder="1">
                </div>
                <div class="form-group" style="margin:0;grid-column:span 2">
                    <label class="form-label">Lớp học được phép <span class="hint">bỏ trống = tất cả lớp</span></label>
                    <div class="grade-checks">
                        <template x-for="g in [1,2,3,4,5,6,7,8,9]" :key="g">
                            <label class="grade-check">
                                <input type="checkbox" :value="g" x-model="newPkg.allowed_grades">
                                <span x-text="'L' + g"></span>
                            </label>
                        </template>
                    </div>
                </div>
                <div class="form-group" style="margin:0;grid-column:span 2">
                    <label class="form-label">Mô tả</label>
                    <input class="form-input" x-model="newPkg.description" placeholder="Mô tả ngắn về gói...">
                </div>
                <div style="grid-column:span 2;display:flex;gap:8px">
                    <button class="btn btn--primary btn--sm" @click="createPackage()" :disabled="creating">
                        <span x-text="creating ? 'Đang tạo...' : 'Tạo gói'"></span>
                    </button>
                    <button class="btn btn--secondary btn--sm" @click="resetNewPkg()">Đặt lại</button>
                </div>
            </div>
        </div>

        <!-- Package cards grid -->
        <div x-show="loadingAllPkg" style="padding:60px;text-align:center;color:var(--color-text-muted)">Đang tải...</div>
        <div class="pkg-cards-grid" x-show="!loadingAllPkg" style="align-items:start">
            <template x-for="pkg in allPackages" :key="pkg.id">
                <div class="pkg-card" :class="{'pkg-card--inactive': !Number(pkg.is_active)}">
                    <div class="pkg-card__stripe"></div>
                    <div class="pkg-card__inner">

                        <!-- Header row -->
                        <div class="pkg-card__head">
                            <div style="flex:1;min-width:0">
                                <div x-show="editingId !== pkg.id">
                                    <div class="pkg-card__name" x-text="pkg.name"></div>
                                    <code class="pkg-card__code" x-text="pkg.package_key"></code>
                                </div>
                                <div x-show="editingId === pkg.id">
                                    <input class="form-input form-input--sm" x-model="editBuf.name"
                                           placeholder="Tên gói" style="margin-bottom:4px">
                                    <code class="pkg-card__code" x-text="pkg.package_key"></code>
                                </div>
                            </div>
                            <label class="toggle-switch" style="flex-shrink:0;margin-left:8px">
                                <input type="checkbox" :checked="Number(pkg.is_active)"
                                       @change="togglePkg(pkg, $event.target.checked)">
                                <span class="toggle-switch__slider"></span>
                            </label>
                        </div>

                        <!-- Pricing (view) -->
                        <div x-show="editingId !== pkg.id" class="pkg-card__pricing">
                            <div class="pkg-card__price"
                                 x-text="Number(pkg.price) === 0 ? 'Miễn phí' : Number(pkg.price).toLocaleString('vi-VN') + '₫'"></div>
                            <div class="pkg-card__days-tag" x-text="pkg.days_to_add + ' ngày'"></div>
                        </div>

                        <!-- Fields (edit) -->
                        <div x-show="editingId === pkg.id" style="display:grid;gap:8px;margin:12px 0">
                            <div class="pkg-edit-row">
                                <div>
                                    <label class="edit-label">Giá (₫)</label>
                                    <input class="form-input form-input--sm" type="number" x-model="editBuf.price" min="0">
                                </div>
                                <div>
                                    <label class="edit-label">Số ngày</label>
                                    <input class="form-input form-input--sm" type="number" x-model="editBuf.days_to_add" min="1">
                                </div>
                            </div>
                            <div class="pkg-edit-row">
                                <div>
                                    <label class="edit-label">Loại đăng ký</label>
                                    <select class="form-input form-input--sm" x-model="editBuf.sub_type">
                                        <option value="VIP">Premium</option>
                                        <option value="TRIAL">Dùng thử</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="edit-label">Số HS tối đa</label>
                                    <input class="form-input form-input--sm" type="number" x-model="editBuf.max_students" min="1" max="10">
                                </div>
                            </div>
                            <div>
                                <label class="edit-label">Mô tả</label>
                                <input class="form-input form-input--sm" x-model="editBuf.description" placeholder="Mô tả...">
                            </div>
                            <div>
                                <label class="edit-label">Lớp học được phép <span class="hint">(bỏ trống = tất cả)</span></label>
                                <div class="grade-checks grade-checks--sm">
                                    <template x-for="g in [1,2,3,4,5,6,7,8,9]" :key="g">
                                        <label class="grade-check">
                                            <input type="checkbox" :value="g" x-model="editBuf.allowed_grades">
                                            <span x-text="'L' + g"></span>
                                        </label>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <!-- Description + badges (view) -->
                        <div x-show="editingId !== pkg.id" style="margin-bottom:6px">
                            <div x-show="pkg.description" class="pkg-card__desc" x-text="pkg.description"></div>
                            <div style="display:flex;flex-wrap:wrap;gap:4px;margin-top:6px">
                                <span class="badge-tag"
                                      :class="pkg.sub_type === 'TRIAL' ? 'badge-tag--blue' : 'badge-tag--purple'"
                                      x-text="pkg.sub_type === 'TRIAL' ? 'Dùng thử' : 'Premium'"></span>
                                <span x-show="Number(pkg.max_students) > 1"
                                      class="badge-tag badge-tag--purple"
                                      x-text="'👥 ' + pkg.max_students + ' học sinh'"></span>
                                <template x-if="pkg.allowed_grades">
                                    <span class="badge-tag badge-tag--blue"
                                          x-text="'Lớp: ' + JSON.parse(pkg.allowed_grades).join(', ')"></span>
                                </template>
                                <span x-show="!pkg.allowed_grades" class="badge-tag badge-tag--gray">Tất cả lớp</span>
                            </div>
                        </div>

                        <!-- Footer actions -->
                        <div class="pkg-card__footer">
                            <template x-if="editingId !== pkg.id">
                                <button class="btn btn--secondary btn--sm" @click="startEdit(pkg)">✏️ Chỉnh sửa</button>
                            </template>
                            <template x-if="editingId === pkg.id">
                                <div style="display:flex;gap:6px;justify-content:flex-end">
                                    <button class="btn btn--secondary btn--sm" style="width:72px" @click="cancelEdit()">Hủy</button>
                                    <button class="btn btn--primary btn--sm" style="width:72px"
                                            :disabled="saving" @click="saveEdit(pkg)"
                                            x-text="saving ? '...' : 'Lưu'"></button>
                                </div>
                            </template>
                        </div>

                    </div>
                </div>
            </template>
        </div>
    </div>

    <!-- ===== TAB: DANH SÁCH ===== -->
    <div x-show="tab === 'list'">

        <!-- Filters -->
        <div class="filter-bar" style="margin-bottom:16px;padding:0">
            <input class="form-input filter-bar__search" style="height:36px"
                   placeholder="Tìm tên, email, username..."
                   x-model="subFilters.search"
                   @input.debounce.400ms="loadSubList(true)">
            <select class="form-input" style="height:36px"
                    x-model="subFilters.status" @change="loadSubList(true)">
                <option value="">Tất cả trạng thái</option>
                <option value="VIP">Premium</option>
                <option value="TRIAL">Dùng thử</option>
                <option value="EXPIRED">Hết hạn</option>
            </select>
            <select class="form-input" style="height:36px"
                    x-model="subFilters.grade" @change="loadSubList(true)">
                <option value="">Tất cả lớp</option>
                <template x-for="g in [1,2,3,4,5,6,7,8,9]" :key="g">
                    <option :value="g" x-text="'Lớp ' + g"></option>
                </template>
            </select>
            <span class="filter-bar__count" x-text="'Tổng đăng ký: ' + subPagination.total"></span>
        </div>

        <div class="card">
            <div class="card__body" style="padding:0">
                <div x-show="loadingSubs" style="padding:40px;text-align:center;color:var(--color-text-muted)">Đang tải...</div>
                <div class="table-wrapper" x-show="!loadingSubs">
                    <table class="table table-card">
                        <thead>
                            <tr>
                                <th>Học sinh</th>
                                <th class="hide-mobile">Lớp</th>
                                <th class="hide-mobile">Gói học</th>
                                <th class="hide-tablet">Lớp được học</th>
                                <th>Trạng thái</th>
                                <th class="hide-tablet">Bắt đầu</th>
                                <th>Hết hạn</th>
                                <th style="width:48px"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="s in subscriptions" :key="s.id">
                                <tr>
                                    <td data-label="Học sinh">
                                        <div style="font-weight:500;font-size:13px" x-text="s.full_name || '—'"></div>
                                        <div style="font-size:11px;color:var(--color-text-muted)" x-text="s.email || ''"></div>
                                    </td>
                                    <td data-label="Lớp" class="hide-mobile" style="font-size:13px" x-text="s.grade ? 'Lớp ' + s.grade : '—'"></td>
                                    <td data-label="Gói học" class="hide-mobile">
                                        <div style="font-size:13px;font-weight:500" x-text="s.package_name || s.package_key"></div>
                                        <div style="font-size:11px;color:var(--color-text-muted)"
                                             x-text="s.days_to_add + ' ngày · ' + Number(s.price).toLocaleString('vi-VN') + '₫'"></div>
                                    </td>
                                    <td data-label="Lớp học" class="hide-tablet" style="font-size:12px">
                                        <template x-if="s.allowed_grades">
                                            <span class="badge-tag badge-tag--blue"
                                                  x-text="'Lớp ' + JSON.parse(s.allowed_grades).join(', ')"></span>
                                        </template>
                                        <span x-show="!s.allowed_grades" style="color:var(--color-text-muted)">Tất cả</span>
                                    </td>
                                    <td data-label="Trạng thái">
                                        <span class="badge"
                                              :class="s.status === 'VIP' ? 'badge--success' : s.status === 'TRIAL' ? 'badge--info' : 'badge--danger'"
                                              x-text="s.status === 'VIP' ? 'Premium' : s.status === 'TRIAL' ? 'Dùng thử' : 'Hết hạn'"></span>
                                    </td>
                                    <td data-label="Bắt đầu" class="hide-tablet" style="font-size:12px;color:var(--color-text-muted)" x-text="s.start_date ? s.start_date.substring(0,10) : '—'"></td>
                                    <td data-label="Hết hạn" style="font-size:12px" :style="isExpired(s.expired_date) ? 'color:#ef4444' : ''"
                                        x-text="s.expired_date ? s.expired_date.substring(0,10) : '—'"></td>
                                    <td data-label="">
                                        <button class="btn btn--ghost btn--sm" style="padding:4px 8px;font-size:12px"
                                                @click="openEditSub(s)" title="Sửa">✏️</button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <div x-show="!loadingSubs && subscriptions.length === 0"
                     style="padding:40px;text-align:center;color:var(--color-text-muted)">
                    Chưa có dữ liệu.
                </div>
                <!-- Pagination -->
                <div x-show="subPagination.total_pages > 1" class="pagination-bar">
                    <button class="btn btn--ghost btn--sm"
                            @click="subGoPage(subPagination.page - 1)"
                            :disabled="subPagination.page <= 1">← Trước</button>
                    <span style="padding:6px 12px;font-size:13px"
                          x-text="subPagination.page + ' / ' + subPagination.total_pages"></span>
                    <button class="btn btn--ghost btn--sm"
                            @click="subGoPage(subPagination.page + 1)"
                            :disabled="subPagination.page >= subPagination.total_pages">Sau →</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== MODAL: Sửa subscription ===== -->
    <div x-show="editingSub" x-cloak
         class="modal-overlay"
         @click.self="editingSub = null">
        <div class="modal-box" style="max-width:440px">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
                <h3 style="font-size:15px;font-weight:600;margin:0">Sửa đăng ký</h3>
                <button class="btn btn--ghost btn--sm" @click="editingSub = null">✕</button>
            </div>

            <template x-if="editingSub">
                <div>
                    <div style="margin-bottom:6px;font-size:13px;font-weight:500;color:var(--color-text-muted)">
                        Học viên: <span style="color:var(--color-text)" x-text="editingSub.full_name + ' (' + editingSub.email + ')'"></span>
                    </div>

                    <div class="form-group" style="margin-top:16px">
                        <label class="form-label">Gói học</label>
                        <select class="form-input" x-model="editSubBuf.package_key">
                            <option value="">— Chọn gói —</option>
                            <template x-for="pkg in allPackages" :key="pkg.package_key">
                                <option :value="pkg.package_key" x-text="pkg.name + ' (' + pkg.days_to_add + ' ngày)'"></option>
                            </template>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Trạng thái</label>
                        <select class="form-input" x-model="editSubBuf.status">
                            <option value="VIP">Premium</option>
                            <option value="TRIAL">Dùng thử</option>
                            <option value="EXPIRED">Hết hạn</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Ngày hết hạn</label>
                        <input type="date" class="form-input" x-model="editSubBuf.expired_date">
                        <div style="font-size:11px;color:var(--color-text-muted);margin-top:4px">Để trống nếu không giới hạn thời gian.</div>
                    </div>

                    <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:20px">
                        <button class="btn btn--ghost" style="width:72px" @click="editingSub = null">Hủy</button>
                        <button class="btn btn--primary" style="width:72px" @click="saveSubEdit()" :disabled="savingSub">
                            <span x-show="!savingSub">Lưu</span>
                            <span x-show="savingSub">...</span>
                        </button>
                    </div>
                </div>
            </template>
        </div>
    </div>

</div>

<!-- ===== STYLES ===== -->
<style>
@keyframes spin { to { transform: rotate(360deg); } }

/* ---- Header & tabs ---- */
.sub-header {
    display: flex; justify-content: space-between; align-items: flex-start;
    margin-bottom: 28px; flex-wrap: wrap; gap: 12px;
}
.sub-tabs {
    display: flex; gap: 4px;
    background: var(--color-bg, #f8fafc);
    border: 1px solid var(--color-border, #e2e8f0);
    border-radius: 10px; padding: 4px;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
    flex-shrink: 0;
    max-width: 100%;
}
.sub-tabs::-webkit-scrollbar { display: none; }
.sub-tab {
    display: flex; align-items: center; gap: 6px;
    padding: 7px 14px; border-radius: 7px; border: none; cursor: pointer;
    font-size: 13px; font-weight: 500;
    background: transparent; color: var(--color-text-muted, #64748b);
    transition: all .15s; white-space: nowrap; flex-shrink: 0;
}
.sub-tab:hover { background: rgba(0,0,0,.04); color: var(--color-text, #1e293b); }
.sub-tab--active {
    background: var(--color-surface, #fff);
    color: var(--color-primary, #4f46e5);
    box-shadow: 0 1px 4px rgba(0,0,0,.1);
}
@media (max-width: 639px) {
    .sub-header { flex-direction: column; gap: 10px; }
    .sub-tabs { align-self: stretch; }
    .sub-tab { padding: 7px 12px; font-size: 12px; }
}

/* ---- Activate layout ---- */
.activate-layout {
    display: grid;
    grid-template-columns: 360px 1fr;
    gap: 20px;
    align-items: start;
}
@media (max-width: 900px) {
    .activate-layout { grid-template-columns: 1fr; }
}
.activate-col { display: flex; flex-direction: column; gap: 16px; }

.step-card {
    background: var(--color-surface, #fff);
    border: 1px solid var(--color-border, #e2e8f0);
    border-radius: 12px; padding: 20px;
}
.step-label {
    display: flex; align-items: center; gap: 8px;
    font-size: 12px; font-weight: 600; text-transform: uppercase;
    letter-spacing: .06em; color: var(--color-text-muted, #64748b);
    margin-bottom: 14px;
}
.step-num {
    display: inline-flex; align-items: center; justify-content: center;
    width: 20px; height: 20px; border-radius: 50%;
    background: var(--color-primary, #4f46e5); color: #fff;
    font-size: 11px; font-weight: 700;
}

/* ---- Searchable select ---- */
.ss-wrap { position: relative; }
.ss-field {
    display: flex; align-items: center; gap: 8px;
    padding: 0 12px; height: 42px;
    border: 1.5px solid var(--color-border, #e2e8f0);
    border-radius: 8px; background: var(--color-surface, #fff);
    transition: border-color .15s;
    position: relative;
}
.ss-field--focus { border-color: var(--color-primary, #4f46e5); }
.ss-field__icon { width: 16px; height: 16px; flex-shrink: 0; color: var(--color-text-muted, #94a3b8); }
.ss-field__input {
    flex: 1; border: none; outline: none; background: transparent;
    font-size: 14px; color: var(--color-text, #1e293b); min-width: 0;
}
.ss-field__selected {
    position: absolute; left: 38px; right: 36px;
    display: flex; align-items: center; justify-content: space-between;
    font-size: 14px; color: var(--color-text, #1e293b);
    pointer-events: auto;
}
.ss-field__selected-name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ss-field__clear {
    flex-shrink: 0; border: none; background: none; cursor: pointer;
    font-size: 18px; color: var(--color-text-muted, #94a3b8); line-height: 1;
    padding: 0 4px;
}
.ss-field__clear:hover { color: var(--color-danger, #ef4444); }
.ss-dropdown {
    position: absolute; top: calc(100% + 6px); left: 0; right: 0; z-index: 50;
    background: var(--color-surface, #fff);
    border: 1px solid var(--color-border, #e2e8f0);
    border-radius: 10px;
    box-shadow: 0 8px 24px rgba(0,0,0,.12);
    max-height: 260px; overflow-y: auto;
}
.ss-hint {
    display: flex; align-items: center; gap: 8px;
    padding: 14px 16px; font-size: 13px; color: var(--color-text-muted, #64748b);
}
.ss-avatar {
    width: 32px; height: 32px; border-radius: 50%; flex-shrink: 0;
    background: var(--color-primary-light, #eff6ff);
    color: var(--color-primary, #4f46e5);
    display: flex; align-items: center; justify-content: center;
    font-weight: 600; font-size: 13px;
}
.ss-option {
    display: flex; align-items: center; gap: 12px;
    padding: 10px 16px; cursor: pointer;
    border-bottom: 1px solid var(--color-border, #f1f5f9);
    transition: background .1s;
}
.ss-option:last-child { border-bottom: none; }
.ss-option:hover, .ss-option--active { background: var(--color-primary-light, #eff6ff); }
.ss-option__name { font-size: 13px; font-weight: 500; color: var(--color-text, #1e293b); }
.ss-option__sub { font-size: 11px; color: var(--color-text-muted, #64748b); margin-top: 1px; }
.ss-loadmore { border-top: 1px solid var(--color-border, #e2e8f0); }
.ss-loadmore-btn {
    width: 100%; padding: 8px 16px; background: none; border: none; cursor: pointer;
    font-size: 12px; color: var(--color-primary, #4f46e5); font-weight: 500;
    transition: background .15s;
}
.ss-loadmore-btn:hover { background: var(--color-primary-light, #eff6ff); }

/* ---- Selected student card ---- */
.selected-student {
    display: flex; align-items: center; gap: 12px;
    margin-top: 12px; padding: 12px 14px;
    background: var(--color-primary-light, #eff6ff);
    border: 1px solid rgba(79,70,229,.2); border-radius: 8px;
}
.selected-student__avatar {
    width: 36px; height: 36px; border-radius: 50%; flex-shrink: 0;
    background: var(--color-primary, #4f46e5); color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 14px;
}
.selected-student__info { flex: 1; min-width: 0; }
.selected-student__name { font-size: 13px; font-weight: 600; color: var(--color-text, #1e293b); }
.selected-student__email { font-size: 11px; color: var(--color-text-muted, #64748b); margin-top: 1px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.selected-student__check {
    width: 22px; height: 22px; border-radius: 50%; flex-shrink: 0;
    background: #16a34a; color: #fff;
    display: flex; align-items: center; justify-content: center; font-size: 12px;
}

/* ---- Package list (activate tab) ---- */
.pkg-list { display: flex; flex-direction: column; gap: 8px; }
.pkg-option {
    display: flex; align-items: center; gap: 14px;
    padding: 14px 16px; border-radius: 10px;
    border: 1.5px solid var(--color-border, #e2e8f0);
    cursor: pointer; transition: all .15s;
}
.pkg-option:hover { border-color: var(--color-primary, #4f46e5); background: var(--color-primary-light, #eff6ff); }
.pkg-option--selected {
    border-color: var(--color-primary, #4f46e5);
    background: var(--color-primary-light, #eff6ff);
    box-shadow: 0 0 0 3px rgba(79,70,229,.12);
}
.pkg-option__radio {
    width: 18px; height: 18px; border-radius: 50%; flex-shrink: 0;
    border: 2px solid var(--color-border, #cbd5e1);
    display: flex; align-items: center; justify-content: center;
    transition: border-color .15s;
}
.pkg-option--selected .pkg-option__radio { border-color: var(--color-primary, #4f46e5); }
.pkg-option__dot {
    width: 8px; height: 8px; border-radius: 50%;
    background: var(--color-primary, #4f46e5);
}
.pkg-option__info { flex: 1; }
.pkg-option__name { font-size: 14px; font-weight: 600; color: var(--color-text, #1e293b); }
.pkg-option__days { font-size: 11px; color: var(--color-text-muted, #64748b); margin-top: 2px; }
.pkg-option__price { font-size: 15px; font-weight: 700; color: var(--color-primary, #4f46e5); flex-shrink: 0; }

/* ---- Confirm box ---- */
.confirm-box { display: flex; flex-direction: column; gap: 0; }
.confirm-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 8px 0; border-bottom: 1px solid var(--color-border, #f1f5f9);
    font-size: 13px;
}
.confirm-row:last-of-type { border-bottom: none; }
.confirm-row--price { margin-top: 4px; }
.confirm-row__label { color: var(--color-text-muted, #64748b); }
.confirm-row__value { font-weight: 500; color: var(--color-text, #1e293b); }
.confirm-row__price { font-size: 18px; font-weight: 700; color: var(--color-primary, #4f46e5); }

/* ---- Activate button ---- */
.btn-activate {
    width: 100%; margin-top: 16px; padding: 12px;
    background: linear-gradient(135deg, var(--color-primary, #4f46e5), #7c3aed);
    color: #fff; border: none; border-radius: 10px;
    font-size: 15px; font-weight: 600; cursor: pointer;
    transition: opacity .15s, transform .1s;
    display: flex; align-items: center; justify-content: center; gap: 8px;
}
.btn-activate:hover:not(:disabled) { opacity: .9; transform: translateY(-1px); }
.btn-activate:disabled { opacity: .6; cursor: not-allowed; transform: none; }

/* ---- Result banner ---- */
.result-banner {
    display: flex; align-items: flex-start; gap: 12px;
    padding: 14px 16px;
    background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px;
}
.result-banner__icon { font-size: 20px; flex-shrink: 0; }
.result-banner__body { flex: 1; }
.result-banner__title { font-weight: 600; color: #15803d; font-size: 14px; margin-bottom: 4px; }
.result-banner__rows { font-size: 12px; color: #166534; display: flex; flex-wrap: wrap; gap: 4px; }
.result-sep { color: #bbf7d0; }
.result-banner__close {
    flex-shrink: 0; border: none; background: none; cursor: pointer;
    font-size: 20px; color: #15803d; line-height: 1; padding: 0;
}

/* ---- Package management toolbar ---- */
.pkg-toolbar {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 16px;
}

/* ---- Create panel ---- */
.create-panel {
    background: var(--color-surface, #fff);
    border: 1px solid var(--color-border, #e2e8f0);
    border-radius: 12px; padding: 20px; margin-bottom: 20px;
}
.create-panel__title {
    font-size: 14px; font-weight: 600; color: var(--color-text, #1e293b);
    margin-bottom: 16px;
}
.create-panel__grid {
    display: grid; grid-template-columns: 1fr 1fr; gap: 16px;
}
@media (max-width: 639px) {
    .create-panel__grid { grid-template-columns: 1fr; }
}

/* ---- Package inline-edit 2-col rows ---- */
.pkg-edit-row {
    display: grid; grid-template-columns: 1fr 1fr; gap: 8px;
}
@media (max-width: 479px) {
    .pkg-edit-row { grid-template-columns: 1fr; }
}

/* ---- Package cards grid ---- */
.pkg-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 16px;
}
.pkg-card {
    background: var(--color-surface, #fff);
    border: 1px solid var(--color-border, #e2e8f0);
    border-radius: 12px; overflow: hidden;
    transition: box-shadow .15s, opacity .2s;
}
.pkg-card:hover { box-shadow: 0 4px 20px rgba(0,0,0,.08); }
.pkg-card--inactive { opacity: .55; }
.pkg-card__stripe {
    height: 4px;
    background: linear-gradient(90deg, var(--color-primary, #4f46e5), #7c3aed);
}
.pkg-card--inactive .pkg-card__stripe { background: #cbd5e1; }
.pkg-card__inner { padding: 16px; }
.pkg-card__head { display: flex; align-items: flex-start; gap: 8px; margin-bottom: 14px; }
.pkg-card__name { font-size: 15px; font-weight: 600; color: var(--color-text, #1e293b); }
.pkg-card__code { font-size: 11px; color: var(--color-text-muted, #64748b); background: var(--color-bg, #f8fafc); padding: 2px 6px; border-radius: 4px; display: inline-block; margin-top: 3px; }
.pkg-card__pricing { margin-bottom: 8px; }
.pkg-card__price { font-size: 22px; font-weight: 700; color: var(--color-primary, #4f46e5); line-height: 1.2; }
.pkg-card__days-tag {
    display: inline-block; margin-top: 4px;
    font-size: 11px; padding: 2px 8px; border-radius: 20px;
    background: var(--color-primary-light, #eff6ff); color: var(--color-primary, #4f46e5);
    font-weight: 500;
}
.pkg-card__desc { font-size: 12px; color: var(--color-text-muted, #64748b); line-height: 1.5; margin-bottom: 4px; }
.pkg-card__footer { margin-top: 14px; padding-top: 12px; border-top: 1px solid var(--color-border, #f1f5f9); }

/* ---- Misc ---- */
.form-label { display: block; font-size: 12px; font-weight: 500; color: var(--color-text-muted, #64748b); margin-bottom: 5px; }
.required { color: #ef4444; }
.hint { font-size: 10px; font-weight: 400; color: var(--color-text-muted, #94a3b8); }
.edit-label { display: block; font-size: 11px; color: var(--color-text-muted, #64748b); margin-bottom: 3px; }
.form-input--sm { padding: 5px 8px; font-size: 13px; }

/* Grade checkboxes */
.grade-checks { display: flex; flex-wrap: wrap; gap: 6px; }
.grade-check {
    display: flex; align-items: center; gap: 4px; cursor: pointer;
    padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 500;
    border: 1.5px solid var(--color-border, #e2e8f0);
    transition: all .1s; user-select: none;
}
.grade-check:has(input:checked) {
    background: var(--color-primary, #4f46e5); color: #fff; border-color: var(--color-primary, #4f46e5);
}
.grade-check input { display: none; }
.grade-checks--sm .grade-check { padding: 3px 8px; font-size: 11px; }

/* Badge tags */
.badge-tag {
    display: inline-block; padding: 2px 8px; border-radius: 20px;
    font-size: 11px; font-weight: 500;
}
.badge-tag--purple { background: #f3e8ff; color: #7c3aed; }
.badge-tag--blue   { background: #eff6ff; color: #2563eb; }
.badge-tag--gray   { background: #f1f5f9; color: #64748b; }

/* Toggle switch */
.toggle-switch { position: relative; display: inline-block; width: 40px; height: 22px; cursor: pointer; }
.toggle-switch input { opacity: 0; width: 0; height: 0; }
.toggle-switch__slider { position: absolute; inset: 0; background: #cbd5e1; border-radius: 22px; transition: .2s; }
.toggle-switch__slider::before { content: ''; position: absolute; width: 16px; height: 16px; left: 3px; top: 3px; background: #fff; border-radius: 50%; transition: .2s; }
.toggle-switch input:checked + .toggle-switch__slider { background: var(--color-primary, #4f46e5); }
.toggle-switch input:checked + .toggle-switch__slider::before { transform: translateX(18px); }
</style>

<!-- ===== SCRIPT ===== -->
<script>
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

        // Subscription list
        subscriptions: [],
        loadingSubs: false,
        subFilters: { search: '', status: '', grade: '' },
        subPagination: { page: 1, per_page: 20, total: 0, total_pages: 0 },
        // Edit subscription
        editingSub: null,
        editSubBuf: {},
        savingSub: false,

        async init() {
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

        // ---- Student picker: 10/page, load more, exclude subscribed ----
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
                role: 'user', per_page: 10, page: this._studentPage,
                exclude_subscribed: 1,
            });
            if (q) params.set('search', q);
            const data = await apiGet('/api/admin/users?' + params);
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
                // Refresh student list vì học viên vừa đăng ký không còn trong filter nữa
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
                // Cập nhật dòng trong bảng tại chỗ
                const idx = this.subscriptions.findIndex(s => s.id === this.editingSub.id);
                if (idx !== -1) {
                    this.subscriptions[idx] = {
                        ...this.subscriptions[idx],
                        package_key:  this.editSubBuf.package_key,
                        status:       this.editSubBuf.status,
                        expired_date: this.editSubBuf.expired_date ? this.editSubBuf.expired_date + ' 00:00:00' : null,
                    };
                    // Cập nhật tên gói nếu đổi gói
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
</script>

<?= $this->endSection() ?>
