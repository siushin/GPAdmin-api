<?php

namespace Modules\Base\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Base\Models\Company;

/**
 * 数据填充：公司
 */
class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $companyName = env('APP_COMPANY', '默认公司');

        // 查找或创建公司数据
        $company = Company::query()->where('company_name', $companyName)->first();

        if (!$company) {
            // 如果不存在，创建新公司
            Company::query()->create([
                'company_id'          => generateId(),
                'company_name'        => $companyName,
                'company_code'        => env('APP_COMPANY_CODE', 'COMPANY-001'),
                'company_credit_code' => env('APP_COMPANY_CREDIT_CODE', '911100000000000000'),
                'legal_person'        => env('APP_COMPANY_LEGAL_PERSON'),
                'contact_phone'       => env('APP_COMPANY_PHONE'),
                'contact_email'       => env('APP_COMPANY_EMAIL'),
                'company_address'     => env('APP_COMPANY_ADDRESS'),
                'company_desc'        => env('APP_COMPANY_DESC'),
                'status'              => 1,
            ]);
        } else {
            // 如果已存在，更新其他字段（不更新 company_id）
            $company->update([
                'company_code'        => env('APP_COMPANY_CODE', $company->company_code),
                'company_credit_code' => env('APP_COMPANY_CREDIT_CODE', $company->company_credit_code),
                'legal_person'        => env('APP_COMPANY_LEGAL_PERSON', $company->legal_person),
                'contact_phone'       => env('APP_COMPANY_PHONE', $company->contact_phone),
                'contact_email'       => env('APP_COMPANY_EMAIL', $company->contact_email),
                'company_address'     => env('APP_COMPANY_ADDRESS', $company->company_address),
                'company_desc'        => env('APP_COMPANY_DESC', $company->company_desc),
            ]);
        }
    }
}

