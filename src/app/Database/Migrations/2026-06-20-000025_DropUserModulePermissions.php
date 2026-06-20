<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Drop bảng user_module_permissions (Phương án A — Single Source of Truth).
 *
 * Bảng này trước đây lưu quyền TRỰC TIẾP trên user, gây ra:
 * - Drift dữ liệu khi role đổi
 * - Copy dữ liệu phức tạp khi áp dụng role
 * - Hai nguồn dữ liệu (role + user_module_permissions)
 *
 * Sau migration này, quyền của user = UNION quyền của TẤT CẢ role user được gán
 * (xem UserPermissionRepository).
 */
class DropUserModulePermissions extends Migration
{
    public function up(): void
    {
        // Bảng user_module_permissions có thể đã được drop từ migration cũ
        // Kiểm tra trước khi drop để tránh lỗi
        $db = \Config\Database::connect();

        if ($db->tableExists('user_module_permissions')) {
            $this->forge->dropTable('user_module_permissions', true);
        }
    }

    public function down(): void
    {
        // Khôi phục bảng nếu rollback
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 10,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'user_uuid' => [
                'type'       => 'VARCHAR',
                'constraint' => 36,
                'null'       => false,
            ],
            'module_slug' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
                'null'       => false,
            ],
            'can_read' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
            ],
            'can_write' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
            ],
            'can_edit' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
            ],
            'can_delete' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
            ],
            'granted_by' => [
                'type'       => 'VARCHAR',
                'constraint' => 36,
                'null'       => true,
                'default'    => null,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['user_uuid', 'module_slug']);
        $this->forge->addKey('user_uuid');
        $this->forge->addKey('module_slug');
        $this->forge->createTable('user_module_permissions', true);
    }
}