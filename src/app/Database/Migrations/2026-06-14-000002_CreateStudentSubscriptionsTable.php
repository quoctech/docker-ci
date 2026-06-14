<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateStudentSubscriptionsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'student_id' => [
                'type'     => 'INT',
                'unsigned' => true,
                'comment'  => 'FK → students.id (sẽ tạo sau khi có module Students)',
            ],
            'parent_id' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
                'comment'  => 'FK → users.id phụ huynh mua gói cho con (null nếu tự mua)',
            ],
            'package_key' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'comment'    => 'Tham chiếu packages.package_key tại thời điểm kích hoạt',
            ],
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'TRIAL',
                'comment'    => 'TRIAL | VIP | EXPIRED',
            ],
            'start_date' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'expired_date' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'comment' => 'NULL = chưa kích hoạt (đang TRIAL)',
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
        $this->forge->addKey('student_id');
        $this->forge->addKey('expired_date');
        $this->forge->createTable('student_subscriptions', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('student_subscriptions', true);
    }
}
