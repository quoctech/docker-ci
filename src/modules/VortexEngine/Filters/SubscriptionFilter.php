<?php

namespace Modules\VortexEngine\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Modules\VortexEngine\Repositories\SubscriptionRepository;

/**
 * SubscriptionFilter - Kiểm tra quyền truy cập nội dung của học viên.
 *
 * Chạy sau JWTAuthFilter (request đã có authUser).
 * Guard cho các route api/lessons/*.
 *
 * Flow:
 * 1. Admin bypass (super_admin / workspace_admin không cần subscription)
 * 2. Lấy student_id từ JWT payload
 * 3. Check Redis cache → nếu hit: kiểm tra expired_date
 * 4. Cache miss → query DB → cache kết quả vào Redis
 * 5. Nếu hết hạn: cập nhật status EXPIRED trên DB, xóa cache, trả 403
 */
class SubscriptionFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $authUser = $request->authUser ?? null;

        if ($authUser === null) {
            return service('response')
                ->setStatusCode(401)
                ->setJSON(['status' => 'error', 'message' => 'Unauthenticated.']);
        }

        // Admin bypass — không cần subscription
        if (in_array($authUser->role ?? '', [ROLE_SUPER_ADMIN, ROLE_WORKSPACE_ADMIN], true)) {
            return null;
        }

        $studentId = $authUser->student_id ?? null;

        if ($studentId === null) {
            return service('response')
                ->setStatusCode(403)
                ->setJSON([
                    'status'  => 'error',
                    'code'    => 'no_student_id',
                    'message' => 'Chỉ học viên mới có thể truy cập nội dung bài học.',
                ]);
        }

        $studentId = (int) $studentId;
        $repo      = new SubscriptionRepository();

        try {
            $cached = $repo->getCachedSubscription($studentId);

            if ($cached !== null) {
                return $this->checkExpiry($repo, $studentId, $cached['status'] ?? '', $cached['expired_date'] ?? '', null);
            }

            // Cache miss — query DB
            $sub = $repo->findLatestByStudent($studentId);

            if ($sub === null) {
                // Học viên chưa có subscription nào
                return service('response')
                    ->setStatusCode(403)
                    ->setJSON([
                        'status'  => 'error',
                        'code'    => SUB_STATUS_TRIAL,
                        'message' => 'Bạn chưa kích hoạt gói học. Vui lòng liên hệ để đăng ký.',
                    ]);
            }

            // Cache lại kết quả từ DB
            $repo->cacheSubscription($studentId, $sub->status, $sub->expired_date);

            return $this->checkExpiry($repo, $studentId, $sub->status, $sub->expired_date ?? '', $sub->id);
        } catch (\Throwable $e) {
            log_message('error', '[VortexEngine][SubscriptionFilter] student_id=' . $studentId . ' error=' . $e->getMessage());

            // Fail-open: lỗi hệ thống không chặn học viên
            return null;
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }

    private function checkExpiry(
        SubscriptionRepository $repo,
        int $studentId,
        string $status,
        string $expiredDate,
        ?int $subId
    ) {
        if ($status === SUB_STATUS_EXPIRED) {
            return service('response')
                ->setStatusCode(403)
                ->setJSON([
                    'status'  => 'error',
                    'code'    => SUB_STATUS_EXPIRED,
                    'message' => 'Gói học của bạn đã hết hạn. Vui lòng gia hạn để tiếp tục học.',
                ]);
        }

        if ($status === SUB_STATUS_VIP && ! empty($expiredDate)) {
            if (strtotime($expiredDate) <= time()) {
                // Hết hạn trong thực tế nhưng DB chưa cập nhật
                if ($subId !== null) {
                    $repo->markExpired($studentId, $subId);
                } else {
                    $repo->invalidateCache($studentId);
                }

                return service('response')
                    ->setStatusCode(403)
                    ->setJSON([
                        'status'  => 'error',
                        'code'    => SUB_STATUS_EXPIRED,
                        'message' => 'Gói học của bạn đã hết hạn. Vui lòng gia hạn để tiếp tục học.',
                    ]);
            }
        }

        // TRIAL hoặc VIP còn hạn
        return null;
    }
}
