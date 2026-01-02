<?php

namespace Modules\Base\Http\Controllers;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Base\Enums\OperationActionEnum;
use Modules\Base\Models\File;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Siushin\LaravelTool\Attributes\ControllerName;
use Siushin\LaravelTool\Attributes\OperationAction;

#[ControllerName('文件管理')]
class FileController extends Controller
{
    /**
     * 文件上传
     * @param Request $request
     * @return JsonResponse
     * @throws ContainerExceptionInterface|NotFoundExceptionInterface
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::upload)]
    public function upload(Request $request): JsonResponse
    {
        $file = $request->file('file');
        !isset($file) && throw_exception('请上传文件');
        return success(File::uploadFile($file));
    }

    /**
     * 删除文件
     * @param Request $request
     * @return JsonResponse
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::delete)]
    public function delete(Request $request): JsonResponse
    {
        $params = trimParam($request->all());
        return success(File::deleteFile($params), '删除文件成功');
    }

    /**
     * 清空文件（只能清空属于自己的）
     * @return JsonResponse
     * @throws ContainerExceptionInterface|NotFoundExceptionInterface
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::delete)]
    public function cleanup(): JsonResponse
    {
        File::cleanupFileByAccountId(currentUserId(), true);
        return success([], '清空文件成功');
    }
}
