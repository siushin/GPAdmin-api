<?php

namespace Modules\Base\Http\Controllers;

use Exception;
use Illuminate\Http\JsonResponse;
use Modules\Base\Enums\OperationActionEnum;
use Modules\Base\Models\Admin;
use Modules\Base\Models\AuditLog;
use Modules\Base\Models\GeneralLog;
use Modules\Base\Models\LoginLog;
use Modules\Base\Models\OperationLog;
use Siushin\LaravelTool\Attributes\ControllerName;
use Siushin\LaravelTool\Attributes\OperationAction;
use Siushin\Util\Traits\ParamTool;

#[ControllerName('管理员列表')]
class AdminController extends Controller
{
    use ParamTool;

    /**
     * 管理员列表（全部）
     * @return JsonResponse
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::list)]
    public function list(): JsonResponse
    {
        $params = request()->all();
        return success(Admin::getAllData($params));
    }

    /**
     * 管理员列表
     * @return JsonResponse
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::index)]
    public function index(): JsonResponse
    {
        $params = request()->all();
        return success(Admin::getPageData($params));
    }

    /**
     * 添加管理员
     * @return JsonResponse
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::add)]
    public function add(): JsonResponse
    {
        $params = request()->all();
        return success(Admin::addAdmin($params));
    }

    /**
     * 更新管理员
     * @return JsonResponse
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::update)]
    public function update(): JsonResponse
    {
        $params = request()->all();
        return success(Admin::updateAdmin($params));
    }

    /**
     * 删除管理员
     * @return JsonResponse
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::delete)]
    public function delete(): JsonResponse
    {
        $params = trimParam(request()->only(['account_id']));
        return success(Admin::deleteAdmin($params));
    }

    /**
     * 获取管理员详情
     * @return JsonResponse
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::detail)]
    public function getDetail(): JsonResponse
    {
        $params = trimParam(request()->only(['account_id']));
        return success(Admin::getAdminDetail($params));
    }

    /**
     * 获取管理员日志（支持多种日志类型）
     * @return JsonResponse
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::list)]
    public function getLogs(): JsonResponse
    {
        $params = request()->all();
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
     * 批量移除员工（从公司移除）
     * @return JsonResponse
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::batchDelete)]
    public function batchRemoveFromCompany(): JsonResponse
    {
        $params = request()->all();
        return success(Admin::batchRemoveFromCompany($params));
    }

    /**
     * 获取账号所属部门列表
     * @return JsonResponse
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::list)]
    public function getAccountDepartments(): JsonResponse
    {
        $params = trimParam(request()->only(['account_id']));
        return success(Admin::getAccountDepartments($params));
    }
}

