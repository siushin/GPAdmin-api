<?php

namespace Modules\Base\Http\Controllers;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Modules\Base\Enums\OperationActionEnum;
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
                    'name'        => $moduleData['name'] ?? $moduleName,
                    'alias'       => $moduleData['alias'] ?? $moduleName,
                    'description' => $moduleData['description'] ?? '',
                    'keywords'    => $moduleData['keywords'] ?? [],
                    'priority'    => $moduleData['priority'] ?? 0,
                    'source'      => $moduleData['source'] ?? '第三方',
                    'enabled'     => $isEnabled,
                    'path'        => $moduleName,
                ];

                // 如果有搜索关键词，进行筛选
                if (!empty($keyword)) {
                    $keywordLower = mb_strtolower($keyword, 'UTF-8');
                    $matchAlias = mb_strpos(mb_strtolower($app['alias'], 'UTF-8'), $keywordLower) !== false;
                    $matchName = mb_strpos(mb_strtolower($app['name'], 'UTF-8'), $keywordLower) !== false;
                    $matchDescription = mb_strpos(mb_strtolower($app['description'], 'UTF-8'), $keywordLower) !== false;
                    $matchKeywords = false;

                    // 检查关键词数组
                    if (is_array($app['keywords'])) {
                        foreach ($app['keywords'] as $kw) {
                            if (mb_strpos(mb_strtolower($kw, 'UTF-8'), $keywordLower) !== false) {
                                $matchKeywords = true;
                                break;
                            }
                        }
                    }

                    // 如果都不匹配，跳过该应用
                    if (!$matchAlias && !$matchName && !$matchDescription && !$matchKeywords) {
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
            return ($b['priority'] ?? 0) <=> ($a['priority'] ?? 0);
        });

        return success($apps, '获取应用列表成功');
    }
}
