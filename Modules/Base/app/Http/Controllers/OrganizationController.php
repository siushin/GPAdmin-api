<?php

namespace Modules\Base\Http\Controllers;

use Exception;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Modules\Base\Enums\OperationActionEnum;
use Modules\Base\Enums\ResourceTypeEnum;
use Modules\Base\Enums\SysParamFlagEnum;
use Modules\Base\Models\Dictionary;
use Modules\Base\Models\DictionaryCategory;
use Modules\Base\Models\Organization;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Siushin\LaravelTool\Attributes\ControllerName;
use Siushin\LaravelTool\Attributes\OperationAction;
use Siushin\LaravelTool\Traits\ModelTool;
use Siushin\Util\Traits\ParamTool;
use Siushin\Util\Utils\TreeHtmlFormatter;
use Throwable;

#[ControllerName('组织架构')]
class OrganizationController extends Controller
{
    use ParamTool, ModelTool;

    /**
     * 获取组织架构类型列表
     * @return JsonResponse
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::list)]
    public function getOrganizationTypeList(): JsonResponse
    {
        $data = Dictionary::getAllData(
            ['category_code' => 'OrganizationType', 'sortbys' => 'dictionary_id=asc'],
            ['dictionary_id', 'dictionary_name', 'dictionary_value', 'dictionary_desc', 'sys_param_flag']
        );
        return success($data);
    }

    /**
     * 添加组织架构类型
     * @return JsonResponse
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::add)]
    public function addOrganizationType(): JsonResponse
    {
        $params = trimParam(request()->all());
        $params['category_id'] = DictionaryCategory::checkCodeValidate(['category_code' => 'OrganizationType']);
        self::checkEmptyParam($params, ['dictionary_name', 'dictionary_value', 'dictionary_desc']);

        // 查重校验
        $where1 = self::buildWhereData($params, ['category_id' => '=', 'dictionary_name' => '=']);
        $where2 = self::buildWhereData($params, ['category_id' => '=', 'dictionary_name' => '=', 'dictionary_value' => '=']);
        $exist_check = Dictionary::where($where1)->orWhere(fn(Builder $query) => $query->where($where2))->exists();
        $exist_check && throw_exception('组织架构类型已存在');

        $params = array_merge(['dictionary_id' => generateId()], $params);
        $field = ['dictionary_id', 'category_id', 'dictionary_name', 'dictionary_value', 'dictionary_desc'];
        $data = user_get_fields_array($params, $field);
        Dictionary::upsert($data, ['category_id', 'dictionary_name', 'dictionary_value']);

        // 获取插入后的数据
        $info = Dictionary::query()->find($params['dictionary_id']);
        $new_data = $info->toArray();

        // 记录审计日志
        logAudit(
            request(),
            currentUserId(),
            '组织架构类型',
            OperationActionEnum::add->value,
            ResourceTypeEnum::other->value,
            $params['dictionary_id'],
            null,
            $new_data,
            "新增组织架构类型: {$params['dictionary_name']}"
        );

        return success(user_filter_array($data, ['category_id']), '新增成功');
    }

    /**
     * 更新组织架构类型
     * @return JsonResponse
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::update)]
    public function updateOrganizationType(): JsonResponse
    {
        $params = trimParam(request()->all());
        self::checkEmptyParam($params, ['dictionary_id', 'dictionary_name', 'dictionary_value', 'dictionary_desc']);

        $dictionary_id = $params['dictionary_id'];

        // 检查数据是否存在
        $info = Dictionary::query()->find($dictionary_id);
        !$info && throw_exception('找不到该数据，请刷新后重试');

        // 验证是否为组织架构类型
        $category_id = DictionaryCategory::checkCodeValidate(['category_code' => 'OrganizationType']);
        $info->category_id != $category_id && throw_exception('该数据不是组织架构类型');

        $params['category_id'] = $category_id;

        // 查重校验（排除自己）
        $where1 = self::buildWhereData($params, ['category_id' => '=', 'dictionary_name' => '=']);
        $where1[] = ['dictionary_id', '<>', $dictionary_id];
        $where2 = self::buildWhereData($params, ['category_id' => '=', 'dictionary_name' => '=', 'dictionary_value' => '=']);
        $where2[] = ['dictionary_id', '<>', $dictionary_id];
        $exist_check = Dictionary::where($where1)->orWhere(fn(Builder $query) => $query->where($where2))->exists();
        $exist_check && throw_exception('组织架构类型已存在');

        $old_data = $info->toArray();
        $field = ['dictionary_name', 'dictionary_value', 'dictionary_desc'];
        $update_data = user_get_fields_array($params, $field);

        $bool = $info->update($update_data);
        !$bool && throw_exception('更新失败');

        // 记录审计日志
        $new_data = $info->fresh()->toArray();
        logAudit(
            request(),
            currentUserId(),
            '组织架构类型',
            OperationActionEnum::update->value,
            ResourceTypeEnum::other->value,
            $dictionary_id,
            $old_data,
            $new_data,
            "更新组织架构类型: {$params['dictionary_name']}"
        );

        return success([], '更新成功');
    }

    /**
     * 删除组织架构类型
     * @return JsonResponse
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::delete)]
    public function deleteOrganizationType(): JsonResponse
    {
        $params = trimParam(request()->all());
        self::checkEmptyParam($params, ['dictionary_id']);

        $dictionary_id = $params['dictionary_id'];

        // 检查数据是否存在
        $info = Dictionary::query()->find($dictionary_id);
        !$info && throw_exception('数据不存在');

        // 验证是否为组织架构类型
        $category_id = DictionaryCategory::checkCodeValidate(['category_code' => 'OrganizationType']);
        $info->category_id != $category_id && throw_exception('该数据不是组织架构类型');

        // 检查是否禁止删除
        $info->sys_param_flag == SysParamFlagEnum::Yes->value && throw_exception('该组织架构类型禁止删除');

        // 检查是否有关联的组织架构数据
        $hasOrganization = Organization::query()
            ->where('organization_tid', $dictionary_id)
            ->exists();
        $hasOrganization && throw_exception('该组织架构类型下存在组织架构数据，无法删除');

        $old_data = $info->toArray();
        $bool = $info->delete();
        !$bool && throw_exception('删除失败');

        // 记录审计日志
        logAudit(
            request(),
            currentUserId(),
            '组织架构类型',
            OperationActionEnum::delete->value,
            ResourceTypeEnum::other->value,
            $dictionary_id,
            $old_data,
            null,
            "删除组织架构类型: {$old_data['dictionary_name']}"
        );

        return success([], '删除成功');
    }

    /**
     * 获取组织架构树状Html数据
     * tips：按层级使用占位符 ├─、└─ 缩进
     * @return JsonResponse
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::list)]
    public function getFullTreeDataForHtml(): JsonResponse
    {
        $params = trimParam(request()->all());

        // 获取树形数据
        $treeData = Organization::getTreeData($params);

        // 如果没有数据，返回空数组
        if (empty($treeData)) {
            return success();
        }

        // 使用 TreeHtmlFormatter 格式化数据
        $formatter = new TreeHtmlFormatter([
            'id_field'       => 'organization_id',
            'output_id'      => 'organization_pid',
            'title_field'    => 'organization_name',
            'children_field' => 'children',
            'output_title'   => 'organization_name',
            'fields'         => ['organization_id', 'organization_name'], // 只返回指定字段
        ]);

        $htmlData = $formatter->format($treeData);

        return success($htmlData);
    }

    /**
     * 获取组织架构（树状结构）
     * @return JsonResponse
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::index)]
    public function index(): JsonResponse
    {
        $params = trimParam(request()->all());
        return success(Organization::getTreeData($params));
    }

    /**
     * 添加组织架构
     * @return JsonResponse
     * @throws ContainerExceptionInterface|NotFoundExceptionInterface
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::add)]
    public function add(): JsonResponse
    {
        $params = trimParam(request()->all());
        return success(Organization::addOrganization($params));
    }

    /**
     * 更新组织架构
     * @return JsonResponse
     * @throws ContainerExceptionInterface|NotFoundExceptionInterface|Throwable
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::update)]
    public function update(): JsonResponse
    {
        $params = trimParam(request()->only(['organization_id', 'organization_name', 'organization_pid']));
        return success(Organization::updateOrganization($params));
    }

    /**
     * 删除组织架构
     * @return JsonResponse
     * @throws ContainerExceptionInterface|NotFoundExceptionInterface
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::delete)]
    public function delete(): JsonResponse
    {
        $params = trimParam(request()->only(['organization_id']));
        return success(Organization::deleteOrganization($params));
    }

    /**
     * 移动组织架构
     * @return JsonResponse
     * @throws Exception|Throwable
     * @author siushin<siushin@163.com>
     */
    #[OperationAction(OperationActionEnum::move)]
    public function move(): JsonResponse
    {
        $params = trimParam(request()->all());
        return success(Organization::moveOrganization($params));
    }
}
