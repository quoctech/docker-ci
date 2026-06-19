<?php

namespace Modules\SchoolManagement\Models;

use CodeIgniter\Model;

class CenterModel extends Model
{
    protected $table         = 'centers';
    protected $primaryKey    = 'id';
    protected $returnType    = 'object';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'uuid', 'name', 'address', 'phone', 'email', 'is_active',
    ];
}
