<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Base\Enums\ModulePullTypeEnum;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $modulePullTypeComment = buildEnumComment(ModulePullTypeEnum::cases(), '模块拉取类型');

        // 模块表
        Schema::create('gpa_modules', function (Blueprint $table) use ($modulePullTypeComment) {
            $table->id('module_id')->comment('模块ID');
            $table->string('module_name', 50)->unique()->comment('模块名称/标识（对应module.json中的name，如：Base, Sms）');
            $table->string('module_alias', 100)->nullable()->comment('模块别名（对应module.json中的alias，如：base, sms）');
            $table->string('module_title', 100)->nullable()->comment('模块标题（对应module.json中的title，如：基础服务、短信服务）');
            $table->text('module_desc')->nullable()->comment('模块描述（对应module.json中的description）');
            $table->string('module_icon', 100)->nullable()->comment('模块图标名称');
            $table->string('module_version', 20)->nullable()->comment('模块版本号');
            $table->unsignedInteger('module_priority')->default(0)->comment('优先级（数字越大优先级越高）');
            $table->string('module_source', 50)->default('official')->comment('模块来源: official官方, third_party第三方, custom自定义');
            $table->tinyInteger('module_status')->default(1)->comment('状态: 1启用, 0禁用');
            $table->tinyInteger('module_is_core')->default(0)->comment('是否为核心模块: 1是（不可删除/禁用）, 0否');
            $table->tinyInteger('module_is_installed')->default(1)->comment('是否已安装: 1是, 0否');
            $table->timestamp('module_installed_at')->nullable()->comment('安装时间');
            $table->string('module_author', 100)->nullable()->comment('模块作者');
            $table->string('module_author_email', 100)->nullable()->comment('作者邮箱');
            $table->string('module_homepage', 255)->nullable()->comment('模块主页/文档链接');
            $table->json('module_keywords')->nullable()->comment('关键词（对应module.json中的keywords）');
            $table->json('module_providers')->nullable()->comment('服务提供者（对应module.json中的providers）');
            $table->json('module_dependencies')->nullable()->comment('模块依赖（存储依赖的其他模块标识）');
            $table->string('module_pull_type', 20)->nullable()->comment($modulePullTypeComment);
            $table->string('module_pull_url', 500)->nullable()->unique()->comment('模块拉取地址（Git仓库地址或下载URL）');
            $table->unsignedBigInteger('uploader_id')->nullable()->comment('上传人ID（关联gpa_account.id）');
            $table->timestamps();
            $table->softDeletes()->comment('软删除时间');

            $table->foreign('uploader_id')
                ->references('id')
                ->on('gpa_account')
                ->onDelete('set null')
                ->onUpdate('cascade');

            $table->index('module_name');
            $table->index('module_status');
            $table->index('module_is_core');
            $table->index('module_is_installed');
            $table->index('module_source');
            $table->index('module_priority');
            $table->index('uploader_id');
            $table->comment('模块管理表');
        });

        // 模块菜单关联表
        Schema::create('gpa_module_menu', function (Blueprint $table) {
            $table->id()->comment('关联ID');
            $table->unsignedBigInteger('module_id')->comment('模块ID');
            $table->unsignedBigInteger('menu_id')->comment('菜单ID');
            $table->unsignedBigInteger('original_module_id')->nullable()->comment('原始模块ID（用于还原，为null表示当前模块就是原始模块）');
            $table->tinyInteger('is_root')->default(0)->comment('是否为菜单树根节点: 1是, 0否（用于标识整个菜单模块的入口）');
            $table->timestamp('moved_at')->nullable()->comment('移动时间（为null表示未移动过）');
            $table->unsignedBigInteger('moved_by')->nullable()->comment('移动操作人ID');
            $table->timestamps();

            $table->foreign('module_id')
                ->references('module_id')
                ->on('gpa_modules')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->foreign('menu_id')
                ->references('menu_id')
                ->on('gpa_menu')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->foreign('original_module_id')
                ->references('module_id')
                ->on('gpa_modules')
                ->onDelete('set null')
                ->onUpdate('cascade');

            $table->foreign('moved_by')
                ->references('id')
                ->on('gpa_account')
                ->onDelete('set null')
                ->onUpdate('cascade');

            // 同一模块不能重复关联同一菜单
            $table->unique(['module_id', 'menu_id'], 'uk_module_menu');
            $table->index('module_id');
            $table->index('menu_id');
            $table->index('original_module_id');
            $table->index('is_root');
            $table->index('moved_at');
            $table->comment('模块菜单关联表');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gpa_module_menu');
        Schema::dropIfExists('gpa_modules');
    }
};
