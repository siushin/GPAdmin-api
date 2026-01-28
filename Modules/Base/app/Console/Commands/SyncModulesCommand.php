<?php

namespace Modules\Base\Console\Commands;

use Illuminate\Console\Command;
use Modules\Admin\Models\Module;

/**
 * 扫描并同步模块数据到 gpa_modules 表
 * 用法:
 *   php artisan gpa:sync-modules         -- 扫描所有模块
 *   php artisan gpa:sync-modules --path=Sms  -- 扫描指定模块
 *
 * @author siushin<siushin@163.com>
 */
class SyncModulesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gpa:sync-modules
                            {--path= : 指定模块路径（相对于 Modules 目录或绝对路径）}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '扫描并同步模块数据到 gpa_modules 表';

    /**
     * Execute the console command.
     *
     * @return int
     * @author siushin<siushin@163.com>
     */
    public function handle(): int
    {
        $modulePath = $this->option('path');

        $this->info('开始扫描模块...');
        $this->newLine();

        try {
            $result = Module::scanAndUpdateModules($modulePath);

            // 输出成功结果
            if (!empty($result['success'])) {
                $this->info('✅ 成功同步 ' . count($result['success']) . ' 个模块:');
                foreach ($result['success'] as $item) {
                    $this->line('   - ' . $item['module_name'] . ' (' . basename($item['path']) . ')');
                }
            } else {
                $this->comment('没有找到需要同步的模块');
            }

            // 输出失败结果
            if (!empty($result['failed'])) {
                $this->newLine();
                $this->warn('❌ 失败 ' . count($result['failed']) . ' 个:');
                foreach ($result['failed'] as $item) {
                    $this->error('   - ' . basename($item['path'] ?? 'unknown') . ': ' . $item['message']);
                }
            }

            // 输出汇总
            $this->newLine();
            $this->info('═══════════════════════════════════════════════════════════');
            $this->info('  模块同步完成！');
            $this->info('  成功: ' . count($result['success']) . ' 个');
            $this->info('  失败: ' . count($result['failed']) . ' 个');
            $this->info('═══════════════════════════════════════════════════════════');

            return count($result['failed']) > 0 ? self::FAILURE : self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('模块同步失败: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}

