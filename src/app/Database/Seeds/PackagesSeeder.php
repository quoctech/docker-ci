<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class PackagesSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        $packages = [
            [
                'package_key' => '1_MONTH',
                'name'        => 'Gói 1 tháng',
                'description' => 'Truy cập toàn bộ nội dung trong 30 ngày',
                'price'       => 500000,
                'days_to_add' => 30,
                'is_active'   => 1,
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'package_key' => '3_MONTHS',
                'name'        => 'Gói 3 tháng',
                'description' => 'Truy cập toàn bộ nội dung trong 90 ngày',
                'price'       => 1200000,
                'days_to_add' => 90,
                'is_active'   => 1,
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'package_key' => '6_MONTHS',
                'name'        => 'Gói 6 tháng',
                'description' => 'Truy cập toàn bộ nội dung trong 180 ngày',
                'price'       => 2000000,
                'days_to_add' => 180,
                'is_active'   => 1,
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'package_key' => '1_YEAR',
                'name'        => 'Gói 1 năm',
                'description' => 'Truy cập toàn bộ nội dung trong 365 ngày',
                'price'       => 3500000,
                'days_to_add' => 365,
                'is_active'   => 1,
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
        ];

        $this->db->table('packages')->insertBatch($packages);
    }
}
