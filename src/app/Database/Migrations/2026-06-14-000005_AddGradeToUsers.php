<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddGradeToUsers extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('users', [
            'grade' => [
                'type'       => 'TINYINT',
                'unsigned'   => true,
                'null'       => true,
                'default'    => null,
                'comment'    => 'Lớp học (1–9), null nếu không phải học sinh',
                'after'      => 'role',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('users', 'grade');
    }
}
