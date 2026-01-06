<?php

namespace Modules\Base\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

/**
 * 生成控制器命令
 * 用法: php artisan gpa:controller User --module=Base --cn=用户管理
 */
class MakeControllerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gpa:controller 
                            {name : 控制器名称（不需要写Controller后缀）}
                            {--module=Base : 模块名称}
                            {--model= : 模型名称（默认与控制器名称相同）}
                            {--cn= : 控制器中文名称（用于注释和ControllerName注解）}
                            {--author= : 作者名称}
                            {--email= : 作者邮箱}
                            {--force : 强制覆盖已存在的文件}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '生成 GPA 风格的控制器文件';

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
        $model = $this->option('model') ?: $name;
        $cnName = $this->option('cn') ?: $name;
        $author = $this->getAuthor();
        $email = $this->getEmail();
        $force = $this->option('force');

        // 确保名称是 StudlyCase
        $name = Str::studly($name);
        $model = Str::studly($model);

        // 检查模块是否存在
        $modulePath = base_path("Modules/{$module}");
        if (!$this->files->isDirectory($modulePath)) {
            $this->error("模块 [{$module}] 不存在！");
            return self::FAILURE;
        }

        // 生成控制器
        $controllerPath = "{$modulePath}/app/Http/Controllers/{$name}Controller.php";
        
        if ($this->files->exists($controllerPath) && !$force) {
            $this->error("控制器 [{$name}Controller] 已存在！使用 --force 选项强制覆盖。");
            return self::FAILURE;
        }

        // 确保目录存在
        $this->files->ensureDirectoryExists(dirname($controllerPath));

        // 获取模板内容
        $stub = $this->getStub();

        // 替换占位符
        $content = $this->replacePlaceholders($stub, [
            'namespace' => "Modules\\{$module}\\Http\\Controllers",
            'module' => $module,
            'class' => $name,
            'model' => $model,
            'name' => $cnName,
            'author' => $author,
            'email' => $email,
        ]);

        // 写入文件
        $this->files->put($controllerPath, $content);

        $this->info("控制器 [{$name}Controller] 创建成功！");
        $this->line("  路径: {$controllerPath}");

        // 提示下一步操作
        $this->newLine();
        $this->comment('下一步操作：');
        $this->line("  1. 创建模型: php artisan module:make-model {$model} {$module}");
        $this->line("  2. 在路由文件中添加路由: Modules/{$module}/routes/api.php");

        return self::SUCCESS;
    }

    /**
     * 获取作者名称
     * 优先级：命令行参数 > config > env > 默认值
     */
    protected function getAuthor(): string
    {
        $author = $this->option('author');
        
        if (!empty($author)) {
            return $author;
        }

        // 尝试从 config 获取
        $configAuthor = config('laravel-tool.author');
        if (!empty($configAuthor)) {
            return $configAuthor;
        }

        // 尝试从 env 获取
        $envAuthor = env('APP_AUTHOR');
        if (!empty($envAuthor)) {
            return $envAuthor;
        }

        return 'GPA';
    }

    /**
     * 获取作者邮箱
     * 优先级：命令行参数 > config > env > 默认值
     */
    protected function getEmail(): string
    {
        $email = $this->option('email');
        
        if (!empty($email)) {
            return $email;
        }

        // 尝试从 config 获取
        $configEmail = config('laravel-tool.email');
        if (!empty($configEmail)) {
            return $configEmail;
        }

        // 尝试从 env 获取
        $envEmail = env('APP_EMAIL');
        if (!empty($envEmail)) {
            return $envEmail;
        }

        return 'gpa@example.com';
    }

    /**
     * 获取模板内容
     */
    protected function getStub(): string
    {
        $stubPath = base_path('Modules/Base/stubs/controller.stub');

        if (!$this->files->exists($stubPath)) {
            throw new \RuntimeException("模板文件不存在: {$stubPath}");
        }

        return $this->files->get($stubPath);
    }

    /**
     * 替换占位符
     */
    protected function replacePlaceholders(string $stub, array $replacements): string
    {
        foreach ($replacements as $key => $value) {
            $stub = str_replace("{{ {$key} }}", $value, $stub);
        }

        return $stub;
    }
}
