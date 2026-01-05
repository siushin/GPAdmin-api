<?php

namespace Modules\Base\Models;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Base\Enums\LogActionEnum;
use Modules\Base\Enums\OperationActionEnum;
use Modules\Base\Enums\ResourceTypeEnum;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Siushin\LaravelTool\Traits\ModelTool;
use Siushin\Util\Traits\ParamTool;

/**
 * 模型：公司
 */
class Company extends Model
{
    use ParamTool, ModelTool, SoftDeletes;

    protected $table      = 'gpa_company';
    protected $primaryKey = 'company_id';

    protected $fillable = [
        'company_id',
        'company_code',
        'company_name',
        'company_credit_code',
        'legal_person',
        'contact_phone',
        'contact_email',
        'company_address',
        'company_desc',
        'status',
    ];

    protected $hidden = [
        'deleted_at',
    ];

    const int STATUS_DISABLE = 0;   // 禁用
    const int STATUS_NORMAL  = 1;   // 正常

    /**
     * 获取公司列表（全部）
     * @param array $params 支持：company_code、company_name、company_credit_code
     * @param array $fields
     * @return array
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    public static function getAllData(array $params = [], array $fields = []): array
    {
        $params['status'] = self::STATUS_NORMAL;
        return self::fastGetAllData(self::class, $params, [
            'company_name'        => 'like',
            'company_code'        => 'like',
            'company_credit_code' => 'like',
            'status'              => '=',
            'date_range'          => 'created_at',
        ], $fields);
    }

    /**
     * 获取公司列表
     * @param array $params
     * @return array
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    public static function getPageData(array $params = []): array
    {
        return self::fastGetPageData(self::query(), $params, [
            'company_name'        => 'like',
            'company_code'        => 'like',
            'company_credit_code' => 'like',
            'status'              => '=',
            'date_range'          => 'created_at',
        ]);
    }

    /**
     * 新增公司
     * @param array $params
     * @return array
     * @throws ContainerExceptionInterface|NotFoundExceptionInterface|Exception
     * @author siushin<siushin@163.com>
     */
    public static function addCompany(array $params): array
    {
        self::trimValueArray($params, [], [null]);
        self::checkEmptyParam($params, ['company_name']);

        $company_name = $params['company_name'];
        $company_code = $params['company_code'] ?? null;

        // 检查统一社会信用代码必填
        $company_credit_code = $params['company_credit_code'] ?? null;
        if (empty($company_credit_code)) {
            throw_exception('统一社会信用代码不能为空');
        }
        if (strlen($company_credit_code) !== 18) {
            throw_exception('统一社会信用代码必须为18位');
        }

        // 如果提供了 company_code，检查唯一性（排除软删除的记录）
        if (!empty($company_code)) {
            $exist = self::query()
                ->where('company_code', $company_code)
                ->whereNull('deleted_at') // 排除软删除的记录
                ->exists();
            $exist && throw_exception('公司编码已存在');
        }

        // 检查统一社会信用代码唯一性（排除软删除的记录）
        $exist = self::query()
            ->where('company_credit_code', $company_credit_code)
            ->whereNull('deleted_at') // 排除软删除的记录
            ->exists();
        $exist && throw_exception('统一社会信用代码已存在');

        // 检查公司名称唯一性（排除软删除的记录）
        $exist = self::query()
            ->where('company_name', $company_name)
            ->whereNull('deleted_at') // 排除软删除的记录
            ->exists();
        $exist && throw_exception('公司名称已存在');

        // 过滤允许的字段
        $allowed_fields = [
            'company_code', 'company_name', 'company_credit_code', 'legal_person',
            'contact_phone', 'contact_email', 'company_address', 'company_desc', 'status'
        ];
        $create_data = self::getArrayByKeys($params, $allowed_fields);

        // 设置默认值
        $create_data['status'] = $create_data['status'] ?? self::STATUS_NORMAL;

        $info = self::query()->create($create_data);
        !$info && throw_exception('新增公司失败');
        $info = $info->toArray();

        logGeneral(LogActionEnum::insert->name, "新增公司成功(company_name: $company_name)", $info);

        // 记录审计日志
        logAudit(
            request(),
            currentUserId(),
            '公司管理',
            OperationActionEnum::add->value,
            ResourceTypeEnum::other->value,
            $info['company_id'],
            null,
            $info,
            "新增公司: $company_name"
        );

        return ['company_id' => $info['company_id']];
    }

    /**
     * 更新公司
     * @param array $params
     * @return array
     * @throws ContainerExceptionInterface|NotFoundExceptionInterface|Exception
     * @author siushin<siushin@163.com>
     */
    public static function updateCompany(array $params): array
    {
        self::trimValueArray($params, [], [null]);
        self::checkEmptyParam($params, ['company_id', 'company_name']);

        $company_id = $params['company_id'];
        $company_name = $params['company_name'];

        $info = self::query()->find($company_id);
        !$info && throw_exception('找不到该数据，请刷新后重试');
        $old_data = $info->toArray();

        // 检查公司名称唯一性，排除当前记录和软删除的记录
        $exist = self::query()
            ->where('company_name', $company_name)
            ->where('company_id', '<>', $company_id)
            ->whereNull('deleted_at') // 排除软删除的记录
            ->exists();
        $exist && throw_exception('公司名称已存在，更新失败');

        // 如果提供了 company_code，检查唯一性，排除当前记录和软删除的记录
        if (isset($params['company_code']) && !empty($params['company_code'])) {
            $exist = self::query()
                ->where('company_code', $params['company_code'])
                ->where('company_id', '<>', $company_id)
                ->whereNull('deleted_at') // 排除软删除的记录
                ->exists();
            $exist && throw_exception('公司编码已存在，更新失败');
        }

        // 如果提供了 company_credit_code，检查唯一性和格式，排除当前记录和软删除的记录
        if (isset($params['company_credit_code']) && !empty($params['company_credit_code'])) {
            if (strlen($params['company_credit_code']) !== 18) {
                throw_exception('统一社会信用代码必须为18位');
            }
            $exist = self::query()
                ->where('company_credit_code', $params['company_credit_code'])
                ->where('company_id', '<>', $company_id)
                ->whereNull('deleted_at') // 排除软删除的记录
                ->exists();
            $exist && throw_exception('统一社会信用代码已存在，更新失败');
        }

        // 构建更新数据
        $update_data = ['company_name' => $company_name];

        // 支持更新其他字段
        // 注意：使用 array_key_exists 而不是 isset，允许显式传递 null 来清空字段
        $allowed_fields = [
            'company_code', 'company_credit_code', 'legal_person',
            'contact_phone', 'contact_email', 'company_address', 'company_desc', 'status'
        ];
        foreach ($allowed_fields as $field) {
            if (array_key_exists($field, $params)) {
                $update_data[$field] = $params[$field];
            }
        }

        $bool = $info->update($update_data);
        !$bool && throw_exception('更新公司失败');

        $log_extend_data = compareArray($update_data, $old_data);
        logGeneral(LogActionEnum::update->name, "更新公司(company_name: $company_name)", $log_extend_data);

        // 记录审计日志
        $new_data = $info->fresh()->toArray();
        logAudit(
            request(),
            currentUserId(),
            '公司管理',
            OperationActionEnum::update->value,
            ResourceTypeEnum::other->value,
            $company_id,
            $old_data,
            $new_data,
            "更新公司: $company_name"
        );

        return [];
    }

    /**
     * 删除公司
     * @param array $params
     * @return array
     * @throws ContainerExceptionInterface|NotFoundExceptionInterface|Exception
     * @author siushin<siushin@163.com>
     */
    public static function deleteCompany(array $params): array
    {
        self::checkEmptyParam($params, ['company_id']);
        $company_id = $params['company_id'];

        $info = self::query()->find($company_id);
        !$info && throw_exception('数据不存在');

        // 检查是否有部门关联
        $hasDepartments = Department::query()->where('company_id', $company_id)->exists();
        $hasDepartments && throw_exception('该公司下存在部门，无法删除');

        // 检查是否有管理员关联
        $hasAdmins = Admin::query()->where('company_id', $company_id)->exists();
        $hasAdmins && throw_exception('该公司下存在管理员，无法删除');

        $old_data = $info->toArray();
        $company_name = $old_data['company_name'];
        $bool = $info->delete();
        !$bool && throw_exception('删除失败');

        logGeneral(LogActionEnum::delete->name, "删除公司(ID: $company_id)", $old_data);

        // 记录审计日志
        logAudit(
            request(),
            currentUserId(),
            '公司管理',
            OperationActionEnum::delete->value,
            ResourceTypeEnum::other->value,
            $company_id,
            $old_data,
            null,
            "删除公司: $company_name"
        );

        return [];
    }
}
