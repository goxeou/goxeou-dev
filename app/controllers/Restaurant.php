<?php




namespace app\controllers;


use think\facade\Db;
use think\facade\View;

class Restaurant extends Common
{

    public function index()
    {
        //统计菜品，餐桌，员工，打印机
        //统计订单收入 订单数量,交易金额,交易明细
        //平台 指定期间 外卖 预定 排队 等订单数量统计
        //统计小程序每日来访用户数量,新增用户数量,用户设备比例,用户性别比例,用户年龄段比例,
        $monthEnd = strtotime(date('Y-m-d',time()-86400));
        $monthStart = $monthEnd - 86400 * 29;

        if(input('post.op') == 'getdata'){
            $dataArr = array();
            $dateArr = array();
            for($i=0;$i<30;$i++){
                $thisDayStart = $monthStart + $i * 86400;
                $thisDayEnd = $monthStart + ($i+1) * 86400;
                $dateArr[] = date('m-d',$thisDayStart);
                if($_POST['type']==1){//客户数
                    $dataArr[] = 0 + Db::name('member')->where('aid',aid)->where('createtime','<',$thisDayEnd)->count();
                }elseif($_POST['type']==2){//新增客户数
                    $dataArr[] = 0 + Db::name('member')->where('aid',aid)->where('createtime','>=',$thisDayStart)->where('createtime','<',$thisDayEnd)->count();
                }elseif($_POST['type']==3){//收款金额
                    $dataArr[] = 0 + Db::name('payorder')->where('aid',aid)->where('createtime','>=',$thisDayStart)->where('createtime','<',$thisDayEnd)->where('paytypeid','<>','1,4')->where('status',1)->where('type','like',"restaurant%")->sum('money');
                }elseif($_POST['type']==4){//订单金额
                    $dataArr[] = 0 + Db::name('restaurant_shop_order')->where('aid',aid)->where('bid',bid)->where('paytime','>=',$thisDayStart)->where('paytime','<',$thisDayEnd)->where('status','in','1,2,3')->sum('totalprice') + Db::name('restaurant_takeaway_order')->where('aid',aid)->where('bid',bid)->where('paytime','>=',$thisDayStart)->where('paytime','<',$thisDayEnd)->where('status','in','1,2,3')->sum('totalprice');
                }elseif($_POST['type']==5){//订单数
                    $dataArr[] = 0 + Db::name('restaurant_shop_order')->where('aid',aid)->where('bid',bid)->where('paytime','>=',$thisDayStart)->where('paytime','<',$thisDayEnd)->where('status','in','1,2,3')->count() + Db::name('restaurant_takeaway_order')->where('aid',aid)->where('bid',bid)->where('paytime','>=',$thisDayStart)->where('paytime','<',$thisDayEnd)->where('status','in','1,2,3')->count();
                }
            }
            return json(['dateArr'=>$dateArr,'dataArr'=>$dataArr]);
        }
        $dataArr = array();
        $dateArr = array();
        for($i=0;$i<30;$i++){
            $thisDayStart = $monthStart + $i * 86400;
            $thisDayEnd = $monthStart + ($i+1) * 86400;
            $dateArr[] = date('m-d',$thisDayStart);
            if(bid == 0){
                $dataArr[] = 0 + Db::name('member')->where('aid',aid)->where('createtime','<',$thisDayEnd)->count();
            }else{
                $dataArr[] = 0 + Db::name('restaurant_shop_order')->where('aid',aid)->where('bid',bid)->where('paytime','>=',$thisDayStart)->where('paytime','<',$thisDayEnd)->where('status','in','1,2,3')->sum('totalprice') + Db::name('restaurant_takeaway_order')->where('aid',aid)->where('bid',bid)->where('paytime','>=',$thisDayStart)->where('paytime','<',$thisDayEnd)->where('status','in','1,2,3')->sum('totalprice');
            }
        }

        $lastDayStart = strtotime(date('Y-m-d',time()-86400));
        $lastDayEnd = $lastDayStart + 86400;
        $thisMonthStart = strtotime(date('Y-m-1'));
        $nowtime = time();

        //商品数量
        $data['productCount'] = 0 + Db::name('restaurant_product')->where('aid',aid)->where('bid',bid)->count();
        $data['productCount0'] = 0 + Db::name('restaurant_product')->where('aid',aid)->where('bid',bid)->where('status',0)->count();
        $data['productCount1'] = 0 + Db::name('restaurant_product')->where('aid',aid)->where('bid',bid)->where('status',1)->count();

        //餐桌数量
        $data['tableCount'] = 0 + Db::name('restaurant_table')->where('aid',aid)->where('bid',bid)->count();
        $data['tableCount0'] = 0 + Db::name('restaurant_table')->where('aid',aid)->where('bid',bid)->where('status',0)->count();
        $data['tableCount2'] = 0 + Db::name('restaurant_table')->where('aid',aid)->where('bid',bid)->where('status',2)->count();

        //订单数
        $data['shopOrderCount'] = 0 + Db::name('restaurant_shop_order')->where('aid',aid)->where('bid',bid)->where('status','in','1,2,3')->count();
        $data['shopOrderLastDayCount'] = 0 + Db::name('restaurant_shop_order')->where('aid',aid)->where('bid',bid)->where('status','in','1,2,3')->where('paytime','>=',$lastDayStart)->where('paytime','<',$lastDayEnd)->count();
        $data['shopOrderThisMonthCount'] = 0 + Db::name('restaurant_shop_order')->where('aid',aid)->where('bid',bid)->where('status','in','1,2,3')->where('paytime','>=',$thisMonthStart)->where('paytime','<',$nowtime)->count();

        //订单金额
        $data['shopOrderMoney'] = 0 + Db::name('restaurant_shop_order')->where('aid',aid)->where('bid',bid)->where('status','in','1,2,3')->sum('totalprice');
        $data['shopOrderMoneyLastDayCount'] = 0 + Db::name('restaurant_shop_order')->where('aid',aid)->where('bid',bid)->where('paytime','>=',$lastDayStart)->where('paytime','<',$lastDayEnd)->where('status','in','1,2,3')->sum('totalprice');
        $data['shopOrderMoneyThisMonthCount'] = 0 + Db::name('restaurant_shop_order')->where('aid',aid)->where('bid',bid)->where('paytime','>=',$thisMonthStart)->where('paytime','<',$nowtime)->where('status','in','1,2,3')->sum('totalprice');

        //退款金额
        $data['refundMoney'] = 0 + Db::name('restaurant_shop_order')->where('aid',aid)->where('bid',bid)->where('refund_status',2)->sum('refund_money');
        $data['refundMoneyLastDay'] = 0 + Db::name('restaurant_shop_order')->where('aid',aid)->where('bid',bid)->where(['createtime'=>[['>=',$lastDayStart],['<',$lastDayEnd]],'refund_status'=>2])->sum('refund_money');
        $data['refundMoneyThisMonth'] = 0 + Db::name('restaurant_shop_order')->where('aid',aid)->where('bid',bid)->where('createtime','>=',$thisMonthStart)->where('createtime','<',$nowtime)->where('refund_status',2)->sum('refund_money');

        //订单数
        $data['takeawayOrderCount'] = 0 + Db::name('restaurant_takeaway_order')->where('aid',aid)->where('bid',bid)->where('status','in','1,2,3')->count();
        $data['takeawayOrderLastDayCount'] = 0 + Db::name('restaurant_takeaway_order')->where('aid',aid)->where('bid',bid)->where('status','in','1,2,3')->where('paytime','>=',$lastDayStart)->where('paytime','<',$lastDayEnd)->count();
        $data['takeawayOrderThisMonthCount'] = 0 + Db::name('restaurant_takeaway_order')->where('aid',aid)->where('bid',bid)->where('status','in','1,2,3')->where('paytime','>=',$thisMonthStart)->where('paytime','<',$nowtime)->count();

        //订单金额
        $data['takeawayOrderMoney'] = 0 + Db::name('restaurant_takeaway_order')->where('aid',aid)->where('bid',bid)->where('status','in','1,2,3')->sum('totalprice');
        $data['takeawayOrderMoneyLastDayCount'] = 0 + Db::name('restaurant_takeaway_order')->where('aid',aid)->where('bid',bid)->where('paytime','>=',$lastDayStart)->where('paytime','<',$lastDayEnd)->where('status','in','1,2,3')->sum('totalprice');
        $data['takeawayOrderMoneyThisMonthCount'] = 0 + Db::name('restaurant_takeaway_order')->where('aid',aid)->where('bid',bid)->where('paytime','>=',$thisMonthStart)->where('paytime','<',$nowtime)->where('status','in','1,2,3')->sum('totalprice');

        //退款金额
        $data['takeawayRefundMoney'] = 0 + Db::name('restaurant_takeaway_order')->where('aid',aid)->where('bid',bid)->where('refund_status',2)->sum('refund_money');
        $data['takeawayRefundMoneyLastDay'] = 0 + Db::name('restaurant_takeaway_order')->where('aid',aid)->where('bid',bid)->where(['createtime'=>[['>=',$lastDayStart],['<',$lastDayEnd]],'refund_status'=>2])->sum('refund_money');
        $data['takeawayRefundMoneyThisMonth'] = 0 + Db::name('restaurant_takeaway_order')->where('aid',aid)->where('bid',bid)->where('createtime','>=',$thisMonthStart)->where('createtime','<',$nowtime)->where('refund_status',2)->sum('refund_money');
        View::assign('bid',bid);
        View::assign('data',$data);
        View::assign('dateArr',$dateArr);
        View::assign('dataArr',$dataArr);

        return View::fetch();
    }

    public function sysset()
    {
        if(bid == 0){
            //平台
            $rs = Db::name('restaurant_admin_set')->where('aid',aid)->find();
            if(empty($rs)) {
                $data = [
                    'aid' => aid,
                ];
                Db::name('restaurant_admin_set')->insert($data);
                $rs = Db::name('restaurant_admin_set')->where('aid',aid)->find();
            }
            if(request()->isPost()){
                $info = input('post.info/a');
                if($rs){
                    Db::name('restaurant_admin_set')->where('aid',aid)->update($info);
                }else{
                    $info['aid'] = aid;
                    Db::name('restaurant_admin_set')->insert($info);
                }
                \app\commons\System::plog('餐饮系统设置');
                return json(['status'=>1,'msg'=>'设置成功','url'=>true]);
            }
            if($this->auth_data == 'all' || in_array('Business/*',$this->auth_data)){
                $rs['business_auth'] = true;
            }
            $bset = Db::name('business_sysset')->where('aid',aid)->find();   
            if($bset['wxfw_status']==0){
                $rs['duli_disabled'] = true;
            }
            View::assign('info',$rs);
            return View::fetch();
        }

    }

    public function tongji(){
        }
    
    public function tjexcel(){
        }
}