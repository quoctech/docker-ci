<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * InitialSeeder - Dữ liệu khởi tạo cho hệ thống.
 *
 * Chạy 1 lần duy nhất khi setup project mới.
 * Lệnh: php spark db:seed InitialSeeder
 */
class InitialSeeder extends Seeder
{
    public function run(): void
    {
        // Load helper để dùng now_datetime(), hash_password()
        helper('app');

        $this->seedModules();
        $this->seedSiteConfigs();
        $this->seedSuperAdmin();
        $this->seedTestAccounts();
    }

    private function seedModules(): void
    {
        $now = now_datetime();

        $modules = [
            [
                'slug'        => 'auth',
                'name'        => 'Xác thực & Bảo mật',
                'description' => 'JWT authentication, quản lý phiên, rate limiting.',
                'is_enabled'  => 1,
                'is_core'     => 1,
                'version'     => '1.0.0',
                'sort_order'  => 1,
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'slug'        => 'system-admin',
                'name'        => 'Quản trị Hệ thống',
                'description' => 'Quản lý module, cấu hình website, quản lý người dùng.',
                'is_enabled'  => 1,
                'is_core'     => 1,
                'version'     => '1.0.0',
                'sort_order'  => 2,
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
        ];

        $this->db->table('modules')->insertBatch($modules);
    }

    private function seedSiteConfigs(): void
    {
        $now = now_datetime();

        $configs = [
            // Cài đặt chung
            ['key' => 'site_name',        'value' => 'BladeEngine',                             'group' => 'general', 'type' => 'string',  'description' => 'Tên website hiển thị'],
            ['key' => 'site_description', 'value' => 'Nền tảng module hóa BladeEngine',        'group' => 'general', 'type' => 'string',  'description' => 'Mô tả ngắn về website'],
            ['key' => 'site_logo',        'value' => '',                                        'group' => 'general', 'type' => 'string',  'description' => 'URL logo website'],
            ['key' => 'site_favicon',     'value' => '',                                        'group' => 'general', 'type' => 'string',  'description' => 'URL favicon'],
            ['key' => 'meta_title',       'value' => 'BladeEngine - Core Module Platform',      'group' => 'general', 'type' => 'string',  'description' => 'Meta title cho SEO'],
            ['key' => 'meta_description', 'value' => 'Nền tảng phát triển module BladeEngine',  'group' => 'general', 'type' => 'string',  'description' => 'Meta description cho SEO'],
            ['key' => 'maintenance_mode', 'value' => '0',                                      'group' => 'general', 'type' => 'boolean', 'description' => 'Bật chế độ bảo trì (tạm đóng website)'],
            ['key' => 'register_enabled', 'value' => '1',                                      'group' => 'general', 'type' => 'boolean', 'description' => 'Cho phép đăng ký tài khoản mới'],

            // Liên hệ
            ['key' => 'hotline',       'value' => '',                  'group' => 'contact', 'type' => 'string', 'description' => 'Số hotline hỗ trợ'],
            ['key' => 'support_email', 'value' => 'support@bladeengine.local', 'group' => 'contact', 'type' => 'string', 'description' => 'Email hỗ trợ khách hàng'],
            ['key' => 'phone',         'value' => '',                  'group' => 'contact', 'type' => 'string', 'description' => 'Số điện thoại liên hệ'],
            ['key' => 'address',       'value' => '',                  'group' => 'contact', 'type' => 'string', 'description' => 'Địa chỉ văn phòng'],
            ['key' => 'facebook',      'value' => '',                  'group' => 'contact', 'type' => 'string', 'description' => 'Link Facebook fanpage'],
            ['key' => 'zalo',          'value' => '',                  'group' => 'contact', 'type' => 'string', 'description' => 'Link hoặc số Zalo'],
        ];

        foreach ($configs as &$config) {
            $config['updated_at'] = $now;
        }

        $this->db->table('site_configs')->insertBatch($configs);
    }

    private function seedSuperAdmin(): void
    {
        $now = now_datetime();

        // UUID v4
        $data    = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        $uuid    = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));

        $this->db->table('users')->insert([
            'uuid'          => $uuid,
            'email'         => 'admin@bladeengine.local',
            'username'      => 'administrator',
            'phone'         => '0900000000',
            'password_hash' => hash_password('123456'),
            'full_name'     => 'Super Administrator',
            'role'          => ROLE_SUPER_ADMIN,
            'status'        => STATUS_ACTIVE,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);
    }

    private function seedTestAccounts(): void
    {
        $now = now_datetime();

        $accounts = [
            [
                'email'     => 'teacher@bladeengine.local',
                'username'  => 'teacher01',
                'phone'     => '0911000001',
                'password'  => 'Test@123',
                'full_name' => 'Nguyễn Thị Giáo Viên',
                'role'      => ROLE_WORKSPACE_ADMIN,
            ],
            [
                'email'     => 'student@bladeengine.local',
                'username'  => 'student01',
                'phone'     => '0922000002',
                'password'  => 'Test@123',
                'full_name' => 'Trần Văn Học Sinh',
                'role'      => ROLE_USER,
            ],
        ];

        foreach ($accounts as $account) {
            $data    = random_bytes(16);
            $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
            $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
            $uuid    = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));

            $this->db->table('users')->insert([
                'uuid'          => $uuid,
                'email'         => $account['email'],
                'username'      => $account['username'],
                'phone'         => $account['phone'],
                'password_hash' => hash_password($account['password']),
                'full_name'     => $account['full_name'],
                'role'          => $account['role'],
                'status'        => STATUS_ACTIVE,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
        }
    }
}
