<?php

namespace Modules\Base\Enums;

use Modules\Base\Attributes\DescriptionAttribute;

/**
 * 枚举：账号状态
 */
enum AccountStatusEnum: int
{
    #[DescriptionAttribute('已拒绝')]
    case REJECTED   = -2;
    #[DescriptionAttribute('待审核')]
    case WAIT_AUDIT = -1;
    #[DescriptionAttribute('禁用')]
    case DISABLED   = 0;
    #[DescriptionAttribute('正常')]
    case NORMAL     = 1;
}
