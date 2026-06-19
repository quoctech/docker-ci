<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Nâng cấp user_module_permissions: thêm 4 cột phân quyền chi tiết.
 *
 * can_read   = truy cập module (sidebar hiện, Ctrl+K tìm được)
 * can_write  = tạo mới
 * can_edit   = chỉnh sửa
 * can_delete = xóa
 *
 * Bản ghi cũ được migrate sang can_read=1 (đã cấp trước đây → mặc định có đọc).
 */
class AddGranularModulePermissions extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('user_module_permissions', [
            'can_read' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'null'       => false,
                'default'    => 0,
                'after'      => 'module_slug',
            ],
            'can_write' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'null'       => false,
                'default'    => 0,
                'after'      => 'can_read',
            ],
            'can_edit' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'null'       => false,
                'default'    => 0,
                'after'      => 'can_write',
            ],
            'can_delete' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'null'       => false,
                'default'    => 0,
                'after'      => 'can_edit',
            ],
        ]);

        // Bản ghi cũ = đã được cấp quyền → mặc định có can_read
        \Config\Database::connect()
            ->table('user_module_permissions')
            ->set('can_read', 1)
            ->update();
    }

    public function down(): void
    {
        $this->forge->dropColumn('user_module_permissions', ['can_read', 'can_write', 'can_edit', 'can_delete']);
    }
}
