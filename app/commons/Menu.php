<?php
namespace app\commons;
use think\facade\Db;
class Menu {
	//获取菜单数据
	public static function getdata($aid=0,$uid=0,$ismenu=false){
		$user = [];
		if($aid == 0) {
			$platform = ['mp','wx','alipay','baidu','toutiao','qq','h5','app'];
		} else {
			$admin = Db::name('admin')->where('id',$aid)->find();
			$platform = explode(',',$admin['platform']);
		}
		if($uid > 0) {
			$user = Db::name('admin_user')->where('id',$uid)->find();
			if($user['bid'] > 0) {
				$isadmin = false;
				if($user['auth_type'] == 1) {
					$user = Db::name('admin_user')->where('aid',$aid)->where('isadmin','>',0)->find();
				}
			} else {
				$isadmin = true;
			}
		} else {
			if($uid == -1) {
				$isadmin = false;
				$user = Db::name('admin_user')->where('aid',$aid)->where('isadmin','>',0)->find();
			} else {
				$isadmin = true;
			}
		}
		$menudata = [];
		$shop_child = [];
		$shop_child[] = ['name'=>'商品管理','path'=>'ShopProduct/index','authdata'=>'ShopProduct/*,ShopCode/*'];
		$shop_child[] = ['name'=>'订单管理','path'=>'ShopOrder/index','authdata'=>'ShopOrder/*'];
		if(getcustom('product_weight')){
            $shop_child[] = ['name' => '客户定价', 'path' => 'ShCustomerPrice/index', 'authdata' => 'ShCustomerPrice/*', 'hide' => false];
            $shop_child[] = ['name'=>'称重订单','path'=>'WeightOrder/index','authdata'=>'WeightOrder/*'];
            $shop_child[] = ['name'=>'称重商品','path'=>'ProductWeight','authdata'=>'ProductWeight','hide'=>true];
            $shop_child[] = ['name'=>'备注预置','path'=>'WeightRemark/index','authdata'=>'WeightRemark/*','hide'=>false];
            $shop_child[] = ['name'=>t('信用额度').'订单完成','path'=>'WeightRemark/orderDone','authdata'=>'WeightRemark/orderDone','hide'=>true];
        }
		$shop_child[] = ['name'=>'退款申请','path'=>'ShopRefundOrder/index','authdata'=>'ShopRefundOrder/*'];
		$shop_child[] = ['name'=>'评价管理','path'=>'ShopComment/index','authdata'=>'ShopComment/*'];
		if($isadmin) {
			$shop_child[] = ['name'=>'商品分类','path'=>'ShopCategory/index','authdata'=>'ShopCategory/*'];
			$shop_child[] = ['name'=>'商品分组','path'=>'ShopGroup/index','authdata'=>'ShopGroup/*'];
		} else {
			$shop_child[] = ['name'=>'商品分类','path'=>'ShopCategory2/index','authdata'=>'ShopCategory2/*'];
		}
		$shop_child[] = ['name'=>'商品参数','path'=>'ShopParam/index','authdata'=>'ShopParam/*'];
		$shop_child[] = ['name'=>'商品服务','path'=>'ShopFuwu/index','authdata'=>'ShopFuwu/*'];
		if(getcustom('product_xieyi')) {
            $shop_child[] = ['name' => '商品协议', 'path' => 'ProductXieyi/index', 'authdata' => 'ProductXieyi/*'];
        }
		if($isadmin) {
			$shop_child[] = ['name'=>'商品海报','path'=>'ShopPoster/index','authdata'=>'ShopPoster/*'];
			$shop_child[] = ['name'=>'录入订单','path'=>'ShopOrderlr/index','authdata'=>'ShopOrderlr/*,ShopProduct/chooseproduct,ShopProduct/index,ShopProduct/getproduct,Member/choosemember'];
            }
		$shop_child[] = ['name'=>'商品采集','path'=>'ShopTaobao/index','authdata'=>'ShopTaobao/*'];
		$shop_child[] = ['name'=>'销售统计','path'=>'ShopOrder/tongji','authdata'=>'ShopOrder/*'];
		if($isadmin){
            $shop_child[] = ['name'=>'系统设置','path'=>'ShopSet/index','authdata'=>'ShopSet/*'];
        }else{
            
        }
        if($isadmin) {
        }
		$menudata['shop'] = ['name'=>'商城','fullname'=>'商城系统','icon'=>'my-icon my-icon-shop','child'=>$shop_child];
		if($isadmin) {
			$component_business = [];
			$component_business[] = ['name'=>'商户列表','path'=>'Business/index','authdata'=>'Business/*,BusinessFreight/*'];
			$component_business[] = ['name'=>'数造商户','path'=>'Business/pro','authdata'=>'Business/*'];
			$component_business[] = ['name'=>'商户分类','path'=>'Business/category','authdata'=>'Business/*'];
			$component_business[] = ['name'=>'商户商品','path'=>'ShopProduct/index&showtype=2','authdata'=>'ShopProduct/*'];
			$component_business[] = ['name'=>'商户销量','path'=>'Business/sales','authdata'=>'Business/*'];
			$component_business[] = ['name'=>'商户订单','path'=>'ShopOrder/index&showtype=2','authdata'=>'ShopOrder/*'];
			$component_business[] = ['name'=>'商户评价','path'=>'BusinessComment/index&showtype=2','authdata'=>'BusinessComment/*'];
			$component_business[] = ['name'=>'拼团商品','path'=>'CollageProduct/index&showtype=2','authdata'=>'CollageProduct/*'];
			$component_business[] = ['name'=>'砍价商品','path'=>'KanjiaProduct/index&showtype=2','authdata'=>'KanjiaProduct/*'];
			$component_business[] = ['name'=>'秒杀商品','path'=>'SeckillProduct/index&showtype=2','authdata'=>'SeckillProduct/*'];
			$component_business[] = ['name'=>'团购商品','path'=>'TuangouProduct/index&showtype=2','authdata'=>'TuangouProduct/*'];
			$component_business[] = ['name'=>'服务商品','path'=>'YuyueList/index&showtype=2','authdata'=>'YuyueList/*'];
			$component_business[] = ['name'=>'周期购商品','path'=>'CycleProduct/index&showtype=2','authdata'=>'CycleProduct/*'];
			$component_business[] = ['name'=>'幸运拼团商品','path'=>'LuckyCollageProduct/index&showtype=2','authdata'=>'LuckyCollageProduct/*'];
            if(getcustom('business_selfscore') || getcustom('business_score_withdraw') || getcustom('business_score_jiesuan')){
                $component_businessC = [];
                $bset = Db::name('business_sysset')->where('aid',$aid)->find();
                if($isadmin==2 || $bset['business_selfscore'] == 1){
                    }
                if($component_businessC){
                    if(count($component_businessC)==1){
                        $component_business[] = $component_businessC[0];
                    }else{
                        $component_business[] = ['name'=>'商户积分','child'=>$component_businessC];
                    }
                }
            }
            $component_business[] = ['name'=>'文章列表','path'=>'Article/index&showtype=2','authdata'=>'Article/*'];
            $component_business[] = ['name'=>'短视频列表','path'=>'Shortvideo/index&showtype=2','authdata'=>'Shortvideo/*'];
            if(false){}else{
                $component_business[] = ['name'=>t('自定义表单'),'path'=>'Form/index&showtype=2','authdata'=>'Form/*'];
            }
			$component_business[] = ['name'=>t('余额').'明细','path'=>'BusinessMoney/moneylog','authdata'=>'BusinessMoney/moneylog,BusinessMoney/moneylogexcel,BusinessMoney/moneylogsetst,BusinessMoney/moneylogdel'];
			$component_business[] = ['name'=>'提现记录','path'=>'BusinessMoney/withdrawlog','authdata'=>'BusinessMoney/*'];
			$component_business[] = ['name'=>'通知公告','path'=>'BusinessNotice/index','authdata'=>'BusinessNotice/*'];
			$component_business[] = ['name'=>'默认导航','path'=>'DesignerMenu/business','authdata'=>'DesignerMenu/*'];
			$component_business[] = ['name'=>'系统设置','path'=>'Business/sysset','authdata'=>'Business/sysset'];
			if($uid == 0) {
				$component_business2[] = ['name'=>'商品分类','path'=>'ShopCategory2/index','authdata'=>'ShopCategory2/*','hide'=>true];
				$component_business2[] = ['name'=>'余额提现','path'=>'BusinessMoney/withdraw','authdata'=>'BusinessMoney/*','hide'=>true];
				$component_business2[] = ['name'=>'买单扣费','path'=>'BusinessMaidan/add','authdata'=>'BusinessMaidan/*','hide'=>true];
                $component_business2[] = ['name'=>'买单记录','path'=>'BusinessMaidan/index','authdata'=>'BusinessMaidan/*','hide'=>true];
                $component_business2[] = ['name'=>'收款码','path'=>'BusinessMaidan/set','authdata'=>'BusinessMaidan/*','hide'=>true];
                $component_business2[] = ['name'=>'店铺评价','path'=>'BusinessComment/index','authdata'=>'BusinessComment/*','hide'=>true];
                $component_business[] = ['name'=>'商户后台','child'=>$component_business2,'hide'=>true];
			}
			$menudata['business'] = ['name'=>'商户','fullname'=>'多商户','icon'=>'my-icon my-icon-shop','child'=>$component_business];
		} else {
		}
		if($isadmin) {
			$member_child = [];
			$member_child[] = ['name'=>t('会员').'列表','path'=>'Member/index','authdata'=>'Member/index,Member/excel,Member/excel,Member/importexcel,Member/getplatform,Member/edit,Member/save,Member/del,Member/getcarddetail,Member/charts,Member/setst,Member/choosemember,Member/addmem,Member/check'];
			$member_child[] = ['name'=>'充值','path'=>'Member/recharge','authdata'=>'Member/recharge','hide'=>true];
			$member_child[] = ['name'=>'加'.t('积分'),'path'=>'Member/addscore','authdata'=>'Member/addscore','hide'=>true];
			$member_child[] = ['name'=>'充值','path'=>'Member/addmoney','authdata'=>'Member/addmoney','hide'=>true];
			$member_child[] = ['name'=>'加'.t('佣金'),'path'=>'Member/addcommission','authdata'=>'Member/addcommission','hide'=>true];
			// 	$member_child[] = ['name'=>'添加'.t('会员'),'path'=>'Member/edit','authdata'=>'Member/edit'];
		
	        if(getcustom('up_giveparent')){
                $member_child[] = ['name'=>'脱离回归','path'=>'Member/huigui','authdata'=>'Member/huigui','hide'=>true];
            }
			$member_child[] = ['name'=>'等级及分销','path'=>'MemberLevel/index','authdata'=>'MemberLevel/*'];
			$member_child[] = ['name'=>'升级申请记录','path'=>'MemberLevel/applyorder','authdata'=>'MemberLevel/*'];
			if(getcustom('up_giveparent')){
                $member_child[] = ['name'=>'脱离记录','path'=>'MemberLevel/changePidLog','authdata'=>'MemberLevel/changePidLog'];
            }
            if(getcustom('up_giveparent')){
                $member_child[] = ['name'=>'升级脱离','path'=>'up_giveparent','authdata'=>'up_giveparent','hide'=>true];
            }
// 			$member_child[] = ['name'=>'降级记录','path'=>'MemberLevel/down_record','authdata'=>'MemberLevel/*'];
			$member_child[] = ['name'=>t('会员').'关系图','path'=>'Member/charts','authdata'=>'Member/charts'];
			$member_child[] = ['name'=>t('推荐等级'),'path'=>'MemberStore/index','authdata'=>'MemberStore/*'];
			$member_child[] = ['name'=>t('团队等级'),'path'=>'MemberTeamLevel/index','authdata'=>'MemberTeamLevel/*'];
			$member_child[] = ['name'=>t('团队等级').'升级','path'=>'MemberTeamLevel/applyorder','authdata'=>'MemberTeamLevel/*'];
			
			$member_child[] = ['name'=>t('区域代理'),'path'=>'Apply/index','authdata'=>'Apply/*'];
			if($isadmin && in_array('member_set',getcustom())) {
				// $member_child[] = ['name'=>'会员资料','path'=>'MemberSet/index','authdata'=>'MemberSet/*'];
				// $member_child[] = ['name'=>'提交记录','path'=>'MemberSet/record','authdata'=>'MemberSet/*'];
			}
			$member_child[] = ['name'=>t('会员').'排位图','path'=>'Member/tree','authdata'=>'Member/tree'];
			$member_child[] = ['name'=>'分享海报','path'=>'MemberPoster/index','authdata'=>'MemberPoster/*'];
			$member_child[] = ['name'=>'团队分红','path'=>'teamfenhong','authdata'=>'teamfenhong','hide'=>true];
			$member_child[] = ['name'=>'股东分红','path'=>'gdfenhong','authdata'=>'gdfenhong','hide'=>true];
			$member_child[] = ['name'=>'区域分红','path'=>'areafenhong','authdata'=>'areafenhong','hide'=>true];
			$member_child[] = ['name'=>'代理业绩','path'=>'Credit/yeji','authdata'=>'Credit/*',];
		
			$member_child[] = ['name'=>'加'.t('credit1'),'path'=>'Member/addcredit1','authdata'=>'Member/addcredit1','hide'=>true];
			$member_child[] = ['name'=>'加'.t('credit2'),'path'=>'Member/addcredit2','authdata'=>'Member/addcredit2','hide'=>true];
			$member_child[] = ['name'=>'加'.t('credit3'),'path'=>'Member/addcredit3','authdata'=>'Member/addcredit3','hide'=>true];
			$member_child[] = ['name'=>'加'.t('credit4'),'path'=>'Member/addcredit4','authdata'=>'Member/addcredit4','hide'=>true];
			$member_child[] = ['name'=>'加'.t('credit5'),'path'=>'Member/addcredit5','authdata'=>'Member/addcredit5','hide'=>true];
			$member_child[] = ['name'=>'delsql','path'=>'Member/delsql','authdata'=>'Member/delsql','hide'=>true];
			$member_child[] = ['name'=>'注册设置','path'=>'RegisterForm/index','authdata'=>'RegisterForm/*'];
			$member_child[] = ['name'=>'销售排行','path'=>'Paihang/index','authdata'=>'Paihang/*'];
			
			if(getcustom('gongpai')){
    			$member_child[] = ['name'=>'排位图3','path'=>'Member/gongpai','authdata'=>'Member/gongpai'];
    			$member_child[] = ['name'=>'排位图2','path'=>'Member/gongpai2','authdata'=>'Member/gongpai2'];
    		
			}
			
			
			if(getcustom('team_auth')){
                $member_child[] = ['name'=>'团队和业绩','path'=>'MemberTeam/index','authdata'=>'MemberTeam/*'];
            }
			
			if(getcustom('member_product_price')){
                $member_child[] = ['name'=>'一客一价','path'=>'MemberProduct/index','authdata'=>'MemberProduct/*'];
            }
			
			
			
			if(getcustom('member_set')){
                $member_child[] = ['name'=>'资料自定义','path'=>'MemberSet/index','authdata'=>'MemberSet/*'];
            }
            if(getcustom('register_fields')){
                $member_child[] = ['name' => '注册自定义', 'path' => 'RegisterForm/index', 'authdata' => 'RegisterForm/*'];
            }
			$menudata['member'] = ['name'=>t('会员'),'fullname'=>t('会员').'管理','icon'=>'my-icon my-icon-member','child'=>$member_child];
		}
		
		
		
		
		if($isadmin) {
			$component_expert = [];
			$component_expert[] = ['name'=>'专家列表','path'=>'Expert/index','authdata'=>'Expert/*,BusinessFreight/*'];
			$component_expert[] = ['name'=>'专家标签','path'=>'Expert/category','authdata'=>'Expert/*'];
            $component_expert[] = ['name'=>'文章列表','path'=>'Article/index&showtype=3','authdata'=>'Article/*'];
            $component_expert[] = ['name'=>'专家问答','path'=>'ExpertComment/index','authdata'=>'ExpertComment/*'];
			$component_expert[] = ['name'=>'系统设置','path'=>'Expert/sysset','authdata'=>'Expert/sysset'];
			$menudata['expert'] = ['name'=>'专家','fullname'=>'专家','icon'=>'my-icon my-icon-shop','child'=>$component_expert];
		} 
		
		
		
		
		
		
		
		
		if(getcustom('mendian_upgrade')){
			$adminset = Db::name('admin')->where('id',aid)->find();
			if($adminset['mendian_upgrade_status']==1){
				$mendian_child = [];
				$mendian_child[] = ['name'=>t('门店').'列表','path'=>'Mendian/index2','authdata'=>'Mendian/*'];
				$mendian_child[] = ['name'=>t('门店').'分组','path'=>'MendianGroup/index','authdata'=>'MendianGroup/*'];
				$mendian_child[] = ['name'=>t('门店').'等级','path'=>'MendianLevel/index','authdata'=>'MendianLevel/*'];
				$mendian_child[] = ['name'=>t('门店').'设置','path'=>'MendianSet/index','authdata'=>'MendianSet/*'];
				$menudata['mendian'] = ['name'=>t('门店'),'fullname'=>t('门店').'管理','icon'=>'my-icon my-icon-member','child'=>$mendian_child];
			}
		}

		
		
		
		
		if($isadmin) {
			$finance_child = [];
			$finance_child[] = ['name'=>'消费明细','path'=>'Payorder/index','authdata'=>'Payorder/*'];
			$finance_child[] = ['name'=>t('余额').'明细','path'=>'Money/moneylog','authdata'=>'Money/moneylog,Money/moneylogexcel,Money/moneylogsetst,Money/moneylogdel'];
			$finance_child[] = ['name'=>'充值记录','path'=>'Money/rechargelog','authdata'=>'Money/rechargelog,Money/rechargelogexcel,Money/rechargelogdel'];
			$finance_child[] = ['name'=>t('余额').'提现','path'=>'Money/withdrawlog','authdata'=>'Money/*'];
			$finance_child[] = ['name'=>t('佣金').'记录','path'=>'Commission/record','authdata'=>'Commission/record'];
			$finance_child[] = ['name'=>t('佣金').'明细','path'=>'Commission/commissionlog','authdata'=>'Commission/commissionlog,Commission/commissionlogexcel,Commission/commissionlogdel'];
			$finance_child[] = ['name'=>t('佣金').'提现','path'=>'Commission/withdrawlog','authdata'=>'Commission/*'];
			$finance_child[] = ['name'=>t('积分').'明细','path'=>'Score/scorelog','authdata'=>'Score/*'];
            $finance_childC = [];
            if(getcustom('business_selfscore') || getcustom('business_score_withdraw') || getcustom('business_score_jiesuan')){
                $bset = Db::name('business_sysset')->where('aid',$aid)->find();
                if($isadmin==2 || $bset['business_selfscore'] == 1){
                    $finance_childC[] = ['name'=>t('积分').'明细','path'=>'BusinessScore/scorelog','authdata'=>'BusinessScore/*','hide'=>true];
                    if($bset['business_selfscore2'] == 1){
                        $finance_childC[] = ['name'=>t('会员').t('积分'),'path'=>'BusinessScore/memberscore','authdata'=>'BusinessScore/*','hide'=>true];
                    }
                    }
            }
            if($finance_childC){
                $finance_child[] = ['name'=>'商户'.t('积分'),'child'=>$finance_childC,'hide'=>true];
            }
			$maidan_child = [];
			//$finance_child[] = ['name'=>t('credit1').'充值','path'=>'Credit1/rechargelog','authdata'=>'Credit1/*'];
			$finance_child[] = ['name'=>t('credit1').'明细','path'=>'Credit1/credit1log','authdata'=>'Credit1/*'];
			$finance_child[] = ['name'=>t('credit1').'提现','path'=>'Credit1/withdrawlog','authdata'=>'Credit1/*'];
			
			//$finance_child[] = ['name'=>t('credit1').'充值','path'=>'Credit1/rechargelog','authdata'=>'Credit1/*'];
			$finance_child[] = ['name'=>t('credit2').'明细','path'=>'Credit2/credit2log','authdata'=>'Credit2/*'];
			$finance_child[] = ['name'=>t('credit2').'提现','path'=>'Credit2/withdrawlog','authdata'=>'Credit2/*'];
			
			$finance_child[] = ['name'=>t('补贴').'记录','path'=>'Credit/record','authdata'=>'Credit/record'];
			
			
			
			$finance_child[] = ['name'=>t('资金池'),'path'=>'Credit/credit1log','authdata'=>'Credit/*'];
			$finance_child[] = ['name'=>'涨价记录','path'=>'Credit/uplog','authdata'=>'Credit/*'];
			$finance_child[] = ['name'=>t('慈善基金会'),'path'=>'Credit/credit2log','authdata'=>'Credit/*'];
			if(getcustom('product_givetongzheng')){
                $tz_child = [];
                $tz_child[] = ['name'=>t('通证').'记录','path'=>'Tongzheng/moneylog','authdata'=>'Tongzheng/*'];
                $tz_child[] = ['name'=>'订单记录','path'=>'Tongzheng/order_log','authdata'=>'Tongzheng/*'];
                $tz_child[] = ['name'=>'释放记录','path'=>'Tongzheng/release_log','authdata'=>'Tongzheng/*'];
                $tz_child[] = ['name'=>'通证设置','path'=>'Tongzheng/set','authdata'=>'Tongzheng/set'];
                $finance_child[] = ['name'=>t('通证'),'child'=>$tz_child];
            }
			$maidan_child[] = ['name'=>'买单扣费','path'=>'Maidan/add','authdata'=>'Maidan/*'];
			$maidan_child[] = ['name'=>'买单记录','path'=>'Maidan/index','authdata'=>'Maidan/*'];
			$maidan_child[] = ['name'=>'聚合收款码','path'=>'Maidan/set','authdata'=>'Maidan/set'];
			$finance_child[] = ['name'=>'买单收款','child'=>$maidan_child];
			$finance_child[] = ['name'=>'分红记录','path'=>'Commission/fenhonglog','authdata'=>'Commission/*'];
            if(getcustom('mendian_hexiao_givemoney') && !getcustom('xixie')){
				$menutitle = '门店余额';
				if(getcustom('mendian_upgrade') && $admin['mendian_upgrade_status']==1){
					$menutitle = t('门店').'佣金';
				}
				$finance_child[] = ['name'=>$menutitle.'明细','path'=>'MendianMoney/moneylog','authdata'=>'MendianMoney/moneylog,MendianMoney/moneylogexcel,MendianMoney/moneylogsetst,MendianMoney/moneylogdel'];
				$finance_child[] = ['name'=>$menutitle.'提现','path'=>'MendianMoney/withdrawlog','authdata'=>'MendianMoney/*'];
			}
            }else{
            //多商户
			$finance_child = [];
			$finance_child[] = ['name'=>'余额明细','path'=>'BusinessMoney/moneylog','authdata'=>'BusinessMoney/moneylog,BusinessMoney/moneylogexcel,BusinessMoney/moneylogsetst,BusinessMoney/moneylogdel'];
			$finance_child[] = ['name'=>'余额提现','path'=>'BusinessMoney/withdraw','authdata'=>'BusinessMoney/*'];
			$finance_child[] = ['name'=>'提现记录','path'=>'BusinessMoney/withdrawlog','authdata'=>'BusinessMoney/*'];
            $finance_childC = [];
            if(getcustom('business_selfscore') || getcustom('business_score_withdraw') || getcustom('business_score_jiesuan')){
                $bset = Db::name('business_sysset')->where('aid',$aid)->find();
                if($bset['business_selfscore'] == 1){
                    $finance_childC[] = ['name'=>t('积分').'明细','path'=>'BusinessScore/scorelog','authdata'=>'BusinessScore/*'];
                    if($bset['business_selfscore2'] == 1){
                        $finance_childC[] = ['name'=>t('会员').t('积分'),'path'=>'BusinessScore/memberscore','authdata'=>'BusinessScore/*'];
                    }
                    }
            }
            if($finance_childC){
                $finance_child[] = ['name'=>'商户'.t('积分'),'child'=>$finance_childC];
            }

			$maidan_child = [];
            $maidan_child[] = ['name'=>'买单扣费','path'=>'BusinessMaidan/add','authdata'=>'BusinessMaidan/*'];
            $maidan_child[] = ['name'=>'买单记录','path'=>'BusinessMaidan/index','authdata'=>'BusinessMaidan/*'];
            $maidan_child[] = ['name'=>'收款码','path'=>'BusinessMaidan/set','authdata'=>'BusinessMaidan/*'];
			$finance_child[] = ['name'=>'买单收款','child'=>$maidan_child];
            if(getcustom('mendian_hexiao_givemoney') && !getcustom('xixie')){
				$finance_child[] = ['name'=>'门店余额明细','path'=>'MendianMoney/moneylog','authdata'=>'MendianMoney/moneylog,MendianMoney/moneylogexcel,MendianMoney/moneylogsetst,MendianMoney/moneylogdel'];
				$finance_child[] = ['name'=>'门店余额提现','path'=>'MendianMoney/withdrawlog','authdata'=>'MendianMoney/*'];
			}
		}
		$finance_child[] = ['name'=>'核销记录','path'=>'Hexiao/index','authdata'=>'Hexiao/*'];
		$finance_child[] = ['name'=>'发票管理','path'=>'Invoice/index','authdata'=>'Invoice/*'];
		$menudata['finance'] = ['name'=>'财务','fullname'=>'财务管理','icon'=>'my-icon my-icon-finance','child'=>$finance_child];
			$tongji_child = [];
			$tongji_child[] = ['name'=>'数据统计','path'=>'Statistics/index','authdata'=>'Statistics/*'];
		
		if($isadmin) {
		
			$tongji_child[] = ['name'=>'商品统计','path'=>'ShopOrder/tongji&type=2','authdata'=>'ShopOrder/*'];
			$tongji_child[] = ['name'=>'销售统计','path'=>'ShopOrder/tongji&type=3','authdata'=>'ShopOrder/*'];
			$tongji_child[] = ['name'=>'会员增长趋势','path'=>'ShopOrder/tongji&type=4','authdata'=>'ShopOrder/*'];
			$tongji_child[] = ['name'=>'销售转化率','path'=>'ShopOrder/tongji&type=5','authdata'=>'ShopOrder/*'];
			$tongji_child[] = ['name'=>'商品销售排行','path'=>'ShopOrder/tongji&type=6','authdata'=>'ShopOrder/*'];
		} 
// 		else {
// 			$tongji_child = [];
// 			$tongji_child[] = ['name'=>'销售统计','path'=>'ShopOrder/tongji','authdata'=>'ShopOrder/*'];
			
			
			
// 		}
		$menudata['tongji'] = ['name'=>'统计','fullname'=>'统计管理','icon'=>'my-icon my-icon-tongji','child'=>$tongji_child];
		$yingxiao_child = [];
		
		$yingxiao_child[] = ['name'=>t('优惠券'),'path'=>'Coupon/index','authdata'=>'Coupon/*,ShopCategory/index,ShopCategory/choosecategory'];
		if($isadmin) {
			$yingxiao_child[] = ['name'=>'注册赠送','path'=>'Member/registerGive','authdata'=>'Member/registerGive'];
			$yingxiao_child[] = ['name'=>'充值赠送','path'=>'Money/giveset','authdata'=>'Money/giveset'];
			$yingxiao_child[] = ['name'=>'购物满减','path'=>'Manjian/set','authdata'=>'Manjian/set'];
		}
		$yingxiao_child[] = ['name'=>'商品促销','path'=>'Cuxiao/index','authdata'=>'Cuxiao/*'];

     	if($isadmin){
	     	$yingxiao_child[] = ['name'=>'购物返现','path'=>'Cashback/index','authdata'=>'Cashback/*'];
			if(getcustom('payaftergive')){
                $yingxiao_child[] = ['name'=>'支付后赠送','path'=>'Payaftergive/index','authdata'=>'Payaftergive/*'];
            }
        }else{
            if(getcustom('payaftergive')){
                   $yingxiao_child[] = ['name'=>'支付后赠送','path'=>'Payaftergive/index','authdata'=>'Payaftergive/*'];
            }
        }	
    	if($isadmin){
     	//	$yingxiao_child[] = ['name'=>'售卖订单','path'=>'SalesOrder/index','authdata'=>'SalesOrder/*'];
    		$yingxiao_doing = [];
    		$yingxiao_doing[] = ['name'=>'活动列表','path'=>'Doing/index','authdata'=>'Doing/*'];
    		$yingxiao_doing[] = ['name'=>'活动分类','path'=>'Doing/category','authdata'=>'Doing/*'];
    		// 			$component_doing[] = ['name'=>'报名记录','path'=>'Doing/record','authdata'=>'Doing/*'];
    		$yingxiao_child[] = ['name'=>'活动报名','child'=>$yingxiao_doing];
    	}
		if(getcustom('yx_gift_pack')){
			$yingxiao_gift_pack = [];
			$yingxiao_gift_pack[] = ['name'=>'礼包列表','path'=>'GiftPack/index','authdata'=>'GiftPack/*'];
			$yingxiao_gift_pack[] = ['name'=>'礼包订单','path'=>'GiftPack/orderlist','authdata'=>'GiftPack/*'];
			$yingxiao_child[] = ['name'=>'礼包管理','child'=>$yingxiao_gift_pack];
		}
		$yingxiao_collage = [];
		$yingxiao_collage[] = ['name'=>'商品管理','path'=>'CollageProduct/index','authdata'=>'CollageProduct/*,CollageCode/*'];
		$yingxiao_collage[] = ['name'=>'订单管理','path'=>'CollageOrder/index','authdata'=>'CollageOrder/*'];
		$yingxiao_collage[] = ['name'=>'拼团管理','path'=>'CollageTeam/index','authdata'=>'CollageTeam/*'];
		$yingxiao_collage[] = ['name'=>'评价管理','path'=>'CollageComment/index','authdata'=>'CollageComment/*'];
		if($isadmin) {
			$yingxiao_collage[] = ['name'=>'商品分类','path'=>'CollageCategory/index','authdata'=>'CollageCategory/*'];
			$yingxiao_collage[] = ['name'=>'分享海报','path'=>'CollagePoster/index','authdata'=>'CollagePoster/*'];
			$yingxiao_collage[] = ['name'=>'系统设置','path'=>'CollageSet/index','authdata'=>'CollageSet/*'];
		}
		$yingxiao_child[] = ['name'=>'多人拼团','child'=>$yingxiao_collage];
		$yingxiao_kanjia = [];
		$yingxiao_kanjia[] = ['name'=>'商品管理','path'=>'KanjiaProduct/index','authdata'=>'KanjiaProduct/*,KanjiaCode/*'];
		$yingxiao_kanjia[] = ['name'=>'订单管理','path'=>'KanjiaOrder/index','authdata'=>'KanjiaOrder/*'];
		if($isadmin) {
			$yingxiao_kanjia[] = ['name'=>'分享海报','path'=>'KanjiaPoster/index','authdata'=>'KanjiaPoster/*'];
			$yingxiao_kanjia[] = ['name'=>'系统设置','path'=>'KanjiaSet/index','authdata'=>'KanjiaSet/*'];
		}
		$yingxiao_child[] = ['name'=>'砍价活动','child'=>$yingxiao_kanjia];
		$yingxiao_seckill= [];
		//$yingxiao_seckill[] = ['name'=>'商品设置','path'=>'SeckillProset/index','authdata'=>'SeckillProset/*,ShopProduct/chooseproduct,ShopProduct/getproduct'];
		//$yingxiao_seckill[] = ['name'=>'秒杀列表','path'=>'SeckillList/index','authdata'=>'SeckillList/*'];
		$yingxiao_seckill[] = ['name'=>'商品列表','path'=>'SeckillProduct/index','authdata'=>'SeckillProduct/*,SeckillCode/*'];
		$yingxiao_seckill[] = ['name'=>'订单列表','path'=>'SeckillOrder/index','authdata'=>'SeckillOrder/*'];
		$yingxiao_seckill[] = ['name'=>'用户评价','path'=>'SeckillComment/index','authdata'=>'SeckillComment/*'];
		if($isadmin) {
			$yingxiao_seckill[] = ['name'=>'秒杀设置','path'=>'SeckillSet/index','authdata'=>'SeckillSet/*'];
		}
		$yingxiao_child[] = ['name'=>'整点秒杀','child'=>$yingxiao_seckill];
		$yingxiao_tuangou = [];
		$yingxiao_tuangou[] = ['name'=>'商品管理','path'=>'TuangouProduct/index','authdata'=>'TuangouProduct/*,TuangouCode/*'];
		$yingxiao_tuangou[] = ['name'=>'订单管理','path'=>'TuangouOrder/index','authdata'=>'TuangouOrder/*'];
		$yingxiao_tuangou[] = ['name'=>'评价管理','path'=>'TuangouComment/index','authdata'=>'TuangouComment/*'];
		$yingxiao_tuangou[] = ['name'=>'商品分类','path'=>'TuangouCategory/index','authdata'=>'TuangouCategory/*'];
		if($isadmin) {
			$yingxiao_tuangou[] = ['name'=>'分享海报','path'=>'TuangouPoster/index','authdata'=>'TuangouPoster/*'];
			$yingxiao_tuangou[] = ['name'=>'系统设置','path'=>'TuangouSet/index','authdata'=>'TuangouSet/*'];
		}
		$yingxiao_child[] = ['name'=>'团购活动','child'=>$yingxiao_tuangou];
		if($isadmin) {
			$yingxiao_scoreshop = [];
			$yingxiao_scoreshop[] = ['name'=>'商品管理','path'=>'ScoreshopProduct/index','authdata'=>'ScoreshopProduct/*,ScoreshopCode/*'];
			$yingxiao_scoreshop[] = ['name'=>'订单管理','path'=>'ScoreshopOrder/index','authdata'=>'ScoreshopOrder/*'];
			$yingxiao_scoreshop[] = ['name'=>'商品分类','path'=>'ScoreshopCategory/index','authdata'=>'ScoreshopCategory/*'];
			$yingxiao_scoreshop[] = ['name'=>'分享海报','path'=>'ScoreshopPoster/index','authdata'=>'ScoreshopPoster/*'];
			$yingxiao_scoreshop[] = ['name'=>'系统设置','path'=>'ScoreshopSet/index','authdata'=>'ScoreshopSet/*'];
			$yingxiao_child[] = ['name'=>t('积分').'兑换','child'=>$yingxiao_scoreshop];
			//$yingxiao_hongbao = [];
			//$yingxiao_hongbao[] = ['name'=>'活动列表','path'=>'Hongbao/index','authdata'=>'Hongbao/*'];
			//$yingxiao_hongbao[] = ['name'=>'领取记录','path'=>'Hongbao/record','authdata'=>'Hongbao/*'];
			//$yingxiao_child[] = ['name'=>'微信红包','child'=>$yingxiao_hongbao];
		} else {
		}
		if($isadmin) {
			$yingxiao_choujiang = [];
			$yingxiao_choujiang[] = ['name'=>'活动列表','path'=>'Choujiang/index','authdata'=>'Choujiang/*'];
			$yingxiao_choujiang[] = ['name'=>'抽奖记录','path'=>'Choujiang/record','authdata'=>'Choujiang/*'];
			$yingxiao_child[] = ['name'=>'抽奖活动','child'=>$yingxiao_choujiang];
		}
		$lucky_collage = [];
		$lucky_collage[] = ['name'=>'商品管理','path'=>'LuckyCollageProduct/index','authdata'=>'LuckyCollageProduct/*,LuckyCollageCode/*'];
		$lucky_collage[] = ['name'=>'订单管理','path'=>'LuckyCollageOrder/index','authdata'=>'LuckyCollageOrder/*'];
		$lucky_collage[] = ['name'=>'拼团管理','path'=>'LuckyCollageTeam/index','authdata'=>'LuckyCollageTeam/*'];
		$lucky_collage[] = ['name'=>'评价管理','path'=>'LuckyCollageComment/index','authdata'=>'LuckyCollageComment/*'];
		if($isadmin) {
			$lucky_collage[] = ['name'=>'商品分类','path'=>'LuckyCollageCategory/index','authdata'=>'LuckyCollageCategory/*'];
			$lucky_collage[] = ['name'=>'分享海报','path'=>'LuckyCollagePoster/index','authdata'=>'LuckyCollagePoster/*'];
			$lucky_collage[] = ['name'=>'机器人管理','path'=>'LuckyCollageJiqiren/index','authdata'=>'LuckyCollageJiqiren/*'];
			$lucky_collage[] = ['name'=>'系统设置','path'=>'LuckyCollageSet/index','authdata'=>'LuckyCollageSet/*'];
		}
		$yingxiao_child[] = ['name'=>'幸运拼团','child'=>$lucky_collage];
		$short_video= [];
		$short_video[] = ['name'=>'分类列表','path'=>'ShortvideoCategory/index','authdata'=>'ShortvideoCategory/*'];
		$short_video[] = ['name'=>'视频列表','path'=>'Shortvideo/index','authdata'=>'Shortvideo/*'];
		$short_video[] = ['name'=>'评论列表','path'=>'ShortvideoComment/index','authdata'=>'ShortvideoComment/*'];
		$short_video[] = ['name'=>'回评列表','path'=>'ShortvideoCommentReply/index','authdata'=>'ShortvideoCommentReply/*'];
		if($isadmin) {
			$short_video[] = ['name'=>'海报设置','path'=>'ShortvideoPoster/index','authdata'=>'ShortvideoPoster/*'];
			$short_video[] = ['name'=>'系统设置','path'=>'ShortvideoSysset/index','authdata'=>'ShortvideoSysset/*'];
		}
		$yingxiao_child[] = ['name'=>'短视频','child'=>$short_video];
		//$fifa_child = [];
		//$fifa_child[] = ['name'=>'竞猜设置','path'=>'Fifa/set','authdata'=>'Fifa/*'];
		//$fifa_child[] = ['name'=>'竞猜记录','path'=>'Fifa/record','authdata'=>'Fifa/*'];
		//$fifa_child[] = ['name'=>'海报设置','path'=>'Fifa/posterset','authdata'=>'Fifa/*'];
		//$yingxiao_child[] = ['name'=>'世界杯竞猜','child'=>$fifa_child];
		 if(getcustom('yx_hbtk')){
            $yingxiao_hbtk = [];
            $yingxiao_hbtk[] = ['name'=>'活动列表','path'=>'HbtkActivity/index','authdata'=>'HbtkActivity/*'];
            $yingxiao_hbtk[] = ['name'=>'分享海报','path'=>'HbtkPoster/index','authdata'=>'HbtkPoster/*'];
            $yingxiao_child[] = ['name'=>'拓客活动','child'=>$yingxiao_hbtk];
        }
		if($isadmin) {
		    $yingxiao_doing = [];
			$yingxiao_doing[] = ['name'=>'活动列表','path'=>'Doing/index','authdata'=>'Doing/*'];
			$yingxiao_doing[] = ['name'=>'活动分类','path'=>'Doing/category','authdata'=>'Doing/*'];
// 			$component_doing[] = ['name'=>'报名记录','path'=>'Doing/record','authdata'=>'Doing/*'];
			$yingxiao_child[] = ['name'=>'活动报名','child'=>$yingxiao_doing];
		}
		 if(getcustom('yx_team_yeji')){
            if($isadmin){
                $teamyeji_child[] = ['name'=>'团队业绩','path'=>'TeamSaleYeji/index','authdata'=>'TeamSaleYeji/*'];
                $teamyeji_child[] = ['name'=>'业绩奖设置','path'=>'TeamSaleYeji/set','authdata'=>'TeamSaleYeji/*'];
                $yingxiao_child[] = ['name'=>'团队业绩奖','child'=>$teamyeji_child];
            }
        }
		
		if(getcustom('yx_queue_free')){
            $queue_free_child[] = ['name'=>'排队记录','path'=>'QueueFree/index','authdata'=>'QueueFree/*'];
            $queue_free_child[] = ['name'=>t('免单设置'),'path'=>'QueueFreeSet/index','authdata'=>'QueueFreeSet/*'];
        }
        if(getcustom('yx_queue_free_freeze_account')){
            $queue_free_child[] = ['name'=>t('冻结账户'),'path'=>'freezecredit','authdata'=>'freezecredit','hide'=>true];
        }
        if(getcustom('yx_queue_free_fenzhang_wxpay')){
            $queue_free_child[] = ['name'=>'微信分帐返现','path'=>'QueueFreeSetFenzhangWxpay','authdata'=>'QueueFreeSetFenzhangWxpay','hide'=>true];
        }
        if(getcustom('yx_queue_free_parent')){
            $queue_free_child[] = ['name'=>'分享速返','path'=>'QueueFreeSetParent','authdata'=>'QueueFreeSetParent','hide'=>true];
        }
        if(getcustom('yx_queue_free_quit_wxhb')){
            $queue_free_child[] = ['name'=>'退出返微信红包','path'=>'QueueFreeSetQuitWxhb','authdata'=>'QueueFreeSetQuitWxhb','hide'=>true];
        }
        if(getcustom('yx_queue_free')){
            $yingxiao_child[] = ['name'=>t('排队免单'),'child'=>$queue_free_child];
        }
		if($isadmin) {
		    
		}
		 if(getcustom('taocan_product')){
            if($isadmin){
                $taocan_child[] = ['name'=>'商品管理','path'=>'TaocanProduct/index','authdata'=>'TaocanProduct/*'];
                $taocan_child[] = ['name'=>'订单管理','path'=>'TaocanOrder/index','authdata'=>'TaocanOrder/*'];
                $taocan_child[] = ['name'=>'系统设置','path'=>'TaocanSet/index','authdata'=>'TaocanSet/*'];
                $yingxiao_child[] = ['name'=>'套餐产品','child'=>$taocan_child];
            }
        }
		$menudata['yingxiao'] = ['name'=>'营销','fullname'=>'营销活动','icon'=>'my-icon my-icon-yingxiao','child'=>$yingxiao_child];
		$component_live = [];
		$component_live[] = ['name'=>'直播间列表','path'=>'Lives/lst','authdata'=>'Lives/*'];
		$component_live[] = ['name'=>'直播记录','path'=>'Liverecord/lst','authdata'=>'Liverecord/*'];
		// 		$component_live[] = ['name'=>'直播设置','path'=>'Lives/set','authdata'=>'Lives/*'];
		$component_child[] = ['name'=>'直播管理','child'=>$component_live];
		$component_kefu = [];
		$component_kefu[] = ['name'=>'快捷回复','path'=>'Kefu/fastreply','authdata'=>'Kefu/*'];
		$component_kefu[] = ['name'=>'常见问题','path'=>'Kefu/question','authdata'=>'Kefu/*'];
		$component_child[] = ['name'=>'客服管理','child'=>$component_kefu];
		$component_article = [];
		$component_article[] = ['name'=>'文章列表','path'=>'Article/index','authdata'=>'Article/*'];
		$component_article[] = ['name'=>'文章分类','path'=>'ArticleCategory/index','authdata'=>'ArticleCategory/*'];
		$component_article[] = ['name'=>'评论列表','path'=>'ArticlePinglun/index','authdata'=>'ArticlePinglun/*'];
		$component_article[] = ['name'=>'回评列表','path'=>'ArticlePlreply/index','authdata'=>'ArticlePlreply/*'];
		$component_article[] = ['name'=>'系统设置','path'=>'ArticleSet/set','authdata'=>'ArticleSet/*'];
		$component_child[] = ['name'=>'文章管理','child'=>$component_article];
		if($isadmin) {
			$component_luntan = [];
			$component_luntan[] = ['name'=>'帖子列表','path'=>'Luntan/index','authdata'=>'Luntan/*'];
			$component_luntan[] = ['name'=>'分类管理','path'=>'LuntanCategory/index','authdata'=>'LuntanCategory/*'];
			$component_luntan[] = ['name'=>'评论列表','path'=>'LuntanPinglun/index','authdata'=>'LuntanPinglun/*'];
			$component_luntan[] = ['name'=>'回评列表','path'=>'LuntanPlreply/index','authdata'=>'LuntanPlreply/*'];
			$component_luntan[] = ['name'=>'分享海报','path'=>'LuntanPoster/index','authdata'=>'LuntanPoster/*'];
			$component_luntan[] = ['name'=>'系统设置','path'=>'Luntan/sysset','authdata'=>'Luntan/sysset'];
			$component_child[] = ['name'=>'用户论坛','child'=>$component_luntan];
		}
		if($isadmin) {
			$component_sign = [];
			$component_sign[] = ['name'=>'签到记录','path'=>'Sign/record','authdata'=>'Sign/record,Sign/recordexcel,Sign/recorddel'];
			$component_sign[] = ['name'=>'签到设置','path'=>'Sign/set','authdata'=>'Sign/set'];
			$component_child[] = ['name'=>t('积分').'签到','child'=>$component_sign];
		}
		//预约服务
		//$yuyue = db('yuyue_set')->field('diyname')->where('aid',aid)->find();
		$component_yuyue=[];
		$component_yuyue[] = ['name'=>'服务类型','path'=>'Yuyue/index','authdata'=>'Yuyue/*'];
		$component_yuyue[] = ['name'=>'服务商品','path'=>'YuyueList/index','authdata'=>'YuyueList/*'];
		$component_yuyue[] = ['name'=>'服务订单','path'=>'YuyueOrder/index','authdata'=>'YuyueOrder/*'];
		$component_yuyue[] = ['name'=>'商品服务','path'=>'YuyueFuwu/index','authdata'=>'YuyueFuwu/*'];
		$component_yuyue[] = ['name'=>'商品评价','path'=>'YuyueComment/index','authdata'=>'YuyueComment/*'];
		if($isadmin) {
			$component_yuyue[] = ['name'=>'海报设置','path'=>'YuyuePoster/index','authdata'=>'YuyuePoster/*'];
		}
        //使用平台服务人员的，多商户不再管理人员
        $yuyueUserShow = true;
        if($yuyueUserShow){
            $component_yuyue[] = ['name'=>'人员类型','path'=>'YuyueWorkerCategory/index','authdata'=>'YuyueWorkerCategory/*'];
            $component_yuyue[] = ['name'=>'人员列表','path'=>'YuyueWorker/index','authdata'=>'YuyueWorker/*'];
            $component_yuyue[] = ['name'=>'人员评价','path'=>'YuyueWorkerComment/index','authdata'=>'YuyueWorkerComment/*'];
        }
		$component_yuyue[] = ['name'=>'提成明细','path'=>'YuyueMoney/moneylog','authdata'=>'YuyueMoney/*'];
		$component_yuyue[] = ['name'=>'提现记录','path'=>'YuyueMoney/withdrawlog','authdata'=>'YuyueMoney/*'];
		$component_yuyue[] = ['name'=>'系统设置','path'=>'YuyueSet/set','authdata'=>'YuyueSet/*'];
		$component_child[] = ['name'=>'预约服务','child'=>$component_yuyue];
		$component_kecheng=[];
		$component_kecheng[] = ['name'=>'课程分类','path'=>'KechengCategory/index','authdata'=>'KechengCategory/*'];
		$component_kecheng[] = ['name'=>'课程列表','path'=>'KechengList/index','authdata'=>'KechengList/*,KechengRecord/*'];
		$component_kecheng[] = ['name'=>'课程章节','path'=>'KechengChapter/index','authdata'=>'KechengChapter/*'];
		$component_kecheng[] = ['name'=>'题库管理','path'=>'KechengTiku/index','authdata'=>'KechengTiku/*'];
		$component_kecheng[] = ['name'=>'课程订单','path'=>'KechengOrder/index','authdata'=>'KechengOrder/*'];
		$component_kecheng[] = ['name'=>'学习记录','path'=>'KechengStudylog/index','authdata'=>'KechengStudylog/*'];
		if($isadmin){
		    if(getcustom('kecheng_lecturer')){
                $component_kecheng[] = ['name'=>'课程讲师','path'=>'KechengLecturer/index','authdata'=>'KechengLecturer/*'];
                if(!$ismenu || ($ismenu && $user && $user['lecturerid']>0)){
                    $component_kecheng[] = ['name'=>'个人资料','path'=>'KechengLecturer/mycenter','authdata'=>'KechengLecturer/mycenter'];
                }
                $component_kecheng[] = ['name'=>'讲师课程','path'=>'KechengLecturerList/index','authdata'=>'KechengLecturerList/*'];
            }
			$component_kecheng[] = ['name'=>'课程海报','path'=>'KechengPoster/index','authdata'=>'KechengPoster/*'];
			$component_kecheng[] = ['name'=>'课程设置','path'=>'KechengSet/index','authdata'=>'KechengSet/*'];
		}
		$component_child[] = ['name'=>'知识付费','child'=>$component_kecheng];
        $component_child[] = ['name'=>'视频管理','path'=>'VideoList/index','authdata'=>'VideoList/*'];


		if($isadmin){
			$component_peisong = [];
			$component_peisong[] = ['name'=>'配送员列表','path'=>'PeisongUser/index','authdata'=>'PeisongUser/*'];
			$component_peisong[] = ['name'=>'配送单列表','path'=>'PeisongOrder/index','authdata'=>'PeisongOrder/*'];
			$component_peisong[] = ['name'=>'评价列表','path'=>'PeisongComment/index','authdata'=>'PeisongComment/*'];
			$component_peisong[] = ['name'=>'提成明细','path'=>'PeisongMoney/moneylog','authdata'=>'PeisongMoney/*'];
			$component_peisong[] = ['name'=>'提现记录','path'=>'PeisongMoney/withdrawlog','authdata'=>'Peisong/*'];
			$component_peisong[] = ['name'=>'系统设置','path'=>'Peisong/set','authdata'=>'Peisong/*'];
			$component_peisong[] = ['name'=>t('码科跑腿').'对接','path'=>'Peisong/makeset','authdata'=>'Peisong/*'];
			if(getcustom('express_maiyatian')){
                $component_peisong[] = ['name'=>'麦芽田设置','path'=>'Peisong/mytset','authdata'=>'Peisong/*'];
                $component_peisong[] = ['name'=>'麦芽田订单','path'=>'MytPeisongOrder/index','authdata'=>'MytPeisongOrder/*'];
            }
            if(getcustom('paotui')){
                $component_peisong[] = ['name'=>'跑腿设置','path'=>'PaotuiSet/index','authdata'=>'PaotuiSet/*'];
                $component_peisong[] = ['name'=>'跑腿订单','path'=>'PaotuiOrder/index','authdata'=>'PaotuiOrder/*'];
            }
			$component_child[] = ['name'=>'同城配送','child'=>$component_peisong];
            if(in_array('wx',$platform)){
                $component_child[] = ['name'=>'物流助手','path'=>'Miandan/index','authdata'=>'Miandan/*'];
            }
			$component_express = [];
			$component_express[] = ['name'=>'活动列表','path'=>'Toupiao/index','authdata'=>'Toupiao/*'];
			$component_express[] = ['name'=>'选手列表','path'=>'Toupiao/joinlist','authdata'=>'Toupiao/*'];
			$component_express[] = ['name'=>'投票记录','path'=>'Toupiao/helplist','authdata'=>'Toupiao/*'];
            $component_express[] = ['name'=>'投票分组','path'=>'ToupiaoGroup/index','authdata'=>'ToupiaoGroup/*'];
			//$component_express[] = ['name'=>'投票设置','path'=>'Toupiao/set','authdata'=>'Toupiao/*'];
			$component_child[] = ['name'=>'投票活动','child'=>$component_express];
		}else{
            if(getcustom('express_maiyatian')){
            	$component_peisong = [];
				$component_peisong[] = ['name'=>'配送单列表','path'=>'PeisongOrder/index','authdata'=>'PeisongOrder/*'];
                $component_peisong[] = ['name'=>'麦芽田设置','path'=>'Peisong/mytset','authdata'=>'Peisong/*'];
                $component_child[] = ['name'=>'同城配送','child'=>$component_peisong];
            }
        }

		$yingxiao_cycle = [];
		$yingxiao_cycle[] = ['name'=>'商品管理','path'=>'CycleProduct/index','authdata'=>'CycleProduct/*,CycleCode/*'];
		$yingxiao_cycle[] = ['name'=>'订单管理','path'=>'CycleOrder/index','authdata'=>'CycleOrder/*'];
		$yingxiao_cycle[] = ['name'=>'配送管理','path'=>'CycleOrder/cycle_list&status=1','authdata'=>'CycleOrder/*'];
		$yingxiao_cycle[] = ['name'=>'评价管理','path'=>'CycleComment/index','authdata'=>'CycleComment/*'];
		if($isadmin) {
			$yingxiao_cycle[] = ['name'=>'商品分类','path'=>'CycleCategory/index','authdata'=>'CycleCategory/*'];
			$yingxiao_cycle[] = ['name'=>'分享海报','path'=>'CyclePoster/index','authdata'=>'CyclePoster/*'];
			$yingxiao_cycle[] = ['name'=>'系统设置','path'=>'CycleSet/index','authdata'=>'CycleSet/*'];
		}
		$component_child[] = ['name'=>'周期购','child'=>$yingxiao_cycle];
		if($isadmin) {
			$component_mingpian=[];
			$component_mingpian[] = ['name'=>'名片列表','path'=>'Mingpian/index','authdata'=>'Mingpian/*'];
			$component_mingpian[] = ['name'=>'系统设置','path'=>'Mingpian/set','authdata'=>'Mingpian/*'];
			$component_child[] = ['name'=>'名片','child'=>$component_mingpian];
		}
		$cashier_child = [];
		$cashier_child[] = ['name' => '收银设置', 'path' => 'Cashier/index', 'authdata' => 'Cashier/*'];
		$cashier_child[] = ['name' => '收银订单', 'path' => 'CashierOrder/index', 'authdata' => 'CashierOrder/*'];
		$cashier_child[] = ['name' => '订单统计', 'path' => 'CashierOrder/tongji', 'authdata' => 'CashierOrder/*'];
		$component_child[] = ['name'=>'收银台','child'=>$cashier_child];
		if(getcustom('customer')) {
            $customer_child = [];
            $customer_child[] = ['name' => '客户列表', 'path' => 'ShCustomer/index', 'authdata' => 'ShCustomer/*'];
            $component_child[] = ['name' => '客户管理', 'child' => $customer_child];
        }
		$component_child[] = ['name'=>t('自定义表单'),'path'=>'Form/index','authdata'=>'Form/*'];
		if($isadmin) {
			$lipin_child = [];
			$lipin_child[] = ['name' => '礼品卡', 'path' => 'Lipin/index', 'authdata' => 'Lipin/*'];
			$lipin_child[] = ['name' => '礼品卡兑换码', 'path' => 'Lipin/codelist', 'authdata' => 'Lipin/codelist'];
			$lipin_child[] = ['name'=>'兑换码生成','path'=>'Lipin/makecode','authdata'=>'Lipin/makecode','hide'=>true];
			$lipin_child[] = ['name'=>'兑换码导入','path'=>'Lipin/importexcel','authdata'=>'Lipin/importexcel','hide'=>true];
			$lipin_child[] = ['name'=>'兑换码导出','path'=>'Lipin/codelistexcel','authdata'=>'Lipin/codelistexcel','hide'=>true];
			$lipin_child[] = ['name'=>'兑换码修改状态','path'=>'Lipin/setst','authdata'=>'Lipin/setst','hide'=>true];
			$lipin_child[] = ['name'=>'兑换码删除','path'=>'Lipin/codelistdel','authdata'=>'Lipin/codelistdel','hide'=>true];
			$lipin_child[] = ['name' => '礼品卡分类', 'path' => 'LipinCategory/index', 'authdata' => 'LipinCategory/*'];
			$component_child[] = ['name'=>'礼品卡','child'=>$lipin_child];
			if(in_array('wx',$platform)) {
				$component_child[] = ['name'=>'小程序直播','path'=>'Live/index','authdata'=>'Live/*'];
				/*
				$component_child[] = ['name'=>'视频号接入','child'=>[
					['name'=>'申请接入','path'=>'Wxvideo/apply','authdata'=>'Wxvideo/*'],
					['name'=>'商家信息','path'=>'Wxvideo/setinfo','authdata'=>'Wxvideo/*'],
					['name'=>'商品管理','path'=>'ShopProduct/index&fromwxvideo=1','authdata'=>'ShopProduct/*,ShopCode/*'],
					['name'=>'订单管理','path'=>'ShopOrder/index&fromwxvideo=1','authdata'=>'ShopOrder/*'],
					['name'=>'退款申请','path'=>'ShopRefundOrder/index&fromwxvideo=1','authdata'=>'ShopRefundOrder/*'],
					['name'=>'我的类目','path'=>'Wxvideo/category','authdata'=>'Wxvideo/*'],
					['name'=>'我的品牌','path'=>'Wxvideo/brand','authdata'=>'Wxvideo/*'],
					['name'=>'同步修复','path'=>'Wxvideo/deliverytongbu','authdata'=>'Wxvideo/*'],
				]];
				*/
			}
            if (getcustom('wx_fws_liuliangzhu')){
                $component_child[] = ['name'=>'微信广告','child'=>[
                    ['name'=>'广告列表','path'=>'Wxadvert/index','authdata'=>'Wxadvert/*'],
                    ['name'=>'汇总数据','path'=>'Wxadvert/addata','authdata'=>'Wxadvert/*'],
                    ['name'=>'结算数据','path'=>'Wxadvert/settle','authdata'=>'Wxadvert/*'],
                ]];
            }
			// if(in_array('toutiao',$platform)){
			// 	$component_child[] = ['name'=>'抖音接入','child'=>[
			// 		['name'=>'接入配置','path'=>'DouyinSet/index','authdata'=>'Douyin/*'],
			// 		['name'=>'商品管理','path'=>'ShopProduct/index&fromdouyin=1','authdata'=>'ShopProduct/*,ShopCode/*'],
			// 		['name'=>'商品管理','path'=>'DouyinProduct/index','authdata'=>'DouyinProduct/*'],
			// 	]];
			// }
            if(getcustom('headimg')){
                $component_child[] = ['name'=>'头像库','path'=>'Headimg/index','authdata'=>'Headimg/*'];
            }
            }
        if($isadmin) {
            }

        if($isadmin) {
            }
        //海康摄像机设置
        $qrcode_fenzhang =   getcustom('extend_qrcode_variable_fenzhang');
        $menudata['component'] = ['name'=>'扩展','fullname'=>'扩展功能','icon'=>'my-icon my-icon-kuozhan','child'=>$component_child];

		if(getcustom('restaurant')){
			$menudata['restaurant'] = \app\customs\Restaurant::getmenu($isadmin);
		}

		 $jiemian_child = [];
        if(false){}else{
			$jiemian_child[] = ['name'=>'页面设计','path'=>'DesignerPage/index','authdata'=>'DesignerPage/*'];
		}
        if(getcustom('design_cat')) {
            $jiemian_child[] = ['name'=>'页面分类','path'=>'DesignerCategory/index','authdata'=>'DesignerCategory/*'];
        }
        if($isadmin){
            $jiemian_child[] = ['name'=>'底部导航','path'=>'DesignerMenu/index','authdata'=>'DesignerMenu/*'];
            $jiemian_child[] = ['name'=>'内页导航','path'=>'DesignerMenu/menu2','authdata'=>'DesignerMenu/*'];
			$jiemian_child[] = ['name'=>'商品详情','path'=>'DesignerMenuShopdetail/shopdetail','authdata'=>'DesignerMenuShopdetail/*'];
		} else {
			$jiemian_child[] = ['name'=>'底部导航','path'=>'DesignerMenu/menu2','authdata'=>'DesignerMenu/*'];
			$jiemian_child[] = ['name'=>'商品详情','path'=>'DesignerMenuShopdetail/shopdetail','authdata'=>'DesignerMenuShopdetail/*'];
		}
		if($isadmin) {
			$jiemian_child[] = ['name'=>'登录页面','path'=>'DesignerLogin/index','authdata'=>'DesignerLogin/*'];
			$jiemian_child[] = ['name'=>'移动端后台','path'=>'DesignerMobile/index','authdata'=>'DesignerMobile/*'];
		}
		$jiemian_child[] = ['name'=>'分享设置','path'=>'DesignerShare/index','authdata'=>'DesignerShare/*'];
		$jiemian_child[] = ['name'=>'链接地址','path'=>'DesignerPage/chooseurl','params'=>'/type/geturl','authdata'=>'DesignerPage/chooseurl,DesignerPage/getwxqrcode'];
		if(getcustom('design_template_cat_create')){
            if($uid == 0){
                $jiemian_child[] = ['name'=>'子站添加模板库及分类','path'=>'AddTemplateCat','authdata'=>'AddTemplateCat','hide'=>true];
            }
        }
		$menudata['jiemian'] = ['name'=>'设计','fullname'=>'界面设计','icon'=>'my-icon my-icon-sheji','child'=>$jiemian_child];
		if($isadmin) {
			$pingtai_child = [];
			if(in_array('mp',$platform)) {
				$pingtai_child_mp = [];
				$pingtai_child_mp[] = ['name'=>'公众号绑定','path'=>'Binding/index','authdata'=>'Binding/*'];
				$pingtai_child_mp[] = ['name'=>'菜单设置','path'=>'Mpmenu/index','authdata'=>'Mpmenu/*'];
				$pingtai_child_mp[] = ['name'=>'支付设置','path'=>'Mppay/set','authdata'=>'Mppay/*'];
				$pingtai_child_mp[] = ['name'=>'随行付分账','path'=>'SxpayFenzhang/*','authdata'=>'SxpayFenzhang/*','hide'=>true];
				$pingtai_child_mp[] = ['name'=>'模板消息设置','path'=>'Mptmpl/tmplset','authdata'=>'Mptmpl/*'];
				$pingtai_child_mp[] = ['name'=>'类目模板消息','path'=>'Mptmpl/tmplsetNew','authdata'=>'Mptmpl/tmplsetNew'];
				$pingtai_child_mp[] = ['name'=>'已添加的模板','path'=>'Mptmpl/mytmpl','authdata'=>'Mptmpl/*'];
				$pingtai_child_mp[] = ['name'=>'被关注回复','path'=>'Mpkeyword/subscribe','authdata'=>'Mpkeyword/*'];
				$pingtai_child_mp[] = ['name'=>'关键字回复','path'=>'Mpkeyword/index','authdata'=>'Mpkeyword/*'];
				$pingtai_child_mp[] = ['name'=>'粉丝列表','path'=>'Mpfans/fanslist','authdata'=>'Mpfans/*'];
				//$pingtai_child_mp[] = ['name'=>'素材管理','path'=>'Mpfans/sourcelist','authdata'=>'Mpfans/*'];
				$pingtai_child_mp[] = ['name'=>'模板消息群发','path'=>'Mpfans/tmplsend','authdata'=>'Mpfans/*'];
				//$pingtai_child_mp[] = ['name'=>'活跃粉丝群发','path'=>'Mpfans/kfmsgsend','authdata'=>'Mpfans/*'];
				$pingtai_child[] = ['name'=>'微信公众号','child'=>$pingtai_child_mp];
				$pingtai_child_mpcard = [];
				$pingtai_child_mpcard[] = ['name'=>'领取记录','path'=>'Membercard/record','authdata'=>'Membercard/record'];
				$pingtai_child_mpcard[] = ['name'=>'会员卡/创建','path'=>'Membercard/index','authdata'=>'Membercard/*'];
				$pingtai_child[] = ['name'=>'微信会员卡','child'=>$pingtai_child_mpcard];
			}
			if(in_array('wx',$platform)) {
				$pingtai_child_wx = [];
				$pingtai_child_wx[] = ['name'=>'小程序绑定','path'=>'Binding/index','authdata'=>'Binding/*'];
				$pingtai_child_wx[] = ['name'=>'支付设置','path'=>'Wxpay/set','authdata'=>'Wxpay/*'];
				$pingtai_child_wx[] = ['name'=>'订阅消息设置','path'=>'Wxtmpl/tmplset','authdata'=>'Wxtmpl/*'];
				$pingtai_child_wx[] = ['name'=>'服务类目','path'=>'Wxleimu/index','authdata'=>'Wxleimu/*'];
				$pingtai_child_wx[] = ['name'=>'外部链接','path'=>'Wxurl/index','authdata'=>'Wxurl/*'];
				//$pingtai_child_wx[] = ['name'=>'关键字回复','path'=>'Wxkeyword/index','authdata'=>'Wxkeyword/*'];
				$pingtai_child_wx[] = ['name'=>'半屏小程序','path'=>'Wxembedded/index','authdata'=>'Wxembedded/*'];
				$pingtai_child[] = ['name'=>'微信小程序','child'=>$pingtai_child_wx];
			}
			if(in_array('alipay',$platform)) {
				$pingtai_child[] = ['name'=>'支付宝小程序','path'=>'Binding/alipay','authdata'=>'Binding/*'];
			}
			if(in_array('baidu',$platform)) {
				$pingtai_child[] = ['name'=>'百度小程序','path'=>'Binding/baidu','authdata'=>'Binding/*'];
			}
			if(in_array('toutiao',$platform)) {
				$pingtai_child[] = ['name'=>'抖音小程序','path'=>'Binding/toutiao','authdata'=>'Binding/*'];
			}
			if(in_array('qq',$platform)) {
				$pingtai_child[] = ['name'=>'QQ小程序','path'=>'Binding/qq','authdata'=>'Binding/*'];
			}
			if(in_array('h5',$platform)) {
				$pingtai_child[] = ['name'=>'手机H5','path'=>'Binding/h5','authdata'=>'Binding/*'];
			}
			if(in_array('app',$platform)) {
				$pingtai_child[] = ['name'=>'手机APP','path'=>'Binding/app','authdata'=>'Binding/*'];
				$pingtai_child[] = ['name'=>'app管理','path'=>'Binding/apk','authdata'=>'Binding/apk'];
			}
			if($pingtai_child)
						$menudata['pingtai'] = ['name'=>'平台','fullname'=>'平台设置','icon'=>'my-icon my-icon-pingtai','child'=>$pingtai_child];
		}
		$system_child = [];
		$system_child[] = ['name'=>'系统设置','path'=>'Backstage/sysset','authdata'=>'Backstage/sysset'];
		$system_child[] = ['name'=>'门店管理','path'=>'Mendian/index','authdata'=>'Mendian/*'];
		if(getcustom('reward_business_money')) {
			$system_child[] = ['name'=>'实体门店','path'=>'Mendian/index2','authdata'=>'Mendian/*'];
		}
		$system_child[] = ['name'=>'管理员列表','path'=>'User/index','authdata'=>'User/*,UserGroup/*'];
		$system_child[] = ['name'=>'配送方式','path'=>'Freight/index','authdata'=>'Freight/*'];
        $system_child[] = ['name'=>'快递设置','path'=>'ExpressData/index','authdata'=>'ExpressData/*'];
        $system_child[] = ['name'=>'送货单设置','path'=>'ShdSet/index','authdata'=>'ShdSet/*'];
		$system_child[] = ['name'=>'小票打印机','path'=>'Wifiprint/index','authdata'=>'Wifiprint/*'];
		if($isadmin) {
			$system_child[] = ['name'=>'短信设置','path'=>'Sms/set','authdata'=>'Sms/*'];
		} else {
			$system_child[] = ['name'=>'店铺评价','path'=>'BusinessComment/index','authdata'=>'BusinessComment/*'];
		}
		if($isadmin) {
			//    $system_child[] = ['name' => '企微', 'path' => 'Backstage/huidong', 'authdata' => 'Backstage/huidong'];
		}
		$system_child[] = ['name'=>'操作日志','path'=>'Backstage/plog','authdata'=>'Backstage/plog'];
	
		$menudata['system'] = ['name'=>'系统','fullname'=>'系统设置','icon'=>'my-icon my-icon-sysset','child'=>$system_child];
		
		
		
		
		
		if($user && $user['auth_type']==0) {
			if($user['groupid']) {
				$user['auth_data'] = Db::name('admin_user_group')->where('id',$user['groupid'])->value('auth_data');
			}
			
	
			
			$auth_data = json_decode($user['auth_data'],true);
			foreach($menudata as $k=>$v){
				if($v['child']){
                    $needcheckchild = true;//需要检验子权限
                    if($needcheckchild){
                        foreach($v['child'] as $k1=>$v1){
                            if(!$v1['authdata'] && $v1['child']){
                                $path = array();
                                foreach($v1['child'] as $k2=>$v2){
                                    if(!in_array($v2['path'].','.$v2['authdata'],$auth_data)){
                                        unset($menudata[$k]['child'][$k1]['child'][$k2]);
                                    }
                                }
                                if(count($menudata[$k]['child'][$k1]['child'])==0){
                                    unset($menudata[$k]['child'][$k1]);
                                }
                            }else{
                                if(!in_array($v1['path'].','.$v1['authdata'],$auth_data)){
                                    unset($menudata[$k]['child'][$k1]);
                                }
                            }
                        }
                        if(count($menudata[$k]['child'])==0){
                            unset($menudata[$k]);
                        }
                    }
				}else{
					if(!in_array($v['path'].','.$v['authdata'],$auth_data)){
						unset($menudata[$k]);
					}
				}
			}
		} else {
			foreach($menudata as $k=>$v) {
				if($v['child']) {
					foreach($v['child'] as $k1=>$v1) {
						if($v1['child']) {
							if(count($menudata[$k]['child'][$k1]['child'])==0) {
								unset($menudata[$k]['child'][$k1]);
							}
						}
					}
				} else {
					if(count($menudata[$k]['child'])==0) {
						unset($menudata[$k]);
					}
				}
			}
		}
		return $menudata;
	}

    public static function getdata2($uid=0){
        $menudata = [];

        if(!getcustom('admin_user_hide')){
            $menudata['user'] = ['name'=>'用户列表','path'=>'WebUser/index'];
        }
        $menudata['wxpayset'] = ['name'=>'服务商配置','path'=>'WebSystem/wxpayset'];
        $menudata['wxpaylog'] = ['name'=>'微信支付记录','path'=>'WebSystem/wxpaylog'];
        $menudata['component'] = ['name'=>'开放平台设置','path'=>'WebSystem/component'];
        $child = [];
        $child[] = ['name'=>'系统设置','path'=>'WebSystem/set'];
        $menudata['sysset'] = ['name'=>'系统设置','path'=>'WebSystem/set','child'=>$child];
        $menudata['remote'] = ['name'=>'附件设置','path'=>'WebSystem/remote'];
        $menudata['help'] = ['name'=>'帮助中心','path'=>'WebHelp/index'];
        $menudata['webnotice'] = ['name'=>'通知公告','path'=>'WebNotice/index'];
        $menudata['upgrade'] = ['name'=>'系统升级','path'=>'WebUpgrade/index'];
        return $menudata;
    }

	//白名单 不校验权限
	public static function blacklist() {
		$data = [];
		$data[] = 'Backstage/index';
		$data[] = 'Backstage/welcome';
		$data[] = 'Backstage/welcomeOld';
		$data[] = 'Backstage/setpwd';
		$data[] = 'Backstage/about';
		$data[] = 'Help/*';
		$data[] = 'Upload/*';
		$data[] = 'DesignerPage/chooseurl';
        $data[] = 'DesignerPage/getwxqrcode';
		$data[] = 'Peisong/getpeisonguser';
		$data[] = 'Peisong/peisong';
		$data[] = 'Miandan/addorder';
		$data[] = 'Wxset/*';
		$data[] = 'Notice/*';
		$data[] = 'notice/*';
		$data[] = 'SxpayIncome/*';
		$data[] = 'Member/inputlockpwd';
		$data[] = 'MemberLevel/inputlockpwd';
		$data[] = 'ShopProduct/inputlockpwd';
		$data[] = 'Member/dolock';
		$data[] = 'MemberLevel/dolock';
		$data[] = 'ShopProduct/dolock';
		$data[] = 'MemberArchives/*';
		$data[] = 'Map/*';
		$data[] = 'DesignerPage/choosezuobiao';
		return $data;
	}

}