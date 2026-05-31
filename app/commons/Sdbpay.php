<?php
namespace app\commons;
use think\facade\Db;
class Sdbpay {
	public static function build_wx($aid,$mid,$title,$ordernum,$price,$tablename,$notify_url='',$redirect_url,$openid='') {
		if(!$notify_url) $notify_url = PRE_URL.'/notify.php';
    	require_once (ROOT_PATH. '/app/commons/api/HmPayClient.php');
      	$aop = new \HmPayClient();
      	$payorder = Db::name('payorder')->where('ordernum',$ordernum)->find();
      	if ($payorder['sdpay']) {
      	    return ['status'=>1,'data'=>json_decode($payorder['sdpay'],true)];
      	}
      	$member = Db::name('member')->where('id',$mid)->find();
      	$appinfo = \app\commons\System::appinfo($aid,'wx');
        $notify_url = PRE_URL.'/notify.php';
        $apiMap = array(
            'pay_way'    => 'WECHAT',
            'create_ip' =>  request()->ip(),
            'create_time'   => date("YmdHis"), 
            'pay_type' =>  'JSAPI',
            'pay_mode_code' =>  'PAY_GZH',
            'mer_app_id'    => $appinfo['appid'],
            'mer_buyer_id'   => $openid,  
            'total_amount' => $price,
            'out_order_no'   => $ordernum,  
            'body'    => $title,
            'store_id' =>  "100001",
            'notify_url'   => $notify_url,  
        );
      
        $jsonResp = $aop->build_pay('trade.create',json_encode($apiMap, JSON_UNESCAPED_UNICODE));
        $verifyFlag = $aop->checkResponseSign($jsonResp, $aop->platRsaPublicKey, $aop->signType);
        $jsonResp['verify']= $verifyFlag;
        
        ll($jsonResp,'$jsonResp2');
        
        
        
        if ($jsonResp['code'] == "200") {
            $data = json_decode($jsonResp['data'],true);
            if ($data['pay_data']) {
                Db::name('payorder')->where('ordernum',$ordernum)->update(['sdpay'=>$data['pay_data']]);
                $params = json_decode($data['pay_data'],true);
                return ['status'=>1,'data'=>$params];
			}else {
			    return ['status'=>0,'msg'=>'系统参数错误2'];
			}
        } else {
            return ['status'=>0,'msg'=>$jsonResp['msg']];	
        }
	
	}
    public static function build_mp($aid,$mid,$title,$ordernum,$price,$tablename,$notify_url='',$redirect_url='') {
		if(!$notify_url) $notify_url = PRE_URL.'/notify.php';
    	require_once (ROOT_PATH. '/app/commons/api/HmPayClient.php');
      	$aop = new \HmPayClient();
      	$payorder = Db::name('payorder')->where('ordernum',$ordernum)->find();
      	if ($payorder['sdpay']) {
      	    return ['status'=>1,'data'=>json_decode($payorder['sdpay'],true)];
      	}
      	$member = Db::name('member')->where('id',$mid)->find();
      	$appinfo = \app\commons\System::appinfo($aid,'mp');
        $notify_url = PRE_URL.'/notify.php';
        $apiMap = array(
            'pay_way'    => 'WECHAT',
            'create_ip' =>  request()->ip(),
            'create_time'   => date("YmdHis"), 
            'pay_type' =>  'JSAPI',
            'pay_mode_code' =>  'PAY_GZH',
            'mer_app_id'    => $appinfo['appid'],
            'mer_buyer_id'   => $member['mpopenid'],  
            'total_amount' => $price,
            'out_order_no'   => $ordernum,  
            'body'    => $title,
            'store_id' =>  "100001",
            //'req_reserved'=> $aid.':'.$tablename.':alipay:'.$ordernum,
            'notify_url'   => $notify_url,  
            'redirect_url'   =>$redirect_url
        );
//       	$params["qj_DJPlan"] = json_encode(
// 		    array(
// 		     "headline"=> "点击查看详情", //个人姓名
// 		      "subheading_1"=> "商品名称:{$title}", //个人姓名 
// // 		        "subheading_2"=> "subheading_2", //个人姓名 
// // // 			"remark"=> "zidingyi", //个人姓名
// 			"hyperlink"=> $frontUrl,
// 		    "hyperlink_location"=> 'headline',
// 		));  //新增
        $jsonResp = $aop->build_pay('trade.create',json_encode($apiMap, JSON_UNESCAPED_UNICODE));
        $verifyFlag = $aop->checkResponseSign($jsonResp, $aop->platRsaPublicKey, $aop->signType);
        $jsonResp['verify']= $verifyFlag;
        
        if ($jsonResp['code'] == "200") {
            $data = json_decode($jsonResp['data'],true);
            if ($data['pay_data']) {
                Db::name('payorder')->where('ordernum',$ordernum)->update(['sdpay'=>$data['pay_data']]);
                $params = json_decode($data['pay_data'],true);
                ll($params,'$params');
                return ['status'=>1,'data'=>$params];
			}else {
			    return ['status'=>0,'msg'=>'系统参数错误2'];
			}
        } else {
            return ['status'=>0,'msg'=>$jsonResp['msg']];	
        }
	
	}
	public static function query($ordernum) {
    	require_once (ROOT_PATH. '/app/common/api/HmPayClient.php');
      	$aop = new \HmPayClient();
      
        $apiMap = array(
            'out_order_no'   => $ordernum,  
        );
        $jsonResp = $aop->build_pay('trade.query',json_encode($apiMap, JSON_UNESCAPED_UNICODE),1);
        $verifyFlag = $aop->checkResponseSign($jsonResp, $aop->platRsaPublicKey, $aop->signType);
        $jsonResp['verify']= $verifyFlag;
        ll($jsonResp,'query');
        if ($jsonResp['code'] == "200") {
            $data = json_decode($jsonResp['data'],true);
            if ($data['sub_code']=='SUCCESS') {
                return ['status'=>1,'url'=>$data['cashier_url']];
			}else {
			    return ['status'=>0,'msg'=>'系统参数错误2'];
			}
        } else {
            return ['status'=>0,'msg'=>$jsonResp['msg']];	
        }
	}

	 public static function build_wxpay($aid,$mid,$title,$ordernum,$price,$tablename,$notify_url='',$redirect_url='',$platform) {
	     $platform = 'app';
		if(!$notify_url) $notify_url = PRE_URL.'/notify.php';
    	require_once (ROOT_PATH. '/app/commons/api/HmPayClient.php');
      	$aop = new \HmPayClient();
      	$payorder = Db::name('payorder')->where('ordernum',$ordernum)->find();
      	if ($payorder['hmali']) {
      	   // return ['status'=>1,'url'=>$payorder['hmali']];
      	}
        $redirect_url = m_url('pagesExt/pay/hmpay');
        if ($platform=='app') {
            $pay_extra_list = [array("func_code"=>"01010005","sub_app_id"=>"wx7c4da03958064a73","gh_ori_id"=>"gh_c5ceaaacceb0","path_url"=> "gd4f0yh","mini_program_type"=>"1",)];
        } else {
            $pay_extra_list = [array("func_code"=>"01010005","sub_app_id"=>"wx899fd5","sub_user_id"=> "gd4f0yh")];
        }
        
        $apiMap = array(
            'create_ip' =>  request()->ip(),
            'create_time'   => date("YmdHis"), 
            'total_amount' => $price,
            'out_order_no'   => $ordernum,  
            'body'    => $title,
            'store_id' =>  "100001",
            'pay_extra_list' =>$pay_extra_list,
            'notify_url'   => $notify_url,  
            'redirect_url'   =>$redirect_url.'?host=hmpayfudexinjy',  
            'func_code_list'   =>$platform=='app'? ['01010005']:['01010002'],    //01020004   //01020002  //01010002
            'sd_cashier_type'   =>$platform=='app'?'SDK':'H5',
            'meta_option'   =>'[{"s":"Android","n":"","id":"","sc":""},{"s":"IOS","n":"","id":"","sc":""}]'
        );
        ll(json_encode($apiMap, JSON_UNESCAPED_UNICODE),'$apiMap2');
        $jsonResp = $aop->build_pay('trade.create.cashier',json_encode($apiMap, JSON_UNESCAPED_UNICODE),1);
        $verifyFlag = $aop->checkResponseSign($jsonResp, $aop->platRsaPublicKey, $aop->signType);
        $jsonResp['verify']= $verifyFlag;
      
        if ($jsonResp['code'] == "200") {
            $data = json_decode($jsonResp['data'],true);
            if ($data['sub_code']=='SUCCESS') {
                Db::name('payorder')->where('ordernum',$ordernum)->update(['hmali'=>$data['cashier_url']]);
                return ['status'=>1,'url'=>$data['cashier_url']];
			}else {
			    return ['status'=>0,'msg'=>'系统参数错误2'];
			}
        } else {
            return ['status'=>0,'msg'=>$jsonResp['msg']];	
        }
	}	
    public static function build_alipay($aid,$mid,$title,$ordernum,$price,$tablename,$notify_url='',$redirect_url='',$platform) {
		if(!$notify_url) $notify_url = PRE_URL.'/notify.php';
    	require_once (ROOT_PATH. '/app/commons/api/HmPayClient.php');
      	$aop = new \HmPayClient();
      	$payorder = Db::name('payorder')->where('ordernum',$ordernum)->find();
      	if ($payorder['hmali']) {
      	    return ['status'=>1,'url'=>$payorder['hmali']];
      	}
        $redirect_url = m_url('pagesExt/pay/hmpay');
        $apiMap = array(
            'create_ip' =>  request()->ip(),
            'create_time'   => date("YmdHis"), 
            'total_amount' => $price,
            'out_order_no'   => $ordernum,  
            'body'    => $title,
            'store_id' =>  "100001",
            //'req_reserved'=> $aid.':'.$tablename.':alipay:'.$ordernum,
            'notify_url'   => $notify_url,  
            'redirect_url'   =>$redirect_url.'?host=hmpayfudexinjy',  
            'func_code_list'   =>$platform=='app'? ['01020004']:['01020002'],    //01020004   //01020002
            'sd_cashier_type'   =>$platform=='app'?'SDK':'H5',
            'jump_scheme'   =>"fudexinjyhmpay://hmpayfudexinjy", 
            'meta_option'   =>'[{"s":"Android","n":"","id":"","sc":""},{"s":"IOS","n":"","id":"","sc":""}]'
        );
        ll(json_encode($apiMap, JSON_UNESCAPED_UNICODE),'$apiMap2');
        $jsonResp = $aop->build_pay('trade.create.cashier',json_encode($apiMap, JSON_UNESCAPED_UNICODE),1);
        $verifyFlag = $aop->checkResponseSign($jsonResp, $aop->platRsaPublicKey, $aop->signType);
        $jsonResp['verify']= $verifyFlag;
        ll($jsonResp,'$jsonResp2');
        if ($jsonResp['code'] == "200") {
            $data = json_decode($jsonResp['data'],true);
            if ($data['sub_code']=='SUCCESS') {
                Db::name('payorder')->where('ordernum',$ordernum)->update(['hmali'=>$data['cashier_url']]);
                return ['status'=>1,'url'=>$data['cashier_url']];
			}else {
			    return ['status'=>0,'msg'=>'系统参数错误2'];
			}
        } else {
            return ['status'=>0,'msg'=>$jsonResp['msg']];	
        }
	}

	
	
	
	
	
	
// 	public static function build_alipay($aid,$mid,$title,$ordernum,$price,$tablename,$notify_url='') {
// 		if(!$notify_url) $notify_url = PRE_URL.'/notify.php';
//     	require_once (ROOT_PATH. '/app/common/api/HmPayClient.php');
//       	$aop = new \HmPayClient();
//       	$payorder = Db::name('payorder')->where('ordernum',$ordernum)->find();
//       	if ($payorder['hmali']) {
//       	    return ['status'=>1,'data'=>$payorder['hmali']];
//       	}
//       	$member = Db::name('member')->where('id',$mid)->find();
//       	$appinfo = \app\commons\System::appinfo($aid,'wx');
//         $notify_url = PRE_URL.'/notify.php';
        
        
//         $apiMap = array(
//             'pay_way'    => 'ALIPAY',
//             'create_ip' =>  request()->ip(),
//             'create_time'   => date("YmdHis"), 
//             'pay_type' =>  'MWEB',
//             'pay_mode_code' =>  'PAY_SCAN',
//             'total_amount' => $price,
//             'out_order_no'   => $ordernum,  
//             'body'    => $title,
//             'store_id' =>  "100001",
//             //'req_reserved'=> $aid.':'.$tablename.':alipay:'.$ordernum,
//             'notify_url'   => $notify_url,  
//             'redirect_url'   =>$redirect_url
//         );
       
//         $jsonResp = $aop->build_pay('trade.create',json_encode($apiMap, JSON_UNESCAPED_UNICODE));
//         $verifyFlag = $aop->checkResponseSign($jsonResp, $aop->platRsaPublicKey, $aop->signType);
//         $jsonResp['verify']= $verifyFlag;
        
//         ll($jsonResp,'$jsonResp');
        
        
        
//         if ($jsonResp['code'] == "200") {
//             $data = json_decode($jsonResp['data'],true);
//             if ($data['pay_data']) {
//                 Db::name('payorder')->where('ordernum',$ordernum)->update(['hmali'=>$data['pay_data']]);
//                 $params = json_decode($data['pay_data'],true);
//                 return ['status'=>1,'data'=>$params];
// 			}else {
// 			    return ['status'=>0,'msg'=>'系统参数错误2'];
// 			}
//         } else {
//             return ['status'=>0,'msg'=>$jsonResp['msg']];	
//         }
        
        
        
       
// 	}

	
	


	public static function build_bankbind($aid,$mid,$title,$ordernum,$price,$tablename,$notify_url='',$bankid) {
		if(!$notify_url) $notify_url = PRE_URL.'/notify.php';
		require_once (ROOT_PATH. '/app/common/api/sdpay.class.php');
		$config = include(ROOT_PATH.'/app/common/api/config.php');
		$bank = Db::name('member_bank')->where('mid',mid)->where('id',$bankid)->find();
		if (!$bank) {
		    return ['status'=>1,'msg'=>'银行卡信息不存在'];
		}
		$aop = new \sdpay();
        $notify_url = PRE_URL.'/notify.php';
        $apiMap = array( 
            'method' => 'sandPay.fastPay.apiUnionPay.applySmsBindCard',
            'url'    => '/fastPay/apiUnionPay/applySmsBindCard',
            'productId' => '00000018',
            'custom'   => false  
        );
        $orderCode = date('YmdHis').rand(100000,999999);
        if ($bank['bid']) {
            $body = array(
                'userId'=>'sd'.$mid,
                'bid'=>$bank['bid'],
                'orderCode'=>$orderCode,
                // 'extend'=>$aid.':'.$tablename.':bankpay:'.$ordernum,
            );
        } else {
		    $bank['applyNo'] = make_rand_code(2,6).date('YmdHis').rand(100000,999999);
		    Db::name('member_bank')->where('id',$bankid)->update(['applyNo'=>$bank['applyNo']]);
          	$body = array(
                'userId'=>'sd'.$mid,
                'applyNo'=>$bank['applyNo'],
                'cardNo'=>$bank['cardNo'],
                'userName'=>$bank['userName'],
                'phoneNo'=>$bank['phoneNo'],
                'certificateType'=>'01',
                'certificateNo'=>$bank['certificateNo'],
                'creditFlag'=>$bank['creditFlag'],
                'orderCode'=>$orderCode,
                // 'extend'=>$aid.':'.$tablename.':bankpay:'.$ordernum,
            );
            if ($body['creditFlag'] == 2) {
                $body['checkNo'] = $bank['checkNo'];
                $body['checkExpiry'] = date('Ym',strtotime($bank['checkExpiry']));
            }
        }
        $ret = $aop->request($apiMap,$body);
        $verifyFlag = $aop->verify($ret['data'], $ret['sign']);  
        $data=json_decode($ret['data'],true);
        $data['verify']=$verifyFlag;
        if ($data['head']['respCode'] == "000000") {
            Db::name('payorder')->where('ordernum',$ordernum)->update(['bkid'=>$bank['id'],'orderCode'=>$orderCode,'applyNo'=>$bank['applyNo']]);
            cache($ordernum.$bank['cardNo'].'_paycodetimes',time()+300);
            return ['status'=>1,'data'=>$data['body']];
        } else {
          return ['status'=>0,'msg'=>$data['head']['respMsg']];	
        }
	}
	
	public static function build_bank($aid,$mid,$title,$ordernum,$price,$tablename,$notify_url='',$smsCode) {
		if(!$notify_url) $notify_url = PRE_URL.'/notify.php';
		require_once (ROOT_PATH. '/app/common/api/sdpay.class.php');
		$config = include(ROOT_PATH.'/app/common/api/config.php');
      	$aop = new \sdpay();
        $notify_url = PRE_URL.'/notify.php';
        $orderCode = Db::name('payorder')->where('ordernum',$ordernum)->value('orderCode');
        $bkid = Db::name('payorder')->where('ordernum',$ordernum)->value('bkid');
        $apiMap = array(
            'method' => 'sandPay.fastPay.apiUnionPay.confirmBindPay',
            'url'    => '/fastPay/apiUnionPay/confirmBindPay',
            'productId' => '00000018',
            'custom'   => false  
        );
        $body = array(
            'userId'=>'sd'.$mid,
            'orderCode'=>$orderCode,
            'smsCode'=>$smsCode,
            'orderTime'=>date('YmdHis'),
            'totalAmount'=>str_pad($price * 100,12,"0",STR_PAD_LEFT),
            'subject'=>$title,
            'body'=> $tablename,
            'currencyCode'=> '156',
            'notifyUrl'=>$notify_url,
         //   'extend'=>$ordernum,
            'clearCycle'=>'3',
         
        );
        $ret = $aop->request($apiMap,$body);
        $verifyFlag = $aop->verify($ret['data'], $ret['sign']);  
        $data=json_decode($ret['data'],true);
        $data['verify']= $verifyFlag;
        
        
        
        if ($data['head']['respCode'] == "000000") {
            if ($data['body']['bid']) {
                Db::name('member_bank')->where('id',$bkid)->update(['bid'=>$data['body']['bid']]);
			}
            return ['status'=>1,'data'=>$data['body']];
        } else {
          return ['status'=>0,'msg'=>$data['head']['respMsg']];	
        }
	}
	
	
	
	
	
	
	
	
	
	
	

			//退款
	public static function refund2($productId,$aid,$platform,$ordernum,$totalprice,$refundmoney,$refund_desc='退款',$paynum) {
		if(!$refund_desc) $refund_desc = '退款';
		require_once (ROOT_PATH. '/app/common/api/sdpay.class.php');
		$aop = new \sdpay();
        $notify_url = PRE_URL.'/notify.php';
        $apiMap = array(
            'method' => 'sandpay.trade.refund',
            'url'    => '/gateway/api/order/refund',
            'productId' => $productId,
            'custom'   => false  
        );
	   $body = array(
            'orderCode'=>date('YmdHis').rand(100000,999999),
            'oriOrderCode'=>$paynum,
            'notifyUrl'=>$notify_url,
            'refundAmount'=>str_pad($refundmoney * 100,12,"0",STR_PAD_LEFT),
        );
        $ret = $aop->request($apiMap,$body);
        $res =json_decode($ret['data'],true); 
        if ($res['head']['respCode'] == "000000") {
            $data = [];
			$data['aid'] = $aid;
			$data['ordernum'] = $ordernum;
			$data['out_refund_no'] = $res['body']['tradeNo'];
			$data['totalprice'] = $totalprice;
			$data['refundmoney'] = $refundmoney;
			$data['createtime'] = date('Y-m-d H:i:s');
			$data['status'] = 1;
			$data['remark'] = $refund_desc;
			Db::name('wxrefund_log')->insert($data);
			return ['status'=>1,'msg'=>'退款成功','resp'=>$res['body']];
        } else {
           	$msg = $res['head']['respMsg'];
			$data = [];
			$data['aid'] = $aid;
			$data['mch_id'] = $config['mid'];
			$data['ordernum'] = $ordernum;
			$data['out_refund_no'] = $reqData['channelSsn'];
			$data['totalprice'] = $totalprice;
			$data['refundmoney'] = $refundmoney;
			$data['createtime'] = date('Y-m-d H:i:s');
			$data['status'] = 2;
			$data['remark'] = $refund_desc;
			$data['errmsg'] = $msg;
			Db::name('wxrefund_log')->insert($data);
			return ['status'=>0,'msg'=>$msg];	
        }
	}
   		//退款
	public static function refund($aid,$platform,$ordernum,$totalprice,$refundmoney,$refund_desc='退款',$paynum) {
		if(!$refund_desc) $refund_desc = '退款';
    	require_once (ROOT_PATH. '/app/common/api/HmPayClient.php');
      	$aop = new \HmPayClient();
    //   	$payorder = Db::name('payorder')->where('ordernum',$ordernum)->find();
      	$refund_ordernum = date('YmdHis').rand(100000,999999);
        $notify_url = PRE_URL.'/notify.php';
        $apiMap = array(
            'out_order_no'    => $ordernum,
            'order_create_time'   => date("YmdHis"), 
            'refund_amount' =>  $refundmoney,
            'refund_request_no' => $refund_ordernum,
            'notify_url'   => $notify_url,  
        );
       
        $refund = $aop->build_pay('trade.refund',json_encode($apiMap, JSON_UNESCAPED_UNICODE));
        // $verifyFlag = $aop->checkResponseSign($jsonResp, $aop->platRsaPublicKey, $aop->signType);
        $res =json_decode($refund['data'],true); 
        if ($refund['code'] == "200") {
            $data = [];
			$data['aid'] = $aid;
			$data['ordernum'] = $ordernum;
			$data['out_refund_no'] = $res['plat_trx_no'];
			$data['totalprice'] = $totalprice;
			$data['refundmoney'] = $refundmoney;
			$data['createtime'] = date('Y-m-d H:i:s');
			$data['status'] = 1;
			$data['remark'] = $refund_desc;
			 ll($data,'$data$data');
			Db::name('wxrefund_log')->insert($data);
			return ['status'=>1,'msg'=>'退款成功','resp'=>$res['body']];
        } else {
           	$msg = $refund['msg'];
			$data = [];
			$data['aid'] = $aid;
			$data['mch_id'] = $config['mid'];
			$data['ordernum'] = $ordernum;
			$data['out_refund_no'] = $refund_ordernum;
			$data['totalprice'] = $totalprice;
			$data['refundmoney'] = $refundmoney;
			$data['createtime'] = date('Y-m-d H:i:s');
			$data['status'] = 2;
			$data['remark'] = $refund_desc;
			$data['errmsg'] = $msg;
			Db::name('wxrefund_log')->insert($data);
			return ['status'=>0,'msg'=>$msg];	
        }
	}
}