<?php

namespace Modules\Auth\Controllers;

use App\Controllers\BaseController;

class AuthPageController extends BaseController
{
    public function login(): string
    {
        return view('Modules\Auth\Views\login');
    }

    public function profile(): string
    {
        return view('Modules\Auth\Views\profile');
    }
}
