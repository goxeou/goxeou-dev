<?php


// +----------------------------------------------------------------------
// | 帮助中心
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class Help extends Common
{
    public function index(){
    	$where = [];
		$where[] = ['status','=',1];
		$where1 = '1=1';
		$where2 = '1=1';
		$helplist = Db::name('help')->where($where)->whereRaw($where1)->whereRaw($where2)->order('sort desc,id')->select()->toArray();
		View::assign('helplist',$helplist);
		return View::fetch();
    }
	public function detail(){
		$id = input('param.id/d');
		$where = [];
		$where[] = ['id','=',$id];
		$where[] = ['status','=',1];
		$where1 = '1=1';
		$where2 = '1=1';
		$info = Db::name('help')->where($where)->whereRaw($where1)->whereRaw($where2)->find();
		View::assign('info',$info);
		return View::fetch();
	}
}