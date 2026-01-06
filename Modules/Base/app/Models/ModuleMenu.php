<?php

namespace Modules\Base\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 模型：模块菜单关联
 */
class ModuleMenu extends Model
{
    protected $table = 'gpa_module_menu';

    protected $fillable = [
        'id',
        'module_id',
        'menu_id',
        'original_module_id',
        'is_root',
        'moved_at',
        'moved_by',
    ];

    public $timestamps = true;

    protected $casts = [
        'module_id'          => 'integer',
        'menu_id'            => 'integer',
        'original_module_id' => 'integer',
        'is_root'            => 'integer',
        'moved_by'           => 'integer',
        'moved_at'           => 'datetime',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    /**
     * 获取关联的模块
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class, 'module_id', 'module_id');
    }

    /**
     * 获取关联的菜单
     */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class, 'menu_id', 'menu_id');
    }

    /**
     * 获取原始模块
     */
    public function originalModule(): BelongsTo
    {
        return $this->belongsTo(Module::class, 'original_module_id', 'module_id');
    }

    /**
     * 获取移动操作人
     */
    public function mover(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'moved_by', 'id');
    }

    /**
     * 检查是否已被移动
     */
    public function isMoved(): bool
    {
        return $this->original_module_id !== null && $this->module_id !== $this->original_module_id;
    }

    /**
     * 记录移动操作
     * @param int      $targetModuleId 目标模块ID
     * @param int|null $operatorId     操作人ID
     * @return bool
     */
    public function recordMove(int $targetModuleId, ?int $operatorId = null): bool
    {
        // 如果是首次移动，记录原始模块ID
        if ($this->original_module_id === null) {
            $this->original_module_id = $this->module_id;
        }

        $this->module_id = $targetModuleId;
        $this->moved_at = now();
        $this->moved_by = $operatorId;

        return $this->save();
    }

    /**
     * 还原到原始模块
     * @return bool
     */
    public function restoreToOriginal(): bool
    {
        if ($this->original_module_id === null) {
            return false;
        }

        $this->module_id = $this->original_module_id;
        $this->original_module_id = null;
        $this->moved_at = null;
        $this->moved_by = null;

        return $this->save();
    }
}
