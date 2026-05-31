<?php


// +----------------------------------------------------------------------
// | test
// +----------------------------------------------------------------------
namespace app\controllers;
use app\BaseController;
use think\facade\View;
use think\facade\Db;

class Test extends BaseController
{
	public function index(){
// 		$addressrs = getaddressfromtel(input('param.tel'));
// 		var_dump($addressrs);
// 		die;
// 		$aid = 1;
// 		$mid = input('param.id/d');
// 		$member = Db::name('member')->where('id',$mid)->find();
// 		$fhlevel = Db::name('member_level')->where('id',$member['levelid'])->find();
// 		$levelids = Db::name('member_level')->where('aid',$aid)->where('sort','<',$fhlevel['sort'])->column('id');
// 		var_dump($levelids);
// 		$downmids = \app\commons\Member::getteammids($aid,$mid,999,$levelids);
// 		var_dump($downmids);
// 		die;
// 		define('aid',1);
// 		$rs = \app\commons\Wxvideo::uploadimg('https://image.wxx1.com/upload/1/20211224/084ef5e5f565921eab8feff736fc4c44.jpg');
// 		var_dump($rs);
// 		die;
// 		var_dump(\app\commons\Sms::send(1,15610673660,'tmpl_checksuccess',[]));
// 		die;
		 $sysset = Db::name('admin_set')->where('aid',4)->find();
		 
	ll($sysset,'auto_day');
	
		for ($i = 0; $i < 5; $i++) {
		    $tel='1380013800'.$i;
		   	$data = [];
    		$data['aid'] =4;
    		$data['acc'] = $tel;
    		$data['tel'] = $tel;
    		$data['pwd'] = md5(123456);
    		$data['nickname'] = substr($tel,0,3).'****'.substr($tel,-4);
    		$data['sex'] = 3;
    		$data['levelid'] = 1;
    		$data['money'] = 20000;
    		$data['credit1'] = 0;
    		$data['headimg'] = $sysset['headimg']? $sysset['headimg']:PRE_URL.'/static/img/touxiang.png';;
    		$data['createtime'] = time();
    		$data['platform'] = '';
    		$mid = \app\models\Member::add(4,$data);
    		
    		$data = [];
            $data['aid'] = 4;
            $data['mid'] = $mid;
            $data['name'] = $tel;
            $data['tel'] = $tel;
            
            $data['province'] = '北京市';
            $data['city'] = '北京市';
            $data['district'] = '东城区';
            $data['area'] = '北京市北京市东城区';
            $data['address'] = '乡街道';
            $data['isdefault'] = 1;
            $data['createtime'] = time();
            Db::name('member_address')->insert($data);
    		
		
		}
		
		die('success');
		
		
		
		
		
		
		
	
		
		
		
		
		
		
		
		
		
		
		
		
// 		$member['id'] = '';
// 		$member['tel'] = random(10);
// 		Db::name('member')->insert($member);//die;
// 		$member['tel'] = random(10);
// 		Db::name('member')->insert($member);
// 		$member['tel'] = random(10);
// 		Db::name('member')->insert($member);
// 		$member['tel'] = random(10);
// 		Db::name('member')->insert($member);
// 		$member['tel'] = random(10);
// 		Db::name('member')->insert($member);
// 		$member['tel'] = random(10);
// 		Db::name('member')->insert($member);
// 		$member['tel'] = random(10);
	
// 		die('success');

// 		die;
// 		$order = Db::name('shop_order')->where('aid',2)->order('id desc')->where('status','in','1,2,3')->find();
// 		$rs = \app\customs\Cefang::api($order);
// 		var_dump($rs);
// 		die;
// 		$lvlist = Db::name('member_level')->field('up_fxordermoney')->where('aid',1)->order('sort,id')->select()->toArray();
// 		var_dump($lvlist);
// 		foreach($lvlist as $k=>$v){
// 			var_dump($v['up_fxordermoney']);
// 			if($v['up_fxordermoney'] > 0){
// 				var_dump('true');
// 			}else{
// 				var_dump('false');
// 			}
// 		}

// 		\app\commons\Member::uplv(1,164);

// 		die;

// 		$memberlist = Db::name('member')->where('aid',2)->where("tel is null or tel=''")->order('id')->select()->toArray();

// 		foreach($memberlist as $member){
// 			$order = Db::name('member_levelup_order')->where('aid',2)->where('mid',$member['id'])->find();
// 			if($order['form0'] || $order['form1']){
// 				Db::name('member')->where('id',$member['id'])->update(['tel'=>explode('^_^',$order['form1'])[1]]);
// 			}
// 		}

		
// 		$memberlist = Db::name('member')->where('aid',2)->where("realname is null or realname=''")->order('id')->select()->toArray();

// 		foreach($memberlist as $member){
// 			$order = Db::name('member_levelup_order')->where('aid',2)->where('mid',$member['id'])->find();
// 			if($order['form0']){
// 				Db::name('member')->where('id',$member['id'])->update(['realname'=>explode('^_^',$order['form0'])[1]]);
// 			}
// 		}



// 		die;
// 		$customArr = [];
// 		$customArr[] = 'restaurant';
// 		$customArr[] = 'dc';
// 		$ss = var_export($customArr,true);
// 		var_dump($ss);die;

// 		$pagesjson = file_get_contents(ROOT_PATH.'/uniapp/pages.json');
// 		$pagesjson = preg_replace('/\/\/ #custom if restaurant.*\/\/ #custom endif/Us','',$pagesjson);
// 		var_dump($pagesjson);

// 		die;
// 		require_once ROOT_PATH.'extend/WebsocketClient.php';
// 		$config = include(ROOT_PATH.'config.php');
// 		$socket = new \WebsocketClient('127.0.0.1',$config['kfport']);
// 		$socket->send(json_encode(['type'=>'test']));
	}

	//会员path
	public function updatepath(){
		$memberlist = Db::name('member')->where('aid',2)->order('id')->select()->toArray();
		foreach($memberlist as $member){
			$pathArr = $this->getpath($member,$path=[]);
			if($pathArr){
				$pathArr = array_reverse($pathArr);
				$pathstr = implode(',',$pathArr);
				Db::name('member')->where('aid',2)->where('id',$member['id'])->update(['path'=>$pathstr]);
				var_dump($member['id'].'----'.$pathstr);
			}
		}
	}
	public function getpath($member,$path){
		if($member['pid']){
			$path[] = $member['pid'];
			$parent = Db::name('member')->where('id',$member['pid'])->find();
			return $this->getpath($parent,$path);
		}
		return $path;
	}

	//会员
	public function memberdaoru(){
		die;
		$memberlist = Db::name('member2')->where('aid',10)->select()->toArray();
		$aid = 2;
		foreach($memberlist as $member){
			if($member['levelid'] == 40) $member['levelid'] = 3;
			if($member['levelid'] == 61) $member['levelid'] = 4;
			if($member['levelid'] == 62) $member['levelid'] = 5;
			if($member['levelid'] == 63) $member['levelid'] = 6;
			$newdata = [];
			$newdata['id'] = $member['id'];
			$newdata['aid'] = $aid;
			$newdata['wxopenid'] = $member['wxopenid'];
			$newdata['mpopenid'] = $member['mpopenid'];
			$newdata['unionid'] = $member['unionid'];
			$newdata['pid'] = $member['pid'];
			$newdata['path'] = $member['path'];
			$newdata['levelid'] = $member['levelid'];
			$newdata['money'] = $member['money'];
			$newdata['totalcommission'] = $member['totalcommission'];
			$newdata['commission'] = $member['commission'];
			$newdata['score'] = $member['score'];
			$newdata['nickname'] = $member['nickname'];
			$newdata['headimg'] = $member['headimg'];
			$newdata['sex'] = $member['sex'];
			$newdata['realname'] = $member['realname'];
			$newdata['tel'] = $member['tel'];
			$newdata['usercard'] = $member['usercard'];
			$newdata['weixin'] = $member['weixin'];
			$newdata['aliaccount'] = $member['aliaccount'];
			$newdata['country'] = $member['country'];
			$newdata['province'] = $member['province'];
			$newdata['city'] = $member['city'];
			$newdata['area'] = $member['area'];
			$newdata['address'] = $member['address'];
			$newdata['birthday'] = $member['birthday'];
			$newdata['bankcardnum'] = $member['bankcardnum'];
			$newdata['bankname'] = $member['bankname'];
			$newdata['bankcarduser'] = $member['bankcarduser'];
			$newdata['card_id'] = $member['card_id'];
			$newdata['card_code'] = $member['card_code'];
			$newdata['activate_ticket'] = $member['activate_ticket'];
			$newdata['qrcode'] = $member['qrcode'];
			$newdata['sharepic'] = $member['sharepic'];
			$newdata['shareMediaId'] = $member['shareMediaId'];
			$newdata['createtime'] = $member['createtime'];
			$newdata['signdate'] = $member['signdate'];
			$newdata['signtime'] = $member['signtime'];
			$newdata['signtimes'] = $member['signtimes'];
			$newdata['signtimeslx'] = $member['signtimeslx'];
			$newdata['session_id'] = $member['session_id'];
			$newdata['session_key'] = $member['session_key'];
			$newdata['paypwd'] = $member['paypwd'];
			$newdata['platform'] = $member['platform'];
			$newdata['subscribe'] = $member['subscribe'];
			$newdata['subscribe_time'] = $member['subscribe_time'];
			$newdata['levelstarttime'] = $member['levelstarttime'];
			$newdata['levelendtime'] = $member['levelendtime'];
			$newdata['remark'] = $member['remark'];
			$newdata['random_str'] = random(16);
			Db::name('member')->insert($newdata);
		}
	}

	//商品迁移
	public function qianyipro2(){

		die;
		$fromid = 10;
		$toid = 2;

		/*
		$dataList = Db::query('select * from '.table_name('shop_category_copy').' where aid='.$fromid);
		//var_dump($dataList);die;
		//db('shop_category')->where(['aid'=>$toid])->delete();
		foreach($dataList as $data){
			$newdata = [];
			$newdata['id'] = $data['id'];
			$newdata['aid'] = $toid;
			$newdata['pid'] = $data['pid'];
			$newdata['name'] = $data['name'];
			$newdata['pic'] = $data['pic'];
			$newdata['status'] = $data['status'];
			$newdata['sort'] = $data['sort'];
			$newdata['createtime'] = $data['createtime'];
			db('shop_category')->insertGetId($newdata);
		}

		$dataList = Db::query('select * from '.table_name('shop_group_copy').' where aid='.$fromid);
		//db('shop_group')->where(['aid'=>$toid])->delete();
		foreach($dataList as $data){
			$newdata = [];
			$newdata['id'] = $data['id'];
			$newdata['aid'] = $toid;
			$newdata['name'] = $data['name'];
			$newdata['pic'] = $data['pic'];
			$newdata['status'] = $data['status'];
			$newdata['sort'] = $data['sort'];
			$newdata['createtime'] = $data['createtime'];
			db('shop_group')->insertGetId($newdata);
		}
		$dataList = Db::query('select * from '.table_name('shop_product_copy').' where aid='.$fromid.' and bid=0');
		//db('shop_product')->where(['aid'=>$toid])->delete();
		foreach($dataList as $data){
			$newdata = [];
			$newdata['id'] = $data['id'];
			$newdata['aid'] = $toid;
			$newdata['bid'] = $data['bid'];
			$newdata['cid'] = $data['cid'];
			$newdata['gid'] = $data['gid'];
			$newdata['name'] = $data['name'];
			$newdata['procode'] = $data['procode'];
			$newdata['sellpoint'] = $data['sellpoint'];
			$newdata['pic'] = $data['pic'];
			$newdata['pics'] = $data['pics'];
			$newdata['sales'] = $data['sales'];
			$newdata['detail'] = $data['detail'];
			$newdata['market_price'] = $data['market_price'];
			$newdata['sell_price'] = $data['sell_price'];
			$newdata['cost_price'] = $data['cost_price'];
			$newdata['weight'] = $data['weight'];
			$newdata['sort'] = $data['sort'];
			$newdata['status'] = $data['status'];
			$newdata['stock'] = $data['stock'];
			$newdata['createtime'] = $data['createtime'];
			$newdata['commissionset'] = $data['commissionset'];
			$newdata['commissiondata1'] = $data['commissiondata1'];
			$newdata['commissiondata2'] = $data['commissiondata2'];
			$newdata['commissiondata3'] = $data['commissiondata3'];
			$newdata['commission1'] = $data['commission1'];
			$newdata['commission2'] = $data['commission2'];
			$newdata['commission3'] = $data['commission3'];
			$newdata['guigedata'] = $data['guigedata'];
			$newdata['comment_score'] = $data['comment_score'];
			$newdata['comment_haopercent'] = $data['comment_haopercent'];
			$newdata['comment_num'] = $data['comment_num'];
			$newdata['freighttype'] = $data['freighttype'];
			$newdata['freightdata'] = $data['freightdata'];
			$newdata['lvprice'] = $data['lvprice'];
			$newdata['lvprice_data'] = $data['lvprice_data'];
			$newdata['video'] = $data['video'];
			$newdata['video_duration'] = $data['video_duration'];
			$newdata['perlimit'] = $data['perlimit'];
			$newdata['status'] = $data['status'];
			$newdata['sort'] = $data['sort'];
			$newdata['createtime'] = $data['createtime'];
			db('shop_product')->insertGetId($newdata);
			$guigedataList = Db::query('select * from '.table_name('shop_guige_copy').' where proid='.$data['id']);
			foreach($guigedataList as $guigedata){
			    $newggdata = [];
				$newggdata['id'] = $guigedata['id'];
				$newggdata['aid'] = $toid;
				$newggdata['proid'] = $guigedata['proid'];
				$newggdata['name'] = $guigedata['name'];
				$newggdata['pic'] = $guigedata['pic'];
				$newggdata['market_price'] = $guigedata['market_price'];
				$newggdata['cost_price'] = $guigedata['cost_price'];
				$newggdata['sell_price'] = $guigedata['sell_price'];
				$newggdata['weight'] = $guigedata['weight'];
				$newggdata['stock'] = $guigedata['stock'];
				$newggdata['procode'] = $guigedata['procode'];
				$newggdata['sales'] = $guigedata['sales'];
				$newggdata['ks'] = $guigedata['ks'];
				$newggdata['lvprice_data'] = $guigedata['lvprice_data'];
				$newggdata['givescore'] = $guigedata['givescore'];
				db('shop_guige')->insertGetId($newggdata);
			}
		}

		
		$dataList = Db::query('select * from '.table_name('shop_order_copy').' where aid='.$fromid);
		//db('shop_group')->where(['aid'=>$toid])->delete();
		foreach($dataList as $data){
			$newdata = [];
			$newdata['id'] = $data['id'];
			$newdata['aid'] = $toid;
			$newdata['mid'] = $data['mid'];
			$newdata['title'] = $data['title'];
			$newdata['totalprice'] = $data['totalprice'];
			$newdata['product_price'] = $data['goodsprice'];
			$newdata['freight_price'] = $data['freightprice'];
			$newdata['leveldk_money'] = $data['disprice'];
			$newdata['createtime'] = $data['createtime'];
			$newdata['status'] = $data['status'];
			$newdata['ordernum'] = $data['ordernum'];
			$newdata['linkman'] = $data['linkman'];
			$newdata['tel'] = $data['tel'];
			$newdata['area'] = $data['area'];
			$newdata['address'] = $data['address'];
			$newdata['express_com'] = $data['express'];
			$newdata['express_no'] = $data['express_no'];
			$newdata['refund_reason'] = $data['refund_reason'];
			$newdata['refund_status'] = $data['refund_status'];
			$newdata['refund_time'] = $data['refund_time'];
			$newdata['refund_checkremark'] = $data['refund_checkremark'];
			$newdata['paytype'] = $data['paytype'];
			$newdata['paynum'] = $data['paynum'];
			$newdata['paytime'] = $data['paytime'];
			$newdata['delete'] = $data['delete'];
			$newdata['freight_id'] = $data['freightid'];
			$newdata['freight_text'] = $data['freight'];
			$newdata['freight_type'] = $data['freighttype'];
			$newdata['freight_time'] = $data['freight_time'];
			$newdata['send_time'] = $data['sendtime'];
			$newdata['collect_time'] = $data['collect_time'];
			$newdata['coupon_rid'] = $data['couponrid'];
			$newdata['coupon_money'] = $data['couponmoney'];
			$newdata['platform'] = $data['platform'];
			$newdata['hexiao_code'] = $data['code'];
			$newdata['hexiao_qr'] = $data['hexiaoqr'];
			$newdata['iscomment'] = $data['iscomment'];
			$newdata['scoredkscore'] = $data['scoredkscore'];
			$newdata['scoredk_money'] = $data['scoredk'];
			db('shop_order')->insertGetId($newdata);
		}
		*/

		$dataList = Db::query('select * from '.table_name('shop_order_goods_copy').' where aid='.$fromid);
		//db('shop_group')->where(['aid'=>$toid])->delete();
		foreach($dataList as $data){
			$newdata = [];
			$newdata['id'] = $data['id'];
			$newdata['aid'] = $toid;
			$newdata['mid'] = $data['mid'];
			$newdata['orderid'] = $data['orderid'];
			$newdata['ordernum'] = $data['ordernum'];
			$newdata['proid'] = $data['proid'];
			$newdata['name'] = $data['name'];
			$newdata['pic'] = $data['pic'];
			$newdata['procode'] = $data['procode'];
			$newdata['ggid'] = $data['ggid'];
			$newdata['ggname'] = $data['ggname'];
			$newdata['cid'] = $data['cid'];
			$newdata['num'] = $data['num'];
			$newdata['cost_price'] = $data['cost_price'];
			$newdata['sell_price'] = $data['sell_price'];
			$newdata['totalprice'] = $data['totalprice'];
			$newdata['status'] = $data['status'];
			$newdata['createtime'] = $data['createtime'];
			$newdata['endtime'] = $data['endtime'];
			$newdata['parent1'] = $data['parent1'];
			$newdata['parent2'] = $data['parent2'];
			$newdata['parent3'] = $data['parent3'];
			$newdata['parent1commission'] = $data['parent1commission'];
			$newdata['parent2commission'] = $data['parent2commission'];
			$newdata['parent3commission'] = $data['parent3commission'];
			$newdata['iscomment'] = $data['iscomment'];
			$newdata['isfenhong'] = 1;
			db('shop_order_goods')->insertGetId($newdata);
		}
	}

	//商品迁移
	public function qianyipro(){
		die;
		$fromid = 3;
		$toid = 13;
		$config=config('database');
		$config['connections']['old_db']['type']='mysql';
		$config['connections']['old_db']['hostname']='localhost';
		$config['connections']['old_db']['hostport']='3306';
		$config['connections']['old_db']['username']='';
		$config['connections']['old_db']['password']='';
		$config['connections']['old_db']['database']='';
		config($config, 'database');
		$formdb = Db::connect('old_db');
		/*
		$dataList = $formdb->query('select * from '.table_name('shop_category').' where aid='.$fromid);
		//var_dump($dataList);die;
		//db('shop_category')->where(['aid'=>$toid])->delete();
		foreach($dataList as $data){
			$newdata = [];
			$newdata['id'] = $data['id'] + 100;
			$newdata['aid'] = $toid;
			if($data['pid']){
				$newdata['pid'] = $data['pid'] + 100;
			}
			$newdata['name'] = $data['name'];
			$newdata['pic'] = $data['pic'];
			$newdata['status'] = $data['status'];
			$newdata['sort'] = $data['sort'];
			$newdata['createtime'] = $data['createtime'];
			db('shop_category')->insertGetId($newdata);
		}
		*/
		/*
		$dataList = $formdb->query('select * from '.table_name('shop_group').' where aid='.$fromid);
		//db('shop_group')->where(['aid'=>$toid])->delete();
		foreach($dataList as $data){
			$newdata = [];
			//$newdata['id'] = $data['id'];
			$newdata['aid'] = $toid;
			$newdata['name'] = $data['name'];
			$newdata['pic'] = $data['pic'];
			$newdata['status'] = $data['status'];
			$newdata['sort'] = $data['sort'];
			$newdata['createtime'] = $data['createtime'];
			db('shop_group')->insertGetId($newdata);
		}
		*/
		/*
		$dataList = $formdb->query('select * from '.table_name('shop_product').' where aid='.$fromid.'');
		//db('shop_product')->where(['aid'=>$toid])->delete();
		foreach($dataList as $data){
			$newdata = [];
			$newdata['id'] = $data['id'] + 100;
			$newdata['aid'] = $toid;
			$newdata['bid'] = $data['bid'];
			$newdata['cid'] = $data['cid'];
			$newdata['gid'] = $data['gid'];
			$newdata['name'] = $data['name'];
			$newdata['procode'] = $data['procode'];
			$newdata['sellpoint'] = $data['sellpoint'];
			$newdata['pic'] = $data['pic'];
			$newdata['pics'] = $data['pics'];
			$newdata['sales'] = $data['sales'];
			$newdata['detail'] = $data['detail'];
			$newdata['market_price'] = $data['market_price'];
			$newdata['sell_price'] = $data['sell_price'];
			$newdata['cost_price'] = $data['cost_price'];
			$newdata['weight'] = $data['weight'];
			$newdata['sort'] = $data['sort'];
			$newdata['status'] = $data['status'];
			$newdata['stock'] = $data['stock'];
			$newdata['createtime'] = $data['createtime'];
			$newdata['commissionset'] = $data['commissionset'];
			$newdata['commissiondata1'] = $data['commissiondata1'];
			$newdata['commissiondata2'] = $data['commissiondata2'];
			$newdata['commissiondata3'] = $data['commissiondata3'];
			$newdata['commission1'] = $data['commission1'];
			$newdata['commission2'] = $data['commission2'];
			$newdata['commission3'] = $data['commission3'];
			$newdata['guigedata'] = $data['guigedata'];
			$newdata['comment_score'] = $data['comment_score'];
			$newdata['comment_haopercent'] = $data['comment_haopercent'];
			$newdata['comment_num'] = $data['comment_num'];
			$newdata['freighttype'] = $data['freighttype'];
			$newdata['freightdata'] = $data['freightdata'];
			$newdata['lvprice'] = $data['lvprice'];
			$newdata['lvprice_data'] = $data['lvprice_data'];
			$newdata['video'] = $data['video'];
			$newdata['video_duration'] = $data['video_duration'];
			$newdata['perlimit'] = $data['perlimit'];
			$newdata['status'] = $data['status'];
			$newdata['sort'] = $data['sort'];
			$newdata['createtime'] = $data['createtime'];
			db('shop_product')->insertGetId($newdata);
			$guigedataList = $formdb->query('select * from '.table_name('shop_guige').' where proid='.$data['id']);
			foreach($guigedataList as $guigedata){
			    $newggdata = [];
				$newggdata['id'] = $guigedata['id'] + 100;
				$newggdata['aid'] = $toid;
				$newggdata['proid'] = $guigedata['proid'] + 100;
				$newggdata['name'] = $guigedata['name'];
				$newggdata['pic'] = $guigedata['pic'];
				$newggdata['market_price'] = $guigedata['market_price'];
				$newggdata['cost_price'] = $guigedata['cost_price'];
				$newggdata['sell_price'] = $guigedata['sell_price'];
				$newggdata['weight'] = $guigedata['weight'];
				$newggdata['stock'] = $guigedata['stock'];
				$newggdata['procode'] = $guigedata['procode'];
				$newggdata['sales'] = $guigedata['sales'];
				$newggdata['ks'] = $guigedata['ks'];
				$newggdata['lvprice_data'] = $guigedata['lvprice_data'];
				$newggdata['givescore'] = $guigedata['givescore'];
				db('shop_guige')->insertGetId($newggdata);
			}
		}

		$dataList = $formdb->query('select * from '.table_name('shop_freight').' where aid='.$fromid.'');
		foreach($dataList as $data){
			$newdata = [];
			$newdata['id'] = $data['id'] + 100;
			$newdata['aid'] = $toid;
			$newdata['bid'] = $data['bid'];
			$newdata['pstype'] = $data['pstype'];
			$newdata['name'] = $data['name'];
			$newdata['type'] = $data['type'];
			$newdata['pricedata'] = $data['pricedata'];
			$newdata['storetype'] = $data['storetype'];
			$newdata['storeids'] = $data['storeids'];
			$newdata['status'] = $data['status'];
			$newdata['sort'] = $data['sort'];
			$newdata['free_price'] = $data['free_price'];
			$newdata['pstimeset'] = $data['pstimeset'];
			$newdata['pstimedata'] = $data['pstimedata'];
			$newdata['psprehour'] = $data['psprehour'];
			$newdata['createtime'] = $data['createtime'];
			$newdata['peisong_juli1'] = $data['peisong_juli1'];
			$newdata['peisong_fee1'] = $data['peisong_fee1'];
			$newdata['peisong_juli2'] = $data['peisong_juli2'];
			$newdata['peisong_fee2'] = $data['peisong_fee2'];
			$newdata['peisong_lng'] = $data['peisong_lng'];
			$newdata['peisong_lat'] = $data['peisong_lat'];
			$newdata['peisong_range'] = $data['peisong_range'];
			$newdata['needlinkinfo'] = $data['needlinkinfo'];
			db('freight')->insertGetId($newdata);
		}
		*/
		/*
		$dataList = $formdb->query('select * from '.table_name('shop_order').' where aid='.$fromid.'');
		foreach($dataList as $data){
			$newdata = [];
			$newdata['id'] = $data['id'] + 100;
			$newdata['aid'] = $toid;
			$newdata['bid'] = $data['bid'];
			$newdata['mid'] = $data['mid'] + 2000;
			$newdata['title'] = $data['title'];
			$newdata['totalprice'] = $data['totalprice'];
			$newdata['product_price'] = $data['goodsprice'];
			$newdata['scoredk_money'] = $data['scoredk'];
			$newdata['leveldk_money'] = $data['disprice'];
			$newdata['freight_price'] = $data['freightprice'];
			$newdata['givescore'] = $data['givescore'];
			$newdata['coupon_rid'] = $data['couponrid'];
			$newdata['coupon_money'] = $data['couponmoney'];
			$newdata['scoredkscore'] = $data['scoredkscore'];
			$newdata['createtime'] = $data['createtime'];
			$newdata['status'] = $data['status'];
			$newdata['ordernum'] = $data['ordernum'];
			$newdata['linkman'] = $data['linkman'];
			$newdata['tel'] = $data['tel'];
			$newdata['area'] = $data['area'];
			$newdata['area2'] = $data['area2'];
			$newdata['address'] = $data['address'];
			$newdata['message'] = $data['message'];
			$newdata['remark'] = $data['remark'];
			$newdata['express_com'] = $data['express'];
			$newdata['express_no'] = $data['express_no'];
			$newdata['express_content'] = $data['express_content'];
			$newdata['refund_reason'] = $data['refund_reason'];
			$newdata['refund_money'] = $data['refund_money'];
			$newdata['refund_status'] = $data['refund_status'];
			$newdata['refund_time'] = $data['refund_time'];
			$newdata['refund_checkremark'] = $data['refund_checkremark'];
			$newdata['paytype'] = $data['paytype'];
			$newdata['paynum'] = $data['paynum'];
			$newdata['paytime'] = $data['paytime'];
			$newdata['delete'] = $data['delete'];
			$newdata['freight_id'] = $data['freightid']+100;
			$newdata['freight_text'] = $data['freight'];
			$newdata['freight_type'] = $data['freighttype'];
			$newdata['mdid'] = $data['freight_storeid'];
			$newdata['freight_time'] = $data['freight_time'];
			$newdata['freight_content'] = $data['pscontent'];
			$newdata['send_time'] = $data['send_time'];
			$newdata['collect_time'] = $data['collect_time'];
			$newdata['platform'] = $data['platform'];
			$newdata['hexiao_code'] = $data['code'];
			$newdata['hexiao_qr'] = $data['hexiaoqr'];
			$newdata['iscomment'] = $data['iscomment'];
			db('shop_order')->insertGetId($newdata);
		}

		$dataList = $formdb->query('select * from '.table_name('shop_order_goods').' where aid='.$fromid.'');
		foreach($dataList as $data){
			$newdata = [];
			$newdata['id'] = $data['id'] + 100;
			$newdata['aid'] = $toid;
			$newdata['bid'] = $data['bid'];
			$newdata['mid'] = $data['mid'] + 2000;
			$newdata['orderid'] = $data['orderid'] + 100;
			$newdata['ordernum'] = $data['ordernum'];
			$newdata['proid'] = $data['proid']+100;
			$newdata['name'] = $data['name'];
			$newdata['pic'] = $data['pic'];
			$newdata['procode'] = $data['procode'];
			$newdata['ggid'] = $data['ggid']+100;
			$newdata['ggname'] = $data['ggname'];
			//$newdata['cid'] = $data['cid'];
			$newdata['num'] = $data['num'];
			$newdata['cost_price'] = $data['cost_price'];
			$newdata['sell_price'] = $data['sell_price'];
			$newdata['totalprice'] = $data['totalprice'];
			$newdata['status'] = $data['status'];
			$newdata['createtime'] = $data['createtime'];
			$newdata['endtime'] = $data['endtime'];
			$newdata['parent1'] = $data['parent1'];
			$newdata['parent2'] = $data['parent2'];
			$newdata['parent3'] = $data['parent3'];
			$newdata['parent1commission'] = $data['parent1commission'];
			$newdata['parent2commission'] = $data['parent2commission'];
			$newdata['parent3commission'] = $data['parent3commission'];
			$newdata['iscomment'] = $data['iscomment'];
			//$newdata['seckill_starttime'] = $data['seckill_starttime'];
			//$newdata['fenhongmoney'] = $data['fenhongmoney'];
			$newdata['parent1score'] = $data['parent1score'];
			$newdata['parent2score'] = $data['parent2score'];
			$newdata['parent3score'] = $data['parent3score'];
			//$newdata['teamfenhongmoney'] = $data['teamfenhongmoney'];
			db('shop_order_goods')->insertGetId($newdata);
		}
		$dataList = $formdb->query('select * from '.table_name('business').' where aid='.$fromid.'');
		foreach($dataList as $data){
			$newdata = [];
			$newdata['id'] = $data['id'];
			$newdata['aid'] = $toid;
			if($data['mid']){
				$newdata['mid'] = $data['mid'] + 2000;
			}
			$newdata['cid'] = 1;
			$newdata['name'] = $data['name'];
			$newdata['desc'] = $data['subname'];
			$newdata['linkman'] = $data['linkman'];
			$newdata['tel'] = $data['tel'];
			$newdata['logo'] = $data['pic'];
			$newdata['pics'] = $data['pics'];
			$newdata['content'] = $data['content'];
			$newdata['address'] = $data['address'];
			$newdata['zhengming'] = $data['zhengming'];
			$newdata['longitude'] = $data['longitude'];
			$newdata['latitude'] = $data['latitude'];
			$newdata['money'] = $data['money'];
			$newdata['weixin'] = $data['weixin'];
			$newdata['aliaccount'] = $data['aliaccount'];
			$newdata['bankname'] = $data['bankname'];
			$newdata['bankcarduser'] = $data['bankcarduser'];
			$newdata['bankcardnum'] = $data['bankcardnum'];
			$newdata['sales'] = $data['sales'];
			$newdata['feepercent'] = $data['feepercent'];
			$newdata['sort'] = $data['sort'];
			$newdata['createtime'] = $data['createtime'];
			$newdata['status'] = $data['status'];
			$newdata['reason'] = $data['reason'];
			$newdata['comment_score'] = $data['comment_score'];
			$newdata['comment_num'] = $data['comment_num'];
			db('business')->insertGetId($newdata);
			
			$newdata2 = [];
			$newdata2['aid'] = $toid;
			$newdata2['bid'] = $data['id'];
			$newdata2['un'] = $data['un'];
			$newdata2['pwd'] = md5($data['pwd']);
			$newdata2['status'] = 1;
			$newdata2['createtime'] = time();
			$newdata2['auth_type'] = 1;
			$newdata2['isadmin'] = 1;
			$newdata2['random_str'] = random(10);
			if($data['mid']){
				$newdata2['mid'] = $data['mid'] + 2000;
			}
			db('admin_user')->insertGetId($newdata2);

			db('mendian')->insert(['aid'=>$toid,'bid'=> $data['id'],'name'=>$data['name'],'address'=>$data['address'],'pic'=>$data['pic'],'longitude'=>$data['longitude'],'latitude'=>$data['latitude'],'createtime'=>time()]);
		}

		*/
		$dataList = $formdb->query('select * from '.table_name('member').' where aid='.$fromid.'');
		foreach($dataList as $data){
			$newdata = [];
			$newdata['id'] = $data['id'] + 2000;
			$newdata['aid'] = $toid;
			$newdata['wxopenid'] = $data['wxopenid'];
			$newdata['mpopenid'] = $data['mpopenid'];
			$newdata['unionid'] = $data['unionid'];
			if($data['pid']){
				$newdata['pid'] = $data['pid'] + 2000;
			}
			if($data['levelid'] == 5){
				$newdata['levelid'] = 27;
			}
			if($data['levelid'] == 6){
				$newdata['levelid'] = 28;
			}
			if($data['levelid'] == 22){
				$newdata['levelid'] = 29;
			}
			$newdata['money'] = $data['money'];
			$newdata['totalcommission'] = $data['totalcommission'];
			$newdata['commission'] = $data['commission'];
			$newdata['score'] = $data['score'];
			$newdata['nickname'] = $data['nickname'];
			$newdata['headimg'] = $data['headimg'];
			$newdata['sex'] = $data['sex'];
			$newdata['realname'] = $data['realname'];
			$newdata['tel'] = $data['tel'];
			$newdata['usercard'] = $data['usercard'];
			$newdata['weixin'] = $data['weixin'];
			$newdata['aliaccount'] = $data['aliaccount'];
			$newdata['country'] = $data['country'];
			$newdata['province'] = $data['province'];
			$newdata['city'] = $data['city'];
			$newdata['area'] = $data['area'];
			$newdata['address'] = $data['address'];
			$newdata['birthday'] = $data['birthday'];
			$newdata['bankcardnum'] = $data['bankcardnum'];
			$newdata['bankname'] = $data['bankname'];
			$newdata['bankcarduser'] = $data['bankcarduser'];
			$newdata['createtime'] = $data['createtime'];
			$newdata['signdate'] = $data['signdate'];
			$newdata['signtime'] = $data['signtime'];
			$newdata['signtimes'] = $data['signtimes'];
			$newdata['signtimeslx'] = $data['signtimeslx'];
			$newdata['session_id'] = $data['session_id'];
			$newdata['session_key'] = $data['session_key'];
			$newdata['paypwd'] = $data['paypwd'];
			$newdata['platform'] = $data['platform'];
			$newdata['subscribe'] = $data['subscribe'];
			$newdata['subscribe_time'] = $data['subscribe_time'];
			$newdata['levelendtime'] = $data['levelendtime'];
			db('member')->insertGetId($newdata);
		}
	}
}