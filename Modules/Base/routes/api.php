<?php

use Illuminate\Support\Facades\Route;
use Modules\Base\Http\Controllers\AccountController;
use Modules\Base\Http\Controllers\AdminController;
use Modules\Base\Http\Controllers\AnnouncementController;
use Modules\Base\Http\Controllers\AppController;
use Modules\Base\Http\Controllers\CompanyController;
use Modules\Base\Http\Controllers\DepartmentController;
use Modules\Base\Http\Controllers\DictionaryCategoryController;
use Modules\Base\Http\Controllers\DictionaryController;
use Modules\Base\Http\Controllers\FileController;
use Modules\Base\Http\Controllers\LogController;
use Modules\Base\Http\Controllers\MenuController;
use Modules\Base\Http\Controllers\MessageController;
use Modules\Base\Http\Controllers\NotificationReadController;
use Modules\Base\Http\Controllers\OrganizationController;
use Modules\Base\Http\Controllers\PositionController;
use Modules\Base\Http\Controllers\PostController;
use Modules\Base\Http\Controllers\RoleController;
use Modules\Base\Http\Controllers\SystemNotificationController;
use Modules\Base\Http\Controllers\UserController;

// 公共路由
Route::get('/dictionary/getTplFile', [DictionaryController::class, 'getTplFile']);  // 下载数据字典模板

// 不需要认证的接口
Route::post('/login/account', [AccountController::class, 'login']);
Route::post('/login/code', [AccountController::class, 'loginByCode']);
Route::post('/resetPassword', [AccountController::class, 'resetPassword']);
Route::post('/register', [AccountController::class, 'register']);

// API鉴权 通用接口（不区分用户类型）
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/currentUser', [AccountController::class, 'getCurrentUserInfo']);
    Route::post('/refreshToken', [AccountController::class, 'refreshToken']);
    Route::post('/changePassword', [AccountController::class, 'changePassword']);
    Route::post('/logout', [AccountController::class, 'logout']);
    Route::post('/getUserMenus', [MenuController::class, 'getUserMenus']);
});

// API鉴权 管理员 路由组
Route::middleware(['auth:sanctum'])->prefix('/admin')->group(function () {
    // 管理员列表
    Route::post('/admin/index', [AdminController::class, 'index']);
    Route::post('/admin/add', [AdminController::class, 'add']);
    Route::post('/admin/update', [AdminController::class, 'update']);
    Route::post('/admin/delete', [AdminController::class, 'delete']);
    Route::post('/admin/getDetail', [AdminController::class, 'getDetail']);
    Route::post('/admin/getLogs', [AdminController::class, 'getLogs']);

    // 用户管理
    Route::post('/user/index', [UserController::class, 'index']);
    Route::post('/user/add', [UserController::class, 'add']);
    Route::post('/user/update', [UserController::class, 'update']);
    Route::post('/user/delete', [UserController::class, 'delete']);
    Route::post('/user/getDetail', [UserController::class, 'getDetail']);
    Route::post('/user/getLogs', [UserController::class, 'getLogs']);
    Route::post('/user/audit', [UserController::class, 'audit']);
    Route::post('/user/batchAudit', [UserController::class, 'batchAudit']);
    Route::post('/user/batchDelete', [UserController::class, 'batchDelete']);

    // 文件管理
    Route::post('/file/upload', [FileController::class, 'upload']);     // 上传文件
    Route::post('/file/delete', [FileController::class, 'delete']);     // 删除文件
    Route::post('/file/cleanup', [FileController::class, 'cleanup']);   // 清空文件

    // 组织架构管理
    // 公司管理
    Route::post('/company/list', [CompanyController::class, 'list']);
    Route::post('/company/index', [CompanyController::class, 'index']);
    Route::post('/company/add', [CompanyController::class, 'add']);
    Route::post('/company/update', [CompanyController::class, 'update']);
    Route::post('/company/delete', [CompanyController::class, 'delete']);
    // 部门管理
    Route::post('/department/list', [DepartmentController::class, 'list']);
    Route::post('/department/index', [DepartmentController::class, 'index']);
    Route::post('/department/add', [DepartmentController::class, 'add']);
    Route::post('/department/update', [DepartmentController::class, 'update']);
    Route::post('/department/delete', [DepartmentController::class, 'delete']);
    // 职位管理
    Route::post('/position/list', [PositionController::class, 'list']);
    Route::post('/position/index', [PositionController::class, 'index']);
    Route::post('/position/add', [PositionController::class, 'add']);
    Route::post('/position/update', [PositionController::class, 'update']);
    Route::post('/position/delete', [PositionController::class, 'delete']);
    // 岗位管理
    Route::post('/post/index', [PostController::class, 'index']);
    Route::post('/post/add', [PostController::class, 'add']);
    Route::post('/post/update', [PostController::class, 'update']);
    Route::post('/post/delete', [PostController::class, 'delete']);

    // 菜单管理
    // 角色管理
    Route::post('/role/index', [RoleController::class, 'index']);
    Route::post('/role/add', [RoleController::class, 'add']);
    Route::post('/role/update', [RoleController::class, 'update']);
    Route::post('/role/delete', [RoleController::class, 'delete']);
    // 菜单管理
    Route::post('/menu/index', [MenuController::class, 'index']);
    Route::post('/menu/tree', [MenuController::class, 'tree']);
    Route::post('/menu/add', [MenuController::class, 'add']);
    Route::post('/menu/update', [MenuController::class, 'update']);
    Route::post('/menu/delete', [MenuController::class, 'delete']);

    // 日志管理
    Route::post('/log/generalLog', [LogController::class, 'generalLog']);  // 常规日志列表
    Route::post('/log/operationLog', [LogController::class, 'operationLog']);  // 操作日志列表
    Route::post('/log/auditLog', [LogController::class, 'auditLog']);  // 审计日志列表
    Route::post('/log/loginLog', [LogController::class, 'loginLog']);  // 登录日志列表
    Route::post('/log/getGeneralLogSearchData', [LogController::class, 'getGeneralLogSearchData']);  // 常规日志搜索数据
    Route::post('/log/getOperationLogSearchData', [LogController::class, 'getOperationLogSearchData']);  // 操作日志搜索数据
    Route::post('/log/getAuditLogSearchData', [LogController::class, 'getAuditLogSearchData']);  // 审计日志搜索数据
    Route::post('/log/getLoginLogSearchData', [LogController::class, 'getLoginLogSearchData']);  // 登录日志搜索数据

    // 应用管理
    Route::post('/app/myApps', [AppController::class, 'getMyApps']);  // 获取我的应用列表

    // 通知管理
    // 系统通知管理
    Route::post('/systemNotification/index', [SystemNotificationController::class, 'index']);
    Route::post('/systemNotification/add', [SystemNotificationController::class, 'add']);
    Route::post('/systemNotification/update', [SystemNotificationController::class, 'update']);
    Route::post('/systemNotification/delete', [SystemNotificationController::class, 'delete']);
    // 站内信管理
    Route::post('/message/index', [MessageController::class, 'index']);
    Route::post('/message/add', [MessageController::class, 'add']);
    Route::post('/message/update', [MessageController::class, 'update']);
    Route::post('/message/delete', [MessageController::class, 'delete']);
    // 公告管理
    Route::post('/announcement/index', [AnnouncementController::class, 'index']);
    Route::post('/announcement/add', [AnnouncementController::class, 'add']);
    Route::post('/announcement/update', [AnnouncementController::class, 'update']);
    Route::post('/announcement/delete', [AnnouncementController::class, 'delete']);
    // 通知查看记录管理
    Route::post('/notificationRead/index', [NotificationReadController::class, 'index']);

    // 系统管理
    // 数据字典分类管理
    Route::post('/DictionaryCategory/index', [DictionaryCategoryController::class, 'index']);
    // 数据字典管理
    Route::post('/dictionary/index', [DictionaryController::class, 'index']);
    Route::post('/dictionary/list', [DictionaryController::class, 'list']);
    Route::post('/dictionary/add', [DictionaryController::class, 'add']);
    Route::post('/dictionary/update', [DictionaryController::class, 'update']);
    Route::post('/dictionary/delete', [DictionaryController::class, 'delete']);
    Route::post('/dictionary/batchDelete', [DictionaryController::class, 'batchDelete']);
    Route::get('/dictionary/getTplFile', [DictionaryController::class, 'getTplFile']);
    Route::post('/dictionary/getPidData', [DictionaryController::class, 'getPidData']);
    // 数据字典（树状）
    Route::post('/organization/getOrganizationTypeList', [OrganizationController::class, 'getOrganizationTypeList']);
    Route::post('/organization/addOrganizationType', [OrganizationController::class, 'addOrganizationType']);
    Route::post('/organization/updateOrganizationType', [OrganizationController::class, 'updateOrganizationType']);
    Route::post('/organization/deleteOrganizationType', [OrganizationController::class, 'deleteOrganizationType']);
    Route::post('/organization/getFullTreeDataForHtml', [OrganizationController::class, 'getFullTreeDataForHtml']);
    Route::post('/organization/index', [OrganizationController::class, 'index']);
    Route::post('/organization/add', [OrganizationController::class, 'add']);
    Route::post('/organization/update', [OrganizationController::class, 'update']);
    Route::post('/organization/delete', [OrganizationController::class, 'delete']);
    Route::post('/organization/move', [OrganizationController::class, 'move']);
});
