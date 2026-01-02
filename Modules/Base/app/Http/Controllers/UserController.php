<?php

namespace Modules\Base\Http\Controllers;

use Exception;
use Illuminate\Http\JsonResponse;
use Modules\Base\Enums\OperationActionEnum;
use Modules\Base\Models\AuditLog;
use Modules\Base\Models\GeneralLog;
use Modules\Base\Models\LoginLog;
use Modules\Base\Models\OperationLog;
use Modules\Base\Models\User;
use Siushin\LaravelTool\Attributes\OperationAction;
use Siushin\Util\Traits\ParamTool;
use Throwable;

/**
 * 控制器：用户管理
 * @module 用户管理
 */
class UserController extends Controller
{
    use ParamTool;

    /**
     * 获取用户列表
     * @return JsonResponse
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::index)]
    public function index(): JsonResponse
    {
        $params = trimParam(request()->all());
        return success(User::getPageData($params));
    }

    /**
     * 添加用户
     * @return JsonResponse
     * @throws Exception|Throwable
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::add)]
    public function add(): JsonResponse
    {
        $params = trimParam(request()->all());
        return success(User::addUser($params));
    }

    /**
     * 更新用户
     * @return JsonResponse
     * @throws Exception|Throwable
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::update)]
    public function update(): JsonResponse
    {
        $params = trimParam(request()->all());
        return success(User::updateUser($params));
    }

    /**
     * 删除用户
     * @return JsonResponse
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::delete)]
    public function delete(): JsonResponse
    {
        $params = trimParam(request()->only(['account_id']));
        return success(User::deleteUser($params));
    }

    /**
     * 获取用户详情
     * @return JsonResponse
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::detail)]
    public function getDetail(): JsonResponse
    {
        $params = trimParam(request()->only(['account_id']));
        return success(User::getUserDetail($params));
    }

    /**
     * 获取用户日志（支持多种日志类型）
     * @return JsonResponse
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::list)]
    public function getLogs(): JsonResponse
    {
        $params = trimParam(request()->all());
        $logType = $params['log_type'] ?? 'general'; // general, operation, audit, login

        // 必须提供 account_id
        if (empty($params['account_id'])) {
            throw_exception('缺少 account_id 参数');
        }

        $accountId = $params['account_id'];
        $requestParams = array_merge($params, ['account_id' => $accountId]);

        return match ($logType) {
            'general' => success(GeneralLog::getPageData($requestParams)),
            'operation' => success(OperationLog::getPageData($requestParams)),
            'audit' => success(AuditLog::getPageData($requestParams)),
            'login' => success(LoginLog::getPageData($requestParams)),
            default => throw_exception('不支持的日志类型: ' . $logType),
        };
    }

    /**
     * 审核用户
     * @return JsonResponse
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::update)]
    public function audit(): JsonResponse
    {
        $params = trimParam(request()->all());
        return success(User::auditUser($params));
    }

    /**
     * 批量审核用户
     * @return JsonResponse
     * @throws Exception|Throwable
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::batchUpdate)]
    public function batchAudit(): JsonResponse
    {
        $params = trimParam(request()->all());
        return success(User::batchAuditUser($params));
    }

    /**
     * 批量删除用户
     * @return JsonResponse
     * @throws Exception|Throwable
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::batchDelete)]
    public function batchDelete(): JsonResponse
    {
        $params = trimParam(request()->all());
        return success(User::batchDeleteUser($params));
    }
}

