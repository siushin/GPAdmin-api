<?php

namespace Modules\Base\Services;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Base\Models\AuditLog;
use Modules\Base\Models\GeneralLog;
use Modules\Base\Models\LoginLog;
use Modules\Base\Models\OperationLog;
use Modules\Base\Models\AccountSocial;
use Siushin\LaravelTool\Enums\RequestSourceEnum;
use Siushin\LaravelTool\Enums\SocialTypeEnum;

/**
 * 日志服务类
 * 统一管理各种日志的写入
 */
class LogService
{
    /**
     * 记录常规日志（兼容原有的logging函数）
     * @param string   $actionType 操作类型（对应LogActionEnum）
     * @param string   $content    日志内容
     * @param array    $extendData 扩展数据
     * @param int|null $accountId  账号ID（可选，如果不提供会尝试从请求中获取）
     * @return bool
     */
    public function logGeneral(string $actionType, string $content, array $extendData = [], ?int $accountId = null): bool
    {
        try {
            $request = request();

            // 如果没有提供account_id，尝试从请求中获取
            if (!$accountId) {
                $accountId = currentUserId() ?? null;

                // 如果仍然为空，且 extend_data 中包含 phone，尝试通过手机号查找
                if (!$accountId && !empty($extendData['phone'])) {
                    $accountSocial = AccountSocial::query()
                        ->where('social_type', SocialTypeEnum::Phone->value)
                        ->where('social_account', $extendData['phone'])
                        ->first();

                    if ($accountSocial) {
                        $accountId = $accountSocial->account_id;
                    }
                }
            }

            $sourceType = $request->attributes->get('_request_source') ?? RequestSourceEnum::guest->value;
            $ipAddress = $request->ip();

            // 获取IP归属地
            $ipLocation = getIpLocation($ipAddress);

            $data = [
                'log_id'      => generateId(),
                'account_id'  => $accountId,
                'source_type' => $sourceType,
                'action_type' => $actionType,
                'content'     => $content,
                'ip_address'  => $ipAddress,
                'ip_location' => $ipLocation,
                'extend_data' => !empty($extendData) ? json_encode($extendData, JSON_UNESCAPED_UNICODE) : null,
                'created_at'  => now(),
            ];

            return GeneralLog::query()->insert($data);
        } catch (Exception $e) {
            Log::error('记录常规日志失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 记录操作日志
     * @param Request    $request       请求对象
     * @param int|null   $accountId     账号ID
     * @param string     $module        模块名称
     * @param string     $action        操作类型（如：create, update, delete, export）
     * @param string     $method        HTTP方法
     * @param string     $path          请求路径
     * @param array|null $params        请求参数
     * @param int|null   $responseCode  响应状态码
     * @param int|null   $executionTime 执行耗时（毫秒）
     * @return bool
     */
    public function logOperation(
        Request $request,
        ?int    $accountId = null,
        string  $module = '',
        string  $action = '',
        string  $method = '',
        string  $path = '',
        ?array  $params = null,
        ?int    $responseCode = null,
        ?int    $executionTime = null
    ): bool
    {
        try {
            $sourceType = $request->attributes->get('_request_source') ?? RequestSourceEnum::guest->value;
            $ipAddress = $request->ip();
            $userAgent = $request->userAgent() ?? '';

            // 获取IP归属地
            $ipLocation = getIpLocation($ipAddress);

            $data = [
                'id'             => generateId(),
                'account_id'     => $accountId,
                'source_type'    => $sourceType,
                'module'         => $module,
                'action'         => $action,
                'method'         => $method,
                'path'           => $path,
                'params'         => $params ? json_encode($params, JSON_UNESCAPED_UNICODE) : null,
                'ip_address'     => $ipAddress,
                'ip_location'    => $ipLocation,
                'user_agent'     => $userAgent,
                'response_code'  => $responseCode,
                'execution_time' => $executionTime,
                'operated_at'    => now(),
            ];

            return OperationLog::query()->insert($data);
        } catch (Exception $e) {
            Log::error('记录操作日志失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 记录审计日志
     * @param Request     $request      请求对象
     * @param int|null    $accountId    操作人ID
     * @param string      $module       模块名称
     * @param string      $action       操作类型（如：权限变更、角色分配、数据导出、配置修改）
     * @param string|null $resourceType 资源类型（如：user, role, menu, config）
     * @param int|null    $resourceId   资源ID
     * @param array|null  $beforeData   变更前数据
     * @param array|null  $afterData    变更后数据
     * @param string|null $description  操作描述
     * @return bool
     */
    public function logAudit(
        Request $request,
        ?int    $accountId = null,
        string  $module = '',
        string  $action = '',
        ?string $resourceType = null,
        ?int    $resourceId = null,
        ?array  $beforeData = null,
        ?array  $afterData = null,
        ?string $description = null
    ): bool
    {
        try {
            $ipAddress = $request->ip();
            $userAgent = $request->userAgent() ?? '';

            // 获取IP归属地
            $ipLocation = getIpLocation($ipAddress);

            $data = [
                'id'            => generateId(),
                'account_id'    => $accountId,
                'module'        => $module,
                'action'        => $action,
                'resource_type' => $resourceType,
                'resource_id'   => $resourceId,
                'before_data'   => $beforeData ? json_encode($beforeData, JSON_UNESCAPED_UNICODE) : null,
                'after_data'    => $afterData ? json_encode($afterData, JSON_UNESCAPED_UNICODE) : null,
                'description'   => $description,
                'ip_address'    => $ipAddress,
                'ip_location'   => $ipLocation,
                'user_agent'    => $userAgent,
                'audited_at'    => now(),
            ];

            return AuditLog::query()->insert($data);
        } catch (Exception $e) {
            Log::error('记录审计日志失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 记录登录日志
     * @param Request     $request   请求对象
     * @param int|null    $accountId 账号ID
     * @param string|null $username  用户名
     * @param int         $status    登录状态：1成功，0失败
     * @param string|null $message   登录信息/错误信息
     * @return bool
     */
    public function logLogin(Request $request, ?int $accountId = null, ?string $username = null, int $status = 1, ?string $message = null): bool
    {
        try {
            $ipAddress = $request->ip();
            $userAgent = $request->userAgent() ?? '';

            // 解析User-Agent获取浏览器和操作系统信息
            $browserInfo = $this->parseUserAgent($userAgent);

            // 获取IP归属地
            $ipLocation = getIpLocation($ipAddress);

            // 如果没有提供消息，根据状态生成默认消息
            if (!$message) {
                $message = $status === 1 ? '登录成功' : '登录失败';
            }

            $data = [
                'id'               => generateId(),
                'account_id'       => $accountId,
                'username'         => $username,
                'status'           => $status,
                'ip_address'       => $ipAddress,
                'ip_location'      => $ipLocation,
                'browser'          => $browserInfo['browser'],
                'browser_version'  => $browserInfo['browser_version'],
                'operating_system' => $browserInfo['os'],
                'device_type'      => $browserInfo['device_type'],
                'user_agent'       => $userAgent,
                'message'          => $message,
                'login_at'         => now(),
            ];

            return LoginLog::query()->insert($data);
        } catch (Exception $e) {
            Log::error('记录登录日志失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 解析User-Agent获取浏览器和操作系统信息
     * @param string $userAgent
     * @return array ['browser' => string, 'browser_version' => string, 'os' => string, 'device_type' => string]
     */
    private function parseUserAgent(string $userAgent): array
    {
        $browser = null;
        $browserVersion = null;
        $os = null;
        $deviceType = 'Desktop';

        if (empty($userAgent)) {
            return [
                'browser'         => null,
                'browser_version' => null,
                'os'              => null,
                'device_type'     => null,
            ];
        }

        // 检测操作系统
        if (preg_match('/Windows NT/i', $userAgent)) {
            $os = 'Windows';
        } elseif (preg_match('/Mac OS X/i', $userAgent)) {
            $os = 'macOS';
        } elseif (preg_match('/Linux/i', $userAgent)) {
            $os = 'Linux';
        } elseif (preg_match('/iPhone|iPad|iPod/i', $userAgent)) {
            $os = 'iOS';
            $deviceType = preg_match('/iPad/i', $userAgent) ? 'Tablet' : 'Mobile';
        } elseif (preg_match('/Android/i', $userAgent)) {
            $os = 'Android';
            $deviceType = preg_match('/Mobile/i', $userAgent) ? 'Mobile' : 'Tablet';
        }

        // 检测浏览器
        if (preg_match('/Chrome\/([0-9.]+)/i', $userAgent, $matches)) {
            $browser = 'Chrome';
            $browserVersion = $matches[1];
        } elseif (preg_match('/Firefox\/([0-9.]+)/i', $userAgent, $matches)) {
            $browser = 'Firefox';
            $browserVersion = $matches[1];
        } elseif (preg_match('/Safari\/([0-9.]+)/i', $userAgent, $matches) && !preg_match('/Chrome/i', $userAgent)) {
            $browser = 'Safari';
            $browserVersion = $matches[1];
        } elseif (preg_match('/Edge\/([0-9.]+)/i', $userAgent, $matches)) {
            $browser = 'Edge';
            $browserVersion = $matches[1];
        } elseif (preg_match('/Opera\/([0-9.]+)/i', $userAgent, $matches)) {
            $browser = 'Opera';
            $browserVersion = $matches[1];
        } elseif (preg_match('/MSIE ([0-9.]+)/i', $userAgent, $matches)) {
            $browser = 'IE';
            $browserVersion = $matches[1];
        }

        return [
            'browser'         => $browser,
            'browser_version' => $browserVersion,
            'os'              => $os,
            'device_type'     => $deviceType,
        ];
    }

}
