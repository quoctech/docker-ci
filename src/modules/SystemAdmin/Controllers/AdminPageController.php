<?php

namespace Modules\SystemAdmin\Controllers;

use App\Controllers\BaseController;

class AdminPageController extends BaseController
{
    public function dashboard(): string
    {
        return view('Modules\SystemAdmin\Views/dashboard');
    }

    public function modules(): string
    {
        return view('Modules\SystemAdmin\Views/modules/index');
    }

    public function configs(): string
    {
        return view('Modules\SystemAdmin\Views/configs/index');
    }

    /**
     * Quản lý người dùng — render roles từ DB để admin chọn.
     *
     * Sau refactor drop `users.role`, role của user được quản lý qua `roles` table.
     * Dropdown filter/edit role lấy từ `roles` table (data-driven).
     */
    public function users(): string
    {
        // Lấy roles active từ DB
        $db = \Config\Database::connect();
        $roles = $db->table('roles')
            ->select('id, uuid, name, slug, description')
            ->where('is_active', 1)
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        // Map slug → user.role cho dropdown filter
        // 'super-admin' → 'super_admin', 'workspace-admin' → 'workspace_admin', 'user' → 'user'
        $roleFilterOptions = array_map(function ($r) {
            return [
                'slug'   => $r['slug'],
                'value'  => str_replace('-', '_', $r['slug']),  // super_admin, workspace_admin, user
                'label'  => $r['name'],
                'description' => $r['description'],
            ];
        }, $roles);

        return view('Modules\SystemAdmin\Views/users/index', [
            'roles'             => $roles,
            'roleFilterOptions' => $roleFilterOptions,
        ]);
    }
}