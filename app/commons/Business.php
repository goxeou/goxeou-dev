<?php


namespace app\commons;
use think\facade\Db;
class Business
{
	//加余额
	public static function addmoney($aid,$bid,$money,$remark,$addparentcommission=false,$type='',$ordernum='',$params=[]){
		if($money==0) return ;
		$business = Db::name('business')->where('aid',$aid)->where('id',$bid)->find();
		if(!$business) return ['status'=>0,'msg'=>'商家不存在'];
		Db::name('business')->where('aid',$aid)->where('id',$bid)->inc('money',$money)->update();
		
		$data = [];
		$data['aid'] = $aid;
		$data['bid'] = $bid;
		$data['money'] = $money;
		$data['after'] = $business['money'] + $money;
		$data['createtime'] = time();
		$data['remark'] = $remark;
		$data['type'] = $type;
        $data['ordernum'] = $ordernum;
		Db::name('business_moneylog')->insert($data);

		if($addparentcommission && $money > 0){
			if(getcustom('business_agent')){
                if(isset($params['business_lirun'])){
                    $business_lirun = $params['business_lirun'];
                }else{
                    $business_lirun = 0;
                }
				self::addparentcommission2($aid,$bid,$money,$params['platformMoney'],$business_lirun);
			}else{
				self::addparentcommission($aid,$bid,$money);
			}
		}
		return ['status'=>1,'msg'=>''];
	}
	//加积分
	public static function addscore($aid,$bid,$score,$remark,$addplat=0){
		if($score==0) return ['status'=>0,'msg'=>''];
		$business = Db::name('business')->where('aid',$aid)->where('id',$bid)->find();
		if(!$business) return ['status'=>0,'msg'=>'商家不存在'];
		if($score < 0 && $business['score'] < -$score){
			$business_selfscore_minus = $business['business_selfscore_minus'];
			if($business_selfscore_minus == -1){
				$bset = Db::name('business_sysset')->where('aid',$aid)->find();
				$business_selfscore_minus = $bset['business_selfscore_minus'];
			}
			if($business_selfscore_minus == 0 ) return ['status'=>0,'msg'=>'商家'.t('积分').'不足'];
		}
		Db::name('business')->where('aid',$aid)->where('id',$bid)->inc('score',$score)->update();
		
		$data = [];
		$data['aid'] = $aid;
		$data['bid'] = $bid;
		$data['score'] = $score;
		$data['after'] = $business['score'] + $score;
		$data['createtime'] = time();
		$data['remark'] = $remark;
		Db::name('business_scorelog')->insert($data);

		if($addplat){
			Db::name('admin')->where('id',$aid)->inc('score',-$score)->update();
			$data = [];
			$data['aid'] = $aid;
			$data['score'] = -$score;
			$data['after'] = Db::name('admin')->where('id',$aid)->value('score');
			$data['createtime'] = time();
			if($score > 0){
				$data['remark'] = '给商家'.$business['name'].'加'.t('积分');
			}else{
				$data['remark'] = '商家'.$business['name'].'花费'.t('积分');
			}
			Db::name('admin_scorelog')->insert($data);
		}

		return ['status'=>1,'msg'=>''];
	}

	//加会员积分
	public static function addmemberscore($aid,$bid,$mid,$score,$remark,$decbscore=1){
		if($score==0) return ;
		$business = Db::name('business')->where('aid',$aid)->where('id',$bid)->find();
		if(!$business) return ['status'=>0,'msg'=>'商家不存在'];
		$member = Db::name('member')->where('aid',$aid)->where('id',$mid)->find();
		if(!$member) return ['status'=>0,'msg'=>t('会员').'不存在'];
		$bset = Db::name('business_sysset')->where('aid',$aid)->find();
		if($bset['business_selfscore'] == 1 && $bset['business_selfscore2'] == 1){
			$memberscore = 0;
			$memberscoreinfo = Db::name('business_memberscore')->where('aid',$aid)->where('bid',$bid)->where('mid',$mid)->find();
			if($memberscoreinfo) $memberscore = $memberscoreinfo['score'];
			if($score < 0 && $memberscore < -$score){
				return ['status'=>0,'msg'=>t('积分').'不足'];
			}
			if($decbscore == 1){
				if($score > 0){
					$rs = self::addscore($aid,$bid,-$score,'给'.t('用户').$member['nickname'].'加'.t('积分'));
				}else{
					$rs = self::addscore($aid,$bid,-$score,t('用户').$member['nickname'].'花费'.t('积分'));
				}
				if($rs['status'] == 0) return $rs;
			}
			Db::name('member')->where('aid',$aid)->where('id',$mid)->inc('bscore',$score)->update();

			if(!$memberscoreinfo){
				Db::name('business_memberscore')->insert(['aid'=>$aid,'bid'=>$bid,'mid'=>$mid,'score'=>$score]);
			}else{
				Db::name('business_memberscore')->where('id',$memberscoreinfo['id'])->inc('score',$score)->update();
			}
		}else{
			if($decbscore == 1){
				if($score > 0){
					$rs = self::addscore($aid,$bid,-$score,'给'.t('用户').$member['nickname'].'加'.t('积分'));
				}else{
					$rs = self::addscore($aid,$bid,-$score,t('用户').$member['nickname'].'花费'.t('积分'));
				}
				if($rs['status'] == 0) return $rs;
			}
			$rs = \app\commons\Member::addscore($aid,$mid,$score,$remark,'',$bid);
			if($rs['status'] == 0) return $rs;
		}

		$data = [];
		$data['aid'] = $aid;
		$data['bid'] = $bid;
		$data['mid'] = $mid;
		$data['score'] = $score;
		$data['after'] = ($memberscoreinfo ? $memberscoreinfo['score'] : 0) + $score;
		$data['createtime'] = time();
		$data['remark'] = $remark;
		Db::name('business_member_scorelog')->insert($data);
		return ['status'=>1,'msg'=>''];
	}

	public static function addparentcommission($aid,$bid,$money){
		$bset = Db::name('business_sysset')->where('aid',$aid)->find();
		if($bset['parentcommission'] > 0){
			$business = Db::name('business')->where('id',$bid)->find();
			$buser = Db::name('admin_user')->where('aid',$aid)->where('bid',$bid)->where('isadmin',1)->find();
			if($buser && $buser['mid']){
				$member = Db::name('member')->where('id',$buser['mid'])->find();
				if($member && $member['pid']){
					$commission = round($money * $bset['parentcommission'] * 0.01,2);
					if($commission > 0){
						if(getcustom('shoptongji3')){ //商家推荐商家提成
							$isadminuser = Db::name('admin_user')->where('aid',$aid)->where('bid','>',0)->where('mid',$member['pid'])->where('isadmin',1)->find();
							if($isadminuser && $isadminuser['bid'] != $bid){
								\app\commons\Business::addmoney($aid,$isadminuser['bid'],$commission,'商户['.$business['name'].']营业提成');
							}
						}else{
							\app\commons\Member::addcommission($aid,$member['pid'],$member['id'],$commission,'商户['.$business['name'].']营业提成');
						}
					}
				}
			}
		}
	}
    //todo 收银台退款 扣除佣金
	public static function addparentcommission2($aid,$bid,$businessMoney,$platformMoney=0,$business_lirun=0){
		if(getcustom('business_agent')){
			$business = Db::name('business')->where('id',$bid)->field('id,name')->find();
			if($business){
				$buser = Db::name('admin_user')->where('aid',$aid)->where('bid',$bid)->where('isadmin',1)->find();
				if($buser && $buser['mid']){
					$member = Db::name('member')->where('id',$buser['mid'])->field('id,pid,levelid')->find();
					if($member && $member['pid']){
	                    $money = $businessMoney;
	                    $admin_set = Db::name('admin_set')->where('aid',$aid)->find();
	                    //推荐商家结算方式：0按结算金额,1按平台抽成金额，2按利润进行结算
	                    if($admin_set['tjbusiness_jiesuan_type'] == 1){
	                        $money = $platformMoney;
	                    }
	                    if($admin_set['tjbusiness_jiesuan_type'] == 2){
	                        $money = $business_lirun >= 0 ? $business_lirun : 0;
	                    }
						//查询上级信息
						$parent = Db::name('member')->where('id',$member['pid'])->where('aid',$aid)->field('id,pid,levelid')->find();
						if($parent){
							//查询上级等级信息
							$plevel = Db::name('member_level')->where('id',$parent['levelid'])->where('can_agent','>',0)->where('aid',$aid)->field('business_zt_ratio')->find();
							if($plevel && $plevel['business_zt_ratio']>0){
								//发直推商家分成
								$parentcommission = round($money * $plevel['business_zt_ratio'] * 0.01,2);
								if($parentcommission > 0){
									\app\commons\Member::addcommission($aid,$member['pid'],$member['id'],$parentcommission,'商户['.$business['name'].']营业提成');
								}
							}

							if($parent['pid']>0){
								//查询上上级信息
								$parent2 = Db::name('member')->where('id',$parent['pid'])->where('aid',$aid)->field('id,levelid')->find();
								if($parent2){
									//查询上上级等级信息
									$plevel2 = Db::name('member_level')->where('id',$parent2['levelid'])->where('can_agent','>',0)->where('aid',$aid)->field('business_jt_ratio')->find();
									if($plevel2 && $plevel2['business_jt_ratio']>0){
										//发间推商家分成
										$parent2commission = round($money * $plevel2['business_jt_ratio'] * 0.01,2);
										if($parent2commission > 0){
											\app\commons\Member::addcommission($aid,$parent['pid'],$member['id'],$parent2commission,'商户['.$business['name'].']营业间推提成');
										}
									}
								}
							}
						}
					}
				}
			}
		}
		
	}

    public static function update_expire_status(){
        Db::name('business')->where('status',1)->where('endtime','>',0)->where('endtime','<', time())->update(['status'=>-1]);
    }

    public static function updateDeposit($aid,$bid,$money,$remark,$type='',$ordernum='')
    {
        if(getcustom('business_deposit')){
            if($money==0) return ;
            $business = Db::name('business')->where('aid',$aid)->where('id',$bid)->find();
            if(!$business) return ['status'=>0,'msg'=>'商家不存在'];
            Db::name('business')->where('aid',$aid)->where('id',$bid)->inc('deposit',$money)->update();

            $data = [];
            $data['aid'] = $aid;
            $data['bid'] = $bid;
            $data['money'] = $money;
            $data['after'] = $business['deposit'] + $money;
            $data['createtime'] = time();
            $data['remark'] = $remark;
            $data['type'] = $type;
            $data['ordernum'] = $ordernum;
            Db::name('business_depositlog')->insert($data);

            return ['status'=>1,'msg'=>''];
        }
    }

    //店铺分销(手动结算)
    public static function business_fenxiao($sysset,$type=0,$ids=[]){
	    if(getcustom('business_fenxiao')){
            //type 0结算自动统计线上订单的 1结算手动录入营业额的
            bcscale(2);
            $aid = $sysset['aid'];
            $now_time = time();
            $yesterday = date('Ymd',$now_time-86400);
            $where = [];
            $where[] = ['aid','=',$aid];
            $where[] = ['type','=',$type];
            $where[] = ['status','=',0];
//            $where[] = ['jiesuan_day','=',$yesterday];
            if($ids){
                $where[] = ['id','in',$ids];
            }
            $lists = Db::name('business_fenxiao')->where($where)->select();

            writeLog('aid'.$aid.'分销进入,可结算数据'.count($lists).'条','business_fenxiao');
            foreach($lists as $v){
                $bid = $v['bid'];
                $yeji = $v['yeji'];
                writeLog('数据ID'.$v['id'].'开始处理','business_fenxiao');
                //dump('数据ID'.$v['id'].'开始处理');
                $butie_yeji = 0;
                $business = Db::name('business')->where('id',$bid)->find();
                $yeji_total = Db::name('business_fenxiao')->where('bid',$bid)->sum('yeji');
                //是否开启保护期
                $protect_end = $business['createtime'] + $business['protect_day']*86400;
                if($yeji_total<$business['mature_yeji'] && $business['protect_status'] && $protect_end>$now_time){
                    //保护期内业绩不足的系统自动补足
                    if($yeji<$business['protect_yeji']){
                        $butie_yeji = bcsub($business['protect_yeji'],$yeji,2);
                    }
                    //成熟期
                    $cost_bili = $business['protect_cost_bili'];//成本比例
                    $plate_bili = $business['protect_plate_bili'];
                    $business_bili = $business['protect_business_bili'];//店铺比例
                    $business_send_bili = $business['protect_business_send_bili'];//发放比例
                    writeLog('商户'.$bid.'处于保护期，补贴业绩'.$butie_yeji.'总业绩'.bcadd($yeji,$butie_yeji),'business_fenxiao');
                    //dump('商户'.$bid.'处于保护期，补贴业绩'.$butie_yeji.'总业绩'.bcadd($yeji,$butie_yeji));
                    $stage = 1;
                }else{
                    //成熟期
                    $cost_bili = $business['mature_cost_bili'];//成本比例
                    $plate_bili = $business['mature_plate_bili'];
                    $business_bili = $business['mature_business_bili'];//店铺比例
                    $business_send_bili = $business['mature_business_send_bili'];//发放比例
                    writeLog('商户'.$bid.'处于成熟期，总业绩'.$yeji,'business_fenxiao');
                    //dump('商户'.$bid.'处于成熟期，总业绩'.$yeji);
                    $stage = 2;
                }
                $yeji = bcadd($yeji,$butie_yeji);
                $business_yeji = bcmul($yeji,$business_bili/100);
                //计算利润
                $cost = bcmul($yeji,$cost_bili/100);
                $plate_price = bcmul($yeji,$plate_bili/100);
                $lirun = bcsub($yeji,bcadd($cost,$plate_price,4));
                $bonus_price = bcmul($lirun,$business_send_bili/100);
                if($bonus_price<=0){
                    continue;
                }
                //1、计算发起人推荐人奖金
                $promoter_arr = json_decode($business['promoter'],true);
                if($promoter_arr){
                    foreach($promoter_arr as $promoter_mid=>$promoter_bili){
                        //计算发起人奖金
                        $promoter_bonus = bcmul($bonus_price,$promoter_bili['promoter_bili']/100);
                        writeLog('商户'.$bid.'发起人ID'.$promoter_mid.'，奖金'.$promoter_bonus,'business_fenxiao');
                        //dump('商户'.$bid.'发起人ID'.$promoter_mid.'，奖金'.$promoter_bonus);
                        if($promoter_bonus>0 && $promoter_mid){
                            \app\commons\Member::addcommission($aid,$promoter_mid,0,$promoter_bonus,'店铺发起人奖励',1,'business_fenxiao_promoter');
                            self::bonuslog($promoter_mid,$promoter_bonus,$bid,'business_fenxiao_promoter',$aid,$v['yeji'],$butie_yeji,$v['jiesuan_time']);
                        }
                        //计算发起人推荐人奖金
                        $promoter_tj_mid = Db::name('member')->where('id',$promoter_mid)->value('pid');
                        if($promoter_tj_mid){
                            $promoter_tj_bonus = bcmul($bonus_price,$promoter_bili['promoter_tj_bili']/100);
                        }else{
                            $promoter_tj_bonus = 0;
                        }
                        writeLog('商户'.$bid.'发起人ID'.$promoter_mid.'推荐人ID'.$promoter_tj_mid.'，推荐人奖金'.$promoter_tj_bonus,'business_fenxiao');
                        //dump('商户'.$bid.'发起人ID'.$promoter_mid.'推荐人ID'.$promoter_tj_mid.'，推荐人奖金'.$promoter_tj_bonus);
                        if($promoter_tj_bonus>0 && $promoter_tj_mid){
                            \app\commons\Member::addcommission($aid,$promoter_tj_mid,$promoter_mid,$promoter_tj_bonus,'店铺发起人推荐奖励',1,'business_fenxiao_promoter_tj');
                            self::bonuslog($promoter_tj_mid,$promoter_tj_bonus,$bid,'business_fenxiao_promoter_tj',$aid,$v['yeji'],$butie_yeji,$v['jiesuan_time']);
                        }


                    }
                }

                //2、合伙人推荐人奖金
                $partner_tj_bonus = bcmul($bonus_price,$business['partner_tj_bili']/100);
                $partner_arr = json_decode($business['partner'],true);
                $partner_tj_mids = '';
                if($partner_arr){
                    $fenshu_total = array_sum(array_column($partner_arr,'num'));
                    foreach($partner_arr as $k2=>$v2){
                        $partner_pid = Db::name('member')->where('id',$v2['id'])->value('pid');
                        $partner_tj_mids .= ','.$partner_pid;
                        $partner_tj_bonus_avg = bcmul(bcdiv($partner_tj_bonus,$fenshu_total,4),$v2['num'],4);
                        writeLog('商户'.$bid.'合伙人ID'.$v2['id'].'推荐人ID'.$partner_pid.'占份额'.$v2['num'].'，合伙人推荐人奖金'.$partner_tj_bonus_avg,'business_fenxiao');
                        //dump('商户'.$bid.'合伙人推荐人总奖金'.$partner_tj_bonus.',合伙人ID'.$v2['id'].'推荐人ID'.$partner_pid.'占份额'.$v2['num'].'，合伙人推荐人奖金'.$partner_tj_bonus_avg);
                        if($partner_tj_bonus_avg>0 && $partner_pid){
                            \app\commons\Member::addcommission($aid,$partner_pid,$v2['id'],$partner_tj_bonus_avg,'店铺合伙人推荐奖励',1,'business_fenxiao_partner_tj');
                            self::bonuslog($partner_pid,$partner_tj_bonus_avg,$bid,'business_fenxiao_partner_tj',$aid,$v['yeji'],$butie_yeji,$v['jiesuan_time']);
                        }
                    }
                }

                //3、净利润
                //$lirun = bcsub($bonus_price,bcadd($promoter_tj_bonus,$partner_tj_bonus));
               // $lirun = $bonus_price;
                writeLog('商户'.$bid.'净利润'.$lirun,'business_fenxiao');
                //dump('商户'.$bid.'净利润'.$bonus_price_new);
                //$plate_price = bcmul($lirun,$plate_bili/100);
                //再根据设置的门店比例计算
                //$business_yeji = bcmul($lirun,$business_bili/100);
                //再根据设置的发放比例计算
                //$bonus_price_new = bcmul($business_yeji,$business_send_bili/100);
                $bonus_price_new = $bonus_price;
                writeLog('商户'.$bid.'平台获得'.$plate_price.'，发放基数业绩'.$bonus_price_new,'business_fenxiao');
                //4、计算发起人奖金
//                $promoter_bonus = bcmul($bonus_price_new,$business['promoter_bili']/100);
//                writeLog('商户'.$bid.'发起人ID'.$promoter_mid.'，奖金'.$promoter_bonus,'business_fenxiao');
//                //dump('商户'.$bid.'发起人ID'.$promoter_mid.'，奖金'.$promoter_bonus);
//                if($promoter_bonus>0 && $promoter_mid){
//                    \app\commons\Member::addcommission($aid,$promoter_mid,0,$promoter_bonus,'店铺发起人奖励',1,'business_fenxiao_promoter');
//                    self::bonuslog($promoter_mid,$promoter_bonus,$bid,'business_fenxiao_promoter',$aid,$v['yeji'],$butie_yeji,$v['jiesuan_time']);
//                }
                //5、计算合伙人奖金
                $partner_bonus = bcmul($bonus_price_new,$business['partner_bili']/100);
                $partner_mids = '';
                if($partner_arr){
                    $fenshu_total = array_sum(array_column($partner_arr,'num'));
                    foreach($partner_arr as $k3=>$v3){
                        $partner_mids .= ','.$v3['id'];
                        $partner_bonus_avg = bcmul(bcdiv($partner_bonus,$fenshu_total,4),$v3['num']);
                        writeLog('商户'.$bid.'合伙人总奖金'.$partner_bonus.',合伙人ID'.$v3['id'].'占份额'.$v3['num'].'，奖金'.$partner_bonus_avg,'business_fenxiao');
                        //dump('商户'.$bid.'合伙人总奖金'.$partner_bonus.',合伙人ID'.$v3['id'].'占份额'.$v3['num'].'，奖金'.$partner_bonus_avg);
                        if($partner_bonus_avg>0 && $v3['id']){
                            \app\commons\Member::addcommission($aid,$v3['id'],0,$partner_bonus_avg,'店铺合伙人奖励',1,'business_fenxiao_partner');
                            self::bonuslog($v3['id'],$partner_bonus_avg,$bid,'business_fenxiao_partner',$aid,$v['yeji'],$butie_yeji,$v['jiesuan_time']);
                        }
                    }
                }
                //更新分销记录
                $data_u = [];
                $data_u['butie_yeji'] = $butie_yeji;
                $data_u['yeji'] = bcadd($v['yeji'],$butie_yeji);
                $data_u['cost'] = $cost;
                $data_u['lirun'] = $lirun;
                $data_u['business'] = $business_yeji;
                $data_u['plate'] = $plate_price;
                $data_u['business_send'] = $bonus_price_new;
                $data_u['promoter_mid'] = $promoter_mid;
                $data_u['promoter'] = $promoter_bonus;
                $data_u['promoter_tj_mid'] = $promoter_tj_mid;
                $data_u['promoter_tj'] = $promoter_tj_bonus;
                $data_u['partner_mids'] = ltrim($partner_mids,',');
                $data_u['partner'] = $partner_bonus;
                $data_u['partner_tj_mids'] = ltrim($partner_tj_mids,',');
                $data_u['partner_tj'] = $partner_tj_bonus;
                $data_u['sendtime'] = time();
                $data_u['status'] = 1;
                $data_u['stage'] = $stage;
                Db::name('business_fenxiao')->where('id',$v['id'])->update($data_u);
                //插入分销奖励记录，用于前台统计展示

            }
            return true;
        }
    }
    public static function bonuslog($mid,$bonus,$bid,$type,$aid,$yeji,$butie_yeji,$jiesuan_time){
        if(getcustom('business_fenxiao')) {
            $data = [];
            $data['aid'] = $aid;
            $data['mid'] = $mid;
            $data['bonus'] = $bonus;
            $data['bid'] = $bid;
            $data['type'] = $type;
            $data['createtime'] = time();
            $data['yeji'] = $yeji;
            $data['butie_yeji'] = $butie_yeji;
            $data['jiesuan_time'] = $jiesuan_time;
            Db::name('business_fenxiao_bonus')->insert($data);
            //记录会员各门店获得佣金汇总
            $map = [];
            $map[] = ['aid','=',$aid];
            $map[] = ['mid','=',$mid];
            $map[] = ['bid','=',$bid];
            $exit = Db::name('business_fenxiao_bonus_total')->where($map)->find();
            if($exit){
                $data_t = [];
                $data_t['bonus_total'] = bcadd($exit['bonus_total'],$bonus,2);
                $data_t['remain'] = bcadd($exit['remain'],$bonus,2);
                Db::name('business_fenxiao_bonus_total')->where('id',$exit['id'])->update($data_t);
            }else{
                $data_t = [];
                $data_t['aid'] = $aid;
                $data_t['mid'] = $mid;
                $data_t['bid'] = $bid;
                $data_t['bonus_total'] = $bonus;
                $data_t['remain'] = $bonus;
                Db::name('business_fenxiao_bonus_total')->insert($data_t);
            }
            return true;
        }
    }
    //统计线上店铺每日营业额
    public static function countBusinessYeji($payorder){
	    if(getcustom('business_fenxiao')){
	        if($payorder['business_fenxiao']){
	            return true;
            }
            $aid = $payorder['aid'];
            $bid = $payorder['bid'];
            $yeji = $payorder['money'];
            $jiesuan_day = date('Ymd',$payorder['paytime']);
            $exit = Db::name('business_fenxiao')->where('bid',$bid)->where('type',0)->where('status',0)->where('jiesuan_day',$jiesuan_day)->find();
            if($exit){
                $data = [];
                $data['yeji'] = bcadd($exit['yeji'],$yeji,2);
                Db::name('business_fenxiao')->where('id',$exit['id'])->update($data);
            }else{
                $data = [];
                $data['aid'] = $aid;
                $data['bid'] = $bid;
                $data['yeji'] = $yeji;
                $data['type'] = 0;
                $data['jiesuan_time'] = $payorder['paytime']?:time();
                $data['jiesuan_day'] = date('Ymd',$payorder['paytime']);
                Db::name('business_fenxiao')->insert($data);
            }
            Db::name('payorder')->where('id',$payorder['id'])->update(['business_fenxiao'=>1]);
            return true;
        }
    }

    public static function getUserAgentBids($aid,$user=[]){
        if(getcustom('user_area_agent') && $user['isadmin']==3) {
            $agentLevel = [
                '1' => [
                    'user_field' => 'agent_province',
                    'business_field' => 'province',
                ],
                '2' => [
                    'user_field' => 'agent_province',
                    'business_field' => 'province',
                ],
                '3' => [
                    'user_field' => 'agent_province',
                    'business_field' => 'province',
                ],
            ];
            $where = [];
            $where[] = ['aid','=',$aid];
            if($user['agent_level']>0){
                if ($user['agent_level'] > 0 ) {
                    $where[] = ['province','=',$user['agent_province']];
                }
                if ($user['agent_level'] > 1 ) {
                    $where[] = ['city','=',$user['agent_city']];
                }
                if ($user['agent_level'] > 2 ) {
                    $where[] = ['area','=',$user['agent_area']];
                }
                $areaBids = Db::name('business')->where($where)->column('id');
                $areaBids[] = 0;//平台
            }else{
                $areaBids = [0];
            }
            return $areaBids;
        }
    }


	//加销售额度
	public static function addsalesquota($aid,$bid,$money,$remark,$orderid){
		if($money==0) return ;
		$business = Db::name('business')->where('aid',$aid)->where('id',$bid)->find();
		if(!$business) return ['status'=>0,'msg'=>'商家不存在'];
		Db::name('business')->where('aid',$aid)->where('id',$bid)->inc('total_sales_quota',$money)->update();
		
		$data = [];
		$data['aid'] = $aid;
		$data['bid'] = $bid;
		$data['money'] = $money;
		$data['after'] = $business['total_sales_quota'] + $money;
		$data['createtime'] = time();
		$data['remark'] = $remark;
        $data['orderid'] = $orderid;
		Db::name('business_salesquota_log')->insert($data);
		return ['status'=>1,'msg'=>''];
	}


    public static function totalTurnover($aid, $bid)
    {
        //店铺总销售额   包含订单、买单
        $total = 0;
        $total += Db::name("maidan_order")->where('aid', $aid)->where('bid', $bid)->where('status', 1)->sum('money');
        $total += Db::name("lucky_collage_order")->where('aid', $aid)->where('bid', $bid)->where('status', 'in', [1, 2, 3])->sum('totalprice');
        $total += Db::name("collage_order")->where('aid', $aid)->where('bid', $bid)->where('status', 'in', [1, 2, 3])->sum('totalprice');
        $total += Db::name("kanjia_order")->where('aid', $aid)->where('bid', $bid)->where('status', 'in', [1, 2, 3])->sum('totalprice');
        $total += Db::name("shop_order")->where('aid', $aid)->where('bid', $bid)->where('status', 'in', [1, 2, 3])->sum('totalprice');
        $total += Db::name("tuangou_order")->where('aid', $aid)->where('bid', $bid)->where('status', 'in', [1, 2, 3])->sum('totalprice');
        $total += Db::name("seckill_order")->where('aid', $aid)->where('bid', $bid)->where('status', 'in', [1, 2, 3])->sum('totalprice');
        return round($total, 2);
    }
}