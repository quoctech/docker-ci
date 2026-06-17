<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Chấm bài tập<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>Lớp học / Bài tập / Chấm bài<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div x-data="assignmentGrader('<?= esc($classroomUuid, 'attr') ?>', '<?= esc($assignmentUuid, 'attr') ?>')" x-init="load()">

    <!-- Header -->
    <div class="page-header">
        <div>
            <a :href="'/admin/classrooms/' + classroomUuid" style="font-size:12px;color:var(--color-text-muted);text-decoration:none">← Quay lại lớp học</a>
            <h1 class="content__title" x-text="assignment?.title || 'Đang tải...'"></h1>
            <div style="display:flex;gap:12px;font-size:12px;color:var(--color-text-muted);margin-top:4px;flex-wrap:wrap">
                <span x-show="assignment?.due_date">🗓 Hạn: <span x-text="assignment?.due_date?.substring(0,10)"></span></span>
                <span>📊 Điểm tối đa: <span x-text="assignment?.max_score"></span></span>
                <span>📥 Đã nộp: <span x-text="submissions.length"></span></span>
                <span>✅ Đã chấm: <span x-text="gradedCount"></span></span>
            </div>
        </div>
        <div style="display:flex;gap:8px;align-items:center">
            <span class="badge" :class="assignment?.is_published ? 'badge--success' : 'badge--secondary'"
                  x-text="assignment?.is_published ? 'Đã đăng' : 'Nháp'"></span>
        </div>
    </div>

    <!-- Assignment description -->
    <div x-show="assignment?.description" class="card" style="margin-bottom:20px">
        <div class="card__body">
            <div style="font-size:12px;font-weight:600;color:var(--color-text-muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:0.5px">Nội dung đề bài</div>
            <div style="font-size:13px;line-height:1.6;white-space:pre-wrap" x-text="assignment?.description"></div>
        </div>
    </div>

    <!-- Loading -->
    <div x-show="loading" style="text-align:center;padding:48px;color:var(--color-text-muted)">Đang tải...</div>

    <!-- Empty state -->
    <div x-show="!loading && submissions.length === 0" class="card" style="padding:40px;text-align:center;color:var(--color-text-muted)">
        Chưa có học sinh nào nộp bài.
    </div>

    <!-- Submissions list -->
    <div x-show="!loading && submissions.length > 0" class="card">
        <div class="card__header">
            <h3>Bài nộp (<span x-text="submissions.length"></span>)</h3>
            <div style="display:flex;gap:8px">
                <select class="form-input" x-model="filterStatus" style="font-size:12px;width:auto">
                    <option value="">Tất cả</option>
                    <option value="submitted">Chờ chấm</option>
                    <option value="graded">Đã chấm</option>
                </select>
            </div>
        </div>
        <div class="card__body" style="padding:0">
            <table class="table table-card">
                <thead>
                    <tr>
                        <th>Học sinh</th>
                        <th class="hide-mobile">Nội dung</th>
                        <th class="hide-tablet">Thời gian nộp</th>
                        <th>Điểm</th>
                        <th style="width:80px"></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="s in filteredSubmissions" :key="s.id">
                        <tr :class="s.status === 'graded' ? 'graded-row' : ''">
                            <td data-label="Học sinh">
                                <div style="font-weight:500;font-size:13px" x-text="s.full_name"></div>
                                <div style="font-size:11px;color:var(--color-text-muted)" x-text="s.email"></div>
                            </td>
                            <td data-label="Nội dung" class="hide-mobile">
                                <div x-show="s.content" style="font-size:12px;color:var(--color-text-muted);max-width:280px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"
                                     x-text="s.content?.substring(0,80) + (s.content?.length > 80 ? '...' : '')"></div>
                                <a x-show="s.file_url" :href="s.file_url" target="_blank" rel="noopener noreferrer"
                                   style="font-size:11px;color:var(--color-primary)">📎 Xem file đính kèm</a>
                            </td>
                            <td data-label="Thời gian nộp" class="hide-tablet" style="font-size:12px;color:var(--color-text-muted)"
                                x-text="s.submitted_at?.substring(0,16).replace('T', ' ')"></td>
                            <td data-label="Điểm">
                                <span x-show="s.status === 'graded'" style="font-weight:700;font-size:14px;color:var(--color-success)" x-text="s.score"></span>
                                <span x-show="s.status !== 'graded'" style="font-size:12px;color:var(--color-text-muted)">—</span>
                            </td>
                            <td data-label="">
                                <button class="btn btn--primary btn--sm" style="font-size:11px" @click="openGrade(s)">
                                    <span x-text="s.status === 'graded' ? '✏ Sửa' : '📝 Chấm'"></span>
                                </button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ===== MODAL: Chấm điểm ===== -->
    <div x-show="showGradeModal" x-cloak class="modal-overlay" @click.self="showGradeModal = false">
        <div class="modal-box" style="max-width:560px">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
                <h3 style="margin:0">Chấm bài — <span x-text="gradingItem?.full_name" style="font-weight:400"></span></h3>
                <button class="btn btn--ghost btn--sm" @click="showGradeModal = false">✕</button>
            </div>

            <!-- Student submission content -->
            <div x-show="gradingItem?.content" style="padding:14px;background:var(--color-bg);border-radius:6px;margin-bottom:16px">
                <div style="font-size:11px;font-weight:600;color:var(--color-text-muted);margin-bottom:6px;text-transform:uppercase">Bài làm của học sinh</div>
                <div style="font-size:13px;line-height:1.6;white-space:pre-wrap;max-height:200px;overflow-y:auto" x-text="gradingItem?.content"></div>
            </div>
            <div x-show="gradingItem?.file_url" style="margin-bottom:16px">
                <a :href="gradingItem?.file_url" target="_blank" rel="noopener noreferrer"
                   class="btn btn--secondary btn--sm" style="font-size:12px">📎 Xem file đính kèm</a>
            </div>

            <!-- Grade form -->
            <div style="display:flex;flex-direction:column;gap:14px">
                <div style="display:flex;align-items:center;gap:12px">
                    <label class="form-label" style="white-space:nowrap;margin:0">Điểm <span style="color:var(--color-danger)">*</span></label>
                    <input type="number" class="form-input" x-model="gradeForm.score"
                           :min="0" :max="assignment?.max_score"
                           :placeholder="'0–' + assignment?.max_score"
                           style="width:100px">
                    <span style="font-size:13px;color:var(--color-text-muted)">/ <span x-text="assignment?.max_score"></span></span>
                </div>
                <div>
                    <label class="form-label">Nhận xét của giáo viên</label>
                    <textarea class="form-input" x-model="gradeForm.feedback" rows="3"
                              placeholder="Góp ý cho học sinh..."></textarea>
                </div>
            </div>

            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:20px">
                <button class="btn btn--secondary btn--sm" @click="showGradeModal = false">Hủy</button>
                <button class="btn btn--primary btn--sm" :disabled="submittingGrade" @click="submitGrade()">
                    <span x-text="submittingGrade ? 'Đang lưu...' : 'Lưu điểm'"></span>
                </button>
            </div>
        </div>
    </div>

</div>

<style>
.graded-row td { background: color-mix(in srgb, var(--color-success) 4%, transparent); }
</style>

<script>
function assignmentGrader(classroomUuid, assignmentUuid) {
    return {
        classroomUuid,
        assignmentUuid,
        assignment: null,
        submissions: [],
        loading: false,
        showGradeModal: false,
        submittingGrade: false,
        gradingItem: null,
        gradeForm: { score: '', feedback: '' },
        filterStatus: '',

        get gradedCount() {
            return this.submissions.filter(s => s.status === 'graded').length;
        },
        get filteredSubmissions() {
            if (!this.filterStatus) return this.submissions;
            return this.submissions.filter(s => s.status === this.filterStatus);
        },

        async load() {
            this.loading = true;
            const [assignData, subData] = await Promise.all([
                apiGet('/api/assignments/' + this.assignmentUuid),
                apiGet('/api/assignments/' + this.assignmentUuid + '/submissions'),
            ]);
            if (assignData?.status === 'success') this.assignment  = assignData.data;
            if (subData?.status === 'success')   this.submissions = subData.data.submissions || subData.data;
            this.loading = false;
        },

        openGrade(s) {
            this.gradingItem  = s;
            this.gradeForm    = { score: s.score ?? '', feedback: s.feedback ?? '' };
            this.showGradeModal = true;
        },

        async submitGrade() {
            const score = this.gradeForm.score;
            if (score === '' || score === null) { showToast('error', 'Vui lòng nhập điểm.'); return; }
            if (score < 0 || score > this.assignment.max_score) {
                showToast('error', 'Điểm phải từ 0 đến ' + this.assignment.max_score + '.'); return;
            }
            this.submittingGrade = true;
            const body = new URLSearchParams({ score, feedback: this.gradeForm.feedback || '' });
            const data = await apiRequest('/api/submissions/' + this.gradingItem.uuid + '/grade', {
                method: 'PUT', body, headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
            });
            this.submittingGrade = false;
            if (data?.status === 'success') {
                const idx = this.submissions.findIndex(s => s.uuid === this.gradingItem.uuid);
                if (idx !== -1) this.submissions[idx] = { ...this.submissions[idx], ...data.data };
                this.showGradeModal = false;
                showToast('success', 'Chấm điểm thành công!');
            } else {
                showToast('error', data?.message || 'Có lỗi xảy ra.');
            }
        },
    };
}
</script>

<?= $this->endSection() ?>
