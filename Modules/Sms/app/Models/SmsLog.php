<?php

namespace Modules\Sms\Models;

use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Sms\Enums\SmsTypeEnum;
use Siushin\LaravelTool\Cases\Json;
use Siushin\LaravelTool\Enums\RequestSourceEnum;
use Siushin\LaravelTool\Traits\ModelTool;
use Siushin\Util\Traits\ParamTool;

/**
 * 模型：短信发送记录
 */
class SmsLog extends Model
{
    use HasFactory, ParamTool, ModelTool;

    protected $table = 'sms_logs';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'sms_type'       => SmsTypeEnum::class,
            'source_type'    => RequestSourceEnum::class,
            'extend_data'    => Json::class,
            'status'         => 'integer',
            'expire_minutes' => 'integer',
        ];
    }

    /**
     * 获取短信发送记录列表
     * @param array $params
     * @return array
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    public static function getPageData(array $params = []): array
    {
        return self::fastGetPageData(self::query(), $params, [
            'account_id'   => '=',
            'source_type'  => '=',
            'sms_type'     => '=',
            'phone'        => 'like',
            'status'       => '=',
            'ip_address'   => 'like',
            'ip_location'  => 'like',
            'date_range'   => 'created_at',
            'keyword'      => ['phone', 'ip_address', 'ip_location', 'error_message'],
        ]);
    }
}

