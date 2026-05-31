<?php


// +----------------------------------------------------------------------
// | 后台账号 子账号
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;
use app\models\Member as m;
class Member extends Common
{
    public function initialize(){
		parent::initialize();
        if(getcustom('member_business')){
            if(bid>0){
                //平台权限
                $admin_user = Db::name('admin_user')->where('aid',aid)->where('isadmin','>',0)->field('auth_type,auth_data')->find();
                if($admin_user){
                    if($admin_user['auth_type'] !=1 ){
                        $admin_auth = !empty($admin_user['auth_data'])?json_decode($admin_user['auth_data'],true):[];
                        if(!in_array('MemberBusiness,MemberBusiness',$admin_auth)){
                            showmsg('无访问权限');
                        }
                    }
                }
                $action = request()->action();
                if(!in_array('Member/'.$action,$this->auth_data)){
                    showmsg('无访问权限');
                }
            }
        }else{
            if(bid > 0) showmsg('无访问权限');
        }
	}
	//会员列表
    public function index(){
        
        //	require('upgrade.php');
        
        
        
		$levelArr = Db::name('member_level')->where('aid',aid)->order('sort,id')->column('name','id');
		$defaultCat = Db::name('member_level_category')->where('aid',aid)->where('isdefault', 1)->value('id');
		if($defaultCat > 0) {
            $levelIdsDefault = Db::name('member_level')->where('aid',aid)->where('cid', $defaultCat)->column('id');
        }
	//会员标签
		if(getcustom('member_tag')){
			$tagdata = Db::name('member_tag')->where('aid',aid)->column('name','id');
		}
        $teamArr = Db::name('member_team_level')->where('aid',aid)->column('name','id');
        $storeArr = Db::name('member_store')->where('aid',aid)->column('name','id');
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'id desc'; 
			}
			$where = [];
			$where[] = ['aid','=',aid];
            if(getcustom('member_business')){
                if(bid){
                    $where[] = ['bid','=',bid];
                }else{
                    if(input('?param.bid') && input('param.bid')!=='') $where[] = ['bid','=',input('param.bid')];
                }
            }

			if(input('param.mid')) {
                if(getcustom('order_show_onlychildren')) {
                    if ($this->admin['order_show_onlychildren']) {
                        $uid = Db::name('admin_user')->where('aid',aid)->where('bid',bid)->order('id','asc')->value('id');
                        if($uid != $this->uid){
                            $childmids = \app\commons\Member::getdownmids(aid,$this->user['mid']);
                            if(in_array(input('param.mid'),$childmids))
                                $where[] = ['id','=',input('param.mid')];
                            else
                                $where[] = ['id','in',$childmids];
                        }
                    }else{
                        $where[] = ['id','=',input('param.mid')];
                    }
                }else{
                    $where[] = ['id','=',input('param.mid')];
                }
            }else{
                if(getcustom('order_show_onlychildren')) {
                    if ($this->admin['order_show_onlychildren']) {
                        $uid = Db::name('admin_user')->where('aid',aid)->where('bid',bid)->order('id','asc')->value('id');
                        if($uid != $this->uid){
                            $childmids = \app\commons\Member::getdownmids(aid,$this->user['mid']);
                            if(in_array(input('param.mid'),$childmids))
                                $where[] = ['id','=',input('param.mid')];
                            else
                                $where[] = ['id','in',$childmids];
                        }
                    }
                }else{
                    if(input('param.card_id')) {
                        $mids = Db::name('membercard_record')->where('aid',aid)->where('card_id',input('param.card_id'))->column('mid');
                        $where[] = ['id','in',$mids];
                    }
                }
            }
            if(input('param._pid')) $where[] = ['_pid','=',input('param._pid')];
			if(input('param.pid')) $where[] = ['pid','=',input('param.pid')];
            if(input('param.pid_origin')) $where[] = ['pid_origin','=',input('param.pid_origin')];
			if(input('param.isgetcard')){
				if(input('param.isgetcard') == 1){
					$where[] = ['','exp',Db::raw('card_code is not null')];
				}else{
					$where[] = ['','exp',Db::raw('card_code is null')];
				}
			}
			if(input('param.nickname')) $where[] = ['id|nickname|tel|realname|card_code','like','%'.input('param.nickname').'%'];
            if(input('param.teamlevel')) $where[] = ['teamlevel','=',input('param.teamlevel')];
                    if(input('param.storelevel')) $where[] = ['storelevel','=',input('param.storelevel')]; 
            if(input('param.realname')) $where[] = ['realname','like','%'.input('param.realname').'%'];
			if(input('param.levelid')) {
			    if($levelIdsDefault && !in_array(input('param.levelid'),$levelIdsDefault)) {
                    $mids = Db::name('member_level_record')->where('aid',aid)->where('levelid', input('param.levelid'))->column('mid');
                    $where[] = ['id','in',$mids];
                } else
                    $where[] = ['levelid','=',input('param.levelid')];
            }

			//其他分组等级的筛选

			if(input('param.ctime') ){
				$ctime = explode(' ~ ',input('param.ctime'));
				$where[] = ['createtime','>=',strtotime($ctime[0])];
				$where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
			}

			if(input('param.fhid')){
				$midlist = Db::name('shop_order_fenhong')->where('aid',aid)->where('id',input('param.fhid'))->value('midlist');
				$where[] = ['id','in',$midlist];
			}
            if(getcustom('plug_tengrui')){
                if(input('?param.tr_is_rzh') && input('param.tr_is_rzh')!==''){
                    $where[] = ['tr_is_rzh','=',input('param.tr_is_rzh')];
                }
            }
            if(getcustom('xixie')){
                if(input('?param.mdid') && input('param.mdid')!==''){
                    $where[] = ['mdid','=',input('param.mdid')];
                }
            }

			if(input('param.fxmid')){
				$fxmid = input('param.fxmid/d');
				if(input('param.deep') == 1){
					$where[] = ['pid','=',$fxmid];
				}elseif(input('param.deep') == 2){
					$where[] = Db::raw("pid in(select id from ".table_name('member')." where pid=".$fxmid.")");
				}elseif(input('param.deep') == 3){
					$where[] = Db::raw("pid in(select id from ".table_name('member')." where pid in(select id from ".table_name('member')." where pid=".$fxmid."))");
				}
			}
            if(getcustom('product_glass')){
                $from = input('param.from');
                if($from=='glassrecord'){
                    //只取下过单的商城或预约 客户要求暂时屏蔽
                    /*$mids1 = Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('status','in',[1,2,3])->column('mid');
                    $mids2 = Db::name('yuyue_order')->where('aid',aid)->where('bid',bid)->where('status','in',[1,2,3])->column('mid');
                    $mids = ['-1'];
                    if($mids1){
                        $mids = array_unique($mids1);
                    }
                    if($mids2){
                        $mids = array_unique(array_merge($mids,$mids2));
                    }
                    $where[] = ['id','in',$mids];*/
                }
            }
            if(getcustom('ganer_fenxiao')){
                if(input('areafenhong_province')){
                    $where[] = ['areafenhong_province','like','%'.input('areafenhong_province').'%'];
                }
                if(input('areafenhong_city')){
                    $where[] = ['areafenhong_city','like','%'.input('areafenhong_city').'%'];
                }
                if(input('areafenhong_area')){
                    $where[] = ['areafenhong_area','like','%'.input('areafenhong_area').'%'];
                }
            }

			if(getcustom('member_tag')){
				if(input('?param.tagid') && input('param.tagid')!==''){
					$where[] = Db::raw("find_in_set(".input('param.tagid/d').",tags)");
				}
			}
	

			$count = 0 + Db::name('member')->where($where)->count();
			$data = Db::name('member')->where($where)->page($page,$limit)->order($order)->select()->toArray();
            if(getcustom('plug_yuebao')){
                $set  = Db::name('admin_set')->where('aid',aid)->field('yuebao_rate,yuebao_withdraw_time')->find();
            }
      

            $moeny_weishu = 2;
            if(getcustom('fenhong_money_weishu')){
                $moeny_weishu = Db::name('admin_set')->where('aid',aid)->value('fenhong_money_weishu');
            }
            $score_weishu = 0;
            if(getcustom('score_weishu')){
                $score_weishu = Db::name('admin_set')->where('aid',aid)->value('score_weishu');
            }
            $moeny_weishu2 = 2;
            if(getcustom('member_money_weishu')){
                $moeny_weishu2 = Db::name('admin_set')->where('aid',aid)->value('member_money_weishu');
            }

            if(getcustom('fenhong_max') ){
                $fhlevellist = Db::name('member_level')->where('aid',aid)->where('fenhong','>','0')->order('sort desc,id desc')->column('*','id');
                $sysset = Db::name('admin_set')->where('aid',aid)->find();
                if(!empty($sysset['fenhong_max_add'])){
                    foreach($fhlevellist as $k=>$v){
                        $fenhong_max = Db::name('member_level')
                            ->where('aid',aid)
                            ->where('sort','<',$v['sort'])
                            ->sum('fenhong_max_money');
                        $fhlevellist[$k]['fenhong_max_money'] = bcadd($v['fenhong_max_money'],$fenhong_max,2);
                    }
                }
            }
			foreach($data as $k=>$v){
                $yejiwhere = [];
    			$yejiwhere[] = ['status','in','1,2,3'];
		        $downmids = \app\commons\Member::getdownmids(aid,$v['id'],99);
		        $data[$k]['teamyeji'] = Db::name('shop_order_goods')->where('aid',aid)->where('mid','in',$downmids)->where($yejiwhere)->sum('totalprice');
		        
                $downmids1 = \app\commons\Member::getdownmids_removemax(aid,$v['id'],99);
		        $data[$k]['teamyeji2'] = Db::name('shop_order_goods')->where('aid',aid)->where('mid','in',$downmids1)->where($yejiwhere)->sum('totalprice');
		        
                $data[$k]['downcount'] =count($downmids);
                $data[$k]['teamlevel'] = $teamArr[$v['teamlevel']];
                $data[$k]['storelevel'] = $storeArr[$v['storelevel']];
                if(getcustom('plug_yuebao')){
                    $data[$k]['self_yuebao_rate']     = $v['yuebao_rate'];
                    if($v['yuebao_rate']<0){
                        $data[$k]['yuebao_rate']      = $set['yuebao_rate'];
                    }

                    $data[$k]['self_yuebao_withdraw_time'] = $v['yuebao_withdraw_time'];
                    if($v['yuebao_withdraw_time']<0){
                        $data[$k]['yuebao_withdraw_time']  = $set['yuebao_withdraw_time'];
                    }
                }

				$data[$k]['levelname'] = $levelArr[$v['levelid']];
				if(getcustom('plug_sanyang')) {
                    $level_ids = Db::name('member_level_record')->where('aid', aid)->where('mid',$v['id'])->column('levelid');
                    if($level_ids) {
                        foreach ($level_ids as $lid)
                        $data[$k]['levelname'] .= '<br>'.$levelArr[$lid];
                    }
                }
				if(getcustom('member_level_down_commission')){
					$userlevel = Db::name('member_level')->field('id,name,down_level_totalcommission,down_level_id2')->where('aid', aid)->where('id',$v['levelid'])->find();
					$isauto_down = $v['isauto_down'];
					$showleveldown=true;
					if(!$v['isauto_down'] && $userlevel['down_level_totalcommission']>0){
						$leveldown = Db::name('member_level')->field('id,name')->where('id',$userlevel['down_level_id2'])->find();
						$leveldowncommission =$userlevel['down_level_totalcommission']-($v['totalcommission']-$v['down_commission']);
						$data[$k]['levelname'].='<br>还差'.$leveldowncommission.'佣金，降为'.$leveldown['name'];
					}else if($v['isauto_down']==1){
						$leveldown = Db::name('member_level')->field('id,name,recovery_level_proid')->where('id',$v['up_levelid'])->find();
						$proids = explode(',',$leveldown['recovery_level_proid']);
						$pro = Db::name('shop_product')->field('id,name')->where('id','in',$proids)->select()->toArray();
						$pros=[];
						foreach($pro as $p){
							$pros[] = $p['name'];
						}
						$data[$k]['levelname'].='<br>已降级，需购买'.implode(',',$pros).'才可恢复等级';
					}
				}

				if($v['pid']){
					$parent = Db::name('member')->where('aid',aid)->where('id',$v['pid'])->find();
				}else{
					$parent = array();
				}
                if($v['pid_origin']){
                    $parent_origin = Db::name('member')->where('aid',aid)->where('id',$v['pid_origin'])->find();
                }else{
                    $parent_origin = array();
                }
                if($v['_pid']){
					$_parent = Db::name('member')->where('aid',aid)->where('id',$v['_pid'])->find();
				}else{
					$_parent = array();
				}
                if(getcustom('plug_tengrui')){
                    //查询对应的小区
                    $community_room = Db::name('member_community_room')
                        ->where('mid',$v['id'])
                        ->where('is_del',0)
                        ->field('tr_roomName,tr_relationType')
                        ->select()
                        ->toArray();
                    if($community_room){
                        $community_infor = '';
                        foreach($community_room as $cv){
                            if($data[$k]['community_infor']){
                                $community_infor .= "<br> ".$cv['tr_roomName'];
                            }else{
                                $community_infor .= $cv['tr_roomName'];
                            }
                            if($cv['tr_relationType'] == 0){
                                $community_infor .= " 业主";
                            }else if($cv['tr_relationType'] == 1){
                                $community_infor .= " 家属";
                            }else if($cv['tr_relationType'] == 2){
                                $community_infor .= " 租户";
                            }else if($cv['tr_relationType'] == 3){
                                $community_infor .= " 买断";
                            }else if($cv['tr_relationType'] == 4){
                                $community_infor .= " 租用";
                            }
                            $data[$k]['community_infor'] = $community_infor;
                        }
                        unset($cv);
                    }else{
                        $data[$k]['community_infor'] = '';
                    }
                }
                if(getcustom('xixie')) {
                    $data[$k]['mendian_infor'] = '';
                    if($v['mdid']){
                        $mendian = Db::name('mendian')->where('id',$v['mdid'])->where('aid',aid)->field('id,name')->find();
                        if($mendian){
                            $data[$k]['mendian_infor'] = 'ID:'.$mendian['id']."<br>"."名称:".$mendian['name'];
                        }
                    }
                }
				$data[$k]['parent'] = $parent;
				$data[$k]['_parent'] = $_parent;
				
                $data[$k]['parent_origin'] = $parent_origin;
                $data[$k]['money'] = \app\commons\Member::getmoney($v);
                $data[$k]['score'] = \app\commons\Member::getscore($v);
                $data[$k]['money'] = dd_money_format($v['money'],$moeny_weishu2);
                $data[$k]['score'] = dd_money_format($v['score'],$score_weishu);
				if(getcustom('memberlist_showteamyeji')){
					$downmids = \app\commons\Member::getdownmids(aid,$v['id']);
					if($downmids){
						$teamyeji = Db::name('shop_order_goods')->where('aid',aid)->where('mid','in',$downmids)->where('status','in','1,2,3')->sum('real_totalprice');
						$data[$k]['teamyeji'] = $teamyeji;
                        $data[$k]['ordercount'] = Db::name('shop_order')->where('aid',aid)->where('mid','in',$downmids)->where('status','in','1,2,3')->count();
						$data[$k]['downcount'] = count($downmids);
					}else{
						$data[$k]['teamyeji'] = 0;
                        $data[$k]['ordercount'] = 0;
						$data[$k]['downcount'] = 0;
					}
				}
				
				//会员标签
				if(getcustom('member_tag')){
					if($v['tags']) $tags = explode(',',$v['tags']);
					 foreach ($tags as $t){
						  $data[$k]['tagname'] .= '<br>'.$tagdata[$t];
					 }
				}
				$data[$k]['commission'] = dd_money_format($v['commission'],$moeny_weishu);
                $data[$k]['commission_max'] = dd_money_format($v['commission_max'],$moeny_weishu);
                $data[$k]['totalcommission'] = dd_money_format($v['totalcommission'],$moeny_weishu);
				//查询股东分红上限数据
                if(getcustom('fenhong_max')){
                    if($v['fenhong_max']>0){
                        $data[$k]['gudong_max'] = $v['fenhong_max'];
                    }else{
                        $data[$k]['gudong_max'] = $fhlevellist[$v['levelid']]['fenhong_max_money']?:0;
                    }

                    $data[$k]['gudong_max'] = dd_money_format($data[$k]['gudong_max'],$moeny_weishu);
                    //查询会员已获得股东分红
                    $where_fenhong = [];
                    $where_fenhong[] = ['mid','=',$v['id']];
                    $where_fenhong[] = ['type','=','fenhong'];
                    $gudong_name = t('股东分红',aid);
                    $fenhong_total = Db::name('member_fenhonglog')
                        ->where($where_fenhong)
                        ->where('remark like "%'.$gudong_name.'%"')
                        ->sum('commission');
                    $data[$k]['gudong_total'] = bcmul($fenhong_total,1,2);
                }
  if(getcustom('ciruikang_fenxiao')){
                    $crk_up_info = '';
                    if($v['crk_up_levelid']>0){
                        $crklevel = Db::name('member_level')->where('id',$v['crk_up_levelid'])->field('id,name')->find();
                        if($crklevel){
                            $crk_up_info .= "升级等级：".$crklevel['name'];
                        }else{
                            $crk_up_info = '升级等级'.$v['crk_up_levelid'].'已失效';
                        }
                        $crk_up_info .= "<br>"." 升级购买数量：".$v['crk_up_pronum'];

                        //查询已赠送的数量
                        $givenum = Db::name('shop_order')->where('mid',$v['id'])->where('crk_givenum','>',0)->where('aid',aid)->sum('crk_givenum');
                        $givenum = $givenum??0;
                        $crk_up_info .= "<br>"." 订单赠送数量：".$givenum;
                    }
                    $data[$k]['crk_up_info'] = $crk_up_info;
                }
                if(getcustom('memberlist_showteamyeji')){
                    if(input('param.teamyeji_time') ){
                        $teamyeji_time = explode(' ~ ',input('param.teamyeji_time'));
                        $date_start2 = strtotime($teamyeji_time[0]);
                        $date_end2 = strtotime($teamyeji_time[1]) + 86399;
                    }
                }
                if(getcustom('memberlist_showteamyeji')){
                    $yejiwhere = [];
                    $yejiwhere[] = ['status','in','1,2,3'];
                    if($date_start2 && $date_end2){
                        $yejiwhere[] = ['createtime','between',[$date_start2,$date_end2]];
                    }
                    $downmids = \app\commons\Member::getdownmids(aid,$v['id']);
                    if($downmids){
                        $teamyeji = Db::name('shop_order_goods')->where('aid',aid)->where('mid','in',$downmids)->where($yejiwhere)->sum('real_totalprice');
                        $data[$k]['teamyeji'] = $teamyeji;
                        $data[$k]['ordercount'] = Db::name('shop_order')->where('aid',aid)->where('mid','in',$downmids)->where($yejiwhere)->count();
                        $data[$k]['downcount'] = count($downmids);
                    }else{
                        $data[$k]['teamyeji'] = 0;
                        $data[$k]['ordercount'] = 0;
                        $data[$k]['downcount'] = 0;
                    }
                }
                //会员标签
                if(getcustom('member_tag')){
                    if($v['tags']){ 
						$tags = explode(',',$v['tags']);
						foreach ($tags as $t){
							$data[$k]['tagname'] .= '<br>'.$tagdata[$t];
						}
					}else{
						$data[$k]['tagname'] = '暂无';
					}
                }
                if(getcustom('member_business')){
                    if($v['bid'] > 0){
                        $data[$k]['bname'] = Db::name('business')->where('aid',aid)->where('id',$v['bid'])->value('name');
                    }else{
                        $data[$k]['bname'] = '平台';
                    }
                }
                if(getcustom('shop_label')){
                    $labelnames = '无';
                    if(!empty($v['labelid'])){
                        $labels = Db::name('shop_label')->where('id','in',$v['labelid'])->where('aid',aid)->order('sort desc,id desc')->column('name');
                        if($labels){
                            $labelnames = implode(' ',$labels);
                        }
                    }
                    $data[$k]['labelnames'] = $labelnames;
                }
                //新增优惠券数量
                $coupon_count = 0 + Db::name('coupon_record')->where('aid',aid)->where('mid',$v['id'])->where('status',0)->count();
                $data[$k]['coupon_count'] =$coupon_count;
			}
		
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'role'=>$role]);
		}
		

		if($this->auth_data == 'all' || in_array('Member/*',$this->auth_data) || in_array('Member/recharge',$this->auth_data)){
			$haverechargeAuth = true;
		}else{
			$haverechargeAuth = false;
		}
		
		if($this->auth_data == 'all' || in_array('Member/*',$this->auth_data) || in_array('Member/addmoney',$this->auth_data)){
			$haverechargeAuth = true;
		}else{
			$haverechargeAuth = false;
		}
		
		
		if($this->auth_data == 'all' || in_array('Member/*',$this->auth_data) || in_array('Member/addscore',$this->auth_data)){
			$haveaddscoreAuth = true;
		}else{
			$haveaddscoreAuth = false;
		}
		if($this->auth_data == 'all' || in_array('Member/*',$this->auth_data) || in_array('Member/addcommission',$this->auth_data)){
			$haveaaddcommissionAuth = true;
		}else{
			$haveaaddcommissionAuth = false;
		}
        if(getcustom('pay_yuanbao') && ($this->auth_data == 'all' || in_array('Member/*',$this->auth_data) || in_array('Member/addyuanbao',$this->auth_data))){
            $haveaddyuanbaoAuth = true;
        }else{
            $haveaddyuanbaoAuth = false;
        }
        if(getcustom('member_friend') && ($this->auth_data == 'all' || in_array('Friend/*',$this->auth_data) || in_array('Friend/index',$this->auth_data))){
            View::assign('haveFriendAuth',true);
        }
        if(getcustom('member_overdraft_money') && ($this->auth_data == 'all' || in_array('OverdraftMoney/recharge',$this->auth_data))){
            View::assign('rechargeOverdraftMoneyAuth',true);
        }
	if(getcustom('product_service_fee') && ($this->auth_data == 'all' || in_array('Member/*',$this->auth_data) || in_array('Member/addServiceFee',$this->auth_data))){
            $haveaddservicefeeAuth = true;
        }else{
            $haveaddservicefeeAuth = false;
        }
if($this->auth_data == 'all' || in_array('Member/*',$this->auth_data) || in_array('Member/addcredit1',$this->auth_data)){
			$haveaddcredit1Auth = true;
		}else{
			$haveaddcredit1Auth = false;
		}
		
		if($this->auth_data == 'all' || in_array('Member/*',$this->auth_data) || in_array('Member/addcredit2',$this->auth_data)){
			$haveaddcredit2Auth = true;
		}else{
			$haveaddcredit2Auth = false;
		}
		if($this->auth_data == 'all' || in_array('Member/*',$this->auth_data) || in_array('Member/addcredit3',$this->auth_data)){
			$haveaddcredit3Auth = true;
		}else{
			$haveaddcredit3Auth = false;
		}
		
		if($this->auth_data == 'all' || in_array('Member/*',$this->auth_data) || in_array('Member/addcredit4',$this->auth_data)){
			$haveaddcredit4Auth = true;
		}else{
			$haveaddcredit4Auth = false;
		}
		if($this->auth_data == 'all' || in_array('Member/*',$this->auth_data) || in_array('Member/addcredit5',$this->auth_data)){
			$haveaddcredit5Auth = true;
		}else{
			$haveaddcredit5Auth = false;
		}
		
		View::assign('aid',aid);
		View::assign('levelArr',$levelArr);
		$sort = true;
        if(getcustom('w7moneyscore')) {
            $w7moneyscore = db('admin_set')->where(['aid'=>aid])->value('w7moneyscore');
            if($w7moneyscore == 1)
            $sort = false;
        }

		$adminset = Db::name('admin_set')->where('aid',aid)->find();
        $admin = Db::name('admin')->where('id',aid)->find();

        View::assign('haveaddcredit1Auth',$haveaddcredit1Auth);
        View::assign('haveaddcredit2Auth',$haveaddcredit2Auth);
        View::assign('haveaddcredit3Auth',$haveaddcredit3Auth);
        
            View::assign('haveaddcredit4Auth',$haveaddcredit4Auth);
          View::assign('haveaddcredit5Auth',$haveaddcredit5Auth);
         View::assign('storeArr',$storeArr);
         View::assign('teamArr',$teamArr);
        View::assign('haverechargeAuth',$haverechargeAuth);
        View::assign('haveaddscoreAuth',$haveaddscoreAuth);
        View::assign('haveaddservicefeeAuth',$haveaddservicefeeAuth);
        View::assign('haveaaddcommissionAuth',$haveaaddcommissionAuth);
        View::assign('haveaddyuanbaoAuth',$haveaddyuanbaoAuth);
        View::assign('sort',$sort);
        View::assign('adminset',$adminset);
        View::assign('admin',$admin);
        if(getcustom('member_gongxian')){
            if($admin['member_gongxian_status']){
                if($this->auth_data == 'all' || in_array('Member/addgongxian',$this->auth_data)){
                    View::assign('haveaddgongxianAuth',true);
                }else{
                    View::assign('haveaddgongxianAuth',false);
                }
            }
        }
        if(getcustom('xixie')) {
            $mendian = Db::name('mendian')
                ->where('aid',aid)
                ->select()
                ->toArray();
            if(!$mendian){
                $mendian = [];
            }
            View::assign('mendian',$mendian);
        }
        if(getcustom('other_money')){
            //是否有多账户权限
            $othermoney_status = Db::name('admin')->where('id',aid)->value('othermoney_status');
            if($othermoney_status != 1){
                View::assign('othermoney_status',false);
            }else{
                View::assign('othermoney_status',true);
            }
            View::assign('haveaddmoney2Auth',true);
            View::assign('haveaddmoney3Auth',true);
            View::assign('haveaddmoney4Auth',true);
            View::assign('haveaddmoney5Auth',true);
            View::assign('haveaddfrozen_moneyAuth',true);
            if($this->auth_data != 'all' && !in_array('Member/*',$this->auth_data)){
                if(!in_array('Member/addmoney2',$this->auth_data)){
                    View::assign('haveaddmoney2Auth',false);
                }
                if(!in_array('Member/addmoney3',$this->auth_data)){
                    View::assign('haveaddmoney3Auth',false);
                }
                if(!in_array('Member/addmoney4',$this->auth_data)){
                    View::assign('haveaddmoney4Auth',false);
                }
                if(!in_array('Member/addmoney5',$this->auth_data)){
                    View::assign('haveaddmoney5Auth',false);
                }
                if(!in_array('Member/addfrozen_money',$this->auth_data)){
                    View::assign('haveaddfrozen_moneyAuth',false);
                }
            }
        }

		
		$business_selfscore = 0;
		if(getcustom('business_selfscore')){
			$bset = Db::name('business_sysset')->where('aid',aid)->find();
			if($bset['business_selfscore']==1 && $bset['business_selfscore2']==1){
				$business_selfscore = 1;
			}
		}

		View::assign('business_selfscore',$business_selfscore);
        if(getcustom('member_business')){
            if(!bid){
                $blist = Db::name('business')->where('aid',aid)->field('id,name')->select()->toArray();
                View::assign('blist',$blist);
            }
            if($this->auth_data == 'all' || in_array('MemberBusiness',$this->auth_data)){
                $showbusiness = true;
            }else{
                $showbusiness = false;
            }
            View::assign('showbusiness',$showbusiness);
        }
		if(getcustom('member_tag')){
			View::assign('taglist',$tagdata);
		}
        if(getcustom('member_goldmoney_silvermoney')){
            $showgoldmoney = true;$showsilvermoney = true;
            //平台权限
            if($this->auth_data != 'all' && !in_array('Member/addGoldmoney',$this->auth_data)){
                $showgoldmoney = false;
            }
            if($this->auth_data != 'all' && !in_array('Member/addSilvermoney',$this->auth_data)){
                $showsilvermoney = false;
            }
            View::assign('showgoldmoney',$showgoldmoney);
            View::assign('showsilvermoney',$showsilvermoney);
        }
		if(getcustom('hotel')){
			$text = \app\models\Hotel::gettext(aid);
			View::assign('text',$text);
		}
		return View::fetch();
    }
	//导出
	public function excel(){
        set_time_limit(0);
        ini_set('memory_limit',-1);
		$levelList = Db::name('member_level')->where('aid',aid)->select()->toArray();
		$levelArr = array();
		 $teamArr = Db::name('member_team_level')->where('aid',aid)->column('name','id');
		  $storeArr = Db::name('member_store')->where('aid',aid)->column('name','id');
		foreach($levelList as $v){
			$levelArr[$v['id']] = $v['name'];
		}

		if(input('param.field') && input('param.order')){
			$order = input('param.field').' '.input('param.order');
		}else{
			$order = 'id desc';
		}
        $page = input('param.page')?:1;
        $limit = input('param.limit')?:10;
		$where = [];
		$where[] = ['aid','=',aid];
        if(getcustom('member_business')){
            if(bid){
                $where[] = ['bid','=',bid];
            }else{
                if(input('?param.bid') && input('param.bid')!=='') $where[] = ['bid','=',input('param.bid')];
            }
        }
		if(input('param.mid')) $where[] = ['id','=',input('param.mid')];
		if(input('param.pid')) $where[] = ['pid','=',input('param.pid')];
		if(input('param.isgetcard')){
			if(input('param.isgetcard') == 1){
				$where[] = ['','exp',Db::raw('card_code is not null')];
			}else{
				$where[] = ['','exp',Db::raw('card_code is null')];
			}
		}
		if(input('param._pid')) $where[] = ['_pid','=',input('param._pid')];
		if(input('param.storelevel')) $where[] = ['storelevel','=',input('param.storelevel')]; 
		if(input('param.teamlevel')) $where[] = ['teamlevel','=',input('param.teamlevel')];
		if(input('param.nickname')) $where[] = ['nickname|tel|realname','like','%'.input('param.nickname').'%'];
		if(input('param.realname')) $where[] = ['realname','like','%'.input('param.realname').'%'];
		if(input('param.levelid')) $where[] = ['levelid','=',input('param.levelid')];
		if(input('param.ctime') ){
			$ctime = explode(' ~ ',input('param.ctime'));
			$where[] = ['createtime','>=',strtotime($ctime[0])];
			$where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
		}

		if(input('param.fhid')){
			$midlist = Db::name('shop_order_fenhong')->where('aid',aid)->where('id',input('param.fhid'))->value('midlist');
			$where[] = ['id','in',$midlist];
		}

        if(getcustom('plug_tengrui')){
            if(input('?param.tr_is_rzh') && input('param.tr_is_rzh')!==''){
                $where[] = ['tr_is_rzh','=',input('param.tr_is_rzh')];
            }
        }
        if(getcustom('xixie')){
            if(input('?param.mdid') && input('param.mdid')!==''){
                $where[] = ['mdid','=',input('param.mdid')];
            }
        }
		

		if(input('param.fxmid')){
			$fxmid = input('param.fxmid/d');
			if(input('param.deep') == 1){
				$where[] = ['pid','=',$fxmid];
			}elseif(input('param.deep') == 2){
				$where[] = Db::raw("pid in(select id from ".table_name('member')." where pid=".$fxmid.")");
			}elseif(input('param.deep') == 3){
				$where[] = Db::raw("pid in(select id from ".table_name('member')." where pid in(select id from ".table_name('member')." where pid=".$fxmid."))");
			}
		}
		if(getcustom('member_tag')){
			if(input('?param.tagid') && input('param.tagid')!==''){
				$where[] = Db::raw("find_in_set(".input('param.tagid/d').",tags)");
			}
		}

		$list = Db::name('member')->where($where)->order($order)->page($page,$limit)->select()->toArray();
        $count = Db::name('member')->where($where)->order($order)->count();

        $title = array('ID','头像','昵称','来源','推荐人','省份','城市','姓名','电话',t('余额'),t('积分'),t('佣金'),'等级','加入时间','关注状态','公众号openid','小程序openid');
        if(getcustom('member_business')){
            if(!bid && ($this->auth_data == 'all' || in_array('MemberBusiness',$this->auth_data))){
                $title = array('ID','头像','昵称','所属商家','来源','推荐人','省份','城市','姓名','电话',t('余额'),t('积分'),t('佣金'),'等级','加入时间','关注状态','公众号openid','小程序openid');
            }
        }
        elseif(getcustom('plug_tengrui')){
            $title = array('ID','头像','昵称','来源','省份','城市','姓名','电话',t('余额'),t('积分'),t('佣金'),'等级','加入时间','关注状态','是否认证','小区','公众号openid','小程序openid');
        }
        elseif(getcustom('pay_yuanbao')){
            $title = array('ID','头像','昵称','来源','推荐人','省份','城市','姓名','电话',t('余额'),t('积分'),t('佣金'),t('元宝'),'等级','加入时间','关注状态','公众号openid','小程序openid');
        }
        elseif(getcustom('memberlist_showteamyeji')){
            $title = array('ID','头像','昵称','来源','推荐人','省份','城市','姓名','电话',t('余额'),t('积分'),t('佣金'),'等级','团队人数','团队业绩','团队订单数','加入时间','关注状态','公众号openid','小程序openid');
        }
		elseif(getcustom('member_tag')){
			$title = array('ID','头像','昵称','来源','推荐人',t('会员').'标签','省份','城市','姓名','电话',t('余额'),t('积分'),t('佣金'),'等级','加入时间','关注状态','公众号openid','小程序openid');
		}
        elseif(getcustom('shop_label')){
            $title[] = '商品标签';
        }
	
        $moeny_weishu = 2;
        if(getcustom('fenhong_money_weishu')){
            $moeny_weishu = Db::name('admin_set')->where('aid',aid)->value('fenhong_money_weishu');
        }
        $moeny_weishu2 = 2;
        if(getcustom('member_money_weishu')){
            $moeny_weishu2 = Db::name('admin_set')->where('aid',aid)->value('member_money_weishu');
        }
		$data = array();
		foreach($list as $k=>$vo){
			if($vo['platform']=='wx'){
				$vo['platform'] = '小程序';
			}elseif($vo['platform']=='mp'){
				$vo['platform'] = '公众号';
			}elseif($vo['platform']=='h5'){
				$vo['platform'] = 'H5';
			}elseif($vo['platform']=='wx_channels'){
                $vo['platform'] = '视频号小店';
            }
			if($vo['subscribe']==1){
				$vo['subscribe'] = '已关注';
			}else{
				$vo['subscribe'] = '未关注';
			}
            $vo['money'] = \app\commons\Member::getmoney($vo);
            $vo['score'] = \app\commons\Member::getscore($vo);
			$vo['levelname'] = $levelArr[$vo['levelid']];
			$vo['teamname'] = $teamArr[$vo['teamlevel']];
			$vo['storename'] = $storeArr[$vo['storelevel']];
			//上级
            $pmember = Db::name('member')->where('id',$vo['pid'])->field('id,nickname')->find();
            if($pmember){
                $vo['pmember'] = $pmember['nickname'].'(ID:'.$pmember['id'].')';
            }else{
                $vo['pmember'] = '暂无';
            }
            $vo['commission'] = dd_money_format($vo['commission'],$moeny_weishu);
            $vo['money']      = dd_money_format($vo['money'],$moeny_weishu2);

            $newdata = array($vo['id'],$vo['headimg'],$vo['nickname'],$vo['platform'],$vo['pmember'],$vo['province'],$vo['city'],$vo['realname'],$vo['tel'],$vo['money'],$vo['score'],$vo['commission'],$vo['levelname'],date('Y-m-d H:i',$vo['createtime']),$vo['subscribe'],$vo['mpopenid'],$vo['wxopenid']);
            if(getcustom('member_business')){
                if(!bid && ($this->auth_data == 'all' || in_array('MemberBusiness',$this->auth_data))){
                    if($vo['bid'] > 0){
                        $bname = Db::name('business')->where('aid',aid)->where('id',$vo['bid'])->value('name');
                    }else{
                        $bname = '平台';
                    }
                    $newdata = array($vo['id'],$vo['headimg'],$vo['nickname'],$bname,$vo['platform'],$vo['pmember'],$vo['province'],$vo['city'],$vo['realname'],$vo['tel'],$vo['money'],$vo['score'],$vo['commission'],$vo['levelname'],date('Y-m-d H:i',$vo['createtime']),$vo['subscribe'],$vo['mpopenid'],$vo['wxopenid']);
                }
            }else if(getcustom('xixie')) {
                $vo['mendian_infor'] = '无';
                if($vo['mdid']){
                    $mendian = Db::name('mendian')->where('id',$vo['mdid'])->where('aid',aid)->field('id,name')->find();
                    if($mendian){
                        $vo['mendian_infor'] = 'ID:'.$mendian['id']."\n\r"."名称:".$mendian['name'];
                    }
                }
                $newdata = array($vo['id'],$vo['headimg'],$vo['nickname'],$vo['mendian_infor'],$vo['platform'],$vo['province'],$vo['city'],$vo['realname'],$vo['tel'],$vo['money'],$vo['score'],$vo['commission'],$vo['levelname'],date('Y-m-d H:i',$vo['createtime']),$vo['subscribe'],$vo['mpopenid'],$vo['wxopenid']);
            }else if(getcustom('plug_tengrui')){
                if($vo['tr_is_rzh']==1){
                    $vo['tr_is_rzh'] = '已认证';
                }else{
                    $vo['tr_is_rzh'] = '未认证';
                }
                //查询对应的小区
                $community_room = Db::name('member_community_room')
                    ->where('mid',$vo['id'])
                    ->where('is_del',0)
                    ->field('tr_roomName,tr_relationType')
                    ->select()
                    ->toArray();
                if($community_room){
                    foreach($community_room as $cv){
                        if($community_infor){
                            $community_infor .= " \n\r ".$cv['tr_roomName'];
                        }else{
                            $community_infor = $cv['tr_roomName'];
                        }
                        if($cv['tr_relationType'] == 0){
                            $community_infor .= " 业主";
                        }else if($cv['tr_relationType'] == 1){
                            $community_infor .= " 家属";
                        }else if($cv['tr_relationType'] == 2){
                            $community_infor .= " 租户";
                        }else if($cv['tr_relationType'] == 3){
                            $community_infor .= " 买断";
                        }else if($cv['tr_relationType'] == 4){
                            $community_infor .= " 租用";
                        }
                    }
                    unset($cv);
                }else{
                    $community_infor = '';
                }
                $newdata = array($vo['id'],$vo['headimg'],$vo['nickname'],$vo['platform'],$vo['province'],$vo['city'],$vo['realname'],$vo['tel'],$vo['money'],$vo['score'],$vo['commission'],$vo['levelname'],date('Y-m-d H:i',$vo['createtime']),$vo['subscribe'],$vo['tr_is_rzh'],$community_infor,$vo['mpopenid'],$vo['wxopenid']);
            }else if(getcustom('pay_yuanbao')){
                $newdata = array($vo['id'],$vo['headimg'],$vo['nickname'],$vo['platform'],$vo['pmember'],$vo['province'],$vo['city'],$vo['realname'],$vo['tel'],$vo['money'],$vo['score'],$vo['commission'],$vo['yuanbao'],$vo['levelname'],date('Y-m-d H:i',$vo['createtime']),$vo['subscribe'],$vo['mpopenid'],$vo['wxopenid']);
            }else if(getcustom('memberlist_showteamyeji')){
                
                if(input('param.teamyeji_time') ){
                    $teamyeji_time = explode(' ~ ',input('param.teamyeji_time'));
                    $date_start2 = strtotime($teamyeji_time[0]);
                    $date_end2 = strtotime($teamyeji_time[1]) + 86399;
                }               
                
                $yejiwhere = [];
                $yejiwhere[] = ['status','in','1,2,3'];
                if($date_start2 && $date_end2){
                    $yejiwhere[] = ['createtime','between',[$date_start2,$date_end2]];
                }
                $downmids = \app\commons\Member::getdownmids(aid,$vo['id']);
                if($downmids){
                    $teamyeji = Db::name('shop_order_goods')->where('aid',aid)->where('mid','in',$downmids)->where($yejiwhere)->sum('real_totalprice');
                    $vo['teamyeji'] = $teamyeji;
                    $vo['ordercount'] = Db::name('shop_order')->where('aid',aid)->where('mid','in',$downmids)->where($yejiwhere)->count();
                    $vo['downcount'] = count($downmids);
                }else{
                    $vo['teamyeji'] = 0;
                    $vo['ordercount'] = 0;
                    $vo['downcount'] = 0;
                }
                
                $newdata = array($vo['id'],$vo['headimg'],$vo['nickname'],$vo['platform'],$vo['pmember'],$vo['province'],$vo['city'],$vo['realname'],$vo['tel'],$vo['money'],$vo['score'],$vo['commission'],$vo['levelname'],$vo['downcount'],$vo['teamyeji'],$vo['ordercount'],date('Y-m-d H:i',$vo['createtime']),$vo['subscribe'],$vo['mpopenid'],$vo['wxopenid']);
            }elseif(getcustom('member_tag')){
				$tagdata = Db::name('member_tag')->where('aid',aid)->column('name','id');
				if($vo['tags']){ 
					$tags = explode(',',$vo['tags']);
					foreach ($tags as $t){
						$tagname .= $tagdata[$t].",";
					}
				}else{
					$tagname = '';
				}
				 $newdata = array($vo['id'],$vo['headimg'],$vo['nickname'],$vo['platform'],$vo['pmember'],$vo['tagname'],$vo['province'],$vo['city'],$vo['realname'],$vo['tel'],$vo['money'],$vo['score'],$vo['commission'],$vo['levelname'],date('Y-m-d H:i',$vo['createtime']),$vo['subscribe'],$vo['mpopenid'],$vo['wxopenid'].$tagname);
			}
            if(getcustom('shop_label')){
                $labelnames = '无';
                if($vo['labelid'] > 0){
                    $labels = Db::name('shop_label')->where('id','in',$vo['labelid'])->where('aid',aid)->order('sort desc,id desc')->column('name');
                    if($labels){
                        $labelnames = implode(' ',$labels);
                    }
                }
                $newdata[] = $labelnames;
            }
            $data[] = $newdata;
		}
        return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'title'=>$title]);
		$this->export_excel($title,$data);
	}
	//导入
	public function importexcel(){
		set_time_limit(0);
		ini_set('memory_limit',-1);
		$file = input('post.file');
		$exceldata = $this->import_excel($file);
		$levelList = Db::name('member_level')->where('aid',aid)->select()->toArray();
		$levelArr = array();
		foreach($levelList as $v){
			$levelArr[$v['name']] = $v['id'];
		}
		$defaultlevel = Db::name('member_level')->where('aid',aid)->where('isdefault',1)->find();

		$insertnum = 0;
		$updatenum = 0;
		foreach($exceldata as $data){
			if($data[6]){
				$indata = [];
				$indata['headimg'] = $data[0] ? $data[0] : PRE_URL.'/static/img/touxiang.png';
				$indata['nickname'] = preg_replace('/[\x{10000}-\x{10FFFF}]/u', ' ', $data[1]);
				$indata['platform'] = $this->getplatform(trim($data[2]));
				$indata['province'] = trim($data[3]);
				$indata['city'] = trim($data[4]);
				$indata['realname'] = trim($data[5]);
				$indata['tel'] = trim($data[6]);
				$indata['money'] = trim($data[7]);
				$indata['score'] = trim($data[8]);
				$indata['commission'] = trim($data[9]);
				$indata['levelid'] = $levelArr[$data[10]] ? $levelArr[$data[10]] : $defaultlevel['id'];
				$indata['subscribe'] = ($data[11] == '已关注' ? 1 : 0);
				$indata['mpopenid'] = trim($data[12]);
				$indata['wxopenid'] = trim($data[13]);
				if($data[14] && trim($data[14])!=''){
					$indata['pwd'] = md5(trim($data[14]));
				}
				$member = [];
				if(!$member && $indata['wxopenid']){
					$member = Db::name('member')->where('aid',aid)->where('wxopenid',$indata['wxopenid'])->find();
				}
				if(!$member && $indata['mpopenid']){
					$member = Db::name('member')->where('aid',aid)->where('mpopenid',$indata['mpopenid'])->find();
				}
				if(!$member && $indata['tel']){
					$member = Db::name('member')->where('aid',aid)->where('tel',$indata['tel'])->find();
				}
                $update = $indata;
//				if($indata['money'] === '') unset($indata['money']);
//				if($indata['score'] === '') unset($indata['score']);
//				if($indata['commission'] === '') unset($indata['commission']);
                //通过统一方法更新以下字段
                unset($indata['money']);
                unset($indata['score']);
                unset($indata['commission']);
				if($indata['mpopenid'] == '') unset($indata['mpopenid']);
				if($indata['wxopenid'] == '') unset($indata['wxopenid']);
				if($indata['tel'] == '') unset($indata['tel']);
				if($member){
					if($indata['headimg'] == PRE_URL.'/static/img/touxiang.png') unset($indata['headimg']);
					if($indata['nickname'] == '') unset($indata['nickname']);
					if($indata['platform'] == '') unset($indata['platform']);
					if($indata['province'] == '') unset($indata['province']);
					if($indata['city'] == '') unset($indata['city']);
					if($indata['realname'] == '') unset($indata['realname']);
					if($indata['tel'] == '') unset($indata['tel']);
					if($indata['levelid'] == '') unset($indata['levelid']);
					if($indata['subscribe'] == '') unset($indata['subscribe']);
					if(getcustom('member_import')){
                        if($data[16]) $data[16] = preg_replace('/[\x{10000}-\x{10FFFF}]/u', ' ', $data[16]);
						if(trim($data[15]) && trim($data[16])){
							$pmember = Db::name('member')->where(['aid'=>aid,'tel'=>$data[15],'nickname'=>$data[16]])->find();
						}elseif(trim($data[15])){
							$pmember = Db::name('member')->where(['aid'=>aid,'tel'=>$data[15]])->find();
						}elseif(trim($data[16])){
							$pmember = Db::name('member')->where(['aid'=>aid,'tel'=>$data[16]])->find();
						}
						$indata['id'] = $member['id'];
						$mid = \app\models\Member::edit(aid,$indata);
                        if(!empty($update['money'])) {
                            \app\commons\Member::addmoney(aid,$member['id'],$member['money']*-1,'导入编辑会员');
                            \app\commons\Member::addmoney(aid,$member['id'],$update['money'],'导入编辑会员');
                        }
                        if(!empty($update['score'])) {
                            \app\commons\Member::addscore(aid,$member['id'],$member['score']*-1,'导入编辑会员');
                            \app\commons\Member::addscore(aid,$member['id'],$update['score'],'导入编辑会员');
                        }
                        if(!empty($update['commission'])) {
                            \app\commons\Member::addcommission(aid,$member['id'],0,$member['commission']*-1,'导入编辑会员');
                            \app\commons\Member::addcommission(aid,$member['id'],0,$update['commission'],'导入编辑会员');
                        }
					}else{
						Db::name('member')->where('aid',aid)->where('id',$member['id'])->update($indata);
                        if(!empty($update['money'])) {
                            \app\commons\Member::addmoney(aid,$member['id'],$member['money']*-1,'导入编辑会员');
                            \app\commons\Member::addmoney(aid,$member['id'],$update['money'],'导入编辑会员');
                        }
                        if(!empty($update['score'])) {
                            \app\commons\Member::addscore(aid,$member['id'],$member['score']*-1,'导入编辑会员');
                            \app\commons\Member::addscore(aid,$member['id'],$update['score'],'导入编辑会员');
                        }
                        if(!empty($update['commission'])) {
                            \app\commons\Member::addcommission(aid,$member['id'],0,$member['commission']*-1,'导入编辑会员');
                            \app\commons\Member::addcommission(aid,$member['id'],0,$update['commission'],'导入编辑会员');
                        }
					}
                    \app\commons\System::plog('导入编辑会员'.$member['id']);
					$updatenum++;
				}else{
					if(getcustom('member_import')){
						if(trim($data[15]) && trim($data[16])){
							$pmember = Db::name('member')->where(['aid'=>aid,'tel'=>$data[15],'nickname'=>$data[16]])->find();
						}elseif(trim($data[15])){
							$pmember = Db::name('member')->where(['aid'=>aid,'tel'=>$data[15]])->find();
						}elseif(trim($data[16])){
							$pmember = Db::name('member')->where(['aid'=>aid,'nickname'=>$data[16]])->find();
						}
						$indata['aid'] = aid;
						$indata['sex'] = 3;
						$indata['createtime'] = time();
						$indata['pid'] = $pmember['id'];
                        if(getcustom('member_business')){
                            //商户注册会员
                            if(bid){
                                $indata['bid'] = bid;
                            }
                        }
						$mid = \app\models\Member::add(aid,$indata);
                        if(!empty($update['money'])) \app\commons\Member::addmoney(aid,$mid,$update['money'],'导入添加会员');
                        if(!empty($update['score']))  \app\commons\Member::addscore(aid,$mid,$update['score'],'导入添加会员');
                        if(!empty($update['commission'])) \app\commons\Member::addcommission(aid,$mid,0,$update['commission'],'导入添加会员',1);
                        \app\commons\System::plog('导入添加会员'.$mid);
					}else{		
						$indata['aid'] = aid;
						$indata['sex'] = 3;
						$indata['createtime'] = time();
                        if(getcustom('member_business')){
                            //商户注册会员
                            if(bid){
                                $indata['bid'] = bid;
                            }
                        }
                        $mid = Db::name('member')->insertGetId($indata);
                        if(!empty($update['money'])) \app\commons\Member::addmoney(aid,$mid,$update['money'],'导入添加会员');
                        if(!empty($update['score']))  \app\commons\Member::addscore(aid,$mid,$update['score'],'导入添加会员');
                        if(!empty($update['commission'])) \app\commons\Member::addcommission(aid,$mid,0,$update['commission'],'导入添加会员',1);
                        \app\commons\System::plog('导入添加会员'.$mid);
					}
					$insertnum++;
				}
			}
		}
		\app\commons\System::plog('导入会员');
		return json(['status'=>1,'msg'=>'成功新增'.$insertnum.'条数据，修改'.$updatenum.'条数据']);
	}
	public function getplatform($str){
		if($str == '公众号'){
			return 'mp';
		}elseif($str == '小程序'){
			return 'wx';
		}elseif($str == 'H5'){
			return 'h5';
		}else{
			return $str;
		}
	}
	public function rechargenew(){
        if($this->auth_data != 'all' && !in_array('Member/*',$this->auth_data) && !in_array('Member/recharge',$this->auth_data)){
            return json(['status'=>0,'msg'=>'无权限']);
        }

		$mid = input('post.rechargemid/d');
        $where = [];
        $where[] = ['id','=',$mid];
        if(getcustom('member_business')){
            if(bid){
                $where[] = ['bid','=',bid];
            }
        }
        $where[] = ['aid','=',aid];
        $count = Db::name('member')->where($where)->count('id');
        if(!$count){
             return json(['status'=>0,'msg'=>t('会员').'不存在']);
        }

	}
		//会员充值
	public function addmoney(){
		$mid = input('post.id/d');
		$money = floatval(input('post.money'));
		$actionname = '充值';
		if($money == 0 || $money == ''){
			return json(['status'=>0,'msg'=>'请输入金额']);
		}
		if($money < 0) $actionname = '扣费';
		$remark = $remark ? (t('后台修改')?t('后台修改').'：'.$remark:$remark) : t('后台修改');
		$rs = \app\commons\Member::addmoney(aid,$mid,$money,$remark);
		\app\commons\System::plog('给会员'.$mid.$actionname.'，金额'.$money);
		if($rs['status']==0) return json($rs);
		return json(['status'=>1,'msg'=>$actionname.'成功']);
	}
		public function recharge(){
	
		if($this->auth_data != 'all' && !in_array('Member/*',$this->auth_data) && !in_array('Member/recharge',$this->auth_data)){
            return json(['status'=>0,'msg'=>'无权限']);
        }

		$mid = input('post.rechargemid/d');
        $where = [];
        $where[] = ['id','=',$mid];
        if(getcustom('member_business')){
            if(bid){
                $where[] = ['bid','=',bid];
            }
        }
        $where[] = ['aid','=',aid];
        $count = Db::name('member')->where($where)->count('id');
        if(!$count){
             return json(['status'=>0,'msg'=>t('会员').'不存在']);
        }
		$money = floatval(input('post.rechargemoney'));
        $type = input('post.rechargetype');
		$actionname = '充值';
		if($money == 0 || $money == ''){
			return json(['status'=>0,'msg'=>'请输入金额']);
		}
		if($money < 0) $actionname = '扣费';
		if(session('IS_ADMIN')==0){
			$user = Db::name('admin_user')->where('aid',aid)->where('id',$this->uid)->find();
			$remark = '商家'.$actionname.'，操作员：'.$user['un'];
		}else{
			$remark = '商家'.$actionname;
		}
		$remark1 =  input('post.remark');
		if($remark1) $remark = $remark1;


		$rs = \app\commons\Member::addmoney(aid,$mid,$money,$remark,0,$type);
		\app\commons\System::plog('给会员'.$mid.$actionname.'，金额'.$money);
		if($rs['status']==0) return json($rs);
		
		
	    $ordernum = date('ymdHis').aid.rand(1000,9999);
		//增加消费记录
		$orderdata = [];
		$orderdata['aid'] = aid;
		$orderdata['mid'] = $mid;
		$orderdata['createtime']= time();
		$orderdata['money'] = $money;
		$orderdata['ordernum'] = $ordernum;
		$orderdata['paytype'] = $type;
		$orderdata['status'] = 1;
		$orderdata['paytime'] = time();
			$orderdata['remark'] = $remark;
		$orderid = Db::name('recharge_order')->insertGetId($orderdata);
        //充值余额
        if(getcustom('sms_temp_money_recharge')){
            $tel = Db::name('member')->where('aid',aid)->where('id',$mid)->value('tel');
            if($tel){
                $rs = \app\commons\Sms::send(aid,$tel,'tmpl_money_recharge',['money'=>$money,'givemoney'=>0]);
            }
        }
		return json(['status'=>1,'msg'=>$actionname.'成功']);
	}
	//充值积分
	public function addscore(){
        if($this->auth_data != 'all' && !in_array('Member/*',$this->auth_data) && !in_array('Member/addscore',$this->auth_data)){
            return json(['status'=>0,'msg'=>'无权限']);
        }

		$mid = input('post.id/d');
        $where = [];
        $where[] = ['id','=',$mid];
        if(getcustom('member_business')){
            if(bid){
                $where[] = ['bid','=',bid];
            }
        }
        $where[] = ['aid','=',aid];
        $count = Db::name('member')->where($where)->count('id');
        if(!$count){
             return json(['status'=>0,'msg'=>t('会员').'不存在']);
        }

		$score = input('post.money');
		$remark = input('post.remark');
        $actionname = '增加';
		if($score == 0){
			return json(['status'=>0,'msg'=>'请输入'.t('积分').'数']);
		}
        if($score < 0) $actionname = '扣除';
        $remark = $remark ? (t('后台修改')?t('后台修改').'：'.$remark:$remark) : t('后台修改');
		$rs = \app\commons\Member::addscore(aid,$mid,$score,$remark);
		\app\commons\System::plog('给会员'.$mid.$actionname.'积分'.$score);
		if($rs['status']==0) return json($rs);
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//加佣金
	public function addcommission(){
        if($this->auth_data != 'all' && !in_array('Member/*',$this->auth_data) && !in_array('Member/addcommission',$this->auth_data)){
            return json(['status'=>0,'msg'=>'无权限']);
        }

		$mid = input('post.id/d');
        $where = [];
        $where[] = ['id','=',$mid];
        if(getcustom('member_business')){
            if(bid){
                $where[] = ['bid','=',bid];
            }
        }
        $where[] = ['aid','=',aid];
        $count = Db::name('member')->where($where)->count('id');
        if(!$count){
             return json(['status'=>0,'msg'=>t('会员').'不存在']);
        }

		$commission = floatval(input('post.money'));
		$remark = input('post.remark');
        $actionname = '增加';
		if($commission == 0){
			return json(['status'=>0,'msg'=>'请输入'.t('佣金').'金额']);
		}
        if($commission < 0) $actionname = '扣除';
        $remark = $remark ? (t('后台修改')?t('后台修改').'：'.$remark:$remark) : t('后台修改');
		$rs = \app\commons\Member::addcommission(aid,$mid,0,$commission,$remark,0, 'admin');
		\app\commons\System::plog('给会员'.$mid.$actionname.t('佣金').$commission);
		if($rs['status']==0) return json($rs);
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//编辑
	public function edit(){
		if(input('param.id')){
            $where = [];
            $where[] = ['id','=',input('param.id/d')];
            if(getcustom('member_business')){
                if(bid){
                    $where[] = ['bid','=',bid];
                }
            }
            $where[] = ['aid','=',aid];
			$info = Db::name('member')->where($where)->find();
            $info['money'] = \app\commons\Member::getmoney($info);
            $info['score'] = \app\commons\Member::getscore($info);
            
            if ($info['lv_time']) {
                $info['lv_time'] = date('Y-m-d H:i:s',$info['lv_time']);
            }else {
                $info['lv_time'] = '';
            }
            
            
            
			if(getcustom('member_tag')){
				$info['tags'] = explode(',',$info['tags']);
			}
		}else{
			$info = array('id'=>'');
		}
		$level_cid = Db::name('member_level_category')->where('aid',aid)->where('isdefault',1)->value('id');
        $level_cid = $level_cid ? $level_cid : 0;
		$levelList = Db::name('member_level')->where('aid',aid)->where('cid', $level_cid)->order('sort')->select()->toArray();
		$defaultlevel = Db::name('member_level')->where('aid',aid)->where('isdefault',1)->find();
		$levelArr = Db::name('member_level')->where('aid',aid)->order('sort')->column('name,areafenhong','id');
		if(getcustom('plug_sanyang')) {
            $level_category_extend = Db::name('member_level_category')->where('aid',aid)->where('isdefault',0)->select()->toArray();
            if($level_category_extend) {
                foreach ($level_category_extend as $key => $item) {
                    $level_category_extend[$key]['level_list'] = Db::name('member_level')->where('aid',aid)->where('cid', $item['id'])->order('sort')->select()->toArray();
                }
            }

            View::assign('level_category_extend',$level_category_extend);

            $levelExtendList = Db::name('member_level_record')->where('aid', aid)->where('mid', input('param.id'))->select()->toArray();
            View::assign('levelExtendList',$levelExtendList);
        }
		if(getcustom('register_fields')){
            $sys_form = Db::name("register_form")->where('aid', aid)->find();
            $record = [];
            if($info['form_record_id']){
                $record = Db::name('register_form_record')->where('aid',aid)->where('bid',bid)->where('id',$info['form_record_id'])->find();
            }
            if($sys_form){
                $formcontent = $sys_form['content']?json_decode($sys_form['content'],true):[];
                $record['content'] = $formcontent;
            }
		    View::assign('register_record',$record);
        }

		//标签
		if(getcustom('member_tag')){
			$tagsdata =  Db::name('member_tag')->where('aid',aid)->where('status',1)->select()->toArray();
			View::assign('tagsdata',$tagsdata);
		}
        if(getcustom('member_area_agent_multi')){
            if($info['id']){
                $area_agent_info = [];
                $area_agent = Db::name('member_area_agent')->where('aid',aid)->where('mid',$info['id'])->select()->toArray();
                foreach ($area_agent as $item){
                    if($item['areafenhong_area']){
                        $area_agent_info[]=$item['areafenhong_province'].'-'.$item['areafenhong_city'].'-'.$item['areafenhong_area'];
                    }else{
                        if($item['areafenhong_city']){
                            $area_agent_info[]=$item['areafenhong_province'].'-'.$item['areafenhong_city'];
                        }else{
                            if($item['areafenhong_province']){
                                $area_agent_info[]=$item['areafenhong_province'];
                            }
                        }
                    }
                }
                View::assign('area_agent_info',jsonEncode($area_agent_info));
            }else{
                View::assign('area_agent_info',jsonEncode([]));
            }
        }


		View::assign('defaultlevel',$defaultlevel);
		View::assign('info',$info);
		View::assign('levelList',$levelList);
		View::assign('levelArr',$levelArr);
		$teamArr = Db::name('member_team_level')->where('aid',aid)->select()->toArray();
		View::assign('teamArr',$teamArr);
		
		 $storeArr = Db::name('member_store')->where('aid',aid)->select()->toArray();
			View::assign('storeArr',$storeArr);
		
		
		$blist = Db::name('business')->field('id,name')->where('aid',aid)->order('sort desc,id')->select()->toArray();
        View::assign('blist',$blist);
        if(getcustom('xixie')) {
            $mendian = Db::name('mendian')
                ->where('aid',aid)
                ->select()
                ->toArray();
            if(!$mendian){
                $mendian = [];
            }
            View::assign('mendian',$mendian);
        }

        if(getcustom('member_set')){

            $set        = '';
            $setcontent = '';
            $log        = '';

            $set = Db::name('member_set')->where('aid',aid)->find();
            if($set){
                $setcontent = json_decode($set['content'],true);
                $log = Db::name('member_set_log')->where('formid',$set['id'])->where('mid',$info['id'])->where('aid',aid)->find();
            }

            View::assign('set',$set);
            View::assign('setcontent',$setcontent);
            View::assign('log',$log);
        }
		if(getcustom('member_levelup_auth')){
			if($this->auth_data == 'all' || in_array('Member/*',$this->auth_data) || in_array('Member/recharge',$this->auth_data)){
				$haverechargeAuth = true;
			}else{
				$haverechargeAuth = false;
			}
			View::assign('haverechargeAuth',$haverechargeAuth);
		}
        if(getcustom('shop_label')){
            //分类
            $labels = Db::name('shop_label')->Field('id,name')->where('aid',aid)->order('sort desc,id desc')->select()->toArray();
            View::assign('labels',$labels);
        }
		return View::fetch();
	}
	//保存
	public function save(){
		$info = input('post.info/a');
		$defaultlevel = Db::name('member_level')->where('aid',aid)->where('isdefault',1)->find();
		if($info['levelid'] == $defaultlevel['id'] || input('post.levelcq') == 1){
			$info['levelendtime'] = 0;
		}else{
			$info['levelendtime'] = strtotime($info['levelendtime']);
		}


        if ($info['lv_time']) {
            $info['lv_time'] = strtotime($info['lv_time']);
        }else {
            $info['lv_time'] = 0;
        }
            


        if($info['pwd']){
			$info['pwd'] = md5($info['pwd']);
		}else{
			unset($info['pwd']);
		}
       
		if(getcustom('member_tag')){
			$info['tags'] = implode(',',$info['tags']);
		}
		if(getcustom('member_richinfo')){
			$info['richinfo'] = \app\commons\Common::geteditorcontent($info['richinfo']);
		}

		if($info['id']){

            $where = [];
            $where[] = ['id','=',$info['id']];
            if(getcustom('member_business')){
                if(bid){
                    $where[] = ['bid','=',bid];
                }
            }
            $where[] = ['aid','=',aid];
            $count = Db::name('member')->where($where)->count('id');
            if(!$count){
                 return json(['status'=>0,'msg'=>t('会员').'不存在']);
            }

            $member = Db::name('member')->where('id',$info['id'])->find();
            if($info['paypwd']){
                $info['paypwd_rand'] = $member['paypwd_rand']?:make_rand_code(2,4);
                $info['paypwd'] = md5($info['paypwd'].$info['paypwd_rand']);
            }else{
                unset($info['paypwd']);
            }
			$rs = m::edit(aid,$info);
			if($rs['status'] == 0) return json($rs);
			\app\commons\System::plog('编辑会员'.$info['id']);

            if(getcustom('member_vip_edit')){
                //1.原用户的等级 是普通用户 2. 修改的登记 不等于 当前用户的登记
                if($member['levelid'] == $defaultlevel['id'] && $info['levelid'] != $member['levelid']){
                    //增加升级记录
                    $order = [
                        'aid' => aid,
                        'mid' => $info['id'],
                        'from_mid' => 0,
                        'pid'=>$info['pid'],
                        'levelid' => $info['levelid'] ,
                        'title' => '后台升级',
                        'totalprice' => 0,
                        'createtime' => time(),
                        'levelup_time' => time(),
                        'beforelevelid' => $member['levelid'],
                        'form0' => '类型^_^后台升级',
                        'platform' => platform,
                        'status' => 2
                    ];
                    Db::name('member_levelup_order')->insert($order);
                }
            }
            //升级
            if($info['levelid'] != $member['levelid'] && $member['pid'] > 0){
                \app\commons\Member::uplv(aid,$member['pid']);
            }
            if($info['pid'] != $member['pid'] && $info['pid'] > 0){
                \app\commons\Member::uplv(aid,$info['pid']);
            }
            if($info['levelid'] != $member['levelid']){
                //插入级别变动记录
                $level_sort = Db::name('member_level')->where('aid',aid)->column('sort','id');
                $level_type = $level_sort[$info['levelid']]>$level_sort[$member['levelid']]?'0':'1';
                $remark =  $level_sort[$info['levelid']]>$level_sort[$member['levelid']]?'后台升级':'后台降级';
                $order = [
                    'aid' => $member['aid'],
                    'mid' => $member['id'],
                    'from_mid' => $member['id'],
                    'pid'=>$member['pid'],
                    'levelid' => $info['levelid'],
                    'title' => t('后台修改'),
                    'totalprice' => 0,
                    'createtime' => time(),
                    'levelup_time' => time(),
                    'beforelevelid' => $member['levelid'],
                    'form0' => '类型^_^' . $remark,
                    'platform' => '',
                    'status' => 2,
                    'type' => $level_type
                ];
                Db::name('member_levelup_order')->insert($order);
            }
		}else{
            if($info['paypwd']){
                $info['paypwd_rand'] = make_rand_code(2,4);
                $info['paypwd'] = md5($info['paypwd'].$info['paypwd_rand']);
            }else{
                unset($info['paypwd']);
            }

			$info['aid'] = aid;
			$info['createtime'] = time();
			$info['platform'] = 'admin';
            if(getcustom('member_business')){
                if(bid){
                    $info['bid'] = bid;
                }
            }
            $info['id'] = m::add(aid,$info);
			\app\commons\System::plog('添加会员'.$info['id']);
            if(getcustom('member_vip_edit')){
                if($info['levelid'] != $defaultlevel['id']){
                    //增加升级记录
                    $order = [
                        'aid' => aid,
                        'mid' => $info['id'],
                        'from_mid' => 0,
                        'pid'=>$info['pid'],
                        'levelid' => $info['levelid'] ,
                        'title' => '后台升级',
                        'totalprice' => 0,
                        'createtime' => time(),
                        'levelup_time' => time(),
                        'beforelevelid' => $defaultlevel['id'],
                        'form0' => '类型^_^后台升级',
                        'platform' => platform,
                        'status' => 2
                    ];
                    Db::name('member_levelup_order')->insert($order);
                }
            }
            //升级
            if($info['pid'])
                \app\commons\Member::uplv(aid,$info['pid']);

            if(getcustom('product_bonus_pool')){
                $bonus_pool_status = Db::name('admin')->where('id',aid)->value('bonus_pool_status');
                if($info['pid'] && $bonus_pool_status==1){
                    $shop_set = Db::name('shop_sysset')->where('aid',aid)->find();
                    //判断人数
                    $parent = Db::name('member')->where('id',$info['pid'])->find();
                    $member_tj = $parent['bonus_pool_tjnum'] + 1;
                    //如果设置人数和父级的推荐人数相等  就奖励金 ,如果不相等 推荐人数+1
                    if($shop_set['bonus_pool_tuijian_num'] > 0 && $shop_set['bonus_pool_tuijian_num'] == $member_tj){
                        $pool = Db::name('bonus_pool')->where('aid',aid)->where('status',0)->order('id asc')->find();

                        $wait_money = Db::name('member_bonus_pool_record')->where('aid',aid)->where('status',0)->where('mid',$parent['id'])->sum('commission');

                        $total_product_pool_money =0+Db::name('member_bonus_pool_record')->where('aid',aid)->where('mid',$parent['id'])->sum('commission');

                        if($total_product_pool_money +$pool['money'] +$wait_money <=  $parent['bonus_pool_max_money']){
                            $bonus_pool_money = dd_money_format($parent['bonus_pool_money'] + $pool['money']);
                            //增加log
                            $log = [
                                'aid' =>aid,
                                'mid' =>$parent['id'],
                                'frommid' => 0,
                                'commission' => $pool['money'],
                                'after' => $bonus_pool_money,
                                'createtime' => time(),
                                'remark' => '推荐用户奖金发放'
                            ];
                            Db::name('member_bonus_pool_log') ->insert($log);
                            //修改用户的钱 和 推荐数量清空
                            Db::name('member')->where('id',$info['pid'])->update(['bonus_pool_money' => $bonus_pool_money,'bonus_pool_tjnum' =>0]);
                            //修改奖金池状态
                            Db::name('bonus_pool')->where('aid',aid)->where('id',$pool['id'])->update(['status' => 1,'mid' => $parent['id']]);
                        }
                    }else{
                        Db::name('member')->where('id',$info['pid'])->update(['bonus_pool_tjnum' => $member_tj]);
                    }
                }
            }
		}

        if(getcustom('member_area_agent_multi')){
            //多代理区域
            $areainfo = input('post.areadata/a');
            Db::name('member_area_agent')->where('aid',aid)->where('mid',$info['id'])->delete();
            if($areainfo){
                foreach ($areainfo as $item){
                    $area = explode('-',$item);
                    $insert = [
                        'aid'=>aid,
                        'mid'=>$info['id'],
                        'createtime'=>time(),
                        'areafenhong_province'=>trim($area[0]),
                        'areafenhong_city'=>trim($area[1]),
                        'areafenhong_area'=>trim($area[2]),
                        'areafenhong'=>count($area),
                        'areafenhongbl'=>$info['areafenhongbl']
                    ];
                    Db::name('member_area_agent')->insert($insert);
                }
            }
        }

        if(getcustom('plug_sanyang')) {
            $record = input('post.record/a');
            if($record['level_extend_cq'] == 1){
                $record['levelendtime'] = 0;
            }else{
                $record['levelendtime'] = strtotime($record['levelendtime']);
            }
            unset($record['level_extend_cq']);

            if($record['levelid']) {
                $record['cid'] = Db::name('member_level')->where('aid',aid)->where('id',$record['levelid'])->value('cid');
            }
            if($record['id']) {
                if(empty($record['levelid'])) {
                    Db::name('member_level_record')->where('aid',aid)->where('mid', $info['id'])->where('id', $record['id'])->delete();
                } else {
                    $count = Db::name('member_level_record')->where('aid',aid)->where('mid', $info['id'])->where('cid', $record['cid'])->where('id','<>',$record['id'])->count();
                    if($count) {
                        return json(['status'=>0,'msg'=>'不能添加两个相同分组的等级']);
                    }
                    Db::name('member_level_record')->where('aid',aid)->where('mid', $info['id'])->where('id', $record['id'])->update($record);
                }
            } else {
                if($record['levelid']) {
                    $count = Db::name('member_level_record')->where('aid',aid)->where('mid', $info['id'])->where('cid', $record['cid'])->count();
                    if($count) {
                        return json(['status'=>0,'msg'=>'不能添加两个相同分组的等级']);
                    }
                    $record['aid'] = aid;
                    $record['mid'] = $info['id'];
                    $record['bid'] = bid;
                    $record['createtime'] = time();
                    Db::name('member_level_record')->insertGetId($record);
                }
            }
        }
        if(getcustom('register_fields')){
            $formdata = input('post.formdata/a');
            $set = Db::name("register_form")->where('aid', aid)
                ->where('bid', bid)
                ->find();
            if($formdata){
                if($formdata['id']){
                    foreach ($formdata as $k=>$v){
                        if(is_array($v)){
                            $formdata[$k] = implode(',',$v);
                        }
                    }
                    Db::name('register_form_record')->where('aid',aid)->where('bid',bid)->where('id',$formdata['id'])->update($formdata);
                }else{
                    $logdata = [];
                    $logdata['aid'] = aid;
                    $logdata['bid'] = bid;
                    $logdata['formid'] = $set['id'];
                    $logdata['content'] = $set['content'];
                    foreach ($formdata as $k=>$v){
                        if(is_array($v)){
                            $logdata[$k] = implode(',',$v);
                        }else{
                            $logdata[$k] = $v;
                        }
                    }
                    $logdata['createtime'] = time();
                    $up = Db::name('register_form_record')->insertGetId($logdata);
                    Db::name("member")->where('id', $info['id'])->save([
                        "form_record_id"=>$up
                    ]);
                }

            }
        }
        if(getcustom('member_set')){
            if(input('set')){
                $setinfo  = input('post.set/a');

                $set = Db::name('member_set')->where('aid',aid)->find();
                if(!$set){
                    return json(['status'=>0,'msg'=>'资料自定义设置不存在']);
                } 
                $setcontent = json_decode($set['content'],true);
                if(!$setcontent){
                    return json(['status'=>0,'msg'=>'资料自定义设置不存在']);
                }

                $data =[];
                foreach($setcontent as $k=>$v){
                    $value = $setinfo['form'.$k];
                    if(is_array($value)){
                        $value = implode(',',$value);
                    }
                    $data['form'.$k] = strval($value);
                    if($v['val3']==1 && $data['form'.$k]===''){
                        return json(['status'=>0,'msg'=>$v['val1'].' 必填']);
                    }
                }

                //查询是否设置过
                $log = Db::name('member_set_log')->where('mid',$info['id'])->where('formid',$set['id'])->where('aid',aid)->field('id')->find();
                if($log){
                    Db::name('member_set_log')->where('id',$log['id'])->update($data);
                }else{
                    $data['aid']    = aid;
                    $data['mid']    = $info['id'];
                    $data['formid'] = $set['id'];
                    $data['createtime'] = time();
                    $orderid = Db::name('member_set_log')->insertGetId($data);
                }
            }
        }
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	//会员删除
	public function del(){
		$ids = input('post.ids/a');
		m::del(aid,$ids,bid);
		\app\commons\System::plog('删除会员'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
    //编辑
	public function check(){
        $where = [];
        $where[] = ['id','=',input('param.id/d')];
        if(getcustom('member_business')){
            if(bid){
                $where[] = ['bid','=',bid];
            }
        }
        $where[] = ['aid','=',aid];
        $info = Db::name('member')->where($where)->find();
        if(getcustom('register_fields')){
            if($info['form_record_id']){
                $record = Db::name('register_form_record')->where('aid',aid)->where('bid',bid)->where('id',$info['form_record_id'])->find();
                if(!empty($record)){
                    $formcontent = $record['content']?json_decode($record['content'],true):[];
                    $record['content'] = $formcontent;
                }else{
                    $record = [];
                }
            }
            View::assign('register_record',$record);
        }
        if(getcustom('member_set')){

            $set        = '';
            $setcontent = '';
            $log        = '';

            $set = Db::name('member_set')->where('aid',aid)->find();
            if($set){
                $setcontent = json_decode($set['content'],true);
                $log = Db::name('member_set_log')->where('formid',$set['id'])->where('mid',$info['id'])->where('aid',aid)->find();
            }

            View::assign('set',$set);
            View::assign('setcontent',$setcontent);
            View::assign('log',$log);
        }
        View::assign('info',$info);
		return View::fetch();
	}
	//会员审核
	public function setst(){
		$id = input('post.id/d');
		$st = input('post.st/d');
		$checkreason = input('post.checkreason');

        $where = [];
        $where[] = ['id','=',$id];
        if(getcustom('member_business')){
            if(bid){
                $where[] = ['bid','=',bid];
            }
        }
        $where[] = ['aid','=',aid];
		$member = Db::name('member')->where($where)->find();
		if(!$member) return json(['status'=>0,'msg'=>'未找到该用户']);
		Db::name('member')->where('id',$id)->where('aid',aid)->update(['checkst'=>$st,'checkreason'=>$checkreason]);
		if($st == 1){
			$rs = \app\commons\Sms::send(aid,$member['tel'],'tmpl_checksuccess',[]);
		}elseif($st == 2){
			$rs = \app\commons\Sms::send(aid,$member['tel'],'tmpl_checkerror',['reason'=>$checkreason]);
		}
		
		//审核结果通知
		$tmplcontent = [];
		$tmplcontent['first'] = ($st == 1 ? '恭喜您的注册审核通过' : '抱歉您的注册未审核通过');
		$tmplcontent['remark'] = ($st == 1 ? '' : ($checkreason.'，')) .'请点击查看详情~';
		$tmplcontent['keyword1'] = '用户注册';
		$tmplcontent['keyword2'] = ($st == 1 ? '已通过' : '未通过');
		$tmplcontent['keyword3'] = date('Y年m月d日 H:i');
        $tempconNew = [];
        $tempconNew['thing9'] = '用户注册';
        $tempconNew['thing2'] = ($st == 1 ? '已通过' : '未通过');
        $tempconNew['time3'] = date('Y年m月d日 H:i');
		\app\commons\Wechat::sendtmpl(aid,$id,'tmpl_shenhe',$tmplcontent,m_url('pages/my/usercenter'),$tempconNew);
		//订阅消息
		$tmplcontent = [];
		$tmplcontent['thing8'] = '用户注册';
		$tmplcontent['phrase2'] = ($st == 1 ? '已通过' : '未通过');
		$tmplcontent['thing4'] = $checkreason;
		
		$tmplcontentnew = [];
		$tmplcontentnew['thing2'] = '用户注册';
		$tmplcontentnew['phrase1'] = ($st == 1 ? '已通过' : '未通过');
		$tmplcontentnew['thing5'] = $checkreason;
		\app\commons\Wechat::sendwxtmpl(aid,$id,'tmpl_shenhe',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);

		return json(['status'=>1,'msg'=>'操作成功','res'=>$rs]);
	}
	//冻结 解冻
	public function setfreeze(){
		$id = input('post.id/d');
		$st = input('post.st/d');
		$member = Db::name('member')->where('id',$id)->where('aid',aid)->find();
		if(!$member) return json(['status'=>0,'msg'=>'未找到该用户']);
		Db::name('member')->where('id',$id)->where('aid',aid)->update(['isfreeze'=>$st]);
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//会员卡详情
	public function getcarddetail(){
		$card_id = input('post.card_id');
		$card_code = input('post.card_code');
		$info = Db::name('membercard_record')->where('aid',aid)->where('card_id',$card_id)->where('card_code',$card_code)->find();
		return json(['status'=>1,'data'=>$info]);
	}
	//更新用户的unionid 仅支持公众号 不支持小程序
	public function updateunionid(){
		$platform = input('param.platform');
		if(!$platform) $platform = 'mp';
		$url = 'https://api.weixin.qq.com/cgi-bin/user/info/batchget?access_token='.\app\commons\Wechat::access_token(aid,$platform);
		$memberlist = Db::name('member')->field('id,mpopenid,unionid,headimg,nickname')->where('aid',aid)->where("mpopenid!='' and mpopenid is not null")->select()->toArray();
		$user_list = [];
		$thismemberlist = [];
		foreach($memberlist as $k=>$member){
			$user_list[] = ['openid'=>$member['mpopenid'],'lang'=>'zh_CN'];
			$thismemberlist[$member['mpopenid']] = $member;
			if(count($user_list) >= 100 || $k+1 == count($memberlist)){
				$rs = curl_post($url,jsonEncode(['user_list'=>$user_list]));
				$rs = json_decode($rs,true);
				if($rs['errcode']!=0 && $rs['errcode']!=1){
					return json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs)]);
				}
				$user_info_list = $rs['user_info_list'];
				foreach($user_info_list as $k2=>$v2){
					$thismember = $thismemberlist[$v2['openid']];
					if($v2['unionid'] && $v2['unionid'] != $thismember['unionid']){
						Db::name('member')->where('aid',aid)->where('id',$thismember['id'])->update(['unionid'=>$v2['unionid']]);
						echo "ID:{$thismember['id']}，昵称:{$thismember['nickname']} unionid已更新:{$v2['unionid']}<br>";
					}
				}
				$user_list = [];
				$thismemberlist = [];
			}
		}
		die('success');
	}

	private $deeplevel = 0;
	//关系图
	public function charts(){
		$mid = input('param.mid/d');
        $where = [];
        $where[] = ['id','=',$mid];
        if(getcustom('member_business')){
            if(bid){
                $where[] = ['bid','=',bid];
            }
        }
        $where[] = ['aid','=',aid];
		$member = Db::name('member')->field('id,nickname,headimg,pid,realname,tel,levelid')->where($where)->find();
		$leveldata = Db::name('member_level')->where('aid',aid)->order('sort,id')->column('id,sort,name','id');
		if($member){
		    $member['headimg'] =$member['headimg']?$member['headimg']: PRE_URL.'/static/img/touxiang.png';
			$member['children'] = $this->getdown($mid,0,$leveldata);
			$member['downcount'] = count($member['children']);
			$member['downcountall'] = Db::name('member')->where('aid',aid)->where('find_in_set('.$mid.',path)')->count();
			$member['name'] = $member['nickname'] . "\r\n" .$leveldata[$member['levelid']]['name'].'(ID:'.$member['id'].' 下级:'.$member['downcount'].'/'.$member['downcountall'].'人)';
			View::assign('member',$member);
			View::assign('deeplevel',$this->deeplevel);
		}
		return View::fetch();
	}

	//注册赠送
    public function registerGive()
    {
        if(getcustom('member_goldmoney_silvermoney')){
            $SendSilvermoney = true;//赠送银值权限
            if($this->auth_data != 'all' && !in_array('Member/*',$this->auth_data) && !in_array('SendSilvermoney',$this->auth_data)){
                $SendSilvermoney = false;
            }
            $SendGoldmoney = true;//赠送金值权限
            if($this->auth_data != 'all' && !in_array('Member/*',$this->auth_data) && !in_array('SendGoldmoney',$this->auth_data)){
                $SendGoldmoney = false;
            }
        }

        if(request()->isPost()){
            $info = input('post.info/a');
            if(getcustom('member_goldmoney_silvermoney')){
                if(!$SendGoldmoney && $info['goldmoney']){
                    unset($info['goldmoney']);
                }
                if(!$SendSilvermoney && $info['silvermoney']){
                    unset($info['silvermoney']);
                }
            }
            if($info['id']){
                Db::name('register_giveset')->where('aid',aid)->where('id',$info['id'])->update($info);
                \app\commons\System::plog('编辑注册赠送'.$info['id']);
            }else{
                $info['aid'] = aid;
                $info['createtime'] = time();
                $id = Db::name('register_giveset')->insertGetId($info);
                \app\commons\System::plog('编辑注册赠送'.$id);
            }
            return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('registerGive')]);
        }

        $info = Db::name('register_giveset')->where('aid',aid)->find();
        if($info) {
            $coupon_ids = explode(',', $info['coupon_ids']);
            $couponList = Db::name('coupon')->where('aid',aid)->where('bid', 0)->whereIn('id', $coupon_ids)->select()->toArray();
            $info['score'] = dd_money_format($info['score'],$this->score_weishu);
            $info['wanshan_score'] = dd_money_format($info['wanshan_score'],$this->score_weishu);
            if (getcustom('member_register_give_parent_score')){
                $info['introscore'] = dd_money_format($info['introscore'],$this->score_weishu);
            }
        }
        View::assign('couponList',$couponList);
        View::assign('info',$info);
        if(getcustom('member_goldmoney_silvermoney')){
            View::assign('SendGoldmoney',$SendGoldmoney);
            View::assign('SendSilvermoney',$SendSilvermoney);
        }
        return View::fetch();
    }

	private function getdown($mid,$deeplevel,$leveldata){
		if($deeplevel > 20) return json([]);
		$deeplevel++;
		$memberlist = Db::name('member')->field('id,nickname,headimg,pid,pid_origin,realname,tel,levelid')->where('aid',aid)->where('pid',$mid)->select()->toArray();
		foreach($memberlist as $k=>$member){
			$memberlist[$k]['children'] = $this->getdown($member['id'],$deeplevel,$leveldata);
			$memberlist[$k]['downcount'] = count($memberlist[$k]['children']);
			$memberlist[$k]['downcountall'] = Db::name('member')->where('aid',aid)->where('find_in_set('.$member['id'].',path)')->count();
            if($member['pid_origin'])
                $memberlist[$k]['name'] = $member['nickname'] . "\r\n" . $leveldata[$member['levelid']]['name'].'(ID:'.$member['id'].' 下级:'.$memberlist[$k]['downcount'].'/'.$memberlist[$k]['downcountall'].'人)'. "\r\n"."(原推荐人ID:".$member['pid_origin'].')';
            else
			    $memberlist[$k]['name'] = $member['nickname'] . "\r\n" . $leveldata[$member['levelid']]['name'].'(ID:'.$member['id'].' 下级:'.$memberlist[$k]['downcount'].'/'.$memberlist[$k]['downcountall'].'人)';
		}
		if($this->deeplevel < $deeplevel) $this->deeplevel = $deeplevel;
		return $memberlist;
	}

	//会员path
	public function updatepath(){
		$memberlist = Db::name('member')->where('aid',aid)->order('id')->select()->toArray();
		foreach($memberlist as $member){
			$pathArr = $this->getpath($member,$path=[]);
			if($pathArr){
				$pathArr = array_reverse($pathArr);
				$pathstr = implode(',',$pathArr);
				Db::name('member')->where('aid',aid)->where('id',$member['id'])->update(['path'=>$pathstr]);
				//var_dump($member['id'].'----'.$pathstr);
				if($member['path'] != $pathstr){
					var_dump($member['path'].'----'.$pathstr);
				}
			}else{
				Db::name('member')->where('aid',aid)->where('id',$member['id'])->update(['path'=>'']);
			}
		}
		die('success');
	}
	public function getpath($member,$path,$deep=1){
		if($member['pid'] && $deep < 10){
			if(in_array($member['pid'],$path)) return $path;
			$path[] = $member['pid'];
			$parent = Db::name('member')->where('aid',aid)->where('id',$member['pid'])->find();
			$deep = $deep + 1;
			return $this->getpath($parent,$path,$deep);
		}
		return $path;
	}

	//增加开团次数
	public function addktnum(){
		$mid = input('post.ktmid/d');
		$ktnum = floatval(input('post.ktnum'));
		if($ktnum == 0){
			return json(['status'=>0,'msg'=>'请输入开团次数']);
		}
		if(session('IS_ADMIN')==0){
			$user = Db::name('admin_user')->where('aid',aid)->where('id',$this->uid)->find();
			$remark = '商家充值，操作员：'.$user['un'];
		}else{
			$remark = '商家充值';
		}
		$member = Db::name('member')->where('aid',aid)->where('id',$mid)->find();
        if(!$member) return ['status'=>0,'msg'=>t('会员').'不存在'];
		$after = $member['ktnum'] + $ktnum;
        Db::name('member')->where('aid',aid)->where('id',$mid)->update(['ktnum'=>$after]);
		\app\commons\System::plog('给会员'.$mid.'增加开团次数'.$ktnum);
		return json(['status'=>1,'msg'=>'操作成功']);
	}

    //设置余额宝提现天数
    public function setywtime(){
        $mid = input('post.ywtmid/d');

        $ywrate = input('post.ywrate');
        $ywtime = input('post.ywtime/d');

        if(session('IS_ADMIN')==0){
            $user = Db::name('admin_user')->where('aid',aid)->where('id',$this->uid)->find();
            $remark = '商家设置'.t('余额宝').'提现天数，操作员：'.$user['un'];
        }else{
            $remark = '商家设置'.t('余额宝').'提现天数';
        }
        $member = Db::name('member')->where('aid',aid)->where('id',$mid)->find();
        if(!$member) return ['status'=>0,'msg'=>t('会员').'不存在'];

        Db::name('member')->where('aid',aid)->where('id',$mid)->update(['yuebao_rate'=>$ywrate,'yuebao_withdraw_time'=>$ywtime]);
        \app\commons\System::plog('给会员'.$mid.'设置'.t('余额宝').'收益率、提现天数'.$ywtime);
        return json(['status'=>1,'msg'=>'操作成功']);
    }
 	//充值元宝
    public function addyuanbao(){
        if(getcustom('pay_yuanbao')){
            $mid = input('post.id/d');
            $yuanbao = intval(input('post.yuanbao'));
            $remark = input('post.remark');
            $actionname = '增加';
            if($yuanbao == 0){
                return json(['status'=>0,'msg'=>'请输入'.t('元宝').'数']);
            }
            if($yuanbao < 0) $actionname = '扣除';
            $remark = $remark ? (t('后台修改')?t('后台修改').'：'.$remark:$remark) : t('后台修改');
            $rs = \app\commons\Member::addyuanbao(aid,$mid,$yuanbao,$remark);
            \app\commons\System::plog('给会员'.$mid.$actionname.t('元宝').$yuanbao);
            if($rs['status']==0) return json($rs);
            return json(['status'=>1,'msg'=>'操作成功']);
        }else{
            return json(['status'=>0,'msg'=>'操作失败']);
        }
    }
	
	//锁定
	public function dolock(){
		if(session('IS_ADMIN') == 0) return json(['status'=>1,'msg'=>'无权限操作']);
		$id = input('post.id/d');
		$st = input('post.st/d');
		Db::name('member')->where('aid',aid)->where('id',$id)->update(['islock'=>$st]);
		return json(['status'=>1,'msg'=>'操作成功']);
	}

    public function choosemember(){
        if(request()->isPost()){
            $data = Db::name('member')->where('aid',aid)->where('bid',bid)->where('id',input('post.id/d'))->find();
            if(getcustom('business_fenxiao')){
                $data['pid_nickname'] =  Db::name('member')->where('aid',aid)->where('bid',bid)->where('id',$data['pid'])->value('nickname');
            }
            return json(['status'=>1,'msg'=>'查询成功','data'=>$data]);
        }
        View::assign('from',input('param.from'));
        return View::fetch();
    }

    //加其他余额
    public function addOtherMoney(){
        if(getcustom('other_money')){
            //是否有多账户权限
            $othermoney_status = Db::name('admin')->where('id',aid)->value('othermoney_status');
            if($othermoney_status != 1){
                return json(['status'=>0,'msg'=>'无权限操作']);
            }

            $mid    = input('post.id/d');
            $type   = input('post.type');
            if($type == 'money2'){
                if($this->auth_data != 'all' && !in_array('Member/*',$this->auth_data) && !in_array('Member/addmoney2',$this->auth_data)){
                    return json(['status'=>0,'msg'=>'无权限操作']);
                }
                $type_name = t('余额2');
            }else if($type == 'money3'){
                if($this->auth_data != 'all' && !in_array('Member/*',$this->auth_data) && !in_array('Member/addmoney3',$this->auth_data)){
                    return json(['status'=>0,'msg'=>'无权限操作']);
                }
                $type_name = t('余额3');
            }else if($type == 'money4'){
                if($this->auth_data != 'all' && !in_array('Member/*',$this->auth_data) && !in_array('Member/addmoney4',$this->auth_data)){
                    return json(['status'=>0,'msg'=>'无权限操作']);
                }
                $type_name = t('余额4');
            }else if($type == 'money5'){
                if($this->auth_data != 'all' && !in_array('Member/*',$this->auth_data) && !in_array('Member/addmoney5',$this->auth_data)){
                    return json(['status'=>0,'msg'=>'无权限操作']);
                }
                $type_name = t('余额5');
            }else if($type == 'frozen_money'){
                if($this->auth_data != 'all' && !in_array('Member/*',$this->auth_data) && !in_array('Member/addfrozen_money',$this->auth_data)){
                    return json(['status'=>0,'msg'=>'无权限操作']);
                }
                $type_name = t('冻结金额');
            }else{
                return json(['status'=>0,'msg'=>'操作类型错误']);
            }
            $money  = floatval(input('post.money'));
            $remark = input('post.remark')?input('post.remark'):'';

            $actionname = '增加';
            if($money == 0 || $money == ''){
                return json(['status'=>0,'msg'=>'请输入金额']);
            }
            if($money < 0) $actionname = '扣除';
            // if(session('IS_ADMIN')==0){
            //     $user = Db::name('admin_user')->where('aid',aid)->where('id',$this->uid)->find();
            //     $remark = $remark?$remark:'商家'.$actionname.'，操作员：'.$user['un'];
            // }else{
            //     $remark = $remark?$remark:'商家'.$actionname;
            // }
            $rs = \app\commons\Member::addOtherMoney(aid,$mid,$type,$money,$remark);
            \app\commons\System::plog('给会员'.$mid.$actionname.$type_name.'，金额'.$money);
            if($rs['status']==0) return json($rs);
            return json(['status'=>1,'msg'=>$actionname.'成功']);
        }
    }

    public function addgongxian(){
        if(getcustom('member_gongxian')) {
            //修改贡献数
            $mid = input('post.id/d');
            $score = intval(input('post.score'));
            $remark = input('post.remark');
            $actionname = '增加';
            if($score == 0){
                return json(['status'=>0,'msg'=>'请输入'.t('贡献').'数']);
            }
            if($score < 0) $actionname = '扣除';
            $remark = $remark ? (t('后台修改')?t('后台修改').'：'.$remark:$remark) : t('后台修改');
            $rs = \app\commons\Member::addgongxian(aid,$mid,$score,$remark,'backstage');
            //更新会员消费日期
            Db::name('member')->where('aid',aid)->where('id',$mid)->update(['last_buytime'=>time()]);
            \app\commons\System::plog('给会员'.$mid.$actionname.t('贡献').$score);
            if($rs['status']==0) return json($rs);
            return json(['status'=>1,'msg'=>'操作成功']);
        }
    }
    
    public function yejitongji(){
	    if(getcustom('coupon_xianxia_buy')){
            if(request()->isAjax()){
                $page = input('param.page');
                $limit = input('param.limit');
                $mid = input('param.mid');
                $couponid = input('param.couponid');
                if(input('param.field') && input('param.order')){
                    $order = input('param.field').' '.input('param.order');
                }else{
                    $order = 'id desc';
                }
                $where = [];
                $where[] = ['aid','=',aid];
                $where[] = ['mid','=',$mid];
                if(input('param.bid') == 'all'){
                }else{
                    $where[] = ['bid','=',bid];
                }
                if($couponid){
                    $where[] = ['couponid','=',$couponid];
                }
                if(input('param.id/d')) $where[] = ['coupon_record.couponid','=',input('param.id/d')];
                if(getcustom('yuyueworker_searchmember')){
                    if(input('param.mid/d')){
                        $where[] = ['coupon_record.mid','=',input('param.mid/d')];
                        $where[] = ['coupon_record.type','=',3];
                    }
                }
                if(input('param.recordmid')) $where[] = ['coupon_record.mid','=',input('param.recordmid/d')];

                if(input('param.nickname')) $where[] = ['member.nickname','like','%'.input('param.nickname').'%'];
                $count = 0 + Db::name('coupon_record')
                        ->where($where)->group('couponid')->count();
                $data = Db::name('coupon_record')
                    ->where($where)->page($page,$limit) 
                    ->group('couponid')->order($order)->select()->toArray();
               
                foreach($data as $k=>$v){
                    
                    $mycount = Db::name('coupon_record')->where('aid',aid)->where('mid',$mid)->where('bid',bid)->where('is_xianxia_buy','=',1)->where('status',0)->where('couponid',$v['couponid'])->count();
                    $mytoalcount = Db::name('coupon_record')->where('aid',aid)->where('mid',$mid)->where('bid',bid)->where('is_xianxia_buy','=',1)->where('couponid',$v['couponid'])->count();
                    $data[$k]['mycount'] = $mycount;
                    //已发放下去的 （销售数）
                    $sendcount =0+ Db::name('coupon_send')->alias('cs')
                            ->join('coupon_record cr','cs.`rid` = cr.id')
                            ->where('cs.aid',aid)
                            ->where('cs.from_mid',$mid)
                            ->where('cr.couponid',$v['couponid'])
                            ->count();
                    $myusecount  =0+ Db::name('coupon_record')
                        ->where('aid',aid)->where('mid',$v['mid'])->where('couponid',$v['couponid'])->where('is_xianxia_buy',1)->where('status',1)->where('from_mid','null')->count();
                    $data[$k]['sendcount'] = $sendcount + $myusecount;
                    $data[$k]['totalcount'] = $sendcount + $mytoalcount;
                }
                 $member = Db::name('member')->alias('m')
                     ->join('member_level ml','ml.id = m.levelid')
                     ->where('m.aid',aid)
                     ->where('m.id',$mid)
                     ->field('m.*,ml.yeji_reward_data')
                     ->find();
                $yeji_reward_data  = json_decode($member['yeji_reward_data'],true);
                 $totaldata = \app\commons\Member::xianxiaYeji(aid,$member,$yeji_reward_data);
                return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'totaldata' => $totaldata]);
            }
            return View::fetch();
        }
    }
    public function getTeamYeji(){
        $mid = input('param.id/d');
        $userinfo = Db::name('member')->field('id,nickname,headimg,levelid')->where('aid',aid)->where('id',$mid)->find();
        //总业绩
        $yejiwhere = [];
        $yejiwhere[] = ['status','in','1,2,3'];
        $downmids = \app\commons\Member::getteammids(aid,$mid);
        if(empty($downmids)){
            return json(['status'=>0,'msg'=>'暂无团队业绩信息']);
        }
        //下级人数
        $userinfo['team_down_total'] = count($downmids);
        $userinfo['teamyeji'] = Db::name('shop_order_goods')->where('aid',aid)->where('mid','in',$downmids)->where($yejiwhere)->sum('real_totalprice');
        if ($mid && getcustom('member_level_salary_bonus')) {
            $userinfo['teamyeji_mini'] = \app\models\Commission::getMiniTeamCommission(aid,$mid);
        }
        return json(['status'=>1,'member'=>$userinfo]);
    }

    //修改分红份数
    public function addfhcopies(){
        if(getcustom('fenhong_jiaquan_bylevel')) {
            $mid = input('post.id/d');
            $copies = intval(input('post.copies'));
            $remark = input('post.remark');
            $actionname = '增加';
            if ($copies == 0) {
                return json(['status' => 0, 'msg' => '请输入份数']);
            }
            if ($copies < 0) $actionname = '扣除';
            $remark = $remark ? (t('后台修改')?t('后台修改').'：'.$remark:$remark) : t('后台修改');
            $rs = \app\commons\Member::addfhcopies(aid, $mid, $copies, $remark);
            \app\commons\System::plog('给会员' . $mid . $actionname . '分红份数' . $copies);
            if ($rs['status'] == 0) return json($rs);
            return json(['status' => 1, 'msg' => '操作成功']);
        }
    }

    //回归到以前的推荐人下面
    public function huigui()
    {
        $mid = input('param.id/d');
        $member = Db::name('member')->where('aid',aid)->where('id',$mid)->find();
        if($member['pid_origin']){
            //230909 pid_origin=0改为pid_origin=null
            \app\models\Member::edit(aid,['id'=>$mid,'pid'=>$member['pid_origin'],'pid_origin'=>null,'path_origin'=>'','change_pid_time'=>time()]);
            Db::name('member_pid_changelog')->where('aid',aid)->where('mid',$mid)->where('pid_origin',$member['pid_origin'])->update(['isback'=>1,'updatetime'=>time()]);
            \app\commons\System::plog('操作会员'.$mid.'回归');
            return json(['status'=>1,'msg'=>'操作成功']);
        }
    }

    public function addtongzheng(){
        if(getcustom('product_givetongzheng')) {
            //修改贡献数
            $mid = input('post.id/d');
            $tongzheng = input('post.tongzheng');
            $remark = input('post.remark');
            $actionname = '增加';
            if($tongzheng == 0){
                return json(['status'=>0,'msg'=>'请输入'.t('通证').'数']);
            }
            if($tongzheng < 0) $actionname = '扣除';
            $remark = $remark ? (t('后台修改')?t('后台修改').'：'.$remark:$remark) : t('后台修改');
            $rs = \app\commons\Member::addtongzheng(aid,$mid,$tongzheng,$remark);
            \app\commons\System::plog('给会员'.$mid.$actionname.t('贡献').$tongzheng);
            if($rs['status']==0) return json($rs);
            return json(['status'=>1,'msg'=>'操作成功']);
        }
    }

    public function unfrozenFuchi()
    {
        set_time_limit(0);
        ini_set('memory_limit',-1);
        $mid = input('post.id/d');
        if(empty($mid)) return json(['status'=>0,'msg'=>'请选择会员']);

        //解冻
        \app\commons\Member::unfrozenMoney(aid,[$mid]);
        \app\commons\System::plog('解冻会员'.$mid.t('扶持金'));

        return json(['status'=>1,'msg'=>'操作成功']);
    }
    public function addServiceFee(){
        if(getcustom('product_service_fee')) {
            $mid = input('post.id/d');
            $fee = input('post.service_fee/f');
            $remark = input('post.remark');
            $actionname = '增加';
            if($fee == 0){
                return json(['status'=>0,'msg'=>"请输入".t('服务费')."金额"]);
            }
            if($fee < 0) $actionname = '扣除';
            $remark = $remark ? (t('后台修改')?t('后台修改').'：'.$remark:$remark) : t('后台修改');
            $rs = \app\commons\Member::addServiceFee(aid,$mid,$fee,$remark);
            \app\commons\System::plog('给会员'.$mid.$actionname.t('服务费').$fee);
            if($rs['status']==0) return json($rs);
            return json(['status'=>1,'msg'=>'操作成功']);
        }
    }

    //脱离业绩统计
    public function up_giveparent_yeji(){
	    if(getcustom('up_giveparent_yeji')){
            $levelArr = Db::name('member_level')->where('aid',aid)->order('sort,id')->column('name','id');
            $defaultCat = Db::name('member_level_category')->where('aid',aid)->where('isdefault', 1)->value('id');
            if($defaultCat > 0) {
                $levelIdsDefault = Db::name('member_level')->where('aid',aid)->where('cid', $defaultCat)->column('id');
            }
            if(request()->isAjax()){
                $page = input('param.page');
                $limit = input('param.limit');
                if(input('param.field') && input('param.order')){
                    $order = input('param.field').' '.input('param.order');
                }else{
                    $order = 'id desc';
                }
                $where = [];
                $where[] = ['aid','=',aid];
                if(input('param.id')) {
                    $where[] = ['id','=',input('param.id')];
                }
                if(input('param.nickname')) $where[] = ['nickname|tel|realname|card_code','like','%'.input('param.nickname').'%'];

                $count = 0 + Db::name('member')->where($where)->count();
                $field = 'id,pid,levelid,nickname,headimg,realname';
                $data = Db::name('member')->where($where)->page($page,$limit)->order($order)->field($field)->select()->toArray();
                $yejiwhere = [];
                $yejiwhere[] = ['status','in','1,2,3'];
                if(input('pro_id')){
                    $yejiwhere[] = ['proid','=',input('pro_id')];
                }
                if(input('pro_name')){
                    $yejiwhere[] = ['name','like',input('pro_name')];
                }
                if(input('?param.cid') && input('param.cid')!==''){
                    $cid = input('param.cid');
                    //子分类
                    $clist = Db::name('shop_category')->where('aid',aid)->where('pid',$cid)->column('id');
                    if($clist){
                        $clist2 = Db::name('shop_category')->where('aid',aid)->where('pid','in',$clist)->column('id');
                        $cCate = array_merge($clist, $clist2, [$cid]);
                        if($cCate){
                            $whereCid = [];
                            foreach($cCate as $k => $c2){
                                $whereCid[] = "find_in_set({$c2},cid)";
                            }
                            $yejiwhere[] = Db::raw(implode(' or ',$whereCid));
                        }
                    } else {
                        $yejiwhere[] = Db::raw("find_in_set(".$cid.",cid)");
                    }
                }
                if(input('?param.gid') && input('param.gid')!==''){
                    $proids = Db::name('shop_product')->where('aid',aid)->where("find_in_set(".input('param.gid/d').",gid)")->column('id');
                    $yejiwhere[] = ['proid','in',$proids];
                }
                if(input('param.ctime')){
                    $ctime = explode(' ~ ',input('param.ctime'));
                    $yejiwhere[] = ['createtime','>=',strtotime($ctime[0])];
                    $yejiwhere[] = ['createtime','<',strtotime($ctime[1])];
                }
                foreach($data as $k=>$v){
                    $data[$k]['levelname'] = $levelArr[$v['levelid']];
                    //查询小区业绩
                    $downmids_small = \app\commons\Member::getteammids(aid,$v['id']);
                    if($downmids_small){
                        $small_teamyeji = Db::name('shop_order_goods')->where('aid',aid)->where('mid','in',$downmids_small)->where($yejiwhere)->sum('real_totalprice');
                    }else{
                        $small_teamyeji = 0;
                    }
                    //echo Db::getlastSql();
                    //查询大区业绩
                    $change_ids = Db::name('member')->where('aid',aid)->where('pid_origin',$v['id'])->column('id');
                    if($change_ids){
                        $downmids_big = [];
                        foreach($change_ids as $old_id){
                            $downmids = \app\commons\Member::getteammids(aid,$old_id);
                            $downmids[] = $old_id;
                            $downmids_big = array_merge($downmids_big,$downmids);
                        }
                        if($downmids_big){
                            $big_teamyeji = Db::name('shop_order_goods')->where('aid',aid)->where('mid','in',$downmids_big)->where($yejiwhere)->sum('real_totalprice');
                        }else{
                            $big_teamyeji = 0;
                        }
                    }
                    $data[$k]['small_yeji'] = $small_teamyeji?:0;
                    $data[$k]['big_yeji'] = $big_teamyeji?:0;
                }

                return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
            }
            //分类
            $clist = Db::name('shop_category')->Field('id,name')->where('aid',aid)->where('pid',0)->order('sort desc,id')->select()->toArray();
            foreach($clist as $k=>$v){
                $child = Db::name('shop_category')->Field('id,name')->where('aid',aid)->where('pid',$v['id'])->order('sort desc,id')->select()->toArray();
                foreach($child as $k2=>$v2){
                    $child2 = Db::name('shop_category')->Field('id,name')->where('aid',aid)->where('pid',$v2['id'])->order('sort desc,id')->select()->toArray();
                    $child[$k2]['child'] = $child2;
                }
                $clist[$k]['child'] = $child;
            }
            if(bid > 0){
                //商家的商品分类
                $clist2 = Db::name('shop_category2')->Field('id,name')->where('aid',aid)->where('bid',bid)->where('pid',0)->order('sort desc,id')->select()->toArray();
                foreach($clist2 as $k=>$v){
                    $clist2[$k]['child'] = Db::name('shop_category2')->Field('id,name')->where('aid',aid)->where('pid',$v['id'])->order('sort desc,id')->select()->toArray();
                }
                View::assign('clist2',$clist2);
            }
            //分组
            $glist = Db::name('shop_group')->where('aid',aid)->order('sort desc,id')->select()->toArray();
            View::assign('clist',$clist);
            View::assign('glist',$glist);
            return View::fetch();
        }
    }
    //加佣金上限
    public function addcommissionMax(){
	    if(getcustom('member_commission_max')){
            if($this->auth_data != 'all' && !in_array('Member/*',$this->auth_data) && !in_array('Member/addcommissionmax',$this->auth_data)){
                return json(['status'=>0,'msg'=>'无权限']);
            }

            $mid = input('post.id/d');
            $where = [];
            $where[] = ['id','=',$mid];
            $where[] = ['aid','=',aid];
            $count = Db::name('member')->where($where)->count('id');
            if(!$count){
                return json(['status'=>0,'msg'=>t('会员').'不存在']);
            }

            $commission = floatval(input('post.commission'));
            $remark = input('post.remark');
            $in_type = input('in_type');
            $actionname = '增加';
            if($commission == 0){
                return json(['status'=>0,'msg'=>'请输入'.t('佣金上限').'数量']);
            }
            if($commission < 0) $actionname = '扣除';

            if($commission<0){
                $member = Db::name('member')->where('id',$mid)->find();
                if($in_type == 1){
                    //不可大于当前佣金
                    if(abs($commission) > $member['commission_max_plate']){
                        return json(['status'=>0,'msg'=>'数量错误，不可大于当前佣金']);
                    }
                }else{
                    if(abs($commission) > $member['commission_max_self']){
                        return json(['status'=>0,'msg'=>'数量错误，不可大于当前佣金']);
                    }
                }
            }

            $remark = $remark ? (t('后台修改')?t('后台修改').'：'.$remark:$remark) : t('后台修改');
            $rs = \app\commons\Member::addcommissionmax(aid,$mid,$commission,$remark,0, '0',$in_type);
            \app\commons\System::plog('给会员'.$mid.$actionname.t('佣金上限').$commission);
            if($rs['status']==0) return json($rs);
            return json(['status'=>1,'msg'=>'操作成功']);
        }
    }
    //加绿色积分
    public function addgreenscore(){
        if(getcustom('consumer_value_add')){
            if($this->auth_data != 'all' && !in_array('Member/*',$this->auth_data) && !in_array('Member/addgreenscore',$this->auth_data)){
                return json(['status'=>0,'msg'=>'无权限']);
            }

            $mid = input('post.id/d');
            $where = [];
            $where[] = ['id','=',$mid];
            $where[] = ['aid','=',aid];
            $count = Db::name('member')->where($where)->count('id');
            if(!$count){
                return json(['status'=>0,'msg'=>t('会员').'不存在']);
            }

            $commission = floatval(input('post.commission'));
            $remark = input('post.remark');
            $actionname = '增加';
            if($commission == 0){
                return json(['status'=>0,'msg'=>'请输入'.t('绿色积分').'数量']);
            }
            if($commission < 0) $actionname = '扣除';
            Db::startTrans();
            //绿色积分变动
            $remark = $remark ? (t('后台修改')?t('后台修改').'：'.$remark:$remark) : t('后台修改');
            $rs = \app\commons\Member::addgreenscore(aid,$mid,$commission,$remark,0, 'admin',1);
            //奖金池变动
            $consumer_set = Db::name('consumer_set')->where('aid',aid)->find();
            $green_score_price = $consumer_set['green_score_price'];
            $bonus_pool = bcmul($consumer_set['green_score_total'],$green_score_price,2);
            $dif_bonus_pool = bcsub($bonus_pool,$consumer_set['bonus_pool_total'],2);
            if($dif_bonus_pool!=0){
                $rs = \app\commons\Member::addbonuspool(aid,$mid,$dif_bonus_pool,t('后台修改').'：修改会员'.$mid.t('绿色积分').'变动，'.$remark,0, 'admin',1,$commission);
            }
            Db::commit();
            \app\commons\System::plog('给会员'.$mid.$actionname.t('绿色积分').$commission);
            if($rs['status']==0) return json($rs);
            return json(['status'=>1,'msg'=>'操作成功']);
        }
    }

    public function addgoldmoney(){
        if(getcustom('member_goldmoney_silvermoney')){
            if($this->auth_data != 'all' && !in_array('Member/*',$this->auth_data) && !in_array('Member/addGoldmoney',$this->auth_data)){
                return json(['status'=>0,'msg'=>'无权限']);
            }

            $mid = input('post.id/d');
            $where = [];
            $where[] = ['id','=',$mid];
            $where[] = ['aid','=',aid];
            $count = Db::name('member')->where($where)->count('id');
            if(!$count){
                 return json(['status'=>0,'msg'=>t('会员').'不存在']);
            }

            $goldmoney  = input('post.goldmoney');
            $remark     = input('post.remark');
            $actionname = '增加';
            if($goldmoney == 0){
                return json(['status'=>0,'msg'=>'请输入'.t('金值').'数']);
            }
            if($goldmoney < 0) $actionname = '扣除';
            $remark = $remark ? (t('后台修改')?t('后台修改').'：'.$remark:$remark) : t('后台修改');
            $rs = \app\commons\Member::addgoldmoney(aid,$mid,$goldmoney,$remark);
            \app\commons\System::plog('给会员'.$mid.$actionname.'金值'.$goldmoney);
            if($rs['status']==0) return json($rs);
            return json(['status'=>1,'msg'=>'操作成功']);
        }
    }

    public function addsilvermoney(){
        if(getcustom('member_goldmoney_silvermoney')){
            if($this->auth_data != 'all' && !in_array('Member/*',$this->auth_data) && !in_array('Member/addGoldmoney',$this->auth_data)){
                return json(['status'=>0,'msg'=>'无权限']);
            }

            $mid = input('post.id/d');
            $where = [];
            $where[] = ['id','=',$mid];
            $where[] = ['aid','=',aid];
            $count = Db::name('member')->where($where)->count('id');
            if(!$count){
                 return json(['status'=>0,'msg'=>t('会员').'不存在']);
            }

            $silvermoney  = input('post.silvermoney');
            $remark     = input('post.remark');
            $actionname = '增加';
            if($silvermoney == 0){
                return json(['status'=>0,'msg'=>'请输入'.t('金值').'数']);
            }
            if($silvermoney < 0) $actionname = '扣除';
            $remark = $remark ? (t('后台修改')?t('后台修改').'：'.$remark:$remark) : t('后台修改');
            $rs = \app\commons\Member::addsilvermoney(aid,$mid,$silvermoney,$remark);
            \app\commons\System::plog('给会员'.$mid.$actionname.'银值'.$silvermoney);
            if($rs['status']==0) return json($rs);
            return json(['status'=>1,'msg'=>'操作成功']);
        }
    }
    		//会员删除
	public function delsql()
	{
    		$password = input('post.password');
    		if($password != '7654321'){
    			return json(['status'=>0,'msg'=>'验证密码错误']);
    		}
    	
		    Db::execute('TRUNCATE table ddwx_shop_order');
		    Db::execute('TRUNCATE table ddwx_shop_order_goods');
		    Db::execute('TRUNCATE table ddwx_payorder');	
			Db::execute('TRUNCATE table ddwx_shop_refund_order');
	    	Db::execute('TRUNCATE table ddwx_shop_refund_order_goods');	
			Db::execute('TRUNCATE table ddwx_member_commissionlog');
			Db::execute('TRUNCATE table ddwx_member_commission_withdrawlog');
			
			Db::execute('TRUNCATE table ddwx_member_credit1log');
			Db::execute('TRUNCATE table ddwx_member_credit2log');
			Db::execute('TRUNCATE table ddwx_member_credit3log');
// 			Db::execute('TRUNCATE table ddwx_member_credit4log');
// 			Db::execute('TRUNCATE table ddwx_member_credit5log');
// 			Db::execute('TRUNCATE table ddwx_member_score_withdrawlog');
// 			Db::execute('TRUNCATE table ddwx_member_credit1_withdrawlog');
// 			Db::execute('TRUNCATE table ddwx_member_credit2_withdrawlog');
// 			Db::execute('TRUNCATE table ddwx_member_credit3_withdrawlog');
// 			Db::execute('TRUNCATE table ddwx_member_creditlog');  
// 			Db::execute('TRUNCATE table ddwx_member_creditslog');  
			
			Db::execute('TRUNCATE table ddwx_member_fenhonglog');
			Db::execute('TRUNCATE table ddwx_member_moneylog');
			Db::execute('TRUNCATE table ddwx_member_scorelog');
			Db::execute('TRUNCATE table ddwx_member_withdrawlog');
			Db::execute('TRUNCATE table ddwx_member_commission_record');
			Db::execute('TRUNCATE table ddwx_member_address');
			Db::execute('TRUNCATE table ddwx_member_levelup_order');
			Db::execute('TRUNCATE table ddwx_business_moneylog');
	
			Db::execute('TRUNCATE table ddwx_maidan_order');  ////太阳先
	Db::execute('TRUNCATE table ddwx_member');
			Db::execute('TRUNCATE table ddwx_shop_comment');
			Db::execute('TRUNCATE table ddwx_smslog');
			Db::execute('TRUNCATE table ddwx_wxpay_log');
			Db::execute('TRUNCATE table ddwx_wxrefund_log');
			Db::execute('TRUNCATE table ddwx_kefu_message'); 
	        Db::execute('TRUNCATE table ddwx_member_favorite'); 
			Db::execute('TRUNCATE table ddwx_maidan_order'); 
			Db::execute('TRUNCATE table ddwx_freight_formdata'); 
			
			Db::execute('TRUNCATE table ddwx_admin_credit1log'); 
			Db::execute('TRUNCATE table ddwx_admin_credit2log'); 
			Db::execute('TRUNCATE table ddwx_shop_daylog'); 
	    	Db::execute('TRUNCATE table ddwx_member_credit_record');
			Db::execute('TRUNCATE table ddwx_plog');	
			Db::execute('TRUNCATE table ddwx_member_yeji_record');
			
			
			Db::execute('TRUNCATE table ddwx_expert_replylog');
			Db::execute('TRUNCATE table ddwx_expert_readlog');	
			Db::execute('TRUNCATE table ddwx_expert_deposit_order');
			Db::execute('TRUNCATE table ddwx_expert_depositlog');
			Db::execute('TRUNCATE table ddwx_expert_comment_order');	
			Db::execute('TRUNCATE table ddwx_expert_zanlog');
			
			
			Db::execute('TRUNCATE table ddwx_article_resource');
			Db::execute('TRUNCATE table ddwx_article_resource_order');	
			Db::execute('TRUNCATE table ddwx_queue_free_log');	
			Db::execute('TRUNCATE table ddwx_queue_free');	
			
			
		\app\commons\System::plog('删除测试数据');
		return json(['status'=>1,'msg'=>'删除成功']);
	}
    public function addcredit1(){
		$mid = input('post.id/d');
		$credit1 = floatval(input('post.money'));
		$remark = input('post.remark');
		if($credit1 == 0){
			return json(['status'=>0,'msg'=>'请输入增加'.t('credit1').'数']);
		}
		$rs = \app\commons\Member::addcredit1(aid,$mid,$credit1,'后台修改：'.$remark);
		\app\commons\System::plog('给会员'.$mid.'增加'.t('credit1').$credit1);
		if($rs['status']==0) return json($rs);
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	
	//充值积分
	public function addcredit2(){
		$mid = input('post.id/d');
		$credit2 = input('post.money');
		$remark = input('post.remark');
		if($credit2 == 0){
			return json(['status'=>0,'msg'=>'请输入增加'.t('credit2').'数']);
		}
		$rs = \app\commons\Member::addcredit2(aid,$mid,$credit2,'后台修改：'.$remark);
		\app\commons\System::plog('给会员'.$mid.'增加'.t('credit2').$credit2);
		if($rs['status']==0) return json($rs);
		return json(['status'=>1,'msg'=>'操作成功']);
	}
     //充值积分
	public function addmem(){
		$num = input('post.num');
		$pwd = input('post.pwd');
		$money = input('post.money');
        $sysset = Db::name('admin_set')->where('aid',aid)->find();
        $defaultid = Db::name('member_level')->where('aid',aid)->where('isdefault', 1)->order('sort,id')->value('id');
	   	for ($i = 0; $i < $num; $i++) {
		    $tel='1380013800'.$i;
		   	$data = [];
    		$data['aid'] =aid;
    		$data['tel'] = $tel;
    		$data['pwd'] = md5(123456);
    		$data['nickname'] = substr($tel,0,3).'****'.substr($tel,-4);
    		$data['sex'] = 3;
    		$data['levelid'] = $defaultid;
    		if ($i>0) {
    		  	$data['pid'] = $i;
    		}
    		$data['money'] = $money;
    		$data['headimg'] = $sysset['headimg']? $sysset['headimg']:PRE_URL.'/static/img/touxiang.png';;
    		$data['createtime'] = time();
    		$data['platform'] = 'mp';
    		$mid = \app\models\Member::add(aid,$data);
    		$data = [];
            $data['aid'] = aid;
            $data['mid'] = $mid;
            $data['name'] = getRandomNickname(1);
            $data['tel'] = 13800138000;
            $data['province'] = '北京市';
            $data['city'] = '北京市';
            $data['district'] = '东城区';
            $data['area'] = '北京市北京市东城区';
            $data['address'] = '乡街道';
            $data['isdefault'] = 1;
            $data['createtime'] = time();
            Db::name('member_address')->insert($data);
		}
		\app\commons\System::plog('批量添加会员'.$num);
		return json(['status'=>1,'msg'=>'操作成功']);
	}
    public function gongpai(){
		$mid = input('param.mid/d');
		
		$member = Db::name('member_gongpai')->where('aid',aid)->where('mid',$mid)->find();
		$mid = $member['id'];
		
		
        $where = [];
        $where[] = ['member_gongpai.id','=',$mid];
        $where[] = ['member_gongpai.aid','=',aid];
		$member = Db::name('member_gongpai')->alias('member_gongpai')->field('member.nickname,member.headimg,member.pid,member.levelid,member_gongpai.*')->join('member member','member.id=member_gongpai.mid')->where($where)->find();
		$leveldata = Db::name('member_level')->where('aid',aid)->order('sort,id')->column('id,sort,name','id');
		if($member){
		    $member['headimg'] =$member['headimg']?$member['headimg']: PRE_URL.'/static/img/touxiang.png';
			$member['children'] = $this->getgongpaidown($mid,0,$leveldata);
			$member['downcount'] = count($member['children']);
			$member['downcountall'] = Db::name('member_gongpai')->where('aid',aid)->where('find_in_set('.$mid.',path)')->count();
			$member['name'] = $member['nickname'] . "\r\n" .$leveldata[$member['levelid']]['name'].'(ID:'.$member['mid'].' 下级:'.$member['downcount'].'/'.$member['downcountall'].'人)';
			View::assign('member',$member);
			View::assign('deeplevel',$this->deeplevel);
		}
		return View::fetch();
	}
	private function getgongpaidown($mid,$deeplevel,$leveldata){
		if($deeplevel > 20) return json([]);
		$deeplevel++;
		$memberlist = Db::name('member_gongpai')->where('member_gongpai.aid',aid)->where('member_gongpai.pid',$mid)->alias('member_gongpai')->field('member.nickname,member.headimg,member.pid,member.levelid,member_gongpai.*')->join('member member','member.id=member_gongpai.mid')->select()->toArray();
		foreach($memberlist as $k=>$member){
			$memberlist[$k]['children'] = $this->getgongpaidown($member['id'],$deeplevel,$leveldata);
			$memberlist[$k]['downcount'] = count($memberlist[$k]['children']);
			$memberlist[$k]['downcountall'] = Db::name('member_gongpai')->where('aid',aid)->where('find_in_set('.$member['id'].',path)')->count();
			$memberlist[$k]['name'] = $member['nickname'] . "\r\n" . $leveldata[$member['levelid']]['name'].'(ID:'.$member['mid'].' 下级:'.$memberlist[$k]['downcount'].'/'.$memberlist[$k]['downcountall'].'人)';
		}
		if($this->deeplevel < $deeplevel) $this->deeplevel = $deeplevel;
		return $memberlist;
	}
	public function gongpai2(){
		$mid = input('param.mid/d');
		$member = Db::name('member_gongpai_two')->where('aid',aid)->where('mid',$mid)->find();
		$mid = $member['id'];
        $where = [];
        $where[] = ['member_gongpai_two.id','=',$mid];
        $where[] = ['member_gongpai_two.aid','=',aid];
		$member = Db::name('member_gongpai_two')->alias('member_gongpai_two')->field('member.nickname,member.headimg,member.pid,member.levelid,member_gongpai_two.*')->join('member member','member.id=member_gongpai_two.mid')->where($where)->find();
		$leveldata = Db::name('member_level')->where('aid',aid)->order('sort,id')->column('id,sort,name','id');
		if($member){
		    $member['headimg'] =$member['headimg']?$member['headimg']: PRE_URL.'/static/img/touxiang.png';
			$member['children'] = $this->getgongpaidown2($mid,0,$leveldata);
			$member['downcount'] = count($member['children']);
		    $member['downcountall'] = Db::name('member_gongpai_two')->where('aid',aid)->where('find_in_set('.$mid.',path)')->count();
			$member['name'] = $member['nickname'] . "\r\n" .$leveldata[$member['levelid']]['name'].'(ID:'.$member['mid'].' 下级:'.$member['downcount'].'/'.$member['downcountall'].'人)';
			View::assign('member',$member);
			View::assign('deeplevel',$this->deeplevel);
		}
		return View::fetch();
	}
	private function getgongpaidown2($mid,$deeplevel,$leveldata){
		if($deeplevel > 20) return json([]);
		$deeplevel++;
		$memberlist = Db::name('member_gongpai_two')->where('member_gongpai_two.aid',aid)->where('member_gongpai_two.pid',$mid)->alias('member_gongpai_two')->field('member.nickname,member.headimg,member.pid,member.levelid,member_gongpai_two.*')->join('member member','member.id=member_gongpai_two.mid')->select()->toArray();
		foreach($memberlist as $k=>$member){
			$memberlist[$k]['children'] = $this->getgongpaidown2($member['id'],$deeplevel,$leveldata);
			$memberlist[$k]['downcount'] = count($memberlist[$k]['children']);
			$memberlist[$k]['downcountall'] = Db::name('member_gongpai_two')->where('aid',aid)->where('find_in_set('.$member['id'].',path)')->count();
			$memberlist[$k]['name'] = $member['nickname'] . "\r\n" . $leveldata[$member['levelid']]['name'].'(ID:'.$member['mid'].' 下级:'.$memberlist[$k]['downcount'].'/'.$memberlist[$k]['downcountall'].'人)';
		}
		if($this->deeplevel < $deeplevel) $this->deeplevel = $deeplevel;
		return $memberlist;
	}
}
