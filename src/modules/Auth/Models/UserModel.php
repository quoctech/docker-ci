<?php

namespace Modules\Auth\Models;

use CodeIgniter\Model;

/**
 * UserModel - Quản lý bảng users.
 *
 * Xử lý CRUD, tìm kiếm theo email/username, brute force protection,
 * và quản lý trạng thái tài khoản.
 *
 * Lưu ý: Từ migration 2026-06-21-000027, bảng KHÔNG còn cột `role`.
 *   - `is_super_admin` (TINYINT): chỉ 1 user duy nhất được set = 1.
 *   - Role khác của user được lưu trong bảng `user_applied_roles` (JOIN `roles`).
 *   - Method `getEffectiveRole()` trả về role dựa trên is_super_admin + user_applied_roles.
 */
class UserModel extends Model
{
    protected $table            = 'users';
    protected $primaryKey       = 'uuid';
    protected $useAutoIncrement = false;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = true;
    protected $useTimestamps    = true;

    /**
     * Các field cho phép mass-assignment.
     * Không bao gồm uuid (là PK, set qua beforeInsert hook), timestamps (auto).
     * KHÔNG còn `role` — role được quản lý qua bảng `roles` + `user_applied_roles`.
     */
    protected $allowedFields = [
        'uuid',                    // UUID v4 — primary key
        'email',                   // Email đăng nhập, unique
        'username',                // Tên đăng nhập thay thế email, unique, nullable
        'phone',                   // Số điện thoại liên hệ
        'password_hash',           // Mật khẩu đã hash (Argon2id)
        'full_name',               // Họ tên đầy đủ hiển thị
        'avatar',                  // Đường dẫn file avatar (relative path)
        'is_super_admin',          // Cờ super_admin (chỉ 1 user duy nhất)
        'grade',                   // Lớp học (1–9), dành cho học sinh
        'organization',            // Tổ chức / Trường, dành cho giáo viên
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
     */
    public function findByEmail(string $email): ?object
    {
        return $this->where('email', strtolower(trim($email)))->first();
    }

    /**
     * Tìm user bằng username (case-insensitive).
     */
    public function findByUsername(string $username): ?object
    {
        return $this->where('username', strtolower(trim($username)))->first();
    }

    /**
     * Tìm user bằng email, username, HOẶC số điện thoại.
     * Dùng cho login — user có thể nhập bất kỳ loại identifier nào.
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
     * Tìm user bằng số điện thoại (so sánh sau khi bỏ space và dash).
     */
    public function findByPhone(string $phone): ?object
    {
        $normalized = preg_replace('/[\s\-]/', '', trim($phone));

        return $this->where('phone', $normalized)->first();
    }

    /**
     * Tìm user active theo UUID (dùng cho JWT sub lookup).
     */
    public function findActiveByUuid(string $uuid): ?object
    {
        return $this->where('uuid', $uuid)
                    ->where('status', STATUS_ACTIVE)
                    ->first();
    }

    /**
     * Lấy effective role của user dựa trên is_super_admin + user_applied_roles.
     *
     * - is_super_admin = 1  → 'super_admin' (chỉ 1 user duy nhất)
     * - Có role trong user_applied_roles → slug của role đầu tiên
     * - Mặc định → 'user' (fallback nếu chưa được gán role)
     */
    public function getEffectiveRole(string $userUuid): string
    {
        $user = $this->find($userUuid);
        if (! $user) {
            return 'user';
        }

        // Super_admin: luôn là 'super_admin'
        if ((int) ($user->is_super_admin ?? 0) === 1) {
            return 'super_admin';
        }

        $db = \Config\Database::connect();

        // Lấy TẤT CẢ role của user (active only)
        $rows = $db->table('user_applied_roles uar')
            ->select('r.slug', false)
            ->join('roles r', 'r.id = uar.role_id AND r.is_active = 1', 'inner', false)
            ->where('uar.user_uuid', $userUuid)
            ->orderBy('uar.applied_at', 'ASC')
            ->get()
            ->getResultArray();

        if (empty($rows)) {
            return 'user';  // fallback nếu không có role nào
        }

        // Ưu tiên: super-admin > workspace-admin > user
        $priority = ['super-admin' => 3, 'workspace-admin' => 2, 'user' => 1];
        // Trả về format underscore để tương thích với code cũ
        $slugToValue = ['super-admin' => 'super_admin', 'workspace-admin' => 'workspace_admin', 'user' => 'user'];
        $bestRole = 'user';
        $bestPriority = 0;
        foreach ($rows as $row) {
            $slug = $row['slug'];
            $p    = $priority[$slug] ?? 0;
            if ($p > $bestPriority) {
                $bestPriority = $p;
                $bestRole     = $slug;
            }
        }

        return $slugToValue[$bestRole] ?? $bestRole;
    }

    /**
     * Check user có phải super_admin không.
     */
    public function isSuperAdmin(string $userUuid): bool
    {
        $user = $this->find($userUuid);
        return $user && (int) ($user->is_super_admin ?? 0) === 1;
    }

    // =========================================================================
    // LOGIN TRACKING
    // =========================================================================

    public function recordLogin(string $uuid, string $ip): void
    {
        $this->update($uuid, [
            'last_login_at'         => now_datetime(),
            'last_login_ip'         => $ip,
            'failed_login_attempts' => 0,
            'locked_until'          => null,
        ]);
    }

    public function incrementFailedAttempts(string $uuid): int
    {
        $user     = $this->find($uuid);
        $attempts = ($user->failed_login_attempts ?? 0) + 1;

        $data = ['failed_login_attempts' => $attempts];

        if ($attempts >= MAX_LOGIN_ATTEMPTS) {
            $data['locked_until'] = future_datetime('+' . LOCK_DURATION_MINUTES . ' minutes');
        }

        $this->update($uuid, $data);

        return $attempts;
    }

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

    private function createUuid(): string
    {
        $data    = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // Version 4
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // Variant RFC 4122

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}