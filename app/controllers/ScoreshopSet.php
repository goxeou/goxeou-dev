<?php


// +----------------------------------------------------------------------
// | 积分商城 系统设置
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class ScoreshopSet extends Common
{
    public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无访问权限');
	}
    public function index(){
		$info = Db::name('scoreshop_sysset')->where('aid',aid)->find();
		if(!$info){
			$info = Db::name('scoreshop_sysset')->where('aid',aid)->find();
		}
		$info['gettj'] = explode(',',$info['gettj']);
        $default_cid = Db::name('member_level_category')->where('aid',aid)->where('isdefault', 1)->value('id');
        $default_cid = $default_cid ? $default_cid : 0;
        $levellist = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->order('sort,id')->select()->toArray();
		View::assign('levellist',$levellist);
		View::assign('info',$info);
		return View::fetch();
    }
	public function save(){
		$info = input('post.info/a');
		$info['gettj'] = implode(',',$info['gettj']);
		Db::name('scoreshop_sysset')->where('aid',aid)->update($info);
		\app\commons\System::plog('积分商城系统设置');
		return json(['status'=>1,'msg'=>'保存成功','url'=>(string)url('index')]);
	}
}