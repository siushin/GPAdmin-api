<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Base\Enums\AccountStatusEnum;
use Modules\Base\Enums\AccountTypeEnum;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $accountTypeComment = buildEnumComment(AccountTypeEnum::cases(), '账号类型');
        $statusComment = buildEnumComment(AccountStatusEnum::cases(), '账号状态');

        Schema::create('gpa_account', function (Blueprint $table) use ($accountTypeComment, $statusComment) {
            $table->id()->comment('账号ID');
            $table->string('account_type', 20)
                ->default(AccountTypeEnum::User->value)
                ->comment($accountTypeComment);
            $table->string('username', 50)->unique()->comment('用户名');
            $table->string('password')->comment('密码');
            $table->tinyInteger('status')->default(1)->comment($statusComment);
            $table->string('last_login_ip', 50)->nullable()->comment('最后登录IP');
            $table->timestamp('last_login_time')->nullable()->comment('最后登录时间');
            $table->timestamps();
            $table->softDeletes()->comment('软删除时间');

            $table->index('status');
            $table->index('account_type');
            $table->comment('账号表');
        });

        Schema::create('sys_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
            $table->comment('重置密码-授权码表');
        });

        Schema::create('sys_sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('account_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
            $table->comment('session会话表');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gpa_account');
        Schema::dropIfExists('sys_password_reset_tokens');
        Schema::dropIfExists('sys_sessions');
    }
};
