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
    public function activate(string $studentId, ?string $parentId, string $packageKey, int $daysToAdd): object
    {
        $db  = \Config\Database::connect();
        $now = now_datetime();

        $db->transStart();

        $current = $this->subModel->findLatestByStudent($studentId);

        $isActive = $current
            && $current->status === SUB_STATUS_VIP
            && $current->expired_date !== null
            && strtotime($current->expired_date) > time();

        if ($isActive) {
            // Gia hạn: cộng thêm ngày vào hạn cũ
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
                'status'       => SUB_STATUS_VIP,
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
