<?php


namespace app\commons;
use think\facade\Db;
use app\commons\System;
class Qq
{
	//获取access_token
	public static function access_token($aid){
		$appinfo = System::appinfo($aid,'qq');
		$appid = $appinfo['appid'];
		$appsecret = $appinfo['appsecret'];
		if(!$appid) return '';
		if($appinfo['access_token'] && $appinfo['expires_time'] > time()){
			return $appinfo['access_token'];
		}else{
			if(!$appsecret) return '';
			$url = "https://api.q.qq.com/api/getToken?grant_type=client_credential&appid={$appid}&secret={$appsecret}";
			$res = json_decode(request_get($url));
			$access_token = $res->access_token;
			if($access_token) {
				Db::name('admin_setapp_qq')->where('appid',$appid)->update(['access_token'=>$access_token,'expires_time'=>time()+7000]);
				return $access_token;
			}else{
				//\think\facade\Log::write($res);
				//return '';
				echojson(['status'=>0,'msg'=>$res->errmsg]);
			}
		}
	}
}