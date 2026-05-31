<?php


// +----------------------------------------------------------------------
// | 砍价活动 系统设置
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class KanjiaSet extends Common
{
    public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无访问权限');
	}
    public function index(){
		$info = Db::name('kanjia_sysset')->where('aid',aid)->find();
		if(!$info){
			$info = Db::name('kanjia_sysset')->where('aid',aid)->find();
		}
		View::assign('info',$info);
		return View::fetch();
    }
	public function save(){
		$info = input('post.info/a');
		Db::name('kanjia_sysset')->where('aid',aid)->update($info);
		\app\commons\System::plog('砍价系统设置');
		return json(['status'=>1,'msg'=>'保存成功','url'=>(string)url('index')]);
	}
}