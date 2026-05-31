<?php


// +----------------------------------------------------------------------
// | 预约系统设置
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class YuyueSet extends Common
{
    public function initialize(){
		parent::initialize();
	}
    public function set(){
		$info = Db::name('yuyue_set')->where('aid',aid)->where('bid',bid)->find();
		if(!$info){
			Db::name('yuyue_set')->insert(['aid'=>aid,'bid'=>bid]);
			$info = Db::name('yuyue_set')->where('aid',aid)->where('bid',bid)->find();
		}
		if(!$info['formdata']){
			$info['formdata'] = json_encode([
				['key'=>'input','val1'=>'备注','val2'=>'选填，请输入备注信息','val3'=>'0'],	
			]);
		}
		if($info['yyzhouqi']){
			$info['yyzhouqi'] = explode(',',$info['yyzhouqi']);
		}
		View::assign('info',$info);

		return View::fetch();
    }
	public function save(){
		$info = input('post.info/a');
		if($info['yyzhouqi']) $info['yyzhouqi'] =  implode(',',$info['yyzhouqi']);
		Db::name('yuyue_set')->where('aid',aid)->where('bid',bid)->update($info);
		\app\commons\System::plog('预约派单系统设置');
		return json(['status'=>1,'msg'=>'保存成功','url'=>true]);
	}

}
