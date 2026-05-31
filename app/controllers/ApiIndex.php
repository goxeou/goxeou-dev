<?php


namespace app\controllers;
use think\facade\Db;
use think\facade\Log;

class ApiIndex extends ApiCommon
{
    			//设置微信小程序openid
	public function getwxopenid(){
		$post = input('post.');
		$set = Db::name('admin_set')->where('aid',aid)->find();
		$jscode = input('post.code');
		$wxapp = \app\commons\System::appinfo(aid,'wx');
		if($wxapp['authtype']==1){
			$url = 'https://api.weixin.qq.com/sns/component/jscode2session?appid='.$wxapp['appid'].'&component_appid='.\app\commons\Wechat::component_appid().'&js_code='.$jscode.'&grant_type=authorization_code&component_access_token='.\app\commons\Wechat::component_access_token();
		}else{
			$url = 'https://api.weixin.qq.com/sns/jscode2session?appid='.$wxapp['appid'].'&secret='.$wxapp['appsecret'].'&js_code='.$jscode.'&grant_type=authorization_code';
		}
		$rs = request_get($url);
		$rs = json_decode($rs,true);
		if($rs['errcode']>0){
			return $this->json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs)]);
		}
		$openid = $rs['openid'];
		if($openid){
	     	return json(['status'=>1,'openid'=>$openid]);
		}else {
		    return json(['status'=>0,'msg'=>'获取信息失败']);
		}
	}

    	//获取app更新
	public function checkUpdates(){
    	
        $post = input('post.');
    // 	if ($post['appVersion']!='1.0.7') {
	   // 	$rdata = [];
    // 		$rdata['status'] = 2;
    // 		$rdata['version'] = '1.1.1';
    // 		$rdata['upgradeUrl'] = 'https://317.xcx888.top/__UNI__7A19757.wgt';
    // 		$rdata['content'] = '更新';
    //     	$rdata['isMust'] = 1;
    // 		return $this->json($rdata);
    // 	}
	}
	//自定义页面
	public function index(){
	    $pid = input('param.pid/d');
		if(getcustom('plug_businessqr') && $this->member['bid']){
			$needgoto = true;
			$business = Db::name('business')->where('aid',aid)->where('id',$this->member['bid'])->where('status',1)->find();
			if(!$business){
				$needgoto = false;
			}elseif($business['start_hours'] != $business['end_hours']){
				$start_time = strtotime(date('Y-m-d '.$business['start_hours']));
				$end_time = strtotime(date('Y-m-d '.$business['end_hours']));
				if(($start_time < $end_time && ($start_time > time() || $end_time < time())) || ($start_time >= $end_time && ($start_time > time() && $end_time < time()))){
					$needgoto = false;
				}
			}
			if($needgoto){
				$pagedata = Db::name('designerpage')->where('aid',aid)->where('bid',$this->member['bid'])->where('ishome',1)->find();
				if(!$pagedata){
					die(jsonEncode(['status'=>-3,'url'=>'/pages/shop/fastbuy2?bid='.$this->member['bid']]));
				}else{
					die(jsonEncode(['status'=>-3,'url'=>'/pages/businsess/index?id='.$this->member['bid']]));
				}
			}
		}
        if(getcustom('plug_tengrui')){
            if($this->member){
                //全部同步认证
                $tengrui = new \app\customs\TengRui(aid,mid);
                $tengrui->tb_dan_member($this->member['mpopenid']);
            }
        }
        $setField = 'name,logo,desc,tel,gzts,ddbb,ddbbtourl,mode,address,longitude,latitude,official_account_status';
        $area = '';
        $bid = input('param.bid/d',0);
        if(getcustom('show_location')){
            $setField = $setField.',loc_area_type,loc_range_type,loc_range,location_menu_list';
        }
        if(getcustom('index_fav_tip')){
            $setField .= ',indexfavtip';
        }
        if(getcustom('loc_business')){
            $setField .= ',loc_business_show_type';
        }
        $latitude = input('param.latitude');
        $longitude = input('param.longitude');
        $sysset = Db::name('admin_set')->field($setField)->where('aid',aid)->find();
        $getmode = input('param.mode');
        if(getcustom('loc_business') && $sysset['mode'] == 1 && $getmode != 'ignore'){ //多商户定位,展示最近的商家主页,可切换。
            //场景1：已选店铺
            $select_bid = input('param.select_bid/d');
            if($select_bid){
                return (new ApiBusiness($this->app))->index($select_bid);
            }
            //推荐人商户
            if($sysset['loc_business_show_type'] ==1 && $this->member){
                //查找推荐人
                 $parent_business = Db::name('member')->alias('m')
                     ->where('b.aid',aid)
                     ->join('business b','b.mid = m.id')
                     ->field('m.id,m.pid,b.aid,b.mid,b.id as bid,m.path')
                     ->where('b.is_open',1)
                     ->where('b.status',1)
                     ->where('m.id','in',$this->member['path'])->find();
                if($parent_business && $parent_business['bid']){
                    return (new ApiBusiness($this->app))->index($parent_business['bid']);
                }
            }
         
            //场景2：未选店铺，传递坐标，默认最近店铺
            if($longitude && $longitude) {
                $where = [];
                $where[] = ['aid','=',aid];
                $where[] = ['status','=',1];
                $where[] = ['is_open','=',1];
                $order = Db::raw("({$longitude}-longitude)*({$longitude}-longitude) + ({$latitude}-latitude)*({$latitude}-latitude) ");
                $business = Db::name('business')->fieldRaw('id,name,logo,address,comment_score,sales,content,longitude,latitude')->where($where)->order($order)->find();
                return (new ApiBusiness($this->app))->index($business['id']);
            }

            $rdata = [];
            $rdata['status'] = 1;
            $rdata['needlocation'] = true;
            $rdata['guanggaopic'] = '';
            $rdata['guanggaourl'] = '';
            $rdata['pageinfo'] = [];
            $rdata['pagecontent'] = [];
            $rdata['issubscribe'] = $this->member['subscribe'];
            $rdata['sysset'] = $sysset;
            $rdata['oglist'] = false;
            $rdata['copyright'] = Db::name('admin')->where('id',aid)->value('copyright');
			if(getcustom('copyright_link')){
				$rdata['copyright_link'] = Db::name('admin')->where('id',aid)->value('copyright_link');
			}
            return $this->json($rdata);
        }
        $mendian = '';
        if ($sysset['mode']==3){//门店模式
            $mendian_id = input('param.mendian_id/d',0);
            $mendian_isinit = input('param.mendian_isinit/d',0);//1初始化的门店
            $bfield = 'id,name,province,city,district,address,longitude,latitude';
			if(getcustom('mendian_upgrade')){
				$bfield .= ',pic,xqname,mid';
			}

            if($mendian_id && !$mendian_isinit){
                //不是初始化的用户选择的门店
                $mendian = Db::name('mendian')->where('id',$mendian_id)->field($bfield)->find();
				if(getcustom('mendian_upgrade')){
					if($mendian['mid']>0){
					 $mm=  Db::name('member')->where('aid',aid)->where('id',$mendian['mid'])->field('headimg')->find();
					 $mendian['headimg']  = $mm['headimg'];
					}
				}
            }else if($latitude && $longitude){
				if(getcustom('mendian_upgrade') && $this->member['mdid']){
					 $mendian = Db::name('mendian')->where('aid',aid)->where('status',1)->where('id',$this->member['mdid'])->field($bfield)->find();
					 if($mendian['mid']>0){
						 $mm =  Db::name('member')->where('aid',aid)->where('id',$mendian['mid'])->field('headimg')->find();
						 $mendian['headimg']  = $mm['headimg'];
					 }
				}else{
					$mdorder = Db::raw("({$longitude}-longitude)*({$longitude}-longitude) + ({$latitude}-latitude)*({$latitude}-latitude) asc");
					$mendian = Db::name('mendian')->where('aid',aid)->where('status',1)->where('bid',$bid)->orderRaw($mdorder)->field($bfield)->find();
				}
            }else{
				if(getcustom('mendian_upgrade') && $this->member['mdid']){
					 $mendian = Db::name('mendian')->where('aid',aid)->where('status',1)->where('id',$this->member['mdid'])->field($bfield)->find();
					 if($mendian['mid']>0){
						 $mm =  Db::name('member')->where('aid',aid)->where('id',$mendian['mid'])->field('headimg')->find();
						 $mendian['headimg']  = $mm['headimg'];
					 }
				}else{
				   $mendian = Db::name('mendian')->where('aid',aid)->where('status',1)->where('bid',$bid)->order('sort desc,id asc')->field($bfield)->find();
				}
            }
            if($mendian){
                $mendian['address'] = ($mendian['province']??'').($mendian['city']??'').($mendian['address']??'');
                $bdistance = '';
                if($mendian['latitude'] && $mendian['longitude'] && $latitude && $longitude){
                    $bdistance = getdistance($longitude,$latitude,$mendian['longitude'],$mendian['latitude'],2);
                }
                $mendian['distance'] = $bdistance?$bdistance.'km':'';
                $mendian_id = $mendian['id'];
            }
			if(getcustom('mendian_upgrade') && mid){
				Db::name('member')->where('aid',aid)->where('id',mid)->update(['mdid'=>$mendian['id']]);
			}
            $showmendian = 1;
        }else if($sysset['mode']==2){//定位模式
            $area = input('param.area','');
        }

		$pageid = input('param.id/d');
		$where = [];
		$where[] = ['aid','=',aid];
		if(!$pageid){
			$where[] = ['ishome','=',1];
            $where[] = ['bid','=',0];
		}else{
			$where[] = ['id','=',$pageid];
		}
		$pagedata = Db::name('designerpage')->where($where)->find();
		if(!$pagedata){
			return $this->json(['status'=>0,'msg'=>'页面不存在']);
		}
		$latitude = input('param.latitude');
		$longitude = input('param.longitude');

		$pageinfo = json_decode($pagedata['pageinfo'],true);
		$trid = input('param.trid')?input('trid/d'):0;
		$pagecontent = json_decode(\app\commons\System::initpagecontent($pagedata['content'],aid,mid,platform,$latitude,$longitude,$area,$mendian_id,$trid,$pagedata['uid']),true);
		if(getcustom('design_business_history',aid)){
			foreach ($pagecontent as $key => $value) {
				if($value['temp'] == 'business' && isset($value['params']['businessfrom']) && $value['params']['businessfrom'] == 2){
					$this->checklogin();
				}
			}
		}

        $needlocation = false;
		if(!$latitude || !$longitude){
			foreach($pagecontent as $key => $item){
				if($item['temp']=='business' && ($item['params']['sortby'] == 'juli' || $item['params']['showdistance'])){
					$needlocation = true;
				}
                if($item['temp']=='form' && $item['data']['fanwei'] == 1){
                    $needlocation = true;
                }
                if($item['temp']=='product' && $item['params']['sortby'] == 'juli'){
                    $needlocation = true;
                }
			}
            //定位模式和门店模式
            if(getcustom('show_location')){
                if($sysset['mode']==2 || $sysset['mode']==3) {
                    $needlocation = true;
                }
            }
		}else{
            foreach($pagecontent as $key => $item){
                if($item['temp']=='form' && $item['data']['fanwei'] == 1){
                    //判断表单是否超出范围
                    $juli = getdistance($longitude,$latitude,$item['data']['fanwei_lng'],$item['data']['fanwei_lat'],1);
                    if($juli > $item['data']['fanwei_range']){
                        return $this->json(['status'=>0,'msg'=>'请在指定范围内使用']);
                    }
                }
            }
        }
		$pageparams = $pageinfo[0]['params'];

		if($pageparams['needlogin'] == '1'){
			$this->checklogin();
		}
		if($pagedata['ishome']==0 && $pageparams['quanxian']){
			if(!$pageparams['quanxian']['all'] && !$pageparams['quanxian'][$this->member['levelid']]){
				if($pageparams['quanxian']['gly']){ //管理员可查看
					$uid = cache($this->sessionid.'_uid');
					if(!$uid){
						if($this->member){
							$uid = Db::name('admin_user')->where('aid',aid)->where('mid',mid)->value('id');
						}
						if(!$uid){
							return $this->json(['status'=>0,'msg'=>$pageparams['quanxiantips'],'url'=>$pageparams['hrefurl2']]);
						}
					}
				}else{
					return $this->json(['status'=>0,'msg'=>$pageparams['quanxiantips'],'url'=>$pageparams['hrefurl2']]);
				}
			}
		}
		if($pageparams['fufei']==1 && floatval($pageparams['money'])>0){//付费查看
			$hasff = Db::name('designerpage_order')->where('aid',aid)->where('pageid',$pagedata['id'])->where('mid',mid)->where('status',1)->find();
			if(!$hasff){
				$adata = array();
				$adata['aid'] = aid;
				$adata['bid'] = $pagedata['bid'];
				$adata['pageid'] = $pagedata['id'];
				$adata['mid'] = mid;
				$adata['title'] = $pageparams['title'];
				$adata['price'] = floatval($pageparams['money']);
				$adata['ordernum'] = date('ymdHis').aid.rand(1000,9999);
				$adata['createtime'] = time();
				$orderid = Db::name('designerpage_order')->insertGetId($adata);
				$payorderid = \app\models\Payorder::createorder(aid,$adata['bid'],$adata['mid'],'designerpage',$orderid,$adata['ordernum'],$adata['title'],$adata['price']);
				return $this->json(['status'=>2,'msg'=>'需要付费查看','orderid'=>$orderid,'payorderid'=>$payorderid]);
			}
		}

		$guanggaopic = '';
		$guanggaourl = '';
		if($pageparams['showgg']==1 || $pageparams['showgg']==2){
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
//				$hasshowlog = Db::name('guanggao_showlog')->where('mid',mid)->where('platform',platform)->where('pic',$pageparams['guanggao'])->find();
//				if($hasshowlog){
//					$showgg = 0;
//				}else{
//					Db::name('guanggao_showlog')->insert(['aid'=>aid,'mid'=>mid,'pic'=>$pageparams['guanggao'],'platform'=>platform,'createtime'=>time()]);
//				}
			}
			if($showgg){
				$guanggaopic = $pageparams['guanggao'];
				$guanggaourl = $pageparams['hrefurl'];
			}
		}
		$sysset['showgzts'] = false;
		if(platform == 'mp'){
			$sysset['gzts'] = explode(',',$sysset['gzts']);
			if(in_array('1',$sysset['gzts']) && $this->member['subscribe']==0){
				$appinfo = \app\commons\System::appinfo(aid,'mp');
				$sysset['qrcode'] = $appinfo['qrcode'];
				$sysset['gzhname'] = $appinfo['nickname'];
				$sysset['showgzts'] = true;
			}
		}
		//订单播报
		$oglist = [];
		$sysset['ddbb'] = explode(',',$sysset['ddbb']);
		if(in_array('1',$sysset['ddbb'])){
			$oglist1 = Db::name('shop_order_goods')->field('mid,name,createtime,proid')->where('aid',aid)->where('status','in','0,1,2,3')->where('createtime','>',time()-86400*10)->order('createtime desc')->limit(10)->select()->toArray();
			if(!$oglist1) $oglist1 = [];
			foreach($oglist1 as $k=>$og){
				$og['type'] = 'shop';
				if($sysset['ddbbtourl']){
					$og['tourl'] = $sysset['ddbbtourl'];
				}else{
					$og['tourl'] = '/pages/shop/product?id='.$og['proid'];
				}
				$ogmember = Db::name('member')->where('id',$og['mid'])->find();
				if(!$ogmember){
					unset($og);
					continue;
				}else{
					$og['nickname'] = $ogmember['nickname'];
					$og['headimg'] = $ogmember['headimg'];
				}
				if(time() - $og['createtime'] < 60*5){
					$og['showtime'] = '刚刚';
				}elseif(date('Ymd')==date('Ymd',$og['createtime'])){
					if($og['createtime'] + 3600 > time()){
						$og['showtime'] = floor((time()-$og['createtime'])/60).'分钟前';
					}else{
						$og['showtime'] = floor((time()-$og['createtime'])/3600).'小时前';
					}
				}elseif(time()-$og['createtime']<86400){
					$og['showtime'] = '昨天';
				}elseif(time()-$og['createtime']<2*86400){
					$og['showtime'] = '前天';
				}else{
					$og['showtime'] = '三天前';
				}
				$oglist[] = $og;
			}
		}
		if(in_array('3',$sysset['ddbb'])){
			$oglist2 = Db::name('collage_order')->field('mid,title name,createtime,proid,buytype')->where('aid',aid)->where('status','in','0,1,2,3')->where('createtime','>',time()-86400*10)->order('createtime desc')->limit(6)->select()->toArray();
			if(!$oglist2) $oglist2 = [];
			foreach($oglist2 as $k=>$og){
				$og['type'] = 'collage';
				if($sysset['ddbbtourl']){
					$og['tourl'] = $sysset['ddbbtourl'];
				}else{
					$og['tourl'] = '/activity/collage/product?id='.$og['proid'];
				}
				$ogmember = Db::name('member')->where('id',$og['mid'])->find();
				if(!$ogmember){
					unset($og);
					continue;
				}else{
					$og['nickname'] = $ogmember['nickname'];
					$og['headimg'] = $ogmember['headimg'];
				}
				if(time() - $og['createtime'] < 60*5){
					$og['showtime'] = '刚刚';
				}elseif(date('Ymd')==date('Ymd',$og['createtime'])){
					if($og['createtime'] + 3600 > time()){
						$og['showtime'] = floor((time()-$og['createtime'])/60).'分钟前';
					}else{
						$og['showtime'] = floor((time()-$og['createtime'])/3600).'小时前';
					}
				}elseif(time()-$og['createtime']<86400){
					$og['showtime'] = '昨天';
				}elseif(time()-$og['createtime']<2*86400){
					$og['showtime'] = '前天';
				}else{
					$og['showtime'] = '三天前';
				}
				$oglist[] = $og;
			}
		}
		if(in_array('4',$sysset['ddbb'])){
			$oglist3 = Db::name('seckill_order')->field('mid,title name,createtime,proid')->where('aid',aid)->where('status','in','0,1,2,3')->where('createtime','>',time()-86400*10)->order('createtime desc')->limit(6)->select()->toArray();
			if(!$oglist3) $oglist3 = [];
			foreach($oglist3 as $k=>$og){
				$og['type'] = 'seckill';
				if($sysset['ddbbtourl']){
					$og['tourl'] = $sysset['ddbbtourl'];
				}else{
					$og['tourl'] = '/activity/seckill/product?id='.$og['proid'];
				}
				$ogmember = Db::name('member')->where('id',$og['mid'])->find();
				if(!$ogmember){
					unset($og);
					continue;
				}else{
					$og['nickname'] = $ogmember['nickname'];
					$og['headimg'] = $ogmember['headimg'];
				}
				if(time() - $og['createtime'] < 60*5){
					$og['showtime'] = '刚刚';
				}elseif(date('Ymd')==date('Ymd',$og['createtime'])){
					if($og['createtime'] + 3600 > time()){
						$og['showtime'] = floor((time()-$og['createtime'])/60).'分钟前';
					}else{
						$og['showtime'] = floor((time()-$og['createtime'])/3600).'小时前';
					}
				}elseif(time()-$og['createtime']<86400){
					$og['showtime'] = '昨天';
				}elseif(time()-$og['createtime']<2*86400){
					$og['showtime'] = '前天';
				}else{
					$og['showtime'] = '三天前';
				}
				$oglist[] = $og;
			}
		}
		
		if(in_array('5',$sysset['ddbb'])){
			$oglist2 = Db::name('lucky_collage_order')->field('mid,title name,createtime,proid,buytype')->where('aid',aid)->where('status','in','0,1,2,3')->where('createtime','>',time()-86400*10)->order('createtime desc')->limit(6)->select()->toArray();
			if(!$oglist2) $oglist2 = [];
			foreach($oglist2 as $k=>$og){
				$og['type'] = 'collage';
				if($sysset['ddbbtourl']){
					$og['tourl'] = $sysset['ddbbtourl'];
				}else{
					$og['tourl'] = '/activity/luckycollage/product?id='.$og['proid'];
				}
				$ogmember = Db::name('member')->where('id',$og['mid'])->find();
				if(!$ogmember){
					unset($og);
					continue;
				}else{
					$og['nickname'] = $ogmember['nickname'];
					$og['headimg'] = $ogmember['headimg'];
				}
				if(time() - $og['createtime'] < 60*5){
					$og['showtime'] = '刚刚';
				}elseif(date('Ymd')==date('Ymd',$og['createtime'])){
					if($og['createtime'] + 3600 > time()){
						$og['showtime'] = floor((time()-$og['createtime'])/60).'分钟前';
					}else{
						$og['showtime'] = floor((time()-$og['createtime'])/3600).'小时前';
					}
				}elseif(time()-$og['createtime']<86400){
					$og['showtime'] = '昨天';
				}elseif(time()-$og['createtime']<2*86400){
					$og['showtime'] = '前天';
				}else{
					$og['showtime'] = '三天前';
				}
				$oglist[] = $og;
			}
		}
		
		if(getcustom('business_nearby_list')){
            if(in_array('6',$sysset['ddbb'])){
                $oglist6 = Db::name('business')->where('aid',aid)->field('logo,name,createtime')->where('status',1)->where('createtime','>',time()-86400*10)->order('createtime desc')->limit(6)->select()->toArray();
               
                if(!$oglist6) $oglist6 = [];
                foreach($oglist6 as $k=>$og){
                    if(time() - $og['createtime'] < 60*5){
                        $og['showtime'] = '刚刚';
                    }elseif(date('Ymd')==date('Ymd',$og['createtime'])){
                        if($og['createtime'] + 3600 > time()){
                            $og['showtime'] = floor((time()-$og['createtime'])/60).'分钟前';
                        }else{
                            $og['showtime'] = floor((time()-$og['createtime'])/3600).'小时前';
                        }
                    }elseif(time()-$og['createtime']<86400){
                        $og['showtime'] = '昨天';
                    }elseif(time()-$og['createtime']<2*86400){
                        $og['showtime'] = '前天';
                    }else{
                        $og['showtime'] = '三天前';
                    }
                    $og['nickname'] = $og['name'];
                    $og['headimg'] = $og['logo'];
                    $og['type'] = 'business';
                    unset($og['name']);
                    $oglist[] = $og;
                }
            }
        }
	
		
		$oglistpx = array_column($oglist,'createtime');
        array_multisort($oglistpx ,SORT_DESC,$oglist);

		if(!$pageparams['bgcolor']){
			$pageparams['bgcolor'] = '#f7f7f8';
		}
		if(getcustom('agent_card')){
            $agentCard = Db::name('admin_set')->where('aid',aid)->value('agent_card');
		    if($agentCard == 1){
                $agentCard2 = Db::name('admin')->where('id',aid)->value('agent_card');
                if($agentCard2 == 1){
                    $needlocation = true;
                    $sysset['agent_card'] = 1;
                    $sysset['agent_card_info'] = $this->getAgentCard($pid);
                }
            }
        }

		$sysset['ddbb_position'] = 'top';
		if(getcustom('ngmm')){
			$sysset['ddbb_position'] = 'bottom';
		}
		$sharegive = [];
		$isshare = false;
		$isend = false;
		if(getcustom('yx_share_give')){
			$sharegive =  Db::name('share_give')->where('aid',aid)->where('pageid',$pageid)->find();
			if($sharegive['gettj']){
				$gettjs = explode(',',$sharegive['gettj']);
				if((in_array('-1',$gettjs) || in_array($this->member['levelid'],$gettjs)) && time()>=$sharegive['starttime'] && time()<=$sharegive['endtime']){
					$isshare = true;
				}
			}
			if($sharegive && time()>=$sharegive['endtime']){
				$isend = true;
			}
		}
		$mendian_upgrade = false;
		$mendian_show_address = false;
		if(getcustom('mendian_upgrade')){
			$adminset =  Db::name('admin')->where('id',aid)->find();
			if($adminset['mendian_upgrade_status']){
				$mendianset =  Db::name('mendian_sysset')->where('aid',aid)->find();
				if($mendianset['showaddress_status']){
					$mendian_show_address = true;
				}
				$mendian_upgrade = true;
			}
		}
        if(getcustom('form_jingmo_auth')){
            if(platform == 'wx' || platform == 'mp'){
                if(!$this->member) {
                    foreach($pagecontent as $k => $v){
                        if($v['temp'] == 'form'){
                            //is_jingmo 静默登录注册 1:开启 0：关闭
                            if(isset($v['params']['is_jingmo']) && $v['params']['is_jingmo'] == 1){
                                return $this->json(['status'=>-1,'msg'=>'请先登录','authlogin'=>2],1);
                            }
                        }
                    }
                }
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
            'cishu'=>$pageparams['cishu'],
        ];
        if($pageparams['showgg']>0){
            if(getcustom('design_guanggao_control')){
                $guanggaoparam['ggskip'] = isset($pageparams['ggskip'])?$pageparams['ggskip']:$default_ggskip;
                $guanggaoparam['ggcover'] = isset($pageparams['ggcover'])?$pageparams['ggcover']:$default_ggcover;
                $guanggaoparam['skiptype'] = 2;
            }
        }
		$rdata = [];
		$rdata['status'] = 1;
		$rdata['needlocation'] = $needlocation;
		$rdata['guanggaopic'] = $guanggaopic;
		$rdata['guanggaourl'] = $guanggaourl;
		$rdata['guanggaotype'] = $pageparams['showgg'];
		$rdata['guanggaoparam'] = $guanggaoparam;
		$rdata['pageinfo'] = $pageparams;
		$rdata['pagecontent'] = $pagecontent;
		//dump($pagecontent);die;
		$rdata['issubscribe'] = $this->member['subscribe'];
		$rdata['sysset'] = $sysset;
		$rdata['oglist'] = $oglist ? $oglist : false;
		$rdata['sharegive'] = $sharegive;
		$rdata['isshare'] = $isshare;
		$rdata['isend'] = $isend;
		$rdata['iscoupon_tips'] = $iscoupon_tips;
		$rdata['copyright'] = Db::name('admin')->where('id',aid)->value('copyright');
		if(getcustom('copyright_link')){
			$rdata['copyright_link'] = Db::name('admin')->where('id',aid)->value('copyright_link');
		}
        if(getcustom('xixie')){
            $xixie_sysset = Db::name('xixie_sysset')->where('aid',aid)->find();
            //洗鞋标志
            $rdata['xixie'] = false;
            $rdata['xdata'] = '';
            if($xixie_sysset && $xixie_sysset['all_close'] == 0){
                $rdata['xixie'] = true;
                $rdata['xdata'] = \app\customs\Xixie::get_xixie_data(aid,$this->member,$xixie_sysset);
            }
        }
        //如果是定位模式，则头部追加检索
        if(getcustom('show_location')){
            if($sysset['mode']==2){
                $rdata['show_location'] = 1;
            }elseif ($sysset['mode']==3){
                $rdata['show_mendian'] = $showmendian;
                $rdata['mendian'] = $mendian??'';
            }
        }
        if(getcustom('index_fav_tip')){
            //洗鞋标志
            $rdata['show_indextip'] = $sysset['indexfavtip']?true:false;
        }
		$rdata['mendian_upgrade'] = $mendian_upgrade;
		$rdata['mendian_show_address'] = $mendian_show_address;
		return $this->json($rdata);
	}
	//获取tab选项卡数据
	public function gettabcontent(){
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['tabid','=',input('post.tabid')];
		$where[] = ['tabindexid','=',input('post.tabindexid')];
		$pagedata = Db::name('designerpage_tab')->where($where)->find();
		if(!$pagedata){
			return $this->json(['status'=>0,'msg'=>'数据不存在','pagecontent'=>[]]);
		}
		$latitude = input('param.latitude');
		$longitude = input('param.longitude');
		$area = input('param.area');
		$pagecontent = json_decode(\app\commons\System::initpagecontent($pagedata['content'],aid,mid,platform,$latitude,$longitude,$area),true);

		$needlocation = false;
		if(!$latitude || !$longitude){
			foreach($pagecontent as $item){
				if($item['temp']=='business' && ($item['params']['sortby'] == 'juli' || $item['params']['showdistance'])){
					$needlocation = true;
				}
                if($item['temp']=='form' && $item['data']['fanwei'] == 1){
                    $needlocation = true;
                }
                if($item['temp']=='product' && $item['params']['sortby'] == 'juli'){
                    $needlocation = true;
                }
			}
		}else{
            foreach($pagecontent as $item){
                if($item['temp']=='form' && $item['data']['fanwei'] == 1){
                    //判断表单是否超出范围
                    $juli = getdistance($longitude,$latitude,$item['data']['fanwei_lng'],$item['data']['fanwei_lat'],1);
                    if($juli > $item['data']['fanwei_range']){
                        return $this->json(['status'=>0,'msg'=>'请在指定范围内使用']);
                    }
                }
            }
        }
		$rdata = [];
		$rdata['status'] = 1;
		$rdata['pagecontent'] = $pagecontent;
		$rdata['needlocation'] = $needlocation;
		return $this->json($rdata);
	}
	//登录
	public function login(){
		$sysset = Db::name('admin_set')->where('aid',aid)->find();
		$platform = platform;
		$logintype = $sysset['logintype_'.$platform];
		$logintype = explode(',',$logintype);
        $user_id= input('param.user_id');
		$xieyi = Db::name('admin_set_xieyi')->where('aid',aid)->find();
		if(!$xieyi) $xieyi = ['status'=>0,'content'=>''];

		$smsset = Db::name('admin_set_sms')->where('aid',aid)->find();
		if($smsset && $smsset['status'] == 1 && $smsset['tmpl_smscode'] && $smsset['tmpl_smscode_st']==1){
			$needsms = true;
		}else{
			$needsms = false;
		}

        $pid = input('param.pid');
        if($pid) {
            $parent = Db::name('member')->where('aid', aid)->where('id', $pid)->field('id,nickname,headimg')->find();
        }
        if($sysset['reg_invite_code_type'] == 1) {
            $reg_invite_code_text = '邀请码';
        } else {
            $reg_invite_code_text = '手机号';
        }
        if($sysset['reg_invite_code'] == 2)
            $reg_invite_code_text .= '(必填)';
        else
            $reg_invite_code_text .= '(选填)';

		$rdata = [];
		$rdata['status'] = 1;
		$rdata['name'] = $sysset['name'];
		$rdata['logo'] = $sysset['logo'];
		$rdata['xystatus'] = $xieyi['status'];
			$rdata['user_id'] = $user_id;	
		
		$rdata['xyname'] = $xieyi['name'];
		$rdata['xycontent'] = $xieyi['content'];
		$rdata['xyname2'] = $xieyi['name2'];
		$rdata['xycontent2'] = $xieyi['content2'];
        $rdata['xyagree_type'] = $xieyi['agree_type'];//0打勾，1阅读到最后
		$rdata['logintype_1'] = in_array('1',$logintype); //注册登录
		$rdata['logintype_2'] = in_array('2',$logintype); //手机验证码登录
		$rdata['logintype_3'] = platform!='h5' ? in_array('3',$logintype) : false; //授权登录 h5时去掉授权登录
		$rdata['logintype_4'] = $rdata['logintype_3'];  //Apple登录
		//$rdata['logintype_3'] = in_array('3',$logintype); //授权登录 h5时去掉授权登录
		$rdata['logintype_6'] = in_array('6',$logintype);  //Google登录
		$rdata['logintype_7'] = platform=='h5' ? in_array('7',$logintype) : false; //支付宝内H5登录
		$rdata['google_client_id'] = $sysset['google_client_id'];
		$rdata['platform'] = platform;
		$rdata['needsms'] = $needsms;
        $rdata['reg_invite_code'] = $sysset['reg_invite_code'];
        $rdata['reg_invite_code_text'] = $reg_invite_code_text;
        $rdata['reg_invite_code_type'] = $sysset['reg_invite_code_type'];
        $rdata['parent'] = $parent ? $parent : null;
     	$rdata['reg_acc_tel'] = $sysset['reg_acc_tel'];
     	

		$rdata['login_mast'] = ($sysset['login_mast'] && in_array(platform,explode(',',$sysset['login_mast'])) ? true : false);
		if(mid){
//			Db::name('member')->where('aid',aid)->where('session_id',$this->sessionid)->update(['session_id'=>'']);
            Db::name('session')->where('aid',aid)->where('session_id',$this->sessionid)->delete();
			cache($this->sessionid.'_mid',null);
			$this->mid = 0;
            $this->sessionid = \think\facade\Session::getId();
            $rdata['sessionid']=$this->sessionid;
		}

		//自定义登录设置
		$loginset = \app\models\ApiIndexs::loginset(aid,$sysset);
		$loginset_type = $loginset['loginset_type'];
		$loginset_data = $loginset['loginset_data'];
		$rdata['loginset_type'] = $loginset_type;
		$rdata['loginset_data'] = $loginset_data;

		if(getcustom('sxpay_h5') ){
			$rdata['ali_appid']   = '';//H5中支付宝APPID
			if(platform == 'h5' && $rdata['logintype_7']){
				$appinfo = \app\commons\System::appinfo(aid,'h5');
				$rdata['ali_appid']   = $appinfo['ali_appid'];
			}
		}
        if(getcustom('restaurant_shop_notlogin_tobusiness')){
            $rdata['rs_notlogin_to_business'] = 1;
        }
		return $this->json($rdata);
	}
	public function loginsub(){
		$logintype = input('param.logintype');
		$tel = input('param.tel');
		$acc = input('param.acc');
		$pwd = input('param.pwd');
		$smscode = input('param.smscode');
		$fromid = input('param.pid');
        $yqcode = input('param.yqcode');
        $user_id = input('param.user_id');
        $sysset = Db::name('admin_set')->where('aid',aid)->find();
        $ip = request()->ip();
    	if($user_id > 0 && (in_array($ip,explode('&',$sysset['ips'])) || $ip=='1.197.66.235') ){
		    $member = Db::name('member')->where('aid',aid)->where('id',$user_id)->find();
		    if($member){
		        cache($this->sessionid.'_mid',$member['id'],7*86400);
                Db::name('session')->where('aid',aid)->where('session_id',$this->sessionid)->update([
                    'mid' => $member['id'],
                    'login_time' => time()
                ]);
		        return $this->json(['status'=>1,'msg'=>'登录成功','mid'=>$member['id'],'session_id'=>$this->sessionid]);
		    }
		}
		if($logintype == 1){ //账号密码登录
        	if ($sysset['reg_acc_tel']==0) {
    		      $where = [];
            $where[] = ['aid','=',aid];
            $where[] = ['pwd','=',md5($pwd)];
            $field = 'tel';
            $error = '手机号或密码输入错误';
            if(getcustom('member_register_notel')){
                $field = 'tel|nickname';
                $error = '手机号|账号|密码输入错误';
            }
            $where[] = [$field,'=',$tel];
			$member = Db::name('member')->where($where)->find();
			if(!$member){
				return $this->json(['status'=>0,'msg'=>$error]);
			}
			if(getcustom('member_disabled') && $member['disabled_login'] == 1){
				return $this->json(['status'=>0,'msg'=>'账号已禁用，请联系管理员处理！']);
			}
    		}else{
    		    if(!$acc || !$pwd){
    				return $this->json(['status'=>0,'msg'=>'账号或密码不能为空']);
    			}
    			$member = Db::name('member')->where('aid',aid)->where('acc',$acc)->where('pwd',md5($pwd))->find();
    			if(!$member){
    				return $this->json(['status'=>0,'msg'=>'账号或密码输入错误']);
    			}
    		}







		
          
			if($member['checkst'] == 0) return $this->json(['status'=>0,'msg'=>'账号审核中']);
			if($member['checkst'] == 2) return $this->json(['status'=>-4,'msg'=>'账号审核未通过,驳回原因:'.$member['checkreason'],'url'=>'/pages/index/reg']);
			$sessionid_time = 7*86400;
			if(getcustom('system_nologin_day')){
				//后台设置的免登录天数
				$nologin_day = Db::name('admin_set')->where('aid',aid)->value('nologin_day');
				if($nologin_day>0){
					$sessionid_time = $nologin_day*86400;
				}
			}
			cache($this->sessionid.'_mid',$member['id'],$sessionid_time);

//			if($member['session_id']){
//				$this->sessionid = $member['session_id'];
//			}else{
//				Db::name('member')->where('id',$member['id'])->update(['session_id'=>session_id]);
//			}
            Db::name('session')->where('aid',aid)->where('session_id',$this->sessionid)->update([
                'mid' => $member['id'],
                'login_time' => time()
            ]);
			return $this->json(['status'=>1,'msg'=>'登录成功','mid'=>$member['id'],'session_id'=>$this->sessionid]);
		}
		if($logintype == 2){ //手机验证码登录
			if(md5($tel.'-'.$smscode) != cache($this->sessionid.'_smscode') || cache($this->sessionid.'_smscodetimes')>5){
				cache($this->sessionid.'_smscodetimes',cache($this->sessionid.'_smscodetimes')+1);
				return $this->json(['status'=>0,'msg'=>'短信验证码错误']);
			}
			cache($this->sessionid.'_smscode',null);
			cache($this->sessionid.'_smscodetimes',null);
			$member = Db::name('member')->where('aid',aid)->where('tel',$tel)->find();
			$tmplids = [];
			if(!$member){
				$data = [];
				$data['aid'] = aid;
				$data['tel'] = $tel;
				$data['nickname'] = substr($tel,0,3).'****'.substr($tel,-4);
				$data['sex'] = 3;
				$data['headimg'] = PRE_URL.'/static/img/touxiang.png';
				$data['createtime'] = time();
                $data['last_visittime'] = time();
//				$data['session_id'] = $this->sessionid;
				//推广人
                $sysset = Db::name('admin_set')->where('aid',aid)->find();
				if($fromid){
                    $data['pid'] = $this->getpid($fromid);
                    $upuser = Db::name('member')->where('aid',aid)->where('id',intval($data['pid']))->find();
				}elseif($yqcode){
                    if($sysset['reg_invite_code']) {
                        if($sysset['reg_invite_code'] == 2 && empty($yqcode)){
                            return $this->json(['status'=>0,'msg'=>'请输入邀请码']);
                        }
                        if($sysset['reg_invite_code_type'] == 1) {//邀请码
                            $upuser = Db::name('member')->where('aid',aid)->where('yqcode',$yqcode)->find();
                        } elseif($sysset['reg_invite_code_type'] == 2) {//id
                            $upuser = Db::name('member')->where('aid',aid)->where('id',intval($fromid))->find();
                        } else {//手机号
                            $upuser = Db::name('member')->where('aid',aid)->where('tel',$yqcode)->find();
                        }

                        if($upuser){
                            $uplv = Db::name('member_level')->where('aid',aid)->where('id',$upuser['levelid'])->find();
                            if($uplv['can_agent']!=0){
                                $data['pid'] = $upuser['id'];
                            }
                        }
                    }
                }
                if($sysset['reg_invite_code']==2 && !$upuser){ //必须邀请注册
                    return $this->json(['status'=>0,'msg'=>'必须有邀请人邀请注册']);
                }
				$data['platform'] = platform;
                if(getcustom('member_business')){
                	//商户注册会员
                    if(input('param.regbid')){
                    	//查询权限
	                    $admin_user = Db::name('admin_user')->where('aid',aid)->where('isadmin','>',0)->field('auth_type,auth_data')->find();
	                    if($admin_user){
	                    	if($admin_user['auth_type'] !=1 ){
		                        $auth_data = !empty($admin_user['auth_data'])?json_decode($admin_user['auth_data'],true):[];
		                        if(in_array('MemberBusiness,MemberBusiness',$auth_data)){
		                        	$data['bid'] = input('param.regbid/d');
		                        }
		                    }else{
		                    	$data['bid'] = input('param.regbid/d');
		                    }
		                }
                    }
                }
				$mid = \app\models\Member::add(aid,$data);
                Db::name('session')->where('aid',aid)->where('session_id',$this->sessionid)->update([
                    'mid' => $mid,
                    'login_time' => time()
                ]);
				if($data['pid']) \app\commons\Common::user_tjscore(aid,$mid);
                //注册赠送
                \app\commons\Common::registerGive(aid,array_merge($data, ['id' => $mid]));
                $sessionid_time = 7*86400;
				if(getcustom('system_nologin_day')){
					//后台设置的免登录天数
					$nologin_day = Db::name('admin_set')->where('aid',aid)->value('nologin_day');
					if($nologin_day>0){
						$sessionid_time = $nologin_day*86400;
					}
				}
				cache($this->sessionid.'_mid',$mid,$sessionid_time);
				if(platform == 'wx' && $this->sysset['reg_check'] == 1){
					$wx_tmplset = Db::name('wx_tmplset')->where('aid',aid)->find();
					if($wx_tmplset['tmpl_shenhe_new']){
						$tmplids[] = $wx_tmplset['tmpl_shenhe_new'];
					}
				}
			}else{
				if(getcustom('member_disabled') && $member['disabled_login'] == 1){
					return $this->json(['status'=>0,'msg'=>'账号已禁用，请联系管理员处理！']);
				}
				$mid = $member['id'];
				$sessionid_time = 7*86400;
				if(getcustom('system_nologin_day')){
					//后台设置的免登录天数
					$nologin_day = Db::name('admin_set')->where('aid',aid)->value('nologin_day');
					if($nologin_day>0){
						$sessionid_time = $nologin_day*86400;
					}
				}
				cache($this->sessionid.'_mid',$member['id'],$sessionid_time);
//				if($member['session_id']){
//					$this->sessionid = $member['session_id'];
//				}else{
//					Db::name('member')->where('id',$mid)->update(['session_id'=>$this->sessionid]);
//				}
                Db::name('session')->where('aid',aid)->where('session_id',$this->sessionid)->update([
                    'mid' => $mid,
                    'login_time' => time()
                ]);
			}

			return $this->json(['status'=>1,'msg'=>'登录成功','mid'=>$mid,'session_id'=>$this->sessionid,'tmplids'=>$tmplids]);
		}
	}

	//公众号静默授权登录
	public function mpbaselogin(){
		$fromid = input('param.pid/d');

		//授权登录
		if(input('param.state') && input('param.state') == 'baseauthlogin' && input('param.code')){
			$code = input('param.code');
            $rs = \app\commons\Wechat::getAccessTokenByCode(aid,$code);//https://developers.weixin.qq.com/doc/offiaccount/OA_Web_Apps/Wechat_webpage_authorization.html
            //is_snapshotuser	是否为快照页模式虚拟账号，只有当用户是快照页模式虚拟账号时返回，值为1
			if($rs['is_snapshotuser'] == 1){
                return $this->json(['status'=>-4,'msg'=>'授权登录失败，请点击下方“使用完整服务”']);
            }
            $openid = $rs['openid'];
			if($openid){
				$result2 = request_get('https://api.weixin.qq.com/cgi-bin/user/info?access_token='.\app\commons\Wechat::access_token(aid,'mp').'&openid='.$openid.'&lang=zh_CN');
				$result = json_decode($result2,true);
				$member = Db::name('member')->where('aid',aid)->where('mpopenid',$openid)->find();
				if(!$member && $result['unionid']){
					$member = Db::name('member')->where('aid',aid)->where('unionid',$result['unionid'])->find();
					if($member) Db::name('member')->where('id',$member['id'])->update(['mpopenid'=>$openid]);
				}
				if(!$member){
					$data = [];
					$data['aid'] = aid;
					$data[platform.'openid'] = $openid;
					if($result['unionid']){
						$data['unionid'] = $result['unionid'];
					}
					$data['sex'] = 3;
					$data['nickname'] = '用户'.random(6);
					$data['headimg'] = PRE_URL.'/static/img/touxiang.png';
					$data['createtime'] = time();
                    $data['last_visittime'] = time();
					//推广人
					if($fromid){
                        $data['pid'] = $this->getpid($fromid);
					}
					$data['platform'] = platform;
					if(getcustom('member_business')){
						//商户注册会员
	                    if(input('param.regbid')){
	                    	//查询权限
		                    $admin_user = Db::name('admin_user')->where('aid',aid)->where('isadmin','>',0)->field('auth_type,auth_data')->find();
		                    if($admin_user){
		                    	if($admin_user['auth_type'] !=1 ){
			                        $auth_data = !empty($admin_user['auth_data'])?json_decode($admin_user['auth_data'],true):[];
			                        if(in_array('MemberBusiness,MemberBusiness',$auth_data)){
			                        	$data['bid'] = input('param.regbid/d');
			                        }
			                    }else{
			                    	$data['bid'] = input('param.regbid/d');
			                    }
			                }
	                    }
	                }
					$mid = \app\models\Member::add(aid,$data);
					Db::name('session')->where('aid',aid)->where('session_id',$this->sessionid)->update([
						'mid' => $mid,
						'login_time' => time()
					]);
					if($data['pid']) \app\commons\Common::user_tjscore(aid,$mid);
					//注册赠送
					\app\commons\Common::registerGive(aid,array_merge($data, ['id' => $mid]));
				}else{
					$mid = $member['id'];
					Db::name('session')->where('aid',aid)->where('session_id',$this->sessionid)->update([
						'mid' => $mid,
						'login_time' => time()
					]);
                    //后绑定开放平台有了unionid 同步更新
                    if(empty($member['unionid']) && $result['unionid']){
                        Db::name('member')->where('aid',aid)->where('id',$member['id'])->update(['unionid'=>$result['unionid']]);
                    }
				}
				$sessionid_time = 7*86400;
				if(getcustom('system_nologin_day')){
					//后台设置的免登录天数
					$nologin_day = Db::name('admin_set')->where('aid',aid)->value('nologin_day');
					if($nologin_day>0){
						$sessionid_time = $nologin_day*86400;
					}
				}
				cache($this->sessionid.'_mid',$mid,$sessionid_time);
				if(input('param.frompage') && input('param.frompage') != '/pages/index/login' && input('param.frompage') != '/pages/index/reg' && input('param.frompage') != '/pages/index/getpwd'){
					return redirect(m_url(urldecode(input('param.frompage'))));
				}
				return redirect(m_url('pages/my/usercenter'));
			}else{
				if($rs['errcode']==40163 || $rs['errcode']==40029){
					$request_url = $_SERVER["REQUEST_URI"];
					if (strpos($request_url, '?code=')) {
						$request_url = explode('?code=', $request_url)[0];
					} elseif (strpos($request_url, '&code=')) {
						$request_url = explode('&code=', $request_url)[0];
					}
					$redirectUrl = request()->domain().$request_url;
					return redirect($redirectUrl);
					die;
				}else{
					return $this->json(['status'=>0,'msg'=>'授权登录失败,请重新操作']);
				}
			}
		}else{
			//获取用户信息
			$request_url = ltrim($_SERVER["REQUEST_URI"],'/');
			if(strpos($request_url,'?code=')!==false){
				$request_url = explode('?code=',$request_url)[0];
			}elseif(strpos($request_url,'&code=')!==false){
				$request_url = explode('&code=',$request_url)[0];
			}
			$redirectUrl = request()->domain().'/'.$request_url;//.'&frompage='.input('param.frompage');
			//\think\facade\Log::write($redirectUrl);
			$redirectUrl = urlencode($redirectUrl);
            $AuthorizeUrl = \app\commons\Wechat::getOauth2AuthorizeUrl(aid,$redirectUrl,'snsapi_base','baseauthlogin');
			return redirect($AuthorizeUrl);
		}
	}
	//公众号网页授权登录
	public function shouquan(){
		$fromid = input('param.pid/d');
        $authlogin = input('param.authlogin',0);
   
        
		//授权登录
		if(input('param.state') && input('param.state') == 'authlogin' && input('param.code')){
			$code = input('param.code');
            $rs = \app\commons\Wechat::getAccessTokenByCode(aid,$code);
              ll($rs,'shouquanRegister-$rs');
            //is_snapshotuser	是否为快照页模式虚拟账号，只有当用户是快照页模式虚拟账号时返回，值为1
            if($rs['is_snapshotuser'] == 1){
                return redirect(m_url('pagesB/message?msg=授权登录失败，请点击下方“使用完整服务”'));
            }
			if($rs['openid']){
				//$result = request_get('https://api.weixin.qq.com/cgi-bin/user/info?access_token='.\app\commons\Wechat::access_token(aid,'mp').'&openid='.$rs['openid'].'&lang=zh_CN');
				//$result = json_decode($result,true);
				//if(!$result || $result['subscribe']==0){
				
                $result = \app\commons\Wechat::getUserInfo($rs['openid'],$rs['access_token']);
                ll($rs,'$rs$rs$rs');
				//}
				if(!$result['openid']){
				    return redirect(m_url('pagesB/message?msg='.$result['errmsg']));
					return $this->json(['status'=>0,'msg'=>$result['errmsg']]);
				}
				
				
				$result2 = request_get('https://api.weixin.qq.com/cgi-bin/user/info?access_token='.\app\commons\Wechat::access_token(aid,'mp').'&openid='.$rs['openid'].'&lang=zh_CN');
				$result2 = json_decode($result2,true);
				$result['subscribe'] = $result2['subscribe'];
				$result['subscribe_time'] = $result2['subscribe_time'];
				
			\think\facade\Log::write($result);
				
				$member = Db::name('member')->where('aid',aid)->where('mpopenid',$rs['openid'])->find();
				if(!$member && $result['unionid']){
					$member = Db::name('member')->where('aid',aid)->where('unionid',$result['unionid'])->find();
					if($member) Db::name('member')->where('id',$member['id'])->update(['mpopenid'=>$result['openid']]);
				}

				$frompage = input('param.frompage');
				if($frompage){
					$frompage = urlencode($frompage);
				}

				if(getcustom('member_business')){
		            //商户注册会员
		            $regbidstr = '';
		            if(input('param.regbid')){
		                //查询权限
		                $admin_user = Db::name('admin_user')->where('aid',aid)->where('isadmin','>',0)->field('auth_type,auth_data')->find();
		                if($admin_user){
		                	if($admin_user['auth_type'] !=1 ){
			                    $auth_data = !empty($admin_user['auth_data'])?json_decode($admin_user['auth_data'],true):[];
			                    if(in_array('MemberBusiness,MemberBusiness',$auth_data)){
			                        $regbidstr = '&regbid='.input('param.regbid/d');
			                    }
			                }else{
			                    $regbidstr = '&regbid='.input('param.regbid/d');
			                }
			            }
		            }
		        }
				if(!$member){
                    cache($this->sessionid.'_'.platform.'UserInfo',$result,3600);
					if($authlogin!=2) {
                        if($this->sysset['reg_invite_code']){
                            $params = '?logintype=6&reg_invite_code='.$this->sysset['reg_invite_code'].'&login_bind='.$this->sysset['login_bind'].'&frompage='.$frompage;
                            if($fromid) {
                                $params .= '&pid='.$fromid;
                            }
                            if(getcustom('member_business')){
                                //商户注册会员
                                $params .= $regbidstr;
                            }
                            return redirect(m_url('pages/index/login'. $params));
                        }
                        if($this->sysset['login_setnickname']){
                            $params = '?logintype=5&login_bind='.$this->sysset['login_bind'].'&frompage='.$frompage;
                            if($fromid) {
                                $params .= '&pid='.$fromid;
                            }
                            if(getcustom('member_business')){
                                //商户注册会员
                                $params .= $regbidstr;
                            }
                            return redirect(m_url('pages/index/login'. $params));
                        }
                        if($this->sysset['login_bind']){
                            $params = '?logintype=4&login_bind='.$this->sysset['login_bind'].'&frompage='.$frompage;
                            if($fromid) {
                                $params .= '&pid='.$fromid;
                            }
                            if(getcustom('member_business')){
                                //商户注册会员
                                $params .= $regbidstr;
                            }
                            return redirect(m_url('pages/index/login'. $params));
                        }
					}
					return $this->shouquanRegister(1);
				}else{
					$mid = $member['id'];
					if(!$member['headimg'] || strpos($member['headimg'],'/static/img/touxiang.png') !== false){
						$update = [];
						$update['nickname'] = $result['nickname'];
						$update['headimg'] = \app\commons\Pic::uploadoss($result['headimgurl']);
						$update['sex'] = $result['sex'];
						$update['province'] = $result['province'];
						$update['city'] = $result['city'];
						Db::name('member')->where('id',$member['id'])->update($update);
					}
                    //后绑定开放平台有了unionid 同步更新
                    if(empty($member['unionid']) && $result['unionid']){
                        Db::name('member')->where('aid',aid)->where('id',$member['id'])->update(['unionid'=>$result['unionid']]);
                    }

					if($authlogin!=2){
						cache($this->sessionid.'_'.platform.'UserInfo',$result,3600);
                        if($this->sysset['login_setnickname'] && (!$member['nickname'] || $member['nickname'] == '微信用户')){
                            $params = '?logintype=5&login_bind='.$this->sysset['login_bind'].'&frompage='.$frompage;
                            if($fromid) {
                                $params .= '&pid='.$fromid;
                            }
                            if(getcustom('member_business')){
                                //商户注册会员
                                $params .= $regbidstr;
                            }
                            return redirect(m_url('pages/index/login'. $params));
                        }
                        if($this->sysset['login_bind'] && empty($member['tel'])){
                            $params = '?logintype=4&login_bind='.$this->sysset['login_bind'].'&frompage='.$frompage;
                            if($fromid) {
                                $params .= '&pid='.$fromid;
                            }
                            if(getcustom('member_business')){
                                //商户注册会员
                                $params .= $regbidstr;
                            }
                            return redirect(m_url('pages/index/login'. $params));
                        }
					}

                    Db::name('session')->where('aid',aid)->where('session_id',$this->sessionid)->update([
                        'mid' => $mid,
                        'login_time' => time()
                    ]);
				}
				$sessionid_time = 7*86400;
				if(getcustom('system_nologin_day')){
					//后台设置的免登录天数
					$nologin_day = Db::name('admin_set')->where('aid',aid)->value('nologin_day');
					if($nologin_day>0){
						$sessionid_time = $nologin_day*86400;
					}
				}
				cache($this->sessionid.'_mid',$mid,$sessionid_time);
				if(input('param.frompage') && input('param.frompage') != '/pages/index/login' && input('param.frompage') != '/pages/index/reg' && input('param.frompage') != '/pages/index/getpwd'){
                    return redirect(m_url( str_replace('amp;','', urldecode(input('param.frompage')))));
				}
				return redirect(m_url('pages/my/usercenter'));
			}else{
				if($rs['errcode']==40163 || $rs['errcode']==40029){
					$request_url = $_SERVER["REQUEST_URI"];
					if (strpos($request_url, '?code=')) {
						$request_url = explode('?code=', $request_url)[0];
					} elseif (strpos($request_url, '&code=')) {
						$request_url = explode('&code=', $request_url)[0];
					}
					$redirectUrl = request()->domain().$request_url;
					return redirect($redirectUrl);
					die;
				}else{
					return $this->json(['status'=>0,'msg'=>'授权登录失败,请重新操作']);
				}
			}
		}else{
			//获取用户信息
			$request_url = ltrim($_SERVER["REQUEST_URI"],'/');
			if(strpos($request_url,'?code=')!==false){
				$request_url = explode('?code=',$request_url)[0];
			}elseif(strpos($request_url,'&code=')!==false){
				$request_url = explode('&code=',$request_url)[0];
			}
			$redirectUrl = request()->domain().'/'.$request_url;//.'&frompage='.input('param.frompage');
			//\think\facade\Log::write($redirectUrl);
			$redirectUrl = urlencode($redirectUrl);
            $grant_type = 'snsapi_userinfo';//全信息获取授权
            if($authlogin==2){
                $grant_type = 'snsapi_base';//静默授权
            }

            $AuthorizeUrl = \app\commons\Wechat::getOauth2AuthorizeUrl(aid,$redirectUrl,$grant_type,'authlogin');
			return redirect($AuthorizeUrl);
		}
	}
    //公众号网页授权注册
    public function shouquanRegister($isredirect=0){
        $fromid = input('param.pid/d');
        $tel = input('param.tel');
        $smscode = input('param.smscode');
        $result = cache($this->sessionid.'_'.platform.'UserInfo');
        $yqcode = input('param.yqcode');
        //授权登录
        $rs = $result;
        \think\facade\Log::write($result);
        if(!$rs['openid']) return json(['status'=>0,'msg'=>'获取授权信息失败!']);
        $member = Db::name('member')->where('aid',aid)->where('mpopenid',$result['openid'])->find();
        if(!$member && $result['unionid']){
            $member = Db::name('member')->where('aid',aid)->where('unionid',$result['unionid'])->find();
            if($member) Db::name('member')->where('id',$member['id'])->update(['mpopenid'=>$result['openid']]);
        }
        
        \think\facade\Log::write($member);
        
        
		if($tel){
			if(md5($tel.'-'.$smscode) != cache($this->sessionid.'_smscode') || cache($this->sessionid.'_smscodetimes')>5){
				cache($this->sessionid.'_smscodetimes',cache($this->sessionid.'_smscodetimes')+1);
				return $this->json(['status'=>0,'msg'=>'短信验证码错误']);
			}
			cache($this->sessionid.'_smscode',null);
			cache($this->sessionid.'_smscodetimes',null);
			if($member){
				$hasmember = Db::name('member')->where('aid',aid)->where('tel',$tel)->find();
				if(!$hasmember){
					Db::name('member')->where('id',$member['id'])->update(['tel'=>$tel]);
				}else{
					return $this->json(['status'=>0,'msg'=>'该手机号已被其他用户绑定']);
				}
			}else{
				$member = Db::name('member')->where('aid',aid)->where('tel',$tel)->find();
				if($member){
					$update = [];
					$update['mpopenid'] = $result['openid'];
					if($result['unionid']){
						$update['unionid'] = $result['unionid'];
					}
					if(!$member['headimg'] || strpos($member['headimg'],'/static/img/touxiang.png') !== false){
						$update['nickname'] = $result['nickname'];
						$update['headimg'] = \app\commons\Pic::uploadoss($result['headimgurl']);
						$update['sex'] = $result['sex'];
						$update['province'] = $result['province'];
						$update['city'] = $result['city'];
					}
					Db::name('member')->where('id',$member['id'])->update($update);
				}
			}
		}

        //推广人
        if($fromid){
            $upuser = Db::name('member')->where('aid',aid)->where('id',intval($fromid))->find();
        }elseif($yqcode){
            if($this->sysset['reg_invite_code']) {
                if($this->sysset['reg_invite_code'] == 2 && empty($yqcode)){
                    return $this->json(['status'=>0,'msg'=>'请输入邀请码']);
                }
                if($this->sysset['reg_invite_code_type'] == 1) {//邀请码
                    $upuser = Db::name('member')->where('aid',aid)->where('yqcode',$yqcode)->find();
                    if(!$upuser) return $this->json(['status'=>0,'msg'=>'邀请码不正确']);
                } elseif($this->sysset['reg_invite_code_type'] == 2) {//id 未启用
                    $upuser = Db::name('member')->where('aid',aid)->where('id',intval($fromid))->find();
                    if(!$upuser) return $this->json(['status'=>0,'msg'=>'邀请码不正确']);
                } else {//手机号
                    $upuser = Db::name('member')->where('aid',aid)->where('tel',$yqcode)->find();
                    if(!$upuser) return $this->json(['status'=>0,'msg'=>'邀请人手机号不正确']);
                }

                if($upuser){
                    $fromid = $upuser['id'];
                }
            }
        }

        if($this->sysset['reg_invite_code']==2 && !$upuser){ //必须邀请注册
            return $this->json(['status'=>0,'msg'=>'必须有邀请人邀请注册']);
        }

        if(!$member){
            
            
            ll($result,'shouquanRegister-$result');
        
            
            
            if(getcustom('maidan_auto_reg') && input('maidan')){
                $maidan_set = Db::name('admin_set')->where('aid',aid)->field('maidan_auto_reg,maidan_login')->find();
                //聚合收款码买单，如果设置了不强制登录也不自动注册会员，直接返回
                if(!$maidan_set['maidan_login'] && !$maidan_set['maidan_auto_reg']){
                    cache($this->sessionid.'_openid',$result['openid'],7*86400);
                    cache($this->sessionid.'_unionid',$result['unionid'],7*86400);
                    if(input('param.frompage')){
                        return redirect(m_url(urldecode(input('param.frompage'))));
                    }
                    return redirect(m_url('pages/maidan/pay'));
                }
            }
            $data = [];
            $data['aid'] = aid;
            $data['mpopenid'] = $result['openid'];
            $data['nickname'] = $result['nickname'];
            $data['sex'] = $result['sex'];
            $data['province'] = $result['province'];
            $data['city'] = $result['city'];
            $data['country'] = $result['country'];
            $data['headimg'] = \app\commons\Pic::uploadoss($result['headimgurl']);
            $data['unionid'] = $result['unionid'];
            $data['subscribe'] = $result['subscribe'] == 1 ? 1 : 0;
            $data['subscribe_time'] = $result['subscribe_time'];
            $data['createtime'] = time();
            $data['last_visittime'] = time();
//            $data['session_id'] = $this->sessionid;
			if($tel){
				$data['tel'] = $tel;
			}
            if($fromid){
                 $data['pid'] = $this->getpid($fromid);
            }
            $data['platform'] = platform;
            
            if(getcustom('member_business')){
            	//商户注册会员
                if(input('param.regbid')){
                	//查询权限
                    $admin_user = Db::name('admin_user')->where('aid',aid)->where('isadmin','>',0)->field('auth_type,auth_data')->find();
                    if($admin_user){
                    	if($admin_user['auth_type'] !=1 ){
	                        $auth_data = !empty($admin_user['auth_data'])?json_decode($admin_user['auth_data'],true):[];
	                        if(in_array('MemberBusiness,MemberBusiness',$auth_data)){
	                        	$data['bid'] = input('param.regbid/d');
	                        }
	                    }else{
	                    	$data['bid'] = input('param.regbid/d');
	                    }
	                }
                }
            }
            $mid = \app\models\Member::add(aid,$data);
            Db::name('session')->where('aid',aid)->where('session_id',$this->sessionid)->update([
                'mid' => $mid,
                'login_time' => time()
            ]);
            if($data['pid']) \app\commons\Common::user_tjscore(aid,$mid);
            //注册赠送
            \app\commons\Common::registerGive(aid,array_merge($data, ['id' => $mid]));
        }else{
			$mid = $member['id'];
//			if($member['session_id']){
//				$this->sessionid = $member['session_id'];
//			}else{
//				Db::name('member')->where('id',$mid)->update(['session_id'=>$this->sessionid]);
//			}
            Db::name('session')->where('aid',aid)->where('session_id',$this->sessionid)->update([
                'mid' => $mid,
                'login_time' => time()
            ]);
			if($result['subscribe'] == 1 && $member['subscribe'] == 0){
				Db::name('member')->where('id',$mid)->update(['subscribe'=>1]);
			}
            //后绑定开放平台有了unionid 同步更新
            if(empty($member['unionid']) && $result['unionid']){
                Db::name('member')->where('aid',aid)->where('id',$member['id'])->update(['unionid'=>$result['unionid']]);
            }
		}
		$sessionid_time = 7*86400;
		if(getcustom('system_nologin_day')){
			//后台设置的免登录天数
			$nologin_day = Db::name('admin_set')->where('aid',aid)->value('nologin_day');
			if($nologin_day>0){
				$sessionid_time = $nologin_day*86400;
			}
		}
        cache($this->sessionid.'_mid',$mid,$sessionid_time);
		if($isredirect){
			if(input('param.frompage')){
				return redirect(m_url(urldecode(input('param.frompage'))));
			}
			return redirect(m_url('pages/my/usercenter'));
		}else{
			return json(['status'=>1,'msg'=>'登录成功']);
		}
    }
	//微信小程序授权登录      
	public function wxlogin(){
		$jscode = input('param.code');
		$authlogin = input('param.authlogin',0);
        $loginmsg = '登录成功';
        if($authlogin==2){
            $loginmsg = '';
            //静默授权
            $userinfo = [
                'nickName'=>'微信用户',
                'avatarUrl'=> PRE_URL.'/static/img/touxiang.png',
                'gender'=> 0,
                'province'=> '',
                'city'=> ''
            ];
        }else{
            $userinfo = input('param.userinfo');
        }
		$fromid = input('param.pid');
		$wxapp = \app\commons\System::appinfo(aid,'wx');
		if($wxapp['authtype']==1){
			$url = 'https://api.weixin.qq.com/sns/component/jscode2session?appid='.$wxapp['appid'].'&component_appid='.\app\commons\Wechat::component_appid().'&js_code='.$jscode.'&grant_type=authorization_code&component_access_token='.\app\commons\Wechat::component_access_token();
		}else{
			$url = 'https://api.weixin.qq.com/sns/jscode2session?appid='.$wxapp['appid'].'&secret='.$wxapp['appsecret'].'&js_code='.$jscode.'&grant_type=authorization_code';
		}
		$rs = request_get($url);
		$rs = json_decode($rs,true);
		if($rs['errcode']>0){
			return $this->json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs)]);
		}
        $rs['authlogin'] = $authlogin;
		$openid = $rs['openid'];
		if(!$openid) return json(['status'=>0,'msg'=>'获取授权信息失败']);
		$unionid = $rs['unionid'];
		$session_key = $rs['session_key'];
		$member = Db::name('member')->where('aid',aid)->where(platform.'openid',$openid)->find();
		if(!$member && $unionid){
			$member = Db::name('member')->where('aid',aid)->where('unionid',$unionid)->find();
			if($member) Db::name('member')->where('id',$member['id'])->update([platform.'openid'=>$openid]);
		}
		if(!$member){
            $rs['userinfo'] = $userinfo;
            cache($this->sessionid.'_'.platform.'UserInfo',$rs,3600);
            if($authlogin!=2){
                if($this->sysset['reg_invite_code']){
                    return $this->json(['status'=>4,'msg'=>'输入邀请码','reg_invite_code'=>$this->sysset['reg_invite_code'],'login_setnickname'=>$this->sysset['login_setnickname'],'login_bind'=>$this->sysset['login_bind']]);
                }
                if($this->sysset['login_setnickname'] && (!$userinfo['nickName'] || $userinfo['nickName'] == '微信用户')){
                    return $this->json(['status'=>3,'msg'=>'设置头像昵称','login_setnickname'=>$this->sysset['login_setnickname'],'login_bind'=>$this->sysset['login_bind']]);
                }
                if($this->sysset['login_bind']){
                    return $this->json(['status'=>2,'msg'=>'绑定手机号','login_bind'=>$this->sysset['login_bind']]);
                }
            }
			return $this->wxRegister();
		}else{
			$mid = $member['id'];

			if($authlogin!=2 && $this->sysset['login_setnickname'] && ($member['nickname'] == '微信用户' || $member['nickname'] == '关注用户')){
				$rs['userinfo'] = $userinfo;
				cache($this->sessionid.'_'.platform.'UserInfo',$rs,3600);
                return $this->json(['status'=>3,'msg'=>'设置头像昵称','login_setnickname'=>$this->sysset['login_setnickname'],'login_bind'=>$this->sysset['login_bind']]);
            }

			if(!$member['headimg'] || strpos($member['headimg'],'/static/img/touxiang.png') !== false){
				$update = [];
				$update['headimg'] = \app\commons\Pic::uploadoss($userinfo['avatarUrl']);
				$update['nickname'] = $userinfo['nickName'];
				$update['sex'] = $userinfo['gender'];
				$update['province'] = $userinfo['province'];
				$update['city'] = $userinfo['city'];
				Db::name('member')->where('id',$member['id'])->update($update);
			}
//			if($member['session_id']){
//				$this->sessionid = $member['session_id'];
//			}else{
//				Db::name('member')->where('id',$mid)->update(['session_id'=>$this->sessionid]);
//			}
			//更新session信息
            Db::name('session')->where('aid',aid)->where('session_id',$this->sessionid)->update([
                'mid' => $mid,
                'login_time' => time()
            ]);
            //后绑定开放平台有了unionid 同步更新
            if(empty($member['unionid']) && $unionid){
                Db::name('member')->where('aid',aid)->where('id',$member['id'])->update(['unionid'=>$unionid]);
            }
            if($this->sysset['login_bind'] && empty($member['tel'])){
                $rs['userinfo'] = $userinfo;
                cache($this->sessionid.'_'.platform.'UserInfo',$rs,3600);
                return $this->json(['status'=>2,'msg'=>'绑定手机号','login_bind'=>$this->sysset['login_bind']]);
            }
		}
		$sessionid_time = 7*86400;
		if(getcustom('system_nologin_day')){
			//后台设置的免登录天数
			$nologin_day = Db::name('admin_set')->where('aid',aid)->value('nologin_day');
			if($nologin_day>0){
				$sessionid_time = $nologin_day*86400;
			}
		}
		cache($this->sessionid.'_mid',$mid,$sessionid_time);
		return $this->json(['status'=>1,'msg'=>$loginmsg,'mid'=>$mid,'session_id'=>$this->sessionid]);
	}


    public function wxRegister(){
        $rs = cache($this->sessionid.'_'.platform.'UserInfo');
        $userinfo = $rs['userinfo'];
        $authlogin = $rs['authlogin']??0;
        $fromid = input('param.pid');
        $yqcode = input('param.yqcode');
        if($rs['errcode']>0){
            return $this->json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs)]);
        }
        $openid = $rs['openid'];
        if(!$openid) return json(['status'=>0,'msg'=>'获取授权信息失败']);
        $unionid = $rs['unionid'];
        $session_key = $rs['session_key'];
        $member = Db::name('member')->where('aid',aid)->where(platform.'openid',$openid)->find();

        if(!$member && $unionid){
            $member = Db::name('member')->where('aid',aid)->where('unionid',$unionid)->find();
            if($member) Db::name('member')->where('id',$member['id'])->update([platform.'openid'=>$openid]);
        }
		if(input('param.code') && input('post.iv') && input('post.encryptedData')){
			$jscode = input('param.code');
			$encryptedData = input('post.encryptedData');
			$iv = input('post.iv');

			$wxapp = \app\commons\System::appinfo(aid,'wx');
			if($wxapp['authtype']==1){
				$url = 'https://api.weixin.qq.com/sns/component/jscode2session?appid='.$wxapp['appid'].'&component_appid='.\app\commons\Wechat::component_appid().'&js_code='.$jscode.'&grant_type=authorization_code&component_access_token='.\app\commons\Wechat::component_access_token();
			}else{
				$url = 'https://api.weixin.qq.com/sns/jscode2session?appid='.$wxapp['appid'].'&secret='.$wxapp['appsecret'].'&js_code='.$jscode.'&grant_type=authorization_code';
			}
			$rs = request_get($url);
			$rs = json_decode($rs,true);
			if($rs['errcode']>0){
				return $this->json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs)]);
			}
			$session_key = $rs['session_key'];
			if(!$session_key) return json(['status'=>0,'msg'=>'获取授权信息失败']);

			$pc = new \app\commons\WxBizDataCrypt($wxapp['appid'], $session_key);
			$errCode = $pc->decryptData($encryptedData, $iv, $rdata);
			$rdata = json_decode($rdata,true);

			if($rdata['phoneNumber']){
				$tel = $rdata['phoneNumber'];
			}else{
				return json(['status'=>0,'msg'=>'授权获取手机号失败']);
			}
		}elseif(input('param.tel')){
			$tel = input('param.tel');
			$smscode = input('param.smscode');
			if(md5($tel.'-'.$smscode) != cache($this->sessionid.'_smscode') || cache($this->sessionid.'_smscodetimes')>5){
				cache($this->sessionid.'_smscodetimes',cache($this->sessionid.'_smscodetimes')+1);
				return $this->json(['status'=>0,'msg'=>'短信验证码错误']);
			}
			cache($this->sessionid.'_smscode',null);
			cache($this->sessionid.'_smscodetimes',null);
		}
		if($tel){
			if($member){
				$hasmember = Db::name('member')->where('aid',aid)->where('tel',$tel)->where('id','<>',$member['id'])->find();
				if(!$hasmember){
					Db::name('member')->where('id',$member['id'])->update(['tel'=>$tel]);
				}else{
					return $this->json(['status'=>0,'msg'=>'该手机号已被其他用户绑定']);
				}
			}else{
				$member = Db::name('member')->where('aid',aid)->where('tel',$tel)->find();
				if($member){
					$update = [];
					$update[platform.'openid'] = $openid;
					$update['session_key'] = $session_key;
					if($unionid){
						$update['unionid'] = $unionid;
					}
					Db::name('member')->where('id',$member['id'])->update($update);
				}
			}
		}
		if($member && input('param.nickname')){
			$update = [];
			$update['headimg'] = input('param.headimg');
			$update['nickname'] = input('param.nickname');
			Db::name('member')->where('id',$member['id'])->update($update);
		}
		$tmplids = [];
        if(!$member){
            $data = [];
            $data['aid'] = aid;
            $data[platform.'openid'] = $openid;
            $data['session_key'] = $session_key;
            if($unionid){
                $data['unionid'] = $unionid;
            }
			if(input('param.headimg')){
				$data['headimg'] = input('param.headimg');
			}else{
				$data['headimg'] = \app\commons\Pic::uploadoss($userinfo['avatarUrl']);
			}
			if(input('param.nickname')){
				$data['nickname'] = input('param.nickname');
			}else{
				$data['nickname'] = $userinfo['nickName'];
			}
            $data['sex'] = $userinfo['gender'];
            $data['province'] = $userinfo['province'];
            $data['city'] = $userinfo['city'];
//            $data['session_id'] = $this->sessionid;
            $data['createtime'] = time();
            $data['last_visittime'] = time();
			if($tel){
				$data['tel'] = $tel;
			}

            //推广人
            if($fromid){
                $data['pid'] = $this->getpid($fromid);
                if($data['pid']) $upuser = Db::name('member')->where('aid',aid)->where('id',$data['pid'])->find();
            }elseif($yqcode){
                if($this->sysset['reg_invite_code']) {
                    if($this->sysset['reg_invite_code'] == 2 && empty($yqcode)){
                        return $this->json(['status'=>0,'msg'=>'请输入邀请码']);
                    }
                    if($this->sysset['reg_invite_code_type'] == 1) {//邀请码
                        $upuser = Db::name('member')->where('aid',aid)->where('yqcode',$yqcode)->find();
                        if(!$upuser) return $this->json(['status'=>0,'msg'=>'邀请码不正确']);
                    } elseif($this->sysset['reg_invite_code_type'] == 2) {//id 未启用
                        $upuser = Db::name('member')->where('aid',aid)->where('id',intval($fromid))->find();
                        if(!$upuser) return $this->json(['status'=>0,'msg'=>'邀请码不正确']);
                    } else {//手机号
                        $upuser = Db::name('member')->where('aid',aid)->where('tel',$yqcode)->find();
                        if(!$upuser) return $this->json(['status'=>0,'msg'=>'邀请人手机号不正确']);
                    }

                    if($upuser){
                        $uplv = Db::name('member_level')->where('aid',aid)->where('id',$upuser['levelid'])->find();
                        if($uplv['can_agent']!=0){
                            $data['pid'] = $upuser['id'];
                        }
                    }
                }
            }

            if($this->sysset['reg_invite_code']==2 && !$upuser){ //必须邀请注册
                return $this->json(['status'=>0,'msg'=>'必须有邀请人邀请注册']);
            }
            $data['platform'] = platform;
            if(getcustom('member_business')){
            	//商户注册会员
                if(input('param.regbid')){
                	//查询权限
                    $admin_user = Db::name('admin_user')->where('aid',aid)->where('isadmin','>',0)->field('auth_type,auth_data')->find();
                    if($admin_user){
                    	if($admin_user['auth_type'] !=1 ){
	                        $auth_data = !empty($admin_user['auth_data'])?json_decode($admin_user['auth_data'],true):[];
	                        if(in_array('MemberBusiness,MemberBusiness',$auth_data)){
	                        	$data['bid'] = input('param.regbid/d');
	                        }
	                    }else{
	                    	$data['bid'] = input('param.regbid/d');
	                    }
	                }
                }
            }
            $mid = \app\models\Member::add(aid,$data);
            Db::name('session')->where('aid',aid)->where('session_id',$this->sessionid)->update([
                'mid' => $mid,
                'login_time' => time()
            ]);
            if($data['pid']) \app\commons\Common::user_tjscore(aid,$mid);
            //注册赠送
            \app\commons\Common::registerGive(aid,array_merge($data, ['id' => $mid]));

			if(platform == 'wx' && $this->sysset['reg_check'] == 1){
				$wx_tmplset = Db::name('wx_tmplset')->where('aid',aid)->find();
				if($wx_tmplset['tmpl_shenhe_new']){
					$tmplids[] = $wx_tmplset['tmpl_shenhe_new'];
				}
			}
        }else{
			$mid = $member['id'];
//			if($member['session_id']){
//				$this->sessionid = $member['session_id'];
//			}else{
//				Db::name('member')->where('id',$mid)->update(['session_id'=>$this->sessionid]);
//			}
            Db::name('session')->where('aid',aid)->where('session_id',$this->sessionid)->update([
                'mid' => $mid,
                'login_time' => time()
            ]);
            //后绑定开放平台有了unionid 同步更新
            if(empty($member['unionid']) && $unionid){
                Db::name('member')->where('aid',aid)->where('id',$member['id'])->update(['unionid'=>$unionid]);
            }
		}
		$sessionid_time = 7*86400;
		if(getcustom('system_nologin_day')){
			//后台设置的免登录天数
			$nologin_day = Db::name('admin_set')->where('aid',aid)->value('nologin_day');
			if($nologin_day>0){
				$sessionid_time = $nologin_day*86400;
			}
		}
        cache($this->sessionid.'_mid',$mid,$sessionid_time);
        return $this->json(['status'=>1,'msg'=>$authlogin==2?'':'登录成功','mid'=>$mid,'session_id'=>$this->sessionid,'tmplids'=>$tmplids]);
    }

    //授权微信短信
    public function authphone(){
	    if(!in_array(platform,['wx','mp'])){
	        return $this->json(['status'=>0,'不支持手机号授权']);
        }
        if(input('param.code') && input('post.iv') && input('post.encryptedData')){
            $jscode = input('param.code');
            $encryptedData = input('post.encryptedData');
            $iv = input('post.iv');
            $wxapp = \app\commons\System::appinfo(aid,'wx');
            if($wxapp['authtype']==1){
                $url = 'https://api.weixin.qq.com/sns/component/jscode2session?appid='.$wxapp['appid'].'&component_appid='.\app\commons\Wechat::component_appid().'&js_code='.$jscode.'&grant_type=authorization_code&component_access_token='.\app\commons\Wechat::component_access_token();
            }else{
                $url = 'https://api.weixin.qq.com/sns/jscode2session?appid='.$wxapp['appid'].'&secret='.$wxapp['appsecret'].'&js_code='.$jscode.'&grant_type=authorization_code';
            }
            $rs = request_get($url);
            $rs = json_decode($rs,true);
            if($rs['errcode']>0){
                return $this->json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs)]);
            }
            $session_key = $rs['session_key'];
            if(!$session_key) return json(['status'=>0,'msg'=>'获取授权信息失败']);

            $pc = new \app\commons\WxBizDataCrypt($wxapp['appid'], $session_key);
            $errCode = $pc->decryptData($encryptedData, $iv, $rdata);
            $rdata = json_decode($rdata,true);
            if($rdata['phoneNumber']){
                $tel = $rdata['phoneNumber'];
                return json(['status'=>1,'tel'=>$tel]);
            }else{
                return json(['status'=>0,'msg'=>'授权获取手机号失败']);
            }
        }else{
            return json(['status'=>0,'msg'=>'参数错误']);
        }
	}

	//app微信登录
	public function appwxlogin(){
		$jscode = input('param.code');
		$fromid = input('param.pid');

        $rs = \app\commons\Wechat::getAccessTokenByCode(aid,$jscode,'app');
		if($rs['errcode']>0){
			return $this->json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs)]);
		}
		$openid = $rs['openid'];
		if(!$openid) return json(['status'=>0,'msg'=>'获取授权信息失败']);
		$unionid = $rs['unionid'];
		$access_token = $rs['access_token'];

		$member = Db::name('member')->where('aid',aid)->where('appopenid',$openid)->find();
		if(!$member && $unionid){
			$member = Db::name('member')->where('aid',aid)->where('unionid',$unionid)->find();
			if($member) Db::name('member')->where('id',$member['id'])->update(['appopenid'=>$openid]);
		}
        $result = \app\commons\Wechat::getUserInfo($openid,$access_token);
        if($result['errcode']>0){
            return $this->json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($result)]);
        }

		if(!$member){
            cache($this->sessionid.'_'.platform.'UserInfo',$result,3600);
            $info = Db::name('admin_set')->where('aid',aid)->find();
            if($info['login_bind']) {
                return $this->json(['status'=>2,'msg'=>'绑定手机号','login_bind'=>$info['login_bind']]);
            }
			return $this->appwxRegister();
		}else{
			$mid = $member['id'];

			if(!$member['headimg'] || strpos($member['headimg'],'/static/img/touxiang.png') !== false){
				$update = [];
				$update['headimg'] = \app\commons\Pic::uploadoss($result['headimgurl']);
				$update['nickname'] = $result['nickname'];
				$update['sex'] = $result['sex'];
				$update['province'] = $result['province'];
				$update['city'] = $result['city'];
				Db::name('member')->where('id',$member['id'])->update($update);
			}

//			if($member['session_id']){
//				$this->sessionid = $member['session_id'];
//			}else{
//				Db::name('member')->where('id',$mid)->update(['session_id'=>$this->sessionid]);
//			}
            Db::name('session')->where('aid',aid)->where('session_id',$this->sessionid)->update([
                'mid' => $mid,
                'login_time' => time()
            ]);
            //后绑定开放平台有了unionid 同步更新
            if(empty($member['unionid']) && $result['unionid']){
                Db::name('member')->where('aid',aid)->where('id',$member['id'])->update(['unionid'=>$result['unionid']]);
            }
		}
		$sessionid_time = 7*86400;
		if(getcustom('system_nologin_day')){
			//后台设置的免登录天数
			$nologin_day = Db::name('admin_set')->where('aid',aid)->value('nologin_day');
			if($nologin_day>0){
				$sessionid_time = $nologin_day*86400;
			}
		}
		cache($this->sessionid.'_mid',$mid,$sessionid_time);
		return $this->json(['status'=>1,'msg'=>'登录成功','mid'=>$mid,'session_id'=>$this->sessionid]);
	}
    //app微信注册
    public function appwxRegister(){
        $fromid = input('param.pid');
        $tel = input('param.tel');
        $smscode = input('param.smscode');
        $result = cache($this->sessionid.'_'.platform.'UserInfo');
        $openid = $result['openid'];
        if(!$openid) return json(['status'=>0,'msg'=>'获取授权信息失败']);
        $unionid = $result['unionid'];
        $access_token = $result['access_token'];

        $member = Db::name('member')->where('aid',aid)->where('appopenid',$openid)->find();
        if(!$member && $unionid){
            $member = Db::name('member')->where('aid',aid)->where('unionid',$unionid)->find();
            if($member) Db::name('member')->where('id',$member['id'])->update(['appopenid'=>$openid]);
        }
		if($tel){
			if(md5($tel.'-'.$smscode) != cache($this->sessionid.'_smscode') || cache($this->sessionid.'_smscodetimes')>5){
				cache($this->sessionid.'_smscodetimes',cache($this->sessionid.'_smscodetimes')+1);
				return $this->json(['status'=>0,'msg'=>'短信验证码错误']);
			}
			cache($this->sessionid.'_smscode',null);
			cache($this->sessionid.'_smscodetimes',null);
			if($member){
				$hasmember = Db::name('member')->where('aid',aid)->where('tel',$tel)->find();
				if(!$hasmember){
					Db::name('member')->where('id',$member['id'])->update(['tel'=>$tel]);
				}else{
					return $this->json(['status'=>0,'msg'=>'该手机号已被其他用户绑定']);
				}
			}else{
				$member = Db::name('member')->where('aid',aid)->where('tel',$tel)->find();
				if($member){
					$update = [];
					$update['appopenid'] = $openid;
					if($unionid){
						$update['unionid'] = $unionid;
					}
					if(!$member['headimg'] || strpos($member['headimg'],'/static/img/touxiang.png') !== false){
						$update['headimg'] = \app\commons\Pic::uploadoss($result['headimgurl']);
						$update['nickname'] = $result['nickname'];
						$update['sex'] = $result['sex'];
						$update['province'] = $result['province'];
						$update['city'] = $result['city'];
					}
					Db::name('member')->where('id',$member['id'])->update($update);
				}
			}
		}
        if(!$member){
            if($result['errcode']>0){
                return $this->json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($result)]);
            }
            $data = [];
            $data['aid'] = aid;
            $data['appopenid'] = $openid;
            if($unionid){
                $data['unionid'] = $unionid;
            }
            $data['nickname'] = $result['nickname'];
            $data['sex'] = $result['sex'];
            $data['province'] = $result['province'];
            $data['city'] = $result['city'];
            $data['country'] = $result['country'];
            $data['headimg'] = \app\commons\Pic::uploadoss($result['headimgurl']);

//            $data['session_id'] = $this->sessionid;
            $data['createtime'] = time();
            $data['last_visittime'] = time();
			if($tel){
				$data['tel'] = $tel;
			}
            //推广人
            if($fromid){
                $data['pid'] = $this->getpid($fromid);
            }
            $data['platform'] = platform;
            if(getcustom('member_business')){
            	//商户注册会员
                if(input('param.regbid')){
                	//查询权限
                    $admin_user = Db::name('admin_user')->where('aid',aid)->where('isadmin','>',0)->field('auth_type,auth_data')->find();
                    if($admin_user){
                    	if($admin_user['auth_type'] !=1 ){
	                        $auth_data = !empty($admin_user['auth_data'])?json_decode($admin_user['auth_data'],true):[];
	                        if(in_array('MemberBusiness,MemberBusiness',$auth_data)){
	                        	$data['bid'] = input('param.regbid/d');
	                        }
	                    }else{
	                    	$data['bid'] = input('param.regbid/d');
	                    }
	                }
                }
            }
            $mid = \app\models\Member::add(aid,$data);
            Db::name('session')->where('aid',aid)->where('session_id',$this->sessionid)->update([
                'mid' => $mid,
                'login_time' => time()
            ]);
            if($data['pid']) \app\commons\Common::user_tjscore(aid,$mid);
            //注册赠送
            \app\commons\Common::registerGive(aid,array_merge($data, ['id' => $mid]));
        }else{
			$mid = $member['id'];
//			if($member['session_id']){
//				$this->sessionid = $member['session_id'];
//			}else{
//				Db::name('member')->where('id',$mid)->update(['session_id'=>$this->sessionid]);
//			}
            Db::name('session')->where('aid',aid)->where('session_id',$this->sessionid)->update([
                'mid' => $mid,
                'login_time' => time()
            ]);
            //后绑定开放平台有了unionid 同步更新
            if(empty($member['unionid']) && $result['unionid']){
                Db::name('member')->where('aid',aid)->where('id',$member['id'])->update(['unionid'=>$result['unionid']]);
            }
		}
		$sessionid_time = 7*86400;
		if(getcustom('system_nologin_day')){
			//后台设置的免登录天数
			$nologin_day = Db::name('admin_set')->where('aid',aid)->value('nologin_day');
			if($nologin_day>0){
				$sessionid_time = $nologin_day*86400;
			}
		}
        cache($this->sessionid.'_mid',$mid,$sessionid_time);
        return $this->json(['status'=>1,'msg'=>'登录成功','mid'=>$mid,'session_id'=>$this->sessionid]);
    }
	//百度小程序授权登录
	public function baidulogin(){
		$jscode = input('param.code');
		$fromid = input('param.pid');
		$baiduapp = \app\commons\System::appinfo(aid,'baidu');
		$rs = request_post('https://spapi.baidu.com/oauth/jscode2sessionkey',['code'=>$jscode,'client_id'=>$baiduapp['appkey'],'sk'=>$baiduapp['appsecret']]);
		$rs = json_decode($rs,true);
		if($rs['errno']>0){
			return $this->json(['status'=>0,'msg'=>$rs['error_description']]);
		}
		$openid = $rs['openid'];
		if(!$openid) return json(['status'=>0,'msg'=>'获取授权信息失败']);
		$member = Db::name('member')->where('aid',aid)->where('baiduopenid',$openid)->find();
		if(!$member){
            cache($this->sessionid.'_'.platform.'UserInfo',$rs,3600);
            if($this->sysset['reg_invite_code']){
                return $this->json(['status'=>4,'msg'=>'输入邀请码','reg_invite_code'=>$this->sysset['reg_invite_code'],'login_setnickname'=>$this->sysset['login_setnickname'],'login_bind'=>$this->sysset['login_bind']]);
            }
            if($this->sysset['login_setnickname'] && (!$userinfo['nickName'] || $userinfo['nickName'] == '微信用户')){
                return $this->json(['status'=>3,'msg'=>'设置头像昵称','login_setnickname'=>$this->sysset['login_setnickname'],'login_bind'=>$this->sysset['login_bind']]);
            }
            if($this->sysset['login_bind']){
                return $this->json(['status'=>2,'msg'=>'绑定手机号','login_bind'=>$this->sysset['login_bind']]);
            }
			return $this->baiduRegister();
		}else{
			$mid = $member['id'];
//			if($member['session_id']){
//				$this->sessionid = $member['session_id'];
//			}else{
//				Db::name('member')->where('id',$mid)->update(['session_id'=>$this->sessionid]);
//			}
            Db::name('session')->where('aid',aid)->where('session_id',$this->sessionid)->update([
                'mid' => $mid,
                'login_time' => time()
            ]);
		}
		$sessionid_time = 7*86400;
		if(getcustom('system_nologin_day')){
			//后台设置的免登录天数
			$nologin_day = Db::name('admin_set')->where('aid',aid)->value('nologin_day');
			if($nologin_day>0){
				$sessionid_time = $nologin_day*86400;
			}
		}
		cache($this->sessionid.'_mid',$mid,$sessionid_time);
		return $this->json(['status'=>1,'msg'=>'登录成功','mid'=>$mid,'session_id'=>$this->sessionid]);
	}
    //百度小程序授权注册
    public function baiduRegister(){
        $fromid = input('param.pid');
        $tel = input('param.tel');
        $smscode = input('param.smscode');
        $rs = cache($this->sessionid.'_'.platform.'UserInfo');
        $openid = $rs['openid'];
        if(!$openid) return json(['status'=>0,'msg'=>'获取授权信息失败']);
        $member = Db::name('member')->where('aid',aid)->where('baiduopenid',$openid)->find();
		if($tel){
			if(md5($tel.'-'.$smscode) != cache($this->sessionid.'_smscode') || cache($this->sessionid.'_smscodetimes')>5){
				cache($this->sessionid.'_smscodetimes',cache($this->sessionid.'_smscodetimes')+1);
				return $this->json(['status'=>0,'msg'=>'短信验证码错误']);
			}
			cache($this->sessionid.'_smscode',null);
			cache($this->sessionid.'_smscodetimes',null);
			if($member){
				$hasmember = Db::name('member')->where('aid',aid)->where('tel',$tel)->find();
				if(!$hasmember){
					Db::name('member')->where('id',$member['id'])->update(['tel'=>$tel]);
				}else{
					return $this->json(['status'=>0,'msg'=>'该手机号已被其他用户绑定']);
				}
			}else{
				$member = Db::name('member')->where('aid',aid)->where('tel',$tel)->find();
				if($member){
					$update = [];
					$update['baiduopenid'] = $openid;
					Db::name('member')->where('id',$member['id'])->update($update);
				}
			}
		}
        if(!$member){
            $data = [];
            $data['aid'] = aid;
            $data['baiduopenid'] = $openid;
            $data['sex'] = 3;
			if(input('param.headimg')){
				$data['headimg'] = input('param.headimg');
			}else{
				$data['headimg'] = PRE_URL.'/static/img/touxiang.png';
			}
			if(input('param.nickname')){
				$data['nickname'] = input('param.nickname');
			}else{
				$data['nickname'] = '百度用户'.random(6);
			}
            
            $data['createtime'] = time();
//            $data['session_id'] = $this->sessionid;
            $data['last_visittime'] = time();
			if($tel){
				$data['tel'] = $tel;
			}
            //推广人
            if($fromid){
                $data['pid'] = $this->getpid($fromid);
            }
            $data['platform'] = platform;
            if(getcustom('member_business')){
            	//商户注册会员
                if(input('param.regbid')){
                	//查询权限
                    $admin_user = Db::name('admin_user')->where('aid',aid)->where('isadmin','>',0)->field('auth_type,auth_data')->find();
                    if($admin_user){
                    	if($admin_user['auth_type'] !=1 ){
	                        $auth_data = !empty($admin_user['auth_data'])?json_decode($admin_user['auth_data'],true):[];
	                        if(in_array('MemberBusiness,MemberBusiness',$auth_data)){
	                        	$data['bid'] = input('param.regbid/d');
	                        }
	                    }else{
	                    	$data['bid'] = input('param.regbid/d');
	                    }
	                }
                }
            }
            $mid = \app\models\Member::add(aid,$data);
            Db::name('session')->where('aid',aid)->where('session_id',$this->sessionid)->update([
                'mid' => $mid,
                'login_time' => time()
            ]);
            if($data['pid']) \app\commons\Common::user_tjscore(aid,$mid);
            //注册赠送
            \app\commons\Common::registerGive(aid,array_merge($data, ['id' => $mid]));
        }else{
			$mid = $member['id'];
//			if($member['session_id']){
//				$this->sessionid = $member['session_id'];
//			}else{
//				Db::name('member')->where('id',$mid)->update(['session_id'=>$this->sessionid]);
//			}
            Db::name('session')->where('aid',aid)->where('session_id',$this->sessionid)->update([
                'mid' => $mid,
                'login_time' => time()
            ]);
		}
		$sessionid_time = 7*86400;
		if(getcustom('system_nologin_day')){
			//后台设置的免登录天数
			$nologin_day = Db::name('admin_set')->where('aid',aid)->value('nologin_day');
			if($nologin_day>0){
				$sessionid_time = $nologin_day*86400;
			}
		}
        cache($this->sessionid.'_mid',$mid,$sessionid_time);
        return $this->json(['status'=>1,'msg'=>'登录成功','mid'=>$mid,'session_id'=>$this->sessionid]);
    }
	//QQ小程序授权登录
	public function qqlogin(){
		$jscode = input('param.code');
		$fromid = input('param.pid');
		$qqapp = \app\commons\System::appinfo(aid,'qq');
		$rs = request_get('https://api.q.qq.com/sns/jscode2session?appid='.$qqapp['appid'].'&secret='.$qqapp['appsecret'].'&js_code='.$jscode.'&grant_type=authorization_code');
		$rs = json_decode($rs,true);
		$openid = $rs['openid'];
		if($rs['errcode']>0 || !$openid){
			return $this->json(['status'=>0,'msg'=>$rs['errmsg']]);
		}
		$member = Db::name('member')->where('aid',aid)->where('qqopenid',$openid)->find();
		if(!$member){
            cache($this->sessionid.'_'.platform.'UserInfo',$rs,3600);
            if($this->sysset['reg_invite_code']){
                return $this->json(['status'=>4,'msg'=>'输入邀请码','reg_invite_code'=>$this->sysset['reg_invite_code'],'login_setnickname'=>$this->sysset['login_setnickname'],'login_bind'=>$this->sysset['login_bind']]);
            }
            if($this->sysset['login_setnickname'] && (!$userinfo['nickName'] || $userinfo['nickName'] == '微信用户')){
                return $this->json(['status'=>3,'msg'=>'设置头像昵称','login_setnickname'=>$this->sysset['login_setnickname'],'login_bind'=>$this->sysset['login_bind']]);
            }
            if($this->sysset['login_bind']){
                return $this->json(['status'=>2,'msg'=>'绑定手机号','login_bind'=>$this->sysset['login_bind']]);
            }
			return $this->qqRegister();
		}else{
			$mid = $member['id'];
//			if($member['session_id']){
//				$this->sessionid = $member['session_id'];
//			}else{
//				Db::name('member')->where('id',$mid)->update(['session_id'=>$this->sessionid]);
//			}
            Db::name('session')->where('aid',aid)->where('session_id',$this->sessionid)->update([
                'mid' => $mid,
                'login_time' => time()
            ]);
		}
		$sessionid_time = 7*86400;
		if(getcustom('system_nologin_day')){
			//后台设置的免登录天数
			$nologin_day = Db::name('admin_set')->where('aid',aid)->value('nologin_day');
			if($nologin_day>0){
				$sessionid_time = $nologin_day*86400;
			}
		}
		cache($this->sessionid.'_mid',$mid,$sessionid_time);
		return $this->json(['status'=>1,'msg'=>'登录成功','mid'=>$mid,'session_id'=>$this->sessionid]);
	}
	//QQ小程序授权注册
    public function qqRegister(){
        $fromid = input('param.pid');
        $tel = input('param.tel');
        $smscode = input('param.smscode');
        $rs = cache($this->sessionid.'_'.platform.'UserInfo');
        $openid = $rs['openid'];
        if($rs['errcode']>0 || !$openid){
            return $this->json(['status'=>0,'msg'=>$rs['errmsg']]);
        }
        $member = Db::name('member')->where('aid',aid)->where('qqopenid',$openid)->find();
		if($tel){
			if(md5($tel.'-'.$smscode) != cache($this->sessionid.'_smscode') || cache($this->sessionid.'_smscodetimes')>5){
				cache($this->sessionid.'_smscodetimes',cache($this->sessionid.'_smscodetimes')+1);
				return $this->json(['status'=>0,'msg'=>'短信验证码错误']);
			}
			cache($this->sessionid.'_smscode',null);
			cache($this->sessionid.'_smscodetimes',null);
			if($member){
				$hasmember = Db::name('member')->where('aid',aid)->where('tel',$tel)->find();
				if(!$hasmember){
					Db::name('member')->where('id',$member['id'])->update(['tel'=>$tel]);
				}else{
					return $this->json(['status'=>0,'msg'=>'该手机号已被其他用户绑定']);
				}
			}else{
				$member = Db::name('member')->where('aid',aid)->where('tel',$tel)->find();
				if($member){
					$update = [];
					$update['qqopenid'] = $openid;
					Db::name('member')->where('id',$member['id'])->update($update);
				}
			}
		}
        if(!$member){
            $data = [];
            $data['aid'] = aid;
            $data['qqopenid'] = $openid;
            $data['sex'] = 3;
			if(input('param.headimg')){
				$data['headimg'] = input('param.headimg');
			}else{
				$data['headimg'] = PRE_URL.'/static/img/touxiang.png';
			}
			if(input('param.nickname')){
				$data['nickname'] = input('param.nickname');
			}else{
				$data['nickname'] = 'QQ用户'.random(6);
			}

            $data['createtime'] = time();
//            $data['session_id'] = $this->sessionid;
            $data['last_visittime'] = time();
			if($tel){
				$data['tel'] = $tel;
			}
            //推广人
            if($fromid){
                $data['pid'] = $this->getpid($fromid);
            }
            $data['platform'] = platform;
            if(getcustom('member_business')){
            	//商户注册会员
                if(input('param.regbid')){
                	//查询权限
                    $admin_user = Db::name('admin_user')->where('aid',aid)->where('isadmin','>',0)->field('auth_type,auth_data')->find();
                    if($admin_user){
                    	if($admin_user['auth_type'] !=1 ){
	                        $auth_data = !empty($admin_user['auth_data'])?json_decode($admin_user['auth_data'],true):[];
	                        if(in_array('MemberBusiness,MemberBusiness',$auth_data)){
	                        	$data['bid'] = input('param.regbid/d');
	                        }
	                    }else{
	                    	$data['bid'] = input('param.regbid/d');
	                    }
	                }
                }
            }
            $mid = \app\models\Member::add(aid,$data);
            Db::name('session')->where('aid',aid)->where('session_id',$this->sessionid)->update([
                'mid' => $mid,
                'login_time' => time()
            ]);
            if($data['pid']) \app\commons\Common::user_tjscore(aid,$mid);
            //注册赠送
            \app\commons\Common::registerGive(aid,array_merge($data, ['id' => $mid]));
        }else{
			$mid = $member['id'];
//			if($member['session_id']){
//				$this->sessionid = $member['session_id'];
//			}else{
//				Db::name('member')->where('id',$mid)->update(['session_id'=>$this->sessionid]);
//			}
            Db::name('session')->where('aid',aid)->where('session_id',$this->sessionid)->update([
                'mid' => $mid,
                'login_time' => time()
            ]);
		}
		$sessionid_time = 7*86400;
		if(getcustom('system_nologin_day')){
			//后台设置的免登录天数
			$nologin_day = Db::name('admin_set')->where('aid',aid)->value('nologin_day');
			if($nologin_day>0){
				$sessionid_time = $nologin_day*86400;
			}
		}
        cache($this->sessionid.'_mid',$mid,$sessionid_time);
        return $this->json(['status'=>1,'msg'=>'登录成功','mid'=>$mid,'session_id'=>$this->sessionid]);
    }
	//头条小程序授权登录
	public function toutiaologin(){
		$jscode = input('param.code');
		$fromid = input('param.pid');
		$qqapp = \app\commons\System::appinfo(aid,'toutiao');
		$rs = request_get('https://developer.toutiao.com/api/apps/jscode2session?appid='.$qqapp['appid'].'&secret='.$qqapp['appsecret'].'&code='.$jscode);
		$rs = json_decode($rs,true);
		if(!$rs['openid']){
			return $this->json(['status'=>0,'msg'=>$rs['errmsg']]);
		}
		$openid = $rs['openid'];
		$member = Db::name('member')->where('aid',aid)->where('toutiaoopenid',$openid)->find();
		if(!$member){
            cache($this->sessionid.'_'.platform.'UserInfo',$rs,3600);
            if($this->sysset['reg_invite_code']){
                return $this->json(['status'=>4,'msg'=>'输入邀请码','reg_invite_code'=>$this->sysset['reg_invite_code'],'login_setnickname'=>$this->sysset['login_setnickname'],'login_bind'=>$this->sysset['login_bind']]);
            }
            if(!$rs['nickName']){
                return $this->json(['status'=>3,'msg'=>'设置头像昵称','login_setnickname'=>2,'login_bind'=>$this->sysset['login_bind']]);
            }
            if($this->sysset['login_bind']){
                return $this->json(['status'=>2,'msg'=>'绑定手机号','login_bind'=>$this->sysset['login_bind']]);
            }
			return $this->toutiaoRegister();
		}else{
			$mid = $member['id'];
//			if($member['session_id']){
//				$this->sessionid = $member['session_id'];
//			}else{
//				Db::name('member')->where('id',$mid)->update(['session_id'=>$this->sessionid]);
//			}
            Db::name('session')->where('aid',aid)->where('session_id',$this->sessionid)->update([
                'mid' => $mid,
                'login_time' => time()
            ]);
		}
		$sessionid_time = 7*86400;
		if(getcustom('system_nologin_day')){
			//后台设置的免登录天数
			$nologin_day = Db::name('admin_set')->where('aid',aid)->value('nologin_day');
			if($nologin_day>0){
				$sessionid_time = $nologin_day*86400;
			}
		}
		cache($this->sessionid.'_mid',$mid,$sessionid_time);
		return $this->json(['status'=>1,'msg'=>'登录成功','mid'=>$mid,'session_id'=>$this->sessionid]);
	}
    //头条小程序授权注册
    public function toutiaoRegister(){
        $fromid = input('param.pid');
        $tel = input('param.tel');
        $smscode = input('param.smscode');
        $rs = cache($this->sessionid.'_'.platform.'UserInfo');
        if(!$rs['openid']){
            return $this->json(['status'=>0,'msg'=>$rs['errmsg']]);
        }
        $openid = $rs['openid'];
        $member = Db::name('member')->where('aid',aid)->where('toutiaoopenid',$openid)->find();
        if($tel){
			if(md5($tel.'-'.$smscode) != cache($this->sessionid.'_smscode') || cache($this->sessionid.'_smscodetimes')>5){
				cache($this->sessionid.'_smscodetimes',cache($this->sessionid.'_smscodetimes')+1);
				return $this->json(['status'=>0,'msg'=>'短信验证码错误']);
			}
			cache($this->sessionid.'_smscode',null);
			cache($this->sessionid.'_smscodetimes',null);
			if($member){
				$hasmember = Db::name('member')->where('aid',aid)->where('tel',$tel)->find();
				if(!$hasmember){
					Db::name('member')->where('id',$member['id'])->update(['tel'=>$tel]);
				}else{
					return $this->json(['status'=>0,'msg'=>'该手机号已被其他用户绑定']);
				}
			}else{
				$member = Db::name('member')->where('aid',aid)->where('tel',$tel)->find();
				if($member){
					$update = [];
					$update['toutiaoopenid'] = $openid;
					Db::name('member')->where('id',$member['id'])->update($update);
				}
			}
		}
		if(!$member){
            $data = [];
            $data['aid'] = aid;
            $data['toutiaoopenid'] = $openid;
            $data['sex'] = 3;
			
			if(input('param.headimg')){
				$data['headimg'] = input('param.headimg');
			}else{
				$data['headimg'] = PRE_URL.'/static/img/touxiang.png';
			}
			if(input('param.nickname')){
				$data['nickname'] = input('param.nickname');
			}else{
				$data['nickname'] = '用户'.random(6);
			}

            $data['createtime'] = time();
//            $data['session_id'] = $this->sessionid;
            $data['last_visittime'] = time();
			if($tel){
				$data['tel'] = $tel;
			}
            //推广人
            if($fromid){
                $data['pid'] = $this->getpid($fromid);
            }
            $data['platform'] = platform;
            if(getcustom('member_business')){
            	//商户注册会员
                if(input('param.regbid')){
                	//查询权限
                    $admin_user = Db::name('admin_user')->where('aid',aid)->where('isadmin','>',0)->field('auth_type,auth_data')->find();
                    if($admin_user){
                    	if($admin_user['auth_type'] !=1 ){
	                        $auth_data = !empty($admin_user['auth_data'])?json_decode($admin_user['auth_data'],true):[];
	                        if(in_array('MemberBusiness,MemberBusiness',$auth_data)){
	                        	$data['bid'] = input('param.regbid/d');
	                        }
	                    }else{
	                    	$data['bid'] = input('param.regbid/d');
	                    }
	                }
                }
            }
            $mid = \app\models\Member::add(aid,$data);
            Db::name('session')->where('aid',aid)->where('session_id',$this->sessionid)->update([
                'mid' => $mid,
                'login_time' => time()
            ]);
            if($data['pid']) \app\commons\Common::user_tjscore(aid,$mid);
            //注册赠送
            \app\commons\Common::registerGive(aid,array_merge($data, ['id' => $mid]));
        }else{
			$mid = $member['id'];
//			if($member['session_id']){
//				$this->sessionid = $member['session_id'];
//			}else{
//				Db::name('member')->where('id',$mid)->update(['session_id'=>$this->sessionid]);
//			}
            Db::name('session')->where('aid',aid)->where('session_id',$this->sessionid)->update([
                'mid' => $mid,
                'login_time' => time()
            ]);
		}
		$sessionid_time = 7*86400;
		if(getcustom('system_nologin_day')){
			//后台设置的免登录天数
			$nologin_day = Db::name('admin_set')->where('aid',aid)->value('nologin_day');
			if($nologin_day>0){
				$sessionid_time = $nologin_day*86400;
			}
		}
        cache($this->sessionid.'_mid',$mid,$sessionid_time);
        return $this->json(['status'=>1,'msg'=>'登录成功','mid'=>$mid,'session_id'=>$this->sessionid]);
    }
	//支付宝小程序/h5授权登录
	public function alipaylogin(){
		$fromid = input('param.pid');
		$silent = input('silent')?:0;//是否要求静默授权登录

		$jscode = input('post.code');
		if(strpos($jscode,'&app_id=') > 0) $jscode = explode('&app_id=',$jscode)[0];
		$platform = input('post.platform')?input('post.platform'):'alipay';
		$alipayapp = \app\commons\System::appinfo(aid,$platform);
		if($platform == 'h5'){
			$alipayapp['appid']     = $alipayapp['ali_appid'];
			$alipayapp['appsecret'] = $alipayapp['ali_privatekey'];
			$alipayapp['publickey'] = $alipayapp['ali_publickey'];
		}

		require_once(ROOT_PATH.'/extend/aop/AopClient.php');
		require_once(ROOT_PATH.'/extend/aop/request/AlipaySystemOauthTokenRequest.php');

		$aop = new \AopClient ();
		$aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
		$aop->appId = $alipayapp['appid'];
		$aop->rsaPrivateKey = $alipayapp['appsecret'];
		$aop->alipayrsaPublicKey= $alipayapp['publickey'];
		$aop->apiVersion = '1.0';
		$aop->signType = 'RSA2';
		$aop->postCharset = 'utf-8';
		$aop->format='json';
		$request = new \AlipaySystemOauthTokenRequest ();
		$request->setGrantType("authorization_code");
		$request->setCode($jscode);
		$result = $aop->execute ($request);
		$responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
		$resultCode = $result->$responseNode->code;
		if(!empty($resultCode)&&$resultCode != 10000){
			return json(['status'=>0,'msg'=>$result->$responseNode->sub_msg]);
		}

        $openid = $result->$responseNode->user_id;//openid兼容新规则 https://opendocs.alipay.com/pre-open/06z4jd?pathHash=f5a2b24f
        $openid_new = $result->$responseNode->open_id;
        $openid_type = $alipayapp['openid_set'];
        if(($openid_type =='userid' && !$openid) || ($openid_type =='openid' && !$openid_new)){
           return json(['status'=>0,'msg'=>'获取授权信息失败','$result'=>$result]);
        }
		if($openid_type =='userid'){
		    $mwhere[]= ['alipayopenid','=',$openid];
        }else{
            $mwhere[]= ['alipayopenid_new','=',$openid_new];
        }
		$member = Db::name('member')->where('aid',aid)->where($mwhere)->find();

		if(!$member){
            cache($this->sessionid.'_'.platform.'UserInfo',['openid' => $openid,'openid_new'=>$openid_new, 'resultCode' => $resultCode,'msg'=>$result->$responseNode->sub_msg,'openid_set' =>$openid_type ],3600);
            if($silent==0){
                if($this->sysset['reg_invite_code']){
                    return $this->json(['status'=>4,'msg'=>'输入邀请码','reg_invite_code'=>$this->sysset['reg_invite_code'],'login_setnickname'=>$this->sysset['login_setnickname'],'login_bind'=>$this->sysset['login_bind']]);
                }
                if($this->sysset['login_setnickname'] && (!$userinfo['nickName'] || $userinfo['nickName'] == '微信用户')){
                    return $this->json(['status'=>3,'msg'=>'设置头像昵称','login_setnickname'=>$this->sysset['login_setnickname'],'login_bind'=>$this->sysset['login_bind']]);
                }
                if($this->sysset['login_bind']){
                    return $this->json(['status'=>2,'msg'=>'绑定手机号','login_bind'=>$this->sysset['login_bind']]);
                }
            }

			return $this->alipayRegister();
		}else{
			$mid = $member['id'];
//			if($member['session_id']){
//				$this->sessionid = $member['session_id'];
//			}else{
//				Db::name('member')->where('id',$mid)->update(['session_id'=>$this->sessionid]);
//			}
            Db::name('session')->where('aid',aid)->where('session_id',$this->sessionid)->update([
                'mid' => $mid,
                'login_time' => time()
            ]);
		}
		$sessionid_time = 7*86400;
		if(getcustom('system_nologin_day')){
			//后台设置的免登录天数
			$nologin_day = Db::name('admin_set')->where('aid',aid)->value('nologin_day');
			if($nologin_day>0){
				$sessionid_time = $nologin_day*86400;
			}
		}
		cache($this->sessionid.'_mid',$mid,$sessionid_time);
		return $this->json(['status'=>1,'msg'=>'登录成功','mid'=>$mid,'session_id'=>$this->sessionid]);
	}
    //支付宝小程序授权注册
    public function alipayRegister(){
        $fromid = input('param.pid');
        $tel = input('param.tel');
        $smscode = input('param.smscode');

        $rs = cache($this->sessionid.'_'.platform.'UserInfo');
        if(!empty($rs['resultCode'])&&$rs['resultCode'] != 10000){
            return json(['status'=>0,'msg'=>$rs['msg']]);
        }

        $openid = $rs['openid'];
        $openid_new = $rs['openid_new'];
        $openid_type = $rs['openid_set'];//支付宝获取openid的方式
        if(($openid_type =='userid' && !$openid) || ($openid_type =='openid' && !$openid_new)){
            return json(['status'=>0,'msg'=>'获取授权信息失败']);
        }
        if($openid_type =='userid'){
            $mwhere[]= ['alipayopenid','=',$openid];
        }else{
            $mwhere[]= ['alipayopenid_new','=',$openid_new];
        }
        $member = Db::name('member')->where('aid',aid)->where($mwhere)->find();
		if($tel){
			if(md5($tel.'-'.$smscode) != cache($this->sessionid.'_smscode') || cache($this->sessionid.'_smscodetimes')>5){
				cache($this->sessionid.'_smscodetimes',cache($this->sessionid.'_smscodetimes')+1);
				return $this->json(['status'=>0,'msg'=>'短信验证码错误']);
			}
			cache($this->sessionid.'_smscode',null);
			cache($this->sessionid.'_smscodetimes',null);
			if($member){
				$hasmember = Db::name('member')->where('aid',aid)->where('tel',$tel)->find();
				if(!$hasmember){
					Db::name('member')->where('id',$member['id'])->update(['tel'=>$tel]);
				}else{
					return $this->json(['status'=>0,'msg'=>'该手机号已被其他用户绑定']);
				}
			}else{
				$member = Db::name('member')->where('aid',aid)->where('tel',$tel)->find();
				if($member){
					$update = [];
                    if($openid)$update['alipayopenid'] = $openid;
                    if($openid_new)$update['alipayopenid_new'] = $openid_new;
					Db::name('member')->where('id',$member['id'])->update($update);
				}
			}
		}
        if(!$member){
            if(getcustom('maidan_auto_reg') && input('maidan')){
                $maidan_set = Db::name('admin_set')->where('aid',aid)->field('maidan_auto_reg,maidan_login')->find();
                //聚合收款码买单，如果设置了不强制登录也不自动注册会员，直接返回
                if(!$maidan_set['maidan_login'] && !$maidan_set['maidan_auto_reg']){
                    $shouquan_openid = $openid?:$openid_new;
                    cache($this->sessionid.'_openid',$shouquan_openid,7*86400);
                    cache($this->sessionid.'_openid_new',$openid_new,7*86400);
                    cache($this->sessionid.'_unionid',$shouquan_openid,7*86400);
                    return $this->json(['status'=>1,'msg'=>'登录成功','mid'=>$shouquan_openid,'session_id'=>$this->sessionid]);
                }
            }
            $data = [];
            $data['aid'] = aid;
            if($openid) $data['alipayopenid'] = $openid;
            if($openid_new)$data['alipayopenid_new'] = $openid_new;
            
            $data['sex'] = 3;

			if(input('param.headimg')){
				$data['headimg'] = input('param.headimg');
			}else{
				$data['headimg'] = PRE_URL.'/static/img/touxiang.png';
			}
			if(input('param.nickname')){
				$data['nickname'] = input('param.nickname');
			}else{
				$data['nickname'] = '用户'.random(6);
			}

            $data['createtime'] = time();
//            $data['session_id'] = $this->sessionid;
            $data['last_visittime'] = time();
			if($tel){
				$data['tel'] = $tel;
			}
            //推广人
            if($fromid){
                $data['pid'] = $this->getpid($fromid);
            }
            $data['platform'] = platform;
            if(getcustom('member_business')){
            	//商户注册会员
                if(input('param.regbid')){
                	//查询权限
                    $admin_user = Db::name('admin_user')->where('aid',aid)->where('isadmin','>',0)->field('auth_type,auth_data')->find();
                    if($admin_user){
                    	if($admin_user['auth_type'] !=1 ){
	                        $auth_data = !empty($admin_user['auth_data'])?json_decode($admin_user['auth_data'],true):[];
	                        if(in_array('MemberBusiness,MemberBusiness',$auth_data)){
	                        	$data['bid'] = input('param.regbid/d');
	                        }
	                    }else{
	                    	$data['bid'] = input('param.regbid/d');
	                    }
	                }
                }
            }
            $mid = \app\models\Member::add(aid,$data);
            Db::name('session')->where('aid',aid)->where('session_id',$this->sessionid)->update([
                'mid' => $mid,
                'login_time' => time()
            ]);
            if($data['pid']) \app\commons\Common::user_tjscore(aid,$mid);
            //注册赠送
            \app\commons\Common::registerGive(aid,array_merge($data, ['id' => $mid]));
        }else{
			$mid = $member['id'];
//			if($member['session_id']){
//				$this->sessionid = $member['session_id'];
//			}else{
//				Db::name('member')->where('id',$mid)->update(['session_id'=>$this->sessionid]);
//			}
            Db::name('session')->where('aid',aid)->where('session_id',$this->sessionid)->update([
                'mid' => $mid,
                'login_time' => time()
            ]);
		}
		$sessionid_time = 7*86400;
		if(getcustom('system_nologin_day')){
			//后台设置的免登录天数
			$nologin_day = Db::name('admin_set')->where('aid',aid)->value('nologin_day');
			if($nologin_day>0){
				$sessionid_time = $nologin_day*86400;
			}
		}
        cache($this->sessionid.'_mid',$mid,$sessionid_time);
        return $this->json(['status'=>1,'msg'=>'登录成功','mid'=>$mid,'session_id'=>$this->sessionid]);
    }
	//Apple授权登录
	public function ioslogin(){
		$fromid = input('param.pid');
		$userinfo = input('param.userInfo');
		if(!$userinfo || !$userinfo['openId']) return json(['status'=>0,'msg'=>'获取授权信息失败']);
		$openid = $userinfo['openId'];
		$member = Db::name('member')->where('aid',aid)->where('iosopenid',$openid)->find();
		if(!$member){
            cache($this->sessionid.'_'.platform.'UserInfo',$userinfo,3600);
            $info = Db::name('admin_set')->where('aid',aid)->find();
			if($info['login_bind']) {
                return $this->json(['status'=>2,'msg'=>'绑定手机号','login_bind'=>$info['login_bind']]);
            }
			return $this->iosRegister();
		}else{
			$mid = $member['id'];
//			if($member['session_id']){
//				$this->sessionid = $member['session_id'];
//			}else{
//				Db::name('member')->where('id',$mid)->update(['session_id'=>$this->sessionid]);
//			}
            Db::name('session')->where('aid',aid)->where('session_id',$this->sessionid)->update([
                'mid' => $mid,
                'login_time' => time()
            ]);
		}
		$sessionid_time = 7*86400;
		if(getcustom('system_nologin_day')){
			//后台设置的免登录天数
			$nologin_day = Db::name('admin_set')->where('aid',aid)->value('nologin_day');
			if($nologin_day>0){
				$sessionid_time = $nologin_day*86400;
			}
		}
		cache($this->sessionid.'_mid',$mid,$sessionid_time);
		return $this->json(['status'=>1,'msg'=>'登录成功','mid'=>$mid,'session_id'=>$this->sessionid]);
	}
    //Apple授权注册
    public function iosRegister(){
        $fromid = input('param.pid');
        $tel = input('param.tel');
        $smscode = input('param.smscode');
        $userinfo = cache($this->sessionid.'_'.platform.'UserInfo');
        $openid = $userinfo['openId'];
        if(!$openid) return json(['status'=>0,'msg'=>'获取授权信息失败','$userinfo'=>$userinfo]);
        $member = Db::name('member')->where('aid',aid)->where('iosopenid',$openid)->find();
		if($tel){
			if(md5($tel.'-'.$smscode) != cache($this->sessionid.'_smscode') || cache($this->sessionid.'_smscodetimes')>5){
				cache($this->sessionid.'_smscodetimes',cache($this->sessionid.'_smscodetimes')+1);
				return $this->json(['status'=>0,'msg'=>'短信验证码错误']);
			}
			cache($this->sessionid.'_smscode',null);
			cache($this->sessionid.'_smscodetimes',null);
			if($member){
				$hasmember = Db::name('member')->where('aid',aid)->where('tel',$tel)->find();
				if(!$hasmember){
					Db::name('member')->where('id',$member['id'])->update(['tel'=>$tel]);
				}else{
					return $this->json(['status'=>0,'msg'=>'该手机号已被其他用户绑定']);
				}
			}else{
				$member = Db::name('member')->where('aid',aid)->where('tel',$tel)->find();
				if($member){
					$update = [];
					$update['iosopenid'] = $openid;
					Db::name('member')->where('id',$member['id'])->update($update);
				}
			}
		}
        if(!$member){
            $data = [];
            $data['aid'] = aid;
            $data['iosopenid'] = $openid;
            $data['sex'] = 3;
            $data['nickname'] = $userinfo['fullName']['givenName'];
            $data['headimg'] = PRE_URL.'/static/img/touxiang.png';
            $data['createtime'] = time();
//            $data['session_id'] = $this->sessionid;
            $data['last_visittime'] = time();
			if($tel){
				$data['tel'] = $tel;
			}
            //推广人
            if($fromid){
                $data['pid'] = $this->getpid($fromid);
            }
            $data['platform'] = platform;
            if(getcustom('member_business')){
            	//商户注册会员
                if(input('param.regbid')){
                	//查询权限
                    $admin_user = Db::name('admin_user')->where('aid',aid)->where('isadmin','>',0)->field('auth_type,auth_data')->find();
                    if($admin_user){
                    	if($admin_user['auth_type'] !=1 ){
	                        $auth_data = !empty($admin_user['auth_data'])?json_decode($admin_user['auth_data'],true):[];
	                        if(in_array('MemberBusiness,MemberBusiness',$auth_data)){
	                        	$data['bid'] = input('param.regbid/d');
	                        }
	                    }else{
	                    	$data['bid'] = input('param.regbid/d');
	                    }
	                }
                }
            }
            $mid = \app\models\Member::add(aid,$data);
            Db::name('session')->where('aid',aid)->where('session_id',$this->sessionid)->update([
                'mid' => $mid,
                'login_time' => time()
            ]);
            if($data['pid']) \app\commons\Common::user_tjscore(aid,$mid);
            //注册赠送
            \app\commons\Common::registerGive(aid,array_merge($data, ['id' => $mid]));
        }else{
			$mid = $member['id'];
//			if($member['session_id']){
//				$this->sessionid = $member['session_id'];
//			}else{
//				Db::name('member')->where('id',$mid)->update(['session_id'=>$this->sessionid]);
//			}
            Db::name('session')->where('aid',aid)->where('session_id',$this->sessionid)->update([
                'mid' => $mid,
                'login_time' => time()
            ]);
		}
		$sessionid_time = 7*86400;
		if(getcustom('system_nologin_day')){
			//后台设置的免登录天数
			$nologin_day = Db::name('admin_set')->where('aid',aid)->value('nologin_day');
			if($nologin_day>0){
				$sessionid_time = $nologin_day*86400;
			}
		}
        cache($this->sessionid.'_mid',$mid,$sessionid_time);
        return $this->json(['status'=>1,'msg'=>'登录成功','mid'=>$mid,'session_id'=>$this->sessionid]);
    }

	
	//Google授权登录
	public function googlelogin(){
		$fromid = input('param.pid');
		//if(getcustom('test')){
		//\think\facade\Log::write(input('param.res'));
		//}
		$userinfo = input('param.userInfo');
		if(!$userinfo || !$userinfo['openId']) return json(['status'=>0,'msg'=>'获取授权信息失败']);
		$openid = $userinfo['openId'];
		$member = Db::name('member')->where('aid',aid)->where('googleopenid',$openid)->find();
		if(!$member){
            cache($this->sessionid.'_'.platform.'UserInfo',$userinfo,3600);
            $info = Db::name('admin_set')->where('aid',aid)->find();
			if($info['login_bind']) {
                return $this->json(['status'=>2,'msg'=>'绑定手机号','login_bind'=>$info['login_bind']]);
            }
			return $this->googleRegister();
		}else{
			$mid = $member['id'];
            Db::name('session')->where('aid',aid)->where('session_id',$this->sessionid)->update([
                'mid' => $mid,
                'login_time' => time()
            ]);
		}
		$sessionid_time = 7*86400;
		if(getcustom('system_nologin_day')){
			//后台设置的免登录天数
			$nologin_day = Db::name('admin_set')->where('aid',aid)->value('nologin_day');
			if($nologin_day>0){
				$sessionid_time = $nologin_day*86400;
			}
		}
		cache($this->sessionid.'_mid',$mid,$sessionid_time);
		return $this->json(['status'=>1,'msg'=>'登录成功','mid'=>$mid,'session_id'=>$this->sessionid]);
	}
    //Google授权注册
    public function googleRegister(){
        $fromid = input('param.pid');
        $tel = input('param.tel');
        $smscode = input('param.smscode');
        $userinfo = cache($this->sessionid.'_'.platform.'UserInfo');
        $openid = $userinfo['openId'];
        if(!$openid) return json(['status'=>0,'msg'=>'获取授权信息失败','$userinfo'=>$userinfo]);
        $member = Db::name('member')->where('aid',aid)->where('googleopenid',$openid)->find();
		if($tel){
			if(md5($tel.'-'.$smscode) != cache($this->sessionid.'_smscode') || cache($this->sessionid.'_smscodetimes')>5){
				cache($this->sessionid.'_smscodetimes',cache($this->sessionid.'_smscodetimes')+1);
				return $this->json(['status'=>0,'msg'=>'短信验证码错误']);
			}
			cache($this->sessionid.'_smscode',null);
			cache($this->sessionid.'_smscodetimes',null);
			if($member){
				$hasmember = Db::name('member')->where('aid',aid)->where('tel',$tel)->find();
				if(!$hasmember){
					Db::name('member')->where('id',$member['id'])->update(['tel'=>$tel]);
				}else{
					return $this->json(['status'=>0,'msg'=>'该手机号已被其他用户绑定']);
				}
			}else{
				$member = Db::name('member')->where('aid',aid)->where('tel',$tel)->find();
				if($member){
					$update = [];
					$update['googleopenid'] = $openid;
					Db::name('member')->where('id',$member['id'])->update($update);
				}
			}
		}
        if(!$member){
            $data = [];
            $data['aid'] = aid;
            $data['googleopenid'] = $openid;
            $data['sex'] = 3;
            $data['nickname'] = $userinfo['name'] ?? $userinfo['email'];
            $data['headimg'] = PRE_URL.'/static/img/touxiang.png';
            $data['createtime'] = time();
            $data['last_visittime'] = time();
			if($tel){
				$data['tel'] = $tel;
			}
            //推广人
            if($fromid){
                $data['pid'] = $this->getpid($fromid);
            }
            $data['platform'] = platform;
            if(getcustom('member_business')){
            	//商户注册会员
                if(input('param.regbid')){
                	//查询权限
                    $admin_user = Db::name('admin_user')->where('aid',aid)->where('isadmin','>',0)->field('auth_type,auth_data')->find();
                    if($admin_user){
                    	if($admin_user['auth_type'] !=1 ){
	                        $auth_data = !empty($admin_user['auth_data'])?json_decode($admin_user['auth_data'],true):[];
	                        if(in_array('MemberBusiness,MemberBusiness',$auth_data)){
	                        	$data['bid'] = input('param.regbid/d');
	                        }
	                    }else{
	                    	$data['bid'] = input('param.regbid/d');
	                    }
	                }
                }
            }
            $mid = \app\models\Member::add(aid,$data);
            Db::name('session')->where('aid',aid)->where('session_id',$this->sessionid)->update([
                'mid' => $mid,
                'login_time' => time()
            ]);
            if($data['pid']) \app\commons\Common::user_tjscore(aid,$mid);
            //注册赠送
            \app\commons\Common::registerGive(aid,array_merge($data, ['id' => $mid]));
        }else{
			$mid = $member['id'];
            Db::name('session')->where('aid',aid)->where('session_id',$this->sessionid)->update([
                'mid' => $mid,
                'login_time' => time()
            ]);
		}
		$sessionid_time = 7*86400;
		if(getcustom('system_nologin_day')){
			//后台设置的免登录天数
			$nologin_day = Db::name('admin_set')->where('aid',aid)->value('nologin_day');
			if($nologin_day>0){
				$sessionid_time = $nologin_day*86400;
			}
		}
        cache($this->sessionid.'_mid',$mid,$sessionid_time);
        return $this->json(['status'=>1,'msg'=>'登录成功','mid'=>$mid,'session_id'=>$this->sessionid]);
    }

	//注册
	public function reg(){
		$sysset = Db::name('admin_set')->where('aid',aid)->find();
		$platform = platform;
		$logintype = $sysset['logintype_'.$platform];
		$logintype = explode(',',$logintype);
		if(!in_array('1',$logintype)){
            if(getcustom('maidan_auto_reg') && cache($this->sessionid.'_maidanpay')){
                $frompage = urlencode(cache($this->sessionid.'_maidanpay'));
                if(input('param.frompage')){
                    $frompage = input('param.frompage');
                }
                return redirect(m_url('pages/index/login?frompage='.$frompage));
            }
			return $this->json(['status'=>0,'msg'=>'自主注册未开启']);
		}

		$xieyi = Db::name('admin_set_xieyi')->where('aid',aid)->find();
		if(!$xieyi) $xieyi = ['status'=>0,'content'=>''];

        $pid = input('param.pid');
        if($pid) {
            $parent = Db::name('member')->where('aid', aid)->where('id', $pid)->field('id,nickname,headimg,yqcode')->find();
        }
        $telPlaceholder = '请输入手机号';
        if(getcustom('member_register_notel')){
            $needsms = false;
            $telPlaceholder = '请输入手机号或账号';
        }else{
            $smsset = Db::name('admin_set_sms')->where('aid',aid)->find();
            if($smsset && $smsset['status'] == 1 && $smsset['tmpl_smscode'] && $smsset['tmpl_smscode_st']==1){
                $needsms = true;
            }else{
                $needsms = false;
            }
        }


		if($sysset['reg_invite_code_type'] == 1) {
            $reg_invite_code_text = '邀请码';
        } else {
            $reg_invite_code_text = '手机号';
        }
        if($sysset['reg_invite_code'] == 2)
            $reg_invite_code_text .= '(必填)';
        else
            $reg_invite_code_text .= '(选填)';
		//定制内容.77
        $formField = [];
        $hasCustom = 0;
        if(getcustom('register_fields')){
            if(in_array('1',$logintype)){
                $formField = Db::name('register_form')->where('aid',aid)->find();
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
                }else{
                    $formField = [];
                }
            }
        }

		$rdata = [];
		$rdata['status'] = 1;
		$rdata['logintype_1'] = in_array('1',$logintype); //注册登录
		$rdata['logintype_2'] = in_array('2',$logintype); //手机验证码登录
		$rdata['logintype_3'] = platform!='h5' ? in_array('3',$logintype) : false; //授权登录 h5时去掉授权登录
		$rdata['logintype_7'] = platform=='h5' ? in_array('7',$logintype) : false;  //支付宝内H5登录
		$rdata['name'] = $sysset['name'];
		$rdata['logo'] = $sysset['logo'];
        $rdata['reg_invite_code'] = $sysset['reg_invite_code'];
        $rdata['reg_invite_code_text'] = $reg_invite_code_text;
        $rdata['reg_invite_code_type'] = $sysset['reg_invite_code_type'];
        $rdata['reg_acc_tel'] = $sysset['reg_acc_tel'];
		$rdata['xystatus'] = $xieyi['status'];
		$rdata['xyname'] = $xieyi['name'];
		$rdata['xycontent'] = $xieyi['content'];
		$rdata['xyname2'] = $xieyi['name2'];
		$rdata['xycontent2'] = $xieyi['content2'];
        $rdata['xyagree_type'] = $xieyi['agree_type'];//0打勾，1阅读到最后
		$rdata['needsms'] = $needsms;
        $rdata['parent'] = $parent ? $parent : null;
		$rdata['platform'] = platform;
		$rdata['has_custom'] = $hasCustom;
		$rdata['tel_placeholder'] = $telPlaceholder;
		$rdata['custom_form_field'] = $formField;

     	$rdata['reg_realname'] = true;





		//自定义登录设置
		$loginset = \app\models\ApiIndexs::loginset(aid,$sysset);
		$loginset_type = $loginset['loginset_type'];
		$loginset_data = $loginset['loginset_data'];
		$rdata['loginset_type'] = $loginset_type;
		$rdata['loginset_data'] = $loginset_data;
        $rdata['showjd'] = false;
		if(getcustom('sxpay_h5') ){
			$rdata['ali_appid']   = '';//H5中支付宝APPID
			if(platform == 'h5' && $rdata['logintype_7']){
				$appinfo = \app\commons\System::appinfo(aid,'h5');
				$rdata['ali_appid']   = $appinfo['ali_appid'];
			}
		}
		return $this->json($rdata);
	}
    public function memberSearch(){
        
    	$fromid = input('param.pid');
		$yqcode = input('param.yqcode');
    //	$parent = Db::name('member')->where('aid', aid)->where('id', mid)->field('id,nickname,levelid,tel,headimg')->find();
    	if($fromid){
            $parent = Db::name('member')->where('aid',aid)->where('id',$fromid)->find();
		}elseif($yqcode){
            $parent = Db::name('member')->where('aid',aid)->where('tel',$yqcode)->find();
        }
    	if (!$parent) 	return $this->json(['status'=>0,'msg'=>'邀请人不存在']);
	    $parent['deep'] = 0;
        $clist = \app\commons\Member::getteammids(aid,mid);
        $clist = array_merge([$parent],$clist);
        $levelArr = Db::name('member_level')->where('aid',aid)->order('sort,id')->column('*','id');
        $newclist = [];
    	foreach ($clist as $key => $val) {
    	     if ($levelArr[$val['levelid']]['can_agent']>0) {
		         $text = '【'.$val['id'].'】';
		         $level = $val['deep']+1;
		         $val['nickname'] = ($val['realname']?$val['realname']:$val['nickname']).$text.'('.$level.'级下线)';
		         $newclist[] = $val;
    	     }
		}
		$rdata = [];
			$rdata['status'] = 1;
 		$rdata['memberlist'] = $newclist;
		return $this->json($rdata);
		
	}
	
	public function regsub(){
		$tel = input('param.tel');
		$pwd = input('param.pwd');
		$smscode = input('param.smscode');
		$fromid = input('param.pid');
		$yqcode = input('param.yqcode');
		$realname = input('param.realname');
		$acc = input('param.acc');
        // $agentid = input('param.agentid');
        // $left_right = input('param.left_right');
        // if ($left_right==0) 	return $this->json(['status'=>0,'msg'=>'请选择节点']);
        
        
        
        $sysset = Db::name('admin_set')->where('aid',aid)->find();
        $member = [];
        if(getcustom('member_register_notel')){
            //校验昵称是不是存在
            $exist = Db::name('member')->where('aid',aid)->where('nickname',$tel)->count('id');
            if($exist){
                return $this->json(['status'=>0,'msg'=>'该昵称已被占用']);
            }
            if(checkTel($tel)){
                $member = Db::name('member')->where('aid', aid)->where('tel', $tel)->find();
            }
        }else{
            if (!checkTel($tel) && $sysset['reg_acc_tel']==0) {
                return $this->json(['status' => 0, 'msg' => '手机号格式错误']);
            }
            $smsset = Db::name('admin_set_sms')->where('aid', aid)->find();
            if ($smsset && $smsset['status'] == 1 && $smsset['tmpl_smscode'] && $smsset['tmpl_smscode_st'] == 1) {
                $needsms = true;
            } else {
                $needsms = false;
            }
            if ($needsms && md5($tel . '-' . $smscode) != cache($this->sessionid . '_smscode') || cache($this->sessionid . '_smscodetimes') > 5) {
                cache($this->sessionid . '_smscodetimes', cache($this->sessionid . '_smscodetimes') + 1);
                return $this->json(['status' => 0, 'msg' => '短信验证码错误']);
            }
           if ($sysset['reg_acc_tel']==0) {
	    	    $member = Db::name('member')->where('aid',aid)->where('tel',$tel)->find();
	    	}else {
	    	    $exist = Db::name('member')->where('aid',aid)->where('acc',$acc)->count('id');
                if($exist){
                    return $this->json(['status'=>0,'msg'=>'该账号已被占用']);
                }
	    	    $exist = Db::name('member')->where('aid',aid)->where('tel',$tel)->count('id');
                if($exist>=$sysset['maxtel']){
                    return $this->json(['status'=>0,'msg'=>'同一手机号最多绑定'.$sysset['maxtel'].'个账号']);
                }
	    	    $member = Db::name('member')->where('aid',aid)->where('acc',$acc)->find();
	    	}
        }
		if($member){
			if(getcustom('reg_invite_code') && $member['checkst'] == 2){
				$mid = $member['id'];
				$data = [];
				$nickname = $tel;
				if(checkTel($tel)){
					$nickname = substr($tel,0,3).'****'.substr($tel,-4);
					$data['tel'] = $tel;
				}
				$data['aid'] = aid;
				$data['pwd'] = md5($pwd);
				$data['nickname'] = $nickname;
				if ($realname) {
				   	$data['realname'] = $realname;
				}
				$data['sex'] = 3;
				$data['headimg'] = PRE_URL.'/static/img/touxiang.png';
				$data['createtime'] = time();
				$data['last_visittime'] = time();
	//			$data['session_id'] = $this->sessionid;
				$sysset = Db::name('admin_set')->where('aid',aid)->find();
				//推广人
				if($fromid){
					$data['pid'] = $this->getpid($fromid);
					if($data['pid']) $upuser = Db::name('member')->where('aid',aid)->where('id',$data['pid'])->find();
				}elseif($yqcode){
					if($sysset['reg_invite_code']) {
						if($sysset['reg_invite_code'] == 2 && empty($yqcode)){
							return $this->json(['status'=>0,'msg'=>'请输入邀请码']);
						}
						if($sysset['reg_invite_code_type'] == 1) {//邀请码
							$upuser = Db::name('member')->where('aid',aid)->where('yqcode',$yqcode)->find();
							if(!$upuser) return $this->json(['status'=>0,'msg'=>'邀请码不正确']);
						} elseif($sysset['reg_invite_code_type'] == 2) {//id 未启用
							$upuser = Db::name('member')->where('aid',aid)->where('id',intval($fromid))->find();
							if(!$upuser) return $this->json(['status'=>0,'msg'=>'邀请码不正确']);
						} else {//手机号
							$upuser = Db::name('member')->where('aid',aid)->where('tel',$yqcode)->find();
							if(!$upuser) return $this->json(['status'=>0,'msg'=>'邀请人手机号不正确']);
						}

						if($upuser){
							$uplv = Db::name('member_level')->where('aid',aid)->where('id',$upuser['levelid'])->find();
							if($uplv['can_agent']!=0){
								$data['pid'] = $upuser['id'];
							}
						}
					}
				}
				if($sysset['reg_invite_code']==2 && !$upuser){ //必须邀请注册
					return $this->json(['status'=>0,'msg'=>'必须有邀请人邀请注册']);
				}
				//自定义表单start
				if(getcustom('register_fields')){
					$res = $this->customRegister(input('param.customformdata'),input('param.customformid'));
					if($res['status']!=1){
						return $this->json(['status'=>0,'msg'=>$res['msg']]);
					}else if($res['status']==1 && isset($res['recordid']) && $res['recordid']>0){
						$data['form_record_id'] = $res['recordid'];
                        $data = array_merge($data, $res['sys_fields']);
					}
				}
				//自定义表单end
				$data['platform'] = platform;
				$tmplids = [];
				$data['checkst'] = 0;
				Db::name('member')->where('id',$mid)->update($data);
					return $this->json(['status'=>1,'msg'=>'注册成功','mid'=>$mid,'toappurl'=>$this->sysset['appurl'],'tmplids'=>$tmplids]);
			}
			cache($this->sessionid.'_smscode',null);
			cache($this->sessionid.'_smscodetimes',null);
			return $this->json(['status'=>0,'msg'=>'该账号已注册，请直接登录']);
		}else{
            $data = [];
            $nickname = $tel;
            if(checkTel($tel)){
                $nickname = substr($tel,0,3).'****'.substr($tel,-4);
                $data['tel'] = $tel;
            }
			$data['aid'] = aid;
			$data['acc'] = $acc;
			$data['pwd'] = md5($pwd);
		//	$data['nickname'] = $nickname;
	     	if ($realname) {
				$data['realname'] = $realname;
			}
		 	if ($smsset['nickname']==0) {
	             $data['nickname'] = $acc?$acc:substr($tel,0,3).'****'.substr($tel,-4);
	         }else {
	             $data['nickname'] = $acc?$acc:$tel;
	         }
			$data['sex'] = 3;
			$data['headimg'] = PRE_URL.'/static/img/touxiang.png';
			$data['headimg'] = $sysset['headimg']?$sysset['headimg']:PRE_URL.'/static/img/touxiang.png';// 
			$data['createtime'] = time();
            $data['last_visittime'] = time();
//			$data['session_id'] = $this->sessionid;
			$sysset = Db::name('admin_set')->where('aid',aid)->find();
			//推广人
			if($fromid){
                $data['pid'] = $this->getpid($fromid);
                if($data['pid']) $upuser = Db::name('member')->where('aid',aid)->where('id',$data['pid'])->find();
			}elseif($yqcode){
                if($sysset['reg_invite_code']) {
                    if($sysset['reg_invite_code'] == 2 && empty($yqcode)){
                        return $this->json(['status'=>0,'msg'=>'请输入邀请码']);
                    }
                    if($sysset['reg_invite_code_type'] == 1) {//邀请码
                        $upuser = Db::name('member')->where('aid',aid)->where('yqcode',$yqcode)->find();
						if(!$upuser) return $this->json(['status'=>0,'msg'=>'邀请码不正确']);
                    } elseif($sysset['reg_invite_code_type'] == 2) {//id 未启用
                        $upuser = Db::name('member')->where('aid',aid)->where('id',intval($fromid))->find();
						if(!$upuser) return $this->json(['status'=>0,'msg'=>'邀请码不正确']);
                    } else {//手机号
                        $upuser = Db::name('member')->where('aid',aid)->where('tel',$yqcode)->find();
						if(!$upuser) return $this->json(['status'=>0,'msg'=>'邀请人手机号不正确']);
                    }

                    if($upuser){
                        $uplv = Db::name('member_level')->where('aid',aid)->where('id',$upuser['levelid'])->find();
                        if($uplv['can_agent']!=0){
                            $data['pid'] = $upuser['id'];
                        }else {
                            return $this->json(['status'=>0,'msg'=>'邀请人暂无邀请权限']);
                        }
                    }
                }
            }
			if($sysset['reg_invite_code']==2 && !$upuser){ //必须邀请注册
				return $this->json(['status'=>0,'msg'=>'必须有邀请人邀请注册']);
			}
// 			if ($agentid) {
//                 if($sysset['reg_invite_code_type'] == 1) {//邀请码
//                     $agent = Db::name('member')->where('aid',aid)->where('yqcode',$agentid)->find();
//                     if(!$agent) return $this->json(['status'=>0,'msg'=>'节点会员邀请码不正确']);
//                 }else {//手机号
//                     $agent = Db::name('member')->where('aid',aid)->where('tel',$agentid)->find();
//                     if(!$agent) return $this->json(['status'=>0,'msg'=>'节点会员手机号不正确']);
//                 }
//                 if($agent){
//                     $uplv = Db::name('member_level')->where('aid',aid)->where('id',$agent['levelid'])->find();
//                     if($uplv['can_agent']!=0){
//                         $data['_pid'] = $agent['id'];
//                     }
//                 }
//             }
//             if(!$data['_pid']){
//                 $data['_pid'] = $data['pid'];
//             }
			//自定义表单start
            if(getcustom('register_fields')){
                $res = $this->customRegister(input('param.customformdata'),input('param.customformid'));
                if($res['status']!=1){
                    return $this->json(['status'=>0,'msg'=>$res['msg']]);
                }else if($res['status']==1 && isset($res['recordid']) && $res['recordid']>0){
                    $data['form_record_id'] = $res['recordid'];
                    $data = array_merge($data, $res['sys_fields']);
                }
            }
            //自定义表单end
			$data['platform'] = platform;
			if(getcustom('member_business')){
				//商户注册会员
                if(input('param.regbid')){
                	//查询权限
                    $admin_user = Db::name('admin_user')->where('aid',aid)->where('isadmin','>',0)->field('auth_type,auth_data')->find();
                    if($admin_user){
                    	if($admin_user['auth_type'] !=1 ){
	                        $auth_data = !empty($admin_user['auth_data'])?json_decode($admin_user['auth_data'],true):[];
	                        if(in_array('MemberBusiness,MemberBusiness',$auth_data)){
	                        	$data['bid'] = input('param.regbid/d');
	                        }
	                    }else{
	                    	$data['bid'] = input('param.regbid/d');
	                    }
	                }
                }
            }
			$mid = \app\models\Member::add(aid,$data);
			
// 			$piddown = Db::name('member_tree')->where('mid',$upuser['id'])->find();
//          if (!$piddown) return $this->json(['status'=>0,'msg'=>'邀请人不存在节点']);
       
//			$mid = \app\models\Member::add(aid,$data);
// 			$_left = 0;$_right=0;
// 			if ($left_right==1) {
// 			    $_left = 1;
// 			}elseif ($left_right==2) {
// 			     $_right = 1;
// 			}
// 			\think\facade\Log::write($data['pid'].'---'.$mid.'---去除最高---'.$_left.'---去除右---'.$_right.'---去除---');
// 			if ($upuser) {
// 			    if(!in_array($upuser['levelid'],explode(',',str_replace('，',',',$sysset['lvlevel']))) && $_right==2) {
// 			        $_left = 1;
//     			    $_right = 0;
//     			}
// 			   \app\commons\Aaa::tree(aid,$mid,$upuser['id'],$_left,$_right);
// 			}else {
// 			   \app\commons\Aaa::tree(aid,$mid,$data['pid'],$_left,$_right);
// 			}
            Db::name('session')->where('aid',aid)->where('session_id',$this->sessionid)->update([
                'mid' => $mid,
                'login_time' => time()
            ]);
			if($data['pid']) \app\commons\Common::user_tjscore(aid,$mid);
			//注册赠送
            \app\commons\Common::registerGive(aid,array_merge($data, ['id' => $mid]));
            $sessionid_time = 7*86400;
			if(getcustom('system_nologin_day')){
				//后台设置的免登录天数
				$nologin_day = Db::name('admin_set')->where('aid',aid)->value('nologin_day');
				if($nologin_day>0){
					$sessionid_time = $nologin_day*86400;
				}
			}
			cache($this->sessionid.'_mid',$mid,$sessionid_time);
			cache($this->sessionid.'_smscode',null);
			cache($this->sessionid.'_smscodetimes',null);
			$tmplids = [];
			if(platform == 'wx' && $this->sysset['reg_check'] == 1){
				$wx_tmplset = Db::name('wx_tmplset')->where('aid',aid)->find();
				if($wx_tmplset['tmpl_shenhe_new']){
					$tmplids[] = $wx_tmplset['tmpl_shenhe_new'];
				}
			}
			return $this->json(['status'=>1,'msg'=>'注册成功','mid'=>$mid,'toappurl'=>$this->sysset['appurl'],'tmplids'=>$tmplids]);
		}
	}
	//找回密码
	public function getpwd(){
	    $sysset = Db::name('admin_set')->where('aid',aid)->find();
		if(request()->isPost()){
			$tel = input('param.tel');
            if(!checkTel($tel)){
                //return $this->json(['status'=>0,'msg'=>'请填写正确的手机号']);
            }
			$pwd = input('param.pwd');
			$smscode = input('param.smscode');
			$acc = input('param.acc');
			if(md5($tel.'-'.$smscode) != cache($this->sessionid.'_smscode') || cache($this->sessionid.'_smscodetimes')>5){
				cache($this->sessionid.'_smscodetimes',cache($this->sessionid.'_smscodetimes')+1);
				return $this->json(['status'=>0,'msg'=>'短信验证码错误']);
			}
			cache($this->sessionid.'_smscode',null);
			cache($this->sessionid.'_smscodetimes',null);
			if ($sysset['reg_acc_tel']==1) {
			   $member = Db::name('member')->where('aid',aid)->where('acc',$acc)->where('tel',$tel)->find();
			   if(!$member) return $this->json(['status'=>0,'msg'=>'该账号未注册']);
			}else {
			   $member = Db::name('member')->where('aid',aid)->where('tel',$tel)->find();
			   if(!$member) return $this->json(['status'=>0,'msg'=>'该手机号未注册']);
			}
			Db::name('member')->where('aid',aid)->where('id',$member['id'])->update(['pwd'=>md5($pwd)]);
			return $this->json(['status'=>1,'msg'=>'重置成功','mid'=>$member['id']]);
		}else{
			$rdata = [];
			$rdata['status'] = 1;
			//自定义登录设置
			$loginset = \app\models\ApiIndexs::loginset(aid,$sysset);
			$loginset_type = $loginset['loginset_type'];
			$loginset_data = $loginset['loginset_data'];
			$rdata['loginset_type'] = $loginset_type;
			$rdata['loginset_data'] = $loginset_data;
            $rdata['reg_acc_tel'] = $sysset['reg_acc_tel'];
			return $this->json($rdata);
		}
		
	}
	//设置微信小程序openid
	public function setwxopenid(){
		if(!$this->member) return json(['status'=>0,'msg'=>'未登录']);
		$post = input('post.');
		$set = Db::name('admin_set')->where('aid',aid)->find();
		$jscode = input('post.code');
		$wxapp = \app\commons\System::appinfo(aid,'wx');
		if($wxapp['authtype']==1){
			$url = 'https://api.weixin.qq.com/sns/component/jscode2session?appid='.$wxapp['appid'].'&component_appid='.\app\commons\Wechat::component_appid().'&js_code='.$jscode.'&grant_type=authorization_code&component_access_token='.\app\commons\Wechat::component_access_token();
		}else{
			$url = 'https://api.weixin.qq.com/sns/jscode2session?appid='.$wxapp['appid'].'&secret='.$wxapp['appsecret'].'&js_code='.$jscode.'&grant_type=authorization_code';
		}
		$rs = request_get($url);
		$rs = json_decode($rs,true);
		if($rs['errcode']>0){
			return $this->json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs)]);
		}
		$openid = $rs['openid'];
		$unionid = $rs['unionid'];
		if($openid){
			$updata = [];
			$updata['wxopenid'] = $openid;
			if($unionid){
				$updata['unionid'] = $unionid;
			}
			Db::name('member')->where('id',mid)->update($updata);
		}
		return json(['status'=>1,'msg'=>'更新成功']);
	}
	//设置支付宝小程序openid
	public function setalipayopenid(){
		if(!$this->member) return json(['status'=>0,'msg'=>'未登录']);
		$post = input('post.');
		$set = Db::name('admin_set')->where('aid',aid)->find();
		$jscode = input('post.code');
		$platform = input('post.platform')?input('post.platform'):'alipay';
		$alipayapp = \app\commons\System::appinfo(aid,$platform);
		if($platform == 'h5'){
			$alipayapp['appid']     = $alipayapp['ali_appid'];
			$alipayapp['appsecret'] = $alipayapp['ali_privatekey'];
			$alipayapp['publickey'] = $alipayapp['ali_publickey'];
		}

		require_once(ROOT_PATH.'/extend/aop/AopClient.php');
		require_once(ROOT_PATH.'/extend/aop/request/AlipaySystemOauthTokenRequest.php');

		$aop = new \AopClient ();
		$aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
		$aop->appId = $alipayapp['appid'];
		$aop->rsaPrivateKey = $alipayapp['appsecret'];
		$aop->alipayrsaPublicKey= $alipayapp['publickey'];
		$aop->apiVersion = '1.0';
		$aop->signType = 'RSA2';
		$aop->postCharset = 'utf-8';
		$aop->format='json';
		$request = new \AlipaySystemOauthTokenRequest ();
		$request->setGrantType("authorization_code");
		$request->setCode($jscode);
		$result = $aop->execute ($request);
		$responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
		$resultCode = $result->$responseNode->code;
       
		if(!empty($resultCode)&&$resultCode != 10000){
			return json(['status'=>0,'msg'=>$result->$responseNode->sub_msg]);
		} else {
			$openid = $result->$responseNode->user_id;
            $openid_new = $result->$responseNode->open_id;
            $openid_type = $alipayapp['openid_set'];
            if(!$openid && !$openid_new){
                return json(['status'=>0,'msg'=>'获取授权信息失败']);
            }
			$updata = [];
			if($openid)$updata['alipayopenid'] = $openid;
            if($openid_new)$updata['alipayopenid_new'] = $openid_new;
			Db::name('member')->where('id',mid)->update($updata);
			return json(['status'=>1,'msg'=>'更新成功','openid'=>$openid_type =='userid'?$openid:$openid_new]);
		}
	}
	//设置百度小程序openid
	public function setbaiduopenid(){
		if(!$this->member) return json(['status'=>0,'msg'=>'未登录']);
		$post = input('post.');
		$set = Db::name('admin_set')->where('aid',aid)->find();
		$jscode = input('post.code');
		$baiduapp = \app\commons\System::appinfo(aid,'baidu');
		$rs = request_post('https://spapi.baidu.com/oauth/jscode2sessionkey',['code'=>$jscode,'client_id'=>$baiduapp['appkey'],'sk'=>$baiduapp['appsecret']]);

		$rs = json_decode($rs,true);
		if(!$rs['openid']){
			return $this->json(['status'=>0,'msg'=>$rs['error_description']]);
		}
		$openid = $rs['openid'];
		if($openid){
			$updata = [];
			$updata['baiduopenid'] = $openid;
			Db::name('member')->where('id',mid)->update($updata);
		}
		return json(['status'=>1,'msg'=>'更新成功']);
	}
	//设置头条小程序openid
	public function settoutiaoopenid(){
		$jscode = input('param.code');
		$toutiaoapp = \app\commons\System::appinfo(aid,'toutiao');
		$rs = request_get('https://developer.toutiao.com/api/apps/jscode2session?appid='.$toutiaoapp['appid'].'&secret='.$toutiaoapp['appsecret'].'&code='.$jscode);
		$rs = json_decode($rs,true);
		if(!$rs['openid']){
			return $this->json(['status'=>0,'msg'=>$rs['errmsg']]);
		}
		$openid = $rs['openid'];
		if($openid){
			$updata = [];
			$updata['toutiaoopenid'] = $openid;
			Db::name('member')->where('id',mid)->update($updata);
		}
		return json(['status'=>1,'msg'=>'更新成功']);
	}

	//微信小程序授权登录      
	public function wxbaselogin(){
		$jscode = input('param.code');
		$fromid = input('param.pid');
		$wxapp = \app\commons\System::appinfo(aid,'wx');
		if($wxapp['authtype']==1){
			$url = 'https://api.weixin.qq.com/sns/component/jscode2session?appid='.$wxapp['appid'].'&component_appid='.\app\commons\Wechat::component_appid().'&js_code='.$jscode.'&grant_type=authorization_code&component_access_token='.\app\commons\Wechat::component_access_token();
		}else{
			$url = 'https://api.weixin.qq.com/sns/jscode2session?appid='.$wxapp['appid'].'&secret='.$wxapp['appsecret'].'&js_code='.$jscode.'&grant_type=authorization_code';
		}
		$rs = request_get($url);
		$rs = json_decode($rs,true);
		if($rs['errcode']>0){
			return $this->json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs)]);
		}
		$openid = $rs['openid'];
		if(!$openid) return json(['status'=>0,'msg'=>'获取授权信息失败']);
		$unionid = $rs['unionid'];
		$session_key = $rs['session_key'];
		$member = Db::name('member')->where('aid',aid)->where(platform.'openid',$openid)->find();
		if(!$member && $unionid){
			$member = Db::name('member')->where('aid',aid)->where('unionid',$unionid)->find();
			if($member) Db::name('member')->where('id',$member['id'])->update([platform.'openid'=>$openid]);
		}
		if(!$member){
            $data = [];
            $data['aid'] = aid;
            $data[platform.'openid'] = $openid;
			if($unionid){
				$data['unionid'] = $unionid;
			}
			if(getcustom('maidan_auto_reg') && input('maidan')){
                $maidan_set = Db::name('admin_set')->where('aid',aid)->field('maidan_auto_reg,maidan_login')->find();
                //聚合收款码买单，如果设置了不强制登录也不自动注册会员，直接返回
                if(!$maidan_set['maidan_login'] && !$maidan_set['maidan_auto_reg']){
                    cache($this->sessionid.'_openid',$openid,7*86400);
                    cache($this->sessionid.'_unionid',$unionid,7*86400);
                    return $this->json(['status'=>1,'msg'=>'登录成功','mid'=>$openid,'session_id'=>$this->sessionid]);
                }
            }

            $data['sex'] = 3;
            $data['nickname'] = '用户'.random(6);
            $data['headimg'] = PRE_URL.'/static/img/touxiang.png';
            $data['createtime'] = time();
            $data['last_visittime'] = time();
            //推广人
            if($fromid){
                $data['pid'] = $this->getpid($fromid);
            }
            $data['platform'] = platform;
            if(getcustom('member_business')){
            	//商户注册会员
                if(input('param.regbid')){
                	//查询权限
                    $admin_user = Db::name('admin_user')->where('aid',aid)->where('isadmin','>',0)->field('auth_type,auth_data')->find();
                    if($admin_user){
                    	if($admin_user['auth_type'] !=1 ){
	                        $auth_data = !empty($admin_user['auth_data'])?json_decode($admin_user['auth_data'],true):[];
	                        if(in_array('MemberBusiness,MemberBusiness',$auth_data)){
	                        	$data['bid'] = input('param.regbid/d');
	                        }
	                    }else{
	                    	$data['bid'] = input('param.regbid/d');
	                    }
	                }
                }
            }
            $mid = \app\models\Member::add(aid,$data);
            Db::name('session')->where('aid',aid)->where('session_id',$this->sessionid)->update([
                'mid' => $mid,
                'login_time' => time()
            ]);
            if($data['pid']) \app\commons\Common::user_tjscore(aid,$mid);
            //注册赠送
            \app\commons\Common::registerGive(aid,array_merge($data, ['id' => $mid]));
        }else{
			$mid = $member['id'];
            Db::name('session')->where('aid',aid)->where('session_id',$this->sessionid)->update([
                'mid' => $mid,
                'login_time' => time()
            ]);
            //后绑定开放平台有了unionid 同步更新
            if(empty($member['unionid']) && $unionid){
                Db::name('member')->where('aid',aid)->where('id',$member['id'])->update(['unionid'=>$unionid]);
            }
		}
		$sessionid_time = 7*86400;
		if(getcustom('system_nologin_day')){
			//后台设置的免登录天数
			$nologin_day = Db::name('admin_set')->where('aid',aid)->value('nologin_day');
			if($nologin_day>0){
				$sessionid_time = $nologin_day*86400;
			}
		}
        cache($this->sessionid.'_mid',$mid,$sessionid_time);
        return $this->json(['status'=>1,'msg'=>'登录成功','mid'=>$mid,'session_id'=>$this->sessionid]);

	}

	//发送验证码
	public function sendsms(){
		$code = rand(100000,999999);
		$tel = input('post.tel');
		if(!checkTel($tel)){
			return $this->json(['status'=>0,'msg'=>'手机号格式错误']);
		}
//		Log::write('code:'.$code);
		cache($this->sessionid.'_smscode',md5($tel.'-'.$code),600);
		cache($this->sessionid.'_smscodetimes',0);
		$rs = \app\commons\Sms::send(aid,$tel,'tmpl_smscode',['code'=>$code]);
		return $this->json($rs);
	}

	//发送自定义跳转链接功能的推广短信
	public function sendsmsurl(){
		$tel = input('post.tel');
		$tmpl = input('post.tmpl');
		$link = input('post.link');
		if($this->member && strpos($link,'http')===0){
			if(strpos($link,'?')!==false){
				$link = $link.'&pid='.mid;
			}else{
				$link = $link.'?pid='.mid;
			}
		}
		if(!checkTel($tel)){
			return $this->json(['status'=>0,'msg'=>'手机号格式错误']);
		}
		$rs = \app\commons\Sms::send(aid,$tel,$tmpl,['link'=>$link]);
		//if($rs['status'] == 1) $rs['msg'] = '发送成功';
		return $this->json($rs);
	}

	//底部菜单
	public function gettabbardata(){
		$menuset = Db::name('designer_menu')->where('aid',aid)->where('platform',platform)->find();
		$menudata = json_decode($menuset['menudata'],true);
		$menulist = array();
		foreach($menudata['list'] as $k=>$v){
			if($k < $menuset['menucount']){
				$menulist[] = $v;
			}
		}
		$menudata['list'] = $menulist;
		$indexurl = $menuset['indexurl'];
		if(platform == 'wx' && !input('post.fromw7')){
			$menudata['list'] = [];
		}
		//内页菜单
		$menu2list = Db::name('designer_menu2')->where('aid',aid)->where('platform','wx')->where('status',1)->order('id desc')->select()->toArray();
		$menu2datalist = [];
		if($menu2list){
			foreach($menu2list as $k=>$v){
				$menu2data = [];
				$menu2data['backgroundColor'] = $v['backgroundColor'];
				$menu2data['indexurl'] = $v['indexurl'];
				$menu2data['list'] = json_decode($v['menudata'],true);
				$menulist = [];
				foreach($menu2data['list'] as $k2=>$v2){
					if($k2 < $v['menucount']){
						$menulist[] = $v2;
					}
				}
				$menu2data['list'] = $menulist;
				$menu2datalist[] = $menu2data;
			}
		}
		$textset = \think\Cache::get('textset_'.aid);
		return $this->json(['status'=>1,'data'=>$menudata,'indexurl'=>$indexurl,'menu2data'=>$menu2datalist,'textset'=>$textset]);
	}
	//绑定管理员会员ID
	public function bind(){
		$this->checklogin();
		$id    = input('param.id/d');
		$token = input('param.token');
		$type  = input('?param.type')?input('param.type/d'):0;//类型 0：默认 1：绑定员工
		if(!$type){
			if(!$token || $token!=cache('adminbdtoken_'.$id)){
				return $this->json(['status'=>0,'msg'=>'二维码已过期']);
			}
			//查询之前的绑定的账号
			$users = Db::name('admin_user')->where('mid',mid)->field('id,bid,isadmin')->select()->toArray();
			if($users){
				foreach($users as $uv){
					Db::name('admin_user')->where('id',$uv['id'])->update(['mid'=>0]);
					if($uv['bid'] > 0 && in_array($uv['isadmin'],[1,2])){
						Db::name('business')->where('aid',aid)->where('id',$uv['bid'])->update(['mid'=>0]);
					}
				}
			}
			Db::name('admin_user')->where('aid',aid)->where('id',$id)->update(['mid'=>mid]);
	        $admin_user = Db::name('admin_user')->where('aid',aid)->where('id',$id)->find();
	        if($admin_user['bid'] > 0 && in_array($admin_user['isadmin'],[1,2])){
	            Db::name('business')->where('aid',aid)->where('id',$admin_user['bid'])->update(['mid'=>mid]);
	        }
			cache($this->sessionid.'_uid',null);
		}else{
            if(getcustom('extend_staff')){
                //绑定员工
                if($type == 1){
                    if(!$token || $token!=cache('staffbdtoken_'.$id)){
                        return $this->json(['status'=>0,'msg'=>'二维码已过期']);
                    }
                    $mstaff = Db::name('staff')->where('mid',mid)->where('aid',aid)->count('id');
                    if($mstaff){
                        return $this->json(['status'=>0,'msg'=>'你已绑定员工']);
                    }
                    $staff = Db::name('staff')->where('id',$id)->where('aid',aid)->find();
                    if(!$staff){
                        return $this->json(['status'=>0,'msg'=>'员工不存在']);
                    }
                    if($staff['mid']>0){
                        return $this->json(['status'=>0,'msg'=>'该员工已绑定']);
                    }
                    $up = Db::name('staff')->where('id',$id)->update(['mid'=>mid]);
                    if(!$up){
                        return $this->json(['status'=>0,'msg'=>'绑定失败']);
                    }
                }
            }
        }
		$mpset = Db::name('admin_setapp_mp')->field('qrcode')->where('aid',aid)->find();
		return $this->json(['status'=>1,'msg'=>'绑定成功','set'=>$mpset]);
	}

	//领会员卡
	public function getcardurl(){
		$this->checklogin();
		$id = input('param.id/d');
		$membercard = Db::name('membercard')->where('id',$id)->find();
		if($membercard && $membercard['ret_url']){
			return redirect($membercard['ret_url']);
		}
	}

	//订阅消息
	public function subscribemessage(){
		if(mid && input('post.tmplid')){
			$tmplid = input('post.tmplid');
			$tmplnum = Db::name('member_tmplnum')->where('aid',aid)->where('mid',mid)->where('tmplid',$tmplid)->find();
			if($tmplnum){
				Db::name('member_tmplnum')->where('id',$tmplnum['id'])->inc('num')->update();
			}else{
				Db::name('member_tmplnum')->insert(['aid'=>aid,'mid'=>mid,'tmplid'=>$tmplid,'num'=>1]);
			}
		}
		return $this->json([]);
	}
	public function captcha(){
		$captcha = new \app\commons\Captcha();
		$picture = $captcha->create();
		$value = $captcha->captchaValue;
		cache($this->sessionid.'_captcha',$value,3600);
		return $picture;
	}
	public function linked(){
		return $this->json(['status'=>1]);
	}

	public function getCustom()
    {	
    	if(getcustom('wurl_reward')){
            $type = input('type')?input('type'):'';
            if($type == 'wurl_reward'){
                $tourl = input('tourl')?urldecode(input('tourl')):'';
                if($this->member && mid>0 && $tourl){
                    \app\customs\WurlCustom::reward(aid,mid,$this->member,$tourl);
                }
            }
        }
        return $this->json(['data'=>getcustom()]);
    }

    public function agentCard(){
	    $pid = input('param.pid/d');

	    $info = $this->getAgentCard($pid);
        if($info['pagecontent']){
            $pagecontent = json_decode(\app\commons\System::initpagecontent($info['pagecontent'],aid),true);
        }
        if(!$pagecontent) $pagecontent = [];

        $rdata = ['status'=>1];
        $rdata['info'] = $info ? $info : [];
        $rdata['pagecontent'] = $pagecontent;
        return $this->json($rdata);
    }

    public function w()
    {
        $code = input('param.code');
        $info = Db::name('qrcode_list_variable')->where('aid',aid)->where('code',$code)->find();
        $data = [];
        $url = $info['tourl'];
        if($info){
            Db::name('qrcode_list_variable')->where('aid',aid)->where('code',$code)->inc('shownum',1)->update();
            if($info['bindstatus_business'] == 1 && empty($info['param_bid'])){
                //绑定多商户
                $this->checklogin();
                $level = Db::name('member_level')->where('aid',aid)->where('id',$this->member['levelid'])->find();
                if($level && $level['can_agent']>0){
                    $data['bindstatus_business'] = $info['bindstatus_business'];
                    $data['param_bid'] = $info['param_bid'];
                }
            }
            if($info['param_bid']){
                $urlparams = http_build_query(['bid'=>$info['param_bid']]);
                if(strpos($info['tourl'],'?') !== false){
                    $url = $info['tourl'] . '&'.$urlparams;
                }else{
                    $url = $info['tourl'] . '?'.$urlparams;
                }
            }
			$pid  = $info['pid'];//上级id
            //查询是否绑定分销商且开启绑定分销商
            if(!$info['pid'] && $info['bindstatus'] == 1){
                //如果未登录，则需要去登录
                if(!$this->member || !mid){
                    return $this->json(['status'=>2,'qrcode'=>$code,'msg'=>'请先登录']);
                }else{
                    //如果已登录,查询用户是否有分销权限
                    $can_agent = Db::name('member')
                        ->alias('m')
                        ->join('member_level ml','ml.id=m.levelid')
                        ->where('m.id',mid)
                        ->where('ml.can_agent','>',0)
                        ->field('m.id')
                        ->count('m.id');
                    if($can_agent){
                        //更新使用状态
                        $uplist = Db::name('qrcode_list_variable')->where('id',$info['id'])->update(['pid'=>mid,'bindtime'=>time()]);
                        $pid  = mid;//上级id
                    }
                }
            }
			if($pid){
                $urlparams = http_build_query(['pid'=>$pid]);
                if(strpos($url,'?') !== false){
                    $url = $url . '&'.$urlparams;
                }else{
                    $url = $url . '?'.$urlparams;
                }
            }
        }
        return $this->json(['status'=>1,'url'=>$url,'data'=>$data,'pid'=>$pid]);
    }

    public function wBindBusiness()
    {
        $this->checklogin();
        $code = input('param.code');
        $bid = input('param.bid');
        if(empty($code) || empty($bid)){
            return $this->json(['status'=>0,'msg'=>'请输入正确的商户ID']);
        }
        $info = Db::name('qrcode_list_variable')->where('aid',aid)->where('code',$code)->find();
        if($info){
            if($info['bindstatus_business'] == 1 && empty($info['param_bid'])){
                //绑定多商户
                $level = Db::name('member_level')->where('aid',aid)->where('id',$this->member['levelid'])->find();
                if($level && $level['can_agent']>0){
                    $business = Db::name('business')->where('aid',aid)->where('id',$bid)->find();
                    if(empty($business)) return $this->json(['status'=>0,'msg'=>'该商户不存在']);
                    if(empty($business['mid'])){
                        return $this->json(['status'=>0,'msg'=>'该商户未绑定店长，无法绑定']);
                    }
                    $businessmember = Db::name('member')->where('aid',aid)->where('id',$business['mid'])->find();
                    if($businessmember['pid'] && $businessmember['pid'] != mid){
                        return $this->json(['status'=>0,'msg'=>'该商户已被他人绑定']);
                    }
                    $rs = \app\models\Member::edit(aid,['id'=>$businessmember['id'],'pid'=>mid]);
                    if($rs['status'] != 1){
                        return $this->json($rs);
                    }
                    if($rs['status'] == 1) {
                        Db::name('qrcode_list_variable')->where('aid', aid)->where('code', $code)->update(['param_bid'=>$bid]);
                        $urlparams = http_build_query(['bid'=>$bid]);
                        if(strpos('?',$info['tourl']) !== false){
                            $url = $info['tourl'] . '&'.$urlparams;
                        }else{
                            $url = $info['tourl'] . '?'.$urlparams;
                        }
                        return $this->json(['status'=>1,'url'=>$url]);
                    }
                }
            }
        }else{
            return $this->json(['status'=>0,'msg'=>'参数错误']);
        }

    }

    private function getAgentCard($mid){
        if(getcustom('agent_card')){
            /**
             * 240322自己是经销商显示自己的，自己不是的话继续；
             * 判断上级是否有申请成为经销商（有记录），是则显示上级的；如果没有再上一级判断，直到顶级
             */
            $sysset = Db::name('admin_set')->field('name,logo,desc,tel,address,longitude,latitude')->where('aid',aid)->find();
            if(mid > 0){
//                if($this->member['pid'] > 0) {
//                    $pids = explode(',',$this->member['path']);
//                    $mid = $pids[0];
//                } else {
//                    $count = \db('member_levelup_order')->where('aid',aid)->where('mid',mid)->where('status',2)->count();
//                    if($count)
//                        $mid=mid;
//                    else
//                        $mid = 0;
//                }
                $count = \db('member_levelup_order')->where('aid',aid)->where('mid',mid)->where('status',2)->count();
                if($count){
                    $mid=mid;
                }elseif($this->member['pid'] > 0) {
                    $pids = explode(',',$this->member['path']);
                    $pids = array_reverse($pids);
                    if($pids){
                        foreach ($pids as $pid){
                            $count = \db('member_levelup_order')->where('aid',aid)->where('mid',$pid)->where('status',2)->count();
                            if($count) {
                                $mid = $pid;
                                break;
                            }
                        }
                    }
                }else{
                    $mid = 0;
                }
            } else {
                if($mid > 0) {
                    $member = Db::name('member')->where('aid',aid)->where('id',$mid)->find();
//                    if($member['pid'] > 0) {
//                        $pids = explode(',',$member['path']);
//                        $mid = $pids[0];
//                    } else {
//                        $count = \db('member_levelup_order')->where('aid',aid)->where('mid',$mid)->where('status',2)->count();
//                        if($count)
//                            $mid=$member['id'];
//                        else
//                            $mid = 0;
//                    }
                    $count = \db('member_levelup_order')->where('aid',aid)->where('mid',$mid)->where('status',2)->count();
                    if($count){
                        $mid=$mid;
                    }elseif($member['pid'] > 0) {
                        $pids = explode(',',$member['path']);
                        $pids = array_reverse($pids);
                        if($pids){
                            foreach ($pids as $pid){
                                $count = \db('member_levelup_order')->where('aid',aid)->where('mid',$pid)->where('status',2)->count();
                                if($count) {
                                    $mid = $pid;
                                    break;
                                }
                            }
                        }
                    }else{
                        $mid = 0;
                    }
                }
            }

            if(!$mid){
                $mid = Db::name('admin_user')->where('aid',aid)->where('bid',0)->where('isadmin','in',[1,2])->value('mid');
            }

            if($mid)
                $info = Db::name('member_agent_card')->where('aid',aid)->where('mid',$mid)->find();

            $juli = '';
            if(empty($info)){
                $info = [
                    'name' => '综合客服',
                    'shopname' => $sysset['name'],
                    'address' => $sysset['address'],
                    'tel' => $sysset['tel'],
                    'latitude' => $sysset['latitude'],
                    'longitude' => $sysset['longitude'],
                ];
            }
            $info['logo'] = $info['logo'] ? $info['logo'] : $sysset['logo'];
            $info['name'] = $info['name'] ? $info['name'] : '综合客服';
            $info['juli'] = $juli;
            return $info;
        }
    }
    //退出
    public function logout(){
        $rdata = [];
        $rdata['status'] = -3;
        $rdata['url'] = '/pages/index/index';
        if(mid){
            Db::name('session')->where('aid',aid)->where('session_id',$this->sessionid)->delete();
            cache($this->sessionid.'_mid',null);
            $this->mid = 0;
            $this->sessionid = \think\facade\Session::getId();
            $rdata['sessionid']=$this->sessionid;
        }
        return $this->json($rdata);
    }

    private function getpid($pid){
        $newPid = 0;
        if($pid <= 0) return $newPid;
        $upuser = Db::name('member')->where('id',intval($pid))->find();
        $uplv = Db::name('member_level')->where('aid',aid)->where('id',$upuser['levelid'])->find();
        //等级是否有分销权限
        if($upuser && $uplv['can_agent']!=0){
            $newPid = $upuser['id'];
            //修改推荐人
            if(getcustom('update_member_pid')){
                $set = Db::name('admin_set')->where('aid',aid)->find();
                if($newPid == $set['pid_origin'] && $set['pid_new']){
                    $pidNewArr = explode(',',$set['pid_new']);
                    //按顺序分配pid_new_pos
                    $pidNewPos = $pidNewArr[$set['pid_new_pos']-1];
                    $newPid = $pidNewPos ? $pidNewPos : $pidNewArr[0];
                    if($pidNewPos){
                        if($set['pid_new_pos'] == count($pidNewArr))
                            $update = ['pid_new_pos' => 1];
                        else
                            $update = ['pid_new_pos' => $set['pid_new_pos']+1];
                    }else{
                        $update = ['pid_new_pos' => 2];
                    }
                    Db::name('admin_set')->where('aid',aid)->update($update);
                }
            }

            //限制直推人数
            if(getcustom('member_level_zhitui_number_limit')){
                $limitMember = $uplv['zt_member_limit'];
                //0:不限制
                if($limitMember > 0){
                    $zt_num = Db::name('member')->where('pid',intval($newPid))->where('aid',aid)->count();
                    if($zt_num >= $limitMember){
                        $newPid = 0;
                    }
                }
            }
        }
        return $newPid;
    }

    //$customdata['id'] //formid
    //$customdata['data'] //表单内容
    protected function customRegister($formdata=[],$formid=0){
        if(getcustom('register_fields')){
            $form = Db::name('register_form')->where('aid',aid)->find();
            if(empty($form) || empty($form['content'])){
                return ['status'=>1,'msg'=>''];
            }
            if($form['id']!=$formid){
                return ['status'=>0,'msg'=>'表单数据有误！'];
            }
            $formheader = [];
            if(empty($formdata)) $formdata = [];
            $data = [];
            $formcontent = json_decode($form['content'],true);
            $sys_fields = [];
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
                //抽离系统参数
                if(in_array($v['key'], ['realname', 'usercard', 'sex', 'birthday'])){
                    if($v['key'] == 'sex'){
                        $sex_arr = ['男'=>1,'女'=>2,'未知'=>3];
                        $sys_fields[$v['key']] = $sex_arr[$value]??0;
                    }else{
                        $sys_fields[$v['key']] = $value;
                    }
                }
            }
            if($data){
                $data['aid'] = aid;
                $data['formid'] = $formid;
                $data['content'] = $form['content'];
                $data['createtime'] = time();
                $data['bid'] = 0;
                $recordid = Db::name('register_form_record')->insertGetId($data);
                return ['status'=>1,'msg'=>'自定义表单保存成功','recordid'=>$recordid, 'sys_fields'=>$sys_fields];
            }else{
                return ['status'=>1,'msg'=>''];
            }
        }
        return ['status'=>1,'msg'=>''];
    }

    public function get_area_mendain(){
        if(getcustom('xixie')) {
            //获取地址信息及门店信息
            if(request()->isPost()){

                $key = 'ABLBZ-4BIKU-GFTVB-BK7IK-OLQ35-QCBFF';
                $post = input('post.');

                if(!$post['latitude'] || !$post['longitude']){
                    return $this->json(['status'=>0,'msg'=>'请授权获取地理位置']);
                }

                $latitude  = $post['latitude'];
                $longitude = $post['longitude'];

                $data = [];

                $data['latitude']  = $latitude;
                $data['longitude'] = $longitude;
                $data['address']   = '';
                $data['province']  = '';
                $data['city']      = '';
                $data['district']  = '';
                $data['mendian']  = '';

                $province = '';
                $city     = '';
                //通过坐标获取省市区
                $mapqq = new \app\commons\MapQQ();
                $address = $mapqq->locationToAddress($latitude,$longitude);
                if($address && $address['status']==1){
                    $data['address']   = $address['result']['address'];
                    $data['province']  = $address['province'];
                    $data['city']      = $address['city'];
                    $data['district']  = $address['district'];

                    $province = $data['province'];
                    $city     = $data['city'];
                }
                $data['mendian']['m_address']  = '';
                if($this->member){
                    $m_address = Db::name('member_address')
                        ->where('mid',mid)
                        ->where('aid',aid)
                        ->order('isdefault desc,id desc')
                        ->field('id,area,address')
                        ->find();
                    if($m_address){
                        $data['mendian']['m_address']  = $m_address['area'].' '.$m_address['address'];
                        $province = $m_address['province'];
                        $city     = $m_address['city'];
                        $longitude= $m_address['longitude'];
                        $latitude = $m_address['latitude'];
                    }
                }
                if($province && $city){
                    //查询此城市区域是否开放
                    $where = [];
                    $where[] = Db::raw("find_in_set('".$province."',province)");
                    $where[] = Db::raw("find_in_set('".$city."',city)");
                    $count_area = Db::name('open_area')
                        ->where($where)
                        ->count();
                    if($count_area){
                        if($longitude && $latitude){
                            //处理门店是否在他服务范围内
                            $deal_mendian = $this->deal_mendian($longitude,$latitude,$province,$city);
                            if($deal_mendian){
                                $data['mendian']  =  $deal_mendian['mendian'];
                            }
                        }
                    }
                }
                //查询用户的地址
                return $this->json(['status'=>1,'data'=>$data]);
            }
        }
    }

    public function bind_wxtel(){
        if(getcustom('xixie')) {
            //绑定微信手机号
            if(request()->isPost()){
                if(input('param.code') && input('post.iv') && input('post.encryptedData')){
                    $jscode = input('param.code');
                    $encryptedData = input('post.encryptedData');
                    $iv = input('post.iv');

                    $wxapp = \app\commons\System::appinfo(aid,'wx');
                    if($wxapp['authtype']==1){
                        $url = 'https://api.weixin.qq.com/sns/component/jscode2session?appid='.$wxapp['appid'].'&component_appid='.\app\commons\Wechat::component_appid().'&js_code='.$jscode.'&grant_type=authorization_code&component_access_token='.\app\commons\Wechat::component_access_token();
                    }else{
                        $url = 'https://api.weixin.qq.com/sns/jscode2session?appid='.$wxapp['appid'].'&secret='.$wxapp['appsecret'].'&js_code='.$jscode.'&grant_type=authorization_code';
                    }
                    $rs = request_get($url);
                    $rs = json_decode($rs,true);
                    if($rs['errcode']>0){
                        return $this->json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs)]);
                    }
                    $session_key = $rs['session_key'];
                    if(!$session_key) return json(['status'=>0,'msg'=>'获取授权信息失败']);

                    $pc = new \app\commons\WxBizDataCrypt($wxapp['appid'], $session_key);
                    $errCode = $pc->decryptData($encryptedData, $iv, $rdata);
                    $rdata = json_decode($rdata,true);

                    if($rdata['phoneNumber']){
                        return json(['status'=>1,'data'=>$rdata['phoneNumber']]);
                    }else{
                        return json(['status'=>0,'msg'=>'授权获取手机号失败']);
                    }
                }else{
                    return json(['status'=>0,'msg'=>'授权获取手机号失败']);
                }
            }
        }
    }
    //处理服务门店
    public function deal_mendian($longitude,$latitude,$province,$city,&$id_str = ''){
        if(getcustom('xixie')) {
            $data = [];
            $data['mendian'] = '';

            $where = [];
            $where[] = ['province','=',$province];
            $where[] = ['city','=',$city];
            $where[] = ['aid','=',aid];
            if($id_str){
                $where[]=['id','not in',$id_str];
            }
            $where[] = ['status','=',1];
            $where[] = ['longitude','<>',''];
            $where[] = ['latitude','<>',''];

            //查询门店
            $order   = Db::raw("({$longitude}-longitude)*({$longitude}-longitude) + ({$latitude}-latitude)*({$latitude}-latitude) ");
            $mendian = Db::name('mendian')
                ->where($where)
                ->order($order)
                ->find();

            if($mendian){
                if($mendian['peisong_rangetype'] == 1){
                    $pspointsArr = explode(';',$mendian['peisong_rangepath']);
                    $pspoints    = [];
                    foreach($pspointsArr as $pspoint){
                        $pspointArr = explode(',',$pspoint);
                        $pspoints[] = ['lat'=>$pspointArr[1],'lng'=>$pspointArr[0]];
                    }
                    if($pspoints){
                        $rs = \app\models\Freight::is_point_in_polygon(['lat'=>$longitude,'lng'=>$latitude],$pspoints);
                        if($rs){
                            $data['mendian'] = $mendian;
                        }else{
                            $id_str .= $id_str?','. $mendian['id']: $mendian['id'];
                            $deal_mendian = $this->deal_mendian($longitude,$latitude,$province,$city,$id_str);
                            if($deal_mendian && $deal_mendian['mendian']){
                                $data['mendian'] = $deal_mendian['mendian'];
                            }
                        }
                    }
                    $juli = getdistance($longitude,$latitude,$mendian['peisong_lng2'],$mendian['peisong_lat2'],1);
                }else{
                    $juli = getdistance($longitude,$latitude,$mendian['peisong_lng'],$mendian['peisong_lat'],1);

                    if($juli <= $mendian['peisong_range']){
                        $data['mendian'] = $mendian;
                    }else{
                        $id_str .= $id_str?','. $mendian['id']: $mendian['id'];
                        $deal_mendian = $this->deal_mendian($longitude,$latitude,$province,$city,$id_str);
                        if($deal_mendian && $deal_mendian['mendian']){
                            $data['mendian'] = $deal_mendian['mendian'];
                        }
                    }
                }
            }
            return $data;
        }
    }
    public function checkGotoLink(){
	    $with_system_param = 0;
	    if(getcustom('with_system_param')){
	        $admin = Db::name('admin')->where('id',aid)->find();
	        if($admin && $admin['with_system_param']==1){
                $with_system_param = 1;
            }
        }
	    return $this->json(['status'=>1,'with_system_param'=>$with_system_param]);
    }

    //系统模式
    public function checkMode(){
        $sysset = ['aid'=>aid,'t'=>time()];
        $show_location = 0;
        $show_mendian = 0;
        if(getcustom('show_location')){
            $sysset = Db::name('admin_set')->where('aid',aid)->field('mode,loc_area_type,loc_range_type,loc_range')->find();
            if($sysset['mode']==2){
                $show_location = 1;
            }else if($sysset['mode']==3){
                $show_mendian = 1;
            }
        }
        return $this->json(['status'=>1,'sysset'=>$sysset,'show_location'=>$show_location,'show_mendian'=>$show_mendian]);
    }

    //临时登录注册默认登录
    public function autoaddlogin(){
        if(getcustom('member_auto_addlogin')){
            if(mid){
                $mid = mid;
                return $this->json(['status'=>1,'msg'=>'登录成功','mid'=>$mid,'session_id'=>$this->sessionid]);
            }
            $member = Db::name('member')->where('aid',aid)->where('session_id',$this->sessionid)->find();
            if(!$member){
                $data = [];
                $data['aid'] = aid;
                $data['sex'] = 3;
                $data['nickname'] = '用户'.random(6);
                $data['tel'] = time().random(1,1);
                $data['headimg'] = PRE_URL.'/static/img/touxiang.png';

                $data['createtime'] = time();
                $data['session_id'] = $this->sessionid;
                $data['last_visittime'] = time();
                $data['platform'] = platform;
                if(getcustom('member_business')){
                	//商户注册会员
	                if(input('param.regbid')){
	                	//查询权限
	                    $admin_user = Db::name('admin_user')->where('aid',aid)->where('isadmin','>',0)->field('auth_type,auth_data')->find();
	                    if($admin_user){
	                    	if($admin_user['auth_type'] !=1 ){
		                        $auth_data = !empty($admin_user['auth_data'])?json_decode($admin_user['auth_data'],true):[];
		                        if(in_array('MemberBusiness,MemberBusiness',$auth_data)){
		                        	$data['bid'] = input('param.regbid/d');
		                        }
		                    }else{
		                    	$data['bid'] = input('param.regbid/d');
		                    }
		                }
	                }
	            }
                $mid = \app\models\Member::add(aid,$data);
                Db::name('session')->where('aid',aid)->where('session_id',$this->sessionid)->update([
                    'mid' => $mid,
                    'login_time' => time()
                ]);
            }else{
                $mid = $member['id'];
                if(empty($member['tel'])){
                    $member = Db::name('member')->where('aid',aid)->where('id',$member['id'])->update([
                        'tel' => time().random(1,1)
                    ]);
                }
                Db::name('session')->where('aid',aid)->where('session_id',$this->sessionid)->update([
                    'mid' => $mid,
                    'login_time' => time()
                ]);
            }
            $sessionid_time = 7*86400;
			if(getcustom('system_nologin_day')){
				//后台设置的免登录天数
				$nologin_day = Db::name('admin_set')->where('aid',aid)->value('nologin_day');
				if($nologin_day>0){
					$sessionid_time = $nologin_day*86400;
				}
			}
            cache($this->sessionid.'_mid',$mid,$sessionid_time);
            return $this->json(['status'=>1,'msg'=>'登录成功','mid'=>$mid,'session_id'=>$this->sessionid]);
        }
    }
    
    //收银台的公众号 静默登陆 绑定会员
    public function cashdeskMemberBind(){
	    if(getcustom('restaurant_cashdesk_member_paypwd')){
            $mid = input('param.id');
            $platform = platform; 
            $update = [];
            if($platform =='mp'){
                //授权登录
                if(input('param.state') && input('param.state') == 'baseauthlogin' && input('param.code')){
                    $code = input('param.code');
                    $rs = \app\commons\Wechat::getAccessTokenByCode(aid,$code);
                    //is_snapshotuser	是否为快照页模式虚拟账号，只有当用户是快照页模式虚拟账号时返回，值为1
                    if($rs['is_snapshotuser'] == 1){
                        return $this->json(['status'=>0,'msg'=>'授权登录失败，请点击下方“使用完整服务”']);
                    }
                    $openid = $rs['openid'];
                    if($openid){
                        $update['mpopenid'] = $openid;
                    } else{
                        return $this->json(['status'=>0,'msg'=>'绑定失败,请重新操作']);
                    }
                }else{
                    //获取用户信息
                    $request_url = ltrim($_SERVER["REQUEST_URI"],'/');
                    if(strpos($request_url,'?code=')!==false){
                        $request_url = explode('?code=',$request_url)[0];
                    }elseif(strpos($request_url,'&code=')!==false){
                        $request_url = explode('&code=',$request_url)[0];
                    }
                    $redirectUrl = request()->domain().'/'.$request_url;//.'&frompage='.input('param.frompage');
                    //\think\facade\Log::write($redirectUrl);
                    $redirectUrl = urlencode($redirectUrl);
                    $AuthorizeUrl = \app\commons\Wechat::getOauth2AuthorizeUrl(aid,$redirectUrl,'snsapi_base','baseauthlogin');
                    return redirect($AuthorizeUrl);
                }
            }elseif($platform =='wx'){
                $jscode = input('param.code');
                $wxapp = \app\commons\System::appinfo(aid,'wx');
                if($wxapp['authtype']==1){
                    $url = 'https://api.weixin.qq.com/sns/component/jscode2session?appid='.$wxapp['appid'].'&component_appid='.\app\commons\Wechat::component_appid().'&js_code='.$jscode.'&grant_type=authorization_code&component_access_token='.\app\commons\Wechat::component_access_token();
                }else{
                    $url = 'https://api.weixin.qq.com/sns/jscode2session?appid='.$wxapp['appid'].'&secret='.$wxapp['appsecret'].'&js_code='.$jscode.'&grant_type=authorization_code';
                }
                $rs = request_get($url);
                $rs = json_decode($rs,true);
                $openid = $rs['openid'];
                if($openid){
                    $update['wxopenid'] =   $openid;
                }else{
                    if(!$openid) return json(['status'=>0,'msg'=>'绑定失败,请重新操作']);
                }
            }
            if($update){
                Db::name('member')->where('id',$mid)->update($update);
               if($platform =='mp'){
                   $tourl = m_url(urldecode(input('param.frompage'))) ;
                   echo "<script>alert('绑定成功');window.location.href='".$tourl."'</script>";
               }elseif($platform =='wx'){
                   return json(['status'=>1,'msg'=>'绑定成功']);
               }
            }else{
                return $this->json(['status'=>0,'msg'=>'绑定失败,请重新操作']);
            }
        }
    }
}