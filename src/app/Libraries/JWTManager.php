<?php

namespace App\Libraries;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;

/**
 * JWTManager - Quản lý vòng đời JWT token.
 *
 * Kiến trúc 2 token:
 * - Access Token (ngắn hạn, 15 phút): Gửi trong header Authorization.
 *   Stateless, verify bằng signature + check Redis blacklist.
 * - Refresh Token (dài hạn, 7 ngày): Lưu trong HttpOnly cookie.
 *   Stateful, hash lưu trong DB, hỗ trợ token rotation.
 *
 * Tại sao 2 token?
 * - Access token ngắn → nếu bị lộ, thiệt hại giới hạn trong 15 phút.
 * - Refresh token dài → UX tốt, không cần đăng nhập lại liên tục.
 * - Token rotation → phát hiện refresh token bị đánh cắp (reuse detection).
 */
class JWTManager
{
    /** @var string Secret key ký JWT (HMAC-SHA256) */
    private string $secretKey;

    /** @var string Thuật toán ký: HS256 (HMAC + SHA-256) */
    private string $algorithm = 'HS256';

    /** @var int Access token TTL tính bằng giây (default: 900 = 15 phút) */
    private int $accessTtl;

    /** @var int Refresh token TTL tính bằng giây (default: 604800 = 7 ngày) */
    private int $refreshTtl;

    public function __construct()
    {
        $this->secretKey  = env('JWT_SECRET_KEY', '');
        $this->accessTtl  = (int) env('JWT_ACCESS_TTL', 900);
        $this->refreshTtl = (int) env('JWT_REFRESH_TTL', 604800);

        if (empty($this->secretKey)) {
            throw new \RuntimeException('JWT_SECRET_KEY is not configured.');
        }
    }

    /**
     * Sinh access token cho user đã xác thực.
     *
     * Payload chứa:
     * - iss: Issuer (domain phát hành)
     * - iat: Issued At (thời điểm phát hành)
     * - exp: Expiration (hết hạn)
     * - jti: JWT ID (unique, dùng cho blacklist)
     * - sub: Subject (user ID)
     * - uuid: User UUID (public identifier)
     * - role: User role (phân quyền)
     *
     * @param array $userData Chứa id, uuid, role
     * @return array{token: string, jti: string, expires_at: int}
     */
    public function generateAccessToken(array $userData): array
    {
        $now = time();
        // JTI: unique ID cho mỗi token, dùng khi cần blacklist (logout)
        $jti = bin2hex(random_bytes(16));

        $payload = [
            'iss'  => env('app.baseURL', 'http://localhost'),
            'iat'  => $now,
            'exp'  => $now + $this->accessTtl,
            'jti'  => $jti,
            'sub'  => $userData['id'],   // UUID string
            'role' => $userData['role'],
        ];

        $token = JWT::encode($payload, $this->secretKey, $this->algorithm);

        RedisService::setUserSession($userData['id'], $jti, $this->accessTtl);

        return [
            'token'      => $token,
            'jti'        => $jti,
            'expires_at' => $now + $this->accessTtl,
        ];
    }

    /**
     * Sinh refresh token (opaque, không phải JWT).
     *
     * Refresh token là chuỗi random 64 hex chars.
     * Chỉ lưu SHA-256 hash vào DB (nếu DB bị lộ, attacker không dùng được token gốc).
     * Token gốc gửi cho client qua HttpOnly cookie.
     *
     * @return array{token: string, hash: string, expires_at: int}
     */
    public function generateRefreshToken(): array
    {
        // 32 bytes random = 64 hex chars (cryptographically secure)
        $token = bin2hex(random_bytes(32));

        return [
            'token'      => $token,                    // Gửi cho client (cookie)
            'hash'       => hash('sha256', $token),    // Lưu DB (so sánh khi refresh)
            'expires_at' => time() + $this->refreshTtl,
        ];
    }

    /**
     * Validate và decode access token.
     *
     * Quy trình:
     * 1. Decode JWT (verify signature + check expiration)
     * 2. Check Redis blacklist (token đã bị logout chưa)
     * 3. Trả về payload nếu hợp lệ
     *
     * @param string $token JWT access token
     * @return object|null Decoded payload hoặc null nếu invalid/expired/blacklisted
     */
    public function validateToken(string $token): ?object
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secretKey, $this->algorithm));

            // Check blacklist: token có thể chưa hết hạn nhưng đã bị revoke (logout)
            if (RedisService::isTokenBlacklisted($decoded->jti)) {
                return null;
            }

            return $decoded;
        } catch (ExpiredException $e) {
            // Token hết hạn tự nhiên → client cần dùng refresh token
            return null;
        } catch (\Exception $e) {
            // Signature invalid, malformed token, v.v.
            return null;
        }
    }

    /**
     * Vô hiệu hóa token bằng cách đưa JTI vào Redis blacklist.
     *
     * TTL = thời gian còn lại của token (không cần lưu vĩnh viễn,
     * vì sau khi hết hạn tự nhiên thì token cũng invalid).
     *
     * @param string $jti          JWT ID cần blacklist
     * @param int    $remainingTtl Thời gian còn lại trước khi token tự hết hạn
     */
    public function invalidateToken(string $jti, int $remainingTtl): void
    {
        if ($remainingTtl > 0) {
            RedisService::blacklistToken($jti, $remainingTtl);
        }
    }

    /**
     * @return int Access token TTL (giây)
     */
    public function getAccessTtl(): int
    {
        return $this->accessTtl;
    }

    /**
     * @return int Refresh token TTL (giây)
     */
    public function getRefreshTtl(): int
    {
        return $this->refreshTtl;
    }
}
