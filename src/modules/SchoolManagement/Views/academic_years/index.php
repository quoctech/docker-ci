<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Quản lý năm học<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>Tổ chức / Năm học<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div x-data="academicYearManager()" x-init="load()" @click.outside="closeAllDropdowns()"
     x-on:keydown.escape.window="closeAllModals()">

    <!-- ===== Page Header ===== -->
    <div class="page-header">
        <div>
            <h1 class="content__title">Quản lý năm học</h1>
            <p class="content__subtitle">Tạo và quản lý năm học, học kỳ. Là cơ sở để xếp lớp, lập thời khóa biểu và ghi nhận kết quả học tập.</p>
        </div>
        <button class="btn btn--primary btn--sm" @click="openCreate()"
                x-show="canWrite" :title="canWrite ? 'Tạo năm học mới' : 'Bạn không có quyền tạo'">
            + Tạo năm học
        </button>
    </div>

    <!-- ===== Toolbar ===== -->
    <div class="card" style="margin-bottom:20px">
        <div class="filter-bar">
            <input class="form-input filter-bar__search" style="height:36px"
                   placeholder="Tên năm học..."
                   x-model="searchQuery"
                   @input.debounce.300ms="applyFilter()">
            <select class="form-input" style="height:36px" x-model="filterBranchUuid" @change="applyFilter()">
                <option value="">Tất cả chi nhánh</option>
                <template x-for="b in branches" :key="b.uuid">
                    <option :value="b.uuid" x-text="b.name"></option>
                </template>
            </select>
            <span class="filter-bar__count" x-text="'Tổng: ' + filteredYears.length + ' năm học'"></span>
        </div>
    </div>

    <!-- ===== Loading ===== -->
    <div x-show="loading" style="text-align:center;padding:48px;color:var(--color-text-muted)">Đang tải...</div>

    <!-- ===== Empty state ===== -->
    <div x-show="!loading && filteredYears.length === 0 && searchQuery === '' && filterBranchUuid === ''"
         class="card" style="padding:48px;text-align:center">
        <div style="font-size:48px;margin-bottom:12px">📅</div>
        <div style="font-weight:600;font-size:15px;margin-bottom:6px">Chưa có năm học nào</div>
        <div style="color:var(--color-text-muted);font-size:13px;margin-bottom:20px">
            Tạo năm học đầu tiên để bắt đầu quản lý.<br>
            Năm học dùng làm cơ sở cho việc xếp lớp, lập thời khóa biểu và ghi nhận kết quả học tập.
        </div>
        <button class="btn btn--primary btn--sm" @click="openCreate()" x-show="canWrite">+ Tạo năm học đầu tiên</button>
    </div>

    <!-- ===== Empty search result ===== -->
    <div x-show="!loading && filteredYears.length === 0 && (searchQuery !== '' || filterBranchUuid !== '')"
         class="card" style="padding:32px;text-align:center;color:var(--color-text-muted)">
        Không tìm thấy năm học phù hợp.
    </div>

    <!-- ===== Table ===== -->
    <div x-show="!loading && filteredYears.length > 0" class="card">
        <div style="overflow-x:auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tên năm học</th>
                        <th class="hide-mobile">Chi nhánh</th>
                        <th>Ngày bắt đầu</th>
                        <th>Ngày kết thúc</th>
                        <th class="hide-mobile">Thời gian</th>
                        <th style="width:60px"></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="y in filteredYears" :key="y.uuid">
                        <tr>
                            <td>
                                <div style="display:flex;align-items:center;gap:8px">
                                    <span style="font-size:18px">📅</span>
                                    <strong x-text="y.name"></strong>
                                </div>
                            </td>
                            <td class="hide-mobile" style="font-size:13px">
                                <span class="badge badge--secondary" x-show="y.branch_name" x-text="y.branch_name"></span>
                                <span x-show="!y.branch_name" style="color:var(--color-text-muted)">—</span>
                            </td>
                            <td style="font-size:13px" x-text="formatDate(y.start_date)"></td>
                            <td style="font-size:13px" x-text="formatDate(y.end_date)"></td>
                            <td class="hide-mobile" style="font-size:12px;color:var(--color-text-muted)" x-text="durationText(y)"></td>
                            <td>
                                <div class="role-actions" :class="openDropdown === y.uuid ? 'is-open' : ''"
                                     @click.stop="toggleDropdown(y.uuid)">
                                    <button class="role-actions__btn" :aria-label="'Thao tác ' + y.name">⋮</button>
                                    <div class="role-actions__menu">
                                        <button class="role-actions__item"
                                                @click="openEdit(y); closeAllDropdowns()"
                                                x-show="canEdit"
                                                :class="!canEdit && 'role-actions__item--disabled'">
                                            <span class="role-actions__item-icon">✏</span> Sửa
                                        </button>
                                        <div class="role-actions__divider"></div>
                                        <button class="role-actions__item role-actions__item--danger"
                                                @click="confirmDelete(y); closeAllDropdowns()"
                                                x-show="canDelete"
                                                :class="!canDelete && 'role-actions__item--disabled'">
                                            <span class="role-actions__item-icon">🗑</span> Xóa
                                        </button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ===== MODAL: Tạo / Sửa ===== -->
    <div x-show="showModal" x-cloak class="modal-overlay" @click.self="showModal = false">
        <div class="modal-box" style="max-width:540px">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
                <h3 style="margin:0" x-text="editingUuid ? 'Cập nhật năm học' : 'Tạo năm học mới'"></h3>
                <button class="btn btn--ghost btn--sm" @click="showModal = false">✕</button>
            </div>

            <div style="display:flex;flex-direction:column;gap:14px">
                <div>
                    <label class="form-label">Tên năm học <span style="color:var(--color-danger)">*</span></label>
                    <input class="form-input" x-model="form.name" placeholder="VD: Năm học 2024-2025, Học kỳ 1">
                </div>

                <div>
                    <label class="form-label">Chi nhánh <span style="color:var(--color-danger)">*</span></label>
                    <select class="form-input" x-model="form.branch_uuid">
                        <option value="">— Chọn chi nhánh —</option>
                        <template x-for="b in branches" :key="b.uuid">
                            <option :value="b.uuid" x-text="b.name"></option>
                        </template>
                    </select>
                    <div x-show="branches.length === 0" style="font-size:12px;color:var(--color-text-muted);margin-top:6px">
                        Chưa có chi nhánh nào. Vui lòng tạo chi nhánh trước.
                    </div>
                </div>

                <div class="form-row">
                    <div>
                        <label class="form-label">Ngày bắt đầu <span style="color:var(--color-danger)">*</span></label>
                        <input type="date" class="form-input" x-model="form.start_date">
                    </div>
                    <div>
                        <label class="form-label">Ngày kết thúc <span style="color:var(--color-danger)">*</span></label>
                        <input type="date" class="form-input" x-model="form.end_date">
                    </div>
                </div>

                <div x-show="dateError" style="font-size:12px;color:var(--color-danger)">
                    <span x-text="dateError"></span>
                </div>
            </div>

            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:20px">
                <button class="btn btn--secondary btn--sm" @click="showModal = false">Hủy</button>
                <button class="btn btn--primary btn--sm" :disabled="saving" @click="save()">
                    <span x-text="saving ? 'Đang lưu...' : (editingUuid ? 'Cập nhật' : 'Tạo năm học')"></span>
                </button>
            </div>
        </div>
    </div>

</div>

<?= $this->section('scripts') ?>
<?php
    $smJsFile = __DIR__ . '/../../../../public/assets/modules/SchoolManagement/school-management.js';
    $smJsVer  = is_file($smJsFile) ? filemtime($smJsFile) : time();
?>
<script src="/assets/modules/SchoolManagement/school-management.js?v=<?= $smJsVer ?>"></script>
<?= $this->endSection() ?>

<?= $this->endSection() ?>
