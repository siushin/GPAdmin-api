<?php

namespace Modules\Base\Models;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
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
        'sort',
    ];

    protected $hidden = [
        'deleted_at',
    ];

    const int STATUS_DISABLE = 0;   // 禁用
    const int STATUS_NORMAL  = 1;   // 正常

    /**
     * 获取部门列表（树形结构）
     * @param array $params 支持：department_name、department_code
     * @return array
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    public static function getAllData(array $params = []): array
    {
        $where = self::buildWhereData($params, [
            'company_id'      => '=',
            'department_name' => 'like',
            'department_code' => 'like',
            'status'          => '=',
        ]);

        // 先查询匹配条件的部门，获取 full_parent_id 和 parent_id
        $searchDepartments = self::query()
            ->where($where)
            ->select('department_id', 'manager_id', 'parent_id', 'full_parent_id')
            ->get()
            ->toArray();

        // 如果没有搜索结果，直接返回空数组
        if (empty($searchDepartments)) {
            return [];
        }

        // 收集所有需要查询的部门ID（包括搜索结果本身的ID）
        $allDepartmentIds = array_column($searchDepartments, 'department_id');

        // 根据搜索结果，找出所有上级部门ID
        foreach ($searchDepartments as $dept) {
            // 优先使用 full_parent_id（如果存在且不为空）
            if (!empty($dept['full_parent_id'])) {
                // 解析 full_parent_id，获取所有父级部门ID
                $parentIds = explode(',', trim($dept['full_parent_id'], ','));
                $allDepartmentIds = array_merge($allDepartmentIds, array_filter($parentIds, function ($id) {
                    return !empty($id) && $id > 0;
                }));
            } else {
                // 如果 full_parent_id 为空，通过 parent_id 递归查找所有父级部门
                $parentId = $dept['parent_id'] ?? 0;
                while ($parentId > 0) {
                    $allDepartmentIds[] = $parentId;
                    $parentDept = self::query()->find($parentId);
                    if ($parentDept) {
                        $parentId = $parentDept->parent_id ?? 0;
                    } else {
                        break;
                    }
                }
            }
        }

        // 去重并过滤空值和0值
        $allDepartmentIds = array_unique(array_filter($allDepartmentIds, function ($id) {
            return !empty($id) && $id > 0;
        }));

        // 如果没有需要查询的部门ID，返回空数组
        if (empty($allDepartmentIds)) {
            return [];
        }

        // 查询所有需要的部门（包括搜索结果和所有父级部门）
        $departments = self::query()
            ->whereIn('department_id', $allDepartmentIds)
            ->orderBy('parent_id', 'asc')
            ->orderBy('sort', 'asc')
            ->orderBy('department_id', 'asc')
            ->get()
            ->toArray();

        // 关联上级部门信息
        $parentIds = array_values(array_unique(array_filter(array_column($departments, 'parent_id'))));
        $parentDepartments = [];
        if (!empty($parentIds)) {
            $parents = self::query()
                ->whereIn('department_id', $parentIds)
                ->select(['department_id', 'department_name'])
                ->get()
                ->keyBy('department_id')
                ->toArray();

            foreach ($parents as $parentId => $parent) {
                $parentDepartments[$parentId] = [
                    'parent_department_name' => $parent['department_name'] ?? '',
                ];
            }
        }

        // 关联管理员信息
        $managerIds = array_values(array_unique(array_filter(array_column($departments, 'manager_id'))));
        $managers = [];
        if (!empty($managerIds)) {
            $accounts = \Modules\Base\Models\Account::query()
                ->whereIn('id', $managerIds)
                ->with('profile')
                ->select(['id', 'username'])
                ->get()
                ->keyBy('id')
                ->toArray();

            foreach ($accounts as $accountId => $account) {
                $profile = $account['profile'] ?? null;
                $nickname = $profile['nickname'] ?? null;
                $username = $account['username'] ?? '';
                $name = $nickname ?: $username;
                $managers[$accountId] = [
                    'manager_name'     => $name,
                    'manager_username' => $username,
                ];
            }
        }

        // 将上级部门信息和管理员信息添加到部门数据中
        foreach ($departments as &$dept) {
            if (!empty($dept['parent_id']) && isset($parentDepartments[$dept['parent_id']])) {
                $dept = array_merge($dept, $parentDepartments[$dept['parent_id']]);
            }
            if (!empty($dept['manager_id']) && isset($managers[$dept['manager_id']])) {
                $dept = array_merge($dept, $managers[$dept['manager_id']]);
            }
        }

        $treeData = (new Tree('department_id', 'parent_id'))->getTree($departments);

        // 递归处理树形结构，确保子节点也包含上级部门信息和管理员信息
        $addInfoToTree = function (&$nodes) use (&$addInfoToTree, $parentDepartments, $managers) {
            foreach ($nodes as &$node) {
                if (!empty($node['parent_id']) && isset($parentDepartments[$node['parent_id']])) {
                    $node = array_merge($node, $parentDepartments[$node['parent_id']]);
                }
                if (!empty($node['manager_id']) && isset($managers[$node['manager_id']])) {
                    $node = array_merge($node, $managers[$node['manager_id']]);
                }
                if (!empty($node['children'])) {
                    $addInfoToTree($node['children']);
                }
            }
        };
        $addInfoToTree($treeData);

        return $treeData;
    }

    /**
     * 新增部门
     * @param array $params
     * @return array
     * @throws ContainerExceptionInterface|NotFoundExceptionInterface|Exception
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
            'company_id', 'department_name', 'department_code', 'manager_id',
            'description', 'parent_id', 'status', 'sort'
        ];
        $create_data = self::getArrayByKeys($params, $allowed_fields);

        // 生成部门ID
        $create_data['department_id'] = generateId();

        // 设置默认值并处理空字符串
        $create_data['status'] = $create_data['status'] ?? self::STATUS_NORMAL;
        $create_data['sort'] = (isset($create_data['sort']) && $create_data['sort'] !== '' && $create_data['sort'] !== null) ? intval($create_data['sort']) : 0;
        $create_data['parent_id'] = (isset($create_data['parent_id']) && $create_data['parent_id'] !== '' && $create_data['parent_id'] !== null) ? intval($create_data['parent_id']) : 0;
        // manager_id 是 string 类型，可以为 null，空字符串转换为 null
        if (isset($create_data['manager_id']) && $create_data['manager_id'] === '') {
            $create_data['manager_id'] = null;
        }

        // 创建部门（full_parent_id 先设置为空，创建后再更新）
        $create_data['full_parent_id'] = '';
        $info = self::query()->create($create_data);
        !$info && throw_exception('新增部门失败');

        // 更新完整父级部门ids
        // 顶级部门：,department_id,
        // 非顶级部门：parent_full_parent_id + department_id + ,
        $parent_full_parent_id = '';
        if ($parent_id > 0) {
            $parentDept = self::query()->find($parent_id);
            if ($parentDept && $parentDept->full_parent_id) {
                $parent_full_parent_id = $parentDept->full_parent_id;
            }
        }
        $info->full_parent_id = $parent_full_parent_id . $info->department_id . ',';
        $info->save();

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
     * @throws ContainerExceptionInterface|NotFoundExceptionInterface|Exception
     * @author siushin<siushin@163.com>
     */
    public static function updateDepartment(array $params): array
    {
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
        if (!empty($params['department_code'])) {
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
            'description', 'parent_id', 'status', 'sort'
        ];
        foreach ($allowed_fields as $field) {
            // 使用 array_key_exists 而不是 isset，确保值为 0、false、null 等也能正确更新
            if (array_key_exists($field, $params)) {
                $value = $params[$field];
                // 对于 status 字段，必须明确处理 0 值的情况
                if ($field === 'status') {
                    $update_data[$field] = intval($value);
                } elseif ($field === 'parent_id') {
                    // parent_id 字段：空字符串转换为 0（顶级部门）
                    $update_data[$field] = ($value === '' || $value === null) ? 0 : intval($value);
                } elseif ($field === 'manager_id') {
                    // manager_id 字段：空字符串转换为 null（表示没有负责人）
                    $update_data[$field] = ($value === '' || $value === null) ? null : intval($value);
                } elseif ($field === 'sort') {
                    // sort 字段：空字符串转换为 0
                    $update_data[$field] = ($value === '' || $value === null) ? 0 : intval($value);
                } else {
                    $update_data[$field] = $value;
                }
            }
        }

        // 检查 parent_id 是否改变
        $old_parent_id = $info->parent_id ?? 0;
        $new_parent_id = $update_data['parent_id'] ?? $old_parent_id;
        $pidChanged = $old_parent_id != $new_parent_id;

        // 如果 parent_id 改变了，需要更新 full_parent_id
        if ($pidChanged) {
            $old_full_parent_id = $info->full_parent_id ?? '';
            $new_full_parent_id = '';
            if ($new_parent_id > 0) {
                $parentDept = self::query()->find($new_parent_id);
                if ($parentDept && $parentDept->full_parent_id) {
                    $new_full_parent_id = $parentDept->full_parent_id;
                }
            }
            $new_full_parent_id = $new_full_parent_id . $department_id . ',';
            $update_data['full_parent_id'] = $new_full_parent_id;

            // 更新所有子、孙部门的 full_parent_id
            $allSubDepartmentIds = self::query()
                ->where('full_parent_id', 'like', "%$old_full_parent_id%")
                ->where('department_id', '!=', $department_id)
                ->pluck('department_id')
                ->toArray();

            if (!empty($allSubDepartmentIds)) {
                self::query()->whereIn('department_id', $allSubDepartmentIds)
                    ->update([
                        'full_parent_id' => DB::raw("REPLACE(`full_parent_id`, '$old_full_parent_id', '$new_full_parent_id')")
                    ]);
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
     * @throws ContainerExceptionInterface|NotFoundExceptionInterface|Exception
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
