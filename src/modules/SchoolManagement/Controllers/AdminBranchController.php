<?php

namespace Modules\SchoolManagement\Controllers;

use App\Controllers\ApiController;
use App\Libraries\SystemLogger;
use CodeIgniter\HTTP\ResponseInterface;
use Modules\SchoolManagement\Repositories\BranchRepository;

class AdminBranchController extends ApiController
{
    private BranchRepository $repo;

    public function __construct()
    {
        $this->repo = new BranchRepository();
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
            'name'  => 'required|max_length[100]',
            'phone' => 'permit_empty|max_length[20]',
            'email' => 'permit_empty|valid_email|max_length[100]',
        ];
        if (! $this->validate($rules)) {
            return $this->error('Dữ liệu không hợp lệ.', 422, $this->validator->getErrors());
        }

        $branch = $this->repo->create([
            'name'    => $this->request->getPost('name'),
            'address' => $this->request->getPost('address'),
            'phone'   => $this->request->getPost('phone'),
            'email'   => $this->request->getPost('email'),
        ]);

        if (! $branch) {
            SystemLogger::error('Không thể tạo chi nhánh', null, [
                'name' => $this->request->getPost('name'),
            ]);
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

        $branch = $this->repo->findByUuid($uuid);
        if (! $branch) return $this->error('Không tìm thấy chi nhánh.', 404);

        // getVar() không đọc PUT body — phải dùng getRawInput()
        $input = $this->request->getRawInput();

        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            return $this->error('Tên chi nhánh không được để trống.', 422);
        }

        $data = ['name' => $name];
        foreach (['address', 'phone', 'email'] as $field) {
            if (array_key_exists($field, $input)) {
                $data[$field] = $input[$field] !== '' ? $input[$field] : null;
            }
        }

        $this->repo->update($branch->id, $data);

        SystemLogger::info('Cập nhật chi nhánh: ' . $branch->name, ['branch_uuid' => $uuid]);
        return $this->success($this->repo->findByUuid($uuid), 'Cập nhật thành công.');
    }

    /** DELETE /api/school-management/branches/:uuid */
    public function delete(string $uuid): ResponseInterface
    {
        if ($err = $this->requireAdmin()) return $err;

        $branch = $this->repo->findByUuid($uuid);
        if (! $branch) return $this->error('Không tìm thấy chi nhánh.', 404);

        $this->repo->deactivate($branch->id);
        SystemLogger::info('Xóa chi nhánh: ' . $branch->name, ['branch_uuid' => $uuid]);
        return $this->success(null, 'Đã xóa chi nhánh.');
    }
}
