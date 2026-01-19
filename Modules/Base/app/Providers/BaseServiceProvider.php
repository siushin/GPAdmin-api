<?php

namespace Modules\Base\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;
use Modules\Base\Console\Commands\MakeApiCommand;
use Modules\Base\Console\Commands\MakeControllerCommand;
use Modules\Base\Console\Commands\MakeModelCommand;
use Modules\Base\Console\Commands\SyncModulesCommand;
use Modules\Base\Models\Module;
use Modules\Base\Models\PersonalAccessToken;
use Nwidart\Modules\Traits\PathNamespace;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class BaseServiceProvider extends ServiceProvider
{
    use PathNamespace;

    protected string $name = 'Base';

    protected string $nameLower = 'base';

    /**
     * Boot the application events.
     *
     * @author siushin<siushin@163.com>
     */
    public function boot(): void
    {
        $this->registerCommands();
        $this->registerCommandSchedules();
        $this->registerTranslations();
        $this->registerConfig();
        $this->loadMigrationsFrom(module_path($this->name, 'database/migrations'));

        // 配置 Sanctum 使用自定义的 PersonalAccessToken 模型
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        // 启动时自动同步模块数据（使用缓存控制扫描频率）
        $this->syncModulesOnBoot();
    }

    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->register(EventServiceProvider::class);
        $this->app->register(RouteServiceProvider::class);
    }

    /**
     * Register commands in the format of Command::class
     *
     * @author siushin<siushin@163.com>
     */
    protected function registerCommands(): void
    {
        $this->commands([
            MakeApiCommand::class,
            MakeControllerCommand::class,
            MakeModelCommand::class,
            SyncModulesCommand::class,
        ]);
    }

    /**
     * Register command Schedules.
     *
     * @author siushin<siushin@163.com>
     */
    protected function registerCommandSchedules(): void
    {
        $this->app->booted(function () {
            /** @var Schedule $schedule */
            $schedule = $this->app->make(Schedule::class);

            // 每 5 分钟同步一次模块数据（定时任务兜底）
            $schedule->command('gpa:sync-modules')
                ->everyFiveMinutes()
                ->withoutOverlapping()
                ->runInBackground()
                ->appendOutputTo(storage_path('logs/modules-sync.log'));
        });
    }

    /**
     * Register translations.
     */
    public function registerTranslations(): void
    {
        $langPath = resource_path('lang/modules/' . $this->nameLower);

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, $this->nameLower);
            $this->loadJsonTranslationsFrom($langPath);
        } else {
            $this->loadTranslationsFrom(module_path($this->name, 'lang'), $this->nameLower);
            $this->loadJsonTranslationsFrom(module_path($this->name, 'lang'));
        }
    }

    /**
     * Register config.
     */
    protected function registerConfig(): void
    {
        $configPath = module_path($this->name, config('modules.paths.generator.config.path'));

        if (is_dir($configPath)) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($configPath));

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $config = str_replace($configPath . DIRECTORY_SEPARATOR, '', $file->getPathname());
                    $config_key = str_replace([DIRECTORY_SEPARATOR, '.php'], ['.', ''], $config);
                    $segments = explode('.', $this->nameLower . '.' . $config_key);

                    // Remove duplicated adjacent segments
                    $normalized = [];
                    foreach ($segments as $segment) {
                        if (end($normalized) !== $segment) {
                            $normalized[] = $segment;
                        }
                    }

                    $key = ($config === 'config.php') ? $this->nameLower : implode('.', $normalized);

                    // 特殊处理 laravel-tool.php，使其可以通过 config('laravel-tool') 访问
                    if ($config === 'laravel-tool.php') {
                        $this->publishes([$file->getPathname() => config_path($config)], 'config');
                        $this->merge_config_from($file->getPathname(), 'laravel-tool');
                    } else {
                        $this->publishes([$file->getPathname() => config_path($config)], 'config');
                        $this->merge_config_from($file->getPathname(), $key);
                    }
                }
            }
        }
    }

    /**
     * Merge config from the given path recursively.
     */
    protected function merge_config_from(string $path, string $key): void
    {
        $existing = config($key, []);
        $module_config = require $path;

        config([$key => array_replace_recursive($existing, $module_config)]);
    }

    /**
     * 启动时同步模块数据
     * 使用缓存控制扫描频率，避免每次请求都扫描影响性能
     *
     * @author siushin<siushin@163.com>
     */
    protected function syncModulesOnBoot(): void
    {
        // 只在非控制台命令时执行（避免影响 artisan 命令执行速度）
        if ($this->app->runningInConsole()) {
            return;
        }

        // 使用缓存控制扫描频率（每 5 分钟扫描一次）
        $cacheKey = 'gpa_modules_sync_timestamp';
        $syncInterval = 300; // 5 分钟（秒）

        $lastSync = cache()->get($cacheKey, 0);

        if (time() - $lastSync >= $syncInterval) {
            try {
                // 更新缓存时间戳（先更新，防止并发请求重复执行）
                cache()->put($cacheKey, time(), $syncInterval * 2);

                // 执行模块扫描同步
                Module::scanAndUpdateModules();
            } catch (\Exception $e) {
                // 记录日志但不影响应用运行
                Log::warning('模块自动同步失败: ' . $e->getMessage(), [
                    'exception' => $e->getTraceAsString(),
                ]);
            }
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @author siushin<siushin@163.com>
     */
    public function provides(): array
    {
        return [];
    }
}
