<?php

namespace Modules\VortexEngine\Models;

use CodeIgniter\Model;

class PackageModel extends Model
{
    protected $table         = 'packages';
    protected $primaryKey    = 'id';
    protected $returnType    = 'object';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'package_key',
        'name',
        'description',
        'price',
        'days_to_add',
        'is_active',
    ];

    public function findByKey(string $key): ?object
    {
        return $this->where('package_key', $key)->where('is_active', 1)->first();
    }

    public function getAllActive(): array
    {
        return $this->where('is_active', 1)->orderBy('days_to_add', 'ASC')->findAll();
    }
}
