<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Quản lý trung tâm<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>Tổ chức / Trung tâm<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div x-data="centerManager()" x-init="load()"
     x-on:keydown.escape.window="showModal = false">

    <div class="page-header">
        <div>
            <h1 class="content__title">Trung tâm</h1>
            <p class="content__subtitle">Quản lý các trung tâm — cấp cao nhất trong cấu trúc tổ chức.</p>
        </div>
        <button class="btn btn--primary btn--sm" @click="openCreate()">+ Thêm trung tâm</button>
    </div>

    <!-- Loading -->
    <div x-show="loading" style="text-align:center;padding:48px;color:var(--color-text-muted)">Đang tải...</div>

    <!-- Empty state -->
    <div x-show="!loading && centers.length === 0" class="card" style="padding:48px;text-align:center">
        <div style="font-size:40px;margin-bottom:12px">🏛</div>
        <div style="font-weight:600;font-size:15px;margin-bottom:6px">Chưa có trung tâm nào</div>
        <div style="color:var(--color-text-muted);font-size:13px;margin-bottom:20px">Thêm trung tâm để bắt đầu tổ chức cơ cấu hệ thống.<br>Luồng: Trung tâm → Chi nhánh → Phòng học</div>
        <button class="btn btn--primary btn--sm" @click="openCreate()">+ Thêm trung tâm</button>
    </div>

    <!-- Centers table -->
    <div x-show="!loading && centers.length > 0" class="card">
        <div style="overflow-x:auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tên trung tâm</th>
                        <th class="hide-mobile">Địa chỉ</th>
                        <th class="hide-mobile">Liên hệ</th>
                        <th style="text-align:center;width:80px">Chi nhánh</th>
                        <th style="width:90px"></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="c in centers" :key="c.uuid">
                        <tr>
                            <td>
                                <strong x-text="c.name"></strong>
                            </td>
                            <td class="hide-mobile" style="font-size:13px;color:var(--color-text-muted)" x-text="c.address || '—'"></td>
                            <td class="hide-mobile" style="font-size:12px">
                                <div x-show="c.phone" x-text="'📞 ' + c.phone"></div>
                                <div x-show="c.email" style="color:var(--color-text-muted)" x-text="c.email"></div>
                                <span x-show="!c.phone && !c.email" style="color:var(--color-text-muted)">—</span>
                            </td>
                            <td style="text-align:center">
                                <span class="badge badge--info" x-text="c.branch_count + ' chi nhánh'"></span>
                            </td>
                            <td>
                                <div style="display:flex;gap:4px">
                                    <button class="btn btn--ghost btn--sm" @click="openEdit(c)" title="Sửa">✏</button>
                                    <button class="btn btn--ghost btn--sm" @click="confirmDelete(c)" title="Xóa">✕</button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ===== MODAL: Tạo / Sửa trung tâm ===== -->
    <div x-show="showModal" x-cloak class="modal-overlay" @click.self="showModal = false">
        <div class="modal-box" style="max-width:500px">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
                <h3 style="margin:0" x-text="editingId ? 'Cập nhật trung tâm' : 'Thêm trung tâm mới'"></h3>
                <button class="btn btn--ghost btn--sm" @click="showModal = false">✕</button>
            </div>
            <div style="display:flex;flex-direction:column;gap:14px">
                <div>
                    <label class="form-label">Tên trung tâm <span style="color:var(--color-danger)">*</span></label>
                    <input class="form-input" x-model="form.name" placeholder="VD: Trung tâm Hà Nội">
                </div>
                <div>
                    <label class="form-label">Địa chỉ</label>
                    <input class="form-input" x-model="form.address" placeholder="Số nhà, đường, quận, thành phố">
                </div>
                <div class="form-row">
                    <div style="flex:1">
                        <label class="form-label">Số điện thoại</label>
                        <input class="form-input" x-model="form.phone" placeholder="0901234567">
                    </div>
                    <div style="flex:1">
                        <label class="form-label">Email</label>
                        <input class="form-input" type="email" x-model="form.email" placeholder="center@example.com">
                    </div>
                </div>
            </div>
            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:20px">
                <button class="btn btn--secondary btn--sm" @click="showModal = false">Hủy</button>
                <button class="btn btn--primary btn--sm" :disabled="submitting" @click="submitForm()">
                    <span x-text="submitting ? 'Đang lưu...' : (editingId ? 'Cập nhật' : 'Thêm trung tâm')"></span>
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
