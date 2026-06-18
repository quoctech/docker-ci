<?php

namespace Modules\Classroom\Repositories;

use Modules\Classroom\Models\ClassroomMemberModel;

class ClassroomMemberRepository
{
    private ClassroomMemberModel $model;

    public function __construct()
    {
        $this->model = new ClassroomMemberModel();
    }

    public function join(int $classroomId, string $studentUuid, bool $autoApprove): ?object
    {
        $existing = $this->model
            ->where('classroom_id', $classroomId)
            ->where('student_uuid', $studentUuid)
            ->first();

        if ($existing) {
            return $existing;
        }

        $now    = date('Y-m-d H:i:s');
        $status = $autoApprove ? 'approved' : 'pending';
        $id     = $this->model->insert([
            'classroom_id' => $classroomId,
            'student_uuid' => $studentUuid,
            'status'       => $status,
            'joined_at'    => $autoApprove ? $now : null,
            'created_at'   => $now,
        ]);

        return $id ? $this->model->find($id) : null;
    }

    public function approve(int $id): bool
    {
        return $this->model->update($id, [
            'status'    => 'approved',
            'joined_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function reject(int $id): bool
    {
        return $this->model->update($id, ['status' => 'rejected']);
    }

    public function remove(int $id): bool
    {
        return $this->model->delete($id);
    }

    public function listByClassroom(int $classroomId, ?string $status = null): array
    {
        $db = \Config\Database::connect();
        $statusSql = $status ? "AND cm.status = " . $db->escape($status) : "";

        return $db->query("
            SELECT cm.*, u.full_name, u.email, u.username, u.grade AS student_grade
            FROM classroom_members cm
            JOIN users u ON u.uuid = cm.student_uuid
            WHERE cm.classroom_id = ? {$statusSql}
            ORDER BY cm.status ASC, cm.created_at DESC
        ", [$classroomId])->getResultObject();
    }

    public function isEnrolled(int $classroomId, string $studentUuid): ?object
    {
        return $this->model
            ->where('classroom_id', $classroomId)
            ->where('student_uuid', $studentUuid)
            ->first();
    }

    public function myClassrooms(string $studentUuid): array
    {
        $db = \Config\Database::connect();
        return $db->query("
            SELECT c.id AS classroom_id, c.uuid AS classroom_uuid,
                   c.name AS classroom_name, c.subject, c.grade,
                   cm.id AS member_id, cm.status, cm.joined_at,
                   u.full_name AS teacher_name,
                   (SELECT COUNT(*) FROM assignments a WHERE a.classroom_id = c.id AND a.is_published = 1) AS assignment_count
            FROM classroom_members cm
            JOIN classrooms c ON c.id = cm.classroom_id AND c.is_active = 1
            JOIN users u ON u.uuid = c.teacher_uuid
            WHERE cm.student_uuid = ? AND cm.status != 'rejected'
            ORDER BY cm.joined_at DESC, cm.created_at DESC
        ", [$studentUuid])->getResultObject();
    }
}
