<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tạo bảng audit log cho việc thay đổi role/permission.
 *
 * Ghi lại mọi thay đổi:
 * - role_created       : tạo role mới
 * - role_updated       : đổi tên/mô tả
 * - role_deleted       : soft delete role
 * - role_perm_changed  : thay đổi permission của role (kèm before/after JSON)
 * - role_applied       : gán role cho user
 * - role_unapplied     : bỏ gán role khỏi user
 *
 * Dùng để debug + compliance (truy vết ai đổi quyền gì, khi nào).
 */
class CreateRolePermissionAuditLogs extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'uuid' => [
                'type'       => 'VARCHAR',
                'constraint' => 36,
                'null'       => false,
            ],
            'action' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
                'null'       => false,
                'comment'    => 'role_created|role_updated|role_deleted|role_perm_changed|role_applied|role_unapplied',
            ],
            'role_uuid' => [
                'type'       => 'VARCHAR',
                'constraint' => 36,
                'null'       => true,
            ],
            'role_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
                'null'     => true,
            ],
            'user_uuid' => [
                'type'       => 'VARCHAR',
                'constraint' => 36,
                'null'       => true,
                'comment'    => 'user bị ảnh hưởng (khi áp dụng role)',
            ],
            'performed_by' => [
                'type'       => 'VARCHAR',
                'constraint' => 36,
                'null'       => true,
                'comment'    => 'admin/user thực hiện thay đổi',
            ],
            'before_json' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'after_json' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'ip_address' => [
                'type'       => 'VARCHAR',
                'constraint' => 45,
                'null'       => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('uuid');
        $this->forge->addKey('action');
        $this->forge->addKey('role_id');
        $this->forge->addKey('user_uuid');
        $this->forge->addKey('performed_by');
        $this->forge->addKey('created_at');
        $this->forge->createTable('role_permission_audit_logs');
    }

    public function down(): void
    {
        $this->forge->dropTable('role_permission_audit_logs', true);
    }
}