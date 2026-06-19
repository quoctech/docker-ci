<?php

namespace Modules\RoleManagement\Models;

use CodeIgniter\Model;

class RoleModel extends Model
{
    protected $table         = 'roles';
    protected $primaryKey    = 'id';
    protected $returnType    = 'object';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'uuid', 'name', 'slug', 'description', 'is_active',
    ];
}
