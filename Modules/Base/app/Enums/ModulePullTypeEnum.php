<?php

namespace Modules\Base\Enums;

use Siushin\LaravelTool\Attributes\DescriptionAttribute;

/**
 * 枚举：模块拉取类型
 */
enum ModulePullTypeEnum: string
{
    #[DescriptionAttribute('Git仓库')]
    case GIT = 'git';

    #[DescriptionAttribute('URL下载')]
    case URL = 'url';
}

