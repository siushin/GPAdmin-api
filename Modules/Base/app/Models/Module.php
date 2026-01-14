<?php

namespace Modules\Base\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\File;

/**
 * 模型：模块
 */
class Module extends Model
{
    use SoftDeletes;

    protected $primaryKey = 'module_id';
    protected $table      = 'gpa_modules';

    protected $fillable = [
        'module_id',
        'module_name',
        'module_alias',
        'module_title',
        'module_desc',
        'module_icon',
        'module_version',
        'module_priority',
        'module_source',
        'module_status',
        'module_is_core',
        'module_is_installed',
        'module_installed_at',
        'module_author',
        'module_author_email',
        'module_homepage',
        'module_keywords',
        'module_providers',
        'module_dependencies',
        'uploader_id',
    ];

    protected $casts = [
        'module_priority'     => 'integer',
        'module_status'       => 'integer',
        'module_is_core'      => 'integer',
        'module_is_installed' => 'integer',
        'module_installed_at' => 'datetime',
        'module_keywords'     => 'array',
        'module_providers'    => 'array',
        'module_dependencies' => 'array',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * 获取上传人
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'uploader_id', 'id');
    }

    /**
     * 获取模块关联的菜单（通过中间表）
     */
    public function menus(): BelongsToMany
    {
        return $this->belongsToMany(
            Menu::class,
            'gpa_module_menu',
            'module_id',
            'menu_id',
            'module_id',
            'menu_id'
        )->withPivot(['original_module_id', 'is_root', 'moved_at', 'moved_by'])
            ->withTimestamps();
    }

    /**
     * 获取当前归属于此模块的菜单（直接关联）
     */
    public function currentMenus(): HasMany
    {
        return $this->hasMany(Menu::class, 'module_id', 'module_id');
    }

    /**
     * 获取原始归属于此模块的菜单（用于还原）
     */
    public function originalMenus(): HasMany
    {
        return $this->hasMany(Menu::class, 'original_module_id', 'module_id');
    }

    /**
     * 获取模块菜单关联记录
     */
    public function moduleMenus(): HasMany
    {
        return $this->hasMany(ModuleMenu::class, 'module_id', 'module_id');
    }

    /**
     * 检查模块是否已启用
     */
    public function isEnabled(): bool
    {
        return $this->module_status === 1;
    }

    /**
     * 检查模块是否为核心模块
     */
    public function isCoreModule(): bool
    {
        return $this->module_is_core === 1;
    }

    /**
     * 检查模块是否已安装
     */
    public function isInstalled(): bool
    {
        return $this->module_is_installed === 1;
    }

    /**
     * 扫描并更新模块数据
     * @param string|null $modulePath 指定模块路径（模块根目录），为 null 时扫描所有模块
     * @return array 返回更新结果 ['success' => [], 'failed' => []]
     */
    public static function scanAndUpdateModules(?string $modulePath = null): array
    {
        $result = [
            'success' => [],
            'failed'  => [],
        ];

        $modulesBasePath = base_path('Modules');

        // 如果指定了模块路径，只处理该模块
        if ($modulePath !== null) {
            $modulePath = rtrim($modulePath, DIRECTORY_SEPARATOR);
            // 如果是相对路径，转换为绝对路径
            // 判断是否为绝对路径：Unix系统以 / 开头，Windows系统以 C:\ 等开头
            $isAbsolute = (DIRECTORY_SEPARATOR === '/' && str_starts_with($modulePath, '/')) ||
                          (DIRECTORY_SEPARATOR === '\\' && preg_match('/^[A-Z]:\\\\/i', $modulePath));
            if (!$isAbsolute) {
                $modulePath = $modulesBasePath . DIRECTORY_SEPARATOR . $modulePath;
            }

            if (!File::isDirectory($modulePath)) {
                $result['failed'][] = [
                    'path'    => $modulePath,
                    'message' => '模块目录不存在',
                ];
                return $result;
            }

            $moduleDirs = [$modulePath];
        } else {
            // 扫描所有模块
            if (!File::exists($modulesBasePath)) {
                return $result;
            }

            $moduleDirs = File::directories($modulesBasePath);
        }

        foreach ($moduleDirs as $dir) {
            $moduleJsonPath = $dir . DIRECTORY_SEPARATOR . 'module.json';

            if (!File::exists($moduleJsonPath)) {
                $result['failed'][] = [
                    'path'    => $dir,
                    'message' => 'module.json 文件不存在',
                ];
                continue;
            }

            try {
                // 读取 module.json
                $moduleJsonContent = File::get($moduleJsonPath);
                $moduleData = json_decode($moduleJsonContent, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    $result['failed'][] = [
                        'path'    => $dir,
                        'message' => 'module.json 解析失败: ' . json_last_error_msg(),
                    ];
                    continue;
                }

                // 解析模块数据
                $moduleName = $moduleData['name'] ?? basename($dir);
                $moduleAlias = $moduleData['alias'] ?? strtolower($moduleName);
                $moduleTitle = $moduleData['title'] ?? $moduleAlias;
                $moduleDesc = $moduleData['description'] ?? '';
                $modulePriority = $moduleData['priority'] ?? 0;
                $moduleSource = $moduleData['source'] ?? 'third_party';
                $moduleKeywords = $moduleData['keywords'] ?? [];
                $moduleProviders = $moduleData['providers'] ?? [];

                // 处理 source 字段映射（"官方" -> "official"）
                $sourceMap = [
                    '官方'      => 'official',
                    'official'  => 'official',
                    '第三方'    => 'third_party',
                    'third_party' => 'third_party',
                    '自定义'    => 'custom',
                    'custom'   => 'custom',
                ];
                $moduleSource = $sourceMap[$moduleSource] ?? 'third_party';

                // 获取 extra->meta 中的数据
                $meta = $moduleData['extra']['meta'] ?? [];
                $moduleIcon = $meta['module_icon'] ?? '';
                $moduleVersion = $meta['module_version'] ?? null;
                $moduleStatus = $meta['module_status'] ?? 1;
                $moduleIsCore = $meta['module_is_core'] ?? 0;
                $moduleIsInstalled = $meta['module_is_installed'] ?? 1;
                $moduleAuthor = $meta['module_author'] ?? null;
                $moduleAuthorEmail = $meta['module_author_email'] ?? null;
                $moduleHomepage = $meta['module_homepage'] ?? null;
                $moduleDependencies = $meta['module_dependencies'] ?? [];

                // 查找或创建模块
                $module = self::where('module_name', $moduleName)->first();

                $moduleDataToUpdate = [
                    'module_name'         => $moduleName,
                    'module_alias'        => $moduleAlias,
                    'module_title'        => $moduleTitle,
                    'module_desc'         => $moduleDesc,
                    'module_icon'         => $moduleIcon,
                    'module_version'      => $moduleVersion,
                    'module_priority'     => $modulePriority,
                    'module_source'       => $moduleSource,
                    'module_status'       => $moduleStatus,
                    'module_is_core'      => $moduleIsCore,
                    'module_is_installed' => $moduleIsInstalled,
                    'module_author'       => $moduleAuthor,
                    'module_author_email' => $moduleAuthorEmail,
                    'module_homepage'     => $moduleHomepage,
                    'module_keywords'     => $moduleKeywords,
                    'module_providers'    => $moduleProviders,
                    'module_dependencies' => $moduleDependencies,
                ];

                if ($module) {
                    // 更新现有模块
                    // 如果模块已安装但 installed_at 为空，设置安装时间
                    if ($moduleIsInstalled && !$module->module_installed_at) {
                        $moduleDataToUpdate['module_installed_at'] = now();
                    } elseif (!$moduleIsInstalled) {
                        // 如果模块未安装，清除安装时间
                        $moduleDataToUpdate['module_installed_at'] = null;
                    }
                    $module->update($moduleDataToUpdate);
                } else {
                    // 创建新模块
                    $moduleDataToUpdate['module_id'] = generateId();
                    if ($moduleIsInstalled) {
                        $moduleDataToUpdate['module_installed_at'] = now();
                    }
                    self::create($moduleDataToUpdate);
                }

                $result['success'][] = [
                    'module_name' => $moduleName,
                    'path'        => $dir,
                ];
            } catch (\Exception $e) {
                $result['failed'][] = [
                    'path'    => $dir,
                    'message' => '处理失败: ' . $e->getMessage(),
                ];
            }
        }

        return $result;
    }
}
