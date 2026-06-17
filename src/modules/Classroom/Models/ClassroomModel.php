<?php

namespace Modules\Classroom\Models;

use CodeIgniter\Model;

class ClassroomModel extends Model
{
    protected $table         = 'classrooms';
    protected $primaryKey    = 'id';
    protected $returnType    = 'object';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'uuid', 'teacher_uuid', 'name', 'description', 'code',
        'subject', 'grade', 'auto_approve', 'is_active',
    ];
}
