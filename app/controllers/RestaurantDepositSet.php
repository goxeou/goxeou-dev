<?php


// +----------------------------------------------------------------------
// | 寄存设置
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class RestaurantDepositSet extends Common
{
    public function initialize(){
		parent::initialize();
		//if(bid > 0) showmsg('无访问权限');
	}
    public function index(){
		$info = Db::name('restaurant_deposit_sysset')->where('aid',aid)->where('bid',bid)->find();
		if(!$info){
			Db::name('restaurant_deposit_sysset')->insert(['aid'=>aid,'bid'=>bid]);
			$info = Db::name('restaurant_takeaway_sysset')->where('aid',aid)->where('bid',bid)->find();
		}
		View::assign('info',$info);
		return View::fetch();
    }
	public function save(){
		$info = input('post.info/a');
		Db::name('restaurant_deposit_sysset')->where('aid',aid)->where('bid',bid)->update($info);
		return json(['status'=>1,'msg'=>'保存成功','url'=>(string)url('index')]);
	}

}