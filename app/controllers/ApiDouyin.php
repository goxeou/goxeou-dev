<?php


// +----------------------------------------------------------------------
// | 抖音开放平台 消息推送通知
// +----------------------------------------------------------------------
namespace app\controllers;
use app\BaseController;
use think\facade\Db;
class ApiDouyin extends BaseController
{
    public function initialize(){

	}
	public function notify(){
		
		//\think\facade\Log::write('-----param-----');
		//\think\facade\Log::write(input('param.'));
		$param = input('param.');

		$aid = $param['aid'];
		$postStr = file_get_contents('php://input');
		
		$header = getallheaders();
		//\think\facade\Log::write(getallheaders());
		//\think\facade\Log::write($postStr);

		$appinfo = Db::name('douyin_sysset')->where('aid',$aid)->find();
		if(!$appinfo) die();

		$sign = md5($appinfo['app_id'].$postStr.$appinfo['app_secret']);
		//\think\facade\Log::write($sign);
		//\think\facade\Log::write($header['Event-Sign']);


		if($sign != $header['Event-Sign']){
			\think\facade\Log::write('抖音消息推送: 校验失败');
			die('校验失败');
		}
		$postData = json_decode($postStr,true);
		foreach($postData as $k=>$v){
			//\think\facade\Log::write($v);
			if($v['tag'] == '400'){
				$data = json_decode($v['data'],true);
				//\think\facade\Log::write($data);
				$product_id = $data['product_id'];
				$product = Db::name('shop_product')->where('douyin_product_id',$product_id)->find();
				if(!$product) continue;
				$douyin_status = $product['douyin_status'];

				if($data['event'] == '11' || $data['event'] == '4'){
					$douyin_status = 1;
				}
				$product = Db::name('shop_product')->where('douyin_product_id',$product_id)->update([
					'douyin_check_status'=>$data['event'],
					'douin_check_reason'=>$data['check_reject_reason'],
					'douyin_status'=>$douyin_status,
				]);
			}
		}
		
		die('{"code":0,"msg":"success"}');
	}
	
}