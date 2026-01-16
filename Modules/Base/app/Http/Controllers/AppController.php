<?php

namespace Modules\Base\Http\Controllers;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Base\Enums\OperationActionEnum;
use Modules\Base\Models\Module as ModuleModel;
use Siushin\LaravelTool\Attributes\ControllerName;
use Siushin\LaravelTool\Attributes\OperationAction;

#[ControllerName('应用管理')]
class AppController extends Controller
{
    /**
     * 获取我的应用列表
     * @param Request $request
     * @return JsonResponse
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::index)]
    public function getMyApps(Request $request): JsonResponse
    {
        $params = $request->all();
        $apps = ModuleModel::getMyApps($params);
        return success($apps, '获取应用列表成功');
    }

    /**
     * 获取市场应用列表（所有模块）
     * @param Request $request
     * @return JsonResponse
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::index)]
    public function getMarketApps(Request $request): JsonResponse
    {
        $params = $request->all();
        $apps = ModuleModel::getMarketApps($params);
        return success($apps, '获取应用列表成功');
    }

    /**
     * 更新本地模块
     * @param Request $request
     * @return JsonResponse
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::update)]
    public function updateModules(Request $request): JsonResponse
    {
        $modulePath = $request->input('module_path', null);

        // 如果提供了空字符串，转换为 null（表示扫描所有模块）
        if ($modulePath === '') {
            $modulePath = null;
        }

        try {
            $result = ModuleModel::scanAndUpdateModules($modulePath);

            $message = '更新模块成功';
            if (!empty($result['success'])) {
                $message .= '，成功更新 ' . count($result['success']) . ' 个模块';
            }
            if (!empty($result['failed'])) {
                $message .= '，失败 ' . count($result['failed']) . ' 个模块';
            }

            return success([
                'success' => $result['success'],
                'failed'  => $result['failed'],
            ], $message);
        } catch (Exception $e) {
            throw_exception('更新模块失败: ' . $e->getMessage());
            // @phpstan-ignore-next-line
            return success([], '');
        }
    }

    /**
     * 卸载模块
     * @param Request $request
     * @return JsonResponse
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::delete)]
    public function uninstallModule(Request $request): JsonResponse
    {
        $moduleId = $request->input('module_id');
        if (empty($moduleId)) {
            throw_exception('模块ID不能为空');
        }

        $result = ModuleModel::uninstallModule($moduleId);

        return success($result, '卸载模块成功');
    }

    /**
     * 安装模块
     * @param Request $request
     * @return JsonResponse
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::create)]
    public function installModule(Request $request): JsonResponse
    {
        $moduleId = $request->input('module_id');
        if (empty($moduleId)) {
            throw_exception('模块ID不能为空');
        }

        // 获取当前登录用户ID
        $accountId = currentUserId();
        if (!$accountId) {
            throw_exception('未登录或登录已过期');
        }

        // 检查模块是否存在
        $module = ModuleModel::find($moduleId);
        if (!$module) {
            throw_exception('模块不存在');
        }

        // TODO: 后续需要完善安装逻辑，目前暂时只插入 gpa_account_module 表
        // 检查是否已安装
        $exists = \Modules\Base\Models\AccountModule::where('account_id', $accountId)
            ->where('module_id', $moduleId)
            ->exists();

        if ($exists) {
            throw_exception('该模块已安装');
        }

        // 插入账号模块关联表
        \Modules\Base\Models\AccountModule::create([
            'account_id' => $accountId,
            'module_id' => $moduleId,
        ]);

        return success([
            'module_id' => $moduleId,
            'module_name' => $module->module_name,
        ], '安装模块成功');
    }
}
