<?php
//custom_file(mendian_upgrade)
// +----------------------------------------------------------------------
// | 门店设置
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class MendianSet extends Common
{
	public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无操作权限');
	}
	//系统设置
	public function index(){
		if(request()->isPost()){
			$rs = Db::name('mendian_sysset')->where('aid',aid)->find();
			$info = input('post.info/a');


			if($rs){
				Db::name('mendian_sysset')->where('aid',aid)->update($info);
				\app\commons\System::plog('门店设置');
			}else{
				$info['aid'] = aid;
				Db::name('mendian_sysset')->insert($info);
			}
			return json(['status'=>1,'msg'=>'设置成功','url'=>true]);
		}
		$info = Db::name('mendian_sysset')->where('aid',aid)->find();
		if(!$info){
			Db::name('mendian_sysset')->insert(['aid'=>aid]);
			$info = Db::name('mendian_sysset')->where('aid',aid)->find();
		}
	    $levelList = Db::name('member_level')->where('aid',aid)->order('sort')->select()->toArray();
		View::assign('levelList',$levelList);
		View::assign('info',$info);
		return View::fetch();
	}
}
