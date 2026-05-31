<?php


// +----------------------------------------------------------------------
// | 佣金管理
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class Commission extends Common
{
	public function initialize(){
		parent::initialize();
		if(!getcustom('business_fenxiao')){
            if(bid > 0) showmsg('无操作权限');
        }
	}
	//佣金记录
    public function record(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = 'member_commission_record.'.input('param.field').' '.input('param.order');
			}else{
				$order = 'member_commission_record.id desc';
			}
			$where = [];
			$where[] = ['member_commission_record.aid','=',aid];
			$where[] = ['member_commission_record.status','in',[0,1,2]];
			
			if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
			if(input('param.mid')) $where[] = ['member_commission_record.mid','=',trim(input('param.mid'))];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['member_commission_record.status','=',input('param.status')];
			if(input('id')){
                $where[] = ['member_commission_record.id','=',trim(input('param.id'))];
            }

            if(input('param.ctime') ){
                $ctime = explode(' ~ ',input('param.ctime'));
                $where[] = ['member_commission_record.createtime','>=',strtotime($ctime[0])];
                $where[] = ['member_commission_record.createtime','<',strtotime($ctime[1])];
            }

			$count = 0 + Db::name('member_commission_record')->alias('member_commission_record')->field('member.nickname,member.headimg,member_commission_record.*')->join('member member','member.id=member_commission_record.mid')->where($where)->count();
			$data = Db::name('member_commission_record')->alias('member_commission_record')->field('member.nickname,member.headimg,member_commission_record.*')->join('member member','member.id=member_commission_record.mid')->where($where)->page($page,$limit)->order($order)->select()->toArray();
            $score_weishu = 0;
            if(getcustom('score_weishu')){
                $score_weishu = Db::name('admin_set')->where('aid',aid)->value('score_weishu');
                $score_weishu = $score_weishu?$score_weishu:0;
            }
			foreach($data as $k=>$v){
				if($v['type'] == 'levelup'){
					$data[$k]['orderstatus'] = 1;
				}elseif($v['orderid'] && !in_array($v['type'],['platform','salary'])){
					$data[$k]['orderstatus'] = Db::name($v['type'].'_order')->where('id',$v['orderid'])->value('status');
				}
				if($v['frommid']){
					$frommember = Db::name('member')->where('id',$v['frommid'])->find();
					if($frommember){
						$data[$k]['fromheadimg'] = $frommember['headimg'];
						$data[$k]['fromnickname'] = $frommember['nickname'];
					}
				}
                $data[$k]['score'] = dd_money_format($v['score'],$score_weishu);
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
    }
	//佣金记录导出
	public function recordexcel(){
		if(input('param.field') && input('param.order')){
			$order = 'member_commission_record.'.input('param.field').' '.input('param.order');
		}else{
			$order = 'member_commission_record.id desc';
		}
        $page = input('param.page')?:1;
        $limit = input('param.limit')?:10;
		$where = [];
		$where[] = ['member_commission_record.aid','=',aid];
        $where[] = ['member_commission_record.status','in',[0,1,2]];
		
		if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
		if(input('param.mid')) $where[] = ['member_commission_record.mid','=',trim(input('param.mid'))];
		if(input('?param.status') && input('param.status')!=='') $where[] = ['member_commission_record.status','=',input('param.status')];

        if(input('param.ctime') ){
            $ctime = explode(' ~ ',input('param.ctime'));
            $where[] = ['member_commission_record.createtime','>=',strtotime($ctime[0])];
            $where[] = ['member_commission_record.createtime','<',strtotime($ctime[1])];
        }

		$list = Db::name('member_commission_record')->alias('member_commission_record')
            ->field('member.nickname,member.headimg,member_commission_record.*')
            ->join('member member','member.id=member_commission_record.mid')->where($where)->order($order)
            ->page($page,$limit)
            ->select()->toArray();
        $count = Db::name('member_commission_record')->alias('member_commission_record')
            ->field('member.nickname,member.headimg,member_commission_record.*')
            ->join('member member','member.id=member_commission_record.mid')->where($where)->order($order)
            ->count();
		$title = array();
		$title[] = t('会员').'信息';
		$title[] = '佣金';
		$title[] = '积分';
		$title[] = '状态';
		$title[] = '产生时间';
		$title[] = '发放时间';
		$title[] = '备注';
		$data = array();
		foreach($list as $v){
			$tdata = array();
			$tdata[] = $v['nickname'].'('.t('会员').'ID:'.$v['mid'].')';
			$tdata[] = $v['commission'] ? $v['commission'] : 0;
			$tdata[] = $v['score'] ? $v['score'] : 0;
			$tdata[] = $v['status']==0 ? '未发放' : '已发放';
			$tdata[] = date('Y-m-d H:i:s',$v['createtime']);
			$tdata[] = $v['endtime'] ? date('Y-m-d H:i:s',$v['endtime']) : '';
			$tdata[] = $v['remark'];
			$data[] = $tdata;
		}
        return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'title'=>$title]);
		$this->export_excel($title,$data);
	}
	//佣金记录删除
	public function recorddel(){
		$ids = input('post.ids/a');
		Db::name('member_commission_record')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('删除佣金记录'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}


    //佣金记录
    public function tongji(){
        if(request()->isAjax()){
            $page = input('param.page');
            $limit = input('param.limit');
            if(input('param.field') && input('param.order')){
                $order = 'member_commission_record.'.input('param.field').' '.input('param.order');
            }else{
                $order = 'member_commission_record.id desc';
            }
            $where = [];
            $where[] = ['member_commission_record.aid','=',aid];
            $where[] = ['member_commission_record.status','in',[0,1]];

            if(input('param.level')) {
                if(!input('param.mid')){
                    return json(['code'=>1,'msg'=>'请先指定'.t('会员').'ID']);
                }
                if(input('param.fromid')){
                    return json(['code'=>1,'msg'=>'层级和下级ID不可同时设置']);
                }
                $mid = trim(input('param.mid'));
                $level = input('param.level/d');
                if($level <= 0) return json(['code'=>1,'msg'=>'请输入大于0的层级']);
                if($level == 1){
                    $childrenids = \app\commons\Member::getdownmids(aid,$mid,$level);
                }
                if($level > 1){
                    $childrenids1 = \app\commons\Member::getdownmids(aid,$mid,$level-1);
                    $childrenids2 = \app\commons\Member::getdownmids(aid,$mid,$level);
                    $childrenids = array_diff($childrenids2,$childrenids1);
                }
                if($childrenids)
                    $where[] = ['member_commission_record.frommid','in',$childrenids];
            }
            if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
            if(input('param.mid')) $where[] = ['member_commission_record.mid','=',trim(input('param.mid'))];
            if(input('param.fromid')) $where[] = ['member_commission_record.frommid','=',trim(input('param.fromid'))];
            if(input('param.ctime') ){
                $ctime = explode(' ~ ',input('param.ctime'));
                $where[] = ['member_commission_record.createtime','>=',strtotime($ctime[0])];
                $where[] = ['member_commission_record.createtime','<',strtotime($ctime[1]) + 86400];
            }
            $where2 = [];
            $where2[] = ['aid','=',aid];
//            $where2[] = ['status','in',[1,2,3]];
            if(input('param.cid') ){
                $where2[] = Db::raw('find_in_set('.input('param.cid/d').',cid)');
            }
            if(input('param.proid') ){
                $where2[] = ['proid','=',input('param.proid/d')];
            }
            if(input('param.cid') || input('param.proid')){
                $where_ogids = Db::name('shop_order_goods')->where($where2)->column('id');
//                if($where_ogids){
                    $where[] = ['member_commission_record.ogid','in',$where_ogids];
//                }
            }
            if(input('?param.status') && input('param.status')!=='') $where[] = ['member_commission_record.status','=',input('param.status')];
            $count = 0 + Db::name('member_commission_record')->alias('member_commission_record')->field('member.nickname,member.headimg,member_commission_record.*')->join('member member','member.id=member_commission_record.mid')->where($where)->count();
            $data = Db::name('member_commission_record')->alias('member_commission_record')->field('member.nickname,member.headimg,member_commission_record.*')->join('member member','member.id=member_commission_record.mid')->where($where)->page($page,$limit)->order($order)->select()->toArray();

            foreach($data as $k=>$v){
                if($v['type'] == 'levelup'){
                    $data[$k]['orderstatus'] = 1;
                }else{
                    $data[$k]['orderstatus'] = Db::name($v['type'].'_order')->where('id',$v['orderid'])->value('status');
                }
                if($v['frommid']){
                    $frommember = Db::name('member')->where('id',$v['frommid'])->find();
                    if($frommember){
                        $data[$k]['fromheadimg'] = $frommember['headimg'];
                        $data[$k]['fromnickname'] = $frommember['nickname'];
                    }
                }
            }
            $ogids = Db::name('member_commission_record')->alias('member_commission_record')->field('member.nickname,member.headimg,member_commission_record.*')->join('member member','member.id=member_commission_record.mid')->where($where)->column('ogid');
            if($ogids) $total = 0+Db::name('shop_order_goods')->whereIn('id',$ogids)->sum('real_totalprice');
            $total = round($total,2);
            $totalCommission = 0+Db::name('member_commission_record')->alias('member_commission_record')->field('member.nickname,member.headimg,member_commission_record.*')->join('member member','member.id=member_commission_record.mid')->where($where)->sum(Db::raw('member_commission_record.commission'));
            $totalCommission = round($totalCommission,2);
            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'total'=>$total,'totalCommission'=>$totalCommission]);
        }
        return View::fetch();
    }

	//佣金明细
    public function commissionlog(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = 'member_commissionlog.'.input('param.field').' '.input('param.order');
			}else{
				$order = 'member_commissionlog.id desc';
			}
			$where = [];
			$where[] = ['member_commissionlog.aid','=',aid];
			
			if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
			if(input('param.mid')) $where[] = ['member_commissionlog.mid','=',trim(input('param.mid'))];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['member_commissionlog.status','=',input('param.status')];

            if(input('param.ctime') ){
                $ctime = explode(' ~ ',input('param.ctime'));
                $where[] = ['member_commissionlog.createtime','>=',strtotime($ctime[0])];
                $where[] = ['member_commissionlog.createtime','<',strtotime($ctime[1])];
            }

			$count = 0 + Db::name('member_commissionlog')->alias('member_commissionlog')->field('member.nickname,member.headimg,member_commissionlog.*')->join('member member','member.id=member_commissionlog.mid')->where($where)->count();
			$data = Db::name('member_commissionlog')->alias('member_commissionlog')->field('member.nickname,member.headimg,member_commissionlog.*')->join('member member','member.id=member_commissionlog.mid')->where($where)->page($page,$limit)->order($order)->select()->toArray();
            $moeny_weishu = 2;
            if(getcustom('fenhong_money_weishu')){
                $moeny_weishu = Db::name('admin_set')->where('aid',aid)->value('fenhong_money_weishu');
            }
			foreach($data as $k=>$v){
				if($v['frommid']){
					$frommember = Db::name('member')->where('id',$v['frommid'])->find();
					if($frommember){
						$data[$k]['fromheadimg'] = $frommember['headimg'];
						$data[$k]['fromnickname'] = $frommember['nickname'];
					}
				}
				$data[$k]['commission'] = dd_money_format($v['commission'],$moeny_weishu);
                $data[$k]['after'] = dd_money_format($v['after'],$moeny_weishu);
                $data[$k]['service_fee'] = dd_money_format($v['service_fee'],$moeny_weishu);
			}

			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
    }
	//佣金明细导出
	public function commissionlogexcel(){
		if(input('param.field') && input('param.order')){
			$order = 'member_commissionlog.'.input('param.field').' '.input('param.order');
		}else{
			$order = 'member_commissionlog.id desc';
		}
        $page = input('param.page')?:1;
        $limit = input('param.limit')?:10;
		$where = [];
		$where[] = ['member_commissionlog.aid','=',aid];
		
		if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
		if(input('param.mid')) $where[] = ['member_commissionlog.mid','=',trim(input('param.mid'))];
		if(input('?param.status') && input('param.status')!=='') $where[] = ['member_commissionlog.status','=',input('param.status')];

        if(input('param.ctime') ){
            $ctime = explode(' ~ ',input('param.ctime'));
            $where[] = ['member_commissionlog.createtime','>=',strtotime($ctime[0])];
            $where[] = ['member_commissionlog.createtime','<',strtotime($ctime[1])];
        }

		$list = Db::name('member_commissionlog')->alias('member_commissionlog')->field('member.nickname,member.headimg,member_commissionlog.*')
            ->join('member member','member.id=member_commissionlog.mid')->where($where)->order($order)->page($page,$limit)->select()->toArray();
        $count = Db::name('member_commissionlog')->alias('member_commissionlog')->field('member.nickname,member.headimg,member_commissionlog.*')
            ->join('member member','member.id=member_commissionlog.mid')->where($where)->order($order)->count();
		$title = array();
		$title[] = t('会员').'信息';
		$title[] = '变更金额';
		$title[] = '变更后金额';
		$title[] = '变更时间';
		$title[] = '备注';
		$data = array();
        $moeny_weishu = 2;
        if(getcustom('fenhong_money_weishu')){
            $moeny_weishu = Db::name('admin_set')->where('aid',aid)->value('fenhong_money_weishu');
        }
		foreach($list as $v){
			$tdata = array();
			$tdata[] = $v['nickname'].'('.t('会员').'ID:'.$v['mid'].')';
			$tdata[] = dd_money_format($v['commission'],$moeny_weishu);
			$tdata[] = dd_money_format($v['after'],$moeny_weishu);
			$tdata[] = date('Y-m-d H:i:s',$v['createtime']);
			$tdata[] = $v['remark'];
			$data[] = $tdata;
		}
        return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'title'=>$title]);
		$this->export_excel($title,$data);
	}
	//佣金明细删除
	public function commissionlogdel(){
		$ids = input('post.ids/a');
		Db::name('member_commissionlog')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('删除佣金明细'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	//提现记录
	public function withdrawlog(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = 'member_commission_withdrawlog.'.input('param.field').' '.input('param.order');
			}else{
				$order = 'member_commission_withdrawlog.id desc';
			}
			$where = array();
			$where[] = ['member_commission_withdrawlog.aid','=',aid];
			if(getcustom('business_fenxiao')){
                $bid = bid;
                if($bid>0){
                    $where[] = ['member_commission_withdrawlog.bid','=',$bid];
                }
            }

			
			if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
			if(input('param.mid')) $where[] = ['member_commission_withdrawlog.mid','=',trim(input('param.mid'))];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['member_commission_withdrawlog.status','=',input('param.status')];

            if(input('param.ctime') ){
                $ctime = explode(' ~ ',input('param.ctime'));
                $where[] = ['member_commission_withdrawlog.createtime','>=',strtotime($ctime[0])];
                $where[] = ['member_commission_withdrawlog.createtime','<',strtotime($ctime[1])];
            }

			$count = 0 + Db::name('member_commission_withdrawlog')->alias('member_commission_withdrawlog')->field('member.nickname,member.headimg,member.tel,member_commission_withdrawlog.*')->join('member member','member.id=member_commission_withdrawlog.mid')->where($where)->count();
			$data = Db::name('member_commission_withdrawlog')->alias('member_commission_withdrawlog')->field('member.nickname,member.headimg,member.tel,member.realname,member.usercard,member_commission_withdrawlog.*')->join('member member','member.id=member_commission_withdrawlog.mid')->where($where)->page($page,$limit)->order($order)->select()->toArray();

			// $comwithdrawbl = Db::name('admin_set')->where('aid',aid)->value('comwithdrawbl');
			foreach($data as $k=>$v){
				if( !getcustom('fengdanjiangli') ){
					$data[$k]['paymoney'] = $v['money'];
					$data[$k]['tomoney'] = 0;
				}
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
    }
    //快商小额通推送记录
	public function withdrawlogxiaoetong(){
		if(getcustom('transfer_farsion')){
			if(request()->isAjax()){
				$page = input('param.page');
				$limit = input('param.limit');
				if(input('param.field') && input('param.order')){
					$order = 'member_withdrawlog_xiaoetong.'.input('param.field').' '.input('param.order');
				}else{
					$order = 'member_withdrawlog_xiaoetong.id desc';
				}
				$where = [];
				$where[] = ['member_withdrawlog_xiaoetong.aid','=',aid];
				$where[] = ['member_withdrawlog_xiaoetong.withdraw_type','=','佣金提现'];
				if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
				if(input('param.mid')) $where[] = ['member_withdrawlog_xiaoetong.mid','=',trim(input('param.mid'))];
				if(input('?param.status') && input('param.status')!=='') $where[] = ['member_withdrawlog_xiaoetong.status','=',input('param.status')];
				$count = 0 + Db::name('member_withdrawlog_xiaoetong')->alias('member_withdrawlog_xiaoetong')->field('member.nickname,member.headimg,member.tel,member.realname,member.usercard,member_withdrawlog_xiaoetong.*')->join('member member','member.id=member_withdrawlog_xiaoetong.mid')->where($where)->count();
				$data = Db::name('member_withdrawlog_xiaoetong')->alias('member_withdrawlog_xiaoetong')->field('member.nickname,member.headimg,member.tel,member.realname,member.usercard,member_withdrawlog_xiaoetong.*')->join('member member','member.id=member_withdrawlog_xiaoetong.mid')->where($where)->page($page,$limit)->order($order)->select()->toArray();
				return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
			}
		}
		return View::fetch();
    }
	//提现记录导出
	public function withdrawlogexcel(){
		if(input('param.field') && input('param.order')){
			$order = 'member_commission_withdrawlog.'.input('param.field').' '.input('param.order');
		}else{
			$order = 'member_commission_withdrawlog.id desc';
		}
        $page = input('param.page')?:1;
        $limit = input('param.limit')?:10;
		$where = [];
		$where[] = ['member_commission_withdrawlog.aid','=',aid];
        if(getcustom('business_fenxiao')){
            $bid = bid;
            $where[] = ['member_commission_withdrawlog.bid','=',$bid];
        }
		
		if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
		if(input('param.mid')) $where[] = ['member_commission_withdrawlog.mid','=',trim(input('param.mid'))];
		if(input('?param.status') && input('param.status')!=='') $where[] = ['member_commission_withdrawlog.status','=',input('param.status')];

        if(input('param.ctime') ){
            $ctime = explode(' ~ ',input('param.ctime'));
            $where[] = ['member_commission_withdrawlog.createtime','>=',strtotime($ctime[0])];
            $where[] = ['member_commission_withdrawlog.createtime','<',strtotime($ctime[1])];
        }

		$list = Db::name('member_commission_withdrawlog')->alias('member_commission_withdrawlog')
            ->field('member.nickname,member.headimg,member.tel,member.realname,member.usercard,member_commission_withdrawlog.*')
            ->join('member member','member.id=member_commission_withdrawlog.mid')->where($where)->order($order)->page($page,$limit)->select()->toArray();
        $count = Db::name('member_commission_withdrawlog')->alias('member_commission_withdrawlog')
            ->field('member.nickname,member.headimg,member.tel,member.realname,member.usercard,member_commission_withdrawlog.*')
            ->join('member member','member.id=member_commission_withdrawlog.mid')->where($where)->order($order)->count();
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
		$info = Db::name('member_commission_withdrawlog')->where('aid',aid)->where('id',$id)->find();

		if(getcustom('fengdanjiangli')){
			$paymoney = $info['paymoney'];
			$tomoney = $info['tomoney'];
		}else{
			$paymoney = $info['money'];
			$tomoney = 0;
		}

		if($st==10){//微信打款
			if($info['status']!=1) return json(['status'=>0,'msg'=>'已审核状态才能打款']);

			Db::startTrans();
            try {
                $optstatus = false;//操作状态
                $msg       = '操作失败';

                $rs = \app\commons\Wxpay::transfers(aid,$info['mid'],$paymoney,$info['ordernum'],$info['platform'],t('佣金').'提现');
                if($rs && $rs['status'] == 1){
                    $optstatus = true;

                    if($tomoney > 0){
                    	$optstatus = false;
                        $addmoney = \app\commons\Member::addmoney(aid,$info['mid'],$tomoney,t('佣金').'提现');
                        if($addmoney && $addmoney['status'] ==1){
                            $optstatus = true;
                        }else{
                            $msg = $addmoney && $addmoney['msg']?$addmoney['msg']:'添加'.t('会员').'余额失败';
                        }
                    }

                    if($optstatus){
                        $sql = Db::name('member_commission_withdrawlog')->where('aid',aid)->where('id',$id)->update(['status'=>3,'paytime'=>time(),'paynum'=>$rs['resp']['payment_no']]);
                        if(!$sql){
                            $optstatus = false;
                            $msg       = '操作失败';
                        }else{
                            $msg = $rs['msg']?$rs['msg']:'操作成功';
                        }
                    }

                }else{
                    $msg = $rs && $rs['msg']?$rs['msg']:'提现失败';
                }
            } catch (\Exception $e) {
                Db::rollback();
                return json(['status'=>0,'msg'=>$msg]);
            }

            if($optstatus){
                Db::commit();

                //处理发送信息
                $this->deal_sendinfo($info,'微信打款');
                \app\commons\System::plog('佣金提现微信打款'.$id);

                return json(['status'=>1,'msg'=>$msg]);
            }else{
                Db::rollback();
                return json(['status'=>0,'msg'=>$msg]);
            }
		}else if($st == 20){
            if(getcustom('pay_adapay')){
                if($info['status']!=1) return json(['status'=>0,'msg'=>'已审核状态才能打款']);
                $adapay = Db::name('adapay_member')->where('aid',aid)->where('mid',$info['mid'])->find();

                Db::startTrans();
                try {
                    $optstatus = false;//操作状态
                    $msg       = '操作失败';//
                    $reason    = '';//

                    $rs = \app\customs\AdapayPay::balancePay(aid,'h5',$adapay['member_id'],$info['ordernum'],$paymoney);
                    if($rs && $rs['status'] == 1){
                        //从用户余额中进行提现到银行卡
                        $drs = \app\customs\AdapayPay::drawcash(aid,'h5',$adapay['member_id'],$info['ordernum'],$paymoney);
                        if($drs && $drs['status'] == 1){
                            $optstatus = true;

                            if($tomoney > 0){
                                $optstatus = false;
                                $addmoney = \app\commons\Member::addmoney(aid,$info['mid'],$tomoney,t('佣金').'提现');
                                if($addmoney && $addmoney['status'] ==1){
                                    $optstatus = true;
                                }else{
                                    $msg = $addmoney && $addmoney['msg']?$addmoney['msg']:'添加'.t('会员').'余额失败';
                                }
                            }

                            if($optstatus){
                                $sql = Db::name('member_commission_withdrawlog')->where('aid',aid)->where('id',$id)->update(['status'=>3,'paytime'=>time(),'paynum'=>$rs['data']['balance_seq_id'],'reason'=>'']);
                                if(!$sql){
                                    $optstatus = false;
                                    $msg       = '操作失败';
                                }
                            }
                        }else{
                            $msg    = $drs && $drs['msg']?$drs['msg']:'操作失败';
                            $reason = $msg;
                        }
                    }else{
                        $msg    = $rs && $rs['msg']?$rs['msg']:'操作失败';
                        $reason = $msg;
                        
                    }
                } catch (\Exception $e) {
                    Db::rollback();
                }

                if($optstatus){

                    Db::commit();

                    //处理发送信息
                    $this->deal_sendinfo($info,'汇付天下打款');
                    \app\commons\System::plog('佣金提现汇付天下打款'.$id);

                    return json(['status'=>1,'msg'=>'已提交打款，请耐心等待']);
                }else{
                    Db::rollback();
                    if($reason){
                        Db::name('member_commission_withdrawlog')->where('aid',aid)->where('id',$info['id'])->update(['reason'=>$reason]);
                    }
                    return json(['status'=>0,'msg'=>$msg]);
                }
            }
        }else if($st==30){
        	if(getcustom('alipay_auto_transfer')){
	        	//支付宝打款
				if($info['status']!=1) return json(['status'=>0,'msg'=>'已审核状态才能打款']);

				//查询会员信息
				$member = Db::name('member')->where('id',$info['mid'])->field('aliaccount,aliaccountname')->find();
				if(!$member){
					return json(['status'=>0,'msg'=>t('会员').'不存在']);
				}
				if(empty($member['aliaccount']) || empty($member['aliaccountname']) ){
					return json(['status'=>0,'msg'=>t('会员').'支付宝信息不完整']);
				}
				
				Db::startTrans();
	            try {
	                $optstatus = false;//操作状态
	                $msg       = '操作失败';

	                $rs = \app\commons\Alipay::transfers(aid,$info['ordernum'],$paymoney,t('佣金').'提现',$member['aliaccount'],$member['aliaccountname'],t('佣金').'提现');
	                if($rs && $rs['status'] == 1){
	                    $optstatus = true;

	                    if($tomoney > 0){
	                    	$optstatus = false;
	                        $addmoney = \app\commons\Member::addmoney(aid,$info['mid'],$tomoney,t('佣金').'提现');
	                        if($addmoney && $addmoney['status'] ==1){
	                            $optstatus = true;
	                        }else{
	                            $msg = $addmoney && $addmoney['msg']?$addmoney['msg']:'添加'.t('会员').'余额失败';
	                        }
	                    }

	                    if($optstatus){
	                        $sql = Db::name('member_commission_withdrawlog')->where('aid',aid)->where('id',$id)->update(['status'=>3,'paytime'=>time(),'paynum'=>$rs['pay_fund_order_id']]);
	                        if(!$sql){
	                            $optstatus = false;
	                            $msg       = '操作失败';
	                        }else{
	                            $msg = $rs['msg']?$rs['msg']:'操作成功';
	                        }
	                    }

	                }else{
	                    $msg = $rs && $rs['msg']?$rs['msg']:'提现失败';
	                }
	            } catch (\Exception $e) {
	                Db::rollback();
	                return json(['status'=>0,'msg'=>$msg]);
	            }

	            if($optstatus){
	                Db::commit();

	                //处理发送信息
	                $this->deal_sendinfo($info,'支付宝打款');
	                \app\commons\System::plog('佣金提现支付宝打款'.$id);

	                return json(['status'=>1,'msg'=>$msg]);
	            }else{
	                Db::rollback();
	                return json(['status'=>0,'msg'=>$msg]);
	            }
	        }
        }else if($st=='huifu'){
            if(getcustom('pay_huifu')){
                //汇付斗拱打款 银行卡代发
                if($info['status']!=1) return json(['status'=>0,'msg'=>'已审核状态才能打款']);
                //查询会员信息
                $member = Db::name('member')->where('id',$info['mid'])->find();
                if(!$member){
                    return json(['status'=>0,'msg'=>t('会员').'不存在']);
                }

                Db::startTrans();
                try {
                    $optstatus = false;//操作状态
                    $msg       = '操作失败';

                    $appinfo = \app\commons\System::appinfo(aid);
                    if(empty($appinfo['huifu_sys_id'])) $appinfo = \app\commons\System::appinfo(aid,'wx');
                    if(empty($appinfo['huifu_sys_id'])) $appinfo = \app\commons\System::appinfo(aid,'h5');
                    if(empty($appinfo['huifu_sys_id'])) return json(['status'=>0,'msg'=>'汇付支付信息配置错误']);
                    $huifu = new \app\customs\Huifu($appinfo,aid,bid,$member['id'],t('佣金').'提现',$info['ordernum'],$paymoney);
                    $rs = $huifu->bankSurrogate($info);
                    if($rs && $rs['status'] == 1){
                        $optstatus = true;
                        if($tomoney > 0){
                            $optstatus = false;
                            $addmoney = \app\commons\Member::addmoney(aid,$info['mid'],$tomoney,t('佣金').'提现');
                            if($addmoney && $addmoney['status'] ==1){
                                $optstatus = true;
                            }else{
                                $msg = $addmoney && $addmoney['msg']?$addmoney['msg']:'添加'.t('会员').'余额失败';
                            }
                        }

                        if($optstatus){
                            $sql = Db::name('member_commission_withdrawlog')->where('aid',aid)->where('id',$id)->update(['status'=>3,'paytime'=>time(),'paynum'=>$rs['pay_fund_order_id']]);
                            if(!$sql){
                                $optstatus = false;
                                $msg       = '操作失败';
                            }else{
                                $msg = $rs['msg']?$rs['msg']:'操作成功';
                            }
                        }

                    }else{
                        $msg = $rs && $rs['msg']?$rs['msg']:'提现失败';
                    }
                } catch (\Exception $e) {
                    Db::rollback();
                    return json(['status'=>0,'msg'=>$msg]);
                }

                if($optstatus){
                    Db::commit();

                    //处理发送信息
                    $this->deal_sendinfo($info,$info['paytype']);
                    \app\commons\System::plog('佣金提现汇付斗拱打款'.$id);

                    return json(['status'=>1,'msg'=>$msg]);
                }else{
                    Db::rollback();
                    return json(['status'=>0,'msg'=>$msg]);
                }

            }
        }else if($st=='huifu_moneypay'){
            if(getcustom('pay_huifu')){
                //汇付斗拱余额打款 余额支付
                if($info['status']!=1) return json(['status'=>0,'msg'=>'已审核状态才能打款']);
                //查询会员信息
                $member = Db::name('member')->where('id',$info['mid'])->find();
                if(!$member){
                    return json(['status'=>0,'msg'=>t('会员').'不存在']);
                }

                $huifu = new \app\customs\Huifu([],aid,bid,$member['id'],t('佣金').'提现',$info['ordernum'],$info['money']);
                $rs = $huifu->moneypayTradeAcctpaymentPay($info['huifu_id'],array_merge($info,['tablename'=>'member_commission_withdrawlog']));
                if($rs['status']==0){
                    return json(['status'=>0,'msg'=>$rs['msg']]);
                }elseif($rs['status']==2){//处理中
                    Db::name('member_commission_withdrawlog')->where('aid',aid)->where('id',$id)->update(['status'=>4,'paynum'=>$rs['resp']['hf_seq_id']]);
                    \app\commons\System::plog('佣金提汇付斗拱余额打款'.$id);
                    return json(['status'=>1,'msg'=>'支付处理中，'.$rs['msg']]);
                }else{
                    $huifu->tradeSettlementEnchashmentRequest();
                    Db::name('member_commission_withdrawlog')->where('aid',aid)->where('id',$id)->update(['status'=>3,'paytime'=>time(),'paynum'=>$rs['resp']['hf_seq_id']]);
                    //处理发送信息
                    $this->deal_sendinfo($info,'汇付天下打款');
                    \app\commons\System::plog('佣金提汇付斗拱余额打款'.$id);
                    return json(['status'=>1,'msg'=>$rs['msg']]);
                }
            }
        }else if($st=='linghuoxin'){
            if(getcustom('extend_linghuoxin')){
                if($info['paytype'] == '灵活薪支付宝' || $info['paytype'] == '灵活薪银行卡'){
                    $member = Db::name('member')->where('id',$info['mid'])->where('aid',aid)->find();
                    $gopay = \app\customs\LinghuoxinCustom::gopay(aid,0,$member,$id,$info,$info['paytype'],1);
                    if($gopay && $gopay['status'] == 1){
                        $updata = [];
                        $updata['taskNo']   = $gopay['data']['taskNo'];
                        $updata['taskdata'] = json_encode($gopay['data']);
                        Db::name('member_commission_withdrawlog')->where('id',$id)->update($updata);
                        return json(['status'=>1,'msg'=>'提交成功,请等待打款','data'=>[]]);
                    }else{
                        $msg = $gopay && $gopay['msg']?$gopay['msg']:'';
                        return json(['status'=>0,'msg'=>$msg]);
                    }
                }else{
                    return json(['status'=>0,'msg'=>'提现方式错误']);
                }
            }
		}else{
			$up_data = [];
			$up_data['status'] = $st;
			if($reason){
				$up_data['reason'] = $reason;
			}
			Db::name('member_commission_withdrawlog')->where('aid',aid)->where('id',$id)->update($up_data);
			if($st == 2){//驳回返还佣金
                if(getcustom('commission_withdraw_need_score')){
                    if($info['need_score']>0){
                        \app\commons\Member::addscore(aid,$info['mid'],$info['need_score'],t('佣金').'提现消耗返还');
                    }
                }
                if(getcustom('commission_duipeng_score_withdraw')){
                    if($info['need_commission_withdraw_score']>0){
                        \app\commons\Member::add_commission_withdraw_score(aid,$info['mid'],$info['need_commission_withdraw_score'],t('佣金').'提现消耗返还');
                    }
                }
				\app\commons\Member::addcommission(aid,$info['mid'],0,$info['txmoney'],t('佣金').'提现返还',0,'withdraw_back');
                if(getcustom('cashback_yongjin')){
                    //还原正在返现的商品
                    Db::name('shop_order_goods_cashback')->where('id',$info['cashback_id'])->update(['cashback_yongjin'=>1]);
                }
                if(getcustom('business_fenxiao')){
                    $bid = $info['bid'];
                    if($bid>0){
                        Db::name('business_fenxiao_bonus_total')
                            ->where('mid',$info['mid'])
                            ->where('bid',$bid)
                            ->inc('remain',$info['txmoney'])
                            ->dec('withdraw',$info['txmoney'])
                            ->update();
                    }
                }
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
				\app\commons\System::plog('佣金提现驳回'.$id);
			}
			if($st==3){


				// 提现时计算$tomoney
				if($tomoney > 0){
					\app\commons\Member::addmoney(aid,$info['mid'],$tomoney,t('佣金').'提现');
				}
                //记录用户累计提现
                if(getcustom('cashback_yongjin')){
                    Db::name('member')->where('id',$info['mid'])->inc('cash_yongji_total',$info['txmoney'])->update();
                }

                //处理发送信息
	            $this->deal_sendinfo($info,$info['paytype']);
	            \app\commons\System::plog('佣金提现改为已打款'.$id);
			}
            if($st == 1){
                if(getcustom('transfer_farsion')){
                    if($info['paytype'] == '小额通支付宝' || $info['paytype'] == '小额通银行卡'){
                        $field = 'id,realname,usercard,tel,bankcardnum';
                        $userinfo = Db::name('member')->field($field)->where('aid',aid)->where('id',$info['mid'])->find();
                        $xetService = new  \app\commons\Xiaoetong();
                        //导入数据        
                        $xet_res = $xetService->sendData($info,$userinfo,'佣金提现');	
                        if($xet_res['code'] == 0){

                        }else{
                            Db::name('member_commission_withdrawlog')->where('id',$info['id'])->update(['status' => 0]);
                            return json(['status'=>0,'msg'=>'提现失败'.$xet_res['msg']]);
                        }
                    }
                }
            }
		}
		Db::commit();
		return json(['status'=>1,'msg'=>'操作成功']);
	}

    public function withdrawlogQuery()
    {
        $id = input('post.id/d');
        $info = Db::name('member_commission_withdrawlog')->where('aid',aid)->where('id',$id)->find();
        $huifu = new \app\customs\Huifu([],aid,bid,$info['mid'],t('佣金').'提现',$info['ordernum'],$info['money']);
        $rs = $huifu->moneypayTradeAcctpaymentPayQuery($info['paynum']);
        if($rs['status']==0){
            return json(['status'=>0,'msg'=>$rs['msg']]);
        }elseif($rs['status']==2){//处理中
            return json(['status'=>1,'msg'=>'支付处理中，'.$rs['msg']]);
        }else{
            $this->deal_sendinfo($info);
            return json(['status'=>1,'msg'=>$rs['msg']]);
        }
    }

	//处理发送信息
	private function deal_sendinfo($info,$paytype=""){
        if(empty($paytype)){
            $paytype = $info['paytype'];
        }
        if(getcustom('product_givetongzheng')){
            if($info['tongzheng']>0){
                \app\commons\Member::addtongzheng($info['aid'],$info['mid'],$info['tongzheng'],'提现');
            }
        }
		//提现成功通知
        $tmplcontent = [];
        $tmplcontent['first']  = '您的提现申请已打款，请留意查收';
        $tmplcontent['remark'] = '请点击查看详情~';
        $tmplcontent['money']  = (string) round($info['money'],2);
        $tmplcontent['timet']  = date('Y-m-d H:i',$info['createtime']);
        $tempconNew = [];
        $tempconNew['amount2'] = (string) round($info['money'],2);//提现金额
        $tempconNew['time3'] = date('Y-m-d H:i',$info['createtime']);//提现时间
        \app\commons\Wechat::sendtmpl(aid,$info['mid'],'tmpl_tixiansuccess',$tmplcontent,m_url('pages/my/usercenter'),$tempconNew);
        //订阅消息
        $tmplcontent = [];
        $tmplcontent['amount1'] = $info['money'];
        $tmplcontent['thing3']  = $paytype;
        $tmplcontent['time5']   = date('Y-m-d H:i');
        
        $tmplcontentnew = [];
        $tmplcontentnew['amount3'] = $info['money'];
        $tmplcontentnew['phrase9'] = $paytype;
        $tmplcontentnew['date8']   = date('Y-m-d H:i');
        \app\commons\Wechat::sendwxtmpl(aid,$info['mid'],'tmpl_tixiansuccess',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);
        //短信通知
        $member = Db::name('member')->where('id',$info['mid'])->find();
        if($member['tel']){
            $tel = $member['tel'];
            \app\commons\Sms::send(aid,$tel,'tmpl_tixiansuccess',['money'=>$info['money']]);
        }
	}

	//提现记录删除
	public function withdrawlogdel(){
		$ids = input('post.ids/a');
		Db::name('member_commission_withdrawlog')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('佣金提现记录删除'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}

	
	//佣金明细
    public function fenhonglog(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = 'member_fenhonglog.'.input('param.field').' '.input('param.order');
			}else{
				$order = 'member_fenhonglog.id desc';
			}
			$where = [];
			$where[] = ['member_fenhonglog.aid','=',aid];
			
			if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
			if(input('param.mid')) $where[] = ['member_fenhonglog.mid','=',trim(input('param.mid'))];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['member_fenhonglog.status','=',input('param.status')];
			if(getcustom('fenhong_max')){
                if(input('fenhong_remark')){
                    $fenhong_remark = input('fenhong_remark');
                    if($fenhong_remark=='股东分红'){
                        $fenhong_remark = t('股东分红',aid);
                        $where[] = ['member_fenhonglog.type','=','fenhong'];
                    }
                    $where[] = ['member_fenhonglog.remark','like','%'.$fenhong_remark.'%'];
                }
            }

            if(input('param.ctime') ){
                $ctime = explode(' ~ ',input('param.ctime'));
                $where[] = ['member_fenhonglog.createtime','>=',strtotime($ctime[0])];
                $where[] = ['member_fenhonglog.createtime','<',strtotime($ctime[1])];
            }

			$count = 0 + Db::name('member_fenhonglog')->alias('member_fenhonglog')->field('member.nickname,member.headimg,member_fenhonglog.*')->join('member member','member.id=member_fenhonglog.mid')->where($where)->count();
			$data = Db::name('member_fenhonglog')->alias('member_fenhonglog')->field('member.nickname,member.headimg,member_fenhonglog.*')->join('member member','member.id=member_fenhonglog.mid')->where($where)->page($page,$limit)->order($order)->select()->toArray();
            $moeny_weishu = 2;
            if(getcustom('fenhong_money_weishu')){
                $moeny_weishu = Db::name('admin_set')->where('aid',aid)->value('fenhong_money_weishu');
            }
			foreach ($data as $key=>$val){
                $data[$key]['commission']  =  dd_money_format($val['commission'],$moeny_weishu);
            }
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
    }
	//分红记录导出
	public function fenhonglogexcel(){
		if(input('param.field') && input('param.order')){
			$order = 'member_fenhonglog.'.input('param.field').' '.input('param.order');
		}else{
			$order = 'member_fenhonglog.id desc';
		}
		$where = [];
		$where[] = ['member_fenhonglog.aid','=',aid];
		
		if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
		if(input('param.mid')) $where[] = ['member_fenhonglog.mid','=',trim(input('param.mid'))];
		if(input('?param.status') && input('param.status')!=='') $where[] = ['member_fenhonglog.status','=',input('param.status')];

        if(input('param.ctime') ){
            $ctime = explode(' ~ ',input('param.ctime'));
            $where[] = ['member_fenhonglog.createtime','>=',strtotime($ctime[0])];
            $where[] = ['member_fenhonglog.createtime','<',strtotime($ctime[1])];
        }

		$list = Db::name('member_fenhonglog')->alias('member_fenhonglog')->field('member.nickname,member.headimg,member_fenhonglog.*')->join('member member','member.id=member_fenhonglog.mid')->where($where)->order($order)->select()->toArray();
		$title = array();
		$title[] = t('会员').'信息';
		$title[] = '分红金额';
		$title[] = '结算时间';
		$title[] = '备注';
		$data = array();
        $moeny_weishu = 2;
        if(getcustom('fenhong_money_weishu')){
            $moeny_weishu = Db::name('admin_set')->where('aid',aid)->value('fenhong_money_weishu');
        }
		foreach($list as $v){
			$tdata = array();
			$tdata[] = $v['nickname'].'('.t('会员').'ID:'.$v['mid'].')';
			$tdata[] = dd_money_format($v['commission'],$moeny_weishu);
			$tdata[] = date('Y-m-d H:i:s',$v['createtime']);
			$tdata[] = $v['remark'];
			$data[] = $tdata;
		}
		$this->export_excel($title,$data);
	}
	//分红记录删除
	public function fenhonglogdel(){
		$ids = input('post.ids/a');
		Db::name('member_fenhonglog')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('删除分红记录'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}

	//团队分红--ttdz
	public function teamfenhong(){
		if(request()->isAjax()){
			$sysset = Db::name('admin_set')->where('aid',aid)->find();
			$fhjiesuantype = $sysset['fhjiesuantype'];
			$fhjiesuantime = $sysset['fhjiesuantime'];
			$fhjiesuanbusiness = $sysset['fhjiesuanbusiness'];
			
			$type = input('param.type');
			if(!$type) $type = 1;
			if($type == 1){ //上月
				$starttime = strtotime(date('Y-m-01').' -1 month');
				$endtime = strtotime(date('Y-m-01'));
			}
			if($type == 2){ //本月
				$starttime = strtotime(date('Y-m-01'));
				$endtime = time();
			}
			if($type == 3){ //昨日
				$starttime = strtotime(date('Y-m-d'))-86400;
				$endtime = $starttime + 86400;
			}
			if($type == 4){ //今日
				$starttime = strtotime(date('Y-m-d'));
				$endtime = time();
			}
			if($type == 10){ //全部
				$starttime = 1;
				$endtime = time();
			}

			//多商户的商品是否参与分红
			if($fhjiesuanbusiness == 1){
				$bwhere = '1=1';
			}else{
				$bwhere = [['og.bid','=','0']];
			}
			if(getcustom('fenhong_manual')){
				$oglist = Db::name('shop_order_goods')->alias('og')->field('og.*,o.area2,m.nickname,m.headimg')->where('og.aid',aid)->where('og.isteamfenhong',0)->where('og.status',3)->join('shop_order o','o.id=og.orderid')->join('member m','m.id=og.mid')->where($bwhere)->where('og.endtime','>=',$starttime)->where('og.endtime','<',$endtime)->order('og.id desc')->select()->toArray();
			}else{
				$oglist = Db::name('shop_order_goods')->alias('og')->field('og.*,o.area2,m.nickname,m.headimg')->where('og.aid',aid)->where('og.isfenhong',0)->where('og.isteamfenhong',0)->where('og.status',3)->join('shop_order o','o.id=og.orderid')->join('member m','m.id=og.mid')->where($bwhere)->where('og.endtime','>=',$starttime)->where('og.endtime','<',$endtime)->order('og.id desc')->select()->toArray();
			}

			$newoglist = [];
			$commissionyj = 0;
			if($oglist){

                //参与团队分红的等级
                $teamfhlevellist = Db::name('member_level')->where('aid',aid)->where('teamfenhonglv','>','0')->where(function ($query) {
                    $query->where('teamfenhongbl','>',0)->whereOr('teamfenhong_money','>',0);
                })->column('*','id');

                if(getcustom('level_teamfenhong')) {
                    //参与等级团队分红的等级
                    $level_teamfhlevellist = Db::name('member_level')->where('aid',aid)->where('level_teamfenhong_ids','<>','')->where('level_teamfenhonglv','>','0')->where(function ($query) {
                        $query->where('level_teamfenhongbl','>',0)->whereOr('level_teamfenhong_money','>',0);
                    })->column('*','id');
                }

                $isjicha = ($sysset['teamfenhong_differential'] == 1 ? true : false);
				$allfenhongprice = 0;
				$ogids = [];
				$midfhArr = [];
				$midteamfhArr = [];
				$midareafhArr = [];
                $teamfenhong_orderids = [];
                $teamfenhong_orderids_cat = [];
                $level_teamfenhong_orderids = [];
                $level_teamfenhong_orderids_cat = [];
				foreach($oglist as $og){
					if($fhjiesuantype == 0){
						$fenhongprice = $og['real_totalprice'];
					}else{
						$fenhongprice = $og['real_totalprice'] - $og['cost_price'];
					}
					if($fenhongprice <= 0) continue;
					$ogids[] = $og['id'];
					$allfenhongprice = $allfenhongprice + $fenhongprice;

					$commission = 0;
					
					//团队分红
					if($teamfhlevellist){
						$pids = Db::name('member')->where('id',$og['mid'])->value('path');
                        if($pids) $pids .= ','.$og['mid'];
                        else $pids = (string)$og['mid'];
						if($pids){
							$parentList = Db::name('member')->where('id','in',$pids)->order(Db::raw('field(id,'.$pids.')'))->select()->toArray();
							$parentList = array_reverse($parentList);
							$hasfhlevelids = [];
							$last_teamfenhongbl = 0;
                            //层级判断，如购买人等级未开启“包含自己teamfenhong_self“则购买人的上级为第一级，开启了则购买人为第一级
                            $level_i = 0;
							foreach($parentList as $k=>$parent){
								$leveldata = $teamfhlevellist[$parent['levelid']];
                                if($parent['levelstarttime'] >= $og['createtime']) {
                                    $levelup_order_levelid = Db::name('member_levelup_order')->where('aid',aid)->where('mid', $parent['id'])->where('status', 2)
                                        ->where('levelup_time', '<', $og['createtime'])->order('levelup_time', 'desc')->value('levelid');
                                    if($levelup_order_levelid) {
                                        $parent['levelid'] = $levelup_order_levelid;
                                        $leveldata = $teamfhlevellist[$parent['levelid']];
                                    }else{
                                        //不包含自己跳过
                                        unset($parentList[$k]);
                                        continue;
                                    }
                                }
                                $level_i++;
                                if($parent['id'] == $og['mid'] && $leveldata['teamfenhong_self'] != 1) { $level_i--; unset($parentList[$k]);continue;}//不包含自己则层级-1
                                if(!$leveldata || $level_i>$leveldata['teamfenhonglv']) continue;
                                if($leveldata['teamfenhongonly'] == 1 && in_array($parent['levelid'],$hasfhlevelids)) continue; //该等级设置了只给最近的上级分红
                                $hasfhlevelids[] = $parent['levelid'];
                                //每单奖励
                                if($leveldata['teamfenhong_money'] > 0 && !in_array($og['orderid'],$teamfenhong_orderids)) {
                                    $fenhongmoney = $leveldata['teamfenhong_money'];
                                    $commission += $fenhongmoney;
                                    $teamfenhong_orderids[] = $og['orderid'];
                                }
                                //分红比例
								if($isjicha){
									$this_teamfenhongbl = $leveldata['teamfenhongbl'] - $last_teamfenhongbl;
								}else{
									$this_teamfenhongbl = $leveldata['teamfenhongbl'];
								}
								if($this_teamfenhongbl <=0) continue;
								$last_teamfenhongbl = $last_teamfenhongbl + $this_teamfenhongbl;

								if($leveldata['teamfenhongonly'] == 1 && in_array($parent['levelid'],$hasfhlevelids)) continue; //该等级设置了只给最近的上级分红
								$hasfhlevelids[] = $parent['levelid'];

								$fenhongmoney = $this_teamfenhongbl * $fenhongprice * 0.01;
								//if($parent['id'] == mid){
									$commission += $fenhongmoney;
								//}
							}
							//其他分组等级

						}
					}

                    //等级团队分红
                    if(getcustom('level_teamfenhong')) {
                    	//查询此账号是否有等级分红权限
                        $uinfo = db('admin_user')->where('aid',aid)->where('bid',0)->where('isadmin','>=',1)->field('id,auth_type,auth_data')->find();
                        if(!$uinfo){ continue; }

                        if($uinfo['auth_type'] !=1){
                            if(empty($uinfo['auth_data'])){ continue; }

                            $auth_data =  json_decode($uinfo['auth_data'],true);
                            if(!in_array('level_teamfenhong,level_teamfenhong',$auth_data)){ continue; }
                        }

                    	$member = Db::name('member')->where('id', $og['mid'])->field('levelid')->find();
                    	//判断是否是商城商品是否开启了等级分红配
                        $product = Db::name('shop_product')->where('id',$og['proid'])->field('id,level_teamfenhongset,levelteamfenhongs')->find();
                        if($product){
                            //关闭了等级分红，则不走
                            if($product['level_teamfenhongset'] == -1){
                                continue;
                            //单独设置
                            }else if($product['level_teamfenhongset'] == 1){
                                if(!$product['levelteamfenhongs']){
                                    continue;
                                }
                                $levelteamfenhongs = json_decode($product['levelteamfenhongs'],true);
                                if(!$levelteamfenhongs){
                                    continue;
                                }

                                $level_teamfhlevellistnew = [];
                                foreach($levelteamfenhongs as $lk=>$lv){
                                	//查询等级是否存在
                                	$teamlevel = Db::name('member_level')->where('id',$lv['id'])->field('id,cid,name,sort')->find();
                                	if(!$teamlevel) continue;
                                    //如果团队等级ID为空 或者 分红级数小等于0 或者 分红提成比例或分红固定金额小于等于0且每单分红金额 则不走
                                    if($lv['level_teamfenhong_ids'] == '' || $lv['level_teamfenhonglv']<=0 || ($lv['level_teamfenhongbl']<=0 && $lv['level_teamfenhong_money']<=0)){
                                        continue;
                                    }
                                    $lv['name'] = $teamlevel['name'];
                                    $lv['sort'] = $teamlevel['sort'];
                                    $level_teamfhlevellistnew[$lk]=$lv;
                                }
                            }else{
                                if(!$level_teamfhlevellist) continue;
                                $level_teamfhlevellistnew = $level_teamfhlevellist;
                            }
                        }else{
                            if(!$level_teamfhlevellist) continue;
                            $level_teamfhlevellistnew = $level_teamfhlevellist;
                        }

                        if($level_teamfhlevellistnew){
                            $pids = Db::name('member')->where('id',$og['mid'])->value('path');
                            if($pids){

                                //查询符合等级条件的
                                $plevelids = [];
                                foreach($level_teamfhlevellistnew as $level) {
                                    array_push($plevelids,$level['id']);
                                }

                                //格式化pids数组
                            	$pidsarr = explode(',',$pids);
                            	//反转数组
                            	$pidsarr = array_reverse($pidsarr);

                            	//获取上级并按照pids排序
                                $parentList = Db::name('member')->where('id','in',$pids)->whereIn('levelid',$plevelids)->order(Db::raw('field(id,'.$pids.')'))->select()->toArray();
                                if(!count($parentList)) continue;
                                //反转数组
                                $parentList = array_reverse($parentList);

                                //标记上级的是此会员的多少层级，从0开始
                                foreach($pidsarr as $pk=>$pv){
                                    foreach($parentList as &$pv2){
                                        if($pv2['id'] == $pv){
                                            $pv2['teamnum'] = $pk;
                                        }
                                    }
                                    unset($pv2);
                                }

                                $hasfhlevelids = [];
                                $last_teamcommission   = 0;
                                foreach($parentList as $k=>$parent){

                                	//获取上级等级信息
                                    $leveldata = $level_teamfhlevellistnew[$parent['levelid']];
                                    //判断升级最后时间
                                    if($parent['levelstarttime'] >= $og['createtime']) {
                                        $levelup_order_levelid = Db::name('member_levelup_order')->where('mid', $parent['id'])->where('aid',$parent['aid'])->where('status', 2)
                                            ->where('levelup_time', '<', $og['createtime'])->order('levelup_time', 'desc')->value('levelid');
                                        if($levelup_order_levelid) {
                                            $parent['levelid'] = $levelup_order_levelid;
                                            $leveldata = $level_teamfhlevellistnew[$parent['levelid']];
                                        }
                                    }

                                    //等级不存在 或者团队级数超过他级数
                                    if(!$leveldata || $parent['teamnum']>=$leveldata['level_teamfenhonglv']) continue;

                                    //上级等级设置的团队等级ID
                                    $level_teamfenhong_ids = $leveldata['level_teamfenhong_ids']?explode(',',$leveldata['level_teamfenhong_ids']):'';
                                    if(!in_array($member['levelid'], $level_teamfenhong_ids)) continue;

                                    //查询是否开启需要直属下级超越，开启则需要查询直属下级级别是否超越了此上级
                                    if($leveldata['level_surpass'] == 1){
                                        //如果上级团队层级是直属上级，则查询直接查本会员的等级
                                        if($parent['teamnum']==0){
                                            $mlevel = 0+Db::name('member_level')->where('id',$member['levelid'])->where('sort','>',$leveldata['sort'])->count('id');
                                            if(!$mlevel) continue;
                                        //其他层次上级，查询此层次上级的直属下级等级
                                        }else{
                                            //查询上级所处位置
                                            $pos = array_search($parent['id'],$pidsarr);
                                            if($pos>0){
                                                $lowpid = $pidsarr[$pos-1];
                                                //查询直属下级会员信息
                                                $lowparent = Db::name('member')->where('id',$lowpid)->field('levelid')->find();
                                                if(empty($lowparent)) continue;

                                                //是否符合上级设置团队等级ID的范围
                                                //if(!in_array($lowparent['levelid'], $level_teamfenhong_ids)) continue;

                                                $lowlevel = 0+Db::name('member_level')->where('id',$lowparent['levelid'])->where('sort','>',$leveldata['sort'])->count('id');
                                                if(!$lowlevel) continue;
                                            }else{
                                                continue;
                                            }
                                        }
                                    }

                                    //每单奖励
                                    if($leveldata['level_teamfenhong_money'] > 0 && !in_array($og['orderid'], $level_teamfenhong_orderids[$parent['id']])) {
                                    	//该等级设置了只给最近的上级分红
                                        if($leveldata['level_teamfenhongonly'] == 1 && in_array($parent['levelid'],$hasfhlevelids)) continue;
                                        $hasfhlevelids[] = $parent['levelid'];

                                        $fenhongmoney = $leveldata['level_teamfenhong_money'];
                                        $level_teamfenhong_orderids[$parent['id']][] = $og['orderid'];
                                        //if($parent['id'] == mid){
                                            $commission += $fenhongmoney;
                                        //}
                                    }

                                    //分红比例
                                    if($leveldata['level_teamfenhongbl'] > 0) {
                                        //该等级设置了只给最近的上级分红
                                        if($leveldata['level_teamfenhongonly'] == 1 && in_array($parent['levelid'],$hasfhlevelids)) continue;
                                        $hasfhlevelids[] = $parent['levelid'];

                                        //如果是分红类型是固定分红
                                        if($leveldata['level_teamfenhongbl_type'] && $leveldata['level_teamfenhongbl_type'] == 1){
                                            //查询是等级团队分红级差单独设置
                                            if(!$leveldata['level_jicha']){
                                                if($isjicha){
                                                    $fenhongmoney   = $leveldata['level_teamfenhongbl'] - $last_teamcommission;
                                                }else{
                                                    $fenhongmoney   = $leveldata['level_teamfenhongbl'];
                                                }
                                            }else{
                                                if($leveldata['level_jicha'] == 1){
                                                    $fenhongmoney   = $leveldata['level_teamfenhongbl'] - $last_teamcommission;
                                                }else{
                                                    $fenhongmoney   = $leveldata['level_teamfenhongbl'];
                                                }
                                            }
                                            if($fenhongmoney <=0) continue;
                                            $last_teamcommission = $last_teamcommission + $fenhongmoney;

                                        }else{
                                            //查询是等级团队分红级差单独设置
                                            if(!$leveldata['level_jicha']){
                                                if($isjicha){
                                                    $fenhongmoney = $leveldata['level_teamfenhongbl']* $fenhongprice * 0.01 - $last_teamcommission;
                                                }else{
                                                    $fenhongmoney = $leveldata['level_teamfenhongbl']* $fenhongprice * 0.01;
                                                }
                                            }else{
                                                if($leveldata['level_jicha'] == 1){
                                                    $fenhongmoney = $leveldata['level_teamfenhongbl']* $fenhongprice * 0.01 - $last_teamcommission;
                                                }else{
                                                    $fenhongmoney = $leveldata['level_teamfenhongbl']* $fenhongprice * 0.01;
                                                }
                                            }
                                            if($fenhongmoney <=0) continue;
                                            $last_teamcommission = $last_teamcommission + $fenhongmoney;
                                        }
                                        
                                        //if($parent['id'] == mid){
                                            $commission += $fenhongmoney;
                                        //}
                                    }
                                }
                                //其他分组等级
                            }
                        }
                    }


					if($commission > 0){
						$commissionyj += $commission;
						$og['commission'] = round($commission,2);
						$newoglist[] = $og;
					}
					$commissionyj = round($commissionyj,2);
				}
			}
			return json(['code'=>0,'msg'=>'查询成功','type'=>$type,'count'=>count($newoglist),'data'=>$newoglist,'commissionyj'=>$commissionyj]);
		}
		return View::fetch();
	}
	//团队分红发放--ttdz
	public function teamfenhongfafang(){
		$aid = aid;
		$sysset = Db::name('admin_set')->where('aid',aid)->find();
		$fhjiesuantype = $sysset['fhjiesuantype'];
		$fhjiesuantime = $sysset['fhjiesuantime'];
		$fhjiesuanbusiness = $sysset['fhjiesuanbusiness'];
		
		$type = input('param.type');
		if(!$type) $type = 1;
		if($type == 1){ //上月
			$starttime = strtotime(date('Y-m-01').' -1 month');
			$endtime = strtotime(date('Y-m-01'));
		}
		if($type == 2){ //本月
			$starttime = strtotime(date('Y-m-01'));
			$endtime = time();
		}
		if($type == 3){ //昨日
			$starttime = strtotime(date('Y-m-d'))-86400;
			$endtime = $starttime + 86400;
		}
		if($type == 4){ //今日
			$starttime = strtotime(date('Y-m-d'));
			$endtime = time();
		}
		if($type == 10){ //全部
			$starttime = 1;
			$endtime = time();
		}

		//多商户的商品是否参与分红
		if($fhjiesuanbusiness == 1){
			$bwhere = '1=1';
		}else{
			$bwhere = [['og.bid','=','0']];
		}

		if(getcustom('fenhong_manual')){
			$oglist = Db::name('shop_order_goods')->alias('og')->field('og.*,o.area2,m.nickname,m.headimg')->where('og.aid',aid)->where('og.isteamfenhong',0)->where('og.status',3)->join('shop_order o','o.id=og.orderid')->join('member m','m.id=og.mid')->where($bwhere)->where('og.endtime','>=',$starttime)->where('og.endtime','<',$endtime)->order('og.id desc')->select()->toArray();
		}else{
			$oglist = Db::name('shop_order_goods')->alias('og')->field('og.*,o.area2,m.nickname,m.headimg')->where('og.aid',aid)->where('og.isfenhong',0)->where('og.isteamfenhong',0)->where('og.status',3)->join('shop_order o','o.id=og.orderid')->join('member m','m.id=og.mid')->where($bwhere)->where('og.endtime','>=',$starttime)->where('og.endtime','<',$endtime)->order('og.id desc')->select()->toArray();
		}

		if(!$oglist) return json(['status'=>0,'msg'=>'没有未发放的团队分红订单']);
		
		//参与团队分红的等级
		$teamfhlevellist = Db::name('member_level')->where('aid',$aid)->where('teamfenhonglv','>','0')->where(function ($query) {
            $query->where('teamfenhongbl','>',0)->whereOr('teamfenhong_money','>',0);
        })->column('id,cid,name,teamfenhonglv,teamfenhongbl,teamfenhongonly,teamfenhong_money,teamfenhong_self','id');

		$defaultCid = Db::name('member_level_category')->where('aid',$aid)->where('isdefault', 1)->value('id');
		
		$isjicha = ($sysset['teamfenhong_differential'] == 1 ? true : false);
		$allfenhongprice = 0;
		$ogids = [];
		$midfhArr = [];
		$midteamfhArr = [];
		$midareafhArr = [];
        $teamfenhong_orderids = [];
        $teamfenhong_orderids_cat = [];
		foreach($oglist as $og){
			if($fhjiesuantype == 0){
				$fenhongprice = $og['real_totalprice'];
			}else{
				$fenhongprice = $og['real_totalprice'] - $og['cost_price'];
			}
			if($fenhongprice <= 0) continue;
			$ogids[] = $og['id'];
			$allfenhongprice = $allfenhongprice + $fenhongprice;

			//团队分红
			if($teamfhlevellist){
				$pids = Db::name('member')->where('id',$og['mid'])->value('path');
                if($pids) $pids .= ','.$og['mid'];
                else $pids = (string)$og['mid'];
				if($pids){
					$parentList = Db::name('member')->where('id','in',$pids)->order(Db::raw('field(id,'.$pids.')'))->select()->toArray();
					$parentList = array_reverse($parentList);
					$hasfhlevelids = [];
					$last_teamfenhongbl = 0;
                    //层级判断，如购买人等级未开启“包含自己teamfenhong_self“则购买人的上级为第一级，开启了则购买人为第一级
                    $level_i = 0;
					foreach($parentList as $k=>$parent){
						$leveldata = $teamfhlevellist[$parent['levelid']];
                        if($parent['levelstarttime'] >= $og['createtime']) {
                            $levelup_order_levelid = Db::name('member_levelup_order')->where('aid',$aid)->where('mid', $parent['id'])->where('status', 2)
                                ->where('levelup_time', '<', $og['createtime'])->order('levelup_time', 'desc')->value('levelid');
                            if($levelup_order_levelid) {
                                $parent['levelid'] = $levelup_order_levelid;
                                $leveldata = $teamfhlevellist[$parent['levelid']];
                            }else{
                                //不包含自己跳过
                                unset($parentList[$k]);
                                continue;
                            }
                        }

                        $level_i++;
                        if($parent['id'] == $og['mid'] && $leveldata['teamfenhong_self'] != 1) { $level_i--; unset($parentList[$k]);continue;}//不包含自己则层级-1
                        if(!$leveldata || $level_i>$leveldata['teamfenhonglv']) continue;
                        if($leveldata['teamfenhongonly'] == 1 && in_array($parent['levelid'],$hasfhlevelids)) continue; //该等级设置了只给最近的上级分红

                        $hasfhlevelids[] = $parent['levelid'];
                        //每单奖励
                        if($leveldata['teamfenhong_money'] > 0 && !in_array($og['orderid'],$teamfenhong_orderids[$parent['id']])) {
                            $fenhongmoney = $leveldata['teamfenhong_money'];
                            if($midteamfhArr[$parent['id']]){
                                $midteamfhArr[$parent['id']]['commission'] =  $midteamfhArr[$parent['id']]['commission'] + $fenhongmoney;
                                $midteamfhArr[$parent['id']]['ogids'][] = $og['id'];
                            } else {
                                $midteamfhArr[$parent['id']] = ['commission'=>$fenhongmoney,'ogids'=>[$og['id']]];
                            }
                            $teamfenhong_orderids[$parent['id']][] = $og['orderid'];
                        }
                        //分红比例
						if($isjicha){
							$this_teamfenhongbl = $leveldata['teamfenhongbl'] - $last_teamfenhongbl;
						}else{
							$this_teamfenhongbl = $leveldata['teamfenhongbl'];
						}
						if($this_teamfenhongbl <=0) continue;
						$last_teamfenhongbl = $last_teamfenhongbl + $this_teamfenhongbl;
						
						if($leveldata['teamfenhongonly'] == 1 && in_array($parent['levelid'],$hasfhlevelids)) continue; //该等级设置了只给最近的上级分红
						$hasfhlevelids[] = $parent['levelid'];

						$fenhongmoney = $this_teamfenhongbl * $fenhongprice * 0.01;
						if($midteamfhArr[$parent['id']]){
							$midteamfhArr[$parent['id']]['commission'] = $midteamfhArr[$parent['id']]['commission'] + $fenhongmoney;
							$midteamfhArr[$parent['id']]['ogids'][] = $og['id'];
						}else{
							$midteamfhArr[$parent['id']] = ['commission'=>$fenhongmoney,'ogids'=>[$og['id']]];
						}
					}
					//其他分组等级
					if(getcustom('plug_sanyang')) {
						$catList = Db::name('member_level_category')->where('aid', $aid)->where('isdefault', 0)->select()->toArray();
						foreach ($catList as $cat) {
							$parentList = Db::name('member_level_record')->field('mid id,levelid')->where('aid', $aid)->where('cid', $cat['id'])->whereIn('mid', $pids)->select()->toArray();
							$parentList = array_reverse($parentList);
							$hasfhlevelids = [];
							$last_teamfenhongbl = 0;
							foreach($parentList as $k=>$parent){
								$leveldata = $teamfhlevellist[$parent['levelid']];
								if(!$leveldata || $k>=$leveldata['teamfenhonglv']) continue;
                                if($parent['id'] == $og['mid'] && $leveldata['teamfenhong_self'] != 1) continue;
                                //每单奖励
                                if($leveldata['teamfenhong_money'] > 0 && !in_array($og['orderid'],$teamfenhong_orderids_cat[$parent['id']])) {
                                    $fenhongmoney = $leveldata['teamfenhong_money'];
                                    if($midteamfhArr[$parent['id']]){
                                        $midteamfhArr[$parent['id']]['commission'] =  $midteamfhArr[$parent['id']]['commission'] + $fenhongmoney;
                                        $midteamfhArr[$parent['id']]['ogids'][] = $og['id'];
                                    } else {
                                        $midteamfhArr[$parent['id']] = ['commission'=>$fenhongmoney,'ogids'=>[$og['id']]];
                                    }
                                    $teamfenhong_orderids_cat[$parent['id']] = $og['orderid'];
                                }
                                //分红比例
								if($isjicha){
									$this_teamfenhongbl = $leveldata['teamfenhongbl'] - $last_teamfenhongbl;
								}else{
									$this_teamfenhongbl = $leveldata['teamfenhongbl'];
								}
								if($this_teamfenhongbl <=0) continue;
								$last_teamfenhongbl = $last_teamfenhongbl + $this_teamfenhongbl;

								if($leveldata['teamfenhongonly'] == 1 && in_array($parent['levelid'],$hasfhlevelids)) continue; //该等级设置了只给最近的上级分红
								$hasfhlevelids[] = $parent['levelid'];

								$fenhongmoney = $this_teamfenhongbl * $fenhongprice * 0.01;
								
								if($midteamfhArr[$parent['id']]){
									$midteamfhArr[$parent['id']]['commission'] += $fenhongmoney;
									$midteamfhArr[$parent['id']]['ogids'][] = $og['id'];
								}else{
									$midteamfhArr[$parent['id']] = ['commission'=>$fenhongmoney,'ogids'=>[$og['id']]];
								}
							}
						}
					}
				}
			}
			Db::name('shop_order_goods')->where('aid',$aid)->where('id',$og['id'])->update(['isteamfenhong'=>1]);
		}
		$totalcommission = 0;
		if($midteamfhArr){
			foreach($midteamfhArr as $mid=>$midfh){
				$commission = $midfh['commission'];
				$commission = round($commission,2);
				$fhdata = [];
				$fhdata['aid'] = $aid;
				$fhdata['mid'] = $mid;
				$fhdata['commission'] = $commission;
				$fhdata['remark'] = t('团队分红');
				$fhdata['type'] = 'teamfenhong';
				$fhdata['createtime'] = time();
				$fhdata['ogids'] = implode(',',$midfh['ogids']);
				Db::name('member_fenhonglog')->insert($fhdata);
				\app\commons\Member::addcommission($aid,$mid,0,$commission,$fhdata['remark']);
				$totalcommission += $commission;
			}
		}
		return json(['status'=>1,'msg'=>'发放完成，共发放佣金：'.$totalcommission.'元','totalcommission'=>$totalcommission]);
	}

    public function fuchiRecord(){
        if(getcustom('commission_frozen')){
            //扶持金记录
            if(request()->isAjax()){
                $page = input('param.page');
                $limit = input('param.limit');
                if(input('param.field') && input('param.order')){
                    $order = 'member_fuchi_record.'.input('param.field').' '.input('param.order');
                }else{
                    $order = 'member_fuchi_record.id desc';
                }
                $where = [];
                $where[] = ['member_fuchi_record.aid','=',aid];

                if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
                if(input('param.mid')) $where[] = ['member_fuchi_record.mid','=',trim(input('param.mid'))];
                if(input('?param.status') && input('param.status')!=='') $where[] = ['member_fuchi_record.status','=',input('param.status')];
                $count = 0 + Db::name('member_fuchi_record')->alias('member_fuchi_record')->field('member.nickname,member.headimg,member_fuchi_record.*')->join('member member','member.id=member_fuchi_record.mid')->where($where)->count();
                $data = Db::name('member_fuchi_record')->alias('member_fuchi_record')->field('member.nickname,member.headimg,member_fuchi_record.*')->join('member member','member.id=member_fuchi_record.mid')->where($where)->page($page,$limit)->order($order)->select()->toArray();

                foreach($data as $k=>$v){
                    if($v['type'] == 'levelup'){
                        $data[$k]['orderstatus'] = 1;
                    }else{
                        if($v['orderid'])
                        $data[$k]['orderstatus'] = Db::name('shop_order')->where('id',$v['orderid'])->value('status');
                    }
                }
                return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
            }
            return View::fetch();
        }
    }

    public function fuchiLog(){
        if(getcustom('commission_frozen')){
            //扶持金明细
            if(request()->isAjax()){
                $page = input('param.page');
                $limit = input('param.limit');
                if(input('param.field') && input('param.order')){
                    $order = 'member_fuchi_log.'.input('param.field').' '.input('param.order');
                }else{
                    $order = 'member_fuchi_log.id desc';
                }
                $where = [];
                $where[] = ['member_fuchi_log.aid','=',aid];

                if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
                if(input('param.mid')) $where[] = ['member_fuchi_log.mid','=',trim(input('param.mid'))];
                if(input('?param.status') && input('param.status')!=='') $where[] = ['member_fuchi_log.status','=',input('param.status')];
                $count = 0 + Db::name('member_fuchi_log')->alias('member_fuchi_log')->field('member.nickname,member.headimg,member_fuchi_log.*')->join('member member','member.id=member_fuchi_log.mid')->where($where)->count();
                $data = Db::name('member_fuchi_log')->alias('member_fuchi_log')->field('member.nickname,member.headimg,member_fuchi_log.*')->join('member member','member.id=member_fuchi_log.mid')->where($where)->page($page,$limit)->order($order)->select()->toArray();
                return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
            }
            return View::fetch();
        }
    }

    //明细
    public function gongxianlog(){
	    if(getcustom('member_gongxian')){
            if(request()->isAjax()){
                $page = input('param.page');
                $limit = input('param.limit');
                if(input('param.field') && input('param.order')){
                    $order = 'member_gongxianlog.'.input('param.field').' '.input('param.order');
                }else{
                    $order = 'member_gongxianlog.id desc';
                }
                $where = array();
                $where[] = ['member_gongxianlog.aid','=',aid];

                if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
                if(input('param.mid')) $where[] = ['member_gongxianlog.mid','=',trim(input('param.mid'))];
                if(input('?param.status') && input('param.status')!=='') $where[] = ['member_gongxianlog.status','=',input('param.status')];
                $count = 0 + Db::name('member_gongxianlog')->alias('member_gongxianlog')->field('member.nickname,member.headimg,member_gongxianlog.*')->join('member member','member.id=member_gongxianlog.mid')->where($where)->count();
                $data = Db::name('member_gongxianlog')->alias('member_gongxianlog')->field('member.nickname,member.headimg,member_gongxianlog.*')->join('member member','member.id=member_gongxianlog.mid')->where($where)->page($page,$limit)->order($order)->select()->toArray();
                return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
            }

            return View::fetch();
        }
    }

    public function fenhongGudong()
    {
        if(getcustom('fenhong_gudong')){
            //手动发放股东分红
            $levelArr = Db::name('member_level')->where('aid',aid)->order('sort,id')->column('name','id');

            if(request()->isAjax()){
                $sysset = Db::name('admin_set')->where('aid',aid)->find();
                //多商户的商品是否参与分红

                $page = input('param.page');
                $limit = input('param.limit');
                $page--;
                if(input('param.field') && input('param.order')){
                    $order = input('param.field').' '.input('param.order');
                }else{
                    $order = 'teamyeji desc';
                }
                $whereStr = $whereStr2 = ' ';
                $whereOn = ' ';
                $whereStr .= 'm.aid = '.aid ;//.' and teamyeji > 0 ';
                $whereStr2 .= ' and og2.status in (1,2,3) ';
                if($sysset['fhjiesuanbusiness'] != 1){
                    $whereOn .= ' and og.bid = 0';
                }
                if(input('param.ctime') ){
                    $ctime = explode(' ~ ',input('param.ctime'));
                    $time1 = strtotime($ctime[0]);
                    $time2 = strtotime($ctime[1]) + 86399;
                    $whereStr2 .= ' and og2.createtime between '.$time1.' and '.$time2;
                }
                if(input('param.yeji1') && input('param.yeji2')){
                    $yeji1 = input('param.yeji1');
                    $yeji2 = input('param.yeji2');
                    $whereStr .= ' and teamyeji between '.$yeji1.' and '.$yeji2;
                }
                if(input('param.levelid')){
                    $whereStr .= ' and m.levelid = '.input('param.levelid/d');
                }
//                dump($ctime);
//                dump($order);
//                dd($whereStr);
//SELECT levelid,og.createtime,m.aid,og.bid,og.mid,m.id,og.status,m.nickname,m.headimg,m.platform,m.pid,m.realname,m.tel,m.money,m.score,m.commission,m.buymoney,m.levelid ,(SELECT sum(real_totalprice) FROM ddwx_shop_order_goods as og2 left JOIN ddwx_member as m2 on og2.mid=m2.id where FIND_IN_SET(m.id,m2.path)) as teamyeji FROM ddwx_shop_order_goods as og RIGHT join ddwx_member as m on og.mid=m.id and og.status in (1,2,3) GROUP BY m.id having m.aid =3 order by teamyeji desc
                $count = Db::query("SELECT count(m.id) as countnum,levelid,og.createtime,m.aid,og.bid,og.mid,m.id,og.status,m.nickname,m.headimg,m.platform,m.pid,m.realname,m.tel,m.money,m.score,m.commission,m.buymoney,m.levelid,
       (SELECT sum(real_totalprice) FROM ".table_name('shop_order_goods')." as og2 LEFT JOIN ".table_name('member')." as m2 on og2.mid=m2.id where (FIND_IN_SET(m.id,m2.path) or og2.mid = m.id) ".$whereStr2.") as teamyeji 
       FROM ".table_name('shop_order_goods')." as og RIGHT JOIN ".table_name('member')." as m on og.mid=m.id ".$whereOn." GROUP BY m.id having ".$whereStr);
                $count = 0 + count($count);
                $data = Db::query("SELECT levelid,og.createtime,m.aid,og.bid,og.mid,m.id,og.status,m.nickname,m.headimg,m.platform,m.pid,m.realname,m.tel,m.money,m.score,m.commission,m.buymoney,m.levelid,
       (SELECT sum(real_totalprice) FROM ".table_name('shop_order_goods')." as og2 LEFT JOIN ".table_name('member')." as m2 on og2.mid=m2.id where (FIND_IN_SET(m.id,m2.path) or og2.mid = m.id) ".$whereStr2.") as teamyeji
       FROM ".table_name('shop_order_goods')." as og RIGHT JOIN ".table_name('member')." as m on og.mid=m.id ".$whereOn." GROUP BY m.id having ".$whereStr. ' order by '.$order." LIMIT ".$page*$limit.",".$limit);
//dd(Db::getLastSql());
                foreach($data as $k=>$v){
                    $data[$k]['levelname'] = $levelArr[$v['levelid']];
                    $data[$k]['teamyeji'] = $v['teamyeji'] ?? 0;

                    if($v['pid']){
                        $parent = Db::name('member')->where('aid',aid)->where('id',$v['pid'])->find();
                    }else{
                        $parent = array();
                    }
                    $data[$k]['parent'] = $parent;
                    $data[$k]['money'] = \app\commons\Member::getmoney($v);
                    $data[$k]['score'] = \app\commons\Member::getscore($v);

                }
                return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
            }

            View::assign('aid',aid);
            View::assign('levelArr',$levelArr);
            $sort = true;

            View::assign('sort',$sort);

            return View::fetch();
        }
    }

    public function fenhongGudongGive(){
        set_time_limit(0);
        ini_set('memory_limit', '2000M');

        if(getcustom('fenhong_gudong')){
            $sendmoney = input('param.sendmoney');
            if(!$sendmoney || $sendmoney <=0) return json(['status'=>0,'msg'=>'请输入正确的发放金额']);
            $datawhere = input('post.datawhere/a');
            $sysset = Db::name('admin_set')->where('aid',aid)->find();
            //多商户的商品是否参与分红
            $whereStr = $whereStr2 = ' ';
            $whereOn = ' ';
            $whereStr .= 'm.aid = '.aid ;//.' and teamyeji > 0 ';
            $whereStr2 .= ' and og2.status in (1,2,3) ';
            if($sysset['fhjiesuanbusiness'] != 1){
                $whereOn .= ' and og.bid = 0';
            }
            if($datawhere['ctime']){
                $ctime = explode(' ~ ',$datawhere['ctime']);
                $time1 = strtotime($ctime[0]);
                $time2 = strtotime($ctime[1]) + 86399;
                $whereStr2 .= ' and og2.createtime between '.$time1.' and '.$time2;
            }
            if($datawhere['yeji1'] && $datawhere['yeji2']){
                $yeji1 = $datawhere['yeji1'];
                $yeji2 = $datawhere['yeji2'];
                $whereStr .= ' and teamyeji between '.$yeji1.' and '.$yeji2;
            }
            if($datawhere['levelid']){
                $whereStr .= ' and m.levelid = '.$datawhere['levelid'];
            }
//                dd($whereStr);
            $data = Db::query("SELECT levelid,og.createtime,m.aid,og.bid,og.mid,m.id,og.status,m.nickname,m.headimg,m.platform,m.pid,m.realname,m.tel,m.money,m.score,m.commission,m.buymoney,m.levelid,
       (SELECT sum(real_totalprice) FROM ".table_name('shop_order_goods')." as og2 LEFT JOIN ".table_name('member')." as m2 on og2.mid=m2.id where (FIND_IN_SET(m.id,m2.path) or og2.mid = m.id) ".$whereStr2.") as teamyeji
       FROM ".table_name('shop_order_goods')." as og RIGHT JOIN ".table_name('member')." as m on og.mid=m.id ".$whereOn." GROUP BY m.id having ".$whereStr);
//dump($data);
//            dd(Db::getLastSql());
            $count = count($data);
            $perMoney = floor($sendmoney / $count *100)/100;
            foreach($data as $k=>$v){
                $commission = dd_money_format($perMoney);
                $remark = '后台发放'.t('股东分红');
                $type = 'fenhong';
                if($commission > 0) {
                    $fhdata = [];
                    $fhdata['aid'] = aid;
                    $fhdata['mid'] = $v['id'];
                    $fhdata['commission'] = $commission;
                    $fhdata['remark'] = $remark;
                    $fhdata['type'] = $type;
                    $fhdata['createtime'] = time();
                    Db::name('member_fenhonglog')->insert($fhdata);
                }
                \app\commons\Member::addcommission(aid,$v['id'],0,$commission,$remark,1,$type);
            }
            \app\commons\System::plog('后台发放'.t('股东分红'));
            return json(['status'=>1,'msg'=>'发放完成']);
        }

    }

    //冻结佣金记录
    public function xiaofei(){
	    if(getcustom('commission_xiaofei')){
            if(request()->isAjax()){
                $page = input('param.page');
                $limit = input('param.limit');
                if(input('param.field') && input('param.order')){
                    $order = 'l.'.input('param.field').' '.input('param.order');
                }else{
                    $order = 'l.id desc';
                }
                $where = [];
                $where[] = ['l.aid','=',aid];

                if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
                if(input('param.mid')) $where[] = ['l.mid','=',trim(input('param.mid'))];
                if(input('?param.status') && input('param.status')!=='') $where[] = ['l.status','=',input('param.status')];
                $count = 0 + Db::name('member_xiaofei_money_log')
                        ->alias('l')
                        ->field('member.nickname,member.headimg,l.*')
                        ->join('member member','member.id=l.mid')
                        ->where($where)->count();
                $data = Db::name('member_xiaofei_money_log')
                    ->alias('l')
                    ->field('member.nickname,member.headimg,l.*')
                    ->join('member member','member.id=l.mid')
                    ->where($where)->page($page,$limit)->order($order)->select()->toArray();

                foreach($data as $k=>$v){

                    if($v['frommid']){
                        $frommember = Db::name('member')->where('id',$v['frommid'])->find();
                        if($frommember){
                            $data[$k]['fromheadimg'] = $frommember['headimg'];
                            $data[$k]['fromnickname'] = $frommember['nickname'];
                        }
                    }
                }
                return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
            }
            return View::fetch();
        }
    }

    //冻结佣金导出
    public function xiaofeilogexcel(){
        if(getcustom('commission_xiaofei')) {
            if (input('param.field') && input('param.order')) {
                $order = 'l.' . input('param.field') . ' ' . input('param.order');
            } else {
                $order = 'l.id desc';
            }
            $page = input('param.page')?:1;
            $limit = input('param.limit')?:10;
            $where = [];
            $where[] = ['l.aid', '=', aid];

            if (input('param.nickname')) $where[] = ['member.nickname', 'like', '%' . trim(input('param.nickname')) . '%'];
            if (input('param.mid')) $where[] = ['l.mid', '=', trim(input('param.mid'))];
            $list = Db::name('member_xiaofei_money_log')
                ->alias('l')
                ->field('member.nickname,member.headimg,l.*')
                ->join('member member', 'member.id=l.mid')->where($where)->order($order)->page($page,$limit)->select()->toArray();
            $count = Db::name('member_xiaofei_money_log')
                ->alias('l')
                ->field('member.nickname,member.headimg,l.*')
                ->join('member member', 'member.id=l.mid')->where($where)->count();
            $title = array();
            $title[] = t('会员') . '信息';
            $title[] = '变更金额';
            $title[] = '变更后金额';
            $title[] = '变更时间';
            $title[] = '备注';
            $data = array();
            $moeny_weishu = 2;
            if (getcustom('fenhong_money_weishu')) {
                $moeny_weishu = Db::name('admin_set')->where('aid', aid)->value('fenhong_money_weishu');
            }
            foreach ($list as $v) {
                $tdata = array();
                $tdata[] = $v['nickname'] . '(' . t('会员') . 'ID:' . $v['mid'] . ')';
                $tdata[] = dd_money_format($v['commission'], $moeny_weishu);
                $tdata[] = dd_money_format($v['after'], $moeny_weishu);
                $tdata[] = date('Y-m-d H:i:s', $v['createtime']);
                $tdata[] = $v['remark'];
                $data[] = $tdata;
            }
            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'title'=>$title]);
            $this->export_excel($title, $data);
        }
    }

    //冻结佣金记录删除
    public function xiaofeilogdel(){
        if(getcustom('commission_xiaofei')) {
            $ids = input('post.ids/a');
            Db::name('member_xiaofei_money_log')->where('aid', aid)->where('id', 'in', $ids)->delete();
            \app\commons\System::plog('删除冻结佣金明细' . implode(',', $ids));
            return json(['status' => 1, 'msg' => '删除成功']);
        }
    }

    public function toscoreset(){
	    if(getcustom('commission_to_score')){
            if(request()->isPost()){
                $info = input('post.info/a');
                $rs = Db::name('admin_set')->where('aid',aid)->find();
                if($rs){
                    Db::name('admin_set')->where('aid',aid)->update($info);
                }else{
                    $info['aid'] = aid;
                    Db::name('admin_set')->where('aid',aid)->insert($info);
                }
                return json(['status'=>1,'msg'=>'设置成功']);
            }else{
                $s_time = strtotime(date('Y-m-d 00:00:00'));
                $e_time = $s_time+86400;
                $yesterday_time = $s_time-86400;
                //查询累计利润
                $where = [];
                $where[] = ['aid','=',aid];
                $where[] = ['status','in','1,2,3'];
                $where[] = ['refund_num','=',0];
                $orders = Db::name('shop_order_goods')->where($where)->field('sum( real_totalprice - cost_price * num) cost_price_total')->find();

                $lj_shouyi = $orders['cost_price_total'];
                //查询今日利润
                $where[] = ['paytime','between',[$s_time,$e_time]];
                $orders = Db::name('shop_order_goods')->where($where)->field('sum(real_totalprice - cost_price * num) cost_price_total')->find();
                $today_shouyi = $orders['cost_price_total'];
                //查询昨日利润
                $where = [];
                $where[] = ['aid','=',aid];
                $where[] = ['status','in','1,2,3'];
                $where[] = ['refund_num','=',0];
                $where[] = ['paytime','between',[$yesterday_time,$s_time]];
                $orders = Db::name('shop_order_goods')->where($where)->field('sum(real_totalprice - cost_price * num) cost_price_total')->find();
                $yesterday_shouyi = $orders['cost_price_total'];

                $score_weishu = 0;
                if(getcustom('score_weishu')){
                    $score_weishu = Db::name('admin_set')->where('aid',aid)->value('score_weishu');
                    $score_weishu = $score_weishu?$score_weishu:0;
                }
                //查询累计积分
                $lj_score = Db::name('member')->where('aid',aid)->sum('score');
                //查询今日新增积分
                $today_score = Db::name('member_scorelog')
                    ->where('aid',aid)
                    ->where('score','>',0)
                    ->where('createtime','between',[$s_time,$e_time])
                    ->sum('score');
                //查询昨日新增积分
                $yesterday_score = Db::name('member_scorelog')
                    ->where('aid',aid)
                    ->where('score','>',0)
                    ->where('createtime','between',[$yesterday_time,$s_time])
                    ->sum('score');
                //查询累计佣金
                $lj_commission = Db::name('member')->where('aid',aid)->sum('commission');
                //查询今日新增佣金
                $today_commission = Db::name('member_commissionlog')
                    ->where('aid',aid)
                    ->where('commission','>',0)
                    ->where('createtime','between',[$s_time,$e_time])
                    ->sum('commission');
                //查询昨日新增佣金
                $yesterday_commission = Db::name('member_commissionlog')
                    ->where('aid',aid)
                    ->where('commission','>',0)
                    ->where('createtime','between',[$yesterday_time,$s_time])
                    ->sum('commission');

                $rt = [];
                $rt['lj_shouyi'] = $lj_shouyi;
                $rt['today_shouyi'] = $today_shouyi;
                $rt['yesterday_shouyi'] = $yesterday_shouyi;
                $rt['lj_score'] = dd_money_format($lj_score,$score_weishu);
                $rt['today_score'] = dd_money_format($today_score,$score_weishu);
                $rt['yesterday_score'] = dd_money_format($yesterday_score,$score_weishu);
                $rt['lj_commission'] = $lj_commission;
                $rt['today_commission'] = $today_commission;
                $rt['yesterday_commission'] = $yesterday_commission;
                View::assign('rt',$rt);

                $info = Db::name('admin_set')->where('aid',aid)->find();
                View::assign('info',$info);
                return View::fetch();
            }
        }
    }
    //明细
    public function toscorelog(){
        $score_weishu = 0;
        if(getcustom('score_weishu')){
            $score_weishu = Db::name('admin_set')->where('aid',aid)->value('score_weishu');
            $score_weishu = $score_weishu?$score_weishu:0;
        }
        if(getcustom('commission_to_score')){
            if(request()->isAjax()){
                $page = input('param.page');
                $limit = input('param.limit');
                if(input('param.field') && input('param.order')){
                    $order = 'l.'.input('param.field').' '.input('param.order');
                }else{
                    $order = 'l.id desc';
                }
                $where = array();
                $where[] = ['l.aid','=',aid];

                if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
                if(input('param.mid')) $where[] = ['l.mid','=',trim(input('param.mid'))];
                if(input('param.ctime') ){
                    $ctime = explode(' ~ ',input('param.ctime'));
                    $where[] = ['l.w_time','>=',strtotime($ctime[0])];
                    $where[] = ['l.w_time','<',strtotime($ctime[1]) + 86400];
                }
                $count = 0 + Db::name('commission_toscore_log')
                        ->alias('l')
                        ->field('member.nickname,member.headimg,l.*')
                        ->join('member member','member.id=l.mid')
                        ->where($where)->count();
                $data = Db::name('commission_toscore_log')
                    ->alias('l')
                    ->field('member.nickname,member.headimg,l.*')
                    ->join('member member','member.id=l.mid')
                    ->where($where)->page($page,$limit)->order($order)->select()->toArray();
                foreach($data as $k=>$v){
                    $data[$k]['commission'] = dd_money_format($v['commission'],$score_weishu);
                    $data[$k]['commission_total'] = dd_money_format($v['commission_total'],$score_weishu);
                    $data[$k]['num'] = dd_money_format($v['num'],$score_weishu);
                }
                //汇总累计补贴
                $butie_total_arr = Db::name('commission_toscore_log')
                    ->alias('l')
                    ->field('member.nickname,member.headimg,l.*')
                    ->join('member member','member.id=l.mid')
                    ->where($where)->group('l.w_day')->column('butie_num');
                $butie_total = array_sum($butie_total_arr);
                $butie_total = bcmul($butie_total,1,2);
                //汇总转换数量
                $num_total = Db::name('commission_toscore_log')
                    ->alias('l')
                    ->field('member.nickname,member.headimg,l.*')
                    ->join('member member','member.id=l.mid')
                    ->where($where)->sum('num');
                $num_total = dd_money_format($num_total,$score_weishu);
                $totalRow = ['butie_total'=>$butie_total,'num_total'=>$num_total];
                return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'totalRow'=>$totalRow,'butie_total'=>$butie_total,'num_total'=>$num_total]);
            }
            return View::fetch();
        }
    }
    public function toscore(){
        if(getcustom('commission_to_score')) {
            Db::startTrans();
            $page = input('pagenum');
            $limit = input('pagelimit');
            $last_mid = input('last_mid')?:0;
            $sysset = Db::name('admin_set')->where('aid', aid)->find();
            if ($sysset['commission_to_score_time'] == 0) {
                $res = ['status' => 0, 'msg' => '不可手动释放！'];
                return json($res);
            }
            //查询今日是否有转换记录
            $today = date('Ymd', time());
            $exit = Db::name('commission_toscore_log')->where('aid', $sysset['aid'])->where('w_day', $today)->find();
//        if(!$exit){
            $where = [];
            $where[] = ['aid','=',$sysset['aid']];
            $where[] = ['score','>',0];
            //计算全网总佣金
            $commission_total = Db::name('member')->where($where)->field('id,commission,score')->sum('score');
            $res = \app\commons\Member::commission_to_score($sysset,$page,$limit,$last_mid,$commission_total);
            $where = [];
            $where[] = ['aid','=',$sysset['aid']];
            $where[] = ['score','>',0];
            $total = Db::name('member')->where($where)->field('id,commission,score')->page(1,$limit)->order('id asc')->count();
            $sucnum = $res['sucnum']>0? $res['sucnum']:$total;
            $percent = bcdiv($sucnum,$total,2);
            $percent = $percent*100;
            $res['percent'] = $percent;
//        }else{
//            $res = ['status'=>0,'msg'=>'今日已释放过'];
//        }
            Db::commit();
            return json($res);
        }
    }

    //回本股东分红汇总
    public function fenhongHuiben(){
	    if(getcustom('fenhong_gudong_huiben')){
            if(request()->isAjax()){
                $page = input('param.page');
                $limit = input('param.limit');
                if(input('param.field') && input('param.order')){
                    $order = 'f.'.input('param.field').' '.input('param.order');
                }else{
                    $order = 'f.id desc';
                }
                $where = [];
                $where[] = ['f.aid','=',aid];

                if(input('param.mid')) $where[] = ['f.mid','=',trim(input('param.mid'))];

                $count = 0 + Db::name('fenhong_huiben')->alias('f')->where($where)->count();
                $data = Db::name('fenhong_huiben')->alias('f')->where($where)->page($page,$limit)->order($order)->select()->toArray();
                $moeny_weishu = 2;
                if(getcustom('fenhong_money_weishu')){
                    $moeny_weishu = Db::name('admin_set')->where('aid',aid)->value('fenhong_money_weishu');
                }
                $level_names = Db::name('member_level')->where('aid',aid)->column('name','id');
                foreach ($data as $key=>$val){
                    $data[$key]['commission']  =  dd_money_format($val['commission'],$moeny_weishu);
                    $data[$key]['level_name']  =  $level_names[$val['levelid']];
                }
                return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
            }
            return View::fetch();
        }

    }

    public function areafenhong()
    {
        if(getcustom('member_areafenhong_pc')){
            $st = input('param.st/d',1);
            $moeny_weishu = 2;
            if (getcustom('fenhong_money_weishu')) {
                $moeny_weishu = Db::name('admin_set')->where('aid', aid)->value('fenhong_money_weishu');
            }
            if(input('param.mid')){
                $mid = input('param.mid');
            }else{
                $mid = $this->user['mid'];
            }
            if (request()->isAjax()) {
                $pernum = input('param.limit', 10);
                $pagenum = input('param.page') ? input('param.page') : 1;
                //待结算
                $sysset = Db::name('admin_set')->where('aid',aid)->find();
                $newoglist = [];
                $datalist =  [];
                $count = 0;
                $commissionyj = 0;
                if ($st == 1) {
                    $rs = \app\commons\Fenhong::areafenhong(aid, $sysset, [], 0, time(), 1, $mid);
                    $newoglist = $rs['oglist'];
                    foreach ($newoglist as $k=>$og){
                        $goodshtml = '<div class="flex">' .
                            '<div><img height="40" width="40" style="margin-right: 6px" src="'.$og['pic'].'"></div>' .
                            '<div><p>'.$og['name']. ' <span style="color: #fe5b07"> x '.$og['num'].'</span></p>
<p  style="color: #fe5b07">￥' . $og['real_totalprice'] .'</p></div>'.
                            '</div>';
                        $newoglist[$k]['ogdata'] = $goodshtml;
                        if(!$og['commission']){
                            $newoglist[$k]['commission'] = 0;
                        }
                    }
                    $commissionyj = $rs['commissionyj'];
                }
                if ($st == 2) {//已结算
                    $where = [];
                    $where[] = ['aid', '=', aid];
                    $where[] = ['mid', '=', $mid];
                    $where[] = ['type', '=', 'areafenhong'];
                    $where[] = ['id', '=', '4532'];
                    if (!$pagenum) $pagenum = 1;
                    $count = Db::name('member_fenhonglog')->where($where)->count();
                    $datalist = Db::name('member_fenhonglog')->where($where)->page($pagenum, $pernum)->order('id desc')->select()->toArray();
                    if (!$datalist) $datalist = [];
                    foreach ($datalist as $k => $v) {
                        if ($v['ogids']) {
                            if ($v['module'] == 'yuyue') {
                                $oglist = Db::name('yuyue_order')->alias('og')->join('member m', 'og.mid=m.id')->field('og.ordernum,og.proname name,og.propic pic,og.num,og.totalprice real_totalprice,og.createtime,og.status,m.nickname,m.headimg')->where('og.id', 'in', $v['ogids'])->select()->toArray();
                            } elseif ($v['module'] == 'luckycollage') {
                                $oglist = Db::name('lucky_collage_order')->alias('og')->join('member m', 'og.mid=m.id')->field('og.ordernum,og.proname name,og.propic pic,og.num,og.totalprice real_totalprice,og.createtime,og.status,m.nickname,m.headimg')->where('og.id', 'in', $v['ogids'])->select()->toArray();
                            } elseif ($v['module'] == 'scoreshop') {
                                $oglist = Db::name('scoreshop_order_goods')->alias('og')->join('member m', 'og.mid=m.id')->field('og.ordernum,og.name,og.pic,og.num,og.totalmoney real_totalprice,og.createtime,og.status,m.nickname,m.headimg')->where('og.id', 'in', $v['ogids'])->select()->toArray();
                            } elseif ($v['module'] == 'kecheng') {
                                $oglist = Db::name('kecheng_order')->alias('og')->join('member m', 'og.mid=m.id')->field('og.ordernum,og.title as name,og.pic,"1" as num,og.totalprice real_totalprice,og.createtime,og.status,m.nickname,m.headimg')->where('og.id', 'in', $v['ogids'])->select()->toArray();
                            } elseif ($v['module'] == 'maidan') {
                                $oglist = Db::name('maidan_order')->alias('og')->join('member m', 'og.mid=m.id')->field('og.ordernum,og.title as name,"" as pic,"1" as num,og.paymoney real_totalprice,og.createtime,og.status,m.nickname,m.headimg')->where('og.id', 'in', $v['ogids'])->select()->toArray();
                            } elseif ($v['module'] == 'shop' || empty($v['module'])) {
                                $oglist = Db::name('shop_order_goods')->alias('og')->join('member m', 'og.mid=m.id')->field('og.ordernum,og.name,og.pic,og.num,og.real_totalprice,og.createtime,og.status,m.nickname,m.headimg')->where('og.id', 'in', $v['ogids'])->select()->toArray();
                            } else {
                                $oglist = [];
                            }
                        } else {
                            $oglist = [];
                        }
                        $goodshtml = '';
                        foreach($oglist as $og) {
                            $goodshtml = '<div class="flex">' .
                                '<div><img height="40" width="40" style="margin-right: 6px" src="'.$og['pic'].'"></div>' .
                                '<div><p>'.$og['name']. '  <span style="color: #fe5b07"> x '.$og['num'].'</span></p>
<p  style="color: #fe5b07">￥' . $og['real_totalprice'] .'</p></div>'.
                                '</div>';
                        }
                        $datalist[$k]['ogdata'] = $goodshtml;
                        $datalist[$k]['oglist'] = $oglist;
                        $datalist[$k]['commission'] = dd_money_format($v['commission'], $moeny_weishu);
                    }
                }
                $rdata = [];
                $rdata['count'] = $count + count($newoglist);
                $rdata['commissionyj'] = dd_money_format($commissionyj, $moeny_weishu);
                if ($st == 1) {
                    $rdata['data'] = $newoglist;
                } else {
                    $rdata['data'] = $datalist;
                }
                $rdata['code'] = 0;
                $rdata['st'] = $st;
                return json($rdata);
            }
            $areafenhong = dd_money_format(Db::name('member_fenhonglog')->where('aid',aid)->where('mid',mid)->where('type','areafenhong')->sum('commission'),$moeny_weishu);
            $rs = \app\commons\Fenhong::areafenhong(aid,$this->sysset,[],0,time(),1,mid);
            $areafenhong_yj = dd_money_format($rs['commissionyj'],$moeny_weishu);
            $datawhere = input('param.');
            if(empty($datawhere)) $datawhere = ['t'=>time()];
            View::assign('mid',$mid);
            View::assign('st',$st);
            View::assign('areafenhong',$areafenhong);
            View::assign('areafenhong_yj',$areafenhong_yj);
            View::assign('datawhere',json_encode($datawhere));
            return View::fetch();
        }
    }

    //佣金上限明细
    public function maxlog(){
	    if(getcustom('member_commission_max')){
            if(request()->isAjax()){
                $page = input('param.page');
                $limit = input('param.limit');
                if(input('param.field') && input('param.order')){
                    $order = 'l.'.input('param.field').' '.input('param.order');
                }else{
                    $order = 'l.id desc';
                }
                $where = [];
                $where[] = ['l.aid','=',aid];

                if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
                if(input('param.mid')) $where[] = ['l.mid','=',trim(input('param.mid'))];
                if(input('?param.status') && input('param.status')!=='') $where[] = ['l.status','=',input('param.status')];
                if(input('?param.in_type') && input('param.in_type')!==''){
                    $where[] = ['l.in_type','=',input('param.in_type')];
                }

                $count = 0 + Db::name('member_commissionmax_log')->alias('l')->field('member.nickname,member.headimg,l.*')
                        ->join('member member','member.id=l.mid')->where($where)->count();
                $data = Db::name('member_commissionmax_log')->alias('l')->field('member.nickname,member.headimg,l.*')
                    ->join('member member','member.id=l.mid')->where($where)->page($page,$limit)->order($order)->select()->toArray();
                foreach ($data as $key=>$val){

                }
                return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
            }
            return View::fetch();
        }
    }
    //佣金上限记录删除
    public function commission_max_del(){
        if(getcustom('member_commission_max')) {
            $ids = input('post.ids/a');
            Db::name('member_commissionmax_log')->where('aid', aid)->where('id', 'in', $ids)->delete();
            \app\commons\System::plog('删除佣金上限记录' . implode(',', $ids));
            return json(['status' => 1, 'msg' => '删除成功']);
        }
    }
}
