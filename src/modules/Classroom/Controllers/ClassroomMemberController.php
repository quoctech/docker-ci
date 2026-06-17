<?php

namespace Modules\Classroom\Controllers;

use App\Controllers\ApiController;
use App\Libraries\SystemLogger;
use CodeIgniter\HTTP\ResponseInterface;
use Modules\Classroom\Repositories\ClassroomRepository;
use Modules\Classroom\Repositories\ClassroomMemberRepository;
use Modules\Auth\Repositories\UserRepository;

class ClassroomMemberController extends ApiController
{
    private ClassroomRepository       $classroomRepo;
    private ClassroomMemberRepository $memberRepo;
    private UserRepository            $userRepo;

    public function __construct()
    {
        $this->classroomRepo = new ClassroomRepository();
        $this->memberRepo    = new ClassroomMemberRepository();
        $this->userRepo      = new UserRepository();
    }

    /** POST /api/classrooms/join — học sinh nhập mã để vào lớp */
    public function join(): ResponseInterface
    {
        $code = strtoupper(trim($this->request->getPost('code') ?? ''));
        if (! $code) return $this->error('Vui lòng nhập mã lớp học.', 422);

        $auth = $this->getAuthUser();

        $classroom = $this->classroomRepo->findByCode($code);
        if (! $classroom) return $this->error('Mã lớp học không tồn tại hoặc đã đóng.', 404);

        $existing = $this->memberRepo->isEnrolled($classroom->id, $auth->sub);
        if ($existing) {
            if ($existing->status === 'approved') return $this->error('Bạn đã tham gia lớp này rồi.', 409);
            if ($existing->status === 'pending')  return $this->error('Yêu cầu của bạn đang chờ giáo viên duyệt.', 409);
            if ($existing->status === 'rejected') return $this->error('Yêu cầu của bạn đã bị từ chối.', 403);
        }

        $member = $this->memberRepo->join($classroom->id, $auth->sub, (bool) $classroom->auto_approve);
        if (! $member) return $this->error('Không thể tham gia lớp học.', 500);

        $message = $classroom->auto_approve
            ? 'Tham gia lớp ' . $classroom->name . ' thành công!'
            : 'Yêu cầu tham gia đã gửi, chờ giáo viên duyệt.';

        SystemLogger::info('Học sinh tham gia lớp: ' . $classroom->name, [
            'student_uuid' => $auth->sub,
            'classroom_id' => $classroom->id,
            'auto_approve' => $classroom->auto_approve,
        ]);

        return $this->success([
            'status'    => $member->status,
            'classroom' => $classroom,
        ], $message, 201);
    }

    /** GET /api/classrooms/:uuid/members — giáo viên xem danh sách học sinh */
    public function index(string $uuid): ResponseInterface
    {
        $auth      = $this->getAuthUser();
        $classroom = $this->classroomRepo->findByUuid($uuid);
        if (! $classroom) return $this->error('Không tìm thấy lớp học.', 404);

        if ($classroom->teacher_uuid !== $auth->sub && $auth->role !== 'super_admin') {
            return $this->error('Không có quyền truy cập.', 403);
        }

        $status  = $this->request->getGet('status');
        $members = $this->memberRepo->listByClassroom($classroom->id, $status ?: null);

        return $this->success($members);
    }

    /** PUT /api/classrooms/:uuid/members/:id/approve */
    public function approve(string $uuid, int $memberId): ResponseInterface
    {
        [$classroom, $err] = $this->assertTeacher($uuid);
        if ($err) return $err;

        $this->memberRepo->approve($memberId);
        return $this->success(null, 'Đã duyệt học sinh.');
    }

    /** PUT /api/classrooms/:uuid/members/:id/reject */
    public function reject(string $uuid, int $memberId): ResponseInterface
    {
        [$classroom, $err] = $this->assertTeacher($uuid);
        if ($err) return $err;

        $this->memberRepo->reject($memberId);
        return $this->success(null, 'Đã từ chối học sinh.');
    }

    /** DELETE /api/classrooms/:uuid/members/:id */
    public function remove(string $uuid, int $memberId): ResponseInterface
    {
        [$classroom, $err] = $this->assertTeacher($uuid);
        if ($err) return $err;

        $this->memberRepo->remove($memberId);
        return $this->success(null, 'Đã xóa học sinh khỏi lớp.');
    }

    /** GET /api/my-classrooms — học sinh xem lớp đã tham gia */
    public function myClassrooms(): ResponseInterface
    {
        $auth = $this->getAuthUser();
        $classrooms = $this->memberRepo->myClassrooms($auth->sub);
        return $this->success($classrooms);
    }

    /** GET /api/my-classrooms/:uuid — học sinh xem thông tin lớp học */
    public function show(string $uuid): ResponseInterface
    {
        $auth      = $this->getAuthUser();
        $classroom = $this->classroomRepo->findByUuid($uuid);
        if (! $classroom) return $this->error('Không tìm thấy lớp học.', 404);

        $member = $this->memberRepo->isEnrolled($classroom->id, $auth->sub);
        if (! $member || $member->status !== 'approved') {
            return $this->error('Bạn chưa tham gia lớp học này.', 403);
        }

        return $this->success($classroom);
    }

    /** DELETE /api/my-classrooms/:uuid/leave — học sinh rời lớp */
    public function leave(string $uuid): ResponseInterface
    {
        $auth      = $this->getAuthUser();
        $classroom = $this->classroomRepo->findByUuid($uuid);
        if (! $classroom) return $this->error('Không tìm thấy lớp học.', 404);

        $member = $this->memberRepo->isEnrolled($classroom->id, $auth->sub);
        if (! $member) return $this->error('Bạn không tham gia lớp học này.', 404);

        $this->memberRepo->remove($member->id);

        SystemLogger::info('Học sinh rời lớp: ' . $classroom->name, [
            'student_uuid' => $auth->sub,
            'classroom_id' => $classroom->id,
        ]);

        return $this->success(null, 'Đã rời lớp học.');
    }

    private function assertTeacher(string $uuid): array
    {
        $auth      = $this->getAuthUser();
        $classroom = $this->classroomRepo->findByUuid($uuid);
        if (! $classroom) return [null, $this->error('Không tìm thấy lớp học.', 404)];

        if ($classroom->teacher_uuid !== $auth->sub && $auth->role !== 'super_admin') {
            return [null, $this->error('Không có quyền thực hiện.', 403)];
        }

        return [$classroom, null];
    }
}
