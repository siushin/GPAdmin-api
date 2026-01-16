<?php

namespace Modules\Base\Providers;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Modules\Base\Enums\LogActionEnum;
use Modules\Base\Models\AccountModule;
use Modules\Base\Models\Menu;
use Modules\Base\Models\Module;
use Modules\Base\Models\ModuleMenu;

/**
 * 模块卸载 Provider
 * 处理模块卸载相关的所有操作
 */
class ModuleUninstallProvider
{
    /**
     * 卸载模块
     * @param int $moduleId 模块ID
     * @return array 返回卸载结果
     * @throws Exception
     */
    public function uninstall(int $moduleId): array
    {
        $module = Module::find($moduleId);
        if (!$module) {
            throw_exception('模块不存在');
        }

        // 检查是否为核心模块
        if ($module->module_is_core == 1) {
            throw_exception('核心模块不允许卸载');
        }

        $moduleName = $module->module_name;
        $modulePath = base_path('Modules' . DIRECTORY_SEPARATOR . $moduleName);

        // 开始事务
        DB::beginTransaction();
        try {
            // 1. 移除账号模块关联数据（gpa_account_module）
            $this->removeAccountModules($moduleId);

            // 2. 移除菜单数据
            $this->removeMenus($moduleId);

            // 3. 移除模块目录下的代码
            $this->removeModuleDirectory($modulePath, $moduleName);

            // 4. 更新模块状态
            $module->update([
                'module_is_installed' => 0,
                'module_installed_at' => null,
            ]);

            // 5. 记录日志
            $this->logUninstall($module, $modulePath);

            DB::commit();

            return [
                'module_id'   => $moduleId,
                'module_name' => $moduleName,
                'module_path' => $modulePath,
            ];
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * 移除账号模块关联数据
     * @param int $moduleId
     * @return void
     */
    private function removeAccountModules(int $moduleId): void
    {
        AccountModule::where('module_id', $moduleId)->delete();
    }

    /**
     * 移除菜单数据
     * @param int $moduleId
     * @return void
     */
    private function removeMenus(int $moduleId): void
    {
        // 移除模块菜单关联记录
        ModuleMenu::where('module_id', $moduleId)->delete();

        // 移除菜单数据（软删除）
        Menu::where('module_id', $moduleId)->delete();
    }

    /**
     * 移除模块目录
     * @param string $modulePath 模块路径
     * @param string $moduleName 模块名称
     * @return void
     * @throws Exception
     */
    private function removeModuleDirectory(string $modulePath, string $moduleName): void
    {
        // 检查模块目录是否存在
        if (!File::exists($modulePath)) {
            // 目录不存在，记录警告但不抛出异常
            logGeneral(
                LogActionEnum::delete->name,
                "模块目录不存在，跳过删除: {$modulePath}",
                ['module_name' => $moduleName, 'module_path' => $modulePath]
            );
            return;
        }

        // 检查是否为 Modules 目录下的子目录（安全措施）
        $modulesBasePath = base_path('Modules');
        $realModulePath = realpath($modulePath);
        $realModulesBasePath = realpath($modulesBasePath);

        if (!$realModulePath || !$realModulesBasePath) {
            throw_exception('无法获取模块路径的真实路径');
        }

        // 确保模块路径在 Modules 目录下
        if (!str_starts_with($realModulePath, $realModulesBasePath)) {
            throw_exception('模块路径不在 Modules 目录下，拒绝删除');
        }

        // 删除模块目录
        try {
            File::deleteDirectory($modulePath);
        } catch (Exception $e) {
            throw_exception('删除模块目录失败: ' . $e->getMessage());
        }
    }

    /**
     * 记录卸载日志
     * @param Module $module
     * @param string $modulePath
     * @return void
     */
    private function logUninstall(Module $module, string $modulePath): void
    {
        $logData = [
            'module_id'   => $module->module_id,
            'module_name' => $module->module_name,
            'module_path' => $modulePath,
        ];

        logGeneral(
            LogActionEnum::delete->name,
            "卸载模块成功: {$module->module_name}",
            $logData
        );
    }
}

