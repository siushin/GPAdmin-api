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

        // 获取角色已分配的菜单（包含 target_module_id 信息）
        $roleMenus = RoleMenu::query()
            ->where('role_id', $roleId)
            ->get()
            ->keyBy('menu_id')
            ->toArray();

        $checkedMenuIds = array_keys($roleMenus);

        // 构建菜单移动映射：menu_id => target_module_id
        // 只包含target_module_id不等于原始module_id的记录
        $menuMoveMap = [];
        $menusMap = array_column($menus, null, 'menu_id');
        foreach ($roleMenus as $menuId => $roleMenu) {
            if (!empty($roleMenu['target_module_id'])) {
                $menu = $menusMap[$menuId] ?? null;
                // 如果目标模块不等于原始模块，才包含在map中
                if ($menu && $roleMenu['target_module_id'] != $menu['module_id']) {
                    $menuMoveMap[$menuId] = $roleMenu['target_module_id'];
                }
            }
        }

        // 按模块分组菜单
        $modulesWithMenus = [];
        foreach ($modules as $module) {
            $moduleMenus = array_filter($menus, function ($menu) use ($module) {
                return $menu['module_id'] == $module->module_id;
            });

            if (!empty($moduleMenus)) {
                $menuTree = $this->buildMenuTree(array_values($moduleMenus), 0, $menuMoveMap);
                $modulesWithMenus[] = [
                    'module' => [
                        'module_id'    => $module->module_id,
                        'module_name'  => $module->module_name,
                        'module_alias' => $module->module_alias,
                        'module_title' => $module->module_title,
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
            $menuTree = $this->buildMenuTree(array_values($orphanMenus), 0, $menuMoveMap);
            array_unshift($modulesWithMenus, [
                'module' => [
                    'module_id'    => 0,
                    'module_name'  => '未分类',
                    'module_alias' => 'uncategorized',
                    'module_title' => '未分类',
                ],
                'menus'  => $menuTree,
            ]);
        }

        return success([
            'modules_with_menus' => $modulesWithMenus,
            'checked_menu_ids'   => $checkedMenuIds,
            'menu_move_map'      => $menuMoveMap,
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
        $menuMoveMap = $params['menu_move_map'] ?? []; // 菜单移动映射：menu_id => target_module_id

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
                // 获取所有菜单的原始模块ID
                $menus = Menu::query()->whereIn('menu_id', $menuIds)->get()->keyBy('menu_id');

                $insertData = [];
                $now = now();
                foreach ($menuIds as $menuId) {
                    $menu = $menus[$menuId] ?? null;
                    if (!$menu) {
                        continue;
                    }

                    $targetModuleId = $menuMoveMap[$menuId] ?? null;
                    // 如果目标模块等于原始模块，则设置为null
                    $finalTargetModuleId = null;
                    if ($targetModuleId !== null && $targetModuleId != $menu->module_id) {
                        $finalTargetModuleId = $targetModuleId;
                    }

                    $insertData[] = [
                        'role_id'          => $roleId,
                        'menu_id'          => $menuId,
                        'target_module_id' => $finalTargetModuleId,
                        'created_at'       => $now,
                        'updated_at'       => $now,
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
     * 获取所有模块列表
     * @return JsonResponse
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::list)]
    public function getModuleList(): JsonResponse
    {
        // 获取所有已启用的模块
        $modules = Module::query()
            ->where('module_status', 1)
            ->orderBy('module_priority', 'desc')
            ->get(['module_id', 'module_name', 'module_alias', 'module_title'])
            ->toArray();

        return success($modules);
    }

    /**
     * 移动菜单组到新模块
     * @return JsonResponse
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::update)]
    public function moveMenuToModule(): JsonResponse
    {
        $params = request()->all();
        self::checkEmptyParam($params, ['role_id', 'menu_ids', 'target_module_id']);

        $roleId = $params['role_id'];
        $menuIds = $params['menu_ids'];
        $targetModuleId = $params['target_module_id'];

        // 验证角色是否存在
        $role = Role::query()->find($roleId);
        if (!$role) {
            throw_exception('角色不存在');
        }

        // 验证目标模块是否存在
        $targetModule = Module::query()->find($targetModuleId);
        if (!$targetModule) {
            throw_exception('目标模块不存在');
        }

        // 开启事务
        DB::beginTransaction();
        try {
            // 获取菜单的原始模块ID
            $menus = Menu::query()->whereIn('menu_id', $menuIds)->get();

            // 逐个更新，如果目标模块等于原始模块，则设置为null
            foreach ($menus as $menu) {
                $finalTargetModuleId = null;
                // 如果目标模块不等于原始模块，才设置target_module_id
                if ($targetModuleId != $menu->module_id) {
                    $finalTargetModuleId = $targetModuleId;
                }

                RoleMenu::query()
                    ->where('role_id', $roleId)
                    ->where('menu_id', $menu->menu_id)
                    ->update(['target_module_id' => $finalTargetModuleId]);
            }

            DB::commit();

            return success([], '移动成功');
        } catch (Exception $e) {
            DB::rollBack();
            throw_exception('移动失败：' . $e->getMessage());
        }
    }

    /**
     * 将菜单组移回原模块
     * @return JsonResponse
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::update)]
    public function moveMenuBackToOriginal(): JsonResponse
    {
        $params = request()->all();
        self::checkEmptyParam($params, ['role_id', 'menu_ids']);

        $roleId = $params['role_id'];
        $menuIds = $params['menu_ids'];

        // 验证角色是否存在
        $role = Role::query()->find($roleId);
        if (!$role) {
            throw_exception('角色不存在');
        }

        // 开启事务
        DB::beginTransaction();
        try {
            // 清除角色菜单关联表中的 target_module_id
            RoleMenu::query()
                ->where('role_id', $roleId)
                ->whereIn('menu_id', $menuIds)
                ->update(['target_module_id' => null]);

            DB::commit();

            return success([], '已移回原处');
        } catch (Exception $e) {
            DB::rollBack();
            throw_exception('移回失败：' . $e->getMessage());
        }
    }

    /**
     * 将指定模块下所有已移入的菜单组移回原模块
     * @return JsonResponse
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::update)]
    public function moveAllBackByModule(): JsonResponse
    {
        $params = request()->all();
        self::checkEmptyParam($params, ['role_id', 'module_id']);

        $roleId = $params['role_id'];
        $moduleId = $params['module_id'];

        // 验证角色是否存在
        $role = Role::query()->find($roleId);
        if (!$role) {
            throw_exception('角色不存在');
        }

        // 验证模块是否存在
        $module = Module::query()->find($moduleId);
        if (!$module) {
            throw_exception('模块不存在');
        }

        // 开启事务
        DB::beginTransaction();
        try {
            // 获取该模块下所有菜单的原始模块ID
            $menus = Menu::query()
                ->where('module_id', $moduleId)
                ->get()
                ->keyBy('menu_id');

            // 获取该角色在该模块下所有已移入的菜单（target_module_id = moduleId 且不等于原始module_id）
            $roleMenus = RoleMenu::query()
                ->where('role_id', $roleId)
                ->where('target_module_id', $moduleId)
                ->whereIn('menu_id', array_keys($menus->toArray()))
                ->get();

            $menuIds = [];
            foreach ($roleMenus as $roleMenu) {
                $menu = $menus[$roleMenu->menu_id] ?? null;
                // 如果目标模块不等于原始模块，说明是移入的
                if ($menu && $roleMenu->target_module_id != $menu->module_id) {
                    $menuIds[] = $roleMenu->menu_id;
                }
            }

            // 清除这些菜单的 target_module_id
            if (!empty($menuIds)) {
                RoleMenu::query()
                    ->where('role_id', $roleId)
                    ->whereIn('menu_id', $menuIds)
                    ->update(['target_module_id' => null]);
            }

            DB::commit();

            return success(['count' => count($menuIds)], "已将 " . count($menuIds) . " 个菜单移回原处");
        } catch (Exception $e) {
            DB::rollBack();
            throw_exception('移回失败：' . $e->getMessage());
        }
    }

    /**
     * 构建菜单树形结构
     * @param array $menus
     * @param int   $parentId
     * @param array $menuMoveMap 菜单移动映射
     * @return array
     */
    private function buildMenuTree(array $menus, int $parentId = 0, array $menuMoveMap = []): array
    {
        $tree = [];

        foreach ($menus as $menu) {
            if ($menu['parent_id'] == $parentId) {
                $menuId = $menu['menu_id'];
                $menuItem = [
                    'menu_id'          => $menuId,
                    'menu_name'        => $menu['menu_name'],
                    'menu_key'         => $menu['menu_key'],
                    'menu_type'        => $menu['menu_type'],
                    'parent_id'        => $menu['parent_id'],
                    'module_id'        => $menu['module_id'] ?? null,
                    'is_required'      => $menu['is_required'] ?? 0,
                    'target_module_id' => $menuMoveMap[$menuId] ?? null,
                ];

                // 递归获取子菜单
                $children = $this->buildMenuTree($menus, $menuId, $menuMoveMap);
                if (!empty($children)) {
                    $menuItem['children'] = $children;
                }

                $tree[] = $menuItem;
            }
        }

        return $tree;
    }
}
