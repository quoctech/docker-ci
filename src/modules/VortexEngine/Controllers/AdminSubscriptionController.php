<?php

namespace Modules\VortexEngine\Controllers;

use App\Controllers\ApiController;
use CodeIgniter\HTTP\ResponseInterface;
use Modules\VortexEngine\Repositories\SubscriptionRepository;

/**
 * AdminSubscriptionController - Quản lý subscription học viên.
 *
 * Endpoint:
 *   POST /api/admin/subscriptions/activate — Kích hoạt / gia hạn gói học
 */
class AdminSubscriptionController extends ApiController
{
    private SubscriptionRepository $repo;

    public function __construct()
    {
        $this->repo = new SubscriptionRepository();
    }

    /**
     * POST /api/admin/subscriptions/activate
     *
     * Body (JSON hoặc form):
     *   student_id  string (UUID)  required
     *   package_key string         required
     *   parent_id   string (UUID)  optional
     */
    public function activate(): ResponseInterface
    {
        $isJson = str_contains($this->request->getHeaderLine('Content-Type'), 'application/json');
        $input  = $isJson ? (array) $this->request->getJSON(true) : $this->request->getPost();

        $rules = [
            'student_id'  => 'required|min_length[36]|max_length[36]',
            'package_key' => 'required|alpha_dash|max_length[50]',
            'parent_id'   => 'permit_empty|min_length[36]|max_length[36]',
        ];

        if (! $this->validateData($input, $rules)) {
            return $this->error('Dữ liệu không hợp lệ.', 422, $this->validator->getErrors());
        }

        $studentId  = $input['student_id'];
        $packageKey = $input['package_key'];
        $parentId   = $input['parent_id'] ?? null;

        $package = $this->repo->findPackageByKey($packageKey);

        if ($package === null) {
            return $this->error('Gói học không tồn tại hoặc đã bị vô hiệu hoá.', 404);
        }

        try {
            $sub = $this->repo->activate($studentId, $parentId, $packageKey, $package->days_to_add, $package->sub_type ?? 'VIP');

            return $this->success([
                'subscription_id' => $sub->id,
                'student_id'      => $sub->student_id,
                'package_key'     => $sub->package_key,
                'status'          => $sub->status,
                'start_date'      => $sub->start_date,
                'expired_date'    => $sub->expired_date,
            ], 'Kích hoạt gói học thành công.');
        } catch (\Throwable $e) {
            log_message('error', '[VortexEngine][Activate] student_id=' . $studentId . ' error=' . $e->getMessage());

            return $this->error('Có lỗi xảy ra khi kích hoạt gói học. Vui lòng thử lại.', 500);
        }
    }

    /**
     * GET /api/admin/subscriptions/packages
     */
    public function packages(): ResponseInterface
    {
        return $this->success($this->repo->getAllActivePackages());
    }

    /**
     * GET /api/admin/subscriptions/packages/all
     *
     * Toàn bộ gói (cả tắt), dùng cho trang quản lý.
     */
    public function allPackages(): ResponseInterface
    {
        return $this->success($this->repo->getAllPackages());
    }

    /**
     * POST /api/admin/subscriptions/packages
     *
     * Tạo gói học tùy chọn.
     */
    public function createPackage(): ResponseInterface
    {
        $isJson = str_contains($this->request->getHeaderLine('Content-Type'), 'application/json');
        $input  = $isJson ? (array) $this->request->getJSON(true) : $this->request->getPost();

        $rules = [
            'package_key'   => 'required|alpha_dash|max_length[50]|is_unique[packages.package_key]',
            'name'          => 'required|max_length[100]',
            'days_to_add'   => 'required|integer|greater_than[0]',
            'price'         => 'required|integer|greater_than_equal_to[0]',
            'description'   => 'permit_empty|max_length[255]',
            'sub_type'      => 'permit_empty|in_list[VIP,TRIAL]',
            'max_students'  => 'permit_empty|integer|greater_than[0]',
            'allowed_grades'=> 'permit_empty',
        ];

        if (! $this->validateData($input, $rules)) {
            return $this->error('Dữ liệu không hợp lệ.', 422, $this->validator->getErrors());
        }

        $gradesRaw = $input['allowed_grades'] ?? null;
        $gradesJson = $this->normalizeGrades($gradesRaw);

        try {
            $pkg = $this->repo->createPackage([
                'package_key'    => strtoupper($input['package_key']),
                'name'           => $input['name'],
                'days_to_add'    => (int) $input['days_to_add'],
                'price'          => (int) $input['price'],
                'description'    => $input['description'] ?? null,
                'sub_type'       => in_array($input['sub_type'] ?? '', ['VIP', 'TRIAL']) ? $input['sub_type'] : 'VIP',
                'max_students'   => isset($input['max_students']) ? (int) $input['max_students'] : 1,
                'allowed_grades' => $gradesJson,
                'is_active'      => 1,
            ]);

            return $this->success($pkg, 'Đã tạo gói học.', 201);
        } catch (\Throwable $e) {
            log_message('error', '[VortexEngine][CreatePackage] ' . $e->getMessage());
            return $this->error('Có lỗi xảy ra. Vui lòng thử lại.', 500);
        }
    }

    /**
     * PUT /api/admin/subscriptions/packages/(:segment)
     *
     * Cập nhật thông tin gói học.
     */
    public function updatePackage(string $key): ResponseInterface
    {
        $pkg = $this->repo->findPackageByKeyAny($key);

        if ($pkg === null) {
            return $this->error('Gói học không tồn tại.', 404);
        }

        $isJson = str_contains($this->request->getHeaderLine('Content-Type'), 'application/json');
        $input  = $isJson ? (array) $this->request->getJSON(true) : $this->request->getRawInput();

        $rules = [
            'name'           => 'permit_empty|max_length[100]',
            'days_to_add'    => 'permit_empty|integer|greater_than[0]',
            'price'          => 'permit_empty|integer|greater_than_equal_to[0]',
            'description'    => 'permit_empty|max_length[255]',
            'sub_type'       => 'permit_empty|in_list[VIP,TRIAL]',
            'max_students'   => 'permit_empty|integer|greater_than[0]',
            'allowed_grades' => 'permit_empty',
        ];

        if (! $this->validateData($input, $rules)) {
            return $this->error('Dữ liệu không hợp lệ.', 422, $this->validator->getErrors());
        }

        $data = array_filter([
            'name'        => $input['name'] ?? null,
            'days_to_add' => isset($input['days_to_add']) ? (int) $input['days_to_add'] : null,
            'price'       => isset($input['price'])       ? (int) $input['price']       : null,
            'description' => $input['description'] ?? null,
            'sub_type'    => in_array($input['sub_type'] ?? '', ['VIP', 'TRIAL']) ? $input['sub_type'] : null,
            'max_students'=> isset($input['max_students']) ? (int) $input['max_students'] : null,
        ], fn($v) => $v !== null);

        if (array_key_exists('allowed_grades', $input)) {
            $data['allowed_grades'] = $this->normalizeGrades($input['allowed_grades']);
        }

        if (empty($data)) {
            return $this->error('Không có dữ liệu để cập nhật.', 422);
        }

        $this->repo->updatePackage($pkg->id, $data);

        return $this->success(
            array_merge((array) $pkg, $data),
            'Đã cập nhật gói học.'
        );
    }

    /**
     * PUT /api/admin/subscriptions/{id}
     *
     * Sửa subscription: package_key, status, expired_date.
     */
    public function updateSubscription(int $id): ResponseInterface
    {
        $sub = $this->repo->findSubscriptionById($id);

        if ($sub === null) {
            return $this->error('Subscription không tồn tại.', 404);
        }

        $isJson = str_contains($this->request->getHeaderLine('Content-Type'), 'application/json');
        $input  = $isJson ? (array) $this->request->getJSON(true) : $this->request->getRawInput();

        $rules = [
            'package_key'  => 'permit_empty|max_length[50]',
            'status'       => 'permit_empty|in_list[VIP,TRIAL,EXPIRED]',
            'expired_date' => 'permit_empty',
        ];

        if (! $this->validateData($input, $rules)) {
            return $this->error('Dữ liệu không hợp lệ.', 422, $this->validator->getErrors());
        }

        $data = array_filter([
            'package_key'  => $input['package_key']  ?? null,
            'status'       => $input['status']        ?? null,
            'expired_date' => $input['expired_date']  ?? null,
        ], fn($v) => $v !== null && $v !== '');

        if (empty($data)) {
            return $this->error('Không có dữ liệu để cập nhật.', 422);
        }

        $this->repo->updateSubscription($id, $data);
        $this->repo->invalidateCache($sub->student_id);

        $updated = $this->repo->findSubscriptionById($id);

        return $this->success($updated, 'Đã cập nhật subscription.');
    }

    /**
     * GET /api/admin/subscriptions/list
     *
     * Danh sách tất cả subscription kèm thông tin học sinh.
     */
    public function listSubscriptions(): ResponseInterface
    {
        $page    = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = min((int) ($this->request->getGet('per_page') ?? 20), 100);
        $search  = $this->request->getGet('search') ?? '';
        $status  = $this->request->getGet('status') ?? '';
        $grade   = $this->request->getGet('grade') ?? '';

        $result = $this->repo->listSubscriptions($search, $status, $grade !== '' ? (int) $grade : null, $page, $perPage);

        return $this->success($result);
    }

    /**
     * GET /api/admin/subscriptions/students
     *
     * Tìm kiếm học sinh (role=user) phục vụ cho form kích hoạt gói học.
     * Params: search, page, per_page, exclude_subscribed
     */
    public function students(): ResponseInterface
    {
        $search           = $this->request->getGet('search') ?? '';
        $page             = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage          = min((int) ($this->request->getGet('per_page') ?? 10), 50);
        $excludeSubscribed = (bool) $this->request->getGet('exclude_subscribed');

        $db      = \Config\Database::connect();
        $builder = $db->table('users u')
            ->select('u.uuid, u.full_name, u.email, u.username, u.grade', false)
            ->where('u.role', 'user')
            ->where('u.status', 'active');

        if (!empty($search)) {
            $builder->groupStart()
                ->like('u.full_name', $search)
                ->orLike('u.email', $search)
                ->orLike('u.username', $search)
                ->groupEnd();
        }

        if ($excludeSubscribed) {
            $builder->where(
                "u.uuid NOT IN (SELECT ss.student_id FROM student_subscriptions ss WHERE ss.status IN ('VIP','TRIAL') AND (ss.expired_date IS NULL OR ss.expired_date > NOW()))",
                null,
                false
            );
        }

        $total   = $builder->countAllResults(false);
        $users   = $builder->orderBy('u.full_name', 'ASC')
            ->limit($perPage, ($page - 1) * $perPage)
            ->get()->getResultObject();

        return $this->success([
            'users'      => $users,
            'pagination' => [
                'page'        => $page,
                'per_page'    => $perPage,
                'total'       => $total,
                'total_pages' => (int) ceil($total / $perPage),
            ],
        ]);
    }

    /**
     * Chuẩn hoá allowed_grades về JSON string hoặc null.
     * Input có thể là: array [1,2,5], string "1,2,5", string "[1,2,5]", null/empty.
     */
    private function normalizeGrades(mixed $raw): ?string
    {
        if ($raw === null || $raw === '' || $raw === []) {
            return null;
        }

        if (is_array($raw)) {
            $grades = array_map('intval', $raw);
        } else {
            $cleaned = trim((string) $raw, '[]');
            $grades  = array_map('intval', array_filter(explode(',', $cleaned), fn($v) => $v !== ''));
        }

        sort($grades);

        return empty($grades) ? null : json_encode($grades);
    }

    /**
     * PUT /api/admin/subscriptions/packages/(:segment)/toggle
     *
     * Bật / tắt gói học.
     */
    public function togglePackage(string $key): ResponseInterface
    {
        $pkg = $this->repo->findPackageByKeyAny($key);

        if ($pkg === null) {
            return $this->error('Gói học không tồn tại.', 404);
        }

        $newState = ! (bool) $pkg->is_active;
        $this->repo->updatePackage($pkg->id, ['is_active' => $newState ? 1 : 0]);

        return $this->success(
            ['package_key' => $key, 'is_active' => $newState],
            $newState ? 'Đã bật gói học.' : 'Đã tắt gói học.'
        );
    }
}
