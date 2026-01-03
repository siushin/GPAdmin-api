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
        Schema::create('gpa_admin', function (Blueprint $table) {
            $table->id()->comment('管理员ID');
            $table->unsignedBigInteger('account_id')->unique()->comment('账号ID');
            $table->unsignedBigInteger('company_id')->nullable()->comment('所属公司ID');
            $table->tinyInteger('is_super')->default(0)->comment('是否超级管理员：1是，0否');
            $table->timestamps();

            $table->foreign('account_id')
                ->references('id')
                ->on('gpa_account')
                ->onDelete('cascade');

            // 关联公司表
            $table->foreign('company_id')
                ->references('company_id')
                ->on('gpa_company')
                ->onDelete('set null');

            $table->index('company_id');
            $table->comment('管理员表');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gpa_admin');
    }
};

