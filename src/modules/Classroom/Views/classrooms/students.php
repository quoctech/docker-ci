<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Danh sách học sinh<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>Lớp học / Học sinh<?= $this->endSection() ?>

<?= $this->section('content') ?>

<script>
(function() {
    try {
        var u = JSON.parse(localStorage.getItem('user') || 'null');
        if (u && u.role === 'user') window.location.replace('/admin/my-classrooms');
    } catch (e) {}
})();
</script>

<div x-data="classroomStudents()" x-init="load()">

    <div class="page-header">
        <div>
            <h1 class="content__title">Danh sách học sinh</h1>
            <p class="content__subtitle">Tất cả học sinh trong các lớp của bạn.</p>
        </div>
        <a href="/admin/classrooms" class="btn btn--ghost btn--sm">← Quay lại lớp học</a>
    </div>

    <!-- Bộ lọc -->
    <div class="card" style="margin-bottom:16px">
        <div class="card__body" style="padding:14px 20px;display:flex;gap:12px;flex-wrap:wrap;align-items:center">
            <input class="form-input" x-model="search"
                   placeholder="Tìm theo tên, email..."
                   style="flex:1;min-width:200px;max-width:320px">
            <select class="form-input" x-model="filterClassroom" style="flex:1;min-width:160px;max-width:240px">
                <option value="">Tất cả lớp</option>
                <template x-for="c in classrooms" :key="c.uuid">
                    <option :value="c.uuid" x-text="c.name"></option>
                </template>
            </select>
            <span style="font-size:12px;color:var(--color-text-muted);white-space:nowrap">
                <span x-text="filtered.length"></span> học sinh
            </span>
        </div>
    </div>

    <!-- Loading -->
    <div x-show="loading" style="text-align:center;padding:48px;color:var(--color-text-muted)">Đang tải...</div>

    <!-- Empty state -->
    <div x-show="!loading && students.length === 0" class="card" style="padding:48px;text-align:center">
        <div style="font-size:36px;margin-bottom:12px">👥</div>
        <div style="font-weight:600;font-size:15px;margin-bottom:6px">Chưa có học sinh nào</div>
        <div style="color:var(--color-text-muted);font-size:13px">Học sinh sẽ xuất hiện sau khi tham gia và được duyệt vào lớp.</div>
    </div>

    <!-- Bảng học sinh -->
    <div x-show="!loading && students.length > 0" class="card">
        <div class="card__body" style="padding:0">
            <table class="table table-card">
                <thead>
                    <tr>
                        <th>Học sinh</th>
                        <th>Lớp học</th>
                        <th class="hide-mobile">Môn / Khối</th>
                        <th class="hide-tablet">Ngày tham gia</th>
                        <th class="hide-mobile">Đã nộp</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(s, i) in filtered" :key="s.classroom_uuid + '_' + s.email">
                        <tr>
                            <td data-label="Học sinh">
                                <div style="font-weight:500;font-size:13px" x-text="s.full_name"></div>
                                <div style="font-size:11px;color:var(--color-text-muted)" x-text="s.email"></div>
                            </td>
                            <td data-label="Lớp học">
                                <a :href="'/admin/classrooms/' + s.classroom_uuid"
                                   style="font-size:13px;color:var(--color-primary);text-decoration:none;font-weight:500"
                                   x-text="s.classroom_name"></a>
                            </td>
                            <td data-label="Môn / Khối" class="hide-mobile">
                                <span style="font-size:12px;color:var(--color-text-muted)"
                                      x-text="[s.subject, s.grade ? 'Lớp ' + s.grade : ''].filter(Boolean).join(' · ') || '—'"></span>
                            </td>
                            <td data-label="Ngày tham gia" class="hide-tablet">
                                <span style="font-size:12px;color:var(--color-text-muted)"
                                      x-text="s.joined_at?.substring(0,10) || '—'"></span>
                            </td>
                            <td data-label="Đã nộp" class="hide-mobile">
                                <span style="font-size:13px;font-weight:500" x-text="s.submission_count"></span>
                                <span style="font-size:11px;color:var(--color-text-muted)"> bài</span>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <!-- No results -->
    <div x-show="!loading && students.length > 0 && filtered.length === 0"
         style="text-align:center;padding:32px;color:var(--color-text-muted);font-size:13px">
        Không tìm thấy học sinh phù hợp.
    </div>

</div>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="/assets/modules/Classroom/classroom.css">
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="/assets/modules/Classroom/classroom.js"></script>
<?= $this->endSection() ?>

<?= $this->endSection() ?>
