<?php

namespace Modules\Base\Services;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Modules\Base\Enums\AccountTypeEnum;
use Modules\Base\Enums\LogActionEnum;
use Modules\Admin\Models\Account;
use Modules\Admin\Models\AccountSocial;
use Siushin\LaravelTool\Enums\RequestSourceEnum;
use Siushin\LaravelTool\Enums\SocialTypeEnum;

/**
 * 认证服务类
 * 处理各种登录方式的通用逻辑
 */
class AuthService
{
    /**
     * Token过期时间（小时）
     */
    private int $expireHour;

    /**
     * Token过期时间（分钟）
     */
    private int $expireMinute;

    /**
     * Token过期时间（秒）
     */
    private int $expireSecond;

    public function __construct()
    {
        $this->expireMinute = config('sanctum.expiration') ?: 120;
        $this->expireHour = $this->expireMinute / 60;
        $this->expireSecond = $this->expireMinute * 60;
    }

    /**
     * 验证请求来源和账号类型
     * @param Request $request
     * @return array ['request_source' => string, 'account_type' => string|null]
     * @throws Exception
     */
    public function validateRequestSource(Request $request): array
    {
        // AccessAuth 中间件已经根据路径注入了 _request_source 和 _account_type（存储在 attributes 中）
        $requestSource = $request->attributes->get('_request_source');
        $accountType = $request->attributes->get('_account_type');

        // 验证请求来源
        $validSources = array_map(fn($case) => $case->value, RequestSourceEnum::cases());
        if (!in_array($requestSource, $validSources)) {
            throw_exception('请求来源有误');
        }

        return [
            'request_source' => $requestSource,
            'account_type'   => $accountType,
        ];
    }

    /**
     * 通过用户名查找账号
     * @param string      $username    用户名
     * @param string|null $accountType 账号类型
     * @return Account|null
     */
    public function findAccountByUsername(string $username, ?string $accountType = null): ?Account
    {
        $accountQuery = Account::query()->where('username', $username);

        // 根据账号类型筛选
        if ($accountType) {
            $accountQuery->where('account_type', $accountType);
        }

        return $accountQuery->first();
    }

    /**
     * 通过手机号查找账号
     * @param string      $phone       手机号
     * @param string|null $accountType 账号类型
     * @return Account|null
     */
    public function findAccountByPhone(string $phone, ?string $accountType = null): ?Account
    {
        // 先通过手机号在社交网络表中查找
        $accountSocial = AccountSocial::query()
            ->where('social_type', SocialTypeEnum::Phone->value)
            ->where('social_account', $phone)
            ->first();

        if (!$accountSocial) {
            return null;
        }

        $accountQuery = Account::query()->where('id', $accountSocial->account_id);

        // 根据账号类型筛选
        if ($accountType) {
            $accountQuery->where('account_type', $accountType);
        }

        return $accountQuery->first();
    }

    /**
     * 通过邮箱查找账号
     * @param string      $email       邮箱
     * @param string|null $accountType 账号类型
     * @return Account|null
     */
    public function findAccountByEmail(string $email, ?string $accountType = null): ?Account
    {
        // 先通过邮箱在社交网络表中查找
        $accountSocial = AccountSocial::query()
            ->where('social_type', SocialTypeEnum::Email->value)
            ->where('social_account', $email)
            ->first();

        if (!$accountSocial) {
            return null;
        }

        $accountQuery = Account::query()->where('id', $accountSocial->account_id);

        // 根据账号类型筛选
        if ($accountType) {
            $accountQuery->where('account_type', $accountType);
        }

        return $accountQuery->first();
    }

    /**
     * 通过用户名、邮箱或手机号查找账号（兼容原有逻辑）
     * @param string      $identifier  标识符（用户名、邮箱或手机号）
     * @param string|null $accountType 账号类型
     * @return Account|null
     */
    public function findAccountByIdentifier(string $identifier, ?string $accountType = null): ?Account
    {
        // 先尝试通过用户名查找账号
        $account = $this->findAccountByUsername($identifier, $accountType);

        // 如果通过用户名找不到，尝试通过邮箱或手机号在社交网络表中查找
        if (!$account) {
            $accountSocial = AccountSocial::query()
                ->whereIn('social_type', [
                    SocialTypeEnum::Email->value,
                    SocialTypeEnum::Phone->value
                ])
                ->where('social_account', $identifier)
                ->first();

            if ($accountSocial) {
                $accountQuery = Account::query()->where('id', $accountSocial->account_id);
                // 根据账号类型筛选
                if ($accountType) {
                    $accountQuery->where('account_type', $accountType);
                }
                $account = $accountQuery->first();
            }
        }

        return $account;
    }

    /**
     * 验证密码
     * @param Account $account  账号
     * @param string  $password 密码
     * @return bool
     */
    public function verifyPassword(Account $account, string $password): bool
    {
        return Hash::check($password, $account->password);
    }

    /**
     * 更新登录信息
     * @param Account $account 账号
     * @param Request $request 请求对象
     * @return void
     */
    public function updateLoginInfo(Account $account, Request $request): void
    {
        $account->update([
            'last_login_ip'   => $request->ip(),
            'last_login_time' => now(),
        ]);
    }

    /**
     * 生成访问令牌
     * @param Account $account 账号
     * @return string
     */
    public function generateToken(Account $account): string
    {
        return $account->createToken(
            'account_token',
            ['*'],
            now()->addHours($this->expireHour)
        )->plainTextToken;
    }

    /**
     * 获取用户数据（不包括token）
     * @param Account $account
     * @return array
     */
    public function getUserData(Account $account): array
    {
        // 根据账号类型加载对应的数据
        $userData = $account->toArray();

        // 加载对应的类型信息并合并到$userData
        if ($account->account_type === AccountTypeEnum::Admin) {
            $typeInfo = $account->adminInfo;
            if ($typeInfo) {
                $userData = array_merge($userData, $typeInfo->only(['company_id', 'department_id', 'is_super']));
            }
        } elseif ($account->account_type === AccountTypeEnum::User) {
            $typeInfo = $account->customerInfo;
            if ($typeInfo) {
                // User表只有id和account_id，没有其他业务字段，无需合并
            }
        }

        // 加载账号资料信息并合并到$userData
        $profile = $account->profile;
        if ($profile) {
            // 只返回需要的字段：nickname, gender, avatar，并合并到$userData
            $userData = array_merge($userData, $profile->only(['nickname', 'gender', 'avatar']));
        }

        // 加载所有已验证的社交信息
        $socialAccounts = $account->socialAccounts()
            ->where('is_verified', true)
            ->get()
            ->map(function ($social) {
                // 只返回需要的字段：social_type, social_account, social_name, avatar
                return $social->only(['social_type', 'social_account', 'social_name', 'avatar']);
            })
            ->toArray();
        $userData['social_accounts'] = $socialAccounts;

        return $userData;
    }

    /**
     * 构造 token 结构体
     * @param string $token
     * @return array
     */
    public function buildTokenData(string $token): array
    {
        return [
            'token_type'    => 'Bearer',
            'expires_in'    => $this->expireSecond,
            'access_token'  => $token,
            'refresh_token' => $token,
        ];
    }

    /**
     * 执行登录流程（通用方法）
     * @param Account    $account    账号
     * @param Request    $request    请求对象
     * @param string     $identifier 登录标识符（用于日志记录）
     * @param array|null $extendData 扩展数据（用于日志记录）
     * @param string     $loginType  登录类型：account 或 phone
     * @return array ['user_data' => array, 'token' => array]
     */
    public function processLogin(Account $account, Request $request, string $identifier, ?array $extendData = null, string $loginType = 'account'): array
    {
        // 更新登录信息
        $this->updateLoginInfo($account, $request);

        // 生成访问令牌
        $token = $this->generateToken($account);

        // 获取用户数据
        $userData = $this->getUserData($account);

        // 添加token到用户数据
        $userData['token'] = $this->buildTokenData($token);

        // 添加登录类型
        $userData['type'] = $loginType;

        // 添加用户权限（根据账号类型动态返回）
        $userData['currentAuthority'] = $account->account_type;

        // 记录登录日志到 gpa_login_log
        logLogin($request, $account->id, $account->username, 1, '登录成功');

        // 记录常规日志（兼容原有逻辑）
        $logData = $extendData ?? ['username' => $identifier];
        logGeneral(LogActionEnum::login->name, "用户登录系统(account: {$identifier})", $logData);

        return [
            'user_data' => $userData,
            'token'     => $userData['token'],
        ];
    }

    /**
     * 获取Token过期时间（秒）
     * @return int
     */
    public function getExpireSecond(): int
    {
        return $this->expireSecond;
    }
}

