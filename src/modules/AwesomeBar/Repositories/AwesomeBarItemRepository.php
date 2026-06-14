<?php

namespace Modules\AwesomeBar\Repositories;

use Modules\AwesomeBar\Models\AwesomeBarItemModel;

class AwesomeBarItemRepository
{
    private AwesomeBarItemModel $model;

    public function __construct()
    {
        $this->model = new AwesomeBarItemModel();
    }

    public function getActiveForModules(array $enabledSlugs): array
    {
        return $this->model->getActiveForModules($enabledSlugs);
    }

    /**
     * Đăng ký item Awesome Bar khi cài module mới.
     * Gọi trong migration hoặc install script của module.
     */
    public function register(array $item): void
    {
        $existing = $this->model->where('title', $item['title'])
                                ->where('url', $item['url'] ?? null)
                                ->first();
        if ($existing) {
            $this->model->update($existing->id, $item);
        } else {
            $this->model->insert($item);
        }
    }

    public function removeByModuleSlug(string $moduleSlug): void
    {
        $this->model->where('module_slug', $moduleSlug)->delete();
    }
}
