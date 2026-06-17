<?php

namespace Modules\Classroom\Repositories;

use Modules\Classroom\Models\SubmissionModel;

class SubmissionRepository
{
    private SubmissionModel $model;

    public function __construct()
    {
        $this->model = new SubmissionModel();
    }

    public function submit(int $assignmentId, string $studentUuid, array $data): ?object
    {
        $existing = $this->model
            ->where('assignment_id', $assignmentId)
            ->where('student_uuid', $studentUuid)
            ->first();

        $now  = date('Y-m-d H:i:s');
        $uuid = bin2hex(random_bytes(16));
        $uuid = sprintf('%s-%s-%s-%s-%s',
            substr($uuid, 0, 8), substr($uuid, 8, 4),
            substr($uuid, 12, 4), substr($uuid, 16, 4), substr($uuid, 20)
        );

        if ($existing) {
            if ($existing->status === 'graded') {
                return null;
            }
            $this->model->update($existing->id, [
                'content'      => $data['content'] ?? null,
                'file_url'     => $data['file_url'] ?? null,
                'submitted_at' => $now,
            ]);
            return $this->model->find($existing->id);
        }

        $id = $this->model->insert([
            'uuid'          => $uuid,
            'assignment_id' => $assignmentId,
            'student_uuid'  => $studentUuid,
            'content'       => $data['content'] ?? null,
            'file_url'      => $data['file_url'] ?? null,
            'status'        => 'submitted',
            'submitted_at'  => $now,
            'created_at'    => $now,
        ]);

        return $id ? $this->model->find($id) : null;
    }

    public function grade(int $id, int $score, ?string $feedback): bool
    {
        return $this->model->update($id, [
            'score'      => $score,
            'feedback'   => $feedback,
            'status'     => 'graded',
            'graded_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    public function listByAssignment(int $assignmentId): array
    {
        $db = \Config\Database::connect();
        return $db->query("
            SELECT s.*, u.full_name, u.email, u.username
            FROM assignment_submissions s
            JOIN users u ON u.uuid = s.student_uuid
            WHERE s.assignment_id = ?
            ORDER BY s.submitted_at DESC
        ", [$assignmentId])->getResultObject();
    }

    public function mySubmission(int $assignmentId, string $studentUuid): ?object
    {
        return $this->model
            ->where('assignment_id', $assignmentId)
            ->where('student_uuid', $studentUuid)
            ->first();
    }

    public function findByUuid(string $uuid): ?object
    {
        return $this->model->where('uuid', $uuid)->first();
    }

    public function findById(int $id): ?object
    {
        return $this->model->find($id);
    }
}
