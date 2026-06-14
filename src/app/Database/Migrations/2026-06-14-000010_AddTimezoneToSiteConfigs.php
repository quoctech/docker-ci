<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddTimezoneToSiteConfigs extends Migration
{
    public function up(): void
    {
        // Extend type enum to include 'timezone'
        $this->db->query("ALTER TABLE site_configs MODIFY COLUMN `type` ENUM('string','integer','boolean','json','timezone') NOT NULL DEFAULT 'string'");

        // Insert default timezone config
        $this->db->query("
            INSERT INTO site_configs (`key`, `value`, `description`, `type`, `group`)
            VALUES ('app_timezone', 'Asia/Ho_Chi_Minh', 'Múi giờ hệ thống', 'timezone', 'general')
            ON DUPLICATE KEY UPDATE `type` = 'timezone', `description` = 'Múi giờ hệ thống'
        ");
    }

    public function down(): void
    {
        $this->db->query("DELETE FROM site_configs WHERE `key` = 'app_timezone'");
        $this->db->query("ALTER TABLE site_configs MODIFY COLUMN `type` ENUM('string','integer','boolean','json') NOT NULL DEFAULT 'string'");
    }
}
