<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Quản lý phòng học<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>Tổ chức / Phòng học<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div x-data="roomManager()" x-init="load()">

    <div class="page-header">
        <div>
            <h1 class="content__title">Phòng học</h1>
            <p class="content__subtitle">Quản lý phòng học trong tất cả các chi nhánh.</p>
        </div>
        <button class="btn btn--primary btn--sm" @click="openCreate()">+ Thêm phòng</button>
    </div>

    <!-- Filter theo chi nhánh -->
    <div class="card" style="margin-bottom:16px;padding:12px 16px" x-show="branches.length > 0">
        <div style="display:flex;align-items:center;gap:12px;font-size:13px">
            <span style="color:var(--color-text-muted);white-space:nowrap">Lọc chi nhánh:</span>
            <select class="form-input" style="max-width:220px" x-model="filterBranch">
                <option value="">— Tất cả chi nhánh —</option>
                <template x-for="b in branches" :key="b.uuid">
                    <option :value="b.uuid" x-text="b.name"></option>
                </template>
            </select>
            <span style="color:var(--color-text-muted)" x-text="filteredRooms.length + ' phòng'"></span>
        </div>
    </div>

    <!-- Loading -->
    <div x-show="loading" style="text-align:center;padding:48px;color:var(--color-text-muted)">Đang tải...</div>

    <!-- Empty state -->
    <div x-show="!loading && filteredRooms.length === 0" class="card" style="padding:48px;text-align:center">
        <div style="font-size:40px;margin-bottom:12px">🚪</div>
        <div style="font-weight:600;font-size:15px;margin-bottom:6px" x-text="filterBranch ? 'Chi nhánh này chưa có phòng nào' : 'Chưa có phòng nào'"></div>
        <button class="btn btn--primary btn--sm" @click="openCreate()">+ Thêm phòng</button>
    </div>

    <!-- Room table -->
    <div x-show="!loading && filteredRooms.length > 0" class="card">
        <div style="overflow-x:auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tên phòng</th>
                        <th>Chi nhánh</th>
                        <th>Loại phòng</th>
                        <th>Sức chứa</th>
                        <th style="width:100px"></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="r in filteredRooms" :key="r.uuid">
                        <tr>
                            <td><strong x-text="r.name"></strong></td>
                            <td x-text="r.branch_name || '—'"></td>
                            <td x-text="r.room_type || '—'"></td>
                            <td x-text="r.capacity ? r.capacity + ' chỗ' : '—'"></td>
                            <td style="white-space:nowrap">
                                <button class="btn btn--ghost btn--sm" @click="openEdit(r)">✏</button>
                                <button class="btn btn--ghost btn--sm" @click="confirmDelete(r)">✕</button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ===== MODAL: Tạo / Sửa phòng ===== -->
    <div x-show="showModal" x-cloak class="modal-overlay" @click.self="showModal = false">
        <div class="modal-box" style="max-width:480px">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
                <h3 style="margin:0" x-text="editingId ? 'Cập nhật phòng' : 'Thêm phòng mới'"></h3>
                <button class="btn btn--ghost btn--sm" @click="showModal = false">✕</button>
            </div>
            <div style="display:flex;flex-direction:column;gap:14px">
                <div>
                    <label class="form-label">Chi nhánh <span style="color:var(--color-danger)">*</span></label>
                    <select class="form-input" x-model="form.branch_uuid">
                        <option value="">— Chọn chi nhánh —</option>
                        <template x-for="b in branches" :key="b.uuid">
                            <option :value="b.uuid" x-text="b.name"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="form-label">Tên phòng <span style="color:var(--color-danger)">*</span></label>
                    <input class="form-input" x-model="form.name" placeholder="VD: Phòng A101">
                </div>
                <div class="form-row">
                    <div style="flex:1">
                        <label class="form-label">Loại phòng</label>
                        <select class="form-input" x-model="form.room_type">
                            <option value="">— Chọn loại —</option>
                            <option value="Lý thuyết">Lý thuyết</option>
                            <option value="Thực hành">Thực hành</option>
                            <option value="Phòng họp">Phòng họp</option>
                            <option value="Khác">Khác</option>
                        </select>
                    </div>
                    <div style="width:110px">
                        <label class="form-label">Sức chứa</label>
                        <input class="form-input" type="number" min="1" x-model="form.capacity" placeholder="30">
                    </div>
                </div>
            </div>
            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:20px">
                <button class="btn btn--secondary btn--sm" @click="showModal = false">Hủy</button>
                <button class="btn btn--primary btn--sm" :disabled="submitting" @click="submitForm()">
                    <span x-text="submitting ? 'Đang lưu...' : (editingId ? 'Cập nhật' : 'Thêm phòng')"></span>
                </button>
            </div>
        </div>
    </div>

</div>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="/assets/modules/SchoolManagement/school-management.css">
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="/assets/modules/SchoolManagement/school-management.js"></script>
<?= $this->endSection() ?>

<?= $this->endSection() ?>
