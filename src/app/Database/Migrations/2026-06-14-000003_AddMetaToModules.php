<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddMetaToModules extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('modules', [
            'admin_url' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
                'after'      => 'sort_order',
                'comment'    => 'Đường dẫn trang quản trị module trong admin panel',
            ],
            'icon' => [
                'type'       => 'VARCHAR',
                'constraint' => 10,
                'null'       => true,
                'default'    => '🧩',
                'after'      => 'admin_url',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('modules', ['admin_url', 'icon']);
    }
}
