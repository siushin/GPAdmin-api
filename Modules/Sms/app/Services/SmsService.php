<?php

namespace Modules\Sms\Services;

use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Sms\Enums\SmsTypeEnum;
use Modules\Sms\Models\SmsLog;
use Psr\SimpleCache\InvalidArgumentException;
use Siushin\LaravelTool\Enums\RequestSourceEnum;

/**
 * 短信服务类
 */
class SmsService
{
    /**
     * 短信类型配置
     * 每种类型可以设置不同的限制规则
     */
    private array $typeConfig = [
        // 注册验证码
        SmsTypeEnum::Register->value      => [
            'code_expire_minutes'   => 5,       // 验证码过期时间（分钟）
            'ip_limit_window'       => 60,      // IP 限流：时间窗口（秒）
            'ip_limit_max_requests' => 3,       // IP 限流：时间窗口内最大请求次数
            'daily_max_requests'    => 10,      // 每天最大发送次数
        ],
        // 登录验证码
        SmsTypeEnum::Login->value         => [
            'code_expire_minutes'   => 5,
            'ip_limit_window'       => 60,
            'ip_limit_max_requests' => 3,
            'daily_max_requests'    => 10,
        ],
        // 重置密码验证码
        SmsTypeEnum::ResetPassword->value => [
            'code_expire_minutes'   => 10,
            'ip_limit_window'       => 60,
            'ip_limit_max_requests' => 3,
            'daily_max_requests'    => 5,
        ],
        // 绑定手机号验证码
        SmsTypeEnum::BindPhone->value     => [
            'code_expire_minutes'   => 5,
            'ip_limit_window'       => 60,
            'ip_limit_max_requests' => 3,
            'daily_max_requests'    => 5,
        ],
        // 更换手机号验证码
        SmsTypeEnum::ChangePhone->value   => [
            'code_expire_minutes'   => 5,
            'ip_limit_window'       => 60,
            'ip_limit_max_requests' => 3,
            'daily_max_requests'    => 5,
        ],
    ];

    /**
     * 校验参数
     * @param array $data 请求数据数组
     * @return array ['phone' => string, 'type' => SmsTypeEnum]
     * @throws ValidationException
     * @author siushin<siushin@163.com>
     */
    public function validateParams(array $data): array
    {
        $validator = Validator::make($data, [
            'phone' => ['required', 'string', 'regex:/^1[3-9]\d{9}$/'],
            'type'  => ['required', 'string', 'in:register,login,reset_password,bind_phone,change_phone'],
        ], [
            'phone.required' => '手机号不能为空',
            'phone.regex'    => '手机号格式不正确',
            'type.required'  => '短信类型不能为空',
            'type.in'        => '短信类型不正确',
        ]);

        $validated = $validator->validate();

        return [
            'phone' => $validated['phone'],
            'type'  => SmsTypeEnum::from($validated['type']),
        ];
    }

    /**
     * 发送短信验证码（便捷方法）
     * @param string $phone 手机号
     * @param string $type  短信类型（register/login/reset_password/bind_phone/change_phone）
     * @return array
     * @throws Exception|InvalidArgumentException
     * @author siushin<siushin@163.com>
     */
    public function sendSmsCode(string $phone, string $type): array
    {
        return $this->sendVerificationCode($phone, SmsTypeEnum::from($type));
    }

    /**
     * 发送短信验证码
     * @param string      $phone 手机号
     * @param SmsTypeEnum $type  短信类型
     * @return array
     * @throws Exception|InvalidArgumentException
     * @author siushin<siushin@163.com>
     */
    public function sendVerificationCode(string $phone, SmsTypeEnum $type): array
    {
        $typeValue = $type->value;
        $config = $this->getTypeConfig($typeValue);

        // 获取 IP 地址和相关信息
        $ip = request()->ip();
        $accountId = currentUserId() ?? null;
        $sourceType = request()->attributes->get('_request_source') ?? RequestSourceEnum::guest->value;

        try {
            // IP 频繁请求限制检查
            $this->checkIpLimit($ip, $typeValue, $config);

            // 检查当天手机号请求总次数限制
            $this->checkDailyLimit($phone, $typeValue, $config);

            // 生成6位随机数字验证码
            $code = $this->generateCode();

            // 存储验证码到 Redis，设置过期时间
            $codeKey = $this->getCodeKey($phone, $typeValue);
            Cache::store('redis')->put($codeKey, $code, now()->addMinutes($config['code_expire_minutes']));

            // 记录 IP 请求次数
            $this->incrementIpCount($ip, $typeValue, $config);

            // 记录当天手机号请求次数
            $this->incrementDailyCount($phone, $typeValue, $config);

            // TODO: 实际项目中这里应该调用短信服务商 API 发送短信
            // 目前暂时返回验证码（仅用于开发测试）

            // 记录成功日志到 sms_logs 表
            $this->logSmsSend($phone, $type, $code, $config['code_expire_minutes'], $ip, $accountId, $sourceType, true);

            return [
                'phone'  => $phone,
                'type'   => $typeValue,
                'expire' => $config['code_expire_minutes'],
            ];
        } catch (Exception $e) {
            // 解析异常信息
            $errorMessage = $e->getMessage();
            if (json_validate($errorMessage)) {
                $errorData = json_decode($errorMessage, true);
                $errorMessage = $errorData['message'] ?? $errorMessage;
            }

            // 记录失败日志到 sms_logs 表
            $this->logSmsSend($phone, $type, null, null, $ip, $accountId, $sourceType, false, $errorMessage);

            // 重新抛出异常
            throw $e;
        }
    }

    /**
     * 验证验证码
     * @param string      $phone 手机号
     * @param string      $code  验证码
     * @param SmsTypeEnum $type  短信类型
     * @return bool
     * @throws InvalidArgumentException
     * @author siushin<siushin@163.com>
     */
    public function verifyCode(string $phone, string $code, SmsTypeEnum $type): bool
    {
        $codeKey = $this->getCodeKey($phone, $type->value);
        $cachedCode = Cache::store('redis')->get($codeKey);

        if ($cachedCode === null || $cachedCode !== $code) {
            return false;
        }

        // 验证成功后删除验证码
        Cache::store('redis')->forget($codeKey);

        return true;
    }

    /**
     * 检查 IP 频繁请求限制
     * @param string $ip     IP地址
     * @param string $type   短信类型
     * @param array  $config 配置
     * @return void
     * @throws Exception|InvalidArgumentException
     * @author siushin<siushin@163.com>
     */
    private function checkIpLimit(string $ip, string $type, array $config): void
    {
        $ipKey = $this->getIpKey($ip, $type);
        $requestCount = Cache::store('redis')->get($ipKey, 0);

        if ($requestCount >= $config['ip_limit_max_requests']) {
            // 获取 Redis 中 key 的剩余过期时间（秒）
            $cachePrefix = config('cache.prefix', '');
            $fullKey = $cachePrefix ? $cachePrefix . $ipKey : $ipKey;
            $ttl = Redis::connection('cache')->ttl($fullKey);

            if ($ttl > 0) {
                throw_exception("请求过于频繁，请 $ttl 秒后再试");
            } else {
                throw_exception("请求过于频繁，请稍后再试");
            }
        }
    }

    /**
     * 检查当天手机号请求总次数限制
     * @param string $phone  手机号
     * @param string $type   短信类型
     * @param array  $config 配置
     * @return void
     * @throws Exception|InvalidArgumentException
     * @author siushin<siushin@163.com>
     */
    private function checkDailyLimit(string $phone, string $type, array $config): void
    {
        $dailyKey = $this->getDailyKey($phone, $type);
        $requestCount = Cache::store('redis')->get($dailyKey, 0);

        if ($requestCount >= $config['daily_max_requests']) {
            throw_exception("今天发送次数已达上限（{$config['daily_max_requests']}次），请明天再试");
        }
    }

    /**
     * 增加 IP 请求次数
     * @param string $ip     IP地址
     * @param string $type   短信类型
     * @param array  $config 配置
     * @return void
     * @throws InvalidArgumentException
     * @author siushin<siushin@163.com>
     */
    private function incrementIpCount(string $ip, string $type, array $config): void
    {
        $ipKey = $this->getIpKey($ip, $type);
        $requestCount = Cache::store('redis')->get($ipKey, 0);
        Cache::store('redis')->put($ipKey, $requestCount + 1, now()->addSeconds($config['ip_limit_window']));
    }

    /**
     * 增加当天手机号请求次数
     * @param string $phone  手机号
     * @param string $type   短信类型
     * @param array  $config 配置
     * @return void
     * @throws Exception|InvalidArgumentException
     * @author siushin<siushin@163.com>
     */
    private function incrementDailyCount(string $phone, string $type, array $config): void
    {
        $dailyKey = $this->getDailyKey($phone, $type);

        // 计算到明天0点的剩余秒数，确保 key 在当天结束时过期
        $tomorrow = now()->addDay()->startOfDay();
        $secondsUntilMidnight = max(1, $tomorrow->timestamp - now()->timestamp);

        // 先检查 key 是否存在，如果不存在则创建
        $requestCount = Cache::store('redis')->get($dailyKey, 0);

        // 动态校验：增加计数前再次检查是否超过每日限制
        $newCount = $requestCount + 1;
        if ($newCount > $config['daily_max_requests']) {
            throw_exception("今天发送次数已达上限（{$config['daily_max_requests']}次），请明天再试");
        }

        // 如果 key 不存在或值为 0，直接设置值为 1
        if ($requestCount == 0) {
            Cache::store('redis')->put($dailyKey, 1, now()->addSeconds($secondsUntilMidnight));
        } else {
            // 如果 key 已存在，增加计数并更新过期时间
            Cache::store('redis')->put($dailyKey, $newCount, now()->addSeconds($secondsUntilMidnight));
        }
    }

    /**
     * 获取验证码 Redis key
     * @param string $phone 手机号
     * @param string $type  短信类型
     * @return string
     * @author siushin<siushin@163.com>
     */
    private function getCodeKey(string $phone, string $type): string
    {
        return "sms:code:$type:$phone";
    }

    /**
     * 获取 IP 限流 Redis key
     * @param string $ip   IP地址
     * @param string $type 短信类型
     * @return string
     * @author siushin<siushin@163.com>
     */
    private function getIpKey(string $ip, string $type): string
    {
        return "sms:ip:$type:$ip";
    }

    /**
     * 获取每日限流 Redis key
     * @param string $phone 手机号
     * @param string $type  短信类型
     * @return string
     * @author siushin<siushin@163.com>
     */
    private function getDailyKey(string $phone, string $type): string
    {
        $date = now()->format('Y-m-d');
        return "sms:daily:$type:$phone:$date";
    }

    /**
     * 获取短信类型配置
     * @param string $type 短信类型
     * @return array
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    private function getTypeConfig(string $type): array
    {
        if (!isset($this->typeConfig[$type])) {
            throw_exception("不支持的短信类型：$type");
        }

        return $this->typeConfig[$type];
    }

    /**
     * 生成6位随机数字验证码
     * @return string
     * @author siushin<siushin@163.com>
     */
    private function generateCode(): string
    {
        return str_pad((string)mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * 记录短信发送日志到 sms_logs 表
     * @param string      $phone         手机号
     * @param SmsTypeEnum $type          短信类型
     * @param string|null $code          验证码
     * @param int|null    $expireMinutes 过期时间（分钟）
     * @param string      $ip            IP地址
     * @param int|null    $accountId     账号ID
     * @param string      $sourceType    访问来源
     * @param bool        $status        发送状态：true成功，false失败
     * @param string|null $errorMessage  错误信息
     * @return void
     * @author siushin<siushin@163.com>
     */
    private function logSmsSend(
        string      $phone,
        SmsTypeEnum $type,
        ?string     $code,
        ?int        $expireMinutes,
        string      $ip,
        ?int        $accountId,
        string      $sourceType,
        bool        $status,
        ?string     $errorMessage = null
    ): void
    {
        try {
            // 获取 IP 归属地
            $ipLocation = getIpLocation($ip);

            // 准备日志数据
            $logData = [
                'account_id'     => $accountId,
                'source_type'    => $sourceType,
                'sms_type'       => $type->value,
                'phone'          => $phone,
                'code'           => $code,
                'status'         => $status ? 1 : 0,
                'error_message'  => $errorMessage,
                'ip_address'     => $ip,
                'ip_location'    => $ipLocation,
                'expire_minutes' => $expireMinutes,
                'created_at'     => now(),
            ];

            // 插入日志记录
            SmsLog::query()->insert($logData);
        } catch (Exception $e) {
            // 日志记录失败不影响主流程，静默处理
            // 可以记录到 Laravel 日志，但根据用户要求不记录
        }
    }
}

