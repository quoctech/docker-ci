<?php

namespace Modules\SchoolManagement\Controllers;

use App\Controllers\ApiController;
use App\Libraries\SystemLogger;
use CodeIgniter\HTTP\ResponseInterface;
use Modules\SchoolManagement\Repositories\CenterRepository;

class AdminCenterController extends ApiController
{
    private CenterRepository $repo;

    public function __construct()
    {
        $this->repo = new CenterRepository();
    }

    private function requireAdmin(): ?ResponseInterface
    {
        $auth = $this->getAuthUser();
        if (! in_array($auth->role, ['workspace_admin', 'super_admin'])) {
            return $this->error('Không có quyền truy cập.', 403);
        }
        return null;
    }

    /** GET /api/school-management/centers */
    public function index(): ResponseInterface
    {
        if ($err = $this->requireAdmin()) return $err;
        return $this->success($this->repo->list());
    }

    /** POST /api/school-management/centers */
    public function create(): ResponseInterface
    {
        if ($err = $this->requireAdmin()) return $err;

        $rules = [
            'name'  => 'required|max_length[100]',
            'phone' => 'permit_empty|max_length[20]',
            'email' => 'permit_empty|valid_email|max_length[100]',
        ];
        if (! $this->validate($rules)) {
            return $this->error('Dữ liệu không hợp lệ.', 422, $this->validator->getErrors());
        }

        $center = $this->repo->create([
            'name'    => $this->request->getPost('name'),
            'address' => $this->request->getPost('address'),
            'phone'   => $this->request->getPost('phone'),
            'email'   => $this->request->getPost('email'),
        ]);

        if (! $center) {
            return $this->error('Không thể tạo trung tâm.', 500);
        }

        SystemLogger::info('Tạo trung tâm: ' . $center->name, ['center_uuid' => $center->uuid]);
        return $this->success($center, 'Tạo trung tâm thành công.', 201);
    }

    /** GET /api/school-management/centers/:uuid */
    public function show(string $uuid): ResponseInterface
    {
        if ($err = $this->requireAdmin()) return $err;

        $center = $this->repo->findByUuid($uuid);
        return $center
            ? $this->success($center)
            : $this->error('Không tìm thấy trung tâm.', 404);
    }

    /** PUT /api/school-management/centers/:uuid */
    public function update(string $uuid): ResponseInterface
    {
        if ($err = $this->requireAdmin()) return $err;

        $center = $this->repo->findByUuid($uuid);
        if (! $center) return $this->error('Không tìm thấy trung tâm.', 404);

        $input = $this->request->getRawInput();
        $name  = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            return $this->error('Tên trung tâm không được để trống.', 422);
        }

        $data = ['name' => $name];
        foreach (['address', 'phone', 'email'] as $field) {
            if (array_key_exists($field, $input)) {
                $data[$field] = $input[$field] !== '' ? $input[$field] : null;
            }
        }

        $this->repo->update($center->id, $data);

        SystemLogger::info('Cập nhật trung tâm: ' . $center->name, ['center_uuid' => $uuid]);
        return $this->success($this->repo->findByUuid($uuid), 'Cập nhật thành công.');
    }

    /** DELETE /api/school-management/centers/:uuid */
    public function delete(string $uuid): ResponseInterface
    {
        if ($err = $this->requireAdmin()) return $err;

        $center = $this->repo->findByUuid($uuid);
        if (! $center) return $this->error('Không tìm thấy trung tâm.', 404);

        $branchCount = $this->repo->countActiveBranches($center->id);
        if ($branchCount > 0) {
            return $this->error(
                "Không thể xóa trung tâm còn {$branchCount} chi nhánh đang hoạt động. Vui lòng chuyển hoặc xóa các chi nhánh trước.",
                409
            );
        }

        $this->repo->deactivate($center->id);
        SystemLogger::info('Xóa trung tâm: ' . $center->name, ['center_uuid' => $uuid]);
        return $this->success(null, 'Đã xóa trung tâm.');
    }
}
