<?php


namespace app\commons;
use think\facade\Db;
use think\facade\Log;


class Member
{
    
    //  public static function addcredit1($aid,$mid,$credit1,$remark,$credit1_money=0){
    //     if($credit1==0) return ;
    //     $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->lock(true)->find();
    //     if(!$member) return ['status'=>0,'msg'=>t('会员').'不存在'];
        
    //     $member['credit1']  = 0 + Db::name('member_credit1log')->where('mid',$mid)->sum('credit1_after');
    //     if($credit1 < 0 && $member['credit1'] < $credit1*-1)  return ['status'=>0,'msg'=>t('credit1').'不足'];
    //     $after = $member['credit1'] + $credit1;
    //     Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['credit1'=>$after]);
    //     $data = [];
    //     $data['aid'] = $aid;
    //     $data['mid'] = $mid;
    //     $data['credit1'] = $credit1;
    //     $data['after'] = $after;
    //     $data['createtime'] = time();
    //     $data['remark'] = $remark;
      
    //     if ($credit1 > 0) {
    //         $data['credit1_money'] = $credit1_money;
    //         $data['credit1_after'] = $credit1;
    //     }
    //     Db::name('member_credit1log')->insert($data);
        
    //     if ($credit1 < 0) {
    //         $credit1_after = $credit1*-1;
    //         $memberArray = Db::name('member_credit1log')->where('aid',$aid)->where('mid',$mid)->where('credit1_after','>',0)->select()->toArray();
    //         $update_array = [];
    //         foreach($memberArray as $k3=> $log) {
    //             if ($credit1_after <= 0) {
    //                 break;
    //             }
    //             $credit1money = $credit1_money-$log['credit1_money'];
    //             $update = [];
    // 			$update['id'] = $log['id'];
    //             if ($log['credit1_after'] >= $credit1_after) {
    // 				$update['credit1_after'] =  $log['credit1_after']-$credit1_after;
    // 				if ($credit1money>0) {
    // 				    $update['totalmoney'] =  round($credit1_after*$credit1money,6);  //当日收益
    // 				}
    // 		     	$credit1_after = 0;
    //             }else {
    //                 $update['credit1_after'] = 0;
    //                 if ($credit1money>0) {
    // 				    $update['totalmoney'] =  round($log['credit1_after']*$credit1money,6);  //当日收益
    // 				}
    //                 $credit1_after -= $log['credit1_after'];
    //             }
    //         	$update_array[] = $update;
    //         }
    //         if(!empty($update_array)) {
    // 			$model = new \app\models\MemberCredit1log();
    // 			$model->saveAll($update_array);
    // 		}
    //     }
    //     return ['status'=>1,'msg'=>''];
    // }
    
    
    
    
    
    
    
    
     public static function addadmincredit1($aid,$mid,$credit1,$remark){
        if($credit1==0) return ;
        $admin = Db::name('admin_set')->where('aid',$aid)->lock(true)->find();
        if ($admin['after_credit1'] <= $admin['only_credit1']) {
            return ['status'=>0,'msg'=>t('credit1').'已保留'];
        }
        if ($credit1>$admin['after_credit1']) {
            $credit1 = $admin['after_credit1'];
        }
        if($credit1 < 0 && $admin['after_credit1'] < $credit1) return ['status'=>0,'msg'=>t('credit1').'不足'];
        
        $after = $admin['after_credit1'] + $credit1;
        Db::name('admin_set')->where('aid',$aid)->update(['after_credit1'=>$after]);
    
        $data = [];
        $data['aid'] = $aid;
        $data['mid'] = $mid;
        $data['credit1'] = $credit1;
        $data['after'] = $after;
        $data['createtime'] = time();
        $data['remark'] = $remark;
        Db::name('admin_credit1log')->insert($data);
        return ['status'=>1,'msg'=>''];
    }
    //升级
    public static function uplv($aid,$mid,$type='shop',$params = []){
        $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->find();
        if(!$member['id']) return;
        if(getcustom('member_level_down_commission',$aid)){
            if($member['isauto_down']==1){
                return;
            }
        }
        if($member['path']) {
            //处理path异常问题
            $patharr = explode(',', $member['path']);
            $patharr = array_filter($patharr);
            $patharr = array_unique($patharr);
            $newpath = implode(',', $patharr);
            if($newpath != $member['path']) {
                Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['path'=>$newpath]);
            }
            $member['path'] = $newpath;
        }
        self::douplv($aid,$member,$type,$params);
        if(getcustom('ciruikang_fenxiao',$aid)){
            if($params && $params['onebuy']){
                //如果是一次性购买，则上级没有此升级条件
                $params['onebuy_orderid'] = 0;
            }
        }
        //他的上级
        if($member['path']){
            $parentList = Db::name('member')->where('aid',$aid)->where('id','in',$member['path'])->order(Db::raw('field(id,'.$member['path'].')'))->select()->toArray();
            if($parentList){
                $parentList = array_reverse($parentList);
                foreach($parentList as $parent){
                    self::douplv($aid,$parent,$type,$params);
                }
            }
        }
    }
    public static function douplv($aid,$member,$type='shop',$params = []){
        $mid = $member['id'];
        //查询会有最后一次降级时间
        $down_level_time = Db::name('member_levelup_order')->where('mid',$mid)->where('type',1)->order('createtime desc')->value('createtime');
        $down_level_time = $down_level_time?:0;
        $wxpaymoney = 0 + Db::name('wxpay_log')->where('aid',$aid)->where('mid',$mid)->where('createtime','>',$down_level_time)->sum('total_fee');
        if($type=='cashier'){
            $ordermoney = 0 + Db::name('cashier_order')->where('aid',$aid)->where('mid',$mid)->where('status',1)->where('paytime','>',$down_level_time)->sum('totalprice');
        }else{
            $ordermoney = 0 + Db::name('shop_order')->where('aid',$aid)->where('mid',$mid)->where('status','in','1,2,3')->where('paytime','>',$down_level_time)->sum('totalprice');
        }
        $rechargemoney = 0 + Db::name('recharge_order')->where('aid',$aid)->where('mid',$mid)->where('status',1)->where('createtime','>',$down_level_time)->sum('money');
        if(getcustom('member_level_down_commission',$aid)){
            if($member['isauto_down']==1){
                return;
            }
        }
        self::upLevel($aid, $member, $member, $ordermoney, $wxpaymoney, $rechargemoney,0,$down_level_time,$params);

        //其他分组等级
        if(getcustom('plug_sanyang',$aid)) {
            $categoryList = Db::name('member_level_category')->where('aid',$aid)->where('isdefault', 0)->where('status', 1)->select()->toArray();
            if($categoryList) {
                foreach ($categoryList as $cat) {
                    $level_records = Db::name('member_level_record')->where('aid',$aid)->where('mid',$mid)->where('cid', $cat['id'])->find();
                    $level_records = $level_records ? $level_records : [];//无其他分组等级
                    self::upLevel($aid, $member, $level_records, $ordermoney, $wxpaymoney, $rechargemoney, $cat['id']);
                }
            }
        }
    }

    /**
     * @param $aid
     * @param $member
     * @param $levelInfo 等级信息 levelid,levelstarttime
     * @param $ordermoney
     * @param $wxpaymoney
     * @param $rechargemoney
     * @param $cid 其他分组等级为空时使用此字段
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function upLevel($aid, $member, $levelInfo, $ordermoney, $wxpaymoney, $rechargemoney, $cid = 0,$down_level_time=0,$params=[])
    {
        $sendSgReward = true;
        $mid = $member['id'];
        $nowlv = ['sort' => -1];
        if($levelInfo['levelid'])
            $nowlv = Db::name('member_level')->where('aid',$aid)->where('id',$levelInfo['levelid'])->find();
        $cid = $cid ? $cid : $nowlv['cid'];
        //等级列表
        $lvlist = Db::name('member_level')->where('aid',$aid)->where('cid', $cid)->where('can_up',1)->where('sort','>',$nowlv['sort'])->order('sort,id')->select();
        $newlv = $nowlv;
        foreach($lvlist as $lv){

            if(getcustom('levelup_from_levelid',$aid)) {
                //升级前置等级条件
                $gettj = explode(',',$lv['gettj']);
                if ($lv['gettj'] && !in_array('-1', $gettj) && !in_array($nowlv['id'], $gettj)) {
                    continue;
                }
            }
            $condition_or = false;
            if($lv['maxnum'] > 0){
                $lvmembercount = Db::name('member')->where('aid',$aid)->where('levelid',$lv['id'])->count();
                if($lvmembercount >= $lv['maxnum']) continue;
            }

            $tjor = false; //or条件 有一个满足就变成true 有and条件不满足直接continue跳过
            $hasor = false; //是否有or条件
            $hasand = false; //是否有and条件

            if($lv['up_wxpaymoney'] > 0){
                $hasor = true;
                if($wxpaymoney >= $lv['up_wxpaymoney']) $tjor = true;
            }
            if(!$tjor && $lv['up_ordermoney'] > 0){
                $hasor = true;
                if($ordermoney >= $lv['up_ordermoney']) $tjor = true;
            }
            if(!$tjor && $lv['up_rechargemoney'] > 0){
                $hasor = true;
                if($rechargemoney >= $lv['up_rechargemoney']) $tjor = true;
            }
            if(!$tjor && $lv['up_getmembercard']==1){
                $hasor = true;
                if($member['card_code']) $tjor = true;
            }

            if(!$tjor && getcustom('levelup_perpaymoney',$aid) && $lv['up_perpaymoney']>0){
                $hasor = true;
                $max_wxpaymoney = 0 + Db::name('wxpay_log')->where('aid',$aid)->where('mid',$mid)->where('createtime','>',$down_level_time)->max('total_fee');
                if($max_wxpaymoney >= $lv['up_perpaymoney']){
                    $tjor = true;
                }else{
                    $max_fxordermoney = Db::name('shop_order')->where('aid',$aid)->where('status','in','1,2,3')->where('mid',$mid)->where('createtime','>',$down_level_time)->max('totalprice');
                    if($max_fxordermoney >= $lv['up_perpaymoney']){
                        $tjor = true;
                    }
                }
            }


            //hasor true tjor false
            if (getcustom('member_levelup_orderprice',$aid) && ((!$tjor && $lv['up_orderprice_condition'] == 'or') || $lv['up_orderprice_condition'] == 'and') && $lv['up_orderprice'] > 0) {
                if($lv['up_orderprice_condition'] == "or")$hasor = true;
                $ismeet = false;
                $max_orderprice = Db::name('shop_order')->where('aid', $aid)->where('status', 'in', '1,2,3')->where('mid', $mid)->where('createtime', '>', $down_level_time)->max('totalprice');
                if ($max_orderprice >= $lv['up_orderprice']) {
                    $ismeet = true;
                }
                if($lv['up_orderprice_condition'] == 'or'){
                    if($ismeet) $tjor = true;
                }else{
                    if(!$ismeet) continue;
                    $hasand = true;
                }
            }


            if(((!$tjor && $lv['up_fxorder_condition'] == 'or') || $lv['up_fxorder_condition'] == 'and') && $lv['up_fxordermoney'] > 0){
                if($lv['up_fxorder_condition'] == 'or') $hasor = true;
                $ismeet = false;
                if($lv['up_fxordermoney_removemax'] ==1){
                    $downmids = self::getdownmids_removemax($aid,$mid,$lv['up_fxorderlevelnum'],$lv['up_fxorderlevelid']);
                }else{
                    $downmids = self::getdownmids($aid,$mid,$lv['up_fxorderlevelnum'],$lv['up_fxorderlevelid'],0,1,$down_level_time);
                }
                if($downmids){
                    $fxordermoney = 0 + Db::name('shop_order_goods')->where('status','in','1,2,3')->where('mid','in',$downmids)->where('createtime','>',$down_level_time)->sum('totalprice');

                    // 餐饮订单计入团队业绩，参与升级条件统计
                    if(getcustom('restaurant_team_yeji')){
                        // 外卖
                        $rtakeaway_fxordermoney = Db::name('restaurant_takeaway_order_goods')->where('status','in','1,2,3,12')->where('mid','in',$downmids)->where('createtime','>',$down_level_time)->sum('totalprice');
                        $fxordermoney += $rtakeaway_fxordermoney;
                        // 店内点餐
                        $rshop_fxordermoney = Db::name('restaurant_shop_order_goods')->where('status','in','1,2,3')->where('mid','in',$downmids)->where('createtime','>',$down_level_time)->sum('totalprice');
                        $fxordermoney += $rshop_fxordermoney;

                    }
                    if($fxordermoney >= $lv['up_fxordermoney']){
                        $ismeet = true;
                    }
                }
                if($lv['up_fxorder_condition'] == 'or'){
                    if($ismeet) $tjor = true;
                }else{
                    if(!$ismeet) continue;
                    $hasand = true;
                }
            }

            if(!$tjor && $lv['up_fxordermoney_xiao'] > 0){
                $hasor = true;
                $downmids = self::getdownmids_xiao($aid,$mid,$lv['up_fxorderlevelnum_xiao'],$lv['up_fxorderlevelid_xiao']);
                if($downmids){
                    $fxordermoney = 0 + Db::name('shop_order_goods')->where('status','in','1,2,3')->where('mid','in',$downmids)->where('createtime','>',$down_level_time)->sum('totalprice');
                    if($fxordermoney >= $lv['up_fxordermoney_xiao']){
                       $tjor = true;
                    }
                }
            }
            if(!$tjor && ($lv['up_fxdowncount']>0 || $lv['up_fxdowncount2']>0 || $lv['up_fxdowncount3']>0)){
                $hasor = true;
                $downmidcount1 = 0;
                $downmidcount2 = 0;
                $downmidcount3 = 0;
                $up_fxdowncount = intval($lv['up_fxdowncount']);
                $up_fxdowncount2 = intval($lv['up_fxdowncount2']);
                $up_fxdowncount3 = intval($lv['up_fxdowncount3']);
                if($lv['up_fxdowncount'] > 0){
                    $downmids = self::getdownmids($aid,$mid,$lv['up_fxdownlevelnum'],$lv['up_fxdownlevelid'],$lv['up_with_origin'],$lv['up_with_new'],$down_level_time);
                    $downmidcount1 = count($downmids);
                }
                if($lv['up_fxdowncount2'] > 0 && $lv['up_down_down']==1){
                    $downuser = Db::name('member')->where('aid',$aid)->where('id','in',$downmids)->select()->toArray();
                    $downmids2 = [];
                    foreach($downuser as $duser){
            			$downmids = self::getdownmids($aid,$duser['id'],$lv['up_fxdownlevelnum2'],$lv['up_fxdownlevelid2'],$lv['up_with_origin'],$lv['up_with_new'],$down_level_time);
            			if (in_array($duser['levelid'], explode(',',$lv['up_fxdownlevelid2']))) {
                            $downmids[] = $duser['id'];
                        }
            			if (count($downmids) >= 1) {
            			    $downmids2[] = $duser['id'];
            			}
            		}
                    $downmidcount2 = count($downmids2);
                }elseif ($lv['up_fxdowncount2'] > 0 ) {
                    $downmids2 = self::getdownmids($aid,$mid,$lv['up_fxdownlevelnum2'],$lv['up_fxdownlevelid2'],$lv['up_with_origin'],$lv['up_with_new'],$down_level_time);
                    $downmidcount2 = count($downmids2);
                }
                if($lv['up_fxdowncount3'] > 0){
                    $downmids3 = self::getdownmids($aid,$mid,$lv['up_fxdownlevelnum3'],$lv['up_fxdownlevelid3'],$lv['up_with_origin'],$lv['up_with_new'],$down_level_time);
                    $downmidcount3 = count($downmids3);
                }
                if($downmidcount1 >= $up_fxdowncount && $downmidcount2 >= $up_fxdowncount2 && $downmidcount3 >= $up_fxdowncount3){
                    $tjor = true;
                }
                
            }

            if(getcustom('up_downbuyprocount',$aid) && !$tjor && $lv['up_downbuypronum']>0){
                $hasor = true;
                $downmids = self::getdownmids($aid,$mid,$lv['up_downbuyprolvnum']);
                $downbuypronum = 0;
                if($downmids){
                    if($lv['up_downbuyproid']){
                        $downbuypronum = Db::name('shop_order_goods')->where('aid',$aid)->where('status','in','1,2,3')->where('mid','in',$downmids)->where('proid','in',$lv['up_downbuyproid'])->where('createtime','>',$down_level_time)->sum('num');
                    }else{
                        $downbuypronum = Db::name('shop_order_goods')->where('aid',$aid)->where('status','in','1,2,3')->where('mid','in',$downmids)->where('createtime','>',$down_level_time)->sum('num');
                    }
                    if($downbuypronum >= $lv['up_downbuypronum']){
                        $tjor = true;
                    }
                }
            }
            if(getcustom('up_cat_ordermoney',$aid)){
                if(!$tjor && ($lv['up_cat_ordermoney']>0 && $lv['up_catid']!='')){
                    $hasor = true;
                    //购买分类商品,单笔订单金额满
                    //查询最后一笔订单
                    $up_catids = explode(',',str_replace('，',',',$lv['up_catid']));
                    $last_order = Db::name('shop_order')->field('id,aid,bid,mid,ordernum,totalprice,status')->where('aid',$aid)->where('mid',$mid)->where('status','in','1,2,3')->order('id desc')->find();
                    if($last_order['totalprice'] >= $lv['up_cat_ordermoney']){
                        $oglist = Db::name('shop_order_goods')->field('id,aid,bid,mid,orderid,proid,cid,real_totalprice,status')->where('aid',$aid)->where('mid',$mid)->where('status','in','1,2,3')->where('orderid',$last_order['id'])->where('createtime','>',$down_level_time)->select()->toArray();
                        if($oglist){
                            $allcids=[];
                            $up_cat_total=0;
                            $cids = Db::name('shop_category')->where('id','in',$lv['up_catid'])->where('aid',$aid)->column('id');
                            if($cids){
                                $cids2 = Db::name('shop_category')->where('aid',$aid)->where('pid','in',$cids)->column('id');
                                if($cids2){
                                    $cids3 = Db::name('shop_category')->where('aid',$aid)->where('pid','in',$cids2)->column('id');
                                    if($cids3)
                                        $allcids = array_merge($cids,$cids2,$cids3);
                                    else
                                        $allcids = array_merge($cids,$cids2);
                                }else{
                                    $allcids = $cids;
                                }
                                foreach ($oglist as $og){
                                    $ogcid = explode(',',$og['cid']);
                                    if(empty($ogcid)) continue;
                                    if(array_intersect($allcids,$ogcid)){
                                        $up_cat_total += $og['real_totalprice'];
                                    }
                                }
                                if($up_cat_total >= $lv['up_cat_ordermoney']){
                                    $tjor = true;
                                }
                            }
                        }
                    }
                }
            }

            if(getcustom('ciruikang_fenxiao',$aid)){
                //注册满多少天内
                if(!$tjor && $lv['up_regtime_and']>0){
                    $up_regtime_and = $lv['up_regtime_and']*86400;
                    //查询此会员注册时间
                    $regtime = time()-$member['createtime'];
                    //如果超出，则不能升级此等级
                    if($regtime>$up_regtime_and){
                        continue;
                    }
                    $hasand = true;
                }
                //一次性购买升级(从未一次性升级过)
                if(!$member['crk_up_levelid'] && !empty($lv['up_proid2']) && !empty($lv['up_pronum2']) && $lv['up_pronum2']>0){
                    if($params && $params['onebuy'] && $params['onebuy_orderid']){
                        $hasor = true;
                        $deal_onebuy = \app\customs\CiruikangCustom::deal_onebuy($aid,$mid,$member,$lv,$params,$down_level_time,$tjor);
                        $lv   = $deal_onebuy['lv'];
                        $tjor = $deal_onebuy['tjor'];
                        if($deal_onebuy && $tjor && $lv['is_onebuy']){
                            $sendSgReward = false;
                        }
                    }
                }
            }

            //购买指定商品
            if(((!$tjor && $lv['up_buygoods_condition'] == 'or') || $lv['up_buygoods_condition'] == 'and') && ($lv['up_proid']!='0' && $lv['up_proid']!='')){
                if($lv['up_buygoods_condition'] == 'or') $hasor = true;
                $ismeet = false;
                $up_proids = explode(',',str_replace('，',',',$lv['up_proid']));
                $up_pronums = explode(',',str_replace('，',',',$lv['up_pronum']));

                $where = [];
                $up_pro_orderrange = 0;//统计订单范围 0:仅自己订单 1:自己及下级订单
                if(getcustom('ciruikang_fenxiao',$aid)){
                    $up_pro_orderrange = $lv['up_pro_orderrange'];
                }
                if(!$up_pro_orderrange){
                    $where[] = ['mid','=',$mid];
                }else{
                    $mids =[$mid];
                    $mids2 = self::getdownmids($aid,$mid);
                    if($mids2){
                        $mids = array_merge($mids,$mids2);
                    }
                    $where[] = ['mid','in',$mids];
                }

                $up_pro_orderstatus = 0;//统计订单状态 0:付款后所有订单 1:仅确认收货订单
                if(getcustom('ciruikang_fenxiao',$aid)){
                    $up_pro_orderstatus = $lv['up_pro_orderstatus'];
                }
                if(!$up_pro_orderstatus){
                    $where[] = ['status','in','1,2,3'];
                }else{
                    $where[] = ['status','=',3];
                }

                $where[] = ['aid','=',$aid];
                $where[] = ['createtime','>',$down_level_time];

                if(count($up_pronums) > 1) {
                    foreach($up_proids as $k=>$up_proid){
                        $pronum = $up_pronums[$k];
                        if(!$pronum) $pronum = 1;
                        $buynum = Db::name('shop_order_goods')->where('proid',$up_proid)->where($where)->sum('num');
                        if($buynum >= $pronum){
                            $ismeet = true;
                        }
                    }
                } else {
                    $pronum = $up_pronums[0];
                    if(!$pronum) $pronum = 1;
                    $buynum = 0;
                    foreach($up_proids as $k=>$up_proid){
                        $buynum += Db::name('shop_order_goods')->where('proid',$up_proid)->where($where)->sum('num');
                        if($buynum >= $pronum){
                            $ismeet = true;
                        }
                    }
                }

                if(getcustom('ciruikang_fenxiao',$aid)){
                    //是否有小市场业绩限制
                    if($lv['up_small_market_num']>0){
                        $ismeet = \app\customs\CiruikangCustom::deal_small_market($aid,$mid,$lv,$ismeet);
                    }
                    //是否有当前最低等级限制
                    if(!empty($lv['up_pro_minprelevelid'])){
                        //查询等级序号
                        $presort = Db::name('member_level')->where('id',$lv['up_pro_minprelevelid'])->value('sort');
                        $presort = $presort??0;
                        if($presort){
                            if($nowlv['sort']<$presort){
                                $ismeet = false;
                            }
                        }
                    }
                }

                if($lv['up_buygoods_condition'] == 'or'){
                    if($ismeet) $tjor = true;
                }else{
                    if(!$ismeet) continue;
                    $hasand = true;
                }
                
            }

            if(getcustom('member_levelup_businessnum',$aid)){
                if(!$tjor && $lv['up_businessnum']>0){
                    $hasor = true;
                    //查询他推荐的商户数量
                    $businessnum = 0+Db::name('business')
                        ->alias('b')
                        ->join('member m','m.id = b.mid')
                        ->where('b.aid',$aid)
                        ->where('b.status',1)
                        ->where('m.pid',$mid)
                        ->count();
                    if($businessnum >= $lv['up_businessnum']){
                        $tjor = true;
                    }
                }
            }

            if(((!$tjor && getcustom('up_fxdowncount_and_isor',$aid)) || !getcustom('up_fxdowncount_and_isor',$aid)) && ($lv['up_fxdowncount_and']>0 || $lv['up_fxdowncount2_and']>0)){
                if(getcustom('up_fxdowncount_and_isor',$aid)) $hasor = true;
                $ismeet = false;
                $downmidcount1 = 0;
                $downmidcount2 = 0;
                $up_fxdowncount = intval($lv['up_fxdowncount_and']);
                $up_fxdowncount2 = intval($lv['up_fxdowncount2_and']);
                if($lv['up_fxdowncount_and'] > 0){
                    $downmids = self::getdownmids($aid,$mid,$lv['up_fxdownlevelnum_and'],$lv['up_fxdownlevelid_and'],$lv['up_with_origin'],$lv['up_with_new'],$down_level_time);
                    $downmidcount1 = count($downmids);
                }
                if($lv['up_fxdowncount2_and'] > 0){
                    $downmids2 = self::getdownmids($aid,$mid,$lv['up_fxdownlevelnum2_and'],$lv['up_fxdownlevelid2_and'],$lv['up_with_origin'],$lv['up_with_new'],$down_level_time);
                    $downmidcount2 = count($downmids2);
                }
                if($downmidcount1 >= $up_fxdowncount && $downmidcount2 >= $up_fxdowncount2){
                    $ismeet = true;
                }
                if(getcustom('up_fxdowncount_and_isor',$aid)){
                    if($ismeet) $tjor = true;
                }else{
                    if(!$ismeet) continue;
                    $hasand = true;
                }
            }

            if($hasor && !$tjor){
                $isup = false;
            }elseif(!$hasor && !$tjor && !$hasand){
                $isup = false;
            }else{
                $isup = true;
            }

            if(getcustom('up_fxorder_condition_new',$aid)){
                /********************************新增升级条件start 20231104*******************************************/
                //四组下级人数任意搭配
                if($lv['up_fxdowncount_new']>0 || $lv['up_fxdowncount2_new']>0 || $lv['up_fxdowncount3_new']>0 || $lv['up_fxdowncount4_new']>0){
                    $downmidcount1 = 0;
                    $downmidcount2 = 0;
                    $downmidcount3 = 0;
                    $downmidcount4 = 0;
                    $up_fxdowncount = intval($lv['up_fxdowncount_new']);
                    $up_fxdowncount2 = intval($lv['up_fxdowncount2_new']);
                    $up_fxdowncount3 = intval($lv['up_fxdowncount3_new']);
                    $up_fxdowncount4 = intval($lv['up_fxdowncount4_new']);
                    if($lv['up_fxdowncount_new'] > 0){
                        $downmids = self::getdownmids($aid,$mid,$lv['up_fxdownlevelnum_new'],$lv['up_fxdownlevelid_new'],$lv['up_with_origin'],$lv['up_with_new'],$down_level_time);
                        $downmidcount1 = count($downmids);
                    }
                    if($lv['up_fxdowncount2_new'] > 0){
                        $downmids2 = self::getdownmids($aid,$mid,$lv['up_fxdownlevelnum2_new'],$lv['up_fxdownlevelid2_new'],$lv['up_with_origin'],$lv['up_with_new'],$down_level_time);
                        $downmidcount2 = count($downmids2);
                    }
                    if($lv['up_fxdowncount3_new'] > 0){
                        $downmids3 = self::getdownmids($aid,$mid,$lv['up_fxdownlevelnum3_new'],$lv['up_fxdownlevelid3_new'],$lv['up_with_origin'],$lv['up_with_new'],$down_level_time);
                        $downmidcount3 = count($downmids3);
                    }
                    if($lv['up_fxdowncount4_new'] > 0){
                        $downmids4 = self::getdownmids($aid,$mid,$lv['up_fxdownlevelnum4_new'],$lv['up_fxdownlevelid4_new'],$lv['up_with_origin'],$lv['up_with_new'],$down_level_time);
                        $downmidcount4 = count($downmids4);
                    }
                    $logic_str = $lv['up_fxorder_condition_new']=='and'?'&&':'||';
                    $logic_str2 = $lv['up_fxorder_condition2_new']=='and'?'&&':'||';
                    $logic_str3 = $lv['up_fxorder_condition3_new']=='and'?'&&':'||';
                    $logic_str4 = $lv['up_fxorder_condition4_new']=='and'?'&&':'||';
                    //依次根据上一个条件的逻辑判断结果进行下一个条件的判断
                    $isup_int = $isup==true?1:0;
                    if($up_fxdowncount>0){
                        $isup = eval("return ".$isup_int." ".$logic_str." ".$downmidcount1.">=".$up_fxdowncount.";");
                        //dump($isup_int." ".$logic_str." ".$downmidcount1.">=".$up_fxdowncount);
                        //dump('逻辑1判断',$isup);
                        $isup_int = $isup==true?1:0;
                    }
                    if($up_fxdowncount2>0){
                        $isup = eval("return ".$isup_int." ".$logic_str2." ".$downmidcount2.">=".$up_fxdowncount2.";");
                        //dump('逻辑2判断',$isup);
                        $isup_int = $isup==true?1:0;
                    }
                    if($up_fxdowncount3>0){
                        $isup = eval("return ".$isup_int." ".$logic_str3." ".$downmidcount3.">=".$up_fxdowncount3.";");
                        //dump('逻辑3判断',$isup);
                        $isup_int = $isup==true?1:0;
                    }
                    if($up_fxdowncount4>0){
                        $isup = eval("return ".$isup_int." ".$logic_str4." ".$downmidcount4.">=".$up_fxdowncount4.";");
                        //dump('逻辑4判断',$isup);
                    }

                }
                /********************************新增升级条件end 20231104*******************************************/
            }
            if(getcustom('up_level_teamorder',$aid)){
                //*****************************根据团队订单升级start 20231106*********************************************
                if($lv['up_teamorder_num']>0 || $lv['up_teamorder_small_num']>0){
                    $logic_str = $lv['up_teamorder_condition']=='and'?'&&':'||';
                    $logic_str2 = $lv['up_teamorder_small_condition']=='and'?'&&':'||';
                    //查询团队订单数量
                    $order_count = self::getTeamOrderNum($aid,$mid,$lv['up_teamorder_lv'],$lv['up_teamorder_levelid']);
                    $teamorder_count = $order_count['teamorder_count']??0;
                    //查询小区团队订单数量
                    $teamorder_small_count = $order_count['teamorder_small_count']??0;
                    //dump('团队订单数量'.$teamorder_count.'小区团队订单数量'.$teamorder_small_count);
                    //判断团队订单逻辑是否符合
                    $isup_int = $isup==true?1:0;
                    //dump('前面所有条件的判断'.$isup_int);
                    $isup = eval("return ".$isup_int." ".$logic_str." ".$teamorder_count.">=".$lv['up_teamorder_num'].";");
                    //dump('团队订单判断'.$isup_int);
                    //判断团队小区订单逻辑是否符合
                    $isup_int = $isup==true?1:0;
                    $isup = eval("return ".$isup_int." ".$logic_str2." ".$teamorder_small_count.">=".$lv['up_teamorder_small_num'].";");
                    //dump('小区团队订单判断'.$isup_int);
                }
                //*****************************根据团队订单升级end 20231106*********************************************
            }
            if(getcustom('levelup_teamnum_peoplenum',$aid)){
                if($lv['up_team_path_num'] >0){
                    $logic_fh = $lv['up_team_path_condition'] =='and'?'&&':'||';
                    //团队几条线
                    //有x条(y人拥有n等级)的线 ，有多少条满足条件的线
                   $teammids =\app\commons\Member::getdownmids($aid,$mid,1,0);
                    $team_path_count= 0;//X
                    foreach($teammids as $pk=>$pv){
                        $thismid = [$pv];
                        $thisdownmids = \app\commons\Member::getdownmids($aid,$pv);
                        if($thisdownmids){
                            $thisdownmids = array_merge($thismid,$thisdownmids); 
                        }else{
                            $thisdownmids = $thismid;
                        }
                        Log::write([
                            'file' => __FILE__ . __LINE__,
                            '$lv' => $lv,
                            '$pv' => $pv,
                            '$thisdownmids' => $thisdownmids,
                        ]);
                        $thismlist = Db::name('member')->where('aid',$aid)->where('id','in',$thisdownmids)->field('id,levelid')->select()->toArray();
                        Log::write([
                            'file' => __FILE__ . __LINE__,
                            '$thismlist' => $thismlist,
                        ]);
                        if(!$thismlist)continue;
                        $have_lv_num = 0;//Y满足所需等级的人数
                        $up_team_path_level =$lv['up_team_path_level']? explode(',',$lv['up_team_path_level']):[];
                        foreach($thismlist as $mk=>$mv){
                            //设置了等级ID，判断是否在设置的等级中，或者不设置等级
                            Log::write([
                                'file' => __FILE__ . __LINE__,
                                '$up_team_path_level' => $up_team_path_level,
                                'levelid' => $mv['levelid'],
                            ]);
                            if(($up_team_path_level && in_array($mv['levelid'],$up_team_path_level)) || !$up_team_path_level){
                                $have_lv_num++;
                            }
                            Log::write([
                                'file' => __FILE__ . __LINE__,
                                '$have_lv_num' => $have_lv_num,
                            ]);
                        }
                        //满足条件的数量和设置的对比
                        if($have_lv_num >= $lv['up_team_people_num']){
                            $team_path_count++;
                        }
                    }
                    Log::write([
                        'file' => __FILE__ . __LINE__,
                        '$team_path_count' => $team_path_count,
                        'up_team_people_num' => $lv['up_team_people_num'],
                    ]);
                    $isup_int = $isup==true?1:0;
                    $isup = eval("return ".$isup_int." ".$logic_fh." ".$team_path_count.">=".$lv['up_team_path_num'].";");
                }
            }
            if(getcustom('member_up_binding_tel',$aid)){
                if($lv['up_binding_tel'] ==1){
                    $logic_btel = $lv['up_binding_tel_condition'] =='and'?'&&':'||';
                    $isup_int = $isup==true?1:0;
                    $is_tel = 0;
                    if($member['tel']) $is_tel = 1;
                    $isup = eval("return ".$isup_int." ".$logic_btel." ".$is_tel.";");
                }
            }
            if($isup) $newlv = $lv;
        }
        //开启升级协议先记录，等前台点击同意协议再进行升级
        if(getcustom('up_level_agree',$aid) && !empty($newlv['is_agree'])){
            if($newlv && $newlv['id'] != $levelInfo['levelid']) {
                $update = [];
                $update['aid'] = $aid;
                $update['mid'] = $mid;
                $update['newlv_id'] = $newlv['id'];
                $update['sort'] = $newlv['sort'];
                $update['cid'] = $cid;
                $update['w_time'] = time();
                Db::name('member_level_agree')->insert($update);
            }
        }else{
            self::handleUpLevel($aid,$mid,$newlv,$levelInfo,$member,$cid,$params);
        }

        if(!$isup && $newlv['id'] == $nowlv['id']){
            if(getcustom('levelup_pro_extend_time',$aid) && $nowlv['up_pro_extend_time'] == 1 && $nowlv['up_pro_keep_time'] == 0){
                //不升级，判断是否延期，通过member_levelup_order的时间对比订单时间
                //注意两种方式不可同时开启
                if($nowlv['yxqdate'] > 0){
                    $isextend = false;
                    $last_levelup_order = Db::name('member_levelup_order')->where('aid',$aid)->where('mid',$mid)->where('levelid',$nowlv['id'])->order('createtime','desc')->find();
                    $have_orderids = Db::name('shop_order')->where('aid',$aid)->where('mid',$mid)->where('createtime','>',$last_levelup_order['createtime'])->whereIn('status',[1,2,3])->column('id');
                    if($have_orderids){
                        $up_proids = explode(',',str_replace('，',',',$nowlv['up_proid']));
                        $up_pronums = explode(',',str_replace('，',',',$nowlv['up_pronum']));
                        if(count($up_pronums) > 1) {
                            foreach($up_proids as $k=>$up_proid){
                                $pronum = $up_pronums[$k];
                                if(!$pronum) $pronum = 1;
                                $buynum = Db::name('shop_order_goods')->where('aid',$aid)->where('mid',$mid)->where('proid',$up_proid)->whereIn('orderid',$have_orderids)->where('status','in','1,2,3')->sum('num');
                                if($buynum >= $pronum){
                                    $isextend = true;
                                }
                            }
                        } else {
                            $pronum = $up_pronums[0];
                            if(!$pronum) $pronum = 1;
                            $buynum = 0;
                            foreach($up_proids as $k=>$up_proid){
                                $buynum += Db::name('shop_order_goods')->where('aid',$aid)->where('mid',$mid)->where('proid',$up_proid)->whereIn('orderid',$have_orderids)->where('status','in','1,2,3')->sum('num');
                                if($buynum >= $pronum){
                                    $isextend = true;
                                }
                            }
                        }
                        //满足条件，累加延长等级时间
                        if($isextend){
                            $levelendtime = $member['levelendtime'] + 86400 * $nowlv['yxqdate'];
                            //todo 判断是否默认分组
                            Db::name('member')->where('aid', $aid)->where('id', $mid)->update(['levelendtime' => $levelendtime]);
                            $order = [
                                'aid' => $aid,
                                'mid' => $mid,
                                'from_mid' => $mid,
                                'pid' => $member['pid'],
                                'levelid' => $nowlv['id'],
                                'title' => '复购延期',
                                'totalprice' => 0,
                                'createtime' => time(),
                                'levelup_time' => time(),
                                'beforelevelid' => $nowlv['id'],
                                'form0' => '类型^_^购买商品延长等级',
                                'platform' => platform,
                                'status' => 2
                            ];
                            Db::name('member_levelup_order')->insert($order);
                        }
                    }
                }
            }
            if(getcustom('levelup_pro_keep_time',$aid) && $nowlv['up_pro_keep_time'] == 1 && $nowlv['up_pro_extend_time'] == 0){
                //不升级，判断是否保持有效期（购买时间+有效期天数=过期时间），通过member_levelup_order的时间对比订单时间
                //注意两种方式不可同时开启
                if($nowlv['yxqdate'] > 0){
                    $isextend = false;
                    $last_levelup_order = Db::name('member_levelup_order')->where('aid',$aid)->where('mid',$mid)->where('levelid',$nowlv['id'])->order('createtime','desc')->find();
                    $have_orderids = Db::name('shop_order')->where('aid',$aid)->where('mid',$mid)->where('createtime','>',$last_levelup_order['createtime'])->whereIn('status',[1,2,3])->column('id');
                    if($have_orderids){
                        $up_proids = explode(',',str_replace('，',',',$nowlv['up_proid']));
                        $up_pronums = explode(',',str_replace('，',',',$nowlv['up_pronum']));
                        if(count($up_pronums) > 1) {
                            foreach($up_proids as $k=>$up_proid){
                                $pronum = $up_pronums[$k];
                                if(!$pronum) $pronum = 1;
                                $buynum = Db::name('shop_order_goods')->where('aid',$aid)->where('mid',$mid)->where('proid',$up_proid)->whereIn('orderid',$have_orderids)->where('status','in','1,2,3')->sum('num');
                                if($buynum >= $pronum){
                                    $isextend = true;
                                }
                            }
                        } else {
                            $pronum = $up_pronums[0];
                            if(!$pronum) $pronum = 1;
                            $buynum = 0;
                            foreach($up_proids as $k=>$up_proid){
                                $buynum += Db::name('shop_order_goods')->where('aid',$aid)->where('mid',$mid)->where('proid',$up_proid)->whereIn('orderid',$have_orderids)->where('status','in','1,2,3')->sum('num');
                                if($buynum >= $pronum){
                                    $isextend = true;
                                }
                            }
                        }
                        //满足条件，保持等级时间
                        if($isextend){
                            $levelendtime = time() + 86400 * $nowlv['yxqdate'];
                            //todo 判断是否默认分组
                            Db::name('member')->where('aid', $aid)->where('id', $mid)->update(['levelendtime' => $levelendtime]);
                            $order = [
                                'aid' => $aid,
                                'mid' => $mid,
                                'from_mid' => $mid,
                                'pid' => $member['pid'],
                                'levelid' => $nowlv['id'],
                                'title' => '复购延期',
                                'totalprice' => 0,
                                'createtime' => time(),
                                'levelup_time' => time(),
                                'beforelevelid' => $nowlv['id'],
                                'form0' => '类型^_^购买商品延长等级',
                                'platform' => platform,
                                'status' => 2
                            ];
                            Db::name('member_levelup_order')->insert($order);
                        }
                    }
                }
            }
        }
        if(getcustom('member_shougou_parentreward_wait',$aid) && $sendSgReward){
            $sendSgRewardList = Db::name('member_commission_record_wait')->where('type','shop')->where('orderid',$params['onebuy_orderid'])->select()->toArray();
            $sgRewardList = [];
            $wsids = [];
            foreach ($sendSgRewardList as $k=>$v){
                $wsids[] = $v['id'];
                unset($v['id']);
                $sgRewardList[] = $v;
            }
            if($sgRewardList){
                Db::name('member_commission_record_wait')->whereIn('id',$wsids)->update(['status'=>1]);
                Db::name('member_commission_record')->insertAll($sgRewardList);
            }
        }
    }
    //处理升级操作
    public static function handleUpLevel($aid,$mid,$newlv,$levelInfo,$member,$cid,$params=[]){
        if($newlv && $newlv['id'] != $levelInfo['levelid']) {
            Log::write([
                'file'=>__FILE__,
                'line'=>__LINE__,
                'handleUpLevel member'=>jsonEncode($member),
                'newlevel'=>jsonEncode($newlv)
            ]);
            if ($newlv['yxqdate'] > 0) {
                $levelendtime = strtotime(date('Y-m-d')) + 86400 + 86400 * $newlv['yxqdate'];
            } else {
                $levelendtime = 0;
            }
            //判断是否默认分组
            if($newlv['cid'] > 0)
                $is_default = Db::name('member_level_category')->where('id', $newlv['cid'])->value('isdefault');
            if ($is_default || $newlv['cid'] == 0) {
                $update = ['levelid' => $newlv['id'], 'levelendtime' => $levelendtime,'levelstarttime' => time()];
               // if(getcustom('coupon_xianxia_buy')){
               //     $update['is_zt_up'] = 0; 
               // }
                if(getcustom('ciruikang_fenxiao',$aid)){
                    if($newlv['is_onebuy'] && $newlv['is_onebuy'] == 1){
                        $update['crk_up_pronum'] = $newlv['up_pronum2'];
                        $update['crk_up_levelid']= $newlv['id'];
                        $update['crk_up_onetime']= time();
                    }
                }
                Db::name('member')->where('aid', $aid)->where('id', $mid)->update($update);
            } else {
                if (getcustom('plug_sanyang',$aid)) {
                    $count = Db::name('member_level_record')->where('aid', $aid)->where('mid', $mid)->where('cid', $newlv['cid'])->count();
                    if($count) Db::name('member_level_record')->where('aid', $aid)->where('mid', $mid)->where('cid', $newlv['cid'])->update(['levelid' => $newlv['id'], 'levelendtime' => $levelendtime]);
                    else {
                        $record_data = ['levelid' => $newlv['id'], 'levelendtime' => $levelendtime];
                        $record_data['aid'] = $aid;
                        $record_data['mid'] = $mid;
                        $record_data['createtime'] = time();
                        $record_data['cid'] = $newlv['cid'];
                        Db::name('member_level_record')->insertGetId($record_data);
                    }
                    Db::name('member_level_record')->where('aid', $aid)->where('mid', $mid)->where('cid', $newlv['cid'])->update(['levelstarttime' => time()]);
                }
            }

            Wechat::updatemembercard($aid, $mid);
            //赠送积分
            if($newlv['up_give_score'] > 0) {
                self::addscore($aid, $mid, $newlv['up_give_score'], '升级奖励');
            }
            //奖励佣金
            if($newlv['up_give_commission'] > 0) {
                self::addcommission($aid,$mid,0,$newlv['up_give_commission'],'升级奖励');
            }
            //奖励余额
            if($newlv['up_give_money'] > 0) {
                self::addmoney($aid,$mid,$newlv['up_give_money'],'升级奖励');
            }
            
            if (getcustom('240308.fqywl.com')) {
                $set = Db::name('admin_set')->where('aid',$aid)->find();
               
                //奖励余额
                if($set['give_lvid'] > 0 && $newlv['id']== $set['give_lvid'] ) {
                   //奖励余额
                   $ordermoney = 0 + Db::name('shop_order')->where('aid',$aid)->where('mid',$mid)->where('status','in','1,2,3')->sum('totalprice');
                   $give_credit1 = 0;
                   $give_credit2 = $set['give_credit2'];
                  
                    if($set['give_credit1'] > 0) {
                        $give_credit1 =  round($ordermoney*$set['give_credit1'],1);
                        self::addcredit1($aid,$mid,$give_credit1,'升级奖励',1);
                    }
                       //奖励余额
                    if($give_credit2 > 0) {
                        self::addcredit2($aid,$mid,$give_credit2,'升级奖励',1);
                    }
                    $record_data = [];
                    $record_data['aid'] = $aid;
                    $record_data['mid'] = $mid;
                    $record_data['totalprice'] = $ordermoney;
                    $record_data['credit1'] = $give_credit1;
                    $record_data['credit2'] = $give_credit2;
                    $record_data['credit2_after'] = $give_credit2;
                    $record_data['createtime'] = time();
                    Db::name('member_credit_record')->insertGetId($record_data);
                    $update = ['lv_time' => time()];
                    Db::name('member')->where('aid', $aid)->where('id', $mid)->update($update);
                }elseif ($newlv['id'] != $set['give_lvid'] && $newlv['maxrate'] > 0) {
                    
                    $member_info = Db::name('member')->where('aid',$aid)->where('id',$mid)->find();
                    if ($member_info['credit2'] > 0) {
                        self::addcredit1($aid,$mid,$member_info['credit1']*-1,'升级清空');
                        self::addcredit2($aid,$mid,$member_info['credit2']*-1,'升级清空');
                    }
                    
                    if ($member['lv_time'] <= 0) {
                        $update = ['lv_time' => time()];
                        Db::name('member')->where('aid', $aid)->where('id', $mid)->update($update);
                        $ordermoney = 0 + Db::name('shop_order')->where('aid',$aid)->where('mid',$mid)->where('status','in','1,2,3')->sum('totalprice');
                    }else {
                        $ordermoney = 0 + Db::name('shop_order')->where('aid',$aid)->where('mid',$mid)->where('status','in','1,2,3')->where('paytime','<=',$member_info['lv_time'])->sum('totalprice');
                        $ordermoney = $ordermoney + Db::name('shop_order')->where('aid',$aid)->where('mid',$mid)->where('status','in','1,2,3')->where('paytime','>',$member_info['lv_time'])->where('totalprice','>=',$set['give_min'])->sum('totalprice');
                    }
                    $credit1 = 0 + Db::name('member_credit1log')->where('aid',$aid)->where('mid',$mid)->where('addtotal',2)->sum('credit1');
                    $max_credit1 = round($ordermoney*$newlv['maxrate'] - $credit1 ,2);
                    if ($max_credit1 > 0) {
                        self::addcredit1($aid,$mid,$max_credit1,'升级奖励'.$newlv['name'].'已奖励-'.$credit1.'当前订单金额'.$ordermoney,2);
                    }
                }
            }
            
            //赠送上级佣金
            if($newlv['up_give_parent_money'] > 0 && $member['pid']) {
                if(getcustom('coupon_xianxia_buy',$aid)){
                    $next_level = Db::name('member_level')->where('aid',$aid)->where('sort','>',$newlv['sort'])->order('sort asc')->find();
                    if($next_level){
                        self::addcommission($aid, $member['pid'], $mid, $newlv['up_give_parent_money'], '直推奖');
                    }
                }else{
                    self::addcommission($aid, $member['pid'], $mid, $newlv['up_give_parent_money'], '直推奖');
                }
            }

            if($newlv['up_give_parent_coupon_ids'] && $newlv['up_give_parent_coupon_nums'] && $member['pid']){
                $coupon_ids = explode(',',$newlv['up_give_parent_coupon_ids']);
                $coupon_nums = explode(',',$newlv['up_give_parent_coupon_nums']);
                if($coupon_ids){
                    foreach($coupon_ids as $ck=>$coupon_id){
                        if(!$coupon_nums[$ck]){
                            $coupon_nums[$ck] = 1;
                        }
                        for($i=0;$i<$coupon_nums[$ck];$i++){
                            \app\commons\Coupon::send($aid,$member['pid'],$coupon_id,true);
                        }
                    }
                }
            }

            //升级赠送优惠券
            if(getcustom('up_give_coupon',$aid)){
                //商城优惠券赠送
                $shop_coupon = $newlv['up_give_coupon']?json_decode($newlv['up_give_coupon'],true):[];
                foreach($shop_coupon as $k=>$v){
                    if($v['num']<1){
                        continue;
                    }
                    for($i=0;$i<$v['num'];$i++){
                        \app\commons\Coupon::send($aid,$member['id'],$v['id'],true);
                    }
                }
                //餐饮优惠券赠送
                $restaurant_coupon = $newlv['up_give_restaurant_coupon']?json_decode($newlv['up_give_restaurant_coupon'],true):[];
                foreach($restaurant_coupon as $k=>$v){
                    if($v['num']<1){
                        continue;
                    }
                    for($i=0;$i<$v['num'];$i++){
                        \app\commons\Coupon::send($aid,$member['id'],$v['id'],true);
                    }
                }
            }
            $up_giveparent_prize = 0;//升级后 他直推的几个人留给他的上级的同时给上级发放见点奖
            if(getcustom('up_giveparent_prize',$aid)){
                $up_giveparent_prize = $newlv['up_giveparent_prize']?:0;
            }
            if(getcustom('up_giveparent',$aid) && $newlv['up_giveparent_num'] > 0 /*&& $member['pid']*/){ //升级后 他直推的几个人留给他的上级
                //文档 https://doc.weixin.qq.com/doc/w3_AT4AYwbFACwly20n4PhQXGIkzEGvk?scode=AHMAHgcfAA0QNhPwm4AT4AYwbFACw
                $whereup = [];
                $whereup[] = ['aid', '=', $aid];
                $whereup[] = ['pid', '=', $mid];
                if($newlv['up_giveparent_levelid']){
                    //指定等级,多个英文逗号分隔
                    $newlv['up_giveparent_levelid'] = explode(',',$newlv['up_giveparent_levelid']);
                    $whereup[] = ['levelid', 'in', $newlv['up_giveparent_levelid']];
                }
                $downmemberlist = Db::name('member')->where($whereup)->limit($newlv['up_giveparent_num'])->order('id')->select()->toArray();
                if($downmemberlist){
                    $newlv['up_giveparent_levelid_p'] = trim($newlv['up_giveparent_levelid_p']);
                    $newlv_up_giveparent_levelid_arr = explode(',',$newlv['up_giveparent_levelid_p']);
//                    Log::write([
//                        'file'=>__FILE__,
//                        'line'=>__LINE__,
//                        'levelidp'=>$newlv_up_giveparent_levelid_arr,
//                        'downmemberlist'=>jsonEncode($downmemberlist)
//                    ]);
                    foreach($downmemberlist as $downmember){
                        //升级后 他直推的几个人留给他的上级，原推荐人不改变
                        $downmember_level = Db::name('member_level')->where('id',$downmember['levelid'])->field('id,up_change_back')->find();
                        //如同时符合“升级给上级人数”和“升级后回归”，则不脱离，直接回归
                        if($downmember_level['up_change_back'] == 1) {
                            Log::write([
                                'file'=>__FILE__,
                                'error'=>'同时符合“升级给上级人数”和“升级后回归”，则不脱离，直接回归'
                            ]);
                            continue;
                        }
                        //指定上级是默认上级还是指定等级的上级
                        if(!empty($newlv['up_giveparent_levelid_p']) && !empty($newlv_up_giveparent_levelid_arr)){
                            //如果指定了等级，他的所有上级都不在指定的等级范围内，则留给平台
                            $newpid = 0;
                            if($member['path']){
                                $parentList = Db::name('member')->whereIn('levelid',$newlv_up_giveparent_levelid_arr)->where('aid',$aid)->where('id','in',$member['path'])->order(Db::raw('field(id,'.$member['path'].')'))->select()->toArray();
                                if($parentList){
                                    $nearP = end($parentList);
                                    $newpid = $nearP['id'];
                                }
                            }
                        }else{
                            $newpid = $member['pid'];
                        }
                        $updatem = ['id'=>$downmember['id'],'pid'=>$newpid,'change_pid_time'=>time()];
                        if(!$downmember['pid_origin']){
                            $updatem['pid_origin'] = $downmember['pid'];
                            $updatem['path_origin'] = $downmember['path'];
                        }
                        \app\models\Member::edit($aid,$updatem);//todo
                        $insertLog = ['aid'=>$aid,'mid'=>$downmember['id'],'pid'=>$newpid,'createtime'=>time()];
                        if($downmember['pid_origin']){
                            $insertLog['pid_origin'] = $downmember['pid_origin'];
                            $insertLog['path_origin'] = $downmember['path_origin'];
                        }else{
                            $insertLog['pid_origin'] = $downmember['pid'];
                            $insertLog['path_origin'] = $downmember['path'];
                        }
                        Db::name('member_pid_changelog')->insert($insertLog);
                        //给上级发放见点奖
                        if($up_giveparent_prize>0 && $newpid){
                            \app\commons\Member::addcommission($aid,$newpid,$downmember['id'],$up_giveparent_prize,t('见点奖',$aid));

                        }
                    }

                }
            }

            if(getcustom('up_change_pid',$aid)){
                //升级后脱离上级，推荐人为空
                if($newlv['up_change_pid'] == 1 && $member['pid']) {
                    //原推荐人不改变
                    $updatem = ['id'=>$mid,'pid'=>0,'change_pid_time'=>time()];
                    if(!$member['pid_origin']){
                        $updatem['pid_origin'] = $member['pid'];
                        $updatem['path_origin'] = $member['path'];
                    }
                    Log::write([
                        'file'=>__FILE__,
                        'line'=>__LINE__,
                        'edit'=>$updatem
                    ]);
                    \app\models\Member::edit($aid,$updatem);
                    $insertLog = ['aid'=>$aid,'mid'=>$mid,'pid'=>0,'createtime'=>time()];
                    if($member['pid_origin']){
                        $insertLog['pid_origin'] = $member['pid_origin'];
                        $insertLog['path_origin'] = $member['path_origin'];
                    }else{
                        $insertLog['pid_origin'] = $member['pid'];
                        $insertLog['path_origin'] = $member['path'];
                    }
                    Db::name('member_pid_changelog')->insert($insertLog);
                }
                //升级后回归到以前的推荐人下面（仅脱离的人生效）
                if($newlv['up_change_back'] == 1 && $member['pid_origin']){
                    Log::write([
                        'file'=>__FILE__,
                        'line'=>__LINE__,
                        'member[pid_origin]'=>$member['pid_origin']
                    ]);
                    //230909 pid_origin=0改为pid_origin=null
                    \app\models\Member::edit($aid,['id'=>$mid,'pid'=>$member['pid_origin'],'pid_origin'=>null,'path_origin'=>'','change_pid_time'=>time()]);
                    Db::name('member_pid_changelog')->where('aid',$aid)->where('mid',$mid)->where('pid_origin',$member['pid_origin'])->update(['isback'=>1,'updatetime'=>time()]);
                }
            }

            if(getcustom('commission_frozen',$aid)){
                //扶持金解冻冻结
                $admin = Db::name('admin')->where('id',$aid)->find();
                $unfrozen_mid_arr = [];
                $unfrozen1_mids = [];
                $unfrozen2_mids = [];
                $unfrozen3_mids = [];
                $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->find();
                if($admin['commission_frozen'] == 1 && ($member['pid'] || $member['pid_origin'])){
                    $set = Db::name('admin_set')->where('aid',$aid)->find();
                    $fuchi_unfrozen1_ceng = $set['fuchi_unfrozen1_ceng']??0;//伞下会员层级限制
                    $frozen_type = explode(',',$set['fuchi_unfrozen']);
                    //1 伞下x个,等级ID为x的会员（等级ID多个使用英文逗号间隔）
                    //2 直推脱离的会员等级ID升级为x（等级ID多个使用英文逗号间隔）
                    //3 直推会员等级ID升级为x（等级ID多个使用英文逗号间隔）
                    //解冻方式 fuchi_unfrozen_type 1全部(判断条件1和2)，2单线（判断条件1和3）
                    if($frozen_type){
                        $fuchi_unfrozen1_levelidArr = explode(',',$set['fuchi_unfrozen1_levelid']);
                        if(in_array(1,$frozen_type) && in_array($newlv['id'],$fuchi_unfrozen1_levelidArr) && $set['fuchi_unfrozen1_num'] > 0){
                            //存在多个上级同时解冻
                            $parentList = Db::name('member')->field('id,nickname,pid,path,commission_frozen_status')->where('id','in',$member['path'])->where('commission_frozen_status',0)->select()->toArray();
                            foreach($parentList as $parent){
                                $children = self::getdownmids($aid,$parent['id'],$fuchi_unfrozen1_ceng,$set['fuchi_unfrozen1_levelid']);
                                if(count($children) >= $set['fuchi_unfrozen1_num']) {
                                    $unfrozen1_mids[] = $parent['id'];
                                }
                            }
                        }

                        $fuchi_unfrozen2_levelidArr = explode(',',$set['fuchi_unfrozen2_levelid']);
                        //直推脱离的所有会员等级ID升级为x,原父级解冻
                        if($set['fuchi_unfrozen_type']==1 && in_array(2,$frozen_type) && in_array($newlv['id'],$fuchi_unfrozen2_levelidArr)){
                            if($member['pid_origin']){
                                $origin_commission_frozen_status = Db::name('member')->field('id,nickname,pid,path,commission_frozen_status')->where('id',$member['pid_origin'])->value('commission_frozen_status');
                                if(!$origin_commission_frozen_status){
                                    $children = Db::name('member')->where('aid',$aid)->where('pid_origin',$member['pid_origin'])->select()->toArray();
                                    $num_ok = 0;
                                    foreach ($children as $citem){
                                        if(in_array($citem['levelid'],$fuchi_unfrozen2_levelidArr)){
                                            $num_ok++;
                                        }
                                    }
                                    if(count($children) == $num_ok) $unfrozen2_mids[] = $member['pid_origin'];
                                }
                            }else{
                                //升级后回归的会员，230424增加记录表，如不兼容老数据可只判断member_pid_changelog表，无需member表
                                $changelog = Db::name('member_pid_changelog')->where('aid',$aid)->where('mid',$mid)->where('pid_origin',$member['pid'])->find();
                                if($changelog['isback'] == 1){
                                    $origin_commission_frozen_status = Db::name('member')->field('id,nickname,pid,path,commission_frozen_status')->where('id',$member['pid'])->value('commission_frozen_status');
                                    if(!$origin_commission_frozen_status){
                                        $children = Db::name('member')->where('aid',$aid)->where('pid_origin',$member['pid'])->select()->toArray();
                                        $childrenbackMids = Db::name('member_pid_changelog')->where('aid',$aid)->where('pid_origin',$member['pid'])->where('isback',1)->column('mid');
                                        $childrenback = Db::name('member')->where('aid',$aid)->whereIn('id',$childrenbackMids)->select()->toArray();
                                        $num_ok = 0;
                                        foreach ($children as $citem){
                                            if(in_array($citem['levelid'],$fuchi_unfrozen2_levelidArr)){
                                                $num_ok++;
                                            }
                                        }
                                        foreach ($childrenback as $citem){
                                            if(in_array($citem['levelid'],$fuchi_unfrozen2_levelidArr)){
                                                $num_ok++;
                                            }
                                        }
                                        if((count($children) + count($childrenback)) == $num_ok) $unfrozen2_mids[] = $member['pid'];
                                    }
                                }
                            }
                        }
                        $fuchi_unfrozen3_levelidArr = explode(',',$set['fuchi_unfrozen3_levelid']);
                        if($set['fuchi_unfrozen_type']==2 && in_array(3,$frozen_type) && in_array($newlv['id'],$fuchi_unfrozen3_levelidArr)){
                            $unfrozen_pid = $member['pid_origin'] ? $member['pid_origin'] : $member['pid'];
                            $unfrozen3_mids[] = $unfrozen_pid;
                        }
                        //解冻方式 fuchi_unfrozen_type 1全部(判断条件1和2)，2单线（判断条件1和3）
                        if($set['fuchi_unfrozen_type']==1){
                            if(in_array(1,$frozen_type) && in_array(2,$frozen_type)){
                                $unfrozen_mid_arr = array_intersect($unfrozen1_mids,$unfrozen2_mids);
                            }else{
                                if(in_array(1,$frozen_type)){
                                    $unfrozen_mid_arr = $unfrozen1_mids;
                                }
                                if(in_array(2,$frozen_type)){
                                    $unfrozen_mid_arr = $unfrozen2_mids;
                                }
                            }
                            //解冻
                            self::unfrozenMoney($aid,$unfrozen_mid_arr);
                        }elseif($set['fuchi_unfrozen_type']==2){
                            if(in_array(1,$frozen_type) && in_array(3,$frozen_type)){
                                $unfrozen_mid_arr = array_intersect($unfrozen1_mids,$unfrozen3_mids);
                            }else{
                                if(in_array(1,$frozen_type)){
                                    $unfrozen_mid_arr = $unfrozen1_mids;
                                }
                                if(in_array(3,$frozen_type)){
                                    $unfrozen_mid_arr = $unfrozen3_mids;
                                }
                            }
                            //解冻
                            self::unfrozenMoney($aid,$unfrozen_mid_arr, $mid);
                        }
                    }
                }
            }

            if(getcustom('fenhong_jiaquan_bylevel',$aid)){
                if($newlv['fenhong_copies']){
                    \app\commons\Member::addfhcopies($aid,$mid,$newlv['fenhong_copies'],'升级奖励');
                    Db::name('member_fenhong_jiaquan')->insert([
                        'aid' => $aid,
                        'mid' => $mid,
                        'type' => 'uplevel',
                        'remark' => '升级奖励',
                        'createtime' => time(),
                        'effect_time' => time(),
                        'jiesuan_time' => time(),
                        'status' => 2,//已结算
                        'copies' => $newlv['fenhong_copies'],
                    ]);
                }
            }
            if(getcustom('yx_queue_duli_queue',$aid)){
                $duli_memebr = Db::name('member')->where('id',$mid)->where('aid',$aid)->find();
                self::duliQueue($aid,$duli_memebr,$newlv);
            }
            Log::write([
                'file'=>__FILE__,
                'line'=>__LIne__,
                'mid'=>$mid,
                'newlvid'=>$newlv['id']
            ]);

            //升级记录
            $order = [
                'aid' => $aid,
                'mid' => $mid,
                'from_mid' => $mid,
                'pid' => $member['pid'],
                'levelid' => $newlv['id'] ,
                'title' => '自动升级',
                'totalprice' => 0,
                'createtime' => time(),
                'levelup_time' => time(),
                'beforelevelid' => $levelInfo['levelid'],
                'form0' => '类型^_^自动升级',
                'platform' => platform,
                'status' => 2,
            ];
            //自动降级，记录是否检测降级状态，并更新之前的升级记录不再检测
            if(getcustom('level_auto_down',$aid)){
                Db::name('member_levelup_order')->where('mid',$mid)->update(['check_down'=>1]);
                $order['check_down'] = 0;
            }
            Db::name('member_levelup_order')->insert($order);
            if(getcustom('network_slide',$aid)){
                //公排网滑落
                $res = self::net_slide($member['pid'],$mid,$newlv['id']);
            }
            $tmplcontent = [];
            $tmplcontent['first'] = '恭喜您成功升级为'.$newlv['name'];
            $tmplcontent['remark'] = '点击进入查看~';
            $tmplcontent['keyword1'] = $newlv['name']; //会员等级
            $tmplcontent['keyword2'] = '已生效';//审核状态
            $rs = \app\commons\Wechat::sendtmpl($aid,$mid,'tmpl_uplv',$tmplcontent,m_url('pages/my/usercenter', $aid));

            if(getcustom('levelupifdownup',$aid) && $member['path']){
                $levelArr = Db::name('member_level')->where('aid',$aid)->where('cid', $cid)->order('sort,id')->column('sort','id');
                $parentList = Db::name('member')->where('id','in',$member['path'])->select()->toArray();
                foreach($parentList as $parent){
                    if($levelArr[$parent['levelid']] < $levelArr[$newlv['id']]){
                        Db::name('member')->where('aid', $aid)->where('id', $parent['id'])->update(['levelid' => $newlv['id'], 'levelendtime' => $levelendtime]);
                    }
                }
            }
            if(getcustom('ciruikang_fenxiao',$aid)){
                //一次性购买升级奖励上级
                if($member['pid'] && $params && $params['onebuy'] && $params['onebuy_orderid'] && $newlv['is_onebuy'] && $newlv['is_onebuy'] == 1){
                    \app\customs\CiruikangCustom::deal_onebuyup($aid,$mid,$member,$params,$levelInfo);
                }
            }
        }
    }

    public static function unfrozenMoney($aid,$unfrozen_mid_arr,$from_mid=0)
    {
        if(getcustom('commission_frozen',$aid)){
            $unfrozen_mid_arr = array_unique($unfrozen_mid_arr);
            if($from_mid){
                //单线
                $children = self::getdownmids($aid,$from_mid,0);
                $children[] = $from_mid;
                $children[] = 0;
                foreach ($unfrozen_mid_arr as $mid){
                    $frozen_record = Db::name('member_fuchi_record')->where('aid',$aid)->where('mid',$mid)->where('frommid','in',$children)->where('status',0)->select()->toArray();
                    foreach ($frozen_record as $v){
                        self::addFuchi($aid,$v['mid'],$v['frommid'],$v['commission']*-1,'解冻');
                        self::addcommission($aid, $v['mid'], $v['frommid'], $v['commission'], '解冻',1,'unfrozen');
                    }
                    Db::name('member_fuchi_record')->where('aid',$aid)->where('mid',$mid)->where('frommid','in',[$from_mid,0])->where('status',0)->update(['status'=>1,'endtime'=>time()]);
                }
            }else{
                foreach ($unfrozen_mid_arr as $mid){
                    Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['commission_frozen_status'=>1]);
                    $frozen_record = Db::name('member_fuchi_record')->where('aid',$aid)->where('mid',$mid)->where('status',0)->select()->toArray();
                    foreach ($frozen_record as $v){
                        self::addFuchi($aid,$v['mid'],$v['frommid'],$v['commission']*-1,'解冻');
                        self::addcommission($aid, $v['mid'], $v['frommid'], $v['commission'], '解冻',1,'unfrozen');
                    }
                    Db::name('member_fuchi_record')->where('aid',$aid)->where('mid',$mid)->where('status',0)->update(['status'=>1,'endtime'=>time()]);
                }
            }
        }
    }

    //加余额
    public static function addmoney($aid,$mid,$money,$remark,$frommid=0,$paytype='',$rechargeid='',$params=[]){
        if($money==0) return ;
        $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->lock(true)->find();
        if(!$member) return ['status'=>0,'msg'=>t('会员').'不存在'];

        if(getcustom('w7moneyscore')) {
            $w7moneyscore = db('admin_set')->where(['aid'=>$aid])->value('w7moneyscore');
            if($w7moneyscore == 1){
                return self::addw7moneyscore($aid,$member,2,$money,$remark);
            }else{
                $after = $member['money'] + $money;
                Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['money'=>$after]);
            }
        } else {
            $money_weishu = 2;
            if(getcustom('member_money_weishu')){
                $money_weishu = Db::name('admin_set')->where('aid',$aid)->value('member_money_weishu');
            }
            $money = dd_money_format($money,$money_weishu);
            $after = $member['money'] + $money;
            Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['money'=>$after]);
        }

        $data = [];
        $data['aid'] = $aid;
        $data['mid'] = $mid;
        $data['money'] = $money;
        $data['after'] = $after;
        $data['createtime'] = time();
        $data['remark'] = $remark;
        $data['paytype'] = $paytype;
        if(getcustom('money_transfer') || getcustom('money_friend_transfer')) {
            $data['from_mid'] = $frommid;
        }
        if($rechargeid){
            $data['rechargeid'] = $rechargeid;
        }
        if($params){
            if(getcustom('scoreshop_otheradmin_buy',$aid)){
                //其他平台兑换总平台扣除其他平记录扣除来源aid
                if($params['optaid']){
                    $data['optaid'] = $params['optaid'];
                }
            }
            if(getcustom('moneylog_detail')){
                $data['ordernum'] = $params['ordernum'];
                $data['type'] = $params['type'];
            }
        }
        
        Db::name('member_moneylog')->insert($data);
       // self::uplv($aid,$mid);
        Wechat::updatemembercard($aid,$mid);
        $is_send_tmpl = 1;
        if(getcustom('restaurant_finance_notice_switch')){
            if(!$member['is_receive_finance_tmpl']) $is_send_tmpl = 0;
        }
        if($is_send_tmpl){
            $tmplcontent = [];
            $tmplcontent['first'] = '您的'.t('余额').'发生变动，变动金额：'.$money;
            $tmplcontent['remark'] = '点击进入查看~';
            $tmplcontent['keyword1'] = date('Y-m-d H:i'); //变动时间
            $tmplcontent['keyword2'] = $remark;  //变动类型
            $tmplcontent['keyword3'] = (string) round($money,2);  //变动金额
            $tmplcontent['keyword4'] = (string) round($after,2);  //当前余额
            $tmplcontentNew = [];
            $tmplcontentNew['thing2'] = str_replace(',','',mb_substr($remark,0,5));//消费项目
            $tmplcontentNew['amount3'] = round($money,2);//消费金额
            $tmplcontentNew['amount4'] = round($after,2);//卡内余额
            $tmplcontentNew['time6'] = date('Y-m-d H:i'); //变动时间
            $rs = \app\commons\Wechat::sendtmpl($aid,$mid,'tmpl_moneychange',$tmplcontent,m_url('pages/my/usercenter', $aid),$tmplcontentNew);
        }
        if(getcustom('hotel')){
            //小程序消息通知
            $text = \app\models\Hotel::gettext($aid);
            $tmplcontent = [];
            $tmplcontentNew = [];
            $tmplcontentNew['amount2'] = (string) round($after,2);
            $tmplcontentNew['amount1'] = (string) round($money,2);
            $tmplcontentNew['time3'] =  date('Y-m-d H:i');
            $tmplcontentNew['thing4'] = $remark;
            $tmplcontentNew['thing5'] =  '点击进入查看~';
            if($member['wxopenid']){
                //$aid=1,$mid,$tmpltype,$contentnew,$tourl='',$content
                \app\commons\Wechat::sendwxtmpl($aid,$member['id'],'tmpl_moneychange',$tmplcontentNew,'pages/my/usercenter',$tmplcontentNew);
            }
        }
        if(getcustom('restaurant_finance_notice_switch')){
            if($member['tel'] && $money < 0 && $member['is_receive_finance_sms']){
                $rs = \app\commons\Sms::send($aid,$member['tel'],'tmpl_money_change',['money'=>$money,'sy_money'=>$after]);
            }
        }
         //变动通知
        if(getcustom('sms_temp_money_use')){
            if($member['tel'] && $money < 0){
                $rs = \app\commons\Sms::send($aid,$member['tel'],'tmpl_money_use',['money'=>$money,'real_money'=>$money,'sy_money'=>$after]);
            }
        }
        return ['status'=>1,'msg'=>''];
    }
    //加积分
    //@update 22-7-21 增加渠道
    //params 参数数组 如 'canminus'=>true 可以为负 
    public static function addscore($aid,$mid,$score,$remark,$channel='',$bid=0,$frommid=0,$addtotal=1,$params = []){
        if($score==0) return ;
        $score_weishu = 0;
        if(getcustom('score_weishu',$aid)){
            $score_weishu = Db::name('admin_set')->where('aid',$aid)->value('score_weishu');
            $score = dd_money_format($score,$score_weishu);
        }
        if($score_weishu==0){
            $score = intval($score);
            if($score==0){
                return ;
            }
        }

        $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->lock(true)->find();
        if(!$member) return ['status'=>0,'msg'=>t('会员').'不存在'];
        if(getcustom('andun_jiuxuan',$aid) && $member['levelid'] == 5){
            return ['status'=>0,'msg'=>'该等级不能获取积分'];
        }

        $canminus = false;//可以为负
        if($params && $params['canminus']){
            $canminus = true;
        }
        $member['score'] = self::getscore($member);
        if($score < 0 && $member['score'] < $score*-1) {
            if($remark == '过期扣除'){
                $score = $member['score'] *-1;
            }else{
                if(!$canminus){
                    return ['status'=>0,'msg'=>t('积分').'不足'];
                }
            }
        }

        $updata = [];
        $after = $member['score'] + $score;
        $updata['score'] = $after;

        $totalscore = $member['totalscore']?$member['totalscore']:0;
        //如果已定时任务执行统计过累计积分，且积分大于0，则走累计积分
        if($member['iscountscore'] && $score > 0 && $addtotal==1){
            $totalscore = $totalscore + $score;
        }
        $updata['totalscore'] = $totalscore;

        if(getcustom('w7moneyscore',$aid)) {
            $w7moneyscore = db('admin_set')->where(['aid'=>$aid])->value('w7moneyscore');
            if($w7moneyscore == 1){
                return self::addw7moneyscore($aid,$member,1,$score,$remark);
            }else{
                Db::name('member')->where('aid',$aid)->where('id',$mid)->update($updata);
            }
        } else {
            Db::name('member')->where('aid',$aid)->where('id',$mid)->update($updata);
        }

        $data = [];
        $data['aid'] = $aid;
        $data['bid'] = $bid;
        $data['mid'] = $mid;
        $data['score'] = $score;
        $data['after'] = $after;
        $data['createtime'] = time();
        $data['remark'] = $remark;
        $data['type'] = 1;
        $data['channel'] = $channel;
        $data['status'] = ($remark == '过期扣除') ? -1 : 0;
        $data['is_cancel'] = ($remark == '撤销操作') ? 1 : 0;
        if(getcustom('score_transfer',$aid) || getcustom('score_friend_transfer',$aid)) {
            $data['from_mid'] = $frommid;
        }
        if($params){
            if(getcustom('scoreshop_otheradmin_buy',$aid)){
                //其他平台兑换总平台扣除其他平记录扣除来源aid
                if($params['optaid']){
                    $data['optaid'] = $params['optaid'];
                }
            }
        }
        Db::name('member_scorelog')->insert($data);
        Wechat::updatemembercard($aid,$mid,$remark);

        if($score < 0 && $remark != '撤销操作' && $remark != '过期扣除'){
            $score2 = $score*-1;
            $loglist = Db::name('member_scorelog')->where('aid',$aid)->where('mid',$mid)->where('score','>',0)
                ->where('status',0)->where('is_cancel',0)->order('createtime','asc')->select()->toArray();
            foreach ($loglist as $item){
                if($item['score'] - $item['used'] <= $score2){
                    Db::name('member_scorelog')->where('id',$item['id'])->update(['used'=>$item['score'],'status'=>1]);
                }else{
                    Db::name('member_scorelog')->where('id',$item['id'])->update(['used'=>$item['used']+$score2]);
                    break;
                }
                $score2 = $score2 - ($item['score'] - $item['used']);
            }
        }

        if(getcustom('business_selfscore',$aid) && $bid ==0){
            Db::name('admin')->where('id',$aid)->inc('score',-$score)->update();
            $data = [];
            $data['aid'] = $aid;
            $data['score'] = -$score;
            $data['after'] = Db::name('admin')->where('id',$aid)->value('score');
            $data['createtime'] = time();
            if($score > 0){
                $data['remark'] = '给用户'.$member['nickname'].'加'.t('积分');
            }else{
                $data['remark'] = '用户'.$member['nickname'].'花费'.t('积分');
            }
            Db::name('admin_scorelog')->insert($data);
        }

        return ['status'=>1,'msg'=>''];
    }


    //增加冻结账户
    public static function addFreezeCredit($aid, $mid, $money, $remark, $exchange_id=0)
    {
        if (getcustom('yx_queue_free_freeze_account')) {
            if ($money == 0) return;
            $member = Db::name('member')->where('aid', $aid)->where('id', $mid)->lock(true)->find();
            if (!$member) return ['status' => 0, 'msg' => t('会员') . '不存在'];
            $after = $member['freeze_credit'] + $money;
            Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['freeze_credit'=>$after]);
            $data = [];
            $data['aid'] = $aid;
            $data['mid'] = $mid;
            $data['money'] = $money;
            $data['after'] = $after;
            $data['createtime'] = time();
            $data['remark'] = $remark;
            $data['exchange_id'] = $exchange_id;
            Db::name('member_freeze_credit_log')->insert($data);
            $tmplcontent = [];
            $tmplcontent['first'] = '您的' . t('冻结账户') . '发生变动，变动数量：' . $money;
            $tmplcontent['remark'] = '点击进入查看~';
            $tmplcontent['keyword1'] = date('Y-m-d H:i'); //变动时间
            $tmplcontent['keyword2'] = $remark;  //变动类型
            $tmplcontent['keyword3'] = (string)round($money, 2);  //变动金额
            $tmplcontent['keyword4'] = (string)round($after, 2);  //当前余额
            $tmplcontentNew = [];
            $tmplcontentNew['thing2'] = str_replace(',', '', mb_substr($remark, 0, 5));//消费项目
            $tmplcontentNew['amount3'] = round($money, 2);//消费金额
            $tmplcontentNew['amount4'] = round($after, 2);//卡内余额
            $tmplcontentNew['time6'] = date('Y-m-d H:i'); //变动时间
            $rs = \app\commons\Wechat::sendtmpl($aid, $mid, 'tmpl_moneychange', $tmplcontent, m_url('pages/my/usercenter', $aid), $tmplcontentNew);
        }
    }


    //积分过期
    public static function scoreExpire(){
        if(getcustom('score_expire')){
            $admin_setlist = Db::name('admin_set')->where('score_expire_status','=',1)->where('score_expire_days','>',0)->column('aid,score_expire_status,score_expire_days','aid');
            $time = time();
            foreach($admin_setlist as $set){
                $scoreloglist = Db::name('member_scorelog')->where('aid',$set['aid'])->where('status',0)->where('is_cancel',0)->where('score','>',0)
                    ->where('createtime','<',$time - $set['score_expire_days'] * 86400)->select()->toArray();
                foreach ($scoreloglist as $item){
                    Db::name('member_scorelog')->where('id',$item['id'])->update(['expire_time'=>$time,'status'=>-1]);
                    $expirScore = ($item['score']-$item['used']) * -1;//过期积分=获得积分-已用积分
                    self::addscore($item['aid'],$item['mid'],$expirScore,'过期扣除');
                }
            }
        }
    }

    public static function addgongxian($aid,$mid,$value,$remark,$channel='',$orderid=0){
        if(getcustom('member_gongxian')){
            if($value==0) return ;
            $value = intval($value);
            $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->lock(true)->find();
            if(!$member) return ['status'=>0,'msg'=>t('会员').'不存在'];

            if($value < 0 && $member['gongxian'] < $value*-1) return ['status'=>0,'msg'=>t('贡献').'不足'];

            $after = $member['gongxian'] + $value;
            Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['gongxian'=>$after]);

            $data = [];
            $data['aid'] = $aid;
            $data['mid'] = $mid;
            $data['value'] = $value;
            $data['after'] = $after;
            $data['createtime'] = time();
            $data['remark'] = $remark;
            $data['channel'] = $channel;
            $data['orderid'] = $orderid;
            Db::name('member_gongxianlog')->insert($data);
            return ['status'=>1,'msg'=>''];
        }
    }
    //加提现积分
    public static function addscore_withdraw($aid,$mid,$score,$remark){
        if($score==0) return ;
        $score = intval($score);
        $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->lock(true)->find();
        if(!$member) return ['status'=>0,'msg'=>t('会员').'不存在'];
        if($score < 0 && $member['score_withdraw'] < $score*-1) return ['status'=>0,'msg'=>t('积分').'不足'];

        $after = $member['score_withdraw'] + $score;
        Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['score_withdraw'=>$after]);

        $data = [];
        $data['aid'] = $aid;
        $data['mid'] = $mid;
        $data['score'] = $score;
        $data['after'] = $after;
        $data['createtime'] = time();
        $data['remark'] = $remark;
        $data['type'] = 2;
        Db::name('member_scorelog')->insert($data);
        return ['status'=>1,'msg'=>''];
    }
    //加佣金

  public static function addcredit111($aid,$mid,$credit1,$credit1_money){
        $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->find();
        if(!$member) return ['status'=>0,'msg'=>t('会员').'不存在'];
        $maxrate = Db::name('member_team_level')->where('id',$member['teamlevel'])->value('maxrate');
        if ($member['maxrate']>0) {
            $maxrate = $member['maxrate'];
        }
        //if ($maxrate <= 0) $maxrate = 1;
        $commission_after = $credit1*-1;
        $memberArray = Db::name('shebei')->where('aid',$aid)->where('mid',$mid)->where('status',0)->select()->toArray();
        $update_array = [];
        foreach($memberArray as $k3=> $log) {
            if ($commission_after <= 0) break;
            $update = [];
			$update['id'] = $log['id'];
			$commission_max = round($log['totalprice']*$maxrate,2);
            if ($log['after'] > $commission_after) {
                $update['after'] = $log['after'] - $commission_after;  
				$update['commission'] = $log['commission'] + $commission_after*$credit1_money;  //当日收益
				if ($update['commission'] >= $commission_max) {
				    $update['after'] = 0;
				    $update['status'] = 1;
    		    	$update['overtime'] = time();
				}
		     	$commission_after = 0;
            }else {
                $update['after'] = 0;
				$update['commission'] =  $log['commission']+$log['after']*$credit1_money;  //当日收益
    			$update['status'] = 1;
    			$update['overtime'] = time();
                $commission_after -= $log['after'];
            }
        	$update_array[] = $update;
        }
        if(!empty($update_array)) {
			$model = new \app\models\Shebei();
			$model->saveAll($update_array);
		}
		//\app\commons\Aaa::luckfenhong($aid);
        return ['status'=>1,'msg'=>''];
    }
    /**
     * @param $aid
     * @param $mid
     * @param $frommid
     * @param $commission
     * @param $remark
     * @param $addtotal
     * @param $fhtype 类型 枚举值 'unfrozen'解冻,'withdraw_back'提现退回,admin管理员修改
     * @param $levelid 订单支付之前的会员级别，不传使用会员当前级别，用于判断扶持金条件
     * @return array|void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function addcommission($aid,$mid,$frommid=0,$commission=0,$remark='',$addtotal=1,$fhtype='',$levelid=0,$ispj=0){
        if($commission==0) return ;
        $real_commission = $commission;
        $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->lock(true)->find();

        if(!$member) return ['status'=>0,'msg'=>t('会员').'不存在'];

        $set = Db::name('admin_set')->where('aid',$aid)->find();

        if(getcustom('commission_service_fee',$aid)){
            if($set['commission_service_fee'] > 0 && $commission > 0 && !in_array($fhtype, ['unfrozen','withdraw_back','admin'])){
                //平台服务费
                $commission_service_fee = round($set['commission_service_fee'] * $commission / 100,2);
                $commission -= $commission_service_fee;
            }
        }

        if($commission > 0 && $set['commission2scorepercent'] > 0){
            $oldcommission = $commission;
            $commission = round($commission * (1-$set['commission2scorepercent']*0.01),2);
            $score = $oldcommission - $commission;
            self::addscore($aid,$mid,$score,$remark);
        }

        if(getcustom('commission_frozen',$aid)){
            if($commission > 0 && !in_array($fhtype, ['unfrozen','withdraw_back'])){
                //是否仅支持团队分红
                $fuchi_only_teamfenhong = Db::name('admin_set')->where('aid',$aid)->value('fuchi_only_teamfenhong');
                if($fuchi_only_teamfenhong==0 || $fhtype=='teamfenhong'){
                    //扶持金冻结
                    $fuchi_levelids = explode(',',$set['fuchi_levelids']);
                    $member_levelid = $levelid>0?$levelid:$member['levelid'];
                    if(in_array(-1,$fuchi_levelids) || in_array($member_levelid,$fuchi_levelids)){
                        $admin = Db::name('admin')->where('id',$aid)->find();
                        if($admin['commission_frozen'] == 1 && $set['fuchi_percent'] > 0 && $set['fuchi_percent'] <= 100 && !$member['commission_frozen_status']){
                            $fuchi_money = round($set['fuchi_percent'] * $commission / 100,2);
                            $commission -= $fuchi_money;
                            self::addFuchi($aid,$mid,$frommid,$fuchi_money,$remark);
                            Db::name('member_fuchi_record')->insert(['aid'=>$aid,'mid'=>$mid,'frommid'=>$frommid,'orderid'=>0,'ogid'=>0,'type'=>'',
                                'commission'=>$fuchi_money,'score'=>0,'remark'=>$remark,'createtime'=>time()]);
                        }
                    }
                }
            }
        }
        if(getcustom('commission_xiaofei',$aid)){
            if($commission > 0 && !in_array($fhtype, ['unfrozen','withdraw_back'])){
                //佣金金冻结
                $frozen_levelids = explode(',',$set['xiaofei_levelids']);
                if(in_array(-1,$frozen_levelids) || in_array($member['levelid'],$frozen_levelids)){
                    if($set['xiaofei_percent'] > 0 && $set['xiaofei_percent'] <= 100){
                        $xiaofei_money = round($set['xiaofei_percent'] * $commission / 100,2);
                        $commission -= $xiaofei_money;
                        self::addXiaofei($aid,$mid,$frommid,$xiaofei_money,$remark);

                    }
                }
            }
        }
        // 等级限制 达到奖励上限不再发放任何佣金，0表示不限制
        if(getcustom('commission_max',$aid)){
            $m_level_com = Db::name('member_level')->where('id',$member['levelid'])->value('commission_max');
            if($m_level_com > 0){
                // totalcommission是字符型
                $m_total = floatval($member['totalcommission']);
                if($m_total >= $m_level_com){
                    return ['status'=>0,'msg'=>t('佣金').'已达上限！'];
                }
                $com = $m_level_com - $m_total;
                if($com <= $commission){
                    $commission = $com;
                }
            }
        }
        // 佣金上限限制 达到奖励上限不再发放任何佣金
        if(getcustom('member_commission_max',$aid)){
            $member_commission_max = floatval($member['commission_max']);
            if($set['member_commission_max'] && $commission>0 && !empty($member_commission_max)){
//                $m_total = $member['totalcommission'];
//                if($m_total >= $member_commission_max){
//                    return ['status'=>0,'msg'=>t('佣金').'已达上限！'];
//                }
//                $com = $member_commission_max - $m_total;
//                if($com <= $commission){
//                    $commission = $com;
//                }
                if($member_commission_max <= $commission){
                    $commission = $member_commission_max;
                }
                if($commission<=0){
                    return ['status'=>0,'msg'=>t('佣金').'已达上限！'];
                }
            }
            if($fhtype!='admin'){
                $addtotal = 1;
            }
        }
	

        $totalcommission = $member['totalcommission'];


        //佣金发放到余额 0:关闭 1：开启
        $iscommission_send_money = 0;
        if(getcustom('commission_to_money',$aid)){
            //判断是否开启了佣金发放到余额功能，开启了则佣金数额发放到余额
            $commission_send_money   = Db::name('admin_set')->where('aid',$aid)->value('commission_send_money');
            $iscommission_send_money = $commission_send_money?1:0;
            //兼容判断，判断佣金是否自动打款，不是则减少的佣金不走发放到余额步骤
            if($iscommission_send_money == 1){
                if($commission<0){
                    // if(strpos($remark,'后台修改：') !== 0 ){
                        $iscommission_send_money = 0;
                    // }
                    if($set['commission_autowithdraw'] && $remark == '佣金打款成功'){
                        $iscommission_send_money = 1;
                        $commission_autowithdraw_back  = 1;
                    }
                }
            }


            //开启有效则会员佣金不变
            if($iscommission_send_money){

                if(isset($commission_autowithdraw_back) && $commission_autowithdraw_back == 1){
                    $fa_yue = $commission ;
                    $after           = $member['commission'];
                    $addtotal = 0;
                }else{
                    $commission_send_money_bili   = Db::name('admin_set')->where('aid',$aid)->value('commission_send_money_bili');
                    if($commission_send_money_bili >= 0){
                        if($commission_send_money_bili > 100){
                            $commission_send_money_bili = 100;
                        }
                        $fa_yue = dd_money_format($commission * $commission_send_money_bili * 0.01);
                        $commission -= $fa_yue;
                        $after           = $member['commission'] + $commission;
                        if($commission > 0 && $addtotal==1){
                            $totalcommission += $commission;
                        }
                    }
                }
                $update_member   = ['totalcommission'=>$totalcommission,'commission'=>$after];
            }
        }

        if(!$iscommission_send_money){
            $after = $member['commission'] + $commission;
             if($commission > 0 && $addtotal==1){
                $totalcommission += $commission;
             }
            $update_member = ['totalcommission'=>$totalcommission,'commission'=>$after];
        }
        if($fhtype == 'fenhong') {
            $update_member['total_fenhong_partner'] = $member['total_fenhong_partner'] + $commission;
            $update_member['total_fenhong'] = $member['total_fenhong'] + $commission;
        } elseif($fhtype == 'teamfenhong') {
            $update_member['total_fenhong_team'] = $member['total_fenhong_team'] + $commission;
            $update_member['total_fenhong'] = $member['total_fenhong'] + $commission;
        } elseif($fhtype == 'level_teamfenhong') {
            $update_member['total_fenhong_level_team'] = $member['total_fenhong_level_team'] + $commission;
            $update_member['total_fenhong'] = $member['total_fenhong'] + $commission;
        } elseif($fhtype == 'areafenhong') {
            $update_member['total_fenhong_area'] = $member['total_fenhong_area'] + $commission;
            $update_member['total_fenhong'] = $member['total_fenhong'] + $commission;
        }elseif($fhtype == 'touzi_fenhong') {
            $update_member['total_fenhong_touzi'] = $member['total_fenhong_touzi'] + $commission;
            $update_member['total_fenhong'] = $member['total_fenhong'] + $commission;
        }elseif($fhtype == 'gongxian_fenhong'){
            $update_member['total_fenhong_gongxian'] = $member['total_fenhong_gongxian'] + $commission;
            $update_member['total_fenhong'] = $member['total_fenhong'] + $commission;
        }elseif($fhtype == 'fenhong_huiben') {
            $update_member['total_fenhong_huiben'] = bcadd($member['total_fenhong_huiben'] , $real_commission,2);
            $update_member['total_fenhong'] = $member['total_fenhong'] + $commission;
        }elseif($fhtype == 'teamyejifenhong') {
            $update_member['total_team_yeji_fenhong'] = $member['total_team_yeji_fenhong'] + $commission;
            $update_member['total_fenhong'] = $member['total_fenhong'] + $commission;
        }
        if(getcustom('product_baodan',$aid)){
            //如果总佣金大于上限值,减去佣金，并且加 冻结
            if($update_member['totalcommission'] > $member['baodan_max'] && $addtotal==1  && $commission>0){
                $update_member['totalcommission'] = $update_member['totalcommission'] - $commission;
                $update_member['commission'] = $update_member['commission'] - $commission;
                //加冻结
                $totalfreeze =$member['baodan_freeze'] + $commission;
                $update_member['baodan_freeze']  = $totalfreeze;
                //加记录
                $baodan_data = [];
                $baodan_data['aid'] = $aid;
                $baodan_data['mid'] = $mid;
                $baodan_data['commission'] = $commission;
                $baodan_data['after'] = $totalfreeze;
                $baodan_data['createtime'] = time();
                $baodan_data['remark'] = $remark;
                Db::name('member_baodan_freeze_log')->insert($baodan_data);
            }
        }
        if(getcustom('commission_perc_to_score',$aid)){
            if($set['commission_perc_to_score']>0 && $commission>0){
                $scoreNum = round($set['commission_perc_to_score'] * $commission / 100,2);
                \app\commons\Member::addscore($aid,$mid,$scoreNum,t('佣金').'到'.t('积分'));
            }
        }

        if(getcustom('commission_to_money',$aid)){
            //判断是否开启了佣金发放到余额功能，开启了则佣金数额发放到余额
            if($iscommission_send_money){
                if(strpos($remark,'后台修改：')==0){
                    $remark = str_replace('后台修改','后台修改佣金',$remark);
                }
                //佣金数额发放到余额
                if(!empty($fa_yue) && $fa_yue > 0){
                    $res = self::addmoney($aid,$mid,$fa_yue,$remark,$frommid,$fhtype);
                    if(!$res || $res['status'] != 1){
                        $msg = $res['msg']?$res['msg']:'操作失败';
                        return ['status'=>0,'msg'=>$msg];
                    } 
                }
                
            }
        }

        Db::name('member')->where('aid',$aid)->where('id',$mid)->update($update_member);

        if(!isset($commission_autowithdraw_back) && $commission != 0){
        $data = [];
        $data['aid'] = $aid;
        $data['mid'] = $mid;
        $data['frommid'] = $frommid;
        $data['commission'] = $commission;
        $data['after'] = $after;
        $data['createtime'] = time();
        $data['remark'] = $remark;
         $data['addtotal'] = $addtotal;
        if($fhtype) $data['fhtype'] = $fhtype;
        $data['service_fee'] = $commission_service_fee > 0 ? $commission_service_fee : 0;
        Db::name('member_commissionlog')->insert($data);
        }
        if(getcustom('member_commission_max',$aid)){
            if($commission>0 && $fhtype!='withdraw_back'){
                //减少会员佣金上限
                self::addcommissionmax($aid,$mid,-$commission,$remark,'',0,0);
            }
        }

        if(getcustom('commission_autowithdraw',$aid)){
            if($commission>0 && strpos($remark,'后台修改：')!==0){
                $commission_autowithdraw = $set['commission_autowithdraw'];
                if($commission_autowithdraw == 1){ //佣金自动打款
                    if(!empty($fa_yue)){
                       $rs = \app\commons\Wxpay::transfers($aid,$mid,$fa_yue,'','',$remark);
                        if($rs && $rs['status']==1){ //打款成功
                            self::addcommission($aid,$mid,'',-$fa_yue,'佣金打款成功');
                        }else{
                            Log::write('自动打款失败--'.$fa_yue);
                            Log::write($rs);
                        } 
                    }
                    
                }
            }
        }
        
        if(getcustom('forcerebuy',$aid) && $commission > 0){
            $forcerebuyList = Db::name('forcerebuy')->where('aid',$aid)->where('type',0)->where('status',1)->where('commission','<=',$totalcommission)->where("find_in_set('-1',gettj) or find_in_set('".$member['levelid']."',gettj)")->select()->toArray();
            foreach($forcerebuyList as $forcerebuy){
                $orderwhere = [];
                $orderwhere[] = ['aid','=',$aid];
                $orderwhere[] = ['mid','=',$mid];
                $orderwhere[] = ['isfg','=',1];
                $orderwhere[] = ['status','in','1,2,3'];
                if($forcerebuy['fwtype'] == 1){
                    $orderwhere[] = ['cid','in',$forcerebuy['categoryids']];
                }elseif($forcerebuy['fwtype'] == 2){
                    $orderwhere[] = ['proid','in',$forcerebuy['productids']];
                }
                $totalprice = Db::name('shop_order_goods')->where($orderwhere)->sum('totalprice');
                if($totalprice < $forcerebuy['price']){
                    if($forcerebuy['wfgtype'] == 0){
                        Db::name('member')->where('id', $mid)->update(['commission_isfreeze' => 1]);
                    }else{
                        Db::name('member')->where('id', $member['id'])->update(['levelid' => $forcerebuy['wfglvid'], 'levelendtime' => 0]);
                    }
                }
            }
        }
        
        /*佣金累计达到X元降级*/
        if(getcustom('member_level_down_commission',$aid)){
            $member = Db::name('member')->field('id,totalcommission,levelid,levelendtime,pid')->where('aid',$aid)->where('id',$mid)->find();
            $memberlevel =  Db::name('member_level')->field('id,down_level_totalcommission,down_level_id2,recovery_level_proid')->where('id',$member['levelid'])->find();
            if(!$member['isauto_down'] && $memberlevel['down_level_totalcommission']>0 && ($member['totalcommission']-$member['down_commission'])>=$memberlevel['down_level_totalcommission']){
                self::level_autodown_commission($aid,$member,$memberlevel['down_level_id2']);
            }
        }
        if(getcustom('yx_queue_free_fanli_commission',$aid)){
            Log::write([
                'file' => __FILE__ . __LINE__,
                'msg' =>'pingji开始'
            ]);
		    if($ispj == 0){
                Log::write([
                    'file' => __FILE__ . __LINE__,
                    'msg' =>'pingji开始2',
                    '$aid' =>$aid ,
                    '$mid' =>$mid ,
                    '$commission' =>$commission ,
                ]);
                self::sendPingji($aid,$mid,$commission);
            }
        }
        return ['status'=>1,'msg'=>''];
    }
    public static function sendPingji($aid,$mid,$commission=0){
        //$fromid 当前会员  $mid 直推  
        if(getcustom('yx_queue_free_fanli_commission')){
            if($commission > 0){
                $member = Db::name('member')->where('id',$mid)->field('id,pid,levelid')->find();
                Log::write([
                    'file' => __FILE__ . __LINE__,
                    'msg' =>'pingji_member',
                    '$member' => $member
                ]);
                if($member && $member['pid']){
                    $parent = Db::name('member')->where('id',$member['pid'])->where('aid',$aid)->field('id,pid,levelid')->find();
                    Log::write([
                        'file' => __FILE__ . __LINE__,
                        'msg' =>'pingji_3',
                        '$aid' =>$aid ,
                        '$mid' =>$mid ,
                        '$commission' =>$commission ,
                        '$parent' => $parent
                    ]);
                    if($parent){
                        //如果出现平级
                        $queueset = Db::name('queue_free_set')->where('aid',$aid)->where('bid',0)->find();
                        Log::write([
                            'file' => __FILE__ . __LINE__,
                            'msg' =>'pingji_3',
                            '$aid' =>$aid ,
                            '$mid' =>$mid ,
                            '$member' => $member,
                            'queue_free_commission_pj' =>$queueset['queue_free_commission_pj'],
                        ]);
                        if($member['levelid'] == $parent['levelid'] && $queueset['queue_free_commission_pj'] > 0){
                            $pj_money =dd_money_format($commission * $queueset['queue_free_commission_pj'] * 0.01);
                            Log::write([
                                'file' => __FILE__ . __LINE__,
                                '$member_id_levelid' => $member['id'].'---'.$member['levelid'],
                                '$parent_id_levelid' => $parent['id'].'---'.$parent['levelid'],
                                'queue_free_commission_pj' => $queueset['queue_free_commission_pj'],
                                '$commission' => $commission,
                                '$pj_money' => $pj_money,
                            ]);
                            if($pj_money > 0){
                                \app\commons\Member::addcommission($aid,$parent['id'],$mid,$pj_money,'佣金平级奖',1,'',0,1);
                            }
                        }
                    }
                } 
            }
        }
    }
    //加扶持基金
    public static function addFuchi($aid,$mid,$frommid,$commission,$remark){
        if(getcustom('commission_frozen')){
            if($commission==0) return ;
            $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->find();
            if(!$member) return ['status'=>0,'msg'=>t('会员').'不存在'];

            $after = $member['fuchi_money'] + $commission;
            $update_member = ['fuchi_money'=>$after];
            Db::name('member')->where('aid',$aid)->where('id',$mid)->update($update_member);

            $data = [];
            $data['aid'] = $aid;
            $data['mid'] = $mid;
            $data['frommid'] = $frommid;
            $data['commission'] = $commission;
            $data['after'] = $after;
            $data['createtime'] = time();
            $data['remark'] = $remark;
            Db::name('member_fuchi_log')->insert($data);
            return ['status'=>1,'msg'=>''];
        }
    }
    //加冻结佣金
    public static function addXiaofei($aid,$mid,$frommid,$commission,$remark){
        if(getcustom('commission_xiaofei')){
            if($commission==0) return ;
            $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->find();
            if(!$member) return ['status'=>0,'msg'=>t('会员').'不存在'];

            $after = $member['xiaofei_money'] + $commission;
            $update_member = ['xiaofei_money'=>$after];
            Db::name('member')->where('aid',$aid)->where('id',$mid)->update($update_member);

            $data = [];
            $data['aid'] = $aid;
            $data['mid'] = $mid;
            $data['frommid'] = $frommid;
            $data['commission'] = $commission;
            $data['after'] = $after;
            $data['createtime'] = time();
            $data['remark'] = $remark;
            Db::name('member_xiaofei_money_log')->insert($data);
            return ['status'=>1,'msg'=>''];
        }
    }

    //加通证
    public static function addtongzheng($aid,$mid,$money,$remark,$frommid=0,$paytype=''){
        if(getcustom('product_givetongzheng')) {
            if ($money == 0) return;
            $member = Db::name('member')->where('aid', $aid)->where('id', $mid)->lock(true)->find();
            if (!$member) return ['status' => 0, 'msg' => t('会员') . '不存在'];
            $after = $member['tongzheng'] + $money;
            Db::name('member')->where('aid', $aid)->where('id', $mid)->update(['tongzheng' => $after]);

            $data = [];
            $data['aid'] = $aid;
            $data['mid'] = $mid;
            $data['money'] = $money;
            $data['after'] = $after;
            $data['createtime'] = time();
            $data['remark'] = $remark;
            $data['paytype'] = $paytype;
            $data['from_mid'] = $frommid;

            Db::name('member_tongzhenglog')->insert($data);

            $tmplcontent = [];
            $tmplcontent['first'] = '您的' . t('通证') . '发生变动，变动金额：' . $money;
            $tmplcontent['remark'] = '点击进入查看~';
            $tmplcontent['keyword1'] = date('Y-m-d H:i'); //变动时间
            $tmplcontent['keyword2'] = $remark;  //变动类型
            $tmplcontent['keyword3'] = (string)round($money, 2);  //变动金额
            $tmplcontent['keyword4'] = (string)round($after, 2);  //当前余额
            $tmplcontentNew = [];
            $tmplcontentNew['thing2'] = str_replace(',', '', mb_substr($remark, 0, 5));//消费项目
            $tmplcontentNew['amount3'] = round($money, 2);//消费金额
            $tmplcontentNew['amount4'] = round($after, 2);//卡内余额
            $tmplcontentNew['time6'] = date('Y-m-d H:i'); //变动时间
            $rs = \app\commons\Wechat::sendtmpl($aid, $mid, 'tmpl_moneychange', $tmplcontent, m_url('pages/my/usercenter', $aid), $tmplcontentNew);
            //变动通知
            return ['status' => 1, 'msg' => ''];
        }
    }

    //佣金对碰提现积分
    public static function add_commission_withdraw_score($aid,$mid,$score,$remark,$from_id=0){
        if($score==0) return ;
        $score = intval($score);
        $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->lock(true)->find();
        if(!$member) return ['status'=>0,'msg'=>t('会员').'不存在'];
        if($score < 0 && $member['commission_withdraw_score'] < $score*-1) return ['status'=>0,'msg'=>'提现积分不足'];

        $after = $member['commission_withdraw_score'] + $score;
        Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['commission_withdraw_score'=>$after]);

        $data = [];
        $data['aid'] = $aid;
        $data['mid'] = $mid;
        $data['score'] = $score;
        $data['after'] = $after;
        $data['createtime'] = time();
        $data['remark'] = $remark;
        $data['from_mid'] = $from_id;
        Db::name('member_commission_withdraw_scorelog')->insert($data);
        return ['status'=>1,'msg'=>''];
    }
    //获取余额
    public static function getmoney($member){
        if(!$member || !$member['id']) return '0.00';
        $member = db('member')->where(['id'=>$member['id']])->find();
        if(getcustom('w7moneyscore')) {
            static $w7moneyscore = -1;
            if($w7moneyscore == -1){
                $w7moneyscore = db('admin_set')->where(['aid'=>$member['aid']])->value('w7moneyscore');
            }
            $w7uniacid = db('admin_set')->where(['aid'=>$member['aid']])->value('w7uniacid');
//            Log::write([
//                'file' => __FILE__,
//                'line' => __LINE__,
//                '$w7uniacid' => $w7uniacid
//            ]);
            if($w7moneyscore == 1 && $w7uniacid){
                $fansinfo = Db::connect('w7')->table('ims_mc_mapping_fans')->where("uniacid='{$w7uniacid}' and (openid='{$member['mpopenid']}' or (unionid!='' && unionid is not null && unionid='{$member['unionid']}') or (openid!='' && openid is not null && openid='{$member['wxopenid']}'))")->find();
//                Log::write([
//                    'file' => __FILE__,
//                    'line' => __LINE__,
//                    '$fansinfo' => $fansinfo
//                ]);
                $uid = $fansinfo['uid'];
                $mcmember = Db::connect('w7')->table('ims_mc_members')->where(['uid'=>$uid])->find();
//                Log::write([
//                    'file' => __FILE__,
//                    'line' => __LINE__,
//                    '$uid' => $uid,
//                    '$mcmember' => $mcmember
//                ]);
                if(!$uid || !$mcmember) return '0.00';
                return $mcmember['credit2'];
            }
        }

        $moeny_weishu = 2;
        if(getcustom('member_money_weishu',$member['aid'])){
            $moeny_weishu = Db::name('admin_set')->where('aid',$member['aid'])->value('member_money_weishu');
        }
        $member['money'] = dd_money_format($member['money'],$moeny_weishu);
        return $member['money'];
    }
    //获取积分
    public static function getscore($member){
        if(!$member || !$member['id']) return '0';
        $member = db('member')->where(['id'=>$member['id']])->find();

        if(getcustom('w7moneyscore')) {
            static $w7moneyscore = -1;
            if($w7moneyscore == -1){
                $w7moneyscore = db('admin_set')->where(['aid'=>$member['aid']])->value('w7moneyscore');
            }
            $w7uniacid = db('admin_set')->where(['aid'=>$member['aid']])->value('w7uniacid');
            if($w7moneyscore == 1 && $w7uniacid){
                $fansinfo = Db::connect('w7')->table('ims_mc_mapping_fans')->where("uniacid='{$w7uniacid}' and (openid='{$member['mpopenid']}' or (unionid!='' && unionid is not null && unionid='{$member['unionid']}') or (openid!='' && openid is not null && openid='{$member['wxopenid']}'))")->find();
                $uid = $fansinfo['uid'];
                $mcmember = Db::connect('w7')->table('ims_mc_members')->where(['uid'=>$uid])->find();
                if(!$uid || !$mcmember) return '0';
                return intval($mcmember['credit1']);
            }
        }

        return $member['score'];
    }

    public static function addw7moneyscore($aid,$member,$type,$money,$remark){
        $w7uniacid = db('admin_set')->where(['aid'=>$aid])->value('w7uniacid');
        if(empty($w7uniacid)) {
            return ['status'=>0,'msg'=>'w7uniacid empty'];
        }
        $fansinfo = Db::connect('w7')->table('ims_mc_mapping_fans')->where("uniacid='{$w7uniacid}' and (openid='{$member['mpopenid']}' or (unionid!='' && unionid is not null && unionid='{$member['unionid']}') or (openid!='' && openid is not null && openid='{$member['wxopenid']}'))")->find();
//        \think\facade\Log::write([
//            'file' => __FILE__,
//            'line' => __LINE__,
//            '$member' => $member,
//            '$fansinfo' => $fansinfo,
//            'sql' =>  Db::connect('w7')->table('ims_mc_mapping_fans')->getLastSql()
//        ]);
        $openid = $member['mpopenid'];
        if(!$openid) $openid = $member['unionid'];
        if(!$openid) $openid = $member['wxopenid'];
        if(!$fansinfo){
            $rec = array();
            $rec['acid'] = $w7uniacid;
            $rec['uniacid'] = $w7uniacid;
            $rec['openid'] = '';//$openid
            $rec['nickname'] = $member['nickname'];
            $rec['unionid'] = $member['unionid'];
            $rec['follow'] = $member['subscribe'] ? 1 : 0;
            $rec['followtime'] = $member['subscribe_time'] ? $member['subscribe_time'] : $member['createtime'];
            $rec['tag'] = base64_encode(serialize([
                'openid'=>$openid,
                'nickname'=>$member['nickname'],
                'sex'=>$member['sex'],
                'province'=>$member['province'],
                'city'=>$member['city'],
                'country'=>$member['country'],
                'unionid'=>$member['unionid'],
                'subscribe'=>$member['subscribe'],
                'subscribe_time'=>$member['subscribe_time'],
            ]));
            $member2 = array();
            $member2['uniacid'] = $w7uniacid;
            $member2['email'] = md5($openid).'@we7.cc';
            $member2['salt'] = random(8);
            $default_groupid = Db::connect('w7')->table('ims_mc_groups')->where(['uniacid'=>$w7uniacid,'isdefault'=>1])->value('groupid');
            $member2['groupid'] = $default_groupid;
            $member2['createtime'] = time();
            $member2['nickname'] = $member['nickname'];
            $member2['avatar'] = $member['headimg'];
            $member2['nationality'] = $member['country'];
            $member2['resideprovince'] = $member['province'];
            $member2['residecity'] = $member['city'];
            $config = include(ROOT_PATH.'config.php');
            $member2['password'] = md5($openid . $member2['salt'] . $config['authkey']);
            $rec['uid'] = Db::connect('w7')->table('ims_mc_members')->insertGetId($member2);
            Db::connect('w7')->table('ims_mc_mapping_fans')->insertGetId($rec);
        }
        $fansinfo = Db::connect('w7')->table('ims_mc_mapping_fans')->where("uniacid='{$w7uniacid}' and (openid='{$member['mpopenid']}' or (unionid!='' && unionid is not null && unionid='{$member['unionid']}') or (openid!='' && openid is not null && openid='{$member['wxopenid']}'))")->find();
        $uid = $fansinfo['uid'];
        $mcmember = Db::connect('w7')->table('ims_mc_members')->where(['uid'=>$uid])->find();
        if($uid == 0 || !$mcmember){
            $member2 = array();
            $member2['uniacid'] = $w7uniacid;
            $member2['email'] = md5($openid).'@we7.cc';
            $member2['salt'] = random(8);
            $default_groupid = Db::connect('w7')->table('ims_mc_groups')->where(['uniacid'=>$w7uniacid,'isdefault'=>1])->value('groupid');
            $member2['groupid'] = $default_groupid;
            $member2['createtime'] = time();
            $member2['nickname'] = $member['nickname'];
            $member2['avatar'] = $member['headimg'];
            $member2['nationality'] = $member['country'];
            $member2['resideprovince'] = $member['province'];
            $member2['residecity'] = $member['city'];
            $config = include(ROOT_PATH.'config.php');
            $member2['password'] = md5($openid . $member2['salt'] . $config['authkey']);
            $uid = Db::connect('w7')->table('ims_mc_members')->insertGetId($member2);
            Db::connect('w7')->table('ims_mc_mapping_fans')->where(['fanid'=>$fansinfo['fanid']])->update(['uid'=>$uid]);
            $mcmember = Db::connect('w7')->table('ims_mc_members')->where(['uid'=>$uid])->find();
        }
        $after = $mcmember['credit'.$type] + $money;
        Db::connect('w7')->table('ims_mc_members')->where(['uid'=>$uid])->update(['credit'.$type=>$after]);
        $data = array(
            'uid' => $uid,
            'credittype' => 'credit'.$type,
            'uniacid' => $w7uniacid,
            'num' => $money,
            'createtime' => time(),
            'operator' => '',
            'module' => 'ddwx_shop',
            'clerk_id' => '',
            'store_id' => '',
            'clerk_type' => 1,
            'remark' => $remark,
            'real_uniacid' => $uid
        );
        Db::connect('w7')->table('ims_mc_credits_record')->insert($data);

        if($type == 2){
            $data = [];
            $data['aid'] = $aid;
            $data['mid'] = $member['id'];
            $data['money'] = $money;
            $data['after'] = $after;
            $data['createtime'] = time();
            $data['remark'] = $remark;
            Db::name('member_moneylog')->insert($data);
            Db::name('member')->where(['aid'=>$aid,'id'=>$member['id']])->update(['money'=>$after]);
            Wechat::updatemembercard($aid,$member['id']);
        }else{
            $data = [];
            $data['aid'] = $aid;
            $data['mid'] = $member['id'];
            $data['score'] = $money;
            $data['after'] = $after;
            $data['createtime'] = time();
            $data['remark'] = $remark;
            Db::name('member_scorelog')->insert($data);
            Db::name('member')->where(['aid'=>$aid,'id'=>$member['id']])->update(['score'=>$after]);
            Wechat::updatemembercard($aid,$member['id'],$remark);
        }

        return ['status'=>1,'msg'=>''];
    }
	//获取多少级以内的下级
    /**
     * @param $aid
     * @param $mid
     * @param $levelnum 层级
     * @param $levelid 指定等级id 可以逗号多个等级id
     * @param $with_origin 脱离后作为原上级的升级条件，0不包含 1包含
     * @param $with_new 脱离后作为新上级的升级条件，0不包含 1包含
     * @return array
     */
	public static function getdownmids($aid,$mid,$levelnum=0,$levelid=0,$with_origin = 0,$with_new = 1,$down_level_time=0){
		$downmids = [];
		if($levelid == 0){
            if($with_new)
			    $memberlist = Db::name('member')->field('id,path')->where('aid',$aid)->where('find_in_set('.$mid.',path)')->where('createtime','>',$down_level_time)->select()->toArray();
            else
                $memberlist = Db::name('member')->field('id,path')->where('aid',$aid)->where('find_in_set('.$mid.',path)')->where(function ($query){
                    $query->whereNull('pid_origin')->whereOr('pid_origin','=',0);
                })->where('createtime','>',$down_level_time)->select()->toArray();
            //230909兼容原有回归会员 pid_origin=0的情况
            if($with_origin){
                $memberlistOrigin = Db::name('member')->field('id,path')->where('aid',$aid)->where('pid_origin',$mid)->where('createtime','>',$down_level_time)->select()->toArray();
            }
		}else{
			$levelid = str_replace('，',',',$levelid);
            if($with_new)
			    $memberlist = Db::name('member')->field('id,path')->where('aid',$aid)->where('levelid','in',$levelid)->where('find_in_set('.$mid.',path)')->where('createtime','>',$down_level_time)->select()->toArray();
            else
                $memberlist = Db::name('member')->field('id,path')->where('aid',$aid)->where('levelid','in',$levelid)->where('find_in_set('.$mid.',path)')->where(function ($query){
                    $query->whereNull('pid_origin')->whereOr('pid_origin','=',0);
                })->where('createtime','>',$down_level_time)->select()->toArray();
			if (getcustom('plug_sanyang')) {
                $levelmids = Db::name('member_level_record')->where('aid', $aid)->where('levelid','in',$levelid)->column('mid');
                if(!empty($levelmids)) {
                    $levelmids = array_unique($levelmids);
                    $memberlist2 = Db::name('member')->field('id,path')->where('aid',$aid)->whereIn('id',$levelmids)->where('find_in_set('.$mid.',path)')->where('createtime','>',$down_level_time)->select()->toArray();
                    if(!empty($memberlist2)) {
                        $memberlist = array_merge($memberlist, $memberlist2);
                        $memberlist = array_unique($memberlist,SORT_REGULAR);
                    }
                }
            }
            if($with_origin){
                $memberlistOrigin = Db::name('member')->field('id,path')->where('aid',$aid)->where('levelid','in',$levelid)->where('pid_origin',$mid)->where('createtime','>',$down_level_time)->select()->toArray();
            }
		}
		foreach($memberlist as $member){
			if($levelnum == 0){
				$downmids[] = $member['id'];
			}else{
				$path = explode(',',$member['path']);
                $path = array_reverse($path);
				$key = array_search($mid,$path);
				if($key!==false && $key < $levelnum){
					$downmids[] = $member['id'];
				}
			}
		}
        if($with_origin && $memberlistOrigin){
            foreach($memberlistOrigin as $member){
                $downmids[] = $member['id'];
            }
        }
		return $downmids;
	}
	public static function getdownmids2($aid,$mid,$levelnum=0,$levelid=0,$with_origin = 0,$with_new = 1,$down_level_time=0){
		$downmids = [];
		if($levelid == 0){
            $memberlist = Db::name('member')->field('id,_path')->where('aid',$aid)->where('find_in_set('.$mid.',_path)')->where(function ($query){
                $query->whereNull('pid_origin')->whereOr('pid_origin','=',0);
            })->where('createtime','>',$down_level_time)->select()->toArray();
      
		}else{
			$levelid = str_replace('，',',',$levelid);
            
            $memberlist = Db::name('member')->field('id,_path')->where('aid',$aid)->where('levelid','in',$levelid)->where('find_in_set('.$mid.',_path)')->where(function ($query){
                $query->whereNull('pid_origin')->whereOr('pid_origin','=',0);
            })->where('createtime','>',$down_level_time)->select()->toArray();
		
		}
		foreach($memberlist as $member){
			if($levelnum == 0){
				$downmids[] = $member['id'];
			}else{
				$path = explode(',',$member['_path']);
                $path = array_reverse($path);
				$key = array_search($mid,$path);
				if($key!==false && $key < $levelnum){
					$downmids[] = $member['id'];
				}
			}
		}
     
		return $downmids;
	}
	public static function getdownteammids($aid,$mid,$levelnum=0,$levelid=0,$with_origin = 0,$with_new = 1,$down_level_time=0){
		$downmids = [];
		if($levelid == 0){
            $memberlist = Db::name('member')->field('id,path')->where('aid',$aid)->where('find_in_set('.$mid.',path)')->where(function ($query){
                $query->whereNull('pid_origin')->whereOr('pid_origin','=',0);
            })->where('createtime','>',$down_level_time)->select()->toArray();
      
		}else{
			$levelid = str_replace('，',',',$levelid);
            
            $memberlist = Db::name('member')->field('id,path')->where('aid',$aid)->where('teamlevel','in',$levelid)->where('find_in_set('.$mid.',path)')->where(function ($query){
                $query->whereNull('pid_origin')->whereOr('pid_origin','=',0);
            })->where('createtime','>',$down_level_time)->select()->toArray();
		
		}
		foreach($memberlist as $member){
			if($levelnum == 0){
				$downmids[] = $member['id'];
			}else{
				$path = explode(',',$member['path']);
                $path = array_reverse($path);
				$key = array_search($mid,$path);
				if($key!==false && $key < $levelnum){
					$downmids[] = $member['id'];
				}
			}
		}
     
		return $downmids;
	}
    //获取多少级以外的下级
    /**
     * @param $aid
     * @param $mid
     * @param $levelnum 层级
     * @param $levelid 指定等级id
     * @param $with_origin 脱离后作为原上级的升级条件，0不包含 1包含
     * @param $with_new 脱离后作为新上级的升级条件，0不包含 1包含
     * @param string $where 其他查询条件
     * @return array
     */
    public static function getdowntotalmids($aid,$mid,$levelnum=0,$levelid=0,$with_origin = 0,$with_new = 1,$where='1=1'){
        $downmids = [];
        if($levelid == 0){
            if($with_new){
                $memberlist = Db::name('member')->field('id,path')->where('aid',$aid)->where('find_in_set('.$mid.',path)')->where($where)->select()->toArray();
            } else{
                $memberlist = Db::name('member')->field('id,path')->where('aid',$aid)->where('find_in_set('.$mid.',path)')->where(function ($query){
                    $query->whereNull('pid_origin')->whereOr('pid_origin','=',0);
                })->where($where)->select()->toArray();
            }
            if($with_origin){
                $memberlistOrigin = Db::name('member')->field('id,path')->where('aid',$aid)->where('pid_origin',$mid)->where($where)->select()->toArray();
            }
        }else{
            $levelid = str_replace('，',',',$levelid);
            if($with_new)
                $memberlist = Db::name('member')->field('id,path')->where('aid',$aid)->where('levelid','in',$levelid)->where('find_in_set('.$mid.',path)')->where($where)->select()->toArray();
            else
                $memberlist = Db::name('member')->field('id,path')->where('aid',$aid)->where('levelid','in',$levelid)->where('find_in_set('.$mid.',path)')->where($where)->where(function ($query){
                    $query->whereNull('pid_origin')->whereOr('pid_origin','=',0);
                })->select()->toArray();
            if (getcustom('plug_sanyang')) {
                $levelmids = Db::name('member_level_record')->where('aid', $aid)->where('levelid','in',$levelid)->column('mid');
                if(!empty($levelmids)) {
                    $levelmids = array_unique($levelmids);
                    $memberlist2 = Db::name('member')->field('id,path')->where('aid',$aid)->whereIn('id',$levelmids)->where('find_in_set('.$mid.',path)')->select()->toArray();
                    if(!empty($memberlist2)) {
                        $memberlist = array_merge($memberlist, $memberlist2);
                        $memberlist = array_unique($memberlist,SORT_REGULAR);
                    }
                }
            }
            if($with_origin){
                $memberlistOrigin = Db::name('member')->field('id,path')->where('aid',$aid)->where('levelid','in',$levelid)->where('pid_origin',$mid)->where($where)->select()->toArray();
            }
        }
        foreach($memberlist as $member){
            if($levelnum == 0){
                $downmids[] = $member['id'];
            }else{
                $path = explode(',',$member['path']);
                $path = array_reverse($path);
                $key = array_search($mid,$path);
                if($key!==false && $key >= $levelnum){
                    $downmids[] = $member['id'];
                }
            }
        }
        if($with_origin && $memberlistOrigin){
            foreach($memberlistOrigin as $member){
                $downmids[] = $member['id'];
            }
        }
        return $downmids;
    }

	//获取多少级以内的下级 小区的(即除了人数最多的区的所有区)
	public static function getdownmids_xiao($aid,$mid,$levelnum=0,$levelid=0){
		$childList = Db::name('member')->field('id,path')->where('aid',$aid)->where('pid',$mid)->select()->toArray();

		$downmidsArr = [];
		foreach($childList as $cmember){
			$thisdownmids = self::getdownmids($aid,$cmember['id'],$levelnum,$levelid);
			if(!$thisdownmids){
				$thisdownmids = $cmember['id'];
			}else{
				$thisdownmids[] = $cmember['id'];
			}
			$downmidsArr[] = ['count'=>count($thisdownmids),'mids'=>$thisdownmids];
		}
		$counts = array_column($downmidsArr,'count');
		array_multisort($counts,SORT_DESC,$downmidsArr);

		$downmids = [];
		foreach($downmidsArr as $k=>$v){
			if($k > 0){
				$downmids = array_merge($downmids,$v['mids']);
			}
		}
		return $downmids;
	}

    //获取多少级以内的下级 去除业绩最高的一个
	public static function getdownmids_removemax($aid,$mid,$levelnum=0,$levelid=0){
		$childList = Db::name('member')->field('id,path')->where('aid',$aid)->where('pid',$mid)->select()->toArray();
		$downmidsArr = [];
		foreach($childList as $cmember){
			$thisdownmids = self::getdownmids($aid,$cmember['id'],$levelnum,$levelid);
			if(!$thisdownmids){
				$thisdownmids = [$cmember['id']];
			}else{
				$thisdownmids[] = $cmember['id'];
			}
			//\think\facade\Log::write($thisdownmids);
			$fxordermoney = 0 + Db::name('shop_order_goods')->where('status','in','1,2,3')->where('mid','in',$thisdownmids)->sum('totalprice');
			$downmidsArr[] = ['count'=>count($thisdownmids),'mids'=>$thisdownmids,'fxordermoney'=>$fxordermoney];
		}
		//\think\facade\Log::write($downmidsArr);
		$counts = array_column($downmidsArr,'fxordermoney');
		array_multisort($counts,SORT_DESC,$downmidsArr);
		//\think\facade\Log::write($downmidsArr);

		$downmids = [];
		foreach($downmidsArr as $k=>$v){
			if($k > 0){
				$downmids = array_merge($downmids,$v['mids']);
			}
		}
		return $downmids;
	}
	//获取团队的会员id集合 团队中有和他平级或超过他等级的就跳出
	private static $mids = [];
	public static function getteammids($aid,$mid,$deep=999,$levelids=[],$mids=[],$thisdeep=0){
		if($thisdeep == 0){
			self::$mids = [];
		}
		$thisdeep = $thisdeep+1;
		if($thisdeep > $deep) return self::$mids;
        $where = [];
        $where[] = ['aid','=',$aid];
        $where[] = ['pid','=',$mid];
        if(!empty($levelids)){
            $where[] = ['levelid','in',$levelids];
        }
		$dowmids = Db::name('member')->where($where)->column('id');
		if($dowmids){
			foreach($dowmids as $downmid){
				if(!in_array($downmid,self::$mids)){
					self::$mids[] = $downmid;
					$down2mids = self::getteammids($aid,$downmid,$deep,$levelids,$mids,$thisdeep);
				}
			}
		}
		return self::$mids;
	}
	 //每条线查找到 某一个等级就停，不再继续查询
    private static $mids2 = [];
    public static function getteammidsByStoplevelid($aid,$mid,$deep=999,$mids2=[],$stoplevelid=0,$thisdeep=0){
        if($thisdeep == 0){
            self::$mids2 = [];
        }
        $thisdeep = $thisdeep+1;
        $where = [];
        $where[] = ['aid','=',$aid];
        $where[] = ['pid','=',$mid];
        $dowmids = Db::name('member')->where($where)->column('id,levelid');
        if($dowmids){
            foreach($dowmids as $downmid){
                //如果等级和 停止等级一样
                if(!in_array($downmid['id'],self::$mids2)){
                    self::$mids2[] = $downmid['id'];
                    if($downmid['levelid'] ==$stoplevelid ){
                        continue;
                    }
                    self::getteammidsByStoplevelid($aid,$downmid['id'],$deep,$mids2,$stoplevelid,$thisdeep);
                }
            }
        }
        return self::$mids2;
    }

    /**
     * @param $aid
     * @param $path
     * @param $where
     * @param $sort true 由近到远；false 由远到近
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function getParentsByPath($aid,$path,$where=[],$sort=true)
    {
        if(empty($path)) return [];
        $parentList = Db::name('member')->where('aid',$aid)->where('id','in',$path)->where($where)
            ->order(Db::raw('field(id,'.$path.')'))->select()->toArray();
        if($parentList){
            if($sort) $parentList = array_reverse($parentList);
            return $parentList;
        }
        return [];
    }

    public static function addHongbaoEverydayEdu($aid,$mid,$money,$remark,$ogid=0){
        if($money==0) return ;
        $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->lock(true)->find();
        if(!$member) return ['status'=>0,'msg'=>t('会员').'不存在'];

        $after = $member['hongbao_everyday_edu'] + $money;
        Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['hongbao_everyday_edu'=>$after]);

        $data = [];
        $data['aid'] = $aid;
        $data['mid'] = $mid;
        $data['money'] = $money;
        $data['ogid'] = $ogid;
        $data['createtime'] = time();
        $data['remark'] = $remark;
        Db::name('member_hbe_edu_record')->insert($data);
        return ['status'=>1,'msg'=>''];
    }

    public static function addHongbaoLog($aid,$mid,$money,$remark){

        if($money==0) return ;
        $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->find();
        if(!$member) return ['status'=>0,'msg'=>t('会员').'不存在'];

        $afterTotal = $member['hongbao_ereryday_total'] + $money;

        $data = [];
        $data['aid'] = $aid;
        $data['mid'] = $mid;
        $data['money'] = $money;
        $data['after'] = $afterTotal;
        $data['createtime'] = time();
        $data['remark'] = $remark;
        Db::name('member_hbe_log')->insert($data);
        return ['status'=>1,'msg'=>''];
    }

    //加余额宝
    public static function addyuebaomoney($aid,$mid,$money,$remark,$type=0){

        if($money==0) return ;
        $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->find();
        if(!$member) return ['status'=>0,'msg'=>t('会员').'不存在'];

        $after = $member['yuebao_money'] + $money;
        Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['yuebao_money'=>$after]);

        $data = [];
        $data['aid'] = $aid;
        $data['mid'] = $mid;
        $data['money'] = $money;
        $data['after'] = $after;
        $data['type']  = $type;
        $data['createtime'] = time();
        $data['remark'] = $remark;
        Db::name('member_yuebao_moneylog')->insert($data);

        return ['status'=>1,'msg'=>''];
    }
    //加元宝
    public static function addyuanbao($aid,$mid,$yuanbao,$remark){
        if(getcustom('pay_yuanbao')){
            if($yuanbao==0) return ;
            $yuanbao = round($yuanbao,2);
            $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->lock(true)->find();
            if(!$member) return ['status'=>0,'msg'=>t('会员').'不存在'];

            if($yuanbao < 0 && $member['yuanbao'] < $yuanbao*-1) return ['status'=>0,'msg'=>t('元宝').'不足'];

            $after = $member['yuanbao'] + $yuanbao;
            Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['yuanbao'=>$after]);

            $data = [];
            $data['aid'] = $aid;
            $data['mid'] = $mid;
            $data['yuanbao'] = $yuanbao;
            $data['after'] = $after;
            $data['createtime'] = time();
            $data['remark'] = $remark;
            $data['type'] = 1;
            Db::name('member_yuanbaolog')->insert($data);
            return ['status'=>1,'msg'=>''];
        }
    }
    //加其他余额
    public static function addOtherMoney($aid,$mid,$type,$money,$remark){
        if(getcustom('other_money')){
            if($money==0) return ;
            $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->lock(true)->find();
            if(!$member) return ['status'=>0,'msg'=>t('会员').'不存在'];

            if($type == 'money2'){
                $type_name = t('余额2');
                $log_type  = 2;
                if($money < 0 && $member['money2'] < $money*-1){
                    return ['status'=>0,'msg'=>$type_name.'不足'];
                }
                $after = $member['money2'] + $money;
                $up = Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['money2'=>$after]);
            }else if($type == 'money3'){
                $type_name = t('余额3');
                $log_type  = 3;
                if($money < 0 && $member['money3'] < $money*-1){
                    return ['status'=>0,'msg'=>$type_name.'不足'];
                }
                $after = $member['money3'] + $money;
                $up = Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['money3'=>$after]);
            }else if($type == 'money4'){
                $type_name = t('余额4');
                $log_type  = 4;
                if($money < 0 && $member['money4'] < $money*-1){
                    return ['status'=>0,'msg'=>$type_name.'不足'];
                }
                $after = $member['money4'] + $money;
                $up = Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['money4'=>$after]);
            }else if($type == 'money5'){
                $type_name = t('余额5');
                $log_type  = 5;
                if($money < 0 && $member['money5'] < $money*-1){
                    return ['status'=>0,'msg'=>$type_name.'不足'];
                }
                $after = $member['money5'] + $money;
                $up = Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['money5'=>$after]);
            }else if($type == 'frozen_money'){
                $type_name = t('冻结金额');
                $log_type  = 0;
                if($money < 0 && $member['frozen_money'] < $money*-1){
                    return ['status'=>0,'msg'=>$type_name.'不足'];
                }
                $after = $member['frozen_money'] + $money;
                $up = Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['frozen_money'=>$after]);
            }else{
                return ['status'=>0,'msg'=>'操作类型错误'];
            }

            if(!$up){
                return ['status'=>0,'msg'=>'操作失败'];
            }

            $data = [];
            $data['aid'] = $aid;
            $data['mid'] = $mid;
            $data['type']= $log_type;
            $data['money'] = $money;
            $data['after'] = $after;
            $data['createtime'] = time();
            $data['remark'] = $remark;
            Db::name('member_othermoneylog')->insert($data);

            $tmplcontent = [];
            $tmplcontent['first'] = '您的'.$type_name.'发生变动，变动金额：'.$money;
            $tmplcontent['remark'] = '点击进入查看~';
            $tmplcontent['keyword1'] = date('Y-m-d H:i'); //变动时间
            $tmplcontent['keyword2'] = $remark;  //变动类型
            $tmplcontent['keyword3'] = (string) round($money,2);  //变动金额
            $tmplcontent['keyword4'] = (string) round($after,2);  //当前余额
            $tmplcontentNew = [];
            $tmplcontentNew['thing2'] = $remark;//消费项目
            $tmplcontentNew['amount3'] = round($money,2);//消费金额
            $tmplcontentNew['amount4'] = round($after,2);//卡内余额
            $tmplcontentNew['time6'] = date('Y-m-d H:i'); //变动时间
            $rs = \app\commons\Wechat::sendtmpl($aid,$mid,'tmpl_moneychange',$tmplcontent,m_url('pages/my/usercenter', $aid),$tmplcontentNew);
            return ['status'=>1,'msg'=>''];
        }
    }


    /**
     * 验证支付密码
     * @param $member 会员信息
     * @param $paypwd 输入的支付密码
     */
    public static function checkPayPwd($member,$paypwd){
        //设置过MD5加密的
        if($member['paypwd_rand']){
            if($member['paypwd'] == md5($paypwd.$member['paypwd_rand'])){
                return true;
            }else{
                return false;
            }
        }else{
            //未设置过MD5加密的
            if($member['paypwd'] == $paypwd){
                //验证通过后更新密码为加密后的
                $rand_str = make_rand_code(2,4);
                $paypwd = md5($paypwd.$rand_str);
                Db::name('member')->where('id',$member['id'])->update(['paypwd_rand'=>$rand_str,'paypwd'=>$paypwd]);
                return true;
            }else{
                return false;
            }
        }
    }
    //判断会员购车资金拿奖条件
    public static function goucheAble($mid){
        if(getcustom('teamfenhong_gouche')){
            $level_lists = Db::name('member_level')->column('gouche_down_num,gouche_levelid,gouche_bonus_total,sort','id');
            //判断会员是否满足拿奖条件
            $member_info = Db::name('member')->where('id',$mid)->field('id,levelid,gouche_able')->find();
            if(!$member_info['gouche_able']){
                //查询级别设置的拿奖条件
                $level_info = $level_lists[$member_info['levelid']];
                $level_limit = $level_lists[$level_info['gouche_levelid']]['sort'];//拿奖需要达到的级别
                //查询会员直推的前两个人
                $down_members = Db::name('member')->where('pid',$mid)
                    ->field('id,levelid')
                    ->order('createtime asc')
                    ->limit($level_info['gouche_down_num'])
                    ->select()->toArray();
                if(count($down_members)<$level_info['gouche_down_num']){
                    //dump($mid.'未达到购车基金资格1');
                    writeLog('会员'.$mid.'购车基金：直推下一级'.json_encode($down_members).'数量'.count($down_members).'<'.$level_info['gouche_down_num'].'未满足条件','gouche_bonus.log');
                    return false;
                }
                $gouche_able = 1;
                foreach($down_members as $k=>$down){
                    //判断级别是否达到条件
                    $level_sort = $level_lists[$down['levelid']]['sort'];
                    if($level_sort<$level_limit){
                        $gouche_able = 0;
                        writeLog('会员'.$mid.'购车基金：直推下一级会员'.$down['id'].'级别未达到'.$level_info['gouche_levelid'].'未满足条件','gouche_bonus.log');
                        break;
                    }
                    //判断累计收入是否达到条件
                    $down_commission = Db::name('member_commissionlog')->where('mid',$down['id'])->sum('commission');
                    $down_members[$k]['total_commission'] = $down_commission;
                    if(bccomp($down_commission,$level_info['gouche_bonus_total'],2)<0){
                        $gouche_able = 0;
                        writeLog('会员'.$mid.'购车基金：直推下一级会员'.$down['id'].'收入'.$down_commission.'<'.$level_info['gouche_bonus_total'].'未满足条件','gouche_bonus.log');
                        break;
                    }

                }
                //以上条件都能满足，更新会员购车基金资格
                if($gouche_able){
                    writeLog('会员'.$mid.'购车基金：直推下一级会员'.json_encode($down_members).'满足条件','gouche_bonus.log');
                    Db::name('member')->where('id',$mid)->update(['gouche_able'=>1]);
                    return true;
                }else{
                    writeLog('会员'.$mid.'购车基金：直推下一级会员'.json_encode($down_members).'未满足条件','gouche_bonus.log');
                    //dump($mid.'未达到购车基金资格2');
                    return false;
                }
            }
            writeLog('会员'.$mid.'购车基金：满足条件','gouche_bonus.log');
            return true;
        }
    }
    //判断会员旅游基金资格
    public static function lvyouAble($mid){
        if(getcustom('teamfenhong_lvyou')){
            $level_lists = Db::name('member_level')->column('lvyou_down_num,lvyou_levelid,lvyou_bonus_total,sort','id');
            $member_info = Db::name('member')->where('id',$mid)->field('id,levelid,lvyou_able')->find();
            if(!$member_info['lvyou_able']){
                //查询级别设置的拿奖条件
                $level_info = $level_lists[$member_info['levelid']];
                $level_limit = $level_lists[$level_info['lvyou_levelid']]['sort'];//拿奖需要达到的级别
                //查询会员直推的前两个人
                $down_members = Db::name('member')->where('pid',$mid)
                    ->field('id,levelid')
                    ->order('createtime asc')
                    ->limit($level_info['lvyou_down_num'])
                    ->select()->toArray();

                //查询下二级直推的前四个人
                $down_down_members = [];
                foreach($down_members as $down){
                    //查询下一级会员直推的前两个人
                    $down2_members = Db::name('member')->where('pid',$down['id'])
                        ->field('id,levelid')
                        ->order('createtime asc')
                        ->limit(2)
                        ->select()->toArray();
                    foreach($down2_members as $down2){
                        $down_down_members[] = $down2;
                    }
                    if(count($down_down_members)>=$level_info['lvyou_down_num']){
                        break;
                    }
                }
                if(count($down_down_members)<$level_info['lvyou_down_num']){
                    writeLog('会员'.$mid.'旅游基金：直推下二级'.json_encode($down_down_members).'数量'.count($down_down_members).'<'.$level_info['lvyou_down_num'].'未满足条件','lvyou_bonus.log');
                    return false;
                }
                $lvyou_able = 1;
                foreach($down_down_members as $k=>$down_down){
                    //判断级别是否达到条件
                    $level_sort = $level_lists[$down_down['levelid']]['sort'];
                    if($level_sort<$level_limit){
                        $lvyou_able = 0;
                        writeLog('会员'.$mid.'旅游基金：直推下二级会员'.$down_down['mid'].'级别'.$down_down['levelid'].'<'.$level_info['lvyou_levelid'].'未满足条件','lvyou_bonus.log');
                        break;
                    }
                    //判断累计收入是否达到条件
                    $down_commission = Db::name('member_commissionlog')->where('mid',$down_down['id'])->sum('commission');
                    $down_down_members[$k]['total_commission'] = $down_commission;
                    if(bccomp($down_commission,$level_info['lvyou_bonus_total'],2)<0){
                        $lvyou_able = 0;
                        writeLog('会员'.$mid.'旅游基金：直推下二级会员'.$down_down['mid'].'收入'.$down_commission.'<'.$level_info['lvyou_bonus_total'].'未满足条件','lvyou_bonus.log');
                        break;
                    }
                }
                //以上条件都能拿到，更新会员购车基金资格
                if($lvyou_able){
                    writeLog('会员'.$mid.'旅游基金：直推下二级会员'.json_encode($down_down_members).'满足条件','lvyou_bonus.log');
                    Db::name('member')->where('id',$mid)->update(['lvyou_able'=>1]);
                    return true;
                }else{
                    writeLog('会员'.$mid.'旅游基金：直推下二级会员'.json_encode($down_down_members).'未满足条件','lvyou_bonus.log');
                    return false;
                }
            }
            writeLog('会员'.$mid.'旅游基金：满足条件','lvyou_bonus.log');
            return true;
        }
    }

    public static function createMemberCode($aid,$mid)
    {
        if(getcustom('member_code')){
            $set = Db::name('member_code_set')->where('aid',$aid)->find();
            if($set['status'] == 1)
            {
                $last = Db::name('member')->where('aid',$aid)->where('member_code','<>','')->order('member_code','desc')->limit(1)->value('member_code');
                if($last){
                    $member_code = $last + 1;
                }else{
                    $member_code = $set['no_start'];
                }
                $member_code_img = createqrcode($member_code,'',$aid);
                Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['member_code'=>$member_code,'member_code_img'=>$member_code_img]);
                return ['status'=>1,'member_code'=>$member_code,'member_code_img'=>$member_code_img];
            }
            return ['status'=>0];
        }
    }

    /**
     * 自动降级
     */
    public function checkDownLevelCon($member,$level_data){
        if(getcustom('level_auto_down')){
            $is_down = 0;
            $date_start = $member['levelstarttime'];
            $date_end = $member['levelendtime'];
            //查询直推人数
            $is_down = 0;
            if($level_data['down_level_tjr']>0){
                $able_level_sort = Db::name('member_level')->where('id',$level_data['tjr_level_id'])->value('sort');
                $map = [];
                $map[] = ['sort','>=',$able_level_sort];
                $map[] = ['aid','=',$member['aid']];
                $able_level_ids = Db::name('member_level')->where($map)->column('id');
                $map = [];
                $map[] = ['pid','=',$member['id']];
                $map[] = ['createtime','between',[$date_start,$date_end]];
                $map[] = ['levelid','in',$able_level_ids];
                $tjnum = Db::name('member')->where($map)->count();

                if($tjnum<$level_data['down_level_tjr']){
                    $is_down = 1;
                    $desc = '直推人数'.$tjnum.',未达到'.$level_data['down_level_tjr'];
                }
            }
            //直推人数达到了，检测团队业绩
            if($is_down==0 && $level_data['down_level_teamyeji']>0){
                //团队业绩
                $yejiwhere = [];
                $yejiwhere[] = ['status','in','1,2,3'];
                $yejiwhere[] = ['createtime','between',[$date_start,$date_end]];
                $downmids = self::getteammids($member['aid'],$member['id']);
                $teamyeji = Db::name('shop_order_goods')->where('aid',$member['aid'])->where('mid','in',$downmids)->where($yejiwhere)->sum('real_totalprice');
                if($teamyeji<$level_data['down_level_teamyeji']){
                    $is_down = 1;
                    $desc = '团队业绩'.$teamyeji.',未达到'.$level_data['down_level_teamyeji'];
                }
            }
            if($level_data['down_level_tjr']<=0 && $level_data['down_level_teamyeji']<=0){
                //未设置检测条件，到期直接降级
                $is_down = 1;
                $desc = '级别到期';
            }
            return ['is_down'=>$is_down,'desc'=>$desc];

        }
    }

    //判断会员是否有团队收益的拿奖条件
    public static function teamshouyiAble($mid,$level_data){
        if(getcustom('teamfenhong_shouyi')){
            $order_money = Db::name('shop_order')
                ->where('mid','=',$mid)
                ->whereIn('status',[1,2,3])
                ->sum('totalprice');
            if($level_data['team_shouyi_ordermoney']>0 && $order_money<$level_data['team_shouyi_ordermoney']){
                writeLog('会员'.$mid.'团队收益：累计订单金额'.$order_money.'未达到'.$level_data['team_shouyi_ordermoney'],'teamfenhong_shouyi.log');
                return false;
            }
            writeLog('会员'.$mid.'团队收益：满足条件','teamfenhong_shouyi.log');
            return true;
        }
    }

    //信用额度钱包
    public static function addOverdraftMoney($aid,$mid,$money,$remark){
        if(getcustom('member_overdraft_money')) {
            if ($money == 0) return;
            $member = Db::name('member')->where('aid', $aid)->where('id', $mid)->lock(true)->find();
            if (!$member) return ['status' => 0, 'msg' => t('会员') . '不存在'];
            $after = $member['overdraft_money'] + $money;
            Db::name('member')->where('aid', $aid)->where('id', $mid)->update(['overdraft_money' => $after]);
            $data = [];
            $data['aid'] = $aid;
            $data['mid'] = $mid;
            $data['money'] = $money;
            $data['after'] = $after;
            $data['createtime'] = time();
            $data['remark'] = $remark;
            Db::name('member_overdraft_moneylog')->insert($data);
            self::uplv($aid, $mid);
            Wechat::updatemembercard($aid, $mid);

            $tmplcontent = [];
            $tmplcontent['first'] = '您的'.t('信用额度').'发生变动，变动金额：' . $money;
            $tmplcontent['remark'] = '点击进入查看~';
            $tmplcontent['keyword1'] = date('Y-m-d H:i'); //变动时间
            $tmplcontent['keyword2'] = $remark;  //变动类型
            $tmplcontent['keyword3'] = (string)round($money, 2);  //变动金额
            $tmplcontent['keyword4'] = (string)round($after, 2);  //当前余额
            $tmplcontentNew = [];
            $tmplcontentNew['thing2'] = $remark;//消费项目
            $tmplcontentNew['amount3'] = round($money, 2);//消费金额
            $tmplcontentNew['amount4'] = round($after, 2);//卡内余额
            $tmplcontentNew['time6'] = date('Y-m-d H:i'); //变动时间
            $rs = \app\commons\Wechat::sendtmpl($aid, $mid, 'tmpl_moneychange', $tmplcontent, m_url('pages/my/usercenter', $aid), $tmplcontentNew);
            return ['status' => 1, 'msg' => ''];
        }
        return ['status' => 0, 'msg' => 'failed'];
    }
    
    //线下优惠券直接升级 $member=》 领取的会员
    public static function xianxiaUpLevel($member,$persendnum,$form_mid=0){
        if(getcustom('coupon_xianxia_buy')){
            //1.根据优惠券数量进行升级 2.发放奖励
            $aid = $member['aid'];
            $newlv = $now_level = Db::name('member_level')->where('aid',$aid)->where('id',$member['levelid'])->find();
            $lvlist = Db::name('member_level')->where('aid',$aid)->where('cid', 0)->where('sort','>',$now_level['sort'])->order('sort,id')->select()->toArray();
            $isup = false;
            foreach($lvlist as $lv){
                //如果下个等级的优惠券数量  =  当前转入的数量 就进行升级
                if($persendnum >=$lv['up_get_couponnum'] && $lv['up_get_couponnum']>0){
                    $newlv =  $lv;
                    $isup = true;
                }
            }
            if($isup){
                if ($newlv['yxqdate'] > 0) {
                    $levelendtime = strtotime(date('Y-m-d')) + 86400 + 86400 * $newlv['yxqdate'];
                } else {
                    $levelendtime = 0;
                }
                //判断是否是升级到最高等级 且判断是直接升级
                $next_level = Db::name('member_level')->where('aid',$aid)->where('sort','>',$newlv['sort'])->order('sort asc')->find();
                //升级记录 
                $level_record = Db::name('member_levelup_order')->where('aid',$aid)->where('mid',$member['id'])->find();
                $is_zt_up = 0;
                if(!$next_level && !$level_record){
                     $is_zt_up = 1;
                }
                $order = [
                    'aid' => $aid,
                    'mid' => $member['id'],
                    'from_mid' => $form_mid,
                    'pid' => $member['pid'],
                    'levelid' => $newlv['id'],
                    'title' => '续费延期',
                    'totalprice' => 0,
                    'createtime' => time(),
                    'levelup_time' => time(),
                    'beforelevelid' => $now_level['id'],
                    'form0' => '类型^_^购买优惠券升级',
                    'platform' => platform,
                    'status' => 2
                ];
                Db::name('member_levelup_order')->insert($order);
                Db::name('member')->where('id',$member['id'])->update(['levelid' => $newlv['id'],'levelendtime' => $levelendtime]);
                if($member['pid']){
                    \app\commons\Member::uplv($aid,$member['pid']);
                }
                //直推发放奖励                   
                if($newlv['up_give_parent_money'] > 0 && $member['pid'] && ($is_zt_up==1 || $next_level)) {
                    \app\commons\Member::addcommission($aid, $member['pid'], $member['id'], $newlv['up_give_parent_money'], '直推奖');
                }
            }
            //发放每组优惠券奖励 推荐人链中 父级的最近团购和分公司
            $parent = Db::name('member')->where('aid',$aid)->field('id,levelid,path')->where('id',$member['pid'])->find();
            //查询关系链
            $path  =$parent['path'].','.$parent['id'];
            $parent_list = Db::name('member')->where('aid',$aid)->where('id','in',$path)->field('id,levelid')->select()->toArray();
            $parent_list = array_reverse($parent_list);
           
            //发放排序
            $sort = Db::name('member_level')->where('aid',$aid)->where('sort','>',$newlv['sort'])->order('sort asc')->where('isdefault','=',0)->column('id');
            if($newlv['xianxia_coupon_vip_tj'] && $member['pid']){
                $xianxia_coupon_tj  = json_decode($newlv['xianxia_coupon_vip_tj'],true);
                $jl_data = $xianxia_coupon_tj[$parent['levelid']]; 
                if($jl_data){
                    //循环关系链的父级的等级 是否有和设置中levelid 相同，相同  取出 佣金
                    $have_level = [];//已送佣金的等级，直送最近的，防止重复
                    foreach($parent_list as $key=>$val){
                        $nowlevelid =  $val['levelid'];
                        if($jl_data[$nowlevelid] && !in_array($nowlevelid,$have_level)){
                            $have_level[] = $nowlevelid;
                            $money = $jl_data[$val['levelid']];
                            $commission_money =  dd_money_format($money*  $persendnum);
                            if($commission_money > 0) {
                                $p_levelname =Db::name('member_level')->where('id',$val['levelid'])->value('name');
                                $remark = '团队'.$p_levelname.'('.$val['id'].')推荐'.$newlv['name'].'('.$member['id'].')奖励';
                                $give_mid = self::getXianxiaSortMid($parent_list,$sort);
                                if($give_mid ==0){
                                    \app\commons\Member::addcommission($aid, $val['id'], $member['id'], $commission_money, $remark);
                                }else{
                                    self::addXianxiaCommissionLog($aid, $val['id'], $give_mid, $member['id'],$commission_money,$persendnum, $remark);
                                }
                            }
                        }
                    }
                }
            }
            
            $form_member = Db::name('member')->where('aid',$aid)->where('id',$form_mid)->find();
            $form_level = Db::name('member_level')->where('aid',$aid)->where('id',$form_member['levelid'])->find();
         
            if($form_level['xianxia_full'] && $form_member){
                $xianxia_full = json_decode($form_level['xianxia_full'],true);
                if($xianxia_full['levelid'] ==$form_member['levelid']){//推荐人的ID  = 设置的ID
                    $coupon_send = 0+Db::name('coupon_send')->where('aid',$aid)->where('from_mid',$form_member['id'])->count();
                    if($xianxia_full['num'] && $coupon_send > $xianxia_full['num']){
                        $commission_money =  dd_money_format($xianxia_full['money']*  $persendnum);
                        if($commission_money > 0){
                            \app\commons\Member::addcommission($aid, $form_member['id'], $member['id'], $commission_money, '发券满'.$xianxia_full['num'].'组奖励');
                        }
                      
                    }
                } 
                
            }
        }
    }
    //线下优惠券 佣金应该谁发放
    public static function getXianxiaSortMid($parent_list,$sort){
        if(getcustom('coupon_xianxia_buy')) {
            $mid = 0;
            if($sort){
                foreach ($parent_list as $key=>$parent) {
                    if (in_array($parent['levelid'],$sort) && $key>0) {
                        $mid = $parent['id'];
                        break;
                    }
                }
            }
            return $mid;
        }
    }
    //添加线下优惠券 发放记录
    public static function addXianxiaCommissionLog($aid,$tomid,$give_mid,$frommid,$commission,$num,$remark=''){
        if(getcustom('coupon_xianxia_buy')) {
            $insert = [
                'aid' => $aid,
                'mid' => $give_mid,
                'tomid' => $tomid,
                'frommid' => $frommid,
                'commission' => $commission,
                'status' => 0,
                'remark' => $remark,
                'num' => $num,
                'createtime' => time()
            ];
            Db::name('xianxia_commission_log')->insert($insert);
        }
    }
    public static function xianxiaYeji($aid,$member,$yeji_reward_data,$month_start='',$month_end=''){
        //直推会员的支付金额
        $mids = Db::name('member')->where('aid',$aid)->where('pid',$member['id'])->column('id');
        $owhere = [];
        if($month_start && $month_end){
            $owhere[] = ['paytime','between',[$month_start,$month_end]];
        }
        $total_order_yeji =0+ Db::name('shop_order')->where('aid',$aid)->where('mid','in',$mids)->where('status','in',[1,2,3])->where($owhere)->sum('totalprice');
        //该用户转发的 =》 接收的用户的 等级 对应的商品的 会员价格，就是业绩
        $swhere = [];
        if($month_start && $month_end){
            $swhere[] = ['send_time','between',[$month_start,$month_end]];
        }
        $total_coupon_yeji = 0;   //(销售额)
        $my_total_coupon_yeji = 0;//按照自己的价格的销售额
        $sendcount = 0; //销售数量
        $shouyi = 0;    //收益额
        $coupon_send = Db::name('coupon_send')->where('aid',$aid)->where('from_mid',$member['id'])->where($swhere)->select()->toArray();
        foreach($coupon_send as $send){
            $total_coupon_yeji += $send['coupon_yeji'];
            $my_total_coupon_yeji += $send['from_coupon_yeji'];
            $shouyi +=$send['shouyi'];
            $sendcount++;
        }
        
        $total_yeji = dd_money_format($total_coupon_yeji + $total_order_yeji); //销售额 + 支付金额 = 总业绩
        $rewardkey = -1;
        foreach ($yeji_reward_data as $key => $reward){
            if($total_yeji >$reward['limit']){
                $rewardkey = $key;
            }
        }
        $get_commission = 0; //业绩奖励 
        if($yeji_reward_data[$rewardkey]){
            $get_commission = dd_money_format($yeji_reward_data[$rewardkey]['reward']/100 * $total_yeji);
        }
        
        
        //自己使用的也算
         $myuselist = Db::name('coupon_record')->alias('cr')
             ->join('coupon c','c.id = cr.couponid')
             ->where('cr.aid',$aid)->where('cr.mid',$member['id'])->where('cr.is_xianxia_buy',1)->where('status',1)->where('cr.from_mid','null')
             ->field('cr.*,c.productids')
             ->select()->toArray();
         $myuseryeji = 0;
        $myusecount = 0;
        foreach($myuselist as $record){
            $product_id =   explode(',',$record['productids']);
            $product = Db::name('shop_product')->where('aid',$aid)->where('id',$product_id[0])->find();
            if($product['lvprice']==1){
                $lvprice_data = json_decode($product['lvprice_data'],true);
                $myuseryeji += $lvprice_data[$member['levelid']];
            }else{
                $myuseryeji +=$product['sell_price'];
            }
            $myusecount++;
        }
        $total_coupon_yeji = $total_coupon_yeji + $myuseryeji;     
             
    
        //购买总数
        //目前我的优惠券数量
        $mycount =0+ Db::name('coupon_record')->where('aid',$aid)->where('mid',$member['id'])->where('is_xianxia_buy',1)->where('status',0)->count();
        
        //已发放下去的 （销售数）
        $mytoalcount =0+ Db::name('coupon_record')->where('aid',$aid)->where('mid',$member['id'])->where('is_xianxia_buy',1)->count();
        $total_count = $mytoalcount + $sendcount;
        $return =  [
            'totalyeji' => $total_yeji,//总业绩
            'order_yeji'  => $total_order_yeji,
            'coupon_yeji' => dd_money_format($total_coupon_yeji), //销售额，只出售优惠券额度
            'commission' => $get_commission,// 业绩奖励
            'shouyi' => $shouyi,          //收益额
            'mycount' => $mycount,        //目前剩余数量
            'sendcount' =>$sendcount + $myusecount,    //销售数
            'totalcount' => $total_count //总购买数
        ];
        return $return;
    }
	public function level_autodown_commission($aid,$member,$newlevelid){
		if(getcustom('member_level_down_commission')){
			$level_data = Db::name('member_level')->where('id',$newlevelid)->find();
			//var_dump($level_data);die;
			$data_u = [];
			$data_u['levelid'] = $newlevelid;
			if($level_data['yxqdate']>0){
				$data_u['levelendtime'] = $member['levelendtime']+86400 * $level_data['yxqdate'];
			}else{
				$data_u['levelendtime'] = 0;
			}
			$data_u['isauto_down']=1;
			$data_u['up_levelid']=$member['levelid'];
			$data_u['down_commission'] = $member['down_commission']+$member['totalcommission'];
			Db::name('member')->where('id', $member['id'])->update($data_u);
			
			//降级记录
			$order = [
				'aid' => $aid,
				'mid' => $member['id'],
				'from_mid' => $member['id'],
				'pid'=>$member['pid'],
				'levelid' => $newlevelid,
				'title' => '自动降级',
				'totalprice' => 0,
				'createtime' => time(),
				'levelup_time' => time(),
				'beforelevelid' => $member['levelid'],
				'form0' => '类型^_^' .'自动降级',
				'platform' => '',
				'status' => 2,
			];
			Db::name('member_levelup_order')->insert($order);
		}
	}
	public function recovery_level($aid,$member){
		if(getcustom('member_level_down_commission')){
			$level_data = Db::name('member_level')->where('id',$member['up_levelid'])->find();
			$data_u = [];
			$data_u['levelid'] = $member['up_levelid'];
			if($level_data['yxqdate']>0){
				$data_u['levelendtime'] = $member['levelendtime']+86400 * $level_data['yxqdate'];
			}else{
				$data_u['levelendtime'] = 0;
			}
			$data_u['isauto_down'] = 0;
			$data_u['up_levelid'] = 0;
			Db::name('member')->where('id', $member['id'])->update($data_u);
			//升级记录
			$order = [
				'aid' => $member['aid'],
				'mid' => $member['id'],
				'from_mid' => $member['id'],
				'pid'=>$member['pid'],
				'levelid' =>  $member['levelid'],
				'title' => '自动升级',
				'totalprice' => 0,
				'createtime' => time(),
				'levelup_time' => time(),
				'beforelevelid' => $level_data['id'],
				'form0' => '类型^_^' .'恢复等级',
				'platform' => '',
				'status' => 2,
			];
			Db::name('member_levelup_order')->insert($order);
		}
	}

    public function commission_to_score($sysset,$page=0,$limit=0,$last_mid=0,$commission_total=0){
        if(getcustom('commission_to_score',$sysset['aid'])){
            if($sysset['commission_to_score_status']!=1){
                return ['status'=>0,'msg'=>'未开启！'];
            }
            $score_weishu = $sysset['score_weishu']??0;
            $butie_num = 0;//补贴金额
            if($sysset['commission_to_score_type']==0){
                //按利润百分比计算补贴金额
                $where = [];
                $where[] = ['aid','=',$sysset['aid']];
                $where[] = ['status','in','1,2,3'];
                $where[] = ['refund_num','=',0];
                $e_time = strtotime(date('Y-m-d 00:00:00'));
                $s_time = $e_time-86400;
                $where[] = ['paytime','between',[$s_time,$e_time]];
                $orders = Db::name('shop_order_goods')->where($where)->field('sum(real_totalprice- cost_price * num) cost_price_total')->find();

                $butie_num = bcmul($orders['cost_price_total'],$sysset['commission_to_score_money']/100,2);
            }else{
                //按固定金额计算补贴金额
                $butie_num = $sysset['commission_to_score_money'];
            }
            if($butie_num<=0){
                return ['status'=>0,'msg'=>'补贴金额为0'];
            }

            $where = [];
            $where[] = ['aid','=',$sysset['aid']];
            $where[] = ['score','>',0];
            $where[] = ['id','>',$last_mid];
            //计算全网总佣金
            //$commission_total = Db::name('member')->where($where)->field('id,commission,score')->sum('score');
            $commission_total = dd_money_format($commission_total,$score_weishu);
            if($commission_total<0){
                if($butie_num<=0){
                    return ['status'=>0,'msg'=>'全网'.t('佣金',$sysset['aid']).'为0'];
                }
            }
            //查询会员，循环处理
            if($page>0 && $limit>0){
                $member_lists = Db::name('member')->where($where)->field('id,commission,score')->page(1,$limit)->order('id asc')->select()->toArray();
            }else{
                $member_lists = Db::name('member')->where($where)->field('id,commission,score')->order('id asc')->select()->toArray();
            }
            $sql = Db::getlastSql();
            writeLog('第'.$page.'页，'.$limit.',上一次最后一个会员ID'.$last_mid.',sql::'.$sql.'，共'.count($member_lists).'条','toscore.log');
            if(count($member_lists)<=0){
                return ['status'=>0,'msg'=>'全部处理完成！'];
            }
            $commission_to_score_bili = $sysset['commission_to_score_bili']>0?$sysset['commission_to_score_bili']:100;
            $sucnum = bcmul(($page-1),$limit);
            foreach($member_lists as $member){
                $sucnum ++;
                $last_mid = $member['id'];
                $num = bcmul(bcdiv($member['score'],$commission_total,4),$butie_num,$score_weishu);
                if($num<=0){
                    continue;
                }
                if($num>$member['score']){
                    $num = $member['score'];
                }
                $commission_num = bcmul($num,$commission_to_score_bili/100,2);
                if($num<=0 || $commission_num<=0){
                    continue;
                }
                //减少积分
                $res = self::addscore($sysset['aid'],$member['id'],-$num,'转入'.t('佣金',$sysset['aid']));
                //增加佣金
                $res = self::addcommission($sysset['aid'],$member['id'],0,$commission_num,t('积分',$sysset['aid']).'转入');
                //插入转换日志
                $log = [];
                $log['aid'] = $sysset['aid'];
                $log['mid'] = $member['id'];
                $log['commission_to_score_type'] = $sysset['commission_to_score_type'];
                $log['butie_num'] = $butie_num;
                $log['commission'] = $member['score'];
                $log['commission_total'] = $commission_total;
                $log['num'] = $num;
                $log['w_day'] = date('Ymd');
                $log['w_time'] = time();
                Db::name('commission_toscore_log')->insert($log);
            }
            if($sucnum<=0){
                return ['status'=>0,'msg'=>'全部处理完成！'];
            }
            return ['status'=>1,'msg'=>'操作成功！','sucnum'=>$sucnum,'last_mid'=>$last_mid];
        }
    }
    public function commission_to_score2($sysset,$page=0,$limit=0,$last_mid=0,$commission_total=0){
        if(getcustom('commission_to_score',$sysset['aid'])){
            //分仓库设置
            //查询积分范围所处设置
            $set_arr = Db::name('score_to_commission_set')
                ->where('aid',$sysset['aid'])
                ->where('status',1)
                ->column('*','id');
            if(empty($set_arr)){
                return ['status'=>0,'msg'=>'未设置分仓'];
            }
            $score_weishu = $sysset['score_weishu']??0;
            foreach($set_arr as $k=>$v){
                $score_total = Db::name('member')->where('aid',$sysset['aid'])->where('score>='.$v['min'].' and score<'.$v['max'])->sum('score');
                $score_total = dd_money_format($score_total,$score_weishu);
                $set_arr[$k]['score_total'] = $score_total?:0;
            }
            //按利润百分比计算补贴金额
            $where = [];
            $where[] = ['aid','=',$sysset['aid']];
            $where[] = ['status','in','1,2,3'];
            $where[] = ['refund_num','=',0];
            $e_time = strtotime(date('Y-m-d 00:00:00'));
            $s_time = $e_time-86400;
            $where[] = ['paytime','between',[$s_time,$e_time]];
            $orders = Db::name('shop_order_goods')->where($where)->field('sum(real_totalprice- cost_price * num) cost_price_total')->find();


            //查询会员，循环处理
            $where = [];
            $where[] = ['aid','=',$sysset['aid']];
            $where[] = ['score','>',0];
            $where[] = ['id','>',$last_mid];
            if($page>0 && $limit>0){
                $member_lists = Db::name('member')->where($where)->field('id,commission,score')->page(1,$limit)->order('id asc')->select()->toArray();
            }else{
                $member_lists = Db::name('member')->where($where)->field('id,commission,score')->order('id asc')->select()->toArray();
            }
            $sql = Db::getlastSql();
            writeLog('第'.$page.'页，'.$limit.',上一次最后一个会员ID'.$last_mid.',sql::'.$sql.'，共'.count($member_lists).'条','toscore.log');
            if(count($member_lists)<=0){
                return ['status'=>0,'msg'=>'全部处理完成！'];
            }
            $sucnum = bcmul(($page-1),$limit);
            foreach($member_lists as $member){
                $sucnum ++;
                $last_mid = $member['id'];
                //按分仓库发放
                $set = [];
                foreach($set_arr as $set_v){
                    if($member['score']>=$set_v['min'] && $member['score']<$set_v['max']){
                        $set = $set_v;
                        break;
                    }
                }

                if($set){
                    if($set['type']==0){
                        $butie_num = bcmul($orders['cost_price_total'],$set['butie']/100,2);
                    }else{
                        //按固定金额计算补贴金额
                        $butie_num = $set['butie'];
                    }

                    $num = bcmul(bcdiv($member['score'], $set['score_total'], 4), $butie_num, $score_weishu);
                    if ($num <= 0) {
                       continue;
                    }
                    if ($num > $member['score']) {
                        $num = $member['score'];
                    }
                    $commission_num = bcmul($num, $set['bili'] / 100, 2);
                    if ($num <= 0 || $commission_num <= 0) {
                        continue;
                    }
                    //减少积分
                    $res = self::addscore($sysset['aid'], $member['id'], -$num, '转入' . t('佣金', $sysset['aid']));
                    //增加佣金
                    $res = self::addcommission($sysset['aid'], $member['id'], 0, $commission_num, t('积分', $sysset['aid']) . '转入');
                    //插入转换日志
                    $log = [];
                    $log['aid'] = $sysset['aid'];
                    $log['mid'] = $member['id'];
                    $log['set_id'] = $set['id'];
                    $log['commission_to_score_type'] = $set['type'];
                    $log['butie_num'] = $butie_num;
                    $log['commission'] = $member['score'];
                    $log['commission_total'] = $set['score_total'];
                    $log['num'] = $num;
                    $log['w_day'] = date('Ymd');
                    $log['w_time'] = time();
                    Db::name('commission_toscore_log2')->insert($log);
                }


            }
            if($sucnum<=0){
                return ['status'=>0,'msg'=>'全部处理完成！'];
            }
            return ['status'=>1,'msg'=>'操作成功！','sucnum'=>$sucnum,'last_mid'=>$last_mid];
        }
    }

    public static function addfhcopies($aid,$mid,$copies=0,$remark='',$frommid=0){
        if(getcustom('fenhong_jiaquan_bylevel')) {
            if ($copies == 0) return;
            $copies = intval($copies);
            $member = Db::name('member')->where('aid', $aid)->where('id', $mid)->lock(true)->find();
            if (!$member) return ['status' => 0, 'msg' => t('会员') . '不存在'];

            $after = $member['fhcopies'] + $copies;
            Db::name('member')->where('aid', $aid)->where('id', $mid)->update(['fhcopies' => $after]);
            $data = [];
            $data['aid'] = $aid;
            $data['mid'] = $mid;
            $data['copies'] = $copies;
            $data['after'] = $after;
            $data['createtime'] = time();
            $data['remark'] = $remark;
            Db::name('member_fhcopies_log')->insert($data);
            return ['status' => 1, 'msg' => ''];
        }
    }

    //查询团队订单和小区团队订单数量
    public static function getTeamOrderNum($aid,$mid,$levelnum=0,$levelid=0,$down_level_time=0){
        if(getcustom('up_level_teamorder')){
            //统计团队订单数量
            $childrens = Db::name('member')->where('aid',$aid)->where('pid',$mid)->column('id');
            $order_arr = [];
            foreach($childrens as $chid_mid){
                $downmids = self::getdownmids($aid,$chid_mid,$levelnum-1,$levelid);
                if($downmids){
                    $downmids[] = $chid_mid;
                }else{
                    $downmids = [$chid_mid];
                }
                $map = [];
                $map[] = ['aid','=',$aid];
                $map[] = ['mid','in',$downmids];
                $map[] = ['status','in',[1,2,3]];
                $map[] = ['createtime','>',$down_level_time];
                $teamorder_count = Db::name('shop_order')->where($map)->count();
                $order_arr[] = $teamorder_count?:0;
            }
            $max = max($order_arr);
            $teamorder_count = array_sum($order_arr);
            $teamorder_small_count = bcsub($teamorder_count,$max);
            return [
                'teamorder_count' => $teamorder_count?:0,
                'teamorder_small_count' => $teamorder_small_count?:0
            ];
        }

    }

    //分销补贴按期发放
    public static function commission_butie($aid,$ids=[]){
        //查询所有待发放补贴
        $now_time = time();
        if($ids){
            $lists = Db::name('member_commission_butie')->where('aid',$aid)->where('status',0)->whereIn('id',$ids)->select()->toArray();
        }else{
            $lists = Db::name('member_commission_butie')->where('aid',$aid)->where('status',0)->where('next_send_time','<=',$now_time)->select()->toArray();
        }
        if(!$lists){
            return true;
        }
        foreach($lists as $v){
            $can_send = 0;
            if($v['send_circle']==0){
                $can_send = 1;
            }else if($now_time>$v['next_send_time']){
                $can_send = 1;
            }
            if(!$can_send){
                continue;
            }
            $send_bonus = bcdiv($v['commission'],$v['fx_butie_circle'],2);
            //发放补贴
            $send_circle = $v['send_circle'] + 1;
            if($send_circle>=$v['fx_butie_circle']){
                //最后一期发放剩余所有的
                $send_bonus = $v['remain'];
            }
            $remark = '订单'.$v['orderid'].'产生的'.t('分销补贴',$aid).'第'.$send_circle.'期发放';
            \app\commons\Member::addcommission($aid,$v['mid'],$v['frommid'],$send_bonus,$remark,1,'commission_butie');
            //更新记录
            $data_u = [];
            $data_u['have_send'] = bcadd($v['have_send'],$send_bonus,2);
            $data_u['remain'] = bcsub($v['commission'],$data_u['have_send'],2);
            $data_u['send_circle'] = $send_circle;
            if($send_circle>=$v['fx_butie_circle'] || $data_u['remain']<=0){
                $data_u['status'] = 1;
            }
            //判断下一次发放时间
            if($v['fx_butie_type']==1){
                //按周
                $send_day = $v['fx_butie_send_week'];
                $s_time = strtotime('+1 week last monday');
                $send_time = $s_time+$send_day*86400-86400;
            }else{
                //按月
                $s_time = strtotime(date('Y-m-t 23:59:59'))+86400;
                $t = date('t',$s_time);
                $send_day = $v['fx_butie_send_day'];
                if($t<$send_day){
                    //有可能设置31号，但是本月只到30号，取本月最后一天
                    $send_day = $t;
                }
                $send_time = strtotime(date('Y-m-'.$send_day,$s_time));
            }
            $data_u['next_send_time'] = $send_time;
            Db::name('member_commission_butie')->where('id',$v['id'])->update($data_u);
            //插入发放明细
            $data_log = [];
            $data_log['aid'] = $aid;
            $data_log['mid'] = $v['mid'];
            $data_log['pid'] = $v['id'];
            $data_log['send_num'] = $send_bonus;
            $data_log['send_circle'] = $send_circle;
            $data_log['send_time'] = time();
            Db::name('member_commission_butie_log')->insert($data_log);
        }
        return true;
    }

    //上级佣金按级差发放
    public static function parent_commission($leveldata,$parent,$mid,$aid){
        if(getcustom('member_levelup_parentcommission_jicha')){
            $levelup_parentcommission = json_decode($leveldata['levelup_parentcommission'],true);
            if($parent['pid']){
                $pids = $parent['path'];
                $parentList = Db::name('member')->where('id','in',$pids)->order(Db::raw('field(id,'.$pids.')'))->select()->toArray();
                $parentList = array_reverse($parentList);
                $jicha = $levelup_parentcommission[$parent['levelid']];
                foreach($parentList as $parent2){
                    $commission = $levelup_parentcommission[$parent2['levelid']];
                    $real_commission = bcsub($commission,$jicha,2);
                    $real_commission = $real_commission>0?$real_commission:0;
                    if($real_commission<=0){
                        continue;
                    }
                    $jicha = $commission;
                    \app\commons\Member::addcommission($aid, $parent2['id'],$mid, $real_commission, '团队会员升级奖励');
                }
            }
            return true;
        }

    }

    //释放通证
    public static function release_tongzheng($sysset){
        if(getcustom('product_givetongzheng',$sysset['aid'])) {
            $aid = $sysset['aid'];
            $release_bili = $sysset['tongzheng_release_bili'];
            $lists = Db::name('tongzheng_order_log')->where('aid', $aid)->where('status', '=', 0)->select()->toArray();
            if(!$lists){
                return true;
            }
            foreach ($lists as $order) {
                $order_status = Db::name('shop_order')->where('id',$order['orderid'])->value('status');
                if(!$order_status || !in_array($order_status,[1,2,3])){
                    Db::name('tongzheng_order_log')->where('id',$order['id'])->update(['status'=>2]);
                    continue;
                }
                //计算释放数量
                $release_num = bcmul($order['tongzheng'], $release_bili / 100, 3);
                if ($release_num <= 0) {
                    continue;
                }
                $status = 0;
                if($release_num>$order['remain']){
                    $release_num = $order['remain'];
                    $status = 1;
                }
                $remark = date('Y-m-d H:i:s') . '订单id'.$order['orderid'].t('通证') . '释放';
                //增加通证
                \app\commons\Member::addtongzheng($aid, $order['mid'], $release_num, $remark);
                //记录释放记录
                $log = [];
                $log['aid'] = $aid;
                $log['mid'] = $order['mid'];
                $log['pid'] = $order['id'];
                $log['tongzheng'] = $order['tongzheng'];
                $log['release_bili'] = $release_bili;
                $log['release_num'] = $release_num;
                $log['createtime'] = time();
                $log['remark'] = $remark;
                Db::name('tongzheng_release_log')->insert($log);
                //更新释放数量
                $data_u = [];
                $data_u['remain'] = bcsub($order['remain'],$release_num,3);
                $data_u['release_num'] = bcadd($order['release_num'],$release_num,3);
                $data_u['release_time'] = time();
                $data_u['status'] = $status;
                Db::name('tongzheng_order_log')->where('id',$order['id'])->update($data_u);
            }
            return true;
        }
    }

    //公排网滑落
    public static function net_slide($pid,$mid,$levelid){
        if(getcustom('network_slide')){
            $downmember = Db::name('member')->where('id',$mid)->find();
            $member = Db::name('member')->where('id',$pid)->field('id,aid,levelid,pid_origin')->find();
            if($member['pid_origin']>0){
                //链动裂变过来的会有不滑落，不用处理
                return ['status'=>0,'msg'=>'链动裂变过来的会有不滑落'];
            }
            $aid = $member['aid'];
            $level_info = Db::name('member_level')
                ->where('aid',$aid)
                ->where('id',$member['levelid'])
                ->field('net_down_levelid,net_down_num,net_down_next_levelid,slide_down_levelid,slide_down_team')
                ->find();
            if($level_info['net_down_num']<=0){
                //未设置人数代表不滑落，不用处理
                return ['status'=>0,'msg'=>'滑落失败，未设置人数代表不滑落'];
            }
            if($levelid!=$level_info['net_down_next_levelid']){
                //当前升级id不是设置的滑落等级，不用处理
                return ['status'=>0,'msg'=>'滑落失败，当前升级id不是设置的滑落等级'];
            }
            //查找下级人数
            $net_down_levelid = explode(',',$level_info['net_down_levelid']);
            $net_down_num = Db::name('member')->where('aid',$aid)->where('pid',$pid)->where('id','<>',$mid)->where('levelid','in',$net_down_levelid)->count();
            if($net_down_num<$level_info['net_down_num']){
                //直推下级人数不满足滑落条件，不用处理
                return ['status'=>0,'msg'=>'滑落失败，直推下级人数不满足滑落条件'];
            }
            //查找滑落给予下级，顺序：链动脱离的人—自己直推的人—链动裂变过来的人—公排滑落下来的人
            $slide_member = self::getslidedown($pid,$level_info['slide_down_levelid'],$level_info['slide_down_team'],$mid);
            if(!$slide_member){
                return ['status'=>0,'msg'=>'滑落失败，未查找到下级会员'];
            }
            //更改下级的推荐人
            $updatem = ['id'=>$mid,'pid'=>$slide_member['id'],'change_pid_time'=>time(),'is_slide'=>1];
            if(!$downmember['pid_origin']){
                $updatem['pid_origin'] = $downmember['pid'];
                $updatem['path_origin'] = $downmember['path'];
            }
            \app\models\Member::edit($aid,$updatem);//todo
            if(getcustom('network_slide_down_max')){
                Db::name('member')->where('id',$slide_member['id'])->inc('slide_num',1)->update();
            }
            $insertLog = ['aid'=>$aid,'mid'=>$downmember['id'],'pid'=>$slide_member['id'],'createtime'=>time()];
            if($downmember['pid_origin']){
                $insertLog['pid_origin'] = $downmember['pid_origin'];
                $insertLog['path_origin'] = $downmember['path_origin'];
            }else{
                $insertLog['pid_origin'] = $downmember['pid'];
                $insertLog['path_origin'] = $downmember['path'];
            }
            $insertLog['remark'] = '公排网滑落';
            Db::name('member_pid_changelog')->insert($insertLog);
            //滑落后触发上级升级
            self::uplv($aid,$slide_member['id']);
            return ['status'=>1,'msg'=>'滑落成功'];
        }
    }
    //获取滑落给予下级会员
    public static function getslidedown($pid,$levelids,$slide_down_team=0,$mid=0){
        if(getcustom('network_slide')){
            $levelids = explode(',',$levelids);
            foreach($levelids as $levelid){
                $slide_down_max = 0;
                if(getcustom('network_slide_down_max')){
                    $slide_down_max = Db::name('member_level')->where('id',$levelid)->value('slide_down_max');
                }
                if($slide_down_team==2){
                    //仅滑落给未脱离的直推或间推,优先找直推
                    $where = [];
                    $where[] = ['pid','=',$pid];
                    $where[] = ['levelid','=',$levelid];
                    $where[] = ['id','<>',$mid];
                    if($slide_down_max>0){
                        $where[] = ['slide_num','<',$slide_down_max];
                    }
                    $member = Db::name('member')->where($where)->find();
                    if($member){
                        return $member;
                    }
                    //直推不符合条件找间推
                    $pid2 = Db::name('member')->where('pid',$pid)
                        ->column('id');
                    if(!$pid2){
                        return [];
                    }
                    $where = [];
                    $where[] = ['pid','in',$pid2];
                    $where[] = ['levelid','=',$levelid];
                    $where[] = ['id','<>',$mid];
                    if($slide_down_max>0){
                        $where[] = ['slide_num','<',$slide_down_max];
                    }
                    $member = Db::name('member')->where($where)->find();
                    if($member){
                        return $member;
                    }else{
                        return [];
                    }
                }
                //查找链动脱离的人
                if($slide_down_team!=2){
                    $where = [];
                    $where[] = ['pid_origin','=',$pid];
                    $where[] = ['levelid','=',$levelid];
                    $where[] = ['is_slide','=',0];
                    $where[] = ['id','<>',$mid];
                    if($slide_down_max>0){
                        $where[] = ['slide_num','<',$slide_down_max];
                    }
                    $member = Db::name('member')->where($where)->find();
                    if($member){
                        return $member;
                    }
                    if($slide_down_team==1){
                        //仅滑落链动脱离的人
                        return [];
                    }
                }

                //查找自己直推的人
                $where = [];
                $where[] = ['levelid','=',$levelid];
                $where[] = ['is_slide','=',0];
                $where[] = ['id','<>',$mid];
                if($slide_down_max>0){
                    $where[] = ['slide_num','<',$slide_down_max];
                }
                $member = Db::name('member')->where('pid',$pid)
                    ->where('pid_origin=0 or pid_origin is null')
                    ->where($where)->find();
                if($member){
                    return $member;
                }
                //查找链动裂变过来的人
                $where = [];
                $where[] = ['pid','=',$pid];
                $where[] = ['pid_origin','>',0];
                $where[] = ['levelid','=',$levelid];
                $where[] = ['is_slide','=',0];
                $where[] = ['id','<>',$mid];
                if($slide_down_max>0){
                    $where[] = ['slide_num','<',$slide_down_max];
                }
                $member = Db::name('member')->where($where)->find();
                if($member){
                    return $member;
                }
                //查找公排滑落下来的人
                $where = [];
                $where[] = ['pid','=',$pid];
                $where[] = ['pid_origin','>',0];
                $where[] = ['levelid','=',$levelid];
                $where[] = ['is_slide','=',1];
                $where[] = ['id','<>',$mid];
                if($slide_down_max>0){
                    $where[] = ['slide_num','<',$slide_down_max];
                }
                $member = Db::name('member')->where($where)->find();
                if($member){
                    return $member;
                }
            }
            return [];
        }
    }
     public static function membercard_jiangli($aid,$mid,$membercard){
		if(getcustom('membercard_custom')){
			if($membercard['givemoney']>0){
				\app\commons\Member::addmoney($aid,$mid,$membercard['givemoney'],'会员卡开卡赠送');
			}
			if($membercard['givescore']>0){
				\app\commons\Member::addscore($aid,$mid,$membercard['givescore'],'会员卡开卡赠送');
			}
			if($membercard['coupon_ids']){
				$coupon_ids = explode(',',$membercard['coupon_ids']);
				$couponids = '';
				foreach ($coupon_ids as $couponid){
					//查看是否发放完成 本次活动优惠券已派送完毕
					$coupon = Db::name('coupon')->where('aid',$aid)->where('id',$couponid)->find();
					if($coupon['stock']<=0){
						$couponids.= $couponid;
						continue;
					}
					\app\commons\Coupon::send($aid,$mid,$couponid);
				}
			}
			//查询推荐人
			$member = Db::name('member')->field('id,pid,nickname,headimg')->where('id',$mid)->find();
			//赠送上级
			if($member['pid']){
				$pmember = Db::name('member')->where('id',$member['pid'])->find();
				if($pmember){
					$share = Db::name('membercard_sharelog')->where('aid',$aid)->where('mid',$mid)->where('pid',$member['pid'])->where('card_id',$membercard['card_id'])->find();
					if(!$share){

						if($membercard['parent_givemoney']>0){
							\app\commons\Member::addmoney($aid,$member['pid'],$membercard['parent_givemoney'],'推荐开卡赠送');
						}
						if($membercard['parent_givescore']>0){
							\app\commons\Member::addscore($aid,$member['pid'],$membercard['parent_givescore'],'推荐开卡赠送');
						}
						if($membercard['parent_coupon_ids']){
							$coupon_ids = explode(',',$membercard['parent_coupon_ids']);
							$couponids = '';
							$count = 0;
							foreach ($coupon_ids as $couponid){
								//查看是否发放完成 本次活动优惠券已派送完毕
								$coupon = Db::name('coupon')->where('aid',$aid)->where('id',$couponid)->find();
								if($coupon['stock']<=0){
									$couponids.= $couponid;
									continue;
								}
								$count++;
								\app\commons\Coupon::send($aid,$member['pid'],$couponid);
							}
						}
						$record = Db::name('membercard_record')->where('aid',$aid)->where('mid',$member['pid'])->where('card_id',$membercard['card_id'])->find();
						$data = [];
						$data['totalmoney'] = $record['totalmoney']+$membercard['parent_givemoney'];
						$data['totalscore'] = $record['totalscore']+$membercard['parent_givescore'];
						$data['totalcoupon'] = $record['totalcoupon']+$count;
						Db::name('membercard_record')->where('aid',$aid)->where('mid',$member['pid'])->where('card_id',$membercard['card_id'])->update($data);
						$log = [];
						$log['aid'] = $aid;
						$log['mid'] = $mid;
						$log['record_id'] = $record['id'];
						$log['createtime'] = time();
						$log['title'] = $membercard['title'];
						$log['givemoney'] = $membercard['givemoney'];
						$log['givescore'] = $membercard['givescore'];
						$log['give_couponids'] = $membercard['coupon_ids'];
						$log['pid'] = $member['pid'];
						if($member['pid']>0){
							$log['parent_givemoney'] = $membercard['parent_givemoney'];
							$log['parent_givescore'] = $membercard['parent_givescore'];
							$log['parent_couponids'] = $membercard['parent_coupon_ids'];
							$log['card_id'] = $membercard['card_id'];
						}
						$log['nickname'] = $member['nickname'];
						$log['headimg'] = $member['headimg'];
						Db::name('membercard_sharelog')->insert($log);
					}
				}
			}
		}
	}
    //排名分红记录
    public static function addpaimingfenhong($aid,$mid,$value,$remark,$type=0,$orderid=0){
        if(getcustom('shop_paiming_fenhong')){
            if($value==0) return ;
            $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->lock(true)->find();
            if(!$member) return ['status'=>0,'msg'=>t('会员').'不存在'];
            $after = $member['paiming_fenhong_money'] + $value;
            Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['paiming_fenhong_money'=>$after]);
            

            $data = [];
            $data['aid'] = $aid;
            $data['mid'] = $mid;
            $data['money'] = $value;
            $data['after'] = $after;
            $data['createtime'] = time();
            $data['remark'] = $remark;
            $data['type'] = $type;
            $data['orderid'] = $orderid;
            Db::name('member_paiming_fenhong_log')->insert($data);
            return ['status'=>1,'msg'=>''];
        }
    }

    //增加会员佣金上限
    //$in_type 1平台修改 0自己获得
    public static function addcommissionmax($aid,$mid,$value,$remark,$channel='',$orderid=0,$in_type=0){
        $add_commission_max = getcustom('add_commission_max',$aid)?:0;
        if(getcustom('member_commission_max')){
            if($value==0) return ;
            $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->lock(true)->find();
            if(!$member) return ['status'=>0,'msg'=>t('会员').'不存在'];

            if($value < 0 && $member['commission_max'] < $value*-1) return ['status'=>0,'msg'=>t('佣金上限').'不足'];

            $after = $member['commission_max'] + $value;
            Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['commission_max'=>$after]);

            $data = [];
            $data['aid'] = $aid;
            $data['mid'] = $mid;
            $data['value'] = $value;
            $data['after'] = $after;
            $data['createtime'] = time();
            $data['remark'] = $remark;
            $data['channel'] = $channel;
            $data['orderid'] = $orderid;
            if($add_commission_max){
                $data['in_type'] = $in_type?:0;
                if($value>0){
                    //增加佣金上限时根据选择的类型
                    if($in_type==1){
                        Db::name('member')->where('aid',$aid)->where('id',$mid)->inc('commission_max_plate',$value)->update();
                    }else{
                        Db::name('member')->where('aid',$aid)->where('id',$mid)->inc('commission_max_self',$value)->update();
                    }
                }else{
                    //减少佣金上限时优先减会员自己的佣金上限
                    $abs_value = abs($value);
                    $dec_self = $abs_value;
                    $dec_plate = 0;
                    if($abs_value>$member['commission_max_self']){
                        $dec_self = $member['commission_max_self'];
                        $dec_plate = bcsub($abs_value , $dec_self,2);
                    }
                    Db::name('member')->where('aid',$aid)->where('id',$mid)->dec('commission_max_self',$dec_self)->update();
                    Db::name('member')->where('aid',$aid)->where('id',$mid)->dec('commission_max_plate',$dec_plate)->update();
                }
            }
            Db::name('member_commissionmax_log')->insert($data);
            return ['status'=>1,'msg'=>''];
        }
    }
 //增加绿色积分
    public static function addgreenscore($aid,$mid,$value,$remark,$channel='',$orderid=0,$no_cal_price=0,$maximum_num=0,$jilu_id=0){
        if(getcustom('consumer_value_add',$aid)){
            if($value==0) return ;
            $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->lock(true)->find();
            if(!$member) return ['status'=>0,'msg'=>t('会员').'不存在'];


            if($value < 0 && $member['green_score'] < $value*-1) return ['status'=>0,'msg'=>t('绿色积分').'不足'];

            $set = Db::name('consumer_set')->where('aid',$aid)->find();
            if($value < 0 && $set['green_score_total'] < $value*-1) return ['status'=>0,'msg'=>t('绿色积分').'不足'];

            $after = $member['green_score'] + $value;
            Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['green_score'=>$after]);
            $green_score_total = bcadd($set['green_score_total'],$value,2);
            if(!$no_cal_price){
                if($green_score_total>0){
                    $green_price = bcdiv($set['bonus_pool_total'],$green_score_total,4);
                    if($green_price<$set['min_price']){
                        $green_price = $set['min_price'];
                    }
                }else{
                    $green_price = $set['min_price'];
                }
            }else{
                $green_price = $set['green_score_price']?:$set['min_price'];
            }
            //更新绿色积分单笔明细
            if(getcustom('greenscore_max',$aid)){
                if(!$maximum_num && $value>0){
                    $maximum_num = bcmul(bcmul($value,$set['green_score_price'],2),$set['maximum_set'],2);
                }
            }
            $res = \app\customs\GreenScore::greenscore_jilu($aid,$mid,$value,$remark,$orderid,$jilu_id,$maximum_num,$green_price);
            if($res['status']){
                $jilu_datas = $res['jilu_data'];
            }
            $new_after = $member['green_score'];
            foreach($jilu_datas as $jilu_data){
                $new_after = bcadd($new_after,$jilu_data['value'],2);
                $data = [];
                $data['aid'] = $aid;
                $data['mid'] = $mid;
                $data['value'] = $jilu_data['value'];
                $data['after'] = $new_after;
                $data['green_score_price'] = $green_price;
                $data['createtime'] = time();
                $data['remark'] = $remark;
                $data['channel'] = $channel;
                $data['orderid'] = $orderid;
                $data['remain'] = $jilu_data['value']>0?$jilu_data['value']:0;
                if(getcustom('greenscore_max',$aid) && $jilu_data['value']>0){
                    //封顶额度
                    if(!$maximum_num){
                        $maximum_num = bcmul(bcmul($jilu_data['value'],$set['green_score_price'],2),$set['maximum_set'],2);
                    }
                    $data['maximum_num'] = $maximum_num??0;
                    self::addmaximum($aid,$mid,$maximum_num,$remark,$channel,$orderid);
                }

                $data['jilu_id'] = $jilu_data['jilu_id'];
                Db::name('member_greenscore_log')->insert($data);
            }

            Db::name('consumer_set')->where('aid',$aid)->inc('green_score_total',$value)->update(['green_score_price'=>$green_price]);
            if(!$no_cal_price){
                $set = Db::name('consumer_set')->where('aid',$aid)->find();
                \app\customs\GreenScore::autoWithdraw($aid,$set);
            }
            return ['status'=>1,'msg'=>''];
        }
    }
    //增加封顶额度
    public static function addmaximum($aid,$mid,$value,$remark,$channel='',$orderid=0,$no_cal_price=0,$log_id=0){
        if(getcustom('consumer_value_add',$aid)){
            if($value==0) return ;
            $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->lock(true)->find();
            if(!$member) return ['status'=>0,'msg'=>t('会员').'不存在'];
            if($value < 0 && $member['maximum'] < $value*-1) return ['status'=>0,'msg'=>t('绿色积分').'不足'];

            $after = $member['maximum'] + $value;
            Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['maximum'=>$after]);
            $data = [];
            $data['aid'] = $aid;
            $data['mid'] = $mid;
            $data['value'] = $value;
            $data['after'] = $after;
            $data['createtime'] = time();
            $data['remark'] = $remark;
            $data['channel'] = $channel;
            $data['orderid'] = $orderid;
            $data['remain'] = $value>0?$value:0;
            Db::name('member_maximum_log')->insert($data);
            if($value<0){
                if($log_id){
                    Db::name('member_greenscore_jilu')->where('id',$log_id)->inc('dec_maximum',abs($value))->update();
                }else{
                    $where = [];
                    $where['aid'] = $aid;
                    $where['mid'] = $mid;
                    //根据记录顺序扣除记录中的剩余积分，不足的顺延扣除下一条
                    $log_list = Db::name('member_greenscore_jilu')->where($where)->where('(maximum_num-dec_maximum)','>',0)->order('id asc')->select()->toArray();
                    //根据记录顺序扣除记录中的封顶额度，不足的顺延扣除下一条
                    $money = abs($value);
                    foreach($log_list as $log){
                        $remain_maximum = bcsub($log['maximum_num'],$log['dec_maximum'],2);
                        if($money<=$remain_maximum){
                            //直接减掉
                            Db::name('member_greenscore_jilu')->where('id',$log['id'])
                                ->inc('dec_maximum',$money)
                                ->update();
                        }else{
                            Db::name('member_greenscore_jilu')->where('id',$log['id'])->inc('dec_maximum',$remain_maximum)->update();
                        }
                        $money = bcsub($money,$remain_maximum,2);
                        if($money<=0){
                            break;
                        }
                    }
                }
            }
            return ['status'=>1,'msg'=>''];
        }
    }
    //增加激活币
    public static function addactivecoin($aid,$mid,$value,$remark,$channel='',$orderid=0){
        if(getcustom('active_coin',$aid)){
            if($value==0) return ;
            $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->lock(true)->find();
            if(!$member) return ['status'=>0,'msg'=>t('会员').'不存在'];
            if($value < 0 && $member['active_coin'] < $value*-1) return ['status'=>0,'msg'=>t('激活币').'不足'];
            if($value < 0 && $member['active_coin'] < $value*-1) return ['status'=>0,'msg'=>t('激活币').'不足'];
            $after = $member['active_coin'] + $value;
            Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['active_coin'=>$after]);
            $data = [];
            $data['aid'] = $aid;
            $data['mid'] = $mid;
            $data['value'] = $value;
            $data['after'] = $after;
            $data['createtime'] = time();
            $data['remark'] = $remark;
            $data['channel'] = $channel;
            $data['orderid'] = $orderid;
            Db::name('member_activecoin_log')->insert($data);
            //自动下单
            //$remain = bcsub($after,$member['active_coin_order'],1);
            $remain = $after;
            $coin_set = Db::name('active_coin_set')->where('aid',$aid)->find();
            if($remain>=$coin_set['auto_order_num']){
                $num = intval(floor(bcdiv($remain,$coin_set['auto_order_num'],2)));
                if($num<=0){
                    $num = $num+1;
                }
                //自动下单产品id
                $auto_order_proids = $coin_set['auto_order_proids'];
                $can_buy_proids = [];//可以购买的产品
                if($auto_order_proids){
                    $auto_order_proids = explode(',',$auto_order_proids);
                    $products = Db::name('shop_product')->where('id','in',$auto_order_proids)->select()->toArray();
                    foreach($products as $k=>$v){
                        $gettj = explode(',',$v['gettj']);
                        if(!$gettj || in_array('-1',$gettj) || in_array($member['levelid'],$gettj)){
                            $can_buy_proids[] = $v['id'];
                        }
                    }
                }
                if($can_buy_proids){
                    $pro_id = $can_buy_proids[array_rand($can_buy_proids)];
                    $res = self::autoOrder($aid,$mid,$pro_id,$num);
                    if($res['status']){
                        $value = bcmul($coin_set['auto_order_num'],$num,2);
                        $after = bcsub($after,$value,2);
                        $data = [];
                        $data['aid'] = $aid;
                        $data['mid'] = $mid;
                        $data['value'] = -$value;
                        $data['after'] = $after;
                        $data['createtime'] = time();
                        $data['remark'] = '自动下单扣除';
                        $data['channel'] = $channel;
                        $data['orderid'] = $res['oid'];
                        Db::name('member_activecoin_log')->insert($data);
                        Db::name('member')->where('aid',$aid)->where('id',$mid)->inc('active_coin_order',$coin_set['auto_order_num']*$num)->update(['active_coin'=>$after]);
                    }
                }
            }
            return ['status'=>1,'msg'=>''];
        }
    }
    public static function autoOrder($aid,$mid,$pro_id,$num=1){
        if(getcustom('active_coin',$aid)) {
            $product = Db::name('shop_product')->where('id', $pro_id)->find();
            $guige = Db::name('shop_guige')->where('proid', $pro_id)->find();
            $sysset = Db::name('admin_set')->where('aid', $aid)->find();
            if (getcustom('consumer_value_add')) {
                $give_green_score = 0; //奖励绿色积分 确认收货后赠送
                $give_green_score2 = 0; //奖励绿色积分 付款后赠送
                $give_bonus_pool = 0; //奖励绿色积分 确认收货后赠送
                $give_bonus_pool2 = 0; //奖励绿色积分 付款后赠送
                $consumer_set = Db::name('consumer_set')->where('aid', $aid)->find();
                $green_score_price = $consumer_set['green_score_price'] > $consumer_set['min_price'] ? $consumer_set['green_score_price'] : $consumer_set['min_price'];
            }
            if (getcustom('consumer_value_add',$aid)) {
                $can_give_green_score = 1;
                if($consumer_set['fwtype']==2){//指定商品可用
                    $productids = explode(',',$consumer_set['productids']);
                    if(!in_array($product['id'],$productids)){
                        $can_give_green_score = 0;
                    }
                }

                if($consumer_set['fwtype']==1){//指定类目可用
                    $categoryids = explode(',',$consumer_set['categoryids']);
                    $cids = explode(',',$product['cid']);
                    $clist = Db::name('shop_category')->where('pid','in',$categoryids)->select()->toArray();
                    foreach($clist as $vc){
                        $categoryids[] = $vc['id'];
                        $cate2 = Db::name('shop_category')->where('pid',$vc['id'])->find();
                        $categoryids[] = $cate2['id'];
                    }
                    if(!array_intersect($cids,$categoryids)){
                        $can_give_green_score = 0;
                    }
                }
                if($can_give_green_score){
                    if ($guige['give_green_score'] <= 0) {
                        //$guige['give_green_score'] = bcmul($guige['sell_price'],$consumer_set['green_score_bili']/100,2);
                        $guige['give_green_score'] = bcdiv(bcmul($guige['sell_price'], $consumer_set['green_score_bili'] / 100, 4), $green_score_price, 2);
                    } else {
                        $guige['give_green_score'] = bcdiv($guige['give_green_score'], $green_score_price, 2);
                    }
                    if ($guige['give_bonus_pool'] <= 0) {
                        $guige['give_bonus_pool'] = bcmul($guige['sell_price'], $consumer_set['bonus_pool_bili'] / 100, 2);
                    }
                    if ($consumer_set['reward_time'] == 0) {
                        $give_green_score += $guige['give_green_score'] * $num; //奖励绿色积分 确认收货后赠送
                        $give_bonus_pool += $guige['give_bonus_pool'] * $num; //放入奖金池 确认收货后赠送
                    } else {
                        $give_green_score2 += $guige['give_green_score'] * $num; //奖励绿色积分 确认收货后赠送
                        $give_bonus_pool2 += $guige['give_bonus_pool'] * $num; //放入奖金池 确认收货后赠送
                    }
                }
            }

            $ordernum = \app\commons\Common::generateOrderNo($aid);
            $orderdata = [];
            $orderdata['aid'] = $aid;
            $orderdata['mid'] = $mid;
            $orderdata['bid'] = $product['bid'] ?: 0;
            $orderdata['ordernum'] = $ordernum;
            $orderdata['title'] = $product['name'];

            $address = Db::name('member_address')->where('mid', $mid)->order('isdefault desc')->find();
            $orderdata['linkman'] = $address['name'];
            $orderdata['tel'] = $address['tel'];
            $orderdata['area'] = $address['province'] . $address['city'] . $address['district'];;
            $orderdata['area2'] = $address['province'] ? $address['province'] . ',' . $address['city'] . ',' . $address['district'] : '';
            $orderdata['address'] = $address['address'];
            $orderdata['totalprice'] = $product['sell_price'] * $num;
            $orderdata['product_price'] = $product['sell_price'];
            $orderdata['leveldk_money'] = 0;  //会员折扣
            $orderdata['scoredk_money'] = 0;    //积分抵扣
            $orderdata['scoredkscore'] = 0;    //抵扣掉的积分
            $orderdata['freight_price'] = 0; //运费
            $orderdata['message'] = '';
            $orderdata['freight_text'] = '';
            $orderdata['freight_id'] = '';
            $orderdata['freight_type'] = 1;
            $orderdata['mdid'] = 0;
            $orderdata['platform'] = 'admin';
            $orderdata['hexiao_code'] = random(16);
            $orderdata['hexiao_qr'] = createqrcode(m_url('admin/hexiao/hexiao?type=shop&co=' . $orderdata['hexiao_code']));
            $orderdata['status'] = 1;
            $orderdata['paytype'] = 0;
            $orderdata['createtime'] = time();
            $orderdata['paytime'] = time();
            $remark = t('激活币') . '自动下单';

            $orderdata['remark'] = $remark;
            $orderdata['givescore'] = 0;
            $orderdata['givescore2'] = 0;
            if (getcustom('consumer_value_add')) {
                $orderdata['give_green_score'] = $give_green_score;
                $orderdata['give_bonus_pool'] = $give_bonus_pool;
                $orderdata['give_green_score2'] = $give_green_score2;
                $orderdata['give_bonus_pool2'] = $give_bonus_pool2;
            }
            $orderid = Db::name('shop_order')->insertGetId($orderdata);
            $ogdata = [];
            $ogdata['aid'] = $aid;
            $ogdata['bid'] = $product['bid'];
            $ogdata['mid'] = $mid;
            $ogdata['orderid'] = $orderid;
            $ogdata['ordernum'] = $orderdata['ordernum'];
            $ogdata['proid'] = $product['id'];
            $ogdata['name'] = $product['name'];
            $ogdata['pic'] = $guige['pic'] ? $guige['pic'] : $product['pic'];
            $ogdata['procode'] = $product['procode'];
            $ogdata['barcode'] = $product['barcode'];
            $ogdata['ggid'] = $guige['id'];
            $ogdata['ggname'] = $guige['name'];
            $ogdata['cid'] = $product['cid'];
            $ogdata['num'] = $num;
            $ogdata['cost_price'] = $guige['cost_price'];
            $ogdata['sell_price'] = $guige['sell_price'];
            $ogdata['totalprice'] = $num * $guige['sell_price'];
            $ogdata['real_totalprice'] = $ogdata['totalprice'];
            $ogdata['status'] = 1;
            $ogdata['createtime'] = time();
            if (getcustom('consumer_value_add',$aid)) {
                $can_give_green_score = 1;
                if($consumer_set['fwtype']==2){//指定商品可用
                    $productids = explode(',',$consumer_set['productids']);
                    if(!in_array($product['id'],$productids)){
                        $can_give_green_score = 0;
                    }
                }

                if($consumer_set['fwtype']==1){//指定类目可用
                    $categoryids = explode(',',$consumer_set['categoryids']);
                    $cids = explode(',',$product['cid']);
                    $clist = Db::name('shop_category')->where('pid','in',$categoryids)->select()->toArray();
                    foreach($clist as $vc){
                        $categoryids[] = $vc['id'];
                        $cate2 = Db::name('shop_category')->where('pid',$vc['id'])->find();
                        $categoryids[] = $cate2['id'];
                    }
                    if(!array_intersect($cids,$categoryids)){
                        $can_give_green_score = 0;
                    }
                }
                if($can_give_green_score){
                    if ($guige['give_green_score'] <= 0) {
                        //$guige['give_green_score'] = bcdiv(bcmul($guige['sell_price'],$consumer_set['green_score_bili']/100,4),$consumer_set['green_score_price'],2);

                        $guige['give_green_score'] = bcdiv(bcmul($guige['sell_price'], $consumer_set['green_score_bili'] / 100, 4), $green_score_price, 2);
                    } else {
                        $guige['give_green_score'] = bcdiv($guige['give_green_score'], $green_score_price, 2);
                    }
                    if ($guige['give_bonus_pool'] <= 0) {
                        $guige['give_bonus_pool'] = bcmul($guige['sell_price'], $consumer_set['bonus_pool_bili'] / 100, 2);
                    }
                    if ($consumer_set['reward_time'] == 0) {
                        $ogdata['give_green_score'] = $guige['give_green_score']; //奖励绿色积分 确认收货后赠送
                        $ogdata['give_bonus_pool'] = $guige['give_bonus_pool']; //放入奖金池 确认收货后赠送
                    } else {
                        $ogdata['give_green_score2'] = $guige['give_green_score']; //奖励绿色积分 确认收货后赠送
                        $ogdata['give_bonus_pool2'] = $guige['give_bonus_pool']; //放入奖金池 确认收货后赠送
                    }
                }
            }
            if ($product['fenhongset'] == 0) { //不参与分红
                $ogdata['isfenhong'] = 2;
            }
            $ogid = Db::name('shop_order_goods')->insertGetId($ogdata);

            //分销数据
            //计算佣金的商品金额
            $commission_totalprice = $ogdata['totalprice'];
            if($sysset['fxjiesuantype']==1){ //按成交价格
                $commission_totalprice = $ogdata['totalprice'];
            }
            if($sysset['fxjiesuantype']==2){ //按销售利润
                $commission_totalprice = $ogdata['totalprice'] - $guige['cost_price'] * $num;
            }
            if($commission_totalprice < 0) $commission_totalprice = 0;
            $istc1 = 0; //设置了按单固定提成时 只将佣金计算到第一个商品里
            $istc2 = 0;
            $istc3 = 0;
            $isfg  = 0;
            $member = Db::name('member')->where('id',$mid)->find();
            if(!getcustom('fenxiao_manage',$aid)){
                $sysset['fenxiao_manage_status'] = 0;
            }
            if($sysset['fenxiao_manage_status']){
                $commission_data = \app\commons\Fenxiao::fenxiao_jicha($sysset,$member,$product,$num,$commission_totalprice);
            }else{
                $commission_data = \app\commons\Fenxiao::fenxiao($sysset,$member,$product,$num,$commission_totalprice,$isfg,$istc1,$istc2,$istc3);
            }
            $ogupdate = [];
            $ogupdate['parent1'] = $commission_data['parent1']??0;
            $ogupdate['parent2'] = $commission_data['parent2']??0;
            $ogupdate['parent3'] = $commission_data['parent3']??0;
            $ogupdate['parent4'] = $commission_data['parent4']??0;
            $ogupdate['parent1commission'] = $commission_data['parent1commission']??0;
            $ogupdate['parent2commission'] = $commission_data['parent2commission']??0;
            $ogupdate['parent3commission'] = $commission_data['parent3commission']??0;
            $ogupdate['parent4commission'] = $commission_data['parent4commission']??0;
            $ogupdate['parent1score'] = $commission_data['parent1score']??0;
            $ogupdate['parent2score'] = $commission_data['parent2score']??0;
            $ogupdate['parent3score'] = $commission_data['parent3score']??0;
            $istc1 = $commission_data['istc1']??0;
            $istc2 = $commission_data['istc2']??0;
            $istc3 = $commission_data['istc3']??0;
            if($ogupdate){
                Db::name('shop_order_goods')->where('id',$ogid)->update($ogupdate);
            }

            if($product['commissionset']!=4){
                if($ogupdate['parent1'] && ($ogupdate['parent1commission']>0 || $ogupdate['parent1score']>0)){
                    $data_c = ['aid'=>$aid,'mid'=>$ogupdate['parent1'],'frommid'=>$mid,'orderid'=>$orderid,'ogid'=>$ogid,'type'=>'shop','commission'=>$ogupdate['parent1commission'],'score'=>$ogupdate['parent1score'],'remark'=>t('下级').'购买商品奖励','createtime'=>time()];
                    Db::name('member_commission_record')->insert($data_c);
                }
                if($ogupdate['parent2'] && ($ogupdate['parent2commission']>0 || $ogupdate['parent2score']>0)){
                    $data_c = ['aid'=>$aid,'mid'=>$ogupdate['parent2'],'frommid'=>$mid,'orderid'=>$orderid,'ogid'=>$ogid,'type'=>'shop','commission'=>$ogupdate['parent2commission'],'score'=>$ogupdate['parent2score'],'remark'=>t('下二级').'购买商品奖励','createtime'=>time()];
                    Db::name('member_commission_record')->insert($data_c);
                }
                if($ogupdate['parent3'] && ($ogupdate['parent3commission']>0 || $ogupdate['parent3score']>0)){
                    $data_c = ['aid'=>$aid,'mid'=>$ogupdate['parent3'],'frommid'=>$mid,'orderid'=>$orderid,'ogid'=>$ogid,'type'=>'shop','commission'=>$ogupdate['parent3commission'],'score'=>$ogupdate['parent3score'],'remark'=>t('下三级').'购买商品奖励','createtime'=>time()];
                    Db::name('member_commission_record')->insert($data_c);
                }
                if($ogupdate['parent4'] && ($ogupdate['parent4commission']>0)){
                    $remark = '持续推荐奖励';
                    if(getcustom('commission_parent_pj_stop',$aid)){
                        $remark = '平级奖';
                    }
                    Db::name('member_commission_record')->insert(['aid'=>$aid,'mid'=>$ogupdate['parent4'],'frommid'=>$mid,'orderid'=>$orderid,'ogid'=>$ogid,'type'=>'shop','commission'=>$ogupdate['parent4commission'],'score'=>0,'remark'=>$remark,'createtime'=>time()]);
                }
            }

            Db::name('shop_guige')->where('aid', $aid)->where('id', $guige['id'])->update(['stock' => Db::raw("stock-$num"), 'sales' => Db::raw("sales+$num")]);
            Db::name('shop_product')->where('aid', $aid)->where('id', $product['id'])->update(['stock' => Db::raw("stock-$num"), 'sales' => Db::raw("sales+$num")]);
            \app\models\Payorder::shop_pay($orderid);
            return ['status' => true, 'oid' => $orderid];
        }
    }
    //增加奖金池
    public static function addbonuspool($aid,$mid,$value,$remark,$channel='',$orderid=0,$no_cal_price=0,$green_score=0){
        if(getcustom('consumer_value_add',$aid)){
            if($value==0) return ;
            $set = Db::name('consumer_set')->where('aid',$aid)->find();

            if($value < 0 && $set['bonus_pool_total'] < $value*-1) return ['status'=>0,'msg'=>t('奖金池').'不足'];

            $after = $set['bonus_pool_total'] + $value;
            if(!$no_cal_price) {
                if ($set['green_score_total'] > 0) {
                    $green_price = bcdiv($after, $set['green_score_total'], 4);
                    if ($green_price < $set['min_price']) {
                        $green_price = $set['min_price'];
                    }
                } else {
                    $green_price = $set['min_price'];
                }
            }else{
                $green_price = $set['green_score_price'];
            }
            $dif_price = bcsub($green_price,$set['green_score_price'],4);
            Db::name('consumer_set')->where('aid',$aid)->inc('bonus_pool_total',$value)->update(['green_score_price'=>$green_price]);

            $data = [];
            $data['aid'] = $aid;
            $data['mid'] = $mid;
            $data['value'] = $value;
            $data['after'] = $after;
            $data['green_score_price'] = $green_price;
            $data['dif_price'] = $dif_price;
            $data['createtime'] = time();
            $data['remark'] = $remark;
            $data['channel'] = $channel;
            $data['orderid'] = $orderid;
            if(getcustom('greenscore_max',$aid)){
                $data['green_score'] = $green_score;
                $data['green_score_total'] = $set['green_score_total'];
            }
            Db::name('admin_bonuspool_log')->insert($data);
            return ['status'=>1,'msg'=>''];
        }
    }
    
    //独立排队返利
    public static function duliQueue($aid,$member,$level_data){
        if(getcustom('yx_queue_duli_queue',$aid)){
            //1、当前等级是否开启独立排队 ,且没有进行的独立排队
            $queue_fee = Db::name('queue_free')->where('aid',$aid)->where('status',0)->where('teamid',$member['id'])->find();
            $duli_levelid = Db::name('queue_free_set')->where('aid',$aid)->where('bid',0)->value('duli_queue_levelid');
            $duli_queue_levelid = $duli_levelid?explode(',',$duli_levelid):[];
            Log::write([
                'file'=>__FILE__,
                'line'=>__LINE__,
                'msg'=>'升级独立排队,已存在',
                '$queue_fee'=>$queue_fee ,
                '$duli_queue_levelid' => $duli_queue_levelid
            ]);
            if(in_array($member['levelid'],$duli_queue_levelid) && !$queue_fee){
                //2、查找是否排队，如果排队自己排在队伍最前 ，查找下级，把伞下进行重新独立排序
                $downmids = \app\commons\Member::getdownmids($aid,$member['id']);
                $downmids[] = $member['id'];//包含自己的排队
                Log::write([
                    'file'=>__FILE__,
                    'line'=>__LINE__,
                    'msg'=>'升级独立排队',
                    '$downmids'=>$downmids
                ]);
                $child_queue = [];
                if($downmids){
                    $child_queue=Db::name('queue_free')->where('aid',$aid)->where('status',0)->where('mid','in',$downmids)->order('queue_no asc,id asc')->select()->toArray();
                    //如果downmids中的会员存在独立排队，他的伞不再加入当前独立排队，
                    foreach($child_queue as $ck=>$cv){
                        if($cv['teamid'] > 0 && in_array($cv['teamid'],$downmids)){
                               unset($child_queue[$ck]);
                        }
                    }
                }
                Log::write([
                    'file'=>__FILE__,
                    'line'=>__LINE__,
                    'msg'=>'升级独立排队$child_queue',
                    '$child_queue'=>$child_queue
                ]);
                //如果自身存在
                $queue_no = 1;
                if($child_queue){
                   foreach($child_queue as $key=>$val){
                       Db::name('queue_free')->where('id',$val['id'])->update(['queue_no' => $queue_no,'teamid' => $member['id']]);
                       $queue_no +=1;
                   } 
                }
            }
        }
    }

    //增加服务费
    public static function addServiceFee($aid,$mid,$serviceFee,$remark=''){
        if(getcustom('product_service_fee',$aid)) {
            if($serviceFee==0) return ;
            $serviceFee = round($serviceFee,2);
            $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->lock(true)->find();
            if(!$member) return ['status'=>0,'msg'=>t('会员').'不存在'];

            if($serviceFee < 0 && $member['service_fee'] < $serviceFee*-1) return ['status'=>0,'msg'=>t('服务费').'不足'];

            $after = $member['service_fee'] + $serviceFee;
            Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['service_fee'=>$after]);

            $data = [];
            $data['aid'] = $aid;
            $data['mid'] = $mid;
            $data['service_fee'] = $serviceFee;
            $data['after'] = $after;
            $data['createtime'] = time();
            $data['remark'] = $remark;
            $data['type'] = 1;
            Db::name('member_servicefee_log')->insert($data);
            return ['status'=>1,'msg'=>''];
        }
    }

    //释放绿色积分
    public static function release_green_score($set){
        if(getcustom('consumer_value_add', $set['aid'])) {
            $set_member_commission_max = 0;
            if (getcustom('member_commission_max', $set['aid'])) {
                //佣金上限
                $set_member_commission_max = Db::name('admin_set')->where('aid', $set['aid'])->value('member_commission_max');
            }
            if (getcustom('consumer_value_add', $set['aid'])) {
                $field = 'id,green_score';
                if ($set_member_commission_max) {
                    $field .= ',totalcommission,commission_max';
                }
                $member_lists = Db::name('member')->where('aid', $set['aid'])->where('green_score', '>', 0)->field($field)->select()->toArray();
                if (!$member_lists) {
                    return true;
                }
                $score_weishu = 0;
                if (getcustom('score_weishu', $set['aid'])) {
                    $score_weishu = Db::name('admin_set')->where('aid', $set['aid'])->value('score_weishu');
                }
                $green_score_price = $set['green_score_price'] > $set['min_price'] ? $set['green_score_price'] : $set['min_price'];
                foreach ($member_lists as $member) {
                    $score_total = $member['green_score'];
                    $money = bcmul($score_total, $green_score_price, 2);
                    if ($set_member_commission_max) {
                        //佣金上限
                        $m_total = $member['totalcommission'];
                        $member_commission_max = $member['commission_max'];
                        $commission_max = bcsub($member_commission_max, $m_total, 2);
                        if ($commission_max < $money) {
                            $money = $commission_max > 0 ? $commission_max : 0;
                        }
                    }
                    //提现分入三个钱包
                    $to_commission = bcmul($money, $set['to_commission'] / 100, 2);
                    $to_score = bcmul($money, $set['to_score'] / 100, $score_weishu);
                    $to_money = bcmul($money, $set['to_money'] / 100, 2);
                    if ($to_money > 0) {
                        $rs = \app\commons\Member::addmoney($set['aid'], $member['id'], $to_money, t('绿色积分', $set['aid']) . '自动释放');
                    }
                    if ($to_commission > 0) {
                        $rs = \app\commons\Member::addcommission($set['aid'], $member['id'], $member['id'], $to_commission, t('绿色积分', $set['aid']) . '自动释放');
                    }
                    if ($to_score > 0) {
                        $rs = \app\commons\Member::addscore($set['aid'], $member['id'], $to_score, t('绿色积分', $set['aid']) . '自动释放');
                    }

                    //扣除会员绿色积分
                    if ($rs['status'] == 1) {
                        \app\commons\Member::addgreenscore($set['aid'], $member['id'], $score_total * -1, t('绿色积分', $set['aid']) . '自动释放扣除', '', 0, 1);
                    }
                    if(getcustom('greenscore_max',$set['aid'])){
                        //扣除封顶额度
                        \app\commons\Member::addmaximum($set['aid'], $member['id'], $money * -1, t('绿色积分', $set['aid']) . '自动释放扣除', '', 0, 1);
                        //扣除会员已领红包数量
                        Db::name('member')->where('aid', $set['aid'])->where('id',$member['id'])->update(['green_score_hb'=>0]);
                    }
                    //插入提现记录表
                    $log = [];
                    $log['aid'] = $set['aid'];
                    $log['mid'] = $member['id'];
                    $log['money'] = $money;
                    $log['fee'] = 0;
                    $log['green_score'] = $score_total;
                    $log['to_commission'] = $to_commission;
                    $log['to_money'] = $to_money;
                    $log['to_score'] = $to_score;
                    $log['to_pool'] = 0;
                    $log['remark'] = '自动释放';
                    $log['createtime'] = time();
                    Db::name('greenscore_withdraw_log')->insert($log);
                }
                //扣除平台奖金池
                $rs = \app\commons\Member::addbonuspool($set['aid'], 0, -$set['bonus_pool_total'], t('绿色积分', $set['aid']) . '自动释放扣除', '', 0, 0);
                Db::name('consumer_set')->where('aid', $set['aid'])->update(['green_score_total' => 0, 'bonus_pool_total' => 0, 'green_score_price' => $set['min_price']]);
                return true;
            }
        }
    }
      //增加会员给下级升级数量
    public static function addMemberLevelupNum($aid,$mid,$team_levelup_data){
        if(getcustom('member_levelup_givechild',$aid)){
            $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->find();
            $where = [];
            $where[] = ['mid','=',$mid];
            $where[] = ['aid','=',$aid];
            
            if($team_levelup_data){
                $team_levelup_data = json_decode($team_levelup_data,true);
                foreach($team_levelup_data as $k=>$v){
                    $usenum = Db::name('member_levelup_uesnum')->where($where)->where('levelupid',$k)->find();
                    if($usenum){
                        $info = [];
                        $info['levelid'] = $member['levelid'];
                        $info['num'] = $v+$usenum['num'];
                        $res = Db::name('member_levelup_uesnum')->where('aid',$aid)->where('id',$usenum['id'])->update($info);
                    }else{
                        $info = [];
                        $info['aid'] = $aid;
                        $info['mid'] = $mid;
                        $info['levelid'] = $member['levelid'];
                        $info['levelupid'] = $k;
                        $info['num'] = $v;
                        $info['createtime'] = time();
                        $id = Db::name('member_levelup_uesnum')->insertGetId($info);
                    }
                }
            }
        }
    }

    public static function addstaffcommission($aid,$bid,$sid,$commission,$remark='',$params = []){
        if(getcustom('extend_staff',$aid)){
            //员工提成
            if($commission==0) return ;
            $staff = Db::name('staff')->where('id',$sid)->where('aid',$aid)->where('bid',$bid)->lock(true)->find();
            if(!$staff) return ['status'=>0,'msg'=>t('会员').'不存在'];

            $after = $staff['commission'] + $commission;
            Db::name('staff')->where('id',$sid)->update(['commission'=>$after]);

            $data = [];
            $data['aid'] = $aid;
            $data['bid'] = $bid??0;
            $data['sid'] = $sid;
            $data['commission'] = $commission;
            $data['after']      = $after;
            $data['remark']     = $remark;

            $data['commission_rate'] = $params && $params['commission_rate']?$params['commission_rate']:0;
            $data['orderid'] = $params && $params['orderid']?$params['orderid']:0;
            $data['type']    = $params && $params['type']?$params['type']:'';
            $data['uid']     = $params && $params['uid']?$params['uid']:0;
            $data['totalprice']     = $params && $params['totalprice']?$params['totalprice']:0;

            $data['createtime'] = time();
            Db::name('staff_commission_log')->insert($data);
            return ['status'=>1,'msg'=>''];
        }
    }

    public static function addgoldmoney($aid,$mid,$goldmoney,$remark='',$ordernum=0){
        if(getcustom('member_goldmoney_silvermoney',$aid)){
            //添加金值
            if($goldmoney==0) return ;
            $member = Db::name('member')->where('id',$mid)->where('aid',$aid)->lock(true)->find();
            if(!$member) return ['status'=>0,'msg'=>t('会员').'不存在'];

            $updata = [];
            $after = $member['goldmoney'] + $goldmoney;
            $updata['goldmoney'] = $after;
            $up = Db::name('member')->where('id',$mid)->where('aid',$aid)->update($updata);
            if(!$up) return ['status'=>0,'msg'=>'变动失败'];
            $data = [];
            $data['aid'] = $aid;
            $data['mid'] = $mid;
            $data['goldmoney']  = $goldmoney;
            $data['after']      = $after;
            $data['remark']     = $remark;
            $data['ordernum']   = $ordernum;
            $data['createtime'] = time();
            Db::name('member_goldmoneylog')->insert($data);
            return ['status'=>1,'msg'=>''];
        }
    }

    public static function addsilvermoney($aid,$mid,$silvermoney,$remark='',$ordernum=0){
        if(getcustom('member_goldmoney_silvermoney',$aid)){
            //添加金值
            if($silvermoney==0) return ;
            $member = Db::name('member')->where('id',$mid)->where('aid',$aid)->lock(true)->find();
            if(!$member) return ['status'=>0,'msg'=>t('会员').'不存在'];

            $updata = [];
            $after = $member['silvermoney'] + $silvermoney;
            $updata['silvermoney'] = $after;
            $up = Db::name('member')->where('id',$mid)->where('aid',$aid)->update($updata);
            if(!$up) return ['status'=>0,'msg'=>'变动失败'];
            $data = [];
            $data['aid'] = $aid;
            $data['mid'] = $mid;
            $data['silvermoney']= $silvermoney;
            $data['after']      = $after;
            $data['remark']     = $remark;
            $data['ordernum']   = $ordernum;
            $data['createtime'] = time();
            Db::name('member_silvermoneylog')->insert($data);
            return ['status'=>1,'msg'=>''];
        }
    }

    /**
     * 查询原上级，每一层都判断是否有原上级
     * @param $aid
     * @param $mid
     * @param $path
     */
    public static function queryOriginPath($aid,$mid,$max_ceng=1,$now_ceng=0,$parent_arr=[],$field='*'){
        if($max_ceng>=50){
            //限制一下最大50层，防止查询超时
            return $parent_arr;
        }
        $member = Db::name('member')->where('id',$mid)->where('aid',$aid)->find();
        if($member['pid_origin']){
            $parent = Db::name('member')->where('id',$member['pid_origin'])->where('aid',$aid)->field($field)->find();
        }else{
            $parent = Db::name('member')->where('id',$member['pid'])->where('aid',$aid)->field($field)->find();
        }
        $parent_arr[] = $parent;
        if(($parent['pid'] || $parent['pid_origin']) && $now_ceng<$max_ceng){
            $now_ceng++;
            return self::queryOriginPath($aid,$parent['id'],$max_ceng,$now_ceng,$parent_arr,$field);
        }else{
            return $parent_arr;
        }
    }
    public static function addcredit1($aid,$mid,$credit1,$remark,$addtotal=0,$credit1_money=0){
        if($credit1==0) return ;
      
        $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->lock(true)->find();
        if(!$member) return ['status'=>0,'msg'=>t('会员').'不存在'];
	
        $member['credit1'] = $member['credit1'];
        if($credit1 < 0 && $member['credit1'] < $credit1*-1)  return ['status'=>0,'msg'=>t('积分').'不足'];
        // $m_level_com = Db::name('member_level')->where('id',$member['levelid'])->value('credit1_max');
        // if($m_level_com > 0 && $credit1>0){
        //     $m_total = $member['credit1'];
        //     if($m_total >= $m_level_com){
        //         return ['status'=>0,'msg'=>t('credit1').'已达上限！'];
        //     }
        //     $com = $m_level_com - $m_total;
        //     if($com <= $credit1){
        //         $credit1 = $com;
        //     }
        // }
        $after = $member['credit1'] + $credit1;
        Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['credit1'=>$after]);
        $data = [];
        $data['aid'] = $aid;
        $data['mid'] = $mid;
        $data['credit1'] = $credit1;
        $data['after'] = $after;
        $data['createtime'] = time();
        $data['remark'] = $remark;
        $data['type'] = $addtotal;
        $data['addtotal'] = $addtotal;
        $data['credit1_money'] = $credit1_money;
        Db::name('member_credit1log')->insert($data);
        
        
        return ['status'=>1,'msg'=>''];
    }
    public static function addcredit2($aid,$mid,$credit2,$remark,$addtotal=0){
        if($credit2==0) return ;
        $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->lock(true)->find();
        if(!$member) return ['status'=>0,'msg'=>t('会员').'不存在'];
        if($credit2 < 0 && $member['credit2'] < $credit2*-1) return ['status'=>0,'msg'=>t('credit2').'不足'];
        $set = Db::name('admin_set')->where('aid',$aid)->find();
        $after = $member['credit2'] + $credit2;
        Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['credit2'=>$after]);
        if ($credit2 < 0 && $set['give_lvid'] == $member['levelid']) {
            self::addcredit1($aid,$mid,$credit2,'消费扣除');
        }
        
        $data = [];
        $data['aid'] = $aid;
        $data['mid'] = $mid;
        $data['credit2'] = $credit2;
        $data['after'] = $after;
        $data['createtime'] = time();
        $data['remark'] = $remark;
        $data['addtotal'] = $addtotal;
        Db::name('member_credit2log')->insert($data);
        
        if ($credit2 < 0 && $set['give_lvid'] == $member['levelid']) {
            $credit2_after = $credit2*-1;
            $logArray = Db::name('member_credit_record')->where('aid',$aid)->where('mid',$mid)->where('credit2_after','>',0)->select()->toArray();
            foreach($logArray as $k3=> $log) {
                if ($credit2_after <= 0) {
                    break;
                }
                $update = [];
    			$update['id'] = $log['id'];
				if ($log['credit2_after'] >= $credit2_after) {
    				$update['credit2_after'] =  $log['credit2_after']-$credit2_after;
    		     	$credit2_after = 0;
                }else {
                    $update['credit2_after'] = 0;
                    $credit2_after -= $log['credit1_after'];
                }
                if ($log['status']!=1) {
                    $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->find();
                   	$after = $log['credit1'] - $log['credit2'];
                   	if ($after > $member['credit1']) {
                   	    $after = $member['credit1'];
                   	}
            	    self::addcredit1($aid,$mid,$after*-1,'消费扣除!');
            	    $update['status'] = 1;
                }
            	Db::name('member_credit_record')->where('aid',$aid)->where('id',$log['id'])->update($update);
            }
        }
        return ['status'=>1,'msg'=>''];
    }
    
    public static function addcredit3($aid,$mid,$credit3,$remark){
        if($credit3==0) return ;
        $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->lock(true)->find();
        if(!$member) return ['status'=>0,'msg'=>t('会员').'不存在'];
        if($member['credit3']<=0 && $credit3< 0) return ;
        if($credit3 < 0 && $member['credit3'] < $credit3*-1) return ['status'=>0,'msg'=>t('credit3').'不足'];
        $after = $member['credit3'] + $credit3;
        Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['credit3'=>$after]);
        $data = [];
        $data['aid'] = $aid;
        $data['mid'] = $mid;
        $data['credit3'] = $credit3;
        $data['after'] = $after;
        $data['createtime'] = time();
        $data['remark'] = $remark;
        Db::name('member_credit3log')->insert($data);
        return ['status'=>1,'msg'=>''];
    }    
        
        
        
    public static function addcredit4($aid,$mid,$credit4,$remark){
        if($credit4==0) return ;
        $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->lock(true)->find();
        if(!$member) return ['status'=>0,'msg'=>t('会员').'不存在'];
        if($member['credit4']<=0 && $credit4< 0) return ;
        if($credit4 < 0 && $member['credit4'] < $credit4*-1) return ['status'=>0,'msg'=>t('credit4').'不足'];
        $after = $member['credit4'] + $credit4;
        Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['credit4'=>$after]);
        $data = [];
        $data['aid'] = $aid;
        $data['mid'] = $mid;
        $data['credit4'] = $credit4;
        $data['after'] = $after;
        $data['createtime'] = time();
        $data['remark'] = $remark;
        Db::name('member_credit4log')->insert($data);
        return ['status'=>1,'msg'=>''];
    }    
           
        
        
    public static function addcredit5($aid,$mid,$credit5,$remark){
        if($credit5==0) return ;
        $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->lock(true)->find();
        if(!$member) return ['status'=>0,'msg'=>t('会员').'不存在'];
        if($member['credit5']<=0 && $credit5< 0) return ;
        if($credit5 < 0 && $member['credit5'] < $credit5*-1) return ['status'=>0,'msg'=>t('credit5').'不足'];
        $after = $member['credit5'] + $credit5;
        Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['credit5'=>$after]);
        $data = [];
        $data['aid'] = $aid;
        $data['mid'] = $mid;
        $data['credit5'] = $credit5;
        $data['after'] = $after;
        $data['createtime'] = time();
        $data['remark'] = $remark;
        Db::name('member_credit5log')->insert($data);
        return ['status'=>1,'msg'=>''];
    }  
    
    
     /**
     * 自动降级
     */
     //升级
	public static function downlv($aid,$mid,$member=[]){
// 		$member = Db::name('member')->where('aid',$aid)->where('id',$mid)->find();
        if(!$member['id']) return;
        //检测推荐人和团队业绩
        $levellist = Db::name('member_level')->where('aid',$aid)->column('*','id');
        $level_data = $levellist[$member['levelid']];
        $check_result = \app\commons\Member::checkDownLevelCon($member,$level_data);
        if(!$check_result['is_down']){
            //考核通过，更新有效期
            $data_u = [];
            $data_u['isauto_down'] = 0;
            $data_u['levelstarttime'] = $member['levelendtime'];
            if($level_data['yxqdate']>0){
                $data_u['levelendtime'] = $member['levelendtime']+86400 * $level_data['yxqdate'];
            }else{
                $data_u['levelendtime'] = 0;
            }
            Db::name('member')->where('id', $member['id'])->update($data_u);
        }
		//他的上级
		if($member['path']){
			$parentList = Db::name('member')->where('aid',$aid)->where('id','in',$member['path'])->order(Db::raw('field(id,'.$member['path'].')'))->select()->toArray();
			if($parentList){
				$parentList = array_reverse($parentList);
				foreach($parentList as $parent){
				    if($parent['isauto_down']==1){
            		    $level_data = $levellist[$parent['levelid']];
                        $check_result = \app\commons\Member::checkDownLevelCon($parent,$level_data);
                        if(!$check_result['is_down']){
                            //考核通过，更新有效期
                            $data_u = [];
                            $data_u['isauto_down'] = 0;
                            $data_u['levelstarttime'] = $parent['levelendtime'];
                            if($level_data['yxqdate']>0){
                                $data_u['levelendtime'] = $parent['levelendtime']+86400 * $level_data['yxqdate'];
                            }else{
                                $data_u['levelendtime'] = 0;
                            }
                            Db::name('member')->where('id', $parent['id'])->update($data_u);
                        }
            		}
				}
			}
		}
	}
     public function checkDownTeamLevelCon($member,$level_data){
      
        $is_down = 0;
        $date_start = $member['levelstarttime'];
        $date_end = $member['levelendtime'];
        $isup = false;
        $isup_or = false;
        $desc = '';
        $down_level_time = Db::name('member_leveldown_record')->where('mid',$mid)->order('createtime desc')->value('createtime');
        $down_level_time = $down_level_time?:0;
        if($lv['up_ordermoney']>0){
            $ordermoney = 0 + Db::name('shop_order')->where('aid',$aid)->where('mid',$mid)->where('status','in','1,2,3')->where('paytime','>',$down_level_time)->sum('totalprice');
            if ($lv['up_ordermoney'] <= $ordermoney) {
                $isup_or = true;
            }
            $desc .= 'up_ordermoney'.$isup_or."-";
        }
        if($lv['up_wxpaymoney']>0){
            $wxpaymoney = 0 + Db::name('wxpay_log')->where('aid',$aid)->where('mid',$mid)->where('createtime','>',$down_level_time)->sum('total_fee');
            if ($lv['up_wxpaymoney'] <= $wxpaymoney) {
               $isup_or = true;
            }
            $desc .= 'up_wxpaymoney'.$isup_or."-";
        }
         if($lv['up_rechargemoney']>0){
            $rechargemoney = 0 + Db::name('recharge_order')->where('aid',$aid)->where('mid',$mid)->where('status',1)->where('createtime','>',$down_level_time)->sum('money');
            if ($lv['up_rechargemoney'] <= $rechargemoney) {
               $isup_or = true;
            }
            $desc .= 'up_rechargemoney'.$isup_or."-";
        }
        
        if ($lv['up_fxorder_condition'] == 'or') {
            if(!$isup_or && $lv['up_fxordermoney'] > 0){
				$fxordermoney_up = self::fxordermoney($aid, $mid, $lv,$down_level_time);
				if ($fxordermoney_up) {
				    $isup_or = true;
				}
				$desc .= 'up_fxordermoney'.$isup_or."-";
            }
            
        }
        if ($lv['up_downcount_condition'] == 'or') {
            if(!$isup_or && ($lv['up_fxdowncount']>0 || $lv['up_fxdowncount2']>0 || $lv['up_fxdowncount3']>0)){
                $up_fxdowncount_up = self::fxdowncount($aid, $mid, $lv,$down_level_time);
                if ($up_fxdowncount_up) {
				   $isup_or = true;
				}
				$desc .= 'up_fxdowncount'.$isup_or."-";
            }
            
        }
        if ($lv['up_pro_condition']=='or') {
		    if(!$isup_or && ($lv['up_proid']!='0' && $lv['up_proid']!='')){ //购买指定商品
                $up_fxbuypro_up = self::fxbuypro($aid, $mid, $lv,$down_level_time);
               	if ($up_fxbuypro_up) {
				   $isup_or = true;
				}
				 $desc .= 'up_fxbuypro'.$isup_or."-";
            }
           
		} 
		if($lv['up_fxdowncount_and_isor'] == 'or'){
			if(!$isup_or && ($lv['up_fxdowncount_and']>0 || $lv['up_fxdowncount2_and']>0)){
			    $up_fxdowncount2_up = self::fxdowncount2($aid, $mid, $lv,$down_level_time);
			    if ($up_fxbuypro_up) {
				   $isup_or = true;
				}
				$desc .= 'up_fxdowncount2'.$isup_or."-";
			}
	
		}
	
		if ($lv['up_max_condition']=='or') {
            if(!$isup_or && $lv['up_fxordermoney_da'] > 0 ){
				$downmoneys = self::getdownmids_removemax2($aid,$mid,$lv['up_fxorderlevelnum_da'],$lv['up_fxorderlevelid_da'],$down_level_time);
                if($downmoneys[0] >= $lv['up_fxordermoney_da'] && $downmoneys[1] >= $lv['up_fxordermoney_da']){
                    $isup_or = true;
                } 
                $desc .= 'up_removemax2'.$isup_or."-";
            }
        }
        if($isup_or) {
            $isup = true;
        }
     
        if ($lv['up_fxorder_condition'] == 'and') {
            if($isup && $lv['up_fxordermoney'] > 0){
		    	$fxordermoney_up = self::fxordermoney($aid, $mid, $lv,$down_level_time);
                if($fxordermoney_up){
                    $isup = true;
                }else {
                    $isup = false;
                }
                $desc .= 'fxordermoney'.$isup."&";
            }
        }
        if ($lv['up_downcount_condition'] == 'and') {
            if($isup && ($lv['up_fxdowncount']>0 || $lv['up_fxdowncount2']>0 || $lv['up_fxdowncount3']>0)){
                $up_fxdowncount_up = self::fxdowncount($aid, $mid, $lv,$down_level_time);
                if($up_fxdowncount_up){
                    $isup = true;
                }else {
                    $isup = false;
                }
                $desc .= 'fxdowncount'.$isup."&";
            }
        }
        
		if ($lv['up_pro_condition']=='and') {
		    if($isup && ($lv['up_proid']!='0' && $lv['up_proid']!='')){ //购买指定商品
                $up_fxbuypro_up = self::fxbuypro($aid, $mid, $lv,$down_level_time);
                if ($up_fxbuypro_up) {
                    $isup = true;
                } else {
                    $isup = false;
                }
                $desc .= 'fxbuypro'.$isup."&";
            }
		}
		if($lv['up_fxdowncount_and_isor'] == 'and'){
			if($isup && ($lv['up_fxdowncount_and']>0 || $lv['up_fxdowncount2_and']>0)){
				$up_fxdowncount2_up = self::fxdowncount2($aid, $mid, $lv,$down_level_time);
				if ($up_fxdowncount2_up) {
                    $isup = true;
                } else {
                    $isup = false;
                }
                $desc .= 'fxdowncount2'.$isup."&";
			}
		}
      
        if ($lv['up_max_condition']=='and') {
            if($isup && $lv['up_fxordermoney_da'] > 0 ){
				$downmoneys = self::getdownmids_removemax2($aid,$mid,$lv['up_fxorderlevelnum_da'],$lv['up_fxorderlevelid_da'],$down_level_time);
                if($downmoneys[0] >= $lv['up_fxordermoney_da'] && $downmoneys[1] >= $lv['up_fxordermoney_da']){
                    $isup = true;
                }else {
				   	$isup = false;
				}
				$desc .= 'removemax2'.$isup."&";
            }
        }
        
        if($isup){
            $is_down =0;
        }else{
            $is_down = 1;
        }
        return ['is_down'=>$is_down,'desc'=>$desc];
    }
    
    
    
     //加消费赠送积分记录
    public static function scoreinlog($aid,$bid,$mid,$type,$orderid,$ordernum,$score,$totalprice=0){
        if($score<=0) return;
        //查询是否有合并
        $typelen = strlen($type);
        $hbpos   = strpos($type,'_hb');
        //如果有合并，且合并'_hb'标识位于最后方
        if($hbpos && $hbpos == ($typelen-3)){
            //查询合并订单
            $ordertype = substr($type, 0,$hbpos);
            $orders = Db::name($ordertype.'_order')->where('ordernum','like',$ordernum.'%')->where('mid',$mid)->where('aid',$aid)->field('id,bid,ordernum,totalprice')->select()->toArray();
            if($orders){
                $klen = count($orders)-1;//key长度
                $allscore = $score;//赋值积分
                foreach($orders as $ok=>$order){
                    //如果循环到最后一个，则把剩余积分都归它
                    if($ok == $klen){
                        $givescore = $allscore;
                    }else{
                        //计算所占积分比例
                        $radio = $order['totalprice']/$totalprice;
                        $givescore = floor($radio*$score);
                    }
                    if($givescore<0){
                        $givescore = 0;
                    }
                    self::scoreinlog2($aid,$bid,$mid,$ordertype,$order['id'],$order['ordernum'],$givescore);
                    $allscore -= $givescore;
                }
                unset($order);
            }
        }else{
            self::scoreinlog2($aid,$bid,$mid,$type,$orderid,$ordernum,$score);
        }
    }
    //加消费赠送积分记录2
    public static function scoreinlog2($aid,$bid,$mid,$type,$orderid,$ordernum,$score){
        $data = [];
        $data['aid']      = $aid;
        $data['bid']      = $bid;
        $data['mid']      = $mid;
        $data['type']     = $type;
        $data['orderid']  = $orderid;
        $data['ordernum'] = $ordernum;
        $data['score']    = $score;//赠送积分
        $data['residue']  = $score;//剩余赠送积分（扣除时用）
        $data['createtime'] = time();
        Db::name('member_score_scoreinlog')->insert($data);
        return ['status'=>1,'msg'=>''];
    }
    //扣除消费赠送积分
    public static function decscorein($aid,$type,$orderid,$ordernum,$remark='订单退款扣除消费赠送'){
        $where = [];
        $where[] = ['orderid','=',$orderid];
        $where[] = ['ordernum','=',$ordernum];
        $where[] = ['type','=',$type];
        $where[] = ['residue','>',0];
        $where[] = ['score','>',0];
        $where[] = ['aid','=',$aid];
        $scoreinlogs = Db::name('member_score_scoreinlog')->where($where)->select()->toArray();
        if($scoreinlogs){
            foreach($scoreinlogs as $log){
                $res = self::addscore($aid,$log['mid'],-$log['residue'],$remark,$type,0,0,1,['canminus'=>true]);
                if($res && $res['status'] == 1){
                    //修改赠送记录
                    Db::name('member_score_scoreinlog')->where('id',$log['id'])->update(['residue'=>0,'updatetime'=>time()]);
                }
            }
            unset($log);
        }
    }
  
}