<?php



//订单同步到花娃
namespace app\customs;
use think\facade\Db;
class Huawa
{
	//对接订单到花娃
	public static function api($order){
		$aid = $order['aid'];
		$config = include(ROOT_PATH.'config.php');
		$api_account = $config['huawa_account'];
		$keycode = $config['huawa_keycode'];
		if(!$api_account || !$keycode) return;
		$oglist = Db::name('shop_order_goods')->where('orderid',$order['id'])->select()->toArray();
		$quantity = Db::name('shop_order_goods')->where('orderid',$order['id'])->sum('num');
		$data = array();
		//$data['_debug_'] = 1;
		$data['istimer']=0;//是否定时配送1:是，0：否
		$data['delivery_time']=1;//配送时段，如果istimer等于0时这个值必填1:不限时段,2:08-10点,3:10-12点,4:12-14点,5:14-16点,6:16-18点,7:18-20点,8:20-22点,15:上午,16:下午17:晚上
		$data['songdate']=date('Y-m-d H:i:s');
		$data['receive_name']=$order['linkman'];
		$data['receive_mobile']=$order['tel'];
		//花娃地址修正
		$order['area2'] = str_replace('新疆维吾尔自治区','新疆',$order['area2']);
		$order['area2'] = str_replace('宁夏回族自治区','宁夏',$order['area2']);
		$order['area2'] = str_replace('密云区','密云县',$order['area2']);
		$order['area2'] = str_replace('延庆区','延庆县',$order['area2']);
		$order['area2'] = str_replace('崇明区','崇明县',$order['area2']);
		$order['area2'] = str_replace('綦江区','綦江县',$order['area2']);
		$order['area2'] = str_replace('大足区','大足县',$order['area2']);
		$order['area2'] = str_replace('黔江区','黔江开发区',$order['area2']);
		$order['area2'] = str_replace('璧山区','璧山县',$order['area2']);
		$order['area2'] = str_replace('铜梁区','铜梁县',$order['area2']);
		$order['area2'] = str_replace('潼南区','潼南县',$order['area2']);
		$order['area2'] = str_replace('荣昌区','荣昌县',$order['area2']);
		$order['area2'] = str_replace('石柱土家族自治县','石柱县',$order['area2']);
		$order['area2'] = str_replace('秀山土家族苗族自治县','秀山县',$order['area2']);
		$order['area2'] = str_replace('酉阳土家族苗族自治县','酉阳县',$order['area2']);
		$order['area2'] = str_replace('彭水苗族土家族自治县','彭水县',$order['area2']);
		$order['area2'] = str_replace('藁城区','藁城市',$order['area2']);
		$order['area2'] = str_replace('鹿泉区','鹿泉市',$order['area2']);
		$order['area2'] = str_replace('栾城区','栾城县',$order['area2']);
		$order['area2'] = str_replace('抚宁区','抚宁县',$order['area2']);
		$order['area2'] = str_replace('青龙满族自治县','青龙县',$order['area2']);
		$order['area2'] = str_replace('万全区','万全县',$order['area2']);
		$order['area2'] = str_replace('崇礼区','崇礼县',$order['area2']);
		$order['area2'] = str_replace('丰宁满族自治县','丰宁县',$order['area2']);
		$order['area2'] = str_replace('宽城满族自治县','宽城县',$order['area2']);
		$order['area2'] = str_replace('围场满族蒙古族自治县','围场县',$order['area2']);
		$order['area2'] = str_replace('孟村回族自治县','孟村县',$order['area2']);
		$order['area2'] = str_replace('大厂回族自治县','大厂县',$order['area2']);
		$order['area2'] = str_replace('冀州区','冀州市',$order['area2']);
		$order['area2'] = str_replace('怀仁县','怀仁市',$order['area2']);
		$order['area2'] = str_replace('白云鄂博矿区','白云矿区',$order['area2']);
		$order['area2'] = str_replace('康巴什区','康巴什新区',$order['area2']);
		$order['area2'] = str_replace('莫力达瓦达斡尔族自治旗','莫力达瓦',$order['area2']);
		$order['area2'] = str_replace('扎赉诺尔区','满洲里市',$order['area2']);
		$order['area2'] = str_replace('延边朝鲜族自治州','延边',$order['area2']);
		$order['area2'] = str_replace('伊通满族自治县','伊通',$order['area2']);
		$order['area2'] = str_replace('长白朝鲜族自治县','长白',$order['area2']);
		$order['area2'] = str_replace('前郭尔罗斯蒙古族自治县','前郭尔罗斯',$order['area2']);
		$order['area2'] = str_replace('景宁畲族自治县','景宁',$order['area2']);
		$order['area2'] = str_replace('铜官区','铜官山区',$order['area2']);
		$order['area2'] = str_replace('文登区','文登市',$order['area2']);
		$order['area2'] = str_replace('陵城区','陵县',$order['area2']);
		$order['area2'] = str_replace('沾化区','沾化县',$order['area2']);
		$order['area2'] = str_replace('定陶区','定陶县',$order['area2']);
		$order['area2'] = str_replace('长垣县','长垣市',$order['area2']);
		$order['area2'] = str_replace('宁乡县','宁乡市',$order['area2']);
		$order['area2'] = str_replace('邵东县','邵东市',$order['area2']);
		$order['area2'] = str_replace('广西壮族自治区','广西省',$order['area2']);
		$order['area2'] = str_replace('三江侗族自治县','三江',$order['area2']);
		$order['area2'] = str_replace('龙胜各族自治县','龙胜',$order['area2']);
		$order['area2'] = str_replace('恭城瑶族自治县','恭城',$order['area2']);
		$order['area2'] = str_replace('临桂区','临桂县',$order['area2']);
		$order['area2'] = str_replace('隆林各族自治县','隆林',$order['area2']);
		$order['area2'] = str_replace('平桂区','平桂管理区',$order['area2']);
		$order['area2'] = str_replace('富川瑶族自治县','富川',$order['area2']);
		$order['area2'] = str_replace('罗城仫佬族自治县','罗城',$order['area2']);
		$order['area2'] = str_replace('环江毛南族自治县','环江',$order['area2']);
		$order['area2'] = str_replace('巴马瑶族自治县','巴马',$order['area2']);
		$order['area2'] = str_replace('都安瑶族自治县','都安',$order['area2']);
		$order['area2'] = str_replace('大化瑶族自治县','大化',$order['area2']);
		$order['area2'] = str_replace('金秀瑶族自治县','金秀',$order['area2']);
		$order['area2'] = str_replace('阿坝藏族羌族自治州','阿坝州',$order['area2']);
		$order['area2'] = str_replace('甘孜藏族自治州','甘孜州',$order['area2']);
		$order['area2'] = str_replace('凉山彝族自治州','凉山州',$order['area2']);
		$order['area2'] = str_replace('北川羌族自治县','北川',$order['area2']);
		$order['area2'] = str_replace('峨边彝族自治县','峨边',$order['area2']);
		$order['area2'] = str_replace('马边彝族自治县','马边',$order['area2']);
		$order['area2'] = str_replace('彭山区','彭山县',$order['area2']);
		$order['area2'] = str_replace('名山区','名山县',$order['area2']);
		$order['area2'] = str_replace('康定市','康定县',$order['area2']);
		$order['area2'] = str_replace('木里藏族自治县','木里',$order['area2']);
		$order['area2'] = str_replace('玉屏侗族自治县','玉屏县',$order['area2']);
		$order['area2'] = str_replace('印江土家族苗族自治县','印江县',$order['area2']);
		$order['area2'] = str_replace('沿河土家族自治县','沿河县',$order['area2']);
		$order['area2'] = str_replace('松桃苗族自治县','松桃县',$order['area2']);
		$order['area2'] = str_replace('三都水族自治县','三都',$order['area2']);
		$order['area2'] = str_replace('内蒙古自治区','内蒙古',$order['area2']);
		$order['area2'] = str_replace('楚雄彝族自治州','楚雄州',$order['area2']);
		$order['area2'] = str_replace('红河哈尼族彝族自治州','红河州',$order['area2']);
		$order['area2'] = str_replace('文山壮族苗族自治州','文山州',$order['area2']);
		$order['area2'] = str_replace('西双版纳傣族自治州','西双版纳',$order['area2']);
		$order['area2'] = str_replace('大理白族自治州','大理',$order['area2']);
		$order['area2'] = str_replace('德宏傣族景颇族自治州','德宏',$order['area2']);
		$order['area2'] = str_replace('怒江傈傈族自治州','怒江',$order['area2']);
		$order['area2'] = str_replace('迪庆藏族自治州','迪庆',$order['area2']);
		$order['area2'] = str_replace('呈贡区','呈贡县',$order['area2']);
		$order['area2'] = str_replace('石林彝族自治县','石林',$order['area2']);
		$order['area2'] = str_replace('禄劝彝族苗族自治县','禄劝',$order['area2']);
		$order['area2'] = str_replace('寻甸回族彝族自治县','寻甸',$order['area2']);
		$order['area2'] = str_replace('沾益区','沾益县',$order['area2']);
		$order['area2'] = str_replace('江川区','江川县',$order['area2']);
		$order['area2'] = str_replace('峨山彝族自治县','峨山',$order['area2']);
		$order['area2'] = str_replace('新平彝族傣族自治县','新平',$order['area2']);
		$order['area2'] = str_replace('元江哈尼族彝族傣族自治县','元江',$order['area2']);
		$order['area2'] = str_replace('玉龙纳西族自治县','玉龙',$order['area2']);
		$order['area2'] = str_replace('宁蒗彝族自治县','宁蒗',$order['area2']);
		$order['area2'] = str_replace('宁洱哈尼族彝族自治县','宁洱',$order['area2']);
		$order['area2'] = str_replace('墨江哈尼族自治县','墨江',$order['area2']);
		$order['area2'] = str_replace('景东彝族自治区','景东',$order['area2']);
		$order['area2'] = str_replace('景谷傣族彝族自治县','景谷',$order['area2']);
		$order['area2'] = str_replace('镇沅彝族哈尼族拉祜族自治县','镇沅',$order['area2']);
		$order['area2'] = str_replace('江城哈尼族彝族自治县','江城',$order['area2']);
		$order['area2'] = str_replace('孟连傣族拉祜族佤族自治县','孟连',$order['area2']);
		$order['area2'] = str_replace('澜沧拉祜族自治县','澜沧',$order['area2']);
		$order['area2'] = str_replace('西盟佤族自治县','西盟',$order['area2']);
		$order['area2'] = str_replace('双江拉祜族佤族布朗族傣族自治县','双江',$order['area2']);
		$order['area2'] = str_replace('耿马傣族佤族自治县','耿马',$order['area2']);
		$order['area2'] = str_replace('沧源佤族自治县','沧源县',$order['area2']);
		$order['area2'] = str_replace('蒙自市','蒙自县',$order['area2']);
		$order['area2'] = str_replace('弥勒市','弥勒县',$order['area2']);
		$order['area2'] = str_replace('屏边苗族自治县','屏边',$order['area2']);
		$order['area2'] = str_replace('金平苗族瑶族傣族自治县','金平',$order['area2']);
		$order['area2'] = str_replace('河口瑶族自治县','河口',$order['area2']);
		$order['area2'] = str_replace('漾濞彝族自治县','漾濞',$order['area2']);
		$order['area2'] = str_replace('巍山彝族回族自治县','巍山',$order['area2']);
		$order['area2'] = str_replace('南涧彝族自治县','南涧',$order['area2']);
		$order['area2'] = str_replace('贡山独龙族怒族自治县','贡山',$order['area2']);
		$order['area2'] = str_replace('兰坪白族普米族自治县','兰坪',$order['area2']);
		$order['area2'] = str_replace('维西傈僳族自治县','维西',$order['area2']);
		$order['area2'] = str_replace('西藏自治区','西藏',$order['area2']);
		$order['area2'] = str_replace('日喀则市','日喀则',$order['area2']);
		$order['area2'] = str_replace('昌都市','昌都',$order['area2']);
		$order['area2'] = str_replace('林芝市','林芝',$order['area2']);
		$order['area2'] = str_replace('山南市','山南',$order['area2']);
		$order['area2'] = str_replace('那曲地区','那曲',$order['area2']);
		$order['area2'] = str_replace('阿里地区','阿里',$order['area2']);
		$order['area2'] = str_replace('堆龙德庆区','堆龙德庆县',$order['area2']);
		$order['area2'] = str_replace('乃东区','乃东县',$order['area2']);
		$order['area2'] = str_replace('户县','鄂邑区',$order['area2']);
		$order['area2'] = str_replace('彬县','彬州市',$order['area2']);
		$order['area2'] = str_replace('南郑县','南郑区',$order['area2']);
		$order['area2'] = str_replace('神木县','神木市',$order['area2']);
		$order['area2'] = str_replace('临夏回族自治州','临夏州',$order['area2']);
		$order['area2'] = str_replace('甘南藏族自治州','甘南州',$order['area2']);
		$order['area2'] = str_replace('张家川回族自治县','张家川县',$order['area2']);
		$order['area2'] = str_replace('天祝藏族自治县','天祝县',$order['area2']);
		$order['area2'] = str_replace('肃南裕固族自治县','肃南',$order['area2']);
		$order['area2'] = str_replace('肃北蒙古族自治县','肃北',$order['area2']);
		$order['area2'] = str_replace('阿克塞哈萨克族自治县','阿克塞',$order['area2']);
		$order['area2'] = str_replace('积石山保安族东乡族撒拉族自治县','积石山县',$order['area2']);
		$order['area2'] = str_replace('海北藏族自治州','海北州',$order['area2']);
		$order['area2'] = str_replace('黄南藏族自治州','黄南州',$order['area2']);
		$order['area2'] = str_replace('海南藏族自治州','海南州',$order['area2']);
		$order['area2'] = str_replace('果洛藏族自治州','果洛州',$order['area2']);
		$order['area2'] = str_replace('玉树藏族自治州','玉树州',$order['area2']);
		$order['area2'] = str_replace('海西蒙古族藏族自治州','海西州',$order['area2']);
		$order['area2'] = str_replace('大通回族土族自治县','大通',$order['area2']);
		$order['area2'] = str_replace('民和回族土族自治县','民和县',$order['area2']);
		$order['area2'] = str_replace('互助土族自治县','互助县',$order['area2']);
		$order['area2'] = str_replace('化隆回族自治县','化隆县',$order['area2']);
		$order['area2'] = str_replace('循化撒拉族自治县','循化县',$order['area2']);
		$order['area2'] = str_replace('门源回族自治县','门源县',$order['area2']);
		$order['area2'] = str_replace('哈密市','哈密地区',$order['area2']);
		$order['area2'] = str_replace('昌吉回族自治州','昌吉州',$order['area2']);
		$order['area2'] = str_replace('博尔塔拉蒙古自治州','博尔塔拉州',$order['area2']);
		$order['area2'] = str_replace('巴音郭楞蒙古自治州','巴音郭楞州',$order['area2']);
		$order['area2'] = str_replace('克孜勒苏柯尔克孜自治州','克孜勒苏',$order['area2']);
		$order['area2'] = str_replace('伊犁哈萨克自治州','伊犁州',$order['area2']);
		$order['area2'] = str_replace('伊州区','哈密市',$order['area2']);
		$order['area2'] = str_replace('巴里坤哈萨克自治县','巴里坤',$order['area2']);
		$order['area2'] = str_replace('木垒哈萨克自治县','木垒县',$order['area2']);
		$order['area2'] = str_replace('焉耆回族自治县','焉耆县',$order['area2']);
		$order['area2'] = str_replace('塔什库尔干塔吉克自治县','塔什库尔干',$order['area2']);
		$order['area2'] = str_replace('察布查尔锡伯自治县','察布查尔',$order['area2']);

		$data['area_info']=str_replace(',',' ',$order['area2']);
		$data['address']=$order['address'];
		$data['material']=$oglist[0]['ggname'];
		$data['quantity']=$quantity;
		$data['order_amount'] = $order['totalprice'];
		$data['remark']='请准时送达';
		$data['picurl']= $oglist[0]['pic'];
		$data['card_description']= explode('^_^',$order['field2'])[1];
		if(!$data['card_description']){
			$formdata = \app\models\Freight::getformdata($order['id'],'shop_order');
			foreach($formdata as $k=>$v){
				if($v[0] == '卡片内容'){
					$data['card_description'] = $v[1];
				}
			}
		}
		if(!$data['card_description']) $data['card_description'] = '';
		$data['seller_order']=$order['ordernum'];
		$data['is_payment']=0;
		$data['examine_status']=0;
		//$data['payment_password'] = encrypt('支付密码',$keycode);  //支付密码加密
		//$data['member_name'] = '会员名';  //指定花店的会员名

		$data['timestamp'] = time();
		$data['api_account'] = $api_account;
		$data['send_mobile'] = '18137816869';
		ksort($data);
		$str = urldecode(http_build_query($data));
		$hash = mhash(MHASH_SHA256, $str, $keycode);
		$data['sign'] = base64_encode($hash);
		$url = 'https://open.huawa.com/newapi/import';
		$param = $data;
		$result = curl_post($url,$param);
		\think\facade\Log::write($result);

		return ['status'=>1,'msg'=>''];
	}
}