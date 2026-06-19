<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateRoleTables extends Migration
{
    public function up(): void
    {
        // Bảng vai trò
        $this->forge->addField([
            'id'          => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'uuid'        => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => false],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false],
            'slug'        => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false],
            'description' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'is_active'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('uuid');
        $this->forge->addUniqueKey('slug');
        $this->forge->createTable('roles');

        // Bảng phân quyền module cho vai trò
        $this->forge->addField([
            'id'          => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'role_id'     => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false],
            'module_slug' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false],
            'can_read'    => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'can_write'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'can_edit'    => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'can_delete'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('role_id');
        $this->forge->addUniqueKey(['role_id', 'module_slug'], 'role_module_unique');
        $this->forge->createTable('role_module_permissions');

        $db = \Config\Database::connect();

        // Đăng ký module
        $db->table('modules')->insert([
            'slug'        => 'role-management',
            'name'        => 'Quản lý vai trò',
            'description' => 'Tạo vai trò, phân quyền module và áp dụng cho người dùng.',
            'is_enabled'  => 1,
            'is_core'     => 0,
            'version'     => '1.0.0',
            'sort_order'  => 55,
            'admin_url'   => '/admin/role-management',
            'icon'        => '🎭',
        ]);

        // Sidebar item
        $db->table('module_sidebar_items')->insert([
            'module_slug'   => 'role-management',
            'group_label'   => 'Phân quyền',
            'label'         => 'Quản lý vai trò',
            'url'           => '/admin/role-management',
            'icon'          => '🎭',
            'allowed_roles' => '["workspace_admin","super_admin"]',
            'match_exact'   => 0,
            'sort_order'    => 55,
        ]);

        // AwesomeBar
        (new \Modules\AwesomeBar\Repositories\AwesomeBarItemRepository())->register([
            'type'        => 'page',
            'title'       => 'Quản lý vai trò',
            'subtitle'    => 'Tạo vai trò, phân quyền module và áp dụng cho người dùng',
            'url'         => '/admin/role-management',
            'icon'        => '🎭',
            'keywords'    => 'vai tro role quyen phan quyen nhan vien to chuc',
            'module_slug' => 'role-management',
            'sort_order'  => 55,
        ]);
    }

    public function down(): void
    {
        $this->forge->dropTable('role_module_permissions', true);
        $this->forge->dropTable('roles', true);

        $db = \Config\Database::connect();
        $db->table('modules')->where('slug', 'role-management')->delete();
        $db->table('module_sidebar_items')->where('module_slug', 'role-management')->delete();

        (new \Modules\AwesomeBar\Repositories\AwesomeBarItemRepository())->removeByModuleSlug('role-management');
    }
}
