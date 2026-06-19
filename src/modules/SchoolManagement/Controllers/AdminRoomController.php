<?php

namespace Modules\SchoolManagement\Controllers;

use App\Controllers\ApiController;
use App\Libraries\SystemLogger;
use CodeIgniter\HTTP\ResponseInterface;
use Modules\SchoolManagement\Repositories\BranchRepository;
use Modules\SchoolManagement\Repositories\RoomRepository;

class AdminRoomController extends ApiController
{
    private RoomRepository   $repo;
    private BranchRepository $branchRepo;

    public function __construct()
    {
        $this->repo       = new RoomRepository();
        $this->branchRepo = new BranchRepository();
    }

    private function requireAdmin(): ?ResponseInterface
    {
        $auth = $this->getAuthUser();
        if (! in_array($auth->role, ['workspace_admin', 'super_admin'])) {
            return $this->error('Không có quyền truy cập.', 403);
        }
        return null;
    }

    /** GET /api/school-management/rooms[?branch_uuid=xxx] */
    public function index(): ResponseInterface
    {
        if ($err = $this->requireAdmin()) return $err;

        $branchUuid = $this->request->getGet('branch_uuid') ?: null;
        return $this->success($this->repo->list($branchUuid));
    }

    /** POST /api/school-management/rooms */
    public function create(): ResponseInterface
    {
        if ($err = $this->requireAdmin()) return $err;

        $rules = [
            'name'        => 'required|max_length[100]',
            'branch_uuid' => 'required',
            'capacity'    => 'permit_empty|integer|greater_than[0]',
            'room_type'   => 'permit_empty|max_length[50]',
        ];
        if (! $this->validate($rules)) {
            return $this->error('Dữ liệu không hợp lệ.', 422, $this->validator->getErrors());
        }

        $branch = $this->branchRepo->findByUuid($this->request->getPost('branch_uuid'));
        if (! $branch) return $this->error('Chi nhánh không tồn tại.', 404);

        $room = $this->repo->create([
            'branch_id' => $branch->id,
            'name'      => $this->request->getPost('name'),
            'capacity'  => $this->request->getPost('capacity'),
            'room_type' => $this->request->getPost('room_type'),
        ]);

        if (! $room) {
            SystemLogger::error('Không thể tạo phòng học', null, [
                'name'        => $this->request->getPost('name'),
                'branch_uuid' => $this->request->getPost('branch_uuid'),
            ]);
            return $this->error('Không thể tạo phòng.', 500);
        }

        SystemLogger::info('Tạo phòng học: ' . $room->name, [
            'room_uuid'   => $room->uuid,
            'branch_name' => $branch->name,
        ]);
        return $this->success($room, 'Tạo phòng thành công.', 201);
    }

    /** GET /api/school-management/rooms/:uuid */
    public function show(string $uuid): ResponseInterface
    {
        if ($err = $this->requireAdmin()) return $err;

        $room = $this->repo->findByUuidWithBranch($uuid);
        return $room
            ? $this->success($room)
            : $this->error('Không tìm thấy phòng.', 404);
    }

    /** PUT /api/school-management/rooms/:uuid */
    public function update(string $uuid): ResponseInterface
    {
        if ($err = $this->requireAdmin()) return $err;

        $room = $this->repo->findByUuid($uuid);
        if (! $room) return $this->error('Không tìm thấy phòng.', 404);

        // getVar() không đọc PUT body — phải dùng getRawInput()
        $input = $this->request->getRawInput();

        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            return $this->error('Tên phòng không được để trống.', 422);
        }

        $data = ['name' => $name];

        if (array_key_exists('room_type', $input)) {
            $data['room_type'] = $input['room_type'] !== '' ? $input['room_type'] : null;
        }

        if (array_key_exists('capacity', $input)) {
            $cap = $input['capacity'];
            $data['capacity'] = ($cap !== '' && is_numeric($cap) && (int) $cap > 0)
                ? (int) $cap
                : null;
        }

        // Đổi chi nhánh nếu gửi kèm
        if (! empty($input['branch_uuid'])) {
            $branch = $this->branchRepo->findByUuid($input['branch_uuid']);
            if (! $branch) return $this->error('Chi nhánh không tồn tại.', 404);
            $data['branch_id'] = $branch->id;
        }

        $this->repo->update($room->id, $data);

        SystemLogger::info('Cập nhật phòng học: ' . $room->name, ['room_uuid' => $uuid]);
        return $this->success($this->repo->findByUuidWithBranch($uuid), 'Cập nhật thành công.');
    }

    /** DELETE /api/school-management/rooms/:uuid */
    public function delete(string $uuid): ResponseInterface
    {
        if ($err = $this->requireAdmin()) return $err;

        $room = $this->repo->findByUuid($uuid);
        if (! $room) return $this->error('Không tìm thấy phòng.', 404);

        $this->repo->deactivate($room->id);
        SystemLogger::info('Xóa phòng học: ' . $room->name, ['room_uuid' => $uuid]);
        return $this->success(null, 'Đã xóa phòng.');
    }
}
