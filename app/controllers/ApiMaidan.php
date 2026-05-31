<?php


//买单
namespace app\controllers;
use think\facade\Db;
class ApiMaidan extends ApiCommon
{
	public function initialize(){
		parent::initialize();
		$action = request()->action();
		$maidan_login = Db::name('admin_set')->where('aid',aid)->value('maidan_login');
		if($action!='maidan'){
            $this->checklogin();
        }
        if($action=='maidan' && ($maidan_login==1 || !in_array(platform,['wx','alipay','mp']))){
            $params = [];
            //买单页面后台开启了强制登录
            $this->checklogin(0,$params);
        }
	}
	//买单收款
	public function maidan(){
		$adminset = Db::name('admin_set')->where('aid',aid)->find();
		$scoredkmaxpercent = $adminset['scoredkmaxpercent'];
		$bid = input('param.bid') ? input('param.bid') : 0;
		
		
        if($bid > 0){
            $business = Db::name('business')->where('aid',aid)->where('id', $bid)->find();
            if(empty($business)){
                return $this->json(['status'=>0,'msg'=>'商家不存在']);
            }
            if($business['status'] != 1){
                return $this->json(['status'=>0,'msg'=>'商家状态异常']);
            }
		}
		//第一个静默登录，第二次必须绑定手机号
		if(request()->isPost()){
			$post = input('post.');
			$money = floatval($post['money']);
			if($money <= 0){
				return $this->json(['status'=>0,'msg'=>'支付金额必须大于0']);
			}
			$paymoney = $money;
			//会员折扣
			$disprice = 0;
			$userlevel = Db::name('member_level')->where('aid',aid)->where('id',$this->member['levelid'])->find();
			if($userlevel && $userlevel['discount']>0 && $userlevel['discount']<10){
				$disprice = $paymoney * (1 - $userlevel['discount'] * 0.1);
			}
			$paymoney = $paymoney - $disprice;
			//优惠券
			if($post['couponrid'] > 0){
				$couponrid = $post['couponrid'];
				$couponrecord = Db::name('coupon_record')->where('aid',aid)->where('mid',mid)->where('id',$couponrid)->find();
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
				}elseif(! in_array($couponrecord['type'],[1,10])){
					return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'不符合条件']);	
				}
                if($couponrecord['type']==10) {//折扣券    
                    $couponmoney = $paymoney * (100 - $couponrecord['discount']) * 0.01;
                    if ($couponmoney > $paymoney) $couponmoney = $paymoney;
                }else{
                    $couponmoney = $couponrecord['money'];
                    if($couponmoney > $money) $couponmoney = $money;
                }
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
				}else{
					$decscore = 0;
				}
			}else{
				$scoredk = 0;
				$decscore = 0;
			}

			$score_weishu = 0;
			$decscore = dd_money_format($decscore,$score_weishu);

			//$mendian = Db::name('mendian')->where('id',$post['mdid'])->find();
			if($bid > 0){
				$bname = Db::name('business')->where('id',$bid)->value('name');
			}else{
				$bname = Db::name('admin_set')->where('aid',aid)->value('name');
			}
			//创建订单
			$order = [];
			$order['ordernum'] = date('ymdHis').aid.rand(1000,9999);
			$order['aid'] = aid;
			$order['bid'] = $bid;
			$order['mid'] = mid;
			$order['title'] = '付款给'.$bname;
			$order['money'] = $money;
			$order['paymoney'] = $paymoney;
			$order['disprice'] = $disprice;
			$order['scoredk'] = $scoredk;
			$order['decscore'] = $decscore;
			$order['couponrid'] = $couponrid;
			$order['couponmoney'] = $couponmoney; //优惠券抵扣
			$order['createtime'] = time();
			$order['platform'] = platform;
			$order['status'] = 0;
			$order['mdid'] = $post['mdid'];
			$order['remark'] = $post['remark']?$post['remark']:'';
			//多商户商品不参与分红时
            $orderid = Db::name('maidan_order')->insertGetId($order);

			$payorderid = \app\models\Payorder::createorder(aid,$order['bid'],$order['mid'],'maidan',$orderid,$order['ordernum'],$order['title'],$order['paymoney'],$order['decscore']);

			return $this->json(['status'=>1,'payorderid'=>$payorderid]);
		}

		$userlevel = Db::name('member_level')->where('aid',aid)->where('id',$this->member['levelid'])->find();
		$userinfo = [];
		$userinfo['discount'] = $userlevel['discount'];
		$userinfo['score'] = $this->member['score'];
		$userinfo['score2money'] = $adminset['score2money'];
		$userinfo['dkmoney'] = round($userinfo['score'] * $userinfo['score2money'],2);
		$userinfo['scoredkmaxpercent'] = $scoredkmaxpercent;
		$userinfo['money'] = $this->member['money'];
        $userinfo['maidan_getlocation'] = $adminset['maidan_getlocation'];
		if($this->member['paypwd']==''){
			$userinfo['haspwd'] = 0;
		}else{
			$userinfo['haspwd'] = 1;
		}
		if($bid > 0){
			$bname = $business['name'];
			$bcids = $business['cid'] ? explode(',',$business['cid']) : [];
		}else{
			$bcids = [];
		}
		if($bcids){
			$whereCid = [];
			foreach($bcids as $bcid){
				$whereCid[] = "find_in_set({$bcid},canused_bcids)";
			}
			$whereCids = implode(' or ',$whereCid);
		}else{
			$whereCids = '0=1';
		}

		$couponList = Db::name('coupon_record')
			->where('aid',aid)->where('mid',mid)->where('type','in',[1,10])->where('status',0)->where('starttime','<=',time())->where('endtime','>',time())
			->whereRaw("bid=-1 or bid=".$bid." or (bid=0 and (canused_bids='all' or find_in_set(".$bid.",canused_bids) or ($whereCids)))")
			->order('id desc')->select()->toArray();

		if(!$couponList) $couponList = [];
		$newcouponlist = [];
		foreach($couponList as $k=>$v){
			//$couponList[$k]['starttime'] = date('m-d H:i',$v['starttime']);
			//$couponList[$k]['endtime'] = date('m-d H:i',$v['endtime']);
            $v['bname'] = $bname;
			$couponinfo = Db::name('coupon')->where('aid',aid)->where('id',$v['couponid'])->find();
			$fwtype = [0];
			if(!in_array($couponinfo['fwtype'],$fwtype)){//全部可用 
				continue;
			}
			//适用场景
			$fwscene = [0];
            if(getcustom('coupon_maidan_cashdesk')){
                $fwscene[] = 1;
            }
            if(!in_array($couponinfo['fwscene'],$fwscene)){//全部可用 
                continue;
            }
            if($couponinfo['isgive']==2){//仅赠送
                continue;
            }
			$newcouponlist[] = $v;
		}
		$couponList = $newcouponlist;
		//门店
		$whereM = [];
		$whereM[] = ['aid','=',aid];
		$whereM[] = ['status','=',1];
	
		if($bid>0){
			$whereM[] = ['bid','=',$bid];
		}else{
            $bids = [0];
			$whereM[] = ['bid','in',$bids];
		}
        //是不是置顶
		$mdlist = Db::name('mendian')->where($whereM)->order('id')->select()->toArray();
		if(!$mdlist) $mdlist = [];
		$set = Db::name('admin_set')->where('aid',aid)->find();
		if($bid > 0){
			$set['name'] = Db::name('business')->where('id',$bid)->value('name');
			$set['logo'] = Db::name('business')->where('id',$bid)->value('logo');
		}
		$rdata = [];
        //买单广告
        $adlist = [];
        $rdata['adlist'] = $adlist;
		$rdata['userinfo'] = $userinfo;
		$rdata['couponList'] = $couponList;
		$rdata['wxpayst'] = $set['wxpay'];
		$rdata['alipay'] = $set['alipay'];
		$rdata['moneypay'] = $set['moneypay'];
		$rdata['name'] = $set['name'];
		$rdata['logo'] = $set['logo'];
		$rdata['mdlist'] = $mdlist;
		//判断是否登录返回给前端，没登录的话前端静默注册
        $mid = mid;
		$need_login = 0;
		$have_login = 1;
		$login_tip = '';
		if(!$mid){
		    $need_login = 1;
            }
		$rdata['need_login'] = $need_login;
        $rdata['have_login'] = $have_login;
        $rdata['login_tip'] = $login_tip;

        $alipayapp = \app\commons\System::appinfo(aid,'alipay');
        $rdata['ali_appid'] = $alipayapp['appid'];
        return $this->json($rdata);
	}

	public function maidanlog(){
		$pagenum = input('post.pagenum');
        $st = input('post.st');
		if(!$pagenum) $pagenum = 1;
		$pernum = 20;
		if(input('param.keyword')) $where[] = ['ordernum|paynum','like','%'.input('param.keyword').'%'];
        if(false){}else{
            $where = [];
            $where[] = ['aid','=',aid];
            $where[] = ['mid','=',mid];
            $where[] = ['status','=',1];
        }
		$datalist = Db::name('maidan_order')->field('*,from_unixtime(paytime)paytime')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
        if(!$datalist) $datalist = [];
        $canrefund = false;
        if(request()->isPost()){
			return $this->json(['status'=>1,'data'=>$datalist,'canrefund'=>$canrefund]);
		}
		$count = Db::name('maidan_order')->where($where)->count();
		$rdata = [];
		$rdata['count'] = $count;
		$rdata['datalist'] = $datalist;
		$rdata['pernum'] = $pernum;
		$rdata['st'] = $st;
        $rdata['canrefund'] = $canrefund;
		return $this->json($rdata);
	}
	public function maidandetail(){
		$id = input('param.id/d');
        if(!$id)
            return $this->json(['status'=>0,'msg'=>'参数错误']);
		$detail = Db::name('maidan_order')->where('aid',aid)->where('mid',mid)->where('id',$id)->find();
		$detail['paytime'] = date('Y-m-d H:i:s',$detail['paytime']);
		if($detail['couponrid']){
			$couponrecord = Db::name('coupon_record')->where('aid',aid)->where('mid',mid)->where('id',$detail['couponrid'])->find();
		}else{
			$couponrecord = false;
		}
		if($detail['mdid']){
			$mendian = Db::name('mendian')->field('id,name')->where('aid',aid)->where('id',$detail['mdid'])->find();
		}else{
			$mendian = false;
		}
        if($detail['paynum']){
            $detail['paynum'] = '';
        }
        $detail['canrefund'] = false;
        $rdata = [];
		$rdata['detail'] = $detail;
		$rdata['couponrecord'] = $couponrecord;
		$rdata['mendian'] = $mendian;
		return $this->json($rdata);
	}
}