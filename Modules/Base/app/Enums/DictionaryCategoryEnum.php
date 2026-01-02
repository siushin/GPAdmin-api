<?php

namespace Modules\Base\Enums;

use Siushin\LaravelTool\Attributes\DescriptionAttribute;

/**
 * 枚举：字典类别
 */
enum DictionaryCategoryEnum: string
{
    #[DescriptionAttribute('记录请求发起的渠道或终端，如 PC 端、移动端、第三方接口等')]
    case RequestSource = '请求来源';

    #[DescriptionAttribute('标识组织架构的层级或类别，如公司、部门、小组、分公司等')]
    case OrganizationType = '组织架构类型';

    #[DescriptionAttribute('限定系统可接收的上传文件格式，如 jpg、png、docx、pdf 等')]
    case AllowUploadFileType = '允许上传文件类型';
}
