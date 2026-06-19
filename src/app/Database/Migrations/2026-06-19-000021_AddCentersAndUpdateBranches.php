<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddCentersAndUpdateBranches extends Migration
{
    public function up(): void
    {
        // Bảng trung tâm
        $this->forge->addField([
            'id'         => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'uuid'       => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => false],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false],
            'address'    => ['type' => 'TEXT', 'null' => true],
            'phone'      => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'email'      => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'is_active'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('uuid');
        $this->forge->createTable('centers');

        // Thêm center_id và manager vào bảng branches
        $this->forge->addColumn('branches', [
            'center_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true, 'after' => 'uuid'],
            'manager'   => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'after' => 'email'],
        ]);

        $db = \Config\Database::connect();

        // Thêm sidebar item cho trung tâm (vào cùng nhóm "Tổ chức")
        $db->table('module_sidebar_items')->insertBatch([
            [
                'module_slug'   => 'school-management',
                'group_label'   => 'Tổ chức',
                'label'         => 'Quản lý trung tâm',
                'url'           => '/admin/school-management/centers',
                'icon'          => '🏛',
                'allowed_roles' => '["workspace_admin","super_admin"]',
                'match_exact'   => 0,
                'sort_order'    => 55,
            ],
        ]);

        // AwesomeBar item cho trung tâm
        (new \Modules\AwesomeBar\Repositories\AwesomeBarItemRepository())->register([
            'type'        => 'page',
            'title'       => 'Quản lý trung tâm',
            'subtitle'    => 'Xem và quản lý danh sách trung tâm',
            'url'         => '/admin/school-management/centers',
            'icon'        => '🏛',
            'keywords'    => 'trung tam co so truong quan ly to chuc',
            'module_slug' => 'school-management',
            'sort_order'  => 55,
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('branches', ['center_id', 'manager']);
        $this->forge->dropTable('centers', true);

        $db = \Config\Database::connect();
        $db->table('module_sidebar_items')
            ->where('module_slug', 'school-management')
            ->where('url', '/admin/school-management/centers')
            ->delete();

        $db->table('awesome_bar_items')
            ->where('url', '/admin/school-management/centers')
            ->delete();
    }
}
