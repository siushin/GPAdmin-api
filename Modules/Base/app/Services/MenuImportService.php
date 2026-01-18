<?php

namespace Modules\Base\Services;

use Exception;
use Illuminate\Console\Command;
use Modules\Base\Enums\AccountTypeEnum;
use Modules\Base\Enums\SysParamFlagEnum;
use Modules\Base\Models\Menu;
use Modules\Base\Models\Module;
use Modules\Base\Models\ModuleMenu;

/**
 * 菜单导入服务类
 * 处理从 CSV 文件导入菜单数据的逻辑
 */
class MenuImportService
{
    /**
     * 从 CSV 文件导入菜单
     * @param string      $moduleName  模块名称
     * @param string      $csvPath     CSV 文件路径
     * @param string|null $accountType 账号类型，默认 Admin
     * @param Command|null $command    命令行实例（可选，用于输出信息）
     * @return array 返回导入结果 ['success' => bool, 'message' => string, 'count' => int]
     * @throws Exception
     */
    public static function importMenusFromCsv(
        string $moduleName,
        string $csvPath,
        ?string $accountType = null,
        ?Command $command = null
    ): array {
        $accountType = $accountType ?? AccountTypeEnum::Admin->value;

        // 检查文件是否存在
        if (!file_exists($csvPath)) {
            $message = "CSV file not found: {$csvPath}";
            if ($command) {
                $command->warn($message);
            }
            return ['success' => false, 'message' => $message, 'count' => 0];
        }

        // 查询或创建模块，获取模块ID
        $moduleId = self::getOrCreateModuleId($moduleName, $command);

        // 读取 CSV 数据
        $menus = self::readCsvFile($csvPath);

        if (empty($menus)) {
            $message = 'No menu data found in CSV file.';
            if ($command) {
                $command->warn($message);
            }
            return ['success' => true, 'message' => $message, 'count' => 0];
        }

        // 导入菜单
        $importedCount = self::importMenus($menus, $moduleId, $accountType, $command);

        // 关联菜单到模块
        self::associateMenusToModule($moduleId, $accountType);

        return [
            'success' => true,
            'message' => "Successfully imported {$importedCount} menus from {$moduleName}",
            'count' => $importedCount,
        ];
    }

    /**
     * 读取 CSV 文件
     * @param string $csvPath CSV 文件路径
     * @return array 菜单数据数组
     */
    private static function readCsvFile(string $csvPath): array
    {
        $menus = [];
        $handle = fopen($csvPath, 'r');

        if ($handle === false) {
            return $menus;
        }

        // 读取表头
        $headers = fgetcsv($handle);
        if ($headers === false) {
            fclose($handle);
            return $menus;
        }

        // 读取数据行
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) !== count($headers)) {
                continue; // 跳过不完整的行
            }

            // 检查整行是否为空（所有字段都为空或只包含空白字符）
            $isEmpty = true;
            foreach ($row as $cell) {
                if (trim($cell) !== '') {
                    $isEmpty = false;
                    break;
                }
            }

            if ($isEmpty) {
                continue; // 跳过空行
            }

            $menu = [];
            foreach ($headers as $index => $header) {
                $menu[trim($header)] = isset($row[$index]) ? trim($row[$index]) : '';
            }

            $menus[] = $menu;
        }

        fclose($handle);
        return $menus;
    }

    /**
     * 导入菜单数据
     * @param array        $menus      菜单数据数组
     * @param int          $moduleId   模块ID
     * @param string       $accountType 账号类型
     * @param Command|null $command    命令行实例
     * @return int 导入的菜单数量
     */
    private static function importMenus(
        array $menus,
        int $moduleId,
        string $accountType,
        ?Command $command = null
    ): int {
        // 存储菜单 ID 映射（menu_key => menu_id）
        $menuIdMap = [];
        $importedCount = 0;

        // 按层级处理菜单：先处理顶级菜单，然后逐层处理子菜单
        $processedKeys = [];
        $remainingMenus = $menus;

        // 第一轮：处理顶级菜单（parent_key 为空）
        $topLevelMenus = array_filter($remainingMenus, fn($menu) => empty($menu['parent_key']));
        foreach ($topLevelMenus as $menu) {
            $menuId = generateId();
            $menuIdMap[$menu['menu_key']] = $menuId;
            $processedKeys[] = $menu['menu_key'];

            // 使用 CSV 中的 sort 值，如果没有则使用默认值 0
            $sortValue = isset($menu['sort']) && $menu['sort'] !== '' ? (int)$menu['sort'] : 0;

            Menu::upsert([
                [
                    'menu_id'        => $menuId,
                    'account_type'   => $accountType,
                    'menu_name'      => $menu['menu_name'],
                    'menu_key'       => $menu['menu_key'],
                    'menu_path'      => $menu['menu_path'],
                    'menu_icon'      => $menu['menu_icon'],
                    'menu_type'      => $menu['menu_type'],
                    'parent_id'      => 0,
                    'module_id'      => $moduleId,
                    'component'      => $menu['component'] ?: null,
                    'redirect'       => $menu['redirect'] ?: null,
                    'is_required'    => (int)$menu['is_required'],
                    'sort'           => $sortValue,
                    'status'         => (int)$menu['status'],
                    'sys_param_flag' => SysParamFlagEnum::Yes,
                ]
            ], ['account_type', 'menu_key']);

            $importedCount++;
        }

        // 移除已处理的顶级菜单
        $remainingMenus = array_filter($remainingMenus, fn($menu) => !in_array($menu['menu_key'], $processedKeys));

        // 循环处理子菜单，直到所有菜单都被处理
        while (!empty($remainingMenus)) {
            $processedInThisRound = [];

            foreach ($remainingMenus as $menu) {
                $parentKey = $menu['parent_key'];

                // 如果父菜单已处理，则处理当前菜单
                if (isset($menuIdMap[$parentKey])) {
                    $menuId = generateId();
                    $menuIdMap[$menu['menu_key']] = $menuId;
                    $parentId = $menuIdMap[$parentKey];

                    // 使用 CSV 中的 sort 值，如果没有则使用默认值 0
                    $sortValue = isset($menu['sort']) && $menu['sort'] !== '' ? (int)$menu['sort'] : 0;

                    Menu::upsert([
                        [
                            'menu_id'        => $menuId,
                            'account_type'   => $accountType,
                            'menu_name'      => $menu['menu_name'],
                            'menu_key'       => $menu['menu_key'],
                            'menu_path'      => $menu['menu_path'],
                            'menu_icon'      => $menu['menu_icon'],
                            'menu_type'      => $menu['menu_type'],
                            'parent_id'      => $parentId,
                            'module_id'      => $moduleId,
                            'component'      => $menu['component'] ?: null,
                            'redirect'       => $menu['redirect'] ?: null,
                            'is_required'    => (int)$menu['is_required'],
                            'sort'           => $sortValue,
                            'status'         => (int)$menu['status'],
                            'sys_param_flag' => SysParamFlagEnum::Yes,
                        ]
                    ], ['account_type', 'menu_key']);

                    $processedInThisRound[] = $menu['menu_key'];
                    $importedCount++;
                }
            }

            // 移除已处理的菜单
            $remainingMenus = array_filter($remainingMenus, fn($menu) => !in_array($menu['menu_key'], $processedInThisRound));

            // 如果这一轮没有处理任何菜单，说明有循环依赖或缺失的父菜单
            if (empty($processedInThisRound)) {
                $unprocessedKeys = array_column($remainingMenus, 'menu_key');
                $message = 'Some menus could not be processed. Check parent_key references: ' . implode(', ', $unprocessedKeys);
                if ($command) {
                    $command->warn($message);
                }
                break;
            }
        }

        return $importedCount;
    }

    /**
     * 获取或创建模块ID
     * @param string      $moduleName 模块名称
     * @param Command|null $command   命令行实例
     * @return int 模块ID
     */
    private static function getOrCreateModuleId(string $moduleName, ?Command $command = null): int
    {
        // 查找模块
        $module = Module::where('module_name', $moduleName)->first();

        if ($module) {
            return $module->module_id;
        }

        // 尝试读取 module.json 获取模块信息
        $modulesPath = base_path('Modules');
        $modulePath = $modulesPath . DIRECTORY_SEPARATOR . $moduleName;
        $moduleJsonPath = $modulePath . DIRECTORY_SEPARATOR . 'module.json';

        $moduleData = [
            'module_name'     => $moduleName,
            'module_alias'    => strtolower($moduleName),
            'module_title'    => $moduleName,
            'module_desc'     => '',
            'module_status'   => 1,
            'module_priority' => 0,
        ];

        if (file_exists($moduleJsonPath)) {
            $moduleJson = json_decode(file_get_contents($moduleJsonPath), true);
            if ($moduleJson) {
                $moduleData['module_alias'] = $moduleJson['alias'] ?? strtolower($moduleName);
                $moduleData['module_title'] = $moduleJson['title'] ?? $moduleJson['alias'] ?? $moduleName;
                $moduleData['module_desc'] = $moduleJson['description'] ?? '';
                $moduleData['module_priority'] = $moduleJson['priority'] ?? 0;

                // 读取 extra.meta 中的信息
                if (isset($moduleJson['extra']['meta'])) {
                    $meta = $moduleJson['extra']['meta'];
                    if (isset($meta['module_icon'])) {
                        $moduleData['module_icon'] = $meta['module_icon'];
                    }
                }
            }
        }

        // 创建模块
        $moduleId = generateId();
        Module::upsert([
            array_merge(['module_id' => $moduleId], $moduleData)
        ], ['module_name']);

        if ($command) {
            $command->info("Created module: {$moduleName} (ID: {$moduleId})");
        }

        return $moduleId;
    }

    /**
     * 关联菜单到模块
     * @param int    $moduleId    模块ID
     * @param string $accountType 账号类型
     */
    private static function associateMenusToModule(int $moduleId, string $accountType): void
    {
        // 获取所有该模块的菜单
        $menuIds = Menu::where('account_type', $accountType)
            ->where('module_id', $moduleId)
            ->pluck('menu_id')
            ->toArray();

        // 关联菜单到模块
        $moduleMenuData = [];
        foreach ($menuIds as $menuId) {
            // 检查是否已存在关联
            $exists = ModuleMenu::where('module_id', $moduleId)
                ->where('menu_id', $menuId)
                ->exists();

            if (!$exists) {
                $moduleMenuData[] = [
                    'id'        => generateId(),
                    'module_id' => $moduleId,
                    'menu_id'   => $menuId,
                ];
            }
        }

        if (!empty($moduleMenuData)) {
            ModuleMenu::upsert($moduleMenuData, ['module_id', 'menu_id']);
        }
    }

    /**
     * 扫描所有模块的菜单 CSV 文件并导入
     * @param string|null $accountType 账号类型
     * @param Command|null $command    命令行实例
     * @return array 返回导入结果
     */
    public static function importAllModulesMenus(?string $accountType = null, ?Command $command = null): array
    {
        $accountType = $accountType ?? AccountTypeEnum::Admin->value;
        $modulesPath = base_path('Modules');
        $results = [];

        if (!is_dir($modulesPath)) {
            $message = "Modules directory not found: {$modulesPath}";
            if ($command) {
                $command->error($message);
            }
            return ['success' => false, 'message' => $message, 'modules' => []];
        }

        // 扫描所有模块目录
        $moduleDirs = glob($modulesPath . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);

        foreach ($moduleDirs as $moduleDir) {
            $moduleName = basename($moduleDir);
            $csvPath = $moduleDir . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'menu.csv';

            // 如果 CSV 文件不存在，跳过
            if (!file_exists($csvPath)) {
                continue;
            }

            // 读取 module.json 获取模块名称（确保使用正确的模块名称）
            $moduleJsonPath = $moduleDir . DIRECTORY_SEPARATOR . 'module.json';
            if (file_exists($moduleJsonPath)) {
                $moduleJson = json_decode(file_get_contents($moduleJsonPath), true);
                if ($moduleJson && isset($moduleJson['name'])) {
                    $moduleName = $moduleJson['name'];
                }
            }

            // 导入菜单
            try {
                $result = self::importMenusFromCsv($moduleName, $csvPath, $accountType, $command);
                $results[] = [
                    'module' => $moduleName,
                    'csv_path' => $csvPath,
                    ...$result,
                ];
            } catch (Exception $e) {
                $results[] = [
                    'module' => $moduleName,
                    'csv_path' => $csvPath,
                    'success' => false,
                    'message' => $e->getMessage(),
                    'count' => 0,
                ];
                if ($command) {
                    $command->error("Failed to import menus for module {$moduleName}: " . $e->getMessage());
                }
            }
        }

        return [
            'success' => true,
            'message' => 'All modules menus imported',
            'modules' => $results,
        ];
    }
}

