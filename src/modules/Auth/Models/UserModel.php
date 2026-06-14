<?php

namespace Modules\Auth\Models;

use CodeIgniter\Model;

/**
 * UserModel - Quản lý bảng users.
 *
 * Xử lý CRUD, tìm kiếm theo email/username, brute force protection,
 * và quản lý trạng thái tài khoản.
 */
class UserModel extends Model
{
    protected $table            = 'users';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = true;
    protected $useTimestamps    = true;

    /**
     * Các field cho phép mass-assignment.
     * Không bao gồm id, uuid (auto-gen), timestamps (auto).
     */
    protected $allowedFields = [
        'uuid',                    // UUID v4 public identifier (thay thế expose ID)
        'email',                   // Email đăng nhập, unique
        'username',                // Tên đăng nhập thay thế email, unique, nullable
        'phone',                   // Số điện thoại liên hệ
        'password_hash',           // Mật khẩu đã hash (Argon2id)
        'full_name',               // Họ tên đầy đủ hiển thị
        'avatar',                  // Đường dẫn file avatar (relative path)
        'role',                    // Phân quyền: super_admin | workspace_admin | user
        'status',                  // Trạng thái: active | locked | pending
        'email_verified_at',       // Thời điểm xác minh email
        'last_login_at',           // Lần đăng nhập gần nhất
        'last_login_ip',           // IP lần đăng nhập gần nhất
        'failed_login_attempts',   // Số lần đăng nhập sai liên tiếp
        'locked_until',            // Thời điểm hết khóa tạm (brute force)
    ];

    /**
     * Validation rules mặc định khi insert/update.
     */
    protected $validationRules = [
        'email'     => 'required|valid_email|max_length[254]|is_unique[users.email,id,{id}]',
        'username'  => 'permit_empty|alpha_numeric|min_length[3]|max_length[30]|is_unique[users.username,id,{id}]',
        'full_name' => 'required|max_length[100]',
        'role'      => 'in_list[super_admin,workspace_admin,user]',
    ];

    /**
     * Hook: tự động sinh UUID trước khi insert.
     */
    protected $beforeInsert = ['generateUuid'];

    /**
     * Tự động gán UUID v4 nếu chưa có.
     */
    protected function generateUuid(array $data): array
    {
        if (empty($data['data']['uuid'])) {
            $data['data']['uuid'] = $this->createUuid();
        }

        return $data;
    }

    // =========================================================================
    // LOOKUP METHODS
    // =========================================================================

    /**
     * Tìm user bằng email (case-insensitive).
     *
     * @param string $email Email cần tìm
     * @return object|null User object hoặc null nếu không tồn tại
     */
    public function findByEmail(string $email): ?object
    {
        return $this->where('email', strtolower(trim($email)))->first();
    }

    /**
     * Tìm user bằng username (case-insensitive).
     *
     * @param string $username Username cần tìm
     * @return object|null User object hoặc null nếu không tồn tại
     */
    public function findByUsername(string $username): ?object
    {
        return $this->where('username', strtolower(trim($username)))->first();
    }

    /**
     * Tìm user bằng email, username, HOẶC số điện thoại.
     * Dùng cho login — user có thể nhập bất kỳ loại identifier nào.
     *
     * Logic detect:
     * - Chứa "@" → email
     * - Bắt đầu bằng "+" hoặc toàn số (có thể có dấu cách/gạch) → phone
     * - Còn lại → username
     *
     * @param string $identifier Email, username, hoặc số điện thoại
     * @return object|null User object hoặc null
     */
    public function findByCredentialIdentifier(string $identifier): ?object
    {
        $identifier = trim($identifier);

        // Có @ → email
        if (str_contains($identifier, '@')) {
            return $this->findByEmail($identifier);
        }

        // Bắt đầu bằng + hoặc toàn số (cho phép dấu cách, gạch ngang) → phone
        $cleaned = preg_replace('/[\s\-]/', '', $identifier);
        if (preg_match('/^\+?[0-9]{7,15}$/', $cleaned)) {
            return $this->findByPhone($identifier);
        }

        // Còn lại → username
        return $this->findByUsername($identifier);
    }

    /**
     * Tìm user bằng số điện thoại.
     * So sánh sau khi loại bỏ ký tự khoảng trắng và gạch ngang.
     *
     * @param string $phone Số điện thoại cần tìm
     * @return object|null User object hoặc null nếu không tồn tại
     */
    public function findByPhone(string $phone): ?object
    {
        // Normalize: bỏ space và dash để so sánh chính xác
        $normalized = preg_replace('/[\s\-]/', '', trim($phone));

        return $this->where('phone', $normalized)->first();
    }

    /**
     * Tìm user active theo ID.
     * Dùng khi verify JWT — chỉ cho phép user đang active.
     *
     * @param int $id User ID
     * @return object|null User nếu active, null nếu không tồn tại hoặc bị khóa
     */
    public function findActiveById(int $id): ?object
    {
        return $this->where('id', $id)
                    ->where('status', STATUS_ACTIVE)
                    ->first();
    }

    // =========================================================================
    // LOGIN TRACKING
    // =========================================================================

    /**
     * Ghi nhận đăng nhập thành công.
     * Reset bộ đếm failed attempts và cập nhật IP/thời gian.
     */
    public function recordLogin(int $userId, string $ip): void
    {
        $this->update($userId, [
            'last_login_at'         => now_datetime(),
            'last_login_ip'         => $ip,
            'failed_login_attempts' => 0,
            'locked_until'          => null,
        ]);
    }

    /**
     * Tăng bộ đếm đăng nhập sai.
     * Nếu >= MAX_LOGIN_ATTEMPTS lần sai liên tiếp, tự động khóa tạm.
     */
    public function incrementFailedAttempts(int $userId): int
    {
        $user     = $this->find($userId);
        $attempts = ($user->failed_login_attempts ?? 0) + 1;

        $data = ['failed_login_attempts' => $attempts];

        // Khóa tạm sau N lần sai (chống brute force)
        if ($attempts >= MAX_LOGIN_ATTEMPTS) {
            $data['locked_until'] = future_datetime('+' . LOCK_DURATION_MINUTES . ' minutes');
        }

        $this->update($userId, $data);

        return $attempts;
    }

    /**
     * Kiểm tra tài khoản có đang bị khóa tạm không.
     *
     * @param object $user User object chứa trường locked_until
     * @return bool true nếu đang bị khóa
     */
    public function isLocked(object $user): bool
    {
        if (empty($user->locked_until)) {
            return false;
        }

        return strtotime($user->locked_until) > time();
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Tạo UUID v4 (RFC 4122 compliant).
     *
     * Sử dụng random_bytes() (CSPRNG) để đảm bảo tính ngẫu nhiên an toàn.
     * Byte thứ 7: set version = 4 (0100xxxx)
     * Byte thứ 9: set variant = RFC 4122 (10xxxxxx)
     *
     * @return string UUID dạng xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx
     */
    private function createUuid(): string
    {
        $data    = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // Version 4
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // Variant RFC 4122

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
