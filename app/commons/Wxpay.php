<?php


namespace app\commons;
use think\facade\Db;
use think\facade\Log;

/**
 * wxpay_type文档https://doc.weixin.qq.com/doc/w3_AT4AYwbFACwKTcT9kNuQa0MtULMT3?scode=AHMAHgcfAA042sx4xUAT4AYwbFACw
 */
class Wxpay
{
	//创建微支付 微信公众号
	public static function build_mp($aid,$bid,$mid,$title,$ordernum,$price,$tablename,$notify_url='',$openid=''){
		if(!$notify_url) $notify_url = PRE_URL.'/notify.php';
		$member = Db::name('member')->where('id',$mid)->find();
		$package = array();
		$appinfo = \app\commons\System::appinfo($aid,'mp');
		$appid = $appinfo['appid'];
        if($member){
            $openid = $member[platform.'openid'];
        }

		$isbusinesspay = false;
		if($bid > 0){
			$business = Db::name('business')->where('id',$bid)->find();
			if($business['sxpay_mno']){
				$rs = \app\customs\Sxpay::build_mp($aid,$bid,$mid,$title,$ordernum,$price,$tablename,$notify_url);
				return $rs;
			}
			$bset = Db::name('business_sysset')->where('aid',$aid)->find();
			if($bset['wxfw_status'] == 1){
				if($business['wxpayst']==1 && $business['wxpay_submchid']){
					$isbusinesspay = true;
					$package['appid'] = $bset['wxfw_appid'];
					$package['mch_id'] = $bset['wxfw_mchid'];
					$package['sub_appid'] = $appid;
					$package['sub_openid'] = $openid;
					$package['sub_mch_id'] = $business['wxpay_submchid'];
					$mchkey = $bset['wxfw_mchkey'];

					$chouchengmoney = 0;
					if($business['feepercent'] > 0){
						if(false){}else{
							$chouchengmoney = floatval($business['feepercent']) * 0.01 * $price;
						}
						}

					if($bset['commission_kouchu'] == 1){
						$commission = self::getcommission($tablename,$ordernum);
					}else{
						$commission = 0;
						}
					$chouchengmoney = $chouchengmoney + $commission;
					if($chouchengmoney > $price*0.3) return ['status'=>0,'msg'=>'分账金额过大'];

					if($chouchengmoney >= 0.01 && $price*0.3 >= $chouchengmoney){ //需要分账
						$package['profit_sharing'] = 'Y';
					}
					$package['attach'] = $aid.':'.$tablename.':mp:'.$bid;
				}
			}elseif($bset['wxfw_status'] == 2){
				if($business['wxpayst']==1 && $business['wxpay_submchid']){
					$isbusinesspay = true;
					$dbwxpayset = Db::name('sysset')->where('name','wxpayset')->value('value');
					$dbwxpayset = json_decode($dbwxpayset,true);
					if(!$dbwxpayset){
						return ['status'=>0,'msg'=>'未配置服务商微信支付信息'];
					}
					$package['appid'] = $dbwxpayset['appid'];
					$package['mch_id'] = $dbwxpayset['mchid'];
					$package['sub_appid'] = $appid;
					$package['sub_openid'] = $openid;
					$package['sub_mch_id'] = $business['wxpay_submchid'];
					$mchkey = $dbwxpayset['mchkey'];
					
					$feemoney = 0;
					if($business['feepercent'] > 0){
						if(false){}else{
							$feemoney = floatval($business['feepercent']) * 0.01 * $price;
						}
						}

					$chouchengmoney = 0;
					$admindata = Db::name('admin')->where('id',aid)->find();
					if($admindata['chouchengset']==0){ //默认抽成
						if($dbwxpayset && $dbwxpayset['chouchengset']!=0){
							if($dbwxpayset['chouchengset'] == 1){
								//$chouchengmoney = floatval($dbwxpayset['chouchengrate']) * 0.01 * $price;
								$chouchengmoney = floatval($dbwxpayset['chouchengrate']) * 0.01 * $feemoney;
								if($dbwxpayset['chouchengmin'] && $chouchengmoney < floatval($dbwxpayset['chouchengmin'])){
									$chouchengmoney = floatval($dbwxpayset['chouchengmin']);
								}
							}else{
								$chouchengmoney = floatval($dbwxpayset['chouchengmoney']);
							}
						}
					}elseif($admindata['chouchengset']==1){ //按比例抽成
						//$chouchengmoney = floatval($admindata['chouchengrate']) * 0.01 * $price;
						$chouchengmoney = floatval($admindata['chouchengrate']) * 0.01 * $feemoney;
						if($chouchengmoney < floatval($admindata['chouchengmin'])){
							$chouchengmoney = floatval($admindata['chouchengmin']);
						}
					}elseif($admindata['chouchengset']==2){ //按固定金额抽成
						$chouchengmoney = floatval($admindata['chouchengmoney']);
					}
					if($chouchengmoney >= 0.01 && $price*0.3 >= $chouchengmoney){ //需要分账
						$package['profit_sharing'] = 'Y';
					}else{
						$chouchengmoney = 0;
						//if($business['feepercent'] > 0){
						//	$chouchengmoney = floatval($business['feepercent']) * 0.01 * $price;
						//}
						$chouchengmoney = $chouchengmoney + $feemoney;

						if($bset['commission_kouchu'] == 1){
							$commission = self::getcommission($tablename,$ordernum);
						}else{
							$commission = 0;
							}
						$chouchengmoney = $chouchengmoney + $commission;
						if($chouchengmoney > $price*0.3) return ['status'=>0,'msg'=>'分账金额过大'];

						if($chouchengmoney >= 0.01 && $price*0.3 >= $chouchengmoney){ //需要分账
							$package['profit_sharing'] = 'Y';
						}
					}
					$package['attach'] = $aid.':'.$tablename.':mp:'.$bid;
				}
			}
		}
		if(!$isbusinesspay){
			if($appinfo['wxpay_type']==0){//0普通模式
                $package['appid'] = $appid;
				$package['mch_id'] = $appinfo['wxpay_mchid'];
				$package['openid'] = $openid;
				$mchkey = $appinfo['wxpay_mchkey'];
			}elseif($appinfo['wxpay_type']==1){//1服务商模式
                $dbwxpayset = Db::name('sysset')->where('name','wxpayset')->value('value');
				$dbwxpayset = json_decode($dbwxpayset,true);
				if(!$dbwxpayset){
					return ['status'=>0,'msg'=>'未配置服务商微信支付信息'];
				}
				$package['appid'] = $dbwxpayset['appid'];
				$package['sub_appid'] = $appid;
				$package['sub_openid'] = $openid;
				$package['mch_id'] = $dbwxpayset['mchid'];
				$package['sub_mch_id'] = $appinfo['wxpay_sub_mchid'];
				$mchkey = $dbwxpayset['mchkey'];

				$chouchengmoney = 0;
				$admindata = Db::name('admin')->where('id',aid)->find();
				if($admindata['chouchengset']==0){ //默认抽成
					if($dbwxpayset && $dbwxpayset['chouchengset']!=0){
						if($dbwxpayset['chouchengset'] == 1){
							$chouchengmoney = floatval($dbwxpayset['chouchengrate']) * 0.01 * $price;
							if($dbwxpayset['chouchengmin'] && $chouchengmoney < floatval($dbwxpayset['chouchengmin'])){
								$chouchengmoney = floatval($dbwxpayset['chouchengmin']);
							}
						}else{
							$chouchengmoney = floatval($dbwxpayset['chouchengmoney']);
						}
					}
				}elseif($admindata['chouchengset']==1){ //按比例抽成
					$chouchengmoney = floatval($admindata['chouchengrate']) * 0.01 * $price;
					if($chouchengmoney < floatval($admindata['chouchengmin'])){
						$chouchengmoney = floatval($admindata['chouchengmin']);
					}
				}elseif($admindata['chouchengset']==2){ //按固定金额抽成
					$chouchengmoney = floatval($admindata['chouchengmoney']);
				}
				if($chouchengmoney >= 0.01 && $price*0.3 >= $chouchengmoney){ //需要分账
					$package['profit_sharing'] = 'Y';
				}
			}elseif($appinfo['wxpay_type']==3){//随行付
				$rs = \app\customs\Sxpay::build_mp($aid,$bid,$mid,$title,$ordernum,$price,$tablename,$notify_url);
				//\think\facade\Log::write($rs);
				return $rs;
			}elseif($appinfo['wxpay_type']==4){
                }
			$package['attach'] = $aid.':'.$tablename.':mp';
		}

        $package['nonce_str'] = random(8);
		$package['body'] = mb_substr($title,0,42);
		$package['out_trade_no'] = $ordernum;
		$package['total_fee'] = $price * 100;
		//$package['spbill_create_ip'] = CLIENT_IP;
		//$package['time_start'] = date('YmdHis', TIMESTAMP);
		//$package['time_expire'] = date('YmdHis', TIMESTAMP + 600);
		$package['notify_url'] = $notify_url;
		$package['trade_type'] = 'JSAPI';
		ksort($package, SORT_STRING);
		$string1 = '';
		foreach ($package as $key => $v) {
			if (empty($v)) {
				continue;
			} 
			$string1 .= "{$key}={$v}&";
		}
		$string1 .= "key=".$mchkey;
		$package['sign'] = strtoupper(md5($string1));
		$dat = array2xml($package);
		//dump($price);
		//dump($package);
		//dump($dat);
		//dump($mchkey);
		$response = request_post('https://api.mch.weixin.qq.com/pay/unifiedorder', $dat);
		
		$xml = @simplexml_load_string($response, 'SimpleXMLElement', LIBXML_NOCDATA);
		if (strval($xml->return_code) == 'FAIL') {
			return ['status'=>0,'msg'=>strval($xml->return_msg)];
		} 
		if (strval($xml->result_code) == 'FAIL') {
			return ['status'=>0,'msg'=>strval($xml->err_code_des)];
		}
		$prepayid = $xml->prepay_id;
		$wOpt = [];
		if(platform=='wx'){
			$wOpt['appId'] = $appid;
		}else{
			$wOpt['appId'] = $package['appid'];
		}
		$wOpt['timeStamp'] = time()."";
		$wOpt['nonceStr'] = random(8);
		$wOpt['package'] = 'prepay_id=' . $prepayid;
		$wOpt['signType'] = 'MD5';
		ksort($wOpt, SORT_STRING);
		foreach ($wOpt as $key => $v) {
			$string .= "{$key}={$v}&";
		}
		$string .= "key=".$mchkey;
		$wOpt['paySign'] = strtoupper(md5($string));
		return ['status'=>1,'data'=>$wOpt];
	}
	//创建微支付 微信小程序 https://pay.weixin.qq.com/wiki/doc/api/jsapi.php?chapter=9_1
	public static function build_wx($aid,$bid,$mid,$title,$ordernum,$price,$tablename,$notify_url='',$openid=''){

		if(!$notify_url) $notify_url = PRE_URL.'/notify.php';
		$time = time();
		$member = Db::name('member')->where('id',$mid)->find();
		$package = array();
		$appinfo = \app\commons\System::appinfo($aid,'wx');
		$appid = $appinfo['appid'];
		if($member){
            $openid = $member[platform.'openid'];
        }
		
		
		if($tablename == 'shop'){
			$order_detail = [];
			$order = Db::name('shop_order')->where('aid',$aid)->where('ordernum',$ordernum)->find();
			if($order['fromwxvideo'] == 1){
				if(!$order['wxvideo_order_id']){
					$rs = \app\commons\Wxvideo::createorder($order['id']);
					if($rs['status'] == 0){
						return $rs;
					}
				}
				//生成支付参数
				$url = 'https://api.weixin.qq.com/shop/order/getpaymentparams?access_token='.\app\commons\Wechat::access_token($aid,'wx');
				$rs = curl_post($url,jsonEncode(['out_order_id'=>strval($order['id']),'openid'=>$openid]));
				$rs = json_decode($rs,true);
				if($rs['errcode'] == 0 && $rs['payment_params']){
					$wOpt = $rs['payment_params'];
					return ['status'=>1,'data'=>$wOpt,'fromwxvideo'=>1];
				}else{
					return ['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs)];
				}
			}
		}

		if($appinfo['wxpay_type'] == 2){//二级商户模式
			$url = 'https://api.weixin.qq.com/shop/pay/createorder?access_token='.\app\commons\Wechat::access_token($aid,'wx');
			$postdata = [];
			$postdata['openid'] = $openid;
			$postdata['combine_trade_no'] = 'P'.$ordernum;
			$postdata['sub_orders'] = [['mchid'=>$appinfo['wxpay_sub_mchid2'],'amount'=>intval($price * 100),'trade_no'=>$ordernum,'description'=>$title]];
			$rs = curl_post($url,jsonEncode($postdata));
			$rs = json_decode($rs,true);
			//Log::write($rs);
			if($rs['errcode'] == 0 && $rs['payment_params']){
				$wOpt = $rs['payment_params'];
				return ['status'=>1,'data'=>$wOpt,'wxpay_type'=>2];
			}else{
				return ['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs)];
			}
		}

		$isbusinesspay = false;
		if($bid > 0){
			$business = Db::name('business')->where('id',$bid)->find();
			if($business['sxpay_mno']){
				$rs = \app\customs\Sxpay::build_wx($aid,$bid,$mid,$title,$ordernum,$price,$tablename,$notify_url);
				return $rs;
			}
			$bset = Db::name('business_sysset')->where('aid',$aid)->find();
			if($bset['wxfw_status'] == 1){
				if($business['wxpayst']==1 && $business['wxpay_submchid']){
					$isbusinesspay = true;
					$package['appid'] = $bset['wxfw_appid'];
					$package['mch_id'] = $bset['wxfw_mchid'];
					$package['sub_appid'] = $appid;
					$package['sub_openid'] = $openid;
					$package['sub_mch_id'] = $business['wxpay_submchid'];
					$mchkey = $bset['wxfw_mchkey'];

					$chouchengmoney = 0;
					if($business['feepercent'] > 0){
						if(false){}else{
							$chouchengmoney = floatval($business['feepercent']) * 0.01 * $price;
						}
						}

					if($bset['commission_kouchu'] == 1){
						$commission = self::getcommission($tablename,$ordernum);
					}else{
						$commission = 0;
						}
					$chouchengmoney = $chouchengmoney + $commission;
					if($chouchengmoney > $price*0.3) return ['status'=>0,'msg'=>'分账金额过大'];

					if($chouchengmoney >= 0.01 && $price*0.3 >= $chouchengmoney){ //需要分账
						$package['profit_sharing'] = 'Y';
					}
					$package['attach'] = $aid.':'.$tablename.':wx:'.$bid;
				}
			}elseif($bset['wxfw_status'] == 2){
				if($business['wxpayst']==1 && $business['wxpay_submchid']){
					$isbusinesspay = true;
					$dbwxpayset = Db::name('sysset')->where('name','wxpayset')->value('value');
					$dbwxpayset = json_decode($dbwxpayset,true);
					if(!$dbwxpayset){
						return ['status'=>0,'msg'=>'未配置服务商微信支付信息'];
					}
					$package['appid'] = $dbwxpayset['appid'];
					$package['mch_id'] = $dbwxpayset['mchid'];
					$package['sub_appid'] = $appid;
					$package['sub_openid'] = $openid;
					$package['sub_mch_id'] = $business['wxpay_submchid'];
					$mchkey = $dbwxpayset['mchkey'];

					$feemoney = 0;
					if($business['feepercent'] > 0){
						if(false){}else{
							$feemoney = floatval($business['feepercent']) * 0.01 * $price;
						}
						}

					$chouchengmoney = 0;
					$admindata = Db::name('admin')->where('id',aid)->find();
					if($admindata['chouchengset']==0){ //默认抽成
						if($dbwxpayset && $dbwxpayset['chouchengset']!=0){
							if($dbwxpayset['chouchengset'] == 1){
								//$chouchengmoney = floatval($dbwxpayset['chouchengrate']) * 0.01 * $price;
								$chouchengmoney = floatval($dbwxpayset['chouchengrate']) * 0.01 * $feemoney;
								if($dbwxpayset['chouchengmin'] && $chouchengmoney < floatval($dbwxpayset['chouchengmin'])){
									$chouchengmoney = floatval($dbwxpayset['chouchengmin']);
								}
							}else{
								$chouchengmoney = floatval($dbwxpayset['chouchengmoney']);
							}
						}
					}elseif($admindata['chouchengset']==1){ //按比例抽成
						//$chouchengmoney = floatval($admindata['chouchengrate']) * 0.01 * $price;
						$chouchengmoney = floatval($admindata['chouchengrate']) * 0.01 * $feemoney;
						if($chouchengmoney < floatval($admindata['chouchengmin'])){
							$chouchengmoney = floatval($admindata['chouchengmin']);
						}
					}elseif($admindata['chouchengset']==2){ //按固定金额抽成
						$chouchengmoney = floatval($admindata['chouchengmoney']);
					}
					if($chouchengmoney > 0 && $price*0.3 >= $chouchengmoney){ //需要分账
						$package['profit_sharing'] = 'Y';
					}else{
						$chouchengmoney = 0;
						//if($business['feepercent'] > 0){
						//	$chouchengmoney = floatval($business['feepercent']) * 0.01 * $price;
						//}
						$chouchengmoney = $chouchengmoney + $feemoney;

						if($bset['commission_kouchu'] == 1){
							$commission = self::getcommission($tablename,$ordernum);
						}else{
							$commission = 0;
							}
						$chouchengmoney = $chouchengmoney + $commission;
						if($chouchengmoney > $price*0.3) return ['status'=>0,'msg'=>'分账金额过大'];

						if($chouchengmoney >= 0.01 && $price*0.3 >= $chouchengmoney){ //需要分账
							$package['profit_sharing'] = 'Y';
						}
					}
					$package['attach'] = $aid.':'.$tablename.':mp:'.$bid;
				}
			}
		}

		if(!$isbusinesspay){
			if($appinfo['wxpay_type']==0){
				$package['appid'] = $appid;
				$package['mch_id'] = $appinfo['wxpay_mchid'];
				$package['openid'] = $openid;
				$mchkey = $appinfo['wxpay_mchkey'];
			}elseif($appinfo['wxpay_type']==3){
				$rs = \app\customs\Sxpay::build_wx($aid,$bid,$mid,$title,$ordernum,$price,$tablename,$notify_url);
				return $rs;
			}elseif($appinfo['wxpay_type']==1){
				$dbwxpayset = Db::name('sysset')->where('name','wxpayset')->value('value');
				$dbwxpayset = json_decode($dbwxpayset,true);
				if(!$dbwxpayset){
					return ['status'=>0,'msg'=>'未配置服务商微信支付信息'];
				}
				$package['appid'] = $dbwxpayset['appid'];
				$package['sub_appid'] = $appid;
				$package['sub_openid'] = $openid;
				$package['mch_id'] = $dbwxpayset['mchid'];
				$package['sub_mch_id'] = $appinfo['wxpay_sub_mchid'];
				$mchkey = $dbwxpayset['mchkey'];

				$chouchengmoney = 0;
				$admindata = Db::name('admin')->where('id',aid)->find();
				if($admindata['chouchengset']==0){ //默认抽成
					if($dbwxpayset && $dbwxpayset['chouchengset']!=0){
						if($dbwxpayset['chouchengset'] == 1){
							$chouchengmoney = floatval($dbwxpayset['chouchengrate']) * 0.01 * $price;
							if($dbwxpayset['chouchengmin'] && $chouchengmoney < floatval($dbwxpayset['chouchengmin'])){
								$chouchengmoney = floatval($dbwxpayset['chouchengmin']);
							}
						}else{
							$chouchengmoney = floatval($dbwxpayset['chouchengmoney']);
						}
					}
				}elseif($admindata['chouchengset']==1){ //按比例抽成
					$chouchengmoney = floatval($admindata['chouchengrate']) * 0.01 * $price;
					if($chouchengmoney < floatval($admindata['chouchengmin'])){
						$chouchengmoney = floatval($admindata['chouchengmin']);
					}
				}elseif($admindata['chouchengset']==2){ //按固定金额抽成
					$chouchengmoney = floatval($admindata['chouchengmoney']);
				}
				if($chouchengmoney >= 0.01 && $price*0.3 >= $chouchengmoney){ //需要分账
					$package['profit_sharing'] = 'Y';
				}
			}elseif($appinfo['wxpay_type']==4){
                }
			$package['attach'] = $aid.':'.$tablename.':wx';
		}


        $package['nonce_str'] = random(8);
		$package['body'] = mb_substr($title,0,42);
		$package['out_trade_no'] = $ordernum;
		$package['total_fee'] = $price * 100;
		//$package['spbill_create_ip'] = CLIENT_IP;
		//$package['time_start'] = date('YmdHis', TIMESTAMP);
		//$package['time_expire'] = date('YmdHis', TIMESTAMP + 600);
		$package['notify_url'] = $notify_url;
		$package['trade_type'] = 'JSAPI';
		ksort($package, SORT_STRING);
		$string1 = '';
		foreach ($package as $key => $v) {
			if (empty($v)) {
				continue;
			} 
			$string1 .= "{$key}={$v}&";
		}
		$string1 .= "key=".$mchkey;
		$package['sign'] = strtoupper(md5($string1));
		$dat = array2xml($package);
		//dump($price);
		//dump($package);
		//dump($dat);
		//dump($mchkey);
		$response = request_post('https://api.mch.weixin.qq.com/pay/unifiedorder', $dat);
		
		$xml = @simplexml_load_string($response, 'SimpleXMLElement', LIBXML_NOCDATA);
		if (strval($xml->return_code) == 'FAIL') {
			return ['status'=>0,'msg'=>strval($xml->return_msg)];
		} 
		if (strval($xml->result_code) == 'FAIL') {
			return ['status'=>0,'msg'=>strval($xml->err_code_des)];
		}
		$prepayid = strval($xml->prepay_id);
		$wOpt = [];
		if(platform=='wx'){
			$wOpt['appId'] = $appid;
		}else{
			$wOpt['appId'] = $package['appid'];
		}
		$wOpt['timeStamp'] = $time."";
		$wOpt['nonceStr'] = random(8);
		$wOpt['package'] = 'prepay_id=' . $prepayid;
		$wOpt['signType'] = 'MD5';
		ksort($wOpt, SORT_STRING);
		foreach ($wOpt as $key => $v) {
			$string .= "{$key}={$v}&";
		}
		$string .= "key=".$mchkey;
		$wOpt['paySign'] = strtoupper(md5($string));
		
		return ['status'=>1,'data'=>$wOpt];
	}
	//创建微支付参数H5
	public static function build_h5($aid,$bid,$mid,$title,$ordernum,$price,$tablename,$notify_url=''){
		if(!$notify_url) $notify_url = PRE_URL.'/notify.php';
		$set = Db::name('admin_set')->where('aid',$aid)->find();
		$package = array();

		$appinfo = \app\commons\System::appinfo($aid,'h5');
		$appid = $appinfo['appid'];

		$isbusinesspay = false;
		if($bid > 0){
			$bset = Db::name('business_sysset')->where('aid',$aid)->find();
            $business = Db::name('business')->where('id',$bid)->find();
			if($bset['wxfw_status'] == 1){
				if($business['wxpayst']==1 && $business['wxpay_submchid']){
					$isbusinesspay = true;
					$package['appid'] = $bset['wxfw_appid'];
					$package['mch_id'] = $bset['wxfw_mchid'];
					if($appid){
						$package['sub_appid'] = $appid;
					}
					$package['sub_mch_id'] = $business['wxpay_submchid'];
					$mchkey = $bset['wxfw_mchkey'];

					$chouchengmoney = 0;
					if($business['feepercent'] > 0){
						if(false){}else{
							$chouchengmoney = floatval($business['feepercent']) * 0.01 * $price;
						}
						}

					if($bset['commission_kouchu'] == 1){
						$commission = self::getcommission($tablename,$ordernum);
					}else{
						$commission = 0;
						}
					$chouchengmoney = $chouchengmoney + $commission;
					if($chouchengmoney > $price*0.3) return ['status'=>0,'msg'=>'分账金额过大'];

					if($chouchengmoney >= 0.01 && $price*0.3 >= $chouchengmoney){ //需要分账
						$package['profit_sharing'] = 'Y';
					}
					$package['attach'] = $aid.':'.$tablename.':h5:'.$bid;
				}
			}elseif($bset['wxfw_status'] == 2){
				if($business['wxpayst']==1 && $business['wxpay_submchid']){
					$isbusinesspay = true;
					$dbwxpayset = Db::name('sysset')->where('name','wxpayset')->value('value');
					$dbwxpayset = json_decode($dbwxpayset,true);
					if(!$dbwxpayset){
						return ['status'=>0,'msg'=>'未配置服务商微信支付信息'];
					}
					$package['appid'] = $dbwxpayset['appid'];
					$package['mch_id'] = $dbwxpayset['mchid'];
					$package['sub_appid'] = $appid;
					$package['sub_openid'] = $openid;
					$package['sub_mch_id'] = $business['wxpay_submchid'];
					$mchkey = $dbwxpayset['mchkey'];
					
					$feemoney = 0;
					if($business['feepercent'] > 0){
						if(false){}else{
							$feemoney = floatval($business['feepercent']) * 0.01 * $price;
						}
						}

					$chouchengmoney = 0;
					$admindata = Db::name('admin')->where('id',aid)->find();
					if($admindata['chouchengset']==0){ //默认抽成
						if($dbwxpayset && $dbwxpayset['chouchengset']!=0){
							if($dbwxpayset['chouchengset'] == 1){
								//$chouchengmoney = floatval($dbwxpayset['chouchengrate']) * 0.01 * $price;
								$chouchengmoney = floatval($dbwxpayset['chouchengrate']) * 0.01 * $feemoney;
								if($dbwxpayset['chouchengmin'] && $chouchengmoney < floatval($dbwxpayset['chouchengmin'])){
									$chouchengmoney = floatval($dbwxpayset['chouchengmin']);
								}
							}else{
								$chouchengmoney = floatval($dbwxpayset['chouchengmoney']);
							}
						}
					}elseif($admindata['chouchengset']==1){ //按比例抽成
						//$chouchengmoney = floatval($admindata['chouchengrate']) * 0.01 * $price;
						$chouchengmoney = floatval($admindata['chouchengrate']) * 0.01 * $feemoney;
						if($chouchengmoney < floatval($admindata['chouchengmin'])){
							$chouchengmoney = floatval($admindata['chouchengmin']);
						}
					}elseif($admindata['chouchengset']==2){ //按固定金额抽成
						$chouchengmoney = floatval($admindata['chouchengmoney']);
					}
					if($chouchengmoney >= 0.01 && $price*0.3 >= $chouchengmoney){ //需要分账
						$package['profit_sharing'] = 'Y';
					}else{
						$chouchengmoney = 0;
						//if($business['feepercent'] > 0){
						//	$chouchengmoney = floatval($business['feepercent']) * 0.01 * $price;
						//}
						$chouchengmoney = $chouchengmoney + $feemoney;

						if($bset['commission_kouchu'] == 1){
							$commission = self::getcommission($tablename,$ordernum);
						}else{
							$commission = 0;
							}
						$chouchengmoney = $chouchengmoney + $commission;
						if($chouchengmoney > $price*0.3) return ['status'=>0,'msg'=>'分账金额过大'];

						if($chouchengmoney >= 0.01 && $price*0.3 >= $chouchengmoney){ //需要分账
							$package['profit_sharing'] = 'Y';
						}
					}
					$package['attach'] = $aid.':'.$tablename.':mp:'.$bid;
				}
			}
		}

		if(!$isbusinesspay){
			if($appinfo['wxpay_type']==0){//0普通模式
				$package['appid'] = $appid;
				$package['mch_id'] = $appinfo['wxpay_mchid'];
				$mchkey = $appinfo['wxpay_mchkey'];
			}elseif($appinfo['wxpay_type']==1){//1服务商模式
				$dbwxpayset = Db::name('sysset')->where('name','wxpayset')->value('value');
				$dbwxpayset = json_decode($dbwxpayset,true);
				if(!$dbwxpayset){
					return ['status'=>0,'msg'=>'未配置服务商微信支付信息'];
				}
				$package['appid'] = $dbwxpayset['appid'];
				if($appid){
					$package['sub_appid'] = $appid;
				}
				$package['mch_id'] =$dbwxpayset['mchid'];
				$package['sub_mch_id'] = $appinfo['wxpay_sub_mchid'];
				$mchkey = $dbwxpayset['mchkey'];
				
				$chouchengmoney = 0;
				$admindata = Db::name('admin')->where('id',aid)->find();
				if($admindata['chouchengset']==0){ //默认抽成
					if($dbwxpayset && $dbwxpayset['chouchengset']!=0){
						if($dbwxpayset['chouchengset'] == 1){
							$chouchengmoney = floatval($dbwxpayset['chouchengrate']) * 0.01 * $price;
							if($chouchengmoney < floatval($dbwxpayset['chouchengmin'])){
								$chouchengmoney = floatval($dbwxpayset['chouchengmin']);
							}
						}else{
							$chouchengmoney = floatval($dbwxpayset['chouchengmoney']);
						}
					}
				}elseif($admindata['chouchengset']==1){ //按比例抽成
					$chouchengmoney = floatval($admindata['chouchengrate']) * 0.01 * $price;
					if($chouchengmoney < floatval($admindata['chouchengmin'])){
						$chouchengmoney = floatval($admindata['chouchengmin']);
					}
				}elseif($admindata['chouchengset']==2){ //按固定金额抽成
					$chouchengmoney = floatval($admindata['chouchengmoney']);
				}
				if($chouchengmoney >= 0.01 && $price*0.3 >= $chouchengmoney){ //需要分账
					$package['profit_sharing'] = 'Y';
				}
			}elseif($appinfo['wxpay_type']==4){
                }
			$package['attach'] = $aid.':'.$tablename.':h5';
		}
        $package['nonce_str'] = random(8);
		$package['body'] = mb_substr($title,0,42);
		$package['out_trade_no'] = $ordernum;
		$package['total_fee'] = $price * 100;
		$package['spbill_create_ip'] = request()->ip();
		//$package['time_start'] = date('YmdHis', TIMESTAMP);
		//$package['time_expire'] = date('YmdHis', TIMESTAMP + 600);
		$package['notify_url'] = $notify_url;
		$package['trade_type'] = 'MWEB';
		$package['scene_info'] = '{"h5_info": {"type":"Wap","wap_url": "'.PRE_URL.'","wap_name": "'.$set['name'].'"}}';
		//dump($package);
		ksort($package, SORT_STRING);
		$string1 = '';
		foreach ($package as $key => $v) {
			if (empty($v)) {
				continue;
			} 
			$string1 .= "{$key}={$v}&";
		}
		$string1 .= "key=".$mchkey;
		$package['sign'] = strtoupper(md5($string1));
		$dat = array2xml($package);
		//var_dump($string1);
		//var_dump($package);
		//var_dump($dat);
		$response = request_post('https://api.mch.weixin.qq.com/pay/unifiedorder', $dat);
		
		$xml = @simplexml_load_string($response, 'SimpleXMLElement', LIBXML_NOCDATA);
		if (strval($xml->return_code) == 'FAIL') {
			return ['status'=>0,'msg'=>strval($xml->return_msg)];
		} 
		if (strval($xml->result_code) == 'FAIL') {
			return ['status'=>0,'msg'=>strval($xml->err_code_des)];
		}
		//var_dump($xml);
		//var_dump($xml->mweb_url.'');
		//$prepayid = $xml->prepay_id;
		$wOpt = [];
		$wOpt['app_id'] = $set['ttpayid'];
		$wOpt['sign_type'] = 'MD5';
		$wOpt['out_order_no'] = $ordernum;
		$wOpt['merchant_id'] = $set['ttmchid'];
		$wOpt['timestamp'] = time()."";
		$wOpt['product_code'] = 'pay';
		$wOpt['payment_type'] = 'direct';
		$wOpt['total_amount'] = $price * 100;
		$wOpt['trade_type'] = 'H5';
		$wOpt['uid'] = mid;
		$wOpt['version'] = '2.0';
		$wOpt['currency'] = 'CNY';
		$wOpt['subject'] = mb_substr($title,0,42);
		$wOpt['body'] = mb_substr($title,0,42);
		$wOpt['trade_time'] = time()."";
		$wOpt['valid_time'] = '300';
		$wOpt['notify_url'] = $notify_url;
		$wOpt['wx_url'] = strval($xml->mweb_url);
		$wOpt['wx_type'] = 'MWEB';
		//$wOpt['alipay_url'] = '';
		ksort($wOpt, SORT_STRING);
		foreach ($wOpt as $key => $v) {
			$string .= "{$key}={$v}&";
		} 
		$string = rtrim($string,'&');
		$string .= "".$set['ttpaysecret'];
		$wOpt['sign'] = md5($string);
		return ['status'=>1,'data'=>$wOpt];
	}
	//创建微支付参数H5 QQ小程序
	public static function build_qq($aid,$bid,$mid,$title,$ordernum,$price,$tablename,$notify_url=''){
		if(!$notify_url) $notify_url = PRE_URL.'/notify.php';
		$set = Db::name('admin_set')->where('aid',$aid)->find();
		$package = array();

		$appinfo = \app\commons\System::appinfo($aid,'qq');
		$appid = $appinfo['wxpay_appid'];

		$isbusinesspay = false;
		if($bid > 0){
			$bset = Db::name('business_sysset')->where('aid',$aid)->find();
            $business = Db::name('business')->where('id',$bid)->find();
			if($bset['wxfw_status'] == 1){
				if($business['wxpayst']==1 && $business['wxpay_submchid']){
					$isbusinesspay = true;
					$package['appid'] = $bset['wxfw_appid'];
					$package['mch_id'] = $bset['wxfw_mchid'];
					if($appid){
						$package['sub_appid'] = $appid;
					}
					$package['sub_mch_id'] = $business['wxpay_submchid'];
					$mchkey = $bset['wxfw_mchkey'];

					$chouchengmoney = 0;
					if($business['feepercent'] > 0){
						if(false){}else{
							$chouchengmoney = floatval($business['feepercent']) * 0.01 * $price;
						}
						}
					if($chouchengmoney >= 0.01 && $price*0.3 >= $chouchengmoney){ //需要分账
						$package['profit_sharing'] = 'Y';
					}
					$package['attach'] = $aid.':'.$tablename.':qq:'.$bid;
				}
			}elseif($bset['wxfw_status'] == 2){
				if($business['wxpayst']==1 && $business['wxpay_submchid']){
					$isbusinesspay = true;
					$dbwxpayset = Db::name('sysset')->where('name','wxpayset')->value('value');
					$dbwxpayset = json_decode($dbwxpayset,true);
					if(!$dbwxpayset){
						return ['status'=>0,'msg'=>'未配置服务商微信支付信息'];
					}
					$package['appid'] = $dbwxpayset['appid'];
					$package['mch_id'] = $dbwxpayset['mchid'];
					$package['sub_appid'] = $appid;
					$package['sub_openid'] = $openid;
					$package['sub_mch_id'] = $business['wxpay_submchid'];
					$mchkey = $dbwxpayset['mchkey'];
					
					$feemoney = 0;
					if($business['feepercent'] > 0){
						if(false){}else{
							$feemoney = floatval($business['feepercent']) * 0.01 * $price;
						}
						}

					$chouchengmoney = 0;
					$admindata = Db::name('admin')->where('id',aid)->find();
					if($admindata['chouchengset']==0){ //默认抽成
						if($dbwxpayset && $dbwxpayset['chouchengset']!=0){
							if($dbwxpayset['chouchengset'] == 1){
								//$chouchengmoney = floatval($dbwxpayset['chouchengrate']) * 0.01 * $price;
								$chouchengmoney = floatval($dbwxpayset['chouchengrate']) * 0.01 * $feemoney;
								if($dbwxpayset['chouchengmin'] && $chouchengmoney < floatval($dbwxpayset['chouchengmin'])){
									$chouchengmoney = floatval($dbwxpayset['chouchengmin']);
								}
							}else{
								$chouchengmoney = floatval($dbwxpayset['chouchengmoney']);
							}
						}
					}elseif($admindata['chouchengset']==1){ //按比例抽成
						//$chouchengmoney = floatval($admindata['chouchengrate']) * 0.01 * $price;
						$chouchengmoney = floatval($admindata['chouchengrate']) * 0.01 * $feemoney;
						if($chouchengmoney < floatval($admindata['chouchengmin'])){
							$chouchengmoney = floatval($admindata['chouchengmin']);
						}
					}elseif($admindata['chouchengset']==2){ //按固定金额抽成
						$chouchengmoney = floatval($admindata['chouchengmoney']);
					}
					if($chouchengmoney >= 0.01 && $price*0.3 >= $chouchengmoney){ //需要分账
						$package['profit_sharing'] = 'Y';
					}else{
						$chouchengmoney = 0;
						//if($business['feepercent'] > 0){
						//	$chouchengmoney = floatval($business['feepercent']) * 0.01 * $price;
						//}
						$chouchengmoney = $chouchengmoney + $feemoney;

						if($bset['commission_kouchu'] == 1){
							$commission = self::getcommission($tablename,$ordernum);
						}else{
							$commission = 0;
							}
						$chouchengmoney = $chouchengmoney + $commission;
						if($chouchengmoney > $price*0.3) return ['status'=>0,'msg'=>'分账金额过大'];

						if($chouchengmoney >= 0.01 && $price*0.3 >= $chouchengmoney){ //需要分账
							$package['profit_sharing'] = 'Y';
						}
					}
					$package['attach'] = $aid.':'.$tablename.':mp:'.$bid;
				}
			}
		}

		if(!$isbusinesspay){
			if($appinfo['wxpay_type']==0){
				$package['appid'] = $appid;
				$package['mch_id'] = $appinfo['wxpay_mchid'];
				$mchkey = $appinfo['wxpay_mchkey'];
			}else{
				$dbwxpayset = Db::name('sysset')->where('name','wxpayset')->value('value');
				$dbwxpayset = json_decode($dbwxpayset,true);
				if(!$dbwxpayset){
					return ['status'=>0,'msg'=>'未配置服务商微信支付信息'];
				}
				$package['appid'] = $dbwxpayset['appid'];
				if($appid){
					$package['sub_appid'] = $appid;
				}
				$package['mch_id'] =$dbwxpayset['mchid'];
				$package['sub_mch_id'] = $appinfo['wxpay_sub_mchid'];
				$mchkey = $dbwxpayset['mchkey'];
				
				$chouchengmoney = 0;
				$admindata = Db::name('admin')->where('id',aid)->find();
				if($admindata['chouchengset']==0){ //默认抽成
					if($dbwxpayset && $dbwxpayset['chouchengset']!=0){
						if($dbwxpayset['chouchengset'] == 1){
							$chouchengmoney = floatval($dbwxpayset['chouchengrate']) * 0.01 * $price;
							if($chouchengmoney < floatval($dbwxpayset['chouchengmin'])){
								$chouchengmoney = floatval($dbwxpayset['chouchengmin']);
							}
						}else{
							$chouchengmoney = floatval($dbwxpayset['chouchengmoney']);
						}
					}
				}elseif($admindata['chouchengset']==1){ //按比例抽成
					$chouchengmoney = floatval($admindata['chouchengrate']) * 0.01 * $price;
					if($chouchengmoney < floatval($admindata['chouchengmin'])){
						$chouchengmoney = floatval($admindata['chouchengmin']);
					}
				}elseif($admindata['chouchengset']==2){ //按固定金额抽成
					$chouchengmoney = floatval($admindata['chouchengmoney']);
				}
				if($chouchengmoney >= 0.01 && $price*0.3 >= $chouchengmoney){ //需要分账
					$package['profit_sharing'] = 'Y';
				}
			}
			$package['attach'] = $aid.':'.$tablename.':qq';
		}
        $package['nonce_str'] = random(8);
		$package['body'] = mb_substr($title,0,42);
		$package['out_trade_no'] = $ordernum;
		$package['total_fee'] = $price * 100;
		$package['spbill_create_ip'] = request()->ip();
		//$package['time_start'] = date('YmdHis', TIMESTAMP);
		//$package['time_expire'] = date('YmdHis', TIMESTAMP + 600);
		$package['notify_url'] = 'https://api.q.qq.com/wxpay/notify';
		$package['trade_type'] = 'MWEB';
		$package['scene_info'] = '{"h5_info": {"type":"Wap","wap_url": "'.PRE_URL.'","wap_name": "'.$set['name'].'"}}';
		//dump($package);
		ksort($package, SORT_STRING);
		$string1 = '';
		foreach ($package as $key => $v) {
			if (empty($v)) {
				continue;
			} 
			$string1 .= "{$key}={$v}&";
		}
		$string1 .= "key=".$mchkey;
		$package['sign'] = strtoupper(md5($string1));
		$dat = array2xml($package);
		//var_dump($string1);
		//var_dump($package);
		//var_dump($dat);
		$response = request_post('https://api.q.qq.com/wxpay/unifiedorder?appid='.$appinfo['appid'].'&access_token='.\app\commons\Qq::access_token($aid).'&real_notify_url='.urlencode($notify_url), $dat);
		//var_dump($response);
		$xml = @simplexml_load_string($response, 'SimpleXMLElement', LIBXML_NOCDATA);
		if (strval($xml->return_code) == 'FAIL') {
			return ['status'=>0,'msg'=>strval($xml->return_msg)];
		} 
		if (strval($xml->result_code) == 'FAIL') {
			return ['status'=>0,'msg'=>strval($xml->err_code_des)];
		}
		//var_dump($xml);
		//var_dump($xml->mweb_url.'');
		//$prepayid = $xml->prepay_id;
		$wOpt = [];
		$wOpt['wx_url'] = strval($xml->mweb_url);
		$wOpt['referer'] = PRE_URL;
		return ['status'=>1,'data'=>$wOpt];
	}
	public static function build_app($aid,$bid,$mid,$title,$ordernum,$price,$tablename,$notify_url=''){
		if(!$notify_url) $notify_url = PRE_URL.'/notify.php';
		$package = array();
		$appinfo = \app\commons\System::appinfo($aid,'app');
		$appid = $appinfo['appid'];
		
		$isbusinesspay = false;
		if($bid > 0){
			$bset = Db::name('business_sysset')->where('aid',$aid)->find();
            $business = Db::name('business')->where('id',$bid)->find();
			if($bset['wxfw_status'] == 1){
				if($business['wxpayst']==1 && $business['wxpay_submchid']){
					$isbusinesspay = true;
					$package['appid'] = $bset['wxfw_appid'];
					$package['mch_id'] = $bset['wxfw_mchid'];
					if($appid){
						$package['sub_appid'] = $appid;
					}
					$package['sub_mch_id'] = $business['wxpay_submchid'];
					$mchkey = $bset['wxfw_mchkey'];

					$chouchengmoney = 0;
					if($business['feepercent'] > 0){
						if(false){}else{
							$chouchengmoney = floatval($business['feepercent']) * 0.01 * $price;
						}
						}
					if($chouchengmoney >= 0.01 && $price*0.3 >= $chouchengmoney){ //需要分账
						$package['profit_sharing'] = 'Y';
					}
					$package['attach'] = $aid.':'.$tablename.':app:'.$bid;
				}
			}elseif($bset['wxfw_status'] == 2){
				if($business['wxpayst']==1 && $business['wxpay_submchid']){
					$isbusinesspay = true;
					$dbwxpayset = Db::name('sysset')->where('name','wxpayset')->value('value');
					$dbwxpayset = json_decode($dbwxpayset,true);
					if(!$dbwxpayset){
						return ['status'=>0,'msg'=>'未配置服务商微信支付信息'];
					}
					$package['appid'] = $dbwxpayset['appid'];
					$package['mch_id'] = $dbwxpayset['mchid'];
					$package['sub_appid'] = $appid;
					$package['sub_openid'] = $openid;
					$package['sub_mch_id'] = $business['wxpay_submchid'];
					$mchkey = $dbwxpayset['mchkey'];
					
					$feemoney = 0;
					if($business['feepercent'] > 0){
						if(false){}else{
							$feemoney = floatval($business['feepercent']) * 0.01 * $price;
						}
						}

					$chouchengmoney = 0;
					$admindata = Db::name('admin')->where('id',aid)->find();
					if($admindata['chouchengset']==0){ //默认抽成
						if($dbwxpayset && $dbwxpayset['chouchengset']!=0){
							if($dbwxpayset['chouchengset'] == 1){
								//$chouchengmoney = floatval($dbwxpayset['chouchengrate']) * 0.01 * $price;
								$chouchengmoney = floatval($dbwxpayset['chouchengrate']) * 0.01 * $feemoney;
								if($dbwxpayset['chouchengmin'] && $chouchengmoney < floatval($dbwxpayset['chouchengmin'])){
									$chouchengmoney = floatval($dbwxpayset['chouchengmin']);
								}
							}else{
								$chouchengmoney = floatval($dbwxpayset['chouchengmoney']);
							}
						}
					}elseif($admindata['chouchengset']==1){ //按比例抽成
						//$chouchengmoney = floatval($admindata['chouchengrate']) * 0.01 * $price;
						$chouchengmoney = floatval($admindata['chouchengrate']) * 0.01 * $feemoney;
						if($chouchengmoney < floatval($admindata['chouchengmin'])){
							$chouchengmoney = floatval($admindata['chouchengmin']);
						}
					}elseif($admindata['chouchengset']==2){ //按固定金额抽成
						$chouchengmoney = floatval($admindata['chouchengmoney']);
					}
					if($chouchengmoney >= 0.01 && $price*0.3 >= $chouchengmoney){ //需要分账
						$package['profit_sharing'] = 'Y';
					}else{
						$chouchengmoney = 0;
						//if($business['feepercent'] > 0){
						//	$chouchengmoney = floatval($business['feepercent']) * 0.01 * $price;
						//}
						$chouchengmoney = $chouchengmoney + $feemoney;

						if($bset['commission_kouchu'] == 1){
							$commission = self::getcommission($tablename,$ordernum);
						}else{
							$commission = 0;
							}
						$chouchengmoney = $chouchengmoney + $commission;
						if($chouchengmoney > $price*0.3) return ['status'=>0,'msg'=>'分账金额过大'];

						if($chouchengmoney >= 0.01 && $price*0.3 >= $chouchengmoney){ //需要分账
							$package['profit_sharing'] = 'Y';
						}
					}
					$package['attach'] = $aid.':'.$tablename.':mp:'.$bid;
				}
			}
		}
		if(!$isbusinesspay){
           if($appinfo['wxpay_type']==0){
				$package['appid'] = $appid;
				$package['mch_id'] = $appinfo['wxpay_mchid'];
				$mchkey = $appinfo['wxpay_mchkey'];
			}else{
				$dbwxpayset = Db::name('sysset')->where('name','wxpayset')->value('value');
				$dbwxpayset = json_decode($dbwxpayset,true);
				if(!$dbwxpayset){
					return ['status'=>0,'msg'=>'未配置服务商微信支付信息'];
				}
				$package['appid'] = $dbwxpayset['appid'];
				if($appid){
					$package['sub_appid'] = $appid;
				}
				$package['mch_id'] =$dbwxpayset['mchid'];
				$package['sub_mch_id'] = $appinfo['wxpay_sub_mchid'];
				$mchkey = $dbwxpayset['mchkey'];
				
				$chouchengmoney = 0;
				$admindata = Db::name('admin')->where('id',aid)->find();
				if($admindata['chouchengset']==0){ //默认抽成
					if($dbwxpayset && $dbwxpayset['chouchengset']!=0){
						if($dbwxpayset['chouchengset'] == 1){
							$chouchengmoney = floatval($dbwxpayset['chouchengrate']) * 0.01 * $price;
							if($chouchengmoney < floatval($dbwxpayset['chouchengmin'])){
								$chouchengmoney = floatval($dbwxpayset['chouchengmin']);
							}
						}else{
							$chouchengmoney = floatval($dbwxpayset['chouchengmoney']);
						}
					}
				}elseif($admindata['chouchengset']==1){ //按比例抽成
					$chouchengmoney = floatval($admindata['chouchengrate']) * 0.01 * $price;
					if($chouchengmoney < floatval($admindata['chouchengmin'])){
						$chouchengmoney = floatval($admindata['chouchengmin']);
					}
				}elseif($admindata['chouchengset']==2){ //按固定金额抽成
					$chouchengmoney = floatval($admindata['chouchengmoney']);
				}
				if($chouchengmoney >= 0.01 && $price*0.3 >= $chouchengmoney){ //需要分账
					$package['profit_sharing'] = 'Y';
				}
			}
			$package['attach'] = $aid.':'.$tablename.':app';
		}
        $package['nonce_str'] = random(8);
		$package['body'] = mb_substr($title,0,42);
		$package['out_trade_no'] = $ordernum;
		$package['total_fee'] = $price * 100;
		$package['spbill_create_ip'] = request()->ip();
		//$package['time_start'] = date('YmdHis', TIMESTAMP);
		//$package['time_expire'] = date('YmdHis', TIMESTAMP + 600);
		$package['notify_url'] = $notify_url;
		$package['trade_type'] = 'APP';
		//dump($package);
		ksort($package, SORT_STRING);
		$string1 = '';
		foreach ($package as $key => $v) {
			if (empty($v)) {
				continue;
			} 
			$string1 .= "{$key}={$v}&";
		}
		$string1 .= "key=".$mchkey;
		$package['sign'] = strtoupper(md5($string1));
		$dat = array2xml($package);
		//var_dump($string1);
		//var_dump($package);
		//var_dump($dat);
		$response = request_post('https://api.mch.weixin.qq.com/pay/unifiedorder', $dat);
		
		$xml = @simplexml_load_string($response, 'SimpleXMLElement', LIBXML_NOCDATA);
		if (strval($xml->return_code) == 'FAIL') {
			return ['status'=>0,'msg'=>strval($xml->return_msg)];
		} 
		if (strval($xml->result_code) == 'FAIL') {
			return ['status'=>0,'msg'=>strval($xml->err_code_des)];
		}
		//var_dump($xml);
		//var_dump($xml->mweb_url.'');
		$prepayid = strval($xml->prepay_id);
		$wOpt = [];
		$wOpt['appid'] = $appid;
		if($appinfo['wxpay_type']==0){
			$wOpt['partnerid'] = $appinfo['wxpay_mchid'];
		}else{
			$wOpt['partnerid'] = $appinfo['wxpay_sub_mchid'];
		}
		$wOpt['prepayid'] = $prepayid;
		$wOpt['package'] = 'Sign=WXPay';
		$wOpt['noncestr'] = random(8);
		$wOpt['timestamp'] = time()."";
		//$wOpt['signType'] = 'MD5';
		ksort($wOpt, SORT_STRING);
		foreach ($wOpt as $key => $v) {
			$string .= "{$key}={$v}&";
		}
		$string .= "key=".$mchkey;
		$wOpt['sign'] = strtoupper(md5($string));
		return ['status'=>1,'data'=>$wOpt];
	}
	//关闭订单
	public static function closeorder($aid,$ordernum,$platform){
		$appinfo = \app\commons\System::appinfo($aid,$platform);
		$appid = $appinfo['appid'];

		$package = [];
		$package['appid'] = $appid;
		$package['mch_id'] = $appinfo['wxpay_mchid'];
		$mchkey = $appinfo['wxpay_mchkey'];
		$package['out_trade_no'] = $ordernum;
		$package['nonce_str'] = random(8);
		ksort($package, SORT_STRING);
		$string1 = '';
		foreach ($package as $key => $v){
			if (empty($v)) {
				continue;
			} 
			$string1 .= "{$key}={$v}&";
		}
		$string1 .= "key=".$mchkey;
		$package['sign'] = strtoupper(md5($string1));
		$dat = array2xml($package);
		$response = request_post('https://api.mch.weixin.qq.com/pay/closeorder', $dat);
		
		$xml = @simplexml_load_string($response, 'SimpleXMLElement', LIBXML_NOCDATA);
		if (strval($xml->return_code) == 'FAIL') {
			return ['status'=>0,'msg'=>strval($xml->return_msg)];
		} 
		if (strval($xml->result_code) == 'FAIL') {
			return ['status'=>0,'msg'=>strval($xml->err_code_des)];
		}
		return ['status'=>1,'msg'=>'操作成功'];
	}
	//微信退款
	public static function refund($aid,$platform,$ordernum,$totalprice,$refundmoney,$refund_desc='退款',$bid=0,$payorder=[]){
		if(!$refund_desc) $refund_desc = '退款';
        if($platform =='cashdesk' || $platform =='restaurant_cashdesk'){
            $appinfo = Db::name('admin_setapp_'.$platform)->where('aid',$aid)->where('bid',0)->find();
            if($bid > 0){
                $bappinfo = Db::name('admin_setapp_'.$platform)->where('aid',$aid)->where('bid',$bid)->find();
                if($platform =='cashdesk') {
                    $restaurant_sysset = Db::name('business_sysset')->where('aid', $aid)->find();
                }else{
                    $restaurant_sysset = Db::name('restaurant_admin_set')->where('aid',$aid)->find();
                }
                if(!$restaurant_sysset || $restaurant_sysset['business_cashdesk_wxpay_type'] == 0){
                    return ['status'=>0,'msg'=>'微信收款已禁用'];
                }
                //1:服务商
                if($restaurant_sysset['business_cashdesk_wxpay_type'] == 1){
                    $appinfo['sub_mch_id'] = $bappinfo['wxpay_sub_mchid'];
                    $appinfo['wxpay_type'] = 1;
                    $appinfo['wxpay_sub_mchid'] = $bappinfo['wxpay_sub_mchid'];
                }
                //3：独立收款
                if($restaurant_sysset['business_cashdesk_wxpay_type'] == 3){
                    $appinfo['wxpay_type'] = $bappinfo['wxpay_type'];
                    $appinfo['appid'] = $bappinfo['appid'];
                    $appinfo['wxpay_sub_mchid'] = $bappinfo['wxpay_sub_mchid'];
                    if($bappinfo['wxpay_type']==0){
                        $appinfo['mch_id'] = $bappinfo['wxpay_mchid'];
                        $appinfo['wxpay_mchid'] = $bappinfo['wxpay_mchid'];
                        $appinfo['wxpay_mchkey'] = $bappinfo['wxpay_mchkey'];
                        $appinfo['wxpay_apiclient_cert'] = $bappinfo['wxpay_apiclient_cert'];
                        $appinfo['wxpay_apiclient_key'] = $bappinfo['wxpay_apiclient_key'];
                    }else{
                        $appinfo['sub_mch_id'] = $bappinfo['wxpay_sub_mchid'];
                    }
                }
            }
        }else{
            $appinfo = \app\commons\System::appinfo($aid,$platform);
        }
		if($platform == 'qq') $appinfo['appid'] = $appinfo['wxpay_appid'];
		if($appinfo['wxpay_type'] == 2){
			$paylog = Db::name('wxpay_log')->where('aid',$aid)->where('ordernum',$ordernum)->find();
			$url = 'https://api.weixin.qq.com/shop/pay/refundorder?access_token='.\app\commons\Wechat::access_token($aid,'wx');
			$postdata = [];
			$postdata['openid'] = $paylog['openid'];
			$postdata['mchid'] = $appinfo['wxpay_sub_mchid2'];
			$postdata['trade_no'] = $ordernum;
			$postdata['transaction_id'] = $paylog['transaction_id'];
			$postdata['refund_no'] = date('YmdHis').rand(1000,9999);
			$postdata['total_amount'] = $totalprice*100;
			$postdata['refund_amount'] = $refundmoney*100;
			$rs = curl_post($url,jsonEncode($postdata));
			$rs = json_decode($rs,true);
			if($rs['errcode'] == 0){
				//记录
				$data = [];
				$data['aid'] = $aid;
				$data['mch_id'] = $postdata['mchid'];
				$data['ordernum'] = $ordernum;
				$data['out_refund_no'] = $postdata['refund_no'];
				$data['totalprice'] = $totalprice;
				$data['refundmoney'] = $refundmoney;
				$data['createtime'] = date('Y-m-d H:i:s');
				$data['status'] = 1;
				$data['remark'] = $refund_desc;
				Db::name('wxrefund_log')->insert($data);
				if($paylog){
					Db::name('wxpay_log')->where('id',$paylog['id'])->inc('refund_money',$refundmoney)->update();
				}
				return ['status'=>1,'msg'=>'退款成功'];
			}else{
				return ['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs)];
			}
		}

		//是否有分账 分账回退
		$paylog = Db::name('wxpay_log')->where('aid',$aid)->where('ordernum',$ordernum)->find();
		if($paylog && ($paylog['fenzhangmoney'] > 0 || $paylog['fenzhangmoney2'] > 0) && $paylog['isfenzhang'] == 1){
			if($paylog['bid'] == 0){
				$dbwxpayset = Db::name('sysset')->where('name','wxpayset')->value('value');
				$dbwxpayset = json_decode($dbwxpayset,true);
			}else{
				$bset = Db::name('business_sysset')->where('aid',$aid)->find();
				if($bset['wxfw_status']==2){
					$dbwxpayset = Db::name('sysset')->where('name','wxpayset')->value('value');
					$dbwxpayset = json_decode($dbwxpayset,true);
				}else{
					$dbwxpayset = [
						'mchname'=>$bset['wxfw_mchname'],
						'appid'=>$bset['wxfw_appid'],
						'mchid'=>$bset['wxfw_mchid'],
						'mchkey'=>$bset['wxfw_mchkey'],
						'apiclient_cert'=>$bset['wxfw_apiclient_cert'],
						'apiclient_key'=>$bset['wxfw_apiclient_key'],
					];
				}
			}
			$mchkey = $dbwxpayset['mchkey'];
			$sslcert = ROOT_PATH.$dbwxpayset['apiclient_cert'];
			$sslkey = ROOT_PATH.$dbwxpayset['apiclient_key'];
            if(empty($sslcert) || $sslcert == ROOT_PATH || empty($sslkey) || $sslkey == ROOT_PATH){
                return ['status'=>0,'msg'=>'请配置支付证书和密钥'];
            }
			if($paylog['fenzhangmoney'] > 0){
				$pars = array();
				$pars['mch_id'] = $dbwxpayset['mchid'];
				$pars['sub_mch_id'] = $paylog['sub_mchid'];
				$pars['appid'] = $dbwxpayset['appid'];
				$pars['nonce_str'] = random(32);
				$pars['out_order_no'] = $paylog['fz_ordernum'];
				$pars['out_return_no'] = 'R'.date('YmdHis').rand(1000,9999);

				$admin = Db::name('admin')->where('id',$aid)->find();
				if($admin['choucheng_receivertype'] == 0){
					$pars['return_account_type'] = 'MERCHANT_ID';
					$pars['return_account'] = $dbwxpayset['mchid'];
				}elseif($admin['choucheng_receivertype'] == 1){
					$pars['return_account_type'] = 'MERCHANT_ID';
					$pars['return_account'] = $admin['choucheng_receivertype1_account'];
				}elseif($admin['choucheng_receivertype'] == 2){
					if($admin['choucheng_receivertype2_openidtype'] == 0){
						$pars['return_account_type'] = 'PERSONAL_OPENID';
						$pars['return_account'] = $admin['choucheng_receivertype2_account'];
					}else{
						$pars['return_account_type'] = 'PERSONAL_SUB_OPENID';
						$pars['return_account'] = $admin['choucheng_receivertype2_account'];
						if($paylog['platform'] == 'wx'){
							$pars['return_account'] = $admin['choucheng_receivertype2_accountwx'];
						}else{
							$pars['return_account'] = $admin['choucheng_receivertype2_account'];
						}
					}
				}

				//$pars['return_account_type'] = 'MERCHANT_ID';
				//$pars['return_account'] = $dbwxpayset['mchid'];
				$pars['return_amount'] = intval($paylog['fenzhangmoney']*100);
				$pars['description'] = $refund_desc;
				//$pars['sign_type'] = 'MD5';
				ksort($pars, SORT_STRING);
				$string1 = '';
				foreach ($pars as $k => $v) {
					$string1 .= "{$k}={$v}&";
				}
				$string1 .= "key=" . $mchkey;
				//$pars['sign'] = strtoupper(md5($string1));
				$pars['sign'] = strtoupper(hash_hmac("sha256",$string1 ,$mchkey));
				$xml = array2xml($pars);

				$client = new \GuzzleHttp\Client(['timeout'=>30,'verify'=>false]);
				$response = $client->request('POST',"https://api.mch.weixin.qq.com/secapi/pay/profitsharingreturn",['body'=>$xml,'cert'=>$sslcert,'ssl_key'=>$sslkey]);
				$info = $response->getBody()->getContents();

				$resp = (array)(simplexml_load_string($info,'SimpleXMLElement', LIBXML_NOCDATA));
				//var_dump($resp);
				//\think\facade\Log::write($pars);
				//\think\facade\Log::write($resp);
				if($resp['return_code'] == 'SUCCESS' && $resp['result']=='SUCCESS'){
					Db::name('wxpay_log')->where('aid',$aid)->where('ordernum',$ordernum)->update(['isfenzhang'=>3]);
				}
			}
			if($paylog['fenzhangmoney2'] > 0){
				$pars = array();
				$pars['mch_id'] = $dbwxpayset['mchid'];
				$pars['sub_mch_id'] = $paylog['sub_mchid'];
				$pars['appid'] = $dbwxpayset['appid'];
				$pars['nonce_str'] = random(32);
				$pars['out_order_no'] = $paylog['fz_ordernum'];
				$pars['out_return_no'] = 'R'.date('YmdHis').rand(1000,9999);
				$pars['return_account_type'] = 'MERCHANT_ID';
				$pars['return_account'] = $bset['wxfw2_mchid'];
				$pars['return_amount'] = intval($paylog['fenzhangmoney2']*100);
				$pars['description'] = $refund_desc;
				//$pars['sign_type'] = 'MD5';
				ksort($pars, SORT_STRING);
				$string1 = '';
				foreach ($pars as $k => $v) {
					$string1 .= "{$k}={$v}&";
				}
				$string1 .= "key=" . $mchkey;
				//$pars['sign'] = strtoupper(md5($string1));
				$pars['sign'] = strtoupper(hash_hmac("sha256",$string1 ,$mchkey));
				$xml = array2xml($pars);

				$client = new \GuzzleHttp\Client(['timeout'=>30,'verify'=>false]);
				$response = $client->request('POST',"https://api.mch.weixin.qq.com/secapi/pay/profitsharingreturn",['body'=>$xml,'cert'=>$sslcert,'ssl_key'=>$sslkey]);
				$info = $response->getBody()->getContents();

				$resp = (array)(simplexml_load_string($info,'SimpleXMLElement', LIBXML_NOCDATA));
				//\think\facade\Log::write($pars);
				//\think\facade\Log::write($resp);
				if($resp['return_code'] == 'SUCCESS' && $resp['result']=='SUCCESS'){
					Db::name('wxpay_log')->where('aid',$aid)->where('ordernum',$ordernum)->update(['isfenzhang'=>3]);
				}
			}
		}
		$pars = array();
		if($paylog['bid'] > 0){
			$bset = Db::name('business_sysset')->where('aid',$aid)->find();
			if($bset['wxfw_status']==2){
				$dbwxpayset = Db::name('sysset')->where('name','wxpayset')->value('value');
				$dbwxpayset = json_decode($dbwxpayset,true);
			}else{
				$dbwxpayset = [
					'mchname'=>$bset['wxfw_mchname'],
					'appid'=>$bset['wxfw_appid'],
					'mchid'=>$bset['wxfw_mchid'],
					'mchkey'=>$bset['wxfw_mchkey'],
					'apiclient_cert'=>$bset['wxfw_apiclient_cert'],
					'apiclient_key'=>$bset['wxfw_apiclient_key'],
				];
			}
			$pars['appid'] = $dbwxpayset['appid'];
			$pars['sub_appid'] = $appinfo['appid'];
			$pars['mch_id'] = $dbwxpayset['mchid'];
			$pars['sub_mch_id'] = $paylog['sub_mchid'];
			$mchkey = $dbwxpayset['mchkey'];
			$sslcert = ROOT_PATH.$dbwxpayset['apiclient_cert'];
			$sslkey = ROOT_PATH.$dbwxpayset['apiclient_key'];
		}else{
			if($appinfo['wxpay_type']==0){
				$pars['appid'] = $appinfo['appid'];
				$pars['mch_id'] = $appinfo['wxpay_mchid'];
				$mchkey = $appinfo['wxpay_mchkey'];
				$sslcert = ROOT_PATH.$appinfo['wxpay_apiclient_cert'];
				$sslkey = ROOT_PATH.$appinfo['wxpay_apiclient_key'];
				
				/*
				if($appinfo['wxpay_serial_no']){
					if(!$ordernum) $ordernum = date('ymdHis') .$aid. rand(1000, 9999);
					$params = [];
					$params['out_trade_no'] = $ordernum;
					$params['out_refund_no'] = $ordernum. '_' . rand(1000, 9999);
					$params['reason'] = $refund_desc;
					$params['amount'] = [
						'refund'=>$refundmoney * 100,
						'total'=>$totalprice * 100,
						'currency'=>'CNY',
					];
					$rs = self::request_v3('https://api.mch.weixin.qq.com/v3/refund/domestic/refunds','POST',$appinfo['wxpay_mchid'],$appinfo['wxpay_serial_no'],$sslkey,$params);
					return $rs;
				}
				*/
			}elseif($appinfo['wxpay_type']==3){
				$rs = \app\customs\Sxpay::refund($aid,$platform,$ordernum,$totalprice,$refundmoney,$refund_desc,$bid);
				return $rs;
            }elseif($appinfo['wxpay_type']==4){
                }else{
                $dbwxpayset = Db::name('sysset')->where('name','wxpayset')->value('value');
                $dbwxpayset = json_decode($dbwxpayset,true);
			    if($bid > 0 && ($platform =='cashdesk' || $platform =='restaurant_cashdesk')){
                    $bset = Db::name('business_sysset')->where('aid',$aid)->find();
                    if($bset['wxfw_status']==2){
                        $dbwxpayset = Db::name('sysset')->where('name','wxpayset')->value('value');
                        $dbwxpayset = json_decode($dbwxpayset,true);
                    }else{
                        $dbwxpayset = [
                            'mchname'=>$bset['wxfw_mchname'],
                            'appid'=>$bset['wxfw_appid'],
                            'mchid'=>$bset['wxfw_mchid'],
                            'mchkey'=>$bset['wxfw_mchkey'],
                            'apiclient_cert'=>$bset['wxfw_apiclient_cert'],
                            'apiclient_key'=>$bset['wxfw_apiclient_key'],
                        ];
                    }
                }
				$pars['appid'] = $dbwxpayset['appid'];
				$pars['sub_appid'] = $appinfo['appid'];
				$pars['mch_id'] = $dbwxpayset['mchid'];
				$pars['sub_mch_id'] = $appinfo['wxpay_sub_mchid'];
				$mchkey = $dbwxpayset['mchkey'];
				$sslcert = ROOT_PATH.$dbwxpayset['apiclient_cert'];
				$sslkey = ROOT_PATH.$dbwxpayset['apiclient_key'];
			}
		}
        if(empty($sslcert) || $sslcert == ROOT_PATH || empty($sslkey) || $sslkey == ROOT_PATH){
            return ['status'=>0,'msg'=>'请配置支付证书和密钥'];
        }
		$pars['nonce_str'] = random(32);
		$pars['out_trade_no'] = $ordernum;
		$pars['out_refund_no'] = $ordernum. '_' . rand(1000, 9999);
		$pars['total_fee'] = $totalprice * 100;
		$pars['refund_fee'] = $refundmoney * 100;
		$pars['refund_desc'] = $refund_desc;
		ksort($pars, SORT_STRING);
		$string1 = '';
		foreach ($pars as $k => $v) {
			$string1 .= "{$k}={$v}&";
		} 
		$string1 .= "key=" . $mchkey;
		$pars['sign'] = strtoupper(md5($string1));
		$xml = array2xml($pars);
		
		$client = new \GuzzleHttp\Client(['timeout'=>30,'verify'=>false]);
		$response = $client->request('POST',"https://api.mch.weixin.qq.com/secapi/pay/refund",['body'=>$xml,'cert'=>$sslcert,'ssl_key'=>$sslkey]);
		$info = $response->getBody()->getContents();

		$resp = (array)(simplexml_load_string($info,'SimpleXMLElement', LIBXML_NOCDATA));
		if($resp['return_code'] == 'SUCCESS' && $resp['result_code']=='SUCCESS'){
			//记录
			$data = [];
			$data['aid'] = $aid;
			$data['mch_id'] = $pars['mch_id'];
			$data['ordernum'] = $ordernum;
			$data['out_refund_no'] = $pars['out_refund_no'];
			$data['totalprice'] = $totalprice;
			$data['refundmoney'] = $refundmoney;
			$data['createtime'] = date('Y-m-d H:i:s');
			$data['status'] = 1;
			$data['remark'] = $refund_desc;
			Db::name('wxrefund_log')->insert($data);
			if($paylog){
				Db::name('wxpay_log')->where('id',$paylog['id'])->inc('refund_money',$refundmoney)->update();
			}
			return ['status'=>1,'msg'=>'退款成功','resp'=>$resp];
		}else{
			$msg = '未知错误';
			if ($resp['return_code'] == 'FAIL') {
				$msg = $resp['return_msg'];
			} 
			if ($resp['result_code'] == 'FAIL') {
				$msg = $resp['err_code_des'];
			}
			//记录
			$data = [];
			$data['aid'] = $aid;
			$data['mch_id'] = $pars['mch_id'];
			$data['ordernum'] = $ordernum;
			$data['out_refund_no'] = $pars['out_refund_no'];
			$data['totalprice'] = $totalprice;
			$data['refundmoney'] = $refundmoney;
			$data['createtime'] = date('Y-m-d H:i:s');
			$data['status'] = 2;
			$data['remark'] = $refund_desc;
			$data['errmsg'] = $msg;
			Db::name('wxrefund_log')->insert($data);
			return ['status'=>0,'msg'=>$msg,'resp'=>$resp];
		}
	}
	//发红包 https://pay.weixin.qq.com/wiki/doc/api/tools/cash_coupon_xcx.php?chapter=18_4&index=1
	public static function sendredpackage($aid,$mid,$platform,$money,$act_name='微信红包',$send_name='微信红包',$wishing='恭喜发财',$remark='微信红包',$scene_id='',$log=[]){
		if(!$aid || !$mid || !$money) return ['status'=>0,'msg'=>'参数错误'];
		
		if($platform == 'wx'){
			$openid = Db::name('member')->where('id',$mid)->value('wxopenid');
			$url = 'https://api.mch.weixin.qq.com/mmpaymkttransfers/sendminiprogramhb';
			$appinfo = \app\commons\System::appinfo($aid,'wx');
		}else{
			$openid = Db::name('member')->where('id',$mid)->value('mpopenid');
			$url = "https://api.mch.weixin.qq.com/mmpaymkttransfers/sendredpack";
			$appinfo = \app\commons\System::appinfo($aid,'mp');
		}

		$package = array();
		$package['wxappid'] = $appinfo['appid'];
		$package['mch_id'] = $appinfo['wxpay_mchid'];
		$mchkey = $appinfo['wxpay_mchkey'];
		$sslcert = ROOT_PATH.$appinfo['wxpay_apiclient_cert'];
		$sslkey = ROOT_PATH.$appinfo['wxpay_apiclient_key'];
		//dump($sslkey);
		$ordernum = $log['ordernum'] ?? date('ymdHis').$aid.rand(1000, 9999);//单号相同为再次发放
		$package['mch_billno'] = $ordernum;
		$package['send_name'] = $send_name;	//商户名称 红包发送者名称
		$package['re_openid'] = $openid;	//用户openid
		$package['total_amount'] = $money*100;	//付款金额
		$package['total_num'] = 1;	//红包发放总人数
		$package['wishing'] = $wishing;//红包祝福语
		//$package['client_ip'] = '127.0.0.1';
		$package['act_name'] = mb_substr($act_name,0,30);//活动名称
		$package['remark'] = $remark;  //备注信息
		if($scene_id){
			$package['scene_id'] = $scene_id;  //场景id
		}
		if($platform == 'wx'){
			$package['notify_way'] = 'MINI_PROGRAM_JSAPI';
		}
		$nonce_str = '';
		$str = '1234567890abcdefghijklmnopqrstuvwxyz';
		for($i=0;$i<30;$i++){
			$j=rand(0,35);
			$nonce_str .= $str[$j];
		}
		$package['nonce_str'] = $nonce_str;//随机字符串，不长于32位
		ksort($package, SORT_STRING);
		$string1 = '';
		foreach ($package as $key => $v) {
			if (empty($v)) {
				continue;
			} 
			$string1 .= "{$key}={$v}&";
		}
		$string1 .= "key={$mchkey}";
		$package['sign'] = strtoupper(md5($string1));
		$xml = array2xml($package);
		//dump($package);

		$client = new \GuzzleHttp\Client(['timeout'=>30,'verify'=>false]);
		$response = $client->request('POST',$url,['body'=>$xml,'cert'=>$sslcert,'ssl_key'=>$sslkey]);
		$info = $response->getBody()->getContents();

		$resp = (array)(simplexml_load_string($info,'SimpleXMLElement', LIBXML_NOCDATA));
		//dump($resp);die;
		if($resp['return_code'] == 'SUCCESS' && $resp['result_code']=='SUCCESS'){
			//记录
			$data = [];
			$data['aid'] = $aid;
			$data['openid'] = $openid;
			$data['money'] = $money;
			$data['appid'] = $appinfo['appid'];
			$data['mchid'] = $appinfo['wxpay_mchid'];
			$data['ordernum'] = $ordernum;
			$data['createtime'] = date('Y-m-d H:i:s');
			$data['status'] = 1;
			$data['remark'] = '发送成功';
            $data['platform'] = $platform;
			Db::name('sendredpack_log')->insert($data);
			return ['status'=>1,'msg'=>'发送成功','resp'=>$resp];
		}else{
			$msg = '未知错误';
			if ($resp['return_code'] == 'FAIL') {
				$msg = $resp['return_msg'];
			} 
			if ($resp['result_code'] == 'FAIL') {
				$msg = $resp['err_code_des'];
			}
			//记录
			$data = [];
			$data['aid'] = $aid;
			$data['openid'] = $openid;
			$data['money'] = $money;
			$data['appid'] = $appinfo['appid'];
			$data['mchid'] = $appinfo['wxpay_mchid'];
			$data['ordernum'] = $ordernum;
			$data['createtime'] = date('Y-m-d H:i:s');
			$data['status'] = 2;
			$data['remark'] = $msg;
            $data['platform'] = $platform;
			Db::name('sendredpack_log')->insert($data);
			return ['status'=>0,'msg'=>$msg,'resp'=>$resp];
		}
	}
    //查询红包信息 https://pay.weixin.qq.com/wiki/doc/api/tools/cash_coupon_xcx.php?chapter=18_6&index=5
    public static function gethbinfo($log){
        $url = 'https://api.mch.weixin.qq.com/mmpaymkttransfers/gethbinfo';
        if($log['platform']){
            $platform = $log['platform'];
        }else{
            $admin_setapp_wx = Db::name('admin_setapp_wx')->where('aid',$log['aid'])->where('wxpay_mchid',$log['mchid'])->find();
            if($admin_setapp_wx){
                $platform = 'wx';
            }else{
                $platform = 'mp';
            }
        }

        $appinfo = \app\commons\System::appinfo($log['aid'],$platform);
        $mchkey = $appinfo['wxpay_mchkey'];
        $sslcert = ROOT_PATH.$appinfo['wxpay_apiclient_cert'];
        $sslkey = ROOT_PATH.$appinfo['wxpay_apiclient_key'];

        $package = array();
        $package['appid'] = $log['appid'];
        $package['mch_id'] = $log['mchid'];
        $package['mch_billno'] = $log['ordernum'];
        $package['bill_type'] = 'MCHT';
        $nonce_str = '';
        $str = '1234567890abcdefghijklmnopqrstuvwxyz';
        for($i=0;$i<30;$i++){
            $j=rand(0,35);
            $nonce_str .= $str[$j];
        }
        $package['nonce_str'] = $nonce_str;//随机字符串，不长于32位
        ksort($package, SORT_STRING);
        $string1 = '';
        foreach ($package as $key => $v) {
            if (empty($v)) {
                continue;
            }
            $string1 .= "{$key}={$v}&";
        }
        $string1 .= "key={$mchkey}";
        $package['sign'] = strtoupper(md5($string1));
        $xml = array2xml($package);
        //dump($package);

        $client = new \GuzzleHttp\Client(['timeout'=>30,'verify'=>false]);
        $response = $client->request('POST',$url,['body'=>$xml,'cert'=>$sslcert,'ssl_key'=>$sslkey]);
        $info = $response->getBody()->getContents();

        $resp = (array)(simplexml_load_string($info,'SimpleXMLElement', LIBXML_NOCDATA));
        return $resp;
    }

	//企业付款到零钱，商家转账到零钱需要证书
	public static function transfers($aid,$mid,$money,$ordernum='',$platform='wx',$desc='打款'){
//		$set = Db::name('admin_set')->where('aid',$aid)->find();
		if(!$platform){
			$openid = Db::name('member')->where('id',$mid)->value('mpopenid');
			if(!$openid){
				$platform = 'wx';
			}else{
				$platform = 'mp';
			}
		}
		if($platform == 'wx'){ //小程序
			$openid = Db::name('member')->where('id',$mid)->value('wxopenid');
			$appinfo = \app\commons\System::appinfo($aid,'wx');
		}else{ //公众号网页
			$openid = Db::name('member')->where('id',$mid)->value('mpopenid');
			$appinfo = \app\commons\System::appinfo($aid,'mp');
		}
		if(!$openid) return ['status'=>0,'msg'=>'未查找到'.t('会员').'openid'];
        if(!$ordernum) $ordernum = date('ymdHis') .$aid. rand(1000, 9999);
		
		$sslcert = ROOT_PATH.$appinfo['wxpay_apiclient_cert'];//PEM证书
		$sslkey = ROOT_PATH.$appinfo['wxpay_apiclient_key'];//证书密钥

		if($appinfo['wxpay_serial_no']){
            //证书序列号 商家转账到零钱 介绍https://pay.weixin.qq.com/docs/merchant/products/batch-transfer-to-balance/introduction.html,api文档 https://pay.weixin.qq.com/docs/merchant/apis/batch-transfer-to-balance/transfer-batch/initiate-batch-transfer.html
            if(empty($sslkey)) return ['status'=>0,'msg'=>'请在微信支付设置中设置证书密钥'];
            if(empty($appinfo['wxpay_serial_no'])) return ['status'=>0,'msg'=>'请在微信支付设置中设置证书序列号'];
			$params = [];
			$params['appid'] = $appinfo['appid'];
			$params['out_batch_no'] = (string) $ordernum;
			$params['batch_name'] = $desc;
			$params['batch_remark'] = $desc;
			$params['total_amount'] = intval($money * 100);
			$params['total_num'] = 1;
			$params['transfer_detail_list'] = [[
				'out_detail_no'=>$ordernum,
				'transfer_amount'=>intval($money * 100),
				'transfer_remark'=>$desc,
				'openid'=>$openid,
			]];
            Log::write([
                'file'=>__FILE__.__LINE__,
                'params'=>jsonEncode($params)
            ]);
			$rs = self::request_v3('https://api.mch.weixin.qq.com/v3/transfer/batches','POST',$appinfo['wxpay_mchid'],$appinfo['wxpay_serial_no'],$sslkey,$params);
			//$rs = self::request_v3('https://api.mch.weixin.qq.com/v3/certificates','GET',$appinfo['wxpay_mchid'],$appinfo['wxpay_serial_no'],$sslkey);
            Log::write([
                'rs'=>jsonEncode($rs)
            ]);
            //todo 存在二次审核情况 需要优化并处理回调
            if($rs['status'] == 1){
				$rs['msg'] = '打款成功';
			}
			return $rs;
		}


		$pars = array();
		$pars['mch_appid'] = $appinfo['appid'];
		$pars['mchid'] = $appinfo['wxpay_mchid'];
		$pars['nonce_str'] = random(32);
		$pars['partner_trade_no'] = $ordernum;

		$pars['openid'] = $openid;
		$pars['check_name'] = 'NO_CHECK';
		$pars['amount'] = intval($money * 100);
		$pars['desc'] = $desc;
		$pars['spbill_create_ip'] = $_SERVER["REMOTE_ADDR"];
		ksort($pars, SORT_STRING);
		$string1 = '';
		foreach ($pars as $k => $v) {
			$string1 .= "{$k}={$v}&";
		} 
		$string1 .= "key=" . $appinfo['wxpay_mchkey'];
		$pars['sign'] = strtoupper(md5($string1));
		$xml = array2xml($pars);

		$client = new \GuzzleHttp\Client(['timeout'=>30,'verify'=>false]);
		$response = $client->request('POST','https://api.mch.weixin.qq.com/mmpaymkttransfers/promotion/transfers',['body'=>$xml,'cert'=>$sslcert,'ssl_key'=>$sslkey]);
		$info = $response->getBody()->getContents();

		$resp = (array)(simplexml_load_string($info,'SimpleXMLElement', LIBXML_NOCDATA));

		if($resp['return_code'] == 'SUCCESS' && $resp['result_code']=='SUCCESS'){
			return ['status'=>1,'msg'=>'打款成功','resp'=>$resp];
		}else{
            Log::write([
                'file'=>__FILE__.__LINE__,
                'transfers'=>$pars,
                'resp' => $resp
            ]);
			$msg = '未知错误';
			if ($resp['return_code'] == 'FAIL') {
				$msg = $resp['return_msg'];
			} 
			if ($resp['result_code'] == 'FAIL') {
				$msg = $resp['err_code_des'];
			}
			return ['status'=>0,'msg'=>$msg,'resp'=>$resp];
		}
	}
	//v3接口请求
	public static function request_v3($url,$method,$mchid,$serial_no,$sslkey,$params=[]){
        if(empty($sslkey)) return ['status'=>0,'msg'=>'请在微信支付设置中设置证书密钥'];
        if(empty($serial_no)) return ['status'=>0,'msg'=>'请在微信支付设置中设置证书序列号'];
		if($method == 'POST'){
			$body = jsonEncode($params);
		}else{
			$body = '';
		}
		$timestamp = time();
		$nonce = random(16);
		$url_parts = parse_url($url);
		$canonical_url = ($url_parts['path'] . (!empty($url_parts['query']) ? "?${url_parts['query']}" : ""));
		$message = $method."\n".
			$canonical_url."\n".
			$timestamp."\n".
			$nonce."\n".
			$body."\n";
		$mch_private_key = file_get_contents($sslkey);
		//var_dump($message);
		//var_dump($mch_private_key);
		openssl_sign($message, $raw_sign, $mch_private_key, 'sha256WithRSAEncryption');
		$sign = base64_encode($raw_sign);
		$schema = 'WECHATPAY2-SHA256-RSA2048';
		$token = sprintf('mchid="%s",nonce_str="%s",timestamp="%d",serial_no="%s",signature="%s"',$mchid, $nonce, $timestamp, $serial_no, $sign);
		$headers = [];
		$headers[] = "Content-Type: application/json";
		$headers[] = "Accept: application/json";
		$headers[] = "Authorization: {$schema} {$token}";
		//$headers[] = "Wechatpay-Serial: ";

		$ch = curl_init ();
		curl_setopt ( $ch, CURLOPT_URL, $url);
		curl_setopt ( $ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt ( $ch, CURLOPT_SSL_VERIFYPEER, FALSE );
		curl_setopt ( $ch, CURLOPT_AUTOREFERER, 1 );
		if($method == 'POST'){
			curl_setopt ( $ch, CURLOPT_CUSTOMREQUEST, "POST" );
			curl_setopt ( $ch, CURLOPT_POSTFIELDS, $body);
		}
		curl_setopt ( $ch, CURLOPT_USERAGENT,$_SERVER['HTTP_USER_AGENT']);
		curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, true );
		$info = curl_exec ( $ch );
		if (curl_errno ( $ch )) {
			return ['status'=>0,'msg'=>curl_error($ch)];
		}
		curl_close ( $ch );
		$resp = json_decode($info,true);
		//var_dump($url);
		//var_dump($headers);
		if(!$resp){
            Log::write([
                'file'=>__FILE__.__LINE__,
                'params'=>$params,
                'resp' => $info
            ]);
			return ['status'=>0,'msg'=>'未知错误','info'=>$info];
		}elseif($resp['code']){
            Log::write([
                'file'=>__FILE__.__LINE__,
                'params'=>$params,
                'resp' => $info
            ]);
			return ['status'=>0,'msg'=>$resp['message'],'resp'=>$resp,'params'=>$params];
		}else{
			return ['status'=>1,'msg'=>'请求成功','resp'=>$resp];
		}
	}
	//获取订单的佣金
	public static function getcommission($type,$ordernum){
		$payorder = Db::name('payorder')->where('type',$type)->where('ordernum',$ordernum)->find();
		\think\facade\Log::write($payorder);
		$totalcommission = Db::name('member_commission_record')->where('type',$type)->where('orderid',$payorder['orderid'])->sum('commission');
		\think\facade\Log::write($totalcommission);
		if(!$totalcommission || $totalcommission <=0) $totalcommission = 0;
		return $totalcommission;
	}

	//单独获取拼团团中团订单的佣金
	public static function getcommission_teaminteam($type,$ordernum){
		}
}