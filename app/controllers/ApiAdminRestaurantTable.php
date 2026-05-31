<?php


//管理员中心 - 餐桌
namespace app\controllers;
use think\facade\Db;
class ApiAdminRestaurantTable extends ApiAdmin
{	
	public function index(){
        $where[] = ['aid', '=', aid];
        $where[] = ['bid', '=', bid];

		$pernum = 20;
		$pagenum = input('param.pagenum');
		if(!$pagenum) $pagenum = 1;
        $keyword = input('param.keyword');
        if($keyword) $where[] = ['name', 'like', '%'.$keyword.'%'];
        $cid = input('param.cid');
        if($cid) $where[] = ['cid', '=', $cid];
        $status = input('param.tableStatus');
        if($status !== '' && !is_null($status)) $where[] = ['status', '=', $status];
		$datalist = Db::name('restaurant_table')->where($where)->order('sort desc,id desc')->select();
		if(!$datalist) $datalist = array();

		$rdata = [];
        $rdata['status'] = 1;
		$rdata['datalist'] = $datalist;
		return $this->json($rdata);
	}

    //编辑
    public function edit(){
        if(input('param.id')){
            $info = Db::name('restaurant_table')->where('aid',aid)->where('bid',bid)->where('id',input('param.id/d'))->find();
        }else{
            $info = ['id'=>'','status'=>0, 'canbook' => 1, 'sort' => 0];
        }

        $rdata = [];
        $rdata['info'] = $info;
        return $this->json($rdata);
    }

    //编辑
    public function save(){
        if(input('post.id')) $cate = Db::name('restaurant_table')->where('aid',aid)->where('bid',bid)->where('id',input('post.id/d'))->find();
        $info = input('post.info/a');
        $data = array();
        $data['name'] = $info['name'];
        $data['cid'] = $info['cid'];
        $data['pic'] = $info['pic'];
        $data['seat'] = $info['seat'] ? intval($info['seat']) : 0;
        $data['canbook'] = $info['canbook'];
        $data['sort'] = $info['sort'];
        $data['status'] = $info['status'];

        if($cate){
            $data['update_time'] = time();
            Db::name('restaurant_table')->where('aid',aid)->where('bid',bid)->where('id',$cate['id'])->update($data);
            $id = $cate['id'];
            \app\commons\System::plog('餐饮餐桌编辑'.$id);
        }else{
            $data['aid'] = aid;
            $data['bid'] = bid;
            $data['create_time'] = time();
            $id = Db::name('restaurant_table')->insertGetId($data);
            \app\commons\System::plog('餐饮餐桌添加'.$id);
        }

        return json(['status'=>1,'msg'=>'操作成功']);
    }

    //删除
    public function del(){
        $id = input('post.id/d');
        Db::name('restaurant_table')->where(['aid'=>aid,'bid'=>bid,'id'=>$id])->delete();
        return $this->json(['status'=>1,'msg'=>'操作成功']);
    }

    public function detail() {
        if(!input('param.id')){
            return $this->json(['status'=>0,'msg'=>'参数错误']);
        }
        $order_goods_sum = 0;
        $info = Db::name('restaurant_table')->where('aid',aid)->where('bid',bid)->where('id',input('param.id/d'))->find();

        //关联订单，已点菜品
        if($info['orderid']) {
            $order = Db::name('restaurant_shop_order')->where('aid',aid)->where('bid',bid)->where('tableid',$info['id'])->where('id', $info['orderid'])->find();
            if($order['mid']){
                $member = Db::name('member')->where('aid',$order['aid'])->where('id',$order['mid'])->field('nickname,tel')->find();
                $order['linkman'] = $order['linkman']??$member['nickname'];
                $order['tel'] = $order['tel']??$member['tel'];
            }
            $order_goods =  Db::name('restaurant_shop_order_goods')->where('aid',aid)->where('bid',bid)->where('orderid', $info['orderid'])->select()->toArray();
            $order_goods_sum =0 ;
            foreach($order_goods as $key=>$val){
                $order_goods_sum+= $val['num'];
                if(getcustom('restaurant_product_jialiao')) {
                    $order_goods[$key]['sell_price'] = dd_money_format($val['sell_price'] + $val['njlprice']);
                    if($val['njltitle']) {
                        $order_goods[$key]['ggname'] = $val['ggname'] . '(' . $val['njltitle'] . ')';
                    }
                }
                if(getcustom('restaurant_weigh')) {
                    $order_goods[$key]['num'] = floatval($val['num']);
                }
                if(getcustom('restaurant_product_package')){
                    if($val['package_data']){
                        $package_data = json_decode($val['package_data'],true);
                        $ggtext = [];
                        foreach($package_data as $pdk=>$pd){
                            $t = 'x'.$pd['num'].' '.$pd['proname'];
                            if($pd['ggname'] !='默认规格'){
                                $t .='('.$pd['ggname'].')';
                            }
                            $ggtext[] = $t;
                        }
                        $order_goods[$key]['ggtext'] =$ggtext;
                    }
                }
            }
            if(getcustom('restaurant_table_timing')){
                if($info['timing_fee_type'] >0){
                    //如果是计时桌台 更新订单信息
                    $timing_money = $this->getTimingFee($info['orderid'],$info['id']);
                    $info['timing_money'] = $timing_money;
                    $diff_timing_money = $timing_money;
                    if($order['timing_money'] > 0){
                       $diff_timing_money = $timing_money -  $order['timing_money'] ;
                    }
                    $totalprice = dd_money_format($order['totalprice'] + $diff_timing_money);
                    $order['totalprice'] = $totalprice;
                    
                    $is_start = 0;
                    if($order['timeing_start'] > 0){
                        $is_start = 1;
                    }
                    $info['is_start'] = $is_start;
                    
                    
                    $timing_log = Db::name('restaurant_timing_log')->where('aid',$info['aid'])->where('bid',$info['bid'])->where('tableid',$info['id'])->where('orderid',$info['orderid'])->select()->toArray();
                    if($order['timeing_start'] > 0){
                        $num = intval(( time() - $order['timeing_start']) / 60, 0);
                        if($num > 0){
                            $timing_log[] = [
                                'start_time' => date('H:i',$order['timeing_start']),
                                'end_time' => date('H:i',time()),
                                'num' => $num
                            ];
                        }

                    }
                    $info['timing_log'] =$timing_log?$timing_log:[];

                    $timing_fee_text= Db::name('restaurant_shop_sysset')->where('aid',aid)->where('bid',bid)->value('timing_fee_text');
                    $info['timing_fee_text'] = $timing_fee_text;
                }
            }
        }
        $rdata = [];
        $info['create_time'] = date('Y-m-d H:i', $info['create_time']);
        $rdata['info'] = $info;
        $rdata['order'] = $order ? $order : [];
        $rdata['order_goods'] = $order_goods ? $order_goods : [];
        $rdata['order_goods_sum'] = $order_goods_sum;
        return $this->json($rdata);
    }
    //换桌
    public function change()
    {
        if(!input('param.new/d') || !input('param.origin/d')){
            return $this->json(['status'=>0,'msg'=>'参数错误']);
        }
        $info = Db::name('restaurant_table')->where('aid',aid)->where('bid',bid)->where('id',input('param.origin/d'))->find();
        if(empty($info)) {
            return $this->json(['status'=>0,'msg'=>'餐桌不存在']);
        }
        $new_table = Db::name('restaurant_table')->where('aid',aid)->where('bid',bid)->where('id',input('param.new/d'))->find();
        if(empty($new_table)) {
            return $this->json(['status'=>0,'msg'=>'餐桌不存在']);
        }
        if($new_table['status'] !== 0 || $new_table['orderid']) {
            return $this->json(['status'=>0,'msg'=>'餐桌状态不可用']);
        }
        Db::name('restaurant_table')->where('aid',aid)->where('bid',bid)->where('id',input('param.origin/d'))->update(['status' => 0, 'orderid' => 0]);
        Db::name('restaurant_table')->where('aid',aid)->where('bid',bid)->where('id',input('param.new/d'))->update(['status' => 2, 'orderid' => $info['orderid']]);
        if($info['orderid']) {
            Db::name('restaurant_shop_order')->where('aid',aid)->where('bid',bid)->where('tableid',input('param.origin/d'))
                ->where('id', $info['orderid'])->update(['tableid' => $new_table['id']]);
        }

        return $this->json(['status'=>1,'msg'=>'换桌成功']);
    }
    //清台
    public function clean()
    {
        if(!input('param.tableId/d')){
            return $this->json(['status'=>0,'msg'=>'参数错误']);
        }
        $tableid = input('param.tableId/d');
        $info = Db::name('restaurant_table')->where('aid',aid)->where('bid',bid)->where('id',$tableid)->find();
        if(empty($info)) {
            return $this->json(['status'=>0,'msg'=>'餐桌不存在']);
        }
        if($info['status'] == 0) {
            return $this->json(['status'=>0,'msg'=>'当前无需清台']);
        }
        if($info['orderid']) {
            $order = Db::name('restaurant_shop_order')->where('aid',aid)->where('bid',bid)->where('tableid',$tableid)
                ->where('id', $info['orderid'])->find();
            if($order && $order['status'] != 3 && $order['totalprice'] > 0)
            return $this->json(['status'=>0,'msg'=>'请先完成订单结算']);
        }
        Db::name('restaurant_table')->where('aid',aid)->where('bid',bid)->where('id',$tableid)->update(['status' => 3, 'orderid' => 0]);

        return $this->json(['status'=>1,'msg'=>'清理完后请切换餐桌状态']);
    }
    //清台完成 设为空闲中
    public function cleanOver()
    {
        if(!input('param.tableId/d')){
            return $this->json(['status'=>0,'msg'=>'参数错误']);
        }
        $tableid = input('param.tableId/d');
        $info = Db::name('restaurant_table')->where('aid',aid)->where('bid',bid)->where('id',$tableid)->find();
        if(empty($info)) {
            return $this->json(['status'=>0,'msg'=>'餐桌不存在']);
        }
        if($info['status'] == 2) {
            return $this->json(['status'=>0,'msg'=>'就餐中，请先结算然后清台']);
        }
        Db::name('restaurant_table')->where('aid',aid)->where('bid',bid)->where('id',$tableid)->update(['status' => 0, 'orderid' => 0]);

        return $this->json(['status'=>1,'msg'=>'设置成功']);
    }
    public function closeOrder()
    {
        if(!input('param.tableId/d')){
            return $this->json(['status'=>0,'msg'=>'参数错误']);
        }
        $tableid = input('param.tableId/d');
        $info = Db::name('restaurant_table')->where('aid',aid)->where('bid',bid)->where('id',$tableid)->find();
        if(empty($info)) {
            return $this->json(['status'=>0,'msg'=>'餐桌不存在']);
        }
        if($info['orderid']) {
            $order = Db::name('restaurant_shop_order')->where('aid',aid)->where('bid',bid)->where('tableid',$tableid)
                ->where('id', $info['orderid'])->find();
            $orderid = $info['orderid'];
            //加库存
            $oglist = Db::name('restaurant_shop_order_goods')->where('aid',aid)->where('bid',bid)->where('orderid',$orderid)->select()->toArray();
            foreach($oglist as $og){
                Db::name('restaurant_product_guige')->where('aid',aid)->where('id',$og['ggid'])->update(['stock'=>Db::raw("stock+".$og['num']),'sales'=>Db::raw("sales-".$og['num'])]);
                Db::name('restaurant_product')->where('aid',aid)->where('bid',bid)->where('id',$og['proid'])->update(['stock'=>Db::raw("stock+".$og['num']),'sales'=>Db::raw("sales-".$og['num'])]);
            }

            //优惠券抵扣的返还
            if($order['coupon_rid'] > 0){
                Db::name('coupon_record')->where('aid',aid)->where(['mid'=>$order['mid'],'id'=>$order['coupon_rid']])->update(['status'=>0,'usetime'=>'']);
            }
            $rs = Db::name('restaurant_shop_order')->where('id',$orderid)->where('aid',aid)->where('bid',bid)->update(['status'=>4]);
            Db::name('restaurant_shop_order_goods')->where('orderid',$orderid)->where('aid',aid)->where('bid',bid)->update(['status'=>4]);
        }
        Db::name('restaurant_table')->where('aid',aid)->where('bid',bid)->where('id',$tableid)->update(['status' => 0, 'orderid' => 0]);

        \app\commons\System::plog('餐饮关闭订单，桌号:'.$info['name']);
        return $this->json(['status'=>1,'msg'=>'操作成功']);
    }
    public function timingPause(){
        if(getcustom('restaurant_table_timing')) {
            $type = input('param.type');//1开始 0暂停
            $tableid = input('param.tableid');
            $table = Db::name('restaurant_table')->where('aid', aid)->where('id', $tableid)->find();
            if (!$table) {
                return json(['status' => 0, 'msg' => '桌台不存在']);
            }
            if (!$table['timing_fee_type']) {
                return json(['status' => 0, 'msg' => '该桌台未开启计时']);
            }
            $orderid = $table['orderid'];
            $order = Db::name('restaurant_shop_order')->where('aid', aid)->where('bid', $table['bid'])->where('id', $orderid)->where('tableid', $tableid)->find();
            if (!$order) {
                return json(['status' => 0, 'msg' => '订单不存在']);
            }
            if (!$order) {
                return json(['status' => 0, 'msg' => '订单不存在']);
            }
            $insert = [
                'aid' => aid,
                'bid' => bid,
                'tableid' => $tableid,
                'orderid' => $orderid,
            ];

            if ($type == 0) {//暂停
                if (!$order['timeing_start']) {
                    return json(['status' => 0, 'msg' => '请先开始计时']);
                }
                $strttime = strtotime(date('Y-m-d H:i', $order['timeing_start']));
                $endtime = strtotime(date('Y-m-d H:i',time()));

                $num = intval(( $endtime - $strttime) / 60, 0);
                if ($num > 0) {
                    $insert['starttime'] =  $strttime;
                    $insert['endtime'] = $endtime;
                    $insert['start_time'] = date('H:i',$strttime);
                    $insert['end_time'] = date('H:i',$endtime);
                    $insert['num'] = $num;
                    $insert['createtime'] = time();
                    Db::name('restaurant_timing_log')->insertGetId($insert);
                    //修改订单的开始时间为空
                    Db::name('restaurant_shop_order')->where('aid', aid)->where('bid', bid)->where('id', $orderid)->update(['timeing_start' => 0]);

                    return json(['status' => 1, 'msg' => '暂停成功']);
                } else{
                    return json(['status' => 0, 'msg' => '不足一分钟，不能进行暂停']);
                }
            } else {//开始
                if ($order['timeing_start']) {
                    return json(['status' => 0, 'msg' => '计时中']);
                }
                Db::name('restaurant_shop_order')->where('aid', aid)->where('bid', bid)->where('id', $orderid)->update(['timeing_start' => time()]);
                return json(['status' => 1, 'msg' => '恢复成功']);
            }

        }
    }
    public function getTimingFee($orderid,$tableid){
        if(getcustom('restaurant_table_timing')) {
            $table = Db::name('restaurant_table')->where('aid', aid)->where('bid', bid)->where('id', $tableid)->find();
            $order = Db::name('restaurant_shop_order')->where('id', $orderid)->find();
            $fee = 0;
            $strttime = strtotime(date('Y-m-d H:i', $order['timeing_start']));
            $endtime = strtotime(date('Y-m-d H:i',time()));
            if ($table['timing_fee_type'] == 1) {//阶梯计时
                if(!$table['timing_data1'])return $fee;
                $timingdata = json_decode($table['timing_data1'], true);
                //计算出全部时段分钟数
                $totalnum = Db::name('restaurant_timing_log')->where('aid', aid)->where('bid', bid)->where('orderid', $orderid)->where('tableid', $tableid)->sum('num');
                if ($order['timeing_start']) {//未结束的 开始时间-当前时间

                    $nownum = intval(($endtime - $strttime) / 60, 0);
                    $totalnum = $totalnum + $nownum;
                }
                $price = 0;
                foreach ($timingdata as $key => $val) {
                    if ($totalnum > $val['end']) {
                        $price += $val['money'] * ceil( ($val['end'] - $val['start'])/$val['minute']);
                    }
                    if($totalnum < $val['end'] && $totalnum > $val['start']){
                        $price += ceil(($totalnum - $val['start'])/$val['minute']) * $val['money'];
                    }
                }
                $fee = dd_money_format($price);
            } elseif ($table['timing_fee_type'] == 2) {//时段计费
                if (!$table['timing_data2'])return $fee;
                $timingdata = json_decode($table['timing_data2'], true);
                //切割成1分钟
                $timelog = Db::name('restaurant_timing_log')->where('aid', aid)->where('bid', bid)->where('orderid', $orderid)->where('tableid', $tableid)->select()->toArray();
                //如果当前订单的计时处于开始中
                if ($order['timeing_start']) {
                    $timelog[] = [
                        'starttime' => $strttime,
                        'endtime' => $endtime,
                        'start_time' => date('H:i', $strttime),
                        'end_time' => date('H:i', $endtime),
                    ];
                }
                $all_log = [];
                $i = 0;
                foreach ($timelog as $key => $log) {
                    $nowtime = $log['starttime'];
                    while ($nowtime < $log['endtime'] && ($log['endtime'] - $nowtime) >= 60) {
                        $all_log[$i]['id'] = $log['id'];
                        $all_log[$i]['start_time'] = date('H:i', $nowtime);
                        $end_time = $nowtime + 60;
                        $all_log[$i]['end_time'] = date('H:i', $end_time);
                        $nowtime = $end_time;
                        $i++;
                    }
                }

                //$m_arr = [];
                foreach ($all_log as $key => $logdata) {
                    foreach ($timingdata as $tk=>$set) {
                        if ($set['start'] <= $logdata['start_time'] && $logdata['start_time'] < $set['end'] && $logdata['end_time'] > $set['start'] && $logdata['end_time'] < $set['end']) {
                            //$m_arr[] = $logdata;
                            if(!$timingdata[$tk]['num']){
                                $timingdata[$tk]['num'] =  1;
                            }else{
                                $timingdata[$tk]['num'] =  $timingdata[$tk]['num'] +1;
                            }
                            continue;
                        }
                    }
                }
                $money = 0;
                foreach($timingdata as $timek=>$timev){
                    if($timev['num']){
                        echo  ceil($timev['num']/$timev['minute']) *$timev['money'].'---';
                        $money +=ceil($timev['num']/$timev['minute']) *$timev['money'];

                    }
                }
                $fee =  dd_money_format($money);
            }
            return $fee;
        }
    }
    //结算计时费用
    public function settleTimingMoney(){
        if(getcustom('restaurant_table_timing')) {
            $tableid = input('param.tableid');
            $info = Db::name('restaurant_table')->where('aid', aid)->where('bid', bid)->where('id', $tableid)->find();
            if ($info) {
                $orderid = $info['orderid'];
                $order = Db::name('restaurant_shop_order')->where('id', $orderid)->where('aid', $info['aid'])->where('bid', $info['bid'])->find();

                $timing_money = $this->getTimingFee($orderid, $tableid);
                $diff_timing_money = $timing_money;
                if ($order['timing_money'] > 0) {
                    $diff_timing_money = $timing_money - $order['timing_money'];
                }
                $totalprice = dd_money_format($order['totalprice'] + $diff_timing_money);
                $update = [
                    'totalprice' => $totalprice,
                    'timing_money' => $timing_money,
                    'timing_can_pay' => 1
                ];
                if ($order['status'] == 0) {
                    //更新订单
                    Db::name('restaurant_shop_order')->where('id', $orderid)->update($update);
                    //更新payorder
                    $payorder = Db::name('payorder')->where('aid', $order['aid'])->where('bid', $order['bid'])->where('orderid', $order['id'])->where('type', 'restaurant_shop')->find();
                    if ($payorder) {
                        Db::name('payorder')->where('id', $payorder['id'])->update(['money' => $totalprice]);
                    }
                }
            }
        }
    }
}