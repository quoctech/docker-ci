<?php

namespace Modules\Classroom\Controllers;

use App\Controllers\ApiController;
use App\Libraries\SystemLogger;
use CodeIgniter\HTTP\ResponseInterface;
use Modules\Classroom\Repositories\ClassroomRepository;
use Modules\Classroom\Repositories\ClassroomMemberRepository;
use Modules\Classroom\Repositories\AssignmentRepository;
use Modules\Classroom\Repositories\SubmissionRepository;

class SubmissionController extends ApiController
{
    private ClassroomRepository       $classroomRepo;
    private ClassroomMemberRepository $memberRepo;
    private AssignmentRepository      $assignmentRepo;
    private SubmissionRepository      $submissionRepo;

    public function __construct()
    {
        $this->classroomRepo  = new ClassroomRepository();
        $this->memberRepo     = new ClassroomMemberRepository();
        $this->assignmentRepo = new AssignmentRepository();
        $this->submissionRepo = new SubmissionRepository();
    }

    /** POST /api/assignments/:uuid/submit — học sinh nộp bài */
    public function submit(string $assignmentUuid): ResponseInterface
    {
        $auth       = $this->getAuthUser();
        $assignment = $this->assignmentRepo->findByUuid($assignmentUuid);
        if (! $assignment || ! $assignment->is_published) {
            return $this->error('Bài tập không tồn tại hoặc chưa được công bố.', 404);
        }

        $member = $this->memberRepo->isEnrolled($assignment->classroom_id, $auth->sub);
        if (! $member || $member->status !== 'approved') {
            return $this->error('Bạn chưa tham gia lớp học này.', 403);
        }

        $content    = trim($this->request->getPost('content') ?? '');
        $imageFiles = array_filter(
            $this->request->getFileMultiple('images') ?? [],
            fn($f) => $f && $f->isValid() && ! $f->hasMoved()
        );

        if (! $content && count($imageFiles) === 0) {
            return $this->error('Vui lòng nhập nội dung hoặc đính kèm ảnh bài làm.', 422);
        }

        if (count($imageFiles) > 10) {
            return $this->error('Tối đa 10 ảnh cho mỗi bài nộp.', 422);
        }

        $imagePaths = [];
        foreach ($imageFiles as $img) {
            $result = $this->handleSubmissionImage($img);
            if (str_starts_with($result, 'ERR:')) {
                return $this->error(substr($result, 4), 422);
            }
            $imagePaths[] = $result;
        }

        $submission = $this->submissionRepo->submit($assignment->id, $auth->sub, [
            'content'     => $content ?: null,
            'image_paths' => ! empty($imagePaths) ? json_encode($imagePaths) : null,
        ]);

        if ($submission === null) {
            return $this->error('Bài đã được chấm điểm, không thể nộp lại.', 409);
        }

        if ($submission->image_paths) {
            $submission->image_paths = json_decode($submission->image_paths, true);
        } else {
            $submission->image_paths = [];
        }

        SystemLogger::info('Học sinh nộp bài: ' . $assignment->title, [
            'student_uuid'  => $auth->sub,
            'assignment_id' => $assignment->id,
        ]);

        return $this->success($submission, 'Nộp bài thành công!', 201);
    }

    /** GET /api/submissions/:uuid/images/:index — phục vụ ảnh bài nộp */
    public function image(string $uuid, int $index): ResponseInterface
    {
        $auth       = $this->getAuthUser();
        $submission = $this->submissionRepo->findByUuid($uuid);
        if (! $submission || ! $submission->image_paths) {
            return $this->error('Không tìm thấy ảnh.', 404);
        }

        $paths = json_decode($submission->image_paths, true);
        if (! isset($paths[$index])) {
            return $this->error('Ảnh không tồn tại.', 404);
        }

        // Auth: chủ sở hữu hoặc giáo viên/admin
        if ($submission->student_uuid !== $auth->sub) {
            $assignment = $this->assignmentRepo->findById($submission->assignment_id);
            $classroom  = $this->classroomRepo->findById($assignment?->classroom_id);
            if ($classroom?->teacher_uuid !== $auth->sub && $auth->role !== 'super_admin') {
                return $this->error('Không có quyền truy cập.', 403);
            }
        }

        $filePath = WRITEPATH . 'uploads/submissions/' . $paths[$index];
        if (! file_exists($filePath)) {
            return $this->error('File ảnh không tồn tại trên server.', 404);
        }

        $ext = strtolower(pathinfo($paths[$index], PATHINFO_EXTENSION));
        $mimeMap = [
            'jpg'  => 'image/jpeg', 'jpeg' => 'image/jpeg',
            'png'  => 'image/png',  'webp'  => 'image/webp',
            'gif'  => 'image/gif',
        ];
        $mime = $mimeMap[$ext] ?? 'image/jpeg';

        return $this->response
            ->setHeader('Content-Type', $mime)
            ->setHeader('Cache-Control', 'private, max-age=3600')
            ->setHeader('Content-Length', (string) filesize($filePath))
            ->setBody(file_get_contents($filePath));
    }

    /** GET /api/assignments/:uuid/submissions — giáo viên xem tất cả bài nộp */
    public function index(string $assignmentUuid): ResponseInterface
    {
        $auth       = $this->getAuthUser();
        $assignment = $this->assignmentRepo->findByUuid($assignmentUuid);
        if (! $assignment) return $this->error('Không tìm thấy bài tập.', 404);

        $classroom = $this->classroomRepo->findById($assignment->classroom_id);
        if ($classroom?->teacher_uuid !== $auth->sub && $auth->role !== 'super_admin') {
            return $this->error('Chỉ giáo viên mới có thể xem tất cả bài nộp.', 403);
        }

        $submissions = $this->submissionRepo->listByAssignment($assignment->id);
        // Decode image_paths for each submission
        foreach ($submissions as $s) {
            $s->image_paths = $s->image_paths ? json_decode($s->image_paths, true) : [];
        }

        return $this->success([
            'assignment'  => $assignment,
            'submissions' => $submissions,
        ]);
    }

    /** GET /api/assignments/:uuid/my-submission — học sinh xem bài nộp của mình */
    public function mySubmission(string $assignmentUuid): ResponseInterface
    {
        $auth       = $this->getAuthUser();
        $assignment = $this->assignmentRepo->findByUuid($assignmentUuid);
        if (! $assignment) return $this->error('Không tìm thấy bài tập.', 404);

        $submission = $this->submissionRepo->mySubmission($assignment->id, $auth->sub);
        if ($submission && $submission->image_paths) {
            $submission->image_paths = json_decode($submission->image_paths, true);
        }
        return $this->success($submission);
    }

    /** PUT /api/submissions/:uuid/grade — giáo viên chấm điểm */
    public function grade(string $uuid): ResponseInterface
    {
        $auth       = $this->getAuthUser();
        $submission = $this->submissionRepo->findByUuid($uuid);
        if (! $submission) return $this->error('Không tìm thấy bài nộp.', 404);

        $assignment = $this->assignmentRepo->findById($submission->assignment_id);
        $classroom  = $this->classroomRepo->findById($assignment?->classroom_id);

        if ($classroom?->teacher_uuid !== $auth->sub && $auth->role !== 'super_admin') {
            return $this->error('Chỉ giáo viên mới có thể chấm điểm.', 403);
        }

        $input    = $this->request->getRawInput();
        $score    = $input['score'] ?? null;
        $feedback = $input['feedback'] ?? null;

        if ($score === null || $score === '') {
            return $this->error('Vui lòng nhập điểm.', 422);
        }

        $score    = (float) $score;
        $maxScore = (float) ($assignment->max_score ?? 10);
        if ($score < 0 || $score > $maxScore) {
            return $this->error("Điểm phải từ 0 đến {$maxScore}.", 422);
        }
        $this->submissionRepo->grade($submission->id, $score, $feedback);

        SystemLogger::info('Chấm điểm bài nộp', [
            'submission_id' => $submission->id,
            'score'         => $score,
        ]);

        $updated = $this->submissionRepo->findByUuid($uuid);
        if ($updated->image_paths) {
            $updated->image_paths = json_decode($updated->image_paths, true);
        } else {
            $updated->image_paths = [];
        }
        return $this->success($updated, 'Chấm điểm thành công.');
    }

    private function handleSubmissionImage($file): string
    {
        $allowedExts  = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

        $ext  = strtolower($file->getClientExtension());
        $mime = $file->getMimeType();
        $size = $file->getSize();

        if (! in_array($ext, $allowedExts)) {
            return 'ERR:Chỉ chấp nhận ảnh JPG, PNG, WEBP, GIF.';
        }
        if (! in_array($mime, $allowedMimes)) {
            return 'ERR:Loại MIME ảnh không hợp lệ.';
        }
        if ($size > 10 * 1024 * 1024) {
            return 'ERR:Ảnh quá lớn. Tối đa 10MB mỗi ảnh.';
        }

        $fp     = fopen($file->getTempName(), 'rb');
        $header = fread($fp, 12);
        fclose($fp);

        $valid = false;
        if (in_array($ext, ['jpg', 'jpeg']) && substr($header, 0, 3) === "\xFF\xD8\xFF") $valid = true;
        if ($ext === 'png' && substr($header, 0, 8) === "\x89PNG\r\n\x1A\n")             $valid = true;
        if ($ext === 'gif' && (str_starts_with($header, 'GIF87a') || str_starts_with($header, 'GIF89a'))) $valid = true;
        if ($ext === 'webp' && substr($header, 0, 4) === 'RIFF' && substr($header, 8, 4) === 'WEBP') $valid = true;

        if (! $valid) {
            return 'ERR:Nội dung ảnh không hợp lệ.';
        }

        $newName   = bin2hex(random_bytes(16)) . '.' . $ext;
        $uploadDir = WRITEPATH . 'uploads/submissions/';
        if (! is_dir($uploadDir)) mkdir($uploadDir, 0750, true);
        $file->move($uploadDir, $newName);

        return $newName;
    }
}
