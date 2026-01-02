<?php

namespace Modules\Base\Enums;

use Siushin\LaravelTool\Attributes\DescriptionAttribute;

/**
 * 枚举：通知类型
 */
enum NotificationTypeEnum: string
{
    #[DescriptionAttribute('系统通知')]
    case System   = 'system';
    #[DescriptionAttribute('业务通知')]
    case Business = 'business';
    #[DescriptionAttribute('活动通知')]
    case Activity = 'activity';
    #[DescriptionAttribute('其他')]
    case Other    = 'other';
}

