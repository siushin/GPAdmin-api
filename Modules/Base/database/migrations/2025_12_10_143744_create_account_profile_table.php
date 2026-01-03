<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Base\Enums\IdCardVerifyStatusEnum;
use Modules\Base\Enums\VerificationMethodEnum;
use Siushin\LaravelTool\Enums\GenderTypeEnum;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $genderComment = buildEnumComment(GenderTypeEnum::cases(), '性别');
        $verificationMethodComment = buildEnumComment(VerificationMethodEnum::cases(), '认证方式');
        $idCardVerifyStatusComment = buildEnumComment(IdCardVerifyStatusEnum::cases(), '身份证核验状态');

        Schema::create('gpa_account_profile', function (Blueprint $table) use ($genderComment, $verificationMethodComment, $idCardVerifyStatusComment) {
            $table->id()->comment('账号资料ID');
            $table->unsignedBigInteger('account_id')->unique()->comment('账号ID');
            $table->string('nickname')->nullable()->comment('昵称');
            $table->string('gender', 10)->default(GenderTypeEnum::male->name)->comment($genderComment);
            $table->string('avatar')->nullable()->comment('头像');
            $table->string('verification_method', 20)->nullable()->comment($verificationMethodComment);
            $table->timestamp('verified_at')->nullable()->comment('认证时间');
            $table->string('id_card_no', 18)->nullable()->comment('身份证号码');
            $table->string('id_card_name')->nullable()->comment('身份证姓名');
            $table->date('id_card_valid_from')->nullable()->comment('身份证有效期起');
            $table->date('id_card_valid_to')->nullable()->comment('身份证有效期至');
            $table->string('id_card_issuer')->nullable()->comment('身份证签发机关');
            $table->string('id_card_front_url')->nullable()->comment('身份证正面照');
            $table->string('id_card_back_url')->nullable()->comment('身份证反面照');
            $table->tinyInteger('id_card_verify_status')
                ->nullable()
                ->default(IdCardVerifyStatusEnum::NOT_VERIFIED->value)
                ->comment($idCardVerifyStatusComment);
            $table->timestamps();

            $table->foreign('account_id')->references('id')->on('gpa_account')->onDelete('cascade');
            $table->comment('账号资料表');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gpa_account_profile');
    }
};
