<?php


// +----------------------------------------------------------------------
// | 砍价接口
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\Db;
class ApiKanjia extends ApiCommon
{
	//获取商品
	function index(){
		$post = input('param.');
		$order = 'sort desc,id desc';
		if($post['field'] && $post['order']){
			$order = $post['field'].' '.$post['order'].',sort,id desc';
		}else{
			$order = 'sort desc,id desc';
		}
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['status','=',1];
		$where[] = ['ischecked','=',1];
		$business_sysset = Db::name('business_sysset')->where('aid',aid)->find();
		if(input('param.bid')){
			$where[] = ['bid','=',input('param.bid')];
		}elseif(!$business_sysset || $business_sysset['status']==0 || $business_sysset['product_isshow']==0){
			$where[] = ['bid','=',0];
		}

		//分类
		if($post['cid']){
			$post['cid'] = intval($post['cid']);
			$title = Db::name('kanjia_category')->where('aid',aid)->where('id',$post['cid'])->order('sort desc,id')->value('name');
			//子分类
			$child = Db::name('kanjia_category')->where('aid',aid)->where('pid',$post['cid'])->select()->toArray();
			if($child){
				$cateArr = [$post['cid']];
				foreach($child as $c){
					$cateArr[] = $c['id'];
				}
				$where[] = ['cid','in',$cateArr];
			}else{
				$where[] = ['cid','=',$post['cid']];
			}
		}
		if($post['keyword']){
			$where[] = ['name','like','%'.$post['keyword'].'%'];
		}
		if($post['groupid']) $where[] = Db::raw("find_in_set(".intval($post['groupid']).",gid)");
		$pagenum = $post['pagenum'] ? $post['pagenum'] : 1;
		$field = "id,pic,name,sales,min_price,sell_price,sales,saleing";
		$datalist = Db::name('kanjia_product')->field($field)->where($where)->order($order)->page($pagenum,12)->select()->toArray();
		if(!$datalist) $datalist = [];
		foreach($datalist as $k=>$v){
			if($v['saleing']>0){
				$datalist[$k]['joinlist'] = Db::name('kanjia_join')->alias('kanjia_join')->field('kanjia_join.*,member.nickname,member.headimg')
					->join('member','member.id=kanjia_join.mid')
					->where('kanjia_join.aid',aid)->where('kanjia_join.proid',$v['id'])
					->order('kanjia_join.id desc')->limit(7)->select()->toArray();
			}
			}
		$sysset = Db::name('kanjia_sysset')->where('aid',aid)->find();
		$pics = $sysset['pics'];
		if(!$pics) $pics = [];
		$pics = explode(',',$pics);

		$rdata = [];
		$rdata['status'] = 1;
		$rdata['datalist'] = $datalist;
		$rdata['pics'] = $pics;
		$rdata['pic'] = $sysset['pic'] ? $sysset['pic'] : PRE_URL.'/static/images/kanjia_banner.png';
		return $this->json($rdata);
	}
	//获取商品详情
	function product(){
		$proid = input('param.id/d');
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['id','=',$proid];
		$product = Db::name('kanjia_product')->where($where)->find();
		if(!$product) $this->json(['status'=>0,'msg'=>'商品不存在']);
		if($product['status']==0) $this->json(['status'=>0,'msg'=>'商品已下架']);
		if($product['ischecked']!=1) $this->json(['status'=>0,'msg'=>'商品未审核']);

		if(!$product['pics']) $product['pics'] = $product['pic'];
		$product['pics'] = explode(',',$product['pics']);
		if($product['fuwupoint']){
			$product['fuwupoint'] = explode(' ',preg_replace("/\s+/",' ',str_replace('　',' ',trim($product['fuwupoint']))));
		}
		$joinlist = Db::name('kanjia_join')->alias('kanjia_join')->field('kanjia_join.*,member.nickname,member.headimg')->join('member','member.id=kanjia_join.mid')->where('kanjia_join.aid',aid)->where('kanjia_join.proid',$product['id'])->order('kanjia_join.id desc')->limit(7)->select()->toArray();
		$join = Db::name('kanjia_join')->where('aid',aid)->where('mid',mid)->where('proid',$product['id'])->find();
		if($join){
			$imJoin = 1;
		}else{
			$imJoin = 0;
		}
		$rs = Db::name('member_favorite')->where('aid',aid)->where('mid',mid)->where('proid',$proid)->where('type','kanjia')->find();
		if($rs){
			$isfavorite = true;
		}else{
			$isfavorite = false;
		}
		//添加浏览历史
		$rs = Db::name('member_history')->where('aid',aid)->where('mid',mid)->where('proid',$proid)->where('type','kanjia')->find();
		if($rs){
			Db::name('member_history')->where('id',$rs['id'])->update(['createtime'=>time()]);
		}else{
			Db::name('member_history')->insert(['aid'=>aid,'mid'=>mid,'proid'=>$proid,'type'=>'kanjia','createtime'=>time()]);
		}

		$set = Db::name('admin_set')->where('aid',aid)->field('name,logo,desc,tel,kfurl')->find();
		$shopset = Db::name('kanjia_sysset')->where('aid',aid)->find();

		if($product['bid']!=0){
			$business = Db::name('business')->where('aid',aid)->where('id',$product['bid'])->field('id,name,logo,desc,tel,address,sales,kfurl')->find();
		}else{
			$business = $set;
		}

		$product['detail'] = \app\commons\System::initpagecontent($product['detail'],aid,mid,platform);
        $rdata = [];
		$rdata['status'] = 1;
		$rdata['product'] = $product;
		$rdata['business'] = $business;
		$rdata['shopset'] = $shopset;
		$rdata['joinlist'] = $joinlist;
		$rdata['nowtime'] = time();
		$rdata['imJoin'] = $imJoin;
		$rdata['isfavorite'] = $isfavorite;
		return $this->json($rdata);
	}
	function join(){
		//$this->checklogin();
		$post = input('param.');
		$proid = $post['proid'];
		$joinid = $post['joinid'];
		if(!$joinid) $this->checklogin();
		if(!$proid && !$joinid) return $this->json(['status'=>0,'msg'=>'商品不存在']);
		if($joinid){
			$join_info = Db::name('kanjia_join')->where('aid',aid)->where('id',$joinid)->find();
			$product = Db::name('kanjia_product')->where('aid',aid)->where('id',$join_info['proid'])->find();
		}else{
			$product = Db::name('kanjia_product')->where('aid',aid)->where('id',$proid)->find();
		}
		if(!$product) return $this->json(['status'=>0,'msg'=>'商品不存在']);
		if($product['status']==0) return $this->json(['status'=>0,'msg'=>'商品已下架']);
		if($product['ischecked']!=1) return $this->json(['status'=>0,'msg'=>'商品未审核']);

		if(!$product['pics']) $product['pics'] = $product['pic'];
		$product['pics'] = explode(',',$product['pics']);
		if($product['fuwupoint']){
			$product['fuwupoint'] = explode(' ',preg_replace("/\s+/",' ',str_replace('　',' ',trim($product['fuwupoint']))));
		}
		if(!$joinid && $this->member){
			$join_info = Db::name('kanjia_join')->where('aid',aid)->where('mid',mid)->where('proid',$proid)->find();
			if(!$join_info){
				if($product['stock'] <= 0){
					return $this->json(['status'=>0,'msg'=>'该砍价商品已被抢光了']);
				}
				if($product['starttime'] > time()){
					return $this->json(['status'=>0,'msg'=>'活动未开始']);
				}
				if($product['endtime'] < time()){
					return $this->json(['status'=>0,'msg'=>'活动已结束']);
				}
				$joindata = [];
				$joindata['aid'] = aid;
				$joindata['bid'] = $product['bid'];
				$joindata['mid'] = mid;
				$joindata['proid'] = $proid;
				$joindata['now_price'] = $product['sell_price'];
				$joindata['helpnum'] = 0;
				$joindata['createtime'] = time();
				$joinid = Db::name('kanjia_join')->insertGetId($joindata);
				Db::name('kanjia_product')->where('aid',aid)->where('id',$proid)->inc('saleing')->update();
			}else{
				$joinid = $join_info['id'];
			}
		}
		$join_info = Db::name('kanjia_join')->where('aid',aid)->where('id',$joinid)->find();
		$joinuserinfo= Db::name('member')->field('id,nickname,headimg')->where('aid',aid)->where('id',$join_info['mid'])->find();
		$join_info['yikan_price'] = round($product['sell_price']*1 - $join_info['now_price']*1,2);
		$join_info['shengyu_price'] = round($join_info['now_price']*1 - $product['min_price']*1,2);
		$cut_percent = floor(($product['sell_price']*1 - $join_info['now_price']*1)/($product['sell_price']*1-$product['min_price']*1)*100);
        if(is_nan($cut_percent)) $cut_percent = 0;
        if(is_infinite($cut_percent)) $cut_percent = 100;
		$set = Db::name('admin_set')->where('aid',aid)->field('name,logo,desc,tel')->find();
		$shopset = Db::name('kanjia_sysset')->where('aid',aid)->find();

		$product['detail'] = \app\commons\System::initpagecontent($product['detail'],aid,mid,platform);

		//是否砍过了
		$rs = Db::name('kanjia_help')->where('aid',aid)->where('mid',mid)->where('joinid',$joinid)->find();
		if($rs){
			$iskan = 1;
		}else{
			$iskan = 0;
		}

		$rdata = [];
		$rdata['status'] = 1;
		$rdata['product'] = $product;
		$rdata['joininfo'] = $join_info;
		$rdata['joinuserinfo'] = $joinuserinfo;
		$rdata['sysset'] = $set;
		$rdata['shopset'] = $shopset;
		$rdata['mid'] = mid;
		$rdata['nowtime'] = time();
		$rdata['cut_percent'] = $cut_percent;
		$rdata['iskan'] = $iskan;
		return $this->json($rdata);
	}

	function helplist(){
		$post = input('param.');
		$joinid = $post['joinid'];
		$pagenum = $post['pagenum'] ? $post['pagenum'] : 1;
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['joinid','=',$joinid];
		$list = Db::name('kanjia_help')->where($where)->order('id desc')->page($pagenum,10)->select()->toArray();

		if(!$list) $list = [];
		foreach($list as $k=>$v){
			$userinfo = Db::name('member')->where('id',$v['mid'])->find();
			$list[$k]['nickname'] = $userinfo['nickname'];
			$list[$k]['headimg'] = $userinfo['headimg'];
			$list[$k]['createtime'] = date('Y-m-d H:i:s',$v['createtime']);
		}
		$rdata = [];
		$rdata['status'] = 1;
		$rdata['datalist'] = $list;
		return $this->json($rdata);
	}
	function kanjiaKan(){
		$this->checklogin();
		$post = input('post.');
		$joinid = $post['joinid'];
		$join_info = Db::name('kanjia_join')->where('aid',aid)->where('id',$joinid)->find();
		if(!$join_info) return $this->json(['status'=>0,'msg'=>'未找到该记录']);
		if($join_info['status']==1) return $this->json(['status'=>0,'msg'=>'好友已砍价完成']);
		if($join_info['status']==2) return $this->json(['status'=>0,'msg'=>'砍价已结束']);
		$product = Db::name('kanjia_product')->where('aid',aid)->where('id',$join_info['proid'])->find();
		if(!$product) return $this->json(['status'=>0,'msg'=>'商品不存在']);
		if($product['status']==0) return $this->json(['status'=>0,'msg'=>'商品已下架']);
		if($product['ischecked']!=1) return $this->json(['status'=>0,'msg'=>'商品未审核']);
		if($product['stock'] <= 0){
			return $this->json(['status'=>0,'msg'=>'该砍价商品已被抢光了']);
		}
		if($product['endtime'] < time()){
			return $this->json(['status'=>0,'msg'=>'活动已结束']);
		}

		$rs = Db::name('kanjia_help')->where('aid',aid)->where('mid',mid)->where('joinid',$joinid)->find();
		if($rs) return $this->json(['status'=>0,'msg'=>'您已经砍过了']);
		if($join_info['mid'] != mid){
			$helpcount = Db::name('kanjia_help')->where('aid',aid)->where('mid',mid)->where('proid',$product['id'])->where('helpmid','<>',mid)->count();
			$sharelog = Db::name('kanjia_sharelog')->where('aid',aid)->where('mid',mid)->where('proid',$product['id'])->find();
			if($sharelog){
				$addtimes = $sharelog['addtimes'];
			}else{
				$addtimes = 0;
			}
			if($helpcount >= $product['perhelpnum'] + $addtimes){
				return $this->json(['status'=>0,'msg'=>'您最多只能帮'.($product['perhelpnum'] + $addtimes).'个好友砍价']);
			}
		}
		//计算砍价金额
		$kjdata = json_decode($product['kjdata'],true);
		$startmoney = 0;
		$endmoney = 0;
		foreach($kjdata as $v){
			if(intval($v['startnum'])<=$join_info['helpnum']+1 && ($v['endnum'] == '最后一' || intval($v['endnum'])>=$join_info['helpnum']+1)){
				$startmoney = $v['startmoney'];
				$endmoney = $v['endmoney'];
				break;
			}
		}
		$cutmoney = rand($startmoney*100,$endmoney*100)/100;
		if($cutmoney > $join_info['now_price'] - $product['min_price']){
			$cutmoney = $join_info['now_price'] - $product['min_price'];
		}
		$now_price = $join_info['now_price'] - $cutmoney;
		Db::name('kanjia_join')->where('aid',aid)->where('id',$joinid)->update(['now_price'=>$now_price,'helpnum'=>$join_info['helpnum']+1]);
		if($now_price <= $product['min_price']){
			Db::name('kanjia_join')->where('aid',aid)->where('id',$joinid)->update(['status'=>1,'endtime'=>time()]);
			Db::name('kanjia_product')->where('id',$product['id'])->update(['sales'=>Db::raw('sales+1'),'saleing'=>Db::raw('saleing-1'),'stock'=>Db::raw('stock-1')]);
			if($product['stock']-1 <= 0){//抢光了
				Db::name('kanjia_join')->where('aid',aid)->where('proid',$product['id'])->where('status',0)->update(['status'=>2]);
			}
		}
		$helpdata = [];
		$helpdata['aid'] = aid;
		$helpdata['mid'] = mid;
		$helpdata['bid'] = $product['bid'];
		$helpdata['helpmid'] = $join_info['mid'];
		$helpdata['joinid'] = $join_info['id'];
		$helpdata['cut_price'] = $cutmoney;
		$helpdata['after_price'] = $now_price;
		$helpdata['proid'] = $product['id'];
		$helpdata['createtime'] = time();
		Db::name('kanjia_help')->insert($helpdata);
		$joininfo = Db::name('kanjia_join')->where('aid',aid)->where('id',$joinid)->find();
		$joininfo['yikan_price'] = round($product['sell_price']*1 - $joininfo['now_price']*1,2);

		$helpdata['givescore'] = 0;
		$helpdata['givemoney'] = 0;
		if($cutmoney > 0 && $product['helpgive_percent'] > 0){ //帮砍得积分或余额
			if($product['helpgive_type'] == 0){ //积分
				$givescore = intval($cutmoney * $product['helpgive_percent'] * 0.01);
				if($givescore > 0){
					if($product['helpgive_ff'] == 0) \app\commons\Member::addscore(aid,mid,$givescore,'帮好友砍价奖励');
					$helpdata['givescore'] = $givescore;
				}
			}
			if($product['helpgive_type'] == 1){ //余额
				$givemoney = round($cutmoney * $product['helpgive_percent'] * 0.01,2);
				if($givemoney > 0){
					if($product['helpgive_ff'] == 0) \app\commons\Member::addmoney(aid,mid,$givemoney,'帮好友砍价奖励');
					$helpdata['givemoney'] = $givemoney;
				}
			}
		}
		return $this->json(['status'=>1,'helpinfo'=>$helpdata,'joininfo'=>$joininfo]);
	}

	function buy(){
		$this->checklogin();
		$joinid = input('param.joinid/d');
		$joininfo = Db::name('kanjia_join')->where('aid',aid)->where('id',$joinid)->find();
		if(!$joininfo) $this->json(['status'=>0,'msg'=>'砍价记录不存在']);
		if($joininfo['status']==2) $this->json(['status'=>0,'msg'=>'砍价已失败']);
		if($joininfo['isbuy']==1) $this->json(['status'=>0,'msg'=>'该砍价已下单过了']);
		$product = Db::name('kanjia_product')->where('aid',aid)->where('status',1)->where('ischecked',1)->where('id',$joininfo['proid'])->find();
		if(!$product){
			return $this->json(['status'=>0,'msg'=>'产品不存在或已下架']);
		}
		if($product['directbuy']==0 && $joininfo['now_price']>$product['min_price']){
			return $this->json(['status'=>0,'msg'=>'砍到底价后才能购买']);
		}

		$bid = $product['bid'];

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
		$rs = \app\models\Freight::formatFreightList($freightList,$address,$joininfo['now_price'],1,$product['weight']);
		$freightList = $rs['freightList'];
		$freightArr = $rs['freightArr'];
		if($rs['needLocation']==1) $needLocation = 1;

		//$userlevel = Db::name('member_level')->where('aid',aid)->where('id',$this->member['levelid'])->find();
		$adminset = Db::name('admin_set')->where('aid',aid)->find();
		$userinfo = [];
		//$userinfo['discount'] = $userlevel['discount'];
		$userinfo['score'] = $this->member['score'];
		$userinfo['score2money'] = $adminset['score2money'];
		$userinfo['scoredk_money'] = round($userinfo['score'] * $userinfo['score2money'],2);
		$userinfo['scoredkmaxpercent'] = $adminset['scoredkmaxpercent'];
		$userinfo['realname'] = $this->member['realname'];
		$userinfo['tel'] = $this->member['tel'];

		$rdata = [];
		$rdata['havetongcheng'] = $havetongcheng;
		$rdata['status'] = 1;
		$rdata['product'] = $product;
		$rdata['freightList'] = $freightList;
		$rdata['freightArr'] = $freightArr;
		$rdata['userinfo'] = $userinfo;
		$rdata['joininfo'] = $joininfo;
		$rdata['address'] = $address;
		$rdata['linkman'] = $address ? $address['name'] : strval($userinfo['realname']);
		$rdata['tel'] = $address ? $address['tel'] : strval($userinfo['tel']);
		if(!$rdata['linkman']){
			$lastorder = Db::name('kanjia_order')->where('aid',aid)->where('mid',mid)->where('linkman','<>','')->find();
			if($lastorder){
				$rdata['linkman'] = $lastorder['linkman'];
				$rdata['tel'] = $lastorder['tel'];
			}
		}
		$rdata['needLocation'] = $needLocation;
		$rdata['scorebdkyf'] = Db::name('admin_set')->where('aid',aid)->value('scorebdkyf');
		return $this->json($rdata);
	}
	function createOrder(){
		$this->checklogin();
		$post = input('post.');
		$joinid = $post['joinid'];
		$joininfo = Db::name('kanjia_join')->where('aid',aid)->where('id',$joinid)->find();
		if(!$joininfo) return $this->json(['status'=>0,'msg'=>'砍价记录不存在']);
		if($joininfo['status']==2) return $this->json(['status'=>0,'msg'=>'砍价已失败']);
		if($joininfo['isbuy']==1) return $this->json(['status'=>0,'msg'=>'该砍价已下单过了']);
		$productList = [];
		$product = Db::name('kanjia_product')->where('aid',aid)->where('status',1)->where('ischecked',1)->where('id',$joininfo['proid'])->find();
		if(!$product){
			return $this->json(['status'=>0,'msg'=>'产品不存在或已下架']);
		}
		if($product['directbuy']==0 && $joininfo['now_price']>$product['min_price']){
			return $this->json(['status'=>0,'msg'=>'砍到底价后才能购买']);
		}
		if($joininfo['status']==0 && $product['stock']<=0){
			return $this->json(['status'=>0,'msg'=>'库存不足']);
		}

		$product_price = 0;
		$weight = 0;//重量
		$goodsnum = 1;

		$product_price += $joininfo['now_price'];

		$weight = $product['weight'] * 1;

		$totalprice = $product_price;
		if($totalprice<0) $totalprice = 0;

		//收货地址
		if($post['addressid']=='' || $post['addressid']==0){
			$address = ['id'=>0,'name'=>$post['linkman'],'tel'=>$post['tel'],'area'=>'','address'=>''];
		}else{
			$address = Db::name('member_address')->where('id',$post['addressid'])->where('aid',aid)->where('mid',mid)->find();
		}

		//运费
		$freight_price = 0;
		if($post['freightid']){
			$freight = Db::name('freight')->where('aid',aid)->where('id',$post['freightid'])->find();
			if(($address['name']=='' || $address['tel'] =='') && ($freight['pstype']==1 || $freight['pstype']==3) && $freight['needlinkinfo']==1){
				return $this->json(['status'=>0,'msg'=>'请填写联系人和联系电话']);
			}

			$rs = \app\models\Freight::getFreightPrice($freight,$address,$product_price,1,$weight);
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
		$orderdata['mid'] = mid;
		$orderdata['bid'] = $product['bid'];
		$ordernum = date('ymdHis').aid.rand(1000,9999);
		$orderdata['ordernum'] = $ordernum;
		$orderdata['title'] = removeEmoj($product['name']);
		$orderdata['proid'] = $product['id'];
		$orderdata['proname'] = $product['name'];
		$orderdata['propic'] = $product['pic'];

		$orderdata['linkman'] = $address['name'];
		$orderdata['tel'] = $address['tel'];
		$orderdata['area'] = $address['area'];
		$orderdata['area2'] = $address['province'].','.$address['city'].','.$address['district'];
		$orderdata['address'] = $address['address'];
		$orderdata['longitude'] = $address['longitude'];
		$orderdata['latitude'] = $address['latitude'];
		$orderdata['totalprice'] = $totalprice;
		$orderdata['product_price'] = $product_price;
		$orderdata['sell_price'] = $product['sell_price'];
		$orderdata['cost_price'] = $product['cost_price'];
		$orderdata['freight_price'] = $freight_price; //运费
		$orderdata['scoredk_money'] = $scoredk_money;	//积分抵扣
		$orderdata['scoredkscore'] = $scoredkscore;	//抵扣掉的积分
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
		$orderdata['platform'] = platform;
		$orderdata['hexiao_code'] = random(16);
		$orderdata['hexiao_qr'] = createqrcode(m_url('admin/hexiao/hexiao?type=kanjia&co='.$orderdata['hexiao_code']));
		$orderdata['joinid'] = $joininfo['id'];

        if($product['bid'] > 0) {
			$bset = Db::name('business_sysset')->where('aid',aid)->find();
			$scoredkmoney = $scoredk_money ?? 0;
			if($bset['scoredk_kouchu'] == 0){ //扣除积分抵扣
				$scoredkmoney = 0;
			}
            $business_feepercent = Db::name('business')->where('aid',aid)->where('id',$product['bid'])->value('feepercent');
            $totalprice_business = $product_price + $freight_price;
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

        //计算佣金的商品金额
		//$commission_totalprice = $orderdata['totalprice'];
		$commission_totalprice = $product_price;
		//算佣金
		$sysset = Db::name('admin_set')->where('aid',aid)->find();
		if($sysset['fxjiesuantype'] == 1 || $sysset['fxjiesuantype'] == 2){
            $commission_totalprice = $product_price - $scoredk_money;
		}

		$agleveldata = Db::name('member_level')->where('aid',aid)->where('id',$this->member['levelid'])->find();
		if($agleveldata['can_agent'] > 0 && $agleveldata['commission1own']==1){
			$this->member['pid'] = mid;
		}

		if($product['commissionset']!=-1){
			if($this->member['pid']){
				$parent1 = Db::name('member')->where('aid',aid)->where('id',$this->member['pid'])->find();
				if($parent1){
					$agleveldata1 = Db::name('member_level')->where('aid',aid)->where('id',$parent1['levelid'])->find();
					if($agleveldata1['can_agent']!=0){
						$orderdata['parent1'] = $parent1['id'];
					}
				}
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
			if($product['commissionset']==1){//按商品设置的分销比例
				$commissiondata = json_decode($product['commissiondata1'],true);
				if($commissiondata){
					if($agleveldata1) $orderdata['parent1commission'] = $commissiondata[$agleveldata1['id']]['commission1'] * $commission_totalprice * 0.01;
					if($agleveldata2) $orderdata['parent2commission'] = $commissiondata[$agleveldata2['id']]['commission2'] * $commission_totalprice * 0.01;
					if($agleveldata3) $orderdata['parent3commission'] = $commissiondata[$agleveldata3['id']]['commission3'] * $commission_totalprice * 0.01;
				}
			}elseif($product['commissionset']==2){//按固定金额
				$commissiondata = json_decode($product['commissiondata2'],true);
				if($commissiondata){
					if($agleveldata1) $orderdata['parent1commission'] = $commissiondata[$agleveldata1['id']]['commission1'];
					if($agleveldata2) $orderdata['parent2commission'] = $commissiondata[$agleveldata2['id']]['commission2'];
					if($agleveldata3) $orderdata['parent3commission'] = $commissiondata[$agleveldata3['id']]['commission3'];
				}
			}elseif($product['commissionset']==3){//提成是积分
				$commissiondata = json_decode($product['commissiondata3'],true);
				if($commissiondata){
					if($agleveldata1) $orderdata['parent1score'] = $commissiondata[$agleveldata1['id']]['commission1'];
					if($agleveldata2) $orderdata['parent2score'] = $commissiondata[$agleveldata2['id']]['commission2'];
					if($agleveldata3) $orderdata['parent3score'] = $commissiondata[$agleveldata3['id']]['commission3'];
				}
			}else{ //按会员等级设置的分销比例
				if($agleveldata1){
					if($agleveldata1['commissiontype']==1){ //固定金额按单
						$orderdata['parent1commission'] = $agleveldata1['commission1'];
					}else{
						$orderdata['parent1commission'] = $agleveldata1['commission1'] * $commission_totalprice * 0.01;
					}
				}
				if($agleveldata2){
					if($agleveldata2['commissiontype']==1){
						$orderdata['parent2commission'] = $agleveldata2['commission2'];
					}else{
						$orderdata['parent2commission'] = $agleveldata2['commission2'] * $commission_totalprice * 0.01;
					}
				}
				if($agleveldata3){
					if($agleveldata3['commissiontype']==1){
						$orderdata['parent3commission'] = $agleveldata3['commission3'];
					}else{
						$orderdata['parent3commission'] = $agleveldata3['commission3'] * $commission_totalprice * 0.01;
					}
				}
			}
		}

		$orderid = Db::name('kanjia_order')->insertGetId($orderdata);
		\app\models\Freight::saveformdata($orderid,'kanjia_order',$freight['id'],$post['formdata']);

		if($orderdata['parent1'] && ($orderdata['parent1commission'] || $orderdata['parent1score'])){
			Db::name('member_commission_record')->insert(['aid'=>aid,'mid'=>$orderdata['parent1'],'frommid'=>mid,'orderid'=>$orderid,'ogid'=>$product['id'],'type'=>'kanjia','commission'=>$orderdata['parent1commission'],'score'=>$orderdata['parent1score'],'remark'=>'下级购买商品奖励','createtime'=>time()]);
		}
		if($orderdata['parent2'] && ($orderdata['parent2commission'] || $orderdata['parent2score'])){
			Db::name('member_commission_record')->insert(['aid'=>aid,'mid'=>$orderdata['parent2'],'frommid'=>mid,'orderid'=>$orderid,'ogid'=>$product['id'],'type'=>'kanjia','commission'=>$orderdata['parent2commission'],'score'=>$orderdata['parent2score'],'remark'=>'下二级购买商品奖励','createtime'=>time()]);
		}
		if($orderdata['parent3'] && ($orderdata['parent3commission'] || $orderdata['parent3score'])){
			Db::name('member_commission_record')->insert(['aid'=>aid,'mid'=>$orderdata['parent3'],'frommid'=>mid,'orderid'=>$orderid,'ogid'=>$product['id'],'type'=>'kanjia','commission'=>$orderdata['parent3commission'],'score'=>$orderdata['parent3score'],'remark'=>'下三级购买商品奖励','createtime'=>time()]);
		}

		$payorderid = \app\models\Payorder::createorder(aid,$orderdata['bid'],$orderdata['mid'],'kanjia',$orderid,$ordernum,$orderdata['title'],$orderdata['totalprice'],$orderdata['scoredkscore']);
		if($joininfo['status']==0){
			Db::name('kanjia_join')->where('id',$joininfo['id'])->update(['status'=>1,'endtime'=>time(),'isbuy'=>1]);
			//减库存加销量
			Db::name('kanjia_product')->where('aid',aid)->where('id',$product['id'])->update(['stock'=>$product['stock'] -1,'sales'=>$product['sales'] +1]);
		}else{
			Db::name('kanjia_join')->where('id',$joininfo['id'])->update(['isbuy'=>1]);
		}

        $store_name = Db::name('admin_set')->where('aid',aid)->value('name');
		//公众号通知 订单提交成功
		$tmplcontent = [];
		$tmplcontent['first'] = '有新砍价订单提交成功';
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
		\app\commons\Wechat::sendhttmpl(aid,$orderdata['bid'],'tmpl_orderconfirm',$tmplcontent,m_url('admin/order/kanjiaorder'),$orderdata['mdid'],$tempconNew);

		$tmplcontent = [];
		$tmplcontent['thing11'] = $orderdata['title'];
		$tmplcontent['character_string2'] = $orderdata['ordernum'];
		$tmplcontent['phrase10'] = '待付款';
		$tmplcontent['amount13'] = $orderdata['totalprice'].'元';
		$tmplcontent['thing27'] = $this->member['nickname'];
		\app\commons\Wechat::sendhtwxtmpl(aid,$orderdata['bid'],'tmpl_orderconfirm',$tmplcontent,'admin/order/kanjiaorder',$orderdata['mdid']);

		return $this->json(['status'=>1,'orderid'=>$orderid,'payorderid'=>$payorderid,'msg'=>'提交成功']);
	}

	function getOrderCount(){
		$this->checklogin();
		$count0 = 0 + Db::name('kanjia_order')->where('aid',aid)->where('mid',mid)->where('status',0)->count();
		$count1 = 0 + Db::name('kanjia_order')->where('aid',aid)->where('mid',mid)->where('status',1)->count();
		$count2 = 0 + Db::name('kanjia_order')->where('aid',aid)->where('mid',mid)->where('status',2)->count();
		$count4 = 0 + Db::name('kanjia_order')->where('aid',aid)->where('mid',mid)->where('status',4)->count();
		return $this->json(['status'=>1,'count0'=>$count0,'count1'=>$count1,'count2'=>$count2,'count4'=>$count4]);
	}
	function orderlist(){
		$this->checklogin();
		$st = input('param.st');
		if(!$st && $st!=='0') $st = 'all';
		$pagenum = input('param.pagenum') ? input('param.pagenum') : 1;
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['mid','=',mid];
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

		$datalist = Db::name('kanjia_order')->where($where)->order('id desc')->page($pagenum,10)->select()->toArray();
		if(!$datalist) $datalist = [];
		foreach($datalist as $key=>$v){
			if($v['bid']!=0){
				$datalist[$key]['binfo'] = Db::name('business')->where('aid',aid)->where('id',$v['bid'])->field('id,name,logo')->find();
			}
		}
		$rdata = [];
		$rdata['st'] = $st;
		$rdata['datalist'] = $datalist;
		return $this->json($rdata);
	}
	function orderdetail(){
		$this->checklogin();
		$id = input('param.id/d');
		$detail = Db::name('kanjia_order')->where('id',$id)->find();
		if(!$detail) return $this->json(['status'=>0,'msg'=>'订单不存在']);

		$detail['createtime'] = $detail['createtime'] ? date('Y-m-d H:i:s',$detail['createtime']) : '';
		$detail['collect_time'] = $detail['collect_time'] ? date('Y-m-d H:i:s',$detail['collect_time']) : '';
		$detail['paytime'] = $detail['paytime'] ? date('Y-m-d H:i:s',$detail['paytime']) : '';
		$detail['refund_time'] = $detail['refund_time'] ? date('Y-m-d H:i:s',$detail['refund_time']) : '';
		$detail['send_time'] = $detail['send_time'] ? date('Y-m-d H:i:s',$detail['send_time']) : '';
		$detail['formdata'] = \app\models\Freight::getformdata($detail['id'],'kanjia_order');

		$storeinfo = [];
		if($detail['freight_type'] == 1){
            $storeinfo = Db::name('mendian')->where('id',$detail['mdid'])->field('id,name,address,longitude,latitude')->find();
		}
		if($detail['bid'] > 0) {
		    $binfo = Db::name('business')->where('id',$detail['bid'])->field('id,name,logo')->find();
        }
		$shopset = Db::name('kanjia_sysset')->where('aid',aid)->find();
		$rdata = [];
		$rdata['status'] = 1;
		$rdata['detail'] = $detail;
		$rdata['shopset'] = $shopset;
		$rdata['storeinfo'] = $storeinfo;
        $rdata['binfo'] = $binfo;
		return $this->json($rdata);
	}
	function orderCollect(){ //确认收货
		$this->checklogin();
		$post = input('post.');
		$orderid = intval($post['orderid']);
		$order = Db::name('kanjia_order')->where('aid',aid)->where('mid',mid)->where('id',$orderid)->find();
		if(!$order || ($order['status']!=2)){
			return $this->json(['status'=>0,'msg'=>'订单状态不符合收货要求']);
		}

		$rs = \app\commons\Order::collect($order,'kanjia');
		if($rs['status'] == 0) return $this->json($rs);

		Db::name('kanjia_order')->where('aid',aid)->where('mid',mid)->where('id',$orderid)->update(['status'=>3,'collect_time'=>time()]);
		\app\commons\Member::uplv(aid,mid);

		$tmplcontent = [];
		$tmplcontent['first'] = '有砍价订单客户已确认收货';
		$tmplcontent['remark'] = '点击进入查看~';
		$tmplcontent['keyword1'] = $this->member['nickname'];
		$tmplcontent['keyword2'] = $order['ordernum'];
		$tmplcontent['keyword3'] = $order['totalprice'].'元';
		$tmplcontent['keyword4'] = date('Y-m-d H:i',$order['paytime']);
        $tmplcontentNew = [];
        $tmplcontentNew['thing3'] = $this->member['nickname'];//收货人
        $tmplcontentNew['character_string7'] = $order['ordernum'];//订单号
        $tmplcontentNew['time8'] = date('Y-m-d H:i');//送达时间
		\app\commons\Wechat::sendhttmpl(aid,$order['bid'],'tmpl_ordershouhuo',$tmplcontent,m_url('admin/order/kanjiaorder'),$order['mdid'],$tmplcontentNew);

		$tmplcontent = [];
		$tmplcontent['thing2'] = $order['title'];
		$tmplcontent['character_string6'] = $order['ordernum'];
		$tmplcontent['thing3'] = $this->member['nickname'];
		$tmplcontent['date5'] = date('Y-m-d H:i');
		\app\commons\Wechat::sendhtwxtmpl(aid,$order['bid'],'tmpl_ordershouhuo',$tmplcontent,'admin/order/kanjiaorder',$order['mdid']);

		return $this->json(['status'=>1,'msg'=>'确认收货成功']);
	}
	function closeOrder(){
		$this->checklogin();
		$post = input('post.');
		$orderid = intval($post['orderid']);
		$order = Db::name('kanjia_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->find();
		if(!$order || $order['status']!=0){
			return $this->json(['status'=>0,'msg'=>'关闭失败,订单状态错误']);
		}else{
			//加库存
			Db::name('kanjia_product')->where('aid',aid)->where('id',$order['proid'])->update(['stock'=>Db::raw("stock+1"),'sales'=>Db::raw("sales-1")]);

			$rs = Db::name('kanjia_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->update(['status'=>4]);
			return $this->json(['status'=>1,'msg'=>'操作成功','url'=>true]);
		}
	}
	function delOrder(){
		$this->checklogin();
		$post = input('post.');
		$orderid = intval($post['orderid']);
		$order = Db::name('kanjia_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->find();
		if(!$order || $order['status']!=4){
			return $this->json(['status'=>0,'msg'=>'删除失败,订单状态错误']);
		}else{
			$rs = Db::name('kanjia_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->delete();
			return $this->json(['status'=>1,'msg'=>'删除成功']);
		}
	}
	function refund(){//申请退款
		$this->checklogin();
		if(request()->isPost()){
			$post = input('post.');
			$orderid = intval($post['orderid']);
			$money = floatval($post['money']);
			$order = Db::name('kanjia_order')->where('aid',aid)->where('mid',mid)->where('id',$orderid)->find();
			if(!$order || ($order['status']!=1 && $order['status'] != 2) || $order['refund_status'] == 2){
				return $this->json(['status'=>0,'msg'=>'订单状态不符合退款要求']);
			}
			if($money < 0 || $money > $order['totalprice']){
				return $this->json(['status'=>0,'msg'=>'退款金额有误']);
			}
			Db::name('kanjia_order')->where('aid',aid)->where('mid',mid)->where('id',$orderid)->update(['refund_time'=>time(),'refund_status'=>1,'refund_reason'=>$post['reason'],'refund_money'=>$money]);

			$tmplcontent = [];
			$tmplcontent['first'] = '有砍价订单客户申请退款';
			$tmplcontent['remark'] = '点击进入查看~';
			$tmplcontent['keyword1'] = $order['ordernum'];
			$tmplcontent['keyword2'] = $money.'元';
			$tmplcontent['keyword3'] = $post['reason'];
            $tmplcontentNew = [];
            $tmplcontentNew['number2'] = $order['ordernum'];//订单号
            $tmplcontentNew['amount4'] = $money;//退款金额
			\app\commons\Wechat::sendhttmpl(aid,$order['bid'],'tmpl_ordertui',$tmplcontent,m_url('admin/order/kanjiaorder'),$order['mdid'],$tmplcontentNew);

			$tmplcontent = [];
			$tmplcontent['thing1'] = $order['title'];
			$tmplcontent['character_string4'] = $order['ordernum'];
			$tmplcontent['amount2'] = $order['totalprice'];
			$tmplcontent['amount9'] = $money.'元';
			$tmplcontent['thing10'] = $post['reason'];
			\app\commons\Wechat::sendhtwxtmpl(aid,$order['bid'],'tmpl_ordertui',$tmplcontent,'admin/order/kanjiaorder',$order['mdid']);

			return $this->json(['status'=>1,'msg'=>'提交成功,请等待商家审核']);
		}
		$rdata = [];
		$rdata['price'] = input('param.price/f');
		$rdata['orderid'] = input('param.orderid/d');
		$order = Db::name('kanjia_order')->where('aid',aid)->where('mid',mid)->where('id',$rdata['orderid'])->find();
		$rdata['price'] = $order['totalprice'];
		return $this->json($rdata);
	}

	//商品海报
	function getposter(){
		$this->checklogin();
		$post = input('post.');
		$platform = platform;
		$page = '/activity/kanjia/product';
		$scene = 'id_'.$post['proid'].'-pid_'.$this->member['id'];
		//if($platform == 'mp' || $platform == 'h5' || $platform == 'app'){
		//	$page = PRE_URL .'/h5/'.aid.'.html#'. $page;
		//}
		$posterset = Db::name('admin_set_poster')->where('aid',aid)->where('type','kanjia')->where('platform',$platform)->order('id')->find();

		$posterdata = Db::name('member_poster')->where('aid',aid)->where('mid',mid)->where('scene',$scene)->where('type','kanjia')->where('posterid',$posterset['id'])->find();
		if(!$posterdata){
			$product = Db::name('kanjia_product')->where('id',$post['proid'])->find();
			$sysset = Db::name('admin_set')->where('aid',aid)->find();
			$textReplaceArr = [
				'[头像]'=>$this->member['headimg'],
				'[昵称]'=>$this->member['nickname'],
				'[姓名]'=>$this->member['realname'],
				'[手机号]'=>$this->member['mobile'],
				'[商城名称]'=>$sysset['name'],
				'[商品名称]'=>$product['name'],
				'[商品销售价]'=>$product['min_price'],
				'[商品市场价]'=>$product['sell_price'],
				'[商品图片]'=>$product['pic'],
			];

			$poster = $this->_getposter(aid,$product['bid'],$platform,$posterset['content'],$page,$scene,$textReplaceArr);
			$posterdata = [];
			$posterdata['aid'] = aid;
			$posterdata['mid'] = $this->member['id'];
			$posterdata['scene'] = $scene;
			$posterdata['page'] = $page;
			$posterdata['type'] = 'kanjia';
			$posterdata['poster'] = $poster;
			$posterdata['createtime'] = time();
			Db::name('member_poster')->insert($posterdata);
		}
		return $this->json(['status'=>1,'poster'=>$posterdata['poster']]);
	}
	function getJoinPoster(){ //参团海报
		$this->checklogin();
		$post = input('post.');
		$platform = platform;
		$page = '/activity/kanjia/join';
		$scene = 'joinid_'.$post['joinid'].'-pid_'.$this->member['id'];
		//if($platform == 'mp' || $platform == 'h5' || $platform == 'app'){
		//	$page = PRE_URL .'/h5/'.aid.'.html#'. $page;
		//}
		$posterset = Db::name('admin_set_poster')->where('aid',aid)->where('type','kanjiajoin')->where('platform',$platform)->order('id')->find();

		$posterdata = Db::name('member_poster')->where('aid',aid)->where('mid',mid)->where('scene',$scene)->where('type','kanjiajoin')->where('posterid',$posterset['id'])->find();
		if(!$posterdata){
			$product = Db::name('kanjia_product')->where('id',$post['proid'])->find();
			$sysset = Db::name('admin_set')->where('aid',aid)->find();
			$textReplaceArr = [
				'[头像]'=>$this->member['headimg'],
				'[昵称]'=>$this->member['nickname'],
				'[姓名]'=>$this->member['realname'],
				'[手机号]'=>$this->member['mobile'],
				'[商城名称]'=>$sysset['name'],
				'[商品名称]'=>$product['name'],
				'[商品销售价]'=>$product['min_price'],
				'[商品市场价]'=>$product['sell_price'],
				'[商品图片]'=>$product['pic'],
			];

			$poster = $this->_getposter(aid,$product['bid'],$platform,$posterset['content'],$page,$scene,$textReplaceArr);
			$posterdata = [];
			$posterdata['aid'] = aid;
			$posterdata['mid'] = $this->member['id'];
			$posterdata['scene'] = $scene;
			$posterdata['page'] = $page;
			$posterdata['type'] = 'kanjiajoin';
			$posterdata['poster'] = $poster;
			$posterdata['createtime'] = time();
			Db::name('member_poster')->insert($posterdata);
		}
		return $this->json(['status'=>1,'poster'=>$posterdata['poster']]);
	}
	//分享
	public function share(){
		$proid = input('param.proid/d');
		$product = Db::name('kanjia_product')->where('aid',aid)->where('id',$proid)->find();
		if(!$product || $product['status']==0 || $product['shareaddnum'] == 0) return $this->json(['status'=>0,'msg'=>'活动不存在']);
		if($product['shareaddnum'] <= 0) return $this->json(['status'=>2,'msg'=>'']);
		$sharelog = Db::name('kanjia_sharelog')->where('aid',aid)->where('proid',$proid)->where('mid',mid)->find();
		if($sharelog){
			if($sharelog['sharecounttimes'] > 0) return $this->json(['status'=>2,'msg'=>'']);
			$update = [];
			$update['updatetime'] = date('Y-m-d');
			$update['sharedaytimes'] = 1;
			$update['adddaytimes'] = 1;
			$update['addtimes'] = $sharelog['addtimes'] + $product['perhelpnum_shareadd'];
			$update['sharecounttimes'] = $sharelog['sharecounttimes'] + 1;
			Db::name('kanjia_sharelog')->where('id',$sharelog['id'])->update($update);
		}else{
			$data = [];
			$data['aid'] = aid;
			$data['proid'] = $proid;
			$data['mid'] = mid;
			$data['updatetime'] = date('Y-m-d');
			$data['sharedaytimes'] = 1;
			$data['addtimes'] = $product['shareaddnum'];
			$data['adddaytimes'] = $product['shareaddnum'];
			Db::name('kanjia_sharelog')->insert($data);
		}
		return $this->json(['status'=>1,'msg'=>'']);
	}
}