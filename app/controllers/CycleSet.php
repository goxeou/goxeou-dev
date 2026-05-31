<?php


// +----------------------------------------------------------------------
// | 周期购 系统设置
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class CycleSet extends Common
{
	public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无操作权限');
	}
    public function index(){
		$info = Db::name('cycle_sysset')->where('aid',aid)->find();
		if(!$info){
			$info = Db::name('cycle_sysset')->where('aid',aid)->find();
		}
		View::assign('info',$info);
		return View::fetch();
    }
	public function save(){
		$info = input('post.info/a');
        $set = Db::name('cycle_sysset')->where('aid',aid)->find();
      
        if($set){
            Db::name('cycle_sysset')->where('aid',aid)->update($info);
        }else{
            $info['aid'] = aid;
            Db::name('cycle_sysset')->insert($info);
        }
		\app\commons\System::plog('周期购系统设置');
		return json(['status'=>1,'msg'=>'保存成功','url'=>(string)url('index')]);
	}
}
