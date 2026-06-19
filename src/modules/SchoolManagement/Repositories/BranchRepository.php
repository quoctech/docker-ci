<?php

namespace Modules\SchoolManagement\Repositories;

use Modules\SchoolManagement\Models\BranchModel;

class BranchRepository
{
    private BranchModel $model;
    private $db;

    public function __construct()
    {
        $this->model = new BranchModel();
        $this->db    = \Config\Database::connect();
    }

    private function generateUuid(): string
    {
        $hex = bin2hex(random_bytes(16));
        return sprintf('%s-%s-%s-%s-%s',
            substr($hex, 0, 8), substr($hex, 8, 4),
            substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20)
        );
    }

    public function list(): array
    {
        return $this->db->table('branches b')
            ->select('b.uuid, b.name, b.address, b.phone, b.email, b.manager, b.created_at,
                      c.uuid AS center_uuid, c.name AS center_name,
                      (SELECT COUNT(*) FROM rooms r WHERE r.branch_id = b.id AND r.is_active = 1) AS room_count', false)
            ->join('centers c', 'c.id = b.center_id', 'left', false)
            ->where('b.is_active', 1)
            ->orderBy('b.name', 'ASC')
            ->get()
            ->getResultObject();
    }

    public function findByUuid(string $uuid): ?object
    {
        $row = $this->db->table('branches b')
            ->select('b.id, b.uuid, b.name, b.address, b.phone, b.email, b.manager, b.created_at,
                      c.uuid AS center_uuid, c.name AS center_name', false)
            ->join('centers c', 'c.id = b.center_id', 'left', false)
            ->where('b.uuid', $uuid)
            ->where('b.is_active', 1)
            ->get()
            ->getFirstRow();

        return $row ?: null;
    }

    /** Row thô (có id) để update/deactivate */
    public function findRawByUuid(string $uuid): ?object
    {
        return $this->model->where('uuid', $uuid)->where('is_active', 1)->first();
    }

    /** Kiểm tra tên trùng trong cùng center (hoặc global nếu không có center) */
    public function nameExistsInScope(string $name, ?int $centerId, ?string $excludeUuid = null): bool
    {
        // MySQL default collation là case-insensitive, nên where('name', $name) đủ
        $builder = $this->db->table('branches')
            ->where('name', $name)
            ->where('is_active', 1);

        if ($centerId !== null) {
            $builder->where('center_id', $centerId);
        } else {
            $builder->where('center_id', null); // CI4 generates: center_id IS NULL
        }

        if ($excludeUuid !== null) {
            $builder->where('uuid !=', $excludeUuid);
        }

        return $builder->countAllResults() > 0;
    }

    public function create(array $data): ?object
    {
        $uuid = $this->generateUuid();
        $id   = $this->model->insert([
            'uuid'      => $uuid,
            'center_id' => $data['center_id'] ?? null,
            'name'      => $data['name'],
            'address'   => $data['address'] ?? null,
            'phone'     => $data['phone']   ?? null,
            'email'     => $data['email']   ?? null,
            'manager'   => $data['manager'] ?? null,
            'is_active' => 1,
        ]);

        return $id ? $this->findByUuid($uuid) : null;
    }

    public function update(int $id, array $data): void
    {
        $this->model->update($id, $data);
    }

    public function countActiveRooms(int $branchId): int
    {
        return (int) $this->db->table('rooms')
            ->where('branch_id', $branchId)
            ->where('is_active', 1)
            ->countAllResults();
    }

    public function deactivate(int $id): void
    {
        $this->model->update($id, ['is_active' => 0]);
    }
}
