<?php

namespace Modules\VortexEngine\Controllers;

use App\Controllers\BaseController;

class VortexEnginePageController extends BaseController
{
    public function subscriptions(): string
    {
        return view('Modules\VortexEngine\Views\subscriptions/index');
    }
}
