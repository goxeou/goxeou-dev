<?php


// +----------------------------------------------------------------------
// | 短视频系统设置
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class ShortvideoSysset extends Common
{
    public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无访问权限');
	}
    public function index(){
		$info = Db::name('shortvideo_sysset')->where('aid',aid)->find();
		if(!$info){
			Db::name('shortvideo_sysset')->insert(array(
				'aid'=>aid
			));
			$info = Db::name('shortvideo_sysset')->where('aid',aid)->find();
		}

        View::assign('info',$info);
		return View::fetch();
    }
	public function save(){
		$info = input('post.info/a');
		$info['aid'] = aid;
        if(Db::name('shortvideo_sysset')->where('aid',aid)->find()){
			Db::name('shortvideo_sysset')->where('aid',aid)->update($info);
		}else{
			Db::name('shortvideo_sysset')->insert($info);
		}

		
		\app\commons\System::plog('短视频系统设置');
		return json(['status'=>1,'msg'=>'保存成功','url'=>(string)url('index')]);
	}
}