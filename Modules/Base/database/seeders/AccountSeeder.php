<?php

namespace Modules\Base\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Modules\Base\Enums\AccountTypeEnum;
use Modules\Base\Models\Account;
use Modules\Base\Models\AccountProfile;
use Modules\Base\Models\AccountSocial;
use Modules\Base\Models\Admin;
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
        // 生成手机号的辅助函数
        $generatePhoneNumber = function () {
            $firstChar = ['13', '14', '15', '16', '17', '18', '19'];
            $firstChar = $firstChar[array_rand($firstChar)];
            $randomNumber = mt_rand(1000000000, 9999999999);
            $randomNumber = str_pad(substr($randomNumber, -9), 9, '0', STR_PAD_LEFT);
            return $firstChar . $randomNumber;
        };

        // 生成随机姓名（中文名或英文名）
        $generateRandomName = function () {
            if (fake()->boolean()) {
                // 生成英文名
                return fake()->name();
            } else {
                // 生成中文名
                $surnames = ['王', '李', '张', '刘', '陈', '杨', '赵', '黄', '周', '吴', '徐', '孙', '胡', '朱', '高', '林', '何', '郭', '马', '罗', '梁', '宋', '郑', '谢', '韩', '唐', '冯', '于', '董', '萧'];
                $givenNames = ['伟', '芳', '娜', '秀英', '敏', '静', '丽', '强', '磊', '军', '洋', '勇', '艳', '杰', '娟', '涛', '明', '超', '秀兰', '霞', '平', '刚', '桂英', '建华', '文', '华', '建国', '红', '志强', '秀', '敏', '静', '丽', '强', '磊', '军', '洋', '勇', '艳', '杰', '娟', '涛', '明', '超', '秀兰', '霞', '平', '刚', '桂英'];
                return $surnames[array_rand($surnames)] . $givenNames[array_rand($givenNames)];
            }
        };

        // 创建账号
        $accounts = Account::factory(100)->create();

        // 为每个账号随机设置状态值（-1：待审核，0：禁用，1：正常）
        foreach ($accounts as $account) {
            $account->update([
                'status' => fake()->randomElement([-1, 0, 1]),
            ]);
        }

        // 为每个账号创建关联数据
        foreach ($accounts as $account) {
            // 创建账号资料
            AccountProfile::query()->create([
                'id'         => generateId(),
                'account_id' => $account->id,
                'nickname'   => $generateRandomName(),
                'gender'     => fake()->randomElement(array_column(GenderTypeEnum::cases(), 'name')),
                'avatar'     => null,
            ]);

            // 创建社交网络数据（手机号）
            AccountSocial::query()->create([
                'id'             => generateId(),
                'account_id'     => $account->id,
                'social_type'    => SocialTypeEnum::Phone->value,
                'social_account' => $generatePhoneNumber(),
                'social_name'    => null,
                'avatar'         => null,
                'is_verified'    => fake()->boolean(30), // 30% 概率已验证
                'verified_at'    => fake()->boolean(30) ? now() : null,
            ]);

            // 随机决定是否创建邮箱数据
            if (fake()->boolean()) {
                AccountSocial::query()->create([
                    'id'             => generateId(),
                    'account_id'     => $account->id,
                    'social_type'    => SocialTypeEnum::Email->value,
                    'social_account' => fake()->unique()->safeEmail(),
                    'social_name'    => null,
                    'avatar'         => null,
                    'is_verified'    => fake()->boolean(50), // 50% 概率已验证
                    'verified_at'    => fake()->boolean(50) ? now() : null,
                ]);
            }

            // 根据账号类型创建对应的附属信息
            if ($account->account_type === AccountTypeEnum::Admin) {
                Admin::query()->create([
                    'id'            => generateId(),
                    'account_id'    => $account->id,
                    'company_id'    => null,
                    'department_id' => null,
                ]);
            } elseif ($account->account_type === AccountTypeEnum::User) {
                User::query()->create([
                    'id'         => generateId(),
                    'account_id' => $account->id,
                ]);
            }
        }

        // 初始化超级管理员账号（按创建时间取第一条记录，不管是什么账号类型）
        $adminAccount = Account::query()
            ->orderBy('created_at')
            ->first();

        if ($adminAccount) {
            $wasCustomer = $adminAccount->account_type === AccountTypeEnum::User;

            // 如果原本是客户类型，删除对应的客户表脏数据
            if ($wasCustomer) {
                User::query()->where('account_id', $adminAccount->id)->delete();
            }

            // 更新为超级管理员账号
            $adminAccount->update([
                'username'     => env('APP_ADMIN', 'admin'),
                'password'     => Hash::make(env('APP_ADMIN_PASSWORD', 'admin')),
                'status'       => 1,
                'account_type' => AccountTypeEnum::Admin,
            ]);

            // 更新管理员资料
            AccountProfile::query()->updateOrCreate(
                ['account_id' => $adminAccount->id],
                [
                    'nickname' => env('APP_ADMIN_NAME', '超级管理员'),
                    'gender'   => GenderTypeEnum::male->name,
                ]
            );

            // 创建或更新手机号社交账号信息
            $adminPhone = env('APP_ADMIN_PHONE');
            if ($adminPhone) {
                $phoneSocial = AccountSocial::query()
                    ->where('account_id', $adminAccount->id)
                    ->where('social_type', SocialTypeEnum::Phone->value)
                    ->first();

                if ($phoneSocial) {
                    $phoneSocial->update([
                        'social_account' => $adminPhone,
                        'is_verified'    => true,
                        'verified_at'    => now(),
                    ]);
                } else {
                    AccountSocial::query()->create([
                        'id'             => generateId(),
                        'account_id'     => $adminAccount->id,
                        'social_type'    => SocialTypeEnum::Phone->value,
                        'social_account' => $adminPhone,
                        'social_name'    => null,
                        'avatar'         => null,
                        'is_verified'    => true,
                        'verified_at'    => now(),
                    ]);
                }
            }

            // 创建或更新邮箱社交账号信息
            $adminEmail = env('APP_EMAIL');
            if ($adminEmail) {
                $emailSocial = AccountSocial::query()
                    ->where('account_id', $adminAccount->id)
                    ->where('social_type', SocialTypeEnum::Email->value)
                    ->first();

                if ($emailSocial) {
                    $emailSocial->update([
                        'social_account' => $adminEmail,
                        'is_verified'    => true,
                        'verified_at'    => now(),
                    ]);
                } else {
                    AccountSocial::query()->create([
                        'id'             => generateId(),
                        'account_id'     => $adminAccount->id,
                        'social_type'    => SocialTypeEnum::Email->value,
                        'social_account' => $adminEmail,
                        'social_name'    => null,
                        'avatar'         => null,
                        'is_verified'    => true,
                        'verified_at'    => now(),
                    ]);
                }
            }

            // 如果原本是客户类型，需要创建管理员信息（原本是管理员类型的话，前面循环中已创建）
            if ($wasCustomer) {
                // 新创建的管理员标记为超级管理员
                Admin::query()->create([
                    'id'            => generateId(),
                    'account_id'    => $adminAccount->id,
                    'company_id'    => null,
                    'department_id' => null,
                    'is_super'      => 1,
                ]);
            } else {
                // 原本就是管理员类型，直接将对应管理员标记为超级管理员
                Admin::query()
                    ->where('account_id', $adminAccount->id)
                    ->update(['is_super' => 1]);
            }
        }
    }
}
