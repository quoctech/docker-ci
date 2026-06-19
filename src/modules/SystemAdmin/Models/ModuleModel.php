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
     * Lấy tất cả sidebar items của các module đang bật, đã sắp xếp theo sort_order.
     * Dùng trong sidebar.php — không cần sửa view khi thêm module mới,
     * chỉ cần insert vào bảng module_sidebar_items trong migration của module đó.
     */
    public function getSidebarItems(): array
    {
        $db = \Config\Database::connect();
        return $db->table('module_sidebar_items msi')
            ->select('msi.module_slug, msi.group_label, msi.label, msi.url, msi.icon, msi.allowed_roles, msi.match_exact, msi.sort_order', false)
            ->join('modules m', 'm.slug = msi.module_slug', 'inner', false)
            ->where('m.is_enabled', 1)
            ->orderBy('msi.sort_order', 'ASC')
            ->get()
            ->getResultObject();
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
