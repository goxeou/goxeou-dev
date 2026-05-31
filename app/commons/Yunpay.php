<?php


namespace app\commons;
use think\facade\Db;
class Yunpay
{
    //云收银
	public static $url = 'https://showmoney.cn/scanpay/v01/h5/92721888';
	//创建微支付 微信公众号
	public static function build_mp($aid,$mid,$title,$ordernum,$price,$tablename,$notify_url='',$frontUrl=''){
		if(!$notify_url) $notify_url = PRE_URL.'/notify.php';
		$member = Db::name('member')->where('id',$mid)->find();

		$appinfo = \app\commons\System::appinfo($aid,'mp');
		$appid = $appinfo['appid'];
		$openid = $member[platform.'openid'];

		$mchkey = $appinfo['yun_mchkey'];
		
		$package = array();
		$package['version'] = '3.0';
		$package['signType'] = 'SHA256';
		$package['charset'] = 'utf-8';
		$package['orderNum'] = $ordernum;
		$package['busicd'] = 'WPAY';
		$package['chcd'] = 'WXP';
		$package['mchntid'] = $appinfo['yun_mchntid']; //商户号
		$package['terminalid'] = $appinfo['yun_terminalid']; //终端号
		$package['txamt'] = sprintf("%012d",$price*100); //订单金额
		$package['frontUrl'] = $frontUrl;
		$package['backUrl'] = $notify_url;
		$package['attach'] = $aid.':'.$tablename.':mp:yunpay';
		ksort($package, SORT_STRING);
		$string1 = '';
		foreach ($package as $key => $v) {
			if (empty($v)) {
				continue;
			} 
			$string1 .= "{$key}={$v}&";
		}
		$string1 = trim($string1,'&');
		$string1 .= $mchkey;
		$package['sign'] = hash("sha256",$string1);
		//dump($package);die;
		$dat = base64_encode(json_encode($package));
		header("Location:".self::$url.'?data='.$dat);
	}
	//创建微支付 微信小程序
	public static function build_wx($aid,$mid,$title,$ordernum,$price,$tablename,$notify_url='',$frontUrl=''){
		
	}
	//创建微支付参数H5
	public static function build_h5($aid,$mid,$title,$ordernum,$price,$tablename,$notify_url=''){
		
	}
	
	public static function build_app($aid,$mid,$title,$ordernum,$price,$tablename,$notify_url=''){
		
	}
	//退款
	public static function refund($aid,$platform,$ordernum,$totalprice,$refundmoney,$refund_desc='退款'){
		if(!$refund_desc) $refund_desc = '退款';
		$appinfo = \app\commons\System::appinfo($aid,$platform);
		
		$mchkey = $appinfo['yun_mchkey'];
		
		$package = array();
		$package['version'] = '2.5.4';
		$package['signType'] = 'SHA256';
		$package['charset'] = 'utf-8';
		$package['origOrderNum'] = $ordernum;
		$package['orderNum'] = $ordernum. '_' . rand(1000, 9999);
		$package['txndir'] = 'Q';
		$package['busicd'] = 'REFD';
		$package['inscd'] = 'REFD';
		$package['mchntid'] = $appinfo['yun_mchntid']; //商户号
		$package['terminalid'] = $appinfo['yun_terminalid']; //终端号
		$package['txamt'] = sprintf("%012d",$refundmoney*100); //订单金额
		ksort($package, SORT_STRING);
		$string1 = '';
		foreach ($package as $key => $v) {
			if (empty($v)) {
				continue;
			} 
			$string1 .= "{$key}={$v}&";
		}
		$string1 = trim($string1,'&');
		$string1 .= $mchkey;
		$package['sign'] = hash("sha256",$string1);
		
		$resp = curl_post(self::$url,jsonEncode($package));
		$resp = json_decode($resp,true);
		//var_dump($resp);die;
        $mchid = $appinfo['yun_mchntid']; //商户号
		if($resp['respcd'] == '00'){ // || $resp['respcd'] == '09'
			//记录
			$data = [];
			$data['aid'] = $aid;
			$data['mch_id'] = $mchid;
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
			$msg = $resp['errorDetail'];
			//记录
			$data = [];
			$data['aid'] = $aid;
			$data['mch_id'] = $mchid;
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