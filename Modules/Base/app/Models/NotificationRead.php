<?php

namespace Modules\Base\Models;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Siushin\LaravelTool\Traits\ModelTool;
use Siushin\Util\Traits\ParamTool;

/**
 * 模型：通知查看记录
 */
class NotificationRead extends Model
{
    use ParamTool, ModelTool;

    protected $table = 'gpa_notification_reads';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'read_type',
        'target_id',
        'account_id',
        'read_at',
        'ip_address',
        'ip_location',
    ];

    /**
     * 获取通知查看记录列表
     * @param array $params 必须包含 read_type 和 target_id
     * @return array
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    public static function getPageData(array $params = []): array
    {
        // 必须提供 read_type 和 target_id
        if (empty($params['read_type']) || empty($params['target_id'])) {
            throw_exception('缺少 read_type 或 target_id 参数');
        }

        $query = self::query()
            ->where('read_type', $params['read_type'])
            ->where('target_id', $params['target_id'])
            ->with(['account' => function ($q) {
                $q->with('profile');
            }]);

        // 支持按账号ID搜索
        if (!empty($params['account_id'])) {
            $query->where('account_id', $params['account_id']);
        }

        // 支持按查看时间范围搜索
        if (!empty($params['read_at'])) {
            if (is_array($params['read_at']) && count($params['read_at']) === 2) {
                $startTime = $params['read_at'][0];
                $endTime = $params['read_at'][1];
                if (strlen($endTime) <= 10 || !str_contains($endTime, ' ')) {
                    $endTime = $endTime . ' 23:59:59';
                }
                $query->whereBetween('read_at', [$startTime, $endTime]);
            }
        }

        $data = self::fastGetPageData($query, $params, [
            'account_id' => '=',
            'ip_address' => 'like',
            'ip_location' => 'like',
            'date_range' => 'read_at',
            'time_range' => 'read_at',
        ]);

        // 确保关联的账号信息被正确返回
        foreach ($data['data'] as &$item) {
            if (isset($item['account'])) {
                $account = $item['account'];
                $item['account_username'] = $account['username'] ?? '';
                $item['account_nickname'] = $account['profile']['nickname'] ?? '';
            } else {
                $item['account_username'] = '';
                $item['account_nickname'] = '';
            }
        }

        return $data;
    }

    /**
     * 记录查看记录
     * @param string $readType 查看类型（system_notification, message, announcement）
     * @param int $targetId 目标ID
     * @param int|null $accountId 账号ID（可为空，表示未登录用户）
     * @param string|null $ipAddress IP地址
     * @param string|null $ipLocation IP归属地
     * @return bool
     * @author siushin<siushin@163.com>
     */
    public static function recordRead(
        string $readType,
        int $targetId,
        ?int $accountId = null,
        ?string $ipAddress = null,
        ?string $ipLocation = null
    ): bool {
        try {
            // 如果提供了 account_id，检查是否已存在记录（避免重复记录）
            if ($accountId) {
                $exists = self::query()
                    ->where('read_type', $readType)
                    ->where('target_id', $targetId)
                    ->where('account_id', $accountId)
                    ->exists();
                
                if ($exists) {
                    // 如果已存在，更新查看时间和IP信息
                    return self::query()
                        ->where('read_type', $readType)
                        ->where('target_id', $targetId)
                        ->where('account_id', $accountId)
                        ->update([
                            'read_at' => now(),
                            'ip_address' => $ipAddress,
                            'ip_location' => $ipLocation,
                        ]);
                }
            }
            // 如果 account_id 为 null（未登录用户），每次都创建新记录
            // 因为无法区分不同的未登录用户，所以允许重复记录

            // 创建新记录
            return self::query()->insert([
                'read_type' => $readType,
                'target_id' => $targetId,
                'account_id' => $accountId,
                'read_at' => now(),
                'ip_address' => $ipAddress,
                'ip_location' => $ipLocation,
            ]);
        } catch (Exception $e) {
            \Log::error('记录查看记录失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 关联账号
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }
}

