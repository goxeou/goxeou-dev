<?php


namespace app\controllers;
use think\facade\Db;
use think\facade\Log;

class ApiPay extends ApiCommon
{
    public $authlogin = 0;//默认0登录页面  1直接全信息授权，2静默授权仅获取openid
    public $moeny_weishu = 2;//余额位数
	public function initialize(){
        parent::initialize();
        if ((platform=='mp' || platform=='wx') && in_array(request()->action(),['daifu'])){
            $this->authlogin = 2;//静默授权
        }
		$this->checklogin($this->authlogin);
        $shouquan = 1;
        if(getcustom('maidan_auto_reg') ){
            $orderid = input('param.orderid/d');
            $payorder = Db::name('payorder')->where('id',$orderid)->where('aid',aid)->find();
            $set = Db::name('admin_set')->where('aid',aid)->find();
            if($payorder['type']=='maidan' && !$set['maidan_auto_reg'] && cache($this->sessionid.'_openid')){
                $shouquan = 0;
            }
        }
		if(platform=='mp' && !$this->member['mpopenid'] && !in_array(request()->action(),['webviewjump','paypalRedirect']) && $shouquan==1){
			$appinfo = \app\commons\System::appinfo(aid,'mp');
			if($appinfo['wxpay'] == 1){
				//授权登录
				if(input('param.state') && input('param.state') == 'getMpOpenid' && input('param.code')){
					$code = input('param.code');
                    $rs = \app\commons\Wechat::getAccessTokenByCode(aid,$code);
                    //is_snapshotuser	是否为快照页模式虚拟账号，只有当用户是快照页模式虚拟账号时返回，值为1
                    if($rs['is_snapshotuser'] == 1){
                        return $this->json(['status'=>0,'msg'=>'授权登录失败，请点击下方“使用完整服务”']);
                    }
					if($rs['openid']){
						Db::name('member')->where('id',mid)->update(['mpopenid'=>$rs['openid']]);
					}
					header('location:'.input('param.thisurl'));
				}else{
					//获取用户openid
					$request_url = ltrim($_SERVER["REQUEST_URI"],'/');
					if(strpos($request_url,'?code=')!==false){
						$request_url = explode('?code=',$request_url)[0];
					}elseif(strpos($request_url,'&code=')!==false){
						$request_url = explode('&code=',$request_url)[0];
					}
					$redirectUrl = request()->domain().'/'.$request_url.'&thisurl='.urlencode(input('param.thisurl'));
					$redirectUrl = urlencode($redirectUrl);
                    $AuthorizeUrl = \app\commons\Wechat::getOauth2AuthorizeUrl(aid,$redirectUrl,'snsapi_base','getMpOpenid');
					die(jsonEncode(['status'=>-2,'msg'=>'获取用户openid','url'=>$AuthorizeUrl]));
				}
			}
		}
		if(getcustom('member_money_weishu')){
            $this->moeny_weishu = Db::name('admin_set')->where('aid',aid)->value('member_money_weishu');
        }
        $this->member['money'] = dd_money_format($this->member['money'],$this->moeny_weishu);
	}

	//订单支付
	public function query(){
		$orderid = input('param.orderid/d');
    	$payorder = Db::name('payorder')->where('id',$orderid)->where('aid',aid)->find();
    	require_once (ROOT_PATH. '/app/common/api/HmPayClient.php');
      	$aop = new \HmPayClient();
      
        $apiMap = array(
            'out_order_no'   => $payorder['ordernum'],  
        );
        $jsonResp = $aop->build_pay('trade.query',json_encode($apiMap, JSON_UNESCAPED_UNICODE));
        $verifyFlag = $aop->checkResponseSign($jsonResp, $aop->platRsaPublicKey, $aop->signType);
        $jsonResp['verify']= $verifyFlag;
      
        if ($jsonResp['code'] == "200") {
            $data = json_decode($jsonResp['data'],true);
            if ($data['sub_code']=='SUCCESS') {
                return $this->json(['status'=>1,'msg'=>'支付成功']);
			}else {
			    return $this->json(['status'=>0,'msg'=>$data['sub_msg']]);
			}
        } else {
            return $this->json(['status'=>0,'msg'=>$jsonResp['msg']]);	
        }
    
	
	}
	//订单支付
	public function pay(){
		$orderid = input('param.orderid/d');
		$payorder = Db::name('payorder')->where('id',$orderid)->where('aid',aid)->find();
		if(!$payorder){
			return $this->json(['status'=>0,'msg'=>'该订单不存在']);
		}
		if($payorder['type']!='business_recharge' && $payorder['type']!='yuyue_addmoney'){
		    $is_create_child_order = true;
		    if(getcustom('member_create_child_order')){
                if($payorder['pmid'] == mid)$is_create_child_order = false;
            }
			if($is_create_child_order && $payorder && $payorder['mid'] != mid && $payorder['type'] != 'restaurant_shop') {
				return $this->json(['status'=>0,'msg'=>'该订单不存在']);
			}
		}

        //跳转地址
        $detailurl = '';
        $tourl = '/pages/my/usercenter';
        if($payorder['type'] == 'shop' || $payorder['type'] == 'balance'){
            $detailurl = '/pagesExt/order/detail?id='.$payorder['orderid'];
        }
        if(getcustom('h5zb')){
            if($payorder['type'] == 'shop'){
                $order = Db::name('shop_order')->where('id',$payorder['orderid'])->find();
                if($order['roomid']>0){
                    $tourl = '/h5zb/client/main?id='.$order['roomid'];
                }
            }
        }
        if($payorder['type'] == 'shop_fenqi'){
            $tourl = '/pagesExt/order/orderlist';
        }
        if($payorder['type'] == 'collage'){
            $detailurl = '/activity/collage/orderdetail?id='.$payorder['orderid'];
            $tourl = '/activity/collage/orderlist';
        }
        if($payorder['type'] == 'kanjia'){
            $detailurl = '/activity/kanjia/orderdetail?id='.$payorder['orderid'];
            $tourl = '/activity/kanjia/orderlist';
        }
        if($payorder['type'] == 'seckill'){
            $detailurl = '/activity/seckill/orderdetail?id='.$payorder['orderid'];
            $tourl = '/activity/seckill/orderlist';
        }
        if($payorder['type'] == 'scoreshop'){
            $detailurl = '/activity/scoreshop/orderdetail?id='.$payorder['orderid'];
            $tourl = '/activity/scoreshop/orderlist';
        }
        if($payorder['type'] == 'designerpage'){
            $order = Db::name('designerpage_order')->where('id',$payorder['orderid'])->find();
            $tourl = '/pages/index/main?id='.$order['pageid'];
        }
        if($payorder['type'] == 'restaurant_shop'){
            $detailurl = '/restaurant/shop/orderdetail?id='.$payorder['orderid'];
            $tourl = '/restaurant/shop/orderlist';
        }
        if($payorder['type'] == 'restaurant_takeaway'){
            $detailurl = '/restaurant/takeaway/orderdetail?id='.$payorder['orderid'];
            $tourl = '/restaurant/takeaway/orderlist';
        }
        if($payorder['type'] == 'restaurant_booking'){
            $detailurl = '/restaurant/booking/detail?id='.$payorder['orderid'];
            $tourl = '/restaurant/booking/orderlist';
        }
        if($payorder['type'] == 'seckill2'){
            $detailurl = '/activity/seckill2/orderdetail?id='.$payorder['orderid'];
            $tourl = '/activity/seckill2/orderlist';
        }
        if($payorder['type'] == 'yuyue'){
            $detailurl = '/activity/yuyue/orderdetail?id='.$payorder['orderid'];
            $tourl = '/activity/yuyue/orderlist';
        }

        if($payorder['type'] == 'article_resource'){
            $article_resource_order = Db::name('article_resource_order')->where('id',$payorder['orderid'])->find();
            $detailurl = '/pagesExt/article/detail?id='.$article_resource_order['artid'];
            $tourl = '/pagesExt/article/detail?id='.$article_resource_order['artid'];
        }

ll($article_resource_order,'$article_resource_order');


        if($payorder['type'] == 'kecheng'){
            $kcorder = Db::name('kecheng_order')->where('id',$payorder['orderid'])->find();
            $detailurl = '/activity/kecheng/product?id='.$kcorder['kcid'];
            $tourl = '/activity/kecheng/product?id='.$kcorder['kcid'];
        }
        if($payorder['type'] == 'tuangou'){
            $detailurl = '/activity/tuangou/orderdetail?id='.$payorder['orderid'];
            $tourl = '/activity/tuangou/orderlist';
        }
        if($payorder['type'] == 'lucky_collage'){
            $detailurl = '/activity/luckycollage/orderdetail?id='.$payorder['orderid'];
            $tourl = '/activity/luckycollage/orderlist';
        }
        if($payorder['type'] == 'workorder'){
            $detailurl = '/pagesB/workorder/detail?id='.$payorder['orderid'];
            $tourl = '/pagesB/workorder/record';
        }
        if($payorder['type'] == 'business_recharge'){
            $detailurl = '/admin/index/index';
            $tourl = '/admin/index/index';
        }
        if($payorder['type'] == 'maidan'){
            $tourl = '/pages/maidan/maidanlog';
            if($payorder['bid'] > 0) {
                $maidan_payafterurl = Db::name('business')->where('aid',aid)->where('id',$payorder['bid'])->value('maidan_payaftertourl');
                if($maidan_payafterurl){
                    $tourl = $maidan_payafterurl;
                }else{
                    $s_maidan_payafterurl = Db::name('admin_set')->where('aid',aid)->value('maidan_payaftertourl');
                    if($s_maidan_payafterurl) $tourl =  $s_maidan_payafterurl;
                }
            }else{
                $s_maidan_payafterurl = Db::name('admin_set')->where('aid',aid)->value('maidan_payaftertourl');
                $tourl =  $s_maidan_payafterurl;
            }
        }
        if($payorder['type'] == 'yuyue_workerapply'){
            $yuyueset = Db::name('yuyue_set')->field('apply_url')->where('aid',aid)->find();
            $tourl = $yuyueset['apply_url']?$yuyueset['apply_url']:'/activity/yuyue/apply';
        }
        if(getcustom('xixie')){
            if($payorder['type']=='xixie'){
                $detailurl = '/pagesExt/xixie/orderdetail?id='.$payorder['orderid'];
            }
        }
        if($payorder['type'] == 'yueke'){
            $detailurl = '/pagesExt/yueke/orderdetail?id='.$payorder['orderid'];
            $tourl = '/pagesExt/yueke/orderlist';
        }
        if(getcustom('article_reward')){
            if($payorder['type']=='article_reward'){
                $tourl = '/pagesExt/article/detail?id='.$order['artid'];
            }
        }
        if(getcustom('paotui')){
            if($payorder['type']=='paotui'){
                $tourl = '/pagesExt/paotui/orderlist';
            }
        }
        if(getcustom('extend_tour')){
            if($payorder['type']=='tour_activity'){
                $tourl = '/pagesA/tour/orderlist';
            }
        }
        if(getcustom('extend_gift_bag')){
            if($payorder['type']=='gift_bag'){
                $tourl = '/pagesA/giftbag/orderlist';
            }
        }
        if(getcustom('lipinka_morefee') || getcustom('lipinka_freight_free')){
            if($payorder['type']=='lipin'){
                if($order['type'] == 1){
                    $tourl = '/pagesExt/order/orderlist';
                }
                if($order['type'] == 4){
                    $tourl = '/activity/scoreshop/orderlist';
                }
            }
        }
        if(getcustom('lot_cerberuse')){
            if($payorder['type']=='cerberuse'){
                $tourl = '/pagesZ/cerberuse/orderlist';
            }
        }
        if(getcustom('baoming_xcx')){
            if($payorder['type'] == 'baoming_xcx'){
                $tourl = '/pagesA/baomingxcx/index?id='.$order['bmid'];
            }
        }
        if(getcustom('extend_chongzhi')){
            if($payorder['type'] == 'livepay'){
                $tourl = '/pagesA/livepay/record_recharge?type=all';
            }
        }
        if(getcustom('huodong_baoming')){
            if($payorder['type'] == 'huodong_baoming'){
                $tourl = '/pagesB/huodongbaoming/orderlist';
            }
        }
        if(getcustom('taocan_product')) {
            if ($payorder['type'] == 'taocan') {
                $detailurl = '/pagesA/taocan/orderdetail?id=' . $payorder['orderid'];
                $tourl = '/pagesA/taocan/orderlist';
            }
        }
        if(getcustom('sign_pay_bonus')){
            if($payorder['type']=='sign'){
                $tourl = '/pagesExt/sign/index';
            }
        }
        if(getcustom('extend_qrcode_variable_fenzhang')){
            if($payorder['type'] == 'restaurant_shop'){
                $fzcode = Db::name('restaurant_shop_order')->where('aid',aid)->where('ordernum',$payorder['ordernum'])->value('qrcode_val_code');
                if($fzcode){
                    $tourl = Db::name('qrcode_list_variable')->where('aid',aid)->where('code',$fzcode)->value('path');
                    $tourl = $tourl?'/'.$tourl:'';
                }
            }
        }
		if(getcustom('hotel')){
			if($payorder['type'] == 'hotel'){
				$detailurl = '/hotel/order/orderdetail?id='.$payorder['orderid'];
				$tourl = '/hotel/order/orderlist';
			}
		}
        $appinfo = \app\commons\System::appinfo(aid,platform);
        if($payorder['type'] == 'shop' || $payorder['type'] == 'shop_fenqi'){
            if(getcustom('payaftertourl')){
                $product = Db::name('shop_order_goods')->alias('og')->where('og.orderid',$order['id'])->where('product.payaftertourl','<>','')->where('product.payaftertourl','not null')->join('shop_product product','og.proid=product.id')->find();
                $payaftertourl = $product['payaftertourl'];
                $payorder['payafterbtntext'] = $product['payafterbtntext'];
                if($payaftertourl){
                    if(strpos($payaftertourl,'miniProgram::') === 0){
                        if(platform == 'mp'){
                            $afterurl = explode('|',str_replace('miniProgram::','',$payaftertourl));
                            $payorder['payafter_username'] = $afterurl[2];
                            $payorder['payafter_path'] = $afterurl[1].(strpos($afterurl[1],'?')!==false ? '&' : '?') .'appid='.$appinfo['appid'].'&uid='.mid.'&ordernum='.$payorder['ordernum'];
                        }else{
                            $afterurl = explode('|',$payaftertourl);
                            $payaftertourl = $afterurl[0].'|'.$afterurl[1];
                            $payaftertourl = $payaftertourl.(strpos($payaftertourl,'?')!==false ? '&' : '?') .'appid='.$appinfo['appid'].'&uid='.mid.'&ordernum='.$payorder['ordernum'].'|'.$detailurl;
                        }
                    }
                    $tourl = $payaftertourl;
                }
            }
            if(getcustom('member_auto_addlogin')){
                $is_member_auto_addlogin = Db::name('admin_set')->where('aid',aid)->value('is_member_auto_addlogin');
                if($is_member_auto_addlogin == 1){
                    $detailurl = '/pagesExt/order/detail?id='.$payorder['orderid'];
                    $tourl = '/pagesA/shop/addressorder?orderid='.$payorder['orderid'];
                }
            }

        }
        else if($payorder['type'] == 'kanjia'){
            $product = Db::name('kanjia_product')->where('id',$order['proid'])->find();
            $payaftertourl = $product['payaftertourl'];
            $payorder['payafterbtntext'] = $product['payafterbtntext'];
            if($payaftertourl){
                if(strpos($payaftertourl,'miniProgram::') === 0){
                    if(platform == 'mp'){
                        $afterurl = explode('|',str_replace('miniProgram::','',$payaftertourl));
                        $payorder['payafter_username'] = $afterurl[2];
                        $payorder['payafter_path'] = $afterurl[1].(strpos($afterurl[1],'?')!==false ? '&' : '?') .'appid='.$appinfo['appid'].'&uid='.mid.'&ordernum='.$payorder['ordernum'];
                    }else{
                        $afterurl = explode('|',$payaftertourl);
                        $payaftertourl = $afterurl[0].'|'.$afterurl[1];
                        $payaftertourl = $payaftertourl.(strpos($payaftertourl,'?')!==false ? '&' : '?') .'appid='.$appinfo['appid'].'&uid='.mid.'&ordernum='.$payorder['ordernum'].'|'.$detailurl;
                    }
                }
                $tourl = $payaftertourl;
            }
        }
        //百度AI绘画支付
        if(getcustom('image_ai') && $payorder['type']=='imgai'){
            $detailurl = '/pagesExt/imgai/detail?id='.$payorder['orderid'];
            $tourl = '/pagesExt/imgai/detail?id='.$payorder['orderid'];
        }
        //地图标注支付
        if(getcustom('map_mark') && $payorder['type']=='mapmark'){
            $detailurl = '/pagesExt/mapmark/detail?id='.$payorder['orderid'];
            $tourl = '/pagesExt/mapmark/detail?id='.$payorder['orderid'];
        }
        //短视频去水印支付
        if(getcustom('video_spider') && $payorder['type']=='videospider'){
            $detailurl = '/pagesExt/videospider/detail?id='.$payorder['orderid'];
            $tourl = '/pagesExt/videospider/detail?id='.$payorder['orderid'];
        }

        //有没有支付后赠送活动设置了跳转链接
        $payordertype = $payorder['type'];
        if($payordertype == 'shop_hb') $payordertype = 'shop';
        if($payordertype == 'scoreshop_hb') $payordertype = 'scoreshop';
        if($payordertype == 'shop_fenqi') $payordertype = 'shop';
        if($payordertype == 'restaurant_shop' || $payordertype == 'restaurant_takeaway' || $payordertype == 'restaurant_booking') $payordertype = 'restaurant';
        $pwhere = [];
        $pwhere[] = ['aid','=',aid];
        $pwhere[] = ['bid','=',$payorder['bid']];
        if(getcustom('payaftergive_bind_bids')){
            $pwhere[] = Db::raw("find_in_set({$payorder['bid']},`bind_bids`) OR ISNULL(bind_bids)");
        }
        $payaftergive = Db::name('payaftergive')->where($pwhere)->where('pricestart','<=',$payorder['money'])->where('priceend','>=',$payorder['money'])->where('starttime','<',time())->where('endtime','>',time())->where('tourl','<>','')->whereRaw("find_in_set('".$payordertype."',paygive_scene)")->whereRaw("find_in_set('-1',gettj) or find_in_set('".$this->member['levelid']."',gettj)")->order('sort desc')->find();
        if($payaftergive){
            if($payaftergive['tourl']){
                $tourl = $payaftergive['tourl'];
            }
            if($payaftergive['btntext']){
                $payorder['payafterbtntext'] = $payaftergive['btntext'];
            }
        }

        if(getcustom('yx_invite_cashback')) {
            if($payorder['type'] == 'shop' || $payorder['type'] == 'shop_hb'){
                //处理邀请返现文字提示
                $deal_ictips = \app\customs\OrderCustom::deal_ictips(aid,mid,1,$payorder);
                $payorder['ictips']  = $deal_ictips['ictips'];
                $payorder['proid']   = $deal_ictips['proid'];
                $payorder['propic']  = $deal_ictips['propic'];
                $payorder['proname'] = $deal_ictips['proname'];
            }
        }
        if(input('param.tourl')) $tourl = input('param.tourl');
        //跳转地址end

        $score_weishu = 0;
        if(getcustom('score_weishu')){
            $score_weishu = Db::name('admin_set')->where('aid',aid)->value('score_weishu');
            $score_weishu = $score_weishu?$score_weishu:0;
        }
        $payorder['score'] = dd_money_format($payorder['score'],$score_weishu);
		if($payorder['status']==1){
			return $this->json(['status'=>0,'msg'=>'该订单已支付','url'=>$tourl]);
		}
        if($payorder['status']==2){
            $payorder = Db::name('payorder')->where('aid',$payorder['aid'])->where('bid',$payorder['bid'])->where('orderid',$payorder['orderid'])->where('type',$payorder['type'])->where('mid',$payorder['mid'])->where('status',0)->find();
            if($payorder)
                return $this->json(['status'=>-4,'msg'=>'该订单信息变动，请支付新订单','url'=>'/pagesExt/pay/pay?id='.$payorder['id']]);
            else
                return $this->json(['status'=>0,'msg'=>'该订单已取消']);
        }
		//$payorder['type']=='shop' || $payorder['type'] == 'collage' || $payorder['type'] == 'cycle' || $payorder['type'] == 'kanjia' || $payorder['type'] == 'seckill' || $payorder['type'] == 'seckill2' || $payorder['type'] == 'scoreshop' || $payorder['type'] == 'restaurant_booking' || $payorder['type'] == 'restaurant_takeaway' || $payorder['type']=='choujiang'
		if(in_array($payorder['type'],[
            'shop','collage','cycle','kanjia','seckill','seckill2','scoreshop','restaurant_booking','restaurant_takeaway','choujiang','yuyue'
        ])){
            $order = Db::name($payorder['type'].'_order')->where('id',$payorder['orderid'])->find();
            if($order['status']==4){
                return $this->json(['status'=>0,'msg'=>'该订单已关闭']);
            }elseif($order['status']!=0){
                return $this->json(['status'=>0,'msg'=>'订单状态不符合']);
            }
            if($payorder['type'] == 'yuyue'){
				$product = Db::name('yuyue_product')->where('id',$order['proid'])->find();
                //是否是多时间段选择模式
                $selmoretime = false;
                if(getcustom('yuyue_datetype1_model_selnum')){
                    //判断是否是:时间段、模式2多段模式、且时间段起订量大于等于1，需要多选
                    if(($product['rqtype']!=4) && $product['datetype'] == 1 && $product['datetype1_model'] == 1 && $product['datetype1_modelselnum'] >=1){
                        $selmoretime = true;
                    }
                }
                if(!$selmoretime){
                    $yydate = $order['yy_time'];
                    if(getcustom('yuyue_selecttime_with_stock')){
                        if($product['showdatetype']==1){
                            $yydate = explode('~',$order['yy_time']);
                            $yydate = $yydate[0];
                        }
                    }
                    //开始时间
                    $begindate = $yydate;
                    if(strpos($begindate,'年') === false){
                        $begindate = date('Y').'年'.$begindate;
                    }
                    $begindate = preg_replace(['/年|月/','/日/'],['-',''],$begindate);
                    $date = date('Y-m-d H:i:s',strtotime(date('H:i',time())));
                    $begintime = strtotime($begindate);
                    if($begintime <= strtotime(date('H:i',time()))+$product['pdprehour']*60*60){
                        return $this->json(['status'=>0,'msg'=>'预约时间已过，请选择其他时间']);
                    }
                    //查看是否已经存在
                    $yycount= Db::name($payorder['type'].'_order')->where('aid',aid)->where('yy_time',$order['yy_time'])->where('proid',$order['proid'])->where('mid','<>',$order['mid'])->where('status','in','1,2')->count();
                    if($yycount>=$product['yynum']){
                        return $this->json(['status'=>0,'msg'=>'该段时间预约人数已满']);
                    }

                    //查看该服务人员该时间是否已经预约出去
                    if($order['worker_id']){
                        $count = Db::name('yuyue_order')->where('worker_id',$order['worker_id'])->where('aid',aid)->where('status','in','1,2')->where('yy_time',$order['yy_time'])->count('id');
                        if($count){
                            return $this->json(['status'=>0,'msg'=>$yydate.'该段时间不可预约']);
                        }
                    }
                }else{
                    if(getcustom('yuyue_selectpeople_inproduct')){
                        $yydates     = $order['yydates'] && !empty($order['yydates'])?json_decode($order['yydates'],true):[];
                        $yydates_num = count($yydates);
                        if($yydates_num<$product['datetype1_modelselnum']){
                            return $this->json(['status'=>0,'msg'=>'服务时间最少选择'.$product['datetype1_modelselnum'].'个连续时间段']);
                        }
                        $porders = Db::name('yuyue_order')->where('proid',$order['proid'])->where('status','in','1,2')->where('aid',aid)->field('yy_time,yy_times,yydates')->select()->toArray();
                        if($porders){
                            foreach($yydates as &$yv){
                                $yv['num'] = 0;
                                $yy_time = $yv['year'].$yv['date'].' '.$yv['time'];
                                if(strpos($yy_time,'年') === false){
                                    $yy_time = date('Y').'年'.$yy_time;
                                }
                                $yy_time = preg_replace(['/年|月/','/日/'],['-',''],$yy_time);
                                $yv['datetime'] = strtotime($yy_time);
                                foreach($porders as $ov){
                                    if($ov['yy_times']){
                                        $yy_times = json_decode($ov['yy_times']);
                                        if(in_array($yy_time,$yy_times)){
                                            $yv['num'] += 1;
                                        }
                                    }else if($yy_time == $ov['yy_time']){
                                        $yv['num'] += 1;
                                    }
                                }
                                unset($ov);
                            }
                            unset($yv);
                            foreach($yydates as $yv){
                                $yy_time = $yv['year'].$yv['date'].' '.$yv['time'];
                                if($yv['num']>=$product['yynum']){
                                    return $this->json(['status'=>0,'msg'=>$yy_time.'该段时间预约人数已满']);
                                }
                            }
                        }
                        //查看该服务人员该时间是否已经预约出去
                        if($order['worker_id']){
                            //查看该服务人员该时间是否已经预约出去
                            $worders = Db::name('yuyue_order')->where('worker_id',$order['worker_id'])->where('aid',aid)->where('status','in','1,2')->field('yy_time,yy_times,yydates')->select()->toArray();
                            if($worders){
                                foreach($yydates as $yv){
                                    $yy_time = $yv['year'].$yv['date'].' '.$yv['time'];
                                    foreach($worders as $ov){
                                        if($ov['yy_times']){
                                            $yy_times = json_decode($ov['yy_times']);
                                            if(in_array($yy_time,$yy_times)){
                                                return $this->json(['status'=>0,'msg'=>$yy_time.'该段时间不可预约']);
                                            }
                                        }else if($yy_time == $ov['yy_time']){
                                            return $this->json(['status'=>0,'msg'=>$yy_time.'该段时间不可预约']);
                                        }
                                    }
                                    unset($ov);
                                }
                            }
                            unset($yv);
                        }
                    }
                }
			}
            if($order['discount_rand_money'] > 0){
                $payorder['discountText'] = '随机立减'.$order['discount_rand_money'];
            }
            if(getcustom('douyin_groupbuy')){
            	//抖音团购券再次验证
                if($payorder['type'] == 'shop' && $order['isdygroupbuy']==1){
                    $dyset = Db::name('douyin_groupbuy_set')->where('aid',aid)->field('status,autoclose')->find();
                    if(!$dyset || $dyset['status']!=1){
                        return $this->json(['status'=>0,'msg'=>'抖音团购券兑换设置未开启']);
                    }
                    $endtime = time()-$dyset['autoclose']*60;
                    if($order['createtime']<=$endtime){
                        return $this->json(['status'=>0,'msg'=>'此订单兑换已超时间，请重新下单']);
                    }
                    if(empty($order['dycodes']) && empty($order['dyencrypted_datas'])){
                        return $this->json(['status'=>0,'msg'=>'兑换出错，请重新下单']);
                    }

                    $mfield = 'id,name,tel,pic,province,city,district,area,address,longitude,latitude,poi_id';
                    $mendian = Db::name('mendian')->where('id',$order['mdid'])->where('aid',aid)->where('bid',$order['bid'])->where('status',1)->where('poi_id !=""')->field($mfield)->find();
                    if(!$mendian) return $this->json(['status'=>0,'msg'=>'订单所选门店已失效']);

                    $checkset = \app\customs\DouyinGroupbuyCustom::checkset(aid,$order['bid']);
                    if($checkset['status'] == 0){
                        return $this->json(['status'=>0,'msg'=>'系统暂未启用核销功能']);
                    }
                    $set = $checkset['set'];

                    $dycodes = '';
                    if($order['dycodes']) $dycodes = explode(',',$order['dycodes']);

                    $dyencrypted_datas = '';
                    if($order['dyencrypted_datas']) $dyencrypted_datas = explode(',',$order['dyencrypted_datas']);

                    $deal_codeinfo = \app\customs\DouyinGroupbuyCustom::deal_codeinfo(aid,$order['bid'],$mendian,$set,$this->member,$dycodes,$dyencrypted_datas,$order['id']);
                    if(!$deal_codeinfo || $deal_codeinfo['status']!=1){
                        $msg = $deal_codeinfo && $deal_codeinfo['msg']?$deal_codeinfo['msg']:'抖音兑换码获取信息失败';
                        return $this->json(['status'=>0,'msg'=>$msg]);
                    }
                }
            }
        }
		if($payorder['type'] == 'shopfront'){
			$order = Db::name('shop_order')->where('id',$payorder['orderid'])->find();
            if($order['status']==4){
                return $this->json(['status'=>0,'msg'=>'该订单已关闭']);
            }elseif($order['status']!=0){
                return $this->json(['status'=>0,'msg'=>'订单状态不符合']);
            }
		}

		if($payorder['type'] == 'restaurant_shop') {
            $order = Db::name($payorder['type'].'_order')->where('id',$payorder['orderid'])->find();
            if($order['status']==4){
                return $this->json(['status'=>0,'msg'=>'该订单已关闭']);
            }
            if(getcustom('restaurant_shop_pindan')){
                //拼单模式下 谁支付，payorder的mid 更新成谁的
                $table = Db::name('restaurant_table')->where('aid',aid)->where('id', $order['tableid'])->find();
                if($table['pindan_status'] ==1){
                    Db::name('payorder')->where('id',$payorder['id'])->update(['mid' => mid]);
                }
            }
        }
		if($payorder['type'] == 'collage'){ //拼团
			$order = Db::name('collage_order')->where('id',$payorder['orderid'])->find();
			if($order['buytype']!=1){
				$team = Db::name('collage_order_team')->where('aid',aid)->where('id',$order['teamid'])->find();
				if($team['status']==2){
					return $this->json(['status'=>0,'msg'=>'该团已满员']);
				}
				if($team['status']==3){
					return $this->json(['status'=>0,'msg'=>'该团已解散']);
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
					return $this->json(['status'=>0,'msg'=>'该团已满员']);
				}
				if($team['status']==3){
					Db::rollback();
					return $this->json(['status'=>0,'msg'=>'该团已解散']);
				}
				$rs = Db::name('lucky_collage_order')->where('aid',aid)->where('teamid',$order['teamid'])->where('mid',mid)->where('status','>',0)->where('id','<>',$order['id'])->where('isjiqiren',0)->find();
				if($rs){
					Db::rollback();
					return $this->json(['status'=>0,'msg'=>'您已经参与该团了']);
				}
			}
			
			if(getcustom('member_tag')){
				$product = Db::name('lucky_collage_product')->where('aid',aid)->where('status',1)->where('ischecked',1)->where('id',$order['proid'])->find();
				if($product['istag']==1){
					$rs = \app\models\LuckyCollage::membertag_collage($order['mid'],$order['teamid'],$product);
					if($rs && $rs['status']==0){
						return $this->json(['status'=>0,'msg'=>'该团您暂时没有参加条件']);
					}
				}
			}
			Db::commit(); 
		}
		if($payorder['type']!='shop_hb' && $payorder['type']!='scoreshop_hb' && $payorder['type']!='balance' && $payorder['type']!='yuyue_balance' && $payorder['type']!='yuyue_addmoney' && $payorder['type']!='shop_fenqi'){
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
						return $this->json(['status'=>0,'msg'=>($freight['pstype']!=1?'配送':'取货').'时间必须在'.$freight['psprehour'].'小时之后']);
					}
				}
			}
		}

        if($payorder['type'] == 'shop_fenqi'){
			$orderinfo = Db::name('shop_order')->where('id',$payorder['orderid'])->find();
		}

        if($payorder['type'] == 'livepay'){//生活缴费
			$order = Db::name('livepay_order')->where('id',$payorder['orderid'])->find();
            if($order['status']==4){
                return $this->json(['status'=>0,'msg'=>'该订单已关闭']);
            }elseif($order['status']!=0){
                return $this->json(['status'=>0,'msg'=>'订单状态不符合']);
            }
		}
        if(getcustom('huodong_baoming') && $payorder['type'] == 'huodong_baoming'){//活动报名
			$order = Db::name('huodong_baoming_order')->where('id',$payorder['orderid'])->find();
            if($order['status']==4){
                return $this->json(['status'=>0,'msg'=>'该订单已关闭']);
            }elseif($order['status']!=0){
                return $this->json(['status'=>0,'msg'=>'订单状态不符合']);
            }
		}

		if($payorder['type'] == 'coupon'){
			$orderinfo = Db::name('coupon_order')->where('id',$payorder['orderid'])->find();
			$coupon = Db::name('coupon')->where('id',$orderinfo['cpid'])->find();
			if($coupon['stock']<=0) return $this->json(['status'=>0,'msg'=>'库存不足']);
		}

		$set = Db::name('admin_set')->where('aid',aid)->find();
        if(getcustom('pay_yuanbao') && $payorder['type']=='shop'){
            $yuanbao_money_ratio = $set['yuanbao_money_ratio'];
            $yuanbao_money = $order['total_yuanbao']*$yuanbao_money_ratio/100;

            $yuanbao_money = round($yuanbao_money,2);//现金
            $total_yuanbao = $order['total_yuanbao'];//总支付元宝

            $yuanbao_msg = $order['total_yuanbao'].t('元宝').'+'.t('现金').$yuanbao_money.'元';//元宝信息提示
            $yuanbaopay  = $set['yuanbao_pay'];//是否开启元宝支付

            //如果存在非元宝商品，则关闭元宝支付
            if($order['have_no_yuanbao']){
                $yuanbaopay  = 0;
            }
        }else{
            $yuanbao_money = 0;
            $total_yuanbao = 0;
            $yuanbao_msg   = '';
            $yuanbaopay    = 0;
        }
        if(getcustom('xixie')){
            if($payorder['type']=='xixie'){
                $order = Db::name('xixie_order')->where('id',$payorder['orderid'])->find();
                 if($order['status']==4){
                    return $this->json(['status'=>0,'msg'=>'该订单已关闭']);
                }elseif($order['status']!=0){
                    return $this->json(['status'=>0,'msg'=>'订单状态不符合']);
                }
            }
        }
        if(getcustom('article_reward')){
            if($payorder['type']=='article_reward'){
                $order = Db::name('article_reward_order')->where('id',$payorder['orderid'])->find();
                if(!$order){
                    return $this->json(['status'=>0,'msg'=>'订单不存在']);
                }
                if($order['status']!=0){
                    return $this->json(['status'=>0,'msg'=>'订单状态不符合']);
                }
            }
        }
        if(getcustom('lipinka_morefee') || getcustom('lipinka_freight_free')){
        	if($payorder['type']=='lipin'){
                $order = Db::name('lipin_order')->where('id',$payorder['orderid'])->find();
                if(!$order){
                    return $this->json(['status'=>0,'msg'=>'订单不存在']);
                }
                if($order['status']!=0){
                    return $this->json(['status'=>0,'msg'=>'订单状态不符合']);
                }
            }
        }
        if(getcustom('business_deposit')){
            if($payorder['type']=='business_deposit'){
                $order = Db::name('business_deposit_order')->where('id',$payorder['orderid'])->find();
                if(!$order){
                    return $this->json(['status'=>0,'msg'=>'订单不存在']);
                }
                if($order['status']!=0){
                    return $this->json(['status'=>0,'msg'=>'订单状态不符合']);
                }
            }
        }
        $overdraft_moneypay = 0;
        if(getcustom('member_overdraft_money') && $set['overdraft_moneypay'] && !in_array($payorder['type'],['recharge','overdraft_recharge'])){
            //这里增加绑定客户的判断
            $overdraft_moneypay = 1;
            if(getcustom('customer_overdraft_money')){
                //未绑定客户的会员不可用信用额度
                $customerBind = Db::name('sh_customer')->where('aid',aid)->where('mid',$this->mid)->count();
                if(empty($customerBind)){
                    $overdraft_moneypay = 0;
                }
            }
        }
		 if(getcustom('baoming_xcx')){
            if($payorder['type']=='baoming_xcx'){
                $order = Db::name('baoming_xcx_order')->where('id',$payorder['orderid'])->find();
                if(!$order){
                    return $this->json(['status'=>0,'msg'=>'订单不存在']);
                }
                if($order['status']!=1){
                    return $this->json(['status'=>0,'msg'=>'订单状态不符合']);
                }
            }
        }

        if(getcustom('ciruikang_fenxiao')){
        	//是否开启了商城商品需上级购买足量
            $deal_ogstock = \app\customs\CiruikangCustom::deal_ogstock(aid,mid,$this->member,$set,$order,$payorder);
            if($deal_ogstock['status'] == 0 ){
                return $this->json($deal_ogstock);
            }
        }
        $checkscore_params = [];//检查积分方法额外参数
        $decscore_params   = [];//减少积分方法额外参数
        if(getcustom('scoreshop_otheradmin_buy')){
            //积分兑换：是来自本系统其他平台的用户
            if(platform == 'wx' && ($payorder['type']=='scoreshop' || $payorder['type']=='scoreshop_hb') && $order['otheraid'] != aid && $order['othermid']>0){
                $othermember = [];//其他平台用户
                $BuyOverallScoreshop = false;//权限
                $othermember = Db::name('member')->where('id',$order['othermid'])->where('aid',$order['otheraid'])->field('id,aid,nickname,score,money')->find();
                //用户存在，且不是本平台
                if($othermember && $othermember['aid'] != aid){
                    //查询权限组 是否开启兑换总平台积分商品
                    $admin_user = db('admin_user')->where('aid',$othermember['aid'])->where('isadmin','>',0)->field('auth_type,auth_data')->find();
                    if($admin_user['auth_type'] != 1){
                        if($admin_user['groupid']){
                            $admin_user['auth_data'] = Db::name('admin_user_group')->where('id',$admin_user['groupid'])->value('auth_data');
                        }
                        $admin_auth = json_decode($admin_user['auth_data'],true);
                        if($admin_auth && in_array('BuyOverallScoreshop,BuyOverallScoreshop',$admin_auth)){
                            $BuyOverallScoreshop = true;//标记有兑换权限
                        }
                    }else{
                        $BuyOverallScoreshop = true;//标记有兑换权限
                    }
                    if($BuyOverallScoreshop){
                        //查询小程序名称
                        $appinfo = Db::name('admin_setapp_wx')->where('aid',aid)->field('id,nickname')->find();
                        $othermember['appname'] = $appinfo && !empty($appinfo['nickname'])?$appinfo['nickname']:$set['name'];
                        $checkscore_params['othermember'] = $othermember;//检查积分额外参数
                        $decscore_params['othermember']   = $othermember;//减少积分额外参数
                    }
                }
            }
        }

		if(input('param.op') == 'submit'){
			$post = input('param.');
            Db::name('payorder')->where('id',$orderid)->update(['platform'=>platform]);

            //元宝支付
            if(getcustom('pay_yuanbao')){
                //支付类型
                $pay_type = $post['pay_type'];

                //如果支付类型是元宝 且是商城支付
                if( $yuanbaopay && $pay_type == 'yuanbao' && $payorder['type']=='shop'){
                    //检查用户元宝是否足够
                    if($this->member['yuanbao']<$total_yuanbao){
                        return $this->json(['status'=>0,'msg'=>t('元宝').'不足']);
                    }
                    //重新赋值
                    $payorder['money'] = $yuanbao_money;
                }
            }
            if(getcustom('product_service_fee')){
                if($this->member['service_fee'] < $payorder['service_fee_money']){
                    return $this->json(['status'=>0,'msg'=>t('服务费').'不足，请充值']);
                }
            }
          	if(getcustom('business_sales_quota')){
				if($order['bid']>0){
					$business2 = Db::name('business')->where(['aid'=>aid,'id'=>$order['bid']])->field('kctime,kctype,sales_quota,total_sales_quota')->find();
					$sales_price = $order['product_price'];
					if($business2['kctype']==1){
						$sales_price = $order['totalprice'];
					}
					$syquota = $business2['sales_quota']-$business2['total_sales_quota'];
					if($business2['sales_quota']>0 && $syquota<$sales_price){
						return $this->json(['status'=>0,'msg'=>'该商户商品额度不足']);
					}
				}
			}

			if($payorder['type']=='yuyue'){
			    $order = Db::name($payorder['type'].'_order')->where('id',$payorder['orderid'])->find();
				$product = Db::name('yuyue_product')->field('pdprehour,yynum')->where('id',$order['proid'])->find();
				//查看是否已经存在
				$yycount= Db::name($payorder['type'].'_order')->where('aid',aid)->where('yy_time',$order['yy_time'])->where('proid',$order['proid'])->where('mid','<>',$order['mid'])->where('status','in','1,2')->count();
				if($yycount>=$product['yynum']){
					return $this->json(['status'=>0,'msg'=>'该段时间预约人数已满']);
				}
				if($order['scoredkscore'] > 0){
					\app\commons\Member::addscore(aid,$order['mid'],$order['scoredkscore'],'订单退款返还');
				}
			}

$pwd_check = $this->sysset['money_pwd'];
            if($pwd_check) {
                if(!$this->member['paypwd']) {
					return $this->json(['status'=>0,'msg'=>'请先设置支付密码','set_paypwd'=>1]);
				}
    			if($post['typeid']==1){ //余额支付 验证支付密码
    				if(!\app\commons\Member::checkPayPwd($this->member,$post['paypwd'] )){
    					return $this->json(['status'=>0,'msg'=>'支付密码输入错误']);
    				}
    			}
            }
			if($this->member['paypwd33'] && $post['typeid']==1){ //余额支付 验证支付密码
				if(!\app\commons\Member::checkPayPwd($this->member,$post['paypwd'] )){
					return $this->json(['status'=>0,'msg'=>'支付密码输入错误']);
				}
			}
    if($payorder['credit'] > 0 ){
                if($this->member['credit2'] < $payorder['credit']){
    				return $this->json(['status'=>0,'msg'=>t('credit2').'不足']);
    			}
            }
			if($payorder['money'] <=0 && $payorder['score']<=0){
                Db::startTrans();
	if($payorder['type'] == 'maidan'){
					\app\commons\Member::addcredit2(aid,mid,-$payorder['credit'],$payorder['title'].',订单号: '.$payorder['ordernum']);
				}else{
					\app\commons\Member::addcredit2(aid,mid,-$payorder['credit'],'支付订单,订单号: '.$payorder['ordernum']);
				}
                try{
                    \app\models\Payorder::payorder($orderid,'无须付款',1,'');
                    if($payorder['type'] != 'shop_hb'){
                        //\app\commons\notice\Notice::orderPay(aid,$payorder,mid,$this->member);
                        $order = Db::name($payorder['type'].'_order')->where('id',$payorder['orderid'])->find();
                        if($payorder['type'] =='maidan'){
                            $order['totalprice'] =  $order['money'];
                        }
                        //$this->sendNotice(aid,$payorder,$order,$this->member);
                    }                    
                    Db::commit();
                    return $this->json(['status'=>2,'msg'=>'付款成功']);
                }catch(Exception $e){
                    Log::write([
                        'file'=>__FILE__,
                        'line'=>__LINE__,
                        'error'=>$e->getMessage()
                    ]);
                    Db::rollback();
                    return $this->json(['status'=>1,'msg'=>'异常错误，请重试']);
                }
			}
			if($payorder['score'] > 0){
				$ckrs = $this->checkscore($payorder,$checkscore_params);
				if($ckrs['status'] == 0) return $this->json($ckrs);
			}
			if($post['typeid']==1){ //余额支付
				if($payorder['type'] == 'recharge') return $this->json(['status'=>0,'msg'=>'不能用余额支付']);
				if($this->member['money'] < $payorder['money']){
					return $this->json(['status'=>0,'msg'=>t('余额').'不足,请充值']);
				}
				if($payorder['money'] > 0){
				 $aid = aid;$mid = mid;
                    $remark = '支付订单,订单号: '.$payorder['ordernum'];
                    $addmoney_params = [];//其他参数
                    if(getcustom('scoreshop_otheradmin_buy')){
                        //是来自本系统其他平台的用户
                        if(($payorder['type']=='scoreshop' || $payorder['type']=='scoreshop_hb') && $BuyOverallScoreshop && $othermember){
                            $aid = $othermember['aid'];$mid = $othermember['id'];
                            $remark = '支付'.$othermember['appname'].'订单,订单号: '.$payorder['ordernum'];
                            $addmoney_params['optaid'] = aid;
                        }
                    }
                    if(getcustom('moneylog_detail')){
                        $addmoney_params['ordernum'] = $payorder['ordernum'];    
                        $addmoney_params['type'] = $payorder['type'];    
                    }
					//减去会员的余额
					if($payorder['type'] == 'maidan'){
						\app\commons\Member::addmoney($aid,$mid,-$payorder['money'],$payorder['title'].',订单号: '.$payorder['ordernum'],0,'','',$addmoney_params);
					}else{
						\app\commons\Member::addmoney($aid,$mid,-$payorder['money'],$remark,0,'','',$addmoney_params);
					}
				}
				if($payorder['credit'] > 0){
					//减去会员的余额
					if($payorder['type'] == 'maidan'){
						\app\commons\Member::addcredit2(aid,mid,-$payorder['credit'],$payorder['title'].',订单号: '.$payorder['ordernum']);
					}else{
						\app\commons\Member::addcredit2(aid,mid,-$payorder['credit'],remark);
					}
				}
				if($payorder['score'] > 0){
					//减去会员的积分
					$this->decscore($payorder,$decscore_params);
				}

                if(getcustom('product_service_fee') && $payorder['service_fee_money'] > 0){
                    //减去服务费
                    $this->payServiceFee($payorder);
                }

				//元宝 更新payordr支付
                $this->yuanbao_up_pay(aid,$yuanbaopay,$orderid,$post['pay_type'],$payorder);
				if($payorder['money']>0){
					$paytype = t('余额').'支付';
				}elseif($payorder['money']==0 && $payorder['score']>0 ){
					$paytype = t('积分').'支付';
				}
			

				\app\models\Payorder::payorder($orderid,$paytype,1,'');
                //消费送积分
                $giftsScore = 1;
                //积分转赠手续费
                if(getcustom('score_transfer_sxf') && $payorder['type'] =='score_transfer'){
                    $giftsScore = 0;
                }
                if($giftsScore == 1){
                    if($set['scorein_money']>0 && $set['scorein_score']>0 && $set['score_from_moneypay'] == 1){
                        $givescore = floor($payorder['money'] / $set['scorein_money']) * $set['scorein_score'];
                        $res = \app\commons\Member::addscore(aid,mid,$givescore,'消费送'.t('积分'));
                        if($res && $res['status'] == 1){
                            //记录消费赠送积分记录
                            \app\commons\Member::scoreinlog(aid,0,mid,$payorder['type'],$payorder['orderid'],$payorder['ordernum'],$givescore,$payorder['money']);
                        }
                    }
                }
				return $this->json(['status'=>2,'msg'=>'付款成功']);
			}elseif($post['typeid']==4){ //货到付款
                if(getcustom('restaurant') && $payorder['type'] == 'restaurant_shop') {
                    $sysset = Db::name('restaurant_shop_sysset')->where('aid', aid)->where('bid', $payorder['bid'])->find();
                    $paytype_rest = explode(',', $sysset['paytype']);
					$codtxt = '线下支付';
                    if(!in_array('cash', $paytype_rest)) return $this->json(['status'=>0,'msg'=>'不支持'.$codtxt]);
                } else {
                    if($payorder['type'] != 'shop') return $this->json(['status'=>0,'msg'=>'不支持该付款方式']);
					$shopset = Db::name('shop_sysset')->where('aid',aid)->find();
					$codtxt = $shopset['codtxt'];
                    if(!$shopset['cancod']) return $this->json(['status'=>0,'msg'=>'不支持'.$codtxt]);
                }

				if($payorder['score'] > 0){
					//减去会员的积分
					$this->decscore($payorder,$decscore_params);
				}
				\app\models\Payorder::payorder($orderid,$codtxt,4,'');
                //消费送积分
                if($set['scorein_money']>0 && $set['scorein_score']>0 && $set['score_from_xianxiapay'] == 1){
                    $givescore = floor($payorder['money'] / $set['scorein_money']) * $set['scorein_score'];
                    $res = \app\commons\Member::addscore(aid,mid,$givescore,'消费送'.t('积分'));
                    if($res && $res['status'] == 1){
                        //记录消费赠送积分记录
                        \app\commons\Member::scoreinlog(aid,0,mid,$payorder['type'],$payorder['orderid'],$payorder['ordernum'],$givescore,$payorder['money']);
                    }
                }
				return $this->json(['status'=>2,'msg'=>'提交成功']);
            }elseif($post['typeid']==5){
                if(getcustom('pay_transfer')){
                    //转账汇款
                    if($payorder['type'] != 'shop') return $this->json(['status'=>0,'msg'=>'不支持'.t('转账汇款')]);
                    $pay_transfer = Db::name('admin_set')->where('aid',aid)->value('pay_transfer');
                    if(!$pay_transfer) return $this->json(['status'=>0,'msg'=>'不支持该支付方式']);
                    //使用角色
                    $gettj = explode(',',$set['pay_transfer_gettj']);
                    if(!in_array('-1',$gettj) && !in_array($this->member['levelid'],$gettj)){
                        return $this->json(['status'=>0,'msg'=>'不支持该支付方式']);
                    }
                    if($payorder['score'] > 0){
                        //减去会员的积分
                        $this->decscore($payorder,$decscore_params);
                    }
                    //转账后修改状态为已支付
                    $paytype = t('转账汇款');
                    $paytypeid = 5;
                    //\app\models\Payorder::payorder($orderid,'转账汇款',5,'');
                    if($set && $set['pay_transfer_check'] == 1){
                        //需要审核
                        $transfer_check = 0;
                    }else{
                        //不需要审核
                        $transfer_check = 1;
                    }
                    Db::name('payorder')->where('id',$orderid)->update(['paytype'=>$paytype,'paytypeid'=>$paytypeid]);
                    Db::name('shop_order')->where('id',$payorder['orderid'])->update(['paytype'=>$paytype,'paytypeid'=>$paytypeid,'platform'=>platform,'transfer_check'=>$transfer_check]);
                    if($transfer_check == 1){
                        return $this->json(['status'=>2,'msg'=>'提交成功']);
                    }else{
                        return $this->json(['status'=>1,'msg'=>'提交成功待审核']);
                    }
                }
			}elseif($post['typeid']==2){ //微信支付
                if(getcustom('wxpay_member_level')){
                    //微信支付使用角色
                    if($set && $set['wxpay_gettj']){
                        $gettj = explode(',',$set['wxpay_gettj']);
                        //不是所有人
                        if(!in_array('-1',$gettj) && !in_array($this->member['levelid'],$gettj)){
                            return $this->json(['status'=>0,'msg'=>'暂不能使用微信支付']);
                        }
                    }
                }
                if(getcustom('pay_money_combine')){
                    //余额组合支付
                    $combines = $post['combines']?$post['combines']:'';
                    if($combines && $combines['moneypay'] == 1){
                        $deal = self::deal_money_combine(aid,mid,$this->member,$post['typeid'],$combines,$payorder,$set);
                        if(!$deal || $deal['status'] == 0){
                            $msg = $deal['msg']?$deal['msg']:t('余额').'支付错误，请重试';
                            return $this->json(['status'=>0,'msg'=>$msg]);
                        }else if($deal['status'] == 2){
                            return $this->json(['status'=>2,'msg'=>$deal['msg']]);
                        }else{
                            $payorder['money'] = $deal['money'];
                        }
                    }
                }
				$buildfun = 'build_'.platform;
                $openid = '';
                if(getcustom('maidan_auto_reg')){
                    //开启了买单不注册会员
                    $maidan_auto_reg = Db::name('admin_set')->where('aid',aid)->value('maidan_auto_reg');
                    if(!$maidan_auto_reg && !$this->mid && cache($this->sessionid.'_openid')){
                        $openid = cache($this->sessionid.'_openid');
                    }
                }
                if(getcustom('sxpay_apptowx') ){
                    $appinfo = \app\commons\System::appinfo(aid,'app');
                    if( $appinfo['wxpay_type']==3 && platform=='app'){
                        $sessionid = \think\facade\Session::getId();;
                        $path = '/pagesB/pay/pay';
                        $rs['data']['pay_plate'] = 'wx';
                        $query = 'session_id='.$sessionid.'&orderid='.$orderid;
                        $res_url = \app\commons\Wechat::getUrlScheme(aid,$path,$query);
                        if($res_url['status']) {
                            $url = $res_url['url'];
                            $rs['data']['is_jump'] = 1;
                            $rs['data']['jump_link'] = m_url('/pagesB/pay/pay?session_id=' . $sessionid . '&wx_url=' . urlencode($url));
                            $rs['data']['wx_url'] = $url;
                            return $this->json($rs);
                        }else{
                            return $this->json(['status'=>0,'msg'=>$res_url['msg']]);
                        }
                    }
                }
                if(getcustom('product_service_fee') && $payorder['service_fee_money'] > 0){
                    //减去服务费
                    $this->payServiceFee($payorder);
                }
				$rs = \app\commons\Wxpay::$buildfun(aid,$payorder['bid'],mid,$payorder['title'],$payorder['ordernum'],$payorder['money'],$payorder['type'],'',$openid);
				//元宝 更新payordr支付
                $this->yuanbao_up_pay(aid,$yuanbaopay,$orderid,$post['pay_type'],$payorder);
				return $this->json($rs);
			}elseif($post['typeid']==3){ //支付宝支付
                //支付宝交易组件
                $plugin_order_id = '';
                if(getcustom('alipay_plugin_trade') && in_array($payorder['type'],['shop','shop_hb'])){
                    $alipayPluginOrder = input('param.alipayPluginOrder');
                    $sourceId = input('param.sourceId');
                    if($alipayPluginOrder==1 && $sourceId){
                        $pluginResult = \app\commons\Alipay::pluginOrderCreate(aid,$payorder['orderid'],mid,$payorder['title'],$payorder['ordernum'],$payorder['money'],$payorder['type'],$sourceId);
                        if(!$pluginResult || $pluginResult['status']!=1){
                            return $this->json($pluginResult);
                        }
                        //更新订单`alipay_component_orderid`
                        $plugin_order_id = $pluginResult['order_id'];
                        writeLog('-----------pluginOrder------');
                        writeLog(json_encode([
                            'alipayPluginOrder'=>$alipayPluginOrder,
                            'sourceId'=>$sourceId,
                            'orderid'=>$payorder['orderid'],
                        ]));
                        writeLog('-----------pluginOrder------');
                        writeLog(json_encode($pluginResult));
                        writeLog('-----------pluginOrder------');
                    }
                    $ordmap = [];
                    $ordmap[] = ['aid','=',aid];
                    if($payorder['type']=='shop_hb'){
                        $ordmap[] = ['ordernum','like',$payorder['ordernum'].'_%'];
                    }else{
                        $ordmap[] = ['ordernum','=',$payorder['ordernum']];
                    }
                    Db::name('shop_order')->where($ordmap)->update(['alipay_component_orderid'=>$plugin_order_id]);
                }
                if(getcustom('pay_money_combine')){
                    //余额组合支付
                    $combines = $post['combines']?$post['combines']:'';
                    if($combines && $combines['moneypay'] == 1){
                        $deal = self::deal_money_combine(aid,mid,$this->member,$post['typeid'],$combines,$payorder,$set);
                        if(!$deal || $deal['status'] == 0){
                            $msg = $deal['msg']?$deal['msg']:t('余额').'支付错误，请重试';
                            return $this->json(['status'=>0,'msg'=>$msg]);
                        }else if($deal['status'] == 2){
                            return $this->json(['status'=>2,'msg'=>$deal['msg']]);
                        }else{
                            $payorder['money'] = $deal['money'];
                        }
                    }
                }
				$buildfun = 'build_'.platform;
				$alih5 = input('param.alih5')?true:false;
                $openid = '';
                $openid_new = '';
                if(getcustom('maidan_auto_reg')){
                    //开启了买单不注册会员
                    $maidan_auto_reg = Db::name('admin_set')->where('aid',aid)->value('maidan_auto_reg');
                    if(!$maidan_auto_reg && !$this->mid && cache($this->sessionid.'_openid')){
                        $openid = cache($this->sessionid.'_openid');
                        $openid_new = cache($this->sessionid.'_openid_new');
                    }
                }
                if(getcustom('sxpay_apptowx') && platform=='app' ) {
                    $appinfo = \app\commons\System::appinfo(aid, 'app');
                    $appinfo_alipay = \app\commons\System::appinfo(aid, 'alipay');
                    if ($appinfo['alipay_type'] == 3) {
                        $appid = $appinfo_alipay['appid'];
                        $sessionid = \think\facade\Session::getId();;
                        $page = 'pagesB/pay/pay';
                        $query = 'session_id='.$sessionid.'&orderid='.$orderid.'&ali_appid='.$appid;
                        $scheme = 'alipays://platformapi/startapp?appId=' . $appid . '&page=' . $page . '&query=' . urlencode($query);
                        $ali_url = 'https://ds.alipay.com/?scheme=' . urlencode($scheme);
                        $rs['data']['is_jump'] = 1;
                        $rs['data']['jump_link'] = m_url('/pagesB/pay/pay?session_id=' . $sessionid .'&wx_url=' . urlencode($ali_url));
                        $rs['data']['wx_url'] = $ali_url;
                        return $this->json($rs);
                    }
                }
                if(getcustom('product_service_fee') && $payorder['service_fee_money'] > 0){
                    //减去服务费
                    $this->payServiceFee($payorder);
                }
				$rs = \app\commons\Alipay::$buildfun(aid,$payorder['bid'],mid,$payorder['title'],$payorder['ordernum'],$payorder['money'],$payorder['type'],'','',1,$alih5,$plugin_order_id,$openid,$openid_new);
				//元宝 更新payordr支付
                $this->yuanbao_up_pay(aid,$yuanbaopay,$orderid,$post['pay_type'],$payorder);
				return $this->json($rs);
			}elseif($post['typeid']==11){ //百度小程序支付
				$rs = \app\commons\Baidupay::build(aid,mid,$payorder['title'],$payorder['ordernum'],$payorder['money'],$payorder['type']);
				//元宝 更新payordr支付
                $this->yuanbao_up_pay(aid,$yuanbaopay,$orderid,$post['pay_type'],$payorder);
				return $this->json($rs);
			}elseif($post['typeid']==12){ //头条小程序支付
				$rs = \app\commons\Ttpay::build(aid,mid,$payorder['title'],$payorder['ordernum'],$payorder['money'],$payorder['type']);
				//元宝 更新payordr支付
                $this->yuanbao_up_pay(aid,$yuanbaopay,$orderid,$post['pay_type'],$payorder);
				return $this->json($rs);
			}elseif($post['typeid']==22){ //云收银
				$buildfun = 'build_'.platform;
				$rs = \app\commons\Yunpay::$buildfun(aid,mid,$payorder['title'],$payorder['ordernum'],$payorder['money'],$payorder['type'],'',m_url('pages/pay/pay?id='.$payorder['id']));
				//元宝 更新payordr支付
                $this->yuanbao_up_pay(aid,$yuanbaopay,$orderid,$post['pay_type'],$payorder);
				return $this->json($rs);
			}elseif($post['typeid']==23){
				$buildfun = 'build_h5';
				$rs = \app\commons\Qmpay::$buildfun(aid,mid,$payorder['title'],$payorder['ordernum'],$payorder['money'],$payorder['type'],'',m_url('pages/pay/pay?id='.$payorder['id']));
				//元宝 更新payordr支付
                $this->yuanbao_up_pay(aid,$yuanbaopay,$orderid,$post['pay_type'],$payorder);
				return $this->json($rs);
			}elseif($post['typeid']==24){
				$buildfun = 'build_h5_2';
				$rs = \app\commons\Qmpay::$buildfun(aid,mid,$payorder['title'],$payorder['ordernum'],$payorder['money'],$payorder['type'],'',m_url('pages/pay/pay?id='.$payorder['id']));
				//元宝 更新payordr支付
                $this->yuanbao_up_pay(aid,$yuanbaopay,$orderid,$post['pay_type'],$payorder);
				return $this->json($rs);
			}elseif($post['typeid']==41){ //月结
                if(getcustom('pay_month')){
                    $paytypeid=41;
                    if($payorder['type'] != 'shop') return $this->json(['status'=>0,'msg'=>'不支持该付款方式']);
                    $shopset = Db::name('shop_sysset')->where('aid',aid)->find();
                    $codtxt = $shopset['pay_month_txt'];
                    if(!$shopset['pay_month']) return $this->json(['status'=>0,'msg'=>'不支持'.$codtxt]);

                    if($payorder['score'] > 0){
                        //减去会员的积分
						$this->decscore($payorder,$decscore_params);
                    }
                    \app\models\Payorder::payorder($orderid,$codtxt,$paytypeid,'');
                    return $this->json(['status'=>2,'msg'=>'提交成功']);
                }
            }elseif($post['typeid']==51){ //paypal支付
				$rs = \app\customs\PayPal::build(aid,platform,$payorder);
				return $this->json($rs);
			}elseif($post['typeid']==61){ //汇付天下银联支付
				if(getcustom('pay_adapay')){
	                $buildfun = 'build_union_'.platform;
	                $rs = \app\customs\AdapayPay::$buildfun(aid,$payorder['bid'],mid,$payorder['title'],$payorder['ordernum'],$payorder['money'],$payorder['type']);
	                return $this->json($rs);
				}
            }elseif($post['typeid']==71){
			    //冻结佣金支付
                if(getcustom('commission_xiaofei')){
                    if($payorder['type'] == 'recharge') return $this->json(['status'=>0,'msg'=>'不能用'.t('冻结佣金').'支付']);
                    if($this->member['xiaofei_money'] < $payorder['money']){
                        return $this->json(['status'=>0,'msg'=>t('冻结佣金').'不足,请充值']);
                    }
                    if($payorder['money'] > 0){
                        //减去会员的余额
                        if($payorder['type'] == 'maidan'){
                            \app\commons\Member::addxiaofei(aid,mid,mid,-$payorder['money'],$payorder['title'].',订单号: '.$payorder['ordernum']);
                        }else{
                            \app\commons\Member::addxiaofei(aid,mid,mid,-$payorder['money'],'支付订单,订单号: '.$payorder['ordernum']);
                        }
                    }

                    \app\models\Payorder::payorder($orderid,t('余额').'支付',1,'');
                    //消费送积分
                    if($set['scorein_money']>0 && $set['scorein_score']>0 && $set['score_from_moneypay'] == 1){
                        $givescore = floor($payorder['money'] / $set['scorein_money']) * $set['scorein_score'];
                        $res = \app\commons\Member::addscore(aid,mid,$givescore,'消费送'.t('积分'));
                        if($res && $res['status'] == 1){
							//记录消费赠送积分记录
							\app\commons\Member::scoreinlog(aid,0,mid,$payorder['type'],$payorder['orderid'],$payorder['ordernum'],$givescore,$payorder['money']);
						}
                    }
                    return $this->json(['status'=>2,'msg'=>'付款成功']);
                }
            }elseif($post['typeid'] == 81){
                //餐饮收银台随行付支付,勿使用该值作为其他类型支付
            }elseif($post['typeid']==38){//信用付
                if(getcustom('member_overdraft_money')){
                    if($overdraft_moneypay==0){
                        return $this->json(['status'=>0,'msg'=>t('信用额度').'支付未开启']);
                    }
                    //信用额度支付
                    $open_overdraft_money = $this->member['open_overdraft_money']??0;
                    $limit_money = $this->member['limit_overdraft_money']??0;
                    if($open_overdraft_money == 0 && $limit_money == 0){
                        return $this->json(['status'=>0,'msg'=>t('信用额度').'不足']);
                    }
                    if($open_overdraft_money == 0 && $limit_money>0 && ($this->member['overdraft_money']-$payorder['money'] < $limit_money*-1)){
                        return $this->json(['status'=>0,'msg'=>t('信用额度').'不足']);
                    }
                    if($payorder['money'] > 0){
                        //减去会员的额度
                        \app\commons\Member::addOverdraftMoney(aid,mid,-$payorder['money'],'支付订单,订单号: '.$payorder['ordernum']);
                    }
                    if($payorder['score'] > 0){
                        //减去会员的积分
                        $this->decscore($payorder,$decscore_params);
                    }
                    \app\models\Payorder::payorder($orderid,'信用额度支付',38,'');
                    //消费送积分
                    if($set['scorein_money']>0 && $set['scorein_score']>0 && $set['score_from_moneypay'] == 1){
                        $givescore = floor($payorder['money'] / $set['scorein_money']) * $set['scorein_score'];
                        $res = \app\commons\Member::addscore(aid,mid,$givescore,'消费送'.t('积分'),$payorder['type'],0,0,1,['orderid'=>$payorder['orderid'],'ordernum'=>$payorder['ordernum'],'residue'=>$givescore]);
                        if($res && $res['status'] == 1){
							//记录消费赠送积分记录
							\app\commons\Member::scoreinlog(aid,0,mid,$payorder['type'],$payorder['orderid'],$payorder['ordernum'],$givescore,$payorder['money']);
						}
                    }
                    return $this->json(['status'=>2,'msg'=>'付款成功']);
                }
            }elseif($post['typeid']==75){  //微信
        		$openid = input('param.openid');
		  		$tourl = m_url($post['tourl']??'pages/index/index');
		  		if (platform=='app'  || platform=='wx') {
		  		    $buildfun = 'build_wx';
		  		}else {
		  		    $buildfun = 'build_mp';
		  		}
              	$rs = \app\commons\Sdbpay::$buildfun(aid,mid,'支付订单',$payorder['ordernum'],$payorder['money'],$payorder['type'],'',$tourl,$openid);
				if ($rs['status']==0) {
            		$rdata['status']= 0;
            		$rdata['msg']= $rs['msg'];
             	}else {
             	    $rdata['status']= 1;
            		$rdata['pay_info']= $rs['data'];
            		$rdata['data']= $rs['data'];
             	}
        		return $this->json($rdata);
			}elseif($post['typeid']==76){  //支付宝
				$buildfun = 'build_alipay';
				$tourl = m_url($post['tourl']??'pages/index/index');
				$buildfun = 'build_alipay';
				$rs = \app\commons\Sdbpay::$buildfun(aid,mid,'支付订单',$payorder['ordernum'],$payorder['money'],$payorder['type'],'',$tourl,platform);
	         
				return $this->json($rs);
			}elseif($post['typeid']==78){
				$bankid = $post['bankid'];
				$paycode = $post['paycode'];
				if ($paycode) {
				    $rs = \app\commons\Sdbpay::build_bank(aid,mid,'支付订单',$payorder['ordernum'],$payorder['money'],$payorder['type'],'',$paycode);
				}else {
				    $bank = Db::name('member_bank')->where('mid',mid)->where('id',$bankid)->find();
				    if(cache($payorder['ordernum'].$ordernum.$bank['cardNo'].'_paycodetimes') > time()){
    				      return $this->json(['status'=>1]);
        			}
				  	$rs = \app\commons\Sdbpay::build_bankbind(aid,mid,'支付订单',$payorder['ordernum'],$payorder['money'],$payorder['type'],'',$bankid);
				}
				return $this->json($rs);
			}
			if(getcustom('plug_more_alipay') && (platform == 'h5' || platform == 'app')){
				if($post['typeid']>=302 && $post['typeid']<=330){ //支付宝支付
					if(getcustom('pay_money_combine')){
                        //余额组合支付
                        $combines = $post['combines']?$post['combines']:'';
                        if($combines && $combines['moneypay'] == 1){
                            $deal = self::deal_money_combine(aid,mid,$this->member,$post['typeid'],$combines,$payorder,$set);
                            if(!$deal || $deal['status'] == 0){
                                $msg = $deal['msg']?$deal['msg']:t('余额').'支付错误，请重试';
                                return $this->json(['status'=>0,'msg'=>$msg]);
                            }else if($deal['status'] == 2){
                                return $this->json(['status'=>2,'msg'=>$deal['msg']]);
                            }else{
                                $payorder['money'] = $deal['money'];
                            }
                        }
                    }
					$more = $post['typeid'] - 300;
					$buildfun = 'build_'.platform;
					$rs = \app\commons\Alipay::$buildfun(aid,$payorder['bid'],mid,$payorder['title'],$payorder['ordernum'],$payorder['money'],$payorder['type'],'','',$more);
					//元宝 更新payordr支付
                	$this->yuanbao_up_pay(aid,$yuanbaopay,$orderid,$post['pay_type'],$payorder);
					return $this->json($rs);
				}
			}
		}

		$userinfo = [];
		$userinfo['money'] = $this->member['money'];
		$userinfo['score'] = dd_money_format($this->member['score'],$score_weishu);
        if(getcustom('scoreshop_otheradmin_buy')){
            //记录来源平台及来源平台用户
            if(($payorder['type']=='scoreshop' || $payorder['type']=='scoreshop_hb') && $BuyOverallScoreshop && $othermember){
                $userinfo['money'] = dd_money_format($othermember['money'],$this->moeny_weishu);
                $userinfo['score'] = dd_money_format($othermember['score'],$score_weishu);
            }
        }
        $userinfo['xiaofei_money'] = 0;
		if(getcustom('credit2_pay')){
            $userinfo['xiaofei_money'] = $this->member['credit2'];
        }

		if($this->member['paypwd']==''){
			$userinfo['haspwd'] = 0;
		}else{
			$userinfo['haspwd'] = 1;
		}
		
		$userinfo['paycheck'] = $this->sysset['money_pwd'];
		
		ll($this->sysset,'auto_day');
		
		$userinfo['yuanbao'] = 0;
		if(getcustom('pay_yuanbao')) {
            $userinfo['yuanbao'] = $this->member['yuanbao'];
        }
        if(getcustom('member_overdraft_money')){
            $userinfo['overdraft_money'] = $this->member['overdraft_money'];
            $userinfo['limit_overdraft_money'] = $this->member['limit_overdraft_money'];
            $open_overdraft_money = $this->member['open_overdraft_money'];
            $limit_money = $this->member['limit_overdraft_money'];
            $overdraft_money = $this->member['overdraft_money']*-1;
            if(empty($limit_money)){
                $overdraft_money_now = 0; 
            }else{
                $overdraft_money_now = round($limit_money - $overdraft_money,2);
            }
            if($open_overdraft_money == 1){
                $overdraft_money_now = '无限';
            }
            $userinfo['overdraft_money'] = $overdraft_money_now;
            
        }

        //订阅消息
		$tmplids = [];
		if(platform == 'wx' && in_array($payorder['type'],['shop','collage','scoreshop','kanjia','seckill'])){
			$wx_tmplset = Db::name('wx_tmplset')->where('aid',aid)->find();
			if($wx_tmplset['tmpl_orderfahuo_new']){
				$tmplids[] = $wx_tmplset['tmpl_orderfahuo_new'];
			}elseif($wx_tmplset['tmpl_orderfahuo']){
				$tmplids[] = $wx_tmplset['tmpl_orderfahuo'];
			}
			if($payorder['type'] == 'collage'){
				if($wx_tmplset['tmpl_collagesuccess_new']){
					$tmplids[] = $wx_tmplset['tmpl_collagesuccess_new'];
				}elseif($wx_tmplset['tmpl_collagesuccess']){
					$tmplids[] = $wx_tmplset['tmpl_collagesuccess'];
				}
			}
		}
        if(getcustom('choujiang_time') && 	platform == 'wx' && in_array($payorder['type'],['dscj'])){
            $wx_tmplset = Db::name('wx_tmplset')->where('aid',aid)->find();
            if($wx_tmplset['tmpl_choujiang']){
                $tmplids[] = $wx_tmplset['tmpl_choujiang'];
            }
        }

		if(getcustom('yuyue_apply') && 	platform == 'wx' && in_array($payorder['type'],['yuyue_workerapply'])){
			$wx_tmplset = Db::name('wx_tmplset')->where('aid',aid)->find();
			if($wx_tmplset['tmpl_shenhe_new']){
				$tmplids[] = $wx_tmplset['tmpl_shenhe_new'];
			}
		}

		if(getcustom('hotel') && platform == 'wx' && in_array($payorder['type'],['hotel'])){
			$wx_tmplset = Db::name('wx_tmplset')->where('aid',aid)->find();
			if($wx_tmplset['tmpl_hotelbooking_success']){
				$tmplids[] = $wx_tmplset['tmpl_hotelbooking_success'];
			}
			$order = Db::name('hotel_order')->where('aid',aid)->where('id',$payorder['orderid'])->find();
			if($order['use_money']>0) $tmplids[] = $wx_tmplset['tmpl_moneychange'];

		}

		$pay_transfer = 0;
		if($payorder['type'] == 'shop'){
			$shopset = Db::name('shop_sysset')->where('aid',aid)->find();
            if(getcustom('pay_transfer')){
                $gettj = explode(',',$set['pay_transfer_gettj']);
                //不是所有人
                if(in_array('-1',$gettj) || in_array($this->member['levelid'],$gettj)){
                    $pay_transfer = $set['pay_transfer'];
                }
            }
			\think\facade\Log::write(input('param.scene'));
			if(in_array(input('param.scene'),[1175,1176,1177,1191,1195,1216,10001])){
				$order = Db::name('shop_order')->where('id',$payorder['orderid'])->find();
				if($order['fromwxvideo'] == 0){
					Db::name('shop_order')->where('id',$payorder['orderid'])->update(['fromwxvideo'=>1,'scene'=>input('param.scene')]);
				}
			}
		}
		//\think\facade\Log::write($payorder);

		$rdata = [];
		$rdata['payorder'] = $payorder;
		$rdata['wxpay'] = $appinfo['wxpay']==1 ? 1 : 0;
		$rdata['wxpay_type'] = $appinfo['wxpay_type'];
		$rdata['alipay'] = $appinfo['alipay']==1 ? 1 : 0;
		$rdata['baidupay'] = $appinfo['baidupay']==1 ? 1 : 0;
		$rdata['toutiaopay'] = $appinfo['toutiaopay']==1 ? 1 : 0;
		$rdata['moneypay'] = ($set['moneypay'] && $payorder['type']!='recharge' && $payorder['type']!='overdraft_recharge') ? 1 : 0;
		$rdata['overdraft_moneypay'] = $overdraft_moneypay;
        $rdata['scorepay'] = 0;
		if(getcustom('business_moneypay')){ //多商户设置的是否可以用余额支付
			if($payorder['bid'] != 0){
				$moneypayset = Db::name('business_sysset')->where('aid',aid)->value('moneypay');
				if($moneypayset == 1) $rdata['moneypay'] = 1;
				if($moneypayset == 2) $rdata['moneypay'] = 0;
			}
		}
		if(getcustom('product_moneypay') && $payorder['type'] == 'shop'){ //商品中设置的是否可以用余额支付
			$hasnomoneypay = Db::name('shop_order_goods')->alias('og')->where('og.orderid',$order['id'])->join('shop_product product','og.proid=product.id')->field('product.product_moneypay')->find();
			if($hasnomoneypay){
				$rdata['moneypay'] = $rdata['moneypay']?$hasnomoneypay['product_moneypay']:0;
			}else{
                $rdata['moneypay'] = 0;
            }
		}
		
	    if (getcustom('product_scorepay') && $payorder['type'] == 'shop') { //商品中设置的是否可以用余额支付
            $hasnoscorepay = Db::name('shop_order_goods')->alias('og')->where('og.orderid', $order['id'])->where('product.product_scorepay', 'in', '1,2')->join('shop_product product', 'og.proid=product.id')->find();
            if($hasnoscorepay){
				$rdata['scorepay'] = $hasnoscorepay['product_scorepay'];
			}else{
                $rdata['scorepay'] = 0;
            }
        }
		
		
		
		
		
        $rdata['xiaofeipay'] = 0;
        if(getcustom('credit2_pay') && $payorder['type'] == 'shop'){ //商品中设置的是否可以用余额支付
            $hasnomoneypay = Db::name('shop_order_goods')->alias('og')->where('og.orderid',$order['id'])->join('shop_product product','og.proid=product.id')->field('product.product_xiaofeipay')->find();
            if($hasnomoneypay){
                $rdata['xiaofeipay'] = $hasnomoneypay['product_xiaofeipay'];
            }else{
                $rdata['xiaofeipay'] = 0;
            }
        }
        //收银台广告位
        if(getcustom('maidan_pay_ads')){
            $adlist = [];
            //找出bid
            $paytype = $payorder['type'];
            $bids = $payorder['bid']>0?[$payorder['bid']]:[];
            if(strpos($paytype, '_hb') !== false){
                $bidsHb = Db::name('payorder')->where('bid','>',0)->where('aid',$payorder['aid'])->where('ordernum', 'like',  $payorder['ordernum'] . '_%' )->column('bid');
                if(!empty($bidsHb)){
                    $bids = array_merge($bids,$bidsHb);
                }
            }
            //多商户的
            $bidFilter = [];
            if($bids) {
                foreach ($bids as $k => $bid) {
                    $bidFilter[] = "find_in_set({$bid},`bind_bids`)";
                }
            }
            $adwhere = [];
            $adwhere[] = ['aid','=',aid];
            $adwhere[] = ['status','=',1];
            $adwhere[] = Db::raw("find_in_set('-1',`scene`) OR find_in_set('pay',`scene`)");
            $whereStr = "is_bind_bid=0";
            if($bidFilter){
                $bidFilterString = implode(' OR ',$bidFilter);
                $whereStr = $whereStr." OR (is_bind_bid=1 and ({$bidFilterString}))";
            }
            $adwhere[] = Db::raw($whereStr);
            $adlist = Db::name('maidan_ads')->where($adwhere)->order('sort desc,id desc')->select()->toArray();
            $rdata['adlist'] = $adlist??[];
        }

		$rdata['pay_transfer'] = $pay_transfer;
        $rdata['userinfo'] = $userinfo;
		$rdata['cancod'] = $shopset['cancod'];//货到付款
		$rdata['codtxt'] = $shopset['codtxt'];
		$rdata['cod_frontmoney'] = 0;
		$rdata['cod_payorderid'] = 0;
		if(getcustom('shop_cod_frontpercent') && $shopset['cancod'] == 1 && $payorder['type'] == 'shop'){
			$frontorder = Db::name('payorder')->where('orderid',$payorder['orderid'])->where('type','shopfront')->find();
			if($frontorder){
				$rdata['cod_frontmoney'] = $frontorder['money'];
				$rdata['cod_payorderid'] = $frontorder['id'];
			}
		}
        $rdata['pay_month'] = $shopset['pay_month'] && getcustom('pay_month') ? 1 : 0;//月结
        $rdata['pay_month_txt'] = $shopset['pay_month_txt'];
		if($payorder['type'] == 'shop'){
			$rdata['give_coupon_list'] = \app\commons\Coupon::getpaygive(aid,mid,$payorder['type'],$payorder['money'],$payorder['orderid']);
		}else{
			$rdata['give_coupon_list'] = \app\commons\Coupon::getpaygive(aid,mid,$payorder['type'],$payorder['money'],$payorder['orderid']);
		}
        if(getcustom('invite_free')){
            $rdata['invite_free'] = '';
            $rdata['free_tmplids'] = '';
            $invite_free = Db::name('invite_free')->where('aid',aid)->where('status',1)->find();
            if($invite_free && $invite_free['gettj'] && $invite_free['start_time']<=time() && $invite_free['end_time']>=time()){
                $gettj = explode(',',$invite_free['gettj']);
                if(!in_array('-1',$gettj) && !in_array($this->member['levelid'],$gettj)){ //不是所有人
                    $rdata['invite_free'] = '';
                }else{
                    $rdata['invite_free'] = $invite_free;
                    if(platform == 'wx'){
                        $wx_tmplset = Db::name('wx_tmplset')->where('aid',aid)->field('tmpl_orderconfirm,tmpl_activity_notice')->find();
                        if($wx_tmplset['tmpl_orderconfirm']){
                            $free_tmplids[] = $wx_tmplset['tmpl_orderconfirm'];
                        }
                        if($wx_tmplset['tmpl_activity_notice']){
                            $free_tmplids[] = $wx_tmplset['tmpl_activity_notice'];
                        }
                        if($free_tmplids){
                            $rdata['free_tmplids'] = $free_tmplids;
                        }
                    }
                }
            }
        }
        if(getcustom('yuyue_before_starting')){
            //预约服务器通知
            if(platform == 'wx' && $payorder['type'] =='yuyue'){
                $wx_tmplset = Db::name('wx_tmplset')->where('aid',aid)->find();
                if($wx_tmplset['tmpl_yuyue_before_starting']){
                    $tmplids[] = $wx_tmplset['tmpl_yuyue_before_starting'];
                }
            }
        } 
		$rdata['tmplids'] = $tmplids;
		$rdata['detailurl'] = $detailurl;
		$rdata['tourl'] = $tourl;
		if($payorder['type'] == 'plug_businessqr_pay'){
			$rdata['wxpay'] = 0;$rdata['alipay'] = 0;
		}

		if(getcustom('restaurant') && $payorder['type'] == 'restaurant_shop') {
		    $sysset = Db::name('restaurant_shop_sysset')->where('aid', aid)->where('bid', $payorder['bid'])->find();
		    $paytype_rest = explode(',', $sysset['paytype']);

            $rdata['wxpay'] = $appinfo['wxpay']==1 && in_array('weixin', $paytype_rest) ? 1 : 0;
            $rdata['alipay'] = $appinfo['alipay']==1 && in_array('alipay', $paytype_rest) ? 1 : 0;
            $rdata['moneypay'] = $set['moneypay'] && in_array('money', $paytype_rest) && $payorder['type']!='recharge' ? 1 : 0;
            $rdata['cancod'] = in_array('cash', $paytype_rest) ? 1 : 0;//线下支付
            $rdata['codtxt'] = '线下支付';
        }
		if(getcustom('plug_zhangyuan')) $rdata['alipay'] = 2;

		//其他支付宝
		$rdata['more_alipay'] = 0;
		$rdata['more_alipay_data'] = [];
		if(getcustom('plug_more_alipay') && (platform == 'h5' || platform == 'mp' || platform == 'app')){
			$more_alipay_data = [];
			if(platform == 'mp'){
				$appinfo2 = \app\commons\System::appinfo(aid,'h5');
				$rdata['alipay'] = $appinfo2['alipay']==1 ? 1 : 0;
				for($i=2;$i<=30;$i++){
					if($appinfo2['alipay'.$i] == 1){
						$more_alipay_data[] = ['typeid'=>300 + $i,'name'=>$appinfo2['alipayname'.$i]];
					}
				}
			}else{
				for($i=2;$i<=30;$i++){
					if($appinfo['alipay'.$i] == 1){
						$more_alipay_data[] = ['typeid'=>300 + $i,'name'=>$appinfo['alipayname'.$i]];
					}
				}
			}
			if($more_alipay_data){
				$rdata['more_alipay'] = 1;
				$rdata['more_alipay_data'] = $more_alipay_data;
			}
		}
        if(getcustom('article_reward')){
            if($payorder['type']=='article_reward'){
                $rdata['pay_transfer'] = 0;
                $rdata['cancod']       = 0;//线下支付 货到付款
                $rdata['codtxt']       = '';
            }
        }
        $rdata['daifu'] = 0;
        if(getcustom('pay_daifu')){
            if($set['pay_daifu']){
                $rdata['daifu']  = 1;
                $rdata['daifu_txt'] = '好友代付';
                $rdata['daifu_share'] = [

                ];
            }
        }
        if(getcustom('wxpay_member_level')){
            //微信支付使用角色
            if($set && $set['wxpay_gettj'] && $rdata['wxpay'] != 0){
                $gettj = explode(',',$set['wxpay_gettj']);
                if(!in_array('-1',$gettj) && !in_array($this->member['levelid'],$gettj)){ //不是所有人
                    $rdata['wxpay'] = 0;
                }
            }
        }

        if(getcustom('paotui')){
        	if($payorder['type'] == 'paotui'){
        		$rdata['pay_transfer'] = 0;//转账
	            $rdata['cancod']       = 0;//线下支付 货到付款
	            $rdata['codtxt']       = '';
        	}
        }

        if(getcustom('yx_moneypay')){
        	if($payorder['type']=='shop' && $order['cuxiao_id']){
        		$cuxiao = Db::name('cuxiao')->where('id',$order['cuxiao_id'])->field('id,moneypay')->find();
        		if($cuxiao && !$cuxiao['moneypay']){
        			$rdata['moneypay'] = 0;
        		}
			}
        	if($payorder['type'] == 'collage' && $order['proid']){
        		$product = Db::name('collage_product')->where('id',$order['proid'])->field('id,moneypay')->find();
        		if($product && !$product['moneypay']){
        			$rdata['moneypay'] = 0;
        		}
			}
        }
        if(getcustom('collage_teampay')){
        	if($payorder['type'] == 'collage' && $order['proid'] && $order['buytype'] == 3){
        		$product = Db::name('collage_product')->where('id',$order['proid'])->field('id,teampay')->find();
        		//是否开启只发起者团长能使用余额支付，若是则其他参团者不能用余额支付
        		//是否单独开启
        		if($product && $product['teampay'] !=0){
        			if($product['teampay'] == -1){
        				$rdata['moneypay'] = 0;
        			}
        		}else{
        			$set = Db::name('collage_sysset')->where('aid',aid)->field('teampay')->find();
        			if($set){
        				if($set['teampay'] == 0){
        					$rdata['moneypay'] = 0;
        				}
        			}
        		}
			}
        }
        if(getcustom('sxpay_h5') ){
        	$rdata['alih5pay'] = true;
			if(platform == 'h5' && $rdata['alipay']==1){
				$rdata['alipay_type'] = $appinfo['alipay_type'];
				$alipayopenid = $this->member['alipayopenid'] && !empty($this->member['alipayopenid'])?$this->member['alipayopenid']:$this->member['alipayopenid_new'];
				$rdata['alipayopenid']      = $alipayopenid && !empty($alipayopenid)?$alipayopenid:'';//支付宝用id
				$rdata['ali_appid']   = '';//支付appid;
				if($appinfo['alipay_type'] == 3){
					$rdata['ali_appid']   = $appinfo['ali_appid'];
				}
			}
		}
		if(getcustom('money_dec')){
            //使用余额抵扣，则不显示余额支付
            if($rdata['moneypay'] == 1){
                if($payorder['type'] == 'shop' && $order['dec_money']>0){
                    $rdata['moneypay'] = 0;
                }
                if($payorder['type'] == 'shop_hb'){
                    $hblist = Db::name('shop_order')->where('aid',aid)->where('ordernum','like',$payorder['ordernum'].'%')->order('ordernum')->select()->toArray();
                    foreach($hblist as $hbv){
                        if($hbv['dec_money']>0){
                            $rdata['moneypay'] = 0;
                            break;
                        }
                    }
                }
                if(getcustom('maidan_money_dec')){
                	$dec_money = Db::name('maidan_order')->where('id',$payorder['orderid'])->value('dec_money');
					if($payorder['type'] == 'maidan' && $dec_money>0){
	                    $rdata['moneypay'] = 0;
	                }
		        }
            }
        }
        if(getcustom('alipay_plugin_trade')){
            $rdata['alipayPlugin'] = 1;
        }

        if(getcustom('maidan_auto_reg')){
            if(!$this->member['id']){
                $rdata['moneypay'] = 0;
                $rdata['overdraft_moneypay'] = 0;
                $rdata['xiaofeipay'] = 0;
                $rdata['yuanbaopay'] = 0;
            }
        }
		$rdata['paypal'] = $appinfo['paypal']==1 ? 1 : 0;
        if(getcustom('pay_adapay')){
            $rdata['adapay_union'] = $appinfo['adapay_union']==1 ? 1 : 0;
        }
        if(getcustom('pay_share')) {
            //付款前分享 （支持公众号、小程序）
            if (platform == 'mp' || platform == 'wx') {
                if ($payorder['type'] == 'shop') {
                    $shopset = Db::name('shop_sysset')->where('aid', aid)->find();
                    $rdata['share_payment'] = 0;
                    $share = [];
                    if ($shopset['share_payment'] == 1) {
                        $rdata['share_payment'] = 1;
                        //查询商品
                        if (!isset($order['id'])) {
                            $order = Db::name($payorder['type'] . '_order')->where('id', $payorder['orderid'])->find();
                        }
                        $productid = Db::name('shop_order_goods')->where('orderid', $order['id'])->value('proid');
                        $product_share = Db::name('shop_product')->field('id,name,pic,sharetitle,sharepic,sharedesc')->where('id', $productid)->find();
                        if ($product_share) {
                            $share['id'] = $product_share['id'];
                            $share['sharetitle'] = $product_share['sharetitle'] != '' ? $product_share['sharetitle'] : $product_share['name'];
                            $share['sharepic'] = $product_share['sharepic'] != '' ? $product_share['sharepic'] : $product_share['pic'];
                            $share['sharedesc'] = $product_share['sharedesc'];
                        }
                    }
                    $rdata['share_product'] = $share;
                }
            }
        }
		$rdata['yuanbao_money']  = $yuanbao_money;
        $rdata['total_yuanbao']  = $total_yuanbao;
        $rdata['yuanbao_msg']    = $yuanbao_msg;
        $rdata['yuanbaopay']     = $yuanbaopay;
        $rdata['sdb_wx'] = $set['sdb_wx']; 
        $rdata['sdb_ali'] = $set['sdb_ali'];
        $rdata['sdb_bank'] = $set['sdb_bank'];
        $rdata['gh_id'] = 'gh_c5ceaaacceb0';
        
        if (platform == 'h5' || platform == 'app' || platform == 'mp') { 
          //  $rdata['sdb_ali'] = 1;
        }
        if (platform == 'wx') { 
          //  $rdata['sdb_wx'] = 1;
        }
        if(getcustom('pay_money_combine')){
        	//是否开启余额和微信或支付组合支付（仅商城）
        	if($payorder['type'] == 'shop'){
            	$rdata['iscombine'] = $set['iscombine']==1 ? 1 : 0;
            }
        }
		return $this->json($rdata);
	}
	
	private function checkscore($payorder,$params=[]){
        $aid = aid;$mid = mid;
        $member= $this->member;
        $score = $this->member['score'];
        if(getcustom('scoreshop_otheradmin_buy')){
            if(($payorder['type']=='scoreshop' || $payorder['type']=='scoreshop_hb') && $params && $params['othermember']){
                $member= $params['othermember'];
                $aid   = $params['othermember']['aid'];
                $mid   = $params['othermember']['id'];
                $score = $params['othermember']['score'];
            }
        }
		if($payorder['bid']==0 && $payorder['type'] != 'shop_hb' && $payorder['type'] != 'scoreshop_hb' && $score < $payorder['score']){
			return ['status'=>0,'msg'=>t('积分').'不足'];
		}else{
			$business_selfscore = 0;
			if(getcustom('business_selfscore')){
				$bset = Db::name('business_sysset')->where('aid',$aid)->find();
				if($bset['business_selfscore'] == 1 && $bset['business_selfscore2'] == 1){
					$business_selfscore = 1;
				}
			}
			if($business_selfscore == 0 && $score < $payorder['score']){
				return ['status'=>0,'msg'=>t('积分').'不足'];
			}
			if($business_selfscore == 1){
				if($payorder['bid']==0 && $payorder['type'] != 'shop_hb' && $payorder['type'] != 'scoreshop_hb'){
					if($score < $payorder['score']){
						return ['status'=>0,'msg'=>t('积分').'不足'];
					}
				}else{
					if($payorder['type'] != 'shop_hb' && $payorder['type'] != 'scoreshop_hb'){
						$memberscore = Db::name('business_memberscore')->where('aid',$aid)->where('bid',$payorder['bid'])->where('mid',$mid)->value('score');
						if(!$memberscore) $memberscore = 0;
						if($memberscore < $payorder['score']){
							return ['status'=>0,'msg'=>t('积分').'不足'];
						}
					}else{
						$subpayorderlist = Db::name('payorder')->where('aid',$aid)->where('type','shop')->where('ordernum','like',$payorder['ordernum'].'_%')->select()->toArray();
						foreach($subpayorderlist as $subpayorder){
							if($subpayorder['score'] == 0) continue;
							if($subpayorder['bid'] == 0 && $score < $subpayorder['score']){
								return ['status'=>0,'msg'=>t('积分').'不足'];
							}elseif($subpayorder['bid'] != 0){
								$memberscore = Db::name('business_memberscore')->where('aid',$aid)->where('bid',$subpayorder['bid'])->where('mid',$mid)->value('score');
								if(!$memberscore) $memberscore = 0;
								if($memberscore < $subpayorder['score']){
									return ['status'=>0,'msg'=>t('积分').'不足'];
								}
							}
						}
					}
				}
			}
		}
		return ['status'=>1];
	}
	private function decscore($payorder,$params=[]){
        $aid = aid;$mid = mid;
        $member= $this->member;
        $score = $this->member['score'];
        $preRemark = '支付订单,订单号：';
        $addscore_params = [];//其他参数
        if(getcustom('scoreshop_otheradmin_buy')){
            if(($payorder['type']=='scoreshop' || $payorder['type']=='scoreshop_hb') && $params && $params['othermember']){
                $member= $params['othermember'];
                $aid   = $params['othermember']['aid'];
                $mid   = $params['othermember']['id'];
                $score = $params['othermember']['score'];
                $preRemark = '支付'.$params['othermember']['appname'].'订单,订单号';
                $addscore_params['optaid'] = aid;
            }
        }
		if($payorder['bid']==0 && $payorder['type'] != 'shop_hb' && $payorder['type'] != 'scoreshop_hb'){
			\app\commons\Member::addscore($aid,$mid,-$payorder['score'],$preRemark.$payorder['ordernum'],'',0,0,1,$addscore_params);
		}else{
			$business_selfscore = 0;
			if(getcustom('business_selfscore')){
				$bset = Db::name('business_sysset')->where('aid',$aid)->find();
				if($bset['business_selfscore'] == 1 && $bset['business_selfscore2'] == 1){
					$business_selfscore = 1;
				}
			}
			if($business_selfscore == 0){
				\app\commons\Member::addscore($aid,$mid,-$payorder['score'],$preRemark.$payorder['ordernum'],'',$payorder['bid'],0,1,$addscore_params);
				if(getcustom('business_selfscore') && $bset['business_selfscore'] == 1){
					\app\commons\Business::addscore($aid,$payorder['bid'],$payorder['score'],t('用户').$member['nickname'].'花费'.t('积分'));
				}
			}else{
				if($payorder['type'] != 'shop_hb' && $payorder['type'] != 'scoreshop_hb'){
					\app\commons\Business::addmemberscore($aid,$payorder['bid'],$mid,-$payorder['score'],$preRemark.$payorder['ordernum'],1);
				}else{
					$subpayorderlist = Db::name('payorder')->where('aid',$aid)->where('type','shop')->where('ordernum','like',$payorder['ordernum'].'_%')->select()->toArray();
					foreach($subpayorderlist as $subpayorder){
						if($subpayorder['score'] == 0) continue;
						if($subpayorder['bid'] == 0){
							\app\commons\Member::addscore($aid,$mid,-$subpayorder['score'],$preRemark.$subpayorder['ordernum'],'',0,0,1,$addscore_params);
						}elseif($subpayorder['bid'] != 0){
							\app\commons\Business::addmemberscore($aid,$subpayorder['bid'],$mid,-$subpayorder['score'],$preRemark.$subpayorder['ordernum'],1);
						}
					}
				}
			}
		}
	}

    private function payServiceFee($payorder){
        if($payorder['type'] == 'shop'){
            $check = Db::name('member_servicefee_log')->where('aid',aid)->where('mid',mid)->where('remark','支付订单,订单号: '.$payorder['ordernum'])->find();
            if(empty($check)){
                \app\commons\Member::addServiceFee(aid,mid,-$payorder['service_fee_money'],'支付订单,订单号: '.$payorder['ordernum']);
            }
        }
    }
	public function transfer()
    {
        if(getcustom('pay_transfer')){
            //转账汇款
            $id = input('param.id/d');
            $payorder = Db::name('payorder')->where('id',$id)->where('aid',aid)->where('mid',mid)->find();
            if(!$payorder){
                return $this->json(['status'=>0,'msg'=>'该订单不存在']);
            }

            if($payorder['type'] != 'shop' || $payorder['paytypeid'] != 5){
                return $this->json(['status'=>0,'msg'=>'订单不支持'.t('转账汇款')]);
            }

            $detail = Db::name('shop_order')->where('id',$payorder['orderid'])->where('aid',aid)->where('mid',mid)->find();
            if(!$detail) $this->json(['status'=>0,'msg'=>'订单不存在']);

            $set = Db::name('admin_set')->where('aid',aid)->field('pay_transfer_check')->find();
            if($set && $set['pay_transfer_check'] == 1 && $detail['transfer_check'] == 0){
                //需要审核
                return $this->json(['status'=>0,'msg'=>'该订单付款正在审核中']);
            }
            $detail['createtime'] = $detail['createtime'] ? date('Y-m-d H:i:s',$detail['createtime']) : '';

            $post = input('post.');
            if($post) {
                if($payorder['status']==1){
                    return $this->json(['status'=>0,'msg'=>'该订单已支付']);
                }
                if($payorder['check_status'] == 1) {
                    return $this->json(['status'=>0,'msg'=>'已审核通过，请勿重复提交']);
                }
                if(empty($post['pics'])) {
                    return $this->json(['status'=>0,'msg'=>'请上传付款凭证']);
                }
                $update['paypics'] = implode(',', $post['pics']);
                if(getcustom('pay_yuanbao') ){
                    $update['check_status'] = 1;
                    Db::name('payorder')->where('id',$id)->where('aid',aid)->where('mid',mid)->update($update);
                    Db::name('shop_order')->where('id',$payorder['orderid'])->where('aid',aid)->where('mid',mid)->update(['status'=>1,'paytime'=>time()]);
                    return $this->json(['status'=>1,'msg'=>'提交成功']);
                }else{
                    $update['check_status'] = 0;
                    Db::name('payorder')->where('id',$id)->where('aid',aid)->where('mid',mid)->update($update);
                    return $this->json(['status'=>1,'msg'=>'提交成功，等待审核']);
                }
            }

            $payorder['paypics'] =  $payorder['paypics'] ? explode(',', $payorder['paypics']) : [];
            if($payorder['check_status'] === 0) {
                $payorder['check_status_label'] = '待审核';
            }elseif($payorder['check_status'] == 1) {
                $payorder['check_status_label'] = '通过';
            }elseif($payorder['check_status'] == 2) {
                $payorder['check_status_label'] = '驳回';
            }else{
                $payorder['check_status_label'] = '未上传';
            }

            $set = Db::name('admin_set')->where('aid',aid)->find();
            $pay_transfer = 1;
            $pay_transfer_info['pay_transfer_account_name'] = $pay_transfer ? $set['pay_transfer_account_name'] : '';
            $pay_transfer_info['pay_transfer_account'] = $pay_transfer ? $set['pay_transfer_account'] : '';
            $pay_transfer_info['pay_transfer_bank'] = $pay_transfer ? $set['pay_transfer_bank'] : '';
            $pay_transfer_info['pay_transfer_desc'] = $pay_transfer ? $set['pay_transfer_desc'] : '';
            $pay_transfer_info['pay_transfer_qrcode'] = $pay_transfer ? $set['pay_transfer_qrcode'] : '';
            $pay_transfer_info['pay_transfer_qrcode_arr'] = $set['pay_transfer_qrcode'] ? explode(',',$set['pay_transfer_qrcode']) : [];
            
            
             $pay_transfer_info['pay_transfer_wei'] = $pay_transfer ? $set['pay_transfer_wei'] : '';
            $pay_transfer_info['pay_transfer_ali'] = $pay_transfer ? $set['pay_transfer_ali'] : '';
            $pay_transfer_info['aliimg'] = $pay_transfer ? $set['aliimg'] : '';
            $pay_transfer_info['weiimg'] = $pay_transfer ? $set['weiimg'] : '';
                
            
            
            $rdata['pay_transfer_info'] = $pay_transfer_info;
            $rdata['status'] = 1;
            $rdata['detail'] = $payorder;
            $rdata['orderDetail'] = $detail;

            return $this->json($rdata);
        }
    }

	//云收银 获取sessionkey所需要的参数
	public function getYunMpauthParams(){
		$jscode = input('post.jscode');
		$wxapp = \app\commons\System::appinfo(aid,'wx');
		//$url = 'https://showmoney.cn/scanpay/fixed/mpauth';
		$params = [];
		$params['busicd'] = 'WXAU';
		$params['chcd'] = 'WXP';
		$params['inscd'] = '92721888';
		$params['jsCode'] = $jscode;
		$params['mchntid'] = $wxapp['yun_mchntid'];
		$params['signType'] = 'SHA256';
		$params['subappid'] = $wxapp['appid'];
		$params['txndir'] = 'Q';
		$params['version'] = '2.3.9';

		ksort($params, SORT_STRING);
		$string1 = '';
		foreach ($params as $key => $v) {
			if (empty($v)) {
				continue;
			}
			$string1 .= "{$key}={$v}&";
		}
		$string1 = trim($string1,'&');
		$string1 .= $wxapp['yun_mchkey'];
		$params['sign'] = hash("sha256",$string1);
		return json(['status'=>1,'params'=>$params]);
	}
	//云收银 获取unified所需要的参数
	public function getYunUnifiedParams(){
		$orderid = input('post.orderid/d');
		$wxapp = \app\commons\System::appinfo(aid,'wx');
		//$url = 'https://showmoney.cn/scanpay/unified';

		$newordernum = date('ymdHis').rand(100000,999999);
		Db::name('payorder')->where('aid',aid)->where('id',$orderid)->update(['ordernum'=>$newordernum]);
		$payorder = Db::name('payorder')->where('aid',aid)->where('id',$orderid)->find();

		if($payorder['type'] == 'shop_hb'){
			$orderlist = Db::name('shop_order')->where('aid',aid)->where('ordernum','like',$payorder['ordernum'].'%')->order('ordernum')->select()->toArray();
			foreach($orderlist as $k=>$order){
				Db::name('shop_order')->where('id',$order['id'])->update(['ordernum'=>$newordernum.'_'.$k]);
			}
		}elseif($payorder['type'] == 'scoreshop_hb'){
			$orderlist = Db::name('scoreshop_order')->where('aid',aid)->where('ordernum','like',$payorder['ordernum'].'%')->order('ordernum')->select()->toArray();
			foreach($orderlist as $k=>$order){
				Db::name('scoreshop_order')->where('id',$order['id'])->update(['ordernum'=>$newordernum.'_'.$k]);
			}
		}else{
			Db::name($payorder['type'].'_order')->where('id',$payorder['orderid'])->update(['ordernum'=>$newordernum]);
		}

		$params = [];
		$params['backUrl'] = PRE_URL.'/notify.php';
		$params['busicd'] = 'WXMP';
		$params['charset'] = 'utf-8';
		$params['chcd'] = 'WXP';
		$params['inscd'] = '92721888';
		$params['mchntid'] = $wxapp['yun_mchntid'];
		$params['signType'] = 'SHA256';
		$params['subappid'] = $wxapp['appid'];
		$params['txndir'] = 'Q';
		$params['version'] = '2.3.9';
		$params['profitsharing'] = 'N';
		$params['sessionKey'] = input('param.sessionKey');
		$params['terminalType'] = 'miniProgPay';
		$params['subject'] = $payorder['title'];
		$params['orderNum'] = $payorder['ordernum'];
		$params['txamt'] = sprintf("%012d",$payorder['money']*100);

		ksort($params, SORT_STRING);
		$string1 = '';
		foreach ($params as $key => $v) {
			if (empty($v)) {
				continue;
			}
			$string1 .= "{$key}={$v}&";
		}
		$string1 = trim($string1,'&');
		$string1 .= $wxapp['yun_mchkey'];
		$params['sign'] = hash("sha256",$string1);
		return json(['status'=>1,'params'=>$params]);
	}
	//元宝 更新payorder支付参数
    private function yuanbao_up_pay($aid,$yuanbaopay,$orderid,$pay_type,$payorder){
        //元宝支付
        if(getcustom('pay_yuanbao') && $yuanbaopay){
            //如果支付类型是元宝 且是商城支付
            if($pay_type == 'yuanbao' && $payorder['type']=='shop'){
                //更新支pay付
                $up_pay = Db::name('payorder')->where('id',$orderid)->where('aid',$aid)->update(['is_yuanbao_pay'=>1,'yuanbao_money'=>$payorder['money']]);
            }else{
                $up_pay = Db::name('payorder')->where('id',$orderid)->where('aid',$aid)->update(['is_yuanbao_pay'=>0,'yuanbao_money'=>0]);
            }
        }
    }

    private function sendNotice($aid,$payorder,$order,$member)
    {
        $url_admin = 'admin/index/index';
        if($payorder['type'] == 'shop' || $payorder['type'] == 'balance'){
            $url_admin = 'admin/order/shoporder';
        }
        if($payorder['type'] == 'yuyue'){
            $detailurl = '/activity/yuyue/orderdetail?id='.$payorder['orderid'];
            $tourl = '/activity/yuyue/orderlist';
            $url_admin = 'admin/order/yuyueorder';
        }

        //公众号通知 订单支付成功
        $tmplcontent = [];
        if($order['paytypeid'] != 4) {
            $tmplcontent['first'] = '有新订单支付成功';
        } else {
            $tmplcontent['first'] = '有新订单下单成功（'.$order['paytype'].'）';
        }
        $tmplcontent['remark'] = '点击进入查看~';
        $tmplcontent['keyword1'] = $member['nickname']; //用户名
        $tmplcontent['keyword2'] = $order['ordernum'];//订单号
        $tmplcontent['keyword3'] = $order['totalprice'].'元';//订单金额
        $tmplcontent['keyword4'] = $order['title'];//商品信息
        $tmplcontentNew = [];
        $tmplcontentNew['thing8'] = \app\commons\Mendian::getNameWithBusines($order);//门店
        $tmplcontentNew['phrase18'] = $member['nickname']; //用户名
        $tmplcontentNew['character_string2'] = $order['ordernum'];//订单号
        $tmplcontentNew['amount5'] = $order['totalprice']==0?'0.00':$order['totalprice'];//订单金额
        $tmplcontentNew['thing3'] = $order['title'];//商品信息
        \app\commons\Wechat::sendhttmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,m_url($url_admin, $aid),$order['mdid'],$tmplcontentNew);


        if($order['paytypeid'] != 4) {
            $tmplcontent['first'] = '恭喜您的订单已支付成功';
        } else {
            $tmplcontent['first'] = '恭喜您的订单已下单成功';
        }
        $rs = \app\commons\Wechat::sendtmpl($aid,$member['id'],'tmpl_orderpay',$tmplcontent,m_url('pages/my/usercenter', $aid),$tmplcontentNew);

        $tmplcontent = [];
        $tmplcontent['thing11'] = $order['title'];
        $tmplcontent['character_string2'] = $order['ordernum'];
        if($order['paytypeid'] != 4) {
            $tmplcontent['phrase10'] = '已支付';
        }else{
            $tmplcontent['phrase10'] = $order['paytype'];
        }
        $tmplcontent['amount13'] = $order['totalprice'].'元';
        $tmplcontent['thing27'] = $member['nickname'];
        \app\commons\Wechat::sendhtwxtmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,$url_admin,$order['mdid']);
    }


    //代付
    public function daifu(){
	    if(getcustom('pay_daifu')) {
            $set = Db::name('admin_set')->where('aid', aid)->find();
            if (!$set['pay_daifu']) {
                return $this->json(['status' => 0, 'msg' => '该功能未开启']);
            }
            $orderid = input('param.orderid/d');
            $payorder = Db::name('payorder')->where('id', $orderid)->where('aid', aid)->find();
            if (!$payorder) {
                return $this->json(['status' => 0, 'msg' => '该订单不存在']);
            }
            if ($payorder['status'] == 1) {
                return $this->json(['status' => 0, 'msg' => '该订单已支付']);
            }
            $order = [];
            if(in_array($payorder['type'],[
            'shop','collage','cycle','kanjia','seckill','seckill2','scoreshop','restaurant_booking','restaurant_takeaway','choujiang','yuyue'
            ])){
                $order = Db::name($payorder['type'].'_order')->where('id',$payorder['orderid'])->find();
                if($order['status']==4){
                    return $this->json(['status'=>0,'msg'=>'该订单已关闭']);
                }elseif($order['status']!=0){
                    return $this->json(['status'=>0,'msg'=>'订单状态不符合']);
                }
                if($payorder['type'] == 'yuyue'){
                    $product = Db::name('yuyue_product')->field('pdprehour,yynum')->where('id',$order['proid'])->find();
                    $yydate = explode('-',$order['yy_time']);
                    //开始时间
                    $begindate = $yydate[0];
                    if(strpos($begindate,'年') === false){
                        $begindate = date('Y').'年'.$begindate;
                    }
                    $begindate = preg_replace(['/年|月/','/日/'],['-',''],$begindate);
                    $date = date('Y-m-d H:i:s',strtotime(date('H:i',time())));
                    $begintime = strtotime($begindate);
                    if($begintime <= strtotime(date('H:i',time()))+$product['pdprehour']*60*60){
                        return $this->json(['status'=>0,'msg'=>'预约时间已过，请选择其他时间']);
                    }
                    //查看是否已经存在
                    $yycount= Db::name($payorder['type'].'_order')->where('aid',aid)->where('yy_time',$order['yy_time'])->where('proid',$order['proid'])->where('mid','<>',$order['mid'])->where('status','in','1,2')->count();
                    if($yycount>=$product['yynum']){
                        return $this->json(['status'=>0,'msg'=>'该段时间预约人数已满']);
                    }
                }
                if($order['discount_rand_money'] > 0){
                    $payorder['discountText'] = '随机立减'.$order['discount_rand_money'];
                }
                if(getcustom('douyin_groupbuy')){
                    //抖音团购券再次验证
                    if($order['isdygroupbuy']==1){
                        return $this->json(['status'=>0,'msg'=>'抖音团购券不支持代付']);
                    }
                }
            }

            if($payorder['type'] == 'restaurant_shop') {
                $order = Db::name($payorder['type'].'_order')->where('id',$payorder['orderid'])->find();
                if($order['status']==4){
                    return $this->json(['status'=>0,'msg'=>'该订单已关闭']);
                }
            }else if ($payorder['type'] == 'collage') { //拼团
                $order = Db::name('collage_order')->where('id', $payorder['orderid'])->find();
                if ($order['buytype'] != 1) {
                    $team = Db::name('collage_order_team')->where('aid', aid)->where('id', $order['teamid'])->find();
                    if ($team['status'] == 2) {
                        return $this->json(['status' => 0, 'msg' => '该团已满员']);
                    }
                    if ($team['status'] == 3) {
                        return $this->json(['status' => 0, 'msg' => '该团已解散']);
                    }
                }
            } elseif ($payorder['type'] == 'lucky_collage') { //幸运拼团
                $order = Db::name('lucky_collage_order')->where('id', $payorder['orderid'])->find();
                if ($order['buytype'] != 1) {
                    $team = Db::name('lucky_collage_order_team')->where('aid', aid)->where('id', $order['teamid'])->find();
                    if ($team['status'] == 2) {
                        return $this->json(['status' => 0, 'msg' => '该团已满员']);
                    }
                    if ($team['status'] == 3) {
                        return $this->json(['status' => 0, 'msg' => '该团已解散']);
                    }
                }
            } else {
                //判断是否是合并订单
                $table = $payorder['type'];
                if(strpos($payorder['type'],'_hb')!==false){
                    $table = substr($payorder['type'],0,-3);
                    $order = Db::name($table.'_order')->where('id', $payorder['orderid'])->find();
	                if ($order['status'] == 4) {
	                    return $this->json(['status' => 0, 'msg' => '该订单已关闭']);
	                } elseif ($order['status'] != 0) {
	                    return $this->json(['status' => 0, 'msg' => '订单状态不符合']);
	                }
                }
            }

            if (input('param.op') == 'submit') {
                $post = input('param.');
                Db::name('payorder')->where('id', $orderid)->update(['platform' => platform]);
                if ($this->member['paypwd'] && $post['typeid'] == 1) { //余额支付 验证支付密码
                    if(!\app\commons\Member::checkPayPwd($this->member,$post['paypwd'] )){
                        return $this->json(['status'=>0,'msg'=>'支付密码输入错误']);
                    }
                }
                if ($payorder['money'] <= 0 && $payorder['score'] <= 0) {
                    \app\models\Payorder::payorder($orderid, '无须付款', 1, '');
					//\app\commons\notice\Notice::orderPay(aid,$payorder,mid,$this->member);
                    //$this->sendNotice(aid, $payorder, $order, $this->member);
                    return $this->json(['status' => 2, 'msg' => '付款成功']);
                }
				if ($payorder['score'] > 0) {
					$ckrs = $this->checkscore($payorder);
					if($ckrs['status'] == 0) return $this->json($ckrs);
                }
                //代付订单
                if ($payorder['mid'] != $this->mid) {
                    //重新创建订单
                    $newPayorder = $payorder;
                    $newPayorder['id'] = '';
                    $newPayorder['orderid'] = 0;//代付订单
                    $newPayorder['createtime'] = time();
                    $newPayorder['title'] = $payorder['title'].'(代付)';
                    $newPayorder['bid'] = $payorder['bid'];
                    $newPayorder['pid'] = $payorder['id'];
                    $newPayorder['mid'] = $this->mid;
                    $newPayorder['type'] = 'daifu';
                    $newPayorder['platform'] = platform;
                    $newPayorder['ordernum'] = \app\commons\Common::generateOrderNo($payorder['aid']);
                    $newpayorderid = Db::name('payorder')->insertGetId($newPayorder);
                    $newPayorder['id'] = $newpayorderid;
                    $payorder = $newPayorder;
                }
                if ($post['typeid'] == 1) { //余额支付
                    if ($payorder['type'] == 'recharge') return $this->json(['status' => 0, 'msg' => '不能用余额支付']);
                    if ($this->member['money'] < $payorder['money']) {
                        return $this->json(['status' => 0, 'msg' => t('余额') . '不足,请充值']);
                    }
                    if ($payorder['money'] > 0) {
                        //减去会员的余额
                        if ($payorder['type'] == 'maidan') {
                            \app\commons\Member::addmoney(aid, mid, -$payorder['money'], $payorder['title'] . ',订单号: ' . $payorder['ordernum']);
                        } else {
                            \app\commons\Member::addmoney(aid, mid, -$payorder['money'], '代付订单,订单号: ' . $payorder['ordernum']);
                        }
                    }
                    if ($payorder['score'] > 0) {
                        //减去会员的积分
                        $this->decscore($payorder);
                    }
                    \app\models\Payorder::payorder($orderid, t('余额') . '支付', 1, '');
                    return $this->json(['status' => 2, 'msg' => '付款成功']);
                } elseif ($post['typeid'] == 4) { //货到付款
                    if (getcustom('restaurant') && $payorder['type'] == 'restaurant_shop') {
                        $sysset = Db::name('restaurant_shop_sysset')->where('aid', aid)->where('bid', $payorder['bid'])->find();
                        $paytype_rest = explode(',', $sysset['paytype']);
                        $codtxt = '线下支付';
                        if (!in_array('cash', $paytype_rest)) return $this->json(['status' => 0, 'msg' => '不支持' . $codtxt]);
                    } else {
                        if ($payorder['type'] != 'shop') return $this->json(['status' => 0, 'msg' => '不支持该付款方式']);
                        $shopset = Db::name('shop_sysset')->where('aid', aid)->find();
                        $codtxt = $shopset['codtxt'];
                        if (!$shopset['cancod']) return $this->json(['status' => 0, 'msg' => '不支持' . $codtxt]);
                    }

                    if ($payorder['score'] > 0) {
                        //减去会员的积分
                        $this->decscore($payorder);
                    }
                    \app\models\Payorder::payorder($orderid, $codtxt, 4, '');
                    return $this->json(['status' => 2, 'msg' => '提交成功']);
                } elseif ($post['typeid'] == 5) {
                    if (getcustom('pay_transfer')) {
                        //转账汇款
                        if ($payorder['type'] != 'shop') return $this->json(['status' => 0, 'msg' => '不支持'.t('转账汇款')]);
                        $pay_transfer = Db::name('admin_set')->where('aid', aid)->value('pay_transfer');
                        if (!$pay_transfer) return $this->json(['status' => 0, 'msg' => '不支持该支付方式']);
                        if ($payorder['score'] > 0) {
                            //减去会员的积分
                            $this->decscore($payorder);
                        }
                        //转账后修改状态为已支付
                        $paytype = t('转账汇款');
                        $paytypeid = 5;
                //\app\models\Payorder::payorder($orderid,'转账汇款',5,'');
                        Db::name('payorder')->where('id', $orderid)->update(['paytype' => $paytype, 'paytypeid' => $paytypeid]);
                        Db::name('shop_order')->where('id', $payorder['orderid'])->update(['paytype' => $paytype, 'paytypeid' => $paytypeid, 'platform' => platform]);

                        return $this->json(['status' => 2, 'msg' => '提交成功']);
                    }
                } elseif ($post['typeid'] == 2) { //微信支付
                    $buildfun = 'build_' . platform;
                    Log::write('---------------wx_daifu------------------');
                    Log::write('mid='.mid);
                    Log::write('midp='.$payorder['mid']);
                    $rs = \app\commons\Wxpay::$buildfun(aid, $payorder['bid'], mid, '代付订单', $payorder['ordernum'], $payorder['money'], $payorder['type']);
                    Log::write($rs);
                    return $this->json($rs);
                } elseif ($post['typeid'] == 3) { //支付宝支付
                    $buildfun = 'build_' . platform;
                    $rs = \app\commons\Alipay::$buildfun(aid, $payorder['bid'], mid, '代付订单', $payorder['ordernum'], $payorder['money'], $payorder['type']);
                    return $this->json($rs);
                } elseif ($post['typeid'] == 11) { //百度小程序支付
                    $rs = \app\commons\Baidupay::build(aid, mid, '代付订单', $payorder['ordernum'], $payorder['money'], $payorder['type']);
                    return $this->json($rs);
                } elseif ($post['typeid'] == 12) { //头条小程序支付
                    $rs = \app\commons\Ttpay::build(aid, mid, '代付订单', $payorder['ordernum'], $payorder['money'], $payorder['type']);
                    return $this->json($rs);
                } elseif ($post['typeid'] == 22) { //云收银
                    $buildfun = 'build_' . platform;
                    $rs = \app\commons\Yunpay::$buildfun(aid, mid, '代付订单', $payorder['ordernum'], $payorder['money'], $payorder['type'], '', m_url('pages/pay/pay?id=' . $payorder['id']));
                    return $this->json($rs);
                } elseif ($post['typeid'] == 23) {
                    $buildfun = 'build_h5';
                    $rs = \app\commons\Qmpay::$buildfun(aid, mid, '代付订单', $payorder['ordernum'], $payorder['money'], $payorder['type'], '', m_url('pages/pay/pay?id=' . $payorder['id']));
                    return $this->json($rs);
                } elseif ($post['typeid'] == 24) {
                    $buildfun = 'build_h5_2';
                    $rs = \app\commons\Qmpay::$buildfun(aid, mid, '代付订单', $payorder['ordernum'], $payorder['money'], $payorder['type'], '', m_url('pages/pay/pay?id=' . $payorder['id']));
                    return $this->json($rs);
                } elseif ($post['typeid'] == 41) { //月结
                    if(getcustom('pay_month')){
                        $paytypeid = 41;
                        if ($payorder['type'] != 'shop') return $this->json(['status' => 0, 'msg' => '不支持该付款方式']);
                        $shopset = Db::name('shop_sysset')->where('aid', aid)->find();
                        $codtxt = $shopset['pay_month_txt'];
                        if (!$shopset['pay_month']) return $this->json(['status' => 0, 'msg' => '不支持' . $codtxt]);

                        if ($payorder['score'] > 0) {
                            //减去会员的积分
                            $this->decscore($payorder);
                        }
                        \app\models\Payorder::payorder($orderid, $codtxt, $paytypeid, '');
                        return $this->json(['status' => 2, 'msg' => '提交成功']);
                    }
                }
                if (getcustom('plug_more_alipay') && platform == 'h5') {
                    if ($post['typeid'] == 31 || $post['typeid'] == 32) { //支付宝支付
                        if ($post['typeid'] == 31) {
                            $more = 2;
                        }
                        if ($post['typeid'] == 32) {
                            $more = 3;
                        }

                        $buildfun = 'build_' . platform;
                        $rs = \app\commons\Alipay::$buildfun(aid, $payorder['bid'], mid, '代付订单', $payorder['ordernum'], $payorder['money'], $payorder['type'], '', '', $more);
                        return $this->json($rs);
                    }
                }
            }
            $orderMember = Db::name('member')->where('aid', aid)->where('id', $payorder['mid'])->field('id,nickname,headimg')->find();
            $orderGoods = [];
            if (in_array($payorder['type'], ['shop', 'restaurant_takeaway', 'restaurant_shop'])) {
                $orderGoods = Db::name($payorder['type'] . '_order_goods')->where('aid', aid)->where('orderid', $payorder['orderid'])->field('bid,name,ggname,num,totalprice,pic,real_totalprice')->select()->toArray();
            }elseif($payorder['type'] == 'xixie'){
                $orderGoods = Db::name($payorder['type'] . '_order_goods')->where('aid', aid)->where('orderid', $payorder['orderid'])->field('bid,name,"" ggname,num,totalprice,pic,real_totalprice')->select()->toArray();
            }elseif($payorder['type'] == 'scoreshop'){
                //无real_totalprice,ggname
                $orderGoods = Db::name('scoreshop_order_goods')->where('aid', aid)->where('orderid', $payorder['orderid'])->field('0 bid,name,"" ggname,num,totalscore totalprice,pic,0 real_totalprice')->select()->toArray();
            }elseif($payorder['type'] == 'restaurant_booking'){
                //无real_totalprice
                $orderGoods = Db::name('restaurant_booking_order_goods')->where('aid', aid)->where('orderid', $payorder['orderid'])->field('bid,name,"" ggname,num,totalprice,pic,0 real_totalprice')->select()->toArray();
            }elseif ($payorder['type'] == 'cycle') {
                $orderGoods = Db::name('cycle_order_stage')->where('aid', aid)->where('orderid', $payorder['orderid'])->field('bid,proname as name,ggname,num,sell_price as totalprice,propic as pic,0 real_totalprice')->select()->toArray();
            }elseif ($payorder['type'] == 'shop_hb') {
                $orderGoods = Db::name('shop_order_goods')->where('aid', aid)->where('ordernum', 'like', $payorder['ordernum'] . '_%')->field('bid,name,ggname,num,totalprice,pic,real_totalprice')->select()->toArray();
            }elseif ($payorder['type'] == 'scoreshop_hb') {
                $orderGoods = Db::name('scoreshop_order_goods')->where('aid', aid)->where('ordernum', 'like', $payorder['ordernum'] . '_%')->field('bid,name,ggname,num,totalprice,pic,0 real_totalprice')->select()->toArray();
            } elseif ($payorder['type'] == 'restaurant_takeaway_hb') {
                $orderGoods = Db::name('restaurant_takeaway_order_goods')->where('aid', aid)->where('ordernum', 'like', $payorder['ordernum'] . '_%')->field('bid,name,ggname,num,totalprice,pic,real_totalprice')->select()->toArray();
            } elseif ($payorder['type'] != 'balance' && $payorder['type'] != 'yuyue_balance' && $payorder['type'] != 'yuyue_addmoney') {
                $orderGoods = [];
            } else {
                if ($order) {
                    $orderGoods[] = [
                        'bid' => $order['bid'],
                        'name' => $order['title'] ?? $order['proname'],
                        'ggname' => '',
                        'num' => $order['num'] ?? 1,
                        'totalprice' => ($order['totalprice'] ?? $order['money']) ?? 0,
                        'real_totalprice' => $order['product_price']??0,
                        'pic' => $order['propic'] ?? '',
                    ];
                }
            }
            //订单信息按商家分组
            $newGoodsTemp = [];
            foreach ($orderGoods as $k=>$v){
                if($v['bid']){
                    $business = Db::name('business')->where('aid',aid)->where('id',$v['bid'])->field('id,name,logo')->find();
                    $v['bname'] = $business['name']??'';
                }else{
                    $v['bname'] = $set['name'];
                }
                $newGoodsTemp[$v['bid']][] = $v;
            }
            $newGoods = [];
            foreach ($newGoodsTemp as $bid=>$goods){
                $newGoods[] = [
                    'bid'=>$goods[0]['bid'],
                    'bname'=>$goods[0]['bname'],
                    'goodslist'=>$goods,
                ];
            }

            $userinfo = [];
            $userinfo['money'] = $this->member['money'];
            $userinfo['score'] = $this->member['score'];
            if ($this->member['paypwd'] == '') {
                $userinfo['haspwd'] = 0;
            } else {
                $userinfo['haspwd'] = 1;
            }
            $userinfo['yuanbao'] = 0;
            if (getcustom('pay_yuanbao')) {
                $userinfo['yuanbao'] = $this->member['yuanbao'];
            }
            //订阅消息
            $tmplids = [];
            if (platform == 'wx' && in_array($payorder['type'], ['shop', 'collage', 'scoreshop', 'kanjia', 'seckill'])) {
                $wx_tmplset = Db::name('wx_tmplset')->where('aid', aid)->find();
                if ($wx_tmplset['tmpl_orderfahuo_new']) {
                    $tmplids[] = $wx_tmplset['tmpl_orderfahuo_new'];
                } elseif ($wx_tmplset['tmpl_orderfahuo']) {
                    $tmplids[] = $wx_tmplset['tmpl_orderfahuo'];
                }
                if ($payorder['type'] == 'collage') {
                    if ($wx_tmplset['tmpl_collagesuccess_new']) {
                        $tmplids[] = $wx_tmplset['tmpl_collagesuccess_new'];
                    } elseif ($wx_tmplset['tmpl_collagesuccess']) {
                        $tmplids[] = $wx_tmplset['tmpl_collagesuccess'];
                    }
                }
            }

            $pay_transfer = 0;
            if ($payorder['type'] == 'shop') {
                $shopset = Db::name('shop_sysset')->where('aid', aid)->find();
                if (getcustom('pay_transfer')) {
                    $pay_transfer = $set['pay_transfer'];
                }
                \think\facade\Log::write(input('param.scene'));
                if (in_array(input('param.scene'), [1175, 1176, 1177, 1191, 1195, 1216, 10001])) {
                    $order = Db::name('shop_order')->where('id', $payorder['orderid'])->find();
                    if ($order['fromwxvideo'] == 0) {
                        Db::name('shop_order')->where('id', $payorder['orderid'])->update(['fromwxvideo' => 1, 'scene' => input('param.scene')]);
                    }
                }
            }
            //\think\facade\Log::write($payorder);

            $detailurl = '';
            $tourl = '/pages/my/usercenter';
            if ($payorder['type'] == 'shop' || $payorder['type'] == 'balance') {
                $detailurl = '/pagesExt/order/detail?id=' . $payorder['orderid'];
            }
            if ($payorder['type'] == 'collage') {
                $detailurl = '/activity/collage/orderdetail?id=' . $payorder['orderid'];
                $tourl = '/activity/collage/orderlist';
            }
            if ($payorder['type'] == 'kanjia') {
                $detailurl = '/activity/kanjia/orderdetail?id=' . $payorder['orderid'];
                $tourl = '/activity/kanjia/orderlist';
            }
            if ($payorder['type'] == 'seckill') {
                $detailurl = '/activity/seckill/orderdetail?id=' . $payorder['orderid'];
                $tourl = '/activity/seckill/orderlist';
            }
            if ($payorder['type'] == 'scoreshop') {
                $detailurl = '/activity/scoreshop/orderdetail?id=' . $payorder['orderid'];
                $tourl = '/activity/scoreshop/orderlist';
            }
            if ($payorder['type'] == 'designerpage') {
                $order = Db::name('designerpage_order')->where('id', $payorder['orderid'])->find();
                $tourl = '/pages/index/main?id=' . $order['pageid'];
            }
            if ($payorder['type'] == 'restaurant_shop') {
                $detailurl = '/restaurant/shop/orderdetail?id=' . $payorder['orderid'];
                $tourl = '/restaurant/shop/orderlist';
            }
            if ($payorder['type'] == 'restaurant_takeaway') {
                $detailurl = '/restaurant/takeaway/orderdetail?id=' . $payorder['orderid'];
                $tourl = '/restaurant/takeaway/orderlist';
            }
            if ($payorder['type'] == 'restaurant_booking') {
                $detailurl = '/restaurant/booking/detail?id=' . $payorder['orderid'];
                $tourl = '/restaurant/booking/orderlist';
            }
            if ($payorder['type'] == 'seckill2') {
                $detailurl = '/activity/seckill2/orderdetail?id=' . $payorder['orderid'];
                $tourl = '/activity/seckill2/orderlist';
            }
            if ($payorder['type'] == 'yuyue') {
                $detailurl = '/activity/yuyue/orderdetail?id=' . $payorder['orderid'];
                $tourl = '/activity/yuyue/orderlist';
            }
            if ($payorder['type'] == 'kecheng') {
                $kcorder = Db::name('kecheng_order')->where('id', $payorder['orderid'])->find();
                $detailurl = '/activity/kecheng/product?id=' . $kcorder['kcid'];
                $tourl = '/activity/kecheng/product?id=' . $kcorder['kcid'];
            }
            if ($payorder['type'] == 'tuangou') {
                $detailurl = '/activity/tuangou/orderdetail?id=' . $payorder['orderid'];
                $tourl = '/activity/tuangou/orderlist';
            }
            if ($payorder['type'] == 'lucky_collage') {
                $detailurl = '/activity/luckycollage/orderdetail?id=' . $payorder['orderid'];
                $tourl = '/activity/luckycollage/orderlist';
            }
            if ($payorder['type'] == 'workorder') {
                $detailurl = '/pagesB/workorder/detail?id=' . $payorder['orderid'];
                $tourl = '/pagesB/workorder/record';
            }
            if ($payorder['type'] == 'business_recharge') {
                $detailurl = '/admin/index/index';
                $tourl = '/admin/index/index';
            }
            if ($payorder['type'] == 'maidan') {
                $tourl = '/pages/maidan/maidanlog';
            }
            if ($payorder['type'] == 'yuyue_workerapply') {
                $yuyueset = Db::name('yuyue_set')->field('apply_url')->where('aid', aid)->find();
                $tourl = $yuyueset['apply_url'] ? $yuyueset['apply_url'] : '/activity/yuyue/apply';
            }
            if (getcustom('xixie')) {
                if ($payorder['type'] == 'xixie') {
                    $detailurl = '/pagesExt/xixie/orderdetail?id=' . $payorder['orderid'];
                }
            }
            if ($payorder['type'] == 'yueke') {
                $detailurl = '/pagesExt/yueke/orderdetail?id=' . $payorder['orderid'];
                $tourl = '/pagesExt/yueke/orderlist';
            }
            $appinfo = \app\commons\System::appinfo(aid, platform);
            if ($payorder['type'] == 'shop') {
                if (getcustom('payaftertourl')) {
                    $product = Db::name('shop_order_goods')->alias('og')->where('og.orderid', $order['id'])->where('product.payaftertourl', '<>', '')->where('product.payaftertourl', 'not null')->join('shop_product product', 'og.proid=product.id')->find();
                    $payaftertourl = $product['payaftertourl'];
                    $payorder['payafterbtntext'] = $product['payafterbtntext'];
                    if ($payaftertourl) {
                        if (strpos($payaftertourl, 'miniProgram::') === 0) {
                            if (platform == 'mp') {
                                $afterurl = explode('|', str_replace('miniProgram::', '', $payaftertourl));
                                $payorder['payafter_username'] = $afterurl[2];
                                $payorder['payafter_path'] = $afterurl[1] . (strpos($afterurl[1], '?') !== false ? '&' : '?') . 'appid=' . $appinfo['appid'] . '&uid=' . mid . '&ordernum=' . $payorder['ordernum'];
                                \think\facade\Log::write($payorder['payafter_path']);
                            } else {
                                $afterurl = explode('|', $payaftertourl);
                                $payaftertourl = $afterurl[0] . '|' . $afterurl[1];
                                $payaftertourl = $payaftertourl . (strpos($payaftertourl, '?') !== false ? '&' : '?') . 'appid=' . $appinfo['appid'] . '&uid=' . mid . '&ordernum=' . $payorder['ordernum'] . '|' . $detailurl;
                            }
                        }
                        $tourl = $payaftertourl;
                    }
                }
            }
            if (input('param.tourl')) $tourl = input('param.tourl');
            $rdata = [];
            $rdata['payorder'] = $payorder;
            $rdata['wxpay'] = $appinfo['wxpay'] == 1 ? 1 : 0;
            $rdata['wxpay_type'] = $appinfo['wxpay_type'];
            $rdata['alipay'] = $appinfo['alipay'] == 1 ? 1 : 0;
            $rdata['baidupay'] = $appinfo['baidupay'] == 1 ? 1 : 0;
            $rdata['toutiaopay'] = $appinfo['toutiaopay'] == 1 ? 1 : 0;
            $rdata['moneypay'] = $set['moneypay'] && $payorder['type'] != 'recharge' ? 1 : 0;

            if (getcustom('business_moneypay')) { //多商户设置的是否可以用余额支付
                if ($payorder['bid'] != 0) {
                    $moneypayset = Db::name('business_sysset')->where('aid', aid)->value('moneypay');
                    if ($moneypayset == 1) $rdata['moneypay'] = 1;
                    if ($moneypayset == 2) $rdata['moneypay'] = 0;
                }
            }
            if (getcustom('product_moneypay') && $payorder['type'] == 'shop') { //商品中设置的是否可以用余额支付
                $hasnomoneypay = Db::name('shop_order_goods')->alias('og')->where('og.orderid', $order['id'])->where('product.product_moneypay', '=', '0')->join('shop_product product', 'og.proid=product.id')->find();
                if ($hasnomoneypay) {
                    $rdata['moneypay'] = 0;
                }
            }

            $rdata['pay_transfer'] = $pay_transfer;
            $rdata['userinfo'] = $userinfo;
            $rdata['cancod'] = $shopset['cancod'];//货到付款
            $rdata['codtxt'] = $shopset['codtxt'];
            $rdata['pay_month'] = $shopset['pay_month'] && getcustom('pay_month') ? 1 : 0;//月结
            $rdata['pay_month_txt'] = $shopset['pay_month_txt'];
            if ($payorder['type'] == 'shop') {
                $rdata['give_coupon_list'] = \app\commons\Coupon::getpaygive(aid, mid, $payorder['type'], $payorder['money'], $payorder['orderid']);
            } else {
                $rdata['give_coupon_list'] = \app\commons\Coupon::getpaygive(aid, mid, $payorder['type'], $payorder['money']);
            }
            $rdata['tmplids'] = $tmplids;
            $rdata['detailurl'] = $detailurl;
            $rdata['tourl'] = $tourl;
            if ($payorder['type'] == 'plug_businessqr_pay') {
                $rdata['wxpay'] = 0;
                $rdata['alipay'] = 0;
            }

            if (getcustom('restaurant') && $payorder['type'] == 'restaurant_shop') {
                $sysset = Db::name('restaurant_shop_sysset')->where('aid', aid)->where('bid', $payorder['bid'])->find();
                $paytype_rest = explode(',', $sysset['paytype']);

                $rdata['wxpay'] = $appinfo['wxpay'] == 1 && in_array('weixin', $paytype_rest) ? 1 : 0;
                $rdata['alipay'] = $appinfo['alipay'] == 1 && in_array('alipay', $paytype_rest) ? 1 : 0;
                $rdata['moneypay'] = $set['moneypay'] && in_array('money', $paytype_rest) && $payorder['type'] != 'recharge' ? 1 : 0;
                $rdata['cancod'] = in_array('cash', $paytype_rest) ? 1 : 0;//线下支付
                $rdata['codtxt'] = '线下支付';
            }
            if (getcustom('plug_zhangyuan')) $rdata['alipay'] = 2;

            //其他支付宝
            $rdata['more_alipay'] = 0;
            if (getcustom('plug_more_alipay') && platform == 'h5') {
                $rdata['more_alipay'] = 1;
                $rdata['alipay2'] = $appinfo['alipay2'] == 1 ? 1 : 0;
                $rdata['alipay3'] = $appinfo['alipay3'] == 1 ? 1 : 0;
            }
            $orderMember['platform_logo'] = $set['logo'];
            $rdata['daifu'] = 0;
            $rdata['daifu_txt'] = t('好友代付');
            $rdata['daifu_desc'] = $set['pay_daifu_desc']?addslashes($set['pay_daifu_desc']):'';
            $rdata['order_member'] = $orderMember;
            $rdata['order_goods'] = $newGoods;
            return $this->json($rdata);
        }
    }

	public function paypalRedirect(){
		$rs = \app\customs\PayPal::payRedirect();
		return $this->json($rs);
	}

	//关闭webview 跳转回到支付页面
	public function webviewjump(){

		$paramsdata = input('param.');
		foreach($paramsdata as $key => $val){
			$val = str_replace('=','-',$val);
			$params[] = "$key=$val";
		}
		$paramstr = implode("&",$params);

		echo '<!DOCTYPE html><html>'."\r\n";
		echo '<head>'."\r\n";
		echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />'."\r\n";
		echo '<meta name="viewport" content="width=device-width,minimum-scale=1.0,minimum-scale=1.0,maximum-scale=1.0,initial-scale=1.0,user-scalable=no" />'."\r\n";
		echo '<meta name="robots" content="index, follow" />'."\r\n";
		echo '<title>信息提示</title>'."\r\n";
		if($paramsdata['fromplat'] == 'wx'){
			echo '<script type="text/javascript" src="https://res.wx.qq.com/open/js/jweixin-1.3.2.js"></script>'."\r\n";
		}else{
			echo '<script type="text/javascript" src="/static/js/uni.webview.1.5.4.js"></script>'."\r\n";
		}
		echo '</head>'."\r\n";
		echo '<body>'."\r\n";
		echo '<h2 style="text-align:center;padding-top:100px">支付跳转中...</h2>'."\r\n";
		if($paramsdata['fromplat'] == 'wx'){
			echo '<script>';
			//echo 'function gotopay(){';
			echo '	wx.miniProgram.redirectTo({ url:"/pagesExt/pay/pay?'.$paramstr.'"});';
			//echo '}';
			echo '</script>';
		}else{
			echo '<script>'."\r\n";
			echo 'document.addEventListener(\'UniAppJSBridgeReady\', function() {'."\r\n";
			echo '	uni.redirectTo({'."\r\n";
			echo '	  url:"/pagesExt/pay/pay?'.$paramstr.'"'."\r\n";
			echo '	});'."\r\n";
			echo '});'."\r\n";
			echo '</script>'."\r\n";
		}
		echo '</body></html>';
		die;
	}

	public static function deal_money_combine($aid,$mid,$member,$typeid,$combines,$payorder,$set){
        if(getcustom('pay_money_combine')){
            //余额组合支付
            if($combines && $combines['moneypay'] == 1){
                //是否开启余额和微信或支付组合支付（仅商城）
                if($payorder['type'] == 'shop'){
                    if($set['iscombine'] != 1){
                        return ['status'=>0,'msg'=>'系统暂未开启'.t('余额').'组合支付'];
                    }
                    //可扣除多少
                    if($member['money']>0){
                        $cha = $member['money']-$payorder['money'];
                        //扣除全部，直接是余额支付
                        if($cha>=0){
                            //减去会员的余额
                            $res = \app\commons\Member::addmoney($aid,$mid,-$payorder['money'],'支付订单,订单号: '.$payorder['ordernum']);
                            if($res['status'] == 1){
                                $res = \app\models\Payorder::payorder($payorder['id'],t('余额').'支付',1,'');

                                Db::name('shop_order')->where('id',$payorder['orderid'])->update(['combine_money'=>0,'combine_wxpay'=>0,'combine_alipay'=>0]);
                                return ['status'=>2,'msg'=>'付款成功'];
                            }else{
                                return ['status'=>0,'msg'=>$res['msg']?$res['msg']:t('余额').'支付出错，请重试'];
                            }
                        //扣除余额部分 剩余部分微信支付
                        }else{
                            //减去会员的余额
                            $res = \app\commons\Member::addmoney($aid,$mid,-$member['money'],'支付订单,订单号: '.$payorder['ordernum']);
                            if($res['status'] != 1){
                                return ['status'=>0,'msg'=>$res['msg']?$res['msg']:t('余额').'支付出错，请重试'];
                            }
                            //微信或支付宝支付的部分
                            $payorder['money'] = $payorder['money']-$member['money'];
                            $payorder['money'] = round($payorder['money'],2);
                            //修改支付表
                            Db::name('payorder')->where('id',$payorder['id'])->update(['money'=>$payorder['money']]);

                            //修改订单实际支付金额和组合支付
                            $updata = [];
                            if($typeid == 2){
                                $updata['combine_wxpay']  = $payorder['money'];
                                $updata['combine_alipay'] = 0;
                            }else if($typeid == 3 || ($typeid>=302 && $typeid<=330)){
                                $updata['combine_wxpay']  = 0;
                                $updata['combine_alipay'] = $payorder['money'];
                            }
                            Db::name('shop_order')->where('id',$payorder['orderid'])->inc('combine_money',$member['money'])->update($updata);
                        }
                    }
                }else{
                    return ['status'=>0,'msg'=>'仅商城商品支持'.t('余额').'组合支付'];
                }
            }
            return ['status'=>1,'money'=>$payorder['money']];
        }
    }

    public function getOpenid(){
	    if(getcustom('sxpay_apptowx')){
            $member_info = Db::name('member')->where('id',mid)->find();
            $openid = '';
            if(platform=='wx'){
                $openid = $member_info['wxopenid'];
            }else{
                $openid = $member_info['alipayopenid'];
            }
            return $this->json(['status'=>0,'openid'=>$openid]);
        }

    }
    public function sxfpay_app(){
        if(getcustom('sxpay_apptowx')) {
            $orderid = input('param.orderid/d');
            $payorder = Db::name('payorder')->where('id', $orderid)->where('aid', aid)->find();

            $aid = aid;
            $notify_url = PRE_URL . '/notify.php';

            if (platform == 'wx') {
                $rs = \app\customs\Sxpay::build_wx($aid, $payorder['bid'], mid, $payorder['title'], $payorder['ordernum'], $payorder['money'], $payorder['type'], $notify_url, 0, 'app');
            } else {
                $rs = \app\customs\Sxpay::build_alipay($aid, $payorder['bid'], mid, $payorder['title'], $payorder['ordernum'], $payorder['money'], $payorder['type'], $notify_url, 'app');
            }
            return $this->json($rs);
        }
    }

    /**
     * 付款前分享：分享成功后修改分享订单状态
     */
    public function sharePaymentStatus(){
        if(getcustom('pay_share')) {
            $orderid = input('param.orderid/d');
            $payorder = Db::name('payorder')->where('id', $orderid)->where('aid', aid)->find();
            if (!$payorder) {
                return $this->json(['status' => 0, 'msg' => '该订单不存在']);
            }
            $res = Db::name('payorder')->where('id', $orderid)->update(['share_payment_status' => 1]);
            if ($res) {
                return $this->json(['status' => 1, 'msg' => 'ok']);
            }
            return $this->json(['status' => 0, 'msg' => 'error']);
        }
    }
}
