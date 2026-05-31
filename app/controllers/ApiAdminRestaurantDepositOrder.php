<?php


//管理员中心 - 订单管理
namespace app\controllers;
use think\facade\Db;
class ApiAdminRestaurantDepositOrder extends ApiAdmin
{
    //寄存订单 0审核中;1寄存中;2全部取出;3未通过
    public function index(){
        $st = input('param.st');
        if(!input('?param.st') || $st === ''){
            $st = 'all';
        }
        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['bid','=',bid];
        if($this->user['mdid']){
            $where[] = ['mdid','=',$this->user['mdid']];
        }
        if(input('param.keyword')) $where[] = ['name|linkman|tel', 'like', '%'.input('param.keyword').'%'];
        if($st == 'all'){

        }elseif($st == '0'){
            $where[] = ['status','=',0];
        }elseif($st == '1'){
            //1寄存中
            $where[] = ['status','=',1];
        }elseif($st == '2'){
            $where[] = ['status','=',2];
        }elseif($st == '3'){
            //3未通过
            $where[] = ['status','=',3];
        }
        $pernum = 10;
        $pagenum = input('post.pagenum');
        if(!$pagenum) $pagenum = 1;
        $datalist = Db::name('restaurant_deposit_order')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
        if(!$datalist) $datalist = array();
        $rdata = [];
        $rdata['datalist'] = $datalist;
        $rdata['st'] = $st;
        return $this->json($rdata);
    }
    //订单详情
    public function detail(){
        $detail = Db::name('restaurant_deposit_order')->where('id',input('param.id/d'))->where('aid',aid)->where('bid',bid)->find();
        if(!$detail) $this->json(['status'=>0,'msg'=>'订单不存在']);
        $detail['createtime'] = $detail['createtime'] ? date('Y-m-d H:i:s',$detail['createtime']) : '';
        $detail['takeout_time'] = $detail['takeout_time'] ? date('Y-m-d H:i:s',$detail['takeout_time']) : '';

        $member = Db::name('member')->where('id',$detail['mid'])->field('id,nickname,headimg')->find();
        $detail['nickname'] = $member['nickname'];
        $detail['headimg'] = $member['headimg'];
        if($detail['status'] == 0) {
            $detail['statusLabel'] = '待审核';
        }elseif($detail['status'] == 1) {
            $detail['statusLabel'] = '寄存中';
        }elseif($detail['status'] == 2) {
            $detail['statusLabel'] = '已取出';
        }elseif($detail['status'] == 3) {
            $detail['statusLabel'] = '驳回';
        }

        $rdata = [];
        $rdata['detail'] = $detail;

        if($detail){
            $where = [];
            $where['aid'] = aid;
            $where['order_id'] = $detail['id'];
            $rdata['detail']['log'] = Db::name('restaurant_deposit_order_log')->where($where)->order('id desc')->select()->toArray();
        }

        return $this->json($rdata);
    }

    //审核
    public function check(){
        $orderid = input('post.orderid/d');
        $type = input('post.type');
        $order = Db::name('restaurant_deposit_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->find();
        if(empty($order)) {
            return json(['status'=>0,'msg'=>'数据不存在']);
        }
        if($type == 'access') {
            $check_status = 1;
            //审核通过
            $log = [
                'aid'=>$order['aid'],
                'bid'=>$order['bid'],
                'order_id' => $order['id'],
                'mid' => $order['mid'],
                'num' => $order['num'],
                'type'=>0,//0存入
                'createtime' => time(),
                'platform' => 'admin',
                'remark' => '审核通过',
                'waiter_id' => uid
            ];
            \db('restaurant_deposit_order_log')->insert($log);
        } elseif($type == 'refuse') {
            $check_status = 3;
        }
        $update['status'] = $check_status;
        $update['waiter_id'] = $this->user['id'];
        Db::name('restaurant_deposit_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->update($update);
        \app\commons\System::plog('餐饮寄存订单审核'.$orderid);
        return json(['status'=>1,'msg'=>'操作成功']);
    }

    //取酒
    public function takeout(){
        $orderid = input('param.orderid/d');
        $numbers = input('param.numbers/d'); //取出数量
        $where = [];
        $where['aid'] = aid;
        $where['bid'] = bid;
        $where['status'] = 1;
        if($orderid != '0'){
            $where['id'] = $orderid;
        }
        $order = Db::name('restaurant_deposit_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->find();
        if(empty($order)) {
            return json(['status'=>0,'msg'=>'数据不存在']);
        }
        if($order['num'] < 1){
            return $this->json(['status'=>0,'msg'=>'没有可取出的物品']);
        }

        if($numbers > $order['num']){
            return $this->json(['status'=>0,'msg'=>'数量不足']);
        }

        //剩余数量
        $left_number = $order['num']-$numbers;
        $log = [
            'aid'=>$order['aid'],
            'bid'=>$order['bid'],
            'order_id' => $order['id'],
            'mid' => $order['mid'],
            'num' => $numbers,
            'type'=>1,//0存入，1取出
            'createtime' => time(),
            'platform' => 'admin',
            'remark' => '后台取出',
            'waiter_id' => uid
        ];

        $time = time();
        if($left_number > 0){
            Db::name('restaurant_deposit_order')->where($where)->update(['takeout_time'=>$time,'num'=>$left_number]);
            \db('restaurant_deposit_order_log')->insert($log);
            return $this->json(['status'=>1,'msg'=>'操作成功']);
        } else {
            Db::name('restaurant_deposit_order')->where($where)->update(['status'=>2,'takeout_time'=>$time,'num'=>0]);
            $log['num'] = $order['num'];
            \db('restaurant_deposit_order_log')->insert($log);
            return $this->json(['status'=>1,'msg'=>'操作成功']);
        }

        \app\commons\System::plog('餐饮寄存订单取出'.$orderid);
        return $this->json(['status'=>1,'msg'=>'操作成功']);
    }

}