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
 * 模型：公告
 */
class Announcement extends Model
{
    use ParamTool, ModelTool, SoftDeletes;

    protected $table = 'gpa_announcements';

    protected $fillable = [
        'id',
        'title',
        'content',
        'target_platform',
        'position',
        'start_time',
        'end_time',
        'status',
        'account_id',
    ];

    protected $hidden = [
        'deleted_at',
    ];

    const int STATUS_DISABLE = 0;   // 禁用
    const int STATUS_NORMAL  = 1;   // 正常

    /**
     * 获取公告列表（分页）
     * @param array $params
     * @return array
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    public static function getPageData(array $params = []): array
    {
        return self::fastGetPageData(self::query(), $params, [
            'title'           => 'like',
            'status'          => '=',
            'position'        => 'like',
            'target_platform' => 'like',
            'date_range'      => 'created_at',
            'time_range'      => 'created_at',
        ]);
    }

    /**
     * 新增公告
     * @param array $params
     * @return array
     * @throws ContainerExceptionInterface|NotFoundExceptionInterface
     * @author siushin<siushin@163.com>
     */
    public static function addAnnouncement(array $params): array
    {
        self::trimValueArray($params, [], [null]);
        self::checkEmptyParam($params, ['title', 'content']);

        $title = $params['title'];

        // 过滤允许的字段
        $allowed_fields = [
            'title', 'content', 'target_platform', 'position', 'start_time', 'end_time', 'status', 'account_id'
        ];
        $create_data = self::getArrayByKeys($params, $allowed_fields);

        // 设置默认值
        $create_data['status'] = $create_data['status'] ?? self::STATUS_NORMAL;
        $create_data['target_platform'] = $create_data['target_platform'] ?? 'all';
        $create_data['position'] = $create_data['position'] ?? 'home';
        $create_data['account_id'] = $create_data['account_id'] ?? currentUserId();

        $info = self::query()->create($create_data);
        !$info && throw_exception('新增公告失败');
        $info = $info->toArray();

        logGeneral(LogActionEnum::insert->name, "新增公告成功(title: $title)", $info);

        // 记录审计日志
        logAudit(
            request(),
            currentUserId(),
            '公告管理',
            OperationActionEnum::add->value,
            ResourceTypeEnum::other->value,
            $info['id'],
            null,
            $info,
            "新增公告: $title"
        );

        return ['id' => $info['id']];
    }

    /**
     * 更新公告
     * @param array $params
     * @return array
     * @throws ContainerExceptionInterface|NotFoundExceptionInterface
     * @author siushin<siushin@163.com>
     */
    public static function updateAnnouncement(array $params): array
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
            'content', 'target_platform', 'position', 'start_time', 'end_time', 'status'
        ];
        foreach ($allowed_fields as $field) {
            if (isset($params[$field])) {
                $update_data[$field] = $params[$field];
            }
        }

        $bool = $info->update($update_data);
        !$bool && throw_exception('更新公告失败');

        $log_extend_data = compareArray($update_data, $old_data);
        logGeneral(LogActionEnum::update->name, "更新公告(title: $title)", $log_extend_data);

        // 记录审计日志
        $new_data = $info->fresh()->toArray();
        logAudit(
            request(),
            currentUserId(),
            '公告管理',
            OperationActionEnum::update->value,
            ResourceTypeEnum::other->value,
            $id,
            $old_data,
            $new_data,
            "更新公告: $title"
        );

        return [];
    }

    /**
     * 删除公告
     * @param array $params
     * @return array
     * @throws ContainerExceptionInterface|NotFoundExceptionInterface
     * @author siushin<siushin@163.com>
     */
    public static function deleteAnnouncement(array $params): array
    {
        self::checkEmptyParam($params, ['id']);
        $id = $params['id'];

        $info = self::query()->find($id);
        !$info && throw_exception('数据不存在');

        $old_data = $info->toArray();
        $title = $old_data['title'];
        $bool = $info->delete();
        !$bool && throw_exception('删除失败');

        logGeneral(LogActionEnum::delete->name, "删除公告(ID: $id)", $old_data);

        // 记录审计日志
        logAudit(
            request(),
            currentUserId(),
            '公告管理',
            OperationActionEnum::delete->value,
            ResourceTypeEnum::other->value,
            $id,
            $old_data,
            null,
            "删除公告: $title"
        );

        return [];
    }
}

