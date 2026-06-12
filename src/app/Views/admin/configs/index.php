<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Cài đặt Website<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>Hệ thống / Cài đặt Website<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div x-data="configManager()" x-init="loadConfigs()">
    <div style="margin-bottom:24px">
        <h1 class="content__title">Cài đặt Website</h1>
        <p class="content__subtitle">Quản lý thông tin và cấu hình chung của hệ thống.</p>
    </div>

    <!-- Group tabs -->
    <div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap">
        <template x-for="group in groups" :key="group">
            <button class="btn btn--sm"
                    :class="activeGroup === group ? 'btn--primary' : 'btn--secondary'"
                    @click="activeGroup = group"
                    x-text="groupLabels[group] || group"></button>
        </template>
    </div>

    <!-- Loading -->
    <div x-show="loading" style="text-align:center;padding:40px;color:var(--color-text-muted)">
        Đang tải...
    </div>

    <!-- Config list -->
    <div class="card" x-show="!loading">
        <div class="card__header">
            <h3 x-text="groupLabels[activeGroup] || activeGroup"></h3>
        </div>
        <div class="card__body" style="padding:0">
            <template x-for="config in filteredConfigs" :key="config.key">
                <div style="padding:16px 24px;border-bottom:1px solid var(--color-border)">
                    <!-- Label -->
                    <div style="margin-bottom:8px">
                        <div style="font-size:14px;font-weight:500" x-text="config.description || config.key"></div>
                        <div style="font-size:11px;color:var(--color-text-muted)" x-text="'key: ' + config.key"></div>
                    </div>
                    <!-- Input + Save -->
                    <div style="display:flex;gap:8px;align-items:center">
                        <input class="form-input"
                               style="flex:1"
                               :type="config.type === 'integer' ? 'number' : 'text'"
                               :placeholder="config.description || ''"
                               x-model="config._editValue"
                               @keydown.enter="saveConfig(config)">
                        <button class="btn btn--primary btn--sm"
                                :disabled="config._editValue === config.value || config._saving"
                                @click="saveConfig(config)">
                            <span x-text="config._saving ? '...' : 'Lưu'"></span>
                        </button>
                    </div>
                    <!-- Saved feedback -->
                    <div x-show="config._saved" style="font-size:12px;color:var(--color-success);margin-top:4px">
                        ✓ Đã lưu
                    </div>
                </div>
            </template>

            <div x-show="filteredConfigs.length === 0 && !loading"
                 style="padding:40px;text-align:center;color:var(--color-text-muted)">
                Nhóm này chưa có cấu hình nào.
            </div>
        </div>
    </div>
</div>

<script>
function configManager() {
    return {
        configs: [],
        groups: [],
        activeGroup: 'general',
        loading: false,
        groupLabels: {
            general: '⚙ Cài đặt chung',
            contact: '📞 Liên hệ'
        },

        get filteredConfigs() {
            return this.configs.filter(c => c.group === this.activeGroup);
        },

        async loadConfigs() {
            this.loading = true;
            const data = await apiGet('/api/admin/configs');
            if (data && data.status === 'success') {
                // Chỉ lấy nhóm general và contact
                const allowedGroups = ['general', 'contact'];
                this.groups = Object.keys(data.data).filter(g => allowedGroups.includes(g));
                this.configs = [];
                for (const [group, items] of Object.entries(data.data)) {
                    if (!allowedGroups.includes(group)) continue;
                    items.forEach(item => {
                        // _editValue: giá trị đang edit trong input
                        // _saving: đang gọi API
                        // _saved: vừa lưu thành công (feedback tạm)
                        item._editValue = item.value || '';
                        item._saving = false;
                        item._saved = false;
                        this.configs.push(item);
                    });
                }
            }
            this.loading = false;
        },

        async saveConfig(config) {
            // Không gọi API nếu giá trị không đổi
            if (config._editValue === config.value) return;

            config._saving = true;
            config._saved = false;

            // Gửi PUT request với body urlencoded
            const token = getToken();
            try {
                const res = await fetch('/api/admin/configs/' + config.key, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'Authorization': 'Bearer ' + token
                    },
                    body: 'value=' + encodeURIComponent(config._editValue)
                });

                if (res.status === 401) {
                    localStorage.removeItem('access_token');
                    window.location.href = '/admin/login';
                    return;
                }

                const data = await res.json();

                if (data.status === 'success') {
                    // Cập nhật giá trị gốc = giá trị mới (nút Lưu sẽ disable)
                    config.value = config._editValue;
                    config._saved = true;
                    showToast('success', 'Đã lưu: ' + (config.description || config.key));

                    // Ẩn feedback sau 3 giây
                    setTimeout(() => { config._saved = false; }, 3000);
                } else {
                    showToast('error', data.message || 'Lưu thất bại');
                }
            } catch (e) {
                showToast('error', 'Lỗi kết nối');
            }

            config._saving = false;
        }
    };
}
</script>

<?= $this->endSection() ?>
