<?php

namespace Modules\Base\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 模型：模块
 */
class Module extends Model
{
    use SoftDeletes;

    protected $primaryKey = 'module_id';
    protected $table      = 'gpa_module';

    protected $fillable = [
        'module_id',
        'module_name',
        'module_alias',
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
}
