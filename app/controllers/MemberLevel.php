<?php


// +----------------------------------------------------------------------
// | 会员等级
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class MemberLevel extends Common
{
    public function initialize(){
		parent::initialize();

        $request = request();
        $action = $request->action();
        if($action != 'chooselevel')
		    if(bid > 0) showmsg('无访问权限');
	}
	//列表
    public function index(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'sort,id';
			}
			$where = [['aid','=',aid]];
			$count = 0 + Db::name('member_level')->where($where)->count();
			$data = Db::name('member_level')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			foreach($data as $k=>$v){
				$atj = array();
				if($v['can_apply']){
					//if($v['apply_ordercount'] > 0) $atj[]='订单满'.$v['apply_ordercount'].'个';
					if($v['apply_ordermoney'] > 0) $atj[]='订单金额满'.$v['apply_ordermoney'].'元';
					if($v['apply_rechargemoney'] > 0) $atj[]='充值金额满'.$v['apply_rechargemoney'].'元';
					if($atj){
						$data[$k]['applytj'] = implode(' 或 ',$atj);
					}else{
						$data[$k]['applytj'] = '无';
					}
				}else{
					$data[$k]['applytj'] = '不可申请';
				}
				if($v['can_up']){
					$tj = array();
					//if($v['up_ordercount'] > 0) $tj[]='订单满'.$v['up_ordercount'].'个';
					if($v['up_wxpaymoney'] > 0) $tj['up_wxpaymoney']='微信支付金额满'.$v['up_wxpaymoney'].'元';
					if($v['up_ordermoney'] > 0) $tj['up_ordermoney']='订单金额满'.$v['up_ordermoney'].'元';
					if($v['up_rechargemoney'] > 0) $tj['up_rechargemoney']='充值金额满'.$v['up_rechargemoney'].'元';
          if($v['up_perpaymoney'] > 0) $tj['up_perpaymoney']='单次消费满'.$v['up_perpaymoney'].'元';
          if(getcustom('member_levelup_orderprice')){
            if($v['up_orderprice'] > 0) $tj['up_orderprice']='单次订单满'.$v['up_orderprice'].'元';
          }
					if($v['up_fxordermoney'] > 0) $tj['up_fxordermoney']='分销订单满'.$v['up_fxordermoney'].'元';
					if($v['up_fxdowncount'] > 0) $tj['up_fxdowncount']='下级总人数满'.$v['up_fxdowncount'].'个';
                    if($v['up_fxdowncount_and'] > 0) $tj['up_fxdowncount_and']='下级总人数满'.$v['up_fxdowncount_and'].'个';
					if($v['up_proid'] > 0 && $v['up_pronum'] > 0) $tj['up_proid']='购买商品['.Db::name('shop_product')->where('id',$v['up_proid'])->value('name').']*'.$v['up_pronum'];
					if($v['up_getmembercard']==1) $tj[]='领取微信会员卡';
					if(getcustom('member_levelup_businessnum')){
						if($v['up_businessnum'] > 0) $tj['up_businessnum']='推荐商家成功入驻数量满'.$v['up_businessnum'].'个';
					}
					if(getcustom('member_up_binding_tel')){
					    if($v['up_binding_tel']>0)$tj['up_binding_tel']='绑定手机号';
                    }
                    if(getcustom('levelup_teamnum_peoplenum')){
                        $up_team_path_num_tj = '';
                        if($v['up_team_path_num']>0) {
                            $up_team_path_num_tj .='团队满'.$v['up_team_path_num'].'条线';
                            if($v['up_team_people_num']>0){
                                $up_team_path_num_tj .='，每条线超'.$v['up_team_people_num'].'人';
                                if($v['up_team_path_level']){
                                    $up_team_path_num_tj .='等级ID：'.$v['up_team_path_level'];
                                }
                            }
                        }
                        if($up_team_path_num_tj){
                            $tj['up_team_path_num']=$up_team_path_num_tj;
                        }
                    }
					if($tj){
					    $i = 1;
                        $aglevelList[$k]['uptj'] = '';
					    foreach($tj as $key => $item) {
					        if($i == 1) {
                                $data[$k]['uptj'] .= $item;
                            } else {
					            $realtion = ' 或 ';
					            if($v['up_fxorder_condition'] == 'and' && $key == 'up_fxordermoney') {
                                    $realtion = ' 且 ';
                                }
                    if (getcustom('member_levelup_orderprice') && $v['up_orderprice_condition'] == 'and' && $key == 'up_orderprice') {
                      $realtion = ' 且 ';
                    }
                                if($key == 'up_fxdowncount_and') {
                                    $realtion = ' 且 ';
                                }
                                if($v['up_buygoods_condition'] == 'and' && $key == 'up_proid') {
                                    $realtion = ' 且 ';
                                }
                                if(getcustom('member_up_binding_tel')){
                                    if($v['up_binding_tel_condition'] == 'and' && $key == 'up_binding_tel') {
                                        $realtion = ' 且 ';
                                    } 
                                }
                                if(getcustom('levelup_teamnum_peoplenum')){
                                    if($v['up_team_path_condition'] == 'and' && $key == 'up_team_path_num') {
                                        $realtion = ' 且 ';
                                    }
                                }
                                
                                
                                $data[$k]['uptj'] .= $realtion.$item;
                            }
                            $i++;
                        }
					}else{
						$data[$k]['uptj'] = '不自动升级';
					}
				}else{
					$data[$k]['uptj'] = '不自动升级';
					if($v['isdefault']){
						$data[$k]['uptj'] = '默认等级无需升级';
					}
				}
				if($v['can_agent']==1){
					$data[$k]['commission'] = $v['commission1'].($v['commissiontype']==1?'元':'%');
				}elseif($v['can_agent']==2){
					$data[$k]['commission'] = $v['commission1'].($v['commissiontype']==1?'元':'%').' / '.$v['commission2'].($v['commissiontype']==1?'元':'%');
				}elseif($v['can_agent']==3){
					$data[$k]['commission'] = $v['commission1'].($v['commissiontype']==1?'元':'%').' / '.$v['commission2'].($v['commissiontype']==1?'元':'%').' / '.$v['commission3'].($v['commissiontype']==1?'元':'%');
				}elseif($v['can_agent']==99){
                    $data[$k]['commission'] = '递归分销';
                }else{
					$data[$k]['commission'] = '无分销权限';
				}
				$cat = Db::name('member_level_category')->where('aid', aid)->where('id',$v['cid'])->find();
                $data[$k]['cat_name'] = $cat['name'];
                $data[$k]['defaultCat'] = $cat['isdefault'];
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}

		$haslevel = Db::name('member_level')->where('aid',aid)->where('isdefault',1)->find();
		if(!$haslevel){
			Db::name('member_level')->insert(array('aid'=>aid,'isdefault'=>1,'name'=>'普通会员','icon'=>PRE_URL.'/static/imgsrc/level_1.png'));
		}
		return View::fetch();
	}
	//编辑
	public function edit(){
		if(input('param.id')){
			$info = Db::name('member_level')->where('aid',aid)->where('id',input('param.id/d'))->find();
            $info['up_give_score'] = dd_money_format($info['up_give_score'],$this->score_weishu);
			//if($info['islock'] == 1 && session('isunlock')!=1){
			//	showmsg('请先输入解锁密码');
			//}
            if(getcustom('next_level_set')){
                $level_name = '';
                if($info['next_level_id']){
                    $level_name = Db::name('member_level')->where('aid',aid)->where('id',$info['next_level_id'])->value('name');
                }
                $info['next_level_name'] = $level_name??'';
            }
		}else{
			$info = array('id'=>'','yxqdate'=>0);
			$info['sort'] = 1 + Db::name('member_level')->where('aid',aid)->max('sort');
			$level = 1 + Db::name('member_level')->where('aid',aid)->count();
			$info['icon'] = PRE_URL.'/static/imgsrc/level_'.$level.'.png';
		}
		if(!$info['apply_formdata']){
			$info['apply_formdata'] = json_encode([
				['key'=>'input','val1'=>'姓名','val2'=>'请填写姓名','val3'=>'1'],
				['key'=>'input','val1'=>'手机号','val2'=>'请填写手机号','val3'=>'1'],
			]);
		}
		if(getcustom('levelup_from_levelid')){
            //升级前置等级条件
            $info['gettj'] = explode(',',$info['gettj']);
        }
		if(getcustom('member_levelup_auth')) {
            $info['saletj'] = explode(',', $info['saletj']);
        }
        if(getcustom('member_levelup_givechild')){
            $team_levelup_data = json_decode($info['team_levelup_data'],true);
            View::assign('team_levelup_data',$team_levelup_data);
        }
		View::assign('info',$info);
		View::assign('plug_businessqr', getcustom('plug_businessqr'));
        $catList = Db::name('member_level_category')->where('aid',aid)->select()->toArray();
        View::assign('catList',$catList);
        View::assign('isdefault_cat',true);
        $default_cat = collect($catList)->where('isdefault',1)->first();
        View::assign('default_cat',$default_cat);
        if($info['id'] && $default_cat && $info['cid'] != $default_cat['id']) {
            View::assign('isdefault_cat',false);
        }
		$sysset = Db::name('admin_set')->where('aid',aid)->find();
		View::assign('sysset',$sysset);

		
		if(getcustom('teamfenhong_pingji') && ($this->auth_data == 'all' || in_array('teamfenhong_pingji',$this->auth_data))){
			$teamfenhong_pingji = true;
		}else{
			$teamfenhong_pingji = false;
		}
        if(getcustom('up_give_parent_coupon')){
            if($info && $info['up_give_parent_coupon_ids']){
                $coupon_ids = explode(',', $info['up_give_parent_coupon_ids']);
                $coupon_nums = explode(',', $info['up_give_parent_coupon_nums']);
                $couponList = Db::name('coupon')->where('aid',aid)->where('bid', 0)->whereIn('id', $coupon_ids)->select()->toArray();
            }else{
                $couponList = [];
                $coupon_nums = [];
            }
            View::assign('couponList',$couponList);
            View::assign('up_give_parent_coupon_nums',$coupon_nums);
        }

        //升级赠送优惠券
        if(getcustom('up_give_coupon')){
            //商城优惠券
            $upcouponList = [];
            if($info && $info['up_give_coupon']){
                $give_coupon = json_decode($info['up_give_coupon'],true);
                foreach ($give_coupon as $k=>$v){
                    $_tmpcoupon = Db::name('coupon')->where('aid',aid)->where('bid', 0)->whereIn('id', $v['id'])->find();
                    if($_tmpcoupon){
                        $v['stock'] = $_tmpcoupon['stock'];
                        $v['name'] = $_tmpcoupon['name'];
                        $upcouponList[] = $v;
                    }
                }
            }
            //餐饮优惠券
            $upcouponRList = [];
            if($info && $info['up_give_restaurant_coupon']){
                $give_restaurant_coupon = json_decode($info['up_give_restaurant_coupon'],true);
                foreach ($give_restaurant_coupon as $k=>$v){
                    $_tmpcoupon = Db::name('coupon')->where('aid',aid)->where('bid', 0)->whereIn('id', $v['id'])->find();
                    if($_tmpcoupon){
                        $v['stock'] = $_tmpcoupon['stock'];
                        $v['name'] = $_tmpcoupon['name'];
                        $upcouponRList[] = $v;
                    }
                }
            }
            View::assign('upcouponList',$upcouponList);
            View::assign('upcouponRList',$upcouponRList);
        }
        View::assign('teamfenhong_pingji',$teamfenhong_pingji);
        if(getcustom('member_gongxian')){
            View::assign('member_gongxian_status',$this->admin['member_gongxian_status']);
        }
        if(getcustom('member_recharge_yj')){
            $yjlist = [];
            if($info['yj_datas']){
                //业绩数据
                $yj_datas = json_decode($info['yj_datas'],true);
                if($yj_datas){
                    $yjlist = $yj_datas['yj_data']?$yj_datas['yj_data']:[];
                }
            }
            View::assign('yjlist',$yjlist);
        }
        if(getcustom('level_auto_down') || getcustom('member_level_down_commission')){
            //所有级别
            $all_level = Db::name('member_level')->where('aid',aid)->order('sort asc')->select()->toArray();
            View::assign('all_level',$all_level);
        }
        if(getcustom('coupon_xianxia_buy')){
            $xianxia_coupon_jl = json_decode($info['xianxia_coupon_jl'],true);
            $nodefault_level_list = Db::name('member_level')->where('aid',aid)->order('sort asc')->where('isdefault','=',0)->field('id,name')->select()->toArray();
            foreach($nodefault_level_list as $key=>$val){
                $nodefault_level_list[$key]['money'] = $xianxia_coupon_jl[$val['id']];
            }
            View::assign('nodefault_level_list',$nodefault_level_list);

            $prv_level = json_decode($info['xianxia_coupon_vip_tj'],true);
            $prv_level_list = Db::name('member_level')->where('aid',aid)->order('sort asc')->where('isdefault','=',0)->field('id,name')->select()->toArray();
            foreach($prv_level_list as $key=>$level){
                $children_levellist = $prv_level_list;//第二级 等级列表
                 if($prv_level && $prv_level[$level['id']]){//如果设置存在该等级的值
                     $prv_data = $prv_level[$level['id']];
                     foreach($children_levellist as $k=> $children){
                         if($prv_data[$children['id']]){
                             $children_levellist[$k]['money'] = $prv_data[$children['id']];
                         }
                     }
                 }
                $prv_level_list[$key]['children'] = $children_levellist;
            }
            View::assign('prv_level_list',$prv_level_list);
            View::assign('xianxia_full',json_decode($info['xianxia_full'],true));
        }
        //薪资补贴
        if(getcustom('member_level_salary_bonus')){
            $salary = $info['salary_bonus_content']?json_decode($info['salary_bonus_content'],true):[];
            if(empty($salary)){
                $salary = [
                    ['member_num'=>0,'yj_amount'=>0,'bonus'=>0]
                ];
            }
            View::assign('salary',$salary);
        }
	
		//奖励上级佣金
        if(getcustom('member_levelup_parentcommission')){
			$levelup_levelist = Db::name('member_level')->where('aid',aid)->where('can_agent','>',0)->order('sort,id')->select()->toArray();
            $levelup_level = json_decode($info['levelup_parentcommission'],true);
            foreach($levelup_levelist as $key=>$val){
                $levelup_levelist[$key]['money'] = $levelup_level[$val['id']];
            }
			View::assign('levelup_levelist',$levelup_levelist);
        }
	 

        $default_cid = Db::name('member_level_category')->where('aid',aid)->where('isdefault', 1)->value('id');
        $default_cid = $default_cid ? $default_cid : 0;
        $levellist = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->order('sort,id')->select()->toArray();
        View::assign('levellist',$levellist);
        $maidan_fenhong_new = 0;
        if(getcustom('maidan_fenhong_new')){
            //买单分红
            if($sysset['maidanfenhong']==1){
                $maidan_fenhong_new = 1;
            }
        }
        View::assign('maidan_fenhong_new',$maidan_fenhong_new);

		if(getcustom('member_levelup_givecoupon')){
			$cycledata = ['1'=>'单次','2'=>'每周','3'=>'每月'];
			$givecoupondata = json_decode($info['givecoupondata'],true);
			foreach($givecoupondata as $k=>$g){
				$coupon = Db::name('coupon')->field('id,name,stock')->where('aid',aid)->where('id', $g['coupon_id'])->find();
				$givecoupondata[$k]['name'] = $coupon['name'];
				$givecoupondata[$k]['stock'] = $coupon['stock'];
				$givecoupondata[$k]['cyclename'] = $cycledata[$g['cycletype']];
			}
			View::assign('givecoupondata',$givecoupondata);      
		}

        $agree_desc = '';
		if(getcustom('up_level_agree')){
		    $agree_desc = '开启后，如满足升级条件，会员中心页面会弹窗提示，点击同意才能升级';
        }
        if(getcustom('up_level_agree2')){
            $agree_desc = '开启后，升级后进入我的佣金页面需要先同意升级协议并签字';
        }
        if(getcustom('up_level_agree3')){
            $agree_desc = '开启后，前端申请升级需要阅读同意升级协议才可以提交申请';
        }
        View::assign('agree_desc',$agree_desc);

        if(getcustom('teamfenhong_pingji_single_bl')){
            $teamfenhong_pingji_single_bl = json_decode($info['teamfenhong_pingji_single_bl'],true);
            $teamfenhong_pingji_single_money = json_decode($info['teamfenhong_pingji_single_money'],true);
            View::assign('teamfenhong_pingji_single_bl',$teamfenhong_pingji_single_bl);
            View::assign('teamfenhong_pingji_single_money',$teamfenhong_pingji_single_money);
        }
        //是否有见点奖
        $up_giveparent_prize = 0;
        if(getcustom('up_giveparent_prize')){
            $up_giveparent_prize = 1;
        }
        View::assign('up_giveparent_prize',$up_giveparent_prize);
        if(getcustom('member_create_child_order')){
            if($this->auth_data == 'all' || in_array('CreateChildOrder/*',$this->auth_data)){
                $create_child_order = true;
            }else{
                $create_child_order = false;
            }
            View::assign('create_child_order',$create_child_order);
        }

        return View::fetch();
	}
	public function save(){
		$info = input('post.info/a');
		$datatype = input('post.datatype/a');
		$dataval1 = input('post.dataval1/a');
		$dataval2 = input('post.dataval2/a');
		$dataval3 = input('post.dataval3/a');

		if(getcustom('member_levelup_givechild')){
            $info['team_levelup_data'] = '';
            if($info['team_levelup'] == 1){
                $team_levelup_data = input('post.team_levelup_data/a');           
                $info['team_levelup_data'] = json_encode($team_levelup_data,JSON_UNESCAPED_UNICODE);
            }
            
        }
		if(getcustom('member_up_binding_tel')){
            $dataval4 = input('post.dataval4/a');
            $dataval6 = input('post.dataval6/a');
        }
        if(getcustom('levelup_from_levelid')) {
            //升级前置等级条件
            $info['gettj'] = implode(',', $info['gettj']);
        }
		$dhdata = array();
		foreach($datatype as $k=>$v){
			if($dataval3[$k]!=1) $dataval3[$k] = 0;
			$dataval = array('key'=>$v,'val1'=>$dataval1[$k],'val2'=>$dataval2[$k],'val3'=>$dataval3[$k]);
            if(getcustom('member_up_binding_tel')) {
                if ($dataval4) {
                    $val4 = ['val4' => $dataval4[$k]];
                    $dataval = array_merge($dataval, $val4);
                }
                if ($dataval6) {
                    $val6 = ['val6' => $dataval6[$k]];
                    $dataval = array_merge($dataval, $val6);
                }
            }
			$dhdata[] =$dataval; 
		}
		$info['apply_formdata'] = json_encode($dhdata,JSON_UNESCAPED_UNICODE);


      	$info['commissiondata7'] = jsonEncode(input('post.commissiondata7/a'));	
        $info['fenhongdata1'] = jsonEncode(input('post.fenhongdata1/a'));	







        $default_cid = Db::name('member_level_category')->where('aid',aid)->where('isdefault', 1)->value('id');
        $default_cid = $default_cid ? $default_cid : 0;
        if($info['cid'] > 0 && $info['cid'] != $default_cid) {
            //非默认分组不支持设置分销和优惠
            unset($info['discount']);
            $info['can_agent'] = 0;
        }
        if(empty($info['cid'])) $info['cid'] = $default_cid;

		if(getcustom('up_give_parent_coupon')){
			$info['up_give_parent_coupon_nums'] = implode(',',$info['up_give_parent_coupon_nums']);
		}
		//升级赠送优惠券
        if(getcustom('up_give_coupon')){
            //商城优惠券
            $db_give_coupon = [];
            $give_coupons = $info['up_give_coupon']??[];
            if($give_coupons){
                $giveCouponIds = $give_coupons['ids']??[];
                $giveCouponNums = $give_coupons['nums']??[];
                foreach ($giveCouponIds as $gk=>$gcpid){
                    if($gcpid && isset($giveCouponNums[$gk])){
                        $db_give_coupon[] = ['id'=>$gcpid,'num'=>$giveCouponNums[$gk]];
                    }
                }
            }
            $info['up_give_coupon'] = $db_give_coupon?json_encode($db_give_coupon):'';
            //餐饮优惠券
            $db_give_restaurant_coupon = [];
            $give_restaurant_coupons = $info['up_give_restaurant_coupon']??[];
            if($give_restaurant_coupons){
                $giveRCouponIds = $give_restaurant_coupons['ids']??[];
                $giveRCouponNums = $give_restaurant_coupons['nums']??[];
                foreach ($giveRCouponIds as $gk=>$gcpid){
                    if($gcpid && isset($giveRCouponNums[$gk])){
                        $db_give_restaurant_coupon[] = ['id'=>$gcpid,'num'=>$giveRCouponNums[$gk]];
                    }
                }
            }
            $info['up_give_restaurant_coupon'] = $db_give_restaurant_coupon?json_encode($db_give_restaurant_coupon):'';
        }
        if(getcustom('maidan_commission_score')){
            $info['maidan_commission_score1'] = $info['maidan_commission_score1'];
            $info['maidan_commission_score2'] = $info['maidan_commission_score2'];
            $info['maidan_commission_score3'] = $info['maidan_commission_score3'];
        }
        if(getcustom('commission_max')){
            $info['commission_max'] = $info['commission_max'];
        }
        if(getcustom('levelup_code')){
            if($info['apply_code']){
                //查询是否已有申请功能开启的等级和他相同
                $find_code = Db::name('member_level')->where('aid',aid)->where('can_apply',1)->where('apply_code',$info['apply_code'])->field('id,name')->find();
                if($find_code){
                    if($info['id']){
                        if($info['id'] !=$find_code['id']){
                            return json(['status'=>0,'msg'=>'操作失败，此验证码已在'.$find_code['name'].'等级中使用']);
                        }
                    }else{
                        return json(['status'=>0,'msg'=>'操作失败，此验证码已在'.$find_code['name'].'使用']);
                    }
                }
            }
        }
        if(getcustom('team_auth')){
            $info['team_month_data'] = $info['team_month_data']?:0;
            $info['team_down_total'] = $info['team_down_total']?:0;
            $info['team_yeji'] = $info['team_yeji']?:0;
            $info['team_self_yeji'] = $info['team_self_yeji']?:0;
            $info['team_score'] = $info['team_score']?:0;
        }
        if(getcustom('member_recharge_yj')){
            if(!$info['open_yj']){
                $info['open_yj'] = 0;
            }
            if(!$info['recharge_yj_ratio']){
                $info['recharge_yj_ratio'] = 0;
            }
            if(!$info['yj_moneys_after']){
                $info['yj_moneys_after'] = 0;
            }
            if(!$info['yj_ratios_after']){
                $info['yj_ratios_after'] = 0;
            }

            $yj_datas = [];
            if($info['yj_datas']){
                $yj_data = [];
                //金额数据
                $moneys = $info['yj_datas']['moneys']?$info['yj_datas']['moneys']:'';
                $ratios = $info['yj_datas']['moneys']?$info['yj_datas']['ratios']:'';
                if($moneys){
                    foreach($moneys as $mk=>&$mv){
                        $data = [];
                        $data['money'] = $mv;
                        $data['ratio'] = 0;
                        if($ratios){
                            foreach($ratios as $rk=>&$rv){
	                            if($rk == $mk){
	                                $data['ratio'] = $rv?$rv:0;
	                            }
                            }
                        }
                        array_push($yj_data,$data);
                    }
                    unset($mk);
                    unset($mv);
                }

                if($yj_data){
                	//重新排序
	                $new_yj_data = array_column($yj_data,'money');
	                array_multisort($new_yj_data,SORT_ASC,$yj_data);
                    $yj_datas['yj_data'] = $yj_data;
                }
            }
            $info['yj_datas'] = json_encode($yj_datas);
        }

        //升级后脱离和升级后回归只能设置一个，否则矛盾
        if(!empty($info['up_change_pid']) && $info['up_change_pid']==1){
            $info['up_change_back'] = 0;
        }
        if(getcustom('coupon_xianxia_buy')){
            $xianxia = input('param.xianxia');
            $xianxia_coupon_jl = [];
            foreach($xianxia['levelid'] as $key=>$val){
                $xianxia_coupon_jl[$val] = $xianxia['money'][$key];
            }
            $info['xianxia_coupon_jl'] = json_encode($xianxia_coupon_jl);
            $yeji_limit = input('param.yeji_limit');
            $yeji_reward = input('param.yeji_reward');
            $yeji_reward_data = [];
            foreach ($yeji_limit as $key=>$val){
                $yeji_reward_data[$key]['limit'] = $val;
                $yeji_reward_data[$key]['reward'] = $yeji_reward[$key];
            }
            $info['yeji_reward_data'] = json_encode($yeji_reward_data);
            $xianxiatj = input('param.xianxiatj');
            $xianxia_tj_data = [];
            foreach($xianxiatj as $key=>$val){
                $ldata = [];
                $levelid = $val['levelid'];
                $money = $val['money'];
                foreach($levelid as $k=>$v){
                    if($money[$k]){
                        $ldata[$levelid[$k]] = $money[$k];
                    }
                }
                if($ldata){
                    $xianxia_tj_data[$key]  = $ldata;
                }
            }
            $info['xianxia_coupon_vip_tj'] = json_encode($xianxia_tj_data);
            $xianxia_full = input('param.xianxia_full');
            $info['xianxia_full'] = json_encode($xianxia_full);
        }
        //薪资奖励
        if(getcustom('member_level_salary_bonus')){
            $salary = input('param.salary',[]);
            $salaryContent = [];
            if($salary){
                $member_num = $salary['member_num']??[];
                $yj_amount = $salary['yj_amount']??[];
                $bonus = $salary['bonus']??[];
                foreach ($member_num as $k=>$m_num){
                    if(empty($bonus[$k])){
                        continue;
                    }
                    $salaryContent[] = [
                        'member_num'=>$m_num,
                        'yj_amount'=>$yj_amount[$k]??0,
                        'bonus'=>$bonus[$k]
                    ];
                }
            }
            $info['salary_bonus_content'] = $salaryContent?json_encode($salaryContent):'';
        }
		//会员升级奖励不同会员等级的推荐佣金

       if(getcustom('member_levelup_parentcommission')){
            $levelup_parentcommission = input('param.levelup_parentcommission');
            $levelup_parentcommissions = [];
            foreach($levelup_parentcommission['levelid'] as $key=>$val){
                $levelup_parentcommissions[$val] = $levelup_parentcommission['money'][$key];
            }
            $info['levelup_parentcommission'] = json_encode($levelup_parentcommissions);
            $info['levelup_parent_jicha'] = $info['levelup_parent_jicha']??0;
        }
		//升级赠送周期优惠券
		if(getcustom('member_levelup_givecoupon')){
			$givecoupon_ids = input('post.coupon_id/a');
			$cycletype = input('post.cycletype/a');
			$cyclenum = input('post.cyclenum/a');
			$givecoupon_num = input('post.coupon_num/a');
			$givecoupondata = [];
			foreach($givecoupon_ids as $k=>$v){
				$givecoupondata[] = ['coupon_id'=>$v,'cycletype'=>$cycletype[$k],'cyclenum'=>$cyclenum[$k],'coupon_num'=>$givecoupon_num[$k]];
			}
			$info['givecoupondata'] = json_encode($givecoupondata);
		}
		if(getcustom('member_levelup_auth')) {
            //升级前置等级条件
            $info['saletj'] = implode(',', $info['saletj']);
        }
		if(getcustom('team_leader_fh')){
            $info['teamleader_fenhong_self'] = $info['teamleader_fenhong_self']??0;
        }
        if($info['up_pro_extend_time']==1 && $info['up_pro_keep_time']==1){
            return json(['status'=>0,'msg'=>'复购累加有效期和复购保持等级有效期不可同时开启']);
        }
        if(getcustom('teamfenhong_share')){
            if(isset($info['teamfenhong_share_pid_bl']) && isset($info['teamfenhong_share_pid_origin_bl'])){
                if($info['teamfenhong_share_pid_bl'] + $info['teamfenhong_share_pid_origin_bl'] != 100)
                    return json(['status'=>0,'msg'=>'团队分红共享奖原上级和新上级比例之和不等于100，请修改']);
            }
        }
        if(getcustom('teamfenhong_pingji_single_bl')){
            $info['teamfenhong_pingji_single_bl'] = json_encode(input('teamfenhong_pingji_single_bl/a'));
            $info['teamfenhong_pingji_single_money'] = json_encode(input('teamfenhong_pingji_single_money/a'));
        }
        if(getcustom('fenhong_jiaquan_copies')){
            $info['fenhong_limit_stime'] = $info['fenhong_limit_stime']?strtotime($info['fenhong_limit_stime']):'';
            $info['fenhong_limit_etime'] = $info['fenhong_limit_etime']?strtotime($info['fenhong_limit_etime']):'';
            $info['fenhong_jiaquan_maxmoney'] = $info['fenhong_jiaquan_maxmoney']!==''?$info['fenhong_jiaquan_maxmoney']:-1;
        }
        if(getcustom('network_slide')){
            $info['slide_down_team'] = $info['slide_down_team']?:0;
        }
        if(getcustom('business_agent_jicha_pj')){
            $info['business_jicha_status'] = $info['business_jicha_status']?:0;
        }
        if(getcustom('teamfenhong_money_product')){
            $info['teamfenhong_money_product'] = $info['teamfenhong_money_product']?:0;
        }
        if(getcustom('member_up_binding_tel')){
            $info['up_binding_tel'] = $info['up_binding_tel']?:0;
        }
        if(getcustom('commission_bole')){
            $info['commission_bole_origin'] = $info['commission_bole_origin']?:0;
        }

        // 去重级别
        $is_sort_exit = Db::name('member_level')->where('aid',aid)->where('sort',$info['sort'])->where('id','<>',$info['id'])->value('id');
        if($is_sort_exit){
            return json(['status'=>0,'msg'=>'会员等级排序存在重复值，请重新检查排序字段']);
        }

		if($info['id']){
			Db::name('member_level')->where('aid',aid)->where('id',$info['id'])->update($info);
			\app\commons\System::plog('编辑会员等级'.$info['id']);
		}else{
			$info['aid'] = aid;
			$info['createtime'] = time();
			$id = Db::name('member_level')->insertGetId($info);
			\app\commons\System::plog('添加会员等级'.$id);
		}
        // 重新设置默认值
        Db::name('member_level')->where('aid',aid)->where('isdefault',1)->update(['isdefault'=>0]);
        Db::name('member_level')->where('aid',aid)->limit(1)->order('sort asc')->update(['isdefault'=>1]);

		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	//删除
	public function del(){
		$ids = input('post.ids/a');
		Db::name('member_level')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('删除会员等级'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	//锁定
	public function dolock(){
		if(session('IS_ADMIN') == 0) return json(['status'=>1,'msg'=>'无权限操作']);
		$id = input('post.id/d');
		$st = input('post.st/d');
		Db::name('member_level')->where('aid',aid)->where('id',$id)->update(['islock'=>$st]);
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//申请记录
	public function applyorder(){
		$levelList = Db::name('member_level')->where('aid',aid)->select()->toArray();
		$levelArr = array();
		foreach($levelList as $v){
			$levelArr[$v['id']] = [
				'name'=>$v['name'],
				'areafenhong'=>$v['areafenhong'],
				'field_list'=>json_decode($v['field_list'],true),
				'is_agree'=>$v['is_agree']??0,
			];
		}
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'vo.createtime desc,id desc';
			}
			$where = [];
			$where[] = ['vo.aid','=',aid];
            if(input('param.mid')) $where[] = ['m.id','=',input('param.mid')];
			if(input('param.pid')) $where[] = ['m.pid','=',input('param.pid')];
			if(input('param.nickname')) $where[] = ['m.nickname','like','%'.input('param.nickname').'%'];
			if(input('param.realname')) $where[] = ['m.realname','like','%'.input('param.realname').'%'];
			if(input('param.formx')) $where[] = ['vo.form0|vo.form1|vo.form2|vo.form3|vo.form4|vo.form5|vo.form6|vo.form7|vo.form8|vo.form9|vo.form10|vo.form11|vo.form12|vo.form13|vo.form14|vo.form15|vo.form16|vo.form17|vo.form18|vo.form19|vo.form20|vo.areafenhong_province|vo.areafenhong_city|vo.areafenhong_area','like','%'.input('param.formx').'%'];
			if(input('param.ctime')){
				$ctime = explode(' ~ ',$_GET['ctime']);
				$where[] = ['vo.createtime','>=',strtotime($ctime[0])];
				$where[] = ['vo.createtime','<',strtotime($ctime[1]) + 86400];
			}
			if(input('?param.type') && input('param.type')!='all'){
			    $where[] = ['vo.type','=',input('param.type')];
            }
			$count = 0 + Db::name('member_levelup_order')->alias('vo')->join('member m','vo.mid = m.id')->where($where)->count();
			$data = Db::name('member_levelup_order')->alias('vo')->join('member m','vo.mid = m.id')->field('vo.*,m.nickname,m.realname')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			foreach($data as $k=>$v){
				$member = Db::name('member')->where('id',$v['mid'])->find();
				if(!$member){
					$data[$k]['nickname'] = '未找到该'.t('会员');
					$data[$k]['headimg'] = '';
				}else{
					$data[$k]['nickname'] = $member['nickname'];
					$data[$k]['headimg'] = $member['headimg'];
				}
				if($v['pid']) {
                    $parent = Db::name('member')->where('id', $v['pid'])->find();
                }elseif($member['pid']){
                    $parent = Db::name('member')->where('id',$member['pid'])->find();
                }else{
                    $parent = [];
                }
				if(!$parent){
					$data[$k]['pnickname'] = '';
					$data[$k]['pheadimg'] = '';
				}else{
					$data[$k]['pid'] = $parent['id'];
					$data[$k]['pnickname'] = $parent['nickname'];
					$data[$k]['pheadimg'] = $parent['headimg'];
				}
				$data[$k]['levelname'] = $v['beforelevelid'] ? $levelArr[$v['beforelevelid']]['name'] : $levelArr[$member['levelid']]['name'];
				$data[$k]['applylevelname'] = $levelArr[$v['levelid']]['name'];
				$formdata = [];
				for($i=0;$i<=20;$i++){
					if($v['form'.$i]){
						$formArr = explode('^_^',$v['form'.$i]);
						if($formArr[2] == 'upload'){
							$formArr[1] = '<img src="'.$formArr[1].'" style="width:60px;height:auto" onclick="preview(this)">';
						}
						$formdata[] = $formArr[0] . '：'.$formArr[1];
					}
				}
				if($levelArr[$v['levelid']]['areafenhong'] ==1){
					$formdata[] = '代理区域：'.$v['areafenhong_province'];
				}
				if($levelArr[$v['levelid']]['areafenhong'] ==2){
					$formdata[] = '代理区域：'.$v['areafenhong_province'].','.$v['areafenhong_city'];
				}
				if($levelArr[$v['levelid']]['areafenhong'] ==3){
					$formdata[] = '代理区域：'.$v['areafenhong_province'].','.$v['areafenhong_city'].','.$v['areafenhong_area'];
				}
				if($levelArr[$v['levelid']]['areafenhong'] ==10){
					$formdata[] = '代理区域：'.$v['areafenhong_largearea'];
				}
				$data[$k]['formdata'] = implode('<br>',$formdata);
				if(getcustom('up_level_agree2') && $levelArr[$v['levelid']]['is_agree'] == 1){
					$level_agree = Db::name('member_level_agree')->where('aid',aid)->where('mid',$v['mid'])->where('newlv_id',$v['levelid'])->find();
					if($level_agree && $level_agree['status']==1){
						$data[$k]['isagree'] = 1;
						$data[$k]['signatureurl'] = $level_agree['signatureurl'];
					}
				}
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		View::assign('levelArr',$levelArr);
		return View::fetch();
	}
	//申请审核
	public function applyshenhe(){
		$id = input('post.id/d');
		$st = input('post.st/d');
		$order = Db::name('member_levelup_order')->where('aid',aid)->where('id',$id)->find();
		Db::name('member_levelup_order')->where('aid',aid)->where('id',$id)->update(['status'=>$st]);
		if($st ==2){
            //通过
            Db::name('member_levelup_order')->where('aid',aid)->where('id',$id)->update(['levelup_time'=>time()]);
			$newlv = Db::name('member_level')->where('aid',aid)->where('id',$order['levelid'])->find();
			if($newlv['yxqdate'] > 0){
				$levelendtime = strtotime(date('Y-m-d')) + 86400 + 86400 * $newlv['yxqdate'];
			}else{
				$levelendtime = 0;
			}
            //判断是否默认分组
			if($newlv['cid'] > 0)
            $is_default = Db::name('member_level_category')->where('id', $newlv['cid'])->value('isdefault');
            if($is_default || $newlv['cid'] == 0) {
                Db::name('member')->where('id',$order['mid'])->update(['levelid'=>$newlv['id'],'levelendtime'=>$levelendtime,'levelstarttime'=>time()]);
                if($order['status']==1 && $newlv['apply_payfenxiao'] == 1 && $order['totalprice'] > 0){ //升级费用参与分销及分红
                    \app\commons\Common::applypayfenxiao(aid,$order['id']);
                }

                //更新代理区域
                if($newlv['areafenhong']==1){
                    Db::name('member')->where('aid',aid)->where('id',$order['mid'])->update(['areafenhong_province'=>$order['areafenhong_province']]);
                }elseif($newlv['areafenhong']==2){
                    Db::name('member')->where('aid',aid)->where('id',$order['mid'])->update(['areafenhong_province'=>$order['areafenhong_province'],'areafenhong_city'=>$order['areafenhong_city']]);
                }elseif($newlv['areafenhong']==3){
                    Db::name('member')->where('aid',aid)->where('id',$order['mid'])->update(['areafenhong_province'=>$order['areafenhong_province'],'areafenhong_city'=>$order['areafenhong_city'],'areafenhong_area'=>$order['areafenhong_area']]);
                }elseif($newlv['areafenhong']==10){
                    Db::name('member')->where('aid',aid)->where('id',$order['mid'])->update(['areafenhong_largearea'=>$order['areafenhong_largearea']]);
                }
            } else {
                if(getcustom('plug_sanyang')) {
                    $count = Db::name('member_level_record')->where('aid', aid)->where('mid', $order['mid'])->where('cid', $newlv['cid'])->count();
                    if($count) Db::name('member_level_record')->where('aid',aid)->where('mid',$order['mid'])->where('cid', $newlv['cid'])->update(['levelid'=>$newlv['id'],'levelendtime'=>$levelendtime]);
                    else {
                        $record_data = ['levelid' => $newlv['id'], 'levelendtime' => $levelendtime];
                        $record_data['aid'] = aid;
                        $record_data['mid'] = $order['mid'];
                        $record_data['createtime'] = time();
                        $record_data['cid'] = $newlv['cid'];
                        Db::name('member_level_record')->insertGetId($record_data);
                    }
                    Db::name('member_level_record')->where('aid',aid)->where('mid',$order['mid'])->where('cid', $newlv['cid'])->update(['levelstarttime'=>time()]);
                    if($order['status']==1 && $newlv['apply_payfenxiao'] == 1 && $order['totalprice'] > 0){ //升级费用参与分销及分红
                        \app\commons\Common::applypayfenxiao(aid,$order['id']);
                    }

                    //更新代理区域
                    if($newlv['areafenhong']==1){
                        Db::name('member_level_record')->where('aid',aid)->where('mid',$order['mid'])->where('cid', $newlv['cid'])->update(['areafenhong_province'=>$order['areafenhong_province']]);
                    }elseif($newlv['areafenhong']==2){
                        Db::name('member_level_record')->where('aid',aid)->where('mid',$order['mid'])->where('cid', $newlv['cid'])->update(['areafenhong_province'=>$order['areafenhong_province'],'areafenhong_city'=>$order['areafenhong_city']]);
                    }elseif($newlv['areafenhong']==3){
                        Db::name('member_level_record')->where('aid',aid)->where('mid',$order['mid'])->where('cid', $newlv['cid'])->update(['areafenhong_province'=>$order['areafenhong_province'],'areafenhong_city'=>$order['areafenhong_city'],'areafenhong_area'=>$order['areafenhong_area']]);
                    }
                }
            }

            \app\commons\Wechat::updatemembercard(aid,$order['mid']);

            //赠送积分
            if($newlv['up_give_score'] > 0) {
                \app\commons\Member::addscore(aid,$order['mid'],$newlv['up_give_score'],'升级奖励');
            }

            //奖励佣金
            if($newlv['up_give_commission'] > 0) {
                \app\commons\Member::addcommission(aid,$order['mid'],0,$newlv['up_give_commission'],'升级奖励');
            }

            //奖励余额
            if($newlv['up_give_money'] > 0) {
                \app\commons\Member::addmoney(aid,$order['mid'],$newlv['up_give_money'],'升级奖励');
            }

            //赠送上级佣金
            if ($newlv['up_give_parent_money'] > 0) {
                $pid = Db::name('member')->where('aid', aid)->where('id', $order['mid'])->value('pid');
                if($pid > 0) \app\commons\Member::addcommission(aid, $pid, $order['mid'], $newlv['up_give_parent_money'], '直推奖');
            }

			//升级赠送优惠券
            if(getcustom('up_give_coupon')){
                //商城优惠券赠送
                $shop_coupon = $newlv['up_give_coupon']?json_decode($newlv['up_give_coupon'],true):[];
                foreach($shop_coupon as $k=>$v){
                    if($v['num']<1){
                        continue;
                    }
                    for($i=0;$i<$v['num'];$i++){
                        \app\commons\Coupon::send(aid,$order['mid'],$v['id'],true);
                    }
                }
                //餐饮优惠券赠送
                $restaurant_coupon = $newlv['up_give_restaurant_coupon']?json_decode($newlv['up_give_restaurant_coupon'],true):[];
                foreach($restaurant_coupon as $k=>$v){
                    if($v['num']<1){
                        continue;
                    }
                    for($i=0;$i<$v['num'];$i++){
                        \app\commons\Coupon::send(aid,$order['mid'],$v['id'],true);
                    }
                }
            }
            //升级送修改下级升级数量
            if(getcustom('member_levelup_givechild')) {
                if($newlv['team_levelup'] == 1){
                    \app\commons\Member::addMemberLevelupNum(aid,$order['mid'],$newlv['team_levelup_data']);
                }
            }

            if(getcustom('school_product')) {
                \app\models\School::updateMemberClass(aid, $order['mid'], $order['id'], $order['school_id'], $order['grade_id'], $order['class_id'], $order['levelid']);
            }
            $memberUpdateStatus = false;
            if(getcustom('up_fxorder_condition_new')){
                //升级
                $memberUpdateStatus = true;
                \app\commons\Member::uplv(aid,$order['mid']);
            }
            if(getcustom('ganer_fenxiao')){
                //甘尔定制分销，升级发放奖励
                $memberUpdateStatus = true;
                \app\commons\Member::uplv(aid,$order['mid']);
                \app\commons\Fenxiao::tuiguang_bonus($order['id']);
            }
            //父级升级
            if($order['pid'] > 0 && !$memberUpdateStatus) \app\commons\Member::uplv(aid,$order['pid']);
            if(getcustom('network_slide')){
                $member = Db::name('member')->where('id',$order['mid'])->find();
                //公排网滑落
                $res = \app\commons\Member::net_slide($member['pid'],$member['id'],$newlv['id']);
                //dump($res);
            }
			$tmplcontent = [];
			$tmplcontent['first'] = '恭喜您成功升级为'.$newlv['name'];
			$tmplcontent['remark'] = '点击进入查看~';
			$tmplcontent['keyword1'] = $newlv['name']; //会员等级
			$tmplcontent['keyword2'] = '已生效';//审核状态
			\app\commons\Wechat::sendtmpl(aid,$order['mid'],'tmpl_uplv',$tmplcontent,m_url('pages/my/usercenter'));
		}
		\app\commons\System::plog('审核会员升级申请记录'.$id);
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	
	//审核删除
	public function applydel(){
		$ids = input('post.ids/a');
		Db::name('member_levelup_order')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('删除会员升级申请记录'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
    public function chooselevel(){
        if(request()->isPost()){
            $id = input('param.id/d');
            $info = Db::name('member_level')->where('aid',aid)->where('id',$id)->find();
            return json(['status'=>1,'data'=>$info]);
        }elseif(request()->isAjax()){
            $page = input('param.page',1);
            $limit = input('param.limit',10);
            if(input('param.field') && input('param.order')){
                $order = input('param.field').' '.input('param.order');
            }else{
                $order = 'sort,id';
            }
            $where = [['aid','=',aid]];
            $count = 0 + Db::name('member_level')->where($where)->count();
            $data = Db::name('member_level')->field('id,cid,name,aid,sort,can_agent,commission1,commission2,commission3,commissiontype')->where($where)->page($page,$limit)->order($order)->select()->toArray();
            foreach($data as $k=>$v){
                if($v['can_agent']==1){
                    $data[$k]['commission'] = $v['commission1'].($v['commissiontype']==1?'元':'%');
                }elseif($v['can_agent']==2){
                    $data[$k]['commission'] = $v['commission1'].($v['commissiontype']==1?'元':'%').' / '.$v['commission2'].($v['commissiontype']==1?'元':'%');
                }elseif($v['can_agent']==3){
                    $data[$k]['commission'] = $v['commission1'].($v['commissiontype']==1?'元':'%').' / '.$v['commission2'].($v['commissiontype']==1?'元':'%').' / '.$v['commission3'].($v['commissiontype']==1?'元':'%');
                }elseif($v['can_agent']==99){
                    $data[$k]['commission'] = '递归分销';
                }else{
                    $data[$k]['commission'] = '无分销权限';
                }
                $cat = Db::name('member_level_category')->where('aid', aid)->where('id',$v['cid'])->find();
                $data[$k]['cat_name'] = $cat['name'];
                $data[$k]['defaultCat'] = $cat['isdefault'];
            }
            return json(['status'=>1,'code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
        }
        return View::fetch();
    }
    public function changePidLog(){
        if(getcustom('up_giveparent')){
            $levelList = Db::name('member_level')->where('aid',aid)->select()->toArray();
            $levelArr = array();
            foreach($levelList as $v){
                $levelArr[$v['id']] = [
                    'name'=>$v['name'],
                    'areafenhong'=>$v['areafenhong'],
                    'field_list'=>json_decode($v['field_list'],true),
                    'is_agree'=>$v['is_agree']??0,
                ];
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
                $where[] = ['vo.aid','=',aid];
                if(input('param.mid')) $where[] = ['m.id','=',input('param.mid')];
                if(input('param.pid')) $where[] = ['vo.pid','=',input('param.pid')];
                if(input('param.pid_origin')) $where[] = ['vo.pid_origin','=',input('param.pid_origin')];
                if(input('param.nickname')) $where[] = ['m.nickname','like','%'.input('param.nickname').'%'];
                if(input('param.realname')) $where[] = ['m.realname','like','%'.input('param.realname').'%'];
                if(input('param.ctime')){
                    $ctime = explode(' ~ ',$_GET['ctime']);
                    $where[] = ['vo.createtime','>=',strtotime($ctime[0])];
                    $where[] = ['vo.createtime','<',strtotime($ctime[1]) + 86400];
                }
                $count = 0 + Db::name('member_pid_changelog')->alias('vo')->join('member m','vo.mid = m.id')->where($where)->count();
                $data = Db::name('member_pid_changelog')->alias('vo')->join('member m','vo.mid = m.id')->field('vo.*,m.nickname,m.realname')->where($where)->page($page,$limit)->order($order)->select()->toArray();
                foreach($data as $k=>$v){
                    $member = Db::name('member')->where('id',$v['mid'])->find();
                    if(!$member){
                        $data[$k]['nickname'] = '未找到该'.t('会员');
                        $data[$k]['headimg'] = '';
                    }else{
                        $data[$k]['nickname'] = $member['nickname'];
                        $data[$k]['headimg'] = $member['headimg'];
                    }
                    if($v['pid']) {
                        $parent = Db::name('member')->where('id', $v['pid'])->find();
                    }else{
                        $parent = [];
                    }
                    if($v['pid_origin']){
                        $parentOrigin = Db::name('member')->where('id',$v['pid_origin'])->find();
                    }else{
                        $parentOrigin = [];
                    }
                    if(!$parent){
                        $data[$k]['pnickname'] = '';
                        $data[$k]['pheadimg'] = '';
                    }else{
                        $data[$k]['pid'] = $parent['id'];
                        $data[$k]['pnickname'] = $parent['nickname'];
                        $data[$k]['pheadimg'] = $parent['headimg'];
                    }
                    if(!$parentOrigin){
                        $data[$k]['ponickname'] = '';
                        $data[$k]['poheadimg'] = '';
                    }else{
                        $data[$k]['pid_origin'] = $parentOrigin['id'];
                        $data[$k]['ponickname'] = $parentOrigin['nickname'];
                        $data[$k]['poheadimg'] = $parentOrigin['headimg'];
                    }
                }
                return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
            }
            View::assign('levelArr',$levelArr);
            return View::fetch();
        }
    }

    public function bgset(){
        if(getcustom('levelup_code')){
            if(request()->isPost()){
                $info = input('post.info/a');
                $id = $info['id']?$info['id']:0;
                unset($info['id']);

                $count = Db::name('member_level_bgset')->where('id',$id)->where('aid',aid)->count();
                if(!$count){
                    return json(['status'=>0,'msg'=>'设置数据错误，请重新进入页面']);
                }
                
                $up = Db::name('member_level_bgset')->where('id',$id)->update($info);
                if($up){
                    \app\commons\System::plog('编辑'.t('会员').'等级背景设置'.$id);
                    return json(['status'=>1,'msg'=>'操作成功']);
                }else{
                    return json(['status'=>0,'msg'=>'操作失败']);
                }
            }else{
                $info = Db::name('member_level_bgset')->where('aid',aid)->find();
                if(!$info){
                    $info = [];
                    $info['aid'] = aid;
                    $info['create_time'] = time();
                    $info['id'] = Db::name('member_level_bgset')->insertGetId($info);
                }
                View::assign('info',$info);
                return View::fetch();
            }
        }
    }

    public function applyexcel(){
        $levelList = Db::name('member_level')->where('aid',aid)->select()->toArray();
        $levelArr = array();
        foreach($levelList as $v){
            $levelArr[$v['id']] = [
                'name'=>$v['name'],
                'areafenhong'=>$v['areafenhong'],
                'field_list'=>json_decode($v['field_list'],true),
            ];
        }
        if(input('param.field') && input('param.order')){
            $order = input('param.field').' '.input('param.order');
        }else{
            $order = 'id desc';
        }
        $page = input('param.page')?:1;
        $limit = input('param.limit')?:10;
        $where = [];
        $where[] = ['vo.aid','=',aid];
        if(input('param.mid')) $where[] = ['m.id','=',input('param.mid')];
        if(input('param.pid')) $where[] = ['m.pid','=',input('param.pid')];
        if(input('param.nickname')) $where[] = ['m.nickname','like','%'.input('param.nickname').'%'];
        if(input('param.realname')) $where[] = ['m.realname','like','%'.input('param.realname').'%'];
        if(input('param.formx')) $where[] = ['vo.form0|vo.form1|vo.form2|vo.form3|vo.form4|vo.form5|vo.form6|vo.form7|vo.form8|vo.form9|vo.form10|vo.form11|vo.form12|vo.form13|vo.form14|vo.form15|vo.form16|vo.form17|vo.form18|vo.form19|vo.form20|vo.areafenhong_province|vo.areafenhong_city|vo.areafenhong_area','like','%'.input('param.formx').'%'];
        if(input('param.ctime')){
            $ctime = explode(' ~ ',$_GET['ctime']);
            $where[] = ['vo.createtime','>=',strtotime($ctime[0])];
            $where[] = ['vo.createtime','<',strtotime($ctime[1]) + 86400];
        }
        if(input('?param.type') && input('param.type')!='all'){
            $where[] = ['vo.type','=',input('param.type')];
        }
        $data = Db::name('member_levelup_order')->alias('vo')->join('member m','vo.mid = m.id')
            ->field('vo.*,m.nickname,m.realname')
            ->where($where)->order($order)
            ->page($page,$limit)
            ->select()->toArray();
        $count = Db::name('member_levelup_order')->alias('vo')->join('member m','vo.mid = m.id')
            ->field('vo.*,m.nickname,m.realname')
            ->where($where)
            ->count();
        $statusArr = [
            0=>'未支付',
            1=>'待审核',
            2=>'已通过',
            3=>'已驳回',
        ];
        foreach($data as $k=>$v){
            $member = Db::name('member')->where('id',$v['mid'])->find();
            if(!$member){
                $data[$k]['nickname'] = '未找到该'.t('会员');
                $data[$k]['headimg'] = '';
            }else{
                $data[$k]['nickname'] = $member['nickname'];
                $data[$k]['headimg'] = $member['headimg'];
            }
            if($member['pid']){
                $parent = Db::name('member')->where('id',$member['pid'])->find();
            }else{
                $parent = [];
            }
            if(!$parent){
                $data[$k]['pnickname'] = '';
                $data[$k]['pheadimg'] = '';
            }else{
                $data[$k]['pid'] = $parent['id'];
                $data[$k]['pnickname'] = $parent['nickname'];
                $data[$k]['pheadimg'] = $parent['headimg'];
            }
            $data[$k]['levelname'] = $v['beforelevelid'] ? $levelArr[$v['beforelevelid']]['name'] : $levelArr[$member['levelid']]['name'];
            $data[$k]['applylevelname'] = $levelArr[$v['levelid']]['name'];
            $formdata = [];
            for($i=0;$i<=20;$i++){
                if($v['form'.$i]){
                    $formArr = explode('^_^',$v['form'.$i]);
                    if($formArr[2] == 'upload'){
                        $formArr[1] = '<img src="'.$formArr[1].'" style="width:60px;height:auto" onclick="preview(this)">';
                    }
                    $formdata[] = $formArr[0] . '：'.$formArr[1];
                }
            }
            if($levelArr[$v['levelid']]['areafenhong'] ==1){
                $formdata[] = '代理区域：'.$v['areafenhong_province'];
            }
            if($levelArr[$v['levelid']]['areafenhong'] ==2){
                $formdata[] = '代理区域：'.$v['areafenhong_province'].','.$v['areafenhong_city'];
            }
            if($levelArr[$v['levelid']]['areafenhong'] ==3){
                $formdata[] = '代理区域：'.$v['areafenhong_province'].','.$v['areafenhong_city'].','.$v['areafenhong_area'];
            }
            if($levelArr[$v['levelid']]['areafenhong'] ==10){
                $formdata[] = '代理区域：'.$v['areafenhong_largearea'];
            }
            $data[$k]['formdata'] = implode('\n\tab',$formdata);
            $data[$k]['status_txt'] = $statusArr[$v['status']]??'';
        }

        $title = array();
        $title[] = 'ID';
        $title[] = '昵称ID';
        $title[] = '推荐人';
        $title[] = '当前等级';
        $title[] = '申请等级';
        $title[] = '申请资料';
        $title[] = '金额';
        $title[] = '状态';
        $title[] = '申请时间';
        $dataXlx = array();
        foreach($data as $v){
            $tdata = array();
            $tdata[] = $v['id'];
            $tdata[] = $v['nickname'].'('.$v['mid'].')';
            $tdata[] = $v['pnickname'].'('.$v['pid'].')';
            $tdata[] = $v['levelname'];
            $tdata[] = $v['applylevelname'];
            $tdata[] = $v['formdata'];
            $tdata[] = $v['totalprice'];
            $tdata[] = $v['status_txt'];
            $tdata[] = date('Y-m-d H:i:s',$v['createtime']);
            $dataXlx[] = $tdata;
        }
        return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$dataXlx,'title'=>$title]);
        $this->export_excel($title,$dataXlx);
    }
	
	//转赠订单列表
    public function saleorder(){
		if(getcustom('member_levelup_auth')){
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
				if(input('param.mid')) $where[] = ['mid','=',input('param.mid')];
				if(input('param.ctime') ){
					$ctime = explode(' ~ ',input('param.ctime'));
					$where[] = ['createtime','>=',strtotime($ctime[0])];
					$where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
				}
				$count = 0 + Db::name('member_salelevel_order')->where($where)->count();
				//echo M()->_sql();
				$list = Db::name('member_salelevel_order')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			   
				foreach($list as $k=>$vo){
					$member = Db::name('member')->where('id',$vo['mid'])->find();
					$list[$k]['nickname'] = $member['nickname'];
					$list[$k]['headimg'] = $member['headimg'];
					$frommember = Db::name('member')->where('id',$vo['from_mid'])->find();
					$list[$k]['fromnickname'] = $frommember['nickname'];
					$list[$k]['fromheadimg'] = $frommember['headimg'];
					$level =  Db::name('member_level')->field('name')->where('id',$vo['levelid'])->find();
					$list[$k]['levelname'] = $level['name'];
				}
				return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$list]);
			}
			return View::fetch();
		}
    }
		
	//转赠订单删除
	public function saleorderdel(){
		if(getcustom('member_levelup_auth')){
			$ids = input('post.ids/a');
			Db::name('member_salelevel_order')->where('aid',aid)->where('id','in',$ids)->delete();
			\app\commons\System::plog('删除转赠/售卖记录'.implode(',',$ids));
			return json(['status'=>1,'msg'=>'删除成功']);
		}
	}

	//降级记录
    public function down_record(){
        if(request()->isAjax()){
            $page = input('param.page');
            $limit = input('param.limit');
            if(input('param.field') && input('param.order')){
                $order = input('param.field').' '.input('param.order');
            }else{
                $order = 'd.id';
            }
            $where = [['d.aid','=',aid]];
            if(input('param.mid')) $where[] = ['m.id','=',input('param.mid')];
            if(input('param.pid')) $where[] = ['m.pid','=',input('param.pid')];
            if(input('param.nickname')) $where[] = ['m.nickname','like','%'.input('param.nickname').'%'];
            if(input('param.realname')) $where[] = ['m.realname','like','%'.input('param.realname').'%'];
            if(input('param.ctime')){
                $ctime = explode(' ~ ',$_GET['ctime']);
                $where[] = ['d.createtime','>=',strtotime($ctime[0])];
                $where[] = ['d.createtime','<',strtotime($ctime[1]) + 86400];
            }

            $count = 0 + Db::name('member_leveldown_record')->alias('d')->join('member m','d.mid=m.id')->where($where)->count();
            $data = Db::name('member_leveldown_record')->alias('d')
                ->join('member m','d.mid=m.id')
                ->field('d.*,m.nickname')
                ->where($where)->page($page,$limit)->order($order)->select()->toArray();
            $level_arr = Db::name('member_level')->where('aid',aid)->column('*','id');
            foreach($data as $k=>$v){
                $data[$k]['levelname'] = $level_arr[$v['levelid']]['name']??'';
                $data[$k]['before_levelname'] = $level_arr[$v['beforelevelid']]['name']??'';

                $member = Db::name('member')->where('id',$v['mid'])->find();
                if(!$member){
                    $data[$k]['nickname'] = '未找到该'.t('会员');
                    $data[$k]['headimg'] = '';
                }else{
                    $data[$k]['nickname'] = $member['nickname'];
                    $data[$k]['headimg'] = $member['headimg'];
                }
                if($v['pid']) {
                    $parent = Db::name('member')->where('id', $v['pid'])->find();
                }elseif($member['pid']){
                    $parent = Db::name('member')->where('id',$member['pid'])->find();
                }else{
                    $parent = [];
                }
                if(!$parent){
                    $data[$k]['pnickname'] = '';
                    $data[$k]['pheadimg'] = '';
                }else{
                    $data[$k]['pid'] = $parent['id'];
                    $data[$k]['pnickname'] = $parent['nickname'];
                    $data[$k]['pheadimg'] = $parent['headimg'];
                }
            }
            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
        }

        return View::fetch();
    }

    public function excel_down_record(){
        if(input('param.field') && input('param.order')){
            $order = input('param.field').' '.input('param.order');
        }else{
            $order = 'd.id';
        }
        $where = [['d.aid','=',aid]];
        if(input('param.mid')) $where[] = ['m.id','=',input('param.mid')];
        if(input('param.pid')) $where[] = ['m.pid','=',input('param.pid')];
        if(input('param.nickname')) $where[] = ['m.nickname','like','%'.input('param.nickname').'%'];
        if(input('param.realname')) $where[] = ['m.realname','like','%'.input('param.realname').'%'];
        if(input('param.ctime')){
            $ctime = explode(' ~ ',$_GET['ctime']);
            $where[] = ['vo.createtime','>=',strtotime($ctime[0])];
            $where[] = ['vo.createtime','<',strtotime($ctime[1]) + 86400];
        }

        $data = Db::name('member_leveldown_record')->alias('d')
            ->join('member m','d.mid=m.id')
            ->field('d.*,m.nickname')
            ->where($where)->order($order)
            ->page($page,$limit)
            ->select()->toArray();
        $count = Db::name('member_leveldown_record')->alias('d')
            ->join('member m','d.mid=m.id')
            ->field('d.*,m.nickname')
            ->where($where)->order($order)
            ->count();
        $level_arr = Db::name('member_level')->where('aid',aid)->column('*','id');
        foreach($data as $k=>$v){
            $data[$k]['levelname'] = $level_arr[$v['levelid']]['name']??'';
            $data[$k]['before_levelname'] = $level_arr[$v['beforelevelid']]['name']??'';

            $member = Db::name('member')->where('id',$v['mid'])->find();
            if(!$member){
                $data[$k]['nickname'] = '未找到该'.t('会员');
                $data[$k]['headimg'] = '';
            }else{
                $data[$k]['nickname'] = $member['nickname'];
                $data[$k]['headimg'] = $member['headimg'];
            }
            if($v['pid']) {
                $parent = Db::name('member')->where('id', $v['pid'])->find();
            }elseif($member['pid']){
                $parent = Db::name('member')->where('id',$member['pid'])->find();
            }else{
                $parent = [];
            }
            if(!$parent){
                $data[$k]['pnickname'] = '';
                $data[$k]['pheadimg'] = '';
            }else{
                $data[$k]['pid'] = $parent['id'];
                $data[$k]['pnickname'] = $parent['nickname'];
                $data[$k]['pheadimg'] = $parent['headimg'];
            }
        }
        $title = array();
        $title[] = 'ID';
        $title[] = '昵称ID';
        $title[] = '推荐人';
        $title[] = '原等级';
        $title[] = '降级等级';
        $title[] = '备注';
        $title[] = '降级时间';
        $dataXlx = array();
        foreach($data as $v){
            $tdata = array();
            $tdata[] = $v['id'];
            $tdata[] = $v['nickname'].'('.$v['mid'].')';
            $tdata[] = $v['pnickname'].'('.$v['pid'].')';
            $tdata[] = $v['before_levelname'];
            $tdata[] = $v['levelname'];
            $tdata[] = $v['remark'];
            $tdata[] = date('Y-m-d H:i:s',$v['createtime']);
            $dataXlx[] = $tdata;
        }
        return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$dataXlx,'title'=>$title]);
        $this->export_excel($title,$dataXlx);
    }
}
