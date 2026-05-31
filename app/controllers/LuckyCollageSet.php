<?php


// +----------------------------------------------------------------------
// | 幸运拼团商城 系统设置
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class LuckyCollageSet extends Common
{
	public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无操作权限');
	}
    public function index(){
		$info = Db::name('lucky_collage_sysset')->where('aid',aid)->find();
		if(!$info){
			Db::name('lucky_collage_sysset')->insert(['aid'=>aid]);
			$info = Db::name('lucky_collage_sysset')->where('aid',aid)->find();
		}
		View::assign('info',$info);
		return View::fetch();
    }
	public function save(){
		$info = input('post.info/a');
		$info['timeset'] = implode(',',$timeset);
		Db::name('lucky_collage_sysset')->where('aid',aid)->update($info);	
		\app\commons\System::plog('幸运拼团系统设置');
		return json(['status'=>1,'msg'=>'保存成功','url'=>(string)url('index')]);
	}
}