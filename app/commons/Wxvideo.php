<?php


namespace app\commons;
use think\facade\Db;
use think\facade\Log;
use app\commons\Wechat;
class Wxvideo
{
	//同步商品到视频号
	public static function updateproduct($proid){
		$product = Db::name('shop_product')->where('id',$proid)->find();
		if(!$product) return ['status'=>0,'msg'=>'未查找到该商品'];
		if(!$product['wxvideo_third_cat_id'] || !$product['wxvideo_brand_id']) return ['status'=>0,'msg'=>'没有设置类目或品牌'];
		$aid = $product['aid'];
		$postdata = [];
		$postdata['out_product_id'] = strval($product['id']);
		$postdata['title'] = $product['name'];
		$postdata['path'] = 'pages/shop/product?id='.$product['id'];
		$pics = [];
		$pics[] = self::uploadimg($product['pic'],$aid);
		if($product['pics']){
			foreach(explode(',',$product['pics']) as $pic){
				if($pic != $product['pic']){
					$pics[] = self::uploadimg($pic,$aid);
				}
			}
		}
		$postdata['head_img'] = $pics;
		if($product['wxvideo_qualification_pics']){
			$postdata['qualification_pics'] = [];
			foreach(explode(',',$product['wxvideo_qualification_pics']) as $v){
				$postdata['qualification_pics'][] = self::uploadimg($v,$aid);
			}
		}
		$postdata['third_cat_id'] = $product['wxvideo_third_cat_id'];
		$postdata['brand_id'] = $product['wxvideo_brand_id'];
		
		$guigedata = json_decode($product['guigedata'],true);
		$skus = [];
		$gglist = Db::name('shop_guige')->where('aid',$aid)->where('proid',$product['id'])->select()->toArray();
		foreach($gglist as $gg){
			$sku = [];
			$sku['out_product_id'] = strval($gg['proid']);
			$sku['out_sku_id'] = strval($gg['id']);
			$sku['thumb_img'] = self::uploadimg($gg['pic'] ? $gg['pic'] : $product['pic'],$aid);
			$sku['sale_price'] = intval(strval($gg['sell_price']*100));
			$sku['market_price'] = intval(strval($gg['market_price']*100));
			$sku['stock_num'] = $gg['stock'];
			$sku['out_product_id'] = strval($gg['proid']);
			$sku_attrs = [];
			$ks = explode(',',$gg['ks']);
			foreach($guigedata as $k=>$v){
				$attr_key = $v['title'];
				foreach($v['items'] as $v2){
					if($v2['k'] == $ks[$k]){
						$attr_value = $v2['title'];
					}
				}
				$sku_attrs[] = ['attr_key'=>$attr_key,'attr_value'=>$attr_value];
			}
			$sku['sku_attrs'] = $sku_attrs;
			$skus[] = $sku;
		}
		$postdata['skus'] = $skus;
		$postdata['scene_group_list'] = [1];
		if($product['wxvideo_product_id']){
			$url = 'https://api.weixin.qq.com/shop/spu/update?access_token='.Wechat::access_token($aid,'wx');
		}else{
			$url = 'https://api.weixin.qq.com/shop/spu/add?access_token='.Wechat::access_token($aid,'wx');
		}
		$rs = curl_post($url,jsonEncode($postdata));
		$rs = json_decode($rs,true);
		//\think\facade\Log::write($rs);
		if($rs['errcode']!=0){
			\think\facade\Log::write('updateproduct');
			\think\facade\Log::write($rs);
			return ['status'=>0,'msg'=>Wechat::geterror($rs)];
		}else{
			$product_id = $rs['data']['product_id'];
			$update = [];
			$update['wxvideo_edit_status'] = 1;
			$update['wxvideo_reject_reason'] = '';
			$update['wxvideo_product_id'] = $product_id;
			Db::name('shop_product')->where('id',$product['id'])->update($update);
		}
		return ['status'=>1,'msg'=>'更新成功'];
	}
	//免审更新商品
	public static function update_without_audit($proid){
		$product = Db::name('shop_product')->where('id',$proid)->find();
		if(!$product) return ['status'=>0,'msg'=>'未查找到该商品'];
		if(!$product['wxvideo_product_id']) return ['status'=>0,'msg'=>'未上传到视频号'];
		$aid = $product['aid'];
		$guigedata = json_decode($product['guigedata'],true);
		$skus = [];
		$gglist = Db::name('shop_guige')->where('aid',$aid)->where('proid',$product['id'])->select()->toArray();
		foreach($gglist as $gg){
			$sku = [];
			$sku['out_sku_id'] = strval($gg['id']);
			$sku['sale_price'] = intval(strval($gg['sell_price']*100));
			$sku['market_price'] = intval(strval($gg['market_price']*100));
			$sku['stock_num'] = $gg['stock'];
			$skus[] = $sku;
		}
		$postdata = [];
		$postdata['out_product_id'] = strval($product['id']);
		$postdata['skus'] = $skus;
		$url = 'https://api.weixin.qq.com/shop/spu/update_without_audit?access_token='.Wechat::access_token($aid,'wx');
		$rs = curl_post($url,jsonEncode($postdata));
		$rs = json_decode($rs,true);
		//\think\facade\Log::write($postdata);
		if($rs['errcode']!=0){
			\think\facade\Log::write('update_without_audit');
			\think\facade\Log::write($rs);
			return ['status'=>0,'msg'=>Wechat::geterror($rs)];
		}else{
			return ['status'=>1,'msg'=>'更新成功'];
		}
	}
	//上架商品
	public static function listing($proids){
		if(!is_array($proids)) $proids = strval($proids);
		$prolist = Db::name('shop_product')->where('id','in',$proids)->select()->toArray();
		foreach($prolist as $product){
			$aid = $product['aid'];
			if(!$product['wxvideo_product_id']) continue;
			$rs = curl_post('https://api.weixin.qq.com/shop/spu/listing?access_token='.Wechat::access_token($aid,'wx'),jsonEncode(['product_id'=>$product['wxvideo_product_id']]));
			$rs = json_decode($rs,true);
			if($rs['errcode']==0){
				Db::name('shop_product')->where('id',$product['id'])->update(['wxvideo_status'=>5]);
			}else{
				if(count($prolist) == 1) return ['status'=>0,'msg'=>Wechat::geterror($rs)];
			}
		}
		return ['status'=>1,'msg'=>'更新成功'];
	}
	//下架商品
	public static function delisting($proids){
		if(!is_array($proids)) $proids = strval($proids);
		$prolist = Db::name('shop_product')->where('id','in',$proids)->select()->toArray();
		foreach($prolist as $product){
			$aid = $product['aid'];
			if(!$product['wxvideo_product_id']) continue;
			$rs = curl_post('https://api.weixin.qq.com/shop/spu/delisting?access_token='.Wechat::access_token($aid,'wx'),jsonEncode(['product_id'=>$product['wxvideo_product_id']]));
			$rs = json_decode($rs,true);
			if($rs['errcode']==0){
				Db::name('shop_product')->where('id',$product['id'])->update(['wxvideo_status'=>11]);
			}else{
				if(count($prolist) == 1) return ['status'=>0,'msg'=>Wechat::geterror($rs)];
			}
		}
		return ['status'=>1,'msg'=>'更新成功'];
	}
	//删除商品
	public static function deleteproduct($proids){
		if(!is_array($proids)) $proids = strval($proids);
		$prolist = Db::name('shop_product')->where('id','in',$proids)->select()->toArray();
		foreach($prolist as $product){
			$aid = $product['aid'];
			if($product['wxvideo_product_id']){
				curl_post('https://api.weixin.qq.com/shop/spu/del?access_token='.Wechat::access_token($aid,'wx'),jsonEncode(['product_id'=>$product['wxvideo_product_id']]));
			}
		}
	}

	//订单发货
	public static function deliverysend($orderid){
		$order = Db::name('shop_order')->where('id',$orderid)->find();
		$aid = $order['aid'];
		$postdata = [];
		$postdata['out_order_id'] = strval($order['id']);
		$postdata['openid'] = Db::name('member')->where('id',$order['mid'])->value('wxopenid');
		$postdata['finish_all_delivery'] = 1;
		if($order['freight_type'] == 0){
			$delivery_list = [];
			$delivery_list['delivery_id'] = self::get_delivery_id($order['express_com']);
			if(!$delivery_list['delivery_id']) $delivery_list['delivery_id'] = 'OTHERS';
			$delivery_list['waybill_id'] = $order['express_no'];
			$product_info_list = [];
			$oglist = Db::name('shop_order_goods')->where('orderid',$order['id'])->select()->toArray();
			foreach($oglist as $og){
				$product_info_list[] = ['out_product_id'=>strval($og['proid']),'out_sku_id'=>strval($og['ggid']),'product_cnt'=>$og['num']];
			}
			$delivery_list['product_info_list'] = $product_info_list;
			$postdata['delivery_list'] = [$delivery_list];
		}
		$postdata['ship_done_time'] = date('Y-m-d H:i:s');
		//\think\facade\Log::write($postdata);
		$rs = curl_post('https://api.weixin.qq.com/shop/delivery/send?access_token='.Wechat::access_token($aid,'wx'),jsonEncode($postdata));
		$rs = json_decode($rs,true);
		//\think\facade\Log::write($rs);
		if($rs['errcode']==0){
			return ['status'=>1,'msg'=>'成功'];
		}else{
			\think\facade\Log::write('deliverysend');
			\think\facade\Log::write($rs);
			return ['status'=>0,'msg'=>Wechat::geterror($rs)];
		}
	}
	//确认收货
	public static function deliveryrecieve($orderid){
		$order = Db::name('shop_order')->where('id',$orderid)->find();
		$aid = $order['aid'];
		$postdata = [];
		$postdata['out_order_id'] = strval($order['id']);
		$postdata['openid'] = Db::name('member')->where('id',$order['mid'])->value('wxopenid');
		
		$rs = curl_post('https://api.weixin.qq.com/shop/delivery/recieve?access_token='.Wechat::access_token($aid,'wx'),jsonEncode($postdata));
		$rs = json_decode($rs,true);
		if($rs['errcode']==0){
			return ['status'=>1,'msg'=>'成功'];
		}else{
			\think\facade\Log::write('deliveryrecieve');
			\think\facade\Log::write($rs);
			return ['status'=>0,'msg'=>Wechat::geterror($rs)];
		}
	}
	//创建售后
	public static function aftersaleadd($orderid,$refund_id){
		$order = Db::name('shop_order')->where('id',$orderid)->find();
		$aid = $order['aid'];
		$refund = Db::name('shop_refund_order')->where('id',$refund_id)->find();
		
		$postdata = [];
		$postdata['out_order_id'] = strval($order['id']);
		$postdata['out_aftersale_id'] = strval($refund['id']);
		$postdata['openid'] = Db::name('member')->where('id',$order['mid'])->value('wxopenid');
		$postdata['type'] = ($refund['refund_type'] == 'refund' ? 1 : 2);
		//$postdata['create_time'] = date('Y-m-d H:i:s');
		$postdata['type'] = ($refund['refund_type'] == 'refund' ? 1 : 2);
		//$postdata['finish_all_aftersale'] = 0;
		$postdata['orderamt'] = intval(strval($refund['refund_money']*100));
		$postdata['refund_reason'] = $refund['refund_reason'];
		$postdata['refund_reason_type'] = 2;

		$refund_oglist = Db::name('shop_refund_order_goods')->where('refund_orderid',$refund_id)->select()->toArray();

		$product_info = ['out_product_id'=>strval($refund_oglist[0]['proid']),'out_sku_id'=>strval($refund_oglist[0]['ggid']),'product_cnt'=>$refund_oglist[0]['refund_num']];
		$postdata['product_info'] = $product_info;
		$rs = curl_post('https://api.weixin.qq.com/shop/ecaftersale/add?access_token='.Wechat::access_token($aid,'wx'),jsonEncode($postdata));
		$rs = json_decode($rs,true);

		//\think\facade\Log::write($postdata);
		//\think\facade\Log::write($rs);

		//$product_infos = [];
		//foreach($refund_oglist as $og){
			
		//}
		//$postdata['product_infos'] = $product_infos;

		
		//$rs = curl_post('https://api.weixin.qq.com/shop/ecaftersale/add?access_token='.Wechat::access_token($aid,'wx'),jsonEncode($postdata));
		//$rs = json_decode($rs,true);
		//var_dump($postdata);
		//var_dump($rs);
		if($rs['errcode']==0){
			Db::name('shop_refund_order')->where('id',$refund_id)->update(['aftersale_id'=>$rs['aftersale_id']]);
			return ['status'=>1,'msg'=>'成功'];
		}else{
			\think\facade\Log::write('aftersaleadd');
			\think\facade\Log::write($rs);
			\think\facade\Log::write($postdata);
			return ['status'=>0,'msg'=>Wechat::geterror($rs)];
		}
	}
	//更新售后  0取消 1申请退款审核中 2已同意退款 4同意待退货 3已驳回
	public static function aftersaleupdate($orderid,$refund_id){
		$order = Db::name('shop_order')->where('id',$orderid)->find();
		$aid = $order['aid'];
		$refund = Db::name('shop_refund_order')->where('id',$refund_id)->find();
		$postdata = [];
		if(!$refund['aftersale_id']) return ;
		$postdata['aftersale_id'] = $refund['aftersale_id'];
		if($refund['refund_status'] == 2){ //同意退款
			$rs = curl_post('https://api.weixin.qq.com/shop/ecaftersale/acceptrefund?access_token='.Wechat::access_token($aid,'wx'),jsonEncode($postdata));
			Log::write('同意退款');
			Log::write(jsonEncode($postdata));
			Log::write($rs);
		}
		if($refund['refund_status'] == 4){ //同意退货
			$shopset = Db::name('shop_sysset')->where('aid',$aid)->find();
			$postdata['address_info'] = [
				'receiver_name'=>$shopset['receiving_address_name'],
				'detailed_address'=>$shopset['receiving_address_address'],
				'tel_number'=>$shopset['receiving_address_tel'],
				'province'=>$shopset['receiving_address_province'],
				'city'=>$shopset['receiving_address_city'],
				'town'=>$shopset['receiving_address_area'],
			];
			$rs = curl_post('https://api.weixin.qq.com/shop/ecaftersale/acceptreturn?access_token='.Wechat::access_token($aid,'wx'),json_encode($postdata));
			Log::write('同意退货');
			Log::write(jsonEncode($postdata));
			Log::write($rs);
		}
		if($refund['refund_status'] == 3){ //驳回
			$rs = curl_post('https://api.weixin.qq.com/shop/ecaftersale/reject?access_token='.Wechat::access_token($aid,'wx'),jsonEncode($postdata));
		}
		if($refund['refund_status'] == 0){ //取消
			$postdata['openid'] = Db::name('member')->where('id',$refund['mid'])->value('wxopenid');
			$rs = curl_post('https://api.weixin.qq.com/shop/ecaftersale/cancel?access_token='.Wechat::access_token($aid,'wx'),jsonEncode($postdata));
		}
		$rs = json_decode($rs,true);
		if($rs['errcode']==0){
			return ['status'=>1,'msg'=>'成功'];
		}else{
			\think\facade\Log::write('aftersaleupdate');
			\think\facade\Log::write($rs);
			\think\facade\Log::write($postdata);
			return ['status'=>0,'msg'=>Wechat::geterror($rs)];
		}
	}

	//创建订单
	public static function createorder($orderid){
		$time = time();
		$order_info = [];
		$order_detail = [];
		$order = Db::name('shop_order')->where('id',$orderid)->find();
		$aid = $order['aid'];
		$oglist = Db::name('shop_order_goods')->where('orderid',$orderid)->select()->toArray();
		$member = Db::name('member')->where('id',$order['mid'])->find();
		$product_infos = [];
		$sell_price = 0;
		foreach($oglist as $og){
			$sell_price+=$og['sell_price']*$og['num'];
		}
		$discounted_price = $sell_price + $order['freight_price'] - $order['totalprice'];
		foreach($oglist as $og){
			$sku_real_price = round($og['sell_price']*$og['num'] - $og['sell_price']*$og['num'] /$sell_price * $discounted_price,2);
			$product_infos[] = [
				'out_product_id'=>strval($og['proid']),
				'out_sku_id'=>strval($og['ggid']),
				'product_cnt'=>$og['num'],
				'sale_price'=>intval(strval($og['sell_price']*100)),
				'sku_real_price'=>intval(strval($sku_real_price*100)),
				'head_img'=>$og['pic'],
				'title'=>$og['name'],
				'path'=>'pages/shop/product?id='.$og['proid'],
			];
			$sell_price+=$og['sell_price']*$og['num'];
		}
		$order_info['create_time'] = date('Y-m-d H:i:s',$time);
		$order_info['type'] = 0;
		$order_info['out_order_id'] = strval($order['id']);
		$order_info['openid'] = $member['wxopenid'];
		$order_info['path'] = 'pagesExt/orderdetail?id='.$order['id'];
		$order_detail['product_infos'] = $product_infos;

		$pay_method_type = 0;
		if($order['paytypeid'] == 1) $pay_method_type = 2; //余额支付
		if($order['paytypeid'] == 4) $pay_method_type = 1; //货到付款
		$order_detail['pay_info'] = [
			'pay_method_type'=>$pay_method_type,
			//'prepay_time'=>date('Y-m-d H:i:s',$time)
		];
		$order_detail['price_info'] = [
			'order_price'=>intval(strval($order['totalprice'] *100)),
			'freight'=>intval(strval($order['freight_price'] *100)),
		];
		if($discounted_price > 0){
			$order_detail['price_info']['discounted_price'] = intval(strval($discounted_price*100));
		}
		$order_info['order_detail'] = $order_detail;
		$delivery_type = 1;
		if($order['freight_type'] == 0) $delivery_type = 1; //正常快递
		if($order['freight_type'] == 1) $delivery_type = 4; //到店自提
		if($order['freight_type'] == 2) $delivery_type = 3; //线下配送
		if($order['freight_type'] == 3 || $order['freight_type'] == 4) $delivery_type = 2; //无需快递
		$order_info['delivery_detail'] = ['delivery_type'=>$delivery_type];
		if($delivery_type == 4){
			$mendian = Db::name('mendian')->where('id',$order['mdid'])->find();
			$order_info['address_info'] = ['receiver_name'=>$mendian['name'],'tel_number'=>$mendian['tel'],'detailed_address'=>$mendian['area'].$mendian['address']];
		}elseif($delivery_type == 1 || $delivery_type == 3){
			$order_info['address_info'] = ['receiver_name'=>$order['linkman'],'tel_number'=>$order['tel'],'detailed_address'=>$order['area'].$order['address']];
		}
		$shopset = Db::name('shop_sysset')->where('aid',$aid)->find();
		
		//$wOpt['orderInfo'] = $order_info;
		$order_info['scene'] = $order['scene'];
		if($pay_method_type==0){
			$order_info['fund_type'] = 1;
		}else{
			$order_info['fund_type'] = 0;
		}
		$order_info['expire_time'] = time() + $shopset['autoclose'] * 60;
		$rs = curl_post('https://api.weixin.qq.com/shop/order/add?access_token='.Wechat::access_token($aid,'wx'),jsonEncode($order_info));
		$rs = json_decode($rs,true);
		if($rs['errcode']==0){
			Db::name('shop_order')->where('id',$orderid)->update(['wxvideo_order_id'=>$rs['data']['order_id']]);
			return ['status'=>1,'msg'=>'成功','data'=>$rs['data']];
		}else{
			\think\facade\Log::write('createorder');
			\think\facade\Log::write($rs);
			return ['status'=>0,'msg'=>Wechat::geterror($rs)];
		}
	}
	//同步订单支付结果 1:支付成功,2:支付失败,3:用户取消,4:超时未支付;5:商家取消;10:其他原因取消
	public static function orderpay($orderid,$action_type=1){
		$order = Db::name('shop_order')->where('id',$orderid)->find();
		$aid = $order['aid'];
		$member = Db::name('member')->where('id',$order['mid'])->find();
		$order_info = [];
		$order_info['out_order_id'] = strval($order['id']);
		$order_info['openid'] = $member['wxopenid'];
		$order_info['action_type'] = $action_type;
		if($action_type == 1){
			$order_info['pay_time'] = date('Y-m-d H:i:s');
			if($order['paytypeid']==2 && $order['paynum']){
				$order_info['transaction_id'] = $order['paynum'];
				$order_info['pay_time'] = date('Y-m-d H:i:s',$order['paytime']);
			}
		}
		$rs = curl_post('https://api.weixin.qq.com/shop/order/pay?access_token='.Wechat::access_token($aid,'wx'),jsonEncode($order_info));
		$rs = json_decode($rs,true);
		//\think\facade\Log::write($rs);
		if($rs['errcode']==0){
			return ['status'=>1,'msg'=>'成功'];
		}else{
			\think\facade\Log::write('orderpay');
			\think\facade\Log::write($rs);
			return ['status'=>0,'msg'=>Wechat::geterror($rs)];
		}
	}
	//获取订单
	public static function getorder($orderid){
		$order = Db::name('shop_order')->where('id',$orderid)->find();
		$aid = $order['aid'];
		$member = Db::name('member')->where('id',$order['mid'])->find();
		$order_info = [];
		$order_info['out_order_id'] = strval($orderid);
		$order_info['openid'] = $member['wxopenid'];
		$rs = curl_post('https://api.weixin.qq.com/shop/order/get?access_token='.Wechat::access_token($aid,'wx'),jsonEncode($order_info));
		$rs = json_decode($rs,true);
		//\think\facade\Log::write($rs);
		if($rs['errcode']==0){
			return ['status'=>1,'msg'=>'成功','data'=>$rs['order']];
		}else{
			\think\facade\Log::write('getorder');
			\think\facade\Log::write($rs);
			return ['status'=>0,'msg'=>Wechat::geterror($rs)];
		}
	}

	//上传图片到微信侧
	public static function uploadimg($pic,$aid=0){
		if(!$aid) $aid = aid;
		if(!$pic) return '';
		$cache = Db::name('wxpic_cache')->where('pic',$pic)->find();
		if($cache){
			return $cache['img_url'];
		}
		$access_token = \app\commons\Wechat::access_token($aid,'wx');
		$url = \app\commons\Pic::tolocal($pic);
		$mediapath = ROOT_PATH.str_replace(PRE_URL.'/','',$url);
		//$data = array('buffer'=>'@'.$mediapath);
		$data = [];
		$data['resp_type'] = 1;
		$data['upload_type'] = 0;
		$data['media'] = new \CurlFile($mediapath);
		$result = curl_post('https://api.weixin.qq.com/shop/img/upload?access_token='.$access_token,$data);
		$res = json_decode($result,true);
		if($res['errcode'] == 0 && $res['img_info']){
			Db::name('wxpic_cache')->insert(['pic'=>$pic,'media_id'=>$res['img_info']['media_id'],'img_url'=>$res['img_info']['temp_img_url']]);
			return $res['img_info']['temp_img_url'];
		}else{
			return json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($res)]);
		}
	}

    public static function get_delivery_id($express_com)
    {
        $data = array(
            '申通快递' => 'STO',
            'EMS'    => 'EMS',
            '中国邮政速递物流'      => 'EMS',
            '顺丰速运' => 'SF',
            '圆通速递' => 'YTO',
            '中通快递' => 'ZTO',
            '中通快运' => 'ZTKY',
            '韵达快递' => 'YD',
            '韵达速递' => 'YD',
            '韵达快运' => 'YDKY',
            '天天快递' => 'HHTT',
            '百世快递' => 'HTKY',
            '百世快运' => 'BTWL',
            '全峰快递' => '',
            '德邦快递' => 'DBL',
            '德邦快运' => 'DBLKY',
            '德邦物流' => 'DBLKY',
            '宅急送'   => 'ZJS',
            '如风达'   => 'RFD',
            '安信达'   => '',
            '邦送物流' => 'BSWL',
            'DHL快递'  => 'DHL',
            '大田物流' => 'DTWL',
            'EMS国际'  => 'EMSGJ',
            '国通快递' => 'GTO',
            '共速达'   => 'GSD',
            '华宇物流' => 'TDHY',
            '佳吉快运' => 'CNEX',
            '佳怡物流' => 'JYWL',
            '快捷快递' => 'DJKJWL',
            '龙邦速递' => 'LB',
            '联邦快递' => 'FEDEX',
            '联昊通'   => 'LHT',
            '能达速递' => 'NEDA',
            '全一快递' => 'UAPEX',
            '全日通'   => 'QRT',
            '速尔快递' => 'SURE',
            'TNT快递'  => 'TNT',
            '天地华宇' => 'HOAU',
            '新邦物流' => 'XBWL',
            '新蛋物流' => '',
            '优速快递' => 'UC',
            '中邮物流' => 'ZYWL',
            '安能物流' => 'ANE',
            '安能快递' => 'ANEEX',
            '品骏快递' => 'PJ',
            '品骏物流' => 'PJ',
            '极兔快递' => 'JTSD',
            '京东'    => 'JD',
            '京东快递' => 'JD',
            '京东快运' => 'JDKY',
            "自提"     => '',
            "其他"     => ''
        );
        return $data[$express_com];
    }
}