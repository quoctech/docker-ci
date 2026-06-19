<?php

namespace Modules\SchoolManagement\Models;

use CodeIgniter\Model;

class RoomModel extends Model
{
    protected $table         = 'rooms';
    protected $primaryKey    = 'id';
    protected $returnType    = 'object';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'uuid', 'branch_id', 'name', 'capacity', 'room_type', 'is_active',
    ];
}
