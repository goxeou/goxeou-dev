<?php


// +----------------------------------------------------------------------
// | 商户 - 余额管理
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class ExpertMoney extends Common
{
	//余额明细
    public function moneylog(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = 'expert_moneylog.'.input('param.field').' '.input('param.order');
			}else{
				$order = 'expert_moneylog.id desc';
			}
			$where = [];
			$where[] = ['expert_moneylog.aid','=',aid];
			if(bid != 0){
				$where[] = ['expert_moneylog.bid','=',bid];
			}else{
				if(input('param.bid')) $where[] = ['expert_moneylog.bid','=',trim(input('param.bid'))];
               
			}
			if(input('param.name')) $where[] = ['expert.name','like','%'.trim(input('param.name')).'%'];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['expert_moneylog.status','=',input('param.status')];
			$count = 0 + Db::name('expert_moneylog')->alias('expert_moneylog')->field('expert.name,expert_moneylog.*')->join('expert expert','expert.id=expert_moneylog.bid')->where($where)->count();
			$data = Db::name('expert_moneylog')->alias('expert_moneylog')->field('expert.name,expert_moneylog.*')->join('expert expert','expert.id=expert_moneylog.bid')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			if(getcustom('hmy_yuyue')){
				foreach($data as &$d){
					 if (strpos($d['remark'], '/')) {
						$workerarr = explode('/',$d['remark']);	
						$d['worker'] = $workerarr[1];
					 }
				}	
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
    }
	//余额明细导出
	public function moneylogexcel(){
		if(input('param.field') && input('param.order')){
			$order = 'expert_moneylog.'.input('param.field').' '.input('param.order');
		}else{
			$order = 'expert_moneylog.id desc';
		}
		$where = [];
		$where[] = ['expert_moneylog.aid','=',aid];
		if(bid != 0){
			$where[] = ['expert_moneylog.bid','=',bid];
		}else{
			if(input('param.bid')) $where[] = ['expert_moneylog.bid','=',trim(input('param.bid'))];
		}
		if(input('param.name')) $where[] = ['expert.name','like','%'.trim(input('param.name')).'%'];
		if(input('?param.status') && input('param.status')!=='') $where[] = ['expert_moneylog.status','=',input('param.status')];
		$list = Db::name('expert_moneylog')->alias('expert_moneylog')->field('expert.name,expert_moneylog.*')->join('expert expert','expert.id=expert_moneylog.bid')->where($where)->order($order)->select()->toArray();
		$title = array();
		$title[] = '商户名称';
		$title[] = '变更金额';
		$title[] = '变更后剩余';
		$title[] = '变更时间';
		$title[] = '备注';
		$data = array();
		foreach($list as $v){
			$tdata = array();
			$tdata[] = $v['name'];
			$tdata[] = $v['money'];
			$tdata[] = $v['after'];
			$tdata[] = date('Y-m-d H:i:s',$v['createtime']);
			$tdata[] = $v['remark'];
			$data[] = $tdata;
		}
		$this->export_excel($title,$data);
	}
	//余额明细改状态
	public function moneylogsetst(){
		if(bid > 0) showmsg('无操作权限');
		$ids = input('post.ids/a');
        $st = input('post.st/d');
		Db::name('expert_moneylog')->where('aid',aid)->where('id','in',$ids)->update(['status'=>$st]);
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	//余额明细删除
	public function moneylogdel(){
		if(bid > 0) showmsg('无操作权限');
		$ids = input('post.ids/a');
		Db::name('expert_moneylog')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('删除商户余额明细'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}

	//提现记录
	public function withdrawlog(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = 'expert_withdrawlog.'.input('param.field').' '.input('param.order');
			}else{
				$order = 'expert_withdrawlog.id desc';
			}
			$where = [];
			$where[] = ['expert_withdrawlog.aid','=',aid];
			if(bid != 0){
				$where[] = ['expert_withdrawlog.bid','=',bid];
			}else{
				if(input('param.bid')) $where[] = ['expert_withdrawlog.bid','=',trim(input('param.bid'))];
			}
			if(input('param.name')) $where[] = ['expert.name','like','%'.trim(input('param.name')).'%'];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['expert_withdrawlog.status','=',input('param.status')];
			$count = 0 + Db::name('expert_withdrawlog')->alias('expert_withdrawlog')->field('expert.mid,expert.name,expert_withdrawlog.*')->join('expert expert','expert.id=expert_withdrawlog.bid')->where($where)->count();
			$data = Db::name('expert_withdrawlog')->alias('expert_withdrawlog')->field('expert.mid,expert.name,expert_withdrawlog.*')->join('expert expert','expert.id=expert_withdrawlog.bid')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			foreach($data as $k=>$v){
				$mid = Db::name('admin_user')->where('aid',aid)->where('bid',$v['bid'])->where('isadmin',1)->value('mid');
				if($mid){
					$member = Db::name('member')->where('aid',aid)->where('id',$mid)->find();
					$data[$k]['headimg'] = $member['headimg'];
					$data[$k]['nickname'] = $member['nickname'];
				}else{
					$data[$k]['headimg'] = '';
					$data[$k]['nickname'] = '';
				}
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
    }
	//提现记录导出
	public function withdrawlogexcel(){
		if(input('param.field') && input('param.order')){
			$order = 'expert_withdrawlog.'.input('param.field').' '.input('param.order');
		}else{
			$order = 'expert_withdrawlog.id desc';
		}
        $page = input('param.page')?:1;
        $limit = input('param.limit')?:10;
		$where = [];
		$where[] = ['expert_withdrawlog.aid','=',aid];
		if(bid != 0){
			$where[] = ['expert_withdrawlog.bid','=',bid];
		}else{
			if(input('param.bid')) $where[] = ['expert_withdrawlog.bid','=',trim(input('param.bid'))];
		}
		if(input('param.name')) $where[] = ['expert.name','like','%'.trim(input('param.name')).'%'];
		if(input('?param.status') && input('param.status')!=='') $where[] = ['expert_withdrawlog.status','=',input('param.status')];
		$list = Db::name('expert_withdrawlog')->alias('expert_withdrawlog')->field('expert.name,expert_withdrawlog.*')
            ->join('expert expert','expert.id=expert_withdrawlog.bid')
            ->where($where)->order($order)->page($page,$limit)->select()->toArray();
        $count = Db::name('expert_withdrawlog')->alias('expert_withdrawlog')->field('expert.name,expert_withdrawlog.*')
            ->join('expert expert','expert.id=expert_withdrawlog.bid')
            ->where($where)->order($order)->count();
		$title = array();
		$title[] = '商户名称';
		$title[] = '提现金额';
		$title[] = '打款金额';
		$title[] = '提现方式';
		$title[] = '收款账号';
		$title[] = '提现时间';
		$title[] = '状态';
		$data = array();
		foreach($list as $v){
			$tdata = array();
			$tdata[] = $v['name'];
			$tdata[] = $v['txmoney'];
			$tdata[] = $v['money'];
			$tdata[] = $v['paytype'];
			if($v['paytype'] == '支付宝'){
				$tdata[] = $v['aliaccount'];
			}elseif($v['paytype'] == '银行卡'){
				$tdata[] = $v['bankname'] . ' - ' .$v['bankcarduser']. ' - '.$v['bankcardnum'];
			}elseif($v['paytype'] == '微信'){
				$tdata[] = $v['weixin'];
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
		if(bid > 0) showmsg('无操作权限');
		$id = input('post.id/d');
		$st = input('post.st/d');
		$reason = input('post.reason');
		$info = Db::name('expert_withdrawlog')->where('aid',aid)->where('id',$id)->find();
        $info['money'] = dd_money_format($info['money']);
        $info['txmoney'] = dd_money_format($info['txmoney']);
		$mid = Db::name('admin_user')->where('aid',aid)->where('bid',$info['bid'])->where('isadmin',1)->value('mid');
		if($st==10){//微信打款
			if($info['status']!=1) return json(['status'=>0,'msg'=>'已审核状态才能打款']);
			if(!$mid) return json(['status'=>0,'msg'=>'商户未绑定微信']);
			$rs = \app\commons\Wxpay::transfers(aid,$mid,$info['money'],$info['ordernum'],'','余额提现');
			if($rs['status']==0){
				return json(['status'=>0,'msg'=>$rs['msg']]);
			}else{
				Db::name('expert_withdrawlog')->where('aid',aid)->where('id',$id)->update(['status'=>3,'reason'=>$reason,'paytime'=>time(),'paynum'=>$rs['resp']['payment_no']]);
				//提现成功通知
				$tmplcontent = [];
				$tmplcontent['first'] = '您的提现申请已打款，请留意查收';
				$tmplcontent['remark'] = '请点击查看详情~';
				$tmplcontent['money'] = (string) $info['money'];
				$tmplcontent['timet'] = date('Y-m-d H:i',$info['createtime']);
                $tempconNew = [];
                $tempconNew['amount2'] = (string) round($info['money'],2);//提现金额
                $tempconNew['time3'] = date('Y-m-d H:i',$info['createtime']);//提现时间
				\app\commons\Wechat::sendtmpl(aid,$mid,'tmpl_tixiansuccess',$tmplcontent,m_url('admin/index/index'),$tempconNew);
				//短信通知
				$member = Db::name('member')->where('id',$mid)->find();
				if($member['tel']){
					$tel = $member['tel'];
					\app\commons\Sms::send(aid,$tel,'tmpl_tixiansuccess',['money'=>$info['money']]);
				}
				\app\commons\System::plog('商家提现微信打款'.$id);
				return json(['status'=>1,'msg'=>$rs['msg']]);
			}
		}else if($st == 20){
            if(getcustom('pay_adapay')){
                if($info['status']!=1) return json(['status'=>0,'msg'=>'已审核状态才能打款']);
                $adapay = Db::name('adapay_member')->where('aid',aid)->where('mid',$mid)->find();
                $rs = \app\customs\AdapayPay::balancePay(aid,'h5',$adapay['member_id'],$info['ordernum'],$info['money']);
                if($rs['status'] == 0){
                    Db::name('expert_withdrawlog')->where('aid',aid)->where('id',$info['id'])->update(['reason'=>$rs['msg']]);
                    return json(['status'=>0,'msg'=>$rs['msg']]);
                }else{
                    //从用户余额中进行提现到银行卡
                    $drs = \app\customs\AdapayPay::drawcash(aid,'h5',$adapay['member_id'],$info['ordernum'],$info['money']);
                    if($drs['status'] == 0){
                        Db::name('expert_withdrawlog')->where('aid',aid)->where('id',$info['id'])->update(['reason'=>$drs['msg']]);
                        return json(['status'=>0,'msg'=>$drs['msg']]);
                    }

                    Db::name('expert_withdrawlog')->where('aid',aid)->where('id',$id)->update(['status'=>3,'paytime'=>time(),'paynum'=>$rs['data']['balance_seq_id'],'reason'=>'']);
                    //提现成功通知
                    $tmplcontent = [];
                    $tmplcontent['first'] = '您的提现申请已打款，请留意查收';
                    $tmplcontent['remark'] = '请点击查看详情~';
                    $tmplcontent['money'] = (string) round($info['money'],2);
                    $tmplcontent['timet'] = date('Y-m-d H:i',$info['createtime']);
                    $tempconNew = [];
                    $tempconNew['amount2'] = (string) round($info['money'],2);//提现金额
                    $tempconNew['time3'] = date('Y-m-d H:i',$info['createtime']);//提现时间
                    \app\commons\Wechat::sendtmpl(aid,$mid,'tmpl_tixiansuccess',$tmplcontent,m_url('admin/index/index'),$tempconNew);
                    //短信通知
                    $member = Db::name('member')->where('id',$mid)->find();
                    if($member['tel']){
                        $tel = $member['tel'];
                        \app\commons\Sms::send(aid,$tel,'tmpl_tixiansuccess',['money'=>$info['money']]);
                    }
                    \app\commons\System::plog('佣金提现汇付天下打款'.$id);
                    return json(['status'=>1,'msg'=>'已提交打款，请耐心等待']);
                }
            }
        }else if($st==30){
            if(getcustom('alipay_auto_transfer')){
                //支付宝打款
                if($info['status']!=1) return json(['status'=>0,'msg'=>'已审核状态才能打款']);
                //查询会员信息
                $expert = Db::name('expert')->where('id',$info['bid'])->field('aliaccount,aliaccountname')->find();
                if(!$expert){
                    return json(['status'=>0,'msg'=>t('商户').'不存在']);
                }
                if(empty($expert['aliaccount']) || empty($expert['aliaccountname']) ){
                    return json(['status'=>0,'msg'=>t('商户').'支付宝信息不完整']);
                }
                $rs = \app\commons\Alipay::transfers(aid,$info['ordernum'],$info['money'],t('余额').'提现',$expert['aliaccount'],$expert['aliaccountname'],t('余额').'提现');
                if($rs['status']==0){
                    return json(['status'=>0,'msg'=>$rs['msg']]);
                }else{
                    Db::name('expert_withdrawlog')->where('aid',aid)->where('id',$id)->update(['status'=>3,'paytime'=>time(),'paynum'=>$rs['pay_fund_order_id']]);
                    //提现成功通知
                    $tmplcontent = [];
                    $tmplcontent['first'] = '您的提现申请已打款，请留意查收';
                    $tmplcontent['remark'] = '请点击查看详情~';
                    $tmplcontent['money'] = (string) $info['money'];
                    $tmplcontent['timet'] = date('Y-m-d H:i',$info['createtime']);
                    $tempconNew = [];
                    $tempconNew['amount2'] = (string) round($info['money'],2);//提现金额
                    $tempconNew['time3'] = date('Y-m-d H:i',$info['createtime']);//提现时间
                    \app\commons\Wechat::sendtmpl(aid,$mid,'tmpl_tixiansuccess',$tmplcontent,m_url('admin/index/index'),$tempconNew);
                    //短信通知
                    $member = Db::name('member')->where('id',$mid)->find();
                    if($member['tel']){
                        $tel = $member['tel'];
                        \app\commons\Sms::send(aid,$tel,'tmpl_tixiansuccess',['money'=>$info['money']]);
                    }
                    \app\commons\System::plog('商家提现支付宝打款'.$id);
                    return json(['status'=>1,'msg'=>$rs['msg']]);
                }
            }
        }else{
			Db::name('expert_withdrawlog')->where('aid',aid)->where('id',$id)->update(['status'=>$st,'reason'=>$reason]);
			if($st == 2){//驳回返还余额
				\app\commons\expert::addmoney(aid,$info['bid'],$info['txmoney'],'余额提现返还');
				//提现失败通知
				$tmplcontent = [];
				$tmplcontent['first'] = '您的提现申请被商家驳回，可与商家协商沟通。';
				$tmplcontent['remark'] = $reason.'，请点击查看详情~';
				$tmplcontent['money'] = (string) $info['txmoney'];
				$tmplcontent['time'] = date('Y-m-d H:i',$info['createtime']);
				\app\commons\Wechat::sendtmpl(aid,$mid,'tmpl_tixianerror',$tmplcontent,m_url('admin/index/index'));
				//短信通知
				$member = Db::name('member')->where('id',$mid)->find();
				if($member['tel']){
					$tel = $member['tel'];
					$rs = \app\commons\Sms::send(aid,$tel,'tmpl_tixianerror',['reason'=>$reason]);
				}
				\app\commons\System::plog('商家提现驳回'.$id);
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
				\app\commons\Wechat::sendtmpl(aid,$mid,'tmpl_tixiansuccess',$tmplcontent,m_url('admin/index/index'),$tempconNew);
				//短信通知
				$member = Db::name('member')->where('id',$mid)->find();
				if($member['tel']){
					$tel = $member['tel'];
					$rs = \app\commons\Sms::send(aid,$tel,'tmpl_tixiansuccess',['money'=>$info['money']]);
				}
				\app\commons\System::plog('商家提现改为已打款'.$id);
			}
		}
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//提现记录删除
	public function withdrawlogdel(){
		if(bid > 0) showmsg('无操作权限');
		$ids = input('post.ids/a');
		Db::name('expert_withdrawlog')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('删除商家提现记录'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	
	//余额提现
	public function withdraw(){
		$expert = db('expert')->where(array('id'=>bid))->find();
		$bset = db('expert_sysset')->where(['aid'=>aid])->find();
		if(request()->isPost()){
			$info = input('post.info/a');
			$money = floatval($info['money']);
			if($money < $bset['withdrawmin']){
				return json(['status'=>0,'msg'=>'提现金额不能小于'.$bset['withdrawmin']]);
			}
			//if($money > 5000){
			//	return ['status'=>0,'msg'=>'单次提现金额不能大于5000'];
			//}
			if($expert['money'] < $money) return json(['status'=>0,'msg'=>'可提现余额不足']);
			$data = array();
			$data['aid'] = aid;
			$data['bid'] = bid;
			$data['txmoney'] = $money;
			$data['money'] = $money * (1-$bset['withdrawfee']*0.01);
			$data['paytype'] = $info['paytype'];
			$data['ordernum'] = date('YmdHis').rand(1000,9999);
			$data['createtime'] = time();
			$data['status'] = 0;
			if($data['paytype'] == '银行卡'){
				$data['bankname'] = $expert['bankname'];
				$data['bankcarduser'] = $expert['bankcarduser'];
				$data['bankcardnum'] = $expert['bankcardnum'];
				if($data['bankname']=='' || $data['bankcarduser']=='' || $data['bankcardnum']==''){
					return json(['status'=>0,'msg'=>'请填写完整提现信息','url'=>(string)url('Backstage/sysset')]);
				}
				db('expert')->where(['id'=>bid])->update(['bankname'=>$data['bankname'],'bankcarduser'=>$data['bankcarduser'],'bankcardnum'=>$data['bankcardnum']]);
			}
			if($data['paytype'] == '微信'){
				if($bset['commission_autotransfer']==1){
					\app\commons\expert::addmoney(aid,bid,-$money,'余额提现');
					$mid = Db::name('admin_user')->where('aid',aid)->where('bid',bid)->where('isadmin',1)->value('mid');
					if(!$mid) return json(['status'=>0,'msg'=>'商户主管理员未绑定微信']);
					$rs = \app\commons\Wxpay::transfers(aid,$mid,$data['money'],$data['ordernum'],'','余额提现');
					if($rs['status']==0){
						\app\commons\expert::addmoney(aid,bid,$money,'余额提现失败返还');
						return json(['status'=>0,'msg'=>$rs['msg']]);
					}else{
						$data['weixin'] = t('会员').'ID：'.$mid;
						$data['status'] = 3;
						$data['paytime'] = time();
						$data['paynum'] = $rs['resp']['payment_no'];
						$id = db('expert_withdrawlog')->insertGetId($data);

						//提现成功通知
						$tmplcontent = [];
						$tmplcontent['first'] = '您的提现申请已打款，请留意查收';
						$tmplcontent['remark'] = '请点击查看详情~';
						$tmplcontent['money'] = (string) $data['money'];
						$tmplcontent['timet'] = date('Y-m-d H:i',$data['createtime']);
                        $tempconNew = [];
                        $tempconNew['amount2'] = (string) round($data['money'],2);//提现金额
                        $tempconNew['time3'] = date('Y-m-d H:i',$data['createtime']);//提现时间
						\app\commons\Wechat::sendtmpl(aid,$mid,'tmpl_tixiansuccess',$tmplcontent,m_url('admin/index/index'),$tempconNew);
						//短信通知
						$member = Db::name('member')->where('id',$mid)->find();
						if($member['tel']){
							$tel = $member['tel'];
							\app\commons\Sms::send(aid,$tel,'tmpl_tixiansuccess',['money'=>$data['money']]);
						}
						\app\commons\System::plog('商家提现微信打款'.$id);
						return json(['status'=>1,'msg'=>$rs['msg'],'url'=>(string)url('withdrawlog')]);
					}
				}
				$data['weixin'] = $expert['weixin'];
				if($data['weixin']==''){
					return json(['status'=>0,'msg'=>'请填写完整提现信息','url'=>(string)url('Backstage/sysset')]);
				}
				//db('agent')->where(['agid'=>$agid])->update(['weixin'=>$data['weixin']]);
			}
			if($data['paytype'] == '支付宝'){
				$data['aliaccount'] = $expert['aliaccount'];
				if($data['aliaccount']==''){
					return json(['status'=>0,'msg'=>'请填写完整提现信息','url'=>(string)url('Backstage/sysset')]);
				}
			}
			if(getcustom('pay_adapay')){
                if($data['paytype'] == '汇付天下'){
                    //查询商家的管理员，判断管理员是否已绑定会员
                   $admin_user =  Db::name('admin_user')->where('aid',aid)->where('bid',bid)->where('status',1)->where('isadmin',1)->find();
                    if(!$admin_user || !$admin_user['mid']){
                        return json(['status'=>0,'msg'=>'请到[系统-管理员列表]对默认管理员进行会员信息绑定']);
                    }
                    $mid = $admin_user['mid'];
                    $adapay_member = Db::name('adapay_member')->where('aid',aid)->where('mid',$admin_user['mid'])->find(); 
                    if(!$adapay_member){
                        return json(['status'=>0,'msg'=>'请会员到小程序端[余额提现]绑定汇付天下的信息']);
                    }
                    //自动打款
                    if($bset['commission_autotransfer']==1){
                        $data['money'] = dd_money_format($data['money']);
                        $rs = \app\customs\AdapayPay::balancePay(aid,'h5',$adapay_member['member_id'],$data['ordernum'],$data['money']);
                        if($rs['status'] == 0){
                            return json(['status'=>1,'msg'=>'提交成功,请等待打款']);
                        }else{
                            //从用户余额中进行提现到银行卡
                            $drs = \app\customs\AdapayPay::drawcash(aid,'h5',$adapay_member['member_id'],$data['ordernum'],$data['money']);
                            if($drs['status'] == 0){
                                return json(['status'=>0,'msg'=>$drs['msg']]);
                            }
                            
                            $data['weixin'] = t('会员').'ID：'.$admin_user['mid'];
                            $data['status'] = 3;
                            $data['paytime'] = time();
                            $data['paynum'] = $rs['resp']['payment_no'];
                            $id = db('expert_withdrawlog')->insertGetId($data);

                            //提现成功通知
                            $tmplcontent = [];
                            $tmplcontent['first'] = '您的提现申请已打款，请留意查收';
                            $tmplcontent['remark'] = '请点击查看详情~';
                            $tmplcontent['money'] = (string) $data['money'];
                            $tmplcontent['timet'] = date('Y-m-d H:i',$data['createtime']);
                            $tempconNew = [];
                            $tempconNew['amount2'] = (string) round($data['money'],2);//提现金额
                            $tempconNew['time3'] = date('Y-m-d H:i',$data['createtime']);//提现时间
                            \app\commons\Wechat::sendtmpl(aid,$mid,'tmpl_tixiansuccess',$tmplcontent,m_url('admin/index/index'),$tempconNew);
                            //短信通知
                            $member = Db::name('member')->where('id',$mid)->find();
                            if($member['tel']){
                                $tel = $member['tel'];
                                \app\commons\Sms::send(aid,$tel,'tmpl_tixiansuccess',['money'=>$data['money']]);
                            }
                            \app\commons\System::plog('商家提现汇付天下打款'.$id);
                            return json(['status'=>1,'msg'=>$rs['msg'],'url'=>(string)url('withdrawlog')]);
                        }
                    }
                }
            }
			$id = db('expert_withdrawlog')->insertGetId($data);
			\app\commons\expert::addmoney(aid,bid,-$money,'余额提现');
			\app\commons\System::plog('商家余额提现申请'.$id);
			return json(['status'=>1,'msg'=>'提交成功','url'=>(string)url('withdrawlog')]);
		}
		View::assign('money',$expert['money']);
		View::assign('expert',$expert);
		View::assign('bset',$bset);
		return View::fetch();
	}
    //明细
    public function depositlog(){
       
        if(request()->isAjax()){
            $page = input('param.page');
            $limit = input('param.limit');
            if(input('param.field') && input('param.order')){
                $order = 'expert_depositlog.'.input('param.field').' '.input('param.order');
            }else{
                $order = 'expert_depositlog.id desc';
            }
            $where = [];
            $where[] = ['expert_depositlog.aid','=',aid];
            if(bid != 0){
                $where[] = ['expert_depositlog.bid','=',bid];
            }else{
                if(input('param.bid')) $where[] = ['expert_depositlog.bid','=',trim(input('param.bid'))];
            }
            if(input('param.name')) $where[] = ['expert.name','like','%'.trim(input('param.name')).'%'];
            if(input('?param.status') && input('param.status')!=='') $where[] = ['expert_depositlog.status','=',input('param.status')];
            $count = 0 + Db::name('expert_depositlog')->alias('expert_depositlog')->field('expert.name,expert_depositlog.*')->join('expert expert','expert.id=expert_depositlog.bid')->where($where)->count();
            $data = Db::name('expert_depositlog')->alias('expert_depositlog')->field('expert.name,expert_depositlog.*')->join('expert expert','expert.id=expert_depositlog.bid')->where($where)->page($page,$limit)->order($order)->select()->toArray();

            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
        }
        return View::fetch();
    

    }

    //明细删除
    public function depositlogdel(){
       
        $ids = input('post.ids/a');
        Db::name('expert_depositlog')->where('aid',aid)->where('id','in',$ids)->delete();
        \app\commons\System::plog('删除专家认证费明细'.implode(',',$ids));
        return json(['status'=>1,'msg'=>'删除成功']);
    }
    //明细导出
    public function depositlogexcel(){
        
        if(input('param.field') && input('param.order')){
            $order = 'expert_depositlog.'.input('param.field').' '.input('param.order');
        }else{
            $order = 'expert_depositlog.id desc';
        }
        $where = [];
        $where[] = ['expert_depositlog.aid','=',aid];
        if(bid != 0){
            $where[] = ['expert_depositlog.bid','=',bid];
        }else{
            if(input('param.bid')) $where[] = ['expert_depositlog.bid','=',trim(input('param.bid'))];
        }
        if(input('param.name')) $where[] = ['expert.name','like','%'.trim(input('param.name')).'%'];
        if(input('?param.status') && input('param.status')!=='') $where[] = ['expert_depositlog.status','=',input('param.status')];
        $list = Db::name('expert_depositlog')->alias('expert_depositlog')->field('expert.name,expert_depositlog.*')->join('expert expert','expert.id=expert_depositlog.bid')->where($where)->order($order)->select()->toArray();
        $title = array();
        $title[] = '商户名称';
        $title[] = '变更金额';
        $title[] = '变更后剩余';
        $title[] = '变更时间';
        $title[] = '备注';
        $data = array();
        foreach($list as $v){
            $tdata = array();
            $tdata[] = $v['name'];
            $tdata[] = $v['money'];
            $tdata[] = $v['after'];
            $tdata[] = date('Y-m-d H:i:s',$v['createtime']);
            $tdata[] = $v['remark'];
            $data[] = $tdata;
        }
        $this->export_excel($title,$data);
       
    }
}
