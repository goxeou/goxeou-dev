<?php


// +----------------------------------------------------------------------
// | 首页
// +----------------------------------------------------------------------
namespace app\controllers;
use app\BaseController;
use think\facade\View;
use think\facade\Db;

class W extends BaseController
{
    public function a(){
		$code = input('param.x');
		$info = Db::name('wx_url')->where('code',$code)->find();
		if(!$info) showmsg('链接已失效');
		if($info['endtime'] < time()) showmsg('链接已失效');

		$pathurl = explode('?',$info['path']);

		$url = 'https://api.weixin.qq.com/wxa/generate_urllink?access_token='.\app\commons\Wechat::access_token($info['aid']);
		$postdata = [];
		$postdata['path'] = $pathurl[0];
		if($pathurl[1]){
			$postdata['query'] = $pathurl[1];
		}
		if(input('param.pid')){
			$postdata['query'] = $postdata['query'] ? $postdata['query'].'&pid='.input('param.pid') : 'pid='.input('param.pid');
		}
		$postdata['expire_type'] = '1';
		$postdata['expire_interval'] = '30';
		$rs = curl_post($url,jsonEncode($postdata));
		$rs = json_decode($rs,true);
		if($rs['errcode']!=0){
			 showmsg($rs['errmsg']);
			//return json(['status'=>0,'msg'=>$rs['errcode'].'：'.$rs['errmsg']]);
		}
		//die(curl_get($rs['url_link']));
		header('Location:'.$rs['url_link']);
    }
}
