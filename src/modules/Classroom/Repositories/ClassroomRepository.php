<?php

namespace Modules\Classroom\Repositories;

use Modules\Classroom\Models\ClassroomModel;

class ClassroomRepository
{
    private ClassroomModel $model;

    public function __construct()
    {
        $this->model = new ClassroomModel();
    }

    public function create(string $teacherUuid, array $data): ?object
    {
        $uuid = bin2hex(random_bytes(16));
        $uuid = sprintf('%s-%s-%s-%s-%s',
            substr($uuid, 0, 8), substr($uuid, 8, 4),
            substr($uuid, 12, 4), substr($uuid, 16, 4), substr($uuid, 20)
        );

        $id = $this->model->insert([
            'uuid'         => $uuid,
            'teacher_uuid' => $teacherUuid,
            'name'         => $data['name'],
            'description'  => $data['description'] ?? null,
            'code'         => $data['code'],
            'subject'      => $data['subject'] ?? null,
            'grade'        => isset($data['grade']) && $data['grade'] !== '' ? (int) $data['grade'] : null,
            'auto_approve' => isset($data['auto_approve']) ? (int) $data['auto_approve'] : 1,
            'is_active'    => 1,
        ]);

        return $id ? $this->findById($id) : null;
    }

    public function listByTeacher(string $teacherUuid): array
    {
        $db = \Config\Database::connect();
        return $db->query("
            SELECT c.*,
                   (SELECT COUNT(*) FROM classroom_members cm WHERE cm.classroom_id = c.id AND cm.status = 'approved') AS member_count,
                   (SELECT COUNT(*) FROM classroom_members cm WHERE cm.classroom_id = c.id AND cm.status = 'pending')  AS pending_count,
                   (SELECT COUNT(*) FROM assignments a WHERE a.classroom_id = c.id AND a.is_published = 1)             AS assignment_count
            FROM classrooms c
            WHERE c.teacher_uuid = ? AND c.is_active = 1
            ORDER BY c.created_at DESC
        ", [$teacherUuid])->getResultObject();
    }

    public function findByUuid(string $uuid): ?object
    {
        return $this->model->where('uuid', $uuid)->where('is_active', 1)->first();
    }

    public function findByCode(string $code): ?object
    {
        return $this->model->where('code', strtoupper($code))->where('is_active', 1)->first();
    }

    public function findById(int $id): ?object
    {
        return $this->model->find($id);
    }

    public function update(int $id, array $data): bool
    {
        return $this->model->update($id, $data);
    }

    public function deactivate(int $id): bool
    {
        return $this->model->update($id, ['is_active' => 0]);
    }

    public function generateCode(string $username): string
    {
        $prefix = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $username), 0, 3));
        $prefix = str_pad($prefix, 3, 'X');

        do {
            $suffix = strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
            $code   = $prefix . '-' . $suffix;
        } while ($this->model->where('code', $code)->countAllResults() > 0);

        return $code;
    }
}
