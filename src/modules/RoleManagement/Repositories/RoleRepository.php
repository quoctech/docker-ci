<?php

namespace Modules\RoleManagement\Repositories;

use Modules\RoleManagement\Models\RoleModel;

class RoleRepository
{
    private RoleModel $model;
    private $db;

    public function __construct()
    {
        $this->model = new RoleModel();
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
        return $this->db->table('roles r')
            ->select('r.id, r.uuid, r.name, r.slug, r.description, r.is_active, r.created_at,
                      (SELECT COUNT(*) FROM role_module_permissions rmp WHERE rmp.role_id = r.id AND rmp.can_read = 1) AS module_count', false)
            ->where('r.is_active', 1)
            ->orderBy('r.name', 'ASC')
            ->get()
            ->getResultObject();
    }

    public function findByUuid(string $uuid): ?object
    {
        return $this->model->where('uuid', $uuid)->where('is_active', 1)->first();
    }

    public function findBySlug(string $slug): ?object
    {
        return $this->model->where('slug', $slug)->where('is_active', 1)->first();
    }

    public function create(array $data): ?object
    {
        $id = $this->model->insert([
            'uuid'        => $this->generateUuid(),
            'name'        => $data['name'],
            'slug'        => $data['slug'],
            'description' => $data['description'] ?? null,
            'is_active'   => 1,
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

    public function getPermissions(int $roleId): array
    {
        return $this->db->table('role_module_permissions')
            ->where('role_id', $roleId)
            ->get()
            ->getResultObject();
    }

    /** Trả về danh sách user_uuid đang được liên kết với role này */
    public function getUsersWithRole(int $roleId): array
    {
        return array_column(
            $this->db->table('user_applied_roles')
                ->select('user_uuid')
                ->where('role_id', $roleId)
                ->get()
                ->getResultArray(),
            'user_uuid'
        );
    }

    public function setPermissions(int $roleId, array $permissions): void
    {
        $this->db->transStart();

        $this->db->table('role_module_permissions')
            ->where('role_id', $roleId)
            ->delete();

        foreach ($permissions as $perm) {
            $slug = $perm['slug'] ?? '';
            if (! $slug || empty($perm['can_read'])) continue;

            $this->db->table('role_module_permissions')->insert([
                'role_id'     => $roleId,
                'module_slug' => $slug,
                'can_read'    => 1,
                'can_write'   => empty($perm['can_write'])  ? 0 : 1,
                'can_edit'    => empty($perm['can_edit'])   ? 0 : 1,
                'can_delete'  => empty($perm['can_delete']) ? 0 : 1,
            ]);
        }

        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            throw new \RuntimeException('Lưu phân quyền vai trò thất bại. Vui lòng thử lại.');
        }
    }
}
