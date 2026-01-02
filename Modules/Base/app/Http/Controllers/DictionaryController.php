<?php

namespace Modules\Base\Http\Controllers;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Base\Enums\OperationActionEnum;
use Modules\Base\Models\Dictionary;
use Modules\Base\Models\DictionaryCategory;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Siushin\LaravelTool\Attributes\ControllerName;
use Siushin\LaravelTool\Attributes\OperationAction;
use Siushin\Util\Traits\ParamTool;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

#[ControllerName('数据字典')]
class DictionaryController extends Controller
{
    use ParamTool;

    /**
     * 数据字典列表（全部）
     * @param Request $request
     * @param array   $fields
     * @return JsonResponse
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::list)]
    public function list(Request $request, array $fields = []): JsonResponse
    {
        $params = trimParam($request->all());
        return success(Dictionary::getAllData($params, $fields));
    }

    /**
     * 数据字典列表
     * @param Request $request
     * @return JsonResponse
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::index)]
    public function index(Request $request): JsonResponse
    {
        $params = trimParam($request->all());
        return success(Dictionary::getPageData($params));
    }

    /**
     * 添加数据字典
     * @param Request $request
     * @return JsonResponse
     * @throws ContainerExceptionInterface|NotFoundExceptionInterface
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::add)]
    public function add(Request $request): JsonResponse
    {
        $params = trimParam($request->all());
        return success(Dictionary::addDictionary($params));
    }

    /**
     * 更新数据字典
     * @param Request $request
     * @return JsonResponse
     * @throws ContainerExceptionInterface|NotFoundExceptionInterface
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::update)]
    public function update(Request $request): JsonResponse
    {
        $params = trimParam($request->all());
        return success(Dictionary::updateDictionary($params));
    }

    /**
     * 删除数据字典
     * @param Request $request
     * @return JsonResponse
     * @throws ContainerExceptionInterface|NotFoundExceptionInterface
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::delete)]
    public function delete(Request $request): JsonResponse
    {
        $params = trimParam($request->all());
        return success(Dictionary::deleteDictionary($params));
    }

    /**
     * 批量删除数据字典
     * @param Request $request
     * @return JsonResponse
     * @throws ContainerExceptionInterface|NotFoundExceptionInterface
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::batchDelete)]
    public function batchDelete(Request $request): JsonResponse
    {
        $params = trimParam($request->all());
        return success(Dictionary::batchDeleteDictionary($params));
    }

    /**
     * 下载数据字典模板
     * @param Request $request
     * @return BinaryFileResponse
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::download)]
    public function getTplFile(Request $request): BinaryFileResponse
    {
        $params = trimParam($request->all());
        [$category_name, $tpl_path] = DictionaryCategory::getDictionaryTempFilePath($params);
        return response()->download($tpl_path, "{$category_name}_模板文件.xlsx");
    }

    /**
     * 根据字典类型返回所有父级列表数据
     * @param Request $request
     * @return JsonResponse
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::paramData)]
    public function getPidData(Request $request): JsonResponse
    {
        $params = trimParam($request->all());
        $category_id = DictionaryCategory::checkCodeValidate($params);
        $parent_ids = Dictionary::query()->where(compact('category_id'))->distinct()->pluck('parent_id');
        $list = Dictionary::query()->whereIn('category_id', $parent_ids)->pluck('dictionary_name', 'dictionary_id')->toArray();
        return success($list);
    }
}
