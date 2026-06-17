<?php

namespace Modules\Classroom\Models;

use CodeIgniter\Model;

class ClassroomMemberModel extends Model
{
    protected $table         = 'classroom_members';
    protected $primaryKey    = 'id';
    protected $returnType    = 'object';
    protected $useTimestamps = false;
    protected $allowedFields = ['classroom_id', 'student_uuid', 'status', 'joined_at', 'created_at'];
}
