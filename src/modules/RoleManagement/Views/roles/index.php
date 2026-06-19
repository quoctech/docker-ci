<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Quản lý vai trò<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>Phân quyền / Vai trò<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div x-data="roleManager()" x-init="load()"
     x-on:keydown.escape.window="showModal = false; showModulesModal = false; showApplyModal = false">

    <div class="page-header">
        <div>
            <h1 class="content__title">Quản lý vai trò</h1>
            <p class="content__subtitle">Tạo vai trò, phân quyền module và áp dụng cho người dùng.</p>
        </div>
        <button class="btn btn--primary btn--sm" @click="openCreate()">+ Tạo vai trò</button>
    </div>

    <!-- Loading -->
    <div x-show="loading" style="text-align:center;padding:48px;color:var(--color-text-muted)">Đang tải...</div>

    <!-- Empty state -->
    <div x-show="!loading && roles.length === 0" class="card" style="padding:48px;text-align:center">
        <div style="font-size:40px;margin-bottom:12px">🎭</div>
        <div style="font-weight:600;font-size:15px;margin-bottom:6px">Chưa có vai trò nào</div>
        <div style="color:var(--color-text-muted);font-size:13px;margin-bottom:20px">Tạo vai trò để phân quyền module và áp dụng nhanh cho người dùng.</div>
        <button class="btn btn--primary btn--sm" @click="openCreate()">+ Tạo vai trò đầu tiên</button>
    </div>

    <!-- Roles table -->
    <div x-show="!loading && roles.length > 0" class="card">
        <div style="overflow-x:auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tên vai trò</th>
                        <th>Mô tả</th>
                        <th style="text-align:center;width:100px">Module</th>
                        <th style="width:180px"></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="r in roles" :key="r.uuid">
                        <tr>
                            <td>
                                <strong x-text="r.name"></strong>
                                <div style="font-size:11px;color:var(--color-text-muted)" x-text="r.slug"></div>
                            </td>
                            <td style="color:var(--color-text-muted);font-size:13px" x-text="r.description || '—'"></td>
                            <td style="text-align:center">
                                <span class="badge badge--info" x-text="r.module_count + ' module'"></span>
                            </td>
                            <td>
                                <div style="display:flex;gap:4px;flex-wrap:nowrap">
                                    <button class="btn btn--ghost btn--sm" @click="openEdit(r)" title="Sửa">✏</button>
                                    <button class="btn btn--ghost btn--sm" @click="openModulesModal(r)" title="Phân quyền module">🔐</button>
                                    <button class="btn btn--ghost btn--sm" @click="openApplyModal(r)" title="Áp dụng cho người dùng">👤</button>
                                    <button class="btn btn--ghost btn--sm" @click="confirmDelete(r)" title="Xóa">✕</button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ===== MODAL: Tạo / Sửa vai trò ===== -->
    <div x-show="showModal" x-cloak class="modal-overlay" @click.self="showModal = false">
        <div class="modal-box" style="max-width:460px">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
                <h3 style="margin:0" x-text="editingId ? 'Cập nhật vai trò' : 'Tạo vai trò mới'"></h3>
                <button class="btn btn--ghost btn--sm" @click="showModal = false">✕</button>
            </div>
            <div style="display:flex;flex-direction:column;gap:14px">
                <div>
                    <label class="form-label">Tên vai trò <span style="color:var(--color-danger)">*</span></label>
                    <input class="form-input" x-model="form.name" placeholder="VD: Giáo viên Toán, Trưởng phòng">
                </div>
                <div>
                    <label class="form-label">Mô tả</label>
                    <input class="form-input" x-model="form.description" placeholder="Mô tả ngắn về vai trò này">
                </div>
            </div>
            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:20px">
                <button class="btn btn--secondary btn--sm" @click="showModal = false">Hủy</button>
                <button class="btn btn--primary btn--sm" :disabled="submitting" @click="submitForm()">
                    <span x-text="submitting ? 'Đang lưu...' : (editingId ? 'Cập nhật' : 'Tạo vai trò')"></span>
                </button>
            </div>
        </div>
    </div>

    <!-- ===== MODAL: Phân quyền module cho vai trò ===== -->
    <div x-show="showModulesModal" x-cloak class="modal-overlay" @click.self="showModulesModal = false">
        <div class="modal-box" style="max-width:620px;max-height:85vh;overflow-y:auto">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
                <div>
                    <h3 style="margin:0">Phân quyền module</h3>
                    <div style="font-size:12px;color:var(--color-text-muted);margin-top:2px">
                        Vai trò: <strong x-text="currentRole?.name"></strong>
                    </div>
                </div>
                <button class="btn btn--ghost btn--sm" @click="showModulesModal = false">✕</button>
            </div>

            <div x-show="loadingModules" style="text-align:center;padding:24px;color:var(--color-text-muted)">Đang tải...</div>

            <div x-show="!loadingModules && modulesList.length > 0" style="overflow-x:auto">
                <table class="table" style="font-size:13px">
                    <thead>
                        <tr>
                            <th>Module</th>
                            <th style="text-align:center;width:52px">Đọc</th>
                            <th style="text-align:center;width:52px">Ghi</th>
                            <th style="text-align:center;width:52px">Sửa</th>
                            <th style="text-align:center;width:52px">Xóa</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="m in modulesList" :key="m.slug">
                            <tr :style="!m.can_read ? 'opacity:0.5' : ''">
                                <td>
                                    <div style="font-weight:500" x-text="m.name"></div>
                                    <div style="font-size:11px;color:var(--color-text-muted);display:flex;gap:6px;align-items:center">
                                        <span x-text="m.slug"></span>
                                        <span x-show="!m.enabled" class="badge badge--secondary" style="font-size:10px">Tắt</span>
                                    </div>
                                </td>
                                <td style="text-align:center">
                                    <input type="checkbox" x-model="m.can_read" @change="onReadChange(m)" style="width:16px;height:16px;cursor:pointer">
                                </td>
                                <td style="text-align:center">
                                    <input type="checkbox" x-model="m.can_write" :disabled="!m.can_read" style="width:16px;height:16px;cursor:pointer">
                                </td>
                                <td style="text-align:center">
                                    <input type="checkbox" x-model="m.can_edit" :disabled="!m.can_read" style="width:16px;height:16px;cursor:pointer">
                                </td>
                                <td style="text-align:center">
                                    <input type="checkbox" x-model="m.can_delete" :disabled="!m.can_read" style="width:16px;height:16px;cursor:pointer">
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px">
                <button class="btn btn--secondary btn--sm" @click="showModulesModal = false">Hủy</button>
                <button class="btn btn--primary btn--sm" :disabled="savingModules" @click="saveModules()">
                    <span x-text="savingModules ? 'Đang lưu...' : 'Lưu phân quyền'"></span>
                </button>
            </div>
        </div>
    </div>

    <!-- ===== MODAL: Áp dụng vai trò cho người dùng ===== -->
    <div x-show="showApplyModal" x-cloak class="modal-overlay" @click.self="showApplyModal = false">
        <div class="modal-box" style="max-width:500px">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
                <div>
                    <h3 style="margin:0">Áp dụng vai trò</h3>
                    <div style="font-size:12px;color:var(--color-text-muted);margin-top:2px">
                        Vai trò: <strong x-text="applyRole?.name"></strong>
                    </div>
                </div>
                <button class="btn btn--ghost btn--sm" @click="showApplyModal = false">✕</button>
            </div>

            <p style="font-size:13px;color:var(--color-text-muted);margin-bottom:12px">
                Tìm kiếm người dùng để áp dụng vai trò này. Phân quyền module hiện tại của người dùng sẽ bị ghi đè.
            </p>

            <div style="position:relative">
                <input class="form-input" x-model="userSearch"
                       @input.debounce.400ms="searchUsers()"
                       placeholder="Nhập tên, email hoặc username...">
            </div>

            <div x-show="searchingUsers" style="text-align:center;padding:16px;color:var(--color-text-muted);font-size:13px">
                Đang tìm kiếm...
            </div>

            <div x-show="!searchingUsers && userResults.length > 0" style="margin-top:8px;border:1px solid var(--color-border);border-radius:8px;overflow:hidden">
                <template x-for="u in userResults" :key="u.uuid">
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 14px;border-bottom:1px solid var(--color-border);cursor:pointer"
                         @mouseover="$el.style.background='var(--color-surface-hover)'"
                         @mouseleave="$el.style.background=''"
                         @click="applyRoleToUser(u)">
                        <div>
                            <div style="font-weight:500;font-size:13px" x-text="u.full_name"></div>
                            <div style="font-size:11px;color:var(--color-text-muted)" x-text="u.email + (u.username ? ' · @' + u.username : '')"></div>
                        </div>
                        <span class="badge badge--warning" style="font-size:11px">Giáo viên</span>
                    </div>
                </template>
            </div>

            <div x-show="!searchingUsers && userSearch.length >= 2 && userResults.length === 0"
                 style="text-align:center;padding:16px;color:var(--color-text-muted);font-size:13px">
                Không tìm thấy người dùng nào.
            </div>
        </div>
    </div>

</div>

<?= $this->section('scripts') ?>
<script src="/assets/modules/RoleManagement/role-management.js"></script>
<?= $this->endSection() ?>

<?= $this->endSection() ?>
