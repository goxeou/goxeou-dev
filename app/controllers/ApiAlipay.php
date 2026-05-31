<?php

namespace app\controllers;
use think\facade\Db;

class ApiAlipay extends ApiCommon
{
	public function initialize(){
		parent::initialize();
	}
	//extends ApiCommon
		//订单支付
	public function hmpay(){
		$orderid = input('param.orderid/d');
        ll(input('param.'),'hmpay');
        
        return $this->json(['status'=>1,'msg'=>'该订单已支付','url'=>'alipays://platformapi/startapp?saId=10000007&qrcode=']);
	}
	
	//订单支付
	public function alipay(){
	    //	$this->checklogin();
		$orderid = input('param.orderid/d');
		ll( input('param.'),'para2m');
		$payorder = Db::name('payorder')->where('id',$orderid)->where('aid',aid)->find();
		if(!$payorder){
			return $this->json(['status'=>1,'msg'=>'该订单不存在']);
		}
		if($payorder['type']!='business_recharge' && $payorder['type']!='yuyue_addmoney'){
			if($payorder && $payorder['mid'] != mid && $payorder['type'] != 'restaurant_shop') {
				return $this->json(['status'=>1,'msg'=>'该订单不存在']);
			}
		}

		if($payorder['status']==1){
			return $this->json(['status'=>1,'msg'=>'该订单已支付']);
		}
	
		if(in_array($payorder['type'],[
            'shop','collage','cycle','kanjia','seckill','seckill2','scoreshop','restaurant_booking','restaurant_takeaway','choujiang'
        ])){
            $order = Db::name($payorder['type'].'_order')->where('id',$payorder['orderid'])->find();
            if($order['status']==4){
                return $this->json(['status'=>1,'msg'=>'该订单已关闭']);
            }elseif($order['status']!=0){
                return $this->json(['status'=>1,'msg'=>'订单状态不符合']);
            }
        }
		if($payorder['type'] == 'shopfront'){
			$order = Db::name('shop_order')->where('id',$payorder['orderid'])->find();
            if($order['status']==4){
                return $this->json(['status'=>1,'msg'=>'该订单已关闭']);
            }elseif($order['status']!=0){
                return $this->json(['status'=>1,'msg'=>'订单状态不符合']);
            }
		}

		if($payorder['type'] == 'restaurant_shop') {
            $order = Db::name($payorder['type'].'_order')->where('id',$payorder['orderid'])->find();
            if($order['status']==4){
                return $this->json(['status'=>1,'msg'=>'该订单已关闭']);
            }
        }
		if($payorder['type'] == 'collage'){ //拼团
			$order = Db::name('collage_order')->where('id',$payorder['orderid'])->find();
			if($order['buytype']!=1){
				$team = Db::name('collage_order_team')->where('aid',aid)->where('id',$order['teamid'])->find();
				if($team['status']==2){
					return $this->json(['status'=>1,'msg'=>'该团已满员']);
				}
				if($team['status']==3){
					return $this->json(['status'=>1,'msg'=>'该团已解散']);
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
					return $this->json(['status'=>1,'msg'=>'该团已满员']);
				}
				if($team['status']==3){
					Db::rollback();
					return $this->json(['status'=>1,'msg'=>'该团已解散']);
				}
				$rs = Db::name('lucky_collage_order')->where('aid',aid)->where('teamid',$order['teamid'])->where('mid',mid)->where('status','>',0)->where('id','<>',$order['id'])->find();
				if($rs){
					Db::rollback();
					return $this->json(['status'=>1,'msg'=>'您已经参与该团了']);
				}
			}
			Db::commit(); 
		}
		if($payorder['type']!='shop_hb' && $payorder['type']!='scoreshop_hb' && $payorder['type']!='balance' && $payorder['type']!='yuyue_balance' && $payorder['type']!='yuyue_addmoney'){
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
						return $this->json(['status'=>1,'msg'=>($freight['pstype']!=1?'配送':'取货').'时间必须在'.$freight['psprehour'].'小时之后']);
					}
				}
			}
		}
		if($payorder['type'] == 'coupon'){
			$orderinfo = Db::name('coupon_order')->where('id',$payorder['orderid'])->find();
			$coupon = Db::name('coupon')->where('id',$orderinfo['cpid'])->find();
			if($coupon['stock']<=0) return $this->json(['status'=>1,'msg'=>'库存不足']);
		}
	}
}
