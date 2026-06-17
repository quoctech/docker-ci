<?php

namespace Modules\SystemAdmin\Controllers;

use App\Controllers\BaseController;

class AdminPageController extends BaseController
{
    public function dashboard(): string
    {
        return view('Modules\SystemAdmin\Views\dashboard');
    }

    public function modules(): string
    {
        return view('Modules\SystemAdmin\Views\modules/index');
    }

    public function configs(): string
    {
        return view('Modules\SystemAdmin\Views\configs/index');
    }

    public function users(): string
    {
        return view('Modules\SystemAdmin\Views\users/index');
    }

}
