<?php

namespace Modules\Classroom\Controllers;

use App\Controllers\ApiController;
use App\Libraries\SystemLogger;
use CodeIgniter\HTTP\ResponseInterface;
use Modules\Classroom\Repositories\ClassroomRepository;
use Modules\Classroom\Repositories\ClassroomMemberRepository;
use Modules\Classroom\Repositories\AssignmentRepository;

class AssignmentController extends ApiController
{
    private ClassroomRepository       $classroomRepo;
    private ClassroomMemberRepository $memberRepo;
    private AssignmentRepository      $assignmentRepo;

    public function __construct()
    {
        $this->classroomRepo  = new ClassroomRepository();
        $this->memberRepo     = new ClassroomMemberRepository();
        $this->assignmentRepo = new AssignmentRepository();
    }

    /** GET /api/classrooms/:uuid/assignments */
    public function index(string $classroomUuid): ResponseInterface
    {
        $auth      = $this->getAuthUser();
        $classroom = $this->classroomRepo->findByUuid($classroomUuid);
        if (! $classroom) return $this->error('Không tìm thấy lớp học.', 404);

        $isTeacher = ($classroom->teacher_uuid === $auth->sub || $auth->role === 'super_admin');

        if (! $isTeacher) {
            $member = $this->memberRepo->isEnrolled($classroom->id, $auth->sub);
            if (! $member || $member->status !== 'approved') {
                return $this->error('Bạn chưa tham gia lớp học này.', 403);
            }
        }

        if ($isTeacher) {
            $assignments = $this->assignmentRepo->listByClassroom($classroom->id, false);
        } else {
            $assignments = $this->assignmentRepo->listByClassroomForStudent($classroom->id, $auth->sub);
        }

        return $this->success($assignments);
    }

    /** POST /api/classrooms/:uuid/assignments — giáo viên tạo bài tập */
    public function create(string $classroomUuid): ResponseInterface
    {
        $auth      = $this->getAuthUser();
        $classroom = $this->classroomRepo->findByUuid($classroomUuid);
        if (! $classroom) return $this->error('Không tìm thấy lớp học.', 404);

        if ($classroom->teacher_uuid !== $auth->sub && $auth->role !== 'super_admin') {
            return $this->error('Chỉ giáo viên của lớp mới có thể đăng bài tập.', 403);
        }

        $rules = ['title' => 'required|max_length[255]'];
        if (! $this->validate($rules)) {
            return $this->error('Dữ liệu không hợp lệ.', 422, $this->validator->getErrors());
        }

        $filePath = null;
        $uploaded = $this->request->getFile('assignment_file');
        if ($uploaded && $uploaded->isValid() && ! $uploaded->hasMoved()) {
            $result = $this->handleAssignmentFile($uploaded);
            if (is_string($result) && str_starts_with($result, 'ERR:')) {
                return $this->error(substr($result, 4), 422);
            }
            $filePath = $result;
        }

        $assignment = $this->assignmentRepo->create($classroom->id, $auth->sub, [
            'title'        => $this->request->getPost('title'),
            'description'  => $this->request->getPost('description'),
            'due_date'     => $this->request->getPost('due_date') ?: null,
            'max_score'    => $this->request->getPost('max_score') ?? 100,
            'is_published' => (int) ($this->request->getPost('is_published') ?? 1),
            'file_path'    => $filePath,
        ]);

        if (! $assignment) return $this->error('Không thể tạo bài tập.', 500);

        SystemLogger::info('Đăng bài tập: ' . $assignment->title, ['assignment_id' => $assignment->id]);
        return $this->success($assignment, 'Đăng bài tập thành công.', 201);
    }

    /** GET /api/assignments/:uuid/file — tải file đính kèm */
    public function downloadFile(string $uuid): ResponseInterface
    {
        $auth       = $this->getAuthUser();
        $assignment = $this->assignmentRepo->findByUuid($uuid);
        if (! $assignment || ! $assignment->file_path) {
            return $this->error('Không tìm thấy file.', 404);
        }

        $classroom = $this->classroomRepo->findById($assignment->classroom_id);
        $isTeacher = ($classroom && $classroom->teacher_uuid === $auth->sub) || $auth->role === 'super_admin';

        if (! $isTeacher) {
            $member = $this->memberRepo->isEnrolled($assignment->classroom_id, $auth->sub);
            if (! $member || $member->status !== 'approved') {
                return $this->error('Bạn chưa tham gia lớp học này.', 403);
            }
            if (! $assignment->is_published) {
                return $this->error('Bài tập chưa được công bố.', 403);
            }
        }

        $filePath = WRITEPATH . 'uploads/assignments/' . $assignment->file_path;
        if (! file_exists($filePath)) {
            return $this->error('File không tồn tại trên server.', 404);
        }

        $ext = strtolower(pathinfo($assignment->file_path, PATHINFO_EXTENSION));
        $mimeMap = [
            'pdf'  => 'application/pdf',
            'doc'  => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls'  => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt'  => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ];
        $mime        = $mimeMap[$ext] ?? 'application/octet-stream';
        $displayName = preg_replace('/[^\w\s\-.]/', '', $assignment->title) . '.' . $ext;

        return $this->response
            ->setHeader('Content-Type', $mime)
            ->setHeader('Content-Disposition', 'attachment; filename="' . $displayName . '"')
            ->setHeader('Content-Length', (string) filesize($filePath))
            ->setBody(file_get_contents($filePath));
    }

    private function handleAssignmentFile($file): string
    {
        $allowedExts = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'];
        $allowedMimes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ];

        $ext  = strtolower($file->getClientExtension());
        $mime = $file->getMimeType();
        $size = $file->getSize();

        if (! in_array($ext, $allowedExts)) {
            return 'ERR:Chỉ chấp nhận file PDF, Word, Excel, PowerPoint.';
        }
        if (! in_array($mime, $allowedMimes)) {
            return 'ERR:Loại MIME file không hợp lệ.';
        }
        if ($size > 20 * 1024 * 1024) {
            return 'ERR:File quá lớn. Tối đa 20MB.';
        }

        // Magic bytes validation
        $fp     = fopen($file->getTempName(), 'rb');
        $header = fread($fp, 8);
        fclose($fp);

        $valid = false;
        if ($ext === 'pdf' && str_starts_with($header, '%PDF'))                          $valid = true;
        if (in_array($ext, ['doc', 'xls', 'ppt']) && substr($header, 0, 4) === "\xD0\xCF\x11\xE0") $valid = true;
        if (in_array($ext, ['docx', 'xlsx', 'pptx']) && substr($header, 0, 4) === "\x50\x4B\x03\x04") $valid = true;

        if (! $valid) {
            return 'ERR:Nội dung file không khớp với định dạng khai báo.';
        }

        $newName  = bin2hex(random_bytes(16)) . '.' . $ext;
        $uploadDir = WRITEPATH . 'uploads/assignments/';
        if (! is_dir($uploadDir)) mkdir($uploadDir, 0750, true);
        $file->move($uploadDir, $newName);

        return $newName;
    }

    /** GET /api/assignments/:uuid */
    public function show(string $uuid): ResponseInterface
    {
        $auth       = $this->getAuthUser();
        $assignment = $this->assignmentRepo->findByUuid($uuid);
        if (! $assignment) return $this->error('Không tìm thấy bài tập.', 404);

        $classroom = $this->classroomRepo->findById($assignment->classroom_id);
        $isTeacher = ($classroom && $classroom->teacher_uuid === $auth->sub) || $auth->role === 'super_admin';

        if (! $isTeacher) {
            $member = $this->memberRepo->isEnrolled($assignment->classroom_id, $auth->sub);
            if (! $member || $member->status !== 'approved') {
                return $this->error('Bạn chưa tham gia lớp học này.', 403);
            }
            if (! $assignment->is_published) {
                return $this->error('Bài tập chưa được công bố.', 403);
            }
        }

        return $this->success($assignment);
    }

    /** PUT /api/assignments/:uuid */
    public function update(string $uuid): ResponseInterface
    {
        $auth       = $this->getAuthUser();
        $assignment = $this->assignmentRepo->findByUuid($uuid);
        if (! $assignment) return $this->error('Không tìm thấy bài tập.', 404);

        $classroom = $this->classroomRepo->findById($assignment->classroom_id);
        if ($classroom?->teacher_uuid !== $auth->sub && $auth->role !== 'super_admin') {
            return $this->error('Không có quyền chỉnh sửa.', 403);
        }

        $data = array_filter([
            'title'        => $this->request->getVar('title'),
            'description'  => $this->request->getVar('description'),
            'due_date'     => $this->request->getVar('due_date'),
            'max_score'    => $this->request->getVar('max_score'),
            'is_published' => $this->request->getVar('is_published'),
        ], fn($v) => $v !== null);

        $this->assignmentRepo->update($assignment->id, $data);
        return $this->success($this->assignmentRepo->findByUuid($uuid), 'Cập nhật bài tập thành công.');
    }

    /** DELETE /api/assignments/:uuid */
    public function delete(string $uuid): ResponseInterface
    {
        $auth       = $this->getAuthUser();
        $assignment = $this->assignmentRepo->findByUuid($uuid);
        if (! $assignment) return $this->error('Không tìm thấy bài tập.', 404);

        $classroom = $this->classroomRepo->findById($assignment->classroom_id);
        if ($classroom?->teacher_uuid !== $auth->sub && $auth->role !== 'super_admin') {
            return $this->error('Không có quyền xóa.', 403);
        }

        $this->assignmentRepo->delete($assignment->id);
        return $this->success(null, 'Đã xóa bài tập.');
    }
}
