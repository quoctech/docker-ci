<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// Health check
$routes->get('/', 'Home::index');

// ==========================================================================
// Admin Web Pages (render views, auth check ở client-side)
// ==========================================================================

$routes->group('admin', ['namespace' => ''], function ($routes) {
    $routes->get('login', '\Modules\SystemAdmin\Controllers\AdminPageController::login');
    $routes->get('/', '\Modules\SystemAdmin\Controllers\AdminPageController::dashboard');
    $routes->get('modules', '\Modules\SystemAdmin\Controllers\AdminPageController::modules');
    $routes->get('configs', '\Modules\SystemAdmin\Controllers\AdminPageController::configs');
    $routes->get('users', '\Modules\SystemAdmin\Controllers\AdminPageController::users');
    $routes->get('profile', '\Modules\SystemAdmin\Controllers\AdminPageController::profile');
});

// Serve uploaded files (avatar)
$routes->get('uploads/avatars/(:any)', 'UploadController::avatar/$1');

// ==========================================================================
// API Routes
// ==========================================================================

$routes->group('api', ['namespace' => ''], function ($routes) {

    // ------------------------------------------------------------------
    // Auth Module (Public)
    // ------------------------------------------------------------------
    $routes->group('auth', function ($routes) {
        $routes->post('register', '\Modules\Auth\Controllers\AuthController::register');
        $routes->post('login', '\Modules\Auth\Controllers\AuthController::login');
        $routes->post('refresh', '\Modules\Auth\Controllers\AuthController::refresh');
    });

    // ------------------------------------------------------------------
    // Auth Module (Authenticated)
    // ------------------------------------------------------------------
    $routes->group('auth', ['filter' => 'auth'], function ($routes) {
        $routes->get('me', '\Modules\Auth\Controllers\AuthController::me');
        $routes->post('logout', '\Modules\Auth\Controllers\AuthController::logout');
        $routes->post('logout-all', '\Modules\Auth\Controllers\AuthController::logoutAll');
        $routes->put('change-password', '\Modules\Auth\Controllers\AuthController::changePassword');
    });

    // ------------------------------------------------------------------
    // System Admin Module (Super Admin only)
    // ------------------------------------------------------------------
    $routes->group('admin', ['filter' => 'auth:super_admin'], function ($routes) {

        // Module Management
        $routes->get('modules', '\Modules\SystemAdmin\Controllers\ModuleController::index');
        $routes->put('modules/(:segment)/toggle', '\Modules\SystemAdmin\Controllers\ModuleController::toggle/$1');
        $routes->post('modules/sync-cache', '\Modules\SystemAdmin\Controllers\ModuleController::syncCache');

        // Site Configs
        $routes->get('configs', '\Modules\SystemAdmin\Controllers\SiteConfigController::index');
        $routes->get('configs/(:any)', '\Modules\SystemAdmin\Controllers\SiteConfigController::show/$1');
        $routes->post('configs', '\Modules\SystemAdmin\Controllers\SiteConfigController::create');
        $routes->put('configs/(:any)', '\Modules\SystemAdmin\Controllers\SiteConfigController::update/$1');
        $routes->delete('configs/(:any)', '\Modules\SystemAdmin\Controllers\SiteConfigController::delete/$1');

        // User Management
        $routes->get('users', '\Modules\SystemAdmin\Controllers\UserManagementController::index');
        $routes->get('users/(:segment)', '\Modules\SystemAdmin\Controllers\UserManagementController::show/$1');
        $routes->post('users', '\Modules\SystemAdmin\Controllers\UserManagementController::create');
        $routes->put('users/(:segment)', '\Modules\SystemAdmin\Controllers\UserManagementController::update/$1');
        $routes->put('users/(:segment)/status', '\Modules\SystemAdmin\Controllers\UserManagementController::updateStatus/$1');
        $routes->put('users/(:segment)/role', '\Modules\SystemAdmin\Controllers\UserManagementController::updateRole/$1');
        $routes->put('users/(:segment)/reset-password', '\Modules\SystemAdmin\Controllers\UserManagementController::resetPassword/$1');
        $routes->post('users/(:segment)/avatar', '\Modules\SystemAdmin\Controllers\UserManagementController::uploadAvatar/$1');
        $routes->delete('users/(:segment)/avatar', '\Modules\SystemAdmin\Controllers\UserManagementController::deleteAvatar/$1');
    });
});
