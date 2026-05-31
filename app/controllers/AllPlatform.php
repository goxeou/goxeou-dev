<?php

namespace app\controllers;
use app\commons\File;
use app\commons\Pic;
use think\facade\View;
use think\facade\Db;

class AllPlatform extends Common
{
	//首页框架
    public function index(){
		$menudata = \app\commons\Menu::getdata(aid,uid);
		$set = Db::name('admin_set')->where('aid',aid)->find();
		if(!$set){
			\app\commons\System::initaccount(aid);
			$set = Db::name('admin_set')->where('aid',aid)->find();
		}
		$webname = \app\commons\System::webname();

		$adminname = $this->user['un'];
		if(false){}else{
			if(bid > 0){
				$bname = Db::name('business')->where('id',bid)->value('name');
				$adminname = $bname . '('.$adminname.')';
			}else{
				$adminname = $set['name'] . '('.$adminname.')';
			}
		}
		$noticecount = 0 + Db::name('admin_notice')->where('aid',aid)->where('uid',uid)->where('isread',0)->count();
		View::assign('isadmin',$this->user['isadmin']);
		View::assign('adminname',$adminname);
		View::assign('menudata',$menudata);
		View::assign('noticecount',$noticecount);
		View::assign('webname',$webname);
        View::assign('set',$set);
		$webinfo = Db::name('sysset')->where('name','webinfo')->value('value');
		$webinfo = json_decode($webinfo,true);

		$socket_uid = '';
		$socket_mid = '';
		$socket_token = '';
		if($this->user['mid']){
			$socket_uid = $this->user['id'];
			$socket_mid = $this->user['mid'];
			$socket_token = Db::name('member')->where('id',$this->user['mid'])->value('random_str');
		}
		View::assign('webinfo',$webinfo);
		View::assign('socket_mid',$socket_mid);
		View::assign('socket_token',$socket_token);
		View::assign('socket_uid',$socket_uid);
		return View::fetch();
    }
	//欢迎页面 数据统计
	public function welcome(){
		if(session('IS_ADMIN')==0 && $this->user['showtj']==0){
			return View::fetch('welcome2');
		}else{
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
						$dataArr[] = 0 + Db::name('payorder')->where('aid',aid)->where('createtime','>=',$thisDayStart)->where('createtime','<',$thisDayEnd)->where('paytypeid','not in','1,4')->where('status',1)->sum('money');
					}elseif($_POST['type']==4){//订单金额
						$dataArr[] = 0 + Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('paytime','>=',$thisDayStart)->where('paytime','<',$thisDayEnd)->where('status','in','1,2,3')->sum('totalprice');
					}elseif($_POST['type']==5){//订单数
						$dataArr[] = 0 + Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('paytime','>=',$thisDayStart)->where('paytime','<',$thisDayEnd)->where('status','in','1,2,3')->count();
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
					$dataArr[] = 0 + Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('paytime','>=',$thisDayStart)->where('paytime','<',$thisDayEnd)->where('status','in','1,2,3')->sum('totalprice');
				}
			}

			$lastDayStart = strtotime(date('Y-m-d',time()-86400));
			$lastDayEnd = $lastDayStart + 86400;
			$thisMonthStart = strtotime(date('Y-m-1'));
			$nowtime = time();
			if(bid == 0){
				//客户数
				$memberCount = 0 + Db::name('member')->where('aid',aid)->count();
				$memberThisDayCount = 0 + Db::name('member')->where('aid',aid)->where('createtime','>=',$lastDayEnd)->count();
				$memberLastDayCount = 0 + Db::name('member')->where('aid',aid)->where('createtime','>=',$lastDayStart)->where('createtime','<',$lastDayEnd)->count();
				$memberThisMonthCount = 0 + Db::name('member')->where('aid',aid)->where('createtime','>=',$thisMonthStart)->where('createtime','<',$nowtime)->count();

				//收款金额
				$payCount = Db::name('payorder')->where('aid',aid)->where('paytypeid','not in','1,4')->where('status',1)->sum('money');
				$payThisDayCount = 0 + Db::name('payorder')->where('aid',aid)->where('paytypeid','not in','1,4')->where('status',1)->where('paytime','>=',$lastDayEnd)->sum('money');
				$payLastDayCount = 0 + Db::name('payorder')->where('aid',aid)->where('paytypeid','not in','1,4')->where('status',1)->where('paytime','>=',$lastDayStart)->where('paytime','<',$lastDayEnd)->sum('money');
				$payThisMonthCount = 0 + Db::name('payorder')->where('aid',aid)->where('paytypeid','not in','1,4')->where('status',1)->where('paytime','>=',$thisMonthStart)->where('paytime','<',$nowtime)->sum('money');

				//提现金额
				$withdrawCount = 0 + Db::name('member_withdrawlog')->where('aid',aid)->where('status',3)->sum('money') + Db::name('member_commission_withdrawlog')->where('aid',aid)->where('status',3)->sum('money');
				$withdrawThisDayCount = 0 + Db::name('member_withdrawlog')->where('aid',aid)->where('status',3)->where('createtime','>=',$lastDayEnd)->sum('money') + Db::name('member_commission_withdrawlog')->where('aid',aid)->where('status',3)->where('createtime','>=',$lastDayEnd)->sum('money');
				$withdrawLastDayCount = 0 + Db::name('member_withdrawlog')->where('aid',aid)->where('status',3)->where('createtime','>=',$lastDayStart)->where('createtime','<',$lastDayEnd)->sum('money') + Db::name('member_commission_withdrawlog')->where('aid',aid)->where('status',3)->where('createtime','>=',$lastDayStart)->where('createtime','<',$lastDayEnd)->sum('money');
				$withdrawThisMonthCount = 0 + Db::name('member_withdrawlog')->where('aid',aid)->where('status',3)->where('createtime','>=',$thisMonthStart)->where('createtime','<',$nowtime)->sum('money') + Db::name('member_commission_withdrawlog')->where('aid',aid)->where('status',3)->where('createtime','>=',$thisMonthStart)->where('createtime','<',$nowtime)->sum('money');
			}
			//商品数量
			$productCount = 0 + Db::name('shop_product')->where('aid',aid)->where('bid',bid)->count();
			$product0Count = 0 + Db::name('shop_product')->where('aid',aid)->where('bid',bid)->where('status',0)->count();
			$product1Count = 0 + Db::name('shop_product')->where('aid',aid)->where('bid',bid)->where('status',1)->count();

			//订单数
			$orderallCount = 0 + Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('status','in','1,2,3')->count();
			$orderallThisDayCount = 0 + Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('status','in','1,2,3')->where('paytime','>=',$lastDayEnd)->count();
			$orderallLastDayCount = 0 + Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('status','in','1,2,3')->where('paytime','>=',$lastDayStart)->where('paytime','<',$lastDayEnd)->count();
			$orderallThisMonthCount = 0 + Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('status','in','1,2,3')->where('paytime','>=',$thisMonthStart)->where('paytime','<',$nowtime)->count();

			//订单金额
			$orderMoneyCount = 0 + Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('status','in','1,2,3')->sum('totalprice');
			$orderMoneyThisDayCount = 0 + Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('paytime','>=',$lastDayEnd)->where('status','in','1,2,3')->sum('totalprice');
			$orderMoneyLastDayCount = 0 + Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('paytime','>=',$lastDayStart)->where('paytime','<',$lastDayEnd)->where('status','in','1,2,3')->sum('totalprice');
			$orderMoneyThisMonthCount = 0 + Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('paytime','>=',$thisMonthStart)->where('paytime','<',$nowtime)->where('status','in','1,2,3')->sum('totalprice');

			//退款金额
			$refundCount = 0 + Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('refund_status',2)->sum('refund_money');
			$refundThisDayCount = 0 + Db::name('shop_refund_order')->where('aid',aid)->where('bid',bid)->where('createtime','>=',$lastDayEnd)->where('refund_status',2)->sum('refund_money');
			$refundLastDayCount = 0 + Db::name('shop_refund_order')->where('aid',aid)->where('bid',bid)->where('createtime','>=',$lastDayStart)->where('createtime','<',$lastDayEnd)->where('refund_status',2)->sum('refund_money');
			$refundThisMonthCount = 0 + Db::name('shop_refund_order')->where('aid',aid)->where('bid',bid)->where('createtime','>=',$thisMonthStart)->where('createtime','<',$nowtime)->where('refund_status',2)->sum('refund_money');

			$mpinfo = [];
			$wxapp = [];
			if(in_array('wx',$this->platform)){//小程序
				$wxapp = \app\commons\System::appinfo(aid,'wx');
			}
			if(in_array('mp',$this->platform)){//公众号
				$mpinfo = \app\commons\System::appinfo(aid,'mp');
			}
			View::assign('mpinfo',$mpinfo);
			View::assign('wxapp',$wxapp);

			$set = Db::name('admin_set')->where('aid',aid)->find();
			View::assign('set',$set);

			$endtime = Db::name('admin')->where('id',aid)->value('endtime');
            if(bid > 0){
                $businessEndtime = Db::name('business')->where('aid',aid)->where('id',bid)->value('endtime');
                View::assign('businessStatus',time() > $businessEndtime ? 'expire' : 'normal');
                View::assign('businessEndtime',$businessEndtime);
            }

			View::assign('bid',bid);
			View::assign('endtime',$endtime);
			View::assign('memberCount',$memberCount);
			View::assign('memberThisDayCount',$memberThisDayCount);
			View::assign('memberLastDayCount',$memberLastDayCount);
			View::assign('memberThisMonthCount',$memberThisMonthCount);
//			View::assign('order3Count',$order3Count);
//			View::assign('order3LastDayCount',$order3LastDayCount);
//			View::assign('order3ThisMonthCount',$order3ThisMonthCount);
			View::assign('orderallCount',$orderallCount);
			View::assign('orderallThisDayCount',$orderallThisDayCount);
			View::assign('orderallLastDayCount',$orderallLastDayCount);
			View::assign('orderallThisMonthCount',$orderallThisMonthCount);
			View::assign('orderMoneyCount',$orderMoneyCount);
			View::assign('orderMoneyThisDayCount',$orderMoneyThisDayCount);
			View::assign('orderMoneyLastDayCount',$orderMoneyLastDayCount);
			View::assign('orderMoneyThisMonthCount',$orderMoneyThisMonthCount);
			View::assign('productCount',$productCount);
			View::assign('product0Count',$product0Count);
			View::assign('product1Count',$product1Count);
			View::assign('payCount',$payCount);
			View::assign('payThisDayCount',$payThisDayCount);
			View::assign('payLastDayCount',$payLastDayCount);
			View::assign('payThisMonthCount',$payThisMonthCount);
			View::assign('refundCount',$refundCount);
			View::assign('refundThisDayCount',$refundThisDayCount);
			View::assign('refundLastDayCount',$refundLastDayCount);
			View::assign('refundThisMonthCount',$refundThisMonthCount);
			View::assign('withdrawCount',$withdrawCount);
			View::assign('withdrawThisDayCount',$withdrawThisDayCount);
			View::assign('withdrawLastDayCount',$withdrawLastDayCount);
			View::assign('withdrawThisMonthCount',$withdrawThisMonthCount);
			View::assign('dateArr',$dateArr);
			View::assign('dataArr',$dataArr);
			return View::fetch();
		}
	}

	public function welcome1(){
		
		$monthEnd = strtotime(date('Y-m-d',time()-86400));
		$monthStart = $monthEnd - 86400 * 29;
		
		$lastDayStart = strtotime(date('Y-m-d',time()-86400));
		$lastDayEnd = $lastDayStart + 86400;
		$thisMonthStart = strtotime(date('Y-m-1'));
		$nowtime = time();

		$last7DayStart = $lastDayEnd - 86400 * 6;


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
					$dataArr[] = 0 + Db::name('payorder')->where('aid',aid)->where('createtime','>=',$thisDayStart)->where('createtime','<',$thisDayEnd)->where('paytypeid','not in','1,4')->where('status',1)->sum('money');
				}elseif($_POST['type']==4){//订单金额
					$dataArr[] = 0 + Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('paytime','>=',$thisDayStart)->where('paytime','<',$thisDayEnd)->where('status','in','1,2,3')->sum('totalprice');
				}elseif($_POST['type']==5){//订单数
					$dataArr[] = 0 + Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('paytime','>=',$thisDayStart)->where('paytime','<',$thisDayEnd)->where('status','in','1,2,3')->count();
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
				$dataArr[] = 0 + Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('paytime','>=',$thisDayStart)->where('paytime','<',$thisDayEnd)->where('status','in','1,2,3')->sum('totalprice');
			}
		}

		
		
		if(input('post.datetype') == 0 || !input('?post.datetype')){ //今日
			$starttime = $lastDayEnd;
			$endtime = time();
		}elseif(input('post.datetype') == 1){ //昨日
			$starttime = $lastDayStart;
			$endtime = $lastDayEnd;
		}elseif(input('post.datetype') == 7){ //七日
			$starttime = $last7DayStart;
			$endtime = time();
		}elseif(input('post.datetype') == 10){ //汇总
			$starttime = 0;
			$endtime = time();
		}elseif(input('post.datetype') == 11){ //选择时间
			$starttime = strtotime(input('post.starttime'));
			$endtime = strtotime(input('post.endtime')) + 86400;
		}

		if(input('post.op') == 'getordercount' || !input('?post.op')){
			$orderallCount = Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('status','in','0,1,2,3')->where('createtime','>=',$starttime)->where('createtime','<',$endtime)->count();
			$order0Count = Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('status','0')->where('createtime','>=',$starttime)->where('createtime','<',$endtime)->count();
			$order1Count = Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('status','1')->where('createtime','>=',$starttime)->where('createtime','<',$endtime)->count();
			$order3Count = Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('status','3')->where('createtime','>=',$starttime)->where('createtime','<',$endtime)->count();

			$orderallCount = number_format($orderallCount);
			$order0Count = number_format($order0Count);
			$order1Count = number_format($order1Count);
			$order3Count = number_format($order3Count);
			if(input('?post.op')){
				return json([
					'orderallCount'=>$orderallCount,
					'order0Count'=>$order0Count,
					'order1Count'=>$order1Count,
					'order3Count'=>$order3Count,
					'orderallCountName'=> (input('post.datetype') == 0) ? '今日订单数' : '订单数'
				]);
			}
		}

		if(input('post.op') == 'getpaynum' || !input('?post.op')){
			$paynumCount = Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('status','in','1,2,3')->where('paytime','>=',$starttime)->where('paytime','<',$endtime)->count();
			$paypersonCount = Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('status','in','1,2,3')->where('paytime','>=',$starttime)->where('paytime','<',$endtime)->group('mid')->count();
			$payproCount = Db::name('shop_order_goods')->where('aid',aid)->where('bid',bid)->where('status','in','1,2,3')->where('createtime','>=',$starttime)->where('createtime','<',$endtime)->sum('num');

			$paysumMoney = Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('status','in','1,2,3')->where('paytime','>=',$starttime)->where('paytime','<',$endtime)->where('paytypeid','1')->sum('totalprice');
			
			$paysumWeixin = Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('status','in','1,2,3')->where('paytime','>=',$starttime)->where('paytime','<',$endtime)->where('paytypeid','in','2,60')->sum('totalprice');
			$paysum = Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('status','in','1,2,3')->where('paytime','>=',$starttime)->where('paytime','<',$endtime)->sum('totalprice');

			$paynumCount = number_format($paynumCount);
			$paypersonCount = number_format($paypersonCount);
			$payproCount = number_format($payproCount);
			$paysumMoney = number_format($paysumMoney,2);
			$paysumWeixin = number_format($paysumWeixin,2);
			$paysum = number_format($paysum,2);
			if(input('?post.op')){
				return json([
					'paynumCount'=>$paynumCount,
					'paypersonCount'=>$paypersonCount,
					'payproCount'=>$payproCount,
					'paysumMoney'=>$paysumMoney,
					'paysumWeixin'=>$paysumWeixin,
					'paysum'=>$paysum,
				]);
			}
		}

		
		if(input('post.op') == 'getwithdrawsum' || !input('?post.op')){
			$withdrawSum = Db::name('member_withdrawlog')->where('aid',aid)->where('status',3)->where('createtime','>=',$starttime)->where('createtime','<',$endtime)->sum('money');
			$withdraw2Sum = Db::name('member_commission_withdrawlog')->where('aid',aid)->where('status',3)->where('createtime','>=',$starttime)->where('createtime','<',$endtime)->sum('money');
			$refundSum = Db::name('shop_refund_order')->where('aid',aid)->where('bid',bid)->where('createtime','>=',$starttime)->where('createtime','<',$endtime)->where('refund_status',2)->sum('refund_money');
			
			$withdrawSum = number_format($withdrawSum,2);
			$withdraw2Sum = number_format($withdraw2Sum,2);
			$refundSum = number_format($refundSum,2);
			if(input('?post.op')){
				return json([
					'withdrawSum'=>$withdrawSum,
					'withdraw2Sum'=>$withdraw2Sum,
					'refundSum'=>$refundSum,
				]);
			}
		}
		
		$productCount = 0 + Db::name('shop_product')->where('aid',aid)->where('bid',bid)->count();
		$productCount = number_format($productCount);

		View::assign('orderallCount',$orderallCount);
		View::assign('order0Count',$order0Count);
		View::assign('order1Count',$order1Count);
		View::assign('order3Count',$order3Count);
		View::assign('productCount',$productCount);
		View::assign('paynumCount',$paynumCount);
		View::assign('paypersonCount',$paypersonCount);
		View::assign('payproCount',$payproCount);
		View::assign('paysumMoney',$paysumMoney);
		View::assign('paysumWeixin',$paysumWeixin);
		View::assign('paysum',$paysum);
		
		View::assign('withdrawSum',$withdrawSum);
		View::assign('withdraw2Sum',$withdraw2Sum);
		View::assign('refundSum',$refundSum);

		View::assign('bid',bid);
		View::assign('endtime',$endtime);
		View::assign('dateArr',$dateArr);
		View::assign('dataArr',$dataArr);

		return View::fetch('welcome1');
	}
	//修改密码
	public function setpwd(){
		if(request()->isPost()){
			$rs = Db::name('admin_user')->where('id',$this->uid)->find();
			if($rs['pwd'] != md5(input('post.oldPassword'))){
				return json(['status'=>0,'msg'=>'当前密码输入错误']);
			}
			Db::name('admin_user')->where('id',$this->uid)->update(['pwd'=>md5(input('post.password'))]);
			\app\commons\System::plog('修改密码');
			return json(['status'=>1,'msg'=>'修改成功']);
		}
		return View::fetch();
	}
	//系统设置
	public function sysset(){
        $admin = Db::name('admin')->where('id',aid)->find();
        $iconurl = "upload/loading/icon_".aid.'.png';
		if(bid == 0){
			if(request()->isPost()){
				$rs = Db::name('admin_set')->where('aid',aid)->find();
				$info = input('post.info/a');
				$gzts = input('post.gzts/a');
				$gzts = implode(',',$gzts);
				$info['gzts'] = $gzts;
				$ddbb = input('post.ddbb/a');
				$ddbb = implode(',',$ddbb);
				$info['ddbb'] = $ddbb;
				$info['login_mast'] = implode(',',input('post.login_mast/a'));
                $info['location_menu_list'] = input('?param.location_menu_list') ? jsonEncode(input('param.location_menu_list/a')) : '';

				foreach($this->platform as $pl){
					$info['logintype_'.$pl] = implode(',',$info['logintype_'.$pl]);
				}
				$info['textset'] = jsonEncode(input('post.textset/a'));
				$info['gettj'] = implode(',',$info['gettj']);
                $info['invoice_type'] = implode(',',$info['invoice_type']);
                //loading图标
                if(empty($info['loading_icon'])){
                    $info['loading_icon'] = PRE_URL.'/static/img/loading/1.png';
                }

                if($rs['loading_icon']!=$info['loading_icon']){
                    @unlink(ROOT_PATH.$iconurl);
                    file_put_contents(ROOT_PATH.$iconurl,request_get($info['loading_icon']));
                    $info['loading_icon'] = PRE_URL.'/'.$iconurl;
                }

                if($info['latitude'] && $info['longitude']){
                    //通过坐标获取省市区
                    $address_component = \app\commons\Common::getAreaByLocation($info['longitude'],$info['latitude']);
                    if($address_component && $address_component['status']==1){
                        $info['province'] = $address_component['province'];
                        $info['city'] = $address_component['city'];
                        $info['district'] = $address_component['district'];
                    }
                }
                if($rs){
					Db::name('admin_set')->where('aid',aid)->update($info);
				}else{
					$info['aid'] = aid;
					Db::name('admin_set')->insert($info);
				}
				$xyinfo = input('post.xyinfo/a');
				$rs = Db::name('admin_set_xieyi')->where('aid',aid)->find();
				if($rs){
					Db::name('admin_set_xieyi')->where('aid',aid)->update($xyinfo);
				}else{
					$xyinfo['aid'] = aid;
					Db::name('admin_set_xieyi')->insert($xyinfo);
				}
		

				$remote = jsonEncode(input('post.rinfo/a'));
				Db::name('admin')->where('id',aid)->update(['remote'=>$remote]);

				if($info['reg_invite_code'] != 0 && $info['reg_invite_code_type']==1){
					$memberlist = Db::name('member')->where('aid',aid)->where("yqcode='' or yqcode is null")->select()->toArray();
					foreach($memberlist as $member){
						$yqcode = \app\models\Member::getyqcode(aid);
						Db::name('member')->where('id',$member['id'])->update(['yqcode'=>$yqcode]);
					}
				}
				\app\commons\System::plog('系统设置');
				return json(['status'=>1,'msg'=>'设置成功','url'=>true]);
			}
			$info = Db::name('admin_set')->where('aid',aid)->find();
			if(!$info){
				\app\commons\System::initaccount(aid);
				$info = Db::name('admin_set')->where('aid',aid)->find();
			}
            //loading图标处理
            if(empty($info['loading_icon'])){
                //追加loading图标
                $defaultIcon = ROOT_PATH."static/img/loading/1.png";
                $iconpath = ROOT_PATH.$iconurl;
                if(!file_exists($iconpath)){
                    File::all_copy($defaultIcon,$iconpath);
                    $info['loading_icon'] = PRE_URL.'/'.$iconurl;
                }else{
                    $info['loading_icon'] = PRE_URL.'/'.$iconurl;
                }
            }
            $info['loading_pics'] = [
                PRE_URL.'/static/img/loading/1.png',
                PRE_URL.'/static/img/loading/2.png',
                PRE_URL.'/static/img/loading/3.png',
                PRE_URL.'/static/img/loading/4.png',
                PRE_URL.'/static/img/loading/5.png',
            ];
            if(empty($info['location_menu_list'])){
                $location_menu_list = [
                    ["isshow"=>"1","url"=>"/pages/shop/cart","icon"=>PRE_URL.'/static/img/cart_64.png'],
                    ["isshow"=>"1","url"=>"/pages/kefu/index","icon"=>PRE_URL.'/static/img/message_64.png'],
                ];
                Db::name('admin_set')->where('aid',aid)->update(['location_menu_list'=>jsonEncode($location_menu_list)]);
            }else{
                $location_menu_list = json_decode($info['location_menu_list'],true);
            }
            $info['location_menu_list'] = $location_menu_list;

			foreach($this->platform as $pl){
				$info['logintype_'.$pl] = explode(',',$info['logintype_'.$pl]);
			}
			$textset = json_decode($info['textset'], true);
			$oldtextset = ['余额'=>'余额','积分'=>'积分','佣金'=>'佣金','优惠券'=>'优惠券','会员'=>'会员'];
            $newtextset = ['团队分红'=>'团队分红','股东分红'=>'股东分红','区域代理分红'=>'区域代理分红'];
            if(!$textset) {
                $textset = array_merge($oldtextset,$newtextset);
            } else {
			    if(array_keys($textset) == array_keys($oldtextset)) {
                    $textset = array_merge($textset, $newtextset);
                }
                }
			if(!$textset['我的团队']){
				$textset['我的团队']='我的团队';
			}
			if(!$textset['分销订单']){
				$textset['分销订单']='分销订单'; 
			}
			if(!$textset['自定义表单']){
				$textset['自定义表单']='自定义表单';
			}
			if(!$textset['推荐人']){
				$textset['推荐人']='推荐人';
			}
			$info['gettj'] = explode(',',$info['gettj']);
            $info['invoice_type'] = explode(',',$info['invoice_type']);
            $default_cid = Db::name('member_level_category')->where('aid',aid)->where('isdefault', 1)->value('id');
            $default_cid = $default_cid ? $default_cid : 0;
            $levellist = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->order('sort,id')->select()->toArray();
			View::assign('levellist',$levellist);

			$ainfo = Db::name('admin')->where('id',aid)->find();

			$xyinfo = Db::name('admin_set_xieyi')->where('aid',aid)->find();


			if($this->auth_data == 'all' || in_array('partner_jiaquan',$this->auth_data)){
				$partner_jiaquan = true;
			}else{
				$partner_jiaquan = false;
			}
			if($this->auth_data == 'all' || in_array('partner_gongxian',$this->auth_data)){
				$partner_gongxian = true;
			}else{
				$partner_gongxian = false;
			}
			
			View::assign('isadmin',$this->user['isadmin']);
			View::assign('info',$info);
            View::assign('admin',$admin);
            View::assign('ainfo',$ainfo);
			View::assign('textset',$textset);
			View::assign('xyinfo',$xyinfo);
			View::assign('rinfo',json_decode($ainfo['remote'],true));
			View::assign('partner_jiaquan',$partner_jiaquan);
			View::assign('partner_gongxian',$partner_gongxian);
			return View::fetch();
		}
		else{
			$bset    = Db::name('business_sysset')->where('aid',aid)->find();
			$oldinfo = Db::name('business')->where('aid',aid)->where('id',bid)->find();
			if(request()->isPost()){
				$postinfo = input('post.info/a');
				$info = [];

                if($postinfo['latitude'] && $postinfo['longitude']){
                    //通过坐标获取省市区
                    $address_component = \app\commons\Common::getAreaByLocation($postinfo['longitude'],$postinfo['latitude']);
                    if($address_component && $address_component['status']==1){
                        $info['province'] = $address_component['province'];
                        $info['city'] = $address_component['city'];
                        $info['district'] = $address_component['district'];
                    }
                }
				$info['tel'] = $postinfo['tel'];
				$info['logo'] = $postinfo['logo'];
				$info['pics'] = $postinfo['pics'];
				$info['content'] = $postinfo['content'];
				$info['address'] = $postinfo['address'];
				$info['longitude'] = $postinfo['longitude'];
				$info['latitude'] = $postinfo['latitude'];
				$info['weixin'] = $postinfo['weixin'];
				$info['aliaccount'] = $postinfo['aliaccount'];
				$info['bankname'] = $postinfo['bankname'];
				$info['bankcarduser'] = $postinfo['bankcarduser'];
				$info['bankcardnum'] = $postinfo['bankcardnum'];
				$info['start_hours'] = $postinfo['start_hours'];
				$info['end_hours'] = $postinfo['end_hours'];
                $info['end_buy_status'] = $postinfo['end_buy_status'];
                $info['invoice'] = $postinfo['invoice'];
                $info['invoice_type'] = implode(',',$postinfo['invoice_type']);
                $info['is_open'] = $postinfo['is_open'];
                $info['autocollecthour'] = $postinfo['autocollecthour'];
				$info['start_hours2'] = $postinfo['start_hours2'];
				$info['end_hours2'] = $postinfo['end_hours2'];
				$info['start_hours3'] = $postinfo['start_hours3'];
				$info['end_hours3'] = $postinfo['end_hours3'];
				$info['kfurl'] = $postinfo['kfurl'];
				db('business')->where(['aid'=>aid,'id'=>bid])->update($info);
				\app\commons\System::plog('系统设置');
				return json(['status'=>1,'msg'=>'设置成功','url'=>true]);
			}
			$info = $oldinfo;
            $info['invoice_type'] = explode(',',$info['invoice_type']);
			View::assign('info',$info);
			View::assign('bset',$bset);
            View::assign('admin',$admin);
			return View::fetch('syssetb');
		}
	}
	//操作日志
    public function plog(){
		if(input('param.op') == 'del'){
			$ids = input('post.ids/a');
			Db::name('plog')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->delete();
			return json(['status'=>1,'msg'=>'删除成功']);
		}
		$userArr = db('admin_user')->where('aid',aid)->where('bid',bid)->column('un','id');
		//dump($userArr);
		if(request()->isAjax()){
			$page = input('get.page');
			$limit = input('get.limit');
			if(input('get.field') && input('get.order')){
				$order = input('get.field').' '.input('get.order');
			}else{
				$order = 'id desc';
			}
			$where = [];
			$where[] = ['aid','=',aid];
			$where[] = ['bid','=',bid];
			if($this->user['isadmin'] > 0){
				if(input('get.uid')) $where[] = ['uid','=',input('get.uid')];
			}else{
				$where[] = ['uid','=',uid];
			}
			if(input('get.remark')) $where[] = ['remark','like','%'.input('get.remark').'%'];
			$count = 0 + Db::name('plog')->where($where)->count();
			$data = Db::name('plog')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			foreach($data as $k=>$v){
				$data[$k]['un'] = $userArr[$v['uid']];
			}
			return ['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data];
		}
		if($this->user['isadmin'] > 0){
			View::assign('userArr',$userArr);
		}
		return View::fetch();
    }

	//保持连接
	public function linked(){
		return json(['status'=>1]);
	}
	public function test(){
		var_dump(t('会员'));
	}
	public function imgsearch(){
        }
    public function diylight(){
        }

    public function huidong()
    {
        $channel = 'huidong';
        if(request()->isPost()){
            $set = input('post.set/a');
            $post = input('post.');

            if($post['op'] == 'reset'){
                $update['appsecret'] = md5(rand(1,9999).uniqid());
                Db::name('open_app')->where('aid',aid)->where('channel',$channel)->update($update);
                return json(['status'=>1,'msg'=>'重置成功','url'=>true]);
            }

            $set = Db::name('admin_set')->where('aid',aid)->update(['huidong_status'=>$set['huidong_status'],'huidong_url'=>$set['huidong_url']]);

            \app\commons\System::plog('慧动企微设置');
            return json(['status'=>1,'msg'=>'设置成功','url'=>true]);
        }
        $info = Db::name('open_app')->where('aid',aid)->where('channel',$channel)->find();
        if(empty($info)){
            $appid = 'dianda'.rand(11,99).make_rand_code(5,10);
            if(Db::name('open_app')->where('appid',$appid)->count())
                $appid = 'dianda'.rand(11,99).make_rand_code(5,10);
            $info = [
                'aid'=>aid,
                'channel' => $channel,
                'appid' => $appid,
                'appsecret' => md5(rand(1,9999).uniqid()),
                'createtime'=>time()
            ];
            Db::name('open_app')->insert($info);
        }
        View::assign('domain',PRE_URL);
        View::assign('info',$info);
        $set = Db::name('admin_set')->where('aid',aid)->find();
        View::assign('set',$set);
        return View::fetch();
    }
}
