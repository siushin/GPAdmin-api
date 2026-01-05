<?php

namespace Modules\Base\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Base\Enums\AccountTypeEnum;

/**
 * 模型：账号部门关联
 */
class AccountDepartment extends Model
{
    protected $table = 'gpa_account_department';

    protected $fillable = [
        'id',
        'account_id',
        'account_type',
        'department_id',
        'sort',
    ];

    protected $casts = [
        'account_type' => AccountTypeEnum::class,
    ];
}

