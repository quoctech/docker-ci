<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use Modules\AwesomeBar\Repositories\AwesomeBarItemRepository;

class CreateClassroomTables extends Migration
{
    public function up(): void
    {
        // ----------------------------------------------------------------
        // classrooms
        // ----------------------------------------------------------------
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'uuid'          => ['type' => 'VARCHAR', 'constraint' => 36],
            'teacher_uuid'  => ['type' => 'VARCHAR', 'constraint' => 36],
            'name'          => ['type' => 'VARCHAR', 'constraint' => 255],
            'description'   => ['type' => 'TEXT', 'null' => true],
            'code'          => ['type' => 'VARCHAR', 'constraint' => 12],
            'subject'       => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'grade'         => ['type' => 'TINYINT', 'null' => true],
            'auto_approve'  => ['type' => 'TINYINT', 'default' => 1],
            'is_active'     => ['type' => 'TINYINT', 'default' => 1],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('uuid');
        $this->forge->addUniqueKey('code');
        $this->forge->addKey('teacher_uuid');
        $this->forge->createTable('classrooms');

        // ----------------------------------------------------------------
        // classroom_members
        // ----------------------------------------------------------------
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'classroom_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'student_uuid'  => ['type' => 'VARCHAR', 'constraint' => 36],
            'status'        => ['type' => 'ENUM', 'constraint' => ['pending', 'approved', 'rejected'], 'default' => 'pending'],
            'joined_at'     => ['type' => 'DATETIME', 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['classroom_id', 'student_uuid']);
        $this->forge->addKey('classroom_id');
        $this->forge->addKey('student_uuid');
        $this->forge->addKey('status');
        $this->forge->createTable('classroom_members');

        // ----------------------------------------------------------------
        // assignments
        // ----------------------------------------------------------------
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'uuid'          => ['type' => 'VARCHAR', 'constraint' => 36],
            'classroom_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'teacher_uuid'  => ['type' => 'VARCHAR', 'constraint' => 36],
            'title'         => ['type' => 'VARCHAR', 'constraint' => 255],
            'description'   => ['type' => 'TEXT', 'null' => true],
            'due_date'      => ['type' => 'DATETIME', 'null' => true],
            'max_score'     => ['type' => 'SMALLINT', 'default' => 10],
            'is_published'  => ['type' => 'TINYINT', 'default' => 1],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('uuid');
        $this->forge->addKey('classroom_id');
        $this->forge->addKey('teacher_uuid');
        $this->forge->addKey('is_published');
        $this->forge->createTable('assignments');

        // ----------------------------------------------------------------
        // assignment_submissions
        // ----------------------------------------------------------------
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'uuid'          => ['type' => 'VARCHAR', 'constraint' => 36],
            'assignment_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'student_uuid'  => ['type' => 'VARCHAR', 'constraint' => 36],
            'content'       => ['type' => 'TEXT', 'null' => true],
            'file_url'      => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'score'         => ['type' => 'SMALLINT', 'null' => true],
            'feedback'      => ['type' => 'TEXT', 'null' => true],
            'status'        => ['type' => 'ENUM', 'constraint' => ['submitted', 'graded'], 'default' => 'submitted'],
            'submitted_at'  => ['type' => 'DATETIME', 'null' => true],
            'graded_at'     => ['type' => 'DATETIME', 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('uuid');
        $this->forge->addUniqueKey(['assignment_id', 'student_uuid']);
        $this->forge->addKey('assignment_id');
        $this->forge->addKey('student_uuid');
        $this->forge->addKey('status');
        $this->forge->createTable('assignment_submissions');

        // ----------------------------------------------------------------
        // Module registry
        // ----------------------------------------------------------------
        $db = \Config\Database::connect();
        $db->table('modules')->insert([
            'slug'        => 'classroom',
            'name'        => 'Lớp học',
            'description' => 'Quản lý lớp học, bài tập và nộp bài cho giáo viên & học sinh.',
            'is_enabled'  => 1,
            'is_core'     => 0,
            'version'     => '1.0.0',
            'sort_order'  => 30,
            'admin_url'   => '/admin/classrooms',
            'icon'        => '🏫',
        ]);

        // ----------------------------------------------------------------
        // Awesome Bar items
        // ----------------------------------------------------------------
        $bar = new AwesomeBarItemRepository();
        $bar->register([
            'type'        => 'page',
            'title'       => 'Quản lý lớp học',
            'subtitle'    => 'Tạo lớp, quản lý học sinh, bài tập',
            'url'         => '/admin/classrooms',
            'icon'        => '🏫',
            'keywords'    => 'lop hoc giao vien bai tap classroom teacher',
            'module_slug' => 'classroom',
            'sort_order'  => 30,
        ]);
        $bar->register([
            'type'        => 'page',
            'title'       => 'Lớp học của tôi',
            'subtitle'    => 'Xem lớp học, bài tập, nộp bài',
            'url'         => '/admin/my-classrooms',
            'icon'        => '📚',
            'keywords'    => 'lop hoc hoc sinh bai tap nop bai student',
            'module_slug' => 'classroom',
            'sort_order'  => 31,
        ]);
    }

    public function down(): void
    {
        $this->forge->dropTable('assignment_submissions', true);
        $this->forge->dropTable('assignments', true);
        $this->forge->dropTable('classroom_members', true);
        $this->forge->dropTable('classrooms', true);

        $db = \Config\Database::connect();
        $db->table('modules')->where('slug', 'classroom')->delete();

        (new AwesomeBarItemRepository())->removeByModuleSlug('classroom');
    }
}
