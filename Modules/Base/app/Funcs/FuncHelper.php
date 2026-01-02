<?php

use Siushin\LaravelTool\Attributes\DescriptionAttribute;

/**
 * 助手函数：通用辅助函数
 */

/**
 * 从枚举注释中提取中文描述
 * 优先从 Attribute 中读取，如果不存在则从注释中读取（向后兼容）
 * @param UnitEnum $case 枚举实例
 * @return string|null 中文描述，如果未找到则返回 null
 * @throws ReflectionException
 * @author siushin<siushin@163.com>
 */
function getEnumComment(UnitEnum $case): ?string
{
    // 优先尝试从 Attribute 中读取描述
    try {
        $reflection = new ReflectionEnumUnitCase($case::class, $case->name);
        $attributes = $reflection->getAttributes(DescriptionAttribute::class);

        if (!empty($attributes)) {
            /** @var DescriptionAttribute $descriptionAttribute */
            $descriptionAttribute = $attributes[0]->newInstance();
            return $descriptionAttribute->description;
        }
    } catch (\ReflectionException $e) {
        // 如果反射失败，继续尝试从注释中读取
    }

    // 向后兼容：从注释中读取（旧格式）
    $reflection = new ReflectionClass($case::class);
    $file = $reflection->getFileName();
    if ($file && file_exists($file)) {
        $lines = file($file);
        // 获取枚举值（BackedEnum 有 value 属性，Pure Enum 没有）
        $enumValue = ($case instanceof BackedEnum) ? $case->value : $case->name;

        // 查找匹配的 case 行
        foreach ($lines as $line) {
            // 匹配 case CaseName = 'value'; // 中文描述
            if (preg_match('/case\s+' . preg_quote($case->name, '/') . '\s*=\s*[\'"]' . preg_quote($enumValue, '/') . '[\'"]\s*;\s*\/\/\s*(.+)/', $line, $matches)) {
                return trim($matches[1]);
            }
        }
    }
    return null;
}

/**
 * 构建枚举字段的注释字符串
 * @param array  $enumCases 枚举cases数组
 * @param string $typeName  类型名称（如：账号类型、组织架构类型等）
 * @return string 格式：类型名[key:value,key:value,...] 或 类型名[value:描述,value:描述,...]（如果有注释）
 * @throws ReflectionException
 * @author siushin<siushin@163.com>
 */
function buildEnumComment(array $enumCases, string $typeName): string
{
    $commentParts = [];
    foreach ($enumCases as $case) {
        $description = getEnumComment($case);
        $enumKey = $case->name; // 枚举名称（如：index, create）
        $enumValue = ($case instanceof BackedEnum) ? $case->value : $case->name; // 枚举值

        // 如果有注释（旧格式），使用 value:描述 格式（向后兼容）
        if ($description) {
            $commentParts[] = $enumValue . ':' . $description;
        } else {
            // 新格式：key:value（枚举名称:枚举值）
            $commentParts[] = $enumKey . ':' . $enumValue;
        }
    }
    return $typeName . '[' . implode(',', $commentParts) . ']';
}

/**
 * 将枚举类转换为数组格式（值作为键名，注释转为值）
 * @param string|UnitEnum $enumClass 枚举类名或枚举类实例
 * @return array 格式：[['key' => 'enum_value', 'value' => 'comment']]
 * @throws ReflectionException
 * @author siushin<siushin@163.com>
 */
function enumToArrayFromComment(string|UnitEnum $enumClass): array
{
    // 如果传入的是实例，获取类名
    if ($enumClass instanceof UnitEnum) {
        $enumClass = $enumClass::class;
    }

    // 获取所有枚举 cases
    $cases = $enumClass::cases();
    $result = [];

    foreach ($cases as $case) {
        // 获取枚举值（BackedEnum 有 value 属性，Pure Enum 使用 name）
        $enumValue = ($case instanceof BackedEnum) ? $case->value : $case->name;

        // 获取注释，如果没有注释则使用枚举名称
        $comment = getEnumComment($case) ?? $case->name;

        $result[] = [
            'key'   => $enumValue,
            'value' => $comment
        ];
    }

    return $result;
}

