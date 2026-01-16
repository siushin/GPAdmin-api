<?php

namespace Modules\Base\Http\Controllers;

use Exception;
use Illuminate\Http\JsonResponse;
use Modules\Base\Enums\NotificationReadTypeEnum;
use Modules\Base\Enums\OperationActionEnum;
use Modules\Base\Models\NotificationRead;
use Modules\Base\Models\SystemNotification;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Siushin\LaravelTool\Attributes\ControllerName;
use Siushin\LaravelTool\Attributes\OperationAction;

#[ControllerName('系统通知')]
class SystemNotificationController extends Controller
{
    /**
     * 获取系统通知列表
     * @return JsonResponse
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::index)]
    public function index(): JsonResponse
    {
        $params = request()->all();
        return success(SystemNotification::getPageData($params));
    }

    /**
     * 添加系统通知
     * @return JsonResponse
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::add)]
    public function add(): JsonResponse
    {
        $params = request()->all();
        return success(SystemNotification::addSystemNotification($params));
    }

    /**
     * 更新系统通知
     * @return JsonResponse
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::update)]
    public function update(): JsonResponse
    {
        $params = request()->all();
        return success(SystemNotification::updateSystemNotification($params));
    }

    /**
     * 删除系统通知
     * @return JsonResponse
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::delete)]
    public function delete(): JsonResponse
    {
        $params = trimParam(request()->only(['id']));
        return success(SystemNotification::deleteSystemNotification($params));
    }

    /**
     * 查看系统通知详情
     * @return JsonResponse
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    public function show(): JsonResponse
    {
        $params = trimParam(request()->only(['id']));
        if (empty($params['id'])) {
            throw_exception('缺少 id 参数');
        }

        $id = $params['id'];
        $notification = SystemNotification::query()->find($id);
        if (!$notification) {
            throw_exception('系统通知不存在');
        }

        // 记录查看记录（需要记录登录人信息）
        $request = request();
        $accountId = currentUserId(); // 如果用户已登录，获取账号ID；否则为 null
        $ipAddress = $request->ip();
        $ipLocation = getIpLocation($ipAddress);

        NotificationRead::recordRead(
            NotificationReadTypeEnum::SystemNotification->value,
            $id,
            $accountId,
            $ipAddress,
            $ipLocation
        );

        return success($notification->toArray());
    }
}

