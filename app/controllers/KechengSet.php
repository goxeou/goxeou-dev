<?php


// +----------------------------------------------------------------------
// |  课程设置
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class KechengSet extends Common
{
    public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无访问权限');
	}
    public function index(){
		$info = Db::name('kecheng_sysset')->where('aid',aid)->find();
		View::assign('info',$info);
		return View::fetch();
    }
	public function save(){
		$info = input('post.info/a');
		$setinfo = Db::name('kecheng_sysset')->where('aid',aid)->find();
		if($setinfo){
			Db::name('kecheng_sysset')->where('aid',aid)->update($info);
		} else {
			$info['aid'] = aid;
			Db::name('kecheng_sysset')->insert($info);
		}
		\app\commons\System::plog('课程系统设置');
		return json(['status'=>1,'msg'=>'保存成功','url'=>(string)url('index')]);
	}
}