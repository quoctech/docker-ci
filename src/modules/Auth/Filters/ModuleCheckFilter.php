<?php

namespace Modules\Auth\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Modules\SystemAdmin\Models\ModuleModel;
use Modules\SystemAdmin\Repositories\UserModulePermissionRepository;

class ModuleCheckFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (empty($arguments)) {
            return null;
        }

        $moduleModel = new ModuleModel();

        foreach ($arguments as $slug) {
            if (! $moduleModel->isEnabled($slug)) {
                return service('response')
                    ->setStatusCode(503)
                    ->setJSON(['status' => 'error', 'message' => 'Module không khả dụng.']);
            }
        }

        // Chỉ super_admin và workspace_admin (có quyền) được truy cập module APIs
        $authUser = $request->authUser ?? null;
        if ($authUser && $authUser->role !== 'super_admin') {
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
                default           => 'can_read',  // GET, HEAD, OPTIONS
            };

            $permRepo = new UserModulePermissionRepository();
            foreach ($arguments as $slug) {
                // Luôn kiểm tra can_read trước
                if (! $permRepo->hasPermission($authUser->sub, $slug)) {
                    return service('response')
                        ->setStatusCode(403)
                        ->setJSON(['status' => 'error', 'message' => 'Bạn không có quyền truy cập module này.']);
                }
                // Với POST/PUT/DELETE, kiểm tra thêm quyền tương ứng
                if ($permissionColumn !== 'can_read' && ! $permRepo->hasGranularPermission($authUser->sub, $slug, $permissionColumn)) {
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
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
