<?php

namespace Modules\Auth\Controllers;

use App\Controllers\ApiController;
use App\Libraries\JWTManager;
use App\Libraries\RedisService;
use CodeIgniter\HTTP\ResponseInterface;
use Modules\Auth\Models\UserModel;
use Modules\Auth\Models\RefreshTokenModel;

/**
 * AuthController - Xử lý xác thực người dùng.
 *
 * Bao gồm: đăng ký, đăng nhập (email hoặc username), refresh token,
 * đăng xuất, đăng xuất toàn bộ thiết bị, đổi mật khẩu.
 *
 * Security: JWT access token (15 phút) + HttpOnly refresh cookie (7 ngày).
 * Brute force protection qua Redis rate limiting + account lock.
 */
class AuthController extends ApiController
{
    private UserModel $userModel;
    private RefreshTokenModel $refreshModel;
    private JWTManager $jwt;

    public function __construct()
    {
        $this->userModel    = new UserModel();
        $this->refreshModel = new RefreshTokenModel();
        $this->jwt          = new JWTManager();
    }

    /**
     * Đăng ký tài khoản mới.
     *
     * POST /api/auth/register
     *
     * Body: email, password, full_name, phone?, username?
     * Rate limit: 1 request / 5 giây / IP (chống spam đăng ký).
     *
     * @return ResponseInterface 201 nếu thành công, 422 nếu validation lỗi
     */
    public function register(): ResponseInterface
    {
        $isJson = str_contains($this->request->getHeaderLine('Content-Type'), 'application/json');
        $input  = $isJson ? (array) $this->request->getJSON(true) : $this->request->getPost();

        $rules = [
            'email'     => 'required|valid_email|max_length[254]|is_unique[users.email]',
            'username'  => 'permit_empty|alpha_numeric|min_length[3]|max_length[30]|is_unique[users.username]',
            'password'  => 'required|min_length[4]|max_length[72]',
            'full_name' => 'required|min_length[2]|max_length[100]',
            'phone'     => 'permit_empty|max_length[20]|regex_match[/^\+?[0-9\s\-]+$/]',
        ];

        if (! $this->validateData($input, $rules)) {
            return $this->error('Validation failed.', 422, $this->validator->getErrors());
        }

        $email = strtolower(trim($input['email'] ?? ''));

        // Rate limit: chặn spam đăng ký
        $remaining = RedisService::rateLimit(
            "register:{$this->request->getIPAddress()}",
            REGISTER_RATE_LIMIT,
            REGISTER_RATE_WINDOW
        );

        if ($remaining === -1) {
            return $this->error('Too many requests. Please wait.', 429);
        }

        // Chuẩn bị dữ liệu insert
        $rawPhone = $input['phone'] ?? null;

        $insertData = [
            'email'         => $email,
            'password_hash' => hash_password($input['password'] ?? ''),
            'full_name'     => trim($input['full_name'] ?? ''),
            'phone'         => $rawPhone ? preg_replace('/[\s\-]/', '', trim($rawPhone)) : null,
            'role'          => ROLE_USER,
            'status'        => STATUS_ACTIVE,
        ];

        // Username là optional — nếu có thì lưu lowercase
        $username = $input['username'] ?? null;
        if (! empty($username)) {
            $insertData['username'] = strtolower(trim($username));
        }

        $userId = $this->userModel->insert($insertData);

        if (! $userId) {
            return $this->error('Registration failed.', 500);
        }

        $user = $this->userModel->find($userId);

        return $this->success([
            'uuid'      => $user->uuid,
            'email'     => $user->email,
            'username'  => $user->username,
            'full_name' => $user->full_name,
        ], 'Registration successful.', 201);
    }

    /**
     * Đăng nhập bằng email hoặc username.
     *
     * POST /api/auth/login
     *
     * Body: identifier (email hoặc username), password
     * - Nếu identifier chứa "@" → tìm theo email
     * - Nếu không → tìm theo username
     *
     * Rate limit: 10 lần / phút / IP.
     * Lock account: sau 5 lần sai mật khẩu → khóa 30 phút.
     *
     * @return ResponseInterface 200 + tokens nếu thành công
     */
    public function login(): ResponseInterface
    {
        // Hỗ trợ cả JSON body lẫn form-encoded
        $isJson = str_contains($this->request->getHeaderLine('Content-Type'), 'application/json');
        $input  = $isJson ? (array) $this->request->getJSON(true) : $this->request->getPost();

        $rules = [
            'identifier' => 'required|min_length[3]|max_length[254]',
            'password'   => 'required',
        ];

        if (! $this->validateData($input, $rules)) {
            return $this->error('Validation failed.', 422, $this->validator->getErrors());
        }

        $ip         = $this->request->getIPAddress();
        $identifier = trim($input['identifier'] ?? '');

        // Rate limit: 10 lần thử đăng nhập / phút / IP
        $remaining = RedisService::rateLimit("login:{$ip}", LOGIN_RATE_LIMIT, LOGIN_RATE_WINDOW);

        if ($remaining === -1) {
            return $this->error('Too many login attempts. Please try again later.', 429);
        }

        // Tìm user bằng email hoặc username (tự detect)
        $user = $this->userModel->findByCredentialIdentifier($identifier);

        if (! $user) {
            return $this->error('Invalid credentials.', 401);
        }

        // Kiểm tra khóa tạm (brute force lock)
        if ($this->userModel->isLocked($user)) {
            return $this->error('Account temporarily locked. Try again later.', 423);
        }

        // Kiểm tra trạng thái bị admin khóa vĩnh viễn
        if ($user->status === STATUS_LOCKED) {
            return $this->error('Account has been suspended.', 403);
        }

        // Xác minh mật khẩu
        if (! verify_password($input['password'] ?? '', $user->password_hash)) {
            $this->userModel->incrementFailedAttempts($user->uuid);
            RedisService::incrementLoginAttempt($ip);

            return $this->error('Invalid credentials.', 401);
        }

        // === Đăng nhập thành công ===

        // Sinh JWT access token (ngắn hạn, 15 phút)
        $accessToken = $this->jwt->generateAccessToken([
            'id'   => $user->uuid,   // UUID là sub trong JWT
            'role' => $user->role,
        ]);

        // Sinh refresh token (dài hạn, 7 ngày, lưu DB)
        $refreshToken = $this->jwt->generateRefreshToken();

        $this->refreshModel->storeToken(
            $user->uuid,
            $refreshToken['hash'],
            timestamp_to_datetime($refreshToken['expires_at']),
            $ip,
            $this->request->getUserAgent()->getAgentString()
        );

        $this->userModel->recordLogin($user->uuid, $ip);
        RedisService::clearLoginAttempts($ip);

        // Gửi refresh token qua HttpOnly cookie (client không đọc được bằng JS)
        $this->response->setCookie(
            name: 'refresh_token',
            value: $refreshToken['token'],
            expire: $this->jwt->getRefreshTtl(),
            path: '/',
            secure: (bool) env('app.forceGlobalSecureRequests', false),
            httponly: true,     // Chống XSS: JS không truy cập được cookie này
            samesite: 'Strict' // Chống CSRF: chỉ gửi cookie cho same-origin request
        );

        return $this->success([
            'access_token' => $accessToken['token'],
            'token_type'   => 'Bearer',
            'expires_in'   => $this->jwt->getAccessTtl(),
            'user'         => [
                'uuid'       => $user->uuid,
                'email'      => $user->email,
                'username'   => $user->username,
                'full_name'  => $user->full_name,
                'avatar_url' => $user->avatar ? '/uploads/avatars/' . $user->avatar : null,
                'role'       => $user->role,
            ],
        ], 'Login successful.');
    }

    /**
     * Làm mới access token bằng refresh token.
     *
     * POST /api/auth/refresh
     *
     * Refresh token lấy từ HttpOnly cookie (tự gửi bởi browser).
     * Áp dụng token rotation: revoke token cũ, cấp token mới.
     * Nếu refresh token bị reuse (đã revoke) → có thể bị đánh cắp → hệ thống phát hiện.
     *
     * @return ResponseInterface 200 + access_token mới
     */
    public function refresh(): ResponseInterface
    {
        $token = $this->request->getCookie('refresh_token');

        if (empty($token)) {
            return $this->error('Refresh token not found.', 401);
        }

        // So sánh hash (DB chỉ lưu hash, không lưu token gốc)
        $tokenHash   = hash('sha256', $token);
        $storedToken = $this->refreshModel->findValidToken($tokenHash);

        if (! $storedToken) {
            return $this->error('Invalid or expired refresh token.', 401);
        }

        $user = $this->userModel->findActiveByUuid($storedToken->user_id);

        if (! $user) {
            $this->refreshModel->revokeToken($tokenHash);
            return $this->error('User not found or inactive.', 401);
        }

        // Token rotation: xóa token cũ, cấp mới (phát hiện token theft)
        $this->refreshModel->revokeToken($tokenHash);

        $accessToken = $this->jwt->generateAccessToken([
            'id'   => $user->uuid,
            'role' => $user->role,
        ]);

        $newRefresh = $this->jwt->generateRefreshToken();

        $this->refreshModel->storeToken(
            $user->uuid,
            $newRefresh['hash'],
            timestamp_to_datetime($newRefresh['expires_at']),
            $this->request->getIPAddress(),
            $this->request->getUserAgent()->getAgentString()
        );

        $this->response->setCookie(
            name: 'refresh_token',
            value: $newRefresh['token'],
            expire: $this->jwt->getRefreshTtl(),
            path: '/',
            secure: (bool) env('app.forceGlobalSecureRequests', false),
            httponly: true,
            samesite: 'Strict'
        );

        return $this->success([
            'access_token' => $accessToken['token'],
            'token_type'   => 'Bearer',
            'expires_in'   => $this->jwt->getAccessTtl(),
        ], 'Token refreshed.');
    }

    /**
     * Đăng xuất thiết bị hiện tại.
     *
     * POST /api/auth/logout
     *
     * - Blacklist JWT hiện tại vào Redis (cho đến khi hết hạn tự nhiên)
     * - Xóa refresh token khỏi DB
     * - Xóa cookie refresh_token
     *
     * @return ResponseInterface
     */
    public function logout(): ResponseInterface
    {
        $authUser = $this->getAuthUser();

        if ($authUser) {
            // Tính thời gian còn lại của JWT, blacklist đúng khoảng đó
            $remainingTtl = $authUser->exp - time();
            $this->jwt->invalidateToken($authUser->jti, $remainingTtl);

            // Xóa refresh token khỏi DB
            $refreshCookie = $this->request->getCookie('refresh_token');
            if ($refreshCookie) {
                $this->refreshModel->revokeToken(hash('sha256', $refreshCookie));
            }
        }

        $this->response->deleteCookie('refresh_token', path: '/');

        return $this->success(null, 'Logged out successfully.');
    }

    /**
     * Đăng xuất tất cả thiết bị.
     *
     * POST /api/auth/logout-all
     *
     * - Xóa toàn bộ refresh token của user khỏi DB
     * - Xóa toàn bộ session keys trên Redis
     * - Kết quả: tất cả access token cũ sẽ bị từ chối khi Redis check
     *
     * @return ResponseInterface
     */
    public function logoutAll(): ResponseInterface
    {
        $authUser = $this->getAuthUser();

        if ($authUser) {
            $this->refreshModel->revokeAllForUser($authUser->sub);
            RedisService::revokeAllSessions($authUser->sub);
        }

        $this->response->deleteCookie('refresh_token', path: '/');

        return $this->success(null, 'All sessions terminated.');
    }

    /**
     * Lấy thông tin user đang đăng nhập.
     *
     * GET /api/auth/me
     *
     * @return ResponseInterface User profile data
     */
    public function me(): ResponseInterface
    {
        $authUser = $this->getAuthUser();
        $user     = $this->userModel->findActiveByUuid($authUser->sub);

        if (! $user) {
            return $this->error('User not found.', 404);
        }

        return $this->success([
            'uuid'       => $user->uuid,
            'email'      => $user->email,
            'username'   => $user->username,
            'full_name'  => $user->full_name,
            'avatar_url' => $user->avatar ? '/uploads/avatars/' . $user->avatar : null,
            'phone'      => $user->phone,
            'role'       => $user->role,
            'created_at' => $user->created_at,
        ]);
    }

    /**
     * Đổi mật khẩu.
     *
     * PUT /api/auth/change-password
     *
     * Body: current_password, new_password
     * Sau khi đổi: revoke toàn bộ session → bắt buộc đăng nhập lại tất cả thiết bị.
     *
     * @return ResponseInterface
     */
    public function changePassword(): ResponseInterface
    {
        $isJson = str_contains($this->request->getHeaderLine('Content-Type'), 'application/json');
        $input  = $isJson ? (array) $this->request->getJSON(true) : $this->request->getPost();

        $rules = [
            'current_password' => 'required',
            'new_password'     => 'required|min_length[4]|max_length[72]',
        ];

        if (! $this->validateData($input, $rules)) {
            return $this->error('Validation failed.', 422, $this->validator->getErrors());
        }

        $authUser = $this->getAuthUser();
        $user     = $this->userModel->find($authUser->sub); // PK = uuid, find by uuid

        if (! verify_password($input['current_password'] ?? '', $user->password_hash)) {
            return $this->error('Current password is incorrect.', 401);
        }

        $this->userModel->update($user->uuid, [
            'password_hash' => hash_password($input['new_password'] ?? ''),
        ]);

        $this->refreshModel->revokeAllForUser($user->uuid);
        RedisService::revokeAllSessions($user->uuid);

        return $this->success(null, 'Password changed. Please login again.');
    }
}
