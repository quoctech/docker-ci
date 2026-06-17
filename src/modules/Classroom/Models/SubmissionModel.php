<?php

namespace Modules\Classroom\Models;

use CodeIgniter\Model;

class SubmissionModel extends Model
{
    protected $table         = 'assignment_submissions';
    protected $primaryKey    = 'id';
    protected $returnType    = 'object';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'uuid', 'assignment_id', 'student_uuid', 'content', 'file_url',
        'score', 'feedback', 'status', 'submitted_at', 'graded_at', 'created_at',
    ];
}
