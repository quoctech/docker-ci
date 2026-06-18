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
                                <div x-show="s.image_paths?.length > 0" style="font-size:12px;color:var(--color-primary);margin-bottom:2px">
                                    📷 <span x-text="s.image_paths?.length"></span> ảnh
                                </div>
                                <div x-show="s.content" style="font-size:12px;color:var(--color-text-muted);max-width:240px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"
                                     x-text="s.content?.substring(0,60) + (s.content?.length > 60 ? '...' : '')"></div>
                                <div x-show="!s.image_paths?.length && !s.content" style="font-size:12px;color:var(--color-text-muted)">—</div>
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

            <!-- Student submission: images -->
            <div x-show="gradeImages.length > 0 || loadingGradeImages" style="margin-bottom:16px">
                <div style="font-size:11px;font-weight:600;color:var(--color-text-muted);margin-bottom:8px;text-transform:uppercase">Ảnh bài làm</div>
                <div x-show="loadingGradeImages" style="font-size:12px;color:var(--color-text-muted)">Đang tải ảnh...</div>
                <div x-show="!loadingGradeImages && gradeImages.length > 0" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(88px,1fr));gap:6px">
                    <template x-for="(src, i) in gradeImages" :key="i">
                        <img :src="src" style="width:100%;aspect-ratio:1;object-fit:cover;border-radius:6px;cursor:pointer"
                             @click="gradeImageLightbox = src" alt="">
                    </template>
                </div>
            </div>

            <!-- Student submission: text -->
            <div x-show="gradingItem?.content" style="padding:14px;background:var(--color-bg);border-radius:6px;margin-bottom:16px">
                <div style="font-size:11px;font-weight:600;color:var(--color-text-muted);margin-bottom:6px;text-transform:uppercase">Ghi chú của học sinh</div>
                <div style="font-size:13px;line-height:1.6;white-space:pre-wrap;max-height:200px;overflow-y:auto" x-text="gradingItem?.content"></div>
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

    <!-- Image lightbox -->
    <div x-show="gradeImageLightbox" x-cloak
         style="position:fixed;inset:0;z-index:2000;background:rgba(0,0,0,.9);display:flex;align-items:center;justify-content:center;padding:16px"
         @click="gradeImageLightbox = null">
        <img :src="gradeImageLightbox" style="max-width:100%;max-height:90vh;object-fit:contain;border-radius:4px" @click.stop="">
        <button @click="gradeImageLightbox = null"
                style="position:absolute;top:16px;right:16px;background:rgba(255,255,255,.15);color:#fff;border:none;border-radius:50%;width:36px;height:36px;font-size:18px;cursor:pointer;display:flex;align-items:center;justify-content:center">✕</button>
    </div>

</div>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="/assets/modules/Classroom/classroom.css">
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="/assets/modules/Classroom/classroom.js"></script>
<?= $this->endSection() ?>

<?= $this->endSection() ?>
