<?php

namespace Modules\Classroom\Controllers;

use App\Controllers\BaseController;

class ClassroomPageController extends BaseController
{
    public function index(): string
    {
        return view('Modules\Classroom\Views\classrooms/index');
    }

    public function detail(string $uuid): string
    {
        return view('Modules\Classroom\Views\classrooms/detail', ['classroomUuid' => $uuid]);
    }

    public function assignment(string $classroomUuid, string $assignmentUuid): string
    {
        return view('Modules\Classroom\Views\classrooms/assignment', [
            'classroomUuid'  => $classroomUuid,
            'assignmentUuid' => $assignmentUuid,
        ]);
    }

    public function myClassrooms(): string
    {
        return view('Modules\Classroom\Views\my_classrooms/index');
    }

    public function myClassroomDetail(string $uuid): string
    {
        return view('Modules\Classroom\Views\my_classrooms/detail', ['classroomUuid' => $uuid]);
    }
}
