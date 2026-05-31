<?php




namespace app\controllers;

use app\commons\Common;
use think\facade\Db;

class ApiRestaurantBooking extends ApiCommon
{
    public function add()
    {
        $bid = input('param.bid/d',0);
//        $bid = $bid?$bid:bid;
        $this->checklogin();
        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['bid','=',$bid];
        $set = Db::name('restaurant_booking_sysset')->where($where)->find();
        if($set['status'] != 1) {
            return $this->json(['status'=>0,'data'=>'未开启预定']);
        }

        if(request()->isPost()){
            $info = input('param.info');
            if(!checkTel($info['tel'])){
                return json(['status'=>0,'msg'=>'请检查手机号格式']);
            }
            $table = Db::name('restaurant_table')->where('id', $info['tableId'])->find();
            $tableName = $table['name'];
            if(empty($table)){
                return json(['status'=>0,'msg'=>'预定餐桌不存在']);
            }

            $booking_fee = 0;
            $tableCat = Db::name('restaurant_table_category')->where('id',$table['cid'])->where('aid', aid)->where('bid', $bid)->find();
            if($tableCat['booking_fee'] > 0){
                $booking_fee = $tableCat['booking_fee'];
            }else{
                $booking_fee = $set['pay_money'];
                }
            $booking_fee = $set['pay'] ? $booking_fee : 0;
            $insert = [
                'aid' => aid,
                'bid' => $bid,
                'mid' => mid,
                'ordernum' => Common::generateOrderNo(aid,'booking_order'),
                'tableid' => $info['tableId'],
                'table_cid' => 0,
                'seat' => $info['renshu'],
                'booking_time' => $info['time'],
                'tel' => $info['tel'],
                'message' => $info['message'],
                'linkman' => $info['linkman'],
                'platform' => platform,
                'createtime' => time(),
                'check_status' => 0,
                'booking_fee' => $booking_fee,
                'product_price' => 0
            ];
            $insert['totalprice'] = $insert['booking_fee'] + $insert['product_price'];
            if($insert['totalprice'] == 0) {
                $insert['status'] = 1;
            }
            //支付，审核
            $orderid = Db::name('restaurant_booking_order')->insertGetId($insert);
            if($insert['totalprice']) {
                $payorderid = \app\models\Payorder::createorder(aid,$insert['bid'],$insert['mid'],'restaurant_booking',$orderid,$insert['ordernum'],'预定订单：'.$insert['ordernum'],$insert['totalprice']);
            }

            $member = $this->member;

            //
            if($info['bid']) {
                $business = Db::name('business')->where('id', $info['bid'])->field('name,logo,tel,address')->find();
            } else {
                $business = Db::name('admin_set')->where('aid', $info['aid'])->field('name,logo,tel,address')->find();
            }
            //公众号通知
            $tmplcontent = [];
            $tmplcontent['first'] = '有新的预定订单';
            $tmplcontent['remark'] = '点击进入查看~';
            $tmplcontent['keyword1'] = $business['name']; //餐厅名称
            $tmplcontent['keyword2'] = $member['nickname']; //用户名
            $tmplcontent['keyword3'] = $tableName;//桌号
            $tmplcontent['keyword4'] = $insert['booking_time'];//预定时间
            \app\commons\Wechat::sendhttmpl(aid,$insert['bid'],'tmpl_restaurant_booking',$tmplcontent,m_url('admin/restaurant/bookingorder'));
            if($insert['totalprice'] == 0) {
                $tmplcontent['first'] = '预定信息已提交，等待审核';
                $rs = \app\commons\Wechat::sendtmpl(aid,mid,'tmpl_restaurant_booking',$tmplcontent,m_url('restaurant/booking/orderlist'));
            }
            //短信通知
//            $rs = \app\commons\Sms::send(aid,$member['tel']?$member['tel']:$insert['tel'],'tmpl_restaurant_booking',['restaurant_name'=>$business['name'], 'table' => $tableName, 'time' => $insert['booking_time']]);

            return $this->json(['status'=>1,'msg' => $insert['totalprice'] > 0 ? '预定成功，请支付' : '预定成功','id'=>$orderid, 'payorderid' => $payorderid ]);
        }

        $pstimedata = json_decode($set['timedata'],true);
        $pstimeArr = [];
        foreach($pstimedata as $k2=>$v2){
            if($v2['day']==1){
                $thistxt = '今天('.date('m月d日').') '.($v2['hour']<=9?'0'.$v2['hour']:$v2['hour']).':'.($v2['minute']<=9?'0'.$v2['minute']:$v2['minute']);
                $thisval = date('Y-m-d').' '.($v2['hour']<=9?'0'.$v2['hour']:$v2['hour']).':'.($v2['minute']<=9?'0'.$v2['minute']:$v2['minute']);
                $thisval2 = date('Y-m-d').' '.($v2['hour2']<=9?'0'.$v2['hour2']:$v2['hour2']).':'.($v2['minute2']<=9?'0'.$v2['minute2']:$v2['minute2']);
                if(strtotime($thisval2) > time() + 3600*$set['prehour']){
                    if($v2['hour2'] != $v2['hour'] || $v2['minute2'] != $v2['minute']){
                        $thistxt.= ('-'.($v2['hour2']<=9?'0'.$v2['hour2']:$v2['hour2']).':'.($v2['minute2']<=9?'0'.$v2['minute2']:$v2['minute2']));
                        $thisval.= ('~'.($v2['hour2']<=9?'0'.$v2['hour2']:$v2['hour2']).':'.($v2['minute2']<=9?'0'.$v2['minute2']:$v2['minute2']));
                    }
                    $pstimeArr[] = [
                        'title'=>$thistxt,
                        'value'=>$thisval,
                        'bid'=>$set['bid'],
                    ];
                }
            }
            if($v2['day']==2){
                $thistxt = '明天('.date('m月d日',time()+86400).') '.($v2['hour']<=9?'0'.$v2['hour']:$v2['hour']).':00';
                $thisval = date('Y-m-d',time()+86400).' '.($v2['hour']<=9?'0'.$v2['hour']:$v2['hour']).':'.($v2['minute']<=9?'0'.$v2['minute']:$v2['minute']);
                $thisval2 = date('Y-m-d',time()+86400).' '.($v2['hour2']<=9?'0'.$v2['hour2']:$v2['hour2']).':'.($v2['minute2']<=9?'0'.$v2['minute2']:$v2['minute2']);
                if(strtotime($thisval2) > time() + 3600*$set['prehour']){
                    if($v2['hour2'] != $v2['hour'] || $v2['minute2'] != $v2['minute']){
                        $thistxt.= ('-'.($v2['hour2']<=9?'0'.$v2['hour2']:$v2['hour2']).':'.($v2['minute2']<=9?'0'.$v2['minute2']:$v2['minute2']));
                        $thisval.= ('~'.($v2['hour2']<=9?'0'.$v2['hour2']:$v2['hour2']).':'.($v2['minute2']<=9?'0'.$v2['minute2']:$v2['minute2']));
                    }
                    $pstimeArr[] = [
                        'title'=>$thistxt,
                        'value'=>$thisval,
                        'bid'=>$set['bid'],
                    ];
                }
            }
            if($v2['day']==3){
                $thistxt = '后天('.date('m月d日',time()+86400*2).') '.($v2['hour']<=9?'0'.$v2['hour']:$v2['hour']).':00';
                $thisval = date('Y-m-d',time()+86400*2).' '.($v2['hour']<=9?'0'.$v2['hour']:$v2['hour']).':'.($v2['minute']<=9?'0'.$v2['minute']:$v2['minute']);
                $thisval2 = date('Y-m-d',time()+86400*2).' '.($v2['hour2']<=9?'0'.$v2['hour2']:$v2['hour2']).':'.($v2['minute2']<=9?'0'.$v2['minute2']:$v2['minute2']);
                if(strtotime($thisval2) > time() + 3600*$set['prehour']){
                    if($v2['hour2'] != $v2['hour'] || $v2['minute2'] != $v2['minute']){
                        $thistxt.= ('-'.($v2['hour2']<=9?'0'.$v2['hour2']:$v2['hour2']).':'.($v2['minute2']<=9?'0'.$v2['minute2']:$v2['minute2']));
                        $thisval.= ('~'.($v2['hour2']<=9?'0'.$v2['hour2']:$v2['hour2']).':'.($v2['minute2']<=9?'0'.$v2['minute2']:$v2['minute2']));
                    }
                    $pstimeArr[] = [
                        'title'=>$thistxt,
                        'value'=>$thisval,
                        'bid'=>$set['bid'],
                    ];
                }
            }
            if($v2['day']==4){
                $thistxt = '大后天('.date('m月d日',time()+86400*3).') '.($v2['hour']<=9?'0'.$v2['hour']:$v2['hour']).':00';
                $thisval = date('Y-m-d',time()+86400*3).' '.($v2['hour']<=9?'0'.$v2['hour']:$v2['hour']).':'.($v2['minute']<=9?'0'.$v2['minute']:$v2['minute']);
                $thisval2 = date('Y-m-d',time()+86400*3).' '.($v2['hour2']<=9?'0'.$v2['hour2']:$v2['hour2']).':'.($v2['minute2']<=9?'0'.$v2['minute2']:$v2['minute2']);
                if(strtotime($thisval) > time() + 3600*$set['prehour']){
                    if($v2['hour2'] != $v2['hour'] || $v2['minute2'] != $v2['minute']){
                        $thistxt.= ('-'.($v2['hour2']<=9?'0'.$v2['hour2']:$v2['hour2']).':'.($v2['minute2']<=9?'0'.$v2['minute2']:$v2['minute2']));
                        $thisval.= ('~'.($v2['hour2']<=9?'0'.$v2['hour2']:$v2['hour2']).':'.($v2['minute2']<=9?'0'.$v2['minute2']:$v2['minute2']));
                    }
                    $pstimeArr[] = [
                        'title'=>$thistxt,
                        'value'=>$thisval,
                        'bid'=>$set['bid'],
                    ];
                }
            }
        }
        $tableid = input('param.tableId/d');
        $where[] = ['id', '=', $tableid];
        $table = Db::name('restaurant_table')->where($where)->find();
        //自主选择日期
        $restaurant_book_date = getcustom('restaurant_book_custom')?1:0;
        $textset = [];
        if(!$textset['就餐']) $textset['就餐']='就餐';
        if(!$textset['餐桌']) $textset['餐桌']='餐桌';
        if(!$textset['订餐']) $textset['订餐']='订餐';
        if(!$textset['订座']) $textset['订座']='订座';
        if(!$textset['餐桌']) $textset['餐桌']='餐桌';
        if(!$textset['座位数']) $textset['座位数']='座位数';

        return $this->json(['status'=>1,'timeArr'=>$pstimeArr, 'table' => $table,'restaurant_book_date'=>$restaurant_book_date,'textset'=>$textset]);
    }
    public function tableList()
    {
        $bid = input('param.bid/d',0);
//        $bid = $bid?$bid:bid;
        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['status','=',0];//空闲
        $where[] = ['canbook','=',1];

        $whereBooking = [];
        $whereBooking[] = ['aid','=',aid];

        $where[] = ['bid','=',$bid];
        $whereBooking[] = ['bid','=',$bid];
        if(input('param.cid')){
            $where[] = ['cid','=',input('param.cid/d')];
            $category = Db::name('restaurant_table_category')->where('id',input('param.cid/d'))->where('aid', aid)->where('bid', $bid)->column('*','id');
        }else{
            $category = Db::name('restaurant_table_category')->where('aid', aid)->where('bid', $bid)->column('*','id');
        }
        if(input('param.time')){
            $whereBooking[] = ['booking_time','=',input('param.time')];
            $whereBooking[] = ['check_status', 'in', [0,1]];
            $tableid = Db::name('restaurant_booking_order')->where($whereBooking)->column('tableid');
        }
        if($tableid) {
            $where[] = ['id','not in',$tableid];
        }
        if(input('param.field') && input('param.order')){
            $order = input('param.field').' '.input('param.order').',sort,id desc';
        }else{
            $order = 'sort desc,id desc';
        }

        $where2 = [];
        $where2[] = ['aid','=',aid];
        $where2[] = ['bid','=',$bid];
        $booking_set = Db::name('restaurant_booking_sysset')->where($where2)->find();

        $pernum = 10;
        $pagenum = input('post.pagenum');
        if(!$pagenum) $pagenum = 1;
        $datalist = Db::name('restaurant_table')->where($where)->page($pagenum,$pernum)->order($order)->select()->toArray();
        foreach($datalist as $k => $item) {
            $datalist[$k]['limit_fee'] = $category[$item['cid']]['limit_fee'] ? $category[$item['cid']]['limit_fee'] : 0;
            $datalist[$k]['service_fee'] = $category[$item['cid']]['service_fee'] ? $category[$item['cid']]['service_fee'] : 0;
            //餐桌分类未设置预定费用时使用预定设置金额
            $booking_fee = $category[$item['cid']]['booking_fee'];
            if($booking_fee <= 0){
                $booking_fee = $booking_set['pay_money'];
                }

            $datalist[$k]['booking_fee'] = $booking_fee;
        }
        if(!$datalist) $datalist = [];
        return $this->json(['status'=>1,'data'=>$datalist]);
    }

    public function tableCategory()
    {
        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['status','=',1];
        $bid = input('param.bid/d',0);
//        $bid = $bid?$bid:bid;
        $where[] = ['bid','=',$bid];

        if(input('param.field') && input('param.order')){
            $order = input('param.field').' '.input('param.order').',sort,id desc';
        }else{
            $order = 'sort desc,id desc';
        }

        $datalist = Db::name('restaurant_table_category')->where($where)->order($order)->select()->toArray();
        if(!$datalist) $datalist = [];

        $where2 = [];
        $where2[] = ['aid','=',aid];
        $where2[] = ['bid','=',$bid];
        $booking_set = Db::name('restaurant_booking_sysset')->where($where2)->find();
        //开启预定+并且收费，才显示预定费
        $set = [];
        $set['show_booking_fee'] = 0;
        if($booking_set['status'] && $booking_set['pay']){
            $set['show_booking_fee'] = 1;
        }
        $textset = [];
        if(!$textset['就餐']) $textset['就餐']='就餐';
        if(!$textset['餐桌']) $textset['餐桌']='餐桌';
        if(!$textset['订餐']) $textset['订餐']='订餐';
        if(!$textset['订座']) $textset['订座']='订座';
        if(!$textset['餐桌']) $textset['餐桌']='餐桌';
        if(!$textset['座位数']) $textset['座位数']='座位数';
        return $this->json(['status'=>1,'data'=>$datalist,'set'=>$set,'textset'=>$textset]);
    }

    public function orderlist(){
        $st = input('param.st');
        if(!input('?param.st') || $st === ''){
            $st = 'all';
        }
        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['mid','=',mid];
        if($this->user['mdid']){
            $where[] = ['mdid','=',$this->user['mdid']];
        }
        if($st == 'all'){

        }elseif($st == '0'){
            $where[] = ['status','=',0];
        }elseif($st == '1'){
            //待审核
            $where[] = ['status','=',1];
            $where[] = ['check_status','=',0];
        }elseif($st == '2'){
            $where[] = ['status','=',2];
        }elseif($st == '3'){
            //已完成(已付款 并审核通过）
            $where[] = ['status','=',1];
            $where[] = ['check_status','=',1];
        }elseif($st == '10'){
            $where[] = ['refund_status','>',0];
        }
        $pernum = 10;
        $pagenum = input('post.pagenum');
        if(!$pagenum) $pagenum = 1;
        $datalist = Db::name('restaurant_booking_order')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
        if(!$datalist) $datalist = array();
        else {
            foreach ($datalist as $key => $v) {
                if($v['bid']!=0){
                    $datalist[$key]['binfo'] = Db::name('business')->where('aid',aid)->where('id',$v['bid'])->field('id,name,logo')->find();
                }else{
                    $datalist[$key]['binfo'] = Db::name('admin_set')->where('aid',aid)->field('id,name,logo')->find();
                }
                $datalist[$key]['tableName'] = Db::name('restaurant_table')->where('aid',aid)->where('id', $v['tableid'])->value('name');
            }
        }
        $rdata = [];
        $rdata['datalist'] = $datalist;
        $rdata['st'] = $st;
        return $this->json($rdata);
    }

    public function detail()
    {
        $id = input('param.id/d');
        $where[] = ['id', '=', $id];
        $where[] = ['mid', '=', mid];
        $order = Db::name('restaurant_booking_order')->where($where)->find();
        $order['tableName'] = Db::name('restaurant_table')->where('id', $order['tableid'])->value('name');
        //
        if($order['bid']) {
            $business = Db::name('business')->where('id', $order['bid'])->field('name,logo,tel,address')->find();
        } else {
            $business = Db::name('admin_set')->where('aid', $order['aid'])->field('name,logo,tel,address')->find();
        }
        $where = [];
        $where[] = ['aid','=',$order['aid']];
        $where[] = ['bid','=',$order['bid']];
        $set = Db::name('restaurant_booking_sysset')->where($where)->find();
        $textset = [];
        $business['show_tip'] = 1;//是否显示提示语
        if(!$textset['就餐']) $textset['就餐']='就餐';
        if(!$textset['餐桌']) $textset['餐桌']='餐桌';
        if(!$textset['订餐']) $textset['订餐']='订餐';
        if(!$textset['订座']) $textset['订座']='订座';
        if(!$textset['餐桌']) $textset['餐桌']='餐桌';
        if(!$textset['座位数']) $textset['座位数']='座位数';
        if(!$textset['用餐人数']) $textset['用餐人数']='用餐人数';
        if(!$textset['预定桌台']) $textset['预定桌台']='预定桌台';

        return $this->json(['status'=>1, 'data' => $order, 'business' => $business,'textset'=>$textset]);
    }

    //
    public function del(){
        $orderid = input('post.id/d');
        //退款
        $order = Db::name('restaurant_booking_order')->where('aid',aid)->where('mid',mid)->where('id',$orderid)->find();
        if($order['totalprice'] > 0) {
            $update['refund_money'] = $order['totalprice'];
            $update['refund_reason'] = '用户取消退款';
            $rs = \app\commons\Order::refund($order,$update['refund_money'],$update['refund_reason']);
            if($rs['status']==0){
                return json(['status'=>0,'msg'=>$rs['msg']]);
            }
            $update['refund_status'] = 2;
            $update['refund_time'] = time();
        }
        $update['status'] = 4;
        Db::name('restaurant_booking_order')->where('aid',aid)->where('mid',mid)->where('id',$orderid)->update($update);
        return json(['status'=>1,'msg'=>'操作成功']);
    }
}