<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddFilePathAndModulePermissions extends Migration
{
    public function up(): void
    {
        // Add file_path column to assignments
        $this->forge->addColumn('assignments', [
            'file_path' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true,
                'default'    => null,
                'after'      => 'is_published',
            ],
        ]);

        // Create user_module_permissions table
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 10,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'user_uuid' => [
                'type'       => 'VARCHAR',
                'constraint' => 36,
                'null'       => false,
            ],
            'module_slug' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
                'null'       => false,
            ],
            'granted_by' => [
                'type'       => 'VARCHAR',
                'constraint' => 36,
                'null'       => true,
                'default'    => null,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['user_uuid', 'module_slug']);
        $this->forge->addKey('user_uuid');
        $this->forge->addKey('module_slug');
        $this->forge->createTable('user_module_permissions', true);
    }

    public function down(): void
    {
        $this->forge->dropColumn('assignments', 'file_path');
        $this->forge->dropTable('user_module_permissions', true);
    }
}
