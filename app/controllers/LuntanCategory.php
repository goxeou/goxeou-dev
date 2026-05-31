<?php


// +----------------------------------------------------------------------
// | 论坛 分类管理
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class LuntanCategory extends Common
{
	public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无操作权限');
		$this->defaultSet();
	}
	//分类列表
    public function index(){
		if(request()->isAjax()){
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'sort desc,id';
			}
			$page = input('param.page');
			$limit = input('param.limit');
			$data = [];
			$count = 0 + Db::name('luntan_category')->where('aid',aid)->where('pid',0)->count();
			$cate0 = Db::name('luntan_category')->where('aid',aid)->where('pid',0)->page($page,$limit)->order($order)->select()->toArray();
			foreach($cate0 as $c0){
				$data[] = $c0;
				$cate1 = Db::name('luntan_category')->where('aid',aid)->where('pid',$c0['id'])->order($order)->select()->toArray();
				foreach($cate1 as $k1=>$c1){
					if($k1 < count($cate1)-1){
						$c1['name'] = '<span style="color:#aaa">&nbsp;&nbsp;&nbsp;&nbsp;├ </span>'.$c1['name'];
					}else{
						$c1['name'] = '<span style="color:#aaa">&nbsp;&nbsp;&nbsp;&nbsp;└ </span>'.$c1['name'];
					}
					$data[] = $c1;
				}
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
    }
	//编辑
	public function edit(){
		if(input('param.id')){
			$info = Db::name('luntan_category')->where('aid',aid)->where('id',input('param.id/d'))->find();
			}else{
			$info = array('id'=>'');

		}
		if(input('param.pid')) $info['pid'] = input('param.pid');
		$pcatelist = Db::name('luntan_category')->where('aid',aid)->where('pid',0)->where('id','<>',$info['id'])->order('sort desc,id')->select()->toArray();
		View::assign('info',$info);
		View::assign('pcatelist',$pcatelist);
		return View::fetch();
	}
	//保存
	public function save(){
		$info = input('post.info/a');
		if($info['id']){
			Db::name('luntan_category')->where('aid',aid)->where('id',$info['id'])->update($info);
			\app\commons\System::plog('编辑用户论坛分类'.$info['id']);
		}else{
			$info['aid'] = aid;
			$info['createtime'] = time();
			$id = Db::name('luntan_category')->insertGetId($info);
			\app\commons\System::plog('添加用户论坛分类'.$id);
		}
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	//删除
	public function del(){
		$ids = input('post.ids/a');
		Db::name('luntan_category')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('删除用户论坛分类'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
    function defaultSet(){
        $set = Db::name('luntan_sysset')->where('aid',aid)->find();
        if(!$set){
            Db::name('luntan_sysset')->insert(['aid'=>aid]);
        }
    }
}