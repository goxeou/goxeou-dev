<?php


namespace app\commons;

use think\facade\Db;
class Order
{
	//订单退款  1余额支付 2微信支付 3支付宝支付 4货到付款 11百度小程序 12头条小程序
	//$params 其他数据 如['refund_combine'=>true,'refund_order'=>$refund_order]
	public static function refund($order,$refund_money,$reason='退款',$params=[]){
		if(!$reason) $reason = '退款';
		$paytype = $order['paytypeid'];
		if(is_null($paytype)) return ['status'=>1,'msg'=>''];
		if($refund_money == 0) return ['status'=>1,'msg'=>''];
        //代付订单退款，推给实际支付人
        //如果是代付则退回代付账号,不可以用orderid[]
        $payorder = Db::name('payorder')->where('aid',$order['aid'])->where('ordernum',$order['ordernum'])->find();
        $order['totalprice'] = $payorder['money'];
        if(strpos($order['ordernum'],'_')){ //合并支付
            $ordernum = explode('_',$order['ordernum'])[0];
            $payorder = Db::name('payorder')->where('aid',$order['aid'])->where('ordernum',$ordernum)->find();
            if($payorder['status']==1){ //是合并支付的
                $order['totalprice'] = $payorder['money'];
                $order['ordernum'] = $ordernum;
            }
        }
        if($payorder['paymid']>0){
            $payorderP = Db::name('payorder')->where('status',1)->where('pid',$payorder['id'])->find();
            $mid = $payorderP['mid'];
//            $order['platform'] = $payorderP['platform'];
            $order['ordernum'] = $payorderP['ordernum'];
            $order['totalprice'] = $payorderP['money'];
            $remark = '代付订单退款';
        }else{
            $mid = $order['mid'];
            $remark = '订单退款';
        }
        if(getcustom('member_create_child_order')){
           if($payorder['pmid']) $mid = $payorder['pmid'];
        }
        if($paytype == 0){
            $rs = ['status' =>1,'msg'=>'现金退款成功'];
        }
		if($paytype == 1){
            //aid和mid重新定义
            $aid2 = $order['aid'];$mid2 = $mid;
            $addmoney_params = [];//其他参数
            if(getcustom('scoreshop_otheradmin_buy')){
                //如果扣除的是其他平台用户积分
                if($order['othermid']){
                    $aid2 = $order['otheraid'];$mid2 = $order['othermid'];
                    $appinfo = Db::name('admin_setapp_wx')->where('aid',$order['aid'])->field('id,nickname')->find();
                    if($appinfo && !empty($appinfo['nickname'])){
                        $remark = $appinfo['nickname'].'订单'.$order['ordernum'].'退款返还';
                    }else{
                        $set = Db::name('admin_set')->where('aid',$order['aid'])->field('name')->find();
                        if($set && !empty($set['name'])){
                            $remark = $set['name'].'订单'.$order['ordernum'].'退款返还';
                        }
                    }
                    $addmoney_params['optaid'] = $order['aid'];
                }
            }
			$rs = \app\commons\Member::addmoney($aid2,$mid2,$refund_money,$remark.' '.$reason,0,'','',$addmoney_params);
		}

		if($paytype == 2){
			if(getcustom('pay_money_combine')){
				//处理余额组合支付退款
                $refund_money = \app\customs\OrderCustom::deal_refund_combine2($refund_money,$order,$payorder,$params,'wxpay');
            }
            if($refund_money>0){
                $rs = \app\commons\Wxpay::refund($order['aid'],$order['platform'],$order['ordernum'],$order['totalprice'],$refund_money,$reason,$order['bid'],$payorder);
            }else{
                $rs = ['status'=>1,'msg'=>''];
            }
            if(getcustom('pay_money_combine')){
                //处理余额组合支付退款
                $res3 = \app\customs\OrderCustom::deal_refund_combine3($refund_money,$order,$payorder,$params,'wxpay',$mid,$rs,$paytype,$remark,$reason);
                if($res3['status'] != 1){
                    if(!$res3 || $res3['status'] !=1){
                        $msg = $res3 && $res3['msg']?$res3['msg']:'退款错误';
                        return ['status'=>0,'msg'=>$msg];
                    }
                }
            }
		}
		if($paytype == 3 || ($paytype>=302 && $paytype<=330)){
			if(getcustom('pay_money_combine')){
				//处理余额组合支付退款
                $refund_money = \app\customs\OrderCustom::deal_refund_combine2($refund_money,$order,$payorder,$params,'alipay');
            }
            if($refund_money>0){
				$rs = \app\commons\Alipay::refund($order['aid'],$order['platform'],$order['ordernum'],$order['totalprice'],$refund_money,$reason,$order['bid']);
			}else{
            	$rs = ['status'=>1,'msg'=>''];
            }
			if(getcustom('pay_money_combine')){
            	//处理余额组合支付退款
                $res3 = \app\customs\OrderCustom::deal_refund_combine3($refund_money,$order,$payorder,$params,'alipay',$mid,$rs,$paytype,$remark,$reason);
                if($res3['status'] != 1){
                	if(!$res3 || $res3['status'] !=1){
	                    $msg = $res3 && $res3['msg']?$res3['msg']:'退款错误';
	                    return ['status'=>0,'msg'=>$msg];
	                }
                }
            }
		}
		 if($paytype == 71){
			$rs = \app\commons\Member::addcredit2($order['aid'],$mid,$refund_money,$remark.' '.$reason);
		}
		if($paytype == 4){
			$rs = ['status'=>1,'msg'=>''];
		}
		//转账汇款
        if($paytype == 5){
            $rs = ['status'=>1,'msg'=>''];
        }
		if($order['paytypeid'] == 11){ //百度小程序
			$rs = \app\commons\Baidupay::refund($order['aid'],$order['mid'],$order['ordernum'],$order['paynum'],$order['totalprice'],$refund_money,$reason);
		}
		if($order['paytypeid'] == 12){ //头条小程序
			$rs = \app\commons\Ttpay::refund($order['aid'],$order['ordernum'],$order['totalprice'],$refund_money,$reason);
		}
		if($order['paytypeid'] == 22){ //云收银
			$rs = \app\commons\Yunpay::refund($order['aid'],$order['platform'],$order['ordernum'],$order['totalprice'],$refund_money,$reason);
		}
		if($order['paytypeid'] == 23){
			$rs = \app\commons\Qmpay::refund($order['aid'],$order['platform'],$order['ordernum'],$order['totalprice'],$refund_money,$reason);
		}
		if($order['paytypeid'] == 24){
			$rs = \app\commons\Qmpay::refund2($order['aid'],$order['platform'],$order['ordernum'],$order['totalprice'],$refund_money,$reason);
		}
		if($order['paytypeid'] == 51){  //paypal
			$rs = \app\customs\PayPal::refund($order['aid'],$order['platform'],$order['ordernum'],$order['totalprice'],$refund_money,$reason);
		}
        if($order['paytypeid'] == 62){  //汇付     ($order['aid'],$order['platform'],$order['ordernum'],$order['totalprice'],$refund_money,$reason
            $huifu = new \app\customs\Huifu([],$order['aid'],$order['bid'],$mid,$reason,$order['ordernum'],$order['totalprice']);
            $rs = $huifu->refund($refund_money,$payorder);
        }
	  if($paytype == 75){
		    $payorder = Db::name('payorder')->where('aid',$order['aid'])->where('ordernum',$order['ordernum'])->find();
            $rs = \app\commons\Sdbpay::refund($order['aid'],$order['platform'],$order['ordernum'],$order['totalprice'],$refund_money,$reason,$payorder['paynum']);
		}
		
		if($paytype == 76){
		    $payorder = Db::name('payorder')->where('aid',$order['aid'])->where('ordernum',$order['ordernum'])->find();
            $rs = \app\commons\Sdbpay::refund($order['aid'],$order['platform'],$order['ordernum'],$order['totalprice'],$refund_money,$reason,$payorder['paynum']);
		}
        //信用额度支付
        if($paytype == 38){
            $rs = \app\commons\Member::addOverdraftMoney($order['aid'],$mid,$refund_money,$remark.' '.$reason);
        }
        if($order['paytypeid'] == 60){ //视频号
            $rs = ['status'=>1,'msg'=>''];
        }
		if($order['paytypeid'] == 60){ //视频号
			$rs = ['status'=>1,'msg'=>''];
		}
		//新增退款金额和时间，用于统计
        $after_refund_money =    $payorder['refund_money'] + $refund_money;
		if($after_refund_money > $payorder['money']){
            $after_refund_money =  $payorder['money'];
        }
		$refund_update  =[
		    'refund_money' => $after_refund_money,
            'refund_time' => time()
        ];
        Db::name('payorder')->where('aid',$order['aid'])->where('id',$payorder['id'])->update($refund_update);
        if(getcustom('yx_buy_fenhong')){
            if($order['paytypeid'] !=1){
                \app\customs\BuyFenhong::refundSubScoreWeight($payorder);
            }
        }
		return $rs;
	}
	//订单收货
	public static function collect($order,$type='shop',$commission_mid = 0){
		$aid = $order['aid'];
		$mid = $order['mid'];
        $platformMoney = 0;
        $business_lirun = 0;
        if(getcustom('yx_jidian',$aid) && $order['bid']) {
            $jidian_set = Db::name('jidian_set')->where('aid', $aid)->where('bid', $order['bid'])->find();
            $paygive_scene = explode(',',$jidian_set['paygive_scene']);
        }
        $businessDkScore = $businessDkMoney = 0;
		if($type == 'shop'){
            if(getcustom('supply_zhenxin',$aid)){
                //如果是甄新汇选商品，需要先请求接口完成订单
                if($order['issource'] && $order['source'] == 'supply_zhenxin'){
                    if($order['status'] != 2){
                        return ['status'=>0,'msg'=>'订单状态不符'];
                    }
                    $orderconfirm = \app\customs\SupplyZhenxinCustom::orderconfirm($aid,$order['bid'],$order['sordernum']);
                    if(!$orderconfirm ||  $orderconfirm['status'] != 1){
                        $msg = $orderconfirm && $orderconfirm['msg']?$orderconfirm['msg']:'确认收货失败';
                        return ['status'=>0,'msg'=>$msg];
                    }
                }
            }
			if($order['fromwxvideo'] == 1){
				\app\commons\Wxvideo::deliveryrecieve($order['id']);
			}

			if(getcustom('cefang',$aid) && $aid==2){ //定制1 订单对接 同步到策方
			    $order2 = $order;
			    $order2['status'] = 3;
				\app\customs\Cefang::api($order2);
			}

			if(getcustom('active_coin',$aid)){
			    //先发放激活币
                self::giveActiveCoin($aid,$order);
            }
            if(getcustom('member_commission_max',$aid) && getcustom('add_commission_max',$aid)){
                //先发放佣金上限
                \app\commons\Order::giveCommissionMax($aid,$order);
            }
            if(getcustom('active_score',$aid)){
			    //先发放让利积分
                self::giveActiveScore($aid,$order);
            }


            $oglist = Db::name('shop_order_goods')->where('aid',$aid)->where('orderid',$order['id'])->select()->toArray();
            if($order['bid']!=0 && $order['paytypeid'] !=4){//入驻商家的货款
				$totalnum = 0;
				foreach($oglist as $og){
					$totalnum += $og['num'];
				}

				$totalcommission = 0;
				$og_business_money = false;
				$totalmoney = 0;
				$all_cost_price = 0;
				$lirun_cost_price = 0;
				$total_cost_price = 0;
                $total_activecoin = 0;
                //扣除返现比例
                $queue_feepercent_type = 0;
                $queue_feepercent_allmoney = 0;
                if(getcustom('yx_queue_free',$aid)){
                    $queue_free_set = Db::name('queue_free_set')->where('aid',$order['aid'])->where('bid',0)->find();
                    $b_queue_free_set = Db::name('queue_free_set')->where('aid',$order['aid'])->where('bid',$order['bid'])->find();
                    $queue_free_set['order_types'] = explode(',',$queue_free_set['order_types']);
                    if($queue_free_set && $queue_free_set['status']==1 && $b_queue_free_set['status']==1 && in_array('all',$queue_free_set['order_types']) || in_array('shop',$queue_free_set['order_types'])){
                        if($queue_free_set['feepercent_type'] == 1){
                            $queue_feepercent_type = 1;
                        }
                    }
                }

				foreach($oglist as $og){
					//if($og['iscommission']) continue;
					if($og['parent1'] && $og['parent1commission'] > 0){
						$totalcommission += $og['parent1commission'];
					}
					if($og['parent2'] && $og['parent2commission'] > 0){
						$totalcommission += $og['parent2commission'];
					}
					if($og['parent3'] && $og['parent3commission'] > 0){
						$totalcommission += $og['parent3commission'];
					}
                    if($og['parent4'] && $og['parent4commission'] > 0){
                        $totalcommission += $og['parent4commission'];
                    }
                    //等级价格极差分销
                    $commissionJicha = Db::name('member_commission_record')->where(['aid' => $order['aid'], 'frommid' => $order['mid'], 'orderid' => $order['id'], 'ogid' => $og['id'], 'type' => 'shop'])
                        ->whereIn('status', [0,1])//佣金可能发了，可能还没发
                        ->whereLike('remark',"%购买商品差价")->sum('commission');
                    if($commissionJicha > 0) $totalcommission += $commissionJicha;

					if(!is_null($og['business_total_money'])) {
						$og_business_money = true;
						$totalmoney += $og['business_total_money'];
					}
					if(getcustom('business_deduct_cost',$aid)){
						if(!empty($og['cost_price']) && $og['cost_price']>0){
							if($og['cost_price']<=$og['sell_price']){
								$all_cost_price += $og['cost_price'];
							}else{
								$all_cost_price += $og['sell_price'];
							}
						}
					}
                    if(getcustom('business_agent',$aid)){
                        if(!empty($og['cost_price']) && $og['cost_price']>0){
                            $lirun_cost_price += ($og['cost_price']*$og['num']);
                        }
                    }
                    if(getcustom('business_fee_type',$aid)){
                        if(!empty($og['cost_price']) && $og['cost_price']>0){
                            $total_cost_price += ($og['cost_price']*$og['num']);
                        }
                    }
                    if(getcustom('active_coin',$aid)){
                        $total_activecoin = bcadd($total_activecoin,$og['activecoin'],2);
                    }
                    if(getcustom('yx_queue_free',$aid)){
                        $product = Db::name('shop_product')->where('id',$og['proid'])->where('aid',$order['aid'])->where('bid',$order['bid'])->find();

                        if($product['queue_free_status'] == 1){
                            $queue_feepercent_allmoney += $og['real_totalprice'];
                        }
                    }
				}
				$binfo = Db::name('business')->where('aid',$aid)->where('id',$order['bid'])->find();
				$bset = Db::name('business_sysset')->where('aid',$aid)->find();
				if($bset['commission_kouchu'] == 0){ //不扣除佣金
					$totalcommission = 0;
				}
                if(getcustom('business_agent',$aid)){
                    $business_lirun = $order['totalprice']-$order['refund_money']-$lirun_cost_price;
                }
                $scoredkmoney = 0;
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
                if(getcustom('business_toaccount_type',$aid)){
                    $hastoaccount = true;//实际到账单独设置权限
                    $addDecmoney = 0;//抵扣货款
                    //查询权限组
                    $admin_user = db('admin_user')->where('aid',$aid)->where('isadmin','>',0)->field('auth_type,auth_data')->find();
                    //如果开启了设计组件分类多选
                    if($admin_user['auth_type'] != 1){
                        if($admin_user['groupid']){
                            $admin_user['auth_data'] = Db::name('admin_user_group')->where('id',$admin_user['groupid'])->value('auth_data');
                        }
                        $admin_auth = json_decode($admin_user['auth_data'],true);
                        if(!in_array('BusinessToaccountType,BusinessToaccountType',$admin_auth)){
                            $hastoaccount = false;
                        }
                    }
                    //计算实际到账 0：默认 1、按销售价 2、按市场价 3、按成本价
                    if($binfo['toaccount_type']<1 || $binfo['toaccount_type']>3) $hastoaccount = false;
                    $toaccountmoney = 0;//实际到账
                    if($hastoaccount){
                        foreach($oglist as $og){
                            if($binfo['toaccount_type'] == 1){
                                $toaccountmoney += $og['sell_price']*$og['num'];
                            }else if($binfo['toaccount_type'] == 2){
                                $toaccountmoney += $og['market_price']*$og['num'];
                            }else if($binfo['toaccount_type'] == 3){
                                $toaccountmoney += $og['cost_price']*$og['num'];
                            }
                        }
                        //商品独立费率
                        if($og_business_money) {
                        	$toaccountmoney  = $toaccountmoney*(100- $binfo['feepercent']) * 0.01;
                        	$toaccountmoney2 = $totalmoney;//原到账金额
                        }else{
                        	$toaccountmoney2 = $order['product_price'];//原到账金额
                        }
                        //如果实际到账金额小于等于原到账金额，则重置原到账金额等于实际到账金额
                        if($toaccountmoney<=$toaccountmoney2){
                            $toaccountmoney2 = $toaccountmoney>=0?$toaccountmoney:0;
                        }else{
                            if(getcustom('member_goldmoney_silvermoney')){
                                $tocha = $toaccountmoney-$toaccountmoney2;//计算实际差额
                                $goldsilvermoneydec = $order['goldmoneydec']+$order['silvermoneydec'];//计算金银值抵扣部分
                                if($tocha>$goldsilvermoneydec){
                                    $addDecmoney = $goldsilvermoneydec;//抵扣货款
                                }else{
                                    $addDecmoney = $tocha;//抵扣货款
                                }
                                //原到账金额加上抵扣货款
                                $toaccountmoney2 += $addDecmoney;
                                $toaccountmoney2 = $toaccountmoney2>=0?$toaccountmoney2:0;
                            }
                        }
                        //商品独立费率
                        if($og_business_money) {
                        	$totalmoney = $toaccountmoney2;
                        }else{
                        	$order['product_price'] = $toaccountmoney2;
                        }
                    }
                }
				//商品独立费率
				if($og_business_money) {
                    $totalmoney = $totalmoney + $order['freight_price'] - $totalcommission - $order['refund_money'] - $scoredkmoney;
                    $platformMoney = $order['totalprice']-$totalmoney - $order['refund_money'];
				} else {
					$leveldkmoney = $order['leveldk_money'] ?? 0;
					if($bset['leveldk_kouchu'] == 0){ //扣除积分抵扣
						$leveldkmoney = 0;
					}

					$totalmoney = $order['product_price'] + $order['freight_price'] - $order['coupon_money'] - $order['manjia_money'] - $order['discount_money_admin'] - $order['refund_money'] - $totalcommission - $scoredkmoney - $leveldkmoney;
					if($totalmoney > 0){
						if(getcustom('business_deduct_cost',$aid)){
                        	if($binfo && $binfo['deduct_cost'] == 1){
	                        	//扣除成本
                                $platformMoney = ($totalmoney-$all_cost_price)*$binfo['feepercent']/100;
			                }else{
                                $platformMoney = $totalmoney * $binfo['feepercent'] * 0.01;
			                }
		                }else{
                            $platformMoney = $totalmoney * $binfo['feepercent'] * 0.01;
		                }
                        if(getcustom('business_fee_type',$aid)){
                            if($bset['business_fee_type'] == 1){
                                //多商户结算按销售价
                                $platformMoney = ($order['totalprice']-$order['freight_price']) * $binfo['feepercent'] * 0.01;
                            }elseif($bset['business_fee_type'] == 2){
                                //多商户结算按销售价
                                $platformMoney = $total_cost_price * $binfo['feepercent'] * 0.01;
                            }
                        }
                        $totalmoney = $totalmoney - $platformMoney;
					}
				}
				if(getcustom('active_coin',$aid)){
                    //$totalmoney = bcsub($totalmoney,$total_activecoin,2);
                }

				if($order['paytypeid']==4){
					$totalmoney = $totalmoney - $order['totalprice'];
				}
                if(getcustom('yx_queue_free',$aid)){
                    if($queue_feepercent_type == 1 && $queue_feepercent_allmoney > 0 && $b_queue_free_set['rate_back'] > 0){
                        $totalmoney = $totalmoney - $queue_feepercent_allmoney * $b_queue_free_set['rate_back'] * 0.01;
                    }
                }

				$isbusinesspay = Db::name('payorder')->where('aid',$aid)->where('ordernum',$order['ordernum'])->value('isbusinesspay');
				if(!$isbusinesspay){
					if($totalmoney < 0){
						$bmoney = Db::name('business')->where('aid',$aid)->where('id',$order['bid'])->value('money');
						if($bmoney + $totalmoney < 0){
							return ['status'=>0,'msg'=>'操作失败,商家余额不足'];
						}
					}
					//商家货款
					\app\commons\Business::addmoney($aid,$order['bid'],$totalmoney,'货款，订单号：'.$order['ordernum'],true,$type,$order['ordernum'],['platformMoney'=>$platformMoney,'business_lirun'=>$business_lirun]);
				}else{
					//商家推荐分成
					if($totalmoney > 0){
						if(getcustom('business_agent',$aid)){
							\app\commons\Business::addparentcommission2($aid,$order['bid'],$totalmoney,$platformMoney,$business_lirun);
						}else{
							\app\commons\Business::addparentcommission($aid,$order['bid'],$totalmoney);
						}
					}
                    if(getcustom('business_toaccount_type',$aid)){
                    	$payorder = Db::name('payorder')->where('aid',$aid)->where('ordernum',$order['ordernum'])->find();
                        //不是随行付付款，且有实际到账权限，且单独设置，且抵扣货款大于0
                        if($payorder && !$payorder['issxpay'] && $hastoaccount && $addDecmoney>0){
                            //抵扣货款乘以抽成
                            $addDecmoney = $addDecmoney*$binfo['feepercent'] * 0.01;
                            $addDecmoney = round($addDecmoney,2);
                            if($addDecmoney>0){
                                //补发抵扣货款
                                \app\commons\Business::addmoney($aid,$order['bid'],$addDecmoney,'补发货款，订单号：'.$order['ordernum'],false,$type,$order['ordernum']);
                            }
                        }
                    }
				}
                //店铺加销量
                Db::name('business')->where('aid',$aid)->where('id',$order['bid'])->inc('sales',$totalnum)->update();
                //Db::name('shop_order_goods')->where('aid',$aid)->where('orderid',$order['id'])->update(['iscommission' => 1]);
                if(getcustom('yx_jidian',$aid)){
                    //集点
                    if($jidian_set && in_array('shop',$paygive_scene) && $jidian_set['status'] == 1 && time() >= $jidian_set['starttime'] && time() <= $jidian_set['endtime']){
                        //执行时此笔订单还没收货
                        \app\commons\System::getOrderNumFromJidian($aid,$order['bid'],$jidian_set,$order['mid'],1,true);
                    }
                }

                if(getcustom('pay_yuanbao',$aid)){
                    //元宝支付
                    //查询商家
                    $business = Db::name('business')->where('id',$order['bid'])->field('mid')->find();
                    if($business && $business['mid']>0){
                        //查询用户是否存在
                        $count_member = Db::name('member')
                            ->where('id',$business['mid'])
                            ->count();
                        if($count_member){
                            //给商家用户发元宝
                            \app\commons\Member::addyuanbao($order['aid'],$business['mid'],$order['total_yuanbao'],'订单:'.$order['ordernum'].'完成发放');
                        }
                    }
                }
				if(getcustom('business_moneypay',$aid) && in_array($order['paytypeid'],[2,3,12,13])){ //多商户设置的消费送积分
					$bset = Db::name('business_sysset')->where('aid',$aid)->find();
					$givescore = floor($order['totalprice'] / $bset['scorein_money']) * $bset['scorein_score'];
					if($givescore > 0){
						$res = \app\commons\Member::addscore($aid,$order['mid'],$givescore,'消费送'.t('积分'));
						if($res && $res['status'] == 1){
							//记录消费赠送积分记录
							\app\commons\Member::scoreinlog($aid,$order['bid'],$order['mid'],'shop',$order['id'],$order['ordernum'],$givescore,$order['totalprice']);
						}
					}
				}

				if(getcustom('business_canuseplatcoupon',$aid) && $order['coupon_money'] > 0 && $order['coupon_rid'] > 0){
					$couponrecord = Db::name('coupon_record')->where('id',$order['coupon_rid'])->find();
					if($couponrecord && $couponrecord['bid'] == 0){
						$businessuserecord = [];
						$businessuserecord['aid'] = $order['aid'];
						$businessuserecord['bid'] = $order['bid'];
						$businessuserecord['mid'] = $order['mid'];
						$businessuserecord['ordertype'] = $type;
						$businessuserecord['orderid'] = $order['id'];
						$businessuserecord['couponrid'] = $order['coupon_rid'];
						$businessuserecord['couponid'] = $couponrecord['couponid'];
						$businessuserecord['couponname'] = $couponrecord['couponname'];
						$businessuserecord['couponmoney'] = $couponrecord['money'];
						$businessuserecord['decmoney'] = $order['coupon_money'];
						$businessuserecord['status'] = 1;
						$businessuserecord['createtime'] = time();
						Db::name('coupon_businessuserecord')->insert($businessuserecord);
						Db::name('business')->where('id',$order['bid'])->inc('couponmoney',$order['coupon_money'])->update();
					}
				}
            }
            //赠积分
            if($order['givescore'] > 0){
				if($order['bid'] > 0){
					\app\commons\Business::addmemberscore($aid,$order['bid'],$order['mid'],$order['givescore'],'购买产品赠送'.t('积分'));
				}else{
					\app\commons\Member::addscore($aid,$order['mid'],$order['givescore'],'购买产品赠送'.t('积分'));
				}
            }
            if(getcustom('member_commission_max',$aid)){
                //送佣金上限
                if($order['give_commission_max'] > 0){
                    \app\commons\Member::addcommissionmax($aid,$order['mid'],$order['give_commission_max'],'购买商品赠送'.t('佣金上限'),'shop',$order['id']);
                }
            }
            //赠提现积分
            if(getcustom('commission_duipeng_score_withdraw',$aid)){
                if($order['give_withdraw_score'] > 0){
                    \app\commons\Member::add_commission_withdraw_score($aid,$order['mid'],$order['give_withdraw_score'],'购买产品赠送提现积分');
                }
                if($order['give_parent1_withdraw_score'] > 0 && $order['give_parent1'] >0){
                    \app\commons\Member::add_commission_withdraw_score($aid,$order['give_parent1'],$order['give_parent1_withdraw_score'],'推荐下级购买产品赠送提现积分',$order['mid']);
                }
            }
            if(getcustom('product_givetongzheng',$aid)){
                if($order['givetongzheng']>0){
                    $release_bili = Db::name('admin_set')->where('aid',$order['aid'])->value('tongzheng_release_bili');
                    $tz_log = [];
                    $tz_log['aid'] = $order['aid'];
                    $tz_log['mid'] = $order['mid'];
                    $tz_log['orderid'] = $order['id'];
                    $tz_log['tongzheng'] = $order['givetongzheng'];
                    $tz_log['release_bili'] = $release_bili;
                    $tz_log['remain'] = $order['givetongzheng'];
                    $tz_log['createtime'] = time();
                    Db::name('tongzheng_order_log')->insert($tz_log);
                }
            }

            if(getcustom('everyday_hongbao',$aid)) {
                $totalHongbao = 0;
                foreach($oglist as $og){
                    if($og['ishongbao'] || $og['hongbaoEdu'] <= 0) continue;
                    \app\commons\Member::addHongbaoEverydayEdu($aid,$order['mid'],$og['hongbaoEdu'], '购买增加红包额度', $og['id']);
                }
                Db::name('shop_order_goods')->where('aid',$aid)->where('orderid',$order['id'])->update(['ishongbao' => 1]);
            }

            if(getcustom('discount_code_zhongchuang',$aid)){
                if($order['discount_code_zc']){
                    //中创推送订单数据
                    $postzcog = [];
                    foreach($oglist as $og){
                        $postzcog[] = [
                            'proid'=>$og['proid'],
                            'name'=>$og['name'],
                            'pic'=>$og['pic'],
                            'procode'=>$og['procode'],
                            'ggid'=>$og['ggid'],
                            'ggname'=>$og['ggname'],
                            'num'=>$og['num'],
                            'sell_price'=>$og['sell_price'],
                            'totalprice'=>$og['totalprice'],
                        ];
                    }
                    $postzc =[
                        'invitecode' => $order['discount_code_zc'],
                        'order'=> [
                            'ordernum' => $order['ordernum'],
                            'totalprice' => $order['totalprice'],
                            'product_price' => $order['product_price'],
                            'freight_price' => $order['freight_price'],
                            'scoredk_money' => $order['scoredk_money'],
                            'leveldk_money' => $order['leveldk_money'],
                            'manjian_money' => $order['manjian_money'],
                            'coupon_money' => $order['coupon_money'],
                            'discount_money_admin' => $order['discount_money_admin'],
                            'cuxiao_money' => $order['cuxiao_money'],
                            'createtime' => $order['createtime'],
                            'linkman' => $order['linkman'],
                            'tel' => $order['tel'],
                            'area' => $order['area'],
                            'address' => $order['address'],
                            'paytime' => $order['paytime'],
                            'collect_time' => $order['collect_time'],
                        ],
                        'orderGoods'=> $postzcog
                    ];
                    $url ='https://zckl.zhoming.top/imcore/api/mall/getUserInfo';
                    $res = curl_post($url,jsonEncode($postzc),0,array('Content-Type: application/json'));
                    $res = json_decode($res,true);
                    \think\facade\Log::write('中创rs:'.jsonEncode($res));
                }
            }

            if(getcustom('member_levelup_givechild',$aid)){
                foreach($oglist as $og){
                $product = Db::name('shop_product')->where('id',$og['proid'])->field('id,give_team_levelup,team_levelup_data')->find();
                    if($product['give_team_levelup'] == 1){
                        \app\commons\Member::addMemberLevelupNum($aid,$mid,$product['team_levelup_data']);
                    }
                }
            }
            if(getcustom('consumer_value_add',$aid)){
                //送绿色积分
                if($order['give_green_score'] > 0){
                    \app\commons\Member::addgreenscore($aid,$order['mid'],$order['give_green_score'],'购买商品赠送'.t('绿色积分'),'shop_order',$order['id'],0,$order['give_green_score']);
                }
                //放入奖金池
                if($order['give_bonus_pool'] > 0){
                    \app\commons\Member::addbonuspool($aid,$order['mid'],$order['give_bonus_pool'],'购买商品赠送'.t('奖金池'),'shop_order',$order['id'],0,$order['give_green_score']);
                }
            }
			//查询购买用户
			$member = Db::name('member')->where('id',$order['mid'])->find();
			if($oglist && $member){
				//购物返现
                $bid = $order['bid'];
                if(getcustom('cashback_yongjin',$aid)){
                    //定制总后台添加多商户商品
                    $bid = 0;
                }
                $cashbacklist = Db::name('cashback')->where('aid',$aid)->where('bid',$bid)->where('starttime','<',$order['paytime'])->where('endtime','>',$order['paytime'])->order('sort desc')->select()->toArray();
				$allreal_totalprice = 0;//实际消费

				//返现类型 1、余额 2、佣金 3、积分 小数位数
				$money_weishu = 2;$commission_weishu = 2;$score_weishu = 0;
		        if(getcustom('member_money_weishu',$aid)){
                    $money_weishu = Db::name('admin_set')->where('aid',$aid)->value('member_money_weishu');
                }
		        if(getcustom('fenhong_money_weishu',$aid)){
                    $commission_weishu = Db::name('admin_set')->where('aid',$aid)->value('fenhong_money_weishu');
                }
                if(getcustom('score_weishu',$aid)){
		            $score_weishu = Db::name('admin_set')->where('aid',$aid)->value('score_weishu');
		        }
				foreach($oglist as $og){
					$real_totalprice = $og['real_totalprice'];
                    if(getcustom('money_dec',$aid)){
                        //如果是余额抵扣，则要加上余额部分
                        if($og['dec_money'] && $og['dec_money'] >0){
                            $real_totalprice = $og['real_totalprice']+$og['dec_money'];
                        }
                    }
                    $allreal_totalprice += $real_totalprice;

					$product = Db::name('shop_product')->where('id',$og['proid'])->field('id,cid')->find();
					if($product && $cashbacklist){
						foreach($cashbacklist as $v){

							$gettj = explode(',',$v['gettj']);
							if(!in_array('-1',$gettj) && !in_array($member['levelid'],$gettj)){ //不是所有人
								continue;
							}

							if($v['fwtype']>=3){
								//其他类型不适应
								continue;
							}

							if($v['fwtype']==2){//指定商品可用
								$productids = explode(',',$v['productids']);
								if(!in_array($product['id'],$productids)){
									continue;
								}
							}

							if($v['fwtype']==1){//指定类目可用
								$categoryids = explode(',',$v['categoryids']);
								$cids = explode(',',$product['cid']);
								$clist = Db::name('shop_category')->where('pid','in',$categoryids)->select()->toArray();
								foreach($clist as $vc){
									$categoryids[] = $vc['id'];
									$cate2 = Db::name('shop_category')->where('pid',$vc['id'])->find();
									$categoryids[] = $cate2['id'];
								}
								if(!array_intersect($cids,$categoryids)){
									continue;
								}
							}

							//如果返现利率大于0
							if($v['back_ratio']>0){
								//计算返现
								$back_price = $v['back_ratio']*$real_totalprice/100;

								//返现类型 1、余额 2、佣金 3、积分
								if($v['back_type'] == 1 ){
									$back_price = dd_money_format($back_price,$money_weishu);
								}else if($v['back_type']== 2){
									$back_price = dd_money_format($back_price,$commission_weishu);
								}else if($v['back_type'] == 3){
									$back_price = dd_money_format($back_price,$score_weishu);
								}

                                $return_type = 0;//发放类型 0：立即发放 1、自定义 2、阶梯
                                if(getcustom('yx_cashback_time',$aid) || getcustom('yx_cashback_stage',$aid)){
                                    $return_type = $v['return_type'];
                                }

                                $og['ordertype'] = 'shop';
                                //记录参与的会员
                                $cashback_member_check = Db::name('cashback_member')->where('aid',$order['aid'])->where('mid',$order['mid'])->where(['cashback_id'=>$v['id'],'pro_id'=>$og['proid'],'type'=>'shop'])->find();
                                if(!$cashback_member_check){
                                    $cashback_member = [];
                                    $cashback_member['aid'] = $order['aid'];
                                    $cashback_member['mid'] = $order['mid'];
                                    $cashback_member['cashback_id'] = $v['id'];
                                    $cashback_member['pro_id'] = $og['proid'];
                                    $cashback_member['pro_num'] = $og['num'];
                                    $cashback_member['cashback_money_max'] = $v['goods_multiple_max'] > 0 ? $og['sell_price'] * $v['goods_multiple_max'] * $og['num']:0;
                                    //$cashback_member['cashback_money']   = $back_price;
                                    $cashback_member['back_type']          = $v['back_type'];
                                    $cashback_member['type']               = 'shop';
                                    $cashback_member['create_time']        = time();
                                    $insert = Db::name('cashback_member')->insert($cashback_member);
                                    $cashback_member_check = Db::name('cashback_member')->where('aid',$order['aid'])->where('mid',$order['mid'])->where(['cashback_id'=>$v['id'],'pro_id'=>$og['proid'],'type'=>'shop'])->find();
                                }else{
                                    Db::name('cashback_member')->where('id',$cashback_member_check['id'])->inc('pro_num',$og['num'])->update();
                                    $cashback_money_max     = $v['goods_multiple_max'] > 0 ? $og['sell_price'] * $v['goods_multiple_max']* $og['num']:0;
                                    $cashback_member['cashback_money_max']  = $cashback_member_check['cashback_money_max'] + $cashback_money_max;
                                    Db::name('cashback_member')->where('id',$cashback_member_check['id'])->update($cashback_member);
                                }

                                //开启限额
                                $cashback_max = 0;
                                if(getcustom('cashback_max',$aid)){
                                    $cashback_max = 1;
                                }
                                //开启选择受益人
                                $cashback_receiver = 0;
                                if(getcustom('cashback_receiver',$aid)){
                                    $cashback_receiver = 1;
                                }

                                if(!$return_type){
                                    //受益人限额仅限单个商品可用
                                    if($cashback_receiver || $cashback_max){
                                        if($back_price){
                                            //判定受益人的方式1
                                            if($v['receiver_type'] == 1){
                                                //判定是否限额
                                                if($v['back_type'] == 1 ){
                                                    $cashback_num = $cashback_member_check['cashback_money'];
                                                }else if($v['back_type'] == 2){
                                                    $cashback_num = $cashback_member_check['commission'];
                                                }else if($v['back_type'] == 3){
                                                    $cashback_num = $cashback_member_check['score'];
                                                }
                                                if($v['goods_multiple_max'] > 0 &&  $cashback_member_check['cashback_money_max'] > $cashback_num){
                                                    //最大可追加金额
                                                    $cashback_money_max = $cashback_member_check['cashback_money_max'] - $cashback_num;
                                                    if($cashback_money_max < $back_price){
                                                        $back_price = $cashback_money_max;
                                                    }
                                                }
                                            }elseif($v['receiver_type'] ==2){//参与活动的人
                                                //查询参与活动的所有人发放佣金
                                                $res_code = self::cashbackMemerDo($order['aid'],$order['mid'],$v,$og,$back_price);
                                                $back_price = 0;
                                            }
                                        }
    					            }
    								if($back_price>0){
    									if($v['back_type'] == 1 ){
    										\app\commons\Member::addmoney($aid,$order['mid'],$back_price,$v['name']);
                                            //累计到参与人统计表
                                            Db::name('cashback_member')->where('id',$cashback_member_check['id'])->inc('cashback_money',$back_price)->update();
    									}else if($v['back_type'] == 2){
    										\app\commons\Member::addcommission($aid,$order['mid'],$order['mid'],$back_price,$v['name']);
                                            //累计到参与人统计表
                                            Db::name('cashback_member')->where('id',$cashback_member_check['id'])->inc('commission',$back_price)->update();
    									}else if($v['back_type'] == 3){
    										\app\commons\Member::addscore($aid,$order['mid'],$back_price,$v['name']);
                                            //累计到参与人统计表
                                            Db::name('cashback_member')->where('id',$cashback_member_check['id'])->inc('score',$back_price)->update();
    									}
                                        if(getcustom('yx_cashback_time',$aid) || getcustom('yx_cashback_stage',$aid)){
                                            //直接发放
                                            \app\customs\OrderCustom::deal_first_cashback($aid,$order['mid'],$back_price,$og,$v);
                                        }
                                        //写入发放日志
                                        \app\customs\OrderCustom::cashbackMemerDoLog($order['aid'],$order['mid'],$v,$og,$back_price);
    								}
                                }else{
                                    if($back_price>0){
                                        if(getcustom('yx_cashback_time',$aid) || getcustom('yx_cashback_stage',$aid)){
                                            //处理自定义第一次发放
                                            //处理受益人是参与人
                                            if($cashback_receiver == 1 && $v['receiver_type'] ==2){
                                                //查询参与活动的所有人发放佣金
                                                $res_code = self::cashbackMemerDo($order['aid'],$order['mid'],$v,$og,$back_price,$return_type);
                                            }else{
                                                \app\customs\OrderCustom::deal_first_cashback($aid,$order['mid'],$back_price,$og,$v,$return_type);
                                            }
                                        }
                                    }
                                }
							}

                            if(getcustom('yx_cashback_time_tjspeed',$aid)){
                                //给上级、上上级加速
                                if($member['pid']>0 && $real_totalprice>0){
                                    \app\customs\OrderCustom::deal_cashbackspeed($member,$real_totalprice,$v);
                                }
                            }
						}
					}

				}
				if(getcustom('yx_cashback_time_teamspeed',$aid)){
                    //团队业绩达标加速
                    if($member['pid']>0 && $allreal_totalprice>0){
                        \app\customs\OrderCustom::deal_cashbackteamspeed($member,$allreal_totalprice);
                    }
                }
			}

            //排名分红根据购买金额获取分红位置
            if(getcustom('shop_paiming_fenhong',$aid)){
                self::PaimingFenhongPoint($order);
            }
            if(getcustom('product_bonus_pool')){
                self::prodcutBonusPoolCollect($aid,$oglist,$member);
            }
			if(getcustom('invite_free',$aid)){
				if($order['is_free'] ||$member['pid']>0){
					//处理上级邀请免单及退回自己免单余额
					\app\customs\InviteFree::deal_free($member,$order);
				}
			}

			if(getcustom('yx_invite_cashback',$aid)){
				//邀请返现
				if($order && $oglist && $member){
					\app\customs\OrderCustom::deal_invitecashback2($aid,$order,$oglist,$member);
				}
			}

			//恢复会员等级
			if(getcustom('member_level_down_commission',$aid) && $member['isauto_down']==1){
				if($member['up_levelid']>0){
					$level =   Db::name('member_level')->field('id,recovery_level_proid')->where('aid',$aid)->where('id',$member['up_levelid'])->find();
					$proids = [];
					$isrecovery=false;
					$recovery_level_proid = explode(',',$level['recovery_level_proid']);
					foreach ($oglist as $og){
						if(in_array($og['proid'],$recovery_level_proid)){
							$isrecovery = true;
							break;
						}
					}
					if($isrecovery){
						\app\Common\member::recovery_level($aid,$member);
					}
				}
			}

			//增加购买次数 购买金额if(getcustom('member_tag'))
            Db::name('member')->where('aid',$aid)->where('id',$mid)->inc('buynum',1)->update();
            Db::name('member')->where('aid',$aid)->where('id',$mid)->inc('buymoney',$order['totalprice'])->update();
            //支付宝小程序交易组件订单状态同步
            if($order['platform']=='alipay' && $order['paytypeid'] == 3){
                $ordernum = $order['ordernum'];
                if(strpos($ordernum, '_')!==false){
                    $ordernum = explode('-',$ordernum)[0];
                }
                if(getcustom('alipay_plugin_trade',$aid) && $order['alipay_component_orderid']){
                    $pluginResult = \app\commons\Alipay::pluginOrderConfirm($aid,$mid,$ordernum);
                }
            }
			if($order['platform'] == 'toutiao'){
				\app\commons\Ttpay::pushorder($aid,$order['ordernum'],4);
			}
            if(getcustom('yx_team_yeji_manage',$aid)){
                self::teamYejiManage($aid,$member);
            }
            if(getcustom('yx_queue_free',$aid)){
                \app\customs\QueueFree::join($order,$type,'collect');
            }
            if(getcustom('yx_hongbao_queue_free',$aid)){
                \app\customs\HongbaoQueueFree::join($order,'shop');
            }
            if(getcustom('fenhong_jiaquan_bylevel',$aid)){
                //份数累加到会员
                \app\commons\Fenhong::updateJiaquanCopies2member($order['id']);
            }
            $score_weishu = 0;
            if(getcustom('score_weishu',$aid)){
                $score_weishu = Db::name('admin_set')->where('aid',$aid)->value('score_weishu');
                $score_weishu = $score_weishu?$score_weishu:0;
            }
            if(getcustom('reward_business_score',$aid)){
                $reward_score = dd_money_format($order['reward_business_score'],$score_weishu);
                $uinfo = Db::name('admin_user')->where('aid',$aid)->where('bid',$og['bid'])->where('isadmin',1)->find();
                if($reward_score>0 && $uinfo['mid']){
                    \app\commons\Member::addscore($aid,$uinfo['mid'],$reward_score,'店长奖励');
                }
            }

			if(getcustom('commission_withdraw_freeze',$aid)){
				$set = Db::name('admin_set')->field('jiedong_condtion,buy_proid,buypro_num')->where('aid',$aid)->find();
				if(in_array('1',explode(',',$set['jiedong_condtion']))){
					$isjiedong = false;
					$buy_proids = explode(',',str_replace('，',',',$set['buy_proid']));
					$buypro_nums = explode(',',str_replace('，',',',$set['buypro_num']));
					foreach ($oglist as $og){
						if(count($buypro_nums) > 1) {
							foreach($buy_proids as $k=>$proid){
								$pronum = $buypro_nums[$k];
								if(!$pronum) $pronum = 1;
								$buynum = Db::name('shop_order_goods')->where('aid',$aid)->where('mid',$mid)->where('proid',$proid)->where('status','in','1,2,3')->sum('num');
								if($buynum >= $pronum){
									$isjiedong = true;
								}
							}
						}else {
							$pronum = $buypro_nums[0];
							if(!$pronum) $pronum = 1;
							$buynum = 0;
							foreach($buy_proids as $k=>$proid){
								$buynum += Db::name('shop_order_goods')->where('aid',$aid)->where('mid',$mid)->where('proid',$proid)->where('status','in','1,2,3')->sum('num');
								if($buynum >= $pronum){
									$isjiedong = true;
								}
							}
						}
					}
					if($isjiedong){
						Db::name('member')->where('id',$order['mid'])->update(['iscomwithdraw_freeze'=>0]);
					}
				}
			}
			if(getcustom('mendian_upgrade',$aid)){
				$mendian_upgrade_status = Db::name('admin')->where('id',$order['aid'])->value('mendian_upgrade_status');
				//核销提成
				//$rs = \app\customs\Mendian::mendian_hexiao_ticheng($order, $this->mendian);
				if($mendian_upgrade_status){
					\app\customs\Mendian::givecommission($order);
					$mendian = Db::name('mendian')->where('id',$order['mdid'])->find();
					//门店升级
					\app\customs\Mendian::uplv($order['aid'],$mendian);

				}
			}
            if(getcustom('erp_wangdiantong',$aid)){
                //订单存在erp同步商品
                if($order['wdt_status']==1){
                    $c = new \app\customs\Wdt($order['aid'],$order['bid']);
                    $c->orderCollect($order['id']);
                }
            }

            if(getcustom('ciruikang_fenxiao',$aid)){
            	if($order['crk_givenum']>0){
            		//增加赠送发放数量
            		Db::name('member')->where('id', $order['mid'])->inc('crk_up_send_pronum',$order['crk_givenum'])->update();
            	}
                //增加商品库存
                \app\customs\CiruikangCustom::deal_ogstock3($order['aid'],$order['mid'],$order['id']);
                //处理推荐商家补贴
                \app\customs\CiruikangCustom::deal_recom_btmoney($order);
            }

            if(getcustom('yx_mangfan',$aid)){
                \app\customs\Mangfan::sendBonus($order['aid'],$order['mid'],$order['id']);
            }
            if(getcustom('member_goldmoney_silvermoney')){
                if($order['givesilvermoney']>0 || $order['givegoldmoney']>0){
                    $ShopSendSilvermoney = $ShopSendGoldmoney = true;//赠送金值银值权限
                    //平台权限
                    $admin_user = Db::name('admin_user')->where('aid',$order['aid'])->where('isadmin','>',0)->field('auth_type,auth_data')->find();
                    if($admin_user['auth_type'] !=1 ){
                        $admin_auth = !empty($admin_user['auth_data'])?json_decode($admin_user['auth_data'],true):[];
                        if(!in_array('ShopSendSilvermoney,ShopSendSilvermoney',$admin_auth)){
                            $ShopSendSilvermoney = false;
                        }
                        if(!in_array('ShopSendGoldmoney,ShopSendGoldmoney',$admin_auth)){
                            $ShopSendGoldmoney   = false;
                        }
                    }
                    if($ShopSendSilvermoney && $order['givesilvermoney'] > 0) {
                        \app\commons\Member::addsilvermoney($order['aid'],$order['mid'],$order['givesilvermoney'],'购买商品赠送'.$order['ordernum'],$order['ordernum']);
                    }
                    if($ShopSendGoldmoney && $order['givegoldmoney'] > 0) {
                        \app\commons\Member::addgoldmoney($order['aid'],$order['mid'],$order['givegoldmoney'],'购买商品赠'.$order['ordernum'],$order['ordernum']);
                    }
                }
            }

        }elseif($type=='collage'){
			if($order['bid']!=0){//入驻商家的货款

				$totalmoney      = 0;
				$totalcommission = 0;
				//if($order['iscommission']){
				if($order['parent1'] && $order['parent1commission'] > 0){
					$totalcommission += $order['parent1commission'];
				}
				if($order['parent2'] && $order['parent2commission'] > 0){
					$totalcommission += $order['parent2commission'];
				}
				if($order['parent3'] && $order['parent3commission'] > 0){
					$totalcommission += $order['parent3commission'];
				}
				//}

				$binfo = Db::name('business')->where('aid',$aid)->where('id',$order['bid'])->find();
				$bset = Db::name('business_sysset')->where('aid',$aid)->find();
				if($bset['commission_kouchu'] == 0){ //不扣除佣金
					$totalcommission = 0;
				}

				if(getcustom('yx_collage_team_in_team',$aid)){
                    $commission_teaminteam_kouchu = Db::name('business_sysset')->where('aid',$order['aid'])->value('commission_teaminteam_kouchu');
                    if($commission_teaminteam_kouchu && $commission_teaminteam_kouchu == 1){
                        //团中团奖励
                        $teaminteamlogs = Db::name('member_commission_record')->where('orderid',$order['id'])->where('isteaminteam',1)->where('type','collage')->where('commission','>',0)->where('status','>=',0)->where('status','<=',1)->where('aid',$order['aid'])->select()->toArray();
                        foreach($teaminteamlogs as $tv){
                            //$totalcommission += $tv['residue'];
                            $totalcommission += $tv['commission'];
                        }
                        unset($tv);
                    }
                }


                if(getcustom('business_agent',$aid)){
                    $lirun_cost_price = 0;
                    if($order['cost_price']>0){
                        $lirun_cost_price = $order['cost_price']*$order['num'];
                    }
                    $business_lirun = $order['totalprice']-$lirun_cost_price;
                }
                $queue_feepercent_type = 0;
                $has_yx_queue_free = 0;
                if(getcustom('yx_queue_free',$aid)){
                    $has_yx_queue_free = 1;
                }

                if(getcustom('yx_queue_free_collage',$aid) && $has_yx_queue_free){
                    $queue_free_set = Db::name('queue_free_set')->where('aid',$order['aid'])->where('bid',0)->find();
                    $b_queue_free_set = Db::name('queue_free_set')->where('aid',$order['aid'])->where('bid',$order['bid'])->find();
                    $queue_free_set['order_types'] = explode(',',$queue_free_set['order_types']);
                    if($queue_free_set && $queue_free_set['status']==1 && $b_queue_free_set['status']==1 && in_array('all',$queue_free_set['order_types']) || in_array('collage',$queue_free_set['order_types'])){
                        if($queue_free_set['feepercent_type'] == 1){
                            $queue_feepercent_type = 1;
                        }
                    }

                }

                $scoredkmoney = 0;
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
				if(!is_null($order['business_total_money'])) {
					$leveldkmoney = $order['leveldk_money'] ?? 0;
					if($bset['leveldk_kouchu'] == 0){ //扣除会员抵扣
						$leveldkmoney = 0;
					}
					$totalmoney = $order['business_total_money'] - $leveldkmoney - $scoredkmoney;
                    $platformMoney = $order['totalprice']-$totalmoney - $order['refund_money'];
                    if(getcustom('yx_queue_free_collage',$aid)){
                        $product = Db::name('collage_product')->where('id',$order['proid'])->field('queue_free_status,queue_free_rate_back')->find();
                            if($product['queue_free_status'] == 1 && $product['queue_free_rate_back']>=0){
                                $b_queue_free_set['rate_back'] =  $product['queue_free_rate_back'];
                            }
                        if($queue_feepercent_type == 1 && $totalmoney > 0 && $b_queue_free_set['rate_back'] > 0){
                            $totalmoney = $totalmoney - $totalmoney * $b_queue_free_set['rate_back'] * 0.01;
                        }
                    }
				} else {
					$leveldkmoney = $order['leveldk_money'] ?? 0;
					if($bset['leveldk_kouchu'] == 0){ //扣除积分抵扣
						$leveldkmoney = 0;
					}

					$oldtotalmoney = $totalmoney = $order['product_price'] + $order['freight_price'] - $order['coupon_money'] - $order['leader_money'] - $scoredkmoney - $leveldkmoney;

					if($totalmoney > 0){
						if(getcustom('business_deduct_cost',$aid)){
                        	if($binfo && $binfo['deduct_cost'] == 1 && $order['cost_price']>0){
                        		if($order['cost_price']<=$order['sell_price']){
									$all_cost_price = $order['cost_price'];
								}else{
									$all_cost_price = $order['sell_price'];
								}
	                        	//扣除成本
                                $platformMoney = ($totalmoney-$all_cost_price)*$binfo['feepercent']/100;
			                }else{
                                $platformMoney = $totalmoney * $binfo['feepercent'] * 0.01;
			                }
		                }else{
                            $platformMoney = $totalmoney * $binfo['feepercent'] * 0.01;
		                }

                        if(getcustom('business_fee_type',$aid)){
                            if($bset['business_fee_type'] == 1){
                                $platformMoney = ($order['totalprice']-$order['freight_price']) * $binfo['feepercent'] * 0.01;
                            }elseif($bset['business_fee_type'] == 2){
                                $platformMoney = $order['cost_price'] * $binfo['feepercent'] * 0.01;
                            }
                        }
                        $totalmoney = $totalmoney - $platformMoney;
                        //扣掉返利
                        if(getcustom('yx_queue_free_collage',$aid)){
                            $product = Db::name('collage_product')->where('id',$order['proid'])->field('queue_free_status,queue_free_rate_back')->find();
                            if($product['queue_free_status'] == 1 && $product['queue_free_rate_back']>=0){
                                $b_queue_free_set['rate_back'] =  $product['queue_free_rate_back'];
                            }
                            if($queue_feepercent_type == 1 && $totalmoney > 0 && $b_queue_free_set['rate_back'] > 0){
                                $totalmoney = $totalmoney - $oldtotalmoney * $b_queue_free_set['rate_back'] * 0.01;
                            }
                        }
					}
				}

				if($order['paytypeid']==4){
					$totalmoney = $totalmoney - $order['totalprice'];
				}
				$totalmoney -= $totalcommission;//扣除佣金
				$isbusinesspay = Db::name('payorder')->where('aid',$aid)->where('ordernum',$order['ordernum'])->value('isbusinesspay');
				if(!$isbusinesspay){
					if($totalmoney < 0){
						$bmoney = Db::name('business')->where('aid',$aid)->where('id',$order['bid'])->value('money');
						if($bmoney + $totalmoney < 0){
							return ['status'=>0,'msg'=>'操作失败,商家余额不足'];
						}
					}
					\app\commons\Business::addmoney($aid,$order['bid'],$totalmoney,'货款，拼团订单号：'.$order['ordernum'],true,$type,$order['ordernum'],['platformMoney'=>$platformMoney,'business_lirun'=>$business_lirun]);
				}else{
					//商家推荐分成
					if($totalmoney > 0){
						if(getcustom('business_agent',$aid)){
							\app\commons\Business::addparentcommission2($aid,$order['bid'],$totalmoney,$platformMoney,$business_lirun);
						}else{
							\app\commons\Business::addparentcommission($aid,$order['bid'],$totalmoney);
						}
					}
				}
				//店铺加销量
				Db::name('business')->where('aid',$aid)->where('id',$order['bid'])->inc('sales',$order['num'])->update();

				if(getcustom('business_canuseplatcoupon',$aid) && $order['coupon_money'] > 0 && $order['coupon_rid'] > 0){
					$couponrecord = Db::name('coupon_record')->where('id',$order['coupon_rid'])->find();
					if($couponrecord && $couponrecord['bid'] == 0){
						$businessuserecord = [];
						$businessuserecord['aid'] = $order['aid'];
						$businessuserecord['bid'] = $order['bid'];
						$businessuserecord['mid'] = $order['mid'];
						$businessuserecord['ordertype'] = $type;
						$businessuserecord['orderid'] = $order['id'];
						$businessuserecord['couponrid'] = $order['coupon_rid'];
						$businessuserecord['couponid'] = $couponrecord['couponid'];
						$businessuserecord['couponname'] = $couponrecord['couponname'];
						$businessuserecord['couponmoney'] = $couponrecord['money'];
						$businessuserecord['decmoney'] = $order['coupon_money'];
						$businessuserecord['status'] = 1;
						$businessuserecord['createtime'] = time();
						Db::name('coupon_businessuserecord')->insert($businessuserecord);
						Db::name('business')->where('id',$order['bid'])->inc('couponmoney',$order['coupon_money'])->update();
					}
				}
			}

			if(getcustom('yx_cashback_collage',$aid)){
				//处理返现
				\app\customs\OrderCustom::deal_collagecashback($aid,$order);
			}

			if(getcustom('collage_givescore_time',$aid)){
				//赠积分
	            if($order['givescore1'] > 0){
					\app\commons\Member::addscore($aid,$order['mid'],$order['givescore1'],'购买拼团商品赠送'.t('积分'));
	            }
            }

            if(getcustom('yx_mangfan_collage',$aid)){
                \app\customs\Mangfan::sendBonus($order['aid'],$order['mid'],$order['id'],'collage');
            }

            if(getcustom('yx_queue_free_collage',$aid)){
                \app\customs\QueueFree::join($order,$type,'collect');
            }
		}elseif($type=='cycle'){
            if($order['bid']!=0){//入驻商家的货款

                $totalmoney = 0;
                $binfo = Db::name('business')->where('aid',$aid)->where('id',$order['bid'])->find();
                $bset = Db::name('business_sysset')->where('aid',$aid)->find();

                if(getcustom('business_agent',$aid)){
                    $lirun_cost_price = 0;
                    if($order['cost_price']>0){
                        $lirun_cost_price = $order['cost_price']*$order['num'];
                    }
                    $business_lirun = $order['totalprice']-$lirun_cost_price;
                }
                $scoredkmoney = 0;
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
				if(!is_null($order['business_total_money'])) {
					$leveldkmoney = $order['leveldk_money'] ?? 0;
					if($bset['leveldk_kouchu'] == 0){ //扣除会员抵扣
						$leveldkmoney = 0;
					}
					$totalmoney = $order['business_total_money'] - $leveldkmoney - $scoredkmoney;
                    $platformMoney = $order['totalprice']-$totalmoney - $order['refund_money'];
				} else {
					$leveldkmoney = $order['leveldk_money'] ?? 0;
					if($bset['leveldk_kouchu'] == 0){ //扣除积分抵扣
						$leveldkmoney = 0;
					}
					$totalmoney = $order['product_price'] + $order['freight_price'] - $order['coupon_money'] - $order['leader_money'] - $order['discount_money_admin'] - $scoredkmoney - $leveldkmoney;
                    if($totalmoney > 0){
						if(getcustom('business_deduct_cost',$aid)){
                        	if($binfo && $binfo['deduct_cost'] == 1 && $order['cost_price']>0){
                        		if($order['cost_price']<=$order['sell_price']){
									$all_cost_price = $order['cost_price'];
								}else{
									$all_cost_price = $order['sell_price'];
								}
	                        	//扣除成本
                                $platformMoney = ($totalmoney-$all_cost_price)*$binfo['feepercent']/100;
                            }else{
                                $platformMoney = $totalmoney * $binfo['feepercent'] * 0.01;
                            }
                        }else {
                            $platformMoney = $totalmoney * $binfo['feepercent'] * 0.01;
                        }
                        if(getcustom('business_fee_type',$aid)){
                            if($bset['business_fee_type'] == 1){
                                $platformMoney = ($order['totalprice']-$order['freight_price']) * $binfo['feepercent'] * 0.01;
                            }elseif($bset['business_fee_type'] == 2){
                                $platformMoney = $order['cost_price'] * $binfo['feepercent'] * 0.01;
                            }
                        }
                        $totalmoney = $totalmoney - $platformMoney;
					}
				}

                if($order['paytypeid']==4){
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
                    \app\commons\Business::addmoney($aid,$order['bid'],$totalmoney,'货款，周期购订单号：'.$order['ordernum'],true,$type,$order['ordernum'],['platformMoney'=>$platformMoney,'business_lirun'=>$business_lirun]);
                }else{
                	//商家推荐分成
					if($totalmoney > 0){
						if(getcustom('business_agent',$aid)){
							\app\commons\Business::addparentcommission2($aid,$order['bid'],$totalmoney,$platformMoney,$business_lirun);
						}else{
							\app\commons\Business::addparentcommission($aid,$order['bid'],$totalmoney);
						}
					}
                }
                //店铺加销量
                Db::name('business')->where('aid',$aid)->where('id',$order['bid'])->inc('sales',$order['num'])->update();

				if(getcustom('business_canuseplatcoupon',$aid) && $order['coupon_money'] > 0 && $order['coupon_rid'] > 0){
					$couponrecord = Db::name('coupon_record')->where('id',$order['coupon_rid'])->find();
					if($couponrecord && $couponrecord['bid'] == 0){
						$businessuserecord = [];
						$businessuserecord['aid'] = $order['aid'];
						$businessuserecord['bid'] = $order['bid'];
						$businessuserecord['mid'] = $order['mid'];
						$businessuserecord['ordertype'] = $type;
						$businessuserecord['orderid'] = $order['id'];
						$businessuserecord['couponrid'] = $order['coupon_rid'];
						$businessuserecord['couponid'] = $couponrecord['couponid'];
						$businessuserecord['couponname'] = $couponrecord['couponname'];
						$businessuserecord['couponmoney'] = $couponrecord['money'];
						$businessuserecord['decmoney'] = $order['coupon_money'];
						$businessuserecord['status'] = 1;
						$businessuserecord['createtime'] = time();
						Db::name('coupon_businessuserecord')->insert($businessuserecord);
						Db::name('business')->where('id',$order['bid'])->inc('couponmoney',$order['coupon_money'])->update();
					}
				}
            }
        }elseif($type=='yuyue'){
			if($order['bid']!=0){//入驻商家的货款
                $bset = Db::name('business_sysset')->where('aid',$aid)->find();
				$binfo = Db::name('business')->where('aid',$aid)->where('id',$order['bid'])->find();
				if(getcustom('hmy_yuyue',$aid)){
					$totalmoney = $order['totalprice']+$order['balance_price']-$order['paidan_money'];
				}else{
					//$totalmoney = $order['product_price'];
                    //按实付金额计算
					$totalmoney = $order['totalprice'];
				}
                $paidanMoney = $platformMoney = 0;
                if($binfo['feepercent'] > 0){
                    $platformMoney = $totalmoney * $binfo['feepercent'] * 0.01;
                }
                $totalmoney = max(round($totalmoney-$paidanMoney-$platformMoney,2),0);
                if($bset['commission_kouchu']==1){
                    $totalcommission = round($order['parent1commission'] + $order['parent2commission'] + $order['parent3commission'],2);
                    if($totalcommission>0) $totalmoney = $totalmoney - $totalcommission;
                }

				$isbusinesspay = Db::name('payorder')->where('aid',$aid)->where('ordernum',$order['ordernum'])->value('isbusinesspay');
				if(!$isbusinesspay){
					if($totalmoney < 0){
						$bmoney = Db::name('business')->where('aid',$aid)->where('id',$order['bid'])->value('money');
						if($bmoney + $totalmoney < 0){
							return ['status'=>0,'msg'=>'操作失败,商家余额不足'];
						}
					}
					$worker_order = Db::name('yuyue_worker_order')->where('id',$order['worker_orderid'])->find();
					if(getcustom('hmy_yuyue',$aid)){
						//获取师傅信息
						$rs = \app\customs\Yuyue::getMaster($worker_order['worker_id']);
						$worker = [];
						$worker['realname'] =$rs['data']['name'];
						$worker['tel'] = $rs['data']['phone']?$rs['data']['phone']:'';
					}else{
						$worker = Db::name('yuyue_worker')->where('id',$worker_order['worker_id'])->find();
					}
					\app\commons\Business::addmoney($aid,$order['bid'],$totalmoney,'服务订单/('.$worker['realname'].')'.$worker['tel'].' /:'.$order['ordernum'],true,$type,$order['ordernum'],['platformMoney'=>$platformMoney,'business_lirun'=>$business_lirun]);
				}else{
                	//商家推荐分成
					if($totalmoney > 0){
						if(getcustom('business_agent',$aid)){
							\app\commons\Business::addparentcommission2($aid,$order['bid'],$totalmoney,$platformMoney);
						}else{
							\app\commons\Business::addparentcommission($aid,$order['bid'],$totalmoney);
						}
					}
                }
				//店铺加销量
				Db::name('business')->where('aid',$aid)->where('id',$order['bid'])->inc('sales',$order['num'])->update();

				if(getcustom('business_canuseplatcoupon',$aid) && $order['coupon_money'] > 0 && $order['coupon_rid'] > 0){
					$couponrecord = Db::name('coupon_record')->where('id',$order['coupon_rid'])->find();
					if($couponrecord && $couponrecord['bid'] == 0){
						$businessuserecord = [];
						$businessuserecord['aid'] = $order['aid'];
						$businessuserecord['bid'] = $order['bid'];
						$businessuserecord['mid'] = $order['mid'];
						$businessuserecord['ordertype'] = $type;
						$businessuserecord['orderid'] = $order['id'];
						$businessuserecord['couponrid'] = $order['coupon_rid'];
						$businessuserecord['couponid'] = $couponrecord['couponid'];
						$businessuserecord['couponname'] = $couponrecord['couponname'];
						$businessuserecord['couponmoney'] = $couponrecord['money'];
						$businessuserecord['decmoney'] = $order['coupon_money'];
						$businessuserecord['status'] = 1;
						$businessuserecord['createtime'] = time();
						Db::name('coupon_businessuserecord')->insert($businessuserecord);
						Db::name('business')->where('id',$order['bid'])->inc('couponmoney',$order['coupon_money'])->update();
					}
				}
			}
			//赠积分
            if($order['givescore'] > 0){
				if($order['bid'] > 0){
					\app\commons\Business::addmemberscore($aid,$order['bid'],$order['mid'],$order['givescore'],'购买商品赠送'.t('积分'));
				}else{
					\app\commons\Member::addscore($aid,$order['mid'],$order['givescore'],'购买商品赠送'.t('积分'));
				}
            }
		}elseif($type=='lucky_collage'){
			if($order['bid']!=0){//入驻商家的货款

				$totalcommission = 0;
				//if($order['iscommission']){
				if($order['parent1'] && $order['parent1commission'] > 0){
					$totalcommission += $order['parent1commission'];
				}
				if($order['parent2'] && $order['parent2commission'] > 0){
					$totalcommission += $order['parent2commission'];
				}
				if($order['parent3'] && $order['parent3commission'] > 0){
					$totalcommission += $order['parent3commission'];
				}
				//}
				$binfo = Db::name('business')->where('aid',$aid)->where('id',$order['bid'])->find();
				$bset = Db::name('business_sysset')->where('aid',$aid)->find();
				if($bset['commission_kouchu'] == 0){ //不扣除佣金
					$totalcommission = 0;
				}
                $scoredkmoney = 0;
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

				$leveldkmoney = $order['leveldk_money'] ?? 0;
				if($bset['leveldk_kouchu'] == 0){ //扣除积分抵扣
					$leveldkmoney = 0;
				}

				$totalmoney = $order['product_price'] + $order['freight_price'] - $order['coupon_money'] - $order['leader_money'] - $totalcommission - $scoredkmoney - $leveldkmoney;
				if($totalmoney > 0){
                    $platformMoney = $totalmoney * $binfo['feepercent'] * 0.01;
                    $totalmoney = $totalmoney - $platformMoney;
				}

				if($order['paytypeid']==4){
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
					\app\commons\Business::addmoney($aid,$order['bid'],$totalmoney,'货款，幸运拼团订单号：'.$order['ordernum'],true,$type,$order['ordernum'],['platformMoney'=>$platformMoney]);
				}else{
                	//商家推荐分成
					if($totalmoney > 0){
						if(getcustom('business_agent',$aid)){
							\app\commons\Business::addparentcommission2($aid,$order['bid'],$totalmoney,$platformMoney);
						}else{
							\app\commons\Business::addparentcommission($aid,$order['bid'],$totalmoney);
						}
					}
                }
				//店铺加销量
				Db::name('business')->where('aid',$aid)->where('id',$order['bid'])->inc('sales',$order['num'])->update();

				if(getcustom('business_canuseplatcoupon',$aid) && $order['coupon_money'] > 0 && $order['coupon_rid'] > 0){
					$couponrecord = Db::name('coupon_record')->where('id',$order['coupon_rid'])->find();
					if($couponrecord && $couponrecord['bid'] == 0){
						$businessuserecord = [];
						$businessuserecord['aid'] = $order['aid'];
						$businessuserecord['bid'] = $order['bid'];
						$businessuserecord['mid'] = $order['mid'];
						$businessuserecord['ordertype'] = $type;
						$businessuserecord['orderid'] = $order['id'];
						$businessuserecord['couponrid'] = $order['coupon_rid'];
						$businessuserecord['couponid'] = $couponrecord['couponid'];
						$businessuserecord['couponname'] = $couponrecord['couponname'];
						$businessuserecord['couponmoney'] = $couponrecord['money'];
						$businessuserecord['decmoney'] = $order['coupon_money'];
						$businessuserecord['status'] = 1;
						$businessuserecord['createtime'] = time();
						Db::name('coupon_businessuserecord')->insert($businessuserecord);
						Db::name('business')->where('id',$order['bid'])->inc('couponmoney',$order['coupon_money'])->update();
					}
				}
			}
		}elseif($type=='kanjia'){
			if($order['bid']!=0){//入驻商家的货款
                $bset = Db::name('business_sysset')->where('aid',$aid)->find();
				$binfo = Db::name('business')->where('aid',$aid)->where('id',$order['bid'])->find();
                $scoredkmoney = 0;
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
				if(!is_null($order['business_total_money'])) {
					$totalmoney = $order['business_total_money'] - $scoredkmoney;
                    $platformMoney = $order['totalprice']-$totalmoney - $order['refund_money'];
				} else {
					$totalmoney = $order['product_price'] + $order['freight_price'] - $scoredkmoney;
					if($totalmoney > 0){
                        $platformMoney = $totalmoney * $binfo['feepercent'] * 0.01;
                        $totalmoney = $totalmoney - $platformMoney;
					}
				}

				if($order['paytypeid']==4){
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
					\app\commons\Business::addmoney($aid,$order['bid'],$totalmoney,'货款，砍价订单号：'.$order['ordernum'],true,$type,$order['ordernum'],['platformMoney'=>$platformMoney]);
				}else{
                	//商家推荐分成
					if($totalmoney > 0){
						if(getcustom('business_agent',$aid)){
							\app\commons\Business::addparentcommission2($aid,$order['bid'],$totalmoney,$platformMoney);
						}else{
							\app\commons\Business::addparentcommission($aid,$order['bid'],$totalmoney);
						}
					}
                }
				//店铺加销量
				Db::name('business')->where('aid',$aid)->where('id',$order['bid'])->inc('sales',$order['num'])->update();

				if(getcustom('business_canuseplatcoupon',$aid) && $order['coupon_money'] > 0 && $order['coupon_rid'] > 0){
					$couponrecord = Db::name('coupon_record')->where('id',$order['coupon_rid'])->find();
					if($couponrecord && $couponrecord['bid'] == 0){
						$businessuserecord = [];
						$businessuserecord['aid'] = $order['aid'];
						$businessuserecord['bid'] = $order['bid'];
						$businessuserecord['mid'] = $order['mid'];
						$businessuserecord['ordertype'] = $type;
						$businessuserecord['orderid'] = $order['id'];
						$businessuserecord['couponrid'] = $order['coupon_rid'];
						$businessuserecord['couponid'] = $couponrecord['couponid'];
						$businessuserecord['couponname'] = $couponrecord['couponname'];
						$businessuserecord['couponmoney'] = $couponrecord['money'];
						$businessuserecord['decmoney'] = $order['coupon_money'];
						$businessuserecord['status'] = 1;
						$businessuserecord['createtime'] = time();
						Db::name('coupon_businessuserecord')->insert($businessuserecord);
						Db::name('business')->where('id',$order['bid'])->inc('couponmoney',$order['coupon_money'])->update();
					}
				}
			}
		}elseif($type=='seckill'){
			if($order['bid']!=0){//入驻商家的货款

				$totalcommission = 0;
				if($order['parent1'] && $order['parent1commission'] > 0){
					$totalcommission += $order['parent1commission'];
				}
				if($order['parent2'] && $order['parent2commission'] > 0){
					$totalcommission += $order['parent2commission'];
				}
				if($order['parent3'] && $order['parent3commission'] > 0){
					$totalcommission += $order['parent3commission'];
				}
				$binfo = Db::name('business')->where('aid',$aid)->where('id',$order['bid'])->find();
				$bset = Db::name('business_sysset')->where('aid',$aid)->find();
				if($bset['commission_kouchu'] == 0){ //不扣除佣金
					$totalcommission = 0;
				}
                if(getcustom('business_agent',$aid)){
                    $lirun_cost_price = 0;
                    if($order['cost_price']>0){
                        $lirun_cost_price = $order['cost_price']*$order['num'];
                    }
                    $business_lirun = $order['totalprice'] - $order['refund_money'] - $lirun_cost_price;
                }
                $scoredkmoney = 0;
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
				if(!is_null($order['business_total_money'])) {
					$leveldkmoney = $order['leveldk_money'] ?? 0;
					if($bset['leveldk_kouchu'] == 0){ //扣除会员抵扣
						$leveldkmoney = 0;
					}
					$totalmoney = $order['business_total_money'] - $totalcommission - $leveldkmoney - $scoredkmoney;
                    $platformMoney = $order['totalprice']-$totalmoney - $order['refund_money'];
				} else {
					$leveldkmoney = $order['leveldk_money'] ?? 0;
					if($bset['leveldk_kouchu'] == 0){ //扣除积分抵扣
						$leveldkmoney = 0;
					}
					$totalmoney = $order['product_price'] + $order['freight_price'] - $order['coupon_money'] - $order['manjia_money'] - $totalcommission - $scoredkmoney - $leveldkmoney;
					if($totalmoney > 0){
						if(getcustom('business_deduct_cost',$aid)){
                        	if($binfo && $binfo['deduct_cost'] == 1 && $order['cost_price']>0){
                        		if($order['cost_price']<=$order['sell_price']){
									$all_cost_price = $order['cost_price'];
								}else{
									$all_cost_price = $order['sell_price'];
								}
	                        	//扣除成本
                                $platformMoney = ($totalmoney-$all_cost_price)*$binfo['feepercent']/100;
                            }else{
                                $platformMoney = $totalmoney * $binfo['feepercent'] * 0.01;
                            }

		                }else{
                            $platformMoney = $totalmoney * $binfo['feepercent'] * 0.01;

		                }
                        if(getcustom('business_fee_type',$aid)){
                            if($bset['business_fee_type'] == 1){
                                $platformMoney = ($order['totalprice']-$order['freight_price']) * $binfo['feepercent'] * 0.01;
                            }elseif($bset['business_fee_type'] == 2){
                                $platformMoney = $order['cost_price'] * $binfo['feepercent'] * 0.01;
                            }
                        }
                        $totalmoney = $totalmoney - $platformMoney;
					}
				}

				if($order['paytypeid']==4){
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
					\app\commons\Business::addmoney($aid,$order['bid'],$totalmoney,'货款，秒杀订单号：'.$order['ordernum'],true,$type,$order['ordernum'],['platformMoney'=>$platformMoney,'business_lirun'=>$business_lirun]);
				}else{
                	//商家推荐分成
					if($totalmoney > 0){
						if(getcustom('business_agent',$aid)){
							\app\commons\Business::addparentcommission2($aid,$order['bid'],$totalmoney,$platformMoney,$business_lirun);
						}else{
							\app\commons\Business::addparentcommission($aid,$order['bid'],$totalmoney);
						}
					}
                }
                //店铺加销量
                Db::name('business')->where('aid',$aid)->where('id',$order['bid'])->inc('sales',$order['num'])->update();

				if(getcustom('business_canuseplatcoupon',$aid) && $order['coupon_money'] > 0 && $order['coupon_rid'] > 0){
					$couponrecord = Db::name('coupon_record')->where('id',$order['coupon_rid'])->find();
					if($couponrecord && $couponrecord['bid'] == 0){
						$businessuserecord = [];
						$businessuserecord['aid'] = $order['aid'];
						$businessuserecord['bid'] = $order['bid'];
						$businessuserecord['mid'] = $order['mid'];
						$businessuserecord['ordertype'] = $type;
						$businessuserecord['orderid'] = $order['id'];
						$businessuserecord['couponrid'] = $order['coupon_rid'];
						$businessuserecord['couponid'] = $couponrecord['couponid'];
						$businessuserecord['couponname'] = $couponrecord['couponname'];
						$businessuserecord['couponmoney'] = $couponrecord['money'];
						$businessuserecord['decmoney'] = $order['coupon_money'];
						$businessuserecord['status'] = 1;
						$businessuserecord['createtime'] = time();
						Db::name('coupon_businessuserecord')->insert($businessuserecord);
						Db::name('business')->where('id',$order['bid'])->inc('couponmoney',$order['coupon_money'])->update();
					}
				}
            }
            //赠积分
            if($order['givescore'] > 0){
                \app\commons\Member::addscore($aid,$order['mid'],$order['givescore'],'购买产品赠送'.t('积分'));
            }
		}elseif($type=='coupon'){
			if($order['bid']!=0){//入驻商家的货款
				$binfo = Db::name('business')->where('aid',$aid)->where('id',$order['bid'])->find();
				$totalmoney = $order['price'];
				if($totalmoney > 0){
                    $platformMoney = $totalmoney * $binfo['feepercent'] * 0.01;
                    $totalmoney = $totalmoney - $platformMoney;
				}
				$isbusinesspay = Db::name('payorder')->where('aid',$aid)->where('ordernum',$order['ordernum'])->value('isbusinesspay');
				if(!$isbusinesspay){
					\app\commons\Business::addmoney($aid,$order['bid'],$totalmoney,'销售'.t('优惠券').' 订单号：'.$order['ordernum'],true,$type,$order['ordernum'],['platformMoney'=>$platformMoney]);
				}else{
                	//商家推荐分成
					if($totalmoney > 0){
						if(getcustom('business_agent',$aid)){
							\app\commons\Business::addparentcommission2($aid,$order['bid'],$totalmoney,$platformMoney);
						}else{
							\app\commons\Business::addparentcommission($aid,$order['bid'],$totalmoney);
						}
					}
                }
			}
		}elseif($type=='maidan'){
			$totalcommission = 0;
			$maidanfenxiao = Db::name('admin_set')->where('aid',$aid)->value('maidanfenxiao');
            if(getcustom('maidan_qrcode',$aid)) {
                $maidanfenxiao = 0;
            }
			if($maidanfenxiao == 1){ //参与分销 买单分销
				$member = Db::name('member')->where('aid',$aid)->where('id',$order['mid'])->find();
				$agleveldata = Db::name('member_level')->where('aid',$aid)->where('id',$member['levelid'])->find();
				if($agleveldata['can_agent'] > 0 && $agleveldata['commission1own']==1){
					$member['pid'] = $member['id'];
				}
				$isCommissionScore = 0;
				if(getcustom('maidan_commission_score',$aid)){
                    $isCommissionScore = 1;
                }
				if(getcustom('maidan_fenhong_new',$aid)){
                    //买单分销结算方式
                    $sysset = Db::name('admin_set')->where('aid',$aid)->field('maidanfenxiao_type,maidan_cost')->find();
                    $maidanfenhong_type = $sysset['maidanfenxiao_type'];
                    if($maidanfenhong_type == 1){
                        //按利润结算时直接把销售额改成利润
                        if($order['bid']>0){
                            $maidan_cost = Db::name('business')->where('id',$order['bid'])->value('maidan_cost');
                        }else{
                            $maidan_cost = $sysset['maidan_cost'];
                        }
                        $cost_price = bcmul($order['paymoney'],$maidan_cost/100,2);
                        $order['paymoney'] = $order['paymoney'] - $cost_price;
                    }
                }
				$ogdata = [];
                //是否积分提成
                $ogdata['isparent1score'] = 0;
                $ogdata['isparent2score'] = 0;
                $ogdata['isparent3score'] = 0;
				if($member['pid']){
					$parent1 = Db::name('member')->where('aid',$aid)->where('id',$member['pid'])->find();
					if($parent1){
						$agleveldata1 = Db::name('member_level')->where('aid',$aid)->where('id',$parent1['levelid'])->find();
						if($agleveldata1['can_agent']!=0){
							$ogdata['parent1'] = $parent1['id'];
							if($isCommissionScore && $agleveldata1['maidan_commission_score1']>0){
                                $ogdata['isparent1score'] = 1;
                                $ogdata['parent1score'] = round($agleveldata1['maidan_commission_score1'] * $order['paymoney'] * 0.01);
                            }
						}
					}
				}
				if($parent1['pid']){
					$parent2 = Db::name('member')->where('aid',$aid)->where('id',$parent1['pid'])->find();
					if($parent2){
						$agleveldata2 = Db::name('member_level')->where('aid',$aid)->where('id',$parent2['levelid'])->find();
						if($agleveldata2['can_agent']>1){
							$ogdata['parent2'] = $parent2['id'];
                            if($isCommissionScore && $agleveldata2['maidan_commission_score2']>0){
                                $ogdata['isparent2score'] = 1;
                                $ogdata['parent2score'] = round($agleveldata2['maidan_commission_score2'] * $order['paymoney'] * 0.01);
                            }
						}
					}
				}
				if($parent2['pid']){
					$parent3 = Db::name('member')->where('aid',$aid)->where('id',$parent2['pid'])->find();
					if($parent3){
						$agleveldata3 = Db::name('member_level')->where('aid',$aid)->where('id',$parent3['levelid'])->find();
						if($agleveldata3['can_agent']>2){
							$ogdata['parent3'] = $parent3['id'];
                            if($isCommissionScore &&  $agleveldata3['maidan_commission_score3']>0){
                                $ogdata['isparent3score'] = 1;
                                $ogdata['parent3score'] = round($agleveldata3['maidan_commission_score3'] * $order['paymoney'] * 0.01);
                            }
						}
					}
				}
                if($agleveldata1['commissiontype']==1){ //固定金额按单
                    $ogdata['parent1commission'] = $agleveldata1['commission1'];
                }else{
                    $ogdata['parent1commission'] = $agleveldata1['commission1'] * $order['paymoney'] * 0.01;
                }
                if($agleveldata2['commissiontype']==1){ //固定金额按单
                    $ogdata['parent2commission'] = $agleveldata2['commission2'];
                }else{
                    $ogdata['parent2commission'] = $agleveldata2['commission2'] * $order['paymoney'] * 0.01;
                }
                if($agleveldata3['commissiontype']==1){ //固定金额按单
                    $ogdata['parent3commission'] = $agleveldata3['commission3'];
                }else{
                    $ogdata['parent3commission'] = $agleveldata3['commission3'] * $order['paymoney'] * 0.01;
                }

				if($ogdata['parent1'] && $ogdata['isparent1score']==1){
                    $ogdata['parent1score']>0 && \app\commons\Member::addscore($aid,$ogdata['parent1'],$ogdata['parent1score'],'下级买单'.t('积分').'奖励');
                }elseif($ogdata['parent1'] && $ogdata['isparent1score']==0 && $ogdata['parent1commission'] > 0){
					$totalcommission+=$ogdata['parent1commission'];
					\app\commons\Member::addcommission($aid,$ogdata['parent1'],$mid,$ogdata['parent1commission'],'下级买单收款奖励');
					Db::name('member_commission_record')->insert(['aid'=>$aid,'mid'=>$ogdata['parent1'],'frommid'=>$order['mid'],'orderid'=>$order['id'],'type'=>'maidan','commission'=>$ogdata['parent1commission'],'remark'=>'下级买单收款奖励','createtime'=>time(),'status'=>1,'endtime'=>time()]);
				}
                if($ogdata['parent2'] && $ogdata['isparent2score']==1){
                    $ogdata['parent2score']>0 && \app\commons\Member::addscore($aid,$ogdata['parent2'],$ogdata['parent2score'],'下二级买单'.t('积分').'奖励');
                }elseif($ogdata['parent2'] && $ogdata['isparent2score']==0 && $ogdata['parent2commission'] > 0){
					$totalcommission+=$ogdata['parent2commission'];
					\app\commons\Member::addcommission($aid,$ogdata['parent2'],$mid,$ogdata['parent2commission'],'下二级买单收款奖励');
					Db::name('member_commission_record')->insert(['aid'=>$aid,'mid'=>$ogdata['parent2'],'frommid'=>$order['mid'],'orderid'=>$order['id'],'type'=>'maidan','commission'=>$ogdata['parent2commission'],'remark'=>'下二级买单收款奖励','createtime'=>time(),'status'=>1,'endtime'=>time()]);
				}
                if($ogdata['parent3'] && $ogdata['isparent3score']==1){
                    $ogdata['parent3score']>0 && \app\commons\Member::addscore($aid,$ogdata['parent3'],$ogdata['parent3score'],'下三级买单'.t('积分').'奖励');
                }elseif($ogdata['parent3'] && $ogdata['isparent3score']==0 && $ogdata['parent3commission'] > 0){
					$totalcommission+=$ogdata['parent3commission'];
					\app\commons\Member::addcommission($aid,$ogdata['parent3'],$mid,$ogdata['parent3commission'],'下三级买单收款奖励');
					Db::name('member_commission_record')->insert(['aid'=>$aid,'mid'=>$ogdata['parent3'],'frommid'=>$order['mid'],'orderid'=>$order['id'],'type'=>'maidan','commission'=>$ogdata['parent3commission'],'remark'=>'下三级买单收款奖励','createtime'=>time(),'status'=>1,'endtime'=>time()]);
				}
				if($ogdata['parent1']){
					\app\commons\Member::uplv($aid,$ogdata['parent1']);
				}
				$bset = Db::name('business_sysset')->where('aid',$aid)->find();
                if($bset['commission_kouchu'] == 0){ //不扣除佣金
                    $totalcommission = 0;
                }
			}
            if(getcustom('active_coin',$aid)){
                //先发放激活币
                self::giveActiveCoin($aid,$order,'maidan');
            }
            if(getcustom('active_score',$aid)){
                //让利积分
                self::giveActiveScore($aid,$order,'maidan');
            }
            if(getcustom('member_commission_max',$aid) && getcustom('add_commission_max',$aid)){
                //先发放佣金上限
                \app\commons\Order::giveCommissionMax($aid,$order);
            }
			if($order['bid']!=0){//入驻商家的货款
				$binfo = Db::name('business')->where('aid',$aid)->where('id',$order['bid'])->find();
				$oldtotalmoney = $totalmoney = $order['money'] - $order['couponmoney'];
				if($totalmoney > 0){
                    $platformMoney = $totalmoney * $binfo['feepercent'] * 0.01;
                    $totalmoney = $totalmoney - $platformMoney - $totalcommission;//先算抽成，然后扣除佣金
				}
                if(!isset($bset)) $bset = Db::name('business_sysset')->where('aid',$aid)->find();
                $scoredkmoney = 0;
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
                $totalmoney = $totalmoney - $scoredkmoney;
                if(getcustom('active_coin',$aid) && getcustom('maidan_fenhong_new',$aid)){
                    //$total_activecoin = Db::name('maidan_order')->where('id',$order['id'])->value('activecoin');
                    //$totalmoney = bcsub($totalmoney,bcmul($totalmoney,$binfo['maidan_cost']/100,2),2);
                }
                //扣除返现比例
                $queue_feepercent_type = 0;
                $queue_feepercent_allmoney = 0;
                if(getcustom('yx_queue_free',$aid)){
                    $queue_free_set = Db::name('queue_free_set')->where('aid',$order['aid'])->where('bid',0)->find();
                    $b_queue_free_set = Db::name('queue_free_set')->where('aid',$order['aid'])->where('bid',$order['bid'])->find();
                    $queue_free_set['order_types'] = explode(',',$queue_free_set['order_types']);
                    if($queue_free_set && $queue_free_set['status']==1 && $b_queue_free_set['status']==1 && in_array('all',$queue_free_set['order_types']) || in_array('maidan',$queue_free_set['order_types'])){
                        if($queue_free_set['feepercent_type'] == 1){
                            $queue_feepercent_type = 1;
                        }
                    }

                    if($queue_feepercent_type == 1  && $b_queue_free_set['rate_back'] > 0){
                        $totalmoney = $totalmoney - $oldtotalmoney * $b_queue_free_set['rate_back'] * 0.01;
                    }
                }
				$isbusinesspay = Db::name('payorder')->where('aid',$aid)->where('ordernum',$order['ordernum'])->value('isbusinesspay');
				if(!$isbusinesspay){
					\app\commons\Business::addmoney($aid,$order['bid'],$totalmoney,'买单 订单号：'.$order['ordernum'],true,$type,$order['ordernum'],['platformMoney'=>$platformMoney]);
				}else{
                	//商家推荐分成
					if($totalmoney > 0){
                        $business_lirun = $totalmoney;
                        if(getcustom('maidan_fenhong_new',$aid)){
                            $business_lirun = $oldtotalmoney * (100 - $binfo['maidan_cost'])*0.01;
                        }
						if(getcustom('business_agent',$aid)){
							\app\commons\Business::addparentcommission2($aid,$order['bid'],$totalmoney,$platformMoney,$business_lirun);
						}else{
							\app\commons\Business::addparentcommission($aid,$order['bid'],$totalmoney);
						}
					}
                }
				if(getcustom('business_canuseplatcoupon',$aid) && $order['couponmoney'] > 0 && $order['couponrid'] > 0){
					$couponrecord = Db::name('coupon_record')->where('id',$order['couponrid'])->find();
					if($couponrecord && $couponrecord['bid'] == 0){
						$businessuserecord = [];
						$businessuserecord['aid'] = $order['aid'];
						$businessuserecord['bid'] = $order['bid'];
						$businessuserecord['mid'] = $order['mid'];
						$businessuserecord['ordertype'] = $type;
						$businessuserecord['orderid'] = $order['id'];
						$businessuserecord['couponrid'] = $order['couponrid'];
						$businessuserecord['couponid'] = $couponrecord['couponid'];
						$businessuserecord['couponname'] = $couponrecord['couponname'];
						$businessuserecord['couponmoney'] = $couponrecord['money'];
						$businessuserecord['decmoney'] = $order['couponmoney'];
						$businessuserecord['status'] = 1;
						$businessuserecord['createtime'] = time();
						Db::name('coupon_businessuserecord')->insert($businessuserecord);
						Db::name('business')->where('id',$order['bid'])->inc('couponmoney',$order['couponmoney'])->update();
					}
				}
			}

            if(getcustom('yx_queue_free',$aid)){
                \app\customs\QueueFree::join($order,$type,'collect');
            }

			if(getcustom('mendian_maidan_ticheng',$aid)){
				if($order['mdid']>0){
					$mendian = 	Db::name('mendian')->field('id,maidangivepercent,maidangivemoney')->where('id',$order['mdid'])->find();
					$givemoney = 0;
					if($mendian['maidangivepercent']>0 || $mendian['maidangivemoney']>0){
						$givemoney += $order['paymoney'] * 0.01 * $mendian['maidangivepercent'] + $mendian['maidangivemoney'];
						if($givemoney > 0){
							\app\commons\Mendian::addmoney($order['aid'],$mendian['id'],$givemoney,'买单提成'.$order['ordernum']);
						}
					}
				}

			}
            if(getcustom('business_maidan_team_fenhong')){
                self::maidanFenhongJl($order);
            }
		}elseif($type=='designerpage'){
			if($order['bid']!=0){//入驻商家的货款
				$binfo = Db::name('business')->where('aid',$aid)->where('id',$order['bid'])->find();
				$totalmoney = $order['price'];
				if($totalmoney > 0){
                    $platformMoney = $totalmoney * $binfo['feepercent'] * 0.01;
                    $totalmoney = $totalmoney - $platformMoney;
				}
				$isbusinesspay = Db::name('payorder')->where('aid',$aid)->where('ordernum',$order['ordernum'])->value('isbusinesspay');
				if(!$isbusinesspay){
					\app\commons\Business::addmoney($aid,$order['bid'],$totalmoney,'付费查看页面 订单号：'.$order['ordernum'],true,$type,$order['ordernum'],['platformMoney'=>$platformMoney]);
				}else{
                	//商家推荐分成
					if($totalmoney > 0){
						if(getcustom('business_agent',$aid)){
							\app\commons\Business::addparentcommission2($aid,$order['bid'],$totalmoney,$platformMoney);
						}else{
							\app\commons\Business::addparentcommission($aid,$order['bid'],$totalmoney);
						}
					}
                }
			}
		}elseif($type == 'scoreshop') {
            //门店分成
            if($order['mdid'] && $commission_mid) {
                $orderGoods = Db::name($type.'_order_goods')->where('orderid',$order['id'])->select()->toArray();
                foreach ($orderGoods as $og) {
                    if($og['mendian_iscommission'] == 0 && $og['mendian_commission'] > 0 && $og['mendian_score'] > 0){
                        if($og['mendian_commission'] > 0) {
                            \app\commons\Member::addcommission($aid, $commission_mid, $order['mid'], $og['mendian_commission'], '门店核销：'.$order['ordernum']);
                        }
                        if($og['mendian_score'] > 0) {
                            \app\commons\Member::addscore($aid, $commission_mid, $og['mendian_score'],'门店核销：'.$order['ordernum']);
                        }
                        Db::name($type.'_order_goods')->where('id',$og['id'])->update(['mendian_iscommission' => 1]);
                    }
                }
            }
			if($order['bid']!=0){//入驻商家的货款

				$binfo = Db::name('business')->where('aid',$aid)->where('id',$order['bid'])->find();
				$bset = Db::name('business_sysset')->where('aid',$aid)->find();
                if(getcustom('business_agent',$aid)){
                    $lirun_cost_price = 0;
                    if($order['cost_price']>0){
                        $lirun_cost_price = $order['cost_price']*$order['num'];
                    }
                    $business_lirun = $order['totalprice'] - $order['refund_money'] - $lirun_cost_price;
                }

				if(!is_null($order['business_total_money'])) {
					$totalmoney = $order['business_total_money'];
                    $platformMoney = $order['totalprice']-$totalmoney - $order['refund_money'];
				} else {
					$totalmoney = $order['totalmoney'] + $order['freight_price'];
					if($totalmoney > 0){
						if(getcustom('business_deduct_cost',$aid)){
							//获取商品成
							$orderGoods = Db::name($type.'_order_goods')->where('orderid',$order['id'])->select()->toArray();
			                $all_cost_price = 0;
			                if($orderGoods){
			                	foreach ($orderGoods as $og) {
				                    if(!empty($og['cost_price']) && $og['cost_price']>0){
										if($og['cost_price']<=$og['sell_price']){
											$all_cost_price += $og['cost_price'];
										}else{
											$all_cost_price += $og['sell_price'];
										}
									}
				                }
			                }

                        	if($binfo && $binfo['deduct_cost'] == 1){
	                        	//扣除成本
                                $platformMoney = ($totalmoney-$all_cost_price)*$binfo['feepercent']/100;
                            }else{
                                $platformMoney = $totalmoney * $binfo['feepercent'] * 0.01;
                            }

                        }else{
                            $platformMoney = $totalmoney * $binfo['feepercent'] * 0.01;

                        }
                        if(getcustom('business_fee_type',$aid)){
                            if($bset['business_fee_type'] == 1){
                                $platformMoney = ($order['totalprice']-$order['freight_price']) * $binfo['feepercent'] * 0.01;
                            }elseif($bset['business_fee_type'] == 2){
                                //获取商品成
                                $orderGoods = Db::name($type.'_order_goods')->where('orderid',$order['id'])->select()->toArray();
                                $total_cost_price = 0;
                                if($orderGoods){
                                    foreach ($orderGoods as $og) {
                                        if(!empty($og['cost_price']) && $og['cost_price']>0){
                                                $total_cost_price += $og['cost_price']*$og['num'];
                                        }
                                    }
                                }
                                $platformMoney = $total_cost_price * $binfo['feepercent'] * 0.01;
                            }
                        }
                        $totalmoney = $totalmoney - $platformMoney;

					}
				}

				if($order['paytypeid']==4){
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
					\app\commons\Business::addmoney($aid,$order['bid'],$totalmoney,'货款，'.t('积分').'兑换订单号：'.$order['ordernum'],true,$type,$order['ordernum'],['platformMoney'=>$platformMoney,'business_lirun'=>$business_lirun]);
				}else{
                	//商家推荐分成
					if($totalmoney > 0){
						if(getcustom('business_agent',$aid)){
							\app\commons\Business::addparentcommission2($aid,$order['bid'],$totalmoney,$platformMoney,$business_lirun);
						}else{
							\app\commons\Business::addparentcommission($aid,$order['bid'],$totalmoney);
						}
					}
                }
				$bset = Db::name('business_sysset')->where('aid',$aid)->find();
				if($bset['business_selfscore'] == 1){
					$totalscore = Db::name('scoreshop_order_goods')->where('orderid',$order['id'])->sum('totalscore');
					if($totalscore > 0 && $order['totalscore'] == 0){
						\app\commons\Business::addscore($aid,$order['bid'],$totalscore,'用户兑换商品，订单号：'.$order['ordernum'],1);
					}
				}
				//店铺加销量
				$totalnum = Db::name('scoreshop_order_goods')->where('orderid',$order['id'])->sum('num');
				Db::name('business')->where('aid',$aid)->where('id',$order['bid'])->inc('sales',$totalnum)->update();
			}
        }elseif($type=='tuangou'){
            if($order['bid']!=0){//入驻商家的货款

				$binfo = Db::name('business')->where('aid',$aid)->where('id',$order['bid'])->find();

                if(getcustom('business_agent',$aid)){
                    $lirun_cost_price = 0;
                    if($order['cost_price']>0){
                        $lirun_cost_price = $order['cost_price']*$order['num'];
                    }
                    $business_lirun = $order['totalprice'] - $order['refund_money'] - $lirun_cost_price;
                }

				$totalmoney = $order['product_price'] + $order['freight_price'] * (100-$binfo['feepercent_freight']) * 0.01 - $order['coupon_money'] - $order['manjia_money'];
				if($order['paytypeid']==4){
					$totalmoney = $totalmoney - $order['totalprice'];
				}
				$bset = Db::name('business_sysset')->where('aid',$aid)->find();
                $scoredkmoney = 0;
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

				$leveldkmoney = $order['leveldk_money'] ?? 0;
				if($bset['leveldk_kouchu'] == 0){ //扣除积分抵扣
					$leveldkmoney = 0;
				}

				$totalmoney = $totalmoney - $scoredkmoney - $leveldkmoney;
                if($totalmoney > 0){
                    $platformMoney = $totalmoney * $binfo['feepercent'] * 0.01;
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
					\app\commons\Business::addmoney($aid,$order['bid'],$totalmoney,'货款，团购订单号：'.$order['ordernum'],true,$type,$order['ordernum'],['platformMoney'=>$platformMoney,'business_lirun'=>$business_lirun]);
				}else{
                	//商家推荐分成
					if($totalmoney > 0){
						if(getcustom('business_agent',$aid)){
							\app\commons\Business::addparentcommission2($aid,$order['bid'],$totalmoney,$platformMoney,$business_lirun);
						}else{
							\app\commons\Business::addparentcommission($aid,$order['bid'],$totalmoney);
						}
					}
                }
                //店铺加销量
                Db::name('business')->where('aid',$aid)->where('id',$order['bid'])->inc('sales',$order['num'])->update();

				if(getcustom('business_canuseplatcoupon',$aid) && $order['coupon_money'] > 0 && $order['coupon_rid'] > 0){
					$couponrecord = Db::name('coupon_record')->where('id',$order['coupon_rid'])->find();
					if($couponrecord && $couponrecord['bid'] == 0){
						$businessuserecord = [];
						$businessuserecord['aid'] = $order['aid'];
						$businessuserecord['bid'] = $order['bid'];
						$businessuserecord['mid'] = $order['mid'];
						$businessuserecord['ordertype'] = $type;
						$businessuserecord['orderid'] = $order['id'];
						$businessuserecord['couponrid'] = $order['coupon_rid'];
						$businessuserecord['couponid'] = $couponrecord['couponid'];
						$businessuserecord['couponname'] = $couponrecord['couponname'];
						$businessuserecord['couponmoney'] = $couponrecord['money'];
						$businessuserecord['decmoney'] = $order['coupon_money'];
						$businessuserecord['status'] = 1;
						$businessuserecord['createtime'] = time();
						Db::name('coupon_businessuserecord')->insert($businessuserecord);
						Db::name('business')->where('id',$order['bid'])->inc('couponmoney',$order['coupon_money'])->update();
					}
				}
            }
        }elseif($type=='kecheng'){
			if($order['bid']!=0){//入驻商家的货款

				$binfo = Db::name('business')->where('aid',$aid)->where('id',$order['bid'])->find();
				$totalcommission = 0;
				//if($order['iscommission']){
				if($order['parent1'] && $order['parent1commission'] > 0){
					$totalcommission += $order['parent1commission'];
				}
				if($order['parent2'] && $order['parent2commission'] > 0){
					$totalcommission += $order['parent2commission'];
				}
				if($order['parent3'] && $order['parent3commission'] > 0){
					$totalcommission += $order['parent3commission'];
				}
				//}
				$binfo = Db::name('business')->where('aid',$aid)->where('id',$order['bid'])->find();
				$bset = Db::name('business_sysset')->where('aid',$aid)->find();
				if($bset['commission_kouchu'] == 0){ //不扣除佣金
					$totalcommission = 0;
				}

				$totalmoney = $order['totalprice'] - $totalcommission;
				if($totalmoney > 0){
                    $platformMoney = $totalmoney * $binfo['feepercent'] * 0.01;
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
					\app\commons\Business::addmoney($aid,$order['bid'],$totalmoney,'课程订单号：'.$order['ordernum'],true,$type,$order['ordernum'],['platformMoney'=>$platformMoney]);
				}else{
                	//商家推荐分成
					if($totalmoney > 0){
						if(getcustom('business_agent',$aid)){
							\app\commons\Business::addparentcommission2($aid,$order['bid'],$totalmoney,$platformMoney);
						}else{
							\app\commons\Business::addparentcommission($aid,$order['bid'],$totalmoney);
						}
					}
                }
				//店铺加销量
                if(empty($order['num'])){
                    $order['num'] = 1;
                }
				Db::name('business')->where('aid',$aid)->where('id',$order['bid'])->inc('sales',$order['num'])->update();
			}
		}elseif($type=='yueke'){
			if($order['bid']!=0){//入驻商家的货款

				$binfo = Db::name('business')->where('aid',$aid)->where('id',$order['bid'])->find();
				$totalmoney = $order['product_price'];
				if($totalmoney > 0){
                    $platformMoney = $totalmoney * $binfo['feepercent'] * 0.01;
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
					$worker = Db::name('yuyue_worker')->where('id',$order['workerid'])->find();
					\app\commons\Business::addmoney($aid,$order['bid'],$totalmoney,'约课订单/('.$worker['realname'].')'.$worker['tel'].' /:'.$order['ordernum'],true,$type,$order['ordernum'],['platformMoney'=>$platformMoney]);
				}else{
                	//商家推荐分成
					if($totalmoney > 0){
						if(getcustom('business_agent',$aid)){
							\app\commons\Business::addparentcommission2($aid,$order['bid'],$totalmoney,$platformMoney);
						}else{
							\app\commons\Business::addparentcommission($aid,$order['bid'],$totalmoney);
						}
					}
                }
				//店铺加销量
				Db::name('business')->where('aid',$aid)->where('id',$order['bid'])->inc('sales',$order['num'])->update();
			}
		}elseif($type=='gift_bag'){
			if(getcustom('extend_gift_bag',$aid)){
				if($order['bid']!=0){//入驻商家的货款
					$binfo = Db::name('business')->where('aid',$aid)->where('id',$order['bid'])->find();
					$totalmoney = $order['totalprice'];
					if($totalmoney > 0){
                        $platformMoney = $totalmoney * $binfo['feepercent'] * 0.01;
                        $totalmoney = $totalmoney - $platformMoney;
					}
					$isbusinesspay = Db::name('payorder')->where('aid',$aid)->where('ordernum',$order['ordernum'])->value('isbusinesspay');
					if(!$isbusinesspay){
						\app\commons\Business::addmoney($aid,$order['bid'],$totalmoney,'礼包订单号：'.$order['ordernum'],true,$type,$order['ordernum'],['platformMoney'=>$platformMoney]);
					}else{
	                	//商家推荐分成
						if($totalmoney > 0){
							if(getcustom('business_agent',$aid)){
								\app\commons\Business::addparentcommission2($aid,$order['bid'],$totalmoney,$platformMoney);
							}else{
								\app\commons\Business::addparentcommission($aid,$order['bid'],$totalmoney);
							}
						}
	                }
				}
			}
		}elseif($type=='hotel'){
            if($order['bid']!=0){//入驻商家的货款
				$binfo = Db::name('business')->where('aid',$aid)->where('id',$order['bid'])->find();
				//需要结算的金额为 实付金额减去押金  减去已退的服务费
				$totalmoney = $order['totalprice'] - $order['yajin_money'] - $order['fuwu_refund_money'];
				$totalmoney = $totalmoney* (100-$binfo['feepercent_freight']) * 0.01 - $order['coupon_money'];
				$bset = Db::name('business_sysset')->where('aid',$aid)->find();
                $scoredkmoney = 0;
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
				$leveldkmoney = $order['leveldk_money'] ?? 0;
				if($bset['leveldk_kouchu'] == 0){ //扣除积分抵扣
					$leveldkmoney = 0;
				}

				$totalmoney = $totalmoney - $scoredkmoney - $leveldkmoney;

                if($totalmoney > 0){
                    $platformMoney = $totalmoney * $binfo['feepercent'] * 0.01;
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
					$text =  \app\models\Hotel::gettext($aid);
					\app\commons\Business::addmoney($aid,$order['bid'],$totalmoney,'货款，'.$text['酒店'].'订单号：'.$order['ordernum'],true,$type,$order['ordernum']);
				}
                //店铺加销量
                Db::name('business')->where('aid',$aid)->where('id',$order['bid'])->inc('sales',$order['totalnum'])->update();
            }
        }
		$set = Db::name('admin_set')->where('aid',$aid)->find();
		if($set['fxjiesuantime_delaydays'] == '0'){ //确认收货后发佣金 0天结算
			self::giveCommission($order,$type);
		}
        if(getcustom('business_fenxiao',$aid)){
            $fenxiao_type = $set['business_fenxiao_type'];
            //收货完成，店铺分销统计日营业额
            if($fenxiao_type==1){
                $payorder = Db::name('payorder')
                    ->where('orderid',$order['id'])
                    ->where('mid',$order['mid'])
                    ->where('status',1)
                    ->where('type',$type)
                    ->find();
                \app\commons\Business::countBusinessYeji($payorder);
            }
        }
        \app\models\Payorder::afterusecoupon($order['id'],$type,2,$order['ordernum']);
        if(getcustom('ganer_fenxiao',$aid)){
            //订单业绩进入奖金池
            $set = Db::name('prize_pool_set')->where('aid',$aid)->find();
            //订单业绩进入奖金池
            if($set['pool_time']==1){
                \app\commons\Fenxiao::bonus_poul($order['id'],$type);
            }
        }
        //会员抵扣积分兑换到余额
        if(getcustom('business_score_jiesuan',$aid) && $order['bid']>0){
            if($businessDkMoney>0){
                \app\commons\Business::addmoney($aid,$order['bid'],$businessDkMoney,t('积分').'抵扣转'.t('余额').'，订单号：'.$order['ordernum'],false,$type,$order['ordernum']);
            }
            if($businessDkScore>0){
                \app\commons\Business::addscore($aid,$order['bid'],$businessDkScore,t('积分').'抵扣到商户'.t('积分').'，订单号：'.$order['ordernum']);
            }
        }
        return ['status'=>1,'msg'=>'操作成功'];
	}

    //排名分红追加点位
    public static function PaimingFenhongPoint($order){
        $aid = $order['aid'];
		$mid = $order['mid'];
        $set = Db::name('paiming_fenhong_set')->where('aid',$aid)->find();
        if($set['is_open'] == 1){
            $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->find();
            $order_amount = $order['totalprice'];

            $over_point_amount = $set['over_point_amount'];
            $all_amount = $order_amount + $member['paiming_fenhong_buy_money'];
            $point_num = floor($all_amount/$over_point_amount);
            $buy_money = round($all_amount - ($point_num*$over_point_amount),2);
            //剩余金额继续保留
            Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['paiming_fenhong_buy_money'=>$buy_money]);
            //追加点位
            if($point_num>0){
                for($i=0;$i<$point_num;$i++){
                    $data_record = [];
                    $data_record['aid'] = $aid;
                    $data_record['mid'] = $mid;
                    $data_record['max_amount'] = $set['max_point_amount'];
                    $data_record['status'] = 0;
                    $data_record['createtime'] = time();
                    $id = Db::name('paiming_fenhong_record')->insertGetId($data_record);
                }
            }
        }
    }
	//结算佣金 设置了延时结算时每小时执行
	public static function jiesuanCommission($aid,$admin_set=[]){
        //分销结算时间 fxjiesuantime,0确认收货后,1付款后；延迟几天fxjiesuantime_delaydays
		if(getcustom('fxjiesuantime_perweek',$aid)) return;
        if(empty($admin_set))
            $admin_set = Db::name('admin_set')->where('aid',$aid)->find();
        if(empty($admin_set))
            return;
        $where = [];
        $where[] = ['aid', '=', $aid];
        $where[] = ['status', '=', 0];
        $where[] = ['type', 'in', ['shop','seckill','scoreshop','collage','lucky_collage','kecheng','tuangou','fishpond','hotel']];
        $dtime = time();
        if($admin_set['fxjiesuantime_delaydays'] > 0){
            $delaytime = floatval($admin_set['fxjiesuantime_delaydays']) * 86400;
            $dtime -= $delaytime;
            $where[] = ['createtime', '<', $dtime];
//            $where[] = ['createtime', '>', $dtime-300*86400];//部分客户很长时间未收货，改为300天
        }else{
            $where[] = ['createtime', '<', time()];
        }
        $recordList = Db::name('member_commission_record')->where($where)->select()->toArray();
		foreach($recordList as $k=>$record){
			$order = Db::name($record['type'].'_order')->where('id',$record['orderid'])->find();
			if(!$order || $order['status'] == 4){
				Db::name('member_commission_record')->where('id',$record['id'])->update(['status'=>2]);
				continue;
			}
			$status = $order['status'];
			if($record['type'] == 'kecheng' && $status == 1) $status = 3;
			if($record['type'] == 'hotel' && $status == 1) $status = 0; //已支付未确认
			if($record['type'] == 'hotel' && $status == 4) $status = 3; //已离店  0未支付;1已支付;2已确认 ,3已到店 4已离店 -1已关闭
			if(($admin_set['fxjiesuantime'] == 0 && $status==3 && $order['paytime'] < $dtime) || ($admin_set['fxjiesuantime'] == 1 && in_array($status,[1,2,3]) && $order['paytime'] < $dtime)){
				self::giveCommission($order,$record['type']);
			}
		}
	}
	//结算佣金 每周几结算
	public static function jiesuanCommissionWeek($aid,$admin_set=[]){
		if(!getcustom('fxjiesuantime_perweek',$aid)) return;
        if(empty($admin_set))
            $admin_set = Db::name('admin_set')->where('aid',$aid)->find();
		$week = $admin_set['fxjiesuantime_delaydays'];
		if($week == 0) return;
		if($week == 7) $week = 0;
		if($week != date('w')) return;
		$dtime = strtotime(date('Y-m-d',time()-86400));
		$recordList = Db::name('member_commission_record')->where('aid',$aid)->where('status',0)
            ->where('type','in',['shop','seckill','scoreshop','collage','lucky_collage','kecheng','tuangou','fishpond'])->where('createtime','<',$dtime)
            /*->where('createtime','>',$dtime-30*86400)*/->select()->toArray();
		foreach($recordList as $k=>$record){
			$order = Db::name($record['type'].'_order')->where('id',$record['orderid'])->find();
			if(!$order || $order['status'] == 4){
				Db::name('member_commission_record')->where('id',$record['id'])->update(['status'=>2]);
				continue;
			}
			$status = $order['status'];
			if($record['type'] == 'kecheng' && $status == 1) $status = 3;
			if(getcustom('commission_times_coupon',$aid)){
                if($record['type'] == 'coupon' && $status == 1) $status = 3;
            }
			if(($admin_set['fxjiesuantime'] == 0 && $status==3 && $order['paytime'] < $dtime) || ($admin_set['fxjiesuantime'] == 1 && in_array($status,[1,2,3]) && $order['paytime'] < $dtime)){
				self::giveCommission($order,$record['type']);
			}
		}
	}

	//发放佣金
	public static function giveCommission($order,$type='shop'){
		$aid = $order['aid'];
		if($type == 'shop'){
			$commission_record_list = Db::name('member_commission_record')->where('aid',$aid)->where('type','shop')->where('orderid',$order['id'])->where('status',0)->select();
			foreach($commission_record_list as $commission_record){
				if(getcustom('member_shougou_parentreward')){
					//商城订单是否锁住
					if($commission_record['islock'] == 1){
						continue;
					}
				}
				Db::name('member_commission_record')->where('id',$commission_record['id'])->update(['status'=>1,'endtime'=>time()]);
				$og = Db::name('shop_order_goods')->where('id',$commission_record['ogid'])->find();
				Db::name('shop_order_goods')->where('id',$commission_record['ogid'])->update(['iscommission'=>1]);
				if($commission_record['commission'] > 0){
					$commission = $commission_record['commission'];
					if(getcustom('commission2moneypercent')){
						$sysset = Db::name('admin_set')->where('aid',$aid)->find();
						if($sysset['commission2moneypercent1'] > 0){
							//是否是首单
							$beforeorder = Db::name('shop_order')->where('aid',$aid)->where('mid',$order['mid'])->where('status','in','1,2,3')->where('paytime','<',$order['paytime'])->find();
							if(!$beforeorder){
								$commission = (100 - $sysset['commission2moneypercent1']) * $commission_record['commission'] * 0.01;
								$money = $sysset['commission2moneypercent1'] * $commission_record['commission'] * 0.01;
							}else{
								$commission = (100 - $sysset['commission2moneypercent2']) * $commission_record['commission'] * 0.01;
								$money = $sysset['commission2moneypercent2'] * $commission_record['commission'] * 0.01;
							}
							$commission = round($commission,2);
							$money = round($money,2);
							if($money > 0){
								\app\commons\Member::addmoney($aid,$commission_record['mid'],$money,$commission_record['remark']);
							}
						}
					}
                    $levelid = 0;
					if(getcustom('commission_frozen_level')){
					    $member = Db::name('member')->where('id','=',$commission_record['mid'])->find();
                        if($member['levelstarttime'] >= $order['createtime']) {
                            $levelup_order_levelid = Db::name('member_levelup_order')->where('aid',$aid)->where('mid', $commission_record['mid'])->where('status', 2)
                                ->where('levelup_time', '<', $order['createtime'])->order('levelup_time', 'desc')->value('levelid');
                            if($levelup_order_levelid) {
                                $levelid = $levelup_order_levelid;
                            }
                        }
                    }
					\app\commons\Member::addcommission($aid,$commission_record['mid'],$commission_record['frommid'],$commission,$commission_record['remark'],1,'',$levelid);
					$tmplcontent = [];
					$tmplcontent['first'] = '恭喜您，成功分销商品获得'.t('佣金').'：￥'.$commission_record['commission'];
					$tmplcontent['remark'] = '点击进入查看~';
					$tmplcontent['keyword1'] = $og['name']; //商品信息
					$tmplcontent['keyword2'] = (string) $og['sell_price'];//商品单价
					$tmplcontent['keyword3'] = $commission_record['commission'].'元';//商品佣金
					$tmplcontent['keyword4'] = date('Y-m-d H:i:s',$commission_record['createtime']);//分销时间
					$rs = \app\commons\Wechat::sendtmpl($aid,$commission_record['mid'],'tmpl_fenxiaosuccess',$tmplcontent,m_url('pages/my/usercenter', $aid));
                    //短信通知
					$tel = Db::name('member')->where('id',$commission_record['mid'])->value('tel');
                    $rs = \app\commons\Sms::send($aid,$tel,'tmpl_fenxiaosuccess',['money'=>$commission_record['commission']]);
                    //发放团队收益
                    //根据分红奖团队收益
                    if(getcustom('teamfenhong_shouyi')){
                        $sysset = Db::name('admin_set')->where('aid',$aid)->find();
                        $midfhArr = [
                            $commission_record['mid']=>[
                                'commission' => $commission
                            ]
                        ];
                       \app\commons\Fenhong::teamshouyi($aid,$sysset,$midfhArr,[],0,0,1,0);
                    }
				}
				if($commission_record['score'] > 0){
					\app\commons\Member::addscore($aid,$commission_record['mid'],$commission_record['score'],$commission_record['remark']);
					$tmplcontent = [];
                    $tmplcontent['first'] = '恭喜您，成功分销商品获得：'.$commission_record['score'].t('积分');
                    $tmplcontent['remark'] = '点击进入查看~';
                    $tmplcontent['keyword1'] = $og['name']; //商品信息
                    $tmplcontent['keyword2'] = (string) $og['sell_price'];//商品单价
                    $tmplcontent['keyword3'] = $commission_record['score'].t('积分');//商品佣金
                    $tmplcontent['keyword4'] = date('Y-m-d H:i:s',$commission_record['createtime']);//分销时间
                    $rs = \app\commons\Wechat::sendtmpl($aid,$commission_record['mid'],'tmpl_fenxiaosuccess',$tmplcontent,m_url('pages/my/usercenter', $aid));
                    //短信通知
					//$tel = Db::name('member')->where('id',$commission_record['mid'])->value('tel');
                    //$rs = \app\commons\Sms::send($aid,$tel,'tmpl_fenxiaosuccess');
				}
			}
        }elseif($type=='seckill'){
			$commission_record_list = Db::name('member_commission_record')->where('aid',$aid)->where('type','seckill')->where('orderid',$order['id'])->where('status',0)->select();
			foreach($commission_record_list as $commission_record){
				Db::name('member_commission_record')->where('id',$commission_record['id'])->update(['status'=>1,'endtime'=>time()]);
				if($commission_record['commission'] > 0){
					\app\commons\Member::addcommission($aid,$commission_record['mid'],$commission_record['frommid'],$commission_record['commission'],$commission_record['remark']);
					$tmplcontent = [];
					$tmplcontent['first'] = '恭喜您，成功分销商品获得'.t('佣金').'：￥'.$commission_record['commission'];
					$tmplcontent['remark'] = '点击进入查看~';
					$tmplcontent['keyword1'] = $order['proname']; //商品信息
					$tmplcontent['keyword2'] = (string) $order['sell_price'];//商品单价
					$tmplcontent['keyword3'] = $commission_record['commission'].'元';//商品佣金
					$tmplcontent['keyword4'] = date('Y-m-d H:i:s',$commission_record['createtime']);//分销时间
					$rs = \app\commons\Wechat::sendtmpl($aid,$commission_record['mid'],'tmpl_fenxiaosuccess',$tmplcontent,m_url('pages/my/usercenter', $aid));
                    //短信通知
					$tel = Db::name('member')->where('id',$commission_record['mid'])->value('tel');
                    $rs = \app\commons\Sms::send($aid,$tel,'tmpl_fenxiaosuccess',['money'=>$commission_record['commission']]);
                    //根据分红奖团队收益
                    if(getcustom('teamfenhong_shouyi')){
                        $sysset = Db::name('admin_set')->where('aid',$aid)->find();
                        $midfhArr = [
                            $commission_record['mid']=>[
                                'commission' => $commission_record['commission']
                            ]
                        ];
                       \app\commons\Fenhong::teamshouyi($aid,$sysset,$midfhArr,[],0,0,1,0);
                    }
				}
				if($commission_record['score'] > 0){
					\app\commons\Member::addscore($aid,$commission_record['mid'],$commission_record['score'],$commission_record['remark']);
					$tmplcontent = [];
                    $tmplcontent['first'] = '恭喜您，成功分销商品获得：'.$commission_record['score'].t('积分');
                    $tmplcontent['remark'] = '点击进入查看~';
                    $tmplcontent['keyword1'] = $order['proname']; //商品信息
                    $tmplcontent['keyword2'] = (string) $order['sell_price'];//商品单价
                    $tmplcontent['keyword3'] = $commission_record['score'].t('积分');//商品佣金
                    $tmplcontent['keyword4'] = date('Y-m-d H:i:s',$commission_record['createtime']);//分销时间
                    $rs = \app\commons\Wechat::sendtmpl($aid,$commission_record['mid'],'tmpl_fenxiaosuccess',$tmplcontent,m_url('pages/my/usercenter', $aid));
                    //短信通知
					//$tel = Db::name('member')->where('id',$commission_record['mid'])->value('tel');
                    //$rs = \app\commons\Sms::send($aid,$tel,'tmpl_fenxiaosuccess');
				}
			}
		}elseif($type == 'scoreshop'){
            //佣金
            $commission_record_list = Db::name('member_commission_record')->where('aid',$aid)->where('type',$type)->where('orderid',$order['id'])->where('status',0)->select();
            foreach($commission_record_list as $commission_record){
                Db::name('member_commission_record')->where('id',$commission_record['id'])->update(['status'=>1,'endtime'=>time()]);
                $og = Db::name($type.'_order_goods')->where('id',$commission_record['ogid'])->find();
                Db::name($type.'_order_goods')->where('id',$commission_record['ogid'])->update(['iscommission'=>1]);
                if($commission_record['commission'] > 0){
                    \app\commons\Member::addcommission($aid,$commission_record['mid'],$commission_record['frommid'],$commission_record['commission'],$commission_record['remark']);
                    $tmplcontent = [];
                    $tmplcontent['first'] = '恭喜您，成功分销商品获得'.t('佣金').'：￥'.$commission_record['commission'];
                    $tmplcontent['remark'] = '点击进入查看~';
                    $tmplcontent['keyword1'] = $og['name']; //商品信息
                    $tmplcontent['keyword2'] = (string) $og['sell_price'];//商品单价
                    $tmplcontent['keyword3'] = $commission_record['commission'].'元';//商品佣金
                    $tmplcontent['keyword4'] = date('Y-m-d H:i:s',$commission_record['createtime']);//分销时间
                    $rs = \app\commons\Wechat::sendtmpl($aid,$commission_record['mid'],'tmpl_fenxiaosuccess',$tmplcontent,m_url('pages/my/usercenter', $aid));
                    //短信通知
                    $tel = Db::name('member')->where('id',$commission_record['mid'])->value('tel');
                    $rs = \app\commons\Sms::send($aid,$tel,'tmpl_fenxiaosuccess',['money'=>$commission_record['commission']]);
                    //根据分红奖团队收益
                    if(getcustom('teamfenhong_shouyi')){
                        $sysset = Db::name('admin_set')->where('aid',$aid)->find();
                        $midfhArr = [
                            $commission_record['mid']=>[
                                'commission' => $commission_record['commission']
                            ]
                        ];
                       \app\commons\Fenhong::teamshouyi($aid,$sysset,$midfhArr,[],0,0,1,0);
                    }
                }
                if($commission_record['score'] > 0){
                    \app\commons\Member::addscore($aid,$commission_record['mid'],$commission_record['score'],$commission_record['remark']);
                    $tmplcontent = [];
                    $tmplcontent['first'] = '恭喜您，成功分销商品获得：'.$commission_record['score'].t('积分');
                    $tmplcontent['remark'] = '点击进入查看~';
                    $tmplcontent['keyword1'] = $og['name']; //商品信息
                    $tmplcontent['keyword2'] = $og['sell_price'];//商品单价
                    $tmplcontent['keyword3'] = $commission_record['score'].t('积分');//商品佣金
                    $tmplcontent['keyword4'] = date('Y-m-d H:i:s',$commission_record['createtime']);//分销时间
                    $rs = \app\commons\Wechat::sendtmpl($aid,$commission_record['mid'],'tmpl_fenxiaosuccess',$tmplcontent,m_url('pages/my/usercenter', $aid));
                    //短信通知
                    //$tel = Db::name('member')->where('id',$commission_record['mid'])->value('tel');
                    //$rs = \app\commons\Sms::send($aid,$tel,'tmpl_fenxiaosuccess');
                }
            }
        }elseif($type == 'collage'){
            //佣金
            $commission_record_list = Db::name('member_commission_record')->where('aid',$aid)->where('type',$type)->where('orderid',$order['id'])->where('status',0)->select();
            foreach($commission_record_list as $commission_record){
                Db::name('member_commission_record')->where('id',$commission_record['id'])->update(['status'=>1,'endtime'=>time()]);
                $og = Db::name($type.'_order')->where('id',$commission_record['orderid'])->find();
                if($commission_record['commission'] > 0){
                    \app\commons\Member::addcommission($aid,$commission_record['mid'],$commission_record['frommid'],$commission_record['commission'],$commission_record['remark']);
                    $tmplcontent = [];
                    $tmplcontent['first'] = '恭喜您，成功分销商品获得'.t('佣金').'：￥'.$commission_record['commission'];
                    $tmplcontent['remark'] = '点击进入查看~';
                    $tmplcontent['keyword1'] = $og['proname']; //商品信息
                    $tmplcontent['keyword2'] = $og['sell_price'];//商品单价
                    $tmplcontent['keyword3'] = $commission_record['commission'].'元';//商品佣金
                    $tmplcontent['keyword4'] = date('Y-m-d H:i:s',$commission_record['createtime']);//分销时间
                    $rs = \app\commons\Wechat::sendtmpl($aid,$commission_record['mid'],'tmpl_fenxiaosuccess',$tmplcontent,m_url('pages/my/usercenter', $aid));
                    //短信通知
                    $tel = Db::name('member')->where('id',$commission_record['mid'])->value('tel');
                    $rs = \app\commons\Sms::send($aid,$tel,'tmpl_fenxiaosuccess',['money'=>$commission_record['commission']]);
                    //根据分红奖团队收益
                    if(getcustom('teamfenhong_shouyi')){
                        $sysset = Db::name('admin_set')->where('aid',$aid)->find();
                        $midfhArr = [
                            $commission_record['mid']=>[
                                'commission' => $commission_record['commission']
                            ]
                        ];
                       \app\commons\Fenhong::teamshouyi($aid,$sysset,$midfhArr,[],0,0,1,0);
                    }
                }
                if($commission_record['score'] > 0){
                    \app\commons\Member::addscore($aid,$commission_record['mid'],$commission_record['score'],$commission_record['remark']);
                    $tmplcontent = [];
                    $tmplcontent['first'] = '恭喜您，成功分销商品获得：'.$commission_record['score'].t('积分');
                    $tmplcontent['remark'] = '点击进入查看~';
                    $tmplcontent['keyword1'] = $og['proname']; //商品信息
                    $tmplcontent['keyword2'] = $og['sell_price'];//商品单价
                    $tmplcontent['keyword3'] = $commission_record['score'].t('积分');//商品佣金
                    $tmplcontent['keyword4'] = date('Y-m-d H:i:s',$commission_record['createtime']);//分销时间
                    $rs = \app\commons\Wechat::sendtmpl($aid,$commission_record['mid'],'tmpl_fenxiaosuccess',$tmplcontent,m_url('pages/my/usercenter', $aid));
                    //短信通知
                    //$tel = Db::name('member')->where('id',$commission_record['mid'])->value('tel');
                    //$rs = \app\commons\Sms::send($aid,$tel,'tmpl_fenxiaosuccess');
                }
            }
        }elseif($type == 'kanjia'){
            //佣金
            $commission_record_list = Db::name('member_commission_record')->where('aid',$aid)->where('type',$type)->where('orderid',$order['id'])->where('status',0)->select();
            foreach($commission_record_list as $commission_record){
                Db::name('member_commission_record')->where('id',$commission_record['id'])->update(['status'=>1,'endtime'=>time()]);
                $og = Db::name($type.'_order')->where('id',$commission_record['orderid'])->find();
                if($commission_record['commission'] > 0){
                    \app\commons\Member::addcommission($aid,$commission_record['mid'],$commission_record['frommid'],$commission_record['commission'],$commission_record['remark']);
                    $tmplcontent = [];
                    $tmplcontent['first'] = '恭喜您，成功分销商品获得'.t('佣金').'：￥'.$commission_record['commission'];
                    $tmplcontent['remark'] = '点击进入查看~';
                    $tmplcontent['keyword1'] = $og['proname']; //商品信息
                    $tmplcontent['keyword2'] = $og['product_price'];//商品单价
                    $tmplcontent['keyword3'] = $commission_record['commission'].'元';//商品佣金
                    $tmplcontent['keyword4'] = date('Y-m-d H:i:s',$commission_record['createtime']);//分销时间
                    $rs = \app\commons\Wechat::sendtmpl($aid,$commission_record['mid'],'tmpl_fenxiaosuccess',$tmplcontent,m_url('pages/my/usercenter', $aid));
                    //短信通知
                    $tel = Db::name('member')->where('id',$commission_record['mid'])->value('tel');
                    $rs = \app\commons\Sms::send($aid,$tel,'tmpl_fenxiaosuccess',['money'=>$commission_record['commission']]);
                    //根据分红奖团队收益
                    if(getcustom('teamfenhong_shouyi')){
                        $sysset = Db::name('admin_set')->where('aid',$aid)->find();
                        $midfhArr = [
                            $commission_record['mid']=>[
                                'commission' => $commission_record['commission']
                            ]
                        ];
                       \app\commons\Fenhong::teamshouyi($aid,$sysset,$midfhArr,[],0,0,1,0);
                    }
                }
                if($commission_record['score'] > 0){
                    \app\commons\Member::addscore($aid,$commission_record['mid'],$commission_record['score'],$commission_record['remark']);
                    $tmplcontent = [];
                    $tmplcontent['first'] = '恭喜您，成功分销商品获得：'.$commission_record['score'].t('积分');
                    $tmplcontent['remark'] = '点击进入查看~';
                    $tmplcontent['keyword1'] = $og['proname']; //商品信息
                    $tmplcontent['keyword2'] = $og['product_price'];//商品单价
                    $tmplcontent['keyword3'] = $commission_record['score'].t('积分');//商品佣金
                    $tmplcontent['keyword4'] = date('Y-m-d H:i:s',$commission_record['createtime']);//分销时间
                    $rs = \app\commons\Wechat::sendtmpl($aid,$commission_record['mid'],'tmpl_fenxiaosuccess',$tmplcontent,m_url('pages/my/usercenter', $aid));
                    //短信通知
                    //$tel = Db::name('member')->where('id',$commission_record['mid'])->value('tel');
                    //$rs = \app\commons\Sms::send($aid,$tel,'tmpl_fenxiaosuccess');
                }
            }
        }elseif($type == 'lucky_collage'){
            //佣金
            $commission_record_list = Db::name('member_commission_record')->where('aid',$aid)->where('type',$type)->where('orderid',$order['id'])->where('status',0)->select();
            foreach($commission_record_list as $commission_record){
				Db::name('member_commission_record')->where('id',$commission_record['id'])->update(['status'=>1,'endtime'=>time()]);
                $og = Db::name($type.'_order')->where('id',$commission_record['orderid'])->find();
                if($commission_record['commission'] > 0){
                    \app\commons\Member::addcommission($aid,$commission_record['mid'],$commission_record['frommid'],$commission_record['commission'],$commission_record['remark']);
                    $tmplcontent = [];
                    $tmplcontent['first'] = '恭喜您，成功分销商品获得'.t('佣金').'：￥'.$commission_record['commission'];
                    $tmplcontent['remark'] = '点击进入查看~';
                    $tmplcontent['keyword1'] = $og['proname']; //商品信息
                    $tmplcontent['keyword2'] = $og['sell_price'];//商品单价
                    $tmplcontent['keyword3'] = $commission_record['commission'].'元';//商品佣金
                    $tmplcontent['keyword4'] = date('Y-m-d H:i:s',$commission_record['createtime']);//分销时间
                    $rs = \app\commons\Wechat::sendtmpl($aid,$commission_record['mid'],'tmpl_fenxiaosuccess',$tmplcontent,m_url('pages/my/usercenter', $aid));
                    //短信通知
                    $tel = Db::name('member')->where('id',$commission_record['mid'])->value('tel');
                    $rs = \app\commons\Sms::send($aid,$tel,'tmpl_fenxiaosuccess',['money'=>$commission_record['commission']]);
                    //根据分红奖团队收益
                    if(getcustom('teamfenhong_shouyi')){
                        $sysset = Db::name('admin_set')->where('aid',$aid)->find();
                        $midfhArr = [
                            $commission_record['mid']=>[
                                'commission' => $commission_record['commission']
                            ]
                        ];
                       \app\commons\Fenhong::teamshouyi($aid,$sysset,$midfhArr,[],0,0,1,0);
                    }
                }
                if($commission_record['score'] > 0){
                    \app\commons\Member::addscore($aid,$commission_record['mid'],$commission_record['score'],$commission_record['remark']);
                    $tmplcontent = [];
                    $tmplcontent['first'] = '恭喜您，成功分销商品获得：'.$commission_record['score'].t('积分');
                    $tmplcontent['remark'] = '点击进入查看~';
                    $tmplcontent['keyword1'] = $og['proname']; //商品信息
                    $tmplcontent['keyword2'] = $og['sell_price'];//商品单价
                    $tmplcontent['keyword3'] = $commission_record['score'].t('积分');//商品佣金
                    $tmplcontent['keyword4'] = date('Y-m-d H:i:s',$commission_record['createtime']);//分销时间
                    $rs = \app\commons\Wechat::sendtmpl($aid,$commission_record['mid'],'tmpl_fenxiaosuccess',$tmplcontent,m_url('pages/my/usercenter', $aid));
                    //短信通知
                    //$tel = Db::name('member')->where('id',$commission_record['mid'])->value('tel');
                    //$rs = \app\commons\Sms::send($aid,$tel,'tmpl_fenxiaosuccess');
                }

            }
        }elseif($type == 'kecheng'){
            //佣金
            $commission_record_list = Db::name('member_commission_record')->where('aid',$aid)->where('type',$type)->where('orderid',$order['id'])->where('status',0)->select();
            foreach($commission_record_list as $commission_record){
                Db::name('member_commission_record')->where('id',$commission_record['id'])->update(['status'=>1,'endtime'=>time()]);
                $og = Db::name($type.'_order')->where('id',$commission_record['orderid'])->find();
                if($commission_record['commission'] > 0){
                    \app\commons\Member::addcommission($aid,$commission_record['mid'],$commission_record['frommid'],$commission_record['commission'],$commission_record['remark']);
                    $tmplcontent = [];
                    $tmplcontent['first'] = '恭喜您，成功分销商品获得'.t('佣金').'：￥'.$commission_record['commission'];
                    $tmplcontent['remark'] = '点击进入查看~';
                    $tmplcontent['keyword1'] = $og['proname']; //商品信息
                    $tmplcontent['keyword2'] = $og['price'];//商品单价
                    $tmplcontent['keyword3'] = $commission_record['commission'].'元';//商品佣金
                    $tmplcontent['keyword4'] = date('Y-m-d H:i:s',$commission_record['createtime']);//分销时间
                    $rs = \app\commons\Wechat::sendtmpl($aid,$commission_record['mid'],'tmpl_fenxiaosuccess',$tmplcontent,m_url('pages/my/usercenter', $aid));
                    //短信通知
                    $tel = Db::name('member')->where('id',$commission_record['mid'])->value('tel');
                    $rs = \app\commons\Sms::send($aid,$tel,'tmpl_fenxiaosuccess',['money'=>$commission_record['commission']]);
                    //根据分红奖团队收益
                    if(getcustom('teamfenhong_shouyi')){
                        $sysset = Db::name('admin_set')->where('aid',$aid)->find();
                        $midfhArr = [
                            $commission_record['mid']=>[
                                'commission' => $commission_record['commission']
                            ]
                        ];
                       \app\commons\Fenhong::teamshouyi($aid,$sysset,$midfhArr,[],0,0,1,0);
                    }
                }
                if($commission_record['score'] > 0){
                    \app\commons\Member::addscore($aid,$commission_record['mid'],$commission_record['score'],$commission_record['remark']);
                    $tmplcontent = [];
                    $tmplcontent['first'] = '恭喜您，成功分销商品获得：'.$commission_record['score'].t('积分');
                    $tmplcontent['remark'] = '点击进入查看~';
                    $tmplcontent['keyword1'] = $og['proname']; //商品信息
                    $tmplcontent['keyword2'] = $og['price'];//商品单价
                    $tmplcontent['keyword3'] = $commission_record['score'].t('积分');//商品佣金
                    $tmplcontent['keyword4'] = date('Y-m-d H:i:s',$commission_record['createtime']);//分销时间
                    $rs = \app\commons\Wechat::sendtmpl($aid,$commission_record['mid'],'tmpl_fenxiaosuccess',$tmplcontent,m_url('pages/my/usercenter', $aid));
                    //短信通知
                    //$tel = Db::name('member')->where('id',$commission_record['mid'])->value('tel');
                    //$rs = \app\commons\Sms::send($aid,$tel,'tmpl_fenxiaosuccess');
                }
            }
        }elseif($type=='tuangou'){
			$commission_record_list = Db::name('member_commission_record')->where('aid',$aid)->where('type',$type)->where('orderid',$order['id'])->where('status',0)->select();
			foreach($commission_record_list as $commission_record){
				Db::name('member_commission_record')->where('id',$commission_record['id'])->update(['status'=>1,'endtime'=>time()]);
				if($commission_record['commission'] > 0){
					\app\commons\Member::addcommission($aid,$commission_record['mid'],$commission_record['frommid'],$commission_record['commission'],$commission_record['remark']);
					$tmplcontent = [];
					$tmplcontent['first'] = '恭喜您，成功分销商品获得'.t('佣金').'：￥'.$commission_record['commission'];
					$tmplcontent['remark'] = '点击进入查看~';
					$tmplcontent['keyword1'] = $order['proname']; //商品信息
					$tmplcontent['keyword2'] = (string) $order['sell_price'];//商品单价
					$tmplcontent['keyword3'] = $commission_record['commission'].'元';//商品佣金
					$tmplcontent['keyword4'] = date('Y-m-d H:i:s',$commission_record['createtime']);//分销时间
					$rs = \app\commons\Wechat::sendtmpl($aid,$commission_record['mid'],'tmpl_fenxiaosuccess',$tmplcontent,m_url('pages/my/usercenter', $aid));
                    //短信通知
					$tel = Db::name('member')->where('id',$commission_record['mid'])->value('tel');
                    $rs = \app\commons\Sms::send($aid,$tel,'tmpl_fenxiaosuccess',['money'=>$commission_record['commission']]);
                    //根据分红奖团队收益
                    if(getcustom('teamfenhong_shouyi')){
                        $sysset = Db::name('admin_set')->where('aid',$aid)->find();
                        $midfhArr = [
                            $commission_record['mid']=>[
                                'commission' => $commission_record['commission']
                            ]
                        ];
                       \app\commons\Fenhong::teamshouyi($aid,$sysset,$midfhArr,[],0,0,1,0);
                    }
				}
				if($commission_record['score'] > 0){
					\app\commons\Member::addscore($aid,$commission_record['mid'],$commission_record['score'],$commission_record['remark']);
					$tmplcontent = [];
                    $tmplcontent['first'] = '恭喜您，成功分销商品获得：'.$commission_record['score'].t('积分');
                    $tmplcontent['remark'] = '点击进入查看~';
                    $tmplcontent['keyword1'] = $order['proname']; //商品信息
                    $tmplcontent['keyword2'] = (string) $order['sell_price'];//商品单价
                    $tmplcontent['keyword3'] = $commission_record['score'].t('积分');//商品佣金
                    $tmplcontent['keyword4'] = date('Y-m-d H:i:s',$commission_record['createtime']);//分销时间
                    $rs = \app\commons\Wechat::sendtmpl($aid,$commission_record['mid'],'tmpl_fenxiaosuccess',$tmplcontent,m_url('pages/my/usercenter', $aid));
                    //短信通知
					//$tel = Db::name('member')->where('id',$commission_record['mid'])->value('tel');
                    //$rs = \app\commons\Sms::send($aid,$tel,'tmpl_fenxiaosuccess');
				}
			}
		}elseif($type == 'yuyue'){
            //佣金
            $commission_record_list = Db::name('member_commission_record')->where('aid',$aid)->where('type',$type)->where('orderid',$order['id'])->where('status',0)->select();
            foreach($commission_record_list as $commission_record){
				Db::name('member_commission_record')->where('id',$commission_record['id'])->update(['status'=>1,'endtime'=>time()]);
                $og = Db::name($type.'_order')->where('id',$commission_record['orderid'])->find();
                if($commission_record['commission'] > 0){
                    \app\commons\Member::addcommission($aid,$commission_record['mid'],$commission_record['frommid'],$commission_record['commission'],$commission_record['remark']);
                    $tmplcontent = [];
                    $tmplcontent['first'] = '恭喜您，成功分销服务获得'.t('佣金').'：￥'.$commission_record['commission'];
                    $tmplcontent['remark'] = '点击进入查看~';
                    $tmplcontent['keyword1'] = $og['proname']; //商品信息
                    $tmplcontent['keyword2'] = $og['sell_price'];//商品单价
                    $tmplcontent['keyword3'] = $commission_record['commission'].'元';//商品佣金
                    $tmplcontent['keyword4'] = date('Y-m-d H:i:s',$commission_record['createtime']);//分销时间
                    $rs = \app\commons\Wechat::sendtmpl($aid,$commission_record['mid'],'tmpl_fenxiaosuccess',$tmplcontent,m_url('pages/my/usercenter', $aid));
                    //短信通知
                    $tel = Db::name('member')->where('id',$commission_record['mid'])->value('tel');
                    $rs = \app\commons\Sms::send($aid,$tel,'tmpl_fenxiaosuccess',['money'=>$commission_record['commission']]);
                    //根据分红奖团队收益
                    if(getcustom('teamfenhong_shouyi')){
                        $sysset = Db::name('admin_set')->where('aid',$aid)->find();
                        $midfhArr = [
                            $commission_record['mid']=>[
                                'commission' => $commission_record['commission']
                            ]
                        ];
                        \app\commons\Fenhong::teamshouyi($aid,$sysset,$midfhArr,[],0,0,1,0);
                    }
                }
                if($commission_record['score'] > 0){
                    \app\commons\Member::addscore($aid,$commission_record['mid'],$commission_record['score'],$commission_record['remark']);
                    $tmplcontent = [];
                    $tmplcontent['first'] = '恭喜您，成功分销服务获得：'.$commission_record['score'].t('积分');
                    $tmplcontent['remark'] = '点击进入查看~';
                    $tmplcontent['keyword1'] = $og['proname']; //商品信息
                    $tmplcontent['keyword2'] = $og['sell_price'];//商品单价
                    $tmplcontent['keyword3'] = $commission_record['score'].t('积分');//商品佣金
                    $tmplcontent['keyword4'] = date('Y-m-d H:i:s',$commission_record['createtime']);//分销时间
                    $rs = \app\commons\Wechat::sendtmpl($aid,$commission_record['mid'],'tmpl_fenxiaosuccess',$tmplcontent,m_url('pages/my/usercenter', $aid));
                    //短信通知
                    //$tel = Db::name('member')->where('id',$commission_record['mid'])->value('tel');
                    //$rs = \app\commons\Sms::send($aid,$tel,'tmpl_fenxiaosuccess');
                }

            }
        }elseif($type=='cashier'){
            //佣金
            $commission_record_list = Db::name('member_commission_record')->where('aid',$aid)->where('type',$type)->where('orderid',$order['id'])->where('status',0)->select();
            foreach($commission_record_list as $commission_record){
                Db::name('member_commission_record')->where('id',$commission_record['id'])->update(['status'=>1,'endtime'=>time()]);
                $og = Db::name($type.'_order')->where('id',$commission_record['orderid'])->find();
                if($commission_record['commission'] > 0){
                    \app\commons\Member::addcommission($aid,$commission_record['mid'],$commission_record['frommid'],$commission_record['commission'],$commission_record['remark']);
                    $tmplcontent = [];
                    $tmplcontent['first'] = '恭喜您，成功分销服务获得'.t('佣金').'：￥'.$commission_record['commission'];
                    $tmplcontent['remark'] = '点击进入查看~';
                    $tmplcontent['keyword1'] = $og['proname']; //商品信息
                    $tmplcontent['keyword2'] = $og['sell_price'];//商品单价
                    $tmplcontent['keyword3'] = $commission_record['commission'].'元';//商品佣金
                    $tmplcontent['keyword4'] = date('Y-m-d H:i:s',$commission_record['createtime']);//分销时间
                    $rs = \app\commons\Wechat::sendtmpl($aid,$commission_record['mid'],'tmpl_fenxiaosuccess',$tmplcontent,m_url('pages/my/usercenter', $aid));
                    //短信通知
                    $tel = Db::name('member')->where('id',$commission_record['mid'])->value('tel');
                    $rs = \app\commons\Sms::send($aid,$tel,'tmpl_fenxiaosuccess',['money'=>$commission_record['commission']]);
                    //根据分红奖团队收益
                    if(getcustom('teamfenhong_shouyi')){
                        $sysset = Db::name('admin_set')->where('aid',$aid)->find();
                        $midfhArr = [
                            $commission_record['mid']=>[
                                'commission' => $commission_record['commission']
                            ]
                        ];
                       \app\commons\Fenhong::teamshouyi($aid,$sysset,$midfhArr,[],0,0,1,0);
                    }
                }
                if($commission_record['score'] > 0){
                    \app\commons\Member::addscore($aid,$commission_record['mid'],$commission_record['score'],$commission_record['remark']);
                    $tmplcontent = [];
                    $tmplcontent['first'] = '恭喜您，成功分销服务获得：'.$commission_record['score'].t('积分');
                    $tmplcontent['remark'] = '点击进入查看~';
                    $tmplcontent['keyword1'] = $og['proname']; //商品信息
                    $tmplcontent['keyword2'] = $og['sell_price'];//商品单价
                    $tmplcontent['keyword3'] = $commission_record['score'].t('积分');//商品佣金
                    $tmplcontent['keyword4'] = date('Y-m-d H:i:s',$commission_record['createtime']);//分销时间
                    $rs = \app\commons\Wechat::sendtmpl($aid,$commission_record['mid'],'tmpl_fenxiaosuccess',$tmplcontent,m_url('pages/my/usercenter', $aid));
                    //短信通知
                    //$tel = Db::name('member')->where('id',$commission_record['mid'])->value('tel');
                    //$rs = \app\commons\Sms::send($aid,$tel,'tmpl_fenxiaosuccess');
                }

            }
        }elseif($type=='restaurant_shop'){
            //佣金
            $commission_record_list = Db::name('member_commission_record')->where('aid',$aid)->where('type',$type)->where('orderid',$order['id'])->where('status',0)->select();
            foreach($commission_record_list as $commission_record){
                Db::name('member_commission_record')->where('id',$commission_record['id'])->update(['status'=>1,'endtime'=>time()]);
                $og = Db::name($type.'_order')->where('id',$commission_record['orderid'])->find();
                if($commission_record['commission'] > 0){
                    \app\commons\Member::addcommission($aid,$commission_record['mid'],$commission_record['frommid'],$commission_record['commission'],$commission_record['remark']);
                    $tmplcontent = [];
                    $tmplcontent['first'] = '恭喜您，成功分销服务获得'.t('佣金').'：￥'.$commission_record['commission'];
                    $tmplcontent['remark'] = '点击进入查看~';
                    $tmplcontent['keyword1'] = $og['proname']; //商品信息
                    $tmplcontent['keyword2'] = $og['sell_price'];//商品单价
                    $tmplcontent['keyword3'] = $commission_record['commission'].'元';//商品佣金
                    $tmplcontent['keyword4'] = date('Y-m-d H:i:s',$commission_record['createtime']);//分销时间
                    $rs = \app\commons\Wechat::sendtmpl($aid,$commission_record['mid'],'tmpl_fenxiaosuccess',$tmplcontent,m_url('pages/my/usercenter', $aid));
                    //短信通知
                    $tel = Db::name('member')->where('id',$commission_record['mid'])->value('tel');
                    $rs = \app\commons\Sms::send($aid,$tel,'tmpl_fenxiaosuccess',['money'=>$commission_record['commission']]);
                    //根据分红奖团队收益
                    if(getcustom('teamfenhong_shouyi')){
                        $sysset = Db::name('admin_set')->where('aid',$aid)->find();
                        $midfhArr = [
                            $commission_record['mid']=>[
                                'commission' => $commission_record['commission']
                            ]
                        ];
                       \app\commons\Fenhong::teamshouyi($aid,$sysset,$midfhArr,[],0,0,1,0);
                    }
                }
                if($commission_record['score'] > 0){
                    \app\commons\Member::addscore($aid,$commission_record['mid'],$commission_record['score'],$commission_record['remark']);
                    $tmplcontent = [];
                    $tmplcontent['first'] = '恭喜您，成功分销服务获得：'.$commission_record['score'].t('积分');
                    $tmplcontent['remark'] = '点击进入查看~';
                    $tmplcontent['keyword1'] = $og['proname']; //商品信息
                    $tmplcontent['keyword2'] = $og['sell_price'];//商品单价
                    $tmplcontent['keyword3'] = $commission_record['score'].t('积分');//商品佣金
                    $tmplcontent['keyword4'] = date('Y-m-d H:i:s',$commission_record['createtime']);//分销时间
                    $rs = \app\commons\Wechat::sendtmpl($aid,$commission_record['mid'],'tmpl_fenxiaosuccess',$tmplcontent,m_url('pages/my/usercenter', $aid));
                    //短信通知
                    //$tel = Db::name('member')->where('id',$commission_record['mid'])->value('tel');
                    //$rs = \app\commons\Sms::send($aid,$tel,'tmpl_fenxiaosuccess');
                }
            }
        }elseif($type=='coupon'){
		    if(getcustom('commission_times_coupon')){
                //佣金
                $commission_record_list = Db::name('member_commission_record')->where('aid',$aid)->where('type',$type)->where('orderid',$order['id'])->where('status',0)->select();
                foreach($commission_record_list as $commission_record){
                    Db::name('member_commission_record')->where('id',$commission_record['id'])->update(['status'=>1,'endtime'=>time()]);
                    $og = Db::name($type.'_order')->where('id',$commission_record['orderid'])->find();
                    if($commission_record['commission'] > 0){
                        \app\commons\Member::addcommission($aid,$commission_record['mid'],$commission_record['frommid'],$commission_record['commission'],$commission_record['remark']);
                        $tmplcontent = [];
                        $tmplcontent['first'] = '恭喜您，成功分销服务获得'.t('佣金').'：￥'.$commission_record['commission'];
                        $tmplcontent['remark'] = '点击进入查看~';
                        $tmplcontent['keyword1'] = $og['proname']; //商品信息
                        $tmplcontent['keyword2'] = $og['sell_price'];//商品单价
                        $tmplcontent['keyword3'] = $commission_record['commission'].'元';//商品佣金
                        $tmplcontent['keyword4'] = date('Y-m-d H:i:s',$commission_record['createtime']);//分销时间
                        $rs = \app\commons\Wechat::sendtmpl($aid,$commission_record['mid'],'tmpl_fenxiaosuccess',$tmplcontent,m_url('pages/my/usercenter', $aid));
                        //短信通知
                        $tel = Db::name('member')->where('id',$commission_record['mid'])->value('tel');
                        $rs = \app\commons\Sms::send($aid,$tel,'tmpl_fenxiaosuccess',['money'=>$commission_record['commission']]);
                    }
                    if($commission_record['score'] > 0){
                        \app\commons\Member::addscore($aid,$commission_record['mid'],$commission_record['score'],$commission_record['remark']);
                        $tmplcontent = [];
                        $tmplcontent['first'] = '恭喜您，成功分销服务获得：'.$commission_record['score'].t('积分');
                        $tmplcontent['remark'] = '点击进入查看~';
                        $tmplcontent['keyword1'] = $og['proname']; //商品信息
                        $tmplcontent['keyword2'] = $og['sell_price'];//商品单价
                        $tmplcontent['keyword3'] = $commission_record['score'].t('积分');//商品佣金
                        $tmplcontent['keyword4'] = date('Y-m-d H:i:s',$commission_record['createtime']);//分销时间
                        $rs = \app\commons\Wechat::sendtmpl($aid,$commission_record['mid'],'tmpl_fenxiaosuccess',$tmplcontent,m_url('pages/my/usercenter', $aid));
                        //短信通知
                        //$tel = Db::name('member')->where('id',$commission_record['mid'])->value('tel');
                        //$rs = \app\commons\Sms::send($aid,$tel,'tmpl_fenxiaosuccess');
                    }

                }
            }

        }elseif($type=='livepay'){
            if(getcustom('extend_chongzhi')){
			$commission_record_list = Db::name('member_commission_record')->where('aid',$aid)->where('type','livepay')->where('orderid',$order['id'])->where('status',0)->select();
			foreach($commission_record_list as $commission_record){
				Db::name('member_commission_record')->where('id',$commission_record['id'])->update(['status'=>1,'endtime'=>time()]);
				if($commission_record['commission'] > 0){
					\app\commons\Member::addcommission($aid,$commission_record['mid'],$commission_record['frommid'],$commission_record['commission'],$commission_record['remark']);
					$tmplcontent = [];
					$tmplcontent['first'] = '恭喜您，成功生活缴费分销获得'.t('佣金').'：￥'.$commission_record['commission'];
					$tmplcontent['remark'] = '点击进入查看~';
					$tmplcontent['keyword1'] = $order['type_name']; //商品信息
					$tmplcontent['keyword2'] = (string) $order['pay_money'];//商品单价
					$tmplcontent['keyword3'] = $commission_record['commission'].'元';//商品佣金
					$tmplcontent['keyword4'] = date('Y-m-d H:i:s',$commission_record['createtime']);//分销时间
					$rs = \app\commons\Wechat::sendtmpl($aid,$commission_record['mid'],'tmpl_fenxiaosuccess',$tmplcontent,m_url('pages/my/usercenter', $aid));
                    //短信通知
					$tel = Db::name('member')->where('id',$commission_record['mid'])->value('tel');
                    $rs = \app\commons\Sms::send($aid,$tel,'tmpl_fenxiaosuccess',['money'=>$commission_record['commission']]);
				}
            }
			}
		}elseif($type=='gift_pack'){
            if(getcustom('yx_gift_pack')){
			$commission_record_list = Db::name('member_commission_record')->where('aid',$aid)->where('type','gift_pack')->where('orderid',$order['id'])->where('status',0)->select();
				foreach($commission_record_list as $commission_record){
					Db::name('member_commission_record')->where('id',$commission_record['id'])->update(['status'=>1,'endtime'=>time()]);
					if($commission_record['commission'] > 0){
						\app\commons\Member::addcommission($aid,$commission_record['mid'],$commission_record['frommid'],$commission_record['commission'],$commission_record['remark']);
						$tmplcontent = [];
						$tmplcontent['first'] = '恭喜您，分销获得'.t('佣金').'：￥'.$commission_record['commission'];
						$tmplcontent['remark'] = '点击进入查看~';
						$tmplcontent['keyword1'] = $order['title'];
						$tmplcontent['keyword2'] = (string) $order['sell_price'];
						$tmplcontent['keyword3'] = $commission_record['commission'].'元';
						$tmplcontent['keyword4'] = date('Y-m-d H:i:s',$commission_record['createtime']);//分销时间
						$rs = \app\commons\Wechat::sendtmpl($aid,$commission_record['mid'],'tmpl_fenxiaosuccess',$tmplcontent,m_url('pages/my/usercenter', $aid));
						//短信通知
						$tel = Db::name('member')->where('id',$commission_record['mid'])->value('tel');
						$rs = \app\commons\Sms::send($aid,$tel,'tmpl_fenxiaosuccess',['money'=>$commission_record['commission']]);
					}
				}
			}
		}elseif($type=='business_reward'){
            if(getcustom('business_reward_member')){
                $commission_record_list = Db::name('member_commission_record')->where('aid',$aid)->where('type','business_reward')->where('orderid',$order['id'])->where('status',0)->select();
                foreach($commission_record_list as $commission_record){
                    Db::name('member_commission_record')->where('id',$commission_record['id'])->update(['status'=>1,'endtime'=>time()]);
                    if($commission_record['commission'] > 0){
                        \app\commons\Member::addcommission($aid,$commission_record['mid'],$commission_record['frommid'],$commission_record['commission'],$commission_record['remark']);
                        $tmplcontent = [];
                        $tmplcontent['first'] = '恭喜您，分销获得'.t('佣金').'：￥'.$commission_record['commission'];
                        $tmplcontent['remark'] = '点击进入查看~';
                        $tmplcontent['keyword1'] = $order['title'];
                        $tmplcontent['keyword2'] = (string) $order['sell_price'];
                        $tmplcontent['keyword3'] = $commission_record['commission'].'元';
                        $tmplcontent['keyword4'] = date('Y-m-d H:i:s',$commission_record['createtime']);//分销时间
                        $rs = \app\commons\Wechat::sendtmpl($aid,$commission_record['mid'],'tmpl_fenxiaosuccess',$tmplcontent,m_url('pages/my/usercenter', $aid));
                        //短信通知
                        $tel = Db::name('member')->where('id',$commission_record['mid'])->value('tel');
                        $rs = \app\commons\Sms::send($aid,$tel,'tmpl_fenxiaosuccess',['money'=>$commission_record['commission']]);
                    }
                }
            }
        }else if($type == 'channels'){
            $commission_record_list = Db::name('member_commission_record')->where('aid',$aid)->where('type','channels')->where('orderid',$order['id'])->where('status',0)->select();
            foreach($commission_record_list as $commission_record){
                if(getcustom('member_shougou_parentreward')){
                    //商城订单是否锁住
                    if($commission_record['islock'] == 1){
                        continue;
                    }
                }
                Db::name('member_commission_record')->where('id',$commission_record['id'])->update(['status'=>1,'endtime'=>time()]);
                $og = Db::name('channels_order_goods')->where('id',$commission_record['ogid'])->find();
                Db::name('channels_order_goods')->where('id',$commission_record['ogid'])->update(['iscommission'=>1]);
                if($commission_record['commission'] > 0){
                    $commission = $commission_record['commission'];
                    $levelid = 0;
                    if(getcustom('commission_frozen_level')){
                        $member = Db::name('member')->where('id','=',$commission_record['mid'])->find();
                        if($member['levelstarttime'] >= $order['createtime']) {
                            $levelup_order_levelid = Db::name('member_levelup_order')->where('aid',$aid)->where('mid', $commission_record['mid'])->where('status', 2)
                                ->where('levelup_time', '<', $order['createtime'])->order('levelup_time', 'desc')->value('levelid');
                            if($levelup_order_levelid) {
                                $levelid = $levelup_order_levelid;
                            }
                        }
                    }
                    \app\commons\Member::addcommission($aid,$commission_record['mid'],$commission_record['frommid'],$commission,$commission_record['remark'],'','',$levelid);
                    $tmplcontent = [];
                    $tmplcontent['first'] = '恭喜您，成功分销商品获得'.t('佣金').'：￥'.$commission_record['commission'];
                    $tmplcontent['remark'] = '点击进入查看~';
                    $tmplcontent['keyword1'] = $og['name']; //商品信息
                    $tmplcontent['keyword2'] = (string) $og['sell_price'];//商品单价
                    $tmplcontent['keyword3'] = $commission_record['commission'].'元';//商品佣金
                    $tmplcontent['keyword4'] = date('Y-m-d H:i:s',$commission_record['createtime']);//分销时间
                    $rs = \app\commons\Wechat::sendtmpl($aid,$commission_record['mid'],'tmpl_fenxiaosuccess',$tmplcontent,m_url('pages/my/usercenter', $aid));
                    //短信通知
                    $tel = Db::name('member')->where('id',$commission_record['mid'])->value('tel');
                    $rs = \app\commons\Sms::send($aid,$tel,'tmpl_fenxiaosuccess',['money'=>$commission_record['commission']]);
                    //发放团队收益
                    //根据分红奖团队收益
                    if(getcustom('teamfenhong_shouyi')){
                        $sysset = Db::name('admin_set')->where('aid',$aid)->find();
                        $midfhArr = [
                            $commission_record['mid']=>[
                                'commission' => $commission
                            ]
                        ];
                        \app\commons\Fenhong::teamshouyi($aid,$sysset,$midfhArr,[],0,0,1,0);
                    }
                }
                if($commission_record['score'] > 0){
                    \app\commons\Member::addscore($aid,$commission_record['mid'],$commission_record['score'],$commission_record['remark']);
                    $tmplcontent = [];
                    $tmplcontent['first'] = '恭喜您，成功分销商品获得：'.$commission_record['score'].t('积分');
                    $tmplcontent['remark'] = '点击进入查看~';
                    $tmplcontent['keyword1'] = $og['name']; //商品信息
                    $tmplcontent['keyword2'] = (string) $og['sell_price'];//商品单价
                    $tmplcontent['keyword3'] = $commission_record['score'].t('积分');//商品佣金
                    $tmplcontent['keyword4'] = date('Y-m-d H:i:s',$commission_record['createtime']);//分销时间
                    $rs = \app\commons\Wechat::sendtmpl($aid,$commission_record['mid'],'tmpl_fenxiaosuccess',$tmplcontent,m_url('pages/my/usercenter', $aid));
                    //短信通知
                    //$tel = Db::name('member')->where('id',$commission_record['mid'])->value('tel');
                    //$rs = \app\commons\Sms::send($aid,$tel,'tmpl_fenxiaosuccess');
                }
            }
        }else if($type == 'car_hailing'){
            //佣金
            $commission_record_list = Db::name('member_commission_record')->where('aid',$aid)->where('type',$type)->where('orderid',$order['id'])->where('status',0)->select();
            foreach($commission_record_list as $commission_record){
                Db::name('member_commission_record')->where('id',$commission_record['id'])->update(['status'=>1,'endtime'=>time()]);
                $og = Db::name($type.'_order')->where('id',$commission_record['orderid'])->find();
                if($commission_record['commission'] > 0){
                    \app\commons\Member::addcommission($aid,$commission_record['mid'],$commission_record['frommid'],$commission_record['commission'],$commission_record['remark']);
                    $tmplcontent = [];
                    $tmplcontent['first'] = '恭喜您，成功分销商品获得'.t('佣金').'：￥'.$commission_record['commission'];
                    $tmplcontent['remark'] = '点击进入查看~';
                    $tmplcontent['keyword1'] = $og['proname']; //商品信息
                    $tmplcontent['keyword2'] = $og['sell_price'];//商品单价
                    $tmplcontent['keyword3'] = $commission_record['commission'].'元';//商品佣金
                    $tmplcontent['keyword4'] = date('Y-m-d H:i:s',$commission_record['createtime']);//分销时间
                    $rs = \app\commons\Wechat::sendtmpl($aid,$commission_record['mid'],'tmpl_fenxiaosuccess',$tmplcontent,m_url('pages/my/usercenter', $aid));
                    //短信通知
                    $tel = Db::name('member')->where('id',$commission_record['mid'])->value('tel');
                    $rs = \app\commons\Sms::send($aid,$tel,'tmpl_fenxiaosuccess',['money'=>$commission_record['commission']]);
                    //根据分红奖团队收益
                    if(getcustom('teamfenhong_shouyi')){
                        $sysset = Db::name('admin_set')->where('aid',$aid)->find();
                        $midfhArr = [
                            $commission_record['mid']=>[
                                'commission' => $commission_record['commission']
                            ]
                        ];
                        \app\commons\Fenhong::teamshouyi($aid,$sysset,$midfhArr,[],0,0,1,0);
                    }
                }
                if($commission_record['score'] > 0){
                    \app\commons\Member::addscore($aid,$commission_record['mid'],$commission_record['score'],$commission_record['remark']);
                    $tmplcontent = [];
                    $tmplcontent['first'] = '恭喜您，成功分销商品获得：'.$commission_record['score'].t('积分');
                    $tmplcontent['remark'] = '点击进入查看~';
                    $tmplcontent['keyword1'] = $og['proname']; //商品信息
                    $tmplcontent['keyword2'] = $og['sell_price'];//商品单价
                    $tmplcontent['keyword3'] = $commission_record['score'].t('积分');//商品佣金
                    $tmplcontent['keyword4'] = date('Y-m-d H:i:s',$commission_record['createtime']);//分销时间
                    $rs = \app\commons\Wechat::sendtmpl($aid,$commission_record['mid'],'tmpl_fenxiaosuccess',$tmplcontent,m_url('pages/my/usercenter', $aid));
                    //短信通知
                    //$tel = Db::name('member')->where('id',$commission_record['mid'])->value('tel');
                    //$rs = \app\commons\Sms::send($aid,$tel,'tmpl_fenxiaosuccess');
                }
            }
        }elseif($type == 'hotel'){
            //佣金
            $commission_record_list = Db::name('member_commission_record')->where('aid',$aid)->where('type',$type)->where('orderid',$order['id'])->where('status',0)->select();
            foreach($commission_record_list as $commission_record){
				Db::name('member_commission_record')->where('id',$commission_record['id'])->update(['status'=>1,'endtime'=>time()]);
                $og = Db::name($type.'_order')->where('id',$commission_record['orderid'])->find();
                if($commission_record['commission'] > 0){
                    \app\commons\Member::addcommission($aid,$commission_record['mid'],$commission_record['frommid'],$commission_record['commission'],$commission_record['remark']);
                    $tmplcontent = [];
                    $tmplcontent['first'] = '恭喜您，成功分销服务获得'.t('佣金').'：￥'.$commission_record['commission'];
                    $tmplcontent['remark'] = '点击进入查看~';
                    $tmplcontent['keyword1'] = $og['proname']; //商品信息
                    $tmplcontent['keyword2'] = $og['sell_price'];//商品单价
                    $tmplcontent['keyword3'] = $commission_record['commission'].'元';//商品佣金
                    $tmplcontent['keyword4'] = date('Y-m-d H:i:s',$commission_record['createtime']);//分销时间
                    $rs = \app\commons\Wechat::sendtmpl($aid,$commission_record['mid'],'tmpl_fenxiaosuccess',$tmplcontent,m_url('pages/my/usercenter', $aid));
                    //短信通知
                    $tel = Db::name('member')->where('id',$commission_record['mid'])->value('tel');
                    $rs = \app\commons\Sms::send($aid,$tel,'tmpl_fenxiaosuccess',['money'=>$commission_record['commission']]);
                    //根据分红奖团队收益
                    if(getcustom('teamfenhong_shouyi')){
                        $sysset = Db::name('admin_set')->where('aid',$aid)->find();
                        $midfhArr = [
                            $commission_record['mid']=>[
                                'commission' => $commission_record['commission']
                            ]
                        ];
                        \app\commons\Fenhong::teamshouyi($aid,$sysset,$midfhArr,[],0,0,1,0);
                    }
                }
                if($commission_record['score'] > 0){
                    \app\commons\Member::addscore($aid,$commission_record['mid'],$commission_record['score'],$commission_record['remark']);
                    $tmplcontent = [];
                    $tmplcontent['first'] = '恭喜您，成功分销服务获得：'.$commission_record['score'].t('积分');
                    $tmplcontent['remark'] = '点击进入查看~';
                    $tmplcontent['keyword1'] = $og['proname']; //商品信息
                    $tmplcontent['keyword2'] = $og['sell_price'];//商品单价
                    $tmplcontent['keyword3'] = $commission_record['score'].t('积分');//商品佣金
                    $tmplcontent['keyword4'] = date('Y-m-d H:i:s',$commission_record['createtime']);//分销时间
                    $rs = \app\commons\Wechat::sendtmpl($aid,$commission_record['mid'],'tmpl_fenxiaosuccess',$tmplcontent,m_url('pages/my/usercenter', $aid));
                    //短信通知
                    //$tel = Db::name('member')->where('id',$commission_record['mid'])->value('tel');
                    //$rs = \app\commons\Sms::send($aid,$tel,'tmpl_fenxiaosuccess');
                }
            }
        }elseif($type=='fishpond'){
            //佣金
            $commission_record_list = Db::name('member_commission_record')->where('aid',$aid)->where('type',$type)->where('orderid',$order['id'])->where('status',0)->select();
            foreach($commission_record_list as $commission_record){
                Db::name('member_commission_record')->where('id',$commission_record['id'])->update(['status'=>1,'endtime'=>time()]);
                $og = Db::name($type.'_order')->where('id',$commission_record['orderid'])->find();
                if($commission_record['commission'] > 0){
                    \app\commons\Member::addcommission($aid,$commission_record['mid'],$commission_record['frommid'],$commission_record['commission'],$commission_record['remark']);
                    $tmplcontent = [];
                    $tmplcontent['first'] = '恭喜您，成功分销商品获得'.t('佣金').'：￥'.$commission_record['commission'];
                    $tmplcontent['remark'] = '点击进入查看~';
                    $tmplcontent['keyword1'] = $og['proname']; //商品信息
                    $tmplcontent['keyword2'] = $og['sell_price'];//商品单价
                    $tmplcontent['keyword3'] = $commission_record['commission'].'元';//商品佣金
                    $tmplcontent['keyword4'] = date('Y-m-d H:i:s',$commission_record['createtime']);//分销时间
                    $rs = \app\commons\Wechat::sendtmpl($aid,$commission_record['mid'],'tmpl_fenxiaosuccess',$tmplcontent,m_url('pages/my/usercenter', $aid));
                    //短信通知
                    $tel = Db::name('member')->where('id',$commission_record['mid'])->value('tel');
                    $rs = \app\commons\Sms::send($aid,$tel,'tmpl_fenxiaosuccess',['money'=>$commission_record['commission']]);
                }
                if($commission_record['score'] > 0){
                    \app\commons\Member::addscore($aid,$commission_record['mid'],$commission_record['score'],$commission_record['remark']);
                    $tmplcontent = [];
                    $tmplcontent['first'] = '恭喜您，成功分销商品获得：'.$commission_record['score'].t('积分');
                    $tmplcontent['remark'] = '点击进入查看~';
                    $tmplcontent['keyword1'] = $og['proname']; //商品信息
                    $tmplcontent['keyword2'] = $og['sell_price'];//商品单价
                    $tmplcontent['keyword3'] = $commission_record['score'].t('积分');//商品佣金
                    $tmplcontent['keyword4'] = date('Y-m-d H:i:s',$commission_record['createtime']);//分销时间
                    $rs = \app\commons\Wechat::sendtmpl($aid,$commission_record['mid'],'tmpl_fenxiaosuccess',$tmplcontent,m_url('pages/my/usercenter', $aid));
                    //短信通知
                    //$tel = Db::name('member')->where('id',$commission_record['mid'])->value('tel');
                    //$rs = \app\commons\Sms::send($aid,$tel,'tmpl_fenxiaosuccess');
                }
            }
        }

        if(getcustom('commission_butie')){
            $sysset = Db::name('admin_set')->where('aid',$aid)->find();
            $fx_butie_type = $sysset['fx_butie_type'];
            $fx_butie_circle = $sysset['fx_butie_circle'];
            //记录分销补贴
            $butie_ids = [];
            foreach($commission_record_list as $record){
                if($record['butie']<=0){
                    continue;
                }
                //记录补贴数据
                $data_butie = [];
                $data_butie['aid'] = $record['aid'];
                $data_butie['mid'] = $record['mid'];
                $data_butie['frommid'] = $record['frommid'];
                $data_butie['orderid'] = $record['orderid'];
                $data_butie['ogid'] = $record['ogid'];
                $data_butie['type'] = $record['type'];
                $data_butie['commission'] = $record['butie'];
                $data_butie['fx_butie_type'] = $fx_butie_type;
                $data_butie['fx_butie_circle'] = $fx_butie_circle;
                $data_butie['fx_butie_send_week'] = $sysset['fx_butie_send_week'];
                $data_butie['fx_butie_send_day'] = $sysset['fx_butie_send_day'];;
                $data_butie['createtime'] = time();
                $data_butie['record_id'] = $record['id'];
                $data_butie['remark'] = $record['remark'];
                $butie_id = Db::name('member_commission_butie')->insertGetId($data_butie);
                $butie_ids[] = $butie_id;
            }
            if($butie_ids){
                \app\commons\Member::commission_butie($record['aid'],$butie_ids);
            }
        }

		return ['status'=>1,'msg'=>'操作成功'];
	}

    //重新计算分成
    public static function updateCommission($orderGoods, $refundOrderGoods)
    {
        $newkey = 'ogid';
        $refundOrderGoods = collect($refundOrderGoods)->dictionary(null,$newkey);
        $ogids = array_keys($refundOrderGoods);
        foreach ($orderGoods as $og) {
            if(in_array($og['id'], $ogids)) {
                $new = [];
                if($og['parent1'] && ($og['parent1commission'] || $og['parent1score'])) {
                    $record = [];
                    if($og['parent1commission']) {
                        $new['parent1commission'] = $og['parent1commission'] / $og['num'] * ($og['num'] - $og['refund_num']);
                        $record['commission'] = $new['parent1commission'];
                    }
                    if($og['parent1score']) {
                        $new['parent1score'] = $og['parent1score'] / $og['num'] * ($og['num'] - $og['refund_num']);
                        $record['score'] = $new['parent1score'];
                    }
                    if($record) {
                        Db::name('member_commission_record')->where('mid',$og['parent1'])->where('aid',$og['aid'])->where('orderid',$og['orderid'])
                            ->where('ogid', $og['id'])->where('type', 'shop')->update($record);
                    }
                }
                if($og['parent2'] && ($og['parent2commission'] || $og['parent2score'])) {
                    $record = [];
                    if($og['parent2commission']) {
                        $new['parent2commission'] = $og['parent2commission'] / $og['num'] * ($og['num'] - $og['refund_num']);
                        $record['commission'] = $new['parent2commission'];
                    }
                    if($og['parent2score']) {
                        $new['parent2score'] = $og['parent2score'] / $og['num'] * ($og['num'] - $og['refund_num']);
                        $record['score'] = $new['parent2score'];
                    }
                    if($record) {
                        Db::name('member_commission_record')->where('mid',$og['parent2'])->where('aid',$og['aid'])->where('orderid',$og['orderid'])
                            ->where('ogid', $og['id'])->where('type', 'shop')->update($record);
                    }
                }
                if($og['parent3'] && ($og['parent3commission'] || $og['parent3score'])) {
                    $record = [];
                    if($og['parent3commission']) {
                        $new['parent3commission'] = $og['parent3commission'] / $og['num'] * ($og['num'] - $og['refund_num']);
                        $record['commission'] = $new['parent3commission'];
                    }
                    if($og['parent3score']) {
                        $new['parent3score'] = $og['parent3score'] / $og['num'] * ($og['num'] - $og['refund_num']);
                        $record['score'] = $new['parent3score'];
                    }
                    if($record) {
                        Db::name('member_commission_record')->where('mid',$og['parent3'])->where('aid',$og['aid'])->where('orderid',$og['orderid'])
                            ->where('ogid', $og['id'])->where('type', 'shop')->update($record);
                    }
                }
                if($new)
                    Db::name('shop_order_goods')->where('id', $og['id'])->update($new);
            }
        }
    }

    //递归分销:按订单金额的50%[变量]一直向下分销，直到金额小于1[变量]元截止；
    public static function recursionCommission($aid,$mid=0,$totalprice=0,$orderid='',$ogid='',$type='shop'){
        if(getcustom('commission_recursion')) {
            if (empty($mid)) return;
            //所有的父级
            $adminset = Db::name('admin_set')->where('aid', $aid)->field('is_fugou_commission,fugou_recursion_percent,fugou_commission_min')->find();
            if (empty($adminset['is_fugou_commission']) || empty($adminset['fugou_recursion_percent'])) {
                return;
            }
            $commission_min = $adminset['fugou_commission_min'] ?? 0;
            $recursionPercent = $adminset['fugou_recursion_percent'];
            $rate = round(100 / $recursionPercent, 2);
            self::recursionCommissionAllParent($aid, $mid, $totalprice, $commission_min, $recursionPercent, $rate, $mid, $orderid, $ogid);
        }
    }
    public static function shougouReward($aid,$mid=0,$totalprice=0,$orderid='',$ogid='',$type='shop'){
        if(getcustom('member_shougou_parentreward')) {
            if (empty($mid)) return;
            //所有的父级
            $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->field('id,levelid,pid')->find();
            $memberPreLevel = Db::name('member_level')->where('aid',$aid)->where('id',$member['levelid'])->field('commissionsg1,commissionsg2,commissionsg3,can_agent')->find();
            if(!$memberPreLevel['can_agent']){
                return;
            }
            $canAgent = $memberPreLevel['can_agent'];
            //查询上级
            $hasParent1 = $hasParent2 = $hasParent3 = false;
            $onebuy_commission1 = $onebuy_commission2 = $onebuy_commission3 = 0;
            $onebuy_commissionjs = 1;//按当前购买者等级配置发放
            //是否按会员当前购买等级算奖励
            $parent = Db::name('member')->where('id', $member['pid'])->field('id,pid,levelid')->find();
            //$onebuy_commissionjs = $memberPreLevel['onebuy_commissionjs']??0;
            if($memberPreLevel && $onebuy_commissionjs==1){
                $onebuy_commission1 = $memberPreLevel['commissionsg1'];
                $onebuy_commission2 = $memberPreLevel['commissionsg2'];
                $onebuy_commission3 = $memberPreLevel['commissionsg3'];
            }
            //首购奖励和一次性升级不重复发
            $member_commission_table = 'member_commission_record';
            if(getcustom('member_shougou_parentreward_wait')){
                $member_commission_table = 'member_commission_record_wait';
            }
            if ($parent) {
                $hasParent1 = true;
                if ($onebuy_commissionjs==0 && $parent['levelid']) {
                    $level = Db::name('member_level')->where('id', $parent['levelid'])->field('commissionsg1')->find();
                    if ($level && $level['commissionsg1'] > 0) {
                        $onebuy_commission1 = $level['commissionsg1'];
                    }
                }
                if ($canAgent>1 && $parent['pid']) {
                    //查询上级
                    $parent2 = Db::name('member')->where('id', $parent['pid'])->field('id,pid,levelid')->find();
                    if ($parent2) {
                        $hasParent2 = true;
                        if ($onebuy_commissionjs==0 && $parent2['levelid']) {
                            $level = Db::name('member_level')->where('id', $parent2['levelid'])->field('commissionsg2')->find();
                            if ($level && $level['commissionsg2'] > 0) {
                                $onebuy_commission2 = $level['commissionsg2'];
                            }
                        }
                        if ($canAgent>2 && $parent2['pid']) {
                            //查询上级
                            $parent3 = Db::name('member')->where('id', $parent2['pid'])->field('id,pid,levelid')->find();
                            if ($parent3) {
                                $hasParent3 = true;
                                if ($onebuy_commissionjs==0 && $parent3['levelid']) {
                                    $level = Db::name('member_level')->where('id', $parent3['levelid'])->field('commissionsg3')->find();
                                    if ($level && $level['commissionsg3'] > 0) {
                                        $onebuy_commission3 = $level['commissionsg3'];
                                    }
                                }
                            }
                        }
                    }
                }
            }

            $updata = ['aid' => $aid, 'mid' => 0, 'frommid' => $mid, 'orderid' => $orderid, 'ogid' => $ogid, 'type' => 'shop', 'commission' => 0, 'score' => 0, 'remark' => '', 'createtime' => time(),'islock'=>0];
            if(getcustom('ciruikang_fenxiao')){
            	$updata['islock'] = 1;
            }
            if($onebuy_commission1>0 && $hasParent1){
                $money = $totalprice * $onebuy_commission1 / 100;
                $money = round($money, 2);
                if ($money > 0) {
                	$updata['mid']        = $parent['id'];
                	$updata['commission'] = $money;
                	$updata['remark']     = '下级首购奖励';
                    Db::name($member_commission_table)->insert($updata);
                }
            }
            if($onebuy_commission2>0 && $hasParent2){
                $money2 = $totalprice * $onebuy_commission2 / 100;
                $money2 = round($money2, 2);
                if ($money2 > 0) {
                    $updata['mid']        = $parent2['id'];
                	$updata['commission'] = $money2;
                	$updata['remark']     = '下下级首购奖励';
                    //首购奖励和一次性升级不重复发
                    Db::name($member_commission_table)->insert($updata);
                }
            }
            if($onebuy_commission3>0 && $hasParent3){
                $money3 = $totalprice * $onebuy_commission3 / 100;
                $money3 = round($money3, 2);
                if($money3 > 0) {
                    $updata['mid']        = $parent3['id'];
                	$updata['commission'] = $money3;
                	$updata['remark']     = '下三级首购奖励';
                    Db::name($member_commission_table)->insert($updata);
                }
            }
        }
    }

    //递归发佣金
    public static function recursionCommissionAllParent($aid,$mid,$totalprice,$commission_min,$recursionPercent,$rate=2,$frommid='',$orderid='',$ogid=''){
        if(getcustom('commission_recursion')) {
            if ($totalprice <= $commission_min) {
                return;
            }
            $commission = $totalprice * $recursionPercent * 0.01;
            if ($commission <= $commission_min) {
                return;
            }
            $member = Db::name('member')->where('aid', $aid)->where('id', $mid)->field('id,levelid,pid,path,path_origin')->find();
            if (empty($member['pid'])) return;
            $parent = Db::name('member')->alias('m')->join('member_level l', 'm.levelid=l.id')
                ->where('m.aid', $aid)
                ->where('m.id', $member['pid'])->field('m.id,m.levelid,m.pid,l.is_fugou_commission')->find();
            if ($parent['is_fugou_commission']) {
                Db::name('member_commission_record')->insert(['aid' => $aid, 'mid' => $parent['id'], 'frommid' => $frommid, 'orderid' => $orderid, 'ogid' => $ogid, 'type' => 'shop', 'commission' => $commission, 'score' => 0, 'remark' => '下级购物奖励[递归]', 'createtime' => time()]);
            }
            //如果有上级 继续发奖励
            if ($parent['pid'] > 0) {
                $recursionPercent = $recursionPercent / $rate;
                return self::recursionCommissionAllParent($aid, $parent['id'], $totalprice, $commission_min, $recursionPercent, $rate, $frommid, $orderid, $ogid);
            }
            return;
        }
    }

    //小程序 发货信息录入接口
    public static function wxShipping($aid,$order,$orderType = 'shop',$shippingInfo=[]){
        $isTradeManaged = \app\commons\Wechat::isTradeManaged($aid);
        if($isTradeManaged['status'] == 1 && $isTradeManaged['is_trade_managed']){
//            \think\facade\Log::write('wxShipping发货信息录入接口:'.jsonEncode($order));
            \think\facade\Log::write('wxShipping发货信息录入接口orderType:'.$orderType);
//            \think\facade\Log::write('wxShipping发货信息录入接口shippingInfo:'.jsonEncode($shippingInfo));
            //发货信息录入接口 https://developers.weixin.qq.com/miniprogram/dev/platform-capabilities/business-capabilities/order-shipping/order-shipping.html#%E4%B8%80%E3%80%81%E5%8F%91%E8%B4%A7%E4%BF%A1%E6%81%AF%E5%BD%95%E5%85%A5%E6%8E%A5%E5%8F%A3
            $orderType = str_replace('_order','',$orderType);
            $wxpaylog = Db::name('wxpay_log')->where('aid',$aid)->where('ordernum',$order['ordernum'])->where('tablename',$orderType)->find();
            if(empty($wxpaylog)){
                $wxpaylog = Db::name('wxpay_log')->where('aid',$aid)->where('transaction_id',$order['paynum'])->where('tablename','daifu')->find();
                if(empty($wxpaylog)){
                    \think\facade\Log::write('wxShipping发货信息录入接口:未找到微信支付记录');
                    return ['status'=>0,'msg'=>'未找到微信支付记录'];
                }
            }
            if($wxpaylog['platform'] != 'wx'){
                \think\facade\Log::write('wxShipping发货信息录入接口:非微信小程序支付订单');
                return ['status'=>0,'msg'=>'非微信小程序支付订单'];
            }
            //支付不足20秒不请求微信接口
            if($wxpaylog['createtime'] && time() - $wxpaylog['createtime'] < 20){
                $data = [
                    'aid'=>$aid,
                    'mid'=>$order['mid'],
                    'wxpaylogid'=>$wxpaylog['id'],
                    'openid'=>$wxpaylog['openid'],
                    'tablename'=>$wxpaylog['tablename'],
                    'ordernum'=>$wxpaylog['ordernum'],
                    'mch_id'=>$wxpaylog['mch_id'],
                    'createtime'=>time(),
                    'nexttime'=>time()+20,
                    'times_failed'=>0,
                    'status'=>0
                ];
                Db::name('wx_upload_shipping')->insert($data);
                return ['status'=>0,'msg'=>'稍后再试'];
            }
            $shipping_list = ['item_desc'=> $order['title']?$order['title']:self::getOrderTypeName($orderType)];
            //0普通快递 1到店自提 2同城配送 3自动发货 4在线卡密
            if(in_array($orderType,['restaurant_shop'])){
                //无需配送 订单
                $postdata['logistics_type'] = self::getWxLogisticsType();
            }else{
                if($order['freight_type'] === 0){
                    $shipping_list['tracking_no'] = $shippingInfo['express_no'];   //tracking_no 物流单号，物流快递发货时必填
                    $shipping_list['express_company'] = \app\commons\Wxvideo::get_delivery_id($shippingInfo['express_com']);
                    $shipping_list['contact']['receiver_contact'] = substr($order['tel'], 0,3).'****'.substr($order['tel'],-4);
                    //contact 联系方式，当发货的物流公司为顺丰时，联系方式为必填，收件人或寄件人联系方式二选一
                }
                $postdata['logistics_type'] = self::getWxLogisticsType($order['freight_type']);//物流模式，发货方式枚举值：1、实体物流配送采用快递公司进行实体物流配送形式 2、同城配送 3、虚拟商品，虚拟商品，例如话费充值，点卡等，无实体配送形式 4、用户自提
            }
            $postdata['shipping_list'] = [$shipping_list];
            $postdata['order_key'] = [
                'order_number_type' => 2,
                'transaction_id'=>$wxpaylog['transaction_id']
//                'mchid' => $appinfo['wxpay_type'] == 0 ? $appinfo['wxpay_mchid'] : $appinfo['wxpay_sub_mchid'],
//                'out_trade_no'=>$order['ordernum']
            ];
            $postdata['delivery_mode'] = 1;
            $postdata['upload_time'] = date(DATE_RFC3339,time());
            $member = Db::name('member')->where('id',$wxpaylog['mid'])->find();//可能存在代付
            $postdata['payer'] = [
                'openid' => $member['wxopenid']
            ];
            $rs = \app\commons\Wechat::uploadShippingInfo($aid,'wx',$postdata);
            if($rs['status'] == 1){
                Db::name('wxpay_log')->where('aid',$aid)->where('id',$wxpaylog['id'])->update(['is_upload_shipping_info'=>1]);
            }
            return $rs;
        }
    }

    //再次录入发货信息
    public static function retryUploadShipping()
    {
        $time = time();
        $list = Db::name('wx_upload_shipping')->where('status',0)->where('nexttime','<=',$time)->where('times_failed','<',3)->where('tablename','<>','daifu')
            ->select()->toArray();
        if($list){
            foreach ($list as $item){
                $order = Db::name($item['tablename'].'_order')->where('aid',$item['aid'])->where('ordernum',$item['ordernum'])->find();
                if($order['paytypeid'] == 2){
                    $rs = \app\commons\Order::wxShipping($order['aid'],$order,$item['tablename']);
                    if($rs['status'] == 1){
                        Db::name('wx_upload_shipping')->where('id',$item['id'])->update(['status'=>1]);
                    }else{
                        Db::name('wx_upload_shipping')->where('id',$item['id'])->update(['nexttime'=>$time+60,'times_failed'=>$item['times_failed']+1]);
                    }
                }else{
                    Db::name('wx_upload_shipping')->where('id',$item['id'])->update(['status'=>-1]);
                }
            }
        }
    }

    /**
     * 微信物流模式
     * @param $freight_type  0普通快递 1到店自提 2同城配送 3自动发货 4在线卡密 5门店配送
     * @return void
     */
    public static function getWxLogisticsType($freight_type=''){
        //物流模式，发货方式枚举值：1、实体物流配送采用快递公司进行实体物流配送形式 2、同城配送 3、虚拟商品，虚拟商品，例如话费充值，点卡等，无实体配送形式 4、用户自提
        if($freight_type === 0){
            return 1;
        }else if($freight_type == 1){
            return 4;
        }else if($freight_type == 2){
            return 2;
        }else if($freight_type == 3){
            return 3;
        }else if($freight_type == 4){
            return 3;
        }
        return 3;
    }


    /**
     * 通过订单类型获得订单类型名称
     * @param $orderType
     * @return string
     */
    public static function getOrderTypeName($orderType)
    {
        $typeName = '订单';
        $orderType = str_replace('_order','',$orderType);
        if($orderType == 'shop'){
            $typeName = '商城订单';
        }elseif($orderType == 'recharge'){
            $typeName = '充值订单';
        }elseif($orderType == 'miandan'){
            $typeName = '买单订单';
        }elseif($orderType == 'scoreshop'){
            $typeName = t('积分').'兑换订单';
        }elseif($orderType == 'seckill'){
            $typeName = '秒杀订单';
        }elseif($orderType == 'collage'){
            $typeName = '拼团订单';
        }elseif($orderType == 'lucky_collage'){
            $typeName = '幸运拼团订单';
        }elseif($orderType == 'coupon'){
            $typeName = t('优惠券').'订单';
        }elseif($orderType == 'cycle'){
            $typeName = '周期购订单';
        }elseif($orderType == 'hbtk'){
            $typeName = '拓客订单';
        }elseif($orderType == 'kanjia'){
            $typeName = '砍价订单';
        }elseif($orderType == 'kecheng'){
            $typeName = '知识付费订单';
        }elseif($orderType == 'paotui'){
            $typeName = '跑腿订单';
        }elseif($orderType == 'restaurant_booking'){
            $typeName = '预定订单';
        }elseif($orderType == 'restaurant_shop'){
            $typeName = '点餐订单';
        }elseif($orderType == 'restaurant_takeaway'){
            $typeName = '外卖订单';
        }elseif($orderType == 'yuyue'){
            $typeName = '预约服务订单';
        }elseif($orderType == 'form'){
            $typeName = '表单订单';
        }elseif($orderType == 'member_levelup'){
            $typeName = t('会员').'升级';
        }elseif($orderType == 'cashier'){
            $typeName = '收银台';
        }elseif($orderType == 'designerpage'){
            $typeName = '页面';
        }

        return $typeName;
    }


    /**
     * 判断订单类型是否有orderGoods表
     * @param $orderType 订单类型
     * @return void
     */
    public static function hasOrderGoodsTable($orderType)
    {
        if(empty($orderType)){
            return false;
        }
        $hasOrderGoodsArr = [
            'shop',
            'scoreshop',
            'restaurant_shop',
            'restaurant_booking',
            'restaurant_takeaway',
            'cashier',
            'gift_bag'
        ];
        if(in_array($orderType,$hasOrderGoodsArr)){
            return true;
        }
        return table_exists($orderType.'_order_goods');
    }

    /**
     * 通过快递名字查询微信物流公司编码
     * @param $aid
     * @param $express_com
     * @return void ‘STO’
     */
    public static function getWxExpressCompany($aid,$express_com){
        $rs = \app\commons\Wechat::get_delivery_list($aid);
        if($rs['status'] == 1) {
            $srs = collect($rs['delivery_list'])->where('delivery_name','=',$express_com)->first();
            return $srs['delivery_id'];
        }
    }

    /**
     * @param $aid
     * @param $orderid
     * @param $order
     * @param $mid
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function collectCheck($aid,$orderid=0,$order=[],$mid=0)
    {
        if(empty($order))
            $order = Db::name('shop_order')->where('aid',$aid)->where('mid',$mid)->where('id',$orderid)->find();
        else
            $orderid = $order['id'];

        if(!$order || ($order['status']!=2) || $order['paytypeid']==4){
            return ['status'=>0,'msg'=>'订单状态不符合收货要求'];
        }

        if(getcustom('product_collect_time')){
            //查询可确认收货时间
            $shopset = Db::name('shop_sysset')->where('aid',$aid)->field('ordercollect_time')->find();
            $start_time = time()-$shopset['ordercollect_time']*86400;
            if($order['send_time']>$start_time){
                return ['status'=>0,'msg'=>'订单发货后'.$shopset['ordercollect_time'].'天之后才可点击确认收货'];
            }
        }

        $refundOrder = Db::name('shop_refund_order')->where('refund_status','in',[1,4])->where('aid',$aid)->where('mid',$mid)->where('orderid',$orderid)->count();
        if($refundOrder){
            return ['status'=>0,'msg'=>'有正在进行的退款，无法确认收货'];
        }
        if($order['balance_price'] > 0 && $order['balance_pay_status']==0)
            return ['status'=>0,'msg'=>'请先支付尾款'];

        return ['status'=>1,'msg'=>'ok'];
    }

    public static function teamYejiManage($aid,$member){
        if(getcustom('yx_team_yeji_manage')){
            //团队管理奖
//            Db::startTrans();
//            dump($member);
            if(empty($member['path'])) return false;
            $set = Db::name('team_yeji_manage_set')->where('aid',$aid)->where('status',1)->find();
//            dump(Db::getLastSql());
//            dump($set);
            if($set['status'] != 1) return false;
            $config = json_decode($set['config_data'],true);
            if(empty($config)) return false;
            $levelids = array_keys($config);

            //查询所有父级
            $parentList = \app\commons\Member::getParentsByPath($aid,$member['path'],[['levelid','in',$levelids]]);
            if(empty($parentList)) return false;
//            dump($parentList);

            foreach ($parentList as $parent){
                $configLevel = $config[$parent['levelid']];
//                dump($parent['id'].'-'.$parent['nickname']);
//                dump($configLevel);
                if(empty($configLevel)) continue;
                //直推团队
                $children1 = Db::name('member')->where('aid',$aid)->where('pid',$parent['id'])->column('id');
                if(empty($children1)) continue;
                //排除已发放
                $logmids = Db::name('team_yeji_manage')->where('aid',$aid)->where('mid',$parent['id'])->column('from_mid');
                if($logmids){
                    foreach ($children1 as $k => $mid){
                        if(in_array($mid,$logmids)){
                            unset($children1[$k]);
                        }
                    }
                }

                if(count($children1) < $configLevel['teamNum']){
                    continue;
                }
                $commission = 0;
                $score = 0;
                $teamNum = 0;//满足条件的团队数量
                $from_mids = [];
                //任意直推N条线业绩满足条件即为成功
                foreach ($children1 as $k => $mid){
                    if($configLevel['levelNum'] > 1){
                        $childrenmids = \app\commons\Member::getdownmids($aid,$mid,$configLevel['levelNum']-1);
                        if(empty($childrenmids)) continue;
                        $childrenmids = array_merge([$mid],$childrenmids);
                    }else{
                        $childrenmids = [$mid];
                    }
//                    dump($childrenmids);

                    $yejiwhere = [];
                    $yejiwhere[] = ['status','in','1,2,3'];
                    $yeji = Db::name('shop_order_goods')->where('aid',$aid)->where('mid','in',$childrenmids)->where($yejiwhere)->sum('real_totalprice');
//                    dump('$yeji:'.$yeji);
                    if($yeji >= $configLevel['yeji']){
                        //满足一个团队
                        $from_mids[] = $mid;
                        $teamNum++;
                        if($teamNum >= $configLevel['teamNum']) break;
                    }
                }
//                dump('$teamNum:'.$teamNum);

                //发奖
                if($teamNum >= $configLevel['teamNum']){
//                if($score > 0){
//                    \app\commons\Member::addscore($aid,$parent['id'],$score,'团队管理奖励');
//                    Db::name('member')->where('aid',$set['aid'])->where('id',$parent['id'])->inc('day_give_score_total',$score)->update();
//                }
                    $commission += $configLevel['commission'];
                    if($commission > 0){
                        $commission_member = $commission/$configLevel['teamNum'];
                        foreach ($from_mids as $from_mid){
                            \app\commons\Member::addcommission($aid,$parent['id'],$from_mid,$commission_member,'团队管理奖励');
                            Db::name('team_yeji_manage')->insert(['aid'=>$aid,'mid'=>$parent['id'],'from_mid'=>$from_mid,'score'=>$score,'commission'=>$commission_member,'createtime'=>time()]);
                        }
                        Db::name('member')->where('aid',$aid)->where('id',$parent['id'])->inc('team_yeji_manage_commission_total',$commission)->update();
                    }
                }
            }

//            Db::commit();
        }
    }
    /**
     * 所有参与活动的用户平均发放返现
     * aid 商家id
     * mid 会员id
     * cashback 购物返现活动
     * og 商品id
     * back_price_total 返现金额
     */

    public static function cashbackMemerDo($aid,$mid,$cashback,$og,$back_price_total,$cash_type = 0){
        $pro_id = $og['proid'];
        $cashback_member = Db::name('cashback_member')->where('aid',$aid)->where(['cashback_id'=>$cashback['id'],'pro_id'=>$pro_id])->whereRaw('cashback_money_max > cashback_money or cashback_money_max <= 0')->select()->toArray();
        //平均返现
        //$member_num = count($cashback_member);
        $member_num = array_sum(array_column($cashback_member,'pro_num'));
        $av_back_price_one = round($back_price_total/$member_num,2);
        $over_back_price = 0;
        foreach($cashback_member as $k=>$v){
            $av_back_price = $v['pro_num'] * $av_back_price_one;
            //自定义发放
            if($cash_type >= 1){
                \app\customs\OrderCustom::deal_first_cashback($aid,$v['mid'],$av_back_price,$og,$cashback,$cash_type);
            }else{
                if($v['back_type'] == 1 ){
                    $cashback_num = $v['cashback_money'];
                }else if($v['back_type'] == 2 ){
                    $cashback_num = $v['commission'];
                }else if($v['back_type'] == 3 ){
                    $cashback_num = $v['score'];
                }
                if($v['cashback_money_max'] > 0 &&  $v['cashback_money_max'] > $cashback_num){
                    //最大可追加金额
                    $cashback_money_max = $v['cashback_money_max'] - $cashback_num;
                    if($cashback_money_max < $av_back_price){
                        $over_price = $av_back_price - $cashback_money_max;
                        $av_back_price_tem = $av_back_price;
                        $av_back_price = $cashback_money_max;
                        $over_back_price += $over_price;
                    }
                }
                if($v['back_type'] == 1 ){
                    \app\commons\Member::addmoney($aid,$v['mid'],$av_back_price,$cashback['name']);
                    Db::name('cashback_member')->where('id',$v['id'])->inc('cashback_money',$av_back_price)->update();
                }
                if($v['back_type'] == 2){
                    \app\commons\Member::addcommission($aid,$v['mid'],$mid,$av_back_price,$cashback['name']);
                    Db::name('cashback_member')->where('id',$v['id'])->inc('commission',$av_back_price)->update();
                }
                if($v['back_type'] == 3){
                    $av_back_price = round($av_back_price);
                    \app\commons\Member::addscore($aid,$v['mid'],$av_back_price,$cashback['name']);
                    Db::name('cashback_member')->where('id',$v['id'])->inc('score',$av_back_price)->update();
                }

                //写入发放日志
                \app\customs\OrderCustom::cashbackMemerDoLog($aid,$v['mid'],$cashback,$og,$av_back_price);
                if($av_back_price_tem){
                    $av_back_price = $av_back_price_tem;
                }
            }
        }
        if($over_back_price >0 ){
            $res = self::cashbackMemerDo($aid,$mid,$cashback,$og,$over_back_price,$cash_type);
        }
        return true;
    }
    /**
     * 所有参与活动的用户平均发放返现
     * aid 商家id
     * mid 会员id
     * cashback 购物返现活动
     * og 商品
     * back_price_total 返现数量
     */

    public static function cashbackMemerDoLog($aid,$mid,$cashback,$og,$back_price_total){
        $cashback_member = [];
        $cashback_member['aid'] = $aid;
        $cashback_member['mid'] = $mid;
        $cashback_member['cashback_id'] = $cashback['id'];
        $cashback_member['pro_id'] = $og['proid'];
        if($cashback['back_type'] == 1 ){
            $cashback_member['cashback_money'] = $back_price_total;
        }else if($cashback['back_type'] == 2 ){
            $cashback_member['commission'] = $back_price_total;
        }else if($cashback['back_type'] == 3 ){
            $cashback_member['score'] = $back_price_total;
        }
        $cashback_member['back_type']   = $cashback['back_type'];
        $cashback_member['type']        = $og['ordertype']??'shop';
        $cashback_member['create_time'] = time();
        $insert = Db::name('cashback_member_log')->insert($cashback_member);
        return $insert;
    }
    //订单退还分红
	public static function refundFenhongDeduct($order,$type='shop'){
        if(getcustom('commission_orderrefund_deduct')){
		$aid = $order['aid'];

        $open_commission_orderrefund_deduct = Db::name('admin_set')->where('aid',$aid)->value('open_commission_orderrefund_deduct');
        if($open_commission_orderrefund_deduct !=1){
            return;
        }
        writeLog('订单退款扣除分红佣金orderid:'.$order['id'].'type:'.$type,'commissionrefund');
		if($type == 'shop'){
            $og_list = Db::name('shop_order_goods')->where('orderid',$order['id'])->select();
            foreach($og_list as $k=>$v){
                $og_record = Db::name('member_fenhong_record')->where('aid',$aid)->where('ogid',$v['id'])->where('module',$type)->where('status',1)->select();
                foreach($og_record as $record){
                    $ogid = $record['ogid'];
                    $fenhonglog = Db::name('member_fenhonglog')->where('aid',$aid)->where('mid',$record['mid'])->where('module',$type)->where("find_in_set('{$ogid}',ogids)")->find();
                    if($fenhonglog){
                        \app\commons\Member::addcommission($aid,$record['mid'],0,-1*$record['commission'],'订单退款扣除'.$record['remark'],1,$record['type']);
                        Db::name('member_fenhong_record')->where('id', $record['id'])->update(['status' => 2]);
                    }
                }

            }
        }elseif($type=='seckill'){

                $og_record = Db::name('member_fenhong_record')->where('aid',$aid)->where('ogid',$order['id'])->where('module',$type)->where('status',1)->select();
                foreach($og_record as $record){
                    $ogid = $record['ogid'];
                    $fenhonglog = Db::name('member_fenhonglog')->where('aid',$aid)->where('mid',$record['mid'])->where('module',$type)->where("find_in_set('{$ogid}',ogids)")->find();
                    if($fenhonglog){
                        \app\commons\Member::addcommission($aid,$record['mid'],0,-1*$record['commission'],'订单退款扣除'.$record['remark'],1,$record['type']);
                        Db::name('member_fenhong_record')->where('id', $record['id'])->update(['status' => 2]);
                    }
                }

		}elseif($type == 'scoreshop'){
            $og_list = Db::name('scoreshop_order_goods')->where('orderid',$order['id'])->select();
            foreach($og_list as $k=>$v){
                $og_record = Db::name('member_fenhong_record')->where('aid',$aid)->where('ogid',$v['id'])->where('module',$type)->where('status',1)->select();
                foreach($og_record as $record){
                    $ogid = $record['ogid'];
                    $fenhonglog = Db::name('member_fenhonglog')->where('aid',$aid)->where('mid',$record['mid'])->where('module',$type)->where("find_in_set('{$ogid}',ogids)")->find();
                    if($fenhonglog){
                        \app\commons\Member::addcommission($aid,$record['mid'],0,-1*$record['commission'],'订单退款扣除'.$record['remark'],1,$record['type']);
                        Db::name('member_fenhong_record')->where('id', $record['id'])->update(['status' => 2]);
                    }
                }

            }
        }elseif($type == 'collage'){
            $og_record = Db::name('member_fenhong_record')->where('aid',$aid)->where('ogid',$order['id'])->where('module',$type)->where('status',1)->select();
            foreach($og_record as $record){
                $ogid = $record['ogid'];
                $fenhonglog = Db::name('member_fenhonglog')->where('aid',$aid)->where('mid',$record['mid'])->where('module',$type)->where("find_in_set('{$ogid}',ogids)")->find();
                if($fenhonglog){
                    \app\commons\Member::addcommission($aid,$record['mid'],0,-1*$record['commission'],'订单退款扣除'.$record['remark'],1,$record['type']);
                    Db::name('member_fenhong_record')->where('id', $record['id'])->update(['status' => 2]);
                }
            }
        }elseif($type == 'kanjia'){
            $og_record = Db::name('member_fenhong_record')->where('aid',$aid)->where('ogid',$order['id'])->where('module',$type)->where('status',1)->select();
            foreach($og_record as $record){
                $ogid = $record['ogid'];
                $fenhonglog = Db::name('member_fenhonglog')->where('aid',$aid)->where('mid',$record['mid'])->where('module',$type)->where("find_in_set('{$ogid}',ogids)")->find();
                if($fenhonglog){
                    \app\commons\Member::addcommission($aid,$record['mid'],0,-1*$record['commission'],'订单退款扣除'.$record['remark'],1,$record['type']);
                    Db::name('member_fenhong_record')->where('id', $record['id'])->update(['status' => 2]);
                }
            }
        }elseif($type == 'lucky_collage'){
		    $type = 'luckycollage';
            $og_record = Db::name('member_fenhong_record')->where('aid',$aid)->where('ogid',$order['id'])->where('module',$type)->where('status',1)->select();
            foreach($og_record as $record){
                $ogid = $record['ogid'];
                $fenhonglog = Db::name('member_fenhonglog')->where('aid',$aid)->where('mid',$record['mid'])->where('module',$type)->where("find_in_set('{$ogid}',ogids)")->find();
                if($fenhonglog){
                    \app\commons\Member::addcommission($aid,$record['mid'],0,-1*$record['commission'],'订单退款扣除'.$record['remark'],1,$record['type']);
                    Db::name('member_fenhong_record')->where('id', $record['id'])->update(['status' => 2]);
                }
            }
        }elseif($type == 'kecheng'){
            $og_record = Db::name('member_fenhong_record')->where('aid',$aid)->where('ogid',$order['id'])->where('module',$type)->where('status',1)->select();
            foreach($og_record as $record){
                $ogid = $record['ogid'];
                $fenhonglog = Db::name('member_fenhonglog')->where('aid',$aid)->where('mid',$record['mid'])->where('module',$type)->where("find_in_set('{$ogid}',ogids)")->find();
                if($fenhonglog){
                    \app\commons\Member::addcommission($aid,$record['mid'],0,-1*$record['commission'],'订单退款扣除'.$record['remark'],1,$record['type']);
                    Db::name('member_fenhong_record')->where('id', $record['id'])->update(['status' => 2]);
                }
            }
        }elseif($type=='tuangou'){
			$og_record = Db::name('member_fenhong_record')->where('aid',$aid)->where('ogid',$order['id'])->where('module',$type)->where('status',1)->select();
            foreach($og_record as $record){
                $ogid = $record['ogid'];
                $fenhonglog = Db::name('member_fenhonglog')->where('aid',$aid)->where('mid',$record['mid'])->where('module',$type)->where("find_in_set('{$ogid}',ogids)")->find();
                if($fenhonglog){
                    \app\commons\Member::addcommission($aid,$record['mid'],0,-1*$record['commission'],'订单退款扣除'.$record['remark'],1,$record['type']);
                    Db::name('member_fenhong_record')->where('id', $record['id'])->update(['status' => 2]);
                }
            }
		}elseif($type == 'yuyue'){
            $og_record = Db::name('member_fenhong_record')->where('aid',$aid)->where('ogid',$order['id'])->where('module',$type)->where('status',1)->select();
            foreach($og_record as $record){
                $ogid = $record['ogid'];
                $fenhonglog = Db::name('member_fenhonglog')->where('aid',$aid)->where('mid',$record['mid'])->where('module',$type)->where("find_in_set('{$ogid}',ogids)")->find();
                if($fenhonglog){
                    \app\commons\Member::addcommission($aid,$record['mid'],0,-1*$record['commission'],'订单退款扣除'.$record['remark'],1,$record['type']);
                    Db::name('member_fenhong_record')->where('id', $record['id'])->update(['status' => 2]);
                }
            }
        }elseif($type=='cashier'){
            $og_list = Db::name('cashier_order_goods')->where('orderid',$order['id'])->select();
            foreach($og_list as $k=>$v){
                $og_record = Db::name('member_fenhong_record')->where('aid',$aid)->where('ogid',$v['id'])->where('module',$type)->where('status',1)->select();
                foreach($og_record as $record){
                    $ogid = $record['ogid'];
                    $fenhonglog = Db::name('member_fenhonglog')->where('aid',$aid)->where('mid',$record['mid'])->where('module',$type)->where("find_in_set('{$ogid}',ogids)")->find();
                    if($fenhonglog){
                        \app\commons\Member::addcommission($aid,$record['mid'],0,-1*$record['commission'],'订单退款扣除'.$record['remark'],1,$record['type']);
                        Db::name('member_fenhong_record')->where('id', $record['id'])->update(['status' => 2]);
                    }
                }

            }
        }elseif($type=='restaurant_shop'){
            $type='diancan';
            $og_list = Db::name('restaurant_shop_order_goods')->where('orderid',$order['id'])->select();
            foreach($og_list as $k=>$v){
                $og_record = Db::name('member_fenhong_record')->where('aid',$aid)->where('ogid',$v['id'])->where('module',$type)->where('status',1)->select();
                foreach($og_record as $record){
                    $ogid = $record['ogid'];
                    $fenhonglog = Db::name('member_fenhonglog')->where('aid',$aid)->where('mid',$record['mid'])->where('module',$type)->where("find_in_set('{$ogid}',ogids)")->find();
                    if($fenhonglog){
                        \app\commons\Member::addcommission($aid,$record['mid'],0,-1*$record['commission'],'订单退款扣除'.$record['remark'],1,$record['type']);
                        Db::name('member_fenhong_record')->where('id', $record['id'])->update(['status' => 2]);
                    }
                }

            }
        }elseif($type=='restaurant_takeaway'){
            $type='takeaway';
            $og_list = Db::name('restaurant_takeaway_order_goods')->where('orderid',$order['id'])->select();
            foreach($og_list as $k=>$v){
                $og_record = Db::name('member_fenhong_record')->where('aid',$aid)->where('ogid',$v['id'])->where('module',$type)->where('status',1)->select();
                foreach($og_record as $record){
                    $ogid = $record['ogid'];
                    $fenhonglog = Db::name('member_fenhonglog')->where('aid',$aid)->where('mid',$record['mid'])->where('module',$type)->where("find_in_set('{$ogid}',ogids)")->find();
                    if($fenhonglog){
                        \app\commons\Member::addcommission($aid,$record['mid'],0,-1*$record['commission'],'订单退款扣除'.$record['remark'],1,$record['type']);
                        Db::name('member_fenhong_record')->where('id', $record['id'])->update(['status' => 2]);
                    }
                }

            }
        }elseif($type=='coupon'){
		    if(getcustom('commission_times_coupon')){
                $og_record = Db::name('member_fenhong_record')->where('aid',$aid)->where('ogid',$order['id'])->where('module',$type)->where('status',1)->select();
                foreach($og_record as $record){
                    $ogid = $record['ogid'];
                    $fenhonglog = Db::name('member_fenhonglog')->where('aid',$aid)->where('mid',$record['mid'])->where('module',$type)->where("find_in_set('{$ogid}',ogids)")->find();
                    if($fenhonglog){
                        \app\commons\Member::addcommission($aid,$record['mid'],0,-1*$record['commission'],'订单退款扣除'.$record['remark'],1,$record['type']);
                        Db::name('member_fenhong_record')->where('id', $record['id'])->update(['status' => 2]);
                    }
                }
            }

        }elseif($type=='gift_pack'){
            if(getcustom('yx_gift_pack')){
                $og_record = Db::name('member_fenhong_record')->where('aid',$aid)->where('ogid',$order['id'])->where('module',$type)->where('status',1)->select();
                foreach($og_record as $record){
                    $ogid = $record['ogid'];
                    $fenhonglog = Db::name('member_fenhonglog')->where('aid',$aid)->where('mid',$record['mid'])->where('module',$type)->where("find_in_set('{$ogid}',ogids)")->find();
                    if($fenhonglog){
                        \app\commons\Member::addcommission($aid,$record['mid'],0,-1*$record['commission'],'订单退款扣除'.$record['remark'],1,$record['type']);
                        Db::name('member_fenhong_record')->where('id', $record['id'])->update(['status' => 2]);
                    }
                }
			}
		}
    }

		return true;
	}
    /**
     * 分期退款处理
     */

    public static function fenqi_refund($order,$refund_money,$reason=''){
		//$refund_money = $data['refund_money'];
		if(getcustom('shop_product_fenqi_pay')){
			$fenqi_data = json_decode($order['fenqi_data'],true);
			$fenqi_order = $order;
			$member = Db::name('member')->where('id',$order['mid'])->find();
			foreach($fenqi_data as $fqkey=>$fq){
				if($fq['status'] == 1){
					if($refund_money<$fq['fenqi_money']){
						$fq['fenqi_money'] = $refund_money;
					}
					if($fq['fenqi_money'] <=0 ||$refund_money <= 0){
						break;
					}
					$where_counpon[] = ['status','=',0];
					$where_counpon[] = ['endtime','>',time()];
					if($order['fenqigive_couponid']){
                        Db::name('coupon_record')->where('aid',$order['aid'])->where('mid',$order['mid'])->where('couponid','=',$order['fenqigive_couponid'])->where($where_counpon)->limit($fq['fenqi_give_num'])->order('id asc')->update(['endtime'=>time()]);
					}
					//分销奖励上级
					if($member['pid'] > 0 && $order['fenqigive_fx_couponid']>0){
                        Db::name('coupon_record')->where('aid',$order['aid'])->where('mid',$member['pid'])->where('couponid','=',$order['fenqigive_fx_couponid'])->where($where_counpon)->limit($fq['fenqi_fx_num'])->order('id asc')->update(['endtime'=>time()]);

					}
					$payorder = Db::name('payorder')->where('aid',$order['aid'])->where('ordernum',$fq['ordernum'])->find();
					$fenqi_order['ordernum']=$fq['ordernum'];
					$fenqi_order['totalprice']=$payorder['money'];
					$rs = \app\commons\Order::refund($fenqi_order, $fq['fenqi_money'], $reason);
                    if($rs['status']==0){
						return $rs;
					}
					$refund_money = round($refund_money - $fq['fenqi_money'],2);

				}
			}
            return $rs;
		}
	}

	//商品柜 付款后处理
    public static function pickupDeviceGoodsPayafter($order){
        if(getcustom('product_pickup_device')) {
            if ($order['dgid']) {//商品柜更改库存和开门
                $aid = $order['aid'];
                $bid = $order['bid'];
                $device_goods = Db::name('product_pickup_device_goods')->where('aid', $aid)->where('id', $order['dgid'])->find();
                $device =Db::name('product_pickup_device')->where('id',$device_goods['device_id'])->field('name,address,uid,guangeiot')->find();
                if ($device_goods) {
                    $num = Db::name('shop_order_goods')->where('aid', $aid)->where('orderid', $order['id'])->sum('num');
                    //更新库存
                    $real_stock = $device_goods['real_stock'] - $num;
                    //增加销量
                    $real_stock = $real_stock <= 0 ? 0 : $real_stock;
                    $dgsales = $device_goods['sales'] + $num;
                    //更改库存
                    Db::name('product_pickup_device_goods')->where('aid', $order['aid'])->where('id', $order['dgid'])->update(['real_stock' => $real_stock, 'sales' => $dgsales]);

                    //开门 只有自提的开门
                    if($order['freight_type'] ==1){
                        if(getcustom('product_pickup_device_guangeiot')) {
                            if ($device['guangeiot'] == 'xinjierui') {
                                $senddata = [
                                    'device_id' => $device_goods['device_no'],
                                    'operation' => "openBox",
                                    'userId' => 0,
                                    'msgId' => rand(1, 100),
                                    'boxCh' => $device_goods['goods_lane'],
                                    'halfway' => 0,
                                    'certType' => 6,
                                    'dateTime' => time()
                                ];
                                $senddata = json_encode($senddata, JSON_UNESCAPED_UNICODE);
                                $rs = \app\customs\ProductPickupDevice::publishData($aid, $bid, $device_goods['device_no'], $senddata);
                            }
                            self::collect($order,'shop');
                            Db::name('shop_order')->where('aid',$aid)->where('id',$order['id'])->update(['status' => 3]);
                        }
                    }
                    //发送通知
                    $set = Db::name('product_pickup_device_set')->where('aid',$aid)->where('bid',$bid)->find();
                    if($set['add_stock_remind']){
                        $remind_type = explode(',',$set['remind_type']);
                        $remind_pinlv = explode(',',$set['remind_pinlv']);
                        //查找管理员信息
                        //模板通知
                        if(in_array('tmpl',$remind_type)){
                            if(in_array(1,$remind_pinlv) || (in_array(2,$remind_pinlv) && $real_stock <=$set['remind_limit_stock'])){ //每件通知开启  ,库存达x件
                                $tmplcontent = [];
                                $tempconNew = [];
                                $tempconNew['thing11'] = $device['name'];//设备名称
                                $tempconNew['thing12'] = $device['address'];//地点
                                $send_uid = explode(',',$device['uid']);
                                \app\commons\Wechat::sendhttmplByUids($order['aid'],$send_uid,'tmpl_device_addstock_remind',$tempconNew,m_url('/pagesB/admin/pickupdeviceaddstock'),0);
                            }
                        }
                        if(in_array('sms',$remind_type)){
                            if(in_array(1,$remind_pinlv) || (in_array(2,$remind_pinlv) && $real_stock <=$set['remind_limit_stock'])){ //每件通知开启   库存达
                                $tel_list = Db::name('admin_user')->alias('au')
                                    ->join('member m','m.id = au.mid')
                                    ->where('au.aid',$aid)->where('au.bid',$bid)
                                    ->where('au.id',$device['uid'])
                                    ->column('tel');
                                foreach($tel_list as $tel) {
                                    \app\commons\Sms::send($aid, $tel, 'tmpl_device_addstock_remind', ['address' => $device['address'], 'name' => $device['name']]);
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    public static function prodcutBonusPool($aid,$order,$oglist,$member){
        if(getcustom('product_bonus_pool')){
            $bonus_pool_status = Db::name('admin')->where('id',$aid)->value('bonus_pool_status');
            if($bonus_pool_status ==1){
                $bonus_pool_sysset = Db::name('shop_sysset')->where('aid',$order['aid'])->field('bonus_pool_member_count,bonus_pool_total_mcount')->find();
                $level_member=[];
                $total_member = [];
                //查询每个等级，已释放的会员人数
                $default_cid = Db::name('member_level_category')->where('aid',$order['aid'])->where('isdefault', 1)->value('id');
                $default_cid = $default_cid ? $default_cid : 0;
                $levellist = Db::name('member_level')->where('aid',$order['aid'])->where('cid', $default_cid)->field('id')->order('sort,id')->select()->toArray();
                foreach($levellist as $key=>$level){
                    $mids = Db::name('member_bonus_pool_record')->alias('bpr')
                        ->join('member m','m.id = bpr.mid')
                        ->join('member_level ml','ml.id = m.levelid')
                        ->where('bpr.aid',$order['aid'])
                        ->where('ml.id',$level['id'])
                        ->group('bpr.mid')
                        ->column('mid');
                    \think\facade\Log::info([
                        'file' => __FILE__,
                        'line' => __LINE__,
                        'sqlllll' => Db::name('member_bonus_pool_record')->getLastSql()
                    ]);
                    $level_member[$level['id']] =  $mids?$mids:[];
                    $total_member = array_merge($total_member,$mids);
                }
                //如果购买人不在 已购买的等级中且 人数已超限制
                $is_add_max_money = true;
                if($bonus_pool_sysset['bonus_pool_total_mcount'] > 0){
                    $buy_member_limitnum =$bonus_pool_sysset['bonus_pool_total_mcount']; //设置中等级对应的会员数量
                    $buy_member_limitnum = $buy_member_limitnum?$buy_member_limitnum:0;
                    if(count($total_member)+1 >= $buy_member_limitnum  && $buy_member_limitnum >0){
                        $is_add_max_money = false;
                    }
                }

                //平台首单不释放
                $order_number = Db::name('shop_order')->where('status','in',[1,2,3])->where('aid',$order['aid'])-> count();
                if($order_number > 1){

                    $bonus_pool_member_count = json_decode($bonus_pool_sysset['bonus_pool_member_count'],true);
                    \think\facade\Log::info([
                        'file' => __FILE__,
                        'line' => __LINE__,
                        '$level_member' => $level_member,
                        '$total_member' => $total_member
                    ]);
                    foreach ($oglist as $ok=>$og){
                        $release_list =  Db::name('shop_order_goods')->alias('sog')
                            ->join('shop_product sp','sp.id = sog.proid')
                            ->join('member m','m.id = sog.mid')
                            ->where('sog.status','in',[1,2,3])
                            ->where('sog.aid',$order['aid'])
                            ->field('sog.id,sog.aid,sog.proid,sog.mid,sog.num,m.bonus_pool_money,m.levelid,m.pid,sp.bonus_pool_isrelease,sp.bonus_pool_releasetj,sp.bonus_pool_money_max,m.bonus_pool_max_money,m.bonus_pool_money')->select()->toArray();
                        //释放奖金池
                        if($release_list){
                            foreach ($release_list as $mk=>$mv){
                                //自己的订单 不拿奖励
                                if($order['mid'] == $mv['mid']){
                                    continue;
                                }

                                //其他用户购买的产品 是释放的 就给释放  不是释放的就不释放 释放数量是 购买的数量
                                if(!$mv['bonus_pool_isrelease']){
                                    continue;
                                }
                                $pool_releasetj = explode(',',$mv['bonus_pool_releasetj']);
                                //判断是否存在不发放的问题

                                if(!in_array($mv['levelid'],$pool_releasetj) && !in_array(-1,$pool_releasetj)){
                                    continue;
                                }
                                //如果设置总数量，等级的不再生效
                                \think\facade\Log::info([
                                    'file' => __FILE__,
                                    'line' => __LINE__,
                                    'sysset' =>$bonus_pool_sysset,
                                    'bonus_pool_total_mcount' => $bonus_pool_sysset['bonus_pool_total_mcount']
                                ]);
                                if($bonus_pool_sysset['bonus_pool_total_mcount'] <=0){
                                    //判断会员的数量
                                    $this_level_mids = $level_member[$mv['levelid']];
                                    $bonus_pool_member_limitnum = $bonus_pool_member_count[$mv['levelid']];
                                    $bonus_pool_member_limitnum = $bonus_pool_member_limitnum?$bonus_pool_member_limitnum:0;
                                    //获得奖金的会员 不在已释放的列表中且发放的会员数量大4于设置的数量
                                    \think\facade\Log::info([
                                        'file' => __FILE__,
                                        'line' => __LINE__,
                                        '$this_level_mids1' => $this_level_mids,
                                        'levelid' => $mv['levelid'],
                                        '$mv[mid]1'=>$mv['mid'],
                                        'count$this_level_mids' =>count($this_level_mids),
                                    ]);
                                    if(!in_array($mv['mid'],$this_level_mids) && count($this_level_mids) >= $bonus_pool_member_limitnum && $bonus_pool_member_limitnum >0){
                                        \think\facade\Log::info([
                                            'file' => __FILE__,
                                            'line' => __LINE__,
                                            '$this_level_mids' => $this_level_mids,
                                            '$mv[mid]'=>$mv['mid'],
                                            '$bonus_pool_member_limitnum' =>$bonus_pool_member_limitnum,
                                        ]);
                                        continue;
                                    }
                                }else{
                                    $total_member_count = count($total_member);
                                    if(!in_array($mv['mid'],$total_member) && count($total_member_count) >= $bonus_pool_sysset['bonus_pool_total_mcount']){
                                        \think\facade\Log::info([
                                            'file' => __FILE__,
                                            'line' => __LINE__,
                                            '$total_member_count' => $total_member_count,
                                            '$mv[mid]'=>$mv['mid'],
                                            'bonus_pool_total_mcount' =>$bonus_pool_sysset['bonus_pool_total_mcount'],
                                        ]);
                                        continue;
                                    }
                                }
                                for($i=1;$i<=$mv['num'];$i++){
                                    //新增 等级对应的奖金池分类
                                    $pc_where = [];
                                    $pc_where[] = ['aid','=',$order['aid']];
                                    $pc_where[] = ['bid','=',$order['bid']];
                                    $pc_where[] = ['status','=',1];
                                    $pc_where[] = Db::raw('find_in_set(-1,gettj) or find_in_set('.$mv['levelid'].',gettj)');
                                    $p_category = Db::name('bonus_pool_category')->where($pc_where)->column('id');
                                    \think\facade\Log::info([
                                        'file' => __FILE__,
                                        'line' => __LINE__,
                                        '$p_category' => $p_category,
                                        'category_sql'=>Db::name('bonus_pool_category')->getLastSql(),
                                    ]);
                                    $pool = Db::name('bonus_pool')->where('aid',$mv['aid'])->where('status',0) ->where('cid','in',$p_category)->order('id asc')->find();
                                    \think\facade\Log::info([
                                        'file' => __FILE__,
                                        'line' => __LINE__,
                                        'bonus_pool_sql' => Db::name('bonus_pool')->getLastSql(),
                                        '$pool' => $pool,
                                    ]);
                                    if(!$pool){
                                        continue ;
                                    }
                                    $wait_money = Db::name('member_bonus_pool_record')->where('aid',$mv['aid'])->where('status',0)->where('mid',$mv['mid'])->sum('commission');

                                    //判断商品 发放上限
                                    $total_product_pool_money =0+Db::name('member_bonus_pool_record')->where('aid',$og['aid'])->where('mid',$mv['mid'])->sum('commission');

                                    if($total_product_pool_money +$pool['money'] +$wait_money >  $mv['bonus_pool_max_money'] ){
                                        \think\facade\Log::write($mv['bonus_pool_money_max'].'超过商品设置上限'.$og['id'].'---total'.$total_product_pool_money.'---'.$pool['money'].'---'.$wait_money.'---mid:'.$mv['mid']);
                                        continue;
                                    }
                                    if($mv['bonus_pool_money'] +$pool['money'] +$wait_money >  $mv['bonus_pool_max_money'] ){
                                        \think\facade\Log::write($mv['bonus_pool_money_max'].'超过商品设置上限2'.$og['id'].'---total'.$total_product_pool_money.'---'.$pool['money'].'---'.$wait_money.'---mid:'.$mv['mid']);
                                        continue;
                                    }
                                    //插入记录
                                    $record = [
                                        'aid' => $og['aid'],
                                        'mid' => $mv['mid'],
                                        'orderid' => $og['orderid'],
                                        'frommid' => $og['mid'],
                                        'ogid' => $og['id'],
                                        'proid' =>$og['proid'],
                                        'bpid' => $pool['id'],
                                        'type' => 'shop',
                                        'commission' => $pool['money'],
                                        'remark' => '新进订单释放',
                                        'createtime' => time(),
                                        'status' => 0
                                    ];
                                    Db::name('member_bonus_pool_record')->insert($record);
                                    //修改奖金池状态
                                    Db::name('bonus_pool')->where('aid',$mv['aid'])->where('id',$pool['id'])->update(['status' => 1,'mid' => $mv['mid'],'endtime' => time()]);
                                }
                            }
                            //推荐人发放
                            foreach($release_list as $mk=>$mv){
                                if($mv['pid'] ==0){
                                    continue;
                                }
                                $parent = Db::name('member')->where('id',$mv['pid'])->find();
                                if($parent['bonus_pool_max_money'] <=0){
                                    continue;
                                }
                                $tpooldata = Db::name('bonus_pool')->where('aid',$mv['aid'])->where('status',0)->order('id asc')->find();
                                if(!$tpooldata){
                                    continue;
                                }
                                if($bonus_pool_sysset['bonus_pool_total_mcount'] <=0) {
                                    //判断会员的数量
                                    $this_level_mids = $level_member[$parent['levelid']];
                                    $bonus_pool_member_limitnum = $bonus_pool_member_count[$parent['levelid']];
                                    $bonus_pool_member_limitnum = $bonus_pool_member_limitnum ? $bonus_pool_member_limitnum : 0;
                                    //获得奖金的会员 不在已释放的列表中且发放的会员数量大于设置的数量
                                    if (!in_array($parent['id'], $this_level_mids) && count($this_level_mids) >= $bonus_pool_member_limitnum && $bonus_pool_member_limitnum > 0) {
                                        \think\facade\Log::info([
                                            'file' => __FILE__,
                                            'line' => __LINE__,
                                            '$this_level_mids' => $this_level_mids,
                                            '$parent[id]' => $parent['id'],
                                            '$bonus_pool_member_limitnum' => $bonus_pool_member_limitnum,
                                        ]);
                                        continue;
                                    }
                                }else{
                                    $total_member_count = count($total_member);
                                    if(!in_array($parent['id'],$total_member) && count($total_member_count) >= $bonus_pool_sysset['bonus_pool_total_mcount']){
                                        \think\facade\Log::info([
                                            'file' => __FILE__,
                                            'line' => __LINE__,
                                            '$total_member_count' => $total_member_count,
                                            '$parent[id]'=>$parent['id'],
                                            'bonus_pool_total_mcount' =>$bonus_pool_sysset['bonus_pool_total_mcount'],
                                        ]);
                                        continue;
                                    }
                                }
                                //判断是否存在不发放的问题
                                for($i=1;$i<=$mv['num'];$i++){
                                    //用户达到上限，不释放
                                    //未发放的
                                    $tpc_where = [];
                                    $tpc_where[] = ['aid','=',$order['aid']];
                                    $tpc_where[] = ['bid','=',$order['bid']];
                                    $tpc_where[] = ['status','=',1];
                                    $tpc_where[] = Db::raw('(find_in_set(-1,gettj) or find_in_set('.$parent['levelid'].',gettj))');
                                    $tp_category = Db::name('bonus_pool_category')->where($tpc_where)->column('id');
                                    $tpool = Db::name('bonus_pool')->where('aid',$mv['aid'])->where('status',0)->where('cid','in',$tp_category)->order('id asc')->find();
                                    \think\facade\Log::info([
                                        'file' => __FILE__,
                                        'line' => __LINE__,
                                        '$tp_category' => $tp_category,
                                        '$tpool' => $tpool,
                                    ]);
                                    if(!$tpool){
                                        continue ;
                                    }
                                    $wait_money = Db::name('member_bonus_pool_record')->where('aid',$mv['aid'])->where('status',0)->where('mid',$parent['id'])->sum('commission');
                                    // if($parent['bonus_pool_money']+$pool['money'] +$wait_money > $poolshopset['bonus_pool_money_max']){
                                    //     continue;
                                    // }
                                    //判断商品 发放上限
                                    $total_product_pool_money =0+Db::name('member_bonus_pool_record')->where('aid',$og['aid'])->where('mid',$parent['id'])->sum('commission');
                                    if($total_product_pool_money +$tpool['money'] +$wait_money >  $parent['bonus_pool_max_money']){
                                        \think\facade\Log::write($mv['bonus_pool_money_max'].'推荐人超过商品设置上限'.$og['id'].'---total'.$total_product_pool_money.'---'.$tpool['money'].'---'.$wait_money);
                                        continue;
                                    }
                                    if($parent['bonus_pool_money'] +$tpool['money'] +$wait_money >  $parent['bonus_pool_max_money'] ){
                                        \think\facade\Log::write($mv['bonus_pool_money_max'].'推荐人超过商品设置上限2'.$og['id'].'---total'.$total_product_pool_money.'---'.$tpool['money'].'---'.$wait_money);
                                        continue;
                                    }
                                    //插入记录
                                    $record = [
                                        'aid' => $og['aid'],
                                        'mid' => $parent['id'],
                                        'orderid' => $og['orderid'],
                                        'frommid' => $mv['mid'],
                                        'ogid' => $og['id'],
                                        'proid' =>$og['proid'],
                                        'bpid' => $tpool['id'],
                                        'type' => 'shop',
                                        'commission' => $tpool['money'],
                                        'remark' => '推荐人-新进订单释放',
                                        'createtime' => time(),
                                        'status' => 0
                                    ];
                                    Db::name('member_bonus_pool_record')->insert($record);
                                    //修改奖金池状态
                                    Db::name('bonus_pool')->where('aid',$mv['aid'])->where('id',$tpool['id'])->update(['status' => 1,'mid' => $parent['id'],'endtime' => time()]);
                                }

                            }
                        }
                    }
                }
                foreach($levellist as $key=>$level){
                    $mids = Db::name('member_bonus_pool_record')->alias('bpr')
                        ->join('member m','m.id = bpr.mid')
                        ->join('member_level ml','ml.id = m.levelid')
                        ->where('bpr.aid',$order['aid'])
                        ->where('ml.id',$level['id'])
                        ->group('bpr.mid')
                        ->column('mid');
                    \think\facade\Log::info([
                        'file' => __FILE__,
                        'line' => __LINE__,
                        'sq2222' => Db::name('member_bonus_pool_record')->getLastSql()
                    ]);
                    $level_member[$level['id']] =  $mids?$mids:[];
                    $total_member = array_merge($total_member,$mids);
                }
                //不设置总数，设置每个等级时
                if($bonus_pool_sysset['bonus_pool_total_mcount'] <= 0){
                    $bonus_pool_member_count = json_decode($bonus_pool_sysset['bonus_pool_member_count'],true);
                    \think\facade\Log::info([
                        'file' => __FILE__,
                        'line' => __LINE__,
                        '$bonus_pool_member_count' => $bonus_pool_member_count
                    ]);
                    $buy_level_mids = $level_member[$member['levelid']];

                    $buy_bonus_pool_member_limitnum = $bonus_pool_member_count[$member['levelid']];
                    \think\facade\Log::info([
                        'file' => __FILE__,
                        'line' => __LINE__,
                        '$level_member' => $level_member,
                        '$buy_level_mids' => $buy_level_mids,
                        'member' => $member,
                        'levelid' => $member['levelid'],
                        '$buy_bonus_pool_member_limitnum' =>$buy_bonus_pool_member_limitnum,
                        'count($buy_level_mids)' => count($buy_level_mids),
                        '$order[mid]'=> $order['mid']
                    ]);
                    $mids_count = count($buy_level_mids);
                    \think\facade\Log::info([
                        'file' => __FILE__,
                        'line' => __LINE__,
                        '$mids_count1' => $mids_count
                    ]);
//                    $order_number2 = Db::name('shop_order')->where('status','in',[1,2,3])->where('aid',$order['aid'])-> count();
                    //查找第一单的会员的等级，根据这个等级知道是不是首单的等级
                    $first_order_levelid = Db::name('shop_order')->alias('so')
                        ->join('member m','m.id = so.mid')
                        ->where('so.status','in',[1,2,3])
                        ->where('so.aid',$aid)
                        ->order('so.paytime asc')
                        ->limit(1)
                        ->value('m.levelid');
                    if($first_order_levelid == $member['levelid']){
                        $mids_count = $mids_count+2;
                    }else{
                        $mids_count = $mids_count+1;
                    }
//                    if($order_number2 ==2 && ){
//                        $mids_count = $mids_count+1;
//                    }
                    \think\facade\Log::info([
                        'file' => __FILE__,
                        'line' => __LINE__,
                        '$mids_count3' => $mids_count ,
                        'fistlevelid' => $first_order_levelid ,
                        'fist_sql' =>  Db::name('shop_order')->getLastSql()
                    ]);

                    \think\facade\Log::info([
                        'file' => __FILE__,
                        'line' => __LINE__,
                        '$mids_count2' => $mids_count
                    ]);
                    if(!in_array($order['mid'],$buy_level_mids) && $mids_count > $buy_bonus_pool_member_limitnum && $buy_bonus_pool_member_limitnum >0){// 0 > 1? true    1>1?
                        $is_add_max_money = false;
                    }
                }
                \think\facade\Log::info([
                    'file' => __FILE__,
                    'line' => __LINE__,
                    '$is_add_max_money' => $is_add_max_money,
                ]);
                if($is_add_max_money){
                    foreach ($oglist as $ok=>$og){
                        //更新用户的最大值
                        $member =Db::name('member')->where('aid',$og['aid'])->where('id',$og['mid'])->find();
                        $product_max_money = Db::name('shop_product')->where('aid',$og['aid'])->where('id',$og['proid'])->value('bonus_pool_money_max');
                        if($product_max_money>0){
                            $m_max_money = $product_max_money * $og['num'] + $member['bonus_pool_max_money'];
                            Db::name('member')->where('aid',$og['aid'])->where('id',$og['mid'])->update(['bonus_pool_max_money' => $m_max_money]);
                        }
                    }
                }
            }
        }
    }
    public static function prodcutBonusPoolCollect($aid,$oglist,$member){
        if(getcustom('product_bonus_pool')){
            $bonus_pool_status = Db::name('admin')->where('id',$aid)->value('bonus_pool_status');
            if($oglist && $member && $bonus_pool_status){
                foreach($oglist as $og){
                    $field='id,cid';
                    $field .=',bonus_pool_ratio,bonus_pool_num,bonus_pool_releasetj,bonus_pool_cid';//新增bonus_pool_cid分类
                    $product = Db::name('shop_product')
                        ->where('id',$og['proid'])
                        ->field($field)
                        ->find();
                    if($product && $product['bonus_pool_ratio']){
                        //加入奖金池
                        for($i=0;$i < $product['bonus_pool_num'];$i++){
                            $pool_money = $product['bonus_pool_ratio']/100 *$og['totalprice'];
                            $money = dd_money_format($pool_money / $product['bonus_pool_num']);
                            if($money > 0){
                                $pool_data = [
                                    'aid' => $og['aid'],
                                    'bid' => $og['bid'],
                                    'cid' =>$product['bonus_pool_cid'],//新增bonus_pool_cid分类
                                    'money' => $money,
                                    'ogid' => $og['id'],
                                    'createtime' => time()
                                ];
                                Db::name('bonus_pool')->insert($pool_data);
                            }

                        }
                    }

                }
            }
            if($oglist){
                //奖金池 记录 发放
                foreach ($oglist as $og){
                    $recordlist = Db::name('member_bonus_pool_record')->where('aid',$og['aid'])->where('ogid',$og['id'])->select()->toArray();

                    foreach ($recordlist as $rk=>$rv){
                        $member = Db::name('member')->where('id',$rv['mid'])->find();
                        $bonus_pool_money = dd_money_format($member['bonus_pool_money'] + $rv['commission']);
                        //增加log
                        $log = [
                            'aid' =>$rv['aid'],
                            'mid' =>$rv['mid'],
                            'frommid' => $og['mid'],
                            'commission' => $rv['commission'],
                            'after' => $bonus_pool_money,
                            'createtime' => time(),
                            'remark' => $rv['remark']
                        ];

                        Db::name('member_bonus_pool_log') ->insert($log);

                        Db::name('member')->where('id',$rv['mid'])->update(['bonus_pool_money' => $bonus_pool_money]);
                        Db::name('member_bonus_pool_record')->where('id',$rv['id'])->update(['status' => 1,'endtime' => time()]);
                    }
                }

            }
        }
    }
    //赠送激活币
    public static function giveActiveCoin($aid,$order,$type='shop'){
        if(getcustom('active_coin')) {
            //送激活币
            $coin_set = Db::name('active_coin_set')->where('aid', $aid)->find();
            $can_handle = 1;
            if($type=='shop'){
                if ($order['status'] == 1 && $coin_set['reward_time'] == 0) {
                    //设置的收货后赠送
                    $can_handle = 0;
                }
                if ($order['status'] == 3 && $coin_set['reward_time'] == 1) {
                    //设置的支付后赠送
                    $can_handle = 0;
                }
            }
            if ($order['is_coin'] == 1) {
                //已经赠送完了
                $can_handle = 0;
            }
            if (!$can_handle) {
                return true;
            }
            $activecoin_ratio = $coin_set['activecoin_ratio'];
            $member_activecoin_ratio = 100;
            $business_activecoin_ratio = 0;
            //查找会员id和商家id
            $mid = $order['mid'];
            $business_mid = 0;
            if ($order['bid'] > 0) {
                $binfo = Db::name('business')->where('aid', $aid)->where('id', $order['bid'])->find();
                $business_mid = $binfo['mid'];
                $activecoin_ratio = $binfo['activecoin_ratio'];
                $member_activecoin_ratio = $binfo['member_activecoin_ratio'];
                $business_activecoin_ratio = $binfo['business_activecoin_ratio'];
            }
            if($type=='shop'){
                $oglist = Db::name('shop_order_goods')->where('aid', $aid)->where('orderid', $order['id'])->select()->toArray();
                foreach ($oglist as $k => $og) {
                    $order_money = $og['real_totalprice'];
                    if ($coin_set['reward_type'] == 0) {
                        //按订单利润
                        $order_money = $og['real_totalprice'] - $og['cost_price'] * $og['num'];
                    }
                    $activecoin_total = bcmul($order_money, $activecoin_ratio / 100, 2);
                    $member_activecoin = bcmul($activecoin_total, $member_activecoin_ratio / 100, 2);
                    if ($member_activecoin > 0 && $mid > 0) {
                        \app\commons\Member::addactivecoin($aid, $mid, $member_activecoin, '购买商品' . $og['name'] . '赠送', '', $order['id']);
                    }
                    $business_activecoin = bcmul($activecoin_total, $business_activecoin_ratio / 100, 2);
                    if ($business_activecoin > 0 && $business_mid > 0) {
                        \app\commons\Member::addactivecoin($aid, $business_mid, $business_activecoin, '购买商品' . $og['name'] . '赠送', '', $order['id']);
                    }
                    Db::name('shop_order_goods')->where('id', $og['id'])->update(['activecoin' => $activecoin_total]);
                }
                Db::name('shop_order')->where('id', $order['id'])->update(['is_coin' => 1]);
            }else if($type=='maidan'){
                $order_money = $order['money'];

                $activecoin_total = bcmul($order_money, $activecoin_ratio / 100, 2);
                $member_activecoin = bcmul($activecoin_total, $member_activecoin_ratio / 100, 2);
                if ($member_activecoin > 0 && $mid > 0) {
                    \app\commons\Member::addactivecoin($aid, $mid, $member_activecoin, '买单赠送', '', $order['id']);
                }
                $business_activecoin = bcmul($activecoin_total, $business_activecoin_ratio / 100, 2);
                if ($business_activecoin > 0 && $business_mid > 0) {
                    \app\commons\Member::addactivecoin($aid, $business_mid, $business_activecoin, '买单赠送', '', $order['id']);
                }
                Db::name('maidan_order')->where('id', $order['id'])->update(['is_coin' => 1,'activecoin' => $activecoin_total]);
            }

            return true;
        }

    }
    //获取产品预计赠送激活币数量
    public static function getProductActiveCoin($aid,$product){
        if(getcustom('active_coin')) {
            //送激活币
            $coin_set = Db::name('active_coin_set')->where('aid', $aid)->find();
            $activecoin_ratio = $coin_set['activecoin_ratio'];
            $member_activecoin_ratio = 100;
            $business_activecoin_ratio = 0;
            //查找会员id和商家id
            $business_mid = 0;
            if ($product['bid'] > 0) {
                $binfo = Db::name('business')->where('aid', $aid)->where('id', $product['bid'])->find();
                $business_mid = $binfo['mid'];
                $activecoin_ratio = $binfo['activecoin_ratio'];
                $member_activecoin_ratio = $binfo['member_activecoin_ratio'];
                $business_activecoin_ratio = $binfo['business_activecoin_ratio'];
            }
            $order_money = $product['sell_price'];
            if ($coin_set['reward_type'] == 0) {
                //按订单利润
                $order_money = $product['sell_price'] - $product['cost_price'] ;
            }
            $activecoin_total = bcmul($order_money, $activecoin_ratio / 100, 2);
            $member_activecoin = bcmul($activecoin_total, $member_activecoin_ratio / 100, 2);
            return $member_activecoin?:0;
        }
    }

    //配送
    public static function peisong($aid,$bid,$orderid,$type,$psid,$order,$params=[]){
    	if(!$order) return ['status'=>0,'msg'=>'订单不存在'];
        if($order['status']!=1 && $order['status']!=12) return ['status'=>0,'msg'=>'订单状态不符合'];

        $set = Db::name('peisong_set')->where('aid',$aid)->find();
        if(getcustom('express_maiyatian')) {
            if($set['myt_status'] == 1){
                $psid = -2;//  -1、码科  -2、麦芽田配送
            }
        }

        $other = [];
        if(getcustom('express_maiyatian')){
            $other['myt_shop_id'] = $params['myt_shop_id']?$params['myt_shop_id']:0;
            $other['myt_weight']  = $params['myt_weight']?$params['myt_weight']:1;
            if(!is_numeric($other['myt_weight'])){
                return ['status'=>0,'msg'=>'重量必须为纯数字'];
            }
            $other['myt_remark']  = $params['myt_remark']?$params['myt_remark']:'';
        }

        $rs = \app\models\PeisongOrder::create($type,$order,$psid,$other);
        if($rs['status']==0) return $rs;
        //发货信息录入 微信小程序+微信支付
        if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
            \app\commons\Order::wxShipping($aid,$order,$type);
        }
        \app\commons\System::plog('订单配送'.$orderid);
        return ['status'=>1,'msg'=>'操作成功'];
    }

    //团队业绩统计 $config:阶梯配置  $yuji:待结算
    public static function  teamyejiJiangli($member,$yeji_set,$sysset,$config){
        $is_include_self = getcustom('yx_team_yeji_include_self');
        $pingji_yueji_custom = getcustom('yx_team_yeji_pingji_jinsuo');
        $is_jicha_custom = getcustom('yx_team_yeji_jicha');
        if(getcustom('yx_team_yeji')){
            $mid = $member['id'];
            $now_month = date('Y-m',strtotime('-1 month'));
            $fenhong = 0;
            $xuni_yeji = 0;  //虚拟业绩
            $yejiwhere = [];
            $levelup_time = 0;
            if($is_jicha_custom){
                $show_levelid = array_keys($config);
                if(in_array($member['levelid'],$show_levelid)){
                    $levelup_order = Db::name('member_levelup_order')
                        ->where('aid',$sysset['aid'])
                        ->where('mid',$mid)
                        ->where('levelid',$member['levelid'])
                        ->where('status',2)
                        ->order('levelup_time desc')
                        ->find();
                    $levelup_time = $levelup_order['levelup_time'];
                }
            }
            if($yeji_set['jiesuan_type'] == 1){//按月
                $month_start = strtotime(date('Y-m-01 00:00:00'));
                $month_end  = strtotime(date('Y-m-t 23:59:59'));
                if($is_jicha_custom){
                    if($levelup_time && $levelup_time > $month_start )$month_start =   $levelup_time;
                }
                $yejiwhere[] = ['createtime','between',[$month_start,$month_end]];
                //虚拟业绩
                $xuni_yeji = 0 +Db::name('tem_yeji_xuni')->where('aid',$sysset['aid'])->where('mid',$mid)->where('yeji_month',$now_month)->value('yeji');
            }elseif($yeji_set['jiesuan_type'] == 2){//按年
                $year_start=strtotime(date('Y') . '-01-01 00:00:00');
                $year_end=strtotime(date('Y') . '-12-31 23:59:59');
                if($is_jicha_custom){
                    if($levelup_time && $levelup_time > $year_start )$year_start =   $levelup_time;
                }
                $yejiwhere[] = ['createtime','between',[$year_start,$year_end]];
            }elseif($yeji_set['jiesuan_type'] == 3){//按季度
                $season_start=strtotime(date('Y-m-01 00:00:00'));
                $season_end=strtotime(date('Y-m-t 23:59:59',strtotime('+3 month')));
                if($is_jicha_custom){
                    if($levelup_time && $levelup_time > $season_start )$season_start =   $levelup_time;
                }
                $yejiwhere[] = ['createtime','between',[$season_start,$season_end]];
            }
            $yejiwhere[] = ['status','in','1,2,3'];
            $deep = 999;
            if($config[$member['levelid']]['levelnum'] > 0) $deep = intval($config[$member['levelid']]['levelnum']);
            $levelids = [];
            if($pingji_yueji_custom){
                //下级统计或越级不算上级业绩
                if($yeji_set['yueji_pingji_status']){
                    $nowlevelsort = Db::name('member_level')->where('aid',$member['aid'])->where('id',$member['levelid'])->value('sort');
                    //查找等级排序小于当前等级的会员
                    $levelids= Db::name('member_level')->where('aid',$member['aid'])->where('sort','<',$nowlevelsort)->column('id');
                }
            }
            $downmids = \app\commons\Member::getteammids($sysset['aid'],$mid,$deep,$levelids);
            if($is_include_self){
                if($yeji_set['include_self']) $downmids[] = $member['id'];
            }
//            if(!$downmids){
//                return 0;
//            }
            //下级人数
            $teamyeji = Db::name('shop_order_goods')->where('aid',$sysset['aid'])->where('mid','in',$downmids)->where($yejiwhere)->sum('real_totalprice');//real_totalprice totalprice
            $totalyeji = $teamyeji + $xuni_yeji;
            //阶梯设置
            $jt_range = $config[$member['levelid']]['range'];
//            if(!$jt_range){
//                return 0;
//            }
            $ratio = 0;
            foreach($jt_range as $rk=> $range){
                if( $range['start'] <= $totalyeji && $totalyeji < $range['end']){
                    $ratio = $range['ratio'];
                }
            }
            if($is_jicha_custom && $yeji_set['is_jicha']){
                $fenhong = self::getDownTeamyejiJiangli($member,$yeji_set,$sysset,$config);
            } else{
                if($ratio > 0){
                    $fenhong = $ratio / 100 * $totalyeji;
                }
            }

            //平级
            if($pingji_yueji_custom){
                $pingji_yueji_data = json_decode($yeji_set['yueji_pingji_data'],true);
                $pathlist = Db::name('member')->where('aid',$sysset['aid'])->where('find_in_set('.$member['id'].',path)'.($member['path'] ? ' or id in ('.$member['path'].')' : ''))->select()->toArray();
                $this_fenhong = 0;
                foreach($pathlist as $pk=>$pval){
                    $levelids = [];
                    //下级统计或越级不算上级业绩
                    if($yeji_set['yueji_pingji_status']){
                        $nowlevelsort = Db::name('member_level')->where('aid',$member['aid'])->where('id',$pval['levelid'])->value('sort');
                        //查找等级排序小于当前等级的会员
                        $levelids= Db::name('member_level')->where('aid',$member['aid'])->where('sort','<',$nowlevelsort)->column('id');
                    }
                    $downmids = \app\commons\Member::getteammids($sysset['aid'],$pval['id'],$deep,$levelids);
                    if($is_include_self){
                        if($yeji_set['include_self']) $downmids[] = $pval['id'];
                    }
                    //下级人数
                    $thistotalyeji = Db::name('shop_order_goods')->where('aid',$sysset['aid'])->where('mid','in',$downmids)->where($yejiwhere)->sum('real_totalprice');
                    //real_totalprice totalprice
                    //查找path
                    $parentList = [];
                    if($pval['path']){
                        $parentList = Db::name('member')->where('id','in',$pval['path'])->order(Db::raw('field(id,'.$pval['path'].')'))->select()->toArray();
                    }
                    if($parentList){
                        $parentList = array_reverse($parentList);
                        $level_lists = Db::name('member_level')->where('aid',$member['aid'])->column('*','id');
                        //当前设置
                        $this_pingjidata = $pingji_yueji_data[$pval['levelid']];
                        $parent_arr = [];
                        $is_jinsuo =  $this_pingjidata['jinsuo'];
                        $dai = 1;
                        foreach($parentList as $k=>$parent){
                            //没级别 紧缩掉
                            $level_data = $level_lists[$parent['levelid']]??[];
                            if(!$level_data){
                                continue;
                            }
                            //开启紧缩后，往上查找平级
                            if($is_jinsuo){
                                //如果 平级，且不到2级
                                if($parent['levelid'] != $pval['levelid'] || count($parent_arr) >= 2){
                                    continue;
                                }
                                if($parent['id'] == $member['id']){
                                    $parent_arr[$dai] =$parent;
                                }
                                $dai += 1;
                            }else{
                                if($dai <= 2 &&  $parent['levelid'] == $pval['levelid']){
                                    if($parent['id'] == $member['id']) {
                                        $parent_arr[$dai] = $parent;
                                    }
                                }
                                $dai +=1;
                            }
                        }
                        foreach($parent_arr as $dai=>$pv){
                            //发放奖励的会员 和 当前会员不是一个
                            if($pv['id'] != $member['id']){
                                continue;
                            }
                            $commission1_ratio = $this_pingjidata['commission1'];
                            $commission2_ratio = $this_pingjidata['commission2'];
                            if($dai ==1){
                                $this_fenhong  += dd_money_format($thistotalyeji * $commission1_ratio/100);
                            }
                            if($dai ==2){
                                $this_fenhong  += dd_money_format($thistotalyeji * $commission2_ratio/100);
                            }
                        }
                    }
                }
                $fenhong +=$this_fenhong;
            }

            return dd_money_format($fenhong);
        }
    }

    public static function getDownTeamyejiJiangli($member,$yeji_set,$sysset,$config,$yj=1){
        $is_include_self = getcustom('yx_team_yeji_include_self');
        $is_jicha_custom = getcustom('yx_team_yeji_jicha');
        if(getcustom('yx_team_yeji') && $is_jicha_custom){
            $mid = $member['id'];
            $deep = 999;
            if($config[$member['levelid']]['levelnum'] > 0) $deep = intval($config[$member['levelid']]['levelnum']);
            
            $show_levelid = array_keys($config);
            if(!in_array($member['levelid'],$show_levelid)) return 0;
            
            //当前会员所有下级，查出对应业绩
            $downmids = \app\commons\Member::getteammidsByStoplevelid($sysset['aid'],$mid,$deep,[],$member['levelid'],0);
            $new_downmids = [];
            foreach($downmids as $key=>$downmid){
                $this_levelid = Db::name('member')->where('aid',$sysset['aid'])->where('id',$downmid)->value('levelid');
                if(in_array($this_levelid,$show_levelid)){
                    $new_downmids[] =$downmid;
                }
            }
            $new_downmids[] = $mid;
            $yejidata = [];
            foreach($new_downmids as $key => $thismid){
                $this_levelup_time = 0;
                $this_levelid = Db::name('member')->where('aid',$sysset['aid'])->where('id',$thismid)->value('levelid');
                $this_show_levelid = array_keys($config);
                if(in_array($this_levelid,$this_show_levelid)){
                        $levelup_order = Db::name('member_levelup_order')
                            ->where('aid',$sysset['aid'])
                            ->where('mid',$thismid)
                            ->where('levelid',$this_levelid)
                            ->where('status',2)
                            ->order('levelup_time desc')
                            ->find();
                        $this_levelup_time = $levelup_order['levelup_time'];
                    }
                $this_downmids = \app\commons\Member::getteammids($sysset['aid'],$thismid);
                if($is_include_self){
                    if($yeji_set['include_self']) $this_downmids[] = $thismid;
                }
                $after_sj_yeji = 0;
                if($this_levelup_time){
                    $after_sj_yeji_where = [];
                    if($yj ==1){ //预计是当月获得当
                        if($yeji_set['jiesuan_type'] == 1) {//按月
                            $after_month_start = strtotime(date('Y-m-01 00:00:00'));
                            $after_month_end  = strtotime(date('Y-m-t 23:59:59'));
                            if($this_levelup_time > $after_month_start ){
                                $after_month_start =   $this_levelup_time;
                            }
                            $after_sj_yeji_where[] = ['createtime','between',[$after_month_start,$after_month_end]];
                        }
                        elseif($yeji_set['jiesuan_type'] == 2) {//按年
                            $after_year_start=strtotime(date('Y') . '-01-01 00:00:00');
                            $after_year_end=strtotime(date('Y') . '-12-31 23:59:59');
                            if($this_levelup_time > $after_year_start ) {
                                $after_year_start  = $this_levelup_time;
                            }
                            $after_sj_yeji_where[] = ['createtime','between',[$after_year_start,$after_year_end]];
                        }
                        elseif($yeji_set['jiesuan_type'] == 3) {//按季度
                            $this_season_start=strtotime(date('Y-m-01 00:00:00'));
                            if($this_levelup_time > $this_season_start ) {
                                $this_season_start =  $this_levelup_time;
                            }
                            $after_sj_yeji_where[] = ['createtime','between',[$this_season_start,$this_levelup_time]];
                        }
                    }else{// //非预计 是上月
                        if($yeji_set['jiesuan_type'] == 1){//按月
                            $start = strtotime(date('Y-m-01 00:00:00',strtotime('-1 month')));
                            $end  = strtotime(date('Y-m-t 23:59:59',strtotime('-1 month')));
                            //升级时间大于结束时间，无业绩
                        }elseif($yeji_set['jiesuan_type'] == 2){//按年
                            $start=strtotime((date('Y')-1) . '-01-01 00:00:00');
                            $end=strtotime((date('Y')-1) . '-12-31 23:59:59');
                        }elseif($yeji_set['jiesuan_type'] == 3){//按季度
                            $start=strtotime(date('Y-m-01 00:00:00',strtotime('-3 month')));
                            $end=strtotime(date('Y-m-t 23:59:59',strtotime('-1 month')));
                        }
                        if($this_levelup_time > $end){
                            $start = 0;
                            $end = 0;
                        }elseif ($this_levelup_time > $start && $this_levelup_time < $end){
                            $start =  $this_levelup_time;
                        }
                        $after_sj_yeji_where[] = ['createtime','between',[$start,$end]];
                    }

                    $after_sj_yeji= Db::name('shop_order_goods')->where('aid',$sysset['aid'])->where('mid','in',$this_downmids)->where('status','in',['1','2','3'])->where($after_sj_yeji_where)->sum('real_totalprice');
                }
                $jt_range = $config[$this_levelid]['range'];
                $this_ratio = 0;
                if($jt_range){
                    foreach($jt_range as $rk=> $range){
                        if( $range['start'] <= $after_sj_yeji && $after_sj_yeji < $range['end']){
                            $this_ratio = $range['ratio'];
                        }
                    }
                }
                $fenhong=0;
                if($this_ratio > 0)$fenhong = $after_sj_yeji * $this_ratio * 0.01;
                $yejidata[$thismid] = ['after_yeji'=> $after_sj_yeji,'ratio' => $this_ratio,'levelid' =>$this_levelid,'leveluptime' =>$this_levelup_time,'fenhong' => $fenhong ];
            }
            //找到当前会员的业绩，并删除，下面循环 进行和当前会员进行相减
            $this_member_yeji =$yejidata[$mid];
            $fenhong = $this_member_yeji['fenhong'];
            unset($yejidata[$mid]);
            foreach($yejidata as $key=>$yeji){
                $fenhong -= $yeji['fenhong'];
            }
            return  $fenhong;
        }
    }
    
    public static function giveActiveScore($aid,$order,$type = 'shop'){
        if(getcustom('active_score')) {
            //让利积分
            $active_score_set = Db::name('active_score_set')->where('aid', $aid)->find();
            $can_handle = 1;
            if($type=='shop'){
                if ($order['status'] == 1 && $active_score_set['reward_time'] == 0) {
                    //设置的收货后赠送
                    $can_handle = 0;
                }
                if ($order['status'] == 3 && $active_score_set['reward_time'] == 1) {
                    //设置的支付后赠送
                    $can_handle = 0;
                }
            }
            if ($order['is_activescore'] == 1) {
                //已经赠送完了
                $can_handle = 0;
            }
            if (!$can_handle) {
                return true;
            }
            
            if($type == 'shop'){
                $shopactivescore_ratio = $active_score_set['shopactivescore_ratio'];
                $member_shopactivescore_ratio = 100;
                $business_shopactivescore_ratio = 0;
                //查找会员id和商家id
                $mid = $order['mid'];
                $business_mid = 0;
                if ($order['bid'] > 0) {
                    $binfo = Db::name('business')->where('aid', $aid)->where('id', $order['bid'])->find();
                    $business_mid = $binfo['mid'];
                    $shopactivescore_ratio = $binfo['shopactivescore_ratio'];
                    $member_shopactivescore_ratio = $binfo['member_shopactivescore_ratio'];
                    $business_shopactivescore_ratio = $binfo['business_shopactivescore_ratio'];
                }
                $oglist = Db::name('shop_order_goods')->where('aid', $aid)->where('orderid', $order['id'])->select()->toArray();
                foreach ($oglist as $k => $og) {
                    $order_money = $og['real_totalprice'];
                    if ($active_score_set['reward_type'] == 0) {
                        //按订单利润
                        $order_money = $og['real_totalprice'] - $og['cost_price'] * $og['num'];
                    }
                    $shopactivescore_total = bcmul($order_money, $shopactivescore_ratio / 100, 2);
                    $member_shopactivescore = bcmul($shopactivescore_total, $member_shopactivescore_ratio / 100, 2);
                    if ($member_shopactivescore > 0 && $mid > 0) {
                        \app\commons\Member::addscore($aid, $mid, $member_shopactivescore, '购买商品' . $og['name'] . '赠送');
                    }
                    $business_shopactivescore = bcmul($shopactivescore_total, $business_shopactivescore_ratio / 100, 2);
                    if ($business_shopactivescore > 0 && $business_mid > 0) {
                        \app\commons\Member::addscore($aid, $business_mid, $business_shopactivescore, '购买商品' . $og['name'] . '赠送');
                    }
                    Db::name('shop_order_goods')->where('id', $og['id'])->update(['activescore' => $shopactivescore_total]);
                }
                Db::name('shop_order')->where('id', $order['id'])->update(['is_activescore' => 1]);
            }else if($type == 'maidan'){
                $maidanactivescore_ratio = $active_score_set['maidanactivescore_ratio'];
                $member_maidanactivescore_ratio = 100;
                $business_maidanactivescore_ratio = 0;
                //查找会员id和商家id
                $mid = $order['mid'];
                $business_mid = 0;
                if ($order['bid'] > 0) {
                    $binfo = Db::name('business')->where('aid', $aid)->where('id', $order['bid'])->find();
                    $business_mid = $binfo['mid'];
                    $maidanactivescore_ratio = $binfo['maidanactivescore_ratio'];
                    $member_maidanactivescore_ratio = $binfo['member_maidanactivescore_ratio'];
                    $business_maidanactivescore_ratio = $binfo['business_maidanactivescore_ratio'];
                }
                $order_money = $order['paymoney'];
                $maidanactivescore_total = bcmul($order_money, $maidanactivescore_ratio / 100, 2);
                $member_maidanactivescore = bcmul($maidanactivescore_total, $member_maidanactivescore_ratio / 100, 2);
                if ($member_maidanactivescore > 0 && $mid > 0) {
                    \app\commons\Member::addscore($aid, $mid, $member_maidanactivescore, '买单赠送');
                }
                $business_maidanactivescore = bcmul($maidanactivescore_total, $business_maidanactivescore_ratio / 100, 2);
                if ($business_maidanactivescore > 0 && $business_mid > 0) {
                    \app\commons\Member::addscore($aid, $business_mid, $business_maidanactivescore, '买单赠送');
                }
                Db::name('maidan_order')->where('id', $order['id'])->update(['is_activescore' => 1,'activescore' => $maidanactivescore_total]);
            }
            return true;
        }
    }

    //赠送佣金上限
    public static function giveCommissionMax($aid,$order,$type='shop'){
        if(getcustom('member_commission_max',$aid) && getcustom('add_commission_max',$aid)) {
            //送佣金上限
            $set = Db::name('admin_set')->where('aid', $aid)->find();
            if($set['member_commission_max']==0){
                //未开启佣金上限功能
                return true;
            }
            $can_handle = 1;
            if($type=='shop'){
                if ($order['status'] == 1 && $set['commission_max_time'] == 0) {
                    //设置的收货后赠送
                    $can_handle = 0;
                }
                if ($order['status'] == 3 && $set['commission_max_time'] == 1) {
                    //设置的支付后赠送
                    $can_handle = 0;
                }
            }
            if ($order['is_commission_max'] == 1) {
                //已经赠送完了
                $can_handle = 0;
            }
            if (!$can_handle) {
                return true;
            }
            if($order['give_commission_max2']>0 || $order['give_commission_max']>0){
                //产品单独设置了赠送佣金上限
                return true;
            }
            $commission_max_ratio = 0;
            $member_commission_max_ratio = 0;
            $business_commission_max_ratio = 0;
            //查找会员id和商家id
            $mid = $order['mid'];
            $business_mid = 0;
            if ($order['bid'] > 0) {
                $binfo = Db::name('business')->where('aid', $aid)->where('id', $order['bid'])->find();
                $business_mid = $binfo['mid'];
                $commission_max_ratio = $binfo['commission_max_ratio'];
                $member_commission_max_ratio = $binfo['member_commission_max_ratio'];
                $business_commission_max_ratio = $binfo['business_commission_max_ratio'];
            }
            /********************1、按商户独立设置的让利比例赠送**************************/
            if($commission_max_ratio>0){
                //商户独立设置让利比例
                if($type=='shop'){
                    $oglist = Db::name('shop_order_goods')->where('aid', $aid)->where('orderid', $order['id'])->select()->toArray();
                    foreach ($oglist as $k => $og) {
                        $order_money = $og['real_totalprice'];
                        if ($set['commission_max_type'] == 0) {
                            //按订单利润
                            $order_money = $og['real_totalprice'] - $og['cost_price'] * $og['num'];
                        }
                        $commission_max_total = bcmul($order_money, $commission_max_ratio / 100, 2);
                        $member_commission_max = bcmul($commission_max_total, $member_commission_max_ratio / 100, 2);
                        if ($member_commission_max > 0 && $mid > 0) {
                            \app\commons\Member::addcommissionmax($aid, $mid, $member_commission_max, '购买商品' . $og['name'] . '赠送', $type, $order['id']);
                        }
                        $business_commission_max = bcmul($commission_max_total, $business_commission_max_ratio / 100, 2);
                        if ($business_commission_max > 0 && $business_mid > 0) {
                            \app\commons\Member::addcommissionmax($aid, $business_mid, $business_commission_max, '购买商品' . $og['name'] . '赠送', $type, $order['id']);
                        }
                        Db::name('shop_order_goods')->where('id', $og['id'])->update(['commission_max_total' => $commission_max_total]);
                    }
                    Db::name('shop_order')->where('id', $order['id'])->update(['is_commission_max' => 1]);
                }else if($type=='maidan'){
                    $order_money = $order['money'];

                    $commission_max_total = bcmul($order_money, $commission_max_ratio / 100, 2);
                    $member_commission_max = bcmul($commission_max_total, $member_commission_max_ratio / 100, 2);
                    if ($member_commission_max > 0 && $mid > 0) {
                        \app\commons\Member::addcommissionmax($aid, $mid, $member_commission_max, '买单赠送', $type, $order['id']);
                    }
                    $business_commission_max = bcmul($commission_max_total, $business_commission_max_ratio / 100, 2);
                    if ($business_commission_max > 0 && $business_mid > 0) {
                        \app\commons\Member::addcommissionmax($aid, $business_mid, $business_commission_max, '买单赠送', $type, $order['id']);
                    }
                    Db::name('maidan_order')->where('id', $order['id'])->update(['is_commission_max' => 1,'commission_max_total' => $commission_max_total]);
                }
            }
            /********************2、按平台设置的消费额赠送**************************/
            if($commission_max_ratio<=0){
                //产品未设置，商户未设置，按消费额赠送
                $commission_max_xf = json_decode($set['commission_max_xf'],true);
                if($type=='shop'){
                    $order_money = $order['totalprice'];
                }
                if($type=='maidan'){
                    $order_money = $order['money'];
                }
                $count = count($commission_max_xf);
                $bili = 0;//赠送比例
                for($i=$count-1;$i>=0;$i--){
                    $xf_num = $commission_max_xf[$i]['xf_num']??0;
                    if($order_money>=$xf_num){
                        $bili = $commission_max_xf[$i]['bili']??0;
                        break;
                    }
                }
                $member_commission_max = bcmul($order_money, $bili / 100, 2);
                if($member_commission_max>0){
                    \app\commons\Member::addcommissionmax($aid, $mid, $member_commission_max, '购买订单ID'.$order['id'].'满' . $xf_num . '赠送', $type, $order['id']);
                }
                Db::name('shop_order')->where('id', $order['id'])->update(['is_commission_max' => 1]);
            }
            return true;
        }

    }

    //买单分红奖励 团队
    public static function maidanFenhongJl($order){
        if(getcustom('business_maidan_team_fenhong')){
            if($order['bid'] > 0){//付款是多商户的
                $aid = $order['aid'];
                $bid = $order['bid'];
                $business = Db::name('business')->where('aid',$aid)->where('id',$bid)->field('maidan_fenhong_jl_status,maidan_fenhong_jl_minprice,maidan_fenhong_jl_data,maidan_fenhong_jl_lv')->find();
                $maidan_fenhong_jicha =Db::name('business_sysset')->where('aid',$aid)->value('maidan_fenhong_jicha');
                //开启后
                if($business['maidan_fenhong_jl_status'] ==1){
                     //买单的人和是否和商户绑定
                    $buser = Db::name('admin_user')->where('aid',$aid)->where('bid',$bid)->where('isadmin',1)->find();
                    if($buser && $buser['mid']){
                        $maidan_member = Db::name('member')->where('id',$order['mid'])->field('id,path')->find();
                        $maidan_member_path = [];
                        if($maidan_member){
                            $maidan_member_path = explode(',',$maidan_member['path']);
                        }
                        $maidan_member_path = array_reverse($maidan_member_path);
                        //统计商户的买单金额
                        $business_maidan_money = Db::name('maidan_order')->where('aid',$order['aid'])->where('bid',$order['bid'])->where('status',1)->sum('paymoney');
                        
                        //如果买单的会员的 path中包含 商户会员，且 设置的最低金额 >= 商户买单金额 
                        if( $business_maidan_money >= $business['maidan_fenhong_jl_minprice'] && $business['maidan_fenhong_jl_minprice'] > 0){
                            $last_ratio = 0;
                            $maidan_fenhong_jl_data = json_decode($business['maidan_fenhong_jl_data'],true);
                            $level_i = 0;
                            foreach($maidan_member_path as  $pid){
                                $level_i++;
                                //层级 超过 设置的层级，不发放
                                if($level_i >$business['maidan_fenhong_jl_lv']  && $business['maidan_fenhong_jl_lv'] > 0){
                                        break;
                                }
                                $parent = Db::name('member')->where('id',$pid)->field('id,levelid')->find();
                                $thisratio =  $maidan_fenhong_jl_data[$parent['levelid']]['ratio'];
                              
                                //如果开启级差
                                if($maidan_fenhong_jicha){
                                    $ratio =   $thisratio - $last_ratio;
                                } else{
                                    $ratio =  $thisratio;
                                }
                                $last_ratio = $thisratio;
                                if($ratio > 0){
                                    $commission = dd_money_format($order['paymoney'] * $ratio * 0.01,2);
                                    if($commission > 0){
                                        \app\commons\Member::addcommission($aid,$pid,$maidan_member['id'],$commission,'买单分红奖励');
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
