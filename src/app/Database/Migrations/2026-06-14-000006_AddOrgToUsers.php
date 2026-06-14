<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddOrgToUsers extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('users', [
            'organization' => [
                'type'       => 'VARCHAR',
                'constraint' => 200,
                'null'       => true,
                'default'    => null,
                'comment'    => 'Tổ chức / Trường (dành cho giáo viên)',
                'after'      => 'grade',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('users', 'organization');
    }
}
