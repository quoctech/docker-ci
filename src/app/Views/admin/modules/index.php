<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Quản lý Module<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>Hệ thống / Quản lý Module<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div x-data="moduleManager()" x-init="loadModules()">
    <div style="margin-bottom:24px">
        <h1 class="content__title">Quản lý Module</h1>
        <p class="content__subtitle">Danh sách module đang hoạt động trong hệ thống.</p>
    </div>

    <!-- Loading -->
    <div x-show="loading" style="text-align:center;padding:40px;color:var(--color-text-muted)">
        Đang tải...
    </div>

    <!-- Module list -->
    <div class="grid grid--2" x-show="!loading">
        <template x-for="module in modules" :key="module.id">
            <div class="module-card">
                <div class="module-card__info" style="width:100%">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
                        <h4 x-text="module.name" style="margin:0"></h4>
                        <span class="badge badge--info">Core</span>
                        <span class="badge badge--success">Đang hoạt động</span>
                    </div>
                    <p x-text="module.description"></p>
                    <span style="font-size:11px;color:var(--color-text-muted)" x-text="'Phiên bản ' + module.version"></span>
                </div>
            </div>
        </template>
    </div>

    <div class="card" style="margin-top:24px" x-show="!loading">
        <div class="card__body" style="text-align:center;color:var(--color-text-muted);font-size:13px">
            Các module mở rộng sẽ hiển thị tại đây khi được phát triển.
        </div>
    </div>
</div>

<script>
function moduleManager() {
    return {
        modules: [],
        loading: false,

        async loadModules() {
            this.loading = true;
            const data = await apiGet('/api/admin/modules');
            if (data && data.status === 'success') {
                this.modules = data.data;
            }
            this.loading = false;
        }
    };
}
</script>

<?= $this->endSection() ?>
