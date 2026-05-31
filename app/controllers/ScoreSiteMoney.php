<?php


// +----------------------------------------------------------------------
// | 积分管理
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class ScoreSiteMoney extends Common
{
    public function initialize(){
        parent::initialize();
    }
    //积分明细
    public function moneylog(){
        if(request()->isAjax()){
            $page = input('param.page');
            $limit = input('param.limit');
            if(input('param.field') && input('param.order')){
                $order = 'mml.'.input('param.field').' '.input('param.order');
            }else{
                $order = 'mml.id desc';
            }
            $mdidarr = Db::name('score_site')->where('aid',aid)->where('bid',bid)->column('id');
            $where = [];
            if($this->mdid){
                if(in_array($this->mdid,$mdidarr)){
                    $where[] = ['mml.mdid','=',$this->mdid];
                }else{
                    $where[] = ['mml.id','=',0];
                }
            }else{
                if(input('param.mdid')){
                    if(in_array(input('param.mdid'),$mdidarr)){
                        $where[] = ['mml.mdid','=',input('param.mdid')];
                    }else{
                        $where[] = ['mml.id','=',0];
                    }
                }else{
                    $where[] = ['mml.mdid','in',$mdidarr];
                }
            }
            $where[] = ['mml.aid','=',aid];
            if(input('param.name')) $where[] = ['mendian.name','like','%'.trim(input('param.name')).'%'];

            $count = 0 + Db::name('score_site_moneylog')
                ->alias('mml')
                ->join('score_site mendian','mendian.id=mml.mdid')
                ->where($where)
                ->count();
            $data = Db::name('score_site_moneylog')
                ->alias('mml')
                ->field('mml.*,mendian.name')
                ->join('score_site mendian','mendian.id=mml.mdid')
                ->where($where)
                ->page($page,$limit)
                ->order($order)
                ->select()
                ->toArray();
            if($data){
                foreach($data as &$v){
                    $v['mendian_infor'] = '';
                    $mendian = Db::name('score_site')->where('id',$v['mdid'])->field('id,name')->find();
                    if($mendian){
                        $v['mendian_infor'] = 'ID:'.$v['mdid']."\n\r"."名称:".$mendian['name'];
                    }
                }
                unset($v);
            }
            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
        }
        if(!$this->mdid){
            $mendian = Db::name('score_site')
            ->where('aid',aid)
            ->where('bid',bid)
            ->field('id,name')
            ->select()
            ->toArray();
            View::assign('score_site',$mendian);
        }
		$mendian_upgrade = false;
		View::assign('mendian_upgrade',$mendian_upgrade);
        View::assign('mdid',$this->mdid?$this->mdid:0);
        return View::fetch();
    }
    //积分明细导出
    public function moneylogexcel(){
        if(input('param.field') && input('param.order')){
            $order = 'mml.'.input('param.field').' '.input('param.order');
        }else{
            $order = 'mml.id desc';
        }
        $page = input('param.page');
        $limit = input('param.limit');
        $mdidarr = Db::name('score_site')->where('aid',aid)->where('bid',bid)->column('id');
        $where = [];
        if($this->mdid){
            if(in_array($this->mdid,$mdidarr)){
                $where[] = ['mml.mdid','=',$this->mdid];
            }else{
                $where[] = ['mml.id','=',0];
            }
        }else{
            if(input('param.mdid')){
                if(in_array(input('param.mdid'),$mdidarr)){
                    $where[] = ['mml.mdid','=',input('param.mdid')];
                }else{
                    $where[] = ['mml.id','=',0];
                }
            }else{
                $where[] = ['mml.mdid','in',$mdidarr];
            }
        }
        $where[] = ['mml.aid','=',aid];
        if(input('param.name')) $where[] = ['mendian.name','like','%'.trim(input('param.name')).'%'];
        if(input('param.mid')) $where[] = ['mml.mid','=',trim(input('param.mid'))];

        $list = Db::name('score_site_moneylog')
            ->alias('mml')
            ->field('mendian.name,mml.*')
            ->join('score_site mendian','mendian.id=mml.mdid')
            ->where($where)->order($order)->page($page,$limit)->select()->toArray();
        $count = Db::name('mendian_moneylog')
            ->alias('mml')
            ->field('mendian.name,mml.*')
            ->join('score_site mendian','mendian.id=mml.mdid')
            ->where($where)->order($order)->count();
        $title = array();
        $title[] = '站点信息';
        $title[] = t('会员').'信息';
        $title[] = '变更金额';
        $title[] = '变更后剩余';
        $title[] = '变更时间';
        $title[] = '备注';
        $data = array();
        foreach($list as $v){
            $tdata = array();
            $mendian_infor = '';
            $mendian = Db::name('score_site')->where('id',$v['mdid'])->field('id,name')->find();
            if($mendian){
                $mendian_infor = 'ID:'.$v['mdid']."\n\r"."名称:".$mendian['name'];
            }
            $tdata[] = $mendian_infor;
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
    //积分明细删除
    public function moneylogdel(){
        if($this->mdid){
            return json(['status'=>0,'msg'=>'无操作权限']);
        }
        $ids = input('post.ids/a');
        $mdidarr = Db::name('score_site')->where('aid',aid)->where('bid',bid)->column('id');
        $where = array();
        $where[] = ['id','in',$ids];
        if($this->mdid){
            if(in_array($this->mdid,$mdidarr)){
                $where[] = ['mdid','=',$this->mdid];
            }else{
                $where[] = ['id','=',0];
            }
        }else{
            $where[] = ['mdid','in',$mdidarr];
        }
        $where[] = ['aid','=',aid];
        Db::name('score_site_moneylog')->where($where)->delete();
        \app\commons\System::plog('删除站点积分明细'.implode(',',$ids));
        return json(['status'=>1,'msg'=>'删除成功']);
    }

    //提现记录
    public function withdrawlog(){
        if(request()->isAjax()){
            $page = input('param.page');
            $limit = input('param.limit');
            if(input('param.field') && input('param.order')){
                $order = 'mwl.'.input('param.field').' '.input('param.order');
            }else{
                $order = 'mwl.id desc';
            }
            $where = [];

            if($this->mdid){
                $where[] = ['mwl.mdid','=',$this->mdid];
            }else{
                if(input('param.mdid')){
                    $where[] = ['mwl.mdid','=',input('param.mdid')];
                }
            }

            $where[] = ['mwl.aid','=',aid];
            $where[] = ['mwl.bid','=',bid];
            if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
            if(input('param.mid')) $where[] = ['mwl.mid','=',trim(input('param.mid'))];
            if(input('?param.status') && input('param.status')!=='') $where[] = ['mwl.status','=',input('param.status')];
            $count = 0 + Db::name('score_site_withdrawlog')->alias('mwl')
                ->field('member.nickname,member.headimg,mwl.*')
                ->join('member member','member.id=mwl.mid')
                ->where($where)
                ->count();
            $data = Db::name('score_site_withdrawlog')
                ->alias('mwl')
                ->field('member.nickname,member.headimg,mwl.*')
                ->join('member member','member.id=mwl.mid')
                ->where($where)->page($page,$limit)->order($order)->select()->toArray();
            if($data){
                foreach($data as &$v){
                    $v['mendian_infor'] = '';
                    $mendian = Db::name('score_site')->where('id',$v['mdid'])->field('id,name')->find();
                    if($mendian){
                        $v['mendian_infor'] = 'ID:'.$mendian['id']."\n\r"."名称:".$mendian['name'];
                    }
                }
                unset($v);
            }
            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
        }
        if(!$this->mdid){
            $mendian = Db::name('score_site')
            ->where('aid',aid)
            ->where('bid',bid)
            ->field('id,name')
            ->select()
            ->toArray();
            View::assign('score_site',$mendian);
        }
        View::assign('mdid',$this->mdid?$this->mdid:0);
        View::assign('bid',bid);
        return View::fetch();
    }

    //提现记录导出
    public function withdrawlogexcel(){
        if(input('param.field') && input('param.order')){
            $order = 'score_site_withdrawlog.'.input('param.field').' '.input('param.order');
        }else{
            $order = 'score_site_withdrawlog.id desc';
        }
        $page = input('param.page');
        $limit = input('param.limit');
        $where = [];
        if($this->mdid){
            $where[] = ['score_site_withdrawlog.mdid','=',$this->mdid];
        }else{
            if(input('param.mdid')){
                $where[] = ['score_site_withdrawlog.mdid','=',input('param.mdid')];
            }
        }
        $where[] = ['score_site_withdrawlog.aid','=',aid];
        $where[] = ['score_site_withdrawlog.bid','=',bid];
        if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
        if(input('param.mid')) $where[] = ['score_site_withdrawlog.mid','=',trim(input('param.mid'))];
        if(input('?param.status') && input('param.status')!=='') $where[] = ['score_site_withdrawlog.status','=',input('param.status')];
        $list = Db::name('score_site_withdrawlog')->alias('score_site_withdrawlog')->field('member.nickname,member.headimg,score_site_withdrawlog.*')
            ->join('member member','member.id=score_site_withdrawlog.mid')->where($where)->page($page,$limit)->order($order)->select()->toArray();
        $count = Db::name('score_site_withdrawlog')->alias('score_site_withdrawlog')->field('member.nickname,member.headimg,score_site_withdrawlog.*')
            ->join('member member','member.id=score_site_withdrawlog.mid')->where($where)->count();
        $title = array();
        $title[] = '站点信息';
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
             $mendian_infor = '';
            $mendian = Db::name('score_site')->where('id',$v['mdid'])->field('id,name')->find();
            if($mendian){
                $mendian_infor = 'ID:'.$mendian['id']."\n\r"."名称:".$mendian['name'];
            }
            $tdata[] = $mendian_infor;
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
        return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'title'=>$title]);
        $this->export_excel($title,$data);
    }
    //提现记录改状态
    public function withdrawlogsetst(){
        if($this->mdid){
            return json(['status'=>0,'msg'=>'无操作权限']);
        }
        $id = input('post.id/d');
        $st = input('post.st/d');
        $reason = input('post.reason');
        $info = Db::name('score_site_withdrawlog')->where('aid',aid)->where('bid',bid)->where('id',$id)->find();
        $info['txmoney'] = dd_money_format($info['txmoney']);
        $info['money'] = dd_money_format($info['money']);
        if($st==10){//微信打款
           
            if($info['status']!=1) return json(['status'=>0,'msg'=>'已审核状态才能打款']);
            $rs = \app\commons\Wxpay::transfers(aid,$info['mid'],$info['money'],$info['ordernum'],$info['platform'],t('积分').'提现');
            if($rs['status']==0){
                return json(['status'=>0,'msg'=>$rs['msg']]);
            }else{
               
                Db::name('score_site_withdrawlog')->where('id',$id)->where('aid',aid)->where('bid',bid)->update(['status'=>3,'reason'=>$reason,'paytime'=>time(),'paynum'=>$rs['resp']['payment_no']]);
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
                \app\commons\System::plog('积分提现微信打款'.$id);
                return json(['status'=>1,'msg'=>$rs['msg']]);
            }
        }else{
            
            Db::name('score_site_withdrawlog')->where('id',$id)->where('aid',aid)->where('bid',bid)->update(['status'=>$st,'reason'=>$reason]);
            if($st == 2){
                //驳回返还积分
                \app\commons\ScoreSite::addmoney(aid,$info['mdid'],$info['txmoney'],'积分提现返还');

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
                \app\commons\System::plog('积分提现驳回'.$id);
            }
            if($st==3){
                //手动打款不扣除多商户积分

                // if(bid>0){
                //     //查询多商户的金额
                //     $business = Db::name('business')->where('id',bid)->where('aid',aid)->lock(true)->field('money')->find();
                //     if($business['money']<$info['money']){
                //         return json(['status'=>0,'msg'=>'账户积分不足']);
                //     }
                //     \app\commons\Business::addmoney(aid,bid,-$info['money'],'门店：ID'.$info['mdid'].'提现，订单号：'.$info['ordernum']);
                //}

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
                \app\commons\System::plog('积分提现改为已打款'.$id);
            }
        }
        return json(['status'=>1,'msg'=>'操作成功']);
    }
    //提现记录删除
    public function withdrawlogdel(){
        if($this->mdid){
            return json(['status'=>0,'msg'=>'无操作权限']);
        }
        $ids = input('post.ids/a');
        $where = array();
        $where[] = ['id','in',$ids];
        if($this->mdid){
            $where[] = ['mdid','=',$this->mdid];
        }
        $where[] = ['aid','=',aid];
        $where[] = ['bid','=',bid];
        Db::name('score_site_withdrawlog')->where($where)->delete();
        \app\commons\System::plog('积分提现记录删除'.implode(',',$ids));
        return json(['status'=>1,'msg'=>'删除成功']);
    }
}
