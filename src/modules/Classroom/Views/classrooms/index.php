<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Quản lý lớp học<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>Lớp học / Quản lý<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div x-data="classroomManager()" x-init="load()">

    <div class="page-header">
        <div>
            <h1 class="content__title">Lớp học</h1>
            <p class="content__subtitle">Tạo và quản lý lớp học, bài tập cho học sinh.</p>
        </div>
        <button class="btn btn--primary btn--sm" @click="openCreate()">+ Tạo lớp học</button>
    </div>

    <!-- Empty state -->
    <div x-show="!loading && classrooms.length === 0" class="card" style="padding:48px;text-align:center">
        <div style="font-size:40px;margin-bottom:12px">🏫</div>
        <div style="font-weight:600;font-size:15px;margin-bottom:6px">Chưa có lớp học nào</div>
        <div style="color:var(--color-text-muted);font-size:13px;margin-bottom:20px">Tạo lớp học đầu tiên để bắt đầu dạy học.</div>
        <button class="btn btn--primary btn--sm" @click="openCreate()">+ Tạo lớp học</button>
    </div>

    <!-- Classroom grid -->
    <div x-show="loading" style="text-align:center;padding:48px;color:var(--color-text-muted)">Đang tải...</div>

    <div x-show="!loading && classrooms.length > 0" class="grid grid--3">
        <template x-for="c in classrooms" :key="c.id">
            <div class="card classroom-card">
                <div class="card__body" style="padding:18px 20px">
                    <!-- Header -->
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px">
                        <div style="flex:1;min-width:0">
                            <div style="font-weight:600;font-size:14px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" x-text="c.name"></div>
                            <div style="font-size:12px;color:var(--color-text-muted);margin-top:2px" x-show="c.subject" x-text="c.subject"></div>
                        </div>
                        <div style="display:flex;gap:4px;flex-shrink:0;margin-left:8px">
                            <button class="btn btn--ghost btn--sm" @click="viewDetail(c)" title="Chi tiết">→</button>
                            <button class="btn btn--ghost btn--sm" @click="confirmDelete(c)" title="Xóa">✕</button>
                        </div>
                    </div>

                    <!-- Class code -->
                    <div style="display:flex;align-items:center;gap:8px;padding:8px 10px;background:var(--color-bg);border-radius:6px;margin-bottom:12px">
                        <span style="font-family:monospace;font-weight:700;font-size:15px;letter-spacing:2px;color:var(--color-primary)" x-text="c.code"></span>
                        <button class="btn btn--ghost btn--sm" style="margin-left:auto;font-size:11px"
                                @click="copyCode(c.code)" title="Copy mã lớp">📋 Copy</button>
                    </div>

                    <!-- Stats -->
                    <div style="display:flex;gap:16px;font-size:12px;color:var(--color-text-muted);margin-bottom:12px">
                        <span>👤 <span x-text="c.member_count"></span> học sinh</span>
                        <span x-show="c.pending_count > 0" style="color:var(--color-warning)">
                            ⏳ <span x-text="c.pending_count"></span> chờ duyệt
                        </span>
                        <span>📄 <span x-text="c.assignment_count"></span> bài tập</span>
                    </div>

                    <!-- Auto approve badge -->
                    <div style="display:flex;align-items:center;gap:6px">
                        <span style="font-size:11px;color:var(--color-text-muted)">Tự động duyệt:</span>
                        <span class="badge"
                              :class="c.auto_approve ? 'badge--success' : 'badge--secondary'"
                              style="font-size:11px"
                              x-text="c.auto_approve ? 'Bật' : 'Tắt (duyệt thủ công)'"></span>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <!-- ===== MODAL: Tạo lớp học ===== -->
    <div x-show="showCreate" x-cloak class="modal-overlay" @click.self="showCreate = false">
        <div class="modal-box" style="max-width:520px">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
                <h3 style="margin:0">Tạo lớp học mới</h3>
                <button class="btn btn--ghost btn--sm" @click="showCreate = false">✕</button>
            </div>

            <div style="display:flex;flex-direction:column;gap:14px">
                <div>
                    <label class="form-label">Tên lớp học <span style="color:var(--color-danger)">*</span></label>
                    <input class="form-input" x-model="form.name" placeholder="VD: Lớp Toán 8A — Học kỳ 1"
                           @keydown.enter.prevent="submitCreate()">
                </div>
                <div class="form-row">
                    <div style="flex:1">
                        <label class="form-label">Môn học</label>
                        <input class="form-input" x-model="form.subject" placeholder="Toán, Lý, Hóa...">
                    </div>
                    <div style="width:100px">
                        <label class="form-label">Khối lớp</label>
                        <select class="form-input" x-model="form.grade">
                            <option value="">—</option>
                            <template x-for="g in [1,2,3,4,5,6,7,8,9,10,11,12]" :key="g">
                                <option :value="g" x-text="'Lớp ' + g"></option>
                            </template>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="form-label">Mô tả</label>
                    <textarea class="form-input" x-model="form.description" rows="2"
                              placeholder="Mô tả ngắn về lớp học..."></textarea>
                </div>
                <div>
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                        <input type="checkbox" x-model="form.auto_approve" style="width:16px;height:16px">
                        <span style="font-size:13px">Tự động duyệt học sinh tham gia</span>
                    </label>
                    <div style="font-size:12px;color:var(--color-text-muted);margin-top:4px;margin-left:24px">
                        Tắt nếu muốn kiểm duyệt thủ công từng học sinh trước khi cho vào lớp.
                    </div>
                </div>
            </div>

            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:20px">
                <button class="btn btn--secondary btn--sm" @click="showCreate = false">Hủy</button>
                <button class="btn btn--primary btn--sm" :disabled="submitting" @click="submitCreate()">
                    <span x-text="submitting ? 'Đang tạo...' : 'Tạo lớp học'"></span>
                </button>
            </div>
        </div>
    </div>

</div>

<style>
.classroom-card { transition: box-shadow var(--transition); }
.classroom-card:hover { box-shadow: var(--shadow-md); }
</style>

<script>
function classroomManager() {
    return {
        classrooms: [],
        loading: false,
        showCreate: false,
        submitting: false,
        form: { name: '', subject: '', grade: '', description: '', auto_approve: true },

        async load() {
            this.loading = true;
            const data = await apiGet('/api/classrooms');
            if (data?.status === 'success') this.classrooms = data.data;
            this.loading = false;
        },

        openCreate() {
            this.form = { name: '', subject: '', grade: '', description: '', auto_approve: true };
            this.showCreate = true;
        },

        async submitCreate() {
            if (!this.form.name.trim()) { showToast('error', 'Vui lòng nhập tên lớp học.'); return; }
            this.submitting = true;
            const body = new URLSearchParams({
                name:         this.form.name,
                subject:      this.form.subject || '',
                grade:        this.form.grade || '',
                description:  this.form.description || '',
                auto_approve: this.form.auto_approve ? '1' : '0',
            });
            const data = await apiRequest('/api/classrooms', { method: 'POST', body, headers: { 'Content-Type': 'application/x-www-form-urlencoded' } });
            this.submitting = false;
            if (data?.status === 'success') {
                showToast('success', 'Tạo lớp học thành công!');
                this.showCreate = false;
                await this.load();
            } else {
                showToast('error', data?.message || 'Có lỗi xảy ra.');
            }
        },

        viewDetail(c) {
            window.location.href = '/admin/classrooms/' + c.uuid;
        },

        async copyCode(code) {
            await navigator.clipboard.writeText(code);
            showToast('success', 'Đã copy mã lớp: ' + code);
        },

        async confirmDelete(c) {
            const ok = await showConfirm({
                title: 'Xóa lớp học',
                message: 'Xóa lớp "' + c.name + '"? Toàn bộ bài tập và dữ liệu sẽ bị xóa.',
                type: 'danger',
                confirmText: 'Xóa'
            });
            if (!ok) return;
            const data = await apiRequest('/api/classrooms/' + c.uuid, { method: 'DELETE' });
            if (data?.status === 'success') {
                showToast('success', 'Đã xóa lớp học.');
                this.classrooms = this.classrooms.filter(x => x.uuid !== c.uuid);
            } else {
                showToast('error', data?.message || 'Có lỗi xảy ra.');
            }
        },
    };
}
</script>

<?= $this->endSection() ?>
