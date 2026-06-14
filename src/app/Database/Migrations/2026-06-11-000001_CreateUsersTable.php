<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migration: Tạo bảng users.
 *
 * Bảng chính lưu thông tin tài khoản người dùng.
 * Hỗ trợ: soft delete, brute force tracking, RBAC 3 cấp.
 */
class CreateUsersTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            // === Primary Key (UUID) ===
            'uuid' => [
                'type'       => 'CHAR',
                'constraint' => 36,
                'comment'    => 'UUID v4 — primary key, định danh duy nhất public',
            ],
            'email' => [
                'type'       => 'VARCHAR',
                'constraint' => 254,
                'unique'     => true,
                'comment'    => 'Email đăng nhập + liên hệ, unique, lowercase',
            ],
            'username' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'unique'     => true,
                'null'       => true,
                'comment'    => 'Tên đăng nhập thay thế email, alphanumeric, lowercase',
            ],
            'phone' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => true,
                'comment'    => 'Số điện thoại liên hệ (có thể dùng cho OTP sau này)',
            ],

            // === Authentication ===
            'password_hash' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'comment'    => 'Mật khẩu đã hash bằng Argon2id (KHÔNG lưu plaintext)',
            ],

            // === Profile ===
            'full_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'comment'    => 'Họ tên đầy đủ hiển thị trên giao diện',
            ],

            // === Authorization ===
            'role' => [
                'type'       => 'ENUM',
                'constraint' => ['super_admin', 'workspace_admin', 'user'],
                'default'    => 'user',
                'comment'    => 'Phân quyền RBAC: super_admin > workspace_admin > user',
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['active', 'locked', 'pending'],
                'default'    => 'active',
                'comment'    => 'Trạng thái: active=hoạt động, locked=bị khóa, pending=chờ xác minh',
            ],

            // === Security Tracking ===
            'email_verified_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'comment' => 'Thời điểm xác minh email (null = chưa verify)',
            ],
            'last_login_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'comment' => 'Thời điểm đăng nhập gần nhất',
            ],
            'last_login_ip' => [
                'type'       => 'VARCHAR',
                'constraint' => 45,
                'null'       => true,
                'comment'    => 'IP đăng nhập gần nhất (IPv4 hoặc IPv6)',
            ],
            'failed_login_attempts' => [
                'type'     => 'TINYINT',
                'unsigned' => true,
                'default'  => 0,
                'comment'  => 'Số lần đăng nhập sai liên tiếp (reset khi login thành công)',
            ],
            'locked_until' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'comment' => 'Khóa tạm đến thời điểm này (brute force protection, null = không khóa)',
            ],

            // === Timestamps ===
            'created_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'comment' => 'Thời điểm tạo tài khoản',
            ],
            'updated_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'comment' => 'Thời điểm cập nhật gần nhất',
            ],
            'deleted_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'comment' => 'Soft delete: null = chưa xóa, có giá trị = đã xóa',
            ],
        ]);

        $this->forge->addPrimaryKey('uuid');
        $this->forge->addKey('role');
        $this->forge->addKey('status');
        $this->forge->addKey('deleted_at');
        $this->forge->createTable('users', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('users', true);
    }
}
