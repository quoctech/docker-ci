<?php

namespace Modules\SystemAdmin\Controllers;

use App\Controllers\ApiController;
use App\Libraries\RedisService;
use CodeIgniter\HTTP\ResponseInterface;
use Modules\Auth\Repositories\UserRepository;
use Modules\Auth\Repositories\RefreshTokenRepository;

/**
 * UserManagementController - Quản lý người dùng (Super Admin only).
 *
 * Xử lý request/response. Logic database ủy thác cho Repository.
 */
class UserManagementController extends ApiController
{
    private UserRepository $userRepo;
    private RefreshTokenRepository $tokenRepo;

    public function __construct()
    {
        $this->userRepo  = new UserRepository();
        $this->tokenRepo = new RefreshTokenRepository();
    }

    /**
     * GET /api/admin/users
     */
    public function index(): ResponseInterface
    {
        $page    = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = min((int) ($this->request->getGet('per_page') ?? 20), 100);

        $filters = [
            'role'   => $this->request->getGet('role'),
            'status' => $this->request->getGet('status'),
            'search' => $this->request->getGet('search'),
        ];

        $result = $this->userRepo->listUsers($filters, $page, $perPage);

        return $this->success([
            'users'      => array_map(fn($u) => $this->formatUser($u), $result['users']),
            'pagination' => [
                'page'        => $page,
                'per_page'    => $perPage,
                'total'       => $result['total'],
                'total_pages' => (int) ceil($result['total'] / $perPage),
            ],
        ]);
    }

    /**
     * GET /api/admin/users/(:segment)
     */
    public function show(string $uuid): ResponseInterface
    {
        $user = $this->userRepo->findByUuid($uuid);

        if (! $user) {
            return $this->error('Không tìm thấy người dùng.', 404);
        }

        return $this->success($this->formatUser($user));
    }

    /**
     * POST /api/admin/users
     */
    public function create(): ResponseInterface
    {
        $rules = [
            'email'     => 'required|valid_email|max_length[254]|is_unique[users.email]',
            'full_name' => 'required|min_length[2]|max_length[100]',
            'password'  => 'required|min_length[4]|max_length[72]',
            'role'      => 'required|in_list[super_admin,workspace_admin,user]',
            'username'  => 'permit_empty|alpha_numeric|min_length[3]|max_length[30]|is_unique[users.username]',
            'phone'     => 'permit_empty|max_length[20]',
        ];

        if (! $this->validate($rules)) {
            return $this->error('Dữ liệu không hợp lệ.', 422, $this->validator->getErrors());
        }

        $data = [
            'email'         => strtolower(trim($this->request->getVar('email'))),
            'full_name'     => trim($this->request->getVar('full_name')),
            'password_hash' => hash_password($this->request->getVar('password')),
            'role'          => $this->request->getVar('role'),
            'status'        => STATUS_ACTIVE,
        ];

        $username = $this->request->getVar('username');
        if (! empty($username)) {
            $data['username'] = strtolower(trim($username));
        }

        $phone = $this->request->getVar('phone');
        if (! empty($phone)) {
            $data['phone'] = preg_replace('/[\s\-]/', '', trim($phone));
        }

        $userUuid = $this->userRepo->create($data);

        if (! $userUuid) {
            return $this->error('Tạo người dùng thất bại.', 500);
        }

        $user = $this->userRepo->findByUuid($userUuid);

        return $this->success($this->formatUser($user), 'Đã tạo người dùng.', 201);
    }

    /**
     * PUT /api/admin/users/(:segment)
     */
    public function update(string $uuid): ResponseInterface
    {
        $user = $this->userRepo->findByUuid($uuid);

        if (! $user) {
            return $this->error('Không tìm thấy người dùng.', 404);
        }

        $input = $this->request->getRawInput();
        $data  = [];

        if (isset($input['full_name']) && ! empty(trim($input['full_name']))) {
            $data['full_name'] = trim($input['full_name']);
        }

        if (isset($input['phone'])) {
            $raw = trim($input['phone']);
            $data['phone'] = $raw !== '' ? preg_replace('/[\s\-]/', '', $raw) : null;
        }

        if (isset($input['username'])) {
            $username = strtolower(trim($input['username']));
            if (! empty($username)) {
                $existing = $this->userRepo->findByUsername($username);
                if ($existing && $existing->uuid !== $uuid) {
                    return $this->error('Username đã tồn tại.', 422);
                }
            }
            $data['username'] = $username ?: null;
        }

        if (empty($data)) {
            return $this->error('Không có dữ liệu cập nhật.', 400);
        }

        $this->userRepo->update($uuid, $data);
        $user = $this->userRepo->findByUuid($uuid);

        return $this->success($this->formatUser($user), 'Đã cập nhật người dùng.');
    }

    /**
     * PUT /api/admin/users/(:segment)/status
     */
    public function updateStatus(string $uuid): ResponseInterface
    {
        $user = $this->userRepo->findByUuid($uuid);

        if (! $user) {
            return $this->error('Không tìm thấy người dùng.', 404);
        }

        if ($user->role === ROLE_SUPER_ADMIN && $this->getAuthUser()->role !== ROLE_SUPER_ADMIN) {
            return $this->error('Không thể thao tác trên Super Admin.', 403);
        }

        $input     = $this->request->getRawInput();
        $newStatus = $input['status'] ?? '';

        if (! in_array($newStatus, [STATUS_ACTIVE, STATUS_LOCKED, STATUS_PENDING], true)) {
            return $this->error('Trạng thái không hợp lệ.', 422);
        }

        $this->userRepo->update($uuid, ['status' => $newStatus]);

        if ($newStatus === STATUS_LOCKED) {
            RedisService::revokeAllSessions($uuid);
            $this->tokenRepo->revokeAllForUser($uuid);
        }

        return $this->success(['uuid' => $uuid, 'status' => $newStatus], 'Đã cập nhật trạng thái.');
    }

    /**
     * PUT /api/admin/users/(:segment)/role
     */
    public function updateRole(string $uuid): ResponseInterface
    {
        $user = $this->userRepo->findByUuid($uuid);

        if (! $user) {
            return $this->error('Không tìm thấy người dùng.', 404);
        }

        $input   = $this->request->getRawInput();
        $newRole = $input['role'] ?? '';

        if (! in_array($newRole, [ROLE_SUPER_ADMIN, ROLE_WORKSPACE_ADMIN, ROLE_USER], true)) {
            return $this->error('Quyền không hợp lệ.', 422);
        }

        if ($newRole === ROLE_SUPER_ADMIN && $this->getAuthUser()->role !== ROLE_SUPER_ADMIN) {
            return $this->error('Chỉ Super Admin mới có thể phân quyền Super Admin.', 403);
        }

        $this->userRepo->update($uuid, ['role' => $newRole]);

        RedisService::revokeAllSessions($uuid);
        $this->tokenRepo->revokeAllForUser($uuid);

        return $this->success(['uuid' => $uuid, 'role' => $newRole], 'Đã cập nhật quyền.');
    }

    /**
     * PUT /api/admin/users/(:segment)/reset-password
     */
    public function resetPassword(string $uuid): ResponseInterface
    {
        $user = $this->userRepo->findByUuid($uuid);

        if (! $user) {
            return $this->error('Không tìm thấy người dùng.', 404);
        }

        $input       = $this->request->getRawInput();
        $newPassword = $input['new_password'] ?? '';

        if (empty($newPassword) || mb_strlen($newPassword) < 4 || mb_strlen($newPassword) > 72) {
            return $this->error('Mật khẩu phải từ 4 đến 72 ký tự.', 422);
        }

        $this->userRepo->update($uuid, [
            'password_hash' => hash_password($newPassword),
        ]);

        RedisService::revokeAllSessions($uuid);
        $this->tokenRepo->revokeAllForUser($uuid);

        return $this->success(null, 'Đã đặt lại mật khẩu. Người dùng cần đăng nhập lại.');
    }

    /**
     * POST /api/admin/users/(:segment)/avatar
     */
    public function uploadAvatar(string $uuid): ResponseInterface
    {
        $user = $this->userRepo->findByUuid($uuid);

        if (! $user) {
            return $this->error('Không tìm thấy người dùng.', 404);
        }

        $file = $this->request->getFile('avatar');

        if (! $file || ! $file->isValid()) {
            return $this->error('File không hợp lệ.', 422);
        }

        $rules = [
            'avatar' => 'uploaded[avatar]|max_size[avatar,2048]|mime_in[avatar,image/png,image/jpeg,image/webp]|is_image[avatar]',
        ];

        if (! $this->validate($rules)) {
            return $this->error('File không hợp lệ.', 422, $this->validator->getErrors());
        }

        // Xóa avatar cũ
        if (! empty($user->avatar)) {
            $oldPath = WRITEPATH . 'uploads/avatars/' . $user->avatar;
            if (is_file($oldPath)) {
                unlink($oldPath);
            }
        }

        $newName = $file->getRandomName();
        $file->move(WRITEPATH . 'uploads/avatars/', $newName);

        $this->userRepo->update($uuid, ['avatar' => $newName]);

        return $this->success([
            'avatar'     => $newName,
            'avatar_url' => '/uploads/avatars/' . $newName,
        ], 'Đã cập nhật avatar.');
    }

    /**
     * DELETE /api/admin/users/(:segment)/avatar
     */
    public function deleteAvatar(string $uuid): ResponseInterface
    {
        $user = $this->userRepo->findByUuid($uuid);

        if (! $user) {
            return $this->error('Không tìm thấy người dùng.', 404);
        }

        if (! empty($user->avatar)) {
            $path = WRITEPATH . 'uploads/avatars/' . $user->avatar;
            if (is_file($path)) {
                unlink($path);
            }
            $this->userRepo->update($uuid, ['avatar' => null]);
        }

        return $this->success(null, 'Đã xóa avatar.');
    }

    private function formatUser(object $u): array
    {
        return [
            'uuid'       => $u->uuid,
            'email'      => $u->email,
            'username'   => $u->username,
            'phone'      => $u->phone,
            'full_name'  => $u->full_name,
            'avatar'     => $u->avatar,
            'avatar_url' => $u->avatar ? '/uploads/avatars/' . $u->avatar : null,
            'role'       => $u->role,
            'status'     => $u->status,
            'created_at' => $u->created_at,
            'last_login' => $u->last_login_at ?? null,
        ];
    }
}
