<?php

namespace Modules\Base\Logics;

use Exception;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Modules\Base\Enums\ModulePullTypeEnum;
use Modules\Admin\Models\AccountModule;
use Modules\Admin\Models\Module as ModuleModel;

/**
 * 应用逻辑类
 * 处理应用相关的业务逻辑
 */
class AppLogic
{
    /**
     * 获取模块排序列表
     * @return array
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    public static function getModulesSort(): array
    {
        // 获取当前登录用户ID
        $accountId = currentUserId();
        if (!$accountId) {
            throw_exception('未登录或登录已过期');
        }

        // 通过账号模块关联表查询该账号有权限的模块，按 sort 排序
        $modules = ModuleModel::query()
            ->join('gpa_account_module', 'gpa_modules.module_id', '=', 'gpa_account_module.module_id')
            ->where('gpa_account_module.account_id', $accountId)
            ->where('gpa_modules.module_status', 1)
            ->where('gpa_modules.module_is_installed', 1)
            ->select('gpa_modules.module_id', 'gpa_modules.module_name', 'gpa_modules.module_title', 'gpa_account_module.sort')
            ->orderBy('gpa_account_module.sort', 'asc')
            ->orderBy('gpa_modules.module_priority', 'desc')
            ->orderBy('gpa_modules.module_id', 'asc')
            ->get();

        $list = [];
        foreach ($modules as $module) {
            $list[] = [
                'module_id'    => $module->module_id,
                'module_name'  => $module->module_name,
                'module_title' => $module->module_title,
                'sort'         => $module->sort,
            ];
        }

        return $list;
    }

    /**
     * 更新模块排序
     * @param array $sortList 排序列表
     * @return void
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    public static function updateModulesSort(array $sortList): void
    {
        // 获取当前登录用户ID
        $accountId = currentUserId();
        if (!$accountId) {
            throw_exception('未登录或登录已过期');
        }

        if (empty($sortList) || !is_array($sortList)) {
            throw_exception('排序数据不能为空');
        }

        // 更新排序
        foreach ($sortList as $index => $item) {
            $moduleId = $item['module_id'] ?? null;
            if (empty($moduleId)) {
                continue;
            }

            AccountModule::where('account_id', $accountId)
                ->where('module_id', $moduleId)
                ->update(['sort' => $index + 1]);
        }
    }

    /**
     * 安装模块
     * @param int $moduleId 模块ID
     * @return array
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    public static function installModule(int $moduleId): array
    {
        // 获取当前登录用户ID
        $accountId = currentUserId();
        if (!$accountId) {
            throw_exception('未登录或登录已过期');
        }

        // 检查模块是否存在
        $module = ModuleModel::find($moduleId);
        if (!$module) {
            throw_exception('模块不存在');
        }

        // 检查是否已安装
        $exists = AccountModule::where('account_id', $accountId)
            ->where('module_id', $moduleId)
            ->exists();

        if ($exists) {
            throw_exception('该模块已安装');
        }

        // 拉取模块代码
        $modulePath = self::pullModuleCode($module);

        // TODO: 数据校验
        // 验证模块代码完整性、module.json 文件存在性等

        // 插入账号模块关联表
        AccountModule::create([
            'id'         => generateId(),
            'account_id' => $accountId,
            'module_id'  => $moduleId,
        ]);

        return [
            'module_id'   => $moduleId,
            'module_name' => $module->module_name,
            'module_path' => $modulePath,
        ];
    }

    /**
     * 拉取模块代码
     * @param ModuleModel $module 模块模型
     * @return string 模块路径
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    private static function pullModuleCode(ModuleModel $module): string
    {
        $modulesBasePath = base_path('Modules');
        $modulePath      = $modulesBasePath . DIRECTORY_SEPARATOR . $module->module_name;

        // 检查拉取类型和URL
        $pullType = $module->module_pull_type;
        $pullUrl  = $module->module_pull_url;

        if (empty($pullType) || empty($pullUrl)) {
            throw_exception('模块拉取类型或拉取URL不能为空');
        }

        if ($pullType === ModulePullTypeEnum::GIT->value) {
            // Git 子模块拉取
            self::pullModuleByGit($modulePath, $pullUrl, $module->module_name);
        } elseif ($pullType === ModulePullTypeEnum::URL->value) {
            // URL zip 下载
            self::pullModuleByUrl($modulePath, $pullUrl, $module->module_name);
        } else {
            throw_exception('不支持的模块拉取类型: ' . $pullType);
        }

        return $modulePath;
    }

    /**
     * 使用 Git 子模块拉取模块代码
     * @param string $modulePath 模块路径
     * @param string $gitUrl Git仓库URL
     * @param string $moduleName 模块名称
     * @return void
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    private static function pullModuleByGit(string $modulePath, string $gitUrl, string $moduleName): void
    {
        $modulesBasePath = base_path('Modules');
        $gitModulesPath  = $modulesBasePath . DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR . 'modules';
        $gitModulesFile  = base_path('.gitmodules');

        // 检查目录是否存在
        $dirExists     = File::isDirectory($modulePath);
        $isGitRepo     = $dirExists && File::isDirectory($modulePath . DIRECTORY_SEPARATOR . '.git');
        $hasModuleJson = $dirExists && File::exists($modulePath . DIRECTORY_SEPARATOR . 'module.json');

        // 判断目录状态：不存在、存在但残缺、存在且完整
        $needClean = false;
        if ($dirExists) {
            // 目录存在，检查是否残缺
            if (!$isGitRepo || !$hasModuleJson) {
                // 目录存在但残缺，需要清理
                $needClean = true;
            } else {
                // 目录存在且完整，尝试更新
                self::updateGitModule($modulePath);
                return;
            }
        }

        // 如果需要清理，先删除目录
        if ($needClean) {
            try {
                File::deleteDirectory($modulePath);
            } catch (Exception $e) {
                throw_exception('清理模块目录失败: ' . $e->getMessage());
            }
        }

        // 添加 Git 子模块
        $gitPath = self::findGitCommand();
        $command = sprintf(
            '%s submodule add --name %s -- %s %s',
            escapeshellarg($gitPath),
            escapeshellarg($moduleName),
            escapeshellarg($gitUrl),
            escapeshellarg($modulePath)
        );

        $output = [];
        $returnVar = 0;
        exec($command . ' 2>&1', $output, $returnVar);

        if ($returnVar !== 0) {
            $errorMsg = implode("\n", $output);
            throw_exception('Git 子模块添加失败: ' . $errorMsg);
        }

        // 初始化并更新子模块
        $initCommand = sprintf(
            '%s submodule update --init --recursive -- %s',
            escapeshellarg($gitPath),
            escapeshellarg($modulePath)
        );

        exec($initCommand . ' 2>&1', $output, $returnVar);

        if ($returnVar !== 0) {
            $errorMsg = implode("\n", $output);
            throw_exception('Git 子模块初始化失败: ' . $errorMsg);
        }

        // 验证模块目录和 module.json 是否存在
        if (!File::isDirectory($modulePath)) {
            throw_exception('模块目录创建失败');
        }

        if (!File::exists($modulePath . DIRECTORY_SEPARATOR . 'module.json')) {
            throw_exception('模块 module.json 文件不存在，模块可能不完整');
        }
    }

    /**
     * 更新已存在的 Git 模块
     * @param string $modulePath 模块路径
     * @return void
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    private static function updateGitModule(string $modulePath): void
    {
        $gitPath  = self::findGitCommand();
        $command  = sprintf(
            'cd %s && %s pull origin main 2>&1 || %s pull origin master 2>&1',
            escapeshellarg($modulePath),
            escapeshellarg($gitPath),
            escapeshellarg($gitPath)
        );

        $output   = [];
        $returnVar = 0;
        exec($command, $output, $returnVar);

        // 更新失败不影响，只记录日志
        if ($returnVar !== 0) {
            Log::warning('Git 模块更新失败', [
                'path'    => $modulePath,
                'output'  => implode("\n", $output),
            ]);
        }
    }

    /**
     * 使用 URL 下载 zip 文件并解压
     * @param string $modulePath 模块路径
     * @param string $zipUrl Zip文件URL
     * @param string $moduleName 模块名称
     * @return void
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    private static function pullModuleByUrl(string $modulePath, string $zipUrl, string $moduleName): void
    {
        $modulesBasePath = base_path('Modules');
        $tempDir         = storage_path('app' . DIRECTORY_SEPARATOR . 'temp');
        $tempZipFile     = $tempDir . DIRECTORY_SEPARATOR . $moduleName . '_' . time() . '.zip';

        // 确保临时目录存在
        if (!File::isDirectory($tempDir)) {
            File::makeDirectory($tempDir, 0755, true);
        }

        try {
            // 如果模块目录已存在，先删除
            if (File::isDirectory($modulePath)) {
                File::deleteDirectory($modulePath);
            }

            // 使用 curl 下载 zip 文件
            $ch = curl_init($zipUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 5分钟超时

            $zipContent = curl_exec($ch);
            $httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error      = curl_error($ch);
            curl_close($ch);

            if ($zipContent === false || $httpCode !== 200) {
                throw_exception('下载 zip 文件失败: ' . ($error ?: "HTTP {$httpCode}"));
            }

            // 保存 zip 文件到临时目录
            File::put($tempZipFile, $zipContent);

            // 解压 zip 文件
            $zip = new \ZipArchive();
            if ($zip->open($tempZipFile) !== true) {
                throw_exception('无法打开 zip 文件');
            }

            // 创建模块目录
            File::makeDirectory($modulePath, 0755, true);

            // 解压文件（解压到临时目录，然后移动）
            $extractPath = $tempDir . DIRECTORY_SEPARATOR . $moduleName . '_extract';
            if (File::isDirectory($extractPath)) {
                File::deleteDirectory($extractPath);
            }
            File::makeDirectory($extractPath, 0755, true);

            $zip->extractTo($extractPath);
            $zip->close();

            // 查找解压后的模块目录（可能有一层目录）
            $extractedDirs = File::directories($extractPath);
            if (count($extractedDirs) === 1) {
                // 如果只有一个目录，直接移动该目录的内容
                $sourceDir = $extractedDirs[0];
                File::copyDirectory($sourceDir, $modulePath);
            } else {
                // 如果多个目录或文件在根目录，直接移动所有内容
                $files = File::allFiles($extractPath);
                foreach ($files as $file) {
                    $relativePath = str_replace($extractPath . DIRECTORY_SEPARATOR, '', $file->getPathname());
                    $targetPath   = $modulePath . DIRECTORY_SEPARATOR . $relativePath;
                    $targetDir    = dirname($targetPath);
                    if (!File::isDirectory($targetDir)) {
                        File::makeDirectory($targetDir, 0755, true);
                    }
                    File::copy($file->getPathname(), $targetPath);
                }
            }

            // 清理临时文件
            File::delete($tempZipFile);
            File::deleteDirectory($extractPath);

            // 验证模块目录和 module.json 是否存在
            if (!File::isDirectory($modulePath)) {
                throw_exception('模块目录创建失败');
            }

            if (!File::exists($modulePath . DIRECTORY_SEPARATOR . 'module.json')) {
                throw_exception('模块 module.json 文件不存在，模块可能不完整');
            }
        } catch (Exception $e) {
            // 清理临时文件
            if (File::exists($tempZipFile)) {
                File::delete($tempZipFile);
            }

            throw $e;
        }
    }

    /**
     * 查找 git 命令路径
     * @return string
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    private static function findGitCommand(): string
    {
        // 尝试查找 git 命令
        $commands = ['git', '/usr/bin/git', '/usr/local/bin/git'];
        $gitPath  = null;

        foreach ($commands as $cmd) {
            $output = [];
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
}

