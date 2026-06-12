<?php

namespace Modules\SystemAdmin\Controllers;

use App\Controllers\BaseController;

/**
 * AdminPageController - Serve các trang HTML cho admin panel.
 *
 * Controller này CHỈ render view. Toàn bộ logic business
 * được xử lý qua API (fetch từ frontend Alpine.js).
 *
 * Auth check thực hiện ở client-side (JS kiểm tra token trong localStorage).
 * Nếu không có token → redirect /admin/login.
 */
class AdminPageController extends BaseController
{
    /**
     * GET /admin/login
     * Trang đăng nhập admin.
     */
    public function login(): string
    {
        return view('admin/login');
    }

    /**
     * GET /admin
     * Trang dashboard (tổng quan).
     */
    public function dashboard(): string
    {
        return view('admin/dashboard');
    }

    /**
     * GET /admin/modules
     * Trang quản lý module (bật/tắt).
     */
    public function modules(): string
    {
        return view('admin/modules/index');
    }

    /**
     * GET /admin/configs
     * Trang cài đặt website (key-value config).
     */
    public function configs(): string
    {
        return view('admin/configs/index');
    }
}
