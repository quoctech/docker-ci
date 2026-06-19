<?php

namespace Modules\SchoolManagement\Repositories;

use Modules\SchoolManagement\Models\CenterModel;

class CenterRepository
{
    private CenterModel $model;
    private $db;

    public function __construct()
    {
        $this->model = new CenterModel();
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
        return $this->db->table('centers c')
            ->select('c.uuid, c.name, c.address, c.phone, c.email, c.created_at,
                      (SELECT COUNT(*) FROM branches b WHERE b.center_id = c.id AND b.is_active = 1) AS branch_count', false)
            ->where('c.is_active', 1)
            ->orderBy('c.name', 'ASC')
            ->get()
            ->getResultObject();
    }

    public function findByUuid(string $uuid): ?object
    {
        return $this->model->where('uuid', $uuid)->where('is_active', 1)->first();
    }

    public function create(array $data): ?object
    {
        $id = $this->model->insert([
            'uuid'      => $this->generateUuid(),
            'name'      => $data['name'],
            'address'   => $data['address'] ?? null,
            'phone'     => $data['phone']   ?? null,
            'email'     => $data['email']   ?? null,
            'is_active' => 1,
        ]);

        return $id ? $this->model->find($id) : null;
    }

    public function update(int $id, array $data): void
    {
        $this->model->update($id, $data);
    }

    public function deactivate(int $id): void
    {
        $this->model->update($id, ['is_active' => 0]);
    }

    public function countActiveBranches(int $centerId): int
    {
        return (int) $this->db->table('branches')
            ->where('center_id', $centerId)
            ->where('is_active', 1)
            ->countAllResults();
    }
}
