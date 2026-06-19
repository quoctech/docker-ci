<?php

namespace Modules\RoleManagement\Controllers;

use App\Controllers\ApiController;
use App\Libraries\RedisService;
use App\Libraries\SystemLogger;
use CodeIgniter\HTTP\ResponseInterface;
use Modules\Auth\Repositories\RefreshTokenRepository;
use Modules\RoleManagement\Repositories\RoleRepository;
use Modules\SystemAdmin\Repositories\UserModulePermissionRepository;

class AdminRoleController extends ApiController
{
    private RoleRepository $repo;

    private const NON_GRANTABLE = ['auth', 'system-admin', 'system-log'];

    public function __construct()
    {
        $this->repo = new RoleRepository();
    }

    /** GET /api/role-management/roles */
    public function index(): ResponseInterface
    {
        return $this->success($this->repo->list());
    }

    /** POST /api/role-management/roles */
    public function create(): ResponseInterface
    {
        $rules = ['name' => 'required|max_length[100]'];
        if (! $this->validate($rules)) {
            return $this->error('Dữ liệu không hợp lệ.', 422, $this->validator->getErrors());
        }

        $name = trim($this->request->getPost('name'));
        $slug = $this->toSlug($name);

        if ($this->repo->findBySlug($slug)) {
            return $this->error('Đã có vai trò với tên tương tự. Vui lòng đặt tên khác.', 409);
        }

        $role = $this->repo->create([
            'name'        => $name,
            'slug'        => $slug,
            'description' => $this->request->getPost('description'),
        ]);

        if (! $role) {
            return $this->error('Không thể tạo vai trò.', 500);
        }

        SystemLogger::info('Tạo vai trò: ' . $role->name, ['role_uuid' => $role->uuid]);
        return $this->success($role, 'Tạo vai trò thành công.', 201);
    }

    /** GET /api/role-management/roles/:uuid */
    public function show(string $uuid): ResponseInterface
    {
        $role = $this->repo->findByUuid($uuid);
        if (! $role) return $this->error('Không tìm thấy vai trò.', 404);

        return $this->success([
            'role'        => $role,
            'permissions' => $this->repo->getPermissions($role->id),
        ]);
    }

    /** PUT /api/role-management/roles/:uuid */
    public function update(string $uuid): ResponseInterface
    {
        $role = $this->repo->findByUuid($uuid);
        if (! $role) return $this->error('Không tìm thấy vai trò.', 404);

        $input = $this->request->getRawInput();
        $name  = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            return $this->error('Tên vai trò không được để trống.', 422);
        }

        $data = ['name' => $name];
        if (array_key_exists('description', $input)) {
            $data['description'] = $input['description'] !== '' ? $input['description'] : null;
        }

        $this->repo->update($role->id, $data);

        SystemLogger::info('Cập nhật vai trò: ' . $role->name, ['role_uuid' => $uuid]);
        return $this->success($this->repo->findByUuid($uuid), 'Cập nhật thành công.');
    }

    /** DELETE /api/role-management/roles/:uuid */
    public function delete(string $uuid): ResponseInterface
    {
        $role = $this->repo->findByUuid($uuid);
        if (! $role) return $this->error('Không tìm thấy vai trò.', 404);

        $this->repo->deactivate($role->id);
        SystemLogger::info('Xóa vai trò: ' . $role->name, ['role_uuid' => $uuid]);
        return $this->success(null, 'Đã xóa vai trò.');
    }

    /** GET /api/role-management/roles/:uuid/modules */
    public function getModules(string $uuid): ResponseInterface
    {
        $role = $this->repo->findByUuid($uuid);
        if (! $role) return $this->error('Không tìm thấy vai trò.', 404);

        $db         = \Config\Database::connect();
        $allModules = $db->table('modules')
            ->select('slug, name, is_enabled')
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

    /** PUT /api/role-management/roles/:uuid/modules */
    public function setModules(string $uuid): ResponseInterface
    {
        $auth = $this->getAuthUser();

        $role = $this->repo->findByUuid($uuid);
        if (! $role) return $this->error('Không tìm thấy vai trò.', 404);

        $body    = $this->request->getJSON(true) ?? [];
        $modules = $body['modules'] ?? [];
        if (! is_array($modules)) {
            return $this->error('Trường modules phải là mảng.', 422);
        }

        $db         = \Config\Database::connect();
        $validSlugs = array_column(
            $db->table('modules')
                ->select('slug')
                ->whereNotIn('slug', self::NON_GRANTABLE)
                ->get()
                ->getResultArray(),
            'slug'
        );

        $permissions = [];
        foreach ($modules as $m) {
            $slug = $m['slug'] ?? '';
            if (! in_array($slug, $validSlugs, true) || empty($m['can_read'])) continue;

            $permissions[] = [
                'slug'       => $slug,
                'can_read'   => 1,
                'can_write'  => empty($m['can_write'])  ? 0 : 1,
                'can_edit'   => empty($m['can_edit'])   ? 0 : 1,
                'can_delete' => empty($m['can_delete']) ? 0 : 1,
            ];
        }

        try {
            $this->repo->setPermissions($role->id, $permissions);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 500);
        }

        // Sync quyền mới sang tất cả user đang dùng role này
        $linkedUsers = $this->repo->getUsersWithRole($role->id);
        if (! empty($linkedUsers)) {
            $permRepo = new UserModulePermissionRepository();
            $tokenRepo = new RefreshTokenRepository();
            foreach ($linkedUsers as $userUuid) {
                try {
                    $permRepo->setPermissions($userUuid, $permissions, $auth->sub);
                } catch (\RuntimeException $e) {
                    // Không để 1 user lỗi làm hỏng toàn bộ response
                }
                RedisService::forceLogoutUser($userUuid);
                $tokenRepo->revokeAllForUser($userUuid);
            }
        }

        $synced = count($linkedUsers);
        $msg = $synced > 0
            ? "Đã cập nhật phân quyền vai trò và đồng bộ cho {$synced} người dùng."
            : 'Đã cập nhật phân quyền vai trò.';

        return $this->success(null, $msg);
    }

    /** POST /api/role-management/roles/:uuid/apply-to-user */
    public function applyToUser(string $uuid): ResponseInterface
    {
        $auth = $this->getAuthUser();

        $role = $this->repo->findByUuid($uuid);
        if (! $role) return $this->error('Không tìm thấy vai trò.', 404);

        $body     = $this->request->getJSON(true) ?? [];
        $userUuid = trim((string) ($body['user_uuid'] ?? $this->request->getPost('user_uuid') ?? ''));
        if (! $userUuid) {
            return $this->error('Thiếu user_uuid.', 422);
        }

        $db         = \Config\Database::connect();
        $validSlugs = array_column(
            $db->table('modules')
                ->select('slug')
                ->whereNotIn('slug', self::NON_GRANTABLE)
                ->get()
                ->getResultArray(),
            'slug'
        );

        $permissions = [];
        foreach ($this->repo->getPermissions($role->id) as $p) {
            if (! in_array($p->module_slug, $validSlugs, true) || ! $p->can_read) continue;
            $permissions[] = [
                'slug'       => $p->module_slug,
                'can_read'   => (int) $p->can_read,
                'can_write'  => (int) $p->can_write,
                'can_edit'   => (int) $p->can_edit,
                'can_delete' => (int) $p->can_delete,
            ];
        }

        $permRepo = new UserModulePermissionRepository();
        try {
            $permRepo->setPermissions($userUuid, $permissions, $auth->sub);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 500);
        }

        // Lưu link user → role để sau này thay đổi role sẽ tự động sync
        $db->table('user_applied_roles')
            ->where('user_uuid', $userUuid)
            ->delete();
        $db->table('user_applied_roles')->insert([
            'user_uuid'  => $userUuid,
            'role_id'    => $role->id,
            'applied_by' => $auth->sub,
            'applied_at' => date('Y-m-d H:i:s'),
        ]);

        // Buộc user đăng xuất ngay để phiên mới nhận đủ quyền module
        RedisService::forceLogoutUser($userUuid);
        (new RefreshTokenRepository())->revokeAllForUser($userUuid);

        SystemLogger::info('Áp dụng vai trò "' . $role->name . '" cho user: ' . $userUuid, [
            'role_uuid' => $uuid,
            'user_uuid' => $userUuid,
        ]);

        return $this->success(null, 'Đã áp dụng vai trò "' . $role->name . '". Người dùng sẽ được đăng xuất tự động để nhận quyền mới.');
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
