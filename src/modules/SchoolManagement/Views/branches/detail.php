<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Chi tiết chi nhánh<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>Tổ chức / <a href="/admin/school-management/branches">Chi nhánh</a> / Chi tiết<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div x-data="branchDetail('<?= esc($branchUuid) ?>')" x-init="init()"
     x-on:keydown.escape.window="showEditBranch = false; showRoomModal = false">

    <!-- Loading -->
    <div x-show="loading" style="text-align:center;padding:48px;color:var(--color-text-muted)">Đang tải...</div>

    <template x-if="!loading && branch">
        <div>
            <!-- Header -->
            <div class="page-header">
                <div>
                    <h1 class="content__title" x-text="branch.name"></h1>
                    <p class="content__subtitle" x-show="branch.center_name">
                        Thuộc trung tâm: <strong x-text="branch.center_name"></strong>
                    </p>
                </div>
                <div style="display:flex;gap:8px">
                    <button class="btn btn--secondary btn--sm" @click="openEditBranch()">✏ Sửa chi nhánh</button>
                    <button class="btn btn--primary btn--sm" @click="openCreateRoom()">+ Thêm phòng</button>
                </div>
            </div>

            <!-- Info cards -->
            <div class="grid grid--4" style="margin-bottom:20px">
                <div class="card">
                    <div class="card__body" style="padding:14px 18px">
                        <div style="font-size:11px;color:var(--color-text-muted);margin-bottom:4px">PHÒNG HỌC</div>
                        <div style="font-size:22px;font-weight:700" x-text="rooms.length"></div>
                    </div>
                </div>
                <div class="card" x-show="branch.manager">
                    <div class="card__body" style="padding:14px 18px">
                        <div style="font-size:11px;color:var(--color-text-muted);margin-bottom:4px">PHỤ TRÁCH</div>
                        <div style="font-size:13px;font-weight:600" x-text="branch.manager"></div>
                    </div>
                </div>
                <div class="card" x-show="branch.phone">
                    <div class="card__body" style="padding:14px 18px">
                        <div style="font-size:11px;color:var(--color-text-muted);margin-bottom:4px">ĐIỆN THOẠI</div>
                        <div style="font-size:13px;font-weight:500" x-text="branch.phone"></div>
                    </div>
                </div>
                <div class="card" x-show="branch.email">
                    <div class="card__body" style="padding:14px 18px">
                        <div style="font-size:11px;color:var(--color-text-muted);margin-bottom:4px">EMAIL</div>
                        <div style="font-size:13px;font-weight:500;word-break:break-all" x-text="branch.email"></div>
                    </div>
                </div>
            </div>

            <!-- Address -->
            <div x-show="branch.address" class="card" style="margin-bottom:20px">
                <div class="card__body" style="padding:12px 18px;font-size:13px;color:var(--color-text-muted)">
                    📍 <span x-text="branch.address"></span>
                </div>
            </div>

            <!-- Rooms list -->
            <h2 style="font-size:15px;font-weight:600;margin-bottom:12px">Danh sách phòng học</h2>

            <div x-show="rooms.length === 0" class="card" style="padding:32px;text-align:center;color:var(--color-text-muted)">
                Chưa có phòng nào. <button class="btn btn--ghost btn--sm" @click="openCreateRoom()">Thêm phòng ngay</button>
            </div>

            <div x-show="rooms.length > 0" class="card">
                <div style="overflow-x:auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Tên phòng</th>
                                <th>Loại phòng</th>
                                <th>Sức chứa</th>
                                <th style="width:100px"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="r in rooms" :key="r.uuid">
                                <tr>
                                    <td><strong x-text="r.name"></strong></td>
                                    <td x-text="r.room_type || '—'"></td>
                                    <td x-text="r.capacity ? r.capacity + ' chỗ' : '—'"></td>
                                    <td style="white-space:nowrap">
                                        <button class="btn btn--ghost btn--sm" @click="openEditRoom(r)">✏</button>
                                        <button class="btn btn--ghost btn--sm" @click="confirmDeleteRoom(r)">✕</button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </template>

    <!-- ===== MODAL: Sửa chi nhánh ===== -->
    <div x-show="showEditBranch" x-cloak class="modal-overlay" @click.self="showEditBranch = false">
        <div class="modal-box" style="max-width:540px">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
                <h3 style="margin:0">Cập nhật chi nhánh</h3>
                <button class="btn btn--ghost btn--sm" @click="showEditBranch = false">✕</button>
            </div>
            <div style="display:flex;flex-direction:column;gap:14px">
                <div>
                    <label class="form-label">Tên chi nhánh <span style="color:var(--color-danger)">*</span></label>
                    <input class="form-input" x-model="branchForm.name">
                </div>
                <div>
                    <label class="form-label">Địa chỉ <span style="color:var(--color-danger)">*</span></label>
                    <input class="form-input" x-model="branchForm.address">
                </div>
                <div>
                    <label class="form-label">Người phụ trách <span style="color:var(--color-danger)">*</span></label>
                    <input class="form-input" x-model="branchForm.manager">
                </div>
                <div class="form-row">
                    <div style="flex:1">
                        <label class="form-label">Điện thoại <span style="color:var(--color-danger)">*</span></label>
                        <input class="form-input" x-model="branchForm.phone">
                    </div>
                    <div style="flex:1">
                        <label class="form-label">Email <span style="color:var(--color-danger)">*</span></label>
                        <input class="form-input" type="email" x-model="branchForm.email">
                    </div>
                </div>
            </div>
            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:20px">
                <button class="btn btn--secondary btn--sm" @click="showEditBranch = false">Hủy</button>
                <button class="btn btn--primary btn--sm" :disabled="submittingBranch" @click="submitBranch()">
                    <span x-text="submittingBranch ? 'Đang lưu...' : 'Cập nhật'"></span>
                </button>
            </div>
        </div>
    </div>

    <!-- ===== MODAL: Tạo / Sửa phòng ===== -->
    <div x-show="showRoomModal" x-cloak class="modal-overlay" @click.self="showRoomModal = false">
        <div class="modal-box" style="max-width:460px">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
                <h3 style="margin:0" x-text="editingRoomId ? 'Cập nhật phòng' : 'Thêm phòng mới'"></h3>
                <button class="btn btn--ghost btn--sm" @click="showRoomModal = false">✕</button>
            </div>
            <div style="display:flex;flex-direction:column;gap:14px">
                <div>
                    <label class="form-label">Tên phòng <span style="color:var(--color-danger)">*</span></label>
                    <input class="form-input" x-model="roomForm.name" placeholder="VD: Phòng A101">
                </div>
                <div class="form-row">
                    <div style="flex:1">
                        <label class="form-label">Loại phòng</label>
                        <select class="form-input" x-model="roomForm.room_type">
                            <option value="">— Chọn loại —</option>
                            <option value="Lý thuyết">Lý thuyết</option>
                            <option value="Thực hành">Thực hành</option>
                            <option value="Phòng họp">Phòng họp</option>
                            <option value="Khác">Khác</option>
                        </select>
                    </div>
                    <div style="width:110px">
                        <label class="form-label">Sức chứa</label>
                        <input class="form-input" type="number" min="1" x-model="roomForm.capacity" placeholder="30">
                    </div>
                </div>
            </div>
            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:20px">
                <button class="btn btn--secondary btn--sm" @click="showRoomModal = false">Hủy</button>
                <button class="btn btn--primary btn--sm" :disabled="submittingRoom" @click="submitRoom()">
                    <span x-text="submittingRoom ? 'Đang lưu...' : (editingRoomId ? 'Cập nhật' : 'Thêm phòng')"></span>
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
