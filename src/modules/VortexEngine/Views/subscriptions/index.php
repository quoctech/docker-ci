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

<?= $this->section('styles') ?>
<link rel="stylesheet" href="/assets/modules/VortexEngine/vortex-engine.css">
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="/assets/modules/VortexEngine/vortex-engine.js"></script>
<?= $this->endSection() ?>

<?= $this->endSection() ?>
