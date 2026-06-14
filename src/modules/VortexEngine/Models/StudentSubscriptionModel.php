<?php

namespace Modules\VortexEngine\Models;

use CodeIgniter\Model;

class StudentSubscriptionModel extends Model
{
    protected $table         = 'student_subscriptions';
    protected $primaryKey    = 'id';
    protected $returnType    = 'object';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'student_id',
        'parent_id',
        'package_key',
        'status',
        'start_date',
        'expired_date',
    ];

    public function findActiveByStudent(string $studentId): ?object
    {
        return $this->where('student_id', $studentId)
                    ->whereIn('status', [SUB_STATUS_TRIAL, SUB_STATUS_VIP])
                    ->orderBy('id', 'DESC')
                    ->first();
    }

    public function findLatestByStudent(string $studentId): ?object
    {
        return $this->where('student_id', $studentId)
                    ->orderBy('id', 'DESC')
                    ->first();
    }
}
