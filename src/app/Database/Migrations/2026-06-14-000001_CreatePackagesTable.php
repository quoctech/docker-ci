<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePackagesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'package_key' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'comment'    => 'Định danh gói: 1_MONTH, 3_MONTHS, 6_MONTHS, 1_YEAR',
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'description' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'price' => [
                'type'     => 'INT',
                'unsigned' => true,
                'default'  => 0,
                'comment'  => 'Giá VND (không dùng DECIMAL để tránh làm tròn)',
            ],
            'days_to_add' => [
                'type'     => 'INT',
                'unsigned' => true,
                'comment'  => 'Số ngày gia hạn khi kích hoạt gói',
            ],
            'is_active' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('package_key');
        $this->forge->createTable('packages', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('packages', true);
    }
}
