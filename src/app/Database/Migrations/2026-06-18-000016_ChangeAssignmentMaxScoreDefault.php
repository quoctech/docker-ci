<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class ChangeAssignmentMaxScoreDefault extends Migration
{
    public function up(): void
    {
        $this->db->query('ALTER TABLE assignments MODIFY COLUMN max_score SMALLINT NOT NULL DEFAULT 10');
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE assignments MODIFY COLUMN max_score SMALLINT NOT NULL DEFAULT 100');
    }
}
