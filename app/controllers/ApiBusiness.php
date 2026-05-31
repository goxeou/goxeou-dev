<?php


namespace app\controllers;
use think\facade\Db;
class ApiBusiness extends ApiCommon{
    public function initialize(){
		parent::initialize();
		$bset = Db::name('business_sysset')->where('aid',aid)->find();
		if($bset['status'] == 0){
			die(jsonEncode(['status'=>-4, 'msg' => '功能未开启', 'url'=>'/pages/index/index']));
		}
	}
	//商家详情页
	public function index($select_bid=0){
		if(getcustom('member_business')){
			//商户注册会员
            if(input('param.regbid')){
            	//平台权限
                $admin_user = Db::name('admin_user')->where('aid',aid)->where('isadmin','>',0)->field('auth_type,auth_data')->find();
                if($admin_user){
                	if($admin_user['auth_type'] !=1 ){
	                    $admin_auth = !empty($admin_user['auth_data'])?json_decode($admin_user['auth_data'],true):[];
	                    if(in_array('MemberBusiness,MemberBusiness',$admin_auth)){
	                        $this->checklogin();
		                }
	                }else{
	                	$this->checklogin();
	                }
	            }
            }
        }
		$bid = $select_bid>0 ? $select_bid : input('param.id/d');
        $latitude = input('param.latitude','');
        $longitude = input('param.longitude','');
		$business = Db::name('business')->where('aid',aid)->where('id',$bid)->where('status',1)->find();
		if(!$business) return $this->json(['status'=>0,'msg'=>'商家信息不存在']);
        //如果是门店模式且用户选择了门店，则显示该商户该门店的数据，否则显示全部门店数据
        $mendian_id = input('param.mendian_id/d',0);
		if($business['is_open']==0) return $this->json(['status'=>-4,'msg'=>'商家未营业']);
		

		$is_open = 0;
		if($is_open==0){
			if($business['start_hours'] != $business['end_hours']){
				$start_time = strtotime(date('Y-m-d '.$business['start_hours']));
				$end_time = strtotime(date('Y-m-d '.$business['end_hours']));
				if(($start_time < $end_time && ($start_time > time() || $end_time < time())) || ($start_time > $end_time && ($start_time > time() && $end_time < time()))){
					//return $this->json(['status'=>-4,'msg'=>'商家不在营业时间']);
				}else{
					$is_open = 1;
				}
			}else{
				$is_open = 1;
			}
		}
		if($is_open==0){
			$start_time = strtotime(date('Y-m-d '.$business['start_hours2']));
			$end_time = strtotime(date('Y-m-d '.$business['end_hours2']));
			if($start_time == $end_time || ($start_time < $end_time && ($start_time > time() || $end_time < time())) || ($start_time > $end_time && ($start_time > time() && $end_time < time()))){
				//return $this->json(['status'=>-4,'msg'=>'商家不在营业时间']);
			}else{
				$is_open = 1;
			}
		}
		if($is_open==0){
			$start_time = strtotime(date('Y-m-d '.$business['start_hours3']));
			$end_time = strtotime(date('Y-m-d '.$business['end_hours3']));
			if($start_time == $end_time || ($start_time < $end_time && ($start_time > time() || $end_time < time())) || ($start_time > $end_time && ($start_time > time() && $end_time < time()))){
				//return $this->json(['status'=>-4,'msg'=>'商家不在营业时间']);
			}else{
				$is_open = 1;
			}
		}
		if($is_open == 0 && $business['end_buy_status'] == 0){
            $open_time = $business['start_hours'].'-'.$business['end_hours'];
            if($business['start_hours2'] != $business['end_hours2']){
                $open_time .= ' '.$business['start_hours2'].'-'.$business['end_hours2'];
            }
            if($business['start_hours3'] != $business['end_hours3']){
                $open_time .= ' '.$business['start_hours3'].'-'.$business['end_hours3'];
            }
			return $this->json(['status'=>-4,'msg'=>'商家已打烊，营业时间为:'.$open_time]);
		}
		$bset = Db::name('business_sysset')->where('aid',aid)->find();

		// 是否强制登录
		if(getcustom('design_business_history')){
			if(!empty($bset['homepage_need_login']) && $bset['homepage_need_login'] == 1){
				$this->checklogin();
			}
        }
		$addviewnum = 1;
		if(getcustom('blist_showviewnum')){
			$addviewnum = $bset['viewnum_addnum'];
			$business['viewnum'] = $business['viewnum'] + $bset['viewnum_defaultnum'];
		}
		$bset['show_mianndan'] = 0;
		if(getcustom('yx_business_miandan')){
			$miandan_set = Db::name('business_miandan_set')->where('aid',aid)->where('bid',$bid)->find();
			if($miandan_set['status'] == 1){
				$bset['show_mianndan'] = 1;
			}
		}
		Db::name('business')->where('id',$bid)->inc('viewnum',$addviewnum)->update();
        $business['turnover'] = 0;
        if(getcustom('business_show_turnover')){
            if($business['turnover_show'] == 1){
                $business['turnover'] = \app\commons\Business::totalTurnover(aid, $bid);
            }
        }
		$countcomment = Db::name('business_comment')->where('aid',aid)->where('bid',$bid)->where('status',1)->count();
		$couponcount= Db::name('coupon')->where('aid',aid)->where('bid',$bid)->where('tolist',1)->order('sort desc,id desc')->count();
		
		$prosales = Db::name('shop_product')->where('bid',$bid)->sum('sales');
		if($business['sales'] < $prosales) $business['sales'] = $prosales;
        $sales = Db::name('business_sales')->where('bid',$bid)->value('total_sales');
        $business['sales'] = !$sales?0:$sales;
        $admin_set = Db::name('admin_set')->where('aid',aid)->find();
		$pagedata = Db::name('designerpage')->where('aid',aid)->where('bid',$bid)->where('ishome',1)->find();

        //门店模式下 如果有门店，则显示门店信息
        $show_mendian = 0;
        $mendian = '';
        $rdata = [];
        if(getcustom('show_location')){
            if($admin_set['mode']==3){
                //商户门店
                $bfield = 'id,name,province,city,district,address,longitude,latitude,pic';

                if($latitude && $longitude){
                    $mdorder = Db::raw("({$longitude}-longitude)*({$longitude}-longitude) + ({$latitude}-latitude)*({$latitude}-latitude) asc");
                    $bfield .=",round(6378.138*2*asin(sqrt(pow(sin( ({$latitude}*pi()/180-latitude*pi()/180)/2),2)+cos({$latitude}*pi()/180)*cos(latitude*pi()/180)* pow(sin( ({$longitude}*pi()/180-longitude*pi()/180)/2),2)))*1000) as distance";
                    $mendianlist = Db::name('mendian')->where('aid',aid)->where('status',1)->where('bid',$bid)->orderRaw($mdorder)->field($bfield)->select()->toArray();
                }else{
                    $bfield .=',0 distance';
                    $mendianlist = Db::name('mendian')->where('aid',aid)->where('status',1)->where('bid',$bid)->order('sort desc,id asc')->field($bfield)->select()->toArray();
                }
                if(count($mendianlist)>0){
                    $show_mendian = 1;
                    foreach ($mendianlist as $k=>$v){
                        if(empty($v['distance'])){
                            $mendianlist[$k]['distance'] = '';
                        }else{
                            $mendianlist[$k]['distance'] = round($v['distance']/1000,2).'km';
                        }
                        if($mendian_id && $mendian_id==$v['id']){
                            $mendian = $mendianlist[$k];
                        }
                    }
                }
                $rdata['show_mendian'] = $show_mendian;
                $rdata['mendian'] = $mendian??'';
                $rdata['mendianlist'] = $mendianlist??[];
            }
			if($admin_set['mode']==2 || $admin_set['mode']==0){
				$rdata['show_location_jl'] = 1;
			}
        }
		if(getcustom('business_indexbindfenxiao')){
			$rdata['business_indexbindfenxiao'] = true;
			$buser = Db::name('admin_user')->where('aid',aid)->where('bid',$bid)->where('isadmin',1)->find();
			if($buser['mid']) $business['mid'] = $buser['mid'];
		}
        $sysset = ['name'=>$business['name'],'logo'=>$business['logo'],'desc'=>$business['address'],'tel'=>$business['tel'],'mode'=>$admin_set['mode'],'address' => $business['address'],'sysname'=>$admin_set['name']];

        if(mid>0){
			//添加浏览历史
			$rs = Db::name('member_history')->where(array('aid'=>aid,'mid'=>mid,'proid'=>$bid,'type'=>'business'))->find();
			if($rs){
				Db::name('member_history')->where(array('id'=>$rs['id']))->update(['createtime'=>time()]);
			}else{
				Db::name('member_history')->insert(array('aid'=>aid,'mid'=>mid,'proid'=>$bid,'type'=>'business','createtime'=>time()));
			}
		}

		if(!$pagedata){
			if(getcustom('plug_businessqr')){
				die(jsonEncode(['status'=>-3,'url'=>'/pages/shop/fastbuy2?bid='.$bid]));
			}
			$rdata['isdiy'] = 0;
			$rdata['bset'] = $bset;
			$rdata['sysset'] = $sysset;
			$rdata['business'] = $business;
			$rdata['countcomment'] = $countcomment;
			$rdata['couponcount'] = $couponcount;
			$rdata['pics'] = $business['pics']?explode(',',$business['pics']):[PRE_URL.'/static/img/topbg.png'];
			$rdata['showfw'] = getcustom('businessindex_showfw');
            if($select_bid) $rdata['needlocation'] = true;
			if(getcustom('businessindex_showfw')){
				$clist = Db::name('yuyue_category')->where('aid',aid)->where('pid',0)->where('bid',$bid)->where('status',1)->order('sort desc,id')->select()->toArray();
				$rdata['yuyue_clist'] = $clist;
			}
            if(getcustom('business_showshortvideo')){
                //短视频
                $rdata['shortvideos'] = $shortvideos;
                $sysset = Db::name('shortvideo_sysset')->where('aid',aid)->field('list_type')->find();
                $rdata['shortvideo_type'] = $sysset?$sysset['list_type']:0;
                if($business['show_shortvideo'] && $business['show_shortvideo_num']>0){
                    $where = [];
                    $where[] = ['bid','=',$bid];
                    $where[] = ['status','=',1];
                    $where[] = ['aid','=',aid];
                    $svlist = Db::name('shortvideo')->where($where)->order('sort desc,zan_num desc,createtime desc')->page(1,$business['show_shortvideo_num'])->select()->toArray();
                    if($svlist){
                        foreach($svlist as &$sv){
                            if($sv['view_num'] > 10000){
                                $sv['view_num'] = round($sv['view_num'] / 10000,1).'W';
                            }
                            if($sv['zan_num'] > 10000){
                                $sv['zan_num'] = round($sv['zan_num'] / 10000,1).'W';
                            }
                            if(getcustom('video_qq_url')){
                                $sv['url'] = \app\customs\VideoQQ::getMp4Url($sv['url']);
                            }
                            if($sv['bid']!=0){
                                $binfo = Db::name('business')->where('aid',aid)->where('id',$sv['bid'])->field('id,name,logo')->find();
                            } else {
                                if($sv['mid'] > 0){
                                    $binfo = Db::name('member')->where('aid',aid)->where('id',$sv['mid'])->field('nickname name,headimg logo')->find();
                                }else{
                                    $binfo = Db::name('admin_set')->where('aid',aid)->field('name,logo')->find();
                                }
                            }
                            if(!$binfo) $binfo = [];
                            $sv['binfo'] = $binfo;
                        }
                        unset($sv);
                        $rdata['shortvideos'] = $svlist;
                    }
                }
            }
            if(getcustom('business_poster')){
            	$rdata['showShare'] = true;
            }
			return $this->json($rdata);
		}

		$pageinfo = json_decode($pagedata['pageinfo'],true);
		$pagecontent = json_decode(\app\commons\System::initpagecontent($pagedata['content'],aid,mid,platform,$latitude,$longitude,'',$mendian_id),true);
		$pageparams = $pageinfo[0]['params'];
        if($pageparams['needlogin'] == '1'){
            $this->checklogin();
        }

		
		$guanggaopic = '';
		$guanggaourl = '';
		if($pageparams['showgg']==1){
			$showgg = 0;
			if($pageparams['ggrenqun']){
				if($pageparams['ggrenqun']['0']){
					$showgg = 1;
				}
				if($pageparams['ggrenqun']['-1'] && $this->member['subscribe']==1){
					$showgg = 1;
				}
				if($pageparams['ggrenqun']['-2'] && $this->member['subscribe']!=1){
					$showgg = 1;
				}
				if($showgg==0 && $pageparams['ggrenqun'][$this->member['levelid']]){
					$showgg = 1;
				}
			}
			if($showgg == 1 && $pageparams['cishu']==0 && $this->member){
				$hasshowlog = Db::name('guanggao_showlog')->where('mid',mid)->where('pic',$pageparams['guanggao'])->find();
				if($hasshowlog){
					$showgg = 0;
				}else{
					Db::name('guanggao_showlog')->insert(['aid'=>aid,'mid'=>mid,'pic'=>$pageparams['guanggao'],'createtime'=>time()]);
				}
			}
			if($showgg){
				$guanggaopic = $pageparams['guanggao'];
				$guanggaourl = $pageparams['hrefurl'];
			}
		}
        //广告参数
        $default_ggskip = $pageparams['showgg']==2?0:1;//视频默认不跳过，图片默认跳过
        $default_ggcover = $pageparams['showgg']==2?1:0;//视频默认全屏，图片默认不全屏
        $guanggaoparam = [
            'showgg'=>$pageparams['showgg']??0,
            'guanggaopic'=>$guanggaopic,
            'guanggaourl'=>$guanggaourl,
            'ggskip'=>$default_ggskip,
            'ggcover'=>$default_ggcover,
            'skiptype'=>1,//1右上角关闭 2底部跳过
        ];
        if($pageparams['showgg']>0){
            if(getcustom('design_guanggao_control')){
                $guanggaoparam['ggskip'] = isset($pageparams['ggskip'])?$pageparams['ggskip']:$default_ggskip;
                $guanggaoparam['ggcover'] = isset($pageparams['ggcover'])?$pageparams['ggcover']:$default_ggcover;
                $guanggaoparam['skiptype'] = 2;
            }
        }


		
		if(!$pageparams['bgcolor']){
			$pageparams['bgcolor'] = '#f7f7f8';
		}
        $rdata['pics'] = $business['pics']?explode(',',$business['pics']):[PRE_URL.'/static/img/topbg.png'];
		$rdata['status'] = 1;
		$rdata['isdiy'] = 1;
		$rdata['bset'] = $bset;
		$rdata['guanggaopic'] = $guanggaopic;
		$rdata['guanggaourl'] = $guanggaourl;
		$rdata['guanggaotype'] = $pageparams['showgg'];
        $rdata['guanggaoparam'] = $guanggaoparam;
		$rdata['pageinfo'] = $pageparams;
		$rdata['pagecontent'] = $pagecontent;
        $rdata['sysset'] = $sysset;
        $rdata['business'] = $business;
        if($select_bid) $rdata['needlocation'] = true;
        if(getcustom('yuyue_douyin_video')){
            $yuyueset = Db::name('yuyue_set')->field('ad_status,ad_pic,ad_link,video_status,video_tag,video_title')->where('aid',aid)->where('bid',0)->find();
            if($yuyueset['video_tag']) $yuyueset['video_tag'] = explode(',',$yuyueset['video_tag']);
            else $yuyueset['video_tag'] = [];
            $rdata['yuyueset'] = $yuyueset;
        }


		return $this->json($rdata);
	}

	public function main(){
		$pageid = input('param.id/d');
		$pagedata = Db::name('designerpage')->where('aid',aid)->where('id',$pageid)->find();
		if(!$pagedata){
			return $this->json(['status'=>0,'msg'=>'页面不存在']);
		}
		$bid = $pagedata['bid'];

		$business = Db::name('business')->where('aid',aid)->where('id',$bid)->where('status',1)->find();
		$pageinfo = json_decode($pagedata['pageinfo'],true);
		$pagecontent = json_decode(\app\commons\System::initpagecontent($pagedata['content'],aid,mid,platform),true);
		$pageparams = $pageinfo[0]['params'];
		if($pageparams['quanxian']){
			if(!$pageparams['quanxian']['0'] && !$pageparams['quanxian'][$this->member['levelid']]){
				return $this->json(['status'=>0,'msg'=>'您无查看权限']);
			}
		}
		if($pageparams['fufei']==1 && floatval($pageparams['money'])>0){//付费查看
			$hasff = Db::name('designerpage_order')->where('aid',aid)->where('pageid',$pagedata['id'])->where('mid',mid)->where('status',1)->find();
			if(!$hasff){
				$adata = array();
				$adata['aid'] = aid;
				$adata['pageid'] = $pagedata['id'];
				$adata['mid'] = mid;
				$adata['title'] = $pageparams['title'];
				$adata['price'] = floatval($pageparams['money']);
				$adata['ordernum'] = date('ymdHis').aid.rand(1000,9999);
				$adata['createtime'] = time();
				$orderid = Db::name('designerpage_order')->insertGetId($adata);
				return $this->json(['status'=>2,'msg'=>'需要付费查看','orderid'=>$orderid]);
			}
		}
		
		$guanggaopic = '';
		$guanggaourl = '';
		if($pageparams['showgg']==1){
			$showgg = 0;
			if($pageparams['ggrenqun']){
				if($pageparams['ggrenqun']['0']){
					$showgg = 1;
				}
				if($pageparams['ggrenqun']['-1'] && $this->member['subscribe']==1){
					$showgg = 1;
				}
				if($pageparams['ggrenqun']['-2'] && $this->member['subscribe']!=1){
					$showgg = 1;
				}
				if($showgg==0 && $pageparams['ggrenqun'][$this->member['levelid']]){
					$showgg = 1;
				}
			}
			if($showgg == 1 && $pageparams['cishu']==0 && $this->member){
				$hasshowlog = Db::name('guanggao_showlog')->where('mid',mid)->where('pic',$pageparams['guanggao'])->find();
				if($hasshowlog){
					$showgg = 0;
				}else{
					Db::name('guanggao_showlog')->insert(['aid'=>aid,'mid'=>mid,'pic'=>$pageparams['guanggao'],'createtime'=>time()]);
				}
			}
			if($showgg){
				$guanggaopic = $pageparams['guanggao'];
				$guanggaourl = $pageparams['hrefurl'];
			}
		}
		$sysset = ['name'=>$business['name'],'logo'=>$business['pic'],'desc'=>$business['address'],'tel'=>$business['tel']];
		
		if(!$pageparams['bgcolor']){
			$pageparams['bgcolor'] = '#f7f7f8';
		}
		$rdata = [];
		$rdata['status'] = 1;
		$rdata['isdiy'] = 1;
		$rdata['guanggaopic'] = $guanggaopic;
		$rdata['guanggaourl'] = $guanggaourl;
        $rdata['guanggaotype'] = $pageparams['showgg'];
		$rdata['pageinfo'] = $pageparams;
		$rdata['pagecontent'] = $pagecontent;
		//dump($pagecontent);die;
		$rdata['sysset'] = $sysset;
		return $this->json($rdata);
	}
	//获取商品列表 评价列表
	public function getdatalist(){
		$id = input('param.id/d');
		$st = input('param.st/d');
		$mendian_id = input('param.mendian_id/d',0);//如果切换了门店，则只显示该商家下该门店的数据
		$pagenum = input('param.pagenum');
		if(!$pagenum) $pagenum = 1;
		if($st == 0){//商品
			$pernum = 20;
			$where = [];
			$where[] = ['aid','=',aid];
			$where[] = ['bid','=',$id];
			//$where[] = ['status','=',1];
			$nowtime = time();
			$nowhm = date('H:i');
            if(getcustom('product_bind_mendian')){
                if($mendian_id){
                    $where[] = Db::raw("find_in_set({$mendian_id},`bind_mendian_ids`) OR find_in_set('-1',`bind_mendian_ids`) OR ISNULL(bind_mendian_ids)");
                }
            }
            $price_tag = $cost_tag = '￥';
            $price_color = $cost_color = '';
            $show_sellprice = true;
            $show_cost = false;
            $hidecart = false;
            if(getcustom('product_cost_show') || getcustom('product_sellprice_show') || getcustom('product_list_nocart')){
                $shopset = Db::name('shop_sysset')->where('aid',aid)->find();
                if(isset($shopset['hide_sellprice']) && $shopset['hide_sellprice']==1){
                    $show_sellprice = false;
                }
                if(isset($shopset['hide_cost']) && $shopset['hide_sellprice']==0){
                    $show_cost = true;
                }
                if($shopset['sellprice_name']){
                    $price_tag = $shopset['sellprice_name'];
                }
                if($shopset['sellprice_color']){
                    $price_color = $shopset['sellprice_color'];
                }
                if($shopset['cost_name']){
                    $cost_tag = $shopset['cost_name'];
                }
                if($shopset['sellprice_color']){
                    $cost_color = $shopset['cost_color'];
                }
                if (getcustom('product_list_nocart') && $shopset['list_nocart_platform']){
                    $cartnoplatform = explode(',',$shopset['list_nocart_platform']);
                    if(in_array(platform,$cartnoplatform)){
                        $hidecart = true;
                    }
                }
            }

            //显示条件
            $where2 = "find_in_set('-1',showtj)";
            if($this->member){
                $where2 .= " or find_in_set('".$this->member['levelid']."',showtj)";
                if($this->member['subscribe']==1){
                    $where2 .= " or find_in_set('0',showtj)";
                }
            }else{
                $where2 .= " or find_in_set('-2',showtj)";
            }
            $where[] = Db::raw($where2);

			$where[] = Db::raw("`status`=1 or (`status`=2 and unix_timestamp(start_time)<=$nowtime and unix_timestamp(end_time)>=$nowtime) or (`status`=3 and ((start_hours<end_hours and start_hours<='$nowhm' and end_hours>='$nowhm') or (start_hours>=end_hours and (start_hours<='$nowhm' or end_hours>='$nowhm'))) )");

			$prolist = Db::name('shop_product')->where($where)->page($pagenum,$pernum)->order('sort desc,id desc')->select()->toArray();
            $prolist = $this->formatprolist($prolist);
			foreach ($prolist as $v=>&$value){
                $value['price_tag'] = $price_tag;
                $value['price_color'] = $price_color;
                $value['cost_tag'] = $cost_tag;
                $value['cost_color'] = $cost_color;
                $value['show_sellprice'] = $show_sellprice;
                $value['show_cost'] = $show_cost;
                $value['hide_cart'] = $hidecart;
            }
			if(getcustom('product_wholesale')){
				foreach($prolist as $k=>$v){
					if($v['product_type'] == 4){
						$guigedata = json_decode($v['guigedata'],true);
						$prolist[$k]['gg_num'] =  count($guigedata);
					}
				}
			}
			//未登录查看价格
			if(getcustom('show_price_unlogin')){
				$shopset = Db::name('shop_sysset')->where('aid', aid)->find();
				$mid = mid;
				if(!$mid && $shopset['is_show_price_unlogin'] == 0){
					foreach($prolist as &$pv){
						$pv['sell_price'] =  $shopset['show_price_unlogin_txt'];					
					}
				}			
			}
			//未审核查看价格
			if(getcustom('show_price_uncheck')){
				$shopset = Db::name('shop_sysset')->where('aid', aid)->find();
				if(mid && $this->member['checkst'] !=1 && $shopset['is_show_price_uncheck'] == 0){
					foreach($prolist as &$pv){
						$pv['sell_price'] =  $shopset['show_price_uncheck_txt'];					
					}
				}			
			}
            if(!$prolist) $prolist = [];
			if(request()->isPost()){
				return $this->json(['status'=>1,'data'=>$prolist]);
			}
		}elseif($st == -1){//预约商品
			$where = [];
			$where[] = ['aid','=',aid];
			$where[] = ['ischecked','=',1];
			//$where[] = ['status','=',1];
			$nowtime = time();
			$where[] = Db::raw("`status`=1  or (`status`=2 and unix_timestamp(start_time)<=$nowtime and unix_timestamp(end_time)>=$nowtime)");
			$where[] = ['bid','=',$id];
			$order = 'sort desc,id desc';
			//分类 
			if(input('param.yuyue_cid')){
				$cid = input('post.yuyue_cid/d');
				//子分类
				$clist = Db::name('yuyue_category')->where('aid',aid)->where('pid',$cid)->column('id');
				if($clist){
					$clist2 = Db::name('yuyue_category')->where('aid',aid)->where('pid','in',$clist)->column('id');
					$cCate = array_merge($clist, $clist2, [$cid]);
					if($cCate){
						$whereCid = [];
						foreach($cCate as $k => $c2){
							$whereCid[] = "find_in_set({$c2},cid)";
						}
						$where[] = Db::raw(implode(' or ',$whereCid));
					}
				} else {
					$where[] = Db::raw("find_in_set(".$cid.",cid)");
				}
			}
			if(input('param.keyword')){
				$where[] = ['name','like','%'.input('param.keyword').'%'];
			}
			$pernum = 10;
			$pagenum = input('post.pagenum');
			if(!$pagenum) $pagenum = 1;
			$datalist = Db::name('yuyue_product')->field("id,pic,name,sales,sell_price,danwei")->where($where)->page($pagenum,$pernum)->order($order)->select()->toArray();
			if(!$datalist) $datalist = [];
			$datalist = $this->formatprolist($datalist);
			return $this->json(['status'=>1,'data'=>$datalist]);
		}elseif($st == 3){
			//免单
			if(getcustom('yx_business_miandan')){
				$where = [];
				$where[] = ['aid','=',aid];
				$where[] = ['is_del','=',0];
				$where[] = ['status','=',1];
				$where[] = ['ischecked','=',1];

				if(input('param.bid')){
					$bid = input('param.bid/d');
				}elseif(input('param.id/d')){
					$bid = input('param.id/d');
				}else{
					$bid = 0;
				}			
				$where[] = ['bid','=',$bid];
				$order = 'id desc';
				$pernum = 10;
				$pagenum = input('post.pagenum');
				if(!$pagenum) $pagenum = 1;
				$datalist = Db::name('business_miandan')->where($where)->page($pagenum,$pernum)->order($order)->select()->toArray();
				if(!$datalist) $datalist = [];
				$miandanset = Db::name('business_miandan_set')->where('aid',aid)->where('bid',$bid)->find();
				if($miandanset['status'] == 0){
					$datalist = [];
				}
				return $this->json(['status'=>1,'data'=>$datalist]);
			}
		}else{//评价
			$pernum = 10;
			$where = [];
			$where[] = ['aid','=',aid];
			$where[] = ['bid','=',$id];
			$where[] = ['status','=',1];
			$commentlist = Db::name('business_comment')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
			if(!$commentlist) $commentlist = [];
			foreach($commentlist as $k=>$pl){
				$commentlist[$k]['createtime'] = date('Y-m-d H:i',$pl['createtime']);
				if($commentlist[$k]['content_pic']) $commentlist[$k]['content_pic'] = explode(',',$commentlist[$k]['content_pic']);
			}
			if(request()->isPost()){
				return $this->json(['status'=>1,'data'=>$commentlist]);
			}
		}
	}
	//商家优惠券
	public function couponlist(){
		//商家优惠券
		$couponlist= Db::name('coupon')->where('aid',aid)->where('bid',$id)->where("unix_timestamp(starttime)<=".time()." and unix_timestamp(endtime)>=".time())->order('sort desc,id desc')->select()->toArray();
		if(!$couponlist) $couponlist = [];
		foreach($couponlist as $k=>$v){
			$haveget = Db::name('coupon_record')->where('aid',aid)->where('mid',mid)->where('couponid',$v['id'])->count();
			$couponlist[$k]['haveget'] = $haveget;
			$couponlist[$k]['starttime'] = date('m-d H:i',strtotime($v['starttime']));
			$couponlist[$k]['endtime'] = date('m-d H:i',strtotime($v['endtime']));
		}
		$rdata = [];
		$rdata['couponlist'] = $couponlist;
		return $this->json($rdata);
	}
	//分类商家
	public function clist(){
		$clist = Db::name('business_category')->where('aid',aid)->where('status',1)->order('sort desc,id')->select()->toArray();
		if(request()->isPost()){
			$cid = input('param.cid/d');
			$where = [];
			$where[] = ['aid','=',aid];
			$where[] = ['status','=',1];
			//分类 
			if($cid){
				$where[] = Db::raw('find_in_set('.$cid.',cid)'); //['cid','=',$cid];
			}else{
				//$where[] = Db::raw('find_in_set('.$clist[0]['id'].',cid)'); // ['cid','=',$clist[0]['id']];
			}
			if(input('param.keyword')){
				$where[] = ['name','like','%'.input('param.keyword').'%'];
			}
			$nowhm = date('H:i');
			$where[] = Db::raw("(start_hours<end_hours and start_hours<='$nowhm' and end_hours>='$nowhm') or (start_hours>=end_hours and (start_hours<='$nowhm' or end_hours>='$nowhm')) or (start_hours2<end_hours2 and start_hours2<='$nowhm' and end_hours2>='$nowhm') or (start_hours2>end_hours2 and (start_hours2<='$nowhm' or end_hours2>='$nowhm')) or (start_hours3<end_hours3 and start_hours3<='$nowhm' and end_hours3>='$nowhm') or (start_hours3>end_hours3 and (start_hours3<='$nowhm' or end_hours3>='$nowhm'))");

			$pernum = 12;
			$pagenum = input('post.pagenum');
			if(!$pagenum) $pagenum = 1;

			$longitude = input('post.longitude');
			$latitude = input('post.latitude');
			if($longitude && $latitude){
				$orderBy = Db::raw("({$longitude}-longitude)*({$longitude}-longitude) + ({$latitude}-latitude)*({$latitude}-latitude) ");
			}else{
				$orderBy = 'sort desc,id';
			}
			$datalist = Db::name('business')->field("id,name,logo,desc,tel,address,longitude,latitude")->where($where)->order($orderBy)->page($pagenum,$pernum)->select()->toArray();
			if(!$datalist) $datalist = array();
			
			if($this->member){
				$memberlevel = Db::name('member_level')->where('id',$this->member['levelid'])->find();
			}else{
				$memberlevel = [];
			}
			foreach($datalist as $k=>$v){
				if($longitude && $latitude){
					$datalist[$k]['juli'] = (getdistance($longitude,$latitude,$v['longitude'],$v['latitude'],2)).'km';
				}else{
					$datalist[$k]['juli'] = '';
				}
				if($memberlevel && $memberlevel['notshowbtel']==1){
					$datalist[$k]['tel'] = Db::name('admin_set')->where('aid',aid)->value('tel');
				}
			}
			return $this->json(['status'=>1,'data'=>$datalist]);
		}
		$rdata = [];
		$rdata['clist'] = $clist;
		return $this->json($rdata);
	}
	//商家列表
	public function blist(){
		if(getcustom('business_show_maidanscoredk')){
			//是否展示买单积分抵扣
			$show_maidanscoredk = Db::name('business_sysset')->where('aid',aid)->value('show_maidanscoredk');
			$show_maidanscoredk = $show_maidanscoredk?true:false;
        }
		if(request()->isPost()){
			$pernum = 10;
			$pagenum = input('post.pagenum/d');
			if(!$pagenum) $pagenum = 1;
			$cid = input('post.cid/d');
			$where = [];
			if(input('post.ids')){
				$where[] = ['b.id','in',input('post.ids')];
			}
			$where[] = ['b.aid','=',aid];
			$where[] = ['b.status','=',1];
			$where[] = ['b.is_open','=',1];
			if($cid) $where[] = Db::raw('find_in_set('.$cid.',cid)'); // ['cid','=',$cid];
			if(input('param.keyword')){
				$where[] = ['b.name|b.desc','like','%'.input('param.keyword').'%'];
			}
			$nowhm = date('H:i');
			$where[] = Db::raw("(start_hours<end_hours and start_hours<='$nowhm' and end_hours>='$nowhm') or (start_hours>=end_hours and (start_hours<='$nowhm' or end_hours>='$nowhm')) or (start_hours2<end_hours2 and start_hours2<='$nowhm' and end_hours2>='$nowhm') or (start_hours2>end_hours2 and (start_hours2<='$nowhm' or end_hours2>='$nowhm')) or (start_hours3<end_hours3 and start_hours3<='$nowhm' and end_hours3>='$nowhm') or (start_hours3>end_hours3 and (start_hours3<='$nowhm' or end_hours3>='$nowhm'))");

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

			$bset = Db::name('business_sysset')->where('aid',aid)->find();
			$field = 'b.id,b.logo,b.name,b.tel,b.address,b.latitude,b.longitude,b.comment_score,b.viewnum,b.tourl,s.total_sales sales';
			if(getcustom('business_reward_member')){
                $field .= ',reward_member,reward_member_bili';
            }
            if(getcustom('business_show_turnover')){
                $field .= ',turnover_show';
            }
            if(getcustom('business_maidan_scoredk')){
            	$field .= ',scoredkmaxset,scoredkmaxval';
            }
			$datalist = Db::name('business')
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
				if(getcustom('business_show_maidanscoredk')){
                    if($show_maidanscoredk){
                        //查询买单积分抵扣比例
                        $scoredkmaxpercent = Db::name('admin_set')->where('aid',aid)->value('scoredkmaxpercent');
                        $scoredkmaxpercent = $scoredkmaxpercent?$scoredkmaxpercent:0;
                    }
                }
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
			
			return $this->json(['status'=>1,'data'=>$datalist]);
		}
		//分类
		$clist = Db::name('business_category')->where('aid',aid)->where('status',1)->field('id,name,pic')->order('sort desc,id')->select()->toArray();
		
		$rdata = [];
		$rdata['clist'] = $clist;
		$rdata['showtype'] = 0;
		// if(getcustom('blist_showtype1')){
		// 	$rdata['showtype'] = 1;
		// }
		$rdata['showviewnum'] = false;
		if(getcustom('blist_showviewnum')){
			$rdata['showviewnum'] = true;
		}
		$show_style = 0;
		if(getcustom('business_nearby_list')){
            $show_style = 1;
        }
        $rdata['show_style'] = $show_style;
        if(getcustom('business_show_maidanscoredk')){
        	//是否展示买单积分抵扣
			$rdata['show_maidanscoredk'] = $show_maidanscoredk;
        }
		return $this->json($rdata);
	}
    //商家列表
    public function blist2(){
            $pernum = 15;
            $pagenum = input('post.pagenum/d');
            if(!$pagenum) $pagenum = 1;
            $cid = input('post.cid/d');
            $where = [];
            $where[] = ['aid','=',aid];
            $where[] = ['status','=',1];
            $where[] = ['is_open','=',1];
            if($cid) $where[] = Db::raw('find_in_set('.$cid.',cid)'); // ['cid','=',$cid];
            if(input('param.keyword')){
                $where[] = ['name','like','%'.input('param.keyword').'%'];
            }
            $nowhm = date('H:i');
            $where[] = Db::raw("(start_hours<end_hours and start_hours<='$nowhm' and end_hours>='$nowhm') or (start_hours>=end_hours and (start_hours<='$nowhm' or end_hours>='$nowhm')) or (start_hours2<end_hours2 and start_hours2<='$nowhm' and end_hours2>='$nowhm') or (start_hours2>end_hours2 and (start_hours2<='$nowhm' or end_hours2>='$nowhm')) or (start_hours3<end_hours3 and start_hours3<='$nowhm' and end_hours3>='$nowhm') or (start_hours3>end_hours3 and (start_hours3<='$nowhm' or end_hours3>='$nowhm'))");

            $latitude = input('param.latitude');
            $longitude = input('param.longitude');
            if($longitude && $latitude){
                $order = Db::raw("({$longitude}-longitude)*({$longitude}-longitude) + ({$latitude}-latitude)*({$latitude}-latitude) ");
            }else{
                $order = 'sort desc,id desc';
            }
            $field = input('param.field');
            if($field && $field!='juli'){
                $order = $field.' '.input('param.order').',id desc';
            }
            $datalist = Db::name('business')->where($where)->field('id,logo,name,sales,address,latitude,longitude,comment_score,tel,start_hours,end_hours,start_hours2,end_hours2,start_hours3,end_hours3')->page($pagenum,$pernum)->order($order)->select()->toArray();
            $nowtime = time();
            $nowhm = date('H:i');
            if(!$datalist) $datalist = array();
            foreach($datalist as $k=>$v){
                $statuswhere = "`status`=1 or (`status`=2 and unix_timestamp(start_time)<=$nowtime and unix_timestamp(end_time)>=$nowtime) or (`status`=3 and ((start_hours<end_hours and start_hours<='$nowhm' and end_hours>='$nowhm') or (start_hours>=end_hours and (start_hours<='$nowhm' or end_hours>='$nowhm'))) )";
//                $prolist = Db::name('shop_product')->where('bid',$v['id'])->where($statuswhere)->field('id,pic,name,sales,market_price,sell_price')->limit(4)->order('sort desc,id desc')->select()->toArray();
                if(!$prolist) $prolist = array();
                $v['prolist'] = $prolist;
                if(getcustom('restaurant')) {
//                    $restaurantProlist = Db::name('restaurant_product')->where('bid',$v['id'])->where($statuswhere)->field('id,pic,name,sales,market_price,sell_price')->limit(4)->order('sort desc,id desc')->select()->toArray();
                    if(!$restaurantProlist) $restaurantProlist = array();
                    $v['restaurantProlist'] = $restaurantProlist;
                }
                if($longitude && $latitude){
                    $v['juli'] = ''.getdistance($longitude,$latitude,$v['longitude'],$v['latitude'],2).'km';
                }else{
                    $v['juli'] = '';
                }
//                $prosales = Db::name('shop_product')->where('bid',$v['id'])->sum('sales');
//                if($v['sales'] < $prosales) $v['sales'] = $prosales;
                $datalist[$k] = $v;
            }
            return $this->json(['status'=>1,'data'=>$datalist]);
    }
	//入驻申请
	public function apply(){
		$this->checklogin();
        if(getcustom('business_num_limit')){
            if($this->admin['business_num_limit'] > 0){
                $bcount = Db::name('business')->where('aid',aid)->count();
                if($bcount >= $this->admin['business_num_limit']){
                    return $this->json(['status'=>-4,'msg'=>'多商户数量已达上限']);
                }
            }
        }
        $field = 'xieyi_show,xieyi';
        if(getcustom('business_deposit')){
            $field .= ',deposit';
        }
        $bset = Db::name('business_sysset')->where('aid',aid)->field($field)->find();

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
			$info['status'] = 0;
			$info['createtime'] = time();
			if(getcustom('active_coin')){
			    $info['activecoin_ratio'] = $formdata['activecoin_ratio']?:0;
//                $info['member_activecoin_ratio'] = $formdata['member_activecoin_ratio']?:0;
//                $info['business_activecoin_ratio'] = $formdata['business_activecoin_ratio']?:0;
                //前端参数写反了，不想改前端
                $info['member_activecoin_ratio'] = $formdata['business_activecoin_ratio']?:0;
                $info['business_activecoin_ratio'] = $formdata['member_activecoin_ratio']?:0;
            }
            if(getcustom('business_category_feepercent')){
				//商家分类是否设置了抽成费率
				if($formdata['cid']){
					$category = Db::name('business_category')->where('id',$formdata['cid'])->field('feepercent')->find();
					if(!$category){
						return $this->json(['status'=>0, 'msg'=>'商家分类不存在']);
					}
					if(!is_null($category['feepercent'])){
						$info['feepercent'] = $category['feepercent'];
					}
				}
			}
			if(!isset($info['feepercent'])){
				$info['feepercent'] = Db::name('business_sysset')->where('aid',aid)->value('default_rate');
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
				//自定义表单start
				if(getcustom('business_apply_form')){
					$res = $this->customRegister($formdata['customformdata'],$formdata['customformid'],$formdata['id']);
					if($res['status']!=1){
						return $this->json(['status'=>0,'msg'=>$res['msg']]);
					}else if($res['status']==1 && isset($res['recordid']) && $res['recordid']>0){
						$info['form_record_id'] = $res['recordid'];
					}
				}
				Db::name('business')->where('aid',aid)->where('mid',mid)->where('id',$formdata['id'])->update($info);
				if($uinfo['pwd']!=''){
					$uinfo['pwd'] = md5($uinfo['pwd']);
				}else{
					unset($uinfo['pwd']);
				}
				Db::name('admin_user')->where('aid',aid)->where('bid',$info['id'])->where('id',$uinfo['id'])->update($uinfo);
				if(getcustom('business_apply_queue_free_rate_back') && $hasyxqueuefree == 1){
					if($formdata['id']){
						$free_set = Db::name('queue_free_set')->where('aid',aid)->where('bid',$formdata['id'])->find();
						$queue_free_set['rate_back'] = $formdata['rate_back'];
						if($free_set){							
							Db::name('queue_free_set')->where('aid',aid)->where('bid',$formdata['id'])->update($queue_free_set);
						}else{
							$queue_free_set['aid'] = aid;
							$queue_free_set['bid'] = $formdata['id'];
							$queue_free_set['rate_status_business'] = -1;
							$queue_free_set['money_max'] = null;
							$queue_free_set['status'] = 0;
							$queue_free_set['createtime'] = time();
							Db::name('queue_free_set')->insertGetId($queue_free_set);
						}						
					}			
				}
			}else{
				$bid = Db::name('business')->insertGetId($info);
				$uinfo['aid'] = aid;
				$uinfo['bid'] = $bid;
                $uinfo['mid'] = mid;
				$uinfo['auth_type'] = 1;
				$uinfo['pwd'] = md5($uinfo['pwd']);
				$uinfo['createtime'] = time();
				$uinfo['isadmin'] = 1;
				$uinfo['random_str'] = random(16);
				$id = Db::name('admin_user')->insertGetId($uinfo);
				//自定义表单start
				if(getcustom('business_apply_form')){
					$res = $this->customRegister($formdata['customformdata'],$formdata['customformid'],$bid);
					if($res['status']!=1){
						return $this->json(['status'=>0,'msg'=>$res['msg']]);
					}else if($res['status']==1 && isset($res['recordid']) && $res['recordid']>0){
						$infoa['form_record_id'] = $res['recordid'];
						Db::name('business')->where('aid',aid)->where('mid',mid)->where('id',$bid)->update($infoa);
					}
				}
				//返利比例
				if(getcustom('business_apply_queue_free_rate_back') && $hasyxqueuefree == 1){
					if($bid){
						$free_set = Db::name('queue_free_set')->where('aid',aid)->where('bid',$bid)->find();
						$queue_free_set['rate_back'] = $formdata['rate_back'];
						if($free_set){							
							Db::name('queue_free_set')->where('aid',aid)->where('bid',$bid)->update($queue_free_set);
						}else{
							$queue_free_set['aid'] = aid;
							$queue_free_set['bid'] = $bid;
							$queue_free_set['rate_status_business'] = -1;
							$queue_free_set['money_max'] = null;
							$queue_free_set['status'] = 0;
							$queue_free_set['createtime'] = time();
							Db::name('queue_free_set')->insertGetId($queue_free_set);
						}						
					}			
				}
                if(getcustom('business_deposit')){
                    if($bset['deposit'] > 0){
                        //生成保证金订单，跳转支付
                        $ordernum = date('ymdHis').aid.rand(1000,9999);
                        $money = $bset['deposit'];
                        $orderdata = [];
                        $orderdata['aid'] = aid;
                        $orderdata['mid'] = mid;
                        $orderdata['bid'] = $bid;
                        $orderdata['createtime']= time();
                        $orderdata['money'] = $money;
                        $orderdata['ordernum'] = $ordernum;
                        $orderid = Db::name('business_deposit_order')->insertGetId($orderdata);
                        $payorderid = \app\models\Payorder::createorder(aid,0,$orderdata['mid'],'business_deposit',$orderid,$ordernum,'商家入驻保证金充值',$money);

                        return $this->json(['status'=>-3,'msg'=>'提交成功，请支付保证金，等待审核','url'=>'/pagesExt/pay/pay?id='.$payorderid,'orderid'=>$orderid,'payorderid'=>$payorderid]);
                    }
                }
			}
			return $this->json(['status'=>1,'msg'=>'提交成功，请等待审核']);
		}

		
		$info = Db::name('business')->where('aid',aid)->where('mid',mid)->find();
		if($info && $info['status']==1){
			return $this->json(['status'=>2,'msg'=>'您已成功入驻']); 
		}
        if($info && $info['logo']){
            $info['pic'] = $info['logo'];
        }
		$clist = Db::name('business_category')->where('aid',aid)->where('status',1)->order('sort desc,id')->select()->toArray();
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
		if(getcustom('business_apply_form')){
			
				$formField = Db::name('business_apply_form')->where('aid',aid)->find();
				if($formField && $formField['content']){
					$custom_content = json_decode($formField['content'],true);
					foreach ($custom_content as &$cc) {
						if($cc['val4'] == 0){
							$cc['input_type']='text';
						}
						if($cc['val4'] == 1){
							$cc['input_type']='digit';
						}
						if($cc['val4'] == 2){
							$cc['input_type']='tel';
						}
						if($cc['val4'] == 3){
							$cc['input_type']='idcard';
						}
						if($cc['val4'] == 4){
							$cc['input_type']='email';
						}
					}
					$formField['content']  = $custom_content;
					$hasCustom = 1;

					if($info['form_record_id']>0){
						$register_record = Db::name('business_apply_form_record')->where('id', $info['form_record_id'])->find();
						if($register_record){
							foreach ($custom_content as $k=>$item) {										
								$formvaldata['form'.$k] = $register_record['form'.$k]??'';
								//$register_forms[$k] = $item;
								if($item['key']=='checkbox'){
									if($register_record['form'.$k]){
										$register_forms[$k] = explode(',',$register_record['form'.$k]);		
									}else{
										$register_forms[$k] = [];		
									}
									
								}elseif($item['key']=='selector'){
									$register_forms[$k] = '';
									foreach($item['val2'] as $k2=>$v2){
										if($v2 == $register_record['form'.$k]){
											$register_forms[$k] = $k2;
										}
									}
																		
								}else{
									$register_forms[$k] = $register_record['form'.$k]??'';		
								}
															
							}
						}
					}
				}else{
					$formField = [];
				}
			
		}
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
	
	//商品搜索
	public function search(){
		$bid = input('param.bid/d');
		//分类
		if(input('param.cid')){
			$clist = Db::name('business_shop_category')->where('aid',aid)->where('bid',$bid)->where('pid',input('param.cid/d'))->where('status',1)->order('sort desc,id')->select()->toArray();
			if(!$clist) $clist = [];
		}else{
			$clist = Db::name('business_shop_category')->where('aid',aid)->where('bid',$bid)->where('pid',0)->where('status',1)->order('sort desc,id')->select()->toArray();
			if(!$clist) $clist = [];
		}
		//分组
		//$glist = Db::name('shop_group')->where('aid',aid)->where('status',1)->select()->toArray();
		//if(!$glist) $glist = [];

		$productlisttype = cookie('productlisttype');
		if(!$productlisttype) $productlisttype = 'item2';
		
		$rdata = [];
		$rdata['clist'] = $clist;
		$rdata['glist'] = [];
		$rdata['productlisttype'] = $productlisttype;
		return $this->json($rdata);
	}
	//商品列表
	public function prolist(){
		$bid = input('param.bid/d');
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['bid','=',$bid];
		$nowtime = time();
		$nowhm = date('H:i');
		$where[] = Db::raw("`status`=1 or (`status`=2 and unix_timestamp(start_time)<=$nowtime and unix_timestamp(end_time)>=$nowtime) or (`status`=3 and ((start_hours<end_hours and start_hours<='$nowhm' and end_hours>='$nowhm') or (start_hours>=end_hours and (start_hours<='$nowhm' or end_hours>='$nowhm'))) )");

		if(input('param.field') && input('param.order')){
			$order = input('param.field').' '.input('param.order').',sort,id desc';
		}else{
			$order = 'sort desc,id desc';
		}
		//分类 
		if(input('param.cid')){
			$cid = input('param.cid/d');
			$where[] = ['bcid','=',$cid];
			//子分类
			$clist = Db::name('business_shop_category')->where('aid',aid)->where('bid',$bid)->where('pid',$cid)->select()->toArray();
			if($clist){
				$cateArr = [$cid];
				foreach($clist as $c){
					$cateArr[] = $c['id'];
				}
				$where[] = ['bcid','in',$cateArr];
			}
		}
		if(input('param.keyword')){
			$where[] = ['name','like','%'.input('param.keyword').'%'];
		}
		if(input('param.groupid')) $where[] = Db::raw("find_in_set(".intval(input('param.groupid')).",gid)");
		if(input('param.gid')) $where[] = Db::raw("find_in_set(".intval(input('param.gid')).",gid)");
		$pernum = 10;
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$datalist = Db::name('shop_product')->field("id,pic,name,sales,market_price,sell_price,lvprice,lvprice_data,sellpoint,fuwupoint")->where($where)->page($pagenum,$pernum)->order($order)->select()->toArray();
		if(!$datalist) $datalist = array();
		$datalist = $this->formatprolist($datalist);
		if(request()->isPost()){
			return $this->json(['status'=>1,'data'=>$datalist]);
		}
		
		//分类
		if(input('param.cid')){
			$clist = Db::name('business_shop_category')->where('aid',aid)->where('bid',$bid)->where('pid',input('param.cid/d'))->where('status',1)->order('sort desc,id')->select()->toArray();
			if(!$clist) $clist = [];
		}else{
			$clist = Db::name('business_shop_category')->where('aid',aid)->where('bid',$bid)->where('pid',0)->where('status',1)->order('sort desc,id')->select()->toArray();
			if(!$clist) $clist = [];
		}

		$productlisttype = cookie('productlisttype');
		if(!$productlisttype) $productlisttype = 'item2';
		
		$rdata = [];
		$rdata['clist'] = $clist;
		$rdata['glist'] = [];
		$rdata['datalist'] = $datalist;
		$rdata['productlisttype'] = $productlisttype;
		
		return $this->json($rdata);
	}
	//分类商品
	public function classify(){
		$order = 'sort desc,id desc';
		if(input('param.field') && input('param.order')){
			$order = input('param.field').' '.input('param.order').',sort,id desc';
		}else{
			$order = 'sort desc,id desc';
		}
		$bid = input('param.bid/d');
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['bid','=',$bid];
		//$where[] = ['status','=',1];
		$nowtime = time();
		$nowhm = date('H:i');
		$where[] = Db::raw("`status`=1 or (`status`=2 and unix_timestamp(start_time)<=$nowtime and unix_timestamp(end_time)>=$nowtime) or (`status`=3 and ((start_hours<end_hours and start_hours<='$nowhm' and end_hours>='$nowhm') or (start_hours>=end_hours and (start_hours<='$nowhm' or end_hours>='$nowhm'))) )");

		$cid = input('param.cid');
		
		$clist = Db::name('business_shop_category')->where('aid',aid)->where('bid',$bid)->where('pid',0)->where('status',1)->order('sort desc,id')->select()->toArray();
		foreach($clist as $k=>$v){
			$rs = Db::name('business_shop_category')->where('aid',aid)->where('bid',$bid)->where('pid',$v['id'])->where('status',1)->order('sort desc,id')->select()->toArray();
			if(!$rs) $rs = [];
			$clist[$k]['child'] = $rs;
		}
		//if(!$cid) $cid = $clist[0]['id'];
		//分类 
		if($cid){
			$where[] = ['','=',intval($cid)];
			$title = Db::name('business_shop_category')->where('aid',aid)->where('bid',$bid)->where('id',$where['bcid'])->order('sort desc,id')->value('name');
			//子分类
			$child = Db::name('business_shop_category')->where('aid',aid)->where('bid',$bid)->where('pid',$where['bcid'])->select()->toArray();
			if($child){
				$cateArr = [$where['bcid']];
				foreach($child as $c){
					$cateArr[] = $c['id'];
				}
				$where[] = ['bcid','in',$cateArr];
			}
		}
		if(input('param.keyword')){
			$where[] = ['name','like','%'.input('param.keyword').'%'];
		}
		if(input('param.groupid')) $where[] = Db::raw("find_in_set(".intval(input('param.groupid')).",gid)");
		$pernum = 12;
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$datalist = Db::name('shop_product')->field("pic,id,name,sales,market_price,sell_price,lvprice,lvprice_data,sellpoint,fuwupoint")->where($where)->order($order)->page($pagenum,$pernum)->select()->toArray();
		if(!$datalist) $datalist = array();
		$datalist = $this->formatprolist($datalist);
		if(request()->isPost()){
			return $this->json(['status'=>1,'data'=>$datalist]);
		}

		$rdata = [];
		$rdata['clist'] = $clist;
		$rdata['datalist'] = $datalist;
		return $this->json($rdata);
	}
	//获取子分类
	public function getdownclist(){
		$pid = input('param.id/d');
		$clist = Db::name('business_shop_category')->where('aid',aid)->where('pid',$pid)->where('status',1)->order('sort desc,id')->select()->toArray();
		if(!$clist) $clist = [];
		return $this->json(['status'=>1,'data'=>$clist]);
	}
	
	//商品评价
	public function commentlist(){
		$bid = input('param.bid/d');
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$pernum = 20;
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['bid','=',$bid];
		$where[] = ['status','=',1];
		$datalist = Db::name('business_comment')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		if(!$datalist) $datalist = [];
		foreach($datalist as $k=>$pl){
			$datalist[$k]['createtime'] = date('Y-m-d H:i',$pl['createtime']);
			if($datalist[$k]['content_pic']) $datalist[$k]['content_pic'] = explode(',',$datalist[$k]['content_pic']);
		}
		
		$business = Db::name('business')->field('id,name,logo,desc,comment_num,comment_score,comment_haopercent')->where('aid',aid)->where('id',$bid)->where('status',1)->find();
		if(!$business) return $this->json(['status'=>0,'msg'=>'商家信息不存在']);

		$rdata = [];
		$rdata['data'] = $datalist;
		$rdata['business'] = $business;
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

	protected function customRegister($formdata=[],$formid=0,$bid){
        if(getcustom('business_apply_form')){
            $form = Db::name('business_apply_form')->where('aid',aid)->find();
            if(empty($form) || empty($form['content'])){
                return ['status'=>1,'msg'=>''];
            }
            if($form['id']!=$formid){
                return ['status'=>0,'msg'=>'表单数据有误！'.$formid];
            }
            $formheader = [];
            if(empty($formdata)) $formdata = [];
            $data = [];
            $formcontent = json_decode($form['content'],true);
            foreach($formcontent as $k=>$v){
                $value = $formdata['form'.$k];
                if(is_array($value)){
                    $value = implode(',',$value);
                }
                if($v['key']=='switch'){
                    if($value){
                        $value = '是';
                    }else{
                        $value = '否';
                    }
                }
                $data['form'.$k] = strval($value);
                if($v['val3']==1 && $data['form'.$k]===''){
                    return ['status'=>0,'msg'=>$v['val1'].' 必填'.$data['form'.$k]];
                }
                if($v['key'] == 'usercard' && !checkIdCard($value)){
                    return ['status'=>0, 'msg'=>'请输入正确的身份证号'];
                }
                
            }
			$form_record = Db::name("business_apply_form_record")->where('aid', aid)->where('bid',$bid)
                ->find();
			
            if($data){				
                $data['aid'] = aid;
                $data['formid'] = $formid;
                $data['content'] = $form['content'];                
                $data['bid'] = $bid;
				if($form_record){
					Db::name('business_apply_form_record')->where('aid',aid)->where('id',$form_record['id'])->update($data);
					$recordid = $form_record['id'];
				}else{
					$data['createtime'] = time();
					$recordid = Db::name('business_apply_form_record')->insertGetId($data);
				}
                
                return ['status'=>1,'msg'=>'自定义表单保存成功','recordid'=>$recordid];
            }else{
                return ['status'=>1,'msg'=>''];
            }
        }
        return ['status'=>1,'msg'=>''];
    }

}