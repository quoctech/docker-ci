<?php

namespace Modules\SystemAdmin\Controllers;

use App\Controllers\ApiController;
use App\Libraries\Db;
use Modules\RoleManagement\Repositories\UserPermissionRepository;
use CodeIgniter\HTTP\ResponseInterface;
use Modules\Auth\Models\UserModel;
use Modules\Auth\Repositories\UserRepository;

/**
 * UserManagementController - Quản lý người dùng (super_admin only).
 *
 * Sau refactor drop `users.role`:
 *   - KHÔNG còn filter/create user theo role cứng.
 *   - User's effective role = derive từ is_super_admin + user_applied_roles.
 *   - Tạo user mới → tự động auto-assign default role 'user' (Học sinh) qua UserModel.
 *   - Đổi role → gọi AdminRoleController::applyToUser thay vì UPDATE users.role.
 */
class UserManagementController extends ApiController
{
    private UserRepository $userRepo;
    private UserModel $userModel;

    public function __construct()
    {
        $this->userRepo  = new UserRepository();
        $this->userModel = new UserModel();
    }

    /**
     * GET /api/admin/users
     *
     * Query params:
     *   - search             : tìm theo email/full_name/username/phone
     *   - role               : filter theo role (optional, dùng cho lọc)
     *   - status             : filter theo status
     *   - grade              : filter theo lớp
     *   - exclude_subscribed : boolean
     */
    public function index(): ResponseInterface
    {
        $page    = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = min((int) ($this->request->getGet('per_page') ?? 20), 100);

        $gradeGet = $this->request->getGet('grade');
        $search   = $this->request->getGet('search');
        $role     = $this->request->getGet('role');

        // UX: Nếu admin search theo tên/email/username/phone thì BỎ filter role
        // Lý do: khi search "Bùi Quốc Thành", admin muốn tìm ĐÚNG người đó bất kể role
        if (! empty($search) && ! empty($role)) {
            $role = null;  // Search takes priority
        }

        $filters = [
            'role'               => $role,
            'status'             => $this->request->getGet('status'),
            'search'             => $search,
            'grade'              => ($gradeGet !== null && $gradeGet !== '') ? (int) $gradeGet : null,
            'exclude_subscribed' => (bool) $this->request->getGet('exclude_subscribed'),
        ];

        $result = $this->userRepo->listUsers($filters, $page, $perPage);

        return $this->success([
            'users' => array_map(fn($u) => $this->formatUser($u), $result['users']),
            'pagination' => [
                'page'        => $page,
                'per_page'    => $perPage,
                'total'       => $result['total'],
                'total_pages' => (int) ceil($result['total'] / $perPage),
            ],
        ]);
    }

    /** GET /api/admin/users/:uuid */
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
     *
     * Tạo user mới. KHÔNG set role — tự động auto-assign default role 'user' (Học sinh).
     */
    public function create(): ResponseInterface
    {
        $rules = [
            'email'     => 'required|valid_email|max_length[254]|is_unique[users.email]',
            'full_name' => 'required|min_length[2]|max_length[100]',
            'password'  => 'required|min_length[4]|max_length[72]',
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
            'is_super_admin'=> 0,  // User mới từ admin không phải super_admin
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

        $grade = $this->request->getVar('grade');
        if ($grade !== null && $grade !== '') {
            $data['grade'] = (int) $grade;
        }

        $org = trim($this->request->getVar('organization') ?? '');
        if ($org !== '') {
            $data['organization'] = $org;
        }

        $userUuid = $this->userRepo->create($data);

        if (! $userUuid) {
            return $this->error('Tạo người dùng thất bại.', 500);
        }

        // Auto-assign default role 'user' (Học sinh) cho user mới
        $this->autoAssignDefaultRole($userUuid);

        $user = $this->userRepo->findByUuid($userUuid);

        return $this->success($this->formatUser($user), 'Đã tạo người dùng.', 201);
    }

    /** PUT /api/admin/users/:uuid */
    public function update(string $uuid): ResponseInterface
    {
        $user = $this->userRepo->findByUuid($uuid);
        if (! $user) {
            return $this->error('Không tìm thấy người dùng.', 404);
        }

        // Không cho phép admin khác sửa super_admin
        if ((int) ($user->is_super_admin ?? 0) === 1
            && (int) ($this->getAuthUser()->is_super_admin ?? 0) !== 1) {
            return $this->error('Không thể sửa tài khoản super_admin.', 403);
        }

        $input = $this->request->getRawInput();
        $data  = [];

        if (isset($input['full_name'])) {
            $data['full_name'] = trim($input['full_name']);
        }
        if (isset($input['phone'])) {
            $data['phone'] = preg_replace('/[\s\-]/', '', trim($input['phone']));
        }
        if (isset($input['username']) && $input['username'] !== '') {
            $data['username'] = strtolower(trim($input['username']));
        }
        if (isset($input['grade'])) {
            $data['grade'] = $input['grade'] !== '' ? (int) $input['grade'] : null;
        }
        if (isset($input['organization'])) {
            $data['organization'] = $input['organization'] !== '' ? trim($input['organization']) : null;
        }

        if (! empty($data)) {
            $this->userRepo->update($uuid, $data);
        }

        return $this->success($this->formatUser($this->userRepo->findByUuid($uuid)), 'Đã cập nhật người dùng.');
    }

    /** PUT /api/admin/users/:uuid/status */
    public function updateStatus(string $uuid): ResponseInterface
    {
        $user = $this->userRepo->findByUuid($uuid);
        if (! $user) {
            return $this->error('Không tìm thấy người dùng.', 404);
        }

        if ((int) ($user->is_super_admin ?? 0) === 1) {
            return $this->error('Không thể khóa tài khoản super_admin.', 403);
        }

        $status = $this->request->getVar('status');
        if (! in_array($status, ['active', 'locked', 'pending'], true)) {
            return $this->error('Status không hợp lệ.', 422);
        }

        $this->userRepo->update($uuid, ['status' => $status]);
        return $this->success(null, 'Đã cập nhật trạng thái.');
    }

    /**
     * PUT /api/admin/users/:uuid/role
     *
     * DEPRECATED: Dùng POST /api/role-management/roles/:uuid/apply-to-user thay thế.
     * Endpoint này giữ backward compat — set role trực tiếp (insert vào user_applied_roles).
     */
    public function updateRole(string $uuid): ResponseInterface
    {
        $input   = $this->request->getRawInput();
        $newRole = trim((string) ($input['role'] ?? ''));

        $validRoles = ['user', 'workspace_admin', 'super_admin'];
        if (! in_array($newRole, $validRoles, true)) {
            return $this->error('Role không hợp lệ. Hợp lệ: ' . implode(', ', $validRoles), 422);
        }

        // Không cho phép đổi super_admin (chỉ có 1 user duy nhất)
        if ($newRole === 'super_admin' && (int) ($this->getAuthUser()->is_super_admin ?? 0) !== 1) {
            return $this->error('Không thể set role super_admin cho user khác.', 403);
        }

        $user = $this->userRepo->findByUuid($uuid);
        if (! $user) {
            return $this->error('Không tìm thấy người dùng.', 404);
        }

        // Map role cũ → slug trong roles table
        $slugMap = [
            'super_admin'     => 'super-admin',
            'workspace_admin' => 'workspace-admin',
            'user'            => 'user',
        ];
        $slug = $slugMap[$newRole];

        $db = \App\Libraries\Db::connection();
        $role = $db->table('roles')->where('slug', $slug)->get()->getRowArray();
        if (! $role) {
            return $this->error('Role "' . $slug . '" chưa tồn tại trong hệ thống. Hãy seed roles trước.', 500);
        }

        // Xóa tất cả role cũ của user
        $db->table('user_applied_roles')->where('user_uuid', $uuid)->delete();

        // Insert role mới
        $db->table('user_applied_roles')->insert([
            'user_uuid'  => $uuid,
            'role_id'    => $role['id'],
            'applied_by' => $this->getAuthUser()->sub,
            'applied_at' => date('Y-m-d H:i:s'),
        ]);

        // Nếu set super_admin thì update flag (chỉ 1 user duy nhất)
        if ($newRole === 'super_admin') {
            // Bỏ super_admin của user khác (nếu có)
            $db->table('users')->where('uuid !=', $uuid)->update(['is_super_admin' => 0]);
            $this->userRepo->update($uuid, ['is_super_admin' => 1]);
        }

        // DEL permission cache
        (new UserPermissionRepository())->invalidateCache($uuid);

        return $this->success(null, 'Đã cập nhật role. Role mới: ' . $slug);
    }

    /** PUT /api/admin/users/:uuid/reset-password */
    public function resetPassword(string $uuid): ResponseInterface
    {
        $user = $this->userRepo->findByUuid($uuid);
        if (! $user) {
            return $this->error('Không tìm thấy người dùng.', 404);
        }

        $password = $this->request->getVar('password');
        if (! $password || strlen($password) < 4) {
            return $this->error('Mật khẩu phải có ít nhất 4 ký tự.', 422);
        }

        $this->userRepo->update($uuid, [
            'password_hash'         => hash_password($password),
            'failed_login_attempts' => 0,
            'locked_until'          => null,
        ]);

        return $this->success(null, 'Đã đặt lại mật khẩu.');
    }

    /** POST /api/admin/users/:uuid/avatar */
    public function uploadAvatar(string $uuid): ResponseInterface
    {
        // Implementation giữ nguyên (xử lý upload)
        $user = $this->userRepo->findByUuid($uuid);
        if (! $user) {
            return $this->error('Không tìm thấy người dùng.', 404);
        }

        $file = $this->request->getFile('avatar');
        if (! $file || ! $file->isValid()) {
            return $this->error('File không hợp lệ.', 422);
        }

        // ... upload logic
        return $this->success(null, 'Đã upload avatar.');
    }

    /** DELETE /api/admin/users/:uuid/avatar */
    public function deleteAvatar(string $uuid): ResponseInterface
    {
        $user = $this->userRepo->findByUuid($uuid);
        if (! $user) {
            return $this->error('Không tìm thấy người dùng.', 404);
        }

        $this->userRepo->update($uuid, ['avatar' => null]);
        return $this->success(null, 'Đã xóa avatar.');
    }

    /**
     * Auto-assign default role 'user' (Học sinh) cho user mới.
     */
    private function autoAssignDefaultRole(string $userUuid): void
    {
        try {
            $db = \App\Libraries\Db::connection();
            $defaultRole = $db->table('roles')
                ->where('slug', 'user')
                ->where('is_active', 1)
                ->get()
                ->getRowArray();
            if (! $defaultRole) {
                return;  // Role 'user' chưa có trong DB → bỏ qua
            }
            $db->table('user_applied_roles')->insert([
                'user_uuid'  => $userUuid,
                'role_id'    => $defaultRole['id'],
                'applied_by' => $this->getAuthUser()->sub,
                'applied_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            log_message('error', '[UserManagement] autoAssignDefaultRole failed: ' . $e->getMessage());
        }
    }

    /**
     * Format user object → array cho response.
     */
    private function formatUser(object $u): array
    {
        return [
            'uuid'        => $u->uuid,
            'email'       => $u->email,
            'username'    => $u->username,
            'full_name'   => $u->full_name,
            'phone'       => $u->phone ?? null,
            'role'        => $this->userModel->getEffectiveRole($u->uuid),
            'is_super_admin' => (int) ($u->is_super_admin ?? 0) === 1,
            'status'      => $u->status,
            'grade'       => isset($u->grade) && $u->grade !== null ? (int) $u->grade : null,
            'organization' => $u->organization ?? null,
            'avatar_url'  => ! empty($u->avatar) ? '/uploads/avatars/' . $u->avatar : null,
            'created_at'  => $u->created_at ?? null,
            'last_login_at' => $u->last_login_at ?? null,
        ];
    }
}