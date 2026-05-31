<?php
namespace app\controllers;
use think\facade\Db;
class ApiCredit3 extends ApiCommon {
	public function initialize() {
		parent::initialize();
		$this->checklogin();
	}
	public function credit3_log() {
		$st = input('param.st');
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$pernum = 20;
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['mid','=',mid];
		if($st == 1)  $where[] = ['credit3','>',0];
		if($st == 2)  $where[] = ['credit3','<',0];
		$datalist = Db::name('member_credit3log')->field("id,credit3,`after`,remark,type,from_unixtime(createtime) createtime")->where($where)->page($pagenum,$pernum)->order('id desc')->order('id desc')->select()->toArray();
		if(!$datalist) $datalist = [];
		if($pagenum == 1) {
			$credit3_transfer = Db::name('admin_set')->where('aid',aid)->value('credit3_transfer');
			$credit3_withdraw = Db::name('admin_set')->where('aid',aid)->value('credit3_withdraw');
			$credit3_money = Db::name('admin_set')->where('aid',aid)->value('credit3_money');
			$userinfo = Db::name('member')->where('aid',aid)->where('id',mid)->field('credit3')->find();
			$userinfo['credit3in']= Db::name('member_credit3log')->where('aid',aid)->where('mid',mid)->where('credit3','>',0)->sum('credit3');
			$userinfo['credit3out']= Db::name('member_credit3log')->where('aid',aid)->where('mid',mid)->where('credit3','<',0)->sum('credit3');
		}
		return $this->json(['status'=>1,'data'=>$datalist,'userinfo'=>$userinfo,'credit3_transfer'=>$credit3_transfer,'credit3_money'=>$credit3_money,'credit3_withdraw'=>$credit3_withdraw]);
	}
	public function credit3log() {
		$st = input('param.st');
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$pernum = 20;
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['mid','=',mid];
		$datalist = Db::name('member_credit3_withdrawlog')->field("id,money,txmoney,reason,`status`,from_unixtime(createtime) createtime")->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		if(!$datalist) $datalist = [];
		return $this->json(['status'=>1,'data'=>$datalist]);
	}
	public function credit3_withdraw() {
		$set = Db::name('admin_set')->where('aid',aid)->field('credit3_withdraw,ali_withdraw_autotransfer,credit3_transfer_pwd,credit3_withdrawmin,credit3_withdrawfee,credit3_money,withdraw_weixin,withdraw_aliaccount,withdraw_bankcard')->find();
		if(request()->isPost()) {
			$post = input('post.');
			if($set['credit3_withdraw'] == 0) {
				return $this->json(['status'=>0,'msg'=>t('credit3').'提现功能未开启']);
			}
			// 			$str=date('d',time());
			//             $arr = explode(',',$set['credit3_withdrawdate']);
			//             if (!in_array($str,$arr)) {
			//                  return $this->json(['status'=>0,'msg'=>'每月'.$set['credit3_withdrawdate'].'为提现日']);
			//             }
			// 			$max = Db::name('member_credit3_withdrawlog')->where('aid',aid)->where('mid',mid)->where('createtime','>=',strtotime(date('Y-m-d')))->count();
			// 			if ($max)   return $this->json(['status'=>0,'msg'=>'每月只能提现一次']);
			if($post['paytype']=='支付宝' && $this->member['aliaccount']=='') {
				return $this->json(['status'=>0,'msg'=>'请先设置支付宝账号']);
			}
			if($post['paytype']=='银行卡' && ($this->member['bankname']==''||$this->member['bankcarduser']==''||$this->member['bankcardnum']=='')) {
				return $this->json(['status'=>0,'msg'=>'请先设置完整银行卡信息']);
			}
			//验证支付密码
			$pwd_check = 1;
			if($pwd_check) {
				if(!$this->member['paypwd']) {
					return $this->json(['status'=>0,'msg'=>'请先设置支付密码','set_paypwd'=>1]);
				}
				$pay_pwd = input('paypwd')?:'';
				if(!\app\commons\Member::checkPayPwd($this->member,$pay_pwd )) {
					return $this->json(['status'=>0,'msg'=>'支付密码输入错误']);
				}
			}
			//             $comwithdrawnum = 100;
			// 		    if ($comwithdrawnum > 0) {
			// 			    $tim = strtotime(date('Y-m-d',time()));
			// 			    $count = Db::name('member_credit3_withdrawlog')->where('mid',mid)->where('createtime','>=',$tim)->count();
			// 			    if ($count >= $comwithdrawnum) {
			// 			       	return $this->json(['status'=>0,'msg'=>'每日最多提现'.$comwithdrawnum.'次']);
			// 			    }
			// 			}
			$money = $post['money'];
			if($money<=0 || $money < $set['credit3_withdrawmin']) {
				return $this->json(['status'=>0,'msg'=>'提现金额必须大于'.($set['credit3_withdrawmin']?$set['credit3_withdrawmin']:0)]);
			}
			if($money > $this->member['credit3']) {
				return $this->json(['status'=>0,'msg'=>'可提现'.t('credit3').'不足']);
			}
			$ordernum = date('ymdHis').aid.rand(1000,9999);
			$record['aid'] = aid;
			$record['mid'] = mid;
			$record['createtime']= time();
			//	$record['money'] = $money*(100-$set['credit3_withdrawfee'])*0.01;
			$record['credit3_withdrawfee'] = $money*$set['credit3_withdrawfee']*0.01;
			$record['money'] = $money*(100-$set['credit3_withdrawfee'])*0.01*$set['credit3_money'];
			$record['credit3_money']= $set['credit3_money'];
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
			$recordid = Db::name('member_credit3_withdrawlog')->insertGetId($record);
			\app\commons\Member::addcredit3(aid,mid,-$money,t('credit3').'提现');
			if($post['paytype'] != '') {
				$tmplcontent = array();
				$tmplcontent['first'] = '有客户申请'.t('credit3').'收益提现';
				$tmplcontent['remark'] = '点击进入查看~';
				$tmplcontent['keyword1'] = $this->member['nickname'];
				$tmplcontent['keyword2'] = date('Y-m-d H:i');
				$tmplcontent['keyword3'] = $money.'元';
				$tmplcontent['keyword4'] = $post['paytype'];
				\app\commons\Wechat::sendhttmpl(aid,0,'tmpl_withdraw',$tmplcontent,m_url('admin/finance/credit3withdrawlog'));
			}
			if($set['withdraw_autotransfer'] && ($post['paytype'] = '微信钱包' || $post['paytype'] = '银行卡')) {
				Db::name('member_credit3_withdrawlog')->where('id',$recordid)->update(['status' => 1]);
				$rs = \app\commons\Wxpay::transfers(aid,mid,$record['money'],$record['ordernum'],platform,t('credit3').'收益提现');
				if($rs['status']==0) {
					return json(['status'=>1,'msg'=>'提交成功,请等待打款']);
				} else {
					Db::name('member_credit3_withdrawlog')->where('id',$recordid)->update(['status' => 3]);
					Db::name('member_credit3_withdrawlog')->where('aid',aid)->where('id',$recordid)->update(['status'=>3,'paytime'=>time(),'paynum'=>$rs['resp']['payment_no']]);
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
				Db::name('member_credit3_withdrawlog')->where('id',$recordid)->update(['status' => 1]);
				///	$rs = \app\commons\Wxpay::transfers(aid,mid,$record['money'],$record['ordernum'],platform,t('credit3').'收益提现');
				$rs = \app\commons\Alipay::transfers(aid,$record['ordernum'],$record['money'],t('credit3').'提现',$this->member['aliaccount'],$this->member['aliaccountname'],t('credit3').'提现');
				if($rs['status']==0) {
					$sub_msg = $rs['sub_msg']?$rs['sub_msg']:'';
					if($sub_msg) {
						Db::name('member_credit3_withdrawlog')->where('aid',aid)->where('id',$recordid)->update(['reason'=>$sub_msg]);
					}
					return json(['status'=>1,'msg'=>'提交成功,请等待打款']);
				} else {
					Db::name('member_credit3_withdrawlog')->where('id',$recordid)->update(['status' => 3]);
					Db::name('member_credit3_withdrawlog')->where('aid',aid)->where('id',$recordid)->update(['status'=>3,'paytime'=>time(),'paynum'=>$rs['pay_fund_order_id']]);
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
		$userinfo = Db::name('member')->where('id',mid)->field('id,credit3,aliaccount,tel,bankname,bankcarduser,bankcardnum')->find();
		//订阅消息
		if($this->member['paypwd']=='') {
			$userinfo['haspwd'] = 1;
		} else {
			$userinfo['haspwd'] = 0;
		}
		$wx_tmplset = Db::name('wx_tmplset')->where('aid',aid)->find();
		$tmplids = [];
		if($wx_tmplset['tmpl_tixiansuccess_new']) {
			$tmplids[] = $wx_tmplset['tmpl_tixiansuccess_new'];
		} elseif($wx_tmplset['tmpl_tixiansuccess']) {
			$tmplids[] = $wx_tmplset['tmpl_tixiansuccess'];
		}
		if($wx_tmplset['tmpl_tixianerror_new']) {
			$tmplids[] = $wx_tmplset['tmpl_tixianerror_new'];
		} elseif($wx_tmplset['tmpl_tixianerror']) {
			$tmplids[] = $wx_tmplset['tmpl_tixianerror'];
		}
		$rdata = [];
		$rdata['status'] = 1;
		$rdata['paycheck'] = true;
		$rdata['userinfo'] = $userinfo;
		$rdata['sysset'] = $set;
		$rdata['tmplids'] = $tmplids;
		return $this->json($rdata);
	}
	//转送
	public function credit3Transfer() {
		$set = Db::name('admin_set')->where('aid',aid)->find();
		if($set['credit3_transfer'] != 1) {
			return $this->json(['status'=>0,'msg'=>'未开启此功能']);
		}
		if(request()->isPost()) {
			$mobile = input('post.mobile');
			$mid = input('post.mid/d');
			$credit3 = input('post.credit3/f');
			if ($credit3 < 0.01) {
				return $this->json(['status'=>0,'msg'=>'请输入正确的金额，最小金额为：0.01']);
			}
			if(getcustom('100_transfer')) {
				if ($credit3%100!=0) {
					return $this->json(['status'=>0,'msg'=>'数量必须输入100的倍数']);
				}
			}
			if (input('?post.mobile')) {
				$member = Db::name('member')->where('aid', aid)->where('tel', $mobile)->find();
			}
			if (input('?post.mid')) {
				$member = Db::name('member')->where('aid', aid)->where('id', $mid)->find();
			}
			if(!$member) return $this->json(['status'=>0,'msg'=>'未找到该'.t('会员')]);
			$user_id = $member['id'];
			if ($user_id == mid) {
				return $this->json(['status'=>0,'msg'=>'不能转账给自己']);
			}
			if($set['credit3_transfer_range'] == 1) {
				//所有上下级
				$isparent = false;
				if(in_array($user_id,explode(',',$this->member['path']))) {
					$isparent = true;
				}
				if(!$isparent) {
					if(!in_array(mid,explode(',',$member['path']))) {
						return $this->json(['status'=>0,'msg'=>'仅限转账给上下级'.t('会员')]);
					}
				}
			}
			$credit3_transfer_fee = round($credit3*$set['credit3_transfer_fee']*0.01,2);
			$tocredit3 = round($credit3*(100 + $set['credit3_transfer_fee'])*0.01,2);
			if ($tocredit3 > $this->member['credit3']) {
				return $this->json(['status'=>0,'msg'=>'您的'.t('credit3').'不足']);
			}
			$smscode = input('post.code');
			if( md5($this->member['tel'].'-'.$smscode) != cache($this->sessionid.'_smscode') || cache($this->sessionid.'_smscodetimes')>5) {
				cache($this->sessionid.'_smscodetimes',cache($this->sessionid.'_smscodetimes')+1);
				return $this->json(['status'=>0,'msg'=>'短信验证码错误']);
			}
			cache(input('param.session_id').'_smscode',null);
			cache(input('param.session_id').'_smscodetimes',null);
			//验证支付密码
			$pwd_check = $set['credit3_transfer_pwd'];
			if($pwd_check) {
				if(!$this->member['paypwd']) {
					return $this->json(['status'=>0,'msg'=>'请先设置支付密码','set_paypwd'=>1]);
				}
				$pay_pwd = input('paypwd')?:'';
				if(!\app\commons\Member::checkPayPwd($this->member,$pay_pwd )) {
					return $this->json(['status'=>0,'msg'=>'支付密码输入错误']);
				}
			}
			$rs = \app\commons\Member::addcredit3(aid,mid,$tocredit3 * -1, sprintf("转账给：%s",$member['nickname']));
			if ($rs['status'] == 1) {
				\app\commons\Member::addcredit3(aid,$user_id,$credit3,sprintf("来自%s的转账", $this->member["nickname"]));
				\app\commons\Admin::addcredit(aid,mid,$credit3_transfer_fee,sprintf("转账给：%s",$member['nickname']));
				\app\commons\Admin::addcredit3(aid,mid,$credit3_transfer_fee*-1,sprintf("转账给：%s",$member['nickname']));
			} else {
				return $this->json(['status'=>0, 'msg' => '转账失败']);
			}
			return $this->json(['status'=>1, 'msg' => '转账成功', 'url'=>m_url('my/usercenter').'/aid/'.aid]);
		}
		if($this->member['paypwd']=='') {
			$userinfo['haspwd'] = 1;
		} else {
			$userinfo['haspwd'] = 0;
		}
		$userinfo['tel'] = $this->member['tel'];
		$rdata['userinfo'] = $userinfo;
		$rdata['paycheck'] = false;
		$rdata['paytype'] = $set['credit3_transfer_type'];
		$rdata['credit3_transfer_fee'] = $set['credit3_transfer_fee'];
		$rdata['status'] = 1;
		$rdata['mycredit3'] = $this->member['credit3'];
		return $this->json($rdata);
	}
	public function change_credit3() {
		$set = Db::name('admin_set')->where('aid', aid)->field('credit3_money,credit3_cm,credit3_c2,credit3_mo')->find();
		$moneyList['commission'] = array('credit3_fee'=>$set['credit3_cm'], 'credit3_min' =>0);
		$moneyList['credit2'] = array('credit3_fee'=>$set['credit3_c2'], 'credit3_min' => 0);
		$moneyList['money'] = array('credit3_fee'=>$set['credit3_mo'], 'credit3_min' =>0);
		if(request()->isPost()) {
			$paytype = input('post.paytype');
			$credit3 = input('post.credit3');
			if ($credit3 <= 0) {
				return $this->json(['status'=>0,'msg'=>'请输入正确的'.t('credit3').'数量']);
			}
			if ($credit3 > $this->member['credit3']) {
				return $this->json(['status'=>0,'msg'=>'您的'.t('credit3').'数量不足']);
			}
			// 			if ($credit2%$set['change_credit2']!=0){
			//                 return $this->json(['status'=>0,'msg'=>'金额必须输入'.$set['change_credit2'].'的倍数']);
			//             }
			if(!$this->member['paypwd']) {
				return $this->json(['status'=>0,'msg'=>'请先设置支付密码','set_paypwd'=>1]);
			}
			$pay_pwd = input('paypwd')?:'';
			if(!\app\commons\Member::checkPayPwd($this->member,$pay_pwd )) {
				return $this->json(['status'=>0,'msg'=>'支付密码输入错误']);
			}
			if ($paytype=='commission') {
				$remark =  t('credit3')."兑换".t('佣金');
			} elseif($paytype=='credit2') {
				$remark =  t('credit3')."兑换".t('credit2');
			} elseif($paytype=='money') {
				$remark =  t('credit3')."兑换".t('余额');
			} else {
				return $this->json(['status'=>0,'msg'=>'请选择兑换类型']);
			}
			$credit3_fee = $moneyList[$paytype]['credit3_fee'];
			$change_fee = round($credit3*$credit3_fee*0.01,2);
			$change_money = round(($credit3 - $change_fee)*$set['credit3_money'],2);
			$rs = \app\commons\Member::addcredit3(aid,mid,$credit3 * -1,$remark);
			if ($rs['status'] == 1) {
				$buildfun = 'add'.$paytype;
				if ($paytype =='commission') {
					\app\commons\Member::$buildfun(aid,mid,0,$change_money,$remark);
				} else {
					\app\commons\Member::$buildfun(aid,mid,$change_money,$remark);
				}
				\app\commons\Admin::addcredit(aid,mid,$change_fee,$remark);
				\app\commons\Admin::addcredit3(aid,mid,$credit3* -1,$remark);
			} else {
				return $this->json(['status'=>0, 'msg' => '兑换失败', 'url'=>m_url('my/usercenter').'/aid/'.aid]);
			}
		}
		$rdata['status'] = 1;
		$rdata['set'] = $set;
		$userinfo['credit3'] = $this->member['credit3'];
		if($this->member['paypwd']=='') {
			$userinfo['haspwd'] = 1;
		} else {
			$userinfo['haspwd'] = 0;
		}
		$rdata['userinfo'] =$userinfo;
		$rdata['moneyList'] = $moneyList;
		//可选金额列表
		return $this->json($rdata);
	}
}