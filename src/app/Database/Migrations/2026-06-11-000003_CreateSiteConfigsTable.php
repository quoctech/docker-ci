<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSiteConfigsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'key' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'unique'     => true,
            ],
            'value' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'group' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'default'    => 'general',
            ],
            'type' => [
                'type'       => 'ENUM',
                'constraint' => ['string', 'integer', 'boolean', 'json'],
                'default'    => 'string',
            ],
            'description' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('group');
        $this->forge->createTable('site_configs', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('site_configs', true);
    }
}
