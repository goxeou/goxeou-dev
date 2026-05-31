<?php


namespace app\controllers;
use think\facade\Db;
class ApiYuyue extends ApiCommon{
	public function getprolist(){
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['ischecked','=',1];
		//$where[] = ['status','=',1];
		$nowtime = time();
		$where[] = Db::raw("`status`=1  or (`status`=2 and unix_timestamp(start_time)<=$nowtime and unix_timestamp(end_time)>=$nowtime)");

		if(input('param.bid')){
			$bid = input('param.bid/d');
		}else{
			$bid = 0;
		}
		if($bid){
			$where[] = ['bid','=',$bid];
		}else{
			$business_sysset = Db::name('business_sysset')->where('aid',aid)->find();
			if(!$business_sysset || $business_sysset['status']==0 || $business_sysset['product_isshow']==0){
				$where[] = ['bid','=',0];
			}
		}
		
		if(input('param.field') && input('param.order')){
			$order = input('param.field').' '.input('param.order').',sort,id desc';
		}else{
			$order = 'sort desc,id desc';
		}
		//分类 
		if(input('param.cid')){
			$cid = input('post.cid') ? input('post.cid/d') : input('param.cid/d');
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
        //优惠券可用商品列表
        $cpid = input('param.cpid/d');
        if($cpid > 0){
            $coupon = Db::name('coupon')->where('id',$cpid)->find();
            $where[] = ['bid','=',$coupon['bid']];
            if($coupon['fwtype']==4){ //指定商品
                $where[] = ['id','in',$coupon['yuyue_productids']];
            }
        }

		if(input('param.keyword')){
			$where[] = ['name','like','%'.input('param.keyword').'%'];
		}
		$pernum = 10;
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$field = "id,pic,name,sales,sell_price,danwei";
        if(getcustom('extend_yuyue_car')){
            $field .=',type'; 
        }
        if(getcustom('yuyue_product_lvprice')){
            $field .=',lvprice,lvprice_data'; 
        }
		$datalist = Db::name('yuyue_product')->field($field)->where($where)->page($pagenum,$pernum)->order($order)->select()->toArray();
		if(!$datalist) $datalist = [];
		if(getcustom('yuyue_product_lvprice')){
			$datalist = $this->formatprolist($datalist);
		}
		return $this->json(['status'=>1,'data'=>$datalist]);
	}
	public function prolist(){
		if(input('param.bid')){
			$bid = input('param.bid/d');
		}else{
			$bid = 0;
		}
		//分类
		if(input('param.cid')){
			$clist = Db::name('yuyue_category')->where('aid',aid)->where('pid',input('param.cid/d'))->where('status',1)->order('sort desc,id')->select()->toArray();
			if(!$clist) $clist = [];
		}else{
			$clist = Db::name('yuyue_category')->where('aid',aid)->where('bid',$bid)->where('pid',0)->where('status',1)->order('sort desc,id')->select()->toArray();
			if(!$clist) $clist = [];
		}
		return $this->json(['clist'=>$clist]);
	}
	
	//分类商品
	public function classify(){
		if(input('param.bid')){
			$bid = input('param.bid/d');
		}else{
			$bid = 0;
		}
		$clist = Db::name('yuyue_category')->where('aid',aid)->where('pid',0)->where('bid',$bid)->where('status',1)->order('sort desc,id')->select()->toArray();
		foreach($clist as $k=>$v){
			$rs = Db::name('yuyue_category')->where('aid',aid)->where('pid',$v['id'])->where('status',1)->order('sort desc,id')->select()->toArray();
			if(!$rs) $rs = [];
			$clist[$k]['child'] = $rs;
		}
		return $this->json(['status'=>1,'data'=>$clist]);
	}
	//商品
	public function product(){
		$proid = input('param.id/d');
		$product = Db::name('yuyue_product')->where('id',$proid)->where('aid',aid)->find();
		if(!$product) return $this->json(['status'=>0,'msg'=>'商品不存在']);
		if($product['status']==0) return $this->json(['status'=>0,'msg'=>'商品未上架']);
		if($product['ischecked']!=1) return $this->json(['status'=>0,'msg'=>'商品未审核']);
		
		if($product['status']==2 && (strtotime($product['start_time']) > time() || strtotime($product['end_time']) < time())){
			return $this->json(['status'=>0,'msg'=>'商品未上架']);
		}
		if($product['status']==2) $product['status']=1;
		if(!$product['pics']) $product['pics'] = $product['pic'];
		$product['pics'] = explode(',',$product['pics']);
		$product = $this->formatproduct($product);

		//优惠券
		$couponlist = Db::name('coupon')->where('aid',aid)->where('bid',$product['bid'])->where('tolist',1)->where('type','in','1,4')->where("unix_timestamp(starttime)<=".time()." and unix_timestamp(endtime)>=".time())->order('sort desc')->select()->toArray();
		$newcplist = [];
		foreach($couponlist as $k=>$v){
			$gettj = explode(',',$v['gettj']);
			if(!in_array('-1',$gettj) && !in_array($this->member['levelid'],$gettj)){ //不是所有人
				continue;
			}
            if($v['isgive'] == 2) continue;//仅转赠
            //0全场通用,4指定服务商品
            if(!in_array($v['fwtype'],[0,4])){
                continue;
            }
            if($v['fwtype']==4){//指定服务商品可用
                $productids = explode(',',$v['yuyue_productids']);
                if(!in_array($product['id'],$productids)){
                    continue;
                }
            }
			$haveget = Db::name('coupon_record')->where('aid',aid)->where('mid',mid)->where('couponid',$v['id'])->count();
			$v['haveget'] = $haveget;
			//$v['starttime'] = date('m-d H:i',strtotime($v['starttime']));
			//$v['endtime'] = date('m-d H:i',strtotime($v['endtime']));
            if($v['yxqtype'] == 1){
                $yxqtime = explode(' ~ ',$v['yxqtime']);
                $v['yxqdate'] = strtotime($yxqtime[1]);
            }elseif($v['yxqtype'] == 2){
                $v['yxqdate'] = time() + 86400 * $v['yxqdate'];
            }elseif($v['yxqtype'] == 3) {
                //次日起计算有效期
                $v['yxqdate'] = strtotime(date('Y-m-d')) + 86400 * ($v['yxqdate'] + 1) - 1;
            }
            if($v['bid'] > 0){
                $v['bname'] = Db::name('business')->where('aid',aid)->where('id',$v['bid'])->value('name');
            }
			$newcplist[] = $v;
		}
		//是否收藏
		$rs = Db::name('member_favorite')->where('aid',aid)->where('mid',mid)->where('proid',$proid)->where('type','yuyue')->find();
		if($rs){
			$isfavorite = true;
		}else{
			$isfavorite = false;
		}
		//获取评论
		$commentlist = Db::name('yuyue_comment')->where('aid',aid)->where('proid',$proid)->where('status',1)->order('id desc')->limit(10)->select()->toArray();
		if(!$commentlist) $commentlist = [];
		foreach($commentlist as $k=>$pl){
			$commentlist[$k]['createtime'] = date('Y-m-d H:i',$pl['createtime']);
			if($commentlist[$k]['content_pic']) $commentlist[$k]['content_pic'] = explode(',',$commentlist[$k]['content_pic']);
		}
		$commentcount = Db::name('yuyue_comment')->where('aid',aid)->where('proid',$proid)->where('status',1)->count();

		//添加浏览历史
		if(mid){
			$rs = Db::name('member_history')->where('aid',aid)->where('mid',mid)->where('proid',$proid)->where('type','yuyue')->find();
			if($rs){
				Db::name('member_history')->where('id',$rs['id'])->update(['createtime'=>time()]);
			}else{
				Db::name('member_history')->insert(['aid'=>aid,'mid'=>mid,'proid'=>$proid,'type'=>'yuyue','createtime'=>time()]);
			}
		}
        //商品服务
        if($product['fuwupoint']){
            $fuwulist = explode(' ', $product['fuwupoint']);
        }else{
            $fuwulist = [];
        }
		if($product['fwid']){
			$fuwulist2 = Db::name('yuyue_fuwu')->where('aid',aid)->where('status',1)->where('id','in',$product['fwid'])->order('sort desc,id')->select()->toArray();
		}else{
			$fuwulist2 = [];
		}

		$sysset = Db::name('admin_set')->where('aid',aid)->field('name,logo,desc,fxjiesuantype,tel,kfurl,gzts,ddbb')->find();

		if($product['balance'] > 0){
			$product['advance_price'] = round($product['sell_price'] * (1 - $product['balance'] *0.01),2);
			$product['balance_price'] = round($product['sell_price'] * $product['balance'] *0.01,2);
		}
		if($product['bid']!=0){
			$business = Db::name('business')->where('aid',aid)->where('id',$product['bid'])->field('id,name,logo,desc,tel,address,sales,kfurl')->find();
		}else{
			$business = $sysset;
		}
		$product['detail'] = \app\commons\System::initpagecontent($product['detail'],aid,mid,platform);
        if(getcustom('form_jingmo_auth')){
            $pagecontent = json_decode($product['detail'],true);
            if(platform == 'wx' || platform == 'mp'){
                if(!$this->member){
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


		$product['comment_starnum'] = floor($product['comment_score']);
		
		$sysset['showgzts'] = false;
		//关注提示
		if(platform == 'mp'){
			$sysset['gzts'] = explode(',',$sysset['gzts']);
			if(in_array('2',$sysset['gzts']) && $this->member['subscribe']==0){
				$appinfo = \app\commons\System::appinfo(aid,'mp');
				$sysset['qrcode'] = $appinfo['qrcode'];
				$sysset['gzhname'] = $appinfo['nickname'];
				$sysset['showgzts'] = true;
			}
		}
		//获取设置
		$set = Db::name('yuyue_set')->field('ad_status,ad_pic,ad_link,video_status,video_tag,video_title')->where('aid',aid)->where('bid',0)->find();
        if($set['video_tag']) $set['video_tag'] = explode(',',$set['video_tag']);
        else $set['video_tag'] = [];
		$times = [];
		$j = $product['wanhour'] - $product['zaohour'];

		for($i=strtotime($product['zaohour'].':00') ;$i<=strtotime($product['wanhour'].':00') ; $i=$i+60*$product['timejg']){
			$times[]=date("H:i",$i);
		}
		if($product['rqtype']==1){
			$datelist = $this->GetWeeks($product['yyzhouqi']);
		}elseif($product['rqtype']==2){
			$yybeigntime = strtotime($product['yybegintime']);
			$yyendtime = strtotime($product['yyendtime']);
			$datelist = $this->GetWeeks2($yybeigntime,$yyendtime);
		}elseif($product['rqtype']==3){
			$timeday = explode(',',$product['yytimeday']);
			foreach($timeday as $k=>$d){
				$year=date('Y',time()+86400).'年';
				$month=date('m',time()+86400).'月';
				$day=date('d',time()+86400*($d-1));
				if($d==1) $week='今天';
				if($d==2) $week='明天';
				if($d==3) $week='后天';
				if($d==4) $week='大后天';
				if($d>4) $week='';
				$datelist[$k]['key'] = $k;
				$datelist[$k]['weeks'] = $week;
				$datelist[$k]['date'] = $month.$day;
				$datelist[$k]['year'] = $year;
			}
		}elseif($product['rqtype']==4){
			$timedata = json_decode($product['selftimedata'],true);
			$datelist = $this->GetWeeks3($timedata);
		}

		$minprice = 999999999999999;
		$maxprice = 0;
		$gglist = Db::name('yuyue_guige')->where('proid',$product['id'])->select()->toArray();
		if(getcustom('yuyue_product_lvprice')){
			$gglist = $this->formatgglist($gglist,$product['bid'],$product['lvprice']);
		}
		foreach($gglist as $k=>$v){
			if($v['sell_price'] < $minprice){
				$minprice = $v['sell_price'];
			}
			if($v['sell_price'] > $maxprice){
				$maxprice = $v['sell_price'];
			}
		}
		$product['min_price'] = round($minprice,2);
		$product['max_price'] = round($maxprice,2);
		if(getcustom('yuyue_selecttime_with_stock')){
			$product['showdatetype'] = $product['showdatetype']?$product['showdatetype']:0;
		}else{
			$product['showdatetype'] = 0;
		}

		$isfuwu = false;
		if(getcustom('yuyue_date')){
			$isfuwu = true;
		}
        $shopset_field = 'showjd,comment,showcommission,hide_sales,hide_stock,show_lvupsavemoney';
        $shopset = Db::name('shop_sysset')->where('aid',aid)->field($shopset_field)->find();
		$rdata = [];
		$rdata['status'] = 1;
		$rdata['title'] = $product['name'];
		$rdata['isfuwu'] = $isfuwu;
        $rdata['set'] = $set ? $set : [];
		$rdata['isfavorite'] = $isfavorite;
		$rdata['product'] = $product;
        $rdata['fuwulist2'] = $fuwulist2;
        $rdata['fuwulist'] = $fuwulist;
		$rdata['business'] = $business;
		$rdata['shopset'] = $shopset;
		$rdata['commentlist'] = $commentlist;
		$rdata['commentcount'] = $commentcount;
		$rdata['datelist'] = $datelist;
		$rdata['daydate'] = $daydate;
		$rdata['sysset'] = $sysset;
		//$rdata['couponlist'] = $newcplist;

        if(getcustom('yuyue_selectpeople_inproduct')){
            $rdata['showselectpeople'] = true;
            //服务人员
            $worker = [];
            $workerid = input('?param.workerid')?input('param.workerid/d'):0;
            if($workerid){
                $worker = Db::name('yuyue_worker')->where('id',$workerid)->where('status',1)->where('aid',aid)->field('id,aid,realname,tel')->find();
            }else{
                //用户选择查询数量
                if($product['fwpeople'] == 1){
                    if(!empty($product['fwpeoid'])){
                        $fwpeoids = $product['fwpeoid']?explode(',',$product['fwpeoid']):'';
                        $count    = $fwpeoids?count($fwpeoids):0;
                        if($count == 1){
                            $worker = Db::name('yuyue_worker')->where('id',$fwpeoids[0])->where('status',1)->where('aid',aid)->field('id,aid,realname,tel')->find();
                        }
                    }
                }
            }
            $rdata['worker'] = $worker?$worker:'';
            //查询规格数量
            $ggarr = [];
            $countguige = Db::name('yuyue_guige')->where('proid',$product['id'])->where('aid',aid)->count('id');
            if($countguige == 1){
                $guige = Db::name('yuyue_guige')->where('proid',$product['id'])->where('aid',aid)->field('id,name')->find();
                $ggarr['ggname'] = $guige['name'];
                $ggarr['ggid']   = $guige['id'];
                $ggarr['proid']  = $product['id'];
                $ggarr['num']    = 1;
            }
            $rdata['ggarr'] = $ggarr?$ggarr:'';
        }
        if(getcustom('yuyue_datetype1_model_selnum')){
            $rdata['selmoretime'] = false;//是否需要多选时间
            //判断是否是:时间段、模式2多段模式、且时间段起订量大于等于1，需要多选
            if(($product['rqtype']<4) && $product['datetype'] == 1 && $product['datetype1_model'] == 1 && $product['datetype1_modelselnum'] >=1){
                $rdata['selmoretime'] = true;
                if($datelist){
                    $sort= 0;
                    foreach($datelist as &$dv){
                        $params = [
                            'date'=>$dv['year'].$dv['date'],
                            'proid'=>$product['id'],
                            'key'=>0,
                            'workerid'=>$worker?$worker['id']:0,
                            'sort'=>$sort,
                        ];
                        $isgetTime   = $this->isgetTime($params);
                        $dv['times'] = $isgetTime['times'];
                        $sort        = $isgetTime['sort'];
                    }
                    unset($dv);
                }
                $rdata['datetimes'] = $datelist;
            }
        }

		return $this->json($rdata);
	}
	//获取商品详情
	public function getproductdetail(){
		$proid = input('param.id/d');
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['id','=',$proid];
		$field = "bid,id,pic,name,sales,sell_price,guigedata,status,ischecked,start_time,end_time,minbuynum";
		if(getcustom('yuyue_product_lvprice')) {
            $field .= ',lvprice,lvprice_data';
        }
		$product = Db::name('yuyue_product')->field($field)->where($where)->find();
		if(!$product){
			return $this->json(['status'=>0,'msg'=>'商品不存在']);
		}
		$product = $this->formatproduct($product);
		if($product['status']==0) return $this->json(['status'=>0,'msg'=>'商品已下架']);
		if($product['ischecked']!=1) return $this->json(['status'=>0,'msg'=>'商品未审核']);
		if($product['status']==2 && (strtotime($product['start_time']) > time() || strtotime($product['end_time']) < time())){
			return $this->json(['status'=>0,'msg'=>'商品未上架']);
		}

		$gglist = Db::name('yuyue_guige')->where('proid',$product['id'])->select()->toArray();
		if(getcustom('yuyue_product_lvprice')) {
			$gglist = $this->formatgglist($gglist,$product['bid'],$product['lvprice']);
		}
		$guigelist = array();
		foreach($gglist as $k=>$v){
			if($product['balance'] > 0){
				$v['advance_price'] = round($v['sell_price'] * (1 - $product['balance']*0.01),2);
				$v['balance_price'] = round($v['sell_price'] * $product['balance']*0.01,2);
			}else{
				$v['balance_price'] = 0;
			}
			$guigelist[$v['ks']] = $v;
		}
		$guigedata = json_decode($product['guigedata'],true);
		$ggselected = [];
		foreach($guigedata as $v) {
			$ggselected[] = 0;
		}
		$ks = implode(',',$ggselected);
		$set = Db::name('yuyue_set')->where('aid',aid)->field('yuyue_numtext')->find();
		return $this->json(['status'=>1,'product'=>$product,'guigelist'=>$guigelist,'guigedata'=>$guigedata,'ggselected'=>$ggselected,'ks'=>$ks,'set'=>$set]);
	}
	//商品评价
	public function commentlist(){
		$proid = input('param.proid/d');
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$pernum = 20;
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['proid','=',$proid];
		$where[] = ['status','=',1];
		$datalist = Db::name('yuyue_comment')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		if(!$datalist) $datalist = [];
		foreach($datalist as $k=>$pl){
			$datalist[$k]['createtime'] = date('Y-m-d H:i',$pl['createtime']);
			if($datalist[$k]['content_pic']) $datalist[$k]['content_pic'] = explode(',',$datalist[$k]['content_pic']);
		}
		if(request()->isPost()){
			return $this->json(['status'=>1,'data'=>$datalist]);
		}
		$rdata = [];
		$rdata['datalist'] = $datalist;
		return $this->json($rdata);
	}
	//商品海报
	function getposter(){
		$this->checklogin();
		$post = input('post.');
		$platform = platform;
		$page = '/activity/yuyue/product';
		$scene = 'id_'.$post['proid'].'-pid_'.$this->member['id'];
		//if($platform == 'mp' || $platform == 'h5' || $platform == 'app'){
		//	$page = PRE_URL .'/h5/'.aid.'.html#'. $page;
		//}
		$posterset = Db::name('admin_set_poster')->where('aid',aid)->where('type','yuyue')->where('platform',$platform)->order('id')->find();

		$posterdata = Db::name('member_poster')->where('aid',aid)->where('mid',mid)->where('scene',$scene)->where('type','yuyue')->where('posterid',$posterset['id'])->find();
		if(true || !$posterdata){
			$product = Db::name('yuyue_product')->where('id',$post['proid'])->find();
			$product = $this->formatproduct($product);
			$sysset = Db::name('admin_set')->where('aid',aid)->find();
			$textReplaceArr = [
				'[头像]'=>$this->member['headimg'],
				'[昵称]'=>$this->member['nickname'],
				'[姓名]'=>$this->member['realname'],
				'[手机号]'=>$this->member['mobile'],
				'[商城名称]'=>$sysset['name'],
				'[商品名称]'=>$product['name'],
				'[商品销售价]'=>$product['sell_price'],
				'[商品市场价]'=>$product['sell_price'],
				'[商品图片]'=>$product['pic'],
			];

			$poster = $this->_getposter(aid,$product['bid'],$platform,$posterset['content'],$page,$scene,$textReplaceArr);
			$posterdata = [];
			$posterdata['aid'] = aid;
			$posterdata['mid'] = $this->member['id'];
			$posterdata['scene'] = $scene;
			$posterdata['page'] = $page;
			$posterdata['type'] = 'yuyue';
			$posterdata['poster'] = $poster;
			$posterdata['createtime'] = time();
			Db::name('member_poster')->insert($posterdata);
		}
		return $this->json(['status'=>1,'poster'=>$posterdata['poster']]);
	}


	//订单提交页
	public function buy(){
		$this->checklogin();
		$prodata = explode('-',input('param.prodata'));
		$multi_promotion = 0;
        if(getcustom('multi_promotion')){
            $multi_promotion = 1;
        }
		$adminset = Db::name('admin_set')->where('aid',aid)->find();
		//会员折扣
		$userlevel = Db::name('member_level')->where('aid',aid)->where('id',$this->member['levelid'])->find();
		$userinfo = [];
		$userinfo['id'] = $this->member['id'];
		$userinfo['realname'] = $this->member['realname'];
		$userinfo['tel'] = $this->member['tel'];
		$userinfo['discount'] = $userlevel['discount'];
		if(getcustom('yuyue_scoredk')){
			$userinfo['canscoredk']        = true;
			$userinfo['score']             = $this->member['score'];
			$userinfo['score2money']       = $adminset['score2money'];
			$userinfo['scoredk_money']     = round($userinfo['score'] * $userinfo['score2money'],2);
			$userinfo['scoredkmaxpercent'] = $adminset['scoredkmaxpercent'];
			$userinfo['scoremaxtype']      = 0; //0最大百分比 1最大抵扣金额
			$scoredkmaxmoney = 0;
		}

		$allbuydata = [];
		$autofahuo  = 0;

		if(getcustom('extend_yuyue_car')){
			$admin = Db::name('admin')->where('id',aid)->field('yuyuecar_status')->find();
			if($admin && $admin['yuyuecar_status'] == 1){
				$protype = -1;//商品类型，不同类型不能一同下单 -2 为无权限 -1为待赋值 0：普通 1：洗车
			}else{
				$protype = -2;
			}
		}

		$worker_id = input('?param.worker_id')?input('worker_id/d'):0;
		$yydate    = input('?param.yydate')?input('yydate'):'';
		$yydates   = input('?param.yydates')?input('yydates'):'';
		$yydates_num = 1;//多段时间段

		//是否是多时间段选择模式
        $selmoretime = false;
		foreach($prodata as $key=>$gwc){
			if($selmoretime){
                return $this->json(['status'=>0,'msg'=>'有商品开启多时间段模式，此模式仅支持一种商品下单']);
            }
			list($proid,$ggid,$num) = explode(',',$gwc);
			$yynum = $num * $yydates_num;//预约数量=数量和多段时间数量的乘积
			$product = Db::name('yuyue_product')->where('aid',aid)->where('ischecked',1)->where('id',$proid)->find();
			if($product['status']==0){
				return $this->json(['status'=>0,'msg'=>'商品未上架']);
			}
			if($product['status']==2 && (strtotime($product['start_time']) > time() || strtotime($product['end_time']) < time())){
				return $this->json(['status'=>0,'msg'=>'商品未上架']);
			}
			if($product['is_open']!=1){
				$tipmsg = $product['noopentip']?$product['noopentip']:'休息中';
				return $this->json(['status'=>0,'msg'=>$tipmsg]);
			}
            if(getcustom('yuyue_datetype1_model_selnum')){
                //判断是否是:时间段、模式2多段模式、且时间段起订量大于等于1，需要多选
                if(($product['rqtype']!=4) && $product['datetype'] == 1 && $product['datetype1_model'] == 1 && $product['datetype1_modelselnum'] >=1){
                    $selmoretime = true;
                }
            }
            if(!$selmoretime){
            	if($yydate){
            		$count = $this->getyytime($yydate,$product['id']);
	                if($count>=$product['yynum']){
	                    return $this->json(['status'=>0,'msg'=>$yydate.'该段时间预约人数已满']);
	                }
            	}
            }else{
                if(getcustom('yuyue_datetype1_model_selnum')){
                    if(!$yydates){
                        return $this->json(['status'=>0,'msg'=>'请先选择服务时间']);
                    }
                    $yydates_num = count($yydates);
                    if($yydates_num<$product['datetype1_modelselnum']){
                        return $this->json(['status'=>0,'msg'=>'服务时间最少选择'.$product['datetype1_modelselnum'].'个连续时间段']);
                    }
                    //验证时间段是否连续
                    if($yydates_num>1){
                    	$yydates2 = array_column($yydates,'sort');
                		array_multisort($yydates2 ,SORT_ASC,$yydates);
                        for($i=0;$i<($yydates_num-1);$i++){
                            $cha = $yydates[$i]['sort'] - $yydates[$i+1]['sort'];
                            if($cha!=-1){
                                 return $this->json(['status'=>0,'msg'=>'请选择连续的时间段']);
                            }
                        }
                    }
                    $porders = Db::name('yuyue_order')->where('proid',$proid)->where('status','in','1,2')->where('aid',aid)->field('yy_time,yy_times,yydates')->select()->toArray();
                    if($porders){
                        foreach($yydates as &$yv){
                            $yv['num'] = 0;
                            $yy_time = $yv['year'].$yv['date'].' '.$yv['time'];
                            foreach($porders as $ov){
                                if($ov['yy_times']){
                                	$yy_times = json_decode($ov['yy_times']);
                                    if(in_array($yy_time,$yy_times)){
                                        $yv['num'] += 1;
                                    }
                                }else if($yy_time == $ov['yy_time']){
                                    $yv['num'] += 1;
                                }
                            }
                            unset($ov);
                        }
                        unset($yv);
                        foreach($yydates as $yv){
                            $yy_time = $yv['year'].$yv['date'].' '.$yv['time'];
                            if($yv['num']>=$product['yynum']){
                                return $this->json(['status'=>0,'msg'=>$yy_time.'该段时间预约人数已满']);
                            }
                        }
                    }
                    $yynum = $num * $yydates_num;//预约数量=数量和多段时间数量的乘积
                }
            }
            if(getcustom('yuyue_selectpeople_inproduct')){
                if($product['fwpeople']==1){
                    if(!$worker_id) return $this->json(['status'=>0,'msg'=>'请选择服务人员']);
                    if(!$selmoretime){
                    	if(!$yydate) return $this->json(['status'=>0,'msg'=>'请选择预约时间']);
                        //查看该服务人员该时间是否已经预约出去
                        $count = Db::name('yuyue_order')->where('worker_id',$worker_id)->where('aid',aid)->where('status','in','1,2')->where('yy_time',$yydate)->count('id');
                        if($count){
                            return $this->json(['status'=>0,'msg'=>$yydate.'该段时间不可预约']);
                        }
                    }else{
                        //查看该服务人员该时间是否已经预约出去
                        $worders = Db::name('yuyue_order')->where('worker_id',$worker_id)->where('aid',aid)->where('status','in','1,2')->field('yy_time,yy_times,yydates')->select()->toArray();
                        if($worders){
                            foreach($yydates as $yv){
                                $yy_time = $yv['year'].$yv['date'].' '.$yv['time'];
                                foreach($worders as $ov){
                                    if($ov['yy_times']){
                                    	$yy_times = json_decode($ov['yy_times']);
                                        if(in_array($yy_time,$yy_times)){
                                            return $this->json(['status'=>0,'msg'=>$yy_time.'该段时间不可预约']);
                                        }
                                    }else if($yy_time == $ov['yy_time']){
                                        return $this->json(['status'=>0,'msg'=>$yy_time.'该段时间不可预约']);
                                    }
                                }
                                unset($ov);
                            }
                        }
                        unset($yv);
                    }
                }
            }

			if(getcustom('extend_yuyue_car')){
				if($protype>= -1){
					if($protype== -1 ){
						$protype = $product['type'];
					}else{
						if($product['type'] != $protype){
							return $this->json(['status'=>0,'msg'=>'订单存在不同类型商品，不能一同下单']);
						}
					}
				}
			}


			if($product['freighttype'] == 3 || $product['freighttype'] == 4) $autofahuo = $product['freighttype'];
			$guige = Db::name('yuyue_guige')->where('id',$ggid)->find();
			if(getcustom('yuyue_product_lvprice')){
				$guige = $this->formatguige($guige, $product['bid'],$product['lvprice']);
			}
            if(getcustom('extend_yuyue_car')){
            	//如果规格不存在
                if(!$guige){
                    $guige = Db::name('yuyue_guige')->where('aid',aid)->where('proid',$proid)->order('id asc')->find();
                }
                if(!$num){
                    $num = $product['minbuynum'];
                }
            }
            if(getcustom('yuyue_scoredk')){
                if($product['scoredkmaxset']==0){
                    if($userinfo['scoredkmaxpercent'] == 0){
                        $userinfo['scoremaxtype'] = 1;
                        $scoredkmaxmoney += 0;
                    }else{
                        if($userinfo['scoredkmaxpercent'] > 0 && $userinfo['scoredkmaxpercent']<100){
                            $scoredkmaxmoney += $userinfo['scoredkmaxpercent'] * 0.01 * $guige['sell_price'] * $yynum;
                        }else{
                            $scoredkmaxmoney += $guige['sell_price'] * $yynum;
                        }
                    }
                }elseif($product['scoredkmaxset']==1){
                    $userinfo['scoremaxtype'] = 1;
                    $scoredkmaxmoney += $product['scoredkmaxval'] * 0.01 * $guige['sell_price'] * $yynum;
                }elseif($product['scoredkmaxset']==2){
                    $userinfo['scoremaxtype'] = 1;
                    $scoredkmaxmoney += $product['scoredkmaxval'] * $yynum;
                }else{
                    $userinfo['scoremaxtype'] = 1;
                    $scoredkmaxmoney += 0;
                }
            }

			if(!$allbuydata[$product['bid']]) $allbuydata[$product['bid']] = [];
			if(!$allbuydata[$product['bid']]['prodata']) $allbuydata[$product['bid']]['prodata'] = [];
			$allbuydata[$product['bid']]['prodata'][] = ['product'=>$product,'guige'=>$guige,'num'=>$num];
		}
		if(getcustom('yuyue_scoredk')){
			$userinfo['scoredkmaxmoney'] = round($scoredkmaxmoney,2);
		}
		if($product['rqtype']==1){
			$datelist = $this->GetWeeks($product['yyzhouqi']);
		}elseif($product['rqtype']==2){
			$yybeigntime = strtotime($product['yybegintime']);
			$yyendtime = strtotime($product['yyendtime']);
			$datelist = $this->GetWeeks2($yybeigntime,$yyendtime);
		}elseif($product['rqtype']==3){
			$timeday = explode(',',$product['yytimeday']);
			foreach($timeday as $k=>$d){
				$year=date('Y',time()+86400).'年';
				$month=date('m',time()+86400).'月';
				$day=date('d',time()+86400*($d-1));
				if($d==1) $week='今天';
				if($d==2) $week='明天';
				if($d==3) $week='后天';
				if($d==4) $week='大后天';
				if($d>4) $week='';
				$datelist[$k]['key'] = $k;
				$datelist[$k]['weeks'] = $week;
				$datelist[$k]['date'] = $month.$day;
				$datelist[$k]['year'] = $year;
			}
		}

		//服务人员
		$worker_id = input('param.worker_id');
		if($worker_id){
			$fw = Db::name('yuyue_worker')->where('aid',aid)->where('id',$worker_id)->find();
			if(!$fw) $fw = [];
		}
		if(getcustom('yuyue_save_people')){
			if(!$worker_id){
				//查询之前的订单保存的服务人员
				$order =  Db::name('yuyue_order')->where('aid',aid)->where('mid',mid)->where('proid',$product['id'])->order('id desc')->find();
				$worker_id = $order['worker_id'];
				$fw = Db::name('yuyue_worker')->where('aid',aid)->where('id',$worker_id)->find();
				if(!$fw) $fw = [];
			}
		}
		$address = Db::name('member_address')->where('aid',aid)->where('mid',mid)->where('latitude','>',0)->order('isdefault desc,id desc')->find();
		if(!$address) $address = [];
		$needLocation = 0;
		$allproduct_price = 0;
		foreach($allbuydata as $bid=>$buydata){
			if($bid!=0){
				$business = Db::name('business')->where('id',$bid)->field('id,aid,cid,name,logo,tel,address,sales,longitude,latitude,start_hours,end_hours,start_hours2,end_hours2,start_hours3,end_hours3,end_buy_status,invoice,invoice_type,province,city,district')->find();
				
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
			}else{
				$business = Db::name('admin_set')->where('aid',aid)->field('id,name,logo,desc,tel,province,city,district,address')->find();
                $business['province'] = $business['province']?$business['province']:'';
                $business['city'] = $business['city']?$business['city']:'';
                $business['district'] = $business['district']?$business['district']:'';
			}
		
			$product_priceArr = [];
			$product_price = 0;
			$needzkproduct_price = 0;
			$totalweight = 0;
			$totalnum = 0;
			$prodataArr = [];
			$proids = [];
			$cids = [];
			foreach($buydata['prodata'] as $prodata){
				$yynum = $prodata['num'] * $yydates_num;//预约数量=数量和多段时间数量的乘积
				$product_priceArr[]  = $prodata['guige']['sell_price'] * $yynum;
				$product_price      += $prodata['guige']['sell_price'] * $yynum;

				if($prodata['product']['balance']){
					$product_price = $product_price * (1-$prodata['product']['balance']*0.01);
				}
				if($prodata['product']['lvprice']==0 && $prodata['product']['no_discount'] == 0){ //未开启会员价
					$needzkproduct_price += $prodata['guige']['sell_price'] * $yynum;
				}
				$totalprice = $prodata['guige']['sell_price'] * $yynum;
				$totalnum += $prodata['num'];
				$prodataArr[] = $prodata['product']['id'].','.$prodata['guige']['id'].','.$prodata['num'];
				$proids[] = $prodata['product']['id'];
				$cids = array_merge(explode(',',$prodata['product']['cid']),$cids);
			}
			$prodatastr = implode('-',$prodataArr);
			
			$yyset = db('yuyue_set')->where('aid',aid)->find();
			if(!$yyset) $yyset=[];
			$leveldk_money = 0;
			if($yyset['discount']==1 && $userlevel && $userlevel['discount']>0 && $userlevel['discount']<10){
				$leveldk_money = $needzkproduct_price * (1 - $userlevel['discount'] * 0.1);
			}
			$leveldk_money = round($leveldk_money,2);
			$price = $product_price - $leveldk_money;
            $manjian_money = 0;
			$newcouponlist = [];
			if($yyset['iscoupon']==1){
                $bwhere2 = [];
                $bwhere2 = [['type','in','1,4,10']];
				if($bid > 0){
					$business = Db::name('business')->where('aid',aid)->where('id', $bid)->find();
					$bcids = $business['cid'] ? explode(',',$business['cid']) : [];
				}else{
					$bcids = [];
				}
				if($bcids){
					$whereCid = [];
					foreach($bcids as $bcid){
						$whereCid[] = "find_in_set({$bcid},canused_bcids)";
					}
					$whereCids = implode(' or ',$whereCid);
				}else{
					$whereCids = '0=1';
				}

				$couponList = Db::name('coupon_record')->where('aid',aid)->where('mid',mid)->where($bwhere2)->where('status',0)
					->whereRaw("bid=-1 or bid=".$bid." or (bid=0 and (canused_bids='all' or find_in_set(".$bid.",canused_bids) or ($whereCids)))")->where('minprice','<=',$price - $manjian_money)->where('starttime','<=',time())->where('endtime','>',time())
				->order('id desc')->select()->toArray();
                if(getcustom('yuyue_coupon') && $product['couponids']){
                    //预约取出计次卡
                    $bwhere2 = [];
                    $bwhere2[] = ['couponid','in',$product['couponids']];
                    $bwhere2[] = ['type','=','3'];
                    $couponList2 = Db::name('coupon_record')
                        ->where("bid=-1 or bid=".$bid)->where('aid',aid)->where('mid',mid)->where($bwhere2)->where('status',0)->where('minprice','<=',$price - $manjian_money)->where('starttime','<=',time())->where('endtime','>',time())
                        ->order('id desc')->select()->toArray();
                    if(!empty($couponList2)){
                        if(!empty($couponList)){
                            $couponList = array_merge($couponList,$couponList2);
                        }else{
                            $couponList = $couponList2;
                        }
                    }
                }

				if(!$couponList) $couponList = [];
				foreach($couponList as $k=>$v){
					//$couponList[$k]['starttime'] = date('m-d H:i',$v['starttime']);
					//$couponList[$k]['endtime'] = date('m-d H:i',$v['endtime']);
					$couponinfo = Db::name('coupon')->where('aid',aid)->where('id',$v['couponid'])->find();
                    if(empty($couponinfo)){
                        continue;
                    }
                    //不可自用
                    if($couponinfo['isgive']==2){
                        continue;
                    }
                    //适用场景
                    if($couponinfo['fwscene'] !==0){
                        continue;
                    }
                    //0全场通用,4指定服务商品
                    if(in_array($couponinfo['fwtype'],[0,4])){
                        if($couponinfo['fwtype']==4){//指定服务商品可用
                            $productids = explode(',',$couponinfo['yuyue_productids']);
                            if(!array_intersect($proids,$productids)){
                                continue;
                            }
                        }
                        if($v['bid'] > 0){
                            $binfo = Db::name('business')->where('aid',aid)->where('id',$v['bid'])->find();
                            $v['bname'] = $binfo['name'];
                        }
                        $newcouponlist[] = $v;
                    }else{
                        continue;
                    }
				}
			}
			
			$text = ['上门服务'=>'上门服务','到店服务'=>'到店服务'];
			if(getcustom('yuyue_fuwutype_text')){
				if($yyset['fuwutype_text']) $text = json_decode($yyset['fuwutype_text'], true);
			}

			//查看服务方式
			if($product['fwtype']){
				$fwtype = explode(',',$product['fwtype']);
				$fwtypelist = [];
			    if(in_array(2,$fwtype)) $fwtypelist[] =  ['name'=>$text['上门服务'],'key'=>2];
				if(in_array(1,$fwtype))	$fwtypelist[] = ['name'=>$text['到店服务'],'key'=>1];
				if(in_array(3,$fwtype))	$fwtypelist[] = ['name'=>'到商家服务','key'=>3];
			}
			//取出设置的自定义表单
			//$yyset = db('yuyue_set')->where('aid',aid)->find();
			$couponList = $newcouponlist;
		
			$allbuydata[$bid]['bid'] = $bid;
			$allbuydata[$bid]['business'] = $business;
			$allbuydata[$bid]['prodatastr'] = $prodatastr;
			$allbuydata[$bid]['couponList'] = $couponList;
			$allbuydata[$bid]['couponCount'] = count($couponList);
			$allbuydata[$bid]['sell_price'] = round($price,2);
			$allbuydata[$bid]['leveldk_money'] = $leveldk_money;
			$allbuydata[$bid]['coupon_money'] = 0;
			$allbuydata[$bid]['coupontype'] = 1;
			$allbuydata[$bid]['couponrid'] = 0;
			$allbuydata[$bid]['editorFormdata'] = [];
			$allbuydata[$bid]['product_price'] = round($product_price,2);
			$allbuydata[$bid]['balance'] = $product['balance'];
			$allbuydata[$bid]['formdata'] = json_decode($product['formdata'],true);
			$allbuydata[$bid]['fwtype'] = $product['fwtype'];
			$allbuydata[$bid]['fwpeople'] = $product['fwpeople'];
			$allbuydata[$bid]['fw'] = $fw;
			$allbuydata[$bid]['bid'] = $bid;
			$allproduct_price += $product_price;
		}
		
		$isdate = false;
		if(getcustom('yuyue_date')){
			$isdate=true;
		}

		$rdata = [];
		$rdata['isdate'] = $isdate;
		$rdata['datelist'] = $datelist;
		$rdata['status'] = 1;
		$rdata['address'] = $address;
		$rdata['linkman'] = $address ? $address['name'] : strval($userinfo['realname']);
		$rdata['tel'] = $address ? $address['tel'] : strval($userinfo['tel']);
		if(!$rdata['linkman']){
			$lastorder = Db::name('yuyue_order')->where('aid',aid)->where('mid',mid)->where('linkman','<>','')->find();
			if($lastorder){
				$rdata['linkman'] = $lastorder['linkman'];
				$rdata['tel'] = $lastorder['tel'];
			}
		}
		$rdata['userinfo'] = $userinfo;
		$rdata['allbuydata'] = $allbuydata;
		$rdata['fwtypelist'] = $fwtypelist;
		$rdata['yyset'] = $yyset;
		if(getcustom('extend_yuyue_car')){
			$rdata['protype'] = $protype>=-1?$protype:0;//商品类型
			$carinfor = '';
			$car = Db::name('member_car')->where('mid',mid)->where('isdefault',1)->where('aid',aid)->find();
			if($car){
				if($car['type'] == 1){
					$typename = 'SUV';
				}else if($cara['type'] == 2){
					$typename = 'MPV';
				}else{
					$typename = '轿车';
				}
				$carinfor = $car['number'].'.'.$car['color'].'.'.$typename;
				$rdata['carinfor'] = ['id'=>$car['id'],'infor'=>$carinfor];//车型信息
			}else{
				$rdata['carinfor'] = '';//车型信息
			}
		}
		if(getcustom('yuyue_before_starting')){
			$tmplids = [];
			if(platform == 'wx'){
				$wx_tmplset = Db::name('wx_tmplset')->where('aid',aid)->find();
				if($wx_tmplset['tmpl_yuyue_before_starting']){
					$tmplids[] = $wx_tmplset['tmpl_yuyue_before_starting'];
				}
			}
			$rdata['tmplids'] = $tmplids;
		}
		$rdata['fw'] = $fw;
		return $this->json($rdata);
	}

	public function createOrder(){
		$this->checklogin();
		$sysset = Db::name('admin_set')->where('aid',aid)->find();
		$post = input('post.');
		$buydata = $post['buydata'];

		$worker_id = input('?param.worker_id')?input('worker_id/d'):0;

		$yydate    = input('?param.yydate')?input('yydate'):'';
		if(!$yydate) return $this->json(['status'=>0,'msg'=>'请选择预约时间']);

		$yydates   = input('?param.yydates')?input('yydates'):'';
		$yydates_num = 1;//多少时间段数量 默认一
		$fwbid     = input('?param.fwbid')?input('fwbid/d'):0;
		if($post['fwtype']==3){
			if(!$fwbid) return $this->json(['status'=>0,'msg'=>'请选择服务商家']);
		}

		$protype = -2;
		if(getcustom('extend_yuyue_car')){
			$admin = Db::name('admin')->where('id',aid)->field('yuyuecar_status')->find();
			if($admin && $admin['yuyuecar_status'] == 1){
				$protype = -1;//商品类型，不同类型不能一同下单 -2 为无权限 -1为待赋值 0：普通 1：洗车
				//处理商品类型，返回商品类型、地址、车辆信息
				$protype_res = \app\customs\YuyueCustom::deal_protype(aid,mid,$buydata,$post,$protype);
				if($protype_res['status'] == 0){
					return $this->json($protype_res['status']);
				}
				$protype = $protype_res['protype'];
				$car     = $protype_res['car'];
				$address = $protype_res['address'];
			}
		}

		if($protype !=1){
			//收货地址
			if($post['fwtype']==1 || $post['fwtype']==3){
				$address = ['id'=>0,'name'=>$post['linkman'],'tel'=>$post['tel'],'area'=>'','address'=>''];
			}else{
				$address = Db::name('member_address')->where('id',$post['addressid'])->where('aid',aid)->where('mid',mid)->find();
				if(!$address['longitude'] || !$address['latitude']){
                    $mapqq = new \app\commons\MapQQ();
                    $res = $mapqq->addressToLocation($address['area'].$address['address']);
                    if($res && $res['status']==1){
                        $data = [];
                        $data['longitude'] = $res['longitude'];
                        $data['latitude'] = $res['latitude'];
                        Db::name('member_address')->where('id',$address['id'])->update($data);
                    }
					$address = Db::name('member_address')->where('id',$address['id'])->find();
				}
				if(!$address) return $this->json(['status'=>0,'msg'=>'所选收货地址不存在']);
			}
		}

	    $userlevel = Db::name('member_level')->where('aid',aid)->where('id',$this->member['levelid'])->find();

		$couponridArr = [];
		foreach($buydata as $data){ //判断有没有重复选择的优惠券
			if($data['couponrid'] && in_array($data['couponrid'],$couponridArr)){
				return $this->json(['status'=>0,'msg'=>t('优惠券').'不可重复使用']);
			}elseif($data['couponrid']){
				$couponridArr[] = $data['couponrid'];
			}
		}

		$ordernum = date('ymdHis').rand(100000,999999);
		$i = 0;
		$alltotalprice = 0;

		$scoredkmaxmoney = 0;
		$scoremaxtype    = 0; //0按系统设置 1商品独立设置

		//是否是多时间段选择模式
        $selmoretime = false;
		foreach($buydata as $data){
			if($selmoretime){
                return $this->json(['status'=>0,'msg'=>'有商品开启多时间段模式，此模式仅支持一种商品下单']);
            }
			$i++;
			if($data['prodata']){
				$prodata = explode('-',$data['prodata']);
			}else{
				return $this->json(['status'=>0,'msg'=>'产品数据错误']);
			}
			$bid = $data['bid'];
			$product_priceArr = [];
			$product_price = 0;
			$totalprice = 0;
			$balance_price = 0;
			$needzkproduct_price = 0;
			$givescore = 0;
			$totalnum = 0;
			$prolist = [];
			$proids = [];
			$cids = [];

			foreach($prodata as $key=>$pro){
				$sdata = explode(',',$pro);
				$sdata[2] = intval($sdata[2]);
				if($sdata[2] <= 0) return $this->json(['status'=>0,'msg'=>'购买数量有误']);
				$yynum = $sdata[2] * $yydates_num;//预约数量=数量和多段时间数量的乘积

				$product = Db::name('yuyue_product')->where('aid',aid)->where('id',$sdata[0])->find();
				if(!$product) return $this->json(['status'=>0,'msg'=>'产品不存在或已下架']);
				if($product['status']==0){
					return $this->json(['status'=>0,'msg'=>'商品未上架']);
				}				
				if($product['status']==2 && (strtotime($product['start_time']) > time() || strtotime($product['end_time']) < time())){
					return $this->json(['status'=>0,'msg'=>'商品未上架']);
				}
				if($product['is_open']!=1){
					$tipmsg = $product['noopentip']?$product['noopentip']:'休息中';
					return $this->json(['status'=>0,'msg'=>$tipmsg]);
				}
				if(getcustom('yuyue_commission')){
					if($product['minbuynum'] > 0 && $sdata[2] < $product['minbuynum']){
						return $this->json(['status'=>0,'msg'=>'该服务最少购买'.$product['minbuynum'].'份']);
					}
				}

				if($product['fwtype']){
					$fwtype = explode(',',$product['fwtype']);
				    if(!in_array($post['fwtype'],$fwtype)){
				    	return $this->json(['status'=>0,'msg'=>'服务方式不存在']);
				    }
				}else{
					if($post['fwtype']) return $this->json(['status'=>0,'msg'=>'服务方式不存在']);
				}

				if($post['fwtype']==2 && $product['isareaxz']==1){
					if($product['areadata']){
						$isfuwu = false;
						$regionlist = explode('];',$product['areadata']);
						foreach($regionlist as $j=>$regiondata){
							$regiondata = explode('[',$regiondata);
							$citys = explode(',',$regiondata[1]);
							$address['city'] = $address['city']??'';
							if($regiondata[0] == $address['province']){
								if($regiondata[1] == '全部地区'){
									$isfuwu = true;
								}else{
									if(empty($address['city'])){
										return $this->json(['status'=>0,'msg'=>'所选地址城市信息有误，请选择其他地址或联系系统人员']);
									}
									if(in_array($address['city'],$citys)){
										$isfuwu = true;
									}
								}
							} 
						}
						if(!$isfuwu){
							return $this->json(['status'=>0,'msg'=>'该城市不在服务范围内，请重新选择']);
						}
					}

					if(getcustom('yuyue_arearange')){
                        //地图限制
                        if($product['selmap'] == 1){
                        	//多边形范围
                            if($product['peisong_rangetype'] == 1){
                                $pspointsArr = explode(';',$product['peisong_rangepath']);
                                $pspoints = [];
                                foreach($pspointsArr as $pspoint){
                                    $pspointArr = explode(',',$pspoint);
                                    $pspoints[] = ['lat'=>$pspointArr[1],'lng'=>$pspointArr[0]];
                                }
                                if($pspoints){
                                    $rs = \app\models\Freight::is_point_in_polygon(['lat'=>$address['latitude'],'lng'=>$address['longitude']],$pspoints);
                                    if(!$rs){
                                        return $this->json(['status'=>0,'freight_price'=>0,'isoutjuli'=>1,'msg'=>'您的地址超出范围']);
                                    }
                                }
                            }else{
                                $juli = getdistance($address['longitude'],$address['latitude'],$product['peisong_lng'],$product['peisong_lat'],2);
                                if($juli > $product['peisong_range']/1000){
                                    return $this->json(['status'=>0,'freight_price'=>0,'isoutjuli'=>1,'msg'=>'您的地址超出范围']);
                                }
                            }
                        }
                    } 
				}else if($post['fwtype'] == 3){
                    if(getcustom('yuyue_gobusiness')){
                        if($product['gobids']){
                            $gobids = explode(',',$product['gobids']);
                            if(!in_array($fwbid,$gobids)){
                                return $this->json(['status'=>0,'msg'=>'服务商家有误']);
                            }
                            $fwbusiness = Db::name('business')->where('id',$fwbid)->where('status',1)->where('aid',aid)->field('id,aid,name,logo,tel,linkman,linktel,province,city,district,address,latitude,longitude')->find();
                            if(!$fwbusiness){
                                return $this->json(['status'=>0,'msg'=>'服务商家不存在']);
                            }
                        }else{
                            return $this->json(['status'=>0,'msg'=>'该商品服务商家信息不完善']);
                        }
                    }
                }

                if(getcustom('yuyue_datetype1_model_selnum')){
                    //判断是否是:时间段、模式2多段模式、且时间段起订量大于等于1，需要多选
                    if(($product['rqtype']!=4)&& $product['datetype'] == 1 && $product['datetype1_model'] == 1 && $product['datetype1_modelselnum'] >=1){
                        $selmoretime = true;
                    }
                }
                if(!$selmoretime){
                    $count = $this->getyytime($yydate,$product['id']);
                    if($count>=$product['yynum']){
                        return $this->json(['status'=>0,'msg'=>$yydate.'该段时间预约人数已满']);
                    }
                }else{
                    if(getcustom('yuyue_datetype1_model_selnum')){
                        if(!$yydates){
                            return $this->json(['status'=>0,'msg'=>'请先选择服务时间']);
                        }
                        $yydates_num = count($yydates);
                        if($yydates_num<$product['datetype1_modelselnum']){
                            return $this->json(['status'=>0,'msg'=>'服务时间最少选择'.$product['datetype1_modelselnum'].'个连续时间段']);
                        }
                        //验证时间段是否连续
                        if($yydates_num>1){
                            $yydates2 = array_column($yydates,'sort');
                            array_multisort($yydates2 ,SORT_ASC,$yydates);
                            for($i=0;$i<($yydates_num-1);$i++){
                                $cha = $yydates[$i]['sort'] - $yydates[$i+1]['sort'];
                                if($cha!=-1){
                                     return $this->json(['status'=>0,'msg'=>'请选择连续的时间段']);
                                }
                            }
                        }
                        $porders = Db::name('yuyue_order')->where('proid',$proid)->where('status','in','1,2')->where('aid',aid)->field('yy_time,yy_times,yydates')->select()->toArray();
                        if($porders){
                            foreach($yydates as &$yv){
                                $yv['num'] = 0;
                                $yy_time = $yv['year'].$yv['date'].' '.$yv['time'];
                                if(strpos($yy_time,'年') === false){
                                    $yy_time = date('Y').'年'.$yy_time;
                                }
                                $yy_time = preg_replace(['/年|月/','/日/'],['-',''],$yy_time);
                                $yv['datetime'] = strtotime($yy_time);
                                foreach($porders as $ov){
                                    if($ov['yy_times']){
                                    	$yy_times = json_decode($ov['yy_times']);
                                    	if(in_array($yy_time,$yy_times)){
                                    		$yv['num'] += 1;
                                    	}
                                    }else if($yy_time == $ov['yy_time']){
                                        $yv['num'] += 1;
                                    }
                                }
                                unset($ov);
                            }
                            unset($yv);
                            foreach($yydates as $yv){
                                $yy_time = $yv['year'].$yv['date'].' '.$yv['time'];
                                if($yv['num']>=$product['yynum']){
                                    return $this->json(['status'=>0,'msg'=>$yy_time.'该段时间预约人数已满']);
                                }
                            }
                        }
                        $yynum = $sdata[2] * $yydates_num;//预约数量=数量和多段时间数量的乘积
                    }
                }

                if($product['fwpeople']==1){
                    if(!$worker_id) return $this->json(['status'=>0,'msg'=>'请选择服务人员']);
                    if(getcustom('yuyue_selectpeople_inproduct')){
                        if(!$selmoretime){
                            //查看该服务人员该时间是否已经预约出去
                            $count = Db::name('yuyue_order')->where('worker_id',$worker_id)->where('aid',aid)->where('status','in','1,2')->where('yy_time',$yydate)->count('id');
                            if($count){
                                return $this->json(['status'=>0,'msg'=>'该段时间不可预约']);
                            }
                        }else{
                            //查看该服务人员该时间是否已经预约出去
                            $worders = Db::name('yuyue_order')->where('worker_id',$worker_id)->where('aid',aid)->where('status','in','1,2')->field('yy_time,yy_times,yydates')->select()->toArray();
                            if($worders){
                                foreach($yydates as $yv){
                                    $ydtime = $yv['year'].$yv['date'].' '.$yv['time'];
                                    foreach($worders as $ov){
                                        if($ov['yy_times']){
                                        	$yy_times = json_decode($ov['yy_times']);
                                        	if(in_array($ydtime,$ov['yy_times'])){
	                                            return $this->json(['status'=>0,'msg'=>$ydtime.'该段时间不可预约']);
	                                        }
                                        }else if($ydtime == $ov['yy_time']){
                                            return $this->json(['status'=>0,'msg'=>$ydtime.'该段时间不可预约']);
                                        }
                                    }
                                    unset($ov);
                                }
                            }
                            unset($yv);
                        }
                    }
                }

				if($key==0) $title = $product['name'];
				$guige = Db::name('yuyue_guige')->where('aid',aid)->where('id',$sdata[1])->find();
				if(getcustom('yuyue_product_lvprice')){
					$guige = $this->formatguige($guige, $product['bid'],$product['lvprice']);
				}
				if(!$guige) return $this->json(['status'=>0,'msg'=>'产品规格不存在或已下架']);
				if(getcustom('yuyue_scoredk')){
                    if($product['scoredkmaxset']==0){
                        if ($sysset['scoredkmaxpercent'] == 0) {
                            $scoredkmaxmoney += 0;
                        } else {
                            if ($sysset['scoredkmaxpercent'] > 0 && $sysset['scoredkmaxpercent'] < 100) {
                                $scoredkmaxmoney += $sysset['scoredkmaxpercent'] * 0.01 * $guige['sell_price'] * $yynum;
                            } else {
                                $scoredkmaxmoney += $guige['sell_price'] * $yynum;
                            }
                        }
                    }elseif($product['scoredkmaxset']==1){
                        $scoremaxtype = 1;
                        $scoredkmaxmoney += $product['scoredkmaxval'] * 0.01 * $guige['sell_price'] * $yynum;
                    }elseif($product['scoredkmaxset']==2){
                        $scoremaxtype = 1;
                        $scoredkmaxmoney += $product['scoredkmaxval'] * $yynum;
                    }else{
                        $scoremaxtype = 1;
                        $scoredkmaxmoney += 0;
                    }
                }
				$totalnum += $sdata[2];

				$product_price += $guige['sell_price'] * $yynum;
				$givescore += $guige['givescore'] * $yynum;
				if($product['balance']>0){
                    $balance_price += $product_price * $product['balance']*0.01;
                    $totalprice += $product_price * (1-$product['balance']*0.01);
				}else{
                    $totalprice += $product_price;
                }

				$product_priceArr[] = $guige['sell_price'] * $yynum;
				if($product['lvprice']==0 && $product['no_discount'] == 0){ //未开启会员价
					$needzkproduct_price += $guige['sell_price'] * $yynum;
				}
				$prolist[] = ['product'=>$product,'guige'=>$guige,'num'=>$sdata[2]];
				$proids[] = $product['id'];
				$cids = array_merge($cids,explode(',',$product['cid']));
			}
		}

		//查看该服务人员该时间是否已经预约出去
		if($worker_id>0){
			$order = Db::name('yuyue_order')->where('aid',aid)->where('worker_id',$worker_id)->where('status','in','1,2')->where('yy_time',$yydate)->find();
			if($order){
				return $this->json(['status'=>0,'msg'=>$yydate.'该段时间不可预约']);
			}
		}

		$yyset = db('yuyue_set')->where('aid',aid)->find();
		//会员折扣
		$leveldk_money = 0;
		if($yyset['discount']==1 && $userlevel && $userlevel['discount']>0 && $userlevel['discount']<10){
			$leveldk_money = $needzkproduct_price * (1 - $userlevel['discount'] * 0.1);
		}
		if($totalprice>=$leveldk_money){
			$totalprice = $totalprice - $leveldk_money;
		}else{
			$totalprice = 0;
		}
		if(getcustom('yuyue_selecttime_with_stock')){
			if($product['showdatetype']==1){
				$yydate = explode('~',$yydate);
				$yydate = $yydate[0];
			}
		}

		//开始时间
		if(!$selmoretime){
			$begindate = $yydate;
		}else{
			$begindate = $yydates[0]['year'].$yydates[0]['date'].' '.$yydates[0]['time'];
		}
		if(strpos($begindate,'年') === false){
			$begindate = date('Y').'年'.$begindate;
		}
		$begindate = preg_replace(['/年|月/','/日/'],['-',''],$begindate);
		$date = date('Y-m-d H:i:s',strtotime(date('H:i',time())));
		$begintime = strtotime($begindate);
		if($begintime <= strtotime(date('H:i',time()))+$product['pdprehour']*60*60){
			return $this->json(['status'=>0,'msg'=>'预约时间已过，请选择其他时间']);
		}

		//优惠券
		if($data['couponrid'] > 0){
			$couponrid = $data['couponrid'];

			$bid = $data['bid'];
			if($bid > 0){
				$business = Db::name('business')->where('aid',aid)->where('id', $bid)->find();
				$bcids = $business['cid'] ? explode(',',$business['cid']) : [];
			}else{
				$bcids = [];
			}
			if($bcids){
				$whereCid = [];
				foreach($bcids as $bcid){
					$whereCid[] = "find_in_set({$bcid},canused_bcids)";
				}
				$whereCids = implode(' or ',$whereCid);
			}else{
				$whereCids = '0=1';
			}
			$couponrecord = Db::name('coupon_record')->where('aid',aid)->where('mid',mid)->where('id',$couponrid)
				->whereRaw("bid=-1 or bid=".$bid." or (bid=0 and (canused_bids='all' or find_in_set(".$bid.",canused_bids) or ($whereCids)))")->find();
			if(!$couponrecord){
				return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'不存在']);
			}elseif($couponrecord['status']!=0){
				return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'已使用过了']);
			}elseif($couponrecord['starttime'] > time()){
				return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'尚未开始使用']);	
			}elseif($couponrecord['endtime'] < time()){
				return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'已过期']);	
			}elseif($couponrecord['minprice'] > $totalprice){
				return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'不符合条件']);
			}elseif(!getcustom('yuyue_coupon') && $couponrecord['type']!=1 && $couponrecord['type']!=4 && $couponrecord['type']!=10){
				return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'不符合条件']);
			}
	
			if(getcustom('yuyue_coupon') && $couponrecord['type']==3 && $product['couponids']){
				if(!in_array($couponrecord['couponid'],explode(',',$product['couponids']))){
					return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'不符合条件']);	
				}
			}
			$couponinfo = Db::name('coupon')->where('aid',aid)->where('id',$couponrecord['couponid'])->find();
            if(empty($couponinfo)){
                return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'不存在或已作废']);
            }
            if($couponrecord['from_mid']==0 && $couponinfo && $couponinfo['isgive']==2){
                return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'仅可转赠']);
            }
            //适用场景
            if($couponinfo['fwscene']!==0){
                return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'不符合条件']);
            }
            //0全场通用,4指定服务商品
            if(!in_array($couponinfo['fwtype'],[0,4])){
                return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'超出可用范围']);
            }
            if($couponinfo['fwtype']==4){//指定服务商品可用
                $productids = explode(',',$couponinfo['yuyue_productids']);
                if(!array_intersect($proids,$productids)){
                    return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'超出可用范围']);
                }
            }
			if($couponrecord['type']==1 || $couponrecord['type']==4){
				Db::name('coupon_record')->where('id',$couponrid)->update(['status'=>1,'usetime'=>time()]);
			}

			if($couponrecord['type']==4){//运费抵扣券
				$coupon_money = 0;
			}elseif($couponrecord['type']==3){//计次券 抵扣全部金额
				$coupon_money = $totalprice;
			}elseif($couponrecord['type']==10){//折扣券
				$coupon_money = $totalprice * (100 - $couponrecord['discount']) * 0.01;
			}else{
				$coupon_money = $couponrecord['money'];
				if($coupon_money > $totalprice) $coupon_money = $totalprice;
			}
		}else{
			$coupon_money = 0;
		}
		$totalprice = $totalprice - $coupon_money;
		
		//积分抵扣
		$scoredkscore = 0;
		$scoredk_money = 0;
		if(getcustom('yuyue_scoredk')){
			if($post['usescore']==1){
				$score2money       = $this->sysset['score2money'];
				$scoredkmaxpercent = $this->sysset['scoredkmaxpercent'];
				$scorebdkyf        = $this->sysset['scorebdkyf'];
				$scoredk_money     = $this->member['score'] * $score2money;
				if($scoredk_money > $totalprice) $scoredk_money = $totalprice;

				if($scoremaxtype == 0){
					if($scoredkmaxpercent >= 0 && $scoredkmaxpercent < 100 && $scoredk_money > 0 && $scoredk_money > $totalprice * $scoredkmaxpercent * 0.01){
						$scoredk_money = $totalprice * $scoredkmaxpercent * 0.01;
					}
				}else{
					if($scoredk_money > $scoredkmaxmoney) $scoredk_money = $scoredkmaxmoney;
				}
				$totalprice = $totalprice - $scoredk_money;
				$totalprice = round($totalprice*100)/100;
				if($scoredk_money > 0){
					$scoredkscore = $scoredk_money / $score2money;
					$scoredkscore = dd_money_format($scoredkscore);
				}
			}
		}

        $payprice = $totalprice;
        //派单设置jiesuantype=1比例 0 固定金额；tichengtype=1按实际结算 0按商品
        //订单总金额
        if($balance_price>0){
            $totalprice = $totalprice+$balance_price;
        }
        if($product['jiesuantype']==1){
            if($product['tichengtype']==1){
                $paidan_money = $totalprice*$product['tc_bfb']/100;
            }else{
                $paidan_money = $product_price*$product['tc_bfb']/100;
            }
        }else{
            $paidan_money = $product['tcmoney'];
        }
		$orderdata = [];
		//计次卡
		if(getcustom('yuyue_coupon') && $couponrecord['type']==3){
			if ($couponrecord['used_count'] >= $couponrecord['limit_count']) {
				return json(['status'=>0,'msg'=>'已使用全部次数']);
			}
			Db::name('coupon_record')->where('aid',aid)->where('bid',$data['bid'])->where('id',$couponrecord['id'])->inc('used_count',$sdata[2])->update();
			$hxorder = [];
			$hxorder['aid'] = aid;
			$hxorder['bid'] = $data['bid'];
			$hxorder['uid'] = $worker_id?$worker_id:0; //师傅的id
			$hxorder['mid'] = mid;
			$hxorder['orderid'] = $couponrecord['id'];
			$hxorder['ordernum'] = date('YmdHis');
			$hxorder['title'] = $couponrecord['couponname'];
			$hxorder['type'] = 'coupon';
			$hxorder['createtime'] = time();
			$hxorder['remark'] = '购买'.$title;
			Db::name('hexiao_order')->insert($hxorder);
			if($couponrecord['used_count']+1>=$couponrecord['limit_count']){
				Db::name('coupon_record')->where('id',$couponrecord['id'])->update(['status'=>1,'usetime'=>time()]);
			}
			$orderdata['status'] = 1;
			$orderdata['paytype'] = '次卡支付';
			$orderdata['paytime'] = time();
			$orderdata['paytypeid'] = '16';
		}


		$orderdata['aid'] = aid;
		$orderdata['mid'] = mid;
		$orderdata['bid'] = $data['bid'];
		if(count($buydata) > 1){
			$orderdata['ordernum'] = $ordernum.'_'.$i;
		}else{
			$orderdata['ordernum'] = $ordernum;
		}

		$orderdata['title'] = $title.(count($prodata)>1?'等':'');
		$orderdata['linkman'] = $address['name'];
		$orderdata['tel'] = $address['tel'];
		$orderdata['area'] = $address['area'];
		$orderdata['address'] = $address['address'];
		$orderdata['longitude'] = $address['longitude'];
		$orderdata['latitude'] = $address['latitude'];
		$orderdata['area2'] = $address['province'].','.$address['city'].','.$address['district'];
		$orderdata['totalprice'] = $totalprice;
		if($totalprice == 0 && !getcustom('yuyue_peisonguser_changeprice')){
			$orderdata['status'] = 1;
			$orderdata['paytime'] = time();
		}
		$orderdata['leveldk_money'] = $leveldk_money;	//会员折扣
		if(getcustom('yuyue_scoredk')){
			$orderdata['scoredk_money'] = $scoredk_money;	//积分抵扣
			$orderdata['scoredkscore']  = $scoredkscore;	//抵扣的积分
		}
		$orderdata['product_price'] = $product_price;
		$orderdata['cost_price'] = $guige['cost_price'];
		$orderdata['givescore'] = $givescore;
		$orderdata['coupon_money'] = $coupon_money;		//优惠券抵扣
		$orderdata['coupon_rid'] = $couponrid;
		$orderdata['yy_time'] = $yydate; //预约时间
		$orderdata['createtime'] = time();
		$orderdata['platform'] = platform;
		$orderdata['hexiao_code'] = random(16);
		$orderdata['remark'] = $post['remark'];
		$orderdata['proname'] = $product['name'];
		$orderdata['ggname'] = $guige['name'];
		$orderdata['num'] = $sdata[2];
		$orderdata['balance_price'] = $balance_price;	
		$orderdata['propic'] = $guige['pic'] ? $guige['pic'] : $product['pic'];
		$orderdata['proid'] = $product['id'];
		$orderdata['paidan_type'] = $product['fwpeople'];
		$orderdata['ggid'] = $guige['id'];
		$orderdata['worker_id'] = $worker_id;
		$orderdata['fwtype'] = $post['fwtype']; //服务方式
		$orderdata['begintime'] = $begintime; 
		$orderdata['hexiao_qr'] = createqrcode(m_url('admin/hexiao/hexiao?type=yuyue&co='.$orderdata['hexiao_code']));
		if(getcustom('buy_selectmember')){
			if($post['checkmemid']) $orderdata['checkmemid'] = $post['checkmemid'];
		}
		$orderdata['paidan_money'] = $paidan_money;

		if(getcustom('hmy_yuyue')){	//预约定制自己的分类id
			$cidarr = explode(',',$product['cid']);
			$appcid = db('yuyue_category')->field('appid')->where('id','in',$cidarr)->order('pid asc')->select()->toArray();
			$appcid =  array_column($appcid, 'appid');
			$orderdata['firstCategory'] = $appcid[0];
			$orderdata['secondCategory'] = $appcid[1];

			$config = include(ROOT_PATH.'config.php');
			$appId=$config['hmyyuyue']['appId'];
			$appSecret=$config['hmyyuyue']['appSecret'];
			$url2 = 'https://shifu.api.kkgj123.cn/api/1/commission/rule';
			$headrs = array('content-type: application/json;charset=UTF-8','appid:'.$appId,'appSecret:'.$appSecret);
			$res2 = curl_get($url2,$param=[],$headrs);
			$res2 = json_decode($res2,true);
			if($res2['code']==200){
				$res2 = $res2['data'];
				if($res2['platformServiceCommissionRule']==1){
					$orderdata['commission'] = $paidan_money;
				}else{
					$orderdata['commission'] = $product_price;
				}
			}
		}
		if(getcustom('yuyue_commission')){
            \app\customs\Yuyue::yuyueCommission(aid,$this->member,$product,$guige,$orderdata);
		}
		if(getcustom('extend_yuyue_car')){
			if($protype == 1){
				$orderdata['protype']    = 1;
				$orderdata['carid']      = $car['id'];
				$orderdata['car_number'] = $car['number'];
				$orderdata['car_color']  = $car['color'];
				$orderdata['car_type']   = $car['type'];
			}
		}

        if(getcustom('yuyue_gobusiness')){
        	//到多商户家服务
            if($post['fwtype'] == 3){
                $orderdata['fwbid']     = $fwbid;
                $orderdata['fwbusiness']= $fwbusiness?json_encode($fwbusiness):'';
            }
        }
        if(getcustom('yuyue_datetype1_model_selnum')){
        	//时间段多选模式
            $orderdata['yydates'] = $yydates?json_encode($yydates,JSON_UNESCAPED_UNICODE):''; //服务时间原数组
            $orderdata['yy_times']= '';//服务时间数组
            if($yydates){
                $yy_times = [];
                foreach($yydates as $yv){
                    $yy_times[] = $yv['year'].$yv['date'].' '.$yv['time'];
                }
                unset($yv);
                $orderdata['yy_times']= json_encode($yy_times,JSON_UNESCAPED_UNICODE);//服务时间数组
            }
        }
        if(getcustom('yuyue_datetype1_autoendorder')){
            //时间段自动完成
            $orderdata['datetype'] = 0;//0：无 1 时间段 2 时间点
            if($product['rqtype']!=4){
                $orderdata['datetype'] = $product['datetype']??0;//1 时间段 2 时间点
                if($product['datetype'] == 1){
                	$orderdata['datetype1_autoendorder'] = $product['datetype1_autoendorder']??0;//0 手动完成 1 自动完成
                    //时间段 预约结束时间
                    if(!$selmoretime){
                        $orderdata['yyendtime'] = $orderdata['begintime']+$product['timejg']*60;
                    }else{
                        $yylen = $yydates?count($yydates):0;
                        if($yylen>=1){
                            $yyendtime = $yydates[$yylen-1]['year'].$yydates[$yylen-1]['date'].' '.$yydates[$yylen-1]['time2'];
                            if(strpos($yyendtime,'年') === false){
                                $yyendtime = date('Y').'年'.$yyendtime;
                            }
                            $yyendtime = preg_replace(['/年|月/','/日/'],['-',''],$yyendtime);
                            $date = date('Y-m-d H:i:s',strtotime(date('H:i',time())));
                            $orderdata['yyendtime'] = strtotime($yyendtime);
                        }
                    }
                }
            }
        }

		$orderid = Db::name('yuyue_order')->insertGetId($orderdata);

		if(getcustom('yuyue_commission')){
			if($orderdata['parent1'] && ($orderdata['parent1commission'] || $orderdata['parent1score'])){
				Db::name('member_commission_record')->insert(['aid'=>aid,'mid'=>$orderdata['parent1'],'frommid'=>mid,'orderid'=>$orderid,'ogid'=>$product['id'],'type'=>'yuyue','commission'=>$orderdata['parent1commission'],'score'=>$orderdata['parent1score'],'remark'=>'下级购买服务奖励','createtime'=>time()]);
			}
			if($orderdata['parent2'] && ($orderdata['parent2commission'] || $orderdata['parent2score'])){
				Db::name('member_commission_record')->insert(['aid'=>aid,'mid'=>$orderdata['parent2'],'frommid'=>mid,'orderid'=>$orderid,'ogid'=>$product['id'],'type'=>'yuyue','commission'=>$orderdata['parent2commission'],'score'=>$orderdata['parent2score'],'remark'=>'下二级购买服务奖励','createtime'=>time()]);
			}
			if($orderdata['parent3'] && ($orderdata['parent3commission'] || $orderdata['parent3score'])){
				Db::name('member_commission_record')->insert(['aid'=>aid,'mid'=>$orderdata['parent3'],'frommid'=>mid,'orderid'=>$orderid,'ogid'=>$product['id'],'type'=>'yuyue','commission'=>$orderdata['parent3commission'],'score'=>$orderdata['parent3score'],'remark'=>'下三级购买服务奖励','createtime'=>time()]);
			}
		}

		$this->saveformdata($orderid,'yuyue_order',$data['formdata'],$product['id']);
		if(($orderdata['status']==0 && $orderdata['totalprice']>0) || getcustom('yuyue_peisonguser_changeprice')){
            //如果用户选择了服务人员，则直接创建服务单且服务人员可以修改未支付的订单价格
            if(getcustom('yuyue_peisonguser_changeprice') && $orderdata['worker_id']>0){
                $orderdata['id'] = $orderid;
                $rs = \app\models\YuyueWorkerOrder::create($orderdata,$orderdata['worker_id'],'');
                if($rs['status']==1 && $rs['worker_orderid']>0){
                    Db::name('yuyue_order')->where('aid',aid)->where('id',$orderid)->update(['worker_orderid'=>$rs['worker_orderid']]);
                }
            }
			$payorderid = \app\models\Payorder::createorder(aid,$orderdata['bid'],$orderdata['mid'],'yuyue',$orderid,$orderdata['ordernum'],$orderdata['title'],$payprice,$scoredkscore);
		}else{
			if($yyset['paidantype']==0 && $yyset['isautopd']==1){
				$order = Db::name('yuyue_order')->where('id',$orderid)->find();
				$rs = \app\models\YuyueWorkerOrder::create($order,0,'');
			}
		}

        if($bid)
            $store_info = Db::name('business')->where('aid',aid)->where('id',$orderdata['bid'])->find();
        else
            $store_info = Db::name('admin_set')->where('aid',aid)->find();
        $store_name = $store_info['name'];

        //公众号通知 订单提交成功
        $tmplcontent = [];
        $tmplcontent['first'] = '有新预约订单提交成功';
        $tmplcontent['remark'] = '点击进入查看~';
        $tmplcontent['keyword1'] = $store_name; //店铺
        $tmplcontent['keyword2'] = date('Y-m-d H:i:s',$orderdata['createtime']);//下单时间
        $tmplcontent['keyword3'] = $orderdata['title'];//商品
        $tmplcontent['keyword4'] = $orderdata['totalprice'].'元';//金额
        $tempconNew = [];
        $tempconNew['character_string2'] = $orderdata['ordernum'];//订单号
        $tempconNew['thing8'] = $store_name;//门店名称
        $tempconNew['thing3'] = $orderdata['title'];//商品名称
        $tempconNew['amount7'] = $orderdata['totalprice'];//金额
        $tempconNew['time4'] = date('Y-m-d H:i:s',$orderdata['createtime']);//下单时间
        \app\commons\Wechat::sendhttmpl(aid,$orderdata['bid'],'tmpl_orderconfirm',$tmplcontent,m_url('admin/order/yuyueorder'),$orderdata['mdid'],$tempconNew);

        $tmplcontent = [];
        $tmplcontent['thing11'] = $orderdata['title'];
        $tmplcontent['character_string2'] = $orderdata['ordernum'];
        $tmplcontent['phrase10'] = $orderdata['status']==0?'待付款':'已付款';
        $tmplcontent['amount13'] = $orderdata['totalprice'].'元';
        $tmplcontent['thing27'] = $this->member['nickname'];
        \app\commons\Wechat::sendhtwxtmpl(aid,$orderdata['bid'],'tmpl_orderconfirm',$tmplcontent,'admin/order/yuyueorder',$orderdata['mdid']);

		if($orderdata['status']==1){
			//短信通知
			if($orderdata['tel']){
				$rs = \app\commons\Sms::send(aid,$orderdata['tel'],'tmpl_yysucess',['name'=>$orderdata['title'],'time'=>$orderdata['yy_time']]);
			}
			if($orderdata['worker_id']){
				//如果用户已经支付成功且 选择服务人员直接进行派单
				$order = Db::name('yuyue_order')->where('id',$orderid)->find();
				$rs = \app\models\YuyueWorkerOrder::create($order,$order['worker_id'],'');
            }

            //支付后送券
            $couponlist = \app\commons\Coupon::getpaygive(aid,mid,'yuyue',$orderdata['totalprice'],$orderid);
            if($couponlist){
                foreach($couponlist as $coupon){
                    if($coupon['buyyuyueprogive'] == 1){
                        $coupon['buyyuyueproids'] = explode(',',$coupon['buyyuyueproids']);
                        $coupon['buyyuyuepro_give_num'] = explode(',',$coupon['buyyuyuepro_give_num']);
                        foreach($coupon['buyyuyueproids'] as $k => $proid) {
                            if($proid == $orderdata['proid'] && $coupon['buyyuyuepro_give_num'][$k] > 0) {
                                for($i=0;$i<$coupon['buyyuyuepro_give_num'][$k];$i++) {
                                    \app\commons\Coupon::send(aid,mid,$coupon['id']);
                                }
                            }
                        }
                    }
                }
            }
		}
		if($balance_price > 0){
			$balancedata = [];
			$balancedata['aid'] = aid;
			$balancedata['bid'] = $orderdata['bid'];
			$balancedata['mid'] = $orderdata['mid'];
			$balancedata['orderid'] = $orderid;
			$balancedata['ordernum'] = $orderdata['ordernum'].'01';
			$balancedata['title'] = $orderdata['title'];
			$balancedata['money'] = $orderdata['balance_price'];
			$balancedata['type'] = 'yuyue_balance';
			$balancedata['score'] = 0;
			$balancedata['createtime'] = time();
			$balancedata['status'] = 0;
			$balance_pay_orderid = Db::name('payorder')->insertGetId($balancedata);
			Db::name('yuyue_order')->where('id',$orderid)->update(['balance_pay_orderid'=>$balance_pay_orderid]);
		}
		$num = $sdata[2];
		Db::name('yuyue_product')->where('aid',aid)->where('id',$product['id'])->update(['sales'=>Db::raw("sales+$num")]);

		return $this->json(['status'=>1,'payorderid'=>$payorderid,'msg'=>'提交成功']);
	}

	//保存自定义表单内容
	public function saveformdata($orderid,$type='yuyue_order',$formdata,$proid){
		if(!$orderid || !$formdata) return ['status'=>0];
		//根据orderid 取出proid
		$formfield = Db::name('yuyue_product')->where('id',$proid)->find();
		$formdataSet = json_decode($formfield['formdata'],true);
		//var_dump($formdataSet);die;
		$data = [];
		foreach($formdataSet as $k=>$v){
			$value = $formdata['form'.$k];
			if(is_array($value)){
				$value = implode(',',$value);
			}
			$value = strval($value);
			$data['form'.$k] = $v['val1'] . '^_^' .$value . '^_^' .$v['key'];
			if($v['val3']==1 && $value===''){
				return ['status'=>0,'msg'=>$v['val1'].' 必填'];
			}
		}
		$data['aid'] = aid;
		$data['type'] = 'yuyue_order';
		$data['orderid'] = $orderid;
		$data['createtime'] = time();
		Db::name('freight_formdata')->insert($data);
		return ['status'=>1];
	}
	//订单列表
	function orderlist(){
		$this->checklogin();
		$st = input('param.st');
		if(!$st && $st!=='0') $st = 'all';
		$pagenum = input('param.pagenum') ? input('param.pagenum') : 1;
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['mid','=',mid];
		$where[] = ['delete','=',0];
		if(input('param.keyword')) $where[] = ['ordernum|title', 'like', '%'.input('param.keyword').'%'];
		if($st == 'all'){
			
		}elseif($st == '0'){
			$where[] = ['status','=',0];
		}elseif($st == '1'){
			$where[] = ['status','=',1];
		}elseif($st == '2'){
			$where[] = ['status','=',2];
		}elseif($st == '3'){
			$where[] = ['status','=',3];
		}elseif($st == '4'){
			$where[] = ['status','=',4];
		}
		
		$datalist = Db::name('yuyue_order')->where($where)->order('id desc')->page($pagenum,10)->select()->toArray();
		if(!$datalist) $datalist = [];
		foreach($datalist as $key=>$v){
			if($v['bid']!=0){
				$datalist[$key]['binfo'] = Db::name('business')->where('aid',aid)->where('id',$v['bid'])->field('id,name,logo')->find();
			}
			//查看服务状态
			if($v['worker_orderid']>0){
				$datalist[$key]['worker'] = Db::name('yuyue_worker_order')->where('aid',aid)->where('id',$v['worker_orderid'])->field('id,status')->find();
			}
			$datalist[$key]['senddate'] = date('Y-m-d H:i:s',$v['send_time']);
            //包含尾款的订单金额显示判断
            if($v['balance_price']>0 && $v['balance_pay_status']==0){
                $datalist[$key]['totalprice'] = round($v['totalprice'] - $v['balance_price'],2);
            }
			//查询是否可取消
			$cancel = false;
			if($v['status'] == 1){
				if($v['totalprice'] == 0){
					$cancel = true;
				}
				if($v['worker_id'] && $v['worker_orderid']){
					$count = Db::name('yuyue_worker_order')->where('id',$v['worker_orderid'])->where('worker_id',$v['worker_id'])->where('status',1)->count();
					if($count){
						$cancel = false;
					}
				}
				if(getcustom('yuyue_canceltime')){
					$product = Db::name('yuyue_product')->where('id',$v['proid'])->where('aid',aid)->field('canceltime')->find();
					if($product){
						//计算现在离开始前取消时间
						$cha = $v['begintime']-time();
						if($cha>=0){
							if($product['canceltime'] == 0){
								$cancel = true;
							}else if($product['canceltime'] > 0){
								$ctime = $product['canceltime']*60;
								if($cha<=$ctime){
									$cancel = false;
								}else{
									$cancel = true;
								}
							}else{
								$cancel = false;
							}
						}else{
							$cancel = false;
						}
					}
				}
			}
			$datalist[$key]['cancel'] = $cancel;


			if(getcustom('yuyue_datetype1_model_selnum')){
				if(!empty($v['yydates'])){
					$yydates = json_decode($v['yydates'],true);
					$yydates_num = count($yydates);
					$datalist[$key]['yy_time'] .= ' '.$yydates_num.'个时间段';
				}
	        }
		}
		$ishowpaidan=false;
		if(getcustom('hmy_yuyue')){
			$ishowpaidan=true;
		}
		$rdata = [];
		$rdata['ishowpaidan'] = $ishowpaidan;
		$rdata['st'] = $st;
		$rdata['datalist'] = $datalist;
		return $this->json($rdata);
	}

	public function orderdetail(){
		$detail = Db::name('yuyue_order')->where('id',input('param.id/d'))->where('aid',aid)->where('mid',mid)->find();
		if(!$detail) return $this->json(['status'=>0,'msg'=>'订单不存在']);
		$detail['createtime'] = $detail['createtime'] ? date('Y-m-d H:i:s',$detail['createtime']) : '';
		$detail['collect_time'] = $detail['collect_time'] ? date('Y-m-d H:i:s',$detail['collect_time']) : '';
		$detail['paytime'] = $detail['paytime'] ? date('Y-m-d H:i:s',$detail['paytime']) : '';
		$detail['refund_time'] = $detail['refund_time'] ? date('Y-m-d H:i:s',$detail['refund_time']) : '';
		$detail['send_time'] = $detail['send_time'] ? date('Y-m-d H:i:s',$detail['send_time']) : '';
		$detail['formdata'] = \app\models\Freight::getformdata($detail['id'],'yuyue_order');
        $paymoney = $detail['totalprice'];
        if($detail['balance_price']>0 && $detail['balance_pay_status']==0){
            $paymoney = round($detail['totalprice'] - $detail['balance_price'],2);
        }
        $detail['paymoney'] = $paymoney;
		$storeinfo = [];
		if($detail['freight_type'] == 1){
            $storeinfo = Db::name('mendian')->where('id',$detail['mdid'])->field('id,name,address,longitude,latitude')->find();
		}
		
		if($detail['bid'] > 0){
			$binfo = Db::name('business')->where('aid',aid)->where('id',$detail['bid'])->field('id,name,logo,province,city,district,address')->find();
            $fulladdress = '';
            if(!in_array($binfo['province'],['北京市','上海市','重庆市','天津市']) && $binfo['province']){
                $fulladdress.=$binfo['province'];
            }
            if($binfo['city']) $fulladdress.=$binfo['city'];
            if($binfo['district']) $fulladdress.=$binfo['district'];
            if($binfo['address']) $fulladdress.=$binfo['address'];
            $binfo['fulladdress'] = $fulladdress;
            $detail['binfo'] = $binfo;
			$iscommentdp = 0;
			$commentdp = Db::name('business_comment')->where('orderid',$detail['id'])->where('aid',aid)->where('mid',mid)->find();
			if($commentdp) $iscommentdp = 1;
		}else{
			$detail['binfo'] = Db::name('admin_set')->where('aid',aid)->field('id,name,logo,province,city,district,address')->find();
			$iscommentdp = 1;
		}

		$prolist = Db::name('yuyue_order')->where('id',$detail['id'])->find();
		
		$yuyueset = Db::name('yuyue_set')->where('aid',aid)->field('autoclose')->find();
		
		if($detail['status']==0 && $yuyueset['autoclose'] > 0 && $detail['paytypeid'] != 5){
			$lefttime = strtotime($detail['createtime']) + $yuyueset['autoclose']*60 - time();
			if($lefttime < 0) $lefttime = 0;
		}else{
			$lefttime = 0;
		}
		
		if(getcustom('yuyue_gobusiness')){
			if($detail['fwtype'] == 3){
				$detail['fwbinfo'] = ['name'=>'不存在'];
				$fwbusines = Db::name('business')->where('id',$detail['fwbid'])->where('status',1)->where('aid',aid)->field('id,aid,name,logo,tel,linkman,linktel,province,city,district,address,latitude,longitude')->find();
				if($fwbusines){
					$fwbusines['address'] = $fwbusines['province'].$fwbusines['city'].$fwbusines['district'].$fwbusines['address'];
					$detail['fwbinfo'] = $fwbusines;
				}
				
			}
		}
		if(getcustom('yuyue_datetype1_model_selnum')){
			if(!empty($detail['yydates'])){
				$yydates = json_decode($detail['yydates'],true);
				$yydates_num = count($yydates);
				$detail['yy_time'] .= ' '.$yydates_num.'个时间段';
			}
        }
		$rdata = [];
		$rdata['status'] = 1;
		$rdata['detail'] = $detail;
		$rdata['iscommentdp'] = $iscommentdp;
		$rdata['prolist'] = $prolist;
		$rdata['storeinfo'] = $storeinfo;
		$rdata['lefttime'] = $lefttime;
		$rdata['codtxt'] = Db::name('shop_sysset')->where('aid',aid)->value('codtxt');

        //发票
        $rdata['invoice'] = 0;
        if($detail['bid']) {
            $rdata['invoice'] = Db::name('business')->where('aid',aid)->where('id',$detail['bid'])->value('invoice');
        } else {
            $rdata['invoice'] = Db::name('admin_set')->where('aid',aid)->value('invoice');
        }

		//定制 查看是否有工单可提交
		$rdata['isworkorder'] = 0;
		if(getcustom('workorder')){
			$workcount = 0+ Db::name('workorder_category')->where('aid',aid)->where('status',1)->where('isglorder',2)->count();
			$rdata['detail']['isworkorder']  = $workcount>0?1:0;
		}
		$text = ['上门服务'=>'上门服务','到店服务'=>'到店服务'];
		if(getcustom('yuyue_fuwutype_text')){
			$yyset = Db::name('yuyue_set')->where('aid',aid)->find();
			if($yyset['fuwutype_text']) $text = json_decode($yyset['fuwutype_text'], true);
		}
		$rdata['text'] = $text;
		return $this->json($rdata);
	}
	function refund(){//申请退款
		$this->checklogin();
		if(request()->isPost()){
			$post = input('post.');
			$orderid = intval($post['orderid']);
			$money = floatval($post['money']);
			$order = Db::name('yuyue_order')->where('aid',aid)->where('mid',mid)->where('id',$orderid)->find();

			$workerorder = Db::name('yuyue_worker_order')->where('aid',aid)->where('id',$order['worker_orderid'])->find();
			// if($order['worker_orderid']>0 && $workerorder['status']>0){
			// 	return $this->json(['status'=>0,'msg'=>'订单接单后不允许退款']);
			// }
			if(!$order || ($order['status']!=1 && $order['status'] != 2) || $order['refund_status'] == 2){
				return $this->json(['status'=>0,'msg'=>'订单状态不符合退款要求']);
			}
            $refundmoney = $order['totalprice'];
            if($order['balance_price']>0 && $order['balance_pay_status']==0){
                $refundmoney = round($order['totalprice'] - $order['balance_price'],2);
            }
			if($money < 0 || $money > $refundmoney){
				return $this->json(['status'=>0,'msg'=>'退款金额有误']);
			}
			if(getcustom('hmy_yuyue')){
				if($order['worker_orderid']>0 && $workerorder['status']>0){
					return $this->json(['status'=>0,'msg'=>'订单接单后不允许退款']);
				}
				Db::name('yuyue_order')->where('id',$orderid)->where('aid',aid)->where('bid',$order['bid'])->update(['refund_time'=>time(),'status'=>4,'refund_status'=>2,'refund_reason'=>$post['reason'],'refund_money'=>$money]);
				$order = Db::name('yuyue_order')->where('aid',aid)->where('mid',mid)->where('id',$orderid)->find();
				$rs = \app\customs\Yuyue::refund($order);
				return $this->json(['status'=>1,'msg'=>'退款成功']);
			}

            Db::name('yuyue_order')->where('aid',aid)->where('mid',mid)->where('id',$orderid)->update(['refund_time'=>time(),'refund_status'=>1,'refund_reason'=>$post['reason'],'refund_money'=>$money]);
            $tmplcontent = [];
            $tmplcontent['first'] = '有服务订单客户申请退款';
            $tmplcontent['remark'] = '点击进入查看~';
            $tmplcontent['keyword1'] = $order['ordernum'];
            $tmplcontent['keyword2'] = $money.'元';
            $tmplcontent['keyword3'] = $post['reason'];
            $tmplcontentNew = [];
            $tmplcontentNew['number2'] = $order['ordernum'];//订单号
            $tmplcontentNew['amount4'] = $money;//退款金额
            \app\commons\Wechat::sendhttmpl(aid,$order['bid'],'tmpl_ordertui',$tmplcontent,m_url('admin/order/yuyueorder'),$order['mdid'],$tmplcontentNew);

            $tmplcontent = [];
            $tmplcontent['thing1'] = $order['title'];
            $tmplcontent['character_string4'] = $order['ordernum'];
            $tmplcontent['amount2'] = $order['totalprice'];
            $tmplcontent['amount9'] = $money.'元';
            $tmplcontent['thing10'] = $post['reason'];
            \app\commons\Wechat::sendhtwxtmpl(aid,$order['bid'],'tmpl_ordertui',$tmplcontent,'admin/order/yuyueorder',$order['mdid']);

            return $this->json(['status'=>1,'msg'=>'提交成功,请等待商家审核']);
		}
		$rdata = [];
		$rdata['price'] = input('param.price/f');
		$rdata['orderid'] = input('param.orderid/d');
		$order = Db::name('yuyue_order')->where('aid',aid)->where('mid',mid)->where('id',$rdata['orderid'])->find();
		$rdata['price'] = $order['totalprice'];
		return $this->json($rdata);
	}

	 
	function logistics(){
		$get = input('param.');
		$worker_order = Db::name('yuyue_worker_order')->where('id',$get['express_no'])->find();
        $worker = Db::name('yuyue_worker')->where('id',$worker_order['worker_id'])->find();
        if(getcustom('hmy_yuyue')){
			//获取师傅信息
			$rs = \app\customs\Yuyue::getMaster($worker_order['worker_id']);
			$worker = [];
			$worker['realname'] =$rs['data']['name']; 
			$worker['tel'] = $rs['data']['phone']?$rs['data']['phone']:''; 
			//$worker['lon'] =$rs['data']['lon']; 
			//$worker['lat'] =$rs['data']['lat']; 
		}
		$orderinfoJ = json_decode($worker_order['orderinfo'],true);
		$order = Db::name('yuyue_order')->where('aid',aid)->where('id',$orderinfoJ['id'])->find();
        $paymoney = $order['totalprice'];
        if($order['balance_price']>0 && $order['balance_pay_status']==0){
            $paymoney = round($order['totalprice'] - $order['balance_price'],2);
        }
        $order['paymoney'] = $paymoney;
        $orderinfo = $order;
		$binfo = json_decode($worker_order['binfo'],true);
		$prolist = json_decode($worker_order['prolist'],true);
		
		if($worker_order['juli']> 1000){
			$worker_order['juli'] = round($worker_order['juli']/1000,1);
			$worker_order['juli_unit'] = 'km';
		}else{
			$worker_order['juli_unit'] = 'm';
		}
		//服务人员距用户的距离 骑行距离
        $mapqq = new \app\commons\MapQQ();
        $bicycl = $mapqq->getDirectionDistance($worker_order['longitude2'],$worker_order['latitude2'],$worker['longitude'],$worker['latitude'],1);
        if($bicycl && $bicycl['status']==1){
            $juli2 = $bicycl['distance'];
        }else{
            $juli2 = getdistance($worker_order['longitude2'],$worker_order['latitude2'],$worker['longitude'],$worker['latitude'],1);
        }
        $worker_order['juli2'] = $juli2;
		if($juli2> 1000){
			$worker_order['juli2'] = round($juli2/1000,1);
			$worker_order['juli2_unit'] = 'km';
		}else{
			$worker_order['juli2_unit'] = 'm';
		}
		$worker_order['leftminute'] = ceil(($worker_order['yujitime'] - time()) / 60);
		$worker_order['ticheng'] = round($worker_order['ticheng'],2);
		if($worker_order['status']==3){
			$worker_order['useminute'] = ceil(($worker_order['endtime'] - $worker_order['createtime']) / 60);
			$worker_order['useminute2'] = ceil(($worker_order['endtime'] - $worker_order['starttime']) / 60); 
		}
	   $info = Db::name('yuyue_set')->where('aid',aid)->find();
		
		$yuyue_sign = false;
		if(getcustom('yuyue_apply')){
			$yuyue_sign=true;
		}

		$rdata = [];
		$rdata['worker_order'] = $worker_order;
		$rdata['mid'] = mid;
		$rdata['binfo'] = $binfo;
		$rdata['worker'] = $worker;
		$rdata['orderinfo'] = $orderinfo;
		$rdata['prolist'] = $prolist;
		$rdata['set'] = $info;
		$rdata['yuyue_sign'] =$yuyue_sign;
		return $this->json($rdata);
	}
	function closeOrder(){
		$post = input('post.');
		$orderid = intval($post['orderid']);
		$order = Db::name('yuyue_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->find();
		if(!$order || $order['status']>1){
			return $this->json(['status'=>0,'msg'=>'关闭失败,订单状态错误']);
		}
		//查询是否可取消
		$cancel = false;
		if($order['status'] == 1){
			if($order['totalprice'] == 0){
				$cancel = true;
			}
			if($order['worker_id'] && $order['worker_orderid']){
				$count = Db::name('yuyue_worker_order')->where('id',$order['worker_orderid'])->where('worker_id',$order['worker_id'])->where('status',1)->count();
				if($count){
					return $this->json(['status'=>0,'msg'=>'关闭失败,订单已接单']);
				}
			}
			if(getcustom('yuyue_canceltime')){
				$product = Db::name('yuyue_product')->where('id',$order['proid'])->where('aid',aid)->field('canceltime')->find();
				if($product){
					//计算现在离开始前取消时间
					$cha = $order['begintime']-time();
					if($cha>=0){
						if($product['canceltime'] == 0){
							$cancel = true;
						}else if($product['canceltime'] > 0){
							$ctime = $product['canceltime']*60;
							if($cha<=$ctime){
								$cancel = false;
							}else{
								$cancel = true;
							}
						}else{
							$cancel = false;
						}
					}else{
						$cancel = false;
					}
				}
			} 
		}else{
			$cancel = true;
		}
		if(!$cancel){
			return $this->json(['status'=>0,'msg'=>'关闭失败,订单不可取消']);
		}
		//优惠券抵扣的返还
		if($order['coupon_rid'] > 0){
			//查看是不是计次卡
			$record = Db::name('coupon_record')->where('aid',aid)->where('mid',mid)->where('id',$order['coupon_rid'])->find();
			if(getcustom('yuyue_coupon') && $record['type']==3){  //将次数加回去
				Db::name('coupon_record')->where('aid',aid)->where('bid',$order['bid'])->where('id',$record['id'])->dec('used_count')->update();
				$hxorder = [];
				$hxorder['aid'] = aid;
				$hxorder['bid'] = $order['bid'];
				$hxorder['uid'] = 0; //师傅的id
				$hxorder['mid'] = mid;
				$hxorder['orderid'] = $record['id'];
				$hxorder['ordernum'] = date('YmdHis');
				$hxorder['title'] = $record['couponname'];
				$hxorder['type'] = 'coupon';
				$hxorder['createtime'] = time();
				$hxorder['remark'] = '订单取消:'.$order['title'];
				Db::name('hexiao_order')->insert($hxorder);
				if($record['status']==1)
					Db::name('coupon_record')->where('id',$record['id'])->update(['status'=>0,'usetime'=>'']);
			}else{
				Db::name('coupon_record')->where('aid',aid)->where('mid',mid)->where('id',$order['coupon_rid'])->update(['status'=>0,'usetime'=>'']);	
			} 
		}
		$rs = Db::name('yuyue_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->update(['status'=>4]);
		return $this->json(['status'=>1,'msg'=>'取消成功']);
	}
	function delOrder(){
		$post = input('post.');
		$orderid = intval($post['orderid']);
		$order = Db::name('yuyue_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->find();
		if(!$order || ($order['status']!=4 && $order['status']!=3)){
			return $this->json(['status'=>0,'msg'=>'删除失败,订单状态错误']);
		}
		if($order['status']==3){
			$rs = Db::name('yuyue_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->update(['delete'=>1]);
		}else{
			$rs = Db::name('yuyue_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->delete();
		}
		return $this->json(['status'=>1,'msg'=>'删除成功']);
	}
	function orderCollect(){ //确认完成
		$post = input('post.');
		$orderid = intval($post['orderid']);
		$order = Db::name('yuyue_order')->where('aid',aid)->where('mid',mid)->where('id',$orderid)->find();
		if(!$order || ($order['status']!=2) || $order['paytypeid']==4){
			return $this->json(['status'=>0,'msg'=>'订单状态不符合收货要求']);
		}
		if($order['balance_price'] > 0 && $order['balance_pay_status']==0) return $this->json(['status'=>0,'msg'=>'请先支付尾款']);
		$rs = \app\commons\Order::collect($order,'yuyue');
		if($rs['status'] == 0) return $this->json($rs);

		Db::name('yuyue_order')->where('aid',aid)->where('mid',mid)->where('id',$orderid)->update(['status'=>3,'collect_time'=>time()]);

		$return = ['status'=>1,'msg'=>'确认收货成功','url'=>true];
		

		$tmplcontent = [];
		$tmplcontent['first'] = '有订单客户已确认完成';
		$tmplcontent['remark'] = '点击进入查看~';
		$tmplcontent['keyword1'] = $this->member['nickname'];
		$tmplcontent['keyword2'] = $order['ordernum'];
		$tmplcontent['keyword3'] = $order['totalprice'].'元';
		$tmplcontent['keyword4'] = date('Y-m-d H:i',$order['paytime']);
        $tmplcontentNew = [];
        $tmplcontentNew['thing3'] = $this->member['nickname'];//收货人
        $tmplcontentNew['character_string7'] = $order['ordernum'];//订单号
        $tmplcontentNew['time8'] = date('Y-m-d H:i');//送达时间
		\app\commons\Wechat::sendhttmpl(aid,$order['bid'],'tmpl_ordershouhuo',$tmplcontent,m_url('admin/order/yuyueorder'),$order['mdid'],$tmplcontentNew);

		
		$tmplcontent = [];
		$tmplcontent['thing2'] = $order['title'];
		$tmplcontent['character_string6'] = $order['ordernum'];
		$tmplcontent['thing3'] = $this->member['nickname'];
		$tmplcontent['date5'] = date('Y-m-d H:i');
		\app\commons\Wechat::sendhtwxtmpl(aid,$order['bid'],'tmpl_ordershouhuo',$tmplcontent,'admin/order/yuyueorder',$order['mdid']);

		return $this->json($return);
	}
	//评价
	public function comment(){
		$oid = input('param.oid/d');
		$order = Db::name('yuyue_order')->where('id',$oid)->where('mid',mid)->find();
		if(!$order){
			return $this->json(['status'=>0,'msg'=>'未查找到相关记录']);
		}
		$comment = Db::name('yuyue_comment')->where('orderid',$oid)->where('aid',aid)->where('mid',mid)->find();
		if(request()->isPost()){
			if($comment){
				return $this->json(['status'=>0,'msg'=>'您已经评价过了']);
			}		
			$content = input('post.content');
			$content_pic = input('post.content_pic');
			$score = input('post.score/d');
			if($score < 1){
				return $this->json(['status'=>0,'msg'=>'请打分']);
			}
            $yuyueset = Db::name('yuyue_set')->where('aid',aid)->where('bid',0)->find();
			$data['aid'] = aid;
			$data['mid'] = mid;
			$data['bid'] = $order['bid'];
			$data['proid'] =$order['proid'];
			$data['proname'] = $order['proname'];
			$data['propic'] = $order['propic'];
			$data['orderid']= $order['id'];
			$data['ordernum']= $order['ordernum'];
			$data['score'] = $score;
			$data['content'] = $content;
			$data['openid']= $this->member['openid'];
			$data['nickname']= $this->member['nickname'];
			$data['headimg'] = $this->member['headimg'];
			$data['createtime'] = time();
			$data['content_pic'] = $content_pic;
			$data['ggid'] = $order['ggid'];
			$data['ggname'] = $order['ggname'];
			$data['status'] = ($yuyueset['comment_check']==1 ? 0 : 1);
			Db::name('yuyue_comment')->insert($data);
			Db::name('yuyue_order')->where('aid',aid)->where('mid',mid)->where('id',$oid)->update(['iscomment'=>1]);

			//如果不需要审核 增加产品评论数及评分
			if($yuyueset['comment_check']==0){
				$countnum = Db::name('yuyue_comment')->where('proid',$order['proid'])->where('status',1)->count();
				$score = Db::name('yuyue_comment')->where('proid',$order['proid'])->where('status',1)->avg('score'); //平均评分
				$haonum = Db::name('yuyue_comment')->where('proid',$order['proid'])->where('status',1)->where('score','>',3)->count(); //好评数
				if($countnum > 0){
					$haopercent = $haonum/$countnum*100;
				}else{
					$haopercent = 100;
				}
				Db::name('yuyue_product')->where('id',$order['proid'])->update(['comment_num'=>$countnum,'comment_score'=>$score,'comment_haopercent'=>$haopercent]);
			}
			return $this->json(['status'=>1,'msg'=>'评价成功']);
		}
		$rdata = [];
		$rdata['order'] = $order;
		$rdata['comment'] = $comment;
		return $this->json($rdata);
	}
	//评价服务人员
	public function commentps(){
		$id = input('param.id/d');
		$worker_order = Db::name('yuyue_worker_order')->where('id',$id)->where('mid',mid)->find();
		if(!$worker_order) return $this->json(['status'=>0,'msg'=>'未找到相关记录']);
		$comment = Db::name('yuyue_worker_comment')->where('orderid',$id)->where('aid',aid)->where('mid',mid)->find();
		if(request()->isPost()){
			if($comment){
				return $this->json(['status'=>0,'msg'=>'您已经评价过了']);
			}
			$content = input('post.content');
			$content_pic = input('post.content_pic');
			$score = input('post.score/d');
			if($score < 1){
				return $this->json(['status'=>0,'msg'=>'请打分']);
			}
			$data['aid'] = aid;
			$data['mid'] = mid;
			$data['bid'] = $worker_order['bid'];
			$data['worker_id'] = $worker_order['worker_id'];
			$data['orderid']= $worker_order['id'];
			$data['ordernum']= $worker_order['ordernum'];
			$data['score'] = $score;
			$data['content'] = $content;
			$data['content_pic'] = $content_pic;
			$data['nickname']= $this->member['nickname'];
			$data['headimg'] = $this->member['headimg'];
			$data['createtime'] = time();
			$data['status'] = 1;
			Db::name('yuyue_worker_comment')->insert($data);
			
			//如果不需要审核 增加配送员评论数及评分
			$countnum = Db::name('yuyue_worker_comment')->where('worker_id',$worker_order['worker_id'])->where('status',1)->count();
			$avgscore = Db::name('yuyue_worker_comment')->where('worker_id',$worker_order['worker_id'])->where('status',1)->avg('score'); //平均评分
			$haonum = Db::name('yuyue_worker_comment')->where('worker_id',$worker_order['worker_id'])->where('status',1)->where('score','>',3)->count(); //好评数
			if($countnum > 0){
				$haopercent = $haonum/$countnum*100;
			}else{
				$haopercent = 100;
			}
			Db::name('yuyue_worker')->where('id',$worker_order['worker_id'])->update(['comment_haopercent'=>$haopercent,'comment_score'=>$avgscore]);
			Db::name('yuyue_worker_order')->where('id',$worker_order['worker_id'])->update(['comment_num'=>$countnum,'comment_score'=>$score,'comment_haopercent'=>$haopercent]);

			return $this->json(['status'=>1,'msg'=>'评价成功']);
		}
		$rdata = [];
		$rdata['worker_order'] = $worker_order;
		$rdata['comment'] = $comment;
		return $this->json($rdata);
	}

	public function selectpeople(){
		$bid = input('param.bid');
		if(!$bid) $bid = 0;
		$type = input('param.type');
		$pernum = 10;
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		if($type=='list'){
			$where = [];
			$where[] = ['aid','=',aid];
            //多商户使用平台服务人员
            $bid = $bid;
            //用平台配送员
            if(getcustom('yuyue_plateform_peisonguser') && $bid>0){
                $bid = 0;
            }
			$where[] = ['bid','=',$bid];
			$where[] = Db::raw("`status`=1");

			$longitude = input('post.longitude');
			$latitude = input('post.latitude');
			if(getcustom('yuyue_apply') && $longitude && $latitude){
				$orderBy = Db::raw("({$longitude}-longitude)*({$longitude}-longitude) + ({$latitude}-latitude)*({$latitude}-latitude) ");
			}else{
				$orderBy = 'sort desc,id';
			}
			if(input('param.cid')){
						$where[] = ['cid','=',input('param.cid')];	
			}
			if(input('param.keyword')){
				$where[] = ['realname','like','%'.input('param.keyword').'%'];
			}
			$datalist = Db::name('yuyue_worker')->where($where)->page($pagenum,$pernum)->order($orderBy)->select()->toArray();
			foreach($datalist as &$d){
				$type =   Db::name('yuyue_worker_category')->where(['id'=>$d['cid']])->find();
				$d['typename'] = empty($type['name'])?'':$type['name'];
			}
		}else{
			$pro =explode(',',input('param.prodata'));
			$yydate = input('param.yydate');
			$product = Db::name('yuyue_product')->field('fwpeoid')->where('id',$pro[0])->find();
			$peoarr = explode(',',$product['fwpeoid']);
			$datalist = Db::name('yuyue_worker')->where('aid',aid)->where('status',1)->where('id','in',$peoarr)->order('sort desc,id')->select()->toArray();
			//查看该时间是否已经预约出去
			foreach($datalist as &$d){
				$type = Db::name('yuyue_worker_category')->where(['id'=>$d['cid']])->find();
				$d['typename'] = empty($type['name'])?'':$type['name'];
				$order = Db::name('yuyue_order')->where('aid',aid)->where('worker_id',$d['id'])->where('status','in','1,2')->where('yy_time',$yydate)->find();
				$d['yystatus']=1;
				if($order){
					$d['yystatus']=-1;
				}
			}
		}
		
		if(!$datalist) $datalist = [];
		return $this->json(['status'=>1,'data'=>$datalist]);
	}

	//人员分类
	public function peocategory(){
		$bid = input('param.bid');
		if(!$bid) $bid = 0;
		$clist = Db::name('yuyue_worker_category')->where('aid',aid)->where('bid',$bid)->where('pid',0)->where('status',1)->order('sort desc,id')->select()->toArray();
		return $this->json(['status'=>1,'data'=>$clist]);
	}

	//人员详情
	public function peodetail(){
		$id = input('param.id/d');
		$detail = Db::name('yuyue_worker')->where('id',$id)->where('aid',aid)->find();
		$type = Db::name('yuyue_worker_category')->where(['id'=>$detail['cid']])->find();
		$detail['typename'] = $type['name'];
		if(getcustom('yuyue_apply')){
			if($detail['sex']=='1') $detail['sex']='男';
			if($detail['sex']=='2') $detail['sex']='女';
		}
		//服务商品数量
		$detail['count'] = 0+Db::name('yuyue_product')->where('aid',aid)->where("find_in_set({$id},fwpeoid)")->count();
		$detail['showdesc'] = false;
		if(getcustom('yuyue_apply')){
			$detail['showdesc'] = true;
		}
        //获取设置
        $set = Db::name('yuyue_set')->field('ad_status,ad_pic,ad_link,video_status,video_tag,video_title')->where('aid',aid)->where('bid',0)->find();
        if($set['video_tag']) $set['video_tag'] = explode(',',$set['video_tag']);
        else $set['video_tag'] = [];
		if(!$detail) return $this->json(['status'=>0,'msg'=>'不存在']);
		return $this->json(['status'=>1,'data'=>$detail,'set'=>$set]);
	}
	public function getdlist(){
		$id = input('param.id/d');
		$type =	input('param.curTopIndex/d');
		$pernum = 10;
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
        $order = 'id desc';
		if($type==0){
			//服务商品
			$datalist = Db::name('yuyue_product')->where('aid',aid)->where("find_in_set({$id},fwpeoid)")->page($pagenum,$pernum)->order($order)->select()->toArray();
		}
		if($type==1){
			//评价列表
			$datalist = Db::name('yuyue_worker_comment')->where(['aid'=>aid,'worker_id'=>$id])->page($pagenum,$pernum)->order($order)->select()->toArray();
			foreach($datalist as $k=>$pl){
				$datalist[$k]['createtime'] = date('Y-m-d H:i',$pl['createtime']);
				if($datalist[$k]['content_pic']) $datalist[$k]['content_pic'] = explode(',',$datalist[$k]['content_pic']);
			}
		}
		if(!$datalist) $datalist = [];
		$datalist = $this->formatprolist($datalist);
		return $this->json(['status'=>1,'data'=>$datalist]);
	}

	public function isgetTime($params = []){
		$sort = 0;//排序用
		if(!$params){
			$date = input('param.date/t');
			if(strpos($date,'年') === false){
				$date = date('Y').'年'.$date;
			}
			$proid = input('param.proid/d');
			$key = input('param.key/d');
			$worker_id = input('?param.workerid')?input('param.workerid/d'):0;
		}else{
			$date = $params['date'];
			if(strpos($date,'年') === false){
				$date = date('Y').'年'.$date;
			}
			$proid = $params['proid'];
			$key   = $params['key']??0;
			$worker_id = $params['workerid']??0;
			$sort      = $params['sort']??0;
		}

		//获取设置
	    $product = Db::name('yuyue_product')->where('aid',aid)->where('id',$proid)->find();
	    $selmoretime = false;//是否是时间段多选模式
	    if(getcustom('yuyue_datetype1_model_selnum')){
            //判断是否是:时间段、模式2多段模式、且时间段起订量大于等于1，需要多选
            if(($product['rqtype']!=4) && $product['datetype'] == 1 && $product['datetype1_model'] == 1 && $product['datetype1_modelselnum'] >=1){
                $selmoretime = true;
            }
        }
		$sets = Db::name('yuyue_set')->where('aid',aid)->find();
		if($product['datetype']==1 && $product['rqtype']!=4 ){
			$timearr = [];
			$j=0;
			$nowdate =strtotime(date('H:i',time()))+$product['pdprehour']*60*60;

			$zaowanhours = [['zaohour'=>$product['zaohour'],'wanhour'=>$product['wanhour']]];//几个时间段
			if(getcustom('yuyue_datetype1_model')){
				//模式2多个时间段
				if($product['datetype1_model'] == 1){
					$zaowanhours = !empty($product['zaowanhours'])?json_decode($product['zaowanhours'],true):[];
				}
			}
			//循环多个非连续时间段
			foreach($zaowanhours as $zv){
				for($i=strtotime($zv['zaohour'].':00') ;$i<=strtotime($zv['wanhour'].':00') ; $i=$i+60*$product['timejg']){
					if(getcustom('yuyue_datetype1_model')){
						//模式2多个时间段 去重重复的
						if($product['datetype1_model'] == 1){
							if($i==strtotime($zv['wanhour'].':00')){
								break;
							}
						}
					}
					$j++;
					$time =strtotime(preg_replace(['/年|月/','/日/'],['-',''],$date.' '.date("H:i",$i)));
					$count = Db::name('yuyue_order')->where('aid',aid)->where('proid',$proid)->where('begintime','=',$time)->where('status','in','1,2')->count();
					$order=Db::name('yuyue_order')->where('aid',aid)->where('proid',$proid)->where('begintime','=',$time)->where('status','in','1,2')->find();
					if($count>=$product['yynum'] || $time<$nowdate){
						$timearr[$j]['status'] = 0;
					}else{
						$timearr[$j]['status'] = 1;
					}

                    if(getcustom('yuyue_selectpeople_inproduct')){
                        if($worker_id && $product['fwpeople']==1){
                            if(!$selmoretime){
                                //查看该服务人员该时间是否已经预约出去
                                $count = Db::name('yuyue_order')->where('worker_id',$worker_id)->where('aid',aid)->where('status','in','1,2')->where('yy_time',$time)->count('id');
                                if($count){
                                    $timearr[$j]['status'] = 0;
                                }
                            }else{
                                //查看该服务人员该时间是否已经预约出去
                                $worders = Db::name('yuyue_order')->where('worker_id',$worker_id)->where('aid',aid)->where('status','in','1,2')->field('yy_time,yy_times,yydates')->select()->toArray();
                                if($worders){
                                    foreach($worders as $ov){
                                        if($ov['yy_times']){
                                        	$yy_time  = $date.' '.date("H:i",$i);
                                            $yy_times = json_decode($ov['yy_times']);
                                            if(in_array($yy_time,$yy_times)){
                                                $timearr[$j]['status'] = 0;
                                                break;
                                            }
                                        }else if($time == $ov['yy_time']){
                                            $timearr[$j]['status'] = 0;
                                            break;
                                        }
                                    }
                                    unset($ov);
                                }
                                unset($yv);
                            }
                        }
                    }

					$jtime  = date("H:i",$i);
					$jtime2 = date("H:i",$i+$product['timejg']*60);
					if(getcustom('yuyue_selecttime_with_stock')){
						if($product['showdatetype']==1){
							$timearr[$j]['time'] = $jtime.'~'.$jtime2;
						}
						$timearr[$j]['stock'] = $product['yynum']-$count;
						if($time<$nowdate){
							$timearr[$j]['stock'] = 0;
						}
					}
					$timearr[$j]['time']    = $jtime;
					$timearr[$j]['timeint'] = str_replace(':','',$jtime);

					$timearr[$j]['time2']      = $jtime2;
					$timearr[$j]['timerange']  = $jtime.'-'.$timearr[$j]['time2'];
					$timearr[$j]['issel']      = false;
					$timearr[$j]['sort']       = $sort;
					$sort++;
				 }
			}
			unset($zv);
		}else if($product['datetype']==2 && $product['rqtype']!=4  ){
			$timearr = [];
			$timearrs = explode(',',$product['timepoint']); 
			$nowdate =strtotime(date('H:i',time()))+$product['pdprehour']*60*60;	
			foreach($timearrs as $k=>$t){
				$time =strtotime(preg_replace(['/年|月/','/日/'],['-',''],$date.' '.$t));
				$count = Db::name('yuyue_order')->where('aid',aid)->where('proid',$proid)->where('begintime','=',$time)->where('status','in','1,2')->count();
				if($count>=$product['yynum'] || $time<$nowdate){
						$timearr[$k]['status'] = 0;
				}else{
						$timearr[$k]['status'] = 1;
				}
				if(getcustom('yuyue_selectpeople_inproduct')){
                    if($worker_id && $product['fwpeople']==1){
                        //查看该服务人员该时间是否已经预约出去
                        $count = Db::name('yuyue_order')->where('worker_id',$worker_id)->where('aid',aid)->where('status','in','1,2')->where('yy_time',$time)->count('id');
                        if($count){
                            $timearr[$j]['status'] = 0;
                        }
                    }
                }
				$timearr[$k]['time'] = $t;
				$timearr[$k]['timeint'] = str_replace(':','',$t);
			}
		}elseif($product['rqtype']==4){
			$timearr = [];
			$j=0;
			$timedata = json_decode($product['selftimedata'],true);
			$nowdate =strtotime(date('H:i',time()))+$product['pdprehour']*60*60;
			foreach($timedata as $k=>$v){
				if($v['day']==$key){
					$j++;
					$thisval = ($v['hour']<=9?'0'.$v['hour']:$v['hour']).':'.($v['minute']<=9?'0'.$v['minute']:$v['minute']);
					$thisval2 = ($v['hour2']<=9?'0'.$v['hour2']:$v['hour2']).':'.($v['minute2']<=9?'0'.$v['minute2']:$v['minute2']);
					$times = $thisval.'~'.$thisval2;
					$time =strtotime(preg_replace(['/年|月/','/日/'],['-',''],$date.' '.$thisval));
					//var_dump(date('Y-m-d H:i:s',$time));
					$count = Db::name('yuyue_order')->where('aid',aid)->where('proid',$proid)->where('begintime','=',$time)->where('status','in','1,2')->count();
					$timearr[$j]['stock'] = $v['stock']-$count;
					if($count>=$v['stock'] || $time<$nowdate){
						$timearr[$j]['status'] = 0;
						$timearr[$j]['stock'] = 0;
					}else{
						$timearr[$j]['status'] = 1;
					}
					$timearr[$j]['time'] = $times;
					$timearr[$j]['timeint'] = str_replace(':','',$time);
				}
			}
		}
		if(!$params){
			return $this->json(['status'=>1,'data'=>$timearr]);	
		}else{
			return ['times'=>$timearr,'sort'=>$sort];
		}
		
	}
	public function getyytime($yydate,$proid){
		$yydate = explode('-',$yydate);
		//开始时间
		$begindate = date('Y年').$yydate[0];
		$begindate = preg_replace(['/年|月/','/日/'],['-',''],$begindate);
		$begintime = strtotime($begindate);
		$ends = explode(' ',$yydate[0]);

		$where[] = ['begintime','=',$begintime];
		$count = 0 + Db::name('yuyue_order')->where($where)->where('aid',aid)->where('status','in','1,2')->where('proid',$proid)->count();
		return $count;
	}

	//申请  0616
	public function apply(){
		$set = Db::name('yuyue_set')->field('apply_paymoney,xieyi_show,xieyi,apply_url')->where('aid',aid)->find();
		if(input('param.bid')){
			$bid = input('param.bid/d');
		}else{
			$bid = 0;
		}
		if(request()->isPost()){
			$formdata = input('post.info/a');
			//查看是否已经存在此账号
			$worker = Db::name('yuyue_worker')->where('aid',aid)->where('bid',$bid)->where('un',$formdata['un'])->find();
			if($worker['mid'] && $worker['mid']!=mid){
				return $this->json(['status'=>0,'msg'=>'此登录账号已存在']);	
			}
			if(!$formdata['realname'] || !$formdata['tel'] || !$formdata['un']){
				return $this->json(['status'=>0,'msg'=>'请将信息填写完整']);	
			}
			$info = [];
			$info['aid'] = aid;
			$info['mid'] = mid;
			$info['bid'] = $formdata['bid']?$formdata['bid']:0;
			$info['cid'] = $formdata['cid'];
			$info['realname'] = $formdata['realname'];
			$info['tel'] = $formdata['tel'];
			$info['age'] = $formdata['age'];
			$info['sex'] = $formdata['sex'];
			$info['tel'] = $formdata['tel'];
			$info['citys'] = $formdata['citys'];
			$info['latitude'] = $formdata['latitude'];
			$info['longitude'] = $formdata['longitude'];
			$info['fuwu_juli'] = $formdata['fuwu_juli'];
			$info['codepic'] = $formdata['codepic'];
			$info['otherpic'] = $formdata['otherpic'];
			$info['headimg'] = $formdata['headimg'];
			$info['un'] = $formdata['un'];
			$info['desc'] = $formdata['desc'];
			$info['apply_paymoney'] = $set['apply_paymoney']?$set['apply_paymoney']:0;
			if($formdata['pwd']!='') $info['pwd'] = md5($info['pwd']);
			$info['status'] = 0;
			$info['shstatus'] = 0;
			$info['createtime'] = time();
			$ordernum = \app\commons\Common::generateOrderNo(aid);
			$info['ordernum'] = $ordernum;
			$info['fwcids'] = implode(',',$formdata['fwcids']);
			if($formdata['id']){
				Db::name('yuyue_worker')->where('aid',aid)->where('bid',$info['bid'])->where('id',$formdata['id'])->update($info);
			}else{
				$worker_id = Db::name('yuyue_worker')->insertGetId($info);
			}

			if($set['apply_paymoney']>0){  //创建支付订单
				$orderdata = [];
				$orderdata['aid'] = aid;
				$orderdata['mid'] = mid;
				$orderdata['bid'] = $bid;
				$orderdata['worker_id'] = $worker_id;
				$orderdata['ordernum'] = $ordernum;
				$orderdata['createtime'] = time();
				$orderdata['status'] = 0;
				$orderdata['price'] = $info['apply_paymoney'];
				$orderdata['title'] = $formdata['realname'].'师傅申请';
				$order_id = Db::name('yuyue_workerapply_order')->insertGetId($orderdata);
				$payorderid = \app\models\Payorder::createorder(aid,$info['bid'],mid,'yuyue_workerapply',$order_id,$ordernum,'预约师傅申请',$info['apply_paymoney'],0);
		
				return $this->json(['status'=>1,'payorderid'=>$payorderid,'msg'=>'提交成功']);
			}else{
				
				//入驻成功给管理员发通知
				$tmplcontent = [];
				$tmplcontent['first'] = '有师傅申请成功';
				$tmplcontent['remark'] = '请登录后台，查看申请详情~';
				$tmplcontent['keyword1'] = '预约师傅申请';
				$tmplcontent['keyword2'] = date('Y-m-d H:i');
                $tempconNew = [];
                $tempconNew['thing3'] = '预约师傅申请';//报名名称
                $tempconNew['time5'] = date('Y-m-d H:i');//申请时间
				\app\commons\Wechat::sendhttmpl(aid,$formdata['bid'],'tmpl_formsub',$tmplcontent,'',0,$tempconNew);
				return $this->json(['status'=>1,'msg'=>'提交成功,请等待审核','tourl'=>$set['apply_url']?$set['apply_url']:'apply']);
			}
		}
		if(mid>0){
			$info = Db::name('yuyue_worker')->where('aid',aid)->where('mid',mid)->find();
			if($info && $info['shstatus']==1){
				//var_dump($info);
				return $this->json(['status'=>2,'msg'=>'您已成功入驻']); 
			}
		}else{
			return $this->json(['status'=>-1,'msg'=>'请先登录']); 
		}

		$clist = Db::name('yuyue_worker_category')->where('aid',aid)->where('status',1)->where('bid',$bid)->order('sort desc,id')->select()->toArray();
		//商家
		$blist1 = [['id'=>'0','name'=>'平台自营']];
		$blist = Db::name('business')->where('aid',aid)->where('status',1)->order('sort desc,id')->select()->toArray();
		$blist = array_merge($blist1,$blist);
		//查看订单表
	 	$order = Db::name('yuyue_workerapply_order')->where(['aid'=>aid,'bid'=>$bid,'mid'=>mid])->order('id desc')->find();

		$fwcateArr = Db::name('yuyue_category')->Field('id,name')->where('aid',aid)->column('name','id');
		$busarr = Db::name('business')->Field('id,name')->where('aid',aid)->column('name','id');

		$isapply2=false;
		if(getcustom('yuyue_apply_refund')){
			$isapply2=true;
		}
		$hide_city=false;
		if(getcustom('yuyue_fuwutype_text')){
			$hide_city=true;
		}
		$rdata = []; 
        $rdata['title'] = '申请入驻';
		$rdata['clist'] = $clist;
		$rdata['set'] = $set;
		$rdata['blist'] = $blist;
		$rdata['fwcateArr'] = $fwcateArr;
		$rdata['order'] = $order?$order:[];
		if(!$info) $info=array('id'=>'');
		$rdata['info'] = $info;
		$rdata['fwcids'] = $info['fwcids'] ? explode(',',$info['fwcids']) : [];
		$rdata['busarr'] = $busarr ? $busarr : [];
		$rdata['isapply2'] = $isapply2;
		$rdata['hide_city'] = $hide_city;
		return $this->json($rdata);
	}
	//获取接下来一周的日期
    function GetWeeks($yyzhouqi) {
		//查看今天周几
		$zqarr = explode(',',$yyzhouqi);
        $weeks=[];
		$week = ['1'=>'周一','2'=>'周二','3'=>'周三','4'=>'周四','5'=>'周五','6'=>'周六','0'=>'周日'];
        for ($i=0;$i<7;$i++){
			$year=date('Y',time()+86400*$i).'年';
            $month=date('m',time()+86400*$i).'月';
            $day=date('d',time()+86400*$i);
            $weeknum=date('w',time()+86400*$i);
			$weeks[$i]['key'] = $weeknum;
			$weeks[$i]['weeks'] = $week[$weeknum];
			$weeks[$i]['date'] = $month.$day;
			$weeks[$i]['year'] = $year;
            //array_push($weeks,$month.$day."(".$week."）");
			$newweek = [];
			foreach($weeks as $k=>$w){
				if(!in_array($w['key'],$zqarr)){
					unset($weeks[$k]);
				}
			}
        }
	    $weeks=array_values($weeks);
        return $weeks;
    }
    function GetWeeks2($yybeigntime,$yyendtime) {
		$datelist = [];
		$days= ($yyendtime-$yybeigntime)/86400;
		$week = ['1'=>'周一','2'=>'周二','3'=>'周三','4'=>'周四','5'=>'周五','6'=>'周六','0'=>'周日'];
		for ($i=0;$i<=$days;$i++){
			$year=date('Y',$yybeigntime+86400*$i).'年';
			$month=date('m',$yybeigntime+86400*$i).'月';
			$day=date('d',$yybeigntime+86400*$i);
			$weeknum=date('w',$yybeigntime+86400*$i);
			$datelist[$i]['key'] = $weeknum;
			$datelist[$i]['weeks'] = $week[$weeknum];
			$datelist[$i]['date'] = $month.$day;
			$datelist[$i]['year'] = $year;
		}
	   return $datelist;
	}
    function GetWeeks3($timedata) {
		$newweek = [];
		foreach($timedata as $k=>$t){
			if($t['day']==1 && !in_array(1,$newweek))  $newweek[]=1;
			if($t['day']==2 && !in_array(2,$newweek))  $newweek[]=2;
			if($t['day']==3 && !in_array(3,$newweek))  $newweek[]=3;
			if($t['day']==4 && !in_array(4,$newweek))  $newweek[]=4;
			if($t['day']==5 && !in_array(5,$newweek))  $newweek[]=5;
			if($t['day']==6 && !in_array(6,$newweek))  $newweek[]=6;
			if($t['day']==7 && !in_array(7,$newweek))  $newweek[]=0;
		}

        $weeks=[];
		$week = ['1'=>'周一','2'=>'周二','3'=>'周三','4'=>'周四','5'=>'周五','6'=>'周六','0'=>'周日'];
        for ($i=0;$i<7;$i++){
			$year=date('Y',time()+86400*$i).'年';
            $month=date('m',time()+86400*$i).'月';
            $day=date('d',time()+86400*$i);
            $weeknum=date('w',time()+86400*$i);
			$weeks[$i]['key'] = $weeknum;
			$weeks[$i]['weeks'] = $week[$weeknum];
			$weeks[$i]['date'] = $month.$day;
			$weeks[$i]['year'] = $year;
            //array_push($weeks,$month.$day."(".$week."）");
			foreach($weeks as $k=>$w){
				if(!in_array($w['key'],$newweek)){
					unset($weeks[$k]);
				}
			}
        }
		$weeks=array_values($weeks);
	    return $weeks;
	}

    public function selectbusiness(){
        if(getcustom('yuyue_gobusiness')){
            $pernum = 10;
            $pagenum = input('post.pagenum');
            if(!$pagenum) $pagenum = 1;
            $datalist = [];
            $pro = explode(',',input('param.prodata'));
            $product  = Db::name('yuyue_product')->where('id',$pro[0])->field('gobids')->find();
            if($product && $product['gobids']){
                $datalist = Db::name('business')->where('id','in',$product['gobids'])->where('status',1)->where('aid',aid)->order('sort desc,id')->field('id,name,logo,province,city,district,address')->select()->toArray();
                if($datalist){
                	foreach($datalist as  &$dv){
                		$dv['address'] = $dv['province'].$dv['city'].$dv['district'].$dv['address'];
                	}
                }
            }
            return $this->json(['status'=>1,'data'=>$datalist]);
        }
    }
}