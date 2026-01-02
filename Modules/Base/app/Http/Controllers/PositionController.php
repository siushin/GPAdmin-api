<?php

namespace Modules\Base\Http\Controllers;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Base\Enums\OperationActionEnum;
use Modules\Base\Models\Position;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Siushin\LaravelTool\Attributes\OperationAction;

/**
 * 控制器：职位管理
 * @module 组织架构管理
 */
class PositionController extends Controller
{
    /**
     * 获取职位列表（全部）
     * @param Request $request
     * @return JsonResponse
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::list)]
    public function list(Request $request): JsonResponse
    {
        $params = trimParam($request->all());
        return success(Position::getAllData($params));
    }

    /**
     * 职位列表
     * @return JsonResponse
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::index)]
    public function index(): JsonResponse
    {
        $params = trimParam(request()->all());
        return success(Position::getPageData($params));
    }

    /**
     * 添加职位
     * @return JsonResponse
     * @throws ContainerExceptionInterface|NotFoundExceptionInterface
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::add)]
    public function add(): JsonResponse
    {
        $params = trimParam(request()->all());
        return success(Position::addPosition($params));
    }

    /**
     * 更新职位
     * @return JsonResponse
     * @throws ContainerExceptionInterface|NotFoundExceptionInterface
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::update)]
    public function update(): JsonResponse
    {
        $params = trimParam(request()->all());
        return success(Position::updatePosition($params));
    }

    /**
     * 删除职位
     * @return JsonResponse
     * @throws ContainerExceptionInterface|NotFoundExceptionInterface
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::delete)]
    public function delete(): JsonResponse
    {
        $params = trimParam(request()->all());
        return success(Position::deletePosition($params));
    }
}
