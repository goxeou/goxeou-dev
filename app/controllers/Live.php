<?php


// +----------------------------------------------------------------------
// | 直播管理
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class Live extends Common
{
	public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无操作权限');
	}
	//列表
    public function index(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'sort desc,starttime desc';
			}
			$where = array();
			$where[] = ['aid','=',aid];
			if(input('param.name')) $where[] = ['name','like','%'.input('param.name').'%'];
			$count = 0 + Db::name('live_room')->where($where)->count();
			$data = Db::name('live_room')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			foreach($data as $k=>$v){
				$goods = json_decode($v['goods'],true);
				$goodsdata=array();
				foreach($goods as $og){
					$goodsdata[] = '<div style="font-size:13px;float:left;clear:both;margin:1px 0">'.
						'<img src="'.$og['cover_img'].'" style="max-width:60px;float:left">'.
						'<div style="float: left;width:160px;margin-left: 10px;white-space:normal;line-height:16px;">'.
							'<div style="width:100%;min-height:25px;max-height:32px;overflow:hidden">'.$og['name'].'</div>'.
							'<div style="padding-top:0px;color:#f60;">'.($og['price_type']==1 ? $og['price']/100 . '元' : ($og['price_type']==2 ? $og['price']/100 . '元 ~ ' . $og['price2']/100 . '元' : '原价:' . $og['price']/100 . '元  现价' . $og['price2']/100 . '元')).'</div>'.
						'</div>'.
					'</div>';
				}
				$data[$k]['goodsdata'] = implode('',$goodsdata);
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'nowtime'=>time()]);
		}
		return View::fetch();
    }
	//编辑
	public function edit(){
		if(input('param.id')){
			$info = Db::name('live_room')->where('aid',aid)->where('id',input('param.id/d'))->find();
		}else{
			$info = array('id'=>'');
			$set = Db::name('admin_set')->where('aid',aid)->find();
			$info['name'] = $set['name'].'直播';
		}
		View::assign('info',$info);
		return View::fetch();
	}
	public function save(){
		$info = input('post.info/a');
		$info['startTime'] = strtotime($info['startTime']);
		$info['endTime'] = strtotime($info['endTime']);

		$postdata = [];
		$postdata['name'] = $info['name'];
		$postdata['coverImg'] = \app\commons\Wechat::pictomedia(aid,'wx',$info['coverImg']);
		$postdata['startTime'] = $info['startTime'];
		$postdata['endTime'] = $info['endTime'];
		$postdata['anchorName'] = $info['anchorName'];
		$postdata['anchorWechat'] = $info['anchorWechat'];
		if($info['subAnchorWechat']){
			$postdata['subAnchorWechat'] = $info['subAnchorWechat'];
		}
		if($info['createrWechat']){
			$postdata['createrWechat'] = $info['createrWechat'];
		}
		$postdata['shareImg'] = \app\commons\Wechat::pictomedia(aid,'wx',$info['shareImg']);
		if($info['feedsImg']){
			$postdata['feedsImg'] = \app\commons\Wechat::pictomedia(aid,'wx',$info['feedsImg']);
		}
		$postdata['isFeedsPublic'] = $info['isFeedsPublic'];
		$postdata['type'] = $info['type'];
		$postdata['screenType'] = $info['screenType'];
		$postdata['closeLike'] = $info['closeLike'];
		$postdata['closeGoods'] = $info['closeGoods'];
		$postdata['closeComment'] = $info['closeComment'];
		$postdata['closeReplay'] = $info['closeReplay'];
		$postdata['closeShare'] = $info['closeShare'];
		$postdata['closeKf'] = $info['closeKf'];
		
		$url = 'https://api.weixin.qq.com/wxaapi/broadcast/room/create?access_token='.\app\commons\Wechat::access_token(aid,'wx');
		//dump($postdata);
		$rs = $this->curl_post($url,jsonEncode($postdata));
		$rs = json_decode($rs,true);
		//dump($rs);
		if($rs['errcode']!=0 && $rs['errcode']!=1){
			return json(['status'=>0,'msg'=>'*号内容必填，'.\app\commons\Wechat::geterror($rs)]);
		}
		$info['roomId'] = $rs['roomId'];

		if($info['id']){
			Db::name('live_room')->where('aid',aid)->where('id',$info['id'])->update($info);
			\app\commons\System::plog('编辑直播间'.$info['id']);
		}else{
			$info['aid'] = aid;
			$info['createtime'] = time();
			$id = Db::name('live_room')->insertGetId($info);
			\app\commons\System::plog('添加直播间'.$id);
		}
		
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}

	//改状态
	public function setst(){
		$st = input('post.st/d');
		$ids = input('post.ids/a');
		Db::name('live_room')->where('aid',aid)->where('id','in',$ids)->update(['status'=>$st]);
		\app\commons\System::plog('直播间改状态'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//删除
	public function del(){
		$ids = input('post.ids/a');
		Db::name('live_room')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('删除直播间'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	//获取小程序码
	public function getpusherqr(){
		$id = input('post.id/d');
		$room = Db::name('live_room')->where('aid',aid)->where('id',$id)->find();
		//$wxapp = Db::name('admin_setapp_wx')->where('aid',aid)->find();
		//if(!$wxapp || !$wxapp['appid'] || !$wxapp['appsecret']){
		//	return json(['status'=>0,'msg'=>'请先绑定小程序']);
		//}
		$rs = \app\commons\Wechat::getQRCode(aid,'wx','pages/live/pusher',['code' => $room['code']]);
		return $rs;
	}

	public function choosegoods(){
		return View::fetch();
	}
	//添加商品到直播间
	public function roomaddgoods(){
		$ids = input('param.ids/a');
		$roomId = input('param.roomId');
		$url = 'https://api.weixin.qq.com/wxaapi/broadcast/room/addgoods?access_token='.\app\commons\Wechat::access_token(aid,'wx');
		$postdata = [];
		$postdata['ids'] = $ids;
		$postdata['roomId'] = $roomId;
		$rs = $this->curl_post($url,jsonEncode($postdata));
		$rs = json_decode($rs,true);
		if($rs['errcode']!=0 && $rs['errcode']!=1){
			return json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs),'_'=>$postdata]);
		}
		$this->tongbu();
		return json(['status'=>1,'msg'=>'操作成功']);
	}

	public function getplayerqr(){
		$id = input('post.id/d');
		$room = Db::name('live_room')->where('aid',aid)->where('id',$id)->find();
		//$wxapp = Db::name('admin_setapp_wx')->where('aid',aid)->find();
		//if(!$wxapp || !$wxapp['appid'] || !$wxapp['appsecret']){
		//	return json(['status'=>0,'msg'=>'请先绑定小程序']);
		//}
		$rs = \app\commons\Wechat::getQRCode(aid,'wx','pages/live/player',['code' => $room['code']]);
		return $rs;
	}

	//从小程序中同步
	public function tongbu(){
		$url = 'https://api.weixin.qq.com/wxa/business/getliveinfo?access_token='.\app\commons\Wechat::access_token(aid,'wx');
		$rs = request_post($url,jsonEncode(['start'=>0,'limit'=>100]));
		$rs = json_decode($rs,true);
		//dump($rs);
		if($rs['errcode']!=0 && $rs['errcode']!=1){
			if($rs['errcode'] == '9410000'){
				Db::name('live_room')->where('aid',aid)->delete();
			}
			return json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs)]);
		}
		$roomlist = array_reverse($rs['room_info']);
		//dump($roomlist);
		$roomidArr = [];
		foreach($roomlist as $k=>$v){
			$roomidArr[] = $v['roomid'];
			$data = [];
			$data['aid'] = aid;
			$data['roomId'] = $v['roomid'];
			$data['name'] = $v['name'];
			$data['coverImg'] = \app\commons\Pic::uploadoss($v['cover_img'],true);
			$data['shareImg'] = \app\commons\Pic::uploadoss($v['share_img'],true);
			$data['live_status'] = $v['live_status'];
			$data['startTime'] = $v['start_time'];
			$data['endTime'] = $v['end_time'];
			$data['anchorName'] = $v['anchor_name'];
			$goods = $v['goods'];
			if($goods){
				foreach($goods as $gk=>$gv){
					$goods[$gk]['cover_img'] = \app\commons\Pic::uploadoss($gv['cover_img'],true);
				}
			}
			$data['goods'] = jsonEncode($goods);
			$hasroom = Db::name('live_room')->where('aid',aid)->where('roomid',$v['roomid'])->find();
			if($hasroom){
				Db::name('live_room')->where('aid',aid)->where('roomid',$v['roomid'])->update($data);
			}else{
				Db::name('live_room')->insert($data);
			}
		}
		Db::name('live_room')->where('aid',aid)->where('roomid','not in',$roomidArr)->delete();
		return json(['status'=>1,'msg'=>'同步完成']);
	}
	//获取官方直播间列表
	public function getwxlivelist(){
		$url = 'https://api.weixin.qq.com/wxa/business/getliveinfo?access_token='.\app\commons\Wechat::access_token(aid,'wx');
		$rs = request_post($url,jsonEncode(['start'=>0,'limit'=>100]));
		$rs = json_decode($rs,true);
		//dump($rs);
		if($rs['errcode']!=0 && $rs['errcode']!=1){
			return json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs)]);
		}
		return json(['status'=>1,'data'=>$rs['room_info']]);
	}
	//获取商品列表
	public function goodslist(){
		if(request()->isAjax()){
			$status = input('param.st'); //商品状态，0：未审核。1：审核中，2：审核通过，3：审核驳回
			$page = input('param.page');
			$limit = input('param.limit');
			$url = 'https://api.weixin.qq.com/wxaapi/broadcast/goods/getapproved?access_token='.\app\commons\Wechat::access_token(aid,'wx');
			$postdata = [];
			$postdata['offset'] = ($page-1)*$limit;
			$postdata['limit'] = $limit;
			$postdata['status'] = $status;
			$rs = curl_get($url,$postdata);
			$rs = json_decode($rs,true);
			if($rs['errcode']!=0 && $rs['errcode']!=1){
				return json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs)]);
			}
			$data = $rs['goods'];
			$count = $rs['total'];
			
			foreach($data as $k=>$v){
				$v['coverImgUrl'] = \app\commons\Pic::uploadoss($v['coverImgUrl'],true);
				$data[$k] = $v;
			}
			
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'nowtime'=>time()]);
		}
		return View::fetch();
    }

	//添加商品
	public function addgoods(){
		if(request()->isAjax()){
			$url = 'https://api.weixin.qq.com/wxaapi/broadcast/goods/add?access_token='.\app\commons\Wechat::access_token(aid,'wx');
			$info = input('post.info/a');
			$goodsInfo = [];
			$goodsInfo['coverImgUrl'] = \app\commons\Wechat::pictomedia(aid,'wx',$info['coverImgUrl']);
			$goodsInfo['name'] = $info['name'];
			$goodsInfo['priceType'] = $info['priceType'];
			$goodsInfo['price'] = $info['price'];
			if($goodsInfo['priceType']==2 || $goodsInfo['priceType']==3){
				$goodsInfo['price2'] = $info['price2'];
			}
			$goodsInfo['url'] = $info['url'];
			$postdata = ['goodsInfo'=>$goodsInfo];
			$rs = $this->curl_post($url,jsonEncode($postdata));
			$rs = json_decode($rs,true);
			if($rs['errcode']!=0 && $rs['errcode']!=1){
				return json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs)]);
			}
			return json(['status'=>1,'msg'=>'操作成功']);
		}
		return View::fetch();
	}
	//删除商品
	public function delgoods(){
		$url = 'https://api.weixin.qq.com/wxaapi/broadcast/goods/delete?access_token='.\app\commons\Wechat::access_token(aid,'wx');
		$postdata = [];
		$postdata['goodsId'] = input('param.goodsId');
		$rs = $this->curl_post($url,jsonEncode($postdata));
		$rs = json_decode($rs,true);
		if($rs['errcode']!=0 && $rs['errcode']!=1){
			return json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs)]);
		}
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//更新商品
	public function updategoods(){
		if(request()->isAjax()){
			$info = input('post.info/a');
			$url = 'https://api.weixin.qq.com/wxaapi/broadcast/goods/update?access_token='.\app\commons\Wechat::access_token(aid,'wx');
			$goodsId = input('param.goodsId');
			$goodsInfo = [];
			$goodsInfo['goodsId'] = $goodsId;
			//$goodsInfo['coverImgUrl'] = \app\commons\Wechat::pictomedia(aid,'wx',$info['coverImgUrl']);
			//$goodsInfo['name'] = $info['name'];
			$goodsInfo['priceType'] = $info['priceType'];
			$goodsInfo['price'] = $info['price'];
			if($goodsInfo['priceType']==2 || $goodsInfo['priceType']==3){
				$goodsInfo['price2'] = $info['price2'];
			}
			//$goodsInfo['url'] = $info['url'];
			$postdata = ['goodsInfo'=>$goodsInfo];
			$rs = $this->curl_post($url,jsonEncode($postdata));
			$rs = json_decode($rs,true);
			if($rs['errcode']!=0 && $rs['errcode']!=1){
				return json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs)]);
			}
			$this->tongbu();
			return json(['status'=>1,'msg'=>'操作成功']);
		}
		$goodsId = input('param.goodsId');
		
		$url = 'https://api.weixin.qq.com/wxa/business/getgoodswarehouse?access_token='.\app\commons\Wechat::access_token(aid,'wx');
		$goods_ids = [$goodsId];
		$rs = $this->curl_post($url,jsonEncode(['goods_ids'=>$goods_ids]));
		$rs = json_decode($rs,true);
		if($rs['errcode']!=0 && $rs['errcode']!=1){
			die(\app\commons\Wechat::geterror($rs));
		}
		$info = $rs['goods'][0];
		View::assign('info',$info);
		return View::fetch();
	}
	//提交审核
	public function goodsaudit(){
		$goodsId = input('param.goodsId');
		$url = 'https://api.weixin.qq.com/wxaapi/broadcast/goods/audit?access_token='.\app\commons\Wechat::access_token(aid,'wx');
		$postdata = [];
		$postdata['goodsId'] = $goodsId;
		$rs = $this->curl_post($url,jsonEncode($postdata));
		$rs = json_decode($rs,true);
		if($rs['errcode']!=0 && $rs['errcode']!=1){
			return json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs)]);
		}
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//撤回审核
	public function goodsresetaudit(){
		$goodsId = input('param.goodsId');
		$url = 'https://api.weixin.qq.com/wxaapi/broadcast/goods/resetaudit?access_token='.\app\commons\Wechat::access_token(aid,'wx');
		$postdata = [];
		$postdata['goodsId'] = $goodsId;
		$rs = $this->curl_post($url,jsonEncode($postdata));
		$rs = json_decode($rs,true);
		if($rs['errcode']!=0 && $rs['errcode']!=1){
			return json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs)]);
		}
		return json(['status'=>1,'msg'=>'操作成功']);
	}

	public function chooseliveroom(){
		return View::fetch();
	}
	public function getliveroominfo(){
		$id = input('param.id/d');
		$info = Db::name('live_room')->where('id',$id)->find();
        $info['startTime'] = date('m-d H:i',$info['startTime']);
        $info['endTime'] = date('m-d H:i',$info['endTime']);
        if($info['endTime'] < time()){ //已结束
            $info['status'] = 2;
        }
		return json(['status'=>1,'data'=>$info]);
	}
	
	//curl post请求
	function curl_post($url, $keysArr=array(), $flag = 0){
		$ch = curl_init();
		if(! $flag) curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE); 
		curl_setopt($ch, CURLOPT_POST, TRUE); 
		curl_setopt($ch, CURLOPT_POSTFIELDS, $keysArr); 
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
		try {
			$response = curl_exec($ch);
		} catch (\Exception $e) {
		   return null;
		}
		$curlError = curl_error($ch);
		if(!empty($curlError)) {
		   return null;
		}
		curl_close($ch);
		//-------请求为空
		if(empty($response)){
			return null;
		}
		return $response;
	}
	//curl get请求
	function curl_get($url,$keysArr=array()){
		if(!empty($keysArr)){
			if(strpos($url,'?')!==false){
				$url = $url."?";
			}
			$url = strpos($url,'?')===false ? ($url."?") : ($url."&");
			$valueArr = array();
			foreach($keysArr as $key => $val){
				$valueArr[] = "$key=$val";
			}
			$keyStr = implode("&",$valueArr);
			$url .= ($keyStr);
		}
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
		try {
			$response = curl_exec($ch);
		} catch (\Exception $e) {
		   return null;
		}
		$curlError = curl_error($ch);
		if(!empty($curlError)) {
		   return null;
		}
		curl_close($ch);
		//-------请求为空
		if(empty($response)){
			return null;
		}
		return $response;
	}
}
