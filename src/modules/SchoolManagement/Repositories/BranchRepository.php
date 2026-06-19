<?php

namespace Modules\SchoolManagement\Repositories;

use Modules\SchoolManagement\Models\BranchModel;

class BranchRepository
{
    private BranchModel $model;

    public function __construct()
    {
        $this->model = new BranchModel();
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
        $db = \Config\Database::connect();
        return $db->table('branches b')
            ->select('b.uuid, b.name, b.address, b.phone, b.email, b.created_at,
                      (SELECT COUNT(*) FROM rooms r WHERE r.branch_id = b.id AND r.is_active = 1) AS room_count', false)
            ->where('b.is_active', 1)
            ->orderBy('b.name', 'ASC')
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
            'phone'     => $data['phone'] ?? null,
            'email'     => $data['email'] ?? null,
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
}
