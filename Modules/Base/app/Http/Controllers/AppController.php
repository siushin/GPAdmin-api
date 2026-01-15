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
}
