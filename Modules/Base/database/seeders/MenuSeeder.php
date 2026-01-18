<?php

namespace Modules\Base\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Base\Enums\AccountTypeEnum;
use Modules\Base\Services\MenuImportService;

/**
 * 数据填充：菜单
 * 使用 MenuImportService 服务类导入 Base 模块的菜单
 */
class MenuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $accountType = AccountTypeEnum::Admin->value;

        // 读取当前模块的 module.json 获取模块名称
        $moduleJsonPath = __DIR__ . '/../../module.json';
        $moduleName = 'Base'; // 默认值
        if (file_exists($moduleJsonPath)) {
            $moduleJson = json_decode(file_get_contents($moduleJsonPath), true);
            $moduleName = $moduleJson['name'] ?? 'Base';
        }

        // 读取 CSV 文件
        $csvPath = __DIR__ . '/../../data/menu.csv';

        // 使用 MenuImportService 导入菜单
        MenuImportService::importMenusFromCsv($moduleName, $csvPath, $accountType, $this->command);
    }

}
