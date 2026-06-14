<?php

namespace Modules\Auth\Models;

use CodeIgniter\Model;

/**
 * RefreshTokenModel - Quản lý bảng refresh_tokens.
 *
 * Lưu SHA-256 hash của refresh token (không lưu token gốc).
 * Mỗi user có thể có nhiều refresh token (multi-device login).
 * Hỗ trợ token rotation: mỗi lần refresh → xóa cũ, cấp mới.
 */
class RefreshTokenModel extends Model
{
    protected $table            = 'refresh_tokens';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useTimestamps    = false;

    protected $allowedFields = [
        'user_id',      // FK → users.uuid (owner của token)
        'token_hash',   // SHA-256 hash của refresh token (không lưu plaintext)
        'expires_at',   // Thời điểm hết hạn (7 ngày từ lúc tạo)
        'ip_address',   // IP tạo token (audit trail)
        'user_agent',   // Browser/device info (audit trail)
        'created_at',   // Thời điểm phát hành token
    ];

    /**
     * Lưu refresh token mới vào DB.
     *
     * @param int         $userId    Owner user ID
     * @param string      $tokenHash SHA-256 hash của token
     * @param string      $expiresAt Datetime hết hạn
     * @param string      $ip        IP address phát hành
     * @param string|null $userAgent Browser user-agent string
     */
    public function storeToken(string $userId, string $tokenHash, string $expiresAt, string $ip, ?string $userAgent): void
    {
        $this->insert([
            'user_id'    => $userId,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
            'ip_address' => $ip,
            'user_agent' => $userAgent ? substr($userAgent, 0, 255) : null,
            'created_at' => now_datetime(),
        ]);
    }

    /**
     * Tìm refresh token hợp lệ (chưa hết hạn) theo hash.
     *
     * Client gửi token gốc → server hash rồi so sánh với DB.
     * Chỉ trả về token chưa expired.
     *
     * @param string $tokenHash SHA-256 hash cần tìm
     * @return object|null Token record hoặc null
     */
    public function findValidToken(string $tokenHash): ?object
    {
        return $this->where('token_hash', $tokenHash)
                    ->where('expires_at >', now_datetime())
                    ->first();
    }

    /**
     * Thu hồi 1 refresh token cụ thể (xóa khỏi DB).
     *
     * Dùng khi: logout thiết bị hiện tại, hoặc token rotation.
     *
     * @param string $tokenHash SHA-256 hash của token cần revoke
     */
    public function revokeToken(string $tokenHash): void
    {
        $this->where('token_hash', $tokenHash)->delete();
    }

    /**
     * Thu hồi toàn bộ refresh token của 1 user (xóa hết).
     *
     * Dùng khi: logout-all, đổi mật khẩu, admin lock account.
     * Kết quả: user bị đá ra khỏi tất cả thiết bị.
     *
     * @param int $userId User ID cần revoke hết
     */
    public function revokeAllForUser(string $userId): void
    {
        $this->where('user_id', $userId)->delete();
    }

    /**
     * Dọn dẹp token đã hết hạn (chạy qua cron job hàng ngày).
     *
     * Token hết hạn vẫn nằm trong DB cho đến khi cleanup.
     * Giữ DB gọn gàng, tránh bảng phình to theo thời gian.
     *
     * @return int Số record đã xóa
     */
    public function purgeExpired(): int
    {
        return $this->where('expires_at <', now_datetime())->delete();
    }
}
