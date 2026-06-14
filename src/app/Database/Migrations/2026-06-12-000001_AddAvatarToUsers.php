<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migration: Thêm trường avatar vào bảng users.
 */
class AddAvatarToUsers extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('users', [
            'avatar' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'after'      => 'full_name',
                'comment'    => 'Đường dẫn file avatar (relative path trong writable/uploads/avatars/)',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('users', 'avatar');
    }
}
