<?php

namespace Modules\Base\Enums;

use Siushin\LaravelTool\Attributes\DescriptionAttribute;

/**
 * 枚举：菜单类型
 */
enum MenuTypeEnum: string
{
    #[DescriptionAttribute('目录')]
    case Dir = 'dir';

    #[DescriptionAttribute('菜单')]
    case Menu = 'menu';

    #[DescriptionAttribute('按钮')]
    case Button = 'button';

    #[DescriptionAttribute('链接')]
    case Link = 'link';
}
