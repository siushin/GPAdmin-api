<?php

namespace Modules\Base\Http\Controllers;

use Exception;
use Illuminate\Http\JsonResponse;
use Modules\Base\Enums\OperationActionEnum;
use Modules\Base\Models\NotificationRead;
use Siushin\LaravelTool\Attributes\OperationAction;

/**
 * 控制器：通知查看记录管理
 * @module 通知管理
 */
class NotificationReadController extends Controller
{
    /**
     * 获取通知查看记录列表
     * @return JsonResponse
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::index)]
    public function index(): JsonResponse
    {
        $params = trimParam(request()->all());
        return success(NotificationRead::getPageData($params));
    }
}

