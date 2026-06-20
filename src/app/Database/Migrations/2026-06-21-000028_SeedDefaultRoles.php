<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Seed các role mặc định vào bảng `roles`.
 *
 * Sau migration 2026-06-21-000027_DropRoleFromUsers, cột `users.role` bị xoá.
 * Role của user phải được quản lý qua bảng `roles` + `user_applied_roles`.
 *
 * Các role mặc định:
 *   - super-admin   : Tài khoản super_admin (chỉ 1 user duy nhất qua is_super_admin flag)
 *   - workspace-admin : Giáo viên
 *   - user          : Học sinh (auto-assign cho user mới đăng ký)
 *
 * Migration này INSERT các role chưa có (idempotent — chạy nhiều lần OK).
 */
class SeedDefaultRoles extends Migration
{
    public function up(): void
    {
        $db = \Config\Database::connect();

        $defaultRoles = [
            [
                'name'        => 'Super Admin',
                'slug'        => 'super-admin',
                'description' => 'Tài khoản quản trị cao nhất — chỉ 1 user duy nhất.',
                'is_active'   => 1,
            ],
            [
                'name'        => 'Giáo viên',
                'slug'        => 'workspace-admin',
                'description' => 'Giáo viên / Quản lý lớp học. Có thể tạo lớp, giao bài tập, chấm điểm.',
                'is_active'   => 1,
            ],
            [
                'name'        => 'Học sinh',
                'slug'        => 'user',
                'description' => 'Học sinh. Được join lớp, làm bài tập, xem lớp của mình.',
                'is_active'   => 1,
            ],
        ];

        foreach ($defaultRoles as $role) {
            // INSERT IGNORE nếu slug đã tồn tại
            $exists = $db->table('roles')->where('slug', $role['slug'])->countAllResults();
            if ($exists > 0) {
                continue;
            }

            // Sinh UUID v4 (vì RoleModel không có hook generateUuid)
            $uuid = sprintf(
                '%08x-%04x-%04x-%04x-%012x',
                random_int(0, 0xffffffff),
                random_int(0, 0xffff),
                random_int(0x4000, 0x4fff),
                random_int(0x8000, 0xbfff),
                random_int(0, 0xffffffffffff)
            );

            $db->table('roles')->insert(array_merge($role, ['uuid' => $uuid]));
        }
    }

    public function down(): void
    {
        $db = \Config\Database::connect();
        $db->table('roles')->whereIn('slug', ['super-admin', 'workspace-admin', 'user'])->delete();
    }
}