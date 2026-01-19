<?php

namespace Modules\Base\Http\Controllers;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Base\Enums\OperationActionEnum;
use Modules\Base\Logics\AppLogic;
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
        $modulePath = $request->input('module_path');

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

        $result = AppLogic::installModule($moduleId);

        return success($result, '安装模块成功');
    }

    /**
     * 获取模块排序列表
     * @param Request $request
     * @return JsonResponse
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::index)]
    public function getModulesSort(Request $request): JsonResponse
    {
        $list = AppLogic::getModulesSort();
        return success($list, '获取模块排序列表成功');
    }

    /**
     * 更新模块排序
     * @param Request $request
     * @return JsonResponse
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::update)]
    public function updateModulesSort(Request $request): JsonResponse
    {
        $sortList = $request->input('sort_list', []);
        AppLogic::updateModulesSort($sortList);

        return success([], '排序保存成功，请退出登录后重新登录以使排序生效');
    }
}
