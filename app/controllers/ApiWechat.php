<?php


namespace app\controllers;
use app\BaseController;
use think\facade\Db;
use think\facade\Log;
class ApiWechat extends BaseController
{
	public $aid;
	public $set;
	public $appinfo;
	public $componentinfo;
    public function initialize(){

	}
	
	public function auth(){
		//Log::write('11');
		$componentinfo = Db::name('sysset')->where('name','component')->value('value');
		$componentinfo = json_decode($componentinfo,true);
		$this->componentinfo = $componentinfo;
		$wxcpt = new \app\commons\WxBizMsgCrypt($this->componentinfo['token'], $this->componentinfo['key'], $this->componentinfo['appid']);
		$postStr = file_get_contents('php://input');
		//Log::write($postStr);
		if (!empty($postStr)){
			$msg_sign = $_GET['msg_signature'];
			$timeStamp= $_GET['timestamp'];
			$nonce= $_GET['nonce'];
			$msg = '';
			$errCode = $wxcpt->decryptMsg($msg_sign, $timeStamp, $nonce, $postStr, $msg);
			if($errCode == 0){// 解密成功，$msg即为xml格式的明文
				$postObj = simplexml_load_string($msg, 'SimpleXMLElement', LIBXML_NOCDATA);
				if($postObj->InfoType == 'unauthorized') {//取消授权
					$appid = strval($postObj->AuthorizerAppid);
					Db::name('admin_setapp_wx')->where('appid',$appid)->where('authtype',1)->delete();
					Db::name('admin_setapp_mp')->where('appid',$appid)->where('authtype',1)->delete();
					Db::name('access_token')->where('appid',$appid)->delete();
				}elseif($postObj->InfoType == 'authorized' || $postObj->InfoType == 'updateauthorized'){
					
				}elseif($postObj->InfoType == 'component_verify_ticket'){
					cache('component_verify_ticket',strval($postObj->ComponentVerifyTicket));
				}elseif($postObj->InfoType == 'notify_third_fasteregister'){
					//Log::write($postObj);
					$info = (array)$postObj->info;
					$errmsg = '';
					$status = 1;
					$reglog = Db::name('admin_wxreglog')->where('status',0)->where('name',strval($info['name']))->where('code',strval($info['code']))->where('legal_persona_wechat',strval($info['legal_persona_wechat']))->where('legal_persona_name',strval($info['legal_persona_name']))->order('id desc')->find();
					$aid = $reglog['aid'];
					if($postObj->status!=0){
						$errarr = array(
							'100001'=>'已下发的模板消息法人并未确认且已超时（24h），未进行身份证校验',
							'100002'=>'已下发的模板消息法人并未确认且已超时（24h），未进行人脸识别校验',
							'100003'=>'已下发的模板消息法人并未确认且已超时（24h）',
							'101'=>'工商数据返回：“企业已注销”',
							'102'=>'工商数据返回：“企业不存在或企业信息未更新”',
							'103'=>'工商数据返回：“企业法定代表人姓名不一致”',
							'104'=>'工商数据返回：“企业法定代表人身份证号码不一致”',
							'105'=>'法定代表人身份证号码，工商数据未更新，请5-15个工作日之后尝试',
							'1000'=>'工商数据返回：“企业信息或法定代表人信息不一致”',
							'1001'=>'主体创建小程序数量达到上限',
							'1002'=>'主体违规命中黑名单',
							'1003'=>'管理员绑定账号数量达到上限',
							'1004'=>'管理员违规命中黑名单',
							'1005'=>'管理员手机绑定账号数量达到上限',
							'1006'=>'管理员手机号违规命中黑名单',
							'1007'=>'管理员身份证创建账号数量达到上限',
							'1008'=>'管理员身份证违规命中黑名单',
							'-1'=>'企业与法人姓名不一致',
						);
						$errmsg = $errarr[strval($postObj->status)];
						if(!$errmsg) $errmsg = strval($postObj->msg);
						$status = 2;
					}else{
						//$postObj->AuthorizerAppid;
						//$postObj['AuthorizationCode'];
						//$postObj['AuthorizationCodeExpiredTime'];
						//使用授权码换取公众号的接口调用凭据和授权信息
						$authorization_code = strval($postObj->auth_code);
						\app\commons\Wechat::setauthinfo($aid,$authorization_code,2);
					}
					db('admin_wxreglog')->where('id',$reglog['id'])->update(['status'=>$status,'reason'=>$errmsg]);
				}
			}else{
                Log::write($postStr);
				Log::write('解析推送消息失败: '.\app\commons\Wechat::geterror($errCode));
			}
		}
		die('success');
	}
	public function authtest(){
		$componentinfo = Db::name('sysset')->where('name','component')->value('value');
		$this->componentinfo = json_decode($componentinfo,true);
		$pc = new \app\commons\WxBizMsgCrypt($this->componentinfo['token'], $this->componentinfo['key'], $this->componentinfo['appid']);
		$postStr = file_get_contents('php://input');
		if($postStr){
			$msg_sign = $_GET['msg_signature'];
			$timeStamp= $_GET['timestamp'];
			$nonce= $_GET['nonce'];
			$errCode = $pc->decryptMsg($msg_sign, $timeStamp, $nonce, $postStr, $msg);
			if($errCode!=0){
                Log::write($postStr);
				Log::write('解析推送消息失败: '.$errCode);die;
			}
			$postObj = simplexml_load_string($msg, 'SimpleXMLElement', LIBXML_NOCDATA);
			if($postObj->MsgType == 'text' && strval($postObj->Content) == 'TESTCOMPONENT_MSG_TYPE_TEXT'){ //测试公众号处理用户消息
				$this->response_text(0,'TESTCOMPONENT_MSG_TYPE_TEXT_callback',$postObj);
			}elseif($postObj->MsgType == 'text' && strpos(strval($postObj->Content),'QUERY_AUTH_CODE:') === 0){  //测试公众号使用客服消息接口处理用户消息
				$authorization_code = str_replace('QUERY_AUTH_CODE:','',strval($postObj->Content));
				$url = 'https://api.weixin.qq.com/cgi-bin/component/api_query_auth?component_access_token='.component_access_token();
				$data = array();
				$data['component_appid'] = $this->componentinfo['appid'];
				$data['authorization_code'] = $authorization_code;
				$rs = request_post($url,jsonEncode($data));
				$rs = json_decode($rs,true);
				$info = $rs['authorization_info'];
				if($info){
					$url = 'https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token='.$info['authorizer_access_token'];
					$data = array();
					$data['touser'] = trim($postObj->FromUserName);
					$data['msgtype'] = 'text';
					$data['text'] = array('content'=>$authorization_code.'_from_api');
					request_post($url,jsonEncode($data));
				}
			}
		}
		die('success');
	}
	public function index(){
		$appid = input('param.appid');

		if($appid == 'wx570bc396a51b8ff8' || $appid == 'wxd101a85aa106f53e'){ //开放平台自动化测试
			$this->authtest();
		}
		$appinfo = Db::name('admin_setapp_mp')->where('appid',$appid)->find();
		if(!$appinfo){
			$appinfo = Db::name('admin_setapp_wx')->where('appid',$appid)->find();
			if(!$appinfo) die('success');
			define('platform','wx'); //小程序
		}else{
			define('platform','mp'); //公众号
		}
		if($appinfo['authtype']==1){
			$componentinfo = Db::name('sysset')->where('name','component')->value('value');
			$componentinfo = json_decode($componentinfo,true);
			$this->componentinfo = $componentinfo;
		}else{
			$this->componentinfo = ['token'=>$appinfo['token'],'key'=>$appinfo['key'],'appid'=>$appid];
		}
		$this->aid = $appinfo['aid'];
		$this->appinfo = $appinfo;
		define('aid',$this->aid);
		$pc = new \app\commons\WxBizMsgCrypt($this->componentinfo['token'], $this->componentinfo['key'], $this->componentinfo['appid']);
		$postStr = file_get_contents('php://input');
		if($postStr){
			$msg_sign = $_GET['msg_signature'];
			$timeStamp= $_GET['timestamp'];
			$nonce= $_GET['nonce'];
			$errCode = $pc->decryptMsg($msg_sign, $timeStamp, $nonce, $postStr, $msg);
			if($errCode!=0){
                Log::write($postStr);
				Log::write('解析推送消息失败: '.$errCode);die;
			}
			//Log::write($msg);
			$postObj = simplexml_load_string($msg, 'SimpleXMLElement', LIBXML_NOCDATA);
			if($postObj->MsgType == 'event' && $postObj->Event == 'weapp_audit_success'){ //小程序审核通过
				$wxalog = Db::name('admin_wxalog')->where('aid',aid)->where('status',1)->order('id desc')->limit(1)->find();
				Db::name('admin_wxalog')->where('id',$wxalog['id'])->update(['status'=>2,'audit_time'=>time()]);
				if($wxalog['autofb']==1){//发布
					$rs = request_post('https://api.weixin.qq.com/wxa/release?access_token='.\app\commons\Wechat::access_token(aid),'{}');
					$rs = json_decode($rs,true);
					if(isset($rs['errcode']) && $rs['errcode']!=0){
						Log::write('小程序发布失败: '.json_encode($rs));
						//return $this->json(['status'=>0,'msg'=>geterror($rs)]);
					}else{
						Db::name('admin_wxalog')->where('id',$wxalog['id'])->update(['status'=>3]);
					}
				}
			}elseif($postObj->MsgType == 'event' && $postObj->Event == 'weapp_audit_fail'){ //小程序审核驳回
				$wxalog = Db::name('admin_wxalog')->where('aid',aid)->where('status',1)->order('id desc')->limit(1)->find();
				Db::name('admin_wxalog')->where('id',$wxalog['id'])->update(['status'=>4,'audit_time'=>time(),'audit_reason'=>strval($postObj->Reason)]);
            }elseif($postObj->MsgType == 'event' && $postObj->Event == 'update_waybill_status'){ //小程序即时配送 配送单配送状态更新通知接口
			    \app\customs\ExpressWx::updateOrderStatus($postObj);
            }elseif($postObj->MsgType == 'event' && $postObj->Event == 'funds_order_pay'){ //小程序二级商户模式 订单支付成功通知
				$openid = strval($postObj->FromUserName);
				$appinfo = \app\commons\System::appinfo(aid,'wx');
				$out_trade_no = strval($postObj->order_info->trade_no);
				$transaction_id = strval($postObj->order_info->transaction_id);
				$payorder = Db::name('payorder')->where('aid',aid)->where('ordernum',$out_trade_no)->find();
				$tablename = $payorder['type'];
				if($payorder['status']!=0) die('success');
				if($payorder['score'] > 0){
					$rs = \app\commons\Member::addscore(aid,$payorder['mid'],-$payorder['score'],'支付订单，订单号：'.$out_trade_no);
					if($rs['status'] == 0){
						$order = $payorder;
						$order['totalprice'] = $order['money'];
						$order['paytypeid'] = 2;

						$params = [];
                        $rs = \app\commons\Order::refund($order,$order['money'],'积分扣除失败退款',$params);
						die('success');
					}
				}
				$rs = \app\models\Payorder::payorder($payorder['id'],'微信支付',2,$transaction_id);
				if($rs['status']==0) die('success');
				$mid = $payorder['mid'];

				$set = Db::name('admin_set')->where('aid',aid)->find();
				//消费送积分
				if($tablename != 'recharge' && $set['scorein_money']>0 && $set['scorein_score']>0){
					$givescore = floor($payorder['money'] / $set['scorein_money']) * $set['scorein_score'];
					$res = \app\commons\Member::addscore(aid,$mid,$givescore,'消费送'.t('积分'));
					if($res && $res['status'] == 1){
						//记录消费赠送积分记录
						\app\commons\Member::scoreinlog(aid,0,$mid,$payorder['type'],$payorder['orderid'],$payorder['ordernum'],$givescore,$payorder['money']);
					}
				}
				//充值送积分
				if($tablename == 'recharge' && $set['scorecz_money']>0 && $set['scorecz_score']>0){
					$givescore = floor($payorder['money'] / $set['scorecz_money']) * $set['scorecz_score'];
					\app\commons\Member::addscore(aid,$mid,$givescore,'充值送'.t('积分'));
				}
				//记录
				$data = array();
				$data['aid'] = aid;
				$data['mid'] = $mid;
				$data['openid'] = $openid;
				$data['tablename'] = $tablename;
				$data['givescore'] = $givescore;
				$data['ordernum'] = $out_trade_no;
				$data['mch_id'] = $appinfo['wxpay_sub_mchid2'];
				$data['transaction_id'] = $transaction_id;
				$data['total_fee'] = $payorder['money'];
				$data['createtime'] = time();
				$data['fenzhangmoney'] = 0;
				$data['sub_mchid'] = '';
				$data['platform'] = 'wx';
				$data['bid'] = $payorder['bid'];
				Db::name('wxpay_log')->insert($data);
				\app\commons\Member::uplv(aid,$mid);

			}elseif($postObj->MsgType == 'event' && $postObj->Event == 'funds_order_refund'){ //小程序二级商户模式 退款结果通知
				$refund_no = strval($postObj->order_info->refund_no);
				$refund_status = strval($postObj->order_info->status);
				if($refund_status != 'SUCCESS'){
					Db::name('wxrefund_log')->where('aid',aid)->where('out_refund_no',$refund_no)->update(['status'=>2,'errmsg'=>$refund_status.':'.strval($postObj->order_info->message)]);
				}
            }elseif($postObj->MsgType == 'event' && $postObj->Event == 'open_product_order_pay'){ //小程序自定义交易组件 订单支付
				$order = Db::name('shop_order')->where('id',intval($postObj->order_info->out_order_id))->find();
			    \app\models\Payorder::payorder($order['payorderid'],'微信支付','60',strval($postObj->order_info->transaction_id));
            }elseif($postObj->MsgType == 'event' && $postObj->Event == 'open_product_spu_audit'){ //小程序自定义交易组件 商品审核回调
				Db::name('shop_product')->where('id',intval($postObj->OpenProductSpuAudit->out_product_id))->update(['wxvideo_edit_status'=>intval($postObj->OpenProductSpuAudit->status),'wxvideo_status'=>intval($postObj->OpenProductSpuAudit->spu_status),'wxvideo_reject_reason'=>strval($postObj->OpenProductSpuAudit->reject_reason)]);
            }elseif($postObj->MsgType == 'event' && $postObj->Event == 'open_product_spu_status_update'){ //小程序自定义交易组件 商品系统下架通知
				Db::name('shop_product')->where('id',intval($postObj->OpenProductSpuStatusUpdate->out_product_id))->update(['wxvideo_status'=>intval($postObj->OpenProductSpuStatusUpdate->status),'wxvideo_reject_reason'=>strval($postObj->OpenProductSpuStatusUpdate->reason)]);
            }elseif($postObj->MsgType == 'event' && $postObj->Event == 'aftersale_new_order'){ //小程序自定义交易组件 用户申请退款
				$aftersale_id = strval($postObj->aftersale_info->aftersale_id);
				$out_order_id = strval($postObj->aftersale_info->out_order_id);
				$hasrefundorder = Db::name('shop_refund_order')->where('orderid',$out_order_id)->where('aftersale_id',$aftersale_id)->find();
				if(!$hasrefundorder){
					$rs = curl_post('https://api.weixin.qq.com/shop/ecaftersale/get?access_token='.\app\commons\Wechat::access_token(aid,platform),json_encode(['aftersale_id'=>$aftersale_id]));
					$rs = json_decode($rs,true);
					Log::write($rs);
					if($rs['errcode']==0){
						$after_sales_order = $rs['after_sales_order'];
						$order = Db::name('shop_order')->where('id',$out_order_id)->find();
						$og = Db::name('shop_order_goods')->where('orderid',$out_order_id)->where('ggid',$after_sales_order['product_info']['out_sku_id'])->find();
						$media_list = $after_sales_order['media_list'];
						$refund_pics = [];
						if($media_list){
							foreach($media_list as $v){
								$refund_pics[] = $v['url'];
							}
						}
						$data = [
							'aid' => $order['aid'],
							'bid' => $order['bid'],
							'mdid' => $order['mdid'],
							'mid' => $order['mid'],
							'orderid' => $order['id'],
							'ordernum' => $order['ordernum'],
							'refund_type' => ($after_sales_order['type']==1?'refund':'return'),
							'refund_ordernum' => ''.date('ymdHis').rand(100000,999999),
							'refund_money' => $after_sales_order['orderamt']/100,
							'refund_reason' => $after_sales_order['refund_reason'],
							'refund_pics' => implode(',',$refund_pics),
							'createtime' => time(),
							'refund_time' => time(),
							'refund_status' => 1,
							'platform' => platform,
							'aftersale_id' => $aftersale_id,
						];
						$refund_id = Db::name('shop_refund_order')->insertGetId($data);
						$od = [
							'aid' => $order['aid'],
							'bid' => $order['bid'],
							'mid' => $order['mid'],
							'orderid' => $order['id'],
							'ordernum' => $order['ordernum'],
							'refund_orderid' => $refund_id,
							'refund_ordernum' => $data['refund_ordernum'],
							'refund_num' => $after_sales_order['product_info']['product_cnt'],
							'refund_money' => $data['refund_money'],
							'ogid' => $og['id'],
							'proid' => $og['proid'],
							'name' => $og['name'],
							'pic' => $og['pic'],
							'procode' => $og['procode'],
							'ggid' => $og['ggid'],
							'ggname' => $og['ggname'],
							'cid' => $og['cid'],
							'cost_price' => $og['cost_price'],
							'sell_price' => $og['sell_price'],
							'createtime' => time()
						];
						Db::name('shop_refund_order_goods')->insertGetId($od);
						Db::name('shop_order_goods')->where('id',$og['id'])->inc('refund_num', $og['num'])->update();
					}
				}

			}elseif($postObj->MsgType == 'event' && $postObj->Event == 'aftersale_update_order'){ //小程序自定义交易组件 用户修改退款
				$aftersale_id = strval($postObj->aftersale_info->aftersale_id);
				$rs = curl_post('https://api.weixin.qq.com/shop/ecaftersale/get?access_token='.\app\commons\Wechat::access_token(aid,platform),json_encode(['aftersale_id'=>$aftersale_id]));
				$rs = json_decode($rs,true);
				if($rs['errcode']==0){
					$after_sales_order = $rs['after_sales_order'];
					$media_list = $after_sales_order['media_list'];
					$refund_pics = [];
					if($media_list){
						foreach($media_list as $v){
							$refund_pics[] = $v['url'];
						}
					}
					$updata = [];
					$updata['refund_type'] = ($after_sales_order['type']==1?'refund':'return');
					$updata['refund_ordernum'] = ''.date('ymdHis').rand(100000,999999);
					$updata['refund_money'] = $after_sales_order['orderamt']/100;
					$refund_reason_type = $after_sales_order['refund_reason_type'];
					$emAfterSalesReason = ['1'=>'拍错/多拍','2'=>'不想要了','3'=>'无快递信息','4'=>'包裹为空','5'=>'已拒签包裹','6'=>'快递长时间未送达','7'=>'与商品目数不符','8'=>'质量问题','9'=>'卖家发错货','10'=>'三无产品','11'=>'假冒产品','12'=>'其他'];
					$updata['refund_reason'] = $emAfterSalesReason[$refund_reason_type].' - '.$after_sales_order['refund_reason'];
					$updata['refund_pics'] = implode(',',$refund_pics);
					Db::name('shop_refund_order')->where('aftersale_id',$aftersale_id)->update($updata);
				}
			}elseif($postObj->MsgType == 'event' && $postObj->Event == 'aftersale_wait_merchant_confirm_receipt'){ //小程序自定义交易组件 用户提交发货单号
				$aftersale_id = strval($postObj->aftersale_info->aftersale_id);
				$rs = curl_post('https://api.weixin.qq.com/shop/ecaftersale/get?access_token='.\app\commons\Wechat::access_token(aid,platform),json_encode(['aftersale_id'=>$aftersale_id]));
				$rs = json_decode($rs,true);
				Log::write($rs);
				if($rs['errcode']==0){
					$after_sales_order = $rs['after_sales_order'];
					$delivery_name = $after_sales_order['return_info']['delivery_name'];
					$waybill_id = $after_sales_order['return_info']['waybill_id'];
					Db::name('shop_refund_order')->where('aftersale_id',$aftersale_id)->update(['express_com'=>$delivery_name,'express_no'=>$waybill_id]);
				}
			}elseif($postObj->MsgType == 'event' && $postObj->Event == 'aftersale_user_cancel') { //小程序自定义交易组件 用户取消售后
                $aftersale_id = strval($postObj->aftersale_info->aftersale_id);
                Db::name('shop_refund_order')->where('aftersale_id', $aftersale_id)->update(['refund_status' => 0]);
            }
            elseif($postObj->MsgType == 'event' && $postObj->Event == 'trade_manage_order_settlement'){
                //订单将要结算或已经结算(订单完成发货时 or 订单结算时，小程序发货信息管理服务) https://developers.weixin.qq.com/miniprogram/dev/platform-capabilities/business-capabilities/order-shipping/order-shipping.html#%E5%85%AB%E3%80%81%E7%9B%B8%E5%85%B3%E6%B6%88%E6%81%AF%E6%8E%A8%E9%80%81
                $transaction_id = strval($postObj->transaction_id);
                $confirm_receive_time = strval($postObj->confirm_receive_time);//确认收货时间，秒级时间戳。结算时推送才有该字段
                if($confirm_receive_time){
                    Log::write('trade_manage_order_settlement');
                    //分账失败时，重新分账
                    Db::name('wxpay_log')->where('transaction_id', $transaction_id)->where('isfenzhang',2)->update(['isfenzhang' => 0]);
                }
            }elseif($postObj->MsgType == 'event' && $postObj->Event == 'wxa_media_check'){ //小程序音视频安全内容检测
            	$update_data = [];
            	$update_data['updatetime'] = time();
            	$update_data['status'] = 1;
            	if($postObj->result->suggest == 'review'){
            		$update_data['status'] = 2;
            		//头像修改
            		$headimg = PRE_URL.'/static/img/touxiang.png';
            		$mid = Db::name('member_wximage_log')->where('trace_id',$postObj->trace_id)->value('mid');
            		Db::name('member')->where('id',$mid)->update(['headimg'=>$headimg]);
            	}
				Db::name('member_wximage_log')->where('trace_id',$postObj->trace_id)->update($update_data);
            }
            else{
				$this->mainapi($postObj);
			}
			die('success');
		}else{
			$signature = $_GET["signature"];
			$timestamp = $_GET["timestamp"];
			$nonce = $_GET["nonce"];
			$tmpArr = array($this->componentinfo['token'], $timestamp, $nonce);
			sort($tmpArr, SORT_STRING);
			$tmpStr = implode( $tmpArr );
			$tmpStr = sha1( $tmpStr );
			if( $tmpStr == $signature ){
				echo $_GET["echostr"];
			}else{
				echo '';
			}
			die;
		}
	}

    //视频号小店回调信息
    public function channels()
    {
        \app\commons\WxChannels::callback();
    }

	function mainapi($postObj){
		$openid = strval($postObj->FromUserName);
        $adminset = Db::name('admin_set')->where('aid',aid)->find();

		if(platform == 'wx' && in_array(strval($postObj->MsgType),['text','image','voice','video','link','location','miniprogrampage'])){
			if($adminset['wxkftransfer'] == 1){
				$timeStamp = $_GET['timestamp'];
				$nonce = $_GET['nonce'];
				$fromUsername = $postObj->FromUserName;
				$toUsername = $postObj->ToUserName;
				$textTpl = "<xml>
				<ToUserName><![CDATA[{$fromUsername}]]></ToUserName>
				<FromUserName><![CDATA[{$toUsername}]]></FromUserName>
				<CreateTime>".time()."</CreateTime>
				<MsgType><![CDATA[transfer_customer_service]]></MsgType>
				</xml>";
				$pc = new \app\commons\WxBizMsgCrypt($this->componentinfo['token'], $this->componentinfo['key'], $this->componentinfo['appid']);
				$rs = $pc->encryptMsg($textTpl, $timeStamp, $nonce,$msg);
				die('');
			}
		}
		//获取推荐人
		$fromid = 0;
		$tgid = 0;
		$bid = 0;
		if($postObj->EventKey && ($postObj->Event == 'subscribe' || $postObj->Event == 'SCAN')){
			if($postObj->EventKey){
				if($postObj->Event == 'SCAN'){
					$eventKey = $postObj->EventKey;
				}
				if($postObj->Event == 'subscribe'){
					$eventKey = str_replace('qrscene_','',$postObj->EventKey);
				}
				$eventKeyArr = explode('_',$eventKey);
				if($eventKeyArr[0] == 'pid'){ //推广
					$fromid = intval($eventKeyArr[1]);
				}
				//if($eventKeyArr[0] == 'tg'){ //推广拉粉
				//	$tgid = intval($eventKeyArr[1]);
				//}
				if($eventKeyArr[0] == 'bid'){ //商家码
					$bid = intval($eventKeyArr[1]);
				}
				if($eventKeyArr[0] == 'bd5'){ //绑定公众号
					$rs = curl_get('https://api.weixin.qq.com/cgi-bin/user/info?access_token='.\app\commons\Wechat::access_token(aid,'mp').'&openid='.$openid.'&lang=zh_CN');
					$fansinfo = json_decode($rs,true);
					$unionid = $fansinfo['unionid'];
					$uid = $eventKeyArr[1];
					$user = Db::name('admin_user')->where('id',$uid)->find();
					//Log::write($user);
					if($eventKeyArr[2] == substr(md5($user['un'].$user['pwd']),8,16)){//合法性校验
						if($user['mpopenid']){
							$this->send_text(aid,'该账号['.$user['un'].']已经绑定过微信账号了，如需换绑，请先在电脑端管理后台解绑',$openid);die;
						}
						$hasbd = Db::name('admin_user')->where('aid',$user['aid'])->where('mpopenid',$openid)->find();
						if($hasbd){
							$this->send_text(aid,'您已经绑定账号['.$hasbd['un'].']，不能再次绑定，如需换绑，请先在电脑端管理后台解绑',$openid);die;
						}
						$data = array();
						$data['mpopenid'] = $openid;
						$data['headimg'] = $fansinfo['headimgurl'];
						$data['nickname'] = $fansinfo['nickname'];
						Db::name('admin_user')->where('id',$uid)->update($data);
						$this->send_text(aid,'绑定成功，绑定账号['.$user['un'].']，您将通过该微信接收消息通知',$openid);
					}else{
						Log::write('合法性校验失败');
					}
				}
			}
		}
		//取消关注
		if($postObj->Event == 'unsubscribe'){
			Db::name('member')->where('aid',aid)->where('mpopenid',$openid)->update(['subscribe'=>0]);
			die('success');
		}
		$member = Db::name('member')->where('aid',aid)->where(platform.'openid',$openid)->find();
		//注册会员
		if(!$member){
			if($postObj->UnionId){
				$unionid = strval($postObj->UnionId);
				$member = Db::name('member')->where('aid',aid)->where('unionid',$unionid)->find();
				if($member){
					Db::name('member')->where('id',$member['id'])->update([platform.'openid'=>$openid]);
					$mid = $member['id'];
				}
			}
			if(!$member){
				$rs = curl_get('https://api.weixin.qq.com/cgi-bin/user/info?access_token='.\app\commons\Wechat::access_token(aid,'mp').'&openid='.$openid.'&lang=zh_CN');
				$result = json_decode($rs);
				if($result->subscribe==1){
					if($result->unionid){
						$member = Db::name('member')->where('aid',aid)->where('unionid',$result->unionid)->find();
						if($member){
							Db::name('member')->where('id',$member['id'])->update([platform.'openid'=>$result->openid]);
							$mid = $member['id'];
						}
					}
					if(!$member){
						$data = [];
						$data['aid'] = aid;
						$data[platform.'openid'] = $result->openid;
						if($result->nickname){
							$data['nickname'] = $result->nickname;
						}else{
							$data['nickname'] = '关注用户';
						}
						$data['sex'] = $result->sex;
						$data['province'] = $result->province;
						$data['city'] = $result->city;
						$data['country'] = $result->country;
						if($result->headimgurl){
							$data['headimg'] = \app\commons\Pic::uploadoss($result->headimgurl);
						}else{
							$data['headimg'] = PRE_URL.'/static/img/touxiang.png';
						}
						$data['unionid'] = $result->unionid;
						$data['createtime'] = time();
						$data['subscribe'] = $result->subscribe;
						$data['subscribe_time'] = $result->subscribe_time;
						//$data['remark'] = $result->remark;
						//$data['tagid_list'] = $result->tagid_list;
						//$data['subscribe_scene'] = $result->subscribe_scene;
						if($fromid){
							$upuser = Db::name('member')->where('id',$fromid)->find();
							$uplv = Db::name('member_level')->where('aid',aid)->where('id',$upuser['levelid'])->find();
							if($upuser && $uplv['can_agent']!=0){
								$data['pid'] = $upuser['id'];
							}
						}
                        //非强制邀请
                        if($adminset['reg_invite_code'] != 2 || $data['pid']){
                            $data['platform'] = platform;
                            $mid = \app\models\Member::add(aid,$data);
                            if($data['pid']) \app\commons\Common::user_tjscore(aid,$mid);
                            //注册赠送
                            \app\commons\Common::registerGive(aid,array_merge($data, ['id' => $mid]));
                        }
					}
				}else{
					if($postObj->Event == 'user_get_card'){ //没有会员信息直接领卡用户 先创建默认用户
						$data = [];
						$data['aid'] = aid;
						$data[platform.'openid'] = $openid;
						$data['nickname'] = '用户'.random(6);
						$data['sex'] = 3;
						$data['headimg'] = PRE_URL.'/static/img/touxiang.png';
						if($fromid){
							$upuser = Db::name('member')->where('id',$fromid)->find();
							$uplv = Db::name('member_level')->where('aid',aid)->where('id',$upuser['levelid'])->find();
							if($upuser && $uplv['can_agent']!=0){
								$data['pid'] = $upuser['id'];
							}
						}
						$data['createtime'] = time();
						//默认等级
						$defaultlv = Db::name('member_level')->where('aid',aid)->where('isdefault',1)->find();
						$data['levelid'] = $defaultlv['id'];
						$data['platform'] = 'mp';

						$unionid = strval($postObj->UnionId);
						if($unionid) $data['unionid'] = $unionid;

						$mid = Db::name('member')->insertGetId($data);
						if($data['pid']) \app\commons\Common::user_tjscore(aid,$mid);
                        //注册赠送
                        \app\commons\Common::registerGive(aid,array_merge($data, ['id' => $mid]));
					}
				}
			}
			$member = Db::name('member')->where('aid',aid)->where(platform.'openid',$openid)->find();
		}else{
			if($fromid && $fromid!=$member['id'] && $fromid!=$member['pid']){
				$upuser = Db::name('member')->where('id',$fromid)->find();
				$uplv = Db::name('member_level')->where('aid',aid)->where('id',$upuser['levelid'])->find();
				if($upuser && $uplv['can_agent']!=0 && $uplv['agent_rule']>0){
					if(!$member['pid'] || $uplv['agent_rule']==2){
                        //不绑定推荐关系：即使该会员有推荐人，当被另一个人推荐时，TA的推荐人就会变成后来推荐TA的人。
						Db::name('member')->where('id',$member['id'])->update(['pid'=>$fromid]);
						\app\commons\Common::user_tjscore(aid,$member['id']);
					}
				}
			}
			$mid = $member['id'];
			if($postObj->Event == 'subscribe'){
				Db::name('member')->where('id',$mid)->update(['subscribe'=>1,'subscribe_time'=>time()]);
			}
		}
		if($bid){
			if($member['bid']==0 && getcustom('plug_businessqr')){
				Db::name('member')->where('id',$mid)->update(['bid'=>$bid]);
				//成员加入提醒
				$tmplcontent = [];
				$tmplcontent['first'] = '恭喜您推荐新成员进入店铺';
				//$tmplcontent['remark'] = '点击进入查看~';
				$tmplcontent['keyword1'] = $member['nickname']; //姓名
				$tmplcontent['keyword2'] = date('Y-m-d H:i');//时间
				$tmplid = Db::name('mp_tmplset')->where('aid',aid)->value('tmpl_joinin');
				\app\commons\Wechat::sendhttmpl(aid,$bid,$tmplid,$tmplcontent);
			}
			$binfo = Db::name('business')->where(['id'=>$bid])->find();
			if($binfo){
				$this->response_article(aid,[['title'=>$binfo['name'],'description'=>$binfo['address'],'pic'=>$binfo['logo'],'url'=>m_url('pagesExt/business/index?id='.$bid)]],$postObj);
			}
		}
		define('mid',$mid);
		/*
		//推广拉粉
		if($tgid){
			$tgset = Db::name('tuiguang_set')->where('aid',aid)->find();
			if($tgset['status']==1){
				$tgrecord = Db::name('tuiguang_record')->where('aid',aid)->where('mid',$mid)->find();
				if(!$tgrecord){
					$member = Db::name('member')->where('aid',aid)->where('id',mid)->find();
					$adata = [];
					$adata['aid'] = aid;
					$adata['tgid'] = $tgid;
					$adata['mid'] = mid;
					$adata['nickname'] = $member['nickname'];
					$adata['headimg'] = $member['headimg'];
					$adata['openid'] = $member['mpopenid'];
					$adata['createtime'] = time();
					$rid = Db::name('tuiguang_record')->insertGetId($adata);
					$text = $tgset['response'];
					if($tgset['needform']){
						$text.= ' <a href="'.m_url('pages/tuiguang/fillform').'">完善资料</a>';
					}elseif($tgset['givecheck']==0){ //不需要填表单 不需要审核 直接给奖励
						$tguser = Db::name('tuiguang_user')->where('aid',aid)->where('id',$tgid)->find();
						if($tgset['givemoney'] > 0){
							\app\commons\Member::addmoney(aid,$tguser['mid'],$tgset['givemoney'],'推广奖励');
						}
						if($tgset['givescore'] > 0){
							\app\commons\Member::addscore(aid,$tguser['mid'],$tgset['givescore'],'推广奖励');
						}
						Db::name('tuiguang_record')->where('id',$rid)->update(['isgive'=>1,'givemoney'=>$tgset['givemoney'],'givescore'=>$tgset['givescore']]);
					}
				}else{
					$text = '您已扫码！';
					if($tgset['needform'] && $tgrecord['isfill']==0){
						$text.= ' <a href="'.m_url('pages/tuiguang/fillform').'">完善资料</a>';
					}
				}
				$this->send_text(aid,$text,$openid);
			}
		}
		*/
		if($postObj->Event == 'user_get_card'){

			$card_id = trim($postObj->CardId);
			$card_code = trim($postObj->UserCardCode);
			//是否商城创建的会员卡
			$membercard = Db::name('membercard')->where('card_id',$card_id)->find();
			if($membercard){
				if($postObj->IsRestoreMemberCard == 1){ //重领会员卡
					$card_id = trim($postObj->CardId);
					$card_code = trim($postObj->UserCardCode);
					$update = [];
					$update['card_id'] = $card_id;
					$update['card_code'] = $card_code;
					Db::name('member')->where('id',mid)->update($update);
					\app\commons\Wechat::updatemembercard(aid,mid);
					\app\commons\Member::uplv(aid,mid);
					return ;
				}
				$update = [];
				$update['card_id'] = $card_id;
				$update['card_code'] = $card_code;
			
				$membercard_record = Db::name('membercard_record')->where('aid',aid)->where('mid',mid)->where('card_id',$card_id)->where('card_code',$card_code)->find();
				if(!$membercard_record){
					$data = [];
					$data['aid'] = aid;
					$data['card_id'] = $card_id;
					$data['card_code'] = $card_code;
					$data['mid'] = mid;
					$data['openid'] = $member['mpopenid'];
					$data['unionid'] = $member['unionid'];
					$data['nickname'] = $member['nickname'];
					$data['headimg'] = $member['headimg'];
					//获取开卡填写的信息
					$url = 'https://api.weixin.qq.com/card/membercard/userinfo/get?access_token='.\app\commons\Wechat::access_token(aid,'mp');
					$rs = request_post($url,jsonEncode(['card_id'=>$card_id,'code'=>$card_code]));
					$rs = json_decode($rs,true);
					foreach($rs['user_info'] as $k=>$v){
						if($k == 'common_field_list'){
							foreach($v as $k1=>$v1){
								$field = $v1['name'];
								if($field == 'USER_FORM_INFO_FLAG_MOBILE'){//手机号
									$data['mobile'] = $v1['value'];
									if($data['mobile'] && !$member['tel']){
										$update['tel'] = $data['mobile'];
									}
								}elseif($field == 'USER_FORM_INFO_FLAG_SEX'){//性别
									$data['sex'] = $v1['value'];
								}elseif($field == 'USER_FORM_INFO_FLAG_NAME'){//姓名
									$data['name'] = $v1['value'];
									if($data['name'] && !$member['realname']){
										$update['realname'] = $data['name'];
									}
								}elseif($field == 'USER_FORM_INFO_FLAG_BIRTHDAY'){//生日
									$data['birthday'] = $v1['value'];
								}elseif($field == 'USER_FORM_INFO_FLAG_IDCARD'){//身份证
									$data['idcard'] = $v1['value'];
								}elseif($field == 'USER_FORM_INFO_FLAG_EMAIL'){//邮箱
									$data['email'] = $v1['value'];
								}elseif($field == 'USER_FORM_INFO_FLAG_LOCATION'){//详细地址
									$data['location'] = $v1['value'];
								}elseif($field == 'USER_FORM_INFO_FLAG_EDUCATION_BACKGROUND'){//教育背景
									$data['education'] = $v1['value'];
								}elseif($field == 'USER_FORM_INFO_FLAG_INDUSTRY'){//行业
									$data['industry'] = $v1['value'];
								}elseif($field == 'USER_FORM_INFO_FLAG_INCOME'){//收入
									$data['income'] = $v1['value'];
								}elseif($field == 'USER_FORM_INFO_FLAG_HABIT'){//兴趣爱好
									$data['habit'] = $v1['value'];
								}
							}
						}
						if($k == 'custom_field_list'){
							foreach($v as $k2=>$v2){
								$data['field'.($k2+1)] = $v2['name'].' :  '.$v2['value'];
							}
						}
					}

					$data['createtime'] = time();
					Db::name('membercard_record')->insert($data);
					Db::name('member')->where('id',mid)->update($update);
					\app\commons\Wechat::updatemembercard(aid,mid);
					\app\commons\Member::uplv(aid,mid);
				}
			}
		}
		if($postObj->Event == 'user_del_card'){//删除会员卡
			$card_id = trim($postObj->CardId);
			$card_code = trim($postObj->UserCardCode);
			Db::name('membercard_record')->where('aid',aid)->where('card_id',$card_id)->where('card_code',$card_code)->update(['status'=>2]);
		}
		if($postObj->Event == 'user_consume_card') {//消卡
			$card_id = trim($postObj->CardId);
			$card_code = trim($postObj->UserCardCode);
			Db::name('membercard_record')->where('aid',aid)->where('card_id',$card_id)->where('card_code',$card_code)->update(['status'=>3]);
		}
		if($postObj->Event == 'user_view_card') {//浏览卡券
			//Log::write($postObj);
			//$card_id = trim($postObj->CardId);
			//$card_code = trim($postObj->UserCardCode);
			//if($member && !$member['card_id'] && !$member['card_code']){
			//	Db::name('member')->where('id',mid)->update(['card_id'=>$card_id,'card_code'=>$card_code]);
			//}
			//updatemembercard(aid,mid);
		}
		if($postObj->Event == 'card_pass_check'){
			//Log::write($postObj);
			Db::name('membercard')->where('card_id',$postObj->CardId)->update(['status'=>1,'RefuseReason'=>$postObj->RefuseReason]);
		}
		if($postObj->Event == 'card_not_pass_check'){
			//Log::write($postObj);
			Db::name('membercard')->where('card_id',$postObj->CardId)->update(['status'=>2,'RefuseReason'=>$postObj->RefuseReason]);
		}

		//关注
		if($postObj->Event == 'subscribe'){
			$mp_keyword = Db::name('mp_keyword')->where("aid=".aid." and `status`=1 and ktype=2")->order('sort desc,id desc')->find();
			if($mp_keyword){
				if($mp_keyword['msgtype'] == 'text' && strpos($mp_keyword['content'],'[关注数]')!==false){
					$subscribeNum = Db::name('member')->where('aid',aid)->where('subscribe',1)->count();
					$subscribeNum = $subscribeNum + $mp_keyword['sort'];
				}
				$nickname = Db::name('member')->where('id',mid)->value('nickname');
				if($mp_keyword['msgtype']=='text'){
					$rstext = str_replace(['[关注数]','[昵称]'],[$subscribeNum,$nickname],$mp_keyword['content']);
					$this->response_text(aid,$rstext,$postObj);
				}elseif($mp_keyword['msgtype']=='image'){
					$content = json_decode($mp_keyword['content'],true);
					$this->response_image(aid,$content['url'],$postObj);
				}elseif($mp_keyword['msgtype']=='voice'){
					$content = json_decode($mp_keyword['content'],true);
					$this->response_voice(aid,$content['url'],$postObj);
				}elseif($mp_keyword['msgtype']=='video'){
					$content = json_decode($mp_keyword['content'],true);
					$this->response_video(aid,$content,$postObj);
				}elseif($mp_keyword['msgtype']=='music'){
					$content = json_decode($mp_keyword['content'],true);
					$this->response_music(aid,$content,$postObj);
				}elseif($mp_keyword['msgtype']=='news'){
					$content = json_decode($mp_keyword['content'],true);
					$this->response_article(aid,[$content],$postObj);
				}
			}
		}elseif($postObj->Event == 'CLICK'){
			if(strpos($postObj->EventKey,'source_')===0){
				$sourceid = substr($postObj->EventKey,7);
				$this->response_news(aid,$sourceid,$postObj);
			}elseif(strpos($postObj->EventKey,'word_')===0){
				$eventkey = strval($postObj->EventKey);
				$menudata = Db::name('admin_set')->where('aid',$this->aid)->value('menudata');
				$menudata = json_decode($menudata,true);
				foreach($menudata['menu']['button'] as $k=>$v){
					if($v['type']=='click' && $v['key'] == $eventkey){
						$this->response_text(aid,$v['content'],$postObj);
					}
					foreach($v['sub_button'] as $k2=>$v2){
						if($v2['type']=='click' && $v2['key'] == $eventkey){
							$this->response_text(aid,$v2['content'],$postObj);
						}
					}
				}
			}else{
				if($postObj->EventKey){
					$menukey = Db::name('mp_menukey')->where('key',$postObj->EventKey)->find();
					if($menukey){
						$this->response_text(aid,$menukey['val'],$postObj);
					}
				}
			}
		}elseif($postObj->MsgType == 'text'){
			if($postObj->Content == 'test'){
				$this->response_text(aid,"测试成功",$postObj);
			}
			$content = strval($postObj->Content);
			$content = $this->parseemoji($content,2);
			$data = array();
			$data['aid'] = aid;
			$data['mid'] = mid;
			$data['bid'] = 0;
			$data['platform'] = platform;
			$data['pre_url'] = PRE_URL;
			$data['nickname'] = $member['nickname'];
			$data['headimg'] = $member['headimg'];
			$data['tel'] = $member['tel'];
			$data['msgtype'] = 'text';
			$data['content'] = $content;
			//$data['msgid'] = strval($postObj->MsgId);
			$data['token'] = $member['random_str'];
			$this->tokefu(aid,$data,$postObj);
			//自动回复
			$rs = Db::name(platform.'_keyword')->where("aid=".aid." and `status`=1 and ((ktype=0 and keyword='{$content}') or (ktype=1 and instr('{$content}',keyword)) or keyword='*')")->order('sort desc,id desc')->find();
			if($rs){
				$appinfo = \app\commons\System::appinfo(aid,platform);
				$data = array();
				$data['aid'] = aid;
				$data['uid'] = -1;
				$data['mid'] = $mid;
				//$data['openid'] = $openid;
				$data['nickname'] = $member['nickname'];
				$data['headimg'] = $member['headimg'];
				$data['unickname'] = '[自动回复]';
				$headimg = $appinfo['headimg'];
				$data['uheadimg'] = $headimg;
				$data['tel'] = $member['tel'];
				$data['msgtype'] = $rs['msgtype'];
				if($rs['msgtype']=='image'){
					$content = json_decode($rs['content'],true);
					$data['content'] = $content['url'];
				}else{
					$data['content'] = $rs['content'];
				}
				$data['createtime'] = time();
				$data['isreply'] = 1;
				$data['id'] = Db::name('kefu_message')->insertGetId($data);
				
				$data['token'] = $member['random_str'];
				$this->tokefu(aid,$data,$postObj);
				if(platform == 'wx'){
					if($rs['msgtype']=='text'){
						$content = $rs['content'];
						$this->send_text(aid,$content,$postObj->FromUserName);
					}elseif($rs['msgtype']=='image'){
						$content = json_decode($rs['content'],true);
						//send_media(aid,$postObj,$content['media_id']);
						$this->send_image(aid,$content['url'],$postObj);
					}elseif($rs['msgtype']=='link'){
						$content = json_decode($rs['content'],true);
						$this->send_link(aid,$postObj,$content);
					}elseif($rs['msgtype']=='miniprogrampage'){
						$content = json_decode($rs['content'],true);
						$this->send_miniprogrampage(aid,$postObj,$content);
					}
				}else{
					if($rs['msgtype']=='text'){
						$content = $rs['content'];
						$this->response_text(aid,$content,$postObj);
					}elseif($rs['msgtype']=='image'){
						$content = json_decode($rs['content'],true);
						$this->response_image(aid,$content['url'],$postObj);
					}elseif($rs['msgtype']=='voice'){
						$content = json_decode($rs['content'],true);
						$this->response_voice(aid,$content['url'],$postObj);
					}elseif($rs['msgtype']=='video'){
						$content = json_decode($rs['content'],true);
						$this->response_video(aid,$content,$postObj);
					}elseif($rs['msgtype']=='music'){
						$content = json_decode($rs['content'],true);
						$this->response_music(aid,$content,$postObj);
					}elseif($rs['msgtype']=='news'){
						$content = json_decode($rs['content'],true);
						$this->response_article(aid,[$content],$postObj);
					}
				}
			}
		}elseif($postObj->MsgType == 'image'){
			$data = [];
			$data['aid'] = aid;
			$data['mid'] = mid;
			$data['bid'] = 0;
			$data['platform'] = platform;
			$data['pre_url'] = PRE_URL;
			$data['nickname'] = $member['nickname'];
			$data['headimg'] = $member['headimg'];
			$data['tel'] = $member['tel'];
			$data['msgtype'] = 'image';
			$piccontent = curl_get('https://api.weixin.qq.com/cgi-bin/media/get?access_token='.\app\commons\Wechat::access_token(aid,platform).'&media_id='.$postObj->MediaId);
			$dir = 'upload/'.aid.'/'.date('Ym');
			if(!is_dir(ROOT_PATH.$dir)) mk_dir(ROOT_PATH.$dir);
			$filename = date('d_His').rand(1000,9999).'.jpg';
			$mediapath = $dir.'/'.$filename;
			file_put_contents(ROOT_PATH.$mediapath,$piccontent);
			$url = PRE_URL.'/'.$mediapath;

			$data['content'] = $url;
			$data['mediaid'] = strval($postObj->MediaId);
			$data['token'] = $member['random_str'];
			$this->tokefu(aid,$data,$postObj);
		}elseif($postObj->MsgType == 'voice'){
			$data = array();
			$data['aid'] = aid;
			$data['mid'] = mid;
			$data['nickname'] = $member['nickname'];
			$data['headimg'] = $member['headimg'];
			$data['tel'] = $member['tel'];
			$data['msgtype'] = 'voice';
			//通过mediaid获取文件
			$voicecontent = curl_get('https://api.weixin.qq.com/cgi-bin/media/get?access_token='.\app\commons\Wechat::access_token(aid,platform).'&media_id='.$postObj->MediaId);
            $dir = 'upload/'.aid.'/'.date('Ym');
			if(!is_dir(ROOT_PATH.$dir)) mk_dir(ROOT_PATH.$dir);
			$filename = date('d_His').rand(1000,9999).'.amr';
			$mediapath = $dir.'/'.$filename;
			file_put_contents(ROOT_PATH.$mediapath,$voicecontent);
			$url = PRE_URL.'/'.$mediapath;
//			$url = amr2mp3($url);
            $urlmp3 = amr2mp3($mediapath);//amr转换mp3
            if($urlmp3) $url = $urlmp3;

			$data['content'] = jsonencode(['url'=>$url,'MediaId'=>strval($postObj->MediaId)]);
			$data['createtime'] = time();
			$data['isreply'] = 0;
			$data['id'] = Db::name('kefu_message')->insertGetId($data);
			$data['token'] = $member['random_str'];
			$this->tokefu(aid,$data,$postObj);
		}elseif($postObj->MsgType == 'video'){
		
		}elseif($postObj->MsgType == 'link'){
		
		}elseif($postObj->MsgType == 'location'){
		
		}elseif($postObj->MsgType == 'miniprogrampage'){
			$data = array();
			$data['aid'] = aid;
			$data['mid'] = mid;
			$data['nickname'] = $member['nickname'];
			$data['headimg'] = $member['headimg'];
			$data['tel'] = $member['tel'];
			$data['msgtype'] = 'miniprogrampage';
			$data['content'] = jsonencode(['Title'=>strval($postObj->Title),'AppId'=>strval($postObj->Appid),'PagePath'=>strval($postObj->PagePath),'ThumbUrl'=>\app\commons\Pic::uploadoss($postObj->ThumbUrl),'ThumbMediaId'=>strval($postObj->ThumbMediaId)]);
			$data['createtime'] = time();
			$data['isreply'] = 0;
			$data['id'] = Db::name('kefu_message')->insertGetId($data);
			$data['token'] = $member['random_str'];
			$this->tokefu(aid,$data,$postObj);
		}
		if($postObj->Event == 'MASSSENDJOBFINISH'){
			//Log::write($postObj);
			$msgid = trim($postObj->MsgID);
			$data = [];
			$status = trim($postObj->Status);
			$data['totalcount'] = trim($postObj->TotalCount);
			$data['sendcount'] = trim($postObj->SentCount);
			$data['errorcount'] = trim($postObj->ErrorCount);
			$data['filtercount'] = trim($postObj->FilterCount);
			$data['sendtimeend'] = time();
			$data['resultdata'] = jsonEncode($postObj);
			if($status == 'send success'){//成功
				$data['status'] = 2;
			}
			if($status == 'send fail' || strpos($status,'err(')!==false){//失败
				$data['status'] = 3;
				$data['msg'] = '发送失败';
				if($status == 'err(10001)'){
					$data['msg'] = '涉嫌广告';
				}elseif($status == 'err(20001)'){
					$data['msg'] = '涉嫌政治';
				}elseif($status == 'err(20004)'){
					$data['msg'] = '涉嫌社会';
				}elseif($status == 'err(20002)'){
					$data['msg'] = '涉嫌色情';
				}elseif($status == 'err(20006)'){
					$data['msg'] = '涉嫌违法犯罪';
				}elseif($status == 'err(20008)'){
					$data['msg'] = '涉嫌欺诈';
				}elseif($status == 'err(20013)'){
					$data['msg'] = '涉嫌版权';
				}elseif($status == 'err(22000)'){
					$data['msg'] = '涉嫌互推(互相宣传) ';
				}elseif($status == 'err(21000)'){
					$data['msg'] = '涉嫌其他';
				}elseif($status == 'err(30001)'){
					$data['msg'] = '原创校验出现系统错误且用户选择了被判为转载就不群发';
				}elseif($status == 'err(30002)'){
					$data['msg'] = '原创校验被判定为不能群发';
				}elseif($status == 'err(30003)'){
					$data['msg'] = '原创校验被判定为转载文且用户选择了被判为转载就不群发';
				}
			}
			Db::name('mp_source_sendalllog')->where('aid',aid)->where('msg_id',$msgid)->update($data);
		}
	}
	//小程序客服
	function tokefu($aid,$data,$postObj){
		if(platform!='wx') return;
		$config = include('config.php');
		$token = $data['token'];
		unset($data['token']);
		$data['iswx'] = 1;
		$sendata = array(
			'type'=>'tokefu',
			'token'=>$token,
			'data'=>$data
		);
		$socket = new \app\commons\WebsocketClient('127.0.0.1',$config['kfport']);
		$socket->send(json_encode($sendata));
	}
	//回复文本
	function response_text($aid,$txt,$postObj,$isdecode=true){
		if($isdecode){
			$txt = $this->parseemoji($txt,1);
		}
		$timeStamp = $_GET['timestamp'];
		$nonce = $_GET['nonce'];
		$fromUsername = $postObj->FromUserName;
		$toUsername = $postObj->ToUserName;
		$textTpl = "<xml>
		<ToUserName><![CDATA[{$fromUsername}]]></ToUserName>
		<FromUserName><![CDATA[{$toUsername}]]></FromUserName>
		<CreateTime>".time()."</CreateTime>
		<MsgType><![CDATA[text]]></MsgType>
		<Content><![CDATA[".htmlspecialchars_decode($txt)."]]></Content>
		</xml>";
		$pc = new \app\commons\WxBizMsgCrypt($this->componentinfo['token'], $this->componentinfo['key'], $this->componentinfo['appid']);
		$rs = $pc->encryptMsg($textTpl, $timeStamp, $nonce,$msg);
		die('');
	}
	//表情转换 1:文字转符号 2:符号转文字
	function parseemoji($content,$type=1){
		$codeArr = array("/::)","/::~","/::B","/::|","/:8-)","/::<","/::$","/::X","/::Z","/::'(","/::-|","/::@","/::P","/::D","/::O","/::(",
			"/::Q","/::T","/:,@P","/:,@-D","/::d","/:,@o","/:|-)","/::!","/::L","/::>","/::,@","/:,@f","/::-S","/:?","/:,@x","/:,@@","/:,@!","/:!!!",
			"/:xx","/:bye","/:wipe","/:dig","/:handclap","/:B-)","/:<@","/:@>","/::-O","/:>-|","/:P-(","/::'|","/:X-)","/::*","/:8*","/:pd",
			"/:<W>","/:beer","/:coffee","/:pig","/:rose","/:fade","/:showlove","/:heart","/:break","/:cake","/:bome","/:shit","/:moon","/:sun","/:hug","/:strong","/:weak",
			"/:share","/:v","/:@)","/:jj","/:@@","/:ok","/:jump","/:shake","/:<O>","/:circle");
		$desArr = array("[微笑]","[撇嘴]","[色]","[发呆]","[得意]","[流泪]","[害羞]","[闭嘴]","[睡]","[大哭]","[尴尬]","[发怒]","[调皮]","[呲牙]","[惊讶]","[难过]",
			"[抓狂]","[吐]","[偷笑]","[愉快]","[白眼]","[傲慢]","[困]","[惊恐]","[流汗]","[憨笑]","[悠闲]","[奋斗]","[咒骂]","[疑问]","[嘘]","[晕]","[衰]","[骷髅]",
			"[敲打]","[再见]","[擦汗]","[抠鼻]","[鼓掌]","[坏笑]","[左哼哼]","[右哼哼]","[哈欠]","[鄙视]","[委屈]","[快哭了]","[阴险]","[亲亲]","[可怜]","[菜刀]",
			"[西瓜]","[啤酒]","[咖啡]","[猪头]","[玫瑰]","[凋谢]","[嘴唇]","[爱心]","[心碎]","[蛋糕]","[炸弹]","[便便]","[月亮]","[太阳]","[拥抱]","[强]","[弱]",
			"[握手]","[胜利]","[抱拳]","[勾引]","[拳头]","[OK]","[跳跳]","[发抖]","[怄火]","[转圈]");
		if($type==1){
			$content = str_replace($desArr,$codeArr,$content);
		}else{
			$content = str_replace($codeArr,$desArr,$content);
		}
		return $content;
	}
	//回复图片
	function response_image($aid,$picurl,$postObj){
		$timeStamp = $_GET['timestamp'];
		$nonce = $_GET['nonce'];
		$fromUsername = $postObj->FromUserName;
		$toUsername = $postObj->ToUserName;
		$media_id = \app\commons\Wechat::getmediaid($aid,$picurl);
		//Log::write($media_id);
		$textTpl = "<xml>
		<ToUserName><![CDATA[{$fromUsername}]]></ToUserName>
		<FromUserName><![CDATA[{$toUsername}]]></FromUserName>
		<CreateTime>".time()."</CreateTime>
		<MsgType><![CDATA[image]]></MsgType>
		<Image>
			<MediaId><![CDATA[{$media_id}]]></MediaId>
		</Image>
		</xml>";
		$pc = new \app\commons\WxBizMsgCrypt($this->componentinfo['token'], $this->componentinfo['key'], $this->componentinfo['appid']);
		$rs = $pc->encryptMsg($textTpl, $timeStamp, $nonce,$msg);
		die('');
	}
	//回复语音
	function response_voice($aid,$voiceurl,$postObj){
		$timeStamp = $_GET['timestamp'];
		$nonce = $_GET['nonce'];
		$fromUsername = $postObj->FromUserName;
		$toUsername = $postObj->ToUserName;
		$media_id = \app\commons\Wechat::getmediaid($aid,$voiceurl,'voice');
		$textTpl = "<xml>
		<ToUserName><![CDATA[{$fromUsername}]]></ToUserName>
		<FromUserName><![CDATA[{$toUsername}]]></FromUserName>
		<CreateTime>".time()."</CreateTime>
		<MsgType><![CDATA[voice]]></MsgType>
		<Voice>
			<MediaId><![CDATA[{$media_id}]]></MediaId>
		</Voice>
		</xml>";
		$pc = new \app\commons\WxBizMsgCrypt($this->componentinfo['token'], $this->componentinfo['key'], $this->componentinfo['appid']);
		$rs = $pc->encryptMsg($textTpl, $timeStamp, $nonce,$msg);
		die('');
	}
	//回复视频
	function response_video($aid,$content,$postObj){
		$timeStamp = $_GET['timestamp'];
		$nonce = $_GET['nonce'];
		$fromUsername = $postObj->FromUserName;
		$toUsername = $postObj->ToUserName;
		$media_id = \app\commons\Wechat::getmediaid($aid,$content['url'],'video',jsonEncode(['title'=>$content['title'],'introduction'=>$content['description']]));
		$textTpl = "<xml>
		<ToUserName><![CDATA[{$fromUsername}]]></ToUserName>
		<FromUserName><![CDATA[{$toUsername}]]></FromUserName>
		<CreateTime>".time()."</CreateTime>
		<MsgType><![CDATA[video]]></MsgType>
		<Video>
			<MediaId><![CDATA[{$media_id}]]></MediaId>
			".($content['title']?"<Title><![CDATA[{$content['title']}]]></Title>":'')."
			".($content['description']?"<Description><![CDATA[{$content['description']}]]></Description>":'')."
		</Video>
		</xml>";
		$pc = new \app\commons\WxBizMsgCrypt($this->componentinfo['token'], $this->componentinfo['key'], $this->componentinfo['appid']);
		$rs = $pc->encryptMsg($textTpl, $timeStamp, $nonce,$msg);
		die('');
	}
	//回复音乐
	function response_music($aid,$content,$postObj){
		$timeStamp = $_GET['timestamp'];
		$nonce = $_GET['nonce'];
		$fromUsername = $postObj->FromUserName;
		$toUsername = $postObj->ToUserName;
		$media_id = \app\commons\Wechat::getmediaid($aid,$content['pic'],'thumb');
		$textTpl = "<xml>
		<ToUserName><![CDATA[{$fromUsername}]]></ToUserName>
		<FromUserName><![CDATA[{$toUsername}]]></FromUserName>
		<CreateTime>".time()."</CreateTime>
		<MsgType><![CDATA[music]]></MsgType>
		<Music>
			".($content['title']?"<Title><![CDATA[{$content['title']}]]></Title>":"")."
			".($content['description']?"<Description><![CDATA[{$content['description']}]]></Description>":"")."
			<MusicUrl><![CDATA[{$content['url']}]]></MusicUrl>
			<HQMusicUrl><![CDATA[{$content['url']}]]></HQMusicUrl>
			<ThumbMediaId><![CDATA[{$media_id}]]></ThumbMediaId>
		</Music>
		</xml>";
		$pc = new \app\commons\WxBizMsgCrypt($this->componentinfo['token'], $this->componentinfo['key'], $this->componentinfo['appid']);
		$rs = $pc->encryptMsg($textTpl, $timeStamp, $nonce,$msg);
		die('');
	}
	//回复图文
	function response_article($aid,$sourceData,$postObj){
		
		$timeStamp = $_GET['timestamp'];
		$nonce = $_GET['nonce'];
		$fromUsername = $postObj->FromUserName;
		$toUsername = $postObj->ToUserName;
		$textTpl = "<xml>";
		$textTpl .= "  <ToUserName><![CDATA[{$fromUsername}]]></ToUserName>";
		$textTpl .= "  <FromUserName><![CDATA[{$toUsername}]]></FromUserName>";
		$textTpl .= "  <CreateTime>".time()."</CreateTime>";
		$textTpl .= "  <MsgType><![CDATA[news]]></MsgType>";
		$textTpl .= "  <ArticleCount>".count($sourceData)."</ArticleCount>";
		$textTpl .= "  <Articles>";
		foreach($sourceData as $v){
			$textTpl .= "	<item>";
			$textTpl .= "	  <Title><![CDATA[".$v['title']."]]></Title>";
			$textTpl .= "	  <Description><![CDATA[".$v['description']."]]></Description>";
			$textTpl .= "	  <PicUrl><![CDATA[".$v['pic']."]]></PicUrl>";
			$textTpl .= "	  <Url><![CDATA[".$v['url']."]]></Url>";
			$textTpl .= "	</item>";
		}
		$textTpl .= "  </Articles>";
		$textTpl .= "</xml>";
		$pc = new \app\commons\WxBizMsgCrypt($this->componentinfo['token'], $this->componentinfo['key'], $this->componentinfo['appid']);
		$rs = $pc->encryptMsg($textTpl, $timeStamp, $nonce,$msg);
		die;
	}
	//回复图文 通过mediaid
	function response_news($aid,$sourceid,$postObj){
		$access_token = \app\commons\Wechat::access_token($aid,'mp');
		$url = 'https://api.weixin.qq.com/cgi-bin/material/get_material?access_token='.$access_token;
		$rs = request_post($url,jsonEncode(['media_id'=>$sourceid]));
		$sourceData = json_decode($rs,true);
		$sourceData = $sourceData['news_item'];

		$timeStamp = $_GET['timestamp'];
		$nonce = $_GET['nonce'];
		$fromUsername = $postObj->FromUserName;
		$toUsername = $postObj->ToUserName;
		$textTpl = "<xml>";
		$textTpl .= "  <ToUserName><![CDATA[{$fromUsername}]]></ToUserName>";
		$textTpl .= "  <FromUserName><![CDATA[{$toUsername}]]></FromUserName>";
		$textTpl .= "  <CreateTime>".time()."</CreateTime>";
		$textTpl .= "  <MsgType><![CDATA[news]]></MsgType>";
		$textTpl .= "  <ArticleCount>".count($sourceData)."</ArticleCount>";
		$textTpl .= "  <Articles>";
		foreach($sourceData as $v){
			$textTpl .= "	<item>";
			$textTpl .= "	  <Title><![CDATA[".$v['title']."]]></Title>";
			$textTpl .= "	  <Description><![CDATA[".$v['digest']."]]></Description>";
			$textTpl .= "	  <PicUrl><![CDATA[".$v['thumb_url']."]]></PicUrl>";
			$textTpl .= "	  <Url><![CDATA[".$v['url']."]]></Url>";
			$textTpl .= "	</item>";
		}
		$textTpl .= "  </Articles>";
		$textTpl .= "</xml>";
		$pc = new \app\commons\WxBizMsgCrypt($this->componentinfo['token'], $this->componentinfo['key'], $this->componentinfo['appid']);
		$rs = $pc->encryptMsg($textTpl, $timeStamp, $nonce,$msg);
		die;
	}
	//发送文本
	function send_text($aid,$content,$openid){
		$access_token = \app\commons\Wechat::access_token($aid,platform);
		$url = 'https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token='.$access_token;
		$data = array();
		$data['touser'] = strval($openid);
		$data['msgtype'] = 'text';
		$data['text'] = array('content'=>$content);
		$rs = request_post($url,jsonEncode($data));
	}
	//发送图片
	function send_image($aid,$picurl,$postObj){
		$media_id = \app\commons\Wechat::pictomedia($aid,platform,$picurl);
		$access_token = \app\commons\Wechat::access_token($aid,platform);
		$url = 'https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token='.$access_token;
		$data = array();
		$data['touser'] = trim($postObj->FromUserName);
		$data['msgtype'] = 'image';
		$data['image'] = array('media_id'=>$media_id);
		$rs = request_post($url,jsonEncode($data));
	}
	//发送图文
	function send_link($aid,$postObj,$content){
		$access_token = \app\commons\Wechat::access_token($aid,platform);
		$url = 'https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token='.$access_token;
		$data = array();
		$data['touser'] = trim($postObj->FromUserName);
		$data['msgtype'] = 'link';
		$data['link'] = array(
			'title'=>$content['title'],
			'description'=>$content['description'],
			'url'=>$content['url'],
			'thumb_url'=>$content['pic'],
		);
		request_post($url,jsonEncode($data));
	}
	//发送小程序
	function send_miniprogrampage($aid,$postObj,$content){
		$access_token = \app\commons\Wechat::access_token($aid,platform);
		$url = 'https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token='.$access_token;
		$data = array();
		$data['touser'] = trim($postObj->FromUserName);
		$data['msgtype'] = 'miniprogrampage';
		$data['miniprogrampage'] = array(
			'title'=>$content['title'],
			'pagepath'=>$content['pagepath'],
			'thumb_media_id'=>\app\commons\Wechat::pictomedia($aid,platform,$content['pic']),
		);
		$rs = request_post($url,str_replace('\\/','/',jsonEncode($data)));
	}
}