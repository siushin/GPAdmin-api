<?php

namespace Modules\Base\Http\Controllers;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Base\Enums\OperationActionEnum;
use Modules\Base\Models\Department;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Siushin\LaravelTool\Attributes\ControllerName;
use Siushin\LaravelTool\Attributes\OperationAction;
use Siushin\Util\Utils\TreeHtmlFormatter;

#[ControllerName('部门管理')]
class DepartmentController extends Controller
{
    /**
     * 部门列表（全部）
     * Tips：树形结构
     * @param Request $request
     * @return JsonResponse
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::list)]
    public function list(Request $request): JsonResponse
    {
        $params = $request->all();
        return success(Department::getAllData($params));
    }

    /**
     * 添加部门
     * @return JsonResponse
     * @throws ContainerExceptionInterface|NotFoundExceptionInterface
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::add)]
    public function add(): JsonResponse
    {
        $params = request()->all();
        return success(Department::addDepartment($params));
    }

    /**
     * 更新部门
     * @return JsonResponse
     * @throws ContainerExceptionInterface|NotFoundExceptionInterface
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::update)]
    public function update(): JsonResponse
    {
        $params = request()->all();
        return success(Department::updateDepartment($params));
    }

    /**
     * 删除部门
     * @return JsonResponse
     * @throws ContainerExceptionInterface|NotFoundExceptionInterface
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::delete)]
    public function delete(): JsonResponse
    {
        $params = request()->all();
        return success(Department::deleteDepartment($params));
    }

    /**
     * 获取部门树状Html数据（根据公司ID）
     * tips：按层级使用占位符 ├─、└─ 缩进
     * @return JsonResponse
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::list)]
    public function getTreeDataForHtml(): JsonResponse
    {
        $params = request()->all();

        // 获取树形数据（根据 company_id 过滤）
        $treeData = Department::getAllData($params);

        // 如果没有数据，返回空数组
        if (empty($treeData)) {
            return success();
        }

        // 使用 TreeHtmlFormatter 格式化数据
        $formatter = new TreeHtmlFormatter([
            'id_field'       => 'department_id',
            'output_id'      => 'department_id',
            'title_field'    => 'department_name',
            'children_field' => 'children',
            'output_title'   => 'department_name',
            'fields'         => ['department_id', 'department_name'], // 只返回指定字段
        ]);

        $htmlData = $formatter->format($treeData);

        return success($htmlData);
    }
}
