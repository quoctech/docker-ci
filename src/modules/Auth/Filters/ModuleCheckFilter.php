<?php

namespace Modules\Auth\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Modules\SystemAdmin\Models\ModuleModel;

/**
 * ModuleCheckFilter - Dynamic routing dựa trên trạng thái module.
 *
 * Kiểm tra module có đang bật không trước khi cho request đi tiếp.
 * Nếu module bị tắt → trả 503 Service Unavailable.
 *
 * Flow: Request → ModuleCheckFilter → Redis cache (fast) → DB fallback → Response
 *
 * Cách dùng trong Routes:
 * - ['filter' => 'module_check:game-engine']           → Check 1 module
 * - ['filter' => 'module_check:game-engine,workspace'] → Check nhiều module
 *
 * Admin bật/tắt module qua API → Redis sync → request tiếp theo tự động apply.
 */
class ModuleCheckFilter implements FilterInterface
{
    /**
     * Kiểm tra tất cả module yêu cầu có đang enabled không.
     *
     * @param RequestInterface $request   HTTP request
     * @param array|null       $arguments Danh sách module slug cần check
     * @return ResponseInterface|null null = pass, 503 = module disabled
     */
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
                    ->setJSON([
                        'status'  => 'error',
                        'message' => 'This service is currently unavailable.',
                    ]);
            }
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
