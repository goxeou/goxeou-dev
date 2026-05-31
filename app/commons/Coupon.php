<?php


namespace app\commons;
use think\facade\Db;
class Coupon
{
	//发送优惠券 $checktj:是否检测领取条件
	public static function send($aid,$mid,$cpid,$checktj=false,$tr_roomId=0,$setyxq=0,$is_scan=0){
		Db::startTrans();
		$coupon = db('coupon')->where('aid',$aid)->where('id',$cpid)->lock(true)->find();
		if(!$coupon){
			Db::commit();
			return ['status'=>0,'msg'=>t('优惠券').'不存在'];
		}
		if($coupon['stock']<=0){
			Db::commit();
			return ['status'=>0,'msg'=>'库存不足'];
		}

		if($checktj){
			$gettj = explode(',',$coupon['gettj']);
			$member = Db::name('member')->where('id',$mid)->find();
			if(!in_array('-1',$gettj) && !in_array($member['levelid'],$gettj)){ //不是所有人
				if(in_array('0',$gettj)){ //关注用户才能领
					if($member['subscribe']!=1){
						$appinfo = \app\commons\System::appinfo($aid,'mp');
						Db::commit();
						return ['status'=>0,'msg'=>'请先关注'.$appinfo['nickname'].'公众号'];
					}
				}else{
					if(count($gettj)==1){
						$levelname = Db::name('member_level')->where('id',$gettj[0])->value('name');
						Db::commit();
						return ['status'=>0,'msg'=>'只有'.$levelname.'才能领取'];
					}
					Db::commit();
					return ['status'=>0,'msg'=>'您没有领取权限'];
				}
			}
		}

		$data = [];
		$data['aid'] = $aid;
		$data['bid'] = $coupon['bid'];
		$data['mid'] = $mid;
		$data['couponid'] = $coupon['id'];
		$data['couponname'] = $coupon['name'];
		$data['money'] = $coupon['money'];
		$data['discount'] = $coupon['discount'];
		$data['minprice'] = $coupon['minprice'];
		if($setyxq){
			//领取后x天有效
			$data['starttime'] = time();
			$data['endtime'] = $data['starttime'] + 86400 * $setyxq;
		}else{
			if($coupon['yxqtype'] == 1){
				//固定有效期
				$yxqtime = explode(' ~ ',$coupon['yxqtime']);
				$data['starttime'] = strtotime($yxqtime[0]);
				$data['endtime'] = strtotime($yxqtime[1]);
			}elseif($coupon['yxqtype'] == 2){
				//领取后x天有效
				$data['starttime'] = time();
				$data['endtime'] = $data['starttime'] + 86400 * $coupon['yxqdate'];
			}elseif($coupon['yxqtype'] == 3){
				//次日起计算有效期
				$data['starttime'] = strtotime(date('Y-m-d')) + 86400;
				$data['endtime'] = $data['starttime'] + 86400 * $coupon['yxqdate'] - 1;
			}elseif($coupon['yxqtype'] == 4){
			    //领取后N天
			    if(getcustom('coupon_get_assert_time',$aid)){
			        $starttime =time()+ $coupon['yxqdate4_assert']*86400; 
			        $endtime =$starttime+ $coupon['yxqdate4_expire']*86400;
                    $data['starttime'] =   $starttime;
                    $data['endtime']  =   $endtime;
                }
            }
		}

        if($data['endtime'] <= time()){
			Db::commit();
			return ['status'=>0,'msg'=>'优惠券已过期'];
		}
		$data['type'] = $coupon['type'];
		$data['limit_count'] = $coupon['limit_count'];
		$data['limit_perday'] = $coupon['limit_perday'];
		$data['createtime'] = time();
		$data['code'] = random(16);
        $is_hexiaoqr = 1;
        if(getcustom('shop_product_fenqi_pay',$aid)){
            $is_hexiaoqr = 0;
        }
        if($is_hexiaoqr == 1){
            $data['hexiaoqr'] = createqrcode(m_url('admin/hexiao/hexiao?type=coupon&co='.$data['code'], $aid),'',$aid);
        }
		if(getcustom('mendian_upgrade',$aid)){
			$admin = Db::name('admin')->where('id',$aid)->field('mendian_upgrade_status')->find();
			if($admin['mendian_upgrade_status']==1){
				$data['hexiaoqr'] = createqrcode(m_url('pagesA/mendiancenter/hexiao?type=coupon&co='.$data['code']),'',$aid);
			}
		}
	
        if(getcustom('restaurant_scan_qrcode_coupon',$aid)){
            if($is_scan ==1){
                $data['is_scan'] = $is_scan;
                $data['hexiaoqr'] = createqrcode('co='.$data['code'].'&isscan=1','',$aid);
            }
        }
		if(getcustom('plug_tengrui',$aid)) {
			$data['tr_roomId'] = $tr_roomId;
		}
		if(getcustom('business_canuseplatcoupon',$aid)){
			$data['canused_bids'] = $coupon['canused_bids'];
			$data['canused_bcids'] = $coupon['canused_bcids'];
		}
		if(getcustom('coupon_xianxia_buy',$aid)){
		    $data['is_xianxia_buy'] = $coupon['is_xianxia_buy'];
        }

		db('coupon_record')->insert($data);
		db('coupon')->where('aid',$aid)->where('id',$coupon['id'])->update(['stock'=>Db::raw('stock-1'),'getnum'=>Db::raw('getnum+1')]);
		Db::commit();
		Wechat::updatemembercard($aid,$mid);
		return ['status'=>1,'msg'=>'发送成功'];
	}
	//支付后送券
	public static function getpaygive($aid,$mid,$type,$money,$orderids=null){
		if(!$money) $money = 0;
		$time = time();
		$whereOr = [];
		$where = [];
		$where[] = ['aid','=',$aid];
		if($type == 'shop' && $orderids){
			if(!is_array($orderids)){
				$orderids = [$orderids];
			}
			$proids = db('shop_order_goods')->where('orderid','in',$orderids)->column('proid');
			foreach($proids as $proid){
				$whereOr[] = "find_in_set('{$proid}',buyproids)";
			}
		}
		$whereOr = implode(' or ',$whereOr);
		if($whereOr){
			$whereOr = "(buyprogive=1 and ({$whereOr}))";
		}else{
			$whereOr = '(1=0)';
		}
        if(getcustom('coupon_other_business')){            
            $wehre_bid=[];
            if($orderids){
                if(!is_array($orderids)){
                    $orderids = [$orderids];
                }
                $wehre_bid[] = ['orderid','in',$orderids];
            }
            $bids = array_unique(Db::name('payorder')->where('aid',$aid)->where('type',$type)->where($wehre_bid)->column('bid'));
            if(empty($bids)){
                $bids = [-1];//不显示任何商家
            }
            $showOtherBCouponSum = Db::name('coupon_set')->where('aid',$aid)->where('bid','in',$bids)->sum('show_other_bcoupon');
            //是否展示其他商家优惠券
            if(intval($showOtherBCouponSum)==0){
                //只展示该商家的赠送优惠券
                $where[] = ['bid','in',$bids];
            }
        }

    	if($type == 'yuyue' && $orderids){
			if(!is_array($orderids)){
				$orderids = [$orderids];
			}
			$yuyueproids = db('yuyue_order')->where('id','in',$orderids)->column('proid');
			$whereOr2 = [];
			if($yuyueproids){
				foreach($yuyueproids as $yuyueproid){
					$whereOr2[] = "find_in_set('{$yuyueproid}',buyyuyueproids)";
				}
			}
			if($whereOr2){
				$whereOr .= ' or (buyyuyueprogive=1 and ('.implode(' or ',$whereOr2).'))';
			}
		}

		$couponlist = db('coupon')->where($where)->where("unix_timestamp(starttime)<={$time} and unix_timestamp(endtime)>={$time} and stock>0")->where("(paygive=1 and paygive_minprice<={$money} and paygive_maxprice>={$money} and find_in_set('{$type}',paygive_scene)) or $whereOr")->order('sort desc,id desc')->select()->toArray();
		//var_dump(Db::getlastsql());
		foreach($couponlist as $k=>$coupon){
			$havegetnum = db('coupon_record')->where('aid',$aid)->where('mid',$mid)->where('couponid',$coupon['id'])->count();
			if($havegetnum >= $coupon['perlimit']) unset($couponlist[$k]);
		}
		if(!$couponlist) return false;
		
		foreach($couponlist as $k=>$coupon){
			$thisnum = 0;
			if($coupon['paygive']==1 && $coupon['paygive_minprice'] <= $money && $coupon['paygive_maxprice'] >= $money && in_array($type,explode(',',$coupon['paygive_scene']))){
				$thisnum++;
			}
			if($type == 'shop' && $orderids && $coupon['buyprogive'] == 1){
				$coupon['buyproids'] = explode(',',$coupon['buyproids']);
				$coupon['buypro_give_num'] = explode(',',$coupon['buypro_give_num']);
				foreach($coupon['buyproids'] as $k2 => $proid) {
					if(in_array($proid, $proids) && $coupon['buypro_give_num'][$k2] > 0) {
						$thisnum += $coupon['buypro_give_num'][$k2];
					}
				}
			}

            if($type == 'yuyue' && $orderids && $coupon['buyyuyueprogive'] == 1){
                $coupon['buyyuyueproids'] = explode(',',$coupon['buyyuyueproids']);
                $coupon['buyyuyuepro_give_num'] = explode(',',$coupon['buyyuyuepro_give_num']);
                foreach($coupon['buyyuyueproids'] as $k2 => $yuyueproid) {
                    if(in_array($yuyueproid, $yuyueproids) && $coupon['buyyuyuepro_give_num'][$k2] > 0) {
                        $thisnum += $coupon['buyyuyuepro_give_num'][$k2];
                    }
                }
            }

			$couponlist[$k]['givenum'] = $thisnum;
            if($coupon['bid']>0){
                $bname = Db::name('business')->where('aid',$aid)->where('id',$coupon['bid'])->value('name');
                $couponlist[$k]['bname'] = $bname??'';
            }else{
                $couponlist[$k]['bname'] = '平台';
            }
            $couponlist[$k]['type_txt'] = self::getCouponTypeTxt($coupon['type']);
		}

		return $couponlist;
	}

    public static function getCouponTypeTxt($type=''){
        $couponTypes = [
            '1'=>'代金券',
            '2'=>'礼品券',
            '3'=>'计次券',
            '4'=>'运费抵扣券',
            '5'=>'餐饮券',
            '10'=>'折扣券',
            '20'=>'券包',
            '11'=>'兑换券',
		    '6'=>'酒店券',
        ];
        if(getcustom('restaurant_cashdesk_free_coupon')){
            $couponTypes['51'] = '免费券';
        }
        if($type && isset($couponTypes[$type])){
            return $couponTypes[$type];
        }
        return $type;
    }

    //使用优惠券

    /**
     * @param $aid
     * @param $coupon_record_id
     * @param $usecoupon_type 1付款后，2确认收货,hexiao
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function useCoupon($aid,$coupon_record_id,$usecoupon_type=1){
        if(getcustom('usecoupon_give_score') || getcustom('usecoupon_give_coupon') || getcustom('usecoupon_give_money')){

            //该优惠券是不是赠送的
            $crecord = Db::name('coupon_record')->where('id',$coupon_record_id)->find();
            if($crecord['from_mid']){
                //是不是满足条件
                $coupon = Db::name('coupon')->where('id',$crecord['couponid'])->find();
                if($coupon && ($coupon['usecoupon_give_type']==$usecoupon_type || $usecoupon_type=='hexiao')){
                    if(getcustom('usecoupon_give_score') && $coupon['usecoupon_give_score']>0){
                        \app\commons\Member::addscore($aid,$crecord['from_mid'],$coupon['usecoupon_give_score'],'转赠'.t('优惠券').'被使用赠送'.t('积分'));
                    }
                    if(getcustom('usecoupon_give_coupon') && $coupon['usecoupon_give_coupon']){
                        $coupon_ids = explode(',', $coupon['usecoupon_give_coupon']);
                        if($coupon_ids) {
                            foreach ($coupon_ids as $coupon_id) {
                                \app\commons\Coupon::send($aid, $crecord['from_mid'], $coupon_id);
                            }
                        }
                    }
                    if(getcustom('usecoupon_give_money') && $coupon['usecoupon_give_money']>0){
                        \app\commons\Member::addmoney($aid,$crecord['from_mid'],$coupon['usecoupon_give_money'],'转赠'.t('优惠券').'被使用赠送'.t('余额'));
                    }
                    if(getcustom('usecoupon_give_money') && $coupon['usecoupon_give_commission']>0){
                        \app\commons\Member::addcommission($aid,$crecord['from_mid'],$crecord['mid'],$coupon['usecoupon_give_commission'],'转赠'.t('优惠券').'被使用赠送'.t('佣金'));
                    }
                }
            }
        }
    }

    public static function auto_expire_notice(){
        if(getcustom('coupon_expire_notice')){
            //优惠券过期提醒，触发条件：开启通知+设置提醒天数+设置提醒模版+优惠券未使用+优惠券未过期+有效期小于8天（小于可设置提醒的最大天数）+（有效期<阈值）https://doc.weixin.qq.com/doc/w3_AT4AYwbFACwJ0AS0ojaT76ThAqOcT?scode=AHMAHgcfAA0c85SvNlAT4AYwbFACw
            $time = time();
            $list = Db::name('coupon_set')->where('bid',0)->where('expire_status',1)->where('expire_rules','<>','')->select()->toArray();
            foreach ($list as $item){
                $tmpl = Db::name('mp_tmplset')->where('aid',$item['aid'])->find();
                if($tmpl['tmpl_coupon_expire']){
                    $record = Db::name('coupon_record')->where('aid',$item['aid'])->where('status',0)
                        ->where('endtime','>=',$time)->where('endtime < 86400*8 +'.$time)->select()->toArray();
                    if($record){
                        foreach ($record as $v){
                            $rules = explode(',',$item['expire_rules']);
                            sort($rules);
                            if($v['endtime']){
                                foreach ($rules as $rule){
                                    if(($rule+1)*86400 > $v['endtime']-$time){
                                        self::send_expire_tmpl($v['aid'],$v['mid'],$v['couponname'],$v['endtime'],$v['id']);
                                        break;
                                    }
                                }
                            }
                        }
                    }

                }
            }
        }
    }

    public static  function send_expire_tmpl($aid,$mid,$couponname,$endtime,$recordId)
    {
        /*编号:OPENTM411793443
        标题:业务到期通知
        行业:IT科技 - 互联网|电子商务
        {{first.DATA}}
        业务类型：{{keyword1.DATA}}
        业务标识：{{keyword2.DATA}}
        到期时间：{{keyword3.DATA}}
        会员账号：{{keyword4.DATA}}
        {{remark.DATA}}*/
        //审核结果通知
        $tmplcontent = [];
        $tmplcontent['first'] = t('优惠券').'过期提醒';
        $tmplcontent['remark'] = '请点击查看详情~';
        $tmplcontent['keyword1'] = t('优惠券');
        $tmplcontent['keyword2'] = $couponname;
        $tmplcontent['keyword3'] = (string) date('Y-m-d H:i',$endtime);
        $tmplcontent['keyword4'] = "[nickname]";
        \app\commons\Wechat::sendtmpl($aid,$mid,'tmpl_coupon_expire',$tmplcontent,m_url('pagesExt/coupon/coupondetail?rid='.$recordId,$aid));
    }
}