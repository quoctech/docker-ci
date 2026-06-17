<?php

namespace Modules\Classroom\Models;

use CodeIgniter\Model;

class AssignmentModel extends Model
{
    protected $table         = 'assignments';
    protected $primaryKey    = 'id';
    protected $returnType    = 'object';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'uuid', 'classroom_id', 'teacher_uuid', 'title', 'description',
        'due_date', 'max_score', 'is_published', 'file_path',
    ];
}
