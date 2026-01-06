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
 * 模型：菜单
 */
class Menu extends Model
{
    use ParamTool, ModelTool, SoftDeletes;

    protected $primaryKey = 'menu_id';
    protected $table      = 'gpa_menu';

    protected $fillable = [
        'menu_id',
        'account_type',
        'menu_name',
        'menu_key',
        'menu_path',
        'menu_icon',
        'menu_type',
        'parent_id',
        'component',
        'redirect',
        'is_required',
        'sort',
        'status',
    ];

    protected $hidden = [
        'deleted_at',
    ];

    /**
     * 获取菜单列表
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
            'parent_id'    => '=',
            'menu_name'    => 'like',
            'menu_key'     => 'like',
            'menu_path'    => 'like',
            'menu_type'    => '=',
            'status'       => '=',
            'date_range'   => 'created_at',
        ]);
    }

    /**
     * 获取菜单树形结构
     * @param array $params
     * @return array
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    public static function getTreeData(array $params = []): array
    {
        // account_type 可选，如果不传则返回所有
        if (isset($params['account_type'])) {
            $allow_account_types = array_column(AccountTypeEnum::cases(), 'value');
            if (!in_array($params['account_type'], $allow_account_types)) {
                throw_exception('账号类型无效');
            }
        }

        $query = self::query();
        if (isset($params['account_type'])) {
            $query->where('account_type', $params['account_type']);
        }

        $menus = $query->orderBy('sort', 'asc')
            ->orderBy('menu_id', 'asc')
            ->get()
            ->toArray();

        return self::buildMenuTree($menus);
    }

    /**
     * 构建菜单树形结构
     * @param array $menus
     * @param int   $parentId
     * @return array
     * @author siushin<siushin@163.com>
     */
    private static function buildMenuTree(array $menus, int $parentId = 0): array
    {
        $tree = [];

        foreach ($menus as $menu) {
            if ($menu['parent_id'] == $parentId) {
                $menuItem = [
                    'menu_id'      => $menu['menu_id'],
                    'menu_name'    => $menu['menu_name'],
                    'menu_key'     => $menu['menu_key'],
                    'menu_path'    => $menu['menu_path'],
                    'menu_icon'    => $menu['menu_icon'],
                    'menu_type'    => $menu['menu_type'],
                    'parent_id'    => $menu['parent_id'],
                    'component'    => $menu['component'],
                    'redirect'     => $menu['redirect'],
                    'is_required'  => $menu['is_required'],
                    'status'       => $menu['status'],
                    'sort'         => $menu['sort'],
                    'account_type' => $menu['account_type'],
                ];

                // 递归获取子菜单
                $children = self::buildMenuTree($menus, $menu['menu_id']);
                if (!empty($children)) {
                    $menuItem['children'] = $children;
                }

                $tree[] = $menuItem;
            }
        }

        return $tree;
    }

    /**
     * 新增菜单
     * @param array $params
     * @return array
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    public static function addMenu(array $params): array
    {
        self::checkEmptyParam($params, ['menu_name', 'menu_type', 'account_type']);

        $menu_name = $params['menu_name'];
        $menu_type = $params['menu_type'];
        $account_type = $params['account_type'];
        $parent_id = $params['parent_id'] ?? 0;

        // 验证 account_type 是否为有效枚举值
        $allow_account_types = array_column(AccountTypeEnum::cases(), 'value');
        if (!in_array($account_type, $allow_account_types)) {
            throw_exception('账号类型无效');
        }

        // 验证 parent_id 是否存在（如果不是0）
        if ($parent_id > 0) {
            $parentMenu = self::query()->find($parent_id);
            !$parentMenu && throw_exception('父菜单不存在');
            // 验证父菜单的 account_type 是否一致
            if ($parentMenu->account_type !== $account_type) {
                throw_exception('父菜单的账号类型与当前菜单不一致');
            }
        }

        // 如果 menu_path 不为空，检查同一账号类型下路径是否唯一
        if (!empty($params['menu_path'])) {
            $exist = self::query()
                ->where('account_type', $account_type)
                ->where('menu_path', $params['menu_path'])
                ->exists();
            $exist && throw_exception('该账号类型下路由路径已存在');
        }

        // 过滤允许的字段
        $allowed_fields = [
            'account_type', 'menu_name', 'menu_key', 'menu_path', 'menu_icon',
            'menu_type', 'parent_id', 'component', 'redirect', 'is_required', 'status', 'sort'
        ];
        $create_data = self::getArrayByKeys($params, $allowed_fields);

        // 设置默认值
        $create_data['parent_id'] = $create_data['parent_id'] ?? 0;
        $create_data['menu_type'] = $create_data['menu_type'] ?? 'menu';
        $create_data['status'] = $create_data['status'] ?? 1;
        $create_data['sort'] = $create_data['sort'] ?? 0;
        $create_data['is_required'] = $create_data['is_required'] ?? 0;

        $info = self::query()->create($create_data);
        !$info && throw_exception('新增菜单失败');
        $info = $info->toArray();

        logGeneral(LogActionEnum::insert->name, "新增菜单成功(menu_name: $menu_name)", $info);

        // 记录审计日志
        logAudit(
            request(),
            currentUserId(),
            '菜单管理',
            OperationActionEnum::add->value,
            ResourceTypeEnum::menu->value,
            $info['menu_id'],
            null,
            $info,
            "新增菜单: $menu_name"
        );

        return ['menu_id' => $info['menu_id']];
    }

    /**
     * 编辑菜单
     * @param array $params
     * @return array
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    public static function updateMenu(array $params): array
    {
        self::checkEmptyParam($params, ['menu_id', 'menu_name', 'menu_type']);

        $menu_id = $params['menu_id'];
        $menu_name = $params['menu_name'];
        $menu_type = $params['menu_type'];

        $info = self::query()->find($menu_id);
        !$info && throw_exception('找不到该数据，请刷新后重试');
        $old_data = $info->toArray();

        // 编辑时使用原有的 account_type，不允许修改
        $account_type = $info->account_type;

        $parent_id = $params['parent_id'] ?? $info->parent_id;

        // 验证 parent_id 是否存在（如果不是0），并且不能设置为自己的子菜单
        if ($parent_id > 0) {
            if ($parent_id == $menu_id) {
                throw_exception('不能将自己设置为父菜单');
            }
            $parentMenu = self::query()->find($parent_id);
            !$parentMenu && throw_exception('父菜单不存在');
            // 验证父菜单的 account_type 是否一致
            if ($parentMenu->account_type !== $account_type) {
                throw_exception('父菜单的账号类型与当前菜单不一致');
            }
            // 检查是否形成循环引用（父菜单不能是自己的子菜单）
            $childIds = self::getAllChildIds($menu_id);
            if (in_array($parent_id, $childIds)) {
                throw_exception('不能将子菜单设置为父菜单，会导致循环引用');
            }
        }

        // 构建更新数据
        $update_data = ['menu_name' => $menu_name, 'menu_type' => $menu_type];

        // 支持更新其他字段（使用 array_key_exists 允许空字符串和 null 值更新）
        // 注意：account_type 不允许在编辑时修改，只能在创建时指定
        $allowed_fields = [
            'menu_key', 'menu_path', 'menu_icon',
            'parent_id', 'component', 'redirect', 'is_required', 'status', 'sort'
        ];
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
        if (array_key_exists('parent_id', $update_data) && ($update_data['parent_id'] === null || $update_data['parent_id'] === '')) {
            $update_data['parent_id'] = 0;
        }
        if (array_key_exists('is_required', $update_data) && ($update_data['is_required'] === null || $update_data['is_required'] === '')) {
            $update_data['is_required'] = 0;
        }

        // 如果 menu_path 发生变化，检查唯一性约束
        $check_menu_path = $update_data['menu_path'] ?? $info->menu_path;
        if (!empty($check_menu_path)) {
            $exist = self::query()
                ->where('account_type', $account_type)
                ->where('menu_path', $check_menu_path)
                ->where('menu_id', '<>', $menu_id)
                ->exists();
            $exist && throw_exception('该账号类型下路由路径已存在，更新失败');
        }

        $bool = $info->update($update_data);
        !$bool && throw_exception('更新菜单失败');

        $log_extend_data = compareArray($update_data, $old_data);
        logGeneral(LogActionEnum::update->name, "更新菜单(menu_name: $menu_name)", $log_extend_data);

        // 记录审计日志
        $new_data = $info->fresh()->toArray();
        logAudit(
            request(),
            currentUserId(),
            '菜单管理',
            OperationActionEnum::update->value,
            ResourceTypeEnum::menu->value,
            $menu_id,
            $old_data,
            $new_data,
            "更新菜单: $menu_name"
        );

        return [];
    }

    /**
     * 删除菜单
     * @param array $params
     * @return array
     * @throws ContainerExceptionInterface|NotFoundExceptionInterface
     * @author siushin<siushin@163.com>
     */
    public static function deleteMenu(array $params): array
    {
        self::checkEmptyParam($params, ['menu_id']);
        $menu_id = $params['menu_id'];

        $info = self::query()->find($menu_id);
        !$info && throw_exception('数据不存在');

        // 检查是否有子菜单
        $hasChildren = self::query()->where('parent_id', $menu_id)->exists();
        $hasChildren && throw_exception('该菜单下存在子菜单，无法删除');

        $old_data = $info->toArray();
        $menu_name = $old_data['menu_name'];
        $bool = $info->delete();
        !$bool && throw_exception('删除失败');

        logGeneral(LogActionEnum::delete->name, "删除菜单(ID: $menu_id)", $old_data);

        // 记录审计日志
        logAudit(
            request(),
            currentUserId(),
            '菜单管理',
            OperationActionEnum::delete->value,
            ResourceTypeEnum::menu->value,
            $menu_id,
            $old_data,
            null,
            "删除菜单: $menu_name"
        );

        return [];
    }

    /**
     * 获取目录树形结构（仅目录类型）
     * @param array $params
     * @return array
     * @throws Exception
     * @author siushin<siushin@163.com>
     */
    public static function getDirTree(array $params = []): array
    {
        self::checkEmptyParam($params, ['account_type']);

        $allow_account_types = array_column(AccountTypeEnum::cases(), 'value');
        if (!in_array($params['account_type'], $allow_account_types)) {
            throw_exception('账号类型无效');
        }

        // 只获取目录类型
        $menus = self::query()
            ->where('account_type', $params['account_type'])
            ->where('menu_type', 'dir')
            ->orderBy('sort', 'asc')
            ->orderBy('menu_id', 'asc')
            ->get()
            ->toArray();

        return self::buildDirTree($menus);
    }

    /**
     * 构建目录树形结构
     * @param array $menus
     * @param int   $parentId
     * @return array
     * @author siushin<siushin@163.com>
     */
    private static function buildDirTree(array $menus, int $parentId = 0): array
    {
        $tree = [];

        foreach ($menus as $menu) {
            if ($menu['parent_id'] == $parentId) {
                $menuItem = [
                    'menu_id'   => $menu['menu_id'],
                    'menu_name' => $menu['menu_name'],
                    'menu_key'  => $menu['menu_key'],
                ];

                // 递归获取子目录
                $children = self::buildDirTree($menus, $menu['menu_id']);
                if (!empty($children)) {
                    $menuItem['children'] = $children;
                }

                $tree[] = $menuItem;
            }
        }

        return $tree;
    }

    /**
     * 获取所有子菜单ID（递归）
     * @param int $menuId
     * @return array
     * @author siushin<siushin@163.com>
     */
    private static function getAllChildIds(int $menuId): array
    {
        $childIds = [];
        $children = self::query()->where('parent_id', $menuId)->pluck('menu_id')->toArray();
        foreach ($children as $childId) {
            $childIds[] = $childId;
            $childIds = array_merge($childIds, self::getAllChildIds($childId));
        }
        return $childIds;
    }
}
