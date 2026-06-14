<?php

namespace Modules\SystemAdmin\Controllers;

use App\Controllers\ApiController;
use CodeIgniter\HTTP\ResponseInterface;
use Modules\SystemAdmin\Repositories\ModuleRepository;

/**
 * ModuleController - Quản lý module hệ thống.
 *
 * Xử lý request/response. Logic database ủy thác cho Repository.
 */
class ModuleController extends ApiController
{
    private ModuleRepository $moduleRepo;

    public function __construct()
    {
        $this->moduleRepo = new ModuleRepository();
    }

    /**
     * GET /api/admin/modules
     */
    public function index(): ResponseInterface
    {
        $modules = $this->moduleRepo->getAll();

        return $this->success(array_map(fn($m) => [
            'id'          => (int) $m->id,
            'slug'        => $m->slug,
            'name'        => $m->name,
            'description' => $m->description,
            'is_enabled'  => (bool) $m->is_enabled,
            'is_core'     => (bool) $m->is_core,
            'version'     => $m->version,
        ], $modules));
    }

    /**
     * PUT /api/admin/modules/(:num)/toggle
     */
    public function toggle(int $id): ResponseInterface
    {
        $module = $this->moduleRepo->findById($id);

        if (! $module) {
            return $this->error('Không tìm thấy module.', 404);
        }

        if ($module->is_core) {
            return $this->error('Module Core không thể tắt.', 403);
        }

        $newState = ! (bool) $module->is_enabled;
        $this->moduleRepo->toggle($id, $newState);

        return $this->success([
            'slug'       => $module->slug,
            'is_enabled' => $newState,
        ], $newState ? 'Đã bật module.' : 'Đã tắt module.');
    }

    /**
     * POST /api/admin/modules/sync-cache
     */
    public function syncCache(): ResponseInterface
    {
        $this->moduleRepo->syncAllToRedis();

        return $this->success(null, 'Đã đồng bộ cache Redis.');
    }
}
