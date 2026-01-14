<?php

namespace Modules\Base\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Base\Enums\AccountTypeEnum;
use Modules\Base\Models\Account;
use Modules\Base\Models\Menu;
use Modules\Base\Models\Role;
use Modules\Base\Models\RoleMenu;
use Modules\Base\Models\UserRole;

/**
 * 数据填充：RBAC权限管理
 */
class RbacSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = now();

        // 1. 创建 Admin 账号类型的角色
        $adminRoles = $this->createAdminRoles($now);

        // 2. 创建 User 账号类型的角色
        $userRoles = $this->createUserRoles($now);

        // 3. 为 Admin 角色分配菜单权限
        $this->assignMenusToAdminRoles($adminRoles, $now);

        // 4. 为 User 角色分配菜单权限（如果有User类型的菜单）
        $this->assignMenusToUserRoles($userRoles, $now);

        // 5. 为账号分配角色
        $this->assignRolesToAccounts($adminRoles, $userRoles, $now);
    }

    /**
     * 创建 Admin 账号类型的角色
     */
    private function createAdminRoles($now): array
    {
        $adminType = AccountTypeEnum::Admin->value;

        $roles = [
            [
                'role_name'   => '系统管理员',
                'role_code'   => 'system_admin',
                'description' => '系统管理相关权限的管理员角色',
                'status'      => 1,
                'sort'        => 1,
            ],
            [
                'role_name'   => '运营管理员',
                'role_code'   => 'operation_admin',
                'description' => '负责日常运营管理的管理员角色',
                'status'      => 1,
                'sort'        => 2,
            ],
            [
                'role_name'   => '普通管理员',
                'role_code'   => 'normal_admin',
                'description' => '拥有基础管理权限的普通管理员角色',
                'status'      => 1,
                'sort'        => 3,
            ],
        ];

        $roleIds = [];
        foreach ($roles as $role) {
            // 检查角色是否已存在
            $exists = Role::query()
                ->where('account_type', $adminType)
                ->where('role_code', $role['role_code'])
                ->exists();

            if (!$exists) {
                $roleId = generateId();
                Role::query()->insert([
                    'role_id'      => $roleId,
                    'account_type' => $adminType,
                    'role_name'    => $role['role_name'],
                    'role_code'    => $role['role_code'],
                    'description'  => $role['description'],
                    'status'       => $role['status'],
                    'sort'         => $role['sort'],
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ]);
                $roleIds[$role['role_code']] = $roleId;
            } else {
                // 如果已存在，获取角色ID
                $existingRole = Role::query()
                    ->where('account_type', $adminType)
                    ->where('role_code', $role['role_code'])
                    ->first();
                $roleIds[$role['role_code']] = $existingRole->role_id;
            }
        }

        return $roleIds;
    }

    /**
     * 创建 User 账号类型的角色
     */
    private function createUserRoles($now): array
    {
        $userType = AccountTypeEnum::User->value;

        $roles = [
            [
                'role_name'   => '普通用户',
                'role_code'   => 'normal_user',
                'description' => '拥有基础功能的普通用户角色',
                'status'      => 1,
                'sort'        => 1,
            ],
            [
                'role_name'   => 'VIP用户',
                'role_code'   => 'vip_user',
                'description' => '拥有更多权限的VIP用户角色',
                'status'      => 1,
                'sort'        => 2,
            ],
        ];

        $roleIds = [];
        foreach ($roles as $role) {
            // 检查角色是否已存在
            $exists = Role::query()
                ->where('account_type', $userType)
                ->where('role_code', $role['role_code'])
                ->exists();

            if (!$exists) {
                $roleId = generateId();
                Role::query()->insert([
                    'role_id'      => $roleId,
                    'account_type' => $userType,
                    'role_name'    => $role['role_name'],
                    'role_code'    => $role['role_code'],
                    'description'  => $role['description'],
                    'status'       => $role['status'],
                    'sort'         => $role['sort'],
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ]);
                $roleIds[$role['role_code']] = $roleId;
            } else {
                // 如果已存在，获取角色ID
                $existingRole = Role::query()
                    ->where('account_type', $userType)
                    ->where('role_code', $role['role_code'])
                    ->first();
                $roleIds[$role['role_code']] = $existingRole->role_id;
            }
        }

        return $roleIds;
    }

    /**
     * 为 Admin 角色分配菜单权限
     */
    private function assignMenusToAdminRoles(array $adminRoles, $now): void
    {
        $adminType = AccountTypeEnum::Admin->value;

        // 获取所有 Admin 类型的菜单
        $menus = Menu::query()
            ->where('account_type', $adminType)
            ->where('status', 1)
            ->get();

        if ($menus->isEmpty()) {
            return;
        }

        // 超级管理员：分配所有菜单权限
        if (isset($adminRoles['super_admin'])) {
            $this->assignMenusToRole($adminRoles['super_admin'], $menus, $now);
        }

        // 系统管理员：分配系统管理相关菜单
        if (isset($adminRoles['system_admin'])) {
            $systemMenus = $menus->filter(function ($menu) {
                return str_contains($menu->menu_key ?? '', 'system.') ||
                    str_contains($menu->menu_path ?? '', '/system');
            });
            $this->assignMenusToRole($adminRoles['system_admin'], $systemMenus, $now);
        }

        // 普通管理员：分配基础菜单（工作台、应用管理等，不包含系统管理）
        if (isset($adminRoles['normal_admin'])) {
            $normalMenus = $menus->filter(function ($menu) {
                return !str_contains($menu->menu_key ?? '', 'system.') &&
                    !str_contains($menu->menu_path ?? '', '/system');
            });
            $this->assignMenusToRole($adminRoles['normal_admin'], $normalMenus, $now);
        }
    }

    /**
     * 为 User 角色分配菜单权限
     */
    private function assignMenusToUserRoles(array $userRoles, $now): void
    {
        $userType = AccountTypeEnum::User->value;

        // 获取所有 User 类型的菜单（如果有的话）
        $menus = Menu::query()
            ->where('account_type', $userType)
            ->where('status', 1)
            ->get();

        if ($menus->isEmpty()) {
            // 如果没有 User 类型的菜单，暂时不分配
            return;
        }

        // VIP用户：分配所有菜单权限
        if (isset($userRoles['vip_user'])) {
            $this->assignMenusToRole($userRoles['vip_user'], $menus, $now);
        }

        // 普通用户：分配部分基础菜单
        if (isset($userRoles['normal_user'])) {
            $normalMenus = $menus->take((int)($menus->count() * 0.7)); // 分配70%的菜单
            $this->assignMenusToRole($userRoles['normal_user'], $normalMenus, $now);
        }
    }

    /**
     * 为角色分配菜单
     */
    private function assignMenusToRole($roleId, $menus, $now): void
    {
        $roleMenuData = [];
        foreach ($menus as $menu) {
            // 检查是否已存在关联
            $exists = RoleMenu::query()
                ->where('role_id', $roleId)
                ->where('menu_id', $menu->menu_id)
                ->exists();

            if (!$exists) {
                $roleMenuData[] = [
                    'id'         => generateId(),
                    'role_id'    => $roleId,
                    'menu_id'    => $menu->menu_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (!empty($roleMenuData)) {
            RoleMenu::query()->insert($roleMenuData);
        }
    }

    /**
     * 为账号分配角色
     */
    private function assignRolesToAccounts(array $adminRoles, array $userRoles, $now): void
    {
        $adminType = AccountTypeEnum::Admin->value;
        $userType = AccountTypeEnum::User->value;

        // 为超级管理员账号分配超级管理员角色
        $superAdminAccount = Account::query()
            ->where('account_type', $adminType)
            ->where('username', env('APP_ADMIN', 'admin'))
            ->first();

        if ($superAdminAccount && isset($adminRoles['super_admin'])) {
            $this->assignRoleToAccount($superAdminAccount->id, $adminRoles['super_admin'], $now);
        }

        // 为前5个Admin账号随机分配角色
        $adminAccounts = Account::query()
            ->where('account_type', $adminType)
            ->where('username', '!=', env('APP_ADMIN', 'admin'))
            ->limit(5)
            ->get();

        $adminRoleCodes = array_keys($adminRoles);
        foreach ($adminAccounts as $index => $account) {
            // 第一个分配系统管理员，其他随机分配
            $roleCode = $index === 0 ? 'system_admin' : $adminRoleCodes[array_rand($adminRoleCodes)];
            if (isset($adminRoles[$roleCode])) {
                $this->assignRoleToAccount($account->id, $adminRoles[$roleCode], $now);
            }
        }

        // 为前10个User账号随机分配角色
        $userAccounts = Account::query()
            ->where('account_type', $userType)
            ->limit(10)
            ->get();

        $userRoleCodes = array_keys($userRoles);
        foreach ($userAccounts as $account) {
            $roleCode = $userRoleCodes[array_rand($userRoleCodes)];
            if (isset($userRoles[$roleCode])) {
                $this->assignRoleToAccount($account->id, $userRoles[$roleCode], $now);
            }
        }
    }

    /**
     * 为账号分配角色
     */
    private function assignRoleToAccount($accountId, $roleId, $now): void
    {
        // 检查是否已存在关联
        $exists = UserRole::query()
            ->where('account_id', $accountId)
            ->where('role_id', $roleId)
            ->exists();

        if (!$exists) {
            UserRole::query()->insert([
                'id'         => generateId(),
                'account_id' => $accountId,
                'role_id'    => $roleId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}

