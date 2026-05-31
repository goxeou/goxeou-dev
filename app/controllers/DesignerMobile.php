<?php


// +----------------------------------------------------------------------
// | 移动端后台页面配置
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class DesignerMobile extends Common
{
	public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无访问权限');
	}
    public function index(){

		$info = Db::name('designer_mobile')->where('aid',aid)->find();
		if(!$info){
			$info = array(
				'aid'=>aid,
				'updatetime'=>time(),
				'data'=>jsonEncode([
					"bgimg"     => PRE_URL.'/static/img/admin/headbgimg.png',
				])
			);
			$id = Db::name('designer_mobile')->insertGetId($info);
			$info['id'] = $id;
		}

		$data = json_decode($info['data'],true);
		View::assign('data',$data);
		View::assign('info',$info);

		return View::fetch();
    }
	public function save(){

		$info = input('post.info/a');
		$id = $info['id']?$info['id']:0;
		$info['data'] = jsonEncode($info['data']);
		$info['updatetime'] = time();

		unset($info['id']);
		if($id){
			Db::name('designer_mobile')->where('id',$id)->update($info);
		}else{
			Db::name('designer_mobile')->insert($info);
		}

		\app\commons\System::plog('移动端后台设置');
		return json(['status'=>1,'msg'=>'保存成功','url'=>(string)url('index')]);
	}

}