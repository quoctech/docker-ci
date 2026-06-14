<?php

namespace Modules\SystemAdmin\Repositories;

use Modules\SystemAdmin\Models\ModuleModel;

/**
 * ModuleRepository - Tầng truy xuất dữ liệu cho bảng modules.
 */
class ModuleRepository
{
    private ModuleModel $model;

    public function __construct()
    {
        $this->model = new ModuleModel();
    }

    public function findById(int $id): ?object
    {
        return $this->model->find($id);
    }

    public function getAll(): array
    {
        return $this->model->getAllModules();
    }

    public function toggle(int $id, bool $enable): bool
    {
        return $this->model->toggleModule($id, $enable);
    }

    public function isEnabled(string $slug): bool
    {
        return $this->model->isEnabled($slug);
    }

    public function syncAllToRedis(): void
    {
        $this->model->syncAllToRedis();
    }

    public function getEnabledWithAdminUrl(): array
    {
        return $this->model->getEnabledWithAdminUrl();
    }

    public function findBySlug(string $slug): ?object
    {
        return $this->model->where('slug', $slug)->first();
    }

    public function install(array $data): void
    {
        $this->model->insert($data);
    }
}
