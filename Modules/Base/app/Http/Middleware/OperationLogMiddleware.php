<?php

namespace Modules\Base\Http\Middleware;

use Closure;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Log;
use Modules\Base\Enums\OperationActionEnum;
use ReflectionClass;
use Siushin\LaravelTool\Attributes\ControllerName;
use Siushin\LaravelTool\Attributes\OperationAction;
use Symfony\Component\HttpFoundation\Response;

/**
 * 中间件：操作日志记录
 * 自动记录每次请求的操作日志，包括模块名称、操作类型、执行耗时等信息
 */
class OperationLogMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param Closure(Request): (Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 记录开始时间（毫秒）
        $startTime = (int)(microtime(true) * 1000);

        // 获取路由信息
        $route = $request->route();

        // 获取模块名称和操作类型（从路由/控制器注释中解析）
        [$module, $action] = $this->extractModuleAndAction($request, $route);

        // 将开始时间和模块信息存储到请求的 attributes 中，与请求参数分离
        $request->attributes->set('_operation_log_start_time', $startTime);
        $request->attributes->set('_operation_log_module', $module);
        $request->attributes->set('_operation_log_action', $action);

        // 执行请求
        $response = $next($request);

        // 记录操作日志（响应后执行）
        $this->logOperation($request, $response, $startTime, $module, $action);

        return $response;
    }

    /**
     * 从路由和控制器中提取模块名称和操作类型
     * 优先级：路由注释 > 控制器方法注释 > 控制器类注释 > 默认值
     *
     * @param Request    $request
     * @param Route|null $route
     * @return array [module, action]
     */
    private function extractModuleAndAction(Request $request, ?Route $route): array
    {
        $module = '';
        $action = '';

        if ($route) {
            $actionName = $route->getActionName();

            // 尝试从控制器方法注释中提取
            if (is_string($actionName) && str_contains($actionName, '@')) {
                [$controllerClass, $methodName] = explode('@', $actionName);

                try {
                    if (class_exists($controllerClass)) {
                        $reflectionClass = new ReflectionClass($controllerClass);

                        // 优先从Attribute中提取模块名称（使用ControllerName注解）
                        $controllerNameAttributes = $reflectionClass->getAttributes(ControllerName::class);
                        if (!empty($controllerNameAttributes)) {
                            /** @var ControllerName $controllerNameAttribute */
                            $controllerNameAttribute = $controllerNameAttributes[0]->newInstance();
                            $module = $controllerNameAttribute->name;
                        } else {
                            // 如果没有Attribute，尝试从注释中提取（向后兼容）
                            $module = $this->extractModuleFromComment($reflectionClass->getDocComment() ?: '');
                        }

                        // 尝试从方法中提取操作类型和模块名称
                        if ($reflectionClass->hasMethod($methodName)) {
                            $reflectionMethod = $reflectionClass->getMethod($methodName);

                            // 优先从Attribute中提取操作类型
                            $attributes = $reflectionMethod->getAttributes(OperationAction::class);
                            if (!empty($attributes)) {
                                /** @var OperationAction $operationAction */
                                $operationAction = $attributes[0]->newInstance();
                                $action = $operationAction->action->value;
                            } else {
                                // 如果没有Attribute，尝试从方法注释中提取操作类型（向后兼容）
                                $methodComment = $reflectionMethod->getDocComment() ?: '';
                                $action = $this->extractActionFromComment($methodComment);

                                // 如果方法注释中也没有操作类型，尝试从方法名推断
                                if (!$action) {
                                    $action = $this->inferActionFromMethodName($methodName);
                                }
                            }

                            // 如果控制器类注释中没有模块名称，才从方法注释中提取
                            if (!$module) {
                                $methodComment = $reflectionMethod->getDocComment() ?: '';
                                $methodModule = $this->extractModuleFromComment($methodComment);
                                if ($methodModule) {
                                    $module = $methodModule;
                                }
                            }
                        }
                    }
                } catch (\ReflectionException $e) {
                    // 忽略反射异常，使用默认值
                    Log::debug('操作日志中间件：解析控制器注释失败: ' . $e->getMessage());
                }
            }

            // 如果还是没有操作类型，尝试从路由名称推断
            if (!$action && $route->getName()) {
                $action = $this->inferActionFromRouteName($route->getName());
            }
        }

        // 如果还是没有模块名称，尝试从请求路径推断
        if (!$module) {
            $module = $this->inferModuleFromPath($request->path());
        }

        // 如果还是没有操作类型，尝试从请求路径和方法推断
        if (!$action) {
            $action = $this->inferActionFromPath($request->path(), $request->method());
        }

        return [$module ?: '未知模块', $action ?: 'unknown'];
    }

    /**
     * 从注释中提取模块名称
     * 支持格式：
     * - @module 模块名称
     * - @module 模块名称 或 #module 模块名称
     *
     * @param string $comment
     * @return string
     */
    private function extractModuleFromComment(string $comment): string
    {
        if (preg_match('/@module\s+([^\s\n\r]+)/i', $comment, $matches)) {
            return trim($matches[1]);
        }
        return '';
    }

    /**
     * 从注释中提取操作类型
     * 支持格式：
     * - @action 操作类型
     * - @action 操作类型 或 #action 操作类型
     *
     * @param string $comment
     * @return string
     */
    private function extractActionFromComment(string $comment): string
    {
        if (preg_match('/@action\s+([^\s\n\r]+)/i', $comment, $matches)) {
            return trim($matches[1]);
        }
        return '';
    }

    /**
     * 从方法名推断操作类型
     *
     * @param string $methodName
     * @return string
     */
    private function inferActionFromMethodName(string $methodName): string
    {
        $methodName = strtolower($methodName);

        // 常见方法名映射（返回枚举值，即中文）
        $actionMap = [
            'index'       => OperationActionEnum::index->value,
            'create'      => OperationActionEnum::create->value,
            'add'         => OperationActionEnum::add->value,
            'store'       => OperationActionEnum::add->value,
            'update'      => OperationActionEnum::update->value,
            'edit'        => OperationActionEnum::edit->value,
            'delete'      => OperationActionEnum::delete->value,
            'destroy'     => OperationActionEnum::delete->value,
            'batchdelete' => OperationActionEnum::batchDelete->value,
            'export'      => OperationActionEnum::export->value,
            'import'      => OperationActionEnum::import->value,
            'upload'      => OperationActionEnum::upload->value,
            'download'    => OperationActionEnum::download->value,
            'move'        => OperationActionEnum::move->value,
            'copy'        => OperationActionEnum::copy->value,
            'view'        => OperationActionEnum::detail->value,
            'show'        => OperationActionEnum::detail->value,
            'search'      => OperationActionEnum::index->value,
            'login'       => OperationActionEnum::login->value,
            'loginbycode' => OperationActionEnum::login->value,
            'logout'      => OperationActionEnum::logout->value,
        ];

        return $actionMap[$methodName] ?? '';
    }

    /**
     * 从路由名称推断操作类型
     *
     * @param string $routeName
     * @return string
     */
    private function inferActionFromRouteName(string $routeName): string
    {
        $routeName = strtolower($routeName);

        // 路由名称通常是英文的，所以使用枚举名称（key）来匹配
        $routeActionMap = [
            'index'       => OperationActionEnum::index->value,
            'create'      => OperationActionEnum::create->value,
            'add'         => OperationActionEnum::add->value,
            'update'      => OperationActionEnum::update->value,
            'edit'        => OperationActionEnum::edit->value,
            'delete'      => OperationActionEnum::delete->value,
            'batchdelete' => OperationActionEnum::batchDelete->value,
            'export'      => OperationActionEnum::export->value,
            'import'      => OperationActionEnum::import->value,
            'upload'      => OperationActionEnum::upload->value,
            'download'    => OperationActionEnum::download->value,
            'move'        => OperationActionEnum::move->value,
            'copy'        => OperationActionEnum::copy->value,
            'view'        => OperationActionEnum::detail->value,
            'show'        => OperationActionEnum::detail->value,
            'search'      => OperationActionEnum::index->value,
            'login'       => OperationActionEnum::login->value,
            'logout'      => OperationActionEnum::logout->value,
        ];

        foreach ($routeActionMap as $key => $value) {
            if (str_contains($routeName, $key)) {
                return $value;
            }
        }

        return '';
    }

    /**
     * 从请求路径推断模块名称
     *
     * @param string $path
     * @return string
     */
    private function inferModuleFromPath(string $path): string
    {
        // 移除 api/ 前缀
        $path = preg_replace('/^api\//', '', $path);

        // 移除 admin/ 或 user/ 前缀
        $path = preg_replace('/^(admin|user)\//', '', $path);

        // 获取第一段作为模块名（如：organization/index -> organization）
        $parts = explode('/', $path);
        if (!empty($parts[0])) {
            // 转换为中文（简单映射，可以根据需要扩展）
            $moduleMap = [
                'organization' => '组织架构',
                'dictionary'   => '数据字典',
                'file'         => '文件管理',
                'log'          => '日志管理',
                'menu'         => '菜单管理',
                'account'      => '账号管理',
                'app'          => '应用管理',
            ];

            $moduleKey = strtolower($parts[0]);
            return $moduleMap[$moduleKey] ?? ucfirst($parts[0]);
        }

        return '';
    }

    /**
     * 从请求路径和方法推断操作类型
     *
     * @param string $path
     * @param string $method
     * @return string
     */
    private function inferActionFromPath(string $path, string $method): string
    {
        $path = strtolower($path);

        // 从路径中提取最后一段作为可能的操作类型（如：/organization/add -> add）
        $pathParts = explode('/', $path);
        $lastPart = end($pathParts);

        // 尝试从路径最后一段映射到操作类型
        $pathActionMap = [
            'index'    => OperationActionEnum::index->value,
            'create'   => OperationActionEnum::create->value,
            'add'      => OperationActionEnum::add->value,
            'update'   => OperationActionEnum::update->value,
            'edit'     => OperationActionEnum::edit->value,
            'delete'   => OperationActionEnum::delete->value,
            'export'   => OperationActionEnum::export->value,
            'import'   => OperationActionEnum::import->value,
            'upload'   => OperationActionEnum::upload->value,
            'download' => OperationActionEnum::download->value,
            'move'     => OperationActionEnum::move->value,
            'copy'     => OperationActionEnum::copy->value,
            'view'     => OperationActionEnum::detail->value,
            'show'     => OperationActionEnum::detail->value,
            'search'   => OperationActionEnum::index->value,
            'login'    => OperationActionEnum::login->value,
            'logout'   => OperationActionEnum::logout->value,
        ];

        if (isset($pathActionMap[$lastPart])) {
            return $pathActionMap[$lastPart];
        }

        // 根据HTTP方法推断
        $methodMap = [
            'GET'    => OperationActionEnum::detail->value,
            'POST'   => OperationActionEnum::add->value,
            'PUT'    => OperationActionEnum::update->value,
            'PATCH'  => OperationActionEnum::update->value,
            'DELETE' => OperationActionEnum::delete->value,
        ];

        return $methodMap[strtoupper($method)] ?? OperationActionEnum::detail->value;
    }

    /**
     * 记录操作日志
     *
     * @param Request  $request
     * @param Response $response
     * @param int      $startTime
     * @param string   $module
     * @param string   $action
     * @return void
     */
    private function logOperation(Request $request, Response $response, int $startTime, string $module, string $action): void
    {
        try {
            // 计算执行耗时（毫秒）
            $endTime = (int)(microtime(true) * 1000);
            $executionTime = $endTime - $startTime;

            // 获取响应状态码（先获取，用于判断是否登录成功）
            $responseCode = $response->getStatusCode();

            // 获取账号ID
            $accountId = null;
            if ($request->user()) {
                $accountId = $request->user()->id;
            } elseif (function_exists('currentUserId')) {
                $accountId = currentUserId();
            }

            // 对于登录接口，如果登录成功（状态码200），尝试从响应数据中获取账号ID
            if (!$accountId && $action === OperationActionEnum::login->value && $responseCode === 200) {
                try {
                    // 尝试从响应内容中解析账号ID
                    $responseContent = $response->getContent();
                    if ($responseContent) {
                        $responseData = json_decode($responseContent, true);
                        // 登录接口返回格式：{code: 200, data: {id: xxx, ...}, message: '登录成功'}
                        // data 就是 user_data，包含 id 字段
                        if (isset($responseData['data']['id'])) {
                            $accountId = $responseData['data']['id'];
                        }
                    }
                } catch (Exception $e) {
                    // 忽略解析错误，但记录日志以便调试
                    Log::debug('操作日志中间件：从响应中解析账号ID失败: ' . $e->getMessage());
                }
            }

            // 获取请求参数（排除敏感信息和大型数据）
            // 注意：_operation_log_* 参数已存储在 attributes 中，不会出现在请求参数中
            $params = trimParam($request->except([
                'password',
                'confirm_password',
                'current_password',
                'token',
                'access_token',
            ]));

            // 处理文件上传：只记录文件名，不记录文件内容
            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $params['file'] = [
                    'name'      => $file->getClientOriginalName(),
                    'size'      => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                ];
            }

            // 限制参数大小，避免记录过大的数据（限制为50KB）
            $paramsJson = json_encode($params, JSON_UNESCAPED_UNICODE);
            if (strlen($paramsJson) > 50000) {
                $params = ['_note' => '参数过大，已截断'];
            }

            // 记录操作日志
            logOperation(
                $request,
                $accountId,
                $module,
                $action,
                $request->method(),
                $request->path(),
                $params,
                $responseCode,
                $executionTime
            );
        } catch (Exception $e) {
            // 记录日志失败不影响主流程，静默处理
            Log::error('操作日志中间件：记录操作日志失败: ' . $e->getMessage());
        }
    }
}
