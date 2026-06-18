<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Chi tiết lớp học<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>Lớp học / Chi tiết<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div x-data="classroomDetail('<?= esc($classroomUuid, 'attr') ?>')" x-init="load()">

    <!-- Header -->
    <div class="page-header">
        <div>
            <a href="/admin/classrooms" style="font-size:12px;color:var(--color-text-muted);text-decoration:none">← Danh sách lớp</a>
            <h1 class="content__title" x-text="classroom?.name || 'Đang tải...'"></h1>
            <div style="display:flex;gap:12px;align-items:center;margin-top:4px">
                <span style="font-family:monospace;font-weight:700;font-size:14px;letter-spacing:2px;color:var(--color-primary)" x-text="classroom?.code"></span>
                <button class="btn btn--ghost btn--sm" style="font-size:11px" @click="copyCode()" title="Copy mã lớp">📋 Copy</button>
                <span class="badge" :class="classroom?.auto_approve ? 'badge--success' : 'badge--secondary'"
                      style="font-size:11px" x-text="classroom?.auto_approve ? 'Tự động duyệt' : 'Duyệt thủ công'"></span>
            </div>
        </div>
        <div style="display:flex;gap:8px">
            <button class="btn btn--ghost btn--sm" @click="toggleApproval()"
                    x-text="classroom?.auto_approve ? 'Chuyển sang duyệt thủ công' : 'Bật tự động duyệt'"></button>
            <button class="btn btn--primary btn--sm" @click="openCreateAssignment()">+ Đăng bài tập</button>
        </div>
    </div>

    <!-- Tabs -->
    <div style="display:flex;gap:0;border-bottom:1px solid var(--color-border);margin-bottom:20px">
        <button class="tab-btn" :class="tab === 'members' ? 'tab-btn--active' : ''" @click="tab = 'members'">
            Học sinh <span x-show="pendingCount > 0" class="badge badge--warning" style="font-size:10px;margin-left:4px" x-text="pendingCount"></span>
        </button>
        <button class="tab-btn" :class="tab === 'assignments' ? 'tab-btn--active' : ''" @click="tab = 'assignments'">
            Bài tập (<span x-text="assignments.length"></span>)
        </button>
    </div>

    <!-- === TAB: Members === -->
    <div x-show="tab === 'members'">
        <!-- Pending approval -->
        <div x-show="pendingMembers.length > 0" class="card" style="margin-bottom:16px;border-left:3px solid var(--color-warning)">
            <div class="card__header">
                <h3>Chờ duyệt (<span x-text="pendingMembers.length"></span>)</h3>
            </div>
            <div class="card__body" style="padding:0">
                <table class="table table-card">
                    <tbody>
                        <template x-for="m in pendingMembers" :key="m.id">
                            <tr>
                                <td data-label="Học sinh">
                                    <div style="font-weight:500;font-size:13px" x-text="m.full_name"></div>
                                    <div style="font-size:11px;color:var(--color-text-muted)" x-text="m.email"></div>
                                </td>
                                <td data-label="Lớp" class="hide-mobile" style="font-size:12px;color:var(--color-text-muted)"
                                    x-text="m.student_grade ? 'Lớp ' + m.student_grade : '—'"></td>
                                <td data-label="Thời gian" class="hide-mobile" style="font-size:12px;color:var(--color-text-muted)"
                                    x-text="m.created_at"></td>
                                <td data-label="">
                                    <div style="display:flex;gap:4px;justify-content:flex-end">
                                        <button class="btn btn--success btn--sm" @click="approve(m)">✓ Duyệt</button>
                                        <button class="btn btn--ghost btn--sm" @click="reject(m)">✕ Từ chối</button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Approved members -->
        <div class="card">
            <div class="card__header">
                <h3>Học sinh trong lớp (<span x-text="approvedMembers.length"></span>)</h3>
            </div>
            <div class="card__body" style="padding:0">
                <div x-show="approvedMembers.length === 0" style="padding:32px;text-align:center;color:var(--color-text-muted);font-size:13px">
                    Chưa có học sinh nào. Chia sẻ mã <strong x-text="classroom?.code"></strong> để học sinh tham gia.
                </div>
                <table class="table table-card" x-show="approvedMembers.length > 0">
                    <thead>
                        <tr>
                            <th>Học sinh</th>
                            <th class="hide-mobile">Lớp</th>
                            <th class="hide-tablet">Ngày tham gia</th>
                            <th style="width:60px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="m in approvedMembers" :key="m.id">
                            <tr>
                                <td data-label="Học sinh">
                                    <div style="font-weight:500;font-size:13px" x-text="m.full_name"></div>
                                    <div style="font-size:11px;color:var(--color-text-muted)" x-text="m.email"></div>
                                </td>
                                <td data-label="Lớp" class="hide-mobile" style="font-size:12px;color:var(--color-text-muted)"
                                    x-text="m.student_grade ? 'Lớp ' + m.student_grade : '—'"></td>
                                <td data-label="Ngày tham gia" class="hide-tablet" style="font-size:12px;color:var(--color-text-muted)"
                                    x-text="m.joined_at ? m.joined_at.substring(0,10) : '—'"></td>
                                <td data-label="">
                                    <button class="btn btn--ghost btn--sm" @click="removeMember(m)" title="Xóa khỏi lớp">✕</button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- === TAB: Assignments === -->
    <div x-show="tab === 'assignments'">
        <div x-show="assignments.length === 0 && !loadingAssignments" class="card"
             style="padding:40px;text-align:center;color:var(--color-text-muted)">
            Chưa có bài tập nào. Nhấn "+ Đăng bài tập" để tạo.
        </div>

        <div class="card" x-show="assignments.length > 0">
            <div class="card__body" style="padding:0">
                <table class="table table-card">
                    <thead>
                        <tr>
                            <th>Tiêu đề</th>
                            <th class="hide-mobile">Hạn nộp</th>
                            <th class="hide-mobile">Điểm tối đa</th>
                            <th class="hide-tablet">Đã nộp</th>
                            <th>Trạng thái</th>
                            <th style="width:100px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="a in assignments" :key="a.id">
                            <tr>
                                <td data-label="Tiêu đề">
                                    <div style="font-weight:500;font-size:13px" x-text="a.title"></div>
                                    <div x-show="a.description" style="font-size:11px;color:var(--color-text-muted);margin-top:2px"
                                         x-text="a.description?.substring(0,60) + (a.description?.length > 60 ? '...' : '')"></div>
                                </td>
                                <td data-label="Hạn nộp" class="hide-mobile" style="font-size:12px;color:var(--color-text-muted)"
                                    x-text="a.due_date ? a.due_date.substring(0,10) : 'Không giới hạn'"></td>
                                <td data-label="Điểm" class="hide-mobile" style="font-size:12px" x-text="a.max_score"></td>
                                <td data-label="Đã nộp" class="hide-tablet" style="font-size:12px">
                                    <span x-text="a.submission_count"></span>/<span x-text="approvedMembers.length"></span>
                                    <span x-show="a.graded_count > 0" style="color:var(--color-success);margin-left:4px"
                                          x-text="'(' + a.graded_count + ' đã chấm)'"></span>
                                </td>
                                <td data-label="Trạng thái">
                                    <span class="badge" :class="a.is_published ? 'badge--success' : 'badge--secondary'"
                                          x-text="a.is_published ? 'Đã đăng' : 'Nháp'"></span>
                                </td>
                                <td data-label="">
                                    <div style="display:flex;gap:4px;justify-content:flex-end">
                                        <button x-show="a.file_path" class="btn btn--ghost btn--sm"
                                                @click="downloadFile(a)" title="Tải file đính kèm">📎</button>
                                        <button class="btn btn--ghost btn--sm"
                                                @click="viewAssignment(a)" title="Xem bài nộp">👁</button>
                                        <button class="btn btn--ghost btn--sm"
                                                @click="deleteAssignment(a)" title="Xóa">✕</button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ===== MODAL: Tạo bài tập ===== -->
    <div x-show="showAssignmentForm" x-cloak class="modal-overlay" @click.self="showAssignmentForm = false">
        <div class="modal-box" style="max-width:820px">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
                <h3 style="margin:0">Đăng bài tập</h3>
                <button class="btn btn--ghost btn--sm" @click="showAssignmentForm = false">✕</button>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px 20px">
                <div style="grid-column:1/-1">
                    <label class="form-label">Tiêu đề bài tập <span style="color:var(--color-danger)">*</span></label>
                    <input class="form-input" x-model="aForm.title" placeholder="VD: Bài tập chương 3 — Phương trình bậc 2">
                </div>
                <div style="grid-column:1/-1">
                    <label class="form-label">Nội dung / Đề bài</label>
                    <textarea class="form-input" x-model="aForm.description" rows="5"
                              placeholder="Mô tả chi tiết bài tập, yêu cầu nộp bài..."></textarea>
                </div>
                <div>
                    <label class="form-label">Hạn nộp bài</label>
                    <input type="datetime-local" class="form-input" x-model="aForm.due_date">
                </div>
                <div>
                    <label class="form-label">Điểm tối đa</label>
                    <input type="number" class="form-input" x-model="aForm.max_score" min="1" max="1000" placeholder="100">
                </div>
                <div style="grid-column:1/-1">
                    <label class="form-label">
                        Đính kèm file
                        <span style="font-size:11px;color:var(--color-text-muted);font-weight:400"> — PDF, Word, Excel, PowerPoint (tối đa 20MB)</span>
                    </label>
                    <label style="display:flex;align-items:center;gap:10px;border:1px dashed var(--color-border);border-radius:6px;padding:10px 14px;cursor:pointer;background:var(--color-bg-secondary);transition:border-color 0.2s"
                           :style="aForm.file ? 'border-color:var(--color-primary)' : ''">
                        <input type="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx"
                               @change="aForm.file = $event.target.files[0]"
                               style="display:none">
                        <span style="font-size:18px">📎</span>
                        <span style="font-size:13px;color:var(--color-text-muted)" x-show="!aForm.file">Nhấn để chọn file đính kèm (tuỳ chọn)</span>
                        <span style="font-size:13px;color:var(--color-text);flex:1" x-show="aForm.file" x-text="aForm.file?.name"></span>
                        <button x-show="aForm.file" type="button" class="btn btn--ghost btn--sm"
                                @click.stop="aForm.file = null; $el.closest('label').querySelector('input').value = ''"
                                style="margin-left:auto">✕</button>
                    </label>
                </div>
                <div style="grid-column:1/-1">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                        <input type="checkbox" x-model="aForm.is_published" style="width:16px;height:16px">
                        <span style="font-size:13px">Đăng ngay (học sinh có thể thấy)</span>
                    </label>
                </div>
            </div>

            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:20px">
                <button class="btn btn--secondary btn--sm" @click="showAssignmentForm = false">Hủy</button>
                <button class="btn btn--primary btn--sm" :disabled="submittingA" @click="submitAssignment()">
                    <span x-text="submittingA ? 'Đang lưu...' : 'Đăng bài tập'"></span>
                </button>
            </div>
        </div>
    </div>

</div>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="/assets/modules/Classroom/classroom.css">
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="/assets/modules/Classroom/classroom.js"></script>
<?= $this->endSection() ?>

<?= $this->endSection() ?>
