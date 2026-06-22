<?php

namespace Modules\SchoolManagement\Models;

use CodeIgniter\Model;

class AcademicYearModel extends Model
{
    protected $table         = 'academic_years';
    protected $primaryKey    = 'id';
    protected $returnType    = 'object';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'uuid', 'branch_id', 'name', 'start_date', 'end_date', 'is_active',
    ];
}
