<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * VortexEngineSeeder - Đăng ký module VortexEngine và seed gói học mặc định.
 *
 * Chạy SAU khi đã migrate:
 *   php spark migrate
 *   php spark db:seed VortexEngineSeeder
 */
class VortexEngineSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        // Đăng ký module vào bảng modules (bỏ qua nếu đã tồn tại)
        $exists = $this->db->table('modules')->where('slug', 'vortex-engine')->countAllResults();

        if (! $exists) {
            $this->db->table('modules')->insert([
                'slug'        => 'vortex-engine',
                'name'        => 'Quản lý gói học',
                'description' => 'Quản lý gói đăng ký học viên: dùng thử, VIP, hết hạn.',
                'is_enabled'  => 1,
                'is_core'     => 0,
                'version'     => '1.0.0',
                'sort_order'  => 10,
                'admin_url'   => '/admin/subscriptions',
                'icon'        => '💎',
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        } else {
            // Cập nhật admin_url và icon nếu chạy lại sau khi có migration AddMetaToModules
            $this->db->table('modules')
                ->where('slug', 'vortex-engine')
                ->where('admin_url IS NULL', null, false)
                ->update(['admin_url' => '/admin/subscriptions', 'icon' => '💎', 'updated_at' => $now]);
        }

        // Seed gói học mặc định (bỏ qua nếu đã có)
        $packageCount = $this->db->table('packages')->countAllResults();

        if ($packageCount === 0) {
            $this->call(PackagesSeeder::class);
        }
    }
}
