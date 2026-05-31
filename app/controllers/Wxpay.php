<?php


// +----------------------------------------------------------------------
// | 公众号支付设置
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class Wxpay extends Common
{
    public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无访问权限');
	}
	//公众号支付设置
    public function set(){
		if(request()->isPost()){
			$info = input('post.info/a');
			$rs = Db::name('admin_setapp_wx')->where('aid',aid)->find();
			$info['wxpay_mchid'] = trim($info['wxpay_mchid']);
			$info['wxpay_mchkey'] = trim($info['wxpay_mchkey']);
			$info['wxpay_sub_mchid'] = trim($info['wxpay_sub_mchid']);
			$info['wxpay_apiclient_cert'] = str_replace(PRE_URL.'/','',$info['wxpay_apiclient_cert']);
			$info['wxpay_apiclient_key'] = str_replace(PRE_URL.'/','',$info['wxpay_apiclient_key']);
			if(!empty($info['wxpay_apiclient_cert']) && substr($info['wxpay_apiclient_cert'], -4) != '.pem'){
				return json(['status'=>0,'msg'=>'PEM证书格式错误']);
			}
			if(!empty($info['wxpay_apiclient_key']) && substr($info['wxpay_apiclient_key'], -4) != '.pem'){
				return json(['status'=>0,'msg'=>'证书密钥格式错误']);
			}
			$info['sxpay_mno'] = trim($info['sxpay_mno']);
			Db::name('admin_setapp_wx')->where('aid',aid)->update($info);
			\app\commons\System::plog('微信小程序支付设置');
			return json(['status'=>1,'msg'=>'设置成功','url'=>true]);
		}
		$info = Db::name('admin_setapp_wx')->where('aid',aid)->find();
		if(!$info) Db::name('admin_setapp_wx')->insert(['aid'=>aid]);
		View::assign('info',$info);
		return View::fetch();
	}
}