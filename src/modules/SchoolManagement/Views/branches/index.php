<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Quản lý chi nhánh<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>Tổ chức / Chi nhánh<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div x-data="branchManager()" x-init="load()">

    <div class="page-header">
        <div>
            <h1 class="content__title">Chi nhánh</h1>
            <p class="content__subtitle">Quản lý các chi nhánh của trường.</p>
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

    <!-- Branch grid -->
    <div x-show="!loading && branches.length > 0" class="grid grid--3">
        <template x-for="b in branches" :key="b.uuid">
            <div class="card">
                <div class="card__body" style="padding:18px 20px">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px">
                        <div style="flex:1;min-width:0">
                            <div style="font-weight:600;font-size:14px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" x-text="b.name"></div>
                            <div style="font-size:12px;color:var(--color-text-muted);margin-top:2px" x-show="b.address" x-text="b.address"></div>
                        </div>
                        <div style="display:flex;gap:4px;flex-shrink:0;margin-left:8px">
                            <button class="btn btn--ghost btn--sm" @click="viewDetail(b)" title="Chi tiết">→</button>
                            <button class="btn btn--ghost btn--sm" @click="openEdit(b)" title="Sửa">✏</button>
                            <button class="btn btn--ghost btn--sm" @click="confirmDelete(b)" title="Xóa">✕</button>
                        </div>
                    </div>

                    <div style="display:flex;gap:16px;font-size:12px;color:var(--color-text-muted);margin-top:8px">
                        <span>🚪 <span x-text="b.room_count"></span> phòng</span>
                        <span x-show="b.phone">📞 <span x-text="b.phone"></span></span>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <!-- ===== MODAL: Tạo / Sửa chi nhánh ===== -->
    <div x-show="showModal" x-cloak class="modal-overlay" @click.self="showModal = false">
        <div class="modal-box" style="max-width:500px">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
                <h3 style="margin:0" x-text="editingId ? 'Cập nhật chi nhánh' : 'Thêm chi nhánh mới'"></h3>
                <button class="btn btn--ghost btn--sm" @click="showModal = false">✕</button>
            </div>

            <div style="display:flex;flex-direction:column;gap:14px">
                <div>
                    <label class="form-label">Tên chi nhánh <span style="color:var(--color-danger)">*</span></label>
                    <input class="form-input" x-model="form.name" placeholder="VD: Cơ sở Hà Nội">
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
