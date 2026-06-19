<?php

namespace Modules\RoleManagement\Controllers;

use App\Controllers\BaseController;

class RoleManagementPageController extends BaseController
{
    public function index(): string
    {
        return view('Modules\RoleManagement\Views\roles/index');
    }
}
