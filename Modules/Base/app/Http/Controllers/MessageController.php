<?php

namespace Modules\Base\Http\Controllers;

use Exception;
use Illuminate\Http\JsonResponse;
use Modules\Base\Enums\NotificationReadTypeEnum;
use Modules\Base\Enums\OperationActionEnum;
use Modules\Base\Models\Message;
use Modules\Base\Models\NotificationRead;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Siushin\LaravelTool\Attributes\ControllerName;
use Siushin\LaravelTool\Attributes\OperationAction;

#[ControllerName('站内信')]
class MessageController extends Controller
{
    /**
     * 获取站内信列表
     * @return JsonResponse
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::index)]
    public function index(): JsonResponse
    {
        $params = request()->all();
        return success(Message::getPageData($params));
    }

    /**
     * 添加站内信
     * @return JsonResponse
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::add)]
    public function add(): JsonResponse
    {
        $params = request()->all();
        return success(Message::addMessage($params));
    }

    /**
     * 更新站内信
     * @return JsonResponse
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::update)]
    public function update(): JsonResponse
    {
        $params = request()->all();
        return success(Message::updateMessage($params));
    }

    /**
     * 删除站内信
     * @return JsonResponse
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::delete)]
    public function delete(): JsonResponse
    {
        $params = trimParam(request()->only(['id']));
        return success(Message::deleteMessage($params));
    }

    /**
     * 查看站内信详情
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
        $message = Message::query()->find($id);
        if (!$message) {
            throw_exception('站内信不存在');
        }

        // 记录查看记录（需要记录登录人信息）
        $request = request();
        $accountId = currentUserId(); // 站内信必须记录登录人信息
        if (!$accountId) {
            throw_exception('查看站内信需要登录');
        }
        $ipAddress = $request->ip();
        $ipLocation = getIpLocation($ipAddress);

        NotificationRead::recordRead(
            NotificationReadTypeEnum::Message->value,
            $id,
            $accountId,
            $ipAddress,
            $ipLocation
        );

        // 如果当前用户是接收者，更新站内信状态为已读
        if ($message->receiver_id == $accountId && $message->status == Message::STATUS_UNREAD) {
            $message->status = Message::STATUS_READ;
            $message->save();
        }

        return success($message->toArray());
    }
}

