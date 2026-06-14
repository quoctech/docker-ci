<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Quản lý Module<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>Hệ thống / Quản lý Module<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div x-data="moduleManager()" x-init="init()">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px">
        <div>
            <h1 class="content__title">Quản lý Module</h1>
            <p class="content__subtitle">Bật / tắt tính năng. Cài đặt module mới từ thư mục <code>modules/</code>.</p>
        </div>
        <div style="display:flex;gap:8px">
            <button class="btn btn--secondary btn--sm" @click="scanModules()" :disabled="scanning">
                <span x-text="scanning ? 'Đang quét...' : '🔍 Quét module mới'"></span>
            </button>
            <button class="btn btn--secondary btn--sm" @click="syncCache()" :disabled="syncing">
                <span x-text="syncing ? 'Đang sync...' : '🔄 Sync Redis'"></span>
            </button>
        </div>
    </div>

    <!-- Loading -->
    <div x-show="loading" style="text-align:center;padding:40px;color:var(--color-text-muted)">Đang tải...</div>

    <div x-show="!loading">

        <!-- Installed modules -->
        <div class="grid grid--2">
            <template x-for="module in modules" :key="module.id">
                <div class="card" style="position:relative;padding:0">
                    <div class="card__body" style="padding:20px">
                        <!-- Top row: badges + toggle -->
                        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px;margin-bottom:12px">
                            <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap">
                                <span x-text="module.icon || '🧩'" style="font-size:20px"></span>
                                <h4 x-text="module.name" style="margin:0;font-size:14px"></h4>
                            </div>
                            <!-- Toggle — chỉ hiện cho non-core -->
                            <div x-show="!module.is_core" style="flex-shrink:0">
                                <label class="toggle-switch" :title="module.is_enabled ? 'Tắt module' : 'Bật module'">
                                    <input type="checkbox"
                                           :checked="module.is_enabled"
                                           @change="toggleModule(module, $event.target.checked)">
                                    <span class="toggle-switch__slider"></span>
                                </label>
                            </div>
                        </div>

                        <!-- Badges -->
                        <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:10px">
                            <span x-show="module.is_core" class="badge badge--warning" style="font-size:10px">Core</span>
                            <span x-show="module.is_enabled" class="badge badge--success" style="font-size:10px">Đang bật</span>
                            <span x-show="!module.is_enabled" class="badge badge--danger" style="font-size:10px">Đã tắt</span>
                        </div>

                        <p x-text="module.description" style="font-size:13px;color:var(--color-text-muted);margin:0 0 10px"></p>

                        <div style="display:flex;align-items:center;justify-content:space-between">
                            <span style="font-size:11px;color:var(--color-text-muted)" x-text="'v' + module.version"></span>
                            <a x-show="module.admin_url && module.is_enabled"
                               :href="module.admin_url"
                               class="btn btn--primary btn--sm"
                               style="font-size:11px">Quản lý →</a>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <!-- Uninstalled modules (found after scan) -->
        <template x-if="uninstalled.length > 0">
            <div style="margin-top:32px">
                <h3 style="margin-bottom:16px;font-size:15px">📦 Module chưa cài đặt</h3>
                <div class="grid grid--2">
                    <template x-for="mod in uninstalled" :key="mod.slug">
                        <div class="card" style="border:2px dashed var(--color-border);padding:0">
                            <div class="card__body" style="padding:20px">
                                <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
                                    <span x-text="mod.icon || '🧩'" style="font-size:20px"></span>
                                    <h4 x-text="mod.name" style="margin:0;font-size:14px"></h4>
                                    <span class="badge" style="background:#f3f3f3;color:#888;font-size:10px">Chưa cài</span>
                                </div>
                                <p x-text="mod.description" style="font-size:13px;color:var(--color-text-muted);margin:0 0 14px"></p>
                                <div style="display:flex;align-items:center;justify-content:space-between">
                                    <span style="font-size:11px;color:var(--color-text-muted)" x-text="'v' + mod.version"></span>
                                    <button class="btn btn--primary btn--sm"
                                            @click="installModule(mod)"
                                            style="font-size:11px">
                                        ⬇ Cài đặt
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </template>

        <!-- Empty uninstalled state after scan -->
        <template x-if="scanned && uninstalled.length === 0">
            <div class="card" style="margin-top:24px">
                <div class="card__body" style="text-align:center;color:var(--color-text-muted);font-size:13px;padding:24px">
                    ✅ Tất cả module trong thư mục đã được cài đặt.
                </div>
            </div>
        </template>
    </div>
</div>

<style>
.toggle-switch {
    position: relative;
    display: inline-block;
    width: 40px;
    height: 22px;
    cursor: pointer;
}
.toggle-switch input { opacity: 0; width: 0; height: 0; }
.toggle-switch__slider {
    position: absolute;
    inset: 0;
    background: #ccc;
    border-radius: 22px;
    transition: .2s;
}
.toggle-switch__slider::before {
    content: '';
    position: absolute;
    width: 16px; height: 16px;
    left: 3px; top: 3px;
    background: white;
    border-radius: 50%;
    transition: .2s;
}
.toggle-switch input:checked + .toggle-switch__slider { background: var(--color-primary, #4f46e5); }
.toggle-switch input:checked + .toggle-switch__slider::before { transform: translateX(18px); }
</style>

<script>
function moduleManager() {
    return {
        modules: [],
        uninstalled: [],
        loading: false,
        scanning: false,
        syncing: false,
        scanned: false,

        async init() {
            this.loading = true;
            const data = await apiGet('/api/admin/modules');
            if (data && data.status === 'success') {
                this.modules = data.data;
            }
            this.loading = false;
        },

        async toggleModule(module, enable) {
            const label = enable ? 'bật' : 'tắt';
            const confirmed = await showConfirm({
                title: (enable ? 'Bật' : 'Tắt') + ' module',
                message: `Bạn chắc chắn muốn ${label} module "${module.name}"?`,
                type: enable ? 'info' : 'warning',
                confirmText: enable ? 'Bật' : 'Tắt',
            });
            if (!confirmed) {
                // Revert checkbox
                module.is_enabled = !enable;
                return;
            }

            const data = await apiPut(`/api/admin/modules/${module.id}/toggle`);
            if (data && data.status === 'success') {
                module.is_enabled = enable;
                showToast('success', `Đã ${label} module "${module.name}".`);
            } else {
                module.is_enabled = !enable;
                showToast('error', data ? data.message : 'Có lỗi xảy ra.');
            }
        },

        async scanModules() {
            this.scanning = true;
            const data = await apiPost('/api/admin/modules/scan');
            this.scanning = false;
            this.scanned = true;
            if (data && data.status === 'success') {
                this.uninstalled = data.data;
                if (data.data.length > 0) {
                    showToast('info', `Tìm thấy ${data.data.length} module chưa cài.`);
                }
            } else {
                showToast('error', data ? data.message : 'Quét thất bại.');
            }
        },

        async installModule(mod) {
            const data = await apiPost(`/api/admin/modules/${mod.dir}/install`);
            if (data && data.status === 'success') {
                showToast('success', data.message);
                this.uninstalled = this.uninstalled.filter(m => m.slug !== mod.slug);
                await this.init();
            } else {
                showToast('error', data ? data.message : 'Cài đặt thất bại.');
            }
        },

        async syncCache() {
            this.syncing = true;
            const data = await apiPost('/api/admin/modules/sync-cache');
            this.syncing = false;
            if (data && data.status === 'success') {
                showToast('success', 'Đã đồng bộ Redis cache.');
            }
        },
    };
}
</script>

<?= $this->endSection() ?>
