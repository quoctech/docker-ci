<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tạo bảng academic_years (Quản lý năm học/học kỳ) + bổ sung sidebar/AwesomeBar.
 *
 * Quy tắc nghiệp vụ:
 *  - Mỗi academic_year thuộc về 1 branch (FK, ON DELETE CASCADE).
 *  - end_date > start_date (enforced bằng CHECK constraint + validation phía server).
 *  - Soft-delete qua is_active = 0.
 *  - 2 năm học KHÔNG được trùng ngày trong cùng branch:
 *      Hai năm học A(start_a, end_a) và B(start_b, end_b) được coi là trùng nếu
 *      A.start_date < B.end_date AND B.start_date < A.end_date (overlap).
 *    → Được enforce ở application layer (vì MySQL CHECK không cho phép subquery).
 *
 * UI / Sidebar:
 *  - Sidebar item "Quản lý năm học" chỉ hiện khi user có can_write trở lên
 *    (column `min_perm` = 'write' → sidebar kiểm tra hasModulePerm).
 */
class CreateAcademicYearsTable extends Migration
{
    public function up(): void
    {
        // ----------------------------------------------------------
        // 1. Bổ sung cột `min_perm` cho module_sidebar_items
        // ----------------------------------------------------------
        // NULL = dùng hasModule (read). 'write'|'edit'|'delete' = hasModulePerm.
        $db = \Config\Database::connect();
        $colExists = $db->query("SHOW COLUMNS FROM module_sidebar_items LIKE 'min_perm'")->getNumRows() > 0;
        if (! $colExists) {
            $this->forge->addColumn('module_sidebar_items', [
                'min_perm' => [
                    'type'       => 'ENUM',
                    'constraint' => ['read', 'write', 'edit', 'delete'],
                    'default'    => 'read',
                    'null'       => true,
                    'after'      => 'allowed_roles',
                ],
            ]);
            // Backfill: set NULL cho các record hiện tại (= dùng hasModule)
            $db->table('module_sidebar_items')->update(null, ['min_perm' => null]);
        }

        // ----------------------------------------------------------
        // 2. Tạo bảng academic_years
        // ----------------------------------------------------------
        $this->forge->addField([
            'id'         => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'uuid'       => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => false],
            'branch_id'  => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],

            'name'        => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false],
            'start_date'  => ['type' => 'DATE', 'null' => false],
            'end_date'    => ['type' => 'DATE', 'null' => false],

            'is_active'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('uuid');
        $this->forge->addKey('branch_id');
        $this->forge->addKey('start_date');
        $this->forge->addKey('end_date');
        $this->forge->addForeignKey('branch_id', 'branches', 'id', '', 'CASCADE');
        $this->forge->createTable('academic_years');

        // ----------------------------------------------------------
        // 3. Sidebar item "Quản lý năm học" — chỉ hiện khi có can_write trở lên
        // ----------------------------------------------------------
        $db->table('module_sidebar_items')->insert([
            'module_slug'  => 'school-management',
            'group_label'  => 'Tổ chức',
            'label'        => 'Quản lý năm học',
            'url'          => '/admin/school-management/academic-years',
            'icon'         => '📅',
            'allowed_roles' => '["workspace_admin","super_admin"]',
            'min_perm'     => 'write', // chỉ hiện khi user có can_write/can_edit/can_delete
            'match_exact'  => 0,
            'sort_order'   => 25,
        ]);

        // ----------------------------------------------------------
        // 4. AwesomeBar item (Ctrl+K)
        // ----------------------------------------------------------
        $bar = new \Modules\AwesomeBar\Repositories\AwesomeBarItemRepository();
        $bar->register([
            'type'        => 'page',
            'title'       => 'Quản lý năm học',
            'subtitle'    => 'Tạo và quản lý năm học, học kỳ',
            'url'         => '/admin/school-management/academic-years',
            'icon'        => '📅',
            'keywords'    => 'nam hoc hoc ky academic year school management',
            'module_slug' => 'school-management',
            'sort_order'  => 25,
        ]);
    }

    public function down(): void
    {
        $db = \Config\Database::connect();

        // AwesomeBar
        $bar = new \Modules\AwesomeBar\Repositories\AwesomeBarItemRepository();
        $bar->removeByUrl('/admin/school-management/academic-years');

        // Sidebar item
        $db->table('module_sidebar_items')
            ->where('url', '/admin/school-management/academic-years')
            ->delete();

        // Bảng
        $this->forge->dropTable('academic_years', true);

        // Xóa cột min_perm
        $this->forge->dropColumn('module_sidebar_items', 'min_perm');
    }
}
