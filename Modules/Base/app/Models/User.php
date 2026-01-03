<?php

namespace Modules\Base\Models;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Base\Enums\AccountTypeEnum;
use Modules\Base\Enums\LogActionEnum;
use Modules\Base\Enums\OperationActionEnum;
use Modules\Base\Enums\ResourceTypeEnum;
use Siushin\LaravelTool\Enums\SocialTypeEnum;
use Siushin\Util\Traits\ParamTool;
use Throwable;

/**
 * 模型：客户
 */
class User extends Model
{
    use ParamTool;

    protected $table = 'gpa_user';

    protected $fillable = [
        'id',
        'account_id',
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
     * 获取用户列表
     * @param array $params
     * @return array
     * @author siushin<siushin@163.com>
     */
    public static function getPageData(array $params): array
    {
        $page = $params['page'] ?? 1;
        $pageSize = $params['pageSize'] ?? 10;

        $query = Account::query()
            ->where('account_type', AccountTypeEnum::User->value)
            ->with(['customerInfo', 'profile', 'socialAccounts'])
            ->when(!empty($params['username']), function ($q) use ($params) {
                $q->where('username', 'like', "%{$params['username']}%");
            })
            ->when(isset($params['status']), function ($q) use ($params) {
                // 支持 status 数组查询（用于"全部"tab等场景）
                if (is_array($params['status'])) {
                    $q->whereIn('status', $params['status']);
                } else {
                    $q->where('status', $params['status']);
                }
            }, function ($q) {
                // 如果没有指定status，默认只显示禁用（0）和正常（1）状态的用户
                // 排除待审核（-1）和已拒绝（-2）状态
                $q->whereIn('status', [0, 1]);
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

        $total = $query->count();
        $list = $query->orderBy('id', 'desc')
            ->skip(($page - 1) * $pageSize)
            ->take($pageSize)
            ->get()
            ->map(function ($account) {
                $userInfo = $account->customerInfo;
                $profile = $account->profile;
                $socialAccounts = $account->socialAccounts;

                // 获取手机号
                $phone = $socialAccounts->firstWhere('social_type', SocialTypeEnum::Phone->value)?->social_account;
                // 获取邮箱
                $email = $socialAccounts->firstWhere('social_type', SocialTypeEnum::Email->value)?->social_account;

                return [
                    'account_id'      => $account->id,
                    'username'        => $account->username,
                    'nickname'        => $profile?->nickname,
                    'phone'           => $phone,
                    'email'           => $email,
                    'account_type'    => $account->account_type->value,
                    'status'          => $account->status,
                    'last_login_ip'   => $account->last_login_ip,
                    'last_login_time' => $account->last_login_time?->format('Y-m-d H:i:s'),
                    'created_at'      => $account->created_at?->format('Y-m-d H:i:s'),
                    'updated_at'      => $account->updated_at?->format('Y-m-d H:i:s'),
                ];
            })
            ->toArray();

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
     * 新增用户
     * @param array $params
     * @return array
     * @throws Exception|Throwable
     * @author siushin<siushin@163.com>
     */
    public static function addUser(array $params): array
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
            $account->account_type = AccountTypeEnum::User->value;
            $account->status = $params['status'] ?? 1;
            $account->save();

            // 创建用户信息
            $user = new self();
            $user->id = generateId();
            $user->account_id = $account->id;
            $user->save();

            DB::commit();

            // 记录审计日志
            logAudit(
                request(),
                currentUserId(),
                '用户管理',
                OperationActionEnum::add->value,
                ResourceTypeEnum::user->value,
                $account->id,
                null,
                $account->only(['id', 'username', 'account_type', 'status']),
                "新增用户: $account->username"
            );

            return ['id' => $account->id];
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * 更新用户
     * @param array $params
     * @return array
     * @throws Exception|Throwable
     * @author siushin<siushin@163.com>
     */
    public static function updateUser(array $params): array
    {
        if (empty($params['account_id'])) {
            throw_exception('缺少 account_id 参数');
        }
        $accountId = $params['account_id'];

        DB::beginTransaction();
        try {
            $account = Account::query()->findOrFail($accountId);
            if ($account->account_type !== AccountTypeEnum::User) {
                throw_exception('该账号不是用户账号');
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

            DB::commit();

            // 记录审计日志
            $new_data = $account->fresh()->only(['id', 'username', 'account_type', 'status']);
            logAudit(
                request(),
                currentUserId(),
                '用户管理',
                OperationActionEnum::update->value,
                ResourceTypeEnum::user->value,
                $account->id,
                $old_data,
                $new_data,
                "更新用户: $account->username"
            );

            return [];
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * 删除用户
     * @param array $params
     * @return array
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    public static function deleteUser(array $params): array
    {
        if (empty($params['account_id'])) {
            throw_exception('缺少 account_id 参数');
        }
        $accountId = $params['account_id'];

        $account = Account::query()->findOrFail($accountId);
        if ($account->account_type !== AccountTypeEnum::User) {
            throw_exception('该账号不是用户账号');
        }

        // 保存旧数据
        $old_data = $account->only(['id', 'username', 'account_type', 'status']);

        // 删除账号（会级联删除用户信息）
        $account->delete();

        // 记录审计日志
        logAudit(
            request(),
            currentUserId(),
            '用户管理',
            OperationActionEnum::delete->value,
            ResourceTypeEnum::user->value,
            $account->id,
            $old_data,
            null,
            "删除用户: $account->username"
        );

        return [];
    }

    /**
     * 获取用户详情
     * @param array $params
     * @return array
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    public static function getUserDetail(array $params): array
    {
        if (empty($params['account_id'])) {
            throw_exception('缺少 account_id 参数');
        }
        $accountId = $params['account_id'];

        $account = Account::query()
            ->where('id', $accountId)
            ->where('account_type', AccountTypeEnum::User->value)
            ->with(['customerInfo', 'profile', 'socialAccounts'])
            ->first();

        if (!$account) {
            throw_exception('用户不存在');
        }

        $userInfo = $account->customerInfo;
        $profile = $account->profile;
        $socialAccounts = $account->socialAccounts;

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
                'id'              => $account->id,
                'username'        => $account->username,
                'account_type'    => $account->account_type->value,
                'status'          => $account->status,
                'last_login_ip'   => $account->last_login_ip,
                'last_login_time' => $account->last_login_time?->format('Y-m-d H:i:s'),
                'created_at'      => $account->created_at?->format('Y-m-d H:i:s'),
                'updated_at'      => $account->updated_at?->format('Y-m-d H:i:s'),
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
            'user'    => $userInfo ? [
                'account_id' => $userInfo->account_id,
                'created_at' => $userInfo->created_at?->format('Y-m-d H:i:s'),
                'updated_at' => $userInfo->updated_at?->format('Y-m-d H:i:s'),
            ] : null,
            'social'  => $socialData,
        ];
    }

    /**
     * 审核用户
     * @param array $params
     * @return array
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    public static function auditUser(array $params): array
    {
        if (empty($params['account_id'])) {
            throw_exception('缺少 account_id 参数');
        }
        $accountId = $params['account_id'];
        if (!isset($params['status'])) {
            throw_exception('缺少 status 参数');
        }

        $account = Account::query()->findOrFail($accountId);
        if ($account->account_type !== AccountTypeEnum::User) {
            throw_exception('该账号不是用户账号');
        }

        if ($account->status !== -1) {
            throw_exception('该用户不是待审核状态');
        }

        // 保存旧数据
        $old_data = $account->only(['id', 'username', 'account_type', 'status']);

        // 更新账号状态
        // status: 1=通过(正常), -2=拒绝(已拒绝)
        $account->status = $params['status'] == 1 ? 1 : -2;
        $account->save();

        // 记录审计日志
        $new_data = $account->fresh()->only(['id', 'username', 'account_type', 'status']);
        logAudit(
            request(),
            currentUserId(),
            '用户管理',
            OperationActionEnum::update->value,
            ResourceTypeEnum::user->value,
            $account->id,
            $old_data,
            $new_data,
            "审核用户: $account->username, " . ($params['status'] == 1 ? '通过' : '拒绝')
        );

        return [];
    }

    /**
     * 批量审核用户
     * @param array $params
     * @return array
     * @throws Exception|Throwable
     * @author siushin<siushin@163.com>
     */
    public static function batchAuditUser(array $params): array
    {
        if (empty($params['account_ids']) || !is_array($params['account_ids'])) {
            throw_exception('缺少 account_ids 参数或参数格式错误');
        }
        $accountIds = $params['account_ids'];
        if (!isset($params['status'])) {
            throw_exception('缺少 status 参数');
        }
        $status = $params['status'] == 1 ? 1 : -2;

        DB::beginTransaction();
        try {
            $accounts = Account::query()
                ->whereIn('id', $accountIds)
                ->where('account_type', AccountTypeEnum::User->value)
                ->where('status', -1) // 只审核待审核状态的用户
                ->get();

            if ($accounts->isEmpty()) {
                throw_exception('所选用户中没有待审核状态的用户');
            }

            $successCount = 0;
            $failCount = 0;
            $successUsernames = [];
            $failUsernames = [];

            foreach ($accounts as $account) {
                try {
                    // 保存旧数据
                    $old_data = $account->only(['id', 'username', 'account_type', 'status']);

                    // 更新账号状态
                    $account->status = $status;
                    $account->save();

                    // 记录审计日志
                    $new_data = $account->fresh()->only(['id', 'username', 'account_type', 'status']);
                    logAudit(
                        request(),
                        currentUserId(),
                        '用户管理',
                        OperationActionEnum::update->value,
                        ResourceTypeEnum::user->value,
                        $account->id,
                        $old_data,
                        $new_data,
                        "批量审核用户: $account->username, " . ($status == 1 ? '通过' : '拒绝')
                    );

                    $successCount++;
                    $successUsernames[] = $account->username;
                } catch (Exception $e) {
                    $failCount++;
                    $failUsernames[] = $account->username . '(' . $e->getMessage() . ')';
                }
            }

            DB::commit();

            $message = "批量审核完成：成功 {$successCount} 个";
            if ($failCount > 0) {
                $message .= "，失败 $failCount 个";
            }

            return [
                'success_count'     => $successCount,
                'fail_count'        => $failCount,
                'success_usernames' => $successUsernames,
                'fail_usernames'    => $failUsernames,
                'message'           => $message,
            ];
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * 批量删除用户
     * @param array $params
     * @return array
     * @throws Exception|Throwable
     * @author siushin<siushin@163.com>
     */
    public static function batchDeleteUser(array $params): array
    {
        if (empty($params['account_ids']) || !is_array($params['account_ids'])) {
            throw_exception('缺少 account_ids 参数或参数格式错误');
        }
        $accountIds = $params['account_ids'];

        DB::beginTransaction();
        try {
            $accounts = Account::query()
                ->whereIn('id', $accountIds)
                ->where('account_type', AccountTypeEnum::User->value)
                ->get();

            if ($accounts->isEmpty()) {
                throw_exception('所选用户中没有有效的用户账号');
            }

            $successCount = 0;
            $failCount = 0;
            $successUsernames = [];
            $failUsernames = [];

            foreach ($accounts as $account) {
                try {
                    // 保存旧数据
                    $old_data = $account->only(['id', 'username', 'account_type', 'status']);

                    // 删除账号（会级联删除用户信息）
                    $account->delete();

                    // 记录审计日志
                    logAudit(
                        request(),
                        currentUserId(),
                        '用户管理',
                        OperationActionEnum::delete->value,
                        ResourceTypeEnum::user->value,
                        $account->id,
                        $old_data,
                        null,
                        "批量删除用户: $account->username"
                    );

                    $successCount++;
                    $successUsernames[] = $account->username;
                } catch (Exception $e) {
                    $failCount++;
                    $failUsernames[] = $account->username . '(' . $e->getMessage() . ')';
                }
            }

            // 记录常规日志
            logGeneral(
                LogActionEnum::batchDelete->name,
                "批量删除用户(数量: $successCount, IDs: " . implode(',', $accountIds) . ")",
                $accounts->toArray()
            );

            DB::commit();

            $message = "批量删除完成：成功 {$successCount} 个";
            if ($failCount > 0) {
                $message .= "，失败 $failCount 个";
            }

            return [
                'success_count'     => $successCount,
                'fail_count'        => $failCount,
                'success_usernames' => $successUsernames,
                'fail_usernames'    => $failUsernames,
                'message'           => $message,
            ];
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
