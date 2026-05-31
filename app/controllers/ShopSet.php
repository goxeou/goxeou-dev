<?php


// +----------------------------------------------------------------------
// | 商城 商城设置
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class ShopSet extends Common
{
    public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无访问权限');
	}
    public function index(){
		$info = Db::name('shop_sysset')->where('aid',aid)->find();
        View::assign('expressdata',express_data(['aid'=>aid,'bid'=>bid]));
		View::assign('info',$info);
		return View::fetch();
    }
	public function save(){
		$info = input('post.info/a');
        Db::name('shop_sysset')->where('aid',aid)->update($info);
		\app\commons\System::plog('商城系统设置');
		return json(['status'=>1,'msg'=>'保存成功','url'=>(string)url('index')]);
	}
}