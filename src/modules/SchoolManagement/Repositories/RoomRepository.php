<?php

namespace Modules\SchoolManagement\Repositories;

use Modules\SchoolManagement\Models\RoomModel;

class RoomRepository
{
    private RoomModel $model;

    public function __construct()
    {
        $this->model = new RoomModel();
    }

    private function generateUuid(): string
    {
        $hex = bin2hex(random_bytes(16));
        return sprintf('%s-%s-%s-%s-%s',
            substr($hex, 0, 8), substr($hex, 8, 4),
            substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20)
        );
    }

    public function list(?string $branchUuid = null): array
    {
        $db      = \Config\Database::connect();
        $builder = $db->table('rooms r')
            ->select('r.uuid, r.branch_id, r.name, r.capacity, r.room_type, r.created_at,
                      b.name AS branch_name, b.uuid AS branch_uuid', false)
            ->join('branches b', 'b.id = r.branch_id', 'left', false)
            ->where('r.is_active', 1)
            ->orderBy('b.name', 'ASC')
            ->orderBy('r.name', 'ASC');

        if ($branchUuid !== null) {
            $builder->where('b.uuid', $branchUuid);
        }

        return $builder->get()->getResultObject();
    }

    public function findByUuid(string $uuid): ?object
    {
        return $this->model->where('uuid', $uuid)->where('is_active', 1)->first();
    }

    public function findByUuidWithBranch(string $uuid): ?object
    {
        $db = \Config\Database::connect();
        return $db->table('rooms r')
            ->select('r.uuid, r.branch_id, r.name, r.capacity, r.room_type, r.created_at,
                      b.name AS branch_name, b.uuid AS branch_uuid', false)
            ->join('branches b', 'b.id = r.branch_id', 'left', false)
            ->where('r.uuid', $uuid)
            ->where('r.is_active', 1)
            ->get()
            ->getFirstRow();
    }

    public function create(array $data): ?object
    {
        $uuid = $this->generateUuid();
        $id   = $this->model->insert([
            'uuid'      => $uuid,
            'branch_id' => $data['branch_id'],
            'name'      => $data['name'],
            'capacity'  => isset($data['capacity']) && $data['capacity'] !== '' ? (int) $data['capacity'] : null,
            'room_type' => $data['room_type'] ?? null,
            'is_active' => 1,
        ]);

        return $id ? $this->findByUuidWithBranch($uuid) : null;
    }

    public function update(int $id, array $data): void
    {
        $this->model->update($id, $data);
    }

    public function deactivate(int $id): void
    {
        $this->model->update($id, ['is_active' => 0]);
    }
}
