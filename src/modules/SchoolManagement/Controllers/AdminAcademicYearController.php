<?php

namespace Modules\SchoolManagement\Controllers;

use App\Controllers\ApiController;
use App\Libraries\SystemLogger;
use CodeIgniter\HTTP\ResponseInterface;
use Modules\SchoolManagement\Repositories\AcademicYearRepository;
use Modules\SchoolManagement\Repositories\BranchRepository;
use Modules\RoleManagement\Repositories\UserPermissionRepository;

/**
 * AdminAcademicYearController - Quản lý năm học/học kỳ.
 *
 * Phân quyền chi tiết (module 'school-management'):
 *  - can_read   : xem danh sách, xem chi tiết
 *  - can_write  : tạo mới
 *  - can_edit   : cập nhật
 *  - can_delete : soft-delete
 *
 * Super_admin bypass tất cả check (userModules === null).
 */
class AdminAcademicYearController extends ApiController
{
    private AcademicYearRepository $repo;
    private BranchRepository        $branchRepo;
    private UserPermissionRepository $userPermRepo;

    public function __construct()
    {
        $this->repo        = new AcademicYearRepository();
        $this->branchRepo  = new BranchRepository();
        $this->userPermRepo = new UserPermissionRepository();
    }

    /**
     * Kiểm tra quyền cụ thể (can_read|can_write|can_edit|can_delete).
     * Super_admin luôn pass.
     */
    private function requirePerm(string $perm): ?ResponseInterface
    {
        $auth = $this->getAuthUser();
        // super_admin: bypass
        if (($auth->role ?? '') === 'super_admin') {
            return null;
        }
        if (! $this->userPermRepo->hasGranularPermission($auth->sub, 'school-management', $perm)) {
            return $this->error("Không có quyền {$perm} trên module School Management.", 403);
        }
        return null;
    }

    /** GET /api/school-management/academic-years */
    public function index(): ResponseInterface
    {
        if ($err = $this->requirePerm('can_read')) return $err;

        $branchUuid = trim((string) ($this->request->getGet('branch_uuid') ?? ''));
        $branchId   = null;
        if ($branchUuid !== '') {
            $branch = $this->branchRepo->findByUuid($branchUuid);
            if (! $branch) {
                return $this->error('Chi nhánh không tồn tại.', 404);
            }
            $branchId = $branch->id;
        }

        return $this->success($this->repo->list($branchId));
    }

    /** GET /api/school-management/academic-years/:uuid */
    public function show(string $uuid): ResponseInterface
    {
        if ($err = $this->requirePerm('can_read')) return $err;

        $year = $this->repo->findByUuid($uuid);
        if (! $year) {
            return $this->error('Không tìm thấy năm học.', 404);
        }

        return $this->success($year);
    }

    /**
     * POST /api/school-management/academic-years
     */
    public function create(): ResponseInterface
    {
        if ($err = $this->requirePerm('can_write')) return $err;

        $rules = [
            'name'       => 'required|max_length[100]',
            'branch_uuid' => 'required',
            'start_date' => 'required|valid_date[Y-m-d]',
            'end_date'   => 'required|valid_date[Y-m-d]',
        ];
        if (! $this->validate($rules)) {
            return $this->error('Dữ liệu không hợp lệ.', 422, $this->validator->getErrors());
        }

        $name       = trim((string) $this->request->getPost('name'));
        $branchUuid = trim((string) $this->request->getPost('branch_uuid'));
        $startDate  = (string) $this->request->getPost('start_date');
        $endDate    = (string) $this->request->getPost('end_date');

        // Validate dates
        if (strtotime($endDate) <= strtotime($startDate)) {
            return $this->error('Ngày kết thúc phải sau ngày bắt đầu.', 422);
        }

        // Lookup branch
        $branch = $this->branchRepo->findByUuid($branchUuid);
        if (! $branch) {
            return $this->error('Chi nhánh không tồn tại.', 404);
        }

        // Validate overlap
        if ($this->repo->hasOverlap($branch->id, $startDate, $endDate)) {
            $existing = $this->repo->findOverlapping($branch->id, $startDate, $endDate);
            $msg = $existing
                ? "Trùng ngày với năm học \"{$existing->name}\" ({$existing->start_date} → {$existing->end_date}) trong cùng chi nhánh."
                : 'Đã có năm học trùng ngày trong chi nhánh này.';
            return $this->error($msg, 409);
        }

        $year = $this->repo->create([
            'branch_id'  => $branch->id,
            'name'       => $name,
            'start_date' => $startDate,
            'end_date'   => $endDate,
        ]);

        if (! $year) {
            SystemLogger::error('Không thể tạo năm học', null, ['name' => $name]);
            return $this->error('Không thể tạo năm học.', 500);
        }

        SystemLogger::info('Tạo năm học: ' . $year->name, [
            'academic_year_uuid' => $year->uuid,
            'branch_uuid'        => $branch->uuid,
        ]);

        return $this->success($year, 'Tạo năm học thành công.', 201);
    }

    /**
     * PUT /api/school-management/academic-years/:uuid
     */
    public function update(string $uuid): ResponseInterface
    {
        if ($err = $this->requirePerm('can_edit')) return $err;

        $year = $this->repo->findByUuid($uuid);
        if (! $year) {
            return $this->error('Không tìm thấy năm học.', 404);
        }

        $body = $this->request->getJSON(true);
        if (! is_array($body)) {
            $body = $this->request->getPost() ?? [];
        }

        $name      = isset($body['name']) ? trim((string) $body['name']) : $year->name;
        $startDate = isset($body['start_date']) ? (string) $body['start_date'] : $year->start_date;
        $endDate   = isset($body['end_date'])   ? (string) $body['end_date']   : $year->end_date;

        // Branch có thể đổi
        $branchId = $year->branch_id;
        if (! empty($body['branch_uuid'])) {
            $branch = $this->branchRepo->findByUuid(trim((string) $body['branch_uuid']));
            if (! $branch) {
                return $this->error('Chi nhánh không tồn tại.', 404);
            }
            $branchId = $branch->id;
        }

        if ($name === '') {
            return $this->error('Tên năm học không được để trống.', 422);
        }
        if (strtotime($endDate) <= strtotime($startDate)) {
            return $this->error('Ngày kết thúc phải sau ngày bắt đầu.', 422);
        }

        if ($this->repo->hasOverlap($branchId, $startDate, $endDate, $uuid)) {
            $existing = $this->repo->findOverlapping($branchId, $startDate, $endDate, $uuid);
            $msg = $existing
                ? "Trùng ngày với năm học \"{$existing->name}\" ({$existing->start_date} → {$existing->end_date}) trong cùng chi nhánh."
                : 'Đã có năm học trùng ngày trong chi nhánh này.';
            return $this->error($msg, 409);
        }

        $this->repo->update($year->id, [
            'name'       => $name,
            'branch_id'  => $branchId,
            'start_date' => $startDate,
            'end_date'   => $endDate,
        ]);

        SystemLogger::info('Cập nhật năm học: ' . $name, ['academic_year_uuid' => $uuid]);

        return $this->success($this->repo->findByUuid($uuid), 'Cập nhật thành công.');
    }

    /**
     * DELETE /api/school-management/academic-years/:uuid
     */
    public function delete(string $uuid): ResponseInterface
    {
        if ($err = $this->requirePerm('can_delete')) return $err;

        $year = $this->repo->findByUuid($uuid);
        if (! $year) {
            return $this->error('Không tìm thấy năm học.', 404);
        }

        $this->repo->deactivate($year->id);

        SystemLogger::info('Xóa năm học: ' . $year->name, ['academic_year_uuid' => $uuid]);

        return $this->success(null, 'Đã xóa năm học.');
    }
}
