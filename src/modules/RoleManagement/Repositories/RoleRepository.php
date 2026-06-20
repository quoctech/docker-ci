<?php

namespace Modules\RoleManagement\Repositories;

use Modules\RoleManagement\Models\RoleModel;

/**
 * RoleRepository - Thao tác với bảng roles + role_module_permissions.
 *
 * Liên kết với UserPermissionRepository (JOIN user_applied_roles → role_module_permissions)
 * để lấy quyền thực tế của user — không copy dữ liệu.
 *
 * Soft-delete + tạo lại: tận dụng row cũ thay vì tạo row mới.
 *   - Tạo role: nếu slug đã tồn tại với is_active=0 → REVIVE row đó (set is_active=1, update tên)
 *   - Nếu slug đã tồn tại với is_active=1 → báo lỗi trùng
 *   - Nếu slug chưa tồn tại → INSERT mới
 *
 * Cách này KHÔNG cần virtual column / partial unique index → đơn giản hơn nhiều.
 */
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
            ->select('r.id, r.uuid, r.name, r.slug, r.description, r.is_active, r.perm_version, r.created_at,
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

    /**
     * Tìm role theo slug, kể cả soft-deleted.
     * Dùng để kiểm tra khi tạo role: nếu đã có row inactive → revive thay vì tạo mới.
     */
    public function findBySlugAny(string $slug): ?object
    {
        return $this->model->where('slug', $slug)->first();
    }

    /**
     * Tạo role MỚI hoặc REVIVE role đã soft-delete (cùng slug).
     *
     * Logic:
     *   - Nếu có row inactive với slug này → REVIVE (set is_active=1, cập nhật name, description, bump perm_version)
     *   - Nếu có row active với slug này → throw exception (slug trùng)
     *   - Nếu chưa có → INSERT mới
     *
     * Cách này giải quyết bug "Duplicate entry 'giao-vien' for key 'slug'" mà KHÔNG
     * cần virtual column / partial unique index ở DB.
     *
     * @return array{role: object, revived: bool}
     * @throws \RuntimeException khi slug đang được dùng bởi role active
     */
    public function createOrRevive(array $data): array
    {
        $slug = $data['slug'] ?? '';
        $existing = $slug ? $this->findBySlugAny($slug) : null;

        if ($existing && (int) $existing->is_active === 1) {
            throw new \RuntimeException("Slug '{$slug}' đã được sử dụng bởi vai trò đang hoạt động.");
        }

        if ($existing) {
            // REVIVE row cũ (giữ uuid để không phá reference trong role_module_permissions,
            // user_applied_roles, audit log...)
            $this->model->update($existing->id, [
                'name'        => $data['name'],
                'description' => $data['description'] ?? null,
                'is_active'   => 1,
                'perm_version' => 1,
            ]);
            return [
                'role'    => $this->model->find($existing->id),
                'revived' => true,
            ];
        }

        // Tạo mới
        $id = $this->model->insert([
            'uuid'        => $this->generateUuid(),
            'name'        => $data['name'],
            'slug'        => $data['slug'],
            'description' => $data['description'] ?? null,
            'is_active'   => 1,
            'perm_version' => 1,
        ]);

        if (! $id) {
            throw new \RuntimeException('Không thể tạo vai trò.');
        }

        return [
            'role'    => $this->model->find($id),
            'revived' => false,
        ];
    }

    public function update(int $id, array $data): void
    {
        $this->model->update($id, $data);
    }

    /**
     * Soft-delete role: chỉ set is_active=0.
     * Slug không đổi — khi tạo lại sẽ được revive (xem createOrRevive()).
     */
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

    /**
     * Ghi permission mới cho role. Bump perm_version.
     *
     * Trả về danh sách user_uuid bị ảnh hưởng để controller xóa cache.
     */
    public function setPermissions(int $roleId, array $permissions): array
    {
        $this->db->transStart();

        $this->db->table('role_module_permissions')
            ->where('role_id', $roleId)
            ->delete();

        foreach ($permissions as $perm) {
            $slug = $perm['slug'] ?? '';
            if (! $slug || empty($perm['can_read'])) {
                continue;
            }

            $this->db->table('role_module_permissions')->insert([
                'role_id'     => $roleId,
                'module_slug' => $slug,
                'can_read'    => 1,
                'can_write'   => empty($perm['can_write'])  ? 0 : 1,
                'can_edit'    => empty($perm['can_edit'])   ? 0 : 1,
                'can_delete'  => empty($perm['can_delete']) ? 0 : 1,
            ]);
        }

        // Bump perm_version
        $this->db->table('roles')
            ->where('id', $roleId)
            ->increment('perm_version');

        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            throw new \RuntimeException('Lưu phân quyền vai trò thất bại. Vui lòng thử lại.');
        }

        return $this->getUsersWithRole($roleId);
    }

    /**
     * Bump perm_version cho role (không thay đổi permission thực tế).
     */
    public function bumpPermVersion(int $roleId): void
    {
        $this->db->table('roles')
            ->where('id', $roleId)
            ->increment('perm_version');
    }
}