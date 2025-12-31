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
     * 获取用户列表（分页）
     * @param array $params
     * @return array
     * @author siushin<siushin@163.com>
     */
    public static function getPageData(array $params): array
    {
        $page = $params['page'] ?? $params['current'] ?? 1;
        $pageSize = $params['pageSize'] ?? 10;

        $query = Account::query()
            ->where('account_type', AccountTypeEnum::User->value)
            ->with(['customerInfo', 'profile', 'socialAccounts'])
            ->when(!empty($params['username']), function ($q) use ($params) {
                $q->where('username', 'like', "%{$params['username']}%");
            })
            ->when(isset($params['status']), function ($q) use ($params) {
                $q->where('status', $params['status']);
            }, function ($q) {
                // 如果没有指定status，默认排除待审核状态（status=-1）
                $q->where('status', '!=', -1);
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
        if (empty($params['status'])) {
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
        // status: 1=通过(正常), 0=拒绝(禁用)
        $account->status = $params['status'] == 1 ? 1 : 0;
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
}
