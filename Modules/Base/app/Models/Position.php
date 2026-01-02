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
use Siushin\Util\Traits\ParamTool;

/**
 * 模型：职位
 */
class Position extends Model
{
    use ParamTool, ModelTool, SoftDeletes;

    protected $table = 'gpa_position';
    protected $primaryKey = 'position_id';

    protected $fillable = [
        'position_id',
        'position_name',
        'position_code',
        'department_id',
        'job_description',
        'job_requirements',
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
     * 获取职位列表（全部）
     * @param array $params
     * @return array
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    public static function getAllData(array $params = [], array $fields = []): array
    {
        $params['status'] = self::STATUS_NORMAL;
        return self::fastGetAllData(self::class, $params, [
            'department_id' => '=',
            'position_code' => '=',
            'position_name' => 'like',
            'status'        => '=',
        ], $fields);
    }

    /**
     * 获取职位列表
     * @param array $params
     * @return array
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    public static function getPageData(array $params = []): array
    {
        return self::fastGetPageData(self::query(), $params, [
            'department_id' => '=',
            'position_code' => 'like',
            'position_name' => 'like',
            'status'        => '=',
            'date_range'    => 'created_at',
            'time_range'    => 'created_at',
        ]);
    }

    /**
     * 新增职位
     * @param array $params
     * @return array
     * @throws ContainerExceptionInterface|NotFoundExceptionInterface
     * @author siushin<siushin@163.com>
     */
    public static function addPosition(array $params): array
    {
        self::trimValueArray($params, [], [null]);
        self::checkEmptyParam($params, ['position_name']);

        $position_name = $params['position_name'];
        $department_id = $params['department_id'] ?? null;
        $position_code = $params['position_code'] ?? null;

        // 如果提供了 department_id，检查部门是否存在
        if ($department_id !== null && $department_id > 0) {
            $department = \Modules\Base\Models\Department::query()->find($department_id);
            !$department && throw_exception('部门不存在');
        }

        // 检查唯一性约束：同一部门下职位名称必须唯一
        if ($department_id !== null) {
            $exist = self::query()
                ->where('department_id', $department_id)
                ->where('position_name', $position_name)
                ->exists();
            $exist && throw_exception('该部门下职位名称已存在');
        }

        // 如果提供了 position_code，检查全局唯一性
        if (!empty($position_code)) {
            $exist = self::query()->where('position_code', $position_code)->exists();
            $exist && throw_exception('职位编码已存在');
        }

        // 过滤允许的字段
        $allowed_fields = [
            'position_name', 'position_code', 'department_id',
            'job_description', 'job_requirements', 'status', 'sort_order'
        ];
        $create_data = self::getArrayByKeys($params, $allowed_fields);

        // 设置默认值
        $create_data['status'] = $create_data['status'] ?? self::STATUS_NORMAL;
        $create_data['sort_order'] = $create_data['sort_order'] ?? 0;

        $info = self::query()->create($create_data);
        !$info && throw_exception('新增职位失败');
        $info = $info->toArray();

        logGeneral(LogActionEnum::insert->name, "新增职位成功(position_name: $position_name)", $info);

        // 记录审计日志
        logAudit(
            request(),
            currentUserId(),
            '职位管理',
            OperationActionEnum::add->value,
            ResourceTypeEnum::other->value,
            $info['position_id'],
            null,
            $info,
            "新增职位: $position_name"
        );

        return ['position_id' => $info['position_id']];
    }

    /**
     * 更新职位
     * @param array $params
     * @return array
     * @throws ContainerExceptionInterface|NotFoundExceptionInterface
     * @author siushin<siushin@163.com>
     */
    public static function updatePosition(array $params): array
    {
        self::trimValueArray($params, [], [null]);
        self::checkEmptyParam($params, ['position_id', 'position_name']);

        $position_id = $params['position_id'];
        $position_name = $params['position_name'];
        $department_id = $params['department_id'] ?? null;

        $info = self::query()->find($position_id);
        !$info && throw_exception('找不到该数据，请刷新后重试');
        $old_data = $info->toArray();

        // 确定用于检查唯一性的 department_id
        $check_department_id = $department_id ?? $info->department_id;

        // 如果提供了 department_id，检查部门是否存在
        if ($department_id !== null && $department_id > 0) {
            $department = \Modules\Base\Models\Department::query()->find($department_id);
            !$department && throw_exception('部门不存在');
        }

        // 检查唯一性约束：同一部门下职位名称必须唯一，排除当前记录
        if ($check_department_id !== null) {
            $exist = self::query()
                ->where('department_id', $check_department_id)
                ->where('position_name', $position_name)
                ->where('position_id', '<>', $position_id)
                ->exists();
            $exist && throw_exception('该部门下职位名称已存在，更新失败');
        }

        // 如果提供了 position_code，检查全局唯一性，排除当前记录
        if (isset($params['position_code']) && !empty($params['position_code'])) {
            $exist = self::query()
                ->where('position_code', $params['position_code'])
                ->where('position_id', '<>', $position_id)
                ->exists();
            $exist && throw_exception('职位编码已存在，更新失败');
        }

        // 构建更新数据
        $update_data = ['position_name' => $position_name];

        // 支持更新其他字段
        $allowed_fields = [
            'position_code', 'department_id',
            'job_description', 'job_requirements', 'status', 'sort_order'
        ];
        foreach ($allowed_fields as $field) {
            if (isset($params[$field])) {
                $update_data[$field] = $params[$field];
            }
        }

        $bool = $info->update($update_data);
        !$bool && throw_exception('更新职位失败');

        $log_extend_data = compareArray($update_data, $old_data);
        logGeneral(LogActionEnum::update->name, "更新职位(position_name: $position_name)", $log_extend_data);

        // 记录审计日志
        $new_data = $info->fresh()->toArray();
        logAudit(
            request(),
            currentUserId(),
            '职位管理',
            OperationActionEnum::update->value,
            ResourceTypeEnum::other->value,
            $position_id,
            $old_data,
            $new_data,
            "更新职位: $position_name"
        );

        return [];
    }

    /**
     * 删除职位
     * @param array $params
     * @return array
     * @throws ContainerExceptionInterface|NotFoundExceptionInterface
     * @author siushin<siushin@163.com>
     */
    public static function deletePosition(array $params): array
    {
        self::checkEmptyParam($params, ['position_id']);
        $position_id = $params['position_id'];

        $info = self::query()->find($position_id);
        !$info && throw_exception('数据不存在');

        // 检查是否有岗位关联
        $hasPosts = \Modules\Base\Models\Post::query()->where('position_id', $position_id)->exists();
        $hasPosts && throw_exception('该职位下存在岗位，无法删除');

        $old_data = $info->toArray();
        $position_name = $old_data['position_name'];
        $bool = $info->delete();
        !$bool && throw_exception('删除失败');

        logGeneral(LogActionEnum::delete->name, "删除职位(ID: $position_id)", $old_data);

        // 记录审计日志
        logAudit(
            request(),
            currentUserId(),
            '职位管理',
            OperationActionEnum::delete->value,
            ResourceTypeEnum::other->value,
            $position_id,
            $old_data,
            null,
            "删除职位: $position_name"
        );

        return [];
    }
}
