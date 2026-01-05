<?php

namespace Modules\Base\Models;

use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Base\Enums\LogActionEnum;
use Modules\Base\Enums\OperationActionEnum;
use Modules\Base\Enums\ResourceTypeEnum;
use Modules\Base\Enums\SysParamFlagEnum;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Siushin\LaravelTool\Traits\ModelTool;
use Siushin\Util\Traits\ParamTool;

/**
 * 模型：字典
 */
class Dictionary extends Model
{
    use HasFactory, ParamTool, ModelTool;

    protected $primaryKey = 'dictionary_id';
    protected $table      = 'gpa_dictionary';

    protected $fillable = [
        'dictionary_id', 'category_id', 'dictionary_name', 'dictionary_value', 'dictionary_desc', 'sort'
    ];

    protected $hidden = ['created_at', 'updated_at'];

    // 自动生成值（按照序号自增）
    private static array $auto_ins_generate_value = [];

    // 自动生成值（值跟键相同）
    private static array $auto_ins_same_key_value = [];

    /**
     * 获取数据字典列表
     * @param array $params
     * @return array
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    public static function getPageData(array $params = []): array
    {
        $category_id = DictionaryCategory::checkCodeValidate($params);
        $params['category_id'] = $category_id;
        return self::fastGetPageData(self::query(), $params, [
            'category_id'      => '=',
            'dictionary_name'  => 'like',
            'dictionary_value' => 'like',
            'sys_param_flag'   => '=',
            'date_range'       => 'created_at',
            'time_range'       => 'created_at',
        ]);
    }

    /**
     * 获取数据字典列表（全部）
     * @param array $params 支持：category_code（指定字典类型code）
     * @param array $fields
     * @return array
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    public static function getAllData(array $params = [], array $fields = []): array
    {
        $category_id = DictionaryCategory::checkCodeValidate($params);
        $params['category_id'] = $category_id;
        return self::fastGetAllData(self::class, $params, [
            'category_id'      => '=',
            'dictionary_name'  => 'like',
            'dictionary_value' => 'like',
            'dictionary_desc'  => 'like',
            'sys_param_flag'   => '=',
            'date_range'       => 'created_at',
            'time_range'       => 'created_at',
        ], $fields);
    }

    /**
     * 根据code获取数据字典id
     * @param string $category_code
     * @param string $dictionary_name
     * @param string $dictionary_value
     * @return int
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    public static function getDictionaryIdByCode(string $category_code, string $dictionary_name = '', string $dictionary_value = ''): int
    {
        $category_id = DictionaryCategory::checkCodeValidate(compact('category_code'));
        $where = self::buildWhereData(
            compact('category_id', 'dictionary_name', 'dictionary_value'),
            ['category_id' => '=', 'dictionary_name' => '=', 'dictionary_value' => '=']
        );
        return self::where('category_id', $category_id)->where($where)->value('dictionary_id', 0);
    }

    /**
     * 获取指定数据字典列表（根据code）
     * @param string $category_code
     * @param array  $fields
     * @param bool   $isValue2Key
     * @return array
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    public static function getDictionaryByCode(string $category_code, array $fields = [], bool $isValue2Key = false): array
    {
        $fields = $fields ?: ['dictionary_name', 'dictionary_value', 'dictionary_desc'];
        $category_id = DictionaryCategory::checkCodeValidate(compact('category_code'));
        $data = self::fastGetAllData(self::query(), compact('category_id'), [
            'category_id' => '=',
        ], $fields);
        return $isValue2Key ? array_column($data, 'dictionary_name', 'dictionary_value') : $data;
    }

    /**
     * 根据code获取指定列数据
     * @param string $category_code
     * @param string $field
     * @return array
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    public static function getDictionaryColumnData(string $category_code, string $field = 'dictionary_value'): array
    {
        return array_column(self::getDictionaryByCode($category_code), $field);
    }

    /**
     * 新增数据字典
     * @param array $params
     * @param array $response_keys
     * @return array
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    public static function addDictionary(array $params = [], array $response_keys = []): array
    {
        $category_id = DictionaryCategory::checkCodeValidate($params);
        $params['category_id'] = $category_id;

        self::checkEmptyParam($params, ['category_code', 'dictionary_name']);

        $category_code = $params['category_code'];
        $dictionary_name = $params['dictionary_name'];
        unset($params['category_code']); // 移除 category_code，避免插入数据库

        // 检查唯一性约束1：同一分类下字典名称必须唯一（unique_dictionary_name）
        $exist_name = self::query()
            ->where('category_id', $category_id)
            ->where('dictionary_name', $dictionary_name)
            ->exists();
        $exist_name && throw_exception('该分类下字典名称已存在');

        // 处理自动生成值逻辑
        if (array_key_exists($category_code, self::$auto_ins_generate_value)) {
            // 自动生成值（取当前数据库最大值+1）
            $last_max_info = self::query()
                ->where('category_id', $category_id)
                ->selectRaw('max(cast(dictionary_value as SIGNED)) as dictionary_value')
                ->first();
            $params['dictionary_value'] = !is_null($last_max_info->dictionary_value) ?
                intval($last_max_info->dictionary_value) + 1 : self::$auto_ins_generate_value[$category_code];
        } elseif (in_array($category_code, self::$auto_ins_same_key_value)) {
            // 自动生成值（值跟键相同）
            $params['dictionary_value'] = $dictionary_name;
        } else {
            // 手动指定值
            self::checkEmptyParam($params, ['dictionary_value']);
        }

        // 检查唯一性约束2：同一分类下字典名称和值的组合必须唯一（unique_dictionary）
        $check_where = [
            'category_id'      => $category_id,
            'dictionary_name'  => $dictionary_name,
            'dictionary_value' => $params['dictionary_value']
        ];
        $exist = self::query()->where($check_where)->exists();
        $exist && throw_exception('该分类下字典名称和值的组合已存在');

        // 过滤允许的字段
        $allowed_fields = ['category_id', 'dictionary_name', 'dictionary_value', 'dictionary_desc', 'sort'];
        $params = self::getArrayByKeys($params, $allowed_fields);

        $info = self::query()->create($params);
        !$info && throw_exception('添加数据字典失败');
        $info = $info->toArray();
        logGeneral(LogActionEnum::insert->name, "添加数据字典成功(dictionary_name: {$dictionary_name})", $info);

        // 记录审计日志
        logAudit(
            request(),
            currentUserId(),
            '数据字典',
            OperationActionEnum::add->value,
            ResourceTypeEnum::other->value,
            $info['dictionary_id'],
            null,
            $info,
            "新增数据字典: {$dictionary_name}"
        );

        $response_keys = $response_keys ?: ['dictionary_id', 'dictionary_name', 'dictionary_value', 'dictionary_desc', 'sort'];
        return self::getArrayByKeys($info, $response_keys);
    }

    /**
     * 更新数据字典
     * @param array $params
     * @return array
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    public static function updateDictionary(array $params = []): array
    {
        self::checkEmptyParam($params, ['dictionary_id', 'dictionary_name']);

        $dictionary_id = $params['dictionary_id'];
        $dictionary_name = $params['dictionary_name'];

        $info = self::query()->find($dictionary_id);
        !$info && throw_exception('找不到该数据，请刷新后重试');
        $old_data = $info->toArray();

        $category_id = $info->category_id;
        $category_code = DictionaryCategory::query()
            ->where('category_id', $category_id)
            ->value('category_code');

        // 构建更新数据
        $update_data = ['dictionary_name' => $dictionary_name];

        // 处理自动生成值逻辑
        if (array_key_exists($category_code, self::$auto_ins_generate_value)) {
            // 自动生成值类型：不允许修改值，保持原值
            $update_data['dictionary_value'] = $info->dictionary_value;
        } elseif (in_array($category_code, self::$auto_ins_same_key_value)) {
            // 自动生成值（值跟键相同）
            $update_data['dictionary_value'] = $dictionary_name;
        } else {
            // 手动指定值
            self::checkEmptyParam($params, ['dictionary_value']);
            $update_data['dictionary_value'] = $params['dictionary_value'];
        }

        // 支持更新其他字段（使用 array_key_exists 允许空字符串和 null 值更新）
        $allowed_fields = ['dictionary_desc', 'sort'];
        foreach ($allowed_fields as $field) {
            if (array_key_exists($field, $params)) {
                $update_data[$field] = $params[$field];
            }
        }

        // 处理不能为 null 的字段，设置默认值
        if (array_key_exists('sort', $update_data) && ($update_data['sort'] === null || $update_data['sort'] === '')) {
            $update_data['sort'] = 0;
        }

        // 检查唯一性约束1：同一分类下字典名称必须唯一（unique_dictionary_name），排除当前记录
        $exist_name = self::query()
            ->where('category_id', $category_id)
            ->where('dictionary_name', $dictionary_name)
            ->where('dictionary_id', '<>', $dictionary_id)
            ->exists();
        $exist_name && throw_exception('该分类下字典名称已存在，更新失败');

        // 检查唯一性约束2：同一分类下字典名称和值的组合必须唯一（unique_dictionary），排除当前记录
        $check_where = [
            'category_id'      => $category_id,
            'dictionary_name'  => $dictionary_name,
            'dictionary_value' => $update_data['dictionary_value']
        ];
        $exist = self::query()
            ->where($check_where)
            ->where('dictionary_id', '<>', $dictionary_id)
            ->exists();
        $exist && throw_exception('该分类下字典名称和值的组合已存在，更新失败');

        $bool = $info->update($update_data);
        !$bool && throw_exception('更新数据字典失败');

        $log_extend_data = compareArray($update_data, $old_data);
        logGeneral(LogActionEnum::update->name, "更新数据字典(dictionary_name: {$dictionary_name})", $log_extend_data);

        // 记录审计日志
        $new_data = $info->fresh()->toArray();
        logAudit(
            request(),
            currentUserId(),
            '数据字典',
            OperationActionEnum::update->value,
            ResourceTypeEnum::other->value,
            $dictionary_id,
            $old_data,
            $new_data,
            "更新数据字典: {$dictionary_name}"
        );

        return [];
    }

    /**
     * 删除数据字典
     * @param array $params
     * @return array
     * @throws ContainerExceptionInterface|NotFoundExceptionInterface
     * @author siushin<siushin@163.com>
     */
    public static function deleteDictionary(array $params = []): array
    {
        self::checkEmptyParam($params, ['dictionary_id']);
        $dictionary_id = $params['dictionary_id'];

        $info = self::query()->find($dictionary_id);
        !$info && throw_exception('数据不存在');

        // 验证：系统参数标识为 Yes 时，不允许删除
        if ($info->sys_param_flag === SysParamFlagEnum::Yes->value) {
            throw_exception('系统支撑数据，禁止删除');
        }

        $old_data = $info->toArray();
        $dictionary_name = $old_data['dictionary_name'];
        $bool = $info->delete();
        !$bool && throw_exception('删除失败');

        logGeneral(LogActionEnum::delete->name, "删除数据字典(ID: {$dictionary_id})", $old_data);

        // 记录审计日志
        logAudit(
            request(),
            currentUserId(),
            '数据字典',
            OperationActionEnum::delete->value,
            ResourceTypeEnum::other->value,
            $dictionary_id,
            $old_data,
            null,
            "删除数据字典: {$dictionary_name}"
        );

        return [];
    }

    /**
     * 批量删除数据字典
     * @param array $params 请求参数（需包含 dictionary_ids）
     * @return array
     * @throws ContainerExceptionInterface|NotFoundExceptionInterface
     * @author siushin<siushin@163.com>
     */
    public static function batchDeleteDictionary(array $params): array
    {
        self::checkEmptyParam($params, ['dictionary_ids']);

        $dictionary_ids = self::getQueryParam($params, 'dictionary_ids', [], ',');
        empty($dictionary_ids) && throw_exception('无效的字典ID列表');

        $records = self::query()->whereIn('dictionary_id', $dictionary_ids)->get();

        if ($records->count() != count($dictionary_ids)) {
            throw_exception('无效的字典ID列表，请刷新页面重试～');
        }

        // 验证：检查是否有系统参数标识为 Yes 的记录，不允许删除
        $sysParamRecords = $records->filter(function ($record) {
            return $record->sys_param_flag === SysParamFlagEnum::Yes->value;
        });

        if ($sysParamRecords->isNotEmpty()) {
            $sysParamNames = $sysParamRecords->pluck('dictionary_name')->toArray();
            throw_exception('系统支撑数据，禁止删除：' . implode('、', $sysParamNames));
        }

        $deletedCount = self::destroy($dictionary_ids);
        $deletedCount === 0 && throw_exception('删除失败，可能记录已不存在');

        logGeneral(
            LogActionEnum::batchDelete->name,
            "批量删除数据字典(数量: $deletedCount, IDs: " . implode(',', $dictionary_ids) . ")",
            $records->toArray()
        );

        // 记录审计日志
        $recordsArray = $records->toArray();
        foreach ($recordsArray as $record) {
            logAudit(
                request(),
                currentUserId(),
                '数据字典',
                OperationActionEnum::batchDelete->value,
                ResourceTypeEnum::other->value,
                $record['dictionary_id'],
                $record,
                null,
                "批量删除数据字典: {$record['dictionary_name']}"
            );
        }

        return [];
    }
}
