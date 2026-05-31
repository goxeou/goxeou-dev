<?php


// +----------------------------------------------------------------------
// | 商城 商品分组
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class ShopGroup extends Common
{
    public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无访问权限');
	}
	//分组列表
    public function index(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'id desc';
			}
			$where = array();
			$where[] = ['aid','=',aid];
			if(input('param.name')) $where[] = ['name','like','%'.input('param.name').'%'];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['status','=',input('param.status')];
			//dump($where);
			$count = 0 + Db::name('shop_group')->where($where)->count();
			$data = Db::name('shop_group')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
    }
	//编辑
	public function edit(){
		if(input('param.id')){
			$info = Db::name('shop_group')->where('aid',aid)->where('id',input('param.id/d'))->find();
		}else{
			$info = array('id'=>'');
		}
		$pcatelist = Db::name('shop_group')->where('aid',aid)->order('sort desc,id')->select()->toArray();
		View::assign('info',$info);
		return View::fetch();
	}
	//保存
	public function save(){
		$info = input('post.info/a');
		if($info['id']){
			Db::name('shop_group')->where('aid',aid)->where('id',$info['id'])->update($info);
			\app\commons\System::plog('编辑商城商品分组'.$info['id']);
		}else{
			$info['aid'] = aid;
			$info['createtime'] = time();
			$id = Db::name('shop_group')->insertGetId($info);
			\app\commons\System::plog('添加商城商品分组'.$id);
		}
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	//删除
	public function del(){
		$ids = input('post.ids/a');
		Db::name('shop_group')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('商城商品分组删除'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
}