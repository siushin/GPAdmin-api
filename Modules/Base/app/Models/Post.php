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
 * 模型：岗位
 */
class Post extends Model
{
    use ParamTool, ModelTool, SoftDeletes;

    protected $table = 'gpa_post';
    protected $primaryKey = 'post_id';

    protected $fillable = [
        'post_id',
        'post_name',
        'post_code',
        'position_id',
        'department_id',
        'post_description',
        'post_requirements',
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
     * 获取岗位列表（全部）
     * @param array $params
     * @return array
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    public static function getAllData(array $params = [], array $fields = []): array
    {
        $params['status'] = self::STATUS_NORMAL;
        return self::fastGetAllData(self::class, $params, [
            'position_id'  => '=',
            'department_id' => '=',
            'post_code'    => '=',
            'post_name'    => 'like',
            'status'       => '=',
        ], $fields);
    }

    /**
     * 获取岗位列表（分页）
     * @param array $params
     * @return array
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    public static function getPageData(array $params = []): array
    {
        return self::fastGetPageData(self::query(), $params, [
            'position_id'  => '=',
            'department_id' => '=',
            'post_code'    => 'like',
            'post_name'    => 'like',
            'status'       => '=',
            'date_range'   => 'created_at',
            'time_range'   => 'created_at',
        ]);
    }

    /**
     * 新增岗位
     * @param array $params
     * @return array
     * @throws ContainerExceptionInterface|NotFoundExceptionInterface
     * @author siushin<siushin@163.com>
     */
    public static function addPost(array $params): array
    {
        self::trimValueArray($params, [], [null]);
        self::checkEmptyParam($params, ['post_name']);

        $post_name = $params['post_name'];
        $position_id = $params['position_id'] ?? null;
        $department_id = $params['department_id'] ?? null;
        $post_code = $params['post_code'] ?? null;

        // 如果提供了 position_id，检查职位是否存在
        if ($position_id !== null) {
            $position = \Modules\Base\Models\Position::query()->find($position_id);
            !$position && throw_exception('职位不存在');
        }

        // 如果提供了 department_id，检查部门是否存在
        if ($department_id !== null) {
            $department = \Modules\Base\Models\Department::query()->find($department_id);
            !$department && throw_exception('部门不存在');
        }

        // 检查唯一性约束：同一职位下岗位名称必须唯一
        if ($position_id !== null) {
            $exist = self::query()
                ->where('position_id', $position_id)
                ->where('post_name', $post_name)
                ->exists();
            $exist && throw_exception('该职位下岗位名称已存在');
        }

        // 如果提供了 post_code，检查全局唯一性
        if (!empty($post_code)) {
            $exist = self::query()->where('post_code', $post_code)->exists();
            $exist && throw_exception('岗位编码已存在');
        }

        // 过滤允许的字段
        $allowed_fields = [
            'post_name', 'post_code', 'position_id', 'department_id',
            'post_description', 'post_requirements', 'status', 'sort_order'
        ];
        $create_data = self::getArrayByKeys($params, $allowed_fields);

        // 设置默认值
        $create_data['status'] = $create_data['status'] ?? self::STATUS_NORMAL;
        $create_data['sort_order'] = $create_data['sort_order'] ?? 0;

        $info = self::query()->create($create_data);
        !$info && throw_exception('新增岗位失败');
        $info = $info->toArray();

        logGeneral(LogActionEnum::insert->name, "新增岗位成功(post_name: $post_name)", $info);

        // 记录审计日志
        logAudit(
            request(),
            currentUserId(),
            '岗位管理',
            OperationActionEnum::add->value,
            ResourceTypeEnum::other->value,
            $info['post_id'],
            null,
            $info,
            "新增岗位: $post_name"
        );

        return ['post_id' => $info['post_id']];
    }

    /**
     * 更新岗位
     * @param array $params
     * @return array
     * @throws ContainerExceptionInterface|NotFoundExceptionInterface
     * @author siushin<siushin@163.com>
     */
    public static function updatePost(array $params): array
    {
        self::trimValueArray($params, [], [null]);
        self::checkEmptyParam($params, ['post_id', 'post_name']);

        $post_id = $params['post_id'];
        $post_name = $params['post_name'];
        $position_id = $params['position_id'] ?? null;
        $department_id = $params['department_id'] ?? null;

        $info = self::query()->find($post_id);
        !$info && throw_exception('找不到该数据，请刷新后重试');
        $old_data = $info->toArray();

        // 确定用于检查唯一性的 position_id
        $check_position_id = $position_id ?? $info->position_id;

        // 如果提供了 position_id，检查职位是否存在
        if ($position_id !== null) {
            $position = \Modules\Base\Models\Position::query()->find($position_id);
            !$position && throw_exception('职位不存在');
        }

        // 如果提供了 department_id，检查部门是否存在
        if ($department_id !== null) {
            $department = \Modules\Base\Models\Department::query()->find($department_id);
            !$department && throw_exception('部门不存在');
        }

        // 检查唯一性约束：同一职位下岗位名称必须唯一，排除当前记录
        if ($check_position_id !== null) {
            $exist = self::query()
                ->where('position_id', $check_position_id)
                ->where('post_name', $post_name)
                ->where('post_id', '<>', $post_id)
                ->exists();
            $exist && throw_exception('该职位下岗位名称已存在，更新失败');
        }

        // 如果提供了 post_code，检查全局唯一性，排除当前记录
        if (isset($params['post_code']) && !empty($params['post_code'])) {
            $exist = self::query()
                ->where('post_code', $params['post_code'])
                ->where('post_id', '<>', $post_id)
                ->exists();
            $exist && throw_exception('岗位编码已存在，更新失败');
        }

        // 构建更新数据
        $update_data = ['post_name' => $post_name];

        // 支持更新其他字段
        $allowed_fields = [
            'post_code', 'position_id', 'department_id',
            'post_description', 'post_requirements', 'status', 'sort_order'
        ];
        foreach ($allowed_fields as $field) {
            if (isset($params[$field])) {
                $update_data[$field] = $params[$field];
            }
        }

        $bool = $info->update($update_data);
        !$bool && throw_exception('更新岗位失败');

        $log_extend_data = compareArray($update_data, $old_data);
        logGeneral(LogActionEnum::update->name, "更新岗位(post_name: $post_name)", $log_extend_data);

        // 记录审计日志
        $new_data = $info->fresh()->toArray();
        logAudit(
            request(),
            currentUserId(),
            '岗位管理',
            OperationActionEnum::update->value,
            ResourceTypeEnum::other->value,
            $post_id,
            $old_data,
            $new_data,
            "更新岗位: $post_name"
        );

        return [];
    }

    /**
     * 删除岗位
     * @param array $params
     * @return array
     * @throws ContainerExceptionInterface|NotFoundExceptionInterface
     * @author siushin<siushin@163.com>
     */
    public static function deletePost(array $params): array
    {
        self::checkEmptyParam($params, ['post_id']);
        $post_id = $params['post_id'];

        $info = self::query()->find($post_id);
        !$info && throw_exception('数据不存在');

        $old_data = $info->toArray();
        $post_name = $old_data['post_name'];
        $bool = $info->delete();
        !$bool && throw_exception('删除失败');

        logGeneral(LogActionEnum::delete->name, "删除岗位(ID: $post_id)", $old_data);

        // 记录审计日志
        logAudit(
            request(),
            currentUserId(),
            '岗位管理',
            OperationActionEnum::delete->value,
            ResourceTypeEnum::other->value,
            $post_id,
            $old_data,
            null,
            "删除岗位: $post_name"
        );

        return [];
    }
}
