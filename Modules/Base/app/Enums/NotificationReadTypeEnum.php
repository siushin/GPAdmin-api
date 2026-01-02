<?php

namespace Modules\Base\Enums;

use Siushin\LaravelTool\Attributes\DescriptionAttribute;

/**
 * 枚举：通知查看类型
 */
enum NotificationReadTypeEnum: string
{
    #[DescriptionAttribute('系统通知')]
    case SystemNotification = 'system_notification';
    #[DescriptionAttribute('站内信')]
    case Message            = 'message';
    #[DescriptionAttribute('公告')]
    case Announcement       = 'announcement';
}

