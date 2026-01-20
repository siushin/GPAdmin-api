<?php

namespace Modules\Base\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 模型：账号模块关联
 */
class AccountModule extends Model
{
    protected $table = 'gpa_account_module';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'id',
        'account_id',
        'module_id',
        'sort',
    ];

    /**
     * 关联账号
     * @return BelongsTo
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id', 'id');
    }

    /**
     * 关联模块
     * @return BelongsTo
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class, 'module_id', 'module_id');
    }
}

