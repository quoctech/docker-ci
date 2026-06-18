<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Lớp học<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>Lớp học của tôi / Chi tiết<?= $this->endSection() ?>

<?= $this->section('content') ?>

<script>
(function() {
    try {
        var u = JSON.parse(localStorage.getItem('user') || 'null');
        if (u && u.role !== 'user') window.location.replace('/admin/classrooms');
    } catch (e) {}
})();
</script>

<div x-data="myClassroomDetail('<?= esc($classroomUuid, 'attr') ?>')" x-init="load()">

    <!-- Header -->
    <div class="page-header">
        <div>
            <a href="/admin/my-classrooms" class="back-link">← Lớp học của tôi</a>
            <h1 class="content__title" x-text="classroom?.name || 'Đang tải...'"></h1>
            <div class="meta-row">
                <span x-show="classroom?.teacher_name">👩‍🏫 <span x-text="classroom?.teacher_name"></span></span>
                <span x-show="classroom?.subject" x-text="classroom?.subject"></span>
                <span x-show="classroom?.grade">Lớp <span x-text="classroom?.grade"></span></span>
            </div>
        </div>
        <button class="btn btn--ghost btn--sm btn--danger-text" @click="leaveClassroom()">Rời lớp</button>
    </div>

    <!-- Loading -->
    <div x-show="loading" class="loading-state">Đang tải...</div>

    <!-- Empty state -->
    <div x-show="!loading && assignments.length === 0" class="card empty-state">
        <div class="empty-icon">📚</div>
        <div class="empty-title">Giáo viên chưa đăng bài tập nào</div>
    </div>

    <!-- Assignments list -->
    <div x-show="!loading && assignments.length > 0" class="assignment-list">
        <template x-for="a in assignments" :key="a.id">
            <div class="card asgn-card">
                <div class="card__body asgn-body">

                    <!-- Assignment info + status (2-col on desktop) -->
                    <div class="asgn-row">
                        <div class="asgn-info">
                            <div class="asgn-title" x-text="a.title"></div>
                            <div x-show="a.description" class="asgn-desc" x-text="a.description"></div>
                            <div class="asgn-meta">
                                <span x-show="a.due_date">🗓 <span x-text="a.due_date?.substring(0,10)"></span></span>
                                <span>📊 Điểm tối đa: <span x-text="a.max_score"></span></span>
                                <!-- Teacher file attachment -->
                                <button x-show="a.file_path"
                                        class="btn btn--ghost btn--xs file-dl-btn"
                                        @click="downloadAssignmentFile(a)">
                                    📎 Tải đề bài
                                </button>
                            </div>
                        </div>

                        <!-- Submission status -->
                        <div class="asgn-status">
                            <template x-if="!a.my_submission">
                                <div class="status-block">
                                    <div class="status-label muted">Chưa nộp bài</div>
                                    <button class="btn btn--primary btn--sm" @click="openSubmit(a)">📤 Nộp bài</button>
                                </div>
                            </template>
                            <template x-if="a.my_submission">
                                <div class="status-block">
                                    <div x-show="a.my_submission.status === 'submitted'" class="badge badge--warning">⏳ Chờ chấm</div>
                                    <div x-show="a.my_submission.status === 'graded'" class="grade-display">
                                        <div class="grade-score" x-text="a.my_submission.score + '/' + a.max_score"></div>
                                        <div class="grade-label">Đã chấm</div>
                                    </div>
                                    <button class="btn btn--secondary btn--sm view-sub-btn" @click="viewSubmission(a)">Xem bài nộp</button>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Teacher feedback (graded) -->
                    <div x-show="a.my_submission?.status === 'graded' && a.my_submission?.feedback" class="feedback-box">
                        <div class="feedback-label">Nhận xét của giáo viên</div>
                        <div class="feedback-text" x-text="a.my_submission?.feedback"></div>
                    </div>

                </div>
            </div>
        </template>
    </div>

    <!-- ===== MODAL: Nộp bài ===== -->
    <div x-show="showSubmitModal" x-cloak class="modal-overlay" @click.self="closeSubmitModal()">
        <div class="modal-box modal-submit">
            <!-- Header -->
            <div class="modal-hdr">
                <div>
                    <div style="font-weight:600;font-size:15px" x-text="submittingItem?.title"></div>
                    <div style="font-size:12px;color:var(--color-text-muted)">Nộp bài tập</div>
                </div>
                <button class="btn btn--ghost btn--sm" @click="closeSubmitModal()">✕</button>
            </div>

            <!-- Đề bài -->
            <div x-show="submittingItem?.description" class="desc-preview">
                <div class="desc-preview-label">Đề bài</div>
                <div style="font-size:12px;line-height:1.55;white-space:pre-wrap" x-text="submittingItem?.description"></div>
            </div>

            <!-- Image section -->
            <div class="submit-section">
                <div class="submit-section-label">📷 Ảnh bài làm <span class="count-hint" x-text="'(' + submitForm.imagePreviews.length + '/10)'"></span></div>

                <!-- Camera/gallery buttons -->
                <div class="img-btn-row">
                    <button class="btn btn--secondary btn--sm" @click="$refs.cameraInput.click()" :disabled="submitForm.imagePreviews.length >= 10">
                        📷 Chụp ảnh
                    </button>
                    <button class="btn btn--secondary btn--sm" @click="$refs.galleryInput.click()" :disabled="submitForm.imagePreviews.length >= 10">
                        🖼 Chọn từ thiết bị
                    </button>
                    <input x-ref="cameraInput" type="file" accept="image/*" capture="environment"
                           style="display:none" @change="handleImageSelect($event)">
                    <input x-ref="galleryInput" type="file" accept="image/*" multiple
                           style="display:none" @change="handleImageSelect($event)">
                </div>

                <!-- Image previews -->
                <div x-show="submitForm.imagePreviews.length > 0" class="img-preview-grid">
                    <template x-for="(src, i) in submitForm.imagePreviews" :key="i">
                        <div class="img-preview-item">
                            <img :src="src" class="img-preview-thumb" alt="">
                            <button class="img-preview-remove" @click="removeImage(i)" title="Xóa ảnh">✕</button>
                        </div>
                    </template>
                </div>

                <div x-show="submitForm.imagePreviews.length === 0" class="img-empty-hint">
                    Chưa có ảnh nào. Chụp hoặc chọn ảnh từ thiết bị.
                </div>
            </div>

            <!-- Text section -->
            <div class="submit-section">
                <div class="submit-section-label">📝 Ghi chú <span style="font-weight:400;color:var(--color-text-muted)">(tùy chọn)</span></div>
                <textarea class="form-input" x-model="submitForm.content" rows="3"
                          placeholder="Viết thêm ghi chú cho bài làm..."></textarea>
            </div>

            <!-- Actions -->
            <div class="modal-footer">
                <button class="btn btn--secondary btn--sm" @click="closeSubmitModal()">Hủy</button>
                <button class="btn btn--primary btn--sm" :disabled="submitting" @click="submitWork()">
                    <span x-text="submitting ? 'Đang nộp...' : '📤 Nộp bài'"></span>
                </button>
            </div>
        </div>
    </div>

    <!-- ===== MODAL: Xem bài đã nộp ===== -->
    <div x-show="showViewModal" x-cloak class="modal-overlay" @click.self="showViewModal = false">
        <div class="modal-box modal-view">
            <div class="modal-hdr">
                <div>
                    <div style="font-weight:600;font-size:15px" x-text="viewingItem?.title"></div>
                    <div style="font-size:12px;color:var(--color-text-muted)">Bài đã nộp</div>
                </div>
                <button class="btn btn--ghost btn--sm" @click="showViewModal = false">✕</button>
            </div>

            <!-- Images -->
            <div x-show="viewImages.length > 0 || loadingViewImages">
                <div class="submit-section-label" style="margin-bottom:8px">📷 Ảnh bài làm</div>
                <div x-show="loadingViewImages" class="loading-state" style="padding:20px">Đang tải ảnh...</div>
                <div x-show="!loadingViewImages && viewImages.length > 0" class="view-img-grid">
                    <template x-for="(src, i) in viewImages" :key="i">
                        <img :src="src" class="view-img-thumb" @click="openLightbox(src)" alt="">
                    </template>
                </div>
            </div>

            <!-- Text -->
            <div x-show="viewingItem?.my_submission?.content" style="margin-top:12px">
                <div class="submit-section-label" style="margin-bottom:6px">📝 Ghi chú</div>
                <div style="font-size:13px;line-height:1.6;white-space:pre-wrap;padding:12px;background:var(--color-bg);border-radius:8px"
                     x-text="viewingItem?.my_submission?.content"></div>
            </div>

            <!-- Score & Feedback -->
            <div x-show="viewingItem?.my_submission?.status === 'graded'" style="margin-top:16px">
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px">
                    <div class="grade-score" x-text="viewingItem?.my_submission?.score + '/' + viewingItem?.max_score"></div>
                    <div class="badge badge--success">✅ Đã chấm</div>
                </div>
                <div x-show="viewingItem?.my_submission?.feedback" class="feedback-box">
                    <div class="feedback-label">Nhận xét của giáo viên</div>
                    <div class="feedback-text" x-text="viewingItem?.my_submission?.feedback"></div>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn--primary btn--sm" @click="showViewModal = false">Đóng</button>
            </div>
        </div>
    </div>

    <!-- Lightbox -->
    <div x-show="lightboxSrc" x-cloak class="lightbox-overlay" @click="lightboxSrc = null">
        <img :src="lightboxSrc" class="lightbox-img" @click.stop="">
        <button class="lightbox-close" @click="lightboxSrc = null">✕</button>
    </div>

</div>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="/assets/modules/Classroom/classroom.css">
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="/assets/modules/Classroom/classroom.js"></script>
<?= $this->endSection() ?>

<?= $this->endSection() ?>
