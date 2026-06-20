<?php

namespace Modules\AwesomeBar\Controllers;

use App\Controllers\ApiController;
use Modules\AwesomeBar\Repositories\AwesomeBarItemRepository;
use Modules\RoleManagement\Repositories\UserPermissionRepository;

/**
 * AdminAwesomeBarController
 *
 * API cho tính năng Awesome Bar (CTRL+K).
 * Tìm kiếm đa nguồn: trang admin, người dùng, module, cài đặt.
 *
 * Quy tắc truy cập (NO RESTRICTION):
 *   - BẤT KỲ user nào đã đăng nhập đều có thể dùng Awesome Bar.
 *   - Kết quả search được filter theo permission của user hiện tại.
 *
 *   super_admin: thấy TẤT CẢ items.
 *   workspace_admin + user (học sinh): chỉ thấy items cho module họ có `can_read`.
 *   user (học sinh) + BẤT KỲ role nào: luôn thấy lớp học của mình (my classrooms).
 *
 * Convention khi thêm module mới:
 *   Gọi AwesomeBarItemRepository::register() trong migration hoặc
 *   install script của module để đăng ký trang/tính năng vào Awesome Bar.
 *   Ví dụ xem: modules/AwesomeBar/docs/AwesomeBar.md
 */
class AdminAwesomeBarController extends ApiController
{
    private AwesomeBarItemRepository $repo;

    public function __construct()
    {
        $this->repo = new AwesomeBarItemRepository();
    }

    /**
     * GET /api/admin/search?q=xxx
     */
    public function search(): \CodeIgniter\HTTP\ResponseInterface
    {
        $q           = trim($this->request->getGet('q') ?? '');
        $auth        = $this->getAuthUser();
        $isSuperAdmin = $auth->role === 'super_admin';
        $isStudent    = $auth->role === 'user';

        // Lấy danh sách slug mà user có quyền can_read.
        // Super_admin: null = không filter (xem hết).
        // workspace_admin + user: filter theo permission qua UserPermissionRepository
        // (JOIN user_applied_roles → role_module_permissions).
        $userPermittedSlugs = $isSuperAdmin
            ? null
            : (new UserPermissionRepository())->getReadableSlugs($auth->sub);

        // Học sinh: luôn thấy lớp học của mình (kèm theo kết quả filter).
        $myClassrooms = $isStudent ? $this->searchMyClassrooms($q, $auth->sub) : [];

        if ($q === '') {
            $pages = $this->getFilteredPages(null, $userPermittedSlugs);
            return $this->respond([
                'status' => 'success',
                'data'   => [
                    'pages'      => $pages,
                    'classrooms' => $myClassrooms,
                    'users'      => [],
                    'modules'    => [],
                    'configs'    => [],
                ],
            ]);
        }

        return $this->respond([
            'status' => 'success',
            'data'   => [
                'pages'      => $this->getFilteredPages($q, $userPermittedSlugs),
                'classrooms' => $myClassrooms,
                'users'      => $isSuperAdmin ? $this->searchUsers($q)   : [],
                'modules'    => $isSuperAdmin ? $this->searchModules($q) : [],
                'configs'    => $isSuperAdmin ? $this->searchConfigs($q) : [],
            ],
        ]);
    }

    /**
     * Lấy + filter awesome_bar_items theo permission của user.
     *
     * @param string|null $q                   Query string (null = lấy tất cả)
     * @param array|null  $userPermittedSlugs  Slug user có quyền can_read. null = super_admin (xem tất cả)
     * @return array
     */
    private function getFilteredPages(?string $q, ?array $userPermittedSlugs): array
    {
        $db   = \Config\Database::connect();
        $builder = $db->table('awesome_bar_items')->where('is_active', 1);

        if ($q !== null && $q !== '') {
            $builder->groupStart()
                ->like('LOWER(title)', strtolower($q))
                ->orLike('keywords', strtolower($q))
            ->groupEnd();
        }

        $rows = $builder->orderBy('sort_order', 'ASC')->get(100)->getResult();

        return array_values(array_map(
            fn($r) => $this->formatItem($r),
            array_filter($rows, function ($r) use ($userPermittedSlugs) {
                // Super_admin (userPermittedSlugs === null) thì xem hết.
                if ($userPermittedSlugs === null) {
                    return true;
                }

                // Item có module_slug cụ thể → phải nằm trong permitted slugs.
                if ($r->module_slug !== null && $r->module_slug !== '') {
                    return in_array($r->module_slug, $userPermittedSlugs, true);
                }

                // Item với module_slug = NULL (hoặc rỗng) → KHÔNG hiện cho non-super-admin.
                return false;
            })
        ));
    }

    private function searchMyClassrooms(string $q, string $studentUuid): array
    {
        $db      = \Config\Database::connect();
        $builder = $db->table('classroom_members cm')
            ->select('c.uuid, c.name, c.subject, c.grade', false)
            ->join('classrooms c', 'c.id = cm.classroom_id', 'inner', false)
            ->where('cm.student_uuid', $studentUuid)
            ->where('cm.status', 'approved')
            ->where('c.is_active', 1);

        if ($q !== '') {
            $builder->groupStart()
                ->like('c.name', $q)
                ->orLike('c.subject', $q)
                ->groupEnd();
        }

        $rows = $builder->orderBy('cm.joined_at', 'DESC')->get(8)->getResult();

        return array_map(fn($c) => [
            'type'     => 'page',
            'title'    => $c->name,
            'subtitle' => ($c->subject ?: '') . ($c->grade ? ' · Lớp ' . $c->grade : ''),
            'url'      => '/admin/my-classrooms/' . $c->uuid,
            'icon'     => '📚',
        ], $rows);
    }

    // =========================================================================

    private function searchUsers(string $q): array
    {
        $db   = \Config\Database::connect();
        $rows = $db->table('users')
            ->select('uuid, username, full_name, email, role')
            ->groupStart()
                ->like('full_name', $q)->orLike('username', $q)->orLike('email', $q)
            ->groupEnd()
            ->where('status', 'active')
            ->orderBy('full_name', 'ASC')
            ->get(6)->getResult();

        return array_map(fn($u) => [
            'type'     => 'user',
            'title'    => $u->full_name ?: $u->username,
            'subtitle' => $u->email . ' · ' . match($u->role) {
                'super_admin'     => 'Super Admin',
                'workspace_admin' => 'Giáo viên',
                default           => 'Học sinh',
            },
            'url'  => '/admin/users',
            'icon' => '👤',
        ], $rows);
    }

    private function searchModules(string $q): array
    {
        $db   = \Config\Database::connect();
        $rows = $db->table('modules')
            ->select('slug, name, description, is_enabled, admin_url')
            ->like('name', $q)
            ->orderBy('sort_order', 'ASC')
            ->get(5)->getResult();

        return array_map(fn($m) => [
            'type'     => 'module',
            'title'    => $m->name,
            'subtitle' => ($m->is_enabled ? 'Đang bật' : 'Đang tắt') . ($m->description ? ' · ' . $m->description : ''),
            'url'      => $m->admin_url ?: '/admin/modules',
            'icon'     => '🧩',
        ], $rows);
    }

    private function searchConfigs(string $q): array
    {
        $db   = \Config\Database::connect();
        $rows = $db->table('site_configs')
            ->select('`key`, description, `value`')
            ->groupStart()
                ->like('description', $q)->orLike('`key`', $q)
            ->groupEnd()
            ->get(4)->getResult();

        return array_map(fn($c) => [
            'type'     => 'config',
            'title'    => $c->description ?: $c->key,
            'subtitle' => 'Cài đặt · ' . $c->key,
            'url'      => '/admin/configs',
            'icon'     => '⚙️',
        ], $rows);
    }

    private function formatItem(object $item): array
    {
        return [
            'type'     => $item->type,
            'title'    => $item->title,
            'subtitle' => $item->subtitle,
            'url'      => $item->url,
            'icon'     => $item->icon,
        ];
    }
}