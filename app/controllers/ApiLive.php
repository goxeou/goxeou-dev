<?php
namespace app\controllers;
use think\facade\Db;
class ApiLive extends ApiCommon {
	public function initialize() {
		parent::initialize();
		$this->checklogin();
	}
	//首页 聊天列表
	public function index() {
		$config = include(ROOT_PATH.'config.php');
		$authtoken = $config['authtoken'];
		$token = md5(md5($authtoken.mid));
		return $this->json(['token'=>$token,'nowtime'=>time()]);
	}
	//获取聊天内容
	public function getmessagelist() {
		$room = input('post.room');
		if (!$room) {
			$datalist = [];
		} else {
			$where = [];
			$where[] = ['aid','=',aid];
			$where[] = ['room','=',$room];
			$datalist = Db::name('kefu_message')->where($where)->order('id desc')->select()->toArray();
		}
		if(!$datalist) $datalist = [];
		foreach($datalist as $k=>$v) {
		}
		$datalist = array_reverse($datalist);
		return $this->json(['status'=>1,'data'=>$datalist]);
	}
	public function hasLiveRoom() {
	    
		$admin_user = Db::name('admin_user')->where('aid',aid)->where(['mid' => mid])->find();
		
		if ($admin_user['bid']==0) {
		    $shop    =  Db::name('admin_set')->field('id,name,logo')->where(['aid' => 4])->find();
		}else{
		    $shop    =  Db::name('business')->field('id,name,logo')->where(['id' => $admin_user['bid']])->find();
		}
		$room =  Db::name('lives')->where(['mid' => mid])->find();
		return $this->json(['status'=>1,'room'=>$room,'shopData'=>$shop]);
	}
	public function launchlive() {
		$mid = $this->member['id'];
		$data['cover']  = input('param.image');
		$data['title']  = input('param.content');
		$data['notice'] = input('param.description');
		if (empty($data['cover'])) {
			return $this->json(['status'=>0,'msg'=>'请上传图片封面']);
		}
		$proids = input('param.proids');
		if (empty($proids)) {
			return $this->json(['status'=>0,'msg'=>'请选择直播商品']);
		}
		$user_arr = Db::name('member')->where(['id' => $mid])->find();
		if (empty($user_arr)) {
			return $this->json(['status'=>0,'msg'=>'没有找到用户信息']);
		}
		$user = Db::name('admin_user')->where('aid',aid)->where(['mid' => mid])->find();
		
	
		$shopInfo    =  Db::name('member')->field('id,nickname name,headimg logo')->where('id',mid )->find();

		ll($shopInfo,'$shopInfo');
		
		
	//	$shopInfo = Db::name('business')->where('aid',aid)->where(['id' => $user['bid']])->find();
		if (empty($shopInfo)) {
			return $this->json(['status'=>0,'msg'=>'对不起，没有找到您的信息']);
		} 
		$live_arr = Db::name('lives')->where('aid',aid)->where('mid',mid)->find();
		if (empty($live_arr)) {
			$insert['shop_id']  = $shopInfo['id'];
			$insert['aid']  = aid;
			$insert['mid']    = mid;
			$insert['bid']    =  $shopInfo['id'];
			$insert['proids']    = implode(',',$proids);
			$insert['livetime'] = time();
			$insert['create_time'] = time();
			$insert['cover']    = $data['cover'];
			$insert['room']     = getRefereeId();
			$insert['title']    = $data['title'];
			$insert['notice']   = $data['notice'];
			$insert_id          = Db::name('lives')->insertGetId($insert);
			$live_arr           = Db::name('lives')->where(['id' => $insert_id])->find();
		}
		$data['bid']    =  $shopInfo['id'];
		$data['proids']    = implode(',',$proids);
		$data['livetime'] = time();
		$data['room'] = $live_arr['room'];
		if (empty($live_arr['room'])) {
			$data['room'] = getRefereeId();
			Db::name('lives')->where(['mid' => $mid])->update($data);
		} else {
			Db::name('lives')->where(['id' => $live_arr['id']])->update($data);
		}
		$live_arr = Db::name('lives')->where(['mid' => mid])->find();
		if ($live_arr['isclose'] == 1) {
			return $this->json(['status'=>0,'msg'=>'对不起，该直播间由于违规操作，以被关闭']);
		}
		$update_live = 1;
		if ($update_live) {
			$streamlive = self::getstream($live_arr['room']);
			$rdata = [];
			$rdata['status'] = 1;
			$rdata['system_notice'] = $live_arr['notice']?$live_arr['notice']:'请您在拍下时确认购买商品和主播实际描述一致，禁止线下交易，谨防上当受骗！';
			$rdata['data'] = array('system_notice' =>$rdata['system_notice'],'id' =>$live_arr['id'],'bid' =>$live_arr['bid'],'room' =>$live_arr['room'],'liveid' =>$live_arr['room'],'pushurl' =>$streamlive,);
			$rdata['pushurl'] = $streamlive;
			
			
			ll($rdata,'auto_day');
			
			
			
			
			return $this->json($rdata);
		} else {
			return $this->json(['status'=>0,'msg'=>'上传封面失败']);
		}
	}
	public function cloud() {
		if(request()->isPost()) {
			$formdata = input('post.');
			if ($formdata['id'] && $formdata['goods_ids']) {
				$info = [];
				$info['proids'] = implode(',',$formdata['goods_ids']);
				Db::name('lives')->where('id',$formdata['id'])->update($info);
				return $this->json(['status'=>1,'msg'=>'修改成功']);
			}
		}
	}
// 	//收藏店铺
// 	public function coll() {
// 		$shop_id = input('post.id');
// 		$shops = Db::name('shops')->where('id',$shop_id)->field('id')->find();
// 		$coll_shops = Db::name('coll_shops')->where('user_id',$userId)->where('shop_id',$shop_id)->find();
// 		Db::name('coll_shops')->insert(array('shop_id'=>$shop_id,'user_id'=>$userId,'addtime'=>time()));
// 		Db::name('shops')->where('id',$shop_id)->setInc('coll_num',1);
// 		//关注直播间
// 		//7关注主播（仅限一次）
// 		Db::name('live_fans')->where('user_id',$userId)->update(array('isfollow'=>1));
// 		$live = db('live')->where(['shop_id'=>$shop_id])->find();
// 		$room = $live['room'];
// 		$num = $this->getLiveIntegralRules(7);
// 		$this->addLiveIntegral($userId,$shop_id,$room,$num,7);
// 		return $this->json(['status'=>1,'msg'=>'关注成功']);
// 	}
	public function play() {
		$userId = $this->member['id'];
		$lvid = input('param.id');
		if (empty($lvid)) {
			return $this->json(['status'=>0,'msg'=>'直播间参数错误']);
		}
		$row  = Db::name('lives')->where(['id' => $lvid])->find();
		$row['goods'] = Db::name('shop_product')->where('id','in',$row['proids'])->field('name,id,pic,sell_price')->select()->toArray();
		$row['state'] = $row['status'];
// 		$admin_user = Db::name('admin_user')->where('aid',aid)->where(['mid' => mid])->find();
// // 		$shop  =  Db::name('business')->field('id,name,logo')->where(['id' => $admin_user['bid']])->find();
// 		if ($admin_user['bid']==0) {
// 		    $shop    =  Db::name('admin_set')->field('id,name,logo')->where(['aid' => 4])->find();
// 		}else{
// 		    $shop    =  Db::name('business')->field('id,name,logo')->where(['id' => $admin_user['bid']])->find();
// 		}
		
		$member = Db::name('member')->field('id,headimg,nickname')->where('id',$row['mid'])->find();
		$shop['name'] = $member['nickname'];
		$shop['logo'] = $member['headimg'];
		
		$row['shop'] = $shop;
		$row['system_notice'] = $row['notice']?$row['notice']:'请您在拍下时确认购买商品和主播实际描述一致，禁止线下交易，谨防上当受骗！';
		$row['playurl0'] = 'https://' . 'play.zjxyyx168.com' . '/live/' . $row['room'] . '.flv';
		$row['playurl1'] = 'rtmp://' . 'play.zjxyyx168.com' . '/live/' . $row['room'];
		$row['recordurl'] = Db::name('live_transcribe')->where('user_id', $row['mid'])->order('id DESC')->value('video_url');
		$rdata = [];
		$rdata['status'] = 1;
		$rdata['data'] = $row;
		
		ll($rdata,'auto_day');
		
		
		
		
		return $this->json($rdata);
	}
	public function getstream($room) {
		$domain = 'push.zjxyyx168.com';
		$streamName=$room;
		$key = '64ee76db8785e0e8b92bdcba15e44931';
		$time = time()+86400;
		$timedate=date('Y-m-d H:i:s',$time);
		return self::getPushUrl($domain,$streamName,$key,$timedate);
	}
	/**
     * 获取推流地址
     * 如果不传key和过期时间，将返回不含防盗链的url
     * @param domain 您用来推流的域名
     *        streamName 您用来区别不同推流地址的唯一流名称
     *        key 安全密钥
     *        time 过期时间 sample 2016-11-12 12:00:00
     * @return String url
     */
	public function getPushUrl($domain, $streamName, $key = null, $time = null) {
		$type = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) ? 'https://' : 'http://';
		if(empty($type)) {
			$type = "https://";
		}
		if($key && $time) {
			$txTime = strtoupper(base_convert(strtotime($time),10,16));
			//txSecret = MD5( KEY + streamName + txTime )
			$txSecret = md5($key.$streamName.$txTime);
			$ext_str = "?".http_build_query(array(
			                    "txSecret"=> $txSecret,
			                    "txTime"=> $txTime
			                ));
		}
		$data = "rtmp://".$domain."/live/".$streamName . (isset($ext_str) ? $ext_str : "");
		return $data;
	}
	// 获取推荐直播间
	public function getRecommendLiveRoom() {//->where('isrecommend', 1)
		$liveRoom = Db::name('lives')->where('is_recycle',0)->where('status','<>', -1)->where('isclose','<>', 1)->limit(5)->select()->toArray();
		return $this->json(['status'=>1,'data'=>$liveRoom]);
	}
	/**
     * @func 获取直播间列表
     */
	public function getLiveList() {
		$st = input('param.st');
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$pernum = 20;
		$where  = ['isclose' => 0,'is_recycle'=>0,];
		$datalist = Db::name('lives')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		if(!$datalist) $datalist = [];
		foreach($datalist as $k=>&$item) {
			//   $live = new Coldlivepush();
			if ($item['status'] == 1) {
				// 直播中
				$item['transcribe'] = 0;
				// 是否回放
			} else {
				$item['addressitem'] = Db::name('live_transcribe')->where('user_id', $item['mid'])->order('id DESC')->value('video_url');
				if (!empty($item['addressitem'])) {
					$item['transcribe'] = 1;
				} else {
					$item['transcribe'] = 0;
				}
			}
		
	    	$member = Db::name('member')->field('id,headimg,nickname')->where('id',$item['mid'])->find();
			$item['shop_name'] = $member['nickname'];
			$item['shop_logo'] = $member['headimg'];
			$recommendGoods    = Db::name('shop_product')->where('id','in',$item['proids'])->field('name,pic,sell_price')->limit(3)->select()->toArray();
			$item['goods'] = $recommendGoods;
		}

		return $this->json(['status'=>1,'data'=>$datalist]);
	}
	public function livegoods() {
		$where = [];
		$where[] = ['aid','=',aid];
		$datalist = Db::name('shop_product')->field("id,bid,pic,name,sales,market_price,sell_price,lvprice,lvprice_data,sellpoint,fuwupoint,price_type,stock")->where($where)->order('id desc')->select()->toArray();
		if(!$datalist) $datalist = [];
		foreach ($datalist as $value) {
			$value['choose'] = 0;
		}
		return $this->json(['status'=>1,'data'=>$datalist]);
	}
}