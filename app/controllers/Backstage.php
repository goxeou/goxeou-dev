<?php


// +----------------------------------------------------------------------
// | 首页
// +----------------------------------------------------------------------
namespace app\controllers;
use app\commons\File;
use app\commons\Pic;
use PhpOffice\PhpSpreadsheet\Worksheet\AutoFilter\Column;
use think\facade\View;
use think\facade\Db;

class Backstage extends Common
{
   
	//首页框架
    public function index(){
		$menudata = \app\commons\Menu::getdata(aid,uid,true);
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
		$auth_data = $this->auth_data;

		$socket_uid = '';
		$socket_mid = '';
		$socket_token = '';
		if($this->user['mid']){
			$socket_uid = $this->user['id'];
			$socket_mid = $this->user['mid'];
			$socket_token = Db::name('member')->where('id',$this->user['mid'])->value('random_str');
		}
	
		$showlinks = true;
		if(getcustom('kecheng_lecturer')){
            //课程讲师前端链接需要验证设计权限
            if($this->user['lecturerid']){
                if($this->auth_data != 'all'){
                    $auth_data = json_decode($this->user['auth_data'],true);
                    $auth_path = [];
                    foreach($auth_data as $v){
                        $auth_path[] = explode(',',$v);
                    }
                    if(!in_array('DesignerPage/*',$auth_path) && !in_array('DesignerPage/chooseurl',$auth_path)){
                        $showlinks = false;
                        $socket_token = '';
                    }
                } 
            }
        }
        View::assign('showlinks',$showlinks);
		View::assign('webinfo',$webinfo);
		View::assign('socket_mid',$socket_mid);
		View::assign('socket_token',$socket_token);
		View::assign('socket_uid',$socket_uid);
		return View::fetch();
    }
	//欢迎页面 数据统计
	public function welcomeOld(){
		if(session('IS_ADMIN')==0 && $this->user['showtj']==0){
			return View::fetch('welcome2');
		}else{
			$monthEnd = strtotime(date('Y-m-d',time()-86400));
			$monthStart = $monthEnd - 86400 * 29;
			//订单限制门店
			if($this->mdid){
				$where1 = [];
				$where1[] = ['mdid','=',$this->mdid];
			}else{
				$where1 = '1=1';
			}
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
						$dataArr[] = 0 + Db::name('payorder')->where('aid',aid)->where('createtime','>=',$thisDayStart)->where('createtime','<',$thisDayEnd)->where('paytypeid','not in','1,4')->where('type','<>','daifu')->where('status',1)->sum('money');
					}elseif($_POST['type']==4){//订单金额
						$dataArr[] = 0 + Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('paytime','>=',$thisDayStart)->where('paytime','<',$thisDayEnd)->where('status','in','1,2,3')->where($where1)->sum('totalprice');
					}elseif($_POST['type']==5){//订单数
						$dataArr[] = 0 + Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('paytime','>=',$thisDayStart)->where('paytime','<',$thisDayEnd)->where('status','in','1,2,3')->where($where1)->count();
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
					$dataArr[] = 0 + Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('paytime','>=',$thisDayStart)->where('paytime','<',$thisDayEnd)->where('status','in','1,2,3')->where($where1)->sum('totalprice');
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
				$payCount = Db::name('payorder')->where('aid',aid)->where('paytypeid','not in','1,4')->where('type','<>','daifu')->where('status',1)->sum('money');
				$payThisDayCount = 0 + Db::name('payorder')->where('aid',aid)->where('paytypeid','not in','1,4')->where('type','<>','daifu')->where('status',1)->where('paytime','>=',$lastDayEnd)->sum('money');
				$payLastDayCount = 0 + Db::name('payorder')->where('aid',aid)->where('paytypeid','not in','1,4')->where('type','<>','daifu')->where('status',1)->where('paytime','>=',$lastDayStart)->where('paytime','<',$lastDayEnd)->sum('money');
				$payThisMonthCount = 0 + Db::name('payorder')->where('aid',aid)->where('paytypeid','not in','1,4')->where('type','<>','daifu')->where('status',1)->where('paytime','>=',$thisMonthStart)->where('paytime','<',$nowtime)->sum('money');

				//提现金额
				$withdrawCount = 0 + Db::name('member_withdrawlog')->where('aid',aid)->where('status',3)->sum('money') + Db::name('member_commission_withdrawlog')->where('aid',aid)->where('status',3)->sum('money');
				$withdrawThisDayCount = 0 + Db::name('member_withdrawlog')->where('aid',aid)->where('status',3)->where('createtime','>=',$lastDayEnd)->sum('money') + Db::name('member_commission_withdrawlog')->where('aid',aid)->where('status',3)->where('createtime','>=',$lastDayEnd)->sum('money');
				$withdrawLastDayCount = 0 + Db::name('member_withdrawlog')->where('aid',aid)->where('status',3)->where('createtime','>=',$lastDayStart)->where('createtime','<',$lastDayEnd)->sum('money') + Db::name('member_commission_withdrawlog')->where('aid',aid)->where('status',3)->where('createtime','>=',$lastDayStart)->where('createtime','<',$lastDayEnd)->sum('money');
				$withdrawThisMonthCount = 0 + Db::name('member_withdrawlog')->where('aid',aid)->where('status',3)->where('createtime','>=',$thisMonthStart)->where('createtime','<',$nowtime)->sum('money') + Db::name('member_commission_withdrawlog')->where('aid',aid)->where('status',3)->where('createtime','>=',$thisMonthStart)->where('createtime','<',$nowtime)->sum('money');
			}
			//商品数量
			$productCount = 0 + Db::name('shop_product')->where('aid',aid)->where('bid',bid)->count();

            $nowtime = time();
            $nowhm = date('H:i');
            $where = [];
            $where[] = ['aid','=',aid];
            $where[] = ['bid','=',bid];
            $where[] = Db::raw("`status`=0 or (`status`=2 and (unix_timestamp(start_time)>$nowtime or unix_timestamp(end_time)<$nowtime)) or (`status`=3 and ((start_hours<end_hours and (start_hours>'$nowhm' or end_hours<'$nowhm')) or (start_hours>=end_hours and (start_hours>'$nowhm' and end_hours<'$nowhm'))) )");
            $product0Count = 0 + Db::name('shop_product')->where($where)->count();
            $where = [];
            $where[] = ['aid','=',aid];
            $where[] = ['bid','=',bid];
            $where[] = Db::raw("`status`=1 or (`status`=2 and unix_timestamp(start_time)<=$nowtime and unix_timestamp(end_time)>=$nowtime) or (`status`=3 and ((start_hours<end_hours and start_hours<='$nowhm' and end_hours>='$nowhm') or (start_hours>=end_hours and (start_hours<='$nowhm' or end_hours>='$nowhm'))) )");
            $product1Count = 0 + Db::name('shop_product')->where($where)->count();

			//订单数
			$orderallCount = 0 + Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('status','in','1,2,3')->where($where1)->count();
			$orderallThisDayCount = 0 + Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('status','in','1,2,3')->where('paytime','>=',$lastDayEnd)->where($where1)->count();
			$orderallLastDayCount = 0 + Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('status','in','1,2,3')->where('paytime','>=',$lastDayStart)->where('paytime','<',$lastDayEnd)->where($where1)->count();
			$orderallThisMonthCount = 0 + Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('status','in','1,2,3')->where('paytime','>=',$thisMonthStart)->where('paytime','<',$nowtime)->where($where1)->count();

			//订单金额
			$orderMoneyCount = 0 + Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('status','in','1,2,3')->where($where1)->sum('totalprice');
			$orderMoneyThisDayCount = 0 + Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('paytime','>=',$lastDayEnd)->where('status','in','1,2,3')->where($where1)->sum('totalprice');
			$orderMoneyLastDayCount = 0 + Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('paytime','>=',$lastDayStart)->where('paytime','<',$lastDayEnd)->where('status','in','1,2,3')->where($where1)->sum('totalprice');
			$orderMoneyThisMonthCount = 0 + Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('paytime','>=',$thisMonthStart)->where('paytime','<',$nowtime)->where('status','in','1,2,3')->where($where1)->sum('totalprice');

			//退款金额
			$refundCount = 0 + Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('refund_status',2)->where($where1)->sum('refund_money');
			$refundThisDayCount = 0 + Db::name('shop_refund_order')->where('aid',aid)->where('bid',bid)->where('createtime','>=',$lastDayEnd)->where('refund_status',2)->where($where1)->sum('refund_money');
			$refundLastDayCount = 0 + Db::name('shop_refund_order')->where('aid',aid)->where('bid',bid)->where('createtime','>=',$lastDayStart)->where('createtime','<',$lastDayEnd)->where('refund_status',2)->where($where1)->sum('refund_money');
			$refundThisMonthCount = 0 + Db::name('shop_refund_order')->where('aid',aid)->where('bid',bid)->where('createtime','>=',$thisMonthStart)->where('createtime','<',$nowtime)->where('refund_status',2)->where($where1)->sum('refund_money');

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

            if(bid == 0){
                $admin = Db::name('admin')->where('id',aid)->find();
                $endtime = $admin['endtime'];
                }
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

            //默认全部显示
            $pc_index_data = ['all'];
            View::assign('pc_index_data', $pc_index_data);
			return View::fetch();
        }
	}
	public function welcome(){
		if(session('IS_ADMIN')==0 && $this->user['showtj']==0){
			return View::fetch('welcome2');
		}
		$admin = Db::name('admin')->where('id',aid)->find();
		$platform = explode(',',$admin['platform']);
		$monthEnd = strtotime(date('Y-m-d',time()-86400));
		$monthStart = $monthEnd - 86400 * 29;
		//订单限制门店
		if($this->mdid){
			$where1 = [];
			$where1[] = ['mdid','=',$this->mdid];
		}else{
			$where1 = '1=1';
		}
		//运营数据概览
		if(input('post.op') == 'getOperateData'){
			$day = input('post.day');
			if(!$day){
				$day = 1;
			}
			$dayEnd = strtotime(date('Y-m-d 23:59:59',time()));
			$dayStart = $dayEnd - 86400 * $day;
			if($day == 2){
				$dayEnd = $dayEnd - 86400;
			}
			
			if($day == 99){
				$dayStart = strtotime(date('Y-01-01 00:00:00',time()));
				$dayEnd = strtotime(date('Y-m-d 23:59:59',time()));
			}
			
			//收款金额			
			$payMoneyDayCount = 0 + Db::name('payorder')->where('aid',aid)->where('paytypeid','not in','1,4')->where('type','<>','daifu')->where('status',1)->where('paytime','>=',$dayStart)->where('paytime','<=',$dayEnd)->sum('money');
			$payMoneyDayCount = round($payMoneyDayCount,2);
			
			
				//收款金额			
			$maidanMoneyDayCount = 0 + Db::name('maidan_order')->where('aid',aid)->where('bid',bid)->where('status',1)->where('paytime','>=',$dayStart)->where('paytime','<=',$dayEnd)->sum('money');
			$maidanMoneyDayCount = round($maidanMoneyDayCount,2);
			
			
				//收款金额			
			$cashierMoneyDayCount = 0 + Db::name('cashier_order')->where('aid',aid)->where('bid',bid)->where('status','in','1,2,3')->where('paytime','>=',$dayStart)->where('paytime','<=',$dayEnd)->sum('totalprice');
			$cashierMoneyDayCount = round($cashierMoneyDayCount,2);
			
			
			
			//订单金额
			$orderMoneyDayCount = 0 + Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('createtime','>=',$dayStart)->where('createtime','<',$dayEnd)->where('status','in','1,2,3')->where($where1)->sum('totalprice');
			$orderMoneyDayCount = round($orderMoneyDayCount,2);
			//订单数
			$orderDayCount = 0 + Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('createtime','>=',$dayStart)->where('createtime','<',$dayEnd)->where('status','in','1,2,3')->where($where1)->count();

			//退款金额
			//$refundMoneyDayCount = 0 + Db::name('shop_refund_order')->where('aid',aid)->where('bid',bid)->where('createtime','>=',$dayStart)->where('createtime','<',$dayEnd)->where('refund_status',2)->where($where1)->sum('refund_money');
			$refundMoneyDayCount = 0 + Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('createtime','>=',$dayStart)->where('createtime','<',$dayEnd)->where('refund_status',2)->where($where1)->sum('refund_money');
			$refundMoneyDayCount = round($refundMoneyDayCount,2);
			//退款数量
			$refundDayCount = 0 + Db::name('shop_refund_order')->where('aid',aid)->where('bid',bid)->where('createtime','>=',$dayStart)->where('createtime','<',$dayEnd)->where('refund_status',2)->where($where1)->count();
			//订单待发货数量
			$orderNoFahuoDayCount = 0 + Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('createtime','>=',$dayStart)->where('createtime','<',$dayEnd)->where('status','=','1')->where($where1)->count();
			//订单待售后（退款）
			$orderShouhouDayCount = 0 + Db::name('shop_refund_order')->where('aid',aid)->where('bid',bid)->where('createtime','>=',$dayStart)->where('createtime','<',$dayEnd)->where('refund_status','in','1,4')->where($where1)->count();
			//订单待支付
			$orderNoPayDayCount = 0 + Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('createtime','>=',$dayStart)->where('createtime','<',$dayEnd)->where('status','=','0')->where($where1)->count();
			$rdata = [];
			$rdata = [
				'payMoneyDayCount'=>$payMoneyDayCount,
				'maidanMoneyDayCount'=>$maidanMoneyDayCount,
				'cashierMoneyDayCount'=>$cashierMoneyDayCount,
				
				
				
				'orderMoneyDayCount'=>$orderMoneyDayCount,
				'orderDayCount'=>$orderDayCount,
				'refundMoneyDayCount'=>$refundMoneyDayCount,
				'refundDayCount'=>$refundDayCount,
				'orderNoFahuoDayCount'=>$orderNoFahuoDayCount,
				'orderShouhouDayCount'=>$orderShouhouDayCount,
				'orderNoPayDayCount'=>$orderNoPayDayCount,

			];
			return json($rdata);
		}

		//热卖商品
		if(input('post.op') == 'getgoodssalesmoney'){
			$day = input('post.day');
			if(!$day){
				$day = 1;
			}
			$dayEnd = strtotime(date('Y-m-d 23:59:59',time()));
			$dayStart = $dayEnd - 86400 * $day;
			if($day == 365){
				$dayStart = strtotime(date('Y-01-01',time()));
			}
			if(input('param.cid') && input('param.cid')!==''){
				//取出cid 在的商品
				$cid = input('param.cid');
				if(bid == 0){
					$shop_product_cid = "shop_product.cid";
				}else{
					$shop_product_cid = "shop_product.cid2";
				}
				//子分类
				$where_cid = '';
				$clist = Db::name('shop_category')->where('aid',aid)->where('pid',$cid)->column('id');
				if($clist){
					$clist2 = Db::name('shop_category')->where('aid',aid)->where('pid','in',$clist)->column('id');
					$cCate = array_merge($clist, $clist2, [$cid]);
					if($cCate){
						$whereCid = [];
						foreach($cCate as $k => $c2){
							$whereCid[] = "find_in_set({$c2},{$shop_product_cid})";
						}
						$where_cid = Db::raw(implode(' or ',$whereCid));
					}
				} else {
					$where_cid = Db::raw("find_in_set(".$cid.",{$shop_product_cid})");
				}
			}
			$where = [];
			$where[] = ['og.aid','=',aid];
			$where[] = ['og.bid','=',bid];
			$where[] = ['og.status','in','1,2,3'];
			$fields = 'og.proid,og.name,og.pic,sum(og.num) num,sum(og.totalprice) totalprice,og.procode';
			$where[] = ['shop_order.createtime','>=',$dayStart];
			$where[] = ['shop_order.createtime','<',$dayEnd];
			if($where_cid !=''){
				$where[] = $where_cid;
			}
			$shop_goods_order = Db::name('shop_order_goods')->alias('og')->leftjoin('shop_order','shop_order.id=og.orderid')->leftjoin('shop_product','shop_product.id=og.proid')->fieldRaw($fields)->where($where)->group('proid')->limit(10)->order('num desc')->select()->toArray();
			//echo Db::getLastSql();exit;
			return json(['goodsdata'=>$shop_goods_order]);
		}
		//数据趋势图 day=1 本月 2上月 3前月
		if(input('post.op') == 'getDataChart'){
			$day = input('post.day');
			$days = 30;
			if(!$day){
				$day = 1;
			}
			$monthEnd = strtotime(date('Y-m-01 00:00:00',time()));
			$days = date('t',$monthEnd-86400*2);//上个月多少天
			$monthStart = $monthEnd - 86400*$days;
			//本月
			if($day == 1){
				$days = date('t',time());
				$monthEnd = time();
            	$monthStart = strtotime(date('Y-m-01 00:00:00',time()));
			}
			//前月
			if($day == 3){
				$monthEnd = $monthStart;
				$days = date('t',$monthEnd-86400*2);//上个月多少天
				$monthStart = $monthEnd - 86400*$days;
			}
			//优化查询数据
			if(bid == 0){
				$payorder_group = Db::name('payorder')->field("id,FROM_UNIXTIME(createtime,'%Y-%m-%d') as day,sum(money) AS totalmoney")->where('aid',aid)->where('createtime','>=',$monthStart)->where('createtime','<',$monthEnd)->where('paytypeid','not in','1,4')->where('type','<>','daifu')->where('status',1)->group('day')->select()->toArray();
				$payorder_group = array_column($payorder_group,'totalmoney','day');
				$membernum_group = Db::name('member')->field("id,FROM_UNIXTIME(createtime,'%Y-%m-%d') as day,count(id) AS totalnum")->where('aid',aid)->where('createtime','>=',$monthStart)->where('createtime','<',$monthEnd)->group('day')->select()->toArray();
				$membernum_group = array_column($membernum_group,'totalnum','day');

				$memebr_total_start = 0 + Db::name('member')->where('aid',aid)->where('createtime','<',$monthStart)->count();
			}
			$ordermoney_group = Db::name('shop_order')->field("id,FROM_UNIXTIME(createtime,'%Y-%m-%d') as day,sum(totalprice) AS totalmoney")->where('aid',aid)->where('bid',bid)->where('createtime','>=',$monthStart)->where('createtime','<',$monthEnd)->where('status','in','1,2,3')->where($where1)->group('day')->select()->toArray();
			$ordermoney_group = array_column($ordermoney_group,'totalmoney','day');

			$ordernum_group = Db::name('shop_order')->field("id,FROM_UNIXTIME(createtime,'%Y-%m-%d') as day,count(id) AS totalnum")->where('aid',aid)->where('bid',bid)->where('createtime','>=',$monthStart)->where('createtime','<',$monthEnd)->where('status','in','1,2,3')->where($where1)->group('day')->select()->toArray();
			$ordernum_group = array_column($ordernum_group,'totalnum','day');

			

			$dateArr = array();
			for($i=0;$i<$days;$i++){
				$thisDayStart = $monthStart + $i * 86400;					
				$thisDayEnd = $monthStart + ($i+1) * 86400;
				$dateArr[] = date('m-d',$thisDayStart);
				$thisday = date('Y-m-d',$thisDayStart);
				
				if(bid == 0){
					//$memberChartNum[] = 0 + Db::name('member')->where('aid',aid)->where('createtime','<',$thisDayEnd)->count();

					$day_member_num = $membernum_group[$thisday]??0;
					$memebr_total_start += $day_member_num;
					$memberChartNum[] = $memebr_total_start;
					//$memberChartAddNum[] = 0 + Db::name('member')->where('aid',aid)->where('createtime','>=',$thisDayStart)->where('createtime','<',$thisDayEnd)->count();
					$memberChartAddNum[] = 0 + $day_member_num;

					//$payChartMoney[] = round(0 + Db::name('payorder')->where('aid',aid)->where('createtime','>=',$thisDayStart)->where('createtime','<',$thisDayEnd)->where('paytypeid','not in','1,4')->where('type','<>','daifu')->where('status',1)->sum('money'),2);
					$payChartMoney[] = round(0 + $payorder_group[$thisday]??0,2);
				}
					//$orderChartMoney[] = round(0 + Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('paytime','>=',$thisDayStart)->where('paytime','<',$thisDayEnd)->where('status','in','1,2,3')->where($where1)->sum('totalprice'),2);
					$orderChartMoney[] = round(0 + $ordermoney_group[$thisday]??0);

					// $orderChartNum[] = 0 + Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('paytime','>=',$thisDayStart)->where('paytime','<',$thisDayEnd)->where('status','in','1,2,3')->where($where1)->count();
					$orderChartNum[] = 0 + $ordernum_group[$thisday]??0;
				
			}
			$title=[];
			$series = [];
			if(bid == 0){
				$title[] = '收款金额';
				$series[] = [
					'name'=>'收款金额',
					'type'=>'line',
					'smooth'=>'true',
					'itemStyle'=>['color'=>'#fac858'],
					'data'=>$payChartMoney,
				];
			}
			$title[] = '订单金额';
			$series[] = [
				'name'=>'订单金额',
				'type'=>'line',
				'smooth'=>'true',
				'itemStyle'=>['color'=>'#ee6666'],
				'data'=>$orderChartMoney,
			];
			$title[] = '订单数量';
			$series[] = [
				'name'=>'订单数量',
				'type'=>'line',
				'smooth'=>'true',
				'itemStyle'=>['color'=>'#66ccff'],
				'data'=>$orderChartNum,
			];
			if(bid == 0){
				$title[] = '会员数量';
				$series[] = [
					'name'=>'会员数量',
					'type'=>'line',
					'smooth'=>'true',
					'itemStyle'=>['color'=>'#5470c6'],
					'data'=>$memberChartNum,
				];
				$title[] = '新增会员数量';
				$series[] = [
					'name'=>'新增会员数量',
					'type'=>'line',
					'smooth'=>'true',
					'itemStyle'=>['color'=>'#99ff66'],
					'data'=>$memberChartAddNum,
				];
			}
			
			return json(['dateArr'=>$dateArr,'series'=>$series,'title'=>$title]);


		}
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
		if(bid == 0){
			$admin = Db::name('admin')->where('id',aid)->find();
			$endtime = $admin['endtime'];
			}
		if(bid > 0){
			$business = Db::name('business')->where('aid',aid)->where('id',bid)->find();
			View::assign('business',$business);
			$endtime = $business['endtime'];
		}
		View::assign('bid',bid);
		View::assign('endtime',$endtime);

		//本月订单统计
		$monthEnd = time();
		$monthStart = strtotime(date('Y-m-1',time()));
		//总订单数
		$orderMonthCountAll = 0 + Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('createtime','>=',$monthStart)->where('createtime','<',$monthEnd)->where($where1)->count();
		//本月待支付
		$orderNoZhifuMonthCount = 0 + Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('createtime','>=',$monthStart)->where('createtime','<',$monthEnd)->where('status','=','0')->where($where1)->count();
		//本月待发货
		$orderNoFahuoMonthCount = 0 + Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('createtime','>=',$monthStart)->where('createtime','<',$monthEnd)->where('status','=','1')->where($where1)->count();
		//发货中运输中
		$orderFahuoMonthCount = 0 + Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('createtime','>=',$monthStart)->where('createtime','<',$monthEnd)->where('status','=','2')->where($where1)->count();
		//已完成
		$orderFinishMonthCount = 0 + Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('createtime','>=',$monthStart)->where('createtime','<',$monthEnd)->where('status','=','3')->where($where1)->count();
		//已关闭
		$orderClosehMonthCount = 0 + Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('createtime','>=',$monthStart)->where('createtime','<',$monthEnd)->where('status','=','4')->where($where1)->count();
		//已退款
		$orderRefundMonthCount = 0 + Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('createtime','>=',$monthStart)->where('createtime','<',$monthEnd)->where('refund_status','=','2')->where($where1)->count();

		$orderNoZhifuMonthCountBili = $orderMonthCountAll > 0 ? round($orderNoZhifuMonthCount * 100 / $orderMonthCountAll ,2) : 0;
		$orderNoFahuoMonthCountBili = $orderMonthCountAll > 0 ? round($orderNoFahuoMonthCount * 100 / $orderMonthCountAll ,2) : 0;
		$orderFahuoMonthCountBili = $orderMonthCountAll > 0 ? round($orderFahuoMonthCount * 100 / $orderMonthCountAll ,2) : 0;
		$orderFinishMonthCountBili = $orderMonthCountAll > 0 ? round($orderFinishMonthCount * 100 / $orderMonthCountAll ,2) : 0;
		$orderClosehMonthCountBili = $orderMonthCountAll > 0 ? round($orderClosehMonthCount * 100 / $orderMonthCountAll ,2) : 0;
		$orderRefundMonthCountBili = $orderMonthCountAll > 0 ? round($orderRefundMonthCount * 100 / $orderMonthCountAll ,2) : 0;

		$channel_order_data[] = [
			'name'=>$orderNoZhifuMonthCountBili.'%',
			'itemStyle'=>['color'=>'#ed7330'],
			'value'=>$orderNoZhifuMonthCount
		];
		$channel_order_data[] = [
			'name'=>$orderNoFahuoMonthCountBili.'%',
			'itemStyle'=>['color'=>'#FFB723'],
			'value'=>$orderNoFahuoMonthCount
		];
		$channel_order_data[] = [
			'name'=>$orderFahuoMonthCountBili.'%',
			'itemStyle'=>['color'=>'#1ECF8F'],
			'value'=>$orderFahuoMonthCount
		];
		$channel_order_data[] = [
			'name'=>$orderFinishMonthCountBili.'%',
			'itemStyle'=>['color'=>'#5FB8FC'],
			'value'=>$orderFinishMonthCount
		];
		$channel_order_data[] = [
			'name'=>$orderClosehMonthCountBili.'%',
			'itemStyle'=>['color'=>'#6161F9'],
			'value'=>$orderClosehMonthCount
		];
		$channel_order_data[] = [
			'name'=>$orderRefundMonthCountBili.'%',
			'itemStyle'=>['color'=>'#333E6A'],
			'value'=>$orderRefundMonthCount
		];
		View::assign('channel_order_data',$channel_order_data);
		View::assign('orderMonthCountAll',$orderMonthCountAll);
		View::assign('orderNoZhifuMonthCount',$orderNoZhifuMonthCount);
		View::assign('orderNoFahuoMonthCount',$orderNoFahuoMonthCount);
		View::assign('orderFahuoMonthCount',$orderFahuoMonthCount);
		View::assign('orderFinishMonthCount',$orderFinishMonthCount);
		View::assign('orderClosehMonthCount',$orderClosehMonthCount);
		View::assign('orderRefundMonthCount',$orderRefundMonthCount);

		//会员概览
		$monthEnd30 = strtotime(date('Y-m-d',time()-86400*30));
		//会员总数
		$memberCount = 0 + Db::name('member')->where('aid',aid)->count();
		//活跃会员
		$memberHuoyueCount = 0 + Db::name('member')->where('aid',aid)->where('last_visittime','>',$monthEnd30)->count();
		//下单会员总数
		$orderMemberCount = 0 + Db::name('shop_order')->where('aid',aid)->where('status','in','1,2,3')->group('mid')->count();
		$orderMemberCountBili = $memberCount > 0 ? floor($orderMemberCount * 100 / $memberCount) : 0;
		//复购会员数
		$orderMemberFugouCount = 0 + Db::name('shop_order')->where('aid',aid)->where('status','in','1,2,3')->group('mid')->having('count(id)>=2')->count();
		$orderMemberFugouCountBili = $memberCount > 0 ? floor($orderMemberFugouCount * 100 / $memberCount) : 0;
		//余额会员数
		$memberMoneyCount = 0 + Db::name('member')->where('aid',aid)->where('money','>',0)->count();
		$memberMoneyCountBili = $memberCount > 0 ? floor($memberMoneyCount * 100 / $memberCount) : 0;
		//佣金会员数
		$memberCommissionCount = 0 + Db::name('member')->where('aid',aid)->where('commission','>',0)->count();
		$memberCommissionCountBili = $memberCount > 0 ? floor($memberCommissionCount * 100 / $memberCount) : 0;

		$memberChartData = [];
		if($memberCount == 0){
			$memberCommissionCountBili = 0;
			$memberMoneyCountBili = 0;
			$orderMemberFugouCountBili = 0;
			$orderMemberCountBili = 0;
		}
		$memberChartData[] = [
			'name'=>'佣金会员数占比',
			'itemStyle'=>['color'=>'rgba(30, 159, 255, 0.2)'],
			'num'=>$memberCommissionCount,
			'value'=>$memberCommissionCountBili
		];
		$memberChartData[] = [
			'name'=>'余额会员数占比',
			'itemStyle'=>['color'=>'rgba(30, 159, 255, 0.4)'],
			'num'=>$memberMoneyCount,
			'value'=>$memberMoneyCountBili
		];
		$memberChartData[] = [
			'name'=>'复购会员数占比',
			'itemStyle'=>['color'=>'rgba(30, 159, 255, 0.6)'],
			'num'=>$orderMemberFugouCount,
			'value'=>$orderMemberFugouCountBili
		];
		$memberChartData[] = [
			'name'=>'下单会员数占比',
			'itemStyle'=>['color'=>'#1E9FFF'],
			'num'=>$orderMemberCount,
			'value'=>$orderMemberCountBili
		];
		$memberChartDataname = [$memberCommissionCountBili,$memberMoneyCountBili,$orderMemberFugouCountBili,$orderMemberCountBili];
		
		//$memberChartDataname = ['a2','b3','c4','d5'];
		View::assign('memberChartDataname',$memberChartDataname);
		View::assign('memberChartData',$memberChartData);
		View::assign('memberCount',$memberCount);
		View::assign('memberHuoyueCount',$memberHuoyueCount);
		View::assign('orderMemberCount',$orderMemberCount);
		View::assign('orderMemberCountBili',$orderMemberCountBili);
		View::assign('orderMemberFugouCount',$orderMemberFugouCount);
		View::assign('orderMemberFugouCountBili',$orderMemberFugouCountBili);
		View::assign('memberMoneyCount',$memberMoneyCount);
		View::assign('memberMoneyCountBili',$memberMoneyCountBili);
		View::assign('memberCommissionCount',$memberCommissionCount);
		View::assign('memberCommissionCountBili',$memberCommissionCountBili);



		//会员金额概览
		//储值余额
		$memberMoney = Db::name('member')->where('aid',aid)->sum('money');
		$memberMoney = round($memberMoney,2);
		//储值总额
		$memberMoneySum = Db::name('recharge_order')->where('aid',aid)->where('status','=',1)->sum('money');
		$memberMoneySum = round($memberMoneySum,2);
		//佣金余额
		$memberCommission = Db::name('member')->where('aid',aid)->sum('commission');		
		$memberCommission = round($memberCommission,2);
		//佣金总额
		$memberCommissionSum = Db::name('member')->where('aid',aid)->sum('totalcommission');
		$memberCommissionSum = round($memberCommissionSum,2);
		//积分余额
		$memberScore = Db::name('member')->where('aid',aid)->sum('score');
		$memberScore = round($memberScore,0);
		//积分总额
		$memberScoreSum = Db::name('member')->where('aid',aid)->sum('totalscore');
		$memberScoreSum = round($memberScoreSum,0);


		View::assign('memberMoney',$memberMoney);
		View::assign('memberMoneySum',$memberMoneySum);
		View::assign('memberCommission',$memberCommission);
		View::assign('memberCommissionSum',$memberCommissionSum);
		View::assign('memberScore',$memberScore);
		View::assign('memberScoreSum',$memberScoreSum);

		//通知列表
		$noticedata = Db::name('admin_notice')->where('aid',aid)->where('uid',uid)->limit(10)->order('id desc')->select()->toArray();
		View::assign('noticedata',$noticedata);

		//分类
		if(bid == 0){
			$clist = Db::name('shop_category')->Field('id,name')->where('aid',aid)->where('pid',0)->order('sort desc,id')->select()->toArray(); 
			foreach($clist as $k=>$v){
				$child = Db::name('shop_category')->Field('id,name')->where('aid',aid)->where('pid',$v['id'])->order('sort desc,id')->select()->toArray();
				foreach($child as $k2=>$v2){
					$child2 = Db::name('shop_category')->Field('id,name')->where('aid',aid)->where('pid',$v2['id'])->order('sort desc,id')->select()->toArray();
					$child[$k2]['child'] = $child2;
				}
				$clist[$k]['child'] = $child;
			}
			View::assign('clist',$clist);
		}else{
			$clist = Db::name('shop_category2')->Field('id,name')->where('aid',aid)->where('pid',0)->order('sort desc,id')->select()->toArray(); 
			foreach($clist as $k=>$v){
				$child = Db::name('shop_category2')->Field('id,name')->where('aid',aid)->where('pid',$v['id'])->order('sort desc,id')->select()->toArray();
				foreach($child as $k2=>$v2){
					$child2 = Db::name('shop_category2')->Field('id,name')->where('aid',aid)->where('pid',$v2['id'])->order('sort desc,id')->select()->toArray();
					$child[$k2]['child'] = $child2;
				}
				$clist[$k]['child'] = $child;
			}
			View::assign('clist',$clist);
		}

		return View::fetch();
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
					$dataArr[] = 0 + Db::name('payorder')->where('aid',aid)->where('createtime','>=',$thisDayStart)->where('createtime','<',$thisDayEnd)->where('paytypeid','not in','1,4')->where('type','<>','daifu')->where('status',1)->sum('money');
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
		$auth_data = $this->auth_data;
		if(bid == 0){
			if(request()->isPost()){
				$rs = Db::name('admin_set')->where('aid',aid)->find();
				$info = input('post.info/a');

				if(!empty($info['ali_apppublickey']) && substr($info['ali_apppublickey'], -4) != '.crt'){
					return json(['status'=>0,'msg'=>'应用公钥格式错误']);
				}
				if(!empty($info['ali_publickey']) && substr($info['ali_publickey'], -4) != '.crt'){
					return json(['status'=>0,'msg'=>'支付宝公钥格式错误']);
				}
				if(!empty($info['ali_publickey']) && substr($info['ali_rootcert'], -4) != '.crt'){
					return json(['status'=>0,'msg'=>'支付宝根证书格式错误']);
				}
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
                
                $info['rank_type'] = implode(',',$info['rank_type']);
				$info['transfer_type'] = implode(',',$info['transfer_type']);
				$info['score_transfer_gettj'] = implode(',',$info['score_transfer_gettj']);
				
                
            
                
                
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
                    $mapqq = new \app\commons\MapQQ();
                    $address_component = $mapqq->locationToAddress($info['latitude'],$info['longitude']);
                    if($address_component && $address_component['status']==1){
                        $info['province'] = $address_component['province'];
                        $info['city'] = $address_component['city'];
                        $info['district'] = $address_component['district'];
                    }
                }
                if(getcustom('pay_transfer')){
                    if($info['pay_transfer_gettj']){
                        $info['pay_transfer_gettj'] = implode(',',$info['pay_transfer_gettj']);
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
				
				
				
				
             	$webinfo = Db::name('sysset')->where('name','webinfo')->value('value');
     	    	$webinfo = json_decode($webinfo,true);
    		    $webpost = input('post.webinfo/a');
                $webinfo['webname'] = $webpost['webname'];
                $webinfo['logo'] = $webpost['logo'];
                $webinfo['ico'] = $webpost['ico'];
                $webinfo['adminloginbg'] = $webpost['adminloginbg'];
                $webinfo['copyright'] = $webpost['copyright'];
                $webinfo['beian'] = $webpost['beian'];
                $webinfo['copyright2'] = $webpost['copyright2'];
                $webinfo['beian2'] = $webpost['beian2'];
                
                
               // $webinfo['ali_appcode'] = $webpost['ali_appcode'];
                $webinfo['map_key_qq'] = $webpost['map_key_qq'];
                $webinfo['map_key_amap'] = $webpost['map_key_amap'];
                $webinfo['js_code_amap'] = $webpost['js_code_amap'];
                    
                
                $webinfo['ali_appcode'] = $webpost['ali_appcode'];
                
                
                
                
				Db::name('sysset')->where('name','webinfo')->update(['value'=>jsonEncode($webinfo)]);
				
				
				if($postinfo['map_key_qq']){
                    $rsh5 = $this->updateH5QQMapKey($postinfo['map_key_qq']);
                    if($rsh5){
                        if($rsh5['status'] != 1){
                            return json($rsh5);
                        }
                    }
                }
				
				
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
            $newtextset = ['团队分红'=>'团队分红','股东分红'=>'股东分红','区域代理分红'=>'区域代理分红','团队业绩'=>'团队业绩'];
            if(getcustom('pay_transfer')) {$newtextset['转账汇款']='转账汇款';}
            if(!$textset) {
                $textset = array_merge($oldtextset,$newtextset);
            } else {
			    if(array_keys($textset) == array_keys($oldtextset)) {
                    $textset = array_merge($textset, $newtextset);
                }
                if(getcustom('pay_transfer')){
                    if(!$textset['转账汇款']) $textset['转账汇款']='转账汇款';
                }
                }
            if(!$textset['团队业绩']){
				$textset['团队业绩']='团队业绩';
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
         	if(!$textset['团队等级']){
				$textset['团队等级']='团队等级';
			}
			if(!$textset['credit1']){
				$textset['credit1']='credit1';
			}
			if(!$textset['credit2']){
				$textset['credit2']='credit2';
			}
			if(!$textset['资金池']){
				$textset['资金池']='资金池';
			}
			 if(getcustom('product_givetongzheng')) {
			    if(!$textset['通证']){
    				$textset['通证']='通证';
    			}
			 }
			
			
            $textset['团队'] = $textset['团队']??'团队';
            $textset['一级'] = $textset['一级']??'一级';
            $textset['二级'] = $textset['二级']??'二级';
            $textset['三级'] = $textset['三级']??'三级';
            $textset['下级'] = $textset['下级']??'下级';
            $textset['下二级'] = $textset['下二级']??'下二级';
            $textset['下三级'] = $textset['下三级']??'下三级';
            $textset['后台修改'] = $textset['后台修改']??'后台修改';
			$info['gettj'] = explode(',',$info['gettj']);
            $info['invoice_type'] = explode(',',$info['invoice_type']);
            if(getcustom('pay_transfer')){
                $info['pay_transfer_gettj'] = $info['pay_transfer_gettj'] ? explode(',',$info['pay_transfer_gettj']) : [];
            }

            $default_cid = Db::name('member_level_category')->where('aid',aid)->where('isdefault', 1)->value('id');
            $default_cid = $default_cid ? $default_cid : 0;
            $levellist = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->order('sort,id')->select()->toArray();
			View::assign('levellist',$levellist);

			$ainfo = Db::name('admin')->where('id',aid)->find();

			$xyinfo = Db::name('admin_set_xieyi')->where('aid',aid)->find();
            if(getcustom('file_size_limit')){
                $remote = Db::name('sysset')->where('name','remote')->value('value');
                $remote = json_decode($remote,true);
                View::assign('remote',$remote);
            }

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
			
			
			View::assign('team_yeji_show',true);
			
			
			
			
			
			$webinfo = Db::name('sysset')->where('name','webinfo')->value('value');
 	    	$webinfo = json_decode($webinfo,true);
 	    	
 	    	
			View::assign('webinfo',$webinfo);
			
			return View::fetch();
		}
		else{
			$bset    = Db::name('business_sysset')->where('aid',aid)->find();
			$oldinfo = Db::name('business')->where('aid',aid)->where('id',bid)->find();

			$BaseSet=$RefundSet=$OpenSet=$PaySet=true;//基础、退货、营业、支付权限设置
            if(request()->isPost()){
				$postinfo = input('post.info/a');
				$info = [];
				//基础部分
				if($BaseSet){
					$info['tel'] = $postinfo['tel'];
					$info['kfurl'] = $postinfo['kfurl'];
					$info['logo'] = $postinfo['logo'];
					$info['pics'] = $postinfo['pics'];
					$info['content'] = $postinfo['content'];
					$info['address'] = $postinfo['address'];
					$info['longitude'] = $postinfo['longitude'];
					$info['latitude'] = $postinfo['latitude'];
	                if($postinfo['latitude'] && $postinfo['longitude']){
	                    //通过坐标获取省市区
	                    $mapqq = new \app\commons\MapQQ();
	                    $address_component = $mapqq->locationToAddress($postinfo['latitude'],$postinfo['longitude']);
	                    if($address_component && $address_component['status']==1){
	                        $info['province'] = $address_component['province'];
	                        $info['city'] = $address_component['city'];
	                        $info['district'] = $address_component['district'];
	                    }
	                }
                }

                //退款部分
                if($RefundSet){
                	$info['return_name']     = $postinfo['return_name']?$postinfo['return_name']:'';
					$info['return_tel']      = $postinfo['return_tel']?$postinfo['return_tel']:'';
					$info['return_province'] = $postinfo['return_province']?$postinfo['return_province']:'';
					$info['return_city']     = $postinfo['return_city']?$postinfo['return_city']:'';
					$info['return_area']     = $postinfo['return_area']?$postinfo['return_area']:'';
					$info['return_address']  = $postinfo['return_address']?$postinfo['return_address']:'';
                }
				
				//营业部分
				if($OpenSet){
					$info['start_hours2'] = $postinfo['start_hours2'];
					$info['end_hours2'] = $postinfo['end_hours2'];
					$info['start_hours3'] = $postinfo['start_hours3'];
					$info['end_hours3'] = $postinfo['end_hours3'];
					$info['start_hours'] = $postinfo['start_hours'];
					$info['end_hours'] = $postinfo['end_hours'];
               		$info['end_buy_status'] = $postinfo['end_buy_status'];
               		$info['is_open'] = $postinfo['is_open'];
				}

				//支付相关设置
				if($PaySet){
					$info['weixin'] = $postinfo['weixin'];
					$info['aliaccount'] = $postinfo['aliaccount'];
					$info['bankname'] = $postinfo['bankname'];
					$info['bankcarduser'] = $postinfo['bankcarduser'];
					$info['bankcardnum'] = $postinfo['bankcardnum'];
					$info['invoice'] = $postinfo['invoice'];
                	$info['invoice_type'] = implode(',',$postinfo['invoice_type']);
                	$info['autocollecthour'] = $postinfo['autocollecthour'];
                	if(getcustom('money_dec') || getcustom('cashier_money_dec')){
	                    $info['money_dec']      = $postinfo['money_dec'];
	                    $info['money_dec_rate'] = $postinfo['money_dec_rate'];
	                }
	                }

				db('business')->where(['aid'=>aid,'id'=>bid])->update($info);
				\app\commons\System::plog('系统设置');
				return json(['status'=>1,'msg'=>'设置成功','url'=>true]);
			}
			$info = $oldinfo;
            $info['invoice_type'] = explode(',',$info['invoice_type']);
			View::assign('info',$info);
			View::assign('bset',$bset);
            View::assign('admin',$admin);
            
            View::assign('BaseSet',$BaseSet);
            View::assign('RefundSet',$RefundSet);
            View::assign('OpenSet',$OpenSet);
            View::assign('PaySet',$PaySet);
			return View::fetch('syssetb');
		}
	}
	public function updateH5QQMapKey($qqmapkey='')
    {
        $from = input('param.from');
        if(empty($qqmapkey)) {
            $info = Db::name('sysset')->where('name','webinfo')->find();
            $webinfo = json_decode($info['value'],true);
            $qqmapkey = $webinfo['map_key_qq'];
        }

        $directory = "h5/static/js";

        // 获取文件夹内的文件列表
        $files = scandir($directory);

        $indexFils = [];
        // 遍历文件列表并输出文件名
        foreach($files as $file) {
            if(preg_match('/^index\.([^\.]+)\.js$/', $file)){
                $indexFils[] = $file;
            }
        }

        if($indexFils){
            foreach($indexFils as $file) {
                $filepath = $directory.'/'.$file;
                if (!is_writable($filepath)) {
                    $return = ['status' => 0, 'msg' => $filepath.'没有写权限，H5地图key更新失败'];
                    if($from == 'update'){
                        $return = json($return);
                    }
                    return $return;
                }
                $filecontent = File::get($filepath);
                $newfilecontent = preg_replace('/qqMapKey="[^"]*"/','qqMapKey="'.$qqmapkey.'"',$filecontent);
                $newfilecontent = preg_replace('/qqmap:\{key:"([^"]+)"/','qqmap:{key:"'.$qqmapkey.'"',$newfilecontent);
                $rs = File::put($filepath,$newfilecontent);
                if(!$rs){
                    $return = ['status' => 0, 'msg' => 'H5地图key更新失败'];
                    if($from == 'update'){
                        $return = json($return);
                    }
                    return $return;
                }
            }
        }

        $return = ['status' => 1, 'msg' => '成功更新'];
        if($from == 'update'){
            $return = json($return);
        }
        return $return;
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

            \app\commons\System::plog('企微设置');
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

    //随行付服务商
    public function sxpayset(){
        }
}
