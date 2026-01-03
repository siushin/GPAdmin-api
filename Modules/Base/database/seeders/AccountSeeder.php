<?php

namespace Modules\Base\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Modules\Base\Enums\AccountTypeEnum;
use Modules\Base\Enums\IdCardVerifyStatusEnum;
use Modules\Base\Enums\VerificationMethodEnum;
use Modules\Base\Models\Account;
use Modules\Base\Models\AccountProfile;
use Modules\Base\Models\AccountSocial;
use Modules\Base\Models\Admin;
use Modules\Base\Models\Company;
use Modules\Base\Models\User;
use Siushin\LaravelTool\Enums\GenderTypeEnum;
use Siushin\LaravelTool\Enums\SocialTypeEnum;

/**
 * 数据填充：账号
 */
class AccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 创建超级管理员账号
        $this->createSuperAdminAccount();
        // 创建用户账号
        $this->createUserAccount();
    }

    /**
     * 创建超级管理员账号
     */
    protected function createSuperAdminAccount(): void
    {
        // 创建管理员账号
        $account = Account::query()->create([
            'id'           => generateId(),
            'username'     => env('APP_ADMIN', 'admin'),
            'password'     => Hash::make(env('APP_ADMIN_PASSWORD', 'admin')),
            'status'       => 1,
            'account_type' => AccountTypeEnum::Admin,
        ]);

        // 创建账号资料
        AccountProfile::query()->create([
            'id'                    => generateId(),
            'account_id'            => $account->id,
            'nickname'              => env('APP_ADMIN_NAME', '超级管理员'),
            'gender'                => GenderTypeEnum::male->name,
            'avatar'                => null,
            'verification_method'   => VerificationMethodEnum::Manual->value,
            'verified_at'           => now(),
            'id_card_verify_status' => IdCardVerifyStatusEnum::VERIFIED->value,
        ]);

        // 创建或更新手机号社交账号信息
        $adminPhone = env('APP_ADMIN_PHONE');
        if ($adminPhone) {
            $this->createOrUpdateAccountSocial(
                $account->id,
                SocialTypeEnum::Phone->value,
                $adminPhone,
                true
            );
        }

        // 创建或更新邮箱社交账号信息
        $adminEmail = env('APP_ADMIN_EMAIL');
        if ($adminEmail) {
            $this->createOrUpdateAccountSocial(
                $account->id,
                SocialTypeEnum::Email->value,
                $adminEmail,
                true
            );
        }

        // 获取公司ID（绑定到默认公司）
        $companyId = null;
        $companyName = env('APP_COMPANY', '默认公司');
        $company = Company::query()->where('company_name', $companyName)->first();
        if ($company) {
            $companyId = $company->company_id;
        }

        // 创建管理员信息（标记为超级管理员）
        Admin::query()->create([
            'id'         => generateId(),
            'account_id' => $account->id,
            'company_id' => $companyId,
            'is_super'   => 1,
        ]);
    }

    /**
     * 创建用户账号
     */
    protected function createUserAccount(): void
    {
        // 创建用户账号
        $account = Account::query()->create([
            'id'           => generateId(),
            'username'     => env('APP_USER', 'user'),
            'password'     => Hash::make(env('APP_USER_PASSWORD', 'user')),
            'status'       => 1,
            'account_type' => AccountTypeEnum::User,
        ]);

        // 创建账号资料
        AccountProfile::query()->create([
            'id'                    => generateId(),
            'account_id'            => $account->id,
            'nickname'              => env('APP_USER_NAME', '用户'),
            'gender'                => GenderTypeEnum::male->name,
            'avatar'                => null,
            'id_card_verify_status' => IdCardVerifyStatusEnum::NOT_VERIFIED->value,
        ]);

        // 创建用户信息
        User::query()->create([
            'id'         => generateId(),
            'account_id' => $account->id,
        ]);
    }

    /**
     * 创建或更新社交账号信息
     *
     * @param string $accountId     账号ID
     * @param string $socialType    社交类型
     * @param string $socialAccount 社交账号
     * @param bool   $isVerified    是否已验证
     */
    protected function createOrUpdateAccountSocial(
        string $accountId,
        string $socialType,
        string $socialAccount,
        bool   $isVerified = false
    ): void
    {
        $social = AccountSocial::query()
            ->where('account_id', $accountId)
            ->where('social_type', $socialType)
            ->first();

        if ($social) {
            $social->update([
                'social_account' => $socialAccount,
                'is_verified'    => $isVerified,
                'verified_at'    => $isVerified ? now() : null,
            ]);
        } else {
            AccountSocial::query()->create([
                'id'             => generateId(),
                'account_id'     => $accountId,
                'social_type'    => $socialType,
                'social_account' => $socialAccount,
                'social_name'    => null,
                'avatar'         => null,
                'is_verified'    => $isVerified,
                'verified_at'    => $isVerified ? now() : null,
            ]);
        }
    }
}
