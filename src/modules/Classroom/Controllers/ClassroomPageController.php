<?php

namespace Modules\Classroom\Controllers;

use App\Controllers\BaseController;

/**
 * ClassroomPageController — Render views cho module lớp học.
 * Auth check thực hiện ở client-side (Alpine.js + JWT).
 */
class ClassroomPageController extends BaseController
{
    /** GET /admin/classrooms — giáo viên quản lý lớp */
    public function index(): string
    {
        return view('admin/classrooms/index');
    }

    /** GET /admin/classrooms/:uuid — chi tiết lớp (members + assignments) */
    public function detail(string $uuid): string
    {
        return view('admin/classrooms/detail', ['classroomUuid' => $uuid]);
    }

    /** GET /admin/classrooms/:cUuid/assignments/:aUuid — chấm bài */
    public function assignment(string $classroomUuid, string $assignmentUuid): string
    {
        return view('admin/classrooms/assignment', [
            'classroomUuid'  => $classroomUuid,
            'assignmentUuid' => $assignmentUuid,
        ]);
    }

    /** GET /admin/my-classrooms — học sinh xem lớp đã tham gia */
    public function myClassrooms(): string
    {
        return view('admin/my_classrooms/index');
    }

    /** GET /admin/my-classrooms/:uuid — học sinh xem bài tập của lớp */
    public function myClassroomDetail(string $uuid): string
    {
        return view('admin/my_classrooms/detail', ['classroomUuid' => $uuid]);
    }
}
