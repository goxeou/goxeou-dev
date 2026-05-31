<?php


// +----------------------------------------------------------------------
// | 文章系统设置
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class ArticleSet extends Common
{
    public function initialize(){
		parent::initialize();
	}
    public function set(){
		$info = Db::name('article_set')->where('aid',aid)->where('bid',bid)->find();
		if(!$info){
			Db::name('article_set')->insert(['aid'=>aid,'bid'=>bid]);
			$info = Db::name('article_set')->where('aid',aid)->where('bid',bid)->find();
		}
		View::assign('info',$info);
		return View::fetch();
    }
	public function save(){
		$info = input('post.info/a');
		Db::name('article_set')->where('aid',aid)->where('bid',bid)->update($info);
		\app\commons\System::plog('文章管理系统设置');
		return json(['status'=>1,'msg'=>'保存成功','url'=>true]);
	}
}
