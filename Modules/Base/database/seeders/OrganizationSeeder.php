<?php

namespace Modules\Base\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Base\Models\Company;
use Modules\Base\Models\Department;
use Modules\Base\Models\Dictionary;
use Modules\Base\Models\Organization;
use Modules\Base\Models\Position;
use Modules\Base\Models\Post;

/**
 * 数据填充：组织架构
 */
class OrganizationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0'); // 关闭外键检查

        // 清空表
        DB::table('gpa_post')->truncate();
        DB::table('gpa_position')->truncate();
        DB::table('gpa_department')->truncate();
        DB::table('gpa_company')->truncate();
        DB::table('gpa_organization')->truncate();

        DB::statement('SET FOREIGN_KEY_CHECKS=1'); // 开启外键检查

        // 生成组织架构ID
        $orgChinaId = generateId();        // 中国
        $orgGuangdongId = generateId();    // 广东省
        $orgShenzhenId = generateId();     // 深圳市
        $orgGuangzhouId = generateId();    // 广州市
        $orgBeijingId = generateId();      // 北京市

        // 组织架构类型的字典ID
        $organization_tid = Dictionary::getDictionaryIdByCode('OrganizationType', 'region');

        // 组织架构数据
        $organizationData = [
            [
                'organization_id'       => $orgChinaId,
                'organization_name'     => '中国',
                'organization_pid'      => 0,
                'full_organization_pid' => ',' . $orgChinaId . ',',
                'organization_tid'      => $organization_tid,
            ],
            [
                'organization_id'       => $orgGuangdongId,
                'organization_name'     => '广东省',
                'organization_pid'      => $orgChinaId,
                'full_organization_pid' => ',' . $orgChinaId . ',' . $orgGuangdongId . ',',
                'organization_tid'      => $organization_tid,
            ],
            [
                'organization_id'       => $orgShenzhenId,
                'organization_name'     => '深圳市',
                'organization_pid'      => $orgGuangdongId,
                'full_organization_pid' => ',' . $orgChinaId . ',' . $orgGuangdongId . ',' . $orgShenzhenId . ',',
                'organization_tid'      => $organization_tid,
            ],
            [
                'organization_id'       => $orgGuangzhouId,
                'organization_name'     => '广州市',
                'organization_pid'      => $orgGuangdongId,
                'full_organization_pid' => ',' . $orgChinaId . ',' . $orgGuangdongId . ',' . $orgGuangzhouId . ',',
                'organization_tid'      => $organization_tid,
            ],
            [
                'organization_id'       => $orgBeijingId,
                'organization_name'     => '北京市',
                'organization_pid'      => $orgChinaId,
                'full_organization_pid' => ',' . $orgChinaId . ',' . $orgBeijingId . ',',
                'organization_tid'      => $organization_tid,
            ],
        ];
        Organization::upsert($organizationData, ['organization_pid', 'organization_name'], ['organization_name']);

        // 生成公司ID
        $companyShenzhenId = generateId();  // 深圳科技有限公司
        $companyGuangzhouId = generateId(); // 广州贸易有限公司
        $companyBeijingId = generateId();   // 北京金融投资有限公司

        // 公司数据（关联组织架构）
        $companyData = [
            [
                'company_id'          => $companyShenzhenId,
                'company_name'        => '深圳科技有限公司',
                'company_code'        => 'SZ-TECH-001',
                'company_credit_code' => '914403001234567891',
                'organization_id'     => $orgShenzhenId, // 关联深圳市
                'legal_person'        => '张三',
                'contact_phone'       => '0755-12345678',
                'contact_email'       => 'contact@sztech.com',
                'address'             => '深圳市南山区科技园',
                'company_desc'        => '专注于软件开发和技术服务的高科技公司',
                'status'              => 1,
            ],
            [
                'company_id'          => $companyGuangzhouId,
                'company_name'        => '广州贸易有限公司',
                'company_code'        => 'GZ-TRADE-001',
                'company_credit_code' => '914401002345678902',
                'organization_id'     => $orgGuangzhouId, // 关联广州市
                'legal_person'        => '李四',
                'contact_phone'       => '020-87654321',
                'contact_email'       => 'contact@gztrade.com',
                'address'             => '广州市天河区CBD',
                'company_desc'        => '专业从事国际贸易和物流服务',
                'status'              => 1,
            ],
            [
                'company_id'          => $companyBeijingId,
                'company_name'        => '北京金融投资有限公司',
                'company_code'        => 'BJ-FINANCE-001',
                'company_credit_code' => '911100003456789013',
                'organization_id'     => $orgBeijingId, // 关联北京市
                'legal_person'        => '王五',
                'contact_phone'       => '010-11223344',
                'contact_email'       => 'contact@bjfinance.com',
                'address'             => '北京市朝阳区金融街',
                'company_desc'        => '专业的金融投资和资产管理公司',
                'status'              => 1,
            ],
        ];
        Company::upsert($companyData, ['company_code']);

        // 生成部门ID（深圳科技有限公司）
        $deptTechRdId = generateId();        // 技术研发部
        $deptFrontId = generateId();         // 前端开发组
        $deptBackId = generateId();          // 后端开发组
        $deptSalesId = generateId();         // 市场销售部
        $deptHrId = generateId();            // 人力资源部

        // 生成部门ID（广州贸易有限公司）
        $deptIntlId = generateId();          // 国际贸易部
        $deptLogisticsId = generateId();     // 物流管理部

        // 部门数据（关联公司）
        $departmentData = [
            // 深圳科技有限公司的部门
            [
                'department_id'   => $deptTechRdId,
                'department_name' => '技术研发部',
                'department_code' => 'TECH-RD-001',
                'company_id'      => $companyShenzhenId,
                'parent_id'       => 0,
                'full_parent_id'  => ',' . $deptTechRdId . ',',
                'manager_id'      => null,
                'description'     => '负责产品研发和技术创新',
                'status'          => 1,
                'sort_order'      => 1,
            ],
            [
                'department_id'   => $deptFrontId,
                'department_name' => '前端开发组',
                'department_code' => 'TECH-FRONT-001',
                'company_id'      => $companyShenzhenId,
                'parent_id'       => $deptTechRdId,
                'full_parent_id'  => ',' . $deptTechRdId . ',' . $deptFrontId . ',',
                'manager_id'      => null,
                'description'     => '负责前端界面开发和用户体验优化',
                'status'          => 1,
                'sort_order'      => 1,
            ],
            [
                'department_id'   => $deptBackId,
                'department_name' => '后端开发组',
                'department_code' => 'TECH-BACK-001',
                'company_id'      => $companyShenzhenId,
                'parent_id'       => $deptTechRdId,
                'full_parent_id'  => ',' . $deptTechRdId . ',' . $deptBackId . ',',
                'manager_id'      => null,
                'description'     => '负责后端服务开发和系统架构设计',
                'status'          => 1,
                'sort_order'      => 2,
            ],
            [
                'department_id'   => $deptSalesId,
                'department_name' => '市场销售部',
                'department_code' => 'TECH-SALES-001',
                'company_id'      => $companyShenzhenId,
                'parent_id'       => 0,
                'full_parent_id'  => ',' . $deptSalesId . ',',
                'manager_id'      => null,
                'description'     => '负责市场推广和产品销售',
                'status'          => 1,
                'sort_order'      => 2,
            ],
            [
                'department_id'   => $deptHrId,
                'department_name' => '人力资源部',
                'department_code' => 'TECH-HR-001',
                'company_id'      => $companyShenzhenId,
                'parent_id'       => 0,
                'full_parent_id'  => ',' . $deptHrId . ',',
                'manager_id'      => null,
                'description'     => '负责人力资源管理和员工发展',
                'status'          => 1,
                'sort_order'      => 3,
            ],
            // 广州贸易有限公司的部门
            [
                'department_id'   => $deptIntlId,
                'department_name' => '国际贸易部',
                'department_code' => 'GZ-INTL-001',
                'company_id'      => $companyGuangzhouId,
                'parent_id'       => 0,
                'full_parent_id'  => ',' . $deptIntlId . ',',
                'manager_id'      => null,
                'description'     => '负责国际贸易业务拓展',
                'status'          => 1,
                'sort_order'      => 1,
            ],
            [
                'department_id'   => $deptLogisticsId,
                'department_name' => '物流管理部',
                'department_code' => 'GZ-LOGISTICS-001',
                'company_id'      => $companyGuangzhouId,
                'parent_id'       => 0,
                'full_parent_id'  => ',' . $deptLogisticsId . ',',
                'manager_id'      => null,
                'description'     => '负责物流运输和仓储管理',
                'status'          => 1,
                'sort_order'      => 2,
            ],
        ];
        Department::upsert($departmentData, ['company_id', 'parent_id', 'department_name']);

        // 生成职位ID
        $posFrontSeniorId = generateId();    // 高级前端工程师
        $posFrontMidId = generateId();       // 中级前端工程师
        $posBackSeniorId = generateId();    // 高级后端工程师
        $posBackMidId = generateId();        // 中级后端工程师
        $posCtoId = generateId();            // 技术总监
        $posSalesManagerId = generateId();   // 销售经理
        $posSalesSpecialistId = generateId(); // 销售专员
        $posHrManagerId = generateId();      // HR经理
        $posIntlManagerId = generateId();    // 国际贸易经理

        // 职位数据（关联部门）
        $positionData = [
            // 技术研发部相关职位
            [
                'position_id'      => $posFrontSeniorId,
                'position_name'    => '高级前端工程师',
                'position_code'    => 'POS-FRONT-SENIOR',
                'department_id'    => $deptFrontId, // 前端开发组
                'job_description'  => '负责复杂前端项目的架构设计和开发工作',
                'job_requirements' => '5年以上前端开发经验，精通React/Vue等框架',
                'status'           => 1,
                'sort_order'       => 1,
            ],
            [
                'position_id'      => $posFrontMidId,
                'position_name'    => '中级前端工程师',
                'position_code'    => 'POS-FRONT-MID',
                'department_id'    => $deptFrontId,
                'job_description'  => '负责前端功能模块的开发与维护',
                'job_requirements' => '3年以上前端开发经验，熟悉主流前端框架',
                'status'           => 1,
                'sort_order'       => 2,
            ],
            [
                'position_id'      => $posBackSeniorId,
                'position_name'    => '高级后端工程师',
                'position_code'    => 'POS-BACK-SENIOR',
                'department_id'    => $deptBackId, // 后端开发组
                'job_description'  => '负责后端系统架构设计和核心功能开发',
                'job_requirements' => '5年以上后端开发经验，精通Laravel/PHP等技术栈',
                'status'           => 1,
                'sort_order'       => 1,
            ],
            [
                'position_id'      => $posBackMidId,
                'position_name'    => '中级后端工程师',
                'position_code'    => 'POS-BACK-MID',
                'department_id'    => $deptBackId,
                'job_description'  => '负责后端业务逻辑开发和API接口设计',
                'job_requirements' => '3年以上后端开发经验，熟悉Laravel框架',
                'status'           => 1,
                'sort_order'       => 2,
            ],
            [
                'position_id'      => $posCtoId,
                'position_name'    => '技术总监',
                'position_code'    => 'POS-TECH-CTO',
                'department_id'    => $deptTechRdId, // 技术研发部
                'job_description'  => '负责技术团队管理和技术战略规划',
                'job_requirements' => '10年以上技术管理经验，具备丰富的团队管理能力',
                'status'           => 1,
                'sort_order'       => 0,
            ],
            // 市场销售部相关职位
            [
                'position_id'      => $posSalesManagerId,
                'position_name'    => '销售经理',
                'position_code'    => 'POS-SALES-MANAGER',
                'department_id'    => $deptSalesId, // 市场销售部
                'job_description'  => '负责销售团队管理和业务拓展',
                'job_requirements' => '5年以上销售管理经验，具备良好的沟通能力',
                'status'           => 1,
                'sort_order'       => 1,
            ],
            [
                'position_id'      => $posSalesSpecialistId,
                'position_name'    => '销售专员',
                'position_code'    => 'POS-SALES-SPECIALIST',
                'department_id'    => $deptSalesId,
                'job_description'  => '负责客户开发和产品销售',
                'job_requirements' => '2年以上销售经验，具备良好的客户服务意识',
                'status'           => 1,
                'sort_order'       => 2,
            ],
            // 人力资源部相关职位
            [
                'position_id'      => $posHrManagerId,
                'position_name'    => 'HR经理',
                'position_code'    => 'POS-HR-MANAGER',
                'department_id'    => $deptHrId, // 人力资源部
                'job_description'  => '负责人力资源规划、招聘和员工关系管理',
                'job_requirements' => '5年以上HR管理经验，熟悉人力资源各模块',
                'status'           => 1,
                'sort_order'       => 1,
            ],
            // 国际贸易部相关职位
            [
                'position_id'      => $posIntlManagerId,
                'position_name'    => '国际贸易经理',
                'position_code'    => 'POS-INTL-MANAGER',
                'department_id'    => $deptIntlId, // 国际贸易部
                'job_description'  => '负责国际贸易业务拓展和客户关系维护',
                'job_requirements' => '5年以上国际贸易经验，英语流利',
                'status'           => 1,
                'sort_order'       => 1,
            ],
        ];
        Position::upsert($positionData, ['department_id', 'position_name']);

        // 生成岗位ID
        $postReactDevId = generateId();       // React前端开发
        $postVueDevId = generateId();         // Vue前端开发
        $postLaravelDevId = generateId();     // Laravel后端开发
        $postApiDevId = generateId();         // API接口开发
        $postTechLeadId = generateId();       // 技术团队管理
        $postB2bSalesId = generateId();       // 企业客户销售
        $postOnlineSalesId = generateId();    // 在线销售专员
        $postRecruitmentId = generateId();    // 招聘专员
        $postEuUsMarketId = generateId();     // 欧美市场拓展

        // 岗位数据（关联职位和部门）
        $postData = [
            [
                'post_id'           => $postReactDevId,
                'post_name'         => 'React前端开发',
                'post_code'         => 'POST-REACT-DEV',
                'position_id'       => $posFrontSeniorId, // 高级前端工程师
                'department_id'     => $deptFrontId, // 前端开发组
                'post_description'  => '负责React项目的开发和维护，参与技术方案设计',
                'post_requirements' => '熟练掌握React、TypeScript、Redux等技术',
                'status'            => 1,
                'sort_order'        => 1,
            ],
            [
                'post_id'           => $postVueDevId,
                'post_name'         => 'Vue前端开发',
                'post_code'         => 'POST-VUE-DEV',
                'position_id'       => $posFrontMidId, // 中级前端工程师
                'department_id'     => $deptFrontId,
                'post_description'  => '负责Vue项目的功能开发和bug修复',
                'post_requirements' => '熟悉Vue3、Element Plus等前端技术',
                'status'            => 1,
                'sort_order'        => 2,
            ],
            [
                'post_id'           => $postLaravelDevId,
                'post_name'         => 'Laravel后端开发',
                'post_code'         => 'POST-LARAVEL-DEV',
                'position_id'       => $posBackSeniorId, // 高级后端工程师
                'department_id'     => $deptBackId, // 后端开发组
                'post_description'  => '负责Laravel后端系统的架构设计和核心功能开发',
                'post_requirements' => '精通Laravel框架，熟悉MySQL、Redis等',
                'status'            => 1,
                'sort_order'        => 1,
            ],
            [
                'post_id'           => $postApiDevId,
                'post_name'         => 'API接口开发',
                'post_code'         => 'POST-API-DEV',
                'position_id'       => $posBackMidId, // 中级后端工程师
                'department_id'     => $deptBackId,
                'post_description'  => '负责RESTful API接口的设计和开发',
                'post_requirements' => '熟悉RESTful API设计规范，了解微服务架构',
                'status'            => 1,
                'sort_order'        => 2,
            ],
            [
                'post_id'           => $postTechLeadId,
                'post_name'         => '技术团队管理',
                'post_code'         => 'POST-TECH-LEAD',
                'position_id'       => $posCtoId, // 技术总监
                'department_id'     => $deptTechRdId, // 技术研发部
                'post_description'  => '负责技术团队的管理和技术决策',
                'post_requirements' => '具备丰富的技术管理经验和团队领导能力',
                'status'            => 1,
                'sort_order'        => 1,
            ],
            [
                'post_id'           => $postB2bSalesId,
                'post_name'         => '企业客户销售',
                'post_code'         => 'POST-B2B-SALES',
                'position_id'       => $posSalesManagerId, // 销售经理
                'department_id'     => $deptSalesId, // 市场销售部
                'post_description'  => '负责企业级客户的开发和维护',
                'post_requirements' => '具备B2B销售经验，良好的客户沟通能力',
                'status'            => 1,
                'sort_order'        => 1,
            ],
            [
                'post_id'           => $postOnlineSalesId,
                'post_name'         => '在线销售专员',
                'post_code'         => 'POST-ONLINE-SALES',
                'position_id'       => $posSalesSpecialistId, // 销售专员
                'department_id'     => $deptSalesId,
                'post_description'  => '负责在线销售渠道的开发和维护',
                'post_requirements' => '熟悉电商平台运营，具备网络销售经验',
                'status'            => 1,
                'sort_order'        => 2,
            ],
            [
                'post_id'           => $postRecruitmentId,
                'post_name'         => '招聘专员',
                'post_code'         => 'POST-RECRUITMENT',
                'position_id'       => $posHrManagerId, // HR经理
                'department_id'     => $deptHrId, // 人力资源部
                'post_description'  => '负责人才招聘和面试组织工作',
                'post_requirements' => '熟悉招聘流程，具备良好的沟通和判断能力',
                'status'            => 1,
                'sort_order'        => 1,
            ],
            [
                'post_id'           => $postEuUsMarketId,
                'post_name'         => '欧美市场拓展',
                'post_code'         => 'POST-EU-US-MARKET',
                'position_id'       => $posIntlManagerId, // 国际贸易经理
                'department_id'     => $deptIntlId, // 国际贸易部
                'post_description'  => '负责欧美市场的业务拓展和客户开发',
                'post_requirements' => '英语流利，熟悉欧美市场，具备国际贸易经验',
                'status'            => 1,
                'sort_order'        => 1,
            ],
        ];
        Post::upsert($postData, ['position_id', 'post_name']);
    }
}
