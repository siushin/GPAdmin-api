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
use Modules\Base\Models\UserRole;

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

        // 如果有is_super筛选（需要检查值是否有效，排除空字符串和null）
        if (isset($params['is_super']) && $params['is_super'] !== '') {
            $query->whereHas('adminInfo', function ($q) use ($params) {
                $q->where('is_super', $params['is_super']);
            });
        }

        // 如果有company_id筛选（需要检查值是否有效，排除空字符串和null）
        if (isset($params['company_id']) && $params['company_id'] !== '') {
            $query->whereHas('adminInfo', function ($q) use ($params) {
                $q->where('company_id', $params['company_id']);
            });
        }

        // 如果有department_id筛选（需要检查值是否有效，排除空字符串和null）
        if (isset($params['department_id']) && $params['department_id'] !== '') {
            $query->whereExists(function ($q) use ($params) {
                $q->select(DB::raw(1))
                    ->from('gpa_account_department')
                    ->whereColumn('gpa_account_department.account_id', 'gpa_account.id')
                    ->where('gpa_account_department.account_type', AccountTypeEnum::Admin->value)
                    ->where('gpa_account_department.department_id', $params['department_id']);
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
                    'last_login_ip'       => $account->last_login_ip,
                    'last_login_location' => getIpLocation($account->last_login_ip),
                    'last_login_time'     => $account->last_login_time?->format('Y-m-d H:i:s'),
                    'created_at'          => $account->created_at?->format('Y-m-d H:i:s'),
                    'updated_at'          => $account->updated_at?->format('Y-m-d H:i:s'),
                ];
            })
            ->toArray();

        // 批量查询公司名
        $companyIds = array_filter(array_unique(array_column($list, 'company_id')));
        $companies = [];
        if (!empty($companyIds)) {
            $companies = Company::query()
                ->whereIn('company_id', $companyIds)
                ->pluck('company_name', 'company_id')
                ->toArray();
        }

        // 遍历数据赋值公司名
        foreach ($list as &$item) {
            $item['company_name'] = $companies[$item['company_id']] ?? '';
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
     * 获取管理员列表（全部）
     * @param array $params
     * @return array
     * @author siushin<siushin@163.com>
     */
    public static function getAllData(array $params = []): array
    {
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
                    if (strlen($endTime) <= 10 || !str_contains($endTime, ' ')) {
                        $endTime = $endTime . ' 23:59:59';
                    }
                    $q->whereBetween('created_at', [$startTime, $endTime]);
                }
            });

        // 如果有is_super筛选
        if (isset($params['is_super']) && $params['is_super'] !== '') {
            $query->whereHas('adminInfo', function ($q) use ($params) {
                $q->where('is_super', $params['is_super']);
            });
        }

        // 如果有company_id筛选
        if (isset($params['company_id']) && $params['company_id'] !== '') {
            $query->whereHas('adminInfo', function ($q) use ($params) {
                $q->where('company_id', $params['company_id']);
            });
        }

        // 如果有department_id筛选（需要检查值是否有效，排除空字符串和null）
        if (isset($params['department_id']) && $params['department_id'] !== '') {
            $query->whereExists(function ($q) use ($params) {
                $q->select(DB::raw(1))
                    ->from('gpa_account_department')
                    ->whereColumn('gpa_account_department.account_id', 'gpa_account.id')
                    ->where('gpa_account_department.account_type', AccountTypeEnum::Admin->value)
                    ->where('gpa_account_department.department_id', $params['department_id']);
            });
        }

        $list = $query->orderBy('id', 'desc')
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
                    'name'                => $profile?->nickname,
                    'account'             => $account->username,
                    'phone'               => $phone,
                    'email'               => $email,
                    'account_type'        => $account->account_type->value,
                    'status'              => $account->status,
                    'is_super'            => $adminInfo?->is_super ?? 0,
                    'company_id'          => $adminInfo?->company_id,
                    'last_login_ip'       => $account->last_login_ip,
                    'last_login_location' => getIpLocation($account->last_login_ip),
                    'last_login_time'     => $account->last_login_time?->format('Y-m-d H:i:s'),
                    'created_at'          => $account->created_at?->format('Y-m-d H:i:s'),
                    'updated_at'          => $account->updated_at?->format('Y-m-d H:i:s'),
                ];
            })
            ->toArray();

        // 批量查询公司名
        $companyIds = array_filter(array_unique(array_column($list, 'company_id')));
        $companies = [];
        if (!empty($companyIds)) {
            $companies = Company::query()
                ->whereIn('company_id', $companyIds)
                ->pluck('company_name', 'company_id')
                ->toArray();
        }

        // 遍历数据赋值公司名
        foreach ($list as &$item) {
            $item['company_name'] = $companies[$item['company_id']] ?? '';
        }

        return $list;
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
            // 检查用户名是否已存在（排除软删除的账号）
            $existingAccount = Account::query()
                ->where('username', $params['username'])
                ->whereNull('deleted_at')
                ->first();
            if ($existingAccount) {
                throw_exception('用户名已存在');
            }

            // 检查是否有被软删除的账号使用了该用户名
            // 如果有，需要先修改被软删除账号的用户名，或者直接删除该记录
            $deletedAccount = Account::query()
                ->withTrashed()
                ->where('username', $params['username'])
                ->whereNotNull('deleted_at')
                ->first();
            if ($deletedAccount) {
                // 修改被软删除账号的用户名，添加时间戳后缀，确保唯一性
                $deletedAccount->username = $params['username'] . '_deleted_' . time();
                $deletedAccount->save();
            }

            // 检查手机号是否已存在（排除软删除的账号）
            if (!empty($params['phone'])) {
                $existingPhone = AccountSocial::query()
                    ->where('social_type', SocialTypeEnum::Phone->value)
                    ->where('social_account', $params['phone'])
                    ->whereHas('account', function ($q) {
                        $q->whereNull('deleted_at'); // 排除软删除的账号
                    })
                    ->exists();
                if ($existingPhone) {
                    throw_exception('手机号已被使用');
                }
            }

            // 检查邮箱是否已存在（排除软删除的账号）
            if (!empty($params['email'])) {
                $existingEmail = AccountSocial::query()
                    ->where('social_type', SocialTypeEnum::Email->value)
                    ->where('social_account', $params['email'])
                    ->whereHas('account', function ($q) {
                        $q->whereNull('deleted_at'); // 排除软删除的账号
                    })
                    ->exists();
                if ($existingEmail) {
                    throw_exception('邮箱已被使用');
                }
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
            $admin->save();

            // 创建账号资料
            if (!empty($params['nickname'])) {
                AccountProfile::create([
                    'id'         => generateId(),
                    'account_id' => $account->id,
                    'nickname'   => $params['nickname'],
                ]);
            }

            // 创建手机号社交账号记录
            if (!empty($params['phone'])) {
                AccountSocial::create([
                    'account_id'     => $account->id,
                    'social_type'    => SocialTypeEnum::Phone->value,
                    'social_account' => $params['phone'],
                    'is_verified'    => false,
                ]);
            }

            // 创建邮箱社交账号记录
            if (!empty($params['email'])) {
                AccountSocial::create([
                    'account_id'     => $account->id,
                    'social_type'    => SocialTypeEnum::Email->value,
                    'social_account' => $params['email'],
                    'is_verified'    => false,
                ]);
            }

            // 处理部门关联（department_ids）
            if (isset($params['department_ids']) && is_array($params['department_ids']) && !empty($params['department_ids'])) {
                $departmentIds = array_filter($params['department_ids'], function ($id) {
                    return !empty($id) && $id > 0;
                });

                if (!empty($departmentIds)) {
                    $companyId = $admin->company_id;

                    // 验证部门是否属于当前公司
                    if ($companyId) {
                        $validDepartments = Department::query()
                            ->whereIn('department_id', $departmentIds)
                            ->where('company_id', $companyId)
                            ->pluck('department_id')
                            ->toArray();

                        if (count($validDepartments) !== count($departmentIds)) {
                            throw_exception('部分部门不属于当前公司，无法关联');
                        }
                    }

                    // 创建部门关联记录
                    $insertData = [];
                    foreach ($departmentIds as $index => $departmentId) {
                        $insertData[] = [
                            'account_id'    => $account->id,
                            'account_type'  => AccountTypeEnum::Admin->value,
                            'department_id' => $departmentId,
                            'sort'          => $index,
                            'created_at'    => now(),
                            'updated_at'    => now(),
                        ];
                    }

                    if (!empty($insertData)) {
                        AccountDepartment::query()->insert($insertData);
                    }
                }
            }

            // 处理角色关联（role_ids）
            if (isset($params['role_ids']) && is_array($params['role_ids'])) {
                $roleIds = array_filter($params['role_ids'], function ($id) {
                    return !empty($id) && $id > 0;
                });

                if (!empty($roleIds)) {
                    // 验证角色是否存在且属于管理员类型
                    $validRoles = Role::query()
                        ->whereIn('role_id', $roleIds)
                        ->where('account_type', AccountTypeEnum::Admin->value)
                        ->where('status', 1)
                        ->pluck('role_id')
                        ->toArray();

                    if (count($validRoles) !== count($roleIds)) {
                        throw_exception('部分角色不存在或不属于管理员类型');
                    }

                    // 创建角色关联记录
                    $insertData = [];
                    $now = now();
                    foreach ($roleIds as $roleId) {
                        $insertData[] = [
                            'account_id' => $account->id,
                            'role_id'    => $roleId,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    if (!empty($insertData)) {
                        UserRole::query()->insert($insertData);
                    }
                }
            }

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

            return ['account_id' => $account->id];
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
            $account = Account::query()->find($accountId);
            if (!$account) {
                throw_exception('账号不存在，请刷新后重试');
            }
            if ($account->account_type !== AccountTypeEnum::Admin) {
                throw_exception('该账号不是管理员账号');
            }

            // 保存旧数据
            $old_data = $account->only(['id', 'username', 'account_type', 'status']);

            // 获取旧的管理员信息
            $admin = self::query()->where('account_id', $account->id)->first();
            if (!$admin) {
                throw_exception('管理员信息不存在');
            }

            $oldCompanyId = $admin->company_id;
            $newCompanyId = $params['company_id'] ?? $oldCompanyId;

            // 检查手机号是否已被其他账号使用（排除软删除的账号）
            if (!empty($params['phone'])) {
                $existingPhone = AccountSocial::query()
                    ->where('social_type', SocialTypeEnum::Phone->value)
                    ->where('social_account', $params['phone'])
                    ->where('account_id', '!=', $accountId)
                    ->whereHas('account', function ($q) {
                        $q->whereNull('deleted_at'); // 排除软删除的账号
                    })
                    ->exists();
                if ($existingPhone) {
                    throw_exception('手机号已被其他账号使用');
                }
            }

            // 检查邮箱是否已被其他账号使用（排除软删除的账号）
            if (!empty($params['email'])) {
                $existingEmail = AccountSocial::query()
                    ->where('social_type', SocialTypeEnum::Email->value)
                    ->where('social_account', $params['email'])
                    ->where('account_id', '!=', $accountId)
                    ->whereHas('account', function ($q) {
                        $q->whereNull('deleted_at'); // 排除软删除的账号
                    })
                    ->exists();
                if ($existingEmail) {
                    throw_exception('邮箱已被其他账号使用');
                }
            }

            // 更新账号信息
            if (isset($params['status'])) {
                $account->status = $params['status'];
            }
            if (isset($params['password']) && !empty($params['password'])) {
                $account->password = Hash::make($params['password']);
            }
            $account->save();

            // 更新管理员信息
            if (isset($params['is_super'])) {
                $admin->is_super = $params['is_super'];
            }
            if (isset($params['company_id'])) {
                $admin->company_id = $params['company_id'];
            }
            $admin->save();

            // 如果切换了公司，删除旧公司对应的部门关联数据
            if ($oldCompanyId && $newCompanyId && $oldCompanyId != $newCompanyId) {
                // 获取旧公司的所有部门ID
                $oldDepartmentIds = Department::query()
                    ->where('company_id', $oldCompanyId)
                    ->pluck('department_id')
                    ->toArray();

                if (!empty($oldDepartmentIds)) {
                    // 删除该账号在旧公司部门下的所有关联记录
                    $deletedCount = AccountDepartment::query()
                        ->where('account_id', $accountId)
                        ->where('account_type', AccountTypeEnum::Admin->value)
                        ->whereIn('department_id', $oldDepartmentIds)
                        ->delete();

                    // 记录删除部门关联的日志
                    if ($deletedCount > 0) {
                        logAudit(
                            request(),
                            currentUserId(),
                            '管理员列表',
                            OperationActionEnum::delete->value,
                            ResourceTypeEnum::user->value,
                            $accountId,
                            [
                                'company_id'     => $oldCompanyId,
                                'department_ids' => $oldDepartmentIds,
                                'deleted_count'  => $deletedCount,
                                'reason'         => '切换公司，删除旧公司部门关联',
                            ],
                            null,
                            "管理员切换公司，删除旧公司({$oldCompanyId})的部门关联数据，共删除 {$deletedCount} 条记录"
                        );
                    }
                }
            }

            // 更新或创建账号资料
            $profile = AccountProfile::query()->where('account_id', $accountId)->first();
            if (!empty($params['nickname'])) {
                if ($profile) {
                    $profile->nickname = $params['nickname'];
                    $profile->save();
                } else {
                    AccountProfile::create([
                        'id'         => generateId(),
                        'account_id' => $accountId,
                        'nickname'   => $params['nickname'],
                    ]);
                }
            }

            // 更新或创建手机号社交账号记录
            if (isset($params['phone'])) {
                $phoneSocial = AccountSocial::query()
                    ->where('account_id', $accountId)
                    ->where('social_type', SocialTypeEnum::Phone->value)
                    ->first();
                if (empty($params['phone'])) {
                    // 如果手机号为空，删除记录
                    if ($phoneSocial) {
                        $phoneSocial->delete();
                    }
                } else {
                    if ($phoneSocial) {
                        $phoneSocial->social_account = $params['phone'];
                        $phoneSocial->save();
                    } else {
                        AccountSocial::create([
                            'account_id'     => $accountId,
                            'social_type'    => SocialTypeEnum::Phone->value,
                            'social_account' => $params['phone'],
                            'is_verified'    => false,
                        ]);
                    }
                }
            }

            // 更新或创建邮箱社交账号记录
            if (isset($params['email'])) {
                $emailSocial = AccountSocial::query()
                    ->where('account_id', $accountId)
                    ->where('social_type', SocialTypeEnum::Email->value)
                    ->first();
                if (empty($params['email'])) {
                    // 如果邮箱为空，删除记录
                    if ($emailSocial) {
                        $emailSocial->delete();
                    }
                } else {
                    if ($emailSocial) {
                        $emailSocial->social_account = $params['email'];
                        $emailSocial->save();
                    } else {
                        AccountSocial::create([
                            'account_id'     => $accountId,
                            'social_type'    => SocialTypeEnum::Email->value,
                            'social_account' => $params['email'],
                            'is_verified'    => false,
                        ]);
                    }
                }
            }

            // 处理部门关联（department_ids）
            if (isset($params['department_ids'])) {
                // 删除该账号的所有部门关联
                AccountDepartment::query()
                    ->where('account_id', $accountId)
                    ->where('account_type', AccountTypeEnum::Admin->value)
                    ->delete();

                // 如果传入了部门ID数组，创建新的关联记录
                if (is_array($params['department_ids']) && !empty($params['department_ids'])) {
                    $departmentIds = array_filter($params['department_ids'], function ($id) {
                        return !empty($id) && $id > 0;
                    });

                    if (!empty($departmentIds)) {
                        // 验证部门是否属于当前公司
                        $validDepartments = Department::query()
                            ->whereIn('department_id', $departmentIds)
                            ->where('company_id', $newCompanyId)
                            ->pluck('department_id')
                            ->toArray();

                        if (count($validDepartments) !== count($departmentIds)) {
                            throw_exception('部分部门不属于当前公司，无法关联');
                        }

                        // 创建部门关联记录
                        $insertData = [];
                        foreach ($validDepartments as $index => $departmentId) {
                            $insertData[] = [
                                'account_id'    => $accountId,
                                'account_type'  => AccountTypeEnum::Admin->value,
                                'department_id' => $departmentId,
                                'sort'          => $index,
                                'created_at'    => now(),
                                'updated_at'    => now(),
                            ];
                        }

                        if (!empty($insertData)) {
                            AccountDepartment::query()->insert($insertData);
                        }
                    }
                }
            }

            // 处理角色关联（role_ids）
            if (isset($params['role_ids'])) {
                // 删除该账号的所有角色关联
                UserRole::query()->where('account_id', $accountId)->delete();

                // 如果传入了角色ID数组，创建新的关联记录
                if (is_array($params['role_ids']) && !empty($params['role_ids'])) {
                    $roleIds = array_filter($params['role_ids'], function ($id) {
                        return !empty($id) && $id > 0;
                    });

                    if (!empty($roleIds)) {
                        // 验证角色是否存在且属于管理员类型
                        $validRoles = Role::query()
                            ->whereIn('role_id', $roleIds)
                            ->where('account_type', AccountTypeEnum::Admin->value)
                            ->where('status', 1)
                            ->pluck('role_id')
                            ->toArray();

                        if (count($validRoles) !== count($roleIds)) {
                            throw_exception('部分角色不存在或不属于管理员类型');
                        }

                        // 创建角色关联记录
                        $insertData = [];
                        $now = now();
                        foreach ($roleIds as $roleId) {
                            $insertData[] = [
                                'account_id' => $accountId,
                                'role_id'    => $roleId,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ];
                        }

                        if (!empty($insertData)) {
                            UserRole::query()->insert($insertData);
                        }
                    }
                }
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

        // 删除账号的所有社交账号记录（释放手机号和邮箱供其他账号使用）
        AccountSocial::query()
            ->where('account_id', $accountId)
            ->delete();

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

        // 获取账号所属部门列表
        $departments = self::getAccountDepartments(['account_id' => $accountId]);

        // 提取部门ID列表
        $departmentIds = array_column($departments, 'department_id');

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
            'account'        => [
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
            'profile'        => $profile ? [
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
            'departments'    => $departments,
            'admin'          => $adminInfo ? [
                'account_id'   => $adminInfo->account_id,
                'is_super'     => $adminInfo->is_super,
                'company_id'   => $adminInfo->company_id,
                'company_name' => $company?->company_name,
                'created_at'   => $adminInfo->created_at?->format('Y-m-d H:i:s'),
                'updated_at'   => $adminInfo->updated_at?->format('Y-m-d H:i:s'),
            ] : null,
            'department_ids' => $departmentIds,
            'social'         => $socialData,
        ];
    }

    /**
     * 批量移除员工（批量将company_id设置为null，并删除部门关联）
     * @param array $params
     * @return array
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    public static function batchRemoveFromCompany(array $params): array
    {
        if (empty($params['account_ids']) || !is_array($params['account_ids'])) {
            throw_exception('缺少 account_ids 参数或参数格式错误');
        }
        if (empty($params['company_id'])) {
            throw_exception('缺少 company_id 参数');
        }

        $accountIds = $params['account_ids'];
        $companyId = $params['company_id'];

        DB::beginTransaction();
        try {
            // 验证账号是否存在且属于该公司
            $admins = self::query()
                ->whereIn('account_id', $accountIds)
                ->where('company_id', $companyId)
                ->get();

            if ($admins->isEmpty()) {
                throw_exception('没有找到属于该公司的管理员账号');
            }

            $validAccountIds = $admins->pluck('account_id')->toArray();
            $count = count($validAccountIds);

            // 获取该公司的所有部门ID
            $departmentIds = Department::query()
                ->where('company_id', $companyId)
                ->pluck('department_id')
                ->toArray();

            // 删除这些账号在该公司部门下的所有关联记录
            $deletedDepartmentCount = 0;
            if (!empty($departmentIds)) {
                $deletedDepartmentCount = AccountDepartment::query()
                    ->whereIn('account_id', $validAccountIds)
                    ->where('account_type', AccountTypeEnum::Admin->value)
                    ->whereIn('department_id', $departmentIds)
                    ->delete();
            }

            // 批量将company_id设置为null
            self::query()
                ->whereIn('account_id', $validAccountIds)
                ->where('company_id', $companyId)
                ->update(['company_id' => null]);

            // 获取账号信息用于日志
            $accounts = Account::query()
                ->whereIn('id', $validAccountIds)
                ->pluck('username', 'id')
                ->toArray();

            DB::commit();

            // 记录常规日志
            logGeneral(
                OperationActionEnum::batchDelete->value,
                "批量移除员工: 公司ID($companyId), 共移除 $count 个员工, 删除部门关联 $deletedDepartmentCount 条",
                [
                    'company_id'               => $companyId,
                    'account_ids'              => $validAccountIds,
                    'account_count'            => $count,
                    'department_ids'           => $departmentIds,
                    'deleted_department_count' => $deletedDepartmentCount,
                    'accounts'                 => $accounts,
                ]
            );

            // 记录审计日志（批量操作，为每个账号记录一条）
            foreach ($validAccountIds as $accountId) {
                $username = $accounts[$accountId] ?? 'ID:' . $accountId;
                logAudit(
                    request(),
                    currentUserId(),
                    '管理员列表',
                    OperationActionEnum::update->value,
                    ResourceTypeEnum::user->value,
                    $accountId,
                    [
                        'company_id'     => $companyId,
                        'department_ids' => $departmentIds,
                    ],
                    [
                        'company_id'     => null,
                        'department_ids' => [],
                    ],
                    "批量移除员工: 从公司ID($companyId)移除员工($username), 删除部门关联"
                );
            }

            return [
                'count'                    => $count,
                'deleted_department_count' => $deletedDepartmentCount,
            ];
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * 获取账号所属部门列表
     * @param array $params
     * @return array
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    public static function getAccountDepartments(array $params): array
    {
        if (empty($params['account_id'])) {
            throw_exception('缺少 account_id 参数');
        }
        $accountId = $params['account_id'];

        // 验证账号是否存在且为管理员
        $account = Account::query()->find($accountId);
        if (!$account) {
            throw_exception('账号不存在');
        }
        if ($account->account_type !== AccountTypeEnum::Admin) {
            throw_exception('该账号不是管理员账号');
        }

        // 查询账号部门关联表
        $accountDepartments = AccountDepartment::query()
            ->where('account_id', $accountId)
            ->where('account_type', AccountTypeEnum::Admin->value)
            ->orderBy('sort', 'asc')
            ->get();

        if ($accountDepartments->isEmpty()) {
            return [];
        }

        // 获取所有部门ID
        $departmentIds = $accountDepartments->pluck('department_id')->toArray();

        // 查询部门信息（包含 full_parent_id 用于构建层级路径）
        $departments = Department::query()
            ->whereIn('department_id', $departmentIds)
            ->select(['department_id', 'department_name', 'department_code', 'company_id', 'full_parent_id'])
            ->get()
            ->keyBy('department_id');

        // 获取公司信息
        $companyIds = $departments->pluck('company_id')->filter()->unique()->toArray();
        $companies = [];
        if (!empty($companyIds)) {
            $companies = Company::query()
                ->whereIn('company_id', $companyIds)
                ->pluck('company_name', 'company_id')
                ->toArray();
        }

        // 组装返回数据
        $result = [];
        foreach ($accountDepartments as $accountDept) {
            $department = $departments->get($accountDept->department_id);
            if (!$department) {
                continue; // 如果部门不存在，跳过
            }

            // 计算层级深度（通过 full_parent_id 中的ID数量判断）
            $level = 0;
            if (!empty($department->full_parent_id)) {
                $parentIds = array_filter(
                    array_map('intval', explode(',', trim($department->full_parent_id, ','))),
                    function ($id) {
                        return $id > 0;
                    }
                );
                $level = count($parentIds);
            }

            // 构建部门层级路径（从高到低用 / 拼接）
            $departmentPath = [];
            if (!empty($department->full_parent_id)) {
                // 解析 full_parent_id，获取所有父级部门ID（格式：,123,456,789,）
                $parentIds = array_filter(
                    array_map('intval', explode(',', trim($department->full_parent_id, ','))),
                    function ($id) {
                        return $id > 0;
                    }
                );
                if (!empty($parentIds)) {
                    // 查询所有父级部门，按 full_parent_id 中的顺序排列
                    $parentDepartments = Department::query()
                        ->whereIn('department_id', $parentIds)
                        ->select(['department_id', 'department_name'])
                        ->get()
                        ->keyBy('department_id');

                    // 按 full_parent_id 中的顺序添加父级部门名称
                    foreach ($parentIds as $parentId) {
                        if (isset($parentDepartments[$parentId])) {
                            $departmentPath[] = $parentDepartments[$parentId]->department_name;
                        }
                    }
                }
            }
            // 添加当前部门名称
            $departmentPath[] = $department->department_name;
            // 拼接成路径字符串
            $departmentNamePath = implode('/', $departmentPath);

            $result[] = [
                'id'              => $accountDept->id,
                'department_id'   => $accountDept->department_id,
                'department_name' => $departmentNamePath,
                'department_code' => $department->department_code,
                'company_id'      => $department->company_id,
                'company_name'    => $companies[$department->company_id] ?? '',
                'sort'            => $accountDept->sort,
                'level'           => $level, // 层级深度，用于排序
                'full_parent_id'  => $department->full_parent_id ?? '', // 用于确保子部门在父部门后面
            ];
        }

        // 对结果进行排序：先按层级排序（层级少的在前），同一层级内按 full_parent_id 路径和 sort 排序
        usort($result, function ($a, $b) {
            // 先按层级排序（层级少的在前，即父级在前）
            if ($a['level'] != $b['level']) {
                return $a['level'] <=> $b['level'];
            }

            // 同一层级内，先按 full_parent_id 路径排序（确保有相同父级的部门按路径顺序排列）
            $aFullParentId = $a['full_parent_id'] ?? '';
            $bFullParentId = $b['full_parent_id'] ?? '';

            // 比较 full_parent_id 路径（字符串比较即可，因为ID是按层级顺序存储的）
            if ($aFullParentId != $bFullParentId) {
                return strcmp($aFullParentId, $bFullParentId);
            }

            // 如果路径相同（兄弟部门），按 sort 排序
            return ($a['sort'] ?? 0) <=> ($b['sort'] ?? 0);
        });

        // 移除临时排序字段
        foreach ($result as &$item) {
            unset($item['level'], $item['full_parent_id']);
        }
        unset($item);

        return $result;
    }

    /**
     * 获取管理员角色列表
     * @param array $params
     * @return array
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    public static function getAccountRoles(array $params): array
    {
        $accountId = $params['account_id'] ?? 0;

        // 如果传入了有效的 account_id，验证账号是否存在且为管理员
        if ($accountId > 0) {
            $account = Account::query()->find($accountId);
            if (!$account) {
                throw_exception('账号不存在');
            }
            if ($account->account_type !== AccountTypeEnum::Admin) {
                throw_exception('该账号不是管理员账号');
            }
        }

        // 获取所有可用的管理员角色
        $allRoles = Role::query()
            ->where('account_type', AccountTypeEnum::Admin->value)
            ->where('status', 1)
            ->orderBy('sort', 'asc')
            ->orderBy('role_id', 'asc')
            ->get(['role_id', 'role_name', 'role_code', 'description'])
            ->toArray();

        // 获取账号已分配的角色ID
        $checkedRoleIds = UserRole::query()
            ->where('account_id', $accountId)
            ->pluck('role_id')
            ->toArray();

        return [
            'all_roles'        => $allRoles,
            'checked_role_ids' => $checkedRoleIds,
        ];
    }

    /**
     * 更新管理员角色
     * @param array $params
     * @return array
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    public static function updateAccountRoles(array $params): array
    {
        if (empty($params['account_id'])) {
            throw_exception('缺少 account_id 参数');
        }
        $accountId = $params['account_id'];
        $roleIds = $params['role_ids'] ?? [];

        // 验证账号是否存在且为管理员
        $account = Account::query()->find($accountId);
        if (!$account) {
            throw_exception('账号不存在');
        }
        if ($account->account_type !== AccountTypeEnum::Admin) {
            throw_exception('该账号不是管理员账号');
        }

        // 验证角色是否存在且属于管理员类型
        if (!empty($roleIds)) {
            $validRoles = Role::query()
                ->whereIn('role_id', $roleIds)
                ->where('account_type', AccountTypeEnum::Admin->value)
                ->where('status', 1)
                ->pluck('role_id')
                ->toArray();

            if (count($validRoles) !== count($roleIds)) {
                throw_exception('部分角色不存在或不属于管理员类型');
            }
        }

        // 获取旧的角色ID
        $oldRoleIds = UserRole::query()
            ->where('account_id', $accountId)
            ->pluck('role_id')
            ->toArray();

        DB::beginTransaction();
        try {
            // 删除原有的角色关联
            UserRole::query()->where('account_id', $accountId)->delete();

            // 如果有新的角色ID，批量插入
            if (!empty($roleIds)) {
                $insertData = [];
                $now = now();
                foreach ($roleIds as $roleId) {
                    $insertData[] = [
                        'account_id' => $accountId,
                        'role_id'    => $roleId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                UserRole::query()->insert($insertData);
            }

            DB::commit();

            // 记录审计日志
            logAudit(
                request(),
                currentUserId(),
                '管理员列表',
                OperationActionEnum::update->value,
                ResourceTypeEnum::user->value,
                $accountId,
                ['role_ids' => $oldRoleIds],
                ['role_ids' => $roleIds],
                "更新管理员角色: {$account->username}"
            );

            return [];
        } catch (Exception $e) {
            DB::rollBack();
            throw_exception('更新管理员角色失败：' . $e->getMessage());
        }
    }

}


