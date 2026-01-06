<?php

namespace Modules\Base\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Base\Enums\AccountTypeEnum;
use Modules\Base\Enums\SysParamFlagEnum;
use Modules\Base\Models\Menu;
use Modules\Base\Models\Module;
use Modules\Base\Models\ModuleMenu;

/**
 * 数据填充：菜单
 */
class MenuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $accountType = AccountTypeEnum::Admin->value;

        // 读取 CSV 文件
        $csvPath = __DIR__ . '/../../data/menu.csv';
        if (!file_exists($csvPath)) {
            $this->command->error("CSV file not found: {$csvPath}");
            return;
        }

        // 读取 CSV 数据
        $menus = $this->readCsvFile($csvPath);

        if (empty($menus)) {
            $this->command->warn('No menu data found in CSV file.');
            return;
        }

        // 存储菜单 ID 映射（menu_key => menu_id）
        $menuIdMap = [];

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
                    'component'      => $menu['component'] ?: null,
                    'redirect'       => $menu['redirect'] ?: null,
                    'is_required'    => (int)$menu['is_required'],
                    'sort'           => $sortValue,
                    'status'         => (int)$menu['status'],
                    'sys_param_flag' => SysParamFlagEnum::Yes,
                ]
            ], ['account_type', 'menu_key']);
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
                            'component'      => $menu['component'] ?: null,
                            'redirect'       => $menu['redirect'] ?: null,
                            'is_required'    => (int)$menu['is_required'],
                            'sort'           => $sortValue,
                            'status'         => (int)$menu['status'],
                            'sys_param_flag' => SysParamFlagEnum::Yes,
                        ]
                    ], ['account_type', 'menu_key']);

                    $processedInThisRound[] = $menu['menu_key'];
                }
            }

            // 移除已处理的菜单
            $remainingMenus = array_filter($remainingMenus, fn($menu) => !in_array($menu['menu_key'], $processedInThisRound));

            // 如果这一轮没有处理任何菜单，说明有循环依赖或缺失的父菜单
            if (empty($processedInThisRound)) {
                $unprocessedKeys = array_column($remainingMenus, 'menu_key');
                $this->command->warn('Some menus could not be processed. Check parent_key references: ' . implode(', ', $unprocessedKeys));
                break;
            }
        }

        // 关联菜单到Base模块
        $this->associateMenusToBaseModule();
    }

    /**
     * 读取 CSV 文件
     * @param string $csvPath CSV 文件路径
     * @return array 菜单数据数组
     */
    private function readCsvFile(string $csvPath): array
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
     * 关联菜单到Base模块
     */
    private function associateMenusToBaseModule(): void
    {
        // 查找Base模块
        $baseModule = Module::where('module_name', 'base')->first();

        if (!$baseModule) {
            // 如果Base模块不存在，创建它
            $baseModuleId = generateId();
            Module::upsert([
                [
                    'module_id'       => $baseModuleId,
                    'module_name'     => 'base',
                    'module_alias'    => '基础服务',
                    'module_desc'     => 'LaravelAPI 基础服务，勿删除！',
                    'module_status'   => 1,
                    'module_priority' => 0,
                ]
            ], ['module_name']);
        } else {
            $baseModuleId = $baseModule->module_id;
        }

        // 获取所有Base模块的Admin菜单
        $menuIds = Menu::where('account_type', AccountTypeEnum::Admin->value)
            ->pluck('menu_id')
            ->toArray();

        // 关联菜单到Base模块
        $moduleMenuData = [];
        foreach ($menuIds as $menuId) {
            // 检查是否已存在关联
            $exists = ModuleMenu::where('module_id', $baseModuleId)
                ->where('menu_id', $menuId)
                ->exists();

            if (!$exists) {
                $moduleMenuData[] = [
                    'id'        => generateId(),
                    'module_id' => $baseModuleId,
                    'menu_id'   => $menuId,
                ];
            }
        }

        if (!empty($moduleMenuData)) {
            ModuleMenu::upsert($moduleMenuData, ['module_id', 'menu_id']);
        }
    }
}
