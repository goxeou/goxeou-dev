<?php



namespace app\customs;
use think\facade\Db;
class Fbpay
{
	//创建微支付 微信公众号
	public static function build_mp($aid,$bid,$mid,$title,$ordernum,$price,$tablename,$notify_url='',$repeat=0){
		if(!$notify_url) $notify_url = PRE_URL.'/notify.php';
		$member = Db::name('member')->where('id',$mid)->find();
		$package = array();
		$appinfo = \app\commons\System::appinfo($aid,'mp');
		if(!$appinfo['fbpay_appid']){
			return ['status'=>0,'msg'=>'请设置商户APPID'];
		}
		if(!$appinfo['fbpay_appsecret']){
			return ['status'=>0,'msg'=>'请设置商户密钥'];
		}

		$appid = $appinfo['appid'];
		$openid = $member[platform.'openid'];
		
		$reqData = [];
		$reqData['merchant_order_sn'] = $ordernum; //商户订单号
		$reqData['total_amount'] = $price;
		$reqData['pay_type'] = 'wxpay';
		$reqData['payWay'] = '02';
		$reqData['body'] = $title;
		$reqData['trmIp'] = request()->ip();
		$reqData['sub_appid'] = $appid;  //微信子公众号
		$reqData['notify_url'] = $notify_url;
		$reqData['user_id'] = $openid;
		$reqData['attach'] = $aid.':'.$tablename.':fbpaymp:'.md5($tablename.$ordernum.$appinfo['fbpay_appsecret']);

		$rs = self::postapi('build',$reqData,$appinfo);
		
		if($rs['result_code'] != '200'){
			if($rs['result_code'] == '0002'){
				$newordernum = date('YmdHis').rand(100000,999999);
				Db::name('payorder')->where('aid',$aid)->where('ordernum',$ordernum)->update(['ordernum'=>$newordernum]);
				if($tablename == 'shop_hb'){
					Db::name('shop_order')->where('aid',$aid)->where('ordernum',$ordernum)->update(['ordernum'=>$newordernum]);
					$orderlist = Db::name('shop_order')->where('ordernum','like',$ordernum.'%')->select()->toArray();
					foreach($orderlist as $order){
						$thisordernum = $newordernum.'_'.explode('_',$order['ordernum'])[1];
						Db::name('shop_order')->where('aid',$aid)->where('id',$order['id'])->update(['ordernum'=>$thisordernum]);
						Db::name('shop_order_goods')->where('aid',$aid)->where('orderid',$order['id'])->update(['ordernum'=>$thisordernum]);
					}
				}else{
					Db::name($tablename.'_order')->where('aid',$aid)->where('ordernum',$ordernum)->update(['ordernum'=>$newordernum]);
					if(\app\commons\Order::hasOrderGoodsTable($tablename)){
						Db::name($tablename.'_order_goods')->where('aid',$aid)->where('ordernum',$ordernum)->update(['ordernum'=>$newordernum]);
					}
				}
				return self::build_mp($aid,$bid,$mid,$title,$newordernum,$price,$tablename,$notify_url,1);
			}
			return ['status'=>0,'msg'=>$rs['msg']];
		}
		$respData = $rs['data']['sign_package'];
		$wOpt = [];
		$wOpt['appId'] = $respData['appId'];
		$wOpt['timeStamp'] = $respData['timeStamp'];
		$wOpt['nonceStr'] = $respData['nonceStr'];
		$wOpt['package'] = $respData['package'];
		$wOpt['signType'] = $respData['signType'];
		$wOpt['paySign'] = $respData['paySign'];
		
		return ['status'=>1,'data'=>$wOpt];
	}
	//创建微支付 微信小程序
	public static function build_wx($aid,$bid,$mid,$title,$ordernum,$price,$tablename,$notify_url=''){
		if(!$notify_url) $notify_url = PRE_URL.'/notify.php';
		$member = Db::name('member')->where('id',$mid)->find();
		$package = array();
		$appinfo = \app\commons\System::appinfo($aid,'mp');
		if(!$appinfo['fbpay_appid']){
			return ['status'=>0,'msg'=>'请设置商户APPID'];
		}
		if(!$appinfo['fbpay_appsecret']){
			return ['status'=>0,'msg'=>'请设置商户密钥'];
		}

		$appid = $appinfo['appid'];
		$openid = $member[platform.'openid'];
		
		$reqData = [];
		$reqData['merchant_order_sn'] = $ordernum; //商户订单号
		$reqData['total_amount'] = $price;
		$reqData['pay_type'] = 'wxpay';
		$reqData['payWay'] = '02';
		$reqData['body'] = $title;
		$reqData['trmIp'] = request()->ip();
		$reqData['sub_appid'] = $appid;  //微信子公众号
		$reqData['notify_url'] = $notify_url;
		$reqData['user_id'] = $openid;
		$reqData['attach'] = $aid.':'.$tablename.':fbpaywx:'.md5($tablename.$ordernum.$appinfo['fbpay_appsecret']);

		$rs = self::postapi('build',$reqData,$appinfo);
		
		if($rs['result_code'] != '200'){
			if($rs['result_code'] == '0002'){
				$newordernum = date('YmdHis').rand(100000,999999);
				Db::name('payorder')->where('aid',$aid)->where('ordernum',$ordernum)->update(['ordernum'=>$newordernum]);
				if($tablename == 'shop_hb'){
					Db::name('shop_order')->where('aid',$aid)->where('ordernum',$ordernum)->update(['ordernum'=>$newordernum]);
					$orderlist = Db::name('shop_order')->where('ordernum','like',$ordernum.'%')->select()->toArray();
					foreach($orderlist as $order){
						$thisordernum = $newordernum.'_'.explode('_',$order['ordernum'])[1];
						Db::name('shop_order')->where('aid',$aid)->where('id',$order['id'])->update(['ordernum'=>$thisordernum]);
						Db::name('shop_order_goods')->where('aid',$aid)->where('orderid',$order['id'])->update(['ordernum'=>$thisordernum]);
					}
				}else{
					Db::name($tablename.'_order')->where('aid',$aid)->where('ordernum',$ordernum)->update(['ordernum'=>$newordernum]);
					if(\app\commons\Order::hasOrderGoodsTable($tablename)){
						Db::name($tablename.'_order_goods')->where('aid',$aid)->where('ordernum',$ordernum)->update(['ordernum'=>$newordernum]);
					}
				}
				return self::build_mp($aid,$bid,$mid,$title,$newordernum,$price,$tablename,$notify_url,1);
			}
			return ['status'=>0,'msg'=>$rs['result_message']];
		}
		$respData = $rs['data']['sign_package'];
		$wOpt = [];
		$wOpt['appId'] = $respData['appId'];
		$wOpt['timeStamp'] = $respData['timeStamp'];
		$wOpt['nonceStr'] = $respData['nonceStr'];
		$wOpt['package'] = $respData['package'];
		$wOpt['signType'] = $respData['signType'];
		$wOpt['paySign'] = $respData['paySign'];
		
		return ['status'=>1,'data'=>$wOpt];
	}
	//创建支付 支付宝小程序
	public static function build_alipay($aid,$bid,$mid,$title,$ordernum,$price,$tablename,$notify_url=''){
		if(!$notify_url) $notify_url = PRE_URL.'/notify.php';
		$member = Db::name('member')->where('id',$mid)->find();
		$package = array();
		$appinfo = \app\commons\System::appinfo($aid,'mp');
		if(!$appinfo['fbpay_appid']){
			return ['status'=>0,'msg'=>'请设置商户APPID'];
		}
		if(!$appinfo['fbpay_appsecret']){
			return ['status'=>0,'msg'=>'请设置商户密钥'];
		}

		$appid = $appinfo['appid'];
		$openid = $member[platform.'openid'];
		
		$reqData = [];
		$reqData['merchant_order_sn'] = $ordernum; //商户订单号
		$reqData['total_amount'] = $price;
		$reqData['pay_type'] = 'alipay';
		$reqData['payWay'] = '02';
		$reqData['body'] = $title;
		$reqData['trmIp'] = request()->ip();
		$reqData['notify_url'] = $notify_url;
		$reqData['user_id'] = $openid;
		$reqData['attach'] = $aid.':'.$tablename.':fbpayali:'.md5($tablename.$ordernum.$appinfo['fbpay_appsecret']);

		$rs = self::postapi('build',$reqData,$appinfo);
		
		if($rs['result_code'] != '200'){
			if($rs['result_code'] == '0002'){
				$newordernum = date('YmdHis').rand(100000,999999);
				Db::name('payorder')->where('aid',$aid)->where('ordernum',$ordernum)->update(['ordernum'=>$newordernum]);
				if($tablename == 'shop_hb'){
					Db::name('shop_order')->where('aid',$aid)->where('ordernum',$ordernum)->update(['ordernum'=>$newordernum]);
					$orderlist = Db::name('shop_order')->where('ordernum','like',$ordernum.'%')->select()->toArray();
					foreach($orderlist as $order){
						$thisordernum = $newordernum.'_'.explode('_',$order['ordernum'])[1];
						Db::name('shop_order')->where('aid',$aid)->where('id',$order['id'])->update(['ordernum'=>$thisordernum]);
						Db::name('shop_order_goods')->where('aid',$aid)->where('orderid',$order['id'])->update(['ordernum'=>$thisordernum]);
					}
				}else{
					Db::name($tablename.'_order')->where('aid',$aid)->where('ordernum',$ordernum)->update(['ordernum'=>$newordernum]);
					if(\app\commons\Order::hasOrderGoodsTable($tablename)){
						Db::name($tablename.'_order_goods')->where('aid',$aid)->where('ordernum',$ordernum)->update(['ordernum'=>$newordernum]);
					}
				}
				return self::build_mp($aid,$bid,$mid,$title,$newordernum,$price,$tablename,$notify_url,1);
			}
			return ['status'=>0,'msg'=>$rs['result_message']];
		}
		$respData = $rs['data']['sign_package'];
		$wOpt = [];
		$wOpt['appId'] = $respData['appId'];
		$wOpt['timeStamp'] = $respData['timeStamp'];
		$wOpt['nonceStr'] = $respData['nonceStr'];
		$wOpt['package'] = $respData['package'];
		$wOpt['signType'] = $respData['signType'];
		$wOpt['paySign'] = $respData['paySign'];
		
		return ['status'=>1,'data'=>$wOpt];
	}

	//退款
	public static function refund($aid,$platform,$ordernum,$totalprice,$refundmoney,$refund_desc='退款'){
		$appinfo = \app\commons\System::appinfo($aid,$platform);
		$reqData = [];
		$reqData['merchant_order_sn'] = $ordernum;
		$reqData['merchant_refund_sn'] = $ordernum. '_' . rand(1000, 9999); //商户订单号
		$reqData['refund_amount'] = $refundmoney;
		//$reqData['refundReason'] = $refund_desc;

		$rs = self::postapi('refund',$reqData,$appinfo);

		if($rs['result_code'] != '200'){
			return ['status'=>0,'msg'=>$rs['result_message']];
		}
		
		//记录
		$data = [];
		$data['aid'] = $aid;
		$data['mch_id'] = $appinfo['fbpay_appid'];
		$data['ordernum'] = $ordernum;
		$data['out_refund_no'] = $reqData['merchant_refund_sn'];
		$data['totalprice'] = $totalprice;
		$data['refundmoney'] = $refundmoney;
		$data['createtime'] = date('Y-m-d H:i:s');
		$data['status'] = 1;
		$data['remark'] = $refund_desc;
		Db::name('wxrefund_log')->insert($data);

		$paylog = Db::name('wxpay_log')->where('aid',$aid)->where('ordernum',$ordernum)->find();
		if($paylog){
			Db::name('wxpay_log')->where('id',$paylog['id'])->inc('refund_money',$refundmoney)->update();
		}
		return ['status'=>1,'msg'=>'退款成功','resp'=>$rs['data']];
	}

	public static function postapi($action,$reqData,$appinfo){
		$apiArr = [
			'build'=>'fbpay.order.create',
			'refund'=>'fbpay.order.refund',
		];
		$url = 'https://shq-api.51fubei.com/gateway/agent';

		$data = [];
		$data['app_id'] = $appinfo['fbpay_appid'];
		$data['method'] = $apiArr[$action];
		$data['nonce'] = random(16);
		$data['biz_content'] = json_encode($reqData);
		ksort($data);
		$string1Arr = [];
		foreach ($data as $key => $v) {
			if(is_array($v)){
				$string1Arr[] = "{$key}=".json_encode($v);
			}else{
				$string1Arr[] = "{$key}={$v}";
			}
		}
		$string1 = implode('&',$string1Arr) . $appinfo['fbpay_appsecret'];
		$data['sign'] = strtoupper(md5($string1));

		$rs = curl_post($url,json_encode($data),0,['content-type:application/json;charset=UTF-8']);
		return $rs;
	}
}