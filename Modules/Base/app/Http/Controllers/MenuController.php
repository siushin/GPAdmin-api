<?php

namespace Modules\Base\Http\Controllers;

use Exception;
use Illuminate\Http\JsonResponse;
use Modules\Base\Enums\OperationActionEnum;
use Modules\Base\Logics\MenuLogic;
use Modules\Base\Models\Menu;
use Modules\Base\Models\Module;
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
     * 获取搜索框数据：菜单列表
     * @return JsonResponse
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::paramData)]
    public function getMenuListSearchData(): JsonResponse
    {
        // 获取所有模块数据
        $modules = Module::query()
            ->where('module_status', 1) // 只获取已启用的模块
            ->where('module_is_installed', 1) // 只获取已安装的模块
            ->orderBy('module_priority', 'desc')
            ->orderBy('module_id', 'asc')
            ->get();

        $moduleList = [];
        foreach ($modules as $module) {
            $moduleList[] = [
                'label' => $module->module_title ?: $module->module_name,
                'value' => $module->module_id,
            ];
        }

        return success([
            'module' => $moduleList,
        ]);
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
