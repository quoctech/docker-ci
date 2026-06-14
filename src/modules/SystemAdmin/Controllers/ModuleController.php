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
            'admin_url'   => $m->admin_url ?? null,
            'icon'        => $m->icon ?? '🧩',
        ], $modules));
    }

    /**
     * POST /api/admin/modules/scan
     *
     * Quét thư mục modules/, so sánh với DB, trả về danh sách chưa cài.
     */
    public function scan(): ResponseInterface
    {
        $modulesPath = ROOTPATH . 'modules' . DIRECTORY_SEPARATOR;

        if (! is_dir($modulesPath)) {
            return $this->error('Thư mục modules không tồn tại.', 500);
        }

        $installed   = array_column($this->moduleRepo->getAll(), null, 'slug');
        $uninstalled = [];

        foreach (scandir($modulesPath) as $dir) {
            if ($dir === '.' || $dir === '..') {
                continue;
            }

            $jsonPath = $modulesPath . $dir . DIRECTORY_SEPARATOR . 'module.json';

            if (! is_file($jsonPath)) {
                continue;
            }

            $meta = json_decode(file_get_contents($jsonPath), true);

            if (! is_array($meta) || empty($meta['slug'])) {
                continue;
            }

            if (! isset($installed[$meta['slug']])) {
                $uninstalled[] = [
                    'slug'        => $meta['slug'],
                    'name'        => $meta['name'] ?? $dir,
                    'description' => $meta['description'] ?? '',
                    'version'     => $meta['version'] ?? '1.0.0',
                    'is_core'     => (bool) ($meta['is_core'] ?? false),
                    'admin_url'   => $meta['admin_url'] ?? null,
                    'icon'        => $meta['icon'] ?? '🧩',
                    'sort_order'  => (int) ($meta['sort_order'] ?? 99),
                    'dir'         => $dir,
                ];
            }
        }

        return $this->success($uninstalled, count($uninstalled) . ' module chưa cài được tìm thấy.');
    }

    /**
     * POST /api/admin/modules/(:segment)/install
     *
     * Cài đặt module từ module.json vào DB.
     */
    public function install(string $dirName): ResponseInterface
    {
        $jsonPath = ROOTPATH . 'modules' . DIRECTORY_SEPARATOR . $dirName . DIRECTORY_SEPARATOR . 'module.json';

        if (! is_file($jsonPath)) {
            return $this->error('Không tìm thấy module.json trong thư mục: ' . $dirName, 404);
        }

        $meta = json_decode(file_get_contents($jsonPath), true);

        if (! is_array($meta) || empty($meta['slug'])) {
            return $this->error('module.json không hợp lệ.', 422);
        }

        if ($this->moduleRepo->findBySlug($meta['slug'])) {
            return $this->error('Module "' . $meta['slug'] . '" đã được cài đặt.', 409);
        }

        $now = date('Y-m-d H:i:s');

        $this->moduleRepo->install([
            'slug'        => $meta['slug'],
            'name'        => $meta['name'] ?? $dirName,
            'description' => $meta['description'] ?? '',
            'is_enabled'  => 0,
            'is_core'     => (int) ($meta['is_core'] ?? 0),
            'version'     => $meta['version'] ?? '1.0.0',
            'sort_order'  => (int) ($meta['sort_order'] ?? 99),
            'admin_url'   => $meta['admin_url'] ?? null,
            'icon'        => $meta['icon'] ?? '🧩',
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);

        $this->moduleRepo->syncAllToRedis();

        return $this->success(['slug' => $meta['slug']], 'Đã cài đặt module "' . ($meta['name'] ?? $dirName) . '". Hãy bật module để sử dụng.', 201);
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
