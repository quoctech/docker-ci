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
            // workspace_admin phải có quyền cụ thể trên module
            $permRepo = new UserModulePermissionRepository();
            foreach ($arguments as $slug) {
                if (! $permRepo->hasPermission($authUser->sub, $slug)) {
                    return service('response')
                        ->setStatusCode(403)
                        ->setJSON(['status' => 'error', 'message' => 'Bạn không có quyền truy cập module này.']);
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
