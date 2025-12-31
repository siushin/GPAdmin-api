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
         * │       │       │
         * │       │       ├── 职位 A (Position)
         * │       │       │       ├── 岗位 1 (Post)
         * │       │       │       ├── 岗位 2 (Post)
         * │       │       │       └── 岗位 3 (Post)
         * │       │       │
         * │       │       └── 职位 B (Position)
         * │       │               ├── 岗位 4 (Post)
         * │       │               └── 岗位 5 (Post)
         * │       │
         * │       └── 职位 C (Position)
         * │
         * └── 另一个一级部门
         * └── ...
         */

        /**
         * 实体关系说明：
         *
         * 公司 1:n 部门
         * 部门 1:n 职位（一个部门可以有多个职位级别）
         * 职位 1:n 岗位（一个职位级别下可以有多个具体岗位）
         * 岗位 n:1 部门（岗位属于某个部门）
         * 岗位 n:1 职位（岗位对应某个职位级别）
         */

        // 公司表
        Schema::create('gpa_company', function (Blueprint $table) {
            $table->id('company_id')->comment('公司ID');
            $table->unsignedBigInteger('organization_id')->nullable()->comment('关联组织架构ID');
            $table->string('company_name')->comment('公司名称');
            $table->string('company_code')->nullable()->comment('公司编码');
            $table->string('company_credit_code', 18)->comment('统一社会信用代码（18位）');
            $table->string('legal_person')->nullable()->comment('法人代表');
            $table->string('contact_phone')->nullable()->comment('联系电话');
            $table->string('contact_email')->nullable()->comment('联系邮箱');
            $table->string('address')->nullable()->comment('公司地址');
            $table->text('company_desc')->nullable()->comment('公司描述');
            $table->tinyInteger('status')->default(1)->comment('状态：1正常，0禁用');
            $table->timestamps();
            $table->softDeletes();

            // 关联组织架构表
            $table->foreign('organization_id')
                ->references('organization_id')
                ->on('gpa_organization')
                ->onDelete('set null');

            $table->index('organization_id');
            $table->unique(['company_name'], 'unique_company_name');
            $table->unique(['company_code'], 'unique_company_code');
            $table->unique(['company_credit_code'], 'unique_company_credit_code');

            $table->comment('公司表');
        });

        // 部门表
        Schema::create('gpa_department', function (Blueprint $table) {
            $table->id('department_id')->comment('部门ID');
            $table->unsignedBigInteger('company_id')->nullable()->comment('所属公司ID');
            $table->string('department_code')->nullable()->comment('部门编码');
            $table->string('department_name')->comment('部门名称');
            $table->string('manager_id')->nullable()->comment('部门负责人ID');
            $table->text('description')->nullable()->comment('部门描述');
            $table->unsignedBigInteger('parent_id')->default(0)->comment('上级部门ID');
            $table->string('full_parent_id')->nullable()->comment('完整上级部门ID路径');
            $table->tinyInteger('status')->default(1)->comment('状态：1正常，0禁用');
            $table->integer('sort_order')->default(0)->comment('排序');
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

        // 职位表
        // 示例1：高级工程师、经理、总监、专员
        // 示例2：P5 初级工程师、P6 中级工程师、P7 高级工程师、P8 专家、M1 经理、M2 总监
        Schema::create('gpa_position', function (Blueprint $table) {
            $table->id('position_id')->comment('职位ID');
            $table->string('position_name')->comment('职位名称');
            $table->string('position_code')->nullable()->comment('职位编码');
            $table->unsignedBigInteger('department_id')->nullable()->comment('所属部门ID');
            $table->text('job_description')->nullable()->comment('职位描述');
            $table->text('job_requirements')->nullable()->comment('任职要求');
            $table->tinyInteger('status')->default(1)->comment('状态：1正常，0禁用');
            $table->integer('sort_order')->default(0)->comment('排序');
            $table->timestamps();
            $table->softDeletes();

            // 关联部门表
            $table->foreign('department_id')
                ->references('department_id')
                ->on('gpa_department')
                ->onDelete('set null');

            $table->index('department_id');
            $table->index('position_code');
            // 同一部门下不能有同名职位
            $table->unique(['department_id', 'position_name'], 'unique_position_department_name');
            // 职位编码全局唯一（如果提供）
            $table->unique(['position_code'], 'unique_position_code');

            $table->comment('职位表（部门内的具体岗位）');
        });

        // 岗位表
        // 示例：Java开发工程师、销售专员、财务主管
        Schema::create('gpa_post', function (Blueprint $table) {
            $table->id('post_id')->comment('岗位ID');
            $table->string('post_name')->comment('岗位名称');
            $table->string('post_code')->nullable()->comment('岗位编码');
            $table->unsignedBigInteger('position_id')->nullable()->comment('所属职位ID');
            $table->unsignedBigInteger('department_id')->nullable()->comment('所属部门ID');
            $table->text('post_description')->nullable()->comment('岗位描述');
            $table->text('post_requirements')->nullable()->comment('岗位要求');
            $table->tinyInteger('status')->default(1)->comment('状态：1正常，0禁用');
            $table->integer('sort_order')->default(0)->comment('排序');
            $table->timestamps();
            $table->softDeletes();

            // 关联职位表
            $table->foreign('position_id')
                ->references('position_id')
                ->on('gpa_position')
                ->onDelete('set null');

            // 关联部门表
            $table->foreign('department_id')
                ->references('department_id')
                ->on('gpa_department')
                ->onDelete('set null');

            $table->index('position_id');
            $table->index('department_id');
            $table->index('post_code');
            // 同一职位下不能有同名岗位
            $table->unique(['position_id', 'post_name'], 'unique_post_position_name');
            // 岗位编码全局唯一（如果提供）
            $table->unique(['post_code'], 'unique_post_code');

            $table->comment('岗位表（具体工作职责）');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gpa_post');
        Schema::dropIfExists('gpa_position');
        Schema::dropIfExists('gpa_department');
        Schema::dropIfExists('gpa_company');
    }
};

