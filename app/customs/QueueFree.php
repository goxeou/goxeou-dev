<?php
//custom_file(yx_queue_free)
namespace app\customs;

use think\facade\Db;
use think\facade\Log;

//文档 https://doc.weixin.qq.com/doc/w3_AT4AYwbFACwKAnVHweoQS231FlDtm?scode=AHMAHgcfAA0dMz1lZnAT4AYwbFACw
class QueueFree
{
    //加入免单队列
    /**
     * @param $order 订单数据（商城订单确认收货后参与）
     * @param $orderType 订单类型
     * @return void
     */
    public static function join($order,$orderType = 'shop',$action='pay')
    {
        $rate = 0;
        $money_max = 0;
        $queue_no = 1;
        $rate_back = 0;
        $set = Db::name('queue_free_set')->where('aid',$order['aid'])->where('bid',0)->find();
        Log::write([
            'file'=>__FILE__.__LINE__,
            'set'=>$set,
            'order'=>jsonEncode(['id'=>$order['id'],'aid'=>$order['aid'],'bid'=>$order['bid'],'mid'=>$order['mid'],'ordernum'=>$order['ordernum'],'totalprice'=>$order['totalprice']])
        ]);

        if($set['status'] != 1){
            return false;
        }
        $set['order_types'] = explode(',',$set['order_types']);
        if(!in_array('all',$set['order_types']) && !in_array($orderType,$set['order_types'])){
            return false;
        }
        //time_type 排队时间:0确认收货，1支付
        if(/*$orderType != 'maidan' &&*/ $set['time_type'] == 1){
            if($order['status'] < 1){
                return false;
            }
        }elseif($set['time_type'] == 0){
            if($orderType == 'maidan' && $order['status'] != 1){
                return false;
            }elseif(($orderType == 'shop' || $orderType == 'collage') && $action != 'collect'){
                return false;
            }
        }

        if(getcustom('yx_queue_free_moneypayjoin')){
            //余额支付订单是否参与排队
            if($order['paytypeid'] == 1 && $set['moneypayjoin'] != 1){
                return false;
            }
        }

        $rate_back = -1;//返利比例 默认 -1
        if(getcustom('yx_queue_free_collage')){
            //查询拼团商品单独设置返利比例
            if($orderType == 'collage'){
                $product = Db::name('collage_product')->where('id',$order['proid'])->field('queue_free_status,queue_free_rate_back')->find();
                if($product['queue_free_status'] == 1 && $product['queue_free_rate_back']>=0){
                    $rate_back = $product['queue_free_rate_back'];
                }
            }
        }

        $businessRateStatus = false;
        if($order['bid'] > 0){
            $businessSet = Db::name('queue_free_set')->where('aid',$order['aid'])->where('bid',$order['bid'])->find();
            if($businessSet['status'] != 1){
                return false;
            }

            //多商户修改免单比例，-1跟随系统，0关闭，1开启（商户跟随系统且系统关闭，或者商户关闭时 使用系统设置）
            if(($businessSet['rate_status_business'] == -1 && $set['rate_status_business'] == 0) || ($businessSet['rate_status_business'] == 0)){
                $rate = $set['rate'];
            }else{
                $rate = $businessSet['rate'];
                $businessRateStatus = true;
            }
            if($rate <= 0){
                return false;
            }

            $money_max = $businessSet['money_max'] == '' ? $set['money_max'] : $businessSet['money_max'];//0为不限制，留空跟随系统设置
            $rate_back = $rate_back >= 0 ? $rate_back : ($businessSet['rate_back'] > 0 ? $businessSet['rate_back'] : $set['rate_back']);//0或空跟随系统设置
        }else{
            if($set['rate'] <= 0){
                return false;
            }
            $rate = $set['rate'];
            $money_max = $set['money_max'];
            $rate_back = $rate_back >= 0 ? $rate_back : $set['rate_back'];
        }

        //是否存在
        $count = Db::name('queue_free')->where('orderid',$order['id'])->where('type',$orderType)->where('aid',$order['aid'])->where('bid',$order['bid'])->count();
        if($count){
            return false;
        }

        //计算
        $totalMoney = 0;
        if($orderType == 'shop'){
            $oglist = Db::name('shop_order_goods')->where('orderid',$order['id'])->where('aid',$order['aid'])->where('bid',$order['bid'])->select()->toArray();
            foreach ($oglist as $og){
                $product = Db::name('shop_product')->where('id',$og['proid'])->where('aid',$order['aid'])->where('bid',$order['bid'])->find();
                if($product['queue_free_status'] == 1){
                    $totalMoney += $og['real_totalprice'];
                }
            }

        }elseif($orderType == 'maidan'){
            $totalMoney = $order['paymoney'];
        }elseif($orderType == 'collage'){
            $totalMoney = $order['totalprice'];
        }
        if(getcustom('yx_queue_free_money_range')){
            if($set['queue_money_range_status'] == 1) {
                //如果开启后定制，根据订单金额所在范围获得
                $range_data = json_decode($set['queue_money_range'], true);
                $range_no = '';
                foreach ($range_data as $key => $val) {
                    //使用订单金额
                    if ($totalMoney > $val['start'] && $totalMoney <= $val['end']) {
                        $range_no = $val['no'];
                        $rate = $businessRateStatus ? $rate : $val['rate'];
                        if($set['rate_status_business'] == 0){
                            $rate_back = $businessRateStatus ? $rate_back : $val['rate_back'];
                            $money_max = $businessRateStatus ? $money_max : $val['money_max'];
                        }
                        break;
                    }
                }
                if (!$range_no) return;
            }
        }
        $freeMoney = $totalMoney * $rate / 100;
        if($freeMoney <= 0 || $freeMoney < 0.01){
            return false;
        }
        if($money_max > 0 && $freeMoney > $money_max){
            $freeMoney = $money_max;
        }
        if(getcustom('yx_queue_free_fanli_commission')){
            if($order['bid'] > 0){
                $moneyBack = $rate_back * $totalMoney / 100;
                self::businessUserCommission($order['aid'],$order['bid'],$moneyBack);
            }
        }
        //多商户排队类型 0独立排队 ，1参与平台排队 queue_type_business
        $whereQueueType = [];
        $whereQueueType[] = ['aid','=',$order['aid']];
        if($set['queue_type_business'] != 1){
            $whereQueueType[] = ['bid','=',$order['bid']];
        }
        $whereQueueType[]=['quit_queue','=',0];
        //排名
        $count = Db::name('queue_free')->where($whereQueueType)->where('status',0)->count();
        $queue_no = $count + 1;
        //插入排队记录表
        $log = [
            'aid'=>$order['aid'],
            'bid'=>$order['bid'],
            'mid'=>$order['mid'],
            'type'=>$orderType,
            'orderid'=>$order['id'],
            'ordernum'=>$order['ordernum'],
//            'ordermoney'=>
            'title'=>$order['title'],
            'money'=>$freeMoney,
            'money_give'=>0,
            'createtime'=>time(),
            'status'=>0,
            'queue_no'=>$queue_no
        ];
        if(getcustom('yx_queue_duli_queue')){
            $member = Db::name('member')->where('aid',$order['aid'])->where('id',$order['mid'])->field('id,path,levelid')->find();
            //1、如果等级开启了独立排队且没有独立的队伍，就进行独立排队，重新排序，
            //2、等级不够，查找path，最近的推荐人是否有开启独立排队
            
            $duli_levelid = Db::name('queue_free_set')->where('aid',$order['aid'])->where('bid',0)->value('duli_queue_levelid');
            $duli_queue_levelid = $duli_levelid?explode(',',$duli_levelid):[];
            if(in_array($member['levelid'],$duli_queue_levelid) ){
                $have_duli_queue_fee = Db::name('queue_free')->where('aid',$order['aid'])->where('status',0)->where('teamid',$member['id'])->find();
                if(!$have_duli_queue_fee){
                    $queue_no = 1;
                    $downmids = \app\commons\Member::getdownmids($order['aid'],$member['id']);
                    $downmids[] = $member['id'];//包含自己的排队
                  
                    if($downmids){
                        $child_queue=Db::name('queue_free')->where('aid',$order['aid'])->where('status',0)->where('mid','in',$downmids)->order('queue_no asc,id asc')->select()->toArray();
                      
                        if($child_queue){
                            //如果downmids中的会员存在独立排队，他的伞不再加入当前独立排队，
                            foreach($child_queue as $key=>$val){
                              
                                if($val['teamid'] > 0 && in_array($val['teamid'],$downmids))continue;
                                Db::name('queue_free')->where('id',$val['id'])->update(['queue_no' => $queue_no,'teamid' => $member['id']]);
                                $queue_no +=1;
                            }
                        }
                    }
                    $log['queue_no'] = $queue_no;
                    $log['teamid'] = $member['id'];
                }else{
                    $last_queue_no =  Db::name('queue_free')->where('aid',$order['aid'])->where('teamid',$have_duli_queue_fee['teamid'])->order('queue_no desc,id desc')->value('queue_no');
                    $log['queue_no'] = $last_queue_no+1;
                    $log['teamid'] = $have_duli_queue_fee['teamid'];
                }
            }else{
                //未开启排队 找父级有排队的 加入队伍
                if($member['path']){
                    $path_arr = explode(',',$member['path']);
                    $path_arr =  array_reverse($path_arr);
                    //所有路径上的排队
                    $parents_queue = Db::name('queue_free')->where('aid',$order['aid'])->where('teamid','in',$path_arr)->field('id,teamid')->select()->toArray();
                  
                    //从近到远如果有独立排队
                    if($parents_queue){
                       
                        $teamid =  $parents_queue[0]['teamid'];
                        
                        //查找当前队伍最大的排队序号
                        $last_queue_no =  Db::name('queue_free')->where('aid',$order['aid'])->where('teamid',$teamid)->order('queue_no desc,id desc')->value('queue_no');
                        $last_queue_no = $last_queue_no?$last_queue_no:1;
                        $log['queue_no'] = $last_queue_no +1;
                        $log['teamid'] = $teamid;
                    }else{
                        //查找平台的排队
                        $platformcount = Db::name('queue_free')->where($whereQueueType)->where('status',0)->where('teamid',0)->count();
                        $log['queue_no'] = $platformcount + 1;
                    } 
                } else{
                    //查找平台的排队
                    $platformcount = Db::name('queue_free')->where($whereQueueType)->where('status',0)->where('teamid',0)->count();
                    $log['queue_no'] = $platformcount + 1;
                }
            }
        }
        if(getcustom('yx_queue_free_money_range')){
            if($set['queue_money_range_status'] ==1) {
                //查找对应编号的队伍
                $range_free = Db::name('queue_free')->where('aid', $order['aid'])->where('status', 0)->where('range_no', $range_no)->order('queue_no desc,id desc')->find();
                //如果有排队 加入排队 没有排队创建排队
                $log['queue_no'] = $range_free ? $range_free['queue_no'] + 1 : 1;
                $log['range_no'] = $range_no;
            }
        }
        if(getcustom('yx_queue_free_multi_team')){
            if($set['queue_multi_team_status'] == 1) {
                //订单金额小于最低参与金额退出
                if($totalMoney < $set['queue_multi_team_min_money'] ){
                    return false;
                }
                //查找最后的排队编号并统计人数，如果统计人数 > 设置人数,编号+1，否则编号 = 最后排队编号，如果统计人数为空则默认1
                $last_team  =Db::name('queue_free')->where('aid',aid)->order('id desc,multi_team_no desc')->find();
                if(!$last_team){
                    $queue_no =1;
                    $multi_team_no = 1;
                }else{
                    $last_team_no_count = Db::name('queue_free')->where('aid',aid)->where('multi_team_no',$last_team['multi_team_no'])->count();
                    if($last_team_no_count >= $set['queue_multi_team_people_num']){
                        $multi_team_no = $last_team['multi_team_no'] +1;
                        $queue_no =1;
                    }else{
                        $multi_team_no = $last_team['multi_team_no'];
                        $queue_no =$last_team_no_count +1;
                    }
                }
                $log['multi_team_no'] = $multi_team_no;
                $log['queue_no'] = $queue_no;
            }
        }
        $id = Db::name('queue_free')->insertGetId($log);
        
        $log['id'] = $id;
        $order['orderType'] = $orderType;

        //历史订单返现
        $moneyBack = $rate_back * $totalMoney / 100;
        if(getcustom('yx_queue_free_other_mode')){
            $bid = $order['bid']?$order['bid']:0;
            $set = Db::name('queue_free_set')->where('aid',$order['aid'])->where('bid',0)->find();
            $queue_list = Db::name('queue_free')->where($whereQueueType)->where('status', 0)->order('id asc')->select()->toArray();
            if($set['mode'] ==1){ //平均分配
                
                  self::averageBackDo($order['aid'],$bid,$order['mid'],$order,$moneyBack,$whereQueueType,$set,$rate_back,$queue_list,1);
                  return;
            }else if($set['mode'] ==2){
                self::moneyBackDo($order['aid'],$bid,$order['mid'],$order,$moneyBack,$whereQueueType,$set,$rate_back,$queue_list,2);
                return;
            }else if($set['mode'] ==3){
               
                self::averageAndMoneyBackDo($order['aid'],$bid,$order['mid'],$order,$moneyBack,$whereQueueType,$set,$rate_back,$queue_list);
                return;
            }else if($set['mode'] ==4){
                
                self::averageAndFixedBackDo($order['aid'],$bid,$order['mid'],$order,$moneyBack,$whereQueueType,$set,$rate_back,$queue_list,$log);
                return;
            }
           
        }
        if(getcustom('yx_queue_free_today_average')){
            if($set['mode'] ==5){
                $bid = $order['bid']?$order['bid']:0;
                $set = Db::name('queue_free_set')->where('aid',$order['aid'])->where('bid',0)->find();
                $queue_list = Db::name('queue_free')->where($whereQueueType)->where('status', 0)->order('id asc')->select()->toArray();
                self::todayAverageAndFixedBackDo($order['aid'],$bid,$order['mid'],$order,$moneyBack,$whereQueueType,$set,$rate_back,$queue_list,$log);
                return;
            }
        }
        if(getcustom('yx_queue_duli_queue')){
            //查找当前用户的队伍信息 给这个队伍内的人返利                                                
            $back_teamid = Db::name('queue_free')->where('aid',$order['aid'])->where('mid',$order['mid'])->value('teamid');
            $whereQueueType[] = ['teamid','=',$back_teamid];
        }
        if(getcustom('yx_queue_free_money_range')){
            if($set['queue_money_range_status'] ==1 && $range_no) {
                $whereQueueType[] = ['range_no', '=', $range_no];
            }
        }
        if(getcustom('yx_queue_free_multi_team')){
            if($set['queue_multi_team_status'] == 1) {
                $whereQueueType[] = ['mid', '=', $order['mid']];
                $whereQueueType[] = ['multi_team_no', '>', 0];
            }
        }
        if($moneyBack > 0)
            self::doOrder($order['aid'],$order['bid'],$order['mid'],$order,$moneyBack,$log,$whereQueueType,$set,$rate_back,1,0,'固定分配');
    }

    public static function doOrder($aid,$bid,$mid,$order,$money,$log,$whereQueueType,$set,$rate_back,$i=1,$mode=0,$remark='')
    {
        if($money <= 0) return ;
        $queueParent = 0;
        if($i == 1 && $set['parent_fast'] == 1 && $set['parent_fast_rate'] > 0 && $mode ==0){
            //分享速返，先分给直推上级
            $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->find();
            if($member['pid']){
                $queue = Db::name('queue_free')->where($whereQueueType)->where('status',0)
                ->where('mid','=',$member['pid'])
                ->order('id asc')
                ->find();
                if($queue) $queueParent=1;
            }
        }else{
            $queue = Db::name('queue_free')->where($whereQueueType)->where('status',0)
//            ->where('mid','<>',$log['mid'])
//            ->order('id asc')
                ->where('queue_no',1)
                ->find();
        }

        if(empty($queue)){
            $q_order = 'id asc';
            if(getcustom('yx_queue_free_change_no')){
                //使用克更改排队号后，必须使用排队号排序来找
                $q_order ='queue_no asc,change_no_time desc';
            }
            $queue = Db::name('queue_free')->where($whereQueueType)->where('status',0)
//            ->where('mid','<>',$log['mid'])
            ->order($q_order)->find();
        }
        if(empty($queue)) return;

        $receive_account = 'money';
        if($set['receive_account'] == 'fenzhang_wxpay' && $rate_back <= 30){
            $receive_account = 'fenzhang_wxpay';
        }
        if(getcustom('yx_queue_free_freeze_account')){
            if($set['receive_account'] == "freeze_credit"){
                $receive_account = 'freeze_credit';
            }
        }
        if(getcustom('yx_queue_free_receive_scoreaccount')){
            if($set['receive_account'] == "score"){
                $receive_account = 'score';
            }
        }
        $queue_money_left = dd_money_format($queue['money']-$queue['money_give']);

        if($i == 1 && $queueParent){
            //分享速返
            if($set['parent_fast_rate'] > 100) $set['parent_fast_rate'] = 100;
            $moneyParent = $money*$set['parent_fast_rate']/100;//返给上级的金额
            if($moneyParent < $queue_money_left){
                $queue_money_left = $moneyParent;//给上级返一部分，其他按排队顺序返
            }
        }

        if($money >= $queue_money_left){
            $money_give_this = $queue_money_left;
            
        }elseif($money < $queue_money_left){
            $money_give_this = $money;
        }

        $update = [
            'money_give'=>$queue['money_give'] + $money_give_this,
            'money_quit_hb'=>null,//退出金额重置
        ];
        $queue_money = dd_money_format($queue['money']-$queue['money_give']-$money_give_this);//当前队伍剩余排队金额
        if($queue_money <= 0){
            $update['status'] = 1;
            $update['queue_no'] = null;
        }
        Db::name('queue_free')->where('id',$queue['id'])->update($update);
        if(getcustom('yx_queue_free_today_average')){
            //返利金额 >大于剩余返利金额 进入分红池 $order 为空是次日凌晨分发不再算结余
            if($money > $queue_money_left && $set['mode'] ==5){ 
                $pool_money =  dd_money_format($money -  $queue_money_left);
                if($pool_money > 0 && $mode ==5 && !$order['is_not_today'] ){
                    self::todayAverageAddPool ($aid,$bid,$pool_money,$set,'yesterday',$order,$queue,'今日平均结余');
                }
            }
           
        }
        if($queue_money <= 0){
            //更新排名
            if($i == 1 && $queueParent) {
                //分享速返
                Db::name('queue_free')->where($whereQueueType)->where('status',0)->where('queue_no','>',$queue['queue_no'])->dec('queue_no',1)->update();
            }else{
                Db::name('queue_free')->where($whereQueueType)->where('status',0)->where('queue_no','>',1)->dec('queue_no',1)->update();
            }
        }

        $logdata = [
            'queueid'=>$queue['id'],
            'aid'=>$queue['aid'],
            'bid'=>$queue['bid'],
            'mid'=>$queue['mid'],
            'type'=>$queue['type'],
            'orderid'=>$queue['orderid'],
            'ordernum'=>$queue['ordernum'],
            'title'=>$queue['title'],
            'money_give'=>$money_give_this,
            'from_queueid'=>$log['id'],
            'from_mid'=>$log['mid'],
            'createtime'=>time(),
            'receive_account'=>$receive_account,
            'payorderjson'=>jsonEncode($order),
            'payordertype'=>$order['orderType'],
            'payordernum'=>$order['ordernum'],
            'fenzhang_wxpay_rate'=>$set['fenzhang_wxpay_rate']
        ];
        if(getcustom('yx_queue_free_other_mode') || getcustom('yx_queue_free_today_average')){
            $logdata['mode'] = $mode;
            $logdata['remark'] = $remark;
        }
        $logid = Db::name('queue_free_log')->insertGetId($logdata);
        if($receive_account == 'fenzhang_wxpay'){
            //废弃 异步处理
//                $rs = self::wxFenzhang($aid,$bid,$mid,$order,$money_give_this,$queue,$logid,$order['orderType']);
//                if($rs['status'] != 1 && $rs['status'] !== 0)//0为待处理，异步分账
//                    \app\commons\Member::addmoney($aid,$queue['mid'],$money_give_this,'排队奖励返现');
        }elseif ($receive_account == 'freeze_credit'){
            if(getcustom('yx_queue_free_freeze_account')){
                \app\commons\Member::addFreezeCredit($aid,$queue['mid'],$money_give_this,t('排队奖励返现'));
            }
        }elseif ($receive_account == 'score'){
            if(getcustom('yx_queue_free_receive_scoreaccount')){
                //积分奖励
                \app\commons\Member::addscore($aid,$queue['mid'],$money_give_this,t('排队奖励返现'));
            }
        }else{
            //余额奖励
            \app\commons\Member::addmoney($aid,$queue['mid'],$money_give_this,t('排队奖励返现'));
        }
        //增加累计排队佣金
        if($queue['mid'] && $money_give_this){
            Db::name('member')->where('aid',$aid)->where('id',$queue['mid'])->inc('totalqueuecommission',$money_give_this)->update();
        }

        $money -= $money_give_this;//扣除本次金额，剩余金额
        $is_xunhuan = 1;
        if(getcustom('yx_queue_free_today_average')){
           if($order['is_not_today']){
               $is_xunhuan = 0;
           } 
        }
        if($money > 0 && $is_xunhuan){
            $i++;
            self::doOrder($aid,$bid,$mid,$order,$money,$log,$whereQueueType,$set,$rate_back,$i,$mode,'固定分配');
        }
    }

    //平均计算
    public static function averageBackDo($aid,$bid,$mid,$order,$moneyBack,$whereQueueType,$set,$rate_back,$queue_list=[],$mode=1,$remark='平均分配'){
        if(getcustom('yx_queue_free_other_mode') || getcustom('yx_queue_free_today_average')) {
            if ($moneyBack > 0) {
                //发放的数量
                $limit_count = count($queue_list);
                $pj_money = dd_money_format($moneyBack / count($queue_list));
                //最小金额
                if($set['mode'] ==1){
                    $min_pj_money = $set['pj_min_money'] ? $set['pj_min_money'] : 0.01; 
                }else if($set['mode'] ==3){
                    $min_pj_money = $set['pjmoney_pj_min_money'] ? $set['pjmoney_pj_min_money'] : 0.01;
                }else if($set['mode'] ==4){
                    $min_pj_money = $set['pjfixed_pj_min_money'] ? $set['pjfixed_pj_min_money'] : 0.01;
                }else{
                    $min_pj_money = 0.01;
                }
              
                if ($pj_money < $min_pj_money) {
                    //小于最低金额了 金额/最低金额 = 人数 目前向下取整比如2.8取值2个人，因为第三个人小于最低金额
                    $limit_count = floor($moneyBack / $min_pj_money);
                    $pj_money = dd_money_format($moneyBack / $limit_count, 2);
                }
                
                //产生返利的 排队
                $newlog = Db::name('queue_free')->where($whereQueueType)->where('ordernum',$order['ordernum'])->where('type',$order['orderType'])->order('id desc')->find();
                foreach ($queue_list as $key => $val) {
                    if ($key+1 <= $limit_count) {
                        $everywhere = $whereQueueType;
                        $everywhere[] = ['id', '=', $val['id']];
                        self::doOrder($aid, $bid, $mid, $order, $pj_money, $newlog, $everywhere, $set, $rate_back,1,$mode,$remark);
                    }
                }
            }
        }
    }
    
    //金额分配 
    public static function moneyBackDo($aid,$bid,$mid,$order,$moneyBack,$whereQueueType,$set,$rate_back,$queue_list=[],$mode=2){
        if(getcustom('yx_queue_free_other_mode')) {
            if ($moneyBack > 0) {
                //总消费金额 如果某个消费者分红出局后，总订单金额不再计算此单
                $total_paymoney = 0;
                foreach ($queue_list as $key => $val) {
                    $total_paymoney += 0+Db::name('payorder')->where('aid', $aid)->where('bid', $bid)->where('status', 1)->where('orderid', $val['orderid'])->where('type',$val['type'])->value('money');
                }
                //查找 最新下单 和最早（第一个下单） 

                $now_money = 0;//最新获得金额
                $last_money = 0;//最早获得金额
                $lastkey = count($queue_list) - 1;
                $nowqueue = $queue_list[$lastkey];//最新
                if($set['mode'] ==2){
                    $new_order_ratio =  $set['new_order_ratio'];
                    $old_order_ratio =   $set['old_order_ratio'];
                }elseif ($set['mode'] ==3){
                    $new_order_ratio =  $set['pjmoney_new_order_ratio'];
                    $old_order_ratio =   $set['pjmoney_old_order_ratio']; 
                }
                if ($nowqueue && $lastkey >= 0) {
                    $now_money = dd_money_format($moneyBack * $new_order_ratio * 0.01);
                    if ($now_money > 0) {
                        $everywhere = $whereQueueType;
                        $everywhere[] = ['id', '=', $nowqueue['id']];
                        self::doOrder($aid, $bid, $mid, $order, $now_money, $nowqueue, $everywhere, $set, $rate_back,1,$mode,'金额分配最新订单');
                    }
                unset($queue_list[$lastkey]);
                }
                $lastqueue = $queue_list[0];//最早
                //count($queue_list) 如果=1 是最新 不是最早 
                if ($lastqueue) {
                    $last_money = dd_money_format($moneyBack * $old_order_ratio * 0.01);
                    $everywhere = $whereQueueType;
                    $everywhere[] = ['id', '=', $lastqueue['id']];
                    self::doOrder($aid, $bid, $mid, $order, $last_money, $lastqueue, $everywhere, $set, $rate_back,1,2,'金额分配最早订单');
                unset($queue_list[0]);
                }
                $sy_queue_money = $moneyBack - $now_money - $last_money;
                foreach ($queue_list as $qk => $qv) {
                    
                    $m_ordermoney = 0 + Db::name('payorder')->where('aid', $aid)->where('bid', $bid)->where('orderid', $qv['orderid'])->where('type',$qv['type'])->value('money');;
                    //当前会员金额 / 总金额 *  $sy_queue_money   
                    $m_get_money = dd_money_format($m_ordermoney / $total_paymoney * $sy_queue_money, 2);
                   
                    $everywhere = $whereQueueType;
                    $everywhere[] = ['id', '=', $qv['id']];
                    self::doOrder($aid, $bid, $mid, $order, $m_get_money, $qv, $everywhere, $set, $rate_back,1,$mode,'金额分配');
                }
            }
        }
    }
    //平均+金额分配
    public static function  averageAndMoneyBackDo($aid,$bid,$mid,$order,$moneyBack,$whereQueueType,$set,$rate_back,$queue_list=[]){
        if(getcustom('yx_queue_free_other_mode')) {
            if ($moneyBack > 0) {
                //计算平均和金额配比的金额
                $pj_money_back = $set['pjmoney_pj_ratio'] * $moneyBack * 0.01;
                $je_money_back = $set['pjmoney_money_ratio'] * $moneyBack * 0.01;
              

                if ($pj_money_back > 0) {
                    self::averageBackDo($aid, $bid, $mid, $order, $pj_money_back, $whereQueueType, $set, $rate_back, $queue_list,3);
                }
                if ($je_money_back > 0) {
                    self::moneyBackDo($aid, $bid, $mid, $order, $je_money_back, $whereQueueType, $set, $rate_back, $queue_list,3);
                }
            }
        }
    }
    //固定+平均分配
    public static function averageAndFixedBackDo($aid,$bid,$mid,$order,$moneyBack,$whereQueueType,$set,$rate_back,$queue_list=[],$log =[]){
        if(getcustom('yx_queue_free_other_mode')) {
            if ($moneyBack > 0) {
                //计算平均和金额配比的金额
                $fixed_money_back = $set['pjfixed_fixed_ratio'] * $moneyBack * 0.01;
                $pj_money_back = dd_money_format($moneyBack - $fixed_money_back);
              
                if ($fixed_money_back > 0) {
                    self::doOrder($aid, $bid, $mid, $order, $fixed_money_back, $log, $whereQueueType, $set, $rate_back,1,0,'固定分配');
                }
                if ($pj_money_back > 0) {
                    self::averageBackDo($aid, $bid, $mid, $order, $pj_money_back, $whereQueueType, $set, $rate_back, $queue_list,4);
                }
            }
        }
    }
    //固定+今日平均（次日凌晨）+非今日平均（次日凌晨）模式
    public static function todayAverageAndFixedBackDo($aid,$bid,$mid,$order,$moneyBack,$whereQueueType,$set,$rate_back,$queue_list=[],$log =[]){
        if (getcustom('yx_queue_free_today_average')){
            if ($moneyBack > 0) {
                //计算固定  今日平均 非今日平均
                $fixed_money_back = $set['today_fixed_ratio'] * $moneyBack * 0.01;
                $today_pj_back =  $set['today_pj_ratio'] * $moneyBack * 0.01;
                $today_other_back =  $set['today_other_ratio'] * $moneyBack * 0.01;
                if ($fixed_money_back > 0) {
                    self::doOrder($aid, $bid, $mid, $order, $fixed_money_back, $log, $whereQueueType, $set, $rate_back,1,5,'固定分配');
                }
                if ($today_pj_back > 0) {
                    //其他加入分红池，等待次日凌晨发放
                    self::todayAverageAddPool($aid,$bid,$today_pj_back,$set,'today',$order,[],'当日消费订单待均分');
                }
                if($today_other_back > 0){
                    //其他加入分红池，等待次日凌晨发放
                    self::todayAverageAddPool($aid,$bid,$today_other_back,$set,'yesterday',$order,[],'非当日消费订单待均分');
                }
            }
        }
    }
    //加入分红池，等待次日凌晨发放
    public static function todayAverageAddPool($aid,$bid,$pool_money,$set,$money_type='yesterday',$order=[],$queue=[],$remark=''){
        if (getcustom('yx_queue_free_today_average')){
            $pool_where[] = ['aid','=',$aid];
            //如果设置平台排队 查询平台 如果商户排队 查询商户
            if($set['queue_type_business'] ==0 ){ //独立排队
                $pool_where[] = ['bid','=',$bid];
            }else{
                $pool_where[] = ['bid','=',0];
            }
            $pooldata = Db::name('queue_free_pool')->where($pool_where)->find();
            if($pooldata){
               if ($money_type =='today'){
                    Db::name('queue_free_pool')->where($pool_where)->inc('today_money',$pool_money)->update();
                }  elseif($money_type =='yesterday'){
                    Db::name('queue_free_pool')->where($pool_where)->inc('money',$pool_money)->update();
                }
            }else{
                $insert = [
                    'aid' =>$aid,
                    'bid' =>$bid,
                ];
                if($money_type =='today'){
                    $insert['today_money'] = $pool_money;
                }elseif ($money_type =='yesterday'){
                    $insert['money'] = $pool_money;
                }
                Db::name('queue_free_pool')->insert($insert);
            }
            //加入分红池日志
            Db::name('queue_free_pool_log')->insert([
                'aid' =>$aid,
                'bid' =>$bid,
                'money' => $pool_money,
                'queueid' => $queue['id'],
                'ordernum' => $queue?$queue['ordernum']:'',
                'type' => $order?$order['orderType']:'',
                'money_type' => $money_type?$money_type:'',
                'remark' => $remark,
                'createtime' => time()
            ]);
        }
    }
    
    //商家推荐人的提成
    public static function businessUserCommission($aid,$bid,$moneyBack){
        if(getcustom('yx_queue_free_fanli_commission')){
            //例如 消费100元，20%消费返利是20元，20%的10% 是2元为 代理合伙人的提成
            $set = Db::name('queue_free_set')->where('aid',$aid)->where('bid',0)->find();
            $moneyBack = $moneyBack * $set['queue_free_commission_ratio'] * 0.01;
            $business = Db::name('business')->where('id',$bid)->field('id,name')->find();
            if($business) {
                $buser = Db::name('admin_user')->where('aid', $aid)->where('bid', $bid)->where('isadmin', 1)->find();
                if($buser && $buser['mid']){
                    $member = Db::name('member')->where('id',$buser['mid'])->field('id,pid,levelid')->find();
                    if($member && $member['pid']){
                        $queue_free_commission = json_decode($set['queue_free_commission'],true);
                        $parent = Db::name('member')->where('id',$member['pid'])->where('aid',$aid)->field('id,pid,levelid')->find();
                        if($parent){
                            //查询上级等级信息
                            $p_ratio = $queue_free_commission[$parent['levelid']];
                            if($p_ratio > 0 ){
                                //发商家分成
                                $parentcommission = round($moneyBack * $p_ratio * 0.01,2);
                                if($parentcommission > 0){
                                    \app\commons\Member::addcommission($aid,$parent['id'],$member['id'],$parentcommission,'商户['.$business['name'].']'.t('消费返利').'提成');
                                }
                            }
                            if($parent['pid']>0){
                                //查询上上级信息
                                $parent2 = Db::name('member')->where('id',$parent['pid'])->where('aid',$aid)->field('id,levelid')->find();
                                //级差
                                if($parent2){
                                    //对应设置比例
                                    $p2_ratio = $queue_free_commission[$parent2['levelid']];
                                    if($set['queue_free_commission_jicha_status'] ==1){
                                        $p2_ratio = $p2_ratio -  $p_ratio;
                                    }
                                    if($p2_ratio > 0){
                                        //如果间推等级 大于 直推 才发（越级）
                                        //发间推商家分成
                                        $parent2commission = round($moneyBack * $p2_ratio * 0.01,2);
                                        if($parent2commission > 0){
                                            \app\commons\Member::addcommission($aid,$parent2['id'],$member['id'],$parent2commission,'商户['.$business['name'].']'.t('消费返利').'提成');
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * 分账
     * @param $aid
     * @param $bid
     * @param $mid 下单人mid
     * @param $order
     * @param $money
     * @param $queuemid
     * @param $logid
     * @param $orderType
     * @return array|void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function wxFenzhang($aid,$bid,$mid,$order,$money,$queuemid,$logid,$orderType)
    {
        if($order['paytypeid'] != 2){
            //微信支付
            $msg = '订单未使用微信支付，无法分账';
            Db::name('queue_free_log')->where('id',$logid)->update(['isfenzhang'=>2,'fz_errmsg'=>$msg]);
            return ['status'=>0,'msg'=>$msg];
        }
        $wxpaylog = Db::name('wxpay_log')->where('aid',$aid)
            ->where('mid',$mid)->where('tablename',$orderType)->where('ordernum','=',$order['ordernum'])->limit(1)->select()->toArray();
        if($wxpaylog){
//            $dbwxpayset = Db::name('sysset')->where('name','wxpayset')->value('value');
//            $dbwxpayset = json_decode($dbwxpayset,true);

            foreach($wxpaylog as $v){
                $receivers = [];
                $addreceivers = [];
                $member = Db::name('member')->where('id',$queuemid)->find();
                $openid = $v['platform']=='mp'?$member['mpopenid']:$member['wxopenid'];
                if(empty($openid)){
                    $msg = '接收用户未授权登录，无openid';
                    Db::name('queue_free_log')->where('id',$logid)->update(['isfenzhang'=>2,'fz_errmsg'=>$msg]);
                    return ['status'=>0,'msg'=>$msg];
                }
                $sub_appid = '';
                $sub_mchid = $v['sub_mchid'];
                $appinfo = \app\commons\System::appinfo($v['aid'],$v['platform']);
                if($appinfo['wxpay_type']==1){//服务商模式
                    $sub_mchid = $appinfo['wxpay_sub_mchid'];
                    $dbwxpayset = Db::name('sysset')->where('name','wxpayset')->value('value');
                    $dbwxpayset = json_decode($dbwxpayset,true);
                }else{
                    $dbwxpayset = [
                        'appid'          => $appinfo['appid'],
                        'mchid'          => $appinfo['wxpay_mchid'],
                        'mchkey'         => $appinfo['wxpay_mchkey'],
                        'apiclient_cert' => $appinfo['wxpay_apiclient_cert'],
                        'apiclient_key'  => $appinfo['wxpay_apiclient_key'],
                    ];
                }

                $amount = intval($money*100);
//                $amount2 = intval($v['fenzhangmoney2']*100);
//                $appinfo = \app\commons\System::appinfo($v['aid'],$v['platform']);
//                if(!$sub_mchid) $sub_mchid = $appinfo['wxpay_sub_mchid'];
                if($v['bid'] > 0) {
                    $bset = Db::name('business_sysset')->where('aid', $v['aid'])->find();
                    if ($bset['wxfw_status'] == 1) {
                        $dbwxpayset = [
                            'mchname'        => $bset['wxfw_mchname'],
                            'appid'          => $bset['wxfw_appid'],
                            'mchid'          => $bset['wxfw_mchid'],
                            'mchkey'         => $bset['wxfw_mchkey'],
                            'apiclient_cert' => $bset['wxfw_apiclient_cert'],
                            'apiclient_key'  => $bset['wxfw_apiclient_key'],
                        ];
                        $sub_appid = $appinfo['appid'];
                        $receivers[] = ['type'=>'PERSONAL_SUB_OPENID','account'=>$openid,'amount'=>$amount,'description'=>$sub_mchid.'排队奖励分账'];//'name'=>'',
                        $addreceivers[] = ['type'=>'PERSONAL_SUB_OPENID','account'=>$openid,'relation_type'=>'USER'];
                    }elseif($bset['wxfw_status'] == 2){
                        $dbwxpayset = Db::name('sysset')->where('name','wxpayset')->value('value');
                        $dbwxpayset = json_decode($dbwxpayset,true);
                        $sub_appid = $appinfo['appid'];
                        $receivers[] = ['type'=>'PERSONAL_SUB_OPENID','account'=>$openid,'amount'=>$amount,'description'=>$sub_mchid.'排队奖励分账'];//'name'=>'',
                        $addreceivers[] = ['type'=>'PERSONAL_SUB_OPENID','account'=>$openid,'relation_type'=>'USER'];
                    }
                    else{
                        $receivers[] = ['type'=>'PERSONAL_OPENID','account'=>$openid,'amount'=>$amount,'description'=>$sub_mchid.'排队奖励分账'];//'name'=>'',
                        $addreceivers[] = ['type'=>'PERSONAL_OPENID','account'=>$openid,'relation_type'=>'USER'];
                    }
                }else{
                    $receivers[] = ['type'=>'PERSONAL_OPENID','account'=>$openid,'amount'=>$amount,'description'=>$sub_mchid.'排队奖励分账'];//'name'=>'',
                    $addreceivers[] = ['type'=>'PERSONAL_OPENID','account'=>$openid,'relation_type'=>'USER'];
                }
                $rs = self::profitsharing($v,$receivers,$addreceivers,$sub_mchid,$dbwxpayset,$v['transaction_id'],$sub_appid,0);
                if($rs['status'] == 0){
                    \think\facade\Log::write(__FILE__.__LINE__.__FUNCTION__);
//                    \think\facade\Log::write(['wxpaylog',$v]);
//                    \think\facade\Log::write(['bset',$bset['wxfw_status']]);
//                    \think\facade\Log::write(['dbwxpayset',$dbwxpayset]);
                    \think\facade\Log::write($rs);
                    Db::name('queue_free_log')->where('id',$logid)->update(['isfenzhang'=>2,'fz_errmsg'=>$rs['msg']]);
                    return ['status'=>0,'msg'=>$rs['msg']];
                }else{
                    Db::name('queue_free_log')->where('id',$logid)->update(['isfenzhang'=>1,'fz_errmsg'=>$rs['msg'],'fz_ordernum'=>$rs['ordernum']]);
                    return ['status'=>1,'msg'=>$rs['msg']];
                }
            }
        }else{
            \think\facade\Log::write(__FILE__.__LINE__);
            \think\facade\Log::write(Db::name('wxpay_log')->getLastSql());
            \think\facade\Log::write($wxpaylog);
            $msg = '未找到微信支付记录，无法分账';
            Db::name('queue_free_log')->where('id',$logid)->update(['isfenzhang'=>2,'fz_errmsg'=>$msg]);
            return ['status'=>0,'msg'=>$msg];
        }
    }

    //分账 https://pay.weixin.qq.com/wiki/doc/api/allocation.php?chapter=27_1&index=1
    public static function profitsharing($wxpaylog,$receivers,$addreceivers,$sub_mchid,$dbwxpayset,$transaction_id,$sub_appid,$times=0){

        $mchkey = $dbwxpayset['mchkey'];
        $sslcert = ROOT_PATH.str_replace(PRE_URL.'/','',$dbwxpayset['apiclient_cert']);
        $sslkey = ROOT_PATH.str_replace(PRE_URL.'/','',$dbwxpayset['apiclient_key']);

        $pars = array();
        $pars['mch_id'] = $dbwxpayset['mchid'];
        if($sub_mchid)$pars['sub_mch_id'] = $sub_mchid;
        $pars['appid'] = $dbwxpayset['appid'];
        $pars['nonce_str'] = random(32);
        $pars['transaction_id'] = $transaction_id;
        $pars['out_order_no'] = 'P'.date('YmdHis').rand(1000,9999);
        $pars['receivers'] = jsonEncode($receivers);
        if($sub_appid){
            $pars['sub_appid'] = $sub_appid;
        }
        //$pars['sign_type'] = 'MD5';
        ksort($pars, SORT_STRING);
        $string1 = '';
        foreach ($pars as $k => $v) {
            $string1 .= "{$k}={$v}&";
        }
        $string1 .= "key=" . $mchkey;
        //$pars['sign'] = strtoupper(md5($string1));
        $pars['sign'] = strtoupper(hash_hmac("sha256",$string1 ,$mchkey));
        $xml = array2xml($pars);
        Log::write(__FILE__.__LINE__.__FUNCTION__);
        Log::write($pars);
        Log::write($xml);
        //Log::write($sslcert);
        $exist = Db::name('wxpay_fzlog')->where('transaction_id',$wxpaylog['transaction_id'])->where('receiversjson',$pars['receivers'])->find();
        if(!$exist){
            $insert = [
                'aid'=>$wxpaylog['aid'],
                'bid'=>$wxpaylog['bid'],
                'mid'=>$wxpaylog['mid'],
                'logid'=>$wxpaylog['id'],
                'openid'=>$wxpaylog['openid'],
                'tablename'=>$wxpaylog['tablename'],
                'ordernum'=>$wxpaylog['ordernum'],
                'mch_id'=>$wxpaylog['mch_id'],
                'sub_mchid'=>$wxpaylog['sub_mchid'],
                'transaction_id'=>$wxpaylog['transaction_id'],
                'out_order_no'=>$pars['out_order_no'],
                'receiversjson'=>$pars['receivers'],
                'createtime'=>time(),
                'fz_ordernum'=>$pars['out_order_no'],
                'platform'=>$wxpaylog['platform']
            ];
            $fzlogid = Db::name('wxpay_fzlog')->insertGetId($insert);
        }else{
            Db::name('wxpay_fzlog')->where('transaction_id',$wxpaylog['transaction_id'])->where('id',$exist['id'])
                ->update(['out_order_no'=>$pars['out_order_no'],'fz_ordernum'=>$pars['out_order_no'],'createtime'=>time()]);
            $fzlogid = $exist['id'];
        }

        $ch = curl_init ();
        curl_setopt ( $ch, CURLOPT_URL, "https://api.mch.weixin.qq.com/secapi/pay/multiprofitsharing" );
        curl_setopt ( $ch, CURLOPT_CUSTOMREQUEST, "POST" );
        curl_setopt ( $ch, CURLOPT_SSL_VERIFYPEER, FALSE );
        curl_setopt ( $ch, CURLOPT_SSL_VERIFYHOST, FALSE );
        curl_setopt ( $ch, CURLOPT_SSLCERT,$sslcert);
        curl_setopt ( $ch, CURLOPT_SSLKEY,$sslkey);
        curl_setopt ( $ch, CURLOPT_FOLLOWLOCATION, 1 );
        curl_setopt ( $ch, CURLOPT_AUTOREFERER, 1 );
        curl_setopt ( $ch, CURLOPT_POSTFIELDS, $xml );
        curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, true );

        $info = curl_exec ( $ch );
        curl_close ( $ch );
        //Log::write($info);
        $resp = (array)(simplexml_load_string($info,'SimpleXMLElement', LIBXML_NOCDATA));
        Log::write($resp);
        if($resp['return_code'] == 'SUCCESS' && $resp['result_code']=='SUCCESS'){
            Db::name('wxpay_fzlog')->where('transaction_id',$wxpaylog['transaction_id'])->where('id',$fzlogid)
                ->update(['isfenzhang'=>1,'fz_errmsg'=>'']);
            return ['status'=>1,'msg'=>'分账成功','resp'=>$resp,'ordernum'=>$pars['out_order_no'],'fzlogid'=>$fzlogid];
        }else{
            //Log::write('profitsharing');
            //Log::write($resp);
            if($times == 0 && ($resp['err_code'] == 'PARAM_ERROR' || $resp['err_code'] == 'RECEIVER_INVALID' || $resp['err_code_des'] == '服务商和分账方无受理关系')){
                //if($times == 0 && $resp['err_code'] == 'RECEIVER_INVALID'){
                foreach($addreceivers as $addreceiver){
                    $pars = array();
                    $pars['mch_id'] = $dbwxpayset['mchid'];
                    if($sub_mchid) $pars['sub_mch_id'] = $sub_mchid;
                    $pars['appid'] = $dbwxpayset['appid'];
                    $pars['nonce_str'] = random(32);
                    $pars['receiver'] = jsonEncode($addreceiver);
                    if($sub_appid){
                        $pars['sub_appid'] = $sub_appid;
                    }
                    //$pars['sign_type'] = 'MD5';
                    ksort($pars, SORT_STRING);
                    $string1 = '';
                    foreach ($pars as $k => $v) {
                        $string1 .= "{$k}={$v}&";
                    }
                    $string1 .= "key=" . $mchkey;
                    //$pars['sign'] = strtoupper(md5($string1));
                    $pars['sign'] = strtoupper(hash_hmac("sha256",$string1 ,$mchkey));
                    $xml = array2xml($pars);
                    $ch = curl_init ();
                    curl_setopt ( $ch, CURLOPT_URL, "https://api.mch.weixin.qq.com/secapi/pay/profitsharingaddreceiver" );
                    curl_setopt ( $ch, CURLOPT_CUSTOMREQUEST, "POST" );
                    curl_setopt ( $ch, CURLOPT_SSL_VERIFYPEER, FALSE );
                    curl_setopt ( $ch, CURLOPT_SSL_VERIFYHOST, FALSE );
                    curl_setopt ( $ch, CURLOPT_SSLCERT,$sslcert);
                    curl_setopt ( $ch, CURLOPT_SSLKEY,$sslkey);
                    curl_setopt ( $ch, CURLOPT_FOLLOWLOCATION, 1 );
                    curl_setopt ( $ch, CURLOPT_AUTOREFERER, 1 );
                    curl_setopt ( $ch, CURLOPT_POSTFIELDS, $xml );
                    curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, true );
                    $info = curl_exec ( $ch );
                    curl_close ( $ch );
                    Log::write('profitsharingaddreceiver');
                    Log::write($info);
                    sleep(2);
                }
                return self::profitsharing($wxpaylog,$receivers,$addreceivers,$sub_mchid,$dbwxpayset,$transaction_id,$sub_appid,1);
            }
            $msg = '未知错误';
            if ($resp['return_code'] == 'FAIL') {
                $msg = $resp['return_msg'];
            }
            if ($resp['result_code'] == 'FAIL') {
                $msg = $resp['err_code_des'];
            }
            Db::name('wxpay_fzlog')->where('transaction_id',$wxpaylog['transaction_id'])->where('id',$fzlogid)
                ->update(['isfenzhang'=>2,'fz_errmsg'=>$msg]);
            return ['status'=>0,'msg'=>$msg,'resp'=>$resp,'fzlogid'=>$fzlogid];
        }
    }

    public function profitsharingfinish($aid,$transaction_id,$log){
        $appinfo = \app\commons\System::appinfo($aid,$log['platform']);
        $sub_mchid = $log['sub_mchid'];
        if(!$log['sub_mchid'] && $appinfo['wxpay_type']==1) $sub_mchid = $appinfo['wxpay_sub_mchid'];
        if($log['paysetjson']){
            $dbwxpayset = json_decode($log['paysetjson'],true);
        }else{
            if($appinfo['wxpay_type']==1){//服务商模式
                $dbwxpayset = Db::name('sysset')->where('name','wxpayset')->value('value');
                $dbwxpayset = json_decode($dbwxpayset,true);
//                if(!$dbwxpayset){
//                    return ['status'=>0,'msg'=>'未配置服务商微信支付信息'];
//                }
            }else{
                $dbwxpayset = [
                    'appid'          => $appinfo['appid'],
                    'mchid'          => $appinfo['wxpay_mchid'],
                    'mchkey'         => $appinfo['wxpay_mchkey'],
                    'apiclient_cert' => $appinfo['wxpay_apiclient_cert'],
                    'apiclient_key'  => $appinfo['wxpay_apiclient_key'],
                ];
            }
            if($log['bid'] > 0) {
                $bset = Db::name('business_sysset')->where('aid', $log['aid'])->find();
                if ($bset['wxfw_status'] == 1) {
                    $dbwxpayset = [
                        'mchname'        => $bset['wxfw_mchname'],
                        'appid'          => $bset['wxfw_appid'],
                        'mchid'          => $bset['wxfw_mchid'],
                        'mchkey'         => $bset['wxfw_mchkey'],
                        'apiclient_cert' => $bset['wxfw_apiclient_cert'],
                        'apiclient_key'  => $bset['wxfw_apiclient_key'],
                    ];
                    $sub_appid = $appinfo['appid'];
                }elseif($bset['wxfw_status'] == 2){
                    $dbwxpayset = Db::name('sysset')->where('name','wxpayset')->value('value');
                    $dbwxpayset = json_decode($dbwxpayset,true);
                    $sub_appid = $appinfo['appid'];
                }
            }
        }

        $mchkey = $dbwxpayset['mchkey'];
        $sslcert = ROOT_PATH.str_replace(PRE_URL.'/','',$dbwxpayset['apiclient_cert']);
        $sslkey = ROOT_PATH.str_replace(PRE_URL.'/','',$dbwxpayset['apiclient_key']);

        $pars = [];
        $pars['mch_id'] = $dbwxpayset['mchid'];
        if($sub_mchid) $pars['sub_mch_id'] = $sub_mchid;
        $pars['appid'] = $dbwxpayset['appid'];
        $pars['nonce_str'] = random(32);
        $pars['transaction_id'] = $transaction_id;
        $pars['out_order_no'] = 'P'.date('YmdHis').rand(1000,9999);
        $pars['description'] = '分账已完成';
        //$pars['sign_type'] = 'MD5';
        ksort($pars, SORT_STRING);
        $string1 = '';
        foreach ($pars as $k => $v) {
            $string1 .= "{$k}={$v}&";
        }
        $string1 .= "key=" . $mchkey;
        $pars['sign'] = strtoupper(hash_hmac("sha256",$string1 ,$mchkey));
        Log::write(__FILE__.__LINE__.__FUNCTION__);
        Log::write($pars);
//        Log::write(['dbwxpayset'=>$dbwxpayset]);
        $dat = array2xml($pars);
        $client = new \GuzzleHttp\Client(['timeout'=>30,'verify'=>false]);
        $response = $client->request('POST',"https://api.mch.weixin.qq.com/secapi/pay/profitsharingfinish",
            ['body'=>$dat,'cert'=>$sslcert,'ssl_key'=>$sslkey]);
        $info = $response->getBody()->getContents();

        $resp = (array)(simplexml_load_string($info,'SimpleXMLElement', LIBXML_NOCDATA));
        Log::write($resp);
        if($resp['return_code'] == 'SUCCESS' && $resp['result_code']=='SUCCESS'){
            Db::name('wxpay_fzlog')->where('transaction_id',$transaction_id)->update(['isfinish'=>1]);
            return ['status'=>1,'msg'=>'ok'];
        }else{
            $msg = '未知错误';
            if ($resp['return_code'] == 'FAIL') {
                $msg = $resp['return_msg'];
            }
            if ($resp['result_code'] == 'FAIL') {
                $msg = $resp['err_code_des'];
            }
            Db::name('wxpay_fzlog')->where('transaction_id',$transaction_id)->inc('finish_error_times',1)->update(['finish_error_time'=>time()]);
            Log::write($resp,'error');
            return ['status'=>0,'msg'=>$msg,'resp'=>$resp];
        }
    }

    //订单退款 排队退出
    public static function orderRefundQuit($order=[],$orderType = 'shop'){
       
        $qwhere = [];
        $qwhere[] = ['aid','=',$order['aid']];
        $qwhere[] = ['bid','=',$order['bid']];
        $qwhere[] = ['status','=',0];
        $qwhere[] = ['quit_queue','=',0];
        $qwhere[] = ['type','=',$orderType];
        $qwhere[] = ['orderid','=',$order['id']];
        $queue_free = Db::name('queue_free')->where($qwhere)->find();
        if($queue_free){
            Db::name('queue_free')->where($qwhere)->update(['quit_queue' => 1]);
        }
    }                                         
    // 固定+今日平均+其他平均（次日凌晨）模式的其他平均
    public function  todayAverageOtherFafang(){
        if(getcustom('yx_queue_free_today_average')){
            //先查询出平台的设置，再根据排队的类型 （平台排队，商户自己排队）
            $set_list= Db::name('queue_free_set')->where('bid',0)->select()->toArray();
            $new_set_list = [];
            foreach($set_list as $key=>$set){
                if(!$set['status']) continue;
                //如果模式不是 今日平均不发放
                if($set['mode'] !=5) continue;
                $new_set_list[] = $set;
                if($set['queue_type_business'] == 0){
                    $business_queue_list =  Db::name('queue_free_set')->where('aid',$set['aid'])->where('bid','>',0)->select()->toArray();
                    $new_set_list = array_merge($new_set_list,$business_queue_list);
                }
            }
            foreach($new_set_list as $k=>$queue_set){
                //查询前天的排队 
                $order = ['is_not_today' => 1];
                $aid  = $queue_set['aid'];
                $bid  = $queue_set['bid'];
                $mid = 0;
                //平台的设置
                $set = Db::name('queue_free_set')->where('aid',$aid)->where('bid',0)->find();
                $rate_back = $queue_set['rate_back'];
                //非当日发放
               
                $whereQueueType = [];
                $whereQueueType[] = ['aid','=',$aid];
                $whereQueueType[]=['quit_queue','=',0];
                if($set['queue_type_business'] != 1){
                    $whereQueueType[] = ['bid','=',$bid];
                }

                $queue_where =  $whereQueueType;
                $yesterday_end = strtotime(date('Y-m-d 23:59:59', strtotime('-2 day')));
                $queue_where[]=['createtime','<=',$yesterday_end];
                $queue_list = Db::name('queue_free')->where($queue_where)->where('status', 0)->order('id asc')->select()->toArray();
                $moneyBack = Db::name('queue_free_pool')->where('aid',$aid)->where('bid',$bid)->value('money');
                
                if($moneyBack > 0 && $queue_list){
                    self::averageBackDo($aid,$bid,$mid,$order,$moneyBack,$whereQueueType,$set,$rate_back,$queue_list,5,'非当日平均分配');
                    self::todayAverageAddPool ($aid,$bid,$moneyBack*-1,$set,'yesterday',$order,0,'非当日消费订单均分');
                }
                //当日发放
                $today_where =  $whereQueueType;
                $today_start = strtotime(date('Y-m-d 00:00:01', strtotime('-1 day')));
                $today_end = strtotime(date('Y-m-d 23:59:59', strtotime('-1 day')));
                $today_where[]=['createtime','between',[$today_start,$today_end]];
                $today_queue_list = Db::name('queue_free')->where($today_where)->where('status', 0)->order('id asc')->select()->toArray();
                $todayMoneyBack = Db::name('queue_free_pool')->where('aid',$aid)->where('bid',$bid)->value('today_money');
                if($todayMoneyBack > 0 && $today_queue_list){
                    self::averageBackDo($aid,$bid,$mid,$order,$todayMoneyBack,$whereQueueType,$set,$rate_back,$today_queue_list,5,'当日平均分配');
                    self::todayAverageAddPool ($aid,$bid,$todayMoneyBack*-1,$set,'today',$order,0,'当日消费订单均分');
                }
            }
        }
   }
}