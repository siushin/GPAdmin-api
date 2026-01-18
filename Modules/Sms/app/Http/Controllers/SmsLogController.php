<?php

namespace Modules\Sms\Http\Controllers;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Base\Enums\OperationActionEnum;
use Modules\Sms\Enums\SmsTypeEnum;
use Modules\Sms\Models\SmsLog;
use Siushin\LaravelTool\Attributes\ControllerName;
use Siushin\LaravelTool\Attributes\OperationAction;
use Siushin\LaravelTool\Enums\RequestSourceEnum;

#[ControllerName('短信发送记录')]
class SmsLogController extends Controller
{
    /**
     * 获取短信发送记录搜索数据
     * @return JsonResponse
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::paramData)]
    public function getSmsLogSearchData(): JsonResponse
    {
        // 短信类型列表
        $smsTypeList = [];
        foreach (SmsTypeEnum::cases() as $case) {
            $smsTypeList[] = [
                'label' => $case->value,
                'value' => $case->value,
            ];
        }

        // 访问来源列表
        $sourceTypeList = [];
        foreach (RequestSourceEnum::cases() as $case) {
            $sourceTypeList[] = [
                'label' => $case->value,
                'value' => $case->value,
            ];
        }

        // 发送状态列表（固定值）
        $statusList = [
            ['label' => '成功', 'value' => 1],
            ['label' => '失败', 'value' => 0],
        ];

        return success([
            'sms_type'    => $smsTypeList,
            'source_type' => $sourceTypeList,
            'status'      => $statusList,
        ]);
    }

    /**
     * 短信发送记录列表
     * @param Request $request
     * @return JsonResponse
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::index)]
    public function index(Request $request): JsonResponse
    {
        $params = $request->all();
        return success(SmsLog::getPageData($params));
    }
}

