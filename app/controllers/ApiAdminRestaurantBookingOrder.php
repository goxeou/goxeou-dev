<?php


//管理员中心 - 订单管理
namespace app\controllers;
use think\facade\Db;
class ApiAdminRestaurantBookingOrder extends ApiAdmin
{
    //预定订单
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
        if(input('param.keyword')) $where[] = ['linkman|tel', 'like', '%'.input('param.keyword').'%'];
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
        foreach($datalist as $key=>$v){
            $datalist[$key]['prolist'] = Db::name('restaurant_booking_order_goods')->where('orderid',$v['id'])->select()->toArray();
            if(!$datalist[$key]['prolist']) $datalist[$key]['prolist'] = [];
            $datalist[$key]['procount'] = Db::name('restaurant_booking_order_goods')->where('orderid',$v['id'])->sum('num');
            $datalist[$key]['member'] = Db::name('member')->field('id,headimg,nickname')->where('id',$v['mid'])->find();
            if(!$datalist[$key]['member']) $datalist[$key]['member'] = [];
            $datalist[$key]['tableName'] = Db::name('restaurant_table')->where('id',$v['tableid'])->value('name');
        }
        $rdata = [];
        $rdata['datalist'] = $datalist;
        $rdata['codtxt'] = Db::name('shop_sysset')->where('aid',aid)->value('codtxt');
        $rdata['st'] = $st;
        return $this->json($rdata);
    }
    //商城订单详情
    public function detail(){
        $detail = Db::name('restaurant_booking_order')->where('id',input('param.id/d'))->where('aid',aid)->where('bid',bid)->find();
        if(!$detail) $this->json(['status'=>0,'msg'=>'订单不存在']);
        $detail['createtime'] = $detail['createtime'] ? date('Y-m-d H:i:s',$detail['createtime']) : '';
        $detail['collect_time'] = $detail['collect_time'] ? date('Y-m-d H:i:s',$detail['collect_time']) : '';
        $detail['paytime'] = $detail['paytime'] ? date('Y-m-d H:i:s',$detail['paytime']) : '';
        $detail['refund_time'] = $detail['refund_time'] ? date('Y-m-d H:i:s',$detail['refund_time']) : '';
        $detail['send_time'] = $detail['send_time'] ? date('Y-m-d H:i:s',$detail['send_time']) : '';

        $member = Db::name('member')->where('id',$detail['mid'])->field('id,nickname,headimg')->find();
        $detail['nickname'] = $member['nickname'];
        $detail['headimg'] = $member['headimg'];

        $storeinfo = [];
        if($detail['freight_type'] == 1){
            $storeinfo = Db::name('mendian')->where('id',$detail['mdid'])->field('name,address,longitude,latitude')->find();
        }

        $prolist = Db::name('restaurant_booking_order_goods')->where('orderid',$detail['id'])->select()->toArray();

        $shopset = Db::name('shop_sysset')->where('aid',aid)->field('comment,autoclose')->find();

        if($detail['status']==0 && $shopset['autoclose'] > 0){
            $lefttime = strtotime($detail['createtime']) + $shopset['autoclose']*60 - time();
            if($lefttime < 0) $lefttime = 0;
        }else{
            $lefttime = 0;
        }

        if($detail['field1']){
            $detail['field1data'] = explode('^_^',$detail['field1']);
        }
        if($detail['field2']){
            $detail['field2data'] = explode('^_^',$detail['field2']);
        }
        if($detail['field3']){
            $detail['field3data'] = explode('^_^',$detail['field3']);
        }
        if($detail['field4']){
            $detail['field4data'] = explode('^_^',$detail['field4']);
        }
        if($detail['field5']){
            $detail['field5data'] = explode('^_^',$detail['field5']);
        }
        $peisong_set = Db::name('peisong_set')->where('aid',aid)->find();
        if($peisong_set['status']==1 && bid>0 && $peisong_set['businessst']==0 && $peisong_set['make_status']==0) $peisong_set['status'] = 0;
        $detail['canpeisong'] = ($detail['freight_type']==2 && $peisong_set['status']==1) ? true : false;

        $tableName = Db::name('restaurant_table')->where('id',$detail['tableid'])->value('name');

        $rdata = [];
        $rdata['detail'] = $detail;
        $rdata['prolist'] = $prolist;
        $rdata['shopset'] = $shopset;
        $rdata['storeinfo'] = $storeinfo;
        $rdata['lefttime'] = $lefttime;
        $rdata['tableName'] = $tableName;
        $rdata['expressdata'] = array_keys(express_data(['aid'=>aid,'bid'=>bid]));
        $rdata['codtxt'] = Db::name('shop_sysset')->where('aid',aid)->value('codtxt');

        return $this->json($rdata);
    }

    //审核
    public function check(){
        $orderid = input('post.orderid/d');
        $type = input('post.type');

        $order = Db::name('restaurant_booking_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->find();
        $member = Db::name('member')->where('id', $order['mid'])->find();
        $tableName = Db::name('restaurant_table')->where('id', $order['tableid'])->value('name');
        //
        if($order['bid']) {
            $business = Db::name('business')->where('id', $order['bid'])->field('name,logo,tel,address')->find();
        } else {
            $business = Db::name('admin_set')->where('aid', $order['aid'])->field('name,logo,tel,address')->find();
        }

        if($type == 'access') {
            $check_status = 1;
        } elseif($type == 'refuse') {
            $check_status = -1;
            //退款
            $order = Db::name('restaurant_booking_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->find();
            if($order['totalprice'] > 0) {
                $update['refund_money'] = $order['totalprice'];
                $update['refund_reason'] = '后台驳回退款';
                $rs = \app\commons\Order::refund($order,$update['refund_money'],$update['refund_reason']);
                if($rs['status']==0){
                    return json(['status'=>0,'msg'=>$rs['msg']]);
                }
                $update['refund_status'] = 2;
                $update['refund_time'] = time();
                Db::name('restaurant_booking_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->update($update);
            }
        }
        Db::name('restaurant_booking_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->update(['check_status'=>$check_status]);
        \app\commons\System::plog('餐饮预定订单设置备注'.$orderid);
        if($check_status == 1) {
            //公众号通知
            $tmplcontent = [];
            $tmplcontent['first'] = '预定信息审核通过';
            $tmplcontent['remark'] = '点击进入查看~';
            $tmplcontent['keyword1'] = $business['name']; //餐厅名称
            $tmplcontent['keyword2'] = $member['nickname']; //用户名
            $tmplcontent['keyword3'] = $tableName;//桌号
            $tmplcontent['keyword4'] = $order['booking_time'];//预定时间
            $rs = \app\commons\Wechat::sendtmpl(aid,$order['mid'],'tmpl_restaurant_booking',$tmplcontent,m_url('restaurant/booking/orderlist'));
            //短信通知
            $rs = \app\commons\Sms::send(aid,$member['tel']?$member['tel']:$order['tel'],'tmpl_restaurant_booking',['restaurant_name'=>$business['name'], 'table' => $tableName, 'time_range' => $order['booking_time']]);
            if($rs['status'] == 0)
                return json($rs);
        } else {
            //公众号通知
            $tmplcontent = [];
            $tmplcontent['first'] = '很抱歉，预定信息未通过审核';
            $tmplcontent['remark'] = '点击进入查看~';
            $tmplcontent['keyword1'] = $business['name']; //餐厅名称
            $tmplcontent['keyword2'] = $member['nickname']; //用户名
            $tmplcontent['keyword3'] = $tableName;//桌号
            $tmplcontent['keyword4'] = $order['booking_time'];//预定时间
            $rs = \app\commons\Wechat::sendtmpl(aid,$order['mid'],'tmpl_restaurant_booking',$tmplcontent,m_url('restaurant/booking/orderlist'));
            //短信通知
            $rs = \app\commons\Sms::send(aid,$member['tel']?$member['tel']:$order['tel'],'tmpl_restaurant_booking_fail',['restaurant_name'=>$business['name']]);
            if($rs['status'] == 0)
                return json($rs);
        }
        return json(['status'=>1,'msg'=>'设置完成']);
    }

}