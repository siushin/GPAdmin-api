<?php

namespace Modules\Base\Http\Controllers;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Modules\Base\Enums\OperationActionEnum;
use Modules\Base\Models\Module as ModuleModel;
use Nwidart\Modules\Facades\Module;
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
        $modulesPath = base_path('Modules');
        $apps = [];
        $keyword = $request->input('keyword', '');

        if (!File::exists($modulesPath)) {
            return success([], '暂无应用');
        }

        // 遍历 Modules 目录
        $directories = File::directories($modulesPath);

        foreach ($directories as $directory) {
            $moduleName = basename($directory);
            $moduleJsonPath = $directory . '/module.json';

            // 检查 module.json 是否存在
            if (!File::exists($moduleJsonPath)) {
                continue;
            }

            try {
                // 读取 module.json 内容
                $moduleJsonContent = File::get($moduleJsonPath);
                $moduleData = json_decode($moduleJsonContent, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    continue;
                }

                // 获取模块信息
                $module = Module::find($moduleName);
                $isEnabled = $module ? $module->isEnabled() : false;

                // 构建应用数据
                $app = [
                    'module_name'     => $moduleData['name'] ?? $moduleName,
                    'module_alias'    => $moduleData['alias'] ?? $moduleName,
                    'module_title'    => $moduleData['title'] ?? $moduleData['alias'] ?? $moduleName,
                    'module_desc'     => $moduleData['description'] ?? '',
                    'module_keywords' => $moduleData['keywords'] ?? [],
                    'module_priority' => $moduleData['priority'] ?? 0,
                    'module_source'   => $moduleData['source'] ?? 'third_party',
                    'module_status'   => $isEnabled ? 1 : 0,
                    'path'            => $moduleName,
                ];

                // 如果有搜索关键词，进行筛选
                if (!empty($keyword)) {
                    $keywordLower = mb_strtolower($keyword, 'UTF-8');
                    $matchAlias = mb_strpos(mb_strtolower($app['module_alias'], 'UTF-8'), $keywordLower) !== false;
                    $matchTitle = mb_strpos(mb_strtolower($app['module_title'], 'UTF-8'), $keywordLower) !== false;
                    $matchName = mb_strpos(mb_strtolower($app['module_name'], 'UTF-8'), $keywordLower) !== false;
                    $matchDescription = mb_strpos(mb_strtolower($app['module_desc'], 'UTF-8'), $keywordLower) !== false;
                    $matchKeywords = false;

                    // 检查关键词数组
                    if (is_array($app['module_keywords'])) {
                        foreach ($app['module_keywords'] as $kw) {
                            if (mb_strpos(mb_strtolower($kw, 'UTF-8'), $keywordLower) !== false) {
                                $matchKeywords = true;
                                break;
                            }
                        }
                    }

                    // 如果都不匹配，跳过该应用
                    if (!$matchAlias && !$matchTitle && !$matchName && !$matchDescription && !$matchKeywords) {
                        continue;
                    }
                }

                $apps[] = $app;
            } catch (Exception $e) {
                // 跳过无法读取的模块
                continue;
            }
        }

        // 按 priority 排序，priority 越大越靠前
        usort($apps, function ($a, $b) {
            return ($b['module_priority'] ?? 0) <=> ($a['module_priority'] ?? 0);
        });

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
            return error('更新模块失败: ' . $e->getMessage());
        }
    }
}
