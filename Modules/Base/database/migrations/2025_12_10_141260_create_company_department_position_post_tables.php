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
         * 关系图说明：
         *
         * 公司 (Company)
         * │
         * ├── 一级部门 (Department)
         * │       │
         * │       ├── 二级部门 (Sub-department)
         * │       │
         * └── 另一个一级部门
         * └── ...
         */

        /**
         * 实体关系说明：
         *
         * 公司 1:n 部门
         */

        // 公司表
        Schema::create('gpa_company', function (Blueprint $table) {
            $table->id('company_id')->comment('公司ID');
            $table->string('company_name')->comment('公司名称');
            $table->string('company_code')->nullable()->comment('公司编码');
            $table->string('company_credit_code', 18)->comment('统一社会信用代码（18位）');
            $table->string('legal_person')->nullable()->comment('法人代表');
            $table->string('contact_phone')->nullable()->comment('联系电话');
            $table->string('contact_email')->nullable()->comment('联系邮箱');
            $table->string('company_address')->nullable()->comment('公司地址');
            $table->text('company_desc')->nullable()->comment('公司描述');
            $table->tinyInteger('status')->default(1)->comment('状态：1正常，0禁用');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_name'], 'unique_company_name');
            $table->unique(['company_code'], 'unique_company_code');
            $table->unique(['company_credit_code'], 'unique_company_credit_code');

            $table->comment('公司表');
        });

        // 部门表
        Schema::create('gpa_department', function (Blueprint $table) {
            $table->id('department_id')->comment('部门ID');
            $table->unsignedBigInteger('company_id')->nullable()->comment('所属公司ID');
            $table->string('department_name')->comment('部门名称');
            $table->string('department_code')->nullable()->comment('部门编码');
            $table->string('manager_id')->nullable()->comment('部门负责人ID');
            $table->text('description')->nullable()->comment('部门描述');
            $table->unsignedBigInteger('parent_id')->default(0)->comment('上级部门ID');
            $table->string('full_parent_id')->nullable()->comment('完整上级部门ID路径');
            $table->tinyInteger('status')->default(1)->comment('状态：1正常，0禁用');
            $table->integer('sort')->default(0)->comment('排序');
            $table->timestamps();
            $table->softDeletes();

            // 关联公司表
            $table->foreign('company_id')
                ->references('company_id')
                ->on('gpa_company')
                ->onDelete('set null');

            $table->index('company_id');
            $table->index('department_code');
            $table->index('parent_id');
            // 同一公司、同一父级下不能有同名部门
            $table->unique(['company_id', 'parent_id', 'department_name']);
            // 部门编码在公司内唯一（如果提供）
            $table->unique(['company_id', 'department_code']);

            $table->comment('部门表');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gpa_department');
        Schema::dropIfExists('gpa_company');
    }
};

