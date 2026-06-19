<?php

namespace Modules\AwesomeBar\Controllers;

use App\Controllers\ApiController;
use Modules\AwesomeBar\Repositories\AwesomeBarItemRepository;

/**
 * AdminAwesomeBarController
 *
 * API cho tính năng Awesome Bar (CTRL+K).
 * Tìm kiếm đa nguồn: trang admin, người dùng, module, cài đặt.
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
        $q    = trim($this->request->getGet('q') ?? '');
        $auth = $this->getAuthUser();
        $isSuperAdmin = $auth->role === 'super_admin';
        $isStudent    = $auth->role === 'user';

        $enabledSlugs = $this->getEnabledModuleSlugs();

        // Học sinh: chỉ tìm lớp học của mình (nếu module classroom đang bật)
        if ($isStudent) {
            $classroomActive = in_array('classroom', $enabledSlugs);

            if ($q === '') {
                $pages = $classroomActive
                    ? array_map(fn($i) => $this->formatItem($i), $this->repo->getActiveForModules(['classroom']))
                    : [];
                $myClassrooms = $classroomActive ? $this->searchMyClassrooms('', $auth->sub) : [];
                return $this->respond([
                    'status' => 'success',
                    'data'   => ['pages' => array_merge($pages, $myClassrooms), 'users' => [], 'modules' => [], 'configs' => []],
                ]);
            }

            return $this->respond([
                'status' => 'success',
                'data'   => [
                    'pages'   => $classroomActive ? $this->searchMyClassrooms($q, $auth->sub) : [],
                    'users'   => [],
                    'modules' => [],
                    'configs' => [],
                ],
            ]);
        }

        // workspace_admin: lọc pages theo module được cấp quyền, không trả users/modules/configs
        if (! $isSuperAdmin) {
            $permittedSlugs = $this->getPermittedSlugsForUser($auth->sub);
            $enabledSlugs   = array_intersect($enabledSlugs, $permittedSlugs);
        }

        if ($q === '') {
            $pages = array_map(fn($i) => $this->formatItem($i), $this->repo->getActiveForModules($enabledSlugs));
            return $this->respond([
                'status' => 'success',
                'data'   => ['pages' => $pages, 'users' => [], 'modules' => [], 'configs' => []],
            ]);
        }

        return $this->respond([
            'status' => 'success',
            'data'   => [
                'pages'   => $this->searchPages($q, $enabledSlugs),
                'users'   => $isSuperAdmin ? $this->searchUsers($q)   : [],
                'modules' => $isSuperAdmin ? $this->searchModules($q) : [],
                'configs' => $isSuperAdmin ? $this->searchConfigs($q) : [],
            ],
        ]);
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

    private function getPermittedSlugsForUser(string $userUuid): array
    {
        $db = \Config\Database::connect();
        return array_column(
            $db->table('user_module_permissions')
                ->select('module_slug')
                ->where('user_uuid', $userUuid)
                ->where('can_read', 1)
                ->get()->getResultArray(),
            'module_slug'
        );
    }

    // =========================================================================

    private function getEnabledModuleSlugs(): array
    {
        $db = \Config\Database::connect();
        return array_column(
            $db->table('modules')
                ->select('slug')
                ->where('is_enabled', 1)
                ->get()->getResultArray(),
            'slug'
        );
    }

    private function searchPages(string $q, array $enabledSlugs): array
    {
        $db   = \Config\Database::connect();
        $rows = $db->table('awesome_bar_items')
            ->where('is_active', 1)
            ->groupStart()
                ->like('LOWER(title)', strtolower($q))
                ->orLike('keywords', strtolower($q))
            ->groupEnd()
            ->orderBy('sort_order', 'ASC')
            ->get(10)->getResult();

        return array_values(array_map(
            fn($r) => $this->formatItem($r),
            array_filter($rows, fn($r) => $r->module_slug === null || in_array($r->module_slug, $enabledSlugs))
        ));
    }

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
