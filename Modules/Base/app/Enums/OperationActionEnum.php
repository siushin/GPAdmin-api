<?php

namespace Modules\Base\Enums;

/**
 * 枚举：操作类型
 */
enum OperationActionEnum: string
{
    case index          = '列表查询';
    case list           = '列表数据';
    case paramData      = '参数数据';
    case loginInfo      = '登录信息';
    case login          = '登录';
    case logout         = '登出';
    case detail         = '查看详情';
    case requestService = '请求服务';
    case create         = '新增';
    case add            = '添加';
    case batchAdd       = '批量添加';
    case update         = '更新';
    case batchUpdate    = '批量更新';
    case edit           = '编辑';
    case delete         = '删除';
    case batchDelete    = '批量删除';
    case export         = '导出';
    case import         = '导入';
    case upload         = '上传';
    case download       = '下载';
    case move           = '移动';
    case copy           = '复制';
}
