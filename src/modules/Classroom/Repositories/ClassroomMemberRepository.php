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
        $db      = \Config\Database::connect();
        $builder = $db->table('classroom_members cm')
            ->select('cm.*, u.full_name, u.email, u.username, u.grade AS student_grade', false)
            ->join('users u', 'u.uuid = cm.student_uuid', 'inner', false)
            ->where('cm.classroom_id', $classroomId)
            ->orderBy('cm.status', 'ASC')
            ->orderBy('cm.created_at', 'DESC');

        if ($status !== null) {
            $builder->where('cm.status', $status);
        }

        return $builder->get()->getResultObject();
    }

    public function isEnrolled(int $classroomId, string $studentUuid): ?object
    {
        return $this->model
            ->where('classroom_id', $classroomId)
            ->where('student_uuid', $studentUuid)
            ->first();
    }

    public function allStudentsByTeacher(?string $teacherUuid): array
    {
        $db = \Config\Database::connect();

        $builder = $db->table('classroom_members cm')
            ->select('u.full_name, u.email, u.username', false)
            ->select('c.uuid AS classroom_uuid, c.name AS classroom_name, c.subject, c.grade', false)
            ->select('cm.id AS member_id, cm.status, cm.joined_at', false)
            ->select('(SELECT COUNT(*) FROM assignment_submissions s JOIN assignments a ON a.id = s.assignment_id WHERE s.student_uuid = u.uuid AND a.classroom_id = c.id) AS submission_count', false)
            ->join('classrooms c', 'c.id = cm.classroom_id', 'inner', false)
            ->join('users u', 'u.uuid = cm.student_uuid', 'inner', false)
            ->where('c.is_active', 1)
            ->where('cm.status', 'approved')
            ->orderBy('c.name', 'ASC')
            ->orderBy('u.full_name', 'ASC');

        if ($teacherUuid !== null) {
            $builder->where('c.teacher_uuid', $teacherUuid);
        }

        return $builder->get()->getResultObject();
    }

    public function myClassrooms(string $studentUuid): array
    {
        $db = \Config\Database::connect();
        return $db->table('classroom_members cm')
            ->select('c.id AS classroom_id, c.uuid AS classroom_uuid, c.name AS classroom_name, c.subject, c.grade', false)
            ->select('cm.id AS member_id, cm.status, cm.joined_at', false)
            ->select('u.full_name AS teacher_name', false)
            ->select('(SELECT COUNT(*) FROM assignments a WHERE a.classroom_id = c.id AND a.is_published = 1) AS assignment_count', false)
            ->join('classrooms c', 'c.id = cm.classroom_id', 'inner', false)
            ->join('users u', 'u.uuid = c.teacher_uuid', 'inner', false)
            ->where('c.is_active', 1)
            ->where('cm.student_uuid', $studentUuid)
            ->where('cm.status !=', 'rejected')
            ->orderBy('cm.joined_at', 'DESC')
            ->orderBy('cm.created_at', 'DESC')
            ->get()
            ->getResultObject();
    }
}
