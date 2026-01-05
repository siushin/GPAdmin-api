<?php

namespace Modules\Base\Models;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Base\Enums\AccountTypeEnum;
use Modules\Base\Enums\LogActionEnum;
use Modules\Base\Enums\OperationActionEnum;
use Modules\Base\Enums\ResourceTypeEnum;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Siushin\LaravelTool\Traits\ModelTool;
use Siushin\Util\Traits\ParamTool;

/**
 * 模型：角色
 */
class Role extends Model
{
    use ParamTool, ModelTool, SoftDeletes;

    protected $primaryKey = 'role_id';
    protected $table      = 'gpa_role';

    protected $fillable = [
        'role_id',
        'account_type',
        'role_name',
        'role_code',
        'description',
        'status',
        'sort',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * 获取角色列表
     * @param array $params
     * @return array
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    public static function getPageData(array $params = []): array
    {
        self::checkEmptyParam($params, ['account_type']);
        // 验证 account_type 是否为有效枚举值
        $allow_account_types = array_column(AccountTypeEnum::cases(), 'value');
        if (!in_array($params['account_type'], $allow_account_types)) {
            throw_exception('账号类型无效');
        }

        return self::fastGetPageData(self::query(), $params, [
            'account_type' => '=',
            'role_name'    => 'like',
            'role_code'    => 'like',
            'status'       => '=',
        ]);
    }

    /**
     * 获取角色列表（全部）
     * @param array $params
     * @param array $fields
     * @return array
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    public static function getAllData(array $params = [], array $fields = []): array
    {
        self::checkEmptyParam($params, ['account_type']);
        // 验证 account_type 是否为有效枚举值
        $allow_account_types = array_column(AccountTypeEnum::cases(), 'value');
        if (!in_array($params['account_type'], $allow_account_types)) {
            throw_exception('账号类型无效');
        }

        return self::fastGetAllData(self::class, $params, [
            'account_type' => '=',
            'role_name'    => 'like',
            'role_code'    => 'like',
            'status'       => '=',
        ], $fields);
    }

    /**
     * 新增角色
     * @param array $params
     * @return array
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    public static function addRole(array $params): array
    {
        self::checkEmptyParam($params, ['role_name', 'role_code', 'account_type']);

        $role_name = $params['role_name'];
        $role_code = $params['role_code'];
        $account_type = $params['account_type'];

        // 验证 account_type 是否为有效枚举值
        $allow_account_types = array_column(AccountTypeEnum::cases(), 'value');
        if (!in_array($account_type, $allow_account_types)) {
            throw_exception('账号类型无效');
        }

        // 检查唯一性约束：同一账号类型下角色编码必须唯一
        $exist = self::query()
            ->where('account_type', $account_type)
            ->where('role_code', $role_code)
            ->exists();
        $exist && throw_exception('该账号类型下角色编码已存在');

        // 过滤允许的字段
        $allowed_fields = ['account_type', 'role_name', 'role_code', 'description', 'status', 'sort'];
        $create_data = self::getArrayByKeys($params, $allowed_fields);

        // 设置默认值
        $create_data['status'] = $create_data['status'] ?? 1;
        $create_data['sort'] = $create_data['sort'] ?? 0;

        $info = self::query()->create($create_data);
        !$info && throw_exception('新增角色失败');
        $info = $info->toArray();

        logGeneral(LogActionEnum::insert->name, "新增角色成功(role_name: $role_name)", $info);

        // 记录审计日志
        logAudit(
            request(),
            currentUserId(),
            '角色管理',
            OperationActionEnum::add->value,
            ResourceTypeEnum::role->value,
            $info['role_id'],
            null,
            $info,
            "新增角色: $role_name"
        );

        return ['role_id' => $info['role_id']];
    }

    /**
     * 更新角色
     * @param array $params
     * @return array
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    public static function updateRole(array $params): array
    {
        self::checkEmptyParam($params, ['role_id', 'role_name', 'role_code']);

        $role_id = $params['role_id'];
        $role_name = $params['role_name'];
        $role_code = $params['role_code'];

        $info = self::query()->find($role_id);
        !$info && throw_exception('找不到该数据，请刷新后重试');
        $old_data = $info->toArray();

        $account_type = $info->account_type;

        // 如果传了 account_type，需要验证是否为有效枚举值
        if (isset($params['account_type'])) {
            if (!in_array($params['account_type'], [AccountTypeEnum::Admin->value, AccountTypeEnum::User->value])) {
                throw_exception('账号类型无效');
            }
            $account_type = $params['account_type'];
        }

        // 构建更新数据
        $update_data = ['role_name' => $role_name, 'role_code' => $role_code];

        // 支持更新其他字段（使用 array_key_exists 允许空字符串和 null 值更新）
        $allowed_fields = ['account_type', 'description', 'status', 'sort'];
        foreach ($allowed_fields as $field) {
            if (array_key_exists($field, $params)) {
                $update_data[$field] = $params[$field];
            }
        }

        // 处理不能为 null 的字段，设置默认值
        if (array_key_exists('sort', $update_data) && ($update_data['sort'] === null || $update_data['sort'] === '')) {
            $update_data['sort'] = 0;
        }
        if (array_key_exists('status', $update_data) && ($update_data['status'] === null || $update_data['status'] === '')) {
            $update_data['status'] = 1;
        }

        // 确定用于检查唯一性的 account_type 和 role_code
        $check_account_type = $update_data['account_type'] ?? $account_type;
        $check_role_code = $update_data['role_code'];

        // 检查唯一性约束：同一账号类型下角色编码必须唯一，排除当前记录
        $exist = self::query()
            ->where('account_type', $check_account_type)
            ->where('role_code', $check_role_code)
            ->where('role_id', '<>', $role_id)
            ->exists();
        $exist && throw_exception('该账号类型下角色编码已存在，更新失败');

        $bool = $info->update($update_data);
        !$bool && throw_exception('更新角色失败');

        $log_extend_data = compareArray($update_data, $old_data);
        logGeneral(LogActionEnum::update->name, "更新角色(role_name: $role_name)", $log_extend_data);

        // 记录审计日志
        $new_data = $info->fresh()->toArray();
        logAudit(
            request(),
            currentUserId(),
            '角色管理',
            OperationActionEnum::update->value,
            ResourceTypeEnum::role->value,
            $role_id,
            $old_data,
            $new_data,
            "更新角色: $role_name"
        );

        return [];
    }

    /**
     * 删除角色
     * @param array $params
     * @return array
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    public static function deleteRole(array $params): array
    {
        self::checkEmptyParam($params, ['role_id']);
        $role_id = $params['role_id'];

        $info = self::query()->find($role_id);
        !$info && throw_exception('数据不存在');

        $old_data = $info->toArray();
        $role_name = $old_data['role_name'];
        $bool = $info->delete();
        !$bool && throw_exception('删除失败');

        logGeneral(LogActionEnum::delete->name, "删除角色(ID: $role_id)", $old_data);

        // 记录审计日志
        logAudit(
            request(),
            currentUserId(),
            '角色管理',
            OperationActionEnum::delete->value,
            ResourceTypeEnum::role->value,
            $role_id,
            $old_data,
            null,
            "删除角色: $role_name"
        );

        return [];
    }
}
