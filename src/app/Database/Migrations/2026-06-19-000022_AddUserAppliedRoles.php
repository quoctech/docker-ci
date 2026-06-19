<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Theo dõi role nào đang được áp dụng cho mỗi user.
 *
 * Khi role thay đổi quyền module, hệ thống tự động sync
 * sang tất cả user được liên kết với role đó.
 */
class AddUserAppliedRoles extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'user_uuid' => [
                'type'       => 'VARCHAR',
                'constraint' => 36,
                'null'       => false,
            ],
            'role_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
                'null'     => false,
            ],
            'applied_by' => [
                'type'       => 'VARCHAR',
                'constraint' => 36,
                'null'       => true,
                'default'    => null,
            ],
            'applied_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('user_uuid');
        $this->forge->addKey('role_id');
        $this->forge->createTable('user_applied_roles');
    }

    public function down(): void
    {
        $this->forge->dropTable('user_applied_roles', true);
    }
}
