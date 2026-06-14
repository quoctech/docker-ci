<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAwesomeBarItemsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'SMALLINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'type' => [
                'type'       => 'ENUM',
                'constraint' => ['page', 'action', 'module', 'external'],
                'default'    => 'page',
            ],
            'title' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],
            'subtitle' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => true,
            ],
            'url' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true,
            ],
            'icon' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
                'null'       => true,
            ],
            'keywords' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true,
            ],
            'module_slug' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
                'null'       => true,
                'comment'    => 'NULL = luôn hiển thị, otherwise kiểm tra module enabled',
            ],
            'is_active' => [
                'type'    => 'TINYINT',
                'default' => 1,
            ],
            'sort_order' => [
                'type'    => 'SMALLINT',
                'default' => 0,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('is_active');
        $this->forge->createTable('awesome_bar_items');

        // Seed core navigation items
        $this->db->table('awesome_bar_items')->insertBatch([
            ['type' => 'page', 'title' => 'Tổng quan',            'subtitle' => 'Dashboard',                    'url' => '/admin',              'icon' => '📊', 'keywords' => 'dashboard tong quan', 'module_slug' => null,           'sort_order' => 1],
            ['type' => 'page', 'title' => 'Quản lý người dùng',   'subtitle' => 'Xem và quản lý tài khoản',     'url' => '/admin/users',        'icon' => '👥', 'keywords' => 'users nguoi dung tai khoan hoc sinh giao vien', 'module_slug' => null, 'sort_order' => 2],
            ['type' => 'page', 'title' => 'Quản lý module',       'subtitle' => 'Bật/tắt các module',           'url' => '/admin/modules',      'icon' => '🧩', 'keywords' => 'modules module bat tat plugin', 'module_slug' => null, 'sort_order' => 3],
            ['type' => 'page', 'title' => 'Cài đặt Website',      'subtitle' => 'Cấu hình chung hệ thống',      'url' => '/admin/configs',      'icon' => '⚙️', 'keywords' => 'settings cai dat cau hinh config', 'module_slug' => null, 'sort_order' => 4],
            ['type' => 'page', 'title' => 'Gói học',              'subtitle' => 'VortexEngine — đăng ký học',   'url' => '/admin/subscriptions','icon' => '💎', 'keywords' => 'subscription goi hoc vortex premium dung thu', 'module_slug' => 'vortex-engine', 'sort_order' => 5],
            ['type' => 'page', 'title' => 'System Log',           'subtitle' => 'Nhật ký lỗi hệ thống',         'url' => '/admin/system-logs',  'icon' => '📋', 'keywords' => 'log loi error warning system nhat ky', 'module_slug' => 'system-log', 'sort_order' => 6],
            ['type' => 'page', 'title' => 'Hồ sơ cá nhân',       'subtitle' => 'Thông tin tài khoản',          'url' => '/admin/profile',      'icon' => '👤', 'keywords' => 'profile ho so ca nhan account', 'module_slug' => null, 'sort_order' => 7],
            ['type' => 'action', 'title' => 'Đăng xuất',          'subtitle' => 'Kết thúc phiên làm việc',      'url' => null,                  'icon' => '🚪', 'keywords' => 'logout dang xuat thoat', 'module_slug' => null, 'sort_order' => 99],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropTable('awesome_bar_items', true);
    }
}
