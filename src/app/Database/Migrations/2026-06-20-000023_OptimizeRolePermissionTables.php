<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tối ưu schema Role/Permission (Phương án A — Single Source of Truth).
 *
 * Thay đổi:
 * 1. Thêm `perm_version` vào `roles` — dùng để bump version khi permission thay đổi,
 *    giúp invalidate cache permissions của tất cả user đang dùng role đó.
 * 2. Bỏ UNIQUE(user_uuid) trên `user_applied_roles` — cho phép 1 user gán nhiều role,
 *    quyền cuối cùng = UNION của tất cả role user được gán.
 *
 * Lưu ý: bảng `user_module_permissions` vẫn còn ở migration này, sẽ được drop
 * ở migration tiếp theo (sau khi các nơi sử dụng đã chuyển sang JOIN qua role).
 */
class OptimizeRolePermissionTables extends Migration
{
    public function up(): void
    {
        // 1. Thêm perm_version vào roles
        $this->forge->addColumn('roles', [
            'perm_version' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => false,
                'default'  => 1,
                'after'    => 'is_active',
            ],
        ]);

        // 2. Bỏ UNIQUE(user_uuid) trên user_applied_roles — cho phép multi-role.
        // CI4 Forge không drop unique key trực tiếp bằng tên; dùng query thô
        // cho thao tác metadata (an toàn — chỉ thay đổi index, không đụng data).
        $db = \Config\Database::connect();

        $indexes = $db->query("SHOW INDEX FROM user_applied_roles")->getResultArray();

        $uniqueOnUserUuid = null;
        foreach ($indexes as $idx) {
            if ((int) $idx['Non_unique'] === 0 && $idx['Column_name'] === 'user_uuid') {
                $uniqueOnUserUuid = $idx['Key_name'];
                break;
            }
        }

        if ($uniqueOnUserUuid !== null) {
            $db->query("ALTER TABLE user_applied_roles DROP INDEX `{$uniqueOnUserUuid}`");
        }

        // Thêm lại index thường (không unique) trên user_uuid để query nhanh
        $this->forge->addKey('user_uuid', false, false, 'uar_user_uuid_idx');
        $this->forge->processIndexes('user_applied_roles');
    }

    public function down(): void
    {
        // Khôi phục lại UNIQUE(user_uuid) — cẩn thận nếu đã có duplicate.
        $db = \Config\Database::connect();

        // Xóa duplicate (giữ bản ghi id nhỏ nhất cho mỗi user_uuid)
        $db->query("
            DELETE uar1 FROM user_applied_roles uar1
            INNER JOIN user_applied_roles uar2
            WHERE uar1.id > uar2.id
              AND uar1.user_uuid = uar2.user_uuid
        ");

        // Xóa cột perm_version khỏi roles
        $this->forge->dropColumn('roles', 'perm_version');

        // Thêm lại UNIQUE(user_uuid)
        $this->forge->addUniqueKey('user_uuid', 'uar_user_uuid_unique');
        $this->forge->processIndexes('user_applied_roles');
    }
}