<?php

namespace Modules\Base\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

/**
 * 生成 API 命令（同时创建控制器和模型）
 * 用法: php artisan gpa:api User --module=Base --cn=用户
 */
class MakeApiCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gpa:api
                            {name : API 名称（将同时用于控制器和模型）}
                            {--module=Base : 模块名称}
                            {--table= : 数据表名称（默认根据名称生成）}
                            {--pk= : 主键名称（默认根据名称生成，如：user_id）}
                            {--cn= : 中文名称（用于注释）}
                            {--author= : 作者名称}
                            {--email= : 作者邮箱}
                            {--only-controller : 仅创建控制器}
                            {--only-model : 仅创建模型}
                            {--force : 强制覆盖已存在的文件}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '生成 GPA 风格的 API（同时创建控制器和模型）';

    /**
     * 文件系统实例
     *
     * @var Filesystem
     */
    protected Filesystem $files;

    /**
     * Create a new command instance.
     */
    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = $this->argument('name');
        $module = $this->option('module');
        $cnName = $this->option('cn') ?: $name;
        $table = $this->option('table');
        $pk = $this->option('pk');
        $author = $this->getAuthor();
        $email = $this->getEmail();
        $force = $this->option('force');
        $onlyController = $this->option('only-controller');
        $onlyModel = $this->option('only-model');

        // 确保名称是 StudlyCase
        $name = Str::studly($name);

        // 检查模块是否存在
        $modulePath = base_path("Modules/{$module}");
        if (!$this->files->isDirectory($modulePath)) {
            $this->error("模块 [{$module}] 不存在！");
            return self::FAILURE;
        }

        $this->info("开始生成 API: {$name}");
        $this->newLine();

        $modelCreated = false;
        $controllerCreated = false;

        // 创建模型
        if (!$onlyController) {
            $modelCreated = $this->createModel($name, $module, $cnName, $table, $pk, $author, $email, $force);
        }

        // 创建控制器
        if (!$onlyModel) {
            $controllerCreated = $this->createController($name, $module, $cnName, $author, $email, $force);
        }

        // 输出汇总信息
        $this->newLine();
        $this->info('═══════════════════════════════════════════════════════════');
        $this->info('  API 生成完成！');
        $this->info('═══════════════════════════════════════════════════════════');

        if ($modelCreated) {
            $tableName = $table ?: 'gpa_' . Str::snake($name);
            $pkName = $pk ?: Str::snake($name) . '_id';
            $this->line("  ✓ 模型: Modules/{$module}/app/Models/{$name}.php");
            $this->line("    表名: {$tableName}");
            $this->line("    主键: {$pkName}");
        }

        if ($controllerCreated) {
            $this->line("  ✓ 控制器: Modules/{$module}/app/Http/Controllers/{$name}Controller.php");
        }

        // 下一步提示
        $this->newLine();
        $this->comment('下一步操作：');

        if ($modelCreated) {
            $tableName = $table ?: 'gpa_' . Str::snake($name);
            $this->line("  1. 创建迁移文件:");
            $this->line("     php artisan module:make-migration create_{$tableName}_table {$module}");
            $this->newLine();
            $this->line("  2. 编辑迁移文件，添加表字段");
            $this->newLine();
            $this->line("  3. 执行迁移:");
            $this->line("     php artisan migrate");
            $this->newLine();
        }

        $this->line("  " . ($modelCreated ? '4' : '1') . ". 在路由文件中添加路由:");
        $this->line("     Modules/{$module}/routes/api.php");
        $this->newLine();

        $routeName = Str::snake($name, '-');
        $this->comment("路由示例：");
        $this->line("  Route::prefix('{$routeName}')->controller({$name}Controller::class)->group(function () {");
        $this->line("      Route::get('list', 'list');");
        $this->line("      Route::get('index', 'index');");
        $this->line("      Route::post('add', 'add');");
        $this->line("      Route::post('update', 'update');");
        $this->line("      Route::post('delete', 'delete');");
        $this->line("  });");

        return self::SUCCESS;
    }

    /**
     * 创建模型
     */
    protected function createModel(string $name, string $module, string $cnName, ?string $table, ?string $pk, string $author, string $email, bool $force): bool
    {
        $table = $table ?: 'gpa_' . Str::snake($name);
        $pk = $pk ?: Str::snake($name) . '_id';

        $modulePath = base_path("Modules/{$module}");
        $modelPath = "{$modulePath}/app/Models/{$name}.php";

        if ($this->files->exists($modelPath) && !$force) {
            $this->warn("  ⚠ 模型 [{$name}] 已存在，跳过创建");
            return false;
        }

        // 获取模板
        $stubPath = base_path('Modules/Base/stubs/model.stub');
        if (!$this->files->exists($stubPath)) {
            $this->error("  ✗ 模型模板文件不存在: {$stubPath}");
            return false;
        }

        $stub = $this->files->get($stubPath);

        // 替换占位符
        $content = str_replace(
            ['{{ namespace }}', '{{ class }}', '{{ name }}', '{{ table }}', '{{ primaryKey }}', '{{ author }}', '{{ email }}'],
            ["Modules\\{$module}\\Models", $name, $cnName, $table, $pk, $author, $email],
            $stub
        );

        // 确保目录存在
        $this->files->ensureDirectoryExists(dirname($modelPath));

        // 写入文件
        $this->files->put($modelPath, $content);

        $this->info("  ✓ 模型 [{$name}] 创建成功");

        return true;
    }

    /**
     * 创建控制器
     */
    protected function createController(string $name, string $module, string $cnName, string $author, string $email, bool $force): bool
    {
        $modulePath = base_path("Modules/{$module}");
        $controllerPath = "{$modulePath}/app/Http/Controllers/{$name}Controller.php";

        if ($this->files->exists($controllerPath) && !$force) {
            $this->warn("  ⚠ 控制器 [{$name}Controller] 已存在，跳过创建");
            return false;
        }

        // 获取模板
        $stubPath = base_path('Modules/Base/stubs/controller.stub');
        if (!$this->files->exists($stubPath)) {
            $this->error("  ✗ 控制器模板文件不存在: {$stubPath}");
            return false;
        }

        $stub = $this->files->get($stubPath);

        // 替换占位符
        $content = str_replace(
            ['{{ namespace }}', '{{ class }}', '{{ controllerCnName }}', '{{ model }}', '{{ author }}'],
            ["Modules\\{$module}\\Http\\Controllers", "{$name}Controller", $cnName, $name, "{$author}<{$email}>"],
            $stub
        );

        // 确保目录存在
        $this->files->ensureDirectoryExists(dirname($controllerPath));

        // 写入文件
        $this->files->put($controllerPath, $content);

        $this->info("  ✓ 控制器 [{$name}Controller] 创建成功");

        return true;
    }

    /**
     * 获取作者名称
     */
    protected function getAuthor(): string
    {
        $author = $this->option('author');

        if (!empty($author)) {
            return $author;
        }

        $configAuthor = config('laravel-tool.author');
        if (!empty($configAuthor)) {
            return $configAuthor;
        }

        $envAuthor = env('APP_AUTHOR');
        if (!empty($envAuthor)) {
            return $envAuthor;
        }

        return 'GPA';
    }

    /**
     * 获取作者邮箱
     */
    protected function getEmail(): string
    {
        $email = $this->option('email');

        if (!empty($email)) {
            return $email;
        }

        $configEmail = config('laravel-tool.email');
        if (!empty($configEmail)) {
            return $configEmail;
        }

        $envEmail = env('APP_EMAIL');
        if (!empty($envEmail)) {
            return $envEmail;
        }

        return 'gpa@example.com';
    }
}

