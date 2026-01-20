<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        /**
         * 账号模块关联表
         *
         * 关系说明：
         * - 账号 n:n 模块（通过 account_id 和 module_id 关联）
         * - 用于管理账号可访问的模块权限
         */
        Schema::create('gpa_account_module', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary()->comment('关联ID（雪花ID）');
            $table->unsignedBigInteger('account_id')->comment('账号ID');
            $table->unsignedBigInteger('module_id')->comment('模块ID');
            $table->unsignedInteger('sort')->default(0)->comment('排序');
            $table->timestamps();

            // 关联账号表
            $table->foreign('account_id')
                ->references('id')
                ->on('gpa_account')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            // 关联模块表
            $table->foreign('module_id')
                ->references('module_id')
                ->on('gpa_modules')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->index('account_id');
            $table->index('module_id');

            // 同一账号不能重复关联同一模块
            $table->unique(['account_id', 'module_id'], 'unique_account_module');

            $table->comment('账号模块关联表');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gpa_account_module');
    }
};

