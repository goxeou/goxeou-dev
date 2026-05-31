<?php


//餐饮系统
namespace app\customs;
use think\facade\Db;
use think\facade\Log;

class Restaurant
{
	public static function getmenu($isadmin){
		//点餐
		$restaurant_child = [];
		$restaurant_child[] = ['name'=>'首页','path'=>'Restaurant/index','authdata'=>'Restaurant/*'];
		//菜品
		$restaurant_product[] = ['name'=>'菜品列表','path'=>'RestaurantProduct/index','authdata'=>'RestaurantProduct/*'];
		$restaurant_product[] = ['name'=>'菜品分类','path'=>'RestaurantProductCategory/index','authdata'=>'RestaurantProductCategory/*'];
        $restaurant_product[] = ['name'=>'菜品采集','path'=>'RestaurantTaobao/index','authdata'=>'RestaurantTaobao/*'];
		$restaurant_child[] = ['name'=>'菜品','child'=>$restaurant_product];

		//外卖
		$restaurant_waimai[] = ['name'=>'外卖订单','path'=>'RestaurantTakeawayOrder/index','authdata'=>'RestaurantTakeawayOrder/*'];
		$restaurant_waimai[] = ['name'=>'评价管理','path'=>'RestaurantTakeawayComment/index','authdata'=>'RestaurantTakeawayComment/*'];
		$restaurant_waimai[] = ['name'=>'配送方式','path'=>'RestaurantTakeawayFreight/index','authdata'=>'RestaurantTakeawayFreight/*'];
		$restaurant_waimai[] = ['name'=>'外卖设置','path'=>'RestaurantTakeawaySet/index','authdata'=>'RestaurantTakeawaySet/*'];
        if(getcustom('goods_hexiao')) {
            $restaurant_waimai[] = ['name'=>'广告设置','path'=>'RestaurantTakeawayAdv/index','authdata'=>'RestaurantTakeawayAdv/*'];
        }
        if(getcustom('restaurant_mobile_admin_refund')){
            $restaurant_waimai[] = ['name'=>'手机管理外卖退款','authdata'=>'RestaurantTakeawayAdminRefund','hide'=>true];
        }
		$restaurant_child[] = ['name'=>'外卖','child'=>$restaurant_waimai];

		//店内点餐
		$restaurant_shop[] = ['name'=>'点餐订单','path'=>'RestaurantShopOrder/index','authdata'=>'RestaurantShopOrder/*'];
		$restaurant_shop[] = ['name'=>'评价管理','path'=>'RestaurantShopComment/index','authdata'=>'RestaurantShopComment/*'];
		$restaurant_shop[] = ['name'=>'点餐设置','path'=>'RestaurantShopSet/index','authdata'=>'RestaurantShopSet/*'];
        if(getcustom('restaurant_shop_designer_page')){
            $restaurant_shop[] = ['name'=>'点餐页设计','path'=>'RestaurantShopDesignerPage/index','authdata'=>'RestaurantShopSet/*'];
        }
        if(getcustom('restaurant_mobile_admin_refund')){
            $restaurant_shop[] = ['name'=>'手机管理点餐退款','authdata'=>'RestaurantShopAdminRefund','hide'=>true];
        }
		$restaurant_child[] = ['name'=>'点餐','child'=>$restaurant_shop];
		
		//预定
		$restaurant_booking[] = ['name'=>'预定订单','path'=>'RestaurantBookingOrder/index','authdata'=>'RestaurantBookingOrder/*'];
		$restaurant_booking[] = ['name'=>'预定设置','path'=>'RestaurantBookingSet/index','authdata'=>'RestaurantBookingSet/*'];
		$restaurant_child[] = ['name'=>'预定','child'=>$restaurant_booking];

		//排队
		$restaurant_queue[] = ['name'=>'排队队列','path'=>'RestaurantQueue/index','authdata'=>'RestaurantQueue/*'];
		$restaurant_queue[] = ['name'=>'队列设置','path'=>'RestaurantQueueCategory/index','authdata'=>'RestaurantQueueCategory/*'];
		$restaurant_queue[] = ['name'=>'排队设置','path'=>'RestaurantQueueSet/index','authdata'=>'RestaurantQueueSet/*'];
		$restaurant_child[] = ['name'=>'排队','child'=>$restaurant_queue];

		//寄存
		$restaurant_deposit[] = ['name'=>'寄存管理','path'=>'RestaurantDeposit/index','authdata'=>'RestaurantDeposit/*'];
		$restaurant_deposit[] = ['name'=>'添加寄存','path'=>'RestaurantDeposit/edit','authdata'=>'RestaurantDeposit/*'];
		$restaurant_deposit[] = ['name'=>'寄存设置','path'=>'RestaurantDepositSet/index','authdata'=>'RestaurantDepositSet/*'];
		$restaurant_child[] = ['name'=>'寄存','child'=>$restaurant_deposit];

		//餐桌
		$restaurant_tables[] = ['name'=>'餐桌列表','path'=>'RestaurantTable/index','authdata'=>'RestaurantTable/*'];
		$restaurant_tables[] = ['name'=>'餐桌分类','path'=>'RestaurantTableCategory/index','authdata'=>'RestaurantTableCategory/*'];
		$restaurant_child[] = ['name'=>'餐桌','child'=>$restaurant_tables];
		
		
		$yingxiao_child = [];
		$yingxiao_child[] = ['name'=>t('优惠券'),'path'=>'RestaurantCoupon/index','authdata'=>'RestaurantCoupon/*,RestaurantProductCategory/index,RestaurantProductCategory/choosecategory'];
		$yingxiao_child[] = ['name'=>'促销','path'=>'RestaurantCuxiao/index','authdata'=>'RestaurantCuxiao/*'];
		$restaurant_child[] = ['name'=>'营销','child'=>$yingxiao_child];
         if(getcustom('restaurant_shop_cashdesk')){
             $restaurant_cashdesk = [];
             $restaurant_cashdesk[] = ['name' => '收银台登录','path'=>'RestaurantCashdesk/login','authdata'=>'RestaurantCashdesk/login','hide'=>true];
             $restaurant_cashdesk[] = ['name' => '收银设置', 'path' => 'RestaurantCashdesk/index', 'authdata' => 'RestaurantCashdesk/*'];
             $restaurant_cashdesk[] = ['name' => '收银订单', 'path' => 'RestaurantCashdeskOrder/index', 'authdata' => 'RestaurantCashdeskOrder/*'];
             $restaurant_cashdesk[] = ['name' => '订单统计', 'path' => 'RestaurantCashdeskOrder/tongji', 'authdata' => 'RestaurantCashdeskOrder/*'];
             if(getcustom('restaurant_cashdesk_custom_pay')){
                 $restaurant_cashdesk[] = ['name' => '自定义支付', 'path' => 'RestaurantCashdeskCustomPay/index', 'authdata' => 'RestaurantCashdeskCustomPay/*'];
             }
             if(getcustom('restaurant_cashdesk_auth_enter')){
                 $restaurant_cashdesk[] = ['name'=>'直接优惠','path'=>'RestaurantCashdesk/discount','authdata'=>'RestaurantCashdesk/discount','hide'=>true];
                 $restaurant_cashdesk[] = ['name'=>'退款','path'=>'RestaurantCashdesk/refund','authdata'=>'RestaurantCashdesk/refund','hide'=>true];
                 $restaurant_cashdesk[] = ['name'=>'取消','path'=>'RestaurantCashdesk/cancel','authdata'=>'RestaurantCashdesk/cancel','hide'=>true];
             }
             if(getcustom('restaurant_take_food')){
                 $restaurant_cashdesk[] = ['name'=>'出餐','path'=>'RestaurantCashdesk/outfood','authdata'=>'RestaurantCashdesk/outfood','hide'=>true];
             }
             if(getcustom('restaurant_cashdesk_baobiao')){
                 $restaurant_cashdesk[] = ['name'=>'报表时间自定义','path'=>'RestaurantCashdesk/BaobiaoCustomTime','authdata'=>'RestaurantCashdesk/BaobiaoCustomTime','hide'=>true];
             }
             $restaurant_child[] = ['name'=>'收银台','child'=>$restaurant_cashdesk];
         }
         if(getcustom('restaurant_take_food')){
            $take_food = [];
            $take_food[] =  ['name' => '取餐列表', 'path' => 'RestaurantTakeFood/index', 'authdata' => 'RestaurantTakeFood/index'];
            $take_food[] =  ['name' => '取餐设置', 'path' => 'RestaurantTakeFood/sysset', 'authdata' => 'RestaurantTakeFood/sysset'];
            $restaurant_child[] = ['name'=>'取餐','child'=>$take_food];
        }
        $restaurant_child[] = ['name'=>'餐厅区域打印','path'=>'RestaurantPrint/index','authdata'=>'RestaurantPrint/*'];
        if(getcustom('restaurant_shop_cashdesk')) {
            $restaurant_child[] = ['name' => '备注预置', 'path' => 'RestaurantRemark/index', 'authdata' => 'RestaurantRemark/*'];
        }
        if(getcustom('restaurant_tongji')){
            $restaurant_child[] = ['name'=>'统计','path'=>'Restaurant/tongji','authdata'=>'Restaurant/tongji'];
        }
		if($isadmin){
			$restaurant_child[] = ['name'=>'设置','path'=>'Restaurant/sysset','authdata'=>'Restaurant/*'];
		}
		return ['name'=>'餐饮','fullname'=>'餐饮系统','icon'=>'my-icon my-icon-canyin','child'=>$restaurant_child];
	}
	//配送方式默认
	public static function init_freight($aid,$bid){
		$freight = Db::name('restaurant_takeaway_freight')->where('bid',$bid)->find();
		if(!$freight){
			Db::name('restaurant_takeaway_freight')->insert([
				'aid'=>$aid,
				'bid'=>$bid,
				'name'=>'同城配送',
				'pstype'=>2,
				'pricedata'=>'[{"region":"全国(默认运费)","fristweight":"1000","fristprice":"0","secondweight":"1000","secondprice":"0"}]',
				'pstimedata'=>'[{"day":"1","hour":"12","minute":"0","hour2":"12","minute2":"30"},{"day":"1","hour":"18","minute":"0","hour2":"18","minute2":"30"},{"day":"2","hour":"12","minute":"0","hour2":"12","minute2":"30"},{"day":"2","hour":"18","minute":"0","hour2":"18","minute2":"30"}]',
				'psprehour'=>4,
				'peisong_juli1'=>5,
				'peisong_fee1'=>3,
				'peisong_juli2'=>1,
				'peisong_fee2'=>1,
				'peisong_lng'=>$business['longitude'],
				'peisong_lat'=>$business['latitude'],
				'peisong_range'=>2000,
				'status'=>1,
			]);
			Db::name('restaurant_takeaway_freight')->insert([
				'aid'=>$aid,
				'bid'=>$bid,
				'name'=>'到店自提',
				'pstype'=>1,
				'pricedata'=>'[{"region":"全国(默认运费)","fristweight":"1000","fristprice":"0","secondweight":"1000","secondprice":"0"}]',
				'pstimedata'=>'[{"day":"1","hour":"12","minute":"0","hour2":"12","minute2":"30"},{"day":"1","hour":"18","minute":"0","hour2":"18","minute2":"30"},{"day":"2","hour":"12","minute":"0","hour2":"12","minute2":"30"},{"day":"2","hour":"18","minute":"0","hour2":"18","minute2":"30"}]',
				'status'=>1,
			]);
		}
		$info = Db::name('restaurant_takeaway_sysset')->where('aid',$aid)->where('bid',$bid)->find();
		if(!$info){
			Db::name('restaurant_takeaway_sysset')->insert(['aid'=>$aid,'bid'=>$bid]);
		}
	}
	//外卖订单完成
	public static function takeaway_orderconfirm($orderid){
		$order = Db::name('restaurant_takeaway_order')->where('id',$orderid)->find();
		if($order['status']==3) return ['status'=>0,'msg'=>'订单已完成'];
        // 启动事务
        Db::startTrans();
        try {
            Db::name('restaurant_takeaway_order')->where('id',$orderid)->update(['status'=>3,'collect_time'=>time()]);
            Db::name('restaurant_takeaway_order_goods')->where('orderid',$orderid)->where('status','<>',3)->update(['status'=>3,'endtime'=>time()]);
            $aid = $order['aid'];
            if($order['bid']!=0){
                //店铺加销量
                $totalnum = Db::name('restaurant_takeaway_order_goods')->where('aid',$aid)->where('orderid',$order['id'])->sum('num');
                Db::name('business')->where('aid',$aid)->where('id',$order['bid'])->inc('sales',$totalnum)->update();
            }
            if(!$order['paytypeid']) return ['status'=>1,'msg'=>''];
            $businessDkScore = $businessDkMoney = 0;
            $oglist = Db::name('restaurant_takeaway_order_goods')->where('aid',$aid)->where('orderid',$order['id'])->select()->toArray();
            if($order['bid']!=0){//入驻商家的货款

                $totalcommission = 0;
                $all_cost_price = 0;
                $lirun_cost_price = 0;
                $total_cost_price = 0;
                foreach($oglist as $og){
                    if($og['iscommission']) continue;
                    if($og['parent1'] && $og['parent1commission'] > 0){
                        $totalcommission += $og['parent1commission'];
                    }
                    if($og['parent2'] && $og['parent2commission'] > 0){
                        $totalcommission += $og['parent2commission'];
                    }
                    if($og['parent3'] && $og['parent3commission'] > 0){
                        $totalcommission += $og['parent3commission'];
                    }
                    if(getcustom('business_deduct_cost')){
						if(!empty($og['cost_price']) && $og['cost_price']>0){
							if($og['cost_price']<=$og['sell_price']){
								$all_cost_price += $og['cost_price'];
							}else{
								$all_cost_price += $og['sell_price'];
							}
						}
					}
                    if(getcustom('business_agent')){
                        if(!empty($og['cost_price']) && $og['cost_price']>0){
                                $lirun_cost_price += ($og['cost_price']*$og['num']);
                        }
                    }
                    if(getcustom('business_fee_type')){
                        if(!empty($og['cost_price']) && $og['cost_price']>0){
                                $total_cost_price += ($og['cost_price']*$og['num']);
                        }
                    }
                }
                $binfo = Db::name('business')->where('aid',$aid)->where('id',$order['bid'])->find();
                $bset = Db::name('business_sysset')->where('aid',$aid)->find();
                $business_lirun = 0;
                if(getcustom('business_agent')){
                    $business_lirun = $order['totalprice']-$order['refund_money']-$lirun_cost_price;
                }
                if($bset['commission_kouchu'] == 0){ //不扣除佣金
                    $totalcommission = 0;
                }
                $scoredkmoney = $leveldkmoney = 0;
                if($bset['leveldk_kouchu'] == 1){ //扣除会员抵扣
                    $leveldkmoney = $order['leveldk_money'] ?? 0;
                }
                if($bset['scoredk_kouchu'] == 0){
                    $scoredkmoney = 0;
                }elseif($bset['scoredk_kouchu'] == 1){ //扣除积分抵扣
                    $scoredkmoney = $order['scoredk_money'] ?? 0;
                }elseif($bset['scoredk_kouchu'] == 2){ //到商户余额
                    $businessDkMoney = $order['scoredk_money'];
                }elseif($bset['scoredk_kouchu'] == 3){ //到商户积分
                    $scoredkmoney = $order['scoredk_money'] ?? 0;
                    $businessDkScore = $order['scoredkscore'];
                }
                $totalmoney = $order['product_price'] + $order['pack_fee'] + $order['freight_price'] - $order['coupon_money'] - $order['manjia_money'] - $totalcommission - $leveldkmoney - $scoredkmoney;
                if($totalmoney > 0){
                	if(getcustom('business_deduct_cost')){
                    	if($binfo && $binfo['deduct_cost'] == 1){
                        	//扣除成本
                            $platformMoney = ($totalmoney-$all_cost_price)*$binfo['feepercent']/100;
                        }else{
                            $platformMoney = $totalmoney * $binfo['feepercent'] * 0.01;
                        }
	                }else{
                        $platformMoney = $totalmoney * $binfo['feepercent'] * 0.01;                        
	                }
                    if(getcustom('business_fee_type')){
                        if($bset['business_fee_type'] == 1){
                            $platformMoney = ($totalmoney-$order['freight_price']) * $binfo['feepercent'] * 0.01;
                        }elseif($bset['business_fee_type'] == 2){
                            $platformMoney = $total_cost_price * $binfo['feepercent'] * 0.01;
                        }
                    } 
                    $totalmoney = $totalmoney - $platformMoney;
                }
	            $isbusinesspay = Db::name('payorder')->where('aid',$aid)->where('ordernum',$order['ordernum'])->value('isbusinesspay');
				if(!$isbusinesspay){
	                if($totalmoney < 0){
	                    $bmoney = Db::name('business')->where('aid',$aid)->where('id',$order['bid'])->value('money');
	                    if($bmoney + $totalmoney < 0){
	                        return ['status'=>0,'msg'=>'操作失败,商家余额不足'];
	                    }
	                }
	                \app\commons\Business::addmoney($aid,$order['bid'],$totalmoney,'货款，外卖订单号：'.$order['ordernum'],true,'restaurant_takeaway',$order['ordernum'],['platformMoney'=>$platformMoney,'business_lirun'=>$business_lirun]);
	            }else{
					//商家推荐分成
					if($totalmoney > 0){
						if(getcustom('business_agent')){
							\app\commons\Business::addparentcommission2($aid,$order['bid'],$totalmoney,$platformMoney,$business_lirun);
						}else{
							\app\commons\Business::addparentcommission($aid,$order['bid'],$totalmoney);
						}
					}
				}
                Db::name('restaurant_takeaway_order_goods')->where('aid',$aid)->where('orderid',$orderid)->update(['iscommission' => 1]);
                if(getcustom('yx_jidian') && $order['bid']) {
                    $jidian_set = Db::name('jidian_set')->where('aid', $aid)->where('bid', $order['bid'])->find();
                    $paygive_scene = explode(',',$jidian_set['paygive_scene']);
                    //集点
                    if($jidian_set && in_array('restaurant_takeaway',$paygive_scene) && $jidian_set['status'] == 1 && time() >= $jidian_set['starttime'] && time() <= $jidian_set['endtime']){
                        //执行时此笔订单还没收货
                        \app\commons\System::getOrderNumFromJidian($aid,$order['bid'],$jidian_set,$order['mid'],0,true);
                    }
                }

            }
            //赠积分
            if($order['givescore'] > 0){
                \app\commons\Member::addscore(aid,$order['mid'],$order['givescore'],'购买菜品赠送'.t('积分'));
            }
            if(getcustom('business_score_jiesuan') && $order['bid']>0){
                if($businessDkMoney>0){
                    \app\commons\Business::addmoney($aid,$order['bid'],$businessDkMoney,t('积分').'抵扣转'.t('余额').'，外卖订单号：'.$order['ordernum'],false,'restaurant_takeaway',$order['ordernum']);
                }
                if($businessDkScore>0){
                    \app\commons\Business::addscore($aid,$order['bid'],$businessDkScore,t('积分').'抵扣到商户'.t('积分').'，外卖订单号：'.$order['ordernum']);
                }
            }
            //佣金
            $commission_record_list = Db::name('member_commission_record')->where('aid',$aid)->where('type','restaurant_takeaway')->where('orderid',$order['id'])->where('status',0)->select();
            foreach($commission_record_list as $commission_record){
                $og = Db::name('restaurant_takeaway_order_goods')->where('id',$commission_record['ogid'])->find();
                if($commission_record['commission'] > 0){
                    \app\commons\Member::addcommission($aid,$commission_record['mid'],$commission_record['frommid'],$commission_record['commission'],$commission_record['remark']);
                    $tmplcontent = [];
                    $tmplcontent['first'] = '恭喜您，成功分销菜品获得'.t('佣金').'：￥'.$commission_record['commission'];
                    $tmplcontent['remark'] = '点击进入查看~';
                    $tmplcontent['keyword1'] = $og['name']; //商品信息
                    $tmplcontent['keyword2'] = $og['sell_price'];//商品单价
                    $tmplcontent['keyword3'] = $commission_record['commission'].'元';//商品佣金
                    $tmplcontent['keyword4'] = date('Y-m-d H:i:s',$commission_record['createtime']);//分销时间
                    $rs = \app\commons\Wechat::sendtmpl($aid,$commission_record['mid'],'tmpl_fenxiaosuccess',$tmplcontent,m_url('pages/my/usercenter'));
                    //短信通知
                    $tel = Db::name('member')->where('id',$commission_record['mid'])->value('tel');
                    $rs = \app\commons\Sms::send($aid,$tel,'tmpl_fenxiaosuccess',['money'=>$commission_record['commission']]);
                }
                if($commission_record['score'] > 0){
                    \app\commons\Member::addscore($aid,$commission_record['mid'],$commission_record['score'],$commission_record['remark']);
                    $tmplcontent = [];
                    $tmplcontent['first'] = '恭喜您，成功分销菜品获得：'.$commission_record['mid'].t('积分');
                    $tmplcontent['remark'] = '点击进入查看~';
                    $tmplcontent['keyword1'] = $og['name']; //商品信息
                    $tmplcontent['keyword2'] = $og['sell_price'];//商品单价
                    $tmplcontent['keyword3'] = $commission_record['commission'].t('积分');//商品佣金
                    $tmplcontent['keyword4'] = date('Y-m-d H:i:s',$commission_record['createtime']);//分销时间
                    $rs = \app\commons\Wechat::sendtmpl($aid,$commission_record['mid'],'tmpl_fenxiaosuccess',$tmplcontent,m_url('pages/my/usercenter'));
                    //短信通知
                    //$tel = Db::name('member')->where('id',$commission_record['mid'])->value('tel');
                    //$rs = \app\commons\Sms::send($aid,$tel,'tmpl_fenxiaosuccess');
                }
                Db::name('member_commission_record')->where('id',$commission_record['id'])->update(['status'=>1,'endtime'=>time()]);
            }
            // 提交事务
            Db::commit();
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
        }

		return ['status'=>1,'msg'=>''];
	}
	//点餐订单完成
	public static function shop_orderconfirm($orderid){
		$order = Db::name('restaurant_shop_order')->where('id',$orderid)->find();
		if(empty($order))  return ['status'=>0,'msg'=>'订单不存在'];
		if($order['status']==3) return ['status'=>0,'msg'=>'订单已完成'];
        $businessDkScore = $businessDkMoney = 0;
		$aid = $order['aid'];
		if($order['bid']!=0){
			//店铺加销量
			$totalnum = Db::name('restaurant_takeaway_order_goods')->where('aid',$aid)->where('orderid',$order['id'])->sum('num');
			Db::name('business')->where('aid',$aid)->where('id',$order['bid'])->inc('sales',$totalnum)->update();
		}

		$oglist = Db::name('restaurant_shop_order_goods')->where('aid',$aid)->where('orderid',$order['id'])->select()->toArray();
		if($order['bid']!=0 && $order['paytypeid'] !=4){//入驻商家的货款
			
			$totalcommission = 0;
			$all_cost_price = 0;
			$lirun_cost_price = 0;
            $total_cost_price = 0;
			foreach($oglist as $og){
				if($og['iscommission']) continue;
				if($og['parent1'] && $og['parent1commission'] > 0){
					$totalcommission += $og['parent1commission'];
				}
				if($og['parent2'] && $og['parent2commission'] > 0){
					$totalcommission += $og['parent2commission'];
				}
				if($og['parent3'] && $og['parent3commission'] > 0){
					$totalcommission += $og['parent3commission'];
				}
				if(getcustom('business_deduct_cost')){
					if(!empty($og['cost_price']) && $og['cost_price']>0){
						if($og['cost_price']<=$og['sell_price']){
							$all_cost_price += $og['cost_price'];
						}else{
							$all_cost_price += $og['sell_price'];
						}
					}
				}

                if(getcustom('business_agent')){
                    if(!empty($og['cost_price']) && $og['cost_price']>0){
                            $lirun_cost_price += ($og['cost_price']*$og['num']);
                    }
                }
                if(getcustom('business_fee_type')){
                    if(!empty($og['cost_price']) && $og['cost_price']>0){
                            $total_cost_price += ($og['cost_price']*$og['num']);
                    }
                }
			}
			$binfo = Db::name('business')->where('aid',$aid)->where('id',$order['bid'])->find();
			$bset = Db::name('business_sysset')->where('aid',$aid)->find();
			if($bset['commission_kouchu'] == 0){ //不扣除佣金
				$totalcommission = 0;
			}

            $business_lirun = 0;
            if(getcustom('business_agent')){
                $business_lirun = $order['totalprice']-$order['refund_money']-$lirun_cost_price;
            }
            $scoredkmoney = $leveldkmoney = 0;
            if($bset['leveldk_kouchu'] == 1){ //扣除会员抵扣
                $leveldkmoney = $order['leveldk_money'] ?? 0;
            }
            if($bset['scoredk_kouchu'] == 0){
                $scoredkmoney = 0;
            }elseif($bset['scoredk_kouchu'] == 1){ //扣除积分抵扣
                $scoredkmoney = $order['scoredk_money'] ?? 0;
            }elseif($bset['scoredk_kouchu'] == 2){ //到商户余额
                $businessDkMoney = $order['scoredk_money'];
            }elseif($bset['scoredk_kouchu'] == 3){ //到商户积分
                $scoredkmoney = $order['scoredk_money'] ?? 0;
                $businessDkScore = $order['scoredkscore'];
            }
			$totalmoney = $order['totalprice'] - $order['coupon_money'] - $order['manjia_money'] - $totalcommission - $leveldkmoney - $scoredkmoney;
			if($totalmoney > 0){
				if(getcustom('business_deduct_cost')){
                	if($binfo && $binfo['deduct_cost'] == 1){
                    	//扣除成本
                        $platformMoney = ($totalmoney-$all_cost_price)*$binfo['feepercent']/100;
                    }else{
                        $platformMoney = $totalmoney * $binfo['feepercent'] * 0.01;
                    }
                }else{
                    $platformMoney = $totalmoney * $binfo['feepercent'] * 0.01;
                    
                }
                if(getcustom('business_fee_type')){
                    if($bset['business_fee_type'] == 1){
                        $platformMoney = ($totalmoney-$order['freight_price']) * $binfo['feepercent'] * 0.01;
                    }elseif($bset['business_fee_type'] == 2){
                        $platformMoney = $total_cost_price * $binfo['feepercent'] * 0.01;
                    }
                }
                $totalmoney = $totalmoney - $platformMoney; 
			}
            if($order['paytypeid']==4 || empty($order['paytype'])){
                $totalmoney = $totalmoney - $order['totalprice'];
            }
	        $isbusinesspay = Db::name('payorder')->where('aid',$aid)->where('ordernum',$order['ordernum'])->value('isbusinesspay');
			if(!$isbusinesspay){
				if($totalmoney < 0){
					$bmoney = Db::name('business')->where('aid',$aid)->where('id',$order['bid'])->value('money');
					if($bmoney + $totalmoney < 0){
						return ['status'=>0,'msg'=>'操作失败,商家余额不足'];
					}
				}
				\app\commons\Business::addmoney($aid,$order['bid'],$totalmoney,'货款，订单号：'.$order['ordernum'],true,'restaurant_shop',$order['ordernum'],['platformMoney'=>$platformMoney,'business_lirun'=>$business_lirun]);
			}else{
				//商家推荐分成
				if($totalmoney > 0){
					if(getcustom('business_agent')){
						\app\commons\Business::addparentcommission2($aid,$order['bid'],$totalmoney,$platformMoney,$business_lirun);
					}else{
						\app\commons\Business::addparentcommission($aid,$order['bid'],$totalmoney);
					}
				}
			}
			Db::name('restaurant_shop_order_goods')->where('aid',$aid)->where('orderid',$orderid)->update(['iscommission' => 1]);
            if(getcustom('yx_jidian') && $order['bid']) {
                $jidian_set = Db::name('jidian_set')->where('aid', $aid)->where('bid', $order['bid'])->find();
                $paygive_scene = explode(',',$jidian_set['paygive_scene']);
                //集点
                if($jidian_set && in_array('restaurant_shop',$paygive_scene) && $jidian_set['status'] == 1 && time() >= $jidian_set['starttime'] && time() <= $jidian_set['endtime']){
                    //执行时此笔订单还没收货
                    \app\commons\System::getOrderNumFromJidian($aid,$order['bid'],$jidian_set,$order['mid'],1,true);
                }
            }
		}
		//赠积分
		if($order['givescore'] > 0){
			\app\commons\Member::addscore(aid,$order['mid'],$order['givescore'],'购买菜品赠送'.t('积分'));
		}
        if(getcustom('business_score_jiesuan') && $order['bid']>0){
            if($businessDkMoney>0){
                \app\commons\Business::addmoney($aid,$order['bid'],$businessDkMoney,t('积分').'抵扣转'.t('余额').'，点餐订单号：'.$order['ordernum'],false,'restaurant_shop',$order['ordernum']);
            }
            if($businessDkScore>0){
                \app\commons\Business::addscore($aid,$order['bid'],$businessDkScore,t('积分').'抵扣到商户'.t('积分').'，点餐订单号：'.$order['ordernum']);
            }
        }

		//佣金
		$commission_record_list = Db::name('member_commission_record')->where('aid',$aid)->where('type','restaurant_shop')->where('orderid',$order['id'])->where('status',0)->select();
		foreach($commission_record_list as $commission_record){
			$og = Db::name('restaurant_shop_order_goods')->where('id',$commission_record['ogid'])->find();
			if($commission_record['commission'] > 0){
				\app\commons\Member::addcommission($aid,$commission_record['mid'],$commission_record['frommid'],$commission_record['commission'],$commission_record['remark']);
				$tmplcontent = [];
				$tmplcontent['first'] = '恭喜您，成功分销菜品获得'.t('佣金').'：￥'.$commission_record['commission'];
				$tmplcontent['remark'] = '点击进入查看~';
				$tmplcontent['keyword1'] = $og['name']; //商品信息
				$tmplcontent['keyword2'] = $og['sell_price'];//商品单价
				$tmplcontent['keyword3'] = $commission_record['commission'].'元';//商品佣金
				$tmplcontent['keyword4'] = date('Y-m-d H:i:s',$commission_record['createtime']);//分销时间
				$rs = \app\commons\Wechat::sendtmpl($aid,$commission_record['mid'],'tmpl_fenxiaosuccess',$tmplcontent,m_url('pages/my/usercenter'));
				//短信通知
				$tel = Db::name('member')->where('id',$commission_record['mid'])->value('tel');
				$rs = \app\commons\Sms::send($aid,$tel,'tmpl_fenxiaosuccess',['money'=>$commission_record['commission']]);
			}
			if($commission_record['score'] > 0){
				\app\commons\Member::addscore($aid,$commission_record['mid'],$commission_record['score'],$commission_record['remark']);
				$tmplcontent = [];
				$tmplcontent['first'] = '恭喜您，成功分销菜品获得：'.$commission_record['mid'].t('积分');
				$tmplcontent['remark'] = '点击进入查看~';
				$tmplcontent['keyword1'] = $og['name']; //商品信息
				$tmplcontent['keyword2'] = $og['sell_price'];//商品单价
				$tmplcontent['keyword3'] = $commission_record['commission'].t('积分');//商品佣金
				$tmplcontent['keyword4'] = date('Y-m-d H:i:s',$commission_record['createtime']);//分销时间
				$rs = \app\commons\Wechat::sendtmpl($aid,$commission_record['mid'],'tmpl_fenxiaosuccess',$tmplcontent,m_url('pages/my/usercenter'));
				//短信通知
				//$tel = Db::name('member')->where('id',$commission_record['mid'])->value('tel');
				//$rs = \app\commons\Sms::send($aid,$tel,'tmpl_fenxiaosuccess');
			}
			Db::name('member_commission_record')->where('id',$commission_record['id'])->update(['status'=>1,'endtime'=>time()]);
		}
        Db::name('restaurant_shop_order')->where('id',$orderid)->update(['status'=>3,'collect_time'=>time()]);
        Db::name('restaurant_shop_order_goods')->where('orderid',$orderid)->update(['status'=>3,'endtime'=>time()]);
		return ['status'=>1,'msg'=>''];
	}

    //预定订单完成
    public static function booking_orderconfirm($orderid){
        $order = Db::name('restaurant_booking_order')->where('id',$orderid)->find();
        if(empty($order))  return ['status'=>0,'msg'=>'订单不存在'];
        if($order['status']==3) return ['status'=>0,'msg'=>'订单已完成'];
        $businessDkScore = $businessDkMoney = 0;
        $aid = $order['aid'];
        if($order['bid']!=0 && $order['totalprice'] > 0){//入驻商家的货款

        	$oglist = Db::name('restaurant_booking_order_goods')->where('orderid',$orderid)->where('aid',$order['id'])->select()->toArray();

            $totalcommission = 0;
			$all_cost_price  = 0;
            $platformMoney   = 0;
            $lirun_cost_price   = 0;
            $total_cost_price   = 0;
			foreach($oglist as $og){
				if(getcustom('business_deduct_cost')){
					if(!empty($og['cost_price']) && $og['cost_price']>0){
						if($og['cost_price']<=$og['sell_price']){
							$all_cost_price += $og['cost_price'];
						}else{
							$all_cost_price += $og['sell_price'];
						}
					}
				}
                if(getcustom('business_agent')){
                    if(!empty($og['cost_price']) && $og['cost_price']>0){
                            $lirun_cost_price += ($og['cost_price']*$og['num']);
                    }
                }
                if(getcustom('business_fee_type')){
                    if(!empty($og['cost_price']) && $og['cost_price']>0){
                            $total_cost_price += ($og['cost_price']*$og['num']);
                    }
                }
			}

            $binfo = Db::name('business')->where('aid',$aid)->where('id',$order['bid'])->find();
            $bset = Db::name('business_sysset')->where('aid',$aid)->find();
            $scoredkmoney = $leveldkmoney = 0;
            if($bset['leveldk_kouchu'] == 1){ //扣除会员抵扣
                $leveldkmoney = $order['leveldk_money'] ?? 0;
            }
            if($bset['scoredk_kouchu'] == 0){
                $scoredkmoney = 0;
            }elseif($bset['scoredk_kouchu'] == 1){ //扣除积分抵扣
                $scoredkmoney = $order['scoredk_money'] ?? 0;
            }elseif($bset['scoredk_kouchu'] == 2){ //到商户余额
                $businessDkMoney = $order['scoredk_money'];
            }elseif($bset['scoredk_kouchu'] == 3){ //到商户积分
                $scoredkmoney = $order['scoredk_money'] ?? 0;
                $businessDkScore = $order['scoredkscore'];
            }
            $business_lirun = 0;
            if(getcustom('business_agent')){                    
                $business_lirun = $order['totalprice']-$order['refund_money']-$lirun_cost_price;
            }
                
            $totalmoney = $order['totalprice'] - $order['coupon_money'] - $order['manjia_money'] - $totalcommission - $leveldkmoney - $scoredkmoney;
            if($totalmoney > 0){
            	if(getcustom('business_deduct_cost')){
                	if($binfo && $binfo['deduct_cost'] == 1){
                    	//扣除成本
                        $platformMoney = ($totalmoney-$all_cost_price)*$binfo['feepercent']/100;
                    }else{
                        $platformMoney = $totalmoney * $binfo['feepercent'] * 0.01;
                    }
                    $totalmoney = $totalmoney - $platformMoney;
                }else{
//	                	$totalmoney = $totalmoney * (100-$binfo['feepercent']) * 0.01;
                    $platformMoney = $totalmoney * $binfo['feepercent'] * 0.01;
                    $totalmoney = $totalmoney - $platformMoney;
                }
            }
            if($order['paytypeid']==4 || empty($order['paytype'])){
                $totalmoney = $totalmoney - $order['totalprice'];
            }
	        $isbusinesspay = Db::name('payorder')->where('aid',$aid)->where('ordernum',$order['ordernum'])->value('isbusinesspay');
			if(!$isbusinesspay){
	            if($totalmoney < 0){
	                $bmoney = Db::name('business')->where('aid',$aid)->where('id',$order['bid'])->value('money');
	                if($bmoney + $totalmoney < 0){
	                    return ['status'=>0,'msg'=>'操作失败,商家余额不足'];
	                }
	            }
	            \app\commons\Business::addmoney($aid,$order['bid'],$totalmoney,'货款，订单号：'.$order['ordernum'],true,'restaurant_booking',$order['ordernum'],['platformMoney'=>$platformMoney,'business_lirun'=>$business_lirun]);
           	}else{
				//商家推荐分成
				if($totalmoney > 0){
					if(getcustom('business_agent')){
						\app\commons\Business::addparentcommission2($aid,$order['bid'],$totalmoney,$platformMoney,$business_lirun);
					}else{
						\app\commons\Business::addparentcommission($aid,$order['bid'],$totalmoney);
					}
				}
			}
            if(getcustom('business_score_jiesuan')){
                if($businessDkMoney>0){
                    \app\commons\Business::addmoney($aid,$order['bid'],$businessDkMoney,t('积分').'抵扣转'.t('余额').'，预订订单号：'.$order['ordernum'],false,'restaurant_booking',$order['ordernum']);
                }
                if($businessDkScore>0){
                    \app\commons\Business::addscore($aid,$order['bid'],$businessDkScore,t('积分').'抵扣到商户'.t('积分').'，预订订单号：'.$order['ordernum']);
                }
            }
        }

        Db::name('restaurant_booking_order')->where('aid',$aid)->where('bid',$order['bid'])->where('id',$orderid)->update(['status'=>3,'collect_time'=>time()]);
        Db::name('restaurant_booking_order_goods')->where('aid',$aid)->where('bid',$order['bid'])->where('orderid',$orderid)->update(['status'=>3,'endtime'=>time()]);
        return ['status'=>1,'msg'=>''];
    }
	//排队叫号
	public static function call_no($aid,$text){
		$set = Db::name('restaurant_admin_set')->where('aid',$aid)->find();
		
		$text = str_replace('0','零',$text);

		$reqArr = array ();
		$reqArr['Action'] = 'TextToStreamAudio';
		$reqArr['AppId'] = intval($set['queue_appid']);
		$reqArr['SecretId'] = $set['queue_secretid'];
		$reqArr['Timestamp'] = time();
		$reqArr['Expired'] = 3600 + time(); //表示为离线识别
		$reqArr['Text'] = $text;
		$reqArr['SessionId'] = self::guid();
		$reqArr['Codec'] = 'mp3';
		$reqArr['VoiceType'] = $set['queue_voicetype'] ? intval($set['queue_voicetype']) : 0;

		$serverUrl = "https://tts.cloud.tencent.com/stream";
		$autho = self::createSign($reqArr,"POST","tts.cloud.tencent.com", "/stream",$set['queue_secretkey']);
		$header = array ('Authorization: ' . $autho,'Content-Type: ' . 'application/json');
		
		$rs = curl_post($serverUrl,jsonEncode($reqArr),0,$header);
		return $rs;
	}
	public static function createSign($reqArr, $method, $domain, $path, $secretKey) {
		$signStr = "";
		$signStr .= $method;
		$signStr .= $domain;
		$signStr .= $path;
		$signStr .= "?";
		ksort($reqArr, SORT_STRING);

		foreach ($reqArr as $key => $val) {
			$signStr .= $key . "=" . $val . "&";
		}
		$signStr = substr($signStr, 0, -1);
		$signStr = base64_encode(hash_hmac('SHA1', $signStr, $secretKey, true));

		return $signStr;
	}
	public static function guid(){
		if (function_exists('com_create_guid')){
			return com_create_guid();
		}else{
			mt_srand((double)microtime()*10000);//optional for php 4.2.0 and up.
			$charid = strtoupper(md5(uniqid(rand(), true)));
			$hyphen = chr(45);// "-"
			$uuid = 
					substr($charid, 0, 8).$hyphen
					.substr($charid, 8, 4).$hyphen
					.substr($charid,12, 4).$hyphen
					.substr($charid,16, 4).$hyphen
					.substr($charid,20,12);
			return $uuid;
		}
	}
	//计划任务 每分钟执行一次
	public static function auto_perminute(){
		if(date('H')=='00' && cache('restaurant_cleardaysalesday')!=date('Ymd')){ //零点清商品每日库存
			cache('restaurant_cleardaysalesday',date('Ymd'));
			Db::name('restaurant_product')->where('sales_daily','<>',0)->update(['sales_daily'=>0]);
			Db::name('restaurant_product_guige')->where('sales_daily','<>',0)->update(['sales_daily'=>0]);
		}
		//自动关闭订单 释放库存
		$orderlist = Db::name('restaurant_takeaway_order')->where('status',0)->select()->toArray();
		$autocloseArr = [];
		foreach($orderlist as $order){
			if(!$autocloseArr[$order['aid']]){
				$autocloseArr[$order['aid']] = Db::name('restaurant_admin_set')->where('aid',$order['aid'])->value('takeaway_autoclose');
			}
			if($order['createtime'] + $autocloseArr[$order['aid']]*60 > time()) continue;
			$aid = $order['aid'];
			$mid = $order['mid'];
			$orderid = intval($order['id']);
			$order = Db::name('restaurant_takeaway_order')->where('id',$orderid)->find();
			if(!$order || $order['status']!=0){
				//return $this->json(['status'=>0,'msg'=>'关闭失败,订单状态错误']);
			}else{
				//加库存
				$oglist = Db::name('restaurant_shop_order_goods')->where('aid',$aid)->where('orderid',$orderid)->select()->toArray();
				foreach($oglist as $og){
					Db::name('restaurant_product_guige')->where('aid',$aid)->where('id',$og['ggid'])->update(['stock'=>Db::raw("stock+".$og['num']),'sales'=>Db::raw("sales-".$og['num']),'sales_daily'=>Db::raw("sales_daily-".$og['num'])]);
					Db::name('restaurant_product')->where('aid',$aid)->where('id',$og['proid'])->update(['stock'=>Db::raw("stock+".$og['num']),'sales'=>Db::raw("sales-".$og['num']),'sales_daily'=>Db::raw("sales_daily-".$og['num'])]);
				}
				//优惠券抵扣的返还
				if($order['coupon_rid'] > 0){
					Db::name('coupon_record')->where('aid',$aid)->where('mid',$mid)->where('id',$order['coupon_rid'])->update(['status'=>0,'usetime'=>'']);
				}
				$rs = Db::name('restaurant_takeaway_order')->where('id',$orderid)->where('aid',$aid)->where('mid',$mid)->update(['status'=>4]);
				Db::name('restaurant_takeaway_order_goods')->where('orderid',$orderid)->where('aid',$aid)->where('mid',$mid)->update(['status'=>4]);
			}
		}

		if(getcustom('restaurant_order_payafter_autoclose')){
            //餐后付款关闭时，未支付订单自动关闭时间并自动清台变为空闲
            self::closepayafter();
        }
        if(getcustom('restaurant_shop_pindan')) {
            self::tableAutoPayexpire();
        }
        if(getcustom('restaurant_take_food')){
            self::takeFoodExpire();
        }
	}
    //计划任务 每小时执行一次
	public static function auto_perhour()
    {
        $time = time();
        //外卖自动收货
        $setlist = Db::name('restaurant_takeaway_sysset')->select()->toArray();
        foreach($setlist as $sysset){
            $aid = $sysset['aid'];
            if($aid){
                $list = Db::name('restaurant_takeaway_order')->where("aid={$aid} and status=2 and ".time().">`send_time` + 86400*".$sysset['autoshdays'])->select()->toArray();
                foreach($list as $order){
                    $rs = self::takeaway_orderconfirm($order['id']);
//                    if($rs['status'] == 0) continue;
//                    Db::name('restaurant_takeaway_order')->where('id',$order['id'])->update(['status'=>3,'collect_time'=>time()]);
                }
            }
        }
        $shopsetlist = Db::name('restaurant_shop_sysset')->select()->toArray();
        foreach($shopsetlist as $sysset){
            $aid = $sysset['aid'];
            if($aid){
                $list = Db::name('restaurant_shop_order')->where("aid={$aid} and status=1 and refund_status = 0 and ".time().">`paytime` + 86400*".$sysset['autoshdays'])->select()->toArray();
                foreach($list as $order){
                    self::shop_orderconfirm($order['id']);
                }
            }
        }
        //每天4点
        if(date('G') == 4) {
            self::auto_perday();
        }
    }

    public static function auto_perday()
    {
        //清0每日销量
        Db::name('restaurant_product')->update(['sales_daily' => 0]);
        Db::name('restaurant_product_guige')->update(['sales_daily' => 0]);
    }
    public static function tableAutoPayexpire(){
        if(getcustom('restaurant_shop_pindan')){
            //查询账户
            $syssetlist = Db::name('admin_set')->where('1=1')->select()->toArray();
            if($syssetlist) {
                foreach ($syssetlist as $sysset) {
                    $aid = $sysset['aid'];
                    $restaurant_shop_set =  Db::name('restaurant_shop_sysset')
                        ->where('aid',$aid)
                        ->where('status',1)
                        ->field('id,bid,table_payafter_autoclosetime')
                        ->select()->toArray();
                    foreach($restaurant_shop_set as $rval){
                        $bid = $rval['bid'];
                        $endtime = time()- $rval['table_payafter_autoclosetime']*60;
                        $orderlist = Db::name('restaurant_shop_order')->alias('so')
                            ->join('ddwx_restaurant_table rt','rt.id = so.tableid')
                            ->where('so.aid',$aid)
                            ->where('so.bid',$bid)
                            ->where('so.status',0)
                            ->where('so.createtime','<=',$endtime)
                            ->where('rt.pindan_status',0)
                            ->field('so.*,rt.name,pindan_status')
                            ->select()->toArray();
                        //吧台下单的
                        $notable_orderlist  =  Db::name('restaurant_shop_order')
                            ->where('aid',$aid)
                            ->where('bid',$bid)
                            ->where('status',0)
                            ->where('tableid',0)
                            ->where('createtime','<=',$endtime)
                            ->select()->toArray();
                        $orderlist = array_merge($orderlist,$notable_orderlist);
                        
                        if($orderlist){
                            foreach($orderlist as $order){
                                //关闭订单
                                $uporder = Db::name('restaurant_shop_order')->where('id',$order['id'])->where('status',0)->update(['status'=>4]);
                                //加库存
                                $oglist = Db::name('restaurant_shop_order_goods')->where('orderid',$order['id'])->where('aid',$aid)->select()->toArray();
                                if($oglist){
                                    foreach($oglist as $og){
                                        Db::name('restaurant_product_guige')->where('aid',$aid)->where('id',$og['ggid'])->update(['stock'=>Db::raw("stock+".$og['num']),'sales'=>Db::raw("sales-".$og['num'])]);
                                        Db::name('restaurant_product')->where('aid',$aid)->where('id',$og['proid'])->update(['stock'=>Db::raw("stock+".$og['num']),'sales'=>Db::raw("sales-".$og['num'])]);
                                    }
                                }
                                //优惠券抵扣的返还
                                if($order['coupon_rid'] > 0){
                                    Db::name('coupon_record')->where('aid',$aid)->where('id',$order['coupon_rid'])->update(['status'=>0,'usetime'=>'']);
                                }
                                Db::name('restaurant_shop_order_goods')->where('orderid',$order['id'])->where('aid',$aid)->update(['status'=>4]);  
                                if($order['tableid'] > 0){
                                    Db::name('restaurant_table')->where('id',$order['tableid'])->where('aid',$aid)->where('orderid',$order['id'])->where('bid',$bid)->update(['status' => 0, 'orderid' => 0]);
                                }
                               
                            }
                        }
                    }
                }
            }
        }
    }
    //取餐过期 24小时
    public static function takeFoodExpire(){
        if(getcustom('restaurant_take_food')){
            $syssetlist = Db::name('admin_set')->where('1=1')->select()->toArray();
            if($syssetlist) {
                foreach ($syssetlist as $sysset) {
                    $aid = $sysset['aid'];
                    $expiretime = time() - 3600*24;
                    Db::name('restaurant_take_food')
                        ->where('aid',$aid)
                        ->where('create_time','<',$expiretime)
                        ->where('status','0')
                        ->update(['status' => -1]);
                }
            }
        }
    }
    //打印小票 aid 模块 订单号
    /**
     * @param $title
     * @param $machineType 0易联云，1飞蛾
     * @param array $area  print_template_type 0普通打印，1一菜一单;id
     * @param null $orderType
     * @param array $orderInfo
     * @param array $orderGoods
     * @return string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function restaurantPrintContent( $title, $machineType, $area = [], $orderType = null, $orderInfo = [], $orderGoods = [],$machine=[]){
        if($orderType != 'test') {
            $order = $orderInfo;
            if(!$order['bid']) $order['bid'] = 0;
            $shop_set = Db::name('restaurant_shop_sysset')->where('aid',$order['aid'])->where('bid',$order['bid'])->find();
            if(getcustom('sys_print_set')){
	            $print_day_ordernum = $orderInfo['print_day_ordernum'];
	        }
        }else{
        	if(getcustom('sys_print_set')){
	            $print_day_ordernum = 1;
	        }
        }

        $templateType = $area['print_template_type'];
        $tabletext = '桌号';
        if(getcustom('restaurant_table_name')){
        	$tabletext = $shop_set['table_text']?$shop_set['table_text']:'桌号';
        }
        $printnum = 0;
        $paytime = $order['paytime'] ? date('Y-m-d H:i:s',$order['paytime']) : '未付款';
        if($machineType==0){
            //易联云
            if($templateType == 0) {
                //普通打印
                $content = '';
                if(getcustom('sys_print_set')){
                    if($print_day_ordernum && $machine['day_ordernum']){
                        $content .=  '<FB><center>#'.$print_day_ordernum."</center></FB>\r\r";
                    }
                }
                $content .= "<FS><center>** ".$title." **</center></FS>\r\r";
                if($order['tableName'])$content .= "<FS>".$tabletext."：".$order['tableName']."</FS>\r";
                if(getcustom('restaurant_take_food')){
                    if($order['pickup_number'])$content .= "<FS>取餐号：".$order['pickup_number']."</FS>\r";
                }
                $content .= "订单编号：".$order['ordernum']."\r";
                if($orderType == 'restaurant_takeaway') {
                    $content .= "配送方式：".$order['freight_text']."\r";
                    if($order['freight_time']){
                        $content .= "配送时间：<FS>".$order['freight_time']."</FS>\r";
                    }
                    $content .= "收货人:<FS>".$order['linkman']."</FS>\r";
                    $content .= "联系电话:<FS>".$order['tel']."</FS>\r";
                    $content .= "收货地址:<FS>".$order['area']." ".$order['address']."</FS>\r";
                }

                $content .= "下单时间：".date('Y-m-d H:i:s',$order['createtime'])."\r";
                $content .= "付款时间：".$paytime."\r";
                $content .= "付款方式：".$order['paytype']."\r\r";
                if($orderType == 'restaurant_booking'){
                    $content .="--------------------------------\r";
                    //预定：小票显示预定内容
                    $content .= "客户姓名：".$order['linkman']."\r";
                    $content .= "联系电话：".$order['tel']."\r";
                    $content .= "预约时间：".$order['booking_time']."\r";
                    $content .= "预约人数：".$order['seat']."人\r\r";
                }else{
                    $content .= "<table>";
                    $content .= "<tr><td>商品名称</td><td>数量</td><td>总价</td></tr>";
                    foreach($orderGoods as $item){
                        if(getcustom('product_jialiao')){
                            $item['ggname']  = $item['jltitle']?$item['ggname'].$item['jltitle']:$item['ggname'];
                        }
                        if(getcustom('restaurant_product_jialiao')){
                            if($item['njltitle']){
                                $item['ggname'] = $item['ggname'].' '.$item['njltitle'];
                            }
                        }
                        $content .= "<tr><td><FB>".$item['name'].'('.$item['ggname'].')'."</FB></td><td>".floatval($item['num'])."</td><td>".$item['totalprice']."</td></tr>";
                    }
                    $content .= "</table>";
                }
                $content .= "\r";
                if($order['message']){
                    $content .= "备注：<FS>".$order['message']."</FS>\r\r";
                }else{
                    $content .= "备注：无\r";
                }
                $content .="--------------------------------\r";
                if($order['tea_fee']) {
                    $content .= "<LR>".$shop_set['tea_fee_text']."：,+".dd_money_format($order['tea_fee'])."</LR>";
                }
                if($order['scoredk_money'] > 0) {
                    $content .= "<LR>积分抵扣：,-".$order['scoredk_money']."</LR>";
                }
                if($order['leveldk_money'] > 0) {
                    $content .= "<LR>会员等级优惠：,-".dd_money_format($order['leveldk_money'])."</LR>";
                }
                if($order['coupon_money'] > 0) {
                    $content .= "<LR>优惠券：,-".dd_money_format($order['coupon_money'])."</LR>";
                }
                if($order['discount_money'] > 0) {
                    $content .= "<LR>优惠：,-".dd_money_format($order['discount_money'])."</LR>";
                }
                if($order['pack_fee'] > 0) {
                    $content .= "<LR>打包费：,+".dd_money_format($order['pack_fee'])."</LR>";
                }
                if($order['moling_money'] > 0) {
                    $content .= "<LR>抹零：,+".dd_money_format($order['moling_money'])."</LR>";
                }
                $money_text = '实付';
                if($order['status'] ==0)$money_text = '应付';
                $content .= "<LR>".$money_text."金额：,".dd_money_format($order['totalprice'])."</LR>";

                if(getcustom('sys_print_set')){
                    //易联云小票底部自定义
                    $boot_custom =  $machine['boot_custom']?$machine['boot_custom']:0;
                    if($boot_custom){
		                $boot_custom_content = $machine['boot_custom_content'];
			            if($boot_custom_content){
			                if(strpos($boot_custom_content,'<br>')!==false){
			                    $boot_custom_content = str_replace("<br>","\r",$boot_custom_content);
			                }
			                if($boot_custom_content){
                                $content .= "\r\r".$boot_custom_content;
                            }
			            }
                    }
                }
                $content .= "\r\r";
            }elseif ($templateType == 1) {
                //一菜一单
                if($orderType == 'test') {
                    $time = date('Y-m-d H:i:s',time());
                    $arr = array('酸菜鱼','可乐鸡翅');
                    $content = '';
                    $num = count($arr);
                    foreach ($arr as $key => $value) {
                        $content .= "<FS2><center>**".$tabletext."：01**</center></FS2>\r\r";
                        $content .= "<FH2><center>".$value."  * 1</center></FH2>\r";
                        $content .= "@@2备注：不要辣，少盐\r";
                        $content .= "时间：".$time."\r";
                       // $end = array_keys($arr);
                       // if(end($end)==$key){
                       //     break;
                       // }else{
                        	if(getcustom('sys_print_set')){
			                    //易联云小票底部自定义
			                    $boot_custom =  $machine['boot_custom']?$machine['boot_custom']:0;
			                    if($boot_custom){
					                $boot_custom_content = $machine['boot_custom_content'];
						            if($boot_custom_content){
						                if(strpos($boot_custom_content,'<br>')!==false){
						                    $boot_custom_content = str_replace("<br>","\r",$boot_custom_content);
						                }
						                if($boot_custom_content){
			                                $content .= "\r\r".$boot_custom_content;
			                            }
						            }
			                    }
			                }
                            $content .= "\r\r\r\r<MK2></MK2>\r";//控制切纸
                       // }
                    }
                } else {
                    $content = '';
                    foreach ($orderGoods as $key => $item) {
                        if($item['area_id'] != $area['id']) continue;//不在区域范围内不打印
                        if(getcustom('product_jialiao')){
                            $item['ggname']  = $item['jltitle']?$item['ggname'].$item['jltitle']:$item['ggname'];
                        }
                        if(getcustom('restaurant_product_jialiao')){
                            if($item['njltitle']){
                                $item['ggname'] = $item['ggname'].' '.$item['njltitle'];
                            }
                        }
                        $content .= "<FS2><center>**".$tabletext."：".$order['tableName']."**</center></FS2>\r\r";
                        $content .= "<FH2><center>".$item['name']."(".$item['ggname'].")" ."  * ".floatval($item['num'])."</center></FH2>\r";
                        if($order['message'])$content .= "@@2备注：".$order['message']."\r";
                        $content .= "时间：".date('Y-m-d H:i:s',$order['createtime'])."\r";

                        if(getcustom('sys_print_set')){
		                    //易联云小票底部自定义
		                    $boot_custom =  $machine['boot_custom']?$machine['boot_custom']:0;
		                    if($boot_custom){
				                $boot_custom_content = $machine['boot_custom_content'];
					            if($boot_custom_content){
					                if(strpos($boot_custom_content,'<br>')!==false){
					                    $boot_custom_content = str_replace("<br>","\r",$boot_custom_content);
					                }
					                if($boot_custom_content){
		                                $content .= "\r\r".$boot_custom_content;
		                            }
					            }
		                    }
		                }

                        $content .= "\r\r\r\r<MK2></MK2>\r";//控制切纸
                    }
                }
            }

        }elseif($machineType==1){
            //飞蛾
            if($templateType == 0) {
                //普通打印
                if($orderType == 'test') {
                    $arr = array('酸菜鱼','可乐鸡翅' );
                    $content = '';
                    if(getcustom('sys_print_set')){
                        if($print_day_ordernum && $machine['day_ordernum']){
                            $content .=  '<CB>#'.$print_day_ordernum."</CB><BR><BR>";
                        }
                    }
                    $content .= "<CB>** ".$title." **</CB><BR><BR>";
                    $content .= '<CB>'.$tabletext.'：01</CB><BR>';
                    $content .= "订单编号：".date('Y-m-d H:i:s')."<BR>";
                    $content .= "配送方式：".$order['freight_text']."<BR>";
                    $content .= "配送时间：<B>".$order['freight_time']."</B><BR>";
                    $content .= "收货人:<B>".$order['linkman']."</B><BR>";
                    $content .= "联系电话:<B>".$order['tel']."</B><BR>";
                    $content .= "收货地址:<B>".$order['area']." ".$order['address']."</B><BR>";

                    $content .= "下单时间：".date('Y-m-d H:i:s')."<BR>";
                    $content .= "付款时间：".date('Y-m-d H:i:s')."<BR>";
                    $content .= "付款方式：".$order['paytype']."<BR><BR>";
                    $content .= "商品名称     数量     总价<BR>";

                    foreach ($arr as $key => $item) {
                        $content .= "<BOLD>".$item."</BOLD>   1  33.6<BR>";
                    }
                    $content .= "<BR>";
                    $content .= "备注：不要辣<BR>";
                    $content .= "<RIGHT>实付金额：￥108.00</RIGHT>";

                    if(getcustom('sys_print_set')){
                        //飞蛾小票底部自定义
                        $boot_custom =  $machine['boot_custom']?$machine['boot_custom']:0;
                        if($boot_custom){
                            $boot_custom_content = $machine['boot_custom_content'];
                            if($boot_custom_content){
                                $content .= "<BR><BR>".$boot_custom_content;
                            }
                        }
                    }

                    $content .= "<BR><BR><BR><BR><BR><CUT>";
                } else {
                    $content = '';
                    $tmpltype = 0;
                    if(getcustom('restaurant_wifiprint_tmpl_custom')){
                        $tmpltype =  $machine['restaurant_tmpltype'];
                    }
                    //如果是加菜 强制使用 默认模板
                    $isaddproduct = $order['isaddproduct']??0;
                    
                    $print_type = $order['print_type']?$order['print_type']:0;//打印类型0 默认 1：预结单  2：退款或取消
                    if($tmpltype ==0) {
                        $content = '';
                        if (getcustom('sys_print_set')) {
                            if ($print_day_ordernum && $machine['day_ordernum'] && $isaddproduct==0 ) {
                                $content .= '<CB>#' . $print_day_ordernum . "</CB><BR>";
                            }
                        }
                        //非加菜，且打印类型是默认，使用打印机标题
                        if($isaddproduct==0 && $print_type ==0)$content .= "<CB>" . $title . "</CB>";
                        //1：预结单，如果是预结单类型，使用预结单标题
                        if($print_type ==1)$content .= '<CB>预结单</CB><BR>';
                        //退款类型，使用退款类型
                        if($print_type ==2)$content .= '<CB>退款小票</CB><BR>';
                        //桌台信息
                        if ($order['tableName']) $content .= '<CB>' . $tabletext . '：' . $order['tableName'] . '</CB>';
                        if(getcustom('extend_qrcode_variable_fenzhang')){
                            if($order['qrcode_val_code']){
                                $qrcode_list_variable=Db::name('qrcode_list_variable')->alias('qlv')
                                    ->join('qrcode_variable qv','qv.id = qlv.qid')
                                    ->where('qlv.aid',$order['aid'])->where('qlv.code',$order['qrcode_val_code'])->field('qlv.name,qv.name as qrname')->find();
                                $qrcode_variable_name =  $qrcode_list_variable['qrname'];
                                $qrcode_list_variable_name = $qrcode_list_variable['name'];
                                if($qrcode_list_variable_name || $qrcode_variable_name){
                                    $content .= "<CB>" . $qrcode_variable_name.$qrcode_list_variable_name . "</CB>";
                                }
                            }
                        }
                        if (getcustom('restaurant_take_food')) {
                            if ($order['pickup_number'] ) $content .= '<CB>取餐号：' . $order['pickup_number'] . '</CB><BR>';
                        }
                        $content .= "订单编号：" . $order['ordernum'] . "<BR>";
                        if ($orderType == 'restaurant_takeaway') {
                            $content .= "配送方式：" . $order['freight_text'] . "<BR>";
                            if ($order['freight_time']) {
                                $content .= "配送时间：<B>" . $order['freight_time'] . "</B><BR>";
                            }
                            $content .= "收货人:<B>" . $order['linkman'] . "</B><BR>";
                            $content .= "联系电话:<B>" . $order['tel'] . "</B><BR>";
                            $content .= "收货地址:<B>" . $order['area'] . " " . $order['address'] . "</B><BR>";
                        }
                        $content .= "下单时间：" . date('Y-m-d H:i:s', $order['createtime']) . "<BR>";
                        $content .= "付款时间：" . $paytime . "<BR>";
                        $content .= "付款方式：" . $order['paytype'] . "<BR>";
                        if($orderType == 'restaurant_booking'){
                            //预定：小票显示预定内容
                            $content .='<BR><C>-------------------------------</C>';
                            $content .= "客户姓名：" . $order['linkman']. "<BR>";
                            $content .= "联系电话：" . $order['tel'] . "<BR>";
                            $content .= "预约时间：" . $order['booking_time'] . "<BR>";
                            $content .= "预约人数：" . $order['seat'] . "人<BR><BR>";
                        }else{
                            //是否打印产品列表
                            $is_print_ordergoods = true; //是否打印商品列表
                            if(getcustom('restaurant_cashdesk_auth_enter')){
                                //如果没有选中的商品，全部商品退款或者取消，不打印
                                if(!$order['ogids'] && $print_type ==2) $is_print_ordergoods = false;
                            }
                            if($is_print_ordergoods){
                                $content .='<C>-------------------------------</C><BR>';
                                $content .= "商品名称   单价  数量    小计<BR>";
                                $ogtotal = 0;
                                foreach ($orderGoods as $item) {
                                    if (getcustom('product_jialiao')) {
                                        $item['ggname'] = $item['jltitle'] ? $item['ggname'] . $item['jltitle'] : $item['ggname'];
                                    }
                                    if (getcustom('restaurant_product_jialiao')) {
                                        if ($item['njltitle']) {
                                            $item['ggname'] = $item['ggname'] . ' ' . $item['njltitle'];
                                        }
                                        $item['sell_price'] =  dd_money_format($item['sell_price']+$item['njlprice']);
                                    }
                                    $ogtotal +=  $item['sell_price'] * $item['num'];
                                    $content .= '<BOLD>'.$item['name'].'</BOLD><BR>';
                                    if(getcustom('restaurant_cashdek_ordergoods_remark')){
                                        if($item['remark']) {
                                            $og_remark = $item['remark'];
                                            $content .= '<BOLD>'.$og_remark.'</BOLD><BR>';
                                        }
                                    }
                                    if(getcustom('restaurant_product_package')){
                                        if($item['package_data']){
                                            $package_data = json_decode($item['package_data'],true);
                                            foreach($package_data as $pdk=>$pd){
                                                if($pd['ggname'] !='默认规格'){
                                                    $content.='—-'.$pd['proname'].'<BR>';
                                                    $content .='  -'.$pd['ggname'].' * '.floatval($pd['num']).'<BR>';
                                                } else{
                                                    $content.='—-'.$pd['proname'].' * '.floatval($pd['num']).'<BR>';
                                                }
                                            }
                                        }
                                    }
                                    $content .=$item['ggname'].'<BR>';
                                    $xj = dd_money_format($item['sell_price'] *$item['num']);
                                    $content .='          '.$item['sell_price'].'   '.floatval($item['num']).'    '.$xj.'<BR>';
                                    //                            $content .='<RIGHT>********************</RIGHT>';
                                    //                            $content .= "<BOLD>" . $item['name'] . "(" . $item['ggname'] . ")</BOLD>   " . floatval($item['num']) . "  " . $item['totalprice'] . "<BR>";
                                }
                                $content .='<C>-------------------------------</C><BR>';
                            }
                        }
                        if($isaddproduct ==1) $content .='<RIGHT>加菜总计:￥'.dd_money_format($ogtotal).'</RIGHT>';
                        $money_text = '实付';
                        if($order['status'] ==0)$money_text = '应付';
                        if($isaddproduct ==1)$content .= "<RIGHT>".$money_text."金额：￥" . $order['totalprice'] . "</RIGHT>";
                        if($isaddproduct ==0){
                            //打印类型 0：默认 1：预结单  2：退款或取消
                            if($print_type ==2){
                                $content .='<C>-------------------------------</C><BR>';
                                $content .= "<RIGHT>退款金额：￥" . $order['refund_money'] . "</RIGHT>";
                            } else{
                                if ($order['message'] || $order['remark']) {
                                    $message =  $order['message']?$order['message']:$order['remark'];
                                    $content .= "备注：<B>" . $message . "</B><BR><BR>";
                                } else {
                                    $content .= "备注：无<BR>";
                                }
                                if ($order['tea_fee']) {
                                    $content .= "<RIGHT>" . $shop_set['tea_fee_text'] . "：￥" . $order['tea_fee'] . "</RIGHT><BR>";
                                }
    
                                if ($order['scoredk_money'] > 0) {
                                    $content .= "<RIGHT>积分抵扣：-￥" . $order['scoredk_money'] . "</RIGHT><BR>";
                                }
                                if ($order['leveldk_money'] > 0) {
                                    $content .= "<RIGHT>会员等级优惠：-￥" . $order['leveldk_money'] . "</RIGHT><BR>";
                                }
                                if ($order['coupon_money'] > 0) {
                                    $content .= "<RIGHT>优惠券：-￥" . $order['coupon_money'] . "</RIGHT><BR>";
                                }
                                if ($order['discount_money'] > 0) {
                                    $content .= "<RIGHT>优惠：-￥" . $order['discount_money'] . "</RIGHT><BR>";
                                }
                                //预定
                                if($orderType == 'restaurant_booking'){
                                    $content .='<C>-------------------------------</C><BR>';
                                }
                                $content .= "<RIGHT>".$money_text."金额：￥" . $order['totalprice'] . "</RIGHT>";
                            }
                        }
                        if(getcustom('restaurant_take_food')){
                            if($order['pickup_number'] && $print_type!=2){
                                $content .='<C>-------------------------------</C>';
                                $content .='<C>出餐码（非员工勿扫）</C>';
                                $qrcodeurl=  'type=outfood&id='.$order['id'].'&co=' . $order['pickup_number'];
                                $qrcodeurl = base64_encode($qrcodeurl);
                                $content .=" <BR>";
                                $content .="<QR>".$qrcodeurl."</QR>";
                            }
                        }
                    }
                    else{
                        if(getcustom('restaurant_wifiprint_tmpl_custom')){
                            if($order['bid'] > 0){
                                $bname = Db::name('business')->where('id',$order['bid'])->value('name');
                            }else{
                                $bname = Db::name('admin_set')->where('aid',$order['aid'])->value('name');
                            }
                             //详细模板
                            $content = '';
                            if (getcustom('sys_print_set')) {
                                if ($print_day_ordernum && $machine['day_ordernum'] && $isaddproduct==0 ) {
                                    $content .= '<CB>#' . $print_day_ordernum . "</CB><BR>";
                                }
                            }
                            if($isaddproduct ==0 && $print_type ==0)$content .= '<C>'.$bname.'</C><BR>';
                            //1：预结单，如果是预结单类型，使用预结单标题
                            if($print_type ==1)  $content .= '<CB>预结单</CB><BR>';
                            //退款类型，使用退款类型
                            if($print_type ==2)$content .= '<CB>退款小票</CB><BR>';
                            
                            if ($order['tableName']){
                                $content .= '<CB>'.$tabletext.'：'.$order['tableName'].'</CB>';
                            }
                            if(getcustom('extend_qrcode_variable_fenzhang')){
                                if($order['qrcode_val_code']){
                                    $qrcode_list_variable=Db::name('qrcode_list_variable')->alias('qlv')
                                        ->join('qrcode_variable qv','qv.id = qlv.qid')
                                        ->where('qlv.aid',$order['aid'])->where('qlv.code',$order['qrcode_val_code'])->field('qlv.name,qv.name as qrname')->find();
                                    $qrcode_variable_name =  $qrcode_list_variable['qrname'];
                                    $qrcode_list_variable_name = $qrcode_list_variable['name'];
                                    if($qrcode_list_variable_name || $qrcode_variable_name){
                                        $content .= "<CB>" . $qrcode_variable_name.$qrcode_list_variable_name . "</CB>";
                                    }
                                }
                            }
                            if (getcustom('restaurant_take_food')) {
                                if ($order['pickup_number'] ) $content .= '<CB>取餐号:'.$order['pickup_number'].'</CB><BR>';
                            }
                            $content .='订单号:'.$order['ordernum'].'<BR>';
                            if($print_type ==0)$content .='人数:'.$order['renshu'].'<BR>';
                            //$admin_user_name = '管理员';
                            //if($order['uid'] > 0){
//                                $admin_user_name = Db::name('admin_user')->where('id',$order['uid'])->value('un');
                                
                            //}
                            if($order['uid'] > 0)$content .='收银员:'.$order['uid'].'<BR>';
                            if($order['mid']){
                                $member = Db::name('member')->where('aid',$order['aid'])->where('id',$order['mid'])->field('id,levelid')->find();
                                $levelname = Db::name('member_level')->where('id',$member['levelid'])->value('name');
                                $content .='会员:'.$order['mid'].'<BR>';
                                $content .='会员等级:'.$levelname.'<BR> ';
                            }
                            $content .='<C>-------------------------------</C><BR>';
                            $remark = $order['message']?$order['message']:$order['remark'];
                            if($print_type ==0)$content .='<B>备注: '.$remark.'</B><BR>';
                            if($order['linkman'])$content .='收货人:'.$order['linkman'].'<BR>';
                            if($order['tel'])$content .='联系电话:'.$order['tel'].'<BR>';
                            if($order['freight_text'])$content .='<B>配送地址:'.$order['freight_text'].'</B><BR>';
                            $is_print_ordergoods = true;//是否打印商品列表
                            if(getcustom('restaurant_cashdesk_auth_enter')){
                                //如果没有选中的商品，全部商品退款或者取消，不打印
                                if(!$order['ogids'] && $print_type ==2) $is_print_ordergoods = false;
                            }
                            if($is_print_ordergoods) {
                                $content .= '<C>-------------------------------</C><BR>';
                                $content .= '商品        单价   数量    小计<BR>';
                                $content .= '<RIGHT>****************</RIGHT>';
                                $totalnum = 0;
                                foreach ($orderGoods as $item) {
                                    $ggname = $item['ggname'];
                                    $sell_price = $item['sell_price'];
                                    if (getcustom('restaurant_product_jialiao')) {
                                        $sell_price = dd_money_format($item['sell_price'] + $item['njlprice']);
                                        if ($item['njltitle']) {
                                            $ggname .= '(' . $item['njltitle'] . ')';
                                        }
                                    }

                                    $content .= '<BOLD>' . $item['name'] . '</BOLD><BR>';
                                    if(getcustom('restaurant_cashdek_ordergoods_remark')){
                                        if($item['remark']){
                                            $og_remark = $item['remark'];
                                            $content .= '<BOLD>' .$og_remark.'</BOLD><BR>';
                                        }
                                    }
                                    if (getcustom('restaurant_product_package')) {
                                        if ($item['package_data']) {
                                            $package_data = json_decode($item['package_data'], true);
                                            foreach ($package_data as $pdk => $pd) {
                                                if ($pd['ggname'] != '默认规格') {
                                                    $content .= '—-' . $pd['proname'] . '<BR>';
                                                    $content .= '  -' . $pd['ggname'] . ' * ' . floatval($pd['num']) . '<BR>';
                                                } else {
                                                    $content .= '—-' . $pd['proname'] . ' * ' . floatval($pd['num']) . '<BR>';
                                                }
                                            }
                                        }
                                    }
                                    $content .= $ggname . '<BR>';
                                    $xj = dd_money_format($sell_price * $item['num']);
                                    $content .= '          ' . $sell_price . '    ' . floatval($item['num']) . '   ' . $xj . '<BR>';
                                    $content .= '<RIGHT>****************</RIGHT>';
                                    $totalnum += $item['num'];
                                }
                                $content .= '商品数量小计:' . $totalnum . '<BR>';
                                $content .= '<C>--------------------------------</C>';
                            }
                            //是否是加菜 0不是
                            $content .='下单时间:'.date('Y-m-d H:i:s',$order['createtime']).'<BR>';
                            if($isaddproduct ==0){
                                //打印类型 0：默认 1：预结单  2：退款或取消
                                if($print_type ==2){
                                    $content .='<CB>退款金额:'.$order['refund_money'].'元</CB><BR>';
                                }else{
                                    if($order['paytime']){
                                        $paytime = $order['paytime']?date('Y-m-d H:i:s',$order['paytime']):'';
                                        $content .='付款时间:'.$paytime.'<BR>';
                                    }
                                    if($order['timing_money'] >0)$content .='计时费:'.$order['timing_money'].'<BR>';
                                    if($order['service_money'] > 0)$content .='服务费:'.dd_money_format($order['service_money']).'<BR>';
                                    if($order['tea_fee'] >0)$content .=$shop_set['tea_fee_text'].':'.$order['tea_fee'].'<BR>';
                                    $product_price = $order['product_price'];
                                    if($order['timing_money'] >0 ){
                                        $product_price =  dd_money_format($product_price + $order['timing_money']);
                                    }
                                    if($order['service_money'] >0 ){
                                        $product_price =  dd_money_format($product_price + $order['service_money']);
                                    }
                                    if($order['tea_fee'] >0){
                                        $product_price =  dd_money_format($product_price + $order['tea_fee']);
                                    }
                                    if($order['product_price'] >0) $content .='总计(优惠前总金额):'.$product_price.'<BR>';
                                    if($order['cuxiao_money'] >0)$content .='活动优惠: -'.dd_money_format($order['cuxiao_money']).'<BR>';
                                    if($order['coupon_money'] >0)$content .=t('优惠券').'优惠:-'.$order['coupon_money'].'<BR>';
                                    if($order['leveldk_money'] >0)$content .='会员折扣:-'.$order['leveldk_money'].'<BR>';
                                    if($order['direct_money'] >0)$content .='其他优惠:-'.$order['direct_money'].'<BR>';
                                    if($order['moling_money'] >0)$content .='抹零:-'.$order['moling_money'].'<BR>';
                                    $content .='<CB>应付:'.$order['totalprice'].'元</CB><BR>';
                                    $content .='<C>--------------------------------</C>';

                                    $content .=$order['paytype']?'付款方式:'.$order['paytype'].'<BR>':'未支付<BR>';
                                    if($order['paynum'])$content .='支付单号:'.$order['paynum'].'<BR>';

                                    if($order['mid']){
                                        $member = Db::name('member')->where('aid',$order['aid'])->where('id',$order['mid'])->field('id,money')->find();
                                        $content .='平台会员余额:'.$member['money'].'<BR>';
                                        $content .='<C>--------------------------------</C>';

                                    }
                                }
                            }
                            if(getcustom('restaurant_take_food')){
                                if($order['pickup_number'] && $print_type !=2){
                                    $content .='<C>出餐码（非员工勿扫）</C>';
                                    $qrcodeurl=  'type=outfood&id='.$order['id'].'&co=' . $order['pickup_number'];
                                    $qrcodeurl = base64_encode($qrcodeurl);
                                    $content .=" <BR>";
                                    $content .="<QR>".$qrcodeurl."</QR>";
                                }
                            }
                        }
                    }
                    if (getcustom('sys_print_set') &&  $print_type ==0) {
                        //飞蛾小票底部自定义
                        $boot_custom = $machine['boot_custom'] ? $machine['boot_custom'] : 0;
                        if ($boot_custom) {
                            $boot_custom_content = $machine['boot_custom_content'];
                            if ($boot_custom_content) {
                                $content .= "<BR><BR>" . $boot_custom_content;
                            }
                        }
                    }
                    $content = str_replace(["\r","\n"],'',$content);
                }
            } elseif ($templateType == 1) {
                //一菜一单
                if($orderType == 'test') {
                    $time = date('Y-m-d H:i:s',time());
                    $arr = array('酸菜鱼','可乐鸡翅' );
                    $content = '';
                    $num = count($arr);
                    foreach ($arr as $key => $value) {
                        $content .= '<CB>** '.$tabletext.'：01 **</CB><BR>';
                        $content .= '<C><L>'.$value.'  * 1</L></C><BR>';
                        $content .= '<L>备注：不要辣，少盐</L><BR>';
                        $content .= '时间：'.$time.'<BR>';
                        $end = array_keys($arr);
                       // if(end($end)==$key){
                       //     break;
                       // }else{
                        	if(getcustom('sys_print_set')){
		                        //飞蛾小票底部自定义
		                        $boot_custom =  $machine['boot_custom']?$machine['boot_custom']:0;
		                        if($boot_custom){
		                            $boot_custom_content = $machine['boot_custom_content'];
		                            if($boot_custom_content){
		                                $content .= "<BR><BR>".$boot_custom_content;
		                            }
		                        }
		                    }
                            $content .= '<BR><BR><BR><CUT>';//控制切纸
                       // }
                    }
                    
                }else {
                    $content = '';
                    $tmpltype = 0;
                    if(getcustom('restaurant_wifiprint_tmpl_custom')){
                        $tmpltype =  $machine['restaurant_tmpltype'];
                    }
                    if(getcustom('restaurant_product_package')){
                        //把带套餐的 解析到外层数据中
                        $orderGoods_p = [];
                        $p = 0;
                        foreach($orderGoods as $pitem){
                            if($pitem['package_data']){
                                $package_data = json_decode($pitem['package_data'],true);
                                foreach($package_data as $pdk=>$pd){
                                    $areaid = Db::name('restaurant_product')->where('aid',$pitem['aid'])->where('id',$pd['proid'])->value('area_id');
                                    $pd['name'] = $pd['proname'];
                                    $pd['area_id'] =$areaid; 
                                    if($pitem['remark'])$pd['remark'] =$pitem['remark']; 
                                    $orderGoods_p[$p] =$pd; 
                                    $p ++;
                                }
                            }else{
                                $orderGoods_p[$p] = $pitem;  
                                $p ++;
                            }
                        }
                        $orderGoods = $orderGoods_p;
                    }
                    $allorderGoods =[];
                    $k = 0;
                    foreach ($orderGoods as $key => $item) {
                        if(!$item['area_id']){
                            $areaid = Db::name('restaurant_product')->where('aid',$item['aid'])->where('id',$item['proid'])->value('area_id');
                            $item['area_id'] =  $areaid;
                        }
                        for ($i = 0; $i < $item['num']; $i++) {
                            $k += 1;
                            $item['no'] = $k;
                            $allorderGoods[] = $item;
                        }
                    }
                    if($tmpltype ==0) {//默认模板
                     
                        foreach ($allorderGoods as $key => $item) {
                           
                            if ($item['area_id'] != $area['id']) continue;//不在区域范围内不打印
                            if (getcustom('sys_print_set')) {
                                if ($print_day_ordernum && $machine['day_ordernum']) {
                                    $content .= '<CB>#' . $print_day_ordernum . "</CB>";
                                }
                            }
                            if (getcustom('product_jialiao')) {
                                $item['ggname'] = $item['jltitle'] ? $item['ggname'] . $item['jltitle'] : $item['ggname'];
                            }
                            if (getcustom('restaurant_product_jialiao')) {
                                $item['ggname'] = $item['njltitle'] ? $item['ggname'] . $item['njltitle'] : $item['ggname'];
                            }
                            if(getcustom('restaurant_take_food')){
                                if($order['pickup_number']){
                                    $content .= '<L>取餐号：' . $order['pickup_number'] . '</L>   ';
                                }
                            }
                            $content .= '<CB>' . $tabletext . '：' . $order['tableName'] . '</CB>';
                            if($item['no']){
                                $content .= '<RIGHT>' . $item['no'].'/'.$k . '</RIGHT>   ';
                            }
                            $content .='<BR>';
                            $content .= '<C><L>' . $item['name'] . "(" . $item['ggname'] . ")" . '  * 1</L></C><BR>';
                            if ($order['message']) $content .= '<L>备注：' . $order['message'] . '</L><BR>';
                            $content .= '时间：' . date('Y-m-d H:i:s', $order['createtime']) . '<BR>';

                            if (getcustom('sys_print_set')) {
                                //飞蛾小票底部自定义
                                $boot_custom = $machine['boot_custom'] ? $machine['boot_custom'] : 0;
                                if ($boot_custom) {
                                    $boot_custom_content = $machine['boot_custom_content'];
                                    if ($boot_custom_content) {
                                        $content .= "<BR><BR>" . $boot_custom_content;
                                    }
                                }
                            }
                            $content .= '<BR><BR><BR><CUT>';//控制切纸 
                        }
                    }
                    else{ //自定义
                        if (getcustom('restaurant_wifiprint_tmpl_custom')) {
                            foreach ($allorderGoods as $key => $item) {
                                if ($item['area_id'] != $area['id']) continue;//不在区域范围内不打印
                                //退款打印时
                                if($order['print_type'] ==2){
                                    $content .= '<CB>[退]</CB><BR>';
                                }
                                if (getcustom('sys_print_set')) {
                                    if ($print_day_ordernum && $machine['day_ordernum']) {
                                        $content .= '#' . $print_day_ordernum ;
                                    }
                                }
                                if($item['no']){
                                    $content .= '                        ' . $item['no'].'/'.$k . '<BR>';
                                }
                                if(getcustom('restaurant_take_food')){
                                    if($order['pickup_number']){
                                        $content .= '<CB>取餐号' . $order['pickup_number'] . '</CB><BR>';
                                    }
                                }
                                if($order['tableName']){
                                    $content .= '<CB>' . $tabletext . '：' . $order['tableName'] . '</CB><BR>';
                                }
                                $content .=  '<B>'.$item['name']. '* 1</B><BR>';
                                $content .=  $item['ggname'] . '<BR>';
                                if (getcustom('restaurant_product_jialiao')) {
                                   if($item['njltitle']){
                                       $content .=  $item['njltitle'] . '<BR>';  
                                   }
                                }
                                if ($order['message']) $content .= '备注：' . $order['message'] . '<BR>';
                                $content .= '时间：' . date('Y-m-d H:i:s', $order['createtime']) . '<BR>';
                                $content .= '<BR><BR><BR><CUT>';//控制切纸 
                            }
                        }
                    }
                    if(getcustom('restaurant_tag_wifiprint')){
                        if($area['tag_print_status'] ==1){
                            //如果开启自定义模板 就使用自定义模板的值
                            $tag_data = $area['tag_data'];

                            if($tag_data && $machine['machine_type'] ==1){
                                $tag_data = json_decode($tag_data,true);
                                if($order['bid'] > 0){
                                    $bname = Db::name('business')->where('id',$order['bid'])->value('name');
                                }else{
                                    $bname = Db::name('admin_set')->where('aid',$order['aid'])->value('name');
                                }
                                $tablename = Db::name('restaurant_table')->where('id',$order['tableid'])->value('name');
                                $queue_no = Db::name('restaurant_queue')->where('aid',$order['aid'])->where('bid',$order['bid'])->where('mid',$order['mid'])->value('queue_no');

                                $content = [];
                                //把多数量的 拆分成一条数据
                                $allorderGoods =[];
                                $k = 0;
                                foreach ($orderGoods as $key => $item) {
                                    for($i =0; $i < $item['num'];$i++){
                                        $k += 1;
                                        $item['no'] = $k;
                                        $allorderGoods[] = $item;
                                    }
                                }
                                foreach ($allorderGoods as $key => $item) {
                                    if ($item['area_id'] != $area['id']) continue;//不在区域范围内不打印
                                    $tagcontent = '';
                                    $sell_price =  $item['sell_price'];
                                    $ggname =  $item['ggname'];
                                    $jialiao = '';
                                    if(getcustom('restaurant_product_jialiao')){
                                        $sell_price = dd_money_format($sell_price + $item['njlprice']);
                                        if($item['njltitle']){
                                            $jialiao = $item['njltitle'];
                                        }
                                    }
                                    $textReplaceArr = [
                                        '[商户名称]'=>$bname,
                                        '[桌号名称]'=>$tablename?$tablename:'',
                                        '[取餐号]'=>$order['pickup_number']?$order['pickup_number']:'',
                                        '[序号]'=>$item['no'].'/'.$k,
                                        '[商品名称]'=>$item['name'],
                                        '[商品单价]'=>$sell_price,
                                        '[规格参数]'=>$ggname=='默认规格'?'':$ggname,
                                        '[订单号]'=>$item['ordernum'],
                                        '[加料]'=>$jialiao,
                                        '[排队号]'=>$queue_no?$queue_no:'暂无',
                                        '[备注]'=>$order['remark']?$order['remark']:$order['message'],
                                        '[单品备注]'=>$item['remark']?$item['remark']:'',
                                    ];

                                    //1mm=8dots  计算比例 60mm * 60mm  模板是 240*240  转换为(240/4) = 60mm 所以使用 对应的像素/4 = 对应 mm  结果  * 8dots
                                    foreach($tag_data as  $d){
                                        $x = dd_money_format($d['left']/5 * 8,0);
                                        $y = dd_money_format($d['top']/5 *8,0);
                                        $w = $d['w']?$d['w']:1;
                                        $h = $d['h']?$d['h']:1;
                                        if ($d['type'] == 'text') {
                                            $d['content'] = str_replace(array_keys($textReplaceArr),array_values($textReplaceArr),$d['content']);
                                            $tagcontent .= '<TEXT x="'.$x.'" y="'.$y.'" font="12" w="'.$w.'" h='.$h.'" r="0">'.$d['content'].'</TEXT>';
                                        }else if ($d['type'] == 'textarea') {
                                            $d['content'] = str_replace(array_keys($textReplaceArr),array_values($textReplaceArr),$d['content']);
                                            $tagcontent .= '<TEXT x="'.$x.'" y="'.$y.'" font="12" w="'.$w.'" h="'.$h.'" r="0">'.$d['content'].'</TEXT>';
                                        }
                                    }
                                    $content[] = $tagcontent;
                                }
                            }
                        }
                    }
                 
                }
            }
        }elseif($machineType==4){
            //芯烨打印机
            if(getcustom('sys_print_xinye')){
                if($templateType == 0) {
                    //普通打印
                    if($orderType == 'test') {
                        $arr = array('酸菜鱼','可乐鸡翅' );
                        $content = '';
                        if(getcustom('sys_print_set')){
                            if($print_day_ordernum && $machine['day_ordernum']){
                                $content .=  '<CB>#'.$print_day_ordernum."</CB><BR><BR>";
                            }
                        }
                        $content .= "<CB>** ".$title." **</CB><BR><BR>";
                        $content .= '<CB>'.$tabletext.'：01</CB><BR>';
                        $content .= "订单编号：".date('Y-m-d H:i:s')."<BR>";
                        $content .= "配送方式：".$order['freight_text']."<BR>";
                        $content .= "配送时间：<B>".$order['freight_time']."</B><BR>";
                        $content .= "收货人:<B>".$order['linkman']."</B><BR>";
                        $content .= "联系电话:<B>".$order['tel']."</B><BR>";
                        $content .= "收货地址:<B>".$order['area']." ".$order['address']."</B><BR>";

                        $content .= "下单时间：".date('Y-m-d H:i:s')."<BR>";
                        $content .= "付款时间：".date('Y-m-d H:i:s')."<BR>";
                        $content .= "付款方式：".$order['paytype']."<BR><BR>";
                        $content .= "商品名称     数量     总价<BR>";

                        foreach ($arr as $key => $item) {
                            $content .= "<BOLD>".$item."</BOLD>   1  33.6<BR>";
                        }
                        $content .= "<BR>";
                        $content .= "备注：不要辣<BR>";
                        $content .= "<R>实付金额：￥108.00</R>";

                        if(getcustom('sys_print_set')){
                            //飞蛾小票底部自定义
                            $boot_custom =  $machine['boot_custom']?$machine['boot_custom']:0;
                            if($boot_custom){
                                $boot_custom_content = $machine['boot_custom_content'];
                                if($boot_custom_content){
                                    $content .= "<BR><BR>".$boot_custom_content;
                                }
                            }
                        }

                        $content .= "<BR><BR><BR><BR><BR><CUT>";
                    } else {
                        $content = '';
                        $tmpltype = 0;
                        if(getcustom('restaurant_wifiprint_tmpl_custom')){
                            $tmpltype =  $machine['restaurant_tmpltype'];
                        }
                        //如果是加菜 强制使用 默认模板
                        $isaddproduct = $order['isaddproduct']??0;

                        $print_type = $order['print_type']?$order['print_type']:0;//打印类型0 默认 1：预结单  2：退款或取消
                        if($tmpltype ==0) {
                            $content = '';
                            if (getcustom('sys_print_set')) {
                                if ($print_day_ordernum && $machine['day_ordernum'] && $isaddproduct==0 ) {
                                    $content .= '<CB>#' . $print_day_ordernum . "<BR></CB>";
                                }
                            }
                            //非加菜，且打印类型是默认，使用打印机标题
                            if($isaddproduct==0 && $print_type ==0)$content .= "<CB>" . $title . "<BR></CB>";
                            //1：预结单，如果是预结单类型，使用预结单标题
                            if($print_type ==1)$content .= '<CB>预结单<BR></CB>';
                            //退款类型，使用退款类型
                            if($print_type ==2)$content .= '<CB>退款小票<BR></CB>';
                            //桌台信息
                            if ($order['tableName']) $content .= '<CB>' . $tabletext . '：' . $order['tableName'] . '<BR></CB>';
                            if(getcustom('extend_qrcode_variable_fenzhang')){
                                if($order['qrcode_val_code']){
                                    $qrcode_list_variable=Db::name('qrcode_list_variable')->alias('qlv')
                                        ->join('qrcode_variable qv','qv.id = qlv.qid')
                                        ->where('qlv.aid',$order['aid'])->where('qlv.code',$order['qrcode_val_code'])->field('qlv.name,qv.name as qrname')->find();
                                    $qrcode_variable_name =  $qrcode_list_variable['qrname'];
                                    $qrcode_list_variable_name = $qrcode_list_variable['name'];
                                    if($qrcode_list_variable_name || $qrcode_variable_name){
                                        $content .= "<CB>" . $qrcode_variable_name.$qrcode_list_variable_name . "<BR></CB>";
                                    }
                                }
                            }
                            
                            if (getcustom('restaurant_take_food')) {
                                if ($order['pickup_number'] ) $content .= '<CB>取餐号：' . $order['pickup_number'] . '<BR><BR></CB>';
                            }
                            $content .= "订单编号：" . $order['ordernum'] . "<BR>";
                            if ($orderType == 'restaurant_takeaway') {
                                $content .= "配送方式：" . $order['freight_text'] . "<BR>";
                                if ($order['freight_time']) {
                                    $content .= "配送时间：<B>" . $order['freight_time'] . "</B><BR>";
                                }
                                $content .= "收货人:<B>" . $order['linkman'] . "<BR></B>";
                                $content .= "联系电话:<B>" . $order['tel'] . "<BR></B>";
                                $content .= "收货地址:<B>" . $order['area'] . " " . $order['address'] . "<BR></B>";
                            }
                            $content .= "下单时间：" . date('Y-m-d H:i:s', $order['createtime']) . "<BR>";
                            $content .= "付款时间：" . $paytime . "<BR>";
                            $content .= "付款方式：" . $order['paytype'] . "<BR>";
                            if($orderType == 'restaurant_booking'){
                                //预定：小票显示预定内容
                                $content .='<C>---------------------------------------------<BR></C>';
                                $content .= "客户姓名：" . $order['linkman']. "<BR>";
                                $content .= "联系电话：" . $order['tel'] . "<BR>";
                                $content .= "预约时间：" . $order['booking_time'] . "<BR>";
                                $content .= "预约人数：" . $order['seat'] . "人<BR><BR>";
                            }else{
                                //是否打印产品列表
                                $is_print_ordergoods = true; //是否打印商品列表
                                if(getcustom('restaurant_cashdesk_auth_enter')){
                                    //如果没有选中的商品，全部商品退款或者取消，不打印
                                    if(!$order['ogids'] && $print_type ==2) $is_print_ordergoods = false;
                                }
                                if($is_print_ordergoods){
                                    $content .='<C>---------------------------------------------<BR></C>';
                                    $content .= "商品名称      单价    数量      小计<BR>";
                                    $ogtotal = 0;
                                    foreach ($orderGoods as $item) {
                                        if (getcustom('product_jialiao')) {
                                            $item['ggname'] = $item['jltitle'] ? $item['ggname'] . $item['jltitle'] : $item['ggname'];
                                        }
                                        if (getcustom('restaurant_product_jialiao')) {
                                            if ($item['njltitle']) {
                                                $item['ggname'] = $item['ggname'] . '   ' . $item['njltitle'];
                                            }
                                            $item['sell_price'] =  dd_money_format($item['sell_price']+$item['njlprice']);
                                        }
                                        $ogtotal +=  $item['sell_price'] * $item['num'];
                                        $content .= '<BOLD>'.$item['name'].'</BOLD><BR>';
                                        if(getcustom('restaurant_cashdek_ordergoods_remark')){
                                            if($item['remark']){
                                                $og_remark = $item['remark'];
                                                $content .= '<BOLD>'.$og_remark.'</BOLD><BR>';
                                            } 
                                        }
                                        if(getcustom('restaurant_product_package')){
                                            if($item['package_data']){
                                                $package_data = json_decode($item['package_data'],true);
                                                foreach($package_data as $pdk=>$pd){
                                                    if($pd['ggname'] !='默认规格'){
                                                        $content.='—-'.$pd['proname'].'<BR>';
                                                        $content .='  -'.$pd['ggname'].' * '.floatval($pd['num']).'<BR>';
                                                    } else{
                                                        $content.='—-'.$pd['proname'].' * '.floatval($pd['num']).'<BR>';
                                                    }
                                                }
                                            }
                                        }
                                        $content .=$item['ggname'].'<BR>';
                                        $xj = dd_money_format($item['sell_price'] *$item['num']);
                                        $content .='            '.$item['sell_price'].'     '.floatval($item['num']).'      '.$xj.'<BR>';
                                        //                            $content .='<R>********************</R>';
                                        //                            $content .= "<BOLD>" . $item['name'] . "(" . $item['ggname'] . ")</BOLD>   " . floatval($item['num']) . "  " . $item['totalprice'] . "<BR>";
                                    }
                                    $content .='<C>---------------------------------------------<BR></C>';
                                }
                            }
                            if($isaddproduct ==1) $content .='<R>加菜总计:￥'.dd_money_format($ogtotal).'<BR></R>';
                            if($isaddproduct ==1)$content .= "<R>实付金额：￥" . $order['totalprice'] . "</R>";
                            if($isaddproduct ==0){
                                //打印类型 0：默认 1：预结单  2：退款或取消
                                if($print_type ==2){
                                    $content .='<C>---------------------------------------------<BR></C>';
                                    $content .= "<R>退款金额：￥" . $order['refund_money'] . "</R>";
                                } else{
                                    if ($order['message'] || $order['remark']) {
                                        $message =  $order['message']?$order['message']:$order['remark'];
                                        $content .= "备注：<B>" . $message . "<BR><BR></B>";
                                    } else {
                                        $content .= "备注：无<BR>";
                                    }
                                    if ($order['tea_fee']) {
                                        $content .= "<R>" . $shop_set['tea_fee_text'] . "：￥" . $order['tea_fee'] . "<BR></R>";
                                    }

                                    if ($order['scoredk_money'] > 0) {
                                        $content .= "<R>积分抵扣：-￥" . $order['scoredk_money'] . "<BR></R>";
                                    }
                                    if ($order['leveldk_money'] > 0) {
                                        $content .= "<R>会员等级优惠：-￥" . $order['leveldk_money'] . "<BR></R>";
                                    }
                                    if ($order['coupon_money'] > 0) {
                                        $content .= "<R>优惠券：-￥" . $order['coupon_money'] . "<BR></R>";
                                    }
                                    if ($order['discount_money'] > 0) {
                                        $content .= "<R>优惠：-￥" . $order['discount_money'] . "<BR></R>";
                                    }
                                    //预定
                                    if($orderType == 'restaurant_booking'){
                                        $content .='<C>-------------------------------<BR></C>';
                                    }
                                    $content .= "<R>实付金额：￥" . $order['totalprice'] . "<BR></R>";
                                }
                            }
                            if(getcustom('restaurant_take_food')){
                                if($order['pickup_number'] && $print_type!=2){
                                    $content .='<C>---------------------------------------------<BR></C>';
                                    $content .='<C>出餐码（非员工勿扫）<BR></C>';
                                    $qrcodeurl=  'type=outfood&id='.$order['id'].'&co=' . $order['pickup_number'];
                                    $qrcodeurl = base64_encode($qrcodeurl);
                                    $content .=" <BR>";
                                    $content .="<QRCODE s=6 e=L l=center>".$qrcodeurl."</QRCODE>";
                                }
                            }
                        }
                        else{
                            if(getcustom('restaurant_wifiprint_tmpl_custom')){
                                if($order['bid'] > 0){
                                    $bname = Db::name('business')->where('id',$order['bid'])->value('name');
                                }else{
                                    $bname = Db::name('admin_set')->where('aid',$order['aid'])->value('name');
                                }
                                //详细模板
                                $content = '';
                                if (getcustom('sys_print_set')) {
                                    if ($print_day_ordernum && $machine['day_ordernum'] && $isaddproduct==0 ) {
                                        $content .= '<CB>#' . $print_day_ordernum . "<BR></CB>";
                                    }
                                }
                                if($isaddproduct ==0 && $print_type ==0)$content .= '<C>'.$bname.'<BR></C>';
                                //1：预结单，如果是预结单类型，使用预结单标题
                                if($print_type ==1)  $content .= '<CB>预结单<BR></CB>';
                                //退款类型，使用退款类型
                                if($print_type ==2)$content .= '<CB>退款小票<BR></CB>';

                                if ($order['tableName']){
                                    $content .= '<CB>'.$tabletext.'：'.$order['tableName'].'<BR></CB>';
                                }
                                if(getcustom('extend_qrcode_variable_fenzhang')){
                                    if($order['qrcode_val_code']){
                                        $qrcode_list_variable=Db::name('qrcode_list_variable')->alias('qlv')
                                            ->join('qrcode_variable qv','qv.id = qlv.qid')
                                            ->where('qlv.aid',$order['aid'])->where('qlv.code',$order['qrcode_val_code'])->field('qlv.name,qv.name as qrname')->find();
                                        $qrcode_variable_name =  $qrcode_list_variable['qrname'];
                                        $qrcode_list_variable_name = $qrcode_list_variable['name'];
                                        if($qrcode_list_variable_name || $qrcode_variable_name){
                                            $content .= "<CB>" . $qrcode_variable_name.$qrcode_list_variable_name . "<BR></CB>";
                                        }
                                    }
                                }
                                if (getcustom('restaurant_take_food')) {
                                    if ($order['pickup_number'] ) $content .= '<CB>取餐号:'.$order['pickup_number'].'<BR></CB>';
                                }
                                $content .='订单号:'.$order['ordernum'].'<BR>';
                                if($print_type ==0)$content .='人数:'.$order['renshu'].'<BR>';
                                //$admin_user_name = '管理员';
                                //if($order['uid'] > 0){
                                //                                $admin_user_name = Db::name('admin_user')->where('id',$order['uid'])->value('un');

                                //}
                                if($order['uid'] > 0)$content .='收银员:'.$order['uid'].'<BR>';
                                if($order['mid']){
                                    $member = Db::name('member')->where('aid',$order['aid'])->where('id',$order['mid'])->field('id,levelid')->find();
                                    $levelname = Db::name('member_level')->where('id',$member['levelid'])->value('name');
                                    $content .='会员:'.$order['mid'].'<BR>';
                                    $content .='会员等级:'.$levelname.'<BR> ';
                                }
                                $content .='<C>---------------------------------------------<BR></C>';
                                $remark = $order['message']?$order['message']:$order['remark'];
                                if($print_type ==0)$content .='<B>备注: '.$remark.'<BR></B>';
                                if($order['linkman'])$content .='收货人:'.$order['linkman'].'<BR>';
                                if($order['tel'])$content .='联系电话:'.$order['tel'].'<BR>';
                                if($order['freight_text'])$content .='<B>配送地址:'.$order['freight_text'].'<BR></B>';
                                $is_print_ordergoods = true;//是否打印商品列表
                                if(getcustom('restaurant_cashdesk_auth_enter')){
                                    //如果没有选中的商品，全部商品退款或者取消，不打印
                                    if(!$order['ogids'] && $print_type ==2) $is_print_ordergoods = false;
                                }
                                if($is_print_ordergoods) {
                                    $content .= '<C>---------------------------------------------<BR></C>';
                                    $content .= '商品        单价   数量    小计<BR>';
                                    $content .= '<R>****************<BR></R>';
                                    $totalnum = 0;
                                    foreach ($orderGoods as $item) {
                                        $ggname = $item['ggname'];
                                        $sell_price = $item['sell_price'];
                                        if (getcustom('restaurant_product_jialiao')) {
                                            $sell_price = dd_money_format($item['sell_price'] + $item['njlprice']);
                                            if ($item['njltitle']) {
                                                $ggname .= '(' . $item['njltitle'] . ')';
                                            }
                                        }

                                        $content .= '<BOLD>' . $item['name'] . '<BR></BOLD>';
                                        if (getcustom('restaurant_product_package')) {
                                            if ($item['package_data']) {
                                                $package_data = json_decode($item['package_data'], true);
                                                foreach ($package_data as $pdk => $pd) {
                                                    if ($pd['ggname'] != '默认规格') {
                                                        $content .= '—-' . $pd['proname'] . '<BR>';
                                                        $content .= '  -' . $pd['ggname'] . ' * ' . floatval($pd['num']) . '<BR>';
                                                    } else {
                                                        $content .= '—-' . $pd['proname'] . ' * ' . floatval($pd['num']) . '<BR>';
                                                    }
                                                }
                                            }
                                        }
                                        $content .= $ggname . '<BR>';
                                        $xj = dd_money_format($sell_price * $item['num']);
                                        $content .= '          ' . $sell_price . '    ' . floatval($item['num']) . '   ' . $xj . '<BR>';
                                        $content .= '<R>****************<BR></R>';
                                        $totalnum += $item['num'];
                                    }
                                    $content .= '商品数量小计:' . $totalnum . '<BR>';
                                    $content .= '<C>---------------------------------------------<BR></C>';
                                }
                                //是否是加菜 0不是
                                $content .='下单时间:'.date('Y-m-d H:i:s',$order['createtime']).'<BR>';
                                if($isaddproduct ==0){
                                    //打印类型 0：默认 1：预结单  2：退款或取消
                                    if($print_type ==2){
                                        $content .='<CB>退款金额:'.$order['refund_money'].'元<BR></CB>';
                                    }else{
                                        if($order['paytime']){
                                            $paytime = $order['paytime']?date('Y-m-d H:i:s',$order['paytime']):'';
                                            $content .='付款时间:'.$paytime.'<BR>';
                                        }
                                        if($order['timing_money'] >0)$content .='计时费:'.$order['timing_money'].'<BR>';
                                        if($order['service_money'] > 0)$content .='服务费:'.dd_money_format($order['service_money']).'<BR>';
                                        if($order['tea_fee'] >0)$content .=$shop_set['tea_fee_text'].':'.$order['tea_fee'].'<BR>';
                                        $product_price = $order['product_price'];
                                        if($order['timing_money'] >0 ){
                                            $product_price =  dd_money_format($product_price + $order['timing_money']);
                                        }
                                        if($order['service_money'] >0 ){
                                            $product_price =  dd_money_format($product_price + $order['service_money']);
                                        }
                                        if($order['tea_fee'] >0){
                                            $product_price =  dd_money_format($product_price + $order['tea_fee']);
                                        }
                                        if($order['product_price'] >0) $content .='总计(优惠前总金额):'.$product_price.'<BR>';
                                        if($order['cuxiao_money'] >0)$content .='活动优惠: -'.dd_money_format($order['cuxiao_money']).'<BR>';
                                        if($order['coupon_money'] >0)$content .=t('优惠券').'优惠:-'.$order['coupon_money'].'<BR>';
                                        if($order['leveldk_money'] >0)$content .='会员折扣:-'.$order['leveldk_money'].'<BR>';
                                        if($order['direct_money'] >0)$content .='其他优惠:-'.$order['direct_money'].'<BR>';
                                        if($order['moling_money'] >0)$content .='抹零:-'.$order['moling_money'].'<BR>';
                                        $content .='<CB>应付:'.$order['totalprice'].'元<BR></CB>';
                                        $content .='<C>---------------------------------------------<BR></C>';

                                        $content .=$order['paytype']?'付款方式:'.$order['paytype'].'<BR>':'未支付<BR>';
                                        if($order['paynum'])$content .='支付单号:'.$order['paynum'].'<BR>';

                                        if($order['mid']){
                                            $member = Db::name('member')->where('aid',$order['aid'])->where('id',$order['mid'])->field('id,money')->find();
                                            $content .='平台会员余额:'.$member['money'];
                                            $content .='<C>--------------------------------<BR></C>';

                                        }
                                    }
                                }
                                if(getcustom('restaurant_take_food')){
                                    if($order['pickup_number'] && $print_type !=2){
                                        $content .='<C>---------------------------------------------<BR></C>';
                                        $content .='<C>出餐码（非员工勿扫）<BR></C>';
                                        $qrcodeurl=  'type=outfood&id='.$order['id'].'&co=' . $order['pickup_number'];
                                        $qrcodeurl = base64_encode($qrcodeurl);
                                        $content .=" <BR>";
                                        $content .="<QRCODE s=8 e=L l=center>".$qrcodeurl."</QRCODE>";
                                    }
                                }
                            }
                        }
                        if (getcustom('sys_print_set') &&  $print_type ==0) {
                            //飞蛾小票底部自定义
                            $boot_custom = $machine['boot_custom'] ? $machine['boot_custom'] : 0;
                            if ($boot_custom) {
                                $boot_custom_content = $machine['boot_custom_content'];
                                if ($boot_custom_content) {
                                    $content .= "<BR><BR>" . $boot_custom_content;
                                }
                            }
                        }
                        $content = str_replace(["\r","\n"],'',$content);
                    }
                } elseif ($templateType == 1) {
                    //一菜一单
                    if($orderType == 'test') {
                        $time = date('Y-m-d H:i:s',time());
                        $arr = array('酸菜鱼','可乐鸡翅' );
                        $content = '';
                        $num = count($arr);
                        foreach ($arr as $key => $value) {
                            $content .= '<CB>** '.$tabletext.'：01 **</CB><BR>';
                            $content .= '<C><HB>'.$value.'  * 1</HB></C><BR>';
                            $content .= '<HB>备注：不要辣，少盐</HB><BR>';
                            $content .= '时间：'.$time.'<BR>';
                            $end = array_keys($arr);
                            // if(end($end)==$key){
                            //     break;
                            // }else{
                            if(getcustom('sys_print_set')){
                                //飞蛾小票底部自定义
                                $boot_custom =  $machine['boot_custom']?$machine['boot_custom']:0;
                                if($boot_custom){
                                    $boot_custom_content = $machine['boot_custom_content'];
                                    if($boot_custom_content){
                                        $content .= "<BR><BR>".$boot_custom_content;
                                    }
                                }
                            }
                            $content .= '<BR><BR><BR><CUT>';//控制切纸
                            // }
                        }

                    }else {
                        $content = '';
                        $tmpltype = 0;
                        if(getcustom('restaurant_wifiprint_tmpl_custom')){
                            $tmpltype =  $machine['restaurant_tmpltype'];
                        }
                        if(getcustom('restaurant_product_package')){
                            //把带套餐的 解析到外层数据中
                            $orderGoods_p = [];
                            $p = 0;
                            foreach($orderGoods as $pitem){
                                if($pitem['package_data']){
                                    $package_data = json_decode($pitem['package_data'],true);
                                    foreach($package_data as $pdk=>$pd){
                                        $areaid = Db::name('restaurant_product')->where('aid',$pitem['aid'])->where('id',$pd['proid'])->value('area_id');
                                        $pd['name'] = $pd['proname'];
                                        $pd['area_id'] =$areaid;
                                        if($pitem['remark'])$pd['remark'] =$pitem['remark'];
                                        $orderGoods_p[$p] =$pd;
                                        $p ++;
                                    }
                                }else{
                                    $orderGoods_p[$p] = $pitem;
                                    $p ++;
                                }
                            }
                            $orderGoods = $orderGoods_p;
                        }
                        $allorderGoods =[];
                        $k = 0;
                        foreach ($orderGoods as $key => $item) {
                            if(!$item['area_id']){
                                $areaid = Db::name('restaurant_product')->where('aid',$item['aid'])->where('id',$item['proid'])->value('area_id');
                                $item['area_id'] =  $areaid;
                            }
                            for ($i = 0; $i < $item['num']; $i++) {
                                $k += 1;
                                $item['no'] = $k;
                                $allorderGoods[] = $item;
                            }
                        }
                        if($tmpltype ==0) {//默认模板

                            foreach ($allorderGoods as $key => $item) {

                                if ($item['area_id'] != $area['id']) continue;//不在区域范围内不打印
                                if (getcustom('sys_print_set')) {
                                    if ($print_day_ordernum && $machine['day_ordernum']) {
                                        $content .= '<CB>#' . $print_day_ordernum . "</CB>";
                                    }
                                }
                                if (getcustom('product_jialiao')) {
                                    $item['ggname'] = $item['jltitle'] ? $item['ggname'] . $item['jltitle'] : $item['ggname'];
                                }
                                if (getcustom('restaurant_product_jialiao')) {
                                    $item['ggname'] = $item['njltitle'] ? $item['ggname'] . $item['njltitle'] : $item['ggname'];
                                }
                                if(getcustom('restaurant_take_food')){
                                    if($order['pickup_number']){
                                        $content .= '<C><CB>取餐号：' . $order['pickup_number'] . '<BR></CB></C>';
                                    }
                                }
                                if($item['no']){
                                    $content .= '<R>'. $item['no'].'/'.$k . '<BR></R>';
                                }
                                $content .= '<C><CB>' . $tabletext . '：' . $order['tableName'] . '<BR></CB><C>';
                                $content .='<BR>';
//                                $content .= '<C><CB>' . $item['name'] . "(" . $item['ggname'] . ")" . '  * 1<BR><BR></CB></C>';
                                $content .= '<C><CB>' . $item['name'] . "(" . $item['ggname'] . ")" . '  * 1<BR><BR></CB></C>';
                                if ($order['message']) $content .= '<HB>备注：' . $order['message'] . '<BR></HB>';
                                $content .= '时间：' . date('Y-m-d H:i:s', $order['createtime']) . '<BR>';

                                if (getcustom('sys_print_set')) {
                                    //底部自定义
                                    $boot_custom = $machine['boot_custom'] ? $machine['boot_custom'] : 0;
                                    if ($boot_custom) {
                                        $boot_custom_content = $machine['boot_custom_content'];
                                        if ($boot_custom_content) {
                                            $content .= "<BR><BR>" . $boot_custom_content;
                                        }
                                    }
                                }
                                $content .= '<BR>';//控制切纸 
                            }
                        }
                        else{ //自定义
                            if (getcustom('restaurant_wifiprint_tmpl_custom')) {
                                foreach ($allorderGoods as $key => $item) {
                                    if ($item['area_id'] != $area['id']) continue;//不在区域范围内不打印
                                    //退款打印时
                                    if($order['print_type'] ==2){
                                        $content .= '<CB>[退]</CB><BR>';
                                    }
                                    if (getcustom('sys_print_set')) {
                                        if ($print_day_ordernum && $machine['day_ordernum']) {
                                            $content .= '#' . $print_day_ordernum ;
                                        }
                                    }
                                    if($item['no']){
                                        $content .= '                        ' . $item['no'].'/'.$k . '<BR>';
                                    }
                                    if(getcustom('restaurant_take_food')){
                                        if($order['pickup_number']){
                                            $content .= '<CB>取餐号' . $order['pickup_number'] . '<BR><BR></CB>';
                                        }
                                    }
                                    if($order['tableName']){
                                        $content .= '<CB>' . $tabletext . '：' . $order['tableName'] . '<BR><BR></CB>';
                                    }
                                    $content .=  '<CB>'.$item['name']. '<BR></CB>';
                                    $content .=  '<CB>【1】'.$item['ggname'] . '<BR></CB>';
                                    if (getcustom('restaurant_product_jialiao')) {
                                        if($item['njltitle']){
                                            $content .=  $item['njltitle'] . '<BR>';
                                        }
                                    }
                                    if ($order['message']) $content .= '备注：' . $order['message'] . '<BR>';
                                    $content .= '时间：' . date('Y-m-d H:i:s', $order['createtime']) . '<BR>';
                                    $content .= '<BR>';//控制切纸 
                                }
                            }
                        }
                        if(getcustom('restaurant_tag_wifiprint')){
                            if($area['tag_print_status'] ==1){
                                //如果开启自定义模板 就使用自定义模板的值
                                $tag_data = $area['tag_data'];

                                if($tag_data && $machine['machine_type'] ==1){
                                    $tag_data = json_decode($tag_data,true);
                                    if($order['bid'] > 0){
                                        $bname = Db::name('business')->where('id',$order['bid'])->value('name');
                                    }else{
                                        $bname = Db::name('admin_set')->where('aid',$order['aid'])->value('name');
                                    }
                                    $tablename = Db::name('restaurant_table')->where('id',$order['tableid'])->value('name');
                                    $queue_no = Db::name('restaurant_queue')->where('aid',$order['aid'])->where('bid',$order['bid'])->where('mid',$order['mid'])->value('queue_no');

                                    $content = [];
                                    //把多数量的 拆分成一条数据
                                    $allorderGoods =[];
                                    $k = 0;
                                    foreach ($orderGoods as $key => $item) {
                                        for($i =0; $i < $item['num'];$i++){
                                            $k += 1;
                                            $item['no'] = $k;
                                            $allorderGoods[] = $item;
                                        }
                                    }
                                    foreach ($allorderGoods as $key => $item) {
                                        if ($item['area_id'] != $area['id']) continue;//不在区域范围内不打印
                                        $tagcontent = '';
                                        $sell_price =  $item['sell_price'];
                                        $ggname =  $item['ggname'];
                                        $jialiao = '';
                                        if(getcustom('restaurant_product_jialiao')){
                                            $sell_price = dd_money_format($sell_price + $item['njlprice']);
                                            if($item['njltitle']){
                                                $jialiao = $item['njltitle'];
                                            }
                                        }
                                        $textReplaceArr = [
                                            '[商户名称]'=>$bname,
                                            '[桌号名称]'=>$tablename?$tablename:'',
                                            '[取餐号]'=>$order['pickup_number']?$order['pickup_number']:'',
                                            '[序号]'=>$item['no'].'/'.$k,
                                            '[商品名称]'=>$item['name'],
                                            '[商品单价]'=>$sell_price,
                                            '[规格参数]'=>$ggname=='默认规格'?'':$ggname,
                                            '[订单号]'=>$item['ordernum'],
                                            '[加料]'=>$jialiao,
                                            '[排队号]'=>$queue_no?$queue_no:'暂无',
                                            '[备注]'=>$order['remark']?$order['remark']:$order['message'],
                                            '[单品备注]'=>$item['remark']?$item['remark']:'',
                                        ];

                                        //1mm=8dots  计算比例 60mm * 60mm  模板是 240*240  转换为(240/4) = 60mm 所以使用 对应的像素/4 = 对应 mm  结果  * 8dots
                                        foreach($tag_data as  $d){
                                            $x = dd_money_format($d['left']/5 * 8,0);
                                            $y = dd_money_format($d['top']/5 *8,0);
                                            $w = $d['w']?$d['w']:1;
                                            $h = $d['h']?$d['h']:1;
                                            if ($d['type'] == 'text') {
                                                $d['content'] = str_replace(array_keys($textReplaceArr),array_values($textReplaceArr),$d['content']);
                                                $tagcontent .= '<TEXT x="'.$x.'" y="'.$y.'" font="12" w="'.$w.'" h='.$h.'" r="0">'.$d['content'].'</TEXT>';
                                            }else if ($d['type'] == 'textarea') {
                                                $d['content'] = str_replace(array_keys($textReplaceArr),array_values($textReplaceArr),$d['content']);
                                                $tagcontent .= '<TEXT x="'.$x.'" y="'.$y.'" font="12" w="'.$w.'" h="'.$h.'" r="0">'.$d['content'].'</TEXT>';
                                            }
                                        }
                                        $content[] = $tagcontent;
                                    }
                                }
                            }
                        }

                    }
                }
            }
        }
        
        return $content;
    }

    /**
     * 餐饮打印小票和标签  当前逻辑：一菜一单的打印机根据菜品绑定区域打印（适合厨房等出餐区域）普通打印的打印机只要开启自动打印每单都会打印（适合吧台、收银台等区域）并行打印
     * @param $orderType
     * @param $orderInfo
     * @param array $orderGoods
     * @param  $templateType 打印类型（后台打印只打印一菜一单类型）  0普通打印，1一菜一单（一菜一单模式根据菜品出餐区域设置 选择对应的打印机打印）
     * @param  $autoprint 是否限制只自动打印，0 不限制条件 1：仅能自动打印的
     * @return array
     * 门店出单
     */
    public static function print($orderType, $orderInfo, $orderGoods = [], $templateType = '',$autoprint=0)
    {
        if(is_int($orderInfo)) {
            $orderInfo = Db::name($orderType.'_order')->where('id',$orderInfo)->find();
            $orderGoods = Db::name($orderType.'_order_goods')->alias('og')->where('orderid',$orderInfo)->leftJoin('restaurant_product p', 'p.id=og.proid')
                ->fieldRaw('og.*,p.area_id')->select()->toArray();
        }
        if(empty($orderGoods)) {
            $orderGoods = Db::name($orderType.'_order_goods')->alias('og')->where('orderid',$orderInfo['id'])->leftJoin('restaurant_product p', 'p.id=og.proid')
                ->fieldRaw('og.*,p.area_id')->select()->toArray();
        }
//        Log::info([
//            'file' => __FILE__,
//            'line' => __LINE__,
//            'orderInfo' => $orderInfo,
//            'orderGoods' => $orderGoods
//        ]);

        if($orderInfo['tableid']) {
            //餐桌绑定打印区域
            $tablewhere = [];
            $tablewhere[] = ['aid','=',$orderInfo['aid']];
            $tablewhere[] = ['id','=',$orderInfo['tableid']];
            if(!getcustom('extend_qrcode_variable_fenzhang')){
                $tablewhere[] = ['bid','=',$orderInfo['bid']];
            }
            $table = Db::name('restaurant_table')->where($tablewhere)->find();
            $orderInfo['tableName'] = $table['name'];
            $tablePrintIds = explode(',',$table['print_ids']);
        }

        $whereArea[] = ['aid', '=', $orderInfo['aid']];
        $whereArea[] = ['bid', '=', $orderInfo['bid']];
        $whereArea[] = ['status', '=', 1];
        if($templateType !== '') {
            $whereArea[] = ['print_template_type', '=', $templateType];
        }

        $areaList = Db::name('restaurant_area')->where($whereArea)->select()->toArray();
//        Log::info([
//            'file' => __FILE__,
//            'line' => __LINE__,
//            'areaList' => $areaList
//        ]);
        if(getcustom('sys_print_set')){
        	$aid = $orderInfo['aid']??0;
    		$bid = $orderInfo['bid']??0;
            //记录今天打印了几次
            $nowtime = strtotime(date("Y-m-d",time()));
            $printdaynum        = 'print_day_ordernum'.$aid.$bid.$nowtime;
            $print_day_ordernum = '';
            if($orderInfo['printdaynum']){
                if($orderType == 'restaurant_shop' || $orderType == 'restaurant_takeaway'){
                    $print_day_ordernum = $orderInfo['printdaynum'];
                }
            }else{
                $print_day_ordernum = cache($printdaynum);
	            if(!$print_day_ordernum || empty($print_day_ordernum)){
	                cache($printdaynum,1);
	                $print_day_ordernum = 1;
	            }
            }
            $orderInfo['print_day_ordernum'] = $print_day_ordernum;
            //打印状态次数
		    $print_status = 0;
        }
        if($areaList) {
            //遍历所有区域，（普通打印）吧台收银台每单都打，（一菜一单）厨房等区域只打绑定的菜品
            foreach ($areaList as $area) {
                //不同菜品，选择不同区域
                if(empty($area['print_ids'])) {
                    continue;
                }
                $where = [];
		        $where[] = ['aid', '=', $orderInfo['aid']];
		        $where[] = ['bid', '=', $orderInfo['bid']];
		        if($autoprint == 1) {
		            $where[] = ['autoprint', '=', $autoprint];
		        }
		        $where[] = ['id', 'in', $area['print_ids']];
                $machineList = Db::name('wifiprint_set')->where($where)->select()->toArray();
                if(empty($machineList)) {
                    continue;
                }
                foreach ($machineList as $machine) {
                    if($orderInfo['freight_type']==1 || $orderInfo['freight_type']==5){ //自提订单,门店配送
                        if($machine['print_zt_type']==0) continue;
                        if($machine['print_zt_type']==2){ //指定门店
                            $mdids = explode(',',$machine['print_zt_mdid']);
                            if(!in_array($orderInfo['mdid'],$mdids)) continue;
                        }
                    }
                    $num = 1;
		            if(getcustom('sys_print_set')){
		            	//打印次数
		                $num =  $machine['print_num']?$machine['print_num']:1;
		            }
		            for($i=0;$i<$num;$i++){
	                    $content = \app\customs\Restaurant::restaurantPrintContent($machine['title'],$machine['type'], $area, $orderType, $orderInfo, $orderGoods,$machine);
	                    if(empty($content)) continue;
	                    if($machine['type']==0){
	                        $rs = \app\commons\Wifiprint::yilianyun_print($machine['client_id'],$machine['client_secret'],$machine['access_token'],$machine['machine_code'],$machine['msign'],$content);
							//return $rs;
	                    }elseif($machine['type']==1){      
	                        if(is_array($content)){
	                            foreach ($content as  $val){
                                    $rs = \app\commons\Wifiprint::feie_print($machine['client_id'],$machine['client_secret'],$machine['machine_code'],$machine['msign'],$val,$machine['machine_type']);
                                }
                            }else{
                                $rs = \app\commons\Wifiprint::feie_print($machine['client_id'],$machine['client_secret'],$machine['machine_code'],$machine['msign'],$content,$machine['machine_type']);
                            }
	                      
							//return $rs;
	                    } elseif($machine['type']==4 && $content){
	                        //芯烨
	                        if(getcustom('sys_print_xinye')){
                                $voice = 1;//默认静音
                                if(is_array($content)){
                                    foreach ($content as  $val){
                                        $rs = \app\commons\Wifiprint::xinye_print($machine['client_id'],$machine['client_secret'],$machine['machine_code'],$val,$voice,$machine['machine_type']);
                                    }
                                }else{
                                    $rs = \app\commons\Wifiprint::xinye_print($machine['client_id'],$machine['client_secret'],$machine['machine_code'],$content,$voice,$machine['machine_type']);
                                }
                            }
                        }
	                }
                }
            }
            
        }
        //餐桌绑定打印区域
        if($tablePrintIds) {
        	$where = [];
	        $where[] = ['aid', '=', $orderInfo['aid']];
	        $where[] = ['bid', '=', $orderInfo['bid']];
	        if($autoprint == 1) {
	            $where[] = ['autoprint', '=', $autoprint];
	        }
	        $where[] = ['id', 'in', $tablePrintIds];
            $machineList = Db::name('wifiprint_set')->where($where)->select()->toArray();
            if($machineList) {
                foreach ($machineList as $machine) {
                	$num = 1;
		            if(getcustom('sys_print_set')){
		            	//打印次数
		                $num =  $machine['print_num']?$machine['print_num']:1;
		            }
		            for($i=0;$i<$num;$i++){
	                    $content = \app\customs\Restaurant::restaurantPrintContent($machine['title'],$machine['type'], ['print_template_type' => 0], $orderType, $orderInfo, $orderGoods,$machine);
	                    Log::info([
	                        'file' => __FILE__,
	                        'line' => __LINE__,
	                        'content' => $content
	                    ]);
	                    if(empty($content)) continue;
	                    if($machine['type']==0){
	                        $rs = \app\commons\Wifiprint::yilianyun_print($machine['client_id'],$machine['client_secret'],$machine['access_token'],$machine['machine_code'],$machine['msign'],$content);
	                    }elseif($machine['type']==1){
	                        $rs = \app\commons\Wifiprint::feie_print($machine['client_id'],$machine['client_secret'],$machine['machine_code'],$machine['msign'],$content,$machine['machine_type']);
	                    }
	                }
                }
            }
        }
        if(getcustom('sys_print_set')){
            if(!$orderInfo['printdaynum']){
                if($orderType == 'restaurant_shop' || $orderType == 'restaurant_takeaway'){
                	//更新日单号
                    Db::name($orderType.'_order')->where('id',$orderInfo['id'])->update(['printdaynum'=>$print_day_ordernum]);
                    $print_day_ordernum ++;
                	cache($printdaynum,$print_day_ordernum);
                }
            }
        }
        return ['status'=>1,'msg'=>'打印成功'];
    }

    //排队打印
    public function queuePrint($queue){
        if (getcustom('restaurant_queue_print')){
            $print_ids = Db::name('restaurant_queue_sysset')->where('aid',$queue['aid'])->where('bid',$queue['bid'])->value('print_ids');
            $machineList = [];
            if($print_ids){
                $where = [];
                $where[] = ['aid', '=', $queue['aid']];
                $where[] = ['id', 'in', $print_ids];
                $machineList = Db::name('wifiprint_set')->where($where)->select()->toArray();
            }
            if(empty($machineList)) {
                return false;
            }
            if($queue['bid'] > 0){
                $bname = Db::name('business')->where('id',$queue['bid'])->value('name');
            }else{
                $bname = Db::name('admin_set')->where('aid',$queue['aid'])->value('name');
            }
            $cname = Db::name('restaurant_queue_category') ->where('id',$queue['cid'])->value('name');
            $count = Db::name('restaurant_queue')->where('aid',$queue['aid'])->where('bid',$queue['bid'])->where('date',date('Y-m-d'))->where('cid',$queue['cid'])->where('create_time','<',$queue['create_time'])->where('status',0)->order('create_time ')->count();
            
            foreach ($machineList as $machine) {
                if($machine['type'] ==0){  //易联云
                    $before_text = "您前面还有 <FS2>".$count."</FS2> 个号码在排队";
                    $content = '';
                    $content .= "<FS><center>** ".$bname." **</center></FS>\r";
                    $content .="--------------------------------\r";
                    $content .=  "您的号码：<FS2>".$cname.' '.$queue['queue_no']."</FS2>\r\r";
                    $content .=  $before_text;
                    $content .= "\r\r";
                    $rs = \app\commons\Wifiprint::yilianyun_print($machine['client_id'],$machine['client_secret'],$machine['access_token'],$machine['machine_code'],$machine['msign'],$content);
                }elseif ($machine['type']==1){ //飞蛾
                    $before_text = "您前面还有 <B>".$count."</B> 个号码在排队";
                    $content = '';
                    $content .= "<CB>** ".$bname." **</CB><BR>";
                    $content .="--------------------------------<BR>";
                    $content .=  "您的号码：<B>".$cname.' '.$queue['queue_no']."</B><BR><BR>";
                    $content .=  $before_text;
                    $content .= "<CUT>";
                     $rs = \app\commons\Wifiprint::feie_print($machine['client_id'],$machine['client_secret'],$machine['machine_code'],$machine['msign'],$content,$machine['machine_type']);
                }
            }
        }
    }
    public static function calculateCommission($aid, $product, $member, $commission_totalprice, $num)
    {
        if($member['pid']){
            $parent1 = self::getParentWithLevel($aid, $member['pid']);
            if($parent1 && $parent1['levelData']['can_agent'] != 0){
                $ogdata['parent1'] = $parent1['id'];
            }
        }
        if($parent1['pid']){
            $parent2 = self::getParentWithLevel($aid, $parent1['pid']);
            if($parent2 && $parent2['levelData']['can_agent'] != 0){
                $ogdata['parent2'] = $parent2['id'];
            }
        }
        if($parent2['pid']){
            $parent3 = self::getParentWithLevel($aid, $parent2['pid']);
            if($parent3 && $parent3['levelData']['can_agent'] != 0){
                $ogdata['parent3'] = $parent3['id'];
            }
        }
        if($product['commissionset']==1){//按商品设置的分销比例
            $commissiondata = json_decode($product['commissiondata1'],true);
            if($commissiondata){
                if($ogdata['parent1']) $ogdata['parent1commission'] = $commissiondata[$parent1['levelData']['id']]['commission1'] * $commission_totalprice * 0.01;
                if($ogdata['parent2']) $ogdata['parent2commission'] = $commissiondata[$parent2['levelData']['id']]['commission2'] * $commission_totalprice * 0.01;
                if($ogdata['parent3']) $ogdata['parent3commission'] = $commissiondata[$parent3['levelData']['id']]['commission3'] * $commission_totalprice * 0.01;
            }
        }elseif($product['commissionset']==2){//按固定金额
            $commissiondata = json_decode($product['commissiondata2'],true);
            if($commissiondata){
                if($ogdata['parent1']) $ogdata['parent1commission'] = $commissiondata[$parent1['levelData']['id']]['commission1'] * $num;
                if($ogdata['parent2']) $ogdata['parent2commission'] = $commissiondata[$parent2['levelData']['id']]['commission2'] * $num;
                if($ogdata['parent3']) $ogdata['parent3commission'] = $commissiondata[$parent3['levelData']['id']]['commission3'] * $num;
            }
        }elseif($product['commissionset']==3){//提成是积分
            $commissiondata = json_decode($product['commissiondata3'],true);
            if($commissiondata){
                if($ogdata['parent1']) $ogdata['parent1score'] = $commissiondata[$parent1['levelData']['id']]['commission1'] * $num;
                if($ogdata['parent2']) $ogdata['parent2score'] = $commissiondata[$parent2['levelData']['id']]['commission2'] * $num;
                if($ogdata['parent3']) $ogdata['parent3score'] = $commissiondata[$parent3['levelData']['id']]['commission3'] * $num;
            }
        }else{ //按会员等级设置的分销比例
            if($ogdata['parent1']){
                if($parent1['levelData']['commissiontype']==1){ //固定金额按单
                    if($istc1==0){
                        $ogdata['parent1commission'] = $parent1['levelData']['commission1'];
                        $istc1 = 1;
                    }
                }else{
                    $ogdata['parent1commission'] = $parent1['levelData']['commission1'] * $commission_totalprice * 0.01;
                }
            }
            if($ogdata['parent2']){
                if($parent2['levelData']['commissiontype']==1){
                    if($istc2==0){
                        $ogdata['parent2commission'] = $parent2['levelData']['commission2'];
                        $istc2 = 1;
                    }
                }else{
                    $ogdata['parent2commission'] = $parent2['levelData']['commission2'] * $commission_totalprice * 0.01;
                }
            }
            if($ogdata['parent3']){
                if($parent3['levelData']['commissiontype']==1){
                    if($istc3==0){
                        $ogdata['parent3commission'] = $parent3['levelData']['commission3'];
                        $istc3 = 1;
                    }
                }else{
                    $ogdata['parent3commission'] = $parent3['levelData']['commission3'] * $commission_totalprice * 0.01;
                }
            }
        }
    }

    public static function getParentWithLevel($aid, $mid, $filter = [])
    {
        if(empty($mid)) {
            return [];
        }
        $parent = Db::name('member')->where('aid',$aid)->where('id',$mid)->find();
        if($parent){
            $parent['levelData'] = Db::name('member_level')->where('aid',$aid)->where('id',$parent['levelid'])->find();
        }
        return $parent;
    }

    public static function getStatusWeek($status_week){
        $order_day = '';
        foreach($status_week as $item){
            if($item == 1){
                $order_day.= ' 周一';
            }elseif($item == 2){
                $order_day.= ' 周二';
            }elseif($item == 3){
                $order_day.= ' 周三';
            }elseif($item == 4){
                $order_day.= ' 周四';
            }elseif($item == 5){
                $order_day.= ' 周五';
            }elseif($item == 6){
                $order_day.= ' 周六';
            }elseif($item == 7){
                $order_day.= ' 周日';
            }
        }
        return $order_day;
    }

    public static function closepayafter(){
        if(getcustom('restaurant_order_payafter_autoclose')){
            //查询账户
            $admin = Db::name('admin')
                ->where('status',1)
                ->field('id')
                ->select()
                ->toArray();
            if($admin){
                foreach($admin as $v){
                	$aid = $v['id'];
                    //餐后付款关闭时，未支付订单自动关闭时间，自动清台后变为空闲
                    $syssets = Db::name('restaurant_shop_sysset')
                        ->where('aid',$v['id'])
                        ->where('status',1)
                        ->where('pay_after',0)
                        ->where('pay_after_autoclose',1)
                        ->field('id,bid,pay_after,pay_after_autoclose,pay_after_autoclosetime')
                        ->select()
                        ->toArray();

                    if($syssets){
                        foreach($syssets as $set){
                        	$bid = $set['bid'];
                            //订单过期时间
                            $endtime = time()-$set['pay_after_autoclosetime'];
                            $orders = Db::name('restaurant_shop_order')
                                ->where('aid',$aid)
                                ->where('bid',$bid)
                                ->where('status',0)
                                ->where('createtime','<=',$endtime)
                                ->field('id,aid,bid,coupon_rid,tableid')
                                ->order('id asc')
                                ->select()
                                ->toArray();
                            if($orders){
                                foreach($orders as $order){
                                    //关闭订单
                                    $uporder = Db::name('restaurant_shop_order')->where('id',$order['id'])->where('status',0)->update(['status'=>4]);
                                    if($uporder){
                                        //加库存
                                        $oglist = Db::name('restaurant_shop_order_goods')->where('orderid',$order['id'])->where('aid',$aid)->select()->toArray();
                                        if($oglist){
                                            foreach($oglist as $og){
                                                Db::name('restaurant_product_guige')->where('aid',$aid)->where('id',$og['ggid'])->update(['stock'=>Db::raw("stock+".$og['num']),'sales'=>Db::raw("sales-".$og['num'])]);
                                                Db::name('restaurant_product')->where('aid',$aid)->where('id',$og['proid'])->update(['stock'=>Db::raw("stock+".$og['num']),'sales'=>Db::raw("sales-".$og['num'])]);
                                                if($og['seckill_starttime']){
                                                    Db::name('seckill_prodata')->where('aid',$aid)->where('proid',$og['proid'])->where('ggid',$og['ggid'])->where('starttime',$og['seckill_starttime'])->dec('sales',$og['num'])->update();
                                                }
                                            }
                                        }
                                        //优惠券抵扣的返还
                                        if($order['coupon_rid'] > 0){
                                            Db::name('coupon_record')->where('aid',$aid)->where('id',$order['coupon_rid'])->update(['status'=>0,'usetime'=>'']);
                                        }
                                        Db::name('restaurant_shop_order_goods')->where('orderid',$order['id'])->where('aid',$aid)->update(['status'=>4]);
                                        Db::name('restaurant_table')->where('id',$order['tableid'])->where('aid',$aid)->where('bid',$bid)->update(['status' => 0, 'orderid' => 0]);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    
    //增加取餐内容
    public static function addTakeFoodNumber($order){
        if(getcustom('restaurant_take_food')) {
            if (!$order) {
                return false;
            }
            $restaurant_sysset = Db::name('restaurant_admin_set')->where('aid',$order['aid'])->find();
            $call_text = '请' . $order['pickup_number'] . '号到取餐处取餐。';
            $file = 'upload/'.$order['aid'].'/audio/'.$restaurant_sysset['queue_voicetype'].'/'.$order['pickup_number'].'.mp3';
            $filepath = ROOT_PATH.$file;
            if(!file_exists($filepath)){
                $rs =self::call_no($order['aid'],$call_text);
                if(!is_dir(dirname($filepath))){
                    @mkdir(dirname($filepath),0777,true);
                }
                file_put_contents($filepath,$rs);
            }
            $call_voice_url = PRE_URL.'/'.$file;
            $insert = [
                'aid' => $order['aid'],
                'bid' => $order['bid'],
                'mid' => $order['mid'],
                'orderid' => $order['id'],
                'pickup_number' => $order['pickup_number'],
                'create_time' => time(),
                'status' => 0,
                'call_text' => $call_text,
                'platform' => 'wx',
                'call_voice_url' => $call_voice_url,
                'need_play' => 1
            ];
            $id = Db::name('restaurant_take_food')->insertGetId($insert);
            send_socket(['type'=>'restaurant_outfood_create','data'=>['aid'=>aid,'bid' => bid,'id'=>$id]]);
            return true;
        }
    }
}