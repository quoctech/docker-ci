<?php

namespace Modules\Auth\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Modules\SystemAdmin\Models\ModuleModel;
use Modules\RoleManagement\Repositories\UserPermissionRepository;

/**
 * ModuleCheckFilter - Kiểm tra module có đang bật và user có quyền truy cập không.
 *
 * Flow:
 * 1. Kiểm tra module đang bật (Redis cache qua ModuleModel::isEnabled)
 * 2. Với workspace_admin: kiểm tra quyền CRUD thông qua UserPermissionRepository
 *    (JOIN từ user_applied_roles → role_module_permissions, có cache Redis).
 * 3. Super_admin luôn pass (đã được xử lý ở JWTAuthFilter).
 * 4. User (học sinh) không được truy cập module admin — trả 403.
 */
class ModuleCheckFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (empty($arguments)) {
            return null;
        }

        $moduleModel = new ModuleModel();

        // Bước 1: Module phải đang bật
        foreach ($arguments as $slug) {
            if (! $moduleModel->isEnabled($slug)) {
                return service('response')
                    ->setStatusCode(503)
                    ->setJSON(['status' => 'error', 'message' => 'Module không khả dụng.']);
            }
        }

        // Bước 2: Phân quyền cho workspace_admin (super_admin đã pass ở JWTAuthFilter)
        $authUser = $request->authUser ?? null;
        if (! $authUser || $authUser->role === 'super_admin') {
            return null;
        }

        if ($authUser->role !== 'workspace_admin') {
            return service('response')
                ->setStatusCode(403)
                ->setJSON(['status' => 'error', 'message' => 'Bạn không có quyền truy cập module này.']);
        }

        // Map HTTP method → cột quyền tương ứng
        $method = strtoupper($request->getMethod());
        $permissionColumn = match ($method) {
            'POST'            => 'can_write',
            'PUT', 'PATCH'    => 'can_edit',
            'DELETE'          => 'can_delete',
            default           => 'can_read',
        };

        $permRepo = new UserPermissionRepository();
        foreach ($arguments as $slug) {
            if (! $permRepo->hasPermission($authUser->sub, $slug)) {
                return service('response')
                    ->setStatusCode(403)
                    ->setJSON(['status' => 'error', 'message' => 'Bạn không có quyền truy cập module này.']);
            }

            if ($permissionColumn !== 'can_read'
                && ! $permRepo->hasGranularPermission($authUser->sub, $slug, $permissionColumn)
            ) {
                $messages = [
                    'can_write'  => 'Bạn không có quyền tạo mới trong module này.',
                    'can_edit'   => 'Bạn không có quyền chỉnh sửa trong module này.',
                    'can_delete' => 'Bạn không có quyền xóa trong module này.',
                ];
                return service('response')
                    ->setStatusCode(403)
                    ->setJSON(['status' => 'error', 'message' => $messages[$permissionColumn]]);
            }
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}