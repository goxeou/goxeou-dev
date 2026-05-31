<?php

namespace app\controllers;
use think\facade\Log;
use think\facade\View;
use think\facade\Db;
class Paihang extends Common
{
    public function initialize(){
        parent::initialize();
    }
    //订单统计
	public function index(){
	    $sysset = Db::name('admin_set')->where('aid',aid)->find();
	    $ranktype = 1;
	    $levelArr = Db::name('member_level')->where('aid',aid)->order('sort,id')->column('name','id');
        if(request()->isAjax() || input('param.excel') == 1){
           
            if(input('param.excel') == 1){
				$page = 1;
				$limit = 10000000;
			}else{
				$page = input('param.page');
				$limit = input('param.limit');
			}
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'totalprice desc';
			}
			$where = [];
    		$where[] = ['og.aid','=',aid];
			$year = date('Y');
			$month = date('m');
			$rankdate= '';
			if(input('param.year')) $year = input('param.year');
			if(input('param.month')) $month = input('param.month');
			if(input('param.rank_date')) $rankdate = input('param.rank_date');

			$where2 = [];
			if(input('param.ctime') ){
		    	$ctime = explode(' ~ ',input('param.ctime'));
    			$where2[] = ['og.createtime','>=',strtotime($ctime[0])];
    			$where2[] = ['og.createtime','<',strtotime($ctime[1]) + 86400];
    		}else {
	            if ($rankdate) {
    			    $timeRangeByYear = self::getTimeRangeBySeason($year,$rankdate);
    			    $where2[] = ['og.createtime','>=',$timeRangeByYear[0]];
        			$where2[] = ['og.createtime','<',$timeRangeByYear[1]];
    			}elseif ($month) {
    			    $timeRangeByYear = self::getTimeRangeByMonth($year,$month);
    			    $where2[] = ['og.createtime','>=',$timeRangeByYear[0]];
        			$where2[] = ['og.createtime','<',$timeRangeByYear[1]];
    			}else {
    			    $timeRangeByYear = self::getTimeRangeByYear($year);
    			    $where2[] = ['og.createtime','>=',$timeRangeByYear[0]];
        			$where2[] = ['og.createtime','<',$timeRangeByYear[1]];
    			}
    		}
         	$fields = 'member.id,member.nickname,member.headimg,member.levelid,sum(og.totalprice) totalprice';
			$count = 0 + Db::name('shop_order_goods')->alias('og')->join('member member','member.id=og.parent1')->fieldRaw('og.parent1')->where($where)->where($where2)->group('og.parent1')->count();
			$list = Db::name('shop_order_goods')->alias('og')->join('member member','member.id=og.parent1')->fieldRaw($fields)->where($where)->where($where2)->group('og.parent1')->page($page,$limit)->order($order)->select()->toArray();
	     	foreach($list as $k=>$v){
    		     $list[$k]['levelname'] = $levelArr[$v['levelid']];
    		     $list[$k]['ph'] = ($k+1) + ($page-1)*$limit;
    		}
			if(input('param.excel') == 1){
				$title = array();
				$title[] = '排行';
	        	$title[] = t('会员').'信息';
        		$title[] = '团队业绩';
        		$data = array();
        		foreach($list as $v){
        			$tdata = array();
        			$tdata[] = $v['ph'];
        			$tdata[] = $v['nickname'].'('.t('会员').'ID:'.$v['id'].')';
        			$tdata[] = $v['totalprice'];
        			$data[] = $tdata;
        		}
        		$this->export_excel($title,$data);
			}
			if($page == 1){
			    $fields = 'sum(og.totalprice) totalprice,sum(og.cost_price*og.num) as chengben';
				$totaldata = Db::name('shop_order_goods')->alias('og')->join('shop_order','shop_order.id=og.orderid')->fieldRaw($fields)->where('og.aid',aid)->where($where2)->find();

				ll($where2,'auto_day');
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$list,'totaldata'=>$totaldata]);
		}
		
		View::assign('day',date('d'));
		View::assign('month',date('m'));
		return View::fetch();
	}
// 	//订单统计
// 	public function index(){
// 	    $sysset = Db::name('admin_set')->where('aid',aid)->find();
// 	    $ranktype = 1;
// 	    $levelArr = Db::name('member_level')->where('aid',aid)->order('sort,id')->column('name','id');
//         if(request()->isAjax() || input('param.excel') == 1){
           
//             if(input('param.excel') == 1){
// 				$page = 1;
// 				$limit = 10000000;
// 			}else{
// 				$page = input('param.page');
// 				$limit = input('param.limit');
// 			}
// 			if(input('param.field') && input('param.order')){
// 				$order = input('param.field').' '.input('param.order');
// 			}else{
// 				$order = 'totalprice desc';
// 			}
// 			$where = [];
//     		$where[] = ['og.aid','=',aid];
// 			$year = date('Y');
// 			$month = date('m');
// 			$rankdate= '';
// 			if(input('param.year')) $year = input('param.year');
// 			if(input('param.month')) $month = input('param.month');
// 			if(input('param.rank_date')) $rankdate = input('param.rank_date');

// 			$where2 = [];
// 			if(input('param.ctime') ){
// 		    	$ctime = explode(' ~ ',input('param.ctime'));
//     			$where2[] = ['og.createtime','>=',strtotime($ctime[0])];
//     			$where2[] = ['og.createtime','<',strtotime($ctime[1]) + 86400];
//     		}else {
// 	            if ($rankdate) {
//     			    $timeRangeByYear = self::getTimeRangeBySeason($year,$rankdate);
//     			    $where2[] = ['og.createtime','>=',$timeRangeByYear[0]];
//         			$where2[] = ['og.createtime','<',$timeRangeByYear[1]];
//     			}elseif ($month) {
//     			    $timeRangeByYear = self::getTimeRangeByMonth($year,$month);
//     			    $where2[] = ['og.createtime','>=',$timeRangeByYear[0]];
//         			$where2[] = ['og.createtime','<',$timeRangeByYear[1]];
//     			}else {
//     			    $timeRangeByYear = self::getTimeRangeByYear($year);
//     			    $where2[] = ['og.createtime','>=',$timeRangeByYear[0]];
//         			$where2[] = ['og.createtime','<',$timeRangeByYear[1]];
//     			}
//     		}
//          	$fields = 'member.id,member.nickname,member.headimg,member.levelid,sum(og.money) totalprice';
// 			$count = 0 + Db::name('member_team_money')->alias('og')->join('member member','member.id=og.mid')->fieldRaw('og.mid')->where($where)->where($where2)->group('og.mid')->count();
// 			$list = Db::name('member_team_money')->alias('og')->join('member member','member.id=og.mid')->fieldRaw($fields)->where($where)->where($where2)->group('og.mid')->page($page,$limit)->order($order)->select()->toArray();
// 	     	foreach($list as $k=>$v){
//     		     $list[$k]['levelname'] = $levelArr[$v['levelid']];
//     		     $list[$k]['ph'] = ($k+1) + ($page-1)*$limit;
//     		}
// 			if(input('param.excel') == 1){
// 				$title = array();
// 				$title[] = '排行';
// 	        	$title[] = t('会员').'信息';
//         		$title[] = '团队业绩';
//         		$data = array();
//         		foreach($list as $v){
//         			$tdata = array();
//         			$tdata[] = $v['ph'];
//         			$tdata[] = $v['nickname'].'('.t('会员').'ID:'.$v['id'].')';
//         			$tdata[] = $v['totalprice'];
//         			$data[] = $tdata;
//         		}
//         		$this->export_excel($title,$data);
// 			}
// 			if($page == 1){
// 			    $fields = 'sum(og.totalprice) totalprice,sum(og.cost_price*og.num) as chengben';
// 				$totaldata = Db::name('shop_order_goods')->alias('og')->join('shop_order','shop_order.id=og.orderid')->fieldRaw($fields)->where('og.aid',aid)->where($where2)->find();

// 				ll($where2,'auto_day');
// 			}
// 			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$list,'totaldata'=>$totaldata]);
// 		}
		
// 		View::assign('day',date('d'));
// 		View::assign('month',date('m'));
// 		return View::fetch();
// 	}
    public function monthfh(){
		$mid = input('post.id/d');
		$year = input('post.year');
		$month = input('post.month');
		$yjbili = floatval(input('post.yjbili'));
		$fhcount = floatval(input('post.hybili'));
		if($yjbili == 0){
			return json(['status'=>0,'msg'=>'请输入业绩比例']);
		}
		if($fhcount == 0){
			return json(['status'=>0,'msg'=>'请输入会员人数']);
		}
		$insertnum = 0;
		$where = [];
		$where[] = ['og.aid','=',aid];
	    $timeRangeByYear = self::getTimeRangeByMonth($year,$month);
	    $where[] = ['og.createtime','>=',$timeRangeByYear[0]];
		$where[] = ['og.createtime','<',$timeRangeByYear[1]];

		$totaldata = Db::name('shop_order_goods')->alias('og')->join('shop_order','shop_order.id=og.orderid')->fieldRaw('sum(og.totalprice) totalprice,sum(og.cost_price*og.num) as chengben')->where($where)->find();//->where('og.is_yeji',1)
		
		$fhmoney = round($totaldata['chengben']*$yjbili*0.01,8);
		if ($fhmoney>0 && $fhcount>0) {
		    $list = Db::name('member_team_money')->alias('og')->join('member member','member.id=og.mid')->fieldRaw('member.id,member.nickname,member.headimg,member.levelid,member.commission,member.totalcommission,sum(og.money) totalprice')->where($where)->group('og.mid')->limit($fhcount)->order('totalprice desc')->select()->toArray();
		    $remark = $year.'-'.$month.'月分红发放';
		    $commission = round($fhmoney/$fhcount,8);
			$returndata = self::giveFhong(aid,$commission,$remark,$list);
			\app\commons\System::plog($remark);
	    	return json(['status'=>1,'msg'=>'成功发放'.$returndata['totalfhmoney'].'元，发放会员'.$returndata['insertnum'].'人']);
		    
		}
		
		return json(['status'=>1,'msg'=>'发放失败']);
	}
    public function seasonfh(){
		$mid = input('post.id/d');
		$year = input('post.year');
		$season = input('post.rank_date');
		$yjbili = floatval(input('post.yjbili'));
		$fhcount = floatval(input('post.hybili'));
		if($yjbili == 0){
			return json(['status'=>0,'msg'=>'请输入业绩比例']);
		}
		if($fhcount == 0){
			return json(['status'=>0,'msg'=>'请输入会员人数']);
		}
	
		$insertnum = 0;
		$where = [];
		$where[] = ['og.aid','=',aid];
	    $timeRangeByYear = self::getTimeRangeBySeason($year,$season);
	    $where[] = ['og.createtime','>=',$timeRangeByYear[0]];
		$where[] = ['og.createtime','<',$timeRangeByYear[1]];
		$totaldata = Db::name('shop_order_goods')->alias('og')->join('shop_order','shop_order.id=og.orderid')->fieldRaw('sum(og.totalprice) totalprice,sum(og.cost_price*og.num) as chengben')->where($where)->find();//->where('og.is_yeji',1)
		$fhmoney = round($totaldata['totalprice']*$yjbili*0.01,8);
	
		if ($fhmoney>0 && $fhcount>0) {
		    $list = Db::name('member_team_money')->alias('og')->join('member member','member.id=og.mid')->fieldRaw('member.id,member.nickname,member.headimg,member.levelid,member.commission,member.totalcommission,sum(og.money) totalprice')->where($where)->group('og.mid')->limit($fhcount)->order('totalprice desc')->select()->toArray();
		    $remark = $year.'-第'.$season.'季度-分红发放';
		    $commission = round($fhmoney/$fhcount,8);
			$returndata = self::giveFhong(aid,$commission,$remark,$list);
			\app\commons\System::plog($remark);
	    	return json(['status'=>1,'msg'=>'成功发放'.$returndata['totalfhmoney'].'元，发放会员'.$returndata['insertnum'].'人']);
		}
		
		return json(['status'=>1,'msg'=>'发放失败']);
	}
   
    
   public function giveFhong($aid,$commission=0,$remark='',$list=[]) {
       	$insertnum = 0;
        $totalfhmoney = 0;
       	try {
	     	Db::startTrans();
     		if ($commission > 0) {
                $update_array = [];
    			$commission_log_array = [];
             	foreach($list as $k=>$val2){
    			    $totalfhmoney += $commission;
    			    $insertnum ++;
    				$update = [];
    				$update['id'] = $val2['id'];
    				$update['commission'] =  $val2['commission'] + $commission;
    				$update['totalcommission'] = $val2['totalcommission'] + $commission;
    				$update_array[] = $update;
    			
    				$data = [];
    				$data['aid'] = $aid;
    				$data['mid'] = $val2['id'];
    				$data['commission'] =  $commission;
    				$data['after'] = $val2['commission'] + $commission;
    				$data['createtime'] = time();
    				$data['remark'] = $remark;
    				$commission_log_array[] = $data;
    			}
        		
            	if(!empty($update_array)) {
    				$model = new \app\models\Member();
    				$model->saveAll($update_array);
    			}
    			if(!empty($commission_log_array)) {
    				Db::name('member_commissionlog')->limit(100)->insertAll($commission_log_array);
    			}
     		}
       	    Db::commit();
		}
		catch (\Exception $e) {
			Log::write([
                'file' => __FILE__ . ' L' . __LINE__,
                'function' => __FUNCTION__,
                'error' => $e->getMessage(),
            ]);
			Db::rollback();
		}
      
       return ['status'=>1,'totalfhmoney'=>$totalfhmoney,'insertnum'=>$insertnum];
    }
	
    public function getTimeRangeByYear($year) {
        $startTimestamp = strtotime($year.'-01-01 00:00:00');
		$endTimestamp = strtotime(($year+1).'-01-01 00:00:00');
        return array($startTimestamp, $endTimestamp);
    }
    
    // 获取对应年月开始和结束的时间戳
    public   function getTimeRangeByMonth($year, $month) {
        $startTimestamp = strtotime($year.'-'.$month.'-01 00:00:00');
        $endTimestamp = strtotime(date('Y-m-t 23:59:59', strtotime($year.'-'.$month)));
        return array($startTimestamp, $endTimestamp);
    }
    
    // 获取对应年月日开始和结束的时间戳
    public  function getTimeRangeByDay($year, $month, $day) {
        $startTimestamp = strtotime($year.'-'.$month.'-'.$day.' 00:00:00');
        $endTimestamp = strtotime($year.'-'.$month.'-'.$day.' 23:59:59');
        return array($startTimestamp, $endTimestamp);
    }
     // 获取对应年月日开始和结束的时间戳
    public  function getTimeRangeBySeason($year, $season) {
     
        $startTimestamp = mktime(00, 00, 00, $season * 2 + 1, 1, $year);
        $endTimestamp = mktime(23, 59, 59, $season * 3, date('t', mktime(0, 0, 0, $season * 3, 1, date("Y"))), date('Y'));
        // // 获取季度的开始时间戳
        // $startTimestamp = strtotime("$year-01-01");
        // // 计算季度的结束时间戳
        // $endTimestamp = strtotime("+3 months", $startTimestamp);
        return array($startTimestamp, $endTimestamp);
    }
    
    
 
    
    

}
