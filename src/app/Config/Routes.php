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
    $routes->get('subscriptions', '\Modules\SystemAdmin\Controllers\AdminPageController::subscriptions', ['filter' => 'module_redirect:vortex-engine']);
    $routes->get('system-logs', '\Modules\SystemAdmin\Controllers\AdminPageController::systemLogs');
});

// Classroom module — page routes
$routes->group('admin', ['namespace' => ''], function ($routes) {
    $routes->get('classrooms', '\Modules\Classroom\Controllers\ClassroomPageController::index');
    $routes->get('classrooms/(:segment)', '\Modules\Classroom\Controllers\ClassroomPageController::detail/$1');
    $routes->get('classrooms/(:segment)/assignments/(:segment)', '\Modules\Classroom\Controllers\ClassroomPageController::assignment/$1/$2');
    $routes->get('my-classrooms', '\Modules\Classroom\Controllers\ClassroomPageController::myClassrooms');
    $routes->get('my-classrooms/(:segment)', '\Modules\Classroom\Controllers\ClassroomPageController::myClassroomDetail/$1');
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
        $routes->put('modules/(:num)/toggle', '\Modules\SystemAdmin\Controllers\ModuleController::toggle/$1');
        $routes->post('modules/scan', '\Modules\SystemAdmin\Controllers\ModuleController::scan');
        $routes->post('modules/sync-cache', '\Modules\SystemAdmin\Controllers\ModuleController::syncCache');
        $routes->post('modules/(:segment)/install', '\Modules\SystemAdmin\Controllers\ModuleController::install/$1');

        // Site Configs
        $routes->get('configs', '\Modules\SystemAdmin\Controllers\SiteConfigController::index');
        $routes->get('configs/(:any)', '\Modules\SystemAdmin\Controllers\SiteConfigController::show/$1');
        $routes->post('configs', '\Modules\SystemAdmin\Controllers\SiteConfigController::create');
        $routes->put('configs/(:any)', '\Modules\SystemAdmin\Controllers\SiteConfigController::update/$1');
        $routes->delete('configs/(:any)', '\Modules\SystemAdmin\Controllers\SiteConfigController::delete/$1');

        // VortexEngine — Subscription Management
        $routes->group('subscriptions', ['filter' => 'module_check:vortex-engine'], function ($routes) {
            $routes->post('activate', '\Modules\VortexEngine\Controllers\AdminSubscriptionController::activate');
            $routes->put('(:num)', '\Modules\VortexEngine\Controllers\AdminSubscriptionController::updateSubscription/$1');
            $routes->get('list', '\Modules\VortexEngine\Controllers\AdminSubscriptionController::listSubscriptions');
            $routes->get('packages', '\Modules\VortexEngine\Controllers\AdminSubscriptionController::packages');
            $routes->get('packages/all', '\Modules\VortexEngine\Controllers\AdminSubscriptionController::allPackages');
            $routes->post('packages', '\Modules\VortexEngine\Controllers\AdminSubscriptionController::createPackage');
            $routes->put('packages/(:segment)/toggle', '\Modules\VortexEngine\Controllers\AdminSubscriptionController::togglePackage/$1');
            $routes->put('packages/(:segment)', '\Modules\VortexEngine\Controllers\AdminSubscriptionController::updatePackage/$1');
        });

        // Server Status
        $routes->get('server-status', '\Modules\SystemAdmin\Controllers\ServerStatusController::index');

        // Search (Awesome Bar)
        $routes->get('search', '\Modules\AwesomeBar\Controllers\AdminAwesomeBarController::search');

        // System Log
        $routes->get('system-logs/stats', '\Modules\SystemLog\Controllers\AdminSystemLogController::stats');
        $routes->get('system-logs/(:num)', '\Modules\SystemLog\Controllers\AdminSystemLogController::show/$1');
        $routes->get('system-logs', '\Modules\SystemLog\Controllers\AdminSystemLogController::index');
        $routes->post('system-logs/mark-seen', '\Modules\SystemLog\Controllers\AdminSystemLogController::markAllSeen');
        $routes->delete('system-logs/(:num)', '\Modules\SystemLog\Controllers\AdminSystemLogController::delete/$1');
        $routes->delete('system-logs', '\Modules\SystemLog\Controllers\AdminSystemLogController::clearAll');

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

        // User Module Permissions
        $routes->get('users/(:segment)/modules', '\Modules\SystemAdmin\Controllers\UserModulePermissionController::getUserModules/$1');
        $routes->put('users/(:segment)/modules', '\Modules\SystemAdmin\Controllers\UserModulePermissionController::setUserModules/$1');
    });

    // ------------------------------------------------------------------
    // Classroom Module (Authenticated — controllers enforce role/ownership)
    // ------------------------------------------------------------------
    $routes->group('', ['filter' => 'auth'], function ($routes) {

        // Teacher: classroom CRUD
        $routes->get('classrooms', '\Modules\Classroom\Controllers\ClassroomController::index');
        $routes->post('classrooms', '\Modules\Classroom\Controllers\ClassroomController::create');
        $routes->post('classrooms/join', '\Modules\Classroom\Controllers\ClassroomMemberController::join');
        $routes->get('classrooms/(:segment)', '\Modules\Classroom\Controllers\ClassroomController::show/$1');
        $routes->put('classrooms/(:segment)', '\Modules\Classroom\Controllers\ClassroomController::update/$1');
        $routes->delete('classrooms/(:segment)', '\Modules\Classroom\Controllers\ClassroomController::delete/$1');
        $routes->put('classrooms/(:segment)/toggle-approval', '\Modules\Classroom\Controllers\ClassroomController::toggleApproval/$1');

        // Teacher: members management
        $routes->get('classrooms/(:segment)/members', '\Modules\Classroom\Controllers\ClassroomMemberController::index/$1');
        $routes->put('classrooms/(:segment)/members/(:num)/approve', '\Modules\Classroom\Controllers\ClassroomMemberController::approve/$1/$2');
        $routes->put('classrooms/(:segment)/members/(:num)/reject', '\Modules\Classroom\Controllers\ClassroomMemberController::reject/$1/$2');
        $routes->delete('classrooms/(:segment)/members/(:num)', '\Modules\Classroom\Controllers\ClassroomMemberController::remove/$1/$2');

        // Assignments (teacher creates/manages, student reads)
        $routes->get('classrooms/(:segment)/assignments', '\Modules\Classroom\Controllers\AssignmentController::index/$1');
        $routes->post('classrooms/(:segment)/assignments', '\Modules\Classroom\Controllers\AssignmentController::create/$1');
        $routes->get('assignments/(:segment)', '\Modules\Classroom\Controllers\AssignmentController::show/$1');
        $routes->put('assignments/(:segment)', '\Modules\Classroom\Controllers\AssignmentController::update/$1');
        $routes->delete('assignments/(:segment)', '\Modules\Classroom\Controllers\AssignmentController::delete/$1');

        // Submissions (student submits, teacher grades)
        $routes->get('assignments/(:segment)/file', '\Modules\Classroom\Controllers\AssignmentController::downloadFile/$1');
        $routes->post('assignments/(:segment)/submit', '\Modules\Classroom\Controllers\SubmissionController::submit/$1');
        $routes->get('assignments/(:segment)/submissions', '\Modules\Classroom\Controllers\SubmissionController::index/$1');
        $routes->get('assignments/(:segment)/my-submission', '\Modules\Classroom\Controllers\SubmissionController::mySubmission/$1');
        $routes->put('submissions/(:segment)/grade', '\Modules\Classroom\Controllers\SubmissionController::grade/$1');

        // Student: my classrooms
        $routes->get('my-classrooms', '\Modules\Classroom\Controllers\ClassroomMemberController::myClassrooms');
        $routes->get('my-classrooms/(:segment)', '\Modules\Classroom\Controllers\ClassroomMemberController::show/$1');
        $routes->get('my-classrooms/(:segment)/assignments', '\Modules\Classroom\Controllers\AssignmentController::index/$1');
        $routes->delete('my-classrooms/(:segment)/leave', '\Modules\Classroom\Controllers\ClassroomMemberController::leave/$1');
    });
});
