<?php

namespace Modules\SystemAdmin\Controllers;

use App\Controllers\ApiController;
use CodeIgniter\HTTP\ResponseInterface;
use Modules\SystemAdmin\Repositories\UserModulePermissionRepository;

class UserModulePermissionController extends ApiController
{
    private UserModulePermissionRepository $permRepo;

    public function __construct()
    {
        $this->permRepo = new UserModulePermissionRepository();
    }

    /** GET /api/admin/users/:uuid/modules */
    public function getUserModules(string $userUuid): ResponseInterface
    {
        $db = \Config\Database::connect();

        $allModules  = $db->query(
            "SELECT slug, name, is_enabled FROM modules ORDER BY name ASC"
        )->getResultObject();

        $grantedRows = $this->permRepo->getByUser($userUuid);
        $granted     = array_column($grantedRows, 'module_slug');

        $result = [];
        foreach ($allModules as $m) {
            $result[] = [
                'slug'    => $m->slug,
                'name'    => $m->name,
                'enabled' => (bool) $m->is_enabled,
                'granted' => in_array($m->slug, $granted),
            ];
        }

        return $this->success($result);
    }

    /** PUT /api/admin/users/:uuid/modules */
    public function setUserModules(string $userUuid): ResponseInterface
    {
        $auth = $this->getAuthUser();

        $body        = $this->request->getJSON(true) ?? [];
        $moduleSlugs = $body['modules'] ?? [];

        if (! is_array($moduleSlugs)) {
            return $this->error('Trường modules phải là mảng.', 422);
        }

        // Validate slugs against the modules table
        if (! empty($moduleSlugs)) {
            $db           = \Config\Database::connect();
            $placeholders = implode(',', array_fill(0, count($moduleSlugs), '?'));
            $valid        = array_column(
                $db->query("SELECT slug FROM modules WHERE slug IN ({$placeholders})", $moduleSlugs)->getResultObject(),
                'slug'
            );
            $moduleSlugs  = array_values(array_intersect($moduleSlugs, $valid));
        }

        $this->permRepo->setPermissions($userUuid, $moduleSlugs, $auth->sub);

        return $this->success(null, 'Đã cập nhật phân quyền module.');
    }
}
