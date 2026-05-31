<?php


//买单
namespace app\controllers;
use think\facade\Db;
class ApiAdminMaidan extends ApiAdmin
{	
	public function index()
    {
        $range = input('param.range/d',1);//时间范围，1今天 2昨天 3本月 4上月
        switch ($range){
            case 5://自定义
                $rangeType = input('param.rangType/d',1);
                if($rangeType==1){
                    $month = input('param.month','');
                    if(empty($month)){
                        return $this->json(['status'=>0,'msg'=>'请选择月份']);
                    }
                    $endtime = strtotime(date($month.'-01 00:00:00'));
                    $days = date('t',$endtime-10);
                    $starttime = $endtime - 86400 * $days;
                    $step = 86400*5;
                }else{
                    $start_date = input('param.start_date');
                    $end_date = input('param.end_date');
                    if(empty($start_date) || empty($end_date)){
                        return $this->json(['status'=>0,'msg'=>'请选择开始和结束时间']);
                    }
                    $starttime = strtotime($start_date);
                    $endtime = strtotime($end_date)+86400;
                    if($endtime>time()){
                        $endtime = time();
                    }
                    if($endtime<$starttime){
                        return $this->json(['status'=>0,'msg'=>'结束时间不得小于开始日期']);
                    }
                    if($endtime-$starttime<=86400){
                        $step = 3600*4;
                    }elseif ($endtime-$starttime<=7*86400){
                        $step = 86400;
                    }else{
                        $step = ceil(($endtime - $starttime)/12);
                    }
                }
                break;
            case 4:
                $endtime = strtotime(date('Y-m-01 00:00:00'));
                $days = date('t',$endtime-10);
                $starttime = $endtime - 86400 * $days;
                $step = 86400*5;
                break;
            case 3:
                $starttime = strtotime(date('Y-m-01 00:00:00'));
//                $days = date('t',$starttime+10);
                $endtime = strtotime(date('Y-m-d 24:00:00',time()));
//                $step = 86400*5;
                $days = ($endtime-$starttime)/86400;
                $step = ceil($days/6) * 86400;
                break;
            case 2:
                $endtime = strtotime(date('Y-m-d 00:00:00'));
                $starttime = $endtime - 86400;
                $step = 3600*4;
                break;
            default:
                $starttime = strtotime(date('Y-m-d 00:00:00'));
                $endtime = strtotime(date('Y-m-d H:i:s',time()));
//                $step = 3600*4;
                $hours = ($endtime - $starttime)/3600;
                $step = ceil($hours/6) * 3600;
                break;
        }
        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['bid','=',bid];
        $where[] = ['status','=',1];
        $where[] = ['paytime','between',[$starttime,$endtime]];
        if($this->user['mdid']){
            $where[] = ['mdid','=',$this->user['mdid']];
        }
        $paytypelist = Db::name('maidan_order')->where($where)->group('paytype')->field('paytypeid,paytype')->order('paytypeid desc')->select()->toArray();
        $allAmount = round(Db::name('maidan_order')->where($where)->sum('money'),2);
        foreach ($paytypelist as $key=>$value){
            $map = $where;
            if(!$value['paytype']){
                $paytypelist[$key]['paytypeid']  = '-1';
                $paytypelist[$key]['paytype']  = '其他支付';
                $map[] = Db::raw("paytype='' OR paytype IS NULL");
            }else{
                $map[] = ['paytype','=',$value['paytype']];
            }
            $totalAmount = round(Db::name('maidan_order')->where($map)->sum('money'),2);
            $paytypelist[$key]['total_amount']  = $totalAmount;
        }
        $paytypelist = array_merge([['paytypeid'=>9999,'paytype'=>'收款总额','total_amount'=>$allAmount]],$paytypelist);
        $xData = $yData = $yData1 = [];
        for($time=$starttime;$time<=$endtime;$time=$time+$step){
            $whereT = [];
            $whereT[] = ['aid','=',aid];
            $whereT[] = ['bid','=',bid];
            $whereT[] = ['status','=',1];
            if($this->user['mdid']){
                $whereT[] = ['mdid','=',$this->user['mdid']];
            }
            $whereT[] = ['paytime','between',[$starttime,$time+$step]];
            $payorderList = Db::name('maidan_order')->where($whereT)->select()->toArray();
            $times = 0;
            $money = 0;
            foreach ($payorderList as $k=>$order){
                $money = $money+$order['money'];
                $times++;
            }
            if($step>3600*4){
                $xData[] = date('m/d',$time);
            }else{
                $xData[] = date('H:i',$time);
            }
            $yData[] = round($money,2);
            $yData1[] = $times;
        }
        $chartdata = [
            'xData'=>$xData,//日期
            'yData'=>$yData,//收款金额
            'yData1'=>$yData1//收款笔数
        ];
        return $this->json(['status'=>1,'paytypelist'=>$paytypelist,'chartdata'=>$chartdata,'curdate'=>date('Y-m-d',time())]);
    }

    public function maidanlog(){
        $pagenum = input('post.pagenum');
        $st = input('post.st');
        if(!$pagenum) $pagenum = 1;
        $pernum = 20;
        $where = [];
        $where[] = ['maidan_order.aid','=',aid];
        $where[] = ['maidan_order.bid','=',bid];
        $where[] = ['maidan_order.status','=',1];
        if($this->user['mdid']){
            $where[] = ['maidan_order.mdid','=',$this->user['mdid']];
        }
        if(input('param.keyword')){
            $where[] = ['member.nickname|maidan_order.ordernum|maidan_order.paynum','like','%'.input('param.keyword').'%'];
        }
        if($pagenum == 1){
            $count = 0 + Db::name('maidan_order')->alias('maidan_order')->field('member.nickname,member.headimg,maidan_order.*')->join('member member','member.id=maidan_order.mid','left')->where($where)->count();
        }else{
            $count = 0;
        }
        $datalist = Db::name('maidan_order')->alias('maidan_order')->field('member.nickname,member.headimg,maidan_order.*')->join('member member','member.id=maidan_order.mid','left')->where($where)->page($pagenum,$pernum)->order('maidan_order.id desc')->select()->toArray();
        if(!$datalist) $datalist = [];
        //按日期分组
        $newlist = [];
        foreach ($datalist as $k=>$v){
            $datekey = date('Ymd',$v['paytime']);
            if(empty($v['paynum'])){
                $v['paynum'] = '';
            }
            if(empty($v['paytype'])){
                $v['paytype'] = '';
            }
            $v['paytime'] = $v['paytime']?date('Y-m-d H:i:s',$v['paytime']):'';
            $newlist[$datekey][] = $v;
        }
        $newdatalist = [];
        $lastdate = '';
        foreach ($newlist as $date=>$datelist){
            $tmp = ['date'=>date('Y-m-d',strtotime($date)),'datelist'=>$datelist];
            $lastdate = $tmp['date'];
            $newdatalist[] = $tmp;
        }
        $canrefund = false;
        $rdata = [];
        $rdata['count'] = $count;
        $rdata['data'] = $newdatalist;
        $rdata['lastdate'] = $lastdate;
        $rdata['canrefund'] = $canrefund;
        return $this->json($rdata);
    }
    public function maidandetail(){
        $id = input('param.id/d');
        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['bid','=',bid];
        $where[] = ['id','=',$id];
        $detail = Db::name('maidan_order')->where($where)->find();
        $detail['paytime'] = $detail['paytime']?date('Y-m-d H:i:s',$detail['paytime']):'';
        $detail['paynum'] = $detail['paynum']?$detail['paynum']:'-';
        if($detail['couponrid']){
            $couponrecord = Db::name('coupon_record')->where(['aid'=>aid,'id'=>$detail['couponrid']])->find();
        }else{
            $couponrecord = false;
        }
        if($detail['mdid']){
            $mendian = Db::name('mendian')->field('id,name')->where(['aid'=>aid,'id'=>$detail['mdid']])->find();
        }else{
            $mendian = false;
        }
        $canrefund = false;
        $member = Db::name('member')->where(['id'=>$detail['mid']])->find();
        if(!$member) $member = [];
        $rdata = [];
        $rdata['detail'] = $detail;
        $rdata['couponrecord'] = $couponrecord;
        $rdata['mendian'] = $mendian;
        $rdata['member'] = $member;
        $rdata['canrefund'] = $canrefund;
        return $this->json($rdata);
    }
    public function maidanrefund(){
        $id = input('param.id/d',0);
        $refund_money = input('param.money',0);
        $remark = input('param.remark','');
        if(empty($refund_money) || $refund_money<0){
            return $this->json(['status'=>0,'msg'=>'退款金额有误']);
        }
        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['bid','=',bid];
        $where[] = ['id','=',$id];
        $order = Db::name('maidan_order')->where($where)->find();
        if($refund_money>$order['paymoney']-$order['refund_money']){
            return $this->json(['status'=>0,'msg'=>'可退款金额不足']);
        }
        $data = [
            'aid' => $order['aid'],
            'bid' => $order['bid'],
            'mdid' => $order['mdid'],
            'mid' => $order['mid'],
            'orderid' => $order['id'],
            'ordernum' => $order['ordernum'],
            'title' => $order['title'],
            'refund_type' => 'refund',//退款
            'refund_ordernum' => '' . date('ymdHis') . rand(100000, 999999),
            'refund_money' => $refund_money,
            'refund_reason' => $remark,
            'createtime' => time(),
            'refund_time' => time(),
            'status' => 1,
            'refund_status' => 0,//退款成功2
            'platform' => platform,
            'uid'=>$this->uid
        ];
        $refund_id = Db::name('maidan_refund_order')->insertGetId($data);

        $rs = \app\commons\Order::refund($order,$refund_money,$remark);

        if($rs && $rs['status']==1){
            //退款成功
            Db::name('maidan_refund_order')->where('id',$refund_id)->update(['refund_status'=>2]);
            Db::name('maidan_order')->where('id',$order['id'])->inc('refund_money',$refund_money)->update();
            $status = 1;
            $msg = '退款成功';
        }else{
            Db::name('maidan_refund_order')->where('id',$refund_id)->update(['refund_status'=>5,'refund_checkremark'=>$rs['msg']??'']);//退款失败
            $status = 0;
        }
        return $this->json(['status'=>$status,'msg'=>$msg]);
    }
}