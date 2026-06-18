<?php

namespace Modules\Classroom\Repositories;

use Modules\Classroom\Models\AssignmentModel;

class AssignmentRepository
{
    private AssignmentModel $model;

    public function __construct()
    {
        $this->model = new AssignmentModel();
    }

    public function create(int $classroomId, string $teacherUuid, array $data): ?object
    {
        $uuid = bin2hex(random_bytes(16));
        $uuid = sprintf('%s-%s-%s-%s-%s',
            substr($uuid, 0, 8), substr($uuid, 8, 4),
            substr($uuid, 12, 4), substr($uuid, 16, 4), substr($uuid, 20)
        );

        $id = $this->model->insert([
            'uuid'         => $uuid,
            'classroom_id' => $classroomId,
            'teacher_uuid' => $teacherUuid,
            'title'        => $data['title'],
            'description'  => $data['description'] ?? null,
            'due_date'     => ($data['due_date'] ?? '') !== '' ? $data['due_date'] : null,
            'max_score'    => (int) ($data['max_score'] ?? 100),
            'is_published' => isset($data['is_published']) ? (int) $data['is_published'] : 1,
            'file_path'    => $data['file_path'] ?? null,
        ]);

        return $id ? $this->model->find($id) : null;
    }

    public function listByClassroom(int $classroomId, bool $publishedOnly = false): array
    {
        $db = \Config\Database::connect();
        $publishedSql = $publishedOnly ? "AND a.is_published = 1" : "";

        return $db->query("
            SELECT a.*,
                   (SELECT COUNT(*) FROM assignment_submissions s WHERE s.assignment_id = a.id) AS submission_count,
                   (SELECT COUNT(*) FROM assignment_submissions s WHERE s.assignment_id = a.id AND s.status = 'graded') AS graded_count
            FROM assignments a
            WHERE a.classroom_id = ? {$publishedSql}
            ORDER BY a.created_at DESC
        ", [$classroomId])->getResultObject();
    }

    public function listByClassroomForStudent(int $classroomId, string $studentUuid): array
    {
        $db   = \Config\Database::connect();
        $rows = $db->query("
            SELECT a.*,
                   s.id          AS sub_id,
                   s.uuid        AS sub_uuid,
                   s.content     AS sub_content,
                   s.image_paths AS sub_image_paths,
                   s.status      AS sub_status,
                   s.score       AS sub_score,
                   s.feedback    AS sub_feedback,
                   s.submitted_at AS sub_submitted_at,
                   s.graded_at   AS sub_graded_at
            FROM assignments a
            LEFT JOIN assignment_submissions s
                   ON s.assignment_id = a.id AND s.student_uuid = ?
            WHERE a.classroom_id = ? AND a.is_published = 1
            ORDER BY a.created_at DESC
        ", [$studentUuid, $classroomId])->getResultObject();

        foreach ($rows as $row) {
            if ($row->sub_id !== null) {
                $row->my_submission = (object) [
                    'id'           => $row->sub_id,
                    'uuid'         => $row->sub_uuid,
                    'content'      => $row->sub_content,
                    'image_paths'  => $row->sub_image_paths
                                        ? json_decode($row->sub_image_paths, true)
                                        : [],
                    'status'       => $row->sub_status,
                    'score'        => $row->sub_score,
                    'feedback'     => $row->sub_feedback,
                    'submitted_at' => $row->sub_submitted_at,
                    'graded_at'    => $row->sub_graded_at,
                ];
            } else {
                $row->my_submission = null;
            }
            unset($row->sub_id, $row->sub_uuid, $row->sub_content, $row->sub_image_paths,
                  $row->sub_status, $row->sub_score, $row->sub_feedback,
                  $row->sub_submitted_at, $row->sub_graded_at);
        }

        return $rows;
    }

    public function findByUuid(string $uuid): ?object
    {
        return $this->model->where('uuid', $uuid)->first();
    }

    public function findById(int $id): ?object
    {
        return $this->model->find($id);
    }

    public function update(int $id, array $data): bool
    {
        return $this->model->update($id, $data);
    }

    public function delete(int $id): bool
    {
        return $this->model->delete($id);
    }
}
