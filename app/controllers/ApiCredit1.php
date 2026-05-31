<?php
namespace app\controllers;
use think\facade\Db;
class ApiCredit1 extends ApiCommon {
	public function initialize() {
		parent::initialize();
		$this->checklogin();
	}
	
	//余额充值
	public function recharge(){
		$canrecharge = Db::name('admin_set')->where('aid',aid)->value('recharge');
		$giveset = Db::name('recharge_credit1_giveset')->where('aid',aid)->find();
		if($giveset && $giveset['status']==1){
			$givedata = json_decode($giveset['givedata'],true);
		}else{
			$givedata = [];
		}
		if(request()->isPost()){
			if($canrecharge == 0) return $this->json(['status'=>0,'msg'=>t('credit1').'充值功能未启用']);
			$money = input('post.money');
			if($money>0){
				$ordernum = date('ymdHis').aid.rand(1000,9999);
				//增加消费记录
				$orderdata = [];
				$orderdata['aid'] = aid;
				$orderdata['mid'] = mid;
				$orderdata['createtime']= time();
				$orderdata['money'] = $money;
				$orderdata['ordernum'] = $ordernum;
				$orderid = Db::name('recharge_credit1_order')->insertGetId($orderdata);
				$payorderid = \app\models\Payorder::createorder(aid,0,$orderdata['mid'],'recharge_credit1',$orderid,$ordernum,t('余额').'充值',$money);

				return $this->json(['status'=>1,'msg'=>'提交成功','orderid'=>$orderid,'payorderid'=>$payorderid]);
			}else{
				return $this->json(['status'=>0,'msg'=>'充值金额必须大于0']);
			}
		}
		$userinfo = [];
		$userinfo['credit1'] = $this->member['credit1'];
		$set = Db::name('admin_set')->where('aid',aid)->find();
		$rdata = [];
		$rdata['canrecharge'] = $canrecharge;
		$rdata['giveset'] = $givedata;
		$rdata['shuoming'] = $giveset['shuoming'];
		$rdata['credit1_money'] = $set['credit1_money'];
		
		
		
		
		
		$rdata['caninput'] = $giveset ? $giveset['caninput'] : 1;
		$rdata['userinfo'] = $userinfo;
		return $this->json($rdata);
	}
	public function credit1_log() {
		$st = input('param.st');
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$pernum = 20;
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['mid','=',mid];
		if($st == 1)  $where[] = ['credit1','>',0];
		if($st == 2)  $where[] = ['credit1','<',0];
		$datalist = Db::name('member_credit1log')->field("id,credit1,`after`,remark,type,from_unixtime(createtime) createtime")->where($where)->page($pagenum,$pernum)->order('id desc')->order('id desc')->select()->toArray();
		if(!$datalist) $datalist = [];
		if($pagenum == 1) {
			$credit1_transfer = Db::name('admin_set')->where('aid',aid)->value('credit1_transfer');
			$credit1_withdraw = Db::name('admin_set')->where('aid',aid)->value('credit1_withdraw');
			$credit1_money = \app\commons\Aaa::Credit1Money(aid);
			$userinfo = Db::name('member')->where('aid',aid)->where('id',mid)->field('credit1')->find();
			$userinfo['credit1in']= Db::name('member_credit1log')->where('aid',aid)->where('mid',mid)->where('credit1','>',0)->sum('credit1');
			$userinfo['credit1out']= Db::name('member_credit1log')->where('aid',aid)->where('mid',mid)->where('credit1','<',0)->sum('credit1');
		}
		return $this->json(['status'=>1,'data'=>$datalist,'userinfo'=>$userinfo,'credit1_transfer'=>$credit1_transfer,'credit1_money'=>$credit1_money,'credit1_withdraw'=>$credit1_withdraw]);
	}
	public function credit1log() {
		$st = input('param.st');
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$pernum = 20;
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['mid','=',mid];
	    if($st == 1){//充值记录
			$datalist = Db::name('recharge_credit1_order')->field("id,money,`status`,from_unixtime(createtime) createtime")->where($where)->where('status=1')->page($pagenum,$pernum)->order('id desc')->select()->toArray();
			if(!$datalist) $datalist = [];
		}elseif($st ==2){//提现记录
			$datalist = Db::name('member_credit1_withdrawlog')->field("id,money,txmoney,reason,`status`,from_unixtime(createtime) createtime")->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
			if(!$datalist) $datalist = [];
		}else{ //余额明细
			$datalist = Db::name('member_credit1log')->field("id,credit1,`after`,from_unixtime(createtime) createtime,remark")->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
			if(!$datalist) $datalist = [];
			foreach($datalist as $k=>$v){
				if(strpos($v['remark'],'商家充值，') === 0){
					$datalist[$k]['remark'] = '商家充值';
				}
			}
		}
		return $this->json(['status'=>1,'data'=>$datalist]);
	}
	
	
	public function credit1Transfer() {
         $mid = input('param.mid/d',0);
            //给他人充值 转账
            //        $info = Db::name('member_recharge')->where('aid', aid)->where('mid', mid)->find();
//        if(empty($info)) {
//            return $this->json(['status'=>0,'msg'=>'您无此权限操作']);
//        }
        $set = Db::name('admin_set')->where('aid',aid)->find();
        if($set['credit1_transfer'] != 1) {
            return $this->json(['status'=>0,'msg'=>'未开启此功能']);
        }
        if(request()->isPost()){
            $mobile = input('post.mobile');
            $mid = input('post.mid/d');
            $credit1 = input('post.credit1/f');
            if ($credit1 < 0.01){
                return $this->json(['status'=>0,'msg'=>'请输入正确的金额，最小金额为：0.01']);
            }
            if($this->sysset['transfer_rate']>0 ){
        	    $transfer_rate = $this->sysset['transfer_rate'];
		    	if($credit1%$transfer_rate!=0)return $this->json(['status'=>0,'msg'=>'金额需是'.$transfer_rate.'的倍数']);
			}
            if (input('?post.mobile') && !empty($mobile)) {
                $member = Db::name('member')->where('aid', aid)->where('tel', $mobile)->find();
            }
            if (input('?post.mid') && $mid > 0) {
                $member = Db::name('member')->where('aid', aid)->where('id', $mid)->find();
            }
            if(!$member) return $this->json(['status'=>0,'msg'=>'未找到该'.t('会员')]);
            $user_id = $member['id'];

            if ($user_id == mid) {
                return $this->json(['status'=>0,'msg'=>'不能转账给自己']);
            }
            if($set['transfer_range'] == 1) {
                //所有上下级
                $isparent = false;
                if(in_array($user_id,explode(',',$this->member['path']))){
                    $isparent = true;
                }
                if(!$isparent){
                    if(!in_array(mid,explode(',',$member['path']))){
                        return $this->json(['status'=>0,'msg'=>'仅限转账给上下级'.t('会员')]);
                    }
                }
            }
            
            
            $credit1_transfer_fee = round($credit1*$set['credit1_transfer_fee']*0.01,2);
			if ($this->sysset['transfer_fee_type']==1) {
			   $tocredit1 = round($credit1 - $credit1_transfer_fee,2);
			} else {
			   $tocredit1 = $credit1;
			   $credit1 = round($credit1 + $credit1_transfer_fee,2);
			}
		
            if ($credit1 > $this->member['credit1']){
                return $this->json(['status'=>0,'msg'=>'您的'.t('credit1').'不足']);
            }
            //验证支付密码
            $pwd_check = $set['transfer_pwd'];
            if($pwd_check){
                if(!$this->member['paypwd']){
                    return $this->json(['status'=>0,'msg'=>'请先设置支付密码','set_paypwd'=>1]);
                }
                $pay_pwd = input('paypwd')?:'';
                if(!\app\commons\Member::checkPayPwd($this->member,$pay_pwd )){
                    return $this->json(['status'=>0,'msg'=>'支付密码输入错误']);
                }
            }
            $midMsg = sprintf("转账给：%s",$member['nickname']);
            $toMidMsg = sprintf("来自%s的转账", $this->member["nickname"]);
            if ($set['transfer_range'] == 2) {
                $midMsg = sprintf("转给：%s",$member['nickname']);
                $toMidMsg = sprintf("来自%s的转账", $this->member["nickname"]);
            }
			$rs = \app\commons\Member::addcredit1(aid,mid,$credit1 * -1, $midMsg);
            if ($rs['status'] == 1) {
                \app\commons\Member::addcredit1(aid,$user_id,$tocredit1,$toMidMsg);
            }else{
				 return $this->json(['status'=>0, 'msg' => '转账失败']);
			}
            return $this->json(['status'=>1, 'msg' => '转账成功', 'url'=>'/pages/my/usercenter']);
        }
        $tomember = [];
        if($mid){
            $tomember = Db::name('member')->where('aid',aid)->where('id',$mid)->field('id,credit1,nickname,headimg')->find();
        }
        if($this->member['paypwd']=='') {
			$rdata['haspwd'] = 1;
		} else {
			$rdata['haspwd'] = 0;
		}
        $rdata['paycheck'] = $set['transfer_pwd'] ? true : false;
        $rdata['status'] = 1;
        $rdata['mycredit1'] = $this->member['credit1'];
        $rdata['credit1List'] = [];//可选金额列表
        $rdata['tomember'] = $tomember?$tomember:['nickname'=>''];//转给谁
       
        $rdata['transfer_type'] = $set['transfer_type'] ? explode(',',$set['transfer_type']) : [];
        $rdata['credit1_transfer_fee'] =$set['credit1_transfer_fee'];
        return $this->json($rdata);
	}
    
	
	
		
	public function credit11_withdraw() {
		$set = Db::name('admin_set')->where('aid',aid)->field('credit1_money,credit1_withdraw,ali_withdraw_autotransfer,credit1_transfer_pwd,credit1_withdrawmin,credit1_withdrawfee,withdraw_weixin,withdraw_aliaccount,withdraw_bankcard')->find();//withdraw_desc,
		
		$set['credit1_money']	 = \app\commons\Aaa::Credit1Money(aid);
		
		if(request()->isPost()) {
			$post = input('post.');
			if($set['credit1_withdraw'] == 0) {
				return $this->json(['status'=>0,'msg'=>t('credit1').'提现功能未开启']);
			}
			// 			$str=date('d',time());
			//             $arr = explode(',',$set['credit1_withdrawdate']);
			//             if (!in_array($str,$arr)) {
			//                  return $this->json(['status'=>0,'msg'=>'每月'.$set['credit1_withdrawdate'].'为提现日']);
			//             }
			// 			$max = Db::name('member_credit1_withdrawlog')->where('aid',aid)->where('mid',mid)->where('createtime','>=',strtotime(date('Y-m-d')))->count();
			// 			if ($max)   return $this->json(['status'=>0,'msg'=>'每月只能提现一次']);
		
			
			//             $comwithdrawnum = 100;
			// 		    if ($comwithdrawnum > 0) {
			// 			    $tim = strtotime(date('Y-m-d',time()));
			// 			    $count = Db::name('member_credit1_withdrawlog')->where('mid',mid)->where('createtime','>=',$tim)->count();
			// 			    if ($count >= $comwithdrawnum) {
			// 			       	return $this->json(['status'=>0,'msg'=>'每日最多提现'.$comwithdrawnum.'次']);
			// 			    }
			// 			}
			$money = $post['money'];
			if($money<=0 || $money < $set['credit1_withdrawmin']) {
				return $this->json(['status'=>0,'msg'=>'提现金额必须大于'.($set['credit1_withdrawmin']?$set['credit1_withdrawmin']:0)]);
			}
			if($money > $this->member['credit1']) {
				return $this->json(['status'=>0,'msg'=>'可提现'.t('credit1').'不足']);
			}
			
			
			//验证支付密码
	    	if($this->sysset['withdraw_rate']>0 ){
        	    $withdraw_rate = $this->sysset['withdraw_rate'];
		    	if($money%$withdraw_rate!=0)return $this->json(['status'=>0,'msg'=>'金额需是'.$withdraw_rate.'的倍数']);
			}
			
			$pwd_check = $this->sysset['money_pwd'];;
			if($pwd_check) {
				if(!$this->member['paypwd']) {
					return $this->json(['status'=>0,'msg'=>'请先设置支付密码','set_paypwd'=>1]);
				}
				$pay_pwd = input('paypwd')?:'';
				if(!\app\commons\Member::checkPayPwd($this->member,$pay_pwd )) {
					return $this->json(['status'=>0,'msg'=>'支付密码输入错误']);
				}
			}
			
			
			$ordernum = date('ymdHis').aid.rand(1000,9999);
			$record['aid'] = aid;
			$record['mid'] = mid;
			$record['createtime']= time();
			$record['credit1_money']= $set['credit1_money'];
        	$credit1money = $money*$set['credit1_money'];
			$record['credit1_withdrawfee'] = $credit1money*$set['credit1_withdrawfee']*0.01;
			$record['money'] = $credit1money*(100-$set['credit1_withdrawfee'])*0.01;
			if($record['money'] <= 0) {
				return $this->json(['status'=>0,'msg'=>'提现金额有误']);
			}
			$credit2 = $credit1money*$set['credit1_to_credit2']*0.01;
			$score = $credit1money*$set['credit1_to_score']*0.01;
			$credit2_admin = $credit1money*$set['credit2_admin']*0.01;
			
		
			$record['money'] = round($record['money'],2);
			$record['txmoney'] = $money;
			$record['ordernum'] = $ordernum;
			$record['paytype']  = t('佣金');
			$record['platform'] = platform;
			$record['status']  = 3;
			$record['paytime']  = time();
		//	$recordid = Db::name('member_credit1_withdrawlog')->insertGetId($record);
          	$rs =	\app\commons\Admin::addcredit1(aid,mid,$credit2_admin*-1,t('credit1')."提现");
          	if ($credit2_admin<=0) $rs = ['status'=>1];
            if ($rs['status']==1) {
                $rs2 =	\app\commons\Member::addcredit1(aid,mid,-$money,t('credit1').'提现');
               	if ($rs2['status']==1) {
               	    $recordid = Db::name('member_credit1_withdrawlog')->insertGetId($record);
               	    \app\commons\Member::addcommission(aid,mid,0,$record['money'],t('credit1').'提现',0);
    	     	    if ($credit2>0) {
    	     	       \app\commons\Member::addcredit2(aid,$record['mid'],$credit2,t('credit1').'提现');
    	     	    }
    	     	     if ($score>0) {
    	     	       \app\commons\Member::addscore(aid,$record['mid'],$score,t('credit1').'提现');
    	     	    }
    	     	    Db::name('member')->where('aid',aid)->where('id',$record['mid'])->inc('credit1_money',$credit1money)->update();
               	} else {
               	    \app\commons\Admin::addcredit1(aid,mid,$credit2_admin,t('credit1')."提现失败");
                    return json(['status'=>1,'msg'=>$rs2['msg']]);
                }
               	
            }else {
                return json(['status'=>1,'msg'=>$rs['msg']]);
            }
        
           
		   return $this->json(['status'=>1,'msg'=>'提现成功']);
		}
		$userinfo = Db::name('member')->where('id',mid)->field('id,credit1,aliaccount,tel,bankname,bankcarduser,bankcardnum')->find();
		if($this->member['paypwd']=='') {
			$userinfo['haspwd'] = 1;
		} else {
			$userinfo['haspwd'] = 0;
		}
		$userinfo['paycheck'] = $this->sysset['money_pwd'];
		$tmplids = [];
		$rdata = [];
		$rdata['status'] = 1;
		$rdata['userinfo'] = $userinfo;
		$rdata['sysset'] = $set;
		$rdata['tmplids'] = $tmplids;
		return $this->json($rdata);
	}
	
	
	
	
	
	public function credit1_withdraw() {
		$set = Db::name('admin_set')->where('aid',aid)->field('credit1_money,credit1_withdraw,ali_withdraw_autotransfer,credit1_transfer_pwd,credit1_withdrawmin,credit1_withdrawfee,withdraw_weixin,withdraw_aliaccount,withdraw_bankcard')->find();
		$set['credit1_money']	 = \app\commons\Aaa::Credit1Money(aid);
		if(request()->isPost()) {
			$post = input('post.');
			if($set['credit1_withdraw'] == 0) {
				return $this->json(['status'=>0,'msg'=>t('credit1').'提现功能未开启']);
			}
			// 			$str=date('d',time());
			//             $arr = explode(',',$set['credit1_withdrawdate']);
			//             if (!in_array($str,$arr)) {
			//                  return $this->json(['status'=>0,'msg'=>'每月'.$set['credit1_withdrawdate'].'为提现日']);
			//             }
			// 			$max = Db::name('member_credit1_withdrawlog')->where('aid',aid)->where('mid',mid)->where('createtime','>=',strtotime(date('Y-m-d')))->count();
			// 			if ($max)   return $this->json(['status'=>0,'msg'=>'每月只能提现一次']);
			if($post['paytype']=='支付宝' && $this->member['aliaccount']=='') {
				return $this->json(['status'=>0,'msg'=>'请先设置支付宝账号']);
			}
			if($post['paytype']=='银行卡' && ($this->member['bankname']==''||$this->member['bankcarduser']==''||$this->member['bankcardnum']=='')) {
				return $this->json(['status'=>0,'msg'=>'请先设置完整银行卡信息']);
			}
		
			
			//             $comwithdrawnum = 100;
			// 		    if ($comwithdrawnum > 0) {
			// 			    $tim = strtotime(date('Y-m-d',time()));
			// 			    $count = Db::name('member_credit1_withdrawlog')->where('mid',mid)->where('createtime','>=',$tim)->count();
			// 			    if ($count >= $comwithdrawnum) {
			// 			       	return $this->json(['status'=>0,'msg'=>'每日最多提现'.$comwithdrawnum.'次']);
			// 			    }
			// 			}
			$money = $post['money'];
			if($money<=0 || $money < $set['credit1_withdrawmin']) {
				return $this->json(['status'=>0,'msg'=>'提现金额必须大于'.($set['credit1_withdrawmin']?$set['credit1_withdrawmin']:0)]);
			}
			if($money > $this->member['credit1']) {
				return $this->json(['status'=>0,'msg'=>'可提现'.t('credit1').'不足']);
			}
			
			
			$pwd_check = $this->sysset['money_pwd'];;
			if($pwd_check) {
				if(!$this->member['paypwd']) {
					return $this->json(['status'=>0,'msg'=>'请先设置支付密码','set_paypwd'=>1]);
				}
				$pay_pwd = input('paypwd')?:'';
				if(!\app\commons\Member::checkPayPwd($this->member,$pay_pwd )) {
					return $this->json(['status'=>0,'msg'=>'支付密码输入错误']);
				}
			}
				//验证支付密码
	    	if($this->sysset['withdraw_rate']>0 ){
        	    $withdraw_rate = $this->sysset['withdraw_rate'];
		    	if($money%$withdraw_rate!=0)return $this->json(['status'=>0,'msg'=>'金额需是'.$withdraw_rate.'的倍数']);
			}
			
			
			
			
			
			$ordernum = date('ymdHis').aid.rand(1000,9999);
			$record['aid'] = aid;
			$record['mid'] = mid;
			$record['createtime']= time();
	
			$record['credit1_withdrawfee'] = $money*$set['credit1_withdrawfee']*0.01;
			$record['money'] = $money*(100-$set['credit1_withdrawfee'])*0.01*$set['credit1_money'];
			$record['credit1_money']= $set['credit1_money'];
			if($record['money'] <= 0) {
				return $this->json(['status'=>0,'msg'=>'提现金额有误']);
			}
			$record['money'] = round($record['money'],2);
			$record['txmoney'] = $money;
			if($post['paytype']=='支付宝') {
				$record['aliaccountname'] = $this->member['aliaccountname'];
				$record['aliaccount'] = $this->member['aliaccount'];
			}
			if($post['paytype']=='银行卡') {
				$record['bankname'] = $this->member['bankname'] . ($this->member['bankaddress'] ? ' '.$this->member['bankaddress'] : '');
				$record['bankcarduser'] = $this->member['bankcarduser'];
				$record['bankcardnum'] = $this->member['bankcardnum'];
			}
			$record['ordernum'] = $ordernum;
			$record['paytype']  = $post['paytype'];
			$record['platform'] = platform;
			$recordid = Db::name('member_credit1_withdrawlog')->insertGetId($record);
			\app\commons\Member::addcredit1(aid,mid,-$money,t('credit1').'提现');
			if($post['paytype'] != '') {
				$tmplcontent = array();
				$tmplcontent['first'] = '有客户申请'.t('credit1').'收益提现';
				$tmplcontent['remark'] = '点击进入查看~';
				$tmplcontent['keyword1'] = $this->member['nickname'];
				$tmplcontent['keyword2'] = date('Y-m-d H:i');
				$tmplcontent['keyword3'] = $money.'元';
				$tmplcontent['keyword4'] = $post['paytype'];
				\app\commons\Wechat::sendhttmpl(aid,0,'tmpl_withdraw',$tmplcontent,m_url('admin/finance/credit1withdrawlog'));
			}
			if($set['withdraw_autotransfer'] && ($post['paytype'] = '微信钱包' || $post['paytype'] = '银行卡')) {
				Db::name('member_credit1_withdrawlog')->where('id',$recordid)->update(['status' => 1]);
				$rs = \app\commons\Wxpay::transfers(aid,mid,$record['money'],$record['ordernum'],platform,t('credit1').'收益提现');
				if($rs['status']==0) {
					return json(['status'=>1,'msg'=>'提交成功,请等待打款']);
				} else {
					Db::name('member_credit1_withdrawlog')->where('id',$recordid)->update(['status' => 3]);
					Db::name('member_credit1_withdrawlog')->where('aid',aid)->where('id',$recordid)->update(['status'=>3,'paytime'=>time(),'paynum'=>$rs['resp']['payment_no']]);
					//提现成功通知
					$tmplcontent = [];
					$tmplcontent['first'] = '您的提现申请已打款，请留意查收';
					$tmplcontent['remark'] = '请点击查看详情~';
					$tmplcontent['money'] = (string) round($record['money'],2);
					$tmplcontent['timet'] = date('Y-m-d H:i',$record['createtime']);
					\app\commons\Wechat::sendtmpl(aid,$record['mid'],'tmpl_tixiansuccess',$tmplcontent,m_url('pages/my/usercenter'));
					//订阅消息
					$tmplcontent = [];
					$tmplcontent['amount1'] = $record['money'];
					$tmplcontent['thing3'] = '微信打款';
					$tmplcontent['time5'] = date('Y-m-d H:i');
					$tmplcontentnew = [];
					$tmplcontentnew['amount3'] = $record['money'];
					$tmplcontentnew['phrase9'] = '微信打款';
					$tmplcontentnew['date8'] = date('Y-m-d H:i');
					\app\commons\Wechat::sendwxtmpl(aid,$record['mid'],'tmpl_tixiansuccess',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);
					//短信通知
					if($this->member['tel']) {
						\app\commons\Sms::send(aid,$this->member['tel'],'tmpl_tixiansuccess',['money'=>$record['money']]);
					}
					return json(['status'=>1,'msg'=>$rs['msg']]);
				}
			}
			if($set['ali_withdraw_autotransfer'] && $post['paytype'] = '支付宝') {
				Db::name('member_credit1_withdrawlog')->where('id',$recordid)->update(['status' => 1]);
				///	$rs = \app\commons\Wxpay::transfers(aid,mid,$record['money'],$record['ordernum'],platform,t('credit1').'收益提现');
				$rs = \app\commons\Alipay::transfers(aid,$record['ordernum'],$record['money'],t('credit1').'提现',$this->member['aliaccount'],$this->member['aliaccountname'],t('credit1').'提现');
				if($rs['status']==0) {
					$sub_msg = $rs['sub_msg']?$rs['sub_msg']:'';
					if($sub_msg) {
						Db::name('member_credit1_withdrawlog')->where('aid',aid)->where('id',$recordid)->update(['reason'=>$sub_msg]);
					}
					return json(['status'=>1,'msg'=>'提交成功,请等待打款']);
				} else {
					Db::name('member_credit1_withdrawlog')->where('id',$recordid)->update(['status' => 3]);
					Db::name('member_credit1_withdrawlog')->where('aid',aid)->where('id',$recordid)->update(['status'=>3,'paytime'=>time(),'paynum'=>$rs['pay_fund_order_id']]);
					//提现成功通知
					$tmplcontent = [];
					$tmplcontent['first'] = '您的提现申请已打款，请留意查收';
					$tmplcontent['remark'] = '请点击查看详情~';
					$tmplcontent['money'] = (string) round($record['money'],2);
					$tmplcontent['timet'] = date('Y-m-d H:i',$record['createtime']);
					\app\commons\Wechat::sendtmpl(aid,$record['mid'],'tmpl_tixiansuccess',$tmplcontent,m_url('pages/my/usercenter'));
					//订阅消息
					$tmplcontent = [];
					$tmplcontent['amount1'] = $record['money'];
					$tmplcontent['thing3'] = '支付宝打款';
					$tmplcontent['time5'] = date('Y-m-d H:i');
					$tmplcontentnew = [];
					$tmplcontentnew['amount3'] = $record['money'];
					$tmplcontentnew['phrase9'] = '支付宝打款';
					$tmplcontentnew['date8'] = date('Y-m-d H:i');
					\app\commons\Wechat::sendwxtmpl(aid,$record['mid'],'tmpl_tixiansuccess',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);
					//短信通知
					if($this->member['tel']) {
						\app\commons\Sms::send(aid,$this->member['tel'],'tmpl_tixiansuccess',['money'=>$record['money']]);
					}
					return json(['status'=>1,'msg'=>$rs['msg']]);
				}
			}
			return $this->json(['status'=>1,'msg'=>'提交成功,请等待打款']);
		}
		$userinfo = Db::name('member')->where('id',mid)->field('id,credit1,aliaccount,tel,bankname,bankcarduser,bankcardnum')->find();
		//订阅消息
		if($this->member['paypwd']=='') {
			$userinfo['haspwd'] = 1;
		} else {
			$userinfo['haspwd'] = 0;
		}
		$userinfo['paycheck'] = $this->sysset['money_pwd'];
		$rdata = [];
		$rdata['status'] = 1;
		$rdata['paycheck'] = $this->sysset['money_pwd'];
		$rdata['userinfo'] = $userinfo;
		$rdata['sysset'] = $set;
		$rdata['tmplids'] = $tmplids;
		return $this->json($rdata);
	}
// 	//转送
// 	public function credit1Transfer() {
// 		$set = Db::name('admin_set')->where('aid',aid)->find();
// 		if($set['credit1_transfer'] != 1) {
// 			return $this->json(['status'=>0,'msg'=>'未开启此功能']);
// 		}
// 		if(request()->isPost()) {
// 			$mobile = input('post.mobile');
// 			$mid = input('post.mid/d');
// 			$credit1 = input('post.credit1/f');
// 			if ($credit1 < 0.01) {
// 				return $this->json(['status'=>0,'msg'=>'请输入正确的金额，最小金额为：0.01']);
// 			}
// 			if(getcustom('100_transfer')) {
// 				if ($credit1%100!=0) {
// 					return $this->json(['status'=>0,'msg'=>'数量必须输入100的倍数']);
// 				}
// 			}
// 			if (input('?post.mobile')) {
// 				$member = Db::name('member')->where('aid', aid)->where('tel', $mobile)->find();
// 			}
// 			if (input('?post.mid')) {
// 				$member = Db::name('member')->where('aid', aid)->where('id', $mid)->find();
// 			}
// 			if(!$member) return $this->json(['status'=>0,'msg'=>'未找到该'.t('会员')]);
// 			$user_id = $member['id'];
// 			if ($user_id == mid) {
// 				return $this->json(['status'=>0,'msg'=>'不能转账给自己']);
// 			}
// 			if($set['credit1_transfer_range'] == 1) {
// 				//所有上下级
// 				$isparent = false;
// 				if(in_array($user_id,explode(',',$this->member['path']))) {
// 					$isparent = true;
// 				}
// 				if(!$isparent) {
// 					if(!in_array(mid,explode(',',$member['path']))) {
// 						return $this->json(['status'=>0,'msg'=>'仅限转账给上下级'.t('会员')]);
// 					}
// 				}
// 			}
// 			$credit1_transfer_fee = round($credit1*$set['credit1_transfer_fee']*0.01,2);
// 			$tocredit1 = round($credit1*(100 + $set['credit1_transfer_fee'])*0.01,2);
// 			if ($tocredit1 > $this->member['credit1']) {
// 				return $this->json(['status'=>0,'msg'=>'您的'.t('credit1').'不足']);
// 			}
// 			$smscode = input('post.code');
// // 			if( md5($this->member['tel'].'-'.$smscode) != cache($this->sessionid.'_smscode') || cache($this->sessionid.'_smscodetimes')>5) {
// // 				cache($this->sessionid.'_smscodetimes',cache($this->sessionid.'_smscodetimes')+1);
// // 				return $this->json(['status'=>0,'msg'=>'短信验证码错误']);
// // 			}
// // 			cache(input('param.session_id').'_smscode',null);
// // 			cache(input('param.session_id').'_smscodetimes',null);
// 			//验证支付密码
// 			$pwd_check = $set['credit1_transfer_pwd'];
// 			if($pwd_check) {
// 				if(!$this->member['paypwd']) {
// 					return $this->json(['status'=>0,'msg'=>'请先设置支付密码','set_paypwd'=>1]);
// 				}
// 				$pay_pwd = input('paypwd')?:'';
// 				if(!\app\commons\Member::checkPayPwd($this->member,$pay_pwd )) {
// 					return $this->json(['status'=>0,'msg'=>'支付密码输入错误']);
// 				}
// 			}
// 			$rs = \app\commons\Member::addcredit1(aid,mid,$tocredit1 * -1, sprintf("转账给：%s",$member['nickname']));
// 			if ($rs['status'] == 1) {
// 				\app\commons\Member::addcredit1(aid,$user_id,$credit1,sprintf("来自%s的转账", $this->member["nickname"]));
// 			} else {
// 				return $this->json(['status'=>0, 'msg' => '转账失败']);
// 			}
// 			return $this->json(['status'=>1, 'msg' => '转账成功', 'url'=>m_url('my/usercenter').'/aid/'.aid]);
// 		}
// 		if($this->member['paypwd']=='') {
// 			$userinfo['haspwd'] = 1;
// 		} else {
// 			$userinfo['haspwd'] = 0;
// 		}
// 		$userinfo['tel'] = $this->member['tel'];
// 		$rdata['userinfo'] = $userinfo;
// 		$rdata['paycheck'] = $set['credit1_transfer_pwd'];
// 		$rdata['paytype'] = $set['credit1_transfer_type'];
// 		$rdata['credit1_transfer_fee'] = $set['credit1_transfer_fee'];
// 		$rdata['status'] = 1;
// 		$rdata['mycredit1'] = $this->member['credit1'];
// 		return $this->json($rdata);
// 	}
// 	public function change_credit1() {
// 		$set = Db::name('admin_set')->where('aid', aid)->field('credit1_cm,credit1_c2,credit1_mo')->find();
// 		$moneyList['commission'] = array('credit1_fee'=>$set['credit1_cm'], 'credit1_min' =>0);
// 		$moneyList['credit1'] = array('credit1_fee'=>$set['credit1_c2'], 'credit1_min' => 0);
// 		$moneyList['money'] = array('credit1_fee'=>$set['credit1_mo'], 'credit1_min' =>0);
// 		if(request()->isPost()) {
// 			$paytype = input('post.paytype');
// 			$credit1 = input('post.credit1');
// 			if ($credit1 <= 0) {
// 				return $this->json(['status'=>0,'msg'=>'请输入正确的'.t('credit1').'数量']);
// 			}
// 			if ($credit1 > $this->member['credit1']) {
// 				return $this->json(['status'=>0,'msg'=>'您的'.t('credit1').'数量不足']);
// 			}
// 			// 			if ($credit1%$set['change_credit1']!=0){
// 			//                 return $this->json(['status'=>0,'msg'=>'金额必须输入'.$set['change_credit1'].'的倍数']);
// 			//             }
// 			if(!$this->member['paypwd']) {
// 				return $this->json(['status'=>0,'msg'=>'请先设置支付密码','set_paypwd'=>1]);
// 			}
// 			$pay_pwd = input('paypwd')?:'';
// 			if(!\app\commons\Member::checkPayPwd($this->member,$pay_pwd )) {
// 				return $this->json(['status'=>0,'msg'=>'支付密码输入错误']);
// 			}
// 			if ($paytype=='commission') {
// 				$remark =  t('credit1')."兑换".t('佣金');
// 			} elseif($paytype=='credit1') {
// 				$remark =  t('credit1')."兑换".t('credit1');
// 			} elseif($paytype=='money') {
// 				$remark =  t('credit1')."兑换".t('余额');
// 			} else {
// 				return $this->json(['status'=>0,'msg'=>'请选择兑换类型']);
// 			}
// 			$credit1_fee = $moneyList[$paytype]['credit1_fee'];
// 			$change_fee = round($credit1*$credit1_fee*0.01,2);
// 			$change_money = round(($credit1 - $change_fee)*$set['credit1_money'],2);
// 			$rs = \app\commons\Member::addcredit1(aid,mid,$credit1 * -1,$remark);
// 			if ($rs['status'] == 1) {
// 				$buildfun = 'add'.$paytype;
// 				if ($paytype =='commission') {
// 					\app\commons\Member::$buildfun(aid,mid,0,$change_money,$remark);
// 				} else {
// 					\app\commons\Member::$buildfun(aid,mid,$change_money,$remark);
// 				}
// 				\app\commons\Admin::addcredit(aid,mid,$change_fee,$remark);
// 				\app\commons\Admin::addcredit1(aid,mid,$credit1* -1,$remark);
// 			} else {
// 				return $this->json(['status'=>0, 'msg' => '兑换失败', 'url'=>m_url('my/usercenter').'/aid/'.aid]);
// 			}
// 		}
// 		$rdata['status'] = 1;
// 		$rdata['set'] = $set;
// 		$userinfo['credit1'] = $this->member['credit1'];
// 		if($this->member['paypwd']=='') {
// 			$userinfo['haspwd'] = 1;
// 		} else {
// 			$userinfo['haspwd'] = 0;
// 		}
// 		$rdata['userinfo'] =$userinfo;
// 		$rdata['moneyList'] = $moneyList;
// 		//可选金额列表
// 		return $this->json($rdata);
// 	}
}