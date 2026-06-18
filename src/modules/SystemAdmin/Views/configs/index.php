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
                        <!-- Timezone select -->
                        <template x-if="config.type === 'timezone'">
                            <select class="form-input" style="flex:1" x-model="config._editValue">
                                <option value="Asia/Ho_Chi_Minh">Asia/Ho_Chi_Minh (UTC+7, Việt Nam)</option>
                                <option value="Asia/Bangkok">Asia/Bangkok (UTC+7, Thái Lan)</option>
                                <option value="Asia/Singapore">Asia/Singapore (UTC+8)</option>
                                <option value="Asia/Kuala_Lumpur">Asia/Kuala_Lumpur (UTC+8)</option>
                                <option value="Asia/Jakarta">Asia/Jakarta (UTC+7, Indonesia)</option>
                                <option value="Asia/Manila">Asia/Manila (UTC+8, Philippines)</option>
                                <option value="Asia/Tokyo">Asia/Tokyo (UTC+9, Nhật Bản)</option>
                                <option value="Asia/Seoul">Asia/Seoul (UTC+9, Hàn Quốc)</option>
                                <option value="Asia/Shanghai">Asia/Shanghai (UTC+8, Trung Quốc)</option>
                                <option value="Asia/Taipei">Asia/Taipei (UTC+8, Đài Loan)</option>
                                <option value="Asia/Kolkata">Asia/Kolkata (UTC+5:30, Ấn Độ)</option>
                                <option value="Asia/Dubai">Asia/Dubai (UTC+4)</option>
                                <option value="Europe/London">Europe/London (UTC+0/+1)</option>
                                <option value="Europe/Paris">Europe/Paris (UTC+1/+2)</option>
                                <option value="America/New_York">America/New_York (UTC-5/-4)</option>
                                <option value="America/Los_Angeles">America/Los_Angeles (UTC-8/-7)</option>
                                <option value="UTC">UTC (UTC+0)</option>
                            </select>
                        </template>
                        <!-- Generic text/number input -->
                        <template x-if="config.type !== 'timezone'">
                            <input class="form-input"
                                   style="flex:1"
                                   :type="config.type === 'integer' ? 'number' : 'text'"
                                   :placeholder="config.description || ''"
                                   x-model="config._editValue"
                                   @keydown.enter="saveConfig(config)">
                        </template>
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

<?= $this->section('scripts') ?>
<script src="/assets/modules/SystemAdmin/system-admin.js"></script>
<?= $this->endSection() ?>

<?= $this->endSection() ?>
