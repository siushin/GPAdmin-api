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
 * 模型：站内信
 */
class Message extends Model
{
    use ParamTool, ModelTool, SoftDeletes;

    protected $table = 'gpa_messages';

    protected $fillable = [
        'id',
        'sender_id',
        'receiver_id',
        'title',
        'content',
        'target_platform',
        'status',
        'account_id',
    ];

    protected $hidden = [
        'deleted_at',
    ];

    const int STATUS_UNREAD = 0;   // 未读
    const int STATUS_READ   = 1;   // 已读

    /**
     * 获取站内信列表
     * @param array $params
     * @return array
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    public static function getPageData(array $params = []): array
    {
        $query = self::query();

        // 处理目标平台多选查询（支持数组或逗号分隔的字符串）
        if (!empty($params['target_platform'])) {
            $platforms = is_array($params['target_platform'])
                ? $params['target_platform']
                : explode(',', $params['target_platform']);
            $platforms = array_filter(array_map('trim', $platforms));
            if (!empty($platforms)) {
                $query->where(function ($q) use ($platforms) {
                    foreach ($platforms as $platform) {
                        $q->orWhere('target_platform', 'like', "%{$platform}%");
                    }
                });
            }
            unset($params['target_platform']);
        }

        $data = self::fastGetPageData($query, $params, [
            'title'           => 'like',
            'status'          => '=',
            'sender_id'       => '=',
            'receiver_id'     => '=',
            'date_range'      => 'created_at',
            'time_range'      => 'created_at',
        ]);

        // 关联发送者和接收者的账号信息
        $accountIds = array_values(array_unique(array_filter(
            array_merge(
                array_column($data['data'], 'sender_id'),
                array_column($data['data'], 'receiver_id')
            )
        )));
        
        if (!empty($accountIds)) {
            $accounts = Account::query()
                ->whereIn('id', $accountIds)
                ->with('profile')
                ->select(['id', 'username'])
                ->get()
                ->keyBy('id')
                ->toArray();

            foreach ($data['data'] as &$item) {
                // 处理发送者信息
                if (!empty($item['sender_id']) && isset($accounts[$item['sender_id']])) {
                    $account = $accounts[$item['sender_id']];
                    $nickname = $account['profile']['nickname'] ?? '';
                    $username = $account['username'];
                    $item['sender_name'] = $nickname ? "{$nickname}({$username})" : $username;
                } else {
                    $item['sender_name'] = '';
                }

                // 处理接收者信息
                if (!empty($item['receiver_id']) && isset($accounts[$item['receiver_id']])) {
                    $account = $accounts[$item['receiver_id']];
                    $nickname = $account['profile']['nickname'] ?? '';
                    $username = $account['username'];
                    $item['receiver_name'] = $nickname ? "{$nickname}({$username})" : $username;
                } else {
                    $item['receiver_name'] = '';
                }
            }
        } else {
            foreach ($data['data'] as &$item) {
                $item['sender_name'] = '';
                $item['receiver_name'] = '';
            }
        }

        return $data;
    }

    /**
     * 新增站内信
     * @param array $params
     * @return array
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    public static function addMessage(array $params): array
    {
        self::checkEmptyParam($params, ['title', 'content', 'receiver_id']);

        $title = $params['title'];

        // 过滤允许的字段
        $allowed_fields = [
            'sender_id', 'receiver_id', 'title', 'content', 'target_platform', 'status', 'account_id'
        ];
        $create_data = self::getArrayByKeys($params, $allowed_fields);

        // 设置默认值
        $create_data['status'] = $create_data['status'] ?? self::STATUS_UNREAD;
        $create_data['target_platform'] = $create_data['target_platform'] ?? 'all';
        $create_data['sender_id'] = $create_data['sender_id'] ?? currentUserId();
        $create_data['account_id'] = $create_data['account_id'] ?? currentUserId();

        $info = self::query()->create($create_data);
        !$info && throw_exception('新增站内信失败');
        $info = $info->toArray();

        logGeneral(LogActionEnum::insert->name, "新增站内信成功(title: $title)", $info);

        // 记录审计日志
        logAudit(
            request(),
            currentUserId(),
            '站内信管理',
            OperationActionEnum::add->value,
            ResourceTypeEnum::other->value,
            $info['id'],
            null,
            $info,
            "新增站内信: $title"
        );

        return ['id' => $info['id']];
    }

    /**
     * 更新站内信
     * @param array $params
     * @return array
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    public static function updateMessage(array $params): array
    {
        self::checkEmptyParam($params, ['id', 'title']);

        $id = $params['id'];
        $title = $params['title'];

        $info = self::query()->find($id);
        !$info && throw_exception('找不到该数据，请刷新后重试');
        $old_data = $info->toArray();

        // 构建更新数据
        $update_data = ['title' => $title];

        // 支持更新其他字段（使用 array_key_exists 允许空字符串和 null 值更新）
        $allowed_fields = [
            'content', 'target_platform', 'status'
        ];
        foreach ($allowed_fields as $field) {
            if (array_key_exists($field, $params)) {
                $update_data[$field] = $params[$field];
            }
        }

        // 处理不能为 null 的字段，设置默认值
        if (array_key_exists('status', $update_data) && ($update_data['status'] === null || $update_data['status'] === '')) {
            $update_data['status'] = 0;
        }

        $bool = $info->update($update_data);
        !$bool && throw_exception('更新站内信失败');

        $log_extend_data = compareArray($update_data, $old_data);
        logGeneral(LogActionEnum::update->name, "更新站内信(title: $title)", $log_extend_data);

        // 记录审计日志
        $new_data = $info->fresh()->toArray();
        logAudit(
            request(),
            currentUserId(),
            '站内信管理',
            OperationActionEnum::update->value,
            ResourceTypeEnum::other->value,
            $id,
            $old_data,
            $new_data,
            "更新站内信: $title"
        );

        return [];
    }

    /**
     * 删除站内信
     * @param array $params
     * @return array
     * @throws ContainerExceptionInterface|NotFoundExceptionInterface
     * @author siushin<siushin@163.com>
     */
    public static function deleteMessage(array $params): array
    {
        self::checkEmptyParam($params, ['id']);
        $id = $params['id'];

        $info = self::query()->find($id);
        !$info && throw_exception('数据不存在');

        $old_data = $info->toArray();
        $title = $old_data['title'];
        $bool = $info->delete();
        !$bool && throw_exception('删除失败');

        logGeneral(LogActionEnum::delete->name, "删除站内信(ID: $id)", $old_data);

        // 记录审计日志
        logAudit(
            request(),
            currentUserId(),
            '站内信管理',
            OperationActionEnum::delete->value,
            ResourceTypeEnum::other->value,
            $id,
            $old_data,
            null,
            "删除站内信: $title"
        );

        return [];
    }
}

