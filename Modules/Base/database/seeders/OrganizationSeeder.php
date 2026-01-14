<?php

namespace Modules\Base\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Base\Models\Dictionary;
use Modules\Base\Models\Organization;

/**
 * 数据填充：组织架构（行政区划）
 */
class OrganizationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Schema::disableForeignKeyConstraints(); // 关闭外键检查
        Organization::query()->truncate();
        Schema::enableForeignKeyConstraints(); // 开启外键检查

        // 获取组织架构类型的字典ID（行政区划）
        $organization_tid = Dictionary::getDictionaryIdByCode('OrganizationType', 'region');

        // 生成组织架构ID
        $orgChinaId = generateId();        // 中国
        $orgBeijingId = generateId();      // 北京市
        $orgShanghaiId = generateId();     // 上海市
        $orgGuangdongId = generateId();    // 广东省
        $orgGuangzhouId = generateId();    // 广州市
        $orgShenzhenId = generateId();     // 深圳市

        // 行政区划数据（按北上广深顺序）
        $organizationData = [
            [
                'organization_id'       => $orgChinaId,
                'organization_name'     => '中国',
                'organization_pid'      => 0,
                'full_organization_pid' => ',' . $orgChinaId . ',',
                'organization_tid'      => $organization_tid,
            ],
            [
                'organization_id'       => $orgBeijingId,
                'organization_name'     => '北京市',
                'organization_pid'      => $orgChinaId,
                'full_organization_pid' => ',' . $orgChinaId . ',' . $orgBeijingId . ',',
                'organization_tid'      => $organization_tid,
            ],
            [
                'organization_id'       => $orgShanghaiId,
                'organization_name'     => '上海市',
                'organization_pid'      => $orgChinaId,
                'full_organization_pid' => ',' . $orgChinaId . ',' . $orgShanghaiId . ',',
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
                'organization_id'       => $orgGuangzhouId,
                'organization_name'     => '广州市',
                'organization_pid'      => $orgGuangdongId,
                'full_organization_pid' => ',' . $orgChinaId . ',' . $orgGuangdongId . ',' . $orgGuangzhouId . ',',
                'organization_tid'      => $organization_tid,
            ],
            [
                'organization_id'       => $orgShenzhenId,
                'organization_name'     => '深圳市',
                'organization_pid'      => $orgGuangdongId,
                'full_organization_pid' => ',' . $orgChinaId . ',' . $orgGuangdongId . ',' . $orgShenzhenId . ',',
                'organization_tid'      => $organization_tid,
            ],
        ];

        Organization::upsert($organizationData, ['organization_pid', 'organization_name'], ['organization_name']);
    }
}
