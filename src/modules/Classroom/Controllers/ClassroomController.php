<?php

namespace Modules\Classroom\Controllers;

use App\Controllers\ApiController;
use App\Libraries\SystemLogger;
use CodeIgniter\HTTP\ResponseInterface;
use Modules\Classroom\Repositories\ClassroomRepository;
use Modules\Auth\Repositories\UserRepository;

class ClassroomController extends ApiController
{
    private ClassroomRepository $classroomRepo;
    private UserRepository      $userRepo;

    public function __construct()
    {
        $this->classroomRepo = new ClassroomRepository();
        $this->userRepo      = new UserRepository();
    }

    /** GET /api/classrooms — giáo viên xem danh sách lớp của mình */
    public function index(): ResponseInterface
    {
        $auth = $this->getAuthUser();
        if (! in_array($auth->role, ['workspace_admin', 'super_admin'])) {
            return $this->error('Chỉ giáo viên mới có quyền này.', 403);
        }

        $classrooms = $this->classroomRepo->listByTeacher($auth->sub);
        return $this->success($classrooms);
    }

    /** POST /api/classrooms — giáo viên tạo lớp mới */
    public function create(): ResponseInterface
    {
        $auth = $this->getAuthUser();
        if (! in_array($auth->role, ['workspace_admin', 'super_admin'])) {
            return $this->error('Chỉ giáo viên mới có quyền này.', 403);
        }

        $rules = [
            'name'    => 'required|max_length[255]',
            'subject' => 'permit_empty|max_length[100]',
            'grade'   => 'permit_empty|integer|greater_than[0]|less_than[13]',
        ];
        if (! $this->validate($rules)) {
            return $this->error('Dữ liệu không hợp lệ.', 422, $this->validator->getErrors());
        }

        $teacher = $this->userRepo->findByUuid($auth->sub);
        if (! $teacher) return $this->error('Không tìm thấy người dùng.', 404);

        $code = $this->classroomRepo->generateCode($teacher->username ?: $teacher->full_name);

        $classroom = $this->classroomRepo->create($auth->sub, [
            'name'         => $this->request->getPost('name'),
            'description'  => $this->request->getPost('description'),
            'code'         => $code,
            'subject'      => $this->request->getPost('subject'),
            'grade'        => $this->request->getPost('grade'),
            'auto_approve' => (int) ($this->request->getPost('auto_approve') ?? 1),
        ]);

        if (! $classroom) {
            return $this->error('Không thể tạo lớp học.', 500);
        }

        SystemLogger::info('Tạo lớp học mới: ' . $classroom->name, ['classroom_id' => $classroom->id]);
        return $this->success($classroom, 'Tạo lớp học thành công.', 201);
    }

    /** GET /api/classrooms/:uuid */
    public function show(string $uuid): ResponseInterface
    {
        $auth      = $this->getAuthUser();
        $classroom = $this->classroomRepo->findByUuid($uuid);
        if (! $classroom) return $this->error('Không tìm thấy lớp học.', 404);

        if ($classroom->teacher_uuid !== $auth->sub && $auth->role !== 'super_admin') {
            return $this->error('Không có quyền truy cập.', 403);
        }

        return $this->success($classroom);
    }

    /** PUT /api/classrooms/:uuid */
    public function update(string $uuid): ResponseInterface
    {
        $auth      = $this->getAuthUser();
        $classroom = $this->classroomRepo->findByUuid($uuid);
        if (! $classroom) return $this->error('Không tìm thấy lớp học.', 404);

        if ($classroom->teacher_uuid !== $auth->sub && $auth->role !== 'super_admin') {
            return $this->error('Không có quyền chỉnh sửa.', 403);
        }

        $data = array_filter([
            'name'        => $this->request->getVar('name'),
            'description' => $this->request->getVar('description'),
            'subject'     => $this->request->getVar('subject'),
            'grade'       => $this->request->getVar('grade'),
        ], fn($v) => $v !== null);

        $this->classroomRepo->update($classroom->id, $data);
        return $this->success($this->classroomRepo->findByUuid($uuid), 'Cập nhật thành công.');
    }

    /** DELETE /api/classrooms/:uuid */
    public function delete(string $uuid): ResponseInterface
    {
        $auth      = $this->getAuthUser();
        $classroom = $this->classroomRepo->findByUuid($uuid);
        if (! $classroom) return $this->error('Không tìm thấy lớp học.', 404);

        if ($classroom->teacher_uuid !== $auth->sub && $auth->role !== 'super_admin') {
            return $this->error('Không có quyền xóa.', 403);
        }

        $this->classroomRepo->deactivate($classroom->id);
        return $this->success(null, 'Đã xóa lớp học.');
    }

    /** PUT /api/classrooms/:uuid/toggle-approval — bật/tắt tự động duyệt */
    public function toggleApproval(string $uuid): ResponseInterface
    {
        $auth      = $this->getAuthUser();
        $classroom = $this->classroomRepo->findByUuid($uuid);
        if (! $classroom) return $this->error('Không tìm thấy lớp học.', 404);

        if ($classroom->teacher_uuid !== $auth->sub && $auth->role !== 'super_admin') {
            return $this->error('Không có quyền thay đổi.', 403);
        }

        $newVal = $classroom->auto_approve ? 0 : 1;
        $this->classroomRepo->update($classroom->id, ['auto_approve' => $newVal]);

        return $this->success(
            ['auto_approve' => (bool) $newVal],
            $newVal ? 'Đã bật tự động duyệt.' : 'Đã tắt tự động duyệt (cần phê duyệt thủ công).'
        );
    }
}
