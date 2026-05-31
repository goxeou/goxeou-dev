<?php


namespace app\commons;
use think\facade\Db;
class Alipay
{
	//支付宝小程序支付
	function build_alipay($aid,$bid,$mid,$title,$ordernum,$price,$tablename,$notify_url='',$return_url='',$more=1,$alih5=false,$trade_component_order_id='',$openid='',$openid_new=''){
		if(!$notify_url) $notify_url = PRE_URL.'/notify.php';
		$appinfo = \app\commons\System::appinfo($aid,'alipay');
		$member = Db::name('member')->where('id',$mid)->find();
		$isbusinesspay = false;
		if($appinfo['sxpay']==1){
			if($bid > 0){
				$business = Db::name('business')->where('id',$bid)->find();
				if($business['sxpay_mno']){
					$rs = \app\customs\Sxpay::build_alipay($aid,$bid,$mid,$title,$ordernum,$price,$tablename,$notify_url);
					return $rs;
				}
			}
			$rs = \app\customs\Sxpay::build_alipay($aid,$bid,$mid,$title,$ordernum,$price,$tablename,$notify_url);
			return $rs;
		}
        require_once(ROOT_PATH.'/extend/aop/AopClient.php');
		require_once(ROOT_PATH.'/extend/aop/AopCertification.php');
		require_once(ROOT_PATH.'/extend/aop/request/AlipayTradeCreateRequest.php');

		$aop = new \AopClient();
		$aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
		$aop->appId = $appinfo['appid'];
		$aop->rsaPrivateKey = $appinfo['appsecret'];
		$aop->alipayrsaPublicKey = $appinfo['publickey'];
		$aop->apiVersion = '1.0';
		$aop->signType = 'RSA2';
		$aop->postCharset = 'utf-8';
		$aop->format = 'json';


		$request = new \AlipayTradeCreateRequest ();
		$bizcontent = [];
		//兼容 openid 新规则
        if($member){
            if($appinfo['openid_set'] =='userid'){
                $bizcontent['buyer_id'] = $member['alipayopenid'];
            }else{
                $bizcontent['buyer_open_id'] = $member['alipayopenid_new'];
            }
        }else{
            if($appinfo['openid_set'] =='openid'){
                $bizcontent['buyer_open_id'] = $openid_new;
            }else{
                $bizcontent['buyer_id'] = $openid;
            }
        }
		$bizcontent['subject'] = mb_substr($title,0,42);
		$bizcontent['op_app_id'] = $appinfo['appid'];
		$bizcontent['out_trade_no'] = ''.$ordernum;
		$bizcontent['total_amount'] = $price;
		$bizcontent['product_code'] = 'JSAPI_PAY';
		$bizcontent['passback_params'] = urlencode($aid.':'.$tablename.':alipay');
        $extend_params = [];
        //交易组件order_id
        if($trade_component_order_id){
            $extend_params['trade_component_order_id'] = $trade_component_order_id;
        }
        if($extend_params){
            $bizcontent['extend_params'] = $extend_params;
        }
		if($tablename == 'shop'){
			$oglist = Db::name('shop_order_goods')->where('aid',$aid)->where('ordernum',$ordernum)->select()->toArray();
			if($oglist){
				$goodsDetail = [];
				foreach($oglist as $og){
					$goodsDetail[] = [
						'goods_id'=>$og['proid'].'_'.$og['ggid'],
						'goods_name'=>$og['name'].'('.$og['ggname'].')',
						'quantity'=>$og['num'],
						'price'=>$og['sell_price'],
					];
				}
				$bizcontent['goodsDetail'] = $goodsDetail;
			}
		}
        writeLog(json_encode(['appid'=>$appinfo['appid']]));
        writeLog(json_encode($bizcontent,JSON_UNESCAPED_UNICODE));
		$request->setBizContent(jsonEncode($bizcontent));

		$request->setNotifyUrl($notify_url);
		$result = $aop->execute ( $request);
		$responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
		$resultCode = $result->$responseNode->code;
        writeLog('-----order.trade-----');
        writeLog(json_encode([
            'code4'=>$result->$responseNode->code,
            'msg'=>$result->$responseNode->sub_msg,
            'sub_code'=>$result->$responseNode->sub_code,
        ]));
        writeLog('-----order.trade-----');
		if(!empty($resultCode)&&$resultCode == 10000){
			return ['status'=>1,'data'=>$result->$responseNode];
		} else {
			return ['status'=>0,'msg'=>$result->$responseNode->sub_msg];
		}
	}
	//支付宝H5支付
	function build_h5($aid,$bid,$mid,$title,$ordernum,$price,$tablename,$notify_url='',$return_url='',$more=1,$alih5=false,$trade_component_order_id='',$openid='',$openid_new=''){
		if(!$notify_url) $notify_url = PRE_URL.'/notify.php';
		if(!$return_url) $return_url = m_url('pages/my/usercenter', $aid);
		$appinfo = \app\commons\System::appinfo($aid,'h5');
		require_once(ROOT_PATH.'/extend/aop/AopClient.php');
		require_once(ROOT_PATH.'/extend/aop/AopCertification.php');
		require_once(ROOT_PATH.'/extend/aop/request/AlipayTradeWapPayRequest.php');

		$aop = new \AopClient();
		$aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
		if($more ==1 ){
			$aop->appId = $appinfo['ali_appid'];
			$aop->rsaPrivateKey = $appinfo['ali_privatekey'];
			$aop->alipayrsaPublicKey = $appinfo['ali_publickey'];
		}else{
			$aop->appId = $appinfo['ali_appid'.$more];
			$aop->rsaPrivateKey = $appinfo['ali_privatekey'.$more];
			$aop->alipayrsaPublicKey = $appinfo['ali_publickey'.$more];
		}
		$aop->apiVersion = '1.0';
		$aop->signType = 'RSA2';
		$aop->postCharset = 'utf-8';
		$aop->format = 'json';

		$request = new \AlipayTradeWapPayRequest ();
		$bizcontent = [];
		$bizcontent['body'] = mb_substr($title,0,42);
		$bizcontent['subject'] = mb_substr($title,0,42);
		$bizcontent['out_trade_no'] = ''.$ordernum;
		$bizcontent['total_amount'] = $price;
		$bizcontent['product_code'] = 'QUICK_WAP_WAY';
		$bizcontent['quit_url'] = $return_url;
		$bizcontent['passback_params'] = urlencode($aid.':'.$tablename.':h5:'.$more);

		if($tablename == 'shop'){
			$oglist = Db::name('shop_order_goods')->where('aid',$aid)->where('ordernum',$ordernum)->select()->toArray();
			if($oglist){
				$goodsDetail = [];
				foreach($oglist as $og){
					$goodsDetail[] = [
						'goods_id'=>$og['proid'].'_'.$og['ggid'],
						'goods_name'=>$og['name'].'('.$og['ggname'].')',
						'quantity'=>$og['num'],
						'price'=>$og['sell_price'],
					];
				}
				$bizcontent['goodsDetail'] = $goodsDetail;
			}
		}
		
		//echo $notify_url;die;
		$request->setBizContent(jsonEncode($bizcontent));
		$request->setNotifyUrl($notify_url);
		$request->setReturnUrl($return_url);
		$result = $aop->pageExecute($request);
		return ['status'=>1,'data'=>$result];
	}
	//支付宝APP支付
	function build_app($aid,$bid,$mid,$title,$ordernum,$price,$tablename,$notify_url='',$return_url='',$more=1,$alih5=false,$trade_component_order_id='',$openid='',$openid_new=''){
		if(!$notify_url) $notify_url = PRE_URL.'/notify.php';
		$appinfo = \app\commons\System::appinfo($aid,'app');
		require_once(ROOT_PATH.'/extend/aop/AopClient.php');
		require_once(ROOT_PATH.'/extend/aop/AopCertification.php');
		require_once(ROOT_PATH.'/extend/aop/request/AlipayTradeAppPayRequest.php');

		$aop = new \AopClient();
		$aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';

		if($more ==1 ){
			$aop->appId = $appinfo['ali_appid'];
			$aop->rsaPrivateKey = $appinfo['ali_privatekey'];
			$aop->alipayrsaPublicKey = $appinfo['ali_publickey'];
		}else{
			$aop->appId = $appinfo['ali_appid'.$more];
			$aop->rsaPrivateKey = $appinfo['ali_privatekey'.$more];
			$aop->alipayrsaPublicKey = $appinfo['ali_publickey'.$more];
		}

		$aop->apiVersion = '1.0';
		$aop->signType = 'RSA2';
		$aop->postCharset = 'utf-8';
		$aop->format = 'json';

		$request = new \AlipayTradeAppPayRequest();
		$bizcontent = [];
		$bizcontent['body'] = mb_substr($title,0,42);
		$bizcontent['subject'] = mb_substr($title,0,42);
		$bizcontent['out_trade_no'] = ''.$ordernum;
		$bizcontent['total_amount'] = $price;
		$bizcontent['product_code'] = 'QUICK_MSECURITY_PAY';
		$bizcontent['passback_params'] = urlencode($aid.':'.$tablename.':app:'.$more);

		if($tablename == 'shop'){
			$oglist = Db::name('shop_order_goods')->where('aid',$aid)->where('ordernum',$ordernum)->select()->toArray();
			if($oglist){
				$goodsDetail = [];
				foreach($oglist as $og){
					$goodsDetail[] = [
						'goods_id'=>$og['proid'].'_'.$og['ggid'],
						'goods_name'=>$og['name'].'('.$og['ggname'].')',
						'quantity'=>$og['num'],
						'price'=>$og['sell_price'],
					];
				}
				$bizcontent['goodsDetail'] = $goodsDetail;
			}
		}

		$request->setBizContent(jsonEncode($bizcontent));
		$request->setNotifyUrl($notify_url);
		$result = $aop->sdkExecute($request);
		return ['status'=>1,'data'=>$result];
	}
	//支付宝退款
	public static function refund($aid,$platform,$ordernum,$totalprice,$refundmoney,$refund_desc='退款',$bid=0){
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
                if(!$restaurant_sysset || $restaurant_sysset['business_cashdesk_alipay_type'] ==0){
                    return ['status'=>0,'msg'=>'支付宝收款已禁用'];
                }
                //3：独立收款
                if($restaurant_sysset['business_cashdesk_alipay_type'] ==3){
                    $appinfo['ali_appid'] = $bappinfo['ali_appid'];
                    $appinfo['ali_privatekey'] = $bappinfo['ali_privatekey'];
                    $appinfo['ali_publickey'] = $bappinfo['ali_publickey'];
                }
            }
        }else{
            $appinfo = \app\commons\System::appinfo($aid,$platform);
            if($appinfo['sxpay']){
                $sxpayrs = \app\customs\Sxpay::refund($aid,$platform,$ordernum,$totalprice,$refundmoney,$refund_desc,$bid);
                return  $sxpayrs;
            }
        }
		if($platform == 'h5' || $platform == 'app' ){
			$appinfo['appid'] = $appinfo['ali_appid'];
			$appinfo['appsecret'] = $appinfo['ali_privatekey'];
			$appinfo['publickey'] = $appinfo['ali_publickey'];
		}
        if($platform == 'cashdesk' || $platform =='restaurant_cashdesk'){
            $appinfo['appid'] = $appinfo['ali_appid'];
            $appinfo['appsecret'] = $appinfo['ali_privatekey'];
            $appinfo['publickey'] = $appinfo['ali_publickey'];
        }
		require_once(ROOT_PATH.'/extend/aop/AopClient.php');
		require_once(ROOT_PATH.'/extend/aop/AopCertification.php');
		require_once(ROOT_PATH.'/extend/aop/request/AlipayTradeRefundRequest.php');

		$aop = new \AopClient();
		$aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
		$aop->appId = $appinfo['appid'];
		$aop->rsaPrivateKey = $appinfo['appsecret'];
		$aop->alipayrsaPublicKey = $appinfo['publickey'];
		$aop->apiVersion = '1.0';
		$aop->signType = 'RSA2';
		$aop->postCharset = 'utf-8';
		$aop->format = 'json';

		$request = new \AlipayTradeRefundRequest();
		$bizcontent = [];
		$bizcontent['out_trade_no'] = $ordernum;
		$bizcontent['out_request_no'] = $ordernum. '_' . rand(1000, 9999);
		$bizcontent['refund_amount'] = $refundmoney*100/100;
		$bizcontent['refund_reason'] = $refund_desc;
		$request->setBizContent(jsonEncode($bizcontent));
		$result = $aop->execute($request);
		$responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
		$resultCode = $result->$responseNode->code;
		if(!empty($resultCode)&&$resultCode == 10000){
			return ['status'=>1,'msg'=>'退款成功'];
		}else{
			return ['status'=>0,'msg'=>$result->$responseNode->sub_msg];
		}
	}
    //支付宝付款码付款
    public static function build_scan($aid,$bid,$mid,$title,$ordernum,$price,$tablename,$notify_url='',$auth_code='',$platform='cashdesk'){      
        if(!$notify_url) $notify_url = PRE_URL.'/notify.php';
        if($platform =='cashdesk' || $platform =='restaurant_cashdesk'){
            $appinfo = Db::name('admin_setapp_'.$platform)->where('aid',$aid)->where('bid',0)->find();
            if($bid > 0){
                $bappinfo = Db::name('admin_setapp_'.$platform)->where('aid',$aid)->where('bid',$bid)->find();
                if($platform =='cashdesk'){
                    $restaurant_sysset = Db::name('business_sysset')->where('aid',$aid)->find();
                }else{
                    $restaurant_sysset = Db::name('restaurant_admin_set')->where('aid',$aid)->find();
                }
                if(!$restaurant_sysset || $restaurant_sysset['business_cashdesk_alipay_type'] ==0){
                    return ['status'=>0,'msg'=>'支付宝收款已禁用'];
                }
                //3：独立收款
                if($restaurant_sysset['business_cashdesk_alipay_type'] ==3){
                    $appinfo['ali_appid'] = $bappinfo['ali_appid'];
                    $appinfo['ali_privatekey'] = $bappinfo['ali_privatekey'];
                    $appinfo['ali_publickey'] = $bappinfo['ali_publickey'];
                }
            }
        } else{
            $appinfo = \app\commons\System::appinfo($aid,$platform);
        }
        require_once(ROOT_PATH.'/extend/aop/AopClient.php');
        require_once(ROOT_PATH.'/extend/aop/AopCertification.php');
        require_once(ROOT_PATH.'/extend/aop/request/AlipayTradePayRequest.php');
        $aop = new \AopClient ();
        $aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
        $aop->appId = $appinfo['ali_appid'];
        $aop->rsaPrivateKey = $appinfo['ali_privatekey'];
        $aop->alipayrsaPublicKey=$appinfo['ali_publickey'];
        $aop->apiVersion = '1.0';
        $aop->signType = 'RSA2';
        $aop->postCharset='utf-8';
        $aop->format='json';
        $object = new \stdClass();
        $object->out_trade_no = $ordernum;
        $object->total_amount = $price;
        $object->subject = mb_substr($title,0,42);
        $object->scene ='bar_code';
        $object->auth_code = $auth_code;
        
//        $bizcontent['passback_params'] = urlencode($aid.':'.$tablename.':app');
      ;
        $json = json_encode($object,JSON_UNESCAPED_UNICODE);
        $request = new \AlipayTradePayRequest();
        $request->setBizContent($json);
        $result = $aop->execute($request);
        \think\facade\Log::write('支付宝扫码支付日志：');
        \think\facade\Log::write(json_encode($result,JSON_UNESCAPED_UNICODE));
        $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        $resultCode = $result->$responseNode->code;
        if(!empty($resultCode)&&$resultCode == 10000){
            $return = [
                'trade_no' => $result->$responseNode->trade_no,
            ];
            return ['status'=>1,'msg'=>'付款成功','data' =>  $return];
        } else {
            return ['status'=>0,'msg'=>$result->$responseNode->sub_msg];
        }
    }
    public static function build_scan_query($aid,$ordernum,$trade_no){
        $appinfo = \app\commons\System::appinfo($aid,'app');
        require_once(ROOT_PATH.'/extend/aop/AopClient.php');
        require_once(ROOT_PATH.'/extend/aop/AopCertification.php');
        require_once(ROOT_PATH.'/extend/aop/request/AlipayTradeQueryRequest.php');
        $aop = new \AopClient ();
        $aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
        $aop->appId = $appinfo['ali_appid'];
        $aop->rsaPrivateKey = $appinfo['ali_privatekey'];
        $aop->alipayrsaPublicKey=$appinfo['ali_publickey'];
        $aop->apiVersion = '1.0';
        $aop->signType = 'RSA2';
        $aop->postCharset='utf-8';
        $aop->format='json';
        $request = new \AlipayTradeQueryRequest ();
        $bizcontent = [];
        $bizcontent['out_trade_no'] =''.$ordernum;
        $request->setBizContent(jsonEncode($bizcontent));
        $result = $aop->execute ( $request);
        $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        $resultCode = $result->$responseNode->code;
        if(!empty($resultCode)&&$resultCode == 10000){
            $return = [
                'trade_no' => $result->$responseNode->trade_no,
            ];
            return ['status'=>1,'msg'=>'成功','data' =>  $return];
        } else {
            return ['status'=>0,'msg'=>$result->$responseNode->sub_msg];
        }
    }

   	public static function transfers($aid,$ordernum,$money,$order_title,$identity,$name,$remark='账户提现'){
	   $set = Db::name('admin_set')->where('aid',$aid)->find();
	   if(!$remark) $remark = '打款';
        require_once (ROOT_PATH.'/extend/aop/AopCertClientNew.php');
        require_once (ROOT_PATH.'/extend/aop/AlipayConfig.php');
        require_once (ROOT_PATH.'/extend/aop/request/AlipayFundTransUniTransferRequest.php');
        $alipayConfig = new \AlipayConfig();
        $alipayConfig->setPrivateKey($set['ali_privatekey']);
        $alipayConfig->setServerUrl('https://openapi.alipay.com/gateway.do');
        $alipayConfig->setAppId($set['ali_appid']);
        $alipayConfig->setCharset('UTF-8');
        $alipayConfig->setSignType('RSA2');
        $alipayConfig->setFormat('json');
        $alipayConfig->setAlipayPublicCertPath($set['ali_publickey']);
        $alipayConfig->setAppCertPath(ROOT_PATH.str_replace(PRE_URL.'/','',$set['ali_apppublickey']));
        $alipayConfig->setRootCertPath(ROOT_PATH.str_replace(PRE_URL.'/','',$set['ali_rootcert']));
        $aop = new \AopCertClientNew($alipayConfig);
        $aop->isCheckAlipayPublicCert = true;
        $request = new \AlipayFundTransUniTransferRequest();
        $bizcontent = [];
		$bizcontent['out_biz_no'] = $ordernum;
		$bizcontent['trans_amount'] = $money*100/100;
		$bizcontent['biz_scene'] = "DIRECT_TRANSFER";
		$bizcontent['product_code'] = "TRANS_ACCOUNT_NO_PWD";
		$payee_info = [];
		$payee_info['identity'] = $identity;
		$payee_info['identity_type']= 'ALIPAY_LOGON_ID';
		$payee_info['name'] = $name;
		$bizcontent['payee_info'] = $payee_info;
		$bizcontent['order_title'] = $order_title;
		$bizcontent['remark'] = $remark;
		$request->setBizContent(jsonEncode($bizcontent));
        $responseResult = $aop->execute($request);
        $responseApiName = str_replace(".","_",$request->getApiMethodName())."_response";
        $response = $responseResult->$responseApiName;
        if(!empty($response->code)&&$response->code==10000){
            return ['status'=>1,'msg'=>'打款成功'];
        }
        else{
            return ['status'=>0,'msg'=>$responseResult->$responseApiName->sub_msg];
        }
	}

    /*+++++++++++++++++++++++++++++支付宝交易组件接口 Start+++++++++++++++++++++++++++++++++++++++*/
    //支付宝交易组件订单创建
    //https://opendocs.alipay.com/mini/54f80876_alipay.open.mini.order.create?pathHash=b9743ab7&ref=api&scene=common
    public static function pluginOrderCreate($aid,$orderid,$mid,$title,$ordernum,$price,$tablename,$source_id=''){
        $appinfo = \app\commons\System::appinfo($aid,'alipay');
        $member = Db::name('member')->where('id',$mid)->find();
        require_once(ROOT_PATH.'/extend/aop/AopClient.php');
        require_once(ROOT_PATH.'/extend/aop/request/AlipayOpenMiniOrderCreateRequest.php');

        $bizcontent = [];
        //兼容 openid 新规则
        if($member['alipayopenid']){
            $bizcontent['buyer_id'] = $member['alipayopenid'];
        }else{
//            $bizcontent['buyer_open_id'] = $member['alipayopenid_new'];
        }
        $bizcontent['out_order_id'] = ''.$ordernum;;
        $bizcontent['title'] = $title;
        $bizcontent['merchant_biz_type'] = 'KX_SHOPPING';
        if($source_id){
            $bizcontent['source_id'] = $source_id;
        }
        $bizcontent['path'] = "/pagesExt/order/detail?id={$orderid}";//小程序订单详情链接
        $goodsDetail = [];
        if($tablename == 'shop' || $tablename=='shop_hb'){
            $oglist = Db::name('shop_order_goods')->where('aid',$aid)->where('ordernum',$ordernum)->select()->toArray();
            if($oglist){
                foreach($oglist as $og){
                    $goodsDetail[] = [
                        'goods_id'=>$og['proid'].'_'.$og['ggid'],
                        'goods_name'=>$og['name'].'('.$og['ggname'].')',
                        'item_cnt'=>$og['num'],
                        'sale_price'=>$og['sell_price'],
                    ];
                }
            }
        }
        $orderdetail = [];
        $orderdetail['item_infos'] = $goodsDetail;
        $orderdetail['price_info'] = ['order_price'=>$price];
        $bizcontent['order_detail'] = $orderdetail;//订单信息
        $aop = new \AopClient();
        $aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
        $aop->appId = $appinfo['appid'];
        $aop->rsaPrivateKey = $appinfo['appsecret'];
        $aop->alipayrsaPublicKey = $appinfo['publickey'];
        $aop->apiVersion = '1.0';
        $aop->signType = 'RSA2';
        $aop->postCharset = 'utf-8';
        $aop->format = 'json';
        $request = new \AlipayOpenMiniOrderCreateRequest();
        writeLog('--------plugin order biz----------');
        writeLog(jsonEncode($bizcontent));
        writeLog('--------plugin order biz----------');
        $request->setBizContent(jsonEncode($bizcontent));
        $responseResult = $aop->execute($request);
        $responseApiName = str_replace(".","_",$request->getApiMethodName())."_response";
        $response = $responseResult->$responseApiName;
        writeLog('--------------plugin order result-----------');
        writeLog(json_encode([
            'code'=>$response->code,
            'sub_msg'=>$response->sub_msg??$response->msg,
            'order_id'=>$response->order_id,
            'out_order_id'=>$response->out_order_id,
        ]));
        writeLog('--------------plugin order result-----------');
        if(!empty($response->code)&&$response->code==10000){
            return ['status'=>1,'msg'=>'ok','order_id'=>$response->order_id,'out_order_id'=>$response->out_order_id];
        }else{
            $sub_msg = $response->sub_msg?$response->sub_msg:'';
            return ['status'=>0,'msg'=>'组件订单创建失败','sub_msg'=>$sub_msg];
        }
    }

    //交易组件订单发货同步
    public static function pluginOrderSend($orderid,$tablename='shop'){
        $order = Db::name('shop_order')->where('id',$orderid)->find();
        $aid = $order['aid'];
        $mid = $order['mid'];
        $ordernum = $order['ordernum'];
        if(strpos($ordernum, '_')!==false){
            $ordernum = explode('-',$ordernum)[0];
        }
        $appinfo = \app\commons\System::appinfo($aid,'alipay');
        $member = Db::name('member')->where('id',$mid)->find();
        require_once(ROOT_PATH.'/extend/aop/AopClient.php');
        require_once(ROOT_PATH.'/extend/aop/AopCertification.php');
        require_once(ROOT_PATH.'/extend/aop/request/AlipayOpenMiniOrderDeliverySendRequest.php');
        $aop = new \AopClient();
        $aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
        $aop->appId = $appinfo['appid'];
        $aop->rsaPrivateKey = $appinfo['appsecret'];
        $aop->alipayrsaPublicKey = $appinfo['publickey'];
        $aop->apiVersion = '1.0';
        $aop->signType = 'RSA2';
        $aop->postCharset = 'utf-8';
        $aop->format = 'json';

        $bizcontent = [];
        //兼容 openid 新规则
        if($member['alipayopenid']){
            $bizcontent['user_id'] = $member['alipayopenid'];
        }else{
//            $bizcontent['buyer_open_id'] = $member['alipayopenid_new'];
        }
        $goodsDetail = [];
        if($tablename == 'shop' || $tablename=='shop_hb'){
            $oglist = Db::name('shop_order_goods')->where('aid',$aid)->where('id',$orderid)->select()->toArray();
            if($oglist){
                foreach($oglist as $og){
                    $goodsDetail[] = [
                        'out_item_id'=>$og['proid'].'_'.$og['ggid'],
                        'out_sku_id'=>$og['ggid'],
                        'item_cnt'=>$og['num'],
                        'goods_id'=>$og['proid'],
                    ];
                }
            }
        }
        $delivery = [];
        //'express_com'=>$express_com,'express_no'=>$express_no,'express_ogids'=>$express_ogids,'express_content'=>$express_content,'express_isbufen'=>$express_isbufen

        $delivery[] = [
            'delivery_id'=>getExpressTag($order['express_com']),//快递公司ID
            'waybill_id'=>$order['express_no'],//快递单号
            'item_info_list'=>$goodsDetail
        ];

        $bizcontent['out_order_id'] = ''.$ordernum;
        $bizcontent['finish_all_delivery'] = $order['express_isbufen']?0:1;//0: 未发完, 1:已发完
        $bizcontent['ship_done_time'] = date('Y-m-d H:i:s',time());//发货时间
        $bizcontent['delivery_list'] = $delivery;
        writeLog('--------plugin send--------');
        writeLog(json_encode($bizcontent,JSON_UNESCAPED_UNICODE));
        writeLog('--------plugin send--------');
        $request = new \AlipayOpenMiniOrderDeliverySendRequest();
        $request->setBizContent(jsonEncode($bizcontent));
        $result = $aop->execute( $request);
        $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        $response = $result->$responseNode;
        $resultCode = $response->code;
        writeLog('--------------plugin order send result-----------');
        writeLog(json_encode([
            'code'=>$response->code,
            'sub_msg'=>$response->sub_msg??$response->msg
        ]));
        writeLog('--------------plugin order send result-----------');
        if(!empty($resultCode) && $resultCode == 10000){
            return ['status'=>1,'data'=>$response];
        } else {
            return ['status'=>0,'msg'=>$response->sub_msg?$response->sub_msg:''];
        }
    }

    //交易组件订单确认收货
    public static function pluginOrderConfirm($aid,$mid,$ordernum){
        $appinfo = \app\commons\System::appinfo($aid,'alipay');
        $member = Db::name('member')->where('id',$mid)->find();
        require_once(ROOT_PATH.'/extend/aop/AopClient.php');
        require_once(ROOT_PATH.'/extend/aop/AopCertification.php');
        require_once(ROOT_PATH.'/extend/aop/request/AlipayOpenMiniOrderDeliveryReceiveRequest.php');
        $aop = new \AopClient();
        $aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
        $aop->appId = $appinfo['appid'];
        $aop->rsaPrivateKey = $appinfo['appsecret'];
        $aop->alipayrsaPublicKey = $appinfo['publickey'];
        $aop->apiVersion = '1.0';
        $aop->signType = 'RSA2';
        $aop->postCharset = 'utf-8';
        $aop->format = 'json';

        $bizcontent = [];
        //兼容 openid 新规则
        if($member['alipayopenid']){
            $bizcontent['user_id'] = $member['alipayopenid'];
        }else{
            //如需启用，需使用$appinfo['openid_set'] 进行判断
//            $bizcontent['buyer_open_id'] = $member['alipayopenid_new'];
        }
        $bizcontent['out_order_id'] = ''.$ordernum;
        writeLog('--------plugin receive--------');
        writeLog(json_encode($bizcontent,JSON_UNESCAPED_UNICODE));
        writeLog('--------plugin receive--------');
        $request = new \AlipayOpenMiniOrderDeliveryReceiveRequest();
        $request->setBizContent(jsonEncode($bizcontent));
        $result = $aop->execute( $request);
        $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        $response = $result->$responseNode;
        $resultCode = $response->code;
        writeLog('--------------plugin order send result-----------');
        writeLog(json_encode([
            'code'=>$response->code,
            'sub_msg'=>$response->sub_msg??$response->msg,
        ]));
        writeLog('--------------plugin order send result-----------');
        if(!empty($resultCode) && $resultCode == 10000){
            return ['status'=>1,'data'=>$response];
        } else {
            return ['status'=>0,'msg'=>$response->sub_msg?$response->sub_msg:''];
        }
    }

    //交易组件订单状态改变
    public static function pluginOrderStatusChange($aid,$mid){

    }
    //交易组件订单查询
    public static function pluginOrderQuery($aid,$mid,$ordernum){
        $appinfo = \app\commons\System::appinfo($aid,'alipay');
        $member = Db::name('member')->where('id',$mid)->find();
        require_once(ROOT_PATH.'/extend/aop/AopClient.php');
        require_once(ROOT_PATH.'/extend/aop/AopCertification.php');
        require_once(ROOT_PATH.'/extend/aop/request/AlipayOpenMiniOrderQueryRequest.php');
        $aop = new \AopClient();
        $aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
        $aop->appId = $appinfo['appid'];
        $aop->rsaPrivateKey = $appinfo['appsecret'];
        $aop->alipayrsaPublicKey = $appinfo['publickey'];
        $aop->apiVersion = '1.0';
        $aop->signType = 'RSA2';
        $aop->postCharset = 'utf-8';
        $aop->format = 'json';

        $bizcontent = [];
        //兼容 openid 新规则
        if($member['alipayopenid']){
            $bizcontent['user_id'] = $member['alipayopenid'];
        }else{
//            $bizcontent['buyer_open_id'] = $member['alipayopenid_new'];
        }
        $bizcontent['out_order_id'] = ''.$ordernum;
        writeLog('--------plugin order query--------');
        writeLog(json_encode($bizcontent,JSON_UNESCAPED_UNICODE));
        writeLog('--------plugin order query--------');
        $request = new \AlipayOpenMiniOrderQueryRequest();
        $request->setBizContent(jsonEncode($bizcontent));
        $result = $aop->execute( $request);
        $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        $resultCode = $result->$responseNode->code;
        if(!empty($resultCode) && $resultCode == 10000){
            return ['status'=>1,'data'=>$result->$responseNode];
        } else {
            return ['status'=>0,'msg'=>$result->$responseNode->sub_msg];
        }
    }
    /*+++++++++++++++++++++++++++++支付宝交易组件接口 End+++++++++++++++++++++++++++++++++++++++*/

    /*+++++++++++++++++++++++++++++支付宝消息通知 Start+++++++++++++++++++++++++++++++++++++++*/
    public static function sendTemplateMessage($aid,$mid,$templatecontent =[]){
        }
    /*+++++++++++++++++++++++++++++支付宝消息通知 End+++++++++++++++++++++++++++++++++++++++*/
}