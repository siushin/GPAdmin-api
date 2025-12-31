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
 * 模型：系统通知
 */
class SystemNotification extends Model
{
    use ParamTool, ModelTool, SoftDeletes;

    protected $table = 'gpa_system_notifications';

    protected $fillable = [
        'id',
        'title',
        'content',
        'target_platform',
        'type',
        'status',
        'account_id',
    ];

    protected $hidden = [
        'deleted_at',
    ];

    const int STATUS_DISABLE = 0;   // 禁用
    const int STATUS_NORMAL  = 1;   // 正常

    /**
     * 获取系统通知列表（分页）
     * @param array $params
     * @return array
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    public static function getPageData(array $params = []): array
    {
        return self::fastGetPageData(self::query(), $params, [
            'title'           => 'like',
            'type'            => '=',
            'status'          => '=',
            'target_platform' => 'like',
            'date_range'      => 'created_at',
            'time_range'      => 'created_at',
        ]);
    }

    /**
     * 新增系统通知
     * @param array $params
     * @return array
     * @throws ContainerExceptionInterface|NotFoundExceptionInterface
     * @author siushin<siushin@163.com>
     */
    public static function addSystemNotification(array $params): array
    {
        self::trimValueArray($params, [], [null]);
        self::checkEmptyParam($params, ['title', 'content']);

        $title = $params['title'];

        // 过滤允许的字段
        $allowed_fields = [
            'title', 'content', 'target_platform', 'type', 'status', 'account_id'
        ];
        $create_data = self::getArrayByKeys($params, $allowed_fields);

        // 设置默认值
        $create_data['status'] = $create_data['status'] ?? self::STATUS_NORMAL;
        $create_data['target_platform'] = $create_data['target_platform'] ?? 'all';
        $create_data['account_id'] = $create_data['account_id'] ?? currentUserId();

        $info = self::query()->create($create_data);
        !$info && throw_exception('新增系统通知失败');
        $info = $info->toArray();

        logGeneral(LogActionEnum::insert->name, "新增系统通知成功(title: $title)", $info);

        // 记录审计日志
        logAudit(
            request(),
            currentUserId(),
            '系统通知管理',
            OperationActionEnum::add->value,
            ResourceTypeEnum::other->value,
            $info['id'],
            null,
            $info,
            "新增系统通知: $title"
        );

        return ['id' => $info['id']];
    }

    /**
     * 更新系统通知
     * @param array $params
     * @return array
     * @throws ContainerExceptionInterface|NotFoundExceptionInterface
     * @author siushin<siushin@163.com>
     */
    public static function updateSystemNotification(array $params): array
    {
        self::trimValueArray($params, [], [null]);
        self::checkEmptyParam($params, ['id', 'title']);

        $id = $params['id'];
        $title = $params['title'];

        $info = self::query()->find($id);
        !$info && throw_exception('找不到该数据，请刷新后重试');
        $old_data = $info->toArray();

        // 构建更新数据
        $update_data = ['title' => $title];

        // 支持更新其他字段
        $allowed_fields = [
            'content', 'target_platform', 'type', 'status'
        ];
        foreach ($allowed_fields as $field) {
            if (isset($params[$field])) {
                $update_data[$field] = $params[$field];
            }
        }

        $bool = $info->update($update_data);
        !$bool && throw_exception('更新系统通知失败');

        $log_extend_data = compareArray($update_data, $old_data);
        logGeneral(LogActionEnum::update->name, "更新系统通知(title: $title)", $log_extend_data);

        // 记录审计日志
        $new_data = $info->fresh()->toArray();
        logAudit(
            request(),
            currentUserId(),
            '系统通知管理',
            OperationActionEnum::update->value,
            ResourceTypeEnum::other->value,
            $id,
            $old_data,
            $new_data,
            "更新系统通知: $title"
        );

        return [];
    }

    /**
     * 删除系统通知
     * @param array $params
     * @return array
     * @throws ContainerExceptionInterface|NotFoundExceptionInterface
     * @author siushin<siushin@163.com>
     */
    public static function deleteSystemNotification(array $params): array
    {
        self::checkEmptyParam($params, ['id']);
        $id = $params['id'];

        $info = self::query()->find($id);
        !$info && throw_exception('数据不存在');

        $old_data = $info->toArray();
        $title = $old_data['title'];
        $bool = $info->delete();
        !$bool && throw_exception('删除失败');

        logGeneral(LogActionEnum::delete->name, "删除系统通知(ID: $id)", $old_data);

        // 记录审计日志
        logAudit(
            request(),
            currentUserId(),
            '系统通知管理',
            OperationActionEnum::delete->value,
            ResourceTypeEnum::other->value,
            $id,
            $old_data,
            null,
            "删除系统通知: $title"
        );

        return [];
    }
}

