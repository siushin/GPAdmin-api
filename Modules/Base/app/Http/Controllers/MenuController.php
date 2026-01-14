<?php

namespace Modules\Base\Http\Controllers;

use Exception;
use Illuminate\Http\JsonResponse;
use Modules\Base\Enums\AccountTypeEnum;
use Modules\Base\Enums\OperationActionEnum;
use Modules\Base\Models\Menu;
use Modules\Base\Models\RoleMenu;
use Modules\Base\Models\UserRole;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Siushin\LaravelTool\Attributes\ControllerName;
use Siushin\LaravelTool\Attributes\OperationAction;

#[ControllerName('菜单管理')]
class MenuController extends Controller
{
    /**
     * 获取用户菜单列表
     * @return JsonResponse
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::loginInfo)]
    public function getUserMenus(): JsonResponse
    {
        $user = request()->user();

        if (!$user) {
            throw_exception('用户未登录');
        }

        // 判断是否为超级管理员（通过关联关系直接获取）
        $isSuperAdmin = $user->account_type === AccountTypeEnum::Admin
            && $user->adminInfo?->is_super == 1;

        // 超级管理员返回所有 account_type 为 admin 的菜单
        if ($isSuperAdmin) {
            $menus = Menu::where('account_type', AccountTypeEnum::Admin->value)
                ->where('status', 1)
                ->orderBy('sort')
                ->orderBy('menu_id')
                ->get()
                ->toArray();
        } else {
            // 获取用户所有启用角色的ID
            $roleIds = UserRole::query()
                ->where('account_id', $user->id)
                ->whereHas('role', function ($query) {
                    $query->where('status', 1);
                })
                ->pluck('role_id')
                ->toArray();

            // 获取这些角色关联的所有菜单ID（多角色去重）
            $menuIds = [];
            if (!empty($roleIds)) {
                $menuIds = RoleMenu::query()
                    ->whereIn('role_id', $roleIds)
                    ->distinct()
                    ->pluck('menu_id')
                    ->toArray();
            }

            // 获取必须选中的菜单（is_required = 1）
            $requiredMenuIds = Menu::query()
                ->where('account_type', $user->account_type->value)
                ->where('is_required', 1)
                ->where('status', 1)
                ->pluck('menu_id')
                ->toArray();

            // 合并必须选中的菜单和角色分配的菜单
            $allMenuIds = array_values(array_unique(array_merge($menuIds, $requiredMenuIds)));

            if (empty($allMenuIds)) {
                $menus = [];
            } else {
                $menus = Menu::query()
                    ->whereIn('menu_id', $allMenuIds)
                    ->where('status', 1)
                    ->orderBy('sort')
                    ->orderBy('menu_id')
                    ->get()
                    ->toArray();
            }
        }

        // 转换为树形结构
        $menuTree = $this->buildMenuTree($menus);

        return success($menuTree, '获取菜单成功');
    }

    /**
     * 构建菜单树形结构
     * @param array $menus
     * @param int   $parentId
     * @return array
     */
    private function buildMenuTree(array $menus, int $parentId = 0): array
    {
        $tree = [];

        foreach ($menus as $menu) {
            if ($menu['parent_id'] == $parentId) {
                $menuItem = [
                    'path'      => $menu['menu_path'],
                    'name'      => $menu['menu_key'],
                    'title'     => $menu['menu_name'],
                    'icon'      => $menu['menu_icon'],
                    'component' => $menu['component'],
                    'redirect'  => $menu['redirect'],
                ];

                // 移除null和空字符串字段（但保留false和0）
                $filteredMenuItem = array_filter($menuItem, function ($value) {
                    return $value !== null && $value !== '';
                });
                $menuItem = $filteredMenuItem;

                // 递归获取子菜单
                $children = $this->buildMenuTree($menus, $menu['menu_id']);
                if (!empty($children)) {
                    $menuItem['routes'] = $children;
                }

                $tree[] = $menuItem;
            }
        }

        return $tree;
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
