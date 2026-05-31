<?php


// +----------------------------------------------------------------------
// | 后台登录
// +----------------------------------------------------------------------
namespace app\controllers;
use app\BaseController;
use think\facade\View;
use think\facade\Db;

class Gw extends BaseController
{
	public $webinfo;
    public function initialize(){
		$request = request();

     	$webinfo = Db::name('sysset')->where('name','webinfo')->value('value');
		$tinfo = Db::name('admin_set')->where(['aid'=>4])->find();
		$webinfo = json_decode($webinfo,true);

		$webinfo['logo'] = $tinfo['logo'];
    	$webinfo['name'] = $tinfo['name'];
		$webinfo['copyright'] = $tinfo['desc'];
		$tinfo = Db::name('admin_set')->where(['aid'=>4])->find();
		View::assign('webinfo',$webinfo);
		View::assign('webname',$tinfo['name']);
	}
    //登录页
	public function index(){
	
	
		$webinfo = Db::name('sysset')->where('name','webinfo')->value('value');
		$tinfo = Db::name('admin_set')->where(['aid'=>4])->find();
			$webinfo = json_decode($webinfo,true);
		$webinfo['logo'] = $tinfo['logo'];
		$webinfo['name'] = $tinfo['name'];
		$webinfo['copyright'] = $tinfo['desc'];
		View::assign('webinfo',$webinfo);
		return View::fetch();
    }

}
