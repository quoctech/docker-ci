<?php

namespace Modules\SystemAdmin\Models;

use CodeIgniter\Model;
use App\Libraries\RedisService;

class ModuleModel extends Model
{
    protected $table         = 'modules';
    protected $primaryKey    = 'id';
    protected $useAutoIncrement = true;
    protected $returnType    = 'object';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'slug',
        'name',
        'description',
        'is_enabled',
        'is_core',
        'version',
        'sort_order',
        'admin_url',
        'icon',
    ];

    protected $validationRules = [
        'slug' => 'required|alpha_dash|max_length[60]|is_unique[modules.slug,id,{id}]',
        'name' => 'required|max_length[100]',
    ];

    /**
     * Get all modules ordered by sort_order.
     */
    public function getAllModules(): array
    {
        return $this->orderBy('sort_order', 'ASC')->findAll();
    }

    /**
     * Toggle module enabled/disabled and sync to Redis.
     */
    public function toggleModule(int $id, bool $enable): bool
    {
        $module = $this->find($id);

        if (! $module) {
            return false;
        }

        // Core modules cannot be disabled
        if ($module->is_core && ! $enable) {
            return false;
        }

        $this->update($id, ['is_enabled' => $enable ? 1 : 0]);
        RedisService::setModuleStatus($module->slug, $enable);

        return true;
    }

    /**
     * Check if a module is enabled (Redis-first, DB fallback).
     */
    public function isEnabled(string $slug): bool
    {
        $cached = RedisService::getModuleStatus($slug);

        if ($cached !== null) {
            return $cached;
        }

        $module = $this->where('slug', $slug)->first();
        $enabled = $module ? (bool) $module->is_enabled : false;

        RedisService::setModuleStatus($slug, $enabled);

        return $enabled;
    }

    /**
     * Lấy các module đang bật có admin_url để hiển thị trên sidebar.
     */
    public function getEnabledWithAdminUrl(): array
    {
        return $this->where('is_enabled', 1)
                    ->where('is_core', 0)
                    ->where('admin_url IS NOT NULL', null, false)
                    ->orderBy('sort_order', 'ASC')
                    ->findAll();
    }

    /**
     * Sync all module statuses to Redis.
     */
    public function syncAllToRedis(): void
    {
        $modules = $this->findAll();
        $map     = [];

        foreach ($modules as $module) {
            $map[$module->slug] = (bool) $module->is_enabled;
        }

        RedisService::syncModuleStatuses($map);
    }
}
