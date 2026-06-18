<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddSubmissionImages extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('assignment_submissions', [
            'image_paths' => [
                'type'  => 'TEXT',
                'null'  => true,
                'after' => 'file_url',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('assignment_submissions', 'image_paths');
    }
}
