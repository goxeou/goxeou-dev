<?php
// +----------------------------------------------------------------------
// | 营销-排队免单 custom_file(yx_queue_free)
// +----------------------------------------------------------------------
namespace app\controllers\yingxiao;
use app\controllers\Common;
use think\facade\View;
use think\facade\Db;

class QueueFree extends \app\controllers\Common
{
    public function initialize(){
		parent::initialize();
	}
	//列表
    public function index(){
        if(request()->isAjax()){
            $page = input('param.page');
            $limit = input('param.limit');
            if(input('param.field') && input('param.order')){
                if(input('param.field') == 'queue_no')
                    $order = ' IF(ISNULL(queue_no),99999999,queue_no)'.' '.input('param.order');
                else
                    $order = 'queue_free.'.input('param.field').' '.input('param.order');
            }else{
                $order = 'queue_free.id desc';
            }

            $set = Db::name('queue_free_set')->where('aid',aid)->where('bid',0)->find();

            $where = [];
            $where[] = ['queue_free.aid','=',aid];
            //多商户排队类型 0独立排队 ，1参与平台排队 queue_type_business
            if(bid == 0){
                if($set['queue_type_business'] != 1){
                    $where[] = ['queue_free.bid','=',bid];
                }
            }else{
                $where[] = ['queue_free.bid','=',bid];
            }

            if(input('param.ctime') ){
                $ctime = explode(' ~ ',input('param.ctime'));
                $where[] = ['queue_free.createtime','>=',strtotime($ctime[0])];
                $where[] = ['queue_free.createtime','<',strtotime($ctime[1]) + 86400];
            }

            if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
            if(input('param.mid')) $where[] = ['queue_free.mid','=',trim(input('param.mid'))];
            if(input('?param.status')) $where[] = ['queue_free.status','=',trim(input('param.status'))];
            if(getcustom('yx_queue_free_multi_team')){
                if(input('param.multi_team_no')) $where[] = ['queue_free.multi_team_no','=',trim(input('param.multi_team_no'))];
            }
            $count = 0 + Db::name('queue_free')->alias('queue_free')->field('member.nickname,member.headimg,member.tel,queue_free.*')->join('member member','member.id=queue_free.mid','left')->where($where)->count();
            $data = Db::name('queue_free')->alias('queue_free')->field('member.nickname,member.headimg,member.tel,queue_free.*')->join('member member','member.id=queue_free.mid','left')->where($where)->page($page,$limit)->order(Db::raw($order))->select()->toArray();

            foreach ($data as &$v){
                if($set['queue_type_business'] != 1 && $v['bid']>0){
                    $v['queue_no'] = $v['queue_no'] ? 'S'.$v['queue_no'] : '';
                }else{
                    $v['queue_no'] = $v['queue_no'] ? 'P'.$v['queue_no'] : '';
                }

                if($v['bid']>0){
                    $v['bname'] = Db::name('business')->where('aid',aid)->where('id',$v['bid'])->value('name');
                }else{
                    $v['bname'] = Db::name('admin_set')->where('aid',aid)->value('name');
                }
                if(getcustom('yx_queue_duli_queue')){
                    $teammember = [];
                    if($v['teamid'] > 0){
                        $teammember = Db::name('member')->where('aid',aid)->where('id',$v['teamid'])->field('id,headimg,nickname')->find();
                    }
                    $v['teammember'] = $teammember;
                }
            }
            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
        }
        if(getcustom('yx_queue_free_money_range')) {
            $set = Db::name('queue_free_set')->where('aid', aid)->where('bid', 0)->find();
            $queue_money_range_status = $set['queue_money_range_status'];
            View::assign('queue_money_range_status', $queue_money_range_status);
        }
        //定制标签显示
        $mode_show = 0;
        if(getcustom('yx_queue_free_other_mode') || getcustom('yx_queue_free_today_average')){
            $mode_show = 1;
        }
        View::assign('mode_show',$mode_show);//平台设置
        return View::fetch();
    }
    public function log(){
        if(request()->isAjax()){
            $page = input('param.page');
            $queueid = input('param.queueid');
            $limit = input('param.limit');
            if(input('param.field') && input('param.order')){
                $order = 'queue_free_log.'.input('param.field').' '.input('param.order');
            }else{
                $order = 'queue_free_log.id desc';
            }

//            $set = Db::name('queue_free_set')->where('aid',aid)->where('bid',0)->find();

            $where = [];
            $where[] = ['queue_free_log.aid','=',aid];
            $where[] = ['queue_free_log.queueid','=',$queueid];


            if(input('param.ctime') ){
                $ctime = explode(' ~ ',input('param.ctime'));
                $where[] = ['queue_free_log.createtime','>=',strtotime($ctime[0])];
                $where[] = ['queue_free_log.createtime','<',strtotime($ctime[1]) + 86400];
            }

            if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
            if(input('param.mid')) $where[] = ['queue_free_log.mid','=',trim(input('param.mid'))];
            if(input('?param.status')) $where[] = ['queue_free_log.status','=',trim(input('param.status'))];
            $count = 0 + Db::name('queue_free_log')->alias('queue_free_log')->field('member.nickname,member.headimg,member.tel,queue_free_log.*')->leftJoin('member member','member.id=queue_free_log.from_mid')->where($where)->count();
            $data = Db::name('queue_free_log')->alias('queue_free_log')->field('member.nickname,member.headimg,member.tel,queue_free_log.*')->leftJoin('member member','member.id=queue_free_log.from_mid')->where($where)->page($page,$limit)->order(Db::raw($order))->select()->toArray();

            foreach ($data as &$v){
                $payorder = json_decode($v['payorderjson'],true);
                $v['payorder'] = $payorder;
                if($payorder['bid']>0){
                    $v['bname'] = Db::name('business')->where('aid',aid)->where('id',$payorder['bid'])->value('name');
                }else{
                    $v['bname'] = Db::name('admin_set')->where('aid',aid)->value('name');
                }
                if($v['receive_account'] == 'money'){
                    $v['receive_accountName'] = t('余额');
                }elseif($v['receive_account'] == 'fenzhang_wxpay'){
                    $v['receive_accountName'] = '微信分账';
                }elseif($v['receive_account'] == 'wxpay'){
                    $v['receive_accountName'] = '微信零钱';
                }elseif($v['receive_account'] == 'score'){
                    $v['receive_accountName'] = t('积分');
                }
            }
            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
        }
        return View::fetch();
    }

    //退出排队
    public function quit_queue(){
        $id = input('param.id');
        Db::name('queue_free')->where('id',$id)->where('aid',aid)->update(['quit_queue' =>1]);
        \app\commons\System::plog('排队免单退出，ID:'.$id);
        return json(['status'=>1,'msg'=>'退出成功']);
    }
    //旧数据重新排队
    public function reset_queue(){
        if(getcustom('yx_queue_free_money_range')){
            $action = input('param.action'); //1未加入的数据 2所有数据
            $action = $action??1;
            $set = Db::name('queue_free_set')->where('aid', aid)->where('bid', 0)->find();
            //1、查找未排队,未退出的数据 2、根据进行排队
            if($set['queue_money_range_status'] && $set['queue_money_range']){
               $queue_where = [];
               $queue_where[] = ['aid','=',aid];
               $queue_where[] = ['quit_queue','=',0];
               $queue_where[] = ['status','=',0];
               if($action ==1){
                   $queue_where[] = ['range_no','NULL','null'];
               }
               $queue_free_data = Db::name('queue_free')
                   ->where($queue_where)
                   ->select()->toArray();       
               
               $range_data = json_decode($set['queue_money_range'], true);
               if($queue_free_data){
                   foreach($queue_free_data as $queue){
                       $totalMoney = 0;
                       if($queue['type'] =='shop'){
                           $oglist = Db::name('shop_order_goods')->where('orderid',$queue['orderid'])->where('aid',$queue['aid'])->where('bid',$queue['bid'])->select()->toArray();
                           foreach ($oglist as $og){
                               $product = Db::name('shop_product')->where('id',$og['proid'])->where('aid',$queue['aid'])->where('bid',$queue['bid'])->find();
                               if($product['queue_free_status'] == 1){
                                   $totalMoney += $og['real_totalprice'];
                               }
                           }
                       }
                       elseif($queue['type'] == 'maidan'){
                           $totalMoney = Db::name('maidan_order')->where('id',$queue['orderid'])->value('paymoney');
                       }elseif($queue['type'] == 'collage'){
                           $totalMoney = Db::name('collage_order')->where('id',$queue['orderid'])->value('totalprice');
                       }
                       $range_no = '';
                       foreach ($range_data as $val) {
                           //使用订单金额
                           if ($totalMoney > $val['start'] && $totalMoney <= $val['end']) {
                               $range_no = $val['no'];
                           }
                       }
                       if (!$range_no) continue;

                       Db::name('queue_free')->where('aid',$queue['aid'])->where('id',$queue['id'])->update(['range_no' => $range_no]);
                   }
               }
               //所有的排队 编号 查询后 按照顺序 重置排队号
               $nodata = array_column($range_data,'no');
               foreach($nodata as $nk=>$no){
                   $queue_free_list = Db::name('queue_free')->where('aid',aid)->where('range_no',$no)->where('status',0)->where('quit_queue',0)->order('id asc')->select()->toArray();
                   if($queue_free_list){
                       foreach($queue_free_list as $qk=>$qv){
                           $queue_no =  $qk +1;
                           Db::name('queue_free')->where('aid',$qv['aid'])->where('id',$qv['id'])->update(['queue_no' => $queue_no]);
                       } 
                   }
               }    
            }

            \app\commons\System::plog('排队免单重新排队');
            return json(['code'=>0,'msg'=>'重置成功']);
        }
    }


    public function freezecreditlog()
    {
        if (request()->isAjax()) {
            $page = input('param.page');
            $limit = input('param.limit');
            $order = 'mfcl.id desc';
            $where = [];
            $where[] = ['mfcl.aid', '=', aid];
            if (input('param.nickname')) $where[] = ['member.nickname', 'like', '%' . trim(input('param.nickname')) . '%'];
            if (input('param.mid')) $where[] = ['mfcl.mid', '=', trim(input('param.mid'))];
            $data = Db::name('member_freeze_credit_log')
                ->alias('mfcl')
                ->field('member.nickname,member.headimg,mfcl.*')
                ->join('member member', 'member.id=mfcl.mid')
                ->where($where)
                ->order($order)
                ->paginate(['list_rows' => $limit, 'page' => $page], false)
                ->toArray();

            return json(['code' => 0, 'msg' => '查询成功', 'count' => $data['total'], 'data' => $data['data']]);
        }
        return View::fetch();
    }

    public function freezecreditlogexcel()
    {
        $page = input('param.page');
        $limit = input('param.limit');
        $order = 'mfcl.id desc';
        $where = [];
        $where[] = ['mfcl.aid', '=', aid];
        if (input('param.nickname')) $where[] = ['member.nickname', 'like', '%' . trim(input('param.nickname')) . '%'];
        if (input('param.mid')) $where[] = ['mfcl.mid', '=', trim(input('param.mid'))];
        $list_data = Db::name('member_freeze_credit_log')
            ->alias('mfcl')
            ->field('member.nickname,member.headimg,mfcl.*')
            ->join('member member', 'member.id=mfcl.mid')
            ->where($where)
            ->order($order)
            ->paginate(['list_rows' => $limit, 'page' => $page], false)
            ->toArray();
        $title = array();
        $title[] = t('会员') . '信息';
        $title[] = '变更数量';
        $title[] = '变更后数量';
        $title[] = '变更时间';
        $title[] = '备注';
        $list = $list_data['data'];
        $data = array();
        foreach ($list as $v) {
            $tdata = array();
            $tdata[] = $v['nickname'] . '(' . t('会员') . 'ID:' . $v['mid'] . ')';
            $tdata[] = $v['money'];
            $tdata[] = $v['after'];
            $tdata[] = date('Y-m-d H:i:s', $v['createtime']);
            $tdata[] = $v['remark'];
            $data[] = $tdata;
        }
        return json(['code'=>0,'msg'=>'查询成功','count'=>$list_data['total'],'data'=>$data,'title'=>$title]);
        $this->export_excel($title, $data);
    }
    public function refreshQueueno(){
        $queue_type_business = Db::name('queue_free_set')->where('aid',aid)->where('bid',0)->value('queue_type_business'); 
        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['status','=',0];
        $where[] = ['quit_queue','=',0];
        $order = 'createtime asc';
        if($queue_type_business ==1){
            $queue_list = Db::name('queue_free')->where($where)->order($order)->select()->toArray();
            $no = 1;
           foreach($queue_list as $key=>$queue){
              Db::name('queue_free')->where('id',$queue['id'])->update(['queue_no' => $no]);
               $no +=1;
           }
        }else{
            $setlist = Db::name('queue_free_set')->where('aid',aid)->field('id,aid,bid')->select()->toArray();
            foreach($setlist as $set){
                //查询每个商户的队伍
                $qwhere = $where;
                $qwhere[] = ['bid','=',$set['bid']];
                $queue_list = Db::name('queue_free')->where($qwhere)->order($order)->select()->toArray();
                $no = 1;
                foreach($queue_list as $key=>$queue){
                    Db::name('queue_free')->where('id',$queue['id'])->update(['queue_no' => $no]);
                    $no +=1;
                }
            }
        }
    }
}
