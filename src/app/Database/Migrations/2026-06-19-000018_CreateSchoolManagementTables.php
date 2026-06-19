<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSchoolManagementTables extends Migration
{
    public function up(): void
    {
        // 2a. Bảng chi nhánh
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'uuid'      => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => false],
            'name'      => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false],
            'address'   => ['type' => 'TEXT', 'null' => true],
            'phone'     => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'email'     => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('uuid');
        $this->forge->createTable('branches');

        // Bảng phòng học
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'uuid'      => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => false],
            'branch_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false],
            'name'      => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false],
            'capacity'  => ['type' => 'INT', 'null' => true],
            'room_type' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('uuid');
        $this->forge->addKey('branch_id');
        $this->forge->createTable('rooms');

        $db = \Config\Database::connect();

        // 2b. Đăng ký module
        $db->table('modules')->insert([
            'slug'        => 'school-management',
            'name'        => 'Quản lý trường học',
            'description' => 'Quản lý chi nhánh và phòng học.',
            'is_enabled'  => 1,
            'is_core'     => 0,
            'version'     => '1.0.0',
            'sort_order'  => 60,
            'admin_url'   => '/admin/school-management/branches',
            'icon'        => '🏢',
        ]);

        // 2c. Sidebar items
        $db->table('module_sidebar_items')->insertBatch([
            [
                'module_slug'   => 'school-management',
                'group_label'   => 'Tổ chức',
                'label'         => 'Quản lý chi nhánh',
                'url'           => '/admin/school-management/branches',
                'icon'          => '🏢',
                'allowed_roles' => '["workspace_admin","super_admin"]',
                'match_exact'   => 1,
                'sort_order'    => 60,
            ],
            [
                'module_slug'   => 'school-management',
                'group_label'   => 'Tổ chức',
                'label'         => 'Quản lý phòng học',
                'url'           => '/admin/school-management/rooms',
                'icon'          => '🚪',
                'allowed_roles' => '["workspace_admin","super_admin"]',
                'match_exact'   => 0,
                'sort_order'    => 70,
            ],
        ]);

        // 2d. AwesomeBar items
        $awesomeBar = new \Modules\AwesomeBar\Repositories\AwesomeBarItemRepository();
        $awesomeBar->register([
            'type'        => 'page',
            'title'       => 'Quản lý chi nhánh',
            'subtitle'    => 'Xem và quản lý danh sách chi nhánh của trường',
            'url'         => '/admin/school-management/branches',
            'icon'        => '🏢',
            'keywords'    => 'chi nhanh truong co so quan ly',
            'module_slug' => 'school-management',
            'sort_order'  => 60,
        ]);
        $awesomeBar->register([
            'type'        => 'page',
            'title'       => 'Quản lý phòng học',
            'subtitle'    => 'Xem và quản lý phòng học trong các chi nhánh',
            'url'         => '/admin/school-management/rooms',
            'icon'        => '🚪',
            'keywords'    => 'phong hoc phong thuc hanh co so quan ly',
            'module_slug' => 'school-management',
            'sort_order'  => 70,
        ]);
    }

    public function down(): void
    {
        $this->forge->dropTable('rooms', true);
        $this->forge->dropTable('branches', true);

        $db = \Config\Database::connect();
        $db->table('modules')->where('slug', 'school-management')->delete();
        $db->table('module_sidebar_items')->where('module_slug', 'school-management')->delete();

        (new \Modules\AwesomeBar\Repositories\AwesomeBarItemRepository())->removeByModuleSlug('school-management');
    }
}
