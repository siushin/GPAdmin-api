<?php

namespace Modules\Base\Enums;

use Siushin\LaravelTool\Attributes\DescriptionAttribute;

/**
 * 枚举：身份证核验状态
 */
enum IdCardVerifyStatusEnum: int
{
    #[DescriptionAttribute('未核验')]
    case NOT_VERIFIED = 0;
    #[DescriptionAttribute('核验通过')]
    case VERIFIED     = 1;
    #[DescriptionAttribute('失败')]
    case FAILED       = 2;
}

