<?php
namespace app\controllers;
use think\facade\Db;
class ApiPayy extends ApiCommon
{
	public function initialize(){
		parent::initialize();
	}
	public function adapay()
    {
       	$orderid = input('param.orderid/d');
       	$openid = input('param.openid');
       	$payorder = Db::name('payorder')->where('id',$orderid)->where('aid',aid)->find();
		if(!$payorder){
			return $this->json(['status'=>0,'msg'=>'该订单不存在']);
		}
		if($payorder['type']!='business_recharge' && $payorder['type']!='yuyue_addmoney'){
		    $is_create_child_order = true;
			if($is_create_child_order && $payorder && $payorder['mid'] != mid && $payorder['type'] != 'restaurant_shop') {
				//return $this->json(['status'=>0,'msg'=>'该订单不存在']);
			}
		}
        $score_weishu = 0;
        $payorder['score'] = dd_money_format($payorder['score'],$score_weishu);
		if($payorder['status']==1){
			return $this->json(['status'=>0,'msg'=>'该订单已支付']);
		}
        if($payorder['status']==2){
            $payorder = Db::name('payorder')->where('aid',$payorder['aid'])->where('bid',$payorder['bid'])->where('orderid',$payorder['orderid'])->where('type',$payorder['type'])->where('mid',$payorder['mid'])->where('status',0)->find();
            if($payorder)
                return $this->json(['status'=>-4,'msg'=>'该订单信息变动，请支付新订单','url'=>'/pagesExt/pay/pay?id='.$payorder['id']]);
            else
                return $this->json(['status'=>0,'msg'=>'该订单已取消']);
        }
		if(in_array($payorder['type'],[
            'shop','collage','cycle','kanjia','seckill','seckill2','scoreshop','restaurant_booking','restaurant_takeaway','choujiang','yuyue'
        ])){
            $order = Db::name($payorder['type'].'_order')->where('id',$payorder['orderid'])->find();
            if($order['status']==4){
                return $this->json(['status'=>0,'msg'=>'该订单已关闭']);
            }elseif($order['status']!=0){
                return $this->json(['status'=>0,'msg'=>'订单状态不符合']);
            }
            if($payorder['type'] == 'yuyue'){
				$product = Db::name('yuyue_product')->field('pdprehour,yynum')->where('id',$order['proid'])->find();
				$yydate = explode('-',$order['yy_time']);
				//开始时间
				$begindate = $yydate[0];
				if(strpos($begindate,'年') === false){
					$begindate = date('Y').'年'.$begindate;
				}
				$begindate = preg_replace(['/年|月/','/日/'],['-',''],$begindate);
				$date = date('Y-m-d H:i:s',strtotime(date('H:i',time())));
				$begintime = strtotime($begindate);
				if($begintime <= strtotime(date('H:i',time()))+$product['pdprehour']*60*60){
					return $this->json(['status'=>0,'msg'=>'预约时间已过，请选择其他时间']);
				}
				//查看是否已经存在
				$yycount= Db::name($payorder['type'].'_order')->where('aid',aid)->where('yy_time',$order['yy_time'])->where('proid',$order['proid'])->where('mid','<>',$order['mid'])->where('status','in','1,2')->count();
				if($yycount>=$product['yynum']){
					return $this->json(['status'=>0,'msg'=>'该段时间预约人数已满']);
				}
			}
            if($order['discount_rand_money'] > 0){
                $payorder['discountText'] = '随机立减'.$order['discount_rand_money'];
            }
           
        }
		if($payorder['type'] == 'shopfront'){
			$order = Db::name('shop_order')->where('id',$payorder['orderid'])->find();
            if($order['status']==4){
                return $this->json(['status'=>0,'msg'=>'该订单已关闭']);
            }elseif($order['status']!=0){
                return $this->json(['status'=>0,'msg'=>'订单状态不符合']);
            }
		}

		if($payorder['type'] == 'restaurant_shop') {
            $order = Db::name($payorder['type'].'_order')->where('id',$payorder['orderid'])->find();
            if($order['status']==4){
                return $this->json(['status'=>0,'msg'=>'该订单已关闭']);
            }
          
        }
		if($payorder['type'] == 'collage'){ //拼团
			$order = Db::name('collage_order')->where('id',$payorder['orderid'])->find();
			if($order['buytype']!=1){
				$team = Db::name('collage_order_team')->where('aid',aid)->where('id',$order['teamid'])->find();
				if($team['status']==2){
					return $this->json(['status'=>0,'msg'=>'该团已满员']);
				}
				if($team['status']==3){
					return $this->json(['status'=>0,'msg'=>'该团已解散']);
				}
			}
		}

		if($payorder['type'] == 'lucky_collage'){ //幸运拼团
			$order = Db::name('lucky_collage_order')->where('id',$payorder['orderid'])->find();
			Db::startTrans();
			if($order['buytype']!=1){
				$team = Db::name('lucky_collage_order_team')->where('aid',aid)->where('id',$order['teamid'])->lock(true)->find();
				if($team['status']==2){
					Db::rollback();
					return $this->json(['status'=>0,'msg'=>'该团已满员']);
				}
				if($team['status']==3){
					Db::rollback();
					return $this->json(['status'=>0,'msg'=>'该团已解散']);
				}
				$rs = Db::name('lucky_collage_order')->where('aid',aid)->where('teamid',$order['teamid'])->where('mid',$order['mid'])->where('status','>',0)->where('id','<>',$order['id'])->find();
				if($rs){
					Db::rollback();
					return $this->json(['status'=>0,'msg'=>'您已经参与该团了']);
				}
			}
		
			Db::commit(); 
		}
		if($payorder['type']!='shop_hb' && $payorder['type']!='scoreshop_hb' && $payorder['type']!='balance' && $payorder['type']!='yuyue_balance' && $payorder['type']!='yuyue_addmoney' && $payorder['type']!='shop_fenqi'){
			if($payorder['type'] == 'shopfront'){
				$orderinfo = Db::name('shop_order')->where('id',$payorder['orderid'])->find();
			}else{
				$orderinfo = Db::name($payorder['type'].'_order')->where('id',$payorder['orderid'])->find();
			}
			//判断配送时间选择是否符合要求
			if($orderinfo['freightid'] && $orderinfo['freight_time']){
				$freight = Db::name('freight')->where('id',$orderinfo['freightid'])->find();
				if($freight){
					$freight_times = explode('~',$orderinfo['freight_time']);
					if($freight_times[1]){
						$freighttime = strtotime(explode(' ',$freight_times[0])[0] . ' '.$freight_times[1]);
					}else{
						$freighttime = strtotime($freight_times[0]);
					}
					if(time() + $freight['psprehour']*3600 > $freighttime){
						return $this->json(['status'=>0,'msg'=>($freight['pstype']!=1?'配送':'取货').'时间必须在'.$freight['psprehour'].'小时之后']);
					}
				}
			}
		}

        if($payorder['type'] == 'shop_fenqi'){
			$orderinfo = Db::name('shop_order')->where('id',$payorder['orderid'])->find();
		}
        if($payorder['type'] == 'livepay'){//生活缴费
			$order = Db::name('livepay_order')->where('id',$payorder['orderid'])->find();
            if($order['status']==4){
                return $this->json(['status'=>0,'msg'=>'该订单已关闭']);
            }elseif($order['status']!=0){
                return $this->json(['status'=>0,'msg'=>'订单状态不符合']);
            }
		}

		if($payorder['type'] == 'coupon'){
			$orderinfo = Db::name('coupon_order')->where('id',$payorder['orderid'])->find();
			$coupon = Db::name('coupon')->where('id',$orderinfo['cpid'])->find();
			if($coupon['stock']<=0) return $this->json(['status'=>0,'msg'=>'库存不足']);
		}

		$set = Db::name('admin_set')->where('aid',aid)->find();
		if($payorder['type']=='shop'){
		    $oglist = Db::name('shop_order_goods')->where('orderid',$payorder['orderid'])->select()->toArray();
            foreach($oglist as $og){
				$product = Db::name('shop_product')->where('id',$og['proid'])->find();    
    	        if($product['is_baodan']==2){
                    $buynum = $og['num'] + Db::name('shop_order_goods')->where('paytime','>=',strtotime(date('Y-m-d')))->where('aid',aid)->where('mid',$og['mid'])->where('is_baodan',2)->where('status','in','1,2,3')->sum('num');
                    if ($buynum > $set['buy_type_num']) {
                      	return $this->json(['status'=>0,'msg'=>'超出今日购买上限']);
                    }
                    
    			}
           }
		}
       
        
// 		if($payorder['score'] > 0){
// 			return $this->json(['status'=>0,'msg'=>'不可用'.t('积分').'抵扣']);
// 		}
// 		if($payorder['credit1'] > 0 ){
// 			return $this->json(['status'=>0,'msg'=>'不可用'.t('credit1').'抵扣']);
// 		}
   		$buildfun = 'build_wx';
     	$rs = \app\commons\Sdbpay::$buildfun($payorder['aid'],$order['mid'],'支付订单',$payorder['ordernum'],$payorder['money'],$payorder['type'],'','',$openid);
     	$tourl = '/pages/index/index';
     	$rdata = [];
     	$rdata['payorder'] = $payorder;
     	$rdata['tourl'] = $tourl;
     	$orderinfo = Db::name($payorder['type'].'_order')->where('id',$payorder['orderid'])->find();
     	if ($rs['status']==0) {
    		$pay_info = $rs['data'];
    		$rdata['status']= 0;
    		$rdata['msg']= $rs['msg'];
     	}else {
     	    $rdata['status']= 1;
    		$rdata['pay_info']= $rs['data'];
     	}
		return $this->json($rdata);
        
    }
}