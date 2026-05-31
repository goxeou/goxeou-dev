<?php


// +----------------------------------------------------------------------
// | 余额管理
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class Money extends Common
{	
	public $money_weishu = 2;
    public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无访问权限');
	}
	 //付款审核
    public function payCheck(){
        $orderid = input('post.orderid/d');
        $st = input('post.st/d');
        $remark = input('post.remark');
        $order = Db::name('recharge_order')->where('id',$orderid)->where('aid',aid)->find();
        if($st==2){
            Db::name('recharge_order')->where('id',$orderid)->where('aid',aid)->update(['check_status'=>2,'check_remark'=>$remark]);
            \app\commons\System::plog('充值订单付款审核驳回'.$orderid);
            return json(['status'=>1,'msg'=>'付款已驳回']);
        }elseif($st == 1){
            if($order['status']!=0){
                return json(['status'=>0,'msg'=>'该订单状态不允许审核付款']);
            }
           Db::name('recharge_order')->where('id',$orderid)->where('aid',aid)->update(['status'=>1,'check_status'=>1,'paytime' => time()]);
            \app\models\Payorder::recharge_pay($order['id']);
            \app\commons\System::plog('充值订单付款审核通过'.$orderid);
            return json(['status'=>1,'msg'=>'审核通过']);
        }
    }
    		//订单详情
	public function getdetail(){
		$orderid = input('param.orderid');
		$order = Db::name('recharge_order')->where('aid',aid)->where('id',$orderid)->find();
		$member = Db::name('member')->field('id,nickname,headimg,realname,tel,wxopenid,unionid')->where('id',$order['mid'])->find();
		if(!$member) $member = ['id'=>$order['mid'],'nickname'=>'','headimg'=>''];
	

        $payorder = [];
        if($order['paytypeid'] == 5) {
            if($order['check_status'] == 0) {
                $payorder['check_status_label'] = '待审核';
            }elseif($order['check_status'] == 1) {
                $payorder['check_status_label'] = '通过';
            }elseif($order['check_status'] == 2) {
                $payorder['check_status_label'] = '驳回';
            }else{
                $payorder['check_status_label'] = '未上传';
            }
            if($order['paypics']) {
                $payorder['paypics'] = explode(',', $order['paypics']);
                foreach ($payorder['paypics'] as $item) {
                    $payorder['paypics_html'] .= '<img src="'.$item.'" width="auto" height="600" style="object-fit:cover;"   onclick="preview(this)"/>';
                }
            }
       
        }
		return json(['order'=>$order,'member'=>$member,'payorder' => $payorder]);
	}
	
		//上传签收单
	public function qs_pic(){ 
		$id = input('post.id/d');
		$order= Db::name('member_withdrawlog')->where('aid',aid)->where('id',$id)->find();
		if($order){
		    $update = [];
    		$update['qs_pic'] = input('post.qs_pic');
			Db::name('member_withdrawlog')->where('aid',aid)->where('id',$id)->update($update);
		}else {
    	    return json(['status'=>0,'msg'=>'操作失败']);
		}
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	
	
	
	//余额明细
    public function moneylog(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = 'member_moneylog.'.input('param.field').' '.input('param.order');
			}else{
				$order = 'member_moneylog.id desc';
			}
			$where = [];
			$where[] = ['member_moneylog.aid','=',aid];
			
			if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
			if(input('param.mid')) $where[] = ['member_moneylog.mid','=',trim(input('param.mid'))];
            if(input('param.tel')) $where[] = ['member.tel','like','%'.trim(input('param.tel')).'%'];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['member_moneylog.status','=',input('param.status')];
            if(input('param.ctime') ){
                $ctime = explode(' ~ ',input('param.ctime'));
                $where[] = ['member_moneylog.createtime','>=',strtotime($ctime[0])];
                $where[] = ['member_moneylog.createtime','<',strtotime($ctime[1])];
            }
            $count = 0 + Db::name('member_moneylog')->alias('member_moneylog')->field('member.nickname,member.headimg,member_moneylog.*')->join('member member','member.id=member_moneylog.mid')->where($where)->count();
			$data = Db::name('member_moneylog')->alias('member_moneylog')->field('member.nickname,member.headimg,member_moneylog.*')->join('member member','member.id=member_moneylog.mid')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			if($data){
				foreach($data as &$v){
					$v['money'] = dd_money_format($v['money'],$this->money_weishu);
					$v['after'] = dd_money_format($v['after'],$this->money_weishu);
				}
				unset($v);
			}
            $tongji = [];
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'tongji' => $tongji]);
		}
		return View::fetch();
    }
	//余额明细导出
	public function moneylogexcel(){
		if(input('param.field') && input('param.order')){
			$order = 'member_moneylog.'.input('param.field').' '.input('param.order');
		}else{
			$order = 'member_moneylog.id desc';
		}
        $page = input('param.page')?:1;
        $limit = input('param.limit')?:10;
		$where = array();
		$where[] = ['member_moneylog.aid','=',aid];
		
		if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
		if(input('param.mid')) $where[] = ['member_moneylog.mid','=',trim(input('param.mid'))];
		if(input('?param.status') && input('param.status')!=='') $where[] = ['member_moneylog.status','=',input('param.status')];
		if(input('param.ctime') ){
            $ctime = explode(' ~ ',input('param.ctime'));
            $where[] = ['member_moneylog.createtime','>=',strtotime($ctime[0])];
            $where[] = ['member_moneylog.createtime','<',strtotime($ctime[1]) + 86400];
        }

		$list = Db::name('member_moneylog')->alias('member_moneylog')->field('member.nickname,member.headimg,member_moneylog.*')
            ->join('member member','member.id=member_moneylog.mid')->where($where)->order($order)
            ->page($page,$limit)
            ->select()->toArray();
        $count = Db::name('member_moneylog')->alias('member_moneylog')->field('member.nickname,member.headimg,member_moneylog.*')
            ->join('member member','member.id=member_moneylog.mid')->where($where)->order($order)
            ->count();
		$title = array();
		$title[] = t('会员').'信息';
		$title[] = '变更金额';
		$title[] = '变更后剩余';
		$title[] = '变更时间';
		$title[] = '备注';
		$data = array();
		foreach($list as $v){
			$v['money'] = dd_money_format($v['money'],$this->money_weishu);
			$tdata = array();
			$tdata[] = $v['nickname'].'('.t('会员').'ID:'.$v['mid'].')';
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
		Db::name('member_moneylog')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('删除余额明细'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
    public function getmoneylogdetail(){
       }
	//充值记录
	public function rechargelog(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = 'recharge_order.'.input('param.field').' '.input('param.order');
			}else{
				$order = 'recharge_order.id desc';
			}
			$where = [];
			$where[] = ['recharge_order.aid','=',aid];
			$where[] = ['recharge_order.status','=',1];
			if(input('?param.paytype') && input('param.paytype')!==''){
			     $where[] = ['recharge_order.paytype','=',input('param.paytype')];
			} 
			
			if(input('param.remark')) $where[] = ['recharge_order.remark','like','%'.trim(input('param.remark')).'%'];
			
			if(input('param.id')) $where[] = ['recharge_order.id','=',trim(input('param.id'))];
			if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
			if(input('param.mid')) $where[] = ['recharge_order.mid','=',trim(input('param.mid'))];
            if(input('param.ctime') ){
                $ctime = explode(' ~ ',input('param.ctime'));
                $where[] = ['recharge_order.createtime','>=',strtotime($ctime[0])];
                $where[] = ['recharge_order.createtime','<',strtotime($ctime[1]) + 86400];
            }
			$count = 0 + Db::name('recharge_order')->alias('recharge_order')->field('member.nickname,member.headimg,recharge_order.*')->join('member member','member.id=recharge_order.mid')->where($where)->count();
			$data = Db::name('recharge_order')->alias('recharge_order')->field('member.nickname,member.headimg,recharge_order.*')->join('member member','member.id=recharge_order.mid')->where($where)->page($page,$limit)->order($order)->select()->toArray();
	         $tdata = [];
			$total_money =  Db::name('recharge_order')->alias('recharge_order')->field('member.nickname,member.headimg,recharge_order.*')->join('member member','member.id=recharge_order.mid')->where($where)->sum('recharge_order.money');
            $tdata['total_money']  =$total_money;
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'tdata'=>$tdata]);
		}
		return View::fetch();
    }
	//充值记录导出
	public function rechargelogexcel(){
		if(input('param.field') && input('param.order')){
			$order = 'recharge_order.'.input('param.field').' '.input('param.order');
		}else{
			$order = 'recharge_order.id desc';
		}
        $page = input('param.page')?:1;
        $limit = input('param.limit')?:10;
		$where = [];
		$where[] = ['recharge_order.aid','=',aid];
		$where[] = ['recharge_order.status','=',1];
			if(input('?param.paytype') && input('param.paytype')!==''){
			     $where[] = ['recharge_order.paytype','=',input('param.paytype')];
			} 
			
			if(input('param.remark')) $where[] = ['recharge_order.remark','like','%'.trim(input('param.remark')).'%'];
			
		if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
		if(input('param.mid')) $where[] = ['recharge_order.mid','=',trim(input('param.mid'))];
        if(input('param.ctime') ){
            $ctime = explode(' ~ ',input('param.ctime'));
            $where[] = ['recharge_order.createtime','>=',strtotime($ctime[0])];
            $where[] = ['recharge_order.createtime','<',strtotime($ctime[1]) + 86400];
        }
		$list = Db::name('recharge_order')->alias('recharge_order')->field('member.nickname,member.headimg,recharge_order.*')
            ->join('member member','member.id=recharge_order.mid')
            ->where($where)->order($order)
            ->page($page,$limit)
            ->select()->toArray();
        $count = Db::name('recharge_order')->alias('recharge_order')->field('member.nickname,member.headimg,recharge_order.*')
            ->join('member member','member.id=recharge_order.mid')
            ->where($where)->order($order)
            ->count();
        $total_money =  Db::name('recharge_order')->alias('recharge_order')->field('member.nickname,member.headimg,recharge_order.*')->join('member member','member.id=recharge_order.mid')->where($where)->sum('recharge_order.money');
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
		if(!$data){ //最后一页没有数据的时候再追加，放到最后
            $data[]= [
                '',
                '',
                '',
                '',
                '',
                '累计充值金额：'.dd_money_format($total_money)
            ];
        }
        return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'title'=>$title]);
		$this->export_excel($title,$data);
	}
	//充值记录删除
	public function rechargelogdel(){
		$ids = input('post.ids/a');
		Db::name('recharge_order')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('删除充值记录'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
    public function rechargeprint(){
        }
	public function rechargerefund(){
        }
    public function rechargerefundprint(){
        }
    
	//提现记录
	public function withdrawlog(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = 'member_withdrawlog.'.input('param.field').' '.input('param.order');
			}else{
				$order = 'member_withdrawlog.id desc';
			}
			$where = [];
			$where[] = ['member_withdrawlog.aid','=',aid];
			if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
			if(input('param.mid')) $where[] = ['member_withdrawlog.mid','=',trim(input('param.mid'))];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['member_withdrawlog.status','=',input('param.status')];

            if(input('param.ctime') ){
                $ctime = explode(' ~ ',input('param.ctime'));
                $where[] = ['member_withdrawlog.createtime','>=',strtotime($ctime[0])];
                $where[] = ['member_withdrawlog.createtime','<',strtotime($ctime[1])];
            }

			$count = 0 + Db::name('member_withdrawlog')->alias('member_withdrawlog')->field('member.nickname,member.headimg,member.tel,member.realname,member.usercard,member_withdrawlog.*')->join('member member','member.id=member_withdrawlog.mid')->where($where)->count();
			$data = Db::name('member_withdrawlog')->alias('member_withdrawlog')->field('member.nickname,member.headimg,member.tel,member.realname,member.usercard,member_withdrawlog.*')->join('member member','member.id=member_withdrawlog.mid')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
    }
	//快商小额通推送记录
	public function withdrawlogxiaoetong(){
		return View::fetch();
    }
	//提现记录导出
	public function withdrawlogexcel(){
		if(input('param.field') && input('param.order')){
			$order = 'member_withdrawlog.'.input('param.field').' '.input('param.order');
		}else{
			$order = 'member_withdrawlog.id desc';
		}
        $page = input('param.page')?:1;
        $limit = input('param.limit')?:10;
		$where = [];
		$where[] = ['member_withdrawlog.aid','=',aid];
		if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
		if(input('param.mid')) $where[] = ['member_withdrawlog.mid','=',trim(input('param.mid'))];
		if(input('?param.status') && input('param.status')!=='') $where[] = ['member_withdrawlog.status','=',input('param.status')];

        if(input('param.ctime') ){
            $ctime = explode(' ~ ',input('param.ctime'));
            $where[] = ['member_withdrawlog.createtime','>=',strtotime($ctime[0])];
            $where[] = ['member_withdrawlog.createtime','<',strtotime($ctime[1])];
        }

		$list = Db::name('member_withdrawlog')->alias('member_withdrawlog')
            ->field('member.nickname,member.headimg,member.tel,member.realname,member.usercard,member_withdrawlog.*')
            ->join('member member','member.id=member_withdrawlog.mid')->where($where)->order($order)
            ->page($page,$limit)
            ->select()->toArray();
        $count = Db::name('member_withdrawlog')->alias('member_withdrawlog')
            ->field('member.nickname,member.headimg,member.tel,member.realname,member.usercard,member_withdrawlog.*')
            ->join('member member','member.id=member_withdrawlog.mid')->where($where)->order($order)
            ->count();
		$title = array();
		$title[] = t('会员').'信息';
        $title[] = '手机号';
		$title[] = '提现金额';
		$title[] = '打款金额';
		$title[] = '提现方式';
		$title[] = '收款账号';
        $title[] = '身份信息';
		$title[] = '提现时间';
		$title[] = '状态';
		$data = array();
		foreach($list as $v){
			$tdata = array();
			$tdata[] = $v['nickname'].'('.t('会员').'ID:'.$v['mid'].')';
            $tdata[] = $v['tel'];
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
            $tdata[] = $v['realname'].' '.$v['usercard'];
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
		$st = input('post.st');
		$reason = input('post.reason');
		$info = Db::name('member_withdrawlog')->where('aid',aid)->where('id',$id)->find();
        $info['txmoney'] = dd_money_format($info['txmoney']);
        $info['money'] = dd_money_format($info['money']);
		if($st==10){//微信打款
			if($info['status']!=1) return json(['status'=>0,'msg'=>'已审核状态才能打款']);
			$rs = \app\commons\Wxpay::transfers(aid,$info['mid'],$info['money'],$info['ordernum'],$info['platform'],t('余额').'提现');
			if($rs['status']==0){
				return json(['status'=>0,'msg'=>$rs['msg']]);
			}else{
				Db::name('member_withdrawlog')->where('aid',aid)->where('id',$id)->update(['status'=>3,'reason'=>$reason,'paytime'=>time(),'paynum'=>$rs['resp']['payment_no']]);
				$this->withdrawSuccessNotice($info);
				\app\commons\System::plog('余额提现微信打款'.$id);
				return json(['status'=>1,'msg'=>$rs['msg']]);
			}
		}else if($st == 20){
            }else if($st==30){
        	}else if($st=='huifu'){
            }else if($st=='huifu_moneypay'){
            }else if($st=='linghuoxin'){
            }else{
			$up_data = [];
			$up_data['status'] = $st;
			if($reason){
				$up_data['reason'] = $reason;
			}
			Db::name('member_withdrawlog')->where('aid',aid)->where('id',$id)->update($up_data);
			if($st == 2){//驳回返还余额
				\app\commons\Member::addmoney(aid,$info['mid'],$info['txmoney'],t('余额').'提现返还');
				$this->withdrawFailNotice($info,$reason);
				\app\commons\System::plog('余额提现驳回'.$id);
			}
			if($st==3){
				$this->withdrawSuccessNotice($info);
				\app\commons\System::plog('余额提现改为已打款'.$id);
			}
			if($st == 1){
				//小额通提现
				}
		}
		return json(['status'=>1,'msg'=>'操作成功']);
	}

    public function withdrawlogQuery()
    {
        $id = input('post.id/d');
        $info = Db::name('member_withdrawlog')->where('aid',aid)->where('id',$id)->find();
        $huifu = new \app\customs\Huifu([],aid,bid,$info['mid'],t('余额').'提现',$info['ordernum'],$info['money']);
        $rs = $huifu->moneypayTradeAcctpaymentPayQuery($info['paynum']);
        if($rs['status']==0){
            return json(['status'=>0,'msg'=>$rs['msg']]);
        }elseif($rs['status']==2){//处理中
            return json(['status'=>1,'msg'=>'支付处理中，'.$rs['msg']]);
        }else{
            $this->withdrawSuccessNotice($info);
            return json(['status'=>1,'msg'=>$rs['msg']]);
        }
    }

	//提现记录删除
	public function withdrawlogdel(){
		$ids = input('post.ids/a');
		Db::name('member_withdrawlog')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('余额提现记录删除'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}

	//充值赠送
	public function giveset(){
		if(request()->isAjax()){
			$info = input('post.info/a');
			$givedata = array();
			$postmoney = input('post.money/a');
			$postgive = input('post.give/a');
            $postgive_score = input('post.give_score/a');
            foreach($postmoney as $k=>$money){
				$data = [
					'money'=>$money,
					'give'=>$postgive[$k],
                    'give_score'=>$postgive_score[$k]
				];
				$givedata[] = $data;
			}
			$info['givedata'] = json_encode($givedata,JSON_UNESCAPED_UNICODE);
			$info['caninput'] = $info['caninput'];
			if(Db::name('recharge_giveset')->where('aid',aid)->find()){
				Db::name('recharge_giveset')->where('aid',aid)->update($info);
			}else{
				$info['aid'] = aid;
				$info['createtime'] = time();
				Db::name('recharge_giveset')->insert($info);
			}

            \app\commons\System::plog('编辑充值赠送');
			return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('giveset')]);
		}
		$info = Db::name('recharge_giveset')->where('aid',aid)->find();
		if(!$info) $info = ['caninput'=>1];
		View::assign('info',$info);
		return View::fetch();
	}

    public function adminmoneylog()
    {
        }

    //todo
    public function huifuBankLog()
    {
        }

    private function withdrawSuccessNotice($info)
    {
        //提现成功通知
        $tmplcontent = [];
        $tmplcontent['first'] = '您的提现申请已打款，请留意查收';
        $tmplcontent['remark'] = '请点击查看详情~';
        $tmplcontent['money'] = (string) round($info['money'],2);
        $tmplcontent['timet'] = date('Y-m-d H:i',$info['createtime']);
        $tempconNew = [];
        $tempconNew['amount2'] = (string) round($info['money'],2);//提现金额
        $tempconNew['time3'] = date('Y-m-d H:i',$info['createtime']);//提现时间
        \app\commons\Wechat::sendtmpl(aid,$info['mid'],'tmpl_tixiansuccess',$tmplcontent,m_url('pages/my/usercenter'),$tempconNew);
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
    }
    private function withdrawFailNotice($info,$reason='')
    {
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
    }

    public function goldmoneylog(){
        }
    public function goldmoneylogexcel(){
        }
    public function goldmoneylogdel(){
        }

    public function silvermoneylog(){
        }
    public function silvermoneylogexcel(){
        }
    public function silvermoneylogdel(){
        }
    public function getrechargedetail(){
        }

}
