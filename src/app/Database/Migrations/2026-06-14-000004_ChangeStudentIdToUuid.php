<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * student_id và parent_id được thiết kế ban đầu cho bảng students (chưa có).
 * Hiện tại học viên lưu trong bảng users với UUID làm PK.
 * Migration này đổi kiểu INT → CHAR(36) để lưu users.uuid.
 */
class ChangeStudentIdToUuid extends Migration
{
    public function up(): void
    {
        $this->forge->modifyColumn('student_subscriptions', [
            'student_id' => [
                'name'       => 'student_id',
                'type'       => 'CHAR',
                'constraint' => 36,
                'null'       => false,
            ],
            'parent_id' => [
                'name'       => 'parent_id',
                'type'       => 'CHAR',
                'constraint' => 36,
                'null'       => true,
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->modifyColumn('student_subscriptions', [
            'student_id' => [
                'name'     => 'student_id',
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => false,
            ],
            'parent_id' => [
                'name'     => 'parent_id',
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
        ]);
    }
}
