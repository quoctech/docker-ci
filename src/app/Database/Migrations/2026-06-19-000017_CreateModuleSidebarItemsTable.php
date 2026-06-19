<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tạo bảng module_sidebar_items — cho phép mỗi module tự đăng ký
 * nav items của mình vào sidebar mà không cần sửa sidebar.php.
 *
 * Cách dùng khi thêm module mới:
 *   $db->table('module_sidebar_items')->insertBatch([...])
 *   trong migration hoặc install script của module đó.
 *
 * Cột allowed_roles (JSON):
 *   ["workspace_admin", "super_admin"]  → x-show: hasModule(slug)        (cả hai role thấy)
 *   ["workspace_admin"]                 → x-show: hasModule + loại super_admin (super_admin có chỗ khác)
 *   ["user"]                            → x-show: user.role === 'user'
 */
class CreateModuleSidebarItemsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'SMALLINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'module_slug' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
                'null'       => false,
                'comment'    => 'Phải tương ứng với slug trong bảng modules',
            ],
            'group_label' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => false,
                'comment'    => 'Tiêu đề nhóm hiển thị trên sidebar',
            ],
            'label' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => false,
            ],
            'url' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
            ],
            'icon' => [
                'type'       => 'VARCHAR',
                'constraint' => 10,
                'default'    => '🧩',
            ],
            'allowed_roles' => [
                'type'    => 'JSON',
                'null'    => false,
                'comment' => '["workspace_admin","super_admin"] | ["workspace_admin"] | ["user"]',
            ],
            'match_exact' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
                'comment'    => '1 = so khớp chính xác URL để đánh dấu active, 0 = prefix',
            ],
            'sort_order' => [
                'type'     => 'SMALLINT',
                'unsigned' => true,
                'default'  => 0,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('module_slug');
        $this->forge->createTable('module_sidebar_items');

        $this->db->table('module_sidebar_items')->insertBatch([
            // Classroom — teacher / admin
            [
                'module_slug'  => 'classroom',
                'group_label'  => 'Lớp học',
                'label'        => 'Danh sách lớp học',
                'url'          => '/admin/classrooms',
                'icon'         => '🏫',
                'allowed_roles' => '["workspace_admin","super_admin"]',
                'match_exact'  => 1,
                'sort_order'   => 10,
            ],
            [
                'module_slug'  => 'classroom',
                'group_label'  => 'Lớp học',
                'label'        => 'Danh sách học sinh',
                'url'          => '/admin/classrooms/students',
                'icon'         => '👥',
                'allowed_roles' => '["workspace_admin","super_admin"]',
                'match_exact'  => 0,
                'sort_order'   => 20,
            ],
            // Classroom — student
            [
                'module_slug'  => 'classroom',
                'group_label'  => 'Học tập',
                'label'        => 'Lớp học của tôi',
                'url'          => '/admin/my-classrooms',
                'icon'         => '📚',
                'allowed_roles' => '["user"]',
                'match_exact'  => 0,
                'sort_order'   => 30,
            ],
            // VortexEngine
            [
                'module_slug'  => 'vortex-engine',
                'group_label'  => 'Tính năng',
                'label'        => 'Quản lý gói học',
                'url'          => '/admin/subscriptions',
                'icon'         => '💎',
                'allowed_roles' => '["workspace_admin","super_admin"]',
                'match_exact'  => 0,
                'sort_order'   => 40,
            ],
            // System Log — super_admin đã có trong section "Hệ thống" (hardcoded),
            // nên ở đây chỉ hiện cho workspace_admin.
            [
                'module_slug'  => 'system-log',
                'group_label'  => 'Tính năng',
                'label'        => 'System Log',
                'url'          => '/admin/system-logs',
                'icon'         => '📋',
                'allowed_roles' => '["workspace_admin"]',
                'match_exact'  => 0,
                'sort_order'   => 50,
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropTable('module_sidebar_items', true);
    }
}
