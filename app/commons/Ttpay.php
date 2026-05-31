<?php


namespace app\commons;
use think\facade\Db;
class Ttpay
{
	//头条小程序支付
	public static function build($aid,$mid,$title,$ordernum,$price,$tablename,$notify_url=''){
		if(!$notify_url) $notify_url = PRE_URL.'/notify.php';
		
		$toutiaoapp = \app\commons\System::appinfo($aid,'toutiao');

		$url = 'https://developer.toutiao.com/api/apps/ecpay/v1/create_order';

		$data = [];
		$data['app_id'] = $toutiaoapp['appid'];
		$data['out_order_no'] = $ordernum;
		$data['total_amount'] = intval($price*100);
		$data['subject'] = $title;
		$data['body'] = $title;
		$data['valid_time'] = 86400;
		$data['cp_extra'] = json_encode(['param'=>$aid.':'.$tablename,'total_amount'=>$data['total_amount']]);
		$data['notify_url'] = $notify_url;
		$data['sign'] = self::getSign($data,$toutiaoapp['pay_salt']);
		$rs = request_post($url,jsonEncode($data));
		$rs = json_decode($rs,true);
		if($rs['err_no'] == 0){
			//self::pushorder($aid,$ordernum,0);
			return ['status'=>1,'orderInfo'=>$rs['data']];
		}else{
			return ['status'=>0,'msg'=>$rs['err_tips']];
		}
	}

    /**
     * 退款
     * @param $aid
     * @param $ordernum
     * @param $totalprice
     * @param $refund_money
     * @param $reason
     * @return array
     */
	public static function refund($aid,$ordernum,$totalprice,$refund_money,$reason){
		$toutiaoapp = \app\commons\System::appinfo($aid,'toutiao');
		$url = 'https://developer.toutiao.com/api/apps/ecpay/v1/create_refund';
		$data = [];
		$data['app_id'] = $toutiaoapp['appid'];
		$data['out_order_no'] = $ordernum;
		$data['out_refund_no'] = date('YmdHis');
		$data['refund_amount'] = intval($refund_money*100);
		$data['reason'] = $reason;
		$data['sign'] = self::getSign($data,$toutiaoapp['pay_salt']);
		$rs = request_post($url,jsonEncode($data));
		$rs = json_decode($rs,true);
		if($rs['err_no'] == 0){
			return ['status'=>1,'msg'=>'退款发起成功','refund_no'=>$rs['refund_no']];
		}else{
			return ['status'=>0,'msg'=>$rs['err_tips']];
		}
	}
	//头条小程序分账
	public static function settle($aid,$ordernum,$out_settle_no='',$settle_desc='分账'){
		//if(!$notify_url) $notify_url = PRE_URL.'/notify.php';

		$out_settle_no = $out_settle_no ? $out_settle_no : date('YmdHis').rand(100000,999999);
		
		$toutiaoapp = \app\commons\System::appinfo($aid,'toutiao');

		$url = 'https://developer.toutiao.com/api/apps/ecpay/v1/settle';

		$data = [];
		$data['app_id'] = $toutiaoapp['appid'];
		$data['out_order_no'] = $ordernum;
		$data['out_settle_no'] = $out_settle_no;
		$data['settle_desc'] = $settle_desc;
		//$data['notify_url'] = $notify_url;
		$data['sign'] = self::getSign($data,$toutiaoapp['pay_salt']);
		$rs = request_post($url,jsonEncode($data));
		$rs = json_decode($rs,true);
		if($rs['err_no'] == 0){
			return ['status'=>1,'orderInfo'=>$rs['data']];
		}else{
			return ['status'=>0,'msg'=>$rs['err_tips']];
		}
	}
	//订单同步 0未支付 1已支付 2已取消 4已核销 5退款中 6已退款 8退款失败
	public static function pushorder($aid,$ordernum,$order_status=4){
		$payorder = Db::name('payorder')->where('aid',$aid)->where('ordernum',$ordernum)->find();
		if(!$payorder) return ['status'=>0,'msg'=>'未找到支付订单'];
		$member = Db::name('member')->where('id',$payorder['mid'])->find();
		
		$toutiaoapp = \app\commons\System::appinfo($aid,'toutiao');
		
		$data = [];
		$data['app_id'] = $toutiaoapp['appid'];
		$data['access_token'] = self::access_token($aid);
		$data['app_name'] = 'douyin';
		$data['open_id'] = $member['toutiaoopenid'];
		$data['order_status'] = $order_status;
		$data['order_type'] = 0;
		$data['update_time'] = msectime();
		$order_detail = [];
		$order_detail['order_id'] = $ordernum;
		$order_detail['create_time'] = intval($payorder['paytime'].'000');
		if($order_status == 0){
		    $order_detail['status'] = '待支付';
		}elseif($order_status == 1){
		    $order_detail['status'] = '已支付';
		}elseif($order_status == 2){
		    $order_detail['status'] = '已取消';
		}elseif($order_status == 4){
		    $order_detail['status'] = '已核销';
		}elseif($order_status == 5){
		    $order_detail['status'] = '退款中';
		}elseif($order_status == 6){
		    $order_detail['status'] = '已退款';
		}elseif($order_status == 8){
		    $order_detail['status'] = '退款失败';
		}
		//$order_detail['status'] = '已核销';
		$order_detail['total_price'] = intval($payorder['money']*100);
		if($payorder['type'] == 'shop'){
			$order_detail['detail_url'] = 'pages/shop/orderdetail?id='.$payorder['orderid'];
			$oglist = Db::name('shop_order_goods')->where('orderid',$payorder['orderid'])->select()->toArray();
			$item_list = [];
			$amount = 0;
			foreach($oglist as $og){
				$amount += $og['num'];
				$item_list[] = ['item_code'=>''.$og['proid'],'img'=>$og['pic'],'title'=>$og['name'],'amount'=>intval($og['num']),'price'=>intval($og['totalprice']*100)];
			}
			$order_detail['amount'] = $amount;
			$order_detail['item_list'] = $item_list;
		}elseif($payorder['type'] == 'scoreshop'){
			$order_detail['detail_url'] = 'activity/scoreshop/orderdetail?id='.$payorder['orderid'];
			$oglist = Db::name('scoreshop_order_goods')->where('orderid',$payorder['orderid'])->select()->toArray();
			$item_list = [];
			$amount = 0;
			foreach($oglist as $og){
				$amount += $og['num'];
				$item_list[] = ['item_code'=>''.$og['proid'],'img'=>$og['pic'],'title'=>$og['name'],'amount'=>intval($og['num']),'price'=>intval($og['totalmoney']*100)];
			}
			$order_detail['amount'] = $amount;
			$order_detail['item_list'] = $item_list;
		}elseif($payorder['type'] == 'collage'){
			$order_detail['detail_url'] = 'activity/collage/orderdetail?id='.$payorder['orderid'];
			$orderinfo = Db::name('collage_order')->where('id',$payorder['orderid'])->find();
			$order_detail['amount'] = $orderinfo['num'];
			$order_detail['item_list'] = [['item_code'=>''.$og['proid'],'img'=>$og['propic'],'title'=>$og['proname'],'amount'=>intval($og['num']),'price'=>intval($og['totalprice']*100)]];
		}elseif($payorder['type'] == 'kanjia'){
			$order_detail['detail_url'] = 'activity/kanjia/orderdetail?id='.$payorder['orderid'];
			$orderinfo = Db::name('kanjia_order')->where('id',$payorder['orderid'])->find();
			$order_detail['amount'] = $orderinfo['num'];
			$order_detail['item_list'] = [['item_code'=>''.$og['proid'],'img'=>$og['propic'],'title'=>$og['proname'],'amount'=>intval($og['num']),'price'=>intval($og['totalprice']*100)]];
		}elseif($payorder['type'] == 'seckill'){
			$order_detail['detail_url'] = 'activity/seckill/orderdetail?id='.$payorder['orderid'];
			$orderinfo = Db::name('seckill_order')->where('id',$payorder['orderid'])->find();
			$order_detail['amount'] = $orderinfo['num'];
			$order_detail['item_list'] = [['item_code'=>''.$og['proid'],'img'=>$og['propic'],'title'=>$og['proname'],'amount'=>intval($og['num']),'price'=>intval($og['totalprice']*100)]];
		}elseif($payorder['type'] == 'tuangou'){
			$order_detail['detail_url'] = 'activity/tuangou/orderdetail?id='.$payorder['orderid'];
			$orderinfo = Db::name('seckill_order')->where('id',$payorder['orderid'])->find();
			$order_detail['amount'] = intval($orderinfo['num']);
			$order_detail['item_list'] = [['item_code'=>''.$og['proid'],'img'=>$og['propic'],'title'=>$og['proname'],'amount'=>intval($og['num']),'price'=>intval($og['totalprice']*100)]];
		}elseif($payorder['type'] == 'kecheng'){
			$order_detail['detail_url'] = 'activity/kecheng/orderlist';
			$orderinfo = Db::name('kecheng_order')->where('id',$payorder['orderid'])->find();
			$order_detail['amount'] = 1;
			$order_detail['item_list'] = [['item_code'=>''.$og['kcid'],'img'=>$og['pic'],'title'=>$og['title'],'amount'=>1,'price'=>intval($og['totalprice']*100)]];
		}else{
			$adminset = Db::name('admin_set')->where('aid',$aid)->find();
			$order_detail['detail_url'] = 'pages/my/usercenter';
			$order_detail['amount'] = 1;
			$order_detail['item_list'] = [['item_code'=>''.$payorder['orderid'],'img'=>$adminset['logo'],'title'=>'支付订单','amount'=>1,'price'=>intval($payorder['money']*100)]];
		}
		$data['order_detail'] = jsonEncode($order_detail);
		//var_dump($order_detail);

		$url = 'https://developer.toutiao.com/api/apps/order/v2/push';
		$headers = ["Content-type: application/json;charset='utf-8'"];
		$rs = curl_post($url,jsonEncode($data),0,$headers);
		$rs = json_decode($rs,true);
		//var_dump($rs);
		\think\facade\Log::write('---------------pushorder-------------');
		\think\facade\Log::write($rs);
		//\think\facade\Log::write($data);
		if($rs['err_code'] == 0){
			return ['status'=>1,'data'=>$rs];
		}else{
			return ['status'=>0,'msg'=>$rs['err_msg']];
		}
	}
	public static function access_token($aid,$iscache=true){
		$toutiaoapp = \app\commons\System::appinfo($aid,'toutiao');
		$appid = $toutiaoapp['appid'];
		$appsecret = $toutiaoapp['appsecret'];
		if(!$appid) return '';
		$tokendata = Db::name('access_token')->where('appid',$appid)->find();
		if($iscache && $tokendata && $tokendata['access_token'] && $tokendata['expires_time'] > time()){
			return $tokendata['access_token'];
		}else{
			if(!$appsecret) return '';
			$url = "https://developer.toutiao.com/api/apps/v2/token";
			$data = [];
			$data['appid'] = $appid;
			$data['secret'] = $appsecret;
			$data['grant_type'] = 'client_credential';

			$headers = ["Content-type: application/json;charset='utf-8'"];
			$rs = curl_post($url,jsonEncode($data),0,$headers);
			$rs = json_decode($rs,true);
			if($rs['err_no'] == 0){
				$access_token = $rs['data']['access_token'];
				if($tokendata){
					Db::name('access_token')->where('appid',$appid)->update(['access_token'=>$access_token,'expires_time'=>time()+7000]);
				}else{
					Db::name('access_token')->insert(['appid'=>$appid,'access_token'=>$access_token,'expires_time'=>time()+7000]);
				}
				return $access_token;
			}else{
				//\think\facade\Log::write($res);
				//return '';
				echojson(['status'=>0,'msg'=>$rs['err_tips'],'err'=>$rs]);
			}
		}
	}

	protected static function getSign($params,$paysecret){
		unset($params["sign"]);
		unset($params["app_id"]);
		unset($params["thirdparty_id"]);
		$paramArray = [];
		foreach ($params as $param) {
			$paramArray[] = trim($param);
		}
		$paramArray[] = trim($paysecret);
		sort($paramArray,2);
		$signStr = trim(implode('&', $paramArray));
		return md5($signStr);
	}
}