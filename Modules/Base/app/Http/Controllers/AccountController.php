<?php

namespace Modules\Base\Http\Controllers;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Modules\Base\Enums\AccountTypeEnum;
use Modules\Base\Enums\LogActionEnum;
use Modules\Base\Enums\OperationActionEnum;
use Modules\Base\Enums\ResourceTypeEnum;
use Modules\Base\Models\Account;
use Modules\Base\Models\AccountProfile;
use Modules\Base\Models\AccountSocial;
use Modules\Base\Services\AuthService;
use Modules\Base\Services\LogService;
use Modules\Sms\Enums\SmsTypeEnum;
use Modules\Sms\Services\SmsService;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Siushin\LaravelTool\Attributes\OperationAction;
use Siushin\LaravelTool\Enums\RequestSourceEnum;
use Siushin\LaravelTool\Enums\SocialTypeEnum;

/**
 * 控制器：账号
 * @module 账号管理
 */
class AccountController extends Controller
{
    /**
     * 认证服务
     */
    private AuthService $authService;

    /**
     * 短信服务
     */
    private SmsService $smsService;

    /**
     * 日志服务
     */
    private LogService $logService;

    public function __construct(AuthService $authService, SmsService $smsService, LogService $logService)
    {
        $this->authService = $authService;
        $this->smsService = $smsService;
        $this->logService = $logService;
    }

    /**
     * 用户登录（用户名+密码）
     * @param Request $request
     * @return JsonResponse
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::login)]
    public function login(Request $request): JsonResponse
    {
        // 验证请求数据（登录账号可以是用户名、邮箱或手机号）
        $request->validate([
            'username' => ['required'],
            'password' => ['required'],
        ]);

        // 尝试认证用户
        $extend_data = ['username' => $request['username']];

        // 通过用户名、邮箱或手机号查找账号
        $account = $this->authService->findAccountByIdentifier($request['username']);

        // 验证账号和密码
        if (!$account || !$this->authService->verifyPassword($account, $request['password'])) {
            logLogin($request, $account?->id, $request['username'], 0, '账号或密码不正确');
            logGeneral(LogActionEnum::fail_login->name, "尝试登录，登录失败(account: {$request['username']})", $extend_data);
            throw_exception('账号或密码不正确');
        }

        // 执行登录流程（账号密码登录类型为 'account'）
        $result = $this->authService->processLogin($account, $request, $request['username'], $extend_data);

        return success($result['user_data'], '登录成功');
    }

    /**
     * 用户登录（手机号+验证码）
     * @param Request $request
     * @return JsonResponse
     * @throws InvalidArgumentException
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::login)]
    public function loginByCode(Request $request): JsonResponse
    {
        // 验证请求数据
        $request->validate([
            'phone' => ['required', 'string', 'regex:/^1[3-9]\d{9}$/'],
            'code'  => ['required', 'string', 'size:6'],
        ], [
            'phone.required' => '手机号不能为空',
            'phone.regex'    => '手机号格式不正确',
            'code.required'  => '验证码不能为空',
            'code.size'      => '验证码必须为6位数字',
        ]);

        $phone = $request['phone'];
        $code = $request['code'];

        // 验证短信验证码
        if (!$this->smsService->verifyCode($phone, $code, SmsTypeEnum::Login)) {
            logLogin($request, null, $phone, 0, '验证码错误或已过期');

            $extend_data = [
                'phone' => $phone,
                'code'  => $code,
            ];
            logGeneral(LogActionEnum::fail_login->name, "尝试登录，验证码错误(phone: $phone)", $extend_data);
            throw_exception('验证码错误或已过期');
        }

        // 通过手机号查找账号（不限制账号类型，直接从数据库读取）
        $account = $this->authService->findAccountByPhone($phone);

        if (!$account) {
            logLogin($request, null, $phone, 0, '该手机号未注册');

            $extend_data = [
                'phone' => $phone,
                'code'  => $code,
            ];
            logGeneral(LogActionEnum::fail_login->name, "尝试登录，账号不存在(phone: $phone)", $extend_data);
            throw_exception('该手机号未注册');
        }

        // 执行登录流程（手机验证码登录类型为 'phone'）
        $extend_data = [
            'phone' => $phone,
            'code'  => $code,
        ];
        $result = $this->authService->processLogin($account, $request, $phone, $extend_data, 'phone');

        return success($result['user_data'], '登录成功');
    }

    /**
     * 退出用户登录
     * @param Request $request
     * @return JsonResponse
     * @throws ContainerExceptionInterface|NotFoundExceptionInterface
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::logout)]
    public function logout(Request $request): JsonResponse
    {
        $account = $request->user();

        // 获取当前使用的token并删除
        $currentToken = $account->currentAccessToken();
        if ($currentToken) {
            // 删除当前token
            $currentToken->delete();
        }

        // 记录用户退出登录日志
        logLogin($request, $account->id, $account->username, 1, '用户退出登录');
        $extend_data = ['username' => $account->username];
        logGeneral('logout', "用户退出登录(account: $account->username)", $extend_data);

        return success([], '用户退出登录成功');
    }

    /**
     * 获取当前登录用户信息
     * @param Request $request
     * @return JsonResponse
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::loginInfo)]
    public function getCurrentUserInfo(Request $request): JsonResponse
    {
        $account = $request->user();
        $userData = $this->authService->getUserData($account);
        return success($userData);
    }

    /**
     * 刷新 API 令牌
     * @param Request $request
     * @return JsonResponse
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::update)]
    public function refreshToken(Request $request): JsonResponse
    {
        $account = $request->user();

        // 将当前使用的token设为过期
        $currentToken = $account->currentAccessToken();
        if ($currentToken) {
            $currentToken->update(['expires_at' => now()]);
        }

        // 创建新的token
        $token = $this->authService->generateToken($account);
        return success(['token' => $this->authService->buildTokenData($token)]);
    }


    /**
     * 用户注册
     * @param Request $request
     * @return JsonResponse
     * @throws InvalidArgumentException
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::add)]
    public function register(Request $request): JsonResponse
    {
        // 验证请求数据
        $request->validate([
            'username'         => ['required', 'string', 'max:255'],
            'password'         => ['required', 'string', 'min:6'],
            'confirm_password' => ['required', 'string', 'same:password'],
            'phone'            => ['required', 'string', 'regex:/^1[3-9]\d{9}$/'],
            'code'             => ['required', 'string', 'size:6'],
        ], [
            'username.required'         => '用户名不能为空',
            'password.required'         => '密码不能为空',
            'password.min'              => '密码长度至少6位',
            'confirm_password.required' => '确认密码不能为空',
            'confirm_password.same'     => '两次输入的密码不一致',
            'phone.required'            => '手机号不能为空',
            'phone.regex'               => '手机号格式不正确',
            'code.required'             => '验证码不能为空',
            'code.size'                 => '验证码必须为6位数字',
        ]);

        // AccessAuth 中间件已经根据路径注入了 _request_source 和 _account_type（存储在 attributes 中）
        $requestSource = $request->attributes->get('_request_source');
        $accountType = $request->attributes->get('_account_type');

        // 验证请求来源
        $validSources = array_map(fn($case) => $case->value, RequestSourceEnum::cases());
        if (!in_array($requestSource, $validSources)) {
            throw_exception('请求来源有误');
        }

        // 验证注册验证码
        if (!$this->smsService->verifyCode($request['phone'], $request['code'], SmsTypeEnum::Register)) {
            throw_exception('验证码错误或已过期');
        }

        // 检查用户名是否已存在
        $existingAccount = Account::query()
            ->where('username', $request['username'])
            ->first();

        if ($existingAccount) {
            throw_exception('用户名已存在');
        }

        // 检查手机号是否已存在
        $existingPhone = AccountSocial::query()
            ->where('social_type', SocialTypeEnum::Phone->value)
            ->where('social_account', $request['phone'])
            ->first();

        if ($existingPhone) {
            throw_exception('手机号已被注册');
        }

        // 创建账号
        $account = Account::create([
            'username'     => $request['username'],
            'password'     => Hash::make($request['password']),
            'account_type' => $accountType ?: AccountTypeEnum::User->value,
            'status'       => 1,
        ]);

        // 创建账号资料记录
        AccountProfile::create([
            'id'         => generateId(),
            'account_id' => $account->id,
            'nickname'   => $request['username'],
        ]);

        // 创建手机号社交账号记录
        AccountSocial::create([
            'account_id'     => $account->id,
            'social_type'    => SocialTypeEnum::Phone->value,
            'social_account' => $request['phone'],
            'is_verified'    => false,
        ]);

        // 记录注册日志
        $extend_data = [
            'username' => $request['username'],
            'phone'    => $request['phone'],
            'code'     => $request['code'],
        ];
        logGeneral(LogActionEnum::login->name, "用户注册成功(account: {$request['username']})", $extend_data);

        // 记录审计日志
        $accountData = $account->only(['id', 'username', 'account_type', 'status', 'created_at']);
        logAudit(
            $request,
            $account->id, // 注册时，操作人就是新注册的用户自己
            '账号管理',
            OperationActionEnum::add->value,
            ResourceTypeEnum::user->value,
            $account->id,
            null,
            $accountData,
            "用户注册: {$request['username']} (手机号: {$request['phone']})"
        );

        return success([], '注册成功');
    }

    /**
     * 重置密码
     * @param Request $request
     * @return JsonResponse
     * @throws InvalidArgumentException
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::update)]
    public function resetPassword(Request $request): JsonResponse
    {
        // 验证请求数据
        $request->validate([
            'phone'            => ['required', 'string', 'regex:/^1[3-9]\d{9}$/'],
            'code'             => ['required', 'string', 'size:6'],
            'password'         => ['required', 'string', 'min:6'],
            'confirm_password' => ['required', 'string', 'same:password'],
        ], [
            'phone.required'            => '手机号不能为空',
            'phone.regex'               => '手机号格式不正确',
            'code.required'             => '验证码不能为空',
            'code.size'                 => '验证码必须为6位数字',
            'password.required'         => '新密码不能为空',
            'password.string'           => '新密码必须是字符串',
            'password.min'              => '密码长度至少6位',
            'confirm_password.required' => '确认新密码不能为空',
            'confirm_password.string'   => '确认新密码必须是字符串',
            'confirm_password.same'     => '两次输入的密码不一致',
        ]);

        $phone = $request['phone'];
        $code = $request['code'];

        // 验证重置密码验证码
        if (!$this->smsService->verifyCode($phone, $code, SmsTypeEnum::ResetPassword)) {
            $extend_data = [
                'phone' => $phone,
                'code'  => $code,
            ];
            logGeneral(LogActionEnum::reset_password->name, "尝试重置密码，验证码错误(phone: $phone)", $extend_data);
            throw_exception('验证码错误或已过期');
        }

        // 通过手机号查找账号（不限制账号类型）
        $account = $this->authService->findAccountByPhone($phone);

        if (!$account) {
            $extend_data = [
                'phone' => $phone,
                'code'  => $code,
            ];
            logGeneral(LogActionEnum::reset_password->name, "尝试重置密码，账号不存在(phone: $phone)", $extend_data);
            throw_exception('该手机号未注册');
        }

        // 更新密码
        $params = [
            'account_id' => $account->id,
            'password'   => $request->input('password'),
        ];

        Account::updatePassword($params);

        // 记录重置密码日志（不包含密码）
        $extend_data = [
            'phone'    => $phone,
            'code'     => $code,
            'username' => $account->username,
        ];
        logGeneral(LogActionEnum::reset_password->name, "用户重置密码成功(phone: $phone)", $extend_data);

        // 记录审计日志（Account::updatePassword 已经记录了，这里补充重置密码的审计日志）
        $accountData = $account->only(['id', 'username', 'account_type', 'status', 'updated_at']);
        logAudit(
            $request,
            $account->id,
            '账号管理',
            OperationActionEnum::update->value,
            ResourceTypeEnum::user->value,
            $account->id,
            null,
            $accountData,
            "重置密码: $account->username (手机号: {$phone})"
        );

        return success([], '密码重置成功');
    }

    /**
     * 修改账号密码
     * @param Request $request
     * @return JsonResponse
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::update)]
    public function changePassword(Request $request): JsonResponse
    {
        // 验证请求参数
        $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'string', 'min:6'],
            'confirm_password' => ['required', 'string', 'same:password'],
        ], [
            'current_password.required' => '当前密码不能为空',
            'current_password.string'   => '当前密码必须是字符串',
            'password.required'         => '新密码不能为空',
            'password.string'           => '新密码必须是字符串',
            'password.min'              => '新密码至少为 :min 个字符',
            'confirm_password.required' => '确认密码不能为空',
            'confirm_password.string'   => '确认密码必须是字符串',
            'confirm_password.same'     => '确认密码与新密码不一致',
        ]);

        $account = $request->user();

        // 验证当前密码是否正确
        if (!Hash::check($request->input('current_password'), $account->password)) {
            throw_exception('当前密码不正确');
        }

        // 更新密码
        $params = [
            'account_id' => $account->id,
            'password'   => $request->input('password'),
        ];

        return success(Account::updatePassword($params), '密码修改成功');
    }
}
