<?php


// +----------------------------------------------------------------------
// | 配送员余额管理
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class PeisongMoney extends Common
{
    public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无访问权限');
	}
	//余额明细
    public function moneylog(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = 'peisong_moneylog.'.input('param.field').' '.input('param.order');
			}else{
				$order = 'peisong_moneylog.id desc';
			}
			$where = [];
			$where[] = ['peisong_moneylog.aid','=',aid];
			
			if(input('param.realname')) $where[] = ['peisong_user.realname','like','%'.trim(input('param.realname')).'%'];
			if(input('param.uid')) $where[] = ['peisong_moneylog.uid','=',trim(input('param.uid'))];
			$count = 0 + Db::name('peisong_moneylog')->alias('peisong_moneylog')->field('peisong_user.realname,peisong_user.tel,peisong_moneylog.*')->join('peisong_user peisong_user','peisong_user.id=peisong_moneylog.uid')->where($where)->count();
			$data = Db::name('peisong_moneylog')->alias('peisong_moneylog')->field('peisong_user.realname,peisong_user.tel,peisong_moneylog.*')->join('peisong_user peisong_user','peisong_user.id=peisong_moneylog.uid')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		$psusers = Db::name('peisong_user')->where('aid',aid)->order('sort desc,id')->select()->toArray();
		View::assign('psusers',$psusers);
        $this->defaultSet();
		return View::fetch();
    }
	//余额明细导出
	public function moneylogexcel(){
		if(input('param.field') && input('param.order')){
			$order = 'peisong_moneylog.'.input('param.field').' '.input('param.order');
		}else{
			$order = 'peisong_moneylog.id desc';
		}
        $page = input('param.page');
        $limit = input('param.limit');
		$where = array();
		$where[] = ['peisong_moneylog.aid','=',aid];
		
		if(input('param.realname')) $where[] = ['peisong_user.realname','like','%'.trim(input('param.realname')).'%'];
		if(input('param.uid')) $where[] = ['peisong_moneylog.uid','=',trim(input('param.uid'))];
		$list = Db::name('peisong_moneylog')->alias('peisong_moneylog')->field('peisong_user.realname,peisong_user.tel,peisong_moneylog.*')
            ->join('peisong_user peisong_user','peisong_user.id=peisong_moneylog.uid')->where($where)->order($order)->page($page,$limit)->select()->toArray();
        $count = Db::name('peisong_moneylog')->alias('peisong_moneylog')->field('peisong_user.realname,peisong_user.tel,peisong_moneylog.*')
            ->join('peisong_user peisong_user','peisong_user.id=peisong_moneylog.uid')->where($where)->order($order)->count();
		$title = array();
		$title[] = '配送员';
		$title[] = '变更金额';
		$title[] = '变更后剩余';
		$title[] = '变更时间';
		$title[] = '备注';
		$data = array();
		foreach($list as $v){
			$tdata = array();
			$tdata[] = $v['realname'].' '.$v['tel'];
			$tdata[] = $v['money'];
			$tdata[] = $v['after'];
			$tdata[] = date('Y-m-d H:i:s',$v['createtime']);
			$tdata[] = $v['remark'];
			$data[] = $tdata;
		}
        return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'title'=>$title]);
		$this->export_excel($title,$data);
	}
	//余额明细删除
	public function moneylogdel(){
		$ids = input('post.ids/a');
		Db::name('peisong_moneylog')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('删除余额明细'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}

	//提现记录
	public function withdrawlog(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = 'peisong_withdrawlog.'.input('param.field').' '.input('param.order');
			}else{
				$order = 'peisong_withdrawlog.id desc';
			}
			$where = [];
			$where[] = ['peisong_withdrawlog.aid','=',aid];
			if(input('param.realname')) $where[] = ['peisong_user.realname','like','%'.trim(input('param.realname')).'%'];
			if(input('param.uid')) $where[] = ['peisong_withdrawlog.uid','=',trim(input('param.uid'))];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['peisong_withdrawlog.status','=',input('param.status')];
			$count = 0 + Db::name('peisong_withdrawlog')->alias('peisong_withdrawlog')->field('peisong_user.realname,peisong_user.tel,peisong_withdrawlog.*')->join('peisong_user peisong_user','peisong_user.id=peisong_withdrawlog.uid')->where($where)->count();
			$data = Db::name('peisong_withdrawlog')->alias('peisong_withdrawlog')->field('peisong_user.realname,peisong_user.tel,peisong_withdrawlog.*')->join('peisong_user peisong_user','peisong_user.id=peisong_withdrawlog.uid')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		$psusers = Db::name('peisong_user')->where('aid',aid)->order('sort desc,id')->select()->toArray();
		View::assign('psusers',$psusers);
        $this->defaultSet();
		return View::fetch();
    }
	//提现记录导出
	public function withdrawlogexcel(){
		if(input('param.field') && input('param.order')){
			$order = 'peisong_withdrawlog.'.input('param.field').' '.input('param.order');
		}else{
			$order = 'peisong_withdrawlog.id desc';
		}
        $page = input('param.page');
        $limit = input('param.limit');
		$where = [];
		$where[] = ['peisong_withdrawlog.aid','=',aid];
		if(input('param.nickname')) $where[] = ['peisong_user.nickname','like','%'.trim(input('param.nickname')).'%'];
		if(input('param.uid')) $where[] = ['peisong_withdrawlog.uid','=',trim(input('param.uid'))];
		if(input('?param.status') && input('param.status')!=='') $where[] = ['peisong_withdrawlog.status','=',input('param.status')];
		$list = Db::name('peisong_withdrawlog')->alias('peisong_withdrawlog')->field('peisong_user.realname,peisong_user.tel,peisong_withdrawlog.*')
            ->join('peisong_user peisong_user','peisong_user.id=peisong_withdrawlog.uid')->where($where)->order($order)->page($page,$limit)->select()->toArray();
        $count = Db::name('peisong_withdrawlog')->alias('peisong_withdrawlog')->field('peisong_user.realname,peisong_user.tel,peisong_withdrawlog.*')
            ->join('peisong_user peisong_user','peisong_user.id=peisong_withdrawlog.uid')->where($where)->order($order)->count();
		$title = array();
		$title[] = '配送员';
		$title[] = '提现金额';
		$title[] = '打款金额';
		$title[] = '提现方式';
		$title[] = '收款账号';
		$title[] = '提现时间';
		$title[] = '状态';
		$data = array();
		foreach($list as $v){
			$tdata = array();
			$tdata[] = $v['realname'].' '.$v['tel'];
			$tdata[] = $v['txmoney'];
			$tdata[] = $v['money'];
			$tdata[] = $v['paytype'];
			if($v['paytype'] == '支付宝'){
				$tdata[] = $v['aliaccount'];
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
        return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'title'=>$title]);
		$this->export_excel($title,$data);
	}
	//提现记录改状态
	public function withdrawlogsetst(){
		$id = input('post.id/d');
		$st = input('post.st/d');
		$reason = input('post.reason');
		$info = Db::name('peisong_withdrawlog')->where('aid',aid)->where('id',$id)->find();
		$info['money'] = dd_money_format($info['money']);
        $info['txmoney'] = dd_money_format( $info['txmoney'] );
		$psuser = Db::name('peisong_user')->where('aid',aid)->where('id',$info['uid'])->find();
		if($st==10){//微信打款
			if($info['status']!=1) return json(['status'=>0,'msg'=>'已审核状态才能打款']);
			$rs = \app\commons\Wxpay::transfers(aid,$psuser['mid'],$info['money'],$info['ordernum'],$info['platform'],'余额提现');
			if($rs['status']==0){
				return json(['status'=>0,'msg'=>$rs['msg']]);
			}else{
				Db::name('peisong_withdrawlog')->where('aid',aid)->where('id',$id)->update(['status'=>3,'reason'=>$reason,'paytime'=>time(),'paynum'=>$rs['resp']['payment_no']]);
				//提现成功通知
				$tmplcontent = [];
				$tmplcontent['first'] = '您的提现申请已打款，请留意查收';
				$tmplcontent['remark'] = '请点击查看详情~';
				$tmplcontent['money'] = (string) $info['money'];
				$tmplcontent['timet'] = date('Y-m-d H:i',$info['createtime']);
                $tempconNew = [];
                $tempconNew['amount2'] = (string) round($info['money'],2);//提现金额
                $tempconNew['time3'] = date('Y-m-d H:i',$info['createtime']);//提现时间
				\app\commons\Wechat::sendtmpl(aid,$psuser['mid'],'tmpl_tixiansuccess',$tmplcontent,m_url('pages/my/usercenter'),$tempconNew);
				//订阅消息
				$tmplcontent = [];
				$tmplcontent['amount1'] = $info['money'];
				$tmplcontent['thing3'] = $info['paytype'];
				$tmplcontent['time5'] = date('Y-m-d H:i');
				
				$tmplcontentnew = [];
				$tmplcontentnew['amount3'] = $info['money'];
				$tmplcontentnew['phrase9'] = $info['paytype'];
				$tmplcontentnew['date8'] = date('Y-m-d H:i');
				\app\commons\Wechat::sendwxtmpl(aid,$psuser['mid'],'tmpl_tixiansuccess',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);
				//短信通知
				if($psuser['tel']){
					\app\commons\Sms::send(aid,$psuser['tel'],'tmpl_tixiansuccess',['money'=>$info['money']]);
				}
				\app\commons\System::plog('配送员余额提现微信打款'.$id);
				return json(['status'=>1,'msg'=>$rs['msg']]);
			}
		}else{
			Db::name('peisong_withdrawlog')->where('aid',aid)->where('id',$id)->update(['status'=>$st,'reason'=>$reason]);
			if($st == 2){//驳回返还余额
				\app\commons\PeisongUser::addmoney(aid,$info['uid'],$info['txmoney'],'余额提现返还');
				//提现失败通知
				$tmplcontent = [];
				$tmplcontent['first'] = '您的提现申请被商家驳回，可与商家协商沟通。';
				$tmplcontent['remark'] = $reason.'，请点击查看详情~';
				$tmplcontent['money'] = (string) $info['txmoney'];
				$tmplcontent['time'] = date('Y-m-d H:i',$info['createtime']);
				\app\commons\Wechat::sendtmpl(aid,$psuser['mid'],'tmpl_tixianerror',$tmplcontent,m_url('pages/my/usercenter'));
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
				\app\commons\Wechat::sendwxtmpl(aid,$psuser['mid'],'tmpl_tixianerror',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);
				//短信通知
				if($psuser['tel']){
					\app\commons\Sms::send(aid,$psuser['tel'],'tmpl_tixianerror',['reason'=>$reason]);
				}
				\app\commons\System::plog('配送员余额提现驳回'.$id);
			}
			if($st==3){
				//提现成功通知
				$tmplcontent = [];
				$tmplcontent['first'] = '您的提现申请已打款，请留意查收';
				$tmplcontent['remark'] = '请点击查看详情~';
				$tmplcontent['money'] = (string) $info['money'];
				$tmplcontent['timet'] = date('Y-m-d H:i',$info['createtime']);
                $tempconNew = [];
                $tempconNew['amount2'] = (string) round($info['money'],2);//提现金额
                $tempconNew['time3'] = date('Y-m-d H:i',$info['createtime']);//提现时间
				\app\commons\Wechat::sendtmpl(aid,$psuser['mid'],'tmpl_tixiansuccess',$tmplcontent,m_url('pages/my/usercenter'),$tempconNew);
				//订阅消息
				$tmplcontent = [];
				$tmplcontent['amount1'] = $info['money'];
				$tmplcontent['thing3'] = $info['paytype'];
				$tmplcontent['time5'] = date('Y-m-d H:i');
				
				$tmplcontentnew = [];
				$tmplcontentnew['amount3'] = $info['money'];
				$tmplcontentnew['phrase9'] = $info['paytype'];
				$tmplcontentnew['date8'] = date('Y-m-d H:i');
				\app\commons\Wechat::sendwxtmpl(aid,$psuser['mid'],'tmpl_tixiansuccess',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);
				//短信通知
				$member = Db::name('member')->where('id',$info['mid'])->find();
				if($psuser['tel']){
					\app\commons\Sms::send(aid,$psuser['tel'],'tmpl_tixiansuccess',['money'=>$info['money']]);
				}
				\app\commons\System::plog('配送员余额提现改为已打款'.$id);
			}
		}
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//提现记录删除
	public function withdrawlogdel(){
		$ids = input('post.ids/a');
		Db::name('peisong_withdrawlog')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('配送员余额提现记录删除'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}

    function defaultSet(){
        $set = Db::name('peisong_set')->where('aid',aid)->find();
        if(!$set){
            Db::name('peisong_set')->insert(['aid'=>aid]);
        }
    }
}