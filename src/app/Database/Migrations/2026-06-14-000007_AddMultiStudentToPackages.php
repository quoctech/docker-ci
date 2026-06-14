<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Bổ sung hỗ trợ gói kép (multi-student) và giới hạn lớp cho packages.
 *
 *   max_students   — số học sinh tối đa được gán vào 1 subscription (default 1)
 *   allowed_grades — danh sách lớp được phép (JSON array, null = không giới hạn)
 */
class AddMultiStudentToPackages extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('packages', [
            'max_students' => [
                'type'       => 'TINYINT',
                'unsigned'   => true,
                'default'    => 1,
                'null'       => false,
                'comment'    => 'Số học sinh tối đa/subscription (1 = đơn, >1 = gói kép)',
                'after'      => 'is_active',
            ],
            'allowed_grades' => [
                'type'    => 'VARCHAR',
                'constraint' => 50,
                'null'    => true,
                'default' => null,
                'comment' => 'Lớp được phép học, JSON array VD: [1,2,5] — null = tất cả',
                'after'   => 'max_students',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('packages', ['max_students', 'allowed_grades']);
    }
}
