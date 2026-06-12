<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddUsernameToUsers extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('users', [
            'username' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'unique'     => true,
                'null'       => true,
                'after'      => 'email',
                'comment'    => 'Tên đăng nhập duy nhất, dùng thay email khi login',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('users', 'username');
    }
}
