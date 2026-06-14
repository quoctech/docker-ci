<?php

namespace Modules\SystemLog\Controllers;

use App\Controllers\ApiController;
use Modules\SystemLog\Repositories\SystemLogRepository;

class AdminSystemLogController extends ApiController
{
    private SystemLogRepository $repo;

    public function __construct()
    {
        $this->repo = new SystemLogRepository();
    }

    /**
     * GET /api/admin/system-logs
     */
    public function index(): \CodeIgniter\HTTP\ResponseInterface
    {
        $filters = [
            'level'   => $this->request->getGet('level') ?? '',
            'channel' => $this->request->getGet('channel') ?? '',
            'seen'    => $this->request->getGet('seen') ?? '',
            'q'       => $this->request->getGet('q') ?? '',
        ];
        $page    = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = min(100, max(10, (int) ($this->request->getGet('per_page') ?? 30)));

        $result = $this->repo->getList($filters, $page, $perPage);

        return $this->respond([
            'status' => 'success',
            'data'   => [
                'records'    => $result['records'],
                'pagination' => [
                    'total'       => $result['total'],
                    'page'        => $result['page'],
                    'per_page'    => $result['per_page'],
                    'total_pages' => $result['total_pages'],
                ],
            ],
        ]);
    }

    /**
     * GET /api/admin/system-logs/(:num)
     */
    public function show(int $id): \CodeIgniter\HTTP\ResponseInterface
    {
        $log = $this->repo->getById($id);
        if (!$log) {
            return $this->respond(['status' => 'error', 'message' => 'Không tìm thấy'], 404);
        }
        $this->repo->markSeen($id);
        return $this->respond(['status' => 'success', 'data' => $log]);
    }

    /**
     * POST /api/admin/system-logs/mark-seen
     */
    public function markAllSeen(): \CodeIgniter\HTTP\ResponseInterface
    {
        $this->repo->markAllSeen();
        return $this->respond(['status' => 'success', 'message' => 'Đã đánh dấu tất cả đã xem']);
    }

    /**
     * DELETE /api/admin/system-logs/(:num)
     */
    public function delete(int $id): \CodeIgniter\HTTP\ResponseInterface
    {
        $this->repo->delete($id);
        return $this->respond(['status' => 'success', 'message' => 'Đã xóa log']);
    }

    /**
     * DELETE /api/admin/system-logs
     */
    public function clearAll(): \CodeIgniter\HTTP\ResponseInterface
    {
        $this->repo->clearAll();
        return $this->respond(['status' => 'success', 'message' => 'Đã xóa toàn bộ log']);
    }

    /**
     * GET /api/admin/system-logs/stats
     */
    public function stats(): \CodeIgniter\HTTP\ResponseInterface
    {
        $unseen = $this->repo->getUnseenCount();
        return $this->respond(['status' => 'success', 'data' => ['unseen' => $unseen]]);
    }
}
