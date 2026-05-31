<?php


// +----------------------------------------------------------------------
// | 拼团商城 系统设置
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class CollageSet extends Common
{
	public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无操作权限');
	}
    public function index(){
		$info = Db::name('collage_sysset')->where('aid',aid)->find();
		if(!$info){
			$info = Db::name('collage_sysset')->where('aid',aid)->find();
		}
		View::assign('info',$info);
		return View::fetch();
    }
	public function save(){
		$info = input('post.info/a');
		Db::name('collage_sysset')->where('aid',aid)->update($info);	
		\app\commons\System::plog('拼团系统设置');
		return json(['status'=>1,'msg'=>'保存成功','url'=>(string)url('index')]);
	}
}