<?php



//订单同步到策方
namespace app\customs;
use think\facade\Db;
class Cefang
{
	public static function api($order){
		$aid = $order['aid'];
		$config = include(ROOT_PATH.'config.php');

		$appId = $config['cefanginfo']['appId'];
		$appSecret = $config['cefanginfo']['appSecret'];

		$token = self::gettoken($appId,$appSecret);

		$member = Db::name('member')->where('id',$order['mid'])->find();

		$url = 'https://bd-server.cefang.cn/open-api/v1/open-order/insert?token='.$token;
		
		$ordergoods = Db::name('shop_order_goods')->where('orderid',$order['id'])->select()->toArray();
		$totalnum = Db::name('shop_order_goods')->where('orderid',$order['id'])->sum('num');

		$appinfo = \app\commons\System::appinfo($aid,$order['platform']);

		$postdata = [];
		$postdata['unionId'] = $member['unionid'];
		$postdata['shopId'] = $appId;
		$postdata['orderId'] = $order['ordernum'];
		$postdata['price'] = intval($order['totalprice'] * 100);
		$postdata['userAddress'] = $order['area2'].$order['address'];
		$postdata['userName'] = $order['linkman'];
		$postdata['userPhone'] = $order['tel'];
		$postdata['orderStatus'] = $order['status']+1;
		$postdata['title'] = mb_substr($order['title'],0,49);
		$postdata['image'] = $ordergoods[0]['pic'];
		$postdata['createdTime'] = date('Y-m-d H:i:s',$order['createtime']);
		$postdata['paidTime'] = date('Y-m-d H:i:s',$order['paytime']);
		if($postdata['orderStatus'] == 4){
			$postdata['deliveryTime'] = date('Y-m-d H:i:s');
		}
		$postdata['totalNum'] = intval($totalnum);
		$postdata['discountFree'] = 0;
		
		$postdatalist = jsonEncode(['list'=>[$postdata]]);

		$rs = curl_post($url,$postdatalist);
		$rs = json_decode($rs,true);
		if($rs['code'] == 0){
			return ['status'=>1,'msg'=>'同步成功'];	
		}else{
			\think\facade\Log::write($rs);
			return ['status'=>0,'msg'=>$rs['err']];	
		}
	}

	//获取token
	public static function gettoken($appId,$appSecret){

		if(cache('cefang_gettoken')){
			return cache('cefang_gettoken');
		}
		$url = 'https://bd-server.cefang.cn/open-api/token/get';
		
		$postdata = [];
		$postdata['appId'] = $appId;
		$postdata['appSecret'] = $appSecret;
		$rs = curl_post($url,jsonEncode($postdata));
		$rs = json_decode($rs,true);
		cache('cefang_gettoken',$rs['token'],strtotime($rs['expire']) - time());
		
		return $rs['token'];
	}
}