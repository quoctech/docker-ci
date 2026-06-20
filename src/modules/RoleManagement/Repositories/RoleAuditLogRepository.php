<?php

namespace Modules\RoleManagement\Repositories;

/**
 * RoleAuditLogRepository - Ghi log thay đổi role/permission vào DB.
 *
 * Mục đích: truy vết (audit trail) — ai đổi quyền gì, khi nào, từ IP nào.
 * Không ảnh hưởng đến business logic — chỉ là log append-only.
 */
class RoleAuditLogRepository
{
    private $db;

    public function __construct()
    {
        $this->db = \Config\Database::connect();
    }

    private function generateUuid(): string
    {
        $hex = bin2hex(random_bytes(16));
        return sprintf('%s-%s-%s-%s-%s',
            substr($hex, 0, 8), substr($hex, 8, 4),
            substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20)
        );
    }

    /**
     * Ghi 1 audit log entry.
     *
     * @param string      $action       Hành động (role_created, role_perm_changed, ...)
     * @param string|null $roleUuid     UUID của role bị ảnh hưởng
     * @param int|null    $roleId       ID của role bị ảnh hưởng
     * @param string|null $userUuid     UUID của user bị ảnh hưởng
     * @param string|null $performedBy  UUID của admin thực hiện
     * @param array|null  $before       Trạng thái trước (sẽ JSON encode)
     * @param array|null  $after        Trạng thái sau (sẽ JSON encode)
     */
    public function log(
        string $action,
        ?string $roleUuid = null,
        ?int $roleId = null,
        ?string $userUuid = null,
        ?string $performedBy = null,
        ?array $before = null,
        ?array $after = null
    ): void {
        try {
            $request = \Config\Services::request();
            $ip      = $request ? $request->getIPAddress() : null;

            $this->db->table('role_permission_audit_logs')->insert([
                'uuid'         => $this->generateUuid(),
                'action'       => $action,
                'role_uuid'    => $roleUuid,
                'role_id'      => $roleId,
                'user_uuid'    => $userUuid,
                'performed_by' => $performedBy,
                'before_json'  => $before !== null ? json_encode($before, JSON_UNESCAPED_UNICODE) : null,
                'after_json'   => $after  !== null ? json_encode($after,  JSON_UNESCAPED_UNICODE) : null,
                'ip_address'   => $ip,
                'created_at'   => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            // Fail-silent — audit log không được làm crash business operation
            log_message('error', '[RoleAuditLog] Failed to write log: ' . $e->getMessage());
        }
    }
}