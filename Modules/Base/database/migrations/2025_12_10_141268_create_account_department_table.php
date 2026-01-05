<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Base\Enums\AccountTypeEnum;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $accountTypeComment = buildEnumComment(AccountTypeEnum::cases(), '账号类型');

        /**
         * 账号部门关联表（统一表）
         *
         * 关系说明：
         * - 账号 n:n 部门（通过 account_id 关联，支持管理员和用户）
         * - 通过 account_type 字段区分账号类型，确保数据安全隔离
         */

        Schema::create('gpa_account_department', function (Blueprint $table) use ($accountTypeComment) {
            $table->id()->comment('关联ID');
            $table->unsignedBigInteger('account_id')->comment('账号ID');
            $table->string('account_type', 20)->comment($accountTypeComment);
            $table->unsignedBigInteger('department_id')->comment('部门ID');
            $table->integer('sort')->default(0)->comment('排序');
            $table->timestamps();

            // 关联账号表
            $table->foreign('account_id')
                ->references('id')
                ->on('gpa_account')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            // 关联部门表
            $table->foreign('department_id')
                ->references('department_id')
                ->on('gpa_department')
                ->onDelete('cascade');

            $table->index('account_id');
            $table->index('account_type');
            $table->index('department_id');

            // 同一账号不能重复关联同一部门
            $table->unique(['account_id', 'department_id'], 'unique_account_department');

            // 复合索引：优化按账号类型和账号ID查询的性能
            $table->index(['account_type', 'account_id'], 'idx_account_type_id');

            // 复合索引：优化按账号类型和部门ID查询的性能
            $table->index(['account_type', 'department_id'], 'idx_account_type_department');

            $table->comment('账号部门关联表');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gpa_account_department');
    }
};

