<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSystemLogsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'level' => [
                'type'       => 'ENUM',
                'constraint' => ['debug', 'info', 'warning', 'error', 'critical'],
                'default'    => 'error',
            ],
            'channel' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'default'    => 'app',
            ],
            'title' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],
            'message' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'context' => [
                'type' => 'JSON',
                'null' => true,
            ],
            'user_id' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
            'ip_address' => [
                'type'       => 'VARCHAR',
                'constraint' => 45,
                'null'       => true,
            ],
            'url' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true,
            ],
            'method' => [
                'type'       => 'VARCHAR',
                'constraint' => 10,
                'null'       => true,
            ],
            'seen' => [
                'type'    => 'TINYINT',
                'default' => 0,
            ],
            'created_at' => [
                'type'    => 'DATETIME',
                'default' => null,
                'null'    => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('level');
        $this->forge->addKey('channel');
        $this->forge->addKey('seen');
        $this->forge->addKey('created_at');
        $this->forge->createTable('system_logs');
    }

    public function down(): void
    {
        $this->forge->dropTable('system_logs', true);
    }
}
