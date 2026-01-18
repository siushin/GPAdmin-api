<?php

namespace Modules\Base\Http\Controllers;

use Exception;
use Illuminate\Http\JsonResponse;
use Modules\Base\Enums\OperationActionEnum;
use Modules\Base\Logics\MenuLogic;
use Modules\Base\Models\Menu;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Siushin\LaravelTool\Attributes\ControllerName;
use Siushin\LaravelTool\Attributes\OperationAction;
use Siushin\Util\Traits\ParamTool;

#[ControllerName('菜单管理')]
class MenuController extends Controller
{
    use ParamTool;

    /**
     * 获取用户菜单列表
     * @return JsonResponse
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::loginInfo)]
    public function getUserMenus(): JsonResponse
    {
        return success(MenuLogic::getUserMenus(), '获取菜单成功');
    }

    /**
     * 菜单列表
     * @return JsonResponse
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::index)]
    public function index(): JsonResponse
    {
        $params = request()->all();
        return success(Menu::getPageData($params));
    }

    /**
     * 添加菜单
     * @return JsonResponse
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::add)]
    public function add(): JsonResponse
    {
        $params = request()->all();
        return success(Menu::addMenu($params));
    }

    /**
     * 更新菜单
     * @return JsonResponse
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::update)]
    public function update(): JsonResponse
    {
        $params = request()->all();
        return success(Menu::updateMenu($params));
    }

    /**
     * 删除菜单
     * @return JsonResponse
     * @throws ContainerExceptionInterface|NotFoundExceptionInterface
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::delete)]
    public function delete(): JsonResponse
    {
        $params = request()->all();
        return success(Menu::deleteMenu($params));
    }

    /**
     * 获取菜单树形结构
     * @return JsonResponse
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    public function tree(): JsonResponse
    {
        $params = request()->all();
        return success(Menu::getTreeData($params));
    }

    /**
     * 获取目录树形结构（仅目录类型，用于筛选）
     * @return JsonResponse
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    public function dirTree(): JsonResponse
    {
        $params = request()->all();
        return success(Menu::getDirTree($params));
    }
}
