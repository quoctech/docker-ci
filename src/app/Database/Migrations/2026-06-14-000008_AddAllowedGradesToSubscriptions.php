<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddAllowedGradesToSubscriptions extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('student_subscriptions', [
            'allowed_grades' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
                'default'    => null,
                'comment'    => 'Snapshot lớp được phép tại thời điểm kích hoạt',
                'after'      => 'package_key',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('student_subscriptions', 'allowed_grades');
    }
}
