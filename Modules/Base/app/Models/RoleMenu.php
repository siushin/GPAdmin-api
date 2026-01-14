<?php

namespace Modules\Base\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 模型：角色菜单关联
 */
class RoleMenu extends Model
{
    protected $table = 'gpa_role_menu';

    protected $fillable = [
        'role_id',
        'menu_id',
        'target_module_id',
    ];

    protected $casts = [
        'role_id'          => 'integer',
        'menu_id'          => 'integer',
        'target_module_id' => 'integer',
    ];

    /**
     * 关联角色
     * @return BelongsTo
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id', 'role_id');
    }

    /**
     * 关联菜单
     * @return BelongsTo
     */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class, 'menu_id', 'menu_id');
    }

    /**
     * 关联目标模块
     * @return BelongsTo
     */
    public function targetModule(): BelongsTo
    {
        return $this->belongsTo(Module::class, 'target_module_id', 'module_id');
    }

    /**
     * 检查菜单是否已被移动到其他模块
     * @return bool
     */
    public function isMoved(): bool
    {
        return $this->target_module_id !== null;
    }
}

