<?php

namespace Modules\Base\Providers;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Modules\Base\Enums\LogActionEnum;
use Modules\Base\Enums\ModulePullTypeEnum;
use Modules\Admin\Models\AccountModule;
use Modules\Admin\Models\Menu;
use Modules\Admin\Models\Module;
use Modules\Admin\Models\ModuleMenu;

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

            // 3. 移除模块目录下的代码（传入 module 对象以判断类型）
            $this->removeModuleDirectory($modulePath, $moduleName, $module);

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
     * @param Module $module 模块模型
     * @return void
     * @throws Exception
     */
    private function removeModuleDirectory(string $modulePath, string $moduleName, Module $module): void
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

        // 根据模块拉取类型选择不同的卸载方式
        $pullType = $module->module_pull_type;
        if ($pullType === ModulePullTypeEnum::GIT->value) {
            // Git 子模块：使用 Git 命令正确卸载
            $this->removeGitSubmodule($modulePath, $moduleName);
        } else {
            // URL 下载或其他方式：直接删除目录
            try {
                File::deleteDirectory($modulePath);
            } catch (Exception $e) {
                throw_exception('删除模块目录失败: ' . $e->getMessage());
            }
        }
    }

    /**
     * 移除 Git 子模块
     * @param string $modulePath 模块路径
     * @param string $moduleName 模块名称
     * @return void
     * @throws Exception
     */
    private function removeGitSubmodule(string $modulePath, string $moduleName): void
    {
        $gitPath      = $this->findGitCommand();
        $relativePath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $modulePath);

        // 步骤1: 取消子模块初始化 (git submodule deinit)
        // 这会移除工作目录中的文件，但保留 .gitmodules 配置
        $deinitCommand = sprintf(
            '%s submodule deinit -f -- %s',
            escapeshellarg($gitPath),
            escapeshellarg($relativePath)
        );

        $output    = [];
        $returnVar = 0;
        exec($deinitCommand . ' 2>&1', $output, $returnVar);

        // deinit 可能因为目录不存在而失败，这是正常的，继续执行

        // 步骤2: 从 .gitmodules 和索引中移除子模块 (git submodule rm)
        // 这会自动删除 .gitmodules 中的对应条目，并从 Git 索引中移除
        $rmCommand = sprintf(
            '%s submodule rm -f -- %s',
            escapeshellarg($gitPath),
            escapeshellarg($relativePath)
        );

        exec($rmCommand . ' 2>&1', $output, $returnVar);

        if ($returnVar !== 0) {
            // 如果 rm 失败，可能是子模块未正确配置，手动处理
            $this->removeGitSubmoduleManually($relativePath);
        }

        // 步骤3: 删除模块目录（如果还存在）
        if (File::exists($modulePath)) {
            try {
                File::deleteDirectory($modulePath);
            } catch (Exception $e) {
                // 记录警告，但不抛出异常，因为 Git 命令已经处理了子模块配置
                logGeneral(
                    LogActionEnum::delete->name,
                    "删除 Git 子模块目录失败: {$e->getMessage()}",
                    ['module_name' => $moduleName, 'module_path' => $modulePath]
                );
            }
        }

        // 步骤4: 删除 .git/modules 中的子模块缓存（如果存在）
        $gitModulesPath = base_path('.git' . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . str_replace('/', '_', $relativePath));
        if (File::exists($gitModulesPath)) {
            try {
                File::deleteDirectory($gitModulesPath);
            } catch (Exception $e) {
                // 记录警告但不抛出异常
                logGeneral(
                    LogActionEnum::delete->name,
                    "删除 Git 子模块缓存失败: {$e->getMessage()}",
                    ['module_name' => $moduleName, 'cache_path' => $gitModulesPath]
                );
            }
        }
    }

    /**
     * 手动移除 Git 子模块（当 git submodule rm 失败时使用）
     * @param string $relativePath 相对路径
     * @return void
     * @throws Exception
     */
    private function removeGitSubmoduleManually(string $relativePath): void
    {
        // 手动从 .gitmodules 文件中移除对应条目
        $gitmodulesPath = base_path('.gitmodules');
        if (File::exists($gitmodulesPath)) {
            $content = File::get($gitmodulesPath);

            // 使用正则表达式移除对应的子模块配置块
            $pattern = '/\[submodule\s+"' . preg_quote($relativePath, '/') . '"\][^\[]*/s';
            $content = preg_replace($pattern, '', $content);

            // 清理多余的空白行
            $content = preg_replace('/\n{3,}/', "\n\n", $content);
            $content = trim($content) . "\n";

            File::put($gitmodulesPath, $content);
        }

        // 从 Git 索引中移除（如果还在的话）
        $gitPath         = $this->findGitCommand();
        $rmCacheCommand  = sprintf(
            '%s rm --cached -r -- %s 2>&1',
            escapeshellarg($gitPath),
            escapeshellarg($relativePath)
        );

        exec($rmCacheCommand, $output, $returnVar);
        // 忽略返回值，因为可能已经不存在了
    }

    /**
     * 查找 git 命令路径
     * @return string
     * @throws Exception
     */
    private function findGitCommand(): string
    {
        $commands = ['git', '/usr/bin/git', '/usr/local/bin/git'];
        $gitPath  = null;

        foreach ($commands as $cmd) {
            $output    = [];
            exec(escapeshellarg($cmd) . ' --version 2>&1', $output, $returnVar);
            if ($returnVar === 0) {
                $gitPath = $cmd;
                break;
            }
        }

        if ($gitPath === null) {
            throw_exception('未找到 git 命令，请确保系统已安装 git');
        }

        return $gitPath;
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

