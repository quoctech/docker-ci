<?php

namespace Modules\RoleManagement\Controllers;

use App\Controllers\ApiController;
use App\Libraries\SystemLogger;
use CodeIgniter\HTTP\ResponseInterface;
use Modules\RoleManagement\Repositories\RoleAuditLogRepository;
use Modules\Auth\Models\UserModel;
use Modules\Auth\Repositories\UserRepository;
use Modules\RoleManagement\Repositories\RoleRepository;
use Modules\RoleManagement\Repositories\UserPermissionRepository;

/**
 * AdminRoleController - Quản lý role và phân quyền module theo role.
 *
 * Triết lý (Phương án A — Single Source of Truth):
 *   Quyền của user = UNION quyền của tất cả role user được gán.
 *   KHÔNG copy quyền sang bảng user_module_permissions.
 *
 * Cache strategy:
 *   - Permission check mỗi request qua UserPermissionRepository (có cache Redis).
 *   - Khi permission thay đổi → CHỈ DEL cache → user nhận perm mới ở request kế tiếp.
 *   - KHÔNG force logout (UX tốt hơn, tránh user bị đăng xuất khi admin cấp quyền).
 *
 * Soft-delete + tạo lại role:
 *   - Dùng `RoleRepository::createOrRevive()` — revive row inactive thay vì INSERT mới.
 *   - Giữ nguyên UUID → không phá reference trong role_module_permissions, user_applied_roles, audit log.
 *
 * Apply role cho user:
 *   - CHẤP NHẬN MỌI USER (super_admin, workspace_admin, user).
 *   - Học sinh cũng có thể được áp dụng role (dùng cho permission đặc biệt).
 *   - Endpoint search users riêng (`searchUsers`) để chọn user.
 */
class AdminRoleController extends ApiController
{
    private RoleRepository          $repo;
    private UserPermissionRepository $userPermRepo;
    private RoleAuditLogRepository  $auditRepo;
    private UserModel                $userModel;

    /** Module lõi không được cấp quyền qua role (chỉ super_admin mới có). */
    private const NON_GRANTABLE = ['auth', 'system-admin', 'system-log'];

    public function __construct()
    {
        $this->repo        = new RoleRepository();
        $this->userPermRepo = new UserPermissionRepository();
        $this->auditRepo   = new RoleAuditLogRepository();
        $this->userModel   = new UserModel();
    }

    /** GET /api/role-management/roles */
    public function index(): ResponseInterface
    {
        return $this->success($this->repo->list());
    }

    /**
     * POST /api/role-management/roles
     *
     * Tạo role mới, hoặc REVIVE role đã soft-delete (cùng slug).
     */
    public function create(): ResponseInterface
    {
        $rules = ['name' => 'required|max_length[100]'];
        if (! $this->validate($rules)) {
            return $this->error('Dữ liệu không hợp lệ.', 422, $this->validator->getErrors());
        }

        $name = trim($this->request->getPost('name'));
        $slug = $this->toSlug($name);

        try {
            $result = $this->repo->createOrRevive([
                'name'        => $name,
                'slug'        => $slug,
                'description' => $this->request->getPost('description'),
            ]);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 409);
        }

        $role    = $result['role'];
        $revived = $result['revived'];

        SystemLogger::info(
            ($revived ? 'Khôi phục vai trò: ' : 'Tạo vai trò: ') . $role->name,
            ['role_uuid' => $role->uuid, 'revived' => $revived]
        );

        $this->auditRepo->log(
            action: $revived ? 'role_revived' : 'role_created',
            roleUuid: $role->uuid,
            roleId: $role->id,
            performedBy: $this->getAuthUser()->sub,
            after: ['name' => $role->name, 'slug' => $role->slug, 'revived' => $revived]
        );

        $msg = $revived
            ? 'Đã khôi phục vai trò "' . $role->name . '" (UUID giữ nguyên).'
            : 'Tạo vai trò thành công.';

        return $this->success($role, $msg, 201);
    }

    /** GET /api/role-management/roles/:uuid */
    public function show(string $uuid): ResponseInterface
    {
        $role = $this->repo->findByUuid($uuid);
        if (! $role) {
            return $this->error('Không tìm thấy vai trò.', 404);
        }

        return $this->success([
            'role'        => $role,
            'permissions' => $this->repo->getPermissions($role->id),
        ]);
    }

    /** PUT /api/role-management/roles/:uuid */
    public function update(string $uuid): ResponseInterface
    {
        $role = $this->repo->findByUuid($uuid);
        if (! $role) {
            return $this->error('Không tìm thấy vai trò.', 404);
        }

        $input = $this->request->getRawInput();
        $name  = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            return $this->error('Tên vai trò không được để trống.', 422);
        }

        $before = ['name' => $role->name, 'description' => $role->description];

        $data = ['name' => $name];
        if (array_key_exists('description', $input)) {
            $data['description'] = $input['description'] !== '' ? $input['description'] : null;
        }

        $this->repo->update($role->id, $data);

        SystemLogger::info('Cập nhật vai trò: ' . $role->name, ['role_uuid' => $uuid]);
        $this->auditRepo->log(
            action: 'role_updated',
            roleUuid: $uuid,
            roleId: $role->id,
            performedBy: $this->getAuthUser()->sub,
            before: $before,
            after: $data
        );

        return $this->success($this->repo->findByUuid($uuid), 'Cập nhật thành công.');
    }

    /**
     * DELETE /api/role-management/roles/:uuid
     *
     * Chỉ set is_active=0. Slug giữ nguyên → có thể tạo lại (sẽ revive row cũ).
     */
    public function delete(string $uuid): ResponseInterface
    {
        $role = $this->repo->findByUuid($uuid);
        if (! $role) {
            return $this->error('Không tìm thấy vai trò.', 404);
        }

        $affectedUsers = $this->repo->getUsersWithRole($role->id);
        $this->userPermRepo->invalidateCacheBatch($affectedUsers);

        $this->repo->deactivate($role->id);

        SystemLogger::info('Xóa vai trò: ' . $role->name, ['role_uuid' => $uuid]);
        $this->auditRepo->log(
            action: 'role_deleted',
            roleUuid: $uuid,
            roleId: $role->id,
            performedBy: $this->getAuthUser()->sub,
            before: ['name' => $role->name, 'slug' => $role->slug],
            after: ['is_active' => 0]
        );

        return $this->success(null, 'Đã xóa vai trò.');
    }

    /** GET /api/role-management/roles/:uuid/modules */
    public function getModules(string $uuid): ResponseInterface
    {
        $role = $this->repo->findByUuid($uuid);
        if (! $role) {
            return $this->error('Không tìm thấy vai trò.', 404);
        }

        $db         = \App\Libraries\Db::connection();
        $allModules = $db->table('modules')
            ->select('slug, name, is_enabled')
            ->where('is_enabled', 1)
            ->whereNotIn('slug', self::NON_GRANTABLE)
            ->orderBy('sort_order', 'ASC')
            ->get()
            ->getResultObject();

        $permsMap = [];
        foreach ($this->repo->getPermissions($role->id) as $p) {
            $permsMap[$p->module_slug] = $p;
        }

        $result = [];
        foreach ($allModules as $m) {
            $p        = $permsMap[$m->slug] ?? null;
            $result[] = [
                'slug'       => $m->slug,
                'name'       => $m->name,
                'enabled'    => (bool) $m->is_enabled,
                'can_read'   => (bool) ($p->can_read   ?? false),
                'can_write'  => (bool) ($p->can_write  ?? false),
                'can_edit'   => (bool) ($p->can_edit   ?? false),
                'can_delete' => (bool) ($p->can_delete ?? false),
            ];
        }

        return $this->success($result);
    }

    /**
     * PUT /api/role-management/roles/:uuid/modules
     *
     * Cập nhật permission cho role. KHÔNG force logout user — chỉ DEL cache.
     */
    public function setModules(string $uuid): ResponseInterface
    {
        $auth = $this->getAuthUser();
        $role = $this->repo->findByUuid($uuid);
        if (! $role) {
            return $this->error('Không tìm thấy vai trò.', 404);
        }

        $body    = $this->request->getJSON(true) ?? [];
        $modules = $body['modules'] ?? [];
        if (! is_array($modules)) {
            return $this->error('Trường modules phải là mảng.', 422);
        }

        $db         = \App\Libraries\Db::connection();
        $validSlugs = array_column(
            $db->table('modules')
                ->select('slug')
                ->where('is_enabled', 1)
                ->whereNotIn('slug', self::NON_GRANTABLE)
                ->get()
                ->getResultArray(),
            'slug'
        );

        $permissions = [];
        foreach ($modules as $m) {
            $slug = $m['slug'] ?? '';
            if (! in_array($slug, $validSlugs, true) || empty($m['can_read'])) {
                continue;
            }

            $permissions[] = [
                'slug'       => $slug,
                'can_read'   => 1,
                'can_write'  => empty($m['can_write'])  ? 0 : 1,
                'can_edit'   => empty($m['can_edit'])   ? 0 : 1,
                'can_delete' => empty($m['can_delete']) ? 0 : 1,
            ];
        }

        $before = $this->repo->getPermissions($role->id);

        try {
            $affectedUsers = $this->repo->setPermissions($role->id, $permissions);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 500);
        }

        $this->userPermRepo->invalidateCacheBatch($affectedUsers);

        $this->auditRepo->log(
            action: 'role_perm_changed',
            roleUuid: $uuid,
            roleId: $role->id,
            performedBy: $auth->sub,
            before: $this->normalizePermsForLog($before),
            after:  $this->normalizePermsForLog(array_map(
                fn($p) => (object) [
                    'module_slug' => $p['slug'],
                    'can_read'    => $p['can_read'],
                    'can_write'   => $p['can_write'],
                    'can_edit'    => $p['can_edit'],
                    'can_delete'  => $p['can_delete'],
                ],
                $permissions
            )),
        );

        $synced = count($affectedUsers);
        $msg = $synced > 0
            ? "Đã cập nhật phân quyền vai trò. Áp dụng cho {$synced} người dùng ở request kế tiếp."
            : 'Đã cập nhật phân quyền vai trò.';

        return $this->success(null, $msg);
    }

    /**
     * GET /api/role-management/users-search
     *
     * Tìm user để áp dụng role. CHẤP NHẬN MỌI ROLE (super_admin, workspace_admin, user).
     *
     * Query params:
     *   - search: tìm theo email/full_name/username/phone
     *   - role:   filter theo role (optional). Nếu không truyền → trả về tất cả.
     *   - exclude_uuids: comma-separated list UUID cần loại (đã được áp role này rồi)
     *
     * @return ResponseInterface
     */
    public function searchUsers(): ResponseInterface
    {
        $search        = trim((string) ($this->request->getGet('search') ?? ''));
        $role          = (string) ($this->request->getGet('role') ?? '');
        $excludeUuids  = (string) ($this->request->getGet('exclude_uuids') ?? '');
        $page          = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage       = min(50, max(10, (int) ($this->request->getGet('per_page') ?? 20)));

        $db = \App\Libraries\Db::connection();
        $builder = $db->table('users')
            ->select('uuid, username, full_name, email, phone, role, status, grade, organization, created_at', false);
        // KHÔNG filter deleted_at — searchUsers dùng cho cả admin thao tác

        // Filter theo role (optional) — KHÔNG ép buộc phải truyền
        if ($role !== '') {
            $builder->where('role', $role);
        }

        // Filter theo status (optional) — KHÔNG default status='active' để search thấy cả học sinh pending/locked
        $status = $this->request->getGet('status');
        if ($status !== null && $status !== '') {
            $builder->where('status', $status);
        }

        // Search theo email/full_name/username/phone
        if ($search !== '') {
            $builder->groupStart()
                ->like('email', $search)
                ->orLike('full_name', $search)
                ->orLike('username', $search)
                ->orLike('phone', $search)
            ->groupEnd();
        }

        // Exclude các UUID đã có role này
        if ($excludeUuids !== '') {
            $exclude = array_filter(array_map('trim', explode(',', $excludeUuids)));
            if (! empty($exclude)) {
                $builder->whereNotIn('uuid', $exclude);
            }
        }

        $total = $builder->countAllResults(false);
        $users = $builder->orderBy('full_name', 'ASC')
                         ->limit($perPage, ($page - 1) * $perPage)
                         ->get()
                         ->getResultArray();

        // DEBUG: log query SQL để debug nếu vẫn không tìm thấy user
        log_message('debug', '[searchUsers] search=' . var_export($search, true)
            . ' role=' . var_export($role, true)
            . ' status=' . var_export($status ?? null, true)
            . ' total=' . $total);

        return $this->success([
            'users' => array_map(fn($u) => [
                'uuid'        => $u['uuid'],
                'email'       => $u['email'],
                'username'    => $u['username'],
                'full_name'   => $u['full_name'],
                'phone'       => $u['phone'],
                'role'        => $u['role'],
                'role_label'  => match ($u['role']) {
                    'super_admin'     => 'Super Admin',
                    'workspace_admin' => 'Giáo viên',
                    default           => 'Học sinh',
                },
                'status'      => $u['status'],
                'grade'       => isset($u['grade']) && $u['grade'] !== null ? (int) $u['grade'] : null,
                'organization' => $u['organization'] ?? null,
            ], $users),
            'pagination' => [
                'page'        => $page,
                'per_page'    => $perPage,
                'total'       => $total,
                'total_pages' => (int) ceil($total / $perPage),
            ],
        ]);
    }

    /**
     * POST /api/role-management/roles/:uuid/apply-to-user
     *
     * Áp dụng role cho user. KHÔNG force logout — chỉ DEL cache.
     * User nhận permission mới ở request kế tiếp.
     *
     * CHẤP NHẬN MỌI USER (super_admin, workspace_admin, user/học sinh).
     * Multi-role: 1 user có thể được gán nhiều role, quyền = UNION.
     */
    public function applyToUser(string $uuid): ResponseInterface
    {
        $auth = $this->getAuthUser();
        $role = $this->repo->findByUuid($uuid);
        if (! $role) {
            return $this->error('Không tìm thấy vai trò.', 404);
        }

        $body     = $this->request->getJSON(true) ?? [];
        $userUuid = trim((string) ($body['user_uuid'] ?? $this->request->getPost('user_uuid') ?? ''));
        if (! $userUuid) {
            return $this->error('Thiếu user_uuid.', 422);
        }

        $db = \App\Libraries\Db::connection();

        // Kiểm tra user tồn tại (CHẤP NHẬN MỌI ROLE — không filter theo role)
        $user = $db->table('users')
            ->select('uuid, status, full_name')
            ->where('uuid', $userUuid)
            ->where('deleted_at', null)
            ->get()
            ->getRowArray();

        if (! $user) {
            return $this->error('Không tìm thấy người dùng.', 404);
        }

        if (($user['status'] ?? '') !== 'active') {
            return $this->error('Người dùng đang bị khóa, không thể áp dụng vai trò.', 422);
        }

        // Kiểm tra xem user đã có role này chưa (multi-role nên KHÔNG chặn, nhưng cảnh báo)
        $alreadyApplied = $db->table('user_applied_roles')
            ->where('user_uuid', $userUuid)
            ->where('role_id', $role->id)
            ->countAllResults();

        // INSERT user_applied_roles (không cần check trùng — multi-role)
        $db->table('user_applied_roles')->insert([
            'user_uuid'  => $userUuid,
            'role_id'    => $role->id,
            'applied_by' => $auth->sub,
            'applied_at' => date('Y-m-d H:i:s'),
        ]);

        // CHỈ xóa cache permission — user KHÔNG bị logout
        $this->userPermRepo->invalidateCache($userUuid);

        SystemLogger::info('Áp dụng vai trò "' . $role->name . '" cho user: ' . $userUuid, [
            'role_uuid' => $uuid,
            'user_uuid' => $userUuid,
            'user_role' => $this->userModel->getEffectiveRole($userUuid),
            'reapplied' => $alreadyApplied > 0,
        ]);

        $this->auditRepo->log(
            action: $alreadyApplied > 0 ? 'role_reapplied' : 'role_applied',
            roleUuid: $uuid,
            roleId: $role->id,
            userUuid: $userUuid,
            performedBy: $auth->sub,
            after: [
                'user_uuid' => $userUuid,
                'user_role' => $this->userModel->getEffectiveRole($userUuid),
                'user_name' => $user['full_name'],
                'role_name' => $role->name,
            ]
        );

        $msg = $alreadyApplied > 0
            ? 'Vai trò "' . $role->name . '" đã được áp dụng trước đó cho "' . $user['full_name'] . '". Đã thêm lần nữa (multi-role).'
            : 'Đã áp dụng vai trò "' . $role->name . '" cho "' . $user['full_name'] . '". User sẽ có quyền mới ở request kế tiếp.';

        return $this->success(
            null,
            $msg
        );
    }

    /**
     * DELETE /api/role-management/user-applied-roles
     *
     * Bỏ áp dụng role cho user. KHÔNG giới hạn role của user.
     *
     * Body: { user_uuid, role_uuid }
     */
    public function unapplyRole(): ResponseInterface
    {
        $auth = $this->getAuthUser();

        $body = $this->request->getJSON(true) ?? [];
        $userUuid = trim((string) ($body['user_uuid'] ?? $this->request->getPost('user_uuid') ?? ''));
        $roleUuid = trim((string) ($body['role_uuid'] ?? $this->request->getPost('role_uuid') ?? ''));

        if (! $userUuid || ! $roleUuid) {
            return $this->error('Thiếu user_uuid hoặc role_uuid.', 422);
        }

        $role = $this->repo->findByUuid($roleUuid);
        if (! $role) {
            return $this->error('Không tìm thấy vai trò.', 404);
        }

        $db = \App\Libraries\Db::connection();
        $deleted = $db->table('user_applied_roles')
            ->where('user_uuid', $userUuid)
            ->where('role_id', $role->id)
            ->delete();

        if ($deleted === false) {
            return $this->error('Không thể bỏ áp dụng vai trò.', 500);
        }

        if ($db->affectedRows() === 0) {
            return $this->error('Người dùng chưa được áp dụng vai trò này.', 404);
        }

        $this->userPermRepo->invalidateCache($userUuid);

        SystemLogger::info('Bỏ áp dụng vai trò "' . $role->name . '" cho user: ' . $userUuid, [
            'role_uuid' => $roleUuid,
            'user_uuid' => $userUuid,
        ]);

        $this->auditRepo->log(
            action: 'role_unapplied',
            roleUuid: $roleUuid,
            roleId: $role->id,
            userUuid: $userUuid,
            performedBy: $auth->sub,
            before: ['user_uuid' => $userUuid, 'role_name' => $role->name]
        );

        return $this->success(null, 'Đã bỏ áp dụng vai trò. Người dùng sẽ mất quyền tương ứng ở request kế tiếp.');
    }

    /**
     * Chuyển mảng permission objects thành format gọn cho audit log.
     */
    private function normalizePermsForLog(array $perms): array
    {
        $out = [];
        foreach ($perms as $p) {
            $out[] = [
                'slug'      => $p->module_slug,
                'can_read'   => (int) $p->can_read,
                'can_write'  => (int) $p->can_write,
                'can_edit'   => (int) $p->can_edit,
                'can_delete' => (int) $p->can_delete,
            ];
        }
        return $out;
    }

    private function toSlug(string $name): string
    {
        $from = ['à','á','ạ','ả','ã','â','ầ','ấ','ậ','ẩ','ẫ','ă','ằ','ắ','ặ','ẳ','ẵ',
                 'è','é','ẹ','ẻ','ẽ','ê','ề','ế','ệ','ể','ễ','ì','í','ị','ỉ','ĩ',
                 'ò','ó','ọ','ỏ','õ','ô','ồ','ố','ộ','ổ','ỗ','ơ','ờ','ớ','ợ','ở','ỡ',
                 'ù','ú','ụ','ủ','ũ','ư','ừ','ứ','ự','ử','ữ','ỳ','ý','ỵ','ỷ','ỹ','đ'];
        $to   = ['a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a',
                 'e','e','e','e','e','e','e','e','e','e','e','i','i','i','i','i',
                 'o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o',
                 'u','u','u','u','u','u','u','u','u','u','u','y','y','y','y','y','d'];
        $name = mb_strtolower($name);
        $name = str_replace($from, $to, $name);
        $name = preg_replace('/[^a-z0-9\s-]/', '', $name);
        $name = preg_replace('/[\s-]+/', '-', trim($name));
        return $name;
    }
}