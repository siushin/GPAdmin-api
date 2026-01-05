<?php

namespace Modules\Base\Http\Controllers;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Base\Enums\BrowserEnum;
use Modules\Base\Enums\DeviceTypeEnum;
use Modules\Base\Enums\HttpMethodEnum;
use Modules\Base\Enums\LogActionEnum;
use Modules\Base\Enums\OperatingSystemEnum;
use Modules\Base\Enums\OperationActionEnum;
use Modules\Base\Enums\ResourceTypeEnum;
use Modules\Base\Models\AuditLog;
use Modules\Base\Models\GeneralLog;
use Modules\Base\Models\LoginLog;
use Modules\Base\Models\OperationLog;
use Siushin\LaravelTool\Attributes\ControllerName;
use Siushin\LaravelTool\Attributes\OperationAction;
use Siushin\LaravelTool\Enums\RequestSourceEnum;

#[ControllerName('系统日志')]
class LogController extends Controller
{
    /**
     * 常规日志列表
     * @param Request $request
     * @return JsonResponse
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::index)]
    public function generalLog(Request $request): JsonResponse
    {
        $params = $request->all();
        return success(GeneralLog::getPageData($params));
    }

    /**
     * 操作日志列表
     * @param Request $request
     * @return JsonResponse
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::index)]
    public function operationLog(Request $request): JsonResponse
    {
        $params = $request->all();
        return success(OperationLog::getPageData($params));
    }

    /**
     * 审计日志列表
     * @param Request $request
     * @return JsonResponse
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::index)]
    public function auditLog(Request $request): JsonResponse
    {
        $params = $request->all();
        return success(AuditLog::getPageData($params));
    }

    /**
     * 登录日志列表
     * @param Request $request
     * @return JsonResponse
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::index)]
    public function loginLog(Request $request): JsonResponse
    {
        $params = $request->all();
        return success(LoginLog::getPageData($params));
    }

    /**
     * 获取搜索框数据：常规日志
     * @return JsonResponse
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::paramData)]
    public function getGeneralLogSearchData(): JsonResponse
    {
        // 操作类型列表（常规日志）
        $actionList = [];
        foreach (LogActionEnum::cases() as $case) {
            $actionList[] = [
                'label' => $case->value,
                'value' => $case->name,
            ];
        }

        // 访问来源列表
        $sourceTypeList = [];
        foreach (RequestSourceEnum::cases() as $case) {
            $sourceTypeList[] = [
                'label' => $case->value,
                'value' => $case->value,
            ];
        }

        return success([
            'action_type' => $actionList,
            'source_type' => $sourceTypeList,
        ]);
    }

    /**
     * 获取搜索框数据：操作日志
     * @return JsonResponse
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::paramData)]
    public function getOperationLogSearchData(): JsonResponse
    {
        // 模块名称列表（去重）
        $modules = OperationLog::query()
            ->distinct()
            ->whereNotNull('module')
            ->where('module', '!=', '')
            ->orderBy('module')
            ->pluck('module')
            ->toArray();

        $moduleList = [];
        foreach ($modules as $module) {
            $moduleList[] = [
                'label' => $module,
                'value' => $module,
            ];
        }

        // 操作类型列表
        $operationActionList = [];
        foreach (OperationActionEnum::cases() as $case) {
            $operationActionList[] = [
                'label' => $case->value,
                'value' => $case->value,
            ];
        }

        // HTTP方法列表
        $httpMethodList = [];
        foreach (HttpMethodEnum::cases() as $case) {
            $httpMethodList[] = [
                'label' => $case->value,
                'value' => $case->value,
            ];
        }

        // 响应状态码列表（去重）
        $responseCodes = OperationLog::query()
            ->distinct()
            ->whereNotNull('response_code')
            ->orderBy('response_code')
            ->pluck('response_code')
            ->toArray();

        $responseCodeList = [];
        foreach ($responseCodes as $code) {
            $responseCodeList[] = [
                'label' => (string)$code,
                'value' => $code,
            ];
        }

        // 访问来源列表
        $sourceTypeList = [];
        foreach (RequestSourceEnum::cases() as $case) {
            $sourceTypeList[] = [
                'label' => $case->value,
                'value' => $case->value,
            ];
        }

        return success([
            'module'        => $moduleList,
            'action'        => $operationActionList,
            'method'        => $httpMethodList,
            'response_code' => $responseCodeList,
            'source_type'   => $sourceTypeList,
        ]);
    }

    /**
     * 获取搜索框数据：审计日志
     * @return JsonResponse
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::paramData)]
    public function getAuditLogSearchData(): JsonResponse
    {
        // 模块名称列表（从审计日志表去重）
        $modules = AuditLog::query()
            ->distinct()
            ->whereNotNull('module')
            ->where('module', '!=', '')
            ->orderBy('module')
            ->pluck('module')
            ->toArray();

        $moduleList = [];
        foreach ($modules as $module) {
            $moduleList[] = [
                'label' => $module,
                'value' => $module,
            ];
        }

        // 操作类型列表
        $actionList = [];
        foreach (OperationActionEnum::cases() as $case) {
            $actionList[] = [
                'label' => $case->value,
                'value' => $case->value,
            ];
        }

        // 资源类型列表
        $resourceTypeList = [];
        foreach (ResourceTypeEnum::cases() as $case) {
            $resourceTypeList[] = [
                'label' => $case->value,
                'value' => $case->value,
            ];
        }

        return success([
            'module'        => $moduleList,
            'action'        => $actionList,
            'resource_type' => $resourceTypeList,
        ]);
    }

    /**
     * 获取搜索框数据：登录日志
     * @return JsonResponse
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::paramData)]
    public function getLoginLogSearchData(): JsonResponse
    {
        // 浏览器列表
        $browserList = [];
        foreach (BrowserEnum::cases() as $case) {
            $browserList[] = [
                'label' => $case->value,
                'value' => $case->value,
            ];
        }

        // 操作系统列表
        $osList = [];
        foreach (OperatingSystemEnum::cases() as $case) {
            $osList[] = [
                'label' => $case->value,
                'value' => $case->value,
            ];
        }

        // 设备类型列表
        $deviceTypeList = [];
        foreach (DeviceTypeEnum::cases() as $case) {
            $deviceTypeList[] = [
                'label' => $case->value,
                'value' => $case->value,
            ];
        }

        // 登录状态列表（固定值）
        $statusList = [
            ['label' => '成功', 'value' => 1],
            ['label' => '失败', 'value' => 0],
        ];

        return success([
            'browser'          => $browserList,
            'operating_system' => $osList,
            'device_type'      => $deviceTypeList,
            'status'           => $statusList,
        ]);
    }
}
