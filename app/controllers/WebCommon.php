<?php


// +----------------------------------------------------------------------
// | 控制台
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class WebCommon extends Common
{
	public $uid;
	public $user;
	public $menudata = [];
    public function initialize(){
		parent::initialize();
		$this->uid = session('BST_ID');
		$this->user = Db::name('admin_user')->where(['id'=>$this->uid])->find();
		if(!session('BST_ID') || !$this->user || $this->user['isadmin'] != 2){
			showmsg('无访问权限');
		}

		$menudata = \app\commons\Menu::getdata2($this->uid);
		$this->menudata = $menudata;

		$childmenu = [];
		$menuname = '';
		$thispath = request()->controller() .'/'.request()->action();
        View::assign('thispath',$thispath);
		if(!request()->isAjax()){
			foreach($this->menudata as $v){
				if(!$v['child']) continue;
				foreach($v['child'] as $v2){
					if($v2['path'] == $thispath){
						$menuname = $v2['name'];
						foreach($v['child'] as $v_2){
							if(!$v_2['hide']) $childmenu[] = $v_2;
						}
						break;
					}
				}
			}
		}
		View::assign('childmenu',$childmenu);
	}
}