<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// Health check
$routes->get('/', 'Home::index');

// ==========================================================================
// Admin Web Pages (render views, auth check ở client-side)
// ==========================================================================

$routes->group('admin', ['namespace' => ''], function ($routes) {
    $routes->get('login',        '\Modules\Auth\Controllers\AuthPageController::login');
    $routes->get('profile',      '\Modules\Auth\Controllers\AuthPageController::profile');
    $routes->get('/',            '\Modules\SystemAdmin\Controllers\AdminPageController::dashboard');
    $routes->get('modules',      '\Modules\SystemAdmin\Controllers\AdminPageController::modules');
    $routes->get('configs',      '\Modules\SystemAdmin\Controllers\AdminPageController::configs');
    $routes->get('users',        '\Modules\SystemAdmin\Controllers\AdminPageController::users');
    $routes->get('subscriptions', '\Modules\VortexEngine\Controllers\VortexEnginePageController::subscriptions', ['filter' => 'module_redirect:vortex-engine']);
    $routes->get('system-logs',  '\Modules\SystemLog\Controllers\SystemLogPageController::index');
});

// Classroom module — page routes (bị chặn nếu module tắt)
$routes->group('admin', ['namespace' => '', 'filter' => 'module_redirect:classroom'], function ($routes) {
    $routes->get('classrooms', '\Modules\Classroom\Controllers\ClassroomPageController::index');
    $routes->get('classrooms/students', '\Modules\Classroom\Controllers\ClassroomPageController::students');
    $routes->get('classrooms/(:segment)', '\Modules\Classroom\Controllers\ClassroomPageController::detail/$1');
    $routes->get('classrooms/(:segment)/assignments/(:segment)', '\Modules\Classroom\Controllers\ClassroomPageController::assignment/$1/$2');
    $routes->get('my-classrooms', '\Modules\Classroom\Controllers\ClassroomPageController::myClassrooms');
    $routes->get('my-classrooms/(:segment)', '\Modules\Classroom\Controllers\ClassroomPageController::myClassroomDetail/$1');
});

// School Management module — page routes
$routes->group('admin', ['namespace' => '', 'filter' => 'module_redirect:school-management'], function ($routes) {
    $routes->get('school-management/centers',              '\Modules\SchoolManagement\Controllers\SchoolManagementPageController::centers');
    $routes->get('school-management/branches',             '\Modules\SchoolManagement\Controllers\SchoolManagementPageController::branches');
    $routes->get('school-management/branches/(:segment)',  '\Modules\SchoolManagement\Controllers\SchoolManagementPageController::branchDetail/$1');
    $routes->get('school-management/rooms',                '\Modules\SchoolManagement\Controllers\SchoolManagementPageController::rooms');
});

// Role Management module — page routes
$routes->group('admin', ['namespace' => '', 'filter' => 'module_redirect:role-management'], function ($routes) {
    $routes->get('role-management', '\Modules\RoleManagement\Controllers\RoleManagementPageController::index');
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
        $routes->get('my-modules', '\Modules\Auth\Controllers\AuthController::myModules');
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


        // Server Status
        $routes->get('server-status', '\Modules\SystemAdmin\Controllers\ServerStatusController::index');


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

    });

    // ------------------------------------------------------------------
    // Classroom Module (Authenticated — controllers enforce role/ownership)
    // ------------------------------------------------------------------
    $routes->group('', ['filter' => 'auth'], function ($routes) {

        // Teacher: classroom CRUD
        $routes->get('classrooms', '\Modules\Classroom\Controllers\ClassroomController::index');
        $routes->get('classrooms/students', '\Modules\Classroom\Controllers\ClassroomMemberController::allStudents');
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
        $routes->get('submissions/(:segment)/images/(:num)', '\Modules\Classroom\Controllers\SubmissionController::image/$1/$2');

        // Student: my classrooms
        $routes->get('my-classrooms', '\Modules\Classroom\Controllers\ClassroomMemberController::myClassrooms');
        $routes->get('my-classrooms/(:segment)', '\Modules\Classroom\Controllers\ClassroomMemberController::show/$1');
        $routes->get('my-classrooms/(:segment)/assignments', '\Modules\Classroom\Controllers\AssignmentController::index/$1');
        $routes->delete('my-classrooms/(:segment)/leave', '\Modules\Classroom\Controllers\ClassroomMemberController::leave/$1');

        // AwesomeBar Search — mọi role đã đăng nhập đều dùng được, controller tự filter theo role
        $routes->get('admin/search', '\Modules\AwesomeBar\Controllers\AdminAwesomeBarController::search');

        // School Management — Center, Branch & Room CRUD
        $routes->group('school-management', ['filter' => 'module_check:school-management'], function ($routes) {
            $routes->get('centers',             '\Modules\SchoolManagement\Controllers\AdminCenterController::index');
            $routes->post('centers',            '\Modules\SchoolManagement\Controllers\AdminCenterController::create');
            $routes->get('centers/(:segment)',  '\Modules\SchoolManagement\Controllers\AdminCenterController::show/$1');
            $routes->put('centers/(:segment)',  '\Modules\SchoolManagement\Controllers\AdminCenterController::update/$1');
            $routes->delete('centers/(:segment)', '\Modules\SchoolManagement\Controllers\AdminCenterController::delete/$1');

            $routes->get('branches',             '\Modules\SchoolManagement\Controllers\AdminBranchController::index');
            $routes->post('branches',            '\Modules\SchoolManagement\Controllers\AdminBranchController::create');
            $routes->get('branches/(:segment)',  '\Modules\SchoolManagement\Controllers\AdminBranchController::show/$1');
            $routes->put('branches/(:segment)',  '\Modules\SchoolManagement\Controllers\AdminBranchController::update/$1');
            $routes->delete('branches/(:segment)', '\Modules\SchoolManagement\Controllers\AdminBranchController::delete/$1');

            $routes->get('rooms',             '\Modules\SchoolManagement\Controllers\AdminRoomController::index');
            $routes->post('rooms',            '\Modules\SchoolManagement\Controllers\AdminRoomController::create');
            $routes->get('rooms/(:segment)',  '\Modules\SchoolManagement\Controllers\AdminRoomController::show/$1');
            $routes->put('rooms/(:segment)',  '\Modules\SchoolManagement\Controllers\AdminRoomController::update/$1');
            $routes->delete('rooms/(:segment)', '\Modules\SchoolManagement\Controllers\AdminRoomController::delete/$1');
        });

        // Role Management — Roles CRUD + apply to user
        $routes->group('role-management', ['filter' => 'module_check:role-management'], function ($routes) {
            $routes->get('roles',                              '\Modules\RoleManagement\Controllers\AdminRoleController::index');
            $routes->post('roles',                             '\Modules\RoleManagement\Controllers\AdminRoleController::create');
            $routes->get('roles/(:segment)',                   '\Modules\RoleManagement\Controllers\AdminRoleController::show/$1');
            $routes->put('roles/(:segment)',                   '\Modules\RoleManagement\Controllers\AdminRoleController::update/$1');
            $routes->delete('roles/(:segment)',                '\Modules\RoleManagement\Controllers\AdminRoleController::delete/$1');
            $routes->get('roles/(:segment)/modules',           '\Modules\RoleManagement\Controllers\AdminRoleController::getModules/$1');
            $routes->put('roles/(:segment)/modules',           '\Modules\RoleManagement\Controllers\AdminRoleController::setModules/$1');
            $routes->post('roles/(:segment)/apply-to-user',   '\Modules\RoleManagement\Controllers\AdminRoleController::applyToUser/$1');
        });

        // VortexEngine — Subscription Management (super_admin + workspace_admin with permission)
        $routes->group('admin/subscriptions', ['filter' => 'module_check:vortex-engine'], function ($routes) {
            $routes->post('activate', '\Modules\VortexEngine\Controllers\AdminSubscriptionController::activate');
            $routes->put('(:num)', '\Modules\VortexEngine\Controllers\AdminSubscriptionController::updateSubscription/$1');
            $routes->get('list', '\Modules\VortexEngine\Controllers\AdminSubscriptionController::listSubscriptions');
            $routes->get('students', '\Modules\VortexEngine\Controllers\AdminSubscriptionController::students');
            $routes->get('packages', '\Modules\VortexEngine\Controllers\AdminSubscriptionController::packages');
            $routes->get('packages/all', '\Modules\VortexEngine\Controllers\AdminSubscriptionController::allPackages');
            $routes->post('packages', '\Modules\VortexEngine\Controllers\AdminSubscriptionController::createPackage');
            $routes->put('packages/(:segment)/toggle', '\Modules\VortexEngine\Controllers\AdminSubscriptionController::togglePackage/$1');
            $routes->put('packages/(:segment)', '\Modules\VortexEngine\Controllers\AdminSubscriptionController::updatePackage/$1');
        });
    });
});
