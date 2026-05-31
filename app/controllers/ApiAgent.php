<?php


/*分销*/
namespace app\controllers;
use think\facade\Db;
class ApiAgent extends ApiCommon
{
	public function initialize(){
		parent::initialize();
		$this->checklogin();
	}
	public function statistics(){
        $st = input('param.st');
        $level = Db::name('member_level')->where('id', $this->member['levelid'])->find();
        if(empty($level['tongji_yeji']) || $level['tongji_yeji'] != 1) {
            $datalist = [];
            return $this->json(['status'=>1,'data'=>$datalist]);
        }
        $pernum = 15;
        $pagenum = input('post.pagenum');
        if(!$pagenum) $pagenum = 1;
        $date = strtotime(date('Y-m-d'));
        for ($i=($pagenum-1)*$pernum;$i<$pagenum*$pernum;$i++) {
            $start = $date-$i*86400;
            $end = $start + 86399;
            $where = [];
            $where[] = ['aid','=',aid];
            
            $where[] = ['status','in',[1,2,3]];
            $where[] = ['createtime','between',[$start,$end]];
            $num = Db::name('shop_order_goods')->where($where)->sum('tongji_1');
            $datalist[] = [
                'i'=>$i,
                'date' => date('Y-m-d',$start),
                'num' => $num
            ];
        }
        if(!$datalist) $datalist = [];
		if($pagenum == 1){
		    $total['count0'] =Db::name('shop_order_goods')->where('aid','=',aid)->where('status','in',[1,2,3])->sum('tongji_1');
		}
		return $this->json(['status'=>1,'data'=>$datalist,'total'=>$total,]);
	
	}
	
	public function commissionTransfer() {
         $mid = input('param.mid/d',0);
            //给他人充值 转账
            //        $info = Db::name('member_recharge')->where('aid', aid)->where('mid', mid)->find();
//        if(empty($info)) {
//            return $this->json(['status'=>0,'msg'=>'您无此权限操作']);
//        }
        $set = Db::name('admin_set')->where('aid',aid)->find();
        if($set['commission_transfer'] != 1) {
            return $this->json(['status'=>0,'msg'=>'未开启此功能']);
        }
        if(request()->isPost()){
            $mobile = input('post.mobile');
            $mid = input('post.mid/d');
            $commission = input('post.commission/f');
            if ($commission < 0.01){
                return $this->json(['status'=>0,'msg'=>'请输入正确的金额，最小金额为：0.01']);
            }
            if($this->sysset['transfer_rate']>0 ){
        	    $transfer_rate = $this->sysset['transfer_rate'];
		    	if($commission%$transfer_rate!=0)return $this->json(['status'=>0,'msg'=>'金额需是'.$transfer_rate.'的倍数']);
			}
            if (input('?post.mobile') && !empty($mobile)) {
                $member = Db::name('member')->where('aid', aid)->where('tel', $mobile)->find();
            }
            if (input('?post.mid') && $mid > 0) {
                $member = Db::name('member')->where('aid', aid)->where('id', $mid)->find();
            }
            if(!$member) return $this->json(['status'=>0,'msg'=>'未找到该'.t('会员')]);
            $user_id = $member['id'];

            if ($user_id == mid) {
                return $this->json(['status'=>0,'msg'=>'不能转账给自己']);
            }
            if($set['transfer_range'] == 1) {
                //所有上下级
                $isparent = false;
                if(in_array($user_id,explode(',',$this->member['path']))){
                    $isparent = true;
                }
                if(!$isparent){
                    if(!in_array(mid,explode(',',$member['path']))){
                        return $this->json(['status'=>0,'msg'=>'仅限转账给上下级'.t('会员')]);
                    }
                }
            }
            
            
            $commission_transfer_fee = round($commission*$set['commission_transfer_fee']*0.01,2);
			if ($this->sysset['transfer_fee_type']==1) {
			   $tocommission = round($commission - $commission_transfer_fee,2);
			} else {
			   $tocommission = $commission;
			   $commission = round($commission + $commission_transfer_fee,2);
			}
		
            if ($commission > $this->member['commission']){
                return $this->json(['status'=>0,'msg'=>'您的'.t('佣金').'不足']);
            }
            //验证支付密码
            $pwd_check = $set['transfer_pwd'];
            if($pwd_check){
                if(!$this->member['paypwd']){
                    return $this->json(['status'=>0,'msg'=>'请先设置支付密码','set_paypwd'=>1]);
                }
                $pay_pwd = input('paypwd')?:'';
                if(!\app\commons\Member::checkPayPwd($this->member,$pay_pwd )){
                    return $this->json(['status'=>0,'msg'=>'支付密码输入错误']);
                }
            }
            $midMsg = sprintf("转账给：%s",$member['nickname']);
            $toMidMsg = sprintf("来自%s的转账", $this->member["nickname"]);
            if ($set['transfer_range'] == 2) {
                $midMsg = sprintf("转给：%s",$member['nickname']);
                $toMidMsg = sprintf("来自%s的转账", $this->member["nickname"]);
            }
			$rs = \app\commons\Member::addcommission(aid,mid,0,$commission * -1, $midMsg,0);
            if ($rs['status'] == 1) {
                \app\commons\Member::addcommission(aid,$user_id,0,$tocommission,$toMidMsg,0);
            }else{
				 return $this->json(['status'=>0, 'msg' => '转账失败']);
			}
            return $this->json(['status'=>1, 'msg' => '转账成功', 'url'=>'/pages/my/usercenter']);
        }
        $tomember = [];
        if($mid){
            $tomember = Db::name('member')->where('aid',aid)->where('id',$mid)->field('id,commission,nickname,headimg')->find();
        }
        if($this->member['paypwd']=='') {
			$rdata['haspwd'] = 1;
		} else {
			$rdata['haspwd'] = 0;
		}
        $rdata['paycheck'] = $set['transfer_pwd'] ? true : false;
        $rdata['status'] = 1;
        $rdata['mycommission'] = $this->member['commission'];
        $rdata['commissionList'] = [];//可选金额列表
        $rdata['tomember'] = $tomember?$tomember:['nickname'=>''];//转给谁
       
        $rdata['transfer_type'] = $set['transfer_type'] ? explode(',',$set['transfer_type']) : [];
        $rdata['commission_transfer_fee'] =$set['commission_transfer_fee'];
        return $this->json($rdata);
	}
	
	
	
	
	
	
	public function commissionTransferold() {
		$set = Db::name('admin_set')->where('aid',aid)->find();
		if($set['commission_transfer'] != 1) {
			return $this->json(['status'=>0,'msg'=>'未开启此功能']);
		}
		if(request()->isPost()) {
			$mobile = input('post.mobile');
			$mid = input('post.mid/d');
			$acc = input('post.acc');
			$commission = input('post.commission/f');
			if ($commission < 0.01) {
				return $this->json(['status'=>0,'msg'=>'请输入正确的金额，最小金额为：0.01']);
			}
			if($this->sysset['transfer_rate']>0 ){
        	    $transfer_rate = $this->sysset['transfer_rate'];
		    	if($commission%$transfer_rate!=0)return $this->json(['status'=>0,'msg'=>'金额需是'.$transfer_rate.'的倍数']);
			}
			if (input('?post.mobile')) {
				$member = Db::name('member')->where('aid', aid)->where('tel', $mobile)->find();
			}
			if (input('?post.acc')) {
				$member = Db::name('member')->where('aid', aid)->where('acc', $acc)->find();
			}
			if (input('?post.mid')) {
				$member = Db::name('member')->where('aid', aid)->where('id', $mid)->find();
			}
			if(!$member) return $this->json(['status'=>0,'msg'=>'未找到该'.t('会员')]);
			$user_id = $member['id'];
			if ($user_id == mid) {
				return $this->json(['status'=>0,'msg'=>'不能转账给自己']);
			}
			if($set['transfer_range'] == 1) {
                //所有上下级
                $isparent = false;
                if(in_array($user_id,explode(',',$this->member['path']))){
                    $isparent = true;
                }
                if(!$isparent){
                    if(!in_array(mid,explode(',',$member['path']))){
                        return $this->json(['status'=>0,'msg'=>'仅限转账给上下级'.t('会员')]);
                    }
                }
            }
            //验证支付密码
            $pwd_check = $this->sysset['money_pwd'];
            if($pwd_check){
                if(!$this->member['paypwd']){
                    return $this->json(['status'=>0,'msg'=>'请先设置支付密码','set_paypwd'=>1]);
                }
                $pay_pwd = input('paypwd')?:'';
                if(!\app\commons\Member::checkPayPwd($this->member,$pay_pwd )){
                    return $this->json(['status'=>0,'msg'=>'支付密码输入错误']);
                }
            }
			$commission_transfer_fee = round($commission*$set['commission_transfer_fee']*0.01,2);
			if ($this->sysset['transfer_fee_type']==1) {
			   $tocommission = round($commission - $commission_transfer_fee,2);
			} else {
			   $tocommission = $commission;
			   $commission = round($commission + $commission_transfer_fee,2);
			}
			if ($commission > $this->member['commission']) {
				return $this->json(['status'=>0,'msg'=>'您的'.t('佣金').'不足']);
			}
// 			$smscode = input('post.code');
// 			if( md5($this->member['tel'].'-'.$smscode) != cache($this->sessionid.'_smscode') || cache($this->sessionid.'_smscodetimes')>5) {
// 				cache($this->sessionid.'_smscodetimes',cache($this->sessionid.'_smscodetimes')+1);
// 				return $this->json(['status'=>0,'msg'=>'短信验证码错误']);
// 			}
// 			cache(input('param.session_id').'_smscode',null);
// 			cache(input('param.session_id').'_smscodetimes',null);
			//验证支付密码
			
			$rs = \app\commons\Member::addcommission(aid,mid,0,$commission * -1, sprintf("转账给：%s",$member['nickname']));
			if ($rs['status'] == 1) {
				\app\commons\Member::addcommission(aid,$user_id,0,$tocommission,sprintf("来自%s的转账", $this->member["nickname"]),0);
			} else {
				return $this->json(['status'=>0, 'msg' => '转账失败']);
			}
			return $this->json(['status'=>1, 'msg' => '转账成功', 'url'=>m_url('my/usercenter').'/aid/'.aid]);
		}
		 if($this->member['paypwd']=='') {
			$userinfo['haspwd'] = 0;
		} else {
			$userinfo['haspwd'] = 1;
		}
		$userinfo['transfer_type'] = $this->sysset['transfer_type'];
		$userinfo['paycheck'] = $this->sysset['money_pwd'];
		$userinfo['tel'] = $this->member['tel'];
		
		$rdata['userinfo'] = $userinfo;
		$rdata['commission_transfer_fee'] = $set['commission_transfer_fee'];
		$rdata['status'] = 1;
		$rdata['mycommission'] = $this->member['commission'];
		return $this->json($rdata);
	}
	
	public function commissionSurvey(){
	     $field = 'id,headimg,nickname,pid,commission,totalcommission,buymoney,levelid';
	     if(getcustom('product_baodan')){
             $field .= ',baodan_freeze';
         }
	     if(getcustom('fenhong_max')){
             $field .= ',fenhong_max';
         }
        if(getcustom('business_fenxiao')){
            $field .= ',disable_withdraw';
        }
        if(getcustom('yx_team_yeji')){
            $field .= ',total_team_yeji_fenhong';
        }
		$userinfo = Db::name('member')->field($field)->where('aid',aid)->where('id',mid)->find();
		
		$count = 0 + Db::name('member_commission_withdrawlog')->where('aid',aid)->where('mid',mid)->sum('txmoney');
		$count0 = 0 + Db::name('member_commission_withdrawlog')->where('aid',aid)->where('mid',mid)->where('status',0)->sum('txmoney');
		$count1 = 0 + Db::name('member_commission_withdrawlog')->where('aid',aid)->where('mid',mid)->where('status',1)->sum('txmoney');
		$count2 = 0 + Db::name('member_commission_withdrawlog')->where('aid',aid)->where('mid',mid)->where('status',2)->sum('txmoney');
		$count3 = 0 + Db::name('member_commission_withdrawlog')->where('aid',aid)->where('mid',mid)->where('status',3)->sum('txmoney');
		if($userinfo){
			$countdqr1 = Db::query("select sum(parent1commission)c from ".table_name('shop_order_goods')." og left join ".table_name('shop_order')." o on o.id=og.orderid where og.aid=".aid." and (o.status=1 or o.status=2) and og.parent1={$userinfo['id']}");
			$countdqr2 = Db::query("select sum(parent2commission)c from ".table_name('shop_order_goods')." og left join ".table_name('shop_order')." o on o.id=og.orderid where og.aid=".aid." and (o.status=1 or o.status=2) and og.parent2={$userinfo['id']}");
			$countdqr3 = Db::query("select sum(parent3commission)c from ".table_name('shop_order_goods')." og left join ".table_name('shop_order')." o on o.id=og.orderid where og.aid=".aid." and (o.status=1 or o.status=2) and og.parent3={$userinfo['id']}");
		}
		if($userinfo['pid']){
			$userinfo['pnickname'] = Db::name('member')->where('aid',aid)->where('id',$userinfo['pid'])->value('nickname');
		}
		$countdqr = 0 + $countdqr1[0]['c'] + $countdqr2[0]['c'] + $countdqr3[0]['c'];
		
		$count = number_format($count,2,'.','');
		$count0 = number_format($count0,2,'.','');
		$count1 = number_format($count1,2,'.','');
		$count2 = number_format($count2,2,'.','');
		$count3 = number_format($count3,2,'.','');
		$countdqr = number_format($countdqr,2,'.','');

		$set = Db::name('admin_set')->where('aid',aid)->find();

		//是否显示分红订单
		$levelinfo = Db::name('member_level')->where('id',$this->member['levelid'])->find();
		$levelRecord = Db::name('member_level_record')->where('mid',mid)->select();
        $levelids = $levelRecord->column('levelid');
        if($levelids){
            $levelExtend = Db::name('member_level')->whereIn('id',$levelids);
        }

		$showfenhong = false;
		$hasfenhong = false;
		$hasteamfenhong = false;
		$hasareafenhong = false;
        $hasYeji = false;
		if($levelinfo['fenhong'] > 0){ //有股东分红
            $showfenhong = true;
			$hasfenhong = true;
		}
        if($levelids && $levelExtend->where('fenhong', '>', 0)->count()) {
            $showfenhong = true;
			$hasfenhong = true;
        }
        $hasfenhong_huiben = false;
        if(getcustom('fenhong_gudong_huiben')){
            if($levelinfo['fenhong_huiben'] > 0){ //有股东分红
                $showfenhong = true;
                $hasfenhong_huiben = true;
            }
            if($levelids && $levelExtend->where('fenhong_huiben', '>', 0)->count()) {
                $showfenhong = true;
                $hasfenhong_huiben = true;
            }
        }
		if($levelinfo['teamfenhonglv'] > 0 && ($levelinfo['teamfenhongbl'] > 0 || $levelinfo['teamfenhong_money'] > 0)){ //有团队分红
			$showfenhong = true;
			$hasteamfenhong = true;
		}
        if($levelinfo['teamfenhong_pingji_lv'] > 0 && ($levelinfo['teamfenhong_pingji_bl'] > 0 || $levelinfo['teamfenhong_pingji_money'] > 0)){ //有团队分红
            $showfenhong = true;
            $hasteamfenhong = true;
        }
        if(getcustom('zhitui_pj')){
            if($levelinfo['teamfenhonglv'] > 0){
                $showfenhong = true;
                $hasteamfenhong = true;
            }
        }
        if(getcustom('teamfenhong_jicha')){
            $showfenhong = true;
            $hasteamfenhong = true;
        }
        if(getcustom('team_leader_fh')){
            $showfenhong = true;
            $hasteamfenhong = true;
        }
        if($levelinfo['product_teamfenhong_ids'] != '' && $levelinfo['product_teamfenhonglv'] > 0 && ($levelinfo['product_teamfenhong_money'] > 0)){ //有商品团队分红
            $showfenhong = true;
            $hasteamfenhong = true;
        }
        if(!empty($levelinfo['level_teamfenhong_ids']) && $levelinfo['level_teamfenhonglv'] > 0 && ($levelinfo['level_teamfenhongbl'] > 0 || $levelinfo['level_teamfenhong_money'] > 0)){ //有等级团队分红
            $showfenhong = true;
            $hasteamfenhong = true;
        }
        if(!empty($levelinfo['tongji_yeji']) && $levelinfo['tongji_yeji'] == 1) {
            $hasYeji = true;
        }
        if($levelids){
            $countfenhong = $levelExtend->where('teamfenhonglv', '>', 0)->where(function ($query) {
                $query->where('teamfenhongbl','>',0)->whereOr('teamfenhong_money','>',0)->whereOr('teamfenhong_pingji_bl','>',0)->whereOr('teamfenhong_pingji_money','>',0);
            })->count();
            if($countfenhong) {
                $showfenhong = true;
                $hasteamfenhong = true;
            }
        }
        if(getcustom('teamfenhong_shouyi')){
            $hasteamfenhong = true;
        }

		if($levelinfo['areafenhong'] > 0 && $levelinfo['areafenhongbl'] > 0){ //有区域代理分红
			$showfenhong = true;
			$hasareafenhong = true;
		}
        if($levelids && $levelExtend->where('areafenhong', '>', 0)->where('areafenhongbl', '>', 0)->count()) {
            $showfenhong = true;
			$hasareafenhong = true;
        }
		if($this->member['areafenhong'] > 0 && $this->member['areafenhongbl'] > 0){ //有单独设置的区域代理分红
			$showfenhong = true;
			$hasareafenhong = true;
		}
        if($levelRecord->where('areafenhong', '>', 0)->where('areafenhongbl', '>', 0)->count()) {
            $showfenhong = true;
			$hasareafenhong = true;
        }
        if(getcustom('plug_zhiming')) {
            $mendian_ids = Db::name('admin_user')->where('aid', aid)->where('mid', mid)->where('mdid', '>', 0)->column('mdid');
            if($mendian_ids) {
                $showMendianOrder = true;
            }
        }
        if(getcustom('member_show_buymoney')) {
            $set['show_myyeji'] = 1;
            $yejiwhere = [];
            $yejiwhere[] = ['status','in','1,2,3'];
            $starttime = strtotime(date('Y-m-01'));
            $endtime = time();
            $yejiwhere[] = ['createtime','between',[$starttime,$endtime]];
            $userinfo['buymoney_thismonth'] = Db::name('shop_order_goods')->where('aid',aid)->where('mid',mid)->where($yejiwhere)->sum('real_totalprice');
        }
        if(getcustom('fenhong_jiaquan_bylevel') && !$showfenhong) {
            $showfenhong = true;
        }
       
		//预计佣金
//		$commissionyj1 = Db::query("select sum(parent1commission)c from ".table_name('shop_order_goods')." og left join ".table_name('shop_order')." o on o.id=og.orderid where og.aid=".aid." and (o.status=1 or o.status=2 or o.status=3) and iscommission=0 and og.parent1=".mid."");
//		$commissionyj2 = Db::query("select sum(parent2commission)c from ".table_name('shop_order_goods')." og left join ".table_name('shop_order')." o on o.id=og.orderid where og.aid=".aid." and (o.status=1 or o.status=2 or o.status=3) and iscommission=0 and og.parent2=".mid."");
//		$commissionyj3 = Db::query("select sum(parent3commission)c from ".table_name('shop_order_goods')." og left join ".table_name('shop_order')." o on o.id=og.orderid where og.aid=".aid." and (o.status=1 or o.status=2 or o.status=3) and iscommission=0 and og.parent3=".mid."");
		$commissionyj_collage1 = Db::name('collage_order')->where('aid',aid)->where('parent1',mid)->whereIn('status',[1,2,3])->where('iscommission',0)->sum('parent1commission');
        $commissionyj_collage2 = Db::name('collage_order')->where('aid',aid)->where('parent2',mid)->whereIn('status',[1,2,3])->where('iscommission',0)->sum('parent2commission');
        $commissionyj_collage3 = Db::name('collage_order')->where('aid',aid)->where('parent3',mid)->whereIn('status',[1,2,3])->where('iscommission',0)->sum('parent3commission');
        $commissionyj_score1 = Db::name('scoreshop_order_goods')->where('aid',aid)->where('parent1',mid)->whereIn('status',[1,2,3])->where('iscommission',0)->sum('parent1commission');
        $commissionyj_score2 = Db::name('scoreshop_order_goods')->where('aid',aid)->where('parent2',mid)->whereIn('status',[1,2,3])->where('iscommission',0)->sum('parent2commission');
        $commissionyj_score3 = Db::name('scoreshop_order_goods')->where('aid',aid)->where('parent3',mid)->whereIn('status',[1,2,3])->where('iscommission',0)->sum('parent3commission');
        //ddwx_seckill_order 不存在iscommission字段
        $commissionyj_yuyue1 = Db::name('yuyue_order')->where('aid',aid)->where('parent1',mid)->whereIn('status',[1,2,3])->where('iscommission',0)->sum('parent1commission');
        $commissionyj_yuyue2 = Db::name('yuyue_order')->where('aid',aid)->where('parent2',mid)->whereIn('status',[1,2,3])->where('iscommission',0)->sum('parent2commission');
        $commissionyj_yuyue3 = Db::name('yuyue_order')->where('aid',aid)->where('parent3',mid)->whereIn('status',[1,2,3])->where('iscommission',0)->sum('parent3commission');
        $commissionyj_ke1 = Db::name('kecheng_order')->where('aid',aid)->where('parent1',mid)->whereIn('status',[1,2,3])->where('iscommission',0)->sum('parent1commission');
        $commissionyj_ke2 = Db::name('kecheng_order')->where('aid',aid)->where('parent2',mid)->whereIn('status',[1,2,3])->where('iscommission',0)->sum('parent2commission');
        $commissionyj_ke3 = Db::name('kecheng_order')->where('aid',aid)->where('parent3',mid)->whereIn('status',[1,2,3])->where('iscommission',0)->sum('parent3commission');
        $commissionyj_tuan1 = Db::name('tuangou_order')->where('aid',aid)->where('parent1',mid)->whereIn('status',[1,2,3])->where('iscommission',0)->sum('parent1commission');
        $commissionyj_tuan2 = Db::name('tuangou_order')->where('aid',aid)->where('parent2',mid)->whereIn('status',[1,2,3])->where('iscommission',0)->sum('parent2commission');
        $commissionyj_tuan3 = Db::name('tuangou_order')->where('aid',aid)->where('parent3',mid)->whereIn('status',[1,2,3])->where('iscommission',0)->sum('parent3commission');
        //todo 未插入record的其他佣金
//        $recordyj = Db::name('member_commission_record')->where('aid',aid)->where('mid',mid)->where('type','<>','shop')->where('status',0)->sum('commission');
        $recordyjshop = Db::name('member_commission_record')->alias('r')->leftJoin('shop_order o','r.orderid=o.id')->where('r.aid',aid)->where('r.mid',mid)->where('r.type','shop')->where('r.status',0)->where('o.status','in',[1,2,3])->sum('r.commission');
        $commissionyj = 0 /*+ $commissionyj1[0]['c'] + $commissionyj2[0]['c'] + $commissionyj3[0]['c']*/
            + $commissionyj_collage1 + $commissionyj_collage2 + $commissionyj_collage3
            + $commissionyj_score1 + $commissionyj_score2 + $commissionyj_score3
            + $commissionyj_yuyue1 + $commissionyj_yuyue2 + $commissionyj_yuyue3
            + $commissionyj_ke1 + $commissionyj_ke2 + $commissionyj_ke3
            + $commissionyj_tuan1 + $commissionyj_tuan2 + $commissionyj_tuan3
//            + $recordyj
            + $recordyjshop;
            if(getcustom('wx_channels')){
                $channels_status = [20,21,30,100];
                $commissionyj_channels1 = Db::name('channels_order_goods')
                    ->alias('og')
                    ->join('channels_order o','og.order_id=o.order_id')
                    ->where('o.aid',aid)->where('og.parent1',mid)->whereIn('o.status',$channels_status)->where('og.iscommission',0)->sum('og.parent1commission');
                $commissionyj_channels2 = Db::name('channels_order_goods')
                    ->alias('og')
                    ->join('channels_order o','og.order_id=o.order_id')
                    ->where('o.aid',aid)->where('og.parent2',mid)->whereIn('o.status',$channels_status)->where('og.iscommission',0)->sum('og.parent2commission');
                $commissionyj_channels3 = Db::name('channels_order_goods')
                    ->alias('og')
                    ->join('channels_order o','og.order_id=o.order_id')
                    ->where('o.aid',aid)->where('og.parent3',mid)->whereIn('o.status',$channels_status)->where('og.iscommission',0)->sum('og.parent3commission');
                $commissionyj = $commissionyj+$commissionyj_channels1+$commissionyj_channels2+$commissionyj_channels3;
            }
		$userinfo['fenhong'] = Db::name('member_fenhonglog')->where('aid',aid)->where('mid',mid)->where('type','fenhong')->sum('commission');
		$userinfo['areafenhong'] = Db::name('member_fenhonglog')->where('aid',aid)->where('mid',mid)->where('type','areafenhong')->sum('commission');
		$userinfo['teamfenhong'] = Db::name('member_fenhonglog')->where('aid',aid)->where('mid',mid)->where('type','teamfenhong')->sum('commission');

	
		$userinfo['commission_yj'] = $commissionyj;

		if($showfenhong){
			$rs = \app\commons\Fenhong::gdfenhong(aid,$this->sysset,[],0,time(),1,mid);
			$userinfo['fenhong_yj'] = $rs['commissionyj'];
			$userinfo['commission_yj'] += $userinfo['fenhong_yj'];
		}
        if($hasfenhong_huiben){
            $rs = \app\commons\Fenhong::gdfenhong_huiben(aid,$this->sysset,[],0,time(),1,mid);
            $userinfo['fenhong_yj_huiben'] = $rs['commissionyj'];
            $userinfo['commission_yj'] += $userinfo['fenhong_yj'];
            $userinfo['fenhong_huiben'] = Db::name('member_fenhonglog')->where('aid',aid)->where('mid',mid)->where('type','fenhong_huiben')->sum('commission');
        }
		if($hasteamfenhong){
			$rs1 = \app\commons\Fenhong::teamfenhong(aid,$this->sysset,[],0,time(),1,mid);
			$rs2 = \app\commons\Fenhong::product_teamfenhong(aid,$this->sysset,[],0,time(),1,mid);
			$rs3 = \app\commons\Fenhong::level_teamfenhong(aid,$this->sysset,[],0,time(),1,mid);
			$teamfenhong_yj = 0;
			if($rs1 && $rs1['oglist']){
				$teamfenhong_yj += $rs1['commissionyj'];
			}
			if($rs2 && $rs2['oglist']){
				$teamfenhong_yj += $rs2['commissionyj'];
			}
			if($rs3 && $rs3['oglist']){
				$teamfenhong_yj += $rs3['commissionyj'];
			}
            if(getcustom('teamfenhong_jicha')){
                $rs4 = \app\commons\Fenhong::teamfenhong_jicha(aid,$this->sysset,[],0,time(),1,mid);
                $teamfenhong_yj += $rs4['commissionyj'];
            }
            if(getcustom('team_leader_fh')){
                $rs5 = \app\commons\Fenhong::teamleader_fenhong(aid,$this->sysset,[],0,time(),1,mid);
                $teamfenhong_yj += $rs5['commissionyj'];
            }
			$userinfo['teamfenhong_yj'] = $teamfenhong_yj;
			$userinfo['commission_yj'] += $userinfo['teamfenhong_yj'];
		}
		if($hasareafenhong){
			$rs = \app\commons\Fenhong::areafenhong(aid,$this->sysset,[],0,time(),1,mid);
			$userinfo['areafenhong_yj'] = $rs['commissionyj'];
			$userinfo['commission_yj'] += $userinfo['areafenhong_yj'];
		}
        $hastouzifenhong = false;
        if(getcustom('touzi_fenhong')) {
            $shareholder = Db::name('shareholder')->where('aid',aid)->where('status',1)->where('mid',mid)->find();
            $fhrs = \app\commons\Fenhong::touzi_fenhong(aid,$this->sysset,[],0,time(),1,mid);
            $userinfo['touzifenhong_yj'] = $fhrs['commissionyj'];
            $userinfo['commission_yj'] += $userinfo['touzifenhong_yj'];
            $touzimoney = 0+ Db::name('shareholder')->where('aid',aid)->where('status',1)->where('mid',mid)-> sum('money');
            $userinfo['touzimoney'] =$touzimoney;
            if($shareholder || $fhrs['commissionyj'] || $userinfo['touzifenhong_yj']){
                $hastouzifenhong = true;
            }  
            if($shareholder){
                $userinfo['touzifenhong'] = Db::name('member_fenhonglog')->where('aid',aid)->where('mid',mid)->where('type','touzi_fenhong')->sum('commission');
            }
        }

		if($set['teamyeji_show']==1){ //团队业绩
			//总业绩
			$yejiwhere = [];
            $yejiwhere[] = ['status','in','1,2,3'];

			$totalyeji = 0;
			$downmids = \app\commons\Member::getteammids(aid,mid);
			$teamyeji = Db::name('shop_order_goods')->where('aid',aid)->where('mid','in',$downmids)->where($yejiwhere)->sum('real_totalprice');
            $teamOrderCount = Db::name('shop_order')->where('aid',aid)->where('mid','in',$downmids)->where($yejiwhere)->count();

            if(getcustom('member_show_teamyeji')){
                $set['show_teamyeji_search'] = 1;
                $starttime = strtotime(date('Y-m-01'));
                $endtime = time();
                $yejiwhere[] = ['createtime','between',[$starttime,$endtime]];
                $userinfo['teamyeji_thismonth'] = Db::name('shop_order_goods')->where('aid',aid)->where('mid','in',$downmids)->where($yejiwhere)->sum('real_totalprice');
            }
		}
		if($set['teamnum_show']==1){ //团队总人数
			if($set['teamyeji_show']!=1){
				$downmids = \app\commons\Member::getteammids(aid,mid);
			}
			$teamnum = count($downmids);
		}
		$gongxianfenhong_show = 0;
		if($set['gongxianfenhong_show'] == 1){
			$pergxcommon = 0;
			if($set['partner_gongxian']==1 && $levelinfo['fenhong'] > 0 && $levelinfo['fenhong_gongxian_percent'] > 0){
				$gongxianfenhong_show = 1;

				
				$orderwhere = [];
				$orderwhere[] = ['aid','=',aid];
				$orderwhere[] = ['isfenhong','=',0];
				//if($set['fhjiesuantime_type'] == 1){
				//	$orderwhere[] = ['status','in','1,2,3'];
				//}else{
				//	$orderwhere[] = ['status','=','3'];
				//}
				$orderwhere[] = ['status','in','1,2,3'];
				$real_totalprice = Db::name('shop_order_goods')->where($orderwhere)->sum('real_totalprice');
				if($set['fhjiesuantime_type'] == 0){
					$fenhongprice = $real_totalprice;
				}else{
					$cost_price = Db::name('shop_order_goods')->where($orderwhere)->sum('cost_price');
					$num = Db::name('shop_order_goods')->where($orderwhere)->sum('num');
					$fenhongprice = $real_totalprice - $cost_price * $num;
				}

				$fhlevellist = Db::name('member_level')->where('aid',aid)->where('fenhong','>','0')->order('sort desc,id desc')->column('id,cid,name,fenhong,fenhong_num,fenhong_max_money,sort,fenhong_gongxian_minyeji,fenhong_gongxian_percent','id');
				$lastmidlist = [];
				$total_fenhong_partner = $this->member['total_fenhong_partner'];
				$commission = 0;
				foreach($fhlevellist as $fhlevel){
					$where = [];
					$where[] = ['aid', '=', aid];
					$where[] = ['levelid', '=', $fhlevel['id']];
					$where2 = [];
					$where2[] = ['ml.aid', '=', aid];
					$where2[] = ['ml.levelid', '=', $fhlevel['id']];
					if ($fhlevel['fenhong_max_money'] > 0) {
						$where[] = ['total_fenhong_partner', '<', $fhlevel['fenhong_max_money']];
						$where2[] = ['m.total_fenhong_partner', '<', $fhlevel['fenhong_max_money']];
					}
					if ($defaultCid > 0 && $defaultCid != $fhlevel['cid']) {

					} else {
						if ($fhlevel['fenhong_num'] > 0) {
							$midlist = Db::name('member')->where($where)->order('levelstarttime,id')->limit(intval($fhlevel['fenhong_num']))->column('id,total_fenhong_partner', 'id');
						} else {
							$midlist = Db::name('member')->where($where)->column('id,total_fenhong_partner', 'id');
						}
					}
					
					if($this->sysset['partner_jiaquan'] == 1){
						$oldmidlist = $midlist;
						$midlist = array_merge((array)$lastmidlist,(array)$midlist);
						$lastmidlist = array_merge((array)$lastmidlist,(array)$oldmidlist);
					}

					if (!$midlist) continue;
					
					//股东贡献量分红 开启后可设置一定比例的分红金额按照股东的团队业绩量分红
					$pergxcommon = 0;
					if($this->sysset['partner_gongxian']==1 && $fhlevel['fenhong_gongxian_percent'] > 0){
						$gongxian_percent = $fhlevel['fenhong'] * $fhlevel['fenhong_gongxian_percent']*0.01;
						$fhlevel['fenhong'] = $fhlevel['fenhong'] * (1 - $fhlevel['fenhong_gongxian_percent']*0.01);
						$gongxianCommissionTotal = $gongxian_percent * $fenhongprice * 0.01;
						//总业绩
						//$levelids = Db::name('member_level')->where('aid',aid)->where('sort','<',$fhlevel['sort'])->column('id');
//						$levelids = Db::name('member_level')->where('aid',aid)->column('id');
						$yejiwhere = [];
						$yejiwhere[] = ['isfenhong','=',0];
						//if($fhjiesuantime_type == 1) {
							$yejiwhere[] = ['status','in','1,2,3'];
						//}else{
						//	$yejiwhere[] = ['status','=','3'];
						//}
						$totalyeji = 0;
						foreach($midlist as $kk=>$item){
							$downmids = \app\commons\Member::getteammids(aid,$item['id']);
							$yeji = Db::name('shop_order')->where('aid',aid)->where('mid','in',$downmids)->where($yejiwhere)->sum('totalprice');
							$midlist[$kk]['yeji'] = $yeji;
							$totalyeji += $yeji;
						}
						if($totalyeji > 0){
							$pergxcommon = $gongxianCommissionTotal / $totalyeji;
						}else{
							$pergxcommon = 0;
						}
					}

					$fenhongmoney = $fhlevel['fenhong'] * $fenhongprice * 0.01 / count($midlist);//平均分给此等级的会员
					foreach($midlist as $item){
						if($item['id'] == mid){
							$gxcommon = 0;
							if($pergxcommon > 0){
								if($item['yeji'] >= $fhlevel['fenhong_gongxian_minyeji']){
									$gxcommon = $item['yeji'] * $pergxcommon;
								}
							}
							$commission += $gxcommon;
							if ($fhlevel['fenhong_max_money'] > 0) {
								if ($fenhongmoney + $total_fenhong_partner > $fhlevel['fenhong_max_money']) {
									$fenhongmoney = $fhlevel['fenhong_max_money'] - $total_fenhong_partner;
								}
								$total_fenhong_partner += $fenhongmoney;//总分红增加
							}
							//$commission += $fenhongmoney;
							break;
						}
					}
				}

				$gongxianfenhong = $commission;
			}
		}
        $moeny_weishu = 2;
        if(getcustom('fenhong_money_weishu')){
            $moeny_weishu = Db::name('admin_set')->where('aid',aid)->value('fenhong_money_weishu');
        }
        $moeny_weishu = $moeny_weishu?$moeny_weishu:2;
        $userinfo['fenhong'] = dd_money_format($userinfo['fenhong'],$moeny_weishu);
        $userinfo['areafenhong'] = dd_money_format($userinfo['areafenhong'],$moeny_weishu);
        $userinfo['teamfenhong'] = dd_money_format($userinfo['teamfenhong'],$moeny_weishu);
        if(getcustom('touzi_fenhong')) {
            $userinfo['touzifenhong'] = dd_money_format($userinfo['touzifenhong'], $moeny_weishu);
        }
        $userinfo['fenhong_yj'] = dd_money_format($userinfo['fenhong_yj'],$moeny_weishu);
        $userinfo['areafenhong_yj'] =dd_money_format($userinfo['areafenhong_yj'],$moeny_weishu);
        $userinfo['teamfenhong_yj'] =dd_money_format($userinfo['teamfenhong_yj'],$moeny_weishu);
        $userinfo['touzifenhong_yj'] = dd_money_format($userinfo['touzifenhong_yj'],$moeny_weishu);
        $userinfo['commission_yj'] =  dd_money_format($userinfo['commission_yj'],$moeny_weishu);
        $userinfo['commission'] =  dd_money_format($userinfo['commission'],$moeny_weishu);
        $userinfo['totalcommission'] =  dd_money_format($userinfo['totalcommission'],$moeny_weishu);
        if(getcustom('member_totalcommission')){
            $userinfo['show_totalcommission'] =true;
        }
        if(getcustom('product_baodan')){
            $userinfo['baodan_freeze'] =  dd_money_format($userinfo['baodan_freeze'],$moeny_weishu);
        }
        //分红上限
        $userinfo['fenhong_max_show'] = 0;

        if(getcustom('fenhong_max') ){
            $userinfo['fenhong_max_show'] = 1;
            if($userinfo['fenhong_max']<=0){
                $sysset = Db::name('admin_set')->where('aid',aid)->find();
                $level_info = Db::name('member_level')->where('id',$userinfo['levelid'])->where('aid',aid)->find();
                $fenhong_max_money = $level_info['fenhong_max_money'];
                if(!empty($sysset['fenhong_max_add'])){
                    $down_max_money = Db::name('member_level')
                        ->where('aid',aid)
                        ->where('sort','<',$level_info['sort'])
                        ->sum('fenhong_max_money');
                    $fenhong_max_money = bcadd($fenhong_max_money,$down_max_money,2);
                }
                $userinfo['fenhong_max'] = $fenhong_max_money;
            }

            //查询会员已获得股东分红
            $where_fenhong = [];
            $where_fenhong[] = ['mid','=',$userinfo['id']];
            $where_fenhong[] = ['type','=','fenhong'];
            $gudong_name = t('股东分红',aid);
            $fenhong_total = Db::name('member_fenhonglog')
                ->where($where_fenhong)
                ->where('remark like "%'.$gudong_name.'%"')
                ->sum('commission');
            $userinfo['gudong_total'] = bcmul($fenhong_total,1,2);
            $userinfo['gudong_remain'] = bcsub($userinfo['fenhong_max'],$userinfo['gudong_total'],2);
        }
		//卡片
        $rsset = [];
        if(getcustom('agent_card')){
            if($set['agent_card'] == 1){
                $agentCard2 = Db::name('admin')->where('id',aid)->value('agent_card');
                if($agentCard2 == 1){
                    $count = \db('member_levelup_order')->where('aid',aid)->where('mid',mid)->where('status',2)->count();
                    if($count)
                        $rsset['agent_card'] = 1;
                }
            }
        }
        if(getcustom('product_price_rate')){
            $rsset['product_price_rate'] = 1;
        }

        if(getcustom('agent_card') && getcustom('member_level_price_rate')){
            $agentCard = Db::name('admin_set')->where('aid',aid)->value('agent_card');
            if($agentCard == 1) {
                $agentCard2 = Db::name('admin')->where('id', aid)->value('agent_card');
                if ($agentCard2 == 1) {
                    //并且申请过会员等级
                    $lorder = Db::name('member_levelup_order')->where('aid',aid)->where('mid',mid)->where('status',2)->count();
                    if($lorder){
                        $rsset['member_level_price_rate'] = 1;
                    }
                }
            }
        }

        if(!getcustom('agent_card') && getcustom('member_level_price_rate')){
            //并且申请过会员等级
            $lorder = Db::name('member_levelup_order')->where('aid',aid)->where('mid',mid)->where('status',2)->count();
            if($lorder){
                $rsset['member_level_price_rate'] = 1;
            }
        }

        $rsset['teamshouyi_show'] = 0;
        $userinfo['teamshouyi'] = 0;
        $hasteamshouyi = false;
        if(getcustom('teamfenhong_shouyi')){
            $hasteamshouyi = true;
            $rsset['teamshouyi_show'] = 1;
            $userinfo['teamshouyi'] = Db::name('member_fenhonglog')->where('aid',aid)->where('mid',mid)->where('type','teamfenhong_shouyi')->sum('commission');
        }
        $showxiaoshouyeji = false;
        if(getcustom('coupon_xianxia_buy')){
            $showxiaoshouyeji = true;
            $yeji_reward_data = json_decode($level_info['yeji_reward_data'],true);
            $res = \app\commons\Member::xianxiaYeji(aid,$this->member,$yeji_reward_data);
           
            $userinfo['totalyeji'] = $res['totalyeji'];
            $userinfo['shouyi'] = $res['shouyi'];
            $userinfo['coupon_yeji'] = $res['coupon_yeji'];//销售额
            $zt_commission = Db::name('member_commissionlog')->where('mid',mid)->where('remark','like','直推奖%')->sum('commission');
            $yeji_commission = Db::name('member_commissionlog')->where('mid',mid)->where('remark','业绩奖')->sum('commission');
            $userinfo['showxiaoshouyeji'] = $showxiaoshouyeji;
            $userinfo['zt_commission'] = $zt_commission;
            $userinfo['yeji_commission'] = $yeji_commission;
        }
        $rsset['parent_show'] = $set['parent_show'];
        $rsset['teamfenhong_show'] = $set['teamfenhong_show'];
        $rsset['commissionlog_show'] = $set['commissionlog_show'];
        $rsset['commissionrecord_show'] = $set['commissionrecord_show'];
        $rsset['fhorder_show'] = $set['fhorder_show'];
        $rsset['fhlog_show'] = $set['fhlog_show'];
        $rsset['show_myyeji'] = $set['show_myyeji'];
        $rsset['show_teamyeji_search'] = $set['show_teamyeji_search'];
		$rsset['tjbusiness_show'] = $set['tjbusiness_show'];
		$rsset['fxorder_show'] = $set['fxorder_show'];
        $rsset['commission_transfer'] = $set['commission_transfer'];






        $rsset['business_teamfenhong_show'] = false;
        $hasbusinessteamfenhong = false;
        if(getcustom('business_teamfenhong') || getcustom('business_teamfenhong_pj')){
            if($levelinfo['business_teamfenhonglv'] > 0 && $levelinfo['business_teamfenhongbl']){ //有商家团队分红
                $hasbusinessteamfenhong = true;
            }
            if($levelinfo['business_teamfenhonglv_pj'] > 0 && $levelinfo['business_teamfenhongbl_pj']){ //有商家团队分红
                $hasbusinessteamfenhong = true;
            }
            $rsset['business_teamfenhong_show'] = $set['business_teamfenhong_show'];
            $userinfo['business_teamfenhong'] = Db::name('member_fenhonglog')->where('aid',aid)->where('mid',mid)->where('type','business_teamfenhong')->sum('commission');

            $rs1 = \app\commons\Fenhong::business_teamfenhong(aid,$this->sysset,[],0,time(),1,mid);
            $teamfenhong_yj = 0;
            if($rs1 && $rs1['oglist']){
                $teamfenhong_yj += $rs1['commissionyj'];
            }
            $userinfo['business_teamfenhong_yj'] = $teamfenhong_yj;
        }
        if(getcustom('business_fenxiao') && $userinfo['disable_withdraw']){
            //已冻结佣金提现
            $set['comwithdraw'] = 0;
            $set['commission2money'] = 0;
        }

		$rdata = [];
		$rdata['count'] = $count;
		$rdata['count1'] = $count1;
		$rdata['count2'] = $count2;
		$rdata['count3'] = dd_money_format($count3,$moeny_weishu);
		$rdata['count0'] = $count0;
		$rdata['countdqr'] = $countdqr;
		$rdata['comwithdraw'] = $set['comwithdraw'];
		$rdata['comwithdrawmin'] = $set['comwithdrawmin'];
		$rdata['commission2money'] = $set['commission2money'];
        if(getcustom('commission_tomoney_need_score') && $set['comtomoney_need_score']){
            $rdata['commission_money_exchange_num'] = $set['commission_money_exchange_num']??0;
        }
        if(getcustom('commission_index_hide_withdraw')){
            $rdata['hide_withdraw_btn'] = true;
        }

		$rdata['fxjiesuantime'] = $set['fxjiesuantime'];
		$rdata['showfenhong'] = $showfenhong;
		$rdata['hasfenhong'] = $hasfenhong;
        $rdata['hasfenhong_huiben'] = $hasfenhong_huiben;
		$rdata['hasteamfenhong'] = $hasteamfenhong;
		$rdata['hasareafenhong'] = $hasareafenhong;
        $rdata['showMendianOrder'] = $showMendianOrder;
        $rdata['hastouzifenhong'] = $hastouzifenhong;
        $rdata['hasteamshouyi'] = $hasteamshouyi;
        $rdata['hasYeji'] = $hasYeji;
        $rdata['teamyeji_show'] = $set['teamyeji_show'];
        $rdata['set'] = $rsset;
		if($rdata['teamyeji_show'] == 1){
			$userinfo['teamyeji'] = round($teamyeji,2);
            $userinfo['teamOrderCount'] = $teamOrderCount;
		}
        $rdata['teamnum_show'] = $set['teamnum_show'];
		if($rdata['teamnum_show'] == 1){
			$userinfo['teamnum'] = $teamnum;
		}
        $rdata['gongxianfenhong_show'] = $gongxianfenhong_show;
		if($rdata['gongxianfenhong_show']==1){
			$userinfo['gongxianfenhong'] = round($gongxianfenhong,2);
			$userinfo['gongxianfenhong_txt'] = $set['gongxianfenhong_txt'];
		}
        $rdata['commission_butie'] = false;
        if(getcustom('commission_butie')){
            $rdata['commission_butie'] = true;
            //待发放补贴
            $butie_total = Db::name('member_commission_butie')->where('aid',aid)->where('mid',mid)->sum('remain');
            //已发放补贴
            $butie_send = Db::name('member_commission_butie')->where('aid',aid)->where('mid',mid)->sum('have_send');
            $userinfo['butie_total'] = $butie_total?:0;
            $userinfo['butie_send'] = $butie_send?:0;
        }
       
       
        if(getcustom('yx_team_yeji')){
            $yeji_set = Db::name('team_yeji_set')->where('aid',aid)->find();
            $sysset =Db::name('admin_set')->where('aid',aid)->find();
            $yeji_set_config =json_decode($yeji_set['config_data'],true);
            $teamyeji_fenhong = \app\commons\Order::teamyejiJiangli($this->member,$yeji_set,$sysset,$yeji_set_config);
            $userinfo['team_yeji_fenhong_yj'] = $teamyeji_fenhong;
            $show_levelid = array_keys($yeji_set_config);
            $userinfo['show_team_yeji_fenhong'] = false;
            if(in_array($this->member['levelid'],$show_levelid)){
                $userinfo['show_team_yeji_fenhong'] = true;
            }
        }
		$rdata['userinfo'] = $userinfo;
		$rdata['hasbusinessteamfenhong'] = $hasbusinessteamfenhong;
		
		$rdata['uplv_agree'] = 0;
		if(getcustom('up_level_agree2') && $levelinfo['is_agree'] == 1){
            $level_agree = Db::name('member_level_agree')->where('aid',aid)->where('mid',mid)->where('newlv_id',$levelinfo['id'])->find();
			if(!$level_agree || $level_agree['status'] == 0){
				$rdata['uplv_agree'] = 1;
				$rdata['agree_content'] = $levelinfo['agree_content'];
			}
		}

		return $this->json($rdata);
	}
	//佣金转余额
	public function commission2money(){
        try{
            Db::startTrans();
            $post = input('post.');
            $set = Db::name('admin_set')->where('aid',aid)->find();
            if($set['commission2money'] !=1){
                return $this->json(['status'=>0,'msg'=>'该功能未启用']);
            }
            $money = floatval($post['money']);
            $member = Db::name('member')->where('aid',aid)->where('id',mid)->lock(true)->find();
            if($money <= 0 || $money > $member['commission']){
                return $this->json(['status'=>0,'msg'=>'转入金额不正确']);
            }
            //是不是需要消耗积分
            if(getcustom('commission_tomoney_need_score') && $set['comtomoney_need_score']){
                $needScoreNum = $set['commission_money_exchange_num']?$set['commission_money_exchange_num']*$money:0;
                if($needScoreNum>0){
                    if($needScoreNum>$member['score']){
                        Db::rollback();
                        return $this->json(['status'=>0,'msg'=>t('积分').'不足']);
                    }
                    \app\commons\Member::addscore(aid,mid,-$needScoreNum,t('佣金').'转'.t('余额').'消耗');
                }
            }
            \app\commons\Member::addcommission(aid,mid,0,-$money,t('佣金').'转'.t('余额'));
            \app\commons\Member::addmoney(aid,mid,$money,t('佣金').'转'.t('余额'));
            Db::commit();
        }catch(Exception $e){
            Db::rollback();
        }
		
		return $this->json(['status'=>1,'msg'=>'转入成功']);
	}
	//佣金明细
	public function commissionlog(){
		$st = input('param.st');
        $type = input('param.type');
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['mid','=',mid];
		if(input('param.keyword')) $where[] = ['remark', 'like', '%'.input('param.keyword').'%'];
		$pernum = 20;
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		if($st ==1){//提现记录
			$datalist = Db::name('member_commission_withdrawlog')->field("id,money,txmoney,`status`,from_unixtime(createtime)createtime,reason")->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		}else{ //佣金明细
            if($type ==1) {
                $where[] = ['remark','=','投资分红'];
            }
            $moeny_weishu =2;
            if(getcustom('fenhong_money_weishu')){
                $moeny_weishu = Db::name('admin_set')->where('aid',aid)->value('fenhong_money_weishu');
            }
            $moeny_weishu =$moeny_weishu?$moeny_weishu:2;
			$datalist = Db::name('member_commissionlog')->field("id,commission money,`after`,from_unixtime(createtime)createtime,remark,service_fee")->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
            foreach ($datalist as $key=>$val){
                $datalist[$key]['commission'] = dd_money_format($val['commission'],$moeny_weishu);
                $datalist[$key]['money'] = dd_money_format($val['money'],$moeny_weishu);
                $datalist[$key]['after'] = dd_money_format($val['after'],$moeny_weishu);
                $datalist[$key]['service_fee'] = dd_money_format($val['service_fee'],$moeny_weishu);
            }
		}
		if(!$datalist) $datalist = [];

        if($pagenum == 1) {
            $field = 'aid,name,logo';
            if(getcustom('commission_service_fee')) {
                $field .= ',commission_service_fee_show';
            }

            $set = Db::name('admin_set')->field($field)->where('aid', aid)->find();
        }

		return $this->json(['status'=>1,'data'=>$datalist,'set'=>$set?$set:[]]);
	}
	
	//佣金记录
    public function commissionrecord(){
		$pernum = 20;
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;

		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['mid','=',mid];
		$st = input('param.st');
		if($st == 0){
			$where[] = ['status','in','0,1'];
		}elseif($st == 1){
			$where[] = ['status','=',1];
		}elseif($st == 2){
			$where[] = ['status','=',0];
		}

		$datalist = Db::name('member_commission_record')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		if(!$datalist) $datalist = [];
		foreach($datalist as $k=>$v){
			if($v['type'] == 'levelup'){
				$datalist[$k]['orderstatus'] = 1;
			}elseif ($v['type']=='fenhong_copies'){
                $datalist[$k]['orderstatus'] = 3;

            }else{
				$datalist[$k]['orderstatus'] = Db::name($v['type'].'_order')->where('id',$v['orderid'])->value('status');
			}
			if($v['frommid']){
				$frommember = Db::name('member')->where('id',$v['frommid'])->find();
				if($frommember){
					$datalist[$k]['fromheadimg'] = $frommember['headimg'];
					$datalist[$k]['fromnickname'] = $frommember['nickname'];
				}else{
					$datalist[$k]['fromheadimg'] = '';
					$datalist[$k]['fromnickname'] = '';
				}
			}
			if(is_null($v['commission'])){
				if(!is_null($v['score'])){
					$commission = $v['score'];
				}else{
					$commission = '0';
				}
			}else{
				$commission = $v['commission'];
			}
			$datalist[$k]['commission'] = $commission;
            $goods = [];
            if($v['type'] =='car_hailing'){
                if(getcustom('car_hailing')){
                    $goods = Db::name('car_hailing_product')->where('aid',$v['aid'])->where('id',$v['ogid'])->field('name,pic')->find();
                    $order = Db::name('car_hailing_order')->where('aid',$v['aid'])->where('id',$v['orderid'])->field('createtime,num,product_price')->find();
                    $goods['createtime'] = date('Y-m-d H:i',$order['createtime']);
                    $goods['num'] = $order['num'];
                    $goods['sell_price'] = $order['product_price'];
                }
            }elseif(in_array($v['type'],['collage','lucky_collage'])){
                $goods = Db::name($v['type'].'_product')->where('aid',$v['aid'])->where('id',$v['ogid'])->field('name,pic')->find();
                $order = Db::name($v['type'].'_order')->where('aid',$v['aid'])->where('id',$v['orderid'])->field('createtime,num,sell_price')->find();
                $goods['createtime'] = date('Y-m-d H:i',$order['createtime']);
                $goods['num'] = $order['num'];
                $goods['sell_price'] = $order['sell_price'];
            }elseif(in_array($v['type'],['shop','scoreshop','restaurant_shop','restaurant_takeaway'])){
                $order_goods = Db::name($v['type'].'_order_goods')
                    ->where('aid',$v['aid'])
                    ->where('id',$v['ogid'])
                    ->field('createtime,num,proid,sell_price')
                    ->find();
                if($order_goods){
                    $goods = Db::name($v['type'].'_product')->where('aid',$v['aid'])->where('id',$order_goods['proid'])->field('name,pic')->find();
                    $goods['createtime'] = date('Y-m-d H:i',$order_goods['createtime']);
                    $goods['num'] = $order_goods['num'];
                    $goods['sell_price'] = $order_goods['sell_price'];
                }
            }
            $datalist[$k]['goods'] = $goods;
		}
		return $this->json(['status'=>1,'data'=>$datalist]);
    }
	//佣金提现
	public function commissionWithdraw(){
		$field = 'withdraw_autotransfer,comwithdraw,comwithdrawmin,comwithdrawfee,comwithdrawbl,comwithdrawdate,withdraw_weixin,withdraw_aliaccount,withdraw_bankcard,withdraw_desc,comwithdrawmax,commissionrecord_withdrawlog_show,day_withdraw_num';
		if(getcustom('alipay_auto_transfer')){
			$field .= ',ali_withdraw_autotransfer';
		}
        if(getcustom('pay_adapay')){
            $field .= ',withdraw_adapay';
        }
        if(getcustom('commission_withdraw_need_score')){
            $field .= ',comwithdraw_need_score,commission_score_exchange_num';
        }
        if(getcustom('transfer_farsion')){
            $field .= ',withdraw_bankcard_xiaoetong,withdraw_aliaccount_xiaoetong';
        }
        if(getcustom('pay_huifu')){
            $field .= ',withdraw_huifu';
        }
		if(getcustom('commission_withdraw_freeze')){
			$field .= ',comwithdraw_freeze,comwithdraw_totalmoney';
		}
		if(getcustom('commission_duipeng_score_withdraw')){
			$field .= ',comwithdraw_integer_type,comwithdraw_duipeng_score_bili';
		}
		if(getcustom('commission_withdraw_level_sxf')){
			$field .= ',comwithdrawfee_level';
		}
		if(getcustom('product_givetongzheng')){
		    $field .= ',commissionwithdraw2tongzheng';
        }

        if(getcustom('withdraw_mul')){
          $field .= ',comwithdrawmul';
        }
        if(getcustom('extend_linghuoxin')){
            $field .= ',withdraw_aliaccount_linghuoxin,withdraw_bankcard_linghuoxin';
        }
        if(getcustom('withdraw_paycode')){
            $field .= ',withdraw_paycode';
        }
		$set = Db::name('admin_set')->where('aid',aid)->field($field)->find();
        if($set['comwithdraw'] == 0){
            return $this->json(['status'=>-4,'msg'=>t('佣金').'提现功能未开启']);
        }

        $memberlevel = Db::name('member_level')->where('id',$this->member['levelid'])->find();
        if($memberlevel && $memberlevel['comwithdraw'] == 0){
            return $this->json(['status'=>-4,'msg'=>t('佣金').'提现未开启','url'=>'/activity/commission/index']);
        }

        if(getcustom('member_realname_verify')) {
            $realname_set = Db::name('member_realname_set')->where('aid', aid)->find();
            if ($realname_set['status'] == 1 && $realname_set['withdraw_status'] == 0 && $this->member['realname_status'] != 1){
                return $this->json(['status'=>-4,'msg'=>'未实名认证不可提现','url'=>'/pagesExt/my/setrealname']);
            }
        }
        if(getcustom('transfer_farsion') && ($set['withdraw_bankcard_xiaoetong'] == 1 || $set['withdraw_aliaccount_xiaoetong'] == 1)){
			$xetService = new  \app\commons\Xiaoetong();
			$res_sign = $xetService->getXiaoetongSigning();
			if ($res_sign['status'] == 0){
                return $this->json(['status'=>-4,'msg'=>'需要签约才可提现','url'=>'/pagesA/my/withdrawXiaoetong']);
            }
		}
        $field = 'id,headimg,nickname,money,score,commission,aliaccount,bankname,bankcarduser,bankcardnum';
        $show_cash_count = 0;
        if(getcustom('cashback_yongjin')){
            $field .= ',cash_yongji_total,cashback_total';
            $show_cash_count = 1;
        }
        if(getcustom('business_fenxiao')){
            $field .= ',disable_withdraw';
        }
        if(getcustom('pay_huifu')){
            $field .= ',realname,usercard,usercard_begin_date,bank_province_code,bank_city_code,tel';
        }
		if(getcustom('commission_withdraw_freeze')){
			$field .= ',iscomwithdraw_freeze';
		}
		if(getcustom('member_lock')){
			$field .= ',lock_withdraw_givemoney';
		}
        if(getcustom('commission_duipeng_score_withdraw')){
			$field .= ',commission_withdraw_score';
		}
        $userinfo = Db::name('member')->where('id',mid)->field($field)->find();
        if(getcustom('business_fenxiao')){
            $bid = input('bid');
            $map = [];
            $map[] = ['aid','=',aid];
            $map[] = ['mid','=',mid];
            if($bid){
                $map[] = ['bid','=',$bid];
                $bonus_total = Db::name('business_fenxiao_bonus_total')->where($map)->value('remain');
                if($bonus_total>$userinfo['commission']){
                    $bonus_total = $userinfo['commission'];
                }
                $userinfo['commission'] = $bonus_total?:0;
            }else{
                $bonus_total = Db::name('business_fenxiao_bonus_total')->where($map)->sum('remain');
                $bonus_total = $bonus_total?:0;
                $userinfo['commission'] = bcsub($userinfo['commission'],$bonus_total,2);
            }
            if($userinfo['disable_withdraw']){
                //已冻结佣金提现
                $userinfo['commission'] = 0;
            }
        }

		if(getcustom('commission_withdraw_level_sxf')){
			$comwithdrawfee_level = json_decode($set['comwithdrawfee_level'],true);
			$set['comwithdrawfee'] = $comwithdrawfee_level[$memberlevel['id']]['sxf'];
		}



		if(request()->isPost()){
			$post = input('post.');
            Db::startTrans();
            $userinfo = Db::name('member')->where('id',mid)->field($field)->lock(true)->find();
            //验证今天提现了几次
            $nowtime = strtotime(date("Y-m-d",time()));//今日时间戳
            $daywithdrawnum   = 'daywithdrawnum'.mid.$nowtime;//会员今日时间参数
            $day_withdraw_num = cache($daywithdrawnum);//获取会员提现次数
            if($set['day_withdraw_num']<0){
                return $this->json(['status'=>0,'msg'=>'暂时不可提现']);
            }else if($set['day_withdraw_num']>0){
                if($day_withdraw_num && !empty($day_withdraw_num)){
                    $daynum = $day_withdraw_num+1;
                    if($daynum>$set['day_withdraw_num']){
                        return $this->json(['status'=>0,'msg'=>'今日申请提现次数已满，请明天继续申请提现']);
                    }
                }
            }
            
            
            
            
            
            
            if(getcustom('member_lock') && $userinfo['lock_withdraw_givemoney'] == 1){
				return $this->json(['status'=>0,'msg'=>'账号已锁定，请联系管理员处理！']);
			}

			if(getcustom('business_fenxiao') && $userinfo['disable_withdraw']){
                //已冻结佣金提现
                return $this->json(['status'=>0,'msg'=>'提现功能被冻结，请联系管理员处理！']);
            }
            if(getcustom('commission_withdraw_freeze') && $userinfo['iscomwithdraw_freeze']==1){
                //已冻结佣金提现
                return $this->json(['status'=>0,'msg'=>'提现已冻结，请购买商品解冻']);
            }

			if(getcustom('comwithdrawdate') && $set['comwithdrawdate'] && $set['comwithdrawdate']!='0'){
				$comwithdrawdate = explode(',',$set['comwithdrawdate']);
				$indate = false;
				$nowdata = date('d');
				foreach($comwithdrawdate as $date){
					if($date == $nowdata || '0'.$date == $nowdata){
						$indate = true; 
						break;
					}
				}
				if(!$indate) return $this->json(['status'=>0,'msg'=>'不在可提现日期内']);
			}
			
			if(getcustom('forcerebuy')){
				if($this->member['commission_isfreeze'] == 1){
					$forcerebuy = Db::name('forcerebuy')->where('aid',aid)->where('wfgtype',0)->where('status',1)->where("find_in_set('-1',gettj) or find_in_set('".$this->member['levelid']."',gettj)")->find();
					if($forcerebuy){
						return $this->json(['status'=>0,'msg'=>$forcerebuy['wfgtxtips']]);
					}else{
						return $this->json(['status'=>0,'msg'=>'对不起！你需要复购，才能解冻']);
					}
				}
			}
            if(empty($post['paytype'])){
				return $this->json(['status'=>0,'msg'=>'请选择提现方式']);
			}
            if(getcustom('transfer_farsion')){
				if($post['paytype']=='小额通支付宝'){
					if(!$this->member['aliaccount'] || !$this->member['aliaccountname']){
						return $this->json(['status'=>0,'msg'=>'请先设置支付宝账号']);
					}
				}
				if($post['paytype']=='小额通银行卡' && ($this->member['bankname']==''||$this->member['bankcarduser']==''||$this->member['bankcardnum']=='')){
					if($set['withdraw_bankcard'] == 0)
						return $this->json(['status'=>0,'msg'=>'银行卡提现功能未开启']);
						return $this->json(['status'=>0,'msg'=>'请先设置完整银行卡信息']);
				}
			}
            if(getcustom('extend_linghuoxin')){
                if($post['paytype']=='灵活薪支付宝' || $post['paytype']=='灵活薪银行卡'){
                    if($post['paytype']=='灵活薪支付宝'){
                        if($set['withdraw_aliaccount_linghuoxin'] != 1){
                            return $this->json(['status'=>0,'msg'=>'灵活薪支付宝提现功能未开启']);
                        }
                        if(empty($this->member['aliaccount']) || empty($this->member['aliaccountname'])){
                            return $this->json(['status'=>0,'msg'=>'请先设置支付宝账号']);
                        }
                    }
                    if($post['paytype']=='灵活薪银行卡'){
                        if($set['withdraw_bankcard_linghuoxin'] != 1){
                            return $this->json(['status'=>0,'msg'=>'灵活薪银行卡提现功能未开启']);
                        }
                        if(empty($this->member['bankname']) || empty($this->member['bankcarduser'])|| empty($this->member['bankcardnum'])){
                            return $this->json(['status'=>0,'msg'=>'请先设置完整银行卡信息']);
                        }
                    }
                    //查看是否签约
                    if(!empty($this->member['usercard'])){
                        $getchecksign = \app\customs\LinghuoxinCustom::getchecksign(aid,0,$this->member['usercard']);
                        if($getchecksign && $getchecksign['status'] == 1){
                            if($getchecksign['data']['status'] == 0){
                                return $this->json(['status'=>-4,'msg'=>'需要签约才可提现','url'=>'/pagesB/my/linghuoxinsign']);
                            }else if($getchecksign['data']['status'] == 1){
                                return $this->json(['status'=>0,'msg'=>'已实名认证，等待签约中']);
                            }
                        }else{
                            //return $this->json($getchecksign);
                            return $this->json(['status'=>-4,'msg'=>'需要签约才可提现','url'=>'/pagesB/my/linghuoxinsign']);
                        }
                    }else{
                        return $this->json(['status'=>-4,'msg'=>'需要签约才可提现','url'=>'/pagesB/my/linghuoxinsign']);
                    }
                }
            }
            if(getcustom('withdraw_paycode')){
                if($post['paytype']=='收款码'){
                    if(!$this->member['wxpaycode'] && !$this->member['alipaycode']){
                        return $this->json(['status'=>0,'msg'=>'请先设置一个收款码']);
                    }
                }
            }
			
			if($post['paytype']=='支付宝' && $this->member['aliaccount']==''){
				return $this->json(['status'=>0,'msg'=>'请先设置支付宝账号']);
			}

			if(getcustom('yx_gift_pack')){
				$bank = Db::name('member_bank')->where('aid',aid)->where('mid',mid)->where('isdefault',1)->find();
				if($post['paytype']=='银行卡' && !$bank){
					return $this->json(['status'=>0,'msg'=>'请先设置完整银行卡信息']);
				}
				$this->member['bankname'] = $bank['bankname'];
				$this->member['bankcarduser'] = $bank['bankcarduser'];
				$this->member['bankcardnum'] = $bank['bankcardnum'];
				$this->member['bankaddress'] = $bank['bankaddress'];
			}else{
				if($post['paytype']=='银行卡' && ($this->member['bankname']==''||$this->member['bankcarduser']==''||$this->member['bankcardnum']=='')){
					return $this->json(['status'=>0,'msg'=>'请先设置完整银行卡信息']);
				}
			}
            if($post['paytype']=='银行卡' && $set['withdraw_huifu'] == 1 && ($this->member['realname']==''||$this->member['tel']==''||$this->member['usercard']==''||$this->member['huifu_id']==''||$this->member['bankname']==''||$this->member['bankcarduser']==''||$this->member['bankcardnum']=='')){
                return $this->json(['status'=>0,'msg'=>'请先设置完整银行卡信息']);
            }








			$money = $post['money'];
			if($money<=0 || $money < $set['comwithdrawmin']){
				return $this->json(['status'=>0,'msg'=>'提现金额必须大于'.($set['comwithdrawmin']?$set['comwithdrawmin']:0)]);
			}
			   if(getcustom('withdraw_mul')){
        if($set['comwithdrawmul']>0 && !isMulInt($money, $set['comwithdrawmul'])){
          return $this->json(['status'=>0,'msg'=>'提现金额必须为'.$set['comwithdrawmul'].'整数倍']);
        }
      }
			if($money > $userinfo['commission']){
				return $this->json(['status'=>0,'msg'=>'可提现'.t('余额').'不足']);
			}
			if($this->sysset['withdraw_rate']>0 ){
        	    $withdraw_rate = $this->sysset['withdraw_rate'];
		    	if($money%$withdraw_rate!=0)return $this->json(['status'=>0,'msg'=>'金额需是'.$withdraw_rate.'的倍数']);
			}
			
			$pwd_check = $this->sysset['money_pwd'];;
			if($pwd_check) {
				if(!$this->member['paypwd']) {
					return $this->json(['status'=>0,'msg'=>'请先设置支付密码','set_paypwd'=>1]);
				}
				$pay_pwd = input('paypwd')?:'';
				if(!\app\commons\Member::checkPayPwd($this->member,$pay_pwd )) {
					return $this->json(['status'=>0,'msg'=>'支付密码输入错误']);
				}
			}
			
            //如果需要消耗积分的 则取积分可兑换的最小值
            $needScoreNum = 0;
            if(getcustom('commission_withdraw_need_score') && $set['comwithdraw_need_score']==1){
                $exchange_score_num = $set['commission_score_exchange_num']??1;
                $scoreExchangeCommission = $this->member['score']/$exchange_score_num;
                $canWithdrawNum = min($scoreExchangeCommission,$this->member['commission']);
                if($money>$canWithdrawNum){
                    return $this->json(['status'=>0,'msg'=>t('积分').'不足，最多可提现'.$canWithdrawNum]);
                }
                $needScoreNum = $money*$exchange_score_num;
            }
			if($set['comwithdrawmax']>0 && $money > $set['comwithdrawmax']){
				return $this->json(['status'=>0,'msg'=>'提现金额过大，单笔'.t('余额').'提现最高金额为'.$set['comwithdrawmax'].'元']);
			}

            //对碰同比减少提现积分
            if(getcustom('commission_duipeng_score_withdraw')){
                if ($set['comwithdraw_integer_type']) {
                    $comwithdraw_integer_type = $set['comwithdraw_integer_type']; 
                    if($comwithdraw_integer_type == 1 && floor($money) != $money){			  
                        return $this->json(['status'=>0,'msg'=>'请输入整数']);
                    }else if($comwithdraw_integer_type == 2 && ($money % 10) != 0){
                        return $this->json(['status'=>0,'msg'=>'请输入10整倍整数']);
                    }else if($comwithdraw_integer_type == 3 && ($money % 100) != 0){
                        return $this->json(['status'=>0,'msg'=>'请输入100整倍整数']);
                    }else if($comwithdraw_integer_type == 4 && ($money % 1000) != 0){
                        return $this->json(['status'=>0,'msg'=>'请输入1000整倍整数']);
                    }
                }
                if($set['comwithdraw_duipeng_score_bili'] > 0){
                    $commission_withdraw_score = $userinfo['commission_withdraw_score'];
                    $use_commission_withdraw_score = $commission_withdraw_score / $set['comwithdraw_duipeng_score_bili'];
                    $need_commission_withdraw_score = ceil($money * $set['comwithdraw_duipeng_score_bili']);
                    if($use_commission_withdraw_score < $money){
                        $use_money = $money;
                        if($comwithdraw_integer_type == 1){
                            $use_money =  floor($use_commission_withdraw_score);
                        }else if($comwithdraw_integer_type == 2){
                            $use_money =  floor($use_commission_withdraw_score/10) * 10;
                        }else if($comwithdraw_integer_type == 3){
                            $use_money =  floor($use_commission_withdraw_score/100) * 100;
                        }else if($comwithdraw_integer_type == 4){
                            $use_money =  floor($use_commission_withdraw_score/1000) * 1000;
                        }
                        return $this->json(['status'=>0,'msg'=>'提现积分不足，最高可提现金额为'.$use_money.'元']);
                      }
                }
            }
			//验证小数点后两位
            $money_arr = explode('.',$money);
            if($money_arr && $money_arr[1]){
                $dot_len = strlen($money_arr[1]);
                if($dot_len>2){
                    return $this->json(['status'=>0,'msg'=>'提现金额最小位数为小数点后两位']);
                }
            }


           $comwithdrawnum = 100;
		   if ($comwithdrawnum > 0) {
			    $tim = strtotime(date('Y-m-d',time()));
			    $count = Db::name('member_commission_withdrawlog')->where('mid',mid)->where('createtime','>=',$tim)->count();
			    if ($count >= $comwithdrawnum) {
			       	return $this->json(['status'=>0,'msg'=>'每日最多提现'.$comwithdrawnum.'次']);
			    }
			}
			$ordernum = date('ymdHis').aid.rand(1000,9999);
			$record['aid'] = aid;
			$record['mid'] = mid;
			$record['createtime']= time();

            $real_money = dd_money_format($money*(1-$set['comwithdrawfee']*0.01)) ;
			if($real_money <= 0) {
                return $this->json(['status'=>0,'msg'=>'提现金额有误']);
            }
			if(getcustom('cashback_yongjin')){
                //提现金额减掉已返现金额
                //查询返现数据，按时间顺序每次查一条
                $cashback = Db::name('shop_order_goods_cashback')->where(['mid'=>mid,'cashback_yongjin'=>1])->order('id asc')->find();
                $record['cashback_id'] = $cashback['id']?:0;
                $back_money = 0;
                if($cashback){
                    if($cashback['back_type']==1){
                        $back_money = bcmul($cashback['moneyave'],$cashback['money_sendnum'],2);
                    }
                    if($cashback['back_type']==2){
                        $back_money = bcmul($cashback['commissionave'],$cashback['commission_sendnum'],2);
                    }
                    if($cashback['back_type']==3){
                        $back_money = bcmul($cashback['scoreave'],$cashback['score_sendnum'],2);
                    }
                }
                $real_money = bcsub($real_money,$back_money,2);
                if($real_money<=0){
                    return $this->json(['status'=>0,'msg'=>'提现金额有误,可提现佣金小于返现金额']);
                }
                //终止正在返现的商品
                Db::name('shop_order_goods_cashback')->where('id',$cashback['id'])->update(['cashback_yongjin'=>2]);
            }
			//提现到账通证
            if(getcustom('product_givetongzheng')){
                $commissionwithdraw2tongzheng = $set['commissionwithdraw2tongzheng'];
                $tongzheng_num = bcmul($money,$commissionwithdraw2tongzheng/100,3);
                $real_money = bcsub($real_money,$tongzheng_num,2);
                if($real_money <= 0) {
                    return $this->json(['status'=>0,'msg'=>'提现金额有误']);
                }
                $record['tongzheng'] = $tongzheng_num;
            }

            $record['money'] = $real_money;
			$record['txmoney'] = $money;
			if($post['paytype']=='支付宝'){
				$record['aliaccountname'] = $this->member['aliaccountname'];
				$record['aliaccount'] = $this->member['aliaccount'];
			}
			if($post['paytype']=='银行卡'){
				$record['bankname'] = $this->member['bankname'].($this->member['bankaddress'] ? ' '.$this->member['bankaddress'] : '');;
				$record['bankcarduser'] = $this->member['bankcarduser'];
				$record['bankcardnum'] = $this->member['bankcardnum'];
			}
            if($post['paytype']=='银行卡' && $set['withdraw_huifu'] == 1){
                $record['huifu_id'] = $this->member['huifu_id'];
            }
            if(getcustom('transfer_farsion')){
                $account_no = '';
                $xiaoetong_type = 1;
				if($post['paytype']=='小额通支付宝'){
					$record['aliaccountname'] = $this->member['aliaccountname'];
					$record['aliaccount'] = $this->member['aliaccount'];
                    $account_no = $record['aliaccount'];
                    $xiaoetong_type = 2;
				}
				if($post['paytype']=='小额通银行卡'){
					$record['bankname'] = $this->member['bankname'] . ($this->member['bankaddress'] ? ' '.$this->member['bankaddress'] : '');
					$record['bankcarduser'] = $this->member['bankcarduser'];
					$record['bankcardnum'] = $this->member['bankcardnum'];
                    $account_no = $record['bankcardnum'];
                    $xiaoetong_type = 1;
				}
			}
            if(getcustom('extend_linghuoxin')){
                if($post['paytype']=='灵活薪支付宝' || $post['paytype']=='灵活薪银行卡'){
                    //查看账号余额
                    // $getbalance = \app\customs\LinghuoxinCustom::getbalance(aid);
                    // if($getbalance['status'] == 0){
                    //     return $this->json($getbalance);
                    // }
                    // if($record['money']>$getbalance['data']['availableBalance']){
                    //     return $this->json(['status'=>0,'msg'=>'灵活薪账号金额不足，请减少提现金额后重试']);
                    // }
                    if($post['paytype']=='灵活薪支付宝'){
                        $record['aliaccountname'] = $this->member['aliaccountname'];
                        $record['aliaccount'] = $this->member['aliaccount'];
                    }
                    if($post['paytype']=='灵活薪银行卡'){
                        $record['bankname']    = $this->member['bankname'] . ($this->member['bankaddress'] ? ' '.$this->member['bankaddress'] : '');
                        $record['bankcarduser']= $this->member['bankcarduser'];
                        $record['bankcardnum'] = $this->member['bankcardnum'];
                    }
                }
            }
            if(getcustom('withdraw_paycode')){
                if($post['paytype']=='收款码'){
                    if($this->member['wxpaycode']){
                        $record['wxpaycode'] = $this->member['wxpaycode'];
                    }
                    if($this->member['alipaycode']){
                        $record['alipaycode'] = $this->member['alipaycode'];
                    }
                }
            }
			$record['ordernum'] = $ordernum;
			$record['paytype'] = $post['paytype'];
			$record['platform'] = platform;
            if(getcustom('commission_withdraw_need_score')){
                $record['need_score'] = $needScoreNum;
            }
            if(getcustom('commission_duipeng_score_withdraw')){
                $record['need_commission_withdraw_score'] = $need_commission_withdraw_score;
            }
            if(getcustom('business_fenxiao')){
                $bid = input('bid')?:0;
                $record['bid'] = $bid;
            }
			if(getcustom('commission_withdraw_freeze')){
				//查看已申请提现金额
				$comwithdrawmoney = Db::name('member_commission_withdrawlog')->where('aid',aid)->where('mid',mid)->where('status',3)->sum('txmoney');
				$totalmoney = $comwithdrawmoney+$money;
				if(in_array('1',explode(',',$set['comwithdraw_freeze'])) && $set['comwithdraw_totalmoney']>0 && $totalmoney>=$set['comwithdraw_totalmoney']){
					 Db::name('member')->where('id',mid)->update(['iscomwithdraw_freeze'=>1]);
				}
			}
            if(getcustom('fengdanjiangli')){
                $record['money'] = dd_money_format($record['money']);
                $comwithdrawbl = Db::name('admin_set')->where('aid',aid)->value('comwithdrawbl');
                
                if($comwithdrawbl > 0 && $comwithdrawbl < 100){
                    $paymoney = round($record['money'] * $comwithdrawbl * 0.01,2);
                    $tomoney = round($record['money'] - $paymoney,2);//可提现比例不是100%时,不可提现部分在提现时直接转换为余额
                }else{
                    $paymoney = $record['money'];
                    $tomoney = 0;
                }
                $record['paymoney'] =  $paymoney;
                $record['tomoney'] =  $tomoney;
            }
            

			$recordid = Db::name('member_commission_withdrawlog')->insertGetId($record);
            if($recordid){
                //记录今天提现了几次
                if(!$day_withdraw_num || empty($day_withdraw_num)){
                    cache($daywithdrawnum,1,86400);
                }else{
                    $daynum = $day_withdraw_num+1;
                    cache($daywithdrawnum,$daynum,86400);
                }
            }

			\app\commons\Member::addcommission(aid,mid,0,-$money,t('佣金').'提现');
            if($needScoreNum>0){
                \app\commons\Member::addscore(aid,mid,-$needScoreNum,t('佣金').'提现消耗');
            }
            if(getcustom('commission_duipeng_score_withdraw')){
                if($need_commission_withdraw_score>0){
                    \app\commons\Member::add_commission_withdraw_score(aid,mid,-$need_commission_withdraw_score,t('佣金').'提现消耗');
                }
            }
            if(getcustom('business_fenxiao')){
                $bid = input('bid');
                if($bid>0){
                    Db::name('business_fenxiao_bonus_total')
                        ->where('mid',mid)
                        ->where('bid',$bid)
                        ->dec('remain',$money)
                        ->inc('withdraw',$money)
                        ->update();
                }
            }
			Db::commit();
			$tmplcontent = array();
			$tmplcontent['first'] = '有客户申请'.t('佣金').'提现';
			$tmplcontent['remark'] = '点击进入查看~';
			$tmplcontent['keyword1'] = $this->member['nickname'];
			$tmplcontent['keyword2'] = date('Y-m-d H:i');
			$tmplcontent['keyword3'] = $money.'元';
			$tmplcontent['keyword4'] = $post['paytype'];
			\app\commons\Wechat::sendhttmpl(aid,0,'tmpl_withdraw',$tmplcontent,m_url('admin/finance/comwithdrawlog'));

			$tmplcontent = [];
			$tmplcontent['name3'] = $this->member['nickname'];
			$tmplcontent['amount1'] = $money.'元';
			$tmplcontent['date2'] = date('Y-m-d H:i');
			$tmplcontent['thing4'] = '提现到'.$post['paytype'];
			\app\commons\Wechat::sendhtwxtmpl(aid,0,'tmpl_withdraw',$tmplcontent,'admin/finance/comwithdrawlog');

            //小额通提现
			if(getcustom('transfer_farsion')){
				if($set['withdraw_autotransfer'] &&  ($post['paytype'] == '小额通支付宝' || $post['paytype'] == '小额通银行卡' )){
					$xetService = new  \app\commons\Xiaoetong();
					//导入数据
                    $record['id'] = $recordid;
					$xet_res = $xetService->sendData($record,$this->member,'佣金提现');	
					//print_r($res);die;
					if($xet_res['code'] == 0){
						Db::name('member_commission_withdrawlog')->where('aid',aid)->where('id',$recordid)->update(['status'=>1]);			
						return $this->json(['status'=>1,'msg'=>'提交成功,请等待打款','data'=>[]]);
					}else{
						\app\commons\Member::addcommission(aid,mid,0,$money,t('佣金').'提现返还');                        
						Db::name('member_commission_withdrawlog')->where('id',$recordid)->update(['status' => 2,'reason'=>'快商小额通推送失败'.$xet_res['msg']]);
						return $this->json(['status'=>1,'msg'=>'提现失败'.$xet_res['msg'],'data'=>[]]);
					}
				}
            }
            if(getcustom('extend_linghuoxin')){
                //灵活薪提现
                if($set['withdraw_autotransfer'] && ($post['paytype'] == '灵活薪支付宝' || $post['paytype'] == '灵活薪银行卡' )){
                    $gopay = \app\customs\LinghuoxinCustom::gopay(aid,0,$this->member,$recordid,$record,$post['paytype'],1);
                    if($gopay && $gopay['status'] == 1){
                        $updata = [];
                        $updata['status']   = 1;
                        $updata['taskNo']   = $gopay['data']['taskNo'];
                        $updata['taskdata'] = json_encode($gopay['data']);
                        Db::name('member_commission_withdrawlog')->where('id',$recordid)->update($updata);
                        return $this->json(['status'=>1,'msg'=>'提交成功,请等待打款','data'=>[]]);
                    }else{
                        $msg = $gopay && $gopay['msg']?$gopay['msg']:'';
                        Db::name('member_commission_withdrawlog')->where('id',$recordid)->update(['reason'=>'灵活薪推送失败'.$msg]);
                        return $this->json(['status'=>1,'msg'=>'提交成功,请等待打款']);
                        // $msg = $gopay && $gopay['msg']?$gopay['msg']:'';
                        // \app\commons\Member::addcommission(aid,mid,0,$money,t('佣金').'提现返还');
                        // Db::name('member_commission_withdrawlog')->where('id',$recordid)->update(['status' => 2,'reason'=>'灵活薪推送失败'.$msg]);
                        // return $this->json(['status'=>0,'msg'=>'提现失败','data'=>[]]);
                    }
                }
            }

			if($set['withdraw_autotransfer'] && ($post['paytype'] == '微信钱包' || $post['paytype'] == '银行卡')){
				if($set['comwithdrawbl'] > 0 && $set['comwithdrawbl'] < 100){
					$paymoney = round($record['money'] * $set['comwithdrawbl'] * 0.01,2);
					$tomoney = round($record['money'] - $paymoney,2);
				}else{
					$paymoney = $record['money'];
					$tomoney = 0;
				}
                $paymoney = dd_money_format($paymoney);
				$rs = \app\commons\Wxpay::transfers(aid,mid,$paymoney,$record['ordernum'],platform,t('佣金').'提现');
				if($rs['status']==0){
					return json(['status'=>1,'msg'=>'提交成功,请等待打款']);
				}else{
					if($tomoney > 0){
						\app\commons\Member::addmoney(aid,mid,$tomoney,t('佣金').'提现');
					}
					Db::name('member_commission_withdrawlog')->where('aid',aid)->where('id',$recordid)->update(['status'=>3,'paytime'=>time(),'paynum'=>$rs['resp']['payment_no']]);
					//记录用户累计提现
                    if(getcustom('cashback_yongjin')){
                        Db::name('member')->where('id',mid)->inc('cash_yongji_total',$record['money'])->update();
                    }
					//提现成功通知
					$tmplcontent = [];
					$tmplcontent['first'] = '您的提现申请已打款，请留意查收';
					$tmplcontent['remark'] = '请点击查看详情~';
					$tmplcontent['money'] = (string) round($record['money'],2);
					$tmplcontent['timet'] = date('Y-m-d H:i',$record['createtime']);
                    $tempconNew = [];
                    $tempconNew['amount2'] = (string) $record['money'];//提现金额
                    $tempconNew['time3'] = date('Y-m-d H:i',$record['createtime']);//提现时间
					\app\commons\Wechat::sendtmpl(aid,$record['mid'],'tmpl_tixiansuccess',$tmplcontent,m_url('pages/my/usercenter'),$tempconNew);
					//订阅消息
					$tmplcontent = [];
					$tmplcontent['amount1'] = $record['money'];
					$tmplcontent['thing3'] = '微信打款';
					$tmplcontent['time5'] = date('Y-m-d H:i');
					
					$tmplcontentnew = [];
					$tmplcontentnew['amount3'] = $record['money'];
					$tmplcontentnew['phrase9'] = '微信打款';
					$tmplcontentnew['date8'] = date('Y-m-d H:i');
					\app\commons\Wechat::sendwxtmpl(aid,$record['mid'],'tmpl_tixiansuccess',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);
					//短信通知
					if($this->member['tel']){
						\app\commons\Sms::send(aid,$this->member['tel'],'tmpl_tixiansuccess',['money'=>$record['money']]);
					}
					return json(['status'=>1,'msg'=>$rs['msg']]);
				}
			}
			if(getcustom('alipay_auto_transfer')){
				if($set['ali_withdraw_autotransfer'] && $post['paytype'] == '支付宝'){
	                if($set['comwithdrawbl'] > 0 && $set['comwithdrawbl'] < 100){
	                    $paymoney = round($record['money'] * $set['comwithdrawbl'] * 0.01,2);
	                    $tomoney = round($record['money'] - $paymoney,2);
	                }else{
	                    $paymoney = $record['money'];
	                    $tomoney = 0;
	                }
	                $rs = \app\commons\Alipay::transfers(aid,$record['ordernum'],$paymoney,t('佣金').'提现',$this->member['aliaccount'],$this->member['aliaccountname'],t('佣金').'提现');
	                if($rs['status']==0){
	                    $sub_msg = $rs['sub_msg']?$rs['sub_msg']:'';
	                    if($sub_msg){
	                        Db::name('member_commission_withdrawlog')->where('aid',aid)->where('id',$recordid)->update(['reason'=>$sub_msg]);
	                    }
	                    return json(['status'=>1,'msg'=>'提交成功,请等待打款']);
	                }else{
	                    if($tomoney > 0){
	                        \app\commons\Member::addmoney(aid,mid,$tomoney,t('佣金').'提现');
	                    }
	                    Db::name('member_commission_withdrawlog')->where('aid',aid)->where('id',$recordid)->update(['status'=>3,'paytime'=>time(),'paynum'=>$rs['pay_fund_order_id']]);
	                    //提现成功通知
	                    $tmplcontent = [];
	                    $tmplcontent['first'] = '您的提现申请已打款，请留意查收';
	                    $tmplcontent['remark'] = '请点击查看详情~';
	                    $tmplcontent['money'] = (string) round($record['money'],2);
	                    $tmplcontent['timet'] = date('Y-m-d H:i',$record['createtime']);
                        $tempconNew = [];
                        $tempconNew['amount2'] = (string) $record['money'];//提现金额
                        $tempconNew['time3'] = date('Y-m-d H:i',$record['createtime']);//提现时间
	                    \app\commons\Wechat::sendtmpl(aid,$record['mid'],'tmpl_tixiansuccess',$tmplcontent,m_url('pages/my/usercenter'),$tempconNew);
	                    //订阅消息
	                    $tmplcontent = [];
	                    $tmplcontent['amount1'] = $record['money'];
	                    $tmplcontent['thing3'] = '支付宝打款';
	                    $tmplcontent['time5'] = date('Y-m-d H:i');
	                    
	                    $tmplcontentnew = [];
	                    $tmplcontentnew['amount3'] = $record['money'];
	                    $tmplcontentnew['phrase9'] = '支付宝打款';
	                    $tmplcontentnew['date8'] = date('Y-m-d H:i');
	                    \app\commons\Wechat::sendwxtmpl(aid,$record['mid'],'tmpl_tixiansuccess',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);
	                    //短信通知
	                    if($this->member['tel']){
	                        \app\commons\Sms::send(aid,$this->member['tel'],'tmpl_tixiansuccess',['money'=>$record['money']]);
	                    }
	                    return json(['status'=>1,'msg'=>$rs['msg']]);
	                }
	            }
			}
			if(getcustom('pay_adapay')){
                if($set['withdraw_autotransfer'] && $post['paytype'] == '汇付天下银行卡'){
                    $adapay = Db::name('adapay_member')->where('aid',aid)->where('mid',mid)->find();
                    if($set['comwithdrawbl'] > 0 && $set['comwithdrawbl'] < 100){
                        $paymoney = round($record['money'] * $set['comwithdrawbl'] * 0.01,2);
                        $tomoney = round($record['money'] - $paymoney,2);
                    }else{
                        $paymoney = $record['money'];
                        $tomoney = 0;
                    }
                    $paymoney = dd_money_format($paymoney);
                    $rs = \app\customs\AdapayPay::balancePay(aid,'h5',$adapay['member_id'],$record['ordernum'],$paymoney);
                    if($rs['status'] == 0){
                        $sub_msg = $rs['msg']?$rs['msg']:'';
                        if($sub_msg){
                            Db::name('member_commission_withdrawlog')->where('aid',aid)->where('id',$recordid)->update(['reason'=>$sub_msg]);     
                        }
                        return json(['status'=>1,'msg'=>'提交成功,请等待打款']);
                    }else{
                        //从用户余额中进行提现到银行卡
                        $drs = \app\customs\AdapayPay::drawcash(aid,'h5',$adapay['member_id'],$record['ordernum'],$paymoney);
                        if($drs['status'] == 0){
                            Db::name('member_commission_withdrawlog')->where('aid',aid)->where('id',$record['id'])->update(['reason'=>$drs['msg']]);
                            return json(['status'=>0,'msg'=>$drs['msg']]);
                        }
                        if($tomoney > 0){
                            \app\commons\Member::addmoney(aid,mid,$tomoney,t('佣金').'提现');
                        }
                        Db::name('member_commission_withdrawlog')->where('aid',aid)->where('id',$recordid)->update(['status'=>3,'paytime'=>time(),'paynum'=>$rs['resp']['payment_no']]);
                        //提现成功通知
                        $tmplcontent = [];
                        $tmplcontent['first'] = '您的提现申请已打款，请留意查收';
                        $tmplcontent['remark'] = '请点击查看详情~';
                        $tmplcontent['money'] = (string) round($record['money'],2);
                        $tmplcontent['timet'] = date('Y-m-d H:i',$record['createtime']);
                        $tempconNew = [];
                        $tempconNew['amount2'] = (string) $record['money'];//提现金额
                        $tempconNew['time3'] = date('Y-m-d H:i',$record['createtime']);//提现时间
                        \app\commons\Wechat::sendtmpl(aid,$record['mid'],'tmpl_tixiansuccess',$tmplcontent,m_url('pages/my/usercenter'),$tempconNew);
                        //订阅消息
                        $tmplcontent = [];
                        $tmplcontent['amount1'] = $record['money'];
                        $tmplcontent['thing3'] = '汇付天下打款';
                        $tmplcontent['time5'] = date('Y-m-d H:i');

                        $tmplcontentnew = [];
                        $tmplcontentnew['amount3'] = $record['money'];
                        $tmplcontentnew['phrase9'] = '汇付天下打款';
                        $tmplcontentnew['date8'] = date('Y-m-d H:i');
                        \app\commons\Wechat::sendwxtmpl(aid,$record['mid'],'tmpl_tixiansuccess',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);
                        //短信通知
                        if($this->member['tel']){
                            \app\commons\Sms::send(aid,$this->member['tel'],'tmpl_tixiansuccess',['money'=>$record['money']]);
                        }
                        return json(['status'=>1,'msg'=>$rs['msg']]);
                    }
                }
            }
			return $this->json(['status'=>1,'msg'=>'提交成功,请等待打款']);
		}

		//订阅消息
		$wx_tmplset = Db::name('wx_tmplset')->where('aid',aid)->find();
		$tmplids = [];
		if($wx_tmplset['tmpl_tixiansuccess_new']){
			$tmplids[] = $wx_tmplset['tmpl_tixiansuccess_new'];
		}elseif($wx_tmplset['tmpl_tixiansuccess']){
			$tmplids[] = $wx_tmplset['tmpl_tixiansuccess'];
		}
		if($wx_tmplset['tmpl_tixianerror_new']){
			$tmplids[] = $wx_tmplset['tmpl_tixianerror_new'];
		}elseif($wx_tmplset['tmpl_tixianerror']){
			$tmplids[] = $wx_tmplset['tmpl_tixianerror'];
		}
		$rdata = [];
        if($this->member['paypwd']=='') {
			$userinfo['haspwd'] = 0;
		} else {
			$userinfo['haspwd'] = 1;
		}
		$userinfo['paycheck'] = $this->sysset['money_pwd'];
        $userinfo['show_cash_count'] = $show_cash_count;
        //如果需要消耗积分的 则取积分可兑换的最小值
        if(getcustom('commission_withdraw_need_score') && $set['comwithdraw_need_score']==1){
            $exchange_score_num = $set['commission_score_exchange_num']??1;
            $scoreExchangeCommission = $userinfo['score']/$exchange_score_num;
            $userinfo['commission'] = min($userinfo['commission'],$scoreExchangeCommission);
        }
        $moeny_weishu = 2;
        if(getcustom('fenhong_money_weishu')){
            $moeny_weishu = Db::name('admin_set')->where('aid',aid)->value('fenhong_money_weishu');
        }
        $moeny_weishu = $moeny_weishu?$moeny_weishu:2;
        $userinfo['commission'] = dd_money_format($userinfo['commission'],$moeny_weishu);
        if(getcustom('pay_adapay')){
            $adapay = Db::name('adapay_member')->where('aid',aid)->where('mid',mid)->find();
            $userinfo['to_set_adapay'] = 0;
            if(!$adapay ||  !$adapay['settle_account_id']){
                $userinfo['to_set_adapay'] = 1;
            }
        }

		$selectbank = false;
		if(getcustom('yx_gift_pack')){
			$selectbank = true;
			//选择默认银行卡
			$bank = Db::name('member_bank')->where('aid',aid)->where('mid',mid)->where('isdefault',1)->find();
			if($bank) $bank['bankcardnum'] = substr($bank['bankcardnum'],0,3).'******'.substr($bank['bankcardnum'],-4);
			$rdata['bank'] = $bank;
		}
		$rdata['selectbank'] =  $selectbank;	
		$rdata['userinfo'] =  $userinfo ;
		$rdata['sysset'] = $set;
		$rdata['tmplids'] = $tmplids;

		return $this->json($rdata);
	}
	public function getBusinesscommission(){
	    if(getcustom('touzi_fenhong')){
            $bid = input('param.bid/d');
            if(!$bid){
                $bid = 0;
            }
            
            $rs = \app\commons\Fenhong::touzi_fenhong(aid,$this->sysset,[],0,time(),1,mid,$bid);
            $newoglist = $rs['oglist'];
            $commissionyj = $rs['commissionyj'];
          
            //已结算
            $where = [];
            $where[] = ['mf.aid','=',aid];
            $where[] = ['mf.mid','=',mid];
            $where[] = ['mf.type','=','touzi_fenhong'];
            $where[] = ['og.bid','=',$bid];
            $pernum = 20;
            $pagenum = input('param.pagenum');
            if(!$pagenum) $pagenum = 1;
            $commission = Db::name('member_fenhonglog')->alias('mf')
                ->join('shop_order_goods og','og.id = mf.ogids')
                ->where($where)->page($pagenum,$pernum)
                ->sum('mf.commission');
            $touzimoney = 0+ Db::name('shareholder')->where('aid',aid)->where('bid',$bid)->where('status',1)->where('mid',mid)-> sum('money');
            $rdata = [];
            $rdata['userinfo']['commissionyj'] = round($commissionyj,2);
            $rdata['userinfo']['commission'] = round($commission,2);
            $rdata['userinfo']['touzimoney'] =$touzimoney;
            return $this->json($rdata);
        }
    }
    //扶持基金明细
    public function fuchiLog()
    {
        if(getcustom('commission_frozen')){
            $st = input('param.st');
            $where = [];
            $where[] = ['aid','=',aid];
            $where[] = ['mid','=',mid];
            if(input('param.keyword')) $where[] = ['remark', 'like', '%'.input('param.keyword').'%'];
            $pernum = 20;
            $pagenum = input('post.pagenum');
            if(!$pagenum) $pagenum = 1;
            if($st ==1){//提现记录
//            $datalist = Db::name('member_commission_withdrawlog')->field("id,money,txmoney,`status`,from_unixtime(createtime)createtime")->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
            }else{ //佣金明细
                $datalist = Db::name('member_fuchi_log')->field("id,commission money,`after`,from_unixtime(createtime)createtime,remark")->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
            }
            if(!$datalist) $datalist = [];
            return $this->json(['status'=>1,'data'=>$datalist]);
        }
    }
    //我的团队
	public function team(){
	    $mid = input('mid')?input('mid/d'):0;
	    if(!$mid){
	        $mid = mid;
        }
	    $date_start = 0;
        $date_end = 0;
	    if(input('date_start') && input('date_end')){
            $date_start = strtotime(input('date_start'));
            $date_end = strtotime(input('date_end'));
        }
	    $checkLevelid = input('checkLevelid')?input('checkLevelid/d'):0;

		$admin_set = Db::name('admin_set')->where('aid',aid)->find();
		$userinfo = Db::name('member')->field('id,nickname,headimg,levelid,last_visittime')->where('aid',aid)->where('id',$mid)->find();
		$userlevel = Db::name('member_level')->where('aid',aid)->where('id',$userinfo['levelid'])->find();

		$downdeep = input('param.st/d');
		$pernum = 20;
		$pagenum = input('post.pagenum');
		$keyword = input('post.keyword');
        $order = "id desc";
        if(getcustom('team_show_visittime')){
            $order = "last_visittime desc,id desc";
        }

		$where2 = "1=1";
		$query_params = [];//query查询条件
		if($keyword){
            $where2 = "(nickname like :keyword or realname like :keyword2 or tel like :keyword3)";
            $query_params['keyword3'] = $query_params['keyword2'] = $query_params['keyword'] = '%'.$keyword.'%';
        } 
		if($date_start && $date_end){
		    $where_date = "createtime>=:date_start and createtime<=:date_end";
            $query_params['date_start'] = $date_start;
            $query_params['date_end']   = $date_end;
            if($where2=='1=1'){
                $where2 = $where_date;
            }else{
                $where2 = $where2.' and '.$where_date;
            }
        }
		if($checkLevelid){
            $where_level = 'levelid='.$checkLevelid;
            if($where2=='1=1'){
                $where2 = $where_level;
            }else{
                $where2 = $where2.' and '.$where_level;
            }
        }
		if(!$pagenum) $pagenum = 1;
		if(!$downdeep) $downdeep = 1;
		if(!$mid){
			$datalist = [];
		}else{
			if($downdeep == 1){
				$datalist = Db::name('member')->field("id,nickname,headimg,tel,pid,score,totalcommission,from_unixtime(createtime)createtime,levelid,last_visittime")->where("aid",aid)->where("pid",$mid)->whereRaw($where2,$query_params)->page($pagenum,$pernum)->order($order)->select()->toArray();
			}elseif($downdeep == 2){
                $query_params['pid'] = $mid;
				$datalist = Db::query("select id,nickname,headimg,tel,pid,score,totalcommission,from_unixtime(createtime)createtime,levelid,last_visittime from ".table_name('member')." where aid=".aid." and $where2 and pid in(select id from ".table_name('member')." where pid= :pid) order by {$order} limit ".($pagenum*$pernum-$pernum).','.$pernum, $query_params);
			}elseif($downdeep == 3){
                $query_params['pid'] = $mid;
				$datalist = Db::query("select id,nickname,headimg,tel,pid,score,totalcommission,from_unixtime(createtime)createtime,levelid,last_visittime from ".table_name('member')." where aid=".aid." and pid in(select id from ".table_name('member')." where aid=".aid." and $where2 and pid in(select id from ".table_name('member')." where pid=:pid)) order by {$order} limit ".($pagenum*$pernum-$pernum).','.$pernum, $query_params);
			}elseif($downdeep == 4){
			    //团队
                $downmids =  \app\commons\Member::getdowntotalmids(aid,$mid,$userlevel['can_agent']);
                if($downmids){
                    $downmids = implode(',',$downmids);
                    $datalist = Db::query("select id,nickname,headimg,tel,pid,score,totalcommission,from_unixtime(createtime)createtime,levelid,last_visittime from ".table_name('member')." where aid=".aid." and $where2 and id in(".$downmids.") order by {$order} limit ".($pagenum*$pernum-$pernum).','.$pernum, $query_params);
                }
			}
		}
		if(!$datalist) $datalist = [];
        $is_include_self = getcustom('yx_team_yeji_include_self');
		$team_yeji_total = 0;
        $downmids = \app\commons\Member::getteammids(aid,$mid);
        if($is_include_self){
            //包含自己 系统设置中
            if($admin_set['teamyeji_include_self']) $downmids[] = $mid;
        }
        if($downmids){
            $yejiwherewm = $yejiwhere = [];
            $yejiwhere[] = ['status','in','1,2,3'];
            $yejiwherewm[] = ['status','in','1,2,3,12'];
            $yejiwherewm[] = $yejiwhere[] = ['mid','in',$downmids];
            if(getcustom('yx_team_yeji_jicha')){
                $levelup_time = 0;
                $yeji_set = Db::name('team_yeji_set')->where('aid',aid)->find();
                $yejiconfig = json_decode($yeji_set['config_data'],true);
                $show_levelid = array_keys($yejiconfig);
                if(in_array($this->member['levelid'],$show_levelid)){
                    $levelup_order = Db::name('member_levelup_order')
                        ->where('aid',aid)
                        ->where('mid',$mid)
                        ->where('levelid',$this->member['levelid'])
                        ->where('status',2)
                        ->order('levelup_time desc')
                        ->find();
                    $levelup_time = $levelup_order['levelup_time'];
                }
            }
            if($date_start && $date_end){
                 if(getcustom('yx_team_yeji_jicha')){
                       if(!$levelup_time) $date_start = 0;  $date_end = 0;
                 }
                $yejiwherewm[] = $yejiwhere[] = ['createtime','between',[$date_start,$date_end]];
            }else{
                if(getcustom('yx_team_yeji_jicha')){
                    $nowtime = time();
                    if(!$levelup_time)$nowtime = 0;
                    $yejiwherewm[] = $yejiwhere[] = ['createtime','between',[$levelup_time,$nowtime]];
                }
            }
            $team_yeji_total = Db::name('shop_order_goods')->where('aid',aid)->where($yejiwhere)->sum('real_totalprice')?:0;
            // 餐饮订单计入团队业绩，参与升级条件统计
            if(getcustom('restaurant_team_yeji')){
                // 外卖
                $rtakeaway_fxordermoney = Db::name('restaurant_takeaway_order_goods')->where('aid',aid)->where($yejiwherewm)->sum('totalprice')?:0;
                $team_yeji_total += $rtakeaway_fxordermoney;
                // 店内点餐
                $rshop_fxordermoney = Db::name('restaurant_shop_order_goods')->where('aid',aid)->where($yejiwhere)->sum('totalprice')?:0;
                $team_yeji_total += $rshop_fxordermoney;

            }

        }
        
		foreach($datalist as $k=>$v){
			//if($downdeep==1){
			//	$commission = Db::name('shop_order_goods')->where('aid',aid)->where('mid',$v['id'])->where('parent1',mid)->where('status',3)->sum('parent1commission');
			//}
			//if($downdeep==2){
			//	$commission = Db::name('shop_order_goods')->where('aid',aid)->where('mid',$v['id'])->where('parent2',mid)->where('status',3)->sum('parent2commission');
			//}
			//if($downdeep==3){
			//	$commission = Db::name('shop_order_goods')->where('aid',aid)->where('mid',$v['id'])->where('parent3',mid)->where('status',3)->sum('parent3commission');
			//}

            $commission_where = [];
            $commission_where[] = ['aid','=',aid];
            $commission_where[] = ['mid','=',$mid];
            $commission_where[] = ['frommid','=',$v['id']];
            $commission_where[] = ['status','=',1];
            if($date_start && $date_end){
                $commission_where[] = ['createtime','between',[$date_start,$date_end]];
            }
			$commission = Db::name('member_commission_record')
                ->where($commission_where)
                ->sum('commission');

			$datalist[$k]['commission'] = 0 + dd_money_format($commission,2);
			$downcount_where = [];
            $downcount_where[] = ['aid','=',aid];
            $downcount_where[] = ['pid','=',$v['id']];
            if($date_start && $date_end){
                $downcount_where[] = ['createtime','between',[$date_start,$date_end]];
            }
			$datalist[$k]['downcount'] = 0 + Db::name('member')->where($downcount_where)->count();

			$level = Db::name('member_level')->where('aid',aid)->where('id',$v['levelid'])->find();
            $datalist[$k]['levelname'] = $level['name'];
            $datalist[$k]['levelsort'] = $level['sort'];
            $datalist[$k]['last_visittime'] = $v['last_visittime']?date('Y-m-d H:i:s',$v['last_visittime']):'';
			if($userlevel['team_showtel'] == 0){
				$datalist[$k]['tel'] = '';
			}

            if(getcustom('team_auth')){
                //团队业绩
                //总业绩
                $yejiwhere = [];
                $yejiwhere[] = ['status','in','1,2,3'];
                if($date_start && $date_end){
                    $yejiwhere[] = ['createtime','between',[$date_start,$date_end]];
                }
                $downmids = \app\commons\Member::getteammids(aid,$v['id']);
                //下级人数
                $team_down_total = count($downmids);
                $datalist[$k]['team_down_total'] = $team_down_total;

                $teamyeji = Db::name('shop_order_goods')->where('aid',aid)->where('mid','in',$downmids)->where($yejiwhere)->sum('real_totalprice');
                //个人业绩
                $self_yeji = Db::name('shop_order_goods')->where('aid',aid)->where('mid',$v['id'])->where($yejiwhere)->sum('real_totalprice');
                //业绩包含自身
                if(getcustom('teamyeji_show') && $admin_set['teamyeji_self']==1){
                    $teamyeji = bcadd($teamyeji,$self_yeji,2);
                }
                $datalist[$k]['teamyeji'] = $teamyeji;
                $datalist[$k]['selfyeji'] = $self_yeji;
                //$team_yeji_total = bcadd($team_yeji_total,$teamyeji,2);
            }
			if(getcustom('yx_gift_pack')){
				$packcount = 0+Db::name('gift_pack_order')->where('aid',aid)->where('mid',$v['id'])->count();
				$datalist[$k]['packcount'] = $packcount;
				$totalpackmoney = 0+Db::name('gift_pack_order')->where('aid',aid)->where('mid',$v['id'])->sum('sell_price');
				$datalist[$k]['totalpackmoney'] = $totalpackmoney;
			}
            if(getcustom('coupon_xianxia_buy')){
                $xianxia_sales =0+ Db::name('coupon_record')->where('aid',aid)->where('from_mid',$v['id'])->where('is_xianxia_buy',1)->count();
                $datalist[$k]['xianxia_sales'] = $xianxia_sales;
            }
        }
        $month_yeji_show = false;
        if(getcustom('yx_team_yeji')){
            $month_yeji_show = true;
            //当月 (业绩 + 虚拟业绩)
            if(input('month_search')){
                $month = rtrim(input('month_search'),'月');
                $month = $month<10?'0'.$month:$month;
                $month_start = strtotime(date('Y-'.$month.'-01 00:00:00'));
                $month_end  = strtotime(date('Y-m-t 23:59:59',$month_start));
                $now_month = date('Y-'.$month);
            }else{
                $month_start = strtotime(date('Y-m-01 00:00:00'));
                $month_end  = strtotime(date('Y-m-t 23:59:59'));
                $now_month = date('Y-m');
            }
           
            $yejiwhere = [];
            if(getcustom('yx_team_yeji_jicha')){
                if(!$levelup_time){
                    $month_start = 0;  $month_end = 0;
                }else{
                    if($levelup_time > $month_start ){
                        $month_start =  $levelup_time;
                    }
                }    
            }
            $yejiwhere[] = ['createtime','between',[$month_start,$month_end]];
            $yejiwhere[] = ['status','in','1,2,3'];

            $downmids = \app\commons\Member::getteammids(aid,$mid);

            if($is_include_self){
                //包含自己 系统设置中
                if($admin_set['teamyeji_include_self']) $downmids[] = $mid;
            }
            $month_teamyeji =0+ Db::name('shop_order_goods')->where('aid',aid)->where('mid','in',$downmids)->where($yejiwhere)->sum('real_totalprice');//totalprice real_totalprice
            $xuni_yeji =  0 +Db::name('tem_yeji_xuni')->where('aid',aid)->where('mid',$mid)->where('yeji_month',$now_month)->value('yeji');
            $now_month_yeji = dd_money_format($xuni_yeji + $month_teamyeji);
            $userinfo['now_month_yeji'] = $now_month_yeji;

        }
        $userinfo['month_yeji_show'] = $month_yeji_show;
        //团队业绩
        $downmids = \app\commons\Member::getteammids(aid,$userinfo['id']);
        $userinfo['team_down_total'] = count($downmids);
        
        $userinfo['team_yeji_total'] =$team_yeji_total; 
		//距离下个等级
        $userinfo['next_ordermoney_show'] = false;
        if(getcustom('team_next_up_ordermoney')){
            $nextlevel = Db::name('member_level')->where('aid',aid)->where('sort','>',$userlevel['sort'])->order('sort,id')->find();
            $userinfo['next_ordermoney_show'] = true;
            $next_up_ordermoney = 0;
            if($nextlevel['can_up'] ==1){
                $up_fxordermoney = $nextlevel['up_fxordermoney'];
                $next_up_ordermoney = dd_money_format( $up_fxordermoney - $userinfo['team_yeji_total'] );
            }
            $userinfo['next_up_ordermoney'] = $next_up_ordermoney <0?0:$next_up_ordermoney;//距离升级的业绩
        }
		if($pagenum == 1){
			//我的团队
            $team_where = [];
            $team_where[] = ['aid','=',aid];
            $team_where[] = ['pid','=',$mid];
            if($date_start && $date_end){
                $team_where[] = ['createtime','between',[$date_start,$date_end]];
            }
			$userinfo['myteamCount1'] = 0 + Db::name('member')->where($team_where)->count();
            $team_where = '1=1';
            $query_params2 = [];
            if($date_start && $date_end){
                $team_where = 'createtime>=:date_start and '.'createtime<=:date_end';
                $query_params2['date_start'] = $date_start;
                $query_params2['date_end']   = $date_end;
            }
			$myteamCount2 = Db::query("select count(1)c from ".table_name('member')." where aid=".aid." and pid in(select id from ".table_name('member')." where pid=".$mid.") and ".$team_where,$query_params2);
			$userinfo['myteamCount2'] = 0 + $myteamCount2[0]['c'];
			$myteamCount3 = Db::query("select count(1)c from ".table_name('member')." where aid=".aid." and pid in(select id from ".table_name('member')." where aid=".aid." and pid in(select id from ".table_name('member')." where pid=".$mid.")) and ".$team_where,$query_params2);
			$userinfo['myteamCount3'] = 0 + $myteamCount3[0]['c'];
			$userinfo['myteamCount'] = $userinfo['myteamCount1'] + $userinfo['myteamCount2'];
			//三级以后的团队人数
            $where_count = '1=1';
            if($date_start && $date_end){
                $where_count = 'createtime between '.$date_start.' and '.$date_end;
            }
            $downmids =  \app\commons\Member::getdowntotalmids(aid,$mid,$userlevel['can_agent'],'','',1,$where_count);
            $userinfo['myteamCount4'] = count($downmids);

			//分销订单
			$userinfo['fxorderCount'] = 0 + Db::name("shop_order_goods")->whereRaw("aid=".aid." and (parent1=".$mid." or parent2=".$mid." or parent3=".$mid.") and ".$team_where,$query_params2)->count();
            $levelList = Db::name('member_level')->field('id,sort,name')->where('aid',aid)->where('sort','<',$userlevel['sort'])->order('sort', 'asc')->select();
            //$levelList = Db::name('member_level')->field('id,sort,name')->where('aid',aid)->where('id', 'in', explode(',',$userlevel['team_levelup_id']))->order('sort', 'asc')->select();
            $newKey = 'id';
            $levelList = $levelList->dictionary(null, $newKey);
		}
        //小部门业绩[只统计确认收货且减掉退款的]
        $userinfo['miniyeji_show'] = false;
        if (getcustom('member_level_salary_bonus')) {
            $userinfo['miniyeji_show'] = true;
            $userinfo['team_miniyeji_total'] = \app\models\Commission::getMiniTeamCommission(aid,$mid,$date_start,$date_end);
        }
        $custom = ['_t'=>time()];//默认
        if(getcustom('team_show_visittime')){
            $custom['team_show_visittime'] = true;
        }
        if(getcustom('team_member_history')){
            $custom['team_member_history'] = true;
        }
		if(getcustom('yx_gift_pack')){
            $custom['yx_gift_pack'] = true;
        }


		$rdata = [];
		$rdata['datalist'] = $datalist;
		$rdata['userinfo'] = $userinfo;
		$rdata['custom'] = $custom;

		if(getcustom('member_levelup_givechild')){
            
            if($userlevel['team_levelup'] == 1){
                $usenum = Db::name('member_levelup_uesnum')->where('aid',aid)->where('mid',mid)->find();
                if(!$usenum){
                    \app\commons\Member::addMemberLevelupNum(aid,mid,$userlevel['team_levelup_data']);
                }else{
                    if($usenum['levelid'] !=$userlevel['id']){
                        $olduserlevel = Db::name('member_level')->where('aid',aid)->where('id',$usenum['levelid'])->find();
                        if(!$olduserlevel || $olduserlevel['sort'] < $userlevel['sort']){
                            \app\commons\Member::addMemberLevelupNum(aid,mid,$userlevel['team_levelup_data']);
                        }                        
                    }
                }
            }
        }
		if(getcustom('yx_shortvideo_jindubag')){
        	if($userlevel && $userlevel['team_showtel']){
        		$userlevel['team_callphone'] = 1;
        	}
        }
        //会员等级直推人数
        if(getcustom('member_level_zhitui_number_limit')){
            //0 无限制
            if($userlevel['zt_member_limit'] == 0){
                $userlevel['zt_member_limit'] = '无限制';
            }
        }
		$rdata['userlevel'] = $userlevel;
		$rdata['levelList'] = $levelList;
		$rdata['st'] = $downdeep;
        $rdata['team_auth'] = getcustom('team_auth')?1:0;
        if(getcustom('team_auth')){
            //所有级别
            $all_level = Db::name('member_level')->field('id,sort,name')->where('aid',aid)->select()->toArray();
            $rdata['all_level'] = $all_level;
        }
        $month_item = []; 
        if(getcustom('yx_team_yeji')){
            //今年的月份
            for($i=1;$i<= date('m');$i++){
                $month_item[] = $i.'月';
            }
        }
        $rdata['month_item'] = $month_item;
		return $this->json($rdata);
	}
	//分销订单
	public function agorder(){
		//拼团的佣金订单
		$pernum = 20;
		$pagenum = input('param.pagenum') ? input('param.pagenum') :1;
		$where = '';
		$st = input('param.st/d');
		if(!$st) $st = 0;
		if($st==1){//待付款
			$where = " and o.status=0";
		}
		if($st==2){//已付款
			$where = " and (o.status=1 or o.status=2)";
		}
		if($st==3){//已完成
			$where = " and o.status=3";
		}
		if($st==5){//售后
			$where = " and o.refund_money>0";
		}
		$datalist = Db::query("select og.id,og.mid,o.ordernum,o.status,o.createtime,o.paytime,og.name,og.proid,og.pic,og.num,og.parent1,og.parent2,og.parent3,og.parent1commission,og.parent2commission,og.parent3commission,og.parent1score,og.parent2score,og.parent3score,og.totalprice,og.real_totalprice from ".table_name('shop_order_goods')." og left join ".table_name('shop_order')." o on o.id=og.orderid where og.aid=".aid." $where and  (og.parent1=".mid." or og.parent2=".mid." or og.parent3=".mid.") order by og.id desc limit ".($pagenum*$pernum-$pernum).','.$pernum);
		if(!$datalist) $datalist = [];
		$newdatalist = [];
		foreach($datalist as $k=>$v){
			$data = [];
			$data['ordernum'] = $v['ordernum'];
			$data['name'] = $v['name'];
			$data['pic'] = $v['pic'];
			$data['num'] = $v['num'];
			$data['totalprice'] = $v['real_totalprice'];
			$data['createtime'] = date('Y-m-d H:i',$v['createtime']);
			$data['status'] = $v['status'];
			$memberinfo = Db::name('member')->where('id',$v['mid'])->field('nickname,headimg')->find();
			$data['nickname'] = $memberinfo['nickname'];
			$data['headimg'] = $memberinfo['headimg'];

			if($v['parent1'] == mid){
				$data['dengji'] = t('一级');
				if($v['parent1score'] > 0){
                    $v['parent1score'] = dd_money_format($v['parent1score'],$this->score_weishu);
					$data['commission'] = $v['parent1score'].t('积分');
				}else{
					$data['commission'] = $v['parent1commission'].'元';
				}
				$data['dannum'] = 0;
				if(getcustom('fengdanjiangli') && in_array($v['status'],[1,2,3])){ //是第几单
					$dannum = Db::name('shop_order_goods')->alias('og')->join('shop_order o ','o.id=og.orderid')->where('og.aid',aid)->where('og.proid',$v['proid'])->where('o.status','in','1,2,3')->where("og.parent1",mid)->where('o.paytime','<',$v['paytime'])->sum('og.num');
					$data['dannum'] = $dannum + 1;
				}
			}
			if($v['parent2'] == mid){
				$data['dengji'] = t('二级');
				if($v['parent2score'] > 0){
                    $v['parent2score'] = dd_money_format($v['parent2score'],$this->score_weishu);
					$data['commission'] = $v['parent2score'].t('积分');
				}else{
					$data['commission'] = $v['parent2commission'].'元';
				}
				$data['dannum'] = 0;
				if(getcustom('fengdanjiangli') && in_array($v['status'],[1,2,3])){ //是第几单
					$dannum = Db::name('shop_order_goods')->alias('og')->join('shop_order o ','o.id=og.orderid')->where('og.aid',aid)->where('og.proid',$v['proid'])->where('o.status','in','1,2,3')->where("og.parent2",mid)->where('o.paytime','<',$v['paytime'])->sum('og.num');
					$data['dannum'] = $dannum + 1;
				}
			}
			if($v['parent3'] == mid){
				$data['dengji'] = t('三级');
				if($v['parent3score'] > 0){
                    $v['parent2score'] = dd_money_format($v['parent2score'],$this->score_weishu);
					$data['commission'] = $v['parent3score'].t('积分');
				}else{
					$data['commission'] = $v['parent3commission'].'元';
				}
				$data['dannum'] = 0;
				if(getcustom('fengdanjiangli') && in_array($v['status'],[1,2,3])){ //是第几单
					$dannum = Db::name('shop_order_goods')->alias('og')->join('shop_order o ','o.id=og.orderid')->where('og.aid',aid)->where('og.proid',$v['proid'])->where('o.status','in','1,2,3')->where("og.parent3",mid)->where('o.paytime','<',$v['paytime'])->sum('og.num');
					$data['dannum'] = $dannum + 1;
				}
			}
			if(getcustom('showdownorderinfo')){
                $data['order_info'] = Db::name('shop_order')->field('id,aid,bid,mid,ordernum,title,totalprice,express_com,express_no,express_content,express_type')->where('ordernum',$v['ordernum'])->find();
			}else{
				$data['order_info'] = false;
			}
			$newdatalist[] = $data;
		}
		if(request()->isPost()){
			return $this->json(['data'=>$newdatalist]);
		}

		$count = Db::query("select count(1)c from ".table_name('shop_order_goods')." og left join ".table_name('shop_order')." o on o.id=og.orderid where og.aid=".aid." and (og.parent1=".mid." or og.parent2=".mid." or og.parent3=".mid.")");
		$count = 0 + $count[0]['c'];

		$count1 = Db::query("select count(1)c from ".table_name('shop_order_goods')." og left join ".table_name('shop_order')." o on o.id=og.orderid where og.aid=".aid." and o.status=0 and (og.parent1=".mid." or og.parent2=".mid." or og.parent3=".mid.")");
		$count1 = 0 + $count1[0]['c'];

		$count2 = Db::query("select count(1)c from ".table_name('shop_order_goods')." og left join ".table_name('shop_order')." o on o.id=og.orderid where og.aid=".aid." and (o.status=1 or o.status=2) and (og.parent1=".mid." or og.parent2=".mid." or og.parent3=".mid.")");
		$count2 = 0 + $count2[0]['c'];

		$count3 = Db::query("select count(1)c from ".table_name('shop_order_goods')." og left join ".table_name('shop_order')." o on o.id=og.orderid where og.aid=".aid." and o.status=3 and (og.parent1=".mid." or og.parent2=".mid." or og.parent3=".mid.")");
		$count3 = 0 + $count3[0]['c'];

		$commissionyj1 = Db::query("select sum(parent1commission)c from ".table_name('shop_order_goods')." og left join ".table_name('shop_order')." o on o.id=og.orderid where og.aid=".aid." and (o.status=1 or o.status=2) and og.parent1=".mid."");
		$commissionyj2 = Db::query("select sum(parent2commission)c from ".table_name('shop_order_goods')." og left join ".table_name('shop_order')." o on o.id=og.orderid where og.aid=".aid." and (o.status=1 or o.status=2) and og.parent2=".mid."");
		$commissionyj3 = Db::query("select sum(parent3commission)c from ".table_name('shop_order_goods')." og left join ".table_name('shop_order')." o on o.id=og.orderid where og.aid=".aid." and (o.status=1 or o.status=2) and og.parent3=".mid."");

		$commissionyj = 0 + $commissionyj1[0]['c'] + $commissionyj2[0]['c'] + $commissionyj3[0]['c'];
		
		$rdata = [];
		$rdata['count'] = $count;
		$rdata['count1'] = $count1;
		$rdata['count2'] = $count2;
		$rdata['count3'] = $count3;
		$rdata['commissionyj'] = round($commissionyj,2);
		$rdata['datalist'] = $newdatalist;
		$rdata['st'] = $st;

        $admin_set = Db::name('admin_set')->where('aid',aid)->find();
        $textset = json_decode($admin_set['textset'], true);
        $wallet_name  = [];
        $wallet_name['yongjin']= $textset['佣金'];
        $wallet_name['yue']= $textset['余额'];
        $rdata['wallet_name'] = $wallet_name;
 
		return $this->json($rdata);
	}

	//股东分红
	public function fenhong(){
		$pernum = 20;
		$pagenum = input('param.pagenum') ? input('param.pagenum') :1;
		$where = '';
		$st = input('param.st/d');
		if(!$st) $st = 1;

		//待结算
		$newoglist = [];
		if($st == 1){
			$rs = \app\commons\Fenhong::gdfenhong(aid,$this->sysset,[],0,time(),1,mid);
			$newoglist = $rs['oglist'];
			$commissionyj = $rs['commissionyj'];
		}

		if($st==2){//已结算
			$where = [];
			$where[] = ['aid','=',aid];
			$where[] = ['mid','=',mid];
			$where[] = ['type','=','fenhong'];
			$pernum = 20;
			$pagenum = input('param.pagenum');
			if(!$pagenum) $pagenum = 1;
			$count = Db::name('member_fenhonglog')->where($where)->count();
			$datalist = Db::name('member_fenhonglog')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
			if(!$datalist) $datalist = [];
			foreach($datalist as $k=>$v){
				if($v['ogids']){
					if($v['module'] == 'yuyue'){
						$oglist = Db::name('yuyue_order')->alias('og')->join('member m','og.mid=m.id')->field('og.ordernum,og.proname name,og.propic pic,og.num,og.totalprice real_totalprice,og.createtime,og.status,m.nickname,m.headimg')->where('og.id','in',$v['ogids'])->select()->toArray();
					}elseif($v['module'] == 'luckycollage'){
						$oglist = Db::name('lucky_collage_order')->alias('og')->join('member m','og.mid=m.id')->field('og.ordernum,og.proname name,og.propic pic,og.num,og.totalprice real_totalprice,og.createtime,og.status,m.nickname,m.headimg')->where('og.id','in',$v['ogids'])->select()->toArray();
                    }elseif($v['module'] == 'scoreshop'){
                        $oglist = Db::name('scoreshop_order_goods')->alias('og')->join('member m','og.mid=m.id')->field('og.ordernum,og.name,og.pic,og.num,og.totalmoney real_totalprice,og.createtime,og.status,m.nickname,m.headimg')->where('og.id','in',$v['ogids'])->select()->toArray();
                    }elseif($v['module'] == 'kecheng'){
                        $oglist = Db::name('kecheng_order')->alias('og')->join('member m','og.mid=m.id')->field('og.ordernum,og.title as name,og.pic,"1" as num,og.totalprice real_totalprice,og.createtime,og.status,m.nickname,m.headimg')->where('og.id','in',$v['ogids'])->select()->toArray();
                    }elseif($v['module'] == 'maidan'){
                        $oglist = Db::name('maidan_order')->alias('og')->join('member m','og.mid=m.id')->field('og.ordernum,og.title as name,"" as pic,"1" as num,og.paymoney real_totalprice,og.createtime,og.status,m.nickname,m.headimg')->where('og.id','in',$v['ogids'])->select()->toArray();
                    }elseif($v['module'] == 'shop' || empty($v['module'])){
						$oglist = Db::name('shop_order_goods')->alias('og')->join('member m','og.mid=m.id')->field('og.ordernum,og.name,og.pic,og.num,og.real_totalprice,og.createtime,og.status,m.nickname,m.headimg')->where('og.id','in',$v['ogids'])->select()->toArray();
					}else{
						$oglist = [];
					}
				}else{
					$oglist = [];
				}
				$datalist[$k]['oglist'] = $oglist;
			}
		}
		$rdata = [];
		$rdata['count'] = $count + count($newoglist);
		$rdata['commissionyj'] = round($commissionyj,2);
		if($st == 1){
			$rdata['datalist'] = $newoglist;
		}else{
			$rdata['datalist'] = $datalist;
		}
		$rdata['st'] = $st;
		return $this->json($rdata);
	}
	//团队分红
	public function teamfenhong(){
		$pernum = 20;
		$pagenum = input('param.pagenum') ? input('param.pagenum') :1;
		$where = '';
		$st = input('param.st/d');
		if(!$st) $st = 1;

		//待结算
		$newoglist = [];
		if($st == 1){
			$rs1 = \app\commons\Fenhong::teamfenhong(aid,$this->sysset,[],0,time(),1,mid);
			$rs2 = \app\commons\Fenhong::product_teamfenhong(aid,$this->sysset,[],0,time(),1,mid);
			$rs3 = \app\commons\Fenhong::level_teamfenhong(aid,$this->sysset,[],0,time(),1,mid);

			$newoglist = [];
			$commissionyj = 0;
			if($rs1 && $rs1['oglist']){
				$newoglist = array_merge($newoglist,$rs1['oglist']);
				$commissionyj += $rs1['commissionyj'];
			}
			if($rs2 && $rs2['oglist']){
				$newoglist = array_merge($newoglist,$rs2['oglist']);
				$commissionyj += $rs2['commissionyj'];
			}
			if($rs3 && $rs3['oglist']){
				$newoglist = array_merge($newoglist,$rs3['oglist']);
				$commissionyj += $rs3['commissionyj'];
			}
            if(getcustom('teamfenhong_jicha')){
                $rs4 = \app\commons\Fenhong::teamfenhong_jicha(aid,$this->sysset,[],0,time(),1,mid);
                $newoglist = array_merge($newoglist,$rs4['oglist']);
                $commissionyj += $rs4['commissionyj'];
            }
            if(getcustom('team_leader_fh')){
                $rs5 = \app\commons\Fenhong::teamleader_fenhong(aid,$this->sysset,[],0,time(),1,mid);
                $newoglist = array_merge($newoglist,$rs5['oglist']);
                $commissionyj += $rs5['commissionyj'];
            }
			
		}
        $moeny_weishu = 2;
        if(getcustom('fenhong_money_weishu')){
            $moeny_weishu = Db::name('admin_set')->where('aid',aid)->value('fenhong_money_weishu');
        }
		if($st==2){//已结算
			$where = [];
			$where[] = ['aid','=',aid];
			$where[] = ['mid','=',mid];
			$where[] = ['type','=','teamfenhong'];
			$pernum = 20;
			$pagenum = input('param.pagenum');
			if(!$pagenum) $pagenum = 1;
			$count = Db::name('member_fenhonglog')->where($where)->count();
			$datalist = Db::name('member_fenhonglog')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
			if(!$datalist) $datalist = [];
			foreach($datalist as $k=>$v){
				if($v['ogids']){
					if($v['module'] == 'yuyue'){
						$oglist = Db::name('yuyue_order')->alias('og')->join('member m','og.mid=m.id')->field('og.ordernum,og.proname name,og.propic pic,og.num,og.totalprice real_totalprice,og.createtime,og.status,m.nickname,m.headimg')->where('og.id','in',$v['ogids'])->select()->toArray();
					}elseif($v['module'] == 'luckycollage'){
						$oglist = Db::name('lucky_collage_order')->alias('og')->join('member m','og.mid=m.id')->field('og.ordernum,og.proname name,og.propic pic,og.num,og.totalprice real_totalprice,og.createtime,og.status,m.nickname,m.headimg')->where('og.id','in',$v['ogids'])->select()->toArray();
                    }elseif($v['module'] == 'scoreshop'){
                        $oglist = Db::name('scoreshop_order_goods')->alias('og')->join('member m','og.mid=m.id')->field('og.ordernum,og.name,og.pic,og.num,og.totalmoney real_totalprice,og.createtime,og.status,m.nickname,m.headimg')->where('og.id','in',$v['ogids'])->select()->toArray();
                    }elseif($v['module'] == 'kecheng'){
                        $oglist = Db::name('kecheng_order')->alias('og')->join('member m','og.mid=m.id')->field('og.ordernum,og.title as name,og.pic,"1" as num,og.totalprice real_totalprice,og.createtime,og.status,m.nickname,m.headimg')->where('og.id','in',$v['ogids'])->select()->toArray();
                    }elseif($v['module'] == 'maidan'){
                        $oglist = Db::name('maidan_order')->alias('og')->join('member m','og.mid=m.id')->field('og.ordernum,og.title as name,"" as pic,"1" as num,og.paymoney real_totalprice,og.createtime,og.status,m.nickname,m.headimg')->where('og.id','in',$v['ogids'])->select()->toArray();
                    }elseif($v['module'] == 'shop' || empty($v['module'])){
						$oglist = Db::name('shop_order_goods')->alias('og')->join('member m','og.mid=m.id')->field('og.ordernum,og.name,og.pic,og.num,og.real_totalprice,og.createtime,og.status,m.nickname,m.headimg')->where('og.id','in',$v['ogids'])->select()->toArray();
					}else{
						$oglist = [];
					}
				}else{
					$oglist = [];
				}
				$datalist[$k]['oglist'] = $oglist;
                $datalist[$k]['commission'] = dd_money_format($v['commission'],$moeny_weishu);
            }
		}
		$rdata = [];
		$rdata['count'] = $count + count($newoglist);
        
		$rdata['commissionyj'] = dd_money_format($commissionyj,$moeny_weishu);
		if($st == 1){
			$rdata['datalist'] = $newoglist;
		}else{
			$rdata['datalist'] = $datalist;
		}
		$rdata['st'] = $st;
		return $this->json($rdata);
	}
	//区域代理分红
	public function areafenhong(){
		$pernum = 20;
		$pagenum = input('param.pagenum') ? input('param.pagenum') :1;
		$where = '';
		$st = input('param.st/d');
		if(!$st) $st = 1;

		//待结算
		$newoglist = [];
		if($st == 1){
			$rs = \app\commons\Fenhong::areafenhong(aid,$this->sysset,[],0,time(),1,mid);
			$newoglist = $rs['oglist'];
			$commissionyj = $rs['commissionyj'];
		}
        $moeny_weishu = 2;
        if(getcustom('fenhong_money_weishu')){
            $moeny_weishu = Db::name('admin_set')->where('aid',aid)->value('fenhong_money_weishu');
        }
		if($st==2){//已结算
			$where = [];
			$where[] = ['aid','=',aid];
			$where[] = ['mid','=',mid];
			$where[] = ['type','=','areafenhong'];
			$pernum = 20;
			$pagenum = input('param.pagenum');
			if(!$pagenum) $pagenum = 1;
			$count = Db::name('member_fenhonglog')->where($where)->count();
			$datalist = Db::name('member_fenhonglog')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
			if(!$datalist) $datalist = [];
			foreach($datalist as $k=>$v){
				if($v['ogids']){
					if($v['module'] == 'yuyue'){
						$oglist = Db::name('yuyue_order')->alias('og')->join('member m','og.mid=m.id')->field('og.ordernum,og.proname name,og.propic pic,og.num,og.totalprice real_totalprice,og.createtime,og.status,m.nickname,m.headimg')->where('og.id','in',$v['ogids'])->select()->toArray();
					}elseif($v['module'] == 'luckycollage'){
						$oglist = Db::name('lucky_collage_order')->alias('og')->join('member m','og.mid=m.id')->field('og.ordernum,og.proname name,og.propic pic,og.num,og.totalprice real_totalprice,og.createtime,og.status,m.nickname,m.headimg')->where('og.id','in',$v['ogids'])->select()->toArray();
                    }elseif($v['module'] == 'scoreshop'){
                        $oglist = Db::name('scoreshop_order_goods')->alias('og')->join('member m','og.mid=m.id')->field('og.ordernum,og.name,og.pic,og.num,og.totalmoney real_totalprice,og.createtime,og.status,m.nickname,m.headimg')->where('og.id','in',$v['ogids'])->select()->toArray();
                    }elseif($v['module'] == 'kecheng'){
                        $oglist = Db::name('kecheng_order')->alias('og')->join('member m','og.mid=m.id')->field('og.ordernum,og.title as name,og.pic,"1" as num,og.totalprice real_totalprice,og.createtime,og.status,m.nickname,m.headimg')->where('og.id','in',$v['ogids'])->select()->toArray();
                    }elseif($v['module'] == 'maidan'){
                        $oglist = Db::name('maidan_order')->alias('og')->join('member m','og.mid=m.id')->field('og.ordernum,og.title as name,"" as pic,"1" as num,og.paymoney real_totalprice,og.createtime,og.status,m.nickname,m.headimg')->where('og.id','in',$v['ogids'])->select()->toArray();
                    }elseif($v['module'] == 'shop' || empty($v['module'])){
						$oglist = Db::name('shop_order_goods')->alias('og')->join('member m','og.mid=m.id')->field('og.ordernum,og.name,og.pic,og.num,og.real_totalprice,og.createtime,og.status,m.nickname,m.headimg')->where('og.id','in',$v['ogids'])->select()->toArray();
					}else{
						$oglist = [];
					}
				}else{
					$oglist = [];
				}
				$datalist[$k]['oglist'] = $oglist;
                $datalist[$k]['commission'] = dd_money_format($v['commission'],$moeny_weishu);
            }
		}
		$rdata = [];
		$rdata['count'] = $count + count($newoglist);
		$rdata['commissionyj'] = dd_money_format($commissionyj,$moeny_weishu);
		if($st == 1){
			$rdata['datalist'] = $newoglist;
		}else{
			$rdata['datalist'] = $datalist;
		}
		$rdata['st'] = $st;
		return $this->json($rdata);
	}

	//分红订单
	public function fhorder(){
		$pernum = 20;
		$pagenum = input('param.pagenum') ? input('param.pagenum') :1;
		$where = '';
		$st = input('param.st/d');
		if(!$st) $st = 1;

		$fhjiesuanbusiness = $this->sysset['fhjiesuanbusiness'];
        $fhjiesuantype = $this->sysset['fhjiesuantype'];
		$fhjiesuantime_type = $this->sysset['fhjiesuantime_type'];

		//待结算
		$newoglist = [];
		if($st == 1){
			$rs1 = \app\commons\Fenhong::gdfenhong(aid,$this->sysset,[],0,time(),1,mid);
			$rs2 = \app\commons\Fenhong::teamfenhong(aid,$this->sysset,[],0,time(),1,mid);
			$rs3 = \app\commons\Fenhong::product_teamfenhong(aid,$this->sysset,[],0,time(),1,mid);
			$rs4 = \app\commons\Fenhong::level_teamfenhong(aid,$this->sysset,[],0,time(),1,mid);
			$rs5 = \app\commons\Fenhong::areafenhong(aid,$this->sysset,[],0,time(),1,mid);

			$newoglist = [];
			$commissionyj = 0;
			if($rs1 && $rs1['oglist']){
				$newoglist = array_merge($newoglist,$rs1['oglist']);
				$commissionyj += $rs1['commissionyj'];
			}
			if($rs2 && $rs2['oglist']){
			    foreach($rs2['oglist'] as $key=>$val){
                    $rs2['oglist'][$key]['type'] = 'teamfenhong';
                }
				$newoglist = array_merge($newoglist,$rs2['oglist']);
				$commissionyj += $rs2['commissionyj'];
			}
			if($rs3 && $rs3['oglist']){
				$newoglist = array_merge($newoglist,$rs3['oglist']);
				$commissionyj += $rs3['commissionyj'];
			}
			if($rs4 && $rs4['oglist']){
				$newoglist = array_merge($newoglist,$rs4['oglist']);
				$commissionyj += $rs4['commissionyj'];
			}
			if($rs5 && $rs5['oglist']){
				$newoglist = array_merge($newoglist,$rs5['oglist']);
				$commissionyj += $rs5['commissionyj'];
			}
            if(getcustom('car_hailing')){
                $cg_rs1 = \app\commons\CarhailingFenhong::gdfenhong(aid,$this->sysset,[],0,time(),1,mid);
                if($cg_rs1 && $cg_rs1['oglist']){
                    foreach($cg_rs1['oglist'] as &$val){
                        $val['name'] = $val['title'];
                        $val['real_totalprice'] = $val['totalprice'];
                    }
                    $newoglist = array_merge($newoglist,$cg_rs1['oglist']);
                    $commissionyj += $cg_rs1['commissionyj'];
                }
                $cg_rs2 = \app\commons\CarhailingFenhong::teamfenhong(aid,$this->sysset,[],0,time(),1,mid);
                if($cg_rs2 && $cg_rs2['oglist']){
                    foreach($cg_rs2['oglist'] as &$val){
                        $val['name'] = $val['title'];
                        $val['real_totalprice'] = $val['totalprice'];
                    }
                    $newoglist = array_merge($newoglist,$cg_rs2['oglist']);
                    $commissionyj += $cg_rs2['commissionyj'];
                }
            }
		}
        $moeny_weishu = 2;
        if(getcustom('fenhong_money_weishu')){
            $moeny_weishu = Db::name('admin_set')->where('aid',aid)->value('fenhong_money_weishu');
        }
		if($st==2){//已结算
			$where = [];
			$where[] = ['aid','=',aid];
			$where[] = ['mid','=',mid];
			$pernum = 20;
			$pagenum = input('param.pagenum');
			if(!$pagenum) $pagenum = 1;
			$count = Db::name('member_fenhonglog')->where($where)->count();
			$datalist = Db::name('member_fenhonglog')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
			if(!$datalist) $datalist = [];
			foreach($datalist as $k=>$v){
				if($v['ogids']){
					if($v['module'] == 'yuyue'){
						$oglist = Db::name('yuyue_order')->alias('og')->join('member m','og.mid=m.id')->field('og.ordernum,og.proname name,og.propic pic,og.num,og.totalprice real_totalprice,og.createtime,og.status,m.nickname,m.headimg')->where('og.id','in',$v['ogids'])->select()->toArray();
					}elseif($v['module'] == 'luckycollage'){
						$oglist = Db::name('lucky_collage_order')->alias('og')->join('member m','og.mid=m.id')->field('og.ordernum,og.proname name,og.propic pic,og.num,og.totalprice real_totalprice,og.createtime,og.status,m.nickname,m.headimg')->where('og.id','in',$v['ogids'])->select()->toArray();
                    }elseif($v['module'] == 'scoreshop'){
                        $oglist = Db::name('scoreshop_order_goods')->alias('og')->join('member m','og.mid=m.id')->field('og.ordernum,og.name,og.pic,og.num,og.totalmoney real_totalprice,og.createtime,og.status,m.nickname,m.headimg')->where('og.id','in',$v['ogids'])->select()->toArray();
                    }elseif($v['module'] == 'kecheng'){
                        $oglist = Db::name('kecheng_order')->alias('og')->join('member m','og.mid=m.id')->field('og.ordernum,og.title as name,og.pic,"1" as num,og.totalprice real_totalprice,og.createtime,og.status,m.nickname,m.headimg')->where('og.id','in',$v['ogids'])->select()->toArray();
                    }elseif($v['module'] == 'maidan'){
                        $oglist = Db::name('maidan_order')->alias('og')->join('member m','og.mid=m.id')->field('og.ordernum,og.title as name,"" as pic,"1" as num,og.paymoney real_totalprice,og.createtime,og.status,m.nickname,m.headimg')->where('og.id','in',$v['ogids'])->select()->toArray();
                    }elseif($v['module'] == 'car_hailing'){
					    if(getcustom('car_hailing')){
                            $oglist = Db::name('car_hailing_order')->alias('og')->join('member m','og.mid=m.id')->field('og.ordernum,og.title as name,"" as pic,"1" as num,og.totalprice real_totalprice,og.createtime,og.status,m.nickname,m.headimg')->where('og.id','in',$v['ogids'])->select()->toArray();
                        }
                    }elseif($v['module'] == 'shop' || empty($v['module'])){
						$oglist = Db::name('shop_order_goods')->alias('og')->join('member m','og.mid=m.id')->field('og.ordernum,og.name,og.pic,og.num,og.real_totalprice,og.createtime,og.status,m.nickname,m.headimg')->where('og.id','in',$v['ogids'])->select()->toArray();
					}elseif($v['module'] == 'cashier_order'){
                        if(getcustom('fenhong_cashier_order',$aid)){
                            $oglist = Db::name('cashier_order_goods')
                                ->alias('og')
                                ->join('cashier_order o','og.orderid=o.id')
                                ->join('member m','o.mid=m.id')
                                ->field('o.ordernum,og.proname as name,og.propic as pic,og.num,og.real_totalprice,og.createtime,o.status,m.nickname,m.headimg')
                                ->where('og.id','in',$v['ogids'])
                                ->select()
                                ->toArray();
                        }
                    }else{
						$oglist = [];
					}
				}else{
					$oglist = [];
				}
				$datalist[$k]['oglist'] = $oglist;
                $datalist[$k]['commission'] = dd_money_format($v['commission'],$moeny_weishu);
            }
		}
		$rdata = [];
		$rdata['count'] = $count + count($newoglist);
		$rdata['commissionyj'] = dd_money_format($commissionyj,$moeny_weishu);
		if($st == 1){
			$rdata['datalist'] = $newoglist;
		}else{
			$rdata['datalist'] = $datalist;
		}
		$rdata['st'] = $st;
		return $this->json($rdata);
	}
    //股东分红
    public function touzifenhong(){
	    if(getcustom('touzi_fenhong')){
            
            $st = input('param.st/d');
            if(!$st) $st = 1;

            //待结算
            $newoglist = [];
            if($st == 1){
                $rs = \app\commons\Fenhong::touzi_fenhong(aid,$this->sysset,[],0,time(),1,mid);
                $newoglist = $rs['oglist'];
                $commissionyj = $rs['commissionyj'];
            }
            $moeny_weishu = 2;
            if(getcustom('fenhong_money_weishu')){
                $moeny_weishu = Db::name('admin_set')->where('aid',aid)->value('fenhong_money_weishu');
            }
            if($st==2){//已结算
                $where = [];
                $where[] = ['aid','=',aid];
                $where[] = ['mid','=',mid];
                $where[] = ['type','=','touzi_fenhong'];
                $pernum = 20;
                $pagenum = input('param.pagenum');
                if(!$pagenum) $pagenum = 1;
                $count = Db::name('member_fenhonglog')->where($where)->count();
                $datalist = Db::name('member_fenhonglog')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
                if(!$datalist) $datalist = [];
                foreach($datalist as $k=>$v){
                    if($v['ogids']){
                        $oglist = Db::name('shop_order_goods')->alias('og')->join('member m','og.mid=m.id')->field('og.ordernum,og.name,og.pic,og.num,og.real_totalprice,og.createtime,og.status,m.nickname,m.headimg')->where('og.id','in',$v['ogids'])->select()->toArray();
                    }else{
                        $oglist = [];
                    }
                    $datalist[$k]['oglist'] = $oglist;
                    $datalist[$k]['commission'] = dd_money_format($v['commission'],$moeny_weishu);
                }
            }
            $rdata = [];
            $rdata['count'] = $count + count($newoglist);
            $rdata['commissionyj'] = dd_money_format($commissionyj,$moeny_weishu);
            if($st == 1){
                $rdata['datalist'] = $newoglist;
            }else{
                $rdata['datalist'] = $datalist;
            }
            $rdata['st'] = $st;
            return $this->json($rdata);
        }
       
    }

    //门店服务订单
    public function orderMendian(){
        if(getcustom('plug_zhiming')) {
            $pernum = 20;
            $pagenum = input('param.pagenum') ? input('param.pagenum') :1;
            $st = input('param.st/d');
            if(!$st) $st = 0;
            $admin_user_ids = Db::name('admin_user')->where('aid', aid)->where('mid', mid)->where('mdid', '>', 0)->column('id');

            $where[] = ['order.aid','=',aid];
            $where[] = ['order.bid','=',bid];
            $where[] = ['order.uid', 'in', $admin_user_ids];
            $order = 'id desc';
            if(input('param.keyword')) $where[] = ['member.nickname','like','%'.trim(input('param.keyword')).'%'];
            if(input('?param.status') && input('param.status')!=='') $where[] = ['member_moneylog.status','=',input('param.status')];
            $datalist = Db::name('hexiao_order')->alias('order')->field('member.nickname,member.headimg,order.*')->leftJoin('member member','member.id=order.mid')->where($where)->page($pagenum,$pernum)->order($order)->select()->toArray();
            if(!$datalist) $datalist = [];
            $newdatalist = [];
            foreach($datalist as $k=>$v){
                $data = [];
                $data['ordernum'] = $v['ordernum'];
                $data['name'] = $v['title'];
                $data['pic'] = $v['pic'];
                $data['num'] = $v['num'];
                $data['createtime'] = date('Y-m-d H:i',$v['createtime']);
                $data['status'] = $v['status'];
                $data['nickname'] = $v['nickname'];
                $data['headimg'] = $v['headimg'];
                $newdatalist[] = $data;
            }
            if(request()->isPost()){
                return $this->json(['data'=>$newdatalist]);
            }

            $rdata = [];
            $rdata['count'] = 0;
            $rdata['commissionyj'] = 0;
            $rdata['datalist'] = $newdatalist;
            $rdata['st'] = $st;
            return $this->json($rdata);
        }

        return $this->json([]);
    }
	
	//分红记录
	public function fhlog(){
		$st = input('param.st');
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['mid','=',mid];
		$pernum = 20;
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$datalist = Db::name('member_fenhonglog')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		if(!$datalist) $datalist = [];
        $moeny_weishu = 2;
        if(getcustom('fenhong_money_weishu')){
            $moeny_weishu = Db::name('admin_set')->where('aid',aid)->value('fenhong_money_weishu');
        }
        foreach ($datalist as $key=>$val){
            $datalist[$key]['commission'] = dd_money_format($val['commission'],$moeny_weishu);
        }
		return $this->json(['status'=>1,'data'=>$datalist]);
	}
	//分享海报
	public function poster(){

        if(getcustom('member_realname_verify')) {
            $realname_set = Db::name('member_realname_set')->where('aid', aid)->find();
            if ($realname_set['status'] == 1 && $realname_set['view_poster'] == 0 && $this->member['realname_status'] != 1){
                return $this->json(['status'=>-4,'msg'=>'请先实名认证','url'=>'/pagesExt/my/setrealname']);
            }
        }

		$member = $this->member;
		$platform = platform;
		$page = $this->indexurl;
		$scene = 'pid_'.$this->member['id'];
		//if($platform == 'mp' || $platform == 'h5' || $platform == 'app'){
		//	$page = PRE_URL .'/h5/'.aid.'.html#'. $page;
		//}

		if(input('param.posterid')){
			$posterset = Db::name('admin_set_poster')->where('aid',aid)->where('type','index')->where('platform',$platform)->where('id',input('param.posterid'))->find();
		}else{
			$posterset = Db::name('admin_set_poster')->where('aid',aid)->where('type','index')->where('platform',$platform)->order('id')->find();
		}
		$posterdata = Db::name('member_poster')->where('aid',aid)->where(['mid'=>mid,'posterid'=>$posterset['id']])->find();
		$sysset = Db::name('admin_set')->field('id,name,logo,desc')->where('aid',aid)->find();
		if(getcustom('app_reg') && $platform == 'app'){
			$page = 'pages/index/reg';
			$scene.='-fromapp_1'; 
		}
        if(getcustom('product_price_rate')){
            $scene.='-priceRate_'.$member['price_rate'];
        }
		if(!$posterdata){
            $yqcode = '';
            if(getcustom('reg_invite_code')){
                $yqcode = !empty($member['yqcode'])?$member['yqcode']:'';
            }
			$textReplaceArr = [
				'[头像]'=>$member['headimg'],
				'[昵称]'=>$member['nickname'],
				'[姓名]'=>$member['realname'],
				'[手机号]'=>$member['tel'],
				'[商城名称]'=>$sysset['name'],
                '[邀请码]'=>$yqcode ,
			];
			$poster = $this->_getposter(aid,0,$platform,$posterset['content'],$page,$scene,$textReplaceArr);
			$posterdata = [];
			$posterdata['aid'] = aid;
			$posterdata['mid'] = $member['id'];
			$posterdata['scene'] = $scene;
			$posterdata['page'] = $page;
			$posterdata['poster'] = $poster;
			$posterdata['posterid'] = $posterset['id'];
			$posterdata['createtime'] = time();
			Db::name('member_poster')->insert($posterdata);
		}

		$posterlist = Db::name('admin_set_poster')->field('id')->where('aid',aid)->where('type','index')->where('platform',$platform)->order('id')->select()->toArray();
        if(getcustom('coupon_xianxia_buy')){
            $is_show_title = 0;
        }
		$rdata = [];
        $rdata['is_show_title'] = $is_show_title;
		$rdata['poster'] = $posterdata['poster'];
		$rdata['guize'] = $posterset['guize'];
		$rdata['posterid'] = $posterset['id'];
		$rdata['posterlist'] = $posterlist;
		$rdata['postercount'] = count($posterlist);
		return $this->json($rdata);
	}
	//给余额
	public function givemoney(){
		$userlevel = Db::name('member_level')->where('aid',aid)->where('id',$this->member['levelid'])->find();
		if($userlevel['team_givemoney'] == 0) return $this->json(['status'=>0,'msg'=>'给下级转账功能未开启']);

        if(getcustom('member_lock')){
            $field = 'lock_withdraw_givemoney';
            $userinfo = Db::name('member')->where('id',mid)->field($field)->find();
            if($userinfo['lock_withdraw_givemoney'] == 1){                
                return $this->json(['status'=>0,'msg'=>'账号已锁定，请联系管理员处理！']);
            }            
        }
		$id = input('param.id/d');
		$money = input('param.money/f');
		if($money <= 0) return $this->json(['status'=>0,'msg'=>'转账金额必须大于0']);
		$tomember = Db::name('member')->where('aid',aid)->where('id',$id)->find();
		if(!$tomember) return $this->json(['status'=>0,'msg'=>'用户不存在']);
		if($money > $this->member['money']) return $this->json(['status'=>0,'msg'=>t('余额').'不足']);
		\app\commons\Member::addmoney(aid,$id,$money,$this->member['nickname'].'-转账');
		\app\commons\Member::addmoney(aid,mid,-$money,'转账给'.$tomember['nickname']);
		return $this->json(['status'=>1,'msg'=>'转账成功']);
	}
	//给积分
	public function givescore(){
		$userlevel = Db::name('member_level')->where('aid',aid)->where('id',$this->member['levelid'])->find();
		if($userlevel['team_givescore'] == 0) return $this->json(['status'=>0,'msg'=>'给下级转账功能未开启']);
        
        if(getcustom('member_lock')){
            $field = 'lock_withdraw_givemoney';
            $userinfo = Db::name('member')->where('id',mid)->field($field)->find();
            if($userinfo['lock_withdraw_givemoney'] == 1){                
                return $this->json(['status'=>0,'msg'=>'账号已锁定，请联系管理员处理！']);
            }            
        }
		$id = input('param.id/d');
		$score = input('param.score/d');
		if($score <= 0) return $this->json(['status'=>0,'msg'=>'转账数量必须大于0']);
		$tomember = Db::name('member')->where('aid',aid)->where('id',$id)->find();
		if(!$tomember) return $this->json(['status'=>0,'msg'=>'用户不存在']);
		if($score > $this->member['score']) return $this->json(['status'=>0,'msg'=>t('积分').'不足']);
		\app\commons\Member::addscore(aid,$id,$score,$this->member['nickname'].'-转账');
		\app\commons\Member::addscore(aid,mid,-$score,'转账给'.$tomember['nickname']);
		return $this->json(['status'=>1,'msg'=>'转账成功']);
	}

	//升级（分销商给下级升级
	public function levelUp()
    {
        if(getcustom('member_levelup_givechild')) {
            // if(!getcustom('plug_zhiming')) {
            //     return $this->json(['status'=>0,'msg'=>'操作失败']);
            // }
            $mid = input('param.mid/d');
            $levelId = input('param.levelId/d');            
            $tomember = Db::name('member')->where('aid',aid)->where('id',$mid)->find();
            if(!$tomember) return $this->json(['status'=>0,'msg'=>'用户不存在']);
            if(!in_array(mid,explode(',',$tomember['path']))) {
                return $this->json(['status'=>0,'msg'=>'无权限操作此用户']);
            }

            $memberlevel = Db::name('member_level')->where('aid',aid)->where('id',$tomember['levelid'])->find();
            $tolevel = Db::name('member_level')->where('aid',aid)->where('id',$levelId)->find();
            if($tolevel['sort'] <= $memberlevel['sort']) {
                return $this->json(['status'=>0,'msg'=>'只能为用户升级']);
            }

            $userlevel = Db::name('member_level')->where('aid',aid)->where('id',$this->member['levelid'])->find();
            if($userlevel['sort'] <= $tolevel['sort']) return $this->json(['status'=>0,'msg'=>'给下级升级需低于推广员当前等级']);
            if($userlevel['team_levelup'] == 0) return $this->json(['status'=>0,'msg'=>'给下级升级功能未开启']);
            
            if($userlevel['team_levelup'] == 1){
                $usenum = Db::name('member_levelup_uesnum')->where('aid',aid)->where('mid',mid)->find();
                if(!$usenum){
                    \app\commons\Member::addMemberLevelupNum(aid,mid,$userlevel['team_levelup_data']);
                }else{
                    if($usenum['levelid'] !=$userlevel['levelid']){
                        $olduserlevel = Db::name('member_level')->where('aid',aid)->where('id',$usenum['levelid'])->find();
                        if(!$olduserlevel || $olduserlevel['sort'] < $userlevel['sort']){
                            \app\commons\Member::addMemberLevelupNum(aid,mid,$userlevel['team_levelup_data']);
                        }                        
                    }
                }
            }
            $usenum = Db::name('member_levelup_uesnum')->where('aid',aid)->where('mid',mid)->where('levelupid',$levelId)->find();
            if(!$usenum || $usenum['num'] <= 0){
                return $this->json(['status'=>0,'msg'=>'给下级升级数量不足']);
            }
            // if($userlevel['team_levelup_num'] <= 0) return $this->json(['status'=>0,'msg'=>'给下级升级数量不足']);

            // $count = Db::name('member_levelup_order')->where('aid', aid)->where('from_mid', mid)->count();
            // if($count >= $userlevel['team_levelup_num']) {
            //     return $this->json(['status'=>0,'msg'=>'给下级升级数量不足']);
            // }

            $order = [
                'aid' => aid,
                'mid' => $mid,
                'from_mid' => mid,
                'pid' => $this->member['pid'],
                'levelid' => $levelId,
                'title' => '推广员为下级升级，推广员：'.$this->member['nickname'],
                'totalprice' => 0,
                'createtime' => time(),
                'beforelevelid' => $tomember['levelid'],
                'form0' => '类型^_^推广员为下级升级',
                'form1' => '推广员^_^'.$this->member['nickname'],
                'platform' => platform,
                'status' => 2
            ];
            Db::name('member_levelup_order')->insert($order);

            $newlv = Db::name('member_level')->where('aid',aid)->where('id',$levelId)->find();
            if($newlv['yxqdate'] > 0){
                $levelendtime = strtotime(date('Y-m-d')) + 86400 + 86400 * $newlv['yxqdate'];
            }else{
                $levelendtime = 0;
            }
            Db::name('member')->where('id',$mid)->update(['levelid'=>$newlv['id'],'levelstarttime'=>time(),'levelendtime'=>$levelendtime]);
            Db::name('member_levelup_uesnum')->where('aid',aid)->where('mid',mid)->where('levelupid',$levelId)->dec('num',1)->update();
            if($newlv['team_levelup'] == 1){
                \app\commons\Member::addMemberLevelupNum(aid,$mid,$newlv['team_levelup_data']);
            }
            

            \app\commons\Wechat::updatemembercard(aid,$mid);

            $tmplcontent = [];
            $tmplcontent['first'] = '恭喜您成功升级为'.$newlv['name'];
            $tmplcontent['remark'] = '点击进入查看~';
            $tmplcontent['keyword1'] = $newlv['name']; //会员等级
            $tmplcontent['keyword2'] = '已生效';//审核状态
            \app\commons\Wechat::sendtmpl(aid,$mid,'tmpl_uplv',$tmplcontent,m_url('pages/my/usercenter'));

            return $this->json(['status'=>1,'msg'=>'升级成功']);
        }
    }


    //佣金明细
    public function orderYeji(){
        $st = input('param.st');
        $level = Db::name('member_level')->where('id', $this->member['levelid'])->find();
        if(empty($level['tongji_yeji']) || $level['tongji_yeji'] != 1) {
            $datalist = [];
            return $this->json(['status'=>1,'data'=>$datalist]);
        }
        $pernum = 15;
        $pagenum = input('post.pagenum');
        if(!$pagenum) $pagenum = 1;
        $childrenmids = \app\commons\Member::getdownmids(aid,mid);
        $childrenmids[] = mid;
        $date = strtotime(date('Y-m-d'));

        for ($i=($pagenum-1)*$pernum;$i<$pagenum*$pernum;$i++) {
            $start = $date-$i*86400;
            $end = $start + 86399;
            $where = [];
            $where[] = ['aid','=',aid];
            $where[] = ['mid','in',$childrenmids];
            $where[] = ['status','in',[1,2,3]];
            $where[] = ['createtime','between',[$start,$end]];
            $num = Db::name('shop_order_goods')->where($where)->sum('num');
            $datalist[] = [
                'i'=>$i,
                'date' => date('Y-m-d',$start),
                'num' => $num
            ];
        }

        if(!$datalist) $datalist = [];
        return $this->json(['status'=>1,'data'=>$datalist]);
    }


	//我的上下级
	public function teamline(){
		
		$userinfo = Db::name('member')->field('id,nickname,headimg,levelid,path')->where('aid',aid)->where('id',mid)->find();
		$userlevel = Db::name('member_level')->where('aid',aid)->where('id',$userinfo['levelid'])->find();

		$pernum = 20;
		$pagenum = input('post.pagenum');
		$keyword = input('post.keyword');
		$where2 = "1=1";
        $query_params = [];//query查询条件
		if($keyword){
            $where2 = "(nickname like :keyword or realname like :keyword2 or tel like :keyword3)";
            $query_params['keyword'] = $query_params['keyword2'] = $query_params['keyword3'] = '%'.$keyword.'%';
        }
		if(!$pagenum) $pagenum = 1;
		if(!mid){
			$datalist = [];
		}else{
			$datalist = Db::name('member')->where('aid',aid)->where('find_in_set('.mid.',path)'.($userinfo['path'] ? ' or id in ('.$userinfo['path'].')' : ''))->whereRaw($where2,$query_params)->order('id')->page($pagenum,$pernum)->select()->toArray();
			//echo Db::getlastsql();
		}

		foreach($datalist as $k=>$v){
			$level = Db::name('member_level')->where('aid',aid)->where('id',$v['levelid'])->find();
            $datalist[$k]['levelname'] = $level['name'];
            $datalist[$k]['levelsort'] = $level['sort'];
			if($userlevel['team_showtel'] == 0){
				$datalist[$k]['tel'] = '';
			}
		}
		
		$rdata = [];
		$rdata['datalist'] = $datalist;
		$rdata['userinfo'] = $userinfo;
		$rdata['userlevel'] = $userlevel;
		$rdata['st'] = $downdeep;
		return $this->json($rdata);
	}
	//我的同等级会员
	public function sameline(){
		$userinfo = Db::name('member')->field('id,nickname,headimg,levelid,path')->where('aid',aid)->where('id',mid)->find();
		$userlevel = Db::name('member_level')->where('aid',aid)->where('id',$userinfo['levelid'])->find();

		$pernum = 20;
		$pagenum = input('post.pagenum');
		$keyword = input('post.keyword');
		$where2 = "1=1";
        $query_params = [];//query查询条件
		if($keyword){
            $where2 = "(nickname like :keyword or realname like :keyword2 or tel like :keyword3)";
            $query_params['keyword'] = $query_params['keyword2'] = $query_params['keyword3'] = '%'.$keyword.'%';
        } 
		if(!$pagenum) $pagenum = 1;
		if(!mid){
			$datalist = [];
		}else{
			$datalist = Db::name('member')->where('aid',aid)->where('levelid',$userinfo['levelid'])->whereRaw($where2,$query_params)->order('id')->page($pagenum,$pernum)->select()->toArray();
			//echo Db::getlastsql();
		}

		foreach($datalist as $k=>$v){
			$level = Db::name('member_level')->where('aid',aid)->where('id',$v['levelid'])->find();
            $datalist[$k]['levelname'] = $level['name'];
            $datalist[$k]['levelsort'] = $level['sort'];
			if($userlevel['team_showtel'] == 0){
				$datalist[$k]['tel'] = '';
			}
		}
		
		$rdata = [];
		$rdata['datalist'] = $datalist;
		$rdata['userinfo'] = $userinfo;
		$rdata['userlevel'] = $userlevel;
		$rdata['st'] = $downdeep;
		return $this->json($rdata);
	}

	//佣金排行
	public function commissionrank(){
		$pernum = 10;
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$sysset = $this->sysset;
		if($sysset['rank_type']){
			$sysset['rank_type'] = explode(',',$sysset['rank_type']);
		}
		if($sysset['rank_status']==0){
			$data = [];
			$data['status'] = 0;
			return $this->json(['status'=>0,'data'=>$data]);
		}
		$downmids = \app\commons\Member::getdownmids(aid,mid,1);
        $fxordermoney = 0 + Db::name('shop_order_goods')->where('status','in','1,2,3')->where('mid','in',$downmids)->sum('totalprice');
		
		$rdata = [];
		$rdata['totalcommission'] = $this->member['totalcommission'];
		$rdata['commission'] = $this->member['commission'];
		$rdata['totalteamprice'] = round($fxordermoney,2);
		
        if(getcustom('yx_queue_free')){
            if($sysset['rank_queue_free_status'] ==1){
                $rdata['commission'] =  dd_money_format($this->member['commission'] + $this->member['totalqueuecommission']);
            }
        }
		$rdata['sysset'] = $sysset;

		$ranktype = input('post.ranktype');
		if (!in_array($ranktype,$sysset['rank_type']) && $ranktype == 1) {
		    $ranktype = 2;
		   
		}
		if (!in_array($ranktype,$sysset['rank_type']) && $ranktype == 3) {
		    $ranktype = 3;
		}
		
		$rdata['ranktype'] = $ranktype;
		$where = [];
		
		$ranknum = $sysset['rank_people'];
		$totalpage = ceil($ranknum/$pernum);//2

		$quyu = $pernum;
		if($pernum * $pagenum > $ranknum && $ranknum > 0) $quyu = $ranknum - $pernum * ($pagenum-1);
	
		if($ranknum>0){
			//$quyu = $ranknum%$pernum;
			//if($pagenum==$totalpage && $quyu>0){
			//	$pernum = $quyu;
			//}
			if($pagenum>$totalpage){
				$rdata['data'] = [];
				return $this->json(['status'=>0,'data'=>$rdata]);
			}
		}
		$where[] = ['o.aid','=',aid];
		// $where[] = ['o.status','in','1,2,3'];
		if($sysset['rank_date'] == 2){
			$beginWeek = mktime(0,0,0,date("m"),date("d")-date("w")+1,date("Y"));
			$where[] = ['o.createtime','>=',$beginWeek];
            if(getcustom('yx_queue_free'))$queue_where[] = ['createtime','>=',$beginWeek];
		}
		if($sysset['rank_date'] == 3){
			$date = time();
			$firstDay = strtotime(date('Y-m-01',$date));
			$where[] = ['o.createtime','>=',$firstDay];
            if(getcustom('yx_queue_free'))$queue_where[] = ['createtime','>=',$firstDay];
		}
		if($sysset['rank_date'] == 4){
			$begindate = strtotime('-7 days');
			$enddate =time();
			$where[] = ['o.createtime','between',[$begindate,$enddate]];
            if(getcustom('yx_queue_free'))$queue_where[] = ['createtime','between',[$begindate,$enddate]];
		}

		if($sysset['rank_date'] == 5){
			$begindate = strtotime("-1 month");
			$enddate =time();
			$where[] = ['o.createtime','between',[$begindate,$enddate]];
			if(getcustom('yx_queue_free'))$queue_where[]= ['createtime','between',[$begindate,$enddate]];
		}
		if($ranktype==1){

            // 先跳过
		    $field ='o.*,sum(o.commission) sumcommission,m.headimg,m.nickname';
            // $datalist = Db::name('member')->field($field)->alias('m')->join('member_commission_record o','o.mid=m.id')->where($where)->limit(($pagenum-1)*$pernum,$quyu)->order('sumcommission desc')->group('o.mid')->select()->toArray();

            // 暂时处理
            $where[] = ['o.commission','>',0];

            $datalist = Db::name('member')->field($field)->alias('m')->join('member_commissionlog o','o.mid=m.id')->where($where)->limit(($pagenum-1)*$pernum,$quyu)->order('sumcommission desc')->group('o.mid')->select()->toArray();
            if($datalist){
                foreach ($datalist as $kk => $vv) {
                    $datalist[$kk]['sumcommission'] = number_format($vv['sumcommission'],2);
                }
            }
            if(getcustom('yx_queue_free')){
                if($sysset['rank_queue_free_status'] ==1){
                    $queue_where[] = Db::raw('(totalcommission > 0 or totalqueuecommission > 0)');
                    $memberlist=Db::name('member')->where('aid',aid)->field('id,aid,totalqueuecommission,headimg,nickname')->where($queue_where)->select()->toArray();
                    foreach($memberlist as $key=>$val){
                        $sumcommission = 0+Db::name('member_commissionlog')->where('aid',aid)->where('mid',$val['id'])->sum('commission');
                        $total_commission = dd_money_format( $sumcommission+$val['totalqueuecommission']);
                        if($total_commission <= 0){
                                 unset($memberlist[$key]);
                                 continue;
                        }
                        $memberlist[$key]['sumcommission'] = $total_commission;
                    }
                    //重新排行
                    $sumcommission = [];
                    foreach ($memberlist as $key => $row)
                    {
                        $sumcommission[$key]  = $row['sumcommission'];
                    }
                    array_multisort($sumcommission,SORT_DESC,$memberlist);
                    $datalist = [];
                    foreach($memberlist as $key=>$val){
                        if($key +1 > $ranknum && $ranknum>0){
                            break;
                        }
                        $datalist[] = $val;
                    }
                    if($pagenum >1){
                        $datalist = [];
                    }
                }
            }
			//$datalist = Db::name('member')->where($where)->page($pagenum,$pernum)->order('totalcommission desc')->select()->toArray();



			if(!$datalist) $datalist = [];
			$rdata['data'] = $datalist;
		}elseif($ranktype==2){
            $where[] = ['o.status','in','1,2,3'];
			$datalist = Db::name('member')->field('o.*,sum(o.totalprice) sumtotalprice,m.headimg,m.nickname')->alias('m')->leftJoin('shop_order o','o.mid=m.id')->where($where)->limit(($pagenum-1)*$pernum,$quyu)->order('sumtotalprice desc')->group('o.mid')->select()->toArray();
			foreach($datalist as &$d){
				$d['sumtotalprice'] = number_format($d['sumtotalprice'],2);
			}
			if(!$datalist) $datalist = [];
			$rdata['data'] = $datalist;
		}elseif($ranktype==3){
			$field ='o.*,sum(o.totalprice) sumtotalteamprice,m.headimg,m.nickname';
			$where[] = ['o.status','in','1,2,3'];
            $datalist = Db::name('member')->field($field)->alias('m')->join('shop_order_goods o','o.parent1=m.id')->where($where)->limit(($pagenum-1)*$pernum,$quyu)->order('sumtotalteamprice desc')->group('o.parent1')->select()->toArray();
			foreach($datalist as &$d){
				$d['sumtotalteamprice'] = number_format($d['sumtotalteamprice'],2);
			}
			if(!$datalist) $datalist = [];
			$rdata['data'] = $datalist;
		}
		return $this->json(['status'=>1,'data'=>$rdata]);
    }

    //佣金排行
    public function fenhongrank(){
        $pernum = 10;
        $pagenum = input('post.pagenum');
        if(!$pagenum) $pagenum = 1;
        $sysset = $this->sysset;
        if($sysset['fenhong_rank_status']==0){
            $data = [];
            $data['status'] = 0;
            return $this->json(['status'=>0,'data'=>$data]);
        }
        $rdata = [];
//        $rdata['totalcommission'] = $this->member['total_fenhong'];
        $rdata['sysset'] = $sysset;
        $where = [];

        $ranknum = $sysset['fenhong_rank_people'];
        $totalpage = ceil($ranknum/$pernum);//2

        $quyu = $pernum;
        if ($pernum * $pagenum > $ranknum && $ranknum > 0) {
          $quyu = $ranknum - $pernum * ($pagenum - 1);
          $quyu = max($quyu, 0);
        }
        if($ranknum>0){
            //$quyu = $ranknum%$pernum;
            //if($pagenum==$totalpage && $quyu>0){
            //	$pernum = $quyu;
            //}
            if($pagenum>$totalpage){
                $rdata['data'] = [];
                return $this->json(['status'=>0,'data'=>$rdata]);
            }
        }
        $rank_type = explode(',', $sysset['fenhong_rank_type']);
        $where[] = ['o.aid','=',aid];
        if($sysset['fenhong_rank_date'] == 2){
            $beginWeek = mktime(0,0,0,date("m"),date("d")-date("w")+1,date("Y"));
            $where[] = ['o.createtime','>=',$beginWeek];
        }
        if($sysset['fenhong_rank_date'] == 3){
            $date = time();
            $firstDay = strtotime(date('Y-m-01',$date));
            $where[] = ['o.createtime','>=',$firstDay];
        }
        if($sysset['fenhong_rank_date'] == 4){
            $begindate = strtotime('-7 days');
            $enddate =time();
            $where[] = ['o.createtime','between',[$begindate,$enddate]];
        }

        if($sysset['fenhong_rank_date'] == 5){
            $begindate = strtotime("-1 month");
            $enddate =time();
            $where[] = ['o.createtime','between',[$begindate,$enddate]];
        }

        $datalist = [];
        if(count($rank_type) == 2){
          //分红和分销合并
          $commission1 = Db::name('member_commission_record')->where('status', 1)->where('aid', aid)->where('mid', mid)->sum('commission');
          $commission2 = Db::name('member_fenhonglog')->where('aid', aid)->where('mid', mid)->sum('commission');
          $rdata['totalcommission'] = $commission1+$commission2;
          $aliasql = Db::name('member_commission_record')
            ->where('status', 1)
            ->where('aid', aid)
            ->field('aid, mid, commission')
            ->union(function ($query) {
              $query->field('aid, mid, commission')->name('member_fenhonglog');
            }, 'all')
            ->buildSql();
          $datalist = Db::name('member')->field('o.*,sum(o.commission) sumcommission,m.headimg,m.nickname')->alias('m')->leftJoin($aliasql." o",'o.mid=m.id')->where($where)->limit(($pagenum-1)*$pernum,$quyu)->order('sumcommission desc')->group('o.mid')->select()->toArray();
        }else{
          if(in_array(1, $rank_type)){
            //分红
            $commission1 = 0;
            $commission2 = Db::name('member_fenhonglog')->where('aid', aid)->where('mid', mid)->sum('commission');
            $rdata['totalcommission'] = $commission1+$commission2;
            $datalist = Db::name('member')->field('o.*,sum(o.commission) sumcommission,m.headimg,m.nickname')->alias('m')->leftJoin('member_fenhonglog o','o.mid=m.id')->where($where)->limit(($pagenum-1)*$pernum,$quyu)->order('sumcommission desc')->group('o.mid')->select()->toArray();
          }
          if(in_array(2, $rank_type)){
            //分销
            $commission1 = Db::name('member_commission_record')->where('status', 1)->where('aid', aid)->where('mid', mid)->sum('commission');
            $commission2 = 0;
            $rdata['totalcommission'] = $commission1+$commission2;
            $datalist = Db::name('member')->field('o.*,sum(o.commission) sumcommission,m.headimg,m.nickname')->alias('m')->leftJoin('member_commission_record o','o.mid=m.id')->where($where)->limit(($pagenum-1)*$pernum,$quyu)->order('sumcommission desc')->group('o.mid')->select()->toArray();
          }
        }



        if(!$datalist) $datalist = [];
        $moeny_weishu = 2;
        if(getcustom('fenhong_money_weishu')){
            $moeny_weishu = Db::name('admin_set')->where('aid',aid)->value('fenhong_money_weishu');
        }
        foreach ($datalist as $key=>$val){
            $datalist[$key]['commission'] = dd_money_format($val['commission'],$moeny_weishu);
            $datalist[$key]['sumcommission'] = dd_money_format($val['sumcommission'],$moeny_weishu);
        }
        $rdata['totalcommission'] =   dd_money_format($rdata['totalcommission'],$moeny_weishu);
        $rdata['data'] = $datalist;
        return $this->json(['status'=>1,'data'=>$rdata]);
    }

    //团队业绩 
    public function teamyjrank(){
	    if(getcustom('team_yeji_ranking')){
            $pernum = 10;
            $pagenum = input('post.pagenum');
            if(!$pagenum) $pagenum = 1;
            $sysset = $this->sysset;
            if($sysset['teamyj_rank_status']==0){
                $data = [];
                $data['status'] = 0;
                return $this->json(['status'=>0,'data'=>$data]);
            }
            $my_team_yeji_total = 0;
            $downmids = \app\commons\Member::getteammids(aid,mid);
            if($downmids){
                $yejiwhere = [];
                $yejiwhere[] = ['status','in','1,2,3'];
                $yejiwhere[] = ['mid','in',$downmids];
                $my_team_yeji_total = Db::name('shop_order_goods')->where('aid',aid)->where('mid','in',$downmids)->where($yejiwhere)->sum('real_totalprice');
            }
            //包含自己
            $my_self_yeji =0+ Db::name('shop_order_goods')->where('aid',aid)->where('status','in','1,2,3')->where('mid',mid)->sum('real_totalprice');
            $my_team_yeji_total  =dd_money_format($my_team_yeji_total +$my_self_yeji);
            
            $rdata = [];
            $rdata['total_yeji'] = $my_team_yeji_total;
            $rdata['sysset'] = $sysset;
            
            $ranknum = $sysset['teamyj_rank_people'];
            $totalpage = ceil($ranknum/$pernum);//2
            $quyu = $pernum;
            if($pernum * $pagenum > $ranknum) $quyu = $ranknum - $pernum * ($pagenum-1);
            if($ranknum>0){
                if($pagenum>$totalpage){
                    $rdata['data'] = [];
                    return $this->json(['status'=>0,'data'=>$rdata]);
                }
            }
            $where = [];
            $where[] = ['aid','=',aid];
            $datalist = Db::name('member')
                ->field('id,nickname,headimg')
                ->where($where)->select()->toArray();
            if(!$datalist) $datalist = [];
            $yejirank = [];
            $newdatalist = [];
            $i = 0;
            foreach($datalist as $key=>$val){
                $haveteam = Db::name('member')->where('aid',aid)->where('pid',$val['id'])->value('id');
                if(!$haveteam)continue;
                $team_yeji_total = 0;
                $downmids = \app\commons\Member::getteammids(aid,$val['id']);
                if($downmids){
                    $yejiwhere = [];
                    $yejiwhere[] = ['aid','=',aid];
                    $yejiwhere[] = ['status','in','1,2,3'];
                    $yejiwhere[] = ['mid','in',$downmids];
                    $team_yeji_total = Db::name('shop_order_goods')->where($yejiwhere)->sum('real_totalprice');
                }
                //自身业绩
                $self_yeji =0+ Db::name('shop_order_goods')->where('aid',aid)->where('status','in','1,2,3')->where('mid',$val['id'])->sum('real_totalprice');
                $team_yeji_total  =dd_money_format($team_yeji_total +$self_yeji);
              
                if($team_yeji_total <=0)continue;
                $val['totalyeji'] = $team_yeji_total;
                $newdatalist[$i] =$val;
                $yejirank[$i] =  $team_yeji_total;
                $i++;
            }
            //排序
            array_multisort($yejirank,SORT_DESC,$newdatalist);
          
            //排序后 限制条数
            if($ranknum > 0){
                foreach ($newdatalist as $ak=>$av){
                    if($ak +1 >$ranknum ){
                        unset($newdatalist[$ak]);
                    }
                }
            }
            $rdata['data'] = $newdatalist;
            return $this->json(['status'=>1,'data'=>$rdata]);
        }
    }
    
    public function myyeji()
    {
        if(getcustom('member_show_buymoney')){
            $yejiwhere = [];
            $yejiwhere[] = ['status','in','1,2,3'];
            $starttime = strtotime(input('param.start_time'));
            $endtime = strtotime(input('param.end_time')) + 86399;
            $yejiwhere[] = ['createtime','between',[$starttime,$endtime]];
            $rdata['yeji'] = Db::name('shop_order_goods')->where('aid',aid)->where('mid',mid)->where($yejiwhere)->sum('real_totalprice');
        }

        return $this->json(['status'=>1,'data'=>$rdata]);
    }

    public function teamyeji()
    {
        if(getcustom('member_show_teamyeji')){
            $yejiwhere = [];
            $yejiwhere[] = ['status','in','1,2,3'];
            $starttime = strtotime(input('param.start_time'));
            $endtime = strtotime(input('param.end_time')) + 86399;
            $yejiwhere[] = ['createtime','between',[$starttime,$endtime]];
            $downmids = \app\commons\Member::getteammids(aid,mid);
            $rdata['yeji'] = Db::name('shop_order_goods')->where('aid',aid)->where('mid','in',$downmids)->where($yejiwhere)->sum('real_totalprice');
        }

        return $this->json(['status'=>1,'data'=>$rdata]);
    }
    //报单佣金冻结记录
    public function baodanCommissionLog(){
	    if (getcustom('product_baodan')){
            $pernum = 20;
            $pagenum = input('post.pagenum');
            if(!$pagenum) $pagenum = 1;
            $where = [];
            $where[] = ['aid','=',aid];
            $where[] = ['mid','=',mid];
            $datalist = Db::name('member_baodan_freeze_log')->page($pagenum,$pernum)->order('id desc')->where($where)->select()->toArray();
            if(!$datalist) $datalist = [];
            $moeny_weishu =2;
            if(getcustom('fenhong_money_weishu')){
                $moeny_weishu = Db::name('admin_set')->where('aid',aid)->value('fenhong_money_weishu');
            }
            $moeny_weishu =$moeny_weishu?$moeny_weishu:2;
            foreach ($datalist as $key=>$val){
                $datalist[$key]['commission'] = dd_money_format($val['commission'],$moeny_weishu);
                $datalist[$key]['after'] = dd_money_format($val['after'],$moeny_weishu);
                $datalist[$key]['createtime'] = date('Y-m-d H:i:s',$val['createtime']);
            }
            return $this->json(['status'=>1,'data'=>$datalist]);
        }
    }


    //商户分享海报
    public function business_poster(){
        $platform = platform;
        $bid = input('bid')?:0;
        $page = '/pagesExt/business/index';
        $scene = 'pid_'.$this->member['id'].'-id_'.$bid;
        if(getcustom('member_business')){
            //商户注册会员
            $scene .= '-regbid_'.$bid;
        }
        //if($platform == 'mp' || $platform == 'h5' || $platform == 'app'){
        //	$page = PRE_URL .'/h5/'.aid.'.html#'. $page;
        //}

        $member = $this->member;
        if(input('param.posterid')){
            $posterset = Db::name('business_set_poster')
                ->where('aid',aid)
                ->where('bid',$bid)
                ->where('type','index')
                ->where('platform',$platform)->where('id',input('param.posterid'))->find();
        }else{
            $posterset = Db::name('business_set_poster')
                ->where('aid',aid)
                ->where('bid',$bid)
                ->where('type','index')->where('platform',$platform)->order('id')->find();
        }
        $sysset = Db::name('admin_set')->field('id,name,logo,desc')->where('aid',aid)->find();
        if(getcustom('app_reg') && $platform == 'app'){
            $page = 'pages/index/reg';
            $scene.='-fromapp_1';
        }
        $poster = $posterset['poster'];
        $business = Db::name('business')->where('aid',aid)->where('id',$bid)->find();
        $pic = '';
        if($business && $business['pics']){
            $pic = explode(',',$business['pics'])[0];
        }
        //if(!$poster){
            $textReplaceArr = [
                '[商户LOGO]'=>$business['logo'],
                '[商户名称]'=>$business['name'],
                '[商户电话]'=>$business['tel'],
                '[商城名称]'=>$sysset['name'],
                '[商户图片]'=>$pic
            ];
            $poster = $this->_getposter(aid,$bid,$platform,$posterset['content'],$page,$scene,$textReplaceArr);
            Db::name('business_set_poster')->where('id',$posterset['id'])->update(['poster'=>$poster]);
        //}

        $posterlist = Db::name('business_set_poster')->field('id')
            ->where('aid',aid)
            ->where('bid',$bid)
            ->where('type','index')->where('platform',$platform)->order('id')->select()->toArray();

        $rdata = [];
        $rdata['poster'] = $poster;
        $rdata['guize'] = $posterset['guize'];
        $rdata['posterid'] = $posterset['id'];
        $rdata['posterlist'] = $posterlist;
        $rdata['postercount'] = count($posterlist);
        $rdata['business_name'] = $business['name'];
        $rdata['business_logo'] = $business['logo'];
        return $this->json($rdata);
    }

    public function shortvideolog(){
    	if(getcustom('yx_shortvideo_jindubag')){
	    	if(request()->isPost()){
	    		$mid = input('param.mid')?input('param.mid/d'):0;
	    		if(!$mid){
	    			return $this->json(['status'=>0,'msg'=>'请选择要查看的下级']);
	    		}
	    		$member = Db::name('member')->where('id',$mid)->where('aid',aid)->field('id,path')->find();
	    		if(!$member || !$member['path']){
	    			return $this->json(['status'=>0,'msg'=>'下级不存在']);
	    		}
	    		//查询是否是他的下级
	    		$patharr = explode(',',$member['path']);
	    		if(!$patharr){
	    			return $this->json(['status'=>0,'msg'=>'下级不存在']);
	    		}
	    		if(!in_array(mid, $patharr)){
	    			return $this->json(['status'=>0,'msg'=>'下级不存在']);
	    		}
		        $where = [];
		        $where[] = ['log.aid','=',aid];
		        $where[] = ['log.mid','=',$mid];
		        $where[] = ['log.is_del','=',0];
		        $pernum = 20;
		        $pagenum = input('post.pagenum');
		        if(!$pagenum) $pagenum = 1;
		        $field = "log.id,log.num,log.vid,log.jindu,video.coverimg as pic,video.name";

		        $datalist = Db::name('shortvideo_looklog')
		        	->alias('log')
		        	->join('shortvideo video','video.id=log.vid')
		        	->where($where)
		        	->field($field)
		        	->page($pagenum,$pernum)
		        	->order('log.id desc')
		        	->select()
		        	->toArray();
		        if(!$datalist) $datalist = array();

		        $rdata = [];
		        $rdata['status']   = 1;
		        $rdata['datalist'] = $datalist;
		        return $this->json($rdata);
	    	}
    	}
    }
	//推荐商家
	public function tjblist(){
		$level =  Db::name('member_level')->where('id',$this->member['levelid'])->find();
		$pernum = 10;
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
	
		$datalist = Db::name('member')->alias('m')->field('m.id,m.pid,b.id,b.*')->where('m.aid',aid)->where('m.pid',mid)->where('b.status',1)->join('business b','m.id=b.mid')->order('b.id desc')->page($pagenum,$pernum)->select()->toArray();

		//$datalist = Db::name('business')->where($where)->page($pagenum,$pernum)->select()->toArray();
		//echo db('business')->getlastsql();
		if(!$datalist) $datalist = [];
		$count =  Db::name('member')->alias('m')->field('m.id,m.pid,b.id,b.logo')->where('m.aid',aid)->where('m.pid',mid)->where('b.status',1)->join('business b','m.id=b.mid')->count();
		return $this->json(['status'=>1,'data'=>$datalist,'count'=>$count]);
	}

    public function priceRate(){
        if(getcustom('product_price_rate')){
            if(request()->isPost()) {
                $rate = input('param.rate') ? input('param.rate') : 1;
                Db::name('member')->where('aid',aid)->where('id',mid)->update(['price_rate'=>$rate]);
                $posterset = Db::name('admin_set_poster')->where('aid',aid)->where('type','index')->where('platform',platform)->order('id')->find();
                Db::name('member_poster')->where('aid',aid)->where(['mid'=>mid,'posterid'=>$posterset['id']])->delete();
                return $this->json(['status'=>1,'msg'=>'设置成功']);
            }
            $member = Db::name('member')->field('id,pid,price_rate')->where('aid',aid)->where('id',mid)->find();
            return $this->json(['status'=>1,'price_rate'=>$member['price_rate']]);
        }
    }

    public function memberPriceRate(){
        if(getcustom('member_level_price_rate')){
            if(request()->isPost()) {
                $levelid = input('param.levelid');
                Db::name('member')->where('aid',aid)->where('id',mid)->update(['levelid_price_rate'=>$levelid]);
                return $this->json(['status'=>1,'msg'=>'设置成功']);
            }
            $levellist = Db::name('member_level')->where('aid',aid)->order('sort asc,id desc')->field('id,name,price_rate,sort')->select()->toArray();
            return $this->json(['status'=>1,'levellist'=>$levellist,'levelid_price_rate'=>$this->member['levelid_price_rate']]);
        }
    }

    //团队收益记录
    public function teamfenhong_shouyi(){
        if (getcustom('teamfenhong_shouyi')){
            $pernum = 20;
            $pagenum = input('post.pagenum');
            if(!$pagenum) $pagenum = 1;
            $where = [];
            $where[] = ['aid','=',aid];
            $where[] = ['mid','=',mid];
            $where[] = ['type','=','teamfenhong_shouyi'];
            $datalist = Db::name('member_fenhonglog')->page($pagenum,$pernum)->order('id desc')->where($where)->select()->toArray();
            if(!$datalist) $datalist = [];
            $moeny_weishu =2;
            if(getcustom('fenhong_money_weishu')){
                $moeny_weishu = Db::name('admin_set')->where('aid',aid)->value('fenhong_money_weishu');
            }
            $moeny_weishu =$moeny_weishu?$moeny_weishu:2;
            foreach ($datalist as $key=>$val){
                $datalist[$key]['commission'] = dd_money_format($val['commission'],$moeny_weishu);
                $datalist[$key]['createtime'] = date('Y-m-d H:i:s',$val['createtime']);
            }
            return $this->json(['status'=>1,'data'=>$datalist]);
        }
    }
    
    //奖励金 提现
    public function gongxianWithdraw(){
	    if(getcustom('product_bonus_pool')){
            $field = 'withdraw_autotransfer,comwithdraw,comwithdrawmin,comwithdrawfee,comwithdrawbl,comwithdrawdate,withdraw_weixin,withdraw_aliaccount,withdraw_bankcard,withdraw_desc';

            $set = Db::name('admin_set')->where('aid',aid)->field($field)->find();
            if(request()->isPost()){
                $post = input('post.');
                if($set['comwithdraw'] == 0){
                    return $this->json(['status'=>0,'msg'=>'提现功能未开启']);
                }
                $memberlevel = Db::name('member_level')->where('id',$this->member['levelid'])->find();
                if($memberlevel && $memberlevel['comwithdraw'] == 0){
                    return $this->json(['status'=>0,'msg'=>'提现功能未开启']);
                }

                if($post['paytype']=='支付宝'){
                    if(!$this->member['aliaccount'] || !$this->member['aliaccountname']){
                        return $this->json(['status'=>0,'msg'=>'请先设置支付宝账号']);
                    }
                }

                if($post['paytype']=='支付宝' && $this->member['aliaccount']==''){
                    return $this->json(['status'=>0,'msg'=>'请先设置支付宝账号']);
                }
                if($post['paytype']=='银行卡' && ($this->member['bankname']==''||$this->member['bankcarduser']==''||$this->member['bankcardnum']=='')){
                    return $this->json(['status'=>0,'msg'=>'请先设置完整银行卡信息']);
                }

                $money = $post['money'];
                if($money<=0 || $money < $set['comwithdrawmin']){
                    return $this->json(['status'=>0,'msg'=>'提现金额必须大于'.($set['comwithdrawmin']?$set['comwithdrawmin']:0)]);
                }
                if($money > $this->member['bonus_pool_money']){
                    return $this->json(['status'=>0,'msg'=>'可提现'.t('贡献值').'不足']);
                }

                //验证小数点后两位
                $money_arr = explode('.',$money);
                if($money_arr && $money_arr[1]){
                    $dot_len = strlen($money_arr[1]);
                    if($dot_len>2){
                        return $this->json(['status'=>0,'msg'=>'提现金额最小位数为小数点后两位']);
                    }
                }

                $ordernum = date('ymdHis').aid.rand(1000,9999);
                $record['aid'] = aid;
                $record['mid'] = mid;
                $record['createtime']= time();
                $real_money =  $money;
                if($post['paytype'] != '余额' ){
                    $real_money = dd_money_format($money*(1-$set['comwithdrawfee']*0.01)) ;
                }
                if($real_money <= 0) {
                    return $this->json(['status'=>0,'msg'=>'提现金额有误']);
                }
                $record['money'] = $real_money;
                $record['txmoney'] = $money;
                if($post['paytype']=='支付宝'){
                    $record['aliaccountname'] = $this->member['aliaccountname'];
                    $record['aliaccount'] = $this->member['aliaccount'];
                }
                if($post['paytype']=='银行卡'){
                    $record['bankname'] = $this->member['bankname'].($this->member['bankaddress'] ? ' '.$this->member['bankaddress'] : '');;
                    $record['bankcarduser'] = $this->member['bankcarduser'];
                    $record['bankcardnum'] = $this->member['bankcardnum'];
                }
                $record['ordernum'] = $ordernum;
                $record['paytype'] = $post['paytype'];
                $record['platform'] = platform;
                $recordid = Db::name('member_bonus_pool_withdrawlog')->insertGetId($record);
                
                $bonus_pool_money = dd_money_format($this->member['bonus_pool_money'] -$money );
                $log = [
                    'aid' =>aid,
                    'mid' =>mid,
                    'frommid' => 0,
                    'commission' => -$money,
                    'after' => $bonus_pool_money,
                    'createtime' => time(),
                    'remark' => t('贡献值').'提现'
                ];
                Db::name('member_bonus_pool_log') ->insert($log);
                //查找
                $bonus_pool_max_money = dd_money_format($this->member['bonus_pool_max_money'] -$money);
                $bonus_pool_max_money = $bonus_pool_max_money<=0?0:$bonus_pool_max_money;
                Db::name('member')->where('id',mid)->update(['bonus_pool_money' => $bonus_pool_money,'bonus_pool_max_money' => $bonus_pool_max_money]);
                
                
                $tmplcontent = array();
                $tmplcontent['first'] = '有客户申请'.t('贡献值').'提现';
                $tmplcontent['remark'] = '点击进入查看~';
                $tmplcontent['keyword1'] = $this->member['nickname'];
                $tmplcontent['keyword2'] = date('Y-m-d H:i');
                $tmplcontent['keyword3'] = $money.'元';
                $tmplcontent['keyword4'] = $post['paytype'];
                \app\commons\Wechat::sendhttmpl(aid,0,'tmpl_withdraw',$tmplcontent,m_url('admin/finance/comwithdrawlog'));

                $tmplcontent = [];
                $tmplcontent['name3'] = $this->member['nickname'];
                $tmplcontent['amount1'] = $money.'元';
                $tmplcontent['date2'] = date('Y-m-d H:i');
                $tmplcontent['thing4'] = '提现到'.$post['paytype'];
                \app\commons\Wechat::sendhtwxtmpl(aid,0,'tmpl_withdraw',$tmplcontent,'admin/finance/comwithdrawlog');
                if($post['paytype'] == '余额' ){
                    $paymoney = dd_money_format($record['money']);
                    \app\commons\Member::addmoney(aid,mid,$paymoney,t('贡献值').'提现');
                    Db::name('member_bonus_pool_withdrawlog')->where('aid',aid)->where('id',$recordid)->update(['status'=>3,'paytime'=>time()]);
                    //提现成功通知
                    $tmplcontent = [];
                    $tmplcontent['first'] = '您的提现申请已打款，请留意查收';
                    $tmplcontent['remark'] = '请点击查看详情~';
                    $tmplcontent['money'] = (string) round($record['money'],2);
                    $tmplcontent['timet'] = date('Y-m-d H:i',$record['createtime']);
                    $tempconNew = [];
                    $tempconNew['amount2'] = (string) $record['money'];//提现金额
                    $tempconNew['time3'] = date('Y-m-d H:i',$record['createtime']);//提现时间
                    \app\commons\Wechat::sendtmpl(aid,$record['mid'],'tmpl_tixiansuccess',$tmplcontent,m_url('pages/my/usercenter'),$tempconNew);
                    //订阅消息
                    $tmplcontent = [];
                    $tmplcontent['amount1'] = $record['money'];
                    $tmplcontent['thing3'] = '余额打款';
                    $tmplcontent['time5'] = date('Y-m-d H:i');

                    $tmplcontentnew = [];
                    $tmplcontentnew['amount3'] = $record['money'];
                    $tmplcontentnew['phrase9'] = '余额打款';
                    $tmplcontentnew['date8'] = date('Y-m-d H:i');
                    \app\commons\Wechat::sendwxtmpl(aid,$record['mid'],'tmpl_tixiansuccess',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);
                    //短信通知
                    if($this->member['tel']){
                        \app\commons\Sms::send(aid,$this->member['tel'],'tmpl_tixiansuccess',['money'=>$record['money']]);
                    }
                    return json(['status'=>1,'msg'=>'提现成功']);
                }
                if($set['withdraw_autotransfer'] && ($post['paytype'] == '微信钱包' || $post['paytype'] == '银行卡')){
                    if($set['comwithdrawbl'] > 0 && $set['comwithdrawbl'] < 100){
                        $paymoney = round($record['money'] * $set['comwithdrawbl'] * 0.01,2);
                        $tomoney = round($record['money'] - $paymoney,2);
                    }else{
                        $paymoney = $record['money'];
                        $tomoney = 0;
                    }
                    $paymoney = dd_money_format($paymoney);
                    $rs = \app\commons\Wxpay::transfers(aid,mid,$paymoney,$record['ordernum'],platform,t('佣金').'提现');
                    if($rs['status']==0){
                        return json(['status'=>1,'msg'=>'提交成功,请等待打款']);
                    }else{
                        if($tomoney > 0){
                            \app\commons\Member::addmoney(aid,mid,$tomoney,t('佣金').'提现');
                        }
                        Db::name('member_bonus_pool_withdrawlog')->where('aid',aid)->where('id',$recordid)->update(['status'=>3,'paytime'=>time(),'paynum'=>$rs['resp']['payment_no']]);
                        
                        //提现成功通知
                        $tmplcontent = [];
                        $tmplcontent['first'] = '您的提现申请已打款，请留意查收';
                        $tmplcontent['remark'] = '请点击查看详情~';
                        $tmplcontent['money'] = (string) round($record['money'],2);
                        $tmplcontent['timet'] = date('Y-m-d H:i',$record['createtime']);
                        $tempconNew = [];
                        $tempconNew['amount2'] = (string) $record['money'];//提现金额
                        $tempconNew['time3'] = date('Y-m-d H:i',$record['createtime']);//提现时间
                        \app\commons\Wechat::sendtmpl(aid,$record['mid'],'tmpl_tixiansuccess',$tmplcontent,m_url('pages/my/usercenter'),$tempconNew);
                        //订阅消息
                        $tmplcontent = [];
                        $tmplcontent['amount1'] = $record['money'];
                        $tmplcontent['thing3'] = '微信打款';
                        $tmplcontent['time5'] = date('Y-m-d H:i');

                        $tmplcontentnew = [];
                        $tmplcontentnew['amount3'] = $record['money'];
                        $tmplcontentnew['phrase9'] = '微信打款';
                        $tmplcontentnew['date8'] = date('Y-m-d H:i');
                        \app\commons\Wechat::sendwxtmpl(aid,$record['mid'],'tmpl_tixiansuccess',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);
                        //短信通知
                        if($this->member['tel']){
                            \app\commons\Sms::send(aid,$this->member['tel'],'tmpl_tixiansuccess',['money'=>$record['money']]);
                        }
                        return json(['status'=>1,'msg'=>$rs['msg']]);
                    }
                }
                return $this->json(['status'=>1,'msg'=>'提交成功,请等待打款']);
            }

            if($set['comwithdraw'] == 0){
                return $this->json(['status'=>-4,'msg'=>t('佣金').'提现功能未开启']);
            }
            $memberlevel = Db::name('member_level')->where('id',$this->member['levelid'])->find();
            if($memberlevel && $memberlevel['comwithdraw'] == 0){
                return $this->json(['status'=>-4,'msg'=>t('佣金').'提现未开启','url'=>'/activity/commission/index']);
            }
            //订阅消息
            $wx_tmplset = Db::name('wx_tmplset')->where('aid',aid)->find();
            $tmplids = [];
            if($wx_tmplset['tmpl_tixiansuccess_new']){
                $tmplids[] = $wx_tmplset['tmpl_tixiansuccess_new'];
            }elseif($wx_tmplset['tmpl_tixiansuccess']){
                $tmplids[] = $wx_tmplset['tmpl_tixiansuccess'];
            }
            if($wx_tmplset['tmpl_tixianerror_new']){
                $tmplids[] = $wx_tmplset['tmpl_tixianerror_new'];
            }elseif($wx_tmplset['tmpl_tixianerror']){
                $tmplids[] = $wx_tmplset['tmpl_tixianerror'];
            }
            $rdata = [];
            $field = 'id,headimg,nickname,money,score,commission,aliaccount,bankname,bankcarduser,bankcardnum,bonus_pool_money,bonus_pool_max_money';
            $show_cash_count = 0;
        
            $userinfo = Db::name('member')->where('id',mid)->field($field)->find();
            $userinfo['commission'] = dd_money_format($userinfo['bonus_pool_money'],2);
            $userinfo['show_cash_count'] = $show_cash_count;
            $rdata['userinfo'] =  $userinfo ;
            $rdata['sysset'] = $set;
            $rdata['tmplids'] = $tmplids;

            return $this->json($rdata); 
        }
    }

    //佣金明细
    public function gongxiancommissionlog(){
        if(getcustom('product_bonus_pool')){
            $st = input('param.st');
            $type = input('param.type');
            $where = [];
            $where[] = ['aid','=',aid];
            $where[] = ['mid','=',mid];
            if(input('param.keyword')) $where[] = ['remark', 'like', '%'.input('param.keyword').'%'];
            $pernum = 20;
            $pagenum = input('post.pagenum');
            if(!$pagenum) $pagenum = 1;
            if($st ==1){//提现记录
                $datalist = Db::name('member_bonus_pool_withdrawlog')->field("id,money,txmoney,`status`,from_unixtime(createtime)createtime")->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
            }else{ //佣金明细
                $moeny_weishu =2;
                $datalist = Db::name('member_bonus_pool_log')->field("id,commission money,`after`,from_unixtime(createtime)createtime,remark")->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
                foreach ($datalist as $key=>$val){
                    $datalist[$key]['commission'] = dd_money_format($val['commission'],$moeny_weishu);
                    $datalist[$key]['money'] = dd_money_format($val['money'],$moeny_weishu);
                    $datalist[$key]['after'] = dd_money_format($val['after'],$moeny_weishu);
                }
            }
            if(!$datalist) $datalist = [];
            return $this->json(['status'=>1,'data'=>$datalist]);
        }
    }
    
    //区域代理排行
    public function regionagentrank(){
	    if(getcustom('areafenhong_region_ranking')){
            $pernum = 10;
            $pagenum = 1;
            
//            if(!$pagenum) $pagenum = 1;
            $sysset = $this->sysset;
            if($sysset['region_rank_status']==0){
                $data = [];
                $data['status'] = 0;
                return $this->json(['status'=>0,'data'=>$data,'msg' =>'排行榜未开启']);
            }
            if(!$sysset['region_show_type']){
                return $this->json(['status'=>0,'data'=>[],'msg' =>'排行榜未开启']);
            }
            $gettj = explode(',',$sysset['region_rank_levelids']);
            if(!in_array('-1',$gettj) && !in_array($this->member['levelid'],$gettj)) { //不是所有人
                $data = [];
                $data['status'] = 0;
                return $this->json(['status'=>0,'data'=>$data,'msg' =>'您没有查看权限']);
            }
            
            $rdata = [];
            $rdata['sysset'] = $sysset;
    
            $ranktype = input('post.ranktype');

            $ranknum = $sysset['region_rank_people'];
            $totalpage = ceil($ranknum/$pernum);//2
    
            $quyu = $pernum;
            if($pernum * $pagenum > $ranknum) $quyu = $ranknum - $pernum * ($pagenum-1);
            if($ranknum>0){
                if($pagenum>$totalpage){
                    $rdata['data'] = [];
                    return $this->json(['status'=>0,'data'=>$rdata]);
                }
            }
            $sort_type = input('param.sorttype',1);
            $rdata['data'] = [];
            if(in_array($ranktype,[0,1,2])){
                $province = [];
                $city = [];
                $area =[];
                $where = [];
                $where[] = ['aid','=',aid];
                $where[] = ['area2','<>',''];
                $where[] = Db::raw(" area2 IS NOT NULL and  area2 !=',,'");
                $area2 =  Db::name('shop_order')->where($where)->group('area2')->field('area2')->select()->toArray();
                foreach($area2 as $key=>$val){
                    $area2data = explode(',',$val['area2']);
                    if($area2data[0]){
                        $province[] = $area2data[0];
                    }
                    if($area2data[1]){
                        $city[] = $area2data[1];
                    }
                    if($area2data[2]){
                        $area[] = $area2data[2];
                    }
                }
                $province =  array_unique($province);
                $city =  array_unique($city);
                $area =  array_unique($area);
            }
            $start_time = '';
            $end_time = '';
            if($sysset['region_ctime']){
                $ctime = explode('~',$sysset['region_ctime']);
                $start_time = strtotime($ctime[0]);
                $end_time = strtotime($ctime[1]);
            }
           
            if($ranktype==0){//省
                $list = [];
                foreach($province as $key=>$val){
                    $where = [];
                    $list[$key]['title'] = $val;
                    $where[] = ['aid','=',aid];
                    $where[] = Db::raw("find_in_set('".$val."',area2)");
//                    $where[] = ['area2','like',$val.'%'];
                    if($start_time && $end_time){
                        $where[] = ['paytime','between',[$start_time,$end_time]];
                    }
                    $where[] = ['status','in',[1,2,3]];
                    $totalprice = 0+Db::name('shop_order')->where($where)->sum('totalprice');
                    $list[$key]['totalprice'] = dd_money_format($totalprice);
                    $orderids =   Db::name('shop_order')->where($where)->column('id');
                    $num =  Db::name('shop_order_goods')->where('aid',aid)->where('orderid','in',$orderids)->sum('num');
                    $list[$key]['num'] = $num;
                }
                $totalprice = [];
                $num = [];
                foreach ($list as $key => $row)
                {
                    $totalprice[$key]  = $row['totalprice'];
                    $num[$key]  = $row['num'];
                }
                if($sort_type ==1){
                    array_multisort($totalprice,SORT_DESC,$list);
                } else{
                    array_multisort($num,SORT_DESC,$list); 
                }
              
                $datalist = [];
                if($ranknum > 0){
                    $ranknum = $ranknum >=count($list)?count($list): $ranknum;
	 				for($i=0;$i<$ranknum;$i++){
	                   $datalist[] = $list[$i];
	               	}
                }else{
                	for($i=0;$i<count($list);$i++){
	                   $datalist[] = $list[$i];
	               	}
                }
              
                $rdata['data'] = $datalist;
            }
            elseif($ranktype==1){//市
                $list = [];
                foreach($city as $key=>$val){
                    $where = [];
                    $list[$key]['title'] = $val;
                    $where[] = ['aid','=',aid];
                    $where[] = Db::raw("find_in_set('".$val."',area2)");
//                    $where[] = ['area2','like','%'.$val.'%'];
                    if($start_time && $end_time){
                        $where[] = ['paytime','between',[$start_time,$end_time]];
                    }
                    $where[] = ['status','in',[1,2,3]];
                    $totalprice = 0+Db::name('shop_order')->where($where)->sum('totalprice');
                    $list[$key]['totalprice'] = dd_money_format($totalprice);
                    $orderids =   Db::name('shop_order')->where($where)->column('id');
                    $num =  Db::name('shop_order_goods')->where('aid',aid)->where('orderid','in',$orderids)->sum('num');
                    $list[$key]['num'] = $num;
                }
              
                $totalprice = [];
                $num = [];
                foreach ($list as $key => $row)
                {
                    $totalprice[$key]  = $row['totalprice'];
                    $num[$key]  = $row['num'];
                }
                if($sort_type ==1){
                    array_multisort($totalprice,SORT_DESC,$list);
                } else{
                    array_multisort($num,SORT_DESC,$list);
                }
                $datalist = [];
               	if($ranknum > 0){
                    $ranknum = $ranknum >=count($list)?count($list): $ranknum;
	 				for($i=0;$i<$ranknum;$i++){
	                   $datalist[] = $list[$i];
	               	}
                }else{
                	for($i=0;$i<count($list);$i++){
	                   $datalist[] = $list[$i];
	               	}
                }
                $rdata['data'] = $datalist;
                
            }
            elseif($ranktype==2){  //县区
                $list = [];
                foreach($area as $key=>$val){
                    $where = [];
                    $list[$key]['title'] = $val;
                    $where[] = ['aid','=',aid];
                    $where[] = Db::raw("find_in_set('".$val."',area2)");
//                    $where[] = ['area2','like','%'.$val.'%'];
                    if($start_time && $end_time){
                        $where[] = ['paytime','between',[$start_time,$end_time]];
                    }
                    $where[] = ['status','in',[1,2,3]];
                    $totalprice = 0+Db::name('shop_order')->where($where)->sum('totalprice');
                    $list[$key]['totalprice'] = dd_money_format($totalprice);
                    $orderids =   Db::name('shop_order')->where($where)->column('id');
                    $num =  Db::name('shop_order_goods')->where('aid',aid)->where('orderid','in',$orderids)->sum('num');
                    $list[$key]['num'] = $num;
                }
                $totalprice = [];
                $num = [];
                foreach ($list as $key => $row)
                {
                    $totalprice[$key]  = $row['totalprice'];
                    $num[$key]  = $row['num'];
                }
                if($sort_type ==1){
                    array_multisort($totalprice,SORT_DESC,$list);
                } else{
                    array_multisort($num,SORT_DESC,$list);
                }
                $datalist = [];
                if($ranknum > 0){
                    $ranknum = $ranknum >=count($list)?count($list): $ranknum;
	 				for($i=0;$i<$ranknum;$i++){
	                   $datalist[] = $list[$i];
	               	}
                }else{
                	for($i=0;$i<count($list);$i++){
	                   $datalist[] = $list[$i];
	               	}
                }
                $rdata['data'] = $datalist;
            }
            else{   //个人
                $where = [];
                $where[] = ['aid','=',aid];
                $memberlist = Db::name('member')->where($where)->field('id,nickname,headimg,path')->select()->toArray();
                foreach($memberlist as $key=>$member){
                    $downmids = [];
                    $downmids[] = $member['id'];
                    $downmids_x = \app\commons\Member::getteammids($sysset['aid'],$member['id']);
                    $downmids = array_merge($downmids_x,$downmids);
                    if($sort_type ==1){
                        $totalprice =0+ Db::name('shop_order')->where('aid',aid)->where('mid','in',$downmids)->where('status','in',[1,2,3])->sum('totalprice');
                        $memberlist[$key]['totalprice'] = dd_money_format($totalprice);
                       
                    }else{
                        $orderids =   Db::name('shop_order')->where('aid',aid)->where('mid','in',$downmids)->where('status','in',[1,2,3])->column('id');
                        $num =0+ Db::name('shop_order_goods')->where('aid',aid)->where('orderid','in',$orderids)->sum('num');

                        $memberlist [$key]['num'] = $num;
                    }
                }
                $totalprice = [];
                $num = [];
                foreach ($memberlist as $key => $row)
                {
                    $totalprice[$key]  = $row['totalprice'];
                    $num[$key]  = $row['num'];
                }
                if($sort_type ==1){
                    array_multisort($totalprice,SORT_DESC,$memberlist);
                } else{
                    array_multisort($num,SORT_DESC,$memberlist);
                }
                $datalist = [];
              
                if($ranknum > 0){
                    $ranknum = $ranknum >=count($memberlist)?count($memberlist): $ranknum;
                   
	 				for($i=0;$i<$ranknum;$i++){
	                   $datalist[] = $memberlist[$i];
	               	}
                }else{
                	for($i=0;$i<count($memberlist);$i++){
	                   $datalist[] = $memberlist[$i];
	               	}
                }
             
               $rdata['data'] = $datalist;
                
            }

            return $this->json(['status'=>1,'data'=>$rdata,'region_ctime' => $sysset['region_ctime'],'region_show_type' =>explode(',',$sysset['region_show_type']) ]);
        }
    }

    //回本股东分红
    public function fenhong_huiben(){
	    if(getcustom('fenhong_gudong_huiben')){
            $pernum = 20;
            $pagenum = input('param.pagenum') ? input('param.pagenum') :1;
            $where = '';
            $st = input('param.st/d');
            if(!$st) $st = 1;

            //待结算
            $newoglist = [];
            if($st == 1){
                $rs = \app\commons\Fenhong::gdfenhong_huiben(aid,$this->sysset,[],0,time(),1,mid);
                $newoglist = $rs['oglist'];
                $commissionyj = $rs['commissionyj'];
            }

            if($st==2){//已结算
                $where = [];
                $where[] = ['aid','=',aid];
                $where[] = ['mid','=',mid];
                $where[] = ['type','=','fenhong_huiben'];
                $pernum = 20;
                $pagenum = input('param.pagenum');
                if(!$pagenum) $pagenum = 1;
                $count = Db::name('member_fenhonglog')->where($where)->count();
                $datalist = Db::name('member_fenhonglog')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
                if(!$datalist) $datalist = [];
                foreach($datalist as $k=>$v){
                    if($v['ogids']){
                        if($v['module'] == 'yuyue'){
                            $oglist = Db::name('yuyue_order')->alias('og')->join('member m','og.mid=m.id')->field('og.ordernum,og.proname name,og.propic pic,og.num,og.totalprice real_totalprice,og.createtime,og.status,m.nickname,m.headimg')->where('og.id','in',$v['ogids'])->select()->toArray();
                        }elseif($v['module'] == 'luckycollage'){
                            $oglist = Db::name('lucky_collage_order')->alias('og')->join('member m','og.mid=m.id')->field('og.ordernum,og.proname name,og.propic pic,og.num,og.totalprice real_totalprice,og.createtime,og.status,m.nickname,m.headimg')->where('og.id','in',$v['ogids'])->select()->toArray();
                        }elseif($v['module'] == 'scoreshop'){
                            $oglist = Db::name('scoreshop_order_goods')->alias('og')->join('member m','og.mid=m.id')->field('og.ordernum,og.name,og.pic,og.num,og.totalmoney real_totalprice,og.createtime,og.status,m.nickname,m.headimg')->where('og.id','in',$v['ogids'])->select()->toArray();
                        }elseif($v['module'] == 'kecheng'){
                            $oglist = Db::name('kecheng_order')->alias('og')->join('member m','og.mid=m.id')->field('og.ordernum,og.title as name,og.pic,"1" as num,og.totalprice real_totalprice,og.createtime,og.status,m.nickname,m.headimg')->where('og.id','in',$v['ogids'])->select()->toArray();
                        }elseif($v['module'] == 'maidan'){
                            $oglist = Db::name('maidan_order')->alias('og')->join('member m','og.mid=m.id')->field('og.ordernum,og.title as name,"" as pic,"1" as num,og.paymoney real_totalprice,og.createtime,og.status,m.nickname,m.headimg')->where('og.id','in',$v['ogids'])->select()->toArray();
                        }elseif($v['module'] == 'shop' || empty($v['module'])){
                            $oglist = Db::name('shop_order_goods')->alias('og')->join('member m','og.mid=m.id')->field('og.ordernum,og.name,og.pic,og.num,og.real_totalprice,og.createtime,og.status,m.nickname,m.headimg')->where('og.id','in',$v['ogids'])->select()->toArray();
                        }else{
                            $oglist = [];
                        }
                    }else{
                        $oglist = [];
                    }
                    $datalist[$k]['oglist'] = $oglist;
                }
            }
            $rdata = [];
            $rdata['count'] = $count + count($newoglist);
            $rdata['commissionyj'] = round($commissionyj,2);
            if($st == 1){
                $rdata['datalist'] = $newoglist;
            }else{
                $rdata['datalist'] = $datalist;
            }
            $rdata['st'] = $st;
            return $this->json($rdata);
        }

    }

    //商家团队分红
    public function business_teamfenhong(){
	    if(getcustom('business_teamfenhong')){
            $pernum = 20;
            $pagenum = input('param.pagenum') ? input('param.pagenum') :1;
            $where = '';
            $st = input('param.st/d');
            if(!$st) $st = 1;

            //待结算
            $newoglist = [];
            if($st == 1){
                $rs1 = \app\commons\Fenhong::business_teamfenhong(aid,$this->sysset,[],0,time(),1,mid);
                $newoglist = [];
                $commissionyj = 0;
                if($rs1 && $rs1['oglist']){
                    $newoglist = array_merge($newoglist,$rs1['oglist']);
                    $commissionyj += $rs1['commissionyj'];
                }
            }
            $moeny_weishu = 2;
            if(getcustom('fenhong_money_weishu')){
                $moeny_weishu = Db::name('admin_set')->where('aid',aid)->value('fenhong_money_weishu');
            }
            if($st==2){//已结算
                $where = [];
                $where[] = ['aid','=',aid];
                $where[] = ['mid','=',mid];
                $where[] = ['type','=','business_teamfenhong'];
                $pernum = 20;
                $pagenum = input('param.pagenum');
                if(!$pagenum) $pagenum = 1;
                $count = Db::name('member_fenhonglog')->where($where)->count();
                $datalist = Db::name('member_fenhonglog')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
                if(!$datalist) $datalist = [];
                foreach($datalist as $k=>$v){
                    if($v['ogids']){
                        if($v['module'] == 'yuyue'){
                            $oglist = Db::name('yuyue_order')->alias('og')->join('member m','og.mid=m.id')->field('og.ordernum,og.proname name,og.propic pic,og.num,og.totalprice real_totalprice,og.createtime,og.status,m.nickname,m.headimg')->where('og.id','in',$v['ogids'])->select()->toArray();
                        }elseif($v['module'] == 'luckycollage'){
                            $oglist = Db::name('lucky_collage_order')->alias('og')->join('member m','og.mid=m.id')->field('og.ordernum,og.proname name,og.propic pic,og.num,og.totalprice real_totalprice,og.createtime,og.status,m.nickname,m.headimg')->where('og.id','in',$v['ogids'])->select()->toArray();
                        }elseif($v['module'] == 'scoreshop'){
                            $oglist = Db::name('scoreshop_order_goods')->alias('og')->join('member m','og.mid=m.id')->field('og.ordernum,og.name,og.pic,og.num,og.totalmoney real_totalprice,og.createtime,og.status,m.nickname,m.headimg')->where('og.id','in',$v['ogids'])->select()->toArray();
                        }elseif($v['module'] == 'kecheng'){
                            $oglist = Db::name('kecheng_order')->alias('og')->join('member m','og.mid=m.id')->field('og.ordernum,og.title as name,og.pic,"1" as num,og.totalprice real_totalprice,og.createtime,og.status,m.nickname,m.headimg')->where('og.id','in',$v['ogids'])->select()->toArray();
                        }elseif($v['module'] == 'maidan'){
                            $oglist = Db::name('maidan_order')->alias('og')->join('member m','og.mid=m.id')->field('og.ordernum,og.title as name,"" as pic,"1" as num,og.paymoney real_totalprice,og.createtime,og.status,m.nickname,m.headimg')->where('og.id','in',$v['ogids'])->select()->toArray();
                        }elseif($v['module'] == 'shop' || empty($v['module'])){
                            $oglist = Db::name('shop_order_goods')->alias('og')->join('member m','og.mid=m.id')->field('og.ordernum,og.name,og.pic,og.num,og.real_totalprice,og.createtime,og.status,m.nickname,m.headimg')->where('og.id','in',$v['ogids'])->select()->toArray();
                        }else{
                            $oglist = [];
                        }
                    }else{
                        $oglist = [];
                    }
                    $datalist[$k]['oglist'] = $oglist;
                    $datalist[$k]['commission'] = dd_money_format($v['commission'],$moeny_weishu);
                }
            }
            $rdata = [];
            $rdata['count'] = $count + count($newoglist);

            $rdata['commissionyj'] = dd_money_format($commissionyj,$moeny_weishu);
            if($st == 1){
                $rdata['datalist'] = $newoglist;
            }else{
                $rdata['datalist'] = $datalist;
            }
            $rdata['st'] = $st;
            return $this->json($rdata);
        }

    }

    //分销补贴
    public function commissionbutie(){
	    if(getcustom('commission_butie')){
            $pernum = 20;
            $pagenum = input('post.pagenum');
            if(!$pagenum) $pagenum = 1;

            $where = [];
            $where[] = ['aid','=',aid];
            $where[] = ['mid','=',mid];
            $st = input('param.st');
            if($st == 0){
                $where[] = ['status','in','0,1'];
            }elseif($st == 1){
                $where[] = ['status','=',1];
            }elseif($st == 2){
                $where[] = ['status','=',0];
            }

            $datalist = Db::name('member_commission_butie')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
            if(!$datalist) $datalist = [];
            foreach($datalist as $k=>$v){
                if($v['type']){
                    $datalist[$k]['orderstatus'] = Db::name($v['type'].'_order')->where('id',$v['orderid'])->value('status');
                }
                if($v['frommid']){
                    $frommember = Db::name('member')->where('id',$v['frommid'])->find();
                    if($frommember){
                        $datalist[$k]['fromheadimg'] = $frommember['headimg'];
                        $datalist[$k]['fromnickname'] = $frommember['nickname'];
                    }else{
                        $datalist[$k]['fromheadimg'] = '';
                        $datalist[$k]['fromnickname'] = '';
                    }
                }
                if($v['fx_butie_type']==1){
                    //按周
                    $circle_str = '每周'.$v['fx_butie_send_week'].'发放';
                }else{
                    $circle_str = '每月'.$v['fx_butie_send_day'].'号发放';
                }
                $datalist[$k]['circle_str'] = $circle_str;
            }
            return $this->json(['status'=>1,'data'=>$datalist]);
        }
    }
    //分销补贴发放记录
    public function commissionbutielog(){
        if(getcustom('commission_butie')){
            $pernum = 20;
            $pagenum = input('post.pagenum');
            if(!$pagenum) $pagenum = 1;

            $where = [];
            $where[] = ['aid','=',aid];
            $where[] = ['mid','=',mid];
            if(input('pid')){
                $where[] = ['pid','=',input('pid')];
            }

            $datalist = Db::name('member_commission_butie_log')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
            if(!$datalist) $datalist = [];
            foreach($datalist as $k=>$v){

            }
            return $this->json(['status'=>1,'data'=>$datalist]);
        }
    }

    public function scorerank(){
        if(getcustom('score_ranking')){
            $pernum = 10;
            $pagenum = input('post.pagenum');
            if(!$pagenum) $pagenum = 1;
            $sysset = $this->sysset;
            if($sysset['score_rank_status']==0){
                $data = [];
                $data['status'] = 0;
                return $this->json(['status'=>0,'data'=>$data]);
            }
            $totalscore = $this->member['totalscore'];
            $rdata = [];
            $rdata['totalscore'] = $totalscore;
            $rdata['sysset'] = $sysset;
            $where = [];
            $ranknum = $sysset['score_rank_people'];
            $totalpage = ceil($ranknum/$pernum);//2

            $quyu = $pernum;
            if($pernum * $pagenum > $ranknum) $quyu = $ranknum - $pernum * ($pagenum-1);
            if($ranknum>0){
                if($pagenum>$totalpage){
                    $rdata['data'] = [];
                    return $this->json(['status'=>0,'data'=>$rdata]);
                }
            }
            $where[] = ['o.aid','=',aid];
            if($sysset['score_rank_date'] == 2){
                $beginWeek = mktime(0,0,0,date("m"),date("d")-date("w")+1,date("Y"));
                $where[] = ['o.createtime','>=',$beginWeek];
            }
            if($sysset['score_rank_date'] == 3){
                $date = time();
                $firstDay = strtotime(date('Y-m-01',$date));
                $where[] = ['o.createtime','>=',$firstDay];
            }
            if($sysset['score_rank_date'] == 4){
                $begindate = strtotime('-7 days');
                $enddate =time();
                $where[] = ['o.createtime','between',[$begindate,$enddate]];
            }

            if($sysset['score_rank_date'] == 5){
                $begindate = strtotime("-1 month");
                $enddate =time();
                $where[] = ['o.createtime','between',[$begindate,$enddate]];
            }
            $datalist = Db::name('member')->field('o.*,sum(o.score) sumscore,m.headimg,m.nickname')->alias('m')->leftJoin('member_scorelog o','o.mid=m.id')->where($where);
            if($ranknum == 0){
                $datalist = $datalist->page($pagenum,$pernum);
            }else{
                $datalist = $datalist->limit(($pagenum-1)*$pernum,$quyu);
            }
            $datalist = $datalist->order('sumscore desc')->group('o.mid')->select()->toArray();
            if(!$datalist) $datalist = [];
            $moeny_weishu = 2;
            foreach ($datalist as $key=>$val){
                $datalist[$key]['score']    = $val['score'];
                $datalist[$key]['sumscore'] = $val['sumscore'];
            }
            $rdata['data'] = $datalist;
            return $this->json(['status'=>1,'data'=>$rdata]);
        }
        
    }
	public function signature(){
		$signatureurl = input('param.signatureurl');
		$levelinfo = Db::name('member_level')->where('id',$this->member['levelid'])->find();
		$level_agree = Db::name('member_level_agree')->where('aid',aid)->where('mid',mid)->where('newlv_id',$levelinfo['id'])->find();
		if(!$level_agree){
			$id = Db::name('member_level_agree')->insertGetId(['aid'=>aid,'mid'=>mid,'newlv_id'=>$levelinfo['id'],'w_time'=>time()]);
		}else{
			$id = $level_agree['id'];
		}
		Db::name('member_level_agree')->where('id',$id)->update(['status'=>1,'signatureurl'=>$signatureurl]);
		return $this->json(['status'=>1,'msg'=>'操作成功']);
	}

    public function ranking(){
        if(getcustom('areafenhong_region_ranking')){
            $pagenum = input('post.pagenum');
            $st      = input('post.st');
            $rdata = \app\customs\AgentCustom::ranking(aid,$pagenum,$st);
            return $this->json($rdata);
        }
    }
    //区域代理排行详情
    public function rankingdetial(){
        if(getcustom('areafenhong_region_ranking')){
            $id = input('id')?input('id/d'):'';
            if(!$id){
                return $this->json(['status'=>0,'msg' =>'请选排行榜活动']);
            }
            $ranktype = input('ranktype')?input('ranktype/d'):'';
            $pagenum  = input('pagenum')?input('pagenum/d'):1;
            $sorttype = input('sorttype')?input('sorttype/d'):1;
            $rdata = \app\customs\AgentCustom::rankingdetial(aid,$this->member,$id,$ranktype,$pagenum,$sorttype);
            return $this->json($rdata);
        }
    }
    //区域代理排行活动订单
    public function rankingorder(){
        if(getcustom('areafenhong_region_ranking')){
            $id = input('id')?input('id/d'):'';
            if(!$id){
                return $this->json(['status'=>0,'msg' =>'请选排行榜活动']);
            }
            $pagenum  = input('pagenum')?input('pagenum/d'):1;
            $sorttype = input('sorttype')?input('sorttype/d'):1;
            $rdata = \app\customs\AgentCustom::rankingorder(aid,$this->member,$id,$pagenum,$sorttype);
            return $this->json($rdata);
        }
    }
    //甘尔定制奖金池数据
    public function prize_pool(){
	    if(getcustom('ganer_fenxiao')){
            $aid = aid;
            $set = Db::name('prize_pool_set')->where('aid',$aid)->find();
            $pool_num = $set['pool_num'];
            if($set['show_pool']==1){
                $pool_num =  bcmul($set['pool_num'],$set['send_bili']/100,2);
            }
            $level_ids = json_decode($set['levelids'],true);
            $level_arr = Db::name('member_level')->where('aid',$aid)->field('id,name')->select()->toArray();
            foreach($level_arr as $k=>$level){
                //本月将分红总金额
                $level_bili = $level_ids[$level['id']]??0;
                if($level_bili<=0){
                    unset($level_arr[$k]);
                    continue;
                }
                $prize_total = bcmul($set['pool_num'],$set['send_bili']/100,2);
                $level_prize = bcmul($prize_total,$level_bili/100,2);
                //上月分红总金额
                $e_time = strtotime(date('Y-m-01'));
                $s_time = strtotime(date('Y-m-01',$e_time-86400));
                $map = [];
                $map[] = ['aid','=',$aid];
                $map[] = ['createtime','between',[$s_time,$e_time]];
                $last_month_prize = Db::name('prize_pool_send_log')->where($map)->sum('level_prize_total');
                //分红人数
                $map = [];
                $map[] = ['levelid', '=', $level['id']];
                $map[] = ['aid', '=', $aid];
                $member_count = Db::name('member')->where($map)->count();
                //人均分红
                $avg_prize = bcdiv($level_prize, $member_count, 2);

                $level_arr[$k]['level_prize'] = $level_prize?:0;
                $level_arr[$k]['last_month_prize'] = $last_month_prize?:0;
                $level_arr[$k]['member_count'] = $member_count?:0;
                $level_arr[$k]['avg_prize'] = $avg_prize?:0;

            }
            return json(['status'=>1,'pool_num'=>$pool_num,'level_arr'=>$level_arr,'title'=>'奖金池分红']);
        }
    }

    public function shoporderranking(){
        if(getcustom('shoporder_ranking')){
            //商城订单消费排行榜
            $st = input('st')?input('st/d'):0;
            $rdata = \app\customs\AgentCustom::shoporderranking(aid,$this->member,$st);
            return $this->json($rdata);
        }
    }
    public function shoporderrankinglog(){
        if(getcustom('shoporder_ranking')){
            //商城订单消费排行榜记录
            $pagenum = input('pagenum')?input('pagenum/d'):1;
            $rdata = \app\customs\AgentCustom::shoporderrankinglog(aid,$this->member,$pagenum);
            return $this->json($rdata);
        }
    }
    public function shoporderrankingdetail(){
        if(getcustom('shoporder_ranking')){
            //商城订单消费排行榜记录详情
            $id = input('id')?input('id/d'):0;
            $st = input('st')?input('st/d'):0;
            $rdata = \app\customs\AgentCustom::shoporderrankingdetail(aid,$this->member,$id,$st);
            return $this->json($rdata);
        }
    }

    //视频号小店分销订单
    public function agorder_wxchannels(){
	    if(getcustom('wx_channels')){
            $pernum = 20;
            $pagenum = input('param.pagenum') ? input('param.pagenum') :1;
            $where = '';
            $st = input('param.st/d');
            if(!$st) $st = 0;
            if($st==10){//待付款
                $where = " and o.status=10";
            }
            if($st==20){//已付款
                $where = " and (o.status=20 or o.status=21 or o.status=30 )";
            }
            if($st==100){//已完成
                $where = " and o.status=100";
            }
            if($st==5){//售后
                $where = " and og.finish_aftersale_sku_cnt>0";
            }
            $field = 'og.id,o.unionid,o.order_id,o.status,o.create_time,o.pay_time,og.finish_aftersale_sku_cnt,og.title,og.product_id,og.thumb_img,og.sku_cnt,og.parent1,og.parent2,og.parent3,og.parent1commission,og.parent2commission,og.parent3commission,og.parent1score,og.parent2score,og.parent3score,og.real_price';
            $datalist = Db::query("select ".$field." from ".table_name('channels_order_goods')." og left join ".table_name('channels_order')." o on o.order_id=og.order_id where og.aid=".aid." $where and  (og.parent1=".mid." or og.parent2=".mid." or og.parent3=".mid.") order by og.id desc limit ".($pagenum*$pernum-$pernum).','.$pernum);
            if(!$datalist) $datalist = [];
            $newdatalist = [];
            foreach($datalist as $k=>$v){
                $data = [];
                $data['ordernum'] = $v['order_id'];
                $data['name'] = $v['title'];
                $data['pic'] = $v['thumb_img'];
                $data['num'] = $v['sku_cnt'];
                $data['totalprice'] = $v['real_price'];
                $data['createtime'] = date('Y-m-d H:i',$v['create_time']);
                $data['status'] = $v['status'];
                $memberinfo = Db::name('member')->where('unionid',$v['unionid'])->field('nickname,headimg')->find();
                $data['nickname'] = $memberinfo['nickname'];
                $data['headimg'] = $memberinfo['headimg'];

                if($v['parent1'] == mid){
                    $data['dengji'] = t('一级');
                    if($v['parent1score'] > 0){
                        $v['parent1score'] = dd_money_format($v['parent1score'],$this->score_weishu);
                        $data['commission'] = $v['parent1score'].t('积分');
                    }else{
                        $data['commission'] = $v['parent1commission'].'元';
                    }
                    $data['dannum'] = 0;
                }
                if($v['parent2'] == mid){
                    $data['dengji'] = t('二级');
                    if($v['parent2score'] > 0){
                        $v['parent2score'] = dd_money_format($v['parent2score'],$this->score_weishu);
                        $data['commission'] = $v['parent2score'].t('积分');
                    }else{
                        $data['commission'] = $v['parent2commission'].'元';
                    }
                    $data['dannum'] = 0;
                }
                if($v['parent3'] == mid){
                    $data['dengji'] = t('三级');
                    if($v['parent3score'] > 0){
                        $v['parent2score'] = dd_money_format($v['parent2score'],$this->score_weishu);
                        $data['commission'] = $v['parent3score'].t('积分');
                    }else{
                        $data['commission'] = $v['parent3commission'].'元';
                    }
                    $data['dannum'] = 0;
                }
                $data['order_info'] = false;
                $newdatalist[] = $data;
            }
            if(request()->isPost()){
                return $this->json(['data'=>$newdatalist]);
            }

            $count = Db::query("select count(1)c from ".table_name('channels_order_goods')." og left join ".table_name('channels_order')." o on o.order_id=og.order_id where og.aid=".aid." and (og.parent1=".mid." or og.parent2=".mid." or og.parent3=".mid.")");
            $count = 0 + $count[0]['c'];

            $count1 = Db::query("select count(1)c from ".table_name('channels_order_goods')." og left join ".table_name('channels_order')." o on o.order_id=og.order_id where og.aid=".aid." and o.status=10 and (og.parent1=".mid." or og.parent2=".mid." or og.parent3=".mid.")");
            $count1 = 0 + $count1[0]['c'];

            $count2 = Db::query("select count(1)c from ".table_name('channels_order_goods')." og left join ".table_name('channels_order')." o on o.order_id=og.order_id where og.aid=".aid." and (o.status=20 or o.status=21 or o.status=30) and (og.parent1=".mid." or og.parent2=".mid." or og.parent3=".mid.")");
            $count2 = 0 + $count2[0]['c'];

            $count3 = Db::query("select count(1)c from ".table_name('channels_order_goods')." og left join ".table_name('channels_order')." o on o.order_id=og.order_id where og.aid=".aid." and o.status=100 and (og.parent1=".mid." or og.parent2=".mid." or og.parent3=".mid.")");
            $count3 = 0 + $count3[0]['c'];

            $commissionyj1 = Db::query("select sum(parent1commission)c from ".table_name('channels_order_goods')." og left join ".table_name('channels_order')." o on o.order_id=og.order_id where og.aid=".aid." and (o.status=10 or o.status=100 or o.status=20 or o.status=21 or o.status=30) and og.parent1=".mid."");
            $commissionyj2 = Db::query("select sum(parent2commission)c from ".table_name('channels_order_goods')." og left join ".table_name('channels_order')." o on o.order_id=og.order_id where og.aid=".aid." and (o.status=10 or o.status=100 or o.status=20 or o.status=21 or o.status=30) and og.parent2=".mid."");
            $commissionyj3 = Db::query("select sum(parent3commission)c from ".table_name('channels_order_goods')." og left join ".table_name('channels_order')." o on o.order_id=og.order_id where og.aid=".aid." and (o.status=10 or o.status=100 or o.status=20 or o.status=21 or o.status=30) and og.parent3=".mid."");

            $commissionyj = 0 + $commissionyj1[0]['c'] + $commissionyj2[0]['c'] + $commissionyj3[0]['c'];

            $rdata = [];
            $rdata['count'] = $count;
            $rdata['count1'] = $count1;
            $rdata['count2'] = $count2;
            $rdata['count3'] = $count3;
            $rdata['commissionyj'] = round($commissionyj,2);
            $rdata['datalist'] = $newdatalist;
            $rdata['st'] = $st;
            return $this->json($rdata);
        }
    }
    
    public function  getteamsaleyejilog(){
        $is_include_self = getcustom('yx_team_yeji_include_self');
        $is_jicha_custom = getcustom('yx_team_yeji_jicha');
       if(getcustom('yx_team_yeji')){
           $st = input('param.st');
           $pagenum = input('param.pagenum');
           if(!$pagenum) $pagenum = 1;
           
           $yejiwhere = [];
           $yeji_set = Db::name('team_yeji_set')->where('aid',aid)->find();
           $now_month = date('Y-m',strtotime('-1 month'));
           $levelup_time = 0;
           if($is_jicha_custom){
               $yeji_set_config =json_decode($yeji_set['config_data'],true);
               $show_levelid = array_keys($yeji_set_config);
               if(in_array($this->member['levelid'],$show_levelid)){
                   $levelup_order = Db::name('member_levelup_order')
                       ->where('aid',aid)
                       ->where('mid',mid)
                       ->where('levelid',$this->member['levelid'])
                       ->where('status',2)
                       ->order('levelup_time desc')
                       ->find();
                   $levelup_time = $levelup_order['levelup_time'];
               }
           }
           if($st ==1){//待结算
               if($yeji_set['jiesuan_type'] == 1){//按月
                   $month_start = strtotime(date('Y-m-01 00:00:00'));
                   $month_end  = strtotime(date('Y-m-t 23:59:59'));
                   if($is_jicha_custom){
                       if($levelup_time && $levelup_time > $month_start )$month_start =   $levelup_time;
                   }
                   $yejiwhere[] = ['createtime','between',[$month_start,$month_end]];
                   //虚拟业绩
                   $xuni_yeji = 0 +Db::name('tem_yeji_xuni')->where('aid',aid)->where('mid',mid)->where('yeji_month',$now_month)->value('yeji');
               }elseif($yeji_set['jiesuan_type'] == 2){//按年
                   $year_start=strtotime(date('Y') . '-01-01 00:00:00');
                   $year_end=strtotime(date('Y') . '-12-31 23:59:59');
                   if($is_jicha_custom){
                       if($levelup_time && $levelup_time > $year_start )$year_start =   $levelup_time;
                   }
                   $yejiwhere[] = ['createtime','between',[$year_start,$year_end]];
               }elseif($yeji_set['jiesuan_type'] == 3){//按季度
                   $season_start=strtotime(date('Y-m-01 00:00:00'));
                   $season_end=strtotime(date('Y-m-t 23:59:59',strtotime('+3 month')));
                   if($is_jicha_custom){
                       if($levelup_time && $levelup_time > $season_start )$season_start =   $levelup_time;
                   }
                   $yejiwhere[] = ['createtime','between',[$season_start,$season_end]];
               }
           }
           else{//已结算
               if($yeji_set['jiesuan_type'] == 1){//按月
                   $month_start = strtotime(date('Y-m-01 00:00:00',strtotime('-1 month')));
                   $month_end  = strtotime(date('Y-m-t 23:59:59',strtotime('-1 month')));
                   if($is_jicha_custom){
                       //升级时间大于结束时间，无业绩
                       if($levelup_time > $month_end){
                           $month_start = $month_end;
                       }elseif ($levelup_time > $month_start && $levelup_time < $month_end){
                           $month_start =  $levelup_time;
                       }
                   }
                   $yejiwhere[] = ['createtime','between',[$month_start,$month_end]];
               }elseif($yeji_set['jiesuan_type'] == 2){//按年
                   $year_start=strtotime((date('Y')-1) . '-01-01 00:00:00');
                   $year_end=strtotime((date('Y')-1) . '-12-31 23:59:59');
                   if($is_jicha_custom){
                       if($levelup_time > $year_end){
                           $year_start = $year_end;
                       }elseif ($levelup_time > $year_start && $levelup_time < $year_end){
                           $year_start =  $levelup_time;
                       }
                   }
                   $yejiwhere[] = ['createtime','between',[$year_start,$year_end]];
               }elseif($yeji_set['jiesuan_type'] == 3){//按季度
                   $season_start=strtotime(date('Y-m-01 00:00:00',strtotime('-3 month')));
                   $season_end=strtotime(date('Y-m-t 23:59:59',strtotime('-1 month')));
                   if($is_jicha_custom){
                       if($levelup_time > $season_end){
                           $season_start =$season_end ;
                       }elseif ($levelup_time > $season_start && $levelup_time < $season_end){
                           $season_start =  $levelup_time;
                       }
                   }
                   $yejiwhere[] = ['createtime','between',[$season_start,$season_end]];
               }
           }
           $yejiwhere[] = ['status','in','1,2,3'];
           $deep = 999;
           $downmids = \app\commons\Member::getteammids(aid,mid,$deep);
           if($is_include_self){
               if($yeji_set['include_self']) $downmids[] = mid;
           }
           $count = Db::name('shop_order_goods')->where('aid',aid)->where('mid','in',$downmids)->where($yejiwhere)->count();
           $datalist = Db::name('shop_order_goods')->where('aid',aid)->where('mid','in',$downmids)->where($yejiwhere)->page($pagenum,20)->select()->toArray();
            foreach($datalist as $key=>&$val){
                $member = Db::name('member')->where('aid',aid)->where('id',$val['mid'])->field('headimg,nickname')->find();
                $val['nickname'] = $member['nickname']??'';
                $val['headimg'] = $member['headimg']??'';
            }
           if(!$datalist) $datalist = [];
           return $this->json(['status'=>1,'datalist'=>$datalist,'count' => $count]);
       } 
    }
    
    
    
    
    
    
    
    
      //我的团队
	public function gongpais(){
	    $mid = input('mid')?input('mid/d'):0;
	    if(!$mid){
	        $mid = mid;
        }
		$userinfo = Db::name('member')->field('id,nickname,headimg,levelid,last_visittime')->where('aid',aid)->where('id',$mid)->find();
		$downdeep = input('param.st/d');
		$pernum = 20;
		$pagenum = input('post.pagenum');
		$keyword = input('post.keyword');
        $order = "id desc";
		$where2 = "1=1";
		if(!$pagenum) $pagenum = 1;
		if(!$downdeep) $downdeep = 1;
		if(!$mid){
			$datalist = [];
		}else{
			if($downdeep == 1){
				$datalist = Db::name('member_gongpai')->field("*,from_unixtime(createtime)createtime")->where("aid",aid)->where("mid",$mid)->page($pagenum,$pernum)->order($order)->select()->toArray();
			}elseif($downdeep == 2){
              	$datalist = Db::name('member_gongpai_two')->field("*,from_unixtime(createtime)createtime")->where("aid",aid)->where("mid",$mid)->page($pagenum,$pernum)->order($order)->select()->toArray();
			}
		}
		if(!$datalist) $datalist = [];
		foreach($datalist as $k=>$v){
			//if($downdeep==1){
			//	$commission = Db::name('shop_order_goods')->where('aid',aid)->where('mid',$v['id'])->where('parent1',mid)->where('status',3)->sum('parent1commission');
			//}
			//if($downdeep==2){
			//	$commission = Db::name('shop_order_goods')->where('aid',aid)->where('mid',$v['id'])->where('parent2',mid)->where('status',3)->sum('parent2commission');
			//}
			//if($downdeep==3){
			//	$commission = Db::name('shop_order_goods')->where('aid',aid)->where('mid',$v['id'])->where('parent3',mid)->where('status',3)->sum('parent3commission');
			//}
        }
		$rdata = [];
		$rdata['datalist'] = $datalist;
		$rdata['userinfo'] = $userinfo;
        $rdata['text1'] = '49排';
        $rdata['text2'] = '36排';
		$rdata['st'] = $downdeep;
		return $this->json($rdata);
	}
   public function gongpai(){
        $downdeep = input('post.st/d');
		if ($downdeep==2) {
		    $member = Db::name('member_gongpai_two')->where('aid',aid)->where('id',input('post.mid/d'))->find();
	    	$mid = $member['id'];
		    $where = [];
            $where[] = ['member_gongpai_two.id','=',$mid];
            $where[] = ['member_gongpai_two.aid','=',aid];
    		$member = Db::name('member_gongpai_two')->alias('member_gongpai_two')->field('member.nickname,member.headimg,member.pid,member.levelid,member_gongpai_two.*')->join('member member','member.id=member_gongpai_two.mid')->where($where)->find();
    		if($member){
    		    $member['name'] = $member['nickname'];
    		    $memberlist = Db::name('member_gongpai_two')->where('member_gongpai_two.aid',aid)->where('member_gongpai_two.pid',$mid)->alias('member_gongpai_two')->field('member.nickname,member.headimg,member.pid,member.levelid,member_gongpai_two.*')->join('member member','member.id=member_gongpai_two.mid')->select()->toArray();
    		    if(!$memberlist) $memberlist = [];
    		    if (count($memberlist) < 2) {
    		        $memberlist = array_merge($memberlist,[['nickname'=>'空']]);
    		        if (count($memberlist) < 2) {
        		        $memberlist = array_merge($memberlist,[['nickname'=>'空']]);
        		    } 
    		    } 
        		foreach($memberlist as $k2=>$member2){
        		     $memberlist2 = Db::name('member_gongpai_two')->where('member_gongpai_two.aid',aid)->where('member_gongpai_two.pid',$member2['id'])->alias('member_gongpai_two')->field('member.nickname,member.headimg,member.pid,member.levelid,member_gongpai_two.*')->join('member member','member.id=member_gongpai_two.mid')->select()->toArray();
        		     if (count($memberlist2) < 2) {
        		        $memberlist2 = array_merge($memberlist2,[['nickname'=>'空']]);
        		        if (count($memberlist2) < 2) {
            		        $memberlist2 = array_merge($memberlist2,[['nickname'=>'空']]);
            		    } 
        		    } 
            		foreach($memberlist2 as $k3=>$member3){
            			$memberlist2[$k3]['name'] = $member3['nickname'];
            		}
        			$memberlist[$k2]['children'] = $memberlist2;
        			$memberlist[$k2]['name'] = $member2['nickname'];
        		}
    		    
    			$member['children'] = $memberlist;
    			
    		}
		} else {
		    $member = Db::name('member_gongpai')->where('aid',aid)->where('id',input('post.mid/d'))->find();
	    	$mid = $member['id'];
		    $where = [];
            $where[] = ['member_gongpai.id','=',$mid];
            $where[] = ['member_gongpai.aid','=',aid];
    		$member = Db::name('member_gongpai')->alias('member_gongpai')->field('member.nickname,member.headimg,member.pid,member.levelid,member_gongpai.*')->join('member member','member.id=member_gongpai.mid')->where($where)->find();
    		if($member){
    		    $member['name'] = $member['nickname'];
    		    $memberlist = Db::name('member_gongpai')->where('member_gongpai.aid',aid)->where('member_gongpai.pid',$mid)->alias('member_gongpai')->field('member.nickname,member.headimg,member.pid,member.levelid,member_gongpai.*')->join('member member','member.id=member_gongpai.mid')->select()->toArray();
    		    if(!$memberlist) $memberlist = [];
    		    if (count($memberlist) < 3) {
    		        $memberlist = array_merge($memberlist,[['nickname'=>'空']]);
    		        if (count($memberlist) < 3) {
        		        $memberlist = array_merge($memberlist,[['nickname'=>'空']]);
        		        if (count($memberlist) < 3) {
            		        $memberlist = array_merge($memberlist,[['nickname'=>'空']]);
            		    } 
        		    } 
    		    } 
        		foreach($memberlist as $k2=>$member2){
        		    $memberlist2 = Db::name('member_gongpai')->where('member_gongpai.aid',aid)->where('member_gongpai.pid',$member2['id'])->alias('member_gongpai')->field('member.nickname,member.headimg,member.pid,member.levelid,member_gongpai.*')->join('member member','member.id=member_gongpai.mid')->select()->toArray();
        		    if(!$memberlist2) $memberlist2 = [];
            		if (count($memberlist2) < 3) {
        		        $memberlist2 = array_merge($memberlist2,[['nickname'=>'空']]);
        		        if (count($memberlist2) < 3) {
            		        $memberlist2 = array_merge($memberlist2,[['nickname'=>'空']]);
            		        if (count($memberlist2) < 3) {
                		        $memberlist2 = array_merge($memberlist2,[['nickname'=>'空']]);
                		    } 
            		    } 
        		    } 
            		foreach($memberlist2 as $k3=>$member3){
            			$memberlist2[$k3]['name'] = $member3['nickname'];
            		}
        			$memberlist[$k2]['children'] = $memberlist2;
        			$memberlist[$k2]['name'] = $member2['nickname'];
        		}
    		    
    			$member['children'] = $memberlist;
    		}
		}
		
		ll($memberlist,'$member');
		
	    return $this->json(['status'=>1,'datalist'=>[$member],'count' => $count]);
	}
    
// 	private $deeplevel = 0;
// 	private function getgongpaidown($mid,$deeplevel){
// 		if($deeplevel > 3) return [];
// 		$deeplevel++;
// 		$memberlist = Db::name('member_gongpai')->where('member_gongpai.aid',aid)->where('member_gongpai.pid',$mid)->alias('member_gongpai')->field('member.nickname,member.headimg,member.pid,member.levelid,member_gongpai.*')->join('member member','member.id=member_gongpai.mid')->select()->toArray();
// 		foreach($memberlist as $k=>$member){
// 			$memberlist[$k]['children'] = $this->getgongpaidown($member['id'],$deeplevel);
// 			$memberlist[$k]['name'] = $member['nickname'];
// 		}
// 		if($this->deeplevel < $deeplevel) $this->deeplevel = $deeplevel;
// 		return $memberlist;
// 	}

    
    
    
    
    
    
    
    
    
}