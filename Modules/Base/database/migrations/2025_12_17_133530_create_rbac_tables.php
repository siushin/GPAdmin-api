<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Base\Enums\AccountTypeEnum;
use Modules\Base\Enums\MenuTypeEnum;
use Modules\Base\Enums\SysParamFlagEnum;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $accountTypeComment = buildEnumComment(AccountTypeEnum::cases(), '账号类型');
        $sysParamFlagComment = buildEnumComment(SysParamFlagEnum::cases(), '系统参数标识');
        $menuTypeComment = buildEnumComment(MenuTypeEnum::cases(), '菜单类型');

        // 角色表
        Schema::create('gpa_role', function (Blueprint $table) use ($accountTypeComment) {
            $table->id('role_id')->comment('角色ID');
            $table->string('account_type', 20)->default(AccountTypeEnum::Admin->value)->comment($accountTypeComment);
            $table->string('role_name', 50)->comment('角色名称');
            $table->string('role_code', 50)->comment('角色编码');
            $table->string('description')->nullable()->comment('角色描述');
            $table->tinyInteger('status')->default(1)->comment('状态: 1启用, 0禁用');
            $table->unsignedInteger('sort')->default(0)->comment('排序');
            $table->timestamps();
            $table->softDeletes()->comment('软删除时间');

            // 同一账号类型下角色编码唯一
            $table->unique(['account_type', 'role_code'], 'uk_role_account_type_code');
            $table->index('account_type');
            $table->index('status');
            $table->index('sort');
            $table->comment('角色表');
        });

        // 菜单表
        Schema::create('gpa_menu', function (Blueprint $table) use ($accountTypeComment, $sysParamFlagComment, $menuTypeComment) {
            $table->id('menu_id')->comment('菜单ID');
            $table->string('menu_type', 20)->default('menu')->comment($menuTypeComment);
            $table->unsignedBigInteger('parent_id')->default(0)->comment('父菜单ID, 0表示顶级菜单');
            $table->string('account_type', 20)->default(AccountTypeEnum::Admin->value)->comment($accountTypeComment);
            $table->unsignedBigInteger('module_id')->comment('所属模块ID（固定不变）');
            $table->string('menu_name', 50)->comment('菜单名称');
            $table->string('menu_key', 100)->nullable()->comment('菜单名称key（用于国际化，如：dashboard.workplace）');
            $table->string('menu_path', 200)->nullable()->comment('路由路径');
            $table->string('menu_icon', 50)->nullable()->comment('图标名称');
            $table->string('component', 200)->nullable()->comment('组件路径（相对路径，如：./Dashboard/Workplace）');
            $table->string('redirect', 200)->nullable()->comment('重定向路径');
            $table->tinyInteger('status')->default(1)->comment('状态: 1启用, 0禁用');
            $table->unsignedTinyInteger('is_required')->default(0)->comment('是否必须选中: 1必须选中, 0非必须');
            $table->unsignedTinyInteger('sys_param_flag')->default(SysParamFlagEnum::No)->comment($sysParamFlagComment);
            $table->unsignedInteger('sort')->default(0)->comment('排序');
            $table->timestamps();
            $table->softDeletes()->comment('软删除时间');

            // 同一账号类型下，路径唯一（如果路径不为空）
            // 注意：parent_id=0时表示顶级菜单，parent_id>0时表示子菜单
            $table->index('menu_type');
            $table->index('parent_id');
            $table->index('account_type');
            $table->index('module_id');
            $table->index('menu_key');
            $table->index('status');
            $table->index('is_required');
            $table->index('sort');
            $table->comment('菜单表');
        });

        // 用户角色关联表
        Schema::create('gpa_user_role', function (Blueprint $table) {
            $table->id()->comment('关联ID');
            $table->unsignedBigInteger('account_id')->comment('账号ID（关联gpa_account.id）');
            $table->unsignedBigInteger('role_id')->comment('角色ID');
            $table->timestamps();

            $table->foreign('account_id')
                ->references('id')
                ->on('gpa_account')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->foreign('role_id')
                ->references('role_id')
                ->on('gpa_role')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            // 同一账号不能重复分配同一角色
            // 注意：应用层需要校验用户账号类型与角色账号类型一致
            // admin用户只能分配account_type='admin'的角色
            // user用户只能分配account_type='user'的角色
            $table->unique(['account_id', 'role_id'], 'uk_user_role');
            $table->index('account_id');
            $table->index('role_id');
            $table->comment('用户角色关联表');
        });

        // 角色菜单关联表
        Schema::create('gpa_role_menu', function (Blueprint $table) {
            $table->id()->comment('关联ID');
            $table->unsignedBigInteger('role_id')->comment('角色ID');
            $table->unsignedBigInteger('menu_id')->comment('菜单ID');
            $table->unsignedBigInteger('target_module_id')->nullable()->comment('目标模块ID（菜单移动到的新模块，为null表示未移动）');
            $table->timestamps();

            $table->foreign('role_id')
                ->references('role_id')
                ->on('gpa_role')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->foreign('menu_id')
                ->references('menu_id')
                ->on('gpa_menu')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            // 注意：应用层需要校验角色账号类型与菜单账号类型一致
            // admin角色只能关联account_type='admin'的菜单
            // user角色只能关联account_type='user'的菜单
            $table->unique(['role_id', 'menu_id'], 'uk_role_menu');
            $table->index('role_id');
            $table->index('menu_id');
            $table->index('target_module_id');
            $table->comment('角色菜单关联表');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gpa_role_menu');
        Schema::dropIfExists('gpa_user_role');
        Schema::dropIfExists('gpa_menu');
        Schema::dropIfExists('gpa_role');
    }
};
