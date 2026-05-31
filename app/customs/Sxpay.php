<?php



namespace app\customs;
use think\facade\Db;
use think\facade\Log;
//文档 https://paas.tianquetech.com/docs/#/api/jhzftyxd

class Sxpay
{
	//创建微支付 微信公众号
	public static function build_mp($aid,$bid,$mid,$title,$ordernum,$price,$tablename,$notify_url='',$repeat=0){
		if(!$notify_url) $notify_url = PRE_URL.'/notify.php';
		$member = Db::name('member')->where('id',$mid)->find();
		$package = array();
		$appinfo = \app\commons\System::appinfo($aid,'mp');
		$isbusinesspay = false;
		if($bid > 0){
			$business = Db::name('business')->where('id',$bid)->find();
			if($business['sxpay_mno']){
				$appinfo['sxpay_mno'] = $business['sxpay_mno'];
				$appinfo['sxpay_mchkey'] = $business['sxpay_mchkey'];
				$isbusinesspay = true;
			}
		}
		if(!$appinfo['sxpay_mno']){
			return ['status'=>0,'msg'=>'请设置商户编号'];
		}
		if(!$appinfo['sxpay_mchkey']){
			return ['status'=>0,'msg'=>'请设置支付密钥'];
		}

		$appid = $appinfo['appid'];
		$openid = $member[platform.'openid'];
		
		$reqData = [];
		$reqData['mno'] = $appinfo['sxpay_mno']; //商户编号
		$reqData['ordNo'] = $ordernum; //商户订单号
		$reqData['amt'] = $price;
		$reqData['payType'] = 'WECHAT';
		$reqData['payWay'] = '02';
		$reqData['timeExpire'] = '1440';
		$reqData['subject'] = mb_substr($title,0,42);
		$reqData['trmIp'] = request()->ip();
		$reqData['subAppid'] = $appid;  //微信子公众号
		$reqData['notifyUrl'] = $notify_url;
		$reqData['userId'] = $openid;
		$reqData['extend'] = $aid.':'.$tablename.':sxpaymp:'.md5($tablename.$ordernum.$appinfo['sxpay_mchkey']);
		if($isbusinesspay) $reqData['extend'] .= ':'.$bid;
		$is_default_fenzhang = true;//走默认分账
        $fenzhang = Db::name('sxpay_fenzhang')->where('aid',$aid)->where('business_code',$reqData['mno'])->where('status',1)->find();
		if($fenzhang && $is_default_fenzhang){
			$fenzhangdata = json_decode($fenzhang['fenzhangdata'],true);
			if($fenzhangdata){
				$fusruleId = [];
				foreach($fenzhangdata as $fz){
					$issetfzmoney = false;
					if($issetfzmoney && $fzmoney){
						$fzprice = $fzmoney;
					}else{
						$fzpercent = floatval($fz['percent']);
						$fzprice = round($fzpercent * 0.01 * $reqData['amt'],2);
					}
					if($fzprice > 0){
						$fusruleId[] = ['allotValue'=>$fzprice,'mno'=>$fz['business_code']];
					}
				}
				if($fusruleId){
					$reqData['fusruleId'] = $fusruleId;
				}
			}
			\think\facade\Log::write($reqData);
		}
		Db::name('payorder')->where('aid',$aid)->where('ordernum',$ordernum)->update(['issxpay'=>1,'createtime'=>time()]);

		$rs = self::postapi($aid,'build',$reqData,$appinfo['sxpay_mchkey']);
		//\think\facade\Log::write($rs);
		
		if($rs['code'] == '0000' && $rs['respData']['bizCode'] == '0000'){
			cache('sxpay_'.$ordernum,$rs,1400);
		}
		if($rs['code'] != '0000'){
			if($repeat==0 && $rs['code'] == '0002'){
				$rs = cache('sxpay_'.$ordernum);
				if(!$rs){
                    $payorder = Db::name('payorder')->where('aid',$aid)->where('ordernum',$ordernum)->find();
                    if($payorder){
                        unset($payorder['id']);
                        $newordernum = \app\commons\Common::generateOrderNo($aid,$tablename);
                        $payorder['ordernum'] = $newordernum;
                        $payorder['createtime'] = time();
                        $payorder['id'] = Db::name('payorder')->insertGetId($payorder);
                        if(!$payorder['id']){
                            \think\facade\Log::write('up---');
                            return ['status'=>0,'msg'=>$rs['msg']];
                        }
                    }
					if($tablename == 'shop_hb'){
						$up1 = Db::name('shop_order')->where('aid',$aid)->where('ordernum',$ordernum)->update(['ordernum'=>$newordernum,'payorderid'=>$payorder['id']]);
						if(!$up1){
							\think\facade\Log::write('up1---');
							return ['status'=>0,'msg'=>$rs['msg']];
						}
                        $changenum = ['aid'=>$payorder['aid'],'bid'=>$payorder['bid'],'mid'=>$payorder['mid'],'tablename'=>$tablename,
                                      'orderid'=>$payorder['orderid'],'ordernum'=>$ordernum,'ordernum_new'=>$newordernum];
                        Db::name('ordernum_change_log')->insert($changenum);
						$orderlist = Db::name('shop_order')->where('ordernum','like',$ordernum.'%')->select()->toArray();
						foreach($orderlist as $order){
							$thisordernum = $newordernum.'_'.explode('_',$order['ordernum'])[1];
							Db::name('shop_order')->where('aid',$aid)->where('id',$order['id'])->update(['ordernum'=>$thisordernum,'payorderid'=>$payorder['id']]);
							Db::name('shop_order_goods')->where('aid',$aid)->where('orderid',$order['id'])->update(['ordernum'=>$thisordernum]);
						}
					}else{
						$up2 = Db::name($tablename.'_order')->where('aid',$aid)->where('ordernum',$ordernum)->update(['ordernum'=>$newordernum,'payorderid'=>$payorder['id']]);
						if(!$up2){
							\think\facade\Log::write('up2---');
							return ['status'=>0,'msg'=>$rs['msg']];
						}
                        $changenum = ['aid'=>$payorder['aid'],'bid'=>$payorder['bid'],'mid'=>$payorder['mid'],'tablename'=>$tablename.'_order',
                                      'orderid'=>$payorder['orderid'],'ordernum'=>$ordernum,'ordernum_new'=>$newordernum];
                        Db::name('ordernum_change_log')->insert($changenum);
						if(\app\commons\Order::hasOrderGoodsTable($tablename)){
							Db::name($tablename.'_order_goods')->where('aid',$aid)->where('ordernum',$ordernum)->update(['ordernum'=>$newordernum]);
						}
					}
					return self::build_mp($aid,$bid,$mid,$title,$newordernum,$price,$tablename,$notify_url,1);
				}
			}else{
				return ['status'=>0,'msg'=>$rs['msg']];
			}
		}
		if($rs['respData']['bizCode'] != '0000'){
			return ['status'=>0,'msg'=>$rs['respData']['bizMsg']?$rs['respData']['bizMsg']:$rs['msg']];
		}
		$respData = $rs['respData'];
		$wOpt = [];
		$wOpt['appId'] = $respData['payAppId'];
		$wOpt['timeStamp'] = $respData['payTimeStamp'];
		$wOpt['nonceStr'] = $respData['paynonceStr'];
		$wOpt['package'] = $respData['payPackage'];
		$wOpt['signType'] = $respData['paySignType'];
		$wOpt['paySign'] = $respData['paySign'];
		
		
		return ['status'=>1,'data'=>$wOpt];
	}
	//创建微支付 微信小程序 https://paas.tianquetech.com/docs/#/api/jhzftyxd
	public static function build_wx($aid,$bid,$mid,$title,$ordernum,$price,$tablename,$notify_url='',$repeat=0,$platform=''){
		if(!$notify_url) $notify_url = PRE_URL.'/notify.php';
		$member = Db::name('member')->where('id',$mid)->find();
		$package = array();
		$appinfo = \app\commons\System::appinfo($aid,'wx');
		$isbusinesspay = false;
		if($bid > 0){
			$business = Db::name('business')->where('id',$bid)->find();
			if($business['sxpay_mno']){
				$appinfo['sxpay_mno'] = $business['sxpay_mno'];
				$appinfo['sxpay_mchkey'] = $business['sxpay_mchkey'];
				$isbusinesspay = true;
			}
		}
		$appid = $appinfo['appid'];
        $openid = $member[platform.'openid'];
		if($platform=='app'){
            $appinfo_app = \app\commons\System::appinfo($aid,'app');
            $appinfo['sxpay_mno'] = $appinfo_app['sxpay_mno'];
            $appinfo['sxpay_mchkey'] = $appinfo_app['sxpay_mchkey'];
            $appinfo['sxpay_embedded'] = $appinfo_app['sxpay_embedded'];
        }

		if(!$appinfo['sxpay_mno']){
			return ['status'=>0,'msg'=>'请设置商户编号'];
		}
		if(!$appinfo['sxpay_mchkey']){
			return ['status'=>0,'msg'=>'请设置支付密钥'];
		}
		
		$reqData = [];
		$reqData['mno'] = $appinfo['sxpay_mno']; //商户编号
		$reqData['ordNo'] = $ordernum; //商户订单号
		$reqData['amt'] = $price;
		$reqData['timeExpire'] = '1440';
		$reqData['subject'] = mb_substr($title,0,42);
		$reqData['trmIp'] = request()->ip();
		$reqData['notifyUrl'] = $notify_url;
		$reqData['extend'] = $aid.':'.$tablename.':sxpaywx:'.md5($tablename.$ordernum.$appinfo['sxpay_mchkey']);
		if($isbusinesspay) $reqData['extend'] .= ':'.$bid;
        //随行付半屏小程序支付  https://paas.tianquetech.com/docs/#/api/xcxsyt
        if($appinfo['sxpay_embedded']){
            $reqData['appletSource'] = '01';//访问来源，枚举值：01 小程序
        }else{
            $reqData['payType'] = 'WECHAT';
            $reqData['payWay'] = '03';
            $reqData['subAppid'] = $appid;  //微信子公众号
            $reqData['userId'] = $openid;
        }
        $is_default_fenzhang = true;//走默认分账
        $fenzhang = Db::name('sxpay_fenzhang')->where('aid',$aid)->where('business_code',$reqData['mno'])->where('status',1)->find();
		if($fenzhang && $is_default_fenzhang){
			$fenzhangdata = json_decode($fenzhang['fenzhangdata'],true);
			if($fenzhangdata){
				$fusruleId = [];
				foreach($fenzhangdata as $fz){
					$issetfzmoney = false;
					if($issetfzmoney && $fzmoney){
						$fzprice = $fzmoney;
					}else{
						$fzpercent = floatval($fz['percent']);
						$fzprice = round($fzpercent * 0.01 * $reqData['amt'],2);
					}
					if($fzprice > 0){
						$fusruleId[] = ['allotValue'=>$fzprice,'mno'=>$fz['business_code']];
					}
				}
				if($fusruleId){
					$reqData['fusruleId'] = $fusruleId;
				}
			}
		}
		//\think\facade\Log::write($reqData);
		Db::name('payorder')->where('aid',$aid)->where('ordernum',$ordernum)->update(['issxpay'=>1,'createtime'=>time()]);

        $action = 'build';
        if($appinfo['sxpay_embedded']){
            $action = 'buildEmbedded';
        }
		$rs = self::postapi($aid,$action,$reqData,$appinfo['sxpay_mchkey']);
		
		if($rs['code'] == '0000' && $rs['respData']['bizCode'] == '0000' && $appinfo['sxpay_embedded'] != 1){//半屏小程序不能使用重复单号支付
			cache('sxpay_'.$ordernum,$rs,1400);
		}
//        Log::write([
//           'file'=>__FILE__,
//           'rs'=>$rs,
//           '$repeat'=>$repeat,
//           'cache'=>cache('sxpay_'.$ordernum)
//        ]);
		if($rs['code'] != '0000'){
            //单号重复
			if(($repeat==0 || $appinfo['sxpay_embedded'] == 1) && $rs['code'] == '0002'){
				$rs = cache('sxpay_'.$ordernum);
				if(!$rs){
                    //未缓存重新请求
                    $payorder = Db::name('payorder')->where('aid',$aid)->where('ordernum',$ordernum)->find();
                    if($payorder){
                        unset($payorder['id']);
                        $newordernum = \app\commons\Common::generateOrderNo($aid,$tablename);
                        $payorder['ordernum'] = $newordernum;
                        $payorder['createtime'] = time();
                        $payorder['id'] = Db::name('payorder')->insertGetId($payorder);
                        if(!$payorder['id']){
                            \think\facade\Log::write('up---');
                            return ['status'=>0,'msg'=>$rs['msg']];
                        }
                    }
					if($tablename == 'shop_hb'){
						$up1 = Db::name('shop_order')->where('aid',$aid)->where('ordernum',$ordernum)->update(['ordernum'=>$newordernum,'payorderid'=>$payorder['id']]);
						if(!$up1){
							\think\facade\Log::write('up1---');
							return ['status'=>0,'msg'=>$rs['msg']];
						}
                        $changenum = ['aid'=>$payorder['aid'],'bid'=>$payorder['bid'],'mid'=>$payorder['mid'],'tablename'=>$tablename,
                                      'orderid'=>$payorder['orderid'],'ordernum'=>$ordernum,'ordernum_new'=>$newordernum];
                        Db::name('ordernum_change_log')->insert($changenum);
						$orderlist = Db::name('shop_order')->where('ordernum','like',$ordernum.'%')->select()->toArray();
						foreach($orderlist as $order){
							$thisordernum = $newordernum.'_'.explode('_',$order['ordernum'])[1];
							Db::name('shop_order')->where('aid',$aid)->where('id',$order['id'])->update(['ordernum'=>$thisordernum,'payorderid'=>$payorder['id']]);
							Db::name('shop_order_goods')->where('aid',$aid)->where('orderid',$order['id'])->update(['ordernum'=>$thisordernum]);
						}
					}else{
						$up2 = Db::name($tablename.'_order')->where('aid',$aid)->where('ordernum',$ordernum)->update(['ordernum'=>$newordernum,'payorderid'=>$payorder['id']]);
						if(!$up2){
							\think\facade\Log::write('up2---');
							return ['status'=>0,'msg'=>$rs['msg']];
						}
                        $changenum = ['aid'=>$payorder['aid'],'bid'=>$payorder['bid'],'mid'=>$payorder['mid'],'tablename'=>$tablename.'_order',
                                      'orderid'=>$payorder['orderid'],'ordernum'=>$ordernum,'ordernum_new'=>$newordernum];
                        Db::name('ordernum_change_log')->insert($changenum);
						if(\app\commons\Order::hasOrderGoodsTable($tablename)){
							Db::name($tablename.'_order_goods')->where('aid',$aid)->where('ordernum',$ordernum)->update(['ordernum'=>$newordernum]);
						}
					}
					return self::build_wx($aid,$bid,$mid,$title,$newordernum,$price,$tablename,$notify_url,1);
				}
			}else{
				return ['status'=>0,'msg'=>$rs['msg']];
			}
		}
		if($rs['respData']['bizCode'] != '0000'){
			return ['status'=>0,'msg'=>$rs['respData']['bizMsg']?$rs['respData']['bizMsg']:$rs['msg']];
		}
		$respData = $rs['respData'];
		$wOpt = [];
        $wOpt['sxpay'] = 1;
		$wOpt['appId'] = $respData['payAppId'];
		$wOpt['timeStamp'] = $respData['payTimeStamp'];
		$wOpt['nonceStr'] = $respData['paynonceStr'];
		$wOpt['package'] = $respData['payPackage'];
		$wOpt['signType'] = $respData['paySignType'];
		$wOpt['paySign'] = $respData['paySign'];
        //随行付半屏小程序支付  https://paas.tianquetech.com/docs/#/api/xcxsyt
        if($appinfo['sxpay_embedded']){
            $wOpt['appId'] = $respData['appId'];
            $wOpt['path'] = $respData['path'];
        }

		return ['status'=>1,'data'=>$wOpt];
	}

	//创建支付 支付宝小程序/支付宝内H5
	public static function build_alipay($aid,$bid,$mid,$title,$ordernum,$price,$tablename,$notify_url='',$platform='alipay',$repeat=0){
		if(!$notify_url) $notify_url = PRE_URL.'/notify.php';
		$member = Db::name('member')->where('id',$mid)->find();
		$package = array();
		if(!$platform){
			$platform='alipay';
		}
		$appinfo = \app\commons\System::appinfo($aid,$platform);
		if($platform == 'h5'){
			$appinfo['appid']        = $appinfo['ali_appid'];
			$appinfo['sxpay_mno']    = $appinfo['alisxpay_mno'];
			$appinfo['sxpay_mchkey'] = $appinfo['alisxpay_mchkey'];
		}
		$isbusinesspay = false;
		if($bid > 0){
			$business = Db::name('business')->where('id',$bid)->find();
			if($business['sxpay_mno']){
				$appinfo['sxpay_mno'] = $business['sxpay_mno'];
				$appinfo['sxpay_mchkey'] = $business['sxpay_mchkey'];
				$isbusinesspay = true;
			}
		}
		$appid = $appinfo['appid'];
		$openid = $member['alipayopenid'] && !empty($member['alipayopenid'])?$member['alipayopenid']:$member['alipayopenid_new'];
		if(!$appinfo['sxpay_mno']){
			return ['status'=>0,'msg'=>'请设置商户编号'];
		}
		if(!$appinfo['sxpay_mchkey']){
			return ['status'=>0,'msg'=>'请设置支付密钥'];
		}
		
		$reqData = [];
		$reqData['mno'] = $appinfo['sxpay_mno']; //商户编号
		$reqData['ordNo'] = $ordernum; //商户订单号
		$reqData['amt'] = $price;
		$reqData['payType'] = 'ALIPAY';
		$reqData['payWay'] = '02';
		$reqData['timeExpire'] = '1440';
		$reqData['subject'] = mb_substr($title,0,42);
		$reqData['trmIp'] = request()->ip();
		$reqData['notifyUrl'] = $notify_url;
		$reqData['userId'] = $openid;
		if($platform == 'h5'){
			$paytype = 'sxalih5';
		}else{
			$paytype = 'sxpayalipay';
		}
		$reqData['extend'] = $aid.':'.$tablename.':'.$paytype.':'.md5($tablename.$ordernum.$appinfo['sxpay_mchkey']);
		if($isbusinesspay) $reqData['extend'] .= ':'.$bid;

		$fenzhang = Db::name('sxpay_fenzhang')->where('aid',$aid)->where('business_code',$reqData['mno'])->where('status',1)->find();
		if($fenzhang){
			$fenzhangdata = json_decode($fenzhang['fenzhangdata'],true);
			if($fenzhangdata){
				$fusruleId = [];
				foreach($fenzhangdata as $fz){
					$fzpercent = floatval($fz['percent']);
					$fzprice = round($fzpercent * 0.01 * $reqData['amt'],2);
					if($fzprice > 0){
						$fusruleId[] = ['allotValue'=>$fzprice,'mno'=>$fz['business_code']];
					}
				}
				if($fusruleId){
					$reqData['fusruleId'] = $fusruleId;
				}
			}
		}
		//\think\facade\Log::write($reqData);

		Db::name('payorder')->where('aid',$aid)->where('ordernum',$ordernum)->update(['issxpay'=>1,'createtime'=>time()]);

		$rs = self::postapi($aid,'build',$reqData,$appinfo['sxpay_mchkey']);

		if($rs['code'] == '0000' && $rs['respData']['bizCode'] == '0000'){
			cache('sxpay_'.$ordernum,$rs,1400);
		}
		if($rs['code'] != '0000'){
			if($repeat==0 && $rs['code'] == '0002'){
				$rs = cache('sxpay_'.$ordernum);
				if(!$rs){
                    $payorder = Db::name('payorder')->where('aid',$aid)->where('ordernum',$ordernum)->find();
                    if($payorder){
                        unset($payorder['id']);
                        $newordernum = \app\commons\Common::generateOrderNo($aid,$tablename);
                        $payorder['ordernum'] = $newordernum;
                        $payorder['createtime'] = time();
                        $payorder['id'] = Db::name('payorder')->insertGetId($payorder);
                        if(!$payorder['id']){
                        //$up = Db::name('payorder')->where('aid',$aid)->where('ordernum',$ordernum)->update(['ordernum'=>$newordernum]);
                            \think\facade\Log::write('up---');
                            return ['status'=>0,'msg'=>$rs['msg']];
                        }
                    }
					if($tablename == 'shop_hb'){
						$up1 = Db::name('shop_order')->where('aid',$aid)->where('ordernum',$ordernum)->update(['ordernum'=>$newordernum,'payorderid'=>$payorder['id']]);
						if(!$up1){
							\think\facade\Log::write('up1---');
							return ['status'=>0,'msg'=>$rs['msg']];
						}
                        $changenum = ['aid'=>$payorder['aid'],'bid'=>$payorder['bid'],'mid'=>$payorder['mid'],'tablename'=>$tablename,
                                      'orderid'=>$payorder['orderid'],'ordernum'=>$ordernum,'ordernum_new'=>$newordernum];
                        Db::name('ordernum_change_log')->insert($changenum);
						$orderlist = Db::name('shop_order')->where('ordernum','like',$ordernum.'%')->select()->toArray();
						foreach($orderlist as $order){
							$thisordernum = $newordernum.'_'.explode('_',$order['ordernum'])[1];
							Db::name('shop_order')->where('aid',$aid)->where('id',$order['id'])->update(['ordernum'=>$thisordernum,'payorderid'=>$payorder['id']]);
							Db::name('shop_order_goods')->where('aid',$aid)->where('orderid',$order['id'])->update(['ordernum'=>$thisordernum]);
						}
					}else{
						$up2 = Db::name($tablename.'_order')->where('aid',$aid)->where('ordernum',$ordernum)->update(['ordernum'=>$newordernum,'payorderid'=>$payorder['id']]);
						if(!$up2){
							\think\facade\Log::write('up2---');
							return ['status'=>0,'msg'=>$rs['msg']];
						}
                        $changenum = ['aid'=>$payorder['aid'],'bid'=>$payorder['bid'],'mid'=>$payorder['mid'],'tablename'=>$tablename.'_order',
                                      'orderid'=>$payorder['orderid'],'ordernum'=>$ordernum,'ordernum_new'=>$newordernum];
                        Db::name('ordernum_change_log')->insert($changenum);
						if(\app\commons\Order::hasOrderGoodsTable($tablename)){
							Db::name($tablename.'_order_goods')->where('aid',$aid)->where('ordernum',$ordernum)->update(['ordernum'=>$newordernum]);
						}
					}
					return self::build_alipay($aid,$bid,$mid,$title,$newordernum,$price,$tablename,$notify_url,$platform,1);
				}
			}else{
				return ['status'=>0,'msg'=>$rs['msg']];
			}
		}
		if($rs['respData']['bizCode'] != '0000'){
			return ['status'=>0,'msg'=>$rs['respData']['bizMsg']?$rs['respData']['bizMsg']:$rs['msg']];
		}
		$respData = $rs['respData'];
		$wOpt = [];
		$wOpt['trade_no'] = $respData['source'];
		$wOpt['sxpay'] = 1;

		return ['status'=>1,'data'=>$wOpt,'$respData'=>$respData];
	}

	//创建  商户扫用户付款码
    public static function build_scan($aid,$bid,$title,$ordernum,$price,$tablename='wx',$auth_code){
        //根据授权码 判断是什么付款码
        if(!$auth_code){
            return ['status'=>0,'msg'=>'请扫付款码进行付款'];
        }
        $paytype ='';
        $wx_reg = '/^1[0-6][0-9]{16}$/';//微信 
        $ali_reg = '/^(?:2[5-9]|30)\d{14,22}$/';;//支付宝  
        $yl_reg = '/^62\d{17}$/';//银联
        if(preg_match($wx_reg,$auth_code)){
            $paytype = 'WECHAT';
        }elseif(preg_match($ali_reg,$auth_code)){
            $paytype = 'ALIPAY';
        }elseif(preg_match($yl_reg,$auth_code)){
            $paytype = 'UNIONPAY';
        }
        if($tablename =='cashdesk' || $tablename =='restaurant_cashdesk'){
            $appinfo = Db::name('admin_setapp_'.$tablename)->where('aid',$aid)->where('bid',0)->find();
            if($bid > 0){
                $bappinfo = Db::name('admin_setapp_'.$tablename)->where('aid',$aid)->where('bid',$bid)->find();
                if($tablename =='cashdesk'){
                    $restaurant_sysset = Db::name('business_sysset')->where('aid',$aid)->find();
                }else{
                    $restaurant_sysset = Db::name('restaurant_admin_set')->where('aid',$aid)->find();
                }
                if(!$restaurant_sysset || $restaurant_sysset['business_cashdesk_sxpay_type'] ==0){
                    return ['status'=>0,'msg'=>'随行付收款已禁用'];
                }
                //1:服务商
                if($restaurant_sysset['business_cashdesk_sxpay_type'] ==1){
                    $appinfo['sxpay_mno'] = $bappinfo['sxpay_sub_mno'];
                    $appinfo['sxpay_mchkey'] = $bappinfo['sxpay_sub_mchkey'];
                }
                //3：独立收款
                if($restaurant_sysset['business_cashdesk_sxpay_type'] ==3){
                    $appinfo['sxpay_mno'] = $bappinfo['sxpay_mno'];
                    $appinfo['sxpay_mchkey'] = $bappinfo['sxpay_mchkey'];
                }
            }
        }else{
            $appinfo = \app\commons\System::appinfo($aid,$tablename);
        }
        if($paytype==''){
            return ['status'=>0,'msg'=>'无效的付款码'];
        }
        if(!$appinfo['sxpay_mno']){
            return ['status'=>0,'msg'=>'请设置商户编号'];
        }
        if(!$appinfo['sxpay_mchkey']){
            return ['status'=>0,'msg'=>'请设置支付密钥'];
        }
        $reqData = [];
        $reqData['mno'] = $appinfo['sxpay_mno']; //商户编号
        $reqData['ordNo'] = $ordernum; //商户订单号
        $reqData['amt'] = $price;
        $reqData['authCode'] = $auth_code;//授权码
        $reqData['payType'] = $paytype;//付款方式
        $reqData['scene'] = '1';//支付场景
        $reqData['subject'] =  mb_substr($title,0,42);
        $reqData['trmIp'] = request()->ip();
        $fenzhang = Db::name('sxpay_fenzhang')->where('aid',$aid)->where('business_code',$reqData['mno'])->where('status',1)->find();
        if($fenzhang){
            $fenzhangdata = json_decode($fenzhang['fenzhangdata'],true);
            if($fenzhangdata){
                $fusruleId = [];
                foreach($fenzhangdata as $fz){
                    $fzpercent = floatval($fz['percent']);
                    $fzprice = round($fzpercent * 0.01 * $reqData['amt'],2);
                    if($fzprice > 0){
                        $fusruleId[] = ['allotValue'=>$fzprice,'mno'=>$fz['business_code']];
                    }
                }
                if($fusruleId){
                    $reqData['fusruleId'] = $fusruleId;
                }
            }
        }
        $rs = self::postapi($aid,'scan',$reqData,$appinfo['sxpay_mchkey']);
        if($rs['respData']['bizCode'] != '0000'){
            return ['status'=>0,'msg'=>$rs['respData']['bizMsg']?$rs['respData']['bizMsg']:$rs['msg']];
        }else{
            $return = ['trade_no' => $rs['respData']['transactionId']];
            return ['status'=>1,'msg'=>'成功','data' => $return,'platform' => $tablename];
        }
    }
	
	//查询订单
	public static function tradeQuery($payorder){

		$appinfo = \app\commons\System::appinfo($payorder['aid'],$payorder['platform']);
		$reqData = [];
		$reqData['mno'] = $appinfo['sxpay_mno']; //商户编号
        if($payorder['bid'] > 0){
            //是否使用了商家的随行付
            $business = Db::name('business')->where('id',$payorder['bid'])->find();
            if($business['sxpay_mno']){
                $reqData['mno'] = $business['sxpay_mno'];
                $appinfo['sxpay_mchkey'] = $business['sxpay_mchkey'];
            }
        }
		$reqData['ordNo'] = $payorder['ordernum']; //订单号

		$rs = self::postapi($payorder['aid'],'tradeQuery',$reqData,$appinfo['sxpay_mchkey']);

		if($rs['code'] != '0000'){
			return ['status'=>0,'msg'=>$rs['msg']];
		}
		if($rs['respData']['bizCode'] != '0000'){
			return ['status'=>0,'msg'=>$rs['respData']['bizMsg']?$rs['respData']['bizMsg']:$rs['msg']];
		}
		return ['status'=>1,'msg'=>'查询成功','data'=>$rs['respData']];
	}

	//退款
	public static function refund($aid,$platform,$ordernum,$totalprice,$refundmoney,$refund_desc='退款',$bid=0){
		if($platform =='cashdesk' || $platform == 'restaurant_cashdesk'){
            $appinfo = Db::name('admin_setapp_'.$platform)->where('aid',$aid)->where('bid',0)->find();
            if($bid > 0){
                $bappinfo = Db::name('admin_setapp_'.$platform)->where('aid',$aid)->where('bid',$bid)->find();
                if($platform =='cashdesk') {
                    $restaurant_sysset = Db::name('business_sysset')->where('aid', $aid)->find();
                }else{
                    $restaurant_sysset = Db::name('restaurant_admin_set')->where('aid',$aid)->find();
                }
                if(!$restaurant_sysset || $restaurant_sysset['business_cashdesk_sxpay_type'] ==0){
                    return ['status'=>0,'msg'=>'随行付收款已禁用'];
                }
                //1:服务商
                if($restaurant_sysset['business_cashdesk_sxpay_type'] ==1){
                    $appinfo['sxpay_mno'] = $bappinfo['sxpay_sub_mno'];
                    $appinfo['sxpay_mchkey'] = $bappinfo['sxpay_sub_mchkey'];
                }
                //3：独立收款
                if($restaurant_sysset['business_cashdesk_sxpay_type'] ==3){
                    $appinfo['sxpay_mno'] = $bappinfo['sxpay_mno'];
                    $appinfo['sxpay_mchkey'] = $bappinfo['sxpay_mchkey'];
                }
            }
        }else{
            $appinfo = \app\commons\System::appinfo($aid,$platform);
        }
        if($bid > 0){
        	//是否使用了商家的随行付
			$business = Db::name('business')->where('id',$bid)->find();
			if($business['sxpay_mno']){
				$appinfo['sxpay_mno'] = $business['sxpay_mno'];
				$appinfo['sxpay_mchkey'] = $business['sxpay_mchkey'];
			}
		}
		$reqData = [];
		$reqData['mno'] = $appinfo['sxpay_mno']; //商户编号
		$reqData['origOrderNo'] = $ordernum;
		$reqData['ordNo'] = $ordernum. '_' . rand(1000, 9999); //商户订单号
		$reqData['amt'] = $refundmoney;
		$reqData['refundReason'] = $refund_desc;
		$rs = self::postapi($aid,'refund',$reqData,$appinfo['sxpay_mchkey']);

		if($rs['code'] != '0000'){
			return ['status'=>0,'msg'=>$rs['msg']];
		}
		if($rs['respData']['bizCode'] != '0000'){
			return ['status'=>0,'msg'=>$rs['respData']['bizMsg']?$rs['respData']['bizMsg']:$rs['msg']];
		}
		
		//记录
		$data = [];
		$data['aid'] = $aid;
		$data['mch_id'] = $appinfo['sxpay_mno'];
		$data['ordernum'] = $ordernum;
		$data['out_refund_no'] = $reqData['ordNo'];
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
		return ['status'=>1,'msg'=>'退款成功','resp'=>$rs['respData']];
	}

	//商户入驻
	public static function income($aid,$reqData){
		$rs = self::postapi($aid,'income',$reqData);
		if($rs['code'] != '0000'){
			return ['status'=>0,'msg'=>$rs['msg']];
		}
		if($rs['respData']['bizCode'] != '0000'){
			return ['status'=>0,'msg'=>$rs['respData']['bizMsg']?$rs['respData']['bizMsg']:$rs['msg']];
		}
		return ['status'=>1,'msg'=>'提交成功','data'=>$rs['respData']];
	}
	//商户修改
	public static function modify($aid,$reqData,$mchkey){
		$rs = self::postapi($aid,'modify',$reqData,$mchkey);
		if($rs['code'] != '0000'){
			return ['status'=>0,'msg'=>$rs['msg']];
		}
		if($rs['respData']['bizCode'] != '0000'){
			return ['status'=>0,'msg'=>$rs['respData']['bizMsg']?$rs['respData']['bizMsg']:$rs['msg']];
		}
		return ['status'=>1,'msg'=>'提交成功','data'=>$rs['respData']];
	}
	//入驻结果查询
	public static function applyQuery($aid,$reqData,$mchkey){
		$rs = self::postapi($aid,'applyQuery',$reqData,$mchkey);
		if($rs['code'] != '0000'){
			return ['status'=>0,'msg'=>$rs['msg']];
		}
		if($rs['respData']['bizCode'] != '0000'){
			return ['status'=>0,'msg'=>$rs['respData']['bizMsg']?$rs['respData']['bizMsg']:$rs['msg']];
		}
		return ['status'=>1,'msg'=>'查询成功','data'=>$rs['respData']];
	}
	//修改结果查询
	public static function modifyQuery($aid,$reqData,$mchkey){
		$rs = self::postapi($aid,'modifyQuery',$reqData,$mchkey);
		if($rs['code'] != '0000'){
			return ['status'=>0,'msg'=>$rs['msg']];
		}
		if($rs['respData']['bizCode'] != '0000'){
			return ['status'=>0,'msg'=>$rs['respData']['bizMsg']?$rs['respData']['bizMsg']:$rs['msg']];
		}
		return ['status'=>1,'msg'=>'查询成功','data'=>$rs['respData']];
	}

	//设置微信子商户参数
	public static function addConf($aid,$reqData,$mchkey){
		$rs = self::postapi($aid,'addConf',$reqData,$mchkey);
		if($rs['code'] != '0000'){
			return ['status'=>0,'msg'=>$rs['msg']];
		}
		if($rs['respData']['bizCode'] != '0000'){
			return ['status'=>0,'msg'=>$rs['respData']['bizMsg']?$rs['respData']['bizMsg']:$rs['msg']];
		}
		return ['status'=>1,'msg'=>'设置成功'];
	}
	//查看配置 
	public static function viewConf($aid,$reqData,$mchkey){
		$rs = self::postapi($aid,'viewConf',$reqData,$mchkey);
		if($rs['code'] != '0000'){
			return ['status'=>0,'msg'=>$rs['msg']];
		}
		if($rs['respData']['bizCode'] != '0000'){
			return ['status'=>0,'msg'=>$rs['respData']['bizMsg']?$rs['respData']['bizMsg']:$rs['msg']];
		}
		return ['status'=>1,'msg'=>'设置成功','data'=>$rs['respData']];
	}
	//签署协议
	public static function signxieyi($aid,$reqData,$mchkey){
		$rs = self::postapi($aid,'signxieyi',$reqData,$mchkey);
		if($rs['code'] != '0000'){
			return ['status'=>0,'msg'=>$rs['msg']];
		}
		if($rs['respData']['bizCode'] != '0000'){
			return ['status'=>0,'msg'=>$rs['respData']['bizMsg']?$rs['respData']['bizMsg']:$rs['msg']];
		}
		return ['status'=>1,'msg'=>'设置成功','signUrl'=>$rs['respData']['signUrl']];
	}
	//实名认证
	public function shiming($aid,$reqData,$mchkey,$type=0){
		if(!$type){
			$rs = self::postapi($aid,'shiming',$reqData,$mchkey);
		}else if($type == 1){
			$rs = self::postapi($aid,'alishiming',$reqData,$mchkey);
		}else{
			return ['status'=>0,'msg'=>'认证类型错误'];
		}

		if($rs['code'] != '0000'){
			return ['status'=>0,'msg'=>$rs['msg']];
		}
		if($rs['respData']['bizCode'] != '0000'){
			return ['status'=>0,'msg'=>$rs['respData']['bizMsg']?$rs['respData']['bizMsg']:$rs['msg']];
		}
		return ['status'=>1,'msg'=>'提交成功,请等待审核'];
	}
	//实名认证查询
	public function shimingQuery($aid,$reqData,$mchkey,$type=0){
		if(!$type){
			$rs = self::postapi($aid,'shimingQuery',$reqData,$mchkey);
		}else if($type == 1){
			$rs = self::postapi($aid,'alishimingQuery',$reqData,$mchkey);
		}else{
			return ['status'=>0,'msg'=>'认证查询错误'];
		}

		if($rs['code'] != '0000'){
			return ['status'=>0,'msg'=>$rs['msg']];
		}
		if($rs['respData']['bizCode'] != '0000'){
			return ['status'=>0,'msg'=>$rs['respData']['bizMsg']?$rs['respData']['bizMsg']:$rs['msg']];
		}
		return ['status'=>1,'msg'=>'查询成功','data'=>$rs['respData']];
	}
	//生成密钥
	public static function updateMchkey($aid,$reqData,$mchkey){
		$rs = self::postapi($aid,'updateMchkey',$reqData,$mchkey);
		return $rs;
	}
	//获取银行列表
	public static function getjiesuanbanklist($aid,$reqData){
		$rs = self::postapi($aid,'getjiesuanbanklist',$reqData);
		return $rs;
	}
	//获取省市区编码
	public static function getareacode($aid,$reqData){
		$rs = self::postapi($aid,'getareacode',$reqData);
		return $rs;
	}
	
	public static function signxieyifz($aid,$reqData,$mchkey){
		$rs = self::postapi($aid,'signxieyifz',$reqData,$mchkey);
		if($rs['code'] != '0000'){
			return ['status'=>0,'msg'=>$rs['msg']];
		}
		if($rs['respData']['bizCode'] != '0000'){
			return ['status'=>0,'msg'=>$rs['respData']['bizMsg']?$rs['respData']['bizMsg']:$rs['msg']];
		}
		return ['status'=>1,'msg'=>'设置成功','retUrl'=>$rs['respData']['retUrl']];
	}


    /**
     * 分账 商户特殊申请 https://paas.tianquetech.com/docs/#/api/shtssqtj
     * @param $reqData
     * @param $mchkey
     * @return array
     */
    public static function specialApplication($aid,$reqData,$mchkey){
        $rs = self::postapi($aid,'specialApplication',$reqData,$mchkey);
        if($rs['code'] != '0000'){
            return ['status'=>0,'msg'=>$rs['msg']];
        }
        if($rs['respData']['bizCode'] != '0000'){
            return ['status'=>0,'msg'=>$rs['respData']['bizMsg']?$rs['respData']['bizMsg']:$rs['msg']];
        }
        return ['status'=>1,'msg'=>'设置成功','id'=>$rs['respData']['id'],'resp'=>$rs];
    }

    /**
     * 商户特殊申请查询 https://paas.tianquetech.com/docs/#/api/shtssqcx
     * @param $reqData
     * @param $mchkey
     * @return array
     */
    public static function specialApplicationApplyQuery($aid,$reqData,$mchkey){
        $rs = self::postapi($aid,'specialApplicationApplyQuery',$reqData,$mchkey);
        if($rs['code'] != '0000'){
            return ['status'=>0,'msg'=>$rs['msg']];
        }
        if($rs['respData']['bizCode'] != '0000'){
            return ['status'=>0,'msg'=>$rs['respData']['bizMsg']?$rs['respData']['bizMsg']:$rs['msg']];
        }
        return ['status'=>1,'msg'=>'查询成功','data'=>$rs['respData']];
    }
    /**
     * 商户特殊申请撤销 https://paas.tianquetech.com/docs/#/api/shtssqchexiao
     * @param $reqData
     * @param $mchkey
     * @return array
     */
    public static function specialApplicationApplyBack($aid,$reqData,$mchkey){
        $rs = self::postapi($aid,'specialApplicationApplyBack',$reqData,$mchkey);
        if($rs['code'] != '0000'){
            return ['status'=>0,'msg'=>$rs['msg']];
        }
        if($rs['respData']['bizCode'] != '0000'){
            return ['status'=>0,'msg'=>$rs['respData']['bizMsg']?$rs['respData']['bizMsg']:$rs['msg']];
        }
        return ['status'=>1,'msg'=>'设置成功'];
    }

    /**
     * 分账设置 https://paas.tianquetech.com/docs/#/api/fzsz
     * @param $reqData
     * @param $mchkey
     * @return array
     */
    public static function setMnoArrayfz($aid,$reqData,$mchkey){
		$rs = self::postapi($aid,'setMnoArrayfz',$reqData,$mchkey);
		if($rs['code'] != '0000'){
			return ['status'=>0,'msg'=>$rs['msg']];
		}
		if($rs['respData']['bizCode'] != '0000'){
			return ['status'=>0,'msg'=>$rs['respData']['bizMsg']?$rs['respData']['bizMsg']:$rs['msg']];
		}
		return ['status'=>1,'msg'=>'设置成功'];
	}
	public static function merchantSetup($aid,$reqData,$mchkey){
		$rs = self::postapi($aid,'merchantSetup',$reqData,$mchkey);
		if($rs['code'] != '0000'){
			return ['status'=>0,'msg'=>$rs['msg']];
		}
		if($rs['respData']['bizCode'] != '0000'){
			return ['status'=>0,'msg'=>$rs['respData']['bizMsg']?$rs['respData']['bizMsg']:$rs['msg']];
		}
		return ['status'=>1,'msg'=>'设置成功'];
	}

    //服务商设置
    public static function getMerchantSet($aid)
    {
        $set = [];
        if(empty($set) || $set['status'] != 1){
            $dbsxpayset = Db::name('sysset')->where('name','sxpayset')->value('value');
            $dbsxpayset = json_decode($dbsxpayset,true);
            $set = $dbsxpayset;
        }

        return $set ? $set : [];
    }

	//上传图片 https://paas.tianquetech.com/docs/#/guide/tplxmb
    /**
     * @param $picurl
     * @param $type 图片类型码表 见接口文档
     * @return array|mixed
     */
	public static function uploadimg($picurl,$type){
		$url = 'https://openapi.tianquetech.com/merchant/uploadPicture';
		$data = [];
        $dbsxpayset = \app\customs\Sxpay::getMerchantSet(aid);
		if($dbsxpayset && $dbsxpayset['orgId']){
			$data['orgId'] = $dbsxpayset['orgId'];
		}else{
			$data['orgId'] = '52439584';
		}
		$data['reqId'] = random(16);
		$data['pictureType'] = $type;

		$picurl = \app\commons\Pic::tolocal($picurl);
		$picpath = ROOT_PATH.str_replace(PRE_URL.'/','',$picurl);

		$data['file'] = new \CurlFile($picpath);
		$header = ["content-type:multipart/form-data"];
		$ch = curl_init ();
		curl_setopt ( $ch, CURLOPT_URL, $url);
		curl_setopt ( $ch, CURLOPT_CUSTOMREQUEST, "POST" );
		curl_setopt ( $ch, CURLOPT_HTTPHEADER, $header);
		curl_setopt ( $ch, CURLOPT_FOLLOWLOCATION, 1 );
		curl_setopt ( $ch, CURLOPT_AUTOREFERER, 1 );
		curl_setopt ( $ch, CURLOPT_POSTFIELDS, $data );
		curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, true );

		$info = curl_exec ( $ch );
		if (curl_errno ( $ch )) {
			return ['status'=>0,'msg'=>curl_error($ch)];
		}
		curl_close ( $ch );
		$rs = json_decode($info,true);
		if($rs['code'] != '0000'){
			return ['status'=>0,'msg'=>$rs['msg']];
		}
		if($rs['respData']['bizCode'] != '0000'){
			return ['status'=>0,'msg'=>$rs['respData']['bizMsg']?$rs['respData']['bizMsg']:$rs['msg']];
		}
		return $rs['respData']['PhotoUrl'];
	}

	public static function postapi($aid, $action,$reqData,$mchkey=''){
		
		if(!in_array($action,['getjiesuanbanklist','getareacode'])){
            $dbsxpayset = \app\customs\Sxpay::getMerchantSet($aid);
			if($dbsxpayset && $dbsxpayset['orgId']){
                if(in_array($action,['specialApplication','specialApplicationApplyQuery','specialApplicationApplyBack']) && $dbsxpayset['orgIdOne']){
                    $dbsxpayset['orgId'] = $dbsxpayset['orgIdOne'];//一级机构号
                }
				if($action == 'updateMchkey'){
					$newmchkey = strtolower(random(32));
					Db::name('sxpay_income')->where('business_code',$reqData['business_code'])->update(['mchkey'=>$newmchkey]);
					return ['status'=>1,'data'=>['mchkey'=>$newmchkey]];
				}
				if($action == 'income'){
					$reqData['qrcodeList'] = [['rateType'=>'01','rate'=>$dbsxpayset['feepercent']],['rateType'=>'02','rate'=>$dbsxpayset['feepercent']],['rateType'=>'06','rate'=>$dbsxpayset['feepercent']],['rateType'=>'07','rate'=>'0.6']];
				}
				$rs = self::curlpost($action,$reqData,$dbsxpayset['orgId'],$dbsxpayset['privateKey'],$dbsxpayset['signType']);
				$rs = json_decode($rs,true);
				if($action == 'income'){
					if($rs['code'] == '0000' && $rs['respData']['bizCode'] == '0000'){
						$rs['respData']['mchkey'] = strtolower(random(32));
					}
				}
				return $rs;
			}
		}

		if($mchkey){
			ksort($reqData);
			$string1 = '';
			foreach ($reqData as $key => $v) {
				if(is_array($v)){
					$string1 .= "{$key}=".json_encode($v)."&";
				}else{
                    if($v!=='' && $v!==null){
                        $string1 .= "{$key}={$v}&";
                    }
				}
			}
			$string1 .= "key=".$mchkey;
			$reqData['sign'] = strtoupper(md5($string1));
		}
		$config = include(ROOT_PATH.'config.php');
        $authkey = $config['authkey'];
		$domain = str_replace('http://','https://',request()->domain());
		$domain = str_replace('https://','',$domain);
		$domain = str_replace('http://','',$domain);


	//	$rs = curl_post('https:///?s=Api/'.$action.'&domain='.$domain.'&authkey='.$authkey,['data'=>json_encode($reqData)]);
        
		$rs = json_decode($rs,true);
		return $rs;
	}

	public static function curlpost($apipath,$reqData,$orgId,$privateKey,$signType){
		$apiArr = [
			'income'=>'/merchant/income',
			'modify'=>'/merchant/editMerchantInfo',
			'applyQuery'=>'/merchant/queryMerchantInfo',
			'modifyQuery'=>'/merchant/queryModifyResult',
			'addConf'=>'/merchant/weChatPaySet/addConf',
			'viewConf'=>'/merchant/weChatPaySet/queryConf',
			'signxieyi'=>'/merchant/elecSignature/openElecSignature',
			'shiming'=>'/merchant/realName/commitApply',
			'shimingQuery'=>'/merchant/realName/queryApplyInfo',
			'build'=>'/order/jsapiScan',
            'buildEmbedded'=>'/order/appletScanPre',//小程序收银台
			'tradeQuery'=>'/query/tradeQuery',
			'refund'=>'/order/refund',
			'signxieyifz'=>'/merchant/sign/getUrl',
			'setMnoArrayfz'=>'/query/ledger/setMnoArray',//https://paas.tianquetech.com/docs/#/api/fzsz
			'merchantSetup'=>'/merchant/merchantSetup',
            'scan'=>'/order/reverseScan',
            'alishiming'=>'/merchant/alipayRealName/commitApply',
            'alishimingQuery'=>'/merchant/alipayRealName/queryApplyInfo',
            'specialApplication'=>'/merchant/specialApplication/commitApply',//https://paas.tianquetech.com/docs/#/api/shtssqtj
            'specialApplicationApplyQuery'=>'/merchant/specialApplication/queryApplyInfo',
            'specialApplicationApplyBack'=>'/merchant/specialApplication/backApplyBill'
		];
		$url = 'https://openapi.tianquetech.com'.$apiArr[$apipath];

		$data = self::sign($reqData,$orgId,$privateKey,$signType);
		$reqStr = json_encode($data);

		$rs = curl_post($url,$reqStr,0,['content-type:application/json;charset=UTF-8']);
		//print_r($rs);exit;
//        Log::write([
//            'file'=>__FILE__,
//            'line'=>__LINE__,
//            'url'=>$url,
//            'data'=>$data,
//            'rs'=>$rs
//        ]);
		return $rs;
	}
	public static function sign($reqData,$orgId,$privateKey, $signType = "RSA"){
		if(!$signType) $signType = 'RSA';
		$data = [];
		$data['signType'] = $signType;
		$data['version'] = '1.0';
		$data['orgId'] = $orgId;
		$data['reqId'] = random(16);
		$data['reqData'] = $reqData;
		$data['timestamp'] = date('YmdHis');
		$rsaPriKeyStr = $privateKey;

		ksort($data);
        $stringToBeSigned = "";
        foreach($data as $k => $v){
            $isarray = is_array($v);
            if ($isarray) {
                $stringToBeSigned .= "$k" . "=" . json_encode($v, 320) . "&";
            } else {
                if($v!=='' && $v!==null){
                    $stringToBeSigned .= "$k" . "=" . "$v" . "&";
                }
//                $stringToBeSigned .= "$k" . "=" . "$v" . "&";
            }
        }
        unset ($k, $v);
        $stringToBeSigned = substr($stringToBeSigned, 0, strlen($stringToBeSigned) - 1);

         $res = "-----BEGIN RSA PRIVATE KEY-----\n" . wordwrap($rsaPriKeyStr, 64, "\n", true) . "\n-----END RSA PRIVATE KEY-----";
        ($res) or die('您使用的私钥格式错误，请检查RSA私钥配置');

        if ("RSA2" == $signType) {
            $signstatus = openssl_sign($stringToBeSigned, $sign, $res, OPENSSL_ALGO_SHA256);
        } else {
            $signstatus = openssl_sign($stringToBeSigned, $sign, $res);
        }
        if($signstatus === false){
            Log::write([
                'file'=>__FILE__,
                'line'=>__LINE__,
                'signstatus'=>$signstatus
            ],'error');
        }
        $data['sign'] = base64_encode($sign);
        return $data;
    }

    //经营类目
    static function mccCategoryConf(){
        $mccCdArr = [
            '5411'=>'生活百货/百货商城/超市（非平台类）',
            '5331'=>'生活百货/百货商城/杂货店',
            '5300'=>'生活百货/百货商城/会员制批量零售店',
            '5309'=>'生活百货/百货商城/国外代购及免税店',
            '5311'=>'生活百货/百货商城/平台类综合商城',
            '5715'=>'生活百货/百货商城/酒精饮料批发商（国际专用）',
            '5914'=>'生活百货/百货商城/成人用品/避孕用品/情趣内衣',
            '5998'=>'生活百货/百货商城/帐篷和遮阳篷商店',
            '5999'=>'生活百货/百货商城/其他专业零售店',
            '5983'=>'生活百货/百货商城/油品燃料经销',
            '5984'=>'生活百货/百货商城/烟花爆竹',
            '5399'=>'生活百货/百货商城/其他综合零售',
            '5611'=>'生活百货/服饰鞋包/男性服饰',
            '5621'=>'生活百货/服饰鞋包/女性成衣',
            '5631'=>'生活百货/服饰鞋包/配饰商店',
            '5651'=>'生活百货/服饰鞋包/内衣/家居服',
            '5661'=>'生活百货/服饰鞋包/鞋类',
            '5681'=>'生活百货/服饰鞋包/皮草皮具',
            '5691'=>'生活百货/服饰鞋包/高档时装及奢侈品',
            '5697'=>'生活百货/服饰鞋包/裁缝、修补、改衣制衣',
            '5698'=>'生活百货/服饰鞋包/假发等饰品',
            '5699'=>'生活百货/服饰鞋包/各类服装及饰物',
            '5137'=>'生活百货/服饰鞋包/制服与商务正装定制',
            '5139'=>'生活百货/服饰鞋包/鞋类销售平台（批发商）',
            '5948'=>'生活百货/服饰鞋包/行李箱包',
            '5945'=>'生活百货/母婴玩具/玩具、游戏用品',
            '5641'=>'生活百货/母婴玩具/母婴用品',
            '5997'=>'生活百货/美妆珠宝配饰/男士用品：剃须刀、烟酒具、瑞士军刀',
            '5977'=>'生活百货/美妆珠宝配饰/化妆品',
            '5944'=>'生活百货/美妆珠宝配饰/钟表店',
            '5094'=>'生活百货/美妆珠宝配饰/珠宝和金银饰品',
            '5946'=>'生活百货/数码家电/专业摄影器材',
            '5722'=>'生活百货/数码家电/家用电器',
            '5732'=>'生活百货/数码家电/数码产品及配件',
            '5045'=>'生活百货/数码家电/商用计算机及服务器',
            '4812'=>'生活百货/数码家电/手机、通讯设备销售',
            '5940'=>'生活百货/运动户外/自行车及配件',
            '5941'=>'生活百货/运动户外/体育用品/器材',
            '5655'=>'生活百货/运动户外/运动服饰',
            '5942'=>'生活百货/图书音像/书籍',
            '5994'=>'生活百货/图书音像/报纸、杂志',
            '5735'=>'生活百货/图书音像/音像制品',
            '5192'=>'生活百货/图书音像/书、期刊和报纸（批发商）',
            '5996'=>'生活百货/家居家纺建材/游泳、SPA、洗浴设备',
            '5949'=>'生活百货/家居家纺建材/家用纺织品',
            '5712'=>'生活百货/家居家纺建材/家具/家庭摆设',
            '5713'=>'生活百货/家居家纺建材/地板和地毯',
            '5714'=>'生活百货/家居家纺建材/窗帘、帷幕、室内装潢',
            '5718'=>'生活百货/家居家纺建材/壁炉、屏风',
            '5719'=>'生活百货/家居家纺建材/各种家庭装饰专营',
            '5200'=>'生活百货/家居家纺建材/大型仓储式家庭用品卖场',
            '5211'=>'生活百货/家居家纺建材/木材与建材商店',
            '5231'=>'生活百货/家居家纺建材/玻璃、油漆涂料、墙纸',
            '5251'=>'生活百货/家居家纺建材/家用五金工具',
            '5261'=>'生活百货/家居家纺建材/草坪和花园用品',
            '5193'=>'生活百货/家居家纺建材/花木栽种用品、苗木和花卉（批发商）',
            '5198'=>'生活百货/家居家纺建材/油漆、清漆用品（批发商）',
            '5131'=>'生活百货/家居家纺建材/布料、缝纫用品和其他纺织品（批发商）',
            '5039'=>'生活百货/家居家纺建材/未列入其他代码的建材（批发商）',
            '3002'=>'生活百货/家居家纺建材/大型企业批发',
            '5993'=>'生活百货/饮食保健/烟草/雪茄',
            '5921'=>'生活百货/饮食保健/酒类',
            '5811'=>'生活百货/饮食保健/宴会提供商',
            '5812'=>'生活百货/饮食保健/餐厅、订餐服务',
            '5813'=>'生活百货/饮食保健/酒吧、舞厅、夜总会',
            '5814'=>'生活百货/饮食保健/快餐店',
            '5815'=>'生活百货/饮食保健/咖啡厅、茶馆',
            '5880'=>'生活百货/饮食保健/校园团餐',
            '5881'=>'生活百货/饮食保健/综合团餐',
            '5422'=>'生活百货/饮食保健/肉、禽、蛋及水产品',
            '5423'=>'生活百货/饮食保健/水果店',
            '5441'=>'生活百货/饮食保健/糖果及坚果商店',
            '5451'=>'生活百货/饮食保健/乳制品/冷饮',
            '5462'=>'生活百货/饮食保健/面包糕点',
            '5466'=>'生活百货/饮食保健/茶叶',
            '5467'=>'生活百货/饮食保健/保健品',
            '5499'=>'生活百货/饮食保健/其他食品零售',
            '5992'=>'生活百货/文化玩乐宠物/花店',
            '5995'=>'生活百货/文化玩乐宠物/宠物及宠物用品',
            '5970'=>'生活百货/文化玩乐宠物/工艺美术用品',
            '5971'=>'生活百货/文化玩乐宠物/艺术品和画廊',
            '5972'=>'生活百货/文化玩乐宠物/邮票/纪念币',
            '5973'=>'生活百货/文化玩乐宠物/宗教物品',
            '5947'=>'生活百货/文化玩乐宠物/礼品、卡片、纪念品',
            '5950'=>'生活百货/文化玩乐宠物/瓷器、玻璃和水晶摆件',
            '5937'=>'生活百货/文化玩乐宠物/古玩复制品（赝品）',
            '5931'=>'生活百货/文化玩乐宠物/旧商品店、二手商品店',
            '5932'=>'生活百货/文化玩乐宠物/文物古董',
            '5733'=>'生活百货/文化玩乐宠物/乐器',
            '7993'=>'生活百货/文化玩乐宠物/家用电子游戏',
            '1520'=>'商业及生活服务/房地产/房地产开发商',
            '1711'=>'商业及生活服务/承包商（农业、建筑、出版）/空调类承包商',
            '1731'=>'商业及生活服务/承包商（农业、建筑、出版）/电器承包商',
            '1740'=>'商业及生活服务/承包商（农业、建筑、出版）/建筑材料承包商',
            '1750'=>'商业及生活服务/承包商（农业、建筑、出版）/木工承包商',
            '1761'=>'商业及生活服务/承包商（农业、建筑、出版）/金属产品承包商',
            '1771'=>'商业及生活服务/承包商（农业、建筑、出版）/混凝土承包商',
            '1799'=>'商业及生活服务/承包商（农业、建筑、出版）/其他工程承包商',
            '2741'=>'商业及生活服务/商业服务/出版印刷服务',
            '2791'=>'商业及生活服务/商业服务/刻版排版服务',
            '2842'=>'商业及生活服务/商业服务/清洁抛光服务',
            '5935'=>'商业及生活服务/商业服务/海上船只遇难救助',
            '5933'=>'商业及生活服务/金融服务/典当行',
            '4829'=>'商业及生活服务/金融服务/电汇和汇票服务',
            '4904'=>'商业及生活服务/公共事业/公共事业-清洁服务缴费',
            '4906'=>'商业及生活服务/公共事业/充电桩',
            '4909'=>'商业及生活服务/公共事业/有线电视缴费',
            '5310'=>'商业及生活服务/团购/团购及折扣店',
            '5962'=>'商业及生活服务/直销/旅游相关服务直销',
            '5963'=>'商业及生活服务/直销/上门直销（直销员）',
            '5964'=>'商业及生活服务/直销/目录直销平台',
            '5965'=>'商业及生活服务/直销/直销代理',
            '5966'=>'商业及生活服务/直销/电话外呼直销',
            '5967'=>'商业及生活服务/直销/电话接入直销',
            '5968'=>'商业及生活服务/直销/订阅订购服务',
            '5969'=>'商业及生活服务/直销/直销',
            '3003'=>'航旅交通/公共交通/铁路局（直属）',
            '4011'=>'航旅交通/公共交通/铁路货运',
            '4111'=>'航旅交通/公共交通/公共交通',
            '4112'=>'航旅交通/公共交通/铁路客运',
            '4113'=>'航旅交通/公共交通/ETC不停车自动缴费',
            '4114'=>'航旅交通/公共交通/MTC半自动车道收费',
            '4115'=>'航旅交通/公共交通/地铁',
            '4119'=>'航旅交通/公共交通/急救服务',
            '4121'=>'航旅交通/公共交通/出租车服务（TAXI）',
            '4131'=>'航旅交通/公共交通/长途公路客运',
            '4411'=>'航旅交通/公共交通/游轮及巡游航线服务',
            '4457'=>'航旅交通/公共交通/出租船只',
            '4468'=>'航旅交通/公共交通/船舶、海运服务提供商',
            '4214'=>'航旅交通/物流仓储/物流货运服务',
            '4215'=>'航旅交通/物流仓储/快递服务',
            '4225'=>'航旅交通/物流仓储/公共仓储、集装整理',
            '4511'=>'航旅交通/航空票务/航空公司',
            '4512'=>'航旅交通/航空票务/机票代理人',
            '4582'=>'航旅交通/航空票务/机场服务',
            '4722'=>'航旅交通/旅行住宿/旅行社和旅游服务',
            '4723'=>'航旅交通/旅行住宿/国际货运代理和报关行',
            '4733'=>'航旅交通/旅行住宿/大型旅游景点',
            '4789'=>'航旅交通/旅行住宿/未列入其他代码的运输服务',
            '7011'=>'航旅交通/旅行住宿/住宿服务（旅馆、酒店、汽车旅馆、度假村等）',
            '7012'=>'航旅交通/旅行住宿/度假用别墅服务',
            '7032'=>'航旅交通/旅行住宿/运动和娱乐露营',
            '7033'=>'航旅交通/旅行住宿/活动房车场及野营场所 ',
            '5943'=>'专业销售/办公用品/文具及办公用品',
            '5978'=>'专业销售/办公用品/打字设备、打印复印机、扫描仪',
            '5021'=>'专业销售/办公用品/办公及商务家具（批发商）',
            '5044'=>'专业销售/办公用品/办公、影印及微缩摄影器材（批发商）',
            '5046'=>'专业销售/办公用品/未列入其他代码的商用器材',
            '5111'=>'专业销售/办公用品/文具、办公用品、复印纸和书写纸（批发商）',
            '5051'=>'专业销售/工业产品/金属产品和服务（批发商）',
            '5065'=>'专业销售/工业产品/电气产品和设备',
            '5072'=>'专业销售/工业产品/五金器材及用品（批发商）',
            '5074'=>'专业销售/工业产品/管道及供暖设备',
            '5085'=>'专业销售/工业产品/工业设备和制成品',
            '5099'=>'专业销售/工业产品/其他工业耐用品',
            '5169'=>'专业销售/工业产品/化工产品',
            '5172'=>'专业销售/工业产品/石油及石油产品（批发商）',
            '5199'=>'专业销售/工业产品/其他工业原料和消耗品',
            '5013'=>'专业销售/汽车和运输工具/机动车供应及零配件（批发商）',
            '5271'=>'专业销售/汽车和运输工具/活动房车销售商',
            '5511'=>'专业销售/汽车和运输工具/汽车销售',
            '5521'=>'专业销售/汽车和运输工具/二手车销售',
            '5531'=>'专业销售/汽车和运输工具/汽车商店、家庭用品商店（国际专用）',
            '5532'=>'专业销售/汽车和运输工具/汽车轮胎经销',
            '5533'=>'专业销售/汽车和运输工具/汽车零配件',
            '5541'=>'专业销售/汽车和运输工具/加油站、服务站',
            '5542'=>'专业销售/汽车和运输工具/加油卡、加油服务',
            '5551'=>'专业销售/汽车和运输工具/船舶及配件销售',
            '5561'=>'专业销售/汽车和运输工具/拖车、篷车及娱乐用车',
            '5564'=>'专业销售/汽车和运输工具/轨道交通设备器材',
            '5565'=>'专业销售/汽车和运输工具/飞机及配件、航道设施',
            '5566'=>'专业销售/汽车和运输工具/运输搬运设备、起重装卸设备',
            '5571'=>'专业销售/汽车和运输工具/摩托车及配件',
            '5572'=>'专业销售/汽车和运输工具/电动车及配件',
            '5592'=>'专业销售/汽车和运输工具/露营及旅行汽车',
            '5598'=>'专业销售/汽车和运输工具/雪车',
            '5599'=>'专业销售/汽车和运输工具/机动车综合经营',
            '5047'=>'专业销售/药品医疗/医疗器械',
            '5122'=>'专业销售/药品医疗/药品、药品经营者（批发商）',
            '5912'=>'专业销售/药品医疗/药物',
            '5975'=>'专业销售/药品医疗/助听器',
            '5976'=>'专业销售/药品医疗/康复和身体辅助用品',
            '7210'=>'商业及生活服务/生活服务/洗衣服务',
            '7211'=>'商业及生活服务/生活服务/洗熨服务（自助洗衣服务）',
            '7216'=>'商业及生活服务/生活服务/干洗店',
            '7217'=>'商业及生活服务/生活服务/室内清洁服务',
            '7221'=>'商业及生活服务/生活服务/摄影服务',
            '7230'=>'商业及生活服务/生活服务/美容/美发服务',
            '7231'=>'商业及生活服务/生活服务/美甲',
            '7251'=>'商业及生活服务/生活服务/鞋帽清洗',
            '7261'=>'商业及生活服务/生活服务/丧仪殡葬服务',
            '7273'=>'商业及生活服务/生活服务/婚介服务',
            '7276'=>'商业及生活服务/生活服务/财务债务咨询',
            '7277'=>'商业及生活服务/生活服务/婚庆服务',
            '7278'=>'商业及生活服务/生活服务/导购、经纪和拍卖服务',
            '7295'=>'商业及生活服务/生活服务/家政服务',
            '7296'=>'商业及生活服务/生活服务/服装出租',
            '7297'=>'商业及生活服务/生活服务/按摩服务',
            '7298'=>'商业及生活服务/生活服务/美容SPA和美体保健',
            '7299'=>'商业及生活服务/生活服务/其他生活服务',
            '7511'=>'商业及生活服务/生活服务/货品停放交易(国际专用)',
            '6010'=>'商业及生活服务/金融服务/金融机构-商业银行服务',
            '6011'=>'商业及生活服务/金融服务/金融机构-自动现金服务',
            '6012'=>'商业及生活服务/金融服务/金融机构-其他服务',
            '6050'=>'商业及生活服务/金融服务/贵金属投资',
            '6051'=>'商业及生活服务/金融服务/外币汇兑',
            '6060'=>'商业及生活服务/金融服务/小贷公司',
            '6061'=>'商业及生活服务/金融服务/消费金融公司',
            '6062'=>'商业及生活服务/金融服务/汽车金融公司',
            '6063'=>'商业及生活服务/金融服务/融资租赁公司',
            '6064'=>'商业及生活服务/金融服务/金融租赁公司',
            '6065'=>'商业及生活服务/金融服务/信托公司',
            '6066'=>'商业及生活服务/金融服务/支付机构',
            '6067'=>'商业及生活服务/金融服务/融资担保公司',
            '6069'=>'商业及生活服务/金融服务/P2P',
            '6211'=>'商业及生活服务/金融服务/证券期货基金',
            '6760'=>'商业及生活服务/金融服务/个人资金借贷',
            '6071'=>'商业及生活服务/无人值守服务/自助贩卖机',
            '6072'=>'商业及生活服务/无人值守服务/自助零售',
            '6073'=>'商业及生活服务/无人值守服务/自助借还',
            '6074'=>'商业及生活服务/无人值守服务/自助娱乐服务',
            '6075'=>'商业及生活服务/无人值守服务/其他自助生活服务',
            '6513'=>'商业及生活服务/房地产/不动产管理－物业管理',
            '7013'=>'商业及生活服务/房地产/不动产代理——房地产经纪',
            '7311'=>'商业及生活服务/商业服务/广告服务',
            '7321'=>'商业及生活服务/商业服务/征信和信用报告咨询服务',
            '7322'=>'商业及生活服务/商业服务/债务催收机构',
            '7333'=>'商业及生活服务/商业服务/商业摄影、设计、绘图服务',
            '7338'=>'商业及生活服务/商业服务/复印及绘图服务',
            '7339'=>'商业及生活服务/商业服务/文字处理/翻译速记',
            '7340'=>'商业及生活服务/商业服务/商户拓展',
            '7342'=>'商业及生活服务/商业服务/灭虫及消毒服务',
            '7349'=>'商业及生活服务/商业服务/清洁、保养及门卫服务',
            '7361'=>'商业及生活服务/商业服务/猎头、职业中介',
            '7392'=>'商业及生活服务/商业服务/公关和企业管理服务',
            '7393'=>'商业及生活服务/商业服务/保安和监控服务',
            '7394'=>'商业及生活服务/商业服务/设备、工具、家具和电器出租',
            '7395'=>'商业及生活服务/商业服务/商业摄影摄像服务',
            '7399'=>'商业及生活服务/商业服务/其他商业服务',
            '7512'=>'商业及生活服务/汽车租赁和服务/汽车出租',
            '7513'=>'商业及生活服务/汽车租赁和服务/卡车及拖车出租',
            '7519'=>'商业及生活服务/汽车租赁和服务/房车和娱乐车辆出租',
            '7523'=>'商业及生活服务/汽车租赁和服务/停车服务',
            '7531'=>'商业及生活服务/汽车租赁和服务/汽车维修、保养、美容装饰',
            '7534'=>'商业及生活服务/汽车租赁和服务/轮胎翻新、维修',
            '7535'=>'商业及生活服务/汽车租赁和服务/汽车喷漆店',
            '7538'=>'商业及生活服务/汽车租赁和服务/汽车改造等服务（非经销商）',
            '7542'=>'商业及生活服务/汽车租赁和服务/洗车',
            '7549'=>'商业及生活服务/汽车租赁和服务/拖车服务',
            '7622'=>'商业及生活服务/维修服务/电器维修',
            '7623'=>'商业及生活服务/维修服务/空调、制冷设备维修',
            '7629'=>'商业及生活服务/维修服务/办公电器和小家电维修',
            '7631'=>'商业及生活服务/维修服务/手表、钟表和首饰维修店',
            '7641'=>'商业及生活服务/维修服务/家具维修、翻新',
            '7692'=>'商业及生活服务/维修服务/焊接维修服务',
            '7699'=>'商业及生活服务/维修服务/各类维修相关服务',
            '0742'=>'商业及生活服务/承包商（农业、建筑、出版）/兽医服务',
            '0743'=>'商业及生活服务/承包商（农业、建筑、出版）/葡萄酒生产商',
            '0744'=>'商业及生活服务/承包商（农业、建筑、出版）/其他酒类生产商',
            '0763'=>'商业及生活服务/承包商（农业、建筑、出版）/农业合作与农具',
            '0780'=>'商业及生活服务/承包商（农业、建筑、出版）/景观美化与园艺服务',
            '4814'=>'网络虚拟/电信通讯/电信运营商',
            '4815'=>'网络虚拟/电信通讯/话费充值与缴费',
            '4821'=>'网络虚拟/电信通讯/网络电话、传真',
            '4899'=>'网络虚拟/电信通讯/付费电视',
            '7379'=>'网络虚拟/互联网服务/计算机维护和修理服务',
            '7829'=>'网络虚拟/娱乐票务/艺术创作服务',
            '7832'=>'网络虚拟/娱乐票务/电影院及电影票',
            '7841'=>'网络虚拟/娱乐票务/音像制品出租',
            '7911'=>'网络虚拟/娱乐票务/歌舞厅/夜店',
            '7922'=>'网络虚拟/娱乐票务/演出票务服务',
            '7929'=>'网络虚拟/娱乐票务/乐队和文艺表演',
            '7932'=>'网络虚拟/娱乐票务/桌球/桌游',
            '7933'=>'网络虚拟/娱乐票务/保龄球',
            '7941'=>'网络虚拟/娱乐票务/体育场馆',
            '7989'=>'网络虚拟/娱乐票务/网吧',
            '7990'=>'网络虚拟/娱乐票务/棋牌室',
            '7991'=>'网络虚拟/娱乐票务/展览和艺术场馆',
            '7992'=>'网络虚拟/娱乐票务/高尔夫球场',
            '7994'=>'网络虚拟/娱乐票务/电玩娱乐场所',
            '7996'=>'网络虚拟/娱乐票务/游乐园、马戏团、嘉年华',
            '7997'=>'网络虚拟/娱乐票务/健身和运动俱乐部',
            '7998'=>'网络虚拟/娱乐票务/动物园、水族馆',
            '7999'=>'网络虚拟/娱乐票务/其他娱乐服务',
            '7995'=>'网络虚拟/彩票/彩票',
            '8011'=>'专业服务/医疗服务/诊所',
            '8021'=>'专业服务/医疗服务/牙科医生',
            '8031'=>'专业服务/医疗服务/正骨医生',
            '8041'=>'专业服务/医疗服务/按摩医生',
            '8042'=>'专业服务/医疗服务/眼科医疗服务',
            '8043'=>'专业服务/医疗服务/眼镜店',
            '8050'=>'专业服务/医疗服务/护理和照料服务',
            '8061'=>'专业服务/医疗服务/民营医院',
            '8071'=>'专业服务/医疗服务/医学及牙科实验室',
            '8099'=>'专业服务/医疗服务/其他医疗保健服务',
            '8241'=>'专业服务/教育服务/函授学校（成人教育）',
            '8244'=>'专业服务/教育服务/商业和文秘学校',
            '3007'=>'专业服务/教育服务/线下教培',
            '8641'=>'专业服务/社会组织/行业协会和专业社团',
            '8661'=>'专业服务/社会组织/宗教组织',
            '8675'=>'专业服务/社会组织/汽车协会',
            '8699'=>'专业服务/社会组织/其他会员组织',
            '8111'=>'专业服务/专业咨询/法律咨询和律师事务所',
            '8734'=>'专业服务/专业咨询/测试实验服务',
            '8911'=>'专业服务/专业咨询/建筑、工程和测量服务',
            '8912'=>'专业服务/专业咨询/装修、装潢、园艺',
            '8931'=>'专业服务/专业咨询/会计、审计、财务服务',
            '8999'=>'专业服务/专业咨询/其他专业服务',
            '9400'=>'专业服务/政府服务/使领馆',
            '9402'=>'专业服务/政府服务/国家邮政',
            '9701'=>'专业服务/其他/Visa信任服务',
            '9702'=>'专业服务/其他/GCAS紧急服务（仅限Visa使用）',
            '3005'=>'校园服务/腾讯微校/校园直连(食堂类)',
            '3006'=>'校园服务/腾讯微校/校园直连(高校类)'
        ];
        return $mccCdArr;
    }

    static public function getBankList(){
        $banklist = [
            '中国工商银行',
            '中国农业银行',
            '中国建设银行',
            '中国民生银行',
            '中国光大银行',
            '中国人民银行',
            '中国银行',
            '中信银行',
            '广发银行',
            '华夏银行',
            '交通银行',
            '平安银行',
            '兴业银行',
            '招商银行',
            '浙商银行',
            '渤海银行',
            '上海浦东发展银行',
            '中国农业发展银行',
            '中国邮政储蓄银行',
            '城市商业银行',
            '农村商业银行',
            '村镇银行',
            '其他银行'
       ];
       return $banklist = $banklist;
    }
}