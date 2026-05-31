<?php


namespace app\controllers;
use think\facade\Db;
class ApiSeckill extends ApiCommon
{
	//秒杀
	function index(){
		$set = Db::name('seckill_sysset')->where('aid',aid)->find();
		if(!$set || $set['status'] ==0 || $set['timeset']==='' || $set['timeset']===null){
			return $this->json(['status'=>0,'msg'=>'未开启秒杀活动']);
		}
		$timeset = explode(',',$set['timeset']);
		$duration = $set['duration'];
		//展示过去24小时之内的 和未来12小时之内的
		$times = [];
		$nowtime = time();
		foreach($timeset as $time){
			$showtime = ($time<10?'0':'').$time.':00';
			$seckill_date = date('Y-m-d',$nowtime-86400);
            $seckill_date_m = date('m-d',$nowtime-86400);
			$datetime = $seckill_date. ' ' . $showtime;
			$timestamp = strtotime($datetime);
			$times[] = ['datetime'=>$datetime,'timestamp'=>$timestamp,'showtime'=>$showtime,'seckill_time'=>$time,'seckill_date'=>$seckill_date,'seckill_date_m'=>$seckill_date_m,'active'=>0];
		}
		foreach($timeset as $time){
			$showtime = ($time<10?'0':'').$time.':00';
			$seckill_date = date('Y-m-d',$nowtime);
            $seckill_date_m = date('m-d',$nowtime);
			$datetime = $seckill_date. ' ' . $showtime;
			$timestamp = strtotime($datetime);
			$times[] = ['datetime'=>$datetime,'timestamp'=>$timestamp,'showtime'=>$showtime,'seckill_time'=>$time,'seckill_date'=>$seckill_date,'seckill_date_m'=>$seckill_date_m,'active'=>0];
		}
		foreach($timeset as $time){
			$showtime = ($time<10?'0':'').$time.':00';
			$seckill_date = date('Y-m-d',$nowtime+86400);
            $seckill_date_m = date('m-d',$nowtime+86400);
			$datetime = $seckill_date. ' ' . $showtime;
			$timestamp = strtotime($datetime);
			$times[] = ['datetime'=>$datetime,'timestamp'=>$timestamp,'showtime'=>$showtime,'seckill_time'=>$time,'seckill_date'=>$seckill_date,'seckill_date_m'=>$seckill_date_m,'active'=>0];
		}
		$newtimes = [];
		$activetime = [];
		$nexttime = [];
		$nextindex = 0;
		$selected = 0;
		$index = 0;
		foreach($times as $k=>$time){
			if($time['timestamp'] >= $nowtime-86400 && $time['timestamp']<= $nowtime +43200){
				if($k>0 && $k<count($times)-1 && $time['timestamp']<=$nowtime && $times[$k+1]['timestamp']>$nowtime){
					$activetime = $time;
					$time['active'] = 1;
					$selected = $index;
				}elseif($time['timestamp']<$nowtime){
					if($time['timestamp'] + $duration*3600 < $nowtime){
						$time['active'] = -1;
					}else{
						$time['active'] = 0;
					}
				}elseif($time['timestamp']>$nowtime){
					$time['active'] = 2;
					if(!$nexttime){
						$nexttime = $time;
						$nextindex = $index;
					}
				}
				if($time['timestamp'] <$nowtime && $duration*3600+$time['timestamp']*1 > $nowtime){
					$time['selling'] = 1;//抢购中
				}elseif($time['timestamp']*1+$duration*3600 < $nowtime){
					$time['selling'] = 0;//已结束
				}else{
					$time['selling'] = 2;//未开始
				}
				$newtimes[] = $time;
				$index++;
			}
		}
		if($selected ==0 && $nexttime){
			$selected = $nextindex;
			$activetime = $nexttime;
		}
		//dump($newtimes);die;
		$rdata = [];
		$rdata['status'] = 1;
		$rdata['selected'] = $selected;
		$rdata['navlist'] = $newtimes;
		$rdata['activetime'] = $activetime;
		$rdata['seckill_duration'] = $duration;
		$rdata['nowtime'] = $nowtime;
		$rdata['set'] = ['indextext'=>$set['indextext'],'indexpic'=>$set['indexpic'],'indexprice'=>$set['indexprice']];
		
		return $this->json($rdata);
	}
	function getprolist(){
		$post = input('post.');
		$pagenum = $post['pagenum'] ? $post['pagenum'] : 1;
		$seckill_date = $post['seckill_date'];
		$seckill_time = $post['seckill_time'];
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['status','=',1];
		$where[] = ['ischecked','=',1];
		$where[] = ['seckill_date','=',$seckill_date];
		$where[] = ['seckill_time','=',$seckill_time];
		$business_sysset = Db::name('business_sysset')->where('aid',aid)->find();
		if(input('param.bid')){
			$where[] = ['bid','=',input('param.bid')];
		}elseif(!$business_sysset || $business_sysset['product_isshow']==0){
			$where[] = ['bid','=',0];
		}
		$field = 'id,name,pic,market_price,sell_price,seckill_date,lvprice,lvprice_data,sellpoint,fuwupoint,guigedata,status,seckill_time,starttime,stock,sales';
		$data = Db::name('seckill_product')->field($field)->where($where)->page($pagenum,20)->order('sort desc')->select()->toArray();
		
		$data = $this->formatprolist($data);
		if(!$data) $data = [];
		foreach($data as $k=>$v){

			//判断商品设置的时间与现在时间对比
			$post_timestr = strtotime($v['seckill_date'].' '.$v['seckill_time'].":00:00");
			if($post_timestr>time()){
				$data[$k]['salepercent'] = 0;
				$data[$k]['sales'] = 0;
			}else{
				$data[$k]['salepercent'] = intval($v['sales']/($v['sales']+$v['stock']) * 10000)/100;
			}

			}
		return $this->json(['status'=>1,'data'=>$data]);
	}

	//商品
	public function product(){
		$proid = input('param.id/d');
		$product = Db::name('seckill_product')->where('id',$proid)->where('aid',aid)->find();
		if(!$product) return $this->json(['status'=>0,'msg'=>'商品不存在']);
		if($product['status']==0) return $this->json(['status'=>0,'msg'=>'商品未上架']);
		if($product['ischecked']!=1) return $this->json(['status'=>0,'msg'=>'商品未审核']);
        $product['givescore'] = dd_money_format($product['givescore'],$this->score_weishu);
		if(!$product['pics']) $product['pics'] = $product['pic'];
		$product['pics'] = explode(',',$product['pics']);
		if($product['fuwupoint']){
			$product['fuwupoint'] = explode(' ',preg_replace("/\s+/",' ',str_replace('　',' ',trim($product['fuwupoint']))));
		}
		//判断商品设置的时间与现在时间对比
		$post_timestr = strtotime($product['seckill_date'].' '.$product['seckill_time'].":00:00");
		if($post_timestr>time()){
			$product['sales'] = 0;
		}

		$product = $this->formatproduct($product);

		if($product['video']){
			$minute = floor($product['video_duration']/60);
			if($minute < 10) $minute = '0'.$minute;
			$second = $product['video_duration']%60;
			if($second < 10) $second = '0'.$second;
			$product['video_duration'] = $minute . "'" . $second . '"';
		}

		//是否收藏
		$rs = Db::name('member_favorite')->where('aid',aid)->where('mid',mid)->where('proid',$proid)->where('type','seckill')->find();
		if($rs){
			$isfavorite = true;
		}else{
			$isfavorite = false;
		}
		//获取评论
		$commentlist = Db::name('seckill_comment')->where('aid',aid)->where('proid',$proid)->where('status',1)->order('id desc')->limit(10)->select()->toArray();
		if(!$commentlist) $commentlist = [];
		foreach($commentlist as $k=>$pl){
			$commentlist[$k]['createtime'] = date('Y-m-d H:i',$pl['createtime']);
			if($commentlist[$k]['content_pic']) $commentlist[$k]['content_pic'] = explode(',',$commentlist[$k]['content_pic']);
		}
		$commentcount = Db::name('seckill_comment')->where('aid',aid)->where('proid',$proid)->where('status',1)->count();
		//添加浏览历史
		if(mid){
			$rs = Db::name('member_history')->where('aid',aid)->where('mid',mid)->where('proid',$proid)->where('type','seckill')->find();
			if($rs){
				Db::name('member_history')->where('id',$rs['id'])->update(['createtime'=>time()]);
			}else{
				Db::name('member_history')->insert(['aid'=>aid,'mid'=>mid,'proid'=>$proid,'type'=>'seckill','createtime'=>time()]);
			}
		}

		$sysset = Db::name('admin_set')->where('aid',aid)->field('name,logo,desc,fxjiesuantype,tel,kfurl,gzts,ddbb')->find();
		$seckillset = Db::name('seckill_sysset')->where('aid',aid)->field('showjd,comment,showcommission,duration')->find();
		if($this->member){
			$userlevel = Db::name('member_level')->where('aid',aid)->where('id',$this->member['levelid'])->find();
		}
		//预计佣金
		$commission = 0;
		if($this->member && $seckillset['showcommission']==1 && $product['commissionset']!=-1){
			if($userlevel['can_agent']!=0){
				if($product['commissionset']==1){//按比例
					$commissiondata = json_decode($product['commissiondata1'],true);
					if($commissiondata){
						$commission = $commissiondata[$userlevel['id']]['commission1'] * ($product['sell_price'] - ($sysset['fxjiesuantype']==2 ? $product['cost_price'] : 0)) * 0.01;
					}
				}elseif($product['commissionset']==2){//按固定金额
					$commissiondata = json_decode($product['commissiondata2'],true);
					if($commissiondata){
						$commission = $commissiondata[$userlevel['id']]['commission1'];
					}
				}else{
				    //fxjiesuantype 0按商品价格,1按成交价格,2按销售利润
					if($userlevel['commissiontype']==1){ //固定金额按单
						$commission = $userlevel['commission1'];
					}else{
						$commission = $userlevel['commission1'] * ($product['sell_price'] - ($sysset['fxjiesuantype']==2 ? $product['cost_price'] : 0)) * 0.01;
					}
				}
			}
		}
		$product['commission'] = round($commission*100)/100;
		unset($product['cost_price']);

		if($product['bid']!=0){
			$business = Db::name('business')->where('aid',aid)->where('id',$product['bid'])->field('id,name,logo,desc,tel,address,sales,kfurl')->find();
		}else{
			$business = $sysset;
		}
		$product['detail'] = \app\commons\System::initpagecontent($product['detail'],aid,mid,platform);
        $product['comment_starnum'] = floor($product['comment_score']);
		
		
		if($this->member && $product['lvprice']==0 && $userlevel['discount'] > 0 && $userlevel['discount'] < 10){
			$product['discount_tips'] = ' '.$userlevel['name'].' 享'.floatval($userlevel['discount']).'折优惠';
		}else{
			$product['discount_tips'] = '';
		}

		//关注提示
		$sysset['showgzts'] = false;
		if(platform == 'mp'){
			$sysset['gzts'] = explode(',',$sysset['gzts']);
			if(in_array('2',$sysset['gzts']) && $this->member['subscribe']==0){
				$appinfo = \app\commons\System::appinfo(aid,'mp');
				$sysset['qrcode'] = $appinfo['qrcode'];
				$sysset['gzhname'] = $appinfo['nickname'];
				$sysset['showgzts'] = true;
			}
		}
		//订单播报
		$bboglist = [];
		$sysset['ddbb'] = explode(',',$sysset['ddbb']);
		if(in_array('2',$sysset['ddbb'])){
			$bboglist = Db::name('seckill_order')
				->field('mid,proname name,createtime,proid')
				->where('aid',aid)
				->where('proid',$product['id'])
				->where('status','in','0,1,2,3')
				->where('createtime','>',time()-86400*10)
				->order('createtime desc')->limit(10)->select()->toArray();
			if(!$bboglist) $bboglist = [];
			foreach($bboglist as $k=>$og){
				$ogmember = Db::name('member')->where('id',$og['mid'])->find();
				if(!$ogmember){
					unset($bboglist[$k]);
					continue;
				}else{
					$bboglist[$k]['nickname'] = $ogmember['nickname'];
					$bboglist[$k]['headimg'] = $ogmember['headimg'];
				}
				if(time() - $og['createtime'] < 60*5){
					$bboglist[$k]['showtime'] = '刚刚';
				}elseif(date('Ymd')==date('Ymd',$og['createtime'])){
					if($og['createtime'] + 3600 > time()){
						$bboglist[$k]['showtime'] = floor((time()-$og['createtime'])/60).'分钟前';
					}else{
						$bboglist[$k]['showtime'] = floor((time()-$og['createtime'])/3600).'小时前';
					}
				}elseif(time()-$og['createtime']<86400){
					$bboglist[$k]['showtime'] = '昨天';
				}elseif(time()-$og['createtime']<2*86400){
					$bboglist[$k]['showtime'] = '前天';
				}else{
					$bboglist[$k]['showtime'] = '三天前';
				}
			}
		}
		/*
		//促销活动
		$cuxiaolist = Db::name('cuxiao')
			->where('aid',aid)
			->where('bid',$product['bid'])
			->where('starttime','<',time())
			->where('endtime','>',time())
			->order('sort desc')->select()->toArray();
		$newcxlist = [];
		foreach($cuxiaolist as $k=>$v){
			$gettj = explode(',',$v['gettj']);
			if(!in_array('-1',$gettj) && !in_array($this->member['levelid'],$gettj)){ //不是所有人
				continue;
			}
			if($v['fwtype']==2){//指定商品可用
				$productids = explode(',',$v['productids']);
				if(!in_array($product['id'],$productids)){
					continue;
				}
			}
			if($v['fwtype']==1){//指定类目可用
				$categoryids = explode(',',$v['categoryids']);
				$cids = [];
				$cids[] = $product['cid'];
				$clist = Db::name('seckill_category')->where('id','in',$cids)->select()->toArray();
				foreach($clist as $kc=>$vc){
					if($vc['pid']){
						$cids[] = $vc['pid'];
						$cate2 = Db::name('seckill_category')->where('id',$vc['pid'])->find();
						if($cate2 && $cate2['pid']){
							$cids[] = $cate2['pid'];
						}
					}
				}
				if(!array_intersect($cids,$categoryids)){
					continue;
				}
			}
			$newcxlist[] = $v;
		}
		*/
		//优惠券
		$couponlist = Db::name('coupon')->where('aid',aid)->where('bid',$product['bid'])->where('isgive','<>',2)->where('tolist',1)->where('type','in','1,4')->where("unix_timestamp(starttime)<=".time()." and unix_timestamp(endtime)>=".time())->order('sort desc')->select()->toArray();
		$newcplist = [];
		foreach($couponlist as $k=>$v){
			$gettj = explode(',',$v['gettj']);
			if(!in_array('-1',$gettj) && !in_array($this->member['levelid'],$gettj)){ //不是所有人
				continue;
			}
			if($v['fwtype']!==0){//全场
				continue;
			}
            if($v['fwscene']!==0){//适用场景
                continue;
            }
			$haveget = Db::name('coupon_record')->where('aid',aid)->where('mid',mid)->where('couponid',$v['id'])->count();
			$v['haveget'] = $haveget;
			$v['starttime'] = date('m-d H:i',strtotime($v['starttime']));
			$v['endtime'] = date('m-d H:i',strtotime($v['endtime']));
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
				$binfo = Db::name('business')->where('aid',aid)->where('id',$v['bid'])->find();
				$datalist[$k]['bname'] = $binfo['name'];
			}
			$newcplist[] = $v;
		}
		if($product['bid']!=0){
			$business = Db::name('business')->where('aid',aid)->where('id',$product['bid'])->field('id,name,logo,desc,tel,address,sales,kfurl')->find();
		}else{
			$business = $sysset;
		}
		$price_dollar = false;
		$rdata = [];
		$rdata['status'] = 1;
		$rdata['title'] = $product['name'];
		$rdata['sysset'] = $sysset;
		$rdata['seckillset'] = $seckillset;
		$rdata['isfavorite'] = $isfavorite;
		$rdata['product'] = $product;
		$rdata['business'] = $business;
		$rdata['commentlist'] = $commentlist;
		$rdata['commentcount'] = $commentcount;
		$rdata['bboglist'] = $bboglist;
		$rdata['cuxiaolist'] = [];//$newcxlist;
		$rdata['couponlist'] = $newcplist;
		$rdata['seckill_duration'] = $seckillset['duration'];
		$rdata['nowtime'] = time();
		$rdata['showprice_dollar'] = $price_dollar;
		return $this->json($rdata);
	}
	//获取商品详情
	public function getproductdetail(){
		$proid = input('param.id/d');
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['id','=',$proid];
		$product = Db::name('seckill_product')->field("pic,id,name,sales,market_price,sell_price,lvprice,lvprice_data,sellpoint,fuwupoint,guigedata,status,ischecked,freighttype")->where($where)->find();
		if(!$product){
			return $this->json(['status'=>0,'msg'=>'商品不存在']);
		}
		$product = $this->formatproduct($product);
		if($product['status']==0) return $this->json(['status'=>0,'msg'=>'商品已下架']);
		if($product['ischecked']!=1) return $this->json(['status'=>0,'msg'=>'商品未审核']);
		$gglist = Db::name('seckill_guige')->where('proid',$product['id'])->select()->toArray();
		$gglist = $this->formatgglist($gglist,$product['bid'],$product['lvprice']);


		$product['price_dollar'] =false;
		$guigelist = array();
		foreach($gglist as $k=>$v){
			if($product['price_dollar'] && $shopset['usdrate']>0){
				$v['usdsell_price'] = round($v['sell_price']/$shopset['usdrate'],2);
			}
			$guigelist[$v['ks']] = $v;
		}
		$guigedata = json_decode($product['guigedata'],true);
		$ggselected = [];
		foreach($guigedata as $v) {
			$ggselected[] = 0;
		}
		$ks = implode(',',$ggselected);
		return $this->json(['status'=>1,'product'=>$product,'guigelist'=>$guigelist,'guigedata'=>$guigedata,'ggselected'=>$ggselected,'ks'=>$ks]);
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
		$datalist = Db::name('seckill_comment')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
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

	public function buy(){
		$this->checklogin();
		if(input('param.prodata')){
			$prodata = explode(',',input('param.prodata'));
			$proid = $prodata[0];
			$ggid = $prodata[1];
			$num = $prodata[2];
		}else{
			$proid = input('param.proid/d');
			$ggid = input('param.ggid/d');
			$num = input('param.num/d');
		}
		if(!$num) $num = 1;
        $product_price = 0;
        $weight = 0;//重量

		$product = Db::name('seckill_product')->where('aid',aid)->where('status',1)->where('ischecked',1)->where('id',$proid)->find();
		if(!$product){
			return $this->json(['status'=>0,'msg'=>'产品不存在或已下架']);
		}
		$guige = Db::name('seckill_guige')->where('id',$ggid)->find();
        $guige = $this->formatguige($guige, $product['bid'],$product['lvprice']);
		if(!$guige){
			return $this->json(['status'=>0,'msg'=>'产品该规格不存在或已下架']);
		}
		$seckillset = db('seckill_sysset')->where(['aid'=>aid])->find();
		$seckill = [];
		if($seckillset['status']!=1) return $this->json(['status'=>0,'msg'=>'秒杀活动未开启']);
		$duration = $seckillset['duration'];
		if($product['starttime'] > time()){
			return $this->json(['status'=>0,'msg'=>'秒杀未开始']);
		}
		if($product['starttime'] < time() - 3600*$duration){
			return $this->json(['status'=>0,'msg'=>'秒杀已结束']);
		}
		$gettj = explode(',',$product['gettj']);
		if(!in_array('-1',$gettj) && !in_array($this->member['levelid'],$gettj) && (!in_array('0',$gettj) || $this->member['subscribe']!=1)){ //不是所有人
			if(!$product['gettjtip']) $product['gettjtip'] = '没有权限购买该商品';
			if($product['gettjurl']){
				return $this->json(['status'=>-4,'msg'=>$product['gettjtip'],'url'=>$product['gettjurl']]);
			}else{
				return $this->json(['status'=>0,'msg'=>$product['gettjtip']]);
			}
		}
        if($product['perlimit'] > 0){
            $buynum = $num + Db::name('seckill_order')->where('aid',aid)->where('mid',mid)->where('proid',$product['id'])->where('status','in','0,1,2,3')->sum('num');
            if($buynum > $product['perlimit']){
                return $this->json(['status'=>0,'msg'=>'每人限购'.$product['perlimit'].'件']);
            }
        }

        if($guige['stock'] < $num){
			return $this->json(['status'=>0,'msg'=>'下手慢了']);
		}
		$bid = $product['bid'];
		if($bid!=0){
			$business = Db::name('business')->where('id',$bid)->field('id,aid,cid,name,logo,tel,address,sales,longitude,latitude')->find();
		}else{
			$business = Db::name('admin_set')->where('aid',aid)->field('id,name,logo,desc,tel')->find();
		}

        $product_price += $guige['sell_price'] * $num;
        $weight += $guige['weight'] * $num;

		if($product['freighttype']==0){
			$fids = explode(',',$product['freightdata']);
			$freightList = \app\models\Freight::getList([['status','=',1],['aid','=',aid],['bid','=',$bid],['id','in',$fids]]);
		}elseif($product['freighttype']==3 || $product['freighttype']==4){
			$freightList = [['id'=>0,'name'=>($product['freighttype']==3?'自动发货':'在线卡密'),'pstype'=>$product['freighttype']]];
		}else{
			$freightList = \app\models\Freight::getList([['status','=',1],['aid','=',aid],['bid','=',$bid]]);
		}
		
		$havetongcheng = 0;
		foreach($freightList as $k=>$v){
			if($v['pstype']==2){ //同城配送
				$havetongcheng = 1;
			}
		}
		if($havetongcheng){
			$address = Db::name('member_address')->where('aid',aid)->where('mid',mid)->where('latitude','>',0)->order('isdefault desc,id desc')->find();
		}else{
			$address = Db::name('member_address')->where('aid',aid)->where('mid',mid)->order('isdefault desc,id desc')->find();
		}
		if(!$address) $address = [];

		$needLocation = 0;
		$rs = \app\models\Freight::formatFreightList($freightList,$address,$product_price,$num,$weight);
		$freightList = $rs['freightList'];
		$freightArr = $rs['freightArr'];
		if($rs['needLocation']==1) $needLocation = 1;

		$userlevel = Db::name('member_level')->where('aid',aid)->where('id',$this->member['levelid'])->find();
		$adminset = Db::name('admin_set')->where('aid',aid)->find();
		$userinfo = [];
		$userinfo['discount'] = $userlevel['discount'];
		$userinfo['score'] = $this->member['score'];
		$userinfo['score2money'] = $adminset['score2money'];
		$userinfo['scoredk_money'] = round($userinfo['score'] * $userinfo['score2money'],2);
		$userinfo['scoredkmaxpercent'] = $adminset['scoredkmaxpercent'];
		$userinfo['realname'] = $this->member['realname'];
		$userinfo['tel'] = $this->member['tel'];
		
		$totalprice = $product_price;
		$leadermoney = 0;
		$totalprice = $totalprice - $leadermoney;
		$leveldk_money = 0;
        $needzkproduct_price = 0;
        if($product['lvprice']==0 && $product['no_discount'] == 0){ //未开启会员价可使用折扣
            $needzkproduct_price = $product_price;
        }
		if($userlevel && $userlevel['discount']>0 && $userlevel['discount']<10){
			$leveldk_money = $needzkproduct_price * (1 - $userlevel['discount'] * 0.1);
		}
		$leveldk_money = round($leveldk_money,2);
		$totalprice = $totalprice - $leveldk_money;

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

		$couponList = Db::name('coupon_record')->where('aid',aid)->where('mid',mid)->where('type','in','1,4')->where('status',0)
			->whereRaw("bid=-1 or bid=".$bid." or (bid=0 and (canused_bids='all' or find_in_set(".$bid.",canused_bids) or ($whereCids)))")->where('minprice','<=',$totalprice)->where('starttime','<=',time())->where('endtime','>',time())->order('id desc')->select()->toArray();
		if(!$couponList) $couponList = [];
		foreach($couponList as $k=>$v){
			//$couponList[$k]['starttime'] = date('m-d H:i',$v['starttime']);
			//$couponList[$k]['endtime'] = date('m-d H:i',$v['endtime']);
			$couponinfo = Db::name('coupon')->where('aid',aid)->where('id',$v['couponid'])->find();
			if(empty($couponinfo) || $couponinfo['fwtype']!==0 || $couponinfo['fwscene']!==0){
				unset($couponList[$k]);
			}
		}

	
		$rdata = [];
		$rdata['havetongcheng'] = $havetongcheng;
		$rdata['status'] = 1;
		$rdata['address'] = $address;
		$rdata['linkman'] = $address ? $address['name'] : strval($userinfo['realname']);
		$rdata['tel'] = $address ? $address['tel'] : strval($userinfo['tel']);
		if(!$rdata['linkman']){
			$lastorder = Db::name('seckill_order')->where('aid',aid)->where('mid',mid)->where('linkman','<>','')->find();
			if($lastorder){
				$rdata['linkman'] = $lastorder['linkman'];
				$rdata['tel'] = $lastorder['tel'];
			}
		}
		$rdata['product'] = $product;
		$rdata['guige'] = $guige;
		$rdata['freightList'] = $freightList;
		$rdata['freightArr'] = $freightArr;
		$rdata['userinfo'] = $userinfo;
		$rdata['couponList'] = $couponList;
		$rdata['business'] = $business;
		$rdata['num'] = $num;
		$rdata['leadermoney'] = $leadermoney;
		$rdata['goodsnum'] = $num;
		$rdata['weight'] = $guige['weight'] * $num;
		$rdata['product_price'] = $product_price;
		$rdata['leveldk_money'] = $leveldk_money;
		$rdata['needLocation'] = $needLocation;

		$rdata['price_dollar'] = false;
		$rdata['scorebdkyf'] = Db::name('admin_set')->where('aid',aid)->value('scorebdkyf');
		return $this->json($rdata);
	}
	public function createOrder(){
		$this->checklogin();
        $sysset = Db::name('admin_set')->where('aid',aid)->find();
		$post = input('post.');
		if($post['proid'] && $post['ggid']){
			$proid = $post['proid'];
			$ggid = $post['ggid'];
			$num = $post['num'] ? $post['num'] : 1;
		}else{
			return $this->json(['status'=>0,'msg'=>'产品数据错误']);
		}
		$num = intval($num);
		if($num <=0) return $this->json(['status'=>0,'msg'=>'产品数据错误']);
		$teamid = $post['teamid'];

		$product_price = 0;
		$givescore = 0; //奖励积分
		$weight = 0;//重量
			
		$product = Db::name('seckill_product')->where('aid',aid)->where('status',1)->where('ischecked',1)->where('id',$proid)->find();
		if(!$product) return $this->json(['status'=>0,'msg'=>'产品不存在或已下架']);
		$bid = $product['bid'];
		
		$guige = Db::name('seckill_guige')->where('aid',aid)->where('id',$ggid)->find();
		if(!$guige) return $this->json(['status'=>0,'msg'=>'产品规格不存在或已下架']);
        $guige = $this->formatguige($guige, $product['bid'],$product['lvprice']);
		$seckillset = db('seckill_sysset')->where(['aid'=>aid])->find();
		$seckill = [];
		if($seckillset['status']!=1) return $this->json(['status'=>0,'msg'=>'秒杀活动未开启']);
		$duration = $seckillset['duration'];
		if($product['starttime'] > time()){
			return $this->json(['status'=>0,'msg'=>'秒杀未开始']);
		}
		if($product['starttime'] < time() - 3600*$duration){
			return $this->json(['status'=>0,'msg'=>'秒杀已结束']);
		}
		$gettj = explode(',',$product['gettj']);
		if(!in_array('-1',$gettj) && !in_array($this->member['levelid'],$gettj) && (!in_array('0',$gettj) || $this->member['subscribe']!=1)){ //不是所有人
			if(!$product['gettjtip']) $product['gettjtip'] = '没有权限购买该商品';
			return $this->json(['status'=>0,'msg'=>$product['gettjtip'],'url'=>$product['gettjurl']]);
		}

        if($product['perlimit'] > 0){
            $buynum = $num + Db::name('seckill_order')->where('aid',aid)->where('mid',mid)->where('proid',$product['id'])->where('status','in','0,1,2,3')->sum('num');
            if($buynum > $product['perlimit']){
                return $this->json(['status'=>0,'msg'=>'每人限购'.$product['perlimit'].'件']);
            }
        }

        if($guige['stock'] < $num){
			return $this->json(['status'=>0,'msg'=>'下手慢了']);
		}
		if($product['buymax'] != 0){
			$mybuycount = $num + Db::name('seckill_order')->where('aid',aid)->where('proid',$product['id'])->where('mid',mid)->where('status','<>',4)->sum('num');
			if($mybuycount > $product['buymax']){
				return $this->json(['status'=>0,'msg'=>'每人最多只能购买'.$product['buymax'].'次']);
			}
		}
		$product_price += $guige['sell_price'] * $num;
		$givescore += $product['givescore'] * $num;
		$weight += $guige['weight'] * $num;

		$totalprice = $product_price;
		if($totalprice<0) $totalprice = 0;

		//收货地址
		if($post['addressid']=='' || $post['addressid']==0){
			$address = ['id'=>0,'name'=>$post['linkman'],'tel'=>$post['tel'],'area'=>'','address'=>''];
		}else{
			$address = Db::name('member_address')->where('id',$post['addressid'])->where('aid',aid)->where('mid',mid)->find();
		}
		
		//会员折扣
		$leveldk_money = 0;
		$userlevel = Db::name('member_level')->where('aid',aid)->where('id',$this->member['levelid'])->find();
		if($userlevel && $userlevel['discount']>0 && $userlevel['discount']<10){
			$leveldk_money = $totalprice * (1 - $userlevel['discount'] * 0.1);
		}
		$totalprice = $totalprice - $leveldk_money;

		//运费
		$freight_price = 0;
		if($post['freightid']){
			$freight = Db::name('freight')->where('aid',aid)->where('bid',$bid)->where('id',$post['freightid'])->find();
            if($freight['minpriceset']==1 && $freight['minprice']>0 && $freight['minprice'] > $product_price){
                return $this->json(['status'=>0,'msg'=>$freight['name'] . '满'.$freight['minprice'].'元起送']);
            }
			if(($address['name']=='' || $address['tel'] =='') && ($freight['pstype']==1 || $freight['pstype']==3) && $freight['needlinkinfo']==1){
				return $this->json(['status'=>0,'msg'=>'请填写联系人和联系电话']);
			}
			
			$rs = \app\models\Freight::getFreightPrice($freight,$address,$product_price,$num,$weight);
			if($rs['status']==0) return $this->json([$rs]);
			$freight_price = $rs['freight_price'];

			//判断配送时间选择是否符合要求
			if($freight['pstimeset']==1){
				$freight_times = explode('~',$post['freight_time']);
				if($freight_times[1]){
					$freighttime = strtotime(explode(' ',$freight_times[0])[0] . ' '.$freight_times[1]);
				}else{
					$freighttime = strtotime($freight_times[0]);
				}
				if(time() + $freight['psprehour']*3600 > $freighttime){
					return $this->json(['status'=>0,'msg'=>(($freight['pstype']==0 || $freight['pstype']==2 || $freight['pstype']==10)?'配送':'提货').'时间必须在'.$freight['psprehour'].'小时之后']);
				}
			}
		}elseif($product['freighttype']==3){
			$freight = ['id'=>0,'name'=>'自动发货','pstype'=>3];
            if($product['contact_require'] == 1 && ($address['name']=='' || $address['tel'] =='')){
                return $this->json(['status'=>0,'msg'=>'请填写联系人和联系电话']);
            }
            if($address['tel']!='' && !checkTel($address['tel'])){
                return $this->json(['status'=>0,'msg'=>'请填写正确的联系电话']);
            }
		}elseif($product['freighttype']==4){
			$freight = ['id'=>0,'name'=>'在线卡密','pstype'=>4];
            if($product['contact_require'] == 1 && ($address['name']=='' || $address['tel'] =='')){
                return $this->json(['status'=>0,'msg'=>'请填写联系人和联系电话']);
            }
            if($address['tel']!='' && !checkTel($address['tel'])){
                return $this->json(['status'=>0,'msg'=>'请填写正确的联系电话']);
            }
		}else{
			$freight = ['id'=>0,'name'=>'包邮','pstype'=>0];
		}
		//优惠券
		if($post['couponrid'] > 0){
			$couponrid = $post['couponrid'];
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
			}elseif($couponrecord['type']!=1 && $couponrecord['type']!=4){
				return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'不符合条件']);	
			}
			$couponinfo = Db::name('coupon')->where('aid',aid)->where('id',$couponrecord['couponid'])->find();
            if(empty($couponinfo)){
                return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'不存在或已作废']);
            }
            if($couponrecord['from_mid']==0 && $couponinfo && $couponinfo['isgive']==2){
                return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'仅可转赠']);
            }
			if($couponinfo['fwtype']!==0){
				return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'不符合条件']);
			}
            if($couponinfo['fwscene']!==0){
                return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'不符合条件']);
            }

			Db::name('coupon_record')->where('id',$couponrid)->update(['status'=>1,'usetime'=>time()]);
			if($couponrecord['type']==4){//运费抵扣券
				$coupon_money = $freight_price;
			}else{
				$coupon_money = $couponrecord['money'];
				if($coupon_money > $totalprice) $coupon_money = $totalprice;
			}
		}else{
			$coupon_money = 0;
		}
		$totalprice = $totalprice - $coupon_money;
		$totalprice = $totalprice + $freight_price;

		//积分抵扣
		$scoredkscore = 0;
		$scoredk_money = 0;
		if($post['usescore']==1){
			$adminset = Db::name('admin_set')->where('aid',aid)->find();
			$score2money = $adminset['score2money'];
			$scoredkmaxpercent = $adminset['scoredkmaxpercent'];
			$scorebdkyf = $adminset['scorebdkyf'];
			$scoredk_money = $this->member['score'] * $score2money;
			if($scorebdkyf == 1){//积分不抵扣运费
				if($scoredk_money > $totalprice - $freight_price) $scoredk_money = $totalprice - $freight_price;
			}else{
				if($scoredk_money > $totalprice) $scoredk_money = $totalprice;
			}
			if($scoredkmaxpercent >= 0 && $scoredkmaxpercent < 100 && $scoredk_money > 0 && $scoredk_money > $totalprice * $scoredkmaxpercent * 0.01){
				$scoredk_money = $totalprice * $scoredkmaxpercent * 0.01;
			}
			$totalprice = $totalprice - $scoredk_money;
			$totalprice = round($totalprice*100)/100;
			if($scoredk_money > 0){
				$scoredkscore = intval($scoredk_money / $score2money);
			}
		}

		$orderdata = [];
		$orderdata['aid'] = aid;
		$orderdata['bid'] = $bid;
		$orderdata['mid'] = mid;

		$ordernum = date('ymdHis').aid.rand(1000,9999);
		$orderdata['ordernum'] = $ordernum;
		$orderdata['title'] = removeEmoj($product['name']);
		
		$orderdata['proid'] = $product['id'];
		$orderdata['proname'] = $product['name'];
		$orderdata['propic'] = $guige['pic'] ? $guige['pic'] : $product['pic'];
		$orderdata['ggid'] = $guige['id'];
		$orderdata['ggname'] = $guige['name'];
		$orderdata['cost_price'] = $guige['cost_price'];
		$orderdata['sell_price'] = $guige['sell_price'];
		$orderdata['num'] = $num;
		
		$orderdata['linkman'] = $address['name'];
		$orderdata['tel'] = $address['tel'];
		$orderdata['area'] = $address['area'];
		$orderdata['area2'] = $address['province'].','.$address['city'].','.$address['district'];
		$orderdata['address'] = $address['address'];
		$orderdata['longitude'] = $address['longitude'];
		$orderdata['latitude'] = $address['latitude'];
		$orderdata['totalprice'] = $totalprice;
		$orderdata['product_price'] = $product_price;
		$orderdata['freight_price'] = $freight_price; //运费
		$orderdata['leveldk_money'] = $leveldk_money;  //会员折扣
		$orderdata['scoredk_money'] = $scoredk_money;	//积分抵扣
		$orderdata['scoredkscore'] = $scoredkscore;	//抵扣的积分
		$orderdata['givescore'] = $givescore;	//赠送积分
		if($freight && ($freight['pstype']==0 || $freight['pstype']==10)){
			$orderdata['freight_text'] = $freight['name'].'('.$freight_price.'元)';
			$orderdata['freight_type'] = $freight['pstype'];
		}elseif($freight && $freight['pstype']==1){
			$storename = Db::name('mendian')->where('aid',aid)->where('id',$post['storeid'])->value('name');
			$orderdata['freight_text'] = $freight['name'].'['.$storename.']';
			$orderdata['freight_type'] = 1;
			$orderdata['mdid'] = $post['storeid'];
		}elseif($freight && $freight['pstype']==2){
			$orderdata['freight_text'] = $freight['name'].'('.$freight_price.'元)';
			$orderdata['freight_type'] = 2;
		}elseif($freight && ($freight['pstype']==3 || $freight['pstype']==4)){ //自动发货 在线卡密
			$orderdata['freight_text'] = $freight['name'];
			$orderdata['freight_type'] = $freight['pstype'];
		}else{
			$orderdata['freight_text'] = '包邮';
		}
		$orderdata['freight_id'] = $freight['id'];
		$orderdata['freight_time'] = $post['freight_time']; //配送时间
		$orderdata['createtime'] = time();
		$orderdata['coupon_rid'] = $couponrid;
		$orderdata['coupon_money'] = $coupon_money; //优惠券抵扣
		
		$orderdata['hexiao_code'] = random(16);
		$orderdata['hexiao_qr'] = createqrcode(m_url('admin/hexiao/hexiao?type=seckill&co='.$orderdata['hexiao_code']));
		$orderdata['platform'] = platform;

        if($product['bid'] > 0) {
			$bset = Db::name('business_sysset')->where('aid',aid)->find();
			$scoredkmoney = $scoredk_money ?? 0;
			if($bset['scoredk_kouchu'] == 0){ //扣除积分抵扣
				$scoredkmoney = 0;
			}
            $business_feepercent = Db::name('business')->where('aid',aid)->where('id',$product['bid'])->value('feepercent');
            $totalprice_business = $product_price + $freight_price - $coupon_money;
            if($bset['scoredk_kouchu']==1){
                $totalprice_business = $totalprice_business - $scoredkmoney;
            }
            //商品独立费率
            if($product['feepercent'] != '' && $product['feepercent'] != null && $product['feepercent'] >= 0) {
                $orderdata['business_total_money'] = $totalprice_business * (100-$product['feepercent']) * 0.01;
                } else {
                //商户费率
                $orderdata['business_total_money'] = $totalprice_business * (100-$business_feepercent) * 0.01;
                }
        }
		
		$totalprice = $product_price;
		//算佣金
		if($sysset['fxjiesuantype'] == 1 || $sysset['fxjiesuantype'] == 2){
            $totalprice = $product_price - $leveldk_money - $scoredk_money;
            if($couponrecord['type']!=4) {//运费抵扣券
                $totalprice -= $coupon_money;
            }
		}
		$agleveldata = Db::name('member_level')->where('aid',aid)->where('id',$this->member['levelid'])->find();
		if($agleveldata['can_agent'] > 0 && $agleveldata['commission1own']==1){
			$this->member['pid'] = mid;
		}
		//$ppath = array_reverse(explode(',',$this->member['path']));
		
		if($product['commissionset']!=-1){
			//return $this->json(['status'=>0,'msg'=>'11','data'=>$this->member]);
			if($this->member['pid']){
				$parent1 = Db::name('member')->where('aid',aid)->where('id',$this->member['pid'])->find();
				
				if($parent1){
					$agleveldata1 = Db::name('member_level')->where('aid',aid)->where('id',$parent1['levelid'])->find();
					if($agleveldata1['can_agent']!=0){
						$orderdata['parent1'] = $parent1['id'];
					}
				}
				//return $this->json(['status'=>0,'msg'=>'11','data'=>$parent1,'data2'=>$agleveldata1]);
			}
			if($parent1['pid']){
				$parent2 = Db::name('member')->where('aid',aid)->where('id',$parent1['pid'])->find();
				if($parent2){
					$agleveldata2 = Db::name('member_level')->where('aid',aid)->where('id',$parent2['levelid'])->find();
					if($agleveldata2['can_agent']>1){
						$orderdata['parent2'] = $parent2['id'];
					}
				}
			}
			if($parent2['pid']){
				$parent3 = Db::name('member')->where('aid',aid)->where('id',$parent2['pid'])->find();
				if($parent3){
					$agleveldata3 = Db::name('member_level')->where('aid',aid)->where('id',$parent3['levelid'])->find();
					if($agleveldata3['can_agent']>2){
						$orderdata['parent3'] = $parent3['id'];
					}
				}
			}
			if($sysset['fxjiesuantype']==2){ //按利润提成
				$totalprice = $totalprice - $guige['cost_price'] * $num;
				if($totalprice < 0) $totalprice = 0;
			}
			if($product['commissionset']==1){//按比例
				$commissiondata = json_decode($product['commissiondata1'],true);
				if($commissiondata){
					$orderdata['parent1commission'] = $commissiondata[$agleveldata1['id']]['commission1'] * $totalprice * 0.01;
					$orderdata['parent2commission'] = $commissiondata[$agleveldata2['id']]['commission2'] * $totalprice * 0.01;
					$orderdata['parent3commission'] = $commissiondata[$agleveldata3['id']]['commission3'] * $totalprice * 0.01;
				}
			}elseif($product['commissionset']==2){//按固定金额
				$commissiondata = json_decode($product['commissiondata2'],true);
				if($commissiondata){
					$orderdata['parent1commission'] = $commissiondata[$agleveldata1['id']]['commission1'] * $num;
					$orderdata['parent2commission'] = $commissiondata[$agleveldata2['id']]['commission2'] * $num;
					$orderdata['parent3commission'] = $commissiondata[$agleveldata3['id']]['commission3'] * $num;
				}
			}elseif($product['commissionset']==3){//提成是积分
				$commissiondata = json_decode($product['commissiondata3'],true);
				if($commissiondata){
					$orderdata['parent1score'] = $commissiondata[$agleveldata1['id']]['commission1'] * $num;
					$orderdata['parent2score'] = $commissiondata[$agleveldata2['id']]['commission2'] * $num;
					$orderdata['parent3score'] = $commissiondata[$agleveldata3['id']]['commission3'] * $num;
				}
			}else{
				if($agleveldata1){
					if($agleveldata1['commissiontype']==1){ //固定金额按单
						$orderdata['parent1commission'] = $agleveldata1['commission1'];
					}else{
						$orderdata['parent1commission'] = $agleveldata1['commission1'] * $totalprice * 0.01;
					}
				}
				if($agleveldata2){
					if($agleveldata2['commissiontype']==1){
						$orderdata['parent2commission'] = $agleveldata2['commission2'];
					}else{
						$orderdata['parent2commission'] = $agleveldata2['commission2'] * $totalprice * 0.01;
					}
				}
				if($agleveldata3){
					if($agleveldata3['commissiontype']==1){
						$orderdata['parent3commission'] = $agleveldata3['commission3'];
					}else{
						$orderdata['parent3commission'] = $agleveldata3['commission3'] * $totalprice * 0.01;
					}
				}
			}
		}

		$orderid = Db::name('seckill_order')->insertGetId($orderdata);
		\app\models\Freight::saveformdata($orderid,'seckill_order',$freight['id'],$post['formdata']);

		if($orderdata['parent1'] && ($orderdata['parent1commission'] || $orderdata['parent1score'])){
			Db::name('member_commission_record')->insert(['aid'=>aid,'mid'=>$orderdata['parent1'],'frommid'=>mid,'orderid'=>$orderid,'ogid'=>$product['id'],'type'=>'seckill','commission'=>$orderdata['parent1commission'],'score'=>$orderdata['parent1score'],'remark'=>'下级购买商品奖励','createtime'=>time()]);
		}
		if($orderdata['parent2'] && ($orderdata['parent2commission'] || $orderdata['parent2score'])){
			Db::name('member_commission_record')->insert(['aid'=>aid,'mid'=>$orderdata['parent2'],'frommid'=>mid,'orderid'=>$orderid,'ogid'=>$product['id'],'type'=>'seckill','commission'=>$orderdata['parent2commission'],'score'=>$orderdata['parent2score'],'remark'=>'下二级购买商品奖励','createtime'=>time()]);
		}
		if($orderdata['parent3'] && ($orderdata['parent3commission'] || $orderdata['parent3score'])){
			Db::name('member_commission_record')->insert(['aid'=>aid,'mid'=>$orderdata['parent3'],'frommid'=>mid,'orderid'=>$orderid,'ogid'=>$product['id'],'type'=>'seckill','commission'=>$orderdata['parent3commission'],'score'=>$orderdata['parent3score'],'remark'=>'下三级购买商品奖励','createtime'=>time()]);
		}

		$payorderid = \app\models\Payorder::createorder(aid,$orderdata['bid'],$orderdata['mid'],'seckill',$orderid,$ordernum,$orderdata['title'],$orderdata['totalprice'],$orderdata['scoredkscore']);

		//减库存加销量
		$stock = $guige['stock'] - $num;
		if($stock < 0) $stock = 0;
		$pstock = $product['stock'] - $num;
		if($pstock < 0) $pstock = 0;
		$sales = $guige['sales'] + $num;
		$psales = $product['sales'] + $num;
		Db::name('seckill_guige')->where('aid',aid)->where('id',$guige['id'])->update(['stock'=>$stock,'sales'=>$sales]);
		Db::name('seckill_product')->where('aid',aid)->where('id',$product['id'])->update(['stock'=>$pstock,'sales'=>$psales]);
		if($orderdata['bid'] > 0)
            $store_name = Db::name('business')->where('aid',aid)->where('id',$orderdata['bid'])->value('name');
        else
            $store_name = Db::name('admin_set')->where('aid',aid)->value('name');
		if($orderdata['bid']==0){
			//公众号通知 订单提交成功
			$tmplcontent = [];
			$tmplcontent['first'] = '有新秒杀订单提交成功';
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
			\app\commons\Wechat::sendhttmpl(aid,$orderdata['bid'],'tmpl_orderconfirm',$tmplcontent,m_url('admin/order/seckillorder'),$orderdata['mdid'],$tempconNew);
			
			$tmplcontent = [];
			$tmplcontent['thing11'] = $orderdata['title'];
			$tmplcontent['character_string2'] = $orderdata['ordernum'];
			$tmplcontent['phrase10'] = '待付款';
			$tmplcontent['amount13'] = $orderdata['totalprice'].'元';
			$tmplcontent['thing27'] = $this->member['nickname'];
			\app\commons\Wechat::sendhtwxtmpl(aid,$orderdata['bid'],'tmpl_orderconfirm',$tmplcontent,'admin/order/seckillorder',$orderdata['mdid']);
		}
		return $this->json(['status'=>1,'orderid'=>$orderid,'payorderid'=>$payorderid,'msg'=>'提交成功']);
	}
	
	public function orderlist(){
		$this->checklogin();
		$st = input('param.st');
		if(!input('?param.st') || $st === ''){
			$st = 'all';
		}
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
		}elseif($st == '10'){
			$where[] = ['refund_status','>',0];
		}
		$pernum = 10;
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$datalist = Db::name('seckill_order')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		if(!$datalist) $datalist = array();
		foreach($datalist as $key=>$v){
			if($v['bid']!=0){
				$datalist[$key]['binfo'] = Db::name('business')->where('aid',aid)->where('id',$v['bid'])->field('id,name,logo')->find();
			}
            //发票
            $datalist[$key]['invoice'] = 0;
            if($v['bid']) {
                $datalist[$key]['invoice'] = Db::name('business')->where('aid',aid)->where('id',$v['bid'])->value('invoice');
            } else {
                $datalist[$key]['invoice'] = Db::name('admin_set')->where('aid',aid)->value('invoice');
            }
		}

		$showprice_dollar= false;
		$rdata = [];
		$rdata['st'] = $st;
		$rdata['datalist'] = $datalist;
		$rdata['showprice_dollar'] = $showprice_dollar;
		return $this->json($rdata);
	}
	public function orderdetail(){
		$this->checklogin();
		$detail = Db::name('seckill_order')->where('id',input('param.id/d'))->where('aid',aid)->where('mid',mid)->find();
		if(!$detail) return $this->json(['status'=>0,'msg'=>'订单不存在']);
		
		$detail['createtime'] = $detail['createtime'] ? date('Y-m-d H:i:s',$detail['createtime']) : '';
		$detail['collect_time'] = $detail['collect_time'] ? date('Y-m-d H:i:s',$detail['collect_time']) : '';
		$detail['paytime'] = $detail['paytime'] ? date('Y-m-d H:i:s',$detail['paytime']) : '';
		$detail['refund_time'] = $detail['refund_time'] ? date('Y-m-d H:i:s',$detail['refund_time']) : '';
		$detail['send_time'] = $detail['send_time'] ? date('Y-m-d H:i:s',$detail['send_time']) : '';
		$detail['formdata'] = \app\models\Freight::getformdata($detail['id'],'seckill_order');
			
		$storeinfo = [];
		if($detail['freight_type'] == 1){
            $storeinfo = Db::name('mendian')->where('id',$detail['mdid'])->field('id,name,address,longitude,latitude')->find();
		}
		if($detail['bid'] > 0){
			$detail['binfo'] = Db::name('business')->where('aid',aid)->where('id',$detail['bid'])->field('id,name,logo')->find();
		}

		$seckillset = Db::name('seckill_sysset')->where('aid',aid)->field('comment')->find();
		$showprice_dollar= false;
		$rdata = [];
        $rdata['status'] = 1;
        //发票
        $rdata['invoice'] = 0;
        if($detail['bid']) {
            $rdata['invoice'] = Db::name('business')->where('aid',aid)->where('id',$detail['bid'])->value('invoice');
        } else {
            $rdata['invoice'] = Db::name('admin_set')->where('aid',aid)->value('invoice');
        }
		$rdata['detail'] = $detail;
		$rdata['seckillset'] = $seckillset;
		$rdata['storeinfo'] = $storeinfo;
		$rdata['showprice_dollar'] = $showprice_dollar;
		return $this->json($rdata);
	}
	function closeOrder(){
		$this->checklogin();
		$post = input('post.');
		$orderid = intval($post['orderid']);
		$order = Db::name('seckill_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->find();
		if(!$order || $order['status']!=0){
			return $this->json(['status'=>0,'msg'=>'关闭失败,订单状态错误']);
		}
		//加库存
		Db::name('seckill_guige')->where('aid',aid)->where('id',$order['ggid'])->update(['stock'=>Db::raw("stock+".$order['num']),'sales'=>Db::raw("sales-".$order['num'])]);
		Db::name('seckill_product')->where('aid',aid)->where('id',$order['proid'])->update(['stock'=>Db::raw("stock+".$order['num']),'sales'=>Db::raw("sales-".$order['num'])]);
		
		//优惠券抵扣的返还
		if($order['coupon_rid'] > 0){
			Db::name('coupon_record')->where('aid',aid)->where('mid',mid)->where('id',$order['coupon_rid'])->update(['status'=>0,'usetime'=>'']);
		}
		$rs = Db::name('seckill_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->update(['status'=>4]);
		return $this->json(['status'=>1,'msg'=>'操作成功']);
	}
	function delOrder(){
		$this->checklogin();
		$post = input('post.');
		$orderid = intval($post['orderid']);
		$order = Db::name('seckill_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->find();
		if(!$order || ($order['status']!=4 && $order['status']!=3)){
			return $this->json(['status'=>0,'msg'=>'删除失败,订单状态错误']);
		}
		if($order['status']==3){
			$rs = Db::name('seckill_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->update(['delete'=>1]);
		}else{
			$rs = Db::name('seckill_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->delete();
		}
		return $this->json(['status'=>1,'msg'=>'删除成功']);
	}
	function orderCollect(){ //确认收货
		$this->checklogin();
		$post = input('post.');
		$orderid = intval($post['orderid']);
		$order = Db::name('seckill_order')->where('aid',aid)->where('mid',mid)->where('id',$orderid)->find();
		if(!$order || ($order['status']!=2)){
			return $this->json(['status'=>0,'msg'=>'订单状态不符合收货要求']);
		}
		$rs = \app\commons\Order::collect($order,'seckill');
		if($rs['status'] == 0) return $this->json($rs);

		Db::name('seckill_order')->where('aid',aid)->where('mid',mid)->where('id',$orderid)->update(['status'=>3,'collect_time'=>time()]);
		\app\commons\Member::uplv(aid,mid);
		
		$tmplcontent = [];
		$tmplcontent['first'] = '有秒杀订单客户已确认收货';
		$tmplcontent['remark'] = '点击进入查看~';
		$tmplcontent['keyword1'] = $this->member['nickname'];
		$tmplcontent['keyword2'] = $order['ordernum'];
		$tmplcontent['keyword3'] = $order['totalprice'].'元';
		$tmplcontent['keyword4'] = date('Y-m-d H:i',$order['paytime']);
        $tmplcontentNew = [];
        $tmplcontentNew['thing3'] = $this->member['nickname'];//收货人
        $tmplcontentNew['character_string7'] = $order['ordernum'];//订单号
        $tmplcontentNew['time8'] = date('Y-m-d H:i');//送达时间
		\app\commons\Wechat::sendhttmpl(aid,$order['bid'],'tmpl_ordershouhuo',$tmplcontent,m_url('admin/order/seckillorder'),$order['mdid'],$tmplcontentNew);

		$tmplcontent = [];
		$tmplcontent['thing2'] = $order['title'];
		$tmplcontent['character_string6'] = $order['ordernum'];
		$tmplcontent['thing3'] = $this->member['nickname'];
		$tmplcontent['date5'] = date('Y-m-d H:i');
		\app\commons\Wechat::sendhtwxtmpl(aid,$order['bid'],'tmpl_ordershouhuo',$tmplcontent,'admin/order/seckillorder',$order['mdid']);

		return $this->json(['status'=>1,'msg'=>'确认收货成功']);
	}
	function refund(){//申请退款
		$this->checklogin();
		if(request()->isPost()){
			$post = input('post.');
			$orderid = intval($post['orderid']);
			$money = floatval($post['money']);
			$order = Db::name('seckill_order')->where('aid',aid)->where('mid',mid)->where('id',$orderid)->find();
			if(!$order || ($order['status']!=1 && $order['status'] != 2) || $order['refund_status'] == 2){
				return $this->json(['status'=>0,'msg'=>'订单状态不符合退款要求']);
			}
			if($money < 0 || $money > $order['totalprice']){
				return $this->json(['status'=>0,'msg'=>'退款金额有误']);
			}
			Db::name('seckill_order')->where('aid',aid)->where('mid',mid)->where('id',$orderid)->update(['refund_time'=>time(),'refund_status'=>1,'refund_reason'=>$post['reason'],'refund_money'=>$money]);

			$tmplcontent = [];
			$tmplcontent['first'] = '有秒杀订单客户申请退款';
			$tmplcontent['remark'] = '点击进入查看~';
			$tmplcontent['keyword1'] = $order['ordernum'];
			$tmplcontent['keyword2'] = $money.'元';
			$tmplcontent['keyword3'] = $post['reason'];
            $tmplcontentNew = [];
            $tmplcontentNew['number2'] = $order['ordernum'];//订单号
            $tmplcontentNew['amount4'] = $money;//退款金额
			\app\commons\Wechat::sendhttmpl(aid,$order['bid'],'tmpl_ordertui',$tmplcontent,m_url('admin/order/seckillorder'),$order['mdid'],$tmplcontentNew);

			
			$tmplcontent = [];
			$tmplcontent['thing1'] = $order['title'];
			$tmplcontent['character_string4'] = $order['ordernum'];
			$tmplcontent['amount2'] = $order['totalprice'];
			$tmplcontent['amount9'] = $money.'元';
			$tmplcontent['thing10'] = $post['reason'];
			\app\commons\Wechat::sendhtwxtmpl(aid,$order['bid'],'tmpl_ordertui',$tmplcontent,'admin/order/seckillorder',$order['mdid']);

			return $this->json(['status'=>1,'msg'=>'提交成功,请等待商家审核']);
		}
		$rdata = [];
		$rdata['price'] = input('param.price/f');
		$rdata['orderid'] = input('param.orderid/d');
		$order = Db::name('seckill_order')->where('aid',aid)->where('mid',mid)->where('id',$rdata['orderid'])->find();
		$rdata['price'] = $order['totalprice'];
		return $this->json($rdata);
	}
	//评价商品
	public function comment(){
		$this->checklogin();
		$orderid = input('param.orderid/d');
		$og = Db::name('seckill_order')->where('id',$orderid)->where('mid',mid)->find();
		if(!$og){
			return $this->json(['status'=>0,'msg'=>'未查找到相关记录']);
		}
		$comment = Db::name('seckill_comment')->where('orderid',$orderid)->where('aid',aid)->where('mid',mid)->find();
		if(request()->isPost()){
			$seckillset = Db::name('seckill_sysset')->where('aid',aid)->find();
			if($seckillset['comment']==0){
				return $this->json(['status'=>0,'msg'=>'评价功能未开启']);
			}
			if($comment){
				return $this->json(['status'=>0,'msg'=>'您已经评价过了']);
			}
			$order = Db::name('seckill_order')->where('aid',aid)->where('mid',mid)->where('id',$orderid)->find();
			$content = input('post.content');
			$content_pic = input('post.content_pic');
			$score = input('post.score/d');
			if($score < 1){
				return $this->json(['status'=>0,'msg'=>'请打分']);
			}
			$data['aid'] = aid;
			$data['mid'] = mid;
			$data['orderid'] = $order['id'];
			$data['ordernum']= $order['ordernum'];
			$data['proid'] =$order['proid'];
			$data['proname'] = $order['proname'];
			$data['propic'] = $order['propic'];
			$data['ggid'] = $order['ggid'];
			$data['ggname'] = $order['ggname'];
			$data['score'] = $score;
			$data['content'] = $content;
			$data['nickname']= $this->member['nickname'];
			$data['headimg'] = $this->member['headimg'];
			$data['createtime'] = time();
			$data['content_pic'] = $content_pic;
			$data['status'] = ($seckillset['comment_check']==1 ? 0 : 1);
			//if($seckillset['comment_check']==0){
			//	$data['status'] = 1;
				//$data['givescore'] = $seckillset['comment_givescore'];
			//}else{
			//	$data['status'] = 0;
				//$data['givescore'] = 0;
			//}
			Db::name('seckill_comment')->insert($data);
			Db::name('seckill_order')->where('aid',aid)->where('mid',mid)->where('id',$order['id'])->update(['iscomment'=>1]);
			
			//如果不需要审核 增加产品评论数及评分
			if($seckillset['comment_check']==0){
				$countnum = Db::name('seckill_comment')->where('proid',$order['proid'])->where('status',1)->count();
				$score = Db::name('seckill_comment')->where('proid',$order['proid'])->where('status',1)->avg('score');
				$haonum = Db::name('seckill_comment')->where('proid',$order['proid'])->where('status',1)->where('score','>',3)->count(); //好评数
				if($countnum > 0){
					$haopercent = $haonum/$countnum*100;
				}else{
					$haopercent = 100;
				}
				Db::name('seckill_product')->where('id',$order['proid'])->update(['comment_num'=>$countnum,'comment_score'=>$score,'comment_haopercent'=>$haopercent]);
			}
			return $this->json(['status'=>1,'msg'=>'评价成功']);
		}
		$rdata = [];
		$rdata['og'] = $og;
		$rdata['comment'] = $comment;
		return $this->json($rdata);
	}

	public function logistics(){//查快递单号
		$get = input('param.');
		$content = \app\commons\Common::ali_getwuliu($get['express_no'],$get['express'],aid);
		$data = json_decode($content,true);

		if(!$data || $data['msg']!='ok'){
			$list = [];
		}else{
			$list = $data['result']['list'];
			foreach($list as $k=>$v){
				$list[$k]['context'] = $v['status'];
			}
		}

		$rdata = [];
		$rdata['express_no'] = $get['express_no'];
		$rdata['express'] = $get['express'];
		$rdata['datalist'] = $list;
		return $this->json($rdata);
	}
	//商品海报
	function getposter(){
		$this->checklogin();
		$post = input('post.');
		$platform = platform;
		$page = '/activity/seckill/product';
		$scene = 'id_'.$post['proid'].'-pid_'.$this->member['id'];
		//if($platform == 'mp' || $platform == 'h5' || $platform == 'app'){
		//	$page = PRE_URL .'/h5/'.aid.'.html#'. $page;
		//}
		$posterset = Db::name('admin_set_poster')->where('aid',aid)->where('type','product')->where('platform',$platform)->order('id')->find();

		$posterdata = Db::name('member_poster')->where('aid',aid)->where('mid',mid)->where('scene',$scene)->where('type','seckill')->where('posterid',$posterset['id'])->find();
		if(!$posterdata){
			$product = Db::name('seckill_product')->where('id',$post['proid'])->find();
			$sysset = Db::name('admin_set')->where('aid',aid)->find();
			$textReplaceArr = [
				'[头像]'=>$this->member['headimg'],
				'[昵称]'=>$this->member['nickname'],
				'[姓名]'=>$this->member['realname'],
				'[手机号]'=>$this->member['mobile'],
				'[商城名称]'=>$sysset['name'],
				'[商品名称]'=>$product['name'],
				'[商品销售价]'=>$product['sell_price'],
				'[商品市场价]'=>$product['market_price'],
				'[商品图片]'=>$product['pic'],
			];

			$poster = $this->_getposter(aid,$product['bid'],$platform,$posterset['content'],$page,$scene,$textReplaceArr);
			$posterdata = [];
			$posterdata['aid'] = aid;
			$posterdata['mid'] = $this->member['id'];
			$posterdata['scene'] = $scene;
			$posterdata['page'] = $page;
			$posterdata['type'] = 'seckill';
			$posterdata['poster'] = $poster;
			$posterdata['createtime'] = time();
			Db::name('member_poster')->insert($posterdata);
		}
		return $this->json(['status'=>1,'poster'=>$posterdata['poster']]);
	}
	//商品列表数据会员价处理
	public function formatprolist($datalist){
		if($this->member){
			foreach($datalist as $k=>$v){
                $datalist[$k]['sell_price_origin'] = $v['sell_price']?$v['sell_price']:0;
				if($v['lvprice']==1){
					$lvprice_data = json_decode($v['lvprice_data'],true);
					if($lvprice_data && isset($lvprice_data[$this->member['levelid']])){
						$datalist[$k]['sell_price'] = $lvprice_data[$this->member['levelid']];
					}
				}
			}
		}
		return $datalist;
	}
}