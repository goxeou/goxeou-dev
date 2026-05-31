<?php
if(getcustom('wx_channels')){
    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_admin_setapp_channels` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL,
      `appid` varchar(255) DEFAULT NULL,
      `appsecret` varchar(255) DEFAULT NULL,
      `nickname` varchar(255) DEFAULT NULL,
      `headimg` varchar(255) DEFAULT NULL,
      `qrcode` varchar(255) DEFAULT NULL,
      `token` varchar(255) DEFAULT NULL,
      `key` varchar(255) DEFAULT NULL,
      `signature` varchar(255) DEFAULT NULL,
      `subject_type` varchar(10) DEFAULT NULL,
      `status` tinyint(1) DEFAULT '0' COMMENT '0关闭 1正常 2已注销 3未知',
      `username` varchar(50) DEFAULT NULL COMMENT '小店原始id',
      `sph_id` varchar(50) DEFAULT NULL COMMENT '视频号id 以“sph”开头的id',
      PRIMARY KEY (`id`),
      KEY `aid` (`aid`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC COMMENT='视频号小店';");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_channels_address` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL,
      `appid` varchar(20) DEFAULT NULL,
      `address_id` varchar(50) DEFAULT NULL COMMENT '地址id',
      `name` varchar(50) DEFAULT NULL COMMENT '联系人姓名',
      `user_name` varchar(100) DEFAULT NULL COMMENT '收货人姓名',
      `postal_code` varchar(10) DEFAULT NULL COMMENT '邮编',
      `province_name` varchar(20) DEFAULT NULL,
      `city_name` varchar(20) DEFAULT NULL,
      `county_name` varchar(20) DEFAULT NULL,
      `detail_info` varchar(100) DEFAULT NULL COMMENT '详细收货地址信息',
      `national_code` varchar(10) DEFAULT NULL COMMENT '收货地址国家码',
      `tel_number` varchar(15) DEFAULT NULL COMMENT '收货人手机号码',
      `lat` varchar(50) DEFAULT NULL COMMENT '纬度',
      `lng` varchar(50) DEFAULT NULL COMMENT '经度',
      `house_number` varchar(50) DEFAULT NULL COMMENT '门牌号',
      `landline` varchar(15) DEFAULT NULL COMMENT '座机',
      `send_addr` tinyint(1) DEFAULT '0' COMMENT '是否为发货地址',
      `recv_addr` tinyint(1) DEFAULT '0' COMMENT '是否为收货地址',
      `default_send` tinyint(1) DEFAULT '0' COMMENT '是否为默认发货地址',
      `default_recv` tinyint(1) DEFAULT '0' COMMENT '是否为默认收货地址',
      `create_time` int(10) DEFAULT '0' COMMENT '创建时间戳',
      `update_time` int(10) DEFAULT '0' COMMENT '更新时间戳',
      `same_city` tinyint(1) DEFAULT '0' COMMENT '1:表示同城配送',
      `pickup` tinyint(1) DEFAULT '0' COMMENT '1:表示用户自提',
      PRIMARY KEY (`id`),
      KEY `aid` (`aid`,`appid`)
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT='视频号小店——地址管理';");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_channels_after_sales` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL,
      `appid` varchar(50) DEFAULT NULL,
      `after_sale_order_id` varchar(20) NOT NULL COMMENT '售后单号',
      `status` varchar(100) DEFAULT NULL COMMENT '售后单当前状态',
      `openid` varchar(100) DEFAULT NULL,
      `unionid` varchar(100) DEFAULT NULL,
      `order_id` varchar(100) DEFAULT NULL,
      `product_id` varchar(30) DEFAULT NULL,
      `sku_id` varchar(30) DEFAULT NULL,
      `count` int(11) DEFAULT NULL,
      `fast_refund` tinyint(1) DEFAULT '0' COMMENT '是否极速退款',
      `desc` varchar(255) DEFAULT NULL COMMENT '售后描述',
      `receive_product` tinyint(1) DEFAULT '0' COMMENT '发起售后的时候用户是否已经收到货',
      `cancel_time` int(10) DEFAULT '0' COMMENT 'cancel_time',
      `media_id_list` text COMMENT '举证图片media_id列表',
      `tel_number` varchar(15) DEFAULT NULL COMMENT '联系电话',
      `amount` decimal(12,2) DEFAULT '0.00' COMMENT '退款金额',
      `refund_reason` int(11) DEFAULT NULL COMMENT '售后单退款直接原因',
      `waybill_id` int(11) DEFAULT NULL COMMENT '快递单号',
      `delivery_id` int(11) DEFAULT NULL COMMENT '物流公司id',
      `delivery_name` varchar(255) DEFAULT NULL COMMENT '物流公司名称',
      `reject_reason` varchar(255) DEFAULT NULL COMMENT '拒绝原因',
      `refund_certificates` text COMMENT '退款凭证',
      `create_time` int(11) DEFAULT NULL,
      `update_time` int(11) DEFAULT NULL,
      `reason` varchar(50) DEFAULT NULL COMMENT '退款原因',
      `reason_text` varchar(255) DEFAULT NULL COMMENT '退款原因解释',
      `refund_resp` text COMMENT '微信支付退款的响应',
      `type` varchar(255) DEFAULT NULL,
      `deadline` int(10) DEFAULT '0' COMMENT '仅在待商家审核退款退货申请或收货期间返回，表示操作剩余时间（秒数',
      `complaint_id` int(11) DEFAULT '0' COMMENT '纠纷id',
      `refund_desc` varchar(255) DEFAULT NULL,
      `media_url_list` text COMMENT '举证图片url集合',
      `refund_certificates_mediaid` text,
      `reject_reason_type` int(11) DEFAULT '0' COMMENT '拒绝原因',
      PRIMARY KEY (`id`),
      KEY `aid` (`aid`,`appid`,`after_sale_order_id`,`order_id`)
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT='视频号小店——售后订单';");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_channels_area` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `name` varchar(30) NOT NULL,
      `code` varchar(20) NOT NULL,
      `level` tinyint(1) NOT NULL,
      `parent_code` varchar(20) DEFAULT NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT='视频号小店——行政区域';");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_channels_bank` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `account_bank` varchar(50) DEFAULT NULL COMMENT '开户银行',
      `bank_code` varchar(100) DEFAULT NULL COMMENT '银行编码',
      `bank_id` int(11) DEFAULT NULL COMMENT '银行联号',
      `bank_name` varchar(100) DEFAULT NULL COMMENT '银行名称',
      `bank_type` tinyint(1) DEFAULT '1' COMMENT '银行类型(1.对公，2.对私)',
      `need_branch` tinyint(1) DEFAULT '0' COMMENT '是否需要填写支行信息 1是',
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='视频号小店——银行信息';");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_channels_bankacct` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL,
      `appid` varchar(50) DEFAULT NULL,
      `bank_account_type` varchar(50) DEFAULT NULL COMMENT '账户类型',
      `account_bank` varchar(100) DEFAULT NULL COMMENT '开户银行',
      `bank_address_code` varchar(50) DEFAULT NULL COMMENT '开户银行省市编码',
      `bank_branch_id` varchar(100) DEFAULT NULL COMMENT '开户银行联行号',
      `bank_name` varchar(100) DEFAULT NULL COMMENT '开户银行全称',
      `account_number` varchar(100) DEFAULT NULL COMMENT '银行账号',
      `account_name` varchar(255) DEFAULT NULL COMMENT '账户名称',
      `available_amount` decimal(12,2) DEFAULT '0.00' COMMENT '可提现余额',
      `pending_amount` decimal(12,2) DEFAULT '0.00' COMMENT '待结算余额',
      `sub_mchid` int(11) DEFAULT '0' COMMENT '二级商户号',
      `bank_code` varchar(255) DEFAULT NULL,
      PRIMARY KEY (`id`),
      KEY `aid` (`aid`,`appid`)
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT='视频号小店——结算账户';");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_channels_bankarea` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `name` varchar(30) DEFAULT NULL,
      `code` varchar(20) DEFAULT NULL,
      `parent_code` varchar(20) DEFAULT NULL,
      `level` tinyint(1) DEFAULT NULL,
      `bank_address_code` varchar(20) DEFAULT NULL COMMENT '开户银行省市编码',
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT='视频号小店——银行省市列表';");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_channels_brand` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) NOT NULL,
      `appid` varchar(50) NOT NULL,
      `brand_id` bigint(20) NOT NULL DEFAULT '0' COMMENT '品牌库中的品牌编号',
      `ch_name` varchar(50) NOT NULL DEFAULT '' COMMENT '品牌商标中文名',
      `en_name` varchar(50) NOT NULL DEFAULT '' COMMENT '品牌商标中文名,',
      `classification_no` int(11) DEFAULT '0' COMMENT '商标分类号, 取值范围1-45',
      `trade_mark_symbol` tinyint(1) DEFAULT '1' COMMENT '商标类型, 取值1:R标; 2: TM标',
      `registrant` varchar(100) DEFAULT '' COMMENT '商标注册人, R标时必填',
      `register_no` varchar(100) DEFAULT '' COMMENT '商标注册号, R标时必填',
      `start_time` int(11) DEFAULT '0' COMMENT '商标注册有效期, 开始时间, 长期有效可不填',
      `end_time` int(11) DEFAULT '0' COMMENT '商标注册有效期, 结束时间, 长期有效可不填',
      `is_permanent` tinyint(1) DEFAULT '0' COMMENT '是否长期有效',
      `register_certifications` text COMMENT '商标注册证',
      `register_certifications_ids` text COMMENT '商标注册证',
      `renew_certifications` text COMMENT '变更/续展证明',
      `renew_certifications_ids` text COMMENT '变更/续展证明的',
      `acceptance_time` int(11) DEFAULT '0' COMMENT '商标申请受理时间',
      `acceptance_certification` text COMMENT '商标注册申请受理书',
      `acceptance_certification_ids` text COMMENT '商标注册申请受理书',
      `acceptance_no` varchar(100) DEFAULT NULL COMMENT '商标申请号, TM标时必填',
      `grant_type` tinyint(1) DEFAULT '1' COMMENT '商标授权信息, 取值1:自有品牌; 2: 授权品牌',
      `grant_certifications` text COMMENT '品牌销售授权书',
      `grant_certifications_ids` text COMMENT '品牌销售授权书',
      `grant_level` tinyint(1) DEFAULT '0' COMMENT '授权级数, 授权品牌必填, 取值1-3',
      `grant_start_time` int(10) DEFAULT '0' COMMENT '授权有效期, 开始时间, 长期有效可不填',
      `grant_end_time` int(10) DEFAULT '0' COMMENT '授权有效期, 结束时间, 长期有效可不填',
      `grant_is_permanent` tinyint(1) DEFAULT '0' COMMENT '是否长期有效',
      `brand_owner_id_photos` text COMMENT '品牌权利人证件照',
      `brand_owner_id_photos_ids` text COMMENT '品牌权利人证件照',
      `status` tinyint(1) DEFAULT '0' COMMENT '0审核中',
      `audit_id` varchar(50) DEFAULT '0' COMMENT '审核单id',
      `reject_reason` varchar(255) DEFAULT NULL COMMENT '拒绝原因',
      `createtime` int(10) DEFAULT '0' COMMENT '创建时间',
      `update_time` int(10) DEFAULT '0' COMMENT '更新时间',
      PRIMARY KEY (`id`),
      KEY `aid` (`aid`,`appid`)
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC COMMENT='视频号小店——品牌';");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_channels_brand_basic` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `brand_id` bigint(20) NOT NULL,
      `ch_name` varchar(50) NOT NULL,
      `en_name` varchar(50) NOT NULL,
      PRIMARY KEY (`id`),
      KEY `brand_id` (`brand_id`)
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC COMMENT='视频号小店——所有品牌';");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_channels_category` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL,
      `appid` varchar(50) NOT NULL,
      `cat_id` bigint(20) NOT NULL,
      `name` varchar(50) NOT NULL,
      `f_cat_id` bigint(20) NOT NULL,
      `need_to_apply` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否需要申请 0否 1是',
      `level` tinyint(1) NOT NULL DEFAULT '1' COMMENT '类目层级 1一级 2二级 3三级',
      `deposit` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '保证金',
      PRIMARY KEY (`id`),
      KEY `appid` (`appid`,`cat_id`,`aid`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC COMMENT='视频号小店——可使用类目';");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_channels_category_apply` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) NOT NULL,
      `appid` varchar(50) NOT NULL,
      `level1` int(11) DEFAULT '0' COMMENT '一级类目',
      `level2` int(11) DEFAULT '0' COMMENT '二级类目',
      `level3` int(11) DEFAULT '0' COMMENT '三级类目',
      `certificate` text COMMENT '资质材料',
      `certificate_ids` text COMMENT '证书',
      `baobeihan` text COMMENT '报备函',
      `baobeihan_ids` text COMMENT '报备函',
      `jingyingzhengming` text COMMENT '经营证明',
      `jingyingzhengming_ids` text COMMENT '经营证明',
      `daihuokoubei` text COMMENT '带货口碑',
      `daihuokoubei_ids` text COMMENT '带货口碑',
      `ruzhuzhizhi` text COMMENT '入住资质',
      `ruzhuzhizhi_ids` text COMMENT '入住资质',
      `jingyingliushui` text COMMENT '经营流水',
      `jingyingliushui_ids` text COMMENT '经营流水',
      `buchongcailiao` text COMMENT '补充材料',
      `buchongcailiao_ids` text COMMENT '补充材料',
      `jingyingpingtai` varchar(30) DEFAULT NULL COMMENT '经营平台',
      `zhanghaomingcheng` varchar(100) DEFAULT NULL COMMENT '账号名称',
      `brand_list` text COMMENT '品牌',
      `createtime` int(10) DEFAULT NULL,
      `audit_id` int(11) DEFAULT NULL,
      `status` varchar(255) DEFAULT NULL,
      `reason` varchar(255) DEFAULT NULL,
      PRIMARY KEY (`id`),
      KEY `aid` (`aid`,`appid`)
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT='视频号小店——类目申请';");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_channels_category_basic` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) NOT NULL,
      `cat_id` bigint(20) NOT NULL,
      `name` varchar(50) NOT NULL,
      `f_cat_id` bigint(20) NOT NULL,
      `level` tinyint(4) NOT NULL,
      `qua` text,
      `product_qua_list` text,
      `brand_qua` text,
      `appid` varchar(30) DEFAULT NULL,
      `need_to_apply` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否需要申请 0否 1是',
      `deposit` decimal(12,2) DEFAULT '0.00',
      PRIMARY KEY (`id`),
      KEY `aid` (`aid`,`cat_id`)
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC COMMENT='视频号小店——平台所有类目';");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_channels_category_detail` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `cat_id` int(11) NOT NULL,
      `info` varchar(255) DEFAULT NULL,
      `attr` text,
      `product_qua_list` text,
      `createtime` int(10) NOT NULL DEFAULT '0',
      PRIMARY KEY (`id`),
      KEY `cat_id` (`cat_id`)
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT='视频号小店——类目详细信息';");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_channels_coupon` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL,
      `appid` varchar(50) DEFAULT NULL,
      `coupon_id` varchar(50) DEFAULT '' COMMENT '优惠券ID',
      `type` int(11) DEFAULT '0' COMMENT '优惠券类型',
      `status` int(11) DEFAULT '0' COMMENT '优惠券状态',
      `create_time` int(10) DEFAULT '0' COMMENT '优惠券创建时间',
      `update_time` int(10) DEFAULT '0' COMMENT '优惠券更新时间',
      `name` varchar(255) DEFAULT '' COMMENT '优惠券名称',
      `promote_type` int(11) DEFAULT '0' COMMENT '推广类型',
      `discount_num` decimal(12,2) DEFAULT '0.00' COMMENT '优惠券折扣数 * 1000, 例如 5.1折-> 5100',
      `discount_fee` decimal(12,2) DEFAULT '0.00' COMMENT '优惠券减少金额, 单位分, 例如0.5元-> 50',
      `end_time` int(10) DEFAULT NULL,
      `limit_num_one_person` int(10) DEFAULT '0' COMMENT '单人限领张数',
      `start_time` int(10) DEFAULT '0' COMMENT '优惠券领用开始时间',
      `valid_type` int(10) DEFAULT '0' COMMENT '优惠券有效期类型',
      `valid_day_num` int(10) DEFAULT '0' COMMENT '优惠券有效天数',
      `valid_start_time` int(10) DEFAULT '0' COMMENT '优惠券有效期开始时间',
      `valid_end_time` int(10) DEFAULT '0' COMMENT '优惠券有效期结束时间',
      `total_num` int(10) DEFAULT '0' COMMENT '优惠券领用总数',
      `issued_num` int(11) DEFAULT '0' COMMENT '优惠券剩余量',
      `receive_num` int(11) DEFAULT '0' COMMENT '优惠券领用但未使用量',
      `used_num` int(11) DEFAULT '0' COMMENT '优惠券已用量',
      `product_cnt` int(11) DEFAULT '0' COMMENT '优惠券使用条件, 满 x 件商品可用',
      `product_price` decimal(12,2) DEFAULT '0.00' COMMENT '优惠券使用条件, 价格满 x 可用，单位分',
      `product_ids` varchar(255) DEFAULT NULL COMMENT '优惠券使用条件, 指定商品 id 可用',
      `invalid_time` int(10) DEFAULT '0' COMMENT '优惠券失效时间',
      `jump_product_id` varchar(255) DEFAULT NULL COMMENT '品折扣券领取后跳转的商品id',
      `notes` varchar(10) DEFAULT NULL COMMENT '备注信息',
      `valid_time` int(10) DEFAULT '0' COMMENT '优惠券有效时间',
      `is_delete` tinyint(1) DEFAULT '0' COMMENT '1已删除',
      `delete_time` int(10) DEFAULT '0' COMMENT '删除时间',
      `coupon_status` tinyint(1) DEFAULT NULL,
      `auto_valid_type` tinyint(1) DEFAULT '0' COMMENT '自动生效 0不启用 1启用',
      PRIMARY KEY (`id`),
      KEY `aid` (`aid`,`appid`,`coupon_id`)
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT='视频号小店——优惠券';");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_channels_delivery` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` varchar(50) DEFAULT NULL,
      `appid` varchar(30) DEFAULT NULL,
      `delivery_id` varchar(30) DEFAULT NULL,
      `delivery_name` varchar(50) DEFAULT NULL,
      PRIMARY KEY (`id`),
      KEY `aid` (`aid`,`appid`)
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT='视频号小店——所有快递公司';");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_channels_ewaybill_account` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL,
      `appid` varchar(50) DEFAULT NULL,
      `delivery_id` varchar(50) DEFAULT NULL,
      `company_type` int(11) DEFAULT NULL COMMENT '快递公司类型，1 表示加盟型，2 表示直营型',
      `shop_id` varchar(255) DEFAULT NULL COMMENT '店铺 id，全局唯一，一个店铺分配一个 shop_id',
      `acct_id` varchar(255) DEFAULT NULL COMMENT '电子面单账号 id，每绑定一个网点分配一个 acct_id',
      `acct_type` int(11) DEFAULT NULL COMMENT '面单账号类型，0 表示普通账号，1 表示共享账号',
      `status` int(11) DEFAULT NULL COMMENT '面单账号状态',
      `available` int(11) DEFAULT NULL COMMENT '面单余额',
      `allocated` int(11) DEFAULT NULL COMMENT '累积已取单',
      `recycled` int(11) DEFAULT NULL COMMENT '累积已回收',
      `cancel` int(11) DEFAULT NULL COMMENT '累计已取消',
      `site_info` text COMMENT '网点信息，结构体详情请参考 SiteInfo',
      `monthly_card` varchar(255) DEFAULT NULL COMMENT '月结账号，company_type 为直营型时有效',
      `sender_address` text COMMENT '绑定的发货地址信息，结构体详情请参考 SenderAddress',
      `share` text COMMENT '共享账号发起方信息，acct_type 为共享账号时有效，结构体详情请参考 EWaybillAcctShare',
      `address` varchar(255) DEFAULT NULL COMMENT '详细地址',
      `site_name` varchar(255) DEFAULT NULL COMMENT '网点名字',
      `site_code` varchar(255) DEFAULT NULL COMMENT '网点编码',
      PRIMARY KEY (`id`),
      KEY `aid` (`aid`,`appid`)
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT='视频号小店——电子面单账号信息';");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_channels_ewaybill_delivery` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL,
      `appid` varchar(50) DEFAULT NULL,
      `shop_id` varchar(100) DEFAULT NULL,
      `delivery_id` varchar(20) DEFAULT NULL,
      `delivery_name` varchar(50) DEFAULT NULL,
      PRIMARY KEY (`id`),
      KEY `aid` (`aid`,`appid`)
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT='视频号小店——电子面单快递';");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_channels_ewaybill_order` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL,
      `appid` varchar(50) DEFAULT NULL,
      `delivery_id` varchar(20) NOT NULL COMMENT '快递公司 id',
      `site_code` varchar(20) DEFAULT NULL COMMENT '网点编码',
      `ewaybill_acct_id` varchar(20) DEFAULT NULL COMMENT '电子面单账号 id',
      `sender_address` text COMMENT '寄件人地址',
      `receiver` text COMMENT '收件人姓名',
      `ec_order_list` text COMMENT '订单信息',
      `remark` varchar(255) DEFAULT NULL COMMENT '备注',
      `shop_id` varchar(50) DEFAULT NULL COMMENT '店铺 id',
      `ewaybill_order_id` varchar(50) DEFAULT NULL COMMENT '电子面单订单id',
      `waybill_id` varchar(50) DEFAULT NULL COMMENT '快递单号',
      `delivery_error_msg` varchar(20) DEFAULT NULL,
      `print_info` text,
      `template_type` tinyint(1) DEFAULT '0' COMMENT '模板类型 0标准模板 1后台模板',
      `template_url` varchar(255) DEFAULT NULL COMMENT '电子面单模板',
      `sender` text COMMENT '发件人',
      `order_id` varchar(50) DEFAULT NULL,
      PRIMARY KEY (`id`),
      KEY `aid` (`aid`,`appid`)
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT='视频号小店——电子面单取号';");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_channels_ewaybill_template` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL,
      `appid` varchar(50) DEFAULT NULL,
      `template_id` varchar(50) DEFAULT NULL COMMENT '模板 id',
      `template_name` varchar(100) DEFAULT NULL COMMENT '模板名，同一快递公司下不可重复',
      `template_desc` varchar(255) DEFAULT '一联单标准模板' COMMENT '模板描述',
      `template_type` varchar(20) DEFAULT 'single' COMMENT '模板类型',
      `options` text COMMENT '模板信息选项，总数必须为 8 个，顺序按照数组序，结构体详情请参考 TemplateOptionPro',
      `is_default` tinyint(1) DEFAULT NULL COMMENT '是否为该快递公司默认模板',
      `create_time` int(10) DEFAULT NULL COMMENT '模板创建时间',
      `update_time` int(10) DEFAULT NULL COMMENT '模板更新时间',
      `delivery_id` varchar(15) DEFAULT NULL,
      PRIMARY KEY (`id`),
      KEY `aid` (`aid`,`appid`)
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT='视频号小店——电子面单模板';");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_channels_ewaybill_template_config` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL,
      `appid` varchar(50) DEFAULT NULL,
      `delivery_id` varchar(20) DEFAULT NULL,
      `type` varchar(20) DEFAULT 'single' COMMENT '模板类型',
      `desc` varchar(255) DEFAULT '一联单标准模板' COMMENT '模板描述',
      `width` int(11) DEFAULT NULL COMMENT '面单宽度（单位毫米）',
      `height` int(11) DEFAULT NULL COMMENT '面单高度（单位毫米）',
      `url` varchar(255) DEFAULT NULL COMMENT '标准模板',
      `custom_config_width` int(11) DEFAULT NULL COMMENT '自定义区域宽度（单位 px，10px = 1 毫米）',
      `custom_config_height` int(11) DEFAULT NULL COMMENT '自定义区域高度（单位 px，10px = 1 毫米）',
      `custom_config_top` int(11) DEFAULT NULL COMMENT '自定义区域到顶部距离（单位 px，10px = 1 毫米）',
      `custom_config_left` int(11) DEFAULT NULL COMMENT '自定义区域到左边距离（单位 px，10px = 1 毫米）',
      PRIMARY KEY (`id`),
      KEY `aid` (`aid`,`appid`)
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT='视频号小店——电子面单标准模板';");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_channels_freight` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) NOT NULL,
      `appid` varchar(50) NOT NULL,
      `template_id` varchar(50) NOT NULL DEFAULT '' COMMENT '模板id',
      `name` varchar(100) NOT NULL DEFAULT '' COMMENT '模板名称',
      `valuation_type` varchar(10) NOT NULL DEFAULT '' COMMENT '计费类型 1按重量（WEIGHT） 2按件数（PIECE）',
      `send_time` varchar(30) NOT NULL DEFAULT '' COMMENT 'SendTime_TWENTYFOUR_HOUR 24小时内发货\r\nSendTime_FOUTYEIGHT_HOUR 48小时内发货\r\nSendTime_THREE_DAY 3天内发货',
      `address_info` text NOT NULL COMMENT '发货地址',
      `delivery_type` varchar(10) NOT NULL DEFAULT '' COMMENT '运输方式',
      `delivery_id` varchar(255) DEFAULT NULL,
      `shipping_method` varchar(30) NOT NULL DEFAULT '' COMMENT '计费方式\r\nFREE：包邮\r\nCONDITION_FREE：条件包邮\r\nNO_FREE：不包邮',
      `all_condition_free_detail` text NOT NULL COMMENT '条件包邮详情',
      `all_freight_calc_method` text NOT NULL COMMENT '具体计费方法，默认运费，指定地区运费等',
      `create_time` int(10) NOT NULL DEFAULT '0' COMMENT '创建时间',
      `update_time` int(10) NOT NULL DEFAULT '0' COMMENT '更新时间',
      `is_default` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否默认模板 1是 0否',
      `not_send_area` text NOT NULL COMMENT '不发货区域',
      PRIMARY KEY (`id`),
      KEY `aid` (`aid`,`appid`)
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT='视频号小店——运费模板';");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_channels_fundsflow` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL,
      `appid` varchar(50) DEFAULT NULL,
      `flow_id` varchar(50) NOT NULL COMMENT '流水id',
      `funds_type` tinyint(4) DEFAULT NULL COMMENT '资金类型',
      `flow_type` tinyint(1) DEFAULT NULL COMMENT '流水类型, 1 收入，2 支出',
      `amount` decimal(12,2) DEFAULT '0.00' COMMENT '流水金额',
      `balance` decimal(12,2) DEFAULT NULL,
      `related_info_list` text COMMENT '流水关联信息',
      `bookkeeping_time` int(10) DEFAULT '0' COMMENT '记账时间',
      `remark` varchar(255) DEFAULT NULL COMMENT '备注',
      PRIMARY KEY (`id`),
      KEY `aid` (`aid`,`appid`)
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT='视频号小店——资金流水';");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_channels_order` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL,
      `appid` varchar(50) DEFAULT NULL,
      `order_id` varchar(50) DEFAULT NULL,
      `status` int(11) DEFAULT NULL COMMENT '10 待付款 20待发货 21部分发货 30待收货 100完成 200全部售后订单取消 250未付款取消',
      `create_time` int(10) DEFAULT NULL,
      `update_time` int(10) DEFAULT NULL,
      `openid` varchar(30) DEFAULT NULL,
      `unionid` varchar(30) DEFAULT NULL,
      `prepay_id` varchar(100) DEFAULT NULL COMMENT '预支付id',
      `transaction_id` varchar(50) DEFAULT NULL COMMENT '支付订单号',
      `prepay_time` int(10) DEFAULT NULL COMMENT '预支付时间，秒级时间戳',
      `pay_time` int(10) DEFAULT NULL COMMENT '支付时间',
      `payment_method` tinyint(1) DEFAULT NULL COMMENT '支付方式 1微信支付 2先用后付 3抽奖商品 4积分兑换',
      `product_price` decimal(12,2) DEFAULT NULL,
      `order_price` decimal(12,2) DEFAULT NULL,
      `freight` decimal(12,2) DEFAULT NULL COMMENT '运费',
      `discounted_price` decimal(12,2) DEFAULT NULL COMMENT '优惠券优惠金额',
      `is_discounted` tinyint(1) DEFAULT '0' COMMENT '是否有优惠券优惠',
      `address_info` text COMMENT '地址信息',
      `delivery_product_info` text COMMENT '发货物流信息',
      `user_coupon_id` varchar(50) DEFAULT NULL COMMENT '用户优惠券id',
      `customer_notes` varchar(255) DEFAULT NULL COMMENT '用户备注',
      `merchant_notes` varchar(255) DEFAULT NULL COMMENT '商家备注',
      `sharer_openid` varchar(50) DEFAULT NULL COMMENT '分享员openid',
      `sharer_unionid` varchar(50) DEFAULT NULL COMMENT '分享员unionid',
      `sharer_type` tinyint(4) DEFAULT NULL COMMENT '分享员类型，0：普通分享员，1：店铺分享员',
      `share_scene` tinyint(1) DEFAULT NULL COMMENT '分享场景 1直播间 2橱窗 3短视频 4视频号主页 5商品详情页 6带商品的公众号文章',
      `commission_fee` decimal(12,2) DEFAULT NULL COMMENT '实际技术服务费',
      `predict_commission_fee` decimal(12,2) DEFAULT NULL COMMENT '预计技术服务费',
      `sku_sharer_infos` text COMMENT '分享员信息',
      `aftersale_detail` text COMMENT '授权账号信息',
      `ship_done_time` int(10) DEFAULT '0' COMMENT '发货时间',
      `deliver_method` tinyint(1) DEFAULT '0' COMMENT '发货方式 0普通物流 1虚拟发货',
      `settle_time` int(10) DEFAULT '0' COMMENT '结算时间',
      `ewaybill_order_id` varchar(50) DEFAULT NULL COMMENT '电子面单id',
      `commission_infos` text COMMENT '分佣信息',
      PRIMARY KEY (`id`),
      KEY `aid` (`aid`,`appid`,`order_id`)
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT='视频号小店——订单';");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_channels_order_goods` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT '0',
      `oid` int(11) DEFAULT NULL,
      `order_id` varchar(50) DEFAULT NULL,
      `product_id` bigint(20) DEFAULT NULL,
      `sku_id` bigint(20) DEFAULT NULL,
      `sku_cnt` int(11) DEFAULT NULL,
      `on_aftersale_sku_cnt` int(11) DEFAULT '0' COMMENT '正在售后/退款流程中的 sku 数量',
      `finish_aftersale_sku_cnt` int(11) DEFAULT '0' COMMENT '完成售后/退款的 sku 数量',
      `title` varchar(100) DEFAULT NULL,
      `thumb_img` varchar(255) DEFAULT NULL,
      `sale_price` decimal(12,2) DEFAULT NULL,
      `market_price` decimal(12,2) DEFAULT NULL,
      `sku_attrs` text COMMENT 'sku属性',
      `order_product_coupon_info_list` text COMMENT '商品优惠券信息',
      `delivery_deadline` int(10) DEFAULT NULL COMMENT '商品发货时效，超时此时间未发货即为发货超时',
      `product_cnt` int(11) DEFAULT '0' COMMENT '已发货数量',
      `iscommission` tinyint(1) DEFAULT '0' COMMENT '1已发放佣金',
      `real_price` decimal(12,2) DEFAULT '0.00' COMMENT '真实价格',
      `is_change_price` tinyint(1) DEFAULT '0' COMMENT '1 改价',
      `commission_data` text COMMENT '分销数据',
      `cacl_commission` tinyint(1) DEFAULT '0' COMMENT '1已计算分销', 
      `parent1` int(11) DEFAULT NULL,
      `parent2` int(11) DEFAULT NULL,
      `parent3` int(11) DEFAULT NULL,
      `parent4` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
      `parent1commission` decimal(11,2) DEFAULT '0.00',
      `parent2commission` decimal(11,2) DEFAULT '0.00',
      `parent3commission` decimal(11,2) DEFAULT '0.00',
      `parent4commission` decimal(11,2) DEFAULT NULL,
      `parent1score` decimal(12,3) DEFAULT '0.000',
      `parent2score` decimal(12,3) DEFAULT '0.000',
      `parent3score` decimal(12,3) DEFAULT '0.000',
      PRIMARY KEY (`id`),
      KEY `order_id` (`order_id`)
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT='视频号小店——订单商品';");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_channels_product` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `product_id` varchar(50) DEFAULT '' COMMENT '微信产品id',
      `aid` int(11) DEFAULT NULL,
      `appid` varchar(50) DEFAULT '0',
      `cat1` varchar(200) CHARACTER SET utf8 DEFAULT '0' COMMENT '分类id，可存储多分类用,间隔',
      `cat2` varchar(255) DEFAULT NULL,
      `cat3` varchar(255) CHARACTER SET utf8 DEFAULT '0' COMMENT '商家的商品分类',
      `gid` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
      `name` varchar(255) DEFAULT '',
      `sub_title` varchar(255) DEFAULT '' COMMENT '副标题',
      `procode` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
      `barcode` varchar(60) CHARACTER SET utf8 DEFAULT NULL,
      `sellpoint` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
      `pic` varchar(255) CHARACTER SET utf8 DEFAULT '',
      `pics` text CHARACTER SET utf8,
      `sales` int(11) DEFAULT '0',
      `paramdata` text CHARACTER SET utf8,
      `detail` longtext,
      `market_price` float(11,2) DEFAULT NULL,
      `sell_price` float(11,2) DEFAULT '0.00',
      `cost_price` float(11,2) DEFAULT '0.00',
      `weight` int(11) DEFAULT NULL,
      `sort` int(11) DEFAULT '0',
      `status` int(1) DEFAULT '1' COMMENT '0未上架，1上架，2按时间上架，3按周期上架',
      `stock` int(11) unsigned DEFAULT '100',
      `createtime` int(11) DEFAULT NULL,
      `comment_num` int(11) DEFAULT '0',
      `deliver_method` tinyint(1) DEFAULT '1' COMMENT '1无需快递 0快递发货',
      `template_id` varchar(50) CHARACTER SET utf8 DEFAULT NULL,
      `brand_id` varchar(100) CHARACTER SET utf8 NOT NULL DEFAULT '' COMMENT '品牌id',
      `remark` varchar(255) CHARACTER SET utf8 NOT NULL DEFAULT '' COMMENT '备注',
      `guige` varchar(100) CHARACTER SET utf8 NOT NULL DEFAULT '' COMMENT '规格',
      `keyword` varchar(255) DEFAULT NULL COMMENT '关键字',
      `seven_day_return` tinyint(1) NOT NULL DEFAULT '0' COMMENT '七天无理由退货 0不支持 1支持 2支持(定制商品除外)',
      `pay_after_use` tinyint(1) NOT NULL DEFAULT '0' COMMENT '先用后付 0不支持 1支持',
      `freight_insurance` tinyint(1) NOT NULL DEFAULT '0' COMMENT '运费险 0不支持 1支持',
      `desc_pics` text COMMENT '商品详情图片',
      `desc_text` text COMMENT '商品详情内容',
      `guigedata` text,
      `attrs` text,
      `aftersale_desc` text,
      `period_type` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0:无限购（默认），1:按自然日限购，2:按自然周限购，3:按自然月限购，4:按自然年限购',
      `limited_buy_num` int(11) DEFAULT '0' COMMENT '限购数量',
      `edit_status` tinyint(4) NOT NULL,
      `reason` text,
      `product_qua_infos` text COMMENT '商品资质列表',
      `audit_status` tinyint(1) DEFAULT '0' COMMENT '商品审核状态 2:审核不通过；3:审核通过；4:撤销审核。',
      `commissionset` tinyint(1) DEFAULT NULL,
      `commissiondata1` text,
      `commissiondata2` text,
      `commissiondata3` text,
      `commissiondata4` text,
      PRIMARY KEY (`id`) USING BTREE,
      KEY `appid` (`appid`) USING BTREE,
      KEY `cat1` (`cat1`) USING BTREE,
      KEY `gid` (`gid`) USING BTREE,
      KEY `status` (`status`) USING BTREE,
      KEY `stock` (`stock`) USING BTREE,
      KEY `aid` (`aid`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC COMMENT='视频号小店——产品';");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_channels_product_guige` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL,
      `proid` int(11) DEFAULT NULL,
      `name` varchar(255) DEFAULT NULL,
      `pic` varchar(255) DEFAULT NULL,
      `market_price` decimal(11,2) DEFAULT '0.00',
      `cost_price` decimal(11,2) DEFAULT '0.00',
      `sell_price` decimal(11,2) DEFAULT '0.00',
      `weight` int(11) DEFAULT NULL,
      `stock` int(11) unsigned DEFAULT '0',
      `procode` varchar(255) DEFAULT NULL,
      `sales` int(11) unsigned DEFAULT '0',
      `ks` varchar(255) DEFAULT NULL,
      `sku_attrs` text,
      `sku_id` varchar(50) DEFAULT NULL,
      `product_id` varchar(50) DEFAULT NULL,
      PRIMARY KEY (`id`),
      KEY `aid` (`aid`) USING BTREE,
      KEY `proid` (`proid`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC COMMENT='视频号小店——产品规格';");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_channels_product_stock` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL,
      `appid` varchar(50) DEFAULT NULL,
      `product_id` varchar(50) NOT NULL,
      `sku_id` varchar(50) DEFAULT NULL,
      `amount` int(11) NOT NULL,
      `op_type` tinyint(1) NOT NULL,
      `update_time` varchar(255) NOT NULL,
      `ext_info` text,
      PRIMARY KEY (`id`),
      KEY `aid` (`aid`,`appid`,`product_id`)
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT='视频号小店——库存流水';");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_channels_sharer` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL,
      `appid` varchar(50) DEFAULT NULL,
      `openid` varchar(50) DEFAULT NULL COMMENT '分享员openid',
      `nickname` varchar(50) DEFAULT NULL,
      `bind_time` int(10) DEFAULT NULL,
      `sharer_type` tinyint(1) DEFAULT NULL,
      `unionid` varchar(50) DEFAULT NULL COMMENT '分享员unionid',
      `mid` int(11) DEFAULT '0' COMMENT '会员id',
      PRIMARY KEY (`id`),
      KEY `aid` (`aid`,`appid`)
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT='视频号小店——分享员';");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_channels_share_orders` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL,
      `appid` int(11) DEFAULT NULL,
      `order_id` varchar(50) DEFAULT NULL COMMENT '订单号',
      `share_scene` int(11) DEFAULT NULL COMMENT '分享场景',
      `sharer_openid` varchar(50) DEFAULT NULL COMMENT '分享员openid',
      `sharer_type` int(11) DEFAULT '0' COMMENT '分享员类型',
      `sku_id` varchar(50) DEFAULT NULL COMMENT '商品sku_id',
      `product_id` varchar(50) DEFAULT NULL COMMENT '商品唯一id',
      `from_wecom` tinyint(1) DEFAULT '0' COMMENT '是否从企微分享',
      `promoter_id` varchar(50) DEFAULT NULL COMMENT '视频号唯一标识',
      `finder_nickname` varchar(100) DEFAULT NULL COMMENT '视频号昵称',
      `live_export_id` varchar(100) DEFAULT NULL COMMENT '直播间唯一标识',
      `video_export_id` varchar(100) DEFAULT NULL COMMENT '短视频唯一标识',
      `video_title` varchar(100) DEFAULT NULL COMMENT '短视频标题',
      PRIMARY KEY (`id`),
      KEY `aid` (`aid`,`appid`)
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT='视频号小店——分享员订单';");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_channels_user_coupon` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL,
      `appid` varchar(50) DEFAULT NULL,
      `openid` varchar(50) DEFAULT NULL COMMENT '用户openid',
      `unionid` varchar(50) DEFAULT NULL COMMENT '用户unionid',
      `user_coupon_id` varchar(50) DEFAULT NULL COMMENT '用户优惠券ID',
      `coupon_id` varchar(50) DEFAULT NULL COMMENT '优惠券ID',
      `status` int(11) DEFAULT NULL COMMENT '优惠券状态',
      `create_time` int(10) DEFAULT '0' COMMENT '优惠券派发时间',
      `update_time` int(10) DEFAULT '0' COMMENT '优惠券更新时间',
      `start_time` int(10) DEFAULT '0' COMMENT '优惠券生效时间',
      `end_time` int(10) DEFAULT '0' COMMENT '优惠券失效时间',
      `use_time` int(10) DEFAULT '0' COMMENT '优惠券核销时间',
      `order_id` int(11) DEFAULT '0' COMMENT '优惠券使用的订单id',
      `discount_fee` decimal(12,2) DEFAULT '0.00' COMMENT '优惠券金额',
      PRIMARY KEY (`id`),
      KEY `aid` (`aid`,`appid`)
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT='视频号小店——用户优惠券';");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_channels_withdrawlog` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) DEFAULT NULL,
      `appid` varchar(50) DEFAULT NULL,
      `withdraw_id` varchar(50) DEFAULT NULL,
      `amount` decimal(12,2) DEFAULT NULL COMMENT '金额',
      `create_time` int(10) DEFAULT NULL COMMENT '创建时间',
      `update_time` int(10) DEFAULT NULL COMMENT '更新时间',
      `reason` varchar(255) DEFAULT NULL COMMENT '失败原因',
      `remark` varchar(255) DEFAULT NULL COMMENT '备注',
      `bank_memo` varchar(255) DEFAULT NULL COMMENT '银行附言',
      `bank_name` varchar(255) DEFAULT NULL COMMENT '银行名称',
      `bank_num` varchar(100) DEFAULT NULL COMMENT '银行账户',
      `status` varchar(20) DEFAULT NULL COMMENT '提现状态',
      PRIMARY KEY (`id`),
      KEY `aid` (`aid`,`appid`)
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT='视频号小店——提现记录';");

    if(!pdo_fieldexists2("ddwx_admin_upload","channels_file_id")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_upload` ADD COLUMN `channels_file_id`  text CHARACTER SET utf8 COLLATE utf8_general_ci NULL;");
    }
    if(!pdo_fieldexists2("ddwx_admin_upload","other_param")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_upload` ADD COLUMN `other_param`  varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;");
    }
    if(!pdo_fieldexists2("ddwx_admin_upload","old_url")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_upload` ADD COLUMN `old_url`  varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;");
    }
    if(!pdo_fieldexists2("ddwx_admin_set","channels_order_fenxiao")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` ADD COLUMN `channels_order_fenxiao` tinyint(1) DEFAULT '0' COMMENT '视频号小店订单分销 0按商城推荐网发放 1按分享人发放 -1不发放';");
    }

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_ext_givescore_record`  (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `aid` int(11) DEFAULT NULL,
        `mid` int(11) DEFAULT NULL,
        `from_table` varchar(32) DEFAULT '',
        `from_id` int(11) DEFAULT NULL,
        `score` int(11) DEFAULT NULL,
        `createtime` int(11) DEFAULT NULL,
        `type` varchar(32) DEFAULT '',
        PRIMARY KEY (`id`) USING BTREE,
        KEY `aid_mid` (`aid`,`mid`)
	) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_channels_reservation_live`  (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `aid` int(11) NOT NULL,
        `shop_id` int(11) NOT NULL COMMENT '视频号小店ID',
        `live_start_time` int(11) NULL DEFAULT 0 COMMENT '开播时间',
        `content` varchar(80) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '直播主题',
        `reserve_num` int(11) NULL DEFAULT 0 COMMENT '报名人数',
        `noticeId` varchar(300) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '微信返回：预告 id',
        `reservable` tinyint(1) NULL DEFAULT NULL COMMENT '微信返回：是否可预约 ',
        `headUrl` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '微信返回：直播封面',
        `startTime` char(10) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '微信返回：开始时间',
        `nickname` varchar(30) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '微信返回：昵称',
        `status` tinyint(1) NULL DEFAULT 0 COMMENT '微信返回：预告状态：0可用 1取消 2已用',
        `matching` tinyint(1) NULL DEFAULT 0 COMMENT '匹配更新 1：匹配 0：未匹配',
        `live_status` tinyint(1) NULL DEFAULT 0 COMMENT '直播状态 0：直播预告 1：超时未开播 2：取消直播预告 3：已开播 4：直播结束',
        PRIMARY KEY (`id`) USING BTREE,
        INDEX `aid`(`aid`) USING BTREE
	) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_channels_reservation_live_set`  (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `aid` int(11) NOT NULL,
        `desc` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '活动介绍/顶部描述',
        `bgpic` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '背景图',
        `bgcolor` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '背景颜色',
        `fontcolor` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '字体颜色',
        `guize` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL COMMENT '活动规则',
        `give_score` tinyint(1) NULL DEFAULT 0 COMMENT '预约直播送积分 1:开启 0：关闭',
        `reservation_give_score` int(11) NULL DEFAULT 0 COMMENT '预约赠送积分',
        `day_give_score` int(11) NULL DEFAULT 0 COMMENT '每日每个会员最多赠送积分数量',
        `give_coupon` tinyint(1) NULL DEFAULT 0 COMMENT '赠送优惠券 1:开启 0:关闭',
        `coupon_list` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '预约直播送优惠券',
        `sharetitle` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '分享标题',
        `sharelink` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '分享链接',
        `sharepic` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '分享图标',
        `sharedesc` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '分享描述',
        PRIMARY KEY (`id`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;");

    \think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_channels_reservation_record`  (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `aid` int(11) NULL DEFAULT NULL,
      `lid` int(11) NULL DEFAULT NULL COMMENT '直播id',
      `mid` int(11) NULL DEFAULT NULL,
      `headimg` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
      `nickname` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
      `status` tinyint(1) NULL DEFAULT 0 COMMENT '预约状态 0:未预约 1：成功预约 2：取消预约',
      `create_time` int(11) NULL DEFAULT NULL COMMENT '预约时间',
      PRIMARY KEY (`id`) USING BTREE,
      INDEX `aid`(`aid`) USING BTREE,
      INDEX `lid`(`lid`) USING BTREE,
      INDEX `mid`(`mid`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;");

    if(pdo_fieldexists2("ddwx_channels_category_basic","name")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_channels_category_basic` MODIFY COLUMN `name`  varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;");
    }
    if(pdo_fieldexists2("ddwx_channels_category","name")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_channels_category` MODIFY COLUMN `name`  varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;");
    }
    if(!pdo_fieldexists2("ddwx_channels_brand_basic","next_key")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_channels_brand_basic` ADD COLUMN `next_key` bigint(20) NOT NULL DEFAULT '0';");
    }
    if(!pdo_fieldexists2("ddwx_channels_order_goods","aid")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_channels_order_goods` ADD COLUMN `aid`  int NULL DEFAULT 0 AFTER `id`;");
    }
    if(!pdo_fieldexists2("ddwx_channels_order_goods","parent1")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_channels_order_goods` ADD COLUMN `parent1`  int(11) NULL DEFAULT NULL;");
    }
    if(!pdo_fieldexists2("ddwx_channels_order_goods","parent2")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_channels_order_goods` ADD COLUMN `parent2`  int(11) NULL DEFAULT NULL;");
    }
    if(!pdo_fieldexists2("ddwx_channels_order_goods","parent3")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_channels_order_goods` ADD COLUMN `parent3`  int(11) NULL DEFAULT NULL;");
    }
    if(!pdo_fieldexists2("ddwx_channels_order_goods","parent4")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_channels_order_goods` ADD COLUMN `parent4`  int(11) NULL DEFAULT NULL;");
    }
    if(!pdo_fieldexists2("ddwx_channels_order_goods","parent1commission")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_channels_order_goods` ADD COLUMN `parent1commission`  decimal(11,2) NULL DEFAULT 0.00;");
    }
    if(!pdo_fieldexists2("ddwx_channels_order_goods","parent2commission")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_channels_order_goods` ADD COLUMN `parent2commission`  decimal(11,2) NULL DEFAULT 0.00;");
    }
    if(!pdo_fieldexists2("ddwx_channels_order_goods","parent3commission")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_channels_order_goods` ADD COLUMN `parent3commission`  decimal(11,2) NULL DEFAULT 0.00;");
    }
    if(!pdo_fieldexists2("ddwx_channels_order_goods","parent4commission")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_channels_order_goods` ADD COLUMN `parent4commission`  decimal(11,2) NULL DEFAULT 0.00;");
    }
    if(!pdo_fieldexists2("ddwx_channels_order_goods","parent1score")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_channels_order_goods` ADD COLUMN `parent1score`  decimal(11,2) NULL DEFAULT 0.00;");
    }
    if(!pdo_fieldexists2("ddwx_channels_order_goods","parent2score")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_channels_order_goods` ADD COLUMN `parent2score`  decimal(11,2) NULL DEFAULT 0.00;");
    }
    if(!pdo_fieldexists2("ddwx_channels_order_goods","parent3score")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_channels_order_goods` ADD COLUMN `parent3score`  decimal(11,2) NULL DEFAULT 0.00;");
    }
    \think\facade\Db::execute("ALTER TABLE `ddwx_channels_category` DROP INDEX `appid` , ADD INDEX `appid` (`appid`, `cat_id`, `aid`) USING BTREE ;");
    if(pdo_fieldexists2("ddwx_channels_share_orders","appid")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_channels_share_orders` MODIFY COLUMN `appid`  varchar(50) NULL DEFAULT NULL ;");
    }
}

