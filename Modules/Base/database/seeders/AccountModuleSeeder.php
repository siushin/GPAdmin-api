<?php

namespace Modules\Base\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Base\Models\AccountModule;
use Modules\Base\Models\Admin;
use Modules\Base\Models\Module;

/**
 * 数据填充：账号模块关联
 */
class AccountModuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 获取所有模块
        $modules = Module::query()
            ->where('module_status', 1) // 只获取已启用的模块
            ->where('module_is_installed', 1) // 只获取已安装的模块
            ->get();

        if ($modules->isEmpty()) {
            $this->command->warn('没有找到已启用且已安装的模块，跳过账号模块关联数据填充');
            return;
        }

        // 获取超级管理员账号
        $superAdmin = Admin::query()
            ->where('is_super', 1)
            ->with('account')
            ->first();

        if (!$superAdmin || !$superAdmin->account) {
            $this->command->warn('没有找到超级管理员账号，跳过账号模块关联数据填充');
            return;
        }

        $accountId = $superAdmin->account->id;

        // 准备所有模块的关联数据（使用 upsert 自动处理创建和更新时间）
        $now = now();
        $insertData = [];
        foreach ($modules as $module) {
            $insertData[] = [
                'id'         => generateId(),
                'account_id' => $accountId,
                'module_id'  => $module->module_id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // 使用 upsert 批量插入/更新（唯一键：account_id + module_id）
        AccountModule::upsert(
            $insertData,
            ['account_id', 'module_id'], // 唯一键
            ['updated_at'] // 如果记录已存在，只更新 updated_at
        );

        $this->command->info('成功为超级管理员绑定 ' . count($modules) . ' 个模块');
        foreach ($modules as $module) {
            $this->command->line('  - ' . $module->module_name . ' (' . $module->module_title . ')');
        }
    }
}

