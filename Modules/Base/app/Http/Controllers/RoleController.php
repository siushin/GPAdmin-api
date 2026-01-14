<?php

namespace Modules\Base\Http\Controllers;

use Exception;
use Illuminate\Http\JsonResponse;
use Modules\Base\Enums\AccountTypeEnum;
use Modules\Base\Enums\OperationActionEnum;
use Modules\Base\Models\Menu;
use Modules\Base\Models\Module;
use Modules\Base\Models\Role;
use Modules\Base\Models\RoleMenu;
use Illuminate\Support\Facades\DB;
use Siushin\LaravelTool\Attributes\ControllerName;
use Siushin\LaravelTool\Attributes\OperationAction;
use Siushin\Util\Traits\ParamTool;

#[ControllerName('角色管理')]
class RoleController extends Controller
{
    use ParamTool;

    /**
     * 角色列表（全部）
     * @return JsonResponse
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::list)]
    public function list(): JsonResponse
    {
        $params = request()->all();
        return success(Role::getAllData($params));
    }

    /**
     * 角色列表
     * @return JsonResponse
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::index)]
    public function index(): JsonResponse
    {
        $params = request()->all();
        return success(Role::getPageData($params));
    }

    /**
     * 添加角色
     * @return JsonResponse
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::add)]
    public function add(): JsonResponse
    {
        $params = request()->all();
        return success(Role::addRole($params));
    }

    /**
     * 更新角色
     * @return JsonResponse
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::update)]
    public function update(): JsonResponse
    {
        $params = request()->all();
        return success(Role::updateRole($params));
    }

    /**
     * 删除角色
     * @return JsonResponse
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::delete)]
    public function delete(): JsonResponse
    {
        $params = request()->all();
        return success(Role::deleteRole($params));
    }

    /**
     * 获取角色菜单（按模块分组）
     * @return JsonResponse
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::list)]
    public function getMenus(): JsonResponse
    {
        $params = request()->all();
        self::checkEmptyParam($params, ['role_id', 'account_type']);

        $roleId = $params['role_id'];
        $accountType = $params['account_type'];

        // 验证 account_type 是否为有效枚举值
        $allowAccountTypes = array_column(AccountTypeEnum::cases(), 'value');
        if (!in_array($accountType, $allowAccountTypes)) {
            throw_exception('账号类型无效');
        }

        // 验证角色是否存在
        $role = Role::query()->find($roleId);
        if (!$role) {
            throw_exception('角色不存在');
        }

        // 获取所有模块（已启用的）
        $modules = Module::query()
            ->where('module_status', 1)
            ->orderBy('module_priority', 'desc')
            ->get();

        // 获取该账号类型下的所有菜单，按模块分组
        $menus = Menu::query()
            ->where('account_type', $accountType)
            ->where('status', 1)
            ->orderBy('sort', 'asc')
            ->orderBy('menu_id', 'asc')
            ->get()
            ->toArray();

        // 获取角色已分配的菜单ID
        $checkedMenuIds = RoleMenu::query()
            ->where('role_id', $roleId)
            ->pluck('menu_id')
            ->toArray();

        // 按模块分组菜单
        $modulesWithMenus = [];
        foreach ($modules as $module) {
            $moduleMenus = array_filter($menus, function ($menu) use ($module) {
                return $menu['module_id'] == $module->module_id;
            });

            if (!empty($moduleMenus)) {
                $menuTree = $this->buildMenuTree(array_values($moduleMenus));
                $modulesWithMenus[] = [
                    'module' => [
                        'module_id'    => $module->module_id,
                        'module_name'  => $module->module_name,
                        'module_alias' => $module->module_alias,
                    ],
                    'menus'  => $menuTree,
                ];
            }
        }

        // 处理没有模块的菜单（module_id 为 null 或 0）
        $orphanMenus = array_filter($menus, function ($menu) {
            return empty($menu['module_id']);
        });

        if (!empty($orphanMenus)) {
            $menuTree = $this->buildMenuTree(array_values($orphanMenus));
            array_unshift($modulesWithMenus, [
                'module' => [
                    'module_id'    => 0,
                    'module_name'  => '未分类',
                    'module_alias' => 'uncategorized',
                ],
                'menus'  => $menuTree,
            ]);
        }

        return success([
            'modules_with_menus' => $modulesWithMenus,
            'checked_menu_ids'   => $checkedMenuIds,
        ]);
    }

    /**
     * 更新角色菜单
     * @return JsonResponse
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::update)]
    public function updateMenus(): JsonResponse
    {
        $params = request()->all();
        self::checkEmptyParam($params, ['role_id']);

        $roleId = $params['role_id'];
        $menuIds = $params['menu_ids'] ?? [];

        // 验证角色是否存在
        $role = Role::query()->find($roleId);
        if (!$role) {
            throw_exception('角色不存在');
        }

        // 开启事务
        DB::beginTransaction();
        try {
            // 删除原有的角色菜单关联
            RoleMenu::query()->where('role_id', $roleId)->delete();

            // 如果有新的菜单ID，批量插入
            if (!empty($menuIds)) {
                $insertData = [];
                $now = now();
                foreach ($menuIds as $menuId) {
                    $insertData[] = [
                        'role_id'    => $roleId,
                        'menu_id'    => $menuId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                RoleMenu::query()->insert($insertData);
            }

            DB::commit();

            return success([], '更新角色菜单成功');
        } catch (Exception $e) {
            DB::rollBack();
            throw_exception('更新角色菜单失败：' . $e->getMessage());
        }
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
                    'menu_id'     => $menu['menu_id'],
                    'menu_name'   => $menu['menu_name'],
                    'menu_key'    => $menu['menu_key'],
                    'menu_type'   => $menu['menu_type'],
                    'parent_id'   => $menu['parent_id'],
                    'is_required' => $menu['is_required'] ?? 0,
                ];

                // 递归获取子菜单
                $children = $this->buildMenuTree($menus, $menu['menu_id']);
                if (!empty($children)) {
                    $menuItem['children'] = $children;
                }

                $tree[] = $menuItem;
            }
        }

        return $tree;
    }
}
