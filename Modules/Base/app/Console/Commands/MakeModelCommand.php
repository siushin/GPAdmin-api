<?php

namespace Modules\Base\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

/**
 * 生成模型命令
 * 用法: php artisan gpa:model User --module=Base --cn=用户
 */
class MakeModelCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gpa:model 
                            {name : 模型名称}
                            {--module=Base : 模块名称}
                            {--table= : 数据表名称（默认根据模型名称生成）}
                            {--pk= : 主键名称（默认根据模型名称生成，如：user_id）}
                            {--cn= : 模型中文名称（用于注释）}
                            {--author= : 作者名称}
                            {--email= : 作者邮箱}
                            {--force : 强制覆盖已存在的文件}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '生成 GPA 风格的模型文件';

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
        $author = $this->getAuthor();
        $email = $this->getEmail();
        $force = $this->option('force');

        // 确保名称是 StudlyCase
        $name = Str::studly($name);

        // 生成表名（默认：gpa_snake_case）
        $table = $this->option('table') ?: 'gpa_' . Str::snake($name);

        // 生成主键名（默认：snake_case_id）
        $pk = $this->option('pk') ?: Str::snake($name) . '_id';

        // 检查模块是否存在
        $modulePath = base_path("Modules/{$module}");
        if (!$this->files->isDirectory($modulePath)) {
            $this->error("模块 [{$module}] 不存在！");
            return self::FAILURE;
        }

        // 生成模型
        $modelPath = "{$modulePath}/app/Models/{$name}.php";
        
        if ($this->files->exists($modelPath) && !$force) {
            $this->error("模型 [{$name}] 已存在！使用 --force 选项强制覆盖。");
            return self::FAILURE;
        }

        // 确保目录存在
        $this->files->ensureDirectoryExists(dirname($modelPath));

        // 获取模板内容
        $stub = $this->getStub();

        // 替换占位符
        $content = $this->replacePlaceholders($stub, [
            'namespace' => "Modules\\{$module}\\Models",
            'class' => $name,
            'name' => $cnName,
            'table' => $table,
            'primaryKey' => $pk,
            'author' => $author,
            'email' => $email,
        ]);

        // 写入文件
        $this->files->put($modelPath, $content);

        $this->info("模型 [{$name}] 创建成功！");
        $this->line("  路径: {$modelPath}");
        $this->line("  表名: {$table}");
        $this->line("  主键: {$pk}");

        // 提示下一步操作
        $this->newLine();
        $this->comment('下一步操作：');
        $this->line("  1. 创建迁移文件: php artisan module:make-migration create_{$table}_table {$module}");
        $this->line("  2. 创建控制器: php artisan gpa:controller {$name} --module={$module} --cn={$cnName}");

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
        $stubPath = base_path('Modules/Base/stubs/model.stub');

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

