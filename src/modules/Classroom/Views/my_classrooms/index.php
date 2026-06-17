<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Lớp học của tôi<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>Lớp học của tôi<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div x-data="myClassrooms()" x-init="load()">

    <div class="page-header">
        <div>
            <h1 class="content__title">Lớp học của tôi</h1>
            <p class="content__subtitle">Các lớp học bạn đã tham gia.</p>
        </div>
        <button class="btn btn--primary btn--sm" @click="showJoin = true">+ Nhập mã lớp</button>
    </div>

    <!-- Loading -->
    <div x-show="loading" style="text-align:center;padding:48px;color:var(--color-text-muted)">Đang tải...</div>

    <!-- Empty state -->
    <div x-show="!loading && classrooms.length === 0 && pending.length === 0" class="card" style="padding:48px;text-align:center">
        <div style="font-size:40px;margin-bottom:12px">📚</div>
        <div style="font-weight:600;font-size:15px;margin-bottom:6px">Bạn chưa tham gia lớp học nào</div>
        <div style="color:var(--color-text-muted);font-size:13px;margin-bottom:20px">Nhập mã lớp do giáo viên cung cấp để tham gia.</div>
        <button class="btn btn--primary btn--sm" @click="showJoin = true">Nhập mã lớp</button>
    </div>

    <!-- Pending requests -->
    <div x-show="pending.length > 0" class="card" style="margin-bottom:20px;border-left:3px solid var(--color-warning)">
        <div class="card__header">
            <h3>Đang chờ duyệt (<span x-text="pending.length"></span>)</h3>
        </div>
        <div class="card__body" style="padding:0">
            <table class="table table-card">
                <tbody>
                    <template x-for="m in pending" :key="m.classroom_id">
                        <tr>
                            <td data-label="Lớp học">
                                <div style="font-weight:500;font-size:13px" x-text="m.classroom_name"></div>
                                <div style="font-size:11px;color:var(--color-text-muted)" x-text="m.teacher_name"></div>
                            </td>
                            <td data-label="Môn học" class="hide-mobile" style="font-size:12px;color:var(--color-text-muted)" x-text="m.subject || '—'"></td>
                            <td data-label="Trạng thái">
                                <span class="badge badge--warning">⏳ Chờ giáo viên duyệt</span>
                            </td>
                            <td data-label="">
                                <button class="btn btn--ghost btn--sm" @click="cancelRequest(m)">Hủy yêu cầu</button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Approved classrooms -->
    <div x-show="!loading && classrooms.length > 0" class="grid grid--3">
        <template x-for="c in classrooms" :key="c.classroom_id">
            <div class="card classroom-card" style="cursor:pointer" @click="viewDetail(c)">
                <div class="card__body" style="padding:18px 20px">
                    <div style="font-weight:600;font-size:14px;margin-bottom:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" x-text="c.classroom_name"></div>
                    <div style="font-size:12px;color:var(--color-text-muted);margin-bottom:12px" x-text="c.teacher_name"></div>

                    <div style="display:flex;gap:12px;font-size:12px;color:var(--color-text-muted);margin-bottom:12px">
                        <span x-show="c.subject" x-text="c.subject"></span>
                        <span x-show="c.grade">Lớp <span x-text="c.grade"></span></span>
                        <span>📄 <span x-text="c.assignment_count"></span> bài tập</span>
                    </div>

                    <div style="display:flex;align-items:center;justify-content:space-between">
                        <span style="font-size:11px;color:var(--color-text-muted)">Tham gia: <span x-text="c.joined_at?.substring(0,10)"></span></span>
                        <span style="font-size:12px;color:var(--color-primary);font-weight:500">Vào lớp →</span>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <!-- ===== MODAL: Nhập mã lớp ===== -->
    <div x-show="showJoin" x-cloak class="modal-overlay" @click.self="showJoin = false">
        <div class="modal-box" style="max-width:420px">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
                <h3 style="margin:0">Tham gia lớp học</h3>
                <button class="btn btn--ghost btn--sm" @click="showJoin = false">✕</button>
            </div>

            <div>
                <label class="form-label">Mã lớp học <span style="color:var(--color-danger)">*</span></label>
                <input class="form-input"
                       x-model="joinCode"
                       placeholder="VD: HOA-XBCD7"
                       style="font-family:monospace;font-size:16px;letter-spacing:2px;text-transform:uppercase"
                       @keydown.enter.prevent="submitJoin()"
                       @input="joinCode = $event.target.value.toUpperCase()">
                <div style="font-size:12px;color:var(--color-text-muted);margin-top:6px">
                    Nhập mã lớp do giáo viên cung cấp.
                </div>
            </div>

            <!-- Result message -->
            <div x-show="joinMessage" style="margin-top:12px;padding:10px 14px;border-radius:6px;font-size:13px"
                 :class="joinSuccess ? 'alert-success' : 'alert-error'"
                 x-text="joinMessage"></div>

            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:20px">
                <button class="btn btn--secondary btn--sm" @click="showJoin = false">Đóng</button>
                <button class="btn btn--primary btn--sm" :disabled="joining || !joinCode.trim()" @click="submitJoin()">
                    <span x-text="joining ? 'Đang tham gia...' : 'Tham gia'"></span>
                </button>
            </div>
        </div>
    </div>

</div>

<style>
.classroom-card { transition: box-shadow var(--transition); }
.classroom-card:hover { box-shadow: var(--shadow-md); }
.alert-success { background: color-mix(in srgb, var(--color-success) 10%, transparent); color: var(--color-success); border: 1px solid color-mix(in srgb, var(--color-success) 30%, transparent); }
.alert-error { background: color-mix(in srgb, var(--color-danger) 10%, transparent); color: var(--color-danger); border: 1px solid color-mix(in srgb, var(--color-danger) 30%, transparent); }
</style>

<script>
function myClassrooms() {
    return {
        classrooms: [],
        pending: [],
        loading: false,
        showJoin: false,
        joining: false,
        joinCode: '',
        joinMessage: '',
        joinSuccess: false,

        async load() {
            this.loading = true;
            const data = await apiGet('/api/my-classrooms');
            if (data?.status === 'success') {
                this.classrooms = (data.data || []).filter(c => c.status === 'approved');
                this.pending    = (data.data || []).filter(c => c.status === 'pending');
            }
            this.loading = false;
        },

        viewDetail(c) {
            window.location.href = '/admin/my-classrooms/' + c.classroom_uuid;
        },

        async submitJoin() {
            if (!this.joinCode.trim()) return;
            this.joining = true;
            this.joinMessage = '';
            const body = new URLSearchParams({ code: this.joinCode.trim() });
            const data = await apiRequest('/api/classrooms/join', {
                method: 'POST', body,
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
            });
            this.joining = false;
            if (data?.status === 'success') {
                this.joinSuccess = true;
                this.joinMessage = data.message || 'Yêu cầu tham gia đã được gửi!';
                this.joinCode = '';
                await this.load();
            } else {
                this.joinSuccess = false;
                this.joinMessage = data?.message || 'Có lỗi xảy ra.';
            }
        },

        async cancelRequest(m) {
            const ok = await showConfirm({ title: 'Hủy yêu cầu', message: 'Hủy yêu cầu tham gia lớp "' + m.classroom_name + '"?', type: 'warning', confirmText: 'Hủy yêu cầu' });
            if (!ok) return;
            const data = await apiRequest('/api/my-classrooms/' + m.classroom_uuid + '/leave', { method: 'DELETE' });
            if (data?.status === 'success') {
                this.pending = this.pending.filter(x => x.classroom_id !== m.classroom_id);
                showToast('success', 'Đã hủy yêu cầu tham gia.');
            }
        },
    };
}
</script>

<?= $this->endSection() ?>
