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

        $content  = $this->request->getPost('content');
        $file_url = $this->request->getPost('file_url');

        if (! $content && ! $file_url) {
            return $this->error('Vui lòng nhập nội dung bài nộp hoặc đính kèm file.', 422);
        }

        $submission = $this->submissionRepo->submit($assignment->id, $auth->sub, [
            'content'  => $content,
            'file_url' => $file_url,
        ]);

        if ($submission === null) {
            return $this->error('Bài đã được chấm điểm, không thể nộp lại.', 409);
        }

        SystemLogger::info('Học sinh nộp bài: ' . $assignment->title, [
            'student_uuid'  => $auth->sub,
            'assignment_id' => $assignment->id,
        ]);

        return $this->success($submission, 'Nộp bài thành công!', 201);
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

        $score = $this->request->getVar('score');
        if ($score === null || $score === '') {
            return $this->error('Vui lòng nhập điểm.', 422);
        }

        $score    = (int) $score;
        $maxScore = $assignment->max_score ?? 100;
        if ($score < 0 || $score > $maxScore) {
            return $this->error("Điểm phải từ 0 đến {$maxScore}.", 422);
        }

        $feedback = $this->request->getVar('feedback');
        $this->submissionRepo->grade($submission->id, $score, $feedback);

        SystemLogger::info('Chấm điểm bài nộp', [
            'submission_id' => $submission->id,
            'score'         => $score,
        ]);

        return $this->success(
            $this->submissionRepo->findByUuid($uuid),
            'Chấm điểm thành công.'
        );
    }
}
