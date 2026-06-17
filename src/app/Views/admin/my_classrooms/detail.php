<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Lớp học<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>Lớp học của tôi / Chi tiết<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div x-data="myClassroomDetail('<?= esc($classroomUuid, 'attr') ?>')" x-init="load()">

    <!-- Header -->
    <div class="page-header">
        <div>
            <a href="/admin/my-classrooms" style="font-size:12px;color:var(--color-text-muted);text-decoration:none">← Lớp học của tôi</a>
            <h1 class="content__title" x-text="classroom?.name || 'Đang tải...'"></h1>
            <div style="display:flex;gap:12px;font-size:12px;color:var(--color-text-muted);margin-top:4px">
                <span x-show="classroom?.teacher_name">👩‍🏫 <span x-text="classroom?.teacher_name"></span></span>
                <span x-show="classroom?.subject" x-text="classroom?.subject"></span>
                <span x-show="classroom?.grade">Lớp <span x-text="classroom?.grade"></span></span>
            </div>
        </div>
        <button class="btn btn--ghost btn--sm" @click="leaveClassroom()" style="color:var(--color-danger)">
            Rời lớp
        </button>
    </div>

    <!-- Loading -->
    <div x-show="loading" style="text-align:center;padding:48px;color:var(--color-text-muted)">Đang tải...</div>

    <!-- Empty state -->
    <div x-show="!loading && assignments.length === 0" class="card" style="padding:40px;text-align:center;color:var(--color-text-muted)">
        Giáo viên chưa đăng bài tập nào.
    </div>

    <!-- Assignments list -->
    <div x-show="!loading && assignments.length > 0">
        <template x-for="a in assignments" :key="a.id">
            <div class="card assignment-item" style="margin-bottom:12px">
                <div class="card__body" style="padding:18px 20px">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap">
                        <!-- Assignment info -->
                        <div style="flex:1;min-width:200px">
                            <div style="font-weight:600;font-size:14px;margin-bottom:4px" x-text="a.title"></div>
                            <div x-show="a.description" style="font-size:13px;color:var(--color-text-muted);line-height:1.5;white-space:pre-wrap;margin-bottom:8px"
                                 x-text="a.description"></div>
                            <div style="display:flex;gap:12px;font-size:12px;color:var(--color-text-muted);flex-wrap:wrap">
                                <span x-show="a.due_date">🗓 Hạn nộp: <span x-text="a.due_date?.substring(0,10)"></span></span>
                                <span>📊 Điểm tối đa: <span x-text="a.max_score"></span></span>
                            </div>
                        </div>

                        <!-- Submission status -->
                        <div style="flex-shrink:0;text-align:right">
                            <div x-show="!a.my_submission">
                                <div style="font-size:12px;color:var(--color-text-muted);margin-bottom:8px">Chưa nộp bài</div>
                                <button class="btn btn--primary btn--sm" @click="openSubmit(a)">📤 Nộp bài</button>
                            </div>
                            <div x-show="a.my_submission">
                                <div x-show="a.my_submission?.status === 'submitted'" style="font-size:12px;color:var(--color-warning)">⏳ Chờ chấm điểm</div>
                                <div x-show="a.my_submission?.status === 'graded'">
                                    <div style="font-size:20px;font-weight:700;color:var(--color-success)" x-text="a.my_submission?.score + '/' + a.max_score"></div>
                                    <div style="font-size:11px;color:var(--color-text-muted)">Đã chấm điểm</div>
                                </div>
                                <button class="btn btn--ghost btn--sm" style="margin-top:6px;font-size:11px" @click="viewSubmission(a)">Xem bài nộp</button>
                            </div>
                        </div>
                    </div>

                    <!-- Feedback (if graded) -->
                    <div x-show="a.my_submission?.status === 'graded' && a.my_submission?.feedback"
                         style="margin-top:12px;padding:10px 14px;background:var(--color-bg);border-radius:6px;border-left:3px solid var(--color-success)">
                        <div style="font-size:11px;font-weight:600;color:var(--color-text-muted);margin-bottom:4px">Nhận xét của giáo viên</div>
                        <div style="font-size:13px;line-height:1.5" x-text="a.my_submission?.feedback"></div>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <!-- ===== MODAL: Nộp bài ===== -->
    <div x-show="showSubmitModal" x-cloak class="modal-overlay" @click.self="showSubmitModal = false">
        <div class="modal-box" style="max-width:540px">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
                <h3 style="margin:0">Nộp bài — <span x-text="submittingItem?.title" style="font-weight:400"></span></h3>
                <button class="btn btn--ghost btn--sm" @click="showSubmitModal = false">✕</button>
            </div>

            <div x-show="submittingItem?.description" style="padding:12px 14px;background:var(--color-bg);border-radius:6px;margin-bottom:16px">
                <div style="font-size:11px;font-weight:600;color:var(--color-text-muted);margin-bottom:4px">Đề bài</div>
                <div style="font-size:12px;line-height:1.5;white-space:pre-wrap" x-text="submittingItem?.description"></div>
            </div>

            <div style="display:flex;flex-direction:column;gap:14px">
                <div>
                    <label class="form-label">Bài làm của bạn</label>
                    <textarea class="form-input" x-model="submitForm.content" rows="6"
                              placeholder="Nhập bài làm của bạn..."></textarea>
                </div>
                <div>
                    <label class="form-label">Link file đính kèm (nếu có)</label>
                    <input class="form-input" x-model="submitForm.file_url" type="url"
                           placeholder="https://drive.google.com/...">
                    <div style="font-size:11px;color:var(--color-text-muted);margin-top:4px">
                        Paste link Google Drive, Dropbox hoặc URL bài làm.
                    </div>
                </div>
            </div>

            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:20px">
                <button class="btn btn--secondary btn--sm" @click="showSubmitModal = false">Hủy</button>
                <button class="btn btn--primary btn--sm" :disabled="submitting" @click="submitWork()">
                    <span x-text="submitting ? 'Đang nộp...' : '📤 Nộp bài'"></span>
                </button>
            </div>
        </div>
    </div>

    <!-- ===== MODAL: Xem bài đã nộp ===== -->
    <div x-show="showViewModal" x-cloak class="modal-overlay" @click.self="showViewModal = false">
        <div class="modal-box" style="max-width:540px">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
                <h3 style="margin:0">Bài đã nộp</h3>
                <button class="btn btn--ghost btn--sm" @click="showViewModal = false">✕</button>
            </div>

            <div x-show="viewingItem?.my_submission?.content"
                 style="padding:14px;background:var(--color-bg);border-radius:6px;margin-bottom:12px">
                <div style="font-size:11px;font-weight:600;color:var(--color-text-muted);margin-bottom:6px">Bài làm của bạn</div>
                <div style="font-size:13px;line-height:1.6;white-space:pre-wrap" x-text="viewingItem?.my_submission?.content"></div>
            </div>
            <div x-show="viewingItem?.my_submission?.file_url" style="margin-bottom:12px">
                <a :href="viewingItem?.my_submission?.file_url" target="_blank" rel="noopener noreferrer"
                   class="btn btn--secondary btn--sm" style="font-size:12px">📎 Xem file đính kèm</a>
            </div>

            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:20px">
                <button class="btn btn--primary btn--sm" @click="showViewModal = false">Đóng</button>
            </div>
        </div>
    </div>

</div>

<style>
.assignment-item { transition: box-shadow var(--transition); }
.assignment-item:hover { box-shadow: var(--shadow-md); }
</style>

<script>
function myClassroomDetail(uuid) {
    return {
        uuid,
        classroom: null,
        assignments: [],
        loading: false,
        showSubmitModal: false,
        showViewModal: false,
        submitting: false,
        submittingItem: null,
        viewingItem: null,
        submitForm: { content: '', file_url: '' },

        async load() {
            this.loading = true;
            const [classData, assignData] = await Promise.all([
                apiGet('/api/my-classrooms/' + this.uuid),
                apiGet('/api/my-classrooms/' + this.uuid + '/assignments'),
            ]);
            if (classData?.status === 'success')  this.classroom   = classData.data;
            if (assignData?.status === 'success') this.assignments = assignData.data;
            this.loading = false;
        },

        openSubmit(a) {
            this.submittingItem = a;
            this.submitForm = { content: '', file_url: '' };
            this.showSubmitModal = true;
        },

        viewSubmission(a) {
            this.viewingItem = a;
            this.showViewModal = true;
        },

        async submitWork() {
            if (!this.submitForm.content.trim() && !this.submitForm.file_url.trim()) {
                showToast('error', 'Vui lòng nhập bài làm hoặc link file.'); return;
            }
            this.submitting = true;
            const body = new URLSearchParams({
                content:  this.submitForm.content || '',
                file_url: this.submitForm.file_url || '',
            });
            const data = await apiRequest('/api/assignments/' + this.submittingItem.uuid + '/submit', {
                method: 'POST', body, headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
            });
            this.submitting = false;
            if (data?.status === 'success') {
                showToast('success', 'Nộp bài thành công!');
                this.showSubmitModal = false;
                const idx = this.assignments.findIndex(x => x.uuid === this.submittingItem.uuid);
                if (idx !== -1) {
                    this.assignments[idx] = { ...this.assignments[idx], my_submission: data.data };
                }
            } else {
                showToast('error', data?.message || 'Có lỗi xảy ra.');
            }
        },

        async leaveClassroom() {
            const ok = await showConfirm({ title: 'Rời lớp học', message: 'Bạn có chắc muốn rời lớp "' + this.classroom?.name + '"?', type: 'danger', confirmText: 'Rời lớp' });
            if (!ok) return;
            const data = await apiRequest('/api/my-classrooms/' + this.uuid + '/leave', { method: 'DELETE' });
            if (data?.status === 'success') {
                showToast('success', 'Đã rời lớp học.');
                window.location.href = '/admin/my-classrooms';
            } else {
                showToast('error', data?.message || 'Có lỗi xảy ra.');
            }
        },
    };
}
</script>

<?= $this->endSection() ?>
