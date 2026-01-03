<?php

namespace Modules\Base\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Base\Enums\DictionaryCategoryEnum;
use Modules\Base\Enums\SysParamFlagEnum;
use Modules\Base\Models\Dictionary;
use Modules\Base\Models\DictionaryCategory;
use Siushin\LaravelTool\Enums\RequestSourceEnum;
use Siushin\LaravelTool\Enums\UploadFileTypeEnum;

/**
 * 数据填充：字典
 */
class DictionarySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 数据字典分类
        $categories = [];
        foreach (DictionaryCategoryEnum::cases() as $category) {
            $categories[] = [
                'category_id'    => generateId(),
                'category_name'  => $category->value,
                'category_code'  => $category->name,
                'tpl_path'       => 'tpl/Dictionary.xlsx',
                'category_desc'  => getEnumComment($category) ?? '',
                'sys_param_flag' => SysParamFlagEnum::Yes,
            ];
        }
        DictionaryCategory::upsert($categories, uniqueBy: ['category_code'], update: ['category_name']);

        $region_data = [
            ['name' => 'region', 'value' => '行政区划', 'desc' => '标识地区行政层级及归属，如省、市、区 / 县、街道 / 乡镇等'],
        ];
        $region_data = collect($region_data)->map(function ($item) {
            return (object)[
                'name'  => $item['name'],
                'value' => $item['value'],
                'desc'  => $item['desc'],
            ];
        })->values()->all();

        // 数据字典
        $dictionary_map = [
            DictionaryCategoryEnum::RequestSource->name       => RequestSourceEnum::cases(),
            DictionaryCategoryEnum::AllowUploadFileType->name => UploadFileTypeEnum::cases(),
            DictionaryCategoryEnum::OrganizationType->name    => $region_data,
        ];
        $dictionary_data = [];
        foreach ($dictionary_map as $category_code => $dictionary_enums) {
            $category_id = DictionaryCategory::checkCodeValidate(compact('category_code'));
            foreach ($dictionary_enums as $dictionary_item) {
                $dictionary_data[] = [
                    'dictionary_id'    => generateId(),
                    'category_id'      => $category_id,
                    'dictionary_name'  => $dictionary_item->name,
                    'dictionary_value' => $dictionary_item->value,
                    'dictionary_desc'  => $dictionary_item->desc ?? null,
                    'sys_param_flag'   => SysParamFlagEnum::Yes,
                ];
            }
        }
        Dictionary::upsert($dictionary_data, uniqueBy: ['category_id', 'dictionary_name', 'dictionary_value']);
    }
}
