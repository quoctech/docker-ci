<?php

namespace Modules\SystemAdmin\Repositories;

class UserModulePermissionRepository
{
    private $db;

    public function __construct()
    {
        $this->db = \Config\Database::connect();
    }

    /** Trả về tất cả permissions của user dưới dạng object đầy đủ */
    public function getByUser(string $userUuid): array
    {
        return $this->db->table('user_module_permissions')
            ->select('module_slug, can_read, can_write, can_edit, can_delete')
            ->where('user_uuid', $userUuid)
            ->get()
            ->getResultObject();
    }

    /** Trả về map slug → {can_read, can_write, can_edit, can_delete} */
    public function getPermissionsMap(string $userUuid): array
    {
        $rows = $this->getByUser($userUuid);
        $map  = [];
        foreach ($rows as $row) {
            $map[$row->module_slug] = [
                'can_read'   => (bool) $row->can_read,
                'can_write'  => (bool) $row->can_write,
                'can_edit'   => (bool) $row->can_edit,
                'can_delete' => (bool) $row->can_delete,
            ];
        }
        return $map;
    }

    /** Trả về danh sách slug mà user có can_read=1 */
    public function getReadableSlugs(string $userUuid): array
    {
        return array_column(
            $this->db->table('user_module_permissions')
                ->select('module_slug')
                ->where('user_uuid', $userUuid)
                ->where('can_read', 1)
                ->get()
                ->getResultArray(),
            'module_slug'
        );
    }

    public function hasPermission(string $userUuid, string $moduleSlug): bool
    {
        return (bool) $this->db->table('user_module_permissions')
            ->where('user_uuid', $userUuid)
            ->where('module_slug', $moduleSlug)
            ->where('can_read', 1)
            ->countAllResults();
    }

    /**
     * Kiểm tra một quyền cụ thể (can_read | can_write | can_edit | can_delete).
     */
    public function hasGranularPermission(string $userUuid, string $moduleSlug, string $permission): bool
    {
        $allowed = ['can_read', 'can_write', 'can_edit', 'can_delete'];
        if (! in_array($permission, $allowed, true)) {
            return false;
        }

        return (bool) $this->db->table('user_module_permissions')
            ->where('user_uuid', $userUuid)
            ->where('module_slug', $moduleSlug)
            ->where($permission, 1)
            ->countAllResults();
    }

    /**
     * Ghi đè toàn bộ permissions cho user trong một transaction.
     * Ném RuntimeException nếu transaction thất bại.
     *
     * @param array $modulePermissions [{slug, can_read, can_write, can_edit, can_delete}, ...]
     */
    public function setPermissions(string $userUuid, array $modulePermissions, string $grantedBy): void
    {
        $this->db->transStart();

        $this->db->table('user_module_permissions')
            ->where('user_uuid', $userUuid)
            ->delete();

        foreach ($modulePermissions as $perm) {
            $slug = $perm['slug'] ?? '';
            if (! $slug) continue;

            $this->db->table('user_module_permissions')->insert([
                'user_uuid'   => $userUuid,
                'module_slug' => $slug,
                'can_read'    => isset($perm['can_read'])   ? (int) $perm['can_read']   : 1,
                'can_write'   => isset($perm['can_write'])  ? (int) $perm['can_write']  : 0,
                'can_edit'    => isset($perm['can_edit'])   ? (int) $perm['can_edit']   : 0,
                'can_delete'  => isset($perm['can_delete']) ? (int) $perm['can_delete'] : 0,
                'granted_by'  => $grantedBy,
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
        }

        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            throw new \RuntimeException('Lưu phân quyền module thất bại. Vui lòng thử lại.');
        }
    }
}
