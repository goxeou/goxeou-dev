<?php


namespace app\commons;
use think\facade\Db;
class Qmpay
{
    //银联商务  全民付
	public static $url = 'https://api-mop.chinaums.com/v1/netpay/trade/h5-pay'; //支付宝
	public static $url2 = 'https://api-mop.chinaums.com/v1/netpay/uac/order'; //银联
	public static $url3 = 'https://test-api-open.chinaums.com/v1/netpay/wxpay/h5-pay'; //微信
	//创建微支付 微信公众号
	public static function build_mp($aid,$mid,$title,$ordernum,$price,$tablename,$notify_url='',$frontUrl=''){
		
	}
	//创建微支付 微信小程序
	public static function build_wx($aid,$mid,$title,$ordernum,$price,$tablename,$notify_url='',$frontUrl=''){
		
	}
	//创建微支付参数H5
	public static function build_h5($aid,$mid,$title,$ordernum,$price,$tablename,$notify_url=''){
		
		$config = include(ROOT_PATH.'config.php');
		$config = $config['qmpay'];

		if(!$notify_url) $notify_url = PRE_URL.'/notify.php';
		$member = Db::name('member')->where('id',$mid)->find();

		$package = array();
		$package['authorization'] = 'OPEN-FORM-PARAM';
		$package['appId'] = $config['appId'];
		$package['timestamp'] = date('YmdHis');
		$package['nonce'] = random(10);

		$content = [];
		$content['msgId'] = 'Qmpay';
		$content['requestTimestamp'] = date('Y-m-d H:i:s');
		$content['merOrderId'] = '11UM'.$ordernum;
		$content['mid'] = $config['mid'];
		$content['tid'] = $config['tid'];
		$content['instMid'] = 'H5DEFAULT';
		$content['attachedData'] = $aid.':'.$tablename.':h5:qmpay';
		$content['totalAmount'] = intval($price*100);
		$content['notifyUrl'] = $notify_url;

		$package['content'] = json_encode($content);
		$package['signature'] = base64_encode(hash_hmac(
			"sha256",
			$package['appId'].$package['timestamp'].$package['nonce'].bin2hex(hash('sha256', $package['content'], true)),
			$config['appKey'],
			true
		));

		$string1 = http_build_query($package);

		$url = self::$url.'?'.$string1;
		//\think\facade\Log::write($url);
		return ['status'=>1,'url'=>$url];
	}

	//  http://localhost:8080/#/pagesExt/pay/pay?id=1600
	public static function build_h5_2($aid,$mid,$title,$ordernum,$price,$tablename,$notify_url=''){
		
		$config = include(ROOT_PATH.'config.php');
		$config = $config['qmpay'];

		if(!$notify_url) $notify_url = PRE_URL.'/notify.php';
		$member = Db::name('member')->where('id',$mid)->find();

		$package = array();
		$package['authorization'] = 'OPEN-FORM-PARAM';
		$package['appId'] = $config['appId'];
		$package['timestamp'] = date('YmdHis');
		$package['nonce'] = random(10);

		$content = [];
		$content['msgId'] = 'Qmpay';
		$content['requestTimestamp'] = date('Y-m-d H:i:s');
		$content['merOrderId'] = '11UM'.$ordernum;
		$content['mid'] = $config['mid2'];
		$content['tid'] = $config['tid2'];
		$content['instMid'] = 'H5DEFAULT';
		$content['attachedData'] = $aid.':'.$tablename.':h5:qmpay';
		$content['totalAmount'] = intval($price*100);
		$content['notifyUrl'] = $notify_url;

		$package['content'] = json_encode($content);
		$package['signature'] = base64_encode(hash_hmac(
			"sha256",
			$package['appId'].$package['timestamp'].$package['nonce'].bin2hex(hash('sha256', $package['content'], true)),
			$config['appKey'],
			true
		));

		$string1 = http_build_query($package);

		$url = self::$url2.'?'.$string1;
		//\think\facade\Log::write($url);
		return ['status'=>1,'url'=>$url];
	}

	
	//创建微支付参数H5
	public static function build_h5_3($aid,$mid,$title,$ordernum,$price,$tablename,$notify_url=''){
		
		$config = include(ROOT_PATH.'config.php');
		$config = $config['qmpay'];

		if(!$notify_url) $notify_url = PRE_URL.'/notify.php';
		$member = Db::name('member')->where('id',$mid)->find();

		$package = array();
		$package['authorization'] = 'OPEN-FORM-PARAM';
		$package['appId'] = $config['appId'];
		$package['timestamp'] = date('YmdHis');
		$package['nonce'] = random(10);

		$content = [];
		$content['msgId'] = 'Qmpay';
		$content['requestTimestamp'] = date('Y-m-d H:i:s');
		$content['merOrderId'] = '11UM'.$ordernum;
		$content['mid'] = $config['mid'];
		$content['tid'] = $config['tid'];
		$content['instMid'] = 'H5DEFAULT';
		$content['attachedData'] = $aid.':'.$tablename.':h5:qmpay';
		$content['totalAmount'] = intval($price*100);
		$content['notifyUrl'] = $notify_url;

		$package['content'] = json_encode($content);
		$package['signature'] = base64_encode(hash_hmac(
			"sha256",
			$package['appId'].$package['timestamp'].$package['nonce'].bin2hex(hash('sha256', $package['content'], true)),
			$config['appKey'],
			true
		));

		$string1 = http_build_query($package);

		$url = self::$url.'trade/h5-pay?'.$string1;
		//\think\facade\Log::write($url);
		return ['status'=>1,'url'=>$url];
	}
	
	public static function build_app($aid,$mid,$title,$ordernum,$price,$tablename,$notify_url=''){
		
	}
	public static function getOpenBodySig($originalOrderId){
		$config = include(ROOT_PATH.'config.php');
		$config = $config['qmpay'];

		$appid = $config['appId'];
		$appkey = $config['appKey'];
		$timestamp = date("YmdHis",time());

		$nonce = md5(uniqid(microtime(true),true));
		//$nonce = 'afc240735306357cda63d05cdccafbbb';

		$merchantCode = $config['mid'];
		$terminalCode = $config['tid'];
		
		$body = json_encode(array('merchantCode'=>$merchantCode,'terminalCode'=>$terminalCode,'originalOrderId'=>$originalOrderId));
		//echo $body;
		$str = bin2hex(hash('sha256', $body, true));
		$signature = base64_encode(hash_hmac('sha256', "$appid$timestamp$nonce$str", $appkey, true));

		//echo "$str $str1 $signature";
		$authorization = "OPEN-BODY-SIG AppId=$appid, Timestamp=$timestamp, Nonce=$nonce, Signature=$signature";
		//echo $authorization;
		return $authorization;
	}
	//退款
	public static function refund($aid,$platform,$ordernum,$totalprice,$refundmoney,$refund_desc='退款'){
		if(!$refund_desc) $refund_desc = '退款';
		$appinfo = \app\commons\System::appinfo($aid,$platform);
		
		$url = 'https://api-mop.chinaums.com/v1/netpay/refund';
		$config = include(ROOT_PATH.'config.php');
		$config = $config['qmpay'];
		$appid = $config['appId'];
		$appkey = $config['appKey'];
		$timestamp = date("YmdHis");
		$nonce = md5(uniqid(microtime(true),true));

		$package = array();
		$package['requestTimestamp'] = date('Y-m-d H:i:s');
		$package['merOrderId'] = '11UM'.$ordernum;
		$package['instMid'] = 'H5DEFAULT';
		$package['mid'] = $config['mid'];
		$package['tid'] = $config['tid'];
		$package['refundAmount'] = intval($refundmoney*100);
		$package['refundOrderId'] = '11UM'.$ordernum. '_' . rand(1000, 9999);

		$str = bin2hex(hash('sha256', json_encode($package), true));
		$signature = base64_encode(hash_hmac('sha256', "$appid$timestamp$nonce$str", $appkey, true));

		$authorization = "OPEN-BODY-SIG AppId=\"$appid\", Timestamp=\"$timestamp\", Nonce=\"$nonce\", Signature=\"$signature\"";
		
		$headers = [];
		$headers[] = 'AUTHORIZATION:'.$authorization;
		$res = curl_post($url,json_encode($package),0,$headers);

		$resp = json_decode($res,true);

		//var_dump($resp);
		if($resp['errCode'] == 'SUCCESS'){
			//记录
			$data = [];
			$data['aid'] = $aid;
			$data['mch_id'] = $config['mid'];
			$data['ordernum'] = $ordernum;
			$data['out_refund_no'] = $package['orderNum'];
			$data['totalprice'] = $totalprice;
			$data['refundmoney'] = $refundmoney;
			$data['createtime'] = date('Y-m-d H:i:s');
			$data['status'] = 1;
			$data['remark'] = $refund_desc;
			Db::name('wxrefund_log')->insert($data);
			return ['status'=>1,'msg'=>'退款成功','resp'=>$resp];
		}else{
			$msg = $resp['errInfo'];
			//记录
			$data = [];
			$data['aid'] = $aid;
			$data['mch_id'] = $config['mid'];
			$data['ordernum'] = $ordernum;
			$data['out_refund_no'] = $package['orderNum'];
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
}