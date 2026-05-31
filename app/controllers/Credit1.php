<?php

namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class Credit1 extends Common
{
    public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无访问权限');
	}
	//充值赠送
	public function giveset(){
		if(request()->isAjax()){
			$info = input('post.info/a');
			$givedata = array();
			$postmoney = input('post.money/a');
			foreach($postmoney as $k=>$money){
				$givedata[] = array(
					'money'=>$money,
				);
			}
			$info['givedata'] = json_encode($givedata,JSON_UNESCAPED_UNICODE);
			$info['caninput'] = $info['caninput'];
			if(Db::name('recharge_credit1_giveset')->where('aid',aid)->find()){
				Db::name('recharge_credit1_giveset')->where('aid',aid)->update($info);
			}else{
				$info['aid'] = aid;
				$info['createtime'] = time();
				Db::name('recharge_credit1_giveset')->insert($info);
			}
			return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('giveset')]);
		}
		$info = Db::name('recharge_credit1_giveset')->where('aid',aid)->find();
		if(!$info) $info = ['caninput'=>1];
		View::assign('info',$info);
		return View::fetch();
	}
		//充值记录
	public function rechargelog(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = 'recharge_credit1_order.'.input('param.field').' '.input('param.order');
			}else{
				$order = 'recharge_credit1_order.id desc';
			}
			$where = [];
			$where[] = ['recharge_credit1_order.aid','=',aid];
			$where[] = ['recharge_credit1_order.status','=',1];
			
			if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
			if(input('param.mid')) $where[] = ['recharge_credit1_order.mid','=',trim(input('param.mid'))];
			$count = 0 + Db::name('recharge_credit1_order')->alias('recharge_credit1_order')->field('member.nickname,member.headimg,recharge_credit1_order.*')->join('member member','member.id=recharge_credit1_order.mid')->where($where)->count();
			$data = Db::name('recharge_credit1_order')->alias('recharge_credit1_order')->field('member.nickname,member.headimg,recharge_credit1_order.*')->join('member member','member.id=recharge_credit1_order.mid')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
    }
	//充值记录导出
	public function rechargelogexcel(){
		if(input('param.field') && input('param.order')){
			$order = 'recharge_credit1_order.'.input('param.field').' '.input('param.order');
		}else{
			$order = 'recharge_credit1_order.id desc';
		}
		$where = [];
		$where[] = ['recharge_credit1_order.aid','=',aid];
		$where[] = ['recharge_credit1_order.status','=',1];
		if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
		if(input('param.mid')) $where[] = ['recharge_credit1_order.mid','=',trim(input('param.mid'))];
		$list = Db::name('recharge_credit1_order')->alias('recharge_credit1_order')->field('member.nickname,member.headimg,recharge_credit1_order.*')->join('member member','member.id=recharge_credit1_order.mid')->where($where)->order($order)->select()->toArray();
		$title = array();
		$title[] = t('会员').'信息';
		$title[] = '充值金额';
		$title[] = '充值时间';
		$title[] = '付款单号';
		$title[] = '付款时间';
		$title[] = '状态';
		$data = array();
		foreach($list as $v){
			$tdata = array();
			$tdata[] = $v['nickname'].'('.t('会员').'ID:'.$v['mid'].')';
			$tdata[] = $v['money'];
			$tdata[] = date('Y-m-d H:i:s',$v['createtime']);
			$tdata[] = $v['paynum'];
			$tdata[] = date('Y-m-d H:i:s',$v['paytime']);
			$tdata[] = ($v['status']==1 ? '充值成功' : '充值失败');
			$data[] = $tdata;
		}
		$this->export_excel($title,$data);
	}
	//充值记录删除
	public function rechargelogdel(){
		$ids = input('post.ids/a');
		Db::name('recharge_credit1_order')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('删除'.t('会员').'充值记录'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}

	//积分明细
    public function credit1log(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = 'member_credit1log.'.input('param.field').' '.input('param.order');
			}else{
				$order = 'member_credit1log.id desc';
			}
			$where = array();
			$where[] = ['member_credit1log.aid','=',aid];
			
			if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
			if(input('param.mid')) $where[] = ['member_credit1log.mid','=',trim(input('param.mid'))];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['member_credit1log.status','=',input('param.status')];
			$count = 0 + Db::name('member_credit1log')->alias('member_credit1log')->field('member.nickname,member.headimg,member_credit1log.*')->join('member member','member.id=member_credit1log.mid')->where($where)->count();
			$data = Db::name('member_credit1log')->alias('member_credit1log')->field('member.nickname,member.headimg,member_credit1log.*')->join('member member','member.id=member_credit1log.mid')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
    }
	//积分明细导出
	public function credit1logexcel(){
		if(input('param.field') && input('param.order')){
			$order = 'member_credit1log.'.input('param.field').' '.input('param.order');
		}else{
			$order = 'member_credit1log.id desc';
		}
		$where = array();
		$where[] = ['member_credit1log.aid','=',aid];
		if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
		if(input('param.mid')) $where[] = ['member_credit1log.mid','=',trim(input('param.mid'))];
		if(input('?param.status') && input('param.status')!=='') $where[] = ['member_credit1log.status','=',input('param.status')];
		$list = Db::name('member_credit1log')->alias('member_credit1log')->field('member.nickname,member.headimg,member_credit1log.*')->join('member member','member.id=member_credit1log.mid')->where($where)->order($order)->select()->toArray();
		$title = array();
		$title[] = t('会员').'信息';
		$title[] = '变更'.t('credit1');
		$title[] = '变更后剩余';
		$title[] = '变更时间';
		$title[] = '备注';
		$data = array();
		foreach($list as $v){
			$tdata = array();
			$tdata[] = $v['nickname'].'('.t('会员').'ID:'.$v['mid'].')';
			$tdata[] = $v['credit1'];
			$tdata[] = $v['after'];
			$tdata[] = date('Y-m-d H:i:s',$v['createtime']);
			$tdata[] = $v['remark'];
			$data[] = $tdata;
		}
		$this->export_excel($title,$data);
	}
	public function credit1logdel(){
	    $islock= Db::name('admin_set')->where('aid',aid)->value('islock');
		if ($islock==1) 	return json(['status'=>0,'msg'=>'数据锁定中，禁用删除功能']);
		$ids = input('post.ids/a');
		Db::name('member_credit1log')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog(t('credit1').'明细删除'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
		
	 //提现记录
    public function withdrawlog(){
        if(request()->isAjax()){
            $page = input('param.page');
            $limit = input('param.limit');
            if(input('param.field') && input('param.order')){
                $order = 'member_credit1_withdrawlog.'.input('param.field').' '.input('param.order');
            }else{
                $order = 'member_credit1_withdrawlog.id desc';
            }
            $where = [];
            $where[] = ['member_credit1_withdrawlog.aid','=',aid];
            if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
            if(input('param.mid')) $where[] = ['member_credit1_withdrawlog.mid','=',trim(input('param.mid'))];
            if(input('?param.status') && input('param.status')!=='') $where[] = ['member_credit1_withdrawlog.status','=',input('param.status')];
            $count = 0 + Db::name('member_credit1_withdrawlog')->alias('member_credit1_withdrawlog')->field('member.nickname,member.headimg,member_credit1_withdrawlog.*')->join('member member','member.id=member_credit1_withdrawlog.mid')->where($where)->count();
            $data = Db::name('member_credit1_withdrawlog')->alias('member_credit1_withdrawlog')->field('member.nickname,member.headimg,member_credit1_withdrawlog.*')->join('member member','member.id=member_credit1_withdrawlog.mid')->where($where)->page($page,$limit)->order($order)->select()->toArray();
            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
        }
        return View::fetch();
    }
    //提现记录导出
    public function withdrawlogexcel(){
        if(input('param.field') && input('param.order')){
            $order = 'member_credit1_withdrawlog.'.input('param.field').' '.input('param.order');
        }else{
            $order = 'member_credit1_withdrawlog.id desc';
        }
        $where = [];
        $where[] = ['member_credit1_withdrawlog.aid','=',aid];
        if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
        if(input('param.mid')) $where[] = ['member_credit1_withdrawlog.mid','=',trim(input('param.mid'))];
        if(input('?param.status') && input('param.status')!=='') $where[] = ['member_credit1_withdrawlog.status','=',input('param.status')];
        $list = Db::name('member_credit1_withdrawlog')->alias('member_credit1_withdrawlog')->field('member.nickname,member.headimg,member_credit1_withdrawlog.*')->join('member member','member.id=member_credit1_withdrawlog.mid')->where($where)->order($order)->select()->toArray();
        $title = array();
        $title[] = t('会员').'信息';
        $title[] = '提现金额';
        $title[] = '打款金额';
        $title[] = '提现方式';
        $title[] = '收款账号';
        $title[] = '提现时间';
        $title[] = '状态';
        $data = array();
        foreach($list as $v){
            $tdata = array();
            $tdata[] = $v['nickname'].'('.t('会员').'ID:'.$v['mid'].')';
            $tdata[] = $v['txmoney'];
            $tdata[] = $v['money'];
            $tdata[] = $v['paytype'];
            if($v['paytype'] == '支付宝'){
                $tdata[] = $v['aliaccountname'].' '.$v['aliaccount'];
            }elseif($v['paytype'] == '银行卡'){
                $tdata[] = $v['bankname'] . ' - ' .$v['bankcarduser']. ' - '.$v['bankcardnum'];
            }else{
                $tdata[] = '';
            }
            $tdata[] = date('Y-m-d H:i:s',$v['createtime']);
            $st = '';
            if($v['status']==0){
                $st = '审核中';
            }elseif($v['status']==1){
                $st = '已审核';
            }elseif($v['status']==2){
                $st = '已驳回';
            }elseif($v['status']==3){
                $st = '已打款';
            }
            $tdata[] = $st;
            $data[] = $tdata;
        }
        $this->export_excel($title,$data);
    }
    //提现记录改状态
    public function withdrawlogsetst(){
        $id = input('post.id/d');
        $st = input('post.st/d');
        $reason = input('post.reason');
        $info = Db::name('member_credit1_withdrawlog')->where('aid',aid)->where('id',$id)->find();
        if($st==10){//微信打款
            if($info['status']!=1) return json(['status'=>0,'msg'=>'已审核状态才能打款']);
            $rs = \app\commons\Wxpay::transfers(aid,$info['mid'],$info['money'],$info['ordernum'],$info['platform'],t('credit1').'提现');
            if($rs['status']==0){
                return json(['status'=>0,'msg'=>$rs['msg']]);
            }else{
                Db::name('member_credit1_withdrawlog')->where('aid',aid)->where('id',$id)->update(['status'=>3,'reason'=>$reason,'paytime'=>time(),'paynum'=>$rs['resp']['payment_no']]);
                //提现成功通知
                
                
                \app\commons\Member::addmoney(aid,mid,$info['tomoney'],t('credit1').'提现');
                $tmplcontent = [];
                $tmplcontent['first'] = '您的提现申请已打款，请留意查收';
                $tmplcontent['remark'] = '请点击查看详情~';
                $tmplcontent['money'] = (string) round($info['money'],2);
                $tmplcontent['timet'] = date('Y-m-d H:i',$info['createtime']);
                \app\commons\Wechat::sendtmpl(aid,$info['mid'],'tmpl_tixiansuccess',$tmplcontent,m_url('pages/my/usercenter'));
                //订阅消息
                $tmplcontent = [];
                $tmplcontent['amount1'] = $info['money'];
                $tmplcontent['thing3'] = $info['paytype'];
                $tmplcontent['time5'] = date('Y-m-d H:i');
				
				$tmplcontentnew = [];
				$tmplcontentnew['amount3'] = $info['money'];
				$tmplcontentnew['phrase9'] = $info['paytype'];
				$tmplcontentnew['date8'] = date('Y-m-d H:i');
                \app\commons\Wechat::sendwxtmpl(aid,$info['mid'],'tmpl_tixiansuccess',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);
                //短信通知
                $member = Db::name('member')->where('id',$info['mid'])->find();
                if($member['tel']){
                    $tel = $member['tel'];
                    \app\commons\Sms::send(aid,$tel,'tmpl_tixiansuccess',['money'=>$info['money']]);
                }
                \app\commons\System::plog(t('credit1').'提现微信打款'.$id);
                return json(['status'=>1,'msg'=>$rs['msg']]);
            }
        }else{
            Db::name('member_credit1_withdrawlog')->where('aid',aid)->where('id',$id)->update(['status'=>$st,'reason'=>$reason]);
            if($st == 2){//驳回返还余额
                \app\commons\Member::addcredit1(aid,$info['mid'],$info['txmoney'],t('credit1').'提现返还');
                //提现失败通知
                $tmplcontent = [];
                $tmplcontent['first'] = '您的提现申请被商家驳回，可与商家协商沟通。';
                $tmplcontent['remark'] = $reason.'，请点击查看详情~';
                $tmplcontent['money'] = (string) round($info['txmoney'],2);
                $tmplcontent['time'] = date('Y-m-d H:i',$info['createtime']);
                \app\commons\Wechat::sendtmpl(aid,$info['mid'],'tmpl_tixianerror',$tmplcontent,m_url('pages/my/usercenter'));
                //订阅消息
                $tmplcontent = [];
                $tmplcontent['amount1'] = $info['txmoney'];
                $tmplcontent['time3'] = date('Y-m-d H:i',$info['createtime']);
                $tmplcontent['thing4'] = $reason;
				
				$tmplcontentnew = [];
				$tmplcontentnew['thing1'] = '提现失败';
				$tmplcontentnew['amount2'] = $info['txmoney'];
				$tmplcontentnew['date4'] = date('Y-m-d H:i',$info['createtime']);
				$tmplcontentnew['thing12'] = $reason;
                \app\commons\Wechat::sendwxtmpl(aid,$info['mid'],'tmpl_tixianerror',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);
                //短信通知
                $member = Db::name('member')->where('id',$info['mid'])->find();
                if($member['tel']){
                    $tel = $member['tel'];
                    \app\commons\Sms::send(aid,$tel,'tmpl_tixianerror',['reason'=>$reason]);
                }
                \app\commons\System::plog(t('credit1').'提现驳回'.$id);
            }
            if($st==3){
                //提现成功通知
                $tmplcontent = [];
                $tmplcontent['first'] = '您的提现申请已打款，请留意查收';
                $tmplcontent['remark'] = '请点击查看详情~';
                $tmplcontent['money'] = (string) round($info['money'],2);
                $tmplcontent['timet'] = date('Y-m-d H:i',$info['createtime']);
                \app\commons\Wechat::sendtmpl(aid,$info['mid'],'tmpl_tixiansuccess',$tmplcontent,m_url('pages/my/usercenter'));
                //订阅消息
                $tmplcontent = [];
                $tmplcontent['amount1'] = $info['money'];
                $tmplcontent['thing3'] = $info['paytype'];
                $tmplcontent['time5'] = date('Y-m-d H:i');
				
				$tmplcontentnew = [];
				$tmplcontentnew['amount3'] = $info['money'];
				$tmplcontentnew['phrase9'] = $info['paytype'];
				$tmplcontentnew['date8'] = date('Y-m-d H:i');
                \app\commons\Wechat::sendwxtmpl(aid,$info['mid'],'tmpl_tixiansuccess',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);
                //短信通知
                $member = Db::name('member')->where('id',$info['mid'])->find();
                if($member['tel']){
                    $tel = $member['tel'];
                    \app\commons\Sms::send(aid,$tel,'tmpl_tixiansuccess',['money'=>$info['money']]);
                }
                \app\commons\System::plog(t('credit1').'提现改为已打款'.$id);
            }
        }
        return json(['status'=>1,'msg'=>'操作成功']);
    }
    //提现记录删除
    public function withdrawlogdel(){
    	$islock= Db::name('admin_set')->where('aid',aid)->value('islock');
	    if ($islock==1) 	return json(['status'=>0,'msg'=>'数据锁定中，禁用删除功能']);
        $ids = input('post.ids/a');
        Db::name('member_credit1_withdrawlog')->where('aid',aid)->where('id','in',$ids)->delete();
        \app\commons\System::plog(t('credit1').'提现记录删除'.implode(',',$ids));
        return json(['status'=>1,'msg'=>'删除成功']);
    }
}
