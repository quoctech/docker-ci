<?php

namespace Modules\SchoolManagement\Controllers;

use App\Controllers\ApiController;
use App\Libraries\SystemLogger;
use CodeIgniter\HTTP\ResponseInterface;
use Modules\SchoolManagement\Repositories\BranchRepository;
use Modules\SchoolManagement\Repositories\CenterRepository;

class AdminBranchController extends ApiController
{
    private BranchRepository $repo;
    private CenterRepository $centerRepo;

    public function __construct()
    {
        $this->repo       = new BranchRepository();
        $this->centerRepo = new CenterRepository();
    }

    private function requireAdmin(): ?ResponseInterface
    {
        $auth = $this->getAuthUser();
        if (! in_array($auth->role, ['workspace_admin', 'super_admin'])) {
            return $this->error('Không có quyền truy cập.', 403);
        }
        return null;
    }

    /** GET /api/school-management/branches */
    public function index(): ResponseInterface
    {
        if ($err = $this->requireAdmin()) return $err;
        return $this->success($this->repo->list());
    }

    /** POST /api/school-management/branches */
    public function create(): ResponseInterface
    {
        if ($err = $this->requireAdmin()) return $err;

        $rules = [
            'name'    => 'required|max_length[100]',
            'address' => 'required|max_length[500]',
            'phone'   => 'required|max_length[20]',
            'email'   => 'required|valid_email|max_length[100]',
            'manager' => 'required|max_length[100]',
        ];
        if (! $this->validate($rules)) {
            return $this->error('Dữ liệu không hợp lệ.', 422, $this->validator->getErrors());
        }

        $centerId   = null;
        $centerUuid = $this->request->getPost('center_uuid');
        if ($centerUuid) {
            $center = $this->centerRepo->findByUuid($centerUuid);
            if (! $center) return $this->error('Trung tâm không tồn tại.', 404);
            $centerId = $center->id;
        }

        $name = $this->request->getPost('name');
        if ($this->repo->nameExistsInScope($name, $centerId)) {
            return $this->error('Tên chi nhánh đã tồn tại trong cùng tổ chức. Vui lòng chọn tên khác.', 409);
        }

        $branch = $this->repo->create([
            'center_id' => $centerId,
            'name'      => $name,
            'address'   => $this->request->getPost('address'),
            'phone'     => $this->request->getPost('phone'),
            'email'     => $this->request->getPost('email'),
            'manager'   => $this->request->getPost('manager'),
        ]);

        if (! $branch) {
            SystemLogger::error('Không thể tạo chi nhánh', null, ['name' => $name]);
            return $this->error('Không thể tạo chi nhánh.', 500);
        }

        SystemLogger::info('Tạo chi nhánh: ' . $branch->name, ['branch_uuid' => $branch->uuid]);
        return $this->success($branch, 'Tạo chi nhánh thành công.', 201);
    }

    /** GET /api/school-management/branches/:uuid */
    public function show(string $uuid): ResponseInterface
    {
        if ($err = $this->requireAdmin()) return $err;

        $branch = $this->repo->findByUuid($uuid);
        return $branch
            ? $this->success($branch)
            : $this->error('Không tìm thấy chi nhánh.', 404);
    }

    /** PUT /api/school-management/branches/:uuid */
    public function update(string $uuid): ResponseInterface
    {
        if ($err = $this->requireAdmin()) return $err;

        $raw = $this->repo->findRawByUuid($uuid);
        if (! $raw) return $this->error('Không tìm thấy chi nhánh.', 404);

        // getRawInput() để đọc PUT body
        $input = $this->request->getRawInput();
        $name  = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            return $this->error('Tên chi nhánh không được để trống.', 422);
        }

        $centerId = $raw->center_id ?: null;
        if (array_key_exists('center_uuid', $input)) {
            if ($input['center_uuid'] === '' || $input['center_uuid'] === null) {
                $centerId = null;
            } else {
                $center = $this->centerRepo->findByUuid($input['center_uuid']);
                if (! $center) return $this->error('Trung tâm không tồn tại.', 404);
                $centerId = $center->id;
            }
        }

        if ($this->repo->nameExistsInScope($name, $centerId, $uuid)) {
            return $this->error('Tên chi nhánh đã tồn tại trong cùng tổ chức.', 409);
        }

        $data = ['name' => $name];
        foreach (['address', 'phone', 'email', 'manager'] as $field) {
            if (array_key_exists($field, $input)) {
                $data[$field] = $input[$field] !== '' ? $input[$field] : null;
            }
        }
        if (array_key_exists('center_uuid', $input)) {
            $data['center_id'] = $centerId;
        }

        $this->repo->update($raw->id, $data);

        SystemLogger::info('Cập nhật chi nhánh: ' . $raw->name, ['branch_uuid' => $uuid]);
        return $this->success($this->repo->findByUuid($uuid), 'Cập nhật thành công.');
    }

    /** DELETE /api/school-management/branches/:uuid */
    public function delete(string $uuid): ResponseInterface
    {
        if ($err = $this->requireAdmin()) return $err;

        $raw = $this->repo->findRawByUuid($uuid);
        if (! $raw) return $this->error('Không tìm thấy chi nhánh.', 404);

        $roomCount = $this->repo->countActiveRooms($raw->id);
        if ($roomCount > 0) {
            return $this->error(
                "Không thể xóa chi nhánh còn {$roomCount} phòng học đang hoạt động. Vui lòng xóa hoặc chuyển các phòng trước.",
                409
            );
        }

        $this->repo->deactivate($raw->id);
        SystemLogger::info('Xóa chi nhánh: ' . $raw->name, ['branch_uuid' => $uuid]);
        return $this->success(null, 'Đã xóa chi nhánh.');
    }
}
