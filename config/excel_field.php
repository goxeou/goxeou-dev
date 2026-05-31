<?php
// +----------------------------------------------------------------------
// | excel表头设置
// +----------------------------------------------------------------------

return [
    //产品列表
//    'ShopProduct/index' => [
//        'name' => '产品列表',
//        'title' => ["商品ID","明细ID","商品编码","规格编码","颜色","规格","其他规格","商品图片","商品详情（图片列表）","规格图片","商品名称","SKU商品简称","商品卖点","商品描述","宝贝链接","库存",
//            "重量","销售价","市场价","成本价","1级供货价","2级供货价","3级供货价","4级供货价","5级供货价","商品分类","商品分组","商品服务","商品类型","配送模板类型","模板信息","分红设置",
//            "团队分红设置","团队分红比例","股东分红设置","股东分红比例","区域代理分红设置","区域代理分红比例","积分抵扣设置","积分抵扣设置比例","显示条件","购买条件","销量",
//            "状态","商品主图","商品视频","商品详情（富文本）"
//        ],
//        'field' => ['id','name']
//    ],
    'Commission/fenhonglog' => [
        'name' => '分红记录',
        'title' => [
            'ID','会员ID','分红金额','分红积分','分红分数','结算时间','备注'
        ],
        'field' => ['id','mid','commission','score','copies','createtime','remark']
    ],
    'ShopStock/index' => [
        'name' => '录入库存记录',
        'title' => [
            '商品ID','商品名称','商品规格','变更库存','变更后剩余','变更时间'
        ],
        'field' => ['proid','name','ggname','stock','afterstock','createtime']
    ],
    'ShopProductStockrecord/index' => [
        'name' => '库存记录',
        'title' => [
            '商品ID','商品名称','商品规格','变更库存','变更后剩余','变更时间'
        ],
        'field' => ['proid','name','ggname','stock','afterstock','createtime']
    ],
    'BusinessMoney/moneylog' => [
        'name' => '商家'.t('余额').'明细',
        'title' => [
            '商户名称','变更金额','变更后剩余','变更时间','备注'
        ],
        'field' => ['name','money','after','createtime','remark']
    ],
    'BusinessScore/adminscorelog' => [
        'name' => '平台'.t('积分').'明细',
        'title' => [
            'ID','变更数量','变更后剩余','变更时间','备注'
        ],
        'field' => ['id','score','after','createtime','remark']
    ],
    'PrizePool/send_log' => [
        'name' => '奖金池发放记录',
        'title' => [
            'ID','奖金池金额','发放总比例','总奖金','等级','等级比例','等级总奖金','等级人数','发放时间','发放类型'
        ],
        'field' => ['id','pool_num','send_bili','prize_total','level_name','level_bili','level_prize_total','member_count','createtime','send_type']
    ],
    'Express/index' => [
        'name' => '寄件列表',
        'title' => [
            'ID','货品信息','订单号','下单时间','快递公司','期望上门时间','寄件人','寄件联系电话','寄件地址','收货人','收货联系电话','收货地址','会员ID','会员昵称'
        ],
        'field' => ['id','cargo','ordernum','createtime','company','sm_time','sendManName','sendManMobile','sendManPrintAddr','recManName','recManMobile','recManPrintAddr','mid','nickname']
    ],

];
