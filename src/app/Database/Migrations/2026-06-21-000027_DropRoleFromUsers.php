<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Loại bỏ cột `role` trong bảng `users`.
 *
 * Lý do: Role của user phải được gán qua bảng `roles` + `user_applied_roles`
 * (không hard-code trong users). Ngoại lệ duy nhất là SUPER_ADMIN — chỉ 1 tài khoản
 * duy nhất, đánh dấu qua cờ `is_super_admin`.
 *
 * Sau migration này:
 *   - users.is_super_admin = 1   → role = 'super_admin' (chỉ 1 user duy nhất)
 *   - users.is_super_admin = 0   → role = slug của role trong user_applied_roles
 *                                (mặc định 'user' nếu chưa được gán role nào)
 *
 * Khôi phục (down): thêm lại cột role với giá trị mặc định 'user'.
 */
class DropRoleFromUsers extends Migration
{
    public function up(): void
    {
        $db = \Config\Database::connect();

        // Bước 1: Thêm cột is_super_admin (boolean flag)
        $this->forge->addColumn('users', [
            'is_super_admin' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'null'       => false,
                'default'    => 0,
                'after'      => 'status',
            ],
        ]);

        // Bước 2: Đánh dấu user super_admin hiện tại (chỉ user đầu tiên có role='super_admin')
        // Lưu ý: chỉ 1 user duy nhất được set is_super_admin=1
        $db->query("
            UPDATE users
            SET is_super_admin = 1
            WHERE role = 'super_admin'
            ORDER BY created_at ASC
            LIMIT 1
        ");

        // Bước 3: Drop cột role
        // (CI4 Forge không drop column đơn lẻ qua method riêng — dùng raw SQL an toàn)
        $db->query("ALTER TABLE users DROP COLUMN role");
    }

    public function down(): void
    {
        $db = \Config\Database::connect();

        // Thêm lại cột role
        $db->query("
            ALTER TABLE users
            ADD COLUMN role VARCHAR(50) NOT NULL DEFAULT 'user' AFTER status
        ");

        // Khôi phục role từ user_applied_roles (lấy slug của role đầu tiên)
        // Nếu là super_admin thì set role='super_admin'
        $db->query("
            UPDATE users u
            SET u.role = CASE
                WHEN u.is_super_admin = 1 THEN 'super_admin'
                ELSE COALESCE(
                    (SELECT r.slug FROM user_applied_roles uar
                     JOIN roles r ON r.id = uar.role_id
                     WHERE uar.user_uuid = u.uuid
                     ORDER BY uar.applied_at ASC
                     LIMIT 1),
                    'user'
                )
            END
        ");

        // Drop cột is_super_admin
        $db->query("ALTER TABLE users DROP COLUMN is_super_admin");
    }
}