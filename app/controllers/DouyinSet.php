<?php


// +----------------------------------------------------------------------
// | 商城 商城设置
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class DouyinSet extends Common
{
    public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无访问权限');
	}
    public function index(){
		$info = Db::name('douyin_sysset')->where('aid',aid)->find();
		if(!$info){
			Db::name('douyin_sysset')->insert(['aid'=>aid]);
			$info = Db::name('douyin_sysset')->where('aid',aid)->find();
		}
		View::assign('info',$info);
		return View::fetch();
    }
	public function save(){
		$info = input('post.info/a');
		Db::name('douyin_sysset')->where('aid',aid)->update($info);
		\app\commons\System::plog('抖音融合方案设置');
		return json(['status'=>1,'msg'=>'保存成功','url'=>(string)url('index')]);
	}
}