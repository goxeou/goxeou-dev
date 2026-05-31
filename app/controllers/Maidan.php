<?php


// +----------------------------------------------------------------------
// | 买单记录
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class Maidan extends Common
{
	public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无操作权限');
	}
	//明细
    public function index(){
        $whereM = [];
        $whereM[] = ['aid','=',aid];
        $whereM[] = ['status','=',1];
        if(bid>0){
            $whereM[] = ['bid','=',bid];
        }else{
            $bids = [0];
            $whereM[] = ['bid','in',$bids];
        }
		$mdArr = Db::name('mendian')->where($whereM)->column('name','id');
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = 'maidan_order.'.input('param.field').' '.input('param.order');
			}else{
				$order = 'maidan_order.id desc';
			}
			$where = [];
			$where[] = ['maidan_order.aid','=',aid];
            if(bid==0){
                if(input('param.showtype')=='all'){
                    $where[] = ['maidan_order.bid','>=',0];
                }else{
                    $where[] = ['maidan_order.bid','=',0];
                }
            }else{
                $where[] = ['maidan_order.bid','=',bid];
            }
//			$where[] = ['maidan_order.bid','=',bid];
			$where[] = ['maidan_order.status','=',1];
			if($this->mdid){
				$where[] = ['maidan_order.mdid','=',$this->mdid];
			}
			if(input('param.orderids')) $where[] = ['maidan_order.id','in',input('param.orderids')];
			if(input('param.orderid')) $where[] = ['maidan_order.id','=',input('param.orderid')];
			if(input('param.mdid')) $where[] = ['maidan_order.mdid','=',input('param.mdid')];
			if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
			if(input('param.mid')) $where[] = ['maidan_order.mid','=',trim(input('param.mid'))];
			if(input('param.ordernum')) $where[] = ['maidan_order.ordernum','=',trim(input('param.ordernum'))];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['maidan_order.status','=',input('param.status')];
			if(input('param.ctime') ){
				$ctime = explode(' ~ ',input('param.ctime'));
				$where[] = ['maidan_order.paytime','>=',strtotime($ctime[0])];
				$where[] = ['maidan_order.paytime','<',strtotime($ctime[1])];
			}
			$count = 0 + Db::name('maidan_order')->alias('maidan_order')->field('member.nickname,member.headimg,maidan_order.*')->join('member member','member.id=maidan_order.mid','left')->where($where)->count();
			$data = Db::name('maidan_order')->alias('maidan_order')->field('member.nickname,member.headimg,maidan_order.*')->join('member member','member.id=maidan_order.mid','left')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			if(!$data) $data = [];
			foreach($data as $k=>$v){
				 $data[$k]['mdname'] = '';
				if($v['mdid']) $data[$k]['mdname'] = $mdArr[$v['mdid']];
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		View::assign('mdArr',$mdArr);
		return View::fetch();
    }
    public function add()
    {
        if(request()->isPost()){
            $post = input('post.');
            $money = floatval($post['money']);
            if($money <=0){
                return $this->json(['status'=>0,'msg'=>'支付金额必须大于0']);
            }
            $paymoney = $money;
            //会员折扣
            if(empty($post['tel'])) return json(['status'=>0,'msg'=>'请输入手机号']);
            $member = Db::name('member')->where('aid',aid)->where('tel',$post['tel'])->field('id,nickname,money,levelid')->find();
            if(empty($member))
                return json(['status'=>0,'msg'=>'未找到用户']);
            $disprice = 0;
            $userlevel = Db::name('member_level')->where('aid',aid)->where('id',$member['levelid'])->find();
            if($userlevel && $userlevel['discount']>0 && $userlevel['discount']<10){
                $disprice = $paymoney * (1 - $userlevel['discount'] * 0.1);
            }
            $paymoney = $paymoney - $disprice;
            //优惠券
            if($post['couponrid'] > 0){
                $couponrid = $post['couponrid'];
                $couponrecord = Db::name('coupon_record')->where('aid',aid)->where('mid',$member['id'])->where('id',$couponrid)->find();
                if(!$couponrecord){
                    return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'不存在']);
                }elseif($couponrecord['status']!=0){
                    return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'已使用过了']);
                }elseif($couponrecord['starttime'] > time()){
                    return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'尚未开始使用']);
                }elseif($couponrecord['endtime'] < time()){
                    return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'已过期']);
                }elseif($couponrecord['minprice'] > $money){
                    return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'不符合条件']);
                }elseif($couponrecord['type']!=1){
                    return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'不符合条件']);
                }
                $couponmoney = $couponrecord['money'];
                if($couponmoney > $money) $couponmoney = $money;
            }else{
                $couponmoney = 0;
            }
            $paymoney = $paymoney - $couponmoney;
            if($paymoney < 0) $paymoney = 0;
            //积分抵扣
            if($post['usescore']==1){
                $adminset = Db::name('admin_set')->where('aid',aid)->find();
                $score2money = $adminset['score2money'];
                $scoredkmaxpercent = $adminset['scoredkmaxpercent'];
                $scoredk = $this->member['score'] * $score2money;
                if($scoredk > $paymoney) $scoredk = $paymoney;
                if($scoredkmaxpercent >= 0 && $scoredkmaxpercent < 100 && $scoredk > 0 && $scoredk > $paymoney * $scoredkmaxpercent * 0.01){
                    $scoredk = $paymoney * $scoredkmaxpercent * 0.01;
                }
                $paymoney = $paymoney - $scoredk;
                $paymoney = round($paymoney*100)/100;
                if($paymoney < 0) $paymoney = 0;
                if($scoredk > 0){
                    $decscore = $scoredk / $score2money;
                }
            }else{
                $scoredk = 0;
                $decscore = 0;
            }

            if($paymoney > $member['money'])  return $this->json(['status'=>0,'msg'=>t('余额').'不足']);

            $bname = Db::name('admin_set')->where('aid',aid)->value('name');
            $title = '后台买单收款：'.$bname;
            $ordernum = date('ymdHis').aid.rand(1000,9999);
            $rs = \app\commons\Member::addmoney(aid,$member['id'],$paymoney*-1,$title.$ordernum);
            if($rs['status'] == 1) {
                //创建订单
                $order = [];
                $order['ordernum'] = $ordernum;
                $order['aid'] = aid;
                $order['bid'] = bid;
                $order['mid'] = $member['id'];
                $order['title'] = $title;
                $order['money'] = $money;
                $order['paymoney'] = $paymoney;
                $order['disprice'] = $disprice;
                $order['scoredk'] = $scoredk;
                $order['decscore'] = $decscore;
                $order['couponrid'] = $couponrid;
                $order['couponmoney'] = $couponmoney; //优惠券抵扣
                $order['createtime'] = time();
                $order['paytime'] = time();
                $order['paytypeid'] = 1;
                $order['paytype'] = t('余额').'支付';
                $order['platform'] = platform;
                $order['status'] = 1;
                $order['mdid'] = $post['mdid'] ? $post['mdid'] : 0;
                $orderid = Db::name('maidan_order')->insertGetId($order);
                \app\commons\System::plog('后台买单收款'.$orderid);

                return json(['status'=>1,'paymoney'=>$paymoney,'msg'=>'实付：'.$paymoney]);
            }

            return json($rs);
        }

        $mendian = Db::name('mendian')->where('aid',aid)->where('bid',bid)->select()->toArray();
        View::assign('mendian',$mendian);

        return View::fetch();
    }
	//明细导出
	public function excel(){
		if(input('param.field') && input('param.order')){
			$order = 'maidan_order.'.input('param.field').' '.input('param.order');
		}else{
			$order = 'maidan_order.id desc';
		}
		$mdArr = Db::name('mendian')->where('aid',aid)->column('name','id');
        $page = input('param.page')?:1;
        $limit = input('param.limit')?:10;
		$where = [];
		$where[] = ['maidan_order.aid','=',aid];
		$where[] = ['maidan_order.bid','=',bid];
		$where[] = ['maidan_order.status','=',1];
		if($this->mdid){
			$where[] = ['maidan_order.mdid','=',$this->mdid];
		}
		if(input('param.mdid')) $where[] = ['maidan_order.mdid','=',input('param.mdid')];
		if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
		if(input('param.mid')) $where[] = ['maidan_order.mid','=',trim(input('param.mid'))];
		if(input('param.ordernum')) $where[] = ['maidan_order.ordernum','=',trim(input('param.ordernum'))];
		if(input('?param.status') && input('param.status')!=='') $where[] = ['maidan_order.status','=',input('param.status')];
		if(input('param.ctime') ){
			$ctime = explode(' ~ ',input('param.ctime'));
			$where[] = ['maidan_order.paytime','>=',strtotime($ctime[0])];
			$where[] = ['maidan_order.paytime','<',strtotime($ctime[1]) + 86400];
		}
		$list = Db::name('maidan_order')->alias('maidan_order')->field('member.nickname,member.headimg,maidan_order.*')
            ->join('member member','member.id=maidan_order.mid','left')
            ->where($where)->order($order)
            ->page($page,$limit)
            ->select()->toArray();
        $count = Db::name('maidan_order')->alias('maidan_order')->field('member.nickname,member.headimg,maidan_order.*')
            ->join('member member','member.id=maidan_order.mid','left')
            ->where($where)->order($order)
            ->count();
		$title = array();
		$title[] = '订单号';
		$title[] = '所属门店';
		$title[] = t('会员').'信息';
		$title[] = '付款金额';
		$title[] = '实付金额';
		$title[] = t('会员').'折扣';
		$title[] = t('积分').'抵扣';
		$title[] = t('优惠券').'抵扣';
		$title[] = '付款时间';
		$title[] = '备注';
		$data = array();
		foreach($list as $v){
			$v['mdname'] = '';
			if($v['mdid']) $v['mdname'] = $mdArr[$v['mdid']];
			$tdata = array();
			$tdata[] = "'".$v['ordernum'];
			$tdata[] = $v['mdname'];
			$tdata[] = $v['nickname'].'('.t('会员').'ID:'.$v['mid'].')';
			$tdata[] = $v['money'];
			$tdata[] = $v['paymoney'];
			$tdata[] = $v['disprice'];
			$tdata[] = $v['scoredk'];
			$tdata[] = $v['couponmoney'];
			$tdata[] = date('Y-m-d H:i:s',$v['paytime']);
			$tdata[] = $v['remark'];
			$data[] = $tdata;
		}

        \app\commons\System::plog('导出买单记录');
        return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'title'=>$title]);
		$this->export_excel($title,$data);
	}
	public function logdel(){
		$ids = input('post.ids/a');
		$where = [];
		$where[] = ['aid','=',aid];
		if(bid > 0){
			$where[] = ['bid','=',bid];
		}
		if($this->mdid){
			$where[] = ['mdid','=',$this->mdid];
		}
		Db::name('maidan_order')->where($where)->where('id','in',$ids)->delete();
		\app\commons\System::plog('删除买单记录'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	public function getopenid($platform,$auth_code){
		
		$appinfo = \app\commons\System::appinfo(aid,$platform);
		$appid = $appinfo['appid'];
		$pars = [];
		if($appinfo['wxpay_type']==0){
			$pars['appid'] = $appid;
			$pars['mch_id'] = $appinfo['wxpay_mchid'];
			$mchkey = $appinfo['wxpay_mchkey'];
		}else{
			$dbwxpayset = Db::name('sysset')->where('name','wxpayset')->value('value');
			$dbwxpayset = json_decode($dbwxpayset,true);
			if(!$dbwxpayset){
				return ['status'=>0,'msg'=>'未配置服务商微信支付信息'];
			}
			$pars['appid'] = $dbwxpayset['appid'];
			$pars['sub_appid'] = $appid;
			$pars['mch_id'] = $dbwxpayset['mchid'];
			$pars['sub_mch_id'] = $appinfo['wxpay_sub_mchid'];
			$mchkey = $dbwxpayset['mchkey'];
		}
		$pars['auth_code'] = $auth_code;
		$pars['nonce_str'] = random(8);
		ksort($pars, SORT_STRING);
		$string1 = '';
		foreach ($pars as $key => $v){
			if (empty($v)) {
				continue;
			} 
			$string1 .= "{$key}={$v}&";
		}
		$string1 .= "key=".$mchkey;
		$pars['sign'] = strtoupper(md5($string1));
		//dump($pars);
		$dat = array2xml($pars);
		//dump($dat);
		$response = request_post('https://api.mch.weixin.qq.com/tools/authcodetoopenid', $dat);
		//dump($response);
		$response = @simplexml_load_string($response, 'SimpleXMLElement', LIBXML_NOCDATA);
		if (strval($response->return_code) == 'FAIL') {
			return ['status'=>0,'msg'=>strval($response->return_msg)];
		} 
		if (strval($response->result_code) == 'FAIL') {
			return ['status'=>0,'msg'=>strval($response->err_code_des)];
		}
		if($appinfo['wxpay_type']==0){
			$openid = strval($response->openid);
		}else{
			$openid = strval($response->sub_openid);
		}
		if(!$openid) return ['status'=>0,'msg'=>'获取'.t('会员').'信息失败'];
		return ['status'=>1,'openid'=>$openid];
	}
	//通过支付授权码获取会员信息
	public function getmember(){
		$auth_code = input('post.auth_code');
		if(strlen($auth_code) == '12'){ //会员卡的会员编号
			$openid = Db::name('membercard_record')->where('aid',aid)->where('card_code',$auth_code)->value('openid');
			if(!$openid) return json(['status'=>2,'msg'=>'未查找到'.t('会员').'信息']);
			$member = Db::name('member')->where('aid',aid)->where('mpopenid',$openid)->find();
		}else{
			$set = Db::name('admin_set')->where('aid',aid)->find();
			$appinfo = \app\commons\System::appinfo(aid,'mp');
			$appid = $appinfo['appid'];
			if($appid){
				$rs = $this->getopenid('mp',$auth_code);
				if($rs['status']==0) return json($rs);
				$openid = $rs['openid'];
				$member = Db::name('member')->where('aid',aid)->where('mpopenid',$openid)->find();
			}
			if(!$member){
				$appinfo = \app\commons\System::appinfo(aid,'wx');
				$appid = $appinfo['appid'];
				$rs = $this->getopenid('wx',$auth_code);
				//dump($appid);
				//dump($rs);
				if($rs['status']==1){
					$openid = $rs['openid'];
					$member = Db::name('member')->where('aid',aid)->where('wxopenid',$openid)->find();
				}
			}
		}
		if(!$member){
			return json(['status'=>2,'msg'=>'未查找到'.t('会员').'信息']);
		}
		$userlevel = Db::name('member_level')->where('aid',aid)->where('id',$member['levelid'])->find();
		$member['levelname'] = $userlevel['name'];
		if($userlevel && $userlevel['discount']>0 && $userlevel['discount']<10){
			//$disprice = $paymoney * (1 - $userlevel['discount'] * 0.1);
			$discount = $userlevel['discount'];
		}else{
			$discount = 10;
		}
		return json(['member'=>$member,'discount'=>$discount,'openid'=>$openid]);
	}
	//扫码收款
	public function shoukuan(){
		$auth_code = input('post.auth_code');
		$skmoney = input('post.skmoney');
		$mid = input('post.mid');
		$openid = input('post.openid');
		$realmoney = input('post.realmoney');
		$usemoney = input('post.usemoney');

		if(strlen($auth_code) == 12 && !$usemoney) return json(['status'=>0,'msg'=>'会员卡扫码只能使用余额支付']);

		$decmoney = 0; //余额抵扣
		if($usemoney && $mid){
			$member = Db::name('member')->where('aid',aid)->where('id',$mid)->find();
			if($member['money'] >= $realmoney){
				$decmoney = $realmoney;
			}else{
				$decmoney = $member['money'];
			}
		}
		$wxpaymoney = $realmoney - $decmoney;
		$ordernum = date('ymdHis').aid.rand(1000,9999);
		$time = time();
		
		$fenzhangmoney = 0;
		$set = Db::name('admin_set')->where('aid',aid)->find();
		if($wxpaymoney > 0){
			if(strlen($auth_code) == 12) return json(['status'=>0,'msg'=>'余额不足']);

			if(in_array('mp',$this->platform)){
				$appinfo = \app\commons\System::appinfo(aid,'mp');
			}else{
				$appinfo = \app\commons\System::appinfo(aid,'wx');
			}
			$appid = $appinfo['appid'];
			$pars = [];
			if($appinfo['wxpay_type']==0){
				$pars['appid'] = $appid;
				$pars['mch_id'] = $appinfo['wxpay_mchid'];
				$mchkey = $appinfo['wxpay_mchkey'];
			}else{
				$dbwxpayset = Db::name('sysset')->where('name','wxpayset')->value('value');
				$dbwxpayset = json_decode($dbwxpayset,true);
				if(!$dbwxpayset){
					return json(['status'=>0,'msg'=>'未配置服务商微信支付信息']);
				}
				$pars['appid'] = $dbwxpayset['appid'];
				//$pars['sub_appid'] = $appid;
				$pars['mch_id'] = $dbwxpayset['mchid'];
				$pars['sub_mch_id'] = $appinfo['wxpay_sub_mchid'];
				$mchkey = $dbwxpayset['mchkey'];

				$chouchengmoney = 0;
				$admindata = Db::name('admin')->where('id',aid)->find();
				if($admindata['chouchengset']==0){ //默认抽成
					if($dbwxpayset && $dbwxpayset['chouchengset']!=0){
						if($dbwxpayset['chouchengset'] == 1){
							$chouchengmoney = floatval($dbwxpayset['chouchengrate']) * 0.01 * $wxpaymoney;
							if($dbwxpayset['chouchengmin'] && $chouchengmoney < floatval($dbwxpayset['chouchengmin'])){
								$chouchengmoney = floatval($dbwxpayset['chouchengmin']);
							}
						}else{
							$chouchengmoney = floatval($dbwxpayset['chouchengmoney']);
						}
					}
				}elseif($admindata['chouchengset']==1){ //按比例抽成
					$chouchengmoney = floatval($admindata['chouchengrate']) * 0.01 * $wxpaymoney;
					if($chouchengmoney < floatval($admindata['chouchengmin'])){
						$chouchengmoney = floatval($admindata['chouchengmin']);
					}
				}elseif($admindata['chouchengset']==2){ //按固定金额抽成
					$chouchengmoney = floatval($admindata['chouchengmoney']);
				}
				if($chouchengmoney > 0 && $wxpaymoney*0.3 >= $chouchengmoney){ //需要分账
					$pars['profit_sharing'] = 'Y';
					$fenzhangmoney = $chouchengmoney;
				}

			}
			$pars['body'] = $set['name'].'-付款码付款';
			$pars['out_trade_no'] = $ordernum;
			$pars['total_fee'] = $wxpaymoney*100;
			$pars['spbill_create_ip'] = request()->ip();
			$pars['auth_code'] = $auth_code;
			$pars['nonce_str'] = random(8);
			ksort($pars, SORT_STRING);
			$string1 = '';
			foreach ($pars as $key => $v){
				if (empty($v)) {
					continue;
				} 
				$string1 .= "{$key}={$v}&";
			}
			$string1 .= "key=".$mchkey;
			$pars['sign'] = strtoupper(md5($string1));
			//dump($pars);
			$dat = array2xml($pars);
			//dump($dat);
			$response = request_post('https://api.mch.weixin.qq.com/pay/micropay', $dat);
			//dump($response);
			$response = @simplexml_load_string($response, 'SimpleXMLElement', LIBXML_NOCDATA);
			if (strval($response->return_code) == 'FAIL' && $response->err_code != 'USERPAYING') {
				return json(['status'=>0,'msg'=>strval($response->return_msg)]);
			} 
			if (strval($response->result_code) == 'FAIL') {
				if ($response->err_code == 'USERPAYING') {
					$result = false;
					for($i=0;$i<10;$i++){
						$pars2          = array();
						if($appinfo['wxpay_type']==0){
							$pars2['appid'] = $appid;
							$pars2['mch_id'] = $appinfo['wxpay_mchid'];
							$mchkey = $appinfo['wxpay_mchkey'];
						}else{
							$dbwxpayset = Db::name('sysset')->where('name','wxpayset')->value('value');
							$dbwxpayset = json_decode($dbwxpayset,true);
							if(!$dbwxpayset){
								return json(['status'=>0,'msg'=>'未配置服务商微信支付信息']);
							}
							$pars2['appid'] = $dbwxpayset['appid'];
							$pars2['mch_id'] = $dbwxpayset['mchid'];
							$pars2['sub_mch_id'] = $appinfo['wxpay_sub_mchid'];
							$mchkey = $dbwxpayset['mchkey'];
						}
						$pars2['out_trade_no'] = $ordernum;
						$pars2['nonce_str'] = random(8);
						ksort($pars2, SORT_STRING);
						$string2 = '';
						foreach ($pars2 as $key => $v){
							if (empty($v)) {
								continue;
							} 
							$string2 .= "{$key}={$v}&";
						}
						$string2 .= "key=".$mchkey;
						$pars2['sign'] = strtoupper(md5($string2));
						$dat2 = array2xml($pars2);
						$response2 = request_post('https://api.mch.weixin.qq.com/pay/orderquery', $dat2);
						$response2 = @simplexml_load_string($response2, 'SimpleXMLElement', LIBXML_NOCDATA);
						//var_dump($response2);
						if ($response2->return_code=='SUCCESS' && $response2->return_code == 'SUCCESS' && $response2->trade_state=="SUCCESS") {
							$result = true;
							$transaction_id = $response2->transaction_id;
							break;
						}elseif($response2->trade_state != 'USERPAYING'){
							return json(['status' => 0, 'msg' => strval($response2->trade_state_desc)]);
						}
						sleep(3);
					}
					if($result==false){
						return json(['status' => 0, 'msg' => '支付超时']);
					}
				} else {
					return json(['status' => 0, 'msg' => strval($response->err_code_des)]);
				}
			}else{
				$transaction_id = $response->transaction_id;
			}
			//dump($response);
		}
		if($decmoney > 0){
			\app\commons\Member::addmoney(aid,$mid,-$decmoney,'付款码付款');
		}
		$order = [];
		$order['aid'] = aid;
		$order['mid'] = $mid;
		$order['ordernum'] = $ordernum;
		$order['title'] = '付款码付款';
		$order['money'] = $skmoney;
		$order['paymoney'] = $realmoney;
		$order['disprice'] = $skmoney - $realmoney;
		$order['decmoney'] = $decmoney;
		$order['createtime'] = $time;
		$order['status'] = 1;
		if($decmoney > 0 && $wxpaymoney > 0){
			$order['paytype'] = '付款码,'.t('余额').'支付￥'.$decmoney.',微信支付￥'.$wxpaymoney;
		}elseif($decmoney > 0){
			$order['paytype'] = '付款码,'.t('余额').'支付￥'.$decmoney;
		}else{
			$order['paytype'] = '付款码,微信支付￥'.$wxpaymoney;
		}
		$order['paynum'] = $transaction_id;
		$order['paytime'] = time();
		$order['uid'] = $this->uid;
		$order['mdid'] = $this->mdid;
		$id = Db::name('maidan_order')->insertGetId($order);


        //消费送积分
        if($set['scorein_money']>0 && $set['scorein_score']>0){
            $paymoney = $wxpaymoney;
            if($set['score_from_moneypay'] == 1){//余额支付送积分0不送，1送
                $paymoney += $decmoney;
            }
            $givescore = floor($paymoney / $set['scorein_money']) * $set['scorein_score'];
            $res = \app\commons\Member::addscore(aid,$mid,$givescore,'消费送'.t('积分'));
            if($res && $res['status'] == 1){
				//记录消费赠送积分记录
				\app\commons\Member::scoreinlog(aid,0,$mid,'maidan',$id,$ordernum,$givescore,$paymoney);
			}
        }

		if($wxpaymoney > 0){
			//记录
			$data = array();
			$data['aid'] = aid;
			$data['mid'] = $mid;
			$data['openid'] = $openid;
			$data['tablename'] = 'maidan_order';
			$data['givescore'] = 0;
			$data['ordernum'] = $ordernum;
			$data['mch_id'] = $appinfo['wxpay_mchid'];
			$data['transaction_id'] = $transaction_id;
			$data['total_fee'] = $wxpaymoney;
			$data['createtime'] = time();
			$data['fenzhangmoney'] = $fenzhangmoney;
			Db::name('wxpay_log')->insert($data);
		}
        \app\commons\Member::uplv(aid,$mid);
		\app\commons\System::plog('买单收款'.$id);
		return json(['status'=>1,'msg'=>'收款成功','url'=>(string)url('index')]);
	}
    //打印小票
    public function wifiprint(){
        $id = input('post.id/d');
        $rs = \app\commons\Wifiprint::print(aid,'maidan',$id,0);
        return json($rs);
    }

    public function set(){
	    $admin = Db::name('admin')->where('id',aid)->field('platform')->find();
        $platform = $admin['platform'];
        $platform_arr = explode(',',$platform);
        View::assign('platform_arr',$platform_arr);
		$field = 'maidan_login,maidan_getlocation,maidan_payaftertourl';
		$admin_set =  Db::name('admin_set')->where('aid',aid)->field($field)->find();
        View::assign('admin',$admin_set);
	    return View::fetch();
    }
    public function save(){
        $maidan_login = input('maidan_login');
		$data = [];
		$data['maidan_login'] = $maidan_login;
		$data['maidan_getlocation'] = input('maidan_getlocation')?:0;
		$data['maidan_payaftertourl'] = input('maidan_payaftertourl')?:'';
        Db::name('admin_set')->where('aid',aid)->update($data);
        \app\commons\System::plog('收款码设置');
        return json(['status'=>1,'msg'=>'设置成功']);
    }

    //上传公众号接口校验文件
    public function uploadjstxt(){
        if (empty($_FILES['file']['tmp_name'])) {
            showmsg('请选择文件');
        }
        if ($_FILES['file']['type'] != 'text/plain' && $_FILES['file']['type'] != 'text/html') {
            showmsg('文件类型错误');
        }
        // 检查文件名是否包含重命名模式
        if (preg_match('/\(\d+\)|副本/',$_FILES['file']['name'])) {
            showmsg('文件名不一致,请重新上传');
        }
        $ext = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
        if(!preg_match('/^[A-Za-z0-9]+$/', file_get_contents($_FILES['file']['tmp_name']))){
            showmsg('上传文件不合法,请重新上传');
        }
        if ('txt' == strtolower($ext) ) {
            $file = $_FILES['file'];
            $file['name'] = $this->parse_path($file['name']);
            if (is_uploaded_file($file['tmp_name'])) {
                move_uploaded_file($file['tmp_name'], ROOT_PATH . '/h5/' . $file['name']);
            } else {
                rename($file['tmp_name'], ROOT_PATH . '/h5/'. $file['name']);
            }
        }elseif('html' == strtolower($ext)){
            $file = $_FILES['file'];
            $file['name'] = $this->parse_path($file['name']);
            if (is_uploaded_file($file['tmp_name'])) {
                move_uploaded_file($file['tmp_name'], ROOT_PATH .$file['name']);
            } else {
                rename($file['tmp_name'], ROOT_PATH . $file['name']);
            }
        }else{
            showmsg('上传文件不合法,请重新上传');
        }

        //$file = file_get_contents($_FILES['file']['tmp_name']);
        //$file_name = 'MP_verify_' . $file . '.txt';
        //if ($_FILES['file']['name'] != $file_name || !preg_match('/^[A-Za-z0-9]+$/', $file)) {
        //	showmsg('上传文件不合法,请重新上传');
        //}
        //file_put_contents(ROOT_PATH . '/' . $_FILES['file']['name'], $file);
        \app\commons\System::plog('上传公众号校验文件');
        showmsg('上传成功',1);
    }
    function parse_path($path) {
        $danger_char = array('../', '{php', '<?php', '<%', '<?', '..\\', '\\\\', '\\', '..\\\\', '%00', '\0', '\r');
        foreach ($danger_char as $char) {
            if ($this->strexists($path, $char)) {
                return false;
            }
        }
        return $path;
    }
    function strexists($string, $find) {
        return !(false === strpos($string, $find));
    }


    public function getMemberByTel()
    {
        $tel = input('param.tel');

        if(empty($tel)) return json(['status'=>0,'msg'=>'请输入手机号']);
        $member = Db::name('member')->where('aid',aid)->where('tel',$tel)->field('id,nickname,money')->find();
        if(empty($member))
            return json(['status'=>0,'msg'=>'未找到用户']);
        return json(['status'=>1,'data'=>$member]);
    }
}
