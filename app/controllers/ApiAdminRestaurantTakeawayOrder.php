<?php


//管理员中心 - 订单管理
namespace app\controllers;
use think\facade\Db;
class ApiAdminRestaurantTakeawayOrder extends ApiAdmin
{
	//商城订单
	public function index(){
		$st = input('param.st');
		if(!input('?param.st') || $st === ''){
			$st = 'all';
		}
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['bid','=',bid];

        if(input('param.keyword')){
            $keywords = input('param.keyword');
            $orderids = Db::name('restaurant_takeaway_order_goods')->where($where)->where('name','like','%'.input('param.keyword').'%')->column('orderid');
            if(!$orderids){
                $where[] = ['ordernum|title', 'like', '%'.$keywords.'%'];
            }
        }

        if($this->user['mdid']){
            $where[] = ['mdid','=',$this->user['mdid']];
        }
        if($st == 'all'){
			
		}elseif($st == '0'){
			$where[] = ['status','=',0];
		}elseif($st == '1'){
			$where[] = ['status','=',1];
		}elseif($st == '2'){
			$where[] = ['status','=',2];
		}elseif($st == '3'){
			$where[] = ['status','=',3];
		}elseif($st == '10'){
			$where[] = ['refund_status','>',0];
		}elseif($st == '12'){
			$where[] = ['status','=',12];
		}
		$pernum = 10;
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
        $datalist = Db::name('restaurant_takeaway_order')->where($where);
        if($orderids){
            $datalist->where(function ($query) use ($orderids,$keywords){
                $query->whereIn('id',$orderids)->whereOr('ordernum|title','like','%'.$keywords.'%');
            });
        }
        $datalist = $datalist->page($pagenum,$pernum)->order('id desc')->select()->toArray();
        if(!$datalist) $datalist = array();
		foreach($datalist as $key=>$v){
			$datalist[$key]['prolist'] = Db::name('restaurant_takeaway_order_goods')->where('orderid',$v['id'])->select()->toArray();
			if(!$datalist[$key]['prolist']) $datalist[$key]['prolist'] = [];
            $datalist[$key]['procount'] = Db::name('restaurant_takeaway_order_goods')->where('orderid',$v['id'])->sum('num');
			$datalist[$key]['member'] = Db::name('member')->field('id,headimg,nickname')->where('id',$v['mid'])->find();
			if(!$datalist[$key]['member']) $datalist[$key]['member'] = [];
		}
		$rdata = [];
		$rdata['datalist'] = $datalist;
		$rdata['codtxt'] = Db::name('shop_sysset')->where('aid',aid)->value('codtxt');
		$rdata['st'] = $st;
		return $this->json($rdata);
	}
	//商城订单详情
	public function detail(){
		$detail = Db::name('restaurant_takeaway_order')->where('id',input('param.id/d'))->where('aid',aid)->where('bid',bid)->find();
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

		$prolist = Db::name('restaurant_takeaway_order_goods')->where('orderid',$detail['id'])->select()->toArray();
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
        $detail['express_wx_status'] = $peisong_set['express_wx_status']==1 ? true : false;

        if(getcustom('express_maiyatian')){
            $detail['myt_status']    = $peisong_set['myt_status']==1 ? true : false;
            $detail['myt_set']       = true;
            $detail['myt_shop']      = false;
            $detail['myt_shoplist']  = [];
            if($detail['myt_shop']){
                $detail['myt_shoplist']  = Db::name('peisong_myt_shop')->where('aid',aid)->where('bid',bid)->where('is_del',0)->order('id asc')->field('id,origin_id,name')->select()->toArray();
                if(!$detail['myt_shoplist']){
                    $detail['myt_shoplist']  = [['id'=>0,'origin_id'=>0,'name'=>'无门店可选择']];
                }
            }
        }
        $is_refund  =0;
        $shopset['is_refund'] = $is_refund;
        $rdata = [];
		$rdata['detail'] = $detail;
		$rdata['prolist'] = $prolist;
		$rdata['shopset'] = $shopset;
		$rdata['storeinfo'] = $storeinfo;
		$rdata['lefttime'] = $lefttime;
		$rdata['expressdata'] = array_keys(express_data(['aid'=>aid,'bid'=>bid]));
		$rdata['codtxt'] = Db::name('shop_sysset')->where('aid',aid)->value('codtxt');

		return $this->json($rdata);
	}
    public function refundProlist(){
        }
    public function refund(){
        }
}