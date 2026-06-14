<?php

namespace Modules\SystemAdmin\Repositories;

use Modules\SystemAdmin\Models\SiteConfigModel;

/**
 * SiteConfigRepository - Tầng truy xuất dữ liệu cho bảng site_configs.
 */
class SiteConfigRepository
{
    private SiteConfigModel $model;

    public function __construct()
    {
        $this->model = new SiteConfigModel();
    }

    public function getValue(string $key, mixed $default = null): mixed
    {
        return $this->model->getValue($key, $default);
    }

    public function setValue(string $key, mixed $value, string $group = 'general', string $type = 'string', ?string $description = null): void
    {
        $this->model->setValue($key, $value, $group, $type, $description);
    }

    public function getAllGrouped(): array
    {
        return $this->model->getAllGrouped();
    }

    public function findByKey(string $key): ?object
    {
        return $this->model->where('key', $key)->first();
    }

    public function delete(int $id): void
    {
        $this->model->delete($id);
    }

    public function invalidateCache(): void
    {
        $this->model->invalidateCache();
    }
}
