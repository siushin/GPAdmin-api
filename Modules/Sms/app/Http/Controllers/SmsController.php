<?php

namespace Modules\Sms\Http\Controllers;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Base\Enums\LogActionEnum;
use Modules\Base\Enums\OperationActionEnum;
use Modules\Sms\Services\SmsService;
use Psr\SimpleCache\InvalidArgumentException;
use Siushin\LaravelTool\Attributes\OperationAction;

/**
 * 控制器：短信服务
 * @module 短信服务
 */
class SmsController extends Controller
{
    /**
     * 短信服务
     */
    private SmsService $smsService;

    /**
     * 构造函数
     * @param SmsService $smsService
     */
    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;
    }

    /**
     * 发送短信验证码
     * @param Request $request
     * @return JsonResponse
     * @throws InvalidArgumentException|ValidationException
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::requestService)]
    public function sendSms(Request $request): JsonResponse
    {
        // 校验参数
        $params = $this->smsService->validateParams(trimParam($request->all()));

        try {
            // 调用服务类发送验证码
            $result = $this->smsService->sendVerificationCode($params['phone'], $params['type']);

            // 发送成功，记录成功日志（包含请求参数和返回结果）
            $logData = [
                'request' => [
                    'phone' => $params['phone'],
                    'type'  => $params['type']->value,
                ],
                'result'  => $result,
            ];
            logGeneral(LogActionEnum::send_sms->name, "发送短信验证码成功(phone: {$params['phone']}, type: {$params['type']->value})", $logData);

            return success([], '验证码发送成功');
        } catch (Exception $e) {
            // 解析异常信息：如果是JSON格式，提取message字段；否则直接使用异常消息
            $errorMessage = $e->getMessage();
            if (json_validate($errorMessage)) {
                $errorData = json_decode($errorMessage, true);
                $errorMessage = $errorData['message'] ?? $errorMessage;
            }

            // 发送失败，记录失败日志到 gpa_logs 表
            $extendData = [
                'request' => [
                    'phone' => $params['phone'],
                    'type'  => $params['type']->value,
                ],
                'error'   => $errorMessage,
            ];
            logGeneral(LogActionEnum::send_sms->name, "发送短信验证码失败(phone: {$params['phone']}, type: {$params['type']->value}, error: $errorMessage)", $extendData);

            // 重新抛出异常，让框架处理错误响应
            throw $e;
        }
    }
}
