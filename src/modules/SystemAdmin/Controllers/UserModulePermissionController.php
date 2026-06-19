<?php

namespace Modules\SystemAdmin\Controllers;

use App\Controllers\ApiController;
use CodeIgniter\HTTP\ResponseInterface;
use Modules\SystemAdmin\Repositories\UserModulePermissionRepository;

class UserModulePermissionController extends ApiController
{
    private UserModulePermissionRepository $permRepo;

    /** Slugs không được phép cấp — core system modules */
    private const NON_GRANTABLE = ['auth', 'system-admin', 'system-log'];

    public function __construct()
    {
        $this->permRepo = new UserModulePermissionRepository();
    }

    /** GET /api/admin/users/:uuid/modules */
    public function getUserModules(string $userUuid): ResponseInterface
    {
        $db = \Config\Database::connect();

        $allModules = $db->table('modules')
            ->select('slug, name, is_enabled')
            ->whereNotIn('slug', self::NON_GRANTABLE)
            ->orderBy('sort_order', 'ASC')
            ->get()
            ->getResultObject();

        $permsMap = $this->permRepo->getPermissionsMap($userUuid);

        $result = [];
        foreach ($allModules as $m) {
            $granted = $permsMap[$m->slug] ?? null;
            $result[] = [
                'slug'       => $m->slug,
                'name'       => $m->name,
                'enabled'    => (bool) $m->is_enabled,
                'can_read'   => (bool) ($granted['can_read']   ?? false),
                'can_write'  => (bool) ($granted['can_write']  ?? false),
                'can_edit'   => (bool) ($granted['can_edit']   ?? false),
                'can_delete' => (bool) ($granted['can_delete'] ?? false),
            ];
        }

        return $this->success($result);
    }

    /** PUT /api/admin/users/:uuid/modules */
    public function setUserModules(string $userUuid): ResponseInterface
    {
        $auth = $this->getAuthUser();

        $body    = $this->request->getJSON(true) ?? [];
        $modules = $body['modules'] ?? [];

        if (! is_array($modules)) {
            return $this->error('Trường modules phải là mảng.', 422);
        }

        // Lấy danh sách slug hợp lệ (không phải core, đang tồn tại trong DB)
        $db = \Config\Database::connect();
        $validSlugs = array_column(
            $db->table('modules')
                ->select('slug')
                ->whereNotIn('slug', self::NON_GRANTABLE)
                ->get()
                ->getResultArray(),
            'slug'
        );

        // Lọc và chuẩn hóa
        $modulePermissions = [];
        foreach ($modules as $m) {
            $slug = $m['slug'] ?? '';
            if (! in_array($slug, $validSlugs, true)) continue;
            if (empty($m['can_read'])) continue; // phải có read mới lưu

            $modulePermissions[] = [
                'slug'       => $slug,
                'can_read'   => 1,
                'can_write'  => empty($m['can_write'])  ? 0 : 1,
                'can_edit'   => empty($m['can_edit'])   ? 0 : 1,
                'can_delete' => empty($m['can_delete']) ? 0 : 1,
            ];
        }

        $this->permRepo->setPermissions($userUuid, $modulePermissions, $auth->sub);

        return $this->success(null, 'Đã cập nhật phân quyền module.');
    }
}
