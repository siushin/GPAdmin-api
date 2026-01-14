<?php

namespace Modules\Base\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Base\Models\Module;

/**
 * 数据填充：模块管理
 */
class ModuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 扫描并更新所有模块
        $result = Module::scanAndUpdateModules();

        // 输出结果
        if (!empty($result['success'])) {
            $this->command->info('成功更新 ' . count($result['success']) . ' 个模块:');
            foreach ($result['success'] as $item) {
                $this->command->line('  - ' . $item['module_name'] . ' (' . $item['path'] . ')');
            }
        }

        if (!empty($result['failed'])) {
            $this->command->warn('失败 ' . count($result['failed']) . ' 个模块:');
            foreach ($result['failed'] as $item) {
                $this->command->error('  - ' . ($item['path'] ?? 'unknown') . ': ' . $item['message']);
            }
        }
    }

    /**
     * 更新指定模块
     * @param string $modulePath 模块路径（模块根目录），可以是绝对路径或相对路径（相对于 Modules 目录）
     */
    public function updateModule(string $modulePath): void
    {
        $result = Module::scanAndUpdateModules($modulePath);

        if (!empty($result['success'])) {
            $this->command->info('成功更新模块:');
            foreach ($result['success'] as $item) {
                $this->command->line('  - ' . $item['module_name'] . ' (' . $item['path'] . ')');
            }
        }

        if (!empty($result['failed'])) {
            $this->command->error('更新失败:');
            foreach ($result['failed'] as $item) {
                $this->command->error('  - ' . ($item['path'] ?? 'unknown') . ': ' . $item['message']);
            }
        }
    }
}

