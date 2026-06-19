<?php

namespace Modules\SchoolManagement\Models;

use CodeIgniter\Model;

class BranchModel extends Model
{
    protected $table         = 'branches';
    protected $primaryKey    = 'id';
    protected $returnType    = 'object';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'uuid', 'name', 'address', 'phone', 'email', 'is_active',
    ];
}
