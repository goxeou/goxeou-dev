<?php


namespace app\plugin;
use think\facade\Db;
class TeamLevel
{
	 
    
    public static function get_team($aid, $mid)
    {
        $member = Db::name('member')->where('aid', $aid)->where('id', $mid)->field('id,path,levelid,teamlevel')->find();
        if (!$member) {
            return;
        }
        self::get_teamlevel($aid, $member);
        if ($member['path']) {
            $parentList = Db::name('member')->where('aid', $aid)->where('id', 'in', $member['path'])->field('id,path,levelid,teamlevel')->order(Db::raw('field(id,' . $member['path'] . ')'))->select()->toArray();
            $parentList = array_reverse($parentList);
            foreach ($parentList as $parent) {
                self::get_teamlevel($aid, $parent);
            }
        }
    }
    public static function get_teamlevel($aid, $member)
    {
        $mid = $member['id'];
        $nowlv = ['sort' => -1];
        if ($member['teamlevel'] > 0) {
            $nowlv = Db::name('member_team_level')->where('aid', $aid)->where('id', $member['teamlevel'])->find();
        }
        $lvlist = Db::name('member_team_level')->where('aid', $aid)->where('sort', '>', $nowlv['sort'])->order('sort,id')->select();
        //->limit(1)
        
        
    //     $times =  getTime('本月');
    // 	$dayStart = $times[0];
    // 	$dayEnd = $times[1];
    	
    // 	$where = [];
    // 	$where[] = ['aid', '=', $aid];
    // 	$where[] = ['commissionset', '=',7];
    // 	$where[] = ['status', 'in', '1,2,3'];
    //     $where[] = ['createtime', 'between', [$dayStart,$dayEnd]];//
    //     $downmids = \app\commons\Member::getteammids($aid, $mid);
    //     $buynum = 0 + Db::name('shop_order_goods')->where($where)->where('mid','in',$downmids)->sum('num');
     
        
        $newlv = $nowlv;
        foreach ($lvlist as $lv) {
            $isup = false;
            if (($lv['up_self_levelid'] != '0' && $lv['up_self_levelid'] != '') || ($lv['up_self_teamid'] != '0' && $lv['up_self_teamid'] != '')) {
                $up_self_levelid = explode(',', str_replace('，', ',', $lv['up_self_levelid']));
                $up_self_teamid= explode(',', str_replace('，', ',', $lv['up_self_teamid']));
                if (in_array($member['levelid'], $up_self_levelid) || in_array($member['teamlevel'], $up_self_teamid)) {
                    $isup = true;
                }
            }
            if (($isup && $lv['up_fxdowncount'] > 0) || ($isup && $lv['up_fxdowncount2'] > 0)) {
                $downmidcount1 = 0;
                $downmidcount2 = 0;
                if ($lv['up_fxdowncount']>0) {
                    $downmids1 = \app\commons\Member::getdownmids($aid, $mid, $lv['up_fxdownlevelnum'], $lv['up_fxdownlevelid'], $lv['up_with_origin'], $lv['up_with_new']);
                    $downmidcount1 = count($downmids1);
                }
               if ($lv['up_fxdowncount2']>0) {
                    $downmids2 = \app\commons\Member::getdownteammids($aid, $mid, $lv['up_fxdownlevelnum2'], $lv['up_fxdownlevelid2'], $lv['up_with_origin'], $lv['up_with_new']);
                    $downmidcount2 = count($downmids2);
                }
                if (($downmidcount1 >= intval($lv['up_fxdowncount'])) && ($downmidcount2 >= intval($lv['up_fxdowncount2']))) {
                    $isup = true;
                } else {
                    $isup = false;
                }
            }
            if ($isup && $lv['up_fxordermoney'] > 0) {//getdownmids_removemax
                $downmids = \app\commons\Member::getdownmids($aid, $mid, $lv['up_fxorderlevelnum'], $lv['up_fxorderlevelid']);
                //->where('commissionset',7)
                $fxordermoney = 0 + Db::name('shop_order_goods')->where('status', 'in', '1,2,3')->where('mid', 'in', $downmids)->sum('totalprice');
                if ($fxordermoney >= $lv['up_fxordermoney']) {
                    $isup = true;
                } else {
                    $isup = false;
                }
            }
            if ($isup && $lv['up_fxordermoney2'] > 0) {//getdownmids_removemax
                $downmids = \app\commons\Member::getdownmids($aid, $mid, $lv['up_fxorderlevelnum2'], $lv['up_fxorderlevelid2']);
                //->where('commissionset',7)
                $fxordermoney = 0 + Db::name('shop_order_goods')->where('status', 'in', '1,2,3')->where('mid', 'in', $downmids)->sum('totalprice');
                if ($fxordermoney >= $lv['up_fxordermoney2']) {
                    $isup = true;
                } else {
                    $isup = false;
                }
            }
            if(!$isup && ($lv['up_proid']!='0' && $lv['up_proid']!='')){ //购买指定商品
                $up_proids = explode(',',str_replace('，',',',$lv['up_proid']));
                $up_pronums = explode(',',str_replace('，',',',$lv['up_pronum']));
                if(count($up_pronums) > 1) {
                    foreach($up_proids as $k=>$up_proid){
                        $pronum = $up_pronums[$k];
                        if(!$pronum) $pronum = 1;
                        $buynum = Db::name('shop_order_goods')->where('aid',$aid)->where('mid',$mid)->where('proid',$up_proid)->where('status','in','1,2,3')->sum('num');
                        if($buynum >= $pronum){
                            $isup = true;
//                        break;
                        }
                    }
                } else {
                    $pronum = $up_pronums[0];
                    if(!$pronum) $pronum = 1;
                    $buynum = 0;
                    foreach($up_proids as $k=>$up_proid){
                        $buynum += Db::name('shop_order_goods')->where('aid',$aid)->where('mid',$mid)->where('proid',$up_proid)->where('status','in','1,2,3')->sum('num');
                        if($buynum >= $pronum){
                            $isup = true;
//                        break;
                        }
                    }
                }
            }
            // if($isup && $lv['pronum']>0){ //购买指定商品
            //     if($buynum >= $lv['pronum']){
            //         $isup = true;
            //     }else {
            //         $isup = false;
            //     }
            // }
            if ($isup) {
                $newlv = $lv;
            }
        }
        if ($newlv && $newlv['id'] != $member['teamlevel']) {
            Db::name('member')->where('aid', $aid)->where('id', $member['id'])->update(['teamlevel' => $newlv['id']]);
            if($newlv['up_give_score'] > 0) {
                \app\commons\Member::addscore($aid, $mid, $newlv['up_give_score'], '升级奖励');
            }
            //奖励佣金
            if($newlv['up_give_commission'] > 0) {
                \app\commons\Member::addcommission($aid,$mid,0,$newlv['up_give_commission'],'升级奖励');
            }
            //奖励余额
            if($newlv['up_give_money'] > 0) {
                \app\commons\Member::addmoney($aid,$mid,$newlv['up_give_money'],'升级奖励');
            }
            
             //奖励余额
            if($newlv['up_give_credit1'] > 0) {
                \app\commons\Member::addcredit1($aid,$mid,$newlv['up_give_credit1'],'升级奖励');
            }
            
             //奖励余额
            if($newlv['up_give_credit2'] > 0) {
                \app\commons\Member::addcredit2($aid,$mid,$newlv['up_give_credit2'],'升级奖励');
            }
			//升级记录
            $order = [
                'aid' => $aid,
                'mid' => $mid,
                'from_mid' => $mid,
                'levelid' => $newlv['id'] ,
                'title' => '自动升级',
                'totalprice' => 0,
                'createtime' => time(),
                'levelup_time' => time(),
                'beforelevelid' => $member['teamlevel'],
                'form0' => '类型^_^自动升级',
                'platform' => platform,
                'status' => 2,
            ];
            Db::name('member_teamup_order')->insert($order);
        }
    }
    public static function get_store($aid, $mid)
    {
        $member = Db::name('member')->where('aid', $aid)->where('id', $mid)->field('id,path,levelid,storelevel')->find();
        if (!$member) {
            return;
        }
        self::get_storelevel($aid, $member);
        if ($member['pid']) {
            $parent = Db::name('member')->where('aid', $aid)->where('id',  $member['pid'])->field('id,path,levelid,storelevel')->find();
            self::get_storelevel($aid, $parent);
        }
    }
    public static function get_storelevel($aid, $member)
    {
        $mid = $member['id'];
        $nowlv = ['sort' => -1];
        if ($member['storelevel'] > 0) {
            $nowlv = Db::name('member_store')->where('aid', $aid)->where('id', $member['storelevel'])->find();
        }
        $lvlist = Db::name('member_store')->where('aid', $aid)->where('sort', '>', $nowlv['sort'])->order('sort,id')->select();
        //->limit(1)
        $newlv = $nowlv;
        foreach ($lvlist as $lv) {
            $isup = false;
            if ($lv['up_self_storeid'] != '0' && $lv['up_self_storeid'] != '') {
                $up_self_levelid = explode(',', str_replace('，', ',', $lv['up_self_storeid']));
                if (in_array($member['levelid'], $up_self_levelid)) {
                    $isup = true;
                }
            }
            if ($isup && $lv['up_fxdowncount'] > 0) {
                $downmids = \app\commons\Member::getdownmids($aid, $mid, $lv['up_fxdownlevelnum'], $lv['up_fxdownlevelid']);
                $downmidcount1 = count($downmids);
                if ($downmidcount1 >= intval($lv['up_fxdowncount'])) {
                    $isup = true;
                } else {
                    $isup = false;
                }
            }
            if(!$isup && ($lv['up_proid']!='0' && $lv['up_proid']!='')){ //购买指定商品
                $up_proids = explode(',',str_replace('，',',',$lv['up_proid']));
                $up_pronums = explode(',',str_replace('，',',',$lv['up_pronum']));
                if(count($up_pronums) > 1) {
                    foreach($up_proids as $k=>$up_proid){
                        $pronum = $up_pronums[$k];
                        if(!$pronum) $pronum = 1;
                        $buynum = Db::name('shop_order_goods')->where('aid',$aid)->where('mid',$mid)->where('proid',$up_proid)->where('status','in','1,2,3')->sum('num');
                        if($buynum >= $pronum){
                            $isup = true;
//                        break;
                        }
                    }
                } else {
                    $pronum = $up_pronums[0];
                    if(!$pronum) $pronum = 1;
                    $buynum = 0;
                    foreach($up_proids as $k=>$up_proid){
                        $buynum += Db::name('shop_order_goods')->where('aid',$aid)->where('mid',$mid)->where('proid',$up_proid)->where('status','in','1,2,3')->sum('num');
                        if($buynum >= $pronum){
                            $isup = true;
//                        break;
                        }
                    }
                }
                if ($isup && $lv['up_fxdowncount2'] > 0) {
                    $downmids = \app\commons\Member::getdownmids($aid, $mid, $lv['up_fxdownlevelnum2'], $lv['up_fxdownlevelid2']);
                    $downmidcount1 = count($downmids);
                    if ($downmidcount1 >= intval($lv['up_fxdowncount2'])) {
                        $isup = true;
                    } else {
                        $isup = false;
                    }
                }
            }
            if ($isup) {
                $newlv = $lv;
            }
        }
        if ($newlv && $newlv['id'] != $member['storelevel']) {
            Db::name('member')->where('aid', $aid)->where('id', $member['id'])->update(['storelevel' => $newlv['id']]);
        }
    }
    
    
    
    
    
    
}