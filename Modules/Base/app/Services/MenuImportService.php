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
        // 获取所有 menu_key 列表
        $menuKeys = array_column($menus, 'menu_key');

        // 预先查询数据库中已存在的菜单（根据 account_type 和 menu_key）
        $existingMenus = Menu::where('account_type', $accountType)
            ->whereIn('menu_key', $menuKeys)
            ->pluck('menu_id', 'menu_key')
            ->toArray();

        // 存储菜单 ID 映射（menu_key => menu_id）
        $menuIdMap = $existingMenus; // 先用已存在的菜单初始化
        $importedCount = 0;

        // 按层级处理菜单：先处理顶级菜单，然后逐层处理子菜单
        $processedKeys = [];
        $remainingMenus = $menus;

        // 第一轮：处理顶级菜单（parent_key 为空）
        $topLevelMenus = array_filter($remainingMenus, fn($menu) => empty($menu['parent_key']));

        // 为顶级菜单自动分配 sort 值（目录和菜单分别计数）
        $topLevelMenus = self::assignSortValues($topLevelMenus);

        foreach ($topLevelMenus as $menu) {
            // 如果已存在则使用已有的 menu_id，否则生成新的
            $menuId = $existingMenus[$menu['menu_key']] ?? generateId();
            $menuIdMap[$menu['menu_key']] = $menuId;
            $processedKeys[] = $menu['menu_key'];

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
                    'sort'           => $menu['sort'],
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

            // 按父菜单分组处理子菜单
            $menusByParent = [];
            foreach ($remainingMenus as $menu) {
                $parentKey = $menu['parent_key'];
                if (isset($menuIdMap[$parentKey])) {
                    if (!isset($menusByParent[$parentKey])) {
                        $menusByParent[$parentKey] = [];
                    }
                    $menusByParent[$parentKey][] = $menu;
                }
            }

            // 对每个父菜单下的子菜单进行处理
            foreach ($menusByParent as $parentKey => $childMenus) {
                // 为子菜单自动分配 sort 值（目录和菜单分别计数）
                $childMenus = self::assignSortValues($childMenus);

                foreach ($childMenus as $menu) {
                    // 如果已存在则使用已有的 menu_id，否则生成新的
                    $menuId = $existingMenus[$menu['menu_key']] ?? generateId();
                    $menuIdMap[$menu['menu_key']] = $menuId;
                    $parentId = $menuIdMap[$parentKey];

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
                            'sort'           => $menu['sort'],
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
     * 为菜单数组自动分配 sort 值
     * 目录（dir）和菜单（menu）分别计数，混合情况下有 sort 值的保持原值，没有的从对应类型的最大值+1 开始自增
     * @param array $menus 菜单数组
     * @return array 已分配 sort 值的菜单数组
     * @author siushin<siushin@163.com>
     */
    private static function assignSortValues(array $menus): array
    {
        if (empty($menus)) {
            return $menus;
        }

        // 创建索引映射，用于保持原始顺序
        $indexMap = [];
        foreach ($menus as $index => $menu) {
            $indexMap[$index] = $menu;
        }

        // 按菜单类型分组（dir 和 menu 分别处理）
        $menusByType = [];
        foreach ($menus as $index => $menu) {
            $menuType = $menu['menu_type'] ?? 'menu';
            if (!isset($menusByType[$menuType])) {
                $menusByType[$menuType] = [];
            }
            $menusByType[$menuType][] = ['index' => $index, 'menu' => $menu];
        }

        // 为每种类型分配 sort 值
        foreach ($menusByType as $menuType => $typeMenus) {
            // 找出该类型中已有的最大 sort 值
            $maxSort = 0;
            foreach ($typeMenus as $item) {
                $sort = isset($item['menu']['sort']) && $item['menu']['sort'] !== '' ? (int)$item['menu']['sort'] : null;
                if ($sort !== null && $sort > $maxSort) {
                    $maxSort = $sort;
                }
            }

            // 为没有 sort 值的菜单分配值（从最大值+1 开始）
            $nextSort = $maxSort + 1;
            foreach ($typeMenus as $item) {
                $sort = isset($item['menu']['sort']) && $item['menu']['sort'] !== '' ? (int)$item['menu']['sort'] : null;
                if ($sort === null) {
                    $indexMap[$item['index']]['sort'] = $nextSort;
                    $nextSort++;
                } else {
                    $indexMap[$item['index']]['sort'] = $sort;
                }
            }
        }

        // 重新组装菜单数组（保持原始顺序）
        $result = [];
        foreach ($indexMap as $menu) {
            $result[] = $menu;
        }

        return $result;
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

