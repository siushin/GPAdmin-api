<?php

namespace Modules\Base\Models;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Base\Enums\LogActionEnum;
use Modules\Base\Enums\OperationActionEnum;
use Modules\Base\Enums\ResourceTypeEnum;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Siushin\LaravelTool\Traits\ModelTool;
use Siushin\LaravelTool\Utils\Tree;
use Siushin\Util\Traits\ParamTool;

/**
 * 模型：部门
 */
class Department extends Model
{
    use ParamTool, ModelTool, SoftDeletes;

    protected $table      = 'gpa_department';
    protected $primaryKey = 'department_id';

    protected $fillable = [
        'department_id',
        'company_id',
        'department_code',
        'department_name',
        'manager_id',
        'description',
        'parent_id',
        'full_parent_id',
        'status',
        'sort_order',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    const int STATUS_DISABLE = 0;   // 禁用
    const int STATUS_NORMAL  = 1;   // 正常

    /**
     * 获取部门列表（全部）
     * @param array $params 支持：department_code、department_name
     * @param array $fields
     * @return array
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    public static function getAllData(array $params = [], array $fields = []): array
    {
        $params['status'] = self::STATUS_NORMAL;
        return self::fastGetAllData(self::class, $params, [
            'company_id'      => '=',
            'department_code' => '=',
            'department_name' => 'like',
            'status'          => '=',
        ], $fields);
    }

    /**
     * 获取部门列表（树形结构）
     * @param array $params
     * @return array
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    public static function getPageData(array $params = []): array
    {
        $where = self::buildWhereData($params, [
            'company_id'      => '=',
            'department_code' => 'like',
            'department_name' => 'like',
            'status'          => '=',
        ]);

        $departments = self::query()
            ->where($where)
            ->orderBy('sort_order', 'asc')
            ->orderBy('department_id', 'asc')
            ->get()
            ->toArray();

        return (new Tree('department_id', 'parent_id'))->getTree($departments);
    }

    /**
     * 新增部门
     * @param array $params
     * @return array
     * @throws ContainerExceptionInterface|NotFoundExceptionInterface
     * @author siushin<siushin@163.com>
     */
    public static function addDepartment(array $params): array
    {
        self::trimValueArray($params, [], [null]);
        self::checkEmptyParam($params, ['department_name']);

        $department_name = $params['department_name'];
        $company_id = $params['company_id'] ?? null;
        $parent_id = $params['parent_id'] ?? 0;
        $department_code = $params['department_code'] ?? null;

        // 检查父部门
        if ($parent_id > 0) {
            $parentDept = self::query()->find($parent_id);
            !$parentDept && throw_exception('父部门不存在');
            // 如果提供了 company_id，验证父部门的 company_id 是否一致
            if ($company_id !== null && $parentDept->company_id != $company_id) {
                throw_exception('父部门的公司ID与当前部门不一致');
            }
            $company_id = $company_id ?? $parentDept->company_id;
        }

        // 检查唯一性约束：同一公司、同一父级下部门名称必须唯一
        $exist = self::query()
            ->where('company_id', $company_id)
            ->where('parent_id', $parent_id)
            ->where('department_name', $department_name)
            ->exists();
        $exist && throw_exception('该层级下部门名称已存在');

        // 如果提供了 department_code，检查唯一性
        if (!empty($department_code) && $company_id !== null) {
            $exist = self::query()
                ->where('company_id', $company_id)
                ->where('department_code', $department_code)
                ->exists();
            $exist && throw_exception('该公司下部门编码已存在');
        }

        // 过滤允许的字段
        $allowed_fields = [
            'company_id', 'department_code', 'department_name', 'manager_id',
            'description', 'parent_id', 'status', 'sort_order'
        ];
        $create_data = self::getArrayByKeys($params, $allowed_fields);

        // 设置默认值
        $create_data['status'] = $create_data['status'] ?? self::STATUS_NORMAL;
        $create_data['sort_order'] = $create_data['sort_order'] ?? 0;
        $create_data['parent_id'] = $create_data['parent_id'] ?? 0;

        $info = self::query()->create($create_data);
        !$info && throw_exception('新增部门失败');
        $info = $info->toArray();

        logGeneral(LogActionEnum::insert->name, "新增部门成功(department_name: $department_name)", $info);

        // 记录审计日志
        logAudit(
            request(),
            currentUserId(),
            '部门管理',
            OperationActionEnum::add->value,
            ResourceTypeEnum::other->value,
            $info['department_id'],
            null,
            $info,
            "新增部门: $department_name"
        );

        return ['department_id' => $info['department_id']];
    }

    /**
     * 更新部门
     * @param array $params
     * @return array
     * @throws ContainerExceptionInterface|NotFoundExceptionInterface
     * @author siushin<siushin@163.com>
     */
    public static function updateDepartment(array $params): array
    {
        self::trimValueArray($params, [], [null]);
        self::checkEmptyParam($params, ['department_id', 'department_name']);

        $department_id = $params['department_id'];
        $department_name = $params['department_name'];
        $parent_id = $params['parent_id'] ?? 0;

        $info = self::query()->find($department_id);
        !$info && throw_exception('找不到该数据，请刷新后重试');
        $old_data = $info->toArray();

        $company_id = $info->company_id;

        // 检查父部门
        if ($parent_id > 0) {
            $parentDept = self::query()->find($parent_id);
            !$parentDept && throw_exception('父部门不存在');
            // 验证父部门的 company_id 是否一致
            if ($parentDept->company_id != $company_id) {
                throw_exception('父部门的公司ID与当前部门不一致');
            }
            // 检查是否形成循环引用（父部门不能是自己的子部门）
            $childIds = self::getAllChildIds($department_id);
            if (in_array($parent_id, $childIds)) {
                throw_exception('不能将子部门设置为父部门，会导致循环引用');
            }
        }

        // 检查唯一性约束：同一公司、同一父级下部门名称必须唯一，排除当前记录
        $check_parent_id = $parent_id > 0 ? $parent_id : ($info->parent_id ?? 0);
        $exist = self::query()
            ->where('company_id', $company_id)
            ->where('parent_id', $check_parent_id)
            ->where('department_name', $department_name)
            ->where('department_id', '<>', $department_id)
            ->exists();
        $exist && throw_exception('该层级下部门名称已存在，更新失败');

        // 如果提供了 department_code，检查唯一性，排除当前记录
        if (isset($params['department_code']) && !empty($params['department_code'])) {
            $exist = self::query()
                ->where('company_id', $company_id)
                ->where('department_code', $params['department_code'])
                ->where('department_id', '<>', $department_id)
                ->exists();
            $exist && throw_exception('该公司下部门编码已存在，更新失败');
        }

        // 构建更新数据
        $update_data = ['department_name' => $department_name];

        // 支持更新其他字段
        $allowed_fields = [
            'company_id', 'department_code', 'manager_id',
            'description', 'parent_id', 'status', 'sort_order'
        ];
        foreach ($allowed_fields as $field) {
            if (isset($params[$field])) {
                $update_data[$field] = $params[$field];
            }
        }

        $bool = $info->update($update_data);
        !$bool && throw_exception('更新部门失败');

        $log_extend_data = compareArray($update_data, $old_data);
        logGeneral(LogActionEnum::update->name, "更新部门(department_name: $department_name)", $log_extend_data);

        // 记录审计日志
        $new_data = $info->fresh()->toArray();
        logAudit(
            request(),
            currentUserId(),
            '部门管理',
            OperationActionEnum::update->value,
            ResourceTypeEnum::other->value,
            $department_id,
            $old_data,
            $new_data,
            "更新部门: $department_name"
        );

        return [];
    }

    /**
     * 删除部门
     * @param array $params
     * @return array
     * @throws ContainerExceptionInterface|NotFoundExceptionInterface
     * @author siushin<siushin@163.com>
     */
    public static function deleteDepartment(array $params): array
    {
        self::checkEmptyParam($params, ['department_id']);
        $department_id = $params['department_id'];

        $info = self::query()->find($department_id);
        !$info && throw_exception('数据不存在');

        // 检查是否有子部门
        $hasChildren = self::query()->where('parent_id', $department_id)->exists();
        $hasChildren && throw_exception('该部门下存在子部门，无法删除');

        $old_data = $info->toArray();
        $department_name = $old_data['department_name'];
        $bool = $info->delete();
        !$bool && throw_exception('删除失败');

        logGeneral(LogActionEnum::delete->name, "删除部门(ID: $department_id)", $old_data);

        // 记录审计日志
        logAudit(
            request(),
            currentUserId(),
            '部门管理',
            OperationActionEnum::delete->value,
            ResourceTypeEnum::other->value,
            $department_id,
            $old_data,
            null,
            "删除部门: $department_name"
        );

        return [];
    }

    /**
     * 获取所有子部门ID（递归）
     * @param int $department_id
     * @return array
     * @author siushin<siushin@163.com>
     */
    private static function getAllChildIds(int $department_id): array
    {
        $childIds = [];
        $children = self::query()->where('parent_id', $department_id)->pluck('department_id')->toArray();
        foreach ($children as $childId) {
            $childIds[] = $childId;
            $childIds = array_merge($childIds, self::getAllChildIds($childId));
        }
        return $childIds;
    }
}
