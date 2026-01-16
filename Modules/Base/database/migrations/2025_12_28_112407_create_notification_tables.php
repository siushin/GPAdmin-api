<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Base\Enums\NotificationReadTypeEnum;
use Modules\Base\Enums\NotificationTypeEnum;
use Modules\Base\Enums\TargetPlatformEnum;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 构造枚举备注
        $targetPlatformComment = buildEnumComment(TargetPlatformEnum::cases(), '目标平台');
        $notificationTypeComment = buildEnumComment(NotificationTypeEnum::cases(), '通知类型');
        $readTypeComment = buildEnumComment(NotificationReadTypeEnum::cases(), '通知查看类型');

        // 系统通知表
        Schema::create('gpa_system_notifications', function (Blueprint $table) use ($targetPlatformComment, $notificationTypeComment) {
            $table->id()->comment('系统通知ID');
            $table->string('title', 200)->comment('通知标题');
            $table->text('content')->comment('通知内容');
            $table->string('target_platform', 50)->default('all')->comment($targetPlatformComment);
            $table->string('type', 50)->nullable()->comment($notificationTypeComment);
            $table->timestamp('start_time')->nullable()->comment('开始时间');
            $table->timestamp('end_time')->nullable()->comment('结束时间');
            $table->tinyInteger('status')->default(1)->comment('状态：1启用，0禁用');
            $table->unsignedBigInteger('account_id')->comment('创建人账号ID');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('account_id')
                ->references('id')
                ->on('gpa_account')
                ->onDelete('cascade');

            $table->index('status');
            $table->index('type');
            $table->index('account_id');
            $table->index('start_time');
            $table->index('end_time');
            $table->index('created_at');
            $table->index(['status', 'created_at']);
            $table->index(['status', 'start_time', 'end_time']);
            $table->comment('系统通知表');
        });

        // 站内信表
        Schema::create('gpa_messages', function (Blueprint $table) use ($targetPlatformComment) {
            $table->id()->comment('站内信ID');
            $table->unsignedBigInteger('sender_id')->comment('发送者账号ID');
            $table->unsignedBigInteger('receiver_id')->comment('接收者账号ID');
            $table->string('title', 200)->comment('站内信标题');
            $table->text('content')->comment('站内信内容');
            $table->string('target_platform', 50)->default('all')->comment($targetPlatformComment);
            $table->tinyInteger('status')->default(0)->comment('状态：0未读，1已读');
            $table->unsignedBigInteger('account_id')->comment('创建人账号ID（发送者）');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('sender_id')
                ->references('id')
                ->on('gpa_account')
                ->onDelete('cascade');

            $table->foreign('receiver_id')
                ->references('id')
                ->on('gpa_account')
                ->onDelete('cascade');

            $table->foreign('account_id')
                ->references('id')
                ->on('gpa_account')
                ->onDelete('cascade');

            $table->index('sender_id');
            $table->index('receiver_id');
            $table->index('status');
            $table->index('created_at');
            $table->index(['receiver_id', 'status']);
            $table->index(['sender_id', 'created_at']);
            $table->index(['receiver_id', 'created_at']);
            $table->comment('站内信表');
        });

        // 公告管理表
        Schema::create('gpa_announcements', function (Blueprint $table) use ($targetPlatformComment) {
            $table->id()->comment('公告ID');
            $table->string('title', 200)->comment('公告标题');
            $table->text('content')->comment('公告内容');
            $table->string('target_platform', 50)->default('all')->comment($targetPlatformComment);
            $table->string('position', 100)->default('home')->comment('显示位置（如：home首页）');
            $table->timestamp('start_time')->nullable()->comment('开始时间');
            $table->timestamp('end_time')->nullable()->comment('结束时间');
            $table->tinyInteger('status')->default(1)->comment('状态：1启用，0禁用');
            $table->unsignedBigInteger('account_id')->comment('创建人账号ID');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('account_id')
                ->references('id')
                ->on('gpa_account')
                ->onDelete('cascade');

            $table->index('status');
            $table->index('position');
            $table->index('account_id');
            $table->index('start_time');
            $table->index('end_time');
            $table->index('created_at');
            $table->index(['status', 'position']);
            $table->index(['status', 'start_time', 'end_time']);
            $table->comment('公告管理表');
        });

        // 通知查看记录表（合并表：系统通知、站内信、公告）
        Schema::create('gpa_notification_reads', function (Blueprint $table) use ($readTypeComment) {
            $table->id()->comment('查看记录ID');
            $table->string('read_type', 50)->comment($readTypeComment);
            $table->unsignedBigInteger('target_id')->comment('目标ID（根据read_type指向不同的表：系统通知ID、站内信ID、公告ID）');
            $table->unsignedBigInteger('account_id')->nullable()->comment('查看人账号ID（可为空，表示未登录用户）');
            $table->timestamp('read_at')->useCurrent()->comment('查看时间');
            $table->ipAddress('ip_address')->nullable()->comment('IP地址');
            $table->string('ip_location')->nullable()->comment('IP归属地');

            $table->foreign('account_id')
                ->references('id')
                ->on('gpa_account')
                ->onDelete('cascade');

            $table->unique(['read_type', 'target_id', 'account_id'], 'read_type_target_account_unique');
            $table->index('read_type');
            $table->index('target_id');
            $table->index('account_id');
            $table->index('read_at');
            $table->index('ip_address');
            $table->index(['read_type', 'target_id']);
            $table->index(['account_id', 'read_at']);
            $table->index(['read_type', 'account_id', 'read_at']);
            $table->comment('通知查看记录表（系统通知、站内信、公告）');
        });

        // 站内信回复表
        Schema::create('gpa_message_replies', function (Blueprint $table) {
            $table->id()->comment('回复ID');
            $table->unsignedBigInteger('message_id')->comment('站内信ID');
            $table->unsignedBigInteger('sender_id')->comment('回复者账号ID');
            $table->text('content')->comment('回复内容');
            $table->unsignedBigInteger('account_id')->comment('创建人账号ID（回复者）');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('message_id')
                ->references('id')
                ->on('gpa_messages')
                ->onDelete('cascade');

            $table->foreign('sender_id')
                ->references('id')
                ->on('gpa_account')
                ->onDelete('cascade');

            $table->foreign('account_id')
                ->references('id')
                ->on('gpa_account')
                ->onDelete('cascade');

            $table->index('message_id');
            $table->index('sender_id');
            $table->index('created_at');
            $table->index(['message_id', 'created_at']);
            $table->comment('站内信回复表');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gpa_message_replies');
        Schema::dropIfExists('gpa_notification_reads');
        Schema::dropIfExists('gpa_announcements');
        Schema::dropIfExists('gpa_messages');
        Schema::dropIfExists('gpa_system_notifications');
    }
};

