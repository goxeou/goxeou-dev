<?php
//custom_file(yx_queue_free)
namespace app\controllers\yingxiao;
use app\controllers\ApiCommon;
use think\facade\Db;
use think\facade\Log;

class ApiQueueFree extends ApiCommon
{
    public function initialize(){
        parent::initialize();
		$this->checklogin();
    }
    public function index(){
        $where = [];
        $where[] = ['queue_free.aid','=',aid];
        $where[] = ['queue_free.mid','=',mid];
        $pernum = 20;
        $pagenum = input('post.pagenum');
        $status = input('post.status');
        $time = time();
        if($status == 0){
            $where[] = ['queue_free.status','=',$status];
        }elseif($status == 1){
            $where[] = ['queue_free.status','=',$status];
        }
        if(!$pagenum) $pagenum = 1;
        $datalist = Db::name('queue_free')->alias('queue_free')->where($where)->page($pagenum,$pernum)->order('queue_free.id desc')->select()->toArray();
        $set = Db::name('queue_free_set')->where('aid',aid)->where('bid',0)->find();
        if(getcustom('yx_queue_free_money_range')){
            $queue_money_range_title = [];
            if($set['queue_money_range']){
                $queue_money_range = json_decode($set['queue_money_range'],true);
                $queue_money_range_title = array_column($queue_money_range,'title','no');
            }
        }
        foreach($datalist as &$v) {
            if ($v['status'] == 0) {
                $v['statusLabel'] = '排队中';
            } elseif ($v['status'] == 1) {
                $v['statusLabel'] = '已完成';
            }
            if($set['queue_type_business'] != 1 && $v['bid']>0){
                $v['bname'] = Db::name('business')->where('aid',aid)->where('id',$v['bid'])->value('name');
                $v['queue_noLabel'] = 'S'.$v['queue_no'];
            }else{
                $v['bname'] = Db::name('admin_set')->where('aid',aid)->value('name');
                $v['queue_noLabel'] = 'P'.$v['queue_no'];
            }
            if($v['bid']>0){
                $v['bname'] = Db::name('business')->where('aid',aid)->where('id',$v['bid'])->value('name');
            }else{
                $v['bname'] = Db::name('admin_set')->where('aid',aid)->value('name');
            }
            $v['createtimeFormat'] = date('Y-m-d H:i:s',$v['createtime']);
            if ($v['status'] == 0 && $set['quit_wxhb'] == 1 && empty($v['money_quit_hb'])) {
                $rand = mt_rand($set['quit_wxhb_min']*100,$set['quit_wxhb_max']*100);
                $v['money_quit_hb'] = floor($rand*($v['money']-$v['money_give'])/100)/100;
                Db::name('queue_free')->alias('queue_free')->where('id',$v['id'])->update(['money_quit_hb'=>$v['money_quit_hb']]);
            }
            if(getcustom('yx_queue_free_money_range')){
                $title='';
                if($v['range_no'] && $queue_money_range_title[$v['range_no']]){
                    $title = $queue_money_range_title[$v['range_no']];
                }
                $v['title'] =  $title;
            }
        }
        $rdata = [];
        $rdata['status']   = 1;
        $rdata['datalist'] = $datalist;
        $rdata['set'] = ['quit_wxhb'=>$set['quit_wxhb']];
        return $this->json($rdata);
    }

    public function quitHb()
    {
        $where = [];
        $where[] = ['queue_free.aid','=',aid];
        $where[] = ['queue_free.mid','=',mid];
        $id = input('post.id');
        if(empty($id)){
            return $this->json(['status'=>0,'msg'=>'参数错误']);
        }
        $where[] = ['queue_free.id','=',$id];

        $data = Db::name('queue_free')->alias('queue_free')->where($where)->find();
        if(empty($data)){
            return $this->json(['status'=>0,'msg'=>'数据不存在']);
        }
        $set = Db::name('queue_free_set')->where('aid',aid)->where('bid',0)->find();
        if($set['quit_wxhb'] != 1){
            return $this->json(['status'=>0,'msg'=>'功能未开启']);
        }
        if($data['status'] != 0){
            return $this->json(['status'=>0,'msg'=>'排队已结束，请刷新页面']);
        }
        if($data['money_quit_hb'] > $data['money'] - $data['money_give']){
            Db::name('queue_free')->alias('queue_free')->where('id',$data['id'])->update(['money_quit_hb'=>null]);
            return $this->json(['status'=>0,'msg'=>'排队数据已更新，请刷新页面再试']);
        }

        $queue = $data;
        $receive_account = 'wxpay';
        $money_give_this = $data['money_quit_hb'];
        $update = [
            'money_give'=>$data['money_give'] + $money_give_this,
            'status'=>1,
            'queue_no'=>null,
            'isquit'=>1
        ];
        Db::name('queue_free')->where('id',$queue['id'])->update($update);
        //更新排名
        //多商户排队类型 0独立排队 ，1参与平台排队 queue_type_business
        $whereQueueType = [];
        $whereQueueType[] = ['aid','=',aid];
        if($set['queue_type_business'] != 1){
            $whereQueueType[] = ['bid','=',$queue['bid']];
        }
        Db::name('queue_free')->where($whereQueueType)->where('status',0)->where('queue_no','>',1)->dec('queue_no',1)->update();
        $logid = Db::name('queue_free_log')->insertGetId([
            'queueid'=>$queue['id'],
            'aid'=>$queue['aid'],
            'bid'=>$queue['bid'],
            'mid'=>$queue['mid'],
            'type'=>$queue['type'],
            'orderid'=>$queue['orderid'],
            'ordernum'=>$queue['ordernum'],
            'title'=>$queue['title'],
            'money_give'=>$money_give_this,
            'from_queueid'=>0,
            'from_mid'=>0,
            'createtime'=>time(),
            'receive_account'=>$receive_account
        ]);
        if($receive_account == 'wxpay'){
            $rs = \app\commons\Wxpay::transfers(aid,$queue['mid'],$money_give_this,date('ymdHis') .aid. rand(1000, 9999),'wx',t('排队奖励返现'));
            if($rs['status']==1){
//                $this->withdrawSuccessNotice($info);
                Db::name('queue_free_log')->where('id',$logid)->update(['wxpay_status'=>1,'wxpay_errmsg'=>'']);
                return $this->json(['status'=>1,'msg'=>$rs['msg']]);
            }else{
                $receive_account = 'money';
                Log::write([
                    'file'=>__FILE__.__LINE__,
                    'queue'=>jsonEncode($queue),
                    'msg'=>$rs['msg'],
                ]);
                Db::name('queue_free_log')->where('id',$logid)->update(['wxpay_status'=>2,'wxpay_errmsg'=>$rs['msg']]);
                //余额奖励
                \app\commons\Member::addmoney(aid,$queue['mid'],$money_give_this,'退出'.t('排队奖励返现'));
//                return json(['status'=>0,'msg'=>$rs['msg']]);
            }
        }elseif($receive_account == 'money'){
            Db::name('queue_free_log')->where('id',$logid)->update(['receive_account'=>$receive_account]);
            //余额奖励
            \app\commons\Member::addmoney(aid,$queue['mid'],$money_give_this,'退出'.t('排队奖励返现'));
        }elseif($receive_account == 'score'){
            Db::name('queue_free_log')->where('id',$logid)->update(['receive_account'=>$receive_account]);
            //积分奖励
            \app\commons\Member::addscore(aid,$queue['mid'],$money_give_this,'退出'.t('排队奖励返现'));
        }
        return $this->json(['status'=>1,'msg'=>'操作成功']);
    }

    public function freezeCreditLog()
    {
        $pagenum = input('post.pagenum');
        $st = input('post.st');
        if (!$pagenum) $pagenum = 1;
        $pernum = 20;
        $where = [];
        $where[] = ['aid', '=', aid];
        $where[] = ['mid', '=', mid];
        $datalist = Db::name('member_freeze_credit_log')
            ->field('id,money,after,remark,from_unixtime(createtime)createtime')
            ->where($where)
            ->page($pagenum, $pernum)
            ->order('id desc')
            ->select()
            ->toArray();
        if (!$datalist) $datalist = [];
        $can_exchange = 0;
        if ($pagenum == 1) {
            $set = Db::name("queue_free_set")->where('aid', aid)->where('bid', bid)->find();
            if($set && $set['freeze_exchange_wallet']){
                $can_exchange = 1;
            }
        }
        return $this->json(['status' => 1, 'data' => $datalist, 'freeze_credit' => $this->member['freeze_credit'], 'can_exchange' => $can_exchange]);
    }

    public function exchangeInfo()
    {
        $sysset = Db::name('admin_set')->where('aid', aid)->find();
        $queue_set = Db::name('queue_free_set')->where('aid', aid)->where('bid', bid)->find();
        $freeze_credit = $this->member['freeze_credit'];
        $wallet = [];
        if ($queue_set && $queue_set['freeze_exchange_wallet']) {
            $wallet_list = explode(',', $queue_set['freeze_exchange_wallet']);
            foreach ($wallet_list as $item) {
                if ($item == 1) {
                    $wallet[] = [
                        'img' => '',
                        'title' => t('余额'),
                        'type' => 1,
                        'selected' => 1
                    ];
                }
                if ($item == 2) {
                    $wallet[] = [
                        'img' => '',
                        'title' => t('佣金'),
                        'type' => 2,
                        'selected' => 0
                    ];
                }

            }
        }
        return $this->json([
            'sysset' => $sysset,
            'freeze_credit' => $freeze_credit,
            'wallet' => $wallet
        ]);
    }

    public function subExchange()
    {
        $num = input('money');
        $type = input('paytype');
        $queue_set = Db::name("queue_free_set")->where('aid', aid)->where('bid', bid)->find();
        if ($num <= 0) {
            return $this->json([
                'status' => 0,
                'msg' => '请输入正确的兑换金额'
            ]);
        }
        if ($queue_set && $queue_set['freeze_exchange_wallet']) {
            if (!in_array($type, explode(',', $queue_set['freeze_exchange_wallet']))) {
                return $this->json([
                    'status' => 0,
                    'msg' => '抱歉，请正确选择兑换方式'
                ]);
            }
        } else {
            return $this->json([
                'status' => 0,
                'msg' => '抱歉，暂不支持兑换'
            ]);
        }
        if ($num > $this->member['freeze_credit']) {
            return $this->json([
                'status' => 0,
                'msg' => t('冻结账户') . '不足'
            ]);
        }
        $admin_set = Db::name('admin_set')->where('aid', aid)->find();
        $score2money = $admin_set['score2money'];
        $score = 0;
        if ($score2money > 0) {
            $score = round($num / $score2money, 2);
            if ($score > $this->member['score']) {
                return $this->json([
                    'status' => 0,
                    'msg' => t('积分') . '不足'
                ]);
            }
        }
        Db::startTrans();
        try {
            $exchange_data = [
                "aid" => aid,
                "mid" => mid,
                "num" => $num,
                "score" => $score,
                "type" => $type,
                "create_time" => time(),
                "update_time" => time()
            ];
            $log_id = Db::name("member_freeze_credit_exchange_log")->insertGetId($exchange_data);
            //增加
            if ($type == 1) {
                //余额
                \app\commons\Member::addmoney(aid, mid, $num, t('冻结账户') . '兑换');
            }

            if ($type == 2) {
                //佣金
                \app\commons\Member::addcommission(aid, mid, mid, $num, t('冻结账户') . '兑换');
            }

            //扣除
            if ($score > 0) {
                \app\commons\Member::addscore(aid, mid, -$score, t('冻结账户') . '兑换扣除');
            }
            \app\commons\Member::addFreezeCredit(aid, mid, -$num, t('冻结账户') . '兑换扣除', $log_id);
            Db::commit();
            return $this->json([
                'status' => 1,
                'msg' => '兑换成功'
            ]);
        } catch (\Throwable $t) {
            Db::rollback();
            \think\facade\Log::write($t->getTraceAsString());
            return $this->json([
                'status' => 1,
                'msg' => '兑换失败'
            ]);
        }
    }

    public function exchangelog()
    {
        $pagenum = input('post.pagenum');
        if (!$pagenum) $pagenum = 1;
        $pernum = 20;
        $where = [];
        $where[] = ['aid', '=', aid];
        $where[] = ['mid', '=', mid];
        $datalist = Db::name('member_freeze_credit_exchange_log')
            ->field('id,num,score,type,from_unixtime(create_time) create_time')
            ->where($where)
            ->page($pagenum, $pernum)
            ->order('id desc')
            ->select()
            ->each(function($item){
                $item['remarks'] = '';
                if($item['type'] == 1){
                    $item['remarks'] = '兑换到'.t('余额');
                }
                if($item['type'] == 2){
                    $item['remarks'] = '兑换到'.t('佣金');
                }
                return $item;
            })
            ->toArray();
        return $this->json(['status' => 1, 'data' => $datalist]);
    }

}
