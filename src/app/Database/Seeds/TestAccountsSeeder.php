<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * TestAccountsSeeder - Tài khoản test cho 3 role.
 *
 * Chạy: php spark db:seed TestAccountsSeeder
 */
class TestAccountsSeeder extends Seeder
{
    public function run(): void
    {
        helper('app');

        $now = now_datetime();

        $accounts = [
            [
                'email'     => 'admin@bladeengine.local',
                'username'  => 'administrator',
                'phone'     => '0900000000',
                'password'  => '123456',
                'full_name' => 'Super Administrator',
                'role'      => ROLE_SUPER_ADMIN,
            ],
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
            // Bỏ qua nếu email đã tồn tại
            $exists = $this->db->table('users')->where('email', $account['email'])->countAllResults();
            if ($exists > 0) {
                continue;
            }

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

        echo "Test accounts seeded.\n";
    }
}
