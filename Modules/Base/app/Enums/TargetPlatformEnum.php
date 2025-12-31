<?php

namespace Modules\Base\Enums;

use Modules\Base\Attributes\DescriptionAttribute;

/**
 * 枚举：目标平台
 */
enum TargetPlatformEnum: string
{
    #[DescriptionAttribute('全部平台')]
    case All = 'all';
    #[DescriptionAttribute('用户端')]
    case User = 'user';
    #[DescriptionAttribute('管理端')]
    case Admin = 'admin';
    #[DescriptionAttribute('小程序')]
    case Miniapp = 'miniapp';
}

