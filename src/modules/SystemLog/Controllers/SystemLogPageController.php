<?php

namespace Modules\SystemLog\Controllers;

use App\Controllers\BaseController;

class SystemLogPageController extends BaseController
{
    public function index(): string
    {
        return view('Modules\SystemLog\Views\system_logs/index');
    }
}
