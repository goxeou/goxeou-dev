<?php


namespace app\commons;
use think\facade\Db;
class Make
{
	//获取token
	public static function access_token($aid){
		$set = Db::name('peisong_set')->where('aid',$aid)->find();
		if($set['make_access_token'] && $set['make_expire_time'] > time()){
			return $set['make_access_token'];
		}else{
			$url = 'https://'.$set['make_domain'].'/addons/make_speed/core/public/index.php/apis/v2/get_token';
			$postdata = [];
			$postdata['token'] = $set['make_token'];
			$postdata['appid'] = $set['make_appid'];
			$rs = request_post($url,$postdata);
			$res = json_decode($rs,true);
			$access_token = $res['token'];
			if($access_token){
				Db::name('peisong_set')->where('aid',$aid)->update(['make_access_token'=>$access_token,'make_expire_time'=>time()+86000*3]);
				return $access_token;
			}else{
				echojson(['status'=>0,'msg'=>$res['msg'] ? $res['msg'] : t('码科').'error']);
			}
		}
	}
	//添加订单
	public static function createorder($order){
		$aid = $order['aid'];
		$set = Db::name('peisong_set')->where('aid',$order['aid'])->find();
		$binfo = json_decode($order['binfo'],true);
		$orderinfo = json_decode($order['orderinfo'],true);
		$prolist = json_decode($order['prolist'],true);

		$url = 'https://'.$set['make_domain'].'/addons/make_speed/core/public/index.php/apis/v2/create_order';
		$postdata = [];
		$postdata['token'] = self::access_token($aid);
		$postdata['goods_name'] = Db::name('admin_set')->where('aid',$aid)->value('name').'订单';
		$postdata['pick_time'] = '立即送出';
		if($orderinfo['message']){
			$postdata['remark'] = $orderinfo['message'];
		}
		$postdata['order_no'] = $order['ordernum'];
		$addressdata = [];
		$addressdata['begin_detail'] = $binfo['area']?$binfo['area']:$binfo['name'];
		$addressdata['begin_address'] = $binfo['address'];
		$addressdata['begin_lat'] = $binfo['latitude'];
		$addressdata['begin_lng'] = $binfo['longitude'];
		$addressdata['begin_username'] = $binfo['name'];
		$addressdata['begin_phone'] = $binfo['tel'];

		$addressdata['end_detail'] = $orderinfo['area']?$orderinfo['area']:'';
		$addressdata['end_address'] = $orderinfo['address'];
		$addressdata['end_lat'] = $orderinfo['latitude'];
		$addressdata['end_lng'] = $orderinfo['longitude'];
		$addressdata['end_username'] = $orderinfo['linkman'];
		$addressdata['end_phone'] = $orderinfo['tel'];
		$postdata['pay_price']  = $order['ticheng'];
		$postdata['total_price'] = $order['ticheng'];

		$postdata['address'] = jsonEncode($addressdata);
		$postdata['notify_url'] = PRE_URL.'/?s=ApiMake/notify/aid/'.$aid;
		$goods = [];
		foreach($prolist as $v){
			$goods[] = ['name'=>$v['name'],'price'=>$v['sell_price'],'num'=>$v['num']];
		}
		$postdata['goods'] = jsonEncode($goods);
		$postdata['shop_id'] = ($order['bid']==0?'-1':$order['bid']); //自营传-1

		$rs = curl_post($url,$postdata);
		$res = json_decode($rs,true);
		if($res['error_code']==0){
			$order_number = $res['data']['order_number'];
			Db::name('peisong_order')->where('id',$order['id'])->update(['make_ordernum'=>$order_number]);
			return ['status'=>1,'msg'=>'创建成功','order_number'=>$order_number,'rs'=>$rs];
		}else{
			return ['status'=>0,'msg'=>$res['msg']];
		}
	}
	//获取配送价格
	public static function getprice($aid,$bid,$latitude,$longitude,$latitude2,$longitude2){
		$set = Db::name('peisong_set')->where('aid',$aid)->find();
		$access_token = self::access_token($aid);
		$url = 'https://'.$set['make_domain'].'/addons/make_speed/core/public/index.php/apis/v2/get_delivery_price?token='.$access_token.'&fromcoord='.$latitude.','.$longitude.'&tocoord='.$latitude2.','.$longitude2.'&shop_id='.$bid;

		$rs = request_get($url);
		$res = json_decode($rs,true);
		if($res['error_code']==0){
			return ['status'=>1,'data'=>$res['data'],'price'=>$res['data']['total_price']];
		}else{
			return ['status'=>0,'msg'=>t('码科').'：'.$res['msg']];
		}
	}
	//取消订单
	public static function cancelorder($order){
		$aid = $order['aid'];
		$set = Db::name('peisong_set')->where('aid',$aid)->find();
		$url = 'https://'.$set['make_domain'].'/addons/make_speed/core/public/index.php/apis/v2/cancel_order';
		$postdata = [];
		$postdata['order_num'] = $order['make_ordernum'];
		$postdata['token'] = self::access_token($order['aid']);
		$rs = request_post($url,$postdata);
		$res = json_decode($rs,true);
		if($res['error_code']==0){
			return ['status'=>1,'msg'=>'取消成功'];
		}else{
			return ['status'=>0,'msg'=>$res['msg']];
		}
	}
}