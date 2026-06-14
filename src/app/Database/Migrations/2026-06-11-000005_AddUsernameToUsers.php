<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddUsernameToUsers extends Migration
{
    public function up(): void
    {
        // username đã có trong migration gốc CreateUsersTable — no-op
    }

    public function down(): void
    {
        // no-op
    }
}
