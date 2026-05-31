<?php


// +----------------------------------------------------------------------
// | 微支付通知
// +----------------------------------------------------------------------
namespace app\commons;
use think\facade\Db;
use think\facade\Log;
class Notify
{
	public $member;
	public $givescore=0;
	public function index(){
	    if($_POST['signType']=='01'){
			$this->sdbpay();die;
		}
		if($_POST['taskId']){
			$this->kuaidi100();die;
		}
		if($_POST['passback_params'] && $_POST['trade_status']){
			$this->alipay();die;
		}
		if($_POST['returnData'] && $_POST['dealId']){
			$this->baidupay();die;
		}
				
		if($_SERVER['QUERY_STRING'] && (strpos($_SERVER['QUERY_STRING'],'%app_id') > 0 || strpos($_SERVER['QUERY_STRING'],'settle_app_id') > 0)){ //云收银
			$this->hemapay();die;
		}
		
//		Log::write([
//            'file'=>__FILE__.__LINE__,
//            $_SERVER['QUERY_STRING']
//        ]);
		if($_SERVER['QUERY_STRING'] && (strpos($_SERVER['QUERY_STRING'],'%3Ayunpay') > 0 || strpos($_SERVER['QUERY_STRING'],'usicd%3DWXMP') > 0)){ //云收银
			$this->yunpay();die;
		}
		$xml = file_get_contents('php://input');
//		Log::write($xml);
		if($xml && strpos($xml,'%3Aqmpay') > 0){
			$this->qmpay();die;
		}
		if($xml && (strpos($xml,':sxpaymp:') > 0 || strpos($xml,':sxpaywx:') > 0 || strpos($xml,':sxpayalipay:') > 0 || strpos($xml,':sxalih5:') > 0 )){
			$this->sxpay();die;
		}
		if($xml && (strpos($xml,'"applicationId":') > 0 && strpos($xml,'"taskType":') > 0)){
			$this->sxaudit();die;
		}
		if($xml && (strpos($xml,':fbpaymp:') > 0 || strpos($xml,':fbpaywx:') > 0 || strpos($xml,':fbpayali:') > 0)){
			$this->fbpay();die;
		}
        if($xml && (strpos($xml,'resp_desc=') == 0 && strpos($xml,'resp_code=') > 0 && strpos($xml,'sign=') > 0 && strpos($xml,'resp_data=') > 0)){
            $this->huifupay();die;
        }

		$ttpost = json_decode($xml,true);
		if($ttpost && $ttpost['msg_signature'] && $ttpost['type']=='payment'){
			$this->ttpay($ttpost);die;
		}
		
        if($xml && strpos($xml,'prod_mode') > 0 && strpos($xml,'type') > 0 && strpos($xml,'object') && strpos($xml,'data') > 0){
            $this->adapay();die;
        }
		
		if(!$xml) die('fail');
		libxml_disable_entity_loader(true);
		$msg = (array)simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
		if (empty($msg)) {
			exit('fail');
		} 
		if ($msg['result_code'] != 'SUCCESS' || $msg['return_code'] != 'SUCCESS') {
			exit('fail');
		}
		//Log::write($msg);
		$attach = explode(':',$msg['attach']);
		$aid = intval($attach[0]);
		define('aid',$aid);
		$tablename = $attach[1];
		$platform = $attach[2];
		$appinfo = \app\commons\System::appinfo($aid,$platform);
		if (!empty($appinfo)) {
			ksort($msg);
			$string1 = '';
			foreach ($msg as $k => $v) {
				if ($v != '' && $k != 'sign') {
					$string1 .= "{$k}={$v}&";
				}
			}
            //0普通模式，1服务商模式，2二级商户模式，3随行付
			if($appinfo['wxpay_type'] == 1){
				$dbwxpayset = Db::name('sysset')->where('name','wxpayset')->value('value');
				$dbwxpayset = json_decode($dbwxpayset,true);
				$mchkey = $dbwxpayset['mchkey'];
			}else{
				$mchkey = $appinfo['wxpay_mchkey'];
			}
			if($attach[3]){
				$bid = $attach[3];
				$bset = Db::name('business_sysset')->where('aid',$aid)->find();
				if($bset['wxfw_status'] == 1){
					$mchkey = $bset['wxfw_mchkey'];
				}elseif($bset['wxfw_status'] == 2){//使用平台服务商
					$dbwxpayset = Db::name('sysset')->where('name','wxpayset')->value('value');
					$dbwxpayset = json_decode($dbwxpayset,true);
					$mchkey = $dbwxpayset['mchkey'];
				}
			}else{
				$bid = 0;
			}
			$sign = strtoupper(md5($string1 . "key={$mchkey}"));
			if($sign == $msg['sign']){
				if($bid){
					Db::name('payorder')->where(['aid'=>$aid,'type'=>$tablename,'ordernum'=>$msg['out_trade_no']])->update(['isbusinesspay'=>1]);
				}
                $payorder = Db::name('payorder')->where(['aid'=>$aid,'type'=>$tablename,'ordernum'=>$msg['out_trade_no']])->find();
                $paymoney = $msg['total_fee']*0.01;
                //记录
                $data = array();
                $data['aid'] = aid;
                $data['mid'] = $payorder['mid'];
                $data['openid'] = $msg['openid'];
                $data['tablename'] = $tablename;
                $data['givescore'] = $this->givescore;
                $data['ordernum'] = $msg['out_trade_no'];
                $data['mch_id'] = $msg['mch_id'];
                $data['transaction_id'] = $msg['transaction_id'];
                $data['total_fee'] = $paymoney;
                $data['createtime'] = time();
//                $data['fenzhangmoney'] = $chouchengmoney;
//                $data['fenzhangmoney2'] = $chouchengmoney2;
//                $data['sub_mchid'] = $sub_mchid;
                $data['platform'] = $platform;
                $data['bid'] = $bid;
                $paylogid = Db::name('wxpay_log')->insertGetId($data);

				$rs = $this->setorder($tablename,$msg['out_trade_no'],$msg['transaction_id'],$msg['total_fee'],'微信支付',2);
				if($rs['status'] == 1){
					$chouchengmoney = 0;
					$chouchengmoney2 = 0;
                    //多商户订单
					if($bid){
                        $business = Db::name('business')->where('id',$bid)->find();
                        //使用平台服务商
						if($bset['wxfw_status'] == 2){
							$paymoney = $msg['total_fee']*0.01;
							$feemoney = 0;
							if($business['feepercent'] > 0){
								if(false){}else{
									$feemoney = floatval($business['feepercent']) * 0.01 * $paymoney;
								}
								}

							$admindata = Db::name('admin')->where('id',aid)->find();
							if($admindata['chouchengset']==0){ //默认抽成
								if($dbwxpayset && $dbwxpayset['chouchengset']!=0){
									if($dbwxpayset['chouchengset'] == 1){
										//$chouchengmoney = floatval($dbwxpayset['chouchengrate']) * 0.01 * $paymoney;
										$chouchengmoney = floatval($dbwxpayset['chouchengrate']) * 0.01 * $feemoney;
										if($dbwxpayset['chouchengmin'] && $chouchengmoney < floatval($dbwxpayset['chouchengmin'])){
											$chouchengmoney = floatval($dbwxpayset['chouchengmin']);
										}
									}else{
										$chouchengmoney = floatval($dbwxpayset['chouchengmoney']);
									}
								}
							}elseif($admindata['chouchengset']==1){ //按比例抽成
								//$chouchengmoney = floatval($admindata['chouchengrate']) * 0.01 * $paymoney;
								$chouchengmoney = floatval($admindata['chouchengrate']) * 0.01 * $feemoney;
								if($chouchengmoney < floatval($admindata['chouchengmin'])){
									$chouchengmoney = floatval($admindata['chouchengmin']);
								}
							}elseif($admindata['chouchengset']==2){ //按固定金额抽成
								$chouchengmoney = floatval($admindata['chouchengmoney']);
							}
							//die;
							if($chouchengmoney >= 0.01 && $paymoney*0.3 >= $chouchengmoney){
								$chouchengmoney = intval($chouchengmoney*100)/100;
							}else{
								$chouchengmoney = 0;
							}
							if($business['feepercent'] > 0){
								$chouchengmoney2 = $feemoney;
								if($bset['commission_kouchu'] == 1){
									$commission = Wxpay::getcommission($tablename,$msg['out_trade_no']);
								}else{
									$commission = 0;
								}
								$chouchengmoney2 = $chouchengmoney2 + $commission;

								if($chouchengmoney2 >= 0.01 && $paymoney*0.3 >= $chouchengmoney2){
									$chouchengmoney2 = intval($chouchengmoney2*100)/100;
								}else{
									$chouchengmoney2 = 0;
								}
							}
						}else{
                            //使用多商户配置的服务商或者关闭
							if($business['feepercent'] > 0){
								$paymoney = $msg['total_fee']*0.01;
								if(false){}else{
									$chouchengmoney = floatval($business['feepercent']) * 0.01 * $paymoney;
								}
								if($bset['commission_kouchu'] == 1){
									$commission = Wxpay::getcommission($tablename,$msg['out_trade_no']);
								}else{
									$commission = 0;
								}
								$chouchengmoney = $chouchengmoney + $commission;

								if($chouchengmoney >= 0.01 && $paymoney*0.3 >= $chouchengmoney){
									$chouchengmoney = intval($chouchengmoney*100)/100;
								}else{
									$chouchengmoney = 0;
								}
							}
						}
						//扣除返现比例
						$queue_feepercent_type = 0;
						$queue_feepercent_allmoney = 0;
						$has_yx_queue_free_collage = 0;
						$sub_mchid = $business['wxpay_submchid'];
					}else{
						//平台订单 服务商分账
						$chouchengmoney = 0;
						if($appinfo['wxpay_type'] == 1){
							$paymoney = $msg['total_fee']*0.01;
							$admindata = Db::name('admin')->where('id',aid)->find();
							if($admindata['chouchengset']==0){ //默认抽成
								if($dbwxpayset && $dbwxpayset['chouchengset']!=0){
									if($dbwxpayset['chouchengset'] == 1){
										$chouchengmoney = floatval($dbwxpayset['chouchengrate']) * 0.01 * $paymoney;
										if($dbwxpayset['chouchengmin'] && $chouchengmoney < floatval($dbwxpayset['chouchengmin'])){
											$chouchengmoney = floatval($dbwxpayset['chouchengmin']);
										}
									}else{
										$chouchengmoney = floatval($dbwxpayset['chouchengmoney']);
									}
								}
							}elseif($admindata['chouchengset']==1){ //按比例抽成
								$chouchengmoney = floatval($admindata['chouchengrate']) * 0.01 * $paymoney;
								if($chouchengmoney < floatval($admindata['chouchengmin'])){
									$chouchengmoney = floatval($admindata['chouchengmin']);
								}
							}elseif($admindata['chouchengset']==2){ //按固定金额抽成
								$chouchengmoney = floatval($admindata['chouchengmoney']);
							}
							//die;
							if($chouchengmoney >= 0.01 && $paymoney*0.3 >= $chouchengmoney){
								$chouchengmoney = intval($chouchengmoney*100)/100;
							}else{
								$chouchengmoney = 0;
							}
						}
						$sub_mchid = ($appinfo['wxpay_type'] == 1 ? $appinfo['wxpay_sub_mchid'] : '');
					}

                    //记录
                    $data = array();
                    $data['fenzhangmoney'] = $chouchengmoney;
                    $data['fenzhangmoney2'] = $chouchengmoney2;
                    $data['sub_mchid'] = $sub_mchid;
                    Db::name('wxpay_log')->where('id',$paylogid)->update($data);

					\app\commons\Member::uplv(aid,mid);
				}
                //退款
                if($rs['status'] == 2){
                    $payorder = $rs['payorder'];
                    \app\commons\Wxpay::refund($aid,$payorder['platform'],$payorder['ordernum'],$payorder['money'],$paymoney,$rs['msg'],$payorder['bid'],$payorder);
                    //die(array2xml(['return_code'=>'FAIL','return_msg'=>'payorder_cancel']));
                    exit('fail');
                }
//                die(array2xml(['return_code'=>'SUCCESS','return_msg'=>'ok']));
				exit('success');
			}
		}
	}
	//云收银
	private function hemapay(){

		$querystring = urldecode($_SERVER['QUERY_STRING']);
		parse_str($querystring,$querydata);
		Log::write($querydata);
		ll($querydata,'$querydata');
		
     	if(!$querydata['attach']){
			$payorder = Db::name('payorder')->where('ordernum',$querydata['out_order_no'])->find();
			if($querydata['busicd'] == 'WXMP'){}
			$aid = intval($payorder['aid']);
			$tablename = $payorder['type'];
			$platform = 'wx';
	    	$pay_way_code =	$querydata['pay_way_code'];
			$platform = $pay_way_code =='ALIPAY' ? 'h5' : 'mp';
    		$paytype = $pay_way_code =='ALIPAY' ? '河马支付宝支付' : '河马微信支付';
    		$paytypeid  = $pay_way_code=='ALIPAY' ? 76: 75;
    			
			
		}else{
			$attach = explode(':',$querydata['attach']);
			$aid = intval($attach[0]);
			$tablename = $attach[1];
			$platform = $attach[2];
		}
		define('aid',$aid);
		$appinfo = \app\commons\System::appinfo($aid,$platform);
		require_once (ROOT_PATH. '/app/commons/api/HmPayClient.php');
      	$aop = new \HmPayClient();
        ll($querydata,'$querydata');
       // $verifyB = $aop->rsaCheckV2($querydata, $aop->platRsaPublicKey);
        if($querydata['trade_status'] == 'SUCCESS'){
		    $total_amount = number_format($querydata['pay_amount']/100,2);
			$rs = $this->setorder($tablename,$querydata['out_order_no'],$querydata['plat_trx_no'],$total_amount,$paytype,$paytypeid);
			if($rs['status'] == 1){
			//记录
				$data = array();
				$data['aid'] = aid;
				$data['mid'] = mid;
				$data['openid'] = '';
				$data['tablename'] = $tablename;
				$data['givescore'] = $this->givescore;
				$data['ordernum'] = $querydata['out_order_no'];
				$data['mch_id'] = $querydata['app_id'];
				$data['transaction_id'] = $querydata['plat_trx_no'];
				$data['total_fee'] = intval($querydata['pay_amount'])*0.01;
				$data['createtime'] = time();
				Db::name('wxpay_log')->insert($data);
				\app\commons\Member::uplv(aid,mid);
			}
			exit('SUCCESS');	
		}else{
			exit('fail');
		}
	}
	//支付宝支付
	private function sdbpay(){
		$msg = $_POST;
		$data=json_decode($msg['data'],true);
		$head = $data['head'];
		$data = $data['body'];
		
		if ($data['extend']) {
		    $attach = explode(':',$data['extend']);
		}else {
		   $orderCode = Db::name('payorder')->where('orderCode',$data['orderCode'])->find();
		   $attach = [];
		   $attach[] = $orderCode['aid'];
		   $attach[] = $orderCode['type'];
		   $attach[] = 'bankpay';
		   $attach[] = $orderCode['ordernum'];
		}
// 		$attach = explode(':',$data['extend']);
		$aid = intval($attach[0]);
		define('aid',$aid);
		$tablename = $attach[1];
		$form = $attach[2];
		$ordernum = $attach[3];
		if($form == 'alipay'){
			$paytype ='支付宝支付';
			$paytypeid ='76';
		}elseif($form== 'wxpay'){
			$paytype ='微信支付';
			$paytypeid ='75';
		}elseif($form== 'bankpay'){
			$paytype ='银联支付';
			$paytypeid ='78';
		}

		require_once (ROOT_PATH. '/app/common/api/sdpay.class.php');
      	$aop = new \sdpay();
        $verifyFlag = $aop->verify($msg['data'], $msg['sign']);  
		if($verifyFlag){
			if($head['respCode'] == '000000'){
			    $total_amount = number_format($data['totalAmount']/100,2);
				$rs = $this->setorder($tablename,$ordernum,$data['tradeNo'],$total_amount,$paytype,$paytypeid);
				if($rs['status'] == 1){
					//记录
					$data = array();
					$data['aid'] = aid;
					$data['mid'] = mid;
					$data['openid'] = '';
					$data['tablename'] = $tablename;
					$data['givescore'] = $this->givescore;
					$data['ordernum'] = $ordernum;
					$data['transaction_id'] = $data['tradeNo'];
					$data['total_fee'] = $total_amount;
					$data['createtime'] = time();
					Db::name('alipay_log')->insert($data);
					\app\commons\Member::uplv(aid,mid);
				}
				exit('success');	
			}
		}else{
			exit('fail');
		}
	}
	//百度支付
	private function baidupay(){
		$msg = $_POST;
		$returnData = json_decode($msg['returnData'],true);
		$attach = explode(':',$returnData['params']);
		$aid = intval($attach[0]);
		define('aid',$aid);
		$tablename = $attach[1];
		$baiduapp = \app\commons\System::appinfo($aid,'baidu');
		$result = \app\commons\RSASign::checkSign($msg,$baiduapp['pay_publickey']);
		if($result){
			if($msg['status'] == 2){
				$rs = $this->setorder($tablename,$msg['tpOrderId'],$msg['orderId'],$msg['payMoney'],'百度支付',11);
                $paymoney = $msg['payMoney']*0.01;
				if($rs['status'] == 1){
					//记录
					$data = array();
					$data['aid'] = aid;
					$data['mid'] = mid;
					$data['openid'] = '';
					$data['tablename'] = $tablename;
					$data['givescore'] = $this->givescore;
					$data['ordernum'] = $msg['tpOrderId'];
					$data['mch_id'] = $baiduapp['pay_appid'];
					$data['transaction_id'] = $msg['orderId'];
					$data['total_fee'] = $paymoney;
					$data['createtime'] = time();
					$data['userId'] = $msg['userId'];
					Db::name('baidupay_log')->insert($data);
					\app\commons\Member::uplv(aid,mid);
				}
                //退款
                if($rs['status'] == 2){
                    $payorder = $rs['payorder'];
                    \app\commons\Baidupay::refund($aid,$payorder['mid'],$payorder['ordernum'],$payorder['paynum'],$payorder['money'],$paymoney,$payorder['platform'],$rs['msg']);
                    exit('fail');
                }

				$ret = [];
				$ret['errno'] = 0;
				$ret['msg']   = 'success';
				$ret['data']  = ['isConsumed'=>2];
				exit(json_encode($ret));
			}
		}else{
            Log::error('baidupay check sign error line:'.__LINE__);
			exit('fail');
		}
	}

	//支付宝支付
	private function alipay(){
		$msg = $_POST;
		$attach = explode(':',urldecode($msg['passback_params']));
		$aid = intval($attach[0]);
		define('aid',$aid);
		$tablename = $attach[1];
		$platform = $attach[2];
		$appinfo = \app\commons\System::appinfo($aid,$platform);
		if($platform == 'alipay'){
			$appinfo['ali_publickey'] = $appinfo['publickey'];
		}
		$paytype = '支付宝支付';
		if($attach[3] && $attach[3]!=1){
			$appinfo['ali_publickey'] = $appinfo['ali_publickey'.$attach[3]];
			$paytype = $appinfo['alipayname'.$attach[3]];
		}

		//Log::write($msg);
		//Log::write($appinfo);
		require_once(ROOT_PATH.'/extend/aop/AopClient.php');
		$aop = new \AopClient();
		$aop->alipayrsaPublicKey = $appinfo['ali_publickey'];
		$result = $aop->rsaCheckV1($msg,$appinfo['ali_publickey'],$msg['sign_type']);
		if($result){
			if($msg['trade_status'] == 'TRADE_FINISHED' || $msg['trade_status'] == 'TRADE_SUCCESS'){
				$rs = $this->setorder($tablename,$msg['out_trade_no'],$msg['trade_no'],$msg['total_amount']*100,$paytype,3);
				if($rs['status'] == 1){
					//记录
					$data = array();
					$data['aid'] = aid;
					$data['mid'] = mid;
					$data['openid'] = '';
					$data['tablename'] = $tablename;
					$data['givescore'] = $this->givescore;
					$data['ordernum'] = $msg['out_trade_no'];
					$data['mch_id'] = $msg['app_id'];
					$data['transaction_id'] = $msg['trade_no'];
					$data['total_fee'] = $msg['total_amount'];//单位 元
					$data['createtime'] = time();
					Db::name('alipay_log')->insert($data);
					\app\commons\Member::uplv(aid,mid);
				}
                //退款
                if($rs['status'] == 2){
                    $payorder = $rs['payorder'];
                    \app\commons\Alipay::refund($aid,$payorder['platform'],$payorder['ordernum'],$payorder['money'], $msg['total_amount'],$rs['msg'],$payorder['bid']);
                    exit('fail');
                }
				exit('success');	
			}
		}else{
            Log::error('alipay check sign error line:'.__LINE__);
			exit('fail');
		}
	}

	//头条支付
	public function ttpay($post){
		//Log::write($post);
		$msg = json_decode($post['msg'],true);
		$extra = json_decode($msg['cp_extra'],true);
		$attach = explode(':',$extra['param']);
		$aid = intval($attach[0]);
		define('aid',$aid);
		$tablename = $attach[1];
		$toutiaoapp = \app\commons\System::appinfo($aid,'toutiao');
		$post['token'] = $toutiaoapp['pay_token'];

		$signdata = [];
		$signdata[] = $toutiaoapp['pay_token'];
		$signdata[] = $post['timestamp'];
		$signdata[] = $post['nonce'];
		$signdata[] = $post['msg'];
		sort($signdata,2);
		$signstr = implode('',$signdata);
		$sign = sha1($signstr);
		if($sign == $post['msg_signature']){
			$rs = $this->setorder($tablename,$msg['cp_orderno'],$post['channel_no'],$extra['total_amount'],'抖音小程序支付',12);
            $paymoney = $extra['total_amount'] * 0.01;
			if($rs['status'] == 1){
				//记录
				$data = array();
				$data['aid'] = aid;
				$data['mid'] = mid;
				$data['openid'] = '';
				$data['tablename'] = $tablename;
				$data['givescore'] = $this->givescore;
				$data['ordernum'] = $msg['out_trade_no'];
				$data['mch_id'] = '';
				$data['transaction_id'] = '';
				$data['total_fee'] = $extra['total_amount'];
				$data['createtime'] = time();
				Db::name('toutiaopay_log')->insert($data);
				\app\commons\Member::uplv(aid,mid);
			}
            //退款
            if($rs['status'] == 2){
                $payorder = $rs['payorder'];
                \app\commons\Ttpay::refund($aid,$payorder['ordernum'],$payorder['money'],$paymoney,$rs['msg']);
                exit('fail');
            }
			exit(json_encode(['err_no'=>0,'err_tips'=>'success']));	
		}else{
            Log::error('ttpay check sign error line:'.__LINE__);
			exit('fail');
		}
	}
	
	//云收银
	private function yunpay(){

		$querystring = urldecode($_SERVER['QUERY_STRING']);
		parse_str($querystring,$querydata);
		//Log::write($querydata);
		if(!$querydata['attach']){
			$payorder = Db::name('payorder')->where('ordernum',$querydata['orderNum'])->find();
			if($querydata['busicd'] == 'WXMP'){
				$aid = intval($payorder['aid']);
				$tablename = $payorder['type'];
				$platform = 'wx';
			}
		}else{
			$attach = explode(':',$querydata['attach']);
			$aid = intval($attach[0]);
			$tablename = $attach[1];
			$platform = $attach[2];
		}
		define('aid',$aid);
		$appinfo = \app\commons\System::appinfo($aid,$platform);

		ksort($querydata);
		$string1 = '';
		foreach ($querydata as $k => $v) {
			if ($v != '' && $k != 'sign') {
				$string1 .= "{$k}={$v}&";
			}
		}
		$string1 = trim($string1,'&');
		$string1 .= $appinfo['yun_mchkey'];
		$sign = hash("sha256",$string1);
		//Log::write($sign);
		//Log::write($querydata['sign']);
		if($sign == $querydata['sign']){
			if($querydata['respcd'] == '00'){
				Db::name('payorder')->where('aid',aid)->where('ordernum',$querydata['orderNum'])->update(['platform'=>$platform]);
				$rs = $this->setorder($tablename,$querydata['orderNum'],$querydata['channelOrderNum'],intval($querydata['txamt']),'在线支付',22);
                $paymoney = intval($querydata['txamt'])*0.01;
                if($rs['status'] == 1){
					//记录
					$data = array();
					$data['aid'] = aid;
					$data['mid'] = mid;
					$data['openid'] = '';
					$data['tablename'] = $tablename;
					$data['givescore'] = $this->givescore;
					$data['ordernum'] = $querydata['orderNum'];
					$data['mch_id'] = $appinfo['pay_appid'];
					$data['transaction_id'] = $querydata['channelOrderNum'];
					$data['total_fee'] = $paymoney;
					$data['createtime'] = time();
					Db::name('wxpay_log')->insert($data);
					\app\commons\Member::uplv(aid,mid);
				}
                //退款
                if($rs['status'] == 2){
                    $payorder = $rs['payorder'];
                    \app\commons\Yunpay::refund($aid,$payorder['platform'],$payorder['ordernum'],$payorder['money'],$paymoney,$rs['msg']);
                    exit('fail');
                }
				exit('success');
			}
		}else{
            Log::error('yunpay check sign error line:'.__LINE__);
			exit('fail');
		}
	}

    //随行付
	private function sxpay(){
		$postdata = json_decode(file_get_contents('php://input'),true);
        Log::write(__FILE__.__LINE__);
		Log::write($postdata);
		$attach = explode(':',$postdata['extend']);
		$aid = intval($attach[0]);
		$tablename = $attach[1];
		$platform = $attach[2];
		if($platform == 'sxpaymp')     $platform = 'mp';
		if($platform == 'sxpaywx')     $platform = 'wx';
		if($platform == 'sxpayalipay') $platform = 'alipay';
		if($platform == 'sxalih5')     $platform = 'h5';
		$appinfo = \app\commons\System::appinfo($aid,$platform);
		if($platform == 'h5'){
			$appinfo['appid']        = $appinfo['ali_appid'];
			$appinfo['sxpay_mno']    = $appinfo['alisxpay_mno'];
			$appinfo['sxpay_mchkey'] = $appinfo['alisxpay_mchkey'];
		}
		$isbusinesspay = 0;
		if($attach[4]){
			$bid = intval($attach[4]);
			$business = Db::name('business')->where('id',$bid)->find();
			$appinfo['sxpay_mno'] = $business['sxpay_mno'];
			$appinfo['sxpay_mchkey'] = $business['sxpay_mchkey'];
			$isbusinesspay = 1;
		}

		//if($appinfo['sxpay_mno'] == '399220401616754') die;

		$md5sign = $attach[3];
		define('aid',$aid);
		if($md5sign == md5($tablename.$postdata['ordNo'].$appinfo['sxpay_mchkey'])){
			if($postdata['bizCode'] == '0000'){
				Db::name('payorder')->where('aid',aid)->where('ordernum',$postdata['ordNo'])->update(['platform'=>$platform,'isbusinesspay'=>$isbusinesspay]);
				$paytype = '微信支付';
                $paytypeid = 2;
				if($platform=='alipay'){
                    $paytype = '支付宝支付';
                    $paytypeid = 3;
                }
                $payorder = Db::name('payorder')->where(['aid'=>$aid,'type'=>$tablename,'ordernum'=>$postdata['ordNo']])->find();
				$mid = $payorder['mid']?:0;
                //记录
                $data = array();
                $data['aid'] = aid;
                $data['mid'] = $mid;
                $data['openid'] = $postdata['uuid'];
                $data['tablename'] = $tablename;
                $data['givescore'] = $this->givescore;
                $data['ordernum'] = $postdata['ordNo'];
                $data['mch_id'] = $postdata['mno'];
                $data['transaction_id'] = $postdata['transactionId'];
                $data['total_fee'] = $postdata['amt'];
                $data['createtime'] = time();
                Db::name('wxpay_log')->insert($data);

				$rs = $this->setorder($tablename,$postdata['ordNo'],$postdata['transactionId'],intval($postdata['amt']*100),$paytype,$paytypeid);
				if($rs['status'] == 1){
					\app\commons\Member::uplv(aid,$mid);
				}
                //退款
                if($rs['status'] == 2){
                    $payorder = $rs['payorder'];
                    \app\customs\Sxpay::refund($aid,$payorder['platform'],$payorder['ordernum'],$payorder['money'],$postdata['amt'],$rs['msg'],$payorder['bid']);
                    exit('fail');
                }
				die('{"code":"success","msg":"成功"}');
			}
		}else{
            Log::write(__FILE__.__LINE__);
            Log::write('sxpay error:加密校验不通过');
        }
	}
	private function fbpay(){
		$postdata = json_decode(file_get_contents('php://input'),true);
		$attach = explode(':',$postdata['attach']);
		$aid = intval($attach[0]);
		$tablename = $attach[1];
		$platform = $attach[2];
		if($platform == 'fbpaymp') $platform = 'mp';
		if($platform == 'fbpaywx') $platform = 'wx';
		if($platform == 'fbpayali') $platform = 'alipay';
		$appinfo = \app\commons\System::appinfo($aid,$platform);
		$md5sign = $attach[3];
		define('aid',$aid);

		ksort($postdata);
		$string1 = '';
		foreach ($postdata as $k => $v) {
			if ($v != '' && $k != 'sign') {
				$string1 .= "{$k}={$v}&";
			}
		}
		$string1 = trim($string1,'&');
		$string1 .= $appinfo['fbpay_appsecret'];
		$sign = strtoupper(md5($string1));
		if($sign == $postdata['sign']){
			if($postdata['result_code'] == '200'){
				Db::name('payorder')->where('aid',aid)->where('ordernum',$postdata['merchant_order_sn'])->update(['platform'=>$platform]);
				$rs = $this->setorder($tablename,$postdata['merchant_order_sn'],$postdata['order_sn'],intval($postdata['fee']*100),($platform=='alipay'?'支付宝支付':'微信支付'),2);
				if($rs['status'] == 1){
					//记录
					$data = array();
					$data['aid'] = aid;
					$data['mid'] = mid;
					$data['openid'] = $postdata['user_id'];
					$data['tablename'] = $tablename;
					$data['givescore'] = $this->givescore;
					$data['ordernum'] = $postdata['merchant_order_sn'];
					$data['mch_id'] = $appinfo['fbpay_appid'];
					$data['transaction_id'] = $postdata['order_sn'];
					$data['total_fee'] = $postdata['fee'];
					$data['createtime'] = time();
					if($platform=='alipay'){
						Db::name('alipay_log')->insert($data);
					}else{
						Db::name('wxpay_log')->insert($data);
					}
					\app\commons\Member::uplv(aid,mid);
				}
                //退款
                if($rs['status'] == 2){
                    $payorder = $rs['payorder'];
                    \app\customs\Fbpay::refund($aid,$payorder['platform'],$payorder['ordernum'],$payorder['money'],$postdata['fee'],$rs['msg']);
                    exit('fail');
                }
				die('success');
			}
		}else{
            Log::error('fbpay check sign error line:'.__LINE__);
        }
	}

    private function huifupay(){
        $post = file_get_contents('php://input');
        //$post = 'resp_desc=%E4%BA%A4%E6%98%93%E6%88%90%E5%8A%9F%5B000%5D&resp_code=00000000&sign=MwtpQ7PT6bs8OOn7GDHs3txH8uQlZT7uYq%2Bq5jtpaZEGQXd2x6ma%2B5ak8DH%2BSNC6%2FUQA%2BCmMBtCSE%2FSGWE%2F3FtmBcGpp96iBfX6qZHjKFEIGrYer2nUj0y7gATSMT%2FIrg77d%2FkvMVG3%2BY4zZ0L39581rG6l6q8Wo6C4wXi5cLp0MJ1aKdt41f%2FQN5e1%2BjopMpwAThZCLb%2FvJmW2MyL3RTB2SG5WXNGdOoBX9H2xaRBCb%2BXhYskdvDPpzAUJyGjFLTK1oUWK8202gaQ%2F5EJFd%2Bsx0JCDu81dDdzWuJhlRZCpzF%2B1nuFJ7CjHj5cHhgSGnzKLOK2hRr%2Fu1ptd7jw2DgQ%3D%3D&resp_data=%7B%22acct_date%22%3A%2220231111%22%2C%22acct_id%22%3A%22A27364363%22%2C%22acct_split_bunch%22%3A%7B%22acct_infos%22%3A%5B%7B%22acct_date%22%3A%2220231111%22%2C%22acct_id%22%3A%22A27364363%22%2C%22div_amt%22%3A%220.01%22%2C%22huifu_id%22%3A%226666000139980345%22%7D%5D%2C%22fee_acct_date%22%3A%2220231111%22%2C%22fee_acct_id%22%3A%22A27364363%22%2C%22fee_amt%22%3A%220.00%22%2C%22fee_huifu_id%22%3A%226666000139980345%22%7D%2C%22acct_stat%22%3A%22S%22%2C%22atu_sub_mer_id%22%3A%22597291375%22%2C%22avoid_sms_flag%22%3A%22%22%2C%22bagent_id%22%3A%226666000139397368%22%2C%22bank_code%22%3A%22SUCCESS%22%2C%22bank_desc%22%3A%22%E4%BA%A4%E6%98%93%E6%88%90%E5%8A%9F%22%2C%22bank_message%22%3A%22%E4%BA%A4%E6%98%93%E6%88%90%E5%8A%9F%22%2C%22bank_order_no%22%3A%224200001975202311113249051625%22%2C%22bank_seq_id%22%3A%221T1495%22%2C%22bank_type%22%3A%22OTHERS%22%2C%22base_acct_id%22%3A%22A27364363%22%2C%22batch_id%22%3A%22231111%22%2C%22channel_type%22%3A%22U%22%2C%22charge_flags%22%3A%22758_0%22%2C%22combinedpay_data%22%3A%5B%5D%2C%22combinedpay_fee_amt%22%3A%220.00%22%2C%22debit_type%22%3A%220%22%2C%22delay_acct_flag%22%3A%22N%22%2C%22div_flag%22%3A%220%22%2C%22end_time%22%3A%2220231111231450%22%2C%22fee_amount%22%3A%220.00%22%2C%22fee_amt%22%3A%220.00%22%2C%22fee_flag%22%3A2%2C%22fee_formula_infos%22%3A%5B%7B%22fee_formula%22%3A%22AMT*0.003%22%2C%22fee_type%22%3A%22TRANS_FEE%22%7D%5D%2C%22fee_rec_type%22%3A%221%22%2C%22fee_type%22%3A%22INNER%22%2C%22gate_id%22%3A%22VT%22%2C%22hf_seq_id%22%3A%22002900TOP1B231111231436P810ac139c7f00000%22%2C%22huifu_id%22%3A%226666000139980345%22%2C%22is_delay_acct%22%3A%220%22%2C%22is_div%22%3A%220%22%2C%22mer_name%22%3A%22%E5%9B%BD%E7%9B%88%E6%B3%B0%E5%92%8C%28%E5%8C%97%E4%BA%AC%29%E6%8E%A7%E8%82%A1%E6%9C%89%E9%99%90%E5%85%AC%E5%8F%B8%22%2C%22mer_ord_id%22%3A%22231111231430537821T1495%22%2C%22mypaytsf_discount%22%3A%220.00%22%2C%22need_big_object%22%3Afalse%2C%22notify_type%22%3A2%2C%22org_auth_no%22%3A%22%22%2C%22org_huifu_seq_id%22%3A%22%22%2C%22org_trans_date%22%3A%22%22%2C%22out_ord_id%22%3A%224200001975202311113249051625%22%2C%22out_trans_id%22%3A%224200001975202311113249051625%22%2C%22party_order_id%22%3A%2203232311118367694710658%22%2C%22pay_amt%22%3A%220.01%22%2C%22pay_scene%22%3A%2202%22%2C%22posp_seq_id%22%3A%2203232311118367694710658%22%2C%22product_id%22%3A%22PAYUN%22%2C%22ref_no%22%3A%222314361T1495%22%2C%22remark%22%3A%2261%253Ashop%253Awx%22%2C%22req_date%22%3A%2220231111%22%2C%22req_seq_id%22%3A%22231111231430537821T1495%22%2C%22resp_code%22%3A%2200000000%22%2C%22resp_desc%22%3A%22%E4%BA%A4%E6%98%93%E6%88%90%E5%8A%9F%22%2C%22risk_check_data%22%3A%7B%22ip_addr%22%3A%2239.76.56.159%22%7D%2C%22risk_check_info%22%3A%7B%22client_ip%22%3A%2239.76.56.159%22%7D%2C%22settlement_amt%22%3A%220.01%22%2C%22sub_resp_code%22%3A%2200000000%22%2C%22sub_resp_desc%22%3A%22%E4%BA%A4%E6%98%93%E6%88%90%E5%8A%9F%22%2C%22subsidy_stat%22%3A%22I%22%2C%22sys_id%22%3A%226666000139980345%22%2C%22trade_type%22%3A%22T_MINIAPP%22%2C%22trans_amt%22%3A%220.01%22%2C%22trans_date%22%3A%2220231111%22%2C%22trans_fee_allowance_info%22%3A%7B%22actual_fee_amt%22%3A%220.00%22%2C%22allowance_fee_amt%22%3A%220.00%22%2C%22allowance_type%22%3A%220%22%2C%22receivable_fee_amt%22%3A%220.00%22%7D%2C%22trans_stat%22%3A%22S%22%2C%22trans_time%22%3A%22231436%22%2C%22trans_type%22%3A%22T_MINIAPP%22%2C%22ts_encash_detail%22%3A%5B%5D%2C%22wx_response%22%3A%7B%22bank_type%22%3A%22OTHERS%22%2C%22coupon_fee%22%3A%220.00%22%2C%22openid%22%3A%22o8jhot6LBCkb2DLS3KhMOx6W0joM%22%2C%22sub_appid%22%3A%22wxb07130f2d20b010a%22%2C%22sub_openid%22%3A%22o2qmV69SoMHAZfUQIrkHMysCmryo%22%7D%7D';
        parse_str($post,$msg);
        $msg['resp_desc'] = urldecode($msg['resp_desc']);
        $msg['resp_code'] = urldecode($msg['resp_code']);
//        $msg['sign'] = urldecode($msg['sign']);
        $resp_data = urldecode($msg['resp_data']);
        Log::write($msg);
        $resp_data = json_decode($resp_data,true);

        $attach = explode(':',urldecode($resp_data['remark']));
        $aid = intval($attach[0]);
        define('aid',$aid);
        $tablename = $attach[1];
        $platform = $attach[2];
        $appinfo = \app\commons\System::appinfo($aid,$platform);
        $req_seq_idarr = explode('T',$resp_data['req_seq_id']);
        $ordernum =$req_seq_idarr[0];

        //Log::write($msg);
        Log::write($appinfo);

        require_once ROOT_PATH . "vendors/huifurepo/dg-php-sdk/BsPaySdk/init.php";
        $result = \BsPaySdk\core\BsPayTools::verifySign($msg['sign'], $msg['resp_data'], $appinfo['huifu_public_key']);
        if($result == 1){
            if($resp_data['resp_code'] == '00000000'){
                $paytype = '微信支付';
                $paytypeid = 2;
                if($platform=='alipay'){
                    $paytype = '支付宝支付';
                    $paytypeid = 3;
                }
                $huifu_total_fee  =$resp_data['trans_amt'] *100;
                $rs = $this->setorder($tablename,$ordernum,$resp_data['hf_seq_id'],$huifu_total_fee,$paytype,$paytypeid);
                if($rs['status'] == 1){
                    //记录
                    $data = array();
                    $data['is_div'] = $resp_data['is_div'];
                    $data['acct_split_bunch'] = json_encode($resp_data['acct_split_bunch']);
                    $data['notify_data'] = json_encode($resp_data);
                    $data['pay_status'] = 1;
                    Db::name('huifu_log')->where('aid',aid)->where('req_seq_id',$resp_data['req_seq_id'])->update($data);
                    \app\commons\Member::uplv(aid,mid);
                }
                //退款
                if($rs['status'] == 2){
                    $payorder = $rs['payorder'];
                    $huifu = new \app\customs\Huifu([],$payorder['aid'],$payorder['bid'],$payorder['mid'],$rs['msg'],$payorder['ordernum'],$payorder['money']);
                    $huifu->refund($resp_data['trans_amt'],$payorder);
                    exit('fail');
                }
                exit("RECV_ORD_ID_" . $resp_data['req_seq_id']);
            }
        }else{
            Log::error('huifupay check sign error line:'.__LINE__);
            exit('fail');
        }
    }

	private function qmpay(){
		//Log::write('qmpay---------------------------');
		$querystring = urldecode(file_get_contents('php://input'));
		parse_str($querystring,$querydata);
		//Log::write($querydata);
		
		$merOrderId = str_replace('11UM','',$querydata['merOrderId']);
		
		if(!$querydata['attachedData']){
			$payorder = Db::name('payorder')->where('ordernum',$merOrderId)->find();
			$aid = intval($payorder['aid']);
			$tablename = $payorder['type'];
			$platform = 'h5';
		}else{
			$attach = explode(':',$querydata['attachedData']);
			$aid = intval($attach[0]);
			$tablename = $attach[1];
			$platform = $attach[2];
		}
		define('aid',$aid);
		$appinfo = \app\commons\System::appinfo($aid,$platform);

		ksort($querydata);
		$string1 = '';
		foreach ($querydata as $k => $v) {
			if ($v != '' && $k != 'sign') {
				$string1 .= "{$k}={$v}&";
			}
		}
		$config = include(ROOT_PATH.'config.php');
		$config = $config['qmpay'];

		$string1 = trim($string1,'&');
		//$string1 .= '47ace12ae3b348fe93ab46cee97c6fde';//$appinfo['yun_mchkey'];
		$string1 .= $config['md5key'];
		$sign = strtoupper(hash("sha256",$string1));
		//$sign = strtoupper(md5($string1));
		//Log::write($string1);
		//Log::write('-----1');
		//Log::write($sign);
		//Log::write('-----2');
		//Log::write($querydata['sign']);
		if($sign == $querydata['sign']){
			if($querydata['status'] == 'TRADE_SUCCESS'){
				Db::name('payorder')->where('aid',aid)->where('ordernum',$merOrderId)->update(['platform'=>$platform]);
				$rs = $this->setorder($tablename,$merOrderId,$querydata['targetOrderId'],intval($querydata['totalAmount']),'支付宝支付',23);
				if($rs['status'] == 1){
					//记录
					$data = array();
					$data['aid'] = aid;
					$data['mid'] = mid;
					$data['openid'] = '';
					$data['tablename'] = $tablename;
					$data['givescore'] = $this->givescore;
					$data['ordernum'] = $merOrderId;
					$data['mch_id'] = $appinfo['pay_appid'];
					$data['transaction_id'] = $querydata['targetOrderId'];
					$data['total_fee'] = intval($querydata['totalAmount'])*0.01;
					$data['createtime'] = time();
					Db::name('wxpay_log')->insert($data);
					\app\commons\Member::uplv(aid,mid);
				}
                //退款
                if($rs['status'] == 2){
                    $payorder = $rs['payorder'];
                    \app\commons\Qmpay::refund($aid,$payorder['platform'],$payorder['ordernum'],$payorder['money'],intval($querydata['totalAmount'])*0.01,$rs['msg']);
                    exit('fail');
                }
				exit('success');
			}
		}else{
            Log::error('qmpay check sign error line:'.__LINE__);
			exit('fail');
		}
	}

	//adapay
    public function adapay(){
    	}

    /**
     * @param $tablename
     * @param $out_trade_no 内部订单号
     * @param $transaction_id 第三方交易号
     * @param $total_fee 金额，整数位，单位分 100是1块钱
     * @param $paytype
     * @param $paytypeid
     * @return array|null status 0已支付，1正常，2退款
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    private function setorder($tablename,$out_trade_no,$transaction_id,$total_fee,$paytype,$paytypeid){
		
		$payorder = Db::name('payorder')->where(['aid'=>aid,'type'=>$tablename,'ordernum'=>$out_trade_no])->find();
		if($payorder['status']!=0) return ['status'=>0,'msg'=>'订单已支付'];
        if($payorder['money']*100 != $total_fee){
            Log::write([
               'file'=>__FILE__.__FUNCTION__.__LINE__,
               '金额不一致',
               '$total_fee'=>$total_fee,
               '$payorder'=>jsonEncode($payorder)
            ]);
//            //金额不一致 退款
//		    return ['status'=>2,'msg'=>'支付金额和订单金额不一致','payorder'=>$payorder];
        }
//        if($payorder['status'] == 2){
//            //支付单取消
//            return ['status'=>2,'msg'=>'订单已修改，请重新发起支付','payorder'=>$payorder];
//        }
		if($payorder['score'] > 0){
			//$rs = \app\commons\Member::addscore(aid,$payorder['mid'],-$payorder['score'],'支付订单，订单号：'.$out_trade_no);
			if($payorder['bid']==0 && $payorder['type'] != 'shop_hb'){
				$rs = \app\commons\Member::addscore(aid,$payorder['mid'],-$payorder['score'],'支付订单，订单号: '.$payorder['ordernum']);
			}else{
				$business_selfscore = 0;
				if($business_selfscore == 0){
					$rs = \app\commons\Member::addscore(aid,$payorder['mid'],-$payorder['score'],'支付订单,订单号: '.$payorder['ordernum'],'',$payorder['bid']);
					}else{
					if($payorder['type'] != 'shop_hb'){
						$rs = \app\commons\Business::addmemberscore(aid,$payorder['bid'],$payorder['mid'],-$payorder['score'],'支付订单,订单号: '.$payorder['ordernum'],1);
					}else{
						$subpayorderlist = Db::name('payorder')->where('aid',aid)->where('type','shop')->where('ordernum','like',$payorder['ordernum'].'_%')->select()->toArray();
						foreach($subpayorderlist as $subpayorder){
							if($subpayorder['score'] == 0) continue;
							if($subpayorder['bid'] == 0){
								$rs = \app\commons\Member::addscore(aid,$payorder['mid'],-$subpayorder['score'],'支付订单,订单号: '.$subpayorder['ordernum']);
							}elseif($subpayorder['bid'] != 0){
								$rs = \app\commons\Business::addmemberscore(aid,$subpayorder['bid'],$payorder['mid'],-$subpayorder['score'],'支付订单,订单号: '.$subpayorder['ordernum'],1);
							}
						}
					}
				}
			}

				if($rs['status'] == 0){
				$order = $payorder;
				$order['totalprice'] = $order['money'];
				$order['paytypeid']  = $paytypeid;

				$params = [];
                $rs = \app\commons\Order::refund($order,$order['money'],'积分扣除失败退款',$params);
				Log::write($rs);
				return ['status'=>0,'msg'=>'已退款'];
			}
		}
		if($payorder['credit'] > 0){
			$rs = \app\commons\Member::addcredit2(aid,$payorder['mid'],-$payorder['credit'],'支付订单,订单号: '.$payorder['ordernum']);
			if($rs['status'] == 0){
			    if ($payorder['score'] > 0) {
			        \app\commons\Member::addscore(aid,$payorder['mid'],$payorder['score'],t('credit2').'扣除失败退款订单号: '.$payorder['ordernum']);
			    }
				$order = $payorder;
				$order['totalprice'] = $order['money'];
				$order['paytypeid'] = $paytypeid;
				$rs = \app\commons\Order::refund($order,$order['money'],t('credit2').'扣除失败退款');
				Log::write($rs);
				return ['status'=>0,'msg'=>'已退款'];
			}
		}
		$rs = \app\models\Payorder::payorder($payorder['id'],$paytype,$paytypeid,$transaction_id);
		if($rs['status']==0) return $rs;
		define('mid',$payorder['mid']);

		$set = Db::name('admin_set')->where('aid',aid)->find();
		//消费送积分
		if($tablename != 'recharge' && $set['scorein_money']>0 && $set['scorein_score']>0){
			$givescore = floor($total_fee*0.01 / $set['scorein_money']) * $set['scorein_score'];
			$res = \app\commons\Member::addscore(aid,mid,$givescore,'消费送'.t('积分'));
			if($res && $res['status'] == 1){
				//记录消费赠送积分记录
				\app\commons\Member::scoreinlog(aid,0,mid,$payorder['type'],$payorder['orderid'],$payorder['ordernum'],$givescore,$total_fee);
			}
		}
		//充值送积分
		if($tablename == 'recharge' && $set['scorecz_money']>0 && $set['scorecz_score']>0){
			$givescore = floor($total_fee*0.01 / $set['scorecz_money']) * $set['scorecz_score'];
			\app\commons\Member::addscore(aid,mid,$givescore,'充值送'.t('积分'));
		}
		$this->givescore = $givescore;
		return ['status'=>1,'msg'=>''];
	}


	//快递100
	private function kuaidi100(){
		$msg = $_POST;
		$param = json_decode($msg['param'],true);
		//Log::write($param);
		if($param['data']['orderId']){
			 $data = [];
			 $data['courierName'] = $param['data']['courierName'];
			 $data['courierMobile'] = $param['data']['courierMobile'];
			 $data['kuaidinum'] = ['kuaidinum'];
			 $data['status'] = $param['data']['status'];
			 Db::name('express_order')->where(['orderId'=>$param['data']['orderId']])->update($data);
			return ['status'=>1,'msg'=>''];
		}
	}	

	//随行付审核
	private function sxaudit(){
		//\think\facade\Log::write(file_get_contents('php://input'));
		$postdata = json_decode(file_get_contents('php://input'),true);
		\think\facade\Log::write($postdata);
		$updata = [];
		if($postdata['taskType'] == '01'){ //修改单
			$updata['taskStatus_edit'] = $postdata['taskStatus'];
			$updata['suggestion_edit'] = $postdata['suggestion'];
		}else{ //入驻单
			$updata['taskStatus'] = $postdata['taskStatus'];
			$updata['suggestion'] = $postdata['suggestion'];
			$repoInfo = $postdata['repoInfo'];
			if($repoInfo){
				$submchid = '';
				$zfbmchid = '';
				foreach($repoInfo as $v){
					if($v['childNoType'] == 'WX'){
						$submchid = $v['childNo'];
					}
					if($v['childNoType'] == 'ZFB'){
						$zfbmchid = $v['childNo'];
					}
				}
				if($submchid){
					$updata['submchid'] = $submchid;
				}
				if($zfbmchid){
					$updata['zfbmchid'] = $zfbmchid;
				}
			}
			if($postdata['taskStatus'] == 1){
				$info = Db::name('sxpay_income')->where('business_code',$postdata['mno'])->find();
				if($info['taskStatus'] != 1){
					$reqData = [];
					$reqData['mno'] = $postdata['mno'];
					$reqData['mecAuthority'] = '01';
					$rs = \app\customs\Sxpay::merchantSetup($info['aid'],$reqData,$info['mchkey']);
				}
			}
		}
		if($postdata['isEspecial'] == '01' || $postdata['isEspecial'] === '00'){ //复核单
			$updata['isEspecial'] = $postdata['isEspecial'];
			$updata['suggestion2'] = $postdata['suggestion'];
			$updata['specialMerFlagEndTime'] = $postdata['specialMerFlagEndTime'];
		}
		Db::name('sxpay_income')->where('business_code',$postdata['mno'])->update($updata);
		die('{"code": "success","msg": "成功"}');
	}

}