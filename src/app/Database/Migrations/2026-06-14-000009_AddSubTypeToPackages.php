<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddSubTypeToPackages extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('packages', [
            'sub_type' => [
                'type'       => 'ENUM',
                'constraint' => ['VIP', 'TRIAL'],
                'default'    => 'VIP',
                'null'       => false,
                'after'      => 'is_active',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('packages', 'sub_type');
    }
}
