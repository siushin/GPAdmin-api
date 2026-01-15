<?php

namespace Modules\Base\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\HasApiTokens;
use Modules\Base\Database\Factories\AccountFactory;
use Modules\Base\Enums\AccountTypeEnum;
use Modules\Base\Enums\OperationActionEnum;
use Modules\Base\Enums\ResourceTypeEnum;
use Siushin\Util\Traits\ParamTool;

/**
 * 模型：账号
 */
class Account extends Authenticatable
{
    /** @use HasFactory<AccountFactory> */
    use HasApiTokens, HasFactory, Notifiable, ParamTool, SoftDeletes;

    protected $table = 'gpa_account';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'password',
        'account_type',
        'status',
        'last_login_ip',
        'last_login_time',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'deleted_at',
        'created_at',
        'updated_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_login_time' => 'datetime',
            'password'        => 'hashed',
            'account_type'    => AccountTypeEnum::class,
        ];
    }

    /**
     * 获取 is_super 属性（访问器）
     * 从关联的 adminInfo 中获取，如果已通过 setAttribute 设置则直接返回
     * @return int|null
     */
    public function getIsSuperAttribute(): ?int
    {
        // 如果已经通过 setAttribute 设置，直接返回
        if (array_key_exists('is_super', $this->attributes)) {
            return $this->attributes['is_super'];
        }

        // 否则从关联关系获取
        if ($this->account_type === AccountTypeEnum::Admin) {
            $adminInfo = $this->relationLoaded('adminInfo') ? $this->adminInfo : $this->adminInfo;
            return $adminInfo?->is_super;
        }

        return null;
    }

    /**
     * 获取 company_id 属性（访问器）
     * 从关联的 adminInfo 中获取，如果已通过 setAttribute 设置则直接返回
     * @return int|null
     */
    public function getCompanyIdAttribute(): ?int
    {
        // 如果已经通过 setAttribute 设置，直接返回
        if (array_key_exists('company_id', $this->attributes)) {
            return $this->attributes['company_id'];
        }

        // 否则从关联关系获取
        if ($this->account_type === AccountTypeEnum::Admin) {
            $adminInfo = $this->relationLoaded('adminInfo') ? $this->adminInfo : $this->adminInfo;
            return $adminInfo?->company_id;
        }

        return null;
    }

    /**
     * 获取 department_id 属性（访问器）
     * 从关联的 adminInfo 中获取，如果已通过 setAttribute 设置则直接返回
     * @return int|null
     */
    public function getDepartmentIdAttribute(): ?int
    {
        // 如果已经通过 setAttribute 设置，直接返回
        if (array_key_exists('department_id', $this->attributes)) {
            return $this->attributes['department_id'];
        }

        // 否则从关联关系获取
        if ($this->account_type === AccountTypeEnum::Admin) {
            $adminInfo = $this->relationLoaded('adminInfo') ? $this->adminInfo : $this->adminInfo;
            return $adminInfo?->department_id;
        }

        return null;
    }

    /**
     * 管理员信息
     * @return HasOne
     */
    public function adminInfo(): HasOne
    {
        return $this->hasOne(Admin::class, 'account_id');
    }

    /**
     * 客户信息
     * @return HasOne
     */
    public function customerInfo(): HasOne
    {
        return $this->hasOne(User::class, 'account_id');
    }

    /**
     * 账号资料信息
     * @return HasOne
     */
    public function profile(): HasOne
    {
        return $this->hasOne(AccountProfile::class, 'account_id');
    }

    /**
     * 社交网络信息
     * @return HasMany
     */
    public function socialAccounts(): HasMany
    {
        return $this->hasMany(AccountSocial::class, 'account_id');
    }

    /**
     * 账号模块关联
     * @return HasMany
     */
    public function accountModules(): HasMany
    {
        return $this->hasMany(AccountModule::class, 'account_id', 'id');
    }

    /**
     * 账号关联的模块（多对多）
     * @return BelongsToMany
     */
    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(
            Module::class,
            'gpa_account_module',
            'account_id',
            'module_id',
            'id',
            'module_id'
        )->withTimestamps();
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return AccountFactory::new();
    }

    /**
     * 修改账号密码
     * @param array $params
     * @return array
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    public static function updatePassword(array $params): array
    {
        self::checkEmptyParam($params, ['account_id', 'password']);

        $info = self::query()->findOrFail($params['account_id']);
        !$info && throw_exception('账号不存在');

        // 保存旧数据（排除密码字段）
        $old_data = $info->only(['id', 'username', 'account_type', 'status', 'last_login_ip', 'last_login_time', 'created_at', 'updated_at']);

        $info->password = Hash::make($params['password']);
        $info->save();

        // 记录审计日志（不记录密码）
        $new_data = $info->fresh()->only(['id', 'username', 'account_type', 'status', 'last_login_ip', 'last_login_time', 'created_at', 'updated_at']);
        logAudit(
            request(),
            currentUserId(),
            '账号管理',
            OperationActionEnum::update->value,
            ResourceTypeEnum::user->value,
            $params['account_id'],
            $old_data,
            $new_data,
            "修改账号密码: $info->username"
        );

        return [];
    }

    /**
     * 根据账号IDs获取用户名（键值对 - 列表）
     * @param array $account_ids
     * @return Collection
     * @author siushin<siushin@163.com>
     */
    public static function getUsernameByIDs(array $account_ids): Collection
    {
        return self::query()->whereIn('id', $account_ids)->pluck('username', 'id');
    }
}
