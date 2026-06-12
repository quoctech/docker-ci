<?php

namespace Modules\SystemAdmin\Controllers;

use App\Controllers\ApiController;
use App\Libraries\RedisService;
use CodeIgniter\HTTP\ResponseInterface;
use Modules\Auth\Models\UserModel;
use Modules\Auth\Models\RefreshTokenModel;

class UserManagementController extends ApiController
{
    private UserModel $userModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
    }

    /**
     * GET /api/admin/users
     */
    public function index(): ResponseInterface
    {
        $page    = (int) ($this->request->getGet('page') ?? 1);
        $perPage = min((int) ($this->request->getGet('per_page') ?? 20), 100);
        $role    = $this->request->getGet('role');
        $status  = $this->request->getGet('status');
        $search  = $this->request->getGet('search');

        $builder = $this->userModel->builder();

        if ($role) {
            $builder->where('role', $role);
        }

        if ($status) {
            $builder->where('status', $status);
        }

        if ($search) {
            $builder->groupStart()
                    ->like('email', $search)
                    ->orLike('full_name', $search)
                    ->groupEnd();
        }

        $builder->where('deleted_at', null);

        $total = $builder->countAllResults(false);
        $users = $builder->orderBy('created_at', 'DESC')
                         ->limit($perPage, ($page - 1) * $perPage)
                         ->get()
                         ->getResult();

        return $this->success([
            'users' => array_map(fn($u) => [
                'id'         => (int) $u->id,
                'uuid'       => $u->uuid,
                'email'      => $u->email,
                'full_name'  => $u->full_name,
                'role'       => $u->role,
                'status'     => $u->status,
                'created_at' => $u->created_at,
                'last_login' => $u->last_login_at,
            ], $users),
            'pagination' => [
                'page'       => $page,
                'per_page'   => $perPage,
                'total'      => $total,
                'total_pages' => (int) ceil($total / $perPage),
            ],
        ]);
    }

    /**
     * PUT /api/admin/users/(:num)/status
     */
    public function updateStatus(int $id): ResponseInterface
    {
        $rules = [
            'status' => 'required|in_list[active,locked,pending]',
        ];

        if (! $this->validate($rules)) {
            return $this->error('Validation failed.', 422, $this->validator->getErrors());
        }

        $user = $this->userModel->find($id);

        if (! $user) {
            return $this->error('User not found.', 404);
        }

        // Cannot modify super_admin unless you are super_admin
        if ($user->role === 'super_admin' && $this->getAuthUser()->role !== 'super_admin') {
            return $this->error('Cannot modify super admin.', 403);
        }

        $newStatus = $this->request->getPost('status');
        $this->userModel->update($id, ['status' => $newStatus]);

        // If locking, kill all sessions immediately
        if ($newStatus === 'locked') {
            RedisService::revokeAllSessions($id);
            (new RefreshTokenModel())->revokeAllForUser($id);
        }

        return $this->success([
            'uuid'   => $user->uuid,
            'status' => $newStatus,
        ], "User status updated to {$newStatus}.");
    }

    /**
     * PUT /api/admin/users/(:num)/role
     */
    public function updateRole(int $id): ResponseInterface
    {
        $rules = [
            'role' => 'required|in_list[super_admin,workspace_admin,user]',
        ];

        if (! $this->validate($rules)) {
            return $this->error('Validation failed.', 422, $this->validator->getErrors());
        }

        $user = $this->userModel->find($id);

        if (! $user) {
            return $this->error('User not found.', 404);
        }

        // Only super_admin can assign super_admin role
        $newRole = $this->request->getPost('role');
        if ($newRole === 'super_admin' && $this->getAuthUser()->role !== 'super_admin') {
            return $this->error('Only super admin can promote to super admin.', 403);
        }

        $this->userModel->update($id, ['role' => $newRole]);

        // Force re-login to get new token with updated role
        RedisService::revokeAllSessions($id);
        (new RefreshTokenModel())->revokeAllForUser($id);

        return $this->success([
            'uuid' => $user->uuid,
            'role' => $newRole,
        ], "User role updated to {$newRole}.");
    }
}
