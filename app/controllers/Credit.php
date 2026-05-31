<?php


// +----------------------------------------------------------------------
// | 余额管理
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class credit extends Common
{
    public function initialize(){
		parent::initialize();
	
	}
	  public function inputlockpwd(){
        
        $tel = Db::name('admin_set')->where('aid',aid)->value('c_tel');
        if(input('param.op') == 'sendsms'){
            $code = rand(100000,999999);
            session('smscode', md5($tel.'-'.$code));
            session('smscodetime', time() + 600);
            $rs = \app\commons\Sms::send(aid,$tel,'tmpl_smscode',['code'=>$code]);
            return json($rs);
        }
        
         if(request()->isPost()){
            $smscode = input('post.lockpwd');
    		 if($smscode == ''){
                return json(['status'=>0,'msg'=>'短信验证码不能为空']);
            }elseif(md5($tel.'-'.$smscode) != session('smscode') || time() > session('smscodetime')){
                return json(['status'=>0,'msg'=>'验证码错误或已过期']);
            }
    		return json(['status'=>1]);
         }
		
    }
    
    
    	//充值积分
	public function addmoney1(){
		$fenhongprice = input('post.money');
		$remark = input('post.remark');
		$ctype = input('post.ctype');
		if($fenhongprice <= 0){
			return json(['status'=>0,'msg'=>'请输入要发放的数量']);
		}
		$total = Db::name('admin_credit1log')->where('aid',aid)->where('credit1','>',0)->where('type',$ctype)->sum('credit1');
		if ($fenhongprice > $total) {
		  //  return json(['status'=>0,'msg'=>'底池不足']);
		}
		try { 
			Db::startTrans();
    		$remark = t('资金池').$ctype.'-发放';
	        $update_array = [];
    		$money_log_array = [];
            $memberArray = Db::name('member')->where('aid',aid)->where([Db::raw("find_in_set(".$ctype.",credit_1)")])->field('id,credit_1,aid,credit2')->select()->toArray();
    		if ($memberArray) {
    		   \app\commons\Admin::addcredit1(aid,0,$fenhongprice*-1, $remark,0,$ctype,1);
    		    $commission = round($fenhongprice / count($memberArray), 8);
    		    foreach($memberArray as $val2 ) {
        			if ($commission > 0) {
        				$update = [];
        				$update['id'] = $val2['id'];
        				$update['credit2'] =  $val2['credit2'] + $commission;
        				$update_array[] = $update;
        				$money_log = [];
        				$money_log['aid'] = aid;
        				$money_log['mid'] = $val2['id'];
        				$money_log['credit2'] =  $commission;
        				$money_log['after'] = $val2['credit2'] + $commission;
        				$money_log['createtime'] = time();
        				$money_log['remark'] = $remark;
        				$money_log['channel'] = 1;
        				$money_log_array[] = $money_log;
        			}
        		}
    	    	if(!empty($update_array)) {
    				$model = new \app\models\Member();
    				$model->saveAll($update_array);
    			}
    			if(!empty($money_log_array)) {
    				Db::name('member_credit2log')->limit(100)->insertAll($money_log_array);
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
		\app\commons\System::plog(t('资金池').$ctype.'发放'.$fenhongprice);
		return json(['status'=>1,'msg'=>'操作成功']);
	}
    
    	//充值积分
	public function addmoney2(){
		$fenhongprice = input('post.money');
		$remark = input('post.remark');
		$ctype = input('post.ctype');
		if($fenhongprice <= 0){
			return json(['status'=>0,'msg'=>'请输入要发放的数量']);
		}
		$total = Db::name('admin_credit2log')->where('aid',aid)->where('credit2','>',0)->where('type',$ctype)->sum('credit2');
		if ($fenhongprice > $total) {
		    //return json(['status'=>0,'msg'=>'底池不足']);
		}
		try { 
			Db::startTrans();
    		$remark = t('分红池').$ctype.'-发放';
	        $update_array = [];
    		$money_log_array = [];
            $memberArray = Db::name('member')->where('aid',aid)->where([Db::raw("find_in_set(".$ctype.",credit_2)")])->field('id,credit_2,aid,credit2')->select()->toArray();
    		if ($memberArray) {
    		   \app\commons\Admin::addcredit2(aid,0,$fenhongprice*-1, $remark,0,$ctype,1);
    		    $commission = round($fenhongprice / count($memberArray), 8);
    		    foreach($memberArray as $val2 ) {
        			if ($commission > 0) {
        				$update = [];
        				$update['id'] = $val2['id'];
        				$update['credit2'] =  $val2['credit2'] + $commission;
        				$update_array[] = $update;
        				$money_log = [];
        				$money_log['aid'] = aid;
        				$money_log['mid'] = $val2['id'];
        				$money_log['credit2'] =  $commission;
        				$money_log['after'] = $val2['credit2'] + $commission;
        				$money_log['createtime'] = time();
        				$money_log['remark'] = $remark;
        				$money_log['channel'] = 1;
        				$money_log_array[] = $money_log;
        			}
        		}
    	    	if(!empty($update_array)) {
    				$model = new \app\models\Member();
    				$model->saveAll($update_array);
    			}
    			if(!empty($money_log_array)) {
    				Db::name('member_credit2log')->limit(100)->insertAll($money_log_array);
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
		\app\commons\System::plog(t('分红池').$ctype.'发放'.$fenhongprice);
		return json(['status'=>1,'msg'=>'操作成功']);
	}
    
    
    
    	//充值积分
	public function addmoney3(){
		$fenhongprice = input('post.money');
		$remark = input('post.remark');
		$ctype = input('post.ctype');
		if($fenhongprice <= 0){
			return json(['status'=>0,'msg'=>'请输入要发放的数量']);
		}
		$total = Db::name('admin_credit3log')->where('aid',aid)->where('credit3','>',0)->where('type',$ctype)->sum('credit3');
		if ($fenhongprice > $total) {
		    //return json(['status'=>0,'msg'=>'底池不足']);
		}
		try { 
			Db::startTrans();
    		$remark = t('私董会分红池').$ctype.'-发放';
	        $update_array = [];
    		$money_log_array = [];
            $memberArray = Db::name('member')->where('aid',aid)->where([Db::raw("find_in_set(".$ctype.",credit_3)")])->field('id,credit_3,aid,credit2')->select()->toArray();
    		if ($memberArray) {
    		    \app\commons\Admin::addcredit3(aid,0,$fenhongprice*-1, $remark,0,$ctype,1);
    		    $commission = round($fenhongprice / count($memberArray), 8);
    		    foreach($memberArray as $val2 ) {
        			if ($commission > 0) {
        				$update = [];
        				$update['id'] = $val2['id'];
        				$update['credit2'] =  $val2['credit2'] + $commission;
        				$update_array[] = $update;
        				$money_log = [];
        				$money_log['aid'] = aid;
        				$money_log['mid'] = $val2['id'];
        				$money_log['credit2'] =  $commission;
        				$money_log['after'] = $val2['credit2'] + $commission;
        				$money_log['createtime'] = time();
        				$money_log['remark'] = $remark;
        				$money_log['channel'] = 1;
        				$money_log_array[] = $money_log;
        			}
        		}
    	    	if(!empty($update_array)) {
    				$model = new \app\models\Member();
    				$model->saveAll($update_array);
    			}
    			if(!empty($money_log_array)) {
    				Db::name('member_credit2log')->limit(100)->insertAll($money_log_array);
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
		\app\commons\System::plog(t('私董会分红池').$ctype.'发放'.$fenhongprice);
		return json(['status'=>1,'msg'=>'操作成功']);
	}
    
    
		//发送优惠券
	public function sendcp(){
	    $teamList = Db::name('member_team_level')->where('aid',aid)->select()->toArray();
        $default_cid = Db::name('member_level_category')->where('aid',aid)->where('isdefault', 1)->value('id');
        $default_cid = $default_cid ? $default_cid : 0;
        $levelList = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->select()->toArray();
		$levelArr = array();
		foreach($levelList as $v){
			$levelArr[$v['id']] = $v['name']; 
		}
		$teamArr = array();
		foreach($teamList as $v){
			$teamArr[$v['id']] = $v['name']; 
		}
		View::assign('levelArr',$levelArr);
		View::assign('teamArr',$teamArr);
		return View::fetch();
	}
		//发送
	public function getdetail(){
		$ids = input('param.ids/a');
		$data = Db::name('member')->where('aid',aid)->where('id','in', $ids)->select()->toArray();
		$count = 0;
		foreach($data as $k=>$v){
		    $count++;
	        $downmids = \app\commons\Member::getteammids(aid,$v['id']);
	        $count +=count($downmids);
		}
		return json(['status'=>1,'count'=>$count]);
	}

	
	
				  	//充值积分
	public function addsend(){
		$money = input('post.money');
		$remark = input('post.remark');
		if($money <= 0){
			return json(['status'=>0,'msg'=>'请输入要发放的数量']);
		}
		$total = Db::name('admin_credit1log')->where('aid',aid)->sum('credit');
		if ($money > $total) {
		    return json(['status'=>0,'msg'=>'底池不足']);
		}
		$res = \app\commons\Admin::addcredit(aid,0,$money*-1, '手续费底池发放'.$remark);
// 		$userlist = Db::name('member_apply')->where('aid',aid)->select()->toArray();
// 		if ($userlist) {
// 		    $credit3 = round($money/count($userlist),2);
// 		    $res = \app\commons\Admin::addcredit(aid,0,$money*-1, '合伙人底池发放'.$remark);
// 		    if ($res['status']==1) {
// 		        foreach( $userlist as $val2 ) 
//               	{
//               	\app\commons\Aaa::fafang(aid,$val2['id'],0,$credit3, '合伙人底池发放'.$remark,2);
//                 }
    		
// 		    }
// 		}else {
// 		  return json(['status'=>1,'msg'=>'暂无合伙人']);
// 		}
		\app\commons\System::plog('手续费底池发放'.$money);
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	
	
	
	
	//发送
	public function sendfenhong(){
		$fenhongmoney = input('param.persendnum');
		if($fenhongmoney <= 0){
			return json(['status'=>0,'msg'=>'请输入要分红的金额']);
		}
		ll(input('param.'),'$page$page');
		$set = Db::name('admin_set')->where('aid', aid)->find();
		$datawhere = input('post.datawhere/a');
		if($datawhere['field'] && $datawhere['order']){
			$order = $datawhere['field'].' '.$datawhere['order'];
		}else{
			$order = 'id desc';
		}
		$fenhongprice = 0 + Db::name('admin_credit1log')->where('aid',aid)->sum('credit1');
		if($fenhongmoney > $fenhongprice){
            return json(['status'=>0,'msg'=>t('资金池').'不足']);
        }
		if(input('post.sendtype') == "0"){
            $where = "id in(".implode(',', $_POST['ids']).")";
		}elseif(input('post.sendtype') == '1'){
            $where = array();
            $where[] = ['aid','=',aid];
            if($datawhere['pid']) $where[] = ['pid','=',$datawhere['pid']];
            if($datawhere['nickname']) $where[] = ['nickname','like','%'.$datawhere['nickname'].'%'];
            if($datawhere['realname']) $where[] = ['realname','like','%'.$datawhere['realname'].'%'];
            if($datawhere['levelid']) $where[] = ['levelid','=',$datawhere['levelid']];
            if($datawhere['ctime']){
                $ctime = explode(' ~ ',$datawhere['ctime']);
                $where[] = ['createtime','>=',strtotime($ctime[0])];
                $where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
            }
            
		}elseif(input('post.sendtype') == '2'){
            $data = Db::name('member')->where('aid',aid)->where('id','in',$_POST['ids'])->select()->toArray();
            $downmids = [];
            foreach($data as $k=>$v){
    	        $mids = \app\commons\Member::getteammids(aid,$v['id']);
    	        $mids[] = $v['id'];
    	        $downmids = array_merge($downmids,$mids);
    		}
    		$where = array();
            $where[] = ['aid','=',aid];
            $where[] = ['id','in',$downmids];
            if($datawhere['ctime2']){
                $ctime = explode(' ~ ',$datawhere['ctime']);
                $where[] = ['createtime','>=',strtotime($ctime[0])];
                $where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
            }
		}else{
			return json(['status'=>0,'msg'=>'参数错误']);
		}
		//->where('credit1','>',0)
        $datalist = Db::name('member')->where($where)->where('credit1','>',0)->field('id,aid,credit1,levelid,buymoney,score,commission,totalcommission')->order($order)->select()->toArray();
        ll($datalist,'$datalist$datalist');
        //->where('credit1','>',0)
        $fhdian = Db::name('member')->where($where)->where('credit1','>',0)->sum('credit1');
        $remark = date("Y-m-d") . t('资金池') .'加权分红';
		$sucnum = 0;
		$errnum = 0;
		if ($fenhongmoney > 0 && $fhdian>0) {
            \app\commons\Admin::addcredit1(aid,0,$fenhongmoney*-1,$remark);
            $update_array = [];
            $commission_log_array = [];
            $score_log_array = [];
            $fhmoney = round($fenhongmoney / $fhdian, 8);
          
           	foreach($datalist as $val2 ) {
           	    $sucnum ++;
			    $commission =  round($fhmoney*$val2['credit1'],2);
				if ($commission <= 0) continue;
				$update = [];
				$update['id'] = $val2['id'];
				$score = 0;
				$oldcommission = $commission;
				if ($set['commission2scorepercent'] > 0) {
        			$score = round($commission * $set['commission2scorepercent']*0.01,2);
        			$commission = $oldcommission - $score;
				}
				$update['score'] =  $val2['score'] + $score;
				$update['commission'] =  $val2['commission'] + $commission;
				$update['totalcommission'] = $val2['totalcommission'] + $oldcommission;
				$update_array[] = $update;
				if ($score>0) {
				    $score_log = [];
    				$score_log['aid'] = aid;
    				$score_log['mid'] = $val2['id'];
    				$score_log['score'] =  $score;
    				$score_log['after'] = $val2['score'] + $score;
    				$score_log['createtime'] = time();
    				$score_log['remark'] = $remark;
    				$score_log_array[] = $score_log;
				}
				$commission_log = [];
				$commission_log['aid'] = aid;
				$commission_log['mid'] = $val2['id'];
				$commission_log['commission'] =  $commission;
				$commission_log['after'] = $val2['commission'] + $commission;
				$commission_log['createtime'] = time();
				$commission_log['remark'] = $remark;
				$commission_log['addtotal'] = 1;
				$commission_log_array[] = $commission_log;
			}
		
            if (!empty($update_array)) {
                $model = new \app\models\Member();
                $model->saveAll($update_array);
            }
            if (!empty($commission_log_array)) {
                Db::name('member_commissionlog')->limit(100)->insertAll($commission_log_array);
            }
            if (!empty($score_log_array)) {
                Db::name('member_scorelog')->limit(100)->insertAll($score_log_array);
            }
        }
	
		\app\commons\System::plog(t('资金池').'发放');
		if($sucnum==0){
            return json(['status'=>0,'msg'=>'发放失败','errnum'=>$errnum,'url'=>(string)url('Credit1/credit1log')]);
        }
		return json(['status'=>1,'msg'=>'发送完成','sucnum'=>$sucnum,'url'=>(string)url('Credit1/credit1log')]);
	}

	
	
	
	
	
	
	
	
	
			//佣金记录
    public function records(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = 'member_yeji_record.'.input('param.field').' '.input('param.order');
			}else{
				$order = 'member_yeji_record.id desc';
			}
			$where = [];
			$where[] = ['member_yeji_record.aid','=',aid];
			$where[] = ['member_yeji_record.status','in',[0,1]];
			if(input('param.ctime') ){
                $ctime = explode(' ~ ',input('param.ctime'));
                $where[] = ['member_yeji_record.createtime','>=',strtotime($ctime[0])];
                $where[] = ['member_yeji_record.createtime','<',strtotime($ctime[1]) + 86400];
            }
            
            $year = date('Y');
            if(input('param.year')) $year = input('param.year');
			if(input('param.month')){
			    $month = input('param.month');
			}else {
			    $month = date('m');
			} 
			$starttime = getTimeRangeByMonth($year,$month);
            $where[] = ['member_yeji_record.createtime','>=',$starttime[0]];
            $where[] = ['member_yeji_record.createtime','<',$starttime[1] + 86400];
            
			if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
			if(input('param.mid')) $where[] = ['member_yeji_record.mid','=',trim(input('param.mid'))];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['member_yeji_record.status','=',input('param.status')];
			$count = 0 + Db::name('member_yeji_record')->alias('member_yeji_record')->field('member.nickname,member.headimg,member_yeji_record.*')->join('member member','member.id=member_yeji_record.mid')->where($where)->count();
			$data = Db::name('member_yeji_record')->alias('member_yeji_record')->field('member.nickname,member.headimg,member_yeji_record.*')->join('member member','member.id=member_yeji_record.mid')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			
			foreach($data as $k=>$v){
			
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
    }
    //佣金记录删除
	public function recordsdel(){
		$ids = input('post.ids/a');
		Db::name('member_yeji_record')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('业绩记录'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}

		//佣金记录
    public function yeji(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = 'member_yeji_record.'.input('param.field').' '.input('param.order');
			}else{
				$order = 'member_yeji_record.id desc';
			}
			$where = [];
			$where[] = ['member_yeji_record.aid','=',aid];
			$where[] = ['member_yeji_record.status','in',[0,1]];
			if(input('param.ctime') ){
                $ctime = explode(' ~ ',input('param.ctime'));
                $where[] = ['member_yeji_record.createtime','>=',strtotime($ctime[0])];
                $where[] = ['member_yeji_record.createtime','<',strtotime($ctime[1]) + 86400];
            }
            
            $year = date('Y');
            if(input('param.year')) $year = input('param.year');
			if(input('param.month')){
			    $month = input('param.month');
			}else {
			    $month = date('m');
			} 
			$starttime = getTimeRangeByMonth($year,$month);
            $where[] = ['member_yeji_record.createtime','>=',$starttime[0]];
            $where[] = ['member_yeji_record.createtime','<',$starttime[1] + 86400];
            
			if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
			if(input('param.mid')) $where[] = ['member_yeji_record.mid','=',trim(input('param.mid'))];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['member_yeji_record.status','=',input('param.status')];
			$count = 0 + Db::name('member_yeji_record')->alias('member_yeji_record')->field('member.nickname,member.headimg,member_yeji_record.*')->join('member member','member.id=member_yeji_record.mid')->where($where)->count();
			$data = Db::name('member_yeji_record')->alias('member_yeji_record')->field('member.nickname,member.headimg,member_yeji_record.*')->join('member member','member.id=member_yeji_record.mid')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			
			foreach($data as $k=>$v){
			
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
    }
    	//审核删除
	public function yejiddel(){
		$type = input('post.type');
        $ids = input('post.ids/a');
		Db::name('member_yeji_record')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('删除会员业绩记录'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	//佣金记录
    public function record(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = 'member_credit_record.'.input('param.field').' '.input('param.order');
			}else{
				$order = 'member_credit_record.id desc';
			}
			$where = [];
			$where[] = ['member_credit_record.aid','=',aid];
			$where[] = ['member_credit_record.status','in',[0,1,2]];
			
			if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
			if(input('param.mid')) $where[] = ['member_credit_record.mid','=',trim(input('param.mid'))];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['member_credit_record.status','=',input('param.status')];
			if(input('id')){
                $where[] = ['member_credit_record.id','=',trim(input('param.id'))];
            }
			$count = 0 + Db::name('member_credit_record')->alias('member_credit_record')->field('member.nickname,member.headimg,member_credit_record.*')->join('member member','member.id=member_credit_record.mid')->where($where)->count();
			$data = Db::name('member_credit_record')->alias('member_credit_record')->field('member.nickname,member.headimg,member_credit_record.*')->join('member member','member.id=member_credit_record.mid')->where($where)->page($page,$limit)->order($order)->select()->toArray();
            $score_weishu = 0;
            if(getcustom('score_weishu')){
                $score_weishu = Db::name('admin_set')->where('aid',aid)->value('score_weishu');
                $score_weishu = $score_weishu?$score_weishu:0;
            }
			foreach($data as $k=>$v){
				if($v['type'] == 'levelup'){
					$data[$k]['orderstatus'] = 1;
				}elseif($v['orderid'] && !in_array($v['type'],['platform','salary'])){
					$data[$k]['orderstatus'] = Db::name($v['type'].'_order')->where('id',$v['orderid'])->value('status');
				}
				if($v['frommid']){
					$frommember = Db::name('member')->where('id',$v['frommid'])->find();
					if($frommember){
						$data[$k]['fromheadimg'] = $frommember['headimg'];
						$data[$k]['fromnickname'] = $frommember['nickname'];
					}
				}
                $data[$k]['score'] = dd_money_format($v['score'],$score_weishu);
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
    }
	//佣金记录导出
	public function recordexcel(){
		if(input('param.field') && input('param.order')){
			$order = 'member_credit_record.'.input('param.field').' '.input('param.order');
		}else{
			$order = 'member_credit_record.id desc';
		}
		$where = [];
		$where[] = ['member_credit_record.aid','=',aid];
        $where[] = ['member_credit_record.status','in',[0,1]];
		
		if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
		if(input('param.mid')) $where[] = ['member_credit_record.mid','=',trim(input('param.mid'))];
		if(input('?param.status') && input('param.status')!=='') $where[] = ['member_credit_record.status','=',input('param.status')];
		$list = Db::name('member_credit_record')->alias('member_credit_record')->field('member.nickname,member.headimg,member_credit_record.*')->join('member member','member.id=member_credit_record.mid')->where($where)->order($order)->select()->toArray();
		$title = array();
		$title[] = t('会员').'信息';
		$title[] = '佣金';
		$title[] = '积分';
		$title[] = '状态';
		$title[] = '产生时间';
		$title[] = '发放时间';
		$title[] = '备注';
		$data = array();
		foreach($list as $v){
			$tdata = array();
			$tdata[] = $v['nickname'].'('.t('会员').'ID:'.$v['mid'].')';
			$tdata[] = $v['commission'] ? $v['commission'] : 0;
			$tdata[] = $v['score'] ? $v['score'] : 0;
			$tdata[] = $v['status']==0 ? '未发放' : '已发放';
			$tdata[] = date('Y-m-d H:i:s',$v['createtime']);
			$tdata[] = $v['endtime'] ? date('Y-m-d H:i:s',$v['endtime']) : '';
			$tdata[] = $v['remark'];
			$data[] = $tdata;
		}
		$this->export_excel($title,$data);
	}
	//佣金记录删除
	public function recorddel(){
		$ids = input('post.ids/a');
		Db::name('member_credit_record')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('删除佣金记录'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}


	
	
	
	
	
		//积分明细
    public function credit1log(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = 'admin_credit1log.'.input('param.field').' '.input('param.order');
			}else{
				$order = 'admin_credit1log.id desc';
			}
			$where = array();
			$where[] = ['admin_credit1log.aid','=',aid];
			
			if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
			if(input('param.mid')) $where[] = ['admin_credit1log.mid','=',trim(input('param.mid'))];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['admin_credit1log.status','=',input('param.status')];
			$count = 0 + Db::name('admin_credit1log')->alias('admin_credit1log')->field('member.nickname,member.headimg,admin_credit1log.*')->leftjoin('member member','member.id=admin_credit1log.mid')->where($where)->count();
			$data = Db::name('admin_credit1log')->alias('admin_credit1log')->field('member.nickname,member.headimg,admin_credit1log.*')->leftjoin('member member','member.id=admin_credit1log.mid')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
    }
	//积分明细导出
	public function credit1logexcel(){
		if(input('param.field') && input('param.order')){
			$order = 'admin_credit1log.'.input('param.field').' '.input('param.order');
		}else{
			$order = 'admin_credit1log.id desc';
		}
		$where = array();
		$where[] = ['admin_credit1log.aid','=',aid];
		if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
		if(input('param.mid')) $where[] = ['admin_credit1log.mid','=',trim(input('param.mid'))];
		if(input('?param.status') && input('param.status')!=='') $where[] = ['admin_credit1log.status','=',input('param.status')];
		$list = Db::name('admin_credit1log')->alias('admin_credit1log')->field('member.nickname,member.headimg,admin_credit1log.*')->join('member member','member.id=admin_credit1log.mid')->where($where)->order($order)->select()->toArray();
		$title = array();
		$title[] = t('会员').'信息';
		$title[] = '变更';
		$title[] = '变更后剩余';
		$title[] = '变更时间';
		$title[] = '备注';
		$data = array();
		foreach($list as $v){
			$tdata = array();
			$tdata[] = $v['nickname'].'('.t('会员').'ID:'.$v['mid'].')';
			$tdata[] = $v['credit1'];
			$tdata[] = $v['after'];
			$tdata[] = date('Y-m-d H:i:s',$v['createtime']);
			$tdata[] = $v['remark'];
			$data[] = $tdata;
		}
		$this->export_excel($title,$data);
	}
	public function credit1logdel(){
		$ids = input('post.ids/a');
		Db::name('admin_credit1log')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog(t('资金池').'明细删除'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
		
		//积分明细
    public function credit2log(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = 'admin_credit2log.'.input('param.field').' '.input('param.order');
			}else{
				$order = 'admin_credit2log.id desc';
			}
			$where = array();
			$where[] = ['admin_credit2log.aid','=',aid];
			
			if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
			if(input('param.mid')) $where[] = ['admin_credit2log.mid','=',trim(input('param.mid'))];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['admin_credit2log.status','=',input('param.status')];
			$count = 0 + Db::name('admin_credit2log')->alias('admin_credit2log')->field('member.nickname,member.headimg,admin_credit2log.*')->join('member member','member.id=admin_credit2log.mid')->where($where)->count();
			$data = Db::name('admin_credit2log')->alias('admin_credit2log')->field('member.nickname,member.headimg,admin_credit2log.*')->join('member member','member.id=admin_credit2log.mid')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
    }
	//积分明细导出
	public function credit2logexcel(){
		if(input('param.field') && input('param.order')){
			$order = 'admin_credit2log.'.input('param.field').' '.input('param.order');
		}else{
			$order = 'admin_credit2log.id desc';
		}
		$where = array();
		$where[] = ['admin_credit2log.aid','=',aid];
		if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
		if(input('param.mid')) $where[] = ['admin_credit2log.mid','=',trim(input('param.mid'))];
		if(input('?param.status') && input('param.status')!=='') $where[] = ['admin_credit2log.status','=',input('param.status')];
		$list = Db::name('admin_credit2log')->alias('admin_credit2log')->field('member.nickname,member.headimg,admin_credit2log.*')->join('member member','member.id=admin_credit2log.mid')->where($where)->order($order)->select()->toArray();
		$title = array();
		$title[] = t('会员').'信息';
		$title[] = '变更';
		$title[] = '变更后剩余';
		$title[] = '变更时间';
		$title[] = '备注';
		$data = array();
		foreach($list as $v){
			$tdata = array();
			$tdata[] = $v['nickname'].'('.t('会员').'ID:'.$v['mid'].')';
			$tdata[] = $v['credit2'];
			$tdata[] = $v['after'];
			$tdata[] = date('Y-m-d H:i:s',$v['createtime']);
			$tdata[] = $v['remark'];
			$data[] = $tdata;
		}
		$this->export_excel($title,$data);
	}
	public function credit2logdel(){
		$ids = input('post.ids/a');
		Db::name('admin_credit2log')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog(t('慈善基金会').'明细删除'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	

		//积分明细
    public function index(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = 'admin_credit1log.'.input('param.field').' '.input('param.order');
			}else{
				$order = 'admin_credit1log.id desc';
			}
			$where = array();
			$where[] = ['admin_credit1log.aid','=',aid];
			
			if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
			if(input('param.mid')) $where[] = ['admin_credit1log.mid','=',trim(input('param.mid'))];
			if(input('?param.type') && input('param.type')!=='') $where[] = ['admin_credit1log.type','=',input('param.type')];
			$count = 0 + Db::name('admin_credit1log')->alias('admin_credit1log')->field('member.nickname,member.headimg,admin_credit1log.*')->leftjoin('member member','member.id=admin_credit1log.mid')->where($where)->count();
			$data = Db::name('admin_credit1log')->alias('admin_credit1log')->field('member.nickname,member.headimg,admin_credit1log.*')->leftjoin('member member','member.id=admin_credit1log.mid')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
    }
	//积分明细导出
	public function indexexcel(){
		if(input('param.field') && input('param.order')){
			$order = 'admin_credit1log.'.input('param.field').' '.input('param.order');
		}else{
			$order = 'admin_credit1log.id desc';
		}
		$where = array();
		$where[] = ['admin_credit1log.aid','=',aid];
		if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
		if(input('param.mid')) $where[] = ['admin_credit1log.mid','=',trim(input('param.mid'))];
		if(input('?param.type') && input('param.type')!=='') $where[] = ['admin_credit1log.type','=',input('param.type')];
		$list = Db::name('admin_credit1log')->alias('admin_credit1log')->field('member.nickname,member.headimg,admin_credit1log.*')->leftjoin('member member','member.id=admin_credit1log.mid')->where($where)->order($order)->select()->toArray();
		$title = array();
		$title[] = t('会员').'信息';
		$title[] = '变更';
		$title[] = '变更后剩余';
		$title[] = '变更时间';
		$title[] = '备注';
		$data = array();
		foreach($list as $v){
			$tdata = array();
			$tdata[] = $v['nickname'].'('.t('会员').'ID:'.$v['mid'].')';
			$tdata[] = $v['credit'];
			$tdata[] = $v['after'];
			$tdata[] = date('Y-m-d H:i:s',$v['createtime']);
			$tdata[] = $v['remark'];
			$data[] = $tdata;
		}
		$this->export_excel($title,$data);
	}
	public function indexdel(){
	     $islock= Db::name('admin_set')->where('aid',aid)->value('islock');
		if ($islock==1) 	return json(['status'=>0,'msg'=>'数据锁定中，禁用删除功能']);
		$ids = input('post.ids/a');
		Db::name('admin_credit1log')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('合伙人底删除'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	//积分明细
    public function uplog(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'id desc';
			}
			$where = array();
			$where[] = ['aid','=',aid];
			$count = 0 + Db::name('member_up_credit1log')->where($where)->count();
			$data = Db::name('member_up_credit1log')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
    }
    public function  uplogdel(){
	     $islock= Db::name('admin_set')->where('aid',aid)->value('islock');
		 if ($islock==1) 	return json(['status'=>0,'msg'=>'数据锁定中，禁用删除功能']);
		$ids = input('post.ids/a');
		Db::name('member_up_credit1log')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog(t('credit1').'涨价明细删除'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
			  	//充值积分
	public function addcredit1(){
		$money = input('post.credit1');
		$remark = input('post.remark');
		if($money == 0){
			return json(['status'=>0,'msg'=>'请输入增加金额数']);
		}
		$rs = \app\commons\Admin::addcredit1(aid,0,$money,'后台修改：'.$remark);
		if($rs['status']==0) return json($rs);
		\app\commons\System::plog('增加'.t('资金池').$money);
		return json(['status'=>1,'msg'=>'操作成功']);
	}

		//积分明细
    public function creditlog(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = 'admin_credit1log.'.input('param.field').' '.input('param.order');
			}else{
				$order = 'admin_credit1log.id desc';
			}
			$where = array();
			$where[] = ['admin_credit1log.aid','=',aid];
			
			if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
			if(input('param.mid')) $where[] = ['admin_credit1log.mid','=',trim(input('param.mid'))];
// 			if(input('?param.type') && input('param.type')!=='') $where[] = ['admin_credit1log.type','=',input('param.type')];
			$count = 0 + Db::name('admin_credit1log')->alias('admin_credit1log')->field('member.nickname,member.headimg,admin_credit1log.*')->leftjoin('member member','member.id=admin_credit1log.mid')->where($where)->count();
			$data = Db::name('admin_credit1log')->alias('admin_credit1log')->field('member.nickname,member.headimg,admin_credit1log.*')->leftjoin('member member','member.id=admin_credit1log.mid')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
    }
	//积分明细导出
	public function creditlogexcel(){
		if(input('param.field') && input('param.order')){
			$order = 'admin_credit1log.'.input('param.field').' '.input('param.order');
		}else{
			$order = 'admin_credit1log.id desc';
		}
		$where = array();
		$where[] = ['admin_credit1log.aid','=',aid];
		if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
		if(input('param.mid')) $where[] = ['admin_credit1log.mid','=',trim(input('param.mid'))];
// 		if(input('?param.type') && input('param.type')!=='') $where[] = ['admin_credit1log.type','=',input('param.type')];
		$list = Db::name('admin_credit1log')->alias('admin_credit1log')->field('member.nickname,member.headimg,admin_credit1log.*')->leftjoin('member member','member.id=admin_credit1log.mid')->where($where)->order($order)->select()->toArray();
		$title = array();
		$title[] = t('会员').'信息';
		$title[] = '变更资金池';
		$title[] = '变更后剩余';
		$title[] = '变更时间';
		$title[] = '备注';
		$data = array();
		foreach($list as $v){
			$tdata = array();
			$tdata[] = $v['nickname'].'('.t('会员').'ID:'.$v['mid'].')';
			$tdata[] = $v['credit'];
			$tdata[] = $v['after'];
			$tdata[] = date('Y-m-d H:i:s',$v['createtime']);
			$tdata[] = $v['remark'];
			$data[] = $tdata;
		}
		$this->export_excel($title,$data);
	}
	public function creditlogdel(){
	     $islock= Db::name('admin_set')->where('aid',aid)->value('islock');
		    if ($islock==1) 	return json(['status'=>0,'msg'=>'数据锁定中，禁用删除功能']);
		$ids = input('post.ids/a');
		Db::name('admin_credit1log')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('资金池明细删除'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}

}
