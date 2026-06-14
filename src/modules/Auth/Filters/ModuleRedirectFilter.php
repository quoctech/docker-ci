<?php

namespace Modules\Auth\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Modules\SystemAdmin\Models\ModuleModel;

/**
 * ModuleRedirectFilter - Chặn trang web của module bị tắt.
 *
 * Dùng cho admin web pages (không phải API).
 * Khi module bị tắt → redirect /admin/modules thay vì trả JSON 503
 * (ModuleCheckFilter dùng cho API endpoints).
 *
 * Cách dùng trong Routes:
 *   ['filter' => 'module_redirect:vortex-engine']
 *   ['filter' => 'module_redirect:vortex-engine,another-module']
 */
class ModuleRedirectFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (empty($arguments)) {
            return null;
        }

        $moduleModel = new ModuleModel();

        foreach ($arguments as $slug) {
            if (! $moduleModel->isEnabled($slug)) {
                return redirect()->to('/admin/modules');
            }
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
