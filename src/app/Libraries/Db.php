<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use Config\Database;

/**
 * Db - Singleton wrapper cho database connection.
 *
 * Lý do tạo singleton thay vì gọi `\Config\Database::connect()` trực tiếp:
 *   - Đảm bảo chỉ MỘT connection instance tồn tại trong suốt request lifecycle.
 *   - Code dễ đọc: `Db::table('users')` thay vì `\Config\Database::connect()->table('users')`.
 *   - Dễ mock trong test: có thể thay thế instance qua `Db::setInstance()`.
 *   - Tránh gọi `connect()` nhiều lần — CI4 cũng đã làm singleton nội bộ
 *     nhưng wrapper này làm rõ ý đồ.
 *
 * Lưu ý: CI4's `\Config\Database::connect()` cũng là singleton theo group,
 * wrapper này chỉ là một lớp trung gian giúp code sạch hơn.
 */
final class Db
{
    /** @var BaseConnection|null Singleton instance */
    private static ?BaseConnection $instance = null;

    /** @var string Database group (mặc định: 'default') */
    private static string $group = 'default';

    /**
     * Lấy connection instance (singleton).
     *
     * @param string|null $group Tên group (null = dùng group hiện tại)
     * @return BaseConnection
     */
    public static function connection(?string $group = null): BaseConnection
    {
        $group = $group ?? self::$group;

        if (self::$instance === null || self::$group !== $group) {
            self::$instance = Database::connect($group);
            self::$group = $group;
        }

        return self::$instance;
    }

    /**
     * Shorthand: lấy query builder cho table.
     *
     * Ví dụ:
     *   Db::table('users')->where('email', $email)->get();
     *   Db::table('classroom_members')->join(...);
     *
     * @param string $tableName
     * @return \CodeIgniter\Database\BaseBuilder
     */
    public static function table(string $tableName): \CodeIgniter\Database\BaseBuilder
    {
        return self::connection()->table($tableName);
    }

    /**
     * Đóng connection hiện tại (dùng cho cleanup hoặc test).
     */
    public static function close(): void
    {
        if (self::$instance !== null) {
            self::$instance->close();
            self::$instance = null;
        }
    }

    /**
     * Inject connection (chỉ dùng cho test).
     *
     * @internal
     */
    public static function setInstance(BaseConnection $connection): void
    {
        self::$instance = $connection;
    }
}