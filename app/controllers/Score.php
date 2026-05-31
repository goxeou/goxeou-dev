<?php


// +----------------------------------------------------------------------
// | 积分管理
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class Score extends Common
{
    public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无访问权限');
	}
		//提现记录
	public function withdrawlog(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = 'member_score_withdrawlog.'.input('param.field').' '.input('param.order');
			}else{
				$order = 'member_score_withdrawlog.id desc';
			}
			$where = [];
			$where[] = ['member_score_withdrawlog.aid','=',aid];
			if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
			if(input('param.mid')) $where[] = ['member_score_withdrawlog.mid','=',trim(input('param.mid'))];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['member_score_withdrawlog.status','=',input('param.status')];
			$count = 0 + Db::name('member_score_withdrawlog')->alias('member_score_withdrawlog')->field('member.nickname,member.headimg,member_score_withdrawlog.*')->join('member member','member.id=member_score_withdrawlog.mid')->where($where)->count();
			$data = Db::name('member_score_withdrawlog')->alias('member_score_withdrawlog')->field('member.nickname,member.headimg,member_score_withdrawlog.*')->join('member member','member.id=member_score_withdrawlog.mid')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
    }
	//提现记录导出
	public function withdrawlogexcel(){
		if(input('param.field') && input('param.order')){
			$order = 'member_score_withdrawlog.'.input('param.field').' '.input('param.order');
		}else{
			$order = 'member_score_withdrawlog.id desc';
		}
		$where = [];
		$where[] = ['member_score_withdrawlog.aid','=',aid];
		if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
		if(input('param.mid')) $where[] = ['member_score_withdrawlog.mid','=',trim(input('param.mid'))];
		if(input('?param.status') && input('param.status')!=='') $where[] = ['member_score_withdrawlog.status','=',input('param.status')];
		$list = Db::name('member_score_withdrawlog')->alias('member_score_withdrawlog')->field('member.nickname,member.headimg,member_score_withdrawlog.*')->join('member member','member.id=member_score_withdrawlog.mid')->where($where)->order($order)->select()->toArray();
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
		$info = Db::name('member_score_withdrawlog')->where('aid',aid)->where('id',$id)->find();
		if($st==10){//微信打款
			if($info['status']!=1) return json(['status'=>0,'msg'=>'已审核状态才能打款']);
			$rs = \app\commons\Wxpay::transfers(aid,$info['mid'],$info['money'],$info['ordernum'],$info['platform'],t('余额').'提现');
			if($rs['status']==0){
				return json(['status'=>0,'msg'=>$rs['msg']]);
			}else{
				Db::name('member_score_withdrawlog')->where('aid',aid)->where('id',$id)->update(['status'=>3,'reason'=>$reason,'paytime'=>time(),'paynum'=>$rs['resp']['payment_no']]);
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
				\app\commons\System::plog('余额提现微信打款'.$id);
				return json(['status'=>1,'msg'=>$rs['msg']]);
			}
		}else{
			Db::name('member_score_withdrawlog')->where('aid',aid)->where('id',$id)->update(['status'=>$st,'reason'=>$reason]);
			if($st == 2){//驳回返还余额
				\app\commons\Member::addscore(aid,$info['mid'],$info['txmoney'],t('积分').'提现返还');
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
				\app\commons\System::plog('余额提现驳回'.$id);
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
				\app\commons\System::plog('余额提现改为已打款'.$id);
			}
		}
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//提现记录删除
	public function withdrawlogdel(){
		$ids = input('post.ids/a');
		Db::name('member_score_withdrawlog')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('余额提现记录删除'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	//积分明细
    public function scorelog(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = 'member_scorelog.'.input('param.field').' '.input('param.order');
			}else{
				$order = 'member_scorelog.id desc';
			}
			$where = array();
			$where[] = ['member_scorelog.aid','=',aid];
            if(input('param.ctime') ){
                $ctime = explode(' ~ ',input('param.ctime'));
                $where[] = ['member_scorelog.createtime','>=',strtotime($ctime[0])];
                $where[] = ['member_scorelog.createtime','<',strtotime($ctime[1]) + 86400];
            }
			
			if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
			if(input('param.mid')) $where[] = ['member_scorelog.mid','=',trim(input('param.mid'))];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['member_scorelog.status','=',input('param.status')];
			if(input('param.ctime') ){
                $ctime = explode(' ~ ',input('param.ctime'));
                $where[] = ['member_scorelog.createtime','>=',strtotime($ctime[0])];
                $where[] = ['member_scorelog.createtime','<',strtotime($ctime[1]) + 86400];
            }

			$count = 0 + Db::name('member_scorelog')->alias('member_scorelog')->field('member.nickname,member.headimg,member_scorelog.*')->join('member member','member.id=member_scorelog.mid')->where($where)->count();
			$data = Db::name('member_scorelog')->alias('member_scorelog')->field('member.nickname,member.headimg,member_scorelog.*')->join('member member','member.id=member_scorelog.mid')->where($where)->page($page,$limit)->order($order)->select()->toArray();
            $score_weishu = 0;
            foreach ($data as $k => $v){
                $data[$k]['score'] = dd_money_format($v['score'],$score_weishu);
                $data[$k]['used'] = dd_money_format($v['used'],$score_weishu);
                $data[$k]['after'] = dd_money_format($v['after'],$score_weishu);
            }
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
    }
	//积分明细导出
	public function scorelogexcel(){
		if(input('param.field') && input('param.order')){
			$order = 'member_scorelog.'.input('param.field').' '.input('param.order');
		}else{
			$order = 'member_scorelog.id desc';
		}
        $page = input('param.page')?:1;
        $limit = input('param.limit')?:10;
		$where = array();
		$where[] = ['member_scorelog.aid','=',aid];
		if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
		if(input('param.mid')) $where[] = ['member_scorelog.mid','=',trim(input('param.mid'))];
		if(input('?param.status') && input('param.status')!=='') $where[] = ['member_scorelog.status','=',input('param.status')];

        if(input('param.ctime') ){
            $ctime = explode(' ~ ',input('param.ctime'));
            $where[] = ['member_scorelog.createtime','>=',strtotime($ctime[0])];
            $where[] = ['member_scorelog.createtime','<',strtotime($ctime[1]) + 86400];
        }

		$list = Db::name('member_scorelog')->alias('member_scorelog')->field('member.nickname,member.headimg,member_scorelog.*')
            ->join('member member','member.id=member_scorelog.mid')->where($where)->order($order)->page($page,$limit)->select()->toArray();
        $count = Db::name('member_scorelog')->alias('member_scorelog')->field('member.nickname,member.headimg,member_scorelog.*')
            ->join('member member','member.id=member_scorelog.mid')->where($where)->count();
		$title = array();
		$title[] = t('会员').'信息';
		$title[] = '变更'.t('积分');
		$title[] = '变更后剩余';
		$title[] = '变更时间';
		$title[] = '备注';
		$data = array();
		foreach($list as $v){
			$tdata = array();
			$tdata[] = $v['nickname'].'('.t('会员').'ID:'.$v['mid'].')';
			$tdata[] = $v['score'];
			$tdata[] = $v['after'];
			$tdata[] = date('Y-m-d H:i:s',$v['createtime']);
			$tdata[] = $v['remark'];
            $data[] = $tdata;
		}
        return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'title'=>$title]);
		$this->export_excel($title,$data);
	}
	public function scorelogdel(){
		$ids = input('post.ids/a');
		Db::name('member_scorelog')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog(t('积分').'明细删除'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}

    public function cancel()
    {
        $ids = input('post.ids/a');
        $list = Db::name('member_scorelog')->where('aid',aid)->where('id','in',$ids)->select()->toArray();
        foreach ($list as $item){
            if($item['status'] != -1 && $item['is_cancel'] == 0){
                //过期和已撤销的无需处理
                Db::name('member_scorelog')->where('aid',aid)->where('id',$item['id'])->update(['is_cancel'=>1]);
                \app\commons\Member::addscore(aid,$item['mid'],$item['score']*-1,'撤销操作');
            }

        }
        \app\commons\System::plog(t('积分').'明细撤销'.implode(',',$ids));
        return json(['status'=>1,'msg'=>'操作成功']);
    }
}
