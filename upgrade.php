<?php

function pdo_fieldexists2($tablename, $fieldname){
	$fields = \think\facade\Db::query("SHOW COLUMNS FROM " . $tablename);
	if(empty($fields)){
		return false;
	}
	foreach ($fields as $field) {
		if ($fieldname == $field['Field']){
			return true;
		}
	}
	return false;
}

//检查表是否存在
 function pdo_fieldexists3($tablename){
	$table = \think\facade\Db::query("SHOW TABLES LIKE '". $tablename."'");
	if(empty($table)){
		return false;
	}else{
		return true;
	}
}

//检查索引是否存在
function pdo_indexExists($tablename, $indexname){
    $table = \think\facade\Db::query("SHOW INDEX FROM " . $tablename. " WHERE key_name = '" .$indexname. "'");
    if(empty($table)){
        return false;
    }else{
        return true;
    }
}

/*
if(!pdo_fieldexists2("ddwx_wifiprint_set", "printauto3")) {
	\think\facade\Db::execute("ALTER TABLE ddwx_wifiprint_set ADD `printauto3` tinyint(1) DEFAULT '1';");
}
\think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_test` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `value` longtext,
  PRIMARY KEY (`id`),
  KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
*/

if(file_exists('upgrade_restaurant.php')){
	require('upgrade_restaurant.php');
}

if(!pdo_fieldexists2("ddwx_member", "yqcode")){
	require('upgrade_1.php');
}

if(!pdo_fieldexists2("ddwx_member_level","up_catid")) {
	require('upgrade_2.php');
}

if(getcustom('extend_tour')) {
    if (!pdo_fieldexists3("ddwx_tour_ecs_region")) {
    	require('upgrade_3.php');
    }
}

if(!pdo_fieldexists2("ddwx_shop_order_goods","gg_group_title")){
	require('upgrade_4.php');
}
if(file_exists(ROOT_PATH.'custom.php')){
    $custom = include(ROOT_PATH.'custom.php');
    if($custom){
        require('upgrade_c1.php');
        require('upgrade_c2.php');
    }
    if(getcustom('wx_channels')){
        require('upgrade_wxchannels.php');
    }
}


if(!pdo_fieldexists2("ddwx_admin","ali_appcode_choose")) {
	\think\facade\Db::execute("ALTER TABLE `ddwx_admin` ADD COLUMN `ali_appcode_choose` tinyint(1) DEFAULT '1' COMMENT '1跟随系统0独立设置';");
}
if(!pdo_fieldexists2("ddwx_admin_set","ali_appcode")) {
	\think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` ADD COLUMN `ali_appcode` varchar(60) DEFAULT NULL COMMENT '快递查询code';");
}
\think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_ali_wuliu` (
`id` int(11) NOT NULL AUTO_INCREMENT,
`aid` int(11) DEFAULT NULL,
`bid` int(11) DEFAULT '0',
`type` varchar(50) DEFAULT NULL COMMENT '物流类型',
`no` varchar(50) DEFAULT NULL COMMENT '物流单号',
`content` text COMMENT '内容',
`createtime` int(11) DEFAULT NULL COMMENT '创建时间',
PRIMARY KEY (`id`),
KEY `aid` (`aid`),
KEY `bid` (`bid`),
KEY `type` (`type`),
KEY `no` (`no`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='物流查询记录';");
\think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_ordernum_change_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `aid` int(11) DEFAULT NULL,
  `bid` int(11) DEFAULT '0',
  `mid` int(11) DEFAULT NULL,
  `tablename` varchar(60) DEFAULT NULL,
  `orderid` int(11) DEFAULT NULL,
  `ordernum` varchar(100) DEFAULT NULL,
  `ordernum_new` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `bid` (`bid`) USING BTREE,
  KEY `mid` (`mid`) USING BTREE,
  KEY `tablename` (`tablename`) USING BTREE,
  KEY `orderid` (`orderid`) USING BTREE,
  KEY `ordernum` (`ordernum`) USING BTREE,
  KEY `ordernum_new` (`ordernum_new`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT '订单号修改记录';");

if(!pdo_fieldexists2("ddwx_business","bottomImg")) {
    \think\facade\Db::execute("ALTER TABLE `ddwx_business` ADD COLUMN `bottomImg` varchar(255) DEFAULT '' COMMENT '商品详情公共底部图片';");
}

if(!pdo_fieldexists2("ddwx_shop_sysset","classify_show_stock")) {
    \think\facade\Db::execute("ALTER TABLE `ddwx_shop_sysset` ADD COLUMN `classify_show_stock` tinyint(1) DEFAULT '0';");
}

if(!pdo_fieldexists2("ddwx_shop_sysset","shipping_pagenum")) {
    \think\facade\Db::execute("ALTER TABLE `ddwx_shop_sysset` 
    	ADD COLUMN `shipping_pagenum` int(3) NOT NULL DEFAULT '18' COMMENT '送货单每页行数',
    	ADD COLUMN `shipping_linenum` int(3) NOT NULL DEFAULT '2' COMMENT '送货单品名及规格行数';");
}
\think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_express_data` (
	`id` int(11) NOT NULL AUTO_INCREMENT,
	`aid` int(11) NOT NULL DEFAULT 0,
	`bid` int(11) NOT NULL DEFAULT 0,
	`express_data` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '快递数据',
	`createtime` int(11) UNSIGNED NOT NULL DEFAULT 0,
	`updatetime` int(11) UNSIGNED NOT NULL DEFAULT 0,
	PRIMARY KEY (`id`) USING BTREE,
	INDEX `aid`(`aid`) USING BTREE,
	INDEX `bid`(`bid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT '自定义快递数据';");
if(!pdo_fieldexists2("ddwx_business","shipping_pagenum")) {
    \think\facade\Db::execute("ALTER TABLE `ddwx_business` 
    	ADD COLUMN `shipping_pagetitle` varchar(100) NOT NULL DEFAULT '送货单' COMMENT '送货单名称',
    	ADD COLUMN `shipping_pagenum` int(3) NOT NULL DEFAULT '18' COMMENT '送货单每页行数',
    	ADD COLUMN `shipping_linenum` int(3) NOT NULL DEFAULT '2' COMMENT '送货单品名及规格行数';");
}
if(!pdo_fieldexists2("ddwx_shop_product","contact_require")) {
    \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product` 
    	ADD COLUMN `contact_require` tinyint(1) NULL DEFAULT 0 COMMENT '自动发货|在线卡密是否必填联系人信息 0 否 1 是' AFTER `freighttype`;");
}
if(!pdo_fieldexists2("ddwx_collage_product","contact_require")) {
    \think\facade\Db::execute("ALTER TABLE `ddwx_collage_product` 
    	ADD COLUMN `contact_require` tinyint(1) NULL DEFAULT 0 COMMENT '自动发货|在线卡密是否必填联系人信息 0 否 1 是' AFTER `freighttype`;");
}
if(!pdo_fieldexists2("ddwx_kanjia_product","contact_require")) {
    \think\facade\Db::execute("ALTER TABLE `ddwx_kanjia_product` 
    	ADD COLUMN `contact_require` tinyint(1) NULL DEFAULT 0 COMMENT '自动发货|在线卡密是否必填联系人信息 0 否 1 是' AFTER `freighttype`;");
}
if(!pdo_fieldexists2("ddwx_seckill_product","contact_require")) {
    \think\facade\Db::execute("ALTER TABLE `ddwx_seckill_product` 
    	ADD COLUMN `contact_require` tinyint(1) NULL DEFAULT 0 COMMENT '自动发货|在线卡密是否必填联系人信息 0 否 1 是' AFTER `freighttype`;");
}
if(!pdo_fieldexists2("ddwx_tuangou_product","contact_require")) {
    \think\facade\Db::execute("ALTER TABLE `ddwx_tuangou_product` 
    	ADD COLUMN `contact_require` tinyint(1) NULL DEFAULT 0 COMMENT '自动发货|在线卡密是否必填联系人信息 0 否 1 是' AFTER `freighttype`;");
}
if(!pdo_fieldexists2("ddwx_scoreshop_product","contact_require")) {
    \think\facade\Db::execute("ALTER TABLE `ddwx_scoreshop_product` 
    	ADD COLUMN `contact_require` tinyint(1) NULL DEFAULT 0 COMMENT '自动发货|在线卡密是否必填联系人信息 0 否 1 是' AFTER `freighttype`;");
}
if(!pdo_fieldexists2("ddwx_lucky_collage_product","contact_require")) {
    \think\facade\Db::execute("ALTER TABLE `ddwx_lucky_collage_product` 
    	ADD COLUMN `contact_require` tinyint(1) NULL DEFAULT 0 COMMENT '自动发货|在线卡密是否必填联系人信息 0 否 1 是' AFTER `freighttype`;");
}
if(!pdo_fieldexists2("ddwx_admin_set","miandan_wx")) {
    \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` 
    	ADD COLUMN `miandan_wx` tinyint(1)  NOT NULL DEFAULT 0 COMMENT '是否优先使用微信物流助手查询物流轨迹 0 否 1 是';");
}

//商品名称兼容emoj表情
\think\facade\Db::execute("ALTER TABLE `ddwx_collage_product` MODIFY COLUMN `name`  varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL;");
\think\facade\Db::execute("ALTER TABLE `ddwx_collage_product` MODIFY COLUMN `sellpoint`  varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL;");
\think\facade\Db::execute("ALTER TABLE `ddwx_collage_order` DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_general_ci;");
\think\facade\Db::execute("ALTER TABLE `ddwx_collage_order` MODIFY COLUMN `title`  varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL;");
\think\facade\Db::execute("ALTER TABLE `ddwx_kanjia_product` MODIFY COLUMN `name`  varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL;");
\think\facade\Db::execute("ALTER TABLE `ddwx_kanjia_order` DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_general_ci;");
\think\facade\Db::execute("ALTER TABLE `ddwx_kanjia_order` MODIFY COLUMN `title`  varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL;");
\think\facade\Db::execute("ALTER TABLE `ddwx_seckill_product` MODIFY COLUMN `name`  varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL;");
\think\facade\Db::execute("ALTER TABLE `ddwx_seckill_order` DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_general_ci;");
\think\facade\Db::execute("ALTER TABLE `ddwx_seckill_order` MODIFY COLUMN `title`  varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL;");
\think\facade\Db::execute("ALTER TABLE `ddwx_tuangou_product` MODIFY COLUMN `name`  varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL;");
\think\facade\Db::execute("ALTER TABLE `ddwx_tuangou_product` MODIFY COLUMN `sellpoint`  varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL;");
\think\facade\Db::execute("ALTER TABLE `ddwx_tuangou_order` DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_general_ci;");
\think\facade\Db::execute("ALTER TABLE `ddwx_tuangou_order` MODIFY COLUMN `title`  varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL;");
\think\facade\Db::execute("ALTER TABLE `ddwx_scoreshop_product` MODIFY COLUMN `name`  varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL;");
\think\facade\Db::execute("ALTER TABLE `ddwx_scoreshop_product` MODIFY COLUMN `sellpoint`  varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL;");
\think\facade\Db::execute("ALTER TABLE `ddwx_scoreshop_order` DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_general_ci;");
\think\facade\Db::execute("ALTER TABLE `ddwx_scoreshop_order` MODIFY COLUMN `title`  varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL;");
\think\facade\Db::execute("ALTER TABLE `ddwx_choujiang` MODIFY COLUMN `name`  varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '幸运大转盘活动开始啦';");
\think\facade\Db::execute("ALTER TABLE `ddwx_choujiang` MODIFY COLUMN `guize`  text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL COMMENT '活动规则';");
\think\facade\Db::execute("ALTER TABLE `ddwx_lucky_collage_product` MODIFY COLUMN `name`  varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;");
\think\facade\Db::execute("ALTER TABLE `ddwx_lucky_collage_product` MODIFY COLUMN `sellpoint`  varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;");
\think\facade\Db::execute("ALTER TABLE `ddwx_lucky_collage_order` DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_general_ci;");
\think\facade\Db::execute("ALTER TABLE `ddwx_lucky_collage_order` MODIFY COLUMN `title`  varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL;");
if(!pdo_fieldexists2("ddwx_shop_sysset","price_show_type")){
    \think\facade\Db::execute("ALTER TABLE `ddwx_shop_sysset` ADD COLUMN `price_show_type` tinyint(1) DEFAULT '0' COMMENT '价格显示方式';");
}
if(!pdo_fieldexists2("ddwx_yuyue_product","tichengtype")) {
    \think\facade\Db::execute("ALTER TABLE `ddwx_yuyue_product` ADD COLUMN `tichengtype` tinyint(1) DEFAULT '0' COMMENT '提成金额结算';");
}
//修复会员表path以逗号开头的数据（Db::raw( field(id,’.$member['path'.')')会报错）
$lists =  \think\facade\Db::name('member')->where('path like ",%"')->field('id,path')->select();
if($lists){
    foreach($lists as $v){
        $path = ltrim($v['path'],',');
        \think\facade\Db::name('member')->where('id',$v['id'])->update(['path'=>$path]);
    }
}
$lists =  \think\facade\Db::name('member')->where('path_origin like ",%"')->field('id,path_origin')->select();
if($lists){
    foreach($lists as $v){
        $path_origin = ltrim($v['path_origin'],',');
        \think\facade\Db::name('member')->where('id',$v['id'])->update(['path_origin'=>$path_origin]);
    }
}
if(!pdo_fieldexists2("ddwx_member_levelup_order","type")) {
    \think\facade\Db::execute("ALTER TABLE `ddwx_member_levelup_order` ADD COLUMN `type` tinyint(1) NOT NULL DEFAULT '0' COMMENT '记录类型 0升级 1降级';");
}

\think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_admin_wxicp` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `aid` int(11) NOT NULL,
  `appid` varchar(30) DEFAULT '' COMMENT '关联小程序id',
  `face_verify_task_id` varchar(100) DEFAULT NULL COMMENT '人脸核身任务id',
  `face_is_finish` tinyint(1) DEFAULT '0' COMMENT '核身任务是否已经完成 0 否 1 是',
  `face_status` tinyint(1) DEFAULT '0' COMMENT '任务状态 0. 未开始；1. 等待中；2. 失败；3. 成功',
  `face_finish_time` int(11) DEFAULT NULL COMMENT '核验通知时间',
  `face_send_time` int(11) DEFAULT NULL COMMENT '核验通知发起时间，用于验证通知有效期，避免重复发起',
  `beian_status` varchar(100) DEFAULT NULL COMMENT '备案状态值',
  `beian_reason` text COMMENT '备案返回内容',
  `subject_province` varchar(100) DEFAULT NULL,
  `subject_city` varchar(100) DEFAULT NULL,
  `subject_district` varchar(100) DEFAULT NULL,
  `subject_address` varchar(100) DEFAULT NULL,
  `subject_comment` varchar(200) DEFAULT NULL,
  `subject_type` varchar(100) DEFAULT NULL,
  `subject_name` varchar(100) DEFAULT NULL,
  `subject_certificate_type` varchar(100) DEFAULT NULL,
  `subject_certificate_photo` varchar(255) DEFAULT NULL,
  `subject_certificate_photo_media_id` varchar(100) DEFAULT '',
  `subject_certificate_address` varchar(255) DEFAULT NULL,
  `subject_certificate_number` varchar(100) DEFAULT NULL,
  `subject_residence_permit` varchar(255) DEFAULT NULL,
  `subject_residence_permit_media_id` varchar(100) DEFAULT '',
  `principal_name` varchar(100) DEFAULT NULL,
  `principal_mobile` varchar(20) DEFAULT NULL,
  `principal_emergency_contact` varchar(20) DEFAULT NULL,
  `principal_email` varchar(50) DEFAULT NULL,
  `principal_certificate_type` varchar(100) DEFAULT NULL,
  `principal_certificate_photo_front` varchar(255) DEFAULT NULL,
  `principal_certificate_photo_front_media_id` varchar(100) DEFAULT '',
  `principal_certificate_photo_back` varchar(255) DEFAULT NULL,
  `principal_certificate_photo_back_media_id` varchar(100) DEFAULT '',
  `principal_certificate_number` varchar(100) DEFAULT NULL,
  `principal_certificate_validity_date_start` varchar(50) DEFAULT NULL,
  `principal_certificate_validity_date_end` varchar(50) DEFAULT NULL,
  `principal_certificate_validity_date_cq` tinyint(1) DEFAULT '0',
  `principal_authorization_letter_media_id` varchar(100) DEFAULT '',
  `principal_authorization_letter` varchar(255) DEFAULT NULL,
  `legal_person_name` varchar(50) DEFAULT NULL,
  `legal_person_certificate_number` varchar(20) DEFAULT NULL,
  `service_content_types` varchar(255) DEFAULT NULL,
  `nrlx_details` text COMMENT '[{\"type\":\"\",\"code\":\"\",\"media\":\"\",\"media_id\":\"\"}]',
  `app_comment` varchar(200) DEFAULT NULL,
  `manager_name` varchar(100) DEFAULT NULL,
  `manager_mobile` varchar(20) DEFAULT NULL,
  `manager_email` varchar(50) DEFAULT NULL,
  `manager_emergency_contact` varchar(20) DEFAULT NULL,
  `manager_certificate_type` varchar(100) DEFAULT NULL,
  `manager_certificate_photo_front` varchar(255) DEFAULT NULL,
  `manager_certificate_photo_front_media_id` varchar(100) DEFAULT '',
  `manager_certificate_photo_back` varchar(255) DEFAULT NULL,
  `manager_certificate_photo_back_media_id` varchar(100) DEFAULT '',
  `manager_certificate_number` varchar(50) DEFAULT NULL,
  `manager_certificate_validity_date_start` varchar(20) DEFAULT NULL,
  `manager_certificate_validity_date_end` varchar(20) DEFAULT NULL,
  `manager_certificate_validity_date_cq` tinyint(1) DEFAULT '0' COMMENT '是否长期',
  `manager_authorization_letter` varchar(255) DEFAULT NULL,
  `manager_authorization_letter_media_id` varchar(100) DEFAULT '',
  `commitment_letter` varchar(255) DEFAULT NULL,
  `commitment_letter_media_id` varchar(100) DEFAULT '',
  `business_name_change_letter` varchar(255) DEFAULT NULL,
  `business_name_change_letter_media_id` varchar(100) DEFAULT '',
  `create_time` int(11) DEFAULT NULL,
  `update_time` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `aid` (`aid`,`appid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC COMMENT='小程序备案记录';");

if (!pdo_fieldexists2("ddwx_admin_wxicp", "beian_reason")) {
    \think\facade\Db::execute("ALTER TABLE `ddwx_admin_wxicp` ADD COLUMN `beian_reason` text COMMENT '备案返回内容';");
}

\think\facade\Db::execute('update ddwx_admin_user set auth_data=replace(auth_data,\'getproduct,Member\\\/index\"\',\'getproduct,Member\\\/choosemember\"\')');//录入订单
\think\facade\Db::execute('update ddwx_admin_user set auth_data=replace(auth_data,\'setst,Member\\\/choosemember\"\',\'setst,Member\\\/choosemember,Member\\\/check\"\')');
\think\facade\Db::execute('update ddwx_admin_user set auth_data=replace(auth_data,\'getproduct,Member\\\/choosemember,Member\\\/check\"\',\'getproduct,Member\\\/choosemember\"\')');//错误替换恢复 2.6.0 移除

if (!pdo_fieldexists2("ddwx_mp_tmplset", "tmpl_message_link")) {
    \think\facade\Db::execute("ALTER TABLE `ddwx_mp_tmplset` ADD COLUMN `tmpl_message_link` varchar(11) DEFAULT 'mp' COMMENT '模板消息链接 mp公众号 wx小程序';");
}
if (!pdo_fieldexists2("ddwx_mp_tmplset_new", "tmpl_message_link")) {
    \think\facade\Db::execute("ALTER TABLE `ddwx_mp_tmplset_new` ADD COLUMN `tmpl_message_link` varchar(11) DEFAULT 'mp' COMMENT '模板消息链接 mp公众号 wx小程序';");
}
if (!pdo_fieldexists2("ddwx_coupon", "bg_color")) {
    \think\facade\Db::execute("ALTER TABLE `ddwx_coupon` 
    ADD COLUMN `font_color` varchar(10) DEFAULT '#2B2B2B' COMMENT '字体颜色',
    ADD COLUMN `title_color` varchar(10) DEFAULT '#2B2B2B' COMMENT '标题颜色',
    ADD COLUMN `bg_color` varchar(10) DEFAULT '#FFFFFF' COMMENT '背景颜色';");
}
if (!pdo_fieldexists2("ddwx_shop_product", "print_name")) {
    \think\facade\Db::execute("ALTER TABLE `ddwx_shop_product` 
    ADD COLUMN `print_name` varchar(50) DEFAULT NULL COMMENT '小票打印机标题';");
}
\think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_video_list` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `aid` int(11) DEFAULT NULL,
  `bid` int(11) DEFAULT '0',
  `name` varchar(255) DEFAULT NULL,
  `pic` varchar(255) DEFAULT NULL,
  `status` int(1) DEFAULT '1',
  `sort` int(11) DEFAULT '1',
  `createtime` int(11) DEFAULT NULL,
  `type` tinyint(4) DEFAULT '0' COMMENT '0本地视频 1微信视频号视频',
  `video_url` varchar(255) DEFAULT '',
  `video_duration` decimal(10,2) DEFAULT '0.00',
  `ext_param` text COMMENT '扩展数据，json格式',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `bid` (`bid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC COMMENT='视频管理';");
//原单独表的降级记录合并到升级记录表中
$lists = \think\facade\Db::name('member_leveldown_record')->where('1=1')->select()->toArray();
if($lists){
    $data_all = [];
    foreach($lists as $k=>$v){
        $data = $v;
        unset($data['id']);
        unset($data['remark']);
        $data['levelup_time'] = $v['createtime'];
        $data['type'] = 1;
        $data['form0'] = $v['remark'];
        $data_all[] = $data;
    }
    \think\facade\Db::name('member_levelup_order')->insertAll($data_all);
    \think\facade\Db::execute('TRUNCATE TABLE ddwx_member_leveldown_record');
}
if (!pdo_fieldexists2("ddwx_admin_setapp_alipay", "openid_set")) {
    \think\facade\Db::execute("ALTER TABLE `ddwx_admin_setapp_alipay` ADD COLUMN `openid_set` varchar(10) NULL DEFAULT 'userid' COMMENT 'openid配置管理，userid、openid';");
}
if (!pdo_fieldexists2("ddwx_payorder", "refund_money")) {
    \think\facade\Db::execute("ALTER TABLE `ddwx_payorder` 
        ADD COLUMN `refund_money` decimal(11,2) DEFAULT '0.00' COMMENT '退款金额',
        ADD COLUMN `refund_time` int(11) DEFAULT '0' COMMENT '退款时间';");
}

if(!pdo_fieldexists2("ddwx_collage_sysset","team_refund")) {
    \think\facade\Db::execute("ALTER TABLE `ddwx_collage_sysset` ADD COLUMN `team_refund` tinyint(1) NOT NULL DEFAULT 0 COMMENT '团长发起订单退款 0:不解散团 1：解散团';");
}

if (!pdo_fieldexists2("ddwx_coupon", "buyyuyueprogive")) {
    \think\facade\Db::execute("ALTER TABLE `ddwx_coupon` 
        ADD COLUMN `buyyuyueprogive` tinyint(1) NULL DEFAULT 0 COMMENT '购买服务商品赠送 0:关闭 1：开启',
        ADD COLUMN `buyyuyueproids` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '购买服务商品集合',
        ADD COLUMN `buyyuyuepro_give_num` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '购买服务商品数量';");
}

if (!pdo_fieldexists2("ddwx_toupiao", "jump_url")) {
    \think\facade\Db::execute("ALTER TABLE `ddwx_toupiao` 
    ADD COLUMN `jump_url` varchar(255) DEFAULT '' COMMENT '投票成功后跳转链接';");
}

if (!pdo_fieldexists2("ddwx_restaurant_takeaway_sysset", "auto_send_order")) {
    \think\facade\Db::execute("ALTER TABLE `ddwx_restaurant_takeaway_sysset` 
    ADD COLUMN `auto_send_order` tinyint(1) DEFAULT 0 COMMENT '自动发单 1:开启 0:关闭';");
}

if (pdo_indexExists("ddwx_restaurant_deposit_order", "order_no")) {
    \think\facade\Db::execute("ALTER TABLE `ddwx_restaurant_deposit_order` 
    DROP INDEX `order_no`,
    ADD INDEX `name`(`name`) USING BTREE;");
}

\think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_member_score_scoreinlog` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `aid` int(11) NOT NULL,
  `bid` int(11) NOT NULL DEFAULT 0,
  `mid` int(1) NOT NULL DEFAULT 0,
  `type` varchar(20) NOT NULL DEFAULT '' COMMENT '类型',
  `orderid` int(11) NOT NULL DEFAULT 0 COMMENT '订单id',
  `ordernum` varchar(50) NOT NULL DEFAULT '' COMMENT '订单号',
  `score` decimal(12, 3) NOT NULL DEFAULT 0.000,
  `residue` decimal(12, 3) NOT NULL DEFAULT 0.00 COMMENT '剩余积分',
  `createtime` int(11) UNSIGNED NOT NULL DEFAULT 0,
  `updatetime` int(11) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `aid`(`aid`) USING BTREE,
  INDEX `mid`(`mid`) USING BTREE,
  INDEX `orderid`(`orderid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC COMMENT='会员消费赠送积分记录';");

if (!pdo_fieldexists2("ddwx_admin_set", "maidan_payaftertourl")) {
    \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set`  ADD COLUMN `maidan_payaftertourl` text DEFAULT NULL;");
}
if (!pdo_fieldexists2("ddwx_restaurant_takeaway_freight", "peisong_lng2")) {
    \think\facade\Db::execute("ALTER TABLE `ddwx_restaurant_takeaway_freight`  
        ADD COLUMN `peisong_lng2` varchar(50) DEFAULT NULL,
        ADD COLUMN `peisong_lat2` varchar(50) DEFAULT NULL;");
}

\think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_wxpay_fzlog` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `aid` int(11) DEFAULT NULL,
  `bid` int(11) DEFAULT '0',
  `mid` int(11) DEFAULT NULL,
  `logid` int(11) DEFAULT NULL,
  `openid` varchar(255) DEFAULT NULL,
  `tablename` varchar(255) DEFAULT NULL COMMENT '字段为ordertype,非表名',
  `ordernum` varchar(255) DEFAULT NULL,
  `mch_id` varchar(100) DEFAULT NULL,
  `sub_mchid` varchar(100) DEFAULT NULL,
  `transaction_id` varchar(255) DEFAULT NULL,
  `out_order_no` varchar(100) DEFAULT NULL,
  `receiversjson` text,
  `platform` varchar(255) DEFAULT NULL,
  `createtime` int(11) DEFAULT NULL,
  `fenzhangmoney` decimal(11,2) DEFAULT '0.00',
  `fenzhangmoney2` decimal(11,2) DEFAULT '0.00',
  `isfenzhang` tinyint(1) DEFAULT '0' COMMENT '0待分账，1已分账，2分账失败，3退款退回，4取消分账',
  `fz_ordernum` varchar(100) DEFAULT NULL,
  `fz_errmsg` varchar(255) DEFAULT NULL,
  `isfinish` tinyint(1) DEFAULT '0' COMMENT '0未结束，1已结束',
  `finish_error_times` int(5) DEFAULT '0',
  `finish_error_time` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `aid` (`aid`) USING BTREE,
  KEY `mid` (`mid`) USING BTREE,
  KEY `logid` (`logid`) USING BTREE,
  KEY `transaction_id` (`transaction_id`) USING BTREE,
  KEY `createtime` (`createtime`) USING BTREE,
  KEY `isfenzhang` (`isfenzhang`) USING BTREE,
  KEY `isfinish` (`isfinish`) USING BTREE,
  KEY `finish_error_times` (`finish_error_times`),
  KEY `finish_error_time` (`finish_error_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;");
if (!pdo_fieldexists2("ddwx_business", "maidan_payaftertourl")) {
    \think\facade\Db::execute("ALTER TABLE `ddwx_business`  ADD COLUMN `maidan_payaftertourl` text DEFAULT NULL;");
}
if (!pdo_fieldexists2("ddwx_cashback_member", "type")) {
    \think\facade\Db::execute("ALTER TABLE `ddwx_cashback_member` ADD COLUMN `type` varchar(30) NOT NULL DEFAULT 'shop' COMMENT '订单类型';");
    \think\facade\Db::execute("ALTER TABLE `ddwx_cashback_member_log` ADD COLUMN `type` varchar(30) NOT NULL DEFAULT 'shop' COMMENT '订单类型';");
}
if (!pdo_fieldexists2("ddwx_payaftergive", "bid")) {
    \think\facade\Db::execute("ALTER TABLE `ddwx_payaftergive` ADD COLUMN `bid` int(11) NULL DEFAULT '0' AFTER `aid`;");
}
if (!pdo_fieldexists2("ddwx_yuyue_product", "is_open")) {
    \think\facade\Db::execute("ALTER TABLE `ddwx_yuyue_product` 
        ADD COLUMN `is_open` tinyint(1) NOT NULL DEFAULT 1 COMMENT '显示状态  0：停业 1：营业',
        ADD COLUMN `noopentip` varchar(100) NOT NULL DEFAULT '' COMMENT '停业提示' ;");
}
if (!pdo_fieldexists2("ddwx_yuyue_product", "opentip")) {
    \think\facade\Db::execute("ALTER TABLE `ddwx_yuyue_product` ADD COLUMN `opentip` varchar(100) NOT NULL DEFAULT '' COMMENT '营业提示';");
}

if(!pdo_fieldexists2("ddwx_business_sales","maidan_sales")) {
    \think\facade\Db::execute("ALTER TABLE `ddwx_business_sales` ADD COLUMN `maidan_sales`  int(11) NOT NULL DEFAULT '0' COMMENT '买单销量';");
}

if (!pdo_fieldexists2("ddwx_kecheng_sysset", "ios_canbuy")) {
    \think\facade\Db::execute("ALTER TABLE `ddwx_kecheng_sysset` ADD COLUMN `ios_canbuy` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'ios端购买 0 ：关闭 1：开启';");
}
if (!pdo_fieldexists2("ddwx_scoreshop_product", "everyday_buymax")) {
    \think\facade\Db::execute("ALTER TABLE `ddwx_scoreshop_product` ADD COLUMN `everyday_buymax` int(4) DEFAULT '0';");
}
if (!pdo_fieldexists2("ddwx_admin_wxicp", "applets_other_materials")) {
    \think\facade\Db::execute("ALTER TABLE `ddwx_admin_wxicp` 
ADD COLUMN `applets_other_materials` varchar(255) NULL COMMENT '小程序其他附件' AFTER `business_name_change_letter_media_id`,
ADD COLUMN `applets_other_materials_media_id` varchar(100) NULL DEFAULT '' AFTER `applets_other_materials`;");
}
if (!pdo_fieldexists2("ddwx_coupon", "categoryids2")) {
    \think\facade\Db::execute("ALTER TABLE `ddwx_coupon` ADD COLUMN `categoryids2` varchar(255) NULL COMMENT '指定商家类目ids';");
}
if(!pdo_fieldexists2("ddwx_cashier_order_goods","real_totalprice")) {
    \think\facade\Db::execute("ALTER TABLE `ddwx_cashier_order_goods` ADD COLUMN `real_totalprice` decimal(10,2) DEFAULT '0.00' COMMENT '实际商品销售金额 减去了优惠券抵扣会员折扣满减积分抵扣的金额';");
}
if(!pdo_fieldexists2("ddwx_wifiprint_set","rsa_publickey")) {
    \think\facade\Db::execute("ALTER TABLE `ddwx_wifiprint_set` 
        ADD COLUMN `rsa_publickey` text COMMENT 'k8打印机平台公钥 验签',
        ADD COLUMN `aes_publickey` text COMMENT 'k8打印机平台公钥 解密公钥';");
}

\think\facade\Db::execute("CREATE TABLE IF NOT EXISTS `ddwx_member_wximage_log` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `aid` int(11) NOT NULL,
  `mid` int(11) NOT NULL,
  `headimg` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL,
  `createtime` int(11) NULL DEFAULT NULL,
  `updatetime` int(11) NULL DEFAULT NULL,
  `trace_id` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
  `status` tinyint(2) NULL DEFAULT 0 COMMENT '1通过2检测不通过',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `aid`(`aid`) USING BTREE,
  INDEX `mid`(`mid`) USING BTREE,
  INDEX `trace_id`(`trace_id`) USING BTREE
) ENGINE = MyISAM AUTO_INCREMENT = 1 CHARACTER SET = utf8 COLLATE = utf8_general_ci COMMENT = '小程序音视频安全检测' ROW_FORMAT = Dynamic;");
if(!pdo_fieldexists2("ddwx_shop_order_goods","real_totalmoney")) {
    \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order_goods` ADD COLUMN `real_totalmoney` decimal(11,2) DEFAULT '0.00' COMMENT '实际支付金额（减去优惠券抵扣、会员折扣、满减积分抵扣），区别于real_totalprice)';");
}

if(!pdo_fieldexists2("ddwx_shop_order", "transfer_check")) {
        \think\facade\Db::execute("ALTER TABLE `ddwx_shop_order` ADD COLUMN `transfer_check` tinyint(1) NOT NULL DEFAULT 0 COMMENT '转账审核 -1 驳回 0：待审核 1：通过';");
}
if(!pdo_fieldexists2("ddwx_admin_set", "score_from_xianxiapay")) {
    \think\facade\Db::execute("ALTER TABLE `ddwx_admin_set` ADD COLUMN `score_from_xianxiapay` tinyint(1) DEFAULT '0' COMMENT '货到付款是否赠送积分 0否 1是';");
}