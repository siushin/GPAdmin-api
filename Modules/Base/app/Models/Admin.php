<?php

namespace Modules\Base\Models;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Base\Enums\AccountTypeEnum;
use Modules\Base\Enums\OperationActionEnum;
use Modules\Base\Enums\ResourceTypeEnum;
use Siushin\LaravelTool\Enums\SocialTypeEnum;
use Siushin\Util\Traits\ParamTool;

/**
 * 模型：管理员
 */
class Admin extends Model
{
    use ParamTool;

    protected $table = 'gpa_admin';

    protected $fillable = [
        'id',
        'account_id',
        'company_id',
        'department_id',
        'is_super',
    ];

    /**
     * 关联账号
     * @return BelongsTo
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    /**
     * 获取管理员列表
     * @param array $params
     * @return array
     * @author siushin<siushin@163.com>
     */
    public static function getPageData(array $params): array
    {
        $page = $params['page'] ?? 1;
        $pageSize = $params['pageSize'] ?? 10;

        $query = Account::query()
            ->where('account_type', AccountTypeEnum::Admin->value)
            ->with(['adminInfo', 'profile', 'socialAccounts'])
            ->when(isset($params['status']), function ($q) use ($params) {
                if (is_array($params['status'])) {
                    $q->whereIn('status', $params['status']);
                } else {
                    $q->where('status', $params['status']);
                }
            })
            ->when(!empty($params['keyword']), function ($q) use ($params) {
                $q->where(function ($query) use ($params) {
                    $query->where('username', 'like', "%{$params['keyword']}%")
                        ->orWhere('last_login_ip', 'like', "%{$params['keyword']}%")
                        ->orWhereHas('profile', function ($q) use ($params) {
                            $q->where('nickname', 'like', "%{$params['keyword']}%");
                        })
                        ->orWhereHas('socialAccounts', function ($q) use ($params) {
                            $q->where(function ($subQuery) use ($params) {
                                $subQuery->where('social_type', SocialTypeEnum::Phone->value)
                                    ->where('social_account', 'like', "%{$params['keyword']}%");
                            })->orWhere(function ($subQuery) use ($params) {
                                $subQuery->where('social_type', SocialTypeEnum::Email->value)
                                    ->where('social_account', 'like', "%{$params['keyword']}%");
                            });
                        });
                });
            })
            ->when(!empty($params['last_login_time']), function ($q) use ($params) {
                if (is_array($params['last_login_time']) && count($params['last_login_time']) === 2) {
                    $startTime = $params['last_login_time'][0];
                    $endTime = $params['last_login_time'][1];
                    // 如果结束时间不包含时分秒（只有日期部分），则设置为当天的最后一秒
                    if (strlen($endTime) <= 10 || !str_contains($endTime, ' ')) {
                        $endTime = $endTime . ' 23:59:59';
                    }
                    $q->whereBetween('last_login_time', [$startTime, $endTime]);
                }
            })
            ->when(!empty($params['created_at']), function ($q) use ($params) {
                if (is_array($params['created_at']) && count($params['created_at']) === 2) {
                    $startTime = $params['created_at'][0];
                    $endTime = $params['created_at'][1];
                    // 如果结束时间不包含时分秒（只有日期部分），则设置为当天的最后一秒
                    if (strlen($endTime) <= 10 || !str_contains($endTime, ' ')) {
                        $endTime = $endTime . ' 23:59:59';
                    }
                    $q->whereBetween('created_at', [$startTime, $endTime]);
                }
            });

        // 如果有is_super筛选
        if (isset($params['is_super'])) {
            $query->whereHas('adminInfo', function ($q) use ($params) {
                $q->where('is_super', $params['is_super']);
            });
        }

        $total = $query->count();
        $list = $query->orderBy('id', 'desc')
            ->skip(($page - 1) * $pageSize)
            ->take($pageSize)
            ->get()
            ->map(function ($account) {
                $adminInfo = $account->adminInfo;
                $profile = $account->profile;
                $socialAccounts = $account->socialAccounts;

                // 获取手机号
                $phone = $socialAccounts->firstWhere('social_type', SocialTypeEnum::Phone->value)?->social_account;
                // 获取邮箱
                $email = $socialAccounts->firstWhere('social_type', SocialTypeEnum::Email->value)?->social_account;

                return [
                    'account_id'          => $account->id,
                    'username'            => $account->username,
                    'nickname'            => $profile?->nickname,
                    'phone'               => $phone,
                    'email'               => $email,
                    'account_type'        => $account->account_type->value,
                    'status'              => $account->status,
                    'is_super'            => $adminInfo?->is_super ?? 0,
                    'company_id'          => $adminInfo?->company_id,
                    'department_id'       => $adminInfo?->department_id,
                    'last_login_ip'       => $account->last_login_ip,
                    'last_login_location' => getIpLocation($account->last_login_ip),
                    'last_login_time'     => $account->last_login_time?->format('Y-m-d H:i:s'),
                    'created_at'          => $account->created_at?->format('Y-m-d H:i:s'),
                    'updated_at'          => $account->updated_at?->format('Y-m-d H:i:s'),
                ];
            })
            ->toArray();

        // 批量查询公司名和部门名
        $companyIds = array_filter(array_unique(array_column($list, 'company_id')));
        $departmentIds = array_filter(array_unique(array_column($list, 'department_id')));

        // 批量查询公司
        $companies = [];
        if (!empty($companyIds)) {
            $companies = Company::query()
                ->whereIn('company_id', $companyIds)
                ->pluck('company_name', 'company_id')
                ->toArray();
        }

        // 批量查询部门
        $departments = [];
        if (!empty($departmentIds)) {
            $departments = Department::query()
                ->whereIn('department_id', $departmentIds)
                ->pluck('department_name', 'department_id')
                ->toArray();
        }

        // 遍历数据赋值公司名和部门名
        foreach ($list as &$item) {
            $item['company_name'] = $companies[$item['company_id']] ?? '';
            $item['department_name'] = $departments[$item['department_id']] ?? '';
        }

        return [
            'data' => $list,
            'page' => [
                'total'    => $total,
                'page'     => $page,
                'pageSize' => $pageSize,
            ],
        ];
    }

    /**
     * 新增管理员
     * @param array $params
     * @return array
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    public static function addAdmin(array $params): array
    {
        self::checkEmptyParam($params, ['username', 'password']);

        DB::beginTransaction();
        try {
            // 检查用户名是否已存在
            if (Account::query()->where('username', $params['username'])->exists()) {
                throw_exception('用户名已存在');
            }

            // 创建账号
            $account = new Account();
            $account->username = $params['username'];
            $account->password = Hash::make($params['password']);
            $account->account_type = AccountTypeEnum::Admin->value;
            $account->status = $params['status'] ?? 1;
            $account->save();

            // 创建管理员信息
            $admin = new self();
            $admin->account_id = $account->id;
            $admin->is_super = $params['is_super'] ?? 0;
            $admin->company_id = $params['company_id'] ?? null;
            $admin->department_id = $params['department_id'] ?? null;
            $admin->save();

            DB::commit();

            // 记录审计日志
            logAudit(
                request(),
                currentUserId(),
                '管理员列表',
                OperationActionEnum::add->value,
                ResourceTypeEnum::user->value,
                $account->id,
                null,
                $account->only(['id', 'username', 'account_type', 'status']),
                "新增管理员: {$account->username}"
            );

            return ['id' => $account->id];
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * 更新管理员
     * @param array $params
     * @return array
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    public static function updateAdmin(array $params): array
    {
        if (empty($params['account_id'])) {
            throw_exception('缺少 account_id 参数');
        }
        $accountId = $params['account_id'];

        DB::beginTransaction();
        try {
            $account = Account::query()->findOrFail($accountId);
            if ($account->account_type !== AccountTypeEnum::Admin) {
                throw_exception('该账号不是管理员账号');
            }

            // 保存旧数据
            $old_data = $account->only(['id', 'username', 'account_type', 'status']);

            // 更新账号信息
            if (isset($params['status'])) {
                $account->status = $params['status'];
            }
            if (isset($params['password']) && !empty($params['password'])) {
                $account->password = Hash::make($params['password']);
            }
            $account->save();

            // 更新管理员信息
            $admin = self::query()->where('account_id', $account->id)->first();
            if ($admin) {
                if (isset($params['is_super'])) {
                    $admin->is_super = $params['is_super'];
                }
                if (isset($params['company_id'])) {
                    $admin->company_id = $params['company_id'];
                }
                if (isset($params['department_id'])) {
                    $admin->department_id = $params['department_id'];
                }
                $admin->save();
            }

            DB::commit();

            // 记录审计日志
            $new_data = $account->fresh()->only(['id', 'username', 'account_type', 'status']);
            logAudit(
                request(),
                currentUserId(),
                '管理员列表',
                OperationActionEnum::update->value,
                ResourceTypeEnum::user->value,
                $account->id,
                $old_data,
                $new_data,
                "更新管理员: {$account->username}"
            );

            return [];
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * 删除管理员
     * @param array $params
     * @return array
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    public static function deleteAdmin(array $params): array
    {
        if (empty($params['account_id'])) {
            throw_exception('缺少 account_id 参数');
        }
        $accountId = $params['account_id'];

        $account = Account::query()->findOrFail($accountId);
        if ($account->account_type !== AccountTypeEnum::Admin) {
            throw_exception('该账号不是管理员账号');
        }

        // 保存旧数据
        $old_data = $account->only(['id', 'username', 'account_type', 'status']);

        // 删除账号（会级联删除管理员信息）
        $account->delete();

        // 记录审计日志
        logAudit(
            request(),
            currentUserId(),
            '管理员列表',
            OperationActionEnum::delete->value,
            ResourceTypeEnum::user->value,
            $account->id,
            $old_data,
            null,
            "删除管理员: {$account->username}"
        );

        return [];
    }

    /**
     * 获取管理员详情
     * @param array $params
     * @return array
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    public static function getAdminDetail(array $params): array
    {
        if (empty($params['account_id'])) {
            throw_exception('缺少 account_id 参数');
        }
        $accountId = $params['account_id'];

        $account = Account::query()
            ->where('id', $accountId)
            ->where('account_type', AccountTypeEnum::Admin->value)
            ->with(['adminInfo', 'profile', 'socialAccounts'])
            ->first();

        if (!$account) {
            throw_exception('管理员不存在');
        }

        $adminInfo = $account->adminInfo;
        $profile = $account->profile;
        $socialAccounts = $account->socialAccounts;

        // 获取公司信息
        $company = null;
        if ($adminInfo?->company_id) {
            $company = Company::query()
                ->where('company_id', $adminInfo->company_id)
                ->first();
        }

        // 获取部门信息
        $department = null;
        if ($adminInfo?->department_id) {
            $department = Department::query()
                ->where('department_id', $adminInfo->department_id)
                ->first();
        }

        // 处理社交账号信息
        $socialData = [];
        foreach ($socialAccounts as $social) {
            $socialData[] = [
                'id'             => $social->id,
                'social_type'    => $social->social_type->value ?? $social->social_type,
                'social_account' => $social->social_account,
                'social_name'    => $social->social_name,
                'avatar'         => $social->avatar,
                'is_verified'    => $social->is_verified,
                'verified_at'    => $social->verified_at?->format('Y-m-d H:i:s'),
                'created_at'     => $social->created_at?->format('Y-m-d H:i:s'),
                'updated_at'     => $social->updated_at?->format('Y-m-d H:i:s'),
            ];
        }

        return [
            'account' => [
                'id'                  => $account->id,
                'username'            => $account->username,
                'account_type'        => $account->account_type->value,
                'status'              => $account->status,
                'last_login_ip'       => $account->last_login_ip,
                'last_login_location' => getIpLocation($account->last_login_ip),
                'last_login_time'     => $account->last_login_time?->format('Y-m-d H:i:s'),
                'created_at'          => $account->created_at?->format('Y-m-d H:i:s'),
                'updated_at'          => $account->updated_at?->format('Y-m-d H:i:s'),
            ],
            'profile' => $profile ? [
                'id'                  => $profile->id,
                'nickname'            => $profile->nickname,
                'gender'              => $profile->gender,
                'avatar'              => $profile->avatar,
                'real_name'           => $profile->real_name,
                'id_card'             => $profile->id_card,
                'verification_method' => $profile->verification_method,
                'verified_at'         => $profile->verified_at?->format('Y-m-d H:i:s'),
                'created_at'          => $profile->created_at?->format('Y-m-d H:i:s'),
                'updated_at'          => $profile->updated_at?->format('Y-m-d H:i:s'),
            ] : null,
            'admin'   => $adminInfo ? [
                'account_id'      => $adminInfo->account_id,
                'is_super'        => $adminInfo->is_super,
                'company_id'      => $adminInfo->company_id,
                'company_name'    => $company?->company_name,
                'department_id'   => $adminInfo->department_id,
                'department_name' => $department?->department_name,
                'created_at'      => $adminInfo->created_at?->format('Y-m-d H:i:s'),
                'updated_at'      => $adminInfo->updated_at?->format('Y-m-d H:i:s'),
            ] : null,
            'social'  => $socialData,
        ];
    }
}


