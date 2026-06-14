<?php

namespace Modules\VortexEngine\Repositories;

use App\Libraries\RedisService;
use Modules\VortexEngine\Models\PackageModel;
use Modules\VortexEngine\Models\StudentSubscriptionModel;

class SubscriptionRepository
{
    private PackageModel $packageModel;
    private StudentSubscriptionModel $subModel;

    public function __construct()
    {
        $this->packageModel = new PackageModel();
        $this->subModel     = new StudentSubscriptionModel();
    }

    public function findPackageByKey(string $key): ?object
    {
        return $this->packageModel->findByKey($key);
    }

    public function getAllActivePackages(): array
    {
        return $this->packageModel->getAllActive();
    }

    public function getAllPackages(): array
    {
        return $this->packageModel->orderBy('days_to_add', 'ASC')->findAll();
    }

    public function findPackageByKeyAny(string $key): ?object
    {
        return $this->packageModel->where('package_key', $key)->first();
    }

    public function createPackage(array $data): object
    {
        $id = $this->packageModel->insert($data, true);
        return $this->packageModel->find($id);
    }

    public function updatePackage(int $id, array $data): void
    {
        $this->packageModel->update($id, $data);
    }

    public function findSubscriptionById(int $id): ?object
    {
        return $this->subModel->find($id);
    }

    public function updateSubscription(int $id, array $data): void
    {
        $this->subModel->update($id, $data);
    }

    /**
     * Danh sách subscription kèm thông tin học sinh (JOIN users).
     */
    public function listSubscriptions(string $search, string $status, ?int $grade, int $page, int $perPage): array
    {
        $db = \Config\Database::connect();

        $builder = $db->table('student_subscriptions ss')
            ->select('ss.id, ss.student_id, ss.package_key, ss.allowed_grades, ss.status, ss.start_date, ss.expired_date,
                      u.full_name, u.email, u.username, u.grade,
                      p.name as package_name, p.days_to_add, p.price, p.max_students')
            ->join('users u',        'u.uuid = ss.student_id', 'left')
            ->join('packages p',     'p.package_key = ss.package_key', 'left')
            ->orderBy('ss.id', 'DESC');

        if ($search !== '') {
            $builder->groupStart()
                ->like('u.full_name', $search)
                ->orLike('u.email', $search)
                ->orLike('u.username', $search)
                ->groupEnd();
        }

        if ($status !== '') {
            if ($status === 'EXPIRED') {
                // Bắt cả hàng đã mark EXPIRED lẫn VIP/TRIAL có expired_date đã qua (lazy-expiry)
                $builder->groupStart()
                    ->where('ss.status', 'EXPIRED')
                    ->orGroupStart()
                        ->whereIn('ss.status', ['VIP', 'TRIAL'])
                        ->where('ss.expired_date IS NOT NULL', null, false)
                        ->where('ss.expired_date <', date('Y-m-d H:i:s'))
                    ->groupEnd()
                ->groupEnd();
            } else {
                // Với VIP/TRIAL chỉ lấy những hàng còn hạn (expired_date null hoặc chưa qua)
                $builder->where('ss.status', $status)
                    ->groupStart()
                        ->where('ss.expired_date IS NULL', null, false)
                        ->orWhere('ss.expired_date >', date('Y-m-d H:i:s'))
                    ->groupEnd();
            }
        }

        if ($grade !== null) {
            $builder->where('u.grade', $grade);
        }

        $total = $builder->countAllResults(false);
        $rows  = $builder->limit($perPage, ($page - 1) * $perPage)->get()->getResultArray();

        return [
            'subscriptions' => $rows,
            'pagination'    => [
                'page'        => $page,
                'per_page'    => $perPage,
                'total'       => $total,
                'total_pages' => (int) ceil($total / $perPage),
            ],
        ];
    }

    /**
     * Lấy thông tin subscription từ Redis. null nếu chưa cache.
     */
    public function getCachedSubscription(string $studentId): ?array
    {
        $redis = RedisService::getInstance();
        $data  = $redis->hgetall(REDIS_PREFIX_SUBSCRIPTION . $studentId);

        return (! empty($data)) ? $data : null;
    }

    /**
     * Lưu subscription vào Redis cache với TTL.
     */
    public function cacheSubscription(string $studentId, string $status, ?string $expiredDate): void
    {
        $redis = RedisService::getInstance();
        $key   = REDIS_PREFIX_SUBSCRIPTION . $studentId;

        $redis->hset($key, 'status', $status);
        $redis->hset($key, 'expired_date', $expiredDate ?? '');
        $redis->expire($key, SUBSCRIPTION_CACHE_TTL);
    }

    /**
     * Xóa cache subscription của 1 student.
     */
    public function invalidateCache(string $studentId): void
    {
        RedisService::getInstance()->del(REDIS_PREFIX_SUBSCRIPTION . $studentId);
    }

    /**
     * Lấy subscription mới nhất từ DB.
     */
    public function findLatestByStudent(string $studentId): ?object
    {
        return $this->subModel->findLatestByStudent($studentId);
    }

    /**
     * Kích hoạt hoặc gia hạn subscription trong DB transaction.
     *
     * Logic:
     * - Nếu có subscription VIP còn hạn: gia hạn (expired_date = old + days)
     * - Ngược lại: tạo mới với start_date = NOW, expired_date = NOW + days
     *
     * @throws \RuntimeException nếu transaction thất bại
     */
    public function activate(string $studentId, ?string $parentId, string $packageKey, int $daysToAdd, string $subType = 'VIP'): object
    {
        $db  = \Config\Database::connect();
        $now = now_datetime();

        $db->transStart();

        $current = $this->subModel->findLatestByStudent($studentId);

        $isActive = $current
            && $current->status === SUB_STATUS_VIP
            && $current->expired_date !== null
            && strtotime($current->expired_date) > time();

        if ($isActive && $subType === SUB_STATUS_VIP) {
            // Gia hạn: cộng thêm ngày vào hạn cũ (chỉ khi cùng loại VIP)
            $newExpiry = date('Y-m-d H:i:s', strtotime($current->expired_date) + ($daysToAdd * DAY));
            $this->subModel->update($current->id, [
                'expired_date' => $newExpiry,
                'updated_at'   => $now,
            ]);
            $current->expired_date = $newExpiry;
            $result = $current;
        } else {
            // Tạo subscription mới
            $newExpiry = date('Y-m-d H:i:s', time() + ($daysToAdd * DAY));
            $insertId  = $this->subModel->insert([
                'student_id'   => $studentId,
                'parent_id'    => $parentId,
                'package_key'  => $packageKey,
                'status'       => $subType,
                'start_date'   => $now,
                'expired_date' => $newExpiry,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
            $result = $this->subModel->find($insertId);
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            throw new \RuntimeException('Kích hoạt subscription thất bại. Transaction rollback.');
        }

        // Invalidate Redis cache để lần sau đọc lại từ DB
        $this->invalidateCache($studentId);

        return $result;
    }

    /**
     * Đánh dấu subscription EXPIRED trong DB và xóa cache.
     */
    public function markExpired(string $studentId, int $subId): void
    {
        $this->subModel->update($subId, [
            'status'     => SUB_STATUS_EXPIRED,
            'updated_at' => now_datetime(),
        ]);
        $this->invalidateCache($studentId);
    }
}
