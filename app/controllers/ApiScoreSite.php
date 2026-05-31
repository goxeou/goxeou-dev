<?php


namespace app\controllers;
use think\facade\Db;
class ApiScoreSite extends ApiCommon{
    public function initialize(){
		parent::initialize();
	}
	public function rechargeToMember()
    {
        $mdid = input('param.mdid/d',0);
        $set = Db::name('admin_set')->where('aid',aid)->find();
       
        if(request()->isPost()){
            $mobile = input('post.mobile');
            $mdid = input('post.mdid/d');
            $money = input('post.money/f');
            if ($money < 0.01){
                return $this->json(['status'=>0,'msg'=>'请输入正确的金额，最小金额为：0.01']);
            }
            $member = Db::name('score_site')->where('aid', aid)->where('id', $mdid)->find();
           
            if(!$member) return $this->json(['status'=>0,'msg'=>'未找到该'.t('站点')]);
            $user_id = $member['id'];
            if ($member['mid'] == mid) {
               // return $this->json(['status'=>0,'msg'=>'不能支付给自己']);
            }
			$tomoney = $money;
            if ($money > $this->member['score']){
                return $this->json(['status'=>0,'msg'=>'您的'.t('积分').'不足']);
            }
            //验证支付密码
            $pwd_check = false;//$set['transfer_pwd'];
            if($pwd_check){
                if(!$this->member['paypwd']){
                    return $this->json(['status'=>0,'msg'=>'请先设置支付密码','set_paypwd'=>1]);
                }
                $pay_pwd = input('paypwd')?:'';
                if(!\app\commons\Member::checkPayPwd($this->member,$pay_pwd )){
                    return $this->json(['status'=>0,'msg'=>'支付密码输入错误']);
                }
            }
            $midMsg = sprintf("支付给：%s",$member['name']);
            $toMidMsg = sprintf("来自%s的支付", $this->member["nickname"]);
           
			$rs = \app\commons\Member::addscore(aid,mid,$money * -1, $midMsg);
            if ($rs['status'] == 1) {
                \app\commons\ScoreSite::addmoney(aid,$user_id,$tomoney,$toMidMsg,$this->mid);
            }else{
				 return $this->json(['status'=>0, 'msg' => '支付失败']);
			}
            return $this->json(['status'=>1, 'msg' => '支付成功', 'url'=>'/pages/my/usercenter']);
        }
        $tomember = [];
        if($mdid){
            $tomember = Db::name('score_site')->where('aid',aid)->where('id',$mdid)->field('id,money,name,logo')->find();
        }
        if (!$tomember) {
			 return $this->json(['status'=>0, 'msg' => '站点不存在']);
		}
        if($this->member['paypwd']=='') {
			$rdata['haspwd'] = 1;
		} else {
			$rdata['haspwd'] = 0;
		}
        $rdata['paycheck'] =false;// $set['transfer_pwd'] ? true : false;
        $rdata['status'] = 1;
        $rdata['mymoney'] = $this->member['score'];
        $rdata['moneyList'] = [];//可选金额列表
        $rdata['tomember'] = $tomember?$tomember:['name'=>''];//转给谁
   
        return $this->json($rdata);
  
    }
	//商家详情页
	public function index(){
	
		$bid =  input('param.id/d');
        $latitude = input('param.latitude','');
        $longitude = input('param.longitude','');
		$business = Db::name('score_site')->where('aid',aid)->where('id',$bid)->where('status',1)->find();
		if(!$business) return $this->json(['status'=>0,'msg'=>'商家信息不存在']);
        //如果是门店模式且用户选择了门店，则显示该商户该门店的数据，否则显示全部门店数据
        $mendian_id = input('param.mendian_id/d',0);
		$bset = Db::name('score_site_sysset')->where('aid',aid)->find();
		$addviewnum = 1;
		$business['viewnum'] = $business['viewnum'] +$addviewnum;
		Db::name('score_site')->where('id',$bid)->inc('viewnum',$addviewnum)->update();
        $business['turnover'] = 0;
        $rdata = [];
        $sysset = ['name'=>$business['name'],'logo'=>$business['logo'],'desc'=>$business['address'],'tel'=>$business['tel'],'mode'=>$admin_set['mode'],'address' => $business['address'],'sysname'=>$admin_set['name']];

        if(mid>0){
			//添加浏览历史
			$rs = Db::name('member_history')->where(array('aid'=>aid,'mid'=>mid,'proid'=>$bid,'type'=>'score_site'))->find();
			if($rs){
				Db::name('member_history')->where(array('id'=>$rs['id']))->update(['createtime'=>time()]);
			}else{
				Db::name('member_history')->insert(array('aid'=>aid,'mid'=>mid,'proid'=>$bid,'type'=>'score_site','createtime'=>time()));
			}
		}
		$rdata['isdiy'] = 0;
		$rdata['bset'] = $bset;
		$rdata['sysset'] = $sysset;
		$rdata['business'] = $business;
		$rdata['pics'] = $business['pics']?explode(',',$business['pics']):[PRE_URL.'/static/img/topbg.png'];
        if($select_bid) $rdata['needlocation'] = true;
		return $this->json($rdata);
	}


	public function blist(){
	    
	    $sysset = Db::name('admin_set')->where('aid',aid)->find();
		if(request()->isPost()){
			$pernum = 10;
			$pagenum = input('post.pagenum/d');
			if(!$pagenum) $pagenum = 1;
			$cid = input('post.cid/d');
			$where = [];
			if(input('post.ids')){
				$where[] = ['b.id','in',input('post.ids')];
			}

			if(getcustom('show_location')){
                // $area = input('post.area');
                // if($sysset['mode']==2){
                //     if($sysset['loc_range_type']==1 && $sysset['loc_range']>0){
                //         //自定义范围:显示范围，组件中的显示范围和系统的显示范围loc_range 取小
                //         $limit_distance = $sysset['loc_range'];
                //         $distance = $sysset['loc_range'];
                //     }else{
                //         //同城
                //         if($area){
                //             //取省或者市
                //             $areaArr = explode(',',$area);
                //             $areaCount = count($areaArr);
                //             if($areaCount==1){
                //                 $where[] = ['b.province','=',$areaArr[0]];
                //             }else{
                //                 $where[] = ['b.city','=',$areaArr[1]];
                //                 //区兼容
                //                 if($areaCount>2){
                //                     $district = $areaArr[2];
                //                     $where[] = ['b.district','=',$district];
                //                 }
                //             }
                //         }
                //     }
                // }
            }
	
			
			
			$where[] = ['b.aid','=',aid];
			$where[] = ['b.status','=',1];
	
			if($cid) $where[] = Db::raw('find_in_set('.$cid.',cid)'); // ['cid','=',$cid];
			if(input('param.keyword')){
				$where[] = ['b.name|b.desc','like','%'.input('param.keyword').'%'];
			}
			$nowhm = date('H:i');
// 			$where[] = Db::raw("(start_hours<end_hours and start_hours<='$nowhm' and end_hours>='$nowhm') or (start_hours>=end_hours and (start_hours<='$nowhm' or end_hours>='$nowhm')) or (start_hours2<end_hours2 and start_hours2<='$nowhm' and end_hours2>='$nowhm') or (start_hours2>end_hours2 and (start_hours2<='$nowhm' or end_hours2>='$nowhm')) or (start_hours3<end_hours3 and start_hours3<='$nowhm' and end_hours3>='$nowhm') or (start_hours3>end_hours3 and (start_hours3<='$nowhm' or end_hours3>='$nowhm'))");

			$latitude = input('param.latitude');
			$longitude = input('param.longitude');
			if($longitude && $latitude){
				$order = Db::raw("({$longitude}-longitude)*({$longitude}-longitude) + ({$latitude}-latitude)*({$latitude}-latitude) ");
			}else{
				$order = 'b.sort desc,b.id desc';
			}
			$field = input('param.field');
			if(getcustom('blist_showviewnum') && $field == 'sales'){
				$field = 'b.viewnum';
			}else if($field == 'sales'){
                $field = 's.total_sales';
            }
			if($field && $field!='juli'){
				$order = $field.' '.input('param.order').',b.id desc';
			}

			$bset = Db::name('score_site_sysset')->where('aid',aid)->find();
			$field = 'b.id,b.logo,b.name,b.rate_money,b.start_hours,b.end_hours,b.tel,b.address,b.latitude,b.longitude,b.comment_score,b.viewnum,b.tourl,s.total_sales sales';
		
			$datalist = Db::name('score_site')
                ->alias('b')
                ->join('business_sales s','b.id=s.bid','left')
                ->where($where)
                ->field($field)
                ->page($pagenum,$pernum)
                ->order($order)
                ->select()
                ->toArray();
			$nowtime = time();
			$nowhm = date('H:i');
			if(!$datalist) $datalist = array();
			if($datalist){
			
		        foreach($datalist as $k=>$v){
	                $turnover = 0;
	                if(isset($v['turnover_show'])){
	                    $turnover = \app\commons\Business::totalTurnover(aid, $v['id']);
	                }else{
	                    $v['turnover_show'] = 0;
	                }
	                $v['turnover'] = $turnover;
					$statuswhere = "`status`=1 or (`status`=2 and unix_timestamp(start_time)<=$nowtime and unix_timestamp(end_time)>=$nowtime) or (`status`=3 and ((start_hours<end_hours and start_hours<='$nowhm' and end_hours>='$nowhm') or (start_hours>=end_hours and (start_hours<='$nowhm' or end_hours>='$nowhm'))) )";
					if(getcustom('blist_showtype1')){
						$prolist = Db::name('shop_product')->where('bid',$v['id'])->where($statuswhere)->field('id,pic,name,sales,market_price,sell_price')->limit(8)->order('sales desc,sort desc,id desc')->select()->toArray();
					}else{
						$prolist = Db::name('shop_product')->where('bid',$v['id'])->where($statuswhere)->field('id,pic,name,sales,market_price,sell_price')->limit(4)->order('sort desc,id desc')->select()->toArray();
					}
					if(!$prolist) $prolist = array();
					$v['prolist'] = $prolist;
	                if(getcustom('restaurant')) {
	                    $restaurantProlist = Db::name('restaurant_product')->where('bid',$v['id'])->where($statuswhere)->field('id,pic,name,sales,market_price,sell_price')->limit(4)->order('sort desc,id desc')->select()->toArray();
	                    if(!$restaurantProlist) $restaurantProlist = array();
	                    $v['restaurantProlist'] = $restaurantProlist;
	                }
					if($longitude && $latitude){
						$v['juli'] = ''.getdistance($longitude,$latitude,$v['longitude'],$v['latitude'],2).'km';
					}else{
						$v['juli'] = '';
					}
					//商城销量
					$prosales = Db::name('shop_product')->where('bid',$v['id'])->sum('sales');
					//当 外卖或餐饮开启时，统计其销量 
	                $restaurant_sales = 0;
	                $restaurant_shop_status = Db::name('restaurant_shop_sysset')->where('bid',$v['id'])->value('status');
	                $restaurant_takeaway_status = Db::name('restaurant_takeaway_sysset')->where('bid',$v['id'])->value('status');
	                if($restaurant_shop_status || $restaurant_takeaway_status){
	                    $restaurant_sales =   Db::name('restaurant_product')->where('bid',$v['id'])->sum('sales');
	                }

					if(getcustom('blist_showviewnum')){
						$v['viewnum'] = $v['viewnum'] + $bset['viewnum_defaultnum'];
					}
	                
					// if($v['sales'] < ($prosales + $restaurant_sales)) $v['sales'] =$prosales+$restaurant_sales;
	                $sales = Db::name('business_sales')->where('bid',$v['id'])->value('total_sales');
	                $v['sales'] = $sales?:0;
	                if(!getcustom('business_reward_member')){
	                    $v['reward_member'] = 0;
	                    $v['reward_member_bili'] = 0;
	                }
                    if(getcustom('business_show_maidanscoredk')){
                    	//是否展示买单积分抵扣
                        if($show_maidanscoredk){
                            $v['maidanscoredk_text'] = '线下可抵扣'.floatval($scoredkmaxpercent).'%';
                            if(getcustom('business_maidan_scoredk')){
                                if($v['scoredkmaxset']==1){
                                    $v['maidanscoredk_text'] = '线下可抵扣'.floatval($v['scoredkmaxval']).'%';
                                }else if($v['scoredkmaxset']==-1){
                                    $v['maidanscoredk_text'] = '';
                                }
                            }
                        }
                    }
					$datalist[$k] = $v;
				}
			}
			ll($datalist,'$datalist');
			return $this->json(['status'=>1,'data'=>$datalist]);
		}
		//分类
		$clist = Db::name('score_site_category')->where('aid',aid)->where('status',1)->field('id,name,pic')->order('sort desc,id')->select()->toArray();
		
		$rdata = [];
		$rdata['clist'] = $clist;
		$rdata['showtype'] = 0;
	
		$rdata['showviewnum'] = false;
		if(getcustom('blist_showviewnum')){
			$rdata['showviewnum'] = true;
		}
		$show_style = 0;
		if(getcustom('business_nearby_list')){
            $show_style = 1;
        }
        $rdata['show_style'] = $show_style;
		return $this->json($rdata);
	}
  
	public function apply(){
		$this->checklogin();
        $field = 'xieyi_show,xieyi';
        $bset = Db::name('score_site_sysset')->where('aid',aid)->field($field)->find();
        if(request()->isPost()){
			$formdata = input('post.info/a');
			//print_r($formdata['customformdata']);die;
			
			$hasun = Db::name('admin_user')->where('id','<>',$formdata['id'])->where('un',$formdata['un'])->find();
			if(!$formdata['id'] && $hasun){
				return $this->json(['status'=>0,'msg'=>'该账号已存在']);
			}

            if(!checkTel($formdata['linktel'])){
                return $this->json(['status'=>0, 'msg'=>'请输入正确的联系人手机号']);
            }
            if(!checkTel($formdata['tel'], [1,2,3])){
                return $this->json(['status'=>0, 'msg'=>'请输入正确的客服电话']);
            }

			$info = [];
			$info['aid'] = aid;
			$info['mid'] = mid;
			$info['cid'] = $formdata['cid'];
			$info['name'] = $formdata['name'];
			$info['desc'] = $formdata['desc'];
			$info['linkman'] = $formdata['linkman'];
			$info['linktel'] = $formdata['linktel'];
			$info['tel'] = $formdata['tel'];
			$info['logo'] = $formdata['pic'];
			$info['pics'] = $formdata['pics'];
			$info['content'] = $formdata['content'];
			$info['address'] = $formdata['address'];
			$info['latitude'] = $formdata['latitude'];
			$info['longitude'] = $formdata['longitude'];
			$info['zhengming'] = $formdata['zhengming'];
			
			
			$info['start_hours'] = $formdata['start_hours'];
			$info['end_hours'] = $formdata['end_hours'];
			$info['rate_money'] = $formdata['rate_money'];
			$info['video'] = $formdata['video'];
			
			$info['status'] = 0;
			$info['createtime'] = time();
		
			if(!isset($info['feepercent'])){
				$info['feepercent'] = Db::name('score_site_sysset')->where('aid',aid)->value('default_rate');
			}
            //通过经纬度获取省市区
            if($info['latitude'] && $info['longitude'] && !$info['district']){
                //通过坐标获取省市区
                $mapqq = new \app\commons\MapQQ();
                $address_component = $mapqq->locationToAddress($info['latitude'],$info['longitude']);
                if($address_component && $address_component['status']==1){
                    $info['province'] = $address_component['province'];
                    $info['city'] = $address_component['city'];
                    $info['district'] = $address_component['district'];
                }
            }
			$uinfo = [];
			$uinfo['un'] = $formdata['un'];
			$uinfo['pwd'] = $formdata['pwd'];

			$hasyxqueuefree = 0;
			if(getcustom('yx_queue_free')){
				$hasyxqueuefree = 1;
			}
			//自定义表单end
			if($formdata['id']){
				Db::name('score_site')->where('aid',aid)->where('mid',mid)->where('id',$formdata['id'])->update($info);
			}else{
				$bid = Db::name('score_site')->insertGetId($info);
			
			}
			return $this->json(['status'=>1,'msg'=>'提交成功，请等待审核']);
		}

		
		$info = Db::name('score_site')->where('aid',aid)->where('mid',mid)->find();
		if($info && $info['status']==1){
			return $this->json(['status'=>2,'msg'=>'您已成功入驻']); 
		}
        if($info && $info['logo']){
            $info['pic'] = $info['logo'];
        }
		$clist = Db::name('score_site_category')->where('aid',aid)->where('status',1)->order('sort desc,id')->select()->toArray();
        $nearby = 0;
        if(getcustom('business_nearby_list')){
            $nearby = 1;
        }
        $bset['nearby'] = $nearby;
        $active_coin = 0;
        if(getcustom('active_coin')){
            $active_coin = 1;
        }
		//排队免单返利
		$queue_free_set = [];
		$queue_free_set['rate_back']= '';
		$queue_free_set['show_rate_back']= 0;
		$hasyxqueuefree = 0;
		if(getcustom('yx_queue_free')){
			$hasyxqueuefree = 1;
		}
		if($hasyxqueuefree == 1){
			$queue_free_set_all = Db::name('queue_free_set')->where('aid',aid)->where('bid',0)->find();
			if(getcustom('business_apply_queue_free_rate_back') && $queue_free_set_all['status'] ==1){
				$queue_free_set['show_rate_back'] = 1;
				if($info){
					$free_set = Db::name('queue_free_set')->where('aid',aid)->where('bid',$info['id'])->find();
					$queue_free_set['rate_back'] = $free_set['rate_back'];
				}			
			}
		}
		
		//定制内容.77
		$formField = [];
		$formvaldata = [];
		$register_record = [];
		$hasCustom = 0;
		$rdata = [];
		$rdata['has_custom'] = $hasCustom;
		$rdata['custom_form_field'] = $formField;
		$rdata['register_forms'] = $register_forms;
		$rdata['formvaldata'] = $formvaldata;
        $rdata['title'] = '申请入驻';
		$rdata['clist'] = $clist;
		$rdata['bset'] = $bset;
		$rdata['info'] = $info ? $info : [];
		$rdata['active_coin'] = $active_coin;
		$rdata['queue_free_set'] = $queue_free_set;
		return $this->json($rdata);
	}
	

    public function mybusiness(){
        if(getcustom('business_mybusiness')){
        	$this->checklogin();
            //我的店铺
            if(request()->isPost()){
                //查询我的店铺
                $bids = Db::name('admin_user')->alias('au')->join('business b','b.id = au.bid')
                    ->where('au.isadmin','>=',1)->where('au.isadmin','<=',2)->where('au.bid','>',0)->where('au.mid',mid)->where('au.aid',aid)->group('au.bid')->column('au.bid');
                if(!$bids){
                    return $this->json(['status'=>0,'msg'=>'您还未入驻','goback'=>true]);
                }
                $bidsnum = count($bids);
                if($bidsnum == 1){
                    return $this->json(['status'=>1,'msg'=>'获取成功','bid'=>$bids[0]]);
                }else{
                	$bids = implode(',',$bids);
                    return $this->json(['status'=>2,'msg'=>'获取成功','bids'=>$bids]);
                }
            }
        }
    }



}