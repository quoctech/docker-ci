<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Quản lý chi nhánh<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>Tổ chức / Chi nhánh<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div x-data="branchManager()" x-init="load()"
     x-on:keydown.escape.window="showModal = false">

    <div class="page-header">
        <div>
            <h1 class="content__title">Chi nhánh</h1>
            <p class="content__subtitle">Quản lý các chi nhánh thuộc trung tâm.</p>
        </div>
        <button class="btn btn--primary btn--sm" @click="openCreate()">+ Thêm chi nhánh</button>
    </div>

    <!-- Loading -->
    <div x-show="loading" style="text-align:center;padding:48px;color:var(--color-text-muted)">Đang tải...</div>

    <!-- Empty state -->
    <div x-show="!loading && branches.length === 0" class="card" style="padding:48px;text-align:center">
        <div style="font-size:40px;margin-bottom:12px">🏢</div>
        <div style="font-weight:600;font-size:15px;margin-bottom:6px">Chưa có chi nhánh nào</div>
        <div style="color:var(--color-text-muted);font-size:13px;margin-bottom:20px">Thêm chi nhánh đầu tiên để bắt đầu quản lý.</div>
        <button class="btn btn--primary btn--sm" @click="openCreate()">+ Thêm chi nhánh</button>
    </div>

    <!-- Branch table -->
    <div x-show="!loading && branches.length > 0" class="card">
        <div style="overflow-x:auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tên chi nhánh</th>
                        <th class="hide-mobile">Trung tâm</th>
                        <th class="hide-mobile">Địa chỉ</th>
                        <th class="hide-tablet">Người phụ trách</th>
                        <th class="hide-mobile">Liên hệ</th>
                        <th style="text-align:center;width:80px">Phòng</th>
                        <th style="width:100px"></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="b in branches" :key="b.uuid">
                        <tr>
                            <td>
                                <strong x-text="b.name"></strong>
                            </td>
                            <td class="hide-mobile" style="font-size:13px">
                                <span x-show="b.center_name" class="badge badge--secondary" x-text="b.center_name"></span>
                                <span x-show="!b.center_name" style="color:var(--color-text-muted)">—</span>
                            </td>
                            <td class="hide-mobile" style="font-size:13px;color:var(--color-text-muted)" x-text="b.address || '—'"></td>
                            <td class="hide-tablet" style="font-size:13px" x-text="b.manager || '—'"></td>
                            <td class="hide-mobile" style="font-size:12px">
                                <div x-show="b.phone" x-text="b.phone"></div>
                                <div x-show="b.email" style="color:var(--color-text-muted)" x-text="b.email"></div>
                                <span x-show="!b.phone && !b.email" style="color:var(--color-text-muted)">—</span>
                            </td>
                            <td style="text-align:center">
                                <span class="badge badge--info" x-text="b.room_count + ' phòng'"></span>
                            </td>
                            <td>
                                <div style="display:flex;gap:4px">
                                    <button class="btn btn--ghost btn--sm" @click="viewDetail(b)" title="Chi tiết">→</button>
                                    <button class="btn btn--ghost btn--sm" @click="openEdit(b)" title="Sửa">✏</button>
                                    <button class="btn btn--ghost btn--sm" @click="confirmDelete(b)" title="Xóa">✕</button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ===== MODAL: Tạo / Sửa chi nhánh ===== -->
    <div x-show="showModal" x-cloak class="modal-overlay" @click.self="showModal = false">
        <div class="modal-box" style="max-width:540px">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
                <h3 style="margin:0" x-text="editingId ? 'Cập nhật chi nhánh' : 'Thêm chi nhánh mới'"></h3>
                <button class="btn btn--ghost btn--sm" @click="showModal = false">✕</button>
            </div>

            <div style="display:flex;flex-direction:column;gap:14px">
                <div>
                    <label class="form-label">Trung tâm</label>
                    <select class="form-input" x-model="form.center_uuid">
                        <option value="">— Không thuộc trung tâm nào —</option>
                        <template x-for="c in centers" :key="c.uuid">
                            <option :value="c.uuid" x-text="c.name"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="form-label">Tên chi nhánh <span style="color:var(--color-danger)">*</span></label>
                    <input class="form-input" x-model="form.name" placeholder="VD: Cơ sở Hà Nội">
                </div>
                <div>
                    <label class="form-label">Địa chỉ <span style="color:var(--color-danger)">*</span></label>
                    <input class="form-input" x-model="form.address" placeholder="Số nhà, đường, quận, thành phố">
                </div>
                <div>
                    <label class="form-label">Người phụ trách <span style="color:var(--color-danger)">*</span></label>
                    <input class="form-input" x-model="form.manager" placeholder="Họ tên người phụ trách chi nhánh">
                </div>
                <div class="form-row">
                    <div style="flex:1">
                        <label class="form-label">Số điện thoại <span style="color:var(--color-danger)">*</span></label>
                        <input class="form-input" x-model="form.phone" placeholder="0901234567">
                    </div>
                    <div style="flex:1">
                        <label class="form-label">Email <span style="color:var(--color-danger)">*</span></label>
                        <input class="form-input" type="email" x-model="form.email" placeholder="branch@example.com">
                    </div>
                </div>
            </div>

            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:20px">
                <button class="btn btn--secondary btn--sm" @click="showModal = false">Hủy</button>
                <button class="btn btn--primary btn--sm" :disabled="submitting" @click="submitForm()">
                    <span x-text="submitting ? 'Đang lưu...' : (editingId ? 'Cập nhật' : 'Thêm chi nhánh')"></span>
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
