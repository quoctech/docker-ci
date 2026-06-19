<?php

namespace App\Libraries;

use Predis\Client;

/**
 * RedisService - Singleton wrapper cho Predis client.
 *
 * Cung cấp các utility methods cho:
 * - JWT token blacklist (logout mà không đợi token hết hạn)
 * - Session management (revoke all devices)
 * - Rate limiting (chống brute force, spam)
 * - Module status cache (dynamic routing)
 *
 * Tất cả methods là static để tiện gọi từ bất kỳ đâu.
 * Connection được tái sử dụng trong suốt 1 request (singleton).
 */
class RedisService
{
    /** @var Client|null Singleton Predis instance */
    private static ?Client $instance = null;

    /**
     * Lấy Predis client instance (singleton pattern).
     * Kết nối lazy: chỉ tạo connection khi thực sự cần.
     *
     * @return Client Predis client đã kết nối
     */
    public static function getInstance(): Client
    {
        if (self::$instance === null) {
            self::$instance = new Client([
                'scheme' => 'tcp',
                'host'   => env('REDIS_HOST', 'redis'),
                'port'   => (int) env('REDIS_PORT', 6379),
            ]);
        }

        return self::$instance;
    }

    // =========================================================================
    // JWT TOKEN BLACKLIST
    // =========================================================================

    /**
     * Đưa 1 JWT vào blacklist (dùng khi logout).
     *
     * JWT là stateless — một khi đã cấp thì không thu hồi được.
     * Giải pháp: lưu JTI (JWT ID) vào Redis với TTL = thời gian còn lại của token.
     * Khi validate token, check Redis trước → nếu có trong blacklist → reject.
     *
     * @param string $jti JWT ID cần blacklist
     * @param int    $ttl Thời gian sống còn lại của token (giây)
     */
    public static function blacklistToken(string $jti, int $ttl): void
    {
        self::getInstance()->setex(REDIS_PREFIX_BLACKLIST . $jti, $ttl, '1');
    }

    /**
     * Kiểm tra JWT có bị blacklist không.
     *
     * @param string $jti JWT ID cần check
     * @return bool true nếu token đã bị revoke
     */
    public static function isTokenBlacklisted(string $jti): bool
    {
        return (bool) self::getInstance()->exists(REDIS_PREFIX_BLACKLIST . $jti);
    }

    // =========================================================================
    // SESSION MANAGEMENT
    // =========================================================================

    /**
     * Lưu session reference cho user (tracking thiết bị đang login).
     *
     * Key pattern: session:user:{userId}:{jti}
     * Dùng để biết user đang có bao nhiêu session active.
     *
     * @param int    $userId User ID
     * @param string $jti    JWT ID của session này
     * @param int    $ttl    Thời gian sống (= access token TTL)
     */
    public static function setUserSession(string $userUuid, string $jti, int $ttl): void
    {
        self::getInstance()->setex(REDIS_PREFIX_SESSION . "{$userUuid}:{$jti}", $ttl, '1');
    }

    /**
     * Hủy toàn bộ session của 1 user (logout everywhere).
     *
     * @param string $userUuid User UUID
     */
    public static function revokeAllSessions(string $userUuid): void
    {
        $redis = self::getInstance();
        $keys  = $redis->keys(REDIS_PREFIX_SESSION . "{$userUuid}:*");

        if (! empty($keys)) {
            $redis->del($keys);
        }
    }

    /**
     * Hủy toàn bộ session VÀ blacklist tất cả JWT đang active của 1 user.
     *
     * Dùng sau khi thay đổi quyền module — đảm bảo user bị đăng xuất ngay
     * lập tức thay vì chờ JWT hết hạn tự nhiên (max 15 phút).
     *
     * @param string $userUuid User UUID
     */
    public static function forceLogoutUser(string $userUuid): void
    {
        $redis  = self::getInstance();
        $prefix = REDIS_PREFIX_SESSION . "{$userUuid}:";
        $keys   = $redis->keys($prefix . '*');

        if (! empty($keys)) {
            foreach ($keys as $key) {
                $jti = substr($key, strlen($prefix));
                $ttl = $redis->ttl($key);
                if ($jti && $ttl > 0) {
                    $redis->setex(REDIS_PREFIX_BLACKLIST . $jti, $ttl, '1');
                }
            }
            $redis->del($keys);
        }
    }

    // =========================================================================
    // LOGIN ATTEMPT TRACKING (BRUTE FORCE PROTECTION)
    // =========================================================================

    /**
     * Tăng bộ đếm login sai cho 1 identifier (IP hoặc email).
     *
     * Key tự hết hạn sau 15 phút (sliding window).
     * Dùng kết hợp với account lock (DB) để chống brute force 2 lớp.
     *
     * @param string $identifier IP address hoặc email
     * @return int Số lần sai hiện tại (sau khi tăng)
     */
    public static function incrementLoginAttempt(string $identifier): int
    {
        $key   = REDIS_PREFIX_LOGIN . $identifier;
        $redis = self::getInstance();
        $count = $redis->incr($key);

        // Set TTL lần đầu tiên (15 phút window)
        if ($count === 1) {
            $redis->expire($key, 900);
        }

        return $count;
    }

    /**
     * Lấy số lần login sai hiện tại.
     *
     * @param string $identifier IP address hoặc email
     * @return int Số lần sai (0 nếu chưa có record)
     */
    public static function getLoginAttempts(string $identifier): int
    {
        return (int) self::getInstance()->get(REDIS_PREFIX_LOGIN . $identifier);
    }

    /**
     * Xóa bộ đếm login sai (sau khi login thành công).
     *
     * @param string $identifier IP address hoặc email
     */
    public static function clearLoginAttempts(string $identifier): void
    {
        self::getInstance()->del(REDIS_PREFIX_LOGIN . $identifier);
    }

    // =========================================================================
    // MODULE STATUS CACHE
    // =========================================================================

    /**
     * Cập nhật trạng thái bật/tắt của 1 module vào Redis.
     *
     * Dynamic routing: khi request đến, CI4 check Redis xem module có bật không.
     * Nếu tắt → trả 503 ngay, không cần query DB.
     *
     * @param string $slug    Module slug (unique identifier)
     * @param bool   $enabled true = bật, false = tắt
     */
    public static function setModuleStatus(string $slug, bool $enabled): void
    {
        self::getInstance()->hset(REDIS_KEY_MODULES, $slug, $enabled ? '1' : '0');
    }

    /**
     * Lấy trạng thái module từ Redis cache.
     *
     * @param string $slug Module slug
     * @return bool|null true/false nếu có cache, null nếu chưa cache (cần query DB)
     */
    public static function getModuleStatus(string $slug): ?bool
    {
        $value = self::getInstance()->hget(REDIS_KEY_MODULES, $slug);

        return $value === null ? null : $value === '1';
    }

    /**
     * Đồng bộ toàn bộ trạng thái module lên Redis (bulk operation).
     *
     * Gọi khi khởi động app hoặc admin nhấn "sync cache".
     * Xóa hash cũ rồi ghi mới để tránh stale data.
     *
     * @param array<string, bool> $modules Map slug => enabled
     */
    public static function syncModuleStatuses(array $modules): void
    {
        $redis = self::getInstance();
        $redis->del(REDIS_KEY_MODULES);

        foreach ($modules as $slug => $enabled) {
            $redis->hset(REDIS_KEY_MODULES, $slug, $enabled ? '1' : '0');
        }
    }

    // =========================================================================
    // RATE LIMITING
    // =========================================================================

    /**
     * Rate limiting sử dụng fixed window counter.
     *
     * Cơ chế: đếm số request trong 1 khoảng thời gian cố định.
     * Key tự expire sau window → reset bộ đếm.
     *
     * Ví dụ: rateLimit("login:192.168.1.1", 10, 60) → tối đa 10 request / 60 giây.
     *
     * LƯU Ý: INCR trước, kiểm tra sau — đảm bảo atomic, không có race condition.
     * Pattern cũ (GET → check → INCR) có thể bị bypass khi 2 request đồng thời
     * cùng đọc được giá trị dưới ngưỡng rồi cùng INCR qua giới hạn.
     *
     * @param string $key           Key định danh (thường là "action:identifier")
     * @param int    $maxRequests   Số request tối đa trong window
     * @param int    $windowSeconds Kích thước window (giây)
     * @return int Số request còn lại (>= 0), hoặc -1 nếu đã vượt giới hạn
     */
    public static function rateLimit(string $key, int $maxRequests, int $windowSeconds): int
    {
        $redis    = self::getInstance();
        $cacheKey = REDIS_PREFIX_RATE . $key;

        // INCR là atomic trong Redis — không có race condition
        $count = (int) $redis->incr($cacheKey);

        // Lần đầu tiên trong window → set TTL
        if ($count === 1) {
            $redis->expire($cacheKey, $windowSeconds);
        }

        if ($count > $maxRequests) {
            return -1;
        }

        return $maxRequests - $count;
    }
}
