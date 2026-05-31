<?php


// +----------------------------------------------------------------------
// | 配送员余额管理
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class YuyueMoney extends Common
{
    public function initialize(){
		parent::initialize();
	}
	//预约服务-提成明细
    public function moneylog(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = 'master_moneylog.'.input('param.field').' '.input('param.order');
			}else{
				$order = 'master_moneylog.id desc';
			}
			$where = [];
			$where[] = ['master_moneylog.aid','=',aid];
			$where[] = ['master_moneylog.bid','=',bid];
			
			if(input('param.realname')) $where[] = ['master_worker.realname','like','%'.trim(input('param.realname')).'%'];
			
			if(input('param.uid')){
				$where[] = ['master_moneylog.uid','=',trim(input('param.uid'))];
			}elseif(!input('?param.uid') && input('param.uid2')){
				$where[] = ['master_moneylog.uid','=',trim(input('param.uid2'))];
			}
			$count = 0 + Db::name('yuyue_worker_moneylog')->alias('master_moneylog')->field('master_worker.realname,master_worker.tel,master_moneylog.*')->join('yuyue_worker master_worker','master_worker.id=master_moneylog.uid')->where($where)->count();
			$data = Db::name('yuyue_worker_moneylog')->alias('master_moneylog')->field('master_worker.realname,master_worker.tel,master_moneylog.*')->join('yuyue_worker master_worker','master_worker.id=master_moneylog.uid')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		$psusers = Db::name('yuyue_worker')->where('aid',aid)->order('sort desc,id')->select()->toArray();
		View::assign('psusers',$psusers);
		return View::fetch();
    }
	//预约服务-提成明细导出
	public function moneylogexcel(){
		if(input('param.field') && input('param.order')){
			$order = 'master_moneylog.'.input('param.field').' '.input('param.order');
		}else{
			$order = 'master_moneylog.id desc';
		}
        $page = input('param.page');
        $limit = input('param.limit');
		$where = array();
		$where[] = ['master_moneylog.aid','=',aid];
		$where[] = ['master_moneylog.bid','=',bid];
		
		if(input('param.realname')) $where[] = ['peisong_user.realname','like','%'.trim(input('param.realname')).'%'];
		if(input('param.uid')) $where[] = ['master_moneylog.uid','=',trim(input('param.uid'))];
		$list = Db::name('yuyue_worker_moneylog')->alias('master_moneylog')
            ->join('yuyue_worker peisong_user','peisong_user.id=master_moneylog.uid')
            ->field('peisong_user.realname,peisong_user.tel,master_moneylog.*')
            ->where($where)->order($order)->page($page,$limit)->select()->toArray();
        $count = Db::name('yuyue_worker_moneylog')->alias('master_moneylog')
            ->join('yuyue_worker peisong_user','peisong_user.id=master_moneylog.uid')
            ->field('peisong_user.realname,peisong_user.tel,master_moneylog.*')
            ->where($where)->order($order)->count();
		$title = array();
		$title[] = '服务人员';
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
	//预约服务-提成明细删除
	public function moneylogdel(){
		$ids = input('post.ids/a');
		Db::name('yuyue_worker_moneylog')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('删除预约服务-提成明细'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}

	//提现记录
	public function withdrawlog(){
        $this->defaultSet();
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = 'master_withdrawlog.'.input('param.field').' '.input('param.order');
			}else{
				$order = 'master_withdrawlog.id desc';
			}
			$where = [];
			$where[] = ['master_withdrawlog.aid','=',aid];
			$where[] = ['master_withdrawlog.bid','=',bid];
			if(input('param.realname')) $where[] = ['master_worker.realname','like','%'.trim(input('param.realname')).'%'];
			if(input('param.uid')) $where[] = ['master_withdrawlog.uid','=',trim(input('param.uid'))];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['master_withdrawlog.status','=',input('param.status')];
			$count = 0 + Db::name('yuyue_worker_withdrawlog')->alias('master_withdrawlog')->field('master_worker.realname,master_worker.tel,master_withdrawlog.*')->join('yuyue_worker master_worker','master_worker.id=master_withdrawlog.uid')->where($where)->count();
			$data = Db::name('yuyue_worker_withdrawlog')->alias('master_withdrawlog')
                ->join('yuyue_worker master_worker','master_worker.id=master_withdrawlog.uid')
                ->field('master_worker.realname,master_worker.tel,master_withdrawlog.*')
                ->where($where)->page($page,$limit)->order($order)->select()->toArray();
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		$psusers = Db::name('yuyue_worker')->where('aid',aid)->order('sort desc,id')->select()->toArray();
		View::assign('psusers',$psusers);
		return View::fetch();
    }
	//提现记录导出
	public function withdrawlogexcel(){
		if(input('param.field') && input('param.order')){
			$order = 'master_withdrawlog.'.input('param.field').' '.input('param.order');
		}else{
			$order = 'master_withdrawlog.id desc';
		}
        $page = input('param.page');
        $limit = input('param.limit');
		$where = [];
		$where[] = ['master_withdrawlog.aid','=',aid];
		$where[] = ['master_withdrawlog.bid','=',bid];
		if(input('param.nickname')) $where[] = ['peisong_user.nickname','like','%'.trim(input('param.nickname')).'%'];
		if(input('param.uid')) $where[] = ['master_withdrawlog.uid','=',trim(input('param.uid'))];
		if(input('?param.status') && input('param.status')!=='') $where[] = ['master_withdrawlog.status','=',input('param.status')];
		$list = Db::name('yuyue_worker_withdrawlog')->alias('master_withdrawlog')
            ->join('yuyue_worker peisong_user','peisong_user.id=master_withdrawlog.uid')
            ->field('peisong_user.realname,peisong_user.tel,master_withdrawlog.*')
            ->where($where)->order($order)->page($page,$limit)->select()->toArray();
        $count = Db::name('yuyue_worker_withdrawlog')->alias('master_withdrawlog')
            ->join('yuyue_worker peisong_user','peisong_user.id=master_withdrawlog.uid')
            ->field('peisong_user.realname,peisong_user.tel,master_withdrawlog.*')
            ->where($where)->order($order)->count();
		$title = array();
		$title[] = '服务人员';
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
		$info = Db::name('yuyue_worker_withdrawlog')->where('aid',aid)->where('bid',bid)->where('id',$id)->find();
        $info['money'] = dd_money_format($info['money']);
        $info['txmoney'] = dd_money_format($info['txmoney']);
		$psuser = Db::name('yuyue_worker')->where('aid',aid)->where('bid',bid)->where('id',$info['uid'])->find();
		if($st==10){ //微信打款
			if($info['status']!=1) return json(['status'=>0,'msg'=>'已审核状态才能打款']);
			//扣商家的钱 
			if($info['bid'] > 0){
				$business = Db::name('business')->where('aid',aid)->where('id',$info['bid'])->find();
				if($business['money'] < $info['money']) return json(['status'=>0,'msg'=>'商家余额不足']);
			}
			$rs = \app\commons\Wxpay::transfers(aid,$psuser['mid'],$info['money'],$info['ordernum'],$info['platform'],'余额提现');
			if($rs['status']==0){
				return json(['status'=>0,'msg'=>$rs['msg']]);
			}else{
				\app\commons\Business::addmoney(aid,$info['bid'],-$info['money'],'给服务人员打款');
				Db::name('yuyue_worker_withdrawlog')->where('aid',aid)->where('id',$id)->update(['status'=>3,'reason'=>$reason,'paytime'=>time(),'paynum'=>$rs['resp']['payment_no']]);
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
				\app\commons\System::plog('预约服务-人员余额提现微信打款'.$id);
				return json(['status'=>1,'msg'=>$rs['msg']]);
			}
		}else{
			Db::name('yuyue_worker_withdrawlog')->where('aid',aid)->where('bid',bid)->where('id',$id)->update(['status'=>$st,'reason'=>$reason]);
			if($st == 2){//驳回返还余额
				\app\commons\YuyueWorker::addmoney(aid,bid,$info['uid'],$info['txmoney'],'提现驳回返还');
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
				\app\commons\System::plog('预约服务-人员余额提现驳回'.$id);
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
				\app\commons\System::plog('预约服务-人员余额提现改为已打款'.$id);
			}
		}
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//提现记录删除
	public function withdrawlogdel(){
		$ids = input('post.ids/a');
		Db::name('yuyue_worker_withdrawlog')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('预约服务-人员余额提现记录删除'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
    function defaultSet(){
        $set = Db::name('yuyue_set')->where('aid',aid)->where('bid',bid)->find();
        if(!$set){
            Db::name('yuyue_set')->insert(['aid'=>aid,'bid' => bid]);
        }
    }
}