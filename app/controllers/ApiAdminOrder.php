<?php


//管理员中心 - 订单管理
namespace app\controllers;
use think\facade\Db;
class ApiAdminOrder extends ApiAdmin
{
	//商城订单
	public function shoporder(){
		$st = input('param.st');
		if(!input('?param.st') || $st === ''){
			$st = 'all';
		}
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['bid','=',bid];
        if(input('param.keyword')){
            $keywords = input('param.keyword');
            $orderids = Db::name('shop_order_goods')->where($where)->where('name','like','%'.input('param.keyword').'%')->column('orderid');
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
		}

		if(input('param.mid')) $where[] = ['mid','=',input('param.mid')];

		$pernum = 10;
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
        $datalist = Db::name('shop_order')->where($where);
        if($orderids){
            $datalist->where(function ($query) use ($orderids,$keywords){
                $query->whereIn('id',$orderids)->whereOr('ordernum|title','like','%'.$keywords.'%');
            });
        }
        $datalist = $datalist->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		if(!$datalist) $datalist = array();
		foreach($datalist as $key=>$v){
            $datalist[$key]['prolist'] = [];
			$prolist = Db::name('shop_order_goods')->where('orderid',$v['id'])->select()->toArray();
            if($prolist) $datalist[$key]['prolist'] = $prolist;
			$datalist[$key]['procount'] = Db::name('shop_order_goods')->where('orderid',$v['id'])->sum('num');
			$datalist[$key]['member'] = Db::name('member')->field('id,headimg,nickname')->where('id',$v['mid'])->find();
			if(!$datalist[$key]['member']) $datalist[$key]['member'] = [];
		}
        $wifiprintAuth = false;
        $rdata = [];
        $rdata['wifiprintAuth'] = $wifiprintAuth;
		$rdata['datalist'] = $datalist;
		$rdata['codtxt'] = Db::name('shop_sysset')->where('aid',aid)->value('codtxt');
		$rdata['st'] = $st;
		return $this->json($rdata);
	}
	//商城订单详情
	public function shoporderdetail(){
            $detail = Db::name('shop_order')->where('id',input('param.id/d'))->where('aid',aid)->where('bid',bid)->find();
            if(!$detail) $this->json(['status'=>0,'msg'=>'订单不存在']);
            $detail['createtime'] = $detail['createtime'] ? date('Y-m-d H:i:s',$detail['createtime']) : '';
            $detail['collect_time'] = $detail['collect_time'] ? date('Y-m-d H:i:s',$detail['collect_time']) : '';
            $detail['paytime'] = $detail['paytime'] ? date('Y-m-d H:i:s',$detail['paytime']) : '';
            $detail['refund_time'] = $detail['refund_time'] ? date('Y-m-d H:i:s',$detail['refund_time']) : '';
            $detail['send_time'] = $detail['send_time'] ? date('Y-m-d H:i:s',$detail['send_time']) : '';
            $detail['formdata'] = \app\models\Freight::getformdata($detail['id'],'shop_order');

            $member = Db::name('member')->where('id',$detail['mid'])->field('id,nickname,headimg')->find();
            $detail['nickname'] = $member['nickname'];
            $detail['headimg'] = $member['headimg'];
            $canFahuo = 0;
            if($detail['status']==1){
                $canFahuo = 1;
            }
            $detail['can_fahuo'] = $canFahuo;
            $storeinfo = [];
            $storelist = [];
            $detail['hidefahuo'] = false;
            if($detail['freight_type'] == 1){
                if($detail['mdid'] == -1){
                    $freight = Db::name('freight')->where('id',$detail['freight_id'])->find();
                    if($freight && $freight['hxbids']){
                        if($detail['longitude'] && $detail['latitude']){
                            $orderBy = Db::raw("({$detail['longitude']}-longitude)*({$detail['longitude']}-longitude) + ({$detail['latitude']}-latitude)*({$detail['latitude']}-latitude) ");
                        }else{
                            $orderBy = 'sort desc,id';
                        }
                        $storelist = Db::name('business')->where('aid',$freight['aid'])->where('id','in',$freight['hxbids'])->where('status',1)->field('id,name,logo pic,longitude,latitude,address')->order($orderBy)->select()->toArray();
                        foreach($storelist as $k2=>$v2){
                            if($detail['longitude'] && $detail['latitude'] && $v2['longitude'] && $v2['latitude']){
                                $v2['juli'] = '距离'.getdistance($detail['longitude'],$detail['latitude'],$v2['longitude'],$v2['latitude'],2).'千米';
                            }else{
                                $v2['juli'] = '';
                            }
                            $storelist[$k2] = $v2;
                        }
                    }
                }else{
                    $storeinfo = Db::name('mendian')->where('id',$detail['mdid'])->field('id,name,address,longitude,latitude')->find();
                }
                }

            $prolist = Db::name('shop_order_goods')->where('orderid',$detail['id'])->select()->toArray();

//            if(false){}else{
//                $shopset = Db::name('shop_sysset')->where('aid',aid)->field('comment,autoclose,canrefund')->find();
//            }

            $shopsetfield = 'comment,autoclose,canrefund';

            $shopset = Db::name('shop_sysset')->where('aid',aid)->field($shopsetfield)->find();

            if($detail['status']==0 && $shopset['autoclose'] > 0){
                $lefttime = strtotime($detail['createtime']) + $shopset['autoclose']*60 - time();
                if($lefttime < 0) $lefttime = 0;
            }else{
                $lefttime = 0;
            }

            //弃用
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

            if($detail['freight_type'] == 2){
                $peisong = Db::name('peisong_order')->where('orderid',$detail['id'])->where('type','shop_order')->field('id,psid')->find();
                if($peisong){
                    $detail['psid'] = $peisong['psid'];
                }
            }

            if($detail['checkmemid']){
                $detail['checkmember'] = Db::name('member')->field('id,nickname,headimg,realname,tel')->where('id',$detail['checkmemid'])->find();
            }else{
                $detail['checkmember'] = [];
            }
            $detail['message'] = \app\models\ShopOrder::checkOrderMessage($detail['id'],$detail);
            $rdata = [];
            $rdata['detail'] = $detail;
            $rdata['prolist'] = $prolist;
            $rdata['shopset'] = $shopset;
            $rdata['storeinfo'] = $storeinfo;
            $rdata['lefttime'] = $lefttime;
            $rdata['expressdata'] = array_keys(express_data(['aid'=>aid,'bid'=>bid]));
            $rdata['codtxt'] = Db::name('shop_sysset')->where('aid',aid)->value('codtxt');
            $rdata['storelist'] = $storelist;

            return $this->json($rdata);
	}
    //退款单列表
    public function shopRefundOrder(){
        $st = input('param.st');
        if(!input('param.st') || $st === ''){
            $st = 'all';
        }
        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['bid','=',bid];
        $order = ['id' => 'desc'];
        if($this->user['mdid']){
            $where[] = ['mdid','=',$this->user['mdid']];
        }
        if(input('param.keyword')) $where[] = ['refund_ordernum|ordernum|title', 'like', '%'.input('param.keyword').'%'];
        if($st == 'all'){

        }elseif($st == '0'){
            $where[] = ['refund_status','=',0];
        }elseif($st == '1'){
            $where[] = ['refund_status','=',1];
            $order['id'] = 'asc';
        }elseif($st == '2'){
            $where[] = ['refund_status','=',2];
        }elseif($st == '3'){
            $where[] = ['refund_status','=',3];
        }

        $pernum = 10;
        $pagenum = input('post.pagenum');
        if(!$pagenum) $pagenum = 1;

        if(input('param.orderid/d')) {
            $where[] = ['orderid','=',input('param.orderid/d')];
            $pernum = 99;
        }

        $datalist = Db::name('shop_refund_order')->where($where)->page($pagenum,$pernum)->order($order)->select()->toArray();
        if(!$datalist) $datalist = array();
        foreach($datalist as $key=>$v){
            $datalist[$key]['prolist'] = Db::name('shop_refund_order_goods')->where('refund_orderid',$v['id'])->select()->toArray();
            if(!$datalist[$key]['prolist']) $datalist[$key]['prolist'] = [];
            $datalist[$key]['procount'] = Db::name('shop_refund_order_goods')->where('refund_orderid',$v['id'])->sum('refund_num');
            $datalist[$key]['member'] = Db::name('member')->field('id,headimg,nickname')->where('id',$v['mid'])->find();
            if(!$datalist[$key]['member']) $datalist[$key]['member'] = [];
            if($v['refund_type'] == 'refund') {
                $datalist[$key]['refund_type_label'] = '退款';
            }elseif($v['refund_type'] == 'return') {
                $datalist[$key]['refund_type_label'] = '退货退款';
            }
        }
        $rdata = [];
        $rdata['datalist'] = $datalist;
        $rdata['st'] = $st;
        return $this->json($rdata);
    }
    public function shopRefundOrderDetail()
    {
        $where = [];
        $where[] = ['id','=',input('param.id/d')];
        $where[] = ['aid','=',aid];
        $where[] = ['bid','=',bid];
        if($this->user['mdid']){
            $where[] = ['mdid','=',$this->user['mdid']];
        }
        $detail = Db::name('shop_refund_order')->where($where)->find();
        if(!$detail) $this->json(['status'=>0,'msg'=>'退款单不存在']);
        $detail['createtime'] = $detail['createtime'] ? date('Y-m-d H:i:s',$detail['createtime']) : '';
        $detail['refund_time'] = $detail['refund_time'] ? date('Y-m-d H:i:s',$detail['refund_time']) : '';
        if($detail['refund_type'] == 'refund') {
            $detail['refund_type_label'] = '退款';
        }elseif($detail['refund_type'] == 'return') {
            $detail['refund_type_label'] = '退货退款';
        }
        if($detail['refund_pics']) {
            $detail['refund_pics'] = explode(',', $detail['refund_pics']);
        }
        unset($where['id']);
        $where[] = ['orderid', '=', $detail['orderid']];
        $detail['refundMoneyTotal'] =  Db::name('shop_refund_order')->where($where)->where('refund_status',2)->sum('refund_money');

        $member = Db::name('member')->where('id',$detail['mid'])->field('id,nickname,headimg')->find();
        $detail['nickname'] = $member['nickname'];
        $detail['headimg'] = $member['headimg'];

        $order = Db::name('shop_order')->where('id',$detail['orderid'])->where('aid',aid)->where('bid',bid)->find();
        $order['createtime'] = $order['createtime'] ? date('Y-m-d H:i:s',$order['createtime']) : '';
        $order['collect_time'] = $order['collect_time'] ? date('Y-m-d H:i:s',$order['collect_time']) : '';
        $order['paytime'] = $order['paytime'] ? date('Y-m-d H:i:s',$order['paytime']) : '';
        $order['refund_time'] = $order['refund_time'] ? date('Y-m-d H:i:s',$order['refund_time']) : '';
        $order['send_time'] = $order['send_time'] ? date('Y-m-d H:i:s',$order['send_time']) : '';
        $order['formdata'] = \app\models\Freight::getformdata($order['id'],'shop_order');

        $prolist = Db::name('shop_refund_order_goods')->where('refund_orderid',$detail['id'])->select()->toArray();
        $detail['cancheck'] = true;
        $rdata = [];
        $rdata['detail'] = $detail;
        $rdata['order'] = $order;
        $rdata['prolist'] = $prolist;
        return $this->json($rdata);
    }
	//拼团订单
	public function collageorder(){
		$st = input('param.st');
		if(!input('?param.st') || $st === ''){
			$st = 'all';
		}
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['bid','=',bid];
	
		if($this->user['mdid']){
            $where[] = ['mdid', '=', $this->user['mdid']];
		}
        if(input('param.keyword')) $where[] = ['ordernum|title', 'like', '%'.input('param.keyword').'%'];
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
		}
		$pernum = 10;
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$datalist = Db::name('collage_order')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
	
		if(!$datalist) $datalist = array();
		foreach($datalist as $key=>$v){
			$datalist[$key]['member'] = Db::name('member')->field('id,headimg,nickname')->where('id',$v['mid'])->find();
			if(!$datalist[$key]['member']) $datalist[$key]['member'] = [];
			if($v['buytpe']!=1) $datalist[$key]['team'] = Db::name('collage_order_team')->where('id',$v['teamid'])->find();
		}
		$rdata = [];
		$rdata['datalist'] = $datalist;
		$rdata['codtxt'] = Db::name('shop_sysset')->where('aid',aid)->value('codtxt');
		$rdata['st'] = $st;
		return $this->json($rdata);
	}
	//拼团订单详情
	public function collageorderdetail(){
		$detail = Db::name('collage_order')->where('id',input('param.id/d'))->where('aid',aid)->where('bid',bid)->find();
		if(!$detail) $this->json(['status'=>0,'msg'=>'订单不存在']);
		$detail['createtime'] = $detail['createtime'] ? date('Y-m-d H:i:s',$detail['createtime']) : '';
		$detail['collect_time'] = $detail['collect_time'] ? date('Y-m-d H:i:s',$detail['collect_time']) : '';
		$detail['paytime'] = $detail['paytime'] ? date('Y-m-d H:i:s',$detail['paytime']) : '';
		$detail['refund_time'] = $detail['refund_time'] ? date('Y-m-d H:i:s',$detail['refund_time']) : '';
		$detail['send_time'] = $detail['send_time'] ? date('Y-m-d H:i:s',$detail['send_time']) : '';
		$detail['formdata'] = \app\models\Freight::getformdata($detail['id'],'collage_order');

		$member = Db::name('member')->where('id',$detail['mid'])->field('id,nickname,headimg')->find();
		$detail['nickname'] = $member['nickname'];
		$detail['headimg'] = $member['headimg'];

		$storeinfo = [];
		if($detail['freight_type'] == 1){
			$storeinfo = Db::name('mendian')->where('id',$detail['mdid'])->field('name,address,longitude,latitude')->find();
		}
		
		if($detail['buytype'] != 1){
			$team = Db::name('collage_order_team')->where('id',$detail['teamid'])->find();
		}else{
			$team = [];
		}
		$peisong_set = Db::name('peisong_set')->where('aid',aid)->find();
		if($peisong_set['status']==1 && bid>0 && $peisong_set['businessst']==0 && $peisong_set['make_status']==0) $peisong_set['status'] = 0;
		$detail['canpeisong'] = ($detail['freight_type']==2 && $peisong_set['status']==1) ? true : false;

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

		$rdata = [];
		$rdata['detail'] = $detail;
		$rdata['team'] = $team;
		$rdata['storeinfo'] = $storeinfo;
		$rdata['expressdata'] = array_keys(express_data(['aid'=>aid,'bid'=>bid]));

		return $this->json($rdata);
	}
	
	
	 //周期购订单列表
    public function cycleorder(){
        $this->checklogin();
        $st = input('param.st');
        if(!input('?param.st') || $st === ''){
            $st = 'all';
        }
        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['bid','=',bid];
        $where[] = ['delete','=',0];
        
        if($this->user['mdid']){
            $where[] = ['mdid', '=', $this->user['mdid']];
        }
    
        if(input('param.keyword')) $where[] = ['ordernum|title', 'like', '%'.input('param.keyword').'%'];
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
        }
        $pernum = 10;
        $pagenum = input('post.pagenum');
        if(!$pagenum) $pagenum = 1;
        $datalist = Db::name('cycle_order')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
            
        if(!$datalist) $datalist = array();
        foreach($datalist as $key=>$v){
            //发票
            $datalist[$key]['invoice'] = 0;
            if($v['bid']) {
                $datalist[$key]['invoice'] = Db::name('business')->where('aid',aid)->where('id',$v['bid'])->value('invoice');
            } else {
                $datalist[$key]['invoice'] = Db::name('admin_set')->where('aid',aid)->value('invoice');
            }
        }
        $rdata = [];
        $rdata['st'] = $st;
        $rdata['datalist'] = $datalist;
        return $this->json($rdata);
    }
    /**
     * 获取周期列表
     */
    public function getCycleList(){
        $orderid = input('param.id/d');
        $this->checklogin();
        $detail = Db::name('cycle_order')->where('id',input('param.id/d'))->where('aid',aid)->where('bid',bid)->find();
        if(!$detail) return $this->json(['status'=>0,'msg'=>'订单不存在']);
        $list = Db::name('cycle_order_stage')
            ->where('orderid',$orderid)
            ->field('id,cycle_date,cycle_number,status')
            ->order('cycle_number asc')
            ->select()->toArray();
        foreach ($list as $k=>&$v){
            $v['title'] = '第'.$v['cycle_number'].'期';
        }
        return $this->json(['status'=>1,'data'=>$list,'detail' => $detail]);
    }
    public function cycleorderdetail(){
        $this->checklogin();

        $detail = Db::name('cycle_order')->where('id',input('param.id/d'))->where('aid',aid)->where('bid',bid)->find();
        if(!$detail) return $this->json(['status'=>0,'msg'=>'订单不存在']);
        $member = Db::name('member')->where('id',$detail['mid'])->field('id,nickname,headimg')->find();
        $detail['nickname'] = $member['nickname'];
        $detail['headimg'] = $member['headimg'];
        
        $detail['createtime'] = $detail['createtime'] ? date('Y-m-d H:i:s',$detail['createtime']) : '';
        $detail['paytime'] = $detail['paytime'] ? date('Y-m-d H:i:s',$detail['paytime']) : '';
        $detail['refund_time'] = $detail['refund_time'] ? date('Y-m-d H:i:s',$detail['refund_time']) : '';
        $detail['send_time'] = $detail['send_time'] ? date('Y-m-d H:i:s',$detail['send_time']) : '';
        $detail['formdata'] = \app\models\Freight::getformdata($detail['id'],'cycle_order');
        //配送频率

        $ps_cycle = ['1' => '每日一期','2' => '每周一期' ,'3' => '每月一期'];
        $every_day = ['1' => '每天配送','2' => '工作日配送' ,'3' => '周末配送','4' => '隔天配送'];

        $detail['pspl'] = $ps_cycle[$detail['ps_cycle']];
        if($detail['ps_cycle'] == 1){
            $detail['every_day'] =$every_day[$detail['fwtc']];
        }else{
            $detail['every_day'] = '';

        }

        $storeinfo = [];
        if($detail['freight_type'] == 1){
            $storeinfo = Db::name('mendian')->where('id',$detail['mdid'])->field('id,name,address,longitude,latitude')->find();
        }

        $shopset = Db::name('cycle_sysset')->where('aid',aid)->find();
        if($detail['status']==0 && $shopset['autoclose'] > 0 && $detail['paytypeid'] != 5){
            $lefttime = strtotime($detail['createtime']) + $shopset['autoclose']*60 - time();
            if($lefttime < 0) $lefttime = 0;
        }else{
            $lefttime = 0;
        }

        $rdata = [];
        //发票
        $rdata['invoice'] = 0;
        if($detail['bid']) {
            $rdata['invoice'] = Db::name('business')->where('aid',aid)->where('id',$detail['bid'])->value('invoice');
        } else {
            $rdata['invoice'] = Db::name('admin_set')->where('aid',aid)->value('invoice');
        }
        $rdata['detail'] = $detail;
        $rdata['shopset'] = $shopset;
        $rdata['storeinfo'] = $storeinfo;
        $rdata['lefttime'] = $lefttime;
        return $this->json($rdata);
    }
    //核销
    public function cycleorderHexiao(){
        $post = input('post.');
        $orderid = intval($post['id']);
        $order = Db::name('cycle_order_stage')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->find();
         
        if(!$order || !in_array($order['status'], [1,2]) ){
            return $this->json(['status'=>0,'msg'=>'订单状态不符合完成要求1']);
        }
        $cycle_order = Db::name('cycle_order')->where('aid',aid)->where('bid',bid)->where('id',$order['orderid'])->find();
        if(!$cycle_order ||  $cycle_order['freight_type'] !=1){
            return $this->json(['status'=>0,'msg'=>'订单状态不符合完成要求2']);
        }
        Db::name('cycle_order_stage')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->update(['status'=>3,'collect_time'=>time()]);

        $order_stage_count = Db::name('cycle_order_stage')
            ->where('status','in','0,1,2')
            ->where('orderid',$order['orderid'])
            ->count();
        if($order_stage_count == 0){
            Db::name('cycle_order')->where('aid',aid)->where('bid',bid)->where('id',$order['orderid'])->update(['status'=>3,'collect_time'=>time()]);

            $rs = \app\commons\Order::collect($cycle_order, 'cycle');
            if($rs['status']==0) return $rs;
        }else{
            Db::name('cycle_order')->where('aid',aid)->where('bid',bid)->where('id',$order['orderid'])->update(['status'=>2]);
        }

        //发货信息录入 微信小程序+微信支付
        if($cycle_order['platform'] == 'wx' && $cycle_order['paytypeid'] == 2){
            \app\commons\Order::wxShipping(aid,$cycle_order,'cycle');
        }

        \app\commons\System::plog('周期购周期订单确认核销'.$orderid);
        return $this->json(['status'=>1,'msg'=>'订单完成']);
    }
    //确认收货
    public function cycleorderStageCollect(){
        $post = input('post.');
        $orderid = intval($post['orderid']);
        $order = Db::name('cycle_order_stage')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->find();

        if(!$order || !in_array($order['status'], [2])){
            return $this->json(['status'=>0,'msg'=>'订单状态不符合完成要求']);
        }
        Db::name('cycle_order_stage')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->update(['status'=>3,'collect_time'=>time()]);


        $order_stage_count = Db::name('cycle_order_stage')
            ->where('status','in','0,1,2')
            ->where('orderid',$order['orderid'])
            ->count();
        if($order_stage_count == 0){
            Db::name('cycle_order')->where('aid',aid)->where('bid',bid)->where('id',$order['orderid'])->update(['status'=>3,'collect_time'=>time()]);

            $cycle_order = Db::name('cycle_order')->where('aid',aid)->where('bid',bid)->where('id',$order['orderid'])->find();
            $rs = \app\commons\Order::collect($cycle_order, 'cycle');
            if($rs['status']==0) return $rs;
        }

        \app\commons\System::plog('周期购周期订单确认收货'.$orderid);
        return $this->json(['status'=>1,'msg'=>'订单完成']);
    }
	//拼团订单
	public function luckycollageorder(){
		$st = input('param.st');
		if(!input('?param.st') || $st === ''){
			$st = 'all';
		}
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['bid','=',bid];
		if($this->user['mdid']){
	          $where[] = ['mdid', '=', $this->user['mdid']];
		}
	  if(input('param.keyword')) $where[] = ['ordernum|title', 'like', '%'.input('param.keyword').'%'];
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
		}
		$pernum = 10;
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$datalist = Db::name('lucky_collage_order')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		if(!$datalist) $datalist = array();
		foreach($datalist as $key=>$v){
			$datalist[$key]['member'] = Db::name('member')->field('id,headimg,nickname')->where('id',$v['mid'])->find();
			if(!$datalist[$key]['member']) $datalist[$key]['member'] = [];
			if($v['buytpe']!=1) $datalist[$key]['team'] = Db::name('collage_order_team')->where('id',$v['teamid'])->find();
		}
		$rdata = [];
		$rdata['datalist'] = $datalist;
		$rdata['codtxt'] = Db::name('shop_sysset')->where('aid',aid)->value('codtxt');
		$rdata['st'] = $st;
		return $this->json($rdata);
	}
	//拼团订单详情
	public function luckycollageorderdetail(){
		$detail = Db::name('lucky_collage_order')->where('id',input('param.id/d'))->where('aid',aid)->where('bid',bid)->find();
		if(!$detail) $this->json(['status'=>0,'msg'=>'订单不存在']);
		$detail['createtime'] = $detail['createtime'] ? date('Y-m-d H:i:s',$detail['createtime']) : '';
		$detail['collect_time'] = $detail['collect_time'] ? date('Y-m-d H:i:s',$detail['collect_time']) : '';
		$detail['paytime'] = $detail['paytime'] ? date('Y-m-d H:i:s',$detail['paytime']) : '';
		$detail['refund_time'] = $detail['refund_time'] ? date('Y-m-d H:i:s',$detail['refund_time']) : '';
		$detail['send_time'] = $detail['send_time'] ? date('Y-m-d H:i:s',$detail['send_time']) : '';
		$detail['formdata'] = \app\models\Freight::getformdata($detail['id'],'collage_order');
	
		$member = Db::name('member')->where('id',$detail['mid'])->field('id,nickname,headimg')->find();
		$detail['nickname'] = $member['nickname'];
		$detail['headimg'] = $member['headimg'];
	
		$storeinfo = [];
		if($detail['freight_type'] == 1){
			$storeinfo = Db::name('mendian')->where('id',$detail['mdid'])->field('name,address,longitude,latitude')->find();
		}
		
		if($detail['buytype'] != 1){
			$team = Db::name('lucky_collage_order_team')->where('id',$detail['teamid'])->find();
		}else{
			$team = [];
		}
		$peisong_set = Db::name('peisong_set')->where('aid',aid)->find();
		if($peisong_set['status']==1 && bid>0 && $peisong_set['businessst']==0 && $peisong_set['make_status']==0) $peisong_set['status'] = 0;
		$detail['canpeisong'] = ($detail['freight_type']==2 && $peisong_set['status']==1) ? true : false;
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

		$rdata = [];
		$rdata['detail'] = $detail;
		$rdata['team'] = $team;
		$rdata['storeinfo'] = $storeinfo;
		$rdata['expressdata'] = array_keys(express_data(['aid'=>aid,'bid'=>bid]));
	
		return $this->json($rdata);
	}
	//砍价订单
	public function kanjiaorder(){
		$st = input('param.st');
		if(!input('?param.st') || $st === ''){
			$st = 'all';
		}
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['bid','=',bid];
		if($this->user['mdid']){
            $where[] = ['mdid', '=', $this->user['mdid']];
		}
        if(input('param.keyword')) $where[] = ['ordernum|title', 'like', '%'.input('param.keyword').'%'];
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
		}
		$pernum = 10;
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$datalist = Db::name('kanjia_order')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		if(!$datalist) $datalist = array();
		foreach($datalist as $key=>$v){
			$datalist[$key]['member'] = Db::name('member')->field('id,headimg,nickname')->where('id',$v['mid'])->find();
			if(!$datalist[$key]['member']) $datalist[$key]['member'] = [];
		}
		$rdata = [];
		$rdata['datalist'] = $datalist;
		$rdata['st'] = $st;
		return $this->json($rdata);
	}
	//砍价订单详情
	public function kanjiaorderdetail(){
		$detail = Db::name('kanjia_order')->where('id',input('param.id/d'))->where('aid',aid)->where('bid',bid)->find();
		if(!$detail) $this->json(['status'=>0,'msg'=>'订单不存在']);
		$detail['createtime'] = $detail['createtime'] ? date('Y-m-d H:i:s',$detail['createtime']) : '';
		$detail['collect_time'] = $detail['collect_time'] ? date('Y-m-d H:i:s',$detail['collect_time']) : '';
		$detail['paytime'] = $detail['paytime'] ? date('Y-m-d H:i:s',$detail['paytime']) : '';
		$detail['refund_time'] = $detail['refund_time'] ? date('Y-m-d H:i:s',$detail['refund_time']) : '';
		$detail['send_time'] = $detail['send_time'] ? date('Y-m-d H:i:s',$detail['send_time']) : '';
		$detail['formdata'] = \app\models\Freight::getformdata($detail['id'],'kanjia_order');

		$member = Db::name('member')->where('id',$detail['mid'])->field('id,nickname,headimg')->find();
		$detail['nickname'] = $member['nickname'];
		$detail['headimg'] = $member['headimg'];

		$storeinfo = [];
		if($detail['freight_type'] == 1){
			$storeinfo = Db::name('mendian')->where('id',$detail['mdid'])->field('name,address,longitude,latitude')->find();
		}
		
		$peisong_set = Db::name('peisong_set')->where('aid',aid)->find();
		if($peisong_set['status']==1 && bid>0 && $peisong_set['businessst']==0 && $peisong_set['make_status']==0) $peisong_set['status'] = 0;
		$detail['canpeisong'] = ($detail['freight_type']==2 && $peisong_set['status']==1) ? true : false;
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

		$rdata = [];
		$rdata['detail'] = $detail;
		$rdata['storeinfo'] = $storeinfo;
		$rdata['expressdata'] = array_keys(express_data(['aid'=>aid,'bid'=>bid]));

		return $this->json($rdata);
	}
	//秒杀订单
	public function seckillorder(){
		$st = input('param.st');
		if(!input('?param.st') || $st === ''){
			$st = 'all';
		}
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['bid','=',bid];
		if($this->user['mdid']){
            $where[] = ['mdid', '=', $this->user['mdid']];
		}
        if(input('param.keyword')) $where[] = ['ordernum|title', 'like', '%'.input('param.keyword').'%'];
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
		}
		$pernum = 10;
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$datalist = Db::name('seckill_order')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		if(!$datalist) $datalist = array();
		foreach($datalist as $key=>$v){
			$datalist[$key]['member'] = Db::name('member')->field('id,headimg,nickname')->where('id',$v['mid'])->find();
			if(!$datalist[$key]['member']) $datalist[$key]['member'] = [];
		}
		$rdata = [];
		$rdata['datalist'] = $datalist;
		$rdata['st'] = $st;
		return $this->json($rdata);
	}
	//秒杀订单详情
	public function seckillorderdetail(){
		$detail = Db::name('seckill_order')->where('id',input('param.id/d'))->where('aid',aid)->where('bid',bid)->find();
		if(!$detail) $this->json(['status'=>0,'msg'=>'订单不存在']);
		$detail['createtime'] = $detail['createtime'] ? date('Y-m-d H:i:s',$detail['createtime']) : '';
		$detail['collect_time'] = $detail['collect_time'] ? date('Y-m-d H:i:s',$detail['collect_time']) : '';
		$detail['paytime'] = $detail['paytime'] ? date('Y-m-d H:i:s',$detail['paytime']) : '';
		$detail['refund_time'] = $detail['refund_time'] ? date('Y-m-d H:i:s',$detail['refund_time']) : '';
		$detail['send_time'] = $detail['send_time'] ? date('Y-m-d H:i:s',$detail['send_time']) : '';
		$detail['formdata'] = \app\models\Freight::getformdata($detail['id'],'seckill_order');

		$member = Db::name('member')->where('id',$detail['mid'])->field('id,nickname,headimg')->find();
		$detail['nickname'] = $member['nickname'];
		$detail['headimg'] = $member['headimg'];

		$storeinfo = [];
		if($detail['freight_type'] == 1){
			$storeinfo = Db::name('mendian')->where('id',$detail['mdid'])->field('name,address,longitude,latitude')->find();
		}
		$peisong_set = Db::name('peisong_set')->where('aid',aid)->find();
		if($peisong_set['status']==1 && bid>0 && $peisong_set['businessst']==0 && $peisong_set['make_status']==0) $peisong_set['status'] = 0;
		$detail['canpeisong'] = ($detail['freight_type']==2 && $peisong_set['status']==1) ? true : false;
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

		$rdata = [];
		$rdata['detail'] = $detail;
		$rdata['storeinfo'] = $storeinfo;
		$rdata['expressdata'] = array_keys(express_data(['aid'=>aid,'bid'=>bid]));

		return $this->json($rdata);
	}

	//积分商城订单
	public function scoreshoporder(){
		$st = input('param.st');
		if(!input('?param.st') || $st === ''){
			$st = 'all';
		}
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['bid','=',bid];
		if($this->user['mdid']){
			$where[] = ['mdid', '=', $this->user['mdid']];
		}
        if(input('param.keyword')) $where[] = ['ordernum|title', 'like', '%'.input('param.keyword').'%'];
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
		}
		$pernum = 10;
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$datalist = Db::name('scoreshop_order')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		if(!$datalist) $datalist = array();
		foreach($datalist as $key=>$v){
			$datalist[$key]['prolist'] = Db::name('scoreshop_order_goods')->where('orderid',$v['id'])->select()->toArray();
			if(!$datalist[$key]['prolist']) $datalist[$key]['prolist'] = [];
			$datalist[$key]['procount'] = Db::name('scoreshop_order_goods')->where('orderid',$v['id'])->sum('num');
			$datalist[$key]['member'] = Db::name('member')->field('id,headimg,nickname')->where('id',$v['mid'])->find();
			if(!$datalist[$key]['member']) $datalist[$key]['member'] = [];
		}
		$rdata = [];
		$rdata['datalist'] = $datalist;
		$rdata['st'] = $st;
		return $this->json($rdata);
	}
	//积分商城订单详情
	public function scoreshoporderdetail(){
		$detail = Db::name('scoreshop_order')->where('id',input('param.id/d'))->where('aid',aid)->where('bid',bid)->find();
		if(!$detail) $this->json(['status'=>0,'msg'=>'订单不存在']);
		$detail['createtime'] = $detail['createtime'] ? date('Y-m-d H:i:s',$detail['createtime']) : '';
		$detail['collect_time'] = $detail['collect_time'] ? date('Y-m-d H:i:s',$detail['collect_time']) : '';
		$detail['paytime'] = $detail['paytime'] ? date('Y-m-d H:i:s',$detail['paytime']) : '';
		$detail['refund_time'] = $detail['refund_time'] ? date('Y-m-d H:i:s',$detail['refund_time']) : '';
		$detail['send_time'] = $detail['send_time'] ? date('Y-m-d H:i:s',$detail['send_time']) : '';
		$detail['formdata'] = \app\models\Freight::getformdata($detail['id'],'scoreshop_order');

		$member = Db::name('member')->where('id',$detail['mid'])->field('id,nickname,headimg')->find();
		$detail['nickname'] = $member['nickname'];
		$detail['headimg'] = $member['headimg'];
		
		$detail['hidefahuo'] = false;
		$storeinfo = [];
		if($detail['freight_type'] == 1){
			if($detail['mdid'] == -1){
				$freight = Db::name('freight')->where('id',$detail['freight_id'])->find();
				if($freight && $freight['hxbids']){
					if($detail['longitude'] && $detail['latitude']){
						$orderBy = Db::raw("({$detail['longitude']}-longitude)*({$detail['longitude']}-longitude) + ({$detail['latitude']}-latitude)*({$detail['latitude']}-latitude) ");
					}else{
						$orderBy = 'sort desc,id';
					}
					$storelist = Db::name('business')->where('aid',$freight['aid'])->where('id','in',$freight['hxbids'])->where('status',1)->field('id,name,logo pic,longitude,latitude,address')->order($orderBy)->select()->toArray();
					foreach($storelist as $k2=>$v2){
						if($detail['longitude'] && $detail['latitude'] && $v2['longitude'] && $v2['latitude']){
							$v2['juli'] = '距离'.getdistance($detail['longitude'],$detail['latitude'],$v2['longitude'],$v2['latitude'],2).'千米';
						}else{
							$v2['juli'] = '';
						}
						$storelist[$k2] = $v2;
					}
				}
			}else{
				$storeinfo = Db::name('mendian')->where('id',$detail['mdid'])->field('id,name,address,longitude,latitude')->find();
			}
			}
		$prolist = Db::name('scoreshop_order_goods')->where('orderid',$detail['id'])->select()->toArray();
		
		$peisong_set = Db::name('peisong_set')->where('aid',aid)->find();
		if($peisong_set['status']==1 && bid>0 && $peisong_set['businessst']==0 && $peisong_set['make_status']==0) $peisong_set['status'] = 0;
		$detail['canpeisong'] = ($detail['freight_type']==2 && $peisong_set['status']==1) ? true : false;
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

		$rdata = [];
		$rdata['detail'] = $detail;
		$rdata['prolist'] = $prolist;
		$rdata['storeinfo'] = $storeinfo;
		$rdata['expressdata'] = array_keys(express_data(['aid'=>aid,'bid'=>bid]));

		return $this->json($rdata);
	}

	//团购订单
	public function tuangouorder(){
		$st = input('param.st');
		if(!input('?param.st') || $st === ''){
			$st = 'all';
		}
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['bid','=',bid];
		if($this->user['mdid']){
            $where[] = ['mdid', '=', $this->user['mdid']];
		}
        if(input('param.keyword')) $where[] = ['ordernum|title', 'like', '%'.input('param.keyword').'%'];
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
		}
		$pernum = 10;
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$datalist = Db::name('tuangou_order')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		if(!$datalist) $datalist = array();
		foreach($datalist as $key=>$v){
			$datalist[$key]['member'] = Db::name('member')->field('id,headimg,nickname')->where('id',$v['mid'])->find();
			if(!$datalist[$key]['member']) $datalist[$key]['member'] = [];
			$datalist[$key]['real_price'] = round($v['totalprice'] - $v['tuimoney'],2);
		}
		$rdata = [];
		$rdata['datalist'] = $datalist;
		$rdata['st'] = $st;
		return $this->json($rdata);
	}
	//团购订单详情
	public function tuangouorderdetail(){
		$detail = Db::name('tuangou_order')->where('id',input('param.id/d'))->where('aid',aid)->where('bid',bid)->find();
		if(!$detail) $this->json(['status'=>0,'msg'=>'订单不存在']);
		$detail['createtime'] = $detail['createtime'] ? date('Y-m-d H:i:s',$detail['createtime']) : '';
		$detail['collect_time'] = $detail['collect_time'] ? date('Y-m-d H:i:s',$detail['collect_time']) : '';
		$detail['paytime'] = $detail['paytime'] ? date('Y-m-d H:i:s',$detail['paytime']) : '';
		$detail['refund_time'] = $detail['refund_time'] ? date('Y-m-d H:i:s',$detail['refund_time']) : '';
		$detail['send_time'] = $detail['send_time'] ? date('Y-m-d H:i:s',$detail['send_time']) : '';
		$detail['formdata'] = \app\models\Freight::getformdata($detail['id'],'tuangou_order');

		$member = Db::name('member')->where('id',$detail['mid'])->field('id,nickname,headimg')->find();
		$detail['nickname'] = $member['nickname'];
		$detail['headimg'] = $member['headimg'];

		$storeinfo = [];
		if($detail['freight_type'] == 1){
			$storeinfo = Db::name('mendian')->where('id',$detail['mdid'])->field('name,address,longitude,latitude')->find();
		}
		$peisong_set = Db::name('peisong_set')->where('aid',aid)->find();
		if($peisong_set['status']==1 && bid>0 && $peisong_set['businessst']==0 && $peisong_set['make_status']==0) $peisong_set['status'] = 0;
		$detail['canpeisong'] = ($detail['freight_type']==2 && $peisong_set['status']==1) ? true : false;
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

		$detail['real_price'] = round($detail['totalprice'] - $detail['tuimoney'],2);

		$rdata = [];
		$rdata['detail'] = $detail;
		$rdata['storeinfo'] = $storeinfo;
		$rdata['expressdata'] = array_keys(express_data(['aid'=>aid,'bid'=>bid]));
		$rdata['mdid'] = $this->user['mdid'];

		return $this->json($rdata);
	}
	//约课订单
	public function yuekeorder(){
		$st = input('param.st');
		if(!input('?param.st') || $st === ''){
			$st = 'all';
		}
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['bid','=',bid];
		if($this->user['mdid']){
            $where[] = ['mdid', '=', $this->user['mdid']];
		}
        if(input('param.keyword')) $where[] = ['ordernum|title', 'like', '%'.input('param.keyword').'%'];
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
		}
		$pernum = 10;
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$datalist = Db::name('yueke_order')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		if(!$datalist) $datalist = array();
		foreach($datalist as $key=>$v){
			$datalist[$key]['member'] = Db::name('member')->field('id,headimg,nickname')->where('id',$v['mid'])->find();
			if(!$datalist[$key]['member']) $datalist[$key]['member'] = [];
			$datalist[$key]['workerinfo'] = Db::name('yueke_worker')->where('aid',aid)->where('id',$v['workerid'])->field('id,realname,tel,headimg,dengji')->find();
		}
		$rdata = [];
		$rdata['datalist'] = $datalist;
		$rdata['st'] = $st;
		return $this->json($rdata);
	}
	//约课订单详情
	public function yuekeorderdetail(){
		$detail = Db::name('yueke_order')->where('id',input('param.id/d'))->where('aid',aid)->where('bid',bid)->find();
		if(!$detail) $this->json(['status'=>0,'msg'=>'订单不存在']);
		$detail['createtime'] = $detail['createtime'] ? date('Y-m-d H:i:s',$detail['createtime']) : '';
		$detail['collect_time'] = $detail['collect_time'] ? date('Y-m-d H:i:s',$detail['collect_time']) : '';
		$detail['paytime'] = $detail['paytime'] ? date('Y-m-d H:i:s',$detail['paytime']) : '';
		$detail['refund_time'] = $detail['refund_time'] ? date('Y-m-d H:i:s',$detail['refund_time']) : '';
		$detail['send_time'] = $detail['send_time'] ? date('Y-m-d H:i:s',$detail['send_time']) : '';
		$detail['formdata'] = \app\models\Freight::getformdata($detail['id'],'yueke_order');

		$member = Db::name('member')->where('id',$detail['mid'])->field('id,nickname,headimg')->find();
		$detail['nickname'] = $member['nickname'];
		$detail['headimg'] = $member['headimg'];

		$storeinfo = [];
		if($detail['freight_type'] == 1){
			$storeinfo = Db::name('mendian')->where('id',$detail['mdid'])->field('name,address,longitude,latitude')->find();
		}
		$peisong_set = Db::name('peisong_set')->where('aid',aid)->find();
		if($peisong_set['status']==1 && bid>0 && $peisong_set['businessst']==0 && $peisong_set['make_status']==0) $peisong_set['status'] = 0;
		$detail['canpeisong'] = ($detail['freight_type']==2 && $peisong_set['status']==1) ? true : false;
		
		$workerinfo = Db::name('yueke_worker')->where('aid',aid)->where('id',$detail['workerid'])->field('id,realname,tel,headimg,dengji')->find();

		$rdata = [];
		$rdata['detail'] = $detail;
		$rdata['workerinfo'] = $workerinfo;
		$rdata['storeinfo'] = $storeinfo;
		$rdata['expressdata'] = array_keys(express_data(['aid'=>aid,'bid'=>bid]));

		return $this->json($rdata);
	}

	//备注
	public function setremark(){
		$post = input('post.');
		$type = $post['type'];
		$orderid = $post['orderid'];
		$content = $post['content'];
		Db::name($type.'_order')->where(['aid'=>aid,'bid'=>bid,'id'=>$orderid])->update(['remark'=>$content]);
        $typeName = \app\commons\Order::getOrderTypeName($type);
        \app\commons\System::plog('手机端后台'.$typeName.'设置备注'.$orderid);
		return $this->json(['status'=>1,'msg'=>'设置完成']);
	}
	//删除订单
	function delOrder(){
		$post = input('post.');
		$type = $post['type'];
		$orderid = input('post.orderid/d');
		$order = Db::name($type.'_order')->where(['id'=>$orderid,'aid'=>aid])->find();
		if(!$order || $order['status']!=4){
			return $this->json(['status'=>0,'msg'=>'删除失败,订单状态错误']);
		}else{
			$rs = Db::name($type.'_order')->where(['id'=>$orderid,'aid'=>aid])->delete();
			if($type=='shop' || $type=='collage'){
				$rs = Db::name($type.'_order_goods')->where(['orderid'=>$orderid,'aid'=>aid])->delete();
			}
            $typeName = \app\commons\Order::getOrderTypeName($type);
            \app\commons\System::plog('手机端后台'.$typeName.'删除'.$orderid);
			return $this->json(['status'=>1,'msg'=>'删除成功']);
		}
	}
	//改为已支付
	function ispay(){
		if(bid != 0) return $this->json(['status'=>-4,'msg'=>'无操作权限']);
		$type = input('post.type');
		$orderid = input('post.orderid/d');
        
        $updata = [];
        $updata['status']  = 1;
        $updata['paytime'] = time();
        $updata['paytype'] = '后台支付';

        Db::name($type.'_order')->where(['aid'=>aid,'id'=>$orderid])->update($updata);
		$payfun = $type.'_pay';
		\app\models\Payorder::$payfun($orderid);
		//if(\app\commons\Order::hasOrderGoodsTable($type)){
		//	Db::name($type.'_order_goods')->where(['orderid'=>$orderid,'aid'=>aid])->update(['status'=>1]);
		//}
		//奖励积分
		//$order = Db::name($type.'_order')->where(['aid'=>aid,'id'=>$orderid])->find();
		//if($order['givescore'] > 0){
		//	\app\commons\Member::addscore(aid,$order['mid'],$order['givescore'],'购买产品奖励'.t('积分'));
		//}
        $typeName = \app\commons\Order::getOrderTypeName($type);
        \app\commons\System::plog('手机端后台'.$typeName.'改为已支付'.$orderid);
		return $this->json(['status'=>1,'msg'=>'操作成功']);
	}
    //改为已接单
    public function jiedan(){
        $type = input('post.type');
        $orderid = input('post.orderid/d');
        Db::name($type.'_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->update(['status'=>12]);
        Db::name($type.'_order_goods')->where('orderid',$orderid)->where('aid',aid)->where('bid',bid)->update(['status'=>12]);
        //判断是否自动派单
        if(in_array($type,['shop','restaurant_takeaway'])) {
            $order = Db::name($type.'_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->find();
            if($order['freight_type'] == 2){
                $peisong_set = \db('peisong_set')->where('aid',aid)->find();
                if($peisong_set['express_wx_status'] == 1 && $peisong_set['express_wx_paidan'] == 1){
                    Db::name($type.'_order')->where('id',$orderid)->update(['express_type'=>'express_wx']);
                    \app\customs\ExpressWx::addOrder($type.'_order',$order);
                    \app\commons\System::plog('订单接单，即时配送自动派单:'.$orderid);
                }else{
                    // 自动派单到大厅
                    }
                if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
                    \app\commons\Order::wxShipping(aid,$order,$type);
                }
            }
        }
        $typeName = \app\commons\Order::getOrderTypeName($type);
        \app\commons\System::plog('手机端后台'.$typeName.'改为已接单'.$orderid);
        return $this->json(['status'=>1,'msg'=>'操作成功']);
    }


    //退款
    public function judan(){
        $type = input('post.type');
        $orderid = input('post.orderid/d');
        $reason = input('post.reason','拒单退款');
        $order = Db::name($type.'_order')->where('id',$orderid)->where('aid',aid)->where('bid',bid)->find();
        $refund_money = $order['totalprice'];
        if($order['status']!=1 && $order['status']!=2 && $order['status']!=12){
            return $this->json(['status'=>0,'msg'=>'该订单状态不允许退款']);
        }
        if($refund_money > 0) {
            $rs = \app\commons\Order::refund($order,$refund_money,$reason);
            if($rs['status']==0){
                return $this->json(['status'=>0,'msg'=>$rs['msg']]);
            }
        }

        Db::name($type.'_order')->where('id',$orderid)->where('aid',aid)->where('bid',bid)->update(['status'=>4,'refund_status'=>2,'refund_money'=>$refund_money,'refund_reason'=>$reason]);
        Db::name($type.'_order_goods')->where('orderid',$orderid)->where('aid',aid)->where('bid',bid)->update(['status'=>4]);

        //积分抵扣的返还
        if($order['scoredkscore'] > 0){
            \app\commons\Member::addscore(aid,$order['mid'],$order['scoredkscore'],'订单退款返还');
        }
        if($order['creditdkcredit'] > 0){
			\app\commons\Member::addcredit2(aid,$order['mid'],$order['creditdkcredit'],'订单退款返还');
		}
		if($order['givescore2'] > 0){
			\app\commons\Member::addscore(aid,$order['mid'],-$order['givescore2'],'订单退款扣除');
		}
        //扣除消费赠送积分
        \app\commons\Member::decscorein(aid,$type,$order['id'],$order['ordernum'],'订单退款扣除消费赠送');

        //优惠券抵扣的返还
        if($order['coupon_rid']){
            Db::name('coupon_record')->where('aid',aid)->where('mid',$order['mid'])->where('id','in',$order['coupon_rid'])->update(['status'=>0,'usetime'=>'']);
        }
        //退款成功通知
        $tmplcontent = [];
        $tmplcontent['first'] = '您的订单已经完成退款，¥'.$refund_money.'已经退回您的付款账户，请留意查收。';
        $tmplcontent['remark'] = $reason.'，请点击查看详情~';
        $tmplcontent['orderProductPrice'] = $refund_money;
        $tmplcontent['orderProductName'] = $order['title'];
        $tmplcontent['orderName'] = $order['ordernum'];
        $tmplcontentNew = [];
        $tmplcontentNew['character_string1'] = $order['ordernum'];//订单编号
        $tmplcontentNew['thing2'] = $order['title'];//商品名称
        $tmplcontentNew['amount3'] = $refund_money;//退款金额
        \app\commons\Wechat::sendtmpl(aid,$order['mid'],'tmpl_tuisuccess',$tmplcontent,m_url('pages/my/usercenter'),$tmplcontentNew);
        //订阅消息
        $tmplcontent = [];
        $tmplcontent['amount6'] = $refund_money;
        $tmplcontent['thing3'] = $order['title'];
        $tmplcontent['character_string2'] = $order['ordernum'];
		
		$tmplcontentnew = [];
		$tmplcontentnew['amount3'] = $refund_money;
		$tmplcontentnew['thing6'] = $order['title'];
		$tmplcontentnew['character_string4'] = $order['ordernum'];
        \app\commons\Wechat::sendwxtmpl(aid,$order['mid'],'tmpl_tuisuccess',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);

        //短信通知
        $member = Db::name('member')->where('id',$order['mid'])->find();
        if($member['tel']){
            $tel = $member['tel'];
        }else{
            $tel = $order['tel'];
        }
        $rs = \app\commons\Sms::send(aid,$tel,'tmpl_tuisuccess',['ordernum'=>$order['ordernum'],'money'=>$refund_money]);

        \app\commons\System::plog('餐饮外卖订单退款'.$orderid);
        return $this->json(['status'=>1,'msg'=>'已退款成功']);
    }

	public function print()
    {
        $type = input('post.type');
        $orderid = input('post.orderid/d');

        if(in_array($type,['restaurant_takeaway','restaurant_shop'])) {
            $order = Db::name($type.'_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->find();
            $rs = \app\customs\Restaurant::print($type, $order, [], 0);//0普通打印，1一菜一单
        } else {
            $rs = \app\commons\Wifiprint::print(aid,$type,$orderid,0);
        }
        return $this->json($rs);
    }
    //改为核销
    function hexiao(){
        $type = input('post.type');
        $orderid = input('post.orderid/d');
        $order = Db::name($type.'_order')->where(['aid'=>aid,'id'=>$orderid])->find();
        $auth_data = json_decode($this->user['hexiao_auth_data'],true);
		
		if($this->user['isadmin']==0){
			if(!in_array($type,$auth_data)){
				return $this->json(['status'=>0,'msg'=>'您没有核销权限']);
			}
			if($type=='shop' || $type=='collage' || $type=='kanjia' || $type=='scoreshop'){
				if($this->user['mdid'] != 0 && $this->user['mdid']!=$order['mdid']){
					return $this->json(['status'=>0,'msg'=>'您没有该门店核销权限']);
				}
			}
		}

        if($type=='shop' || $type=='collage' || $type=='kanjia' || $type=='scoreshop' || $type=='seckill' || $type=='yueke' || $type =='tuangou'){
			if($order['status']==3) return $this->json(['status'=>0,'msg'=>'订单已核销']);
            $data = array();
            $data['aid'] = aid;
            $data['bid'] = bid;
            $data['uid'] = $this->uid;
            $data['mid'] = $order['mid'];
            $data['orderid'] = $order['id'];
            $data['ordernum'] = $order['ordernum'];
            $data['title'] = $order['title'];
            $data['type'] = $type;
            $data['createtime'] = time();
            $data['remark'] = '核销员['.$this->user['un'].']核销';
            $data['mdid']   = empty($this->user['mdid'])?0:$this->user['mdid'];
			Db::name('hexiao_order')->insert($data);
            $remark = $order['remark'] ? $order['remark'].' '.$data['remark'] : $data['remark'];

            $rs = \app\commons\Order::collect($order,$type, $this->user['mid']);
            if($rs['status']==0) return $this->json($rs);

            db($type.'_order')->where(['aid'=>aid,'id'=>$orderid])->update(['status'=>3,'collect_time'=>time(),'remark'=>$remark]);
            if($type == 'scoreshop'){
                Db::name('scoreshop_order_goods')->where(['aid'=>aid,'orderid'=>$order['id']])->update(['status'=>3,'endtime'=>time()]);
            }
            if($type == 'shop'){
                Db::name('shop_order_goods')->where(['aid'=>aid,'orderid'=>$order['id']])->update(['status'=>3,'endtime'=>time()]);
                if(false){}else{
                    \app\commons\Member::uplv(aid,$order['mid']);
                }
                }
            if(getcustom('mendian_hexiao_givemoney') && $order['mdid']){
                $mendian = Db::name('mendian')->where('aid',aid)->where('id',$order['mdid'])->find();
                if($mendian){
                    $givemoney = 0;
                    if($type == 'shop'){
                        $oglist = Db::name('shop_order_goods')->where(['aid'=>aid,'orderid'=>$order['id']])->select()->toArray();
                        if($oglist){
                            if(getcustom('product_mendian_hexiao_givemoney')){
                                foreach ($oglist as $og){
                                    $pro = Db::name('shop_product')->where('aid',aid)->where('id',$og['proid'])->find();
                                    $hexiao_set = Db::name('shop_product_mendian_hexiaoset')->where('aid',aid)->where('mdid',$order['mdid'])->where('proid',$og['proid'])->find();
                                    if($hexiao_set['hexiaogivepercent']>0 || $hexiao_set['hexiaogivemoney']>0){
                                        $givemoney += $hexiao_set['hexiaogivepercent'] * 0.01 * $og['real_totalprice'] + $hexiao_set['hexiaogivemoney'];
                                    }
                                    elseif(!is_null($pro['hexiaogivepercent']) || !is_null($pro['hexiaogivemoney'])){

                                        $givemoney += $pro['hexiaogivepercent'] * 0.01 * $og['real_totalprice'] + $pro['hexiaogivemoney'];
                                    }else{
                                        $givemoney += $mendian['hexiaogivepercent'] * 0.01 * $og['real_totalprice'] + $mendian['hexiaogivemoney'];
                                    }
                                }
                            }else{
                                foreach ($oglist as $og){
                                    $pro = Db::name('shop_product')->where('aid',aid)->where('id',$og['proid'])->find();
                                    if(!is_null($pro['hexiaogivepercent']) || !is_null($pro['hexiaogivemoney'])){
                                        $givemoney += $pro['hexiaogivepercent'] * 0.01 * $og['real_totalprice'] + $pro['hexiaogivemoney'];
                                    }else{
                                        $givemoney += $mendian['hexiaogivepercent'] * 0.01 * $og['real_totalprice'] + $mendian['hexiaogivemoney'];
                                    }
                                }
                            }
                        }
                    }elseif(($mendian['hexiaogivepercent'] || $mendian['hexiaogivemoney'])){
                        $givemoney = $mendian['hexiaogivepercent'] * 0.01 * $order['totalprice'] + $mendian['hexiaogivemoney'];
                    }
                    if($givemoney > 0){
                        \app\commons\Mendian::addmoney(aid,$mendian['id'],$givemoney,'核销订单'.$order['ordernum']);
                    }
                    if(getcustom('business_platform_auth')){
                        if($mendian['bid']>0 && $order['bid']!=$mendian['bid']){
                            $business = Db::name('business')->where('aid',aid)->where('id',$mendian['bid'])->find();
                            if($business['isplatform_auth']==1){
                                \app\commons\Business::addmoney(aid,$mendian['bid'],$givemoney,$mendian['name'].'核销平台商品 订单号：'.$order['ordernum']);
                            }
                        }
                    }
                }
            }
            //发货信息录入 微信小程序+微信支付
            if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
                \app\commons\Order::wxShipping(aid,$order,$type);
            }
        }
        $typeName = \app\commons\Order::getOrderTypeName($type);
        \app\commons\System::plog('手机端后台'.$typeName.'改为核销'.$orderid);
        return $this->json(['status'=>1,'msg'=>'操作成功']);
    }
	//发货
	function sendExpress(){
		$post = input('post.');
		$type = $post['type'];
		$orderid = $post['orderid'];
		$order = Db::name($type.'_order')->where(['aid'=>aid,'id'=>$orderid])->find();

		//如果选择了配送时间，未到配送时间内不可以进行配送
		if($order['freight_type']==10){
			$pic = input('post.pic');
			$fhname = input('post.fhname');
			$fhaddress = input('post.fhaddress');
			$shname = input('post.shname');
			$shaddress = input('post.shaddress');
			$remark = input('post.remark');
			$data = [];
			$data['aid'] = aid;
			$data['pic'] = $pic;
			$data['fhname'] = $fhname;
			$data['fhaddress'] = $fhaddress;
			$data['shname'] = $shname;
			$data['shaddress'] = $shaddress;
			$data['remark'] = $remark;
			$data['createtime'] = time();
			$id = Db::name('freight_type10_record')->insertGetId($data);
			$express_com = '货运托运';
			$express_no = $id;
		}else{
			$express_com = input('post.express_com');
			$express_no = input('post.express_no');
		}

		if($type == 'tuangou'){
			$product = Db::name('tuangou_product')->where('id',$order['proid'])->find();
			if($product['endtime'] > time()){
				return $this->json(['status'=>0,'msg'=>'团购活动未结束 暂不允许发货']);
			}
		}
        if($type == 'shop'){
            }

		Db::name($type.'_order')->where(['aid'=>aid,'id'=>$orderid])->update(['express_com'=>$express_com,'express_no'=>$express_no,'send_time'=>time(),'status'=>2]);
		if(\app\commons\Order::hasOrderGoodsTable($type)){
			Db::name($type.'_order_goods')->where(['orderid'=>$orderid,'aid'=>aid])->update(['status'=>2]);
		}

		if($type=='shop' && $order['fromwxvideo'] == 1){
			\app\commons\Wxvideo::deliverysend($orderid);
		}

        //发货信息录入 微信小程序+微信支付
        if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
            \app\commons\Order::wxShipping(aid,$order,$type,['express_com'=>$express_com,'express_no'=>$express_no]);
        }
		
		//订单发货通知
		$tmplcontent = [];
		$tmplcontent['first'] = '您的订单已发货';
		$tmplcontent['remark'] = '请点击查看详情~';
		$tmplcontent['keyword1'] = $order['title'];
		$tmplcontent['keyword2'] = $express_com;
		$tmplcontent['keyword3'] = $express_no;
		$tmplcontent['keyword4'] = $order['linkman'].' '.$order['tel'];
        $tmplcontentNew = [];
        $tmplcontentNew['thing4'] = $order['title'];//商品名称
        $tmplcontentNew['thing13'] = $express_com;//快递公司
        $tmplcontentNew['character_string14'] = $express_no;//快递单号
        $tmplcontentNew['thing16'] = $order['linkman'].' '.$order['tel'];//收货人
		\app\commons\Wechat::sendtmpl(aid,$order['mid'],'tmpl_orderfahuo',$tmplcontent,m_url('/pages/my/usercenter'),$tmplcontentNew);
		//订阅消息
		$tmplcontent = [];
		$tmplcontent['thing2'] = $order['title'];
		$tmplcontent['thing7'] = $express_com;
		$tmplcontent['character_string4'] = $express_no;
		$tmplcontent['thing11'] = $order['address'];
		$tmplcontentnew = [];
		$tmplcontentnew['thing29'] = $order['title'];
		$tmplcontentnew['thing1'] = $express_com;
		$tmplcontentnew['character_string2'] = $express_no;
		$tmplcontentnew['thing9'] = $order['address'];
		\app\commons\Wechat::sendwxtmpl(aid,$order['mid'],'tmpl_orderfahuo',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);

		//短信通知
		$member = Db::name('member')->where(['id'=>$order['mid']])->find();
		$rs = \app\commons\Sms::send(aid,$member['tel']?$member['tel']:$order['tel'],'tmpl_orderfahuo',['ordernum'=>$order['ordernum'],'express_com'=>$express_com,'express_no'=>$express_no]);

        $typeName = \app\commons\Order::getOrderTypeName($type);
        \app\commons\System::plog('手机端后台'.$typeName.'发货'.$orderid);
		return $this->json(['status'=>1,'msg'=>'操作成功']);
	}
	//退款驳回
	public function refundnopass(){
		$type = input('post.type');
		$orderid = input('post.orderid/d');
		$remark = input('post.remark');
        $release = input('post.release');

        if($release == '2106') {
            //新版本退款
            $order = Db::name('shop_refund_order')->where('id',$orderid)->where('aid',aid)->where('bid',bid)->find();
            $orderOrigin = Db::name('shop_order')->where('id',$order['orderid'])->where('aid',aid)->where('bid',bid)->find();
            $reog = Db::name('shop_refund_order_goods')->where('refund_orderid',$orderid)->select();
            Db::name('shop_refund_order')->where('id',$orderid)->where('aid',aid)->where('bid',bid)->update(['refund_status'=>3,'refund_checkremark'=>$remark]);
            foreach ($reog as $item) {
                Db::name('shop_order_goods')->where('id',$item['ogid'])->where('orderid',$orderOrigin['id'])
                    ->dec('refund_num', $item['refund_num'])->update();
            }

			//聚水潭售后订单驳回
			if($orderOrigin['fromwxvideo'] == 1){
				\app\commons\Wxvideo::aftersaleupdate($order['orderid'],$order['id']);
			}

            //退款申请驳回通知
            $tmplcontent = [];
            $tmplcontent['first'] = '您的退款申请被商家驳回，可与商家协商沟通。';
            $tmplcontent['remark'] = $remark.'，请点击查看详情~';
            $tmplcontent['orderProductPrice'] = $order['refund_money'];
            $tmplcontent['orderProductName'] = $orderOrigin['title'];
            $tmplcontent['orderName'] = $order['ordernum'];
            $tmplcontentNew = [];
            $tmplcontentNew['character_string1'] = $order['ordernum'];//订单编号
            $tmplcontentNew['thing2'] = $orderOrigin['title'];//商品名称
            $tmplcontentNew['amount3'] = $order['refund_money'];//退款金额
            \app\commons\Wechat::sendtmpl(aid,$order['mid'],'tmpl_tuierror',$tmplcontent,m_url('/pages/my/usercenter'),$tmplcontentNew);
            //订阅消息
            $tmplcontent = [];
            $tmplcontent['amount3'] = $order['refund_money'];
            $tmplcontent['thing2'] = $orderOrigin['title'];
            $tmplcontent['character_string1'] = $order['ordernum'];
			
			$tmplcontentnew = [];
			$tmplcontentnew['amount3'] = $order['refund_money'];
			$tmplcontentnew['thing8'] = $orderOrigin['title'];
			$tmplcontentnew['character_string4'] = $order['ordernum'];
            \app\commons\Wechat::sendwxtmpl(aid,$order['mid'],'tmpl_tuierror',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);
            //短信通知
            $member = Db::name('member')->where(['id'=>$order['mid']])->find();
            $rs = \app\commons\Sms::send(aid,$member['tel']?$member['tel']:$orderOrigin['tel'],'tmpl_tuierror',['ordernum'=>$order['ordernum'],'reason'=>$remark]);
        } else {
            if($type == 'shop'){
                }
            Db::name($type.'_order')->where(['id'=>$orderid,'aid'=>aid])->update(['refund_status'=>3,'refund_checkremark'=>$remark]);
            $order = Db::name($type.'_order')->where(['id'=>$orderid,'aid'=>aid])->find();
            //退款申请驳回通知
            $tmplcontent = [];
            $tmplcontent['first'] = '您的退款申请被商家驳回，可与商家协商沟通。';
            $tmplcontent['remark'] = $remark.'，请点击查看详情~';
            $tmplcontent['orderProductPrice'] = $order['refund_money'];
            $tmplcontent['orderProductName'] = $order['title'];
            $tmplcontent['orderName'] = $order['ordernum'];
            $tmplcontentNew = [];
            $tmplcontentNew['character_string1'] = $order['ordernum'];//订单编号
            $tmplcontentNew['thing2'] = $order['title'];//商品名称
            $tmplcontentNew['amount3'] = $order['refund_money'];//退款金额
            \app\commons\Wechat::sendtmpl(aid,$order['mid'],'tmpl_tuierror',$tmplcontent,m_url('/pages/my/usercenter'),$tmplcontentNew);
            //订阅消息
            $tmplcontent = [];
            $tmplcontent['amount3'] = $order['refund_money'];
            $tmplcontent['thing2'] = $order['title'];
            $tmplcontent['character_string1'] = $order['ordernum'];
			
			$tmplcontentnew = [];
			$tmplcontentnew['amount3'] = $order['refund_money'];
			$tmplcontentnew['thing8'] = $order['title'];
			$tmplcontentnew['character_string4'] = $order['ordernum'];
            \app\commons\Wechat::sendwxtmpl(aid,$order['mid'],'tmpl_tuierror',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);
            //短信通知
            $member = Db::name('member')->where(['id'=>$order['mid']])->find();
            $rs = \app\commons\Sms::send(aid,$member['tel']?$member['tel']:$order['tel'],'tmpl_tuierror',['ordernum'=>$order['ordernum'],'reason'=>$remark]);
        }
        $typeName = \app\commons\Order::getOrderTypeName($type);
        \app\commons\System::plog('手机端后台'.$typeName.'驳回退款'.$orderid);
		return $this->json(['status'=>1,'msg'=>'退款已驳回']);
	}
	//退款通过
	function refundpass(){
		$type = input('post.type');
		$orderid = input('post.orderid/d');
		$refund_desc = input('post.reason');
        $release = input('post.release');

        if($release == '2106') {
            //新版本
            $order = Db::name('shop_refund_order')->where('id',$orderid)->where('aid',aid)->where('bid',bid)->find();
            $orderOrigin = Db::name('shop_order')->where('id',$order['orderid'])->where('aid',aid)->where('bid',bid)->find();
            $reog = Db::name('shop_refund_order_goods')->where('refund_orderid',$orderid)->select()->toArray();

            if($orderOrigin['status']!=1 && $orderOrigin['status']!=2){
                return $this->json(['status'=>0,'msg'=>'该订单状态不允许退款']);
            }
            $params = [];
            $rs = \app\commons\Order::refund($orderOrigin,$order['refund_money'],$order['refund_reason'],$params);
            if($rs['status']==0){
				if($orderOrigin['balance_price'] > 0){
					$orderOrigin2 = $orderOrigin;
					$orderOrigin2['totalprice'] = $orderOrigin2['totalprice'] - $orderOrigin2['balance_price'];
					$orderOrigin2['ordernum'] = $orderOrigin2['ordernum'].'_0';
					$rs = \app\commons\Order::refund($orderOrigin2,$order['refund_money'],$order['refund_reason']);
					if($rs['status']==0){
						return $this->json(['status'=>0,'msg'=>$rs['msg']]);
					}
					if($orderOrigin['balance_pay_status'] == 0){
						$orderOrigin['totalprice'] = $orderOrigin['totalprice'] - $orderOrigin['balance_price'];
					}
				}else{
					return $this->json(['status'=>0,'msg'=>$rs['msg']]);
				}
            }

            Db::name('shop_refund_order')->where('id',$orderid)->where('aid',aid)->where('bid',bid)->update(['status'=>4,'refund_status'=>2]);
            $reOrder = Db::name('shop_refund_order')->where('orderid',$order['orderid'])->where('refund_status', 'in', [2,4])->where('aid',aid)->select();
            $refundTotal = 0;
            foreach ($reOrder as $item) {
                $refundTotal += $item['refund_money'];
            }
            $refundTotal = round($refundTotal,2);
            $orderOrigin['totalprice'] = round($orderOrigin['totalprice'],2);

            //整单全部退时 返还积分和优惠券
            $canRefundNum = 0;
            $prolist = Db::name('shop_order_goods')->where('orderid',$orderOrigin['id'])->select()->toArray();
            foreach ($prolist as $key => $item) {
                $canRefundNum += $item['num'] - $item['refund_num'];
            }
            if($canRefundNum == 0 && $refundTotal == $orderOrigin['totalprice']) {
                Db::name('shop_order')->where('id',$order['orderid'])->where('aid',aid)->where('bid',bid)->update(['status'=>4,'refund_status'=>2,'refund_money' => $refundTotal]);
				Db::name('shop_order_goods')->where(['orderid'=>$order['orderid'],'aid'=>aid, 'bid' => bid])->update(['status'=>4]);
                //积分抵扣的返还
                if($orderOrigin['scoredkscore'] > 0){
                    \app\commons\Member::addscore(aid,$orderOrigin['mid'],$orderOrigin['scoredkscore'],'订单退款返还');
                }
                if($orderOrigin['creditdkcredit'] > 0){
        			\app\commons\Member::addcredit2(aid,$orderOrigin['mid'],$orderOrigin['creditdkcredit'],'订单退款返还');
        		}
				if($orderOrigin['givescore2'] > 0){
					\app\commons\Member::addscore(aid,$orderOrigin['mid'],-$orderOrigin['givescore2'],'订单退款扣除');
				}

                //扣除消费赠送积分
                \app\commons\Member::decscorein(aid,'shop',$orderOrigin['id'],$orderOrigin['ordernum'],'订单退款扣除消费赠送');

                //查询后台是否开启退还已使用的优惠券
                $return_coupon = Db::name('shop_sysset')->where('aid',aid)->value('return_coupon');
                //优惠券抵扣的返还
                if($return_coupon && $orderOrigin['coupon_rid']){
                    Db::name('coupon_record')->where('aid',aid)->where('mid',$orderOrigin['mid'])->where('id','in',$orderOrigin['coupon_rid'])->update(['status'=>0,'usetime'=>'']);
                }
                } else {
                //部分退款
                Db::name('shop_order')->where('id',$order['orderid'])->where('aid',aid)->where('bid',bid)->inc('refund_money',$order['refund_money'])->update(['refund_status'=>2]);
                //重新计算佣金
                \app\commons\Order::updateCommission($prolist,$reog);
            }
            \app\commons\System::plog('商城订单退款审核通过并退款'.$orderid);

            //退货退款 增加库存 减销量
            if($order['refund_type'] == 'return') {
                foreach($reog as $item) {
                    Db::name('shop_guige')->where('aid',aid)->where('id',$item['ggid'])->update(['stock'=>Db::raw("stock+".$item['refund_num']),'sales'=>Db::raw("sales-".$item['refund_num'])]);
                    Db::name('shop_product')->where('aid',aid)->where('id',$item['proid'])->update(['stock'=>Db::raw("stock+".$item['refund_num']),'sales'=>Db::raw("sales-".$item['refund_num'])]);
					}
            }

			if($orderOrigin['fromwxvideo'] == 1){
				\app\commons\Wxvideo::aftersaleupdate($order['orderid'],$order['id']);
			}

            //聚水潭售后订单确认收货
			//退款成功通知
            $tmplcontent = [];
            $tmplcontent['first'] = '您的订单已经完成退款，¥'.$order['refund_money'].'已经退回您的付款账户，请留意查收。';
            $tmplcontent['remark'] = '请点击查看详情~';
            $tmplcontent['orderProductPrice'] = $order['refund_money'];
            $tmplcontent['orderProductName'] = $orderOrigin['title'];
            $tmplcontent['orderName'] = $order['ordernum'];
            $tmplcontentNew = [];
            $tmplcontentNew['character_string1'] = $order['ordernum'];//订单编号
            $tmplcontentNew['thing2'] = $orderOrigin['title'];//商品名称
            $tmplcontentNew['amount3'] = $order['refund_money'];//退款金额
            \app\commons\Wechat::sendtmpl(aid,$orderOrigin['mid'],'tmpl_tuisuccess',$tmplcontent,m_url('pages/my/usercenter'),$tmplcontentNew);
            //订阅消息
            $tmplcontent = [];
            $tmplcontent['amount6'] = $order['refund_money'];
            $tmplcontent['thing3'] = $orderOrigin['title'];
            $tmplcontent['character_string2'] = $order['ordernum'];
			
			$tmplcontentnew = [];
			$tmplcontentnew['amount3'] = $order['refund_money'];
			$tmplcontentnew['thing6'] = $orderOrigin['title'];
			$tmplcontentnew['character_string4'] = $order['ordernum'];
            \app\commons\Wechat::sendwxtmpl(aid,$orderOrigin['mid'],'tmpl_tuisuccess',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);

            //短信通知
            $member = Db::name('member')->where('id',$order['mid'])->find();
            if($member['tel']){
                $tel = $member['tel'];
            }else{
                $tel = $orderOrigin['tel'];
            }
            $rs = \app\commons\Sms::send(aid,$tel,'tmpl_tuisuccess',['ordernum'=>$order['ordernum'],'money'=>$order['refund_money']]);

        } else {
            $order = Db::name($type.'_order')->where(['id'=>$orderid,'aid'=>aid, 'bid' => bid])->find();
            if($type == 'shop'){
                }
            if($order['status']!=1 && $order['status']!=2){
                return $this->json(['status'=>0,'msg'=>'该订单状态不允许退款']);
            }

            if($type=='shop'){
                $refundingMoney = Db::name('shop_refund_order')->where('orderid',$order['id'])->where('aid',aid)->where('bid',bid)->whereIn('refund_status',[1,4])->sum('refund_money');
                if($refundingMoney) {
                    return $this->json(['status'=>0,'msg'=>'请先处理完进行中的退款单']);
                }
                $refundedMoney = Db::name('shop_refund_order')->where('orderid',$order['id'])->where('aid',aid)->where('bid',bid)->where('refund_status',2)->sum('refund_money');
                $order['refund_money'] -= $refundedMoney;
            }

            $params = [];
            $rs = \app\commons\Order::refund($order,$order['refund_money'],$refund_desc,$params);
            if($rs['status']==0){
                return $this->json(['status'=>0,'msg'=>$rs['msg']]);
            }

            if ($type=='shop') {
                \app\commons\Aaa::statusOrder(aid,$orderid,$order);
            }

  


            Db::name($type.'_order')->where(['id'=>$orderid,'aid'=>aid, 'bid' => bid])->update(['status'=>4,'refund_status'=>2,'refund_reason'=>$refund_desc]);
            if(\app\commons\Order::hasOrderGoodsTable($type)){
                Db::name($type.'_order_goods')->where(['orderid'=>$orderid,'aid'=>aid, 'bid' => bid])->update(['status'=>4]);
            }
            //积分抵扣的返还
            if($order['scoredkscore'] > 0){
                \app\commons\Member::addscore(aid,$order['mid'],$order['scoredkscore'],'订单退款返还');
            }
            if($order['creditdkcredit'] > 0){
    			\app\commons\Member::addcredit2(aid,$order['mid'],$order['creditdkcredit'],'订单退款返还');
    		}
			if($order['givescore2'] > 0){
                \app\commons\Member::addscore(aid,$order['mid'],-$order['givescore2'],'订单退款扣除');
            }

            if($type=='scoreshop'){
                //积分返还
                if($order['totalscore'] > 0){
                    $aid2 = aid;$mid2 = $order['mid'];
                    $remark = '订单退款返还';
                    $addscore_params = [];//其他参数
                    \app\commons\Member::addscore($aid2,$mid2,$order['totalscore'],$remark,'',0,0,1,$addscore_params);
                }
            }

            //扣除消费赠送积分
            \app\commons\Member::decscorein(aid,$type,$order['id'],$order['ordernum'],'订单退款扣除消费赠送');

            //查询后台是否开启退还已使用的优惠券
            $return_coupon = Db::name('shop_sysset')->where('aid',aid)->value('return_coupon');
            //优惠券抵扣的返还
            if($return_coupon && $order['coupon_rid']){
                Db::name('coupon_record')->where('aid',aid)->where('mid',$order['mid'])->where('id','in',$order['coupon_rid'])->update(['status'=>0,'usetime'=>'']);
            }
			
			if($type == 'collage'){
                }
            //\app\commons\System::plog('商城订单退款审核通过并退款'.$orderid);
            //退款成功通知
            $tmplcontent = [];
            if($type=='scoreshop'){
                $tmplcontent['first'] = '您的订单已经完成退款，'.$order['totalscore'].t('积分').' + ¥'.$order['refund_money'].'已经退回您的付款账户，请留意查收。';
                $tmplcontent['orderProductPrice'] = $order['totalscore'].t('积分').' + ¥'.$order['refund_money'];
            }else{
                $tmplcontent['first'] = '您的订单已经完成退款，¥'.$order['refund_money'].'已经退回您的付款账户，请留意查收。';
                $tmplcontent['orderProductPrice'] = $order['refund_money'];
            }
            $tmplcontent['remark'] = '请点击查看详情~';
            $tmplcontent['orderProductName'] = $order['title'];
            $tmplcontent['orderName'] = $order['ordernum'];
            $tmplcontentNew = [];
            $tmplcontentNew['character_string1'] = $order['ordernum'];//订单编号
            $tmplcontentNew['thing2'] = $order['title'];//商品名称
            $tmplcontentNew['amount3'] = $order['refund_money'];//退款金额
            \app\commons\Wechat::sendtmpl(aid,$order['mid'],'tmpl_tuisuccess',$tmplcontent,m_url('/pages/my/usercenter'),$tmplcontentNew);
            //订阅消息
            $tmplcontent = [];
            $tmplcontent['amount6'] = $order['refund_money'];
            $tmplcontent['thing3'] = $order['title'];
            $tmplcontent['character_string2'] = $order['ordernum'];
			
			$tmplcontentnew = [];
			$tmplcontentnew['amount3'] = $order['refund_money'];
			$tmplcontentnew['thing6'] = $order['title'];
			$tmplcontentnew['character_string4'] = $order['ordernum'];
            \app\commons\Wechat::sendwxtmpl(aid,$order['mid'],'tmpl_tuisuccess',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);

            //短信通知
            $member = Db::name('member')->where(['id'=>$order['mid']])->find();
            $rs = \app\commons\Sms::send(aid,$member['tel']?$member['tel']:$order['tel'],'tmpl_tuisuccess',['ordernum'=>$order['ordernum'],'money'=>$order['refund_money']]);
        }
        $typeName = \app\commons\Order::getOrderTypeName($type);
        \app\commons\System::plog('手机端后台'.$typeName.'通过退款'.$orderid);
		return $this->json(['status'=>1,'msg'=>'已退款成功']);
	}

	public function returnpass()
    {
        $orderid = input('post.orderid/d');
        $remark = input('post.remark');
        $order = Db::name('shop_refund_order')->where('id',$orderid)->where('aid',aid)->where('bid',bid)->find();
        $orderOrigin = Db::name('shop_order')->where('id',$order['orderid'])->where('aid',aid)->where('bid',bid)->find();

        if($orderOrigin['status']!=1 && $orderOrigin['status']!=2){
            return $this->json(['status'=>0,'msg'=>'该订单状态不允许退款']);
        }

        Db::name('shop_refund_order')->where('id',$orderid)->where('aid',aid)->where('bid',bid)->update(['refund_status'=>4,'refund_checkremark'=>$remark]);
		
		if($orderOrigin['fromwxvideo'] == 1){
			\app\commons\Wxvideo::aftersaleupdate($order['orderid'],$order['id']);
		}
        //退款同意通知
        $tmplcontent = [];
        $tmplcontent['first'] = '商家已同意您的退货退款申请，请及时联系商家退货，商家收到退货后将进行退款';
        $tmplcontent['remark'] = $remark.'，请点击查看详情~';
        $tmplcontent['orderProductPrice'] = $order['refund_money'];
        $tmplcontent['orderProductName'] = $orderOrigin['title'];
        $tmplcontent['orderName'] = $order['ordernum'];
        $tmplcontentNew = [];
        $tmplcontentNew['character_string1'] = $order['ordernum'];//订单编号
        $tmplcontentNew['thing2'] = $orderOrigin['title'];//商品名称
        $tmplcontentNew['amount3'] = $order['refund_money'];//退款金额
        \app\commons\Wechat::sendtmpl(aid,$orderOrigin['mid'],'tmpl_tuisuccess',$tmplcontent,m_url('pages/my/usercenter'),$tmplcontentNew);
        //订阅消息
        $tmplcontent = [];
        $tmplcontent['amount6'] = $order['refund_money'];
        $tmplcontent['thing3'] = $orderOrigin['title'];
        $tmplcontent['character_string2'] = $order['ordernum'];
		
		$tmplcontentnew = [];
		$tmplcontentnew['amount3'] = $order['refund_money'];
		$tmplcontentnew['thing6'] = $orderOrigin['title'];
		$tmplcontentnew['character_string4'] = $order['ordernum'];
        \app\commons\Wechat::sendwxtmpl(aid,$orderOrigin['mid'],'tmpl_tuisuccess',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);

        //短信通知
//        $member = Db::name('member')->where('id',$order['mid'])->find();
//        if($member['tel']){
//            $tel = $member['tel'];
//        }else{
//            $tel = $orderOrigin['tel'];
//        }
//        $rs = \app\commons\Sms::send(aid,$tel,'tmpl_tuisuccess',['ordernum'=>$order['ordernum'],'money'=>$order['refund_money']]);



		//聚水潭售后订单审核通过等待退货
		\app\commons\System::plog('商城订单退款审核通过等待退货'.$orderid);
        return $this->json(['status'=>1,'msg'=>'审核通过，等待买家退货']);
    }
	//关闭订单
	function closeOrder(){
		$type = input('post.type');
		$orderid = input('post.orderid/d');
		$order = Db::name($type.'_order')->where(['id'=>$orderid,'aid'=>aid])->find();
		if(!$order || $order['status']!=0){
			return $this->json(['status'=>0,'msg'=>'关闭失败,订单状态错误']);
		}
		if($type=='shop'){
			//加库存
			$oglist = Db::name($type.'_order_goods')->where(['aid'=>aid,'orderid'=>$orderid])->select();
			foreach($oglist as $og){
				Db::name($type.'_guige')->where(['aid'=>aid,'id'=>$og['ggid']])->update(['stock'=>Db::raw("stock+".$og['num']),'sales'=>Db::raw("sales-".$og['num'])]);
				Db::name($type.'_product')->where(['aid'=>aid,'id'=>$og['proid']])->update(['stock'=>Db::raw("stock+".$og['num']),'sales'=>Db::raw("sales-".$og['num'])]);
				
				}
		}elseif($type=='scoreshop'){
			//加库存
			$oglist = Db::name($type.'_order_goods')->where(['aid'=>aid,'orderid'=>$orderid])->select();
			foreach($oglist as $og){
				Db::name($type.'_product')->where(['aid'=>aid,'id'=>$og['proid']])->update(['stock'=>Db::raw("stock+".$og['num']),'sales'=>Db::raw("sales-".$og['num'])]);
                if($og['ggid']) Db::name($type.'_guige')->where(['aid'=>aid,'id'=>$og['ggid']])->update(['stock'=>Db::raw("stock+".$og['num']),'sales'=>Db::raw("sales-".$og['num'])]);
			}
		}else{
			Db::name($type.'_product')->where(['aid'=>aid,'id'=>$order['proid']])->update(['stock'=>Db::raw("stock+".$order['num']),'sales'=>Db::raw("sales-".$order['num'])]);
			if($type=='collage' || $type=='seckill'){
				Db::name($type.'_guige')->where(['aid'=>aid,'id'=>$order['ggid']])->update(['stock'=>Db::raw("stock+".$order['num']),'sales'=>Db::raw("sales-".$order['num'])]);
			}
		}
		//积分抵扣的返还
		if($order['scoredkscore'] > 0){
			\app\commons\Member::addscore(aid,$order['mid'],$order['scoredkscore'],'订单退款返还');
		}
		if($order['creditdkcredit'] > 0){
			\app\commons\Member::addcredit2(aid,$order['mid'],$order['creditdkcredit'],'订单退款返还');
		}
		if($order['givescore2'] > 0){
			\app\commons\Member::addscore(aid,$order['mid'],-$order['givescore2'],'订单退款扣除');
		}
		//优惠券抵扣的返还
		if($order['coupon_rid']){
			Db::name('coupon_record')->where('aid',aid)->where('id','in',$order['coupon_rid'])->update(['status'=>0,'usetime'=>'']);
		}
        if(getcustom('money_dec')){
            //返回余额抵扣
            if($order['dec_money']>0){
                \app\commons\Member::addmoney(aid,$order['mid'],$order['dec_money'],t('余额').'抵扣返回，订单号: '.$order['ordernum']);
            }
        }
        $rs = Db::name($type.'_order')->where(['id'=>$orderid,'aid'=>aid])->update(['status'=>4]);
		if(\app\commons\Order::hasOrderGoodsTable($type)){
			Db::name($type.'_order_goods')->where(['orderid'=>$orderid,'aid'=>aid])->update(['status'=>4]);
		}
        $typeName = \app\commons\Order::getOrderTypeName($type);
        \app\commons\System::plog('手机端后台'.$typeName.'关闭订单'.$orderid);
		return $this->json(['status'=>1,'msg'=>'操作成功']);
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
			$where[] = ['member.nickname|maidan_order.ordernum','like','%'.input('param.keyword').'%'];
		}
		if($pagenum == 1){
			$count = 0 + Db::name('maidan_order')->alias('maidan_order')->field('member.nickname,member.headimg,maidan_order.*')->join('member member','member.id=maidan_order.mid','left')->where($where)->count();
		}else{
			$count = 0;
		}
		$datalist = Db::name('maidan_order')->alias('maidan_order')->field('member.nickname,member.headimg,maidan_order.*')->join('member member','member.id=maidan_order.mid','left')->where($where)->page($pagenum,$pernum)->order('maidan_order.id desc')->select()->toArray();
		if(!$datalist) $datalist = [];
		$rdata = [];
		$rdata['count'] = $count;
		$rdata['data'] = $datalist;
		return $this->json($rdata);
	}
	public function maidandetail(){
		$id = input('param.id/d');
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['bid','=',bid];
		$where[] = ['id','=',$id];
		$detail = Db::name('maidan_order')->where($where)->find();
		$detail['paytime'] = date('Y-m-d H:i:s',$detail['paytime']);
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
		$member = Db::name('member')->where(['id'=>$detail['mid']])->find();
		if(!$member) $member = [];
		$rdata = [];
		$rdata['detail'] = $detail;
		$rdata['couponrecord'] = $couponrecord;
		$rdata['mendian'] = $mendian;
		$rdata['member'] = $member;
		return $this->json($rdata);
	}
	
	//获取配送员
	public function getpeisonguser(){
		$set = Db::name('peisong_set')->where('aid',aid)->find();
		
		$order = Db::name(input('param.type'))->where('id',input('param.orderid'))->find();
		if($order['bid']>0){
			$business = Db::name('business')->field('name,address,tel,logo,longitude,latitude')->where('id',$order['bid'])->find();
		}else{
			$business = Db::name('admin_set')->field('name,address,tel,logo,longitude,latitude')->where('aid',aid)->find();
		}
        //查询骑行距离
        $mapqq = new \app\commons\MapQQ();
        $bicycl = $mapqq->getDirectionDistance($order['longitude'],$order['latitude'],$business['longitude'],$business['latitude'],1);
        if($bicycl && $bicycl['status']==1){
            $juli = $bicycl['distance'];
        }else{
            $juli = getdistance($order['longitude'],$order['latitude'],$business['longitude'],$business['latitude'],1);
        }
		$ticheng = \app\models\PeisongOrder::ticheng($set,$order,$juli/1000);
		if($set['make_status']==1){ //码科配送
			$rs = \app\commons\Make::getprice(aid,$order['bid'],$business['latitude'],$business['longitude'],$order['latitude'],$order['longitude']);
			if($rs['status']==0) return $this->json($rs);
			$ticheng = $rs['price'];
			$selectArr = [];
			$set['paidantype'] = 2;
		}else{
			$selectArr = [];
			if($set['paidantype'] == 0){ //抢单模式
				$selectArr[] = ['id'=>0,'title'=>'--配送员抢单--'];
			}else{
				$peisonguser = Db::name('peisong_user')->where('aid',aid)->where('status',1)->order('sort desc,id')->select()->toArray();
				foreach($peisonguser as $k=>$v){
					$dan = Db::name('peisong_order')->where('psid',$v['id'])->where('status','in','0,1')->count();
					$title = $v['realname'].'-'.$v['tel'].'(配送中'.$dan.'单)';
					$selectArr[] = ['id'=>$v['id'],'title'=>$title];
				}
			}
		}
		$psfee = $ticheng * (1 + $set['businessfee']*0.01);
		return $this->json(['status'=>1,'peisonguser'=>$selectArr,'paidantype'=>$set['paidantype'],'psfee'=>$psfee,'ticheng'=>$ticheng]);
	}
	//下配送单
	public function peisong(){
		$orderid = input('post.orderid/d');
		$type = input('post.type');
		$psid = input('post.psid/d');

        $set = Db::name('peisong_set')->where('aid',aid)->find();
        if(getcustom('express_maiyatian')) {
            if($set['myt_status'] == 1){
                $psid = -2;//  -1、码科  -2、麦芽田配送
            }
        }

		$order = Db::name($type)->where('id',$orderid)->where('aid',aid)->where('bid',bid)->find();

		//如果选择了配送时间，未到配送时间内不可以进行配送
		if(!$order) return $this->json(['status'=>0,'msg'=>'订单不存在']);
		if($order['status']!=1 && $order['status']!=12) return $this->json(['status'=>0,'msg'=>'订单状态不符合']);

        $other = [];
        if(getcustom('express_maiyatian')){
            $other['myt_shop_id'] = input('post.myt_shop_id')?input('post.myt_shop_id'):0;
            $other['myt_weight']  = input('post.myt_weight')?input('post.myt_weight'):1;
            if(!is_numeric($other['myt_weight'])){
                return $this->json(['status'=>0,'msg'=>'重量必须为纯数字']);
            }
            $other['myt_remark']  = input('post.myt_remark')?input('post.myt_remark'):'';
        }

		$rs = \app\models\PeisongOrder::create($type,$order,$psid,$other);
		if($rs['status']==0) return $this->json($rs);
        //发货信息录入 微信小程序+微信支付
        if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
            \app\commons\Order::wxShipping(aid,$order,$type);
        }
		\app\commons\System::plog('订单配送'.$orderid);
		return $this->json(['status'=>1,'msg'=>'操作成功']);
	}

    //下配送单
    public function peisongWx(){
        $orderid = input('post.orderid/d');
        $type = input('post.type');
        $psid = input('post.psid/d');
        $order = Db::name($type)->where('id',$orderid)->where('aid',aid)->where('bid',bid)->find();

        if(!$order) return $this->json(['status'=>0,'msg'=>'订单不存在']);
        if($order['status']!=1 && $order['status']!=12) return $this->json(['status'=>0,'msg'=>'订单状态不符合']);

        $rs = \app\customs\ExpressWx::addOrder($type,$order,$psid);
        if($rs['status']==0) return $this->json($rs);
        //发货信息录入 微信小程序+微信支付
        if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
            \app\commons\Order::wxShipping(aid,$order, $type);
        }
        \app\commons\System::plog('订单即时配送派单'.$orderid);
        return $this->json(['status'=>1,'msg'=>'操作成功']);
    }

	//订单列表
	function yuyueorder(){
		$st = input('param.st');
		if(!input('?param.st') || $st === ''){
			$st = 'all';
		}
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['bid','=',bid];
		$where[] = ['delete','=',0];
        if(input('param.keyword')) $where[] = ['ordernum|title', 'like', '%'.input('param.keyword').'%'];

        if($st == 'all'){
				
		}elseif($st == '0'){
			$where[] = ['status','=',0];
		}elseif($st == '1'){
			$where[] = ['status','=',1];
		}elseif($st == '2'){
			$where[] = ['status','=',2];
		}elseif($st == '3'){
			$where[] = ['status','=',3];
		}elseif($st == '4'){
			$where[] = ['status','=',4];
		}
		$pernum = 10;
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$datalist = Db::name('yuyue_order')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		if(!$datalist) $datalist = array();
        $showpaidanfee = true;
        if(bid>0){
            $showpaidanfee = false;
        }
		foreach($datalist as $key=>$v){
			if($v['bid']!=0){
				$datalist[$key]['binfo'] = Db::name('business')->where('aid',aid)->where('id',$v['bid'])->field('id,name,logo')->find();
			}
			//查看服务状态
			if($v['worker_orderid']>0){
				$datalist[$key]['worker'] = Db::name('yuyue_worker_order')->where('aid',aid)->where('id',$v['worker_orderid'])->field('id,status')->find();
			}
			$datalist[$key]['senddate'] = date('Y-m-d H:i:s',$v['send_time']);
			$datalist[$key]['member'] = Db::name('member')->field('id,headimg,nickname')->where('id',$v['mid'])->find();
			$datalist[$key]['showpaidanfee'] = $showpaidanfee;
			if(!$datalist[$key]['member']) $datalist[$key]['member'] = [];
            }
		$yuyue_sign = false;
		$rdata = [];
		$rdata['datalist'] = $datalist;
		$rdata['codtxt'] = Db::name('shop_sysset')->where('aid',aid)->value('codtxt');
		$rdata['st'] = $st;
		$rdata['yuyue_sign'] = $yuyue_sign;
		return $this->json($rdata);
	}


	public function yuyueorderdetail(){
		$detail = Db::name('yuyue_order')->where('id',input('param.id/d'))->find();
		if(!$detail) return $this->json(['status'=>0,'msg'=>'订单不存在']);
		$detail['createtime'] = $detail['createtime'] ? date('Y-m-d H:i:s',$detail['createtime']) : '';
		$detail['collect_time'] = $detail['collect_time'] ? date('Y-m-d H:i:s',$detail['collect_time']) : '';
		$detail['paytime'] = $detail['paytime'] ? date('Y-m-d H:i:s',$detail['paytime']) : '';
		$detail['refund_time'] = $detail['refund_time'] ? date('Y-m-d H:i:s',$detail['refund_time']) : '';
		$detail['send_time'] = $detail['send_time'] ? date('Y-m-d H:i:s',$detail['send_time']) : '';
		$detail['formdata'] = \app\models\Freight::getformdata($detail['id'],'yuyue_order');
		$storeinfo = [];
		if($detail['freight_type'] == 1){
            $storeinfo = Db::name('mendian')->where('id',$detail['mdid'])->field('id,name,address,longitude,latitude')->find();
		}
        $ticheng = 0;
        $plateform_rate = 0;
        $yuyueset = Db::name('yuyue_set')->where('aid',aid)->field('autoclose')->find();
		if($detail['bid'] > 0){
			$business = Db::name('business')->where('aid',aid)->where('id',$detail['bid'])->field('id,name,logo,feepercent')->find();
            $detail['binfo'] = $business;
			$iscommentdp = 0;
			$commentdp = Db::name('business_comment')->where('orderid',$detail['id'])->find();
			if($commentdp) $iscommentdp = 1;
            if($business['feepercent']>0){
                $plateform_rate = $business['feepercent'];
            }
		}else{
			$iscommentdp = 1;
		}
        $showfeedetail = false;
        $detail['showfeedetail'] = $showfeedetail;

		$prolist = Db::name('yuyue_order')->where('id',$detail['id'])->find();
		if($detail['status']==0 && $yuyueset['autoclose'] > 0 && $detail['paytypeid'] != 5){
			$lefttime = strtotime($detail['createtime']) + $yuyueset['autoclose']*60 - time();
			if($lefttime < 0) $lefttime = 0;
		}else{
			$lefttime = 0;
		}
        $canPaidan = true;
        $detail['can_paidan'] = $canPaidan;
		
		$member = Db::name('member')->where('id',$detail['mid'])->field('id,nickname,headimg')->find();
		$detail['nickname'] = $member['nickname'];
		$detail['headimg'] = $member['headimg'];


		$showlist=false;
		$rdata = [];
		$rdata['detail'] = $detail;
		$rdata['showlist'] = $showlist;
		$rdata['iscommentdp'] = $iscommentdp;
		$rdata['prolist'] = $prolist;
		$rdata['storeinfo'] = $storeinfo;
		$rdata['lefttime'] = $lefttime;

		$text = ['上门服务'=>'上门服务','到店服务'=>'到店服务'];
		$rdata['text'] = $text;
		return $this->json($rdata);
	}

	//获取预约配送员
	public function getyuyuepsuser(){
		$set = Db::name('yuyue_set')->where('aid',aid)->where('bid',bid)->find();
		$order = Db::name(input('param.type'))->where('id',input('param.orderid'))->find();
		if($order['bid']>0){
			$business = Db::name('business')->field('name,address,tel,logo,longitude,latitude')->where('id',$order['bid'])->find();
		}else{
			$business = Db::name('admin_set')->field('name,address,tel,logo,longitude,latitude')->where('aid',aid)->find();
		}
//		$juli = getdistance($order['longitude'],$order['latitude'],$business['longitude'],$business['latitude'],1);
		$ticheng = $order['paidan_money'];
		$selectArr = [];
		if($set['paidantype'] == 0){ //抢单模式
			$selectArr[] = ['id'=>0,'title'=>'--服务人员抢单--'];
		}else{
            $where = [];
            $where[] = ['aid','=',aid];
            $where[] = ['status','=',1];
            $bid = $order['bid'];
            //使用平台配送员
            $where[] = ['bid','=',$bid];
			$peisonguser = Db::name('yuyue_worker')->where($where)->order('sort desc,id')->select()->toArray();
			foreach($peisonguser as $k=>$v){
				$dan = Db::name('yuyue_worker_order')->where('worker_id',$v['id'])->where('status','in','0,1')->count();
				$title = $v['realname'].'-'.$v['tel'].'(进行中'.$dan.'单)';
				//查看是否在改时间已经有服务
				$order = Db::name('yuyue_order')->where('aid',aid)->where('worker_id',$v['id'])->where('status','in','1,2')->where('yy_time',$order['yy_time'])->find();
				$status = 1;
				if($order){
					$status=-1;
				}
				$selectArr[] = ['id'=>$v['id'],'title'=>$title,'status'=>$status];
			}
		}
	
		$psfee = $ticheng * (1 + $set['businessfee']*0.01);
		return $this->json(['status'=>1,'peisonguser'=>$selectArr,'paidantype'=>$set['paidantype'],'psfee'=>$psfee,'ticheng'=>$ticheng]);
	}
	//派单
	public function yuyuepeisong(){
		$orderid = input('post.orderid/d'); 
		$worker_id = input('post.worker_id/d');
		//if(!$worker_id) return $this->json(['status'=>0,'msg'=>'请选择服务人员']);
		$order = Db::name('yuyue_order')->where('id',$orderid)->where('aid',aid)->where('bid',bid)->find();
		if(!$order) return $this->json(['status'=>0,'msg'=>'订单不存在']);
		if($order['status']!=1 && $order['status']!=12) return $this->json(['status'=>0,'msg'=>'订单状态不符合']);
		//取出该订单的服务人员
		$fwpeoid = Db::name('yuyue_product')->where('id',$order['proid'])->where('aid',aid)->where('bid',bid)->value('fwpeoid');
		$type = input('post.type/d');
		if(false){}else{
			$rs = \app\models\YuyueWorkerOrder::create($order,$worker_id,$fwpeoid);
		}
		if($rs['status']==0) return $this->json($rs);
		\app\commons\System::plog('预约派单'.$orderid);
		return $this->json(['status'=>1,'msg'=>'操作成功']);
	}

	public function selectworker(){
		$orderid = input('param.id/d');
		$orders = Db::name('yuyue_order')->where('id',$orderid)->where('aid',aid)->where('bid',bid)->find();
		//取出该订单的服务人员
		$fwpeoid = Db::name('yuyue_product')->where('id',$orders['proid'])->where('aid',aid)->where('bid',bid)->value('fwpeoid');
		$pernum = 10;
		$pagenum = input('param.pagenum');
		if(!$pagenum) $pagenum = 1;
		$peoarr = explode(',',$fwpeoid);
		$longitude = $orders['longitude'];
		$latitude = $orders['latitude'];
		if($longitude && $latitude){
			$orderBy = Db::raw("({$longitude}-longitude)*({$longitude}-longitude) + ({$latitude}-latitude)*({$latitude}-latitude) ");
		}else{
			$orderBy = 'sort desc,id';
		}

		$datalist = Db::name('yuyue_worker')->where('aid',aid)->where('status',1)->where('id','in',$peoarr)->page($pagenum,$pernum)->order($orderBy)->select()->toArray();
		//查看该时间是否已经预约出去
		foreach($datalist as &$d){
			$type = Db::name('yuyue_worker_category')->where(['id'=>$d['cid']])->find();
			$d['typename'] = $type['name'];
			$order = Db::name('yuyue_order')->where('aid',aid)->where('worker_id',$d['id'])->where('status','in','1,2')->where('yy_time',$orders['yy_time'])->find();
			$d['yystatus']=1;
			if($order){
				$d['yystatus']=-1;
			}
			//服务人员到用户的距离 骑行距离
            $mapqq = new \app\commons\MapQQ();
            $bicycl = $mapqq->getDirectionDistance($orders['longitude'],$orders['latitude'],$d['longitude'],$d['latitude'],1);
            if($bicycl && $bicycl['status']==1){
                $juli = $bicycl['distance'];
            }else{
                $juli = getdistance($orders['longitude'],$orders['latitude'],$d['longitude'],$d['latitude'],1);
            }
            //var_dump($juli);
			if($juli> 1000){
				$d['juli'] = round($juli/1000,1);
				$d['juli_unit'] = 'km';
			}else{
				$d['juli_unit'] = 'm';
			}
		}
		if(!$datalist) $datalist = [];
		return $this->json(['status'=>1,'data'=>$datalist]);
	}
	public function addyuyuemoney(){
		$orderid = input('post.orderid');
		$price = input('post.price');
		if(!$price) return $this->json(['status'=>0,'msg'=>'请填写金额']);
		$order = Db::name('yuyue_order')->where(['id'=>$orderid])->find();
		if(!$order ) return $this->json(['status'=>0,'msg'=>'预约订单不存在']);
		$wokerorder = Db::name('yuyue_worker_order')->where(['id'=>$order['worker_orderid']])->find();
		if(!$wokerorder )  return $this->json(['status'=>0,'msg'=>'服务订单不存在']);
	
		$addmoneyPayorderid = input('post.addmoneyPayorderid');
		if($addmoneyPayorderid){
			//修改
			if($price == $order['addmoney']){
				return $this->json(['status'=>0,'msg'=>'金额无变化']);
			}
			$payorder = Db::name('payorder')->where(['id'=>$addmoneyPayorderid])->find();
			if(!$payorder){
				return $this->json(['status'=>0,'msg'=>'支付订单不存在']);
			}
			Db::name('payorder')->where(['id'=>$addmoneyPayorderid])->update(['money'=>$price]);
			$balance_pay_orderid = $addmoneyPayorderid;
			$order = Db::name('yuyue_order')->where(['id'=>$orderid])->update(['addmoney'=>$price]);
		}
	    return $this->json(['status'=>0,'msg'=>'修改成功','payorderid'=>$balance_pay_orderid]);

	}

    //下配送单
    public function mytprice(){
        if(getcustom('express_maiyatian')) {
            if(request()->isPost()){
                //预先读取计费
                $orderid = input('orderid/d');
                $type    = input('type');

                $order = Db::name($type)->where('id',$orderid)->where('aid',aid)->where('bid',bid)->find();
                
                //如果选择了配送时间，未到配送时间内不可以进行配送
                if(getcustom('business_withdraw')){
                    if($order['freight_time']){
                        $freight_time = explode('~',$order['freight_time']);
                        $begin_time = strtotime($freight_time[0]);
                        $date = explode(' ',$freight_time[0]);
                        $end_time =strtotime($date[0].' '.$freight_time[1]);
                        if(time()<$begin_time || (time()>$end_time)){
                            return $this->json(['status'=>0,'msg'=>'未在配送时间内']);    
                        }
                    }
                }
                if(!$order) return $this->json(['status'=>0,'msg'=>'订单不存在']);
                if($order['status']!=1 && $order['status']!=12) return $this->json(['status'=>0,'msg'=>'订单状态不符合']);

                $data   = '';//计价数据
                $detail = '';//配送详情
                $weight = \app\customs\MaiYaTianCustom::getweight(aid,$order,$type);//默认一千克

                $other = [];
                $other['myt_shop_id'] = 0;
                $other['myt_weight']  = $weight;
                $other['myt_remark']  = '';

                //预估配送费
                $set = Db::name('peisong_set')->where('aid',aid)->find();
                $res_price = \app\customs\MaiYaTianCustom::order_price(aid,$set,$order,'',$other,1);

                $msg = '';
                if($res_price['status']== 0){
                    $msg = $res_price['msg'].'(麦芽田返回)';
                }else{
                    $data = $res_price['data'];
                    if($data && $data['detail']){
                        $detail = $data['detail'];
                    }
                }

                $resdata = [];
                $resdata['orderid'] = $orderid;
                $resdata['type']    = $type;
                $resdata['data']    = $data;
                $resdata['detail']  = $detail;
                $resdata['weight']  = $weight;
                $resdata['msg']     = $msg;
                return $this->json(['status'=>1,'data'=>$resdata]);
            }
        }
    }

    //商城订单详情
    public function weightOrderFahuo(){
        }

    public function wifiprint(){
        }
    //改价格
    public function changeprice(){

        if(request()->isPost()) {
            //预先读取计费
            $orderid = input('post.orderid/d');
            $type = input('post.type');
            $newprice = input('post.val/f');
            $newordernum = date('ymdHis').rand(100000,999999);

            $where = [];
            $where[] = ['aid','=',aid];
            if(bid > 0){
                $where[] = ['bid','=',bid];
            }

            $order = Db::name('shop_order')->where($where)->where('id',$orderid)->find();
            if($newprice > $order['totalprice']) return json(['status'=>0,'msg'=>'只能优惠不可加价，加价可通过下单其他商品补差价']);
            $ordernumArr = explode('_',$order['ordernum']);
            if($ordernumArr[1]) $newordernum .= '_'.$ordernumArr[1];
            $discount_money_admin = $order['totalprice']-$newprice;//管理员优惠金额（正数）
            Db::name('shop_order')->where($where)->where('id',$orderid)->update(['totalprice'=>$newprice,'ordernum'=>$newordernum,'discount_money_admin'=>$discount_money_admin]);
            Db::name('shop_order_goods')->where($where)->where('orderid',$orderid)->update(['ordernum'=>$newordernum]);
            //订单商品价格也需同步修改，涉及商家结算
            $oglist = Db::name('shop_order_goods')->where($where)->where('orderid',$orderid)->select()->toArray();
            foreach ($oglist as $og){
                $rate = $newprice/$order['totalprice'];
                $og['real_totalprice'] = $rate*$og['real_totalprice'];
                if(!is_null($og['business_total_money'])) {
                    $og['business_total_money'] = $rate*$og['business_total_money'];
                }
                Db::name('shop_order_goods')->where('id',$og['id'])->where('orderid',$orderid)->update($og);
            }

            $payorderid = Db::name('shop_order')->where('aid',aid)->where('id',$orderid)->value('payorderid');

            \app\models\Payorder::updateorder($payorderid,$newordernum,$newprice,$orderid);
            \app\commons\System::plog('商城订单改价格'.$orderid.'，原价格:'.$order['totalprice'].'，新价格:'.$newprice);
            return $this->json(['status'=>1,'msg'=>'修改完成']);
        }
    }

    //退款信息查询
    public function refundinit(){
        //查询订单信息
        $detail = Db::name('shop_order')->where('id',input('param.orderid/d'))->where('aid',aid)->find();
        if(!$detail){
            return $this->json(['status'=>0,'msg'=>'订单不存在']);
        }

        $detail['createtime'] = $detail['createtime'] ? date('Y-m-d H:i:s',$detail['createtime']) : '';
        $detail['collect_time'] = $detail['collect_time'] ? date('Y-m-d H:i:s',$detail['collect_time']) : '';
        $detail['paytime'] = $detail['paytime'] ? date('Y-m-d H:i:s',$detail['paytime']) : '';
        $detail['refund_time'] = $detail['refund_time'] ? date('Y-m-d H:i:s',$detail['refund_time']) : '';
        $detail['send_time'] = $detail['send_time'] ? date('Y-m-d H:i:s',$detail['send_time']) : '';

        $refundMoneySum = Db::name('shop_refund_order')->where('orderid',$detail['id'])->where('aid',aid)->whereIn('refund_status',[1,2,4])->sum('refund_money');

        $canRefundNum = 0;
        $totalNum = 0;
        $returnTotalprice = 0;
        $prolist = Db::name('shop_order_goods')->where('orderid',$detail['id'])->select()->toArray();
        foreach ($prolist as $key => $item) {
            $prolist[$key]['canRefundNum'] = $item['num'] - $item['refund_num'];
            $totalNum += $item['num'];
            $canRefundNum += $item['num'] - $item['refund_num'];
//            $returnTotalprice += $item['real_totalprice'] / $item['num'] * ($item['num'] - $item['refund_num']);
        }
        $totalprice = $detail['totalprice'];
        if($detail['balance_price'] > 0 && $detail['balance_pay_status'] == 0){
            $totalprice = $totalprice - $detail['balance_price'];
        }
        if($canRefundNum == $totalNum) {
            $returnTotalprice = $totalprice;
        } else {
            $returnTotalprice = $totalprice - $refundMoneySum;
        }
        //可退款金额=总金额-审核中-已退款
        $detail['canRefundNum'] = $canRefundNum;
        $detail['totalNum'] = $totalNum;
        $detail['returnTotalprice'] = $returnTotalprice;
//        if($canRefundNum == 0) {
//            return $this->json(['status'=>0,'msg'=>'当前订单没有可退款的商品']);
//        }
        //todo 确认收货后的退款

        $rdata = [];
        $rdata['status'] = 1;
        $rdata['detail'] = $detail;
        $rdata['prolist'] = $prolist;

        return $this->json($rdata);
    }

    //退款
    public function refund(){
        $orderid = input('post.orderid/d');
        $reason = input('post.reason');
        if(bid == 0){
            $order = Db::name('shop_order')->where('id',$orderid)->where('aid',aid)->find();
        }else{
            $order = Db::name('shop_order')->where('id',$orderid)->where('aid',aid)->where('bid',bid)->find();
        }
        if(!$order) return $this->json(['status'=>0,'msg'=>'订单不存在']);
        if($order['status']!=1 && $order['status']!=2){
            return $this->json(['status'=>0,'msg'=>'该订单状态不允许退款']);
        }
        $refundingMoney = Db::name('shop_refund_order')->where('orderid',$order['id'])->where('aid',aid)->whereIn('refund_status',[1,4])->sum('refund_money');
        if($refundingMoney) {
            return $this->json(['status'=>0,'msg'=>'请先处理完进行中的退款单']);
        }
        try {
            Db::startTrans();
            //新退款 202108
            $post = input('post.');
            $orderid = intval($post['orderid']);
            $money = floatval($post['money']);
            $refundNum = $post['refundNum'];

            $order = Db::name('shop_order')->where('aid', aid)->where('id', $orderid)->find();
            if (!$order || ($order['status'] != 1 && $order['status'] != 2)) {
                return $this->json(['status' => 0, 'msg' => '订单状态不符合退款要求']);
            }
            if ($money < 0 || $money > $order['totalprice']) {
                return $this->json(['status' => 0, 'msg' => '退款金额有误']);
            }
            if (empty($refundNum)) {
                return $this->json(['status' => 0, 'msg' => '请选择退款的商品']);
            }
            $totalRefundNum = 0;
            $returnTotalprice = 0;
            $prolist = Db::name('shop_order_goods')->where('orderid', $orderid)->select();
            $newKey = 'id';
            $prolist = $prolist->dictionary(null, $newKey);
            $ogids = array_keys($prolist);
            $refundMoneySum = Db::name('shop_refund_order')->where('orderid', $orderid)->where('aid', aid)->whereIn('refund_status', [1, 2, 4])->sum('refund_money');

            $canRefundNum = 0;
            $totalNum = 0;
            $canRefundProductPrice = 0;
            $canRefundTotalprice = 0;
            foreach ($prolist as $key => $item) {
                $prolist[$key]['canRefundNum'] = $item['num'] - $item['refund_num'];
                $totalNum += $item['num'];
                $canRefundNum += $item['num'] - $item['refund_num'];
                $canRefundProductPrice += $item['real_totalprice'] / $item['num'] * ($item['num'] - $item['refund_num']);
            }

            foreach ($refundNum as $item) {
                if (!in_array($item['ogid'], $ogids)) {
                    return $this->json(['status' => 0, 'msg' => '退款商品不存在']);
                }
                if ($item['num'] > $prolist[$item['ogid']]['num'] - $prolist[$item['ogid']]['refund_num']) {
                    return $this->json(['status' => 0, 'msg' => $prolist[$item['ogid']]['name'] . '退款数量超出范围']);
                }
                $totalRefundNum += $item['num'];
            }
            if ($totalRefundNum == 0) {
                return $this->json(['status' => 0, 'msg' => '请选择退款的商品']);
            }
            $totalprice = $order['totalprice'];
            if($order['balance_price'] > 0 && $order['balance_pay_status'] == 0){
                $totalprice = $totalprice - $order['balance_price'];
            }
            if ($canRefundNum == $totalNum && $totalNum == $totalRefundNum) {
                $canRefundTotalprice = $totalprice;
            } else {
                $canRefundTotalprice = $totalprice - $refundMoneySum;
            }

            if ($money > $canRefundTotalprice) {
                return $this->json(['status' => 0, 'msg' => '退款金额超出范围']);
            }

            $data = [
                'aid' => $order['aid'],
                'bid' => $order['bid'],
                'mdid' => $order['mdid'],
                'mid' => $order['mid'],
                'orderid' => $order['id'],
                'ordernum' => $order['ordernum'],
                'refund_type' => 'refund',
                'refund_ordernum' => '' . date('ymdHis') . rand(100000, 999999),
                'refund_money' => $money,
                'refund_reason' => '手机端后台退款：' . $post['reason'],
                'refund_pics' => '',
                'createtime' => time(),
                'refund_time' => time(),
                'refund_status' => 2,
                'platform' => platform,
            ];

            $refund_id = Db::name('shop_refund_order')->insertGetId($data);

            if ($order['fromwxvideo'] == 1) {
                \app\commons\Wxvideo::aftersaleadd($order['id'], $refund_id);
            }
            if ($data['refund_money'] > 0) {
                $is_refund = 1;
                if($is_refund){
                    $params = [];
                    $rs = \app\commons\Order::refund($order, $data['refund_money'], $reason,$params);
                    if ($rs['status'] == 0) {
                        if($order['balance_price'] > 0){
                            $order2 = $order;
                            $order2['totalprice'] = $order2['totalprice'] - $order2['balance_price'];
                            $order2['ordernum'] = $order2['ordernum'].'_0';
                            $rs = \app\commons\Order::refund($order2,$data['refund_money'],$reason);
                            if($rs['status']==0){
                                Db::name('shop_refund_order')->where('id', $refund_id)->delete();
                                return $this->json(['status'=>0,'msg'=>$rs['msg']]);
                            }
                        }else{
                            Db::name('shop_refund_order')->where('id', $refund_id)->delete();
                            return $this->json(['status'=>0,'msg'=>$rs['msg']]);
                        }
                    }
                }

            }

            foreach ($refundNum as $item) {
                if ($item['num'] < 1) continue;
                $od = [
                    'aid' => $order['aid'],
                    'bid' => $order['bid'],
                    'mid' => $order['mid'],
                    'orderid' => $order['id'],
                    'ordernum' => $order['ordernum'],
                    'refund_orderid' => $refund_id,
                    'refund_ordernum' => $data['refund_ordernum'],
                    'refund_num' => $item['num'],
                    'refund_money' => $item['num'] * $prolist[$item['ogid']]['real_totalprice'] / $prolist[$item['ogid']]['num'],
                    'ogid' => $item['ogid'],
                    'proid' => $prolist[$item['ogid']]['proid'],
                    'name' => $prolist[$item['ogid']]['name'],
                    'pic' => $prolist[$item['ogid']]['pic'],
                    'procode' => $prolist[$item['ogid']]['procode'],
                    'ggid' => $prolist[$item['ogid']]['ggid'],
                    'ggname' => $prolist[$item['ogid']]['ggname'],
                    'cid' => $prolist[$item['ogid']]['cid'],
                    'cost_price' => $prolist[$item['ogid']]['cost_price'],
                    'sell_price' => $prolist[$item['ogid']]['sell_price'],
                    'createtime' => time()
                ];
                Db::name('shop_refund_order_goods')->insertGetId($od);
                Db::name('shop_order_goods')->where('aid', aid)->where('id', $item['ogid'])->inc('refund_num', $item['num'])->update();
                }

            //        $order_goods = Db::name('shop_order_goods')->where('orderid',$orderid)->where('aid',aid)->where('bid',bid)->fieldRaw('ggid,proid,num,refund_num, num-refund_num as true_num')->select()->toArray();
            //		Db::name('shop_order')->where('id',$orderid)->where('aid',aid)->where('bid',bid)->update(['status'=>4,'refund_status'=>2,'refund_money'=>$refund_money,'refund_reason'=>$reason]);
            //		Db::name('shop_order_goods')->where('orderid',$orderid)->where('aid',aid)->where('bid',bid)->update(['status'=>4,'refund_num' => Db::raw('num')]);

            //恢复库存 删除销量
            foreach ($refundNum as $item) {
                Db::name('shop_guige')->where('aid', aid)->where('id', $prolist[$item['ogid']]['ggid'])->update(['stock' => Db::raw("stock+" . $item['num']), 'sales' => Db::raw("sales-" . $item['num'])]);
                Db::name('shop_product')->where('aid', aid)->where('id', $prolist[$item['ogid']]['proid'])->update(['stock' => Db::raw("stock+" . $item['num']), 'sales' => Db::raw("sales-" . $item['num'])]);

                }

            //整单全部退时 返还积分和优惠券
            if ($totalRefundNum == $canRefundNum && $money == $canRefundTotalprice) {
                
                
              
                \app\commons\Aaa::statusOrder(aid,$order['id'],$order);
               

                
                
                Db::name('shop_order')->where('id', $order['id'])->where('aid', aid)->update(['status' => 4, 'refund_status' => 2, 'refund_money' => $refundMoneySum + $data['refund_money']]);
                Db::name('shop_order_goods')->where('orderid', $order['id'])->where('aid', aid)->update(['status' => 4]);
                //积分抵扣的返还
                if ($order['scoredkscore'] > 0) {
                    \app\commons\Member::addscore(aid, $order['mid'], $order['scoredkscore'], '订单退款返还');
                }
                if ($order['givescore2'] > 0) {
                    \app\commons\Member::addscore(aid, $order['mid'], -$order['givescore2'], '订单退款扣除');
                }
                //扣除消费赠送积分
                \app\commons\Member::decscorein(aid,'shop',$order['id'],$order['ordernum'],'订单退款扣除消费赠送');
                //查询后台是否开启退还已使用的优惠券
                $return_coupon = Db::name('shop_sysset')->where('aid',aid)->value('return_coupon');
                //优惠券抵扣的返还
                if ($return_coupon && $order['coupon_rid']) {
                    Db::name('coupon_record')->where('aid', aid)->where('mid', $order['mid'])->where('id', 'in', $order['coupon_rid'])->update(['status' => 0, 'usetime' => '']);
                }
                //元宝返回
                if ($order['givescore2'] > 0) {
                    \app\commons\Member::addscore(aid, $order['mid'], -$order['givescore2'], '订单退款扣除');
                }
                if(getcustom('money_dec')){
                    if($order['dec_money']>0){
                        \app\commons\Member::addmoney(aid,$order['mid'],$order['dec_money'],t('余额').'抵扣返回，订单号: '.$order['ordernum']);
                    }
                }
                //退款退还佣金
                } else {
                //部分退款
                Db::name('shop_order')->where('id', $order['id'])->where('aid', aid)->inc('refund_money', $data['refund_money'])->update(['refund_status' => 2]);
                //重新计算佣金
                $prolist = Db::name('shop_order_goods')->where('orderid', $order['id'])->where('aid', aid)->select();
                $reog = Db::name('shop_refund_order_goods')->where('refund_orderid', $refund_id)->select();
                \app\commons\Order::updateCommission($prolist, $reog);

                //判断当前订单是否全部退款 退款关闭
                $total_num = Db::name('shop_order_goods')->where('orderid', $order['id'])->where('aid', aid)->field("SUM(`num`) as total_num,SUM(`refund_num`) as total_refund_num")->find();
                if ($total_num['total_num'] == $total_num['total_refund_num']) {
                    Db::name('shop_order')->where('id', $order['id'])->where('aid', aid)->update(['status' => 4, 'refund_status' => 2]);
                    Db::name('shop_order_goods')->where('orderid', $order['id'])->where('aid', aid)->update(['status' => 4]);
                }
            }

            //		//积分抵扣的返还
            //		if($order['scoredkscore'] > 0){
            //			\app\commons\Member::addscore(aid,$order['mid'],$order['scoredkscore'],'订单退款返还');
            //		}
            //		//优惠券抵扣的返还
            //		if($order['coupon_rid'] > 0){
            //			Db::name('coupon_record')->where('aid',aid)->where(['mid'=>$order['mid'],'id'=>$order['coupon_rid']])->update(['status'=>0,'usetime'=>'']);
            //		}

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            return $this->json(['status'=>0,'msg'=>'提交失败,请重试']);
        }

        $refund_money = $data['refund_money'];
        //退款成功通知
        $tmplcontent = [];
        $tmplcontent['first'] = '您的订单已经完成退款，¥'.$refund_money.'已经退回您的付款账户，请留意查收。';
        $tmplcontent['remark'] = $reason.'，请点击查看详情~';
        $tmplcontent['orderProductPrice'] = $refund_money.'元';
        $tmplcontent['orderProductName'] = $order['title'];
        $tmplcontent['orderName'] = $order['ordernum'];
        $tmplcontentNew = [];
        $tmplcontentNew['character_string1'] = $order['ordernum'];//订单编号
        $tmplcontentNew['thing2'] = $order['title'];//商品名称
        $tmplcontentNew['amount3'] = $refund_money;//退款金额
        \app\commons\Wechat::sendtmpl(aid,$order['mid'],'tmpl_tuisuccess',$tmplcontent,m_url('pages/my/usercenter'),$tmplcontentNew);
        //订阅消息
        $tmplcontent = [];
        $tmplcontent['amount6'] = $refund_money;
        $tmplcontent['thing3'] = $order['title'];
        $tmplcontent['character_string2'] = $order['ordernum'];

        $tmplcontentnew = [];
        $tmplcontentnew['amount3'] = $refund_money;
        $tmplcontentnew['thing6'] = $order['title'];
        $tmplcontentnew['character_string4'] = $order['ordernum'];
        \app\commons\Wechat::sendwxtmpl(aid,$order['mid'],'tmpl_tuisuccess',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);

        //短信通知
        $member = Db::name('member')->where('id',$order['mid'])->find();
        if($member['tel']){
            $tel = $member['tel'];
        }else{
            $tel = $order['tel'];
        }
        $rs = \app\commons\Sms::send(aid,$tel,'tmpl_tuisuccess',['ordernum'=>$order['ordernum'],'money'=>$refund_money]);

        \app\commons\System::plog('手机端后台商城订单退款'.$orderid);
        return $this->json(['status'=>1,'msg'=>'已退款成功']);
    }

}