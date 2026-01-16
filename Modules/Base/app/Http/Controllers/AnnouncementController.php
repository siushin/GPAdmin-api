<?php

namespace Modules\Base\Http\Controllers;

use Exception;
use Illuminate\Http\JsonResponse;
use Modules\Base\Enums\NotificationReadTypeEnum;
use Modules\Base\Enums\OperationActionEnum;
use Modules\Base\Models\Announcement;
use Modules\Base\Models\NotificationRead;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Siushin\LaravelTool\Attributes\ControllerName;
use Siushin\LaravelTool\Attributes\OperationAction;

#[ControllerName('通知管理')]
class AnnouncementController extends Controller
{

    /**
     * 获取搜索框数据：公告列表
     * @return JsonResponse
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::paramData)]
    public function getAnnouncementListSearchData(): JsonResponse
    {
        $data = Announcement::getAnnouncementListSearchData();
        return success($data);
    }
    
    /**
     * 获取公告列表
     * @return JsonResponse
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::index)]
    public function index(): JsonResponse
    {
        $params = request()->all();
        return success(Announcement::getPageData($params));
    }

    /**
     * 添加公告
     * @return JsonResponse
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::add)]
    public function add(): JsonResponse
    {
        $params = request()->all();
        return success(Announcement::addAnnouncement($params));
    }

    /**
     * 更新公告
     * @return JsonResponse
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::update)]
    public function update(): JsonResponse
    {
        $params = request()->all();
        return success(Announcement::updateAnnouncement($params));
    }

    /**
     * 删除公告
     * @return JsonResponse
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::delete)]
    public function delete(): JsonResponse
    {
        $params = trimParam(request()->only(['id']));
        return success(Announcement::deleteAnnouncement($params));
    }

    /**
     * 查看公告详情
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
        $announcement = Announcement::query()->find($id);
        if (!$announcement) {
            throw_exception('公告不存在');
        }

        // 记录查看记录（可选记录登录人信息，如果用户已登录则记录，未登录则不记录）
        $request = request();
        $accountId = currentUserId(); // 如果用户已登录，获取账号ID；否则为 null（公告可以不登录查看）
        $ipAddress = $request->ip();
        $ipLocation = getIpLocation($ipAddress);

        NotificationRead::recordRead(
            NotificationReadTypeEnum::Announcement->value,
            $id,
            $accountId,
            $ipAddress,
            $ipLocation
        );

        return success($announcement->toArray());
    }
}

