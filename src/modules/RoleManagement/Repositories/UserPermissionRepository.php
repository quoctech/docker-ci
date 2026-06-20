<?php

namespace Modules\RoleManagement\Repositories;

use App\Libraries\RedisService;

/**
 * UserPermissionRepository — Single Source of Truth cho quyền user.
 *
 * Triết lý (Phương án A): quyền của user = UNION quyền của TẤT CẢ role user được gán.
 * Không lưu quyền trực tiếp trên user → không drift, role đổi là user đổi theo.
 *
 * Cache qua Redis:
 * - Key: `perm:user:{uuid}` — JSON map slug => {can_read, can_write, can_edit, can_delete}
 * - TTL: USER_PERM_CACHE_TTL (1 giờ)
 * - Invalidate khi:
 *   1. Role permission thay đổi → DEL cache của tất cả user có role đó
 *   2. User được gán/bỏ gán role → DEL cache của user đó
 *   3. Role bị tắt/xóa → DEL cache của tất cả user có role đó
 */
class UserPermissionRepository
{
    /** @var \CodeIgniter\Database\BaseConnection */
    private $db;

    public function __construct()
    {
        $this->db = \Config\Database::connect();
    }

    /**
     * Lấy toàn bộ permission map của user, có cache.
     *
     * @param string $userUuid User UUID
     * @return array Map slug => ['can_read' => bool, 'can_write' => bool, 'can_edit' => bool, 'can_delete' => bool]
     */
    public function getPermissionsMap(string $userUuid): array
    {
        // 1. Thử cache
        $cached = RedisService::getUserPermCache($userUuid);
        if ($cached !== null) {
            return $cached;
        }

        // 2. Cache miss → query DB (JOIN role)
        $map = $this->loadFromDb($userUuid);

        // 3. Lưu cache (kể cả khi rỗng — tránh spam DB khi user không có role)
        RedisService::setUserPermCache($userUuid, $map);

        return $map;
    }

    /**
     * Đọc trực tiếp từ DB (bỏ qua cache). Dùng trong controller khi muốn dữ liệu fresh.
     *
     * Logic UNION: nếu user có nhiều role → lấy MAX(can_read), MAX(can_write)...
     * của tất cả role. (Vì role là chỉ định quyền, user có role nào thì có quyền đó.)
     */
    public function loadFromDb(string $userUuid): array
    {
        $rows = $this->db->table('user_applied_roles uar')
            ->select('uar.role_id, rmp.module_slug, rmp.can_read, rmp.can_write, rmp.can_edit, rmp.can_delete', false)
            ->join('roles r', 'r.id = uar.role_id AND r.is_active = 1', 'inner', false)
            ->join('role_module_permissions rmp', 'rmp.role_id = uar.role_id', 'inner', false)
            ->where('uar.user_uuid', $userUuid)
            ->get()
            ->getResultArray();

        $map = [];
        foreach ($rows as $row) {
            $slug = $row['module_slug'];
            if (! isset($map[$slug])) {
                $map[$slug] = [
                    'can_read'   => false,
                    'can_write'  => false,
                    'can_edit'   => false,
                    'can_delete' => false,
                ];
            }

            // UNION: cộng dồn từ mỗi role user được gán
            $map[$slug]['can_read']   = $map[$slug]['can_read']   || (bool) $row['can_read'];
            $map[$slug]['can_write']  = $map[$slug]['can_write']  || (bool) $row['can_write'];
            $map[$slug]['can_edit']   = $map[$slug]['can_edit']   || (bool) $row['can_edit'];
            $map[$slug]['can_delete'] = $map[$slug]['can_delete'] || (bool) $row['can_delete'];
        }

        return $map;
    }

    /**
     * Lấy danh sách slug mà user có can_read=1.
     *
     * @param string $userUuid
     * @return string[]
     */
    public function getReadableSlugs(string $userUuid): array
    {
        $map = $this->getPermissionsMap($userUuid);

        return array_values(array_filter(
            array_keys($map),
            fn($slug) => ! empty($map[$slug]['can_read'])
        ));
    }

    /**
     * Kiểm tra user có quyền truy cập module không (can_read).
     */
    public function hasPermission(string $userUuid, string $moduleSlug): bool
    {
        $map = $this->getPermissionsMap($userUuid);
        return ! empty($map[$moduleSlug]['can_read']);
    }

    /**
     * Kiểm tra 1 quyền cụ thể (can_read | can_write | can_edit | can_delete).
     *
     * Nếu quyền không tồn tại → false.
     */
    public function hasGranularPermission(string $userUuid, string $moduleSlug, string $permission): bool
    {
        $allowed = ['can_read', 'can_write', 'can_edit', 'can_delete'];
        if (! in_array($permission, $allowed, true)) {
            return false;
        }

        $map = $this->getPermissionsMap($userUuid);
        return ! empty($map[$moduleSlug][$permission]);
    }

    /**
     * Xóa cache permission của user. Gọi khi:
     * - User được gán/bỏ gán role
     * - User được áp dụng role mới
     */
    public function invalidateCache(string $userUuid): void
    {
        RedisService::invalidateUserPermCache($userUuid);
    }

    /**
     * Xóa cache permission của nhiều user cùng lúc.
     * Gọi khi role permission thay đổi (tất cả user có role đó cần refresh).
     *
     * @param string[] $userUuids
     */
    public function invalidateCacheBatch(array $userUuids): void
    {
        RedisService::invalidateUserPermCacheBatch($userUuids);
    }

    /**
     * Lấy danh sách user_uuid đang được gán role cụ thể.
     * Dùng để xóa cache khi role thay đổi.
     *
     * @return string[]
     */
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
}