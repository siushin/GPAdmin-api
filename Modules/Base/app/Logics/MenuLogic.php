<?php

namespace Modules\Base\Logics;

use Exception;
use Modules\Base\Enums\AccountTypeEnum;
use Modules\Base\Models\Menu;
use Modules\Base\Models\Module;
use Modules\Base\Models\RoleMenu;
use Modules\Base\Models\UserRole;

/**
 * 菜单逻辑类
 * 处理菜单相关的业务逻辑
 */
class MenuLogic
{
    /**
     * 获取用户菜单列表
     * @return array
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    public static function getUserMenus(): array
    {
        $user = request()->user();

        if (!$user) {
            throw_exception('用户未登录');
        }

        // 判断是否为超级管理员（通过关联关系直接获取）
        $isSuperAdmin = $user->account_type === AccountTypeEnum::Admin
            && $user->adminInfo?->is_super == 1;

        // 1. 获取用户模块列表
        if ($isSuperAdmin) {
            // 超级管理员获取所有启用的模块
            $modules = Module::where('module_status', 1)
                ->where('module_is_installed', 1)
                ->orderBy('module_priority', 'desc')
                ->orderBy('module_id', 'asc')
                ->get()
                ->toArray();
        } else {
            // 普通用户获取已分配的模块（去重模块ID）
            $moduleIds = array_unique($user->modules()
                ->where('module_status', 1)
                ->where('module_is_installed', 1)
                ->pluck('module_id')
                ->toArray());

            if (empty($moduleIds)) {
                $modules = [];
            } else {
                $modules = Module::whereIn('module_id', $moduleIds)
                    ->orderBy('module_priority', 'desc')
                    ->orderBy('module_id', 'asc')
                    ->get()
                    ->toArray();
            }
        }

        // 确保模块列表不重复（按 module_id 去重）
        $uniqueModules = [];
        $processedModuleIds = [];
        foreach ($modules as $module) {
            $moduleId = $module['module_id'] ?? 0;
            if (!isset($processedModuleIds[$moduleId])) {
                $uniqueModules[] = $module;
                $processedModuleIds[$moduleId] = true;
            }
        }
        $modules = $uniqueModules;

        // 2. 获取用户菜单列表
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

            // 合并必须选中的菜单和角色分配的菜单（去重）
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

        // 按 menu_key 去重（解决数据库中重复菜单的问题）
        $uniqueMenus = [];
        $processedMenuKeys = [];
        foreach ($menus as $menu) {
            $menuKey = $menu['menu_key'] ?? $menu['menu_name'];
            if (!isset($processedMenuKeys[$menuKey])) {
                $uniqueMenus[] = $menu;
                $processedMenuKeys[$menuKey] = true;
            }
        }
        $menus = $uniqueMenus;

        // 3. 按模块分组菜单
        $menusByModule = [];
        foreach ($menus as $menu) {
            $moduleId = $menu['module_id'] ?? 0;
            if (!isset($menusByModule[$moduleId])) {
                $menusByModule[$moduleId] = [];
            }
            $menusByModule[$moduleId][] = $menu;
        }

        // 4. 组装模块菜单数据
        $result = [];
        foreach ($modules as $module) {
            $moduleId = $module['module_id'];
            $moduleMenus = $menusByModule[$moduleId] ?? [];

            // 如果该模块下没有菜单，跳过
            if (empty($moduleMenus)) {
                continue;
            }

            // 获取该模块的第一个菜单路径，用于生成模块的顶级路径
            $firstMenuPath = null;
            foreach ($moduleMenus as $menu) {
                if (!empty($menu['menu_path'])) {
                    // 获取路径的第一级（例如：/admin/sub-page1 -> /admin）
                    $pathParts = explode('/', trim($menu['menu_path'], '/'));
                    if (!empty($pathParts[0])) {
                        $firstMenuPath = '/' . $pathParts[0];
                        break;
                    }
                }
            }

            // 如果没有找到路径，使用模块别名或模块名称生成路径
            if (!$firstMenuPath) {
                $moduleAlias = $module['module_alias'] ?? strtolower($module['module_name']);
                $firstMenuPath = '/' . $moduleAlias;
            }

            // 构建模块菜单项
            $moduleMenuItem = [
                'path' => $firstMenuPath,
                'name' => $module['module_title'] ?? $module['module_name'],
            ];

            // 如果有图标，添加到菜单项中
            if (!empty($module['module_icon'])) {
                $moduleMenuItem['icon'] = $module['module_icon'];
            }

            // 构建该模块下的菜单树
            $moduleMenuTree = self::buildMenuTree($moduleMenus);
            if (!empty($moduleMenuTree)) {
                $moduleMenuItem['routes'] = $moduleMenuTree;
            }

            $result[] = $moduleMenuItem;
        }

        return $result;
    }

    /**
     * 构建菜单树形结构
     * @param array $menus
     * @param int   $parentId
     * @return array
     * @author siushin<siushin@163.com>
     */
    public static function buildMenuTree(array $menus, int $parentId = 0): array
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
                $children = self::buildMenuTree($menus, $menu['menu_id']);
                if (!empty($children)) {
                    $menuItem['routes'] = $children;
                }

                $tree[] = $menuItem;
            }
        }

        return $tree;
    }
}

