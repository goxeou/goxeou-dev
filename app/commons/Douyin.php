<?php


namespace app\commons;
use think\facade\Db;
use app\commons\Wechat;
class Douyin
{
	//同步商品到抖音
	public static function updateproduct($proid){
		$product = Db::name('shop_product')->where('id',$proid)->find();
		if(!$product) return ['status'=>0,'msg'=>'未查找到该商品'];
		if(!$product['douyin_cid1'] && !$product['douyin_cid2'] && !$product['douyin_cid3']) return ['status'=>0,'msg'=>'没有设置类目'];
		$aid = $product['aid'];
		$appinfo = Db::name('douyin_sysset')->where('aid',$aid)->find();
		$host = 'https://openapi-fxg.jinritemai.com';
		if($product['douyin_product_id']){
			$method = 'product.editV2';
		}else{
			$method = 'product.addV2';
		}
		$accessToken = self::access_token($aid);
		$appKey = $appinfo['app_id'];
		$appSecret = $appinfo['app_secret'];
		$timestamp = time();
		
		$category_leaf_id = $product['douyin_cid1'];
		if($product['douyin_cid2']) $category_leaf_id = $product['douyin_cid2'];
		if($product['douyin_cid3']) $category_leaf_id = $product['douyin_cid3'];

		$param_json = [];
		$param_json['out_product_id'] = $product['id'];
		$param_json['product_type'] = 0;   //0-普通，3-虚拟，6玉石闪购，7云闪购
		$param_json['category_leaf_id'] = intval($category_leaf_id);  //叶子类目ID通过/shop/getShopCategory接口获取
		$param_json['name'] = mb_substr($product['name'],0,30);
		if($product['douyin_product_format'])
		$param_json['product_format'] = $product['douyin_product_format'];  //属性名称|属性值 之间用|分隔, 多组之间用^分开
		//$param_json['recommend_remark'] = ''; //商家推荐语
		$pics = [];
		$pics[] = $product['pic'];
		if($product['pics']){
			foreach(explode(',',$product['pics']) as $pic){
				if($pic != $product['pic']){
					$pics[] = $pic;
				}
			}
		}
		$param_json['pic'] = implode('|',$pics);

		$detail = json_decode($product['detail'],true);
		preg_match_all('/(<img.*?src=[\'|\"])([^\"\']*?)([\'|\"].*?[\/]?>)/is',$detail[0]['content'],$matches);

		$param_json['description'] = implode('|',$matches[2]);  //商品描述，目前只支持图片。多张图片用 "|" 分开
		$param_json['pay_type'] = 1;  //支付方式，0货到付款 1在线支付，2，货到付款+在线支付
		$param_json['reduce_type'] = 1;  //减库存类型：1-拍下减库存 2-付款减库存
		//$param_json['assoc_ids'] = 1;  //同店商品推荐：为空表示使用系统推荐；多个product_id使用“|”分开
		$param_json['freight_id'] =  0;//运费模板id，传0表示包邮，通过/freightTemplate/list接口获取
		$param_json['weight'] = $product['weight']; //重量
		$param_json['weight_unit'] = 1; //重量单位，0-kg, 1-g
		$param_json['delivery_delay_day'] = 2;  //承诺发货时间，单位是天 不传则默认为2天
		$param_json['mobile'] = $appinfo['mobile'];  //客服电话号码
		$param_json['supply_7day_return'] = $product['douyin_supply_7day_return'];  //客服电话号码
		$param_json['commit'] = 1;  //false仅保存，true保存+提审

		
		if($product['douyin_qualitydata']){
			$douyin_quality_list = Db::name('douyin_quality_list')->where('id','in',$product['douyin_qualitydata'])->select()->toArray();
			$quality_list = [];
			foreach($douyin_quality_list as $k=>$v){
				$quality_list[] = ['quality_key'=>$v['quality_key'],'quality_name'=>$v['quality_name'],'quality_attachments'=>['media_type'=>1,'url'=>$v['pic']]];
			}
			$param_json['quality_list'] = $quality_list;
		}
		
		$guigedata = json_decode($product['guigedata'],true);
		$specs = [];
		foreach($guigedata as $v){
			$values = [];
			foreach($v['items'] as $v2){
				$values[] = $v2['title'];
			}
			$specs[] = $v['title'].'|'.implode(',',$values);
		}
		$param_json['specs'] = implode('^',$specs);

		$spec_prices = [];
		//$spec_pic = [];
		$gglist = Db::name('shop_guige')->where('aid',$aid)->where('proid',$product['id'])->select()->toArray();
		foreach($gglist as $gg){
			$sku = [];
			$sku['stock_num'] = $gg['stock'];
			$sku['price'] = $gg['sell_price']*100;
			$ks = explode(',',$gg['ks']);
			foreach($guigedata as $k=>$v){
				$attr_key = $v['title'];
				foreach($v['items'] as $v2){
					if($v2['k'] == $ks[$k]){
						$attr_value = $v2['title'];
					}
				}
				$sku['spec_detail_name'.($k+1)] = $attr_value;
			}
			$spec_prices[] = $sku;
		}
		$param_json['spec_prices'] = jsonEncode($spec_prices);
		if($product['perlimit'] > 0){
			$param_json['limit_per_buyer'] = $product['perlimit'];
		}
		if($product['limit_start'] > 0){
			$param_json['minimum_per_order'] = $product['limit_start'];
		}
		$param_json['market_price'] = $product['market_price']*100;
		$param_json['discount_price'] = $product['sell_price']*100;

		//var_dump($param_json);

		$paramJson = self::marshal($param_json);
		// 计算签名
		$signVal = self::sign($appKey, $appSecret, $method, $timestamp, $paramJson);
		// 发起请求
		$responseVal = self::fetch($appKey, $host, $method, $timestamp, $paramJson, $accessToken, $signVal);
		
		$rs = json_decode($responseVal,true);
		//var_dump($rs);
		if($rs['err_no']!=0){
			return ['status'=>0,'msg'=>$rs['message']];
		}else{
			$product_id = $rs['data']['product_id'];
			$update = [];
			$update['douyin_status'] = 0;
			$update['douin_check_reason'] = '';
			$update['douyin_product_id'] = $product_id;
			Db::name('shop_product')->where('id',$product['id'])->update($update);
		}
		return ['status'=>1,'msg'=>'更新成功'];
	}
	//从抖音同步商品
	public static function updatefromdouyin($aid){
		$appinfo = Db::name('douyin_sysset')->where('aid',$aid)->find();
		$host = 'https://openapi-fxg.jinritemai.com';
		$method = 'product.listV2';
		$accessToken = self::access_token($aid);
		$appKey = $appinfo['app_id'];
		$appSecret = $appinfo['app_secret'];
		$timestamp = date('Y-m-d H:i:s');

		$param_json = ['page'=>1,'size'=>100];

		$paramJson = self::marshal($param_json);
		//var_dump($paramJson);
		// 计算签名
		$signVal = self::sign($appKey, $appSecret, $method, $timestamp, $paramJson);
		//var_dump($signVal);
		// 发起请求
		$responseVal = self::fetch($appKey, $host, $method, $timestamp, $paramJson, $accessToken, $signVal);
		//var_dump($responseVal);
		
		$rs = json_decode($responseVal,true);
		if($rs['err_no']!=0){
			return ['status'=>0,'msg'=>$rs['message']];
		}else{
			//var_dump($rs['data']);
			return ['status'=>1,'data'=>$rs['data']];
		}
	}
	//上架商品
	public static function setOnline($aid,$proids){

		$appinfo = Db::name('douyin_sysset')->where('aid',$aid)->find();
		$host = 'https://openapi-fxg.jinritemai.com';
		$method = 'product.setOnline';
		$accessToken = self::access_token($aid);
		$appKey = $appinfo['app_id'];
		$appSecret = $appinfo['app_secret'];


		if(!is_array($proids)) $proids = strval($proids);
		$prolist = Db::name('shop_product')->where('id','in',$proids)->select()->toArray();
		foreach($prolist as $product){
			$timestamp = date('Y-m-d H:i:s');
			$param_json = ['product_id'=>$product['douyin_product_id']];
			$paramJson = self::marshal($param_json);
			//var_dump($paramJson);
			// 计算签名
			$signVal = self::sign($appKey, $appSecret, $method, $timestamp, $paramJson);
			//var_dump($signVal);
			// 发起请求
			$responseVal = self::fetch($appKey, $host, $method, $timestamp, $paramJson, $accessToken, $signVal);
			//var_dump($responseVal);
			
			$rs = json_decode($responseVal,true);
			if($rs['err_no']!=0){
				if(count($prolist) == 1) return ['status'=>0,'msg'=>$rs['message']];
			}else{
				Db::name('shop_product')->where('id',$product['id'])->update(['douyin_status'=>1]);
			}
		}
		return ['status'=>1,'msg'=>'更新成功'];
	}
	//下架商品
	public static function setOffline($aid,$proids){
		$appinfo = Db::name('douyin_sysset')->where('aid',$aid)->find();
		$host = 'https://openapi-fxg.jinritemai.com';
		$method = 'product.setOffline';
		$accessToken = self::access_token($aid);
		$appKey = $appinfo['app_id'];
		$appSecret = $appinfo['app_secret'];


		if(!is_array($proids)) $proids = strval($proids);
		$prolist = Db::name('shop_product')->where('id','in',$proids)->select()->toArray();
		foreach($prolist as $product){
			$timestamp = date('Y-m-d H:i:s');
			$param_json = ['product_id'=>$product['douyin_product_id']];
			$paramJson = self::marshal($param_json);
			//var_dump($paramJson);
			// 计算签名
			$signVal = self::sign($appKey, $appSecret, $method, $timestamp, $paramJson);
			//var_dump($signVal);
			// 发起请求
			$responseVal = self::fetch($appKey, $host, $method, $timestamp, $paramJson, $accessToken, $signVal);
			//var_dump($responseVal);
			
			$rs = json_decode($responseVal,true);
			if($rs['err_no']!=0){
				if(count($prolist) == 1) return ['status'=>0,'msg'=>$rs['message']];
			}else{
				Db::name('shop_product')->where('id',$product['id'])->update(['douyin_status'=>0]);
			}
		}
		return ['status'=>1,'msg'=>'更新成功'];
	}
	//删除商品
	public static function delProduct($aid,$proids){
		$appinfo = Db::name('douyin_sysset')->where('aid',$aid)->find();
		$host = 'https://openapi-fxg.jinritemai.com';
		$method = 'product.del';
		$accessToken = self::access_token($aid);
		$appKey = $appinfo['app_id'];
		$appSecret = $appinfo['app_secret'];


		if(!is_array($proids)) $proids = strval($proids);
		$prolist = Db::name('shop_product')->where('id','in',$proids)->select()->toArray();
		foreach($prolist as $product){
			$timestamp = date('Y-m-d H:i:s');
			$param_json = ['product_id'=>$product['douyin_product_id']];
			$paramJson = self::marshal($param_json);
			//var_dump($paramJson);
			// 计算签名
			$signVal = self::sign($appKey, $appSecret, $method, $timestamp, $paramJson);
			//var_dump($signVal);
			// 发起请求
			$responseVal = self::fetch($appKey, $host, $method, $timestamp, $paramJson, $accessToken, $signVal);
			//var_dump($responseVal);
			
			$rs = json_decode($responseVal,true);
			if($rs['err_no']!=0){
				if(count($prolist) == 1) return ['status'=>0,'msg'=>$rs['message']];
			}else{
				Db::name('shop_product')->where('id',$product['id'])->delete();
			}
		}
		return ['status'=>1,'msg'=>'更新成功'];
	}
	
	//获取商品分类
	public static function get_shop_category($aid,$cid=0){
		$appinfo = Db::name('douyin_sysset')->where('aid',$aid)->find();
		$host = 'https://openapi-fxg.jinritemai.com';
		$method = 'shop.getShopCategory';
		$accessToken = self::access_token($aid);
		$appKey = $appinfo['app_id'];
		$appSecret = $appinfo['app_secret'];
		$timestamp = date('Y-m-d H:i:s');

		$param_json = ['cid'=>$cid];

		$paramJson = self::marshal($param_json);
		//var_dump($paramJson);
		// 计算签名
		$signVal = self::sign($appKey, $appSecret, $method, $timestamp, $paramJson);
		//var_dump($signVal);
		// 发起请求
		$responseVal = self::fetch($appKey, $host, $method, $timestamp, $paramJson, $accessToken, $signVal);
		//var_dump($responseVal);
		
		$rs = json_decode($responseVal,true);
		if($rs['err_no']!=0){
			return ['status'=>0,'msg'=>$rs['message']];
		}else{
			return ['status'=>1,'data'=>$rs['data']];
		}
	}
	//获取分类对应的属性
	public static function get_category_property($aid,$cid){
		$appinfo = Db::name('douyin_sysset')->where('aid',$aid)->find();
		$host = 'https://openapi-fxg.jinritemai.com';
		$method = 'product.getCatePropertyV2';
		$accessToken = self::access_token($aid);
		$appKey = $appinfo['app_id'];
		$appSecret = $appinfo['app_secret'];
		$timestamp = date('Y-m-d H:i:s');

		$param_json = ['category_leaf_id'=>$cid];

		$paramJson = self::marshal($param_json);
		//var_dump($paramJson);
		// 计算签名
		$signVal = self::sign($appKey, $appSecret, $method, $timestamp, $paramJson);
		//var_dump($signVal);
		// 发起请求
		$responseVal = self::fetch($appKey, $host, $method, $timestamp, $paramJson, $accessToken, $signVal);
		//var_dump($responseVal);
		
		$rs = json_decode($responseVal,true);
		if($rs['err_no']!=0){
			return ['status'=>0,'msg'=>$rs['message']];
		}else{
			return ['status'=>1,'data'=>$rs['data']];
		}
	}

	//获取运费模板
	public static function getFreightTemplateList($aid){
		$appinfo = Db::name('douyin_sysset')->where('aid',$aid)->find();
		$host = 'https://openapi-fxg.jinritemai.com';
		$method = 'freightTemplate.list';
		$accessToken = self::access_token($aid);
		$appKey = $appinfo['app_id'];
		$appSecret = $appinfo['app_secret'];
		$timestamp = date('Y-m-d H:i:s');

		$param_json = [];

		$paramJson = self::marshal($param_json);
		$paramJson = '{}';
		// 计算签名
		$signVal = self::sign($appKey, $appSecret, $method, $timestamp, $paramJson);
		//var_dump($signVal);
		// 发起请求
		$responseVal = self::fetch($appKey, $host, $method, $timestamp, $paramJson, $accessToken, $signVal);
		//var_dump($responseVal);
		
		$rs = json_decode($responseVal,true);
		if($rs['err_no']!=0){
			return ['status'=>0,'msg'=>$rs['message']];
		}else{
			return ['status'=>1,'data'=>$rs['data']];
		}
	}

	//获取access_token
	public static function access_token($aid){
		$appinfo = Db::name('douyin_sysset')->where('aid',$aid)->find();
		$appid = $appinfo['app_id'];
		$appsecret = $appinfo['app_secret'];
		$shopid = $appinfo['shop_id'];
		if(!$appid) return '';
		if($appinfo['access_token'] && $appinfo['expires_time'] > time()){
			return $appinfo['access_token'];
		}else{
			if(!$appsecret) return '';
			$url = "https://openapi-fxg.jinritemai.com/oauth2/access_token?app_id={$appid}&app_secret={$appsecret}&grant_type=authorization_self&shop_id={$shopid}";
			$res = json_decode(request_get($url),true);
			$access_token = $res['data']['access_token'];
			if($access_token) {
				Db::name('douyin_sysset')->where('app_id',$appid)->update(['access_token'=>$access_token,'expires_time'=>time() + $res['expires_in'] - 100]);
				return $access_token;
			}else{
				//\think\facade\Log::write($res);
				//return '';
				echojson(['status'=>0,'msg'=>$res['message']]);
			}
		}
	}
	// 序列化参数，入参必须为关联数组
	public static function marshal(array $param): string {
		self::rec_ksort($param); // 对关联数组中的kv，执行排序，需要递归
		$s = json_encode($param, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); // 重新序列化，确保所有key按字典序排序
		// 加入flag，确保斜杠不被escape，汉字不被escape
		return $s;
	}

	// 关联数组排序，递归
	public static function rec_ksort(array &$arr) {
		$kstring = true;
		foreach ($arr as $k => &$v) {
			if (!is_string($k)) {
				$kstring = false;
			}
			if(is_array($v)){
				self::rec_ksort($v);
			}
		}
		if ($kstring) {
			ksort($arr);
		}
	}
	// 计算签名
	public static function sign($appKey,$appSecret,$method,$timestamp,$paramJson){
		$paramPattern = 'app_key' . $appKey . 'method' . $method . 'param_json' . $paramJson . 'timestamp' . $timestamp . 'v2';
		$signPattern = $appSecret . $paramPattern . $appSecret;

		//print('sign_pattern:' . $signPattern . "\n");
		return hash_hmac("sha256", $signPattern, $appSecret);
	}
	// 调用Open Api，取回数据
	public static function fetch($appKey,$host,$method,$timestamp,$paramJson,$accessToken,$sign){
		$methodPath = str_replace('.', '/', $method);
		$url = $host . '/' . ltrim($methodPath,'/') .
					'?method=' . urlencode($method) .
					'&app_key=' . urlencode($appKey) .
					'&access_token=' .urlencode($accessToken) .
					'&timestamp=' . urlencode(strval($timestamp)) .
					'&v=' . urlencode('2') .
					'&sign_method=' . urlencode('hmac-sha256').
					'&sign=' . $sign;
		$opts = array('http' =>
			array(
				'method' => 'POST',
				'header' => "Accept: */*\r\n" .
					"Content-type: application/json;charset=UTF-8\r\n",
				'content' => $paramJson
			)
		);
		$context = stream_context_create($opts);
		$result = file_get_contents($url, false, $context);
		return $result;
	}

	public static function get_delivery_id($express_com){
		$data = array('申通快递'=>'STO','EMS'=>'EMS','顺丰速运'=>'SF','圆通速递'=>'YTO','中通快递'=>'ZTO','韵达快递'=>'YD','天天快递'=>'HHTT','百世快递'=>'HTKY','全峰快递'=>'','德邦快递'=>'DBL','宅急送'=>'ZJS','如风达'=>'RFD','安信达'=>'','邦送物流'=>'BSWL','DHL快递'=>'DHL','大田物流'=>'DTWL','EMS国际'=>'EMSGJ','国通快递'=>'GTO','共速达'=>'GSD','华宇物流'=>'TDHY','佳吉快运'=>'CNEX','佳怡物流'=>'JYWL','快捷快递'=>'DJKJWL','龙邦速递'=>'LB','联邦快递'=>'FEDEX','联昊通'=>'LHT','能达速递'=>'NEDA','全一快递'=>'UAPEX','全日通'=>'QRT','速尔快递'=>'SURE','TNT快递'=>'TNT','天地华宇'=>'HOAU','新邦物流'=>'XBWL','新蛋物流'=>'','优速快递'=>'UC','中邮物流'=>'ZYWL','安能物流'=>'ANE','安能快递'=>'ANEEX','品骏快递'=>'PJ','极兔快递'=>'','京东'=>'JD',"自提"=>'',"其他"=>'');
		return $data[$express_com];
	}
    /**
     * 核销抖音券
     * @param $aid
     * @param $encrypted_data     从二维码解析出来的标识
     * @param $code              原始的抖音团购券码   encrypted_data/code必须二选一
     * @return mixed
     */
	public static function hexiaoQrcodePrepare($accessToken,$encrypted_data='',$code=''){
	    }
    /**
     * 核销抖音券
     * @param $aid
     * @param $poi_id  抖音门店ID
     * @param $key  一码多券，选择核销的key
     * @param $encrypted_data     从二维码解析出来的标识
     * @param $code              原始的抖音团购券码   encrypted_data/code必须二选一
     * @return mixed
     */
    public static function  hexiaoQrcode($aid,$bid,$poi_id,$key=[0],$encrypted_data='',$code=''){
        }
    /**
     * 获取抖音AccessToken
     * @param $aid
     * @param $bid
     * @return mixed
     */
    public static function getDouyinAccessToken($aid,$bid){
        }
    /**
     * 抖音请求方法
     * @param $url
     * @param $client_token
     * @param array $data
     * @param int $is_post
     * @return mixed
     */
    public static function douyinRequest($url,$data = [],$client_token = '',$is_post = 1){
        }
}