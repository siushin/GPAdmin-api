<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Admin\Database\Seeders\BaseDatabaseSeeder;
use Modules\Base\Services\MenuImportService;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 调用 Base 模块的 seeder（这会导入 Base 模块的菜单）
        $this->call(BaseDatabaseSeeder::class);

        // 扫描所有模块并导入菜单
        $this->command->info('Scanning all modules for menu CSV files...');
        $result = MenuImportService::importAllModulesMenus(null, $this->command);

        if ($result['success']) {
            $totalCount = 0;
            $successCount = 0;
            foreach ($result['modules'] as $moduleResult) {
                $totalCount += $moduleResult['count'] ?? 0;
                if ($moduleResult['success'] ?? false) {
                    $successCount++;
                }
            }
            $this->command->info("Menu import completed. Successfully imported menus from {$successCount} modules, total {$totalCount} menus.");
        } else {
            $this->command->error('Menu import failed: ' . ($result['message'] ?? 'Unknown error'));
        }
    }
}
