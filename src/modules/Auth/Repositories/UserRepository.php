<?php

namespace Modules\Auth\Repositories;

use Modules\Auth\Models\UserModel;

/**
 * UserRepository - Tầng truy xuất dữ liệu cho bảng users.
 *
 * Tách biệt logic database khỏi Controller.
 * Controller chỉ xử lý request/response, Repository xử lý query.
 */
class UserRepository
{
    private UserModel $model;

    public function __construct()
    {
        $this->model = new UserModel();
    }

    // =========================================================================
    // LOOKUP
    // =========================================================================

    public function findById(int $id): ?object
    {
        return $this->model->find($id);
    }

    public function findActiveById(int $id): ?object
    {
        return $this->model->findActiveById($id);
    }

    public function findByEmail(string $email): ?object
    {
        return $this->model->findByEmail($email);
    }

    public function findByUsername(string $username): ?object
    {
        return $this->model->findByUsername($username);
    }

    public function findByCredentialIdentifier(string $identifier): ?object
    {
        return $this->model->findByCredentialIdentifier($identifier);
    }

    // =========================================================================
    // CRUD
    // =========================================================================

    /**
     * Tạo user mới.
     *
     * @param array $data Dữ liệu user (email, password_hash, full_name, role, status, ...)
     * @return int|false User ID hoặc false nếu thất bại
     */
    public function create(array $data): int|false
    {
        return $this->model->insert($data);
    }

    /**
     * Cập nhật user (skip validation — dùng khi admin update).
     *
     * @param int   $id   User ID
     * @param array $data Fields cần update
     */
    public function update(int $id, array $data): void
    {
        $this->model->skipValidation(true)->update($id, $data);
    }

    // =========================================================================
    // LOGIN TRACKING
    // =========================================================================

    public function recordLogin(int $userId, string $ip): void
    {
        $this->model->recordLogin($userId, $ip);
    }

    public function incrementFailedAttempts(int $userId): int
    {
        return $this->model->incrementFailedAttempts($userId);
    }

    public function isLocked(object $user): bool
    {
        return $this->model->isLocked($user);
    }

    // =========================================================================
    // LISTING (Admin)
    // =========================================================================

    /**
     * Danh sách users với filter, search, pagination.
     *
     * @param array $filters ['role' => ..., 'status' => ..., 'search' => ...]
     * @param int   $page    Trang hiện tại
     * @param int   $perPage Số record / trang
     * @return array{users: array, total: int}
     */
    public function listUsers(array $filters, int $page, int $perPage): array
    {
        $builder = $this->model->builder();
        $builder->where('deleted_at', null);

        if (! empty($filters['role'])) {
            $builder->where('role', $filters['role']);
        }

        if (! empty($filters['status'])) {
            $builder->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $builder->groupStart()
                    ->like('email', $filters['search'])
                    ->orLike('full_name', $filters['search'])
                    ->orLike('username', $filters['search'])
                    ->orLike('phone', $filters['search'])
                    ->groupEnd();
        }

        $total = $builder->countAllResults(false);
        $users = $builder->orderBy('created_at', 'DESC')
                         ->limit($perPage, ($page - 1) * $perPage)
                         ->get()
                         ->getResult();

        return ['users' => $users, 'total' => $total];
    }
}
