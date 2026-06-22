<?php

namespace Modules\SchoolManagement\Controllers;

use App\Controllers\BaseController;

class SchoolManagementPageController extends BaseController
{
    public function branches(): string
    {
        return view('Modules\SchoolManagement\Views\branches/index');
    }

    public function branchDetail(string $uuid): string
    {
        return view('Modules\SchoolManagement\Views\branches/detail', ['branchUuid' => $uuid]);
    }

    public function rooms(): string
    {
        return view('Modules\SchoolManagement\Views\rooms/index');
    }

    public function centers(): string
    {
        return view('Modules\SchoolManagement\Views\centers/index');
    }

    public function academicYears(): string
    {
        return view('Modules\SchoolManagement\Views\academic_years/index');
    }
}
